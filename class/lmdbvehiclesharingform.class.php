<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Keep the native Multicompany widget and DAO preselection. Its existing-object
 * removal callback loads htdocs/<element>/class/<element>.class.php (MC 21/22),
 * which cannot resolve external objects. The module POST method checks revocation.
 * Render with an id-less copy; restore the real id only for native preselection.
 * Loaded only after LmdbVehicleSharing::available().
 */
class LmdbVehicleSharingForm extends ActionsMulticompany
{
	/** @var int */
	public $sharingObjectId = 0;

	/** @inheritdoc */
	public function multiselectEntitiesForGranularity($element, $elementid = null, $onlyselected = false, $parentelement = null, $parentelementid = null, $selected = null)
	{
		return parent::multiselectEntitiesForGranularity($element, $this->sharingObjectId, $onlyselected, $parentelement, $parentelementid, $selected);
	}
}
