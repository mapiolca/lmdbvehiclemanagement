<?php
require_once __DIR__.'/lmdbvehiclesharing.class.php';
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/lmdbvehiclequartixclient.class.php';
require_once __DIR__.'/lmdbvehiclequartix.class.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleodometerreading.class.php');

/** QUARTIX persistence. Every write belongs to the current, owning entity. */
class LmdbVehicleQuartixService
{
	/** @var DoliDB */ public $db;
	/** @param DoliDB $db Database */
	public function __construct($db) { $this->db = $db; }

	/** Dataset factory for native trigger integration tests. @return LmdbVehicleQuartix */
	protected function createDataset() { return new LmdbVehicleQuartix($this->db); }

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
		if (!isModEnabled('lmdbvehiclemanagement') || !empty($user->socid) || !$user->hasRight('lmdbvehiclemanagement', 'read')) throw new RuntimeException('QxAccessDenied');
		if (($action === 'configure' && empty($user->admin)) || (in_array($action, array('location', 'sync'), true) && !$user->hasRight('lmdbvehiclemanagement', 'quartix', $action))) throw new RuntimeException('QxAccessDenied');
		$vehicle = new LmdbVehicle($this->db);
		if ($id <= 0 || $vehicle->fetch($id) <= 0) throw new RuntimeException('QxAccessDenied');
		return $vehicle;
	}

	/** Authorized persistent sources, including local history after vehicle withdrawal.
	 * @param int $vehicleId Vehicle @return list<LmdbVehicleQuartix>
	 */
	public function sources($vehicleId)
	{
		global $user;
		if (!isModEnabled('lmdbvehiclemanagement') || !empty($user->socid) || !$user->hasRight('lmdbvehiclemanagement', 'read')) throw new RuntimeException('QxAccessDenied');
		$rows = $this->rows('SELECT q.rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_dataset q WHERE q.fk_vehicle='.(int) $vehicleId.' AND '.LmdbVehicleSharing::sql($this->db, 'lmdbvehiclequartix', 'q').' ORDER BY q.entity,q.rowid');
		$result = array();
		foreach ($rows as $row) {
			$dataset = $this->createDataset();
			if ($dataset->fetch((int) $row->rowid) > 0) $result[] = $dataset;
		}
		return $result;
	}

	/** Never select an arbitrary remote source or fall back after an explicit refusal.
	 * @param int $vehicleId Vehicle @param int $quartixId Explicit source (0 = local/unique) @return LmdbVehicleQuartix
	 */
	public function dataset($vehicleId, $quartixId = 0)
	{
		global $conf;
		$sources = $this->sources($vehicleId);
		foreach ($sources as $source) {
			if ($quartixId > 0 ? (int) $source->id === $quartixId : (int) $source->entity === (int) $conf->entity) return $source;
		}
		if ($quartixId > 0) throw new RuntimeException('QxAccessDenied');
		if (count($sources) === 1) return $sources[0];
		throw new RuntimeException($sources ? 'QxChooseSource' : 'QxNoData');
	}

	/** Technical links belong to the active entity unless an authorized source is explicit.
	 * @param int $id Vehicle @param int $quartixId Source @return stdClass|null
	 */
	public function link($id, $quartixId = 0)
	{
		global $conf, $user;
		if (!isModEnabled('lmdbvehiclemanagement') || !empty($user->socid) || !$user->hasRight('lmdbvehiclemanagement', 'read')) throw new RuntimeException('QxAccessDenied');
		$entity = $quartixId > 0 ? (int) $this->dataset($id, $quartixId)->entity : (int) $conf->entity;
		$rows = $this->rows('SELECT l.* FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link l INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_dataset q ON q.rowid=l.fk_quartix AND q.entity=l.entity AND q.fk_vehicle=l.fk_vehicle WHERE l.fk_vehicle='.(int) $id.' AND l.entity='.$entity.' AND '.LmdbVehicleSharing::sql($this->db, 'lmdbvehiclequartix', 'q'));
		return $rows[0] ?? null;
	}

	/** @param int $id Vehicle @param int $quartixId Source @return stdClass|null */
	public function position($id, $quartixId = 0)
	{
		global $user;
		if (!$user->hasRight('lmdbvehiclemanagement', 'quartix', 'location')) throw new RuntimeException('QxAccessDenied');
		$dataset = $this->dataset($id, $quartixId);
		$rows = $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_position WHERE fk_quartix='.(int) $dataset->id.' AND entity='.(int) $dataset->entity);
		return $rows[0] ?? null;
	}

	/** Historical mileage for exactly one authorized source, including withdrawn vehicles.
	 * @param int $id Vehicle @param int $quartixId Source @param int $limit Page size @param int $page Page
	 * @return array{rows:list<stdClass>,total:int,page:int}
	 */
	public function readings($id, $quartixId, $limit = 20, $page = 0)
	{
		$dataset = $this->dataset($id, $quartixId);
		$limit = max(1, min(1000, (int) $limit)); $page = max(0, (int) $page);
		$where = ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading r WHERE r.fk_quartix='.(int) $dataset->id.' AND r.entity='.(int) $dataset->entity.' AND '.LmdbVehicleSharing::sql($this->db, 'lmdbvehicleodometerreading', 'r');
		$count = $this->rows('SELECT COUNT(*) AS nb'.$where);
		$total = $count ? (int) $count[0]->nb : 0;
		if ($page * $limit >= $total) $page = 0;
		return array('rows' => $this->rows('SELECT r.rowid,r.reading_date,r.odometer_km,r.reason'.$where.' ORDER BY r.reading_date DESC,r.rowid DESC'.$this->db->plimit($limit, $page * $limit)), 'total' => $total, 'page' => $page);
	}

	/** @param User $user Admin @param int $id Local vehicle @param int $remoteId Remote id @param string $timezone Confirmed IANA timezone @param array<int,mixed> $catalog Fresh /vehicles response @param int $syncFrom Confirmed installation timestamp @return void */
	public function associate($user, $id, $remoteId, $timezone, $catalog, $syncFrom)
	{
		global $conf;
		if (!(isModEnabled('lmdbvehiclemanagement') && empty($user->socid) && $user->hasRight('lmdbvehiclemanagement', 'read') && !empty($user->admin)) || !is_int($id) || $id <= 0) throw new RuntimeException('QxAccessDenied');
		$vehicle = $this->vehicle($id, 'configure');
		if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) throw new RuntimeException('QxAccessDenied');
		LmdbVehicleQuartixRules::id($remoteId);
		if (!is_int($syncFrom) || $syncFrom <= 0 || $syncFrom > dol_now() + 300) throw new RuntimeException('QxInvalidAssociationDate');
		$selected = null;
		foreach ($catalog as $row) if (is_array($row) && ($row['VehicleID'] ?? null) === $remoteId) $selected = $row;
		if ($selected === null || !isset($selected['ShiftStartTime']) || !is_string($selected['ShiftStartTime']) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $selected['ShiftStartTime'])) throw new RuntimeException('QxInvalidResponse');
		if ($this->link($id) !== null) throw new RuntimeException('QxMappingExists');
		if ($this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link WHERE entity='.((int) $conf->entity).' AND remote_id='.$remoteId)) throw new RuntimeException('QxMappingExists');
		// Retained observations belong to this vehicle. A new association may not overwrite them.
		$firstDay = LmdbVehicleQuartixRules::firstUsageDay($syncFrom, $timezone, $selected['ShiftStartTime']);
		$history = $this->rows('SELECT usage_day FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_usage WHERE entity='.((int) $conf->entity).' AND fk_vehicle='.$id." AND usage_day>='".$firstDay."' AND has_data=1 LIMIT 1");
		$readings = $this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading WHERE entity='.((int) $conf->entity).' AND fk_vehicle='.$id." AND is_estimate=1 AND provider_key IS NOT NULL AND reading_date>='".$this->db->idate($syncFrom)."' LIMIT 1");
		require_once __DIR__.'/lmdbvehiclequartixtrips.class.php';
		$firstTripDay = LmdbVehicleQuartixTrips::reportingDay($syncFrom, $timezone, $selected['ShiftStartTime']);
		$tripHistory = $this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday WHERE entity='.((int) $conf->entity).' AND fk_vehicle='.$id." AND trip_day>='".$firstTripDay."' LIMIT 1");
		if ($history || $readings || $tripHistory) throw new RuntimeException('QxAssociationHistoryOverlap');
		$this->db->begin();
		try {
			if (!$this->rows('SELECT v.rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle v WHERE v.rowid='.$id.' AND '.LmdbVehicleSharing::sql($this->db, 'lmdbvehicle', 'v').' FOR UPDATE')) throw new RuntimeException('QxAccessDenied');
			$existing = $this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_dataset WHERE entity='.(int) $conf->entity.' AND fk_vehicle='.$id);
			$dataset = $this->createDataset();
			if ($existing) {
				if ($dataset->fetch((int) $existing[0]->rowid) <= 0) throw new RuntimeException('QxAccessDenied');
			} else {
				$dataset->entity = (int) $conf->entity; $dataset->fk_vehicle = $id;
				$dataset->snapshot_vehicle_label = dol_substr((string) $vehicle->ref, 0, 255);
				if ($dataset->create($user) <= 0) throw new RuntimeException('QxDatabaseError');
			}
			$this->write('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link (entity,fk_quartix,fk_vehicle,remote_id,timezone,shift_start,sync_from,date_creation,fk_user_creat) VALUES ('.((int) $conf->entity).','.(int) $dataset->id.','.$id.','.$remoteId.",'".$this->db->escape($timezone)."','".$this->db->escape($selected['ShiftStartTime'])."','".$this->db->idate($syncFrom)."','".$this->db->idate(dol_now())."',".((int) $user->id).')');
			$dataset->changed($user, 'quartix_link');
			$this->db->commit();
		} catch (Exception $e) { $this->db->rollback(); throw $e; }
	}

	/**
	 * Caller owns the entity QUARTIX lock, shared with workers and configuration.
	 * Reassignment retains history; an erroneous association explicitly purges imports.
	 * @param User $user Admin @param int $id Vehicle @param int $linkId Confirmed association
	 * @param string $mode reassignment/error @return void
	 */
	public function disassociate($user, $id, $linkId, $mode)
	{
		global $conf;
		$dataset = $this->dataset($id);
		if (!(isModEnabled('lmdbvehiclemanagement') && empty($user->socid) && $user->hasRight('lmdbvehiclemanagement', 'read') && !empty($user->admin)) || (int) $dataset->entity !== (int) $conf->entity) throw new RuntimeException('QxAccessDenied');
		if (!in_array($mode, array('reassignment', 'error'), true)) throw new RuntimeException('QxInvalidSettings');
		$link = $this->link($id);
		if ($link === null && !($mode === 'error' && $linkId === 0)) return;
		if ($link !== null && (int) $link->rowid !== $linkId) throw new RuntimeException('QxAssociationChanged');
		$this->db->begin();
		try {
			if ($mode === 'error') {
				$filter = ' WHERE entity='.((int) $conf->entity).' AND fk_vehicle='.((int) $id);
				do {
					$readings = $this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading'.$filter." AND source='external' AND is_estimate=1 AND provider_key IS NOT NULL ORDER BY rowid LIMIT 100");
					foreach ($readings as $row) {
						$reading = $this->createReading();
						$reading->id = (int) $row->rowid;
						if ($reading->deleteQuartix($user) < 0) throw new RuntimeException('QxDatabaseError');
					}
				} while (count($readings) === 100);
				$this->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_usage'.$filter);
				$this->deleteTripCache((int) $conf->entity, (int) $id);
			}
			$this->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_position WHERE entity='.((int) $conf->entity).' AND fk_vehicle='.((int) $id));
			$this->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue WHERE entity='.((int) $conf->entity).' AND fk_tripday IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday WHERE entity='.((int) $conf->entity).' AND fk_vehicle='.((int) $id).')');
			$this->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link WHERE entity='.((int) $conf->entity).' AND fk_vehicle='.((int) $id).' AND rowid='.$linkId);
			$dataset->changed($user, $mode === 'error' ? 'quartix_cleanup' : 'quartix_unlink');
			$this->db->commit();
		} catch (Exception $e) { $this->db->rollback(); throw $e; }
	}

	/** Caller owns transaction and authorizes the vehicle mutation. @param int $entity Owner @param int $vehicleId Vehicle @return void */
	public function deleteTripCache($entity, $vehicleId)
	{
		global $conf, $user;
		if ($entity !== (int) $conf->entity
			|| (!(isModEnabled('lmdbvehiclemanagement') && empty($user->socid) && $user->hasRight('lmdbvehiclemanagement', 'read') && !empty($user->admin)) && !$user->hasRight('lmdbvehiclemanagement', 'delete'))) throw new RuntimeException('QxAccessDenied');
		$filter = ' WHERE entity='.$entity.' AND fk_vehicle='.$vehicleId;
		foreach (array('qx_route', 'qx_routequeue') as $table) $this->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_'.$table.' WHERE entity='.$entity.' AND fk_tripday IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday'.$filter.')');
		$this->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_trip WHERE entity='.$entity.' AND fk_tripday IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday'.$filter.')');
		$this->write('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday'.$filter);
	}

	/** Native reading object; replaceable in offline trigger tests. @return LmdbVehicleOdometerReading */
	protected function createReading() { return new LmdbVehicleOdometerReading($this->db); }

	/** @param User $user Admin @param int $id Vehicle @param int $active Desired state @return void */
	public function setActive($user, $id, $active)
	{
		global $conf;
		$dataset = $this->dataset($id);
		if (empty($user->admin) || !empty($user->socid) || !$user->hasRight('lmdbvehiclemanagement', 'read')) throw new RuntimeException('QxAccessDenied');
		if ((int) $dataset->entity !== (int) $conf->entity) throw new RuntimeException('QxAccessDenied');
		$link = $this->link($id);
		if ($link === null || !in_array($active, array(0, 1), true)) throw new RuntimeException('QxAccessDenied');
		if ((int) $link->active === $active) return;
		$this->db->begin();
		try {
			$this->write('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link SET active = '.$active.' WHERE rowid = '.((int) $link->rowid).' AND entity = '.((int) $conf->entity));
			$dataset->changed($user, 'quartix_link');
			$this->db->commit();
		} catch (Exception $e) { $this->db->rollback(); throw $e; }
	}

	/** @param stdClass $link Current entity association @param array<string,mixed> $row API row @param string $mode Confirmed time mode @return void */
	public function savePosition($link, $row, $mode)
	{
		global $user;
		$this->assertOwner($link);
		if (LmdbVehicleQuartixRules::id($row['VehicleID'] ?? null) !== (int) $link->remote_id || !isset($row['NonTracking']) || !is_bool($row['NonTracking']) || !isset($row['LocationText']) || !is_string($row['LocationText'])) throw new RuntimeException('QxInvalidResponse');
		$date = LmdbVehicleQuartixRules::timestamp($row['LastEventDatetime'] ?? null, $mode, (string) $link->timezone);
		if ($date > dol_now() + 300) throw new RuntimeException('QxInvalidResponse');
		if (!empty($link->sync_from) && $date < $this->db->jdate($link->sync_from)) throw new RuntimeException('QxBeforeAssociation');
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
		$this->db->begin();
		try {
			$this->assertOwner($link, true);
			$this->write('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_position (entity,fk_quartix,fk_vehicle,event_date,fetched_at,latitude,longitude,speed,heading,location,non_tracking) VALUES ('.((int) $link->entity).','.(int) $link->fk_quartix.','.((int) $link->fk_vehicle).",'".$this->db->idate($date)."','".$this->db->idate(dol_now())."',".((float) $row['Latitude']).','.((float) $row['Longitude']).','.$speed.','.($heading === null ? 'NULL' : (string) $heading).",'".$this->db->escape(dol_substr($row['LocationText'], 0, 255))."',".($row['NonTracking'] ? 1 : 0).') ON DUPLICATE KEY UPDATE '.implode(',', $updates));
			$dataset = $this->createDataset();
			if ($dataset->fetch((int) $link->fk_quartix) <= 0) throw new RuntimeException('QxAccessDenied');
			$dataset->changed($user, 'quartix_position');
			$this->db->commit();
		} catch (Exception $e) { $this->db->rollback(); throw $e; }
	}

	/** @param stdClass $link Association @param bool $lock Lock the association and vehicle before writes @return void */
	public function assertOwner($link, $lock = false)
	{
		global $conf, $user;
		if (!(isModEnabled('lmdbvehiclemanagement') && empty($user->socid) && $user->hasRight('lmdbvehiclemanagement', 'read') && $user->hasRight('lmdbvehiclemanagement', 'quartix', 'sync')) || (int) $link->entity !== (int) $conf->entity || !(int) $link->active) throw new RuntimeException('QxAccessDenied');
		$rows = $this->rows('SELECT l.rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link AS l INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid=l.fk_vehicle WHERE '.LmdbVehicleSharing::sql($this->db, 'lmdbvehicle', 'v').' AND l.fk_quartix='.(int) $link->fk_quartix.' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_dataset q WHERE q.rowid=l.fk_quartix AND q.entity=l.entity AND q.fk_vehicle=l.fk_vehicle) AND l.rowid='.((int) $link->rowid).' AND l.entity='.((int) $conf->entity).' AND l.fk_vehicle='.((int) $link->fk_vehicle).' AND l.remote_id='.((int) $link->remote_id).' AND l.active=1'.($lock ? ' FOR UPDATE' : ''));
		if (!$rows) throw new RuntimeException('QxAccessDenied');
	}

	/** @param stdClass $link Association @param array<int,mixed> $data Rows @param string $start First day @param string $end Last day @return void */
	public function saveUsage($link, $data, $start, $end)
	{
		global $user;
		$this->assertOwner($link);
		if ($start > $end || LmdbVehicleQuartixRules::day($start)->diff(LmdbVehicleQuartixRules::day($end))->days > 6) throw new RuntimeException('QxInvalidPeriod');
		if (!empty($link->sync_from) && $start < LmdbVehicleQuartixRules::firstUsageDay($this->db->jdate($link->sync_from), (string) $link->timezone, (string) $link->shift_start)) throw new RuntimeException('QxBeforeAssociation');
		$rows = LmdbVehicleQuartixRules::summaries($data, (int) $link->remote_id, $start, $end);
		$this->db->begin();
		try {
			$this->assertOwner($link, true);
			for ($day = LmdbVehicleQuartixRules::day($start); $day->format('Y-m-d') <= $end; $day = $day->modify('+1 day')) {
				$row = $rows[$day->format('Y-m-d')] ?? null;
				$values = $row === null ? '0,NULL,NULL,NULL,NULL' : '1,'.$row['trips'].','.$row['distance'].','.$row['travel'].','.$row['idling'];
				$this->write('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_usage (entity,fk_quartix,fk_vehicle,usage_day,has_data,trip_count,distance,travel_time,idling_time,date_sync) VALUES ('.((int) $link->entity).','.(int) $link->fk_quartix.','.((int) $link->fk_vehicle).",'".$day->format('Y-m-d')."',".$values.",'".$this->db->idate(dol_now())."') ON DUPLICATE KEY UPDATE has_data=VALUES(has_data),trip_count=VALUES(trip_count),distance=VALUES(distance),travel_time=VALUES(travel_time),idling_time=VALUES(idling_time),date_sync=VALUES(date_sync)");
			}
			$dataset = $this->createDataset();
			if ($dataset->fetch((int) $link->fk_quartix) <= 0) throw new RuntimeException('QxAccessDenied');
			$dataset->changed($user, 'quartix_usage');
			$this->db->commit();
		} catch (Exception $e) { $this->db->rollback(); throw $e; }
	}

	/**
	 * Read cached aggregates only. GPS and credentials are never selected here.
	 * @param int $id Vehicle @param string $start First day @param string $end Last day @param string $group day/month
	 * @param int $limit Page size (0 for bounded chart dataset) @param int $offset Page offset @param string $sortfield Column @param string $sortorder ASC/DESC
	 * @param int $quartixId Source (0 resolves the local or sole authorized source)
	 * @return list<stdClass>
	 */
	public function usage($id, $start, $end, $group, $limit = 0, $offset = 0, $sortfield = 'period', $sortorder = 'DESC', $quartixId = 0)
	{
		$dataset = $this->dataset($id, $quartixId);
		try { LmdbVehicleQuartixRules::day($start); LmdbVehicleQuartixRules::day($end); }
		catch (UnexpectedValueException $e) { throw new RuntimeException('QxInvalidPeriod'); }
		if ($start > $end || LmdbVehicleQuartixRules::day($start)->diff(LmdbVehicleQuartixRules::day($end))->days > 366) throw new RuntimeException('QxInvalidPeriod');
		if (!in_array($group, array('day', 'month'), true)) throw new RuntimeException('QxInvalidPeriod');
		$period = $group === 'month' ? "DATE_FORMAT(usage_day,'%Y-%m')" : 'usage_day';
		$sortfield = in_array($sortfield, array('period', 'known_days', 'trips', 'distance', 'travel', 'idling'), true) ? $sortfield : 'period';
		$sortorder = $sortorder === 'ASC' ? 'ASC' : 'DESC';
		return $this->rows('SELECT '.$period.' AS period,COUNT(*) AS fetched_days,SUM(has_data) AS known_days,SUM(trip_count) AS trips,SUM(distance) AS distance,SUM(travel_time) AS travel,SUM(idling_time) AS idling FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_usage WHERE entity='.((int) $dataset->entity).' AND fk_quartix='.((int) $dataset->id)." AND usage_day>='".$start."' AND usage_day<='".$end."' GROUP BY ".$period.' ORDER BY '.$sortfield.' '.$sortorder.($sortfield === 'period' ? '' : ',period DESC').($limit > 0 ? ' LIMIT '.min(1001, $limit).' OFFSET '.max(0, (int) $offset) : ''));
	}
}
