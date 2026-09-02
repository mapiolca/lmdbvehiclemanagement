<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Central Agenda CRUD matrix for the module business objects.
 */
class LmdbVehicleAgenda
{
	/**
	 * Return the translated title definition for one business event.
	 *
	 * Keeping this matrix independent from Dolibarr services makes the business
	 * wording testable without creating an Agenda record.
	 *
	 * @param string $element Object element
	 * @param string $operation CREATE, UPDATE or DELETE
	 * @param array<string,mixed> $context Trigger context
	 * @return array{key:string,arguments:list<string>}
	 */
	public static function getMessageDefinition($element, $operation, $context = array())
	{
		$operation = strtoupper($operation);
		$reason = isset($context['trigger_reason']) ? (string) $context['trigger_reason'] : strtolower($operation);
		$newStatus = isset($context['new_status']) ? (int) $context['new_status'] : null;

		if ($element === 'lmdbvehicle') {
			if ($operation === 'CREATE' && $reason === 'import') {
				return array('key' => 'AgendaVehicleImported', 'arguments' => array('vehicle_ref'));
			}
			if ($operation === 'UPDATE' && $reason === 'status_change') {
				$statusKeys = array(
					1 => 'AgendaVehicleValidated',
					2 => 'AgendaVehiclePutInService',
					3 => 'AgendaVehiclePutOutOfService',
					4 => 'AgendaVehicleSold',
				);
				if ($newStatus !== null && isset($statusKeys[$newStatus])) {
					return array('key' => $statusKeys[$newStatus], 'arguments' => array('vehicle_ref'));
				}
			}
			if ($operation === 'UPDATE' && $reason === 'reference_sync') {
				return array('key' => 'AgendaVehicleReferenceSynchronized', 'arguments' => array('vehicle_ref'));
			}
			return self::crudDefinition($operation, 'AgendaVehicleCreated', 'AgendaVehicleUpdated', 'AgendaVehicleDeleted', array('vehicle_ref'));
		}

		if ($element === 'lmdbvehicleassignment') {
			if ($operation === 'UPDATE' && $newStatus !== null) {
				return array(
					'key' => $newStatus === 1 ? 'AgendaAssignmentActivated' : 'AgendaAssignmentDeactivated',
					'arguments' => array('driver_name', 'vehicle_ref'),
				);
			}
			return self::crudDefinition($operation, 'AgendaAssignmentCreated', 'AgendaAssignmentUpdated', 'AgendaAssignmentDeleted', array('driver_name', 'vehicle_ref'));
		}

		if ($element === 'lmdbvehicleodometerreading') {
			return self::crudDefinition($operation, 'AgendaOdometerCreated', 'AgendaOdometerUpdated', 'AgendaOdometerDeleted', array('odometer', 'vehicle_ref'));
		}

		if ($element === 'lmdbvehicleconsumption') {
			return self::crudDefinition($operation, 'AgendaConsumptionCreated', 'AgendaConsumptionUpdated', 'AgendaConsumptionDeleted', array('identifier'));
		}

		if ($element === 'lmdbvehicleevent') {
			if ($operation === 'UPDATE' && $newStatus !== null) {
				$statusKeys = array(
					0 => 'AgendaVehicleEventReopened',
					1 => 'AgendaVehicleEventInProgress',
					2 => 'AgendaVehicleEventClosed',
					9 => 'AgendaVehicleEventCancelled',
				);
				if (isset($statusKeys[$newStatus])) {
					return array('key' => $statusKeys[$newStatus], 'arguments' => array('identifier'));
				}
			}
			return self::crudDefinition($operation, 'AgendaVehicleEventCreated', 'AgendaVehicleEventUpdated', 'AgendaVehicleEventDeleted', array('identifier'));
		}

		if ($element === 'lmdbinsurancecontract') {
			if ($operation === 'UPDATE' && $reason === 'status_change') {
				if ($newStatus === 1) {
					return array('key' => 'AgendaInsuranceContractActivated', 'arguments' => array('contract_ref'));
				}
				if ($newStatus === 9) {
					return array('key' => 'AgendaInsuranceContractTerminated', 'arguments' => array('contract_ref'));
				}
			}
			if ($operation === 'UPDATE' && $reason === 'vehicle_link') {
				return array('key' => 'AgendaInsuranceVehicleLinked', 'arguments' => array('linked_vehicle_ref', 'contract_ref'));
			}
			if ($operation === 'UPDATE' && $reason === 'coverage_change') {
				return array('key' => 'AgendaInsuranceCoverageUpdated', 'arguments' => array('contract_ref'));
			}
			return self::crudDefinition($operation, 'AgendaInsuranceContractCreated', 'AgendaInsuranceContractUpdated', 'AgendaInsuranceContractDeleted', array('contract_ref'));
		}

		if ($element === 'lmdbinsurancecertificate') {
			if ($operation === 'CREATE' && $reason === 'create_draft') {
				return array('key' => 'AgendaInsuranceCertificateUploaded', 'arguments' => array('contract_ref'));
			}
			if ($operation === 'CREATE' && $reason === 'create_and_submit') {
				return array('key' => 'AgendaInsuranceCertificateUploadedAndSubmitted', 'arguments' => array('contract_ref'));
			}
			if ($operation === 'UPDATE' && $reason === 'document_upload') {
				return array('key' => 'AgendaInsuranceCertificateDocumentUpdated', 'arguments' => array('contract_ref'));
			}
			if ($operation === 'UPDATE' && $reason === 'status_change') {
				$statusKeys = array(
					1 => 'AgendaInsuranceCertificateSubmitted',
					2 => 'AgendaInsuranceCertificateValidated',
					3 => 'AgendaInsuranceCertificateRejected',
					9 => 'AgendaInsuranceCertificateArchived',
				);
				if ($newStatus !== null && isset($statusKeys[$newStatus])) {
					return array('key' => $statusKeys[$newStatus], 'arguments' => array('contract_ref'));
				}
			}
			return self::crudDefinition($operation, 'AgendaInsuranceCertificateCreated', 'AgendaInsuranceCertificateUpdated', 'AgendaInsuranceCertificateDeleted', array('contract_ref'));
		}

		return self::crudDefinition($operation, 'AgendaRecordCreated', 'AgendaRecordUpdated', 'AgendaRecordDeleted', array('object_label', 'identifier'));
	}

