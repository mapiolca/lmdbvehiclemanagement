<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Exercise the actual vehicle import transaction method, with in-memory persistence.
function dol_include_once($path) {}
class User {}
class LmdbVehicleManagementObject extends stdClass
{
	public $db;
	public $context = array();
	public $error = '';
	public $errors = array();
	public function __construct($db) { $this->db = $db; }
}
require_once dirname(__DIR__).'/class/lmdbvehicle.class.php';
class CapacityImportDB
{
	public $depth = 0;
	public $state = array('vehicle' => 'original', 'capacities' => array(2 => 80.0, 10 => 20.0));
	public $snapshot = array();
	public $events = array();
	public function begin() { if ($this->depth++ === 0) $this->snapshot = $this->state; return 1; }
	public function commit() { $this->depth--; return 1; }
	public function rollback() { if (--$this->depth === 0) $this->state = $this->snapshot; return 1; }
}
class ImportedCapacityVehicle extends LmdbVehicle
{
	public $failWrite = false;
	public $failCapacity = false;
	public $failTrigger = false;
	public function create(User $user, $notrigger = 0) { return $this->write($notrigger); }
	public function update(User $user, $notrigger = 0) { return $this->write($notrigger); }
	private function write($notrigger)
	{
		if ($notrigger !== 1) throw new RuntimeException('Premature trigger');
		$this->db->state['vehicle'] = 'changed';
		return $this->failWrite ? -1 : 12;
	}
	public function saveCapacities(User $user, $capacities)
	{
		foreach ($capacities as $id => $value) {
			if ($value == 0) unset($this->db->state['capacities'][$id]);
			else $this->db->state['capacities'][$id] = $value;
		}
		return $this->failCapacity ? -1 : 1;
	}
	public function call_trigger($code, $user)
	{
		$this->db->events[] = array($code, $this->db->state['capacities']);
		return $this->failTrigger ? -1 : 1;
	}
}
function verify($value, $label) { if (!$value) throw new RuntimeException($label); echo "OK: $label\n"; }
$user = new User();
foreach (array(false, true) as $update) {
	$db = new CapacityImportDB(); $vehicle = new ImportedCapacityVehicle($db);
	$original = $db->state;
	$db->begin(); // Native simulation owns the outer transaction.
	verify($vehicle->saveFromImport($user, array(2 => 90.0), $update, 1) > 0, 'Simulation accepts vehicle and capacity');
	verify($db->depth === 1 && empty($db->events), 'Simulation retains outer transaction and emits no event');
	$db->rollback();
	verify($db->state === $original, 'Simulation rollback preserves vehicle and all capacities');
	$db->begin();
	verify($vehicle->saveFromImport($user, array(2 => 90.0), $update, 0) > 0, 'Final import succeeds');
	$db->commit();
	verify($db->state['capacities'] === array(2 => 90.0, 10 => 20.0), 'Unmapped capacities preserved');
	verify(count($db->events) === 1 && $db->events[0][0] === 'LMDBVEHICLEMANAGEMENT_VEHICLE_'.($update ? 'UPDATE' : 'CREATE') && $db->events[0][1][2] === 90.0, 'One CRUD event sees persisted capacities');
}
foreach (array('failWrite', 'failCapacity', 'failTrigger') as $failure) {
	$db = new CapacityImportDB(); $vehicle = new ImportedCapacityVehicle($db); $original = $db->state;
	$vehicle->{$failure} = true;
	verify($vehicle->saveFromImport($user, array(2 => 100.0), true, 0) < 0, 'Failure reported: '.$failure);
	verify($db->state === $original && $db->depth === 0, 'Atomic rollback: '.$failure);
}
$db = new CapacityImportDB(); $vehicle = new ImportedCapacityVehicle($db);
$vehicle->saveFromImport($user, array(2 => 0.0), true, 1);
verify($db->state['capacities'] === array(10 => 20.0) && empty($db->events), 'Explicit zero removes only selected capacity; fast mode suppresses triggers');
