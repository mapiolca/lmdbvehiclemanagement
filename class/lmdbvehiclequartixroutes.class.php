<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/lmdbvehiclequartixtrips.class.php';

/** On-demand geometry cache. Trip keys survive journal row replacement; they are not access tokens. */
class LmdbVehicleQuartixRoutes extends LmdbVehicleQuartixService
{
	/** @param int $departure UTC timestamp @return string Opaque list selector, not an authorization mechanism */
	public static function tripKey($departure) { return hash('sha256', (string) $departure); }

	/** @param array<string,mixed> $row Normalized journal row with UTC integer dates @return string */
	public static function fingerprint($row)
	{
		$values = array();
		foreach (array('departure', 'arrival', 'start_location', 'end_location', 'distance', 'is_private', 'in_progress') as $key) $values[$key] = $row[$key];
		return hash('sha256', json_encode($values, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
	}

	/** @param stdClass $row SQL journal row @return array<string,mixed> */
	private function normalizedRow($row)
	{
		return array('departure' => $this->db->jdate($row->departure), 'arrival' => $row->arrival === null ? null : $this->db->jdate($row->arrival),
			'start_location' => $row->start_location, 'end_location' => $row->end_location, 'distance' => (float) $row->distance,
			'is_private' => (int) $row->is_private, 'in_progress' => (int) $row->in_progress);
	}

	/** Owner settings govern both shared views and retrieval. @param array<string,string> $cfg Owner settings @return string */
	public static function unavailable($cfg)
	{
		if (!LmdbVehicleQuartixConfig::supported() || !is_file(DOL_DOCUMENT_ROOT.'/includes/leaflet/leaflet.js')) return 'QxRouteRequiresMap';
		if (($cfg['ROUTES_ENABLED'] ?? '') !== '1') return 'QxRoutesDisabled';
		try { LmdbVehicleQuartixConfig::validateTiles($cfg['TILE_URL'], $cfg['TILE_ATTRIBUTION']); }
		catch (RuntimeException $e) { return 'QxInvalidTileSettings'; }
		return '';
	}

	/** GPS check before any information about the requested day is returned. @param int $dayId Day @return stdClass */
	public function day($dayId)
	{
		global $user;
		if (!LmdbVehicleQuartixConfig::can($user, 'location')) throw new RuntimeException('QxAccessDenied');
		$rows = $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday WHERE rowid='.((int) $dayId).' AND entity IN ('.$this->db->sanitize(getEntity('lmdbvehicle')).')');
		if (!$rows) throw new RuntimeException('QxAccessDenied');
		$day = $rows[0];
		$vehicle = $this->vehicle((int) $day->fk_vehicle, 'location');
		if ((int) $day->entity !== (int) $vehicle->entity) throw new RuntimeException('QxAccessDenied');
		$cfg = (new LmdbVehicleQuartixConfig($this->db))->load((int) $day->entity);
		$reason = self::unavailable($cfg);
		if ($reason !== '') throw new RuntimeException($reason);
		if ($day->trip_day < LmdbVehicleQuartixTrips::cutoff(LmdbVehicleQuartixTrips::retention($cfg['TRIP_RETENTION_DAYS']))) throw new RuntimeException('QxRouteExpired');
		return $day;
	}

	/** @param stdClass $day Authorized day @return list<stdClass> Public journal rows only */
	private function publicTrips($day)
	{
		return $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_trip WHERE entity='.((int) $day->entity).' AND fk_tripday='.((int) $day->rowid).' AND is_private=0 AND departure IS NOT NULL');
	}

	/** @param stdClass $day Authorized day @param string $key Opaque selector @return stdClass */
	private function trip($day, $key)
	{
		if (!preg_match('/^[a-f0-9]{64}$/D', $key)) throw new RuntimeException('QxAccessDenied');
		foreach ($this->publicTrips($day) as $row) if (self::tripKey($this->db->jdate($row->departure)) === $key) return $row;
		throw new RuntimeException('QxRouteUnavailable');
	}

	/** @param stdClass $day Authorized day @return stdClass|null Active original association only */
	private function currentLink($day)
	{
		$rows = $this->rows('SELECT l.* FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link AS l INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid=l.fk_vehicle AND v.entity=l.entity WHERE l.entity='.((int) $day->entity).' AND l.fk_vehicle='.((int) $day->fk_vehicle).' AND l.rowid='.((int) $day->source_link_id).' AND l.remote_id='.((int) $day->remote_id).' AND l.active=1');
		return $rows[0] ?? null;
	}

	/** @param stdClass $day Authorized day @return bool Retrieval is allowed here, never across entities */
	public function canRetrieve($day)
	{
		global $conf;
		$cfg = (new LmdbVehicleQuartixConfig($this->db))->load((int) $day->entity);
		return (int) $day->entity === (int) $conf->entity && self::unavailable($cfg) === '' && $cfg['ENABLED'] === '1' && $this->currentLink($day) !== null && count($this->publicTrips($day)) > 0;
	}

	/** @param stdClass $day Day @return stdClass|null */
	private function queueState($day)
	{
		$rows = $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue WHERE entity='.((int) $day->entity).' AND fk_tripday='.((int) $day->rowid));
		return $rows[0] ?? null;
	}

	/** Cache-only read; every poll repeats access, retention and journal privacy checks.
	 * @param int $dayId Day @param string $key Trip selector
	 * @return array{state:string,message:string,points:list<array{0:float,1:float}>,fetched_at:int,in_progress:bool,can_request:bool,poll:bool}
	 */
	public function view($dayId, $key)
	{
		global $conf;
		$day = $this->day($dayId); $trip = $this->trip($day, $key);
		$cfg = (new LmdbVehicleQuartixConfig($this->db))->load((int) $day->entity);
		$queue = $this->queueState($day);
		$rows = $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route WHERE entity='.((int) $day->entity).' AND fk_tripday='.((int) $dayId)." AND trip_key='".$key."'");
		$cache = $rows[0] ?? null;
		if ($cache !== null && $cache->fingerprint !== self::fingerprint($this->normalizedRow($trip))) $cache = null;
		$blocked = $queue !== null && $queue->last_error === 'QxRoutePrivate';
		$points = $cache === null || $blocked ? array() : json_decode(dolDecrypt($cache->geometry), true, 512, JSON_THROW_ON_ERROR);
		$pending = $queue !== null && (int) $queue->pending === 1;
		$enabled = $cfg['ENABLED'] === '1' && $this->currentLink($day) !== null;
		$throttled = $queue !== null && $queue->last_attempt !== null && $this->db->jdate($queue->last_attempt) > dol_now() - 900;
		$state = $blocked ? 'private' : ($cache !== null ? (count($points) >= 2 ? 'ready' : 'empty') : ($pending ? 'pending' : 'missing'));
		$message = $blocked ? 'QxRoutePrivate' : ($pending ? 'QxRoutePending' : ($queue !== null && $queue->last_error ? $queue->last_error : ($state === 'ready' ? 'QxRouteReady' : ($state === 'empty' ? 'QxRouteUnavailable' : ($enabled ? 'QxRouteNotLoaded' : 'QxRouteStopped')))));
		$jobs = $this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX."cronjob WHERE entity=".((int) $day->entity)." AND classesname='/lmdbvehiclemanagement/class/lmdbvehiclequartixcron.class.php' AND objectname='LmdbVehicleQuartixCron' AND methodename='trips' AND status=1 LIMIT 1");
		if ($pending && (!$jobs || ((int) $day->entity === (int) $conf->entity && !isModEnabled('cron')) || !$enabled)) $message = 'QxRouteQueueStopped';
		return array('state' => $state, 'message' => $message, 'points' => $points, 'fetched_at' => $cache === null || $blocked ? 0 : $this->db->jdate($cache->fetched_at),
			'in_progress' => (bool) $trip->in_progress, 'can_request' => (int) $day->entity === (int) $conf->entity && $enabled && !$pending && !$throttled && ($cache === null || $state === 'empty' || (bool) $trip->in_progress), 'poll' => $pending && $message !== 'QxRouteQueueStopped');
	}

	/** User-requested POST, restricted to the vehicle owner entity.
	 * @param int $dayId Day @param string $key Trip selector @return void
	 */
	public function requestRoute($dayId, $key)
	{
		global $conf;
		$day = $this->day($dayId);
		if ((int) $day->entity !== (int) $conf->entity) throw new RuntimeException('QxAccessDenied'); $this->trip($day, $key);
		$state = $this->view($dayId, $key);
		if (!$state['can_request']) return;
		// INSERT..SELECT rechecks the association atomically if a dissociation races the click.
		$this->write('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue (entity,fk_tripday,pending,requested_at) SELECT d.entity,d.rowid,1,\''.$this->db->idate(dol_now())."' FROM ".MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday AS d INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link AS l ON l.rowid=d.source_link_id AND l.entity=d.entity AND l.fk_vehicle=d.fk_vehicle AND l.remote_id=d.remote_id WHERE d.rowid='.((int) $dayId).' AND d.entity='.((int) $day->entity).' AND l.active=1 ON DUPLICATE KEY UPDATE pending=1,requested_at=VALUES(requested_at)');
		if ((int) $day->entity !== (int) $conf->entity || !$this->lock((int) $day->entity)) return;
		try {
			$day = $this->day($dayId);
			if ($this->canRetrieve($day)) {
				$client = $this->createRouteClient((int) $day->entity, (int) $day->rowid);
				$this->fetchDay($client, $day, microtime(true) + 35);
			}
		} finally { $this->unlock((int) $day->entity); }
	}

	/** @param int $entity Owner @param int $dayId Day @return LmdbVehicleQuartixClient */
	protected function createRouteClient($entity, $dayId) { return new LmdbVehicleQuartixClient($this->db, $entity, $dayId); }

	/** Worker holds entity lock. At most one day per passage; failures do not starve the journal.
	 * @param LmdbVehicleQuartixClient $client Existing owner client @param int $entity Owner @param float $deadline Batch deadline @return string Error or empty
	 */
	public function processPending($client, $entity, $deadline)
	{
		global $conf, $user;
		if ($entity !== (int) $conf->entity || !LmdbVehicleQuartixConfig::can($user, 'sync')) throw new RuntimeException('QxAccessDenied');
		$cfg = (new LmdbVehicleQuartixConfig($this->db))->load($entity);
		if (self::unavailable($cfg) !== '' || $cfg['ENABLED'] !== '1' || microtime(true) >= $deadline) return '';
		$rows = $this->rows('SELECT d.* FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue AS q INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday AS d ON d.rowid=q.fk_tripday AND d.entity=q.entity INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link AS l ON l.rowid=d.source_link_id AND l.entity=d.entity AND l.fk_vehicle=d.fk_vehicle AND l.remote_id=d.remote_id WHERE q.entity='.$entity.' AND q.pending=1 AND l.active=1 AND d.trip_day>=\''.LmdbVehicleQuartixTrips::cutoff(LmdbVehicleQuartixTrips::retention($cfg['TRIP_RETENTION_DAYS']))."' AND (q.last_attempt IS NULL OR q.last_attempt<='".$this->db->idate(dol_now() - 900)."') ORDER BY COALESCE(q.last_attempt,q.requested_at),q.rowid LIMIT 1");
		if (!$rows) return '';
		try { $this->fetchDay($client, $rows[0], min($deadline, microtime(true) + 30)); }
		catch (Exception $e) { return self::safeError($e); }
		return '';
	}

	/** @param Exception $e Failure @return string Safe code */
	public static function safeError($e)
	{
		$allowed = array('QxDatabaseError', 'QxAccessDenied', 'QxInvalidResponse', 'QxTimeUnconfirmed', 'QxAmbiguousTime', 'QxNetworkError', 'QxRateLimited', 'QxAuthenticationFailed', 'QxRemoteError', 'QxRequestRejected', 'QxRoutePrivate', 'QxRouteUnavailable', 'QxRouteExpired', 'QxRoutesDisabled', 'QxRouteRequiresMap', 'QxInvalidTileSettings');
		return in_array($e->getMessage(), $allowed, true) ? $e->getMessage() : 'QxInvalidResponse';
	}

	/** Worker/interactive caller holds owner lock; no durable running state to strand after interruption.
	 * @param LmdbVehicleQuartixClient $client QWS @param stdClass $day Day @param float $deadline Deadline @return void
	 */
	private function fetchDay($client, $day, $deadline)
	{
		global $conf, $user;
		if ((int) $day->entity !== (int) $conf->entity || (!LmdbVehicleQuartixConfig::can($user, 'sync') && !LmdbVehicleQuartixConfig::can($user, 'location')) || !$this->canRetrieve($day)) throw new RuntimeException('QxAccessDenied');
		$filter = ' WHERE entity='.((int) $day->entity).' AND fk_tripday='.((int) $day->rowid);
		$state = $this->queueState($day);
		if ($state === null || !(int) $state->pending || ($state->last_attempt !== null && $this->db->jdate($state->last_attempt) > dol_now() - 900)) return;
		$this->write('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue SET last_attempt=\''.$this->db->idate(dol_now())."'".$filter);
		try {
			$client->setDeadline($deadline);
			$data = $client->get('/vehicles/route', array('VehicleID' => (int) $day->remote_id, 'StartDay' => $day->trip_day));
			$cfg = (new LmdbVehicleQuartixConfig($this->db))->load((int) $day->entity);
			$link = $this->currentLink($day);
			if ($link === null) throw new RuntimeException('QxAccessDenied');
			$public = $this->publicTrips($day);
			// Privacy takes precedence even if a different trip has malformed geometry.
			// No private timestamp/key is persisted; it only revokes a former public day.
			$publicKeys = array();
			foreach ($public as $trip) $publicKeys[self::tripKey($this->db->jdate($trip->departure))] = true;
			foreach ($data as $envelope) foreach ($envelope['Trips'] as $raw) {
				if (!is_array($raw) || ($raw['VehicleID'] ?? null) !== (int) $day->remote_id) continue;
				$privacy = $raw['PrivacyDistance'] ?? null;
				$private = ($raw['IsPrivate'] ?? null) === true || (is_numeric($privacy) && $privacy > 0)
					|| (!array_key_exists('IsPrivate', $raw) && $privacy === null);
				if (!$private) continue;
				try { $start = LmdbVehicleQuartixRules::timestamp($raw['StartDateTime'] ?? null, $cfg['TIME_MODE'], $link->timezone); }
				catch (RuntimeException $ignored) { continue; }
				if (isset($publicKeys[self::tripKey($start)])) throw new RuntimeException('QxRoutePrivate');
			}
			$routes = self::normalize($data, $link, $day->trip_day, $cfg['TIME_MODE']);
			$cache = array();
			foreach ($public as $trip) {
				$key = self::tripKey($this->db->jdate($trip->departure));
				$route = $routes[$key] ?? null;
				if ($route !== null && $route['private']) throw new RuntimeException('QxRoutePrivate');
				$fingerprint = self::fingerprint($this->normalizedRow($trip));
				// Live /trips may keep today's trips InProgress after /route reports
				// them closed. The journal remains authoritative for provisional state.
				$matchingFingerprint = $route === null ? '' : ((int) $trip->in_progress ? $route['open_fingerprint'] : $route['fingerprint']);
				if ($route !== null && $matchingFingerprint !== $fingerprint) throw new RuntimeException('QxRouteUnavailable');
				$cache[$key] = array('fingerprint' => $fingerprint, 'points' => $route === null ? array() : $route['points']);
			}
			$this->db->begin();
			try {
				$this->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route'.$filter);
				// Native DB debug logs may include INSERT values: encrypt geometry before SQL.
				foreach ($cache as $key => $route) $this->write('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route (entity,fk_tripday,trip_key,fingerprint,geometry,fetched_at) VALUES ('.((int) $day->entity).','.((int) $day->rowid).",'".$key."','".$route['fingerprint']."','".$this->db->escape(LmdbVehicleQuartixConfig::encrypt(json_encode($route['points'], JSON_THROW_ON_ERROR)))."','".$this->db->idate(dol_now())."')");
				$this->write('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue SET pending=0,last_error=NULL,synced_at=\''.$this->db->idate(dol_now())."'".$filter);
				if ($this->db->commit() <= 0) throw new RuntimeException('QxDatabaseError');
			} catch (Exception $e) { $this->db->rollback(); throw $e; }
		} catch (Exception $e) {
			$error = self::safeError($e);
			// A newly private route must immediately revoke even a formerly valid cache.
			$this->db->begin();
			try {
				if ($error === 'QxRoutePrivate') $this->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route'.$filter);
				$this->write('UPDATE '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_routequeue SET last_error='".$error."'".($error === 'QxRoutePrivate' ? ',pending=0' : '').$filter);
				if ($this->db->commit() <= 0) throw new RuntimeException('QxDatabaseError');
			} catch (Exception $storageError) { $this->db->rollback(); throw $storageError; }
			throw new RuntimeException($error);
		} finally { $client->setDeadline(INF); }
	}

	/** Strict day decoder, discarding all fields except the geometry used in the browser.
	 * @param list<array<string,mixed>> $data Envelope Data @param stdClass $link Association @param string $day Day @param string $mode Timestamp convention
	 * @return array<string,array{private:bool,fingerprint:string,open_fingerprint:string,points:list<array{0:float,1:float}>}>
	 */
	public static function normalize($data, $link, $day, $mode)
	{
		$result = array();
		foreach ($data as $envelope) {
			if (!is_array($envelope) || !isset($envelope['Summary'], $envelope['Trips']) || !is_array($envelope['Summary']) || !is_array($envelope['Trips'])
				|| LmdbVehicleQuartixRules::id($envelope['Summary']['VehicleID'] ?? null) !== (int) $link->remote_id
				|| ($envelope['Trips'] !== array() && array_keys($envelope['Trips']) !== range(0, count($envelope['Trips']) - 1))) throw new RuntimeException('QxInvalidResponse');
			foreach ($envelope['Trips'] as $raw) {
				if (!is_array($raw)) throw new RuntimeException('QxInvalidResponse');
				$normalized = LmdbVehicleQuartixTrips::normalize(array($raw), $link, $day, $mode);
				if (!$normalized['rows']) continue;
				$row = $normalized['rows'][0];
				$start = LmdbVehicleQuartixRules::timestamp($raw['StartDateTime'], $mode, $link->timezone);
				$key = self::tripKey($start);
				$route = array('private' => (bool) $row['is_private'], 'fingerprint' => '', 'open_fingerprint' => '', 'points' => array());
				if (!$route['private']) {
					$route['fingerprint'] = self::fingerprint($row);
					$provisional = $row;
					$provisional['arrival'] = null; $provisional['end_location'] = null; $provisional['in_progress'] = 1;
					$route['open_fingerprint'] = self::fingerprint($provisional);
					if (!isset($raw['Route']) || !is_array($raw['Route']) || ($raw['Route'] !== array() && array_keys($raw['Route']) !== range(0, count($raw['Route']) - 1))) throw new RuntimeException('QxInvalidResponse');
					$lastTime = $start;
					foreach ($raw['Route'] as $point) {
						if (!is_array($point)) throw new RuntimeException('QxInvalidResponse');
						$coords = array();
						foreach (array('Latitude' => 90, 'Longitude' => 180) as $field => $bound) {
							$value = $point[$field] ?? null;
							if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || abs($value) > $bound) throw new RuntimeException('QxInvalidResponse');
							$coords[] = (float) $value;
						}
						$time = LmdbVehicleQuartixRules::timestamp($point['EventDateTime'] ?? null, $mode, $link->timezone);
						if ($time < $lastTime || $time > dol_now() + 300 || ($row['arrival'] !== null && $time > $row['arrival'])) throw new RuntimeException('QxInvalidResponse');
						$lastTime = $time;
						if (!$route['points'] || end($route['points']) !== $coords) $route['points'][] = $coords;
					}
				}
				if (isset($result[$key]) && $result[$key] !== $route) throw new RuntimeException('QxInvalidResponse');
				$result[$key] = $route;
			}
		}
		return $result;
	}

	/** Called inside the journal transaction; private/deleted/changed trips revoke geometry.
	 * @param int $entity Owner @param int $dayId Day @param list<array<string,mixed>> $rows New journal snapshot @return void
	 */
	public function reconcile($entity, $dayId, $rows)
	{
		$valid = array();
		foreach ($rows as $row) if (!$row['is_private']) $valid[self::tripKey($row['departure'])] = self::fingerprint($row);
		$filter = ' WHERE entity='.((int) $entity).' AND fk_tripday='.((int) $dayId);
		foreach ($this->rows('SELECT rowid,trip_key,fingerprint FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route'.$filter) as $cache) {
			if (($valid[$cache->trip_key] ?? '') !== $cache->fingerprint) $this->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route'.$filter.' AND rowid='.((int) $cache->rowid));
		}
		if (!$valid) $this->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue'.$filter);
	}
}
