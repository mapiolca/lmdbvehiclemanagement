<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Pure business rules shared by persistence objects and unit tests.
 */
class LmdbVehicleManagementRules
{
	/**
	 * Check a vehicle lifecycle transition.
	 *
	 * @param int $from Current status
	 * @param int $to Target status
	 * @return bool
	 */
	public static function vehicleStatusTransitionIsAllowed($from, $to)
	{
		$allowed = array(
			0 => array(1),
			1 => array(2, 4),
			2 => array(3, 4),
			3 => array(2, 4),
			4 => array(),
		);

		return isset($allowed[$from]) && in_array($to, $allowed[$from], true);
	}

	/**
	 * Check whether two inclusive time ranges overlap.
	 *
	 * A null end date represents an open-ended period.
	 *
	 * @param int $firstStart First start timestamp
	 * @param ?int $firstEnd First end timestamp
	 * @param int $secondStart Second start timestamp
	 * @param ?int $secondEnd Second end timestamp
	 * @return bool
	 */
	public static function dateRangesOverlap($firstStart, $firstEnd, $secondStart, $secondEnd)
	{
		$firstEndComparable = $firstEnd === null ? PHP_INT_MAX : $firstEnd;
		$secondEndComparable = $secondEnd === null ? PHP_INT_MAX : $secondEnd;

		return $firstStart <= $secondEndComparable && $secondStart <= $firstEndComparable;
	}

	/**
	 * Check whether an odometer transition is allowed.
	 *
	 * Corrections and counter replacements explicitly allow a decrease only
	 * when a non-empty reason has been supplied.
	 *
	 * @param ?float $previousKm Previous reading, if any
	 * @param float $currentKm Submitted reading
	 * @param string $kind standard, correction or replacement
	 * @param ?string $reason Qualification supplied by the user
	 * @return bool
	 */
	public static function odometerTransitionIsAllowed($previousKm, $currentKm, $kind, $reason)
	{
		if ($currentKm < 0) {
			return false;
		}
		if ($kind === 'correction' || $kind === 'replacement') {
			return trim((string) $reason) !== '';
		}
		if ($kind !== 'standard') {
			return false;
		}

		return $previousKm === null || $currentKm >= $previousKm;
	}

	/**
	 * Check the transition exposed when an intermediate reading is removed.
	 *
	 * A decrease is allowed only when the following reading itself carries the
	 * explicit correction or counter-replacement qualification.
	 *
	 * @param ?float $previousKm Reading before the removed row
	 * @param ?float $nextKm Reading after the removed row
	 * @param ?string $nextKind Kind of the following reading
	 * @return bool
	 */
	public static function odometerRemovalPreservesSequence($previousKm, $nextKm, $nextKind)
	{
		if ($previousKm === null || $nextKm === null || $nextKind !== 'standard') {
			return true;
		}

		return self::odometerTransitionIsAllowed($previousKm, $nextKm, 'standard', null);
	}

	/**
	 * Check a contract lifecycle transition.
	 *
	 * @param int $from Current status
	 * @param int $to Target status
	 * @return bool
	 */
	public static function insuranceContractStatusTransitionIsAllowed($from, $to)
	{
		$allowed = array(
			0 => array(1),
			1 => array(9),
			9 => array(),
		);

		return isset($allowed[$from]) && in_array($to, $allowed[$from], true);
	}

	/**
	 * Check an insurance certificate lifecycle transition.
	 *
	 * @param int $from Current status
	 * @param int $to Target status
	 * @return bool
	 */
	public static function insuranceCertificateStatusTransitionIsAllowed($from, $to)
	{
		$allowed = array(
			0 => array(1),
			1 => array(2, 3),
			2 => array(9),
			3 => array(9),
			9 => array(),
		);

		return isset($allowed[$from]) && in_array($to, $allowed[$from], true);
	}

	/**
	 * Return the deterministic overdue reminder bucket.
	 *
	 * @param int $daysOverdue Number of elapsed days
	 * @param int $repeatDays Repeat interval
	 * @return int
	 */
	public static function insuranceReminderBucket($daysOverdue, $repeatDays)
	{
		$repeatDays = max(1, $repeatDays);

		return $daysOverdue < 0 ? -1 : (int) floor($daysOverdue / $repeatDays);
	}
}
