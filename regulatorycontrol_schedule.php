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

/** @var Conf $conf */ /** @var DoliDB $db */ /** @var Translate $langs */ /** @var User $user */
$langs->loadLangs(array('main', 'companies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();
$limit = GETPOSTINT('limit') ?: (int) $conf->liste_limit; $page = GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page'); if ($page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) $page = 0; $offset = $limit * $page;
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'req.retained_due_date'; $sortorder = strtoupper(GETPOST('sortorder', 'alpha')) === 'DESC' ? 'DESC' : 'ASC';
$contextpage = GETPOST('contextpage', 'aZ09');
$allowedSorts = array('req.retained_due_date', 'v.ref', 'at.label', 'r.label', 'req.status', 's.nom', 'v.regulatory_territory', 'req.entity'); if (!in_array($sortfield, $allowedSorts, true)) $sortfield = 'req.retained_due_date';
$startDate = GETPOSTINT('date_startyear') > 0 ? dol_mktime(0, 0, 0, GETPOSTINT('date_startmonth'), GETPOSTINT('date_startday'), GETPOSTINT('date_startyear')) : 0;
$endDate = GETPOSTINT('date_endyear') > 0 ? dol_mktime(23, 59, 59, GETPOSTINT('date_endmonth'), GETPOSTINT('date_endday'), GETPOSTINT('date_endyear')) : 0;
$searchVehicle = GETPOSTINT('search_vehicle'); $searchAssetType = GETPOSTINT('search_asset_type'); $searchProfile = GETPOSTINT('search_profile'); $searchControlType = GETPOSTINT('search_control_type'); $searchStatus = GETPOST('search_status', 'aZ09'); $searchProvider = GETPOSTINT('search_provider'); $searchTerritory = GETPOST('search_territory', 'aZ09'); $searchEntities = GETPOSTISARRAY('search_entity') ? GETPOST('search_entity', 'array:int') : array();
if (GETPOST('button_removefilter', 'alpha')) { $startDate = $endDate = $searchVehicle = $searchAssetType = $searchProfile = $searchControlType = $searchProvider = 0; $searchStatus = $searchTerritory = ''; $searchEntities = array(); }
$form = new Form($db); $vehiclePrototype = new LmdbVehicle($db); $listObject = new LmdbVehicleRegulatoryControl($db);
$arrayfields = array(
	'req.retained_due_date' => array('label' => 'RetainedDueDate', 'checked' => 1, 'enabled' => 1, 'position' => 10),
	'v.ref' => array('label' => 'VehicleOrEquipment', 'checked' => 1, 'enabled' => 1, 'position' => 20),
	'at.label' => array('label' => 'AssetType', 'checked' => 1, 'enabled' => 1, 'position' => 30),
	'profile' => array('label' => 'RegulatoryProfile', 'checked' => 1, 'enabled' => 1, 'position' => 40),
	'r.label' => array('label' => 'Control', 'checked' => 1, 'enabled' => 1, 'position' => 50),
	'req.status' => array('label' => 'Status', 'checked' => 1, 'enabled' => 1, 'position' => 60),
	's.nom' => array('label' => 'ControlBody', 'checked' => 1, 'enabled' => 1, 'position' => 70),
	'v.regulatory_territory' => array('label' => 'RegulatoryTerritory', 'checked' => 1, 'enabled' => 1, 'position' => 80),
);
/** @var array<int,string> $vehicleOptions */ $vehicleOptions = array(); $resql = $db->query('SELECT rowid, ref, registration_number, label FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle WHERE entity IN ('.getEntity('lmdbvehicle').') ORDER BY ref'); if ($resql) { while (is_object($row = $db->fetch_object($resql))) $vehicleOptions[(int) $row->rowid] = (trim((string) $row->registration_number) !== '' ? (string) $row->registration_number : (string) $row->ref).' — '.(string) $row->label; $db->free($resql); }
/** @var array<int,string> $assetTypeOptions */ $assetTypeOptions = array(); $knownAssetTypeCodes = array(); $resql = $db->query('SELECT rowid, code, label FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_asset_type WHERE entity IN ('.getEntity('c_lmdbvehiclemanagement_asset_type').') AND active = 1 ORDER BY CASE WHEN entity = '.((int) $conf->entity).' THEN 0 ELSE 1 END, position'); if ($resql) { while (is_object($row = $db->fetch_object($resql))) { if (isset($knownAssetTypeCodes[$row->code])) continue; $knownAssetTypeCodes[$row->code] = true; $assetTypeOptions[(int) $row->rowid] = $langs->trans((string) $row->label); } $db->free($resql); }
/** @var array<int,string> $profileOptions */ $profileOptions = array(); $knownProfileCodes = array(); $resql = $db->query('SELECT rowid, code, label FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_profile WHERE entity IN ('.getEntity('c_lmdbvehiclemanagement_regulatory_profile').') AND active = 1 ORDER BY CASE WHEN entity = '.((int) $conf->entity).' THEN 0 ELSE 1 END, position'); if ($resql) { while (is_object($row = $db->fetch_object($resql))) { if (isset($knownProfileCodes[$row->code])) continue; $knownProfileCodes[$row->code] = true; $profileOptions[(int) $row->rowid] = $langs->trans((string) $row->label); } $db->free($resql); }
/** @var array<int,string> $controlTypeOptions */ $controlTypeOptions = array(); $knownControlTypeCodes = array(); $resql = $db->query('SELECT rowid, code, label FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_control_type WHERE entity IN ('.getEntity('c_lmdbvehiclemanagement_control_type').') AND active = 1 ORDER BY CASE WHEN entity = '.((int) $conf->entity).' THEN 0 ELSE 1 END, position'); if ($resql) { while (is_object($row = $db->fetch_object($resql))) { if (isset($knownControlTypeCodes[$row->code])) continue; $knownControlTypeCodes[$row->code] = true; $controlTypeOptions[(int) $row->rowid] = $langs->trans((string) $row->label); } $db->free($resql); }
$statusOptions = array('incomplete' => $langs->trans('RequirementStatusIncomplete'), 'up_to_date' => $langs->trans('RequirementStatusUpToDate'), 'due_soon' => $langs->trans('RequirementStatusDueSoon'), 'overdue' => $langs->trans('RequirementStatusOverdue'), 'recheck_required' => $langs->trans('RequirementStatusRecheckRequired'), 'non_compliant_blocking' => $langs->trans('RequirementStatusNonCompliantBlocking'), 'derogation_active' => $langs->trans('RequirementStatusDerogationActive'));
$territoryOptions = array(); foreach ($vehiclePrototype->fields['regulatory_territory']['arrayofkeyval'] as $key => $label) $territoryOptions[$key] = $langs->trans($label);
$entityScope = getEntity('lmdbvehicleregulatorycontrol'); $allowedEntityIds = array_values(array_filter(array_map('intval', explode(',', $entityScope)))); $entityOptions = lmdbVehicleManagementGetEntityOptions('lmdbvehicleregulatorycontrol'); $showEntityColumn = !empty($entityOptions); if ($showEntityColumn) $arrayfields['req.entity'] = array('label' => 'Environment', 'checked' => 1, 'enabled' => 1, 'position' => 90); else $searchEntities = array();
$hookmanager->initHooks(array('lmdbvehicleregulatorycontrolschedule'));
$action = GETPOST('action', 'aZ09');
$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $listObject, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';
$where = ' WHERE req.active = 1 AND req.entity IN ('.$entityScope.')';
if ($startDate > 0) $where .= " AND req.retained_due_date >= '".$db->idate($startDate)."'";
if ($endDate > 0) $where .= " AND req.retained_due_date <= '".$db->idate($endDate)."'";
if ($searchVehicle > 0) $where .= ' AND req.fk_vehicle = '.$searchVehicle;
if ($searchAssetType > 0) $where .= ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_asset_type AS selected_at INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_asset_type AS vehicle_at ON vehicle_at.rowid = v.fk_asset_type AND vehicle_at.code = selected_at.code WHERE selected_at.rowid = '.$searchAssetType.')';
if ($searchProfile > 0) $where .= ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile AS vp INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_profile AS vehicle_profile ON vehicle_profile.rowid = vp.fk_profile INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_profile AS selected_profile ON selected_profile.rowid = '.$searchProfile.' AND selected_profile.code = vehicle_profile.code WHERE vp.entity = req.entity AND vp.fk_vehicle = req.fk_vehicle AND vp.confirmed = 1)';
if ($searchControlType > 0) $where .= ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_control_type AS selected_ct INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_control_type AS rule_ct ON rule_ct.rowid = r.fk_control_type AND rule_ct.code = selected_ct.code WHERE selected_ct.rowid = '.$searchControlType.')';
if ($searchStatus !== '') $where .= " AND req.status = '".$db->escape($searchStatus)."'";
if ($searchProvider > 0) $where .= ' AND c.fk_soc_provider = '.$searchProvider;
if ($searchTerritory !== '') $where .= " AND v.regulatory_territory = '".$db->escape($searchTerritory)."'";
if ($showEntityColumn && !empty($searchEntities)) { $filtered = array_values(array_intersect($allowedEntityIds, array_map('intval', $searchEntities))); if (!empty($filtered)) $where .= ' AND req.entity IN ('.implode(',', $filtered).')'; }
$from = ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement AS req INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid = req.fk_vehicle AND v.entity = req.entity LEFT JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_asset_type AS at ON at.rowid = v.fk_asset_type INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule AS r ON r.rowid = req.fk_rule AND r.entity = req.entity INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_control_type AS ct ON ct.rowid = r.fk_control_type AND ct.entity = r.entity LEFT JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_control AS c ON c.rowid = req.fk_last_control AND c.entity = req.entity LEFT JOIN '.MAIN_DB_PREFIX.'societe AS s ON s.rowid = c.fk_soc_provider';
$resql = $db->query('SELECT COUNT(*) AS total'.$from.$where); if (!$resql) { dol_print_error($db); exit; } $row = $db->fetch_object($resql); $total = is_object($row) ? (int) $row->total : 0; $db->free($resql);
$sql = 'SELECT req.*, v.ref AS vehicle_ref, v.registration_number, v.label AS vehicle_label, v.regulatory_territory, at.label AS asset_type_label, r.label AS rule_label, ct.label AS control_type_label, c.ref AS control_ref, c.fk_soc_provider, s.nom AS provider_name,';
$sql .= ' (SELECT GROUP_CONCAT(p.label ORDER BY p.position SEPARATOR \'||\') FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile AS vp INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_profile AS p ON p.rowid = vp.fk_profile WHERE vp.entity = req.entity AND vp.fk_vehicle = req.fk_vehicle AND vp.confirmed = 1) AS profile_labels';
$sql .= $from.$where.$db->order($sortfield, $sortorder).$db->plimit($limit + 1, $offset); $resql = $db->query($sql); if (!$resql) { dol_print_error($db); exit; } $num = $db->num_rows($resql);

