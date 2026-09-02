<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once dirname(__DIR__).'/class/lmdbvehicleagenda.class.php';

$moduleRoot = dirname(__DIR__);
$definitions = LmdbVehicleAgenda::getTriggerDefinitions();
$objectDefinitions = LmdbVehicleAgenda::getObjectDefinitions();
$agendaClass = file_get_contents($moduleRoot.'/class/lmdbvehicleagenda.class.php');
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

if (!defined('MAIN_DB_PREFIX')) {
	define('MAIN_DB_PREFIX', 'llx_');
}

/** Minimal translation service used to exercise the final Agenda wording. */
class LmdbAgendaTestLangs
{
	/** @var array<string,string> */
	private $translations = array();

	/** @param string $contents Dolibarr .lang contents */
	public function __construct($contents)
	{
		foreach (preg_split('/\R/', $contents) as $line) {
			if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
				continue;
			}
			list($key, $value) = explode('=', $line, 2);
			$this->translations[$key] = $value;
		}
	}

	/** @param string $key Translation key @param mixed ...$arguments Format arguments @return string */
	public function transnoentitiesnoconv($key, ...$arguments)
	{
		$value = isset($this->translations[$key]) ? $this->translations[$key] : $key;
		return empty($arguments) ? $value : vsprintf($value, $arguments);
	}
}

/** Minimal database service resolving the vehicle used by a consumption. */
class LmdbAgendaTestDb
{
	/** @param string $sql SQL query @return object */
	public function query($sql)
	{
		return (object) array('row' => (object) array('registration_number' => 'AA-123-ZZ', 'ref' => 'VEH0001'));
	}

	/** @param object $result Query result @return object */
	public function fetch_object($result)
	{
		return $result->row;
	}

	/** @param object $result Query result @return void */
	public function free($result)
	{
	}
}

