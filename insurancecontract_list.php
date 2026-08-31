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

$langs->loadLangs(array('main', 'companies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();

$limit = GETPOSTINT('limit') ?: (int) $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page');
if ($page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) $page = 0;
$offset = $limit * $page;
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'c.ref';
$sortorder = strtoupper(GETPOST('sortorder', 'alpha')) === 'DESC' ? 'DESC' : 'ASC';
$contextpage = GETPOST('contextpage', 'aZ09');
$allowedSorts = array('c.ref', 'c.policy_number', 'c.label', 's.nom', 'c.date_start', 'c.status', 'c.entity', 'vehicle_count');
if (!in_array($sortfield, $allowedSorts, true)) $sortfield = 'c.ref';

$searchRef = GETPOST('search_ref', 'alphanohtml');
$searchPolicy = GETPOST('search_policy', 'alphanohtml');
$searchLabel = GETPOST('search_label', 'alphanohtml');
$searchCompany = GETPOST('search_company', 'alphanohtml');
$searchStatus = GETPOSTISSET('search_status') ? GETPOSTINT('search_status') : -1;
$searchEntities = GETPOSTISARRAY('search_entity') ? GETPOST('search_entity', 'array:int') : array();
if (GETPOST('button_removefilter', 'alpha')) {
	$searchRef = $searchPolicy = $searchLabel = $searchCompany = '';
	$searchStatus = -1;
	$searchEntities = array();
}

$object = new LmdbVehicleInsuranceContract($db);
$hookmanager->initHooks(array('lmdbinsurancecontractlist'));
$arrayfields = array(
	'c.ref' => array('label' => 'Ref', 'checked' => 1, 'enabled' => 1, 'position' => 10),
	'c.policy_number' => array('label' => 'InsurancePolicyNumber', 'checked' => 1, 'enabled' => 1, 'position' => 20),
	'c.label' => array('label' => 'Label', 'checked' => 1, 'enabled' => 1, 'position' => 30),
	's.nom' => array('label' => 'InsuranceCompany', 'checked' => 1, 'enabled' => 1, 'position' => 40),
	'c.date_start' => array('label' => 'Period', 'checked' => 1, 'enabled' => 1, 'position' => 50),
	'vehicle_count' => array('label' => 'InsuranceVehicleCount', 'checked' => 1, 'enabled' => 1, 'position' => 60),
	'c.status' => array('label' => 'Status', 'checked' => 1, 'enabled' => 1, 'position' => 70),
);

$entityScope = getEntity('lmdbvehicle');
$allowedEntityIds = array_values(array_filter(array_map('intval', explode(',', $entityScope))));
$entityOptions = lmdbVehicleManagementGetEntityOptions('lmdbvehicle');
$showEntityColumn = !empty($entityOptions);
if (!$showEntityColumn) $searchEntities = array();
if ($showEntityColumn) {
	$arrayfields['c.entity'] = array('label' => 'Environment', 'checked' => 1, 'enabled' => 1, 'position' => 80);
}

$action = GETPOST('action', 'aZ09');
$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

$where = ' WHERE c.entity IN ('.$entityScope.')';
if ($searchRef !== '') $where .= natural_search('c.ref', $searchRef);
if ($searchPolicy !== '') $where .= natural_search('c.policy_number', $searchPolicy);
if ($searchLabel !== '') $where .= natural_search('c.label', $searchLabel);
if ($searchCompany !== '') $where .= natural_search('s.nom', $searchCompany);
if ($searchStatus >= 0) $where .= ' AND c.status = '.((int) $searchStatus);
if ($showEntityColumn && !empty($searchEntities)) {
	$filteredEntities = array_values(array_intersect($allowedEntityIds, array_map('intval', $searchEntities)));
	if (!empty($filteredEntities)) $where .= ' AND c.entity IN ('.implode(',', $filteredEntities).')';
}
$parameters = array();
$hookmanager->executeHooks('printFieldListWhere', $parameters, $object, $action);
$where .= $hookmanager->resPrint;

$sqlFrom = ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract AS c';
$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe AS s ON s.rowid = c.fk_soc';
$sqlCount = 'SELECT COUNT(*) AS total'.$sqlFrom.$where;
$resCount = $db->query($sqlCount);
if (!$resCount) {
	dol_print_error($db);
	exit;
}
$total = 0;
if (is_object($countRow = $db->fetch_object($resCount))) $total = (int) $countRow->total;
$db->free($resCount);
if ($offset > $total) {
	$page = 0;
	$offset = 0;
}

$vehicleCountSql = '(SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle AS cv WHERE cv.fk_contract = c.rowid AND cv.entity = c.entity)';
$sql = 'SELECT c.rowid, c.entity, c.ref, c.policy_number, c.label, c.fk_soc, c.date_start, c.date_end, c.status, s.nom AS company_name, '.$vehicleCountSql.' AS vehicle_count';
$sql .= $sqlFrom;
$sql .= $where.$db->order($sortfield, $sortorder).$db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$num = $db->num_rows($resql);

$form = new Form($db);
$title = $langs->trans('InsuranceContractList');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-list bodyforlist');

$param = '';
foreach (array('search_ref' => $searchRef, 'search_policy' => $searchPolicy, 'search_label' => $searchLabel, 'search_company' => $searchCompany) as $name => $value) {
	if ($value !== '') $param .= '&'.$name.'='.urlencode($value);
}
if ($searchStatus >= 0) $param .= '&search_status='.$searchStatus;
foreach ($searchEntities as $entityId) $param .= '&search_entity[]='.((int) $entityId);

print '<form method="POST" id="searchFormList" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="page" value="'.((int) $page).'">';
$newButton = dolGetButtonTitle($langs->trans('NewInsuranceContract'), '', 'fa fa-plus-circle', dol_buildpath('/lmdbvehiclemanagement/insurancecontract_card.php', 1).'?action=create', '', $user->hasRight('lmdbvehiclemanagement', 'insurance', 'write'));
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $total, 'shield-alt', 0, $newButton, '', $limit, 0, 0, 1);

$varpage = empty($contextpage) ? $_SERVER['PHP_SELF'] : $contextpage;
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, $conf->main_checkbox_left_column);
print '<div class="div-table-responsive"><table class="tagtable nobottomiftotal noborder liste">';
print '<tr class="liste_titre_filter">';
if ($conf->main_checkbox_left_column) print '<td class="liste_titre center maxwidthsearch actioncolumn">'.$form->showFilterButtons('left').'</td>';
if (!empty($arrayfields['c.ref']['checked'])) print '<td><input class="flat maxwidth100" name="search_ref" value="'.dol_escape_htmltag($searchRef).'"></td>';
if (!empty($arrayfields['c.policy_number']['checked'])) print '<td><input class="flat maxwidth120" name="search_policy" value="'.dol_escape_htmltag($searchPolicy).'"></td>';
if (!empty($arrayfields['c.label']['checked'])) print '<td><input class="flat maxwidth150" name="search_label" value="'.dol_escape_htmltag($searchLabel).'"></td>';
if (!empty($arrayfields['s.nom']['checked'])) print '<td><input class="flat maxwidth150" name="search_company" value="'.dol_escape_htmltag($searchCompany).'"></td>';
if (!empty($arrayfields['c.date_start']['checked'])) print '<td></td>';
if (!empty($arrayfields['vehicle_count']['checked'])) print '<td></td>';
if (!empty($arrayfields['c.status']['checked'])) print '<td class="center">'.$form->selectarray('search_status', $object->fields['status']['arrayofkeyval'], $searchStatus, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth100', 1).'</td>';
if ($showEntityColumn && !empty($arrayfields['c.entity']['checked'])) print '<td class="center">'.$form->multiselectarray('search_entity', $entityOptions, $searchEntities, 0, 0, 'maxwidth150', 1).'</td>';
if (!$conf->main_checkbox_left_column) print '<td class="liste_titre center maxwidthsearch actioncolumn">'.$form->showFilterButtons().'</td>';
print '</tr>';

print '<tr class="liste_titre">';
if ($conf->main_checkbox_left_column) print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
if (!empty($arrayfields['c.ref']['checked'])) print getTitleFieldOfList('Ref', 0, $_SERVER['PHP_SELF'], 'c.ref', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['c.policy_number']['checked'])) print getTitleFieldOfList('InsurancePolicyNumber', 0, $_SERVER['PHP_SELF'], 'c.policy_number', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['c.label']['checked'])) print getTitleFieldOfList('Label', 0, $_SERVER['PHP_SELF'], 'c.label', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['s.nom']['checked'])) print getTitleFieldOfList('InsuranceCompany', 0, $_SERVER['PHP_SELF'], 's.nom', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['c.date_start']['checked'])) print getTitleFieldOfList('Period', 0, $_SERVER['PHP_SELF'], 'c.date_start', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['vehicle_count']['checked'])) print getTitleFieldOfList('InsuranceVehicleCount', 0, $_SERVER['PHP_SELF'], 'vehicle_count', '', $param, 'class="center"', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['c.status']['checked'])) print getTitleFieldOfList('Status', 0, $_SERVER['PHP_SELF'], 'c.status', '', $param, 'class="center"', $sortfield, $sortorder, 'center ');
if ($showEntityColumn && !empty($arrayfields['c.entity']['checked'])) print getTitleFieldOfList('Environment', 0, $_SERVER['PHP_SELF'], 'c.entity', '', $param, 'class="center"', $sortfield, $sortorder, 'center ');
if (!$conf->main_checkbox_left_column) print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
print '</tr>';

