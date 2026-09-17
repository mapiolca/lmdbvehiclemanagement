<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Execute the page's qualification rendering with native Form and synthetic data.
$core = isset($argv[1]) ? realpath($argv[1]) : false;
if (!$core || !is_file($core.'/core/lib/functions.lib.php')) {
	fwrite(STDERR, "Usage: php test/run_qualification_modal.php <Dolibarr htdocs> [--fixture]\n"); exit(2);
}
define('DOL_DOCUMENT_ROOT', $core);
define('DOL_URL_ROOT', '');
define('MAIN_DB_PREFIX', 'qualification_test_');
if (is_file($core.'/version.inc.php')) require_once $core.'/version.inc.php';
else {
	preg_match('/define\([\'"]DOL_VERSION[\'"],\s*[\'"]([^\'"]+)[\'"]\)/', file_get_contents($core.'/filefunc.inc.php'), $version);
	define('DOL_VERSION', $version[1]);
}
date_default_timezone_set('Europe/Paris');
require_once $core.'/core/class/conf.class.php';
$conf = new Conf();
$conf->global = (object) array('MAIN_USE_JQUERY_MULTISELECT' => 'select2');
$conf->use_javascript_ajax = 1;
$conf->entity = 1;
$conf->theme = 'eldy';
$conf->tzuserinputkey = 'tzserver';
$conf->file->dol_document_root = array('main' => $core, 'alt0' => dirname(__DIR__, 2));
$conf->file->dol_url_root = array('main' => '', 'alt0' => '/custom');
require_once $core.'/core/lib/functions.lib.php';
require_once $core.'/core/lib/date.lib.php';
if (is_file($core.'/core/lib/html.lib.php')) require_once $core.'/core/lib/html.lib.php';
require_once $core.'/core/class/translate.class.php';
require_once $core.'/core/class/html.form.class.php';
$langs = new Translate('', $conf);
$langs->setDefaultLang('fr_FR');
$langs->loadLangs(array('main', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
$user = (object) array('conf' => (object) array());
$_SESSION['newtoken'] = 'qualification-fixture-token';
$_SERVER['PHP_SELF'] = '/vehicle_regulatory.php';
$form = new Form(new stdClass());
$source = file_get_contents(dirname(__DIR__).'/vehicle_regulatory.php');
$start = strpos($source, "if (\$action === 'save_qualification')");
$end = strpos($source, "if (\$action === 'grant_derogation')", $start);
$saveCode = substr($source, $start, $end - $start);
function lmdbVehicleManagementSetObjectErrors($object) {}
$start = strpos($source, '$qualificationComplete =');
$end = strpos($source, '$qualificationSaveFailed =', $start);
$statusCode = substr($source, $start, $end - $start);
$start = strpos($source, "print load_fiche_titre(\$langs->trans('RegulatoryQualification')");
$end = strpos($source, "print '<div class=\"tabsAction\">';", $start);
$renderCode = substr($source, $start, $end - $start);
$baseQuestion = array('id' => 1, 'code' => 'taxi', 'label' => 'RegQuestionTaxi', 'description' => '', 'date_label' => 'Date', 'answer_choice_id' => 2, 'answer_code' => 'yes', 'applicable_since' => strtotime('2026-01-12 12:00:00'), 'choices' => array(
	1 => array('id' => 1, 'code' => 'unknown', 'label' => 'Unknown', 'requires_date' => 0),
	2 => array('id' => 2, 'code' => 'yes', 'label' => 'Yes', 'requires_date' => 1),
	3 => array('id' => 3, 'code' => 'no', 'label' => 'No', 'requires_date' => 0),
));
$fixtures = array();
foreach (array('confirmed', 'readonly', 'incomplete', 'missing_date', 'empty', 'fallback', 'failed') as $name) {
	$id = 42;
	$permissionWrite = $name !== 'readonly';
	$_POST = array();
	$_GET = $name === 'fallback' ? array('show_qualification' => '1') : array();
	$questionnaire = array(1 => $baseQuestion);
	for ($questionId = 2; $questionId <= 10; $questionId++) {
		$questionnaire[$questionId] = array_replace($baseQuestion, array('id' => $questionId, 'label' => 'Question '.$questionId, 'date_label' => '', 'answer_choice_id' => 3, 'answer_code' => 'no', 'applicable_since' => 0));
	}
	if ($name === 'incomplete') { $questionnaire[1]['answer_code'] = 'unknown'; $questionnaire[1]['answer_choice_id'] = 1; }
	if ($name === 'missing_date') $questionnaire[1]['applicable_since'] = 0;
	if ($name === 'empty') $questionnaire = array();
	eval($statusCode);
	if ($qualificationComplete !== !in_array($name, array('incomplete', 'missing_date', 'empty'), true)) throw new RuntimeException('Incorrect qualification status: '.$name);
	$qualificationSaveFailed = false;
	if ($name === 'failed') {
		$action = 'save_qualification';
		$_POST = array('answer_choice_1' => '3', 'answer_date_1day' => '16', 'answer_date_1month' => '9', 'answer_date_1year' => '2026', 'manual_profile_ids' => array('10', '11'));
		$vehicle = new class {
			public $received = array();
			public function saveRegulatoryQualification($answers, $profiles, $user) { $this->received = array($answers, $profiles); return -1; }
		};
		eval($saveCode);
		if (!$qualificationSaveFailed || $vehicle->received[1] !== array(10, 11) || $questionnaire[1]['answer_choice_id'] !== 3
			|| date('Y-m-d', $questionnaire[1]['applicable_since']) !== '2026-09-16') throw new RuntimeException('Failed submission lost its fields');
	}
	$profileOptions = array(10 => 'Profil A', 11 => 'Profil B');
	$selectedProfiles = array(10);
	ob_start();
	eval($renderCode);
	$fixtures[$name] = ob_get_clean();
}
echo in_array('--fixture', $argv, true) ? json_encode($fixtures, JSON_THROW_ON_ERROR) : count($fixtures)." qualification rendering scenarios passed\n";
