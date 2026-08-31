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
		global $conf, $langs, $user;

		$this->db = $db;
		$this->numero = 450026;
		$this->rights_class = 'lmdbvehiclemanagement';
		$this->rights_admin_allowed = 1;
		$this->family = 'Les Métiers du Bâtiment';
		$this->module_position = '30';
		$this->name = 'LmdbVehicleManagement';
		$this->description = 'ModuleLmdbVehicleManagementDesc';
		$this->descriptionlong = 'ModuleLmdbVehicleManagementDesc';
		$this->editor_name = 'Pierre Ardoin';
		$this->editor_url = 'https://github.com/mapiolca';
		$this->version = '0.11.0';
		$this->const_name = 'MAIN_MODULE_LMDBVEHICLEMANAGEMENT';
		$this->picto = 'car';

		$this->module_parts = array(
			'triggers' => 1,
			'js' => array('/lmdbvehiclemanagement/js/lmdbvehiclemanagement.js'),
			'hooks' => array(
				'data' => array(
					'main',
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
		$this->dictionaries = array(
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'tabname' => array('c_lmdbvehiclemanagement_energy', 'c_lmdbvehiclemanagement_consumable'),
			'tablib' => array('VehicleEnergies', 'VehicleConsumables'),
			'tabsql' => array(
				'SELECT f.rowid, f.code, f.label, f.position, f.active, f.entity FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_energy AS f WHERE f.entity IN ('.getEntity('c_lmdbvehiclemanagement_energy').')',
				'SELECT f.rowid, f.code, f.label, f.category, f.unit, f.requires_oil_reference, f.position, f.active, f.entity FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable AS f WHERE f.entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').')',
			),
			'tabsqlsort' => array('position ASC, code ASC', 'position ASC, code ASC'),
			'tabfield' => array('code,label,position', 'code,label,category,unit,requires_oil_reference,position'),
			'tabfieldvalue' => array('code,label,position', 'code,label,category,unit,requires_oil_reference,position'),
			'tabfieldinsert' => array('code,label,position,entity', 'code,label,category,unit,requires_oil_reference,position,entity'),
			'tabrowid' => array('rowid', 'rowid'),
			'tabcond' => array(isModEnabled('lmdbvehiclemanagement'), isModEnabled('lmdbvehiclemanagement')),
			'tabhelp' => array(
				array('code' => $langs->trans('VehicleEnergyCodeHelp'), 'label' => $langs->trans('VehicleEnergyLabelHelp')),
				array('code' => $langs->trans('ConsumableCodeHelp'), 'category' => $langs->trans('ConsumableCategoryHelp'), 'unit' => $langs->trans('ConsumableUnitHelp')),
			),
		);
		$this->boxes = array();
		$this->cronjobs = array(
			0 => array(
				'label' => 'InsuranceCertificateReminderCronLabel',
				'jobtype' => 'method',
				'class' => '/lmdbvehiclemanagement/class/lmdbvehicleinsurancecron.class.php',
				'objectname' => 'LmdbVehicleInsuranceCron',
				'method' => 'sendCertificateReminders',
				'parameters' => '',
				'comment' => 'InsuranceCertificateReminderCronComment',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 1,
				'test' => 'isModEnabled("lmdbvehiclemanagement")',
				'priority' => 50,
			),
		);

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

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionManageVehicleServiceStatus';
		$this->rights[$r][4] = 'lmdbvehicle';
		$this->rights[$r][5] = 'service';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionExportVehicles';
		$this->rights[$r][4] = 'lmdbvehicle';
		$this->rights[$r][5] = 'export';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionImportVehicles';
		$this->rights[$r][4] = 'lmdbvehicle';
		$this->rights[$r][5] = 'import';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionWriteInsurance';
		$this->rights[$r][4] = 'insurance';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionUploadInsuranceCertificate';
		$this->rights[$r][4] = 'insurance';
		$this->rights[$r][5] = 'upload';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionValidateInsuranceCertificate';
		$this->rights[$r][4] = 'insurance';
		$this->rights[$r][5] = 'validate';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionDeleteInsurance';
		$this->rights[$r][4] = 'insurance';
		$this->rights[$r][5] = 'delete';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionWriteConsumptions';
		$this->rights[$r][4] = 'consumption';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionDeleteConsumptions';
		$this->rights[$r][4] = 'consumption';
		$this->rights[$r][5] = 'delete';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionExportConsumptions';
		$this->rights[$r][4] = 'consumption';
		$this->rights[$r][5] = 'export';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'PermissionImportConsumptions';
		$this->rights[$r][4] = 'consumption';
		$this->rights[$r][5] = 'import';

		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => 'VehicleManagementTopMenu',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'lmdbvehiclemanagement',
			'leftmenu' => '',
			'url' => '/lmdbvehiclemanagement/vehicle_list.php',
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'position' => 30,
			'enabled' => 'isModEnabled("lmdbvehiclemanagement")',
			'perms' => '$user->hasRight("lmdbvehiclemanagement", "read")',
			'target' => '',
			'user' => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=lmdbvehiclemanagement',
			'type' => 'left',
			'titre' => 'VehicleMenuSection',
			'prefix' => img_picto('', 'car', 'class="pictofixedwidth valignmiddle paddingright"'),
			'mainmenu' => 'lmdbvehiclemanagement',
			'leftmenu' => 'lmdbvehiclemanagement_vehicles',
			'url' => '/lmdbvehiclemanagement/vehicle_list.php',
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'position' => 100,
			'enabled' => 'isModEnabled("lmdbvehiclemanagement")',
			'perms' => '$user->hasRight("lmdbvehiclemanagement", "read")',
			'target' => '',
			'user' => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=lmdbvehiclemanagement,fk_leftmenu=lmdbvehiclemanagement_vehicles',
			'type' => 'left',
			'titre' => 'NewVehicle',
			'mainmenu' => 'lmdbvehiclemanagement',
			'leftmenu' => 'lmdbvehiclemanagement_new',
			'url' => '/lmdbvehiclemanagement/vehicle_card.php?action=create',
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'position' => 101,
			'enabled' => 'isModEnabled("lmdbvehiclemanagement")',
			'perms' => '$user->hasRight("lmdbvehiclemanagement", "lmdbvehicle", "write")',
			'target' => '',
			'user' => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=lmdbvehiclemanagement,fk_leftmenu=lmdbvehiclemanagement_vehicles',
			'type' => 'left',
			'titre' => 'VehicleList',
			'mainmenu' => 'lmdbvehiclemanagement',
			'leftmenu' => 'lmdbvehiclemanagement_list',
			'url' => '/lmdbvehiclemanagement/vehicle_list.php',
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'position' => 102,
			'enabled' => 'isModEnabled("lmdbvehiclemanagement")',
			'perms' => '$user->hasRight("lmdbvehiclemanagement", "read")',
			'target' => '',
			'user' => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=lmdbvehiclemanagement,fk_leftmenu=lmdbvehiclemanagement_vehicles',
			'type' => 'left',
			'titre' => 'VehicleEvents',
			'mainmenu' => 'lmdbvehiclemanagement',
			'leftmenu' => 'lmdbvehiclemanagement_events',
			'url' => '/lmdbvehiclemanagement/vehicleevent_list.php',
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'position' => 103,
			'enabled' => 'isModEnabled("lmdbvehiclemanagement")',
			'perms' => '$user->hasRight("lmdbvehiclemanagement", "read")',
			'target' => '',
			'user' => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=lmdbvehiclemanagement',
			'type' => 'left',
			'titre' => 'ConsumptionMenuSection',
			'prefix' => img_picto('', 'gas-pump', 'class="pictofixedwidth valignmiddle paddingright"'),
			'mainmenu' => 'lmdbvehiclemanagement',
			'leftmenu' => 'lmdbvehiclemanagement_consumption',
			'url' => '/lmdbvehiclemanagement/consumption_index.php',
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'position' => 200,
			'enabled' => 'isModEnabled("lmdbvehiclemanagement")',
			'perms' => '$user->hasRight("lmdbvehiclemanagement", "read")',
			'target' => '',
			'user' => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=lmdbvehiclemanagement,fk_leftmenu=lmdbvehiclemanagement_consumption',
			'type' => 'left',
			'titre' => 'NewConsumption',
			'mainmenu' => 'lmdbvehiclemanagement',
			'leftmenu' => 'lmdbvehiclemanagement_consumption_new',
			'url' => '/lmdbvehiclemanagement/consumption_card.php?action=create',
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'position' => 201,
			'enabled' => 'isModEnabled("lmdbvehiclemanagement")',
			'perms' => '$user->hasRight("lmdbvehiclemanagement", "consumption", "write")',
			'target' => '',
			'user' => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=lmdbvehiclemanagement,fk_leftmenu=lmdbvehiclemanagement_consumption',
			'type' => 'left',
			'titre' => 'ConsumptionList',
			'mainmenu' => 'lmdbvehiclemanagement',
			'leftmenu' => 'lmdbvehiclemanagement_consumption_list',
			'url' => '/lmdbvehiclemanagement/consumption_list.php',
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'position' => 202,
			'enabled' => 'isModEnabled("lmdbvehiclemanagement")',
			'perms' => '$user->hasRight("lmdbvehiclemanagement", "read")',
			'target' => '',
			'user' => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=lmdbvehiclemanagement',
			'type' => 'left',
			'titre' => 'InsuranceContracts',
			'prefix' => img_picto('', 'shield-alt', 'class="pictofixedwidth valignmiddle paddingright"'),
			'mainmenu' => 'lmdbvehiclemanagement',
			'leftmenu' => 'lmdbvehiclemanagement_insurance',
			'url' => '/lmdbvehiclemanagement/insurancecontract_list.php',
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'position' => 300,
			'enabled' => 'isModEnabled("lmdbvehiclemanagement")',
			'perms' => '$user->hasRight("lmdbvehiclemanagement", "read")',
			'target' => '',
			'user' => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=lmdbvehiclemanagement,fk_leftmenu=lmdbvehiclemanagement_insurance',
			'type' => 'left',
			'titre' => 'NewInsuranceContractMenu',
			'mainmenu' => 'lmdbvehiclemanagement',
			'leftmenu' => 'lmdbvehiclemanagement_insurance_new',
			'url' => '/lmdbvehiclemanagement/insurancecontract_card.php?action=create',
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'position' => 301,
			'enabled' => 'isModEnabled("lmdbvehiclemanagement")',
			'perms' => '$user->hasRight("lmdbvehiclemanagement", "insurance", "write")',
			'target' => '',
			'user' => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=lmdbvehiclemanagement,fk_leftmenu=lmdbvehiclemanagement_insurance',
			'type' => 'left',
			'titre' => 'InsuranceContractList',
			'mainmenu' => 'lmdbvehiclemanagement',
			'leftmenu' => 'lmdbvehiclemanagement_insurance_list',
			'url' => '/lmdbvehiclemanagement/insurancecontract_list.php',
			'langs' => 'lmdbvehiclemanagement@lmdbvehiclemanagement',
			'position' => 302,
			'enabled' => 'isModEnabled("lmdbvehiclemanagement")',
			'perms' => '$user->hasRight("lmdbvehiclemanagement", "read")',
			'target' => '',
			'user' => 0,
		);

		$this->export_code = array();
		$this->export_label = array();
		$this->export_icon = array();
		$this->export_enabled = array();
		$this->export_permission = array();
		$this->export_fields_array = array();
		$this->export_TypeFields_array = array();
		$this->export_entities_array = array();
		$this->export_sql_start = array();
		$this->export_sql_end = array();
		$r = 0;
		$this->export_code[$r] = 'lmdbvehiclemanagement_vehicles';
		$this->export_label[$r] = 'VehicleExportDataset';
		$this->export_icon[$r] = 'car';
		$this->export_enabled[$r] = 'isModEnabled("lmdbvehiclemanagement") && $user->hasRight("lmdbvehiclemanagement", "lmdbvehicle", "export")';
		$this->export_permission[$r] = array(array('lmdbvehiclemanagement', 'lmdbvehicle', 'export'));
		$this->export_fields_array[$r] = array(
			't.ref' => 'Ref',
			't.registration_number' => 'RegistrationNumber',
			't.vin' => 'VIN',
			't.label' => 'Label',
			't.brand' => 'Brand',
			't.model' => 'VehicleModel',
			't.vehicle_version' => 'VehicleVersion',
			'energy.code' => 'VehicleEnergyCode',
			'energy.label' => 'VehicleEnergyLabel',
			't.wltp_range_km' => 'WltpRangeKm',
			't.first_registration_date' => 'FirstRegistrationDate',
			't.commissioning_date' => 'CommissioningDate',
			't.ownership_type' => 'OwnershipType',
			'owner.nom' => 'OwnerThirdParty',
			't.description' => 'Description',
			't.status' => 'Status',
			't.entity' => 'Environment',
		);
		$this->export_TypeFields_array[$r] = array(
			't.ref' => 'Text',
			't.registration_number' => 'Text',
			't.vin' => 'Text',
			't.label' => 'Text',
			't.brand' => 'Text',
			't.model' => 'Text',
			't.vehicle_version' => 'Text',
			'energy.code' => 'Text',
			'energy.label' => 'Text',
			't.wltp_range_km' => 'Numeric',
			't.first_registration_date' => 'Date',
			't.commissioning_date' => 'Date',
			't.ownership_type' => 'Text',
			'owner.nom' => 'Text',
			't.description' => 'Text',
			't.status' => 'Numeric',
			't.entity' => 'Numeric',
		);
		$this->export_entities_array[$r] = array_fill_keys(array_keys($this->export_fields_array[$r]), 'lmdbvehicle');
		if (getDolGlobalString('LMDBVEHICLEMANAGEMENT_LMDBVEHICLE_ADDON', 'mod_lmdbvehicle_standard') === 'mod_lmdbvehicle_registration') {
			unset($this->export_fields_array[$r]['t.ref'], $this->export_TypeFields_array[$r]['t.ref'], $this->export_entities_array[$r]['t.ref']);
		}
		$this->export_sql_start[$r] = 'SELECT DISTINCT ';
		$this->export_sql_end[$r] = ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS t';
		$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_energy AS energy ON energy.rowid = t.fk_energy';
		$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe AS owner ON owner.rowid = t.fk_soc_owner';
		$this->export_sql_end[$r] .= ' WHERE t.entity IN ('.getEntity('lmdbvehicle').')';

		$r++;
		$this->export_code[$r] = 'lmdbvehiclemanagement_consumptions';
		$this->export_label[$r] = 'ConsumptionExportDataset';
		$this->export_icon[$r] = 'gas-pump';
		$this->export_enabled[$r] = 'isModEnabled("lmdbvehiclemanagement") && $user->hasRight("lmdbvehiclemanagement", "consumption", "export")';
		$this->export_permission[$r] = array(array('lmdbvehiclemanagement', 'consumption', 'export'));
		$this->export_fields_array[$r] = array(
			't.ref' => 'Ref', 'r.reading_date' => 'Date', 'v.ref' => 'VehicleRef', 'v.registration_number' => 'RegistrationNumber',
			'u.login' => 'Driver', 'c.code' => 'ConsumableCode', 'c.label' => 'Consumable', 't.category_snapshot' => 'ConsumptionNature',
			't.quantity' => 'Quantity', 't.unit_snapshot' => 'Unit', 'r.odometer_km' => 'OdometerKm', 't.total_ttc' => 'TotalTTC',
			't.currency_snapshot' => 'Currency', 't.oil_reference' => 'OilReference', 't.description' => 'Description', 't.entity' => 'Environment',
		);
		$this->export_TypeFields_array[$r] = array(
			't.ref' => 'Text', 'r.reading_date' => 'Date', 'v.ref' => 'Text', 'v.registration_number' => 'Text', 'u.login' => 'Text',
			'c.code' => 'Text', 'c.label' => 'Text', 't.category_snapshot' => 'Text', 't.quantity' => 'Numeric', 't.unit_snapshot' => 'Text',
			'r.odometer_km' => 'Numeric', 't.total_ttc' => 'Numeric', 't.currency_snapshot' => 'Text', 't.oil_reference' => 'Text',
			't.description' => 'Text', 't.entity' => 'Numeric',
		);
		$this->export_entities_array[$r] = array_fill_keys(array_keys($this->export_fields_array[$r]), 'lmdbvehicleconsumption');
		$this->export_sql_start[$r] = 'SELECT DISTINCT ';
		$this->export_sql_end[$r] = ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumption AS t';
		$this->export_sql_end[$r] .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading AS r ON r.rowid = t.fk_odometer_reading AND r.entity = t.entity';
		$this->export_sql_end[$r] .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid = t.fk_vehicle AND v.entity = t.entity';
		$this->export_sql_end[$r] .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable AS c ON c.rowid = t.fk_consumable';
		$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user AS u ON u.rowid = COALESCE(t.fk_user_driver, t.fk_user_creat)';
		$this->export_sql_end[$r] .= ' WHERE t.entity IN ('.getEntity('lmdbvehicleconsumption').')';

		$this->import_code = array();
		$this->import_label = array();
		$this->import_icon = array();
		$this->import_entities_array = array();
		$this->import_tables_array = array();
		$this->import_tables_creator_array = array();
		$this->import_fields_array = array();
		$this->import_fieldshidden_array = array();
		$this->import_convertvalue_array = array();
		$this->import_regex_array = array();
		$this->import_examplevalues_array = array();
		$this->import_updatekeys_array = array();
		$this->import_run_sql_after_array = array();
		$this->import_TypeFields_array = array();
		$this->import_help_array = array();
		if (is_object($user) && $user->hasRight('lmdbvehiclemanagement', 'lmdbvehicle', 'import')) {
			$r = 0;
			$this->import_code[$r] = 'lmdbvehiclemanagement_vehicles';
			$this->import_label[$r] = 'VehicleImportDataset';
			$this->import_icon[$r] = 'car';
			$this->import_tables_array[$r] = array('t' => MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle');
			$this->import_tables_creator_array[$r] = array('t' => 'fk_user_creat');
			$this->import_fields_array[$r] = array(
				't.ref' => 'Ref',
				't.registration_number' => 'RegistrationNumber*',
				't.vin' => 'VIN',
				't.label' => 'Label*',
				't.brand' => 'Brand',
				't.model' => 'VehicleModel',
				't.vehicle_version' => 'VehicleVersion',
				't.fk_energy' => 'VehicleEnergyCode',
				't.wltp_range_km' => 'WltpRangeKm',
				't.first_registration_date' => 'FirstRegistrationDate',
				't.commissioning_date' => 'CommissioningDate',
				't.ownership_type' => 'OwnershipType',
				't.fk_soc_owner' => 'OwnerThirdParty',
				't.description' => 'Description',
			);
			$this->import_TypeFields_array[$r] = array(
				't.ref' => 'Text',
				't.registration_number' => 'Text',
				't.vin' => 'Text',
				't.label' => 'Text',
				't.brand' => 'Text',
				't.model' => 'Text',
				't.vehicle_version' => 'Text',
				't.fk_energy' => 'Text',
				't.wltp_range_km' => 'Numeric',
				't.first_registration_date' => 'Date',
				't.commissioning_date' => 'Date',
				't.ownership_type' => 'Text',
				't.fk_soc_owner' => 'Text',
				't.description' => 'Text',
			);
			$this->import_entities_array[$r] = array_fill_keys(array_keys($this->import_fields_array[$r]), 'lmdbvehicle');
			$this->import_fieldshidden_array[$r] = array(
				't.entity' => 'rule-compute',
				't.status' => 'const-0',
				't.date_creation' => 'rule-compute',
			);
			$this->import_convertvalue_array[$r] = array(
				't.ref' => array(
					'rule' => 'getrefifauto',
					'class' => getDolGlobalString('LMDBVEHICLEMANAGEMENT_LMDBVEHICLE_ADDON', 'mod_lmdbvehicle_standard'),
					'path' => '/lmdbvehiclemanagement/core/modules/lmdbvehiclemanagement/'.getDolGlobalString('LMDBVEHICLEMANAGEMENT_LMDBVEHICLE_ADDON', 'mod_lmdbvehicle_standard').'.php',
					'classobject' => 'LmdbVehicle',
					'pathobject' => '/lmdbvehiclemanagement/class/lmdbvehicle.class.php',
				),
				't.fk_energy' => array('rule' => 'fetchidfromcodeorlabel', 'file' => '/lmdbvehiclemanagement/class/lmdbvehicleenergy.class.php', 'class' => 'LmdbVehicleEnergy', 'method' => 'fetch', 'dict' => 'VehicleEnergies'),
				't.fk_soc_owner' => array('rule' => 'fetchidfromref', 'file' => '/societe/class/societe.class.php', 'class' => 'Societe', 'method' => 'fetch', 'element' => 'ThirdParty'),
				't.entity' => array('rule' => 'compute', 'file' => '/lmdbvehiclemanagement/class/lmdbvehicleimport.class.php', 'class' => 'LmdbVehicleImport', 'method' => 'getCurrentEntityId', 'type' => 'int'),
				't.date_creation' => array('rule' => 'compute', 'file' => '/lmdbvehiclemanagement/class/lmdbvehicleimport.class.php', 'class' => 'LmdbVehicleImport', 'method' => 'getCreationDate', 'type' => 'string'),
			);
			$this->import_regex_array[$r] = array();
			$this->import_examplevalues_array[$r] = array(
				't.ref' => '(PROV)',
				't.registration_number' => 'AA-123-BB',
				't.vin' => 'VF123456789012345',
				't.label' => 'Véhicule de service',
				't.brand' => 'Renault',
				't.model' => 'Kangoo',
				't.vehicle_version' => 'Confort',
				't.fk_energy' => 'EL',
				't.first_registration_date' => '2026-01-15',
				't.commissioning_date' => '',
				't.ownership_type' => 'owned',
				't.fk_soc_owner' => 'FOURNISSEUR-001',
				't.description' => 'Véhicule affecté aux interventions',
			);
			$this->import_updatekeys_array[$r] = array();
			$this->import_run_sql_after_array[$r] = array();
			if (getDolGlobalString('LMDBVEHICLEMANAGEMENT_LMDBVEHICLE_ADDON', 'mod_lmdbvehicle_standard') === 'mod_lmdbvehicle_registration') {
				unset($this->import_fields_array[$r]['t.ref'], $this->import_TypeFields_array[$r]['t.ref'], $this->import_examplevalues_array[$r]['t.ref']);
				$this->import_fieldshidden_array[$r]['t.ref'] = 'rule-compute';
				$this->import_convertvalue_array[$r]['t.ref'] = array(
					'rule' => 'compute',
					'file' => '/lmdbvehiclemanagement/class/lmdbvehicleimport.class.php',
					'class' => 'LmdbVehicleImport',
					'method' => 'getRegistrationReference',
					'type' => 'string',
				);
				$this->import_entities_array[$r] = array_fill_keys(array_keys($this->import_fields_array[$r]), 'lmdbvehicle');
				$this->import_entities_array[$r]['t.ref'] = 'lmdbvehicle';
			}
		}
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

		if ($this->prepareVehicleSchema() < 0) {
			return -1;
		}
		if ($this->prepareInsuranceContractSchema() < 0) {
			return -1;
		}

		$result = $this->_load_tables('/lmdbvehiclemanagement/sql/');
		if ($result < 0) {
			return -1;
		}

		dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleenergy.class.php');
		$energyDictionary = new LmdbVehicleEnergy($this->db);
		if ($energyDictionary->seedDefaults() < 0) {
			$this->error = $energyDictionary->error;
			return -1;
		}
		dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumable.class.php');
		$consumableDictionary = new LmdbVehicleConsumable($this->db);
		if ($consumableDictionary->seedDefaults() < 0) {
			$this->error = $consumableDictionary->error;
			return -1;
		}
		if ($this->migrateVehicleData((int) $conf->entity) < 0) {
			return -1;
		}

		$result = $this->_init(array(), $options);
		if ($result <= 0) {
			return $result;
		}
		if ($this->ensureInsuranceEmailTemplates((int) $conf->entity) < 0) {
			return -1;
		}

		$defaults = array(
			'MAIN_MODULE_LMDBVEHICLEMANAGEMENT_ICON' => 'fa-car',
			'LMDBVEHICLEMANAGEMENT_LMDBVEHICLE_ADDON' => 'mod_lmdbvehicle_standard',
			'LMDBVEHICLEMANAGEMENT_LMDBVEHICLEEVENT_ADDON' => 'mod_lmdbvehicleevent_standard',
			'LMDBVEHICLEMANAGEMENT_CONSUMPTION_ADDON' => 'mod_lmdbvehicleconsumption_standard',
			'LMDBVEHICLEMANAGEMENT_INSURANCECONTRACT_ADDON' => 'mod_lmdbinsurancecontract_standard',
			'LMDBVEHICLEMANAGEMENT_INSURANCE_REMINDERS_ENABLED' => '0',
			'LMDBVEHICLEMANAGEMENT_INSURANCE_INCLUDE_ASSIGNEES' => '1',
			'LMDBVEHICLEMANAGEMENT_INSURANCE_ASSIGNMENT_TYPES' => '["driver"]',
			'LMDBVEHICLEMANAGEMENT_INSURANCE_BEFORE_DAYS' => '[30,15,7,1]',
			'LMDBVEHICLEMANAGEMENT_INSURANCE_OVERDUE_REPEAT_DAYS' => '7',
			'LMDBVEHICLEMANAGEMENT_INSURANCE_REVIEW_REPEAT_DAYS' => '3',
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
	 * Add the energy foreign key before loading index scripts on an upgrade.
	 *
	 * @return int<-1,1>
	 */
	private function prepareVehicleSchema()
	{
		$table = MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle';
		$tableExists = $this->tableExists($table);
		if ($tableExists < 0) {
			return -1;
		}
		if ($tableExists === 0) {
			return 1;
		}
		$fieldExists = $this->tableFieldExists($table, 'fk_energy');
		if ($fieldExists < 0) {
			return -1;
		}
		if ($fieldExists === 0 && !$this->db->query('ALTER TABLE '.$table.' ADD COLUMN fk_energy integer DEFAULT NULL AFTER vehicle_version')) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$fieldExists = $this->tableFieldExists($table, 'wltp_range_km');
		if ($fieldExists < 0) {
			return -1;
		}
		if ($fieldExists === 0 && !$this->db->query('ALTER TABLE '.$table.' ADD COLUMN wltp_range_km double(24,8) DEFAULT NULL AFTER fk_energy')) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Add native note fields before loading table scripts on an upgrade.
	 *
	 * @return int<-1,1>
	 */
	private function prepareInsuranceContractSchema()
	{
		$table = MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract';
		$tableExists = $this->tableExists($table);
		if ($tableExists < 0) {
			return -1;
		}
		if ($tableExists === 0) {
			return 1;
		}
		$fields = array(
			'note_public' => 'ALTER TABLE '.$table.' ADD COLUMN note_public text DEFAULT NULL AFTER description',
			'note_private' => 'ALTER TABLE '.$table.' ADD COLUMN note_private text DEFAULT NULL AFTER note_public',
		);
		foreach ($fields as $field => $sql) {
			$fieldExists = $this->tableFieldExists($table, $field);
			if ($fieldExists < 0) {
				return -1;
			}
			if ($fieldExists === 0 && !$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Create editable native email templates once and select them only when no
	 * administrator choice exists yet.
	 *
	 * @param int $entity Entity id
	 * @return int<-1,1>
	 */
	private function ensureInsuranceEmailTemplates($entity)
	{
		global $langs;

		$langs->loadLangs(array('mails', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
		$templates = array(
			'LMDBVEHICLEMANAGEMENT_INSURANCE_REQUEST_TEMPLATE' => array(
				'type' => 'lmdbvehicle_insurance_request',
				'label' => $langs->trans('InsuranceRequestEmailTemplateLabel'),
				'topic' => $langs->trans('InsuranceRequestEmailSubject'),
				'content' => $langs->trans('InsuranceRequestEmailContent'),
				'position' => 10,
			),
			'LMDBVEHICLEMANAGEMENT_INSURANCE_REVIEW_TEMPLATE' => array(
				'type' => 'lmdbvehicle_insurance_review',
				'label' => $langs->trans('InsuranceReviewEmailTemplateLabel'),
				'topic' => $langs->trans('InsuranceReviewEmailSubject'),
				'content' => $langs->trans('InsuranceReviewEmailContent'),
				'position' => 20,
			),
		);
		foreach ($templates as $constant => $template) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'c_email_templates';
			$sql .= ' WHERE entity = '.((int) $entity)." AND module = 'lmdbvehiclemanagement'";
			$sql .= " AND type_template = '".$this->db->escape($template['type'])."' ORDER BY rowid LIMIT 1";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$row = $this->db->fetch_object($resql);
			$this->db->free($resql);
			$templateId = is_object($row) ? (int) $row->rowid : 0;
			if ($templateId <= 0) {
				$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'c_email_templates';
				$sql .= ' (entity, module, type_template, lang, private, fk_user, datec, label, position, enabled, active, topic, content, content_lines, joinfiles) VALUES (';
				$sql .= ((int) $entity).", 'lmdbvehiclemanagement', '".$this->db->escape($template['type'])."', '', 0, NULL, '".$this->db->idate(dol_now())."', '";
				$sql .= $this->db->escape($template['label'])."', ".((int) $template['position']).", 1, 1, '".$this->db->escape($template['topic'])."', '";
				$sql .= $this->db->escape($template['content'])."', NULL, 0)";
				if (!$this->db->query($sql)) {
					$this->error = $this->db->lasterror();
					return -1;
				}
				$templateId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'c_email_templates');
			}
			$constantExists = $this->entityConstantExists($constant, $entity);
			if ($constantExists < 0) {
				return -1;
			}
			if ($constantExists === 0 && dolibarr_set_const($this->db, $constant, (string) $templateId, 'chaine', 0, '', $entity) <= 0) {
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Migrate legacy status codes and free-text energies once per entity.
	 *
	 * The historical energy column is intentionally retained on upgraded
	 * installations as a read-only migration source. New installations only
	 * create fk_energy, so it is never a competing source of truth.
	 *
	 * @param int $entity Entity id
	 * @return int<-1,1>
	 */
	private function migrateVehicleData($entity)
	{
		$table = MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle';
		$marker = 'LMDBVEHICLEMANAGEMENT_STATUS_SCHEMA_VERSION';
		$this->db->begin();
		$markerExists = $this->entityConstantExists($marker, $entity);
		if ($markerExists < 0) {
			$this->db->rollback();
			return -1;
		}
		if ($markerExists === 0) {
			$sql = 'UPDATE '.$table.' SET status = CASE status';
			$sql .= ' WHEN 1 THEN 2 WHEN 2 THEN 3 WHEN 9 THEN 4 ELSE status END';
			$sql .= ' WHERE entity = '.((int) $entity).' AND status IN (1, 2, 9)';
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
			if (dolibarr_set_const($this->db, $marker, '2', 'chaine', 0, '', $entity) <= 0) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
		}

		$legacyFieldExists = $this->tableFieldExists($table, 'energy');
		if ($legacyFieldExists < 0) {
			$this->db->rollback();
			return -1;
		}
		if ($legacyFieldExists === 0) {
			$this->db->commit();
			return 1;
		}

		$sql = 'SELECT rowid, energy FROM '.$table;
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_energy IS NULL';
		$sql .= " AND energy IS NOT NULL AND TRIM(energy) <> ''";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		while (is_object($row = $this->db->fetch_object($resql))) {
			$energyId = $this->resolveLegacyEnergyId((string) $row->energy, $entity);
			if ($energyId <= 0) {
				$this->db->free($resql);
				$this->db->rollback();
				return -1;
			}
			$updateSql = 'UPDATE '.$table.' SET fk_energy = '.((int) $energyId);
			$updateSql .= ' WHERE rowid = '.((int) $row->rowid).' AND entity = '.((int) $entity).' AND fk_energy IS NULL';
			if (!$this->db->query($updateSql)) {
				$this->error = $this->db->lasterror();
				$this->db->free($resql);
				$this->db->rollback();
				return -1;
			}
		}
		$this->db->free($resql);
		$this->db->commit();

		return 1;
	}

	/**
	 * Resolve a historical free-text energy without discarding unknown values.
	 *
	 * @param string $value Historical value
	 * @param int $entity Entity id
	 * @return int<-1,max>
	 */
	private function resolveLegacyEnergyId($value, $entity)
	{
		$normalized = strtoupper(trim($value));
		$aliases = array(
			'ESSENCE' => 'ES',
			'DIESEL' => 'GO',
			'GAZOLE' => 'GO',
			'ELECTRIQUE' => 'EL',
			'ÉLECTRIQUE' => 'EL',
			'ELECTRIC' => 'EL',
			'GPL' => 'GP',
			'GNV' => 'GN',
			'GAZ NATUREL' => 'GN',
			'HYDROGENE' => 'H2',
			'HYDROGÈNE' => 'H2',
			'E85' => 'FE',
			'SUPERETHANOL' => 'FE',
			'SUPERÉTHANOL' => 'FE',
			'B100' => 'B1',
			'BIODIESEL B100' => 'B1',
		);
		$code = isset($aliases[$normalized]) ? $aliases[$normalized] : $normalized;
		dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleenergy.class.php');
		$energy = new LmdbVehicleEnergy($this->db);
		$result = $energy->fetch(0, $code);
		if ($result < 0) {
			$this->error = $energy->error;
			return -1;
		}
		if ($result > 0) {
			return (int) $energy->id;
		}

		$legacyCode = 'LEGACY_'.strtoupper(substr(sha1($value), 0, 16));
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_energy (entity, code, label, position, active, date_creation)';
		$sql .= " SELECT ".((int) $entity).", '".$this->db->escape($legacyCode)."', '".$this->db->escape(trim($value))."', 9999, 1, '".$this->db->idate(dol_now())."'";
		$sql .= ' WHERE NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_energy';
		$sql .= ' WHERE entity = '.((int) $entity);
		$sql .= " AND code = '".$this->db->escape($legacyCode)."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$result = $energy->fetch(0, $legacyCode);
		if ($result <= 0) {
			$this->error = $energy->error !== '' ? $energy->error : 'EnergyMigrationFailed';
			return -1;
		}

		return (int) $energy->id;
	}

	/**
	 * @param string $table Full table name
	 * @return int<-1,1>
	 */
	private function tableExists($table)
	{
		$sql = 'SELECT COUNT(*) AS nb FROM information_schema.TABLES';
		$sql .= " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '".$this->db->escape($table)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return is_object($row) && (int) $row->nb > 0 ? 1 : 0;
	}

	/**
	 * @param string $table Full table name
	 * @param string $field Column name
	 * @return int<-1,1>
	 */
	private function tableFieldExists($table, $field)
	{
		$sql = 'SELECT COUNT(*) AS nb FROM information_schema.COLUMNS';
		$sql .= " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '".$this->db->escape($table)."'";
		$sql .= " AND COLUMN_NAME = '".$this->db->escape($field)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return is_object($row) && (int) $row->nb > 0 ? 1 : 0;
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
