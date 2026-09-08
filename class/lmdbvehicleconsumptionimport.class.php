<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/lmdbvehicleconsumption.class.php';
require_once __DIR__.'/lmdbvehicle.class.php';

/** Native import boundary for historical consumptions, without bank operations. */
class LmdbVehicleConsumptionImport
{
	/** @var DoliDB */ private $db;
	/** @var string */ public $error = '';
	/** @var list<string> */ public $errors = array();
	/** @var bool */ public $updated = false;
	/** @var bool */ public $unchanged = false;

	/** @param DoliDB $db Database */
	public function __construct($db) { $this->db = $db; }

	/**
	 * The native caller owns the outer simulation transaction.
	 * @param array<int,mixed> $record CSV (zero-based) or XLSX (one-based) cells
	 * @param array<int,string> $mapping One-based source columns to target fields
	 * @param string $importId Import identifier
	 * @param User $user Author
	 * @param bool $runTriggers Effective import with automatic actions
	 * @param list<string> $updateKeys Empty for creation only
	 * @return int Positive on success, -1 on error
	 */
	public function importNativeRow(array $record, array $mapping, $importId, User $user, $runTriggers, array $updateKeys = array())
	{
		global $conf, $langs;
		$this->error = ''; $this->errors = array(); $this->updated = false; $this->unchanged = false;
		$langs->loadLangs(array('main', 'errors', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
		if (!isModEnabled('lmdbvehiclemanagement') || !empty($user->socid)
			|| !$user->hasRight('lmdbvehiclemanagement', 'consumption', 'read')
			|| !$user->hasRight('lmdbvehiclemanagement', 'consumption', 'import')) return $this->fail('NotEnoughPermissions');
		$values = array();
		$base = array_key_exists(0, $record) ? 0 : 1;
		$allowed = array('ref', 'fk_vehicle', 'fk_consumable', 'reading_date', 'odometer_km', 'quantity', 'total_ttc', 'fk_user_driver', 'description', 'oil_reference', 'reading_kind', 'reading_reason');
		foreach ($mapping as $column => $target) {
			if (!is_string($target) || substr($target, 0, 2) !== 't.' || !in_array(substr($target, 2), $allowed, true)) return $this->fail('ConsumptionImportInvalidRow');
			$cell = $record[(int) $column - 1 + $base] ?? null;
			if (is_array($cell)) $cell = $cell['val'] ?? $cell['value'] ?? $cell['imported_value'] ?? $cell['raw'] ?? null;
			if ($cell !== null && !is_scalar($cell)) return $this->fail('ConsumptionImportInvalidRow');
			$value = trim((string) $cell);
			if ($value !== '') $values[substr($target, 2)] = $value;
		}
		if (!$values) { $this->unchanged = true; return 1; }
		$keys = array_values(array_unique($updateKeys));
		sort($keys);
		$tupleKeys = array('t.fk_consumable', 't.fk_vehicle', 't.reading_date');
		if ($keys && $keys !== array('t.ref') && $keys !== $tupleKeys) return $this->fail('ConsumptionImportInvalidUpdateKey');
		foreach ($keys as $key) {
			if (!isset($values[substr($key, 2)])) return $this->fail('ConsumptionImportInvalidUpdateKey');
		}
		if ($keys && !$user->hasRight('lmdbvehiclemanagement', 'consumption', 'write')) return $this->fail('NotEnoughPermissions');
		$entity = (int) $conf->entity;
		$referenceWhere = isset($values['ref']) ? "ref = '".$this->db->escape($values['ref'])."'" : '';
		$existingId = 0;
		if ($referenceWhere !== '') {
			$existingId = $this->findUnique('lmdbvehiclemanagement_consumption', $referenceWhere.' AND entity = '.$entity, false);
			if ($existingId < 0) return -1;
		}
		$object = new LmdbVehicleConsumption($this->db);
		if ($existingId > 0 && ($object->fetch($existingId) <= 0 || (int) $object->entity !== $entity)) return $this->fail('RecordNotFound');
		$oldVehicle = $existingId > 0 ? (int) $object->fk_vehicle : 0;
		$vehicleId = $oldVehicle;
		if (isset($values['fk_vehicle'])) {
			$identifier = $this->db->escape($values['fk_vehicle']);
			$registration = $this->db->escape(LmdbVehicle::normalizeRegistrationNumber($values['fk_vehicle']));
			$vehicleId = $this->findUnique('lmdbvehiclemanagement_vehicle', "(ref = '".$identifier."' OR registration_number = '".$registration."') AND entity = ".$entity);
			if ($vehicleId <= 0) return -1;
		}
		if ($vehicleId <= 0) return $this->fail('InvalidVehicle');
		if (isset($values['fk_consumable'])) {
			$id = $this->findUnique('c_lmdbvehiclemanagement_consumable', "code = '".$this->db->escape($values['fk_consumable'])."' AND active = 1 AND entity IN (".$this->db->sanitize(getEntity('c_lmdbvehiclemanagement_consumable')).')');
			if ($id <= 0) return -1;
			$values['fk_consumable'] = $id;
		}
		if (isset($values['fk_user_driver'])) {
			$id = $this->findUnique('user', "login = '".$this->db->escape($values['fk_user_driver'])."' AND statut = 1 AND entity IN (".$this->db->sanitize(getEntity('user')).')');
			if ($id <= 0) return -1;
			$values['fk_user_driver'] = $id;
		}
		if (isset($values['reading_date'])) {
			$date = $values['reading_date'];
			if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/D', $date, $parts)
				|| !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
				|| (int) ($parts[4] ?? 0) > 23 || (int) ($parts[5] ?? 0) > 59 || (int) ($parts[6] ?? 0) > 59) return $this->fail('InvalidDateRange');
			$timestamp = dol_stringtotime(str_replace('T', ' ', $date), 1);
			if (!$timestamp) return $this->fail('InvalidDateRange');
			$values['reading_date'] = $timestamp;
		}
		foreach (array('odometer_km', 'quantity', 'total_ttc') as $field) {
			if (!isset($values[$field])) continue;
			if (!preg_match('/^\+?(?:[0-9]+|[0-9]{1,3}(?:[ \x{00A0}\x{202F}][0-9]{3})+)(?:[.,][0-9]+)?$/uD', $values[$field])) return $this->fail('ConsumptionImportInvalidNumber');
			$number = price2num(str_replace(array("\xc2\xa0", "\xe2\x80\xaf", ' '), '', $values[$field]), $field === 'total_ttc' ? 'MT' : '');
			if (!is_numeric($number) || !is_finite((float) $number)) return $this->fail('ConsumptionImportInvalidNumber');
			$values[$field] = (float) $number;
		}
		$this->db->begin();
		try {
			// Same lock order as odometer writes; lock both vehicles when moving a record.
			$vehicles = array_unique(array_filter(array($oldVehicle, $vehicleId)));
			sort($vehicles, SORT_NUMERIC);
			foreach ($vehicles as $id) {
				if ($this->findUnique('lmdbvehiclemanagement_vehicle', 'rowid = '.((int) $id).' AND entity = '.$entity, true, true) <= 0) throw new RuntimeException($this->error);
			}
			if ($referenceWhere !== '') {
				$lockedId = $this->findUnique('lmdbvehiclemanagement_consumption', $referenceWhere.' AND entity = '.$entity, false, true);
				if ($lockedId < 0) throw new RuntimeException($this->error);
				if ($lockedId !== $existingId) throw new RuntimeException($langs->trans('ConsumptionImportConcurrentChange'));
				if ($existingId > 0 && ($this->loadLocked($object, $existingId, $entity) <= 0 || (int) $object->fk_vehicle !== $oldVehicle)) throw new RuntimeException($langs->trans('ConsumptionImportConcurrentChange'));
			}
			if ($existingId === 0 && $keys !== array('t.ref')) {
				if (!isset($values['reading_date'], $values['fk_consumable'])) throw new RuntimeException($langs->trans('ConsumptionImportInvalidUpdateKey'));
				$existingId = $this->findTuple($entity, $vehicleId, (int) $values['fk_consumable'], (int) $values['reading_date']);
				if ($existingId < 0) throw new RuntimeException($this->error);
				if ($existingId > 0 && $this->loadLocked($object, $existingId, $entity) <= 0) throw new RuntimeException($this->error);
			}
			if ($existingId > 0 && !$keys) throw new RuntimeException($langs->trans('ConsumptionImportDuplicate'));
			if ($existingId > 0 && $keys === $tupleKeys && ((int) $object->fk_vehicle !== $vehicleId || (int) $object->fk_consumable !== (int) $values['fk_consumable'] || (int) $object->reading_date !== (int) $values['reading_date'])) throw new RuntimeException($langs->trans('ConsumptionImportInvalidUpdateKey'));
			if ($existingId > 0 && $keys === $tupleKeys && isset($values['ref']) && $values['ref'] !== $object->ref) throw new RuntimeException($langs->trans('ConsumptionImportDuplicate'));
			$before = clone $object;
			$object->entity = $entity;
			$object->fk_vehicle = $vehicleId;
			foreach ($values as $field => $value) {
				if ($field === 'fk_vehicle' || ($field === 'ref' && $existingId > 0)) continue;
				$object->{$field} = $value;
				if (isset($object->fields[$field]) && !$object->validateField($object->fields, $field, (string) $value)) throw new RuntimeException($object->getFieldError($field));
			}
			if ($existingId === 0 && !isset($values['odometer_km'])) throw new RuntimeException($langs->trans('ConsumptionImportMileageRequired'));
			if (!$object->reading_date || !$object->fk_consumable || $object->odometer_km === null) throw new RuntimeException($langs->trans('ConsumptionImportInvalidRow'));
			$duplicateId = $this->findTuple($entity, $vehicleId, (int) $object->fk_consumable, (int) $object->reading_date);
			if ($duplicateId < 0) throw new RuntimeException($this->error);
			if ($duplicateId > 0 && $duplicateId !== $existingId) throw new RuntimeException($langs->trans('ConsumptionImportDuplicate'));
			$changed = $existingId === 0;
			foreach (array_diff($allowed, array('ref')) as $field) {
				if ((string) $before->{$field} !== (string) $object->{$field}) $changed = true;
			}
			if (!$changed) {
				$this->unchanged = true;
				$this->db->commit();
				return $existingId;
			}
			if ($existingId > 0 && !empty($object->fk_payment_various)) throw new RuntimeException($langs->trans('ConsumptionImportOdLinked'));
			$object->import_key = $importId;
			$object->context['import_mode'] = 'historical_without_od';
			$result = $existingId > 0 ? $object->update($user, $runTriggers ? 0 : 1) : $object->create($user, $runTriggers ? 0 : 1);
			if ($result <= 0) {
				$this->errors = $object->errors;
				throw new RuntimeException($object->error ?: $langs->trans('Error'));
			}
			$this->updated = $existingId > 0;
			$this->db->commit();
			return $result;
		} catch (Throwable $exception) {
			$this->db->rollback();
			return $this->fail($exception->getMessage());
		}
	}

	/** Load current locked values, not a stale InnoDB consistent-read snapshot.
	 * @param LmdbVehicleConsumption $object Target @param int $id Id @param int $entity Owner
	 * @return int
	 */
	private function loadLocked($object, $id, $entity)
	{
		$res = $this->db->query('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumption WHERE rowid = '.$id.' AND entity = '.$entity.' FOR UPDATE');
		if (!$res) return $this->fail($this->db->lasterror());
		$row = $this->db->fetch_object($res);
		$this->db->free($res);
		if (!is_object($row)) return $this->fail('RecordNotFound');
		$object->setVarsFromFetchObj($row);
		$res = $this->db->query('SELECT reading_date, odometer_km, reading_kind, reason FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading WHERE rowid = '.((int) $object->fk_odometer_reading).' AND entity = '.$entity." AND source = 'consumption' FOR UPDATE");
		if (!$res) return $this->fail($this->db->lasterror());
		$row = $this->db->fetch_object($res);
		$this->db->free($res);
		if (!is_object($row)) return $this->fail('InvalidOdometerReading');
		$object->reading_date = $this->db->jdate($row->reading_date);
		$object->odometer_km = (float) $row->odometer_km;
		$object->reading_kind = (string) $row->reading_kind;
		$object->reading_reason = $row->reason;
		if (empty($object->fk_user_driver)) $object->fk_user_driver = (int) $object->fk_user_creat;
		return 1;
	}

	/** @param int $entity Entity @param int $vehicle Vehicle @param int $consumable Consumable @param int $date Date @return int */
	private function findTuple($entity, $vehicle, $consumable, $date)
	{
		$where = 't.entity = '.$entity.' AND t.fk_vehicle = '.$vehicle.' AND t.fk_consumable = '.$consumable;
		$where .= " AND r.reading_date = '".$this->db->idate($date)."'";
		$sql = 'SELECT t.rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumption AS t';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading AS r ON r.rowid = t.fk_odometer_reading AND r.entity = t.entity';
		return $this->readUnique($sql.' WHERE '.$where.' LIMIT 2 FOR UPDATE', false);
	}

	/** Resolve existing relations or matches, rejecting ambiguous shared codes.
	 * @param string $table Internal table name @param string $where Validated SQL predicates
	 * @param bool $required Missing row is an error @param bool $lock Lock in active transaction
	 * @return int Id, zero when optional and absent, -1 on failure
	 */
	private function findUnique($table, $where, $required = true, $lock = false)
	{
		return $this->readUnique('SELECT rowid FROM '.MAIN_DB_PREFIX.$table.' WHERE '.$where.' LIMIT 2'.($lock ? ' FOR UPDATE' : ''), $required);
	}

	/** @param string $sql Scoped matching query @param bool $required Missing row is an error @return int */
	private function readUnique($sql, $required)
	{
		$res = $this->db->query($sql);
		if (!$res) return $this->fail($this->db->lasterror());
		$count = $this->db->num_rows($res);
		$row = $this->db->fetch_object($res);
		$this->db->free($res);
		if ($count > 1) return $this->fail('ConsumptionImportAmbiguous');
		if (!is_object($row)) return $required ? $this->fail('ConsumptionImportUnknownReference') : 0;
		return (int) $row->rowid;
	}

	/** @param string $message Translation key or business error @return int */
	private function fail($message)
	{
		global $langs;
		$this->error = $langs->trans($message);
		return -1;
	}
}
