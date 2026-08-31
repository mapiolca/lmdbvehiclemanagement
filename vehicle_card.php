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
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleenergy.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumable.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementcompatibility.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var HookManager $hookmanager */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('main', 'companies', 'other', 'agenda', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09') ?: 'view';
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$permissionToWrite = $user->hasRight('lmdbvehiclemanagement', 'lmdbvehicle', 'write');
$permissionToDelete = $user->hasRight('lmdbvehiclemanagement', 'lmdbvehicle', 'delete');
$permissionToManageService = $user->hasRight('lmdbvehiclemanagement', 'lmdbvehicle', 'service');

$object = new LmdbVehicle($db);
$hookmanager->initHooks(array('lmdbvehiclecard', 'globalcard'));
if ($id > 0 && $object->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));

/**
 * Populate the vehicle object from a submitted form.
 *
 * @param LmdbVehicle $vehicle Vehicle to populate
 * @return void
 */
function lmdbVehiclePopulateFromPost($vehicle)
{
	$vehicle->registration_number = strtoupper(trim(GETPOST('registration_number', 'alphanohtml')));
	$vehicle->vin = trim(GETPOST('vin', 'alphanohtml')) ?: null;
	$vehicle->label = trim(GETPOST('label', 'alphanohtml'));
	$vehicle->brand = trim(GETPOST('brand', 'alphanohtml')) ?: null;
	$vehicle->model = trim(GETPOST('model', 'alphanohtml')) ?: null;
	$vehicle->vehicle_version = trim(GETPOST('vehicle_version', 'alphanohtml')) ?: null;
	$energyId = GETPOSTINT('fk_energy');
	$vehicle->fk_energy = $energyId > 0 ? $energyId : null;
	$wltpRange = trim(GETPOST('wltp_range_km', 'alphanohtml'));
	$vehicle->wltp_range_km = $wltpRange === '' ? null : (float) price2num($wltpRange);
	$vehicle->first_registration_date = dol_mktime(12, 0, 0, GETPOSTINT('first_registration_datemonth'), GETPOSTINT('first_registration_dateday'), GETPOSTINT('first_registration_dateyear')) ?: null;
	$vehicle->commissioning_date = dol_mktime(12, 0, 0, GETPOSTINT('commissioning_datemonth'), GETPOSTINT('commissioning_dateday'), GETPOSTINT('commissioning_dateyear')) ?: null;
	$vehicle->ownership_type = GETPOST('ownership_type', 'alpha') ?: null;
	$ownerId = GETPOSTINT('fk_soc_owner');
	$vehicle->fk_soc_owner = $ownerId > 0 ? $ownerId : null;
	if (LmdbVehicleManagementCompatibility::isFeatureAvailable('resource_link')) {
		$resourceId = GETPOSTINT('fk_resource');
		$vehicle->fk_resource = $resourceId > 0 ? $resourceId : null;
	}
	$vehicle->description = GETPOST('description', 'restricthtml') ?: null;
}