$title = $langs->trans('RegulatoryControlSchedule'); llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-list bodyforlist');
$param = ''; if ($startDate) $param .= '&date_startday='.dol_print_date($startDate, '%d').'&date_startmonth='.dol_print_date($startDate, '%m').'&date_startyear='.dol_print_date($startDate, '%Y'); if ($endDate) $param .= '&date_endday='.dol_print_date($endDate, '%d').'&date_endmonth='.dol_print_date($endDate, '%m').'&date_endyear='.dol_print_date($endDate, '%Y');
foreach (array('search_vehicle' => $searchVehicle, 'search_asset_type' => $searchAssetType, 'search_profile' => $searchProfile, 'search_control_type' => $searchControlType, 'search_status' => $searchStatus, 'search_provider' => $searchProvider, 'search_territory' => $searchTerritory) as $name => $value) if ($value !== '' && $value !== 0) $param .= '&'.$name.'='.urlencode((string) $value); foreach ($searchEntities as $entityId) $param .= '&search_entity[]='.((int) $entityId);
print '<form method="POST" id="searchFormList" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="formfilteraction" id="formfilteraction" value="list"><input type="hidden" name="action" value="list"><input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'"><input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'"><input type="hidden" name="page" value="'.((int) $page).'">';
$newButton = dolGetButtonTitle($langs->trans('NewRegulatoryControl'), '', 'fa fa-plus-circle', dol_buildpath('/lmdbvehiclemanagement/regulatorycontrol_card.php', 1).'?action=create', '', $user->hasRight('lmdbvehiclemanagement', 'regulatorycontrol', 'write')); print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $total, 'calendar-check', 0, $newButton, '', $limit, 0, 0, 1);
$varpage = empty($contextpage) ? $_SERVER['PHP_SELF'] : $contextpage;
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, $conf->main_checkbox_left_column);
print '<div class="div-table-responsive"><table class="tagtable nobottomiftotal noborder liste"><tr class="liste_titre_filter">';
if ($conf->main_checkbox_left_column) print '<td class="liste_titre center maxwidthsearch actioncolumn">'.$form->showFilterButtons('left').'</td>';
if (!empty($arrayfields['req.retained_due_date']['checked'])) print '<td>'.$form->selectDate($startDate ?: -1, 'date_start', 0, 0, 1, '', 1, 1).' '.$form->selectDate($endDate ?: -1, 'date_end', 0, 0, 1, '', 1, 1).'</td>';
if (!empty($arrayfields['v.ref']['checked'])) print '<td>'.$form->selectarray('search_vehicle', $vehicleOptions, $searchVehicle, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth200', 1).'</td>';
if (!empty($arrayfields['at.label']['checked'])) print '<td>'.$form->selectarray('search_asset_type', $assetTypeOptions, $searchAssetType, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth150', 1).'</td>';
if (!empty($arrayfields['profile']['checked'])) print '<td>'.$form->selectarray('search_profile', $profileOptions, $searchProfile, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth150', 1).'</td>';
if (!empty($arrayfields['r.label']['checked'])) print '<td>'.$form->selectarray('search_control_type', $controlTypeOptions, $searchControlType, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth150', 1).'</td>';
if (!empty($arrayfields['req.status']['checked'])) print '<td class="center">'.$form->selectarray('search_status', $statusOptions, $searchStatus, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth150', 1).'</td>';
if (!empty($arrayfields['s.nom']['checked'])) print '<td>'.$form->select_company($searchProvider ?: '', 'search_provider', '', '-1', 0, 0, array(), 0, 'maxwidth150').'</td>';
if (!empty($arrayfields['v.regulatory_territory']['checked'])) print '<td>'.$form->selectarray('search_territory', $territoryOptions, $searchTerritory, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth150', 1).'</td>';
if ($showEntityColumn && !empty($arrayfields['req.entity']['checked'])) print '<td>'.$form->multiselectarray('search_entity', $entityOptions, $searchEntities, 0, 0, 'maxwidth150', 1).'</td>';
if (!$conf->main_checkbox_left_column) print '<td class="liste_titre center maxwidthsearch actioncolumn">'.$form->showFilterButtons().'</td>';
print '</tr><tr class="liste_titre">';
if ($conf->main_checkbox_left_column) print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
foreach ($arrayfields as $field => $definition) if (!empty($definition['checked'])) print getTitleFieldOfList($definition['label'], 0, $_SERVER['PHP_SELF'], $field === 'profile' ? '' : $field, '', $param, in_array($field, array('req.status', 'req.entity'), true) ? 'class="center"' : '', $sortfield, $sortorder);
if (!$conf->main_checkbox_left_column) print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
print '</tr>';
$i = 0; while ($i < min($num, $limit) && is_object($row = $db->fetch_object($resql))) { $vehicle = new LmdbVehicle($db); $vehicle->id = (int) $row->fk_vehicle; $vehicle->ref = (string) $row->vehicle_ref; $vehicle->registration_number = (string) $row->registration_number; $vehicle->label = (string) $row->vehicle_label; $provider = new Societe($db); $provider->id = (int) $row->fk_soc_provider; $provider->name = (string) $row->provider_name; $statusTypes = array('incomplete' => 'status3', 'up_to_date' => 'status4', 'due_soon' => 'status1', 'overdue' => 'status8', 'recheck_required' => 'status6', 'non_compliant_blocking' => 'status8', 'derogation_active' => 'status1');
	$profileLabels = array(); foreach (array_filter(explode('||', (string) $row->profile_labels)) as $profileLabel) $profileLabels[] = $langs->trans($profileLabel);
	$recordUrl = dol_buildpath('/lmdbvehiclemanagement/regulatorycontrol_card.php', 1).'?action=create&vehicle_id='.((int) $row->fk_vehicle).'&requirement_id='.((int) $row->rowid);
	$recordButton = '<a class="button small" href="'.$recordUrl.'">'.$langs->trans('RecordControl').'</a>';
	print '<tr class="oddeven">'; if ($conf->main_checkbox_left_column) print '<td class="center nowraponall actioncolumn">'.$recordButton.'</td>';
	if (!empty($arrayfields['req.retained_due_date']['checked'])) print '<td>'.(!empty($row->retained_due_date) ? dol_print_date($db->jdate($row->retained_due_date), 'day') : '<span class="opacitymedium">'.$langs->trans('NotCalculated').'</span>').'</td>';
	if (!empty($arrayfields['v.ref']['checked'])) print '<td>'.$vehicle->getNomUrl(1).'</td>';
	if (!empty($arrayfields['at.label']['checked'])) print '<td>'.dol_escape_htmltag(!empty($row->asset_type_label) ? $langs->trans((string) $row->asset_type_label) : '').'</td>';
	if (!empty($arrayfields['profile']['checked'])) print '<td>'.dol_escape_htmltag(implode(', ', $profileLabels)).'</td>';
	if (!empty($arrayfields['r.label']['checked'])) print '<td>'.dol_escape_htmltag($langs->trans((string) $row->rule_label)).'</td>';
	if (!empty($arrayfields['req.status']['checked'])) print '<td class="center">'.dolGetStatus(isset($statusOptions[$row->status]) ? $statusOptions[$row->status] : $langs->trans('Unknown'), '', '', $statusTypes[$row->status] ?? 'status0', 5).'</td>';
	if (!empty($arrayfields['s.nom']['checked'])) print '<td>'.(!empty($provider->id) ? $provider->getNomUrl(1) : '').'</td>';
	if (!empty($arrayfields['v.regulatory_territory']['checked'])) print '<td>'.dol_escape_htmltag($territoryOptions[$row->regulatory_territory] ?? (string) $row->regulatory_territory).'</td>';
	if ($showEntityColumn && !empty($arrayfields['req.entity']['checked'])) print '<td class="center">'.lmdbVehicleManagementEntityBadge((int) $row->entity, $entityOptions).'</td>';
	if (!$conf->main_checkbox_left_column) print '<td class="center nowraponall actioncolumn">'.$recordButton.'</td>'; print '</tr>'; $i++; }
$visibleColumns = 1; foreach ($arrayfields as $definition) if (!empty($definition['checked'])) $visibleColumns++;
if ($i === 0) print '<tr class="oddeven"><td colspan="'.$visibleColumns.'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>'; print '</table></div></form>'; $db->free($resql); llxFooter(); $db->close();
