<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleassignment.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('users', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
$id = GETPOSTINT('id');
$assignmentId = GETPOSTINT('assignment_id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$permissionToManage = $user->hasRight('lmdbvehiclemanagement', 'assignment', 'write');
$vehicle = new LmdbVehicle($db);
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();
if ($id <= 0 || $vehicle->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));

$assignment = new LmdbVehicleAssignment($db);
if ($assignmentId > 0) {
	if ($assignment->fetch($assignmentId) <= 0 || (int) $assignment->fk_vehicle !== $id || (int) $assignment->entity !== (int) $vehicle->entity) {
		accessforbidden($langs->trans('RecordNotFound'));
	}
}

/**
 * Populate an assignment from the native form.
 *
 * @param LmdbVehicleAssignment $target Assignment
 * @param int $vehicleId Vehicle id
 * @return void
 */
function lmdbVehicleAssignmentPopulateFromPost($target, $vehicleId)
{
	$target->fk_vehicle = $vehicleId;
	$target->fk_user_driver = GETPOSTINT('fk_user_driver');
	$target->date_start = dol_mktime(GETPOSTINT('date_starthour'), GETPOSTINT('date_startmin'), 0, GETPOSTINT('date_startmonth'), GETPOSTINT('date_startday'), GETPOSTINT('date_startyear'));
	$endYear = GETPOSTINT('date_endyear');
	$target->date_end = $endYear > 0 ? dol_mktime(GETPOSTINT('date_endhour'), GETPOSTINT('date_endmin'), 0, GETPOSTINT('date_endmonth'), GETPOSTINT('date_endday'), $endYear) : null;
	$target->assignment_type = GETPOST('assignment_type', 'alpha');
	$target->is_primary = GETPOSTINT('is_primary') === 1 ? 1 : 0;
	$target->reason = GETPOST('reason', 'alphanohtml') ?: null;
	$target->status = GETPOSTINT('status') === 1 ? LmdbVehicleAssignment::STATUS_ACTIVE : LmdbVehicleAssignment::STATUS_INACTIVE;
}

if ($action === 'add') {
	if (!$permissionToManage) accessforbidden();
	lmdbVehicleAssignmentPopulateFromPost($assignment, $id);
	if ($assignment->create($user) > 0) {
		setEventMessages($langs->trans('AssignmentCreated'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}
	lmdbVehicleManagementSetObjectErrors($assignment);
	$action = 'create';
} elseif ($action === 'update' && $assignmentId > 0) {
	if (!$permissionToManage) accessforbidden();
	lmdbVehicleAssignmentPopulateFromPost($assignment, $id);
	if ($assignment->update($user) > 0) {
		setEventMessages($langs->trans('AssignmentUpdated'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}
	lmdbVehicleManagementSetObjectErrors($assignment);
	$action = 'edit';
} elseif ($action === 'confirm_delete' && $confirm === 'yes' && $assignmentId > 0) {
	if (!$permissionToManage) accessforbidden();
	if ($assignment->delete($user) > 0) setEventMessages($langs->trans('AssignmentDeleted'), null, 'mesgs');
	else lmdbVehicleManagementSetObjectErrors($assignment);
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
	exit;
}

$form = new Form($db);
llxHeader('', $vehicle->ref.' - '.$langs->trans('VehicleAssignments'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');
if ($action === 'delete' && $assignmentId > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id.'&assignment_id='.$assignmentId, $langs->trans('Delete'), $langs->trans('ConfirmDeleteAssignment'), 'confirm_delete', '', 0, 1);
}
$head = lmdbVehiclePrepareHead($vehicle);
print dol_get_fiche_head($head, 'assignments', $langs->trans('Vehicle'), -1, $vehicle->picto);
lmdbVehiclePrintBanner($vehicle);

if ($permissionToManage && ($action === 'create' || $action === 'edit')) {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'">';
	print '<input type="hidden" name="assignment_id" value="'.((int) $assignment->id).'"><input type="hidden" name="action" value="'.($action === 'edit' ? 'update' : 'add').'">';
	print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Driver').'</td><td>'.$form->select_dolusers($assignment->fk_user_driver, 'fk_user_driver', 1, null, 0, '', '', $vehicle->entity, 0, 1, '', 0, '', 'minwidth300', 0, 0, false, 1).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('AssignmentStart').'</td><td>'.$form->selectDate($assignment->date_start ?: dol_now(), 'date_start', 1, 1, 0, '', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('AssignmentEnd').'</td><td>'.$form->selectDate($assignment->date_end ?: -1, 'date_end', 1, 1, 1, '', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('AssignmentType').'</td><td>'.$form->selectarray('assignment_type', $assignment->fields['assignment_type']['arrayofkeyval'], $assignment->assignment_type, 0, 0, 0, '', 1, 0, 0, '', 'minwidth200', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('PrimaryAssignment').'</td><td>'.$form->selectyesno('is_primary', $assignment->is_primary, 1, false, 0, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('Status').'</td><td>'.$form->selectarray('status', $assignment->fields['status']['arrayofkeyval'], (int) $assignment->status, 0, 0, 0, '', 1, 0, 0, '', 'minwidth200', 1).'</td></tr>';
	print '<tr><td class="tdtop">'.$langs->trans('AssignmentReason').'</td><td><textarea class="flat centpercent" rows="3" name="reason">'.dol_escape_htmltag((string) $assignment->reason).'</textarea></td></tr>';
	print '</table></div><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> &nbsp; <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'">'.$langs->trans('Cancel').'</a></div></form>';
} else {
	if ($permissionToManage) print '<div class="tabsAction">'.dolGetButtonAction('', $langs->trans('AddAssignment'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=create').'</div>';
	$records = $assignment->fetchAllByVehicle($id);
	if (!is_array($records)) {
		lmdbVehicleManagementSetObjectErrors($assignment);
		$records = array();
	}
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Driver').'</th><th>'.$langs->trans('AssignmentStart').'</th><th>'.$langs->trans('AssignmentEnd').'</th><th>'.$langs->trans('AssignmentType').'</th><th class="center">'.$langs->trans('PrimaryAssignment').'</th><th class="center">'.$langs->trans('Status').'</th><th></th></tr>';
	foreach ($records as $record) {
		$driver = new User($db);
		$driver->id = (int) $record->fk_user_driver;
		$driver->login = (string) $record->driver_login;
		$driver->firstname = (string) $record->driver_firstname;
		$driver->lastname = (string) $record->driver_lastname;
		$driver->statut = 1;
		$driverLink = $driver->id > 0 ? $driver->getNomUrl(1) : '';
		print '<tr class="oddeven" id="assignment-'.((int) $record->id).'"><td>'.$driverLink.'</td><td>'.dol_print_date($record->date_start, 'dayhour').'</td><td>'.($record->date_end ? dol_print_date($record->date_end, 'dayhour') : '').'</td>';
		print '<td>'.$langs->trans($record->fields['assignment_type']['arrayofkeyval'][$record->assignment_type]).'</td><td class="center">'.$langs->trans($record->is_primary ? 'Yes' : 'No').'</td><td class="center">'.$record->getLibStatut(5).'</td><td class="nowraponall">';
		if ($permissionToManage) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&assignment_id='.$record->id.'&action=edit">'.img_edit().'</a> ';
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&assignment_id='.$record->id.'&action=delete&token='.newToken().'">'.img_delete().'</a>';
		}
		print '</td></tr>';
	}
	if (empty($records)) print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	print '</table></div>';
}
print dol_get_fiche_end();
llxFooter();
$db->close();
