<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementobject.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementrules.class.php');

/**
 * Insurance contract shared by one or more vehicles.
 */
class LmdbVehicleInsuranceContract extends LmdbVehicleManagementObject
{
	public const STATUS_DRAFT = 0;
	public const STATUS_ACTIVE = 1;
	public const STATUS_TERMINATED = 9;
	public const COVERAGE_PRIMARY = 'primary';
	public const COVERAGE_COMPLEMENTARY = 'complementary';

	/** @var string */
	public $element = 'lmdbinsurancecontract';
	/** @var string */
	public $table_element = 'lmdbvehiclemanagement_insurance_contract';
	/** @var string */
	public $TRIGGER_PREFIX = 'LMDBVEHICLEMANAGEMENT_INSURANCE_CONTRACT';
	/** @var string */
	public $entity_scope_element = 'lmdbvehicle';
	/** @var string */
	public $picto = 'shield-alt';
	/** @var int<0,1> */
	public $has_document_storage = 1;

	/** @var array<string,mixed> */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'position' => 10, 'notnull' => 1, 'visible' => 0, 'default' => 1, 'index' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php:1', 'label' => 'InsuranceCompany', 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_contact' => array('type' => 'integer:Contact:contact/class/contact.class.php:1', 'label' => 'InsuranceContact', 'position' => 40, 'notnull' => -1, 'visible' => 1, 'index' => 1),
		'policy_number' => array('type' => 'varchar(128)', 'label' => 'InsurancePolicyNumber', 'position' => 50, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'position' => 60, 'notnull' => 1, 'visible' => 1),
		'coverage_formula' => array('type' => 'varchar(255)', 'label' => 'InsuranceCoverageFormula', 'position' => 70, 'notnull' => -1, 'visible' => 1),
		'date_start' => array('type' => 'date', 'label' => 'DateStart', 'position' => 80, 'notnull' => 1, 'visible' => 1),
		'date_end' => array('type' => 'date', 'label' => 'DateEnd', 'position' => 90, 'notnull' => -1, 'visible' => 1),
		'renewal_mode' => array('type' => 'varchar(32)', 'label' => 'InsuranceRenewalMode', 'position' => 100, 'notnull' => 1, 'visible' => 1, 'default' => 'fixed', 'arrayofkeyval' => array('fixed' => 'InsuranceRenewalFixed', 'tacit' => 'InsuranceRenewalTacit')),
		'notice_date' => array('type' => 'date', 'label' => 'InsuranceNoticeDate', 'position' => 110, 'notnull' => -1, 'visible' => 1),
		'assistance_phone' => array('type' => 'varchar(32)', 'label' => 'InsuranceAssistancePhone', 'position' => 120, 'notnull' => -1, 'visible' => 1),
		'assistance_email' => array('type' => 'varchar(255)', 'label' => 'InsuranceAssistanceEmail', 'position' => 130, 'notnull' => -1, 'visible' => 1),
		'claim_phone' => array('type' => 'varchar(32)', 'label' => 'InsuranceClaimPhone', 'position' => 140, 'notnull' => -1, 'visible' => 1),
		'claim_email' => array('type' => 'varchar(255)', 'label' => 'InsuranceClaimEmail', 'position' => 150, 'notnull' => -1, 'visible' => 1),
		'description' => array('type' => 'text', 'label' => 'Description', 'position' => 160, 'notnull' => -1, 'visible' => 3),
		'status' => array('type' => 'integer', 'label' => 'Status', 'position' => 200, 'notnull' => 1, 'visible' => 1, 'default' => 0, 'arrayofkeyval' => array(0 => 'InsuranceContractStatusDraft', 1 => 'InsuranceContractStatusActive', 9 => 'InsuranceContractStatusTerminated')),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'position' => 501, 'notnull' => 0, 'visible' => -2, 'noteditable' => 1),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'last_main_doc' => array('type' => 'varchar(255)', 'label' => 'LastMainDoc', 'position' => 1010, 'notnull' => -1, 'visible' => 0),
	);

	/** @var string */ public $ref = '';
	/** @var int */ public $fk_soc = 0;
	/** @var ?int */ public $fk_contact;
	/** @var string */ public $policy_number = '';
	/** @var string */ public $label = '';
	/** @var ?string */ public $coverage_formula;
	/** @var int */ public $date_start = 0;
	/** @var ?int */ public $date_end;
	/** @var string */ public $renewal_mode = 'fixed';
	/** @var ?int */ public $notice_date;
	/** @var ?string */ public $assistance_phone;
	/** @var ?string */ public $assistance_email;
	/** @var ?string */ public $claim_phone;
	/** @var ?string */ public $claim_email;
	/** @var ?string */ public $description;
	/** @var ?string */ public $last_main_doc;
	/** @var bool */ private $transitionInProgress = false;

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->status = self::STATUS_DRAFT;
	}

	/** @inheritdoc */
	public function create(User $user, $notrigger = 0)
	{
		$this->status = self::STATUS_DRAFT;

		return parent::create($user, $notrigger);
	}

	/** @inheritdoc */
	public function update(User $user, $notrigger = 0)
	{
		if (!$this->transitionInProgress && !empty($this->id)) {
			$current = new self($this->db);
			if ($current->fetch((int) $this->id) <= 0) {
				$this->error = 'RecordNotFound';
				return -1;
			}
			if ((int) $current->status !== (int) $this->status) {
				$this->error = 'InsuranceInvalidContractStatusTransition';
				$this->errors[] = $this->error;
				return 0;
			}
		}

		return parent::update($user, $notrigger);
	}

	/**
	 * Create or update the contract and its vehicle links atomically.
	 *
	 * @param list<int> $vehicleIds Vehicle ids
	 * @param string $coverageType Coverage type
	 * @param int $dateStart Coverage start
	 * @param ?int $dateEnd Coverage end
	 * @param User $user Author
	 * @return int<-1,max>
	 */
	public function saveWithVehicleLinks($vehicleIds, $coverageType, $dateStart, $dateEnd, User $user)
	{
		$isNew = empty($this->id);
		$createdId = 0;
		$triggerResult = 0;
		$triggerError = '';
		$triggerErrors = array();
		$vehicleIds = array_values(array_unique(array_filter(array_map('intval', $vehicleIds))));
		$lockIds = $vehicleIds;
		if (!$isNew) {
			$lockIds = array_values(array_unique(array_merge($lockIds, $this->getVehicleIds())));
		}
		sort($lockIds, SORT_NUMERIC);
		$this->db->begin();
		foreach ($lockIds as $vehicleId) {
			if ($this->lockVehicleRow($vehicleId) < 0) {
				$this->db->rollback();
				return -1;
			}
		}
		$result = $isNew ? $this->create($user, 1) : $this->update($user, 1);
		if ($isNew && $result > 0) {
			$createdId = (int) $this->id;
		}
		if ($result > 0) {
			$result = $this->replaceVehicleLinks($vehicleIds, $coverageType, $dateStart, $dateEnd, $user, 1);
		}
		if ($result > 0) {
			$this->context['trigger_reason'] = $isNew ? 'create_with_coverage' : 'coverage_change';
			$this->context['changed_fields'] = $isNew ? array_keys($this->fields) : array('vehicle_links');
			$triggerResult = $this->call_trigger($this->TRIGGER_PREFIX.($isNew ? '_CREATE' : '_UPDATE'), $user);
			if ($triggerResult < 0) {
				$triggerError = (string) $this->error;
				$triggerErrors = is_array($this->errors) ? $this->errors : array();
				$result = -1;
			}
		}
		if ($result > 0) {
			$this->db->commit();
			return 1;
		}
		$this->db->rollback();
		if ($isNew && $createdId > 0) {
			$this->id = 0;
			$this->rowid = 0;
			$this->ref = '';
		}
		if ($triggerResult < 0) {
			$this->error = $triggerError;
			$this->errors = $triggerErrors;
		}

		return -1;
	}

	/**
	 * Replace contract vehicle coverage links.
	 *
	 * The caller owns the surrounding transaction.
	 *
	 * @param list<int> $vehicleIds Vehicle ids
	 * @param string $coverageType primary or complementary
	 * @param int $dateStart Coverage start
	 * @param ?int $dateEnd Coverage end
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable trigger
	 * @return int<-1,1>
	 */
	public function replaceVehicleLinks($vehicleIds, $coverageType, $dateStart, $dateEnd, User $user, $notrigger = 0)
	{
		$vehicleIds = array_values(array_unique(array_filter(array_map('intval', $vehicleIds))));
		$outsideContractPeriod = $dateStart < (int) $this->date_start
			|| ($dateEnd !== null && $dateEnd < $dateStart)
			|| (!empty($this->date_end) && ($dateEnd === null || $dateEnd > (int) $this->date_end));
		if (empty($vehicleIds) || !in_array($coverageType, array(self::COVERAGE_PRIMARY, self::COVERAGE_COMPLEMENTARY), true) || $dateStart <= 0 || $outsideContractPeriod) {
			$this->error = 'InsuranceCoverageInvalid';
			$this->errors[] = $this->error;
			return -1;
		}

		foreach ($vehicleIds as $vehicleId) {
			if ($this->vehicleBelongsToContractEntity($vehicleId) <= 0) {
				$this->error = 'InvalidVehicle';
				$this->errors[] = $this->error;
				return -1;
			}
			if ($coverageType === self::COVERAGE_PRIMARY && (int) $this->status === self::STATUS_ACTIVE && $this->hasPrimaryOverlap($vehicleId, $dateStart, $dateEnd) !== 0) {
				if ($this->error === '') {
					$this->error = 'InsurancePrimaryCoverageOverlap';
					$this->errors[] = $this->error;
				}
				return -1;
			}
		}

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle';
		$sql .= ' WHERE fk_contract = '.((int) $this->id).' AND entity = '.((int) $this->entity);
		$sql .= ' AND fk_vehicle NOT IN ('.implode(',', $vehicleIds).')';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		foreach ($vehicleIds as $vehicleId) {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle';
			$sql .= ' (entity, fk_contract, fk_vehicle, coverage_type, date_start, date_end, date_creation, fk_user_creat, fk_user_modif) VALUES (';
			$sql .= ((int) $this->entity).', '.((int) $this->id).', '.((int) $vehicleId).", '".$this->db->escape($coverageType)."', '".$this->db->idate($dateStart)."', ";
			$sql .= $dateEnd !== null ? "'".$this->db->idate($dateEnd)."', " : 'NULL, ';
			$sql .= "'".$this->db->idate(dol_now())."', ".((int) $user->id).', '.((int) $user->id).')';
			$sql .= ' ON DUPLICATE KEY UPDATE coverage_type = VALUES(coverage_type), date_start = VALUES(date_start), date_end = VALUES(date_end), fk_user_modif = VALUES(fk_user_modif)';
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		$this->context['trigger_reason'] = 'coverage_change';
		$this->context['changed_fields'] = array('vehicle_links');
		if (!$notrigger && $this->call_trigger($this->TRIGGER_PREFIX.'_UPDATE', $user) < 0) {
			return -1;
		}

		return 1;
	}

	/**
	 * Activate the contract after checking every primary coverage.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable trigger
	 * @return int<-1,max>
	 */
	public function activate(User $user, $notrigger = 0)
	{
		return $this->changeStatus(self::STATUS_ACTIVE, $user, $notrigger);
	}

	/**
	 * Terminate an active contract.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable trigger
	 * @return int<-1,max>
	 */
	public function terminate(User $user, $notrigger = 0)
	{
		return $this->changeStatus(self::STATUS_TERMINATED, $user, $notrigger);
	}

	/**
	 * Return the active primary contract for a vehicle.
	 *
	 * @param DoliDB $db Database handler
	 * @param int $vehicleId Vehicle id
	 * @param int $at Timestamp
	 * @return LmdbVehicleInsuranceContract|null
	 */
	public static function getPrimaryForVehicle($db, $vehicleId, $at = 0)
	{
		$at = $at > 0 ? $at : dol_now();
		$date = $db->idate($at);
		$sql = 'SELECT c.rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract AS c';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle AS cv ON cv.fk_contract = c.rowid AND cv.entity = c.entity';
		$sql .= ' WHERE cv.fk_vehicle = '.((int) $vehicleId)." AND cv.coverage_type = 'primary'";
		$sql .= ' AND c.status = '.self::STATUS_ACTIVE;
		$sql .= " AND c.date_start <= '".$date."' AND (c.date_end IS NULL OR c.date_end >= '".$date."')";
		$sql .= " AND cv.date_start <= '".$date."' AND (cv.date_end IS NULL OR cv.date_end >= '".$date."')";
		$sql .= " AND c.entity IN (".getEntity('lmdbvehicle').') ORDER BY cv.date_start DESC, c.rowid DESC LIMIT 1';
		$resql = $db->query($sql);
		if (!$resql) {
			return null;
		}
		$row = $db->fetch_object($resql);
		$db->free($resql);
		if (!is_object($row)) {
			return null;
		}
		$contract = new self($db);

		return $contract->fetch((int) $row->rowid) > 0 ? $contract : null;
	}

	/**
	 * Return contracts linked to a vehicle.
	 *
	 * @param DoliDB $db Database handler
	 * @param int $vehicleId Vehicle id
	 * @return array<int,array{contract:LmdbVehicleInsuranceContract,coverage_type:string,date_start:int,date_end:?int}>
	 */
	public static function getForVehicle($db, $vehicleId)
	{
		$sql = 'SELECT c.rowid, cv.coverage_type, cv.date_start, cv.date_end FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract AS c';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle AS cv ON cv.fk_contract = c.rowid AND cv.entity = c.entity';
		$sql .= ' WHERE cv.fk_vehicle = '.((int) $vehicleId).' AND c.entity IN ('.getEntity('lmdbvehicle').')';
		$sql .= ' ORDER BY (cv.coverage_type = \'primary\') DESC, c.status ASC, cv.date_start DESC';
		$resql = $db->query($sql);
		if (!$resql) {
			return array();
		}
		$result = array();
		while (is_object($row = $db->fetch_object($resql))) {
			$contract = new self($db);
			if ($contract->fetch((int) $row->rowid) > 0) {
				$result[] = array(
					'contract' => $contract,
					'coverage_type' => (string) $row->coverage_type,
					'date_start' => (int) $db->jdate($row->date_start),
					'date_end' => !empty($row->date_end) ? (int) $db->jdate($row->date_end) : null,
				);
			}
		}
		$db->free($resql);

		return $result;
	}

	/**
	 * Return linked vehicle ids.
	 *
	 * @return list<int>
	 */
	public function getVehicleIds()
	{
		$sql = 'SELECT fk_vehicle FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle';
		$sql .= ' WHERE fk_contract = '.((int) $this->id).' AND entity = '.((int) $this->entity).' ORDER BY fk_vehicle';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return array();
		}
		$ids = array();
		while (is_object($row = $this->db->fetch_object($resql))) {
			$ids[] = (int) $row->fk_vehicle;
		}
		$this->db->free($resql);

		return $ids;
	}

	/** @inheritdoc */
	public function delete(User $user, $notrigger = 0)
	{
		if ((int) $this->status !== self::STATUS_DRAFT) {
			$this->error = 'InsuranceOnlyDraftCanBeDeleted';
			$this->errors[] = $this->error;
			return 0;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_certificate';
		$sql .= ' WHERE fk_contract = '.((int) $this->id).' AND entity = '.((int) $this->entity).' LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$hasCertificate = $this->db->num_rows($resql) > 0;
		$this->db->free($resql);
		if ($hasCertificate) {
			$this->error = 'InsuranceContractHasCertificates';
			$this->errors[] = $this->error;
			return 0;
		}
		$this->db->begin();
		$this->context['trigger_reason'] = 'delete';
		if (!$notrigger && $this->call_trigger($this->TRIGGER_PREFIX.'_DELETE', $user) < 0) {
			$this->db->rollback();
			return -1;
		}
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle';
		$sql .= ' WHERE fk_contract = '.((int) $this->id).' AND entity = '.((int) $this->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$result = parent::delete($user, 1);
		if ($result <= 0) {
			$this->db->rollback();
			return $result;
		}
		$this->db->commit();

		return 1;
	}

	/** @inheritdoc */
	protected function validateBusinessRules()
	{
		$this->policy_number = trim($this->policy_number);
		$this->label = trim($this->label);
		if ($this->fk_soc <= 0 || $this->policy_number === '' || $this->label === '' || $this->date_start <= 0 || (!empty($this->date_end) && $this->date_end < $this->date_start)) {
			$this->error = 'InsuranceContractRequiredFields';
			$this->errors[] = $this->error;
			return -1;
		}
		if (!in_array($this->renewal_mode, array('fixed', 'tacit'), true) || !in_array((int) $this->status, array(self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_TERMINATED), true)) {
			$this->error = 'ErrorBadValueForParameter';
			$this->errors[] = $this->error;
			return -1;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE rowid = '.((int) $this->fk_soc).' AND entity IN ('.getEntity('societe').')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$exists = $this->db->num_rows($resql) > 0;
		$this->db->free($resql);
		if (!$exists) {
			$this->error = 'InsuranceCompanyInvalid';
			$this->errors[] = $this->error;
			return -1;
		}
		if (!empty($this->fk_contact)) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'socpeople';
			$sql .= ' WHERE rowid = '.((int) $this->fk_contact).' AND fk_soc = '.((int) $this->fk_soc);
			$sql .= ' AND entity IN ('.getEntity('contact').')';
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$contactExists = $this->db->num_rows($resql) > 0;
			$this->db->free($resql);
			if (!$contactExists) {
				$this->error = 'InsuranceContactInvalid';
				$this->errors[] = $this->error;
				return -1;
			}
		}

		return 1;
	}

	/** @inheritdoc */
	protected function getNextNumRef()
	{
		$model = getDolGlobalString('LMDBVEHICLEMANAGEMENT_INSURANCECONTRACT_ADDON', 'mod_lmdbinsurancecontract_standard');
		$file = dol_buildpath('/lmdbvehiclemanagement/core/modules/lmdbvehiclemanagement/'.$model.'.php', 0);
		if (!is_readable($file)) {
			$this->error = 'ErrorNumberingModuleNotFound';
			return -1;
		}
		require_once $file;
		if (!class_exists($model)) {
			$this->error = 'ErrorNumberingModuleNotFound';
			return -1;
		}
		$numbering = new $model();
		$result = $numbering->getNextValue($this);
		if (!is_string($result) || $result === '') {
			$this->error = $numbering->error !== '' ? $numbering->error : 'ErrorGeneratingNumber';
			return -1;
		}

		return $result;
	}

	/** @inheritdoc */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;

		$labels = array(self::STATUS_DRAFT => 'InsuranceContractStatusDraft', self::STATUS_ACTIVE => 'InsuranceContractStatusActive', self::STATUS_TERMINATED => 'InsuranceContractStatusTerminated');
		$classes = array(self::STATUS_DRAFT => 'status0', self::STATUS_ACTIVE => 'status4', self::STATUS_TERMINATED => 'status6');
		$label = isset($labels[$status]) ? $langs->trans($labels[$status]) : (string) $status;

		return dolGetStatus($label, '', '', isset($classes[$status]) ? $classes[$status] : 'status0', $mode);
	}

	/** @inheritdoc */
	protected function getCardPage()
	{
		return 'insurancecontract_card.php';
	}

	/** @inheritdoc */
	protected function getCardUrlParameters()
	{
		return 'id='.((int) $this->id);
	}

	/**
	 * Apply one lifecycle transition.
	 *
	 * @param int $targetStatus Target status
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable trigger
	 * @return int<-1,max>
	 */
	private function changeStatus($targetStatus, User $user, $notrigger)
	{
		$current = new self($this->db);
		if ($current->fetch((int) $this->id) <= 0) {
			$this->error = 'RecordNotFound';
			return -1;
		}
		if (!LmdbVehicleManagementRules::insuranceContractStatusTransitionIsAllowed((int) $current->status, $targetStatus)) {
			$this->error = 'InsuranceInvalidContractStatusTransition';
			$this->errors[] = $this->error;
			return 0;
		}
		$this->entity = (int) $current->entity;
		$this->db->begin();
		if ($targetStatus === self::STATUS_ACTIVE) {
			$sql = 'SELECT fk_vehicle, coverage_type, date_start, date_end FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle';
			$sql .= ' WHERE fk_contract = '.((int) $this->id).' AND entity = '.((int) $this->entity).' ORDER BY fk_vehicle';
			$resql = $this->db->query($sql);
			if (!$resql || $this->db->num_rows($resql) === 0) {
				$this->error = $resql ? 'InsuranceContractRequiresVehicle' : $this->db->lasterror();
				if ($resql) {
					$this->db->free($resql);
				}
				$this->db->rollback();
				return -1;
			}
			$coverageRows = array();
			while (is_object($row = $this->db->fetch_object($resql))) {
				$coverageRows[] = $row;
			}
			$this->db->free($resql);
			foreach ($coverageRows as $row) {
				if ($this->lockVehicleRow((int) $row->fk_vehicle) < 0) {
					$this->db->rollback();
					return -1;
				}
				if ((string) $row->coverage_type === self::COVERAGE_PRIMARY && $this->hasPrimaryOverlap((int) $row->fk_vehicle, (int) $this->db->jdate($row->date_start), !empty($row->date_end) ? (int) $this->db->jdate($row->date_end) : null) !== 0) {
					$this->error = $this->error !== '' ? $this->error : 'InsurancePrimaryCoverageOverlap';
					$this->errors[] = $this->error;
					$this->db->rollback();
					return -1;
				}
			}
		}
		$this->status = $targetStatus;
		$this->context['trigger_reason'] = 'status_change';
		$this->context['old_status'] = (int) $current->status;
		$this->context['new_status'] = $targetStatus;
		$this->transitionInProgress = true;
		$result = parent::update($user, $notrigger);
		$this->transitionInProgress = false;
		if ($result > 0) {
			$this->db->commit();
		} else {
			$this->db->rollback();
		}

		return $result;
	}

	/** @return int<-1,1> */
	private function vehicleBelongsToContractEntity($vehicleId)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle';
		$sql .= ' WHERE rowid = '.((int) $vehicleId).' AND entity = '.((int) $this->entity).' AND entity IN ('.getEntity('lmdbvehicle').')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$exists = $this->db->num_rows($resql) > 0;
		$this->db->free($resql);

		return $exists ? 1 : 0;
	}

	/** @return int<-1,max> */
	private function hasPrimaryOverlap($vehicleId, $dateStart, $dateEnd)
	{
		$sql = 'SELECT cv.rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle AS cv';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract AS c ON c.rowid = cv.fk_contract AND c.entity = cv.entity';
		$sql .= ' WHERE cv.entity = '.((int) $this->entity).' AND cv.fk_vehicle = '.((int) $vehicleId);
		$sql .= " AND cv.coverage_type = 'primary' AND c.status = ".self::STATUS_ACTIVE;
		$sql .= ' AND cv.fk_contract <> '.((int) $this->id);
		$sql .= " AND cv.date_start <= '".$this->db->idate($dateEnd !== null ? $dateEnd : 253402214399)."'";
		$sql .= " AND (cv.date_end IS NULL OR cv.date_end >= '".$this->db->idate($dateStart)."') LIMIT 1";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$count = $this->db->num_rows($resql);
		$this->db->free($resql);

		return $count;
	}
}
