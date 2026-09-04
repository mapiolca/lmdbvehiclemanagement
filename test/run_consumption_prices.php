<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

// Native PHP behavior with query/graph doubles; no connection to a business database.
$coreRoot = isset($argv[1]) ? realpath($argv[1]) : false;
if ($coreRoot === false || !is_file($coreRoot.'/core/lib/functions.lib.php')) {
	fwrite(STDERR, 'Usage: php test/run_consumption_prices.php <Dolibarr htdocs>'.PHP_EOL);
	exit(2);
}
define('DOL_DOCUMENT_ROOT', str_replace('\\', '/', $coreRoot));
define('DOL_URL_ROOT', '');
define('MAIN_DB_PREFIX', 'test_prices_');
if (is_file(DOL_DOCUMENT_ROOT.'/version.inc.php')) {
	require_once DOL_DOCUMENT_ROOT.'/version.inc.php';
} else {
	$source = file_get_contents(DOL_DOCUMENT_ROOT.'/filefunc.inc.php');
	if (!is_string($source) || preg_match('/define\([\'"]DOL_VERSION[\'"],\s*[\'"]([^\'"]+)[\'"]\)/', $source, $match) !== 1) {
		throw new RuntimeException('Cannot read Dolibarr version');
	}
	define('DOL_VERSION', $match[1]);
}
$conf = (object) array(
	'global' => (object) array('MAIN_MAX_DECIMALS_TOT' => 2, 'MAIN_MAX_DECIMALS_UNIT' => 5),
	'entity' => 1, 'currency' => 'EUR', 'modules' => array(),
	'file' => (object) array('dol_document_root' => array('main' => DOL_DOCUMENT_ROOT, 'alt0' => dirname(__DIR__, 2))),
	'lmdbvehiclemanagement' => (object) array('dir_temp' => __DIR__.'/price-graph-test-'.getmypid()),
);
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
$langs = new Translate(DOL_DOCUMENT_ROOT, $conf);
$langs->setDefaultLang('en_US');
$langs->load('main');
require_once dirname(__DIR__).'/class/lmdbvehicleconsumption.class.php';
require_once dirname(__DIR__).'/class/lmdbvehicleconsumptionstats.class.php';
require_once dirname(__DIR__).'/class/lmdbvehicleconsumptionimport.class.php';
require_once dirname(__DIR__).'/core/modules/modLmdbVehicleManagement.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/export/export_csvutf8.modules.php';

/** Reject unexpected SQL, including any unplanned write. */
final class ConsumptionPriceDb
{
	/** @var list<array{pattern:string,rows:list<object>|false}> */ public $expected = array();
	/** @param string $pattern SQL expression @param list<object>|false $rows Result @return void */
	public function expect($pattern, $rows) { $this->expected[] = array('pattern' => $pattern, 'rows' => $rows); }
	/** @param string $sql Query @return object|false */
	public function query($sql)
	{
		$item = array_shift($this->expected);
		if ($item === null || preg_match($item['pattern'], $sql) !== 1) throw new RuntimeException('Unexpected SQL: '.$sql);
		return $item['rows'] === false ? false : (object) array('rows' => $item['rows'], 'position' => 0);
	}
	/** @param object $result Result @return object|false */
	public function fetch_object($result) { return $result->rows[$result->position++] ?? false; }
	/** @param object $result Result @return int */
	public function num_rows($result) { return count($result->rows); }
	/** @param object $result Result @return void */
	public function free($result) {}
	/** @param string $value Input @return string */
	public function escape($value) { return str_replace("'", "''", $value); }
	/** @param string $value Date @return int */
	public function jdate($value) { return (int) strtotime($value); }
	/** @return string */
	public function lasterror() { return 'TestDatabaseError'; }
}

/** Capture only the series passed by the real renderer; no image file is written. */
final class DolGraph
{
	/** @var list<array{0:string,1:string|float}> */ public static $data = array();
	/** @param list<array{0:string,1:string|float}> $data Series @return void */
	public function SetData($data) { self::$data = $data; }
	/** @param string $axis Axis @param string $default Default @return string */
	public static function getDefaultGraphSizeForStats($axis, $default) { return $default; }
	/** @param string $name Setter/rendering method @param array<int,mixed> $args Arguments @return string */
	public function __call($name, $args) { return ''; }
}

