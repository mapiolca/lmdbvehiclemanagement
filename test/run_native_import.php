<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Standalone boundary regression: native CSV/XLSX rows, without database writes.
class User
{
	public $admin = 0;
	public $socid = 0;
	public $allowed = false;
	public function hasRight(...$args) { return $this->allowed; }
}
class LmdbVehicle extends stdClass
{
	public static $last;
	public $context = array();
	public static function normalizeRegistrationNumber($value) { return strtoupper(trim($value)); }
	public function fetch($id) { $this->id = $id; $this->entity = 2; $this->status = 1; $this->label = 'Existing'; $this->brand = 'Original'; $this->fk_asset_type = 4; return 1; }
	public function update($user, $notrigger) { $this->notrigger = $notrigger; self::$last = $this; return 1; }
	public function __construct($db) {}
	public function create($user, $notrigger)
	{
		$this->notrigger = $notrigger;
		self::$last = $this;
		return 42;
	}
}
function dol_include_once($path) {}
function isModEnabled($module) { return $GLOBALS['moduleEnabled']; }
$moduleEnabled = true;
$conf = (object) array('entity' => 2);
$langs = new class {
	public function loadLangs($catalogues) {}
	public function trans($key) { return $key; }
};
require_once dirname(__DIR__).'/class/lmdbvehicleimport.class.php';
require_once dirname(__DIR__).'/class/actions_lmdbvehiclemanagement.class.php';
function check($condition, $name)
{
	if (!$condition) { throw new RuntimeException($name); }
	echo "OK: $name\n";
}
$user = new User();
$import = new LmdbVehicleImport(null);
$mapping = array(1 => 't.registration_number', 3 => 't.label', 2 => 't.vin', 4 => 't.seats');
$cells = array(array('val' => 'AA-123-BB'), array('val' => 'VIN123'), array('val' => 'Transit'), array('val' => '3'));
$import->createVehicleFromNativeRow($cells, $mapping, 'sample', $user, false);
$csv = clone LmdbVehicle::$last;
$import->createVehicleFromNativeRow(array_combine(range(1, 4), $cells), $mapping, 'sample', $user, false);
check(get_object_vars($csv) === get_object_vars(LmdbVehicle::$last), 'CSV and XLSX produce identical objects');
check($csv->registration_number === 'AA-123-BB' && $csv->vin === 'VIN123' && $csv->label === 'Transit' && $csv->seats === 3, 'Mapped data preserves each source column');
check($csv->entity === 2 && $csv->notrigger === 1, 'Simulation uses current entity and suppresses triggers');
check($import->getRegistrationReference($cells, array('t.registration_number' => 0), 0) === 'AA-123-BB', 'Computed reference accepts native target-to-position mapping');
$hooks = new ActionsLmdbVehicleManagement(null);
$object = null; $action = ''; $nbok = 0;
$params = array('datatoimport' => 'lmdbvehiclemanagement_vehicles', 'arrayrecord' => $cells, 'array_match_file_to_database' => $mapping, 'step' => 6, 'nbok' => &$nbok);
check($hooks->ImportInsert($params, $object, $action, null) === -1, 'Standard user without import permission denied');
$user->admin = 1;
check($hooks->ImportInsert($params, $object, $action, null) === 1 && $nbok === 1 && LmdbVehicle::$last->notrigger === 0, 'Admin real import calls business object with triggers');
$user->socid = 5;
check($hooks->ImportInsert($params, $object, $action, null) === -1, 'External user denied');
$user->socid = 0; $moduleEnabled = false;
check($hooks->ImportInsert($params, $object, $action, null) === -1, 'Disabled module denied');
$moduleEnabled = true;
$params['arrayrecord'] = array(array('val' => null));
check($hooks->ImportInsert($params, $object, $action, null) === 1 && $nbok === 1, 'Blank CSV record ignored without incrementing imported count');

class InvalidAssetImport extends LmdbVehicleImport
{
	public function fetchAssetType($id = 0, $code = '', $label = '') { return 0; }
}
$assetImport = new InvalidAssetImport(null);
$assetMapping = array(1 => 't.registration_number', 2 => 't.label', 3 => 't.fk_asset_type');
foreach (array(0, 1) as $base) {
	$row = array_combine(range($base, $base + 2), array(array('val' => 'AA-123-BB'), array('val' => 'Transit'), array('val' => '')));
	check($assetImport->createVehicleFromNativeRow($row, $assetMapping, '', $user, false) === 42, 'Empty asset type accepted with source base '.$base);
	$row[$base + 2]['val'] = 'UNKNOWN';
	check($assetImport->createVehicleFromNativeRow($row, $assetMapping, '', $user, false) === -1, 'Unknown asset type rejected with source base '.$base);
}

