<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleregulatorycontrol.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var HookManager $hookmanager */
/** @var Translate $langs */
/** @var User $user */
$langs->loadLangs(array('main', 'companies', 'agenda', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09') ?: 'view';
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$permissionWrite = $user->hasRight('lmdbvehiclemanagement', 'regulatorycontrol', 'write');
$permissionValidate = $user->hasRight('lmdbvehiclemanagement', 'regulatorycontrol', 'validate');
$permissionDelete = $user->hasRight('lmdbvehiclemanagement', 'regulatorycontrol', 'delete');
$object = new LmdbVehicleRegulatoryControl($db);
if ($id > 0 && $object->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));

/** @param LmdbVehicleRegulatoryControl $control Object @return void */
function lmdbRegulatoryControlPopulateFromPost($control)
{
	$control->fk_vehicle = GETPOSTINT('fk_vehicle');
	$control->fk_requirement = GETPOSTINT('fk_requirement');
	$control->control_kind = GETPOST('control_kind', 'aZ09') ?: 'periodic';
	$control->control_date = dol_mktime(GETPOSTINT('control_datehour'), GETPOSTINT('control_datemin'), 0, GETPOSTINT('control_datemonth'), GETPOSTINT('control_dateday'), GETPOSTINT('control_dateyear'));
	$providerId = GETPOSTINT('fk_soc_provider');
	$control->fk_soc_provider = $providerId > 0 ? $providerId : null;
	$control->document_ref = trim(GETPOST('document_ref', 'alphanohtml')) ?: null;
	$control->result_code = GETPOST('result_code', 'aZ09') ?: null;
	$control->official_valid_until = dol_mktime(12, 0, 0, GETPOSTINT('official_valid_untilmonth'), GETPOSTINT('official_valid_untilday'), GETPOSTINT('official_valid_untilyear')) ?: null;
	$control->retained_valid_until = dol_mktime(12, 0, 0, GETPOSTINT('retained_valid_untilmonth'), GETPOSTINT('retained_valid_untilday'), GETPOSTINT('retained_valid_untilyear')) ?: null;
	$control->due_override_reason = trim(GETPOST('due_override_reason', 'restricthtml')) ?: null;
	$previousId = GETPOSTINT('fk_previous_control');
	$control->fk_previous_control = $previousId > 0 ? $previousId : null;
	$control->observations = GETPOST('observations', 'restricthtml') ?: null;
}

$hookmanager->initHooks(array('lmdbvehicleregulatorycontrolcard', 'globalcard'));
$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
if (empty($reshook)) {
	if ($cancel) {
		header('Location: '.($id > 0 ? $_SERVER['PHP_SELF'].'?id='.$id : dol_buildpath('/lmdbvehiclemanagement/regulatorycontrol_list.php', 1)));
		exit;
	}
	if ($action === 'add') {
		if (!$permissionWrite) accessforbidden();
		lmdbRegulatoryControlPopulateFromPost($object);
		$result = $object->create($user);
		if ($result > 0) { setEventMessages($langs->trans('RegulatoryControlDraftCreated'), null, 'mesgs'); header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id)); exit; }
		lmdbVehicleManagementSetObjectErrors($object); $action = 'create';
	} elseif ($action === 'update') {
		if (!$permissionWrite || $id <= 0) accessforbidden();
		lmdbRegulatoryControlPopulateFromPost($object);
		$result = $object->update($user);
		if ($result > 0) { setEventMessages($langs->trans('RegulatoryControlDraftUpdated'), null, 'mesgs'); header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id); exit; }
		lmdbVehicleManagementSetObjectErrors($object); $action = 'edit';
	} elseif ($action === 'validate') {
		if (!$permissionValidate || $id <= 0) accessforbidden();
		$result = $object->validate($user);
		if ($result > 0) setEventMessages($langs->trans('RegulatoryControlValidated'), null, 'mesgs'); else lmdbVehicleManagementSetObjectErrors($object);
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id); exit;
	} elseif ($action === 'confirm_cancel' && $confirm === 'yes') {
		if (!$permissionValidate || $id <= 0) accessforbidden();
		$result = $object->cancel(GETPOST('cancellation_reason', 'restricthtml'), $user);
		if ($result > 0) setEventMessages($langs->trans('RegulatoryControlCancelled'), null, 'mesgs'); else lmdbVehicleManagementSetObjectErrors($object);
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id); exit;
	} elseif ($action === 'archive') {
		if (!$permissionValidate || $id <= 0) accessforbidden();
		$result = $object->archive($user);
		if ($result > 0) setEventMessages($langs->trans('RegulatoryControlArchived'), null, 'mesgs'); else lmdbVehicleManagementSetObjectErrors($object);
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id); exit;
	} elseif ($action === 'confirm_delete' && $confirm === 'yes') {
		if (!$permissionDelete || $id <= 0) accessforbidden();
		$result = $object->delete($user);
		if ($result > 0) { setEventMessages($langs->trans('RegulatoryControlDeleted'), null, 'mesgs'); header('Location: '.dol_buildpath('/lmdbvehiclemanagement/regulatorycontrol_list.php', 1)); exit; }
		lmdbVehicleManagementSetObjectErrors($object); $action = 'view';
	}
}