/** Exercise the shared UPDATE context independently of odometer/payment persistence. */
final class ConsumptionPriceUpdateProbe extends LmdbVehicleManagementObject
{
	/** @var float|string|null */ public static $storedPrice;
	/** @var float|string|null */ public $total_ttc;
	/** @var array<string,array<string,int|string>> */
	public $fields = array('total_ttc' => array('type' => 'double(24,8)', 'notnull' => 0));
	/** @inheritdoc */
	public function fetch($id, $ref = null) { $this->id = $id; $this->entity = 1; $this->total_ttc = self::$storedPrice; return 1; }
	/** @inheritdoc */
	protected function validateBusinessRules() { return 1; }
	/** @inheritdoc */
	public function updateCommon(User $user, $notrigger = 0) { return 1; }
	/** @inheritdoc */
	public function LibStatut($status, $mode = 0) { return ''; }
	/** @inheritdoc */
	protected function getCardPage() { return ''; }
}

$checks = 0;
/** @param bool $condition Expected behavior @param string $label Scenario @return void */
function checkPrice($condition, $label)
{
	global $checks;
	if (!$condition) throw new RuntimeException($label);
	$checks++;
}

/** @param float|null $amount Price @param float $quantity Quantity @param int $day Day @return array<string,int|float|string|null> */
function priceRow($amount, $quantity = 5.0, $day = 0)
{
	return array('entity' => 1, 'vehicle_id' => 1, 'vehicle_ref' => 'TEST', 'registration_number' => 'TEST',
		'consumable_id' => 1, 'consumable_label' => 'Additive', 'category' => 'additive', 'unit' => 'L', 'currency' => 'EUR',
		'quantity' => $quantity, 'total_ttc' => $amount, 'date' => 1700000000 + $day * 86400,
		'odometer_km' => 10000.0 + $day * 100, 'reading_kind' => 'standard', 'capacity' => 20.0, 'wltp_range_km' => null, 'oil_reference' => '');
}

$db = new ConsumptionPriceDb();
$stats = new LmdbVehicleConsumptionStats($db);
$rows = array(priceRow(25.0, 10.0), priceRow(null, 5.0, 1));
$group = $stats->summarize($rows)['1:1:1:L:EUR'];
checkPrice($group['total_quantity'] === 15.0 && $group['weighted_unit_price'] === 2.5, 'Unknown price does not dilute weighted mean');
$rows[] = priceRow(0.0, 5.0, 2);
$group = $stats->summarize($rows)['1:1:1:L:EUR'];
checkPrice($group['total_quantity'] === 20.0 && $group['priced_quantity'] === 15.0 && $group['weighted_unit_price'] === 25.0 / 15, 'Zero price contributes its quantity');
checkPrice($group['total_cost'] === 25.0 && $group['peak_unit_price'] === 2.5, 'Cost and peak use known prices');
checkPrice($group['count'] === 3 && $group['interval_count'] === 2 && $group['average_days'] === 1.0 && $group['interval_quantity'] === 10.0, 'Unknown price keeps counts, frequency and distance quantities');
foreach (array(null, 0.0) as $amount) {
	$all = $stats->summarize(array(priceRow($amount), priceRow($amount, 5.0, 1)))['1:1:1:L:EUR'];
	checkPrice($all['weighted_unit_price'] === $amount && $all['total_cost'] === $amount && $all['peak_unit_price'] === $amount, 'All unknown and all zero remain distinct');
	checkPrice($all['total_quantity'] === 10.0 && $all['count'] === 2, 'All rows retain quantities');
}
$separate = array(priceRow(null), array_replace(priceRow(10.0), array('entity' => 2)), array_replace(priceRow(20.0), array('currency' => 'USD')));
$groups = $stats->summarize($separate);
checkPrice(count($groups) === 3 && $groups['1:1:1:L:EUR']['total_cost'] === null && $groups['2:1:1:L:EUR']['total_cost'] === 10.0 && $groups['1:1:1:L:USD']['total_cost'] === 20.0, 'Entity and currency groups stay separate');

