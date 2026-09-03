<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res) die('Include of main fails');
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php'; dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php'); dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleregulatorycatalog.class.php'); dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleregulatoryservice.class.php');
/** @var Conf $conf */ /** @var DoliDB $db */ /** @var Translate $langs */ /** @var User $user */
$langs->loadLangs(array('admin', 'users', 'mails', 'lmdbvehiclemanagement@lmdbvehiclemanagement')); if (empty($user->admin)) accessforbidden(); $action = GETPOST('action', 'aZ09');
$overrideEffectiveFrom = GETPOSTINT('override_effective_fromyear') > 0 ? dol_mktime(12, 0, 0, GETPOSTINT('override_effective_frommonth'), GETPOSTINT('override_effective_fromday'), GETPOSTINT('override_effective_fromyear')) : null;
$overrideEffectiveTo = GETPOSTINT('override_effective_toyear') > 0 ? dol_mktime(12, 0, 0, GETPOSTINT('override_effective_tomonth'), GETPOSTINT('override_effective_today'), GETPOSTINT('override_effective_toyear')) : null;
$customEffectiveFrom = GETPOSTINT('custom_effective_fromyear') > 0 ? dol_mktime(12, 0, 0, GETPOSTINT('custom_effective_frommonth'), GETPOSTINT('custom_effective_fromday'), GETPOSTINT('custom_effective_fromyear')) : null;
$customEffectiveTo = GETPOSTINT('custom_effective_toyear') > 0 ? dol_mktime(12, 0, 0, GETPOSTINT('custom_effective_tomonth'), GETPOSTINT('custom_effective_today'), GETPOSTINT('custom_effective_toyear')) : null;
$selectedCustomProfiles = array();
if (GETPOSTISARRAY('custom_profile_ids')) {
	$selectedCustomProfiles = array_values(array_unique(array_filter(array_map('intval', GETPOST('custom_profile_ids', 'array:int')), static function ($profileId) {
		return $profileId > 0;
	})));
} else {
	$submittedProfileIds = trim(GETPOST('custom_profile_ids', 'alphanohtml'));
	if ($submittedProfileIds !== '') {
		$selectedCustomProfiles = array_values(array_unique(array_filter(array_map('intval', explode(',', $submittedProfileIds)), static function ($profileId) {
			return $profileId > 0;
		})));
	}
}
if ($action === 'create_override') {
	$numericValues = array();
	foreach (array('initial_delay_months', 'recurrence_months', 'recurrence_days', 'recheck_days') as $field) {
		$input = trim(GETPOST('override_'.$field, 'alphanohtml'));
		$numericValues[$field] = $input === '' ? null : max(0, (int) $input);
	}
	$values = array(
		'label' => GETPOST('override_label', 'alphanohtml'),
		'override_reason' => GETPOST('override_reason', 'restricthtml'),
		'initial_delay_months' => $numericValues['initial_delay_months'],
		'recurrence_months' => $numericValues['recurrence_months'],
		'recurrence_days' => $numericValues['recurrence_days'],
		'recheck_days' => $numericValues['recheck_days'],
		'default_blocking_mode' => GETPOST('override_blocking_mode', 'aZ09'),
		'effective_from' => $overrideEffectiveFrom,
		'effective_to' => $overrideEffectiveTo,
	);
	$catalog = new LmdbVehicleRegulatoryCatalog($db);
	$result = $catalog->createEntityOverride(GETPOSTINT('parent_rule_id'), $values, $user, (int) $conf->entity);
	if ($result > 0) {
		setEventMessages($langs->trans('RegulatoryRuleOverrideCreated'), null, 'mesgs');
		$service = new LmdbVehicleRegulatoryService($db);
		if ($service->synchronizeEntityRequirements((int) $conf->entity, $user) < 0) {
			setEventMessages($langs->trans('RegulatoryRequirementsSynchronizationDeferred'), null, 'warnings');
		}
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
	setEventMessages($langs->trans($catalog->error !== '' ? $catalog->error : 'Error'), null, 'errors');
}
if ($action === 'create_custom_rule') {
	$customNumericValues = array();
	foreach (array('initial_delay_months', 'recurrence_months', 'recurrence_days', 'recheck_days') as $field) {
		$input = trim(GETPOST('custom_'.$field, 'alphanohtml'));
		$customNumericValues[$field] = $input === '' ? null : max(0, (int) $input);
	}
	$values = array(
		'label' => GETPOST('custom_label', 'alphanohtml'),
		'description' => GETPOST('custom_description', 'restricthtml'),
		'reason' => GETPOST('custom_reason', 'restricthtml'),
		'profile_ids' => $selectedCustomProfiles,
		'calculator_code' => GETPOST('custom_calculator_code', 'aZ09'),
		'initial_delay_months' => $customNumericValues['initial_delay_months'],
		'recurrence_months' => $customNumericValues['recurrence_months'],
		'recurrence_days' => $customNumericValues['recurrence_days'],
		'recheck_days' => $customNumericValues['recheck_days'],
		'default_blocking_mode' => GETPOST('custom_blocking_mode', 'aZ09'),
		'effective_from' => $customEffectiveFrom,
		'effective_to' => $customEffectiveTo,
	);
	$catalog = new LmdbVehicleRegulatoryCatalog($db);
	$result = $catalog->createEntityCustomRule($values, $user, (int) $conf->entity);
	if ($result > 0) {
		setEventMessages($langs->trans('RegulatoryCustomRuleCreated'), null, 'mesgs');
		$service = new LmdbVehicleRegulatoryService($db);
		if ($service->synchronizeEntityRequirements((int) $conf->entity, $user) < 0) setEventMessages($langs->trans('RegulatoryRequirementsSynchronizationDeferred'), null, 'warnings');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
	setEventMessages($langs->trans($catalog->error !== '' ? $catalog->error : 'Error'), null, 'errors');
}
if ($action === 'save') {
	$allowedHorizons = array(90, 60, 45, 30, 15, 7, 1, 0);
	$submittedHorizons = GETPOST('reminder_horizons', 'array:int');
	$horizons = is_array($submittedHorizons) ? array_values(array_unique(array_intersect($allowedHorizons, array_map('intval', $submittedHorizons)))) : array();
	$userIds = GETPOSTISARRAY('recipient_users') ? array_values(array_unique(array_map('intval', GETPOST('recipient_users', 'array:int')))) : array();
	$groupIds = GETPOSTISARRAY('recipient_groups') ? array_values(array_unique(array_map('intval', GETPOST('recipient_groups', 'array:int')))) : array();
	$values = array('LMDBVEHICLEMANAGEMENT_REGULATORY_REMINDER_HORIZONS' => json_encode($horizons), 'LMDBVEHICLEMANAGEMENT_REGULATORY_RECIPIENT_USERS' => json_encode($userIds), 'LMDBVEHICLEMANAGEMENT_REGULATORY_RECIPIENT_GROUPS' => json_encode($groupIds), 'LMDBVEHICLEMANAGEMENT_REGULATORY_REMINDER_TEMPLATE' => (string) GETPOSTINT('reminder_template'), 'LMDBVEHICLEMANAGEMENT_CONTROL_DUE_SOON_DAYS' => (string) max(0, GETPOSTINT('due_soon_days')));
	$error = 0; foreach ($values as $name => $value) if (dolibarr_set_const($db, $name, $value, is_numeric($value) ? 'integer' : 'chaine', 0, '', (int) $conf->entity) <= 0) $error++;
	if ($error) setEventMessages($db->lasterror(), null, 'errors'); else setEventMessages($langs->trans('SettingsSaved'), null, 'mesgs'); header('Location: '.$_SERVER['PHP_SELF']); exit;
}
$form = new Form($db); $userOptions = array(); $resql = $db->query('SELECT rowid, firstname, lastname, login, email FROM '.MAIN_DB_PREFIX.'user WHERE entity IN ('.getEntity('user').') AND statut = 1 AND email IS NOT NULL AND email <> \'\' ORDER BY lastname, firstname, login'); if ($resql) { while (is_object($row = $db->fetch_object($resql))) { $email = trim((string) $row->email); if (!isValidEmail($email)) continue; $name = trim((string) $row->firstname.' '.(string) $row->lastname); $userOptions[(int) $row->rowid] = ($name !== '' ? $name : (string) $row->login).' — '.$email; } $db->free($resql); }
$groupOptions = array(); $resql = $db->query('SELECT rowid, nom FROM '.MAIN_DB_PREFIX.'usergroup WHERE entity IN ('.getEntity('usergroup').') ORDER BY nom'); if ($resql) { while (is_object($row = $db->fetch_object($resql))) $groupOptions[(int) $row->rowid] = (string) $row->nom; $db->free($resql); }
$templateOptions = array(); $resql = $db->query("SELECT rowid, label FROM ".MAIN_DB_PREFIX."c_email_templates WHERE type_template = 'lmdbvehicle_regulatory_reminder' AND active = 1 AND entity IN (0,".((int) $conf->entity).') ORDER BY position, label'); if ($resql) { while (is_object($row = $db->fetch_object($resql))) $templateOptions[(int) $row->rowid] = (string) $row->label; $db->free($resql); }
$nativeRuleOptions = array(); $ruleRows = array(); $resql = $db->query('SELECT r.rowid, r.code, r.label, r.is_native, r.active, r.fk_parent_rule, r.initial_delay_months, r.recurrence_months, r.recurrence_days, r.recheck_days, r.default_blocking_mode, r.effective_from, r.effective_to, r.override_reason, r.source_title, r.source_url, r.source_review_date, r.territory, r.obligation_group, r.applicability_code, r.applicability_priority, parent.label AS parent_label FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule AS r LEFT JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule AS parent ON parent.rowid = r.fk_parent_rule AND parent.entity = r.entity WHERE r.entity = '.((int) $conf->entity).' ORDER BY r.is_native DESC, r.code, r.date_creation DESC'); if ($resql) { while (is_object($row = $db->fetch_object($resql))) { $ruleRows[] = $row; if (!empty($row->is_native) && !empty($row->active)) $nativeRuleOptions[(int) $row->rowid] = $langs->trans((string) $row->label).' ('.(string) $row->code.')'; } $db->free($resql); }
$regulatoryProfileOptions = array(); $resql = $db->query('SELECT rowid, label FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_regulatory_profile WHERE entity = '.((int) $conf->entity).' AND active = 1 ORDER BY position, label'); if ($resql) { while (is_object($row = $db->fetch_object($resql))) $regulatoryProfileOptions[(int) $row->rowid] = $langs->trans((string) $row->label); $db->free($resql); }
$selectedUsers = json_decode(getDolGlobalString('LMDBVEHICLEMANAGEMENT_REGULATORY_RECIPIENT_USERS', '[]'), true); if (!is_array($selectedUsers)) $selectedUsers = array(); $selectedGroups = json_decode(getDolGlobalString('LMDBVEHICLEMANAGEMENT_REGULATORY_RECIPIENT_GROUPS', '[]'), true); if (!is_array($selectedGroups)) $selectedGroups = array(); $horizons = json_decode(getDolGlobalString('LMDBVEHICLEMANAGEMENT_REGULATORY_REMINDER_HORIZONS', '[90,60,30,7,0]'), true); if (!is_array($horizons)) $horizons = array(90, 60, 30, 7, 0);
$title = $langs->trans('RegulatoryControlsSetup'); $linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=lmdbvehiclemanagement">'.img_picto('', 'back', 'class="pictofixedwidth"').$langs->trans('BackToModuleList').'</a>'; llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-admin'); print load_fiche_titre($title, $linkback, 'clipboard-check'); $head = lmdbVehicleManagementAdminPrepareHead(); print dol_get_fiche_head($head, 'regulatory', $title, -1, 'car');
if (!isModEnabled('cron')) print '<div class="warning">'.$langs->trans('RequiresCronModule').'</div>';
if (!isModEnabled('agenda')) print '<div class="warning">'.$langs->trans('RequiresAgendaModule').'</div>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save"><div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre"><th colspan="2">'.$langs->trans('RegulatoryReminderSettings').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('EnableRegulatoryReminders').'</td><td>'.ajax_constantonoff('LMDBVEHICLEMANAGEMENT_REGULATORY_REMINDERS_ENABLED').'</td></tr><tr class="oddeven"><td>'.$langs->trans('IncludeAssignedDriver').'</td><td>'.ajax_constantonoff('LMDBVEHICLEMANAGEMENT_REGULATORY_INCLUDE_DRIVER').'</td></tr>';
$reminderHorizonOptions = array(90 => 'J-90', 60 => 'J-60', 45 => 'J-45', 30 => 'J-30', 15 => 'J-15', 7 => 'J-7', 1 => 'J-1', 0 => 'J');
print '<tr class="oddeven"><td>'.$langs->trans('ReminderHorizonsDays').'</td><td>'.$form->multiselectarray('reminder_horizons', $reminderHorizonOptions, array_map('intval', $horizons), 0, 0, 'minwidth300').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('EnableDailyOverdueRegulatoryReminders').'</td><td>'.ajax_constantonoff('LMDBVEHICLEMANAGEMENT_REGULATORY_DAILY_OVERDUE_REMINDERS').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DueSoonDays').'</td><td><input class="flat width75" inputmode="numeric" name="due_soon_days" value="'.getDolGlobalInt('LMDBVEHICLEMANAGEMENT_CONTROL_DUE_SOON_DAYS', 90).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RecipientUsers').'</td><td>'.$form->multiselectarray('recipient_users', $userOptions, array_map('intval', $selectedUsers), 0, 0, 'minwidth500').'</td></tr><tr class="oddeven"><td>'.$langs->trans('RecipientGroups').'</td><td>'.$form->multiselectarray('recipient_groups', $groupOptions, array_map('intval', $selectedGroups), 0, 0, 'minwidth500').'</td></tr><tr class="oddeven"><td>'.$langs->trans('EmailTemplate').'</td><td>'.$form->selectarray('reminder_template', $templateOptions, getDolGlobalInt('LMDBVEHICLEMANAGEMENT_REGULATORY_REMINDER_TEMPLATE'), 1, 0, 0, '', 1, 0, 0, '', 'minwidth500', 1).'</td></tr></table></div><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div></form>';
print load_fiche_titre($langs->trans('RegulatoryRuleCatalog'), '', 'book-open');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('RegulatoryRuleCode').'</th><th>'.$langs->trans('Label').'</th><th>'.$langs->trans('RuleOrigin').'</th><th>'.$langs->trans('RegulatoryRuleGroup').'</th><th>'.$langs->trans('RegulatoryApplicabilityCode').'</th><th class="center">'.$langs->trans('RegulatoryRulePriority').'</th><th>'.$langs->trans('RulePeriodicity').'</th><th>'.$langs->trans('RegulatoryRuleSource').'</th><th>'.$langs->trans('RuleBlockingMode').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('OverrideReason').'</th></tr>';
foreach ($ruleRows as $ruleRow) {
	$periodParts = array();
	if ($ruleRow->initial_delay_months !== null) $periodParts[] = $langs->trans('InitialDelayMonthsShort', (int) $ruleRow->initial_delay_months);
	if ($ruleRow->recurrence_months !== null) $periodParts[] = $langs->trans('RecurrenceMonthsShort', (int) $ruleRow->recurrence_months);
	if ($ruleRow->recurrence_days !== null) $periodParts[] = $langs->trans('RecurrenceDaysShort', (int) $ruleRow->recurrence_days);
	$ruleLabel = $langs->trans((string) $ruleRow->label);
	if (!empty($ruleRow->source_url)) $ruleLabel = '<a href="'.dol_escape_htmltag((string) $ruleRow->source_url).'" target="_blank" rel="noopener">'.dol_escape_htmltag($ruleLabel).'</a>';
	$sourceLabel = $langs->trans((string) $ruleRow->source_title);
	if (!empty($ruleRow->source_url)) $sourceLabel = '<a href="'.dol_escape_htmltag((string) $ruleRow->source_url).'" target="_blank" rel="noopener">'.dol_escape_htmltag($sourceLabel).'</a>';
	if (!empty($ruleRow->source_review_date)) $sourceLabel .= '<br><span class="opacitymedium">'.$langs->trans('RegulatorySourceReviewedOn', dol_print_date($db->jdate($ruleRow->source_review_date), 'day')).'</span>';
	print '<tr class="oddeven"><td>'.dol_escape_htmltag((string) $ruleRow->code).'</td><td>'.$ruleLabel.'</td><td>'.$langs->trans(!empty($ruleRow->is_native) ? 'NativeRule' : 'EntityRuleOverride').(!empty($ruleRow->parent_label) ? ' — '.dol_escape_htmltag($langs->trans((string) $ruleRow->parent_label)) : '').'</td><td>'.dol_escape_htmltag((string) $ruleRow->obligation_group).'</td><td>'.dol_escape_htmltag((string) $ruleRow->applicability_code).'</td><td class="center">'.((int) $ruleRow->applicability_priority).'</td><td>'.dol_escape_htmltag(implode(' — ', $periodParts)).'</td><td>'.$sourceLabel.'<br><span class="opacitymedium">'.dol_escape_htmltag((string) $ruleRow->territory).'</span></td><td>'.$langs->trans('BlockingMode'.ucfirst((string) $ruleRow->default_blocking_mode)).'</td><td class="center">'.dolGetStatus($langs->trans(!empty($ruleRow->active) ? 'Enabled' : 'Disabled'), '', '', !empty($ruleRow->active) ? 'status4' : 'status0', 5).'</td><td>'.dol_escape_htmltag((string) $ruleRow->override_reason).'</td></tr>';
}
if (empty($ruleRows)) print '<tr class="oddeven"><td colspan="11"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</table></div>';
$blockingOptions = array('' => $langs->trans('InheritNativeRule'), 'none' => $langs->trans('BlockingModeNone'), 'assignment' => $langs->trans('BlockingModeAssignment'), 'service' => $langs->trans('BlockingModeService'), 'both' => $langs->trans('BlockingModeBoth'));
$calculatorOptions = array('periodic_months' => $langs->trans('RuleCalculatorPeriodicMonths'), 'periodic_days' => $langs->trans('RuleCalculatorPeriodicDays'), 'document_expiry' => $langs->trans('RuleCalculatorDocumentExpiry'));
$overrideQuestions = array(
	'text' => '<div class="info">'.$langs->trans('OverrideBlankValuesInherited').'</div>',
	array(
		'type' => 'other',
		'name' => 'parent_rule_id',
		'label' => '<span class="fieldrequired">'.$langs->trans('ParentRegulatoryRule').'</span>',
		'tdclass' => 'titlefieldcreate',
		'value' => $form->selectarray('parent_rule_id', $nativeRuleOptions, GETPOSTINT('parent_rule_id'), 1, 0, 0, '', 1, 0, 0, '', 'minwidth500', 1),
	),
	array('type' => 'text', 'name' => 'override_label', 'label' => $langs->trans('OverrideLabel'), 'value' => dol_escape_htmltag(GETPOST('override_label', 'alphanohtml')), 'morecss' => 'minwidth500'),
	array('type' => 'other', 'name' => 'override_initial_delay_months', 'label' => $langs->trans('InitialDelayMonths'), 'value' => '<input type="text" class="flat width75" inputmode="numeric" id="override_initial_delay_months" name="override_initial_delay_months" value="'.dol_escape_htmltag(GETPOST('override_initial_delay_months', 'alphanohtml')).'"> '.$langs->trans('Month')),
	array('type' => 'other', 'name' => 'override_recurrence_months', 'label' => $langs->trans('RecurrenceMonths'), 'value' => '<input type="text" class="flat width75" inputmode="numeric" id="override_recurrence_months" name="override_recurrence_months" value="'.dol_escape_htmltag(GETPOST('override_recurrence_months', 'alphanohtml')).'"> '.$langs->trans('Month')),
	array('type' => 'other', 'name' => 'override_recurrence_days', 'label' => $langs->trans('RecurrenceDays'), 'value' => '<input type="text" class="flat width75" inputmode="numeric" id="override_recurrence_days" name="override_recurrence_days" value="'.dol_escape_htmltag(GETPOST('override_recurrence_days', 'alphanohtml')).'"> '.$langs->trans('Days')),
	array('type' => 'other', 'name' => 'override_recheck_days', 'label' => $langs->trans('RecheckDays'), 'value' => '<input type="text" class="flat width75" inputmode="numeric" id="override_recheck_days" name="override_recheck_days" value="'.dol_escape_htmltag(GETPOST('override_recheck_days', 'alphanohtml')).'"> '.$langs->trans('Days')),
	array(
		'type' => 'other',
		'name' => 'override_blocking_mode',
		'label' => $langs->trans('RuleBlockingMode'),
		'value' => $form->selectarray('override_blocking_mode', $blockingOptions, GETPOST('override_blocking_mode', 'aZ09'), 0, 0, 0, '', 1, 0, 0, '', 'minwidth300', 1),
	),
	array(
		'type' => 'other',
		'name' => 'override_effective_fromday,override_effective_frommonth,override_effective_fromyear,override_effective_today,override_effective_tomonth,override_effective_toyear',
		'label' => $langs->trans('RuleEffectivePeriod'),
		'value' => $form->selectDate($overrideEffectiveFrom ?: -1, 'override_effective_from', 0, 0, 1, '', 1, 1).' — '.$form->selectDate($overrideEffectiveTo ?: -1, 'override_effective_to', 0, 0, 1, '', 1, 1),
	),
	array('type' => 'textarea', 'name' => 'override_reason', 'label' => '<span class="fieldrequired">'.$langs->trans('OverrideReason').'</span>', 'value' => dol_escape_htmltag(GETPOST('override_reason', 'restricthtml')), 'morecss' => 'flat centpercent', 'moreattr' => 'rows="3"'),
);
$customRuleQuestions = array(
	array('type' => 'text', 'name' => 'custom_label', 'label' => '<span class="fieldrequired">'.$langs->trans('Label').'</span>', 'value' => dol_escape_htmltag(GETPOST('custom_label', 'alphanohtml')), 'morecss' => 'minwidth500', 'tdclass' => 'titlefieldcreate'),
	array('type' => 'textarea', 'name' => 'custom_description', 'label' => $langs->trans('Description'), 'value' => dol_escape_htmltag(GETPOST('custom_description', 'restricthtml')), 'morecss' => 'flat centpercent', 'moreattr' => 'rows="2"'),
	array(
		'type' => 'other',
		'name' => 'custom_profile_ids',
		'label' => '<span class="fieldrequired">'.$langs->trans('RegulatoryProfiles').'</span>',
		'value' => $form->multiselectarray('custom_profile_ids', $regulatoryProfileOptions, $selectedCustomProfiles, 0, 0, 'minwidth500'),
	),
	array(
		'type' => 'other',
		'name' => 'custom_calculator_code',
		'label' => '<span class="fieldrequired">'.$langs->trans('RuleCalculator').'</span>',
		'value' => $form->selectarray('custom_calculator_code', $calculatorOptions, GETPOST('custom_calculator_code', 'aZ09') ?: 'periodic_months', 0, 0, 0, '', 1, 0, 0, '', 'minwidth300', 1),
	),
	array('type' => 'other', 'name' => 'custom_initial_delay_months', 'label' => $langs->trans('InitialDelayMonths'), 'value' => '<input type="text" class="flat width75" inputmode="numeric" id="custom_initial_delay_months" name="custom_initial_delay_months" value="'.dol_escape_htmltag(GETPOST('custom_initial_delay_months', 'alphanohtml')).'"> '.$langs->trans('Month')),
	array('type' => 'other', 'name' => 'custom_recurrence_months', 'label' => $langs->trans('RecurrenceMonths'), 'value' => '<input type="text" class="flat width75" inputmode="numeric" id="custom_recurrence_months" name="custom_recurrence_months" value="'.dol_escape_htmltag(GETPOST('custom_recurrence_months', 'alphanohtml')).'"> '.$langs->trans('Month')),
	array('type' => 'other', 'name' => 'custom_recurrence_days', 'label' => $langs->trans('RecurrenceDays'), 'value' => '<input type="text" class="flat width75" inputmode="numeric" id="custom_recurrence_days" name="custom_recurrence_days" value="'.dol_escape_htmltag(GETPOST('custom_recurrence_days', 'alphanohtml')).'"> '.$langs->trans('Days')),
	array('type' => 'other', 'name' => 'custom_recheck_days', 'label' => $langs->trans('RecheckDays'), 'value' => '<input type="text" class="flat width75" inputmode="numeric" id="custom_recheck_days" name="custom_recheck_days" value="'.dol_escape_htmltag(GETPOST('custom_recheck_days', 'alphanohtml')).'"> '.$langs->trans('Days')),
	array(
		'type' => 'other',
		'name' => 'custom_blocking_mode',
		'label' => $langs->trans('RuleBlockingMode'),
		'value' => $form->selectarray('custom_blocking_mode', $blockingOptions, GETPOST('custom_blocking_mode', 'aZ09') ?: 'none', 0, 0, 0, '', 1, 0, 0, '', 'minwidth300', 1),
	),
	array(
		'type' => 'other',
		'name' => 'custom_effective_fromday,custom_effective_frommonth,custom_effective_fromyear,custom_effective_today,custom_effective_tomonth,custom_effective_toyear',
		'label' => $langs->trans('RuleEffectivePeriod'),
		'value' => $form->selectDate($customEffectiveFrom ?: -1, 'custom_effective_from', 0, 0, 1, '', 1, 1).' — '.$form->selectDate($customEffectiveTo ?: -1, 'custom_effective_to', 0, 0, 1, '', 1, 1),
	),
	array('type' => 'textarea', 'name' => 'custom_reason', 'label' => '<span class="fieldrequired">'.$langs->trans('CustomizationReason').'</span>', 'value' => dol_escape_htmltag(GETPOST('custom_reason', 'restricthtml')), 'morecss' => 'flat centpercent', 'moreattr' => 'rows="3"'),
);
$overrideButtonId = 'action-create-regulatory-rule-override';
$customRuleButtonId = 'action-create-custom-regulatory-rule';
$useAjaxModal = !empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile);
$overrideButtonUrl = $useAjaxModal ? '' : $_SERVER['PHP_SELF'].'?action=show_override&token='.newToken();
$customRuleButtonUrl = $useAjaxModal ? '' : $_SERVER['PHP_SELF'].'?action=show_custom_rule&token='.newToken();
print '<div class="tabsAction">';
print dolGetButtonAction('', $langs->trans('CreateRegulatoryRuleOverride'), 'default', $overrideButtonUrl, $overrideButtonId);
print dolGetButtonAction('', $langs->trans('CreateCustomRegulatoryRule'), 'default', $customRuleButtonUrl, $customRuleButtonId);
print '</div>';
if ($useAjaxModal || in_array($action, array('show_override', 'create_override'), true)) {
	$overrideUseAjax = $useAjaxModal ? ($action === 'create_override' ? 1 : $overrideButtonId) : 0;
	print $form->formconfirm($_SERVER['PHP_SELF'], $langs->trans('CreateRegulatoryRuleOverride'), '', 'create_override', $overrideQuestions, '', $overrideUseAjax, 620, 900, 0, 'Create', 'Cancel');
}
if ($useAjaxModal || in_array($action, array('show_custom_rule', 'create_custom_rule'), true)) {
	$customRuleUseAjax = $useAjaxModal ? ($action === 'create_custom_rule' ? 1 : $customRuleButtonId) : 0;
	print $form->formconfirm($_SERVER['PHP_SELF'], $langs->trans('CreateCustomRegulatoryRule'), '', 'create_custom_rule', $customRuleQuestions, '', $customRuleUseAjax, 680, 900, 0, 'Create', 'Cancel');
}
print '<div class="info">'.$langs->trans('RegulatoryCatalogDisclaimer').'</div>'.dol_get_fiche_end(); llxFooter(); $db->close();
