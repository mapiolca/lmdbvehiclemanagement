<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumption.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var HookManager $hookmanager */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('main', 'users', 'currencies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !lmdbVehicleManagementCanDo($user, 'read') || !empty($user->socid)) accessforbidden();
$limit = GETPOSTINT('limit') ?: (int) $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page');
if ($page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) $page = 0;
$offset = $limit * $page;
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'r.reading_date';
$sortorder = strtoupper(GETPOST('sortorder', 'alpha')) === 'ASC' ? 'ASC' : 'DESC';
$contextpage = GETPOST('contextpage', 'aZ09');
$allowedSorts = array('t.ref', 'r.reading_date', 'v.ref', 'u.lastname', 'c.label', 't.quantity', 'r.odometer_km', 't.total_ttc', 'unit_price', 'capacity_percent', 't.entity');
if (!in_array($sortfield, $allowedSorts, true)) $sortfield = 'r.reading_date';
$searchRef = GETPOST('search_ref', 'alphanohtml');
$searchVehicle = GETPOSTINT('search_vehicle');
$searchDriver = GETPOSTINT('search_driver');
$searchConsumable = GETPOSTINT('search_consumable');
$searchCategory = GETPOST('search_category', 'alpha');
$searchEntities = GETPOSTISARRAY('search_entity') ? GETPOST('search_entity', 'array:int') : array();
if (GETPOST('button_removefilter', 'alpha')) {
	$searchRef = $searchCategory = '';
	$searchVehicle = $searchDriver = $searchConsumable = 0;
	$searchEntities = array();
}
$object = new LmdbVehicleConsumption($db);
$vehicle = new LmdbVehicle($db);
$dictionary = new LmdbVehicleConsumable($db);
$form = new Form($db);
$hookmanager->initHooks(array('lmdbvehicleconsumptionlist'));
$arrayfields = array(
	't.ref' => array('label' => 'Ref', 'checked' => 1, 'enabled' => 1, 'position' => 10),
	'r.reading_date' => array('label' => 'Date', 'checked' => 1, 'enabled' => 1, 'position' => 20),
	'v.ref' => array('label' => 'Vehicle', 'checked' => 1, 'enabled' => 1, 'position' => 30),
	'u.lastname' => array('label' => 'Driver', 'checked' => 1, 'enabled' => 1, 'position' => 40),
	'c.label' => array('label' => 'Consumable', 'checked' => 1, 'enabled' => 1, 'position' => 50),
	't.quantity' => array('label' => 'Quantity', 'checked' => 1, 'enabled' => 1, 'position' => 60),
	'r.odometer_km' => array('label' => 'OdometerKm', 'checked' => 1, 'enabled' => 1, 'position' => 70),
	't.total_ttc' => array('label' => 'TotalTTC', 'checked' => 1, 'enabled' => 1, 'position' => 80),
	'unit_price' => array('label' => 'UnitPrice', 'checked' => 1, 'enabled' => 1, 'position' => 90),
	'capacity_percent' => array('label' => 'RecoveredCapacity', 'checked' => 1, 'enabled' => 1, 'position' => 100),
);
$entityScope = getEntity('lmdbvehicleconsumption');
$allowedEntityIds = array_values(array_filter(array_map('intval', explode(',', $entityScope))));
$entityOptions = lmdbVehicleManagementGetEntityOptions('lmdbvehicleconsumption');
$showEntityColumn = !empty($entityOptions);
if (!$showEntityColumn) $searchEntities = array();
if ($showEntityColumn) {
	$arrayfields['t.entity'] = array('label' => 'Environment', 'checked' => 1, 'enabled' => 1, 'position' => 110);
}
$vehicleOptions = array();
$resOptions = $db->query('SELECT rowid, ref, registration_number, label FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle WHERE entity IN ('.getEntity('lmdbvehicle').') ORDER BY ref');
if ($resOptions) {
	while (is_object($row = $db->fetch_object($resOptions))) $vehicleOptions[(int) $row->rowid] = lmdbVehicleDisplayIdentifier((string) $row->ref, (string) $row->registration_number, (string) $row->label);
	$db->free($resOptions);
}
$consumableOptions = $dictionary->getOptions();
$action = GETPOST('action', 'aZ09');
$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';
$where = ' WHERE t.entity IN ('.$entityScope.')';
if ($searchRef !== '') $where .= natural_search('t.ref', $searchRef);
if ($searchVehicle > 0) $where .= ' AND t.fk_vehicle = '.$searchVehicle;
if ($searchDriver > 0) $where .= ' AND COALESCE(t.fk_user_driver, t.fk_user_creat) = '.$searchDriver;
if ($searchConsumable > 0) $where .= ' AND t.fk_consumable = '.$searchConsumable;
if (in_array($searchCategory, array('fuel', 'additive'), true)) $where .= " AND t.category_snapshot = '".$db->escape($searchCategory)."'";
if ($showEntityColumn && !empty($searchEntities)) {
	$filtered = array_values(array_intersect($allowedEntityIds, array_map('intval', $searchEntities)));
	$where .= !empty($filtered) ? ' AND t.entity IN ('.implode(',', $filtered).')' : ' AND 1 = 0';
}
$sqlFrom = ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumption AS t';
$sqlFrom .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading AS r ON r.rowid = t.fk_odometer_reading AND r.entity = t.entity';
$sqlFrom .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid = t.fk_vehicle AND v.entity = t.entity';
$sqlFrom .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable AS c ON c.rowid = t.fk_consumable';
$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user AS u ON u.rowid = COALESCE(t.fk_user_driver, t.fk_user_creat)';
$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_capacity AS cap ON cap.entity = t.entity AND cap.fk_vehicle = t.fk_vehicle AND cap.fk_consumable = t.fk_consumable';
$resCount = $db->query('SELECT COUNT(*) AS total'.$sqlFrom.$where);
if (!$resCount) { dol_print_error($db); exit; }
$total = is_object($countRow = $db->fetch_object($resCount)) ? (int) $countRow->total : 0;
$db->free($resCount);
if ($offset > $total) { $page = 0; $offset = 0; }
$sql = 'SELECT t.rowid, t.entity, t.ref, t.fk_vehicle, t.fk_consumable, COALESCE(t.fk_user_driver, t.fk_user_creat) AS fk_user_driver, t.category_snapshot, t.unit_snapshot, t.quantity, t.total_ttc, t.currency_snapshot, t.status,';
$sql .= ' r.reading_date, r.odometer_km, v.ref AS vehicle_ref, v.registration_number, v.label AS vehicle_label, c.label AS consumable_label,';
$sql .= ' u.login, u.firstname, u.lastname, CASE WHEN t.quantity > 0 THEN t.total_ttc / t.quantity ELSE 0 END AS unit_price,';
$sql .= ' CASE WHEN cap.capacity > 0 THEN t.quantity / cap.capacity * 100 ELSE NULL END AS capacity_percent';
$sql .= $sqlFrom;
$sql .= $where.$db->order($sortfield, $sortorder).$db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
if (!$resql) { dol_print_error($db); exit; }
$num = $db->num_rows($resql);
$title = $langs->trans('ConsumptionList');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-list bodyforlist');
$param = '';
if ($searchRef !== '') $param .= '&search_ref='.urlencode($searchRef);
if ($searchVehicle > 0) $param .= '&search_vehicle='.$searchVehicle;
if ($searchDriver > 0) $param .= '&search_driver='.$searchDriver;
if ($searchConsumable > 0) $param .= '&search_consumable='.$searchConsumable;
if ($searchCategory !== '') $param .= '&search_category='.urlencode($searchCategory);
foreach ($searchEntities as $entityId) $param .= '&search_entity[]='.((int) $entityId);
print '<form method="POST" id="searchFormList" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="formfilteraction" id="formfilteraction" value="list"><input type="hidden" name="action" value="list"><input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'"><input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'"><input type="hidden" name="page" value="'.((int) $page).'">';
$newButton = '';
if (lmdbVehicleManagementCanDo($user, 'consumption', 'import')) $newButton .= dolGetButtonTitle($langs->trans('Import'), '', 'fa fa-file-import', dol_buildpath('/lmdbvehiclemanagement/consumption_import.php', 1), '', true);
$newButton .= dolGetButtonTitle($langs->trans('NewConsumption'), '', 'fa fa-plus-circle', dol_buildpath('/lmdbvehiclemanagement/consumption_card.php', 1).'?action=create&token='.newToken(), '', lmdbVehicleManagementCanDo($user, 'consumption', 'write'));
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $total, 'gas-pump', 0, $newButton, '', $limit, 0, 0, 1);
$varpage = empty($contextpage) ? $_SERVER['PHP_SELF'] : $contextpage;
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, $conf->main_checkbox_left_column);
print '<div class="div-table-responsive"><table class="tagtable nobottomiftotal noborder liste"><tr class="liste_titre_filter">';
if ($conf->main_checkbox_left_column) print '<td class="liste_titre center maxwidthsearch actioncolumn">'.$form->showFilterButtons('left').'</td>';
if (!empty($arrayfields['t.ref']['checked'])) print '<td><input class="flat maxwidth100" name="search_ref" value="'.dol_escape_htmltag($searchRef).'"></td>';
if (!empty($arrayfields['r.reading_date']['checked'])) print '<td></td>';
if (!empty($arrayfields['v.ref']['checked'])) print '<td>'.$form->selectarray('search_vehicle', $vehicleOptions, $searchVehicle, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth200', 1).'</td>';
if (!empty($arrayfields['u.lastname']['checked'])) print '<td>'.$form->select_dolusers($searchDriver ?: '', 'search_driver', 1, null, 0, '', '', '', 0, 1, '', 0, '', 'maxwidth150', 0, 0, false, 1).'</td>';
if (!empty($arrayfields['c.label']['checked'])) print '<td>'.$form->selectarray('search_consumable', $consumableOptions, $searchConsumable, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth150', 1).'<br>'.$form->selectarray('search_category', array('fuel' => $langs->trans('FuelOrRecharge'), 'additive' => $langs->trans('Additive')), $searchCategory, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth150', 1).'</td>';
foreach (array('t.quantity', 'r.odometer_km', 't.total_ttc', 'unit_price', 'capacity_percent') as $field) if (!empty($arrayfields[$field]['checked'])) print '<td></td>';
if ($showEntityColumn && !empty($arrayfields['t.entity']['checked'])) print '<td class="center">'.$form->multiselectarray('search_entity', $entityOptions, $searchEntities, 0, 0, 'maxwidth150', 1).'</td>';
if (!$conf->main_checkbox_left_column) print '<td class="liste_titre center maxwidthsearch actioncolumn">'.$form->showFilterButtons().'</td>';
print '</tr><tr class="liste_titre">';
if ($conf->main_checkbox_left_column) print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
foreach (array('t.ref' => 'Ref', 'r.reading_date' => 'Date', 'v.ref' => 'Vehicle', 'u.lastname' => 'Driver', 'c.label' => 'Consumable', 't.quantity' => 'Quantity', 'r.odometer_km' => 'OdometerKm', 't.total_ttc' => 'TotalTTC', 'unit_price' => 'UnitPrice', 'capacity_percent' => 'RecoveredCapacity') as $field => $label) {
	if (!empty($arrayfields[$field]['checked'])) print getTitleFieldOfList($label, 0, $_SERVER['PHP_SELF'], $field, '', $param, '', $sortfield, $sortorder);
}
if ($showEntityColumn && !empty($arrayfields['t.entity']['checked'])) print getTitleFieldOfList('Environment', 0, $_SERVER['PHP_SELF'], 't.entity', '', $param, 'class="center"', $sortfield, $sortorder, 'center ');
if (!$conf->main_checkbox_left_column) print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
print '</tr>';
$visibleColumns = 1;
foreach ($arrayfields as $field) if (!empty($field['checked'])) $visibleColumns++;
$i = 0;
while ($i < min($num, $limit) && is_object($row = $db->fetch_object($resql))) {
	$object->id = (int) $row->rowid; $object->ref = (string) $row->ref; $object->entity = (int) $row->entity;
	$vehicle->id = (int) $row->fk_vehicle; $vehicle->entity = (int) $row->entity; $vehicle->ref = (string) $row->vehicle_ref;
	$vehicle->registration_number = (string) $row->registration_number; $vehicle->label = (string) $row->vehicle_label;
	print '<tr class="oddeven">';
	if ($conf->main_checkbox_left_column) print '<td class="center nowraponall actioncolumn"></td>';
	if (!empty($arrayfields['t.ref']['checked'])) print '<td class="nowraponall">'.$object->getNomUrl(1).'</td>';
	if (!empty($arrayfields['r.reading_date']['checked'])) print '<td>'.dol_print_date($db->jdate($row->reading_date), 'dayhour').'</td>';
	if (!empty($arrayfields['v.ref']['checked'])) print '<td>'.$vehicle->getNomUrl(1).'</td>';
	if (!empty($arrayfields['u.lastname']['checked'])) print '<td>'.dol_escape_htmltag(trim((string) $row->firstname.' '.(string) $row->lastname) ?: (string) $row->login).'</td>';
	if (!empty($arrayfields['c.label']['checked'])) print '<td>'.dol_escape_htmltag((string) $row->consumable_label).'</td>';
	if (!empty($arrayfields['t.quantity']['checked'])) print '<td class="right">'.price($row->quantity).' '.dol_escape_htmltag(LmdbVehicleConsumable::unitLabel((string) $row->unit_snapshot)).'</td>';
	if (!empty($arrayfields['r.odometer_km']['checked'])) print '<td class="right">'.price($row->odometer_km).' '.$langs->trans('UnitKm').'</td>';
	if (!empty($arrayfields['t.total_ttc']['checked'])) print '<td class="right">'.price($row->total_ttc).' '.dol_escape_htmltag((string) $row->currency_snapshot).'</td>';
	if (!empty($arrayfields['unit_price']['checked'])) print '<td class="right">'.price($row->unit_price).' '.dol_escape_htmltag((string) $row->currency_snapshot).'/'.dol_escape_htmltag(LmdbVehicleConsumable::unitLabel((string) $row->unit_snapshot)).'</td>';
	if (!empty($arrayfields['capacity_percent']['checked'])) print '<td class="right">'.($row->capacity_percent !== null ? price($row->capacity_percent).' %'.((float) $row->capacity_percent > 100 ? ' '.img_warning() : '') : '').'</td>';
	if ($showEntityColumn && !empty($arrayfields['t.entity']['checked'])) {
		print '<td class="center">'.lmdbVehicleManagementEntityBadge((int) $row->entity, $entityOptions).'</td>';
	}
	if (!$conf->main_checkbox_left_column) print '<td class="center nowraponall actioncolumn"></td>';
	print '</tr>';
	$i++;
}
if ($i === 0) print '<tr class="oddeven"><td colspan="'.((int) $visibleColumns).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</table></div></form>';
$db->free($resql);
llxFooter();
$db->close();
