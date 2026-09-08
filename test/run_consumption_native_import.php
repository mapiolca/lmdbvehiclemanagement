<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Actual importer and consumption orchestration; deterministic in-memory database/odometer doubles.
// This suite does not claim to exercise InnoDB locking or the browser upload UI.
define('MAIN_DB_PREFIX', 'test_');
function dol_include_once($path) {}
function isModEnabled($module) { return $GLOBALS['enabled']; }
function getEntity($element) { return '2'; }
function getDolGlobalString($key, $default = '') { return $default; }
function getDolGlobalInt($key, $default = 0) { return $GLOBALS['conf']->global->{$key} ?? $default; }
function price2num($value, $mode = '') { $v = (float) str_replace(',', '.', (string) $value); return $mode === 'MT' ? round($v, $GLOBALS['precision']) : $v; }
function dol_stringtotime($value, $mode = 0) { return strtotime($value.' UTC'); }
class User {
	public $id = 7; public $admin = 0; public $socid = 0;
	public $rightsGranted = array('read' => true, 'import' => true, 'write' => true);
	public function hasRight($module, $object, $action) { return $this->rightsGranted[$action] ?? false; }
}
class LmdbVehicleManagementObject extends stdClass {
	public $db; public $id = 0; public $entity = 2; public $context = array(); public $error = ''; public $errors = array();
	public function __construct($db) { $this->db = $db; }
	public function setVarsFromFetchObj($row) { foreach (get_object_vars($row) as $k => $v) $this->{$k === 'rowid' ? 'id' : $k} = $v; }
	public function fetch($id, $ref = null) {
		$row = $this->db->rows[$id] ?? null;
		if (!$row || $row->entity !== $GLOBALS['conf']->entity) return 0;
		$this->setVarsFromFetchObj($row); return 1;
	}
	public function validateField($fields, $field, $value) { return $field !== 'oil_reference' || strlen($value) <= 128; }
	public function getFieldError($field) { return 'Invalid '.$field; }
	public function create(User $user, $notrigger = 0) {
		$this->id = count($this->db->rows) + 1;
		if ($this->ref === '') $this->ref = 'C'.$this->id;
		return $this->persist($user, $notrigger, 'CREATE');
	}
	public function update(User $user, $notrigger = 0) { return $this->persist($user, $notrigger, 'UPDATE'); }
	private function persist($user, $notrigger, $verb) {
		if ($this->db->fail === 'consumption') { $this->error = 'WriteFailure'; return -1; }
		$row = (object) get_object_vars($this); unset($row->db); $row->rowid = $this->id; $row->fk_user_creat = $user->id;
		$this->db->rows[$this->id] = $row;
		if (!$notrigger) $this->db->events[] = 'CONSUMPTION_'.$verb;
		if ($this->db->fail === 'trigger' && !$notrigger) { $this->error = 'TriggerFailure'; return -1; }
		return $this->id;
	}
}
class LmdbVehicleConsumable {
	public $category; public $unit; public $active = 1; public $requires_oil_reference = 0;
	public function __construct($db) {}
	public function fetch($id) {
		$this->category = $id === 2 ? 'additive' : 'fuel'; $this->unit = $id === 3 ? 'kWh' : 'L';
		return in_array($id, array(1,2,3), true) ? 1 : 0;
	}
}
class LmdbVehicleOdometerReading extends stdClass {
	public $id = 0; public $db; public $error = ''; public $errors = array();
	public function __construct($db) { $this->db = $db; }
	public function fetch($id) {
		if (!isset($this->db->readings[$id])) return 0;
		foreach (get_object_vars($this->db->readings[$id]) as $k => $v) $this->{$k} = $v;
		return 1;
	}
	public function createFromConsumption($user, $notrigger = 0) { $this->id = count($this->db->readings) + 1; return $this->save($notrigger, 'CREATE'); }
	public function updateFromConsumption($user, $notrigger = 0) { return $this->save($notrigger, 'UPDATE'); }
	private function save($notrigger, $verb) {
		if ($this->db->fail === 'reading') { $this->error = 'ReadingFailure'; return -1; }
		$row = (object) get_object_vars($this); unset($row->db); $this->db->readings[$this->id] = $row;
		if (!$notrigger) $this->db->events[] = 'ODOMETER_'.$verb;
		return $this->id;
	}
}
$enabled = true; $precision = 2;
$conf = (object) array('entity' => 2, 'currency' => 'EUR');
$langs = new class { public function loadLangs($a) {} public function trans($s, ...$args) { return $s; } };
require_once dirname(__DIR__).'/class/lmdbvehicleconsumptionimport.class.php';
require_once dirname(__DIR__).'/class/actions_lmdbvehiclemanagement.class.php';
class ImportDb {
	public $rows = array(); public $readings = array(); public $events = array(); public $depth = 0; public $snapshots = array();
	public $fail = ''; public $ambiguous = false; public $queries = array();
	public $onVehicleLock;
	public function begin() { $this->snapshots[] = serialize(array($this->rows, $this->readings, $this->events)); $this->depth++; }
	public function commit() { array_pop($this->snapshots); $this->depth--; }
	public function rollback() { list($this->rows, $this->readings, $this->events) = unserialize(array_pop($this->snapshots)); $this->depth--; }
	public function escape($s) { return str_replace("'", "''", $s); }
	public function sanitize($s) { return $s; }
	public function idate($date) { return gmdate('Y-m-d H:i:s', $date); }
	public function jdate($date) { return strtotime($date.' UTC'); }
	public function lasterror() { return 'DatabaseFailure'; }
	public function num_rows($r) { return count($r->rows); }
	public function fetch_object($r) { return $r->rows[$r->pos++] ?? false; }
	public function free($r) {}
	public function query($sql) {
		$this->queries[] = $sql;
		if ($this->fail === 'sql') return false;
		if (strpos($sql, 'FOR UPDATE') !== false && $this->depth < 1) throw new RuntimeException('Lock outside transaction');
		if ($this->onVehicleLock && strpos($sql, 'FROM test_lmdbvehiclemanagement_vehicle WHERE rowid') !== false && strpos($sql, 'FOR UPDATE') !== false) {
			$callback = $this->onVehicleLock; $this->onVehicleLock = null; $callback($this);
		}
		$rows = array();
		if (strpos($sql, 'SELECT t.rowid') === 0) {
			preg_match('/t.entity = (\d+).*t.fk_vehicle = (\d+).*t.fk_consumable = (\d+).*reading_date = \'([^\']+)\'/', $sql, $m);
			foreach ($this->rows as $id => $r) if ($r->entity == $m[1] && $r->fk_vehicle == $m[2] && $r->fk_consumable == $m[3] && $this->idate($this->readings[$r->fk_odometer_reading]->reading_date) === $m[4]) $rows[] = (object) array('rowid' => $id);
		} elseif (strpos($sql, 'FROM test_lmdbvehiclemanagement_consumption') !== false) {
			foreach ($this->rows as $id => $r) {
				$matches = preg_match('/ref = \'([^\']+)\'/', $sql, $m) ? $r->ref === $m[1] : (preg_match('/rowid = (\d+)/', $sql, $m) && $id == $m[1]);
				if ($matches && strpos($sql, 'entity = '.$r->entity) !== false) $rows[] = clone $r;
			}
		} elseif (strpos($sql, 'FROM test_lmdbvehiclemanagement_odometer_reading') !== false) {
			preg_match('/rowid = (\d+)/', $sql, $m);
			if (isset($this->readings[$m[1]])) {
				$r = clone $this->readings[$m[1]]; $r->rowid = (int) $m[1];
				if (strpos($sql, 'SELECT reading_date') === 0) $r->reading_date = $this->idate($r->reading_date);
				$rows[] = $r;
			}
		} elseif (strpos($sql, 'c_lmdbvehiclemanagement_consumable') !== false) {
			foreach (array('DIESEL' => 1, 'ADBLUE' => 2, 'ELECTRICITY' => 3) as $code => $id) if (strpos($sql, "code = '".$code."'") !== false) $rows[] = (object) array('rowid' => $id);
			if ($this->ambiguous && $rows) $rows[] = (object) array('rowid' => 4);
		} elseif (strpos($sql, 'FROM test_lmdbvehiclemanagement_vehicle') !== false) {
			if (strpos($sql, "'UNKNOWN'") === false && strpos($sql, 'entity = 3') === false) $rows[] = (object) array('rowid' => 1, 'entity' => 2);
		} elseif (strpos($sql, 'FROM test_user') !== false) {
			if (strpos($sql, "'UNKNOWN'") === false) $rows[] = (object) array('rowid' => 7);
		} else throw new RuntimeException('Unexpected SQL '.$sql);
		return (object) array('rows' => $rows, 'pos' => 0);
	}
}
function checkImport($ok, $label) { if (!$ok) throw new RuntimeException($label); $GLOBALS['checks']++; }
$checks = 0; $user = new User();
$mapping = array(1 => 't.fk_vehicle', 2 => 't.fk_consumable', 3 => 't.reading_date', 4 => 't.odometer_km', 5 => 't.quantity', 6 => 't.total_ttc', 7 => 't.description');
$keys = array('t.fk_vehicle', 't.reading_date', 't.fk_consumable');
$input = array('AA-123-BB', 'ADBLUE', '2026-09-08 12:00:00', '10000', '15', '', 'Original');
foreach (array(0, 1) as $base) {
	$db = new ImportDb(); $import = new LmdbVehicleConsumptionImport($db);
	$row = array_combine(range($base, $base + 6), array_map(static function ($v) { return array('val' => $v); }, $input));
	$db->begin();
	checkImport($import->importNativeRow($row, $mapping, 'sim', $user, false, $keys) > 0, 'CSV/XLSX simulation: '.$import->error);
	checkImport($db->depth === 1 && !$db->events && $db->rows[1]->total_ttc === null, 'Simulation keeps outer transaction, NULL and no child triggers');
	$db->rollback();
	checkImport(!$db->rows && !$db->readings, 'Simulation rolls back both objects');
	checkImport($import->importNativeRow($row, $mapping, 'real', $user, true, $keys) > 0, 'Create');
	checkImport($db->events === array('ODOMETER_CREATE','CONSUMPTION_CREATE'), 'One CRUD per object');
	$before = serialize(array($db->rows, $db->readings, $db->events));
	checkImport($import->importNativeRow($row, $mapping, 'again', $user, true, $keys) > 0 && $import->unchanged, 'Identical row ignored');
	checkImport($before === serialize(array($db->rows, $db->readings, $db->events)), 'No writes or events on identical row');
	$row[$base + 5]['val'] = '0'; $row[$base + 6]['val'] = '';
	checkImport($import->importNativeRow($row, $mapping, 'update', $user, false, $keys) > 0 && $import->updated, 'Zero update accepted');
	checkImport($db->rows[1]->total_ttc === 0.0 && $db->rows[1]->description === 'Original' && count($db->events) === 2, 'Zero, blank preservation and fast child triggers');
	$db->rows[1]->fk_payment_various = 50;
	checkImport($import->importNativeRow($row, $mapping, '', $user, true, $keys) > 0 && $import->unchanged, 'Identical OD-linked row ignored');
	$row[$base + 5]['val'] = '12';
	checkImport($import->importNativeRow($row, $mapping, '', $user, true, $keys) < 0 && $import->error === 'ConsumptionImportOdLinked', 'OD-linked update refused');
}
foreach (array('reading','consumption','trigger','sql') as $failure) {
	$db = new ImportDb(); $db->fail = $failure; $import = new LmdbVehicleConsumptionImport($db);
	checkImport($import->importNativeRow($input, $mapping, '', $user, true, $keys) < 0, 'Failure returned '.$failure);
	checkImport(!$db->rows && !$db->readings && !$db->events && $db->depth === 0, 'Atomic failure '.$failure);
}
foreach (array(array(0,'UNKNOWN'),array(1,'UNKNOWN'),array(2,'2026-02-30'),array(2,'2026-09-08 25:00'),array(3,''),array(3,'-1'),array(4,'1e4'),array(5,'12 EUR')) as $case) {
	$db = new ImportDb(); $import = new LmdbVehicleConsumptionImport($db); $row = $input; $row[$case[0]] = $case[1];
	checkImport($import->importNativeRow($row, $mapping, '', $user, false, $keys) < 0 && !$db->rows, 'Invalid field rejected '.implode(':',$case));
}
foreach (array('read','import','write') as $right) {
	$db = new ImportDb(); $import = new LmdbVehicleConsumptionImport($db); $user->admin = 1; $user->rightsGranted[$right] = false;
	checkImport($import->importNativeRow($input, $mapping, '', $user, false, $keys) < 0 && !$db->queries, 'Admin denied without '.$right);
	$user->rightsGranted[$right] = true;
}
$db = new ImportDb(); $import = new LmdbVehicleConsumptionImport($db);
$user->socid = 4;
checkImport($import->importNativeRow($input, $mapping, '', $user, false) < 0, 'External user denied');
$user->socid = 0; $enabled = false;
checkImport($import->importNativeRow($input, $mapping, '', $user, false) < 0, 'Disabled module denied');
$enabled = true;
checkImport($import->importNativeRow($input, $mapping, '', $user, false, array('t.fk_vehicle')) < 0, 'Partial key denied');
$db->ambiguous = true;
checkImport($import->importNativeRow($input, $mapping, '', $user, false, $keys) < 0 && $import->error === 'ConsumptionImportAmbiguous', 'Ambiguous code denied');
$db->ambiguous = false; $conf->entity = 3;
checkImport($import->importNativeRow($input, $mapping, '', $user, false, $keys) < 0 && !$db->rows, 'Other entity rejected');
$conf->entity = 2;
foreach (array(0, 1) as $odEnabled) {
	$conf->global = (object) array('LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_ENABLED' => $odEnabled);
foreach (array('DIESEL','ELECTRICITY','ADBLUE') as $code) {
	$db = new ImportDb(); $import = new LmdbVehicleConsumptionImport($db); $row = $input; $row[1] = $code; $row[5] = '25.129'; $precision = 3;
	checkImport($import->importNativeRow($row, $mapping, '', $user, false) > 0 && $db->rows[1]->total_ttc === 25.129 && empty($db->rows[1]->fk_payment_various), 'Historical native precision and no OD '.$code);
	checkImport($conf->global->LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_ENABLED === $odEnabled, 'OD configuration preserved');
}
}
$db = new ImportDb(); $hooks = new ActionsLmdbVehicleManagement($db); $driver = (object) array('nbinsert' => 0, 'nbupdate' => 0);
$nbok = 0; $obj = null; $action = '';
$params = array('datatoimport'=>'lmdbvehiclemanagement_consumptions','arrayrecord'=>$input,'array_match_file_to_database'=>$mapping,'updatekeys'=>$keys,'step'=>5,'obj'=>$driver,'nbok'=>&$nbok);
checkImport($hooks->ImportInsert($params, $obj, $action, null) === 1 && $nbok === 1 && $driver->nbinsert === 1, 'Native insert counter');
checkImport($hooks->ImportInsert($params, $obj, $action, null) === 1 && $nbok === 1 && $driver->nbinsert === 1, 'Native identical count unchanged');
$params['arrayrecord'][5] = '20';
checkImport($hooks->ImportInsert($params, $obj, $action, null) === 1 && $nbok === 2 && $driver->nbupdate === 1, 'Native update counter');
$refMap = array(1 => 't.ref', 2 => 't.total_ttc', 3 => 't.reading_date');
$import = new LmdbVehicleConsumptionImport($db);
checkImport($import->importNativeRow(array('C1', '30', '2026-09-09 13:00:00'), $refMap, '', $user, false, array('t.ref')) > 0, 'Reference updates key and preserves missing fields: '.$import->error);
checkImport($db->rows[1]->quantity === 15.0 && $db->readings[1]->reading_date === strtotime('2026-09-09 13:00:00 UTC'), 'Reference change updates owned reading');
checkImport($import->importNativeRow(array('C1', '30', ''), $refMap, '', $user, true, array('t.ref')) > 0 && $import->unchanged, 'Reference partial identical row ignored');
checkImport($import->importNativeRow(array('C1', '30', ''), $refMap, '', $user, true) < 0 && $import->error === 'ConsumptionImportDuplicate', 'Creation-only duplicate reference rejected');
$input[2] = '2026-09-09 13:00:00';
checkImport($import->importNativeRow($input, $mapping, '', $user, true) < 0 && $import->error === 'ConsumptionImportDuplicate', 'Creation-only tuple duplicate rejected');
$db->rows[2] = clone $db->rows[1]; $db->rows[2]->id = 2; $db->rows[2]->rowid = 2; $db->rows[2]->ref = 'C2';
checkImport($import->importNativeRow($input, $mapping, '', $user, false, $keys) < 0 && $import->error === 'ConsumptionImportAmbiguous', 'Ambiguous historical tuple rejected');
unset($db->rows[2]);
$user->rightsGranted['write'] = false;
$input[2] = '2026-09-10';
checkImport($import->importNativeRow($input, $mapping, '', $user, false) > 0, 'Read and import authorize creation-only without write');
$user->rightsGranted['write'] = true;
$mapped = array_merge($mapping, array(8 => 't.ref')); $row = $input; $row[] = 'C1';
checkImport($import->importNativeRow($row, $mapped, '', $user, false, $keys) < 0, 'Tuple mode cannot change keys through conflicting reference');
$db->onVehicleLock = static function ($database) { $database->rows[1]->description = 'Concurrent description'; };
$result = $import->importNativeRow(array('C1', '35', ''), $refMap, '', $user, false, array('t.ref'));
checkImport($result > 0 && $db->rows[1]->description === 'Concurrent description', 'Locked reload preserves concurrent unmapped changes: '.$import->error);
$db->onVehicleLock = static function ($database) { $database->rows[1]->fk_payment_various = 51; };
checkImport($import->importNativeRow(array('C1', '40', ''), $refMap, '', $user, false, array('t.ref')) < 0 && $import->error === 'ConsumptionImportOdLinked', 'Locked reload detects newly linked OD');
echo $checks." native consumption import checks passed\n";
