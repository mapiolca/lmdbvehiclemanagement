<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Aggregates the vehicle timeline from authoritative source objects.
 *
 * @phpstan-type TimelineEntry array{
 *   date:int,
 *   type:string,
 *   source:string,
 *   source_object:string,
 *   source_id:int,
 *   label:string,
 *   odometer_km:?float,
 *   driver_id:?int,
 *   thirdparty_id:?int,
 *   status:int,
 *   has_documents:bool,
 *   document_count:int
 * }
 */
class LmdbVehicleHistory
{
	/** @var DoliDB */
	private $db;

	/** @var string */
	public $error = '';

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return a paginated chronology without persisting a mirror history.
	 *
	 * @param int $vehicleId Vehicle id
	 * @param list<string> $sources Allowed sources: event, assignment, odometer, insurance
	 * @param int $limit Page size
	 * @param int $offset Offset
	 * @return array<int,TimelineEntry>|int<-1,-1>
	 */
	public function getTimeline($vehicleId, $sources = array(), $limit = 100, $offset = 0)
	{
		global $langs;

		$allowedSources = array('event', 'assignment', 'odometer', 'insurance');
		$sources = array_values(array_intersect($allowedSources, $sources));
		if (empty($sources)) {
			$sources = $allowedSources;
		}

		$queries = array();
		if (in_array('event', $sources, true)) {
			$eventSourceTypes = array(
				'lmdbvehiclemanagement_vehicle_event',
				'lmdbvehiclemanagement_vehicle_event@lmdbvehiclemanagement',
				'lmdbvehicleevent',
				'lmdbvehicleevent@lmdbvehiclemanagement',
			);
			$quotedEventSourceTypes = array();
			foreach ($eventSourceTypes as $eventSourceType) {
				$quotedEventSourceTypes[] = "'".$this->db->escape($eventSourceType)."'";
			}
			$queries[] = "SELECT e.event_date AS event_timestamp, e.event_type AS entry_type, 'event' AS source_code, 'lmdbvehicleevent' AS source_object, e.rowid AS source_id, e.label AS source_label, e.odometer_km, e.fk_user_driver AS driver_id, e.fk_soc AS thirdparty_id, e.status, '' AS driver_name, (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."ecm_files AS ef WHERE ef.src_object_id = e.rowid AND ef.entity = e.entity AND ef.src_object_type IN (".implode(', ', $quotedEventSourceTypes).")) AS document_count FROM ".MAIN_DB_PREFIX."lmdbvehiclemanagement_vehicle_event AS e WHERE e.fk_vehicle = ".((int) $vehicleId)." AND e.entity IN (".getEntity('lmdbvehicle').")";
		}
		if (in_array('assignment', $sources, true)) {
			$queries[] = "SELECT a.date_start AS event_timestamp, a.assignment_type AS entry_type, 'assignment' AS source_code, 'lmdbvehicleassignment' AS source_object, a.rowid AS source_id, '' AS source_label, NULL AS odometer_km, a.fk_user_driver AS driver_id, NULL AS thirdparty_id, a.status, TRIM(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, ''))) AS driver_name, 0 AS document_count FROM ".MAIN_DB_PREFIX."lmdbvehiclemanagement_vehicle_assignment AS a LEFT JOIN ".MAIN_DB_PREFIX."user AS u ON u.rowid = a.fk_user_driver WHERE a.fk_vehicle = ".((int) $vehicleId)." AND a.entity IN (".getEntity('lmdbvehicle').")";
		}
		if (in_array('odometer', $sources, true)) {
			$queries[] = "SELECT o.reading_date AS event_timestamp, o.reading_kind AS entry_type, 'odometer' AS source_code, 'lmdbvehicleodometerreading' AS source_object, o.rowid AS source_id, '' AS source_label, o.odometer_km, NULL AS driver_id, NULL AS thirdparty_id, 1 AS status, '' AS driver_name, 0 AS document_count FROM ".MAIN_DB_PREFIX."lmdbvehiclemanagement_odometer_reading AS o WHERE o.fk_vehicle = ".((int) $vehicleId)." AND o.entity IN (".getEntity('lmdbvehicle').")";
		}
		if (in_array('insurance', $sources, true)) {
			$queries[] = "SELECT cv.date_creation AS event_timestamp, 'contract_linked' AS entry_type, 'insurance' AS source_code, 'lmdbinsurancecontract' AS source_object, c.rowid AS source_id, CONCAT(c.ref, ' — ', c.policy_number) AS source_label, NULL AS odometer_km, NULL AS driver_id, c.fk_soc AS thirdparty_id, c.status, '' AS driver_name, 0 AS document_count FROM ".MAIN_DB_PREFIX."lmdbvehiclemanagement_insurance_contract_vehicle AS cv INNER JOIN ".MAIN_DB_PREFIX."lmdbvehiclemanagement_insurance_contract AS c ON c.rowid = cv.fk_contract AND c.entity = cv.entity WHERE cv.fk_vehicle = ".((int) $vehicleId)." AND cv.entity IN (".getEntity('lmdbvehicle').")";
			$queries[] = "SELECT cert.date_submitted AS event_timestamp, 'certificate_submitted' AS entry_type, 'insurance' AS source_code, 'lmdbinsurancecertificate' AS source_object, cert.rowid AS source_id, c.policy_number AS source_label, NULL AS odometer_km, cert.fk_user_submit AS driver_id, c.fk_soc AS thirdparty_id, cert.status, '' AS driver_name, IF(cert.file_name IS NULL OR cert.file_name = '', 0, 1) AS document_count FROM ".MAIN_DB_PREFIX."lmdbvehiclemanagement_insurance_certificate AS cert INNER JOIN ".MAIN_DB_PREFIX."lmdbvehiclemanagement_insurance_contract AS c ON c.rowid = cert.fk_contract AND c.entity = cert.entity INNER JOIN ".MAIN_DB_PREFIX."lmdbvehiclemanagement_insurance_contract_vehicle AS cv ON cv.fk_contract = cert.fk_contract AND cv.entity = cert.entity WHERE cv.fk_vehicle = ".((int) $vehicleId)." AND (cert.fk_vehicle = ".((int) $vehicleId)." OR cert.fk_vehicle IS NULL) AND cert.date_submitted IS NOT NULL AND cert.entity IN (".getEntity('lmdbvehicle').")";
			$queries[] = "SELECT cert.date_reviewed AS event_timestamp, IF(cert.rejection_reason IS NULL OR cert.rejection_reason = '', 'certificate_validated', 'certificate_rejected') AS entry_type, 'insurance' AS source_code, 'lmdbinsurancecertificate' AS source_object, cert.rowid AS source_id, c.policy_number AS source_label, NULL AS odometer_km, cert.fk_user_review AS driver_id, c.fk_soc AS thirdparty_id, cert.status, '' AS driver_name, IF(cert.file_name IS NULL OR cert.file_name = '', 0, 1) AS document_count FROM ".MAIN_DB_PREFIX."lmdbvehiclemanagement_insurance_certificate AS cert INNER JOIN ".MAIN_DB_PREFIX."lmdbvehiclemanagement_insurance_contract AS c ON c.rowid = cert.fk_contract AND c.entity = cert.entity INNER JOIN ".MAIN_DB_PREFIX."lmdbvehiclemanagement_insurance_contract_vehicle AS cv ON cv.fk_contract = cert.fk_contract AND cv.entity = cert.entity WHERE cv.fk_vehicle = ".((int) $vehicleId)." AND (cert.fk_vehicle = ".((int) $vehicleId)." OR cert.fk_vehicle IS NULL) AND cert.date_reviewed IS NOT NULL AND cert.entity IN (".getEntity('lmdbvehicle').")";
		}

