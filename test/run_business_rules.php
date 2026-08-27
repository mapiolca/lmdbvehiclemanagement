<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once dirname(__DIR__).'/class/lmdbvehiclemanagementrules.class.php';
require_once dirname(__DIR__).'/class/lmdbvehicleenergy.class.php';

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
);

$failed = array_keys(array_filter($checks, static function ($result) {
	return !$result;
}));
if (!empty($failed)) {
	fwrite(STDERR, 'Failed checks: '.implode(', ', $failed).PHP_EOL);
	exit(1);
}

print count($checks).' business rule checks passed'.PHP_EOL;
