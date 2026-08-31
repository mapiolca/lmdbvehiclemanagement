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
		if ((string) $this->source === 'consumption' && !$this->consumptionSync) {
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
		if ($this->odometer_km < 0) {
			$this->error = 'OdometerMustBePositive';
			$this->errors[] = $this->error;
			return -1;
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
		$exclude = $rowId > 0 ? ' AND rowid <> '.((int) $rowId) : '';
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
	 * @return array<int,self>|int<-1,-1>
	 */
	public function fetchAllByVehicle($vehicleId)
	{
		$records = array();
		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE fk_vehicle = '.((int) $vehicleId);
		$sql .= ' AND entity IN ('.getEntity('lmdbvehicle').')';
		$sql .= ' ORDER BY reading_date DESC, rowid DESC';
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
		return $records;
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