// The native summary reads obj->nbinsert, not nbok.
$user->admin = 1; $user->socid = 0; $moduleEnabled = true;
foreach (array(5, 6) as $step) {
	$driver = (object) array('nbinsert' => 0, 'nbupdate' => 0);
	$nbok = 0;
	$params = array('datatoimport' => 'lmdbvehiclemanagement_vehicles', 'obj' => $driver, 'arrayrecord' => $cells, 'array_match_file_to_database' => $mapping, 'step' => $step, 'nbok' => &$nbok);
	for ($i = 0; $i < 6; $i++) {
		check($hooks->ImportInsert($params, $object, $action, null) === 1, 'Successful row '.$i.' at step '.$step);
	}
	check($driver->nbinsert === 6 && $driver->nbupdate === 0 && $nbok === 6, 'Native summary reports six inserts at step '.$step);
	$params['arrayrecord'] = array(array('val' => null));
	$hooks->ImportInsert($params, $object, $action, null);
	check($driver->nbinsert === 6, 'Blank row does not increment native counter');
	$user->socid = 10;
	$hooks->ImportInsert($params, $object, $action, null);
	check($driver->nbinsert === 6, 'Denied row does not increment native counter');
	$user->socid = 0;
}

// Exercise the real resolver: a code supplied as the first argument must survive.
define('MAIN_DB_PREFIX', 'test_');
function getEntity($element) { return '2'; }
$db = new class {
	public $sql = '';
	public function escape($value) { return str_replace("'", "''", $value); }
	public function query($sql) { $this->sql = $sql; return true; }
	public function fetch_object($result) { return (object) array('rowid' => 12); }
	public function free($result) {}
};
$resolver = new LmdbVehicleImport($db);
foreach (array(array('light_commercial'), array(0, 'light_commercial'), array(0, '', 'Utilitaire léger')) as $args) {
	check($resolver->fetchAssetType(...$args) === 12, 'Dictionary resolver returns the matched id');
	$expected = $args[count($args) - 1];
	check(strpos($db->sql, "code = '".$expected."'") !== false, 'Dictionary lookup retains code or label');
	check(strpos($db->sql, 'active = 1 AND entity IN (2)') !== false, 'Dictionary lookup preserves activity and entity filters');
}
$resolver->fetchAssetType(12);
check(strpos($db->sql, 'AND rowid = 12') !== false, 'Numeric asset id still supported');
$row = array(1 => array('val' => 'AA-123-BB'), 2 => array('val' => 'Transit'), 3 => array('val' => 'light_commercial'));
check($resolver->createVehicleFromNativeRow($row, $assetMapping, '', $user, false) === 42 && LmdbVehicle::$last->fk_asset_type === 12, 'XLSX code resolves through real importer and resolver');

$updateDb = new class {
	public $queries = array();
	public $cursor = 0;
	public $matches = 1;
	public function escape($value) { return str_replace("'", "''", $value); }
	public function query($sql) { $this->queries[] = $sql; $this->cursor = 0; return true; }
	public function fetch_object($result) { return $this->cursor++ < $this->matches ? (object) array('rowid' => 12) : false; }
	public function free($result) {}
};
$service = new LmdbVehicleImport($updateDb);
$updateRow = array(1 => array('val' => 'AA-123-BB'), 2 => array('val' => 'Updated'), 3 => array('val' => ''));
$updateMapping = array(1 => 't.registration_number', 2 => 't.label', 3 => 't.brand');
check($service->createVehicleFromNativeRow($updateRow, $updateMapping, '', $user, false, array('t.registration_number')) === 1 && $service->updated, 'Existing vehicle updated in simulation');
check(LmdbVehicle::$last->label === 'Updated' && LmdbVehicle::$last->brand === 'Original' && LmdbVehicle::$last->status === 1 && LmdbVehicle::$last->fk_asset_type === 4 && LmdbVehicle::$last->notrigger === 1, 'Update preserves blanks, unmapped fields and status');
check(strpos($updateDb->queries[0], 'entity = 2') !== false, 'Update lookup restricted to current entity');
$updateHooks = new ActionsLmdbVehicleManagement($updateDb);
$driver = (object) array('nbinsert' => 0, 'nbupdate' => 0); $nbok = 0;
$params = array('datatoimport' => 'lmdbvehiclemanagement_vehicles', 'obj' => $driver, 'arrayrecord' => $updateRow, 'array_match_file_to_database' => $updateMapping, 'step' => 6, 'updatekeys' => array('t.registration_number'), 'nbok' => &$nbok);
check($updateHooks->ImportInsert($params, $object, $action, null) === 1 && $driver->nbupdate === 1 && $driver->nbinsert === 0 && LmdbVehicle::$last->notrigger === 0, 'Real update hook increments only update counter');
$updateDb->matches = 0;
check($service->createVehicleFromNativeRow($updateRow, $updateMapping, '', $user, false, array('t.registration_number')) === 42 && !$service->updated, 'Unmatched key inserts new vehicle');
$updateDb->matches = 2;
check($service->createVehicleFromNativeRow($updateRow, $updateMapping, '', $user, false, array('t.registration_number')) === -1, 'Ambiguous match rejected');
check($service->createVehicleFromNativeRow($updateRow, $updateMapping, '', $user, false, array('t.vin')) === -1, 'Unmapped update key rejected');
check($service->createVehicleFromNativeRow($updateRow, $updateMapping, '', $user, false, array('t.entity')) === -1, 'Unauthorized update key rejected');
