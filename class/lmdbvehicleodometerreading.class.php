<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementobject.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementrules.class.php');

/**
 * Dated odometer reading.
 */
class LmdbVehicleOdometerReading extends LmdbVehicleManagementObject
{
	/** @var string */
	public $element = 'lmdbvehicleodometerreading';
	/** @var string */
	public $table_element = 'lmdbvehiclemanagement_odometer_reading';
	/** @var string */
	public $TRIGGER_PREFIX = 'LMDBVEHICLEMANAGEMENT_ODOMETER';
	/** @var string */
	public $entity_scope_element = 'lmdbvehicle';
	/** @var string */
	public $picto = 'gauge-high';

	/** @var array<string,mixed> */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'position' => 10, 'notnull' => 1, 'visible' => 0, 'default' => 1, 'index' => 1),
		'fk_vehicle' => array('type' => 'integer:LmdbVehicle:lmdbvehiclemanagement/class/lmdbvehicle.class.php:0', 'label' => 'Vehicle', 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'reading_date' => array('type' => 'datetime', 'label' => 'ReadingDate', 'position' => 30, 'notnull' => 1, 'visible' => 1),
		'odometer_km' => array('type' => 'double(24,8)', 'label' => 'OdometerKm', 'position' => 40, 'notnull' => 1, 'visible' => 1),
		'source' => array('type' => 'varchar(32)', 'label' => 'ReadingSource', 'position' => 50, 'notnull' => 1, 'visible' => 1, 'default' => 'manual', 'arrayofkeyval' => array('manual' => 'SourceManual', 'import' => 'SourceImport', 'external' => 'SourceExternal', 'consumption' => 'SourceConsumption')),
		'is_estimate' => array('type' => 'integer', 'label' => 'QxEstimate', 'position' => 51, 'notnull' => 1, 'visible' => 0, 'default' => 0),
		'provider_key' => array('type' => 'varchar(64)', 'label' => 'ImportId', 'position' => 52, 'notnull' => -1, 'visible' => 0),
		'reading_kind' => array('type' => 'varchar(32)', 'label' => 'ReadingKind', 'position' => 60, 'notnull' => 1, 'visible' => 1, 'default' => 'standard', 'arrayofkeyval' => array('standard' => 'ReadingKindStandard', 'correction' => 'ReadingKindCorrection', 'replacement' => 'ReadingKindReplacement')),
		'reason' => array('type' => 'text', 'label' => 'ReadingReason', 'position' => 70, 'notnull' => -1, 'visible' => 3),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'position' => 501, 'notnull' => 0, 'visible' => -2, 'noteditable' => 1),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'position' => 1000, 'notnull' => -1, 'visible' => -2),
	);

	/** @var int */
	public $fk_vehicle = 0;
	/** @var int */
	public $reading_date = 0;
	/** @var float */
	public $odometer_km = 0.0;
	/** @var string */
	public $source = 'manual';
	/** @var int<0,1> Imported observation, never an authoritative progression anchor */
	public $is_estimate = 0;
	/** @var string|null Idempotency key owned by the QUARTIX importer */
	public $provider_key;
	/** @var bool Derived from real neighbors when listing; not a second stored truth */
	public $estimate_conflict = false;
	/** @var float|null Previous actual anchor, calculated at display time */
	public $previous_actual_km = null;
	/** @var bool */
	private $quartixSync = false;
	/** @var string */
	public $reading_kind = 'standard';
	/** @var ?string */
	public $reason;
	/** @var bool The caller owns the current database transaction */
	private $externalTransaction = false;
	/** @var bool Authorize a change owned by a consumption object */
	private $consumptionSync = false;

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->status = 1;
	}

	/** @inheritdoc */
	public function create(User $user, $notrigger = 0)
	{
		if (!$this->externalTransaction) {
			$this->db->begin();
		}
		if ($this->lockVehicleRow((int) $this->fk_vehicle) < 0) {
			$this->db->rollback();
			return -1;
		}
		$result = parent::create($user, $notrigger);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}
		if (!$this->externalTransaction) {
			$this->db->commit();
		}

		return $result;
	}

	/** @inheritdoc */
	public function update(User $user, $notrigger = 0)
	{
		$persisted = new self($this->db);
		if (empty($this->id) || $persisted->fetch((int) $this->id) <= 0) {
			$this->error = $persisted->error ?: 'RecordNotFound';
			$this->errors = $persisted->errors;
			return -1;
		}

		if ((string) $persisted->source === 'consumption' && !$this->consumptionSync) {
			$this->error = 'ConsumptionOwnsOdometerReading';
			$this->errors[] = $this->error;
			return 0;
		}
		if (!empty($persisted->is_estimate) && !$this->quartixSync) {
			$this->error = 'QxOwnsReading';
			return -1;
		}
		if (!$this->externalTransaction) {
			$this->db->begin();
		}
		$vehicleIds = array_values(array_unique(array((int) $persisted->fk_vehicle, (int) $this->fk_vehicle)));
		sort($vehicleIds, SORT_NUMERIC);
		foreach ($vehicleIds as $vehicleId) {
			if ($this->lockVehicleRow($vehicleId) < 0) {
				$this->db->rollback();
				return -1;
			}
		}

		if ((int) $persisted->fk_vehicle !== (int) $this->fk_vehicle || (int) $persisted->reading_date !== (int) $this->reading_date) {
			$oldNeighbors = $this->fetchNeighborReadingsAt(
				(int) $persisted->entity,
				(int) $persisted->fk_vehicle,
				(int) $persisted->reading_date,
				(int) $persisted->id
			);
			if (!is_array($oldNeighbors)) {
				$this->db->rollback();
				return -1;
			}
			if (!$this->removalPreservesSequence($oldNeighbors)) {
				$this->error = 'OdometerMoveWouldExposeDecrease';
				$this->errors[] = $this->error;
				$this->db->rollback();
				return 0;
			}
		}
		$result = parent::update($user, $notrigger);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}
		if (!$this->externalTransaction) {
			$this->db->commit();
		}

		return $result;
	}

	/**
	 * Refuse a deletion that would expose an unqualified odometer decrease.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,1>
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$persisted = new self($this->db);
		if (empty($this->id) || $persisted->fetch((int) $this->id) <= 0) {
			$this->error = 'RecordNotFound';
			return -1;
		}
		if ($persisted->is_estimate) {
			$this->error = 'QxOwnsReading';
			return -1;
		}
		if ((string) $persisted->source === 'consumption' && !$this->consumptionSync) {
			$this->error = 'ConsumptionOwnsOdometerReading';
			$this->errors[] = $this->error;
			return 0;
		}
		if (!$this->externalTransaction) {
			$this->db->begin();
		}
		if ($this->lockVehicleRow((int) $this->fk_vehicle) < 0) {
			$this->db->rollback();
			return -1;
		}
		$neighbors = $this->fetchNeighborReadings();
		if (!is_array($neighbors)) {
			$this->db->rollback();
			return -1;
		}
		if (!$this->removalPreservesSequence($neighbors)) {
			$this->error = 'OdometerDeletionWouldExposeDecrease';
			$this->errors[] = $this->error;
			$this->db->rollback();
			return 0;
		}
		$result = parent::delete($user, $notrigger);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}
		if (!$this->externalTransaction) {
			$this->db->commit();
		}

		return $result;
	}

	/**
	 * Create the reading inside a transaction owned by a consumption.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,max>
	 */
	public function createFromConsumption(User $user, $notrigger = 0)
	{
		$this->source = 'consumption';
		$this->externalTransaction = true;
		$this->consumptionSync = true;
		$result = $this->create($user, $notrigger);
		$this->externalTransaction = false;
		$this->consumptionSync = false;
		return $result;
	}

	/**
	 * Update a consumption-owned reading inside the caller transaction.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,max>
	 */
	public function updateFromConsumption(User $user, $notrigger = 0)
	{
		$this->source = 'consumption';
		$this->externalTransaction = true;
		$this->consumptionSync = true;
		$result = $this->update($user, $notrigger);
		$this->externalTransaction = false;
		$this->consumptionSync = false;
		return $result;
	}

	/**
	 * Delete a consumption-owned reading inside the caller transaction.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,1>
	 */
	public function deleteFromConsumption(User $user, $notrigger = 0)
	{
		$this->externalTransaction = true;
		$this->consumptionSync = true;
		$result = $this->delete($user, $notrigger);
		$this->externalTransaction = false;
		$this->consumptionSync = false;
		return $result;
	}

	/** @inheritdoc */
	protected function validateBusinessRules()
	{
		if ($this->loadVehicleEntity() < 0) {
			return -1;
		}
		if ($this->reading_date <= 0) {
			$this->error = 'InvalidDateRange';
			$this->errors[] = $this->error;
			return -1;
		}
		if (!is_finite((float) $this->odometer_km) || $this->odometer_km < 0) {
			$this->error = 'OdometerMustBePositive';
			$this->errors[] = $this->error;
			return -1;
		}
		if ($this->is_estimate || $this->provider_key !== null) {
			if (!$this->quartixSync || $this->source !== 'external' || $this->reading_kind !== 'standard' || !$this->is_estimate || !is_string($this->provider_key) || !preg_match('/^[a-f0-9]{64}$/D', $this->provider_key)) {
				$this->error = 'QxOwnsReading';
				return -1;
			}
			// Contradictory estimates remain visible as observations. They do not
			// participate in the chain of real readings, including consumption writes.
			return 1;
		}
		if (!in_array($this->source, array('manual', 'import', 'external', 'consumption'), true)
			|| !in_array($this->reading_kind, array('standard', 'correction', 'replacement'), true)) {
			$this->error = 'ErrorBadValueForParameter';
			$this->errors[] = $this->error;
			return -1;
		}
		if ($this->source === 'consumption' && !$this->consumptionSync) {
			$this->error = 'ConsumptionOwnsOdometerReading';
			$this->errors[] = $this->error;
			return -1;
		}
		if (in_array($this->reading_kind, array('correction', 'replacement'), true) && trim((string) $this->reason) === '') {
			$this->error = 'OdometerReasonRequired';
			$this->errors[] = $this->error;
			return -1;
		}

		$neighbors = $this->fetchNeighborReadings();
		if (!is_array($neighbors)) {
			return -1;
		}
		if ($this->reading_kind === 'standard') {
			$previousKm = isset($neighbors['previous']) ? $neighbors['previous']['odometer_km'] : null;
			if (!LmdbVehicleManagementRules::odometerTransitionIsAllowed($previousKm, $this->odometer_km, $this->reading_kind, $this->reason)) {
				$this->error = 'OdometerCannotDecrease';
				$this->errors[] = $this->error;
				return -1;
			}
		}
		if (isset($neighbors['next']) && $neighbors['next']['reading_kind'] === 'standard' && $this->odometer_km > $neighbors['next']['odometer_km']) {
			$this->error = 'OdometerCannotDecrease';
			$this->errors[] = $this->error;
			return -1;
		}

		return 1;
	}

	/** @return int<-1,1> */
	private function loadVehicleEntity()
	{
		$sql = 'SELECT entity FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle';
		$sql .= ' WHERE rowid = '.((int) $this->fk_vehicle);
		$sql .= ' AND entity IN ('.getEntity('lmdbvehicle').')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($obj)) {
			$this->error = 'InvalidVehicle';
			$this->errors[] = $this->error;
			return -1;
		}
		if (is_object($this->oldcopy) && !empty($this->oldcopy->entity) && (int) $this->oldcopy->entity !== (int) $obj->entity) {
			$this->error = 'CannotMoveObjectBetweenEntities';
			$this->errors[] = $this->error;
			return -1;
		}
		$this->entity = (int) $obj->entity;
		return 1;
	}

	/**
	 * @return array{previous?:array{odometer_km:float,reading_kind:string},next?:array{odometer_km:float,reading_kind:string}}|int<-1,-1>
	 */
	private function fetchNeighborReadings()
	{
		return $this->fetchNeighborReadingsAt(
			(int) $this->entity,
			(int) $this->fk_vehicle,
			(int) $this->reading_date,
			!empty($this->id) ? (int) $this->id : 0
		);
	}

	/**
	 * Find the readings immediately before and after one chronological position.
	 *
	 * @param int $entity Owner entity
	 * @param int $vehicleId Vehicle id
	 * @param int $readingDate Reading date
	 * @param int $rowId Existing row id, or zero for a new row
	 * @return array{previous?:array{odometer_km:float,reading_kind:string},next?:array{odometer_km:float,reading_kind:string}}|int<-1,-1>
	 */
	private function fetchNeighborReadingsAt($entity, $vehicleId, $readingDate, $rowId)
	{
		$neighbors = array();
		$exclude = ' AND is_estimate = 0'.($rowId > 0 ? ' AND rowid <> '.((int) $rowId) : '');
		$sql = 'SELECT odometer_km, reading_kind FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_vehicle = '.((int) $vehicleId);
		$sql .= $exclude;
		if ($rowId > 0) {
			$sql .= " AND (reading_date < '".$this->db->idate($readingDate)."'";
			$sql .= " OR (reading_date = '".$this->db->idate($readingDate)."' AND rowid < ".((int) $rowId).'))';
		} else {
			$sql .= " AND reading_date <= '".$this->db->idate($readingDate)."'";
		}
		$sql .= ' ORDER BY reading_date DESC, rowid DESC LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		if (is_object($obj)) {
			$neighbors['previous'] = array('odometer_km' => (float) $obj->odometer_km, 'reading_kind' => (string) $obj->reading_kind);
		}
		$this->db->free($resql);

		$sql = 'SELECT odometer_km, reading_kind FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_vehicle = '.((int) $vehicleId);
		$sql .= $exclude;
		if ($rowId > 0) {
			$sql .= " AND (reading_date > '".$this->db->idate($readingDate)."'";
			$sql .= " OR (reading_date = '".$this->db->idate($readingDate)."' AND rowid > ".((int) $rowId).'))';
		} else {
			$sql .= " AND reading_date > '".$this->db->idate($readingDate)."'";
		}
		$sql .= ' ORDER BY reading_date ASC, rowid ASC LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		if (is_object($obj)) {
			$neighbors['next'] = array('odometer_km' => (float) $obj->odometer_km, 'reading_kind' => (string) $obj->reading_kind);
		}
		$this->db->free($resql);

		return $neighbors;
	}

	/**
	 * Check whether removing a reading keeps every unqualified transition monotonic.
	 *
	 * @param array{previous?:array{odometer_km:float,reading_kind:string},next?:array{odometer_km:float,reading_kind:string}} $neighbors Neighbor readings
	 * @return bool
	 */
	private function removalPreservesSequence($neighbors)
	{
		return LmdbVehicleManagementRules::odometerRemovalPreservesSequence(
			isset($neighbors['previous']) ? $neighbors['previous']['odometer_km'] : null,
			isset($neighbors['next']) ? $neighbors['next']['odometer_km'] : null,
			isset($neighbors['next']) ? $neighbors['next']['reading_kind'] : null
		);
	}

	/**
	 * @param int $vehicleId Vehicle id
	 * @param int $limit Page size (0 for full history)
	 * @param int $offset Page offset
	 * @return array<int,self>|int<-1,-1>
	 */
	public function fetchAllByVehicle($vehicleId, $limit = 0, $offset = 0)
	{
		$records = array();
		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE fk_vehicle = '.((int) $vehicleId);
		$sql .= ' AND entity IN ('.getEntity('lmdbvehicle').')';
		$sql .= ' ORDER BY reading_date DESC, rowid DESC';
		if ($limit > 0) $sql .= $this->db->plimit(min(1000, $limit), max(0, $offset));
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$record = new self($this->db);
			$record->setVarsFromFetchObj($obj);
			$records[] = $record;
		}
		$this->db->free($resql);
		$contextRecords = $records;
		if ($limit > 0 && $records) {
			// Include the nearest real anchor outside each page, without loading all history.
			foreach (array('next' => $records[0], 'previous' => $records[count($records) - 1]) as $side => $edge) {
				$operator = $side === 'next' ? '>' : '<';
				$order = $side === 'next' ? 'ASC' : 'DESC';
				$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.$this->table_element.' WHERE fk_vehicle='.((int) $vehicleId).' AND entity IN ('.getEntity('lmdbvehicle').') AND is_estimate=0';
				$sql .= " AND (reading_date".$operator."'".$this->db->idate($edge->reading_date)."' OR (reading_date='".$this->db->idate($edge->reading_date)."' AND rowid".$operator.((int) $edge->id).')) ORDER BY reading_date '.$order.',rowid '.$order.' LIMIT 1';
				$res = $this->db->query($sql);
				if (!$res) { $this->error = $this->db->lasterror(); return -1; }
				$anchor = $this->db->fetch_object($res);
				$this->db->free($res);
				if (is_object($anchor)) {
					$neighbor = new self($this->db); $neighbor->setVarsFromFetchObj($anchor);
					if ($side === 'next') array_unshift($contextRecords, $neighbor);
					else $contextRecords[] = $neighbor;
				}
			}
		}
		self::classifyEstimates($contextRecords);
		return $records;
	}

	/** @param int $vehicleId Vehicle @param self|null $after Count rows newer than this reading @return int Count or -1 */
	public function countByVehicle($vehicleId, $after = null)
	{
		$sql = 'SELECT COUNT(*) AS nb FROM '.MAIN_DB_PREFIX.$this->table_element.' WHERE fk_vehicle='.((int) $vehicleId).' AND entity IN ('.getEntity('lmdbvehicle').')';
		if ($after !== null) $sql .= " AND (reading_date>'".$this->db->idate($after->reading_date)."' OR (reading_date='".$this->db->idate($after->reading_date)."' AND rowid>".((int) $after->id).'))';
		$res = $this->db->query($sql);
		if (!$res) { $this->error = $this->db->lasterror(); return -1; }
		$row = $this->db->fetch_object($res); $this->db->free($res);
		return is_object($row) ? (int) $row->nb : -1;
	}

	/**
	 * Re-evaluate observations after real readings change, without rewriting history.
	 * @param array<int,self> $records Records sorted by descending date/id
	 * @return void
	 */
	public static function classifyEstimates(&$records)
	{
		$next = null;
		foreach ($records as $record) {
			$record->estimate_conflict = false;
			if (!$record->is_estimate) { $next = $record; continue; }
			if ($next !== null && (($next->reading_kind === 'standard' && $record->odometer_km > $next->odometer_km)
				|| ($record->reading_date === $next->reading_date && $record->odometer_km != $next->odometer_km))) $record->estimate_conflict = true;
		}
		$previous = null;
		foreach (array_reverse($records) as $record) {
			$record->previous_actual_km = null;
			if (!$record->is_estimate) {
				$record->previous_actual_km = $previous !== null ? (float) $previous->odometer_km : null;
				$previous = $record;
				continue;
			}
			if ($previous !== null && ($record->odometer_km < $previous->odometer_km
				|| ($record->reading_date === $previous->reading_date && $record->odometer_km != $previous->odometer_km))) $record->estimate_conflict = true;
		}
	}

	/**
	 * One observation per source day. Replays may refresh that observation, never a real reading.
	 * Caller owns the entity-wide QUARTIX lock; this method also locks the parent vehicle.
	 * @param User $user Sync actor @param int $vehicleId Local vehicle @param int $remoteId Remote vehicle
	 * @param int $date Observation timestamp @param string $day Day in the vehicle timezone @param float $km Estimate
	 * @return int Positive id, or -1
	 */
	public function saveQuartix(User $user, $vehicleId, $remoteId, $date, $day, $km)
	{
		global $conf, $langs;
		require_once __DIR__.'/lmdbvehiclequartixconfig.class.php';
		require_once __DIR__.'/lmdbvehiclequartixrules.class.php';
		if (!LmdbVehicleQuartixConfig::can($user, 'sync') || (!LmdbVehicleQuartixConfig::isAdmin($user) && !$user->hasRight('lmdbvehiclemanagement', 'odometer', 'write')) || $date <= 0 || $date > dol_now() + 300 || !is_finite($km) || $km < 0 || $remoteId <= 0) {
			$this->error = 'QxAccessDenied'; return -1;
		}
		$this->db->begin();
		try {
			LmdbVehicleQuartixRules::day($day);
			// Reusing a loaded real reading must never turn it into an estimate.
			$this->id = 0;
			$this->oldcopy = null;
			if ($this->lockVehicleRow($vehicleId) < 0) throw new RuntimeException('QxDatabaseError');
			$mapping = $this->db->query('SELECT l.timezone FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link AS l INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid=l.fk_vehicle AND v.entity=l.entity WHERE l.entity='.((int) $conf->entity).' AND l.fk_vehicle='.((int) $vehicleId).' AND l.remote_id='.((int) $remoteId).' AND l.active=1');
			if (!$mapping) throw new RuntimeException('QxDatabaseError');
			$owner = $this->db->fetch_object($mapping);
			$this->db->free($mapping);
			if (!is_object($owner) || (new DateTimeImmutable('@'.$date))->setTimezone(new DateTimeZone($owner->timezone))->format('Y-m-d') !== $day) throw new RuntimeException('QxAccessDenied');
			$key = hash('sha256', $remoteId.':'.$day);
			$res = $this->db->query('SELECT rowid, reading_date FROM '.MAIN_DB_PREFIX.$this->table_element.' WHERE entity = '.((int) $conf->entity).' AND fk_vehicle = '.((int) $vehicleId)." AND provider_key = '".$key."'");
			if (!$res) throw new RuntimeException('QxDatabaseError');
			$row = $this->db->fetch_object($res);
			$this->db->free($res);
			if (is_object($row)) {
				if ($this->fetch((int) $row->rowid) <= 0) throw new RuntimeException('QxDatabaseError');
				if ($this->reading_date > $date || ($this->reading_date === $date && (float) $this->odometer_km === $km)) { $this->db->commit(); return (int) $this->id; }
			}
			$this->fk_vehicle = $vehicleId;
			if ($this->loadVehicleEntity() < 0 || (int) $this->entity !== (int) $conf->entity) throw new RuntimeException('QxAccessDenied');
			$langs->load('lmdbvehiclemanagement@lmdbvehiclemanagement');
			$this->source = 'external'; $this->is_estimate = 1; $this->provider_key = $key;
			$this->reading_kind = 'standard'; $this->reading_date = $date; $this->odometer_km = $km;
			$this->reason = $langs->transnoentities('QxEstimate');
			$this->context = array('trigger_reason' => 'quartix_estimate');
			$this->quartixSync = true; $this->externalTransaction = true;
			$result = empty($this->id) ? $this->create($user) : $this->update($user);
			if ($result <= 0) throw new RuntimeException('QxDatabaseError');
			$this->db->commit();
			return (int) $this->id;
		} catch (Exception $e) {
			$this->db->rollback(); $this->error = $e->getMessage(); return -1;
		} finally { $this->quartixSync = false; $this->externalTransaction = false; }
	}

	/** @inheritdoc */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;
		$label = $langs->trans('OdometerReading');
		return dolGetStatus($label, $label, '', 'status4', $mode);
	}

	/** @inheritdoc */
	protected function getCardPage()
	{
		return 'vehicle_odometer.php';
	}

	/** @inheritdoc */
	protected function getCardUrlParameters()
	{
		return 'id='.((int) $this->fk_vehicle).'&reading_id='.((int) $this->id);
	}
}
