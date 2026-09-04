<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementobject.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleodometerreading.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumable.class.php');

/** Fuel, recharge or additive recorded for one vehicle. */
class LmdbVehicleConsumption extends LmdbVehicleManagementObject
{
	public const STATUS_RECORDED = 1;

	/** @var string */
	public $element = 'lmdbvehicleconsumption';
	/** @var string */
	public $table_element = 'lmdbvehiclemanagement_consumption';
	/** @var string */
	public $TRIGGER_PREFIX = 'LMDBVEHICLEMANAGEMENT_CONSUMPTION';
	/** @var string */
	public $entity_scope_element = 'lmdbvehicleconsumption';
	/** @var string */
	public $picto = 'gas-pump';
	/** @var int<0,1> */
	public $has_document_storage = 1;

	/** @var array<string,mixed> */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'position' => 5, 'notnull' => 1, 'visible' => 0, 'default' => 1, 'index' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'searchall' => 1),
		'fk_vehicle' => array('type' => 'integer:LmdbVehicle:lmdbvehiclemanagement/class/lmdbvehicle.class.php:0', 'label' => 'Vehicle', 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_consumable' => array('type' => 'integer:LmdbVehicleConsumable:lmdbvehiclemanagement/class/lmdbvehicleconsumable.class.php', 'label' => 'Consumable', 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'category_snapshot' => array('type' => 'varchar(16)', 'label' => 'ConsumptionNature', 'position' => 40, 'notnull' => 1, 'visible' => 1, 'arrayofkeyval' => array('fuel' => 'FuelOrRecharge', 'additive' => 'Additive')),
		'unit_snapshot' => array('type' => 'varchar(16)', 'label' => 'Unit', 'position' => 50, 'notnull' => 1, 'visible' => 1),
		'fk_odometer_reading' => array('type' => 'integer:LmdbVehicleOdometerReading:lmdbvehiclemanagement/class/lmdbvehicleodometerreading.class.php', 'label' => 'OdometerReading', 'position' => 60, 'notnull' => 1, 'visible' => 0, 'index' => 1),
		'fk_user_driver' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Driver', 'position' => 70, 'notnull' => -1, 'visible' => 1, 'index' => 1),
		'fk_project' => array('type' => 'integer:Project:projet/class/project.class.php', 'label' => 'Project', 'position' => 75, 'notnull' => -1, 'visible' => 1, 'index' => 1),
		'fk_payment_various' => array('type' => 'integer:PaymentVarious:compta/bank/class/paymentvarious.class.php', 'label' => 'VariousPayment', 'position' => 76, 'notnull' => -1, 'visible' => 0, 'index' => 1, 'noteditable' => 1),
		'quantity' => array('type' => 'double(24,8)', 'label' => 'Quantity', 'position' => 80, 'notnull' => 1, 'visible' => 1),
		'total_ttc' => array('type' => 'double(24,8)', 'label' => 'TotalTTC', 'position' => 90, 'notnull' => 0, 'visible' => 1, 'default' => null),
		'currency_snapshot' => array('type' => 'varchar(3)', 'label' => 'Currency', 'position' => 100, 'notnull' => 1, 'visible' => 1),
		'oil_reference' => array('type' => 'varchar(128)', 'label' => 'OilReference', 'position' => 110, 'notnull' => -1, 'visible' => 1),
		'description' => array('type' => 'text', 'label' => 'Description', 'position' => 120, 'notnull' => -1, 'visible' => 3),
		'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'position' => 130, 'notnull' => -1, 'visible' => 0),
		'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'position' => 140, 'notnull' => -1, 'visible' => 0),
		'status' => array('type' => 'integer', 'label' => 'Status', 'position' => 200, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'default' => 1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'position' => 501, 'notnull' => 0, 'visible' => -2, 'noteditable' => 1),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'model_pdf' => array('type' => 'varchar(255)', 'label' => 'Model', 'position' => 1010, 'notnull' => -1, 'visible' => 0),
		'last_main_doc' => array('type' => 'varchar(255)', 'label' => 'LastMainDoc', 'position' => 1020, 'notnull' => -1, 'visible' => 0),
	);

	/** @var string */ public $ref = '';
	/** @var int */ public $fk_vehicle = 0;
	/** @var int */ public $fk_consumable = 0;
	/** @var string */ public $category_snapshot = '';
	/** @var string */ public $unit_snapshot = '';
	/** @var int */ public $fk_odometer_reading = 0;
	/** @var ?int */ public $fk_user_driver;
	/** @var ?int */ public $fk_project;
	/** @var ?int */ public $fk_payment_various;
	/** @var float */ public $quantity = 0.0;
	/** @var float|null NULL means an unknown additive price. */ public $total_ttc = null;
	/** @var string */ public $currency_snapshot = '';
	/** @var ?string */ public $oil_reference;
	/** @var ?string */ public $description;
	/** @var ?string */ public $note_public;
	/** @var ?string */ public $note_private;
	/** @var ?string */ public $model_pdf;
	/** @var ?string */ public $last_main_doc;

	/** Date and mileage are transient proxies for the owned odometer reading. */
	/** @var int */ public $reading_date = 0;
	/** @var float */ public $odometer_km = 0.0;
	/** @var string */ public $reading_kind = 'standard';
	/** @var ?string */ public $reading_reason;

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->status = self::STATUS_RECORDED;
	}

	/** @inheritdoc */
	public function fetch($id, $ref = null)
	{
		$result = parent::fetch($id, $ref);
		if ($result > 0 && empty($this->fk_user_driver) && !empty($this->fk_user_creat)) {
			$this->fk_user_driver = (int) $this->fk_user_creat;
		}
		if ($result > 0 && (int) $this->fk_odometer_reading > 0) {
			$reading = new LmdbVehicleOdometerReading($this->db);
			if ($reading->fetch((int) $this->fk_odometer_reading) <= 0) {
				$this->error = $reading->error ?: 'InvalidOdometerReading';
				$this->errors = $reading->errors;
				return -1;
			}
			$this->reading_date = (int) $reading->reading_date;
			$this->odometer_km = (float) $reading->odometer_km;
			$this->reading_kind = (string) $reading->reading_kind;
			$this->reading_reason = $reading->reason;
		}
		return $result;
	}

	/** @inheritdoc */
	public function create(User $user, $notrigger = 0)
	{
		global $conf;

		$this->entity = (int) $conf->entity;
		$this->status = self::STATUS_RECORDED;
		if (empty($this->fk_user_driver)) {
			$this->fk_user_driver = (int) $user->id;
		}
		if ($this->validateBusinessRules() < 0) {
			return -1;
		}
		$this->db->begin();
		$reading = $this->buildReading();
		$result = $reading->createFromConsumption($user);
		if ($result <= 0) {
			return $this->rollbackFrom($reading, $result);
		}
		$this->fk_odometer_reading = (int) $reading->id;
		$result = parent::create($user, $notrigger);
		if ($result <= 0) {
			$this->db->rollback();
			return $result;
		}
		$this->db->commit();
		return $result;
	}

	/** @inheritdoc */
	public function update(User $user, $notrigger = 0)
	{
		$current = new self($this->db);
		if (empty($this->id) || $current->fetch((int) $this->id) <= 0) {
			$this->error = $current->error ?: 'RecordNotFound';
			$this->errors = $current->errors;
			return -1;
		}
		$this->entity = (int) $current->entity;
		$this->fk_odometer_reading = (int) $current->fk_odometer_reading;
		$this->fk_payment_various = !empty($current->fk_payment_various) ? (int) $current->fk_payment_various : null;
		$this->currency_snapshot = (string) $current->currency_snapshot;
		if (empty($this->fk_user_driver)) {
			$this->fk_user_driver = !empty($current->fk_user_driver) ? (int) $current->fk_user_driver : (int) $user->id;
		}
		if ($this->validateBusinessRules() < 0) {
			return -1;
		}
		$this->db->begin();
		$reading = new LmdbVehicleOdometerReading($this->db);
		if ($reading->fetch((int) $this->fk_odometer_reading) <= 0) {
			return $this->rollbackFrom($reading, -1);
		}
		$reading->fk_vehicle = (int) $this->fk_vehicle;
		$reading->reading_date = (int) $this->reading_date;
		$reading->odometer_km = (float) $this->odometer_km;
		$reading->reading_kind = (string) $this->reading_kind;
		$reading->reason = $this->reading_reason;
		$result = $reading->updateFromConsumption($user);
		if ($result <= 0) {
			return $this->rollbackFrom($reading, $result);
		}
		$result = parent::update($user, $notrigger);
		if ($result <= 0) {
			$this->db->rollback();
			return $result;
		}
		$this->db->commit();
		return $result;
	}

	/** @inheritdoc */
	public function delete(User $user, $notrigger = 0)
	{
		$sql = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE entity = '.((int) $this->entity).' AND fk_odometer_reading = '.((int) $this->fk_odometer_reading);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($row) || (int) $row->total !== 1) {
			$this->error = 'ConsumptionOdometerReadingIsShared';
			$this->errors[] = $this->error;
			return 0;
		}
		$this->db->begin();
		$reading = new LmdbVehicleOdometerReading($this->db);
		if ($reading->fetch((int) $this->fk_odometer_reading) <= 0) {
			return $this->rollbackFrom($reading, -1);
		}
		$result = $reading->deleteFromConsumption($user, $notrigger);
		if ($result <= 0) {
			return $this->rollbackFrom($reading, $result);
		}
		$result = parent::delete($user, $notrigger);
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
		global $conf, $langs;

		$sql = 'SELECT entity FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle';
		$sql .= ' WHERE rowid = '.((int) $this->fk_vehicle).' AND entity IN ('.getEntity('lmdbvehicle').')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$vehicle = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($vehicle)) {
			return $this->businessError('InvalidVehicle');
		}
		if (!empty($this->entity) && (int) $this->entity !== (int) $vehicle->entity) {
			return $this->businessError('CannotMoveObjectBetweenEntities');
		}
		$this->entity = (int) $vehicle->entity;

		$consumable = new LmdbVehicleConsumable($this->db);
		if ($consumable->fetch((int) $this->fk_consumable) <= 0 || !$consumable->active) {
			return $this->businessError('InvalidConsumable');
		}
		$this->category_snapshot = (string) $consumable->category;
		$this->unit_snapshot = (string) $consumable->unit;
		if (!in_array($this->category_snapshot, array('fuel', 'additive'), true)) {
			return $this->businessError('InvalidConsumptionNature');
		}
		if ($this->category_snapshot === 'fuel') {
			$sql = 'SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumable_energy AS ce ON ce.fk_energy = v.fk_energy';
			$sql .= ' WHERE v.rowid = '.((int) $this->fk_vehicle).' AND v.entity = '.((int) $this->entity);
			$sql .= ' AND ce.fk_consumable = '.((int) $this->fk_consumable);
			$sql .= ' AND ce.entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').') LIMIT 1';
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$compatible = $this->db->num_rows($resql) === 1;
			$this->db->free($resql);
			if (!$compatible) {
				return $this->businessError('ConsumableNotCompatibleWithVehicleEnergy');
			}
		}
		if ($this->quantity <= 0) {
			return $this->businessError($langs->trans('FieldMustBeGreaterThan', $langs->trans('Quantity'), 0));
		}
		if ($this->category_snapshot === 'additive' && ($this->total_ttc === null || trim((string) $this->total_ttc) === '')) {
			$this->total_ttc = null;
		} else {
			$this->total_ttc = (float) price2num($this->total_ttc, 'MT');
		}
		if ($this->total_ttc !== null && $this->total_ttc < 0) {
			return $this->businessError('ConsumptionTotalCannotBeNegative');
		}
		if ($this->currency_snapshot === '') {
			$this->currency_snapshot = getDolGlobalString('MAIN_MONNAIE', !empty($conf->currency) ? (string) $conf->currency : 'EUR');
		}
		$this->currency_snapshot = strtoupper(substr($this->currency_snapshot, 0, 3));
		$this->oil_reference = $consumable->requires_oil_reference ? trim((string) $this->oil_reference) : null;
		if ($consumable->requires_oil_reference && $this->oil_reference === '') {
			return $this->businessError('OilReferenceRequired');
		}
		$this->status = self::STATUS_RECORDED;

		if ($this->fk_odometer_reading > 0) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading';
			$sql .= ' WHERE rowid = '.((int) $this->fk_odometer_reading).' AND entity = '.((int) $this->entity);
			$sql .= " AND source = 'consumption'";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$valid = $this->db->num_rows($resql) === 1;
			$this->db->free($resql);
			if (!$valid) {
				return $this->businessError('InvalidOdometerReading');
			}
		}
		if ($this->fk_user_driver !== null && (int) $this->fk_user_driver > 0) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE rowid = '.((int) $this->fk_user_driver).' AND statut = 1';
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$valid = $this->db->num_rows($resql) === 1;
			$this->db->free($resql);
			if (!$valid) {
				return $this->businessError('InvalidDriver');
			}
		}
		if ($this->fk_project !== null && (int) $this->fk_project > 0) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'projet';
			$sql .= ' WHERE rowid = '.((int) $this->fk_project).' AND entity IN ('.getEntity('project').')';
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$valid = $this->db->num_rows($resql) === 1;
			$this->db->free($resql);
			if (!$valid) {
				return $this->businessError('InvalidProject');
			}
		} else {
			$this->fk_project = null;
		}
		return 1;
	}

	/** @return LmdbVehicleOdometerReading */
	private function buildReading()
	{
		$reading = new LmdbVehicleOdometerReading($this->db);
		$reading->entity = (int) $this->entity;
		$reading->fk_vehicle = (int) $this->fk_vehicle;
		$reading->reading_date = (int) $this->reading_date;
		$reading->odometer_km = (float) $this->odometer_km;
		$reading->reading_kind = (string) $this->reading_kind;
		$reading->reason = $this->reading_reason;
		$reading->source = 'consumption';
		return $reading;
	}

	/** @param object $source Source exposing errors @param int $result Result @return int */
	private function rollbackFrom($source, $result)
	{
		$this->error = isset($source->error) && is_string($source->error) ? $source->error : 'Error';
		$this->errors = isset($source->errors) && is_array($source->errors) ? $source->errors : array();
		$this->db->rollback();
		return $result < 0 ? -1 : 0;
	}

	/** @param string $error Error key or translated message @return int<-1,-1> */
	private function businessError($error)
	{
		$this->error = $error;
		$this->errors[] = $error;
		return -1;
	}

	/** @return string|int<-1,0> */
	protected function getNextNumRef()
	{
		global $langs;
		$model = getDolGlobalString('LMDBVEHICLEMANAGEMENT_CONSUMPTION_ADDON', 'mod_lmdbvehicleconsumption_standard');
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
		return $this->TRIGGER_PREFIX.':'.getEntity('lmdbvehicleconsumptionnumber', 1, $this);
	}

	/** @inheritdoc */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;
		$label = $langs->trans('ConsumptionStatusRecorded');
		return dolGetStatus($label, $label, '', 'status4', $mode);
	}

	/** @return float|null */
	public function getUnitPrice()
	{
		if ($this->total_ttc === null) {
			return null;
		}
		return $this->quantity > 0 ? (float) price2num($this->total_ttc / $this->quantity, 'MU') : 0.0;
	}

	/** @return float|null */
	public function getConfiguredCapacity()
	{
		$sql = 'SELECT capacity FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_capacity';
		$sql .= ' WHERE entity = '.((int) $this->entity).' AND fk_vehicle = '.((int) $this->fk_vehicle);
		$sql .= ' AND fk_consumable = '.((int) $this->fk_consumable).' LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return null;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return is_object($row) ? (float) $row->capacity : null;
	}

	/** @return float|null */
	public function getCapacityPercentage()
	{
		$capacity = $this->getConfiguredCapacity();
		return $capacity !== null && $capacity > 0 ? (float) ($this->quantity / $capacity * 100) : null;
	}

	/** @param DoliDB $db Database @param int $vehicleId Vehicle @return int */
	public static function suggestConsumable($db, $vehicleId)
	{
		$sql = 'SELECT c.fk_consumable FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumption AS c';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading AS r ON r.rowid = c.fk_odometer_reading AND r.entity = c.entity';
		$sql .= " WHERE c.fk_vehicle = ".((int) $vehicleId)." AND c.category_snapshot = 'fuel'";
		$sql .= ' AND c.entity IN ('.getEntity('lmdbvehicleconsumption').') ORDER BY r.reading_date DESC, c.rowid DESC LIMIT 1';
		$resql = $db->query($sql);
		if ($resql && is_object($row = $db->fetch_object($resql))) {
			$id = (int) $row->fk_consumable;
			$db->free($resql);
			return $id;
		}
		if ($resql) {
			$db->free($resql);
		}
		$dictionary = new LmdbVehicleConsumable($db);
		$options = $dictionary->getOptions('fuel', $vehicleId);
		return count($options) === 1 ? (int) array_key_first($options) : 0;
	}

	/** @inheritdoc */
	protected function getCardPage()
	{
		return 'consumption_card.php';
	}

	/** @inheritdoc */
	protected function getCardUrlParameters()
	{
		return 'id='.((int) $this->id);
	}
}
