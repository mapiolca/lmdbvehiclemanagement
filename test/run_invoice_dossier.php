<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Native classes and PDF/ZIP engines; transactional database double, no business DB.
$core = realpath($argv[1] ?? '');
if (!$core || !is_file($core.'/core/lib/functions.lib.php')) { fwrite(STDERR, "Usage: php -d extension=zip test/run_invoice_dossier.php <htdocs>\n"); exit(2); }
define('DOL_DOCUMENT_ROOT', str_replace('\\', '/', $core));
define('DOL_URL_ROOT', '');
define('DOL_MAIN_URL_ROOT', 'https://dolibarr.invalid');
define('DOL_DATA_ROOT', str_replace('\\', '/', __DIR__).'/.dossier-test');
define('MAIN_DB_PREFIX', 'test_dossier_');
define('TCPDF_PATH', DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/');
define('TCPDI_PATH', DOL_DOCUMENT_ROOT.'/includes/tcpdi/');
if (is_file(DOL_DOCUMENT_ROOT.'/version.inc.php')) require DOL_DOCUMENT_ROOT.'/version.inc.php';
else { preg_match('/define\([\'"]DOL_VERSION[\'"],\s*[\'"]([^\'"]+)[\'"]\)/', file_get_contents(DOL_DOCUMENT_ROOT.'/filefunc.inc.php'), $version); define('DOL_VERSION', $version[1]); }
require_once DOL_DOCUMENT_ROOT.'/core/class/conf.class.php';
$conf = new Conf();
$configuration = array('entity' => 1, 'currency' => 'EUR', 'global' => (object) array('MAIN_DISABLE_TCPDI' => 1, 'MAIN_MAX_DECIMALS_TOT' => 2, 'MAIN_MAX_DECIMALS_UNIT' => 5, 'MAIN_UMASK' => '0664', 'MAIN_MONNAIE' => 'EUR'),
	'modules' => array('lmdbvehiclemanagement' => 1, 'fournisseur' => 1), 'modules_parts' => array('triggers' => array(), 'hooks' => array(), 'substitutions' => array(), 'models' => array('/lmdbvehiclemanagement/')),
	'file' => (object) array('dol_document_root' => array('main' => DOL_DOCUMENT_ROOT, 'alt0' => dirname(__DIR__, 2)), 'dol_url_root' => array('main' => '', 'alt0' => '/external')),
	'lmdbvehiclemanagement' => (object) array('dir_output' => DOL_DATA_ROOT.'/lmdbvehiclemanagement', 'multidir_output' => array(1 => DOL_DATA_ROOT.'/lmdbvehiclemanagement', 2 => DOL_DATA_ROOT.'/2/lmdbvehiclemanagement')));
foreach ($configuration as $key => $value) $conf->$key = $value;
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/db/mysqli.class.php';
require_once dirname(__DIR__).'/class/lmdbvehicledossier.class.php';
require_once dirname(__DIR__).'/core/modules/lmdbvehiclemanagement/doc/pdf_lmdb_vehicle_dossier.modules.php';

final class DossierDb extends DoliDBMysqli
{
	public function __construct() {}
	public $links = array(); public $snapshots = array(); public $invoiceEntity = 1; public $failInsert = false; public $paged = false;
	public $records = array(); public $invoiceCurrency = 'EUR'; public $ecm = array();
	public function query($sql, $usesavepoint = 0, $type = 'auto', $result_mode = 0)
	{
		$rows = array();
		if (preg_match('/^SELECT (.*?) FROM test_dossier_(lmdbvehiclemanagement_[a-z_]+) as t /is', $sql, $m)) {
			preg_match('/t.rowid\s*=\s*(\d+)/', $sql, $id);
			foreach ($this->records[$m[2]] ?? array() as $stored) {
				if ((isset($id[1]) && $stored['rowid'] != $id[1]) || $stored['entity'] !== 1) continue;
				$row = new stdClass();
				foreach (explode(',', $m[1]) as $field) { $key = preg_replace('/^t\./', '', trim($field)); $row->$key = $stored[$key] ?? null; }
				$rows[] = $row;
			}
		} elseif (preg_match('/^SELECT rowid FROM test_dossier_(lmdbvehiclemanagement_[a-z_]+) WHERE fk_vehicle = (\d+)/', $sql, $m)) {
			foreach ($this->records[$m[1]] ?? array() as $stored) if ($stored['entity'] === 1 && $stored['fk_vehicle'] == $m[2]) $rows[] = (object) array('rowid' => $stored['rowid']);
		} elseif (strpos($sql, 'FROM test_dossier_document_model') !== false) $rows[] = (object) array('id' => 'lmdb_vehicle_dossier', 'doc_template_name' => 'lmdb_vehicle_dossier', 'label' => 'Dossier véhicule', 'description' => '');
		elseif (strpos($sql, 'FROM test_dossier_ecm_files WHERE entity') !== false) $rows = $this->ecm;
		elseif (preg_match('/^SELECT .* FROM test_dossier_facture_fourn as t /s', $sql)) {
			preg_match('/^SELECT (.*?) FROM /s', $sql, $m);
			$row = new stdClass();
			foreach (explode(',', $m[1]) as $field) { $parts = preg_split('/\s+as\s+/i', trim($field)); $key = count($parts) > 1 ? end($parts) : preg_replace('/^\w+\./', '', $parts[0]); $row->$key = null; }
			foreach (array('rowid' => 9, 'entity' => $this->invoiceEntity, 'ref' => 'FA-9', 'fk_soc' => 5, 'socid' => 5, 'multicurrency_code' => $this->invoiceCurrency, 'multicurrency_tx' => 1, 'multicurrency_total_ttc' => 87, 'total_ttc' => 70) as $key => $value) $row->$key = $value;
			$rows[] = $row;
		} elseif (strpos($sql, 'FOR UPDATE') !== false) $rows[] = (object) array('rowid' => 7);
		elseif (strpos($sql, 'INSERT INTO test_dossier_element_element') === 0) {
			if ($this->failInsert) return false;
			preg_match("/VALUES \((\d+), '([^']+)', (\d+), '([^']+)'\)/", $sql, $m);
			$this->links[] = (object) array('rowid' => count($this->links) + 1, 'fk_source' => (int) $m[1], 'sourcetype' => $m[2], 'fk_target' => (int) $m[3], 'targettype' => $m[4]);
		} elseif (strpos($sql, 'DELETE FROM test_dossier_element_element') === 0) $this->links = array();
		elseif (strpos($sql, 'FROM test_dossier_element_element') !== false) {
			$rows = $this->links;
			if (preg_match('/fk_source = (\d+) AND sourcetype/', $sql, $m)) $rows = array_values(array_filter($rows, static function ($row) use ($m) { return $row->fk_source == $m[1]; }));
		}
		elseif ($this->paged && strpos($sql, 'SELECT rowid FROM fixture') === 0) {
			preg_match('/LIMIT (\d+), ?(\d+)/i', $sql, $m);
			$offset = (int) ($m[1] ?? 0); $limit = (int) ($m[2] ?? 200);
			for ($i = $offset; $i < min(621, $offset + $limit); $i++) $rows[] = (object) array('rowid' => $i);
		} elseif (!preg_match('/^SELECT/i', $sql)) throw new RuntimeException('Unexpected write: '.$sql);
		return (object) array('rows' => $rows, 'position' => 0);
	}
	public function fetch_object($r) { return $r->rows[$r->position++] ?? false; }
	public function num_rows($r) { return count($r->rows); }
	public function free($r = null) {}
	public function escape($s) { return str_replace("'", "''", $s); }
	public function sanitize($s, $allowsimplequote = 0, $allowsequals = 0, $allowsspace = 0, $allowschars = 1) { return $s; }
	public function prefix() { return MAIN_DB_PREFIX; }
	public function jdate($s, $gm = 'tzserver') { return $s ? strtotime($s) : 0; }
	public function idate($v, $gm = 'tzserver') { return date('Y-m-d H:i:s', $v); }
	public function plimit($limit = 0, $offset = 0) { return ' LIMIT '.$offset.', '.$limit; }
	public function begin($log = '') { $this->snapshots[] = $this->links; return 1; }
	public function commit($log = '') { array_pop($this->snapshots); return 1; }
	public function rollback($log = '') { $this->links = array_pop($this->snapshots); return 1; }
	public function lasterror() { return 'Simulated database failure'; }
}
final class DossierUser extends User
{
	public $denied = '';
	public function hasRight($module, $level1, $level2 = null, $default = null) { return $this->denied !== implode('.', array_filter(array($module, $level1, $level2))); }
}
final class InvoiceSourceProbe extends LmdbVehicleEvent
{
	public $calls = array(); public $failTrigger = false;
	public function call_trigger($action, $user) { $this->calls[] = array($action, $this->context); return $this->failTrigger ? -1 : 1; }
}
final class InvoiceServiceProbe extends LmdbVehicleSupplierInvoice
{
	public $source;
	public function fetchSource($type, $id) { if (!isset(self::SOURCE_TYPES[$type]) || $id <= 0) throw new RuntimeException('ErrorRecordNotFound'); return $this->source; }
}
final class DossierVehicleProbe extends LmdbVehicle
{
	public $indexCalls = array(); public $failIndex = false;
	public function indexFile($path, $update) { $this->indexCalls[] = array($path, $this->entity); return $this->failIndex && substr($path, -4) === '.zip' ? -1 : 1; }
}
final class DossierBuilderProbe extends LmdbVehicleDossier
{
	public $fixture;
	public function collect($vehicle, $langs) { return $this->fixture; }
}
final class DossierFooterHooks extends HookManager
{
	public function executeHooks($method, $parameters = array(), &$object = null, &$action = '')
	{
		$this->resPrint = $method === 'pdf_pagefoot' ? '<p>Annexe de pied personnalisée — contrôle qualité.</p>' : '';
		return 0;
	}
}
$db = new DossierDb();
$hookmanager = new HookManager($db);
$user = new DossierUser($db); $user->id = 1; $user->socid = 0;
$langs = new Translate('', $conf); $langs->setDefaultLang('fr_FR'); $langs->loadLangs(array('main', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
$mysoc = null;
$checks = 0;
function verifyDossier($condition, $label) { global $checks; if (!$condition) throw new RuntimeException($label); $checks++; }
function rejectsDossier(callable $operation, $message) { try { $operation(); } catch (RuntimeException $e) { verifyDossier($e->getMessage() === $message, 'Wrong rejection: '.$e->getMessage()); return; } throw new RuntimeException('Missing rejection: '.$message); }

if (($argv[2] ?? '') === 'access') {
	require_once dirname(__DIR__).'/class/actions_lmdbvehiclemanagement.class.php';
	$case = $argv[3] ?? 'read';
	$db->records['lmdbvehiclemanagement_vehicle'] = array(array('rowid' => 7, 'entity' => $case === 'entity' ? 2 : 1, 'ref' => 'VEH-TEST'));
	if ($case === 'invoice') $user->denied = 'fournisseur.facture.lire';
	if ($case === 'vehicle') $user->denied = 'lmdbvehiclemanagement.read';
	if ($case === 'external') $user->socid = 5;
	if ($case === 'public') { $user->id = 0; $_GET['hashp'] = 'publichash'; }
	if ($case === 'authenticated_public') $_GET['hashp'] = 'publichash';
	if ($case === 'readonly') $user->denied = 'lmdbvehiclemanagement.lmdbvehicle.write';
	$_SERVER['SCRIPT_NAME'] = '/viewimage.php';
	$fullpath_original_file = DOL_DATA_ROOT.'/lmdbvehiclemanagement/'.($case === 'path' ? 'OTHER' : 'VEH-TEST').'/'.($case === 'preview' ? 'thumbs/lmdb-dossier-7.pdf_preview.png' : 'lmdb-dossier-7.pdf');
	$accessHooks = new ActionsLmdbVehicleManagement($db); $obj = null; $action = '';
	register_shutdown_function(static function () { if (http_response_code() === 403) echo "DENIED\n"; });
	$accessHooks->setContentSecurityPolicy(array(), $obj, $action, $hookmanager);
	$accessHooks->downloadDocument(array('fullpath_original_file' => $fullpath_original_file), $obj, $action, $hookmanager);
	$accessHooks->checkSecureAccess(array('original_file' => $fullpath_original_file, 'fuser' => $user, 'entity' => 1), $obj, $action, $hookmanager);
	echo "ALLOWED\n"; exit;
}

$source = new InvoiceSourceProbe($db); $source->id = 7; $source->entity = 1;
$service = new InvoiceServiceProbe($db); $service->source = $source;
rejectsDossier(function () use ($service, $user) { $service->changeLink('tampered', 7, 9, $user); }, 'ErrorRecordNotFound');
foreach (array(0, 1) as $admin) {
	$user->admin = $admin;
	foreach (array('lmdbvehiclemanagement.read', 'lmdbvehiclemanagement.event.write', 'fournisseur.facture.lire', 'fournisseur.facture.creer') as $denied) {
		$user->denied = $denied;
		rejectsDossier(function () use ($service, $user) { $service->changeLink('event', 7, 9, $user); }, 'NotEnoughPermissions');
	}
}
$user->denied = ''; $user->admin = 0;
$source->entity = 2;
rejectsDossier(function () use ($service, $user) { $service->changeLink('event', 7, 9, $user); }, 'LmdbInvoiceSameEntity');
$source->entity = 1; $db->invoiceEntity = 2;
rejectsDossier(function () use ($service, $user) { $service->changeLink('event', 7, 9, $user); }, 'LmdbInvoiceSameEntity');
$db->invoiceEntity = 1;
verifyDossier($service->changeLink('event', 7, 9, $user) === 1, 'Native link creation');
verifyDossier(count($db->links) === 1 && $db->links[0]->sourcetype === 'lmdbvehiclemanagement_lmdbvehicleevent' && $db->links[0]->targettype === 'invoice_supplier', 'Canonical orientation');
verifyDossier($service->changeLink('event', 7, 9, $user) === 0 && count($source->calls) === 1, 'Idempotent duplicate and single CRUD');
verifyDossier($source->calls[0][1]['trigger_reason'] === 'supplier_invoice_link', 'Explicit relationship context');
verifyDossier($service->unlink(1, $source, $user) && !$db->links, 'Native unlink');
$db->failInsert = true;
rejectsDossier(function () use ($service, $user) { $service->changeLink('event', 7, 9, $user); }, 'LmdbInvoiceLinkFailed');
verifyDossier(!$db->links && !$db->snapshots, 'Link failure rolled back');
$db->failInsert = false; $source->failTrigger = true;
rejectsDossier(function () use ($service, $user) { $service->changeLink('event', 7, 9, $user); }, 'LmdbInvoiceLinkFailed');
verifyDossier(!$db->links && !$db->snapshots, 'CRUD failure rolled back');

$builder = new LmdbVehicleDossier($db);
$rowsMethod = new ReflectionMethod($builder, 'rows'); $db->paged = true;
$rows = iterator_to_array($rowsMethod->invoke($builder, 'SELECT rowid FROM fixture ORDER BY rowid'));
verifyDossier(count($rows) === 621 && $rows[620]->rowid === 620, 'All pages beyond 500'); $db->paged = false;
$describe = new ReflectionMethod($builder, 'describe');
$consumption = new LmdbVehicleConsumption($db); $consumption->fields = array('total_ttc' => $consumption->fields['total_ttc']); $consumption->currency_snapshot = 'EUR';
$unknown = html_entity_decode($langs->trans('NotDefined'), ENT_QUOTES, 'UTF-8');
$consumption->total_ttc = null; verifyDossier(strpos($describe->invoke($builder, $consumption, $langs), $unknown) !== false, 'Unknown price stays unknown');
$consumption->total_ttc = 0; verifyDossier(strpos($describe->invoke($builder, $consumption, $langs), $unknown) === false, 'Explicit zero stays known');

dol_mkdir(DOL_DATA_ROOT.'/source/sub');
file_put_contents(DOL_DATA_ROOT.'/source/report.txt', 'Épreuve originale');
file_put_contents(DOL_DATA_ROOT.'/source/sub/report.txt', 'Different original');
file_put_contents(DOL_DATA_ROOT.'/source/lmdb-dossier-7.zip', 'OLD DOSSIER MUST NOT BE INCLUDED');
$vehicle = new DossierVehicleProbe($db); $vehicle->id = 7; $vehicle->entity = 1; $vehicle->ref = 'VEH-TEST';
$vehicle->fields = array('ref' => $vehicle->fields['ref'], 'status' => $vehicle->fields['status']);
$emptyData = $builder->collect($vehicle, $langs);
verifyDossier(count($emptyData['sections']) === 7 && !$emptyData['files'], 'Complete empty dossier collection, excluding previous dossiers');
verifyDossier($langs->transnoentities('LmdbVehicleDossier') === 'Dossier véhicule', 'Module translations loaded');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
$formfile = new FormFile($db); $form = new Form($db);
$conf->browser = (object) array('layout' => 'classic');
$conf->use_javascript_ajax = 1;
$documentForm = $formfile->showdocuments('lmdbvehiclemanagement', $vehicle->ref, '', '/vehicle_document.php?id=7', 1, 0, 'lmdb_vehicle_dossier', 0, 1, 0, 0, 0, '', '', '', '', '', $vehicle);
verifyDossier(is_string($documentForm) && strpos($documentForm, 'name="model"') !== false && strpos($documentForm, 'name="token"') !== false && strpos($documentForm, 'value="builddoc"') !== false, 'Native generation form works for an empty vehicle dossier');
$invoiceSelector = $form->selectForForms('FactureFournisseur:fourn/class/fournisseur.facture.class.php:1:(t.entity:=:1)', 'lmdb_invoice_id', 0, 1, '', '', 'maxwidth300');
verifyDossier(strpos($invoiceSelector, 'lmdb_invoice_id') !== false && strpos($invoiceSelector, 'FA-9') !== false, 'Native supplier invoice selector resolves invoices');
$data = array('sections' => array(), 'files' => array(), 'warnings' => array());
$attachments = new ReflectionMethod($builder, 'attachments');
$args = array($vehicle, DOL_DATA_ROOT.'/source', 'vehicle', &$data, $langs);
$names = $attachments->invokeArgs($builder, $args);
verifyDossier(count($names) === 2 && count($data['files']) === 2, 'Hierarchy kept and old dossiers excluded');

if (!class_exists('ZipArchive')) { fwrite(STDERR, "ZipArchive required for dossier integration checks\n"); exit(2); }
$model = new pdf_lmdb_vehicle_dossier($db);
$fixtureBuilder = new DossierBuilderProbe($db);
$data['sections'] = array(array('title' => 'Caractéristiques techniques', 'rows' => array('Véhicule de test — Énergie électrique — 0 EUR')),
	array('title' => 'Historique complet', 'rows' => array_map(static function ($i) { return 'Événement '.$i.' — Contrôle réalisé — facture FA-'.$i; }, range(1, 621))),
	array('title' => 'Observations', 'rows' => array(str_repeat('Description longue avec accents et références. ', 100))));
$fixtureBuilder->fixture = $data;
$path = $fixtureBuilder->build($vehicle, $langs, array($model, 'writeSummary'));
verifyDossier(is_file($path) && substr(file_get_contents($path), 0, 4) === '%PDF', 'Native PDF generated');
verifyDossier(count($vehicle->indexCalls) === 2, 'Both files indexed');
$archive = new ZipArchive(); verifyDossier($archive->open(substr($path, 0, -4).'.zip') === true, 'ZIP opens');
verifyDossier($archive->numFiles === 3 && $archive->getFromName('vehicle/report.txt') === 'Épreuve originale', 'Original bytes in archive'); $archive->close();
$beforePdf = hash_file('sha256', $path); $beforeZip = hash_file('sha256', substr($path, 0, -4).'.zip');
rejectsDossier(function () use ($fixtureBuilder, $vehicle, $langs) { $fixtureBuilder->build($vehicle, $langs, static function () { throw new RuntimeException('PDF failure'); }); }, 'PDF failure');
verifyDossier(hash_file('sha256', $path) === $beforePdf && hash_file('sha256', substr($path, 0, -4).'.zip') === $beforeZip, 'Render failure preserves pair');
$vehicle->failIndex = true;
rejectsDossier(function () use ($fixtureBuilder, $vehicle, $langs, $model) { $fixtureBuilder->build($vehicle, $langs, array($model, 'writeSummary')); }, 'LmdbDossierWriteFailed');
verifyDossier(hash_file('sha256', $path) === $beforePdf && hash_file('sha256', substr($path, 0, -4).'.zip') === $beforeZip && !$db->snapshots, 'Index failure restores both originals');
$vehicle->failIndex = false; $vehicle->entity = 2; $vehicle->indexCalls = array();
$sharedPath = $fixtureBuilder->build($vehicle, $langs, array($model, 'writeSummary'));
verifyDossier(strpos($sharedPath, '/2/lmdbvehiclemanagement/') !== false && $vehicle->indexCalls[0][1] === 2 && $conf->entity === 1, 'Owner storage and indexing, consultation entity restored');
foreach (array('lmdbvehiclemanagement.read', 'lmdbvehiclemanagement.lmdbvehicle.write', 'fournisseur.facture.lire') as $denied) {
	$user->denied = $denied;
	rejectsDossier(function () use ($fixtureBuilder, $vehicle, $langs, $model) { $fixtureBuilder->build($vehicle, $langs, array($model, 'writeSummary')); }, 'NotEnoughPermissions');
}
$user->denied = '';
$vehicle->entity = 1;
$db->records = array(
	'lmdbvehiclemanagement_vehicle_event' => array(array('rowid' => 7, 'entity' => 1, 'fk_vehicle' => 7, 'ref' => 'EVT-7', 'label' => 'Entretien terminé', 'status' => 2, 'event_date' => '2026-09-01 12:00:00')),
	'lmdbvehiclemanagement_regulatory_control' => array(array('rowid' => 8, 'entity' => 1, 'fk_vehicle' => 7, 'ref' => 'CTL-8', 'status' => 1, 'control_date' => '2026-09-02 12:00:00')),
	'lmdbvehiclemanagement_consumption' => array(),
	'lmdbvehiclemanagement_odometer_reading' => array(array('rowid' => 21, 'entity' => 1, 'fk_vehicle' => 7, 'reading_date' => '2026-09-03 12:00:00', 'odometer_km' => 12500, 'reading_kind' => 'standard')),
);
foreach (array(25, null, 0) as $n => $amount) $db->records['lmdbvehiclemanagement_consumption'][] = array('rowid' => 11 + $n, 'entity' => 1, 'fk_vehicle' => 7, 'ref' => 'CON-'.(11 + $n), 'total_ttc' => $amount, 'quantity' => 5, 'category_snapshot' => 'additive', 'unit_snapshot' => 'L', 'currency_snapshot' => 'EUR', 'status' => 1, 'fk_odometer_reading' => 21);
$realService = new LmdbVehicleSupplierInvoice($db);
verifyDossier($realService->changeLink('control', 8, 9, $user) === 1, 'Validated control can be linked');
verifyDossier($db->records['lmdbvehiclemanagement_regulatory_control'][0]['status'] === 1, 'Regulatory status unchanged');
$db->links[] = (object) array('rowid' => 2, 'fk_source' => 7, 'sourcetype' => 'lmdbvehiclemanagement_lmdbvehicleevent', 'fk_target' => 9, 'targettype' => 'invoice_supplier');
$conf->supplier_invoice = (object) array('multidir_output' => array(1 => DOL_DATA_ROOT.'/supplier_invoice'));
$conf->fournisseur->facture = (object) array('dir_output' => DOL_DATA_ROOT.'/supplier_invoice', 'dir_temp' => DOL_DATA_ROOT.'/supplier_invoice/temp');
$db->invoiceCurrency = 'USD';
$populated = $builder->collect($vehicle, $langs);
verifyDossier(count($populated['sections'][2]['rows']) === 1 && count($populated['sections'][3]['rows']) === 1, 'Event and validated control collected');
verifyDossier(count($populated['sections'][4]['rows']) === 1 && strpos($populated['sections'][4]['rows'][0], 'USD') !== false, 'Shared invoice summarized once in its own currency');
verifyDossier(strpos($populated['sections'][2]['rows'][0], 'FA-9') !== false && strpos($populated['sections'][3]['rows'][0], 'FA-9') !== false, 'Every source keeps its invoice reference');
verifyDossier(count($populated['sections'][5]['rows']) === 3 && strpos($populated['sections'][5]['rows'][1], $unknown) !== false, 'All consumption records including absent and zero prices');
$originalOutput = $conf->lmdbvehiclemanagement->multidir_output[1];
$conf->lmdbvehiclemanagement->multidir_output[1] = DOL_DATA_ROOT.'/annexes/module';
$conf->supplier_invoice->multidir_output[1] = DOL_DATA_ROOT.'/annexes/invoices';
$conf->fournisseur->facture->dir_output = DOL_DATA_ROOT.'/annexes/invoices';
$event = $realService->fetchSource('event', 7);
$control = $realService->fetchSource('control', 8);
$invoice = new FactureFournisseur($db); $invoice->fetch(9);
$documentDirs = array(getMultidirOutput($vehicle, 'lmdbvehiclemanagement', 1), getMultidirOutput($event, 'lmdbvehiclemanagement', 1),
	getMultidirOutput($control, 'lmdbvehiclemanagement', 1), getMultidirOutput($invoice).'/'.get_exdir(9, 2, 0, 0, $invoice, 'invoice_supplier').dol_sanitizeFileName($invoice->ref));
foreach ($documentDirs as $index => $documentDir) { dol_mkdir($documentDir); file_put_contents($documentDir.'/original.txt', 'Original '.$index); }
$receipt = new LmdbVehicleConsumption($db); $receipt->fetch(11);
$receiptDir = getMultidirOutput($receipt, 'lmdbvehiclemanagement', 1);
dol_mkdir($receiptDir); file_put_contents($receiptDir.'/receipt.txt', 'EXCLUDED CONSUMPTION RECEIPT');
$annexes = $builder->collect($vehicle, $langs);
verifyDossier(count($annexes['files']) === 4, 'Vehicle, event, control and shared invoice originals collected once');
verifyDossier(count(array_filter($annexes['files'], static function ($file) { return strpos($file['path'], 'invoices/') === 0; })) === 1, 'Shared invoice attachment deduplicated');
verifyDossier(strpos(implode('|', array_column($annexes['files'], 'source')), 'receipt.txt') === false, 'Consumption receipts excluded');
$fixtureBuilder->fixture = $annexes;
$annexPdf = $fixtureBuilder->build($vehicle, $langs, array($model, 'writeSummary'));
$archive->open(substr($annexPdf, 0, -4).'.zip');
verifyDossier($archive->numFiles === 5, 'ZIP contains the summary and four original documents');
foreach ($annexes['files'] as $file) verifyDossier($archive->getFromName($file['path']) === file_get_contents($file['source']), 'Original attachment remains byte-identical');
$archive->close();
$conf->lmdbvehiclemanagement->multidir_output[1] = $originalOutput;
// Exercise the real native footer with HTML, company details and an extension hook.
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
$mysoc = new Societe($db); $mysoc->name = 'Entreprise Épreuve'; $mysoc->address = 'Rue des Métiers'; $mysoc->zip = '75001'; $mysoc->town = 'Paris'; $mysoc->country_code = 'FR';
$conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS = 1;
$conf->global->PDF_ALLOW_HTML_FOR_FREE_TEXT = 1;
$conf->global->LMDBVEHICLEMANAGEMENT_DOSSIER_FREE_TEXT = '<p>'.str_repeat('Texte libre accentué du dossier. ', 30).'</p>';
$hookmanager = new DossierFooterHooks($db);
$model->writeSummary($vehicle, $data, $langs, DOL_DATA_ROOT.'/footer-long.pdf');
verifyDossier(is_file(DOL_DATA_ROOT.'/footer-long.pdf'), 'Native multipage footer with long HTML, company details and hook');
$conf->global->LMDBVEHICLEMANAGEMENT_DOSSIER_FREE_TEXT = '';
$model->writeSummary($vehicle, $emptyData, $langs, DOL_DATA_ROOT.'/footer-empty.pdf');
verifyDossier(is_file(DOL_DATA_ROOT.'/footer-empty.pdf'), 'Empty dossier and empty free text with native company footer');
$conf->global->LMDBVEHICLEMANAGEMENT_DOSSIER_FREE_TEXT = 'Texte court.';
$model->writeSummary($vehicle, $emptyData, $langs, DOL_DATA_ROOT.'/footer-short.pdf');
verifyDossier(is_file(DOL_DATA_ROOT.'/footer-short.pdf'), 'Short free text footer');
$hookmanager = new HookManager($db);
$db->ecm = array((object) array('filepath' => 'missing', 'filename' => 'absent.pdf'));
$missing = array('sections' => array(), 'files' => array(), 'warnings' => array());
$args = array($vehicle, DOL_DATA_ROOT.'/missing', 'vehicle', &$missing, $langs);
$attachments->invokeArgs($builder, $args);
verifyDossier(count($missing['warnings']) === 1, 'Indexed missing document reported');
require_once dirname(__DIR__).'/class/actions_lmdbvehiclemanagement.class.php';
$hooks = new ActionsLmdbVehicleManagement($db);
$draftInvoice = new FactureFournisseur($db);
$_POST = array('lmdb_source_type' => 'control', 'lmdb_source_id' => 8);
$action = 'add';
verifyDossier($hooks->doActions(array('context' => 'invoicesuppliercard'), $draftInvoice, $action, $hookmanager) === 0 && $draftInvoice->context['lmdb_invoice_source']['id'] === 8, 'Native creation receives validated source context');
$action = 'create';
$hooks->formObjectOptions(array(), $draftInvoice, $action, $hookmanager);
verifyDossier(strpos($hooks->resprints, 'name="lmdb_source_type" value="control"') !== false && strpos($hooks->resprints, 'name="lmdb_source_id" value="8"') !== false, 'Source retained through native form redisplay');
$action = 'add'; $user->denied = 'lmdbvehiclemanagement.regulatorycontrol.write';
verifyDossier($hooks->doActions(array('context' => 'invoicesuppliercard'), $draftInvoice, $action, $hookmanager) === -1 && $action === 'create', 'Forged source rejected before native creation');
$user->denied = ''; $_POST = array();
require_once dirname(__DIR__).'/core/triggers/interface_99_modLmdbVehicleManagement_LmdbVehicleManagementTriggers.class.php';
$trigger = new InterfaceLmdbVehicleManagementTriggers($db);
require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
$publicDocument = new EcmFiles($db); $publicDocument->filename = 'LMDB-DOSSIER-7.PDF'; $publicDocument->share = 1;
verifyDossier($trigger->runTrigger('ECMFILES_MODIFY', $publicDocument, $user, $langs, $conf) === -1, 'Public sharing rejected, including case variants');
$renameAction = '';
verifyDossier($hooks->renameUploadedFile(array('filenamefrom' => 'lmdb-dossier-7.pdf', 'filenameto' => 'public.pdf'), $vehicle, $renameAction, $hookmanager) === -1, 'Protected file cannot be renamed to bypass access rules');
verifyDossier($hooks->renameUploadedFile(array('filenamefrom' => 'ordinary.pdf', 'filenameto' => 'ordinary-renamed.pdf'), $vehicle, $renameAction, $hookmanager) === 0, 'Ordinary native rename remains available');
$draftInvoice->id = 9; $db->links = array(); $db->failInsert = true;
verifyDossier($trigger->runTrigger('BILL_SUPPLIER_CREATE', $draftInvoice, $user, $langs, $conf) === -1 && !$db->links && !$db->snapshots, 'Failed native creation link propagates rollback to invoice trigger caller');
$db->failInsert = false;
foreach (array('read' => 'ALLOWED', 'readonly' => 'ALLOWED', 'preview' => 'ALLOWED', 'invoice' => 'DENIED', 'vehicle' => 'DENIED', 'external' => 'DENIED', 'public' => 'DENIED', 'authenticated_public' => 'DENIED', 'entity' => 'DENIED', 'path' => 'DENIED') as $case => $expected) {
	$process = proc_open(array(PHP_BINARY, __FILE__, DOL_DOCUMENT_ROOT, 'access', $case), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
	if (!is_resource($process)) throw new RuntimeException('Cannot start access test');
	$output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
	verifyDossier($code === 0 && trim($output) === $expected && $errors === '', 'Access '.$case.': '.$output.$errors);
}
echo $checks." invoice/dossier checks passed\nPDF fixture: ".$path."\n";
