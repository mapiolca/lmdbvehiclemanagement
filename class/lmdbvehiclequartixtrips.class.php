<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/lmdbvehiclequartixservice.class.php';

/**
 * Read-only QWS journal cache. A successful vehicle/day snapshot is authoritative.
 * No provider trip identifier is assumed; no GPS coordinates or driver is stored.
 * source_link_id is historical provenance, not a live FK to a removable association.
 */
class LmdbVehicleQuartixTrips extends LmdbVehicleQuartixService
{
	/** @param string $value Setting @return int Valid positive retention */
	public static function retention($value)
	{
		if (!preg_match('/^[1-9][0-9]*$/D', $value) || strlen($value) > 9) throw new RuntimeException('QxInvalidRetention');
		$days = (int) $value;
		// Keep computed dates inside the database date domain; this is not a subscription limit.
		if ($days > (int) LmdbVehicleQuartixRules::day('1000-01-01')->diff(new DateTimeImmutable('today', new DateTimeZone('UTC')))->days) throw new RuntimeException('QxInvalidRetention');
		return $days;
	}

	/** @param int $timestamp Instant @param string $timezone IANA zone @param string $shift Shift start @return string QWS day */
	public static function reportingDay($timestamp, $timezone, $shift)
	{
		$local = (new DateTimeImmutable('@'.$timestamp))->setTimezone(new DateTimeZone($timezone));
		$shift = strlen($shift) === 5 ? $shift.':00' : $shift;
		return $local->modify($local->format('H:i:s') < $shift ? '-1 day' : '+0 days')->format('Y-m-d');
	}

	/** @param int $days Days including current UTC day @return string Retention boundary shared by reads and purge */
	public static function cutoff($days)
	{
		return (new DateTimeImmutable('@'.dol_now()))->modify('-'.($days - 1).' days')->format('Y-m-d');
	}

	/**
	 * Validate in memory before any SQL. Private rows contain only day/distances.
	 * @param list<array<string,mixed>> $data QWS response
	 * @param stdClass $link Owner association @param string $day Requested day @param string $mode Date convention
	 * @return array{rows:list<array{departure:?int,arrival:?int,start_location:?string,end_location:?string,distance:float,private_distance:?float,travel_time:?float,idling_time:?float,is_private:int,in_progress:?int}>,open:bool}
	 */
	public static function normalize($data, $link, $day, $mode)
	{
		LmdbVehicleQuartixRules::day($day);
		$rows = array(); $seen = array(); $hasOpen = false;
		foreach ($data as $row) {
			if (!is_array($row) || LmdbVehicleQuartixRules::id($row['VehicleID'] ?? null) !== (int) $link->remote_id
				|| !isset($row['InProgress']) || !is_bool($row['InProgress'])) throw new RuntimeException('QxInvalidResponse');
			if (array_key_exists('IsPrivate', $row) && !is_bool($row['IsPrivate'])) throw new RuntimeException('QxInvalidResponse');
			$start = LmdbVehicleQuartixRules::timestamp($row['StartDateTime'] ?? null, $mode, (string) $link->timezone);
			if ($start > dol_now() + 300) throw new RuntimeException('QxInvalidResponse');
			// QWS also returns journeys ENDING in the requested day.
			if (self::reportingDay($start, $link->timezone, $link->shift_start) !== $day) continue;
			if (!empty($link->sync_from) && $start < (int) strtotime($link->sync_from.' UTC')) continue;
			// Conflicting starts at the same instant are rejected rather than guessed. Never persist this key.
			$key = (string) $start;
			if (isset($seen[$key])) {
				if ($seen[$key] === $row) continue;
				throw new RuntimeException('QxInvalidResponse');
			}
			$seen[$key] = $row;
			$distance = LmdbVehicleQuartixRules::number($row['Distance'] ?? null);
			$privateDistance = array_key_exists('PrivacyDistance', $row) && $row['PrivacyDistance'] !== null ? LmdbVehicleQuartixRules::number($row['PrivacyDistance']) : null;
			// Missing privacy information must not silently expose precise locations.
			$private = !empty($row['IsPrivate']) || ($privateDistance !== null && $privateDistance > 0)
				|| (!array_key_exists('IsPrivate', $row) && $privateDistance === null);
			$hasOpen = $hasOpen || $row['InProgress'];
			$end = null; $from = null; $to = null; $travel = null; $idling = null;
			if (!$private) {
				if (!isset($row['StartLocation']) || !is_string($row['StartLocation'])) throw new RuntimeException('QxInvalidResponse');
				$from = dol_substr($row['StartLocation'], 0, 255);
				if (!$row['InProgress']) {
					$end = LmdbVehicleQuartixRules::timestamp($row['EndDateTime'] ?? null, $mode, (string) $link->timezone);
					if ($end < $start || $end > dol_now() + 300 || !isset($row['EndLocation']) || !is_string($row['EndLocation'])) throw new RuntimeException('QxInvalidResponse');
					$to = dol_substr($row['EndLocation'], 0, 255);
				}
				foreach (array('TravelTime' => 'travel', 'IdlingTime' => 'idling') as $field => $variable) {
					$value = $row[$field] ?? null;
					if ($value !== null) {
						$value = LmdbVehicleQuartixRules::number($value);
						if ($variable === 'travel') $travel = $value;
						else $idling = $value;
					}
				}
			}
			$rows[] = array('departure' => $private ? null : $start, 'arrival' => $end, 'start_location' => $from, 'end_location' => $to,
				'distance' => $distance, 'private_distance' => $privateDistance, 'travel_time' => $travel, 'idling_time' => $idling,
				'is_private' => (int) $private, 'in_progress' => $private ? null : (int) $row['InProgress']);
		}
		return array('rows' => $rows, 'open' => $hasOpen);
	}