/** @param array<int,string> $options Consumable options @return array<int,float|null> */
function lmdbVehicleCapacityValuesFromPost($options)
{
	$values = array();
	foreach (array_keys($options) as $consumableId) {
		$value = trim(GETPOST('capacity_'.((int) $consumableId), 'alphanohtml'));
		$values[(int) $consumableId] = $value === '' ? null : (float) price2num($value);
	}
	return $values;
}

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook)) {
	if ($cancel) {
		header('Location: '.($id > 0 ? $_SERVER['PHP_SELF'].'?id='.$id : dol_buildpath('/lmdbvehiclemanagement/vehicle_list.php', 1)));
		exit;
	}
	if ($action === 'add') {
		if (!$permissionToWrite) accessforbidden();
		lmdbVehiclePopulateFromPost($object);
		$db->begin();
		$result = $object->create($user);
		if ($result > 0) {
			$capacityDictionary = new LmdbVehicleConsumable($db);
			$compatibleCapacityOptions = !empty($object->fk_energy) ? $capacityDictionary->getCapacityOptions((int) $object->fk_energy) : array();
			$capacityResult = $object->saveCapacities($user, lmdbVehicleCapacityValuesFromPost($compatibleCapacityOptions));
			if ($capacityResult > 0) {
				$db->commit();
				setEventMessages($langs->trans('VehicleCreated'), null, 'mesgs');
				header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id));
				exit;
			}
			$result = $capacityResult;
		}
		$db->rollback();
		lmdbVehicleManagementSetObjectErrors($object);
		$action = 'create';
	} elseif ($action === 'update') {
		if (!$permissionToWrite || $id <= 0) accessforbidden();
		lmdbVehiclePopulateFromPost($object);
		$db->begin();
		$result = $object->update($user);
		if ($result > 0) {
			$capacityDictionary = new LmdbVehicleConsumable($db);
			$compatibleCapacityOptions = !empty($object->fk_energy) ? $capacityDictionary->getCapacityOptions((int) $object->fk_energy) : array();
			$capacityResult = $object->saveCapacities($user, lmdbVehicleCapacityValuesFromPost($compatibleCapacityOptions));
			if ($capacityResult > 0) {
				$db->commit();
				setEventMessages($langs->trans('VehicleUpdated'), null, 'mesgs');
				header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id));
				exit;
			}
			$result = $capacityResult;
		}
		$db->rollback();
		lmdbVehicleManagementSetObjectErrors($object);
		$action = 'edit';
	} elseif ($action === 'validate') {
		if (!$permissionToWrite || $id <= 0) accessforbidden();
		$result = $object->validate($user);
		if ($result > 0) setEventMessages($langs->trans('VehicleValidated'), null, 'mesgs');
		else lmdbVehicleManagementSetObjectErrors($object);
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	} elseif ($action === 'set_in_service') {
		if (!$permissionToManageService || $id <= 0) accessforbidden();
		$result = $object->setInService($user);
		if ($result > 0) setEventMessages($langs->trans('VehiclePutInService'), null, 'mesgs');
		else lmdbVehicleManagementSetObjectErrors($object);
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	} elseif ($action === 'set_out_of_service') {
		if (!$permissionToManageService || $id <= 0) accessforbidden();
		$result = $object->setOutOfService($user);
		if ($result > 0) setEventMessages($langs->trans('VehiclePutOutOfService'), null, 'mesgs');
		else lmdbVehicleManagementSetObjectErrors($object);
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	} elseif ($action === 'set_sold') {
		if (!$permissionToWrite || $id <= 0) accessforbidden();
		$result = $object->setSold($user);
		if ($result > 0) setEventMessages($langs->trans('VehicleMarkedSold'), null, 'mesgs');
		else lmdbVehicleManagementSetObjectErrors($object);
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	} elseif ($action === 'confirm_delete' && $confirm === 'yes') {
		if (!$permissionToDelete || $id <= 0) accessforbidden();
		$result = $object->delete($user);
		if ($result > 0) {
			setEventMessages($langs->trans('VehicleDeleted'), null, 'mesgs');
			header('Location: '.dol_buildpath('/lmdbvehiclemanagement/vehicle_list.php', 1));
			exit;
		}
		lmdbVehicleManagementSetObjectErrors($object);
		$action = 'view';
	}
}

$form = new Form($db);
$formfile = new FormFile($db);
$energyDictionary = new LmdbVehicleEnergy($db);
$consumableDictionary = new LmdbVehicleConsumable($db);
$capacityOptions = $consumableDictionary->getCapacityOptions();
$title = $id > 0 ? $object->ref : $langs->trans('NewVehicle');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');

if ($action === 'delete' && $id > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('Delete'), $langs->trans('ConfirmDeleteVehicle'), 'confirm_delete', '', 0, 1);
}

