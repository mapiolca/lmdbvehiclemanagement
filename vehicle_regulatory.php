<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleregulatorycontrol.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */
$langs->loadLangs(array('main', 'companies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$vehicle = new LmdbVehicle($db);
if ($id <= 0 || $vehicle->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));
$permissionWrite = $user->hasRight('lmdbvehiclemanagement', 'lmdbvehicle', 'write');
$permissionDerogation = $user->hasRight('lmdbvehiclemanagement', 'regulatorycontrol', 'derogation');

if ($action === 'save_profiles') {
	if (!$permissionWrite) accessforbidden();
	$profileIds = GETPOST('profile_ids', 'array:int');
	$result = $vehicle->saveRegulatoryProfiles(is_array($profileIds) ? array_map('intval', $profileIds) : array(), $user);
	if ($result > 0) setEventMessages($langs->trans('RegulatoryQualificationSaved'), null, 'mesgs');
	else lmdbVehicleManagementSetObjectErrors($vehicle);
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
	exit;
}
if ($action === 'grant_derogation') {
	if (!$permissionDerogation) accessforbidden();
	$until = dol_mktime(23, 59, 59, GETPOSTINT('derogation_untilmonth'), GETPOSTINT('derogation_untilday'), GETPOSTINT('derogation_untilyear'));
	$result = $vehicle->grantRegulatoryDerogation(GETPOSTINT('requirement_id'), $until, GETPOST('derogation_reason', 'restricthtml'), $user);
	if ($result > 0) setEventMessages($langs->trans('RegulatoryDerogationGranted'), null, 'mesgs');
	else lmdbVehicleManagementSetObjectErrors($vehicle);
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
	exit;
}

$form = new Form($db);
$profileOptions = array();
$profileMeta = array();
$sql = 'SELECT p.rowid, p.code, p.label, p.description, vp.confirmed, vp.origin FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_profile AS p';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_regulatory_profile AS vp ON vp.entity = '.((int) $vehicle->entity).' AND vp.fk_vehicle = '.((int) $vehicle->id).' AND vp.fk_profile = p.rowid';
$sql .= ' WHERE p.entity IN ('.getEntity('c_lmdbvehiclemanagement_regulatory_profile').') AND p.active = 1';
$sql .= ' ORDER BY (vp.fk_profile IS NULL), (p.entity <> '.((int) $vehicle->entity).'), p.position, p.label';
$resql = $db->query($sql);
if ($resql) {
	$loadedProfileCodes = array();
	while (is_object($row = $db->fetch_object($resql))) {
		if (isset($loadedProfileCodes[(string) $row->code])) continue;
		$loadedProfileCodes[(string) $row->code] = true;
		$profileOptions[(int) $row->rowid] = $langs->trans((string) $row->label);
		$profileMeta[(int) $row->rowid] = array('confirmed' => (int) $row->confirmed, 'origin' => (string) $row->origin, 'description' => $langs->trans((string) $row->description));
	}
	$db->free($resql);
}
$selectedProfiles = $vehicle->fetchRegulatoryProfileIds();

$requirements = array();
$sql = 'SELECT req.*, r.label AS rule_label, r.source_title, r.source_url, ct.label AS control_type_label, c.ref AS control_ref';
$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement AS req';
$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule AS r ON r.rowid = req.fk_rule AND r.entity = req.entity';
$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_control_type AS ct ON ct.rowid = r.fk_control_type AND ct.entity = r.entity';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_control AS c ON c.rowid = req.fk_last_control AND c.entity = req.entity';
$sql .= ' WHERE req.entity = '.((int) $vehicle->entity).' AND req.fk_vehicle = '.((int) $vehicle->id).' AND req.active = 1 ORDER BY req.retained_due_date IS NULL, req.retained_due_date, r.label';
$resql = $db->query($sql);
if ($resql) { while (is_object($row = $db->fetch_object($resql))) $requirements[] = $row; $db->free($resql); }

llxHeader('', $vehicle->ref.' - '.$langs->trans('RegulatoryControls'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');
$head = lmdbVehiclePrepareHead($vehicle);
print dol_get_fiche_head($head, 'regulatory', $langs->trans('VehicleOrEquipment'), -1, $vehicle->picto);
lmdbVehiclePrintBanner($vehicle);

print load_fiche_titre($langs->trans('RegulatoryQualification'), '', 'tags');
if ($permissionWrite) {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="lmdb-responsive-form">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save_profiles"><input type="hidden" name="id" value="'.$id.'">';
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('RegulatoryProfiles').'</th><th>'.$langs->trans('QualificationOrigin').'</th><th>'.$langs->trans('Status').'</th></tr><tr class="oddeven">';
	print '<td>'.$form->multiselectarray('profile_ids', $profileOptions, $selectedProfiles, 0, 0, 'minwidth500').'</td>';
	$origins = array(); $unconfirmed = false;
	foreach ($selectedProfiles as $profileId) { if (!empty($profileMeta[$profileId]['origin'])) $origins[] = $langs->trans($profileMeta[$profileId]['origin'] === 'deduced' ? 'ProfileOriginDeduced' : 'ProfileOriginManual'); if (empty($profileMeta[$profileId]['confirmed'])) $unconfirmed = true; }
	print '<td>'.dol_escape_htmltag(implode(', ', array_unique($origins))).'</td><td class="center">'.($unconfirmed ? dolGetStatus($langs->trans('QualificationToConfirm'), '', '', 'status3', 5) : dolGetStatus($langs->trans('QualificationConfirmed'), '', '', 'status4', 5)).'</td></tr></table></div>';
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('ConfirmRegulatoryProfiles').'"></div></form>';
} else {
	$labels = array(); foreach ($selectedProfiles as $profileId) if (isset($profileOptions[$profileId])) $labels[] = $profileOptions[$profileId];
	print '<div class="info">'.dol_escape_htmltag(!empty($labels) ? implode(', ', $labels) : $langs->trans('NoRegulatoryProfileSelected')).'</div>';
}

print '<div class="tabsAction">';
if ($user->hasRight('lmdbvehiclemanagement', 'regulatorycontrol', 'write')) print dolGetButtonAction('', $langs->trans('NewRegulatoryControl'), 'default', dol_buildpath('/lmdbvehiclemanagement/regulatorycontrol_card.php', 1).'?action=create&vehicle_id='.$id);
print '</div>';
print load_fiche_titre($langs->trans('RegulatoryRequirements'), '', 'clipboard-check');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Control').'</th><th>'.$langs->trans('RequirementKind').'</th><th>'.$langs->trans('Qualification').'</th><th>'.$langs->trans('LastControl').'</th><th>'.$langs->trans('CalculatedDueDate').'</th><th>'.$langs->trans('RetainedDueDate').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('Derogation').'</th><th></th></tr>';
foreach ($requirements as $requirement) {
	$statusLabels = array('incomplete' => 'RequirementStatusIncomplete', 'up_to_date' => 'RequirementStatusUpToDate', 'due_soon' => 'RequirementStatusDueSoon', 'overdue' => 'RequirementStatusOverdue', 'recheck_required' => 'RequirementStatusRecheckRequired', 'non_compliant_blocking' => 'RequirementStatusNonCompliantBlocking', 'derogation_active' => 'RequirementStatusDerogationActive');
	$statusTypes = array('incomplete' => 'status3', 'up_to_date' => 'status4', 'due_soon' => 'status1', 'overdue' => 'status6', 'recheck_required' => 'status6', 'non_compliant_blocking' => 'status8', 'derogation_active' => 'status1');
	$status = (string) $requirement->status;
	print '<tr class="oddeven"><td>'.(!empty($requirement->source_url) ? '<a href="'.dol_escape_htmltag((string) $requirement->source_url).'" target="_blank" rel="noopener">' : '').dol_escape_htmltag($langs->trans((string) $requirement->rule_label)).(!empty($requirement->source_url) ? '</a>' : '').'</td>';
	$requirementKindKey = (string) $requirement->requirement_kind === 'recheck' ? 'ControlKindRecheck' : 'ControlKindPeriodic';
	print '<td>'.$langs->trans($requirementKindKey).'</td>';
	print '<td>'.$langs->trans((string) $requirement->qualification_status === 'complete' ? 'QualificationComplete' : 'QualificationIncomplete').'</td>';
	print '<td>'; if (!empty($requirement->fk_last_control)) { $control = new LmdbVehicleRegulatoryControl($db); $control->id = (int) $requirement->fk_last_control; $control->ref = (string) $requirement->control_ref; print $control->getNomUrl(1); } print '</td>';
	print '<td>'.(!empty($requirement->calculated_due_date) ? dol_print_date($db->jdate($requirement->calculated_due_date), 'day') : '').'</td>';
	print '<td>'.(!empty($requirement->retained_due_date) ? dol_print_date($db->jdate($requirement->retained_due_date), 'day') : '').'</td>';
	print '<td class="center">'.dolGetStatus($langs->trans(isset($statusLabels[$status]) ? $statusLabels[$status] : 'Unknown'), '', '', isset($statusTypes[$status]) ? $statusTypes[$status] : 'status0', 5).'</td>';
	print '<td>'.(!empty($requirement->derogation_until) ? dol_print_date($db->jdate($requirement->derogation_until), 'dayhour').' — '.dol_escape_htmltag((string) $requirement->derogation_reason) : '');
	if ($permissionDerogation && in_array($status, array('overdue', 'recheck_required', 'non_compliant_blocking'), true)) {
		print '<details><summary>'.$langs->trans('GrantDerogation').'</summary><form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="lmdb-responsive-form"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="grant_derogation"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="requirement_id" value="'.((int) $requirement->rowid).'">'.$form->selectDate(-1, 'derogation_until', 0, 0, 1, '', 1, 1).' <input class="flat minwidth200" name="derogation_reason" placeholder="'.$langs->trans('DerogationReason').'"> <button class="button small" type="submit">'.$langs->trans('Grant').'</button></form></details>';
	}
	print '</td><td class="right"><a class="button small" href="'.dol_buildpath('/lmdbvehiclemanagement/regulatorycontrol_card.php', 1).'?action=create&requirement_id='.((int) $requirement->rowid).'&vehicle_id='.$id.'">'.$langs->trans('RecordControl').'</a></td></tr>';
}
if (empty($requirements)) print '<tr class="oddeven"><td colspan="9"><span class="opacitymedium">'.$langs->trans('NoRegulatoryRequirementUntilQualification').'</span></td></tr>';
print '</table></div>'.dol_get_fiche_end();
llxFooter();
$db->close();
