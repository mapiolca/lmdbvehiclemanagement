<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/class/lmdbvehiclemanagementrules.class.php';
require_once dirname(__DIR__, 2).'/class/lmdbvehicleenergy.class.php';

/**
 * Unit tests for persistence-independent fleet rules.
 */
final class LmdbVehicleManagementRulesTest extends TestCase
{
	public function testDefaultEnergyDictionaryUsesTheP3Codes(): void
	{
		$energies = LmdbVehicleEnergy::getDefaultDefinitions();
		self::assertCount(46, $energies);
		self::assertSame('Essence', $energies['ES']);
		self::assertSame('Gazole', $energies['GO']);
		self::assertSame('Électricité', $energies['EL']);
		self::assertSame('Hydrogène', $energies['H2']);
	}

	public function testVehicleLifecycleTransitions(): void
	{
		self::assertTrue(LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(0, 1));
		self::assertTrue(LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(1, 2));
		self::assertTrue(LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(2, 3));
		self::assertTrue(LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(3, 2));
		self::assertTrue(LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(1, 4));
		self::assertTrue(LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(2, 4));
		self::assertTrue(LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(3, 4));
		self::assertFalse(LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(0, 2));
		self::assertFalse(LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(3, 1));
		self::assertFalse(LmdbVehicleManagementRules::vehicleStatusTransitionIsAllowed(4, 2));
	}

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

	public function testInsuranceContractLifecycleTransitions(): void
	{
		self::assertTrue(LmdbVehicleManagementRules::insuranceContractStatusTransitionIsAllowed(0, 1));
		self::assertTrue(LmdbVehicleManagementRules::insuranceContractStatusTransitionIsAllowed(1, 9));
		self::assertFalse(LmdbVehicleManagementRules::insuranceContractStatusTransitionIsAllowed(0, 9));
		self::assertFalse(LmdbVehicleManagementRules::insuranceContractStatusTransitionIsAllowed(9, 1));
	}

	public function testInsuranceCertificateLifecycleTransitions(): void
	{
		self::assertTrue(LmdbVehicleManagementRules::insuranceCertificateStatusTransitionIsAllowed(0, 1));
		self::assertTrue(LmdbVehicleManagementRules::insuranceCertificateStatusTransitionIsAllowed(1, 2));
		self::assertTrue(LmdbVehicleManagementRules::insuranceCertificateStatusTransitionIsAllowed(1, 3));
		self::assertTrue(LmdbVehicleManagementRules::insuranceCertificateStatusTransitionIsAllowed(2, 9));
		self::assertTrue(LmdbVehicleManagementRules::insuranceCertificateStatusTransitionIsAllowed(3, 9));
		self::assertFalse(LmdbVehicleManagementRules::insuranceCertificateStatusTransitionIsAllowed(2, 3));
	}

	public function testInsuranceReminderBucketsAreDeterministic(): void
	{
		self::assertSame(0, LmdbVehicleManagementRules::insuranceReminderBucket(0, 7));
		self::assertSame(1, LmdbVehicleManagementRules::insuranceReminderBucket(8, 7));
		self::assertSame(2, LmdbVehicleManagementRules::insuranceReminderBucket(14, 7));
	}
}
