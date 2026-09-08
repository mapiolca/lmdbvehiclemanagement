<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res) die('Include of main fails');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');
$langs->loadLangs(array('admin', 'multicompany@multicompany', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!LmdbVehicleSharing::isAdmin($user) || !isModEnabled('lmdbvehiclemanagement')) accessforbidden();
$extrajs = array();
if (LmdbVehicleSharing::available() && empty($user->entity)) {
	// ajax_mcconstantonoff() requires the same script as native granularity.php.
	$extrajs[] = '/multicompany/core/js/lib_head.js';
}
llxHeader('', $langs->trans('LmdbIndividualSharing'), '', '', '', '', $extrajs);
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=lmdbvehiclemanagement">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('LmdbIndividualSharing'), $linkback, 'car');
print dol_get_fiche_head(lmdbVehicleManagementAdminPrepareHead(), 'sharing', $langs->trans('ModuleLmdbVehicleManagementName'), -1, 'car');
if (!LmdbVehicleSharing::available()) {
	print '<div class="warning">'.$langs->trans('LmdbSharingRequiresMulticompany21').'</div>';
} elseif (!empty($user->entity)) {
	// Native sharing modes are global; only the native super-administrator edits them.
	print '<div class="info">'.$langs->trans('LmdbSharingGlobalAdminOnly').'</div>';
} else {
	dol_include_once('/multicompany/lib/multicompany.lib.php');
	print '<p>'.$langs->trans('LmdbSharingSetupHelp').'</p>';
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Type').'</th><th>'.$langs->trans('LmdbIndividualSharing').'</th><th>'.$langs->trans('LmdbSharingAllByDefault').'</th></tr>';
	foreach (LmdbVehicleSharing::definitions() as $element => $definition) {
		print '<tr class="oddeven"><td>'.$langs->trans($definition['label']).'</td><td>';
		// Same global flags/endpoint as native Multicompany granularity, including CSRF.
		print ajax_mcconstantonoff('MULTICOMPANY_'.strtoupper($element).'_SHARING_BYELEMENT_ENABLED', array(), 0, 0, 0, 1);
		print '</td><td>'.ajax_mcconstantonoff('MULTICOMPANY_'.strtoupper($element).'_SHARE_ALL_BY_DEFAULT', array(), 0, 0, 0, 1).'</td></tr>';
	}
	print '</table></div>';
}
print dol_get_fiche_end();
llxFooter();
$db->close();
