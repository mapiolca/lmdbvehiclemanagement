<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Native import adapter creating regulatory control drafts through the object API. */
class LmdbVehicleRegulatoryControlImport
{
	/** @var DoliDB */ public $db;
	/** @var string */ public $error = '';
	/** @var array<int,string> */ public $errors = array();

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @param array<int,mixed> $record Native import row
	 * @param array<int,string> $fieldMapping Source-to-target mapping
	 * @param string $importId Native import id
	 * @param User $user Import author
	 * @param bool $runTriggers True for the effective import step
	 * @return int<-1,max>
	 */
	public function createDraftFromNativeRow($record, $fieldMapping, $importId, User $user, $runTriggers)
	{
		global $conf, $langs;

		$langs->loadLangs(array('main', 'errors', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
		$values = array();
		foreach ($fieldMapping as $sourceColumn => $targetField) {
			$sourceIndex = ((int) $sourceColumn) - 1;
			if ($sourceIndex < 0 || !array_key_exists($sourceIndex, $record)) continue;
			$field = preg_replace('/^[^.]+\./', '', (string) $targetField);
			if (is_string($field) && $field !== '') $values[$field] = $this->cellValue($record[$sourceIndex]);
		}

		$vehicleId = $this->resolveVehicle($this->value($values, 'fk_vehicle'), (int) $conf->entity);
		$ruleId = $this->resolveRule($this->value($values, 'fk_rule'), (int) $conf->entity);
		if ($vehicleId <= 0 || $ruleId <= 0) return -1;
		$requirementId = $this->resolveRequirement($vehicleId, $ruleId, (int) $conf->entity);
		if ($requirementId <= 0) return -1;

		dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleregulatorycontrol.class.php');
		$control = new LmdbVehicleRegulatoryControl($this->db);
		$control->entity = (int) $conf->entity;
		$control->fk_vehicle = $vehicleId;
		$control->fk_requirement = $requirementId;
		$control->fk_rule = $ruleId;
		$control->control_kind = $this->value($values, 'control_kind');
		$control->control_date = $this->parseDate($this->value($values, 'control_date'));
		$control->document_ref = $this->nullableValue($values, 'document_ref');
		$control->observations = $this->nullableValue($values, 'observations');
		$control->import_key = $importId !== '' ? substr($importId, 0, 14) : null;
		$provider = $this->value($values, 'fk_soc_provider');
		if ($provider !== '') $control->fk_soc_provider = $this->resolveThirdParty($provider, (int) $conf->entity);
		$resultCode = $this->value($values, 'result_code');
		if ($resultCode !== '') $control->result_code = $this->resolveResultCode($resultCode);
		$officialDate = $this->value($values, 'official_valid_until');
		if ($officialDate !== '') $control->official_valid_until = $this->parseDate($officialDate);
		if ($this->error !== '') return -1;

		$control->context['trigger_reason'] = 'import';
		$result = $control->create($user, $runTriggers ? 0 : 1);
		if ($result <= 0) {
			$this->error = $control->error !== '' ? $control->error : $langs->trans('Error');
			$this->errors = !empty($control->errors) ? $control->errors : array($this->error);
			return -1;
		}

		return $result;
	}

	/** @param mixed $cell @return mixed */
	private function cellValue($cell)
	{
		if (!is_array($cell)) return $cell;
		foreach (array('val', 'value', 'imported_value', 'raw') as $key) if (array_key_exists($key, $cell)) return $cell[$key];
		return '';
	}

	/** @param array<string,mixed> $values @param string $key @return string */
	private function value($values, $key)
	{
		return isset($values[$key]) && is_scalar($values[$key]) ? trim((string) $values[$key]) : '';
	}

	/** @param array<string,mixed> $values @param string $key @return ?string */
	private function nullableValue($values, $key)
	{
		$value = $this->value($values, $key);
		return $value !== '' ? $value : null;
	}

	/** @param string $value @return int */
	private function parseDate($value)
	{
		$timestamp = dol_stringtotime($value, 1);
		if ($value === '' || $timestamp === false || (int) $timestamp <= 0) {
			$this->setError('RegulatoryControlImportInvalidDate');
			return 0;
		}
		return (int) $timestamp;
	}

	/** @param string $value @param int $entity @return int */
	private function resolveVehicle($value, $entity)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle WHERE entity = '.$entity;
		$sql .= " AND (ref = '".$this->db->escape($value)."' OR registration_number = '".$this->db->escape(strtoupper($value))."') LIMIT 1";
		return $this->singleId($sql, 'RegulatoryControlImportInvalidVehicle');
	}

	/** @param string $value @param int $entity @return int */
	private function resolveRule($value, $entity)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule WHERE active = 1';
		$sql .= ' AND entity IN ('.getEntity('lmdbvehicleregulatoryrule').") AND code = '".$this->db->escape($value)."' ORDER BY entity = ".$entity.' DESC LIMIT 1';
		return $this->singleId($sql, 'RegulatoryControlImportInvalidRule');
	}

	/** @return int */
	private function resolveRequirement($vehicleId, $ruleId, $entity)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement WHERE entity = '.$entity.' AND fk_vehicle = '.$vehicleId.' AND fk_rule = '.$ruleId.' AND active = 1 LIMIT 1';
		return $this->singleId($sql, 'RegulatoryControlImportMissingRequirement');
	}

	/** @return int */
	private function resolveThirdParty($value, $entity)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity IN ('.getEntity('societe').") AND (rowid = ".((int) $value)." OR nom = '".$this->db->escape($value)."' OR code_client = '".$this->db->escape($value)."' OR code_fournisseur = '".$this->db->escape($value)."') LIMIT 1";
		return $this->singleId($sql, 'RegulatoryControlImportInvalidProvider');
	}

	/** @return string */
	private function resolveResultCode($value)
	{
		$sql = 'SELECT code FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_control_result WHERE active = 1 AND entity IN ('.getEntity('c_lmdbvehiclemanagement_control_result').") AND (code = '".$this->db->escape($value)."' OR label = '".$this->db->escape($value)."') LIMIT 1";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return '';
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($row)) {
			$this->setError('RegulatoryControlImportInvalidResult');
			return '';
		}
		return (string) $row->code;
	}

	/** @param string $sql @param string $errorKey @return int */
	private function singleId($sql, $errorKey)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return 0;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($row)) {
			$this->setError($errorKey);
			return 0;
		}
		return (int) $row->rowid;
	}

	/** @param string $message @return void */
	private function setError($message)
	{
		$this->error = $message;
		$this->errors = array($message);
	}
}
