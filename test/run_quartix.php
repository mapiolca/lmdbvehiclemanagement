<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

// Native objects + an in-memory SQL adapter. No business database or QWS credentials.
$coreRoot = isset($argv[1]) ? realpath($argv[1]) : false;
if (!$coreRoot || !is_file($coreRoot.'/core/lib/functions.lib.php')) {
	fwrite(STDERR, "Usage: php test/run_quartix.php <Dolibarr htdocs>\n"); exit(2);
}
define('DOL_DOCUMENT_ROOT', $coreRoot);
define('DOL_URL_ROOT', '');
define('MAIN_DB_PREFIX', 'quartix_long_prefix_');
if (is_file($coreRoot.'/version.inc.php')) require_once $coreRoot.'/version.inc.php';
else {
	preg_match('/define\([\'"]DOL_VERSION[\'"],\s*[\'"]([^\'"]+)[\'"]\)/', file_get_contents($coreRoot.'/filefunc.inc.php'), $versionMatch);
	define('DOL_VERSION', $versionMatch[1]);
}
date_default_timezone_set('UTC');
$conf = (object) array(
	'global' => (object) array('LMDBVEHICLEMANAGEMENT_QX_ENABLED' => 1, 'LMDBVEHICLEMANAGEMENT_QX_TIME_MODE' => 'offset'),
	'entity' => 1, 'currency' => 'EUR', 'theme' => 'eldy',
	'modules' => array('lmdbvehiclemanagement' => 1, 'multicompany' => 1, 'cron' => 1),
	'cron' => (object) array('enabled' => 1),
	'lmdbvehiclemanagement' => (object) array('enabled' => 1),
	'multicompany' => (object) array('enabled' => 1),
	'file' => (object) array('instance_unique_id' => 'quartix-tests-only-instance-key', 'dol_document_root' => array('main' => $coreRoot, 'alt0' => dirname(__DIR__, 2))),
);
require_once $coreRoot.'/core/lib/functions.lib.php';
if (is_file($coreRoot.'/core/lib/html.lib.php')) require_once $coreRoot.'/core/lib/html.lib.php';
require_once $coreRoot.'/core/class/translate.class.php';
require_once $coreRoot.'/user/class/user.class.php';
$langs = new Translate('', $conf);
$langs->setDefaultLang('en_US');
$langs->loadLangs(array('main', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
require_once dirname(__DIR__).'/class/lmdbvehiclequartixcron.class.php';
require_once dirname(__DIR__).'/core/modules/modLmdbVehicleManagement.class.php';

/**
 * Translate MySQL syntax only at the test boundary. This does not validate a real
 * MySQL installation: it exercises transactions, constraints and native objects.
 */
final class QxTestDb
{
	/** @var PDO */ public $pdo;
	/** @var string */ public $type = 'mysqli';
	/** @var int */ private $depth = 0;
	/** @var string */ private $error = '';
	/** @var list<string> */ public $queries = array();
	/** @var string */ public $failPattern = '';
	/** @var bool */ public $locked = false;
	public function __construct()
	{
		$this->pdo = class_exists('Pdo\\Sqlite') ? new Pdo\Sqlite('sqlite::memory:') : new PDO('sqlite::memory:');
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$register = method_exists($this->pdo, 'createFunction') ? 'createFunction' : 'sqliteCreateFunction';
		$this->pdo->{$register}('IF', static function ($condition, $yes, $no) { return $condition ? $yes : $no; }, 3);
		$this->pdo->{$register}('GREATEST', static function ($a, $b) { return max($a, $b); }, 2);
		$this->pdo->{$register}('DATE_FORMAT', static function ($date, $format) { return substr($date, 0, 7); }, 2);
	}
	/** @param string $sql SQL @return object|false */
	public function query($sql)
	{
		$this->queries[] = $sql;
		if ($this->failPattern !== '' && strpos($sql, $this->failPattern) !== false) { $this->error = 'Injected failure'; return false; }
		if (strpos($sql, 'GET_LOCK(') !== false) { $acquired = !$this->locked; $this->locked = true; return (object) array('rows' => array((object) array('acquired' => (int) $acquired)), 'pos' => 0); }
		if (strpos($sql, 'RELEASE_LOCK(') !== false) { $this->locked = false; return (object) array('rows' => array(), 'pos' => 0); }
		if (strpos($sql, 'information_schema.') !== false) {
			preg_match("/TABLE_NAME = '([^']+)'/", $sql, $table);
			if (strpos($sql, '.TABLES') !== false) $sql = "SELECT COUNT(*) AS nb FROM sqlite_master WHERE type='table' AND name='".$table[1]."'";
			else {
				preg_match("/COLUMN_NAME = '([^']+)'/", $sql, $field);
				$sql = "SELECT COUNT(*) AS nb FROM pragma_table_info('".$table[1]."') WHERE name='".$field[1]."'";
			}
		}
		$sql = preg_replace('/\s+FOR UPDATE\b/i', '', $sql);
		$sql = str_replace('INSERT IGNORE INTO', 'INSERT OR IGNORE INTO', $sql);
		$sql = preg_replace('/\bAUTO_INCREMENT\b/i', '', $sql);
		$sql = preg_replace('/\s+ENGINE\s*=\s*innodb\s*/i', '', $sql);
		$sql = preg_replace('/\s+ON UPDATE CURRENT_TIMESTAMP/i', '', $sql);
		if (preg_match('/^DELETE FROM (\w+) WHERE (.+) LIMIT (\d+)$/D', $sql, $delete)) {
			$sql = 'DELETE FROM '.$delete[1].' WHERE rowid IN (SELECT rowid FROM '.$delete[1].' WHERE '.$delete[2].' LIMIT '.$delete[3].')';
		}
		if (strpos($sql, 'ON DUPLICATE KEY UPDATE') !== false) {
			$sql = str_replace('ON DUPLICATE KEY UPDATE', 'ON CONFLICT DO UPDATE SET', $sql);
			$sql = preg_replace('/VALUES\((\w+)\)/', 'excluded.$1', $sql);
		}
		try {
			$result = $this->pdo->query($sql);
			$rows = $result->columnCount() ? $result->fetchAll(PDO::FETCH_OBJ) : array();
			return (object) array('rows' => $rows, 'pos' => 0);
		} catch (PDOException $e) { $this->error = $e->getMessage(); throw new RuntimeException($this->error."\n".$sql); }
	}
	public function begin() { if ($this->depth++ === 0) $this->pdo->beginTransaction(); return 1; }
	public function commit() { if ($this->depth > 0 && --$this->depth === 0) $this->pdo->commit(); return 1; }
	public function rollback() { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); $this->depth = 0; return 1; }
	public function prefix() { return MAIN_DB_PREFIX; }
	public function sanitize($value, $a = 0, $b = 0, $c = 0) { return $value; }
	public function escape($value) { return str_replace("'", "''", $value); }
	public function idate($value) { return gmdate('Y-m-d H:i:s', (int) $value); }
	public function jdate($value) { return $value === null ? 0 : (int) strtotime($value.' UTC'); }
	public function fetch_object($result) { return $result->rows[$result->pos++] ?? false; }
	public function num_rows($result) { return count($result->rows); }
	public function free($result) {}
	public function last_insert_id($table) { return (int) $this->pdo->lastInsertId(); }
	public function lasterror() { return $this->error; }
	public function lasterrno() { return ''; }
	public function affected_rows($result = null) { return 1; }
	public function plimit($limit, $offset = 0) { return ' LIMIT '.((int) $limit).' OFFSET '.((int) $offset); }
	public function order($field, $order) { return ' ORDER BY '.$field.' '.$order; }
	public function encrypt($value, $crypt = 1) { return "'".$this->escape($value)."'"; }
	public function decrypt($value) { return $value; }
}

final class QxTestClient extends LmdbVehicleQuartixClient
{
	/** @var list<array{status:int,body:string,retry:int}> */ public $responses = array();
	/** @var list<array{method:string,path:string,values:array<string,int|string>,token:string}> */ public $calls = array();
	protected function request($method, $path, $values, $token)
	{
		$this->calls[] = compact('method', 'path', 'values', 'token');
		$response = array_shift($this->responses);
		if ($response === null) throw new RuntimeException('Unexpected request');
		if ($response['status'] === 0) throw new RuntimeException('QxNetworkError');
		return $response;
	}
}
final class QxTestReading extends LmdbVehicleOdometerReading
{
	/** @var int */ public static $events = 0;
	/** @var bool */ public static $failTrigger = false;
	public function call_trigger($triggerName, $user) { self::$events++; return self::$failTrigger ? -1 : 1; }
}
final class QxTestCron extends LmdbVehicleQuartixCron
{
	/** @var QxTestClient */ public $client;
	protected function createClient($entity) { return $this->client; }
}
$checks = 0;
function qxCheck($condition, $label) { global $checks; if (!$condition) throw new RuntimeException($label); $checks++; }
function qxReject($call, $code) { try { $call(); } catch (RuntimeException $e) { qxCheck($e->getMessage() === $code, 'Expected '.$code.', got '.$e->getMessage()); return; } throw new RuntimeException('Expected rejection: '.$code); }
function qxResponse($data, $status = 200, $retry = 900) { return array('status' => $status, 'body' => json_encode(array('Meta' => array('Code' => 0), 'Data' => $data)), 'retry' => $retry); }

$db = new QxTestDb();
$user = new User($db); $user->id = 1; $user->admin = 1; $user->socid = 0;
$extrafields = (object) array('attributes' => array('lmdbvehiclemanagement_odometer_reading' => array('loaded' => 1), 'lmdbvehiclemanagement_vehicle' => array('loaded' => 1)));
foreach (array('odometer_reading', 'vehicle', 'qx_link', 'qx_position', 'qx_usage', 'qx_token', 'qx_job') as $table) {
	$sql = file_get_contents(dirname(__DIR__).'/sql/llx_lmdbvehiclemanagement_'.$table.'.sql');
	$db->query(str_replace('llx_', MAIN_DB_PREFIX, $sql));
}
foreach (array('qx_link' => array('entity,fk_vehicle', 'entity,remote_id'), 'qx_position' => array('entity,fk_vehicle'), 'qx_usage' => array('entity,fk_vehicle,usage_day'), 'qx_job' => array('entity,job_kind'), 'odometer_reading' => array('entity,fk_vehicle,provider_key')) as $table => $keys) {
	foreach ($keys as $i => $columns) $db->query('CREATE UNIQUE INDEX qx_'.$table.'_'.$i.' ON '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_'.$table.' ('.$columns.')');
}
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'const (rowid integer PRIMARY KEY, entity integer, name text, value text, type text, visible integer, note text)');
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'extrafields (rowid integer PRIMARY KEY, elementtype text)');

