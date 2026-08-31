<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleevent.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var DoliDB $db */
/** @var HookManager $hookmanager */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('companies', 'users', 'other', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09') ?: 'view';
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$permissionToWrite = $user->hasRight('lmdbvehiclemanagement', 'event', 'write');
$object = new LmdbVehicleEvent($db);
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();
if ($id > 0 && $object->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));
$hookmanager->initHooks(array('lmdbvehicleeventcard', 'globalcard'));

/**
 * Populate an event from the native form.
 *
 * @param LmdbVehicleEvent $event Vehicle event
 * @return void
 */
function lmdbVehicleEventPopulateFromPost($event)
{
	$event->fk_vehicle = GETPOSTINT('fk_vehicle');
	$event->event_type = GETPOST('event_type', 'alpha');
	$event->event_subtype = GETPOST('event_subtype', 'alphanohtml') ?: null;
	$event->event_date = dol_mktime(GETPOSTINT('event_datehour'), GETPOSTINT('event_datemin'), 0, GETPOSTINT('event_datemonth'), GETPOSTINT('event_dateday'), GETPOSTINT('event_dateyear'));
	$driverId = GETPOSTINT('fk_user_driver');
	$event->fk_user_driver = $driverId > 0 ? $driverId : null;
	$thirdpartyId = GETPOSTINT('fk_soc');
	$event->fk_soc = $thirdpartyId > 0 ? $thirdpartyId : null;
	$event->socid = $event->fk_soc;
	$event->label = trim(GETPOST('label', 'alphanohtml'));
	$event->description = GETPOST('description', 'alphanohtml') ?: null;
	$event->severity = GETPOSTINT('severity');
	$event->is_immobilized = GETPOSTINT('is_immobilized') === 1 ? 1 : 0;
	$startYear = GETPOSTINT('immobilization_startyear');
	$event->immobilization_start = $startYear > 0 ? dol_mktime(GETPOSTINT('immobilization_starthour'), GETPOSTINT('immobilization_startmin'), 0, GETPOSTINT('immobilization_startmonth'), GETPOSTINT('immobilization_startday'), $startYear) : null;
	$endYear = GETPOSTINT('immobilization_endyear');
	$event->immobilization_end = $endYear > 0 ? dol_mktime(GETPOSTINT('immobilization_endhour'), GETPOSTINT('immobilization_endmin'), 0, GETPOSTINT('immobilization_endmonth'), GETPOSTINT('immobilization_endday'), $endYear) : null;
	$odometer = trim(GETPOST('odometer_km', 'alphanohtml'));
	$event->odometer_km = $odometer === '' ? null : (float) price2num($odometer);
	$event->status = GETPOSTINT('status');
}

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
if (empty($reshook)) {
	if ($cancel) {
		header('Location: '.($id > 0 ? $_SERVER['PHP_SELF'].'?id='.$id : dol_buildpath('/lmdbvehiclemanagement/vehicleevent_list.php', 1)));
		exit;
	}
	if ($action === 'add') {
		if (!$permissionToWrite) accessforbidden();
		lmdbVehicleEventPopulateFromPost($object);
		if ($object->create($user) > 0) {
			setEventMessages($langs->trans('VehicleEventCreated'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		}
		lmdbVehicleManagementSetObjectErrors($object);
		$action = 'create';
	} elseif ($action === 'update') {
		if (!$permissionToWrite || $id <= 0) accessforbidden();
		lmdbVehicleEventPopulateFromPost($object);
		if ($object->update($user) > 0) {
			setEventMessages($langs->trans('VehicleEventUpdated'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		}
		lmdbVehicleManagementSetObjectErrors($object);
		$action = 'edit';
	} elseif ($action === 'confirm_delete' && $confirm === 'yes') {
		if (!$permissionToWrite || $id <= 0) accessforbidden();
		if ($object->delete($user) > 0) {
			setEventMessages($langs->trans('VehicleEventDeleted'), null, 'mesgs');
			header('Location: '.dol_buildpath('/lmdbvehiclemanagement/vehicleevent_list.php', 1));
			exit;
		}
		lmdbVehicleManagementSetObjectErrors($object);
		$action = 'view';
	}
}

$form = new Form($db);
$formfile = new FormFile($db);
$vehicleOptions = array();
$sqlVehicles = 'SELECT rowid, registration_number, ref FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle WHERE entity IN ('.getEntity('lmdbvehicle').') ORDER BY registration_number';
$resVehicles = $db->query($sqlVehicles);
if (!$resVehicles) {
	dol_print_error($db);
	exit;
}
while (is_object($vehicleRow = $db->fetch_object($resVehicles))) {
	$vehicleOptions[(int) $vehicleRow->rowid] = lmdbVehicleDisplayIdentifier((string) $vehicleRow->ref, (string) $vehicleRow->registration_number);
}
$db->free($resVehicles);
if ($action === 'create' && empty($object->fk_vehicle)) $object->fk_vehicle = GETPOSTINT('vehicle_id');

$title = $id > 0 ? $object->ref : $langs->trans('NewVehicleEvent');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');
if ($action === 'delete' && $id > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('Delete'), $langs->trans('ConfirmDeleteVehicleEvent'), 'confirm_delete', '', 0, 1);
}

if ($action === 'create' || $action === 'edit') {
	if (!$permissionToWrite) accessforbidden();
	print load_fiche_titre($title, '', 'calendar-day');
	print '<form class="lmdb-responsive-form" method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="'.($action === 'create' ? 'add' : 'update').'">';
	if ($id > 0) print '<input type="hidden" name="id" value="'.$id.'">';
	print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Vehicle').'</td><td>'.$form->selectarray('fk_vehicle', $vehicleOptions, $object->fk_vehicle, 1, 0, 0, '', 1, 0, 0, '', 'minwidth300', 1).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Label').'</td><td><input class="flat minwidth300" name="label" maxlength="255" required value="'.dol_escape_htmltag($object->label).'"></td></tr>';
	print '<tr><td>'.$langs->trans('EventType').'</td><td>'.$form->selectarray('event_type', $object->fields['event_type']['arrayofkeyval'], $object->event_type, 0, 0, 0, '', 1, 0, 0, '', 'minwidth200', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('EventSubtype').'</td><td><input class="flat minwidth200" name="event_subtype" maxlength="64" value="'.dol_escape_htmltag((string) $object->event_subtype).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('EventDate').'</td><td>'.$form->selectDate($object->event_date ?: dol_now(), 'event_date', 1, 1, 0, '', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('Driver').'</td><td>'.$form->select_dolusers($object->fk_user_driver ?: '', 'fk_user_driver', 1, null, 0, '', '', '', 0, 1, '', 0, '', 'minwidth300', 0, 0, false, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('ThirdParty').'</td><td>'.$form->select_company($object->fk_soc ?: '', 'fk_soc', '', '-1', 0, 0, array(), 0, 'minwidth300').'</td></tr>';
	print '<tr><td>'.$langs->trans('Severity').'</td><td>'.$form->selectarray('severity', $object->fields['severity']['arrayofkeyval'], (int) $object->severity, 0, 0, 0, '', 1, 0, 0, '', 'minwidth200', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('VehicleImmobilized').'</td><td>'.$form->selectyesno('is_immobilized', $object->is_immobilized, 1, false, 0, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('ImmobilizationStart').'</td><td>'.$form->selectDate($object->immobilization_start ?: -1, 'immobilization_start', 1, 1, 1, '', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('ImmobilizationEnd').'</td><td>'.$form->selectDate($object->immobilization_end ?: -1, 'immobilization_end', 1, 1, 1, '', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('OdometerKm').'</td><td><input class="flat width100 right" inputmode="decimal" name="odometer_km" value="'.dol_escape_htmltag($object->odometer_km === null ? '' : (string) $object->odometer_km).'"> km</td></tr>';
	print '<tr><td>'.$langs->trans('Status').'</td><td>'.$form->selectarray('status', $object->fields['status']['arrayofkeyval'], (int) $object->status, 0, 0, 0, '', 1, 0, 0, '', 'minwidth200', 1).'</td></tr>';
	print '<tr><td class="tdtop">'.$langs->trans('Description').'</td><td><textarea class="flat centpercent" rows="4" name="description">'.dol_escape_htmltag((string) $object->description).'</textarea></td></tr>';
	print '</table></div><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> &nbsp; <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'" formnovalidate></div></form>';
} elseif ($id > 0) {
	$head = lmdbVehicleEventPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('VehicleEvent'), -1, $object->picto);
	$linkback = '<a href="'.dol_buildpath('/lmdbvehiclemanagement/vehicleevent_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
	$moreHtmlRef = '<div class="refidno">'.dol_escape_htmltag($object->label);
	$entityBadge = lmdbVehicleManagementEntityBadge((int) $object->entity, lmdbVehicleManagementGetEntityOptions('lmdbvehicle'));
	if ($entityBadge !== '') {
		$moreHtmlRef .= '<br>'.$entityBadge;
	}
	$moreHtmlRef .= '</div>';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $moreHtmlRef);
	$vehicle = new LmdbVehicle($db);
	$vehicleLink = $vehicle->fetch((int) $object->fk_vehicle) > 0 ? $vehicle->getNomUrl(1) : '';
	print '<div class="fichecenter"><div class="fichehalfleft"><div class="underbanner clearboth"></div><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('Vehicle').'</td><td>'.$vehicleLink.'</td></tr>';
	print '<tr><td>'.$langs->trans('EventType').'</td><td>'.$langs->trans($object->fields['event_type']['arrayofkeyval'][$object->event_type]).'</td></tr>';
	print '<tr><td>'.$langs->trans('EventSubtype').'</td><td>'.dol_escape_htmltag((string) $object->event_subtype).'</td></tr>';
	print '<tr><td>'.$langs->trans('EventDate').'</td><td>'.dol_print_date($object->event_date, 'dayhour').'</td></tr>';
	if (!empty($object->fk_user_driver)) {
		$driver = new User($db);
		print '<tr><td>'.$langs->trans('Driver').'</td><td>'.($driver->fetch((int) $object->fk_user_driver) > 0 ? $driver->getNomUrl(1) : '').'</td></tr>';
	}
	if (!empty($object->fk_soc)) {
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$thirdparty = new Societe($db);
		print '<tr><td>'.$langs->trans('ThirdParty').'</td><td>'.($thirdparty->fetch((int) $object->fk_soc) > 0 ? $thirdparty->getNomUrl(1) : '').'</td></tr>';
	}
	print '<tr><td>'.$langs->trans('Severity').'</td><td>'.$langs->trans($object->fields['severity']['arrayofkeyval'][$object->severity]).'</td></tr>';
	print '<tr><td>'.$langs->trans('VehicleImmobilized').'</td><td>'.$langs->trans($object->is_immobilized ? 'Yes' : 'No').'</td></tr>';
	print '<tr><td>'.$langs->trans('ImmobilizationStart').'</td><td>'.($object->immobilization_start ? dol_print_date($object->immobilization_start, 'dayhour') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('ImmobilizationEnd').'</td><td>'.($object->immobilization_end ? dol_print_date($object->immobilization_end, 'dayhour') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('OdometerKm').'</td><td>'.($object->odometer_km !== null ? price($object->odometer_km, 0, $langs, 1, -1, -1).' km' : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('Status').'</td><td>'.$object->getLibStatut(5).'</td></tr>';
	print '<tr><td class="tdtop">'.$langs->trans('Description').'</td><td>'.dol_htmlentitiesbr((string) $object->description).'</td></tr></table></div></div>';
	print '<div class="tabsAction">';
	$hookParameters = array();
	$hookmanager->executeHooks('addMoreActionsButtons', $hookParameters, $object, $action);
	print $hookmanager->resPrint;
	if ($permissionToWrite) {
		print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=edit');
		print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER['PHP_SELF'].'?id='.$id.'&action=delete&token='.newToken());
	}
	print '</div>';
	print '<div class="fichecenter"><div class="fichehalfleft">';
	$uploadDir = getMultidirOutput($object, 'lmdbvehiclemanagement', 1);
	if (is_string($uploadDir) && $uploadDir !== '' && strpos($uploadDir, 'error-diroutput-') !== 0) print $formfile->showdocuments('lmdbvehiclemanagement', dol_sanitizeFileName($object->ref), $uploadDir, $_SERVER['PHP_SELF'].'?id='.$id, 0, $permissionToWrite, '', 1, 0, 0, 28, 0, '&entity='.$object->entity);
	$form->showLinkedObjectBlock($object);
	print '</div><div class="fichehalfright"></div></div>';
	print dol_get_fiche_end();
}
llxFooter();
$db->close();
