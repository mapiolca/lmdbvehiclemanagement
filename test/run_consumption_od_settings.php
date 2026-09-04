<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

// Load native classes without main.inc.php, credentials or database writes.
$coreRoot = isset($argv[1]) ? realpath($argv[1]) : false;
if ($coreRoot === false || !is_file($coreRoot.'/core/lib/functions.lib.php')) {
	fwrite(STDERR, "Usage: php test/run_consumption_od_settings.php <Dolibarr htdocs>".PHP_EOL);
	exit(2);
}
define('DOL_DOCUMENT_ROOT', str_replace('\\', '/', $coreRoot));
define('MAIN_DB_PREFIX', 'test_od_');
if (is_file(DOL_DOCUMENT_ROOT.'/version.inc.php')) {
	require_once DOL_DOCUMENT_ROOT.'/version.inc.php';
} else {
	// Earlier cores define the version in filefunc.inc.php, which also boots the application.
	$versionSource = file_get_contents(DOL_DOCUMENT_ROOT.'/filefunc.inc.php');
	if (!is_string($versionSource) || preg_match('/define\([\'"]DOL_VERSION[\'"],\s*[\'"]([^\'"]+)[\'"]\)/', $versionSource, $versionMatch) !== 1) {
		throw new RuntimeException('Cannot read Dolibarr version');
	}
	define('DOL_VERSION', $versionMatch[1]);
}

$conf = (object) array(
	'global' => (object) array('CHARTOFACCOUNTS' => 7),
	'entity' => 1,
	'modules' => array('bank' => 1),
	'file' => (object) array('dol_document_root' => array('main' => DOL_DOCUMENT_ROOT, 'alt0' => dirname(__DIR__, 2))),
);
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once dirname(__DIR__).'/class/lmdbvehicleconsumptionpayment.class.php';
require_once dirname(__DIR__).'/class/lmdbvehiclemanagementcompatibility.class.php';

/** Read-only database double: unexpected queries, including writes, fail immediately. */
final class ConsumptionOdSettingsDb
{
	/** @var list<array{pattern:string,count:int|false}> */
	public $expected = array();

	/** @param string $sql Query @return object|false */
	public function query($sql)
	{
		$expected = array_shift($this->expected);
		if ($expected === null || preg_match($expected['pattern'], $sql) !== 1) {
			throw new RuntimeException('Unexpected query: '.$sql);
		}
		return $expected['count'] === false ? false : (object) array('count' => $expected['count']);
	}

	/** @param object $result Result @return int */
	public function num_rows($result) { return $result->count; }
	/** @param object $result Result @return void */
	public function free($result) {}
	/** @param string $value Value @return string */
	public function escape($value) { return str_replace("'", "''", $value); }
	/** @return string */
	public function lasterror() { return 'TestDatabaseError'; }
}

$checks = 0;
/** @param array<string,int> $modules Enabled modules @return void */
function setOdTestModules($modules)
{
	global $conf;
	$conf->modules = $modules;
	// Supply both configuration representations used by native isModEnabled() in v20+.
	foreach (array('bank', 'accounting', 'comptabilite') as $module) {
		$conf->{$module} = (object) array('enabled' => isset($modules[$module]) ? $modules[$module] : 0);
	}
}

/** @param bool $condition Assertion @param string $label Scenario @return void */
function checkOdSettings($condition, $label)
{
	global $checks;
	if (!$condition) throw new RuntimeException($label);
	$checks++;
}

$db = new ConsumptionOdSettingsDb();
$service = new LmdbVehicleConsumptionPayment($db);

/** @param string $pattern Required SQL @param int|false $count Result @return array{pattern:string,count:int|false} */
function odExpectedQuery($pattern, $count = 1)
{
	return array('pattern' => $pattern, 'count' => $count);
}

/** @param int $entity Entity @return list<array{pattern:string,count:int|false}> */
function odBankQueries($entity)
{
	return array(
		odExpectedQuery('/^SELECT rowid FROM test_od_bank_account WHERE rowid = 10 AND clos = 0 AND entity IN \('.$entity.'\)$/'),
		odExpectedQuery('/^SELECT id FROM test_od_c_paiement WHERE id = 20 AND active = 1 AND type IN \(1, 2, 3\) AND entity IN \('.$entity.'\)$/'),
	);
}