// Strict boundaries, including the two DST ambiguities and explicit zero.
qxCheck(LmdbVehicleQuartixRules::number(0) === 0.0, 'Zero is known, not missing');
foreach (array(null, '', '12', -1, INF, NAN, array()) as $bad) qxReject(static function () use ($bad) { LmdbVehicleQuartixRules::number($bad); }, 'QxInvalidResponse');
qxReject(static function () { LmdbVehicleQuartixRules::day('2026-02-30'); }, 'QxInvalidResponse');
qxReject(static function () { LmdbVehicleQuartixRules::timestamp('2026-09-01T10:00:00Z', '', 'Europe/Paris'); }, 'QxTimeUnconfirmed');
qxCheck(LmdbVehicleQuartixRules::timestamp('2026-09-01T10:00:00Z', 'offset', 'Europe/Paris') - LmdbVehicleQuartixRules::timestamp('2026-09-01T10:00:00Z', 'local', 'Europe/Paris') === 7200, 'Local and offset contracts remain distinct');
qxReject(static function () { LmdbVehicleQuartixRules::timestamp('2026-10-25T02:30:00Z', 'local', 'Europe/Paris'); }, 'QxAmbiguousTime');
qxReject(static function () { LmdbVehicleQuartixRules::timestamp('2026-03-29T02:30:00Z', 'local', 'Europe/Paris'); }, 'QxInvalidResponse');
qxCheck(LmdbVehicleQuartixRules::hours(60.0, '') === null && LmdbVehicleQuartixRules::hours(60.0, 'minutes') === 1.0 && LmdbVehicleQuartixRules::hours(3600.0, 'seconds') === 1.0, 'Duration confirmation is mandatory');
$summary = array('VehicleID' => 10, 'Date' => '2026-08-30', 'NumberOfTrips' => 2, 'Distance' => 42.5, 'TravelTime' => 60, 'IdlingTime' => 3);
qxReject(static function () use ($summary) { LmdbVehicleQuartixRules::summaries(array($summary, $summary), 10, '2026-08-30', '2026-08-31'); }, 'QxInvalidResponse');
qxReject(static function () use ($summary) { LmdbVehicleQuartixRules::summaries(array($summary), 11, '2026-08-30', '2026-08-31'); }, 'QxInvalidResponse');

