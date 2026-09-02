<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleregulatorycontrol.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */ /** @var DoliDB $db */ /** @var HookManager $hookmanager */ /** @var Translate $langs */ /** @var User $user */
$langs->loadLangs(array('main', 'companies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();

$limit = GETPOSTINT('limit') ?: (int) $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page');
if ($page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) $page = 0;
$offset = $limit * $page;
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 't.control_date';
$sortorder = strtoupper(GETPOST('sortorder', 'alpha')) === 'ASC' ? 'ASC' : 'DESC';
$contextpage = GETPOST('contextpage', 'aZ09');
$allowedSorts = array('t.ref', 't.control_date', 'v.ref', 'r.label', 't.control_kind', 's.nom', 't.result_code', 't.retained_valid_until', 't.status', 't.entity');
if (!in_array($sortfield, $allowedSorts, true)) $sortfield = 't.control_date';
$searchRef = GETPOST('search_ref', 'alphanohtml');
$searchVehicle = GETPOST('search_vehicle', 'alphanohtml');
$searchRule = GETPOST('search_rule', 'alphanohtml');
$searchKind = GETPOST('search_kind', 'aZ09');
$searchProvider = GETPOST('search_provider', 'alphanohtml');
$searchResult = GETPOST('search_result', 'aZ09');
$searchStatus = GETPOSTISSET('search_status') ? GETPOSTINT('search_status') : -1;
$searchEntities = GETPOSTISARRAY('search_entity') ? GETPOST('search_entity', 'array:int') : array();
if (GETPOST('button_removefilter', 'alpha')) { $searchRef = $searchVehicle = $searchRule = $searchKind = $searchProvider = $searchResult = ''; $searchStatus = -1; $searchEntities = array(); }

$object = new LmdbVehicleRegulatoryControl($db);
$arrayfields = array(
	't.ref' => array('label' => 'Ref', 'checked' => 1, 'enabled' => 1, 'position' => 10),
	't.control_date' => array('label' => 'ControlDate', 'checked' => 1, 'enabled' => 1, 'position' => 20),
	'v.ref' => array('label' => 'VehicleOrEquipment', 'checked' => 1, 'enabled' => 1, 'position' => 30),
	'r.label' => array('label' => 'Control', 'checked' => 1, 'enabled' => 1, 'position' => 40),
	't.control_kind' => array('label' => 'ControlKind', 'checked' => 1, 'enabled' => 1, 'position' => 50),
	's.nom' => array('label' => 'ControlBody', 'checked' => 1, 'enabled' => 1, 'position' => 60),
	't.result_code' => array('label' => 'ControlResult', 'checked' => 1, 'enabled' => 1, 'position' => 70),
	't.retained_valid_until' => array('label' => 'RetainedValidUntil', 'checked' => 1, 'enabled' => 1, 'position' => 80),
	't.status' => array('label' => 'Status', 'checked' => 1, 'enabled' => 1, 'position' => 90),
);
$entityScope = getEntity('lmdbvehicleregulatorycontrol');
$allowedEntityIds = array_values(array_filter(array_map('intval', explode(',', $entityScope))));
$entityOptions = lmdbVehicleManagementGetEntityOptions('lmdbvehicleregulatorycontrol');
$showEntityColumn = !empty($entityOptions);
if ($showEntityColumn) $arrayfields['t.entity'] = array('label' => 'Environment', 'checked' => 1, 'enabled' => 1, 'position' => 100); else $searchEntities = array();
$hookmanager->initHooks(array('lmdbvehicleregulatorycontrollist'));
$action = GETPOST('action', 'aZ09');
$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

$resultOptions = array();
$resql = $db->query('SELECT code, label FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_control_result WHERE entity IN ('.getEntity('c_lmdbvehiclemanagement_control_result').') AND active = 1 ORDER BY position');
if ($resql) { while (is_object($row = $db->fetch_object($resql))) $resultOptions[(string) $row->code] = $langs->trans((string) $row->label); $db->free($resql); }
$where = ' WHERE t.entity IN ('.$entityScope.')';
if ($searchRef !== '') $where .= natural_search('t.ref', $searchRef);
if ($searchVehicle !== '') $where .= natural_search(array('v.ref', 'v.registration_number', 'v.label'), $searchVehicle);
if ($searchRule !== '') $where .= natural_search('r.label', $searchRule);
if ($searchKind !== '') $where .= " AND t.control_kind = '".$db->escape($searchKind)."'";
if ($searchProvider !== '') $where .= natural_search('s.nom', $searchProvider);
if ($searchResult !== '') $where .= " AND t.result_code = '".$db->escape($searchResult)."'";
if ($searchStatus >= 0) $where .= ' AND t.status = '.$searchStatus;
if ($showEntityColumn && !empty($searchEntities)) { $filtered = array_values(array_intersect($allowedEntityIds, array_map('intval', $searchEntities))); if (!empty($filtered)) $where .= ' AND t.entity IN ('.implode(',', $filtered).')'; }
$from = ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_control AS t INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid = t.fk_vehicle AND v.entity = t.entity INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule AS r ON r.rowid = t.fk_rule AND r.entity = t.entity LEFT JOIN '.MAIN_DB_PREFIX.'societe AS s ON s.rowid = t.fk_soc_provider';
$resCount = $db->query('SELECT COUNT(*) AS total'.$from.$where);
if (!$resCount) { dol_print_error($db); exit; }
$countRow = $db->fetch_object($resCount); $total = is_object($countRow) ? (int) $countRow->total : 0; $db->free($resCount);
if ($offset > $total) { $page = 0; $offset = 0; }
$sql = 'SELECT t.*, v.ref AS vehicle_ref, v.registration_number, v.label AS vehicle_label, r.label AS rule_label, s.nom AS provider_name'.$from.$where.$db->order($sortfield, $sortorder).$db->plimit($limit + 1, $offset);
$resql = $db->query($sql); if (!$resql) { dol_print_error($db); exit; } $num = $db->num_rows($resql);

$form = new Form($db); $title = $langs->trans('RegulatoryControlList');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-list bodyforlist');
$param = '';
foreach (array('search_ref' => $searchRef, 'search_vehicle' => $searchVehicle, 'search_rule' => $searchRule, 'search_kind' => $searchKind, 'search_provider' => $searchProvider, 'search_result' => $searchResult) as $name => $value) if ($value !== '') $param .= '&'.$name.'='.urlencode($value);
if ($searchStatus >= 0) $param .= '&search_status='.$searchStatus;
foreach ($searchEntities as $entityId) $param .= '&search_entity[]='.((int) $entityId);
print '<form method="POST" id="searchFormList" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="formfilteraction" id="formfilteraction" value="list"><input type="hidden" name="action" value="list"><input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'"><input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'"><input type="hidden" name="page" value="'.$page.'">';
$newButton = dolGetButtonTitle($langs->trans('NewRegulatoryControl'), '', 'fa fa-plus-circle', dol_buildpath('/lmdbvehiclemanagement/regulatorycontrol_card.php', 1).'?action=create', '', $user->hasRight('lmdbvehiclemanagement', 'regulatorycontrol', 'write'));
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $total, 'clipboard-check', 0, $newButton, '', $limit, 0, 0, 1);
$varpage = empty($contextpage) ? $_SERVER['PHP_SELF'] : $contextpage;
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, $conf->main_checkbox_left_column);
print '<div class="div-table-responsive"><table class="tagtable nobottomiftotal noborder liste"><tr class="liste_titre_filter">';
if ($conf->main_checkbox_left_column) print '<td class="liste_titre center maxwidthsearch actioncolumn">'.$form->showFilterButtons('left').'</td>';
if (!empty($arrayfields['t.ref']['checked'])) print '<td><input class="flat maxwidth100" name="search_ref" value="'.dol_escape_htmltag($searchRef).'"></td>';
if (!empty($arrayfields['t.control_date']['checked'])) print '<td></td>';
if (!empty($arrayfields['v.ref']['checked'])) print '<td><input class="flat maxwidth150" name="search_vehicle" value="'.dol_escape_htmltag($searchVehicle).'"></td>';
if (!empty($arrayfields['r.label']['checked'])) print '<td><input class="flat maxwidth150" name="search_rule" value="'.dol_escape_htmltag($searchRule).'"></td>';
if (!empty($arrayfields['t.control_kind']['checked'])) print '<td>'.$form->selectarray('search_kind', $object->fields['control_kind']['arrayofkeyval'], $searchKind, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth150', 1).'</td>';
if (!empty($arrayfields['s.nom']['checked'])) print '<td><input class="flat maxwidth150" name="search_provider" value="'.dol_escape_htmltag($searchProvider).'"></td>';
if (!empty($arrayfields['t.result_code']['checked'])) print '<td>'.$form->selectarray('search_result', $resultOptions, $searchResult, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth150', 1).'</td>';
if (!empty($arrayfields['t.retained_valid_until']['checked'])) print '<td></td>';
if (!empty($arrayfields['t.status']['checked'])) print '<td>'.$form->selectarray('search_status', $object->fields['status']['arrayofkeyval'] ?? array(0 => 'ControlStatusDraft', 1 => 'ControlStatusValidated', 2 => 'ControlStatusCancelled', 3 => 'ControlStatusArchived'), $searchStatus, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth150', 1).'</td>';
if ($showEntityColumn && !empty($arrayfields['t.entity']['checked'])) print '<td>'.$form->multiselectarray('search_entity', $entityOptions, $searchEntities, 0, 0, 'maxwidth150', 1).'</td>';
if (!$conf->main_checkbox_left_column) print '<td class="liste_titre center maxwidthsearch actioncolumn">'.$form->showFilterButtons().'</td>';
print '</tr><tr class="liste_titre">';
if ($conf->main_checkbox_left_column) print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
foreach ($arrayfields as $field => $definition) if (!empty($definition['checked'])) print getTitleFieldOfList($definition['label'], 0, $_SERVER['PHP_SELF'], $field, '', $param, in_array($field, array('t.status', 't.entity'), true) ? 'class="center"' : '', $sortfield, $sortorder);
if (!$conf->main_checkbox_left_column) print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
print '</tr>';
$visibleColumns = 1; foreach ($arrayfields as $definition) if (!empty($definition['checked'])) $visibleColumns++;
$i = 0; while ($i < min($num, $limit) && is_object($row = $db->fetch_object($resql))) {
	$object->setVarsFromFetchObj($row); $vehicle = new LmdbVehicle($db); $vehicle->id = (int) $row->fk_vehicle; $vehicle->ref = (string) $row->vehicle_ref; $vehicle->registration_number = (string) $row->registration_number; $vehicle->label = (string) $row->vehicle_label; $provider = new Societe($db); $provider->id = (int) $row->fk_soc_provider; $provider->name = (string) $row->provider_name;
	print '<tr class="oddeven">'; if ($conf->main_checkbox_left_column) print '<td class="center nowraponall actioncolumn"></td>';
	if (!empty($arrayfields['t.ref']['checked'])) print '<td>'.$object->getNomUrl(1).'</td>';
	if (!empty($arrayfields['t.control_date']['checked'])) print '<td>'.dol_print_date($db->jdate($row->control_date), 'dayhour').'</td>';
	if (!empty($arrayfields['v.ref']['checked'])) print '<td>'.$vehicle->getNomUrl(1).'</td>';
	if (!empty($arrayfields['r.label']['checked'])) print '<td>'.dol_escape_htmltag($langs->trans((string) $row->rule_label)).'</td>';
	if (!empty($arrayfields['t.control_kind']['checked'])) print '<td>'.$langs->trans(isset($object->fields['control_kind']['arrayofkeyval'][$row->control_kind]) ? $object->fields['control_kind']['arrayofkeyval'][$row->control_kind] : 'Unknown').'</td>';
	if (!empty($arrayfields['s.nom']['checked'])) print '<td>'.(!empty($provider->id) ? $provider->getNomUrl(1) : '').'</td>';
	if (!empty($arrayfields['t.result_code']['checked'])) print '<td>'.(isset($resultOptions[$row->result_code]) ? dol_escape_htmltag($resultOptions[$row->result_code]) : '').'</td>';
	if (!empty($arrayfields['t.retained_valid_until']['checked'])) print '<td>'.(!empty($row->retained_valid_until) ? dol_print_date($db->jdate($row->retained_valid_until), 'day') : '').'</td>';
	if (!empty($arrayfields['t.status']['checked'])) print '<td class="center">'.$object->getLibStatut(5).'</td>';
	if ($showEntityColumn && !empty($arrayfields['t.entity']['checked'])) print '<td class="center">'.lmdbVehicleManagementEntityBadge((int) $row->entity, $entityOptions).'</td>';
	if (!$conf->main_checkbox_left_column) print '<td class="center nowraponall actioncolumn"></td>'; print '</tr>'; $i++;
}
if ($i === 0) print '<tr class="oddeven"><td colspan="'.$visibleColumns.'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</table></div></form>'; $db->free($resql); llxFooter(); $db->close();
