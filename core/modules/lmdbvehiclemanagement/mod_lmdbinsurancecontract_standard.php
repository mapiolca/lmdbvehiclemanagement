<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/core/modules/lmdbvehiclemanagement/modules_lmdbinsurancecontract.php');

/** Standard insurance contract numbering. */
class mod_lmdbinsurancecontract_standard extends ModeleNumRefLmdbInsuranceContract
{
	/** @var string */ public $version = 'dolibarr';
	/** @var string */ public $prefix = 'ASS';
	/** @var string */ public $error = '';
	/** @var string */ public $name = 'standard';
	/** @var int */ public $position = 40;

	/** @param Translate $langs Languages @return string */
	public function info($langs)
	{
		return $langs->trans('SimpleInsuranceContractNumRefModelDesc');
	}

	/** @return string */
	public function getExample()
	{
		return 'ASS2608-0001';
	}

	/** @param LmdbVehicleInsuranceContract $object Contract @return bool */
	public function canBeActivated($object)
	{
		return true;
	}

	/** @param LmdbVehicleInsuranceContract $object Contract @return string|int<-1,0> */
	public function getNextValue($object)
	{
		global $db;

		$position = strlen($this->prefix) + 6;
		$sql = 'SELECT MAX(CAST(SUBSTRING(t.ref FROM '.((int) $position).') AS SIGNED)) AS maxref';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract AS t';
		$sql .= " WHERE t.ref LIKE '".$db->escape($this->prefix)."____-%'";
		$sql .= ' AND t.entity = '.((int) $object->entity);
		$resql = $db->query($sql);
		if (!$resql) {
			$this->error = $db->lasterror();
			return -1;
		}
		$row = $db->fetch_object($resql);
		$max = is_object($row) ? (int) $row->maxref : 0;
		$db->free($resql);
		$date = !empty($object->date_creation) ? (int) $object->date_creation : dol_now();
		$number = $max >= 9999 ? (string) ($max + 1) : sprintf('%04u', $max + 1);

		return $this->prefix.dol_print_date($date, '%y%m').'-'.$number;
	}
}