$checks = array(
	'agenda_matrix_has_7_objects' => count($objectDefinitions) === 7,
	'agenda_matrix_has_21_crud_triggers' => count($definitions) === 21,
	'trigger_class_uses_central_matrix' => strpos($triggerClass, 'LmdbVehicleAgenda::getTriggerDefinitions()') !== false,
	'base_object_sets_actionmsg2' => strpos($baseObject, "context['actionmsg2']") !== false,
	'base_object_sets_actionmsg' => strpos($baseObject, "context['actionmsg']") !== false,
	'base_object_uses_central_message_builder' => strpos($baseObject, 'LmdbVehicleAgenda::buildEventMessages') !== false,
	'base_object_preserves_explicit_messages' => strpos($baseObject, "!empty(\$this->context['actionmsg2']) && !empty(\$this->context['actionmsg'])") !== false,
	'agenda_builder_resolves_linked_vehicle' => strpos($agendaClass, 'fetchVehicleRef') !== false,
	'agenda_builder_resolves_linked_driver' => strpos($agendaClass, 'fetchDriverName') !== false,
	'agenda_builder_resolves_linked_contract' => strpos($agendaClass, 'fetchContractRef') !== false,
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

$crudMessageKeys = array(
	'lmdbvehicle' => array('AgendaVehicleCreated', 'AgendaVehicleUpdated', 'AgendaVehicleDeleted'),
	'lmdbvehicleassignment' => array('AgendaAssignmentCreated', 'AgendaAssignmentUpdated', 'AgendaAssignmentDeleted'),
	'lmdbvehicleodometerreading' => array('AgendaOdometerCreated', 'AgendaOdometerUpdated', 'AgendaOdometerDeleted'),
	'lmdbvehicleconsumption' => array('AgendaConsumptionCreated', 'AgendaConsumptionUpdated', 'AgendaConsumptionDeleted'),
	'lmdbvehicleevent' => array('AgendaVehicleEventCreated', 'AgendaVehicleEventUpdated', 'AgendaVehicleEventDeleted'),
	'lmdbinsurancecontract' => array('AgendaInsuranceContractCreated', 'AgendaInsuranceContractUpdated', 'AgendaInsuranceContractDeleted'),
	'lmdbinsurancecertificate' => array('AgendaInsuranceCertificateCreated', 'AgendaInsuranceCertificateUpdated', 'AgendaInsuranceCertificateDeleted'),
);
foreach ($crudMessageKeys as $element => $expectedKeys) {
	foreach (array('CREATE', 'UPDATE', 'DELETE') as $index => $operation) {
		$messageDefinition = LmdbVehicleAgenda::getMessageDefinition($element, $operation);
		$checks['message_'.$element.'_'.$operation] = $messageDefinition['key'] === $expectedKeys[$index];
	}
}

$transitionMessageCases = array(
	'vehicle_imported' => array('lmdbvehicle', 'CREATE', array('trigger_reason' => 'import'), 'AgendaVehicleImported'),
	'vehicle_validated' => array('lmdbvehicle', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 1), 'AgendaVehicleValidated'),
	'vehicle_in_service' => array('lmdbvehicle', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 2), 'AgendaVehiclePutInService'),
	'vehicle_out_of_service' => array('lmdbvehicle', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 3), 'AgendaVehiclePutOutOfService'),
	'vehicle_sold' => array('lmdbvehicle', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 4), 'AgendaVehicleSold'),
	'vehicle_reference_sync' => array('lmdbvehicle', 'UPDATE', array('trigger_reason' => 'reference_sync'), 'AgendaVehicleReferenceSynchronized'),
	'assignment_deactivated' => array('lmdbvehicleassignment', 'UPDATE', array('new_status' => 0), 'AgendaAssignmentDeactivated'),
	'vehicle_event_closed' => array('lmdbvehicleevent', 'UPDATE', array('new_status' => 2), 'AgendaVehicleEventClosed'),
	'insurance_contract_activated' => array('lmdbinsurancecontract', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 1), 'AgendaInsuranceContractActivated'),
	'insurance_contract_terminated' => array('lmdbinsurancecontract', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 9), 'AgendaInsuranceContractTerminated'),
	'insurance_vehicle_linked' => array('lmdbinsurancecontract', 'UPDATE', array('trigger_reason' => 'vehicle_link'), 'AgendaInsuranceVehicleLinked'),
	'insurance_coverage_updated' => array('lmdbinsurancecontract', 'UPDATE', array('trigger_reason' => 'coverage_change'), 'AgendaInsuranceCoverageUpdated'),
	'certificate_uploaded' => array('lmdbinsurancecertificate', 'CREATE', array('trigger_reason' => 'create_draft'), 'AgendaInsuranceCertificateUploaded'),
	'certificate_uploaded_submitted' => array('lmdbinsurancecertificate', 'CREATE', array('trigger_reason' => 'create_and_submit'), 'AgendaInsuranceCertificateUploadedAndSubmitted'),
	'certificate_document_updated' => array('lmdbinsurancecertificate', 'UPDATE', array('trigger_reason' => 'document_upload'), 'AgendaInsuranceCertificateDocumentUpdated'),
	'certificate_submitted' => array('lmdbinsurancecertificate', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 1), 'AgendaInsuranceCertificateSubmitted'),
	'certificate_validated' => array('lmdbinsurancecertificate', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 2), 'AgendaInsuranceCertificateValidated'),
	'certificate_rejected' => array('lmdbinsurancecertificate', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 3), 'AgendaInsuranceCertificateRejected'),
	'certificate_archived' => array('lmdbinsurancecertificate', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 9), 'AgendaInsuranceCertificateArchived'),
);
foreach ($transitionMessageCases as $caseName => $case) {
	$messageDefinition = LmdbVehicleAgenda::getMessageDefinition($case[0], $case[1], $case[2]);
	$checks['transition_'.$caseName] = $messageDefinition['key'] === $case[3];
}