$form = new Form($db);
$formfile = new FormFile($db);
$vehicleOptions = array();
$sql = 'SELECT rowid, ref, registration_number, label FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle WHERE entity IN ('.getEntity('lmdbvehicle').') ORDER BY registration_number, ref';
$resql = $db->query($sql);
if ($resql) { while (is_object($row = $db->fetch_object($resql))) $vehicleOptions[(int) $row->rowid] = trim((string) $row->registration_number) !== '' ? (string) $row->registration_number.' — '.(string) $row->label : (string) $row->ref.' — '.(string) $row->label; $db->free($resql); }
$requirementOptions = array();
$requirementRules = array();
$sql = 'SELECT req.rowid, req.fk_vehicle, req.fk_rule, v.ref AS vehicle_ref, v.registration_number, r.label AS rule_label FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement AS req INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid = req.fk_vehicle AND v.entity = req.entity INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule AS r ON r.rowid = req.fk_rule AND r.entity = req.entity WHERE req.active = 1 AND req.entity IN ('.getEntity('lmdbvehicleregulatorycontrol').') ORDER BY v.ref, r.label';
$resql = $db->query($sql);
if ($resql) { while (is_object($row = $db->fetch_object($resql))) { $vehicleLabel = trim((string) $row->registration_number) !== '' ? (string) $row->registration_number : (string) $row->vehicle_ref; $requirementOptions[(int) $row->rowid] = $vehicleLabel.' — '.$langs->trans((string) $row->rule_label); $requirementRules[(int) $row->rowid] = array('vehicle' => (int) $row->fk_vehicle, 'rule' => (int) $row->fk_rule); } $db->free($resql); }
$resultOptions = array();
$resql = $db->query('SELECT code, label FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_control_result WHERE entity IN ('.getEntity('c_lmdbvehiclemanagement_control_result').') AND active = 1 ORDER BY position, label');
if ($resql) { while (is_object($row = $db->fetch_object($resql))) $resultOptions[(string) $row->code] = $langs->trans((string) $row->label); $db->free($resql); }

if ($action === 'create' && empty($object->fk_vehicle)) $object->fk_vehicle = GETPOSTINT('vehicle_id');
if ($action === 'create' && empty($object->fk_requirement)) $object->fk_requirement = GETPOSTINT('requirement_id');
if ($action === 'create' && !empty($object->fk_requirement) && isset($requirementRules[(int) $object->fk_requirement])) { $object->fk_vehicle = $requirementRules[(int) $object->fk_requirement]['vehicle']; $object->fk_rule = $requirementRules[(int) $object->fk_requirement]['rule']; }
if ($action === 'create' && empty($object->fk_previous_control)) $object->fk_previous_control = GETPOSTINT('previous_id') ?: null;
if ($action === 'create' && $object->control_date <= 0) $object->control_date = dol_now();

$title = $id > 0 ? $object->ref : $langs->trans('NewRegulatoryControl');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');
if ($action === 'delete' && $id > 0) print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('Delete'), $langs->trans('ConfirmDeleteRegulatoryControl'), 'confirm_delete', '', 0, 1);
if ($action === 'cancel_control' && $id > 0) {
	$formquestion = array(array('type' => 'text', 'name' => 'cancellation_reason', 'label' => $langs->trans('CancellationReason'), 'value' => ''));
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('CancelRegulatoryControl'), $langs->trans('ConfirmCancelRegulatoryControl'), 'confirm_cancel', $formquestion, 0, 1);
}

