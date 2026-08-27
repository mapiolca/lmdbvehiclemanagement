<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleevent.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var HookManager $hookmanager */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('main', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !lmdbVehicleManagementCanDo($user, 'lmdbvehicle', 'read') || !empty($user->socid)) accessforbidden();

$limit = GETPOSTINT('limit') ?: (int) $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page');
if ($page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) $page = 0;
$offset = $limit * $page;
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'e.event_date';
$sortorder = strtoupper(GETPOST('sortorder', 'alpha')) === 'ASC' ? 'ASC' : 'DESC';
$allowedSorts = array('e.ref', 'e.event_date', 'v.ref', 'e.label', 'e.event_type', 'e.severity', 'e.status', 'e.entity');
if (!in_array($sortfield, $allowedSorts, true)) $sortfield = 'e.event_date';

$searchRef = GETPOST('search_ref', 'alphanohtml');
$searchVehicle = GETPOSTINT('search_vehicle');
$searchLabel = GETPOST('search_label', 'alphanohtml');
$searchType = GETPOST('search_event_type', 'alpha');
$searchStatus = GETPOSTISSET('search_status') ? GETPOSTINT('search_status') : -1;
$searchEntities = GETPOSTISARRAY('search_entity') ? GETPOST('search_entity', 'array:int') : array();
$searchDateStart = GETPOSTINT('search_date_startyear') > 0 ? dol_mktime(0, 0, 0, GETPOSTINT('search_date_startmonth'), GETPOSTINT('search_date_startday'), GETPOSTINT('search_date_startyear')) : 0;
$searchDateEnd = GETPOSTINT('search_date_endyear') > 0 ? dol_mktime(23, 59, 59, GETPOSTINT('search_date_endmonth'), GETPOSTINT('search_date_endday'), GETPOSTINT('search_date_endyear')) : 0;
if (GETPOST('button_removefilter', 'alpha')) {
	$searchRef = $searchLabel = $searchType = '';
	$searchVehicle = 0;
	$searchStatus = -1;
	$searchEntities = array();
	$searchDateStart = $searchDateEnd = 0;
}

$object = new LmdbVehicleEvent($db);
$hookmanager->initHooks(array('lmdbvehicleeventlist'));
$arrayfields = array(
	'e.ref' => array('label' => 'Ref', 'checked' => 1, 'enabled' => 1, 'position' => 10),
	'e.fk_vehicle' => array('label' => 'Vehicle', 'checked' => 1, 'enabled' => 1, 'position' => 20),
	'e.label' => array('label' => 'Label', 'checked' => 1, 'enabled' => 1, 'position' => 30),
	'e.event_type' => array('label' => 'EventType', 'checked' => 1, 'enabled' => 1, 'position' => 40),
	'e.event_date' => array('label' => 'EventDate', 'checked' => 1, 'enabled' => 1, 'position' => 50),
	'e.status' => array('label' => 'Status', 'checked' => 1, 'enabled' => 1, 'position' => 60),
);
$entityScope = getEntity('lmdbvehicle');
$allowedEntityIds = array_values(array_filter(array_map('intval', explode(',', $entityScope))));
$showEntityColumn = isModEnabled('multicompany') && count($allowedEntityIds) > 1;
$entityOptions = array();
if ($showEntityColumn) {
	$arrayfields['e.entity'] = array('label' => 'Environment', 'checked' => 1, 'enabled' => 1, 'position' => 70);
	$resEntity = $db->query('SELECT rowid, label FROM '.MAIN_DB_PREFIX.'entity WHERE rowid IN ('.$entityScope.') ORDER BY label');
	if (!$resEntity) {
		dol_print_error($db);
		exit;
	}
	while (is_object($entityRow = $db->fetch_object($resEntity))) $entityOptions[(int) $entityRow->rowid] = (string) $entityRow->label;
	$db->free($resEntity);
}
$action = GETPOST('action', 'aZ09');
$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';
$vehicleOptions = array();
$sqlVehicles = 'SELECT rowid, ref, registration_number FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle WHERE entity IN ('.$entityScope.') ORDER BY registration_number';
$resVehicles = $db->query($sqlVehicles);
if (!$resVehicles) {
	dol_print_error($db);
	exit;
}
while (is_object($vehicleRow = $db->fetch_object($resVehicles))) $vehicleOptions[(int) $vehicleRow->rowid] = (string) $vehicleRow->registration_number.' — '.(string) $vehicleRow->ref;
$db->free($resVehicles);