	/**
	 * Build the native Agenda short title and detailed note.
	 *
	 * @param object $object Module business object
	 * @param string $operation CREATE, UPDATE or DELETE
	 * @param object $langs Dolibarr translation service
	 * @return array{title:string,description:string}
	 */
	public static function buildEventMessages($object, $operation, $langs)
	{
		$context = isset($object->context) && is_array($object->context) ? $object->context : array();
		if ($operation === 'UPDATE' && !isset($context['new_status']) && !empty($context['changed_fields']) && is_array($context['changed_fields']) && in_array('status', $context['changed_fields'], true)) {
			if (isset($object->oldcopy) && is_object($object->oldcopy) && property_exists($object->oldcopy, 'status')) {
				$context['old_status'] = (int) $object->oldcopy->status;
			}
			if (property_exists($object, 'status')) {
				$context['new_status'] = (int) $object->status;
			}
		}

		$data = self::getEventData($object, $langs);
		$definition = self::getMessageDefinition((string) $object->element, $operation, $context);
		$titleArguments = array();
		foreach ($definition['arguments'] as $argumentName) {
			$titleArguments[] = isset($data[$argumentName]) && (string) $data[$argumentName] !== '' ? (string) $data[$argumentName] : self::fallbackIdentifier($object);
		}
		$title = self::translate($langs, $definition['key'], $titleArguments);
		$details = self::buildDescriptionDetails($object, $operation, $langs, $data, $context);
		$description = rtrim($title, ". \t\n\r\0\x0B").'.';
		if (!empty($details)) {
			$description .= ' '.implode(' ', $details);
		}

		return array('title' => $title, 'description' => $description);
	}

	/**
	 * Return a CRUD translation definition.
	 *
	 * @param string $operation CRUD operation
	 * @param string $createKey Create translation
	 * @param string $updateKey Update translation
	 * @param string $deleteKey Delete translation
	 * @param list<string> $arguments Argument names
	 * @return array{key:string,arguments:list<string>}
	 */
	private static function crudDefinition($operation, $createKey, $updateKey, $deleteKey, $arguments)
	{
		$keys = array('CREATE' => $createKey, 'UPDATE' => $updateKey, 'DELETE' => $deleteKey);
		return array('key' => isset($keys[$operation]) ? $keys[$operation] : $updateKey, 'arguments' => $arguments);
	}

