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
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementcompatibility.class.php');

/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('admin', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (empty($user->admin)) {
	accessforbidden();
}

$title = $langs->trans('Compatibility');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=lmdbvehiclemanagement">'.img_picto('', 'back', 'class="pictofixedwidth"').$langs->trans('BackToModuleList').'</a>';
llxHeader('', $title);
print load_fiche_titre($title, $linkback, 'technic');
$head = lmdbVehicleManagementAdminPrepareHead();
print dol_get_fiche_head($head, 'compatibility', $title, -1, 'car');

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('DetectedVersion').'</td><td>'.$langs->trans('MinimumVersion').'</td><td>'.$langs->trans('Status').'</td></tr>';
$dolibarrOk = version_compare(DOL_VERSION, '20.0.0', '>=');
$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
print '<tr class="oddeven"><td>Dolibarr '.dol_escape_htmltag(DOL_VERSION).'</td><td>20.0.0</td><td>'.dolGetStatus($langs->trans($dolibarrOk ? 'Available' : 'Unavailable'), '', '', $dolibarrOk ? 'status4' : 'status8', 5).'</td></tr>';
print '<tr class="oddeven"><td>PHP '.dol_escape_htmltag(PHP_VERSION).'</td><td>8.0.0</td><td>'.dolGetStatus($langs->trans($phpOk ? 'Available' : 'Unavailable'), '', '', $phpOk ? 'status4' : 'status8', 5).'</td></tr>';
print '</table>';
print '</div><br>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('MainFeatures').'</td><td>Dolibarr</td><td>PHP</td><td>'.$langs->trans('Status').'</td><td>'.$langs->trans('UnavailableReason').'</td></tr>';
foreach (LmdbVehicleManagementCompatibility::getCompatibilityFeatures() as $feature) {
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans($feature['label']).'</td>';
	print '<td>&ge; '.dol_escape_htmltag($feature['min_dolibarr']).'</td>';
	print '<td>&ge; '.dol_escape_htmltag($feature['min_php']).'</td>';
	print '<td>'.dolGetStatus($langs->trans($feature['available'] ? 'Available' : 'Unavailable'), '', '', $feature['available'] ? 'status4' : 'status6', 5).'</td>';
	print '<td>'.($feature['reason'] !== '' ? $langs->trans($feature['reason']) : '').'</td>';
	print '</tr>';
}
print '</table>';
print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
