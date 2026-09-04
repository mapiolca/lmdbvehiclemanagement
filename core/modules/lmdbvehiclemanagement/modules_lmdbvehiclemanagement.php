<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

/** Required discovery contract for FormFile::showdocuments('lmdbvehiclemanagement'). */
abstract class ModelePDFLmdbvehiclemanagement extends CommonDocGenerator
{
	/**
	 * @param DoliDB $db
	 * @param int $maxfilenamelength
	 * @return array<string,string>|int
	 */
	public static function liste_modeles($db, $maxfilenamelength = 0)
	{
		return getListOfModels($db, 'lmdbvehicle', $maxfilenamelength);
	}
}
