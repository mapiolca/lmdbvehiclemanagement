<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Translate and display errors returned by a module business object.
 *
 * Object methods may expose either a translated sentence or a stable
 * translation key. This boundary keeps persisted/business code independent
 * from the language selected by the current interface.
 *
 * @param object $object Object exposing error and errors properties
 * @return void
 */
function lmdbVehicleManagementSetObjectErrors($object)
{
	global $langs;

	$messages = array();
	if (isset($object->error) && is_string($object->error) && $object->error !== '') {
		$messages[] = $langs->trans($object->error);
	}
	if (isset($object->errors) && is_array($object->errors)) {
		foreach ($object->errors as $error) {
			if (is_string($error) && $error !== '') {
				$messages[] = $langs->trans($error);
			}
		}
	}
	$messages = array_values(array_unique($messages));
	if (!empty($messages)) {
		setEventMessages('', $messages, 'errors');
	}
}

/**
 * Build administration tabs.
 *
 * @return array<int,array{0:string,1:string,2:string}>
 */
function lmdbVehicleManagementAdminPrepareHead()
{
	global $langs;

	$langs->loadLangs(array('admin', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));

	$head = array();
	$h = 0;
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/admin/setup.php', 1), $langs->trans('Settings'), 'settings');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/admin/compatibility.php', 1), $langs->trans('Compatibility'), 'compatibility');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/admin/about.php', 1), $langs->trans('About'), 'about');

	return $head;
}

/**
 * Build vehicle tabs in native order.
 *
 * @param LmdbVehicle $object Vehicle
 * @return array<int,array{0:string,1:string,2:string}>
 */
function lmdbVehiclePrepareHead($object)
{
	global $langs, $user;

	$langs->loadLangs(array('companies', 'agenda', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
	$id = (int) $object->id;
	$head = array();
	$h = 0;
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_card.php', 1).'?id='.$id, $langs->trans('Card'), 'card');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_assignment.php', 1).'?id='.$id, $langs->trans('VehicleAssignments'), 'assignments');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_odometer.php', 1).'?id='.$id, $langs->trans('OdometerReadings'), 'odometer');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_history.php', 1).'?id='.$id, $langs->trans('VehicleHistory'), 'history');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_note.php', 1).'?id='.$id, $langs->trans('Notes'), 'notes');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_document.php', 1).'?id='.$id, $langs->trans('Documents'), 'documents');
	if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
		$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_agenda.php', 1).'?id='.$id, $langs->trans('EventsAgenda'), 'agenda');
	}

	return $head;
}

/**
 * Print the common native banner used by every vehicle tab.
 *
 * @param LmdbVehicle $object Vehicle
 * @return void
 */
function lmdbVehiclePrintBanner($object)
{
	global $db, $langs;

	$linkback = '<a href="'.dol_buildpath('/lmdbvehiclemanagement/vehicle_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
	$moreHtmlRef = '<div class="refidno">'.dol_escape_htmltag($object->registration_number).' — '.dol_escape_htmltag($object->label);
	if (isModEnabled('multicompany') && !empty($object->entity)) {
		$entityLabel = (string) $object->entity;
		$resEntity = $db->query('SELECT label FROM '.MAIN_DB_PREFIX.'entity WHERE rowid = '.((int) $object->entity));
		if ($resEntity && is_object($entityRow = $db->fetch_object($resEntity)) && !empty($entityRow->label)) {
			$entityLabel = (string) $entityRow->label;
		}
		if ($resEntity) {
			$db->free($resEntity);
		}
		$moreHtmlRef .= '<br><div class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityLabel).'</span></div>';
	}
	$moreHtmlRef .= '</div>';

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $moreHtmlRef);
}

/**
 * Build event tabs.
 *
 * @param LmdbVehicleEvent $object Vehicle event
 * @return array<int,array{0:string,1:string,2:string}>
 */
function lmdbVehicleEventPrepareHead($object)
{
	global $langs;

	$langs->loadLangs(array('companies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
	$id = (int) $object->id;

	return array(
		array(dol_buildpath('/lmdbvehiclemanagement/vehicleevent_card.php', 1).'?id='.$id, $langs->trans('Card'), 'card'),
		array(dol_buildpath('/lmdbvehiclemanagement/vehicleevent_note.php', 1).'?id='.$id, $langs->trans('Notes'), 'notes'),
		array(dol_buildpath('/lmdbvehiclemanagement/vehicleevent_document.php', 1).'?id='.$id, $langs->trans('Documents'), 'documents'),
	);
}
