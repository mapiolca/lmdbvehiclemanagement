<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementobject.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementrules.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleenergy.class.php');

/**
 * Vehicle dossier object.
 */
class LmdbVehicle extends LmdbVehicleManagementObject
{
	public const STATUS_DRAFT = 0;
	public const STATUS_VALIDATED = 1;
	public const STATUS_IN_SERVICE = 2;
	public const STATUS_OUT_OF_SERVICE = 3;
	public const STATUS_SOLD = 4;

	/** @var string */
	public $element = 'lmdbvehicle';

	/** @var string */
	public $table_element = 'lmdbvehiclemanagement_vehicle';

	/** @var string */
	public $TRIGGER_PREFIX = 'LMDBVEHICLEMANAGEMENT_VEHICLE';
	/** @var int<0,1> */
	public $has_document_storage = 1;

	/** @var array<string,mixed> */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'position' => 20, 'notnull' => 1, 'visible' => 0, 'default' => 1, 'index' => 1),
		'registration_number' => array('type' => 'varchar(32)', 'label' => 'RegistrationNumber', 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 2),
		'vin' => array('type' => 'varchar(64)', 'label' => 'VIN', 'position' => 40, 'notnull' => -1, 'visible' => 1, 'index' => 1, 'searchall' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'position' => 50, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'brand' => array('type' => 'varchar(128)', 'label' => 'Brand', 'position' => 60, 'notnull' => -1, 'visible' => 1, 'searchall' => 1),
		'model' => array('type' => 'varchar(128)', 'label' => 'VehicleModel', 'position' => 70, 'notnull' => -1, 'visible' => 1, 'searchall' => 1),
		'vehicle_version' => array('type' => 'varchar(128)', 'label' => 'VehicleVersion', 'position' => 80, 'notnull' => -1, 'visible' => -1),
		'fk_energy' => array('type' => 'integer:LmdbVehicleEnergy:lmdbvehiclemanagement/class/lmdbvehicleenergy.class.php', 'label' => 'Energy', 'position' => 90, 'notnull' => -1, 'visible' => -1, 'index' => 1),
		'first_registration_date' => array('type' => 'date', 'label' => 'FirstRegistrationDate', 'position' => 100, 'notnull' => -1, 'visible' => -1),
		'commissioning_date' => array('type' => 'date', 'label' => 'CommissioningDate', 'position' => 110, 'notnull' => -1, 'visible' => -1),
		'ownership_type' => array('type' => 'varchar(32)', 'label' => 'OwnershipType', 'position' => 120, 'notnull' => -1, 'visible' => -1, 'arrayofkeyval' => array('owned' => 'Owned', 'leased' => 'Leased', 'long_term_leased' => 'LongTermLeased', 'short_term_leased' => 'ShortTermLeased')),
		'fk_soc_owner' => array('type' => 'integer:Societe:societe/class/societe.class.php:1', 'label' => 'OwnerThirdParty', 'position' => 130, 'notnull' => -1, 'visible' => -1, 'index' => 1),
		'fk_resource' => array('type' => 'integer:Dolresource:resource/class/dolresource.class.php:0', 'label' => 'LinkedResource', 'position' => 140, 'notnull' => -1, 'visible' => -1, 'enabled' => 'isModEnabled("resource")', 'index' => 1),
		'description' => array('type' => 'text', 'label' => 'Description', 'position' => 150, 'notnull' => -1, 'visible' => 3),
		'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'position' => 160, 'notnull' => -1, 'visible' => 0),
		'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'position' => 170, 'notnull' => -1, 'visible' => 0),
		'status' => array('type' => 'integer', 'label' => 'Status', 'position' => 200, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'default' => 0, 'arrayofkeyval' => array(0 => 'VehicleStatusDraft', 1 => 'VehicleStatusValidated', 2 => 'VehicleStatusInService', 3 => 'VehicleStatusOutOfService', 4 => 'VehicleStatusSold')),
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
	/** @var string */
	public $registration_number = '';
	/** @var ?string */
	public $vin;
	/** @var string */
	public $label = '';
	/** @var ?string */
	public $brand;
	/** @var ?string */
	public $model;
	/** @var ?string */
	public $vehicle_version;
	/** @var ?int */
	public $fk_energy;
	/** @var ?int */
	public $first_registration_date;
	/** @var ?int */
	public $commissioning_date;
	/** @var ?string */
	public $ownership_type;
	/** @var ?int */
	public $fk_soc_owner;
	/** @var ?int */
	public $fk_resource;
	/** @var ?string */
	public $description;
	/** @var ?string */
	public $note_public;
	/** @var ?string */
	public $note_private;
	/** @var ?string */
	public $model_pdf;
	/** @var ?string */
	public $last_main_doc;

	/** @var bool Allow one controlled lifecycle update */
	private $statusTransitionInProgress = false;

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->status = self::STATUS_DRAFT;
	}

	/**
	 * Always create vehicles as drafts, regardless of submitted data.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,max>
	 */
	public function create(User $user, $notrigger = 0)
	{
		$this->status = self::STATUS_DRAFT;

		return parent::create($user, $notrigger);
	}

	/**
	 * Prevent ordinary form updates from changing lifecycle state.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,max>
	 */
	public function update(User $user, $notrigger = 0)
	{
		if (!$this->statusTransitionInProgress && !empty($this->id)) {
			$current = new self($this->db);
			if ($current->fetch((int) $this->id) <= 0) {
				$this->error = $current->error !== '' ? $current->error : 'RecordNotFound';
				$this->errors = $current->errors;
				return -1;
			}
			if ((int) $this->status !== (int) $current->status) {
				$this->error = 'VehicleStatusMustUseAction';
				$this->errors[] = $this->error;
				return -1;
			}
		}

		return parent::update($user, $notrigger);
	}

	/**
	 * Prevent orphaning related business records.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,1>
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$tables = array(
			'lmdbvehiclemanagement_vehicle_assignment',
			'lmdbvehiclemanagement_odometer_reading',
			'lmdbvehiclemanagement_vehicle_event',
		);
		foreach ($tables as $table) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$table;
			$sql .= ' WHERE fk_vehicle = '.((int) $this->id);
			$sql .= ' AND entity = '.((int) $this->entity).' LIMIT 1';
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$hasRecord = $this->db->num_rows($resql) > 0;
			$this->db->free($resql);
			if ($hasRecord) {
				$this->error = 'VehicleHasRelatedRecords';
				$this->errors[] = $this->error;
				return 0;
			}
		}

		$agendaElementTypes = array('lmdbvehicle', 'lmdbvehicle@lmdbvehiclemanagement', 'lmdbvehiclemanagement_lmdbvehicle');
		$quotedAgendaElementTypes = array();
		foreach ($agendaElementTypes as $agendaElementType) {
			$quotedAgendaElementTypes[] = "'".$this->db->escape($agendaElementType)."'";
		}
		$sql = 'SELECT id FROM '.MAIN_DB_PREFIX.'actioncomm';
		$sql .= ' WHERE fk_element = '.((int) $this->id);
		$sql .= ' AND elementtype IN ('.implode(', ', $quotedAgendaElementTypes).')';
		$sql .= ' AND entity = '.((int) $this->entity).' LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$hasAgendaEvent = $this->db->num_rows($resql) > 0;
		$this->db->free($resql);
		if ($hasAgendaEvent) {
			$this->error = 'VehicleHasAgendaEvents';
			$this->errors[] = $this->error;
			return 0;
		}

		return parent::delete($user, $notrigger);
	}

	/**
	 * Validate a draft vehicle.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,max>
	 */
	public function validate(User $user, $notrigger = 0)
	{
		return $this->changeStatus(self::STATUS_VALIDATED, $user, $notrigger);
	}

	/**
	 * Put a validated or out-of-service vehicle into service.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,max>
	 */
	public function setInService(User $user, $notrigger = 0)
	{
		return $this->changeStatus(self::STATUS_IN_SERVICE, $user, $notrigger);
	}

	/**
	 * Put an in-service vehicle out of service.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,max>
	 */
	public function setOutOfService(User $user, $notrigger = 0)
	{
		return $this->changeStatus(self::STATUS_OUT_OF_SERVICE, $user, $notrigger);
	}

	/**
	 * Mark a validated, in-service or out-of-service vehicle as transferred or sold.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,max>
	 */
	public function setSold(User $user, $notrigger = 0)
	{
		return $this->changeStatus(self::STATUS_SOLD, $user, $notrigger);
	}

	/**
	 * Apply one lifecycle transition under a row lock and emit one CRUD UPDATE.
	 *
	 * @param int $targetStatus Target status
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,max>
	 */
	private function changeStatus($targetStatus, User $user, $notrigger = 0)
	{
		if (empty($this->id)) {
			$this->error = 'RecordNotFound';
			$this->errors[] = $this->error;
			return -1;
		}

		$this->db->begin();
		$sql = 'SELECT status, commissioning_date FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE rowid = '.((int) $this->id).' AND entity IN ('.getEntity('lmdbvehicle').') FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			$this->db->rollback();
			return -1;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($row)) {
			$this->error = 'RecordNotFound';
			$this->errors[] = $this->error;
			$this->db->rollback();
			return -1;
		}

		$oldStatus = (int) $row->status;
		if (!LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed($oldStatus, $targetStatus)) {
			$this->error = 'InvalidVehicleStatusTransition';
			$this->errors[] = $this->error;
			$this->db->rollback();
			return 0;
		}
		$fetchResult = $this->fetch((int) $this->id);
		if ($fetchResult <= 0) {
			$this->error = $this->error !== '' ? $this->error : 'RecordNotFound';
			$this->errors[] = $this->error;
			$this->db->rollback();
			return -1;
		}

		$this->status = $targetStatus;
		if ($targetStatus === self::STATUS_IN_SERVICE && empty($row->commissioning_date) && empty($this->commissioning_date)) {
			$this->commissioning_date = dol_now();
		}
		$this->context['trigger_reason'] = 'status_change';
		$this->context['old_status'] = $oldStatus;
		$this->context['new_status'] = $targetStatus;
		$this->statusTransitionInProgress = true;
		$result = parent::update($user, $notrigger);
		$this->statusTransitionInProgress = false;
		if ($result <= 0) {
			$this->db->rollback();
			return $result;
		}
		$this->db->commit();

		return $result;
	}

	/** @inheritdoc */
	protected function validateBusinessRules()
	{
		global $langs;

		$this->registration_number = strtoupper(trim($this->registration_number));
		$this->vin = trim((string) $this->vin) !== '' ? strtoupper(trim((string) $this->vin)) : null;
		if (trim($this->registration_number) === '') {
			$this->error = $langs->trans('FieldRequired', $langs->trans('RegistrationNumber'));
			$this->errors[] = $this->error;
			return -1;
		}
		if (trim($this->label) === '') {
			$this->error = $langs->trans('FieldRequired', $langs->trans('Label'));
			$this->errors[] = $this->error;
			return -1;
		}
		if (!in_array((int) $this->status, array(self::STATUS_DRAFT, self::STATUS_VALIDATED, self::STATUS_IN_SERVICE, self::STATUS_OUT_OF_SERVICE, self::STATUS_SOLD), true)) {
			$this->error = 'InvalidStatus';
			$this->errors[] = $this->error;
			return -1;
		}
		if (!empty($this->fk_energy)) {
			$energy = new LmdbVehicleEnergy($this->db);
			if ($energy->fetch((int) $this->fk_energy) <= 0) {
				$this->error = 'InvalidVehicleEnergy';
				$this->errors[] = $this->error;
				return -1;
			}
		}
		$duplicateRegistration = $this->duplicateVehicleFieldExists('registration_number', $this->registration_number);
		if ($duplicateRegistration < 0) {
			return -1;
		}
		if ($duplicateRegistration > 0) {
			$this->error = 'DuplicateRegistrationNumber';
			$this->errors[] = $this->error;
			return -1;
		}
		if ($this->vin !== null) {
			$duplicateVin = $this->duplicateVehicleFieldExists('vin', $this->vin);
			if ($duplicateVin < 0) {
				return -1;
			}
			if ($duplicateVin > 0) {
				$this->error = 'DuplicateVIN';
				$this->errors[] = $this->error;
				return -1;
			}
		}
		if (!empty($this->fk_soc_owner)) {
			$ownerExists = $this->linkedRecordExists('societe', (int) $this->fk_soc_owner, getEntity('societe'));
			if ($ownerExists < 0) {
				return -1;
			}
			if ($ownerExists === 0) {
				$this->error = 'InvalidOwnerThirdParty';
				$this->errors[] = $this->error;
				return -1;
			}
		}
		if (!empty($this->fk_resource)) {
			if (!isModEnabled('resource')) {
				$existingResourceId = is_object($this->oldcopy) && !empty($this->oldcopy->fk_resource) ? (int) $this->oldcopy->fk_resource : 0;
				if ($existingResourceId !== (int) $this->fk_resource) {
					$this->error = 'InvalidLinkedResource';
					$this->errors[] = $this->error;
					return -1;
				}
			} else {
				$resourceExists = $this->linkedRecordExists('resource', (int) $this->fk_resource, getEntity('resource'));
				if ($resourceExists < 0) {
					return -1;
				}
				if ($resourceExists === 0) {
					$this->error = 'InvalidLinkedResource';
					$this->errors[] = $this->error;
					return -1;
				}
			}
		}

		return 1;
	}

	/**
	 * Check a business identifier before relying on the unique SQL index.
	 *
	 * @param string $field Whitelisted column name
	 * @param string $value Normalized value
	 * @return int<-1,1> -1 on SQL error, 0 when free, 1 when already used
	 */
	private function duplicateVehicleFieldExists($field, $value)
	{
		if (!in_array($field, array('registration_number', 'vin'), true)) {
			$this->error = 'ErrorBadValueForParameter';
			$this->errors[] = $this->error;
			return -1;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE entity = '.((int) $this->entity);
		$sql .= ' AND '.$field." = '".$this->db->escape($value)."'";
		if (!empty($this->id)) {
			$sql .= ' AND rowid <> '.((int) $this->id);
		}
		$sql .= ' LIMIT 1';
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

	/**
	 * Check an entity-scoped linked record.
	 *
	 * @param string $table Table without prefix
	 * @param int $id Row id
	 * @param string $entities Sanitized entity scope returned by getEntity()
	 * @return int<-1,1> -1 on SQL error, 0 when absent, 1 when present
	 */
	private function linkedRecordExists($table, $id, $entities)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$this->db->sanitize($table);
		$sql .= ' WHERE rowid = '.((int) $id).' AND entity IN ('.$entities.')';
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

	/** @inheritdoc */
	protected function getNextNumRef()
	{
		global $langs;

		$model = getDolGlobalString('LMDBVEHICLEMANAGEMENT_LMDBVEHICLE_ADDON', 'mod_lmdbvehicle_standard');
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
	protected function getNumberingLockScope()
	{
		return $this->TRIGGER_PREFIX.':'.getEntity('lmdbvehiclenumber', 1, $this);
	}

	/** @inheritdoc */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;
		$labels = array(
			self::STATUS_DRAFT => 'VehicleStatusDraft',
			self::STATUS_VALIDATED => 'VehicleStatusValidated',
			self::STATUS_IN_SERVICE => 'VehicleStatusInService',
			self::STATUS_OUT_OF_SERVICE => 'VehicleStatusOutOfService',
			self::STATUS_SOLD => 'VehicleStatusSold',
		);
		$types = array(
			self::STATUS_DRAFT => 'status0',
			self::STATUS_VALIDATED => 'status1',
			self::STATUS_IN_SERVICE => 'status4',
			self::STATUS_OUT_OF_SERVICE => 'status3',
			self::STATUS_SOLD => 'status6',
		);
		$label = isset($labels[$status]) ? $langs->trans($labels[$status]) : (string) $status;

		return dolGetStatus($label, $label, '', isset($types[$status]) ? $types[$status] : 'status0', $mode);
	}

	/** @inheritdoc */
	protected function getCardPage()
	{
		return 'vehicle_card.php';
	}
}
