<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/lmdbvehiclequartixclient.class.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleodometerreading.class.php');

/** QUARTIX persistence. Every write belongs to the current, owning entity. */
class LmdbVehicleQuartixService
{
	/** @var DoliDB */ public $db;
	/** @param DoliDB $db Database */
	public function __construct($db) { $this->db = $db; }

	/** @param int $entity Entity @return string */
	public static function lockName($entity) { return 'lmdbvm_qx_'.sha1(MAIN_DB_PREFIX.':'.$entity); }

	/** @param int $entity Entity @return bool */
	public function lock($entity)
	{
		$res = $this->db->query("SELECT GET_LOCK('".self::lockName($entity)."', 0) AS acquired");
		if (!$res) throw new RuntimeException('QxDatabaseError');
		$row = $this->db->fetch_object($res); $this->db->free($res);
		return is_object($row) && (int) $row->acquired === 1;
	}

	/** @param int $entity Entity @return void */
	public function unlock($entity) { $res = $this->db->query("SELECT RELEASE_LOCK('".self::lockName($entity)."')"); if ($res) $this->db->free($res); }

	/** @param string $sql Validated internal SQL @return list<stdClass> */
	public function rows($sql)
	{
		$res = $this->db->query($sql);
		if (!$res) throw new RuntimeException('QxDatabaseError');
		$rows = array();
		while (is_object($row = $this->db->fetch_object($res))) $rows[] = $row;
		$this->db->free($res); return $rows;
	}

	/** @param string $sql Internal SQL @return void */
	public function write($sql) { if (!$this->db->query($sql)) throw new RuntimeException('QxDatabaseError'); }

	/** @param int $id Vehicle id @param string $action Permission @return LmdbVehicle */
	public function vehicle($id, $action = 'read')
	{
		global $user;
		if (!LmdbVehicleQuartixConfig::can($user, $action)) throw new RuntimeException('QxAccessDenied');
		$vehicle = new LmdbVehicle($this->db);
		if ($id <= 0 || $vehicle->fetch($id) <= 0) throw new RuntimeException('QxAccessDenied');
		return $vehicle;
	}

	/** @param int $id Vehicle id @return stdClass|null */
	public function link($id)
	{
		$vehicle = $this->vehicle($id);
		$rows = $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link WHERE fk_vehicle = '.((int) $vehicle->id).' AND entity = '.((int) $vehicle->entity));
		return $rows[0] ?? null;
	}

	/** @param int $id Vehicle id @return stdClass|null Authorized location only */
	public function position($id)
	{
		$vehicle = $this->vehicle($id, 'location');
		$rows = $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_position WHERE fk_vehicle = '.((int) $vehicle->id).' AND entity = '.((int) $vehicle->entity));
		return $rows[0] ?? null;
	}