	/** @param stdClass $link Association @param list<array<string,mixed>> $data QWS rows @param string $day QWS day @param string $mode Time convention @return void */
	public function saveDay($link, $data, $day, $mode)
	{
		$this->assertOwner($link);
		$normalized = self::normalize($data, $link, $day, $mode);
		$filter = ' WHERE entity='.((int) $link->entity).' AND fk_vehicle='.((int) $link->fk_vehicle)." AND trip_day='".$day."'";
		$this->db->begin();
		try {
			$existing = $this->rows('SELECT rowid,source_link_id FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday'.$filter);
			if ($existing && (int) $existing[0]->source_link_id !== (int) $link->rowid) throw new RuntimeException('QxAssociationHistoryOverlap');
			$this->write('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday (entity,fk_vehicle,source_link_id,remote_id,trip_day,timezone,shift_start,synced_at,has_open,trip_count) VALUES ('
				.((int) $link->entity).','.((int) $link->fk_vehicle).','.((int) $link->rowid).','.((int) $link->remote_id).",'".$day."','".$this->db->escape($link->timezone)."','".$this->db->escape($link->shift_start)."','".$this->db->idate(dol_now())."',".((int) $normalized['open']).','.count($normalized['rows']).') ON DUPLICATE KEY UPDATE synced_at=VALUES(synced_at),has_open=VALUES(has_open),trip_count=VALUES(trip_count)');
			$dayId = (int) $this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday'.$filter)[0]->rowid;
			$this->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_trip WHERE entity='.((int) $link->entity).' AND fk_tripday='.$dayId);
			foreach ($normalized['rows'] as $row) {
				$values = array((string) (int) $link->entity, (string) $dayId);
				foreach ($row as $key => $value) {
					if ($value === null) $values[] = 'NULL';
					elseif ($key === 'departure' || $key === 'arrival') $values[] = "'".$this->db->idate($value)."'";
					elseif (is_string($value)) $values[] = "'".$this->db->escape($value)."'";
					else $values[] = (string) $value;
				}
				$this->write('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_trip (entity,fk_tripday,'.implode(',', array_keys($row)).') VALUES ('.implode(',', $values).')');
			}
			$this->db->commit();
		} catch (Exception $e) { $this->db->rollback(); throw $e; }
	}

	/** Purge cache even for paused/unlinked vehicles. @param int $entity Owner @param int $days Retention @param float $deadline Batch deadline @return void */
	public function purge($entity, $days, $deadline = INF)
	{
		global $conf, $user;
		if ($entity !== (int) $conf->entity || !LmdbVehicleQuartixConfig::can($user, 'sync')) throw new RuntimeException('QxAccessDenied');
		$expired = $this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday WHERE entity='.$entity." AND trip_day<'".self::cutoff($days)."' ORDER BY trip_day LIMIT 100");
		foreach ($expired as $day) {
			if (microtime(true) >= $deadline) break;
			$this->db->begin();
			try {
				$this->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_trip WHERE entity='.$entity.' AND fk_tripday='.((int) $day->rowid));
				$this->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday WHERE entity='.$entity.' AND rowid='.((int) $day->rowid));
				$this->db->commit();
			} catch (Exception $e) { $this->db->rollback(); throw $e; }
		}
	}

