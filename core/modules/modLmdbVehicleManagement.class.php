<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/**
 * Module descriptor.
 */
class modLmdbVehicleManagement extends DolibarrModules
{
	/** @var string SPDX license identifier */
	public $license = 'GPL-3.0-or-later';

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;
		$this->numero = 450026;
		$this->rights_class = 'lmdbvehiclemanagement';
		$this->family = 'Les Métiers du Bâtiment';
		$this->module_position = '30';
		$this->name = 'LmdbVehicleManagement';
		$this->description = 'ModuleLmdbVehicleManagementDesc';
		$this->descriptionlong = 'ModuleLmdbVehicleManagementDesc';
		$this->editor_name = 'Pierre Ardoin';
		$this->editor_url = 'https://github.com/mapiolca';
		$this->version = '0.1.0';
		$this->const_name = 'MAIN_MODULE_LMDBVEHICLEMANAGEMENT';
		$this->picto = 'car';

		$this->module_parts = array(
			'triggers' => 1,
			'hooks' => array(
				'data' => array(
					'lmdbvehiclecard',
					'lmdbvehiclelist',
					'lmdbvehicleeventcard',
					'lmdbvehicleeventlist',
					'agendadao',
					'elementproperties',
					'multicompanyexternalmodulesharing',
					'multicompanyexternalmodules',
					'multicompanysharingoptions',
				),
				'entity' => '0',
			),
		);

		$this->dirs = array(
			'/lmdbvehiclemanagement/temp',
		);
		$this->config_page_url = array('setup.php@lmdbvehiclemanagement');
		$this->hidden = getDolGlobalInt('MODULE_LMDBVEHICLEMANAGEMENT_DISABLED');
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('lmdbvehiclemanagement@lmdbvehiclemanagement');
		$this->phpmin = array(8, 0);
		$this->need_dolibarr_version = array(20, 0);
		$this->need_javascript_ajax = 0;
		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();
		$this->const = array();
		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		if (!isModEnabled('lmdbvehiclemanagement')) {
			$conf->lmdbvehiclemanagement = new stdClass();
			$conf->lmdbvehiclemanagement->enabled = 0;
		}

