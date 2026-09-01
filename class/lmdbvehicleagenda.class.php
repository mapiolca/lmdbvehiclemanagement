<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Central Agenda CRUD matrix for the module business objects.
 */
class LmdbVehicleAgenda
{
	/**
	 * Return the business objects whose CRUD triggers are exposed to Agenda.
	 *
	 * @return array<string,array{elementtype:string,class_file:string,class_name:string,trigger_prefix:string}>
	 */
	public static function getObjectDefinitions()
	{
		return array(
			'vehicle' => array(
				'elementtype' => 'lmdbvehicle@lmdbvehiclemanagement',
				'class_file' => 'class/lmdbvehicle.class.php',
				'class_name' => 'LmdbVehicle',
				'trigger_prefix' => 'LMDBVEHICLEMANAGEMENT_VEHICLE',
			),
			'assignment' => array(
				'elementtype' => 'lmdbvehicleassignment@lmdbvehiclemanagement',
				'class_file' => 'class/lmdbvehicleassignment.class.php',
				'class_name' => 'LmdbVehicleAssignment',
				'trigger_prefix' => 'LMDBVEHICLEMANAGEMENT_ASSIGNMENT',
			),
			'odometer' => array(
				'elementtype' => 'lmdbvehicleodometerreading@lmdbvehiclemanagement',
				'class_file' => 'class/lmdbvehicleodometerreading.class.php',
				'class_name' => 'LmdbVehicleOdometerReading',
				'trigger_prefix' => 'LMDBVEHICLEMANAGEMENT_ODOMETER',
			),
			'consumption' => array(
				'elementtype' => 'lmdbvehicleconsumption@lmdbvehiclemanagement',
				'class_file' => 'class/lmdbvehicleconsumption.class.php',
				'class_name' => 'LmdbVehicleConsumption',
				'trigger_prefix' => 'LMDBVEHICLEMANAGEMENT_CONSUMPTION',
			),
			'event' => array(
				'elementtype' => 'lmdbvehicleevent@lmdbvehiclemanagement',
				'class_file' => 'class/lmdbvehicleevent.class.php',
				'class_name' => 'LmdbVehicleEvent',
				'trigger_prefix' => 'LMDBVEHICLEMANAGEMENT_EVENT',
			),
			'insurance_contract' => array(
				'elementtype' => 'lmdbinsurancecontract@lmdbvehiclemanagement',
				'class_file' => 'class/lmdbvehicleinsurancecontract.class.php',
				'class_name' => 'LmdbVehicleInsuranceContract',
				'trigger_prefix' => 'LMDBVEHICLEMANAGEMENT_INSURANCE_CONTRACT',
			),
			'insurance_certificate' => array(
				'elementtype' => 'lmdbinsurancecertificate@lmdbvehiclemanagement',
				'class_file' => 'class/lmdbvehicleinsurancecertificate.class.php',
				'class_name' => 'LmdbVehicleInsuranceCertificate',
				'trigger_prefix' => 'LMDBVEHICLEMANAGEMENT_INSURANCE_CERTIFICATE',
			),
		);
	}

	/**
	 * Expand the object matrix into the 21 CRUD triggers.
	 *
	 * @return array<string,array{elementtype:string,class_file:string,class_name:string,trigger_prefix:string,operation:string}>
	 */
	public static function getTriggerDefinitions()
	{
		$definitions = array();
		foreach (self::getObjectDefinitions() as $objectDefinition) {
			foreach (array('CREATE', 'UPDATE', 'DELETE') as $operation) {
				$code = $objectDefinition['trigger_prefix'].'_'.$operation;
				$definitions[$code] = $objectDefinition + array('operation' => $operation);
			}
		}

		return $definitions;
	}
}
