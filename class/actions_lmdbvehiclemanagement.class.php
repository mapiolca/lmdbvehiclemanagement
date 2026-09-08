<?php
require_once __DIR__.'/lmdbvehiclesharing.class.php';
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');
require_once __DIR__.'/lmdbvehiclesupplierinvoicehooks.trait.php';
require_once __DIR__.'/lmdbvehicledossierhooks.trait.php';
require_once __DIR__.'/lmdbvehiclesharinghooks.trait.php';

/**
 * Hooks for the vehicle management module.
 */
class ActionsLmdbVehicleManagement
{
	use LmdbVehicleSupplierInvoiceHooks;
	use LmdbVehicleDossierHooks;
	use LmdbVehicleSharingHooks;
	/** @var string Multicompany payload root key */
	public const MULTICOMPANY_SHARING_ROOT_KEY = 'lmdbvehiclemanagement';

	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/** @var array<int,string> */
	public $warnings = array();

	/** @var array<string,mixed> */
	public $results = array();

	/** @var string */
	public $resprints = '';

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Add the consumption card to Dolibarr's native quick-add dropdown.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param array<string,mixed> $object Existing quick-add definition
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function menuDropdownQuickaddItems($parameters, &$object, &$action, $hookmanager)
	{
		global $user;

		$this->results = array();
		if (!isModEnabled('lmdbvehiclemanagement')) {
			return 0;
		}

		if (LmdbVehicleSharing::can($user, 'consumption', 'write')) {
			$this->results[] = array(
				'url' => dol_buildpath('/lmdbvehiclemanagement/consumption_card.php', 1).'?action=create&mainmenu=lmdbvehiclemanagement&token='.newToken(),
				'title' => 'NewConsumption@lmdbvehiclemanagement',
				'name' => 'ConsumptionEntry@lmdbvehiclemanagement',
				'picto' => 'gas-pump',
				'activation' => true,
				'position' => 450,
			);
		}
		if (LmdbVehicleSharing::can($user, 'regulatorycontrol', 'write')) {
			$this->results[] = array(
				'url' => dol_buildpath('/lmdbvehiclemanagement/regulatorycontrol_card.php', 1).'?action=create&mainmenu=lmdbvehiclemanagement&token='.newToken(),
				'title' => 'NewRegulatoryControl@lmdbvehiclemanagement',
				'name' => 'RegulatoryControl@lmdbvehiclemanagement',
				'picto' => 'clipboard-check',
				'activation' => true,
				'position' => 451,
			);
		}

		return 0;
	}