/** @param string $label Scenario @param string $general General @param string $aux Auxiliary @param int $result Expected result @param string $error Expected error @param int $bank Bank @param int $mode Payment mode @return void */
function validateOdSettings($label, $general, $aux, $result, $error = '', $bank = 10, $mode = 20)
{
	global $conf, $db, $service;
	$before = serialize($conf->global);
	$actual = $service->validateConfiguration($bank, $mode, $general, $aux, (int) $conf->entity);
	checkOdSettings($actual === $result && $service->error === $error, $label.' result');
	checkOdSettings($db->expected === array(), $label.' expected database reads');
	checkOdSettings(serialize($conf->global) === $before, $label.' preserves settings');
}

setOdTestModules(array());
validateOdSettings('Bank disabled', '', '', -1, 'ConsumptionOdRequiresBankModule');
foreach (array(array('bank' => 1), array('bank' => 1, 'comptabilite' => 1)) as $modules) {
	setOdTestModules($modules);
	foreach (array(1, 2) as $entity) {
		$conf->entity = $entity;
		foreach (array(array('', ''), array('LIBRE-001', "AUX'O"), array('', 'AUX-ONLY'), array(str_repeat('É', 32), str_repeat('A', 32))) as $accounts) {
			$db->expected = odBankQueries($entity);
			validateOdSettings('Optional free accounts', $accounts[0], $accounts[1], 1);
		}
		$db->expected = array(odExpectedQuery('/clos = 0 AND entity IN \('.$entity.'\)$/', 0));
		validateOdSettings('Closed or inaccessible bank', '', '', -1, 'ConsumptionOdBankAccountInvalid');
	}
}

$conf->entity = 1;
foreach (array(array('bank' => 1), array('bank' => 1, 'comptabilite' => 1), array('bank' => 1, 'accounting' => 1)) as $modules) {
	setOdTestModules($modules);
	validateOdSettings('General too long', str_repeat('É', 33), '', -1, 'ConsumptionOdAccountingAccountTooLong');
	validateOdSettings('Auxiliary too long', '6061', str_repeat('A', 33), -1, 'ConsumptionOdSubledgerAccountTooLong');
	validateOdSettings('Bank required', '6061', '', -1, 'ConsumptionOdConfigurationIncomplete', 0);
	validateOdSettings('Payment mode required', '6061', '', -1, 'ConsumptionOdConfigurationIncomplete', 10, 0);
	$db->expected = odBankQueries(1);
	$db->expected[1]['count'] = 0;
	validateOdSettings('Invalid outgoing mode', '6061', '', -1, 'ConsumptionOdPaymentModeInvalid');
}

setOdTestModules(array('bank' => 1, 'accounting' => 1));
validateOdSettings('Double entry requires general', '  ', '', -1, 'ConsumptionOdConfigurationIncomplete');
$conf->global->CHARTOFACCOUNTS = 0;
$db->expected = odBankQueries(1);
validateOdSettings('Chart required', '6061', '', -1, 'ConsumptionOdAccountingAccountInvalid');
$conf->global->CHARTOFACCOUNTS = 7;
foreach (array(1, 2) as $entity) {
	$conf->entity = $entity;
	$generalQuery = odExpectedQuery("/WHERE aa.entity = ".$entity." AND aa.active = 1 AND aa.account_number = '6061' AND ast.rowid = 7$/");
	$db->expected = array_merge(odBankQueries($entity), array($generalQuery));
	validateOdSettings('Double entry optional auxiliary', ' 6061 ', ' ', 1);
	$db->expected = array_merge(odBankQueries($entity), array(array_merge($generalQuery, array('count' => 0))));
	validateOdSettings('Inactive or wrong entity/chart general', '6061', '', -1, 'ConsumptionOdAccountingAccountInvalid');
	foreach (array(0, 1) as $auxExists) {
		$auxQuery = odExpectedQuery("/^SELECT code FROM \(SELECT accountancy_code AS code FROM test_od_user WHERE accountancy_code = 'AUX''O' AND entity IN \(0,".$entity."\) UNION SELECT code_compta AS code FROM test_od_societe WHERE code_compta = 'AUX''O' AND entity IN \(".$entity."\) UNION SELECT code_compta_fournisseur AS code FROM test_od_societe WHERE code_compta_fournisseur = 'AUX''O' AND entity IN \(".$entity."\)\) AS aux LIMIT 1$/", $auxExists);
		$db->expected = array_merge(odBankQueries($entity), array($generalQuery, $auxQuery));
		validateOdSettings('Optional auxiliary checked when provided', '6061', "AUX'O", $auxExists ? 1 : -1, $auxExists ? '' : 'ConsumptionOdSubledgerAccountInvalid');
	}
}

