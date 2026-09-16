<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Real module services and SQL, SQLite adapter and native environment doubles.
// Not a replacement for MySQL/MariaDB, document upload or browser acceptance tests.
define('LMDB_SHARING_FIXTURE_ONLY', true);
require __DIR__.'/run_sharing.php';
function dol_now() { return strtotime('2026-09-16 12:00:00 UTC'); }
function dol_mktime($h, $m, $s, $month, $day, $year) { return gmmktime($h, $m, $s, $month, $day, $year); }
function dol_time_plus_duree($date, $amount, $unit) { return strtotime('+'.$amount.($unit === 'm' ? ' months' : ' days'), $date); }
function dol_print_date($date, $format) { return gmdate(strtr($format, array('%Y'=>'Y','%m'=>'m','%d'=>'d')), $date); }
function price2num($value, $mode = '') { return (float) $value; }
class WorkflowDb extends SharingDb {
	public function query($sql) {
		$sql = str_replace("GROUP_CONCAT(DISTINCT energy_alias.rowid ORDER BY energy_alias.rowid SEPARATOR ',')", 'GROUP_CONCAT(DISTINCT energy_alias.rowid)', $sql);
		$sql = str_replace('CURRENT_DATE', "'2026-09-16'", $sql);
		$sql = str_replace(' ON DUPLICATE KEY UPDATE', ' ON CONFLICT(entity,fk_contract,fk_vehicle) DO UPDATE SET', $sql);
		$sql = preg_replace('/VALUES\((\w+)\)/', 'excluded.$1', $sql);
		$res = parent::query($sql);
		if (!$res) return false;
		return (object) array('rows' => $res->columnCount() ? $res->fetchAll(PDO::FETCH_OBJ) : array(), 'pos' => 0);
	}
	public function fetch_object($res) { return $res->rows[$res->pos++] ?? false; }
	public function num_rows($res) { return count($res->rows); }
	public function free($res) {}
	public function encrypt($value, $mode = 0) { return $value; }
	public function idate($date) { return gmdate('Y-m-d H:i:s', $date); }
	public function jdate($date) { return $date ? strtotime($date.' UTC') : 0; }
}
$checks = 0; $db = new WorkflowDb(); $user = new User();
$langs = new class { public function trans($key, ...$args) { return $key; } public function loadLangs($files) {} };
$conf->entity = 1;
$conf->global->MULTICOMPANY_SHARINGS_ENABLED = 1;
$conf->global->MULTICOMPANY_SHARING_BYELEMENT_ENABLED = 1;
$definitions = LmdbVehicleSharing::definitions();
foreach ($definitions as $element => $definition) {
	$conf->global->{'MULTICOMPANY_'.strtoupper($element).'_SHARING_ENABLED'} = 1;
	$conf->global->{'MULTICOMPANY_'.strtoupper($element).'_SHARING_BYELEMENT_ENABLED'} = 1;
	foreach (array(1,2,3) as $entity) DaoMulticompany::$fixtures[$entity]['sharings'][$element] = array_values(array_diff(array(1,2,3), array($entity)));
}
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'entity_element_sharing (entity integer, element text, fk_element integer)');
foreach (glob(dirname(__DIR__).'/sql/*.sql') as $path) {
	$sql = file_get_contents($path);
	if (strpos($sql, 'CREATE TABLE ') !== 0) continue;
	$sql = str_replace(array('llx_', 'AUTO_INCREMENT', ' ON UPDATE CURRENT_TIMESTAMP', ' ENGINE=innodb'), array(MAIN_DB_PREFIX, '', '', ''), $sql);
	$db->query($sql);
}
require_once dirname(__DIR__).'/class/lmdbvehicle.class.php';
require_once dirname(__DIR__).'/class/lmdbvehicleinsurancecontract.class.php';
require_once dirname(__DIR__).'/class/lmdbvehicleconsumption.class.php';
require_once dirname(__DIR__).'/class/lmdbvehiclequartixservice.class.php';
class ImportedWorkflowVehicle extends LmdbVehicle {
	public $triggerCount = 0;
	// Isolate the vehicle input validator/native persistence; keep the actual import,
	// create transaction, profile inference, requirement selection and date engine.
	protected function validateBusinessRules() { return 1; }
	public function createCommon($user, $notrigger = 0) {
		$date = $this->first_registration_date ? "'".$this->db->idate($this->first_registration_date)."'" : 'NULL';
		$this->db->query('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle (entity,ref,label,eu_category,first_registration_date,date_creation,fk_user_creat) VALUES ('.(int) $this->entity.", '".$this->db->escape($this->ref)."', 'Imported', '".$this->db->escape($this->eu_category)."', ".$date.", '2026-09-16', 1)");
		$this->id = (int) $this->db->pdo->lastInsertId();
		return $this->id;
	}
	public function call_trigger($code, $user) { $this->triggerCount++; return 1; }
}
function workflowRow($sql) { global $db; return $db->fetch_object($db->query($sql)); }
function workflowCount($table) { return (int) workflowRow('SELECT COUNT(*) AS n FROM '.MAIN_DB_PREFIX.$table)->n; }
$db->query('INSERT INTO '.MAIN_DB_PREFIX."c_lmdbvehiclemanagement_regulatory_profile (rowid,entity,code,label,active) VALUES (1,1,'ROAD_M1','M1',1)");
$db->query('INSERT INTO '.MAIN_DB_PREFIX."lmdbvehiclemanagement_regulatory_rule (rowid,entity,code,label,fk_control_type,calculator_code,initial_delay_months,recurrence_months,date_creation) VALUES (1,1,'FR_ROAD_LIGHT','Light',1,'road_light',48,24,'2026-09-16')");
$db->query('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule_profile (entity,fk_rule,fk_profile) VALUES (1,1,1)');
function importedWorkflowVehicle($registration, $category = 'M1') {
	global $db;
	$vehicle = new ImportedWorkflowVehicle($db); $vehicle->ref = 'IMPORT'; $vehicle->registration_number = 'IMPORT';
	$vehicle->eu_category = $category; $vehicle->first_registration_date = $registration ? strtotime($registration.' UTC') : null;
	return $vehicle;
}
$vehicle = importedWorkflowVehicle('2024-01-01');
$db->begin();
checkSharing($vehicle->saveFromImport($user, array(), false, 1) > 0, 'Simulation materializes requirements');
checkSharing(workflowCount('lmdbvehiclemanagement_control_requirement') === 1 && $vehicle->triggerCount === 0, 'Simulation calculates immediately without triggers');
$db->rollback();
checkSharing(workflowCount('lmdbvehiclemanagement_vehicle') === 0 && workflowCount('lmdbvehiclemanagement_control_requirement') === 0, 'Simulation rolls back vehicle, profile and requirement');
$vehicle = importedWorkflowVehicle('2024-01-01');
checkSharing($vehicle->saveFromImport($user, array(), false, 1) > 0, 'Fast import uses actual creation calculation');
$vehicleId = $vehicle->id;
$req = workflowRow('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement');
checkSharing(substr($req->retained_due_date,0,10) === '2028-01-01' && $req->fk_last_control === null, 'First deadline uses first registration without invented inspection');
$profile = workflowRow('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile');
checkSharing((int) $profile->confirmed === 0 && $profile->origin === 'deduced', 'Deduction does not confirm questionnaire');
$service = new LmdbVehicleRegulatoryService($db);
checkSharing($service->refreshSuggestedProfiles($vehicle, $user) > 0 && workflowCount('lmdbvehiclemanagement_control_requirement') === 1, 'Repeated import inference remains idempotent');
$db->query('UPDATE '.MAIN_DB_PREFIX."lmdbvehiclemanagement_vehicle_regulatory_profile SET origin='manual',confirmed=1");
$db->query('UPDATE '.MAIN_DB_PREFIX."lmdbvehiclemanagement_control_requirement SET derogation_until='2030-01-01',derogation_reason='Keep'");
checkSharing($service->synchronizeEntityRequirements(1, $user) > 0, 'Upgrade backfill executes without triggers');
checkSharing(workflowRow('SELECT origin FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile')->origin === 'manual' && workflowRow('SELECT derogation_reason FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement')->derogation_reason === 'Keep', 'Manual qualification and derogation survive backfill');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement SET derogation_until=NULL');
$missing = importedWorkflowVehicle(null);
checkSharing($missing->saveFromImport($user, array(), false, 1) > 0, 'Import without registration date accepted');
$incomplete = workflowRow('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement WHERE fk_vehicle='.$missing->id);
checkSharing($incomplete->retained_due_date === null && $incomplete->status === 'incomplete', 'Missing data never fabricates a date');
$old = importedWorkflowVehicle('2010-01-01'); $old->saveFromImport($user, array(), false, 1);
checkSharing(workflowRow('SELECT status FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement WHERE fk_vehicle='.$old->id)->status === 'overdue', 'Old first deadline stays overdue');
$unknown = importedWorkflowVehicle(null, ''); $unknown->saveFromImport($user, array(), false, 1);
checkSharing((int) workflowRow('SELECT COUNT(*) n FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement WHERE fk_vehicle='.$unknown->id)->n === 0, 'Unknown profile never creates a selectable fake requirement');
$before = workflowCount('lmdbvehiclemanagement_vehicle');
$db->fail = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement';
$failed = importedWorkflowVehicle('2024-01-01');
checkSharing($failed->saveFromImport($user, array(), false, 1) < 0, 'Requirement failure propagates to import');
checkSharing(workflowCount('lmdbvehiclemanagement_vehicle') === $before, 'Requirement failure rolls back vehicle'); $db->fail = '';