// Native encryption, one retry after 401, invalid refresh fallback and safe errors.
qxReject(static function () use ($db) { new QxTestClient($db, 1); }, 'QxApplicationRequired');
qxCheck(LmdbVehicleQuartixConfig::unavailableReason('jobs') === 'QxApplicationRequired', 'Legacy settings require the provider application name before any job or request');
qxCheck(LmdbVehicleQuartixCron::safeError(new RuntimeException('QxApplicationRequired')) === 'QxApplicationRequired', 'Missing application name has an actionable safe error');
foreach (array('', '   ') as $missingApplication) qxReject(static function () use ($missingApplication) { LmdbVehicleQuartixConfig::validateApplication($missingApplication); }, 'QxApplicationRequired');
foreach (array("app\nheader", str_repeat('x', 129)) as $badApplication) qxReject(static function () use ($badApplication) { LmdbVehicleQuartixConfig::validateApplication($badApplication); }, 'QxInvalidSettings');
$conf->global->LMDBVEHICLEMANAGEMENT_QX_APPLICATION = 'test-application-A';
$secret = LmdbVehicleQuartixConfig::encrypt('test-secret');
qxCheck($secret !== 'test-secret' && dolDecrypt($secret) === 'test-secret', 'Native encryption round trip');
$client = new QxTestClient($db, 1);
$client->responses = array(qxResponse(array('AccessToken' => 'test-access', 'RefreshToken' => 'test-refresh')), qxResponse(array(array('VehicleID' => 10))));
qxCheck(count($client->get('/vehicles')) === 1, 'Auth followed by read');
qxCheck($client->calls[0]['values']['Application'] === 'test-application-A', 'Authentication uses the configured application, not the module key');
$tokens = $db->pdo->query('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_token')->fetch(PDO::FETCH_ASSOC);
qxCheck(strpos($tokens['access_token'], 'dolcrypt:') === 0 && strpos($tokens['refresh_token'], 'dolcrypt:') === 0, 'Tokens encrypted in storage');
$client->responses = array(qxResponse(null, 401), qxResponse(array('AccessToken' => 'new-access', 'RefreshToken' => 'new-refresh')), qxResponse(array()));
qxCheck($client->get('/vehicles') === array() && $client->calls[3]['path'] === '/auth/refresh' && $client->calls[4]['token'] === 'new-access', 'Expired token refresh');
$client->responses = array(qxResponse(null, 401), qxResponse(null, 401), qxResponse(array('AccessToken' => 'fallback-access', 'RefreshToken' => 'fallback-refresh')), qxResponse(array()));
qxCheck($client->get('/vehicles') === array() && $client->calls[7]['path'] === '/auth', 'Revoked refresh reauthenticates once');
$client->responses = array(qxResponse(null, 429, 120));
qxReject(static function () use ($client) { $client->get('/vehicles'); }, 'QxRateLimited');
qxCheck($client->retryAfter === 120, 'Retry-After honored');
$callCount = count($client->calls);
qxReject(static function () use ($client) { $client->get('/vehicles'); }, 'QxRateLimited');
qxCheck(count($client->calls) === $callCount, 'Quota prevents manual retry traffic too');
qxCheck($client->getDiagnosticMessage($langs) === '', 'A quota refusal does not reuse the previous HTTP diagnostic');
$db->query("DELETE FROM ".MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_job WHERE job_kind='api' AND entity=1");
$client->responses = array(array('status' => 200, 'body' => '{"Meta":"secret","Data":[]}', 'retry' => 0));
qxReject(static function () use ($client) { $client->get('/vehicles'); }, 'QxInvalidResponse');
qxReject(static function () use ($client) { $client->get('/vehicles/trips'); }, 'QxInvalidEndpoint');
qxCheck($client->getDiagnostic()['endpoint'] === '', 'Rejected local endpoint has no stale transport result');

// Reproduce the generic service error: preserve the HTTP status and failed stage,
// while excluding the provider body, query parameters and credentials from diagnostics.
foreach (array(301, 400, 404, 405, 415, 500, 502, 503) as $status) {
	$client->responses = array(array('status' => $status, 'body' => 'sensitive-provider-body', 'retry' => 0));
	qxReject(static function () use ($client) { $client->get('/vehicles', array('SiteID' => 'sensitive-query')); }, 'QxRemoteError');
	qxCheck($client->getDiagnostic() === array('endpoint' => '/vehicles', 'http_status' => $status, 'curl_error' => 0), 'Safe exact HTTP diagnostic for '.$status);
	$diagnostic = $client->getDiagnosticMessage($langs);
	qxCheck(strpos($diagnostic, '/vehicles') !== false && strpos($diagnostic, (string) $status) !== false && strpos($diagnostic, 'sensitive') === false && strpos($diagnostic, 'access') === false, 'Translated diagnostic excludes response and request secrets');
}
$client->responses = array(qxResponse(null, 401), qxResponse(null, 503));
qxReject(static function () use ($client) { $client->get('/vehicles'); }, 'QxRemoteError');
qxCheck($client->getDiagnostic()['endpoint'] === '/auth/refresh', 'Refresh failure reports the refresh stage');
$client->responses = array(qxResponse(null, 0));
qxReject(static function () use ($client) { $client->get('/vehicles'); }, 'QxNetworkError');
qxCheck($client->getDiagnostic() === array('endpoint' => '/vehicles', 'http_status' => 0, 'curl_error' => 0), 'Transport exception clears the preceding HTTP status');
$conf->entity = 2;
$conf->global->LMDBVEHICLEMANAGEMENT_QX_APPLICATION = 'test-application-B';
$authFailure = new QxTestClient($db, 2);
$authFailure->responses = array(qxResponse(null, 415));
qxReject(static function () use ($authFailure) { $authFailure->get('/vehicles'); }, 'QxRemoteError');
qxCheck($authFailure->getDiagnostic()['endpoint'] === '/auth' && count($authFailure->calls) === 1, 'Authentication failure is distinguished from catalogue failure');
qxCheck($authFailure->calls[0]['values']['Application'] === 'test-application-B', 'Second entity authenticates with its own application name');
qxCheck((int) $db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_token WHERE entity=2')->fetchColumn() === 0, 'Failed authentication creates no tokens');
$authFailure->responses = array(array('status' => 422, 'body' => 'sensitive-validation-body', 'retry' => 0));
qxReject(static function () use ($authFailure) { $authFailure->get('/vehicles'); }, 'QxRequestRejected');
qxCheck($authFailure->getDiagnostic() === array('endpoint' => '/auth', 'http_status' => 422, 'curl_error' => 0) && count($authFailure->calls) === 2, '422 stops at auth without trying another encoding or reading vehicles');
qxCheck(LmdbVehicleQuartixCron::safeError(new RuntimeException('QxRequestRejected')) === 'QxRequestRejected'
	&& strpos($authFailure->getDiagnosticMessage($langs), 'sensitive') === false, 'Validation refusal remains a safe, distinct error');
qxCheck((int) $db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_token WHERE entity=2')->fetchColumn() === 0, '422 never stores tokens');
$conf->entity = 2;
qxReject(static function () use ($db) { new QxTestClient($db, 1); }, 'QxAccessDenied');
$conf->entity = 1;
$conf->global->LMDBVEHICLEMANAGEMENT_QX_APPLICATION = 'test-application-A';

// Real SQL constraints and native CommonObject persistence in two entities.
$db->query("INSERT INTO ".MAIN_DB_PREFIX."lmdbvehiclemanagement_vehicle (rowid,entity,ref,label,fk_user_creat,date_creation) VALUES (1,1,'QX-A','Vehicle A',1,'2026-01-01'),(2,2,'QX-B','Vehicle B',1,'2026-01-01')");
$db->query("INSERT INTO ".MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_link (entity,fk_vehicle,remote_id,timezone,shift_start,date_creation,fk_user_creat) VALUES (1,1,10,'Europe/Paris','08:00:00','2026-01-01',1),(2,2,10,'Europe/Paris','08:00:00','2026-01-01',1)");
$service = new LmdbVehicleQuartixService($db);
$link = $service->link(1);
qxCheck($link !== null && $link->entity == 1, 'Owner association loaded');
qxReject(static function () use ($service) { $service->link(2); }, 'QxAccessDenied');
$service->saveUsage($link, array($summary), '2026-08-30', '2026-08-31');
$service->saveUsage($link, array($summary), '2026-08-30', '2026-08-31');
$rows = $service->usage(1, '2026-08-01', '2026-08-31', 'month');
qxCheck(count($rows) === 1 && $rows[0]->known_days == 1 && $rows[0]->fetched_days == 2 && $rows[0]->distance == 42.5, 'Replay and missing days are distinct from zero');
$db->failPattern = ",'2026-08-31',";
qxReject(static function () use ($service, $link, $summary) { $summary['Distance'] = 999; $service->saveUsage($link, array($summary), '2026-08-30', '2026-08-31'); }, 'QxDatabaseError');
$db->failPattern = '';
qxCheck($service->usage(1, '2026-08-01', '2026-08-31', 'month')[0]->distance == 42.5, 'Partial period rolls back atomically');
$position = array('VehicleID' => 10, 'LastEventDatetime' => '2026-08-30T12:00:00Z', 'Latitude' => 48.5, 'Longitude' => -1.2, 'NonTracking' => false, 'LocationText' => '<script>not HTML</script>', 'Speed' => 40, 'Heading' => 180);
$service->savePosition($link, $position, 'offset');
$service->savePosition($link, $position, 'offset');
$position['LastEventDatetime'] = '2026-08-29T12:00:00Z'; $position['Latitude'] = 0.0;
$service->savePosition($link, $position, 'offset');
qxCheck($service->position(1)->latitude == 48.5 && $db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_position')->fetchColumn() == 1, 'Older positions cannot overwrite and no history is kept');
$foreign = clone $link; $foreign->entity = 2;
qxReject(static function () use ($service, $foreign, $position) { $service->savePosition($foreign, $position, 'offset'); }, 'QxAccessDenied');
qxCheck($service->lock(1) && !$service->lock(1), 'Concurrent lock rejected'); $service->unlock(1);

$reading = new QxTestReading($db);
$reading->fk_vehicle = 1; $reading->reading_date = strtotime('2026-08-29 12:00 UTC'); $reading->odometer_km = 1000;
qxCheck($reading->create($user) > 0, 'Native real reading creation: '.$reading->error);
$realId = $reading->id;
$estimateDate = strtotime('2026-08-30 12:00 UTC');
$estimateId = $reading->saveQuartix($user, 1, 10, $estimateDate, '2026-08-30', 9999.0);
qxCheck($estimateId > 0 && $estimateId !== $realId, 'Loaded real reading cannot be overwritten: '.$reading->error);
$eventCount = QxTestReading::$events;
qxCheck($reading->saveQuartix($user, 1, 10, $estimateDate, '2026-08-30', 9999.0) === $estimateId && QxTestReading::$events === $eventCount, 'Replay has no duplicate trigger');
$actual = new QxTestReading($db); $actual->fk_vehicle = 1; $actual->reading_date = strtotime('2026-08-31 12:00 UTC'); $actual->odometer_km = 1100;
qxCheck($actual->create($user) > 0, 'Contradictory estimate cannot block a real reading: '.$actual->error);
$records = $actual->fetchAllByVehicle(1);
qxCheck($records[1]->is_estimate && $records[1]->estimate_conflict, 'Contradiction remains visible');
$fuel = new QxTestReading($db); $fuel->fk_vehicle = 1; $fuel->reading_date = strtotime('2026-08-30 18:00 UTC'); $fuel->odometer_km = 1050;
$db->begin();
qxCheck($fuel->createFromConsumption($user) > 0, 'Fuel/recharge reading ignores conflicting estimate');
$db->commit();
$fuel->odometer_km = 1080;
$db->begin();
qxCheck($fuel->updateFromConsumption($user) > 0, 'Fuel/recharge correction uses real progression anchors');
$db->commit();
$pageReading = $actual->fetchAllByVehicle(1, 1, 2);
qxCheck(count($pageReading) === 1 && $pageReading[0]->is_estimate && $pageReading[0]->estimate_conflict, 'Estimate page loads real anchors outside the page');
$pageReading = $actual->fetchAllByVehicle(1, 1, 0);
qxCheck(count($pageReading) === 1 && $pageReading[0]->previous_actual_km === 1080.0, 'Real difference crosses page boundary without using an estimate');
qxCheck($actual->countByVehicle(1) === 4 && $actual->countByVehicle(1, $fuel) === 1, 'Paging and links locate the correct reading');
$actual->odometer_km = 900;
qxCheck($actual->update($user) < 0, 'Real progression still enforced');
$estimated = new QxTestReading($db); $estimated->fetch($estimateId); $estimated->is_estimate = 0;
qxCheck($estimated->update($user) < 0 && $estimated->error === 'QxOwnsReading', 'Clearing input marker does not grant ownership');
qxCheck($estimated->delete($user) < 0 && $estimated->error === 'QxOwnsReading', 'Clearing input marker does not grant deletion');
QxTestReading::$failTrigger = true;
$failed = new QxTestReading($db);
qxCheck($failed->saveQuartix($user, 1, 10, strtotime('2026-09-01 12:00 UTC'), '2026-09-01', 1200.0) < 0, 'Failed CRUD rolls back');
QxTestReading::$failTrigger = false;
qxCheck(count($actual->fetchAllByVehicle(1)) === 4, 'Failed CRUD left no estimate');
$conf->entity = 2;
$foreignReading = new QxTestReading($db);
qxCheck($foreignReading->saveQuartix($user, 1, 10, $estimateDate, '2026-08-30', 1200.0) < 0, 'Owner-only imports');
qxCheck($foreignReading->saveQuartix($user, 2, 10, $estimateDate, '2026-08-30', 500.0) > 0, 'Same remote id isolated in second entity');
$mc = new class {
	public function getEntity($element, $shared = 1, $object = null) { return '1,2'; }
};
qxCheck($service->position(1)->entity == 1, 'Shared GPS reads from vehicle owner');
qxCheck($service->usage(1, '2026-08-01', '2026-08-31', 'month')[0]->distance == 42.5, 'Shared usage reads from vehicle owner');
qxReject(static function () use ($service, $link, $summary) { $service->saveUsage($link, array($summary), '2026-08-30', '2026-08-31'); }, 'QxAccessDenied');
$mc = null;
$conf->entity = 1;

// GPS is neither inherited from read nor exposed to external users.
$user->admin = 0;
$user->rights = (object) array('lmdbvehiclemanagement' => (object) array('read' => 1));
qxCheck(LmdbVehicleQuartixConfig::can($user, 'read') && !LmdbVehicleQuartixConfig::can($user, 'location') && !LmdbVehicleQuartixConfig::can($user, 'sync'), 'Read permission does not imply GPS or sync');
qxReject(static function () use ($service) { $service->position(1); }, 'QxAccessDenied');
$user->socid = 12; $user->admin = 1;
qxCheck(!LmdbVehicleQuartixConfig::can($user, 'location'), 'External user cannot use GPS even with stale admin flag');
$user->socid = 0;
qxCheck(LmdbVehicleQuartixConfig::can($user, 'location') && LmdbVehicleQuartixConfig::can($user, 'sync'), 'Admin has functional rights without granular grants');

// Execute real cron orchestration against the offline transport.
$cron = new QxTestCron($db);
$cron->client = new QxTestClient($db, 1);
$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
$lastDay = $now->modify($now->format('H:i:s') < '08:00:00' ? '-2 days' : '-1 day')->format('Y-m-d');
$cron->client->responses = array(qxResponse(array()));
qxCheck($cron->usage() === 0, 'Usage job runs through native orchestration: '.$cron->error);
$firstCall = $cron->client->calls[0];
qxCheck($firstCall['values']['EndDay'] === $lastDay && $firstCall['values']['StartDay'] === LmdbVehicleQuartixRules::day($lastDay)->modify('-6 days')->format('Y-m-d'), 'Usage only rereads seven completed reporting days');
$cron->client->responses = array(qxResponse(array()));
qxCheck($cron->usage() === 0, 'Backfill resumes');
$secondCall = $cron->client->calls[1];
qxCheck($secondCall['values']['EndDay'] === LmdbVehicleQuartixRules::day($lastDay)->modify('-7 days')->format('Y-m-d'), 'Backfill follows previous week');
$db->query("UPDATE ".MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_link SET usage_refreshed='2020-01-01',usage_cursor='2020-01-01' WHERE entity=1");
$db->query("INSERT INTO ".MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_usage (entity,fk_vehicle,usage_day,has_data,date_sync) VALUES (1,1,'2020-01-01',0,'2020-01-01'),(2,2,'2020-01-01',0,'2020-01-01')");
$cron->client->responses = array(qxResponse(array()));
qxCheck($cron->usage() === 0, 'Long outage recovery');
qxCheck($service->link(1)->usage_cursor === LmdbVehicleQuartixRules::day($lastDay)->modify('-7 days')->format('Y-m-d'), 'Outage resets backfill so missed weeks are revisited');
qxCheck($db->pdo->query("SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_usage WHERE usage_day='2020-01-01' AND entity=1")->fetchColumn() == 0
	&& $db->pdo->query("SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_usage WHERE usage_day='2020-01-01' AND entity=2")->fetchColumn() == 1, 'Twelve-month retention is owner scoped');
$cron->client->responses = array(qxResponse(array()));
qxCheck($cron->positions() < 0 && $cron->error === 'QxNoVehicleData', 'Missing vehicle is a partial job failure');
$cron->client->responses = array(qxResponse(array(array_replace($position, array('Latitude' => 48.6, 'LastEventDatetime' => '2026-08-31T12:00:00Z')))));
qxCheck($cron->positions() === 0 && $service->position(1)->latitude == 48.6, 'Position retries recover without duplicate rows');
$cron->client->responses = array(qxResponse(null, 429, 180));
qxCheck($cron->positions() < 0 && $cron->error === 'QxRateLimited', 'Cron returns quota error');
$requestCount = count($cron->client->calls);
qxCheck($cron->usage() === 0 && count($cron->client->calls) === $requestCount, 'Other workers honor entity-wide cooldown');

// Validation failures abort the batch before the next vehicle and preserve the cursor.
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_job SET retry_at=NULL WHERE entity=1');
$db->query("INSERT INTO ".MAIN_DB_PREFIX."lmdbvehiclemanagement_vehicle (rowid,entity,ref,label,fk_user_creat,date_creation) VALUES (3,1,'QX-C','Vehicle C',1,'2026-01-01')");
$db->query("INSERT INTO ".MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_link (entity,fk_vehicle,remote_id,timezone,shift_start,date_creation,fk_user_creat) VALUES (1,3,20,'Europe/Paris','08:00:00','2026-01-01',1)");
$db->query("UPDATE ".MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_job SET last_vehicle=0 WHERE entity=1 AND job_kind='usage'");
$cursorBefore = $service->link(1)->usage_cursor;
$cron->client->responses = array(qxResponse(null, 422));
qxCheck($cron->usage() < 0 && $cron->error === 'QxRequestRejected', 'Cron reports validation failure distinctly');
$jobState = $db->pdo->query("SELECT * FROM ".MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_job WHERE entity=1 AND job_kind='usage'")->fetch(PDO::FETCH_OBJ);
qxCheck(count($cron->client->calls) === $requestCount + 1 && $jobState->last_vehicle == 0 && $service->link(1)->usage_cursor === $cursorBefore, '422 aborts the batch without advancing progress');
qxCheck($db->jdate($jobState->retry_at) > dol_now() && strpos($cron->output, '422') !== false && !$db->locked, '422 schedules a cooldown, reports status and releases lock');
qxCheck($cron->positions() === 0 && count($cron->client->calls) === $requestCount + 1, 'Other workers honor the validation cooldown');
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link WHERE entity=1 AND fk_vehicle=3');
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle WHERE entity=1 AND rowid=3');

// Configuration uses native per-entity constants and never persists a plaintext secret.
$configuration = new LmdbVehicleQuartixConfig($db);
$settings = array('CUSTOMER' => 'test-company', 'USERNAME' => 'test-user', 'PASSWORD' => 'test-password', 'APPLICATION' => 'test-application-A', 'TIME_MODE' => 'offset', 'DURATION_UNIT' => '');
$configuration->save($user, $settings);
$storedPassword = $db->pdo->query("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE entity=1 AND name='LMDBVEHICLEMANAGEMENT_QX_PASSWORD'")->fetchColumn();
qxCheck(strpos($storedPassword, 'dolcrypt:') === 0 && dolDecrypt($storedPassword) === 'test-password', 'Native setting stores ciphertext');
$settings['PASSWORD'] = '';
$configuration->save($user, $settings);
qxCheck($configuration->load(1, true)['PASSWORD'] === 'test-password' && $configuration->load(1)['DURATION_UNIT'] === '', 'Empty password preserves secret, empty unit stays unconfirmed');
$beforeMissing = count($db->queries);
$missingApplicationSettings = $settings;
unset($missingApplicationSettings['APPLICATION']);
qxReject(static function () use ($configuration, $user, $missingApplicationSettings) { $configuration->save($user, $missingApplicationSettings); }, 'QxApplicationRequired');
qxCheck(count($db->queries) === $beforeMissing, 'Missing application cannot mutate credentials or invalidate tokens');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_job SET retry_at=NULL WHERE entity=1');
$oldApplicationClient = new QxTestClient($db, 1);
$oldApplicationClient->responses = array(qxResponse(array('AccessToken' => 'old-app-access', 'RefreshToken' => 'old-app-refresh')), qxResponse(array()));
$oldApplicationClient->get('/vehicles');
$settings['APPLICATION'] = 'test-application-A-updated';
$configuration->save($user, $settings);
qxCheck($configuration->load(1, true)['APPLICATION'] === $settings['APPLICATION'] && $configuration->load(1, true)['PASSWORD'] === 'test-password', 'Application change preserves the saved password');
qxCheck((int) $db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_token WHERE entity=1')->fetchColumn() === 0, 'Application change invalidates old application tokens');
$newApplicationClient = new QxTestClient($db, 1);
$newApplicationClient->responses = array(qxResponse(array('AccessToken' => 'new-app-access', 'RefreshToken' => 'new-app-refresh')), qxResponse(array()));
$newApplicationClient->get('/vehicles');
qxCheck($newApplicationClient->calls[0]['values']['Application'] === $settings['APPLICATION'], 'Next connection authenticates under the updated application');
$savedGlobals = clone $conf->global;
$conf->entity = 2;
$secondEntitySettings = $settings;
$secondEntitySettings['APPLICATION'] = 'test-application-B';
$secondEntitySettings['PASSWORD'] = 'second-entity-password';
$configuration->save($user, $secondEntitySettings);
qxCheck($configuration->load(2, true)['APPLICATION'] === 'test-application-B', 'Second entity persists its application with native constants');
$conf->entity = 1;
$conf->global = $savedGlobals;
qxCheck($configuration->load(1, true)['APPLICATION'] === 'test-application-A-updated'
	&& $db->pdo->query("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE entity=1 AND name='LMDBVEHICLEMANAGEMENT_QX_APPLICATION'")->fetchColumn() === 'test-application-A-updated'
	&& (int) $db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_token WHERE entity=1')->fetchColumn() === 1, 'Other entity configuration preserves both application and tokens');
$settings['CUSTOMER'] = 'different-company';
qxReject(static function () use ($configuration, $user, $settings) { $configuration->save($user, $settings); }, 'QxAccountInUse');
$instanceKey = $conf->file->instance_unique_id;
$conf->file->instance_unique_id = '';
qxReject(static function () { LmdbVehicleQuartixConfig::encrypt('test'); }, 'QxRequiresCrypto');
$conf->file->instance_unique_id = $instanceKey;

// Native renderers: no GPS in the response without permission, and source text is escaped.
require_once dirname(__DIR__).'/lib/lmdbvehiclequartix.lib.php';
$vehicle = $service->vehicle(1);
ob_start(); lmdbVehicleQuartixPrintPosition($vehicle); $gpsHtml = ob_get_clean();
qxCheck(strpos($gpsHtml, '48.6') !== false && strpos($gpsHtml, '<script>not HTML</script>') === false, 'Authorized GPS renders escaped data');
$user->admin = 0;
ob_start(); lmdbVehicleQuartixPrintPosition($vehicle); $deniedHtml = ob_get_clean();
qxCheck($deniedHtml === '', 'No GPS fragment for read-only users');
$user->admin = 1;
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
$graph = new DolGraph('jflot');
$graph->SetData(array(array('Known day', 42.5)));
$graph->SetLegend(array('Distance'));
$graph->SetType(array('bars'));
$graph->SetWidth('100%');
$graph->SetHeight(260);
$graph->draw('qx_test_native_graph');
qxCheck(strpos($graph->show(), '42.5') !== false && strpos($graph->show(), 'qx_test_native_graph') !== false, 'Native graph backend renders without a generated file');

// Upgrade twice, preserving real data and avoiding duplicate columns.
$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading DROP COLUMN is_estimate');
$db->query('DROP INDEX qx_odometer_reading_0');
$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading DROP COLUMN provider_key');
$descriptor = (new ReflectionClass(modLmdbVehicleManagement::class))->newInstanceWithoutConstructor(); $descriptor->db = $db;
$migration = new ReflectionMethod($descriptor, 'prepareQuartixSchema');
if (PHP_VERSION_ID < 80100) $migration->setAccessible(true);
qxCheck($migration->invoke($descriptor) === 1 && $migration->invoke($descriptor) === 1, 'Migration is replayable');
qxCheck($db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading')->fetchColumn() == 5, 'Migration preserves historical rows');
$before = count($db->queries);
qxCheck($descriptor->delete_cronjobs() === 0 && count($db->queries) === $before, 'Disable preserves native cron settings');
foreach (glob(dirname(__DIR__).'/sql/*qx*.key.sql') as $file) {
	preg_match_all('/ADD (?:UNIQUE )?(?:INDEX|KEY)\s+(\w+)/i', file_get_contents($file), $matches);
	foreach ($matches[1] as $name) qxCheck(strlen(str_replace('llx_', MAIN_DB_PREFIX, $name)) <= 64, 'MySQL identifier length');
}
echo $checks.' QUARTIX checks passed (native Dolibarr '.DOL_VERSION.', in-memory SQL adapter; real MySQL and authenticated QWS not exercised)'.PHP_EOL;