// Configuration changes must not reinterpret or rewrite the stored values.
$conf->entity = 1;
$conf->global->LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_ACCOUNTING_ACCOUNT = 'LIBRE-001';
$conf->global->LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_SUBLEDGER_ACCOUNT = '';
setOdTestModules(array('bank' => 1, 'comptabilite' => 1));
$db->expected = odBankQueries(1);
validateOdSettings('Before double entry activation', 'LIBRE-001', '', 1);
setOdTestModules(array('bank' => 1, 'accounting' => 1));
$db->expected = array_merge(odBankQueries(1), array(odExpectedQuery("/aa.account_number = 'LIBRE-001' AND ast.rowid = 7$/", 0)));
validateOdSettings('After double entry activation', 'LIBRE-001', '', -1, 'ConsumptionOdAccountingAccountInvalid');
setOdTestModules(array('bank' => 1));
$db->expected = odBankQueries(1);
validateOdSettings('After accounting deactivation', 'LIBRE-001', '', 1);
$db->expected = array(odExpectedQuery('/^SELECT rowid FROM test_od_bank_account/', false));
validateOdSettings('Database error propagated', '', '', -1, 'TestDatabaseError');

$expectedAvailable = function_exists('finfo_open') && function_exists('imagecreatefromjpeg') && function_exists('imagecreatefrompng') && function_exists('imageflip');
foreach (array(array('bank' => 1), array('bank' => 1, 'comptabilite' => 1), array('bank' => 1, 'accounting' => 1)) as $modules) {
	setOdTestModules($modules);
	$feature = LmdbVehicleManagementCompatibility::getCompatibilityFeatures()['consumption_various_payment'];
	checkOdSettings($feature['available'] === $expectedAvailable, 'Compatibility independent of accounting mode');
	checkOdSettings($feature['reason'] !== 'RequiresAccountingModule', 'No accounting dependency warning');
}
setOdTestModules(array());
$feature = LmdbVehicleManagementCompatibility::getCompatibilityFeatures()['consumption_various_payment'];
checkOdSettings(!$feature['available'] && $feature['reason'] === 'RequiresBankModule', 'Bank remains required');

/** Native payment double used to retain historical accounting locks in every mode. */
final class ConsumptionOdLockPayment extends PaymentVarious
{
	/** @var int */
	public $testAccounted = 0;
	/** @param int $mode Mode @return int */
	public function getVentilExportCompta($mode = 0) { return $this->testAccounted; }
}

final class ConsumptionOdLockService extends LmdbVehicleConsumptionPayment
{
	/** @var PaymentVarious */
	public $testPayment;
	/** @param LmdbVehicleConsumption $consumption Consumption @return PaymentVarious */
	public function fetchPayment($consumption) { return $this->testPayment; }
}

$paymentReflection = new ReflectionClass(ConsumptionOdLockPayment::class);
$payment = $paymentReflection->newInstanceWithoutConstructor();
$lockService = new ConsumptionOdLockService($db);
$lockService->testPayment = $payment;
$consumption = new LmdbVehicleConsumption($db);
$consumption->fk_payment_various = 50;
$consumption->entity = 1;
foreach (array(array('bank' => 1), array('bank' => 1, 'comptabilite' => 1), array('bank' => 1, 'accounting' => 1)) as $modules) {
	setOdTestModules($modules);
	$payment->rappro = 0;
	$payment->testAccounted = 1;
	checkOdSettings($lockService->isLocked($consumption) === 1, 'Historical accounting lock retained');
	$payment->testAccounted = 0;
	$payment->rappro = 1;
	checkOdSettings($lockService->isLocked($consumption) === 1, 'Reconciliation lock retained');
	$payment->rappro = 0;
	checkOdSettings($lockService->isLocked($consumption) === 0, 'Unaccounted unreconciled OD editable');
}
$conf->entity = 2;
checkOdSettings($lockService->isLocked($consumption) === 1, 'Other owner entity remains locked');
checkOdSettings($db->expected === array(), 'No unexpected remaining reads');

print $checks.' consumption OD settings checks passed (native classes '.DOL_VERSION.')'.PHP_EOL;