if ($action === 'create' || $action === 'edit') {
	if (!$permissionWrite) accessforbidden();
	print load_fiche_titre($title, '', 'clipboard-check');
	print '<form class="lmdb-responsive-form" method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="'.($action === 'create' ? 'add' : 'update').'">';
	if ($id > 0) print '<input type="hidden" name="id" value="'.$id.'">';
	print '<input type="hidden" name="fk_previous_control" value="'.((int) $object->fk_previous_control).'">';
	print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('VehicleOrEquipment').'</td><td>'.$form->selectarray('fk_vehicle', $vehicleOptions, (int) $object->fk_vehicle, 1, 0, 0, '', 1, 0, 0, '', 'minwidth500', 1).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('RegulatoryRequirement').'</td><td>'.$form->selectarray('fk_requirement', $requirementOptions, (int) $object->fk_requirement, 1, 0, 0, '', 1, 0, 0, '', 'minwidth500', 1).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('ControlKind').'</td><td>'.$form->selectarray('control_kind', $object->fields['control_kind']['arrayofkeyval'], $object->control_kind, 0, 0, 0, '', 1, 0, 0, '', 'minwidth300', 1).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('ControlDate').'</td><td>'.$form->selectDate($object->control_date ?: dol_now(), 'control_date', 1, 1, 0, '', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('ControlBody').'</td><td>'.$form->select_company($object->fk_soc_provider ?: '', 'fk_soc_provider', '', '-1', 0, 0, array(), 0, 'minwidth500').'</td></tr>';
	print '<tr><td>'.$langs->trans('ControlDocumentRef').'</td><td><input class="flat minwidth300" name="document_ref" maxlength="128" value="'.dol_escape_htmltag((string) $object->document_ref).'"></td></tr>';
	print '<tr><td>'.$langs->trans('ControlResult').'</td><td>'.$form->selectarray('result_code', $resultOptions, (string) $object->result_code, 1, 0, 0, '', 1, 0, 0, '', 'minwidth500', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('OfficialValidUntil').'</td><td>'.$form->selectDate($object->official_valid_until ?: -1, 'official_valid_until', 0, 0, 1, '', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('RetainedValidUntil').'</td><td>'.$form->selectDate($object->retained_valid_until ?: -1, 'retained_valid_until', 0, 0, 1, '', 1, 1).'<span class="opacitymedium left">'.$langs->trans('RetainedDatePriorityHelp').'</span></td></tr>';
	print '<tr><td>'.$langs->trans('DueDateOverrideReason').'</td><td><textarea class="flat centpercent" rows="2" name="due_override_reason">'.dol_escape_htmltag((string) $object->due_override_reason).'</textarea></td></tr>';
	print '<tr><td class="tdtop">'.$langs->trans('Observations').'</td><td><textarea class="flat centpercent" rows="5" name="observations">'.dol_escape_htmltag((string) $object->observations).'</textarea></td></tr>';
	print '</table></div><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> &nbsp; <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'" formnovalidate></div></form>';
	print '<script nonce="'.getNonce().'">jQuery(function($){function syncRequirement(){var v=parseInt($("#fk_vehicle").val()||0,10); $("#fk_requirement option").each(function(){var id=parseInt(this.value||0,10), map='.json_encode($requirementRules).'; if(!id){this.hidden=false;return;} this.hidden=v>0&&map[id]&&parseInt(map[id].vehicle,10)!==v;}); if($("#fk_requirement option:selected").prop("hidden")) $("#fk_requirement").val("").trigger("change.select2");} $("#fk_vehicle").on("change",syncRequirement); $("#fk_requirement").on("change",function(){var map='.json_encode($requirementRules).', id=parseInt(this.value||0,10); if(map[id]) $("#fk_vehicle").val(map[id].vehicle).trigger("change.select2");}); syncRequirement();});</script>';
} elseif ($id > 0) {
	$head = lmdbVehicleRegulatoryControlPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('RegulatoryControl'), -1, $object->picto);
	lmdbVehicleRegulatoryControlPrintBanner($object);
	$vehicle = new LmdbVehicle($db); $vehicleLoaded = $vehicle->fetch((int) $object->fk_vehicle) > 0;
	$provider = new Societe($db); $providerLoaded = !empty($object->fk_soc_provider) && $provider->fetch((int) $object->fk_soc_provider) > 0;
	$ruleLabel = ''; $resql = $db->query('SELECT label FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule WHERE rowid = '.((int) $object->fk_rule).' AND entity = '.((int) $object->entity)); if ($resql && is_object($row = $db->fetch_object($resql))) $ruleLabel = $langs->trans((string) $row->label); if ($resql) $db->free($resql);
	print '<div class="fichecenter"><div class="underbanner clearboth"></div><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('VehicleOrEquipment').'</td><td>'.($vehicleLoaded ? $vehicle->getNomUrl(1) : '').'</td></tr><tr><td>'.$langs->trans('RegulatoryRequirement').'</td><td>'.dol_escape_htmltag($ruleLabel).'</td></tr>';
	print '<tr><td>'.$langs->trans('ControlKind').'</td><td>'.$langs->trans(isset($object->fields['control_kind']['arrayofkeyval'][$object->control_kind]) ? $object->fields['control_kind']['arrayofkeyval'][$object->control_kind] : 'Unknown').'</td></tr>';
	print '<tr><td>'.$langs->trans('ControlDate').'</td><td>'.dol_print_date($object->control_date, 'dayhour').'</td></tr><tr><td>'.$langs->trans('ControlBody').'</td><td>'.($providerLoaded ? $provider->getNomUrl(1) : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('ControlDocumentRef').'</td><td>'.dol_escape_htmltag((string) $object->document_ref).'</td></tr><tr><td>'.$langs->trans('ControlResult').'</td><td>'.(!empty($object->result_code) && isset($resultOptions[$object->result_code]) ? dol_escape_htmltag($resultOptions[$object->result_code]) : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('OfficialValidUntil').'</td><td>'.($object->official_valid_until ? dol_print_date($object->official_valid_until, 'day') : '').'</td></tr><tr><td>'.$langs->trans('CalculatedValidUntil').'</td><td>'.($object->calculated_valid_until ? dol_print_date($object->calculated_valid_until, 'day') : '').'</td></tr><tr><td>'.$langs->trans('RetainedValidUntil').'</td><td>'.($object->retained_valid_until ? dol_print_date($object->retained_valid_until, 'day') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('DueDateOverrideReason').'</td><td>'.dol_htmlentitiesbr((string) $object->due_override_reason).'</td></tr><tr><td class="tdtop">'.$langs->trans('Observations').'</td><td>'.dol_htmlentitiesbr((string) $object->observations).'</td></tr>';
	if (!empty($object->cancellation_reason)) print '<tr><td>'.$langs->trans('CancellationReason').'</td><td>'.dol_htmlentitiesbr((string) $object->cancellation_reason).'</td></tr>';
	print '</table></div>'.dol_get_fiche_end();
	print '<div class="tabsAction">';
	$hookmanager->executeHooks('addMoreActionsButtons', array(), $object, $action);
	print $hookmanager->resPrint;
	if ($permissionWrite && (int) $object->status === LmdbVehicleRegulatoryControl::STATUS_DRAFT) print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=edit');
	if ($permissionValidate && (int) $object->status === LmdbVehicleRegulatoryControl::STATUS_DRAFT) print dolGetButtonAction('', $langs->trans('Validate'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=validate&token='.newToken());
	if ($permissionValidate && (int) $object->status === LmdbVehicleRegulatoryControl::STATUS_VALIDATED) print dolGetButtonAction('', $langs->trans('CancelRegulatoryControl'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=cancel_control&token='.newToken());
	if ($permissionValidate && (int) $object->status === LmdbVehicleRegulatoryControl::STATUS_CANCELLED) print dolGetButtonAction('', $langs->trans('CreateReplacementControl'), 'default', $_SERVER['PHP_SELF'].'?action=create&previous_id='.$id.'&vehicle_id='.((int) $object->fk_vehicle).'&requirement_id='.((int) $object->fk_requirement).'&token='.newToken());
	if ($permissionValidate && $object->canBeArchived() > 0) print dolGetButtonAction('', $langs->trans('Archive'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=archive&token='.newToken());
	if ($permissionDelete && (int) $object->status === LmdbVehicleRegulatoryControl::STATUS_DRAFT) print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER['PHP_SELF'].'?id='.$id.'&action=delete&token='.newToken());
	print '</div><div class="fichecenter"><div class="fichehalfleft">';
	$uploadDir = getMultidirOutput($object, 'lmdbvehiclemanagement', 1);
	if (is_string($uploadDir) && $uploadDir !== '' && strpos($uploadDir, 'error-diroutput-') !== 0) print $formfile->showdocuments('lmdbvehiclemanagement', dol_sanitizeFileName($object->ref), $uploadDir, $_SERVER['PHP_SELF'].'?id='.$id, 0, $permissionWrite && (int) $object->status === 0, '', 1, 0, 0, 28, 0, '&entity='.((int) $object->entity));
	require __DIR__.'/tpl/supplier_invoice_link.tpl.php';
	$form->showLinkedObjectBlock($object);
	print '</div><div class="fichehalfright">';
	if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) { require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php'; $formActions = new FormActions($db); $more = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars', dol_buildpath('/lmdbvehiclemanagement/regulatorycontrol_agenda.php', 1).'?id='.$id); $formActions->showactions($object, $object->element.'@'.$object->module, 0, 1, '', 10, '', $more); }
	print '</div></div>';
}
llxFooter();
$db->close();