		$this->rights = array();
		$r = 0;

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionReadVehicles';
		$this->rights[$r][3] = 1;
		// Dolibarr v20 Agenda checks the top-level module read right before it
		// persists a link from ActionComm to an external object.
		$this->rights[$r][4] = 'read';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionWriteVehicles';
		$this->rights[$r][4] = 'lmdbvehicle';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionDeleteVehicles';
		$this->rights[$r][4] = 'lmdbvehicle';
		$this->rights[$r][5] = 'delete';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionManageVehicleAssignments';
		$this->rights[$r][4] = 'assignment';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionManageVehicleOdometer';
		$this->rights[$r][4] = 'odometer';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionManageVehicleEvents';
		$this->rights[$r][4] = 'event';
		$this->rights[$r][5] = 'write';

		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=tools',
			'type' => 'left',
			'titre' => 'VehicleList',
			'prefix' => img_picto('', 'car', 'class="pictofixedwidth valignmiddle paddingright"'),
			'mainmenu' => 'tools',
			'leftmenu' => 'lmdbvehiclemanagement',
			'url' => '/lmdbvehiclemanagement/vehicle_list.php',
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'position' => 2600,
			'enabled' => 'isModEnabled("lmdbvehiclemanagement")',
			'perms' => '$user->hasRight("lmdbvehiclemanagement", "read")',
			'target' => '',
			'user' => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=lmdbvehiclemanagement',
			'type' => 'left',
			'titre' => 'NewVehicle',
			'mainmenu' => 'tools',
			'leftmenu' => 'lmdbvehiclemanagement_new',
			'url' => '/lmdbvehiclemanagement/vehicle_card.php?action=create',
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'position' => 2601,
			'enabled' => 'isModEnabled("lmdbvehiclemanagement")',
			'perms' => '$user->hasRight("lmdbvehiclemanagement", "lmdbvehicle", "write")',
			'target' => '',
			'user' => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=lmdbvehiclemanagement',
			'type' => 'left',
			'titre' => 'VehicleEvents',
			'mainmenu' => 'tools',
			'leftmenu' => 'lmdbvehiclemanagement_events',
			'url' => '/lmdbvehiclemanagement/vehicleevent_list.php',
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'position' => 2602,
			'enabled' => 'isModEnabled("lmdbvehiclemanagement")',
			'perms' => '$user->hasRight("lmdbvehiclemanagement", "read")',
			'target' => '',
			'user' => 0,
		);
	}

	/**
	 * Enable the module without resetting existing entity settings.
	 *
	 * @param string $options Activation options
	 * @return int<-1,1>
	 */
	public function init($options = '')
	{
		global $conf;

		$result = $this->_load_tables('/lmdbvehiclemanagement/sql/');
		if ($result < 0) {
			return -1;
		}

		$result = $this->_init(array(), $options);
		if ($result <= 0) {
			return $result;
		}

		$defaults = array(
			'LMDBVEHICLEMANAGEMENT_LMDBVEHICLE_ADDON' => 'mod_lmdbvehicle_standard',
			'LMDBVEHICLEMANAGEMENT_LMDBVEHICLEEVENT_ADDON' => 'mod_lmdbvehicleevent_standard',
		);
		foreach ($defaults as $name => $value) {
			$constantExists = $this->entityConstantExists($name, (int) $conf->entity);
			if ($constantExists < 0) {
				return -1;
			}
			if ($constantExists === 0) {
				if (dolibarr_set_const($this->db, $name, $value, 'chaine', 0, '', (int) $conf->entity) <= 0) {
					$this->error = $this->db->lasterror();
					return -1;
				}
			}
		}

		if ($this->mergeMulticompanySharingDefinition((int) $conf->entity) < 0) {
			return -1;
		}

		return 1;
	}

	/**
	 * Disable runtime integration while preserving module settings.
	 *
	 * @param string $options Disable options
	 * @return int<-1,1>
	 */
	public function remove($options = '')
	{
		global $conf;

		$result = $this->_remove(array(), $options);
		if ($result <= 0) {
			return $result;
		}
		if ($this->mergeMulticompanySharingDefinition((int) $conf->entity) < 0) {
			return -1;
		}

		return 1;
	}

	/**
	 * Merge the module definition without resetting existing sharing choices.
	 *
	 * @param int $entity Entity id
	 * @return int<-1,1>
	 */
	private function mergeMulticompanySharingDefinition($entity)
	{
		dol_include_once('/lmdbvehiclemanagement/class/actions_lmdbvehiclemanagement.class.php');
		$existing = json_decode(getDolGlobalString('MULTICOMPANY_EXTERNAL_MODULES_SHARING'), true);
		if (!is_array($existing)) {
			$existing = array();
		}
		$definition = class_exists('ActionsLmdbVehicleManagement') ? ActionsLmdbVehicleManagement::getMulticompanySharingDefinition() : array();
		$payload = array_replace_recursive($existing, $definition);
		$json = json_encode($payload);
		if (!is_string($json) || dolibarr_set_const($this->db, 'MULTICOMPANY_EXTERNAL_MODULES_SHARING', $json, 'chaine', 0, '', $entity) <= 0) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Distinguish a missing constant from configured values such as 0 or ''.
	 *
	 * @param string $name Constant name
	 * @param int $entity Entity id
	 * @return int<-1,1> -1 on error, 0 when absent, 1 when present
	 */
	private function entityConstantExists($name, $entity)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'const';
		$sql .= " WHERE name = '".$this->db->escape($this->db->encrypt($name, 0))."'";
		$sql .= ' AND entity = '.((int) $entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$exists = $this->db->num_rows($resql) > 0 ? 1 : 0;
		$this->db->free($resql);

		return $exists;
	}
}
