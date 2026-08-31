<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/core/modules/lmdbvehiclemanagement/modules_lmdbvehicleconsumption.php');

/** Standard consumption numbering. */
class mod_lmdbvehicleconsumption_standard extends ModeleNumRefLmdbVehicleConsumption
{
	/** @var string */
	public $version = 'dolibarr';
	/** @var string */
	public $prefix = 'CON';
	/** @var string */
	public $error = '';
	/** @var string */
	public $name = 'standard';
	/** @var int */
	public $position = 40;

	/** @param Translate $langs Languages @return string */
	public function info($langs)
	{
		return $langs->trans('SimpleConsumptionNumRefModelDesc');
	}

	/** @return string */
	public function getExample()
	{
		return 'CON2608-0001';
	}

	/** @param LmdbVehicleConsumption $object Consumption @return bool */
	public function canBeActivated($object)
	{
		return true;
	}

	/** @param LmdbVehicleConsumption $object Consumption @return string|int<-1,0> */
	public function getNextValue($object)
	{
		global $db;

		$date = !empty($object->date_creation) ? (int) $object->date_creation : dol_now();
		$period = dol_print_date($date, '%y%m');
		$position = strlen($this->prefix) + 6;
		$sql = 'SELECT MAX(CAST(SUBSTRING(t.ref FROM '.((int) $position).') AS SIGNED)) AS maxref';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumption AS t';
		$sql .= " WHERE t.ref LIKE '".$db->escape($this->prefix.$period)."-%'";
		$sql .= ' AND t.entity IN ('.getEntity('lmdbvehicleconsumptionnumber', 1, $object).')';
		$resql = $db->query($sql);
		if (!$resql) {
			$this->error = $db->lasterror();
			return -1;
		}
		$row = $db->fetch_object($resql);
		$max = is_object($row) ? (int) $row->maxref : 0;
		$db->free($resql);
		$number = $max >= 9999 ? (string) ($max + 1) : sprintf('%04u', $max + 1);

		return $this->prefix.$period.'-'.$number;
	}
}
