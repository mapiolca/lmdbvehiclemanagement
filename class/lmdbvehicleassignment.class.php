<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementobject.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleregulatoryservice.class.php');

/**
 * Driver assignment for a vehicle.
 */
class LmdbVehicleAssignment extends LmdbVehicleManagementObject
{
	public const STATUS_INACTIVE = 0;
	public const STATUS_ACTIVE = 1;

	/** @var string */
	public $element = 'lmdbvehicleassignment';
	/** @var string */
	public $table_element = 'lmdbvehiclemanagement_vehicle_assignment';
	/** @var string */
	public $TRIGGER_PREFIX = 'LMDBVEHICLEMANAGEMENT_ASSIGNMENT';
	/** @var string */
	public $entity_scope_element = 'lmdbvehicle';
	/** @var string */
	public $picto = 'user-clock';

	/** @var array<string,mixed> */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'position' => 10, 'notnull' => 1, 'visible' => 0, 'default' => 1, 'index' => 1),
		'fk_vehicle' => array('type' => 'integer:LmdbVehicle:lmdbvehiclemanagement/class/lmdbvehicle.class.php:0', 'label' => 'Vehicle', 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_user_driver' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Driver', 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'date_start' => array('type' => 'datetime', 'label' => 'AssignmentStart', 'position' => 40, 'notnull' => 1, 'visible' => 1),
		'date_end' => array('type' => 'datetime', 'label' => 'AssignmentEnd', 'position' => 50, 'notnull' => -1, 'visible' => 1),
		'assignment_type' => array('type' => 'varchar(32)', 'label' => 'AssignmentType', 'position' => 60, 'notnull' => 1, 'visible' => 1, 'default' => 'driver', 'arrayofkeyval' => array('driver' => 'AssignmentTypeDriver', 'custodian' => 'AssignmentTypeCustodian', 'pool' => 'AssignmentTypePool')),
		'is_primary' => array('type' => 'boolean', 'label' => 'PrimaryAssignment', 'position' => 70, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'reason' => array('type' => 'text', 'label' => 'AssignmentReason', 'position' => 80, 'notnull' => -1, 'visible' => 3),
		'status' => array('type' => 'integer', 'label' => 'Status', 'position' => 90, 'notnull' => 1, 'visible' => 1, 'default' => 1, 'arrayofkeyval' => array(0 => 'Disabled', 1 => 'Enabled')),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'position' => 501, 'notnull' => 0, 'visible' => -2, 'noteditable' => 1),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'position' => 1000, 'notnull' => -1, 'visible' => -2),
	);

	/** @var int */
	public $fk_vehicle = 0;
	/** @var int */
	public $fk_user_driver = 0;
	/** @var int */
	public $date_start = 0;
	/** @var ?int */
	public $date_end;
	/** @var string */
	public $assignment_type = 'driver';
	/** @var int<0,1> */
	public $is_primary = 0;
	/** @var ?string */
	public $reason;
	/** @var ?string Driver login loaded for list rendering */
	public $driver_login;
	/** @var ?string Driver first name loaded for list rendering */
	public $driver_firstname;
	/** @var ?string Driver last name loaded for list rendering */
	public $driver_lastname;

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->status = self::STATUS_ACTIVE;
	}

	/** @inheritdoc */
	public function create(User $user, $notrigger = 0)
	{
		$this->db->begin();
		if ($this->lockVehicleRow((int) $this->fk_vehicle) < 0) {
			$this->db->rollback();
			return -1;
		}
		if ((int) $this->status === self::STATUS_ACTIVE) {
			$regulatory = new LmdbVehicleRegulatoryService($this->db);
			$allowed = $regulatory->vehicleActionIsAllowed((int) $this->fk_vehicle, 'assignment');
			if ($allowed <= 0) {
				$this->error = $regulatory->error;
				$this->errors = $regulatory->errors;
				$this->db->rollback();
				return $allowed < 0 ? -1 : 0;
			}
		}
		$result = parent::create($user, $notrigger);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		return $result;
	}

	/** @inheritdoc */
	public function update(User $user, $notrigger = 0)
	{
		$this->db->begin();
		if ($this->lockVehicleRow((int) $this->fk_vehicle) < 0) {
			$this->db->rollback();
			return -1;
		}
		if ((int) $this->status === self::STATUS_ACTIVE) {
			$regulatory = new LmdbVehicleRegulatoryService($this->db);
			$allowed = $regulatory->vehicleActionIsAllowed((int) $this->fk_vehicle, 'assignment');
			if ($allowed <= 0) {
				$this->error = $regulatory->error;
				$this->errors = $regulatory->errors;
				$this->db->rollback();
				return $allowed < 0 ? -1 : 0;
			}
		}
		$result = parent::update($user, $notrigger);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		return $result;
	}

	/** @inheritdoc */
	protected function validateBusinessRules()
	{
		global $langs;

		if ($this->loadVehicleEntity() < 0) {
			return -1;
		}
		if ($this->fk_user_driver <= 0) {
			$this->error = 'InvalidDriver';
			$this->errors[] = $this->error;
			return -1;
		}
		$driverExists = $this->driverExists();
		if ($driverExists < 0) {
			return -1;
		}
		if ($driverExists === 0) {
			$this->error = 'InvalidDriver';
			$this->errors[] = $this->error;
			return -1;
		}
		if ($this->date_start <= 0 || (!empty($this->date_end) && $this->date_end < $this->date_start)) {
			$this->error = 'AssignmentDateRangeInvalid';
			$this->errors[] = $this->error;
			return -1;
		}
		if (!in_array($this->assignment_type, array('driver', 'custodian', 'pool'), true)) {
			$this->error = $langs->trans('ErrorBadValueForParameter', 'assignment_type');
			$this->errors[] = $this->error;
			return -1;
		}
		if (!in_array((int) $this->is_primary, array(0, 1), true) || !in_array((int) $this->status, array(self::STATUS_INACTIVE, self::STATUS_ACTIVE), true)) {
			$this->error = 'ErrorBadValueForParameter';
			$this->errors[] = $this->error;
			return -1;
		}
		if ((int) $this->is_primary === 1 && (int) $this->status === self::STATUS_ACTIVE) {
			$overlap = $this->hasPrimaryOverlap();
			if ($overlap < 0) {
				return -1;
			}
			if ($overlap > 0) {
				$this->error = 'PrimaryAssignmentOverlap';
				$this->errors[] = $this->error;
				return -1;
			}
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

	/** @return int<-1,1> -1 on SQL error, 0 when absent, 1 when present */
	private function driverExists()
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'user';
		$sql .= ' WHERE rowid = '.((int) $this->fk_user_driver);
		$sql .= ' AND entity IN ('.getEntity('user').') AND statut = 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
		$exists = $this->db->num_rows($resql) > 0;
		$this->db->free($resql);

		return $exists ? 1 : 0;
	}

	/** @return int<-1,1> -1 on SQL error, 0 without overlap, 1 with overlap */
	private function hasPrimaryOverlap()
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE entity = '.((int) $this->entity);
		$sql .= ' AND fk_vehicle = '.((int) $this->fk_vehicle);
		$sql .= ' AND is_primary = 1 AND status = '.self::STATUS_ACTIVE;
		if (!empty($this->id)) {
			$sql .= ' AND rowid <> '.((int) $this->id);
		}
		if (!empty($this->date_end)) {
			$sql .= " AND date_start <= '".$this->db->idate($this->date_end)."'";
		}
		$sql .= " AND (date_end IS NULL OR date_end >= '".$this->db->idate($this->date_start)."')";
		$sql .= ' LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
		$overlap = $this->db->num_rows($resql) > 0;
		$this->db->free($resql);

		return $overlap ? 1 : 0;
	}

	/**
	 * Fetch assignments for one vehicle.
	 *
	 * @param int $vehicleId Vehicle id
	 * @return array<int,self>|int<-1,-1>
	 */
	public function fetchAllByVehicle($vehicleId)
	{
		$records = array();
		$sql = 'SELECT a.*, u.login AS driver_login, u.firstname AS driver_firstname, u.lastname AS driver_lastname';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element.' AS a';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user AS u ON u.rowid = a.fk_user_driver';
		$sql .= ' WHERE a.fk_vehicle = '.((int) $vehicleId);
		$sql .= ' AND a.entity IN ('.getEntity('lmdbvehicle').')';
		$sql .= ' ORDER BY a.date_start DESC, a.rowid DESC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$record = new self($this->db);
			$record->setVarsFromFetchObj($obj);
			$record->driver_login = isset($obj->driver_login) ? (string) $obj->driver_login : null;
			$record->driver_firstname = isset($obj->driver_firstname) ? (string) $obj->driver_firstname : null;
			$record->driver_lastname = isset($obj->driver_lastname) ? (string) $obj->driver_lastname : null;
			$records[] = $record;
		}
		$this->db->free($resql);

		return $records;
	}

	/** @inheritdoc */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;
		$label = $status === self::STATUS_ACTIVE ? $langs->trans('Enabled') : $langs->trans('Disabled');
		return dolGetStatus($label, $label, '', $status === self::STATUS_ACTIVE ? 'status4' : 'status6', $mode);
	}

	/** @inheritdoc */
	protected function getCardPage()
	{
		return 'vehicle_assignment.php';
	}

	/** @inheritdoc */
	protected function getCardUrlParameters()
	{
		return 'id='.((int) $this->fk_vehicle).'&assignment_id='.((int) $this->id);
	}
}