	/**
	 * Resolve deletion-safe values used by titles and descriptions.
	 *
	 * @param object $object Module business object
	 * @param object $langs Dolibarr translation service
	 * @return array<string,string>
	 */
	private static function getEventData($object, $langs)
	{
		$identifier = self::firstPropertyValue($object, array('ref', 'registration_number', 'label'));
		if ($identifier === '') {
			$identifier = self::fallbackIdentifier($object);
		}

		$objectLabels = array(
			'lmdbvehicle' => 'Vehicle',
			'lmdbvehicleassignment' => 'VehicleAssignment',
			'lmdbvehicleodometerreading' => 'OdometerReading',
			'lmdbvehicleconsumption' => 'ConsumptionEntry',
			'lmdbvehicleevent' => 'VehicleEvent',
			'lmdbinsurancecontract' => 'InsuranceContract',
			'lmdbinsurancecertificate' => 'InsuranceCertificate',
		);
		$element = (string) $object->element;
		$objectLabel = self::translate($langs, isset($objectLabels[$element]) ? $objectLabels[$element] : 'Record');
		$vehicleRef = $element === 'lmdbvehicle' ? self::firstPropertyValue($object, array('registration_number', 'ref')) : '';
		if ($vehicleRef === '' && property_exists($object, 'fk_vehicle') && (int) $object->fk_vehicle > 0) {
			$vehicleRef = self::fetchVehicleRef($object, (int) $object->fk_vehicle);
		}
		if ($vehicleRef === '') {
			$vehicleRef = self::fallbackLinkedIdentifier($object, 'fk_vehicle');
		}

		$linkedVehicleRef = '';
		if (isset($object->context['linked_vehicle_id']) && (int) $object->context['linked_vehicle_id'] > 0) {
			$linkedVehicleRef = self::fetchVehicleRef($object, (int) $object->context['linked_vehicle_id']);
			if ($linkedVehicleRef === '') {
				$linkedVehicleRef = '#'.((int) $object->context['linked_vehicle_id']);
			}
		}

		$driverName = self::firstPropertyValue($object, array('driver_name'));
		if ($driverName === '' && property_exists($object, 'driver_firstname')) {
			$driverName = trim(self::firstPropertyValue($object, array('driver_firstname')).' '.self::firstPropertyValue($object, array('driver_lastname')));
		}
		if ($driverName === '' && property_exists($object, 'fk_user_driver') && (int) $object->fk_user_driver > 0) {
			$driverName = self::fetchDriverName($object, (int) $object->fk_user_driver);
		}
		if ($driverName === '') {
			$driverName = self::fallbackLinkedIdentifier($object, 'fk_user_driver');
		}

		$contractRef = $element === 'lmdbinsurancecontract' ? self::firstPropertyValue($object, array('ref')) : '';
		if ($contractRef === '' && property_exists($object, 'fk_contract') && (int) $object->fk_contract > 0) {
			$contractRef = self::fetchContractRef($object, (int) $object->fk_contract);
		}
		if ($contractRef === '') {
			$contractRef = self::fallbackLinkedIdentifier($object, 'fk_contract');
		}

		$odometer = property_exists($object, 'odometer_km') ? self::formatNumber((float) $object->odometer_km, $langs, false) : '';
		$quantity = property_exists($object, 'quantity') ? self::formatNumber((float) $object->quantity, $langs, true) : '';
		$unit = property_exists($object, 'unit_snapshot') ? self::formatUnit((string) $object->unit_snapshot) : '';
		$nature = '';
		if (property_exists($object, 'category_snapshot')) {
			$nature = (string) $object->category_snapshot === 'additive' ? self::translate($langs, 'Additive') : self::translate($langs, 'FuelOrRecharge');
		}
		$eventType = '';
		if (property_exists($object, 'event_type') && trim((string) $object->event_type) !== '') {
			$eventType = (string) $object->event_type;
			if (isset($object->fields['event_type']['arrayofkeyval'][$eventType])) {
				$eventType = self::translate($langs, (string) $object->fields['event_type']['arrayofkeyval'][$eventType]);
			}
		}

		return array(
			'identifier' => $identifier,
			'object_label' => $objectLabel,
			'vehicle_ref' => $vehicleRef,
			'linked_vehicle_ref' => $linkedVehicleRef,
			'driver_name' => $driverName,
			'contract_ref' => $contractRef,
			'odometer' => $odometer,
			'quantity' => $quantity,
			'unit' => $unit,
			'nature' => $nature,
			'event_type' => $eventType,
		);
	}

