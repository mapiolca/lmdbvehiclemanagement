<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/lmdbvehiclequartixservice.class.php';

/** Three native, bounded and restartable QWS jobs. */
class LmdbVehicleQuartixCron
{
	/** @var DoliDB */ public $db;
	/** @var string */ public $error = '';
	/** @var list<string> */ public $errors = array();
	/** @var string */ public $output = '';
	/** @param DoliDB $db Database */
	public function __construct($db) { $this->db = $db; }
	/** @return int */ public function positions() { return $this->run('positions'); }
	/** @return int */ public function odometer() { return $this->run('odometer'); }
	/** @return int */ public function usage() { return $this->run('usage'); }

	/** @param string $kind positions/odometer/usage @return int 0 success, -1 failure */
	public function run($kind)
	{
		global $conf, $user, $langs;
		$this->error = ''; $this->errors = array(); $this->output = '';
		$entity = (int) $conf->entity;
		$service = new LmdbVehicleQuartixService($this->db);
		$locked = false; $client = null; $processed = 0; $failed = 0; $lastVehicle = 0;
		try {
			if (!in_array($kind, array('positions', 'odometer', 'usage'), true)) throw new RuntimeException('QxInvalidEndpoint');
			$langs->load('lmdbvehiclemanagement@lmdbvehiclemanagement');
			if (!isModEnabled('lmdbvehiclemanagement') || !getDolGlobalInt(LmdbVehicleQuartixConfig::PREFIX.'ENABLED')) { $this->output = $langs->transnoentities('QxDisabled'); return 0; }
			$unavailable = LmdbVehicleQuartixConfig::unavailableReason('jobs');
			if ($unavailable !== '') { $this->error = $unavailable; $this->output = $langs->transnoentities($unavailable); return -1; }
			if (!LmdbVehicleQuartixConfig::can($user, 'sync') || ($kind === 'odometer' && !LmdbVehicleQuartixConfig::isAdmin($user) && !$user->hasRight('lmdbvehiclemanagement', 'odometer', 'write'))) throw new RuntimeException('QxAccessDenied');
			if (!$service->lock($entity)) { $this->output = $langs->transnoentities('QxBusy'); return 0; }
			$locked = true;
			$cfg = (new LmdbVehicleQuartixConfig($this->db))->load($entity);
			if (!LmdbVehicleQuartixConfig::supported()) throw new RuntimeException('QxRequiresCrypto');
			$service->write('INSERT IGNORE INTO '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_job (entity,job_kind) VALUES (".$entity.",'".$kind."')");
			$state = $service->rows('SELECT * FROM '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_job WHERE entity=".$entity." AND job_kind='".$kind."'")[0];
			$throttle = $service->rows('SELECT MAX(retry_at) AS retry_at FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_job WHERE entity='.$entity)[0];
			if (!empty($throttle->retry_at) && $this->db->jdate($throttle->retry_at) > dol_now()) { $this->output = $langs->transnoentities('QxRateLimited'); return 0; }
			$service->write('UPDATE '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_job SET last_attempt='".$this->db->idate(dol_now())."' WHERE entity=".$entity." AND job_kind='".$kind."'");
			if ($kind !== 'usage' && LmdbVehicleQuartixConfig::unavailableReason('timestamps') !== '') throw new RuntimeException('QxTimeUnconfirmed');
			$client = $this->createClient($entity);
			if ($kind === 'usage') {
				// Bound retention work, including paused associations. UTC is the retention clock.
				$cutoff = (new DateTimeImmutable('@'.dol_now()))->modify('-12 months')->format('Y-m-d');
				$service->write('DELETE FROM '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_usage WHERE entity=".$entity." AND usage_day<'".$cutoff."' LIMIT 5000");
			}
			$lastVehicle = (int) $state->last_vehicle;
			$base = 'SELECT l.* FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link AS l INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid=l.fk_vehicle AND v.entity=l.entity WHERE l.entity='.$entity.' AND l.active=1';
			// The worker wakes every 15 minutes; each odometer is imported once per UTC day.
			$today = gmdate('Y-m-d', dol_now());
			if ($kind === 'odometer') $base .= " AND (l.odometer_synced IS NULL OR l.odometer_synced<'".$today."')";
			$batchSize = $kind === 'usage' ? 20 : 100;
			$links = $service->rows($base.' AND l.rowid>'.$lastVehicle.' ORDER BY l.rowid LIMIT '.$batchSize);
			if (!$links) $links = $service->rows($base.' ORDER BY l.rowid LIMIT '.$batchSize);
			$deadline = microtime(true) + 45;
			$byVehicle = array();
			if ($links && $kind !== 'usage') {
				$ids = array_map(static function ($link) { return (int) $link->remote_id; }, $links);
				$data = $client->get('/vehicles/'.($kind === 'positions' ? 'live' : 'odometer'), array('VehicleIDList' => implode(',', $ids)));
				foreach ($data as $row) {
					if (!is_array($row)) throw new RuntimeException('QxInvalidResponse');
					$remote = LmdbVehicleQuartixRules::id($row['VehicleID'] ?? null);
					if (!in_array($remote, $ids, true) || isset($byVehicle[$remote])) throw new RuntimeException('QxInvalidResponse');
					$byVehicle[$remote] = $row;
				}
			}
			foreach ($links as $link) {
				if (microtime(true) >= $deadline) break;
				try {
					if ($kind === 'usage') $this->syncUsage($service, $client, $link);
					else {
						$data = isset($byVehicle[(int) $link->remote_id]) ? array($byVehicle[(int) $link->remote_id]) : array();
						if (!$data) throw new RuntimeException('QxNoVehicleData');
						if ($kind === 'positions') $service->savePosition($link, $data[0], $cfg['TIME_MODE']);
						else {
							$date = LmdbVehicleQuartixRules::timestamp($data[0]['EstimateDateTime'] ?? null, $cfg['TIME_MODE'], (string) $link->timezone);
							if ($date > dol_now() + 300) throw new RuntimeException('QxInvalidResponse');
							$km = LmdbVehicleQuartixRules::number($data[0]['OdoEstimateKm'] ?? null);
							$day = (new DateTimeImmutable('@'.$date))->setTimezone(new DateTimeZone((string) $link->timezone))->format('Y-m-d');
							$reading = new LmdbVehicleOdometerReading($this->db);
							if ($reading->saveQuartix($user, (int) $link->fk_vehicle, (int) $link->remote_id, $date, $day, $km) < 0) throw new RuntimeException($reading->error);
							$service->write('UPDATE '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_link SET odometer_synced='".$today."' WHERE entity=".$entity.' AND rowid='.((int) $link->rowid));
						}
					}
					$processed++;
				} catch (Exception $e) {
					$failed++; $this->error = self::safeError($e);
					dol_syslog('QUARTIX entity='.$entity.' job='.$kind.' vehicle='.((int) $link->fk_vehicle).' error='.$this->error, LOG_ERR);
					if (in_array($this->error, array('QxRateLimited', 'QxAuthenticationFailed', 'QxNetworkError', 'QxRemoteError', 'QxRequestRejected'), true)) throw $e;
				}
				$lastVehicle = (int) $link->rowid;
			}
			$service->write('UPDATE '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_job SET last_vehicle=".$lastVehicle.",retry_at=NULL,last_error=".($failed ? "'".$this->db->escape($this->error)."'" : 'NULL').($failed ? '' : ",last_success='".$this->db->idate(dol_now())."'")." WHERE entity=".$entity." AND job_kind='".$kind."'");
			$this->output = $langs->transnoentities('QxJobResult', $processed, $failed);
			return $failed ? -1 : 0;
		} catch (Exception $e) {
			$this->error = self::safeError($e); $this->errors = array($this->error);
			$this->output = $langs->transnoentities($this->error);
			if ($client !== null) $this->output .= ' '.$client->getDiagnosticMessage($langs);
			if ($locked) {
				$delay = $client !== null && $client->retryAfter ? $client->retryAfter : 900;
				$retry = in_array($this->error, array('QxRateLimited', 'QxAuthenticationFailed', 'QxNetworkError', 'QxRemoteError', 'QxRequestRejected'), true) ? "'".$this->db->idate(dol_now() + $delay)."'" : 'NULL';
				$this->db->query('UPDATE '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_job SET last_vehicle=".$lastVehicle.",last_error='".$this->db->escape($this->error)."',retry_at=".$retry." WHERE entity=".$entity." AND job_kind='".$kind."'");
			}
			dol_syslog('QUARTIX entity='.$entity.' job='.$kind.' error='.$this->error, LOG_ERR);
			return -1;
		} finally { if ($locked) $service->unlock($entity); }
	}

	/** @param Exception $e Exception @return string Stable non-sensitive code */
	public static function safeError($e)
	{
		$allowed = array('QxDatabaseError', 'QxAccessDenied', 'QxInvalidSettings', 'QxAccountInUse', 'QxMappingExists', 'QxInvalidResponse', 'QxTimeUnconfirmed', 'QxAmbiguousTime', 'QxInvalidPeriod', 'QxRequiresCrypto', 'QxNetworkError', 'QxRateLimited', 'QxAuthenticationFailed', 'QxRemoteError', 'QxRequestRejected', 'QxNoVehicleData', 'QxBusy');
		return in_array($e->getMessage(), $allowed, true) ? $e->getMessage() : 'QxInvalidResponse';
	}

	/** Transport seam for offline job tests. @param int $entity Entity @return LmdbVehicleQuartixClient */
	protected function createClient($entity)
	{
		return new LmdbVehicleQuartixClient($this->db, $entity);
	}

	/** @param LmdbVehicleQuartixService $service Storage @param LmdbVehicleQuartixClient $client API @param object $link Association @return void */
	private function syncUsage($service, $client, $link)
	{
		$now = (new DateTimeImmutable('@'.dol_now()))->setTimezone(new DateTimeZone((string) $link->timezone));
		// A QWS reporting day ends at the following shift start, not at midnight.
		$lastDay = $now->modify($now->format('H:i:s') < $link->shift_start ? '-2 days' : '-1 day')->format('Y-m-d');
		$cutoff = $now->modify('-12 months')->format('Y-m-d');
		if ((string) $link->usage_refreshed !== $lastDay) {
			$end = $lastDay; $start = LmdbVehicleQuartixRules::day($end)->modify('-6 days')->format('Y-m-d');
			$field = "usage_refreshed='".$lastDay."'";
			// Rebuild the retained window after a long outage so no unobserved gap is left behind.
			if (!empty($link->usage_refreshed) && $link->usage_refreshed < LmdbVehicleQuartixRules::day($lastDay)->modify('-7 days')->format('Y-m-d')) {
				$field .= ",usage_cursor='".LmdbVehicleQuartixRules::day($start)->modify('-1 day')->format('Y-m-d')."'";
			}
		} else {
			$end = $link->usage_cursor ?: LmdbVehicleQuartixRules::day($lastDay)->modify('-7 days')->format('Y-m-d');
			if ($end < $cutoff) return;
			$start = max($cutoff, LmdbVehicleQuartixRules::day($end)->modify('-6 days')->format('Y-m-d'));
			$field = "usage_cursor='".LmdbVehicleQuartixRules::day($start)->modify('-1 day')->format('Y-m-d')."'";
		}
		$data = $client->get('/vehicles/tripsummary', array('VehicleIDList' => (string) $link->remote_id, 'StartDay' => $start, 'EndDay' => $end, 'GroupBy' => 'day'));
		$this->db->begin();
		try {
			$service->saveUsage($link, $data, $start, $end);
			$service->write('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link SET '.$field.' WHERE entity='.((int) $link->entity).' AND rowid='.((int) $link->rowid));
			$service->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_usage WHERE entity='.((int) $link->entity).' AND fk_vehicle='.((int) $link->fk_vehicle)." AND usage_day<'".$cutoff."'");
			$this->db->commit();
		} catch (Exception $e) { $this->db->rollback(); throw $e; }
	}
}
