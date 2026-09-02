<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Computed values required by the native Dolibarr import profile.
 */
class LmdbVehicleImport
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @param array<int,array<string,mixed>> $record Import row
	 * @param array<int,string> $fields Mapped fields
	 * @param int $index Current column
	 * @return int
	 */
	public function getCurrentEntityId(&$record, $fields, $index)
	{
		global $conf;

		return (int) $conf->entity;
	}

	/**
	 * @param array<int,array<string,mixed>> $record Import row
	 * @param array<int,string> $fields Mapped fields
	 * @param int $index Current column
	 * @return string
	 */
	public function getCreationDate(&$record, $fields, $index)
	{
		return $this->db->idate(dol_now());
	}

	/**
	 * Derive a hidden imported reference from the mapped registration field.
	 *
	 * Dolibarr import rows have used both scalar and structured cells across
	 * supported versions, so the value is narrowed without relying on internals.
	 *
	 * @param array<int,mixed> $record Import row
	 * @param array<int,string> $fields Mapped fields
	 * @param int $index Current column
	 * @return string
	 */
	public function getRegistrationReference(&$record, $fields, $index)
	{
		$fieldIndex = array_search('t.registration_number', $fields, true);
		if ($fieldIndex === false || !array_key_exists($fieldIndex, $record)) {
			return '';
		}
		$value = $record[$fieldIndex];
		if (is_array($value)) {
			foreach (array('value', 'imported_value', 'raw') as $key) {
				if (isset($value[$key]) && is_scalar($value[$key])) {
					$value = $value[$key];
					break;
				}
			}
		}
		if (!is_scalar($value)) {
			return '';
		}

		return strtoupper(trim((string) $value));
	}

	/**
	 * Resolve an asset type code or label for the native import converter.
	 *
	 * @param int|string $id Row id or code
	 * @param string $code Optional code
	 * @param string $label Optional label
	 * @return int<-1,max>
	 */
	public function fetchAssetType($id = 0, $code = '', $label = '')
	{
		$value = (int) $id > 0 ? (string) ((int) $id) : trim($code !== '' ? $code : $label);
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_asset_type WHERE active = 1 AND entity IN ('.getEntity('c_lmdbvehiclemanagement_asset_type').')';
		if (ctype_digit($value)) $sql .= ' AND rowid = '.((int) $value);
		else $sql .= " AND (code = '".$this->db->escape($value)."' OR label = '".$this->db->escape($value)."')";
		$sql .= ' ORDER BY rowid LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return is_object($row) ? (int) $row->rowid : 0;
	}

	/**
	 * Create one vehicle through its business object from a native import row.
	 *
	 * @param array<int,mixed> $record Native import row
	 * @param array<int,string> $fieldMapping One-based source column to target field
	 * @param string $importId Native import identifier
	 * @param User $user Import author
	 * @param bool $runTriggers True only for the real import step
	 * @return int<-1,max> Created vehicle id, -1 on error
	 */
	public function createVehicleFromNativeRow($record, $fieldMapping, $importId, User $user, $runTriggers)
	{
		global $conf, $langs;

		dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
		$langs->loadLangs(array('main', 'errors', 'companies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
		$values = array();
		foreach ($fieldMapping as $sourceColumn => $targetField) {
			$sourceIndex = ((int) $sourceColumn) - 1;
			if ($sourceIndex < 0 || !array_key_exists($sourceIndex, $record)) {
				continue;
			}
			$fieldName = preg_replace('/^[^.]+\./', '', (string) $targetField);
			if (!is_string($fieldName) || $fieldName === '') {
				continue;
			}
			$values[$fieldName] = $this->getImportCellValue($record[$sourceIndex]);
		}

		$vehicle = new LmdbVehicle($this->db);
		$vehicle->entity = (int) $conf->entity;
		$vehicle->ref = $this->stringValue($values, 'ref');
		if ($vehicle->ref === '(PROV)') {
			$vehicle->ref = '';
		}
		$vehicle->registration_number = $this->stringValue($values, 'registration_number');
		$vehicle->vin = $this->nullableStringValue($values, 'vin');
		$vehicle->label = $this->stringValue($values, 'label');
		$vehicle->brand = $this->nullableStringValue($values, 'brand');
		$vehicle->model = $this->nullableStringValue($values, 'model');
		$vehicle->vehicle_version = $this->nullableStringValue($values, 'vehicle_version');
		$vehicle->eu_category = $this->nullableStringValue($values, 'eu_category');
		$vehicle->national_genre = $this->nullableStringValue($values, 'national_genre');
		$vehicle->regulatory_territory = $this->stringValue($values, 'regulatory_territory') ?: 'FR_METRO';
		$vehicle->ownership_type = $this->nullableStringValue($values, 'ownership_type');
		$vehicle->description = $this->nullableStringValue($values, 'description');
		$vehicle->import_key = $importId !== '' ? substr($importId, 0, 14) : null;
		$assetTypeValue = $this->stringValue($values, 'fk_asset_type');
		if ($assetTypeValue !== '') {
			$assetTypeId = $this->fetchAssetType($assetTypeValue);
			if ($assetTypeId <= 0) return $this->setImportError($langs->trans('VehicleImportInvalidAssetType', $assetTypeValue));
			$vehicle->fk_asset_type = $assetTypeId;
		}

		$energyValue = $this->stringValue($values, 'fk_energy');
		if ($energyValue !== '') {
			$energy = new LmdbVehicleEnergy($this->db);
			$energyResult = is_numeric($energyValue) ? $energy->fetch((int) $energyValue) : $energy->fetch(0, $energyValue);
			if ($energyResult === 0 && !is_numeric($energyValue)) {
				$energyResult = $energy->fetch(0, '', $energyValue);
			}
			if ($energyResult <= 0) {
				return $this->setImportError($langs->trans('VehicleImportInvalidEnergy', $energyValue));
			}
			$vehicle->fk_energy = (int) $energy->id;
		}

		$ownerValue = $this->stringValue($values, 'fk_soc_owner');
		if ($ownerValue !== '') {
			require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
			$owner = new Societe($this->db);
			$ownerIdentifier = preg_replace('/^(?:id|ref):/i', '', $ownerValue);
			$ownerIdentifier = is_string($ownerIdentifier) ? trim($ownerIdentifier) : '';
			$ownerResult = is_numeric($ownerIdentifier) ? $owner->fetch((int) $ownerIdentifier) : $owner->fetch(0, $ownerIdentifier);
			if ($ownerResult <= 0) {
				return $this->setImportError($langs->trans('VehicleImportInvalidOwner', $ownerValue));
			}
			$vehicle->fk_soc_owner = (int) $owner->id;
		}

		foreach (array('construction_date', 'first_registration_date', 'commissioning_date') as $dateField) {
			$dateValue = $this->stringValue($values, $dateField);
			if ($dateValue === '') {
				continue;
			}
			$timestamp = dol_stringtotime($dateValue, 1);
			if ($timestamp === false || ((int) $timestamp === 0 && !preg_match('/^1970[-\/]0?1[-\/]0?1(?:\s|$)/', $dateValue))) {
				return $this->setImportError($langs->trans('VehicleImportInvalidDate', $dateValue));
			}
			$vehicle->{$dateField} = (int) $timestamp;
		}

		$rangeValue = $this->stringValue($values, 'wltp_range_km');
		if ($rangeValue !== '') {
			if (!preg_match('/^[\s\x{00A0}\d.,+\-]+$/u', $rangeValue)) {
				return $this->setImportError($langs->trans('VehicleImportInvalidNumber', $rangeValue));
			}
			$normalizedRange = price2num($rangeValue);
			if ($normalizedRange === '' || !is_numeric($normalizedRange)) {
				return $this->setImportError($langs->trans('VehicleImportInvalidNumber', $rangeValue));
			}
			$vehicle->wltp_range_km = (float) $normalizedRange;
		}
		foreach (array('gvw_kg', 'gcw_kg') as $numericField) {
			$value = $this->stringValue($values, $numericField);
			if ($value !== '') {
				$normalized = price2num($value);
				if ($normalized === '' || !is_numeric($normalized)) return $this->setImportError($langs->trans('VehicleImportInvalidNumber', $value));
				$vehicle->{$numericField} = (float) $normalized;
			}
		}
		$seats = $this->stringValue($values, 'seats');
		if ($seats !== '') {
			if (!ctype_digit($seats)) return $this->setImportError($langs->trans('VehicleImportInvalidNumber', $seats));
			$vehicle->seats = (int) $seats;
		}

		$vehicle->context['trigger_reason'] = 'import';
		$result = $vehicle->create($user, $runTriggers ? 0 : 1);
		if ($result <= 0) {
			$this->error = $vehicle->error !== '' ? $vehicle->error : $langs->trans('Error');
			$this->errors = !empty($vehicle->errors) ? $vehicle->errors : array($this->error);
			return -1;
		}

		return $result;
	}

	/**
	 * @param mixed $cell Native import cell
	 * @return mixed
	 */
	private function getImportCellValue($cell)
	{
		if (!is_array($cell)) {
			return $cell;
		}
		foreach (array('val', 'value', 'imported_value', 'raw') as $key) {
			if (array_key_exists($key, $cell)) {
				return $cell[$key];
			}
		}

		return '';
	}

	/**
	 * @param array<string,mixed> $values Mapped values
	 * @param string $key Field name
	 * @return string
	 */
	private function stringValue($values, $key)
	{
		return isset($values[$key]) && is_scalar($values[$key]) ? trim((string) $values[$key]) : '';
	}

	/**
	 * @param array<string,mixed> $values Mapped values
	 * @param string $key Field name
	 * @return ?string
	 */
	private function nullableStringValue($values, $key)
	{
		$value = $this->stringValue($values, $key);

		return $value !== '' ? $value : null;
	}

	/**
	 * @param string $message Error message
	 * @return -1
	 */
	private function setImportError($message)
	{
		$this->error = $message;
		$this->errors = array($message);

		return -1;
	}
}
