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
$sql = regulatorySource('sql/data.sql');
$fr = regulatorySource('langs/fr_FR/lmdbvehiclemanagement.lang');
$en = regulatorySource('langs/en_US/lmdbvehiclemanagement.lang');

$checks = array(
	'module_version_0140' => strpos($descriptor, "\$this->version = '0.14.0';") !== false,
	'optional_registration_schema' => strpos(regulatorySource('sql/llx_lmdbvehiclemanagement_vehicle.sql'), 'registration_number varchar(32) DEFAULT NULL') !== false,
	'material_reference_fallback' => strpos(regulatorySource('core/modules/lmdbvehiclemanagement/mod_lmdbvehicle_registration.php'), "return 'MAT'.\$period") !== false,
	'control_numbering_model' => strpos(regulatorySource('core/modules/lmdbvehiclemanagement/mod_lmdbvehicleregulatorycontrol_standard.php'), "public \$prefix = 'CTL'") !== false,
	'normalized_profiles' => strpos(regulatorySource('sql/llx_lmdbvehiclemanagement_vehicle_regulatory_profile.sql'), 'fk_profile integer NOT NULL') !== false,
	'normalized_rule_links' => strpos(regulatorySource('sql/llx_lmdbvehiclemanagement_regulatory_rule_profile.sql'), 'fk_rule integer NOT NULL') !== false,
	'requirements_materialized' => strpos(regulatorySource('sql/llx_lmdbvehiclemanagement_control_requirement.sql'), 'retained_due_date date DEFAULT NULL') !== false,
	'requirements_can_be_historically_deactivated' => strpos(regulatorySource('sql/llx_lmdbvehiclemanagement_control_requirement.sql'), 'active smallint DEFAULT 1 NOT NULL') !== false && strpos($service, 'SET active = 0') !== false,
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
	'native_rules_are_overridden_without_update' => strpos($catalog, 'createEntityOverride(') !== false && strpos($catalog, 'fk_parent_rule') !== false && strpos($catalog, 'is_native = 1') !== false,
	'custom_rules_are_normalized' => strpos($catalog, 'createEntityCustomRule(') !== false && strpos($catalog, 'lmdbvehiclemanagement_regulatory_rule_profile') !== false,
	'admin_exposes_versioned_rule_management' => strpos($adminRegulatory, "action\" value=\"create_override") !== false && strpos($adminRegulatory, "action\" value=\"create_custom_rule") !== false,
	'reminder_horizons_use_native_multiselect' => strpos($adminRegulatory, "multiselectarray('reminder_horizons'") !== false && strpos($adminRegulatory, "GETPOST('reminder_horizons', 'array:int')") !== false,
	'blocking_assignment' => strpos($assignment, "vehicleActionIsAllowed((int) \$this->fk_vehicle, 'assignment')") !== false,
	'blocking_service' => strpos($vehicle, "vehicleActionIsAllowed((int) \$this->id, 'service')") !== false,
	'derogation_remains_alert' => strpos($service, "return 'derogation_active'") !== false,
	'cron_is_declared' => strpos($descriptor, "'method' => 'runDaily'") !== false,
	'cron_prevents_concurrent_entity_runs' => strpos($cron, 'GET_LOCK(') !== false && strpos($cron, 'RELEASE_LOCK(') !== false,
	'cron_resynchronizes_rule_validity' => strpos($cron, 'synchronizeEntityRequirements($entity, $user)') !== false,
	'cron_is_idempotent' => strpos($cron, 'INSERT IGNORE INTO') !== false && strpos($cron, 'fk_actioncomm') !== false,
	'cron_uses_email_template' => strpos($cron, "type_template = 'lmdbvehicle_regulatory_reminder'") !== false,
	'future_agenda_event_is_linked' => strpos($cron, "elementtype = 'lmdbvehicle@lmdbvehiclemanagement'") !== false,
	'future_agenda_type_is_short_and_automatic' => strpos($sql, "'AC_LMDB_REGULATORY_DUE', 'systemauto'") !== false && strlen('AC_LMDB_REGULATORY_DUE') <= 50,
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
	'vehicle_tab_confirms_profiles' => strpos($vehicleRegulatory, "action\" value=\"save_profiles") !== false,
	'qualification_status_uses_native_badge' => strpos($vehicleRegulatory, "dolGetStatus(\$langs->trans('QualificationToConfirm'), '', '', 'status3', 5)") !== false && strpos($vehicleRegulatory, "dolGetStatus(\$langs->trans('QualificationConfirmed'), '', '', 'status4', 5)") !== false,
	'control_form_uses_native_buttons' => strpos($card, '<input type="submit" class="button button-save"') !== false && strpos($card, '<input type="submit" class="button button-cancel" name="cancel"') !== false && strpos($card, 'formnovalidate') !== false && strpos($vehicleRegulatory, '<input type="submit" class="button button-save"') !== false,
	'schedule_status_is_centered_native_badge' => strpos($schedule, "if (!empty(\$arrayfields['req.status']['checked'])) print '<td class=\"center\">'.\$form->selectarray('search_status'") !== false && strpos($schedule, "\$statusTypes[\$row->status] ?? 'status0', 5") !== false,
	'legacy_vehicles_require_explicit_qualification' => strpos($descriptor, 'road_vehicle_unqualified') !== false && strpos($descriptor, 'LMDBVEHICLEMANAGEMENT_REGULATORY_VEHICLE_SCHEMA_VERSION') !== false,
	'vehicle_reference_migration_uses_numbering_lock' => strpos($vehicle, 'acquireNumberingLock') !== false && strpos($vehicle, 'releaseNumberingLock') !== false,
	'exports_controls_and_register' => strpos($descriptor, 'lmdbvehiclemanagement_regulatory_controls') !== false && strpos($descriptor, 'lmdbvehiclemanagement_safety_register') !== false,
	'imports_are_drafts' => strpos($descriptor, "'t.status' => 'const-0'") !== false,
	'fr_translations' => strpos($fr, 'RegulatoryControls=Contrôles réglementaires') !== false && strpos($fr, 'Notify_LMDBVEHICLEMANAGEMENT_REGULATORY_CONTROL_CREATE=') !== false && strpos($fr, 'SimpleRegulatoryControlNumRefModelDesc=Références des contrôles réglementaires au format CTL AAMM-0001') !== false,
	'en_translations' => strpos($en, 'RegulatoryControls=Regulatory controls') !== false && strpos($en, 'Notify_LMDBVEHICLEMANAGEMENT_REGULATORY_CONTROL_CREATE=') !== false && strpos($en, 'SimpleRegulatoryControlNumRefModelDesc=Regulatory control references using CTL YYMM-0001 format') !== false,
);

$failed = array_keys(array_filter($checks, static function ($result) {
	return !$result;
}));
if (!empty($failed)) {
	fwrite(STDERR, 'Failed regulatory checks: '.implode(', ', $failed).PHP_EOL);
	exit(1);
}

print count($checks).' regulatory contract checks passed'.PHP_EOL;
