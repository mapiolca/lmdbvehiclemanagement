<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Actual payment service, consumption methods and sharing SQL. SQLite plus explicit
// native persistence/upload/ECM doubles; no instance, bank, email or HTTP upload.
define('MAIN_DB_PREFIX', 'od_sharing_');
define('DOL_VERSION', '20.0.0');
define('DOL_URL_ROOT', '');
$root = __DIR__.'/.dossier-test/od-sharing-'.getmypid();
define('DOL_DOCUMENT_ROOT', $root.'/core-fixture');
foreach (array('/compta/bank/class/paymentvarious.class.php', '/compta/bank/class/account.class.php', '/core/lib/files.lib.php') as $path) {
	if (!is_dir(dirname(DOL_DOCUMENT_ROOT.$path))) mkdir(dirname(DOL_DOCUMENT_ROOT.$path), 0777, true);
	file_put_contents(DOL_DOCUMENT_ROOT.$path, "<?php\n// Native boundary double defined by the test runner.\n");
}
register_shutdown_function(static function () use ($root) {
	// Only this process's fixture directory, including failed-run artifacts.
	if (!is_dir($root)) return;
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($files as $file) { if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
	rmdir($root);
});
function dol_include_once($path) {}
function isModEnabled($module) { return in_array($module, array('lmdbvehiclemanagement', 'multicompany', 'bank'), true); }
function getDolGlobalInt($key, $default = 0) { return (int) ($GLOBALS['conf']->global->{$key} ?? $default); }
function getDolGlobalString($key, $default = '') { return (string) ($GLOBALS['conf']->global->{$key} ?? $default); }
function getEntity($element) { return $element === 'lmdbvehicle' ? '1,2' : (string) $GLOBALS['conf']->entity; }
function dol_buildpath($path, $mode = 0) { return __FILE__; }
function dol_strlen($value) { return strlen($value); }
function dol_sanitizeFileName($value) { return basename($value); }
function price2num($value, $mode = '') { return (float) $value; }
function dol_now() { return 1700000000; }
function dol_syslog($message, $level = 0) {}
function getMultidirOutput($object, $module = '', $forobject = 0) {
	return $GLOBALS['directoryResult'] ?? $GLOBALS['conf']->{$module}->multidir_output[$object->entity].'/'.$object->ref;
}
function dol_mkdir($path) { return $GLOBALS['db']->fail === 'directory' ? -1 : (is_dir($path) || mkdir($path, 0777, true) ? 1 : -1); }
function addFileIntoDatabaseIndex($directory, $fileName, $sourceName, $type, $position, $object) {
	global $db, $conf;
	if ($db->fail === 'index') return -1;
	$db->ecm[$directory.'/'.$fileName] = array('entity' => $conf->entity, 'payment' => $object->id);
	return 1;
}
function deleteFilesIntoDatabaseIndex($directory, $fileName, $type) { unset($GLOBALS['db']->ecm[$directory.'/'.$fileName]); }
function dol_delete_file($path, $index = 0, $a = 0, $b = 0, $object = null) { unset($GLOBALS['db']->ecm[$path]); return !is_file($path) || unlink($path); }
class modMultiCompany { public $version = '21.0.2'; public function __construct($db) {} }
class ActionsMulticompany { public function formSelectSharingByElement() {} public function multiselectEntitiesForGranularity() {} }
class DaoMulticompany { public function setSharingsByElement() {} public function getListOfSharingsByElement() {} }
class User {
	public $id = 7, $socid = 0, $admin = 0, $lastname = 'Test', $firstname = 'Driver', $allowed = true;
	public function hasRight(...$args) { return $this->allowed; }
}
class OdSharingDb {
	public $pdo, $objects = array(), $ecm = array(), $events = array(), $snapshots = array(), $fail = '';
	public function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
	public function query($sql) {
		if (strpos($sql, 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumption SET fk_payment_various') === 0 && $this->fail === 'link') return false;
		if (strpos($sql, 'information_schema.TABLES') !== false) return (object) array('rows' => array((object) array('nb' => 1)), 'pos' => 0);
		$result = $this->pdo->query($sql);
		return (object) array('rows' => $result->columnCount() ? $result->fetchAll(PDO::FETCH_OBJ) : array(), 'pos' => 0);
	}
	public function fetch_object($result) { return $result->rows[$result->pos++] ?? false; }
	public function num_rows($result) { return count($result->rows); }
	public function free($result) {}
	public function sanitize($value) { return $value; }
	public function escape($value) { return str_replace("'", "''", $value); }
	public function lasterror() { return 'InjectedDatabaseFailure'; }
	public function begin() { $level = count($this->snapshots); $this->pdo->exec('SAVEPOINT level'.$level); $this->snapshots[] = serialize(array($this->objects, $this->ecm, $this->events)); }
	public function commit() { array_pop($this->snapshots); $this->pdo->exec('RELEASE SAVEPOINT level'.count($this->snapshots)); }
	public function rollback() { list($this->objects, $this->ecm, $this->events) = unserialize(array_pop($this->snapshots)); $this->pdo->exec('ROLLBACK TO SAVEPOINT level'.count($this->snapshots)); $this->pdo->exec('RELEASE SAVEPOINT level'.count($this->snapshots)); }
	public function save($object, $table) {
		$row = get_object_vars($object); unset($row['db']);
		$this->objects[$table][$object->id] = $row;
	}
}
// Persistence boundaries. The real consumption create/update/validation methods
// still decide ownership and build the associated odometer reading.
class LmdbVehicleManagementObject extends stdClass {
	public $db, $id = 0, $entity = 0, $ref = '', $error = '', $errors = array(), $context = array();
	public function __construct($db) { $this->db = $db; }
	public function create(User $user, $notrigger = 0) {
		if ($this->db->fail === 'consumption') { $this->error = 'InjectedConsumptionFailure'; return -1; }
		$this->id = 10; $this->ref = 'FUEL-10';
		$this->db->query('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumption VALUES (10,'.(int) $this->entity.',NULL)');
		$this->db->save($this, 'consumption');
		if (!$notrigger) $this->call_trigger($this->TRIGGER_PREFIX.'_CREATE', $user);
		return $this->id;
	}
	public function fetch($id, $ref = null) {
		$row = $this->db->objects['consumption'][$id] ?? null;
		if (!$row) return 0;
		foreach ($row as $key => $value) $this->{$key} = $value;
		$link = $this->db->fetch_object($this->db->query('SELECT fk_payment_various FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumption WHERE rowid='.(int) $id));
		$this->fk_payment_various = $link->fk_payment_various;
		return 1;
	}
	public function update(User $user, $notrigger = 0) { $this->db->save($this, 'consumption'); return 1; }
	public function call_trigger($code, $user) { if ($this->db->fail === 'trigger') { $this->error = 'InjectedTriggerFailure'; return -1; } $this->db->events[] = $code; return 1; }
}
class LmdbVehicleOdometerReading extends stdClass {
	public $db, $id = 0, $error = '', $errors = array();
	public function __construct($db) { $this->db = $db; }
	public function fetch($id) { foreach ($this->db->objects['reading'][$id] ?? array() as $key => $value) $this->{$key} = $value; return $this->id > 0 ? 1 : 0; }
	public function createFromConsumption($user, $notrigger = 0) { $this->id = 20; $this->db->query('INSERT INTO '.MAIN_DB_PREFIX."lmdbvehiclemanagement_odometer_reading VALUES (20,".(int) $this->entity.",'consumption')"); $this->db->save($this, 'reading'); return 20; }
	public function updateFromConsumption($user, $notrigger = 0) { $this->db->save($this, 'reading'); return 1; }
}
class LmdbVehicleConsumable extends stdClass {
	private $db;
	public function __construct($db) { $this->db = $db; }
	public function fetch($id) { $row = $this->db->fetch_object($this->db->query('SELECT * FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable WHERE rowid='.(int) $id)); if (!$row) return 0; foreach (get_object_vars($row) as $key => $value) $this->{$key} = $value; return 1; }
}
class PaymentVarious extends stdClass {
	public $db, $id = 0, $ref = '', $entity = 0, $error = '', $errors = array(), $rappro = 0, $accounted = 0;
	public function __construct($db) { $this->db = $db; }
	public function create($user) {
		if ($this->db->fail === 'payment') { $this->error = 'InjectedPaymentFailure'; return -1; }
		$this->id = 30; $this->ref = '30'; $this->entity = $GLOBALS['conf']->entity; $this->fk_bank = 40;
		$this->db->query('INSERT INTO '.MAIN_DB_PREFIX.'payment_various VALUES (30,'.(int) $this->entity.')');
		$this->db->save($this, 'payment');
		$this->db->objects['bank'][40] = array('id' => 40, 'amount' => -abs($this->amount), 'label' => $this->label, 'fk_account' => $this->fk_account);
		return 30;
	}
	public function fetch($id) { foreach ($this->db->objects['payment'][$id] ?? array() as $key => $value) $this->{$key} = $value; return $this->id > 0 ? 1 : 0; }
	public function update($user) { if ($this->db->fail === 'payment_update') { $this->error = 'InjectedPaymentUpdateFailure'; return -1; } $this->db->save($this, 'payment'); return 1; }
	public function getVentilExportCompta($mode = 0) { return $this->accounted; }
}
class AccountLine extends stdClass {
	public $db, $id = 0;
	public function __construct($db) { $this->db = $db; }
	public function fetch($id) { foreach ($this->db->objects['bank'][$id] ?? array() as $key => $value) $this->{$key} = $value; return $this->id > 0 ? 1 : 0; }
	public function update($user) { $this->db->save($this, 'bank'); return 1; }
	public function updateLabel() { $this->db->save($this, 'bank'); return 1; }
}
class LmdbVehicleManagementSecureUpload {
	public $error = '', $errors = array();
	public function inspect($upload, $keys) { if (empty($upload)) { $this->error = $keys['invalid_upload']; return null; } return array('mime' => 'application/pdf', 'extension' => 'pdf'); }
	public function store($upload, $path, $mime, $keys) { if ($GLOBALS['db']->fail === 'upload') { $this->error = $keys['save']; return -1; } return file_put_contents($path, '%PDF-1.4 test fixture') !== false ? 1 : -1; }
}
require_once dirname(__DIR__).'/class/lmdbvehicleconsumption.class.php';
require_once dirname(__DIR__).'/class/lmdbvehicleconsumptionpayment.class.php';
$checks = 0; $caseNumber = 0;
function checkOdSharing($condition, $message) { if (!$condition) throw new RuntimeException($message); $GLOBALS['checks']++; }
function odFixture($vehicle = 1) {
	global $db, $conf, $user, $langs, $root, $caseNumber, $directoryResult;
	$directoryResult = null; $caseNumber++; $db = new OdSharingDb(); $user = new User();
	$langs = new class { public function trans($key, ...$args) { return $key; } };
	$conf = (object) array('entity' => 2, 'currency' => 'EUR', 'global' => (object) array(
		'LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_ENABLED' => 1, 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_BANK_ACCOUNT' => 22,
		'LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_PAYMENT_MODE' => 4, 'MULTICOMPANY_SHARINGS_ENABLED' => 1,
		'MULTICOMPANY_SHARING_BYELEMENT_ENABLED' => 1, 'MULTICOMPANY_LMDBVEHICLE_SHARING_BYELEMENT_ENABLED' => 1),
		'bank' => (object) array('dir_output' => $root.'/forbidden-fallback', 'multidir_output' => array(1 => $root.'/A', 2 => $root.'/case'.$caseNumber.'/B')));
	foreach (array(
		'lmdbvehiclemanagement_vehicle (rowid integer,entity integer,registration_number text,ref text,fk_energy integer)',
		'entity_element_sharing (entity integer,element text,fk_element integer)',
		'c_lmdbvehiclemanagement_consumable (rowid integer,entity integer,label text,category text,unit text,active integer,requires_oil_reference integer)',
		'c_lmdbvehiclemanagement_energy (rowid integer,code text)', 'lmdbvehiclemanagement_consumable_energy (entity integer,fk_consumable integer,fk_energy integer)',
		'bank_account (rowid integer,entity integer,clos integer)', 'c_paiement (id integer,entity integer,active integer,type integer)',
		'user (rowid integer,statut integer)', 'lmdbvehiclemanagement_consumption (rowid integer,entity integer,fk_payment_various integer)',
		'lmdbvehiclemanagement_odometer_reading (rowid integer,entity integer,source text)', 'payment_various (rowid integer,entity integer)'
	) as $definition) $db->query('CREATE TABLE '.MAIN_DB_PREFIX.$definition);
	foreach (array(
		"lmdbvehiclemanagement_vehicle VALUES (1,1,'SHARED-01','VA',1),(2,2,'LOCAL-02','VB',1),(3,1,'PRIVATE-03','VC',1)",
		"entity_element_sharing VALUES (2,'lmdbvehicle',1)",
		"c_lmdbvehiclemanagement_consumable VALUES (1,2,'Diesel','fuel','L',1,0),(2,2,'Additive','additive','L',1,0)",
		"c_lmdbvehiclemanagement_energy VALUES (1,'DIESEL')", 'lmdbvehiclemanagement_consumable_energy VALUES (2,1,1)',
		'bank_account VALUES (11,1,0),(22,2,0)', 'c_paiement VALUES (4,2,1,1)', 'user VALUES (7,1)'
	) as $data) $db->query('INSERT INTO '.MAIN_DB_PREFIX.$data);
	$entry = new LmdbVehicleConsumption($db); $entry->fk_vehicle = $vehicle; $entry->fk_consumable = 1;
	$entry->quantity = 50; $entry->total_ttc = 100; $entry->reading_date = dol_now(); $entry->odometer_km = 5000;
	return array(new LmdbVehicleConsumptionPayment($db), $entry);
}
function odCreate($service, $entry, $receipt = true) { return $service->createConsumption($entry, $receipt ? array('name' => 'receipt.pdf') : array(), 0, $GLOBALS['user']); }
function odEmpty($label) {
	global $db, $root, $caseNumber;
	foreach (array('consumption', 'reading', 'payment', 'bank') as $table) checkOdSharing(empty($db->objects[$table]), $label.': no '.$table);
	foreach (array('lmdbvehiclemanagement_consumption', 'lmdbvehiclemanagement_odometer_reading', 'payment_various') as $table) {
		$row = $db->fetch_object($db->query('SELECT COUNT(*) AS n FROM '.MAIN_DB_PREFIX.$table)); checkOdSharing((int) $row->n === 0, $label.': SQL rollback '.$table);
	}
	checkOdSharing(!$db->ecm && !$db->events && !$db->snapshots, $label.': no index, event or open transaction');
	checkOdSharing(!glob($root.'/case'.$caseNumber.'/B/30/*'), $label.': no orphan receipt');
}
foreach (array(1 => 'SHARED-01', 2 => 'LOCAL-02') as $vehicle => $registration) {
	list($service, $entry) = odFixture($vehicle);
	checkOdSharing(odCreate($service, $entry) > 0, 'Create '.$registration.': '.$service->error.' '.$entry->error);
	foreach (array('consumption', 'reading', 'payment') as $table) checkOdSharing(reset($db->objects[$table])['entity'] === 2, $table.' belongs to input entity');
	checkOdSharing($db->objects['reading'][20]['fk_vehicle'] === $vehicle, 'Reading remains linked to selected vehicle');
	checkOdSharing($db->objects['payment'][30]['label'] === 'FUEL-10 - '.$registration.' - Diesel', 'Complete native OD label');
	checkOdSharing($db->objects['payment'][30]['fk_account'] === 22 && $db->objects['bank'][40]['amount'] === -100.0, 'Input entity bank settings and debit');
	checkOdSharing($db->objects['payment'][30]['type_payment'] === 4, 'Configured payment mode');
	$path = $service->getReceiptPath($entry);
	checkOdSharing($path === $conf->bank->multidir_output[2].'/30/ticket-10.pdf' && is_file($path), 'Receipt in input owner directory');
	checkOdSharing($db->ecm[$path] === array('entity' => 2, 'payment' => 30), 'ECM indexed in input entity');
	checkOdSharing($db->events === array('LMDBVEHICLEMANAGEMENT_CONSUMPTION_CREATE') && !$db->snapshots, 'One consumption trigger and committed transaction');
	$owner = $db->fetch_object($db->query('SELECT entity FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle WHERE rowid='.$vehicle));
	checkOdSharing((int) $owner->entity === ($vehicle === 1 ? 1 : 2), 'Vehicle ownership retained');
	$entry->total_ttc = 120;
	checkOdSharing($service->updateConsumption($entry, $user) > 0, 'Update '.$registration);
	checkOdSharing($db->objects['payment'][30]['amount'] === 120.0 && $db->objects['bank'][40]['amount'] === -120.0, 'OD and bank update together');
	checkOdSharing($db->objects['payment'][30]['label'] === 'FUEL-10 - '.$registration.' - Diesel', 'Update keeps registration');
}
foreach (array('inaccessible', 'withdrawn', 'permission', 'admin_permission', 'external', 'receipt', 'bank', 'amount', 'consumption', 'payment', 'link', 'directory', 'upload', 'index', 'trigger') as $case) {
	list($service, $entry) = odFixture($case === 'inaccessible' ? 3 : 1);
	if ($case === 'withdrawn') $db->query('DELETE FROM '.MAIN_DB_PREFIX.'entity_element_sharing');
	if ($case === 'permission' || $case === 'admin_permission') { $user->allowed = false; $user->admin = $case === 'admin_permission' ? 1 : 0; }
	if ($case === 'external') $user->socid = 5;
	if ($case === 'bank') $conf->global->LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_BANK_ACCOUNT = 11;
	if ($case === 'amount') $entry->total_ttc = 0;
	$db->fail = $case;
	checkOdSharing(odCreate($service, $entry, $case !== 'receipt') < 0, 'Refuse '.$case);
	$expected = array('inaccessible' => 'InvalidVehicle', 'withdrawn' => 'InvalidVehicle', 'permission' => 'NotEnoughPermissions', 'admin_permission' => 'NotEnoughPermissions', 'external' => 'NotEnoughPermissions', 'receipt' => 'ConsumptionReceiptRequired', 'bank' => 'ConsumptionOdBankAccountInvalid', 'amount' => 'ConsumptionOdAmountMustBePositive');
	if (isset($expected[$case])) checkOdSharing($service->error === $expected[$case], 'Specific refusal '.$case.': '.$service->error);
	odEmpty($case);
}
foreach (array('missing', '', '   ', 'error-diroutput-unavailable', array(), 42) as $invalidRoot) {
	list($service, $entry) = odFixture();
	if ($invalidRoot === 'missing') unset($conf->bank->multidir_output[2]); else $conf->bank->multidir_output[2] = $invalidRoot;
	checkOdSharing(odCreate($service, $entry) < 0, 'Reject missing/invalid owner root'); odEmpty('owner root');
}
foreach (array('', 'error-diroutput-native', false) as $invalidDirectory) {
	list($service, $entry) = odFixture(); $directoryResult = $invalidDirectory;
	checkOdSharing(odCreate($service, $entry) < 0, 'Reject invalid native directory result'); odEmpty('native directory');
}
foreach (array('rappro', 'accounted', 'foreign', 'withdrawn', 'payment_update') as $case) {
	list($service, $entry) = odFixture(); checkOdSharing(odCreate($service, $entry) > 0, 'Prepare update '.$case);
	if ($case === 'rappro' || $case === 'accounted') $db->objects['payment'][30][$case] = 1;
	if ($case === 'foreign') $conf->entity = 1;
	if ($case === 'withdrawn') $db->query('DELETE FROM '.MAIN_DB_PREFIX.'entity_element_sharing');
	$db->fail = $case; $before = serialize(array($db->objects, $db->ecm, $db->events)); $entry->total_ttc = 150;
	checkOdSharing($service->updateConsumption($entry, $user) < 0, 'Reject update '.$case);
	checkOdSharing(serialize(array($db->objects, $db->ecm, $db->events)) === $before && !$db->snapshots, 'No partial update '.$case);
	if (in_array($case, array('rappro', 'accounted', 'foreign'), true)) {
		checkOdSharing($service->replaceReceipt($entry, array('name' => 'new.pdf'), $user) < 0, 'Receipt protected '.$case);
		checkOdSharing($service->deleteConsumption($entry, $user) < 0, 'Deletion protected '.$case);
	}
}
foreach (array('disabled', 'additive') as $case) {
	list($service, $entry) = odFixture();
	if ($case === 'disabled') $conf->global->LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_ENABLED = 0;
	else { $entry->fk_consumable = 2; $entry->total_ttc = null; }
	checkOdSharing(odCreate($service, $entry, false) > 0, 'Non-OD path '.$case);
	checkOdSharing($entry->entity === 2 && empty($db->objects['payment']) && !$db->ecm, 'Non-OD ownership '.$case);
}
checkOdSharing(!is_dir($root.'/A') && !is_dir($root.'/forbidden-fallback'), 'No files in vehicle entity or fallback directory');
echo $checks." consumption OD sharing checks passed (SQLite, native persistence/upload/ECM doubles)\n";
