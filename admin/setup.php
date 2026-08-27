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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleevent.class.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('admin', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (empty($user->admin)) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$objectType = GETPOST('object', 'aZ09');
$value = GETPOST('value', 'aZ09');
$backtopage = GETPOST('backtopage', 'alphanohtml');
/** @var array<string,array{label:string,class:class-string,model:string,constant:string}> $models */
$models = array(
	'lmdbvehicle' => array(
		'label' => 'Vehicle',
		'class' => 'LmdbVehicle',
		'model' => 'mod_lmdbvehicle_standard',
		'constant' => 'LMDBVEHICLEMANAGEMENT_LMDBVEHICLE_ADDON',
	),
	'lmdbvehicleevent' => array(
		'label' => 'VehicleEvent',
		'class' => 'LmdbVehicleEvent',
		'model' => 'mod_lmdbvehicleevent_standard',
		'constant' => 'LMDBVEHICLEMANAGEMENT_LMDBVEHICLEEVENT_ADDON',
	),
);

if ($action === 'setmod') {
	if (!isset($models[$objectType]) || $value !== $models[$objectType]['model']) {
		accessforbidden($langs->trans('ErrorBadValueForParameter', 'object'));
	}
	$result = dolibarr_set_const($db, $models[$objectType]['constant'], $value, 'chaine', 0, '', (int) $conf->entity);
	if ($result > 0) {
		setEventMessages($langs->trans('SettingsSaved'), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$title = $langs->trans('LmdbVehicleManagementSetup');
$linkback = '<a href="'.($backtopage ?: DOL_URL_ROOT.'/admin/modules.php?search_keyword=lmdbvehiclemanagement').'">'.img_picto('', 'back', 'class="pictofixedwidth"').$langs->trans('BackToModuleList').'</a>';

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-admin');
print load_fiche_titre($title, $linkback, 'title_setup');
$head = lmdbVehicleManagementAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $title, -1, 'car');

print load_fiche_titre($langs->trans('NumberingModels'), '', 'hashtag');
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Name').'</td><td>'.$langs->trans('Description').'</td><td>'.$langs->trans('Example').'</td><td class="center">'.$langs->trans('Status').'</td><td class="center">'.$langs->trans('ShortInfo').'</td></tr>';

foreach ($models as $code => $definition) {
	$modelFile = dol_buildpath('/lmdbvehiclemanagement/core/modules/lmdbvehiclemanagement/'.$definition['model'].'.php', 0);
	require_once $modelFile;
	$modelClass = $definition['model'];
	$numbering = new $modelClass();
	$objectClass = $definition['class'];
	$specimen = new $objectClass($db);
	$specimen->date_creation = dol_now();
	$specimen->entity = (int) $conf->entity;
	$active = getDolGlobalString($definition['constant']) === $definition['model'];

	print '<tr class="liste_titre"><td colspan="5"><strong>'.$langs->trans($definition['label']).'</strong></td></tr>';
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($numbering->getName($langs)).'</td>';
	print '<td>'.$numbering->info($langs).'</td>';
	print '<td class="nowrap">'.dol_escape_htmltag($numbering->getExample()).'</td>';
	print '<td class="center">';
	if ($active) {
		print img_picto($langs->trans('Activated'), 'switch_on');
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=setmod&token='.newToken().'&object='.urlencode($code).'&value='.urlencode($definition['model']).'">'.img_picto($langs->trans('Disabled'), 'switch_off').'</a>';
	}
	print '</td>';
	$next = $numbering->getNextValue($specimen);
	$tooltip = $langs->trans('Version').': <b>'.dol_escape_htmltag($numbering->getVersion()).'</b><br>'.$langs->trans('NextValue').': '.dol_escape_htmltag(is_string($next) ? $next : $numbering->error);
	$form = new Form($db);
	print '<td class="center">'.$form->textwithpicto('', $tooltip, 1, 'info').'</td>';
	print '</tr>';
}

print '</table>';
print '</div>';
print '<div class="underbanner opacitymedium">'.$langs->trans('NoMandatoryDependency').'. '.$langs->trans('OptionalDependencies').': Agenda, Multicompany, Ressources.</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