// Private foreign source changes the public deadline; source detail remains hidden.
$db->query('INSERT INTO '.MAIN_DB_PREFIX."lmdbvehiclemanagement_regulatory_control (rowid,entity,ref,fk_vehicle,fk_requirement,fk_rule,control_date,result_code,official_valid_until,status,date_creation,fk_user_creat) VALUES (99,2,'PRIVATE',".$vehicleId.','.$req->rowid.",1,'2026-09-01','critical','2027-01-01',1,'2026-09-16',1)");
$db->query('INSERT INTO '.MAIN_DB_PREFIX."c_lmdbvehiclemanagement_control_result (entity,code,label,requires_recheck,is_blocking) VALUES (1,'critical','Critical',1,1)");
checkSharing($service->recalculateVehicle($vehicleId, 2) > 0, 'Foreign control recalculates owner requirement');
$conf->global->MULTICOMPANY_LMDBVEHICLEREGULATORYCONTROL_SHARING_ENABLED = 0;
$readDue = function () use ($db, $req) {
	return workflowRow('SELECT req.retained_due_date, '.LmdbVehicleSharing::requirementStatusSql($db).' AS status, c.ref FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement req LEFT JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_control c ON c.rowid=req.fk_last_control AND '.LmdbVehicleSharing::sql($db,'lmdbvehicleregulatorycontrol','c').' WHERE req.rowid='.(int) $req->rowid.' AND '.LmdbVehicleSharing::requirementSql($db));
};
$due = $readDue();
checkSharing(substr($due->retained_due_date,0,10)==='2027-01-01' && $due->ref === null && $due->status === 'up_to_date', 'Private result hidden while official deadline remains visible');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_control SET status=2 WHERE rowid=99');
checkSharing($service->recalculateVehicle($vehicleId,2)>0 && substr($readDue()->retained_due_date,0,10)==='2028-01-01', 'Cancellation restores prior common calculation');
$conf->global->MULTICOMPANY_LMDBVEHICLEREGULATORYCONTROL_SHARING_ENABLED = 1;

