<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumption.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementcompatibility.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var HookManager $hookmanager */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('main', 'users', 'agenda', 'currencies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();

$id = GETPOSTINT('id');
$vehicleIdFromUrl = GETPOSTINT('vehicle_id');
$action = GETPOST('action', 'aZ09') ?: ($id > 0 ? 'view' : 'create');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$permissionWrite = $user->hasRight('lmdbvehiclemanagement', 'consumption', 'write');
$permissionDelete = $user->hasRight('lmdbvehiclemanagement', 'consumption', 'delete');
$object = new LmdbVehicleConsumption($db);
if ($id > 0 && $object->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));
$hookmanager->initHooks(array('lmdbvehicleconsumptioncard', 'globalcard'));

/** @return array<int,string> */
function lmdbConsumptionVehicleOptions($db)
{
	global $conf;

	$options = array();
	$sql = 'SELECT rowid, ref, registration_number, label FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle';
	$sql .= ' WHERE entity = '.((int) $conf->entity).' AND status <> '.LmdbVehicle::STATUS_SOLD.' ORDER BY ref';
	$resql = $db->query($sql);
	if ($resql) {
		while (is_object($row = $db->fetch_object($resql))) {
			$options[(int) $row->rowid] = lmdbVehicleDisplayIdentifier((string) $row->ref, (string) $row->registration_number, (string) $row->label);
		}
		$db->free($resql);
	}
	return $options;
}

/** @return array<int,list<int>> Vehicle id to compatible consumable ids */
function lmdbConsumptionCompatibilityByVehicle($db)
{
	global $conf;

	$map = array();
	$sql = 'SELECT DISTINCT v.rowid AS vehicle_id, ce.fk_consumable';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v';
	$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumable_energy AS ce ON ce.fk_energy = v.fk_energy';
	$sql .= ' WHERE v.entity = '.((int) $conf->entity);
	$sql .= ' AND ce.entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').')';
	$resql = $db->query($sql);
	if ($resql) {
		while (is_object($row = $db->fetch_object($resql))) {
			$vehicleId = (int) $row->vehicle_id;
			if (!isset($map[$vehicleId])) $map[$vehicleId] = array();
			$map[$vehicleId][] = (int) $row->fk_consumable;
		}
		$db->free($resql);
	}
	return $map;
}

/** @param array<int,list<int>> $compatibility Compatibility map @return array<int,int> Vehicle id to suggested consumable */
function lmdbConsumptionSuggestedConsumables($db, $compatibility)
{
	$suggestions = array();
	foreach ($compatibility as $vehicleId => $consumableIds) {
		if (count($consumableIds) === 1) $suggestions[(int) $vehicleId] = (int) $consumableIds[0];
	}
	$sql = 'SELECT t.fk_vehicle, t.fk_consumable FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumption AS t';
	$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading AS r ON r.rowid = t.fk_odometer_reading AND r.entity = t.entity';
	$sql .= " WHERE t.category_snapshot = 'fuel' AND t.entity IN (".getEntity('lmdbvehicleconsumption').')';
	$sql .= ' ORDER BY r.reading_date DESC, t.rowid DESC';
	$resql = $db->query($sql);
	$seen = array();
	if ($resql) {
		while (is_object($row = $db->fetch_object($resql))) {
			$vehicleId = (int) $row->fk_vehicle;
			if (!isset($seen[$vehicleId]) && isset($compatibility[$vehicleId]) && in_array((int) $row->fk_consumable, $compatibility[$vehicleId], true)) {
				$suggestions[$vehicleId] = (int) $row->fk_consumable;
				$seen[$vehicleId] = true;
			}
		}
		$db->free($resql);
	}
	return $suggestions;
}

/** @param LmdbVehicleConsumption $target Target @return void */
function lmdbConsumptionPopulateFromPost($target)
{
	$target->fk_vehicle = GETPOSTINT('fk_vehicle');
	$nature = GETPOST('nature', 'alpha');
	$target->fk_consumable = $nature === 'additive' ? GETPOSTINT('additive_consumable_id') : GETPOSTINT('fuel_consumable_id');
	$target->category_snapshot = $nature;
	$driver = GETPOSTINT('fk_user_driver');
	$target->fk_user_driver = $driver > 0 ? $driver : null;
	$target->quantity = (float) price2num(GETPOST('quantity', 'alphanohtml'));
	$target->total_ttc = (float) price2num(GETPOST('total_ttc', 'alphanohtml'), 'MT');
	$target->oil_reference = trim(GETPOST('oil_reference', 'alphanohtml')) ?: null;
	$target->description = GETPOST('description', 'restricthtml') ?: null;
	$target->reading_date = dol_mktime(GETPOSTINT('reading_datehour'), GETPOSTINT('reading_datemin'), 0, GETPOSTINT('reading_datemonth'), GETPOSTINT('reading_dateday'), GETPOSTINT('reading_dateyear'));
	$target->odometer_km = (float) price2num(GETPOST('odometer_km', 'alphanohtml'));
	$target->reading_kind = GETPOST('reading_kind', 'alpha') ?: 'standard';
	$target->reading_reason = GETPOST('reading_reason', 'alphanohtml') ?: null;
}

