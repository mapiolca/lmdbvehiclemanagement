<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
require_once __DIR__.'/lmdbvehiclequartixtrips.class.php';

/** Fleet reporting over the cache only. No QWS transport is instantiated here. */
class LmdbVehicleQuartixDashboard extends LmdbVehicleQuartixService
{
	/**
	 * @param string $start First reporting day @param string $end Last reporting day
	 * @param string $vehicle Vehicle search @param string $association associated/suspended/unlinked or empty
	 * @param list<int> $entities Selected owner entities @param int $limit Limit @param int $offset Offset
	 * @param string $sort Sort key @param string $order Direction
	 * @return array{rows:list<stdClass>,total:int,totals:stdClass,daily:list<stdClass>,comparison:list<stdClass>,jobs:list<stdClass>,expected:int}
	 */
	public function report($start, $end, $vehicle = '', $association = '', $entities = array(), $limit = 20, $offset = 0, $sort = 'vehicle', $order = 'ASC')
	{
		global $user;
		if (!LmdbVehicleQuartixConfig::can($user, 'read') || !LmdbVehicleQuartixConfig::supported()) throw new RuntimeException('QxAccessDenied');
		LmdbVehicleQuartixRules::day($start); LmdbVehicleQuartixRules::day($end);
		if ($start > $end || LmdbVehicleQuartixRules::day($start)->diff(LmdbVehicleQuartixRules::day($end))->days > 366) throw new RuntimeException('QxInvalidPeriod');
		$scope = array_values(array_filter(array_map('intval', explode(',', getEntity('lmdbvehicle'))), static function ($id) { return $id > 0; }));
		if ($entities) $scope = array_values(array_intersect($scope, array_map('intval', $entities)));
		$scopeSql = implode(',', $scope ?: array(0));
		$where = ' WHERE v.entity IN ('.$scopeSql.')';
		if ($vehicle !== '') $where .= natural_search(array('v.ref', 'v.registration_number', 'v.label'), $vehicle);
		$state = "CASE WHEN l.rowid IS NULL THEN 'unlinked' WHEN l.active=0 OR COALESCE(c.value,'0')<>'1' THEN 'suspended' ELSE 'associated' END";
		if ($association !== '') {
			if (!in_array($association, array('associated', 'suspended', 'unlinked'), true)) throw new RuntimeException('QxInvalidSettings');
			$where .= ' AND '.$state."='".$association."'";
		}
		$base = ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link AS l ON l.fk_vehicle=v.rowid AND l.entity=v.entity'
			.' LEFT JOIN '.MAIN_DB_PREFIX."const AS c ON c.entity=v.entity AND c.name='".LmdbVehicleQuartixConfig::PREFIX."ENABLED'";
		// Usage imports contain completed QWS days only. Do not infer missing days as zero.
		$period = "u.usage_day>='".$start."' AND u.usage_day<='".$end."' AND u.has_data=1 AND u.entity IN (".$scopeSql.')';
		$aggregate = 'SELECT u.entity,u.fk_vehicle,COUNT(*) AS known_days,SUM(u.distance) AS distance,SUM(u.trip_count) AS trips,'
			.'SUM(CASE WHEN u.distance>0 OR u.trip_count>0 THEN 1 ELSE 0 END) AS active_days'
			.' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_usage AS u WHERE '.$period.' GROUP BY u.entity,u.fk_vehicle';
		$base .= ' LEFT JOIN ('.$aggregate.') AS a ON a.entity=v.entity AND a.fk_vehicle=v.rowid';
		$select = 'v.rowid,v.entity,v.ref,v.registration_number,v.label,'.$state.' AS association,a.known_days,a.distance,a.trips,a.active_days';
		$gps = LmdbVehicleQuartixConfig::can($user, 'location');
		if ($gps) {
			$base .= ' LEFT JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_position AS p ON p.entity=v.entity AND p.fk_vehicle=v.rowid';
			$select .= ',p.event_date,p.fetched_at,p.location,p.non_tracking';
		}
		$sorts = array('vehicle' => 'v.ref', 'entity' => 'v.entity', 'association' => 'association', 'coverage' => 'a.known_days', 'distance' => 'a.distance', 'trips' => 'a.trips', 'active' => 'a.active_days');
		if ($gps) $sorts['position'] = 'p.event_date';
		$sort = $sorts[$sort] ?? 'v.ref'; $order = $order === 'DESC' ? 'DESC' : 'ASC';
		$totals = $this->rows('SELECT COUNT(*) AS vehicles,COUNT(a.known_days) AS known_vehicles,SUM(a.distance) AS distance,SUM(a.trips) AS trips,SUM(a.known_days) AS known_days,SUM(a.active_days) AS active_days'.$base.$where)[0];
		$rows = $this->rows('SELECT '.$select.$base.$where.' ORDER BY '.$sort.' '.$order.',v.rowid ASC LIMIT '.max(1, min(1000, $limit)).' OFFSET '.max(0, $offset));
		$comparison = $this->rows('SELECT v.ref,a.distance'.$base.$where.' AND a.known_days IS NOT NULL ORDER BY a.distance DESC,v.rowid LIMIT 20');
		// Same cohort filters apply to both charts, independently of the current list page.
		$cohort = 'SELECT v.rowid'.$base.$where;
		$daily = $this->rows('SELECT u.usage_day,SUM(u.distance) AS distance,COUNT(*) AS known_vehicles FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_usage AS u WHERE '.$period.' AND u.fk_vehicle IN ('.$cohort.') GROUP BY u.usage_day ORDER BY u.usage_day');
		$jobs = $this->rows('SELECT j.entity,j.methodename,j.status,s.last_attempt,s.last_success,s.last_error FROM '.MAIN_DB_PREFIX.'cronjob AS j'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_job AS s ON s.entity=j.entity AND s.job_kind=j.methodename'
			." WHERE j.entity IN (".$scopeSql.") AND j.classesname='/lmdbvehiclemanagement/class/lmdbvehiclequartixcron.class.php' AND j.objectname='LmdbVehicleQuartixCron' AND j.methodename IN ('positions','odometer','usage','trips') ORDER BY j.entity,j.methodename");
		return array('rows' => $rows, 'total' => (int) $totals->vehicles, 'totals' => $totals, 'daily' => $daily, 'comparison' => $comparison, 'jobs' => $jobs,
			'expected' => 1 + (int) LmdbVehicleQuartixRules::day($start)->diff(LmdbVehicleQuartixRules::day($end))->days);
	}
}
