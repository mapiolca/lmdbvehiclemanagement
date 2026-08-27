<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';

/** Parent class for vehicle numbering models. */
abstract class ModeleNumRefLmdbVehicle extends CommonNumRefGenerator
{
	/** @return string */
	abstract public function getExample();

	/** @param LmdbVehicle $object Vehicle @return string|int<-1,0> */
	abstract public function getNextValue($object);
}

