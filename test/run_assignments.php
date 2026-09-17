<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Real assignment methods, native Form/Select2, native Multicompany verifyRight.
// SQLite and User/persistence doubles; no ERP access or external side effects.
$core = isset($argv[1]) ? realpath($argv[1]) : false;
$multicompanyRoot = isset($argv[2]) ? realpath($argv[2]) : false;
if (!$core || !$multicompanyRoot) {
	fwrite(STDERR, "Usage: php -d extension=pdo_sqlite test/run_assignments.php <Dolibarr htdocs> <Multicompany root> [--fixture]\n");
	exit(2);
}
define('DOL_DOCUMENT_ROOT', $core);
define('DOL_URL_ROOT', '');
if (is_file($core.'/version.inc.php')) require_once $core.'/version.inc.php';
else {
	if (!preg_match('/define\([\'"]DOL_VERSION[\'"],\s*[\'"]([^\'"]+)[\'"]\)/', file_get_contents($core.'/filefunc.inc.php'), $versionMatch)) {
		fwrite(STDERR, "Cannot read the native Dolibarr version.\n"); exit(2);
	}
	define('DOL_VERSION', $versionMatch[1]);
}
define('MAIN_DB_PREFIX', 'assignment_test_');
date_default_timezone_set('Europe/Paris');
$conf = (object) array('entity' => 2, 'use_javascript_ajax' => 1, 'global' => (object) array('MULTICOMPANY_TRANSVERSE_MODE' => 1, 'MAIN_USE_JQUERY_MULTISELECT' => 1));
$enabled = true;
$shared = true;
function isModEnabled($name) { global $enabled; return $name !== 'multicompany' || $enabled; }
function getDolGlobalInt($key, $default = 0) { global $conf; return (int) ($conf->global->$key ?? $default); }
function getDolGlobalString($key, $default = '') { global $conf; return (string) ($conf->global->$key ?? $default); }
function getEntity($element, $share = 1, $object = null) {
	global $conf, $shared;
	if ($element === 'user') return getDolGlobalInt('MULTICOMPANY_TRANSVERSE_MODE') ? '0,1' : '0,'.$conf->entity;
	if ($element === 'usergroup') return '0,'.$conf->entity;
	if ($element === 'lmdbvehicle' && $shared) return '1,'.$conf->entity;
	return (string) $conf->entity;
}
function dol_include_once($path) {
	global $multicompanyRoot;
	if (strpos($path, '/lmdbvehiclemanagement/') === 0) require_once dirname(__DIR__).substr($path, strlen('/lmdbvehiclemanagement'));
	elseif (strpos($path, '/multicompany/') === 0) require_once $multicompanyRoot.substr($path, strlen('/multicompany'));
}
function dol_now() { return strtotime('2026-09-17 12:00:00 Europe/Paris'); }
function dol_getdate($time, $fast = false, $tz = '') { return getdate($time); }
function dol_mktime($h, $m, $s, $month, $day, $year, $tz = '') { return mktime($h, $m, $s, $month, $day, $year); }
function dol_syslog($message, $level = 0) {}
function getNonce() { return 'fixture'; }
function dol_escape_htmltag($value, ...$args) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function dol_escape_js($value) { return addslashes((string) $value); }
function dol_print_error($db) { throw new RuntimeException($db->lasterror()); }
$langs = new class { public function trans($key, ...$args) { return $key; } public function transnoentities($key, ...$args) { return $key; } };
$hookmanager = new class { public function executeHooks(...$args) { return 0; } };
$action = 'create';
class AssignmentDb {
	public $pdo, $fail = '', $error = '', $depth = 0;
	public function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
	public function prefix() { return MAIN_DB_PREFIX; }
	public function query($sql) {
		if ($this->fail !== '' && strpos($sql, $this->fail) !== false) { $this->error = 'Simulated SQL error'; return false; }
		$sql = str_replace(array(' FOR UPDATE', 'NOW()'), array('', "'2026-09-17 12:00:00'"), $sql);
		$res = $this->pdo->query($sql);
		return (object) array('rows' => $res->columnCount() ? $res->fetchAll(PDO::FETCH_OBJ) : array(), 'pos' => 0);
	}
	public function fetch_object($res) { return $res->rows[$res->pos++] ?? false; }
	public function num_rows($res) { return count($res->rows); }
	public function free($res) {}
	public function sanitize($value) { return $value; }
	public function escape($value) { return str_replace("'", "''", $value); }
	public function lasterror() { return $this->error; }
	public function idate($time) { return date('Y-m-d H:i:s', $time); }
	public function begin() { if ($this->depth++ === 0) $this->pdo->beginTransaction(); }
	public function commit() { if (--$this->depth === 0) $this->pdo->commit(); }
	public function rollback() { $this->depth = 0; if ($this->pdo->inTransaction()) $this->pdo->rollBack(); }
}
#[AllowDynamicProperties]
class User {
	public $id = 0, $admin = 0, $entity = 0, $socid = 0, $statut = 1, $error = '', $firstname = '', $lastname = '';
	private $db;
	public function __construct($db) { $this->db = $db; }
	public function fetch($id) {
		$res = $this->db->query('SELECT * FROM '.MAIN_DB_PREFIX.'user WHERE rowid='.(int) $id);
		if (!$res) { $this->error = $this->db->lasterror(); return -1; }
		$row = $this->db->fetch_object($res);
		if (!$row) return 0;
		foreach (get_object_vars($row) as $key => $value) $this->$key = $value;
		$this->id = (int) $row->rowid;
		return 1;
	}
	public function getFullName(...$args) { return $this->firstname.' '.$this->lastname; }
	public function getNomUrl(...$args) { return ''; }
}
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once $multicompanyRoot.'/class/dao_multicompany.class.php';
require_once dirname(__DIR__).'/class/lmdbvehicleassignment.class.php';
class AssignmentProbe extends LmdbVehicleAssignment {
	public static $events = array();
	public function createCommon(User $user, $notrigger = 0) {
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element.' (entity,fk_vehicle,fk_user_driver,date_start,date_end,status,is_primary) VALUES ('.(int) $this->entity.','.(int) $this->fk_vehicle.','.(int) $this->fk_user_driver.", '".$this->db->idate($this->date_start)."', ".($this->date_end ? "'".$this->db->idate($this->date_end)."'" : 'NULL').','.(int) $this->status.','.(int) $this->is_primary.')';
		if (!$this->db->query($sql)) return -1;
		$this->id = (int) $this->db->pdo->lastInsertId();
		if (!$notrigger) self::$events[] = $this->TRIGGER_PREFIX.'_CREATE';
		return $this->id;
	}
	public function updateCommon(User $user, $notrigger = 0) {
		if (!$this->db->query('UPDATE '.MAIN_DB_PREFIX.$this->table_element.' SET fk_user_driver='.(int) $this->fk_user_driver.", date_start='".$this->db->idate($this->date_start)."', status=".(int) $this->status.' WHERE rowid='.(int) $this->id.' AND entity='.(int) $this->entity)) return -1;
		if (!$notrigger) self::$events[] = $this->TRIGGER_PREFIX.'_UPDATE';
		return 1;
	}
	public function fetchCommon($id = 0, $ref = null, $morewhere = '', $noextrafields = 0) {
		$res = $this->db->query('SELECT t.* FROM '.MAIN_DB_PREFIX.$this->table_element.' t WHERE t.rowid='.(int) $id.$morewhere);
		if (!$res) return -1;
		$row = $this->db->fetch_object($res);
		if (!$row) return 0;
		foreach (get_object_vars($row) as $key => $value) $this->$key = $value;
		$this->id = (int) $row->rowid;
		$this->date_start = strtotime($row->date_start);
		$this->date_end = $row->date_end ? strtotime($row->date_end) : null;
		return 1;
	}
}
$db = new AssignmentDb();
$user = new User($db); $user->id = 1;
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'user (rowid integer PRIMARY KEY, entity integer, statut integer, admin integer, login text, firstname text, lastname text, gender text, photo text)');
$db->query("INSERT INTO ".MAIN_DB_PREFIX."user VALUES (1,0,1,1,'root','Global','Admin','',''),(2,1,1,0,'local','Allowed','Driver','',''),(3,1,1,0,'foreign','Foreign','Driver','',''),(4,1,0,0,'inactive','Inactive','Driver','',''),(5,2,1,1,'admin2','Local','Admin','',''),(6,2,1,0,'local2','Local','Driver','','')");
$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'user ADD employee integer DEFAULT 1');
$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'user ADD fk_soc integer DEFAULT NULL');
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'usergroup_user (rowid integer PRIMARY KEY, entity integer, fk_user integer)');
$db->query('INSERT INTO '.MAIN_DB_PREFIX.'usergroup_user VALUES (1,2,2),(2,3,3),(3,2,4),(4,2,5),(5,1,2),(6,1,3)');
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle (rowid integer PRIMARY KEY, entity integer)');
$db->query('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle VALUES (10,1)');
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_assignment (rowid integer PRIMARY KEY, entity integer, fk_vehicle integer, fk_user_driver integer, date_start text, date_end text, status integer, is_primary integer)');
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement (rowid integer PRIMARY KEY, entity integer, fk_vehicle integer, active integer, status text, blocking_mode text, derogation_until text)');
$db->query("INSERT INTO ".MAIN_DB_PREFIX."lmdbvehiclemanagement_control_requirement VALUES (1,1,10,1,'overdue','both',NULL)");
$checks = 0;
function checkAssignment($ok, $message) { global $checks; $checks++; if (!$ok) throw new RuntimeException($message); }
function newAssignment($start = '2026-01-12 17:27:00', $driver = 2) {
	global $db;
	$record = new AssignmentProbe($db); $record->fk_vehicle = 10; $record->fk_user_driver = $driver; $record->date_start = strtotime($start); return $record;
}
$historical = newAssignment(); $historical->entity = 3;
checkAssignment($historical->create($user) > 0 && $historical->entity === 2 && $historical->date_end === null, 'Historical ongoing assignment saved in entry entity despite current regulatory block');
checkAssignment(AssignmentProbe::$events === array('LMDBVEHICLEMANAGEMENT_ASSIGNMENT_CREATE'), 'One CRUD create at persistence boundary');
foreach (array('2026-09-17 00:00:00', '2026-09-17 11:59:00', '2026-09-17 13:00:00', '2026-09-18 00:00:00') as $start) {
	$record = newAssignment($start);
	checkAssignment($record->create($user) < 0 && $record->error === 'VehicleRegulatoryAssignmentBlocked' && $db->depth === 0, 'Today and future assignments remain blocked: '.$start);
}
checkAssignment(newAssignment('2026-09-16 23:59:59')->create($user) > 0, 'Previous local calendar day is historical');
$historical->reason = 'Correction';
checkAssignment($historical->update($user) > 0 && $historical->entity === 2, 'Historical update keeps owner');
$historical->date_start = dol_now();
checkAssignment($historical->update($user) < 0 && $historical->error === 'VehicleRegulatoryAssignmentBlocked', 'Moving historical start to today rechecks blocking');
foreach (array(3,4,999) as $driver) {
	$record = newAssignment('2026-01-12', $driver);
	checkAssignment($record->create($user) < 0 && $record->error === 'InvalidDriver', 'Foreign/inactive/missing driver refused despite historical date: '.$driver);
}
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'usergroup_user WHERE entity=2 AND fk_user=2');
$historical->date_start = strtotime('2026-01-12');
checkAssignment($historical->update($user) < 0 && $historical->error === 'InvalidDriver', 'Removed membership immediately prevents update');
$db->query('INSERT INTO '.MAIN_DB_PREFIX.'usergroup_user VALUES (1,2,2)');
$db->fail = 'FROM '.MAIN_DB_PREFIX.'usergroup_user';
checkAssignment(newAssignment()->create($user) < 0, 'Membership query error fails closed'); $db->fail = '';
$record = newAssignment(); $record->date_end = strtotime('2025-12-01');
checkAssignment($record->create($user) < 0 && $record->error === 'AssignmentDateRangeInvalid', 'Historical date does not bypass invalid period');
$record = newAssignment(); $record->date_start = 0;
checkAssignment($record->create($user) < 0 && $record->error === 'AssignmentDateRangeInvalid', 'Missing start refused');
$shared = false;
checkAssignment(newAssignment()->create($user) < 0, 'Withdrawn vehicle access prevents historical entry'); $shared = true;
$db->query("UPDATE ".MAIN_DB_PREFIX."lmdbvehiclemanagement_control_requirement SET status='up_to_date'");
checkAssignment(newAssignment('2026-09-17')->create($user) > 0, 'Current compliant assignment accepted');
$db->query("UPDATE ".MAIN_DB_PREFIX."lmdbvehiclemanagement_control_requirement SET status='overdue'");
$record = newAssignment('2026-09-17'); $record->status = 0;
checkAssignment($record->create($user) > 0, 'Inactive assignment remains recordable');
$primary = newAssignment(); $primary->is_primary = 1;
checkAssignment($primary->create($user) > 0, 'First historical primary assignment accepted');
$overlap = newAssignment(); $overlap->is_primary = 1;
checkAssignment($overlap->create($user) < 0 && $overlap->error === 'PrimaryAssignmentOverlap', 'Historical primary overlap still rejected');
$db->fail = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_assignment';
checkAssignment(newAssignment()->create($user) < 0 && $db->depth === 0, 'Persistence failure rolls back'); $db->fail = '';
$db->fail = 'FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement';
checkAssignment(newAssignment('2026-09-17')->create($user) < 0 && $db->depth === 0, 'Regulatory query failure rejects current assignment'); $db->fail = '';
$enabled = false; $conf->global->MULTICOMPANY_TRANSVERSE_MODE = 0;
checkAssignment(newAssignment('2026-01-12', 6)->create($user) > 0, 'Without Multicompany local active user accepted');
checkAssignment(newAssignment('2026-01-12', 3)->create($user) < 0, 'Without Multicompany foreign user refused');
$enabled = true;

