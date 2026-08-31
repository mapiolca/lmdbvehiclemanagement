<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Aggregated consumption statistics produced without per-row SQL queries. */
class LmdbVehicleConsumptionStats
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';

	/** @param DoliDB $db Database */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @param array{vehicle_id?:int,user_id?:int,consumable_id?:int,entity_ids?:array<int,int>,date_start?:int,date_end?:int,category?:string} $filters Filters
	 * @return array<int,array<string,int|float|string|null>>|int<-1,-1>
	 */
	public function fetchRows($filters = array())
	{
		$sql = 'SELECT t.rowid, t.entity, t.ref, t.fk_vehicle, t.fk_consumable, t.category_snapshot, t.unit_snapshot,';
		$sql .= ' COALESCE(t.fk_user_driver, t.fk_user_creat) AS fk_user_driver, t.quantity, t.total_ttc, t.currency_snapshot, t.oil_reference,';
		$sql .= ' r.reading_date, r.odometer_km, r.reading_kind, c.code AS consumable_code, c.label AS consumable_label,';
		$sql .= ' v.ref AS vehicle_ref, v.registration_number, v.label AS vehicle_label, v.wltp_range_km,';
		$sql .= ' cap.capacity, u.login AS driver_login, u.firstname AS driver_firstname, u.lastname AS driver_lastname';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumption AS t';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading AS r ON r.rowid = t.fk_odometer_reading AND r.entity = t.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable AS c ON c.rowid = t.fk_consumable';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid = t.fk_vehicle AND v.entity = t.entity';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_capacity AS cap ON cap.entity = t.entity AND cap.fk_vehicle = t.fk_vehicle AND cap.fk_consumable = t.fk_consumable';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user AS u ON u.rowid = COALESCE(t.fk_user_driver, t.fk_user_creat)';
		$sql .= ' WHERE t.entity IN ('.getEntity('lmdbvehicleconsumption').')';
		if (!empty($filters['vehicle_id'])) $sql .= ' AND t.fk_vehicle = '.((int) $filters['vehicle_id']);
		if (!empty($filters['user_id'])) $sql .= ' AND COALESCE(t.fk_user_driver, t.fk_user_creat) = '.((int) $filters['user_id']);
		if (!empty($filters['consumable_id'])) $sql .= ' AND t.fk_consumable = '.((int) $filters['consumable_id']);
		if (!empty($filters['category']) && in_array($filters['category'], array('fuel', 'additive'), true)) $sql .= " AND t.category_snapshot = '".$this->db->escape($filters['category'])."'";
		if (!empty($filters['date_start'])) $sql .= " AND r.reading_date >= '".$this->db->idate((int) $filters['date_start'])."'";
		if (!empty($filters['date_end'])) $sql .= " AND r.reading_date <= '".$this->db->idate((int) $filters['date_end'])."'";
		if (!empty($filters['entity_ids'])) {
			$ids = array_map('intval', $filters['entity_ids']);
			$sql .= ' AND t.entity IN ('.implode(',', $ids).')';
		}
		$sql .= ' ORDER BY t.fk_vehicle, t.fk_consumable, t.unit_snapshot, r.reading_date, t.rowid';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$rows = array();
		while (is_object($row = $this->db->fetch_object($resql))) {
			$rows[] = array(
				'id' => (int) $row->rowid, 'entity' => (int) $row->entity, 'ref' => (string) $row->ref,
				'vehicle_id' => (int) $row->fk_vehicle, 'consumable_id' => (int) $row->fk_consumable,
				'category' => (string) $row->category_snapshot, 'unit' => (string) $row->unit_snapshot,
				'driver_id' => $row->fk_user_driver !== null ? (int) $row->fk_user_driver : null,
				'quantity' => (float) $row->quantity, 'total_ttc' => (float) $row->total_ttc, 'currency' => (string) $row->currency_snapshot,
				'oil_reference' => (string) $row->oil_reference, 'date' => $this->db->jdate($row->reading_date),
				'odometer_km' => (float) $row->odometer_km, 'reading_kind' => (string) $row->reading_kind,
				'consumable_code' => (string) $row->consumable_code, 'consumable_label' => (string) $row->consumable_label,
				'vehicle_ref' => (string) $row->vehicle_ref, 'registration_number' => (string) $row->registration_number,
				'vehicle_label' => (string) $row->vehicle_label, 'wltp_range_km' => $row->wltp_range_km !== null ? (float) $row->wltp_range_km : null,
				'capacity' => $row->capacity !== null ? (float) $row->capacity : null,
				'driver_name' => trim((string) $row->driver_firstname.' '.(string) $row->driver_lastname) ?: (string) $row->driver_login,
			);
		}
		$this->db->free($resql);
		return $rows;
	}

	/**
	 * @param array<int,array<string,int|float|string|null>> $rows Chronological rows
	 * @return array<string,array<string,int|float|string|null>> Grouped statistics
	 */
	public function summarize($rows)
	{
		$groups = array();
		foreach ($rows as $row) {
			$key = ((int) $row['entity']).':'.((int) $row['vehicle_id']).':'.((int) $row['consumable_id']).':'.(string) $row['unit'].':'.(string) $row['currency'];
			if (!isset($groups[$key])) {
				$groups[$key] = array(
					'entity' => $row['entity'], 'vehicle_id' => $row['vehicle_id'], 'vehicle_ref' => $row['vehicle_ref'], 'registration_number' => $row['registration_number'],
					'consumable_id' => $row['consumable_id'], 'consumable_label' => $row['consumable_label'], 'category' => $row['category'], 'unit' => $row['unit'], 'currency' => $row['currency'],
					'count' => 0, 'total_quantity' => 0.0, 'total_cost' => 0.0, 'total_distance' => 0.0, 'interval_quantity' => 0.0,
					'interval_days' => 0.0, 'interval_count' => 0, 'peak_quantity' => 0.0, 'peak_unit_price' => 0.0,
					'peak_consumption_100' => null, 'last_date' => null, 'last_odometer' => null, 'average_capacity_percent' => null,
					'capacity_percent_sum' => 0.0, 'capacity_percent_count' => 0, 'wltp_range_km' => $row['wltp_range_km'],
					'oil_reference' => '', 'excluded_intervals' => 0, '_previous_date' => null, '_previous_odometer' => null,
				);
			}
			$group =& $groups[$key];
			$quantity = (float) $row['quantity'];
			$total = (float) $row['total_ttc'];
			$unitPrice = $quantity > 0 ? $total / $quantity : 0.0;
			$group['count'] = (int) $group['count'] + 1;
			$group['total_quantity'] = (float) $group['total_quantity'] + $quantity;
			$group['total_cost'] = (float) $group['total_cost'] + $total;
			$group['peak_quantity'] = max((float) $group['peak_quantity'], $quantity);
			$group['peak_unit_price'] = max((float) $group['peak_unit_price'], $unitPrice);
			$group['last_date'] = $row['date'];
			$group['last_odometer'] = $row['odometer_km'];
			if ((string) $row['oil_reference'] !== '') $group['oil_reference'] = (string) $row['oil_reference'];
			if ($row['capacity'] !== null && (float) $row['capacity'] > 0) {
				$group['capacity_percent_sum'] = (float) $group['capacity_percent_sum'] + $quantity / (float) $row['capacity'] * 100;
				$group['capacity_percent_count'] = (int) $group['capacity_percent_count'] + 1;
			}
			if ($group['_previous_date'] !== null && $group['_previous_odometer'] !== null) {
				$distance = (float) $row['odometer_km'] - (float) $group['_previous_odometer'];
				$days = ((int) $row['date'] - (int) $group['_previous_date']) / 86400;
				if ($distance > 0 && (string) $row['reading_kind'] === 'standard') {
					$group['total_distance'] = (float) $group['total_distance'] + $distance;
					$group['interval_quantity'] = (float) $group['interval_quantity'] + $quantity;
					$group['interval_days'] = (float) $group['interval_days'] + max(0, $days);
					$group['interval_count'] = (int) $group['interval_count'] + 1;
					$intervalConsumption = $quantity / $distance * 100;
					$group['peak_consumption_100'] = $group['peak_consumption_100'] === null ? $intervalConsumption : max((float) $group['peak_consumption_100'], $intervalConsumption);
				} else {
					$group['excluded_intervals'] = (int) $group['excluded_intervals'] + 1;
				}
			}
			$group['_previous_date'] = $row['date'];
			$group['_previous_odometer'] = $row['odometer_km'];
			unset($group);
		}

		foreach ($groups as &$group) {
			$count = (int) $group['count'];
			$intervalCount = (int) $group['interval_count'];
			$group['average_quantity'] = $count > 0 ? (float) $group['total_quantity'] / $count : 0.0;
			$group['weighted_unit_price'] = (float) $group['total_quantity'] > 0 ? (float) $group['total_cost'] / (float) $group['total_quantity'] : 0.0;
			$group['average_distance'] = $intervalCount > 0 ? (float) $group['total_distance'] / $intervalCount : null;
			$group['average_days'] = $intervalCount > 0 ? (float) $group['interval_days'] / $intervalCount : null;
			$group['consumption_100'] = (float) $group['total_distance'] > 0 ? (float) $group['interval_quantity'] / (float) $group['total_distance'] * 100 : null;
			$group['average_capacity_percent'] = (int) $group['capacity_percent_count'] > 0 ? (float) $group['capacity_percent_sum'] / (int) $group['capacity_percent_count'] : null;
			$group['wltp_passage_ratio'] = $group['wltp_range_km'] !== null && (float) $group['wltp_range_km'] > 0 && $group['average_distance'] !== null ? (float) $group['average_distance'] / (float) $group['wltp_range_km'] * 100 : null;
			unset($group['_previous_date'], $group['_previous_odometer'], $group['capacity_percent_sum'], $group['capacity_percent_count']);
		}
		unset($group);
		return $groups;
	}
}
