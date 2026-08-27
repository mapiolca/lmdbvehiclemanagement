<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';

/** Parent class for vehicle event numbering models. */
abstract class ModeleNumRefLmdbVehicleEvent extends CommonNumRefGenerator
{
	/** @return string */
	abstract public function getExample();

	/** @param LmdbVehicleEvent $object Event @return string|int<-1,0> */
	abstract public function getNextValue($object);
}

