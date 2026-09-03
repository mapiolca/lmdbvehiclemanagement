<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$root = dirname(__DIR__);

/** @param string $path Relative module path @return string */
function regulatorySource($path)
{
	global $root;
	$contents = file_get_contents($root.'/'.$path);
	if (!is_string($contents)) {
		fwrite(STDERR, 'Unable to read '.$path.PHP_EOL);
		exit(1);
	}
	return $contents;
}

$descriptor = regulatorySource('core/modules/modLmdbVehicleManagement.class.php');
$vehicle = regulatorySource('class/lmdbvehicle.class.php');
$assignment = regulatorySource('class/lmdbvehicleassignment.class.php');
$control = regulatorySource('class/lmdbvehicleregulatorycontrol.class.php');
$service = regulatorySource('class/lmdbvehicleregulatoryservice.class.php');
$catalog = regulatorySource('class/lmdbvehicleregulatorycatalog.class.php');
$cron = regulatorySource('class/lmdbvehicleregulatorycron.class.php');
$card = regulatorySource('regulatorycontrol_card.php');
$document = regulatorySource('regulatorycontrol_document.php');
$controlList = regulatorySource('regulatorycontrol_list.php');
$schedule = regulatorySource('regulatorycontrol_schedule.php');
$vehicleRegulatory = regulatorySource('vehicle_regulatory.php');
$adminRegulatory = regulatorySource('admin/regulatory.php');
$permissions = regulatorySource('lib/lmdbvehiclemanagement.lib.php');
$moduleStylesheet = regulatorySource('css/lmdbvehiclemanagement.css');
$moduleJavascript = regulatorySource('js/lmdbvehiclemanagement.js');
$sql = regulatorySource('sql/data.sql');
$ruleSql = regulatorySource('sql/llx_lmdbvehiclemanagement_regulatory_rule.sql');
$requirementSql = regulatorySource('sql/llx_lmdbvehiclemanagement_control_requirement.sql');
$questionSql = regulatorySource('sql/llx_c_lmdbvehiclemanagement_regulatory_question.sql');
$choiceSql = regulatorySource('sql/llx_c_lmdbvehiclemanagement_regulatory_question_choice.sql');
$answerSql = regulatorySource('sql/llx_lmdbvehiclemanagement_vehicle_regulatory_answer.sql');
$fr = regulatorySource('langs/fr_FR/lmdbvehiclemanagement.lang');
$en = regulatorySource('langs/en_US/lmdbvehiclemanagement.lang');
$runtimePhpSources = '';
$runtimeFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($runtimeFiles as $runtimeFile) {
	if (!$runtimeFile->isFile() || strtolower($runtimeFile->getExtension()) !== 'php') continue;
	$relativeRuntimePath = str_replace('\\', '/', substr($runtimeFile->getPathname(), strlen($root) + 1));
	if (strpos($relativeRuntimePath, 'test/') === 0) continue;
	$runtimeSource = file_get_contents($runtimeFile->getPathname());
	if (!is_string($runtimeSource)) {
		fwrite(STDERR, 'Unable to read '.$relativeRuntimePath.PHP_EOL);
		exit(1);
	}
	$runtimePhpSources .= $runtimeSource;
}

