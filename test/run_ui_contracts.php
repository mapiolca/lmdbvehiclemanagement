<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$moduleRoot = dirname(__DIR__);

/**
 * Read a module source file for static UI contract checks.
 *
 * @param string $relativePath Path relative to the module root
 * @return string
 */
function readModuleSource($relativePath)
{
	global $moduleRoot;

	$content = file_get_contents($moduleRoot.'/'.$relativePath);
	if (!is_string($content)) {
		fwrite(STDERR, 'Unable to read '.$relativePath.PHP_EOL);
		exit(1);
	}

	return $content;
}

$library = readModuleSource('lib/lmdbvehiclemanagement.lib.php');
$vehicleCard = readModuleSource('vehicle_card.php');
$checks = array();

$orderedTabs = array(
	'vehicle_card.php',
	'vehicle_assignment.php',
	'vehicle_odometer.php',
	'vehicle_history.php',
	'vehicle_note.php',
	'vehicle_document.php',
	'vehicle_agenda.php',
);
$previousPosition = -1;
foreach ($orderedTabs as $tabFile) {
	$position = strpos($library, $tabFile);
	$checks['tab_order_'.$tabFile] = $position !== false && $position > $previousPosition;
	if ($position !== false) {
		$previousPosition = $position;
	}
}

$vehicleTabPages = array(
	'vehicle_card.php',
	'vehicle_assignment.php',
	'vehicle_odometer.php',
	'vehicle_history.php',
	'vehicle_note.php',
	'vehicle_document.php',
	'vehicle_agenda.php',
);
foreach ($vehicleTabPages as $pageFile) {
	$checks['common_banner_'.$pageFile] = strpos(readModuleSource($pageFile), 'lmdbVehiclePrintBanner(') !== false;
}

$checks['banner_uses_native_helper'] = strpos($library, 'dol_banner_tab(') !== false;
$checks['banner_keeps_multicompany_badge'] = strpos($library, 'multicompany-entity-card-container') !== false;
$checks['description_uses_native_wysiwyg'] = strpos($vehicleCard, "new DolEditor('description'") !== false;
$checks['description_uses_restricted_html_input'] = strpos($vehicleCard, "GETPOST('description', 'restricthtml')") !== false;
$checks['status_row_is_hidden'] = strpos($vehicleCard, "langs->trans('Status')") === false;
$checks['actions_use_native_buttons'] = strpos($vehicleCard, "dolGetButtonAction('', \$langs->trans('Validate')") !== false;
$ficheEndPosition = strpos($vehicleCard, 'print dol_get_fiche_end();');
$tabsActionPosition = strpos($vehicleCard, 'print \'<div class="tabsAction">\';');
$checks['actions_follow_native_fiche_end'] = $ficheEndPosition !== false && $tabsActionPosition !== false && $ficheEndPosition < $tabsActionPosition;

$failed = array_keys(array_filter($checks, static function ($result) {
	return !$result;
}));
if (!empty($failed)) {
	fwrite(STDERR, 'Failed UI contract checks: '.implode(', ', $failed).PHP_EOL);
	exit(1);
}

print count($checks).' UI contract checks passed'.PHP_EOL;
