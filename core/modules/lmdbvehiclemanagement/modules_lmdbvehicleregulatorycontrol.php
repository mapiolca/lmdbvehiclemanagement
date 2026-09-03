<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';

/** Parent class for regulatory-control numbering models. */
abstract class ModeleNumRefLmdbVehicleRegulatoryControl extends CommonNumRefGenerator
{
	/** @return string */
	abstract public function getExample();

	/** @param LmdbVehicleRegulatoryControl $object Control @return string|int<-1,0> */
	abstract public function getNextValue($object);
}
