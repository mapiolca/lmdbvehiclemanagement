<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var HookManager $hookmanager */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('companies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$object = new LmdbVehicleInsuranceContract($db);
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();
if ($id <= 0 || $object->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));

$permissionnote = $user->hasRight('lmdbvehiclemanagement', 'insurance', 'write');
$hookmanager->initHooks(array('lmdbinsurancecontractnote', 'globalcard'));
$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
if (empty($reshook)) include DOL_DOCUMENT_ROOT.'/core/actions_setnotes.inc.php';

$form = new Form($db);
llxHeader('', $object->ref.' - '.$langs->trans('Notes'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card_notes');
$head = lmdbInsuranceContractPrepareHead($object);
print dol_get_fiche_head($head, 'notes', $langs->trans('InsuranceContract'), -1, $object->picto);
lmdbInsuranceContractPrintBanner($object);
print '<div class="fichecenter"><div class="underbanner clearboth"></div>';
$cssclass = 'titlefield';
$dirtpls = array_merge($conf->modules_parts['tpl'], array('/core/tpl'));
foreach ($dirtpls as $reldir) {
	$res = @include dol_buildpath($reldir.'/notes.tpl.php');
	if ($res) break;
}
print '</div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