$checks = array(
	'module_version_0141' => strpos($descriptor, "\$this->version = '0.14.1';") !== false,
	'optional_registration_schema' => strpos(regulatorySource('sql/llx_lmdbvehiclemanagement_vehicle.sql'), 'registration_number varchar(32) DEFAULT NULL') !== false,
	'material_reference_fallback' => strpos(regulatorySource('core/modules/lmdbvehiclemanagement/mod_lmdbvehicle_registration.php'), "return 'MAT'.\$period") !== false,
	'control_numbering_model' => strpos(regulatorySource('core/modules/lmdbvehiclemanagement/mod_lmdbvehicleregulatorycontrol_standard.php'), "public \$prefix = 'CTL'") !== false,
	'normalized_profiles' => strpos(regulatorySource('sql/llx_lmdbvehiclemanagement_vehicle_regulatory_profile.sql'), 'fk_profile integer NOT NULL') !== false,
	'normalized_rule_links' => strpos(regulatorySource('sql/llx_lmdbvehiclemanagement_regulatory_rule_profile.sql'), 'fk_rule integer NOT NULL') !== false,
	'normalized_qualification_questionnaire' => strpos($questionSql, 'entity integer DEFAULT 1 NOT NULL') !== false
		&& strpos($choiceSql, 'fk_question integer NOT NULL') !== false
		&& strpos($answerSql, 'fk_vehicle integer NOT NULL') !== false
		&& strpos($answerSql, 'fk_choice integer NOT NULL') !== false
		&& strpos(regulatorySource('sql/llx_lmdbvehiclemanagement_vehicle_regulatory_answer.key.sql'), 'UNIQUE INDEX uk_lmdbvm_vehicle_reg_answer (entity, fk_vehicle, fk_question)') !== false,
	'requirements_materialized' => strpos(regulatorySource('sql/llx_lmdbvehiclemanagement_control_requirement.sql'), 'retained_due_date date DEFAULT NULL') !== false,
	'requirements_keep_applicability_date' => strpos($requirementSql, 'applicability_date date DEFAULT NULL') !== false,
	'rules_have_exclusive_applicability_metadata' => strpos($ruleSql, 'obligation_group varchar(64)') !== false
		&& strpos($ruleSql, 'applicability_code varchar(64)') !== false
		&& strpos($ruleSql, 'applicability_priority integer') !== false,
	'requirements_can_be_historically_deactivated' => strpos(regulatorySource('sql/llx_lmdbvehiclemanagement_control_requirement.sql'), 'active smallint DEFAULT 1 NOT NULL') !== false && strpos($service, 'SET active = 0') !== false,
	'obsolete_requirements_are_never_deleted' => strpos($service, "DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement") === false
		&& strpos($catalog, "DELETE req FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement") === false,
	'shared_profiles_resolve_to_local_rules' => strpos($service, 'selected_profile') !== false && strpos($service, 'local_profile') !== false,
	'control_is_common_object' => strpos($control, 'extends LmdbVehicleManagementObject') !== false,
	'control_uses_crud_prefix' => strpos($control, "public \$TRIGGER_PREFIX = 'LMDBVEHICLEMANAGEMENT_REGULATORY_CONTROL';") !== false,
	'validation_requires_document' => strpos($control, 'hasSupportingDocument()') !== false && strpos($control, "businessError('ControlSupportingDocumentRequired')") !== false,
	'validated_control_is_immutable' => strpos($control, "businessError('ValidatedRegulatoryControlIsImmutable')") !== false,
	'only_drafts_are_deleted' => strpos($control, "businessError('OnlyDraftControlCanBeDeleted')") !== false,
	'cancel_requires_reason' => strpos($control, "businessError('ControlCancellationReasonRequired')") !== false,
	'replacement_requires_cancelled_same_vehicle_control' => strpos($control, "businessError('InvalidPreviousRegulatoryControl')") !== false && strpos($control, 'status = '."self::STATUS_CANCELLED") !== false,
	'official_date_has_priority' => strpos($control, '!empty($this->official_valid_until)') !== false,
	'due_override_requires_reason' => strpos($control, "businessError('DueDateOverrideReasonRequired')") !== false,
	'profile_confirmation_precedes_requirements' => strpos($service, 'vp.confirmed = 1') !== false,
	'event_based_rule_has_no_invented_due_date' => strpos($service, "\$calculator === 'document_expiry' || \$calculator === 'event_based'") !== false,
	'rule_effective_dates_are_applied' => strpos($service, 'r.effective_from IS NULL OR r.effective_from <= CURRENT_DATE') !== false && strpos($service, 'r.effective_to IS NULL OR r.effective_to >= CURRENT_DATE') !== false,
	'multiple_vgp_profiles_are_explicit' => strpos($catalog, "'VGP_3M'") !== false && strpos($catalog, "'VGP_6M'") !== false && strpos($catalog, "'VGP_12M'") !== false,
	'specialized_public_profiles_replace_legacy_profile' => strpos($catalog, "'SPECIAL_TAXI_VTC'") !== false
		&& strpos($catalog, "'SPECIAL_SANITARY'") !== false
		&& strpos($catalog, "'SPECIAL_DRIVING_SCHOOL'") !== false
		&& strpos($catalog, "'SPECIAL_BREAKDOWN'") !== false
		&& strpos($catalog, "'SPECIAL_PUBLIC_LT10'") !== false
		&& strpos($catalog, "code = 'SPECIAL_PUBLIC'") !== false,
	'questionnaire_uses_specialized_answers_as_profile_source' => strpos($catalog, 'public static function getQualificationQuestions()') !== false
		&& strpos($service, 'choice_profile.profile_code') !== false
		&& strpos($service, "'questionnaire'") !== false
		&& strpos($service, 'code NOT IN ('."'.implode(',', \$quotedManagedCodes).')") !== false,
	'qualification_is_transactional_and_emits_one_vehicle_update' => strpos($service, 'public function saveVehicleQualification(') !== false
		&& strpos($service, "\$vehicle->context['trigger_reason'] = 'regulatory_qualification_change';") !== false
		&& substr_count(substr($service, strpos($service, 'public function saveVehicleQualification('), strpos($service, 'public function saveVehicleProfiles(') - strpos($service, 'public function saveVehicleQualification(')), "call_trigger('LMDBVEHICLEMANAGEMENT_VEHICLE_UPDATE'") === 1,
	'legacy_profile_entry_point_uses_central_qualification_workflow' => strpos($service, 'return $this->saveVehicleQualification($vehicle, $answers') !== false,
	'qualification_removes_stale_answers_and_keeps_known_profiles' => strpos($service, 'fk_question NOT IN ('."'.implode(',', array_map('intval', array_keys(\$validatedAnswers)))") !== false
		&& strpos($service, '."\', 1, \'".$this->db->idate(dol_now())') !== false,
	'one_rule_is_selected_per_obligation_group' => strpos($service, '$selectedRules[$group]') !== false
		&& strpos($service, '$priority > $selectedRules[$group]') !== false
		&& strpos($catalog, "'group' => 'ROAD_MAIN'") !== false
		&& strpos($catalog, "'group' => 'VGP'") !== false,
	'n1_pollution_exemptions_are_explicit' => strpos($service, "array('GA', 'EL', 'AC', 'H2', 'HE', 'HH')") !== false
		&& strpos($service, '10, 1, 1972') !== false
		&& strpos($service, '1, 1, 1980') !== false,
	'category_l_transition_and_recheck_are_complete' => strpos($catalog, "'effective_from' => '2024-04-15'") !== false
		&& strpos($service, '$year <= 2016') !== false
		&& strpos($service, '$year <= 2019') !== false
		&& strpos($service, '$year <= 2021') !== false
		&& strpos($service, '8, 15, 2024') !== false
		&& strpos($service, "\$code === 'FR_CATEGORY_L'") !== false,
	'vgp_intervals_are_exclusive_and_sourced' => strpos($catalog, "'FR_VGP_3M'") !== false
		&& strpos($catalog, "'FR_VGP_6M'") !== false
		&& strpos($catalog, "'FR_VGP_12M'") !== false
		&& strpos($catalog, "'2005-03-31'") !== false
		&& strpos($catalog, 'LEGIARTI000006680469') !== false,
	'heavy_recheck_deadlines_cover_mainland_and_overseas' => strpos($service, "'FR_GUADELOUPE', 'FR_MARTINIQUE', 'FR_GUYANE', 'FR_REUNION', 'FR_MAYOTTE'") !== false
		&& strpos($service, "return 30;") !== false
		&& strpos($control, "return (int) \$this->control_date;") !== false,
	'native_rules_are_overridden_without_update' => strpos($catalog, 'createEntityOverride(') !== false && strpos($catalog, 'fk_parent_rule') !== false && strpos($catalog, 'is_native = 1') !== false,
	'custom_rules_are_normalized' => strpos($catalog, 'createEntityCustomRule(') !== false && strpos($catalog, 'lmdbvehiclemanagement_regulatory_rule_profile') !== false,
	'admin_exposes_versioned_rule_management' => strpos($adminRegulatory, "'create_override', \$overrideQuestions") !== false
		&& strpos($adminRegulatory, "'create_custom_rule', \$customRuleQuestions") !== false
		&& strpos($adminRegulatory, "\$overrideButtonId = 'action-create-regulatory-rule-override';") !== false
		&& strpos($adminRegulatory, "\$customRuleButtonId = 'action-create-custom-regulatory-rule';") !== false,
	'admin_displays_rule_applicability_and_source_metadata' => strpos($adminRegulatory, 'RegulatoryRuleGroup') !== false
		&& strpos($adminRegulatory, 'RegulatoryApplicabilityCode') !== false
		&& strpos($adminRegulatory, 'RegulatorySourceReviewedOn') !== false,
	'reminder_horizons_use_native_multiselect' => strpos($adminRegulatory, "multiselectarray('reminder_horizons'") !== false && strpos($adminRegulatory, "GETPOST('reminder_horizons', 'array:int')") !== false,
	'daily_overdue_reminders_are_optional_and_native' => strpos($descriptor, "'LMDBVEHICLEMANAGEMENT_REGULATORY_DAILY_OVERDUE_REMINDERS' => '0'") !== false
		&& strpos($adminRegulatory, "ajax_constantonoff('LMDBVEHICLEMANAGEMENT_REGULATORY_DAILY_OVERDUE_REMINDERS')") !== false
		&& strpos($cron, "\$remaining < 0 && getDolGlobalInt('LMDBVEHICLEMANAGEMENT_REGULATORY_DAILY_OVERDUE_REMINDERS') > 0") !== false,
	'daily_overdue_reminders_keep_one_attempt_per_day' => strpos($cron, "':'.\$remaining.':'.\$email.':'.dol_print_date(\$dueDate, '%Y-%m-%d')") !== false
		&& strpos($cron, 'INSERT IGNORE INTO') !== false,
	'blocking_assignment' => strpos($assignment, "vehicleActionIsAllowed((int) \$this->fk_vehicle, 'assignment')") !== false,
	'blocking_service' => strpos($vehicle, "vehicleActionIsAllowed((int) \$this->id, 'service')") !== false,
	'derogation_remains_alert' => strpos($service, "return 'derogation_active'") !== false,
	'cron_is_declared' => strpos($descriptor, "'method' => 'runDaily'") !== false,
	'cron_prevents_concurrent_entity_runs' => strpos($cron, 'GET_LOCK(') !== false && strpos($cron, 'RELEASE_LOCK(') !== false,
	'cron_resynchronizes_rule_validity' => strpos($cron, 'synchronizeEntityRequirements($entity, $user)') !== false,
	'cron_is_idempotent' => strpos($cron, 'INSERT IGNORE INTO') !== false && strpos($cron, 'fk_actioncomm') !== false,
	'cron_resolves_complete_native_agenda_type' => strpos($cron, 'private function resolveAgendaType()') !== false
		&& strpos($cron, "private const AGENDA_FALLBACK_TYPE_CODE = 'AC_OTH_AUTO';") !== false
		&& strpos($cron, "\$event->type_id = \$agendaType['id'];") !== false
		&& strpos($cron, "\$event->type_code = \$agendaType['code'];") !== false,
	'cron_identifies_owned_deadlines_by_event_code' => strpos($cron, "in_array((string) \$event->code, array(self::AGENDA_EVENT_CODE, self::AGENDA_FALLBACK_TYPE_CODE), true)") !== false,
	'cron_uses_email_template' => strpos($cron, "type_template = 'lmdbvehicle_regulatory_reminder'") !== false,
	'manual_scheduled_job_run_forces_regulatory_reminders' => strpos($cron, 'public function runDaily($force = 0)') !== false
		&& strpos($cron, "GETPOST('action', 'aZ09') === 'confirm_execute'") !== false
		&& strpos($cron, "GETPOST('confirm', 'alpha') === 'yes'") !== false
		&& strpos($cron, "in_array('--force', \$argv, true)") !== false
		&& strpos($cron, "':forced:'.\$forcedRunKey") !== false
		&& strpos($cron, "Execution mode: '.(\$force ? 'forced' : 'automatic')") !== false,
	'reminder_subject_is_plain_utf8' => strpos($cron, "html_entity_decode(strtr(\$template['subject'], \$replacements), ENT_QUOTES | ENT_HTML5, 'UTF-8')") !== false
		&& strpos($descriptor, "transnoentitiesnoconv('RegulatoryReminderEmailSubject')") !== false,
	'reminder_recipient_identity_is_preserved' => strpos($cron, "'id' => (int) \$row->rowid") !== false
		&& strpos($cron, "'email' => \$email") !== false
		&& strpos($cron, "((int) \$recipient['id'])") !== false
		&& strpos($cron, "ORDER BY FIELD(rowid, '.implode(',', \$userIds).')'") !== false,
	'reminder_recipient_addresses_are_visible_and_validated' => strpos($adminRegulatory, "SELECT rowid, firstname, lastname, login, email") !== false
		&& strpos($adminRegulatory, ".' — '.\$email") !== false
		&& strpos($cron, '!isValidEmail($email)') !== false,
	'future_agenda_event_is_linked' => strpos($cron, "elementtype = 'lmdbvehicle@lmdbvehiclemanagement'") !== false,
	'future_agenda_type_is_short_and_automatic' => strpos($sql, "'AC_LMDB_REGDUE', 'systemauto'") !== false && strlen('AC_LMDB_REGDUE') <= 16,
	'crud_triggers_declared' => substr_count($sql, 'LMDBVEHICLEMANAGEMENT_REGULATORY_CONTROL_') === 6,
	'card_has_csrf_token' => strpos($card, 'newToken()') !== false,
	'document_uses_multicompany_output' => strpos($document, 'getMultidirOutput(') !== false,
	'schedule_has_sql_filters' => strpos($schedule, 'search_status') !== false && strpos($schedule, 'sortfield') !== false && strpos($schedule, 'print_barre_liste') !== false,
	'regulatory_lists_have_native_column_selectors' => count(array_filter(array($controlList, $schedule), static function ($source) {
		return strpos($source, 'actions_changeselectedfields.inc.php') !== false
			&& strpos($source, "\$contextpage = GETPOST('contextpage', 'aZ09');") !== false
			&& strpos($source, "\$varpage = empty(\$contextpage) ? \$_SERVER['PHP_SELF'] : \$contextpage;") !== false
			&& strpos($source, "multiSelectArrayWithCheckbox('selectedfields', \$arrayfields, \$varpage, \$conf->main_checkbox_left_column)") !== false
			&& strpos($source, 'name="formfilteraction" id="formfilteraction" value="list"') !== false
			&& strpos($source, "if (\$conf->main_checkbox_left_column) print '<td class=\"liste_titre center maxwidthsearch actioncolumn\">'.\$form->showFilterButtons('left')") !== false
			&& strpos($source, "if (!\$conf->main_checkbox_left_column) print '<td class=\"liste_titre center maxwidthsearch actioncolumn\">'.\$form->showFilterButtons()") !== false
			&& substr_count($source, 'getTitleFieldOfList($selectedfields') === 2;
	})) === 2,
	'shared_control_type_filter_uses_stable_code' => strpos($schedule, 'rule_ct.code = selected_ct.code') !== false,
	'vehicle_tab_uses_guided_native_questionnaire' => strpos($vehicleRegulatory, "action\" value=\"save_qualification") !== false
		&& strpos($vehicleRegulatory, 'getQualificationQuestionnaire(') !== false
		&& strpos($vehicleRegulatory, "selectarray('answer_") !== false
		&& strpos($vehicleRegulatory, 'selectDate(') !== false,
	'qualification_status_uses_native_badge' => strpos($vehicleRegulatory, "dolGetStatus(\$langs->trans('QualificationToConfirm'), '', '', 'status3', 5)") !== false && strpos($vehicleRegulatory, "dolGetStatus(\$langs->trans('QualificationConfirmed'), '', '', 'status4', 5)") !== false,
	'control_form_uses_native_buttons' => strpos($card, '<input type="submit" class="button button-save"') !== false && strpos($card, '<input type="submit" class="button button-cancel" name="cancel"') !== false && strpos($card, 'formnovalidate') !== false && strpos($vehicleRegulatory, '<input type="submit" class="button button-save"') !== false,
	'schedule_status_is_centered_native_badge' => strpos($schedule, "if (!empty(\$arrayfields['req.status']['checked'])) print '<td class=\"center\">'.\$form->selectarray('search_status'") !== false && strpos($schedule, "\$statusTypes[\$row->status] ?? 'status0', 5") !== false,
	'overdue_requirement_uses_native_danger_badge' => strpos($schedule, "'overdue' => 'status8'") !== false && strpos($vehicleRegulatory, "'overdue' => 'status8'") !== false,
	'validated_controls_archive_only_after_replacement' => strpos($control, 'fk_previous_control = '."'.((int) \$current->id)") !== false
		&& strpos($control, 'status = '."'.self::STATUS_VALIDATED") !== false
		&& strpos($control, "businessError('OnlyCancelledOrReplacedControlCanBeArchived')") !== false
		&& strpos($control, 'public function canBeArchived()') !== false
		&& strpos($card, '$object->canBeArchived() > 0') !== false,
	'legacy_vehicles_require_explicit_qualification' => strpos($descriptor, 'road_vehicle_unqualified') !== false && strpos($descriptor, 'LMDBVEHICLEMANAGEMENT_REGULATORY_VEHICLE_SCHEMA_VERSION') !== false,
	'legacy_special_public_migration_is_conservative' => strpos($descriptor, 'migrateRegulatoryQualification141(') !== false
		&& strpos($descriptor, "profile.code = 'SPECIAL_PUBLIC'") !== false
		&& strpos($descriptor, "rule_definition.code = 'FR_SPECIAL_PUBLIC'") !== false
		&& strpos($descriptor, 'SET requirement.active = 0') !== false
		&& strpos($descriptor, 'synchronizeEntityRequirements((int) $entity, $user)') !== false,
	'permissions_use_only_native_hasright' => strpos($runtimePhpSources, 'lmdbVehicleManagementUserIsFullAdmin') === false
		&& strpos($runtimePhpSources, 'lmdbVehicleManagementCanDo') === false
		&& strpos($runtimePhpSources, 'rights->lmdbvehiclemanagement') === false
		&& strpos($descriptor, '$user->hasRight("lmdbvehiclemanagement", "read")') !== false
		&& strpos($descriptor, '$user->hasRight("lmdbvehiclemanagement", "regulatorycontrol", "write")') !== false,
	'schedule_column_selector_fix_is_strictly_scoped' => strpos($moduleStylesheet, '.mod-lmdbvehiclemanagement.page-regulatorycontrol-schedule .dropdown') !== false
		&& strpos($moduleStylesheet, 'z-index: 10000;') !== false
		&& strpos($moduleJavascript, ".mod-lmdbvehiclemanagement.page-regulatorycontrol-schedule .dropdown") !== false
		&& strpos($moduleStylesheet, '.mod-lmdbvehiclemanagement.page-list .dropdown') === false,
	'vehicle_reference_migration_uses_numbering_lock' => strpos($vehicle, 'acquireNumberingLock') !== false && strpos($vehicle, 'releaseNumberingLock') !== false,
	'exports_controls_and_register' => strpos($descriptor, 'lmdbvehiclemanagement_regulatory_controls') !== false
		&& strpos($descriptor, 'lmdbvehiclemanagement_safety_register') !== false
		&& strpos($descriptor, 'qualification_answers') !== false
		&& strpos($descriptor, 'rr.source_url') !== false,
	'imports_are_drafts' => strpos($descriptor, "'t.status' => 'const-0'") !== false,
	'fr_translations' => strpos($fr, 'RegulatoryControls=Contrôles réglementaires') !== false && strpos($fr, 'Notify_LMDBVEHICLEMANAGEMENT_REGULATORY_CONTROL_CREATE=') !== false && strpos($fr, 'SimpleRegulatoryControlNumRefModelDesc=Références des contrôles réglementaires au format CTL AAMM-0001') !== false && strpos($fr, 'EnableDailyOverdueRegulatoryReminders=Activer les rappels journaliers après échéance') !== false && strpos($fr, 'RegulatoryQuestionTaxiVtc=') !== false && strpos($fr, 'RuleSpecialPublicLt10=') !== false,
	'en_translations' => strpos($en, 'RegulatoryControls=Regulatory controls') !== false && strpos($en, 'Notify_LMDBVEHICLEMANAGEMENT_REGULATORY_CONTROL_CREATE=') !== false && strpos($en, 'SimpleRegulatoryControlNumRefModelDesc=Regulatory control references using CTL YYMM-0001 format') !== false && strpos($en, 'EnableDailyOverdueRegulatoryReminders=Enable daily reminders after the due date') !== false && strpos($en, 'RegulatoryQuestionTaxiVtc=') !== false && strpos($en, 'RuleSpecialPublicLt10=') !== false,
);

$failed = array_keys(array_filter($checks, static function ($result) {
	return !$result;
}));
if (!empty($failed)) {
	fwrite(STDERR, 'Failed regulatory checks: '.implode(', ', $failed).PHP_EOL);
	exit(1);
}

print count($checks).' regulatory contract checks passed'.PHP_EOL;
