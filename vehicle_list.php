<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../main.inc.php')) {
	$res = @include '../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var HookManager $hookmanager */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('main', 'companies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !lmdbVehicleManagementCanDo($user, 'lmdbvehicle', 'read') || !empty($user->socid)) {
	accessforbidden();
}

$limit = GETPOSTINT('limit') ?: (int) $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page');
if ($page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$offset = $limit * $page;
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 't.registration_number';
$sortorder = strtoupper(GETPOST('sortorder', 'alpha')) === 'DESC' ? 'DESC' : 'ASC';
$allowedSorts = array('t.ref', 't.registration_number', 't.label', 't.brand', 't.model', 't.status', 't.entity');
if (!in_array($sortfield, $allowedSorts, true)) {
	$sortfield = 't.registration_number';
}

$searchRef = GETPOST('search_ref', 'alphanohtml');
$searchRegistration = GETPOST('search_registration_number', 'alphanohtml');
$searchLabel = GETPOST('search_label', 'alphanohtml');
$searchBrand = GETPOST('search_brand', 'alphanohtml');
$searchModel = GETPOST('search_model', 'alphanohtml');
$searchStatus = GETPOSTISSET('search_status') ? GETPOSTINT('search_status') : -1;
$searchEntities = GETPOSTISARRAY('search_entity') ? GETPOST('search_entity', 'array:int') : array();
if (GETPOST('button_removefilter', 'alpha')) {
	$searchRef = $searchRegistration = $searchLabel = $searchBrand = $searchModel = '';
	$searchStatus = -1;
	$searchEntities = array();
}

$object = new LmdbVehicle($db);
$hookmanager->initHooks(array('lmdbvehiclelist'));
$arrayfields = array(
	't.ref' => array('label' => 'Ref', 'checked' => 1, 'enabled' => 1, 'position' => 10),
	't.registration_number' => array('label' => 'RegistrationNumber', 'checked' => 1, 'enabled' => 1, 'position' => 20),
	't.label' => array('label' => 'Label', 'checked' => 1, 'enabled' => 1, 'position' => 30),
	't.brand' => array('label' => 'Brand', 'checked' => 1, 'enabled' => 1, 'position' => 40),
	't.model' => array('label' => 'Model', 'checked' => 1, 'enabled' => 1, 'position' => 50),
	't.status' => array('label' => 'Status', 'checked' => 1, 'enabled' => 1, 'position' => 60),
);

$entityScope = getEntity('lmdbvehicle');
$allowedEntityIds = array_values(array_filter(array_map('intval', explode(',', $entityScope))));
$showEntityColumn = isModEnabled('multicompany') && count($allowedEntityIds) > 1;
$entityOptions = array();
if ($showEntityColumn) {
	$arrayfields['t.entity'] = array('label' => 'Environment', 'checked' => 1, 'enabled' => 1, 'position' => 70);
	$sqlEntity = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'entity WHERE rowid IN ('.$entityScope.') ORDER BY label';
	$resEntity = $db->query($sqlEntity);
	if (!$resEntity) {
		dol_print_error($db);
		exit;
	}
	while (is_object($entityRow = $db->fetch_object($resEntity))) {
		$entityOptions[(int) $entityRow->rowid] = (string) $entityRow->label;
	}
	$db->free($resEntity);
}

$action = GETPOST('action', 'aZ09');
$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

$where = ' WHERE t.entity IN ('.$entityScope.')';
if ($searchRef !== '') {
	$where .= natural_search('t.ref', $searchRef);
}
if ($searchRegistration !== '') {
	$where .= natural_search('t.registration_number', $searchRegistration);
}
if ($searchLabel !== '') {
	$where .= natural_search('t.label', $searchLabel);
}
if ($searchBrand !== '') {
	$where .= natural_search('t.brand', $searchBrand);
}
if ($searchModel !== '') {
	$where .= natural_search('t.model', $searchModel);
}
if ($searchStatus >= 0) {
	$where .= ' AND t.status = '.((int) $searchStatus);
}
if (!empty($searchEntities)) {
	$filteredEntities = array_values(array_intersect($allowedEntityIds, array_map('intval', $searchEntities)));
	if (!empty($filteredEntities)) {
		$where .= ' AND t.entity IN ('.implode(',', $filteredEntities).')';
	}
}
$parameters = array();
$hookmanager->executeHooks('printFieldListWhere', $parameters, $object, $action);
$where .= $hookmanager->resPrint;

$sqlCount = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.$object->table_element.' AS t'.$where;
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

$sql = 'SELECT t.rowid, t.entity, t.ref, t.registration_number, t.label, t.brand, t.model, t.status';
if ($showEntityColumn) {
	$sql .= ', e.label AS entity_label';
}
$sql .= ' FROM '.MAIN_DB_PREFIX.$object->table_element.' AS t';
if ($showEntityColumn) {
	$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'entity AS e ON e.rowid = t.entity';
}
$sql .= $where.$db->order($sortfield, $sortorder).$db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$num = $db->num_rows($resql);

$form = new Form($db);
$title = $langs->trans('VehicleList');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-list bodyforlist');

$param = '';
foreach (array('search_ref' => $searchRef, 'search_registration_number' => $searchRegistration, 'search_label' => $searchLabel, 'search_brand' => $searchBrand, 'search_model' => $searchModel) as $name => $value) {
	if ($value !== '') {
		$param .= '&'.$name.'='.urlencode($value);
	}
}
if ($searchStatus >= 0) {
	$param .= '&search_status='.$searchStatus;
}
foreach ($searchEntities as $entityId) {
	$param .= '&search_entity[]='.((int) $entityId);
}

print '<form method="POST" id="searchFormList" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="page" value="'.((int) $page).'">';

$newButton = dolGetButtonTitle($langs->trans('NewVehicle'), '', 'fa fa-plus-circle', dol_buildpath('/lmdbvehiclemanagement/vehicle_card.php', 1).'?action=create', '', lmdbVehicleManagementCanDo($user, 'lmdbvehicle', 'write'));
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $total, 'car', 0, $newButton, '', $limit, 0, 0, 1);