	/** @param User $user Admin @param int $id Local vehicle @param int $remoteId Remote id @param string $timezone Confirmed IANA timezone @param array<int,mixed> $catalog Fresh /vehicles response @return void */
	public function associate($user, $id, $remoteId, $timezone, $catalog)
	{
		global $conf;
		$vehicle = $this->vehicle($id, 'configure');
		if ((int) $vehicle->entity !== (int) $conf->entity || !in_array($timezone, DateTimeZone::listIdentifiers(), true)) throw new RuntimeException('QxAccessDenied');
		$selected = null;
		foreach ($catalog as $row) if (is_array($row) && ($row['VehicleID'] ?? null) === $remoteId) $selected = $row;
		if ($selected === null || !isset($selected['ShiftStartTime']) || !is_string($selected['ShiftStartTime']) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $selected['ShiftStartTime'])) throw new RuntimeException('QxInvalidResponse');
		if ($this->link($id) !== null) throw new RuntimeException('QxMappingExists');
		$this->db->begin();
		try {
			$this->write('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link (entity,fk_vehicle,remote_id,timezone,shift_start,date_creation,fk_user_creat) VALUES ('.((int) $conf->entity).','.$id.','.$remoteId.",'".$this->db->escape($timezone)."','".$this->db->escape($selected['ShiftStartTime'])."','".$this->db->idate(dol_now())."',".((int) $user->id).')');
			$vehicle->context = array('trigger_reason' => 'quartix_link', 'changed_fields' => array('quartix_link'));
			if ($vehicle->call_trigger($vehicle->TRIGGER_PREFIX.'_UPDATE', $user) < 0) throw new RuntimeException('QxDatabaseError');
			$this->db->commit();
		} catch (Exception $e) { $this->db->rollback(); throw $e; }
	}

	/** @param User $user Admin @param int $id Vehicle @param int $active Desired state @return void */
	public function setActive($user, $id, $active)
	{
		global $conf;
		$vehicle = $this->vehicle($id, 'configure');
		if ((int) $vehicle->entity !== (int) $conf->entity) throw new RuntimeException('QxAccessDenied');
		$link = $this->link($id);
		if ($link === null || !in_array($active, array(0, 1), true)) throw new RuntimeException('QxAccessDenied');
		if ((int) $link->active === $active) return;
		$this->db->begin();
		try {
			$this->write('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link SET active = '.$active.' WHERE rowid = '.((int) $link->rowid).' AND entity = '.((int) $conf->entity));
			$vehicle->context = array('trigger_reason' => 'quartix_link', 'changed_fields' => array('quartix_link'));
			if ($vehicle->call_trigger($vehicle->TRIGGER_PREFIX.'_UPDATE', $user) < 0) throw new RuntimeException('QxDatabaseError');
			$this->db->commit();
		} catch (Exception $e) { $this->db->rollback(); throw $e; }
	}

	/** @param stdClass $link Current entity association @param array<string,mixed> $row API row @param string $mode Confirmed time mode @return void */
	public function savePosition($link, $row, $mode)
	{
		$this->assertOwner($link);
		if (LmdbVehicleQuartixRules::id($row['VehicleID'] ?? null) !== (int) $link->remote_id || !isset($row['NonTracking']) || !is_bool($row['NonTracking']) || !isset($row['LocationText']) || !is_string($row['LocationText'])) throw new RuntimeException('QxInvalidResponse');
		$date = LmdbVehicleQuartixRules::timestamp($row['LastEventDatetime'] ?? null, $mode, (string) $link->timezone);
		if ($date > dol_now() + 300) throw new RuntimeException('QxInvalidResponse');
		foreach (array('Latitude' => 90, 'Longitude' => 180) as $key => $bound) {
			$value = $row[$key] ?? null;
			if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || abs($value) > $bound) throw new RuntimeException('QxInvalidResponse');
		}
		$speed = $row['NonTracking'] ? 'NULL' : (string) LmdbVehicleQuartixRules::number($row['Speed'] ?? null);
		$heading = $row['NonTracking'] ? null : ($row['Heading'] ?? null);
		if ($heading !== null && (!is_int($heading) || $heading < 0 || $heading > 360)) throw new RuntimeException('QxInvalidResponse');
		$columns = array('event_date', 'latitude', 'longitude', 'speed', 'heading', 'location', 'non_tracking');
		$updates = array();
		// event_date is assigned last: all comparisons see the original timestamp.
		foreach (array_slice($columns, 1) as $column) $updates[] = $column.'=IF(VALUES(event_date)>=event_date,VALUES('.$column.'),'.$column.')';
		$updates[] = 'event_date=GREATEST(event_date,VALUES(event_date))'; $updates[] = 'fetched_at=VALUES(fetched_at)';
		$this->write('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_position (entity,fk_vehicle,event_date,fetched_at,latitude,longitude,speed,heading,location,non_tracking) VALUES ('.((int) $link->entity).','.((int) $link->fk_vehicle).",'".$this->db->idate($date)."','".$this->db->idate(dol_now())."',".((float) $row['Latitude']).','.((float) $row['Longitude']).','.$speed.','.($heading === null ? 'NULL' : (string) $heading).",'".$this->db->escape(dol_substr($row['LocationText'], 0, 255))."',".($row['NonTracking'] ? 1 : 0).') ON DUPLICATE KEY UPDATE '.implode(',', $updates));
	}

	/** @param stdClass $link Association @return void */
	private function assertOwner($link)
	{
		global $conf, $user;
		if (!LmdbVehicleQuartixConfig::can($user, 'sync') || (int) $link->entity !== (int) $conf->entity || !(int) $link->active) throw new RuntimeException('QxAccessDenied');
		$rows = $this->rows('SELECT l.rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link AS l INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid=l.fk_vehicle AND v.entity=l.entity WHERE l.rowid='.((int) $link->rowid).' AND l.entity='.((int) $conf->entity).' AND l.fk_vehicle='.((int) $link->fk_vehicle).' AND l.remote_id='.((int) $link->remote_id).' AND l.active=1');
		if (!$rows) throw new RuntimeException('QxAccessDenied');
	}

	/** @param stdClass $link Association @param array<int,mixed> $data Rows @param string $start First day @param string $end Last day @return void */
	public function saveUsage($link, $data, $start, $end)
	{
		$this->assertOwner($link);
		if ($start > $end || LmdbVehicleQuartixRules::day($start)->diff(LmdbVehicleQuartixRules::day($end))->days > 6) throw new RuntimeException('QxInvalidPeriod');
		$rows = LmdbVehicleQuartixRules::summaries($data, (int) $link->remote_id, $start, $end);
		$this->db->begin();
		try {
			for ($day = LmdbVehicleQuartixRules::day($start); $day->format('Y-m-d') <= $end; $day = $day->modify('+1 day')) {
				$row = $rows[$day->format('Y-m-d')] ?? null;
				$values = $row === null ? '0,NULL,NULL,NULL,NULL' : '1,'.$row['trips'].','.$row['distance'].','.$row['travel'].','.$row['idling'];
				$this->write('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_usage (entity,fk_vehicle,usage_day,has_data,trip_count,distance,travel_time,idling_time,date_sync) VALUES ('.((int) $link->entity).','.((int) $link->fk_vehicle).",'".$day->format('Y-m-d')."',".$values.",'".$this->db->idate(dol_now())."') ON DUPLICATE KEY UPDATE has_data=VALUES(has_data),trip_count=VALUES(trip_count),distance=VALUES(distance),travel_time=VALUES(travel_time),idling_time=VALUES(idling_time),date_sync=VALUES(date_sync)");
			}
			$this->db->commit();
		} catch (Exception $e) { $this->db->rollback(); throw $e; }
	}

	/**
	 * Read cached aggregates only. GPS and credentials are never selected here.
	 * @param int $id Vehicle @param string $start First day @param string $end Last day @param string $group day/month
	 * @param int $limit Page size (0 for bounded chart dataset) @param int $offset Page offset @param string $sortfield Column @param string $sortorder ASC/DESC
	 * @return list<stdClass>
	 */
	public function usage($id, $start, $end, $group, $limit = 0, $offset = 0, $sortfield = 'period', $sortorder = 'DESC')
	{
		$vehicle = $this->vehicle($id);
		try { LmdbVehicleQuartixRules::day($start); LmdbVehicleQuartixRules::day($end); }
		catch (UnexpectedValueException $e) { throw new RuntimeException('QxInvalidPeriod'); }
		if ($start > $end || LmdbVehicleQuartixRules::day($start)->diff(LmdbVehicleQuartixRules::day($end))->days > 366) throw new RuntimeException('QxInvalidPeriod');
		if (!in_array($group, array('day', 'month'), true)) throw new RuntimeException('QxInvalidPeriod');
		$period = $group === 'month' ? "DATE_FORMAT(usage_day,'%Y-%m')" : 'usage_day';
		$sortfield = in_array($sortfield, array('period', 'known_days', 'trips', 'distance', 'travel', 'idling'), true) ? $sortfield : 'period';
		$sortorder = $sortorder === 'ASC' ? 'ASC' : 'DESC';
		return $this->rows('SELECT '.$period.' AS period,COUNT(*) AS fetched_days,SUM(has_data) AS known_days,SUM(trip_count) AS trips,SUM(distance) AS distance,SUM(travel_time) AS travel,SUM(idling_time) AS idling FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_usage WHERE entity='.((int) $vehicle->entity).' AND fk_vehicle='.((int) $vehicle->id)." AND usage_day>='".$start."' AND usage_day<='".$end."' GROUP BY ".$period.' ORDER BY '.$sortfield.' '.$sortorder.($sortfield === 'period' ? '' : ',period DESC').($limit > 0 ? ' LIMIT '.min(1001, $limit).' OFFSET '.max(0, (int) $offset) : ''));
	}
}
