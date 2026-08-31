<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleodometerreading.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('lmdbvehiclemanagement@lmdbvehiclemanagement'));
$id = GETPOSTINT('id');
$readingId = GETPOSTINT('reading_id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$permissionToManage = $user->hasRight('lmdbvehiclemanagement', 'odometer', 'write');
$vehicle = new LmdbVehicle($db);
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();
if ($id <= 0 || $vehicle->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));

$reading = new LmdbVehicleOdometerReading($db);
if ($readingId > 0) {
	if ($reading->fetch($readingId) <= 0 || (int) $reading->fk_vehicle !== $id || (int) $reading->entity !== (int) $vehicle->entity) {
		accessforbidden($langs->trans('RecordNotFound'));
	}
}

/**
 * Populate a reading from the native form.
 *
 * @param LmdbVehicleOdometerReading $target Reading
 * @param int $vehicleId Vehicle id
 * @return void
 */
function lmdbVehicleOdometerPopulateFromPost($target, $vehicleId)
{
	$target->fk_vehicle = $vehicleId;
	$target->reading_date = dol_mktime(GETPOSTINT('reading_datehour'), GETPOSTINT('reading_datemin'), 0, GETPOSTINT('reading_datemonth'), GETPOSTINT('reading_dateday'), GETPOSTINT('reading_dateyear'));
	$target->odometer_km = (float) price2num(GETPOST('odometer_km', 'alphanohtml'));
	$target->source = GETPOST('source', 'alpha');
	$target->reading_kind = GETPOST('reading_kind', 'alpha');
	$target->reason = GETPOST('reason', 'alphanohtml') ?: null;
}

if ($action === 'add') {
	if (!$permissionToManage) accessforbidden();
	lmdbVehicleOdometerPopulateFromPost($reading, $id);
	if ($reading->create($user) > 0) {
		setEventMessages($langs->trans('OdometerReadingCreated'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}
	lmdbVehicleManagementSetObjectErrors($reading);
	$action = 'create';
} elseif ($action === 'update' && $readingId > 0) {
	if (!$permissionToManage) accessforbidden();
	lmdbVehicleOdometerPopulateFromPost($reading, $id);
	if ($reading->update($user) > 0) {
		setEventMessages($langs->trans('OdometerReadingUpdated'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}
	lmdbVehicleManagementSetObjectErrors($reading);
	$action = 'edit';
} elseif ($action === 'confirm_delete' && $confirm === 'yes' && $readingId > 0) {
	if (!$permissionToManage) accessforbidden();
	if ($reading->delete($user) > 0) setEventMessages($langs->trans('OdometerReadingDeleted'), null, 'mesgs');
	else lmdbVehicleManagementSetObjectErrors($reading);
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
	exit;
}

$form = new Form($db);
llxHeader('', $vehicle->ref.' - '.$langs->trans('OdometerReadings'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');
if ($action === 'delete' && $readingId > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id.'&reading_id='.$readingId, $langs->trans('Delete'), $langs->trans('ConfirmDeleteOdometerReading'), 'confirm_delete', '', 0, 1);
}
$head = lmdbVehiclePrepareHead($vehicle);
print dol_get_fiche_head($head, 'odometer', $langs->trans('Vehicle'), -1, $vehicle->picto);
lmdbVehiclePrintBanner($vehicle);

if ($permissionToManage && ($action === 'create' || $action === 'edit')) {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'">';
	print '<input type="hidden" name="reading_id" value="'.((int) $reading->id).'"><input type="hidden" name="action" value="'.($action === 'edit' ? 'update' : 'add').'">';
	print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('ReadingDate').'</td><td>'.$form->selectDate($reading->reading_date ?: dol_now(), 'reading_date', 1, 1, 0, '', 1, 1).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('OdometerKm').'</td><td><input class="flat width100 right" inputmode="decimal" name="odometer_km" value="'.dol_escape_htmltag((string) $reading->odometer_km).'"> km</td></tr>';
	$sourceOptions = $reading->fields['source']['arrayofkeyval'];
	unset($sourceOptions['consumption']);
	print '<tr><td>'.$langs->trans('ReadingSource').'</td><td>'.$form->selectarray('source', $sourceOptions, $reading->source, 0, 0, 0, '', 1, 0, 0, '', 'minwidth200', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('ReadingKind').'</td><td>'.$form->selectarray('reading_kind', $reading->fields['reading_kind']['arrayofkeyval'], $reading->reading_kind, 0, 0, 0, '', 1, 0, 0, '', 'minwidth200', 1).'</td></tr>';
	print '<tr><td class="tdtop">'.$langs->trans('ReadingReason').'</td><td><textarea class="flat centpercent" rows="3" name="reason">'.dol_escape_htmltag((string) $reading->reason).'</textarea></td></tr>';
	print '</table></div><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> &nbsp; <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'">'.$langs->trans('Cancel').'</a></div></form>';
} else {
	if ($permissionToManage) print '<div class="tabsAction">'.dolGetButtonAction('', $langs->trans('AddOdometerReading'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=create').'</div>';
	$records = $reading->fetchAllByVehicle($id);
	if (!is_array($records)) {
		lmdbVehicleManagementSetObjectErrors($reading);
		$records = array();
	}
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('ReadingDate').'</th><th class="right">'.$langs->trans('OdometerKm').'</th><th class="right">'.$langs->trans('OdometerDifference').'</th><th>'.$langs->trans('ReadingSource').'</th><th>'.$langs->trans('ReadingKind').'</th><th>'.$langs->trans('ReadingReason').'</th><th></th></tr>';
	$recordCount = count($records);
	foreach ($records as $recordIndex => $record) {
		$differenceHtml = '<span class="opacitymedium">&mdash;</span>';
		if ($recordIndex + 1 < $recordCount) {
			$difference = (float) $record->odometer_km - (float) $records[$recordIndex + 1]->odometer_km;
			$differenceClass = '';
			$differenceSign = '';
			if ($difference > 0) {
				$differenceClass = 'text-success';
				$differenceSign = '+';
			} elseif ($difference < 0) {
				$differenceClass = 'text-danger';
				$differenceSign = '-';
			}
			$differenceHtml = '<span'.($differenceClass !== '' ? ' class="'.$differenceClass.'"' : '').'>'.$differenceSign.price(abs($difference), 0, $langs, 1, -1, -1).' km</span>';
		}
		print '<tr class="oddeven" id="odometer-'.((int) $record->id).'"><td>'.dol_print_date($record->reading_date, 'dayhour').'</td><td class="right">'.price($record->odometer_km, 0, $langs, 1, -1, -1).' km</td><td class="right nowraponall">'.$differenceHtml.'</td>';
		print '<td>'.$langs->trans($record->fields['source']['arrayofkeyval'][$record->source]).'</td><td>'.$langs->trans($record->fields['reading_kind']['arrayofkeyval'][$record->reading_kind]).'</td><td>'.dol_htmlentitiesbr((string) $record->reason).'</td><td class="nowraponall">';
		if ($permissionToManage && $record->source !== 'consumption') {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&reading_id='.$record->id.'&action=edit">'.img_edit().'</a> ';
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&reading_id='.$record->id.'&action=delete&token='.newToken().'">'.img_delete().'</a>';
		} elseif ($record->source === 'consumption') {
			print img_info($langs->trans('ConsumptionOwnsOdometerReading'));
		}
		print '</td></tr>';
	}
	if (empty($records)) print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	print '</table></div>';
}
print dol_get_fiche_end();
llxFooter();
$db->close();