		$sql = 'SELECT timeline.* FROM ('.implode(' UNION ALL ', $queries).') AS timeline';
		$sql .= ' ORDER BY timeline.event_timestamp DESC, timeline.source_id DESC';
		$sql .= $this->db->plimit(max(1, min(500, (int) $limit)), max(0, (int) $offset));
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$entries = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$source = (string) $obj->source_code;
			$label = (string) $obj->source_label;
			$documentCount = (int) $obj->document_count;
			if ($source === 'assignment') {
				$label = $langs->trans('Driver').': '.((string) $obj->driver_name);
			} elseif ($source === 'odometer') {
				$label = price((float) $obj->odometer_km, 0, $langs, 1, -1, -1).' km';
			}

			$entries[] = array(
				'date' => (int) $this->db->jdate($obj->event_timestamp),
				'type' => (string) $obj->entry_type,
				'source' => $source,
				'source_object' => (string) $obj->source_object,
				'source_id' => (int) $obj->source_id,
				'label' => $label,
				'odometer_km' => isset($obj->odometer_km) ? (float) $obj->odometer_km : null,
				'driver_id' => !empty($obj->driver_id) ? (int) $obj->driver_id : null,
				'thirdparty_id' => !empty($obj->thirdparty_id) ? (int) $obj->thirdparty_id : null,
				'status' => (int) $obj->status,
				'has_documents' => $documentCount > 0,
				'document_count' => $documentCount,
			);
		}
		$this->db->free($resql);

		return $entries;
	}

	/**
	 * Count timeline entries for pagination.
	 *
	 * @param int $vehicleId Vehicle id
	 * @param list<string> $sources Allowed sources
	 * @return int<-1,max>
	 */
	public function countTimeline($vehicleId, $sources = array())
	{
		$allowedSources = array('event', 'assignment', 'odometer', 'insurance');
		$sources = array_values(array_intersect($allowedSources, $sources));
		if (empty($sources)) {
			$sources = $allowedSources;
		}

		$queries = array();
		if (in_array('event', $sources, true)) {
			$queries[] = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_event WHERE fk_vehicle = '.((int) $vehicleId).' AND entity IN ('.getEntity('lmdbvehicle').')';
		}
		if (in_array('assignment', $sources, true)) {
			$queries[] = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_assignment WHERE fk_vehicle = '.((int) $vehicleId).' AND entity IN ('.getEntity('lmdbvehicle').')';
		}
		if (in_array('odometer', $sources, true)) {
			$queries[] = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading WHERE fk_vehicle = '.((int) $vehicleId).' AND entity IN ('.getEntity('lmdbvehicle').')';
		}
		if (in_array('insurance', $sources, true)) {
			$queries[] = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle WHERE fk_vehicle = '.((int) $vehicleId).' AND entity IN ('.getEntity('lmdbvehicle').')';
			$queries[] = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_certificate AS cert INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle AS cv ON cv.fk_contract = cert.fk_contract AND cv.entity = cert.entity WHERE cv.fk_vehicle = '.((int) $vehicleId).' AND (cert.fk_vehicle = '.((int) $vehicleId).' OR cert.fk_vehicle IS NULL) AND cert.date_submitted IS NOT NULL AND cert.entity IN ('.getEntity('lmdbvehicle').')';
			$queries[] = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_certificate AS cert INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle AS cv ON cv.fk_contract = cert.fk_contract AND cv.entity = cert.entity WHERE cv.fk_vehicle = '.((int) $vehicleId).' AND (cert.fk_vehicle = '.((int) $vehicleId).' OR cert.fk_vehicle IS NULL) AND cert.date_reviewed IS NOT NULL AND cert.entity IN ('.getEntity('lmdbvehicle').')';
		}
		$sql = 'SELECT SUM(source_count.total) AS total FROM ('.implode(' UNION ALL ', $queries).') AS source_count';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return is_object($obj) ? (int) $obj->total : 0;
	}
}