// Execute the actual selector preparation and driver row with native Form/DAO.
$source = file_get_contents(dirname(__DIR__).'/vehicle_assignment.php');
$start = strpos($source, "\t// Keep native user discovery");
$end = strpos($source, "\tprint '<form class=", $start);
$preparation = substr($source, $start, $end - $start);
$line = preg_grep('/titlefieldcreate fieldrequired.*select_dolusers/', explode("\n", $source));
$render = implode("\n", $line);
$form = new Form($db);
$fixtures = array();
foreach (array('transverse' => array(2,1,array(2,5)), 'root_entity' => array(1,1,array(1,2,3)), 'isolated' => array(2,0,array(1,5,6))) as $name => $case) {
	list($conf->entity, $conf->global->MULTICOMPANY_TRANSVERSE_MODE, $expected) = $case;
	$user->admin = 1;
	$assignment = newAssignment(); $assignment->fk_user_driver = 0;
	eval($preparation);
	$actual = array_values(array_diff($driverIds, array(0))); sort($actual);
	checkAssignment($actual === $expected, 'Native selector eligible IDs: '.$name);
	ob_start(); eval($render); $fixtures[$name] = '<table>'.ob_get_clean().'</table>';
	checkAssignment(strpos($fixtures[$name], '.select2(') !== false, 'Native Select2 enabled: '.$name);
}
$conf->entity = 2; $conf->global->MULTICOMPANY_TRANSVERSE_MODE = 1;
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'usergroup_user WHERE entity=2');
$assignment->fk_user_driver = 3;
eval($preparation);
checkAssignment($driverIds === array(0), 'Empty eligible set retains restrictive sentinel');
ob_start(); eval($render); $fixtures['empty'] = ob_get_clean();
checkAssignment(strpos($fixtures['empty'], 'Foreign') === false && strpos($fixtures['empty'], 'disabled') !== false, 'Forged selection cannot repopulate empty selector');
checkAssignment(strpos($source, 'name="token" value="\'.newToken()') !== false && strpos($source, "hasRight('lmdbvehiclemanagement', 'assignment', 'write')") !== false, 'Native token and functional right retained');
checkAssignment($db->depth === 0, 'All assignment transactions closed');
if (in_array('--fixture', $argv, true)) echo json_encode($fixtures, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
else echo $checks." assignment checks passed (SQLite; native Form, dates and Multicompany DAO; persistence/User doubles)\n";
