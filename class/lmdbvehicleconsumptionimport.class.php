<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumption.class.php');

/** CSV import service that persists every row through the consumption object. */
class LmdbVehicleConsumptionImport
{
	/** @var DoliDB */ private $db;
	/** @var string */ public $error = '';
	/** @var array<int,string> */ public $errors = array();
	/** @var int */ public $imported = 0;

	/** @param DoliDB $db Database */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Import a UTF-8 CSV file with a header row.
	 *
	 * @param string $path Uploaded temporary file
	 * @param User $user Author
	 * @return int<-1,max> Imported row count
	 */
	public function importFile($path, User $user)
	{
		$handle = fopen($path, 'rb');
		if ($handle === false) {
			$this->error = 'ErrorFailedToOpenFile';
			return -1;
		}
		$firstLine = fgets($handle);
		if (!is_string($firstLine)) {
			fclose($handle);
			$this->error = 'ConsumptionImportEmptyFile';
			return -1;
		}
		$delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
		rewind($handle);
		$headers = fgetcsv($handle, 0, $delimiter);
		if (!is_array($headers)) {
			fclose($handle);
			$this->error = 'ConsumptionImportInvalidHeader';
			return -1;
		}
		$headers = array_map(static function ($header) { return strtolower(trim((string) $header, "\xEF\xBB\xBF \t\n\r\0\x0B")); }, $headers);
		$required = array('registration_number', 'consumable_code', 'reading_date', 'odometer_km', 'quantity', 'total_ttc');
		foreach ($required as $column) {
			if (!in_array($column, $headers, true)) {
				fclose($handle);
				$this->error = 'ConsumptionImportMissingColumn';
				$this->errors[] = $column;
				return -1;
			}
		}
		$lineNumber = 1;
		while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
			$lineNumber++;
			if (count($values) === 1 && trim((string) $values[0]) === '') continue;
			$values = array_pad($values, count($headers), '');
			$row = array_combine($headers, array_slice($values, 0, count($headers)));
			if (!is_array($row)) {
				$this->errors[] = 'Line '.$lineNumber.': ConsumptionImportInvalidRow';
				continue;
			}
			$object = $this->buildObject($row);
			if (!is_object($object)) {
				$this->errors[] = 'Line '.$lineNumber.': '.$this->error;
				continue;
			}
			$result = $object->create($user);
			if ($result <= 0) {
				$this->errors[] = 'Line '.$lineNumber.': '.($object->error ?: 'Error');
				continue;
			}
			$this->imported++;
		}
		fclose($handle);
		return $this->imported;
	}

	/** @param array<string,string> $row Row @return LmdbVehicleConsumption|null */
	private function buildObject($row)
	{
		global $conf;

		$registration = strtoupper(trim((string) $row['registration_number']));
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle';
		$sql .= " WHERE registration_number = '".$this->db->escape($registration)."' AND entity = ".((int) $conf->entity);
		$resql = $this->db->query($sql);
		$vehicle = $resql ? $this->db->fetch_object($resql) : false;
		if ($resql) $this->db->free($resql);
		if (!is_object($vehicle)) { $this->error = 'InvalidVehicle'; return null; }
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable';
		$sql .= " WHERE code = '".$this->db->escape(strtoupper(trim((string) $row['consumable_code'])))."' AND active = 1";
		$sql .= ' AND entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').')';
		$resql = $this->db->query($sql);
		$consumable = $resql ? $this->db->fetch_object($resql) : false;
		if ($resql) $this->db->free($resql);
		if (!is_object($consumable)) { $this->error = 'InvalidConsumable'; return null; }
		$date = DateTime::createFromFormat('Y-m-d H:i:s', trim((string) $row['reading_date']));
		if (!$date) $date = DateTime::createFromFormat('Y-m-d H:i', trim((string) $row['reading_date']));
		if (!$date) { $this->error = 'InvalidDateRange'; return null; }
		$object = new LmdbVehicleConsumption($this->db);
		$object->fk_vehicle = (int) $vehicle->rowid;
		$object->fk_consumable = (int) $consumable->rowid;
		$object->reading_date = $date->getTimestamp();
		$object->odometer_km = (float) price2num((string) $row['odometer_km']);
		$object->quantity = (float) price2num((string) $row['quantity']);
		$object->total_ttc = (float) price2num((string) $row['total_ttc'], 'MT');
		$object->oil_reference = isset($row['oil_reference']) && trim((string) $row['oil_reference']) !== '' ? trim((string) $row['oil_reference']) : null;
		$object->reading_kind = isset($row['reading_kind']) && trim((string) $row['reading_kind']) !== '' ? trim((string) $row['reading_kind']) : 'standard';
		$object->reading_reason = isset($row['reading_reason']) && trim((string) $row['reading_reason']) !== '' ? trim((string) $row['reading_reason']) : null;
		$object->description = isset($row['description']) && trim((string) $row['description']) !== '' ? trim((string) $row['description']) : null;
		if (isset($row['driver_login']) && trim((string) $row['driver_login']) !== '') {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE login = \''.$this->db->escape(trim((string) $row['driver_login'])).'\' AND statut = 1';
			$resql = $this->db->query($sql);
			$driver = $resql ? $this->db->fetch_object($resql) : false;
			if ($resql) $this->db->free($resql);
			if (!is_object($driver)) { $this->error = 'InvalidDriver'; return null; }
			$object->fk_user_driver = (int) $driver->rowid;
		}
		return $object;
	}
}
