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
$insurancePage = readModuleSource('vehicle_insurance.php');
$insuranceAdmin = readModuleSource('admin/insurance.php');
$descriptor = readModuleSource('core/modules/modLmdbVehicleManagement.class.php');
$insuranceCron = readModuleSource('class/lmdbvehicleinsurancecron.class.php');
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
$checks['insurance_description_uses_native_wysiwyg'] = strpos($insurancePage, "new DolEditor('contract_description'") !== false;
$checks['insurance_allows_new_contract_after_first_one'] = strpos($insurancePage, "new_contract=1") !== false;
$checks['description_uses_restricted_html_input'] = strpos($vehicleCard, "GETPOST('description', 'restricthtml')") !== false;
$checks['status_row_is_hidden'] = strpos($vehicleCard, "langs->trans('Status')") === false;
$checks['actions_use_native_buttons'] = strpos($vehicleCard, "dolGetButtonAction('', \$langs->trans('Validate')") !== false;
$ficheEndPosition = strpos($vehicleCard, 'print dol_get_fiche_end();');
$tabsActionPosition = strpos($vehicleCard, 'print \'<div class="tabsAction">\';');
$checks['actions_follow_native_fiche_end'] = $ficheEndPosition !== false && $tabsActionPosition !== false && $ficheEndPosition < $tabsActionPosition;
$settingsPosition = strpos($library, '/admin/setup.php');
$insurancePosition = strpos($library, '/admin/insurance.php');
$compatibilityPosition = strpos($library, '/admin/compatibility.php');
$checks['insurance_admin_tab_order'] = $settingsPosition !== false && $insurancePosition !== false && $compatibilityPosition !== false && $settingsPosition < $insurancePosition && $insurancePosition < $compatibilityPosition;
$checks['insurance_summary_is_in_half_right'] = strpos($vehicleCard, '<div class="fichehalfright">') !== false && strpos($vehicleCard, 'lmdbVehiclePrintInsuranceBlock($object)') !== false;
$checks['insurance_uses_modal_route'] = strpos($vehicleCard, 'mode=modal') !== false && strpos($insurancePage, "\$isModal = \$mode === 'modal'") !== false;
$checks['insurance_modal_mutations_return_json'] = strpos($insurancePage, "header('Content-Type: application/json; charset=UTF-8')") !== false;
$checks['insurance_fallback_has_vehicle_banner'] = strpos($insurancePage, 'lmdbVehiclePrintBanner($vehicle)') !== false;
$checks['insurance_mutations_use_csrf_tokens'] = substr_count($insurancePage, "name=\"token\" value=\"'.newToken()") >= 3;
$checks['insurance_uses_native_permission_checks'] = strpos($insurancePage, "\$user->hasRight('lmdbvehiclemanagement', 'insurance', 'write')") !== false
	&& strpos($insurancePage, "\$user->hasRight('lmdbvehiclemanagement', 'insurance', 'upload')") !== false
	&& strpos($insurancePage, "\$user->hasRight('lmdbvehiclemanagement', 'insurance', 'validate')") !== false
	&& strpos($insurancePage, "\$user->hasRight('lmdbvehiclemanagement', 'insurance', 'delete')") !== false;
$checks['insurance_download_is_read_only_route'] = strpos($insurancePage, '$downloadCertificate === 1') !== false && strpos($insurancePage, "\$action === 'download_certificate'") === false;
$checks['insurance_admin_uses_native_selects_and_switches'] = strpos($insuranceAdmin, 'ajax_constantonoff(') !== false && strpos($insuranceAdmin, "multiselectarray('recipient_users'") !== false && strpos($insuranceAdmin, "multiselectarray('recipient_groups'") !== false;
$checks['insurance_cron_is_declared'] = strpos($descriptor, "'method' => 'sendCertificateReminders'") !== false && strpos($insuranceCron, 'INSERT IGNORE INTO') !== false;
$checks['insurance_version_is_030'] = strpos($descriptor, "\$this->version = '0.3.0';") !== false;

$failed = array_keys(array_filter($checks, static function ($result) {
	return !$result;
}));
if (!empty($failed)) {
	fwrite(STDERR, 'Failed UI contract checks: '.implode(', ', $failed).PHP_EOL);
	exit(1);
}

print count($checks).' UI contract checks passed'.PHP_EOL;
