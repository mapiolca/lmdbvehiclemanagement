<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';

/** Parent class for consumption numbering models. */
abstract class ModeleNumRefLmdbVehicleConsumption extends CommonNumRefGenerator
{
	/** @return string */
	abstract public function getExample();

	/** @param LmdbVehicleConsumption $object Consumption @return string|int<-1,0> */
	abstract public function getNextValue($object);
}