$fetchedRows = array();
foreach (array(null, '0', '25') as $amount) {
	$fetchedRows[] = (object) array('rowid' => 1, 'entity' => 1, 'ref' => 'TEST', 'fk_vehicle' => 1, 'fk_consumable' => 2,
		'category_snapshot' => 'additive', 'unit_snapshot' => 'L', 'fk_user_driver' => null, 'quantity' => '5',
		'total_ttc' => $amount, 'currency_snapshot' => 'EUR', 'oil_reference' => null, 'reading_date' => '2026-09-04 09:00:00',
		'odometer_km' => '10000', 'reading_kind' => 'standard', 'consumable_code' => 'ADBLUE', 'consumable_label' => 'AdBlue',
		'vehicle_ref' => 'TEST', 'registration_number' => 'TEST', 'vehicle_label' => 'Test', 'wltp_range_km' => null,
		'capacity' => null, 'driver_firstname' => null, 'driver_lastname' => null, 'driver_login' => null);
}
// A requested entity filter is intersected with the accessible scope, never substituted for it.
$db->expect('/^SELECT .*r.entity = t.entity.*v.entity = t.entity.*cap.entity = t.entity.*WHERE t.entity IN \(1\) AND t.fk_vehicle = 1 AND t.category_snapshot = \'additive\' AND t.entity IN \(1,2\) ORDER BY /', $fetchedRows);
$loaded = $stats->fetchRows(array('vehicle_id' => 1, 'category' => 'additive', 'entity_ids' => array(1, 2)));
checkPrice(is_array($loaded) && $loaded[0]['total_ttc'] === null && $loaded[1]['total_ttc'] === 0.0 && $loaded[2]['total_ttc'] === 25.0, 'SQL loading preserves unknown, zero and positive price');

try {
	lmdbVehicleConsumptionRenderGraph($rows, 'unit_price', 'Price', 'prices-test');
	checkPrice(count(DolGraph::$data) === 2 && (float) DolGraph::$data[0][1] === 2.5 && (float) DolGraph::$data[1][1] === 0.0, 'Price graph skips unknown and keeps zero');
	lmdbVehicleConsumptionRenderGraph($rows, 'quantity', 'Quantity', 'quantities-test');
	checkPrice(count(DolGraph::$data) === 3, 'Quantity graph keeps every refill');
	lmdbVehicleConsumptionRenderGraph($rows, 'consumption_100', 'Consumption', 'consumption-test');
	checkPrice(count(DolGraph::$data) === 2, 'Consumption graph keeps intervals with unknown price');
	DolGraph::$data = array();
	lmdbVehicleConsumptionRenderGraph(array(priceRow(null)), 'unit_price', 'Price', 'unknown-test');
	checkPrice(DolGraph::$data === array(), 'No point for an entirely unknown group');
} finally {
	// The graph double writes no files. Remove only this test's empty directories.
	if (is_dir($conf->lmdbvehiclemanagement->dir_temp.'/consumption')) rmdir($conf->lmdbvehiclemanagement->dir_temp.'/consumption');
	if (is_dir($conf->lmdbvehiclemanagement->dir_temp)) rmdir($conf->lmdbvehiclemanagement->dir_temp);
}

