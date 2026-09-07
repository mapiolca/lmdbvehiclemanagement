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
