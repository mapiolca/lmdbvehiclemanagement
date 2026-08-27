<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/class/lmdbvehiclemanagementrules.class.php';

/**
 * Unit tests for persistence-independent fleet rules.
 */
final class LmdbVehicleManagementRulesTest extends TestCase
{
	public function testPrimaryAssignmentRangesOverlapAtTheirBoundary(): void
	{
		self::assertTrue(LmdbVehicleManagementRules::dateRangesOverlap(100, 200, 200, 300));
		self::assertFalse(LmdbVehicleManagementRules::dateRangesOverlap(100, 199, 200, 300));
		self::assertTrue(LmdbVehicleManagementRules::dateRangesOverlap(100, null, 1000, null));
	}

	public function testStandardOdometerCannotDecrease(): void
	{
		self::assertTrue(LmdbVehicleManagementRules::odometerTransitionIsAllowed(10000.0, 10001.0, 'standard', null));
		self::assertFalse(LmdbVehicleManagementRules::odometerTransitionIsAllowed(10000.0, 9999.0, 'standard', null));
	}

	public function testQualifiedCorrectionOrReplacementCanDecrease(): void
	{
		self::assertFalse(LmdbVehicleManagementRules::odometerTransitionIsAllowed(10000.0, 100.0, 'correction', ''));
		self::assertTrue(LmdbVehicleManagementRules::odometerTransitionIsAllowed(10000.0, 100.0, 'correction', 'Incorrect prior reading'));
		self::assertTrue(LmdbVehicleManagementRules::odometerTransitionIsAllowed(10000.0, 0.0, 'replacement', 'Counter replaced'));
	}

	public function testRemovingAReadingCannotExposeAnUnqualifiedDecrease(): void
	{
		self::assertFalse(LmdbVehicleManagementRules::odometerRemovalPreservesSequence(10000.0, 9999.0, 'standard'));
		self::assertTrue(LmdbVehicleManagementRules::odometerRemovalPreservesSequence(10000.0, 0.0, 'replacement'));
	}
}
