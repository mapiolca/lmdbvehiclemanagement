<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Strict QWS boundary; no guessed units, timezones or missing numeric values. */
class LmdbVehicleQuartixRules
{
	/** @param mixed $value API value @return float */
	public static function number($value)
	{
		if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < 0) {
			throw new UnexpectedValueException('QxInvalidResponse');
		}
		return (float) $value;
	}

	/** @param mixed $value API identifier @return int */
	public static function id($value)
	{
		if (!is_int($value) || $value <= 0) throw new UnexpectedValueException('QxInvalidResponse');
		return $value;
	}

	/** @param string $day ISO day @return DateTimeImmutable */
	public static function day($day)
	{
		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('UTC'));
		if (!$date || $date->format('Y-m-d') !== $day || $day < '1000-01-01') throw new UnexpectedValueException('QxInvalidResponse');
		return $date;
	}

	/**
	 * QWS prose says local time while examples contain Z. Require an explicit contract.
	 * @param mixed $value API date @param string $mode offset or local @param string $timezone Vehicle timezone
	 * @return int UTC timestamp
	 */
	public static function timestamp($value, $mode, $timezone)
	{
		if (!is_string($value) || !in_array($mode, array('offset', 'local'), true)
			|| !preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(?:\.\d{1,7})?(Z|[+-]\d{2}:\d{2})?$/D', $value, $match)) {
			throw new UnexpectedValueException('QxTimeUnconfirmed');
		}
		self::day($match[1]);
		if ($mode === 'offset' && empty($match[3])) throw new UnexpectedValueException('QxTimeUnconfirmed');
		if (!empty($match[3]) && $match[3] !== 'Z') {
			$hours = (int) substr($match[3], 1, 2); $minutes = (int) substr($match[3], 4, 2);
			if ($hours > 14 || $minutes > 59 || ($hours === 14 && $minutes > 0)) throw new UnexpectedValueException('QxInvalidResponse');
		}
		$zone = new DateTimeZone($mode === 'offset' ? $match[3] : $timezone);
		$plain = $match[1].'T'.$match[2];
		$date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $plain, $zone);
		if (!$date || $date->format('Y-m-d\TH:i:s') !== $plain) throw new UnexpectedValueException('QxInvalidResponse');
		// A repeated local hour at DST rollback has two possible instants: do not guess.
		if ($mode === 'local') {
			$transitions = $zone->getTransitions($date->getTimestamp() - 86400, $date->getTimestamp() + 86400);
			$previous = null;
			foreach (is_array($transitions) ? $transitions : array() as $transition) {
				if ($previous !== null && $transition['offset'] < $previous) {
					$wall = self::day($match[1])->getTimestamp() + (int) substr($match[2], 0, 2) * 3600 + (int) substr($match[2], 3, 2) * 60 + (int) substr($match[2], 6, 2);
					if ($wall >= $transition['ts'] + $transition['offset'] && $wall < $transition['ts'] + $previous) throw new UnexpectedValueException('QxAmbiguousTime');
				}
				$previous = $transition['offset'];
			}
		}
		if ($date->getTimestamp() <= 0) throw new UnexpectedValueException('QxInvalidResponse');
		return $date->getTimestamp();
	}

	/** @param float|null $value Raw duration @param string $unit Confirmed API unit @return float|null Hours */
	public static function hours($value, $unit)
	{
		$factors = array('seconds' => 3600, 'minutes' => 60, 'hours' => 1);
		return $value !== null && isset($factors[$unit]) ? $value / $factors[$unit] : null;
	}

	/**
	 * Missing days remain absent, never fabricated zero activity. One vehicle per request.
	 * @param array<int,mixed> $data Rows @param int $vehicleId Remote id @param string $start First day @param string $end Last day
	 * @return array<string,array{trips:int,distance:float,travel:float,idling:float}>
	 */
	public static function summaries($data, $vehicleId, $start, $end)
	{
		$result = array();
		foreach ($data as $row) {
			if (!is_array($row) || self::id($row['VehicleID'] ?? null) !== $vehicleId || !isset($row['Date']) || !is_string($row['Date'])) throw new UnexpectedValueException('QxInvalidResponse');
			$day = self::day($row['Date'])->format('Y-m-d');
			if ($day < $start || $day > $end || isset($result[$day]) || !isset($row['NumberOfTrips']) || !is_int($row['NumberOfTrips']) || $row['NumberOfTrips'] < 0) throw new UnexpectedValueException('QxInvalidResponse');
			$result[$day] = array('trips' => $row['NumberOfTrips'], 'distance' => self::number($row['Distance'] ?? null), 'travel' => self::number($row['TravelTime'] ?? null), 'idling' => self::number($row['IdlingTime'] ?? null));
		}
		return $result;
	}
}
