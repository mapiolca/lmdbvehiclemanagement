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
		return 'AA-123-BB';
	}

	/** @param LmdbVehicle $object Vehicle @return bool */
	public function canBeActivated($object)
	{
		return true;
	}

	/** @param LmdbVehicle $object Vehicle @return string|int<-1,0> */
	public function getNextValue($object)
	{
		$registration = LmdbVehicle::normalizeRegistrationNumber((string) $object->registration_number);
		if ($registration === '') {
			$this->error = 'RegistrationNumberRequiredForReference';
			return -1;
		}

		return $registration;
	}
}