if ($action === 'create' || $action === 'edit') {
	if (!$permissionToWrite) accessforbidden();
	print load_fiche_titre($title, '', 'car');
	print '<form class="lmdb-responsive-form" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.($action === 'create' ? 'add' : 'update').'">';
	if ($id > 0) print '<input type="hidden" name="id" value="'.$id.'">';
	print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('RegistrationNumber').'</td><td><input class="flat minwidth200" name="registration_number" maxlength="32" value="'.dol_escape_htmltag($object->registration_number).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Label').'</td><td><input class="flat minwidth300" name="label" maxlength="255" value="'.dol_escape_htmltag($object->label).'"></td></tr>';
	print '<tr><td>'.$langs->trans('VIN').'</td><td><input class="flat minwidth300" name="vin" maxlength="64" value="'.dol_escape_htmltag((string) $object->vin).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Brand').'</td><td><input class="flat minwidth200" name="brand" maxlength="128" value="'.dol_escape_htmltag((string) $object->brand).'"></td></tr>';
	print '<tr><td>'.$langs->trans('VehicleModel').'</td><td><input class="flat minwidth200" name="model" maxlength="128" value="'.dol_escape_htmltag((string) $object->model).'"></td></tr>';
	print '<tr><td>'.$langs->trans('VehicleVersion').'</td><td><input class="flat minwidth200" name="vehicle_version" maxlength="128" value="'.dol_escape_htmltag((string) $object->vehicle_version).'"></td></tr>';
	$energyOptions = $energyDictionary->getSelectOptions((int) $object->fk_energy);
	print '<tr><td>'.$langs->trans('Energy').'</td><td>'.$form->selectarray('fk_energy', $energyOptions, (int) $object->fk_energy, 1, 0, 0, '', 1, 0, 0, '', 'minwidth300', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('WltpRangeKm').'</td><td><input class="flat width100" name="wltp_range_km" value="'.dol_escape_htmltag($object->wltp_range_km !== null ? price($object->wltp_range_km) : '').'"> '.$langs->trans('UnitKm').'</td></tr>';
	$storedCapacities = $object->fetchCapacities();
	foreach ($capacityOptions as $consumableId => $consumableOption) {
		$value = isset($storedCapacities[$consumableId]) ? price($storedCapacities[$consumableId]) : '';
		$capacityLabel = $langs->transnoentitiesnoconv('ConsumableCapacity', $consumableOption['label']);
		$isCompatible = in_array((int) $object->fk_energy, $consumableOption['energy_ids'], true);
		print '<tr class="lmdb-capacity-row" data-energy-ids="'.dol_escape_htmltag(implode(',', $consumableOption['energy_ids'])).'"'.($isCompatible ? '' : ' style="display:none"').'>';
		print '<td>'.dol_escape_htmltag($capacityLabel).'</td><td><input class="flat width100" name="capacity_'.((int) $consumableId).'" value="'.dol_escape_htmltag($value).'"'.($isCompatible ? '' : ' disabled').'> '.dol_escape_htmltag($consumableOption['unit']).'</td></tr>';
	}
	print '<tr><td>'.$langs->trans('FirstRegistrationDate').'</td><td>'.$form->selectDate($object->first_registration_date ?: -1, 'first_registration_date', 0, 0, 1, '', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('CommissioningDate').'</td><td>'.$form->selectDate($object->commissioning_date ?: -1, 'commissioning_date', 0, 0, 1, '', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('OwnershipType').'</td><td>'.$form->selectarray('ownership_type', $object->fields['ownership_type']['arrayofkeyval'], $object->ownership_type, 1, 0, 0, '', 1, 0, 0, '', 'minwidth200', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('OwnerThirdParty').'</td><td>'.$form->select_company($object->fk_soc_owner ?: '', 'fk_soc_owner', '', '-1', 0, 0, array(), 0, 'minwidth300').'</td></tr>';
	if (LmdbVehicleManagementCompatibility::isFeatureAvailable('resource_link')) {
		require_once DOL_DOCUMENT_ROOT.'/resource/class/html.formresource.class.php';
		$formResource = new FormResource($db);
		print '<tr><td>'.$langs->trans('LinkedResource').'</td><td>'.$formResource->select_resource_list($object->fk_resource ?: 0, 'fk_resource', array(), 1, 1, 0, array(), array(), 2, 0, 'minwidth300').'</td></tr>';
	}
	print '<tr><td class="tdtop">'.$langs->trans('Description').'</td><td>';
	$doleditor = new DolEditor('description', (string) $object->description, '', 160, 'dolibarr_notes', 'In', true, false, isModEnabled('fckeditor'), ROWS_5, '100%');
	print $doleditor->Create(1);
	print '</td></tr>';
	print '</table></div>';
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> &nbsp; <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'" formnovalidate></div>';
	print '</form>';
} elseif ($id > 0) {
	$head = lmdbVehiclePrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('Vehicle'), -1, 'car');
	lmdbVehiclePrintBanner($object);

	print '<div class="fichecenter"><div class="fichehalfleft"><div class="underbanner clearboth"></div><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('RegistrationNumber').'</td><td>'.dol_escape_htmltag($object->registration_number).'</td></tr>';
	print '<tr><td>'.$langs->trans('VIN').'</td><td>'.dol_escape_htmltag((string) $object->vin).'</td></tr>';
	print '<tr><td>'.$langs->trans('Brand').'</td><td>'.dol_escape_htmltag((string) $object->brand).'</td></tr>';
	print '<tr><td>'.$langs->trans('VehicleModel').'</td><td>'.dol_escape_htmltag((string) $object->model).'</td></tr>';
	print '<tr><td>'.$langs->trans('VehicleVersion').'</td><td>'.dol_escape_htmltag((string) $object->vehicle_version).'</td></tr>';
	print '<tr><td>'.$langs->trans('Energy').'</td><td>'.dol_escape_htmltag($energyDictionary->getDisplayLabel((int) $object->fk_energy)).'</td></tr>';
	print '<tr><td>'.$langs->trans('WltpRangeKm').'</td><td>'.($object->wltp_range_km !== null ? price($object->wltp_range_km).' '.$langs->trans('UnitKm') : '').'</td></tr>';
	$storedCapacities = $object->fetchCapacities();
	$compatibleCapacityOptions = !empty($object->fk_energy) ? $consumableDictionary->getCapacityOptions((int) $object->fk_energy) : array();
	foreach ($compatibleCapacityOptions as $consumableId => $consumableOption) {
		if (!isset($storedCapacities[$consumableId])) continue;
		$capacityLabel = $langs->transnoentitiesnoconv('ConsumableCapacity', $consumableOption['label']);
		print '<tr><td>'.dol_escape_htmltag($capacityLabel).'</td><td>'.price($storedCapacities[$consumableId]).' '.dol_escape_htmltag($consumableOption['unit']).'</td></tr>';
	}
	print '<tr><td>'.$langs->trans('FirstRegistrationDate').'</td><td>'.($object->first_registration_date ? dol_print_date($object->first_registration_date, 'day') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('CommissioningDate').'</td><td>'.($object->commissioning_date ? dol_print_date($object->commissioning_date, 'day') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('OwnershipType').'</td><td>'.(!empty($object->ownership_type) && isset($object->fields['ownership_type']['arrayofkeyval'][$object->ownership_type]) ? $langs->trans($object->fields['ownership_type']['arrayofkeyval'][$object->ownership_type]) : '').'</td></tr>';
	if (!empty($object->fk_soc_owner)) {
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$owner = new Societe($db);
		print '<tr><td>'.$langs->trans('OwnerThirdParty').'</td><td>'.($owner->fetch((int) $object->fk_soc_owner) > 0 ? $owner->getNomUrl(1) : '').'</td></tr>';
	}
	if (!empty($object->fk_resource) && LmdbVehicleManagementCompatibility::isFeatureAvailable('resource_link')) {
		require_once DOL_DOCUMENT_ROOT.'/resource/class/dolresource.class.php';
		$resource = new Dolresource($db);
		print '<tr><td>'.$langs->trans('LinkedResource').'</td><td>'.($resource->fetch((int) $object->fk_resource) > 0 ? $resource->getNomUrl(1) : '').'</td></tr>';
	}
	$description = (string) $object->description;
	print '<tr><td class="tdtop">'.$langs->trans('Description').'</td><td>'.(dol_textishtml($description) ? $description : dol_htmlentitiesbr($description)).'</td></tr>';
	print '</table></div><div class="fichehalfright">';
	lmdbVehiclePrintInsuranceBlock($object);
	print '</div></div>';
	print '<div class="clearboth"></div>';
	print dol_get_fiche_end();

	// Actions buttons
	print '<div class="tabsAction">';
	$hookmanager->executeHooks('addMoreActionsButtons', array(), $object, $action);
	print $hookmanager->resPrint;
	if ($permissionToWrite) {
		print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=edit');
		if ((int) $object->status === LmdbVehicle::STATUS_DRAFT) {
			print dolGetButtonAction('', $langs->trans('Validate'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=validate&token='.newToken());
		}
		if (in_array((int) $object->status, array(LmdbVehicle::STATUS_VALIDATED, LmdbVehicle::STATUS_IN_SERVICE, LmdbVehicle::STATUS_OUT_OF_SERVICE), true)) {
			print dolGetButtonAction('', $langs->trans('MarkVehicleSold'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=set_sold&token='.newToken());
		}
	}
	if ($permissionToManageService && ((int) $object->status === LmdbVehicle::STATUS_VALIDATED || (int) $object->status === LmdbVehicle::STATUS_OUT_OF_SERVICE)) {
		print dolGetButtonAction('', $langs->trans('PutInService'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=set_in_service&token='.newToken());
	} elseif ($permissionToManageService && (int) $object->status === LmdbVehicle::STATUS_IN_SERVICE) {
		print dolGetButtonAction('', $langs->trans('PutOutOfService'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=set_out_of_service&token='.newToken());
	}
	if ($user->hasRight('lmdbvehiclemanagement', 'event', 'write')) {
		print dolGetButtonAction('', $langs->trans('NewVehicleEvent'), 'default', dol_buildpath('/lmdbvehiclemanagement/vehicleevent_card.php', 1).'?action=create&vehicle_id='.$id);
	}
	if ($permissionToDelete) print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER['PHP_SELF'].'?id='.$id.'&action=delete&token='.newToken());
	print '</div>';

	print '<div class="fichecenter"><div class="fichehalfleft">';
	$uploadDir = getMultidirOutput($object, 'lmdbvehiclemanagement', 1);
	if (is_string($uploadDir) && $uploadDir !== '' && strpos($uploadDir, 'error-diroutput-') !== 0) {
		print $formfile->showdocuments('lmdbvehiclemanagement', dol_sanitizeFileName($object->ref), $uploadDir, $_SERVER['PHP_SELF'].'?id='.$id, 0, $permissionToWrite, '', 1, 0, 0, 28, 0, '&entity='.((int) $object->entity));
	}
	$form->showLinkedObjectBlock($object);
	print '</div><div class="fichehalfright">';
	if (LmdbVehicleManagementCompatibility::isFeatureAvailable('native_agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
		$formActions = new FormActions($db);
		$more = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars', dol_buildpath('/lmdbvehiclemanagement/vehicle_agenda.php', 1).'?id='.$id);
		$formActions->showactions($object, $object->element.'@'.$object->module, 0, 1, '', 10, '', $more);
	}
	print '</div></div>';
}

llxFooter();
$db->close();