$testLangs = new LmdbAgendaTestLangs($fr);
$vehicle = (object) array(
	'element' => 'lmdbvehicle',
	'id' => 10,
	'rowid' => 10,
	'entity' => 1,
	'ref' => 'AA-123-ZZ',
	'registration_number' => 'AA-123-ZZ',
	'status' => 2,
	'context' => array('trigger_reason' => 'status_change', 'new_status' => 2),
	'fields' => array(),
);
$vehicleMessages = LmdbVehicleAgenda::buildEventMessages($vehicle, 'UPDATE', $testLangs);
$checks['rendered_vehicle_transition_title'] = $vehicleMessages['title'] === 'Le véhicule AA-123-ZZ a été mis en service';

$consumption = (object) array(
	'element' => 'lmdbvehicleconsumption',
	'id' => 20,
	'rowid' => 20,
	'entity' => 1,
	'ref' => 'CON2609-0001',
	'fk_vehicle' => 10,
	'fk_user_driver' => 0,
	'quantity' => 45.5,
	'unit_snapshot' => 'L',
	'category_snapshot' => 'fuel',
	'odometer_km' => 105800,
	'context' => array('trigger_reason' => 'create'),
	'fields' => array(),
	'db' => new LmdbAgendaTestDb(),
);
$consumptionMessages = LmdbVehicleAgenda::buildEventMessages($consumption, 'CREATE', $testLangs);
$checks['rendered_consumption_title'] = $consumptionMessages['title'] === 'Plein/Recharge CON2609-0001 enregistré';
$checks['rendered_consumption_description'] = strpos($consumptionMessages['description'], 'Véhicule : AA-123-ZZ.') !== false
	&& strpos($consumptionMessages['description'], 'Nature : Carburant / recharge.') !== false
	&& strpos($consumptionMessages['description'], 'Quantité : 45.5 L.') !== false;

$assignmentWithoutResolvableLinks = (object) array(
	'element' => 'lmdbvehicleassignment',
	'id' => 30,
	'rowid' => 30,
	'entity' => 1,
	'fk_vehicle' => 10,
	'fk_user_driver' => 5,
	'context' => array('trigger_reason' => 'delete'),
	'fields' => array(),
);
$assignmentFallbackMessages = LmdbVehicleAgenda::buildEventMessages($assignmentWithoutResolvableLinks, 'DELETE', $testLangs);
$checks['unresolvable_links_use_safe_identifiers'] = $assignmentFallbackMessages['title'] === 'L’affectation de #5 au véhicule #10 a été supprimée';

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
	$checks['native_actioncomm_code_length_'.$code] = strlen('AC_'.$code) <= LmdbVehicleAgenda::ACTIONCOMM_CODE_MAX_LENGTH;
}

$checks['legacy_certificate_triggers_are_migrated'] = strpos($descriptor, 'migrateAgendaCertificateTriggerCodes') !== false
	&& strpos($descriptor, "'LMDBVEHICLEMANAGEMENT_INSURANCE_CERTIFICATE'") !== false
	&& strpos($descriptor, "'LMDBVEHICLEMANAGEMENT_CERTIFICATE'") !== false;
$checks['legacy_agenda_choice_is_preserved'] = strpos($descriptor, '$oldConstantExists === 1 && $newConstantExists === 0') !== false
	&& strpos($descriptor, 'dolibarr_set_const($this->db, $newConstant, (string) $row->value') !== false;
$checks['legacy_certificate_triggers_are_not_redeclared'] = strpos($sql, 'LMDBVEHICLEMANAGEMENT_INSURANCE_CERTIFICATE') === false;

preg_match_all("/'(Agenda[A-Za-z0-9]+)'/", $agendaClass, $messageTranslationMatches);
$messageTranslationKeys = array_values(array_unique($messageTranslationMatches[1]));
foreach ($messageTranslationKeys as $translationKey) {
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