	/** @param LmdbVehicleQuartixClient $client API @param stdClass $link Association @param array<string,string> $cfg Settings @param float $deadline Batch budget @return bool Vehicle slice complete */
	public function synchronize($client, $link, $cfg, $deadline)
	{
		$today = self::reportingDay(dol_now(), $link->timezone, $link->shift_start);
		$cutoff = self::cutoff(self::retention($cfg['TRIP_RETENTION_DAYS']));
		if (!empty($link->sync_from)) $cutoff = max($cutoff, self::reportingDay($this->db->jdate($link->sync_from), $link->timezone, $link->shift_start));
		if ($cutoff > $today) return true;
		$states = $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday WHERE entity='.((int) $link->entity).' AND fk_vehicle='.((int) $link->fk_vehicle)." AND trip_day>='".$cutoff."' AND trip_day<='".$today."' ORDER BY trip_day DESC");
		$known = array(); $queue = array($today => true);
		foreach ($states as $state) {
			$known[$state->trip_day] = $state;
			if ((int) $state->source_link_id === (int) $link->rowid && (int) $state->has_open) $queue[$state->trip_day] = true;
		}
		for ($n = 1; $n <= 7; $n++) {
			$day = LmdbVehicleQuartixRules::day($today)->modify('-'.$n.' days')->format('Y-m-d');
			if ($day >= $cutoff && !isset($queue[$day])) $queue[$day] = false;
		}
		$shiftTimestamp = LmdbVehicleQuartixRules::timestamp($today.'T'.(strlen($link->shift_start) === 5 ? $link->shift_start.':00' : $link->shift_start), 'local', $link->timezone);
		// Find the next missing historical day, without allocating X dates in memory.
		$historical = LmdbVehicleQuartixRules::day($today)->modify('-8 days');
		while ($historical->format('Y-m-d') >= $cutoff) {
			$day = $historical->format('Y-m-d');
			if (!isset($known[$day])) { $queue[$day] = false; break; }
			$historical = $historical->modify('-1 day');
		}
		$calls = 0;
		foreach ($queue as $day => $frequent) {
			if ($day < $cutoff) continue;
			$state = $known[$day] ?? null;
			if ($state !== null && (int) $state->source_link_id !== (int) $link->rowid) continue;
			if ($state !== null && $this->db->jdate($state->synced_at) >= ($frequent ? dol_now() - 900 : $shiftTimestamp)) continue;
			if (microtime(true) >= $deadline) return false;
			$data = $client->get('/vehicles/trips', array('VehicleIDList' => (string) $link->remote_id, 'StartDay' => $day, 'EndDay' => $day));
			$this->saveDay($link, $data, $day, $cfg['TIME_MODE']);
			if (++$calls >= 10) break;
		}
		return true;
	}

	/**
	 * @param int $id Vehicle @param string $start First day @param string $end Last day @param string $status all/open/done/private
	 * @param int $limit Limit @param int $offset Offset @param string $sort Sort key @param string $order Direction
	 * @return array{rows:list<stdClass>,total:int,days:list<stdClass>,start:string,end:string}
	 */
	public function journal($id, $start, $end, $status = '', $limit = 20, $offset = 0, $sort = 'departure', $order = 'DESC')
	{
		$vehicle = $this->vehicle($id, 'location');
		LmdbVehicleQuartixRules::day($start); LmdbVehicleQuartixRules::day($end);
		if ($start > $end) throw new RuntimeException('QxInvalidPeriod');
		$cfg = (new LmdbVehicleQuartixConfig($this->db))->load((int) $vehicle->entity);
		$start = max($start, self::cutoff(self::retention($cfg['TRIP_RETENTION_DAYS'])));
		$base = ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday AS d';
		$where = ' WHERE d.entity='.((int) $vehicle->entity).' AND d.fk_vehicle='.((int) $vehicle->id)." AND d.trip_day>='".$start."' AND d.trip_day<='".$end."'";
		$days = $this->rows('SELECT d.trip_day,d.synced_at,d.trip_count,d.has_open'.$base.$where.' ORDER BY d.trip_day DESC');
		$base .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_trip AS t ON t.fk_tripday=d.rowid AND t.entity=d.entity';
		if ($status === 'private') $where .= ' AND t.is_private=1';
		elseif ($status === 'open' || $status === 'done') $where .= ' AND t.is_private=0 AND t.in_progress='.($status === 'open' ? 1 : 0);
		elseif ($status !== '') throw new RuntimeException('QxInvalidSettings');
		$total = (int) $this->rows('SELECT COUNT(*) AS nb'.$base.$where)[0]->nb;
		$sorts = array('day' => 'd.trip_day', 'departure' => 't.departure', 'arrival' => 't.arrival', 'from' => 't.start_location', 'to' => 't.end_location', 'distance' => 't.distance', 'private_distance' => 't.private_distance', 'travel' => 't.travel_time', 'idling' => 't.idling_time', 'status' => 't.in_progress');
		$order = $order === 'ASC' ? 'ASC' : 'DESC';
		$sorting = ($sort === 'departure' ? 'd.trip_day '.$order.',' : '').($sorts[$sort] ?? 't.departure').' '.$order.',t.rowid '.$order;
		$rows = $this->rows('SELECT t.*,d.trip_day,d.timezone,d.synced_at'.$base.$where.' ORDER BY '.$sorting.' LIMIT '.max(1, min(1000, $limit)).' OFFSET '.max(0, $offset));
		return array('rows' => $rows, 'total' => $total, 'days' => $days, 'start' => $start, 'end' => $end);
	}
}
