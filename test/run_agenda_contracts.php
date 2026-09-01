<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once dirname(__DIR__).'/class/lmdbvehicleagenda.class.php';

$moduleRoot = dirname(__DIR__);
$definitions = LmdbVehicleAgenda::getTriggerDefinitions();
$objectDefinitions = LmdbVehicleAgenda::getObjectDefinitions();
$sql = file_get_contents($moduleRoot.'/sql/data.sql');
$triggerClass = file_get_contents($moduleRoot.'/core/triggers/interface_99_modLmdbVehicleManagement_LmdbVehicleManagementTriggers.class.php');
$baseObject = file_get_contents($moduleRoot.'/class/lmdbvehiclemanagementobject.class.php');
$descriptor = file_get_contents($moduleRoot.'/core/modules/modLmdbVehicleManagement.class.php');
$hookClass = file_get_contents($moduleRoot.'/class/actions_lmdbvehiclemanagement.class.php');
$vehicleImport = file_get_contents($moduleRoot.'/class/lmdbvehicleimport.class.php');
$consumptionImport = file_get_contents($moduleRoot.'/class/lmdbvehicleconsumptionimport.class.php');
$consumptionObject = file_get_contents($moduleRoot.'/class/lmdbvehicleconsumption.class.php');
$odometerObject = file_get_contents($moduleRoot.'/class/lmdbvehicleodometerreading.class.php');
$referenceMigration = file_get_contents($moduleRoot.'/class/lmdbvehiclereferencemigration.class.php');
$fr = file_get_contents($moduleRoot.'/langs/fr_FR/lmdbvehiclemanagement.lang');
$en = file_get_contents($moduleRoot.'/langs/en_US/lmdbvehiclemanagement.lang');

$checks = array(
	'agenda_matrix_has_7_objects' => count($objectDefinitions) === 7,
	'agenda_matrix_has_21_crud_triggers' => count($definitions) === 21,
	'trigger_class_uses_central_matrix' => strpos($triggerClass, 'LmdbVehicleAgenda::getTriggerDefinitions()') !== false,
	'base_object_sets_actionmsg2' => strpos($baseObject, "context['actionmsg2']") !== false,
	'base_object_sets_actionmsg' => strpos($baseObject, "context['actionmsg']") !== false,
	'descriptor_enables_import_hook' => preg_match("/'imports'/", $descriptor) === 1,
	'descriptor_defaults_agenda_from_matrix' => strpos($descriptor, "'MAIN_AGENDA_ACTIONAUTO_'.\$triggerCode") !== false,
	'descriptor_preserves_existing_zero_constants' => strpos($descriptor, 'if ($constantExists === 0)') !== false,
	'vehicle_import_uses_business_object' => strpos($vehicleImport, '$vehicle->create($user, $runTriggers ? 0 : 1)') !== false,
	'vehicle_import_sets_import_reason' => strpos($vehicleImport, "context['trigger_reason'] = 'import'") !== false,
	'vehicle_import_hook_distinguishes_real_step' => strpos($hookClass, '$step === 6') !== false,
	'consumption_import_uses_business_object' => strpos($consumptionImport, '->create($user') !== false,
	'technical_reference_migration_has_no_trigger' => strpos($referenceMigration, 'reference_migration') === false && strpos($referenceMigration, '->call_trigger(') === false,
	'no_manual_actioncomm_in_module_trigger' => stripos($triggerClass, 'new ActionComm') === false && strpos($triggerClass, "MAIN_DB_PREFIX.'actioncomm'") === false,
	'vehicle_delete_not_blocked_by_agenda_history' => strpos(file_get_contents($moduleRoot.'/class/lmdbvehicle.class.php'), 'VehicleHasAgendaEvents') === false,
	'trigger_handler_keeps_zero_as_success' => strpos($triggerClass, 'return 0;') !== false,
	'consumption_composite_creates_odometer' => strpos($consumptionObject, 'createFromConsumption($user)') !== false,
	'consumption_composite_emits_consumption_create' => strpos($consumptionObject, 'parent::create($user, $notrigger)') !== false,
	'odometer_trigger_uses_crud_prefix' => strpos($odometerObject, "public \$TRIGGER_PREFIX = 'LMDBVEHICLEMANAGEMENT_ODOMETER';") !== false,
);

foreach ($definitions as $code => $definition) {
	$classContents = file_get_contents($moduleRoot.'/'.$definition['class_file']);
	$prefix = $definition['trigger_prefix'];
	$checks['class_prefix_'.$code] = strpos($classContents, "public \$TRIGGER_PREFIX = '".$prefix."';") !== false;
	$checks['element_resolvable_'.$code] = strpos($hookClass, "'".$definition['elementtype']."'") !== false;
	$checks['sql_element_'.$code] = substr_count($sql, "SELECT '".$definition['elementtype']."', '".$code."'") === 1;
	$checks['sql_idempotent_'.$code] = substr_count($sql, "WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = '".$code."')") === 1;
	$checks['fr_translation_'.$code] = preg_match('/^Notify_'.preg_quote($code, '/').'=.+$/m', $fr) === 1;
	$checks['en_translation_'.$code] = preg_match('/^Notify_'.preg_quote($code, '/').'=.+$/m', $en) === 1;
	$checks['crud_only_'.$code] = preg_match('/_(CREATE|UPDATE|DELETE)$/', $code) === 1;
}

foreach (array('AgendaCreateTitle', 'AgendaUpdateTitle', 'AgendaDeleteTitle', 'AgendaEventDescription', 'AgendaChangedFields') as $translationKey) {
	$checks['fr_event_text_'.$translationKey] = preg_match('/^'.preg_quote($translationKey, '/').'=.+$/m', $fr) === 1;
	$checks['en_event_text_'.$translationKey] = preg_match('/^'.preg_quote($translationKey, '/').'=.+$/m', $en) === 1;
}

$failed = array_keys(array_filter($checks, static function ($result) {
	return !$result;
}));
if (!empty($failed)) {
	fwrite(STDERR, 'Failed checks: '.implode(', ', $failed).PHP_EOL);
	exit(1);
}

print count($checks).' Agenda contract checks passed'.PHP_EOL;
