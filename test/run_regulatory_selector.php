<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Execute the card's selection/bootstrap code with native Form and dependencies.
// Synthetic data only; no ERP session or database writes.
$core = isset($argv[1]) ? realpath($argv[1]) : false;
if (!$core || !is_file($core.'/core/class/commonobject.class.php')) {
	fwrite(STDERR, "Usage: php test/run_regulatory_selector.php <Dolibarr htdocs> [--fixture]\n");
	exit(2);
}
define('DOL_DOCUMENT_ROOT', $core);
$conf = (object) array('use_javascript_ajax' => 1);
$langs = new class { public function trans($value) { return $value; } };
function getDolGlobalString($key, $default = '') { return $key === 'MAIN_USE_JQUERY_MULTISELECT' ? '1' : $default; }
function getNonce() { return 'fixture'; }
function dol_escape_htmltag($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function dol_escape_js($value) { return addslashes((string) $value); }
function GETPOSTISSET($key) { global $request; return isset($request[$key]); }
function GETPOSTINT($key) { global $request; return (int) ($request[$key] ?? 0); }
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
$source = file_get_contents(dirname(__DIR__).'/regulatorycontrol_card.php');
if (!is_string($source)) throw new RuntimeException('Card source not found');
$start = strpos($source, "if (\$action === 'create' && !GETPOSTISSET('fk_vehicle'))");
$scriptStart = strpos($source, "\t// Prepare native dependencies");
if ($start === false || $scriptStart === false) throw new RuntimeException('Card fixture start not found');
$end = strpos($source, "if (\$action === 'create' && empty(\$object->fk_previous_control))", $start);
$scriptEnd = strpos($source, '} elseif ($id > 0) {', $scriptStart);
if ($end === false || $scriptEnd === false) throw new RuntimeException('Card fixture end not found');
$selectionCode = substr($source, $start, $end - $start);
$scriptCode = substr($source, $scriptStart, $scriptEnd - $scriptStart);
$requirementRules = array(
	11 => array('vehicle' => 1, 'rule' => 101, 'label' => 'EN-026-EX — Contrôle technique'),
	12 => array('vehicle' => 1, 'rule' => 102, 'label' => 'EN-026-EX — Pollution'),
	21 => array('vehicle' => 2, 'rule' => 101, 'label' => 'FY-765-CT — Contrôle technique'),
	22 => array('vehicle' => 2, 'rule' => 102, 'label' => 'FY-765-CT — Pollution'),
);
$cases = array(
	'blank' => array('create', array(), 0, 0, 0, 0, array()),
	'vehicle' => array('create', array('vehicle_id' => 1), 0, 0, 1, 0, array(11, 12)),
	'schedule' => array('create', array('requirement_id' => 21), 0, 0, 2, 21, array(21, 22)),
	'conflicting_link' => array('create', array('vehicle_id' => 1, 'requirement_id' => 21), 0, 0, 1, 0, array(11, 12)),
	'failed_post' => array('create', array('fk_vehicle' => 1, 'vehicle_id' => 2), 1, 21, 1, 0, array(11, 12)),
	'failed_post_empty_vehicle' => array('create', array('fk_vehicle' => 0), 0, 21, 0, 0, array()),
	'edit' => array('edit', array(), 1, 12, 1, 12, array(11, 12)),
	'no_requirements' => array('create', array('vehicle_id' => 3), 0, 0, 3, 0, array()),
);
$fixtures = array();
foreach ($cases as $name => $case) {
	list($action, $request, $vehicle, $requirement, $expectedVehicle, $expectedRequirement, $expectedOptions) = $case;
	$object = new class extends CommonObject { public $fk_vehicle = 0; public $fk_requirement = 0; public $fk_rule = 0; };
	$object->fk_vehicle = $vehicle;
	$object->fk_requirement = $requirement;
	$requirementOptions = array();
	eval($selectionCode);
	if ($object->fk_vehicle !== $expectedVehicle || $object->fk_requirement !== $expectedRequirement || array_keys($requirementOptions) !== $expectedOptions) {
		throw new RuntimeException('Invalid selection: '.$name);
	}
	$html = Form::selectarray('fk_vehicle', array(1 => 'EN-026-EX', 2 => 'FY-765-CT', 3 => 'No requirement'), $object->fk_vehicle, 1);
	$html .= Form::selectarray('fk_requirement', $requirementOptions, $object->fk_requirement, 1);
	ob_start();
	eval($scriptCode);
	$html .= ob_get_clean();
	$fixtures[$name] = $html;
}
echo in_array('--fixture', $argv, true) ? json_encode($fixtures, JSON_THROW_ON_ERROR) : count($cases)." regulatory selector cases passed (native rendering, synthetic data)\n";
