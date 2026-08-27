<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Hooks for the vehicle management module.
 */
class ActionsLmdbVehicleManagement
{
	/** @var string Multicompany payload root key */
	public const MULTICOMPANY_SHARING_ROOT_KEY = 'lmdbvehiclemanagement';

	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

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
	 * Return the single source of truth for Multicompany sharing.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function getMulticompanySharingDefinition()
	{
		return array(
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
				),
				'sharingmodulename' => array(
					'lmdbvehicle' => 'lmdbvehiclemanagement',
					'lmdbvehiclenumber' => 'lmdbvehiclemanagement',
				),
			),
		);
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
	 * assignment rule as the vehicle Agenda tab.
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
		if ($elementType !== 'lmdbvehicle@lmdbvehiclemanagement') {
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
		$eventDefinition = array(
			'module' => 'lmdbvehiclemanagement',
			'element' => 'lmdbvehicleevent',
			'table_element' => 'lmdbvehiclemanagement_vehicle_event',
			'subelement' => 'lmdbvehicleevent',
			'classpath' => 'lmdbvehiclemanagement/class',
			'classfile' => 'lmdbvehicleevent',
			'classname' => 'LmdbVehicleEvent',
		);
		$definitions = array(
			'lmdbvehicle@lmdbvehiclemanagement' => $vehicleDefinition,
			'lmdbvehiclemanagement_lmdbvehicle' => $vehicleDefinition,
			'lmdbvehicleevent@lmdbvehiclemanagement' => $eventDefinition,
			'lmdbvehiclemanagement_lmdbvehicleevent' => $eventDefinition,
		);
		if (isset($definitions[$elementType])) {
			$this->results = array_replace($this->results, $definitions[$elementType]);
		}

		return 0;
	}
}
