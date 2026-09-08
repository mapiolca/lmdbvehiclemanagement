<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Real module predicates and mutation method, SQLite and native API contract doubles.
// No production data, native files, or Multicompany installation are modified.
$core = isset($argv[1]) ? realpath($argv[1]) : false;
if (!$core || !is_file($core.'/core/class/commonobject.class.php')) { fwrite(STDERR, "Usage: php test/run_sharing.php <Dolibarr htdocs>\n"); exit(2); }
define('DOL_DOCUMENT_ROOT', $core);
define('DOL_VERSION', getenv('LMDB_TEST_DOL_VERSION') ?: '24.0.0');
define('MAIN_DB_PREFIX', 'sharing_long_prefix_');
define('DOL_URL_ROOT', '');
$conf = (object) array('entity' => 1, 'global' => new stdClass());
$langs = null;
$enabled = array('lmdbvehiclemanagement' => true, 'multicompany' => true);
function isModEnabled($name) { global $enabled; return !empty($enabled[$name]); }
function getDolGlobalInt($name, $default = 0) { global $conf; return isset($conf->global->$name) ? (int) $conf->global->$name : $default; }
function getDolGlobalString($name, $default = '') { global $conf; return isset($conf->global->$name) ? (string) $conf->global->$name : $default; }
function getEntity($element, $shared = 1, $object = null) {
	global $conf;
	$ids = array((int) $conf->entity);
	if (getDolGlobalInt('MULTICOMPANY_SHARINGS_ENABLED') && getDolGlobalInt('MULTICOMPANY_'.strtoupper($element).'_SHARING_ENABLED')) $ids = array_merge($ids, DaoMulticompany::$fixtures[$conf->entity]['sharings'][$element] ?? array());
	return implode(',', array_unique($ids));
}
function dol_buildpath($path, $mode = 0) { global $sharingFixture; return !empty($sharingFixture) ? $path : __FILE__; }
function dol_include_once($path) { if (strpos($path, '/lmdbvehiclemanagement/') === 0) require_once dirname(__DIR__).substr($path, strlen('/lmdbvehiclemanagement')); }
function dol_syslog($message, $level = 0) {}
function GETPOST($name, $type = 'alpha', $method = 0) { $value = $method === 2 ? ($_POST[$name] ?? null) : ($_POST[$name] ?? $_GET[$name] ?? null); return $value ?? ($type === 'array' ? array() : ''); }
function GETPOSTISSET($name) { return isset($_POST[$name]) || isset($_GET[$name]); }
function accessforbidden() { throw new RuntimeException('CSRF denied'); }
function currentToken() { return 'fixture'; }
function newToken() { return 'fixture'; }
function getNonce() { return 'fixture'; }
function dol_escape_htmltag($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function isSharingAllByDefault($element) { return getDolGlobalInt('MULTICOMPANY_'.strtoupper($element).'_SHARE_ALL_BY_DEFAULT'); }
function dolGetButtonAction($label, $text, $type, $url, $id) { return '<a class="butAction" id="'.$id.'" href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag($text).'</a>'; }

function dolibarr_set_const($db, $name, $value, $type, $visible, $note, $entity) { return $db->query("INSERT INTO ".MAIN_DB_PREFIX."const (name,value,entity) VALUES ('".$db->escape($name)."','".$db->escape($value)."',".(int) $entity.")") ? 1 : -1; }
class User {
	public $id = 1, $admin = 1, $entity = 0, $socid = 0, $login = 'test';
	public $rights = array();
	public function hasRight(...$args) { return !empty($this->rights[implode('.', $args)]); }
}
class modMultiCompany { public $version = '21.0.2'; public function __construct($db) {} }
if (!empty($argv[2])) require 'zip://'.realpath($argv[2]).'#multicompany/class/actions_multicompany.class.php';
else { class ActionsMulticompany {
	public function formSelectSharingByElement($object, $element = null) {}
	public function multiselectEntitiesForGranularity($element, $elementid = null, $onlyselected = false, $parentelement = null, $parentelementid = null, $selected = null) {}
} }

class SharingDb {
	public $pdo, $fail = '', $error = '';
	private $depth = 0;
	public function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
	public function query($sql) {
		if ($this->fail !== '' && strpos($sql, $this->fail) !== false) return false;
		if (strpos($sql, 'SELECT COUNT(*) AS nb FROM information_schema.TABLES') === 0) {
			$sql = "SELECT COUNT(*) AS nb FROM sqlite_master WHERE type = 'table' AND name = '".MAIN_DB_PREFIX."entity_element_sharing'";
		}
		if (preg_match('/^DELETE ec FROM (\w+) AS ec INNER JOIN (\w+) AS ctc ON ctc.rowid = ec.fk_c_type_contact WHERE (.+)$/D', $sql, $del)) {
			$sql = 'DELETE FROM '.$del[1].' WHERE rowid IN (SELECT ec.rowid FROM '.$del[1].' AS ec INNER JOIN '.$del[2].' AS ctc ON ctc.rowid=ec.fk_c_type_contact WHERE '.$del[3].')';
		}
		try { return $this->pdo->query(str_replace(' FOR UPDATE', '', $sql)); }
		catch (Throwable $e) { $this->error = $e->getMessage(); throw new RuntimeException($e->getMessage().' / '.$sql); }
	}
	public function fetch_object($res) { return $res->fetch(PDO::FETCH_OBJ); }
	public function free($res) { $res->closeCursor(); }
	public function escape($value) { return str_replace("'", "''", $value); }
	public function sanitize($value) { return $value; }
	public function begin() { if ($this->depth++ === 0) $this->pdo->beginTransaction(); }
	public function commit() { if (--$this->depth === 0) $this->pdo->commit(); }
	public function rollback() { $this->depth = 0; if ($this->pdo->inTransaction()) $this->pdo->rollBack(); }
	public function lasterror() { return $this->error; }
}
class DaoMulticompany {
	public static $fixtures = array();
	public $entities = array(), $options = array(), $active = 1, $visible = 1, $label = '', $id, $url = '', $country_id = 1, $country_code = 'FR', $currency_code = 'EUR', $language_code = 'fr_FR', $description = '';
	private $db;
	public function __construct($db) { $this->db = $db; }
	public function fetch($id) { if (!isset(self::$fixtures[$id])) return 0; $this->id = $id; $this->label = 'Entity '.$id; $this->options = self::$fixtures[$id]; return 1; }
	public function getEntities($login = false, $exclude = false, $active = false) { $this->entities = array(); foreach (array_keys(self::$fixtures) as $id) { $entity = new self($this->db); $entity->fetch($id); $this->entities[] = $entity; } return 1; }
	public function verifyRight($entity, $userId) { global $user; return $user->entity === 0 || $user->entity === $entity ? 1 : 0; }
	public function update($id, $user, $trigger = true) { self::$fixtures[$id] = $this->options; return 1; }
	public function getListOfSharingsByElement($element, $id = null) {
		$stored = LmdbVehicleSharing::stored($this->db, $element, $id);
		if (getDolGlobalInt('MULTICOMPANY_'.strtoupper($element).'_SHARE_ALL_BY_DEFAULT')) $stored = array_values(array_diff(array(2, 3), $stored));
		return array($id => $stored);
	}
	public function setSharingsByElement($element, $id, $entities) {
		if (!$this->db->query("DELETE FROM ".MAIN_DB_PREFIX."entity_element_sharing WHERE element = '".$element."' AND fk_element = ".$id)) return -1;
		foreach ($entities as $entity) if (!$this->db->query("INSERT INTO ".MAIN_DB_PREFIX."entity_element_sharing VALUES (".$entity.",'".$element."',".$id.")")) return -1;
		return 1;
	}
}
require_once dirname(__DIR__).'/class/lmdbvehiclemanagementobject.class.php';
require_once dirname(__DIR__).'/class/lmdbvehiclesharingmigration.class.php';
require_once dirname(__DIR__).'/class/lmdbvehiclesharinghooks.trait.php';
class SharingRecord extends LmdbVehicleManagementObject {
	public function LibStatut($status, $mode = 0) { return ''; }
	public function deleteObjectLinked($sourceid = null, $sourcetype = '', $targetid = null, $targettype = '', $rowid = 0, $f_user = null, $notrigger = 0) { return 1; }
	protected function getCardPage() { return 'vehicle_card.php'; }
	public $triggers = 0, $failTrigger = false, $fk_vehicle = 10, $fk_contract = 10;
	public function call_trigger($code, $user) { if ($this->failTrigger) return -1; $this->triggers++; return 1; }
	public function fetchCommon($id, $ref = null, $where = '', $noextrafields = 0) {
		$res = $this->db->query('SELECT t.* FROM '.MAIN_DB_PREFIX.$this->table_element.' t WHERE t.rowid = '.(int) $id.$where);
		$row = $this->db->fetch_object($res); $this->db->free($res);
		if (!$row) return 0;
		$this->id = (int) $row->rowid; $this->entity = (int) $row->entity;
		$this->fk_vehicle = (int) $row->fk_vehicle; $this->fk_contract = (int) $row->fk_contract; return 1;
	}
}
function checkSharing($ok, $message) { global $checks; $checks++; if (!$ok) throw new RuntimeException($message); }
class SharingHooksProbe { use LmdbVehicleSharingHooks; public $db, $results = array(), $resprints = ''; public function __construct($db) { $this->db = $db; } }
$checks = 0; $db = new SharingDb(); $user = new User(); $user->rights['lmdbvehiclemanagement.read'] = 1;
$hooks = new SharingHooksProbe($db); $hookmanager = null; $action = '';
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'entity_element_sharing (entity integer, element text, fk_element integer, UNIQUE(entity,element,fk_element))');
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'const (rowid integer PRIMARY KEY, entity integer, name text, value text)');
$definitions = LmdbVehicleSharing::definitions();
$conf->global->MULTICOMPANY_SHARINGS_ENABLED = 1;
$conf->global->MULTICOMPANY_SHARING_BYELEMENT_ENABLED = 1;
foreach ($definitions as $element => $definition) {
	$db->query('CREATE TABLE '.MAIN_DB_PREFIX.$definition['table'].' (rowid integer PRIMARY KEY, entity integer, fk_vehicle integer, fk_contract integer, ref text, fk_quartix integer DEFAULT NULL, is_estimate integer DEFAULT 0)');
	foreach (array(10, 11) as $id) $db->query('INSERT INTO '.MAIN_DB_PREFIX.$definition['table'].' (rowid,entity,fk_vehicle,fk_contract,ref) VALUES ('.$id.',1,10,10,\'REF-'.$id.'\')');
	$conf->global->{'MULTICOMPANY_'.strtoupper($element).'_SHARING_ENABLED'} = 1;
	$conf->global->{'MULTICOMPANY_'.strtoupper($element).'_SHARING_BYELEMENT_ENABLED'} = 1;
	foreach (array(1, 2, 3) as $entity) DaoMulticompany::$fixtures[$entity]['sharings'][$element] = $entity === 1 ? array() : array(1);
}
$dao = new DaoMulticompany($db);
foreach ($definitions as $element => $definition) $dao->setSharingsByElement($element, 10, array(2));
foreach (array(1, 2, 3) as $entity) {
	$conf->entity = $entity;
	foreach ($definitions as $element => $definition) {
		checkSharing(LmdbVehicleSharing::visible($db, $element, 10) === ($entity !== 3), $element.' shared to entity 2 only');
		checkSharing(LmdbVehicleSharing::visible($db, $element, 11) === ($entity === 1), $element.' second record stays private');
		$count = $db->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.$definition['table'].' t WHERE '.LmdbVehicleSharing::sql($db, $element))->fetchColumn();
		checkSharing((int) $count === ($entity === 1 ? 2 : ($entity === 2 ? 1 : 0)), $element.' SQL counts match access');
		$record = new SharingRecord($db); $record->element = $element; $record->table_element = $definition['table'];
		checkSharing(($record->fetch(11) > 0) === ($entity === 1), $element.' old direct URL denied');
		$hooks->printFieldListWhere(array('showrefnav' => true), $record, $action, $hookmanager);
		$matches = array();
		checkSharing(preg_match('/^ AND \(te\.rowid:in:([0-9,]+)\)$/D', $hooks->resprints, $matches) === 1, $element.' navigation provides native USF on '.DOL_VERSION);
		$navigationIds = array_values(array_filter(array_map('intval', explode(',', $matches[1]))));
		sort($navigationIds);
		checkSharing($navigationIds === ($entity === 1 ? array(10, 11) : ($entity === 2 ? array(10) : array())), $element.' navigation excludes unshared records and unauthorized entities');
	}
}
$db->fail = 'SELECT te.rowid';
$hooks->printFieldListWhere(array('showrefnav' => true), $record, $action, $hookmanager);
checkSharing($hooks->resprints === ' AND (te.rowid:in:0)', 'Navigation fails closed when its access query fails');
$db->fail = '';
$hooks->printFieldListWhere(array(), $record, $action, $hookmanager);
checkSharing($hooks->resprints === '', 'Navigation filter does not alter ordinary list hooks');
$conf->entity = 2;
$dao->setSharingsByElement('lmdbvehicle', 10, array());
foreach ($definitions as $element => $definition) checkSharing(LmdbVehicleSharing::visible($db, $element, 10) === ($element === 'lmdbinsurancecontract'), $element.' requires its vehicle except fleet contract');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_certificate SET fk_vehicle = NULL WHERE rowid = 10');
checkSharing(LmdbVehicleSharing::visible($db, 'lmdbinsurancecertificate', 10), 'Fleet certificate requires contract only');
$dao->setSharingsByElement('lmdbinsurancecontract', 10, array());
checkSharing(!LmdbVehicleSharing::visible($db, 'lmdbinsurancecertificate', 10), 'Certificate cannot grant access to private contract');
$dao->setSharingsByElement('lmdbinsurancecontract', 10, array(2));
$dao->setSharingsByElement('lmdbinsurancecertificate', 10, array());
checkSharing(!LmdbVehicleSharing::visible($db, 'lmdbinsurancecertificate', 10), 'Shared contract does not share certificates');
$dao->setSharingsByElement('lmdbvehicle', 10, array(2));
foreach ($definitions as $element => $definition) {
	$dao->setSharingsByElement($element, 10, array(2));
	checkSharing(LmdbVehicleSharing::visible($db, $element, 10), $element.' restored');
	$dao->setSharingsByElement($element, 10, array());
	checkSharing(!LmdbVehicleSharing::visible($db, $element, 10), $element.' revoked on next query');
	$dao->setSharingsByElement($element, 10, array(2));
	$conf->global->{'MULTICOMPANY_'.strtoupper($element).'_SHARE_ALL_BY_DEFAULT'} = 1;
	checkSharing(LmdbVehicleSharing::visible($db, $element, 10) === ($element === 'lmdbvehiclequartix'), $element.' exclusion mode never broadens QUARTIX');
	$conf->global->{'MULTICOMPANY_'.strtoupper($element).'_SHARE_ALL_BY_DEFAULT'} = 0;
}
$conf->entity = 1;
foreach ($definitions as $element => $definition) {
	$record = new SharingRecord($db); $record->element = $element; $record->table_element = $definition['table']; $record->TRIGGER_PREFIX = strtoupper($element); $record->fetch(10);
	checkSharing($record->setSharingEntities($user, array(2)) > 0 && $record->triggers === 0, $element.' unchanged grants do not trigger');
	checkSharing($record->setSharingEntities($user, array(99)) < 0, $element.' forged destination rejected');
	$record->failTrigger = true;
	checkSharing($record->setSharingEntities($user, array()) < 0 && LmdbVehicleSharing::stored($db, $element, 10) === array(2), $element.' trigger failure rolls back');
	$record->failTrigger = false; $db->fail = 'DELETE FROM';
	checkSharing($record->setSharingEntities($user, array()) < 0, $element.' DAO failure rolls back');
	$db->fail = '';
	checkSharing($record->setSharingEntities($user, array()) > 0 && $record->triggers === 1 && $record->context['trigger_reason'] === 'sharing_change', $element.' one effective UPDATE');
	$dao->setSharingsByElement($element, 10, array(2));
	$conf->entity = 2; checkSharing($record->setSharingEntities($user, array()) < 0, $element.' beneficiary cannot administer sharing'); $conf->entity = 1;
	$user->admin = 0; checkSharing($record->setSharingEntities($user, array()) < 0, $element.' standard reader cannot administer sharing'); $user->admin = 1;
	if ($element === 'lmdbvehiclequartix') continue; // Selection-only family, exercised separately below.
	$conf->global->{'MULTICOMPANY_'.strtoupper($element).'_SHARE_ALL_BY_DEFAULT'} = 1;
	checkSharing($record->setSharingEntities($user, array(2)) > 0 && LmdbVehicleSharing::stored($db, $element, 10) === array(3), $element.' native exclusion storage matches selected beneficiaries');
	$conf->entity = 2; checkSharing(LmdbVehicleSharing::visible($db, $element, 10), $element.' selected destination reads in exclusion mode');
	$conf->entity = 3; checkSharing(!LmdbVehicleSharing::visible($db, $element, 10), $element.' excluded destination cannot read'); $conf->entity = 1;
	$conf->global->{'MULTICOMPANY_'.strtoupper($element).'_SHARING_BYELEMENT_ENABLED'} = 0;
	checkSharing(LmdbVehicleSharing::stored($db, $element, 10) === array(3), $element.' disabling preserves exclusions');
	$conf->global->{'MULTICOMPANY_'.strtoupper($element).'_SHARING_BYELEMENT_ENABLED'} = 1;
	$db->fail = 'INSERT INTO';
	checkSharing($record->setSharingEntities($user, array()) < 0 && LmdbVehicleSharing::stored($db, $element, 10) === array(3), $element.' failed insertion restores deleted exclusions');
	$db->fail = '';
	checkSharing($record->setSharingEntities($user, array()) > 0 && LmdbVehicleSharing::stored($db, $element, 10) === array(2, 3), $element.' revoke all selected destinations in exclusion mode');
	$conf->global->{'MULTICOMPANY_'.strtoupper($element).'_SHARE_ALL_BY_DEFAULT'} = 0;
	$dao->setSharingsByElement($element, 10, array(2));
}
$record->element = 'lmdbvehicleevent'; $record->table_element = $definitions['lmdbvehicleevent']['table'];
checkSharing($record->setSharingEntities($user, array(3)) < 0 && LmdbVehicleSharing::stored($db, 'lmdbvehicleevent', 10) === array(2), 'Parent absent at destination rolls back entire grant');
foreach (array(0, 1, 2) as $admin) {
	$user->admin = $admin; $user->rights = array();
	checkSharing(!LmdbVehicleSharing::can($user), 'No implicit administrative elevation');
	$user->socid = 42; checkSharing(!LmdbVehicleSharing::can($user), 'External users never elevated'); $user->socid = 0;
}
$user->admin = 0; $user->rights['lmdbvehiclemanagement.read'] = 1;
checkSharing(LmdbVehicleSharing::can($user) && !LmdbVehicleSharing::can($user, 'consumption', 'write'), 'Read permission does not grant write');
$user->admin = 1;
DaoMulticompany::$fixtures[2]['sharings']['lmdbvehicleevent'] = array();
unset(DaoMulticompany::$fixtures[3]['sharings']['lmdbvehicleevent']);
$db->query("INSERT INTO ".MAIN_DB_PREFIX."const (entity,name,value) VALUES (0,'MULTICOMPANY_LMDBVEHICLE_SHARING_ENABLED','1'),(0,'MULTICOMPANY_LMDBVEHICLEEVENT_SHARING_ENABLED','0')");
$db->query("INSERT INTO ".MAIN_DB_PREFIX."const (entity,name,value) VALUES (0,'MULTICOMPANY_LMDBVEHICLEASSIGNMENT_SHARING_ENABLED','')");
checkSharing(LmdbVehicleSharingMigration::run($db) > 0 && LmdbVehicleSharingMigration::run($db) > 0, 'Configuration migration replay');
checkSharing(DaoMulticompany::$fixtures[2]['sharings']['lmdbvehicleevent'] === array() && DaoMulticompany::$fixtures[3]['sharings']['lmdbvehicleevent'] === array(1), 'Empty scope preserved and missing scope inherited');
checkSharing($db->query("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name='MULTICOMPANY_LMDBVEHICLEEVENT_SHARING_ENABLED'")->fetchColumn() === '0', 'Disabled constant preserved');
checkSharing($db->query("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name='MULTICOMPANY_LMDBVEHICLEASSIGNMENT_SHARING_ENABLED'")->fetchColumn() === '', 'Empty constant preserved');
checkSharing((int) $db->query("SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."const WHERE name='MULTICOMPANY_LMDBINSURANCECONTRACT_SHARING_ENABLED'")->fetchColumn() === 1, 'Migration does not duplicate constants');
require_once dirname(__DIR__).'/lib/lmdbvehiclesharing.lib.php';
$_SESSION['token'] = 'valid';
foreach (array(array('GET', 'valid'), array('POST', ''), array('POST', 'forged')) as $request) {
	$_SERVER['REQUEST_METHOD'] = $request[0]; $_POST = array('action' => 'lmdb_save_sharing', 'token' => $request[1]);
	try { lmdbSharingAction($record); throw new LogicException('Missing CSRF rejection'); }
	catch (RuntimeException $e) { checkSharing($e->getMessage() === 'CSRF denied', 'Missing/forged token and GET writes denied'); }
}
$_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['SCRIPT_NAME'] = '/core/ajax/ajaxtooltip.php'; $_POST = array();
$hooks = new SharingHooksProbe($db); $hookmanager = null; $action = ''; $unused = null;
$object = new SharingRecord($db); $object->element = 'lmdbvehicle'; $object->table_element = $definitions['lmdbvehicle']['table']; $object->fetch(10);
$conf->entity = 2;
foreach (array(0, 1) as $admin) {
	$user->admin = $admin; $user->rights = array();
	checkSharing($hooks->restrictedArea(array('features' => 'lmdbvehiclemanagement', 'objectid' => 10), $unused, $action, $hookmanager) === 0, 'Tooltip refuses missing read rights even for administrators');
	$user->rights['lmdbvehiclemanagement.read'] = 1;
	checkSharing($hooks->restrictedArea(array('features' => 'lmdbvehiclemanagement', 'objectid' => 10), $unused, $action, $hookmanager) === 1, 'Read-only tooltip of shared vehicle');
}
$dao->setSharingsByElement('lmdbvehicle', 10, array());
checkSharing($hooks->restrictedArea(array('features' => 'lmdbvehiclemanagement', 'objectid' => 10), $unused, $action, $hookmanager) === 0, 'Revoked tooltip never bypasses sharing');
checkSharing($hooks->hookGetEntity(array('element' => 'lmdbvehiclemanagement_vehicle', 'shared' => 1), $object, $action, $hookmanager) === 1 && $hooks->resprints === '2,1', 'Native physical table maps to sharing element');
checkSharing($hooks->hookGetEntity(array('element' => 'product'), $object, $action, $hookmanager) === 0, 'Unrelated native objects untouched');
$conf->entity = 1; $user->admin = 1;
if (!empty($argv[2])) {
	require_once dirname(__DIR__).'/class/lmdbvehiclesharingform.class.php';
	$langs = new class { public function trans($key) { return $key; } public function loadLangs($files) {} };
	foreach ($definitions as $element => $definition) {
		foreach (array(0, 1) as $exclusion) {
			$conf->global->{'MULTICOMPANY_'.strtoupper($element).'_SHARE_ALL_BY_DEFAULT'} = $exclusion;
			$dao->setSharingsByElement($element, 10, array(2));
			foreach (array(2, 3) as $entity) DaoMulticompany::$fixtures[$entity]['sharings'][$element] = array(1);
			$form = new LmdbVehicleSharingForm($db); $form->sharingObjectId = 10;
			$html = $form->formSelectSharingByElement((object) array('id' => 0, 'element' => $element), $element);
			$dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
			$selected = $xpath->query('//select[@name="'.$element.'_to[]"]/option');
			checkSharing($selected->length === 1 && (int) $selected->item(0)->getAttribute('value') === ($exclusion && $element !== 'lmdbvehiclequartix' ? 3 : 2), 'Native selector preserves effective selection '.$element);
			checkSharing(strpos($html, 'checkIfElementIsUsed') === false, 'External object avoids unsupported core class loader');
		}
	}
	require __DIR__.'/sharing_form_cases.php';
}
require __DIR__.'/quartix_sharing_cases.php';
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'element_contact (rowid integer, element_id integer, fk_c_type_contact integer)');
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'c_type_contact (rowid integer, element text)');
foreach ($definitions as $element => $definition) {
	$record = new SharingRecord($db); $record->element = $element; $record->table_element = $definition['table']; $record->TRIGGER_PREFIX = strtoupper($element); $record->fetch(11);
	$dao->setSharingsByElement($element, 11, array(2)); $record->failTrigger = true;
	checkSharing($record->delete($user) < 0 && LmdbVehicleSharing::stored($db, $element, 11) === array(2), 'Failed deletion preserves native associations '.$element);
	$record->failTrigger = false;
	$enabled['multicompany'] = false;
	checkSharing($record->delete($user) > 0 && LmdbVehicleSharing::stored($db, $element, 11) === array(), 'Business deletion cleans native associations '.$element);
	$enabled['multicompany'] = true;
}
$db->query('DROP TABLE '.MAIN_DB_PREFIX.'entity_element_sharing');
$enabled['multicompany'] = false;
$db->query('INSERT INTO '.MAIN_DB_PREFIX.$record->table_element." (rowid,entity,fk_vehicle,fk_contract,ref) VALUES (11,1,10,10,'REF-11')");
$record->fetch(11);
checkSharing($record->delete($user) > 0, 'Multicompany files without an installed native table do not prevent business deletion');
echo $checks." sharing checks passed (SQLite/API doubles; native Multicompany browser validation remains required)\n";
