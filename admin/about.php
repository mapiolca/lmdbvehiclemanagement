<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');
dol_include_once('/lmdbvehiclemanagement/core/modules/modLmdbVehicleManagement.class.php');

/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('admin', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (empty($user->admin)) {
	accessforbidden();
}

$descriptor = new modLmdbVehicleManagement($db);
$title = $langs->trans('About').' — '.$langs->trans('ModuleLmdbVehicleManagementName');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=lmdbvehiclemanagement">'.img_picto('', 'back', 'class="pictofixedwidth"').$langs->trans('BackToModuleList').'</a>';
llxHeader('', $title);
print load_fiche_titre($title, $linkback, 'info');
$head = lmdbVehicleManagementAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $title, -1, 'car');

print '<div class="fichecenter">';
print '<div class="fichehalfleft"><div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('ModuleInformation').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Name').'</td><td>'.$langs->trans('ModuleLmdbVehicleManagementName').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Version').'</td><td>'.dol_escape_htmltag($descriptor->version).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Publisher').'</td><td>'.dol_escape_htmltag($descriptor->editor_name).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Description').'</td><td>'.$langs->trans($descriptor->description).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('License').'</td><td>'.dol_escape_htmltag($descriptor->license).'</td></tr>';
print '<tr class="oddeven"><td>PHP</td><td>&ge; '.implode('.', $descriptor->phpmin).'</td></tr>';
print '<tr class="oddeven"><td>Dolibarr</td><td>&ge; '.implode('.', $descriptor->need_dolibarr_version).'</td></tr>';
print '</table></div></div>';

print '<div class="fichehalfright"><div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('MainFeatures').'</th></tr>';
foreach (array('Vehicles', 'VehicleAssignments', 'OdometerReadings', 'VehicleEvents', 'VehicleHistory', 'InsuranceContracts', 'InsuranceDedicatedNavigation', 'InsuranceCertificates', 'InsuranceCertificateReminderCronLabel', 'FeatureMulticompanySharing') as $feature) {
	print '<tr class="oddeven"><td>'.img_picto('', 'check', 'class="pictofixedwidth"').$langs->trans($feature).'</td></tr>';
}
print '<tr class="liste_titre"><th>'.$langs->trans('Dependencies').'</th></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('NoMandatoryDependency').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('OptionalDependencies').': Agenda, Multicompany, Ressources</td></tr>';
print '<tr class="oddeven"><td><a href="'.dol_escape_htmltag($descriptor->editor_url).'" target="_blank" rel="noopener">'.dol_escape_htmltag($descriptor->editor_url).'</a></td></tr>';
print '</table></div></div>';
print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
