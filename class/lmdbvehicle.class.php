<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementobject.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementrules.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleenergy.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclereferencemigration.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleregulatoryservice.class.php');

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

	/** @var string Avoid the native tooltip mistaking the vehicle write namespace for a read namespace */
	public $ajax_tooltip_element = 'lmdbvehicleajaxtooltip';

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
		'registration_number' => array('type' => 'varchar(32)', 'label' => 'RegistrationNumber', 'position' => 30, 'notnull' => -1, 'visible' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 2),
		'vin' => array('type' => 'varchar(64)', 'label' => 'VIN', 'position' => 40, 'notnull' => -1, 'visible' => 1, 'index' => 1, 'searchall' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'position' => 50, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'fk_asset_type' => array('type' => 'integer', 'label' => 'AssetType', 'position' => 51, 'notnull' => -1, 'visible' => 1, 'index' => 1),
		'eu_category' => array('type' => 'varchar(16)', 'label' => 'EuropeanCategory', 'position' => 52, 'notnull' => -1, 'visible' => -1),
		'national_genre' => array('type' => 'varchar(32)', 'label' => 'NationalGenre', 'position' => 53, 'notnull' => -1, 'visible' => -1),
		'gvw_kg' => array('type' => 'double(24,8)', 'label' => 'GrossVehicleWeight', 'position' => 54, 'notnull' => -1, 'visible' => -1),
		'gcw_kg' => array('type' => 'double(24,8)', 'label' => 'GrossCombinationWeight', 'position' => 55, 'notnull' => -1, 'visible' => -1),
		'seats' => array('type' => 'integer', 'label' => 'NumberOfSeats', 'position' => 56, 'notnull' => -1, 'visible' => -1),
		'regulatory_territory' => array('type' => 'varchar(32)', 'label' => 'RegulatoryTerritory', 'position' => 57, 'notnull' => 1, 'visible' => -1, 'default' => 'FR_METRO', 'arrayofkeyval' => array('FR_METRO' => 'TerritoryMetropolitanFrance', 'FR_GUADELOUPE' => 'TerritoryGuadeloupe', 'FR_MARTINIQUE' => 'TerritoryMartinique', 'FR_GUYANE' => 'TerritoryFrenchGuiana', 'FR_REUNION' => 'TerritoryReunion', 'FR_MAYOTTE' => 'TerritoryMayotte', 'FR_OTHER_OVERSEAS' => 'TerritoryOtherOverseas')),
		'brand' => array('type' => 'varchar(128)', 'label' => 'Brand', 'position' => 60, 'notnull' => -1, 'visible' => 1, 'searchall' => 1),
		'model' => array('type' => 'varchar(128)', 'label' => 'VehicleModel', 'position' => 70, 'notnull' => -1, 'visible' => 1, 'searchall' => 1),
		'vehicle_version' => array('type' => 'varchar(128)', 'label' => 'VehicleVersion', 'position' => 80, 'notnull' => -1, 'visible' => -1),
		'fk_energy' => array('type' => 'integer:LmdbVehicleEnergy:lmdbvehiclemanagement/class/lmdbvehicleenergy.class.php', 'label' => 'Energy', 'position' => 90, 'notnull' => -1, 'visible' => -1, 'index' => 1),
		'wltp_range_km' => array('type' => 'double(24,8)', 'label' => 'WltpRangeKm', 'position' => 95, 'notnull' => -1, 'visible' => -1),
		'construction_date' => array('type' => 'date', 'label' => 'ConstructionDate', 'position' => 97, 'notnull' => -1, 'visible' => -1),
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
	/** @var ?string */
	public $registration_number;
	/** @var ?string */
	public $vin;
	/** @var string */
	public $label = '';
	/** @var ?int */ public $fk_asset_type;
	/** @var ?string */ public $eu_category;
	/** @var ?string */ public $national_genre;
	/** @var ?float */ public $gvw_kg;
	/** @var ?float */ public $gcw_kg;
	/** @var ?int */ public $seats;
	/** @var string */ public $regulatory_territory = 'FR_METRO';
	/** @var ?string Resolved dictionary code, not persisted */ public $asset_type;
	/** @var ?string */
	public $brand;
	/** @var ?string */
	public $model;
	/** @var ?string */
	public $vehicle_version;
	/** @var ?int */
	public $fk_energy;
	/** @var ?float */
	public $wltp_range_km;
	/** @var ?int */ public $construction_date;
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
		if (self::usesRegistrationAsReference()) {
			$this->ref = self::normalizeRegistrationNumber((string) $this->registration_number);
		}
		$this->db->begin();
		$result = parent::create($user, $notrigger);
		if ($result > 0) {
			$service = new LmdbVehicleRegulatoryService($this->db);
			if ($service->initializeSuggestedProfiles($this, $user) < 0) {
				$this->error = $service->error;
				$this->errors = $service->errors;
				$this->db->rollback();
				return -1;
			}
		}
		if ($result <= 0) {
			$this->db->rollback();
			return $result;
		}
		$this->db->commit();

		return $result;
	}

	/** @inheritdoc */
	public function fetch($id, $ref = null)
	{
		$result = parent::fetch($id, $ref);
		if ($result > 0 && !empty($this->fk_asset_type)) {
			$resql = $this->db->query('SELECT code FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_asset_type WHERE rowid = '.((int) $this->fk_asset_type).' AND entity IN ('.getEntity('c_lmdbvehiclemanagement_asset_type').')');
			if ($resql && is_object($row = $this->db->fetch_object($resql))) $this->asset_type = (string) $row->code;
			if ($resql) $this->db->free($resql);
		}
		return $result;
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
		$current = null;
		$numberingLock = '';
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
			$this->entity = (int) $current->entity;
		}
		if (self::usesRegistrationAsReference()) {
			$this->registration_number = self::normalizeRegistrationNumber((string) $this->registration_number) ?: null;
			if (!empty($this->registration_number)) {
				$this->ref = (string) $this->registration_number;
			} elseif ($current instanceof self && strpos((string) $current->ref, 'MAT') !== 0) {
				$this->ref = '';
				$numberingLock = 'lmdbvm_num_'.sha1($this->getNumberingLockScope());
				if ($this->acquireNumberingLock($numberingLock) < 0) return -1;
				$nextRef = $this->getNextNumRef();
				if (!is_string($nextRef) || $nextRef === '') { $this->releaseNumberingLock($numberingLock); return -1; }
				$this->ref = $nextRef;
			}
		}
		if ($current instanceof self && (string) $current->ref !== (string) $this->ref) {
			$this->entity = (int) $current->entity;
			if ($this->validateBusinessRules() < 0) {
				if ($numberingLock !== '') $this->releaseNumberingLock($numberingLock);
				return -1;
			}
			$migration = new LmdbVehicleReferenceMigration($this->db);
			$this->last_main_doc = $migration->replaceReferenceInPath((string) $current->last_main_doc, (string) $current->ref, (string) $this->ref);
			$this->context['trigger_reason'] = 'reference_sync';
			$this->context['old_ref'] = (string) $current->ref;
			$this->context['new_ref'] = (string) $this->ref;
			$this->db->begin();
			if ($migration->relocateUpdatedVehicle($current, $this) < 0) {
				$this->db->rollback();
				if ($numberingLock !== '') $this->releaseNumberingLock($numberingLock);
				$this->error = $migration->error;
				$this->errors = $migration->errors;
				return -1;
			}
			$result = parent::update($user, $notrigger);
			$service = new LmdbVehicleRegulatoryService($this->db);
			if ($result <= 0 || $migration->updateEcmIndex($this, (string) $current->ref, (string) $this->ref) < 0 || $service->refreshSuggestedProfiles($this, $user) < 0) {
				$this->db->rollback();
				$migration->rollbackFilesystem();
				if ($numberingLock !== '') $this->releaseNumberingLock($numberingLock);
				if ($result > 0) {
					$this->error = $service->error !== '' ? $service->error : $migration->error;
					$this->errors = !empty($service->errors) ? $service->errors : $migration->errors;
				}
				return -1;
			}
			$this->db->commit();
			$migration->commitFilesystem();
			if ($numberingLock !== '') $this->releaseNumberingLock($numberingLock);

			return $result;
		}

		$this->db->begin();
		$result = parent::update($user, $notrigger);
		if ($result > 0) {
			$service = new LmdbVehicleRegulatoryService($this->db);
			if ($service->refreshSuggestedProfiles($this, $user) < 0) {
				$this->error = $service->error;
				$this->errors = $service->errors;
				$this->db->rollback();
				return -1;
			}
		}
		if ($result <= 0) {
			$this->db->rollback();
			return $result;
		}
		$this->db->commit();

		return $result;
	}

	/** @return bool Whether the active model derives the reference from registration */
	public static function usesRegistrationAsReference()
	{
		return getDolGlobalString('LMDBVEHICLEMANAGEMENT_LMDBVEHICLE_ADDON', 'mod_lmdbvehicle_standard') === 'mod_lmdbvehicle_registration';
	}

	/** @param string $registration Registration value @return string Normalized value */
	public static function normalizeRegistrationNumber($registration)
	{
		return strtoupper(trim($registration));
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
			'lmdbvehiclemanagement_consumption',
			'lmdbvehiclemanagement_vehicle_capacity',
			'lmdbvehiclemanagement_vehicle_event',
			'lmdbvehiclemanagement_insurance_contract_vehicle',
			'lmdbvehiclemanagement_insurance_certificate',
			'lmdbvehiclemanagement_vehicle_regulatory_profile',
			'lmdbvehiclemanagement_control_requirement',
			'lmdbvehiclemanagement_regulatory_control',
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
		$regulatory = new LmdbVehicleRegulatoryService($this->db);
		$allowed = $regulatory->vehicleActionIsAllowed((int) $this->id, 'service');
		if ($allowed <= 0) { $this->error = $regulatory->error; $this->errors = $regulatory->errors; return $allowed < 0 ? -1 : 0; }
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

		$this->registration_number = self::normalizeRegistrationNumber((string) $this->registration_number) ?: null;
		$this->vin = trim((string) $this->vin) !== '' ? strtoupper(trim((string) $this->vin)) : null;
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
		if (!empty($this->fk_asset_type)) {
			$assetTypeExists = $this->linkedRecordExists('c_lmdbvehiclemanagement_asset_type', (int) $this->fk_asset_type, getEntity('c_lmdbvehiclemanagement_asset_type'));
			if ($assetTypeExists <= 0) return $this->businessRuleError($assetTypeExists < 0 ? $this->error : 'InvalidAssetType');
		}
		if ($this->wltp_range_km !== null && (float) $this->wltp_range_km < 0) {
			$this->error = 'WltpRangeMustBePositive';
			$this->errors[] = $this->error;
			return -1;
		}
		if ($this->registration_number !== null) {
			$duplicateRegistration = $this->duplicateVehicleFieldExists('registration_number', $this->registration_number);
			if ($duplicateRegistration < 0) return -1;
			if ($duplicateRegistration > 0) { $this->error = 'DuplicateRegistrationNumber'; $this->errors[] = $this->error; return -1; }
		}
		if (empty($this->regulatory_territory)) $this->regulatory_territory = 'FR_METRO';
		if (!in_array($this->regulatory_territory, array_keys($this->fields['regulatory_territory']['arrayofkeyval']), true)) return $this->businessRuleError('InvalidRegulatoryTerritory');
		foreach (array('gvw_kg', 'gcw_kg') as $weightField) if ($this->{$weightField} !== null && (float) $this->{$weightField} < 0) return $this->businessRuleError('WeightMustBePositive');
		if ($this->seats !== null && (int) $this->seats < 0) return $this->businessRuleError('SeatsMustBePositive');
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

	/** Return selected regulatory profile ids. @return list<int> */
	public function fetchRegulatoryProfileIds()
	{
		$ids = array();
		if (empty($this->id)) return $ids;
		$resql = $this->db->query('SELECT fk_profile FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile WHERE entity = '.((int) $this->entity).' AND fk_vehicle = '.((int) $this->id).' ORDER BY fk_profile');
		if (!$resql) return $ids;
		while (is_object($row = $this->db->fetch_object($resql))) $ids[] = (int) $row->fk_profile;
		$this->db->free($resql);
		return $ids;
	}

	/** Persist confirmed regulatory profiles and refresh materialized requirements. @param list<int> $profileIds Profiles @param User $user Author @return int<-1,1> */
	public function saveRegulatoryProfiles($profileIds, User $user)
	{
		$service = new LmdbVehicleRegulatoryService($this->db);
		$result = $service->saveVehicleProfiles($this, $profileIds, $user);
		if ($result < 0) { $this->error = $service->error; $this->errors = $service->errors; }
		return $result;
	}

	/** Grant a temporary, justified derogation without marking the equipment compliant. @param int $requirementId Requirement @param int $until Expiry @param string $reason Reason @param User $user Author @return int<-1,1> */
	public function grantRegulatoryDerogation($requirementId, $until, $reason, User $user)
	{
		if ($until <= dol_now() || trim($reason) === '') return $this->businessRuleError('RegulatoryDerogationReasonAndDateRequired');
		$this->db->begin();
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement SET derogation_until = \''.$this->db->idate($until).'\', derogation_reason = \''.$this->db->escape(trim($reason)).'\', fk_user_derogation = '.((int) $user->id).', date_derogation = \''.$this->db->idate(dol_now()).'\'';
		$sql .= ' WHERE rowid = '.((int) $requirementId).' AND entity = '.((int) $this->entity).' AND fk_vehicle = '.((int) $this->id).' AND active = 1';
		$resql = $this->db->query($sql);
		if (!$resql || $this->db->affected_rows($resql) <= 0) { $this->error = $this->db->lasterror() ?: 'InvalidRegulatoryRequirement'; $this->db->rollback(); return -1; }
		$service = new LmdbVehicleRegulatoryService($this->db);
		if ($service->recalculateVehicle((int) $this->id, (int) $this->entity) < 0) {
			$this->error = $service->error;
			$this->errors = $service->errors;
			$this->db->rollback();
			return -1;
		}
		$this->context['trigger_reason'] = 'regulatory_derogation';
		$this->context['changed_fields'] = array('regulatory_derogation');
		$result = parent::update($user);
		if ($result <= 0) { $this->db->rollback(); return $result; }
		$this->db->commit();
		return 1;
	}

	/** @param string $error Error key @return int<-1,-1> */
	private function businessRuleError($error)
	{
		$this->error = $error;
		$this->errors[] = $error;
		return -1;
	}

	/** @return array<int,float> Consumable id to capacity */
	public function fetchCapacities()
	{
		$capacities = array();
		if (empty($this->id)) {
			return $capacities;
		}
		$sql = 'SELECT fk_consumable, capacity FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_capacity';
		$sql .= ' WHERE entity = '.((int) $this->entity).' AND fk_vehicle = '.((int) $this->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $capacities;
		}
		while (is_object($row = $this->db->fetch_object($resql))) {
			$capacities[(int) $row->fk_consumable] = (float) $row->capacity;
		}
		$this->db->free($resql);
		return $capacities;
	}

	/**
	 * Persist configured capacities for this vehicle.
	 *
	 * @param User $user Author
	 * @param array<int,float|null> $capacities Consumable id to capacity, null removes it
	 * @return int<-1,1>
	 */
	public function saveCapacities(User $user, $capacities)
	{
		if (empty($this->id) || empty($this->entity)) {
			$this->error = 'RecordNotFound';
			return -1;
		}
		$this->db->begin();
		if (empty($this->fk_energy)) {
			$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_capacity';
			$sql .= ' WHERE entity = '.((int) $this->entity).' AND fk_vehicle = '.((int) $this->id);
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
			$this->db->commit();
			return 1;
		}
		$sql = 'DELETE cap FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_capacity AS cap';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumable_energy AS ce';
		$sql .= ' ON ce.fk_consumable = cap.fk_consumable AND ce.fk_energy = '.((int) $this->fk_energy);
		$sql .= ' AND ce.entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').')';
		$sql .= ' WHERE cap.entity = '.((int) $this->entity).' AND cap.fk_vehicle = '.((int) $this->id).' AND ce.rowid IS NULL';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		foreach ($capacities as $consumableId => $capacity) {
			if ($capacity !== null && $capacity < 0) {
				$this->error = 'ConsumableCapacityMustBePositive';
				$this->errors[] = $this->error;
				$this->db->rollback();
				return -1;
			}
			$sql = 'SELECT c.rowid FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable AS c';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumable_energy AS ce ON ce.fk_consumable = c.rowid AND ce.entity = c.entity';
			$sql .= ' WHERE c.rowid = '.((int) $consumableId).' AND c.entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').')';
			$sql .= ' AND ce.entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').') AND ce.fk_energy = '.((int) $this->fk_energy);
			$resql = $this->db->query($sql);
			if (!$resql || $this->db->num_rows($resql) !== 1) {
				if ($resql) $this->db->free($resql);
				$this->error = $resql ? 'InvalidConsumable' : $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
			$this->db->free($resql);
			if ($capacity === null || $capacity == 0) {
				$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_capacity';
				$sql .= ' WHERE entity = '.((int) $this->entity).' AND fk_vehicle = '.((int) $this->id).' AND fk_consumable = '.((int) $consumableId);
			} else {
				$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_capacity';
				$sql .= ' (entity, fk_vehicle, fk_consumable, capacity, date_creation, fk_user_creat, fk_user_modif) VALUES (';
				$sql .= ((int) $this->entity).', '.((int) $this->id).', '.((int) $consumableId).', '.((float) $capacity).", '".$this->db->idate(dol_now())."', ".((int) $user->id).', '.((int) $user->id).')';
				$sql .= ' ON DUPLICATE KEY UPDATE capacity = VALUES(capacity), fk_user_modif = VALUES(fk_user_modif)';
			}
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
		}
		$this->db->commit();
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

/**
 * Read-only identity used by the native Ajax tooltip.
 *
 * Dolibarr probes a second-level permission whenever an object's element matches
 * an existing permission namespace. Vehicles have write/delete permissions below
 * `lmdbvehicle`, while their read permission deliberately remains at module level.
 * This alias keeps the native module read check and the vehicle Multicompany scope.
 */
class LmdbVehicleAjaxTooltip extends LmdbVehicle
{
	/** @var string */
	public $element = 'lmdbvehicleajaxtooltip';

	/** @var string */
	public $entity_scope_element = 'lmdbvehicle';
}
