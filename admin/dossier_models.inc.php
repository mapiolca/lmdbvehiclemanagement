<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
if (!defined('DOL_DOCUMENT_ROOT')) exit;
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
/**
 * @var DoliDB $db
 * @var Translate $langs
 */
$dossierModels = getListOfModels($db, 'lmdbvehicle');
$dossierActive = is_array($dossierModels) && isset($dossierModels['lmdb_vehicle_dossier']);
print load_fiche_titre($langs->trans('DocumentModels'), '', 'pdf');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('Name').'</th><th>'.$langs->trans('Description').'</th><th class="center">'.$langs->trans('Status').'</th><th class="center">'.$langs->trans('Default').'</th></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbVehicleDossier').'</td><td>'.$langs->trans('LmdbVehicleDossierDescription').'</td><td class="center">';
print '<a href="'.$_SERVER['PHP_SELF'].'?action='.($dossierActive ? 'disable_dossier' : 'enable_dossier').'&token='.newToken().'">'.img_picto($langs->trans($dossierActive ? 'Disable' : 'Activate'), $dossierActive ? 'switch_on' : 'switch_off').'</a></td><td class="center">';
if ($dossierActive) print '<a href="'.$_SERVER['PHP_SELF'].'?action=default_dossier&token='.newToken().'">'.img_picto($langs->trans('Default'), getDolGlobalString('LMDBVEHICLEMANAGEMENT_DOSSIER_MODEL') === 'lmdb_vehicle_dossier' ? 'on' : 'off').'</a>';
print '</td></tr></table></div>';
if (!LmdbVehicleManagementCompatibility::isFeatureAvailable('vehicle_dossier')) print '<div class="warning">'.$langs->trans(LmdbVehicleManagementCompatibility::getCompatibilityFeatures()['vehicle_dossier']['reason']).'</div>';
