<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/core/modules/lmdbvehiclemanagement/modules_lmdbvehicle.php');

/** Vehicle numbering based on the normalized registration number. */
class mod_lmdbvehicle_registration extends ModeleNumRefLmdbVehicle
{
	/** @var string */
	public $version = 'dolibarr';
	/** @var string */
	public $prefix = '';
	/** @var string */
	public $error = '';
	/** @var string */
	public $name = 'registration';
	/** @var int */
	public $position = 30;

	/** @param Translate $langs Languages @return string */
	public function info($langs)
	{
		return $langs->trans('RegistrationVehicleNumRefModelDesc');
	}

	/** @return string */
	public function getExample()
	{
		return 'AA-123-BB / MAT2609-0001';
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

		$registration = LmdbVehicle::normalizeRegistrationNumber((string) $object->registration_number);
		if ($registration !== '') return $registration;

		$date = !empty($object->date_creation) ? (int) $object->date_creation : dol_now();
		$period = dol_print_date($date, '%y%m');
		$sql = 'SELECT MAX(CAST(SUBSTRING(t.ref FROM 9) AS SIGNED)) AS maxref FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS t';
		$sql .= " WHERE t.ref LIKE '".$db->escape('MAT'.$period)."-%' AND t.entity IN (".getEntity('lmdbvehiclenumber', 1, $object).')';
		$resql = $db->query($sql);
		if (!$resql) { $this->error = $db->lasterror(); return -1; }
		$row = $db->fetch_object($resql);
		$max = is_object($row) ? (int) $row->maxref : 0;
		$db->free($resql);
		return 'MAT'.$period.'-'.($max >= 9999 ? (string) ($max + 1) : sprintf('%04u', $max + 1));
	}
}
