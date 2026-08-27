<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/core/modules/lmdbvehiclemanagement/modules_lmdbvehicle.php');

/** Standard vehicle numbering. */
class mod_lmdbvehicle_standard extends ModeleNumRefLmdbVehicle
{
	/** @var string */
	public $version = 'dolibarr';
	/** @var string */
	public $prefix = 'VEH';
	/** @var string */
	public $error = '';
	/** @var string */
	public $name = 'standard';
	/** @var int */
	public $position = 40;

	/** @param Translate $langs Languages @return string */
	public function info($langs)
	{
		return $langs->trans('SimpleVehicleNumRefModelDesc');
	}

	/** @return string */
	public function getExample()
	{
		return 'VEH2608-0001';
	}

	/** @param LmdbVehicle $object Vehicle @return bool */
	public function canBeActivated($object)
	{
		return true;
	}

	/** @param LmdbVehicle $object Vehicle @return string|int<-1,0> */
	public function getNextValue($object)
	{
		global $db;
		$position = strlen($this->prefix) + 6;
		$entityScope = getEntity('lmdbvehiclenumber', 1, $object);
		$sql = 'SELECT MAX(CAST(SUBSTRING(t.ref FROM '.((int) $position).') AS SIGNED)) AS maxref';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS t';
		$sql .= " WHERE t.ref LIKE '".$db->escape($this->prefix)."____-%'";
		$sql .= ' AND t.entity IN ('.$entityScope.')';
		$resql = $db->query($sql);
		if (!$resql) {
			$this->error = $db->lasterror();
			return -1;
		}
		$obj = $db->fetch_object($resql);
		$max = is_object($obj) ? (int) $obj->maxref : 0;
		$db->free($resql);
		$date = !empty($object->date_creation) ? (int) $object->date_creation : dol_now();
		$number = $max >= 9999 ? (string) ($max + 1) : sprintf('%04u', $max + 1);

		return $this->prefix.dol_print_date($date, '%y%m').'-'.$number;
	}
}

