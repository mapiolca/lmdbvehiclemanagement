<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Module CRUD triggers.
 *
 * The native Agenda remains a separate user-managed view. This trigger does
 * not create ActionComm rows, which prevents duplication with the business
 * timeline.
 */
class InterfaceLmdbVehicleManagementTriggers extends DolibarrTriggers
{
	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = 'Les Métiers du Bâtiment';
		$this->description = 'Vehicle management CRUD triggers';
		$this->version = self::VERSIONS['dev'];
		$this->picto = 'car';
	}

	/**
	 * @param string $action Trigger code
	 * @param CommonObject $object Source object
	 * @param User $user User
	 * @param Translate $langs Languages
	 * @param Conf $conf Configuration
	 * @return int
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('lmdbvehiclemanagement')) {
			return 0;
		}

		$actions = array(
			'LMDBVEHICLEMANAGEMENT_VEHICLE_CREATE',
			'LMDBVEHICLEMANAGEMENT_VEHICLE_UPDATE',
			'LMDBVEHICLEMANAGEMENT_VEHICLE_DELETE',
			'LMDBVEHICLEMANAGEMENT_ASSIGNMENT_CREATE',
			'LMDBVEHICLEMANAGEMENT_ASSIGNMENT_UPDATE',
			'LMDBVEHICLEMANAGEMENT_ASSIGNMENT_DELETE',
			'LMDBVEHICLEMANAGEMENT_ODOMETER_CREATE',
			'LMDBVEHICLEMANAGEMENT_ODOMETER_UPDATE',
			'LMDBVEHICLEMANAGEMENT_ODOMETER_DELETE',
			'LMDBVEHICLEMANAGEMENT_EVENT_CREATE',
			'LMDBVEHICLEMANAGEMENT_EVENT_UPDATE',
			'LMDBVEHICLEMANAGEMENT_EVENT_DELETE',
		);
		if (!in_array($action, $actions, true)) {
			return 0;
		}

		dol_syslog(__METHOD__.' action='.$action.' object_id='.((int) $object->id), LOG_INFO);
		return 0;
	}
}

