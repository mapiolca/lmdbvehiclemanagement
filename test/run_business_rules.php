<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once dirname(__DIR__).'/class/lmdbvehiclemanagementrules.class.php';

$checks = array(
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
