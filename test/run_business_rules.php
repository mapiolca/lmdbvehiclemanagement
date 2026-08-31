<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once dirname(__DIR__).'/class/lmdbvehiclemanagementrules.class.php';
require_once dirname(__DIR__).'/class/lmdbvehicleenergy.class.php';
require_once dirname(__DIR__).'/lib/lmdbvehiclemanagement.lib.php';

$defaultEnergies = LmdbVehicleEnergy::getDefaultDefinitions();

$checks = array(
	'energy_dictionary_has_46_p3_codes' => count($defaultEnergies) === 46,
	'energy_dictionary_contains_essence' => isset($defaultEnergies['ES']) && $defaultEnergies['ES'] === 'Essence',
	'energy_dictionary_contains_diesel' => isset($defaultEnergies['GO']) && $defaultEnergies['GO'] === 'Gazole',
	'energy_dictionary_contains_electricity' => isset($defaultEnergies['EL']) && $defaultEnergies['EL'] === 'Électricité',
	'energy_dictionary_contains_hydrogen' => isset($defaultEnergies['H2']) && $defaultEnergies['H2'] === 'Hydrogène',
	'vehicle_draft_to_validated' => LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(0, 1),
	'vehicle_validated_to_in_service' => LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(1, 2),
	'vehicle_in_service_to_out_of_service' => LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(2, 3),
	'vehicle_out_of_service_to_in_service' => LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(3, 2),
	'vehicle_validated_to_sold' => LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(1, 4),
	'vehicle_in_service_to_sold' => LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(2, 4),
	'vehicle_out_of_service_to_sold' => LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(3, 4),
	'vehicle_draft_cannot_skip_validation' => !LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(0, 2),
	'vehicle_out_of_service_cannot_return_to_validated' => !LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(3, 1),
	'vehicle_sold_is_terminal' => !LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(4, 2),
	'overlap_at_boundary' => LmdbVehicleManagementRules::dateRangesOverlap(100, 200, 200, 300),
	'separate_ranges' => !LmdbVehicleManagementRules::dateRangesOverlap(100, 199, 200, 300),
	'open_ended_overlap' => LmdbVehicleManagementRules::dateRangesOverlap(100, null, 1000, null),
	'standard_increase' => LmdbVehicleManagementRules::odometerTransitionIsAllowed(10000.0, 10001.0, 'standard', null),
	'standard_decrease_rejected' => !LmdbVehicleManagementRules::odometerTransitionIsAllowed(10000.0, 9999.0, 'standard', null),
	'qualified_correction' => LmdbVehicleManagementRules::odometerTransitionIsAllowed(10000.0, 100.0, 'correction', 'Incorrect prior reading'),
	'unqualified_correction_rejected' => !LmdbVehicleManagementRules::odometerTransitionIsAllowed(10000.0, 100.0, 'correction', ''),
	'qualified_replacement' => LmdbVehicleManagementRules::odometerTransitionIsAllowed(10000.0, 0.0, 'replacement', 'Counter replaced'),
	'removal_exposing_standard_decrease_rejected' => !LmdbVehicleManagementRules::odometerRemovalPreservesSequence(10000.0, 9999.0, 'standard'),
	'removal_before_qualified_replacement_allowed' => LmdbVehicleManagementRules::odometerRemovalPreservesSequence(10000.0, 0.0, 'replacement'),
	'insurance_contract_draft_to_active' => LmdbVehicleManagementRules::insuranceContractStatusTransitionIsAllowed(0, 1),
	'insurance_contract_active_to_terminated' => LmdbVehicleManagementRules::insuranceContractStatusTransitionIsAllowed(1, 9),
	'insurance_contract_cannot_skip_to_terminated' => !LmdbVehicleManagementRules::insuranceContractStatusTransitionIsAllowed(0, 9),
	'insurance_contract_terminated_is_terminal' => !LmdbVehicleManagementRules::insuranceContractStatusTransitionIsAllowed(9, 1),
	'insurance_certificate_draft_to_review' => LmdbVehicleManagementRules::insuranceCertificateStatusTransitionIsAllowed(0, 1),
	'insurance_certificate_review_to_validated' => LmdbVehicleManagementRules::insuranceCertificateStatusTransitionIsAllowed(1, 2),
	'insurance_certificate_review_to_rejected' => LmdbVehicleManagementRules::insuranceCertificateStatusTransitionIsAllowed(1, 3),
	'insurance_certificate_validated_to_archived' => LmdbVehicleManagementRules::insuranceCertificateStatusTransitionIsAllowed(2, 9),
	'insurance_certificate_rejected_to_archived' => LmdbVehicleManagementRules::insuranceCertificateStatusTransitionIsAllowed(3, 9),
	'insurance_certificate_controlled_is_immutable' => !LmdbVehicleManagementRules::insuranceCertificateStatusTransitionIsAllowed(2, 3),
	'insurance_reminder_bucket_is_stable' => LmdbVehicleManagementRules::insuranceReminderBucket(8, 7) === 1,
	'registration_reference_is_not_repeated' => lmdbVehicleDisplayIdentifier('AA-123-BB', 'AA-123-BB', 'Jumper') === 'AA-123-BB — Jumper',
	'standard_reference_keeps_registration' => lmdbVehicleDisplayIdentifier('VEH2608-0001', 'AA-123-BB', 'Jumper') === 'VEH2608-0001 — AA-123-BB — Jumper',
);

$failed = array_keys(array_filter($checks, static function ($result) {
	return !$result;
}));
if (!empty($failed)) {
	fwrite(STDERR, 'Failed checks: '.implode(', ', $failed).PHP_EOL);
	exit(1);
}

print count($checks).' business rule checks passed'.PHP_EOL;