	/**
	 * Route native vehicle import rows through transactional business methods.
	 *
	 * Step 5 is the native simulation and therefore runs without triggers.
	 * Step 6 emits one CREATE/UPDATE trigger after capacities, unless fast mode is selected.
	 *
	 * @param array<string,mixed> $parameters Native import parameters
	 * @param CommonObject|null $object Current hook object
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int<-1,1>
	 */
	public function ImportInsert($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		$dataset = isset($parameters['datatoimport']) ? (string) $parameters['datatoimport'] : '';
		if (!in_array($dataset, array('lmdbvehiclemanagement_vehicles', 'lmdbvehiclemanagement_regulatory_controls', 'lmdbvehiclemanagement_consumptions'), true)) {
			return 0;
		}
		$rightObject = $dataset === 'lmdbvehiclemanagement_vehicles' ? 'lmdbvehicle' : ($dataset === 'lmdbvehiclemanagement_consumptions' ? 'consumption' : 'regulatorycontrol');
		$langs->loadLangs(array('main', 'errors', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
		if (!isModEnabled('lmdbvehiclemanagement') || !empty($user->socid) || !$user->hasRight('lmdbvehiclemanagement', $rightObject, 'import') || ($rightObject === 'consumption' && !$user->hasRight('lmdbvehiclemanagement', 'consumption', 'read'))) {
			$this->error = $langs->trans('NotEnoughPermissions');
			$this->errors = array();
			return -1;
		}
		// Native import ignores empty CSV records, including template spacer rows.
		if (isset($parameters['arrayrecord']) && is_array($parameters['arrayrecord'])) {
			$record = $parameters['arrayrecord'];
			$firstCell = reset($record);
			$firstValue = is_array($firstCell) ? ($firstCell['val'] ?? null) : $firstCell;
			if (count($record) === 0 || (count($record) === 1 && ($firstValue === null || $firstValue === ''))) {
				return 1;
			}
		}
		if (empty($parameters['arrayrecord']) || !is_array($parameters['arrayrecord']) || empty($parameters['array_match_file_to_database']) || !is_array($parameters['array_match_file_to_database'])) {
			$this->error = $langs->trans('VehicleImportInvalidRow');
			$this->errors = array();
			return -1;
		}

		$step = isset($parameters['step']) ? (int) $parameters['step'] : 0;
		$importId = isset($parameters['importid']) ? (string) $parameters['importid'] : '';
		$runTriggers = $step === 6 && (!isset($parameters['importtriggermode']) || $parameters['importtriggermode'] !== 'fast_bulk');
		if ($dataset === 'lmdbvehiclemanagement_vehicles') {
			dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleimport.class.php');
			$import = new LmdbVehicleImport($this->db);
			$result = $import->createVehicleFromNativeRow($parameters['arrayrecord'], $parameters['array_match_file_to_database'], $importId, $user, $runTriggers, isset($parameters['updatekeys']) && is_array($parameters['updatekeys']) ? $parameters['updatekeys'] : array());
		} elseif ($dataset === 'lmdbvehiclemanagement_consumptions') {
			require_once __DIR__.'/lmdbvehicleconsumptionimport.class.php';
			$import = new LmdbVehicleConsumptionImport($this->db);
			$result = $import->importNativeRow($parameters['arrayrecord'], $parameters['array_match_file_to_database'], $importId, $user, $runTriggers, isset($parameters['updatekeys']) && is_array($parameters['updatekeys']) ? $parameters['updatekeys'] : array());
		} else {
			dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleregulatorycontrolimport.class.php');
			$import = new LmdbVehicleRegulatoryControlImport($this->db);
			$result = $import->createDraftFromNativeRow($parameters['arrayrecord'], $parameters['array_match_file_to_database'], $importId, $user, $step === 6);
		}
		if ($result <= 0) {
			// HookManager retains errors between rows: publish this row once via error.
			$messages = array_filter(array_unique(array_merge(array($import->error), $import->errors)));
			$this->error = implode(' ; ', $messages);
			$this->errors = array();
			return -1;
		}

		// Native summaries read the driver's counter, separately from successful rows.
		if ($dataset === 'lmdbvehiclemanagement_consumptions' && $import->unchanged) {
			return 1;
		}
		$counter = in_array($dataset, array('lmdbvehiclemanagement_vehicles', 'lmdbvehiclemanagement_consumptions'), true) && $import->updated ? 'nbupdate' : 'nbinsert';
		if (isset($parameters['obj']) && is_object($parameters['obj']) && property_exists($parameters['obj'], $counter)) {
			$parameters['obj']->{$counter} = (int) $parameters['obj']->{$counter} + 1;
		}

		if (isset($parameters['nbok'])) {
			$parameters['nbok'] = (int) $parameters['nbok'] + 1;
		}

		return 1;
	}

	/**
	 * Return the single source of truth for Multicompany sharing.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function getMulticompanySharingDefinition()
	{
		$definition = array(
			self::MULTICOMPANY_SHARING_ROOT_KEY => array(
				'sharingelements' => array(
					'lmdbvehicle' => array(
						'type' => 'element',
						'icon' => 'car',
						'lang' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
						'tooltip' => 'LmdbVehicleSharingInfo',
						'enable' => 'isModEnabled("lmdbvehiclemanagement")',
						'input' => array('global' => array('showhide' => true, 'hide' => true, 'del' => true)),
					),
					'lmdbvehiclenumber' => array(
						'type' => 'objectnumber',
						'icon' => 'hashtag',
						'lang' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
						'tooltip' => 'LmdbVehicleNumberSharingInfo',
						'enable' => 'isModEnabled("lmdbvehiclemanagement")',
						'input' => array('global' => array('showhide' => true, 'hide' => true, 'del' => true)),
					),
					'lmdbvehicleconsumption' => array(
						'type' => 'element',
						'icon' => 'gas-pump',
						'lang' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
						'tooltip' => 'LmdbVehicleConsumptionSharingInfo',
						'enable' => 'isModEnabled("lmdbvehiclemanagement")',
						'input' => array('global' => array('showhide' => true, 'hide' => true, 'del' => true)),
					),
					'lmdbvehicleconsumptionnumber' => array(
						'type' => 'objectnumber',
						'icon' => 'hashtag',
						'lang' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
						'tooltip' => 'LmdbVehicleConsumptionNumberSharingInfo',
						'enable' => 'isModEnabled("lmdbvehiclemanagement")',
						'input' => array('global' => array('showhide' => true, 'hide' => true, 'del' => true)),
					),
					'lmdbvehicleregulatorycontrol' => array(
						'type' => 'element',
						'icon' => 'clipboard-check',
						'lang' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
						'tooltip' => 'LmdbVehicleRegulatoryControlSharingInfo',
						'enable' => 'isModEnabled("lmdbvehiclemanagement")',
						'input' => array('global' => array('showhide' => true, 'hide' => true, 'del' => true)),
					),
					'lmdbvehicleregulatorycontrolnumber' => array(
						'type' => 'objectnumber',
						'icon' => 'hashtag',
						'lang' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
						'tooltip' => 'LmdbVehicleRegulatoryControlNumberSharingInfo',
						'enable' => 'isModEnabled("lmdbvehiclemanagement")',
						'input' => array('global' => array('showhide' => true, 'hide' => true, 'del' => true)),
					),
				),
				'sharingmodulename' => array(
					'lmdbvehicle' => 'lmdbvehiclemanagement',
					'lmdbvehiclenumber' => 'lmdbvehiclemanagement',
					'lmdbvehicleconsumption' => 'lmdbvehiclemanagement',
					'lmdbvehicleconsumptionnumber' => 'lmdbvehiclemanagement',
					'lmdbvehicleregulatorycontrol' => 'lmdbvehiclemanagement',
					'lmdbvehicleregulatorycontrolnumber' => 'lmdbvehiclemanagement',
				),
				'dictionary' => array(
					'c_lmdbvehiclemanagement_energy' => array(
						'type' => 'dictionary',
						'icon' => 'car',
						'transkey' => 'VehicleEnergies',
						'tooltip' => 'VehicleEnergySharingInfo',
						'lang' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
						'filepath' => '/lmdbvehiclemanagement/sql/llx_c_lmdbvehiclemanagement_energy.sql',
					),
					'c_lmdbvehiclemanagement_consumable' => array(
						'type' => 'dictionary',
						'icon' => 'gas-pump',
						'transkey' => 'VehicleConsumables',
						'tooltip' => 'VehicleConsumableSharingInfo',
						'lang' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
						'filepath' => '/lmdbvehiclemanagement/sql/llx_c_lmdbvehiclemanagement_consumable.sql',
					),
					'c_lmdbvehiclemanagement_asset_type' => array(
						'type' => 'dictionary', 'icon' => 'truck', 'transkey' => 'AssetTypes', 'tooltip' => 'AssetTypeSharingInfo',
						'lang' => 'lmdbvehiclemanagement@lmdbvehiclemanagement', 'filepath' => '/lmdbvehiclemanagement/sql/llx_c_lmdbvehiclemanagement_asset_type.sql',
					),
					'c_lmdbvehiclemanagement_regulatory_profile' => array(
						'type' => 'dictionary', 'icon' => 'tags', 'transkey' => 'RegulatoryProfiles', 'tooltip' => 'RegulatoryProfileSharingInfo',
						'lang' => 'lmdbvehiclemanagement@lmdbvehiclemanagement', 'filepath' => '/lmdbvehiclemanagement/sql/llx_c_lmdbvehiclemanagement_regulatory_profile.sql',
					),
					'c_lmdbvehiclemanagement_control_type' => array(
						'type' => 'dictionary', 'icon' => 'clipboard-check', 'transkey' => 'RegulatoryControlTypes', 'tooltip' => 'RegulatoryControlTypeSharingInfo',
						'lang' => 'lmdbvehiclemanagement@lmdbvehiclemanagement', 'filepath' => '/lmdbvehiclemanagement/sql/llx_c_lmdbvehiclemanagement_control_type.sql',
					),
					'c_lmdbvehiclemanagement_control_result' => array(
						'type' => 'dictionary', 'icon' => 'check-circle', 'transkey' => 'RegulatoryControlResults', 'tooltip' => 'RegulatoryControlResultSharingInfo',
						'lang' => 'lmdbvehiclemanagement@lmdbvehiclemanagement', 'filepath' => '/lmdbvehiclemanagement/sql/llx_c_lmdbvehiclemanagement_control_result.sql',
					),
				),
			),
		);
		foreach (LmdbVehicleSharing::definitions() as $element => $item) {
			if (!isset($definition[self::MULTICOMPANY_SHARING_ROOT_KEY]['sharingelements'][$element])) {
				$definition[self::MULTICOMPANY_SHARING_ROOT_KEY]['sharingelements'][$element] = array(
					'type' => 'element', 'icon' => 'car', 'lang' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
					'tooltip' => 'LmdbSharingInfo', 'enable' => 'isModEnabled("lmdbvehiclemanagement")',
					'input' => array('global' => array('showhide' => true, 'hide' => true, 'del' => true)),
				);
			}
			// Native discovery only; guarded module forms own single-record writes.
			if (LmdbVehicleSharing::available()) $definition[self::MULTICOMPANY_SHARING_ROOT_KEY]['sharingelements'][$element]['sharebyelement'] = array('context' => array());
			$definition[self::MULTICOMPANY_SHARING_ROOT_KEY]['sharingmodulename'][$element] = 'lmdbvehiclemanagement';
		}
		return $definition;
	}

	/** @return void */
	private function registerMulticompanySharingDefinition()
	{
		global $langs;

		$langs->load('lmdbvehiclemanagement@lmdbvehiclemanagement');
		$this->results = array_replace_recursive($this->results, self::getMulticompanySharingDefinition());
	}

	/**
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Current object
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function multicompanyExternalModulesSharing($parameters, &$object, &$action, $hookmanager)
	{
		$this->registerMulticompanySharingDefinition();
		return 0;
	}

	/**
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Current object
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function multicompanyExternalModuleSharing($parameters, &$object, &$action, $hookmanager)
	{
		$this->registerMulticompanySharingDefinition();
		return 0;
	}

	/**
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Current object
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function multicompanySharingOptions($parameters, &$object, &$action, $hookmanager)
	{
		$this->registerMulticompanySharingDefinition();
		return 0;
	}

	/**
	 * Restrict the native recent-events block to Agenda events visible to the user.
	 *
	 * FormActions::showactions() delegates its query to the agendadao hooks. This
	 * filter preserves the native rendering while applying the same ownership and
	 * assignment rule as the vehicle and insurance contract Agenda tabs.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject|null $object Current object
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function getActionsListWhere($parameters, &$object, &$action, $hookmanager)
	{
		global $user;

		$elementType = isset($parameters['elementtype']) ? (string) $parameters['elementtype'] : '';
		if (!in_array($elementType, array('lmdbvehicle@lmdbvehiclemanagement', 'lmdbinsurancecontract@lmdbvehiclemanagement', 'lmdbvehicleconsumption@lmdbvehiclemanagement', 'lmdbvehicleregulatorycontrol@lmdbvehiclemanagement'), true)) {
			return 0;
		}
		if ($user->hasRight('agenda', 'allactions', 'read')) {
			return 0;
		}
		if (!$user->hasRight('agenda', 'myactions', 'read')) {
			$this->resprints = ' AND 1 = 0';
			return 0;
		}

		$this->resprints = ' AND (a.fk_user_author = '.((int) $user->id);
		$this->resprints .= ' OR a.fk_user_action = '.((int) $user->id);
		$this->resprints .= ' OR EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'actioncomm_resources AS lmdbvm_ar';
		$this->resprints .= " WHERE lmdbvm_ar.fk_actioncomm = a.id AND lmdbvm_ar.element_type = 'user'";
		$this->resprints .= ' AND lmdbvm_ar.fk_element = '.((int) $user->id).'))';

		return 0;
	}

	/**
	 * Make custom objects resolvable by fetchObjectByElement().
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject|null $object Current object
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function getElementProperties($parameters, &$object, &$action, $hookmanager)
	{
		$elementType = isset($parameters['elementType']) ? (string) $parameters['elementType'] : '';
		$vehicleDefinition = array(
			'module' => 'lmdbvehiclemanagement',
			'element' => 'lmdbvehicle',
			'table_element' => 'lmdbvehiclemanagement_vehicle',
			'subelement' => 'lmdbvehicle',
			'classpath' => 'lmdbvehiclemanagement/class',
			'classfile' => 'lmdbvehicle',
			'classname' => 'LmdbVehicle',
		);
		$vehicleAjaxTooltipDefinition = array(
			'module' => 'lmdbvehiclemanagement',
			'element' => 'lmdbvehicleajaxtooltip',
			'table_element' => 'lmdbvehiclemanagement_vehicle',
			'subelement' => 'lmdbvehicleajaxtooltip',
			'classpath' => 'lmdbvehiclemanagement/class',
			'classfile' => 'lmdbvehicle',
			'classname' => 'LmdbVehicleAjaxTooltip',
		);
		$eventDefinition = array(
			'module' => 'lmdbvehiclemanagement',
			'element' => 'lmdbvehicleevent',
			'table_element' => 'lmdbvehiclemanagement_vehicle_event',
			'subelement' => 'lmdbvehicleevent',
			'classpath' => 'lmdbvehiclemanagement/class',
			'classfile' => 'lmdbvehicleevent',
			'classname' => 'LmdbVehicleEvent',
		);
		$assignmentDefinition = array(
			'module' => 'lmdbvehiclemanagement',
			'element' => 'lmdbvehicleassignment',
			'table_element' => 'lmdbvehiclemanagement_vehicle_assignment',
			'subelement' => 'lmdbvehicleassignment',
			'classpath' => 'lmdbvehiclemanagement/class',
			'classfile' => 'lmdbvehicleassignment',
			'classname' => 'LmdbVehicleAssignment',
		);
		$odometerDefinition = array(
			'module' => 'lmdbvehiclemanagement',
			'element' => 'lmdbvehicleodometerreading',
			'table_element' => 'lmdbvehiclemanagement_odometer_reading',
			'subelement' => 'lmdbvehicleodometerreading',
			'classpath' => 'lmdbvehiclemanagement/class',
			'classfile' => 'lmdbvehicleodometerreading',
			'classname' => 'LmdbVehicleOdometerReading',
		);
		$insuranceContractDefinition = array(
			'module' => 'lmdbvehiclemanagement',
			'element' => 'lmdbinsurancecontract',
			'table_element' => 'lmdbvehiclemanagement_insurance_contract',
			'subelement' => 'lmdbinsurancecontract',
			'classpath' => 'lmdbvehiclemanagement/class',
			'classfile' => 'lmdbvehicleinsurancecontract',
			'classname' => 'LmdbVehicleInsuranceContract',
		);
		$insuranceCertificateDefinition = array(
			'module' => 'lmdbvehiclemanagement',
			'element' => 'lmdbinsurancecertificate',
			'table_element' => 'lmdbvehiclemanagement_insurance_certificate',
			'subelement' => 'lmdbinsurancecertificate',
			'classpath' => 'lmdbvehiclemanagement/class',
			'classfile' => 'lmdbvehicleinsurancecertificate',
			'classname' => 'LmdbVehicleInsuranceCertificate',
		);
		$consumptionDefinition = array(
			'module' => 'lmdbvehiclemanagement',
			'element' => 'lmdbvehicleconsumption',
			'table_element' => 'lmdbvehiclemanagement_consumption',
			'subelement' => 'lmdbvehicleconsumption',
			'classpath' => 'lmdbvehiclemanagement/class',
			'classfile' => 'lmdbvehicleconsumption',
			'classname' => 'LmdbVehicleConsumption',
		);
		$regulatoryControlDefinition = array(
			'module' => 'lmdbvehiclemanagement',
			'element' => 'lmdbvehicleregulatorycontrol',
			'table_element' => 'lmdbvehiclemanagement_regulatory_control',
			'subelement' => 'lmdbvehicleregulatorycontrol',
			'classpath' => 'lmdbvehiclemanagement/class',
			'classfile' => 'lmdbvehicleregulatorycontrol',
			'classname' => 'LmdbVehicleRegulatoryControl',
		);
		$definitions = array(
			'lmdbvehicle@lmdbvehiclemanagement' => $vehicleDefinition,
			'lmdbvehiclemanagement_lmdbvehicle' => $vehicleDefinition,
			'lmdbvehicleajaxtooltip@lmdbvehiclemanagement' => $vehicleAjaxTooltipDefinition,
			'lmdbvehiclemanagement_lmdbvehicleajaxtooltip' => $vehicleAjaxTooltipDefinition,
			'lmdbvehicleevent@lmdbvehiclemanagement' => $eventDefinition,
			'lmdbvehiclemanagement_lmdbvehicleevent' => $eventDefinition,
			'lmdbvehicleassignment@lmdbvehiclemanagement' => $assignmentDefinition,
			'lmdbvehiclemanagement_lmdbvehicleassignment' => $assignmentDefinition,
			'lmdbvehicleodometerreading@lmdbvehiclemanagement' => $odometerDefinition,
			'lmdbvehiclemanagement_lmdbvehicleodometerreading' => $odometerDefinition,
			'lmdbinsurancecontract@lmdbvehiclemanagement' => $insuranceContractDefinition,
			'lmdbvehiclemanagement_lmdbinsurancecontract' => $insuranceContractDefinition,
			'lmdbinsurancecertificate@lmdbvehiclemanagement' => $insuranceCertificateDefinition,
			'lmdbvehiclemanagement_lmdbinsurancecertificate' => $insuranceCertificateDefinition,
			'lmdbvehicleconsumption@lmdbvehiclemanagement' => $consumptionDefinition,
			'lmdbvehiclemanagement_lmdbvehicleconsumption' => $consumptionDefinition,
			'lmdbvehicleregulatorycontrol@lmdbvehiclemanagement' => $regulatoryControlDefinition,
			'lmdbvehiclemanagement_lmdbvehicleregulatorycontrol' => $regulatoryControlDefinition,
		);
		if (isset($definitions[$elementType])) {
			$this->results = array_replace($this->results, $definitions[$elementType]);
		}

		return 0;
	}

	/**
	 * Expose module CRUD events to native Notifications.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject|null $object Current object
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function notifsupported($parameters, &$object, &$action, $hookmanager)
	{
		dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleagenda.class.php');
		$this->results['arrayofnotifsupported'] = array_keys(LmdbVehicleAgenda::getTriggerDefinitions());
		return 0;
	}
}
