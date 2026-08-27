<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/core/modules/lmdbvehiclemanagement/modules_lmdbvehicleevent.php');

/** Standard vehicle event numbering. */
class mod_lmdbvehicleevent_standard extends ModeleNumRefLmdbVehicleEvent
{
	/** @var string */
	public $version = 'dolibarr';
	/** @var string */
	public $prefix = 'EVT';
	/** @var string */
	public $error = '';
	/** @var string */
	public $name = 'standard';
	/** @var int */
	public $position = 40;

	/** @param Translate $langs Languages @return string */
	public function info($langs)
	{
		return $langs->trans('SimpleVehicleEventNumRefModelDesc');
	}

	/** @return string */
	public function getExample()
	{
		return 'EVT2608-0001';
	}

	/** @param LmdbVehicleEvent $object Event @return bool */
	public function canBeActivated($object)
	{
		return true;
	}

	/** @param LmdbVehicleEvent $object Event @return string|int<-1,0> */
	public function getNextValue($object)
	{
		global $db;
		$position = strlen($this->prefix) + 6;
		$sql = 'SELECT MAX(CAST(SUBSTRING(t.ref FROM '.((int) $position).') AS SIGNED)) AS maxref';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_event AS t';
		$sql .= " WHERE t.ref LIKE '".$db->escape($this->prefix)."____-%'";
		$sql .= ' AND t.entity = '.((int) $object->entity);
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

