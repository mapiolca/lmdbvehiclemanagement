<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

// Synthetic fractional-day response, not a claim about an authenticated QWS account.
(static function () use ($configuration, $settings, $user, $conf, $db) {
	global $langs;
	$settings['DURATION_UNIT'] = 'days';
	$configuration->save($user, $settings);
	qxCheck($configuration->load(1)['DURATION_UNIT'] === 'days' && LmdbVehicleQuartixConfig::unavailableReason('durations') === '', 'Fractional days accepted by native entity settings and compatibility');
	$conf->entity = 2;
	$conf->global->LMDBVEHICLEMANAGEMENT_QX_DURATION_UNIT = 'hours';
	qxCheck($configuration->load(1)['DURATION_UNIT'] === 'days' && $configuration->load(2)['DURATION_UNIT'] === 'hours', 'Shared data keeps its collector unit, not the viewing entity unit');
	$conf->entity = 1;
	foreach (array('', 'seconds', 'minutes', 'hours', 'days') as $unit) {
		$settings['DURATION_UNIT'] = $unit;
		$configuration->save($user, $settings);
		qxCheck($configuration->load(1)['DURATION_UNIT'] === $unit, 'Explicit duration setting preserved: '.$unit);
	}
	$settings['DURATION_UNIT'] = 'unsupported';
	qxReject(static function () use ($configuration, $user, $settings) { $configuration->save($user, $settings); }, 'QxInvalidSettings');
	$settings['DURATION_UNIT'] = '';
	$configuration->save($user, $settings);
	qxCheck(LmdbVehicleQuartixConfig::unavailableReason('durations') === 'QxDurationUnconfirmed', 'No automatic unit inference from a duration or distance');

	$normalized = LmdbVehicleQuartixRules::summaries(array(array('VehicleID' => 10, 'Date' => '2026-08-30', 'NumberOfTrips' => 5, 'Distance' => 9.7, 'TravelTime' => 0.01912037037, 'IdlingTime' => 0.00170138889)), 10, '2026-08-30', '2026-08-30')['2026-08-30'];
	qxCheck($normalized['travel'] === 0.01912037037 && $normalized['idling'] === 0.00170138889, 'QWS boundary keeps raw fractional durations without conversion');
	$usageSource = file_get_contents(dirname(__DIR__).'/vehicle_quartix.php');
	$tripSource = file_get_contents(dirname(__DIR__).'/vehicle_trips.php');
	$group = 'day'; $dates = array('start' => '2026-08-30', 'end' => '2026-08-30');
	$actionsLeft = false; $routeAvailable = false;
	$visible = array('travel' => array('align' => 'right'), 'idling' => array('align' => 'right'));
	$cases = array(
		array('days', $normalized['travel'], $normalized['idling'], '00:27', '00:02'),
		array('days', 2.5, 0.0, '60:00', '00:00'),
		array('hours', 1.5, null, '01:30', '—'),
		array('minutes', 90.0, 1.0, '01:30', '00:01'),
		array('seconds', 5400.0, 1.0, '01:30', '00:00'),
		array('', 0.01912037037, 0.00170138889, '—', '—'),
		array('unsupported', 1.0, 1.0, '—', '—'),
		array('days', null, null, '—', '—'),
	);
	foreach ($cases as $case) {
		list($unit, $travel, $idling, $expectedTravel, $expectedIdling) = $case;
		$durationUnit = LmdbVehicleQuartixConfig::DURATION_UNITS[$unit] ?? null;
		$rows = array((object) array('period' => '2026-08-30', 'travel' => $travel, 'idling' => $idling, 'distance' => 9.7, 'travel_time' => $travel, 'idling_time' => $idling, 'is_private' => 0, 'departure' => null));
		$result = array('rows' => $rows);
		foreach (array('rows' => $usageSource, "result['rows']" => $tripSource) as $variable => $source) {
			$start = strpos($source, 'foreach ($'.$variable.' as $row) {');
			$end = strpos($source, 'if (!$'.$variable.')', $start);
			if ($start === false || $end === false) throw new RuntimeException('Duration renderer not found');
			ob_start(); eval(substr($source, $start, $end - $start)); $html = ob_get_clean();
			preg_match_all('/data-col="(?:travel|idling)">(.*?)<\/td>/s', $html, $cells);
			qxCheck(array_map('strip_tags', $cells[1]) === array($expectedTravel, $expectedIdling), 'Actual usage/journal rendering: '.$unit.' '.var_export($travel, true));
		}
		$allRows = $rows;
		$start = strpos($usageSource, '$chartSeries = array(');
		$end = strpos($usageSource, 'foreach ($chartSeries as', $start);
		if ($start === false || $end === false) throw new RuntimeException('Duration chart not found');
		eval(substr($usageSource, $start, $end - $start));
		$data = $chartSeries['duration']['data'];
		if ($durationUnit === null || $travel === null || $idling === null) qxCheck($data === array(), 'Unknown durations are absent from chart');
		else {
			qxCheck(count($data) === 1 && ($data[0][1] == 0 ? '00:00' : convertSecondToTime((int) round($data[0][1] * 3600), 'allhourmin')) === $expectedTravel
				&& ($data[0][2] == 0 ? '00:00' : convertSecondToTime((int) round($data[0][2] * 3600), 'allhourmin')) === $expectedIdling, 'Chart hours agree with table durations without rounding before aggregation');
		}
	}
})();