$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, 'lmdbvehiclelist', 0);
print '<div class="div-table-responsive-no-min">';
print '<table class="tagtable nobottomiftotal noborder liste">';
print '<tr class="liste_titre_filter">';
if (!empty($arrayfields['t.ref']['checked'])) print '<td><input class="flat maxwidth100" type="text" name="search_ref" value="'.dol_escape_htmltag($searchRef).'"></td>';
if (!empty($arrayfields['t.registration_number']['checked'])) print '<td><input class="flat maxwidth100" type="text" name="search_registration_number" value="'.dol_escape_htmltag($searchRegistration).'"></td>';
if (!empty($arrayfields['t.label']['checked'])) print '<td><input class="flat maxwidth150" type="text" name="search_label" value="'.dol_escape_htmltag($searchLabel).'"></td>';
if (!empty($arrayfields['t.brand']['checked'])) print '<td><input class="flat maxwidth100" type="text" name="search_brand" value="'.dol_escape_htmltag($searchBrand).'"></td>';
if (!empty($arrayfields['t.model']['checked'])) print '<td><input class="flat maxwidth100" type="text" name="search_model" value="'.dol_escape_htmltag($searchModel).'"></td>';
if (!empty($arrayfields['t.status']['checked'])) print '<td class="center">'.$form->selectarray('search_status', $object->fields['status']['arrayofkeyval'], $searchStatus, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth100', 1).'</td>';
if ($showEntityColumn && !empty($arrayfields['t.entity']['checked'])) print '<td class="center">'.$form->multiselectarray('search_entity', $entityOptions, $searchEntities, 0, 0, 'maxwidth150', 1).'</td>';
print '<td class="liste_titre maxwidthsearch">'.$form->showFilterButtons().'</td>';
print '</tr>';

print '<tr class="liste_titre">';
if (!empty($arrayfields['t.ref']['checked'])) print getTitleFieldOfList('Ref', 0, $_SERVER['PHP_SELF'], 't.ref', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['t.registration_number']['checked'])) print getTitleFieldOfList('RegistrationNumber', 0, $_SERVER['PHP_SELF'], 't.registration_number', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['t.label']['checked'])) print getTitleFieldOfList('Label', 0, $_SERVER['PHP_SELF'], 't.label', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['t.brand']['checked'])) print getTitleFieldOfList('Brand', 0, $_SERVER['PHP_SELF'], 't.brand', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['t.model']['checked'])) print getTitleFieldOfList('Model', 0, $_SERVER['PHP_SELF'], 't.model', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['t.status']['checked'])) print getTitleFieldOfList('Status', 0, $_SERVER['PHP_SELF'], 't.status', '', $param, 'class="center"', $sortfield, $sortorder, 'center ');
if ($showEntityColumn && !empty($arrayfields['t.entity']['checked'])) print getTitleFieldOfList('Environment', 0, $_SERVER['PHP_SELF'], 't.entity', '', $param, 'class="center"', $sortfield, $sortorder, 'center ');
print '<td class="liste_titre center">'.$selectedfields.'</td>';
print '</tr>';

$visibleColumns = 1;
foreach ($arrayfields as $field) {
	if (!empty($field['checked'])) $visibleColumns++;
}
$i = 0;
while ($i < min($num, $limit) && is_object($row = $db->fetch_object($resql))) {
	$object->setVarsFromFetchObj($row);
	print '<tr class="oddeven">';
	if (!empty($arrayfields['t.ref']['checked'])) print '<td class="nowraponall">'.$object->getNomUrl(1).'</td>';
	if (!empty($arrayfields['t.registration_number']['checked'])) print '<td>'.dol_escape_htmltag($object->registration_number).'</td>';
	if (!empty($arrayfields['t.label']['checked'])) print '<td>'.dol_escape_htmltag($object->label).'</td>';
	if (!empty($arrayfields['t.brand']['checked'])) print '<td>'.dol_escape_htmltag((string) $object->brand).'</td>';
	if (!empty($arrayfields['t.model']['checked'])) print '<td>'.dol_escape_htmltag((string) $object->model).'</td>';
	if (!empty($arrayfields['t.status']['checked'])) print '<td class="center">'.$object->getLibStatut(5).'</td>';
	if ($showEntityColumn && !empty($arrayfields['t.entity']['checked'])) {
		$entityLabel = !empty($row->entity_label) ? (string) $row->entity_label : (string) $row->entity;
		print '<td class="center"><div class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityLabel).'</span></div></td>';
	}
	print '<td></td></tr>';
	$i++;
}
if ($i === 0) {
	print '<tr class="oddeven"><td colspan="'.((int) $visibleColumns).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div></form>';

$db->free($resql);
llxFooter();
$db->close();