$where = ' WHERE e.entity IN ('.$entityScope.')';
if ($searchRef !== '') $where .= natural_search('e.ref', $searchRef);
if ($searchVehicle > 0) $where .= ' AND e.fk_vehicle = '.((int) $searchVehicle);
if ($searchLabel !== '') $where .= natural_search('e.label', $searchLabel);
if ($searchType !== '') $where .= " AND e.event_type = '".$db->escape($searchType)."'";
if ($searchDateStart > 0) $where .= " AND e.event_date >= '".$db->idate($searchDateStart)."'";
if ($searchDateEnd > 0) $where .= " AND e.event_date <= '".$db->idate($searchDateEnd)."'";
if ($searchStatus >= 0) $where .= ' AND e.status = '.((int) $searchStatus);
if (!empty($searchEntities)) {
	$filteredEntities = array_values(array_intersect($allowedEntityIds, array_map('intval', $searchEntities)));
	if (!empty($filteredEntities)) $where .= ' AND e.entity IN ('.implode(',', $filteredEntities).')';
}

$sqlCount = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_event AS e'.$where;
$resCount = $db->query($sqlCount);
$total = 0;
if (!$resCount) {
	dol_print_error($db);
	exit;
}
if (is_object($countRow = $db->fetch_object($resCount))) {
	$total = (int) $countRow->total;
}
$db->free($resCount);
if ($offset > $total) {
	$page = 0;
	$offset = 0;
}
$sql = 'SELECT e.*, v.ref AS vehicle_ref, v.registration_number';
if ($showEntityColumn) $sql .= ', en.label AS entity_label';
$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_event AS e';
$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid = e.fk_vehicle AND v.entity = e.entity';
if ($showEntityColumn) $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'entity AS en ON en.rowid = e.entity';
$sql .= $where.$db->order($sortfield, $sortorder).$db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$num = $db->num_rows($resql);

