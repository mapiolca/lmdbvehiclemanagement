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
