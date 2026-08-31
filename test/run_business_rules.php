<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once dirname(__DIR__).'/class/lmdbvehiclemanagementrules.class.php';
require_once dirname(__DIR__).'/class/lmdbvehicleenergy.class.php';
require_once dirname(__DIR__).'/class/lmdbvehicleconsumable.class.php';
require_once dirname(__DIR__).'/class/lmdbvehicleconsumptionstats.class.php';
require_once dirname(__DIR__).'/lib/lmdbvehiclemanagement.lib.php';

$defaultEnergies = LmdbVehicleEnergy::getDefaultDefinitions();
$defaultConsumables = LmdbVehicleConsumable::getDefaultDefinitions();
$energyCompatibility = LmdbVehicleConsumable::getDefaultEnergyCompatibility();
$statsService = new LmdbVehicleConsumptionStats(null);
$stats = $statsService->summarize(array(
	array('entity' => 1, 'vehicle_id' => 1, 'vehicle_ref' => 'AA-123-BB', 'registration_number' => 'AA-123-BB', 'consumable_id' => 1, 'consumable_label' => 'Essence', 'category' => 'fuel', 'unit' => 'L', 'currency' => 'EUR', 'quantity' => 40.0, 'total_ttc' => 80.0, 'date' => 1000, 'odometer_km' => 10000.0, 'reading_kind' => 'standard', 'capacity' => 50.0, 'wltp_range_km' => 600.0, 'oil_reference' => ''),
	array('entity' => 1, 'vehicle_id' => 1, 'vehicle_ref' => 'AA-123-BB', 'registration_number' => 'AA-123-BB', 'consumable_id' => 1, 'consumable_label' => 'Essence', 'category' => 'fuel', 'unit' => 'L', 'currency' => 'EUR', 'quantity' => 30.0, 'total_ttc' => 66.0, 'date' => 1000 + 864000, 'odometer_km' => 10500.0, 'reading_kind' => 'standard', 'capacity' => 50.0, 'wltp_range_km' => 600.0, 'oil_reference' => ''),
	array('entity' => 1, 'vehicle_id' => 1, 'vehicle_ref' => 'AA-123-BB', 'registration_number' => 'AA-123-BB', 'consumable_id' => 1, 'consumable_label' => 'Essence', 'category' => 'fuel', 'unit' => 'L', 'currency' => 'USD', 'quantity' => 10.0, 'total_ttc' => 25.0, 'date' => 1000 + 1728000, 'odometer_km' => 11000.0, 'reading_kind' => 'standard', 'capacity' => 50.0, 'wltp_range_km' => 600.0, 'oil_reference' => ''),
));
$fuelStats = $stats['1:1:1:L:EUR'];

$checks = array(
	'energy_dictionary_has_46_p3_codes' => count($defaultEnergies) === 46,
	'energy_dictionary_contains_essence' => isset($defaultEnergies['ES']) && $defaultEnergies['ES'] === 'Essence',
	'energy_dictionary_contains_diesel' => isset($defaultEnergies['GO']) && $defaultEnergies['GO'] === 'Gazole',
	'energy_dictionary_contains_electricity' => isset($defaultEnergies['EL']) && $defaultEnergies['EL'] === 'Électricité',
	'energy_dictionary_contains_hydrogen' => isset($defaultEnergies['H2']) && $defaultEnergies['H2'] === 'Hydrogène',
	'consumable_dictionary_contains_fuels_and_additives' => isset($defaultConsumables['GASOLINE'], $defaultConsumables['ELECTRICITY'], $defaultConsumables['HYDROGEN'], $defaultConsumables['ADBLUE'], $defaultConsumables['OIL']),
	'consumable_display_label_decodes_legacy_html_entities' => LmdbVehicleConsumable::displayLabel('&Eacute;lectricit&eacute;') === 'Électricité',
	'consumable_units_use_readable_symbols' => LmdbVehicleConsumable::unitLabel('M3') === 'm³' && LmdbVehicleConsumable::unitLabel('KWH') === 'kWh',
	'electric_vehicle_excludes_adblue_and_oil_capacities' => !in_array('ADBLUE', $energyCompatibility['EL'], true) && !in_array('OIL', $energyCompatibility['EL'], true),
	'diesel_vehicle_includes_adblue_and_oil_capacities' => in_array('ADBLUE', $energyCompatibility['GO'], true) && in_array('OIL', $energyCompatibility['GO'], true),
	'all_energies_include_transverse_fluid_capacities' => count(array_filter($energyCompatibility, static function ($codes) {
		return in_array('WASHER_FLUID', $codes, true) && in_array('COOLANT', $codes, true) && in_array('OTHER_ADDITIVE', $codes, true);
	})) === 46,
	'all_46_p3_codes_have_a_compatibility' => count($energyCompatibility) === 46 && !array_diff_key($defaultEnergies, $energyCompatibility),
	'hybrid_energy_keeps_multiple_compatible_fuels' => in_array('GASOLINE', $energyCompatibility['EE'], true) && in_array('ELECTRICITY', $energyCompatibility['EE'], true),
	'consumption_average_uses_positive_intervals' => abs((float) $fuelStats['consumption_100'] - 6.0) < 0.00001,
	'consumption_weighted_unit_price' => abs((float) $fuelStats['weighted_unit_price'] - (146.0 / 70.0)) < 0.00001,
	'consumption_capacity_percentage' => abs((float) $fuelStats['average_capacity_percent'] - 70.0) < 0.00001,
	'consumption_does_not_mix_historical_currencies' => count($stats) === 2 && isset($stats['1:1:1:L:USD']) && abs((float) $fuelStats['total_cost'] - 146.0) < 0.00001,
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