if ($cancel) {
	header('Location: '.($id > 0 ? $_SERVER['PHP_SELF'].'?id='.$id : dol_buildpath('/lmdbvehiclemanagement/consumption_list.php', 1)));
	exit;
}
$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
if (empty($reshook)) {
	if ($action === 'add' || $action === 'update') {
		if (!$permissionWrite || ($action === 'update' && $id <= 0)) accessforbidden();
		lmdbConsumptionPopulateFromPost($object);
		$result = $action === 'add' ? $object->create($user) : $object->update($user);
		if ($result > 0) {
			$percentage = $object->getCapacityPercentage();
			if ($percentage !== null && $percentage > 100) setEventMessages($langs->trans('ConsumptionCapacityExceeded', price($percentage)), null, 'warnings');
			setEventMessages($langs->trans($action === 'add' ? 'ConsumptionCreated' : 'ConsumptionUpdated'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id));
			exit;
		}
		lmdbVehicleManagementSetObjectErrors($object);
		$action = $action === 'add' ? 'create' : 'edit';
	} elseif ($action === 'confirm_delete' && $confirm === 'yes') {
		if (!$permissionDelete || $id <= 0) accessforbidden();
		$result = $object->delete($user);
		if ($result > 0) {
			setEventMessages($langs->trans('ConsumptionDeleted'), null, 'mesgs');
			header('Location: '.dol_buildpath('/lmdbvehiclemanagement/consumption_list.php', 1));
			exit;
		}
		lmdbVehicleManagementSetObjectErrors($object);
		$action = 'view';
	}
}

$form = new Form($db);
$formfile = new FormFile($db);
$dictionary = new LmdbVehicleConsumable($db);
$vehicleOptions = lmdbConsumptionVehicleOptions($db);
if ($action === 'create' && $object->fk_vehicle <= 0 && $vehicleIdFromUrl > 0 && isset($vehicleOptions[$vehicleIdFromUrl])) $object->fk_vehicle = $vehicleIdFromUrl;
if ($action === 'create' && $object->reading_date <= 0) $object->reading_date = dol_now();
if ($action === 'create' && $object->fk_vehicle > 0 && $object->fk_consumable <= 0) $object->fk_consumable = LmdbVehicleConsumption::suggestConsumable($db, (int) $object->fk_vehicle);
if ($action === 'create' && $object->fk_user_driver === null) $object->fk_user_driver = (int) $user->id;
$fuelOptions = $dictionary->getOptions('fuel');
$additiveOptions = $dictionary->getOptions('additive');
$compatibilityByVehicle = lmdbConsumptionCompatibilityByVehicle($db);
$suggestedConsumables = lmdbConsumptionSuggestedConsumables($db, $compatibilityByVehicle);
$nature = $object->category_snapshot === 'additive' ? 'additive' : 'fuel';
$fuelSelected = $nature === 'fuel' ? (int) $object->fk_consumable : 0;
$additiveSelected = $nature === 'additive' ? (int) $object->fk_consumable : 0;
$title = $id > 0 ? $object->ref : $langs->trans('NewConsumption');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');

if ($action === 'delete' && $id > 0) print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('Delete'), $langs->trans('ConfirmDeleteConsumption'), 'confirm_delete', '', 0, 1);