$validate = new ReflectionMethod(LmdbVehicleConsumption::class, 'validateBusinessRules');
$validate->setAccessible(true);
$save = new ReflectionMethod(CommonObject::class, 'setSaveQuery');
$save->setAccessible(true);
$consumable = (object) array('rowid' => 2, 'entity' => 1, 'code' => 'ADBLUE', 'label' => 'AdBlue', 'category' => 'additive', 'unit' => 'L', 'requires_oil_reference' => 0, 'active' => 1);
foreach (array('additive', 'fuel') as $category) {
	foreach (array(null, '', '  ', 0.0, '0', '25.129', -2.0) as $input) {
		$object = new LmdbVehicleConsumption($db);
		$object->fk_vehicle = 1;
		$object->fk_consumable = 2;
		$object->quantity = 10.0;
		$object->total_ttc = $input;
		$object->category_snapshot = $category === 'fuel' ? 'additive' : 'fuel'; // Ignore submitted category.
		$consumable->category = $category;
		$db->expect('/^SELECT entity .*WHERE rowid = 1 AND entity IN \(1\)$/', array((object) array('entity' => 1)));
		$db->expect('/^SELECT rowid, entity, code, label, category, unit, requires_oil_reference, active .*WHERE rowid = 2 AND entity IN \(1\)$/', array(clone $consumable));
		if ($category === 'fuel') $db->expect('/^SELECT 1 .*v.entity = 1.*ce.entity IN \(1\) LIMIT 1$/', array((object) array('found' => 1)));
		$result = $validate->invoke($object);
		checkPrice($db->expected === array(), 'All validation queries checked for entity');
		if ($input === -2.0) {
			checkPrice($result === -1 && $object->error === 'ConsumptionTotalCannotBeNegative', 'Negative amount refused');
			continue;
		}
		$expected = $category === 'additive' && ($input === null || trim((string) $input) === '') ? null : (float) price2num($input, 'MT');
		checkPrice($result === 1 && $object->total_ttc === $expected && $object->category_snapshot === $category, 'Price normalized using authoritative category');
		checkPrice($object->getUnitPrice() === ($expected === null ? null : (float) price2num($expected / 10, 'MU')), 'Unit price preserves absence');
		// Exercise the native serialization and fetch conversion with the actual field metadata.
		$object->fields = array('total_ttc' => $object->fields['total_ttc']);
		checkPrice($save->invoke($object)['total_ttc'] === $expected, 'CommonObject persists NULL separately from zero');
		$fetched = (object) array('total_ttc' => $expected === null ? null : (string) $expected);
		$object->setVarsFromFetchObj($fetched);
		checkPrice($object->total_ttc === $expected, 'CommonObject fetch preserves NULL and zero');
	}
}

$object = new LmdbVehicleConsumption($db);
$object->entity = 1;
$object->fk_vehicle = 1;
$db->expect('/^SELECT entity .*entity IN \(1\)$/', array((object) array('entity' => 2)));
checkPrice($validate->invoke($object) === -1 && $object->error === 'CannotMoveObjectBetweenEntities', 'Cross-entity consumption refused before price handling');

$conf->global->MAIN_MAX_DECIMALS_TOT = 3;
$conf->global->MAIN_MAX_DECIMALS_UNIT = 2;
$object = new LmdbVehicleConsumption($db);
$object->quantity = 7.0;
$object->fk_vehicle = 1;
$object->fk_consumable = 2;
$object->total_ttc = '25.1239';
$consumable->category = 'additive';
$db->expect('/^SELECT entity .*entity IN \(1\)$/', array((object) array('entity' => 1)));
$db->expect('/^SELECT rowid, entity, code, label, category, unit, requires_oil_reference, active .*entity IN \(1\)$/', array(clone $consumable));
checkPrice($validate->invoke($object) === 1 && $object->total_ttc === (float) price2num('25.1239', 'MT') && $object->total_ttc === 25.124, 'Total follows native precision setting');
checkPrice($object->getUnitPrice() === (float) price2num(25.124 / 7, 'MU') && $object->getUnitPrice() === 3.59, 'Unit price follows native precision setting');
$conf->global->MAIN_MAX_DECIMALS_TOT = 2;
$conf->global->MAIN_MAX_DECIMALS_UNIT = 5;

$user = new User($db);
foreach (array(array(null, 0.0, true), array(0.0, null, true), array(25.0, null, true), array(null, null, false), array('0', 0.0, false)) as $case) {
	ConsumptionPriceUpdateProbe::$storedPrice = $case[0];
	$probe = new ConsumptionPriceUpdateProbe($db);
	$probe->id = 1;
	$probe->total_ttc = $case[1];
	checkPrice($probe->update($user) === 1 && in_array('total_ttc', $probe->context['changed_fields'], true) === $case[2], 'UPDATE detects clearing and zero/NULL transitions without numeric-string noise');
}

