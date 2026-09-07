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

	/** @var bool Whether the last successful row updated an existing vehicle. */
	public $updated = false;

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
	 * @param array<string,int> $fields Target field to zero-based record position
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
	 * @param array<string,int> $fields Target field to zero-based record position
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
	 * @param array<string,int> $fields Target field to zero-based record position
	 * @param int $index Current column
	 * @return string
	 */
	public function getRegistrationReference(&$record, $fields, $index)
	{
		$fieldIndex = $fields['t.registration_number'] ?? null;
		if (!is_int($fieldIndex) || !array_key_exists($fieldIndex, $record)) {
			return '';
		}
		$value = $record[$fieldIndex];
		if (is_array($value)) {
			foreach (array('val', 'value', 'imported_value', 'raw') as $key) {
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
		$identifier = trim((string) $id);
		$value = ctype_digit($identifier) && (int) $identifier > 0
			? (string) ((int) $identifier)
			: trim($code !== '' ? $code : ($label !== '' ? $label : $identifier));
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
	 * Virtual import fields use dictionary codes and units, never installation-specific ids.
	 * Duplicate shared codes are kept together and rejected if ambiguous for the energy.
	 *
	 * @return array<string,array{label:string,unit:string,options:array<int,array{code:string,label:string,unit:string,unit_code:string,energy_ids:array<int,int>}>}>
	 */
	public function getCapacityImportFields()
	{
		global $langs;
		$langs->loadLangs(array('lmdbvehiclemanagement@lmdbvehiclemanagement'));
		dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumable.class.php');
		$dictionary = new LmdbVehicleConsumable($this->db);
		$options = $dictionary->getCapacityOptions();
		if ($dictionary->error !== '') {
			$this->error = $dictionary->error;
			return array();
		}
		$fields = array();
		foreach ($options as $id => $option) {
			// Encode unusual custom codes while leaving standard codes readable.
			$code = preg_match('/^[A-Z][A-Z0-9_]*$/D', $option['code']) ? $option['code'] : 'x'.bin2hex($option['code']);
			$unit = preg_match('/^[A-Z][A-Z0-9_]*$/D', $option['unit_code']) ? $option['unit_code'] : 'x'.bin2hex($option['unit_code']);
			$field = 't.capacity_'.$code.'_'.$unit;
			if (!isset($fields[$field])) {
				$fields[$field] = array(
					'label' => $langs->transnoentitiesnoconv('ConsumableCapacity', $option['label']).' ('.$option['unit'].')',
					'unit' => $option['unit'],
					'options' => array(),
				);
			}
			$fields[$field]['options'][$id] = $option;
		}
		return $fields;
	}

	/**
	 * Validate capacities before any write. Empty cells preserve values; zero removes one capacity.
	 *
	 * @param array<string,mixed> $values Mapped row values without table aliases
	 * @param int $energyId Effective vehicle energy after the import merge
	 * @return array<int,float>|false Consumable ids and values, or false on validation error
	 */
	private function getImportedCapacities($values, $energyId)
	{
		global $langs;
		$requested = array();
		foreach ($values as $field => $value) {
			if (strpos($field, 'capacity_') === 0 && $this->stringValue($values, $field) !== '') {
				$requested[$field] = $this->stringValue($values, $field);
			}
		}
		if (empty($requested)) return array();
		$fields = $this->getCapacityImportFields();
		if ($this->error !== '') {
			$this->setImportError($this->error);
			return false;
		}
		$capacities = array();
		foreach ($requested as $field => $value) {
			if (!isset($fields['t.'.$field])) {
				$this->setImportError($langs->trans('VehicleImportUnknownCapacity', $field));
				return false;
			}
			$definition = $fields['t.'.$field];
			$matches = array();
			foreach ($definition['options'] as $id => $option) {
				if ($energyId > 0 && in_array($energyId, $option['energy_ids'], true)) $matches[] = $id;
			}
			if (count($matches) !== 1) {
				$this->setImportError($langs->trans('VehicleImportIncompatibleCapacity', $definition['label']));
				return false;
			}
			// Reject text/units, exponents and malformed numbers before price2num normalization.
			if (!preg_match('/^\+?(?:[0-9]+|[0-9]{1,3}(?:[ \x{00A0}\x{202F}][0-9]{3})+)(?:[.,][0-9]+)?$/uD', $value)) {
				$this->setImportError($langs->trans('VehicleImportInvalidCapacity', $definition['label'], $value));
				return false;
			}
			$number = price2num(str_replace(array("\xc2\xa0", "\xe2\x80\xaf", ' '), '', $value));
			if (!is_numeric($number) || !is_finite((float) $number) || (float) $number < 0) {
				$this->setImportError($langs->trans('VehicleImportInvalidCapacity', $definition['label'], $value));
				return false;
			}
			$capacities[$matches[0]] = (float) $number;
		}
		return $capacities;
	}

	/**
	 * Create one vehicle through its business object from a native import row.
	 *
	 * @param array<int,mixed> $record Native import row
	 * @param array<int,string> $fieldMapping One-based source column to target field
	 * @param string $importId Native import identifier
	 * @param User $user Import author
	 * @param bool $runTriggers True only for the real import step
	 * @param list<string> $updateKeys Native target fields used together to match an existing vehicle
	 * @return int<-1,max> Created vehicle id, -1 on error
	 */
	public function createVehicleFromNativeRow($record, $fieldMapping, $importId, User $user, $runTriggers, $updateKeys = array())
	{
		global $conf, $langs;

		dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
		$langs->loadLangs(array('main', 'errors', 'companies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
		$this->error = '';
		$this->errors = array();
		$values = array();
		// CSV records start at zero; native XLSX records start at one.
		$recordPositionBase = array_key_exists(0, $record) ? 0 : 1;
		foreach ($fieldMapping as $sourceColumn => $targetField) {
			$sourceIndex = ((int) $sourceColumn) - 1 + $recordPositionBase;
			if ($sourceIndex < 0 || !array_key_exists($sourceIndex, $record)) {
				continue;
			}
			$fieldName = preg_replace('/^[^.]+\./', '', (string) $targetField);
			if (!is_string($fieldName) || $fieldName === '') {
				continue;
			}
			$values[$fieldName] = $this->getImportCellValue($record[$sourceIndex]);
		}

		$this->updated = false;
		$existing = null;
		if (!empty($updateKeys)) {
			$where = array();
			foreach ($updateKeys as $key) {
				if (!in_array($key, array('t.registration_number', 't.vin'), true)) {
					return $this->setImportError($langs->trans('VehicleImportInvalidUpdateKey'));
				}
				$field = substr($key, 2);
				$value = $this->stringValue($values, $field);
				if ($value === '') return $this->setImportError($langs->trans('VehicleImportEmptyUpdateKey'));
				if ($field === 'registration_number') $value = LmdbVehicle::normalizeRegistrationNumber($value);
				$where[] = $field." = '".$this->db->escape($value)."'";
			}
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle WHERE entity = '.((int) $conf->entity).' AND '.implode(' AND ', $where).' LIMIT 2';
			$resql = $this->db->query($sql);
			if (!$resql) return $this->setImportError($this->db->lasterror());
			$match = $this->db->fetch_object($resql);
			$duplicate = $this->db->fetch_object($resql);
			$this->db->free($resql);
			if (is_object($duplicate)) return $this->setImportError($langs->trans('VehicleImportAmbiguousUpdateKey'));
			if (is_object($match)) {
				$existing = new LmdbVehicle($this->db);
				if ($existing->fetch((int) $match->rowid) <= 0 || (int) $existing->entity !== (int) $conf->entity) {
					return $this->setImportError($langs->trans('ErrorRecordNotFound'));
				}
			}
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

		if ($existing !== null) {
			$existing->oldcopy = clone $existing;
			// Empty and unmapped cells preserve existing data during enrichment.
			foreach ($values as $field => $value) {
				if ($this->stringValue($values, $field) !== '' && property_exists($vehicle, $field) && !in_array($field, array('id', 'rowid', 'entity', 'status', 'ref'), true)) {
					$existing->{$field} = $vehicle->{$field};
				}
			}
			$vehicle = $existing;
		}
		$capacities = $this->getImportedCapacities($values, !empty($vehicle->fk_energy) ? (int) $vehicle->fk_energy : 0);
		if ($capacities === false) return -1;
		$vehicle->context['trigger_reason'] = 'import';
		$result = $vehicle->saveFromImport($user, $capacities, $existing !== null, $runTriggers ? 0 : 1);
		$this->updated = $existing !== null && $result > 0;
		if ($result <= 0) {
			$this->error = $vehicle->error !== '' ? $langs->trans($vehicle->error) : $langs->trans('Error');
			$this->errors = array();
			foreach ($vehicle->errors as $message) {
				$this->errors[] = $langs->trans($message);
			}
			if (empty($this->errors)) {
				$this->errors = array($this->error);
			}
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