// Hidden coverage links survive partial editing; forged inaccessible additions fail.
$db->query('CREATE UNIQUE INDEX workflow_coverage ON '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle(entity,fk_contract,fk_vehicle)');
$db->query('INSERT INTO '.MAIN_DB_PREFIX."lmdbvehiclemanagement_insurance_contract_vehicle (entity,fk_contract,fk_vehicle,date_start,date_creation,fk_user_creat) VALUES (1,50,".$vehicleId.",'2026-01-01','2026-01-01',1),(1,50,".$old->id.",'2026-01-01','2026-01-01',1)");
$dao = new DaoMulticompany($db); $dao->setSharingsByElement('lmdbvehicle',$vehicleId,array(2));
$conf->entity = 2;
$contract = new LmdbVehicleInsuranceContract($db); $contract->id=50; $contract->entity=1; $contract->date_start=strtotime('2026-01-01 UTC'); $contract->status=0;
checkSharing($contract->getVehicleIds()===array($vehicleId), 'Contract exposes only accessible covered vehicles');
$db->begin();
checkSharing($contract->replaceVehicleLinks(array($vehicleId),'primary',$contract->date_start,null,$user,1)>0, 'Partial coverage save accepts shared vehicle');
$db->commit();
checkSharing(workflowCount('lmdbvehiclemanagement_insurance_contract_vehicle')===2, 'Partial save preserves hidden link');
checkSharing($contract->replaceVehicleLinks(array($old->id),'primary',$contract->date_start,null,$user,1)<0, 'Forged hidden vehicle cannot be added or changed');

// Association visibility is entirely local and does not depend on collection active.
checkSharing(!LmdbVehicleQuartixService::hasAccessibleAssociation($db,$vehicleId), 'No QUARTIX association hides tabs');
$db->query('INSERT INTO '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_dataset (rowid,entity,snapshot_vehicle_label,fk_vehicle,date_creation,fk_user_creat) VALUES (90,1,'QX',".$vehicleId.",'2026-09-16',1)");
checkSharing(!LmdbVehicleQuartixService::hasAccessibleAssociation($db,$vehicleId), 'Archive without association hides tabs');
$db->query('INSERT INTO '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_link (entity,fk_vehicle,fk_quartix,remote_id,active,date_creation,timezone,shift_start,fk_user_creat) VALUES (1,".$vehicleId.",90,1,1,'2026-09-16','UTC','00:00',1)");
checkSharing(LmdbVehicleQuartixService::hasAccessibleAssociation($db,$vehicleId), 'Shared accessible association shows tabs');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link SET active=0');
checkSharing(LmdbVehicleQuartixService::hasAccessibleAssociation($db,$vehicleId), 'Suspended association still shows tabs');
$conf->global->MULTICOMPANY_LMDBVEHICLEQUARTIX_SHARING_ENABLED=0;
checkSharing(!LmdbVehicleQuartixService::hasAccessibleAssociation($db,$vehicleId), 'Private association hides tabs');
$conf->entity=1;
checkSharing(LmdbVehicleQuartixService::hasAccessibleAssociation($db,$vehicleId), 'Local association remains accessible');
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link');
checkSharing(!LmdbVehicleQuartixService::hasAccessibleAssociation($db,$vehicleId), 'Dissociation hides tabs despite archives');
require __DIR__.'/cross_entity_entry_cases.php';
// Exercise the actual upgrade entry point, including its transaction and marker.
require_once dirname(__DIR__).'/core/modules/modLmdbVehicleManagement.class.php';
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'const (rowid integer PRIMARY KEY, name text, value text, entity integer)');
$descriptor = (new ReflectionClass(modLmdbVehicleManagement::class))->newInstanceWithoutConstructor();
$descriptor->db = $db;
$upgrade = new ReflectionMethod($descriptor, 'initializeDeducedRequirements');
$upgrade->setAccessible(true);
$conf->entity = 1;
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement WHERE fk_vehicle='.(int) $vehicleId);
$beforeRequirements = workflowCount('lmdbvehiclemanagement_control_requirement');
$db->fail = 'INSERT INTO '.MAIN_DB_PREFIX.'const';
checkSharing($upgrade->invoke($descriptor, 1) < 0, 'Failed upgrade marker aborts migration');
checkSharing(workflowCount('lmdbvehiclemanagement_control_requirement') === $beforeRequirements && workflowCount('const') === 0, 'Upgrade failure rolls back backfill and marker together');
$db->fail = '';
checkSharing($upgrade->invoke($descriptor, 1) > 0 && workflowCount('lmdbvehiclemanagement_control_requirement') > $beforeRequirements, 'Upgrade backfills missing imported requirements');
$afterRequirements = workflowCount('lmdbvehiclemanagement_control_requirement');
checkSharing($upgrade->invoke($descriptor, 1) > 0 && workflowCount('const') === 1 && workflowCount('lmdbvehiclemanagement_control_requirement') === $afterRequirements, 'Completed upgrade does not duplicate requirements or marker');
echo $checks." cross-entity workflow checks passed (SQLite and native boundary doubles)\n";