if ($action === 'create' || $action === 'edit') {
	if (!$permissionWrite) accessforbidden();
	print load_fiche_titre($title, '', 'gas-pump');
	print '<form class="lmdb-responsive-form" method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="'.($action === 'create' ? 'add' : 'update').'">';
	if ($id > 0) print '<input type="hidden" name="id" value="'.$id.'">';
	print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('ConsumptionDate').'</td><td>'.$form->selectDate($object->reading_date ?: dol_now(), 'reading_date', 1, 1, 0, '', 1, 1).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Vehicle').'</td><td>'.$form->selectarray('fk_vehicle', $vehicleOptions, (int) $object->fk_vehicle, 1, 0, 0, '', 1, 0, 0, '', 'minwidth500', 1).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('ConsumptionNature').'</td><td>'.$form->selectarray('nature', array('fuel' => $langs->trans('FuelOrRecharge'), 'additive' => $langs->trans('Additive')), $nature, 0, 0, 0, '', 1, 0, 0, '', 'minwidth300', 1).'</td></tr>';
	print '<tr id="fuel_consumable_row"><td class="fieldrequired">'.$langs->trans('FuelOrRecharge').'</td><td>'.$form->selectarray('fuel_consumable_id', $fuelOptions, $fuelSelected, 1, 0, 0, '', 1, 0, 0, '', 'minwidth300', 1).'</td></tr>';
	print '<tr id="additive_consumable_row"><td class="fieldrequired">'.$langs->trans('Additive').'</td><td>'.$form->selectarray('additive_consumable_id', $additiveOptions, $additiveSelected, 1, 0, 0, '', 1, 0, 0, '', 'minwidth300', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('Driver').'</td><td>'.$form->select_dolusers($object->fk_user_driver ?: '', 'fk_user_driver', 1, null, 0, '', '', '', 0, 1, '', 0, '', 'minwidth300', 0, 0, false, 1).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Quantity').'</td><td><input class="flat width100 right" inputmode="decimal" name="quantity" value="'.dol_escape_htmltag((string) $object->quantity).'"> <span id="consumption_unit"></span></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('OdometerKm').'</td><td><input class="flat width100 right" inputmode="decimal" name="odometer_km" value="'.dol_escape_htmltag((string) $object->odometer_km).'"> '.$langs->trans('UnitKm').'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('TotalTTC').'</td><td><input class="flat width100 right" inputmode="decimal" name="total_ttc" value="'.dol_escape_htmltag((string) $object->total_ttc).'"> '.dol_escape_htmltag(getDolGlobalString('MAIN_MONNAIE', !empty($conf->currency) ? (string) $conf->currency : 'EUR')).'</td></tr>';
	print '<tr id="oil_reference_row"><td id="oil_reference_label">'.$langs->trans('OilReference').'</td><td><input class="flat minwidth300" name="oil_reference" maxlength="128" value="'.dol_escape_htmltag((string) $object->oil_reference).'"></td></tr>';
	print '<tr><td>'.$langs->trans('ReadingKind').'</td><td>'.$form->selectarray('reading_kind', array('standard' => $langs->trans('ReadingKindStandard'), 'correction' => $langs->trans('ReadingKindCorrection'), 'replacement' => $langs->trans('ReadingKindReplacement')), $object->reading_kind, 0, 0, 0, '', 1, 0, 0, '', 'minwidth200', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('ReadingReason').'</td><td><input class="flat minwidth500" name="reading_reason" value="'.dol_escape_htmltag((string) $object->reading_reason).'"></td></tr>';
	print '<tr><td class="tdtop">'.$langs->trans('Description').'</td><td>';
	$editor = new DolEditor('description', (string) $object->description, '', 160, 'dolibarr_notes', 'In', true, false, isModEnabled('fckeditor'), ROWS_5, '100%');
	print $editor->Create(1).'</td></tr></table></div>';
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> &nbsp; <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'" formnovalidate></div></form>';
	$metadata = array();
	foreach (array_keys($fuelOptions + $additiveOptions) as $consumableId) {
		$entry = new LmdbVehicleConsumable($db);
		if ($entry->fetch((int) $consumableId) > 0) $metadata[(int) $consumableId] = array('unit' => LmdbVehicleConsumable::unitLabel($entry->unit), 'oil' => (int) $entry->requires_oil_reference);
	}
	print '<script>jQuery(function($){var meta='.json_encode($metadata).',compatible='.json_encode($compatibilityByVehicle).',suggestedFuel='.json_encode($suggestedConsumables).';function filterFuel(selectSuggestion){var vehicle=String($("#fk_vehicle").val()||""),allowed=(compatible[vehicle]||[]).map(String),$fuel=$("#fuel_consumable_id");$fuel.find("option").each(function(){var value=String(this.value||"");this.disabled=value!==""&&allowed.indexOf(value)===-1;});if($fuel.val()&&allowed.indexOf(String($fuel.val()))===-1)$fuel.val("");if(selectSuggestion&&suggestedFuel[vehicle])$fuel.val(String(suggestedFuel[vehicle]));$fuel.trigger("change.select2");}function refresh(){var additive=$("#nature").val()==="additive";$("#fuel_consumable_row").toggle(!additive);$("#additive_consumable_row").toggle(additive);var id=additive?$("#additive_consumable_id").val():$("#fuel_consumable_id").val(),item=meta[id]||{},oil=additive&&item.oil===1;$("#consumption_unit").text(item.unit||"");$("#oil_reference_row").toggle(oil);$("#oil_reference_label").toggleClass("fieldrequired",oil);}$("#fk_vehicle").on("change",function(){filterFuel(true);refresh();});$("#nature,#fuel_consumable_id,#additive_consumable_id").on("change",refresh);filterFuel(false);refresh();});</script>';
} elseif ($id > 0) {
	$head = lmdbVehicleConsumptionPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('ConsumptionEntry'), -1, $object->picto);
	lmdbVehicleConsumptionPrintBanner($object);
	$vehicle = new LmdbVehicle($db);
	$vehicleLink = $vehicle->fetch((int) $object->fk_vehicle) > 0 ? $vehicle->getNomUrl(1) : '';
	$consumable = new LmdbVehicleConsumable($db);
	$consumableLabel = $consumable->fetch((int) $object->fk_consumable) > 0 ? $consumable->label : '';
	$driver = new User($db);
	$driverLink = !empty($object->fk_user_driver) && $driver->fetch((int) $object->fk_user_driver) > 0 ? $driver->getNomUrl(1) : '';
	$percentage = $object->getCapacityPercentage();
	print '<div class="fichecenter"><div class="fichehalfleft"><div class="underbanner clearboth"></div><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('ConsumptionDate').'</td><td>'.dol_print_date($object->reading_date, 'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans('Vehicle').'</td><td>'.$vehicleLink.'</td></tr><tr><td>'.$langs->trans('ConsumptionNature').'</td><td>'.$langs->trans($object->category_snapshot === 'fuel' ? 'FuelOrRecharge' : 'Additive').'</td></tr>';
	print '<tr><td>'.$langs->trans('Consumable').'</td><td>'.dol_escape_htmltag($consumableLabel).'</td></tr><tr><td>'.$langs->trans('Driver').'</td><td>'.$driverLink.'</td></tr>';
	print '<tr><td>'.$langs->trans('Quantity').'</td><td>'.price($object->quantity).' '.dol_escape_htmltag(LmdbVehicleConsumable::unitLabel($object->unit_snapshot)).'</td></tr>';
	print '<tr><td>'.$langs->trans('OdometerKm').'</td><td>'.price($object->odometer_km).' '.$langs->trans('UnitKm').'</td></tr>';
	print '<tr><td>'.$langs->trans('TotalTTC').'</td><td>'.price($object->total_ttc).' '.dol_escape_htmltag($object->currency_snapshot).'</td></tr>';
	print '<tr><td>'.$langs->trans('UnitPrice').'</td><td>'.price($object->getUnitPrice()).' '.dol_escape_htmltag($object->currency_snapshot).'/'.dol_escape_htmltag(LmdbVehicleConsumable::unitLabel($object->unit_snapshot)).'</td></tr>';
	print '<tr><td>'.$langs->trans('RecoveredCapacity').'</td><td>'.($percentage !== null ? price($percentage).' %'.($percentage > 100 ? ' '.img_warning($langs->trans('ConsumptionCapacityExceeded', price($percentage))) : '') : '').'</td></tr>';
	if ((string) $object->oil_reference !== '') print '<tr><td>'.$langs->trans('OilReference').'</td><td>'.dol_escape_htmltag((string) $object->oil_reference).'</td></tr>';
	$description = (string) $object->description;
	print '<tr><td class="tdtop">'.$langs->trans('Description').'</td><td>'.(dol_textishtml($description) ? $description : dol_htmlentitiesbr($description)).'</td></tr></table></div><div class="fichehalfright"></div></div>';
	print '<div class="clearboth"></div>'.dol_get_fiche_end();
	print '<div class="tabsAction">';
	if ($permissionWrite) print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=edit');
	if ($permissionDelete) print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER['PHP_SELF'].'?id='.$id.'&action=delete&token='.newToken());
	print '</div><div class="fichecenter"><div class="fichehalfleft">';
	$uploadDir = getMultidirOutput($object, 'lmdbvehiclemanagement', 1);
	if (is_string($uploadDir) && $uploadDir !== '' && strpos($uploadDir, 'error-diroutput-') !== 0) print $formfile->showdocuments('lmdbvehiclemanagement', dol_sanitizeFileName($object->ref), $uploadDir, $_SERVER['PHP_SELF'].'?id='.$id, 0, $permissionWrite, '', 1, 0, 0, 28, 0, '&entity='.((int) $object->entity));
	$form->showLinkedObjectBlock($object);
	print '</div><div class="fichehalfright">';
	if (LmdbVehicleManagementCompatibility::isFeatureAvailable('native_agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
		$formActions = new FormActions($db);
		$formActions->showactions($object, $object->element.'@'.$object->module, 0, 1, '', 10);
	}
	print '</div></div><div class="clearboth"></div>';
}

llxFooter();
$db->close();
