<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementobject.class.php');

/**
 * Real-world vehicle event.
 */
class LmdbVehicleEvent extends LmdbVehicleManagementObject
{
	public const STATUS_OPEN = 0;
	public const STATUS_IN_PROGRESS = 1;
	public const STATUS_CLOSED = 2;
	public const STATUS_CANCELLED = 9;

	/** @var string */
	public $element = 'lmdbvehicleevent';
	/** @var string */
	public $table_element = 'lmdbvehiclemanagement_vehicle_event';
	/** @var string */
	public $TRIGGER_PREFIX = 'LMDBVEHICLEMANAGEMENT_EVENT';
	/** @var int<0,1> */
	public $has_document_storage = 1;
	/** @var string */
	public $entity_scope_element = 'lmdbvehicle';
	/** @var string */
	public $picto = 'calendar-day';

	/** @var array<string,mixed> */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'searchall' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'position' => 20, 'notnull' => 1, 'visible' => 0, 'default' => 1, 'index' => 1),
		'fk_vehicle' => array('type' => 'integer:LmdbVehicle:lmdbvehiclemanagement/class/lmdbvehicle.class.php:0', 'label' => 'Vehicle', 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'event_type' => array('type' => 'varchar(32)', 'label' => 'EventType', 'position' => 40, 'notnull' => 1, 'visible' => 1, 'arrayofkeyval' => array('maintenance' => 'EventTypeMaintenance', 'breakdown' => 'EventTypeBreakdown', 'accident' => 'EventTypeAccident', 'inspection' => 'EventTypeInspection', 'administrative' => 'EventTypeAdministrative', 'other' => 'EventTypeOther')),
		'event_subtype' => array('type' => 'varchar(64)', 'label' => 'EventSubtype', 'position' => 50, 'notnull' => -1, 'visible' => -1),
		'event_date' => array('type' => 'datetime', 'label' => 'EventDate', 'position' => 60, 'notnull' => 1, 'visible' => 1),
		'fk_user_driver' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Driver', 'position' => 70, 'notnull' => -1, 'visible' => -1, 'index' => 1),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php:1', 'label' => 'ThirdParty', 'position' => 80, 'notnull' => -1, 'visible' => -1, 'index' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'position' => 90, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'description' => array('type' => 'text', 'label' => 'Description', 'position' => 100, 'notnull' => -1, 'visible' => 3),
		'severity' => array('type' => 'integer', 'label' => 'Severity', 'position' => 110, 'notnull' => 1, 'visible' => 1, 'default' => 1, 'arrayofkeyval' => array(1 => 'SeverityLow', 2 => 'SeverityMedium', 3 => 'SeverityHigh')),
		'is_immobilized' => array('type' => 'boolean', 'label' => 'VehicleImmobilized', 'position' => 120, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'immobilization_start' => array('type' => 'datetime', 'label' => 'ImmobilizationStart', 'position' => 130, 'notnull' => -1, 'visible' => -1),
		'immobilization_end' => array('type' => 'datetime', 'label' => 'ImmobilizationEnd', 'position' => 140, 'notnull' => -1, 'visible' => -1),
		'odometer_km' => array('type' => 'double(24,8)', 'label' => 'OdometerKm', 'position' => 150, 'notnull' => -1, 'visible' => -1),
		'status' => array('type' => 'integer', 'label' => 'Status', 'position' => 160, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'default' => 0, 'arrayofkeyval' => array(0 => 'VehicleEventStatusOpen', 1 => 'VehicleEventStatusInProgress', 2 => 'VehicleEventStatusClosed', 9 => 'VehicleEventStatusCancelled')),
		'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'position' => 170, 'notnull' => -1, 'visible' => 0),
		'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'position' => 180, 'notnull' => -1, 'visible' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'position' => 501, 'notnull' => 0, 'visible' => -2, 'noteditable' => 1),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'model_pdf' => array('type' => 'varchar(255)', 'label' => 'Model', 'position' => 1010, 'notnull' => -1, 'visible' => 0),
		'last_main_doc' => array('type' => 'varchar(255)', 'label' => 'LastMainDoc', 'position' => 1020, 'notnull' => -1, 'visible' => 0),
	);

	/** @var string */
	public $ref = '';
	/** @var int */
	public $fk_vehicle = 0;
	/** @var string */
	public $event_type = 'other';
	/** @var ?string */
	public $event_subtype;
	/** @var int */
	public $event_date = 0;
	/** @var ?int */
	public $fk_user_driver;
	/** @var ?int */
	public $fk_soc;
	/** @var ?int */
	public $socid;
	/** @var string */
	public $label = '';
	/** @var ?string */
	public $description;
	/** @var int */
	public $severity = 1;
	/** @var int<0,1> */
	public $is_immobilized = 0;
	/** @var ?int */
	public $immobilization_start;
	/** @var ?int */
	public $immobilization_end;
	/** @var ?float */
	public $odometer_km;
	/** @var ?string */
	public $note_public;
	/** @var ?string */
	public $note_private;
	/** @var ?string */
	public $model_pdf;
	/** @var ?string */
	public $last_main_doc;

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->status = self::STATUS_OPEN;
	}

	/**
	 * Load the linked third party into CommonObject's standard property.
	 *
	 * @param int $id Object id
	 * @param ?string $ref Reference
	 * @return int<-4,1>
	 */
	public function fetch($id, $ref = null)
	{
		$result = parent::fetch($id, $ref);
		if ($result > 0) {
			$this->socid = !empty($this->fk_soc) ? (int) $this->fk_soc : null;
		}

		return $result;
	}

	/** @inheritdoc */
	protected function validateBusinessRules()
	{
		global $langs;

		if ($this->loadVehicleEntity() < 0) {
			return -1;
		}
		if ($this->event_date <= 0) {
			$this->error = 'InvalidDateRange';
			$this->errors[] = $this->error;
			return -1;
		}
		if (trim($this->label) === '') {
			$this->error = $langs->trans('FieldRequired', $langs->trans('Label'));
			$this->errors[] = $this->error;
			return -1;
		}
		if (!in_array($this->event_type, array('maintenance', 'breakdown', 'accident', 'inspection', 'administrative', 'other'), true)
			|| !in_array((int) $this->severity, array(1, 2, 3), true)
			|| !in_array((int) $this->is_immobilized, array(0, 1), true)
			|| !in_array((int) $this->status, array(self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_CLOSED, self::STATUS_CANCELLED), true)) {
			$this->error = 'ErrorBadValueForParameter';
			$this->errors[] = $this->error;
			return -1;
		}
		if ($this->odometer_km !== null && $this->odometer_km < 0) {
			$this->error = 'OdometerMustBePositive';
			$this->errors[] = $this->error;
			return -1;
		}
		if (!empty($this->immobilization_start) && !empty($this->immobilization_end) && $this->immobilization_end < $this->immobilization_start) {
			$this->error = 'InvalidDateRange';
			$this->errors[] = $this->error;
			return -1;
		}
		if (!empty($this->fk_user_driver)) {
			$driverExists = $this->linkedRecordExists('user', (int) $this->fk_user_driver, getEntity('user'));
			if ($driverExists < 0) {
				return -1;
			}
			if ($driverExists === 0) {
				$this->error = 'InvalidDriver';
				$this->errors[] = $this->error;
				return -1;
			}
		}
		if (!empty($this->fk_soc)) {
			$thirdPartyExists = $this->linkedRecordExists('societe', (int) $this->fk_soc, getEntity('societe'));
			if ($thirdPartyExists < 0) {
				return -1;
			}
			if ($thirdPartyExists === 0) {
				$this->error = 'InvalidThirdParty';
				$this->errors[] = $this->error;
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Check a linked record without copying its data.
	 *
	 * @param string $table Table without prefix
	 * @param int $id Row id
	 * @param string $entities Entity scope
	 * @return int<-1,1> -1 on SQL error, 0 when absent, 1 when present
	 */
	private function linkedRecordExists($table, $id, $entities)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$this->db->sanitize($table);
		$sql .= ' WHERE rowid = '.((int) $id);
		if ($table === 'user') {
			$sql .= ' AND entity IN ('.$entities.') AND statut = 1';
		} elseif ($entities !== '') {
			$sql .= ' AND entity IN ('.$entities.')';
		}
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

	/** @inheritdoc */
	protected function getNextNumRef()
	{
		global $langs;
		$model = getDolGlobalString('LMDBVEHICLEMANAGEMENT_LMDBVEHICLEEVENT_ADDON', 'mod_lmdbvehicleevent_standard');
		$file = dol_buildpath('/lmdbvehiclemanagement/core/modules/lmdbvehiclemanagement/'.$model.'.php', 0);
		if ($model === '' || !is_readable($file)) {
			$this->error = $langs->trans('ErrorNumRefModelNotFound');
			return -1;
		}
		require_once $file;
		if (!class_exists($model)) {
			$this->error = $langs->trans('ErrorNumRefModelNotFound');
			return -1;
		}
		$numbering = new $model();
		$next = $numbering->getNextValue($this);
		if (!is_string($next)) {
			$this->error = $numbering->error;
		}
		return $next;
	}

	/** @inheritdoc */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;
		$labels = array(self::STATUS_OPEN => 'VehicleEventStatusOpen', self::STATUS_IN_PROGRESS => 'VehicleEventStatusInProgress', self::STATUS_CLOSED => 'VehicleEventStatusClosed', self::STATUS_CANCELLED => 'VehicleEventStatusCancelled');
		$types = array(self::STATUS_OPEN => 'status1', self::STATUS_IN_PROGRESS => 'status3', self::STATUS_CLOSED => 'status4', self::STATUS_CANCELLED => 'status6');
		$label = isset($labels[$status]) ? $langs->trans($labels[$status]) : (string) $status;
		return dolGetStatus($label, $label, '', isset($types[$status]) ? $types[$status] : 'status0', $mode);
	}

	/** @inheritdoc */
	protected function getCardPage()
	{
		return 'vehicleevent_card.php';
	}
}