	/**
	 * Build concise, translated detail sentences.
	 *
	 * @param object $object Module business object
	 * @param string $operation CRUD operation
	 * @param object $langs Dolibarr translation service
	 * @param array<string,string> $data Resolved display data
	 * @param array<string,mixed> $context Trigger context
	 * @return list<string>
	 */
	private static function buildDescriptionDetails($object, $operation, $langs, $data, $context)
	{
		$details = array();
		$element = (string) $object->element;
		if ($element === 'lmdbvehicleassignment') {
			self::appendPeriodDetail($details, $object, 'date_start', 'date_end', $langs);
		} elseif ($element === 'lmdbvehicleodometerreading') {
			self::appendDateDetail($details, $object, 'reading_date', 'AgendaEventDateDetail', $langs);
		} elseif ($element === 'lmdbvehicleconsumption') {
			self::appendDetail($details, $langs, 'AgendaVehicleDetail', array($data['vehicle_ref']));
			self::appendDetail($details, $langs, 'AgendaNatureDetail', array($data['nature']));
			if ($data['quantity'] !== '') {
				self::appendDetail($details, $langs, 'AgendaQuantityDetail', array(trim($data['quantity'].' '.$data['unit'])));
			}
			if ($data['odometer'] !== '') {
				self::appendDetail($details, $langs, 'AgendaOdometerDetail', array($data['odometer']));
			}
			self::appendDetail($details, $langs, 'AgendaDriverDetail', array($data['driver_name']));
		} elseif ($element === 'lmdbvehicleevent') {
			self::appendDetail($details, $langs, 'AgendaVehicleDetail', array($data['vehicle_ref']));
			$label = self::firstPropertyValue($object, array('label'));
			self::appendDetail($details, $langs, 'AgendaLabelDetail', array($label));
			self::appendDetail($details, $langs, 'AgendaEventTypeDetail', array($data['event_type']));
			self::appendDateDetail($details, $object, 'event_date', 'AgendaEventDateDetail', $langs);
		} elseif ($element === 'lmdbinsurancecontract') {
			self::appendDetail($details, $langs, 'AgendaPolicyNumberDetail', array(self::firstPropertyValue($object, array('policy_number'))));
			self::appendPeriodDetail($details, $object, 'date_start', 'date_end', $langs);
		} elseif ($element === 'lmdbinsurancecertificate') {
			self::appendDetail($details, $langs, 'AgendaVehicleDetail', array($data['vehicle_ref']));
			self::appendPeriodDetail($details, $object, 'validity_start', 'validity_end', $langs, 'AgendaValidityDetail');
		}

		if ($operation === 'UPDATE' && !empty($context['changed_fields']) && is_array($context['changed_fields'])) {
			$changedLabels = array();
			foreach ($context['changed_fields'] as $fieldName) {
				$fieldName = (string) $fieldName;
				if (isset($object->fields[$fieldName]['label'])) {
					$changedLabels[] = self::translate($langs, (string) $object->fields[$fieldName]['label']);
				} elseif ($fieldName === 'vehicle_links') {
					$changedLabels[] = self::translate($langs, 'InsuranceVehicleLinks');
				}
			}
			$changedLabels = array_values(array_unique(array_filter($changedLabels)));
			if (!empty($changedLabels)) {
				$details[] = self::translate($langs, 'AgendaChangedFields', array(implode(', ', $changedLabels)));
			}
		}

		return $details;
	}

	/** @param list<string> $details @param object $object @param object $langs @return void */
	private static function appendPeriodDetail(&$details, $object, $startField, $endField, $langs, $translationKey = 'AgendaPeriodDetail')
	{
		$start = property_exists($object, $startField) ? self::formatDate($object->{$startField}) : '';
		$end = property_exists($object, $endField) ? self::formatDate($object->{$endField}) : '';
		if ($start !== '' && $end !== '') {
			$details[] = self::translate($langs, $translationKey, array($start, $end));
		} elseif ($start !== '') {
			$details[] = self::translate($langs, 'AgendaStartDateDetail', array($start));
		}
	}

	/** @param list<string> $details @param object $object @param object $langs @return void */
	private static function appendDateDetail(&$details, $object, $field, $translationKey, $langs)
	{
		$date = property_exists($object, $field) ? self::formatDate($object->{$field}, true) : '';
		if ($date !== '') {
			$details[] = self::translate($langs, $translationKey, array($date));
		}
	}