$form = new Form($db);
$title = $langs->trans('VehicleEvents');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-list bodyforlist');
$param = '&search_ref='.urlencode($searchRef).'&search_vehicle='.((int) $searchVehicle).'&search_label='.urlencode($searchLabel).'&search_event_type='.urlencode($searchType).'&search_status='.((int) $searchStatus);
if ($searchDateStart > 0) {
	$param .= '&search_date_startday='.dol_print_date($searchDateStart, '%d').'&search_date_startmonth='.dol_print_date($searchDateStart, '%m').'&search_date_startyear='.dol_print_date($searchDateStart, '%Y');
}
if ($searchDateEnd > 0) {
	$param .= '&search_date_endday='.dol_print_date($searchDateEnd, '%d').'&search_date_endmonth='.dol_print_date($searchDateEnd, '%m').'&search_date_endyear='.dol_print_date($searchDateEnd, '%Y');
}
foreach ($searchEntities as $entityId) $param .= '&search_entity[]='.((int) $entityId);
print '<form method="POST" id="searchFormList" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="list"><input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'"><input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'"><input type="hidden" name="page" value="'.((int) $page).'">';
$newButton = dolGetButtonTitle($langs->trans('NewVehicleEvent'), '', 'fa fa-plus-circle', dol_buildpath('/lmdbvehiclemanagement/vehicleevent_card.php', 1).'?action=create', '', lmdbVehicleManagementCanDo($user, 'event', 'write'));
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $total, 'calendar-day', 0, $newButton, '', $limit, 0, 0, 1);
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, 'lmdbvehicleeventlist', 0);
print '<div class="div-table-responsive-no-min"><table class="tagtable nobottomiftotal noborder liste">';
print '<tr class="liste_titre_filter">';
if (!empty($arrayfields['e.ref']['checked'])) print '<td><input class="flat maxwidth100" type="text" name="search_ref" value="'.dol_escape_htmltag($searchRef).'"></td>';
if (!empty($arrayfields['e.fk_vehicle']['checked'])) print '<td>'.$form->selectarray('search_vehicle', $vehicleOptions, $searchVehicle, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth200', 1).'</td>';
if (!empty($arrayfields['e.label']['checked'])) print '<td><input class="flat maxwidth150" type="text" name="search_label" value="'.dol_escape_htmltag($searchLabel).'"></td>';
if (!empty($arrayfields['e.event_type']['checked'])) print '<td>'.$form->selectarray('search_event_type', $object->fields['event_type']['arrayofkeyval'], $searchType, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth120', 1).'</td>';
if (!empty($arrayfields['e.event_date']['checked'])) {
	print '<td class="center nowraponall">';
	print '<div class="nowraponall">'.$langs->trans('From').' '.$form->selectDate($searchDateStart ?: -1, 'search_date_start', 0, 0, 1, '', 1, 0).'</div>';
	print '<div class="nowraponall">'.$langs->trans('To').' '.$form->selectDate($searchDateEnd ?: -1, 'search_date_end', 0, 0, 1, '', 1, 0).'</div>';
	print '</td>';
}
if (!empty($arrayfields['e.status']['checked'])) print '<td class="center">'.$form->selectarray('search_status', $object->fields['status']['arrayofkeyval'], $searchStatus, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth100', 1).'</td>';
if ($showEntityColumn && !empty($arrayfields['e.entity']['checked'])) print '<td class="center">'.$form->multiselectarray('search_entity', $entityOptions, $searchEntities, 0, 0, 'maxwidth150', 1).'</td>';
print '<td class="maxwidthsearch">'.$form->showFilterButtons().'</td></tr>';
print '<tr class="liste_titre">';
if (!empty($arrayfields['e.ref']['checked'])) print getTitleFieldOfList('Ref', 0, $_SERVER['PHP_SELF'], 'e.ref', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['e.fk_vehicle']['checked'])) print getTitleFieldOfList('Vehicle', 0, $_SERVER['PHP_SELF'], 'v.ref', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['e.label']['checked'])) print getTitleFieldOfList('Label', 0, $_SERVER['PHP_SELF'], 'e.label', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['e.event_type']['checked'])) print getTitleFieldOfList('EventType', 0, $_SERVER['PHP_SELF'], 'e.event_type', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['e.event_date']['checked'])) print getTitleFieldOfList('EventDate', 0, $_SERVER['PHP_SELF'], 'e.event_date', '', $param, 'class="center"', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['e.status']['checked'])) print getTitleFieldOfList('Status', 0, $_SERVER['PHP_SELF'], 'e.status', '', $param, 'class="center"', $sortfield, $sortorder, 'center ');
if ($showEntityColumn && !empty($arrayfields['e.entity']['checked'])) print getTitleFieldOfList('Environment', 0, $_SERVER['PHP_SELF'], 'e.entity', '', $param, 'class="center"', $sortfield, $sortorder, 'center ');
print '<td class="center">'.$selectedfields.'</td></tr>';

$visibleColumns = 1;
foreach ($arrayfields as $field) if (!empty($field['checked'])) $visibleColumns++;
$i = 0;
while ($i < min($num, $limit) && is_object($row = $db->fetch_object($resql))) {
	$object->setVarsFromFetchObj($row);
	print '<tr class="oddeven">';
	if (!empty($arrayfields['e.ref']['checked'])) print '<td>'.$object->getNomUrl(1).'</td>';
	if (!empty($arrayfields['e.fk_vehicle']['checked'])) print '<td><a href="'.dol_buildpath('/lmdbvehiclemanagement/vehicle_card.php', 1).'?id='.((int) $row->fk_vehicle).'">'.dol_escape_htmltag((string) $row->registration_number).' — '.dol_escape_htmltag((string) $row->vehicle_ref).'</a></td>';
	if (!empty($arrayfields['e.label']['checked'])) print '<td>'.dol_escape_htmltag($object->label).'</td>';
	if (!empty($arrayfields['e.event_type']['checked'])) print '<td>'.$langs->trans($object->fields['event_type']['arrayofkeyval'][$object->event_type]).'</td>';
	if (!empty($arrayfields['e.event_date']['checked'])) print '<td class="center">'.dol_print_date($object->event_date, 'dayhour').'</td>';
	if (!empty($arrayfields['e.status']['checked'])) print '<td class="center">'.$object->getLibStatut(5).'</td>';
	if ($showEntityColumn && !empty($arrayfields['e.entity']['checked'])) {
		$entityLabel = !empty($row->entity_label) ? (string) $row->entity_label : (string) $row->entity;
		print '<td class="center"><div class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityLabel).'</span></div></td>';
	}
	print '<td></td></tr>';
	$i++;
}
if ($i === 0) print '<tr class="oddeven"><td colspan="'.$visibleColumns.'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</table></div></form>';
$db->free($resql);
llxFooter();
$db->close();