$visibleColumns = 1;
foreach ($arrayfields as $field) if (!empty($field['checked'])) $visibleColumns++;
$i = 0;
while ($i < min($num, $limit) && is_object($row = $db->fetch_object($resql))) {
	$object->setVarsFromFetchObj($row);
	print '<tr class="oddeven">';
	if ($conf->main_checkbox_left_column) print '<td class="center nowraponall actioncolumn"></td>';
	if (!empty($arrayfields['c.ref']['checked'])) print '<td class="nowraponall">'.$object->getNomUrl(1).'</td>';
	if (!empty($arrayfields['c.policy_number']['checked'])) print '<td>'.dol_escape_htmltag((string) $row->policy_number).'</td>';
	if (!empty($arrayfields['c.label']['checked'])) print '<td>'.dol_escape_htmltag((string) $row->label).'</td>';
	if (!empty($arrayfields['s.nom']['checked'])) print '<td><a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.((int) $row->fk_soc).'">'.img_picto('', 'company', 'class="pictofixedwidth"').dol_escape_htmltag((string) $row->company_name).'</a></td>';
	if (!empty($arrayfields['c.date_start']['checked'])) print '<td>'.dol_print_date($db->jdate($row->date_start), 'day').' — '.(!empty($row->date_end) ? dol_print_date($db->jdate($row->date_end), 'day') : $langs->trans('NoLimit')).'</td>';
	if (!empty($arrayfields['vehicle_count']['checked'])) print '<td class="center">'.((int) $row->vehicle_count).'</td>';
	if (!empty($arrayfields['c.status']['checked'])) print '<td class="center">'.$object->getLibStatut(5).'</td>';
	if ($showEntityColumn && !empty($arrayfields['c.entity']['checked'])) {
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