	/** @param list<string> $details @param object $langs @param list<string> $arguments @return void */
	private static function appendDetail(&$details, $langs, $translationKey, $arguments)
	{
		foreach ($arguments as $argument) {
			if (trim((string) $argument) === '' || strpos((string) $argument, '#0') === 0) {
				return;
			}
		}
		$details[] = self::translate($langs, $translationKey, $arguments);
	}

	/** @param object $object @param list<string> $properties @return string */
	private static function firstPropertyValue($object, $properties)
	{
		foreach ($properties as $property) {
			if (property_exists($object, $property) && trim((string) $object->{$property}) !== '') {
				return trim((string) $object->{$property});
			}
		}
		return '';
	}

	/** @param object $object @return string */
	private static function fallbackIdentifier($object)
	{
		$id = !empty($object->id) ? (int) $object->id : (!empty($object->rowid) ? (int) $object->rowid : 0);
		return '#'.$id;
	}

	/** @param object $object @param string $property @return string */
	private static function fallbackLinkedIdentifier($object, $property)
	{
		return property_exists($object, $property) && (int) $object->{$property} > 0 ? '#'.((int) $object->{$property}) : '';
	}

	/** @param object $object @param int $vehicleId @return string */
	private static function fetchVehicleRef($object, $vehicleId)
	{
		$entitySql = !empty($object->entity) ? ' AND entity = '.((int) $object->entity) : '';
		$row = self::fetchRow($object, 'SELECT registration_number, ref FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle WHERE rowid = '.$vehicleId.$entitySql);
		if (!is_object($row)) {
			return '';
		}
		return trim((string) ($row->registration_number ?: $row->ref));
	}

	/** @param object $object @param int $userId @return string */
	private static function fetchDriverName($object, $userId)
	{
		$row = self::fetchRow($object, 'SELECT firstname, lastname, login FROM '.MAIN_DB_PREFIX.'user WHERE rowid = '.$userId);
		if (!is_object($row)) {
			return '';
		}
		$name = trim((string) $row->firstname.' '.(string) $row->lastname);
		return $name !== '' ? $name : (string) $row->login;
	}

	/** @param object $object @param int $contractId @return string */
	private static function fetchContractRef($object, $contractId)
	{
		$entitySql = !empty($object->entity) ? ' AND entity = '.((int) $object->entity) : '';
		$row = self::fetchRow($object, 'SELECT ref FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract WHERE rowid = '.$contractId.$entitySql);
		return is_object($row) ? trim((string) $row->ref) : '';
	}

	/** @param object $object @param string $sql @return ?object */
	private static function fetchRow($object, $sql)
	{
		if (!isset($object->db) || !is_object($object->db)) {
			return null;
		}
		$resql = $object->db->query($sql);
		if (!$resql) {
			if (function_exists('dol_syslog')) {
				dol_syslog(__METHOD__.' failed to resolve Agenda display data: '.$object->db->lasterror(), LOG_WARNING);
			}
			return null;
		}
		$row = $object->db->fetch_object($resql);
		$object->db->free($resql);
		return is_object($row) ? $row : null;
	}

	/** @param float $value @param object $langs @param bool $quantity @return string */
	private static function formatNumber($value, $langs, $quantity)
	{
		if (function_exists('price')) {
			return (string) ($quantity ? price($value, 0, $langs, 1, 2, 2) : price($value, 0, $langs, 1, -1, -1));
		}
		return (string) $value;
	}

	/** @param mixed $value @param bool $withTime @return string */
	private static function formatDate($value, $withTime = false)
	{
		if (empty($value)) {
			return '';
		}
		if (function_exists('dol_print_date')) {
			return dol_print_date((int) $value, $withTime ? 'dayhour' : 'day');
		}
		return date($withTime ? 'Y-m-d H:i' : 'Y-m-d', (int) $value);
	}

	/** @param string $unit @return string */
	private static function formatUnit($unit)
	{
		$units = array('L' => 'L', 'KWH' => 'kWh', 'KG' => 'kg', 'M3' => 'm³');
		$unit = strtoupper(trim($unit));
		return isset($units[$unit]) ? $units[$unit] : $unit;
	}

	/** @param object $langs @param string $key @param list<string> $arguments @return string */
	private static function translate($langs, $key, $arguments = array())
	{
		$parameters = array_merge(array($key), $arguments);
		return (string) call_user_func_array(array($langs, 'transnoentitiesnoconv'), $parameters);
	}

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