$import = new LmdbVehicleConsumptionImport($db);
$build = new ReflectionMethod(LmdbVehicleConsumptionImport::class, 'buildObject');
$build->setAccessible(true);
foreach (array('', '0', '25.129') as $input) {
	$db->expect('/^SELECT rowid .*registration_number = \'TEST\' AND entity = 1$/', array((object) array('rowid' => 1)));
	$db->expect('/^SELECT rowid, category .*active = 1 AND entity IN \(1\)$/', array((object) array('rowid' => 2, 'category' => 'additive')));
	$csv = str_getcsv('TEST;ADBLUE;2026-09-04 09:00;10000;5;'.$input, ';', '"', '');
	$row = array_combine(array('registration_number', 'consumable_code', 'reading_date', 'odometer_km', 'quantity', 'total_ttc'), $csv);
	$object = $build->invoke($import, $row);
	checkPrice($object instanceof LmdbVehicleConsumption && $object->total_ttc === ($input === '' ? null : (float) price2num($input, 'MT')), 'CSV input keeps empty/zero/known amount');
}

$export = new ExportCsvUtf8($db);
$export->handle = fopen('php://memory', 'w+');
if ($export->handle === false) throw new RuntimeException('Cannot open memory stream');
foreach (array(null, 0.0, 25.0) as $amount) {
	$export->write_record(array('t.total_ttc' => 'TotalTTC'), (object) array('t_total_ttc' => $amount), $langs, array('t.total_ttc' => 'Numeric'));
}
rewind($export->handle);
$emptyCsv = fgetcsv($export->handle, 0, ',', '"', '');
$zeroCsv = fgetcsv($export->handle, 0, ',', '"', '');
$knownCsv = fgetcsv($export->handle, 0, ',', '"', '');
fclose($export->handle);
checkPrice(is_array($emptyCsv) && is_array($zeroCsv) && is_array($knownCsv)
	&& $emptyCsv[0] === '' && $zeroCsv[0] === '0' && $knownCsv[0] === '25', 'Native CSV export preserves blank versus numeric zero');

$descriptor = (new ReflectionClass(modLmdbVehicleManagement::class))->newInstanceWithoutConstructor();
$descriptor->db = $db;
$migrate = new ReflectionMethod(modLmdbVehicleManagement::class, 'prepareConsumptionSchema');
$migrate->setAccessible(true);
foreach (array('NO', 'YES') as $nullable) {
	$db->expect('/^SELECT COUNT\(\*\) AS nb FROM information_schema.TABLES.*TABLE_NAME = \'test_prices_lmdbvehiclemanagement_consumption\'$/', array((object) array('nb' => 1)));
	foreach (array('fk_project', 'fk_payment_various') as $field) $db->expect('/^SELECT COUNT\(\*\) AS nb FROM information_schema.COLUMNS.*COLUMN_NAME = \''.$field.'\'$/', array((object) array('nb' => 1)));
	$db->expect('/^SELECT IS_NULLABLE FROM information_schema.COLUMNS.*COLUMN_NAME = \'total_ttc\'$/', array((object) array('IS_NULLABLE' => $nullable)));
	if ($nullable === 'NO') $db->expect('/^ALTER TABLE test_prices_lmdbvehiclemanagement_consumption MODIFY COLUMN total_ttc double\(24,8\) DEFAULT NULL$/', array());
	checkPrice($migrate->invoke($descriptor) === 1 && $db->expected === array(), 'Migration replays without updating rows or settings');
}
$db->expect('/^SELECT COUNT\(\*\) AS nb FROM information_schema.TABLES/', array((object) array('nb' => 0)));
checkPrice($migrate->invoke($descriptor) === 1, 'Fresh installation leaves table creation to SQL schema');
$db->expect('/^SELECT COUNT\(\*\) AS nb FROM information_schema.TABLES/', false);
checkPrice($migrate->invoke($descriptor) === -1 && $descriptor->error === 'TestDatabaseError', 'Migration reports metadata query failure');
checkPrice($db->expected === array(), 'All expected queries consumed');
print $checks.' consumption price checks passed'.PHP_EOL;
