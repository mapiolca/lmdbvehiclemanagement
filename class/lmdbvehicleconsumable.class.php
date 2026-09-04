<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Access to the entity-scoped consumable dictionary and its P.3 compatibility.
 */
class LmdbVehicleConsumable
{
	/** @var string Dictionary identifier used by native field rendering. */
	public $element = 'c_lmdbvehiclemanagement_consumable';

	/** @var DoliDB */
	public $db;
	/** @var int */
	public $id = 0;
	/** @var int */
	public $entity = 0;
	/** @var string */
	public $code = '';
	/** @var string */
	public $label = '';
	/** @var string */
	public $category = '';
	/** @var string */
	public $unit = '';
	/** @var int<0,1> */
	public $requires_oil_reference = 0;
	/** @var int<0,1> */
	public $active = 1;
	/** @var string */
	public $error = '';
	/** @var array<int,string> */
	public $errors = array();

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @return array<string,array{label:string,category:string,unit:string,oil:int,position:int}>
	 */
	public static function getDefaultDefinitions()
	{
		return array(
			'GASOLINE' => array('label' => 'Essence', 'category' => 'fuel', 'unit' => 'L', 'oil' => 0, 'position' => 10),
			'DIESEL' => array('label' => 'Gazole', 'category' => 'fuel', 'unit' => 'L', 'oil' => 0, 'position' => 20),
			'B100' => array('label' => 'Biodiesel B100', 'category' => 'fuel', 'unit' => 'L', 'oil' => 0, 'position' => 30),
			'ETHANOL' => array('label' => 'Éthanol / superéthanol', 'category' => 'fuel', 'unit' => 'L', 'oil' => 0, 'position' => 40),
			'LPG' => array('label' => 'GPL', 'category' => 'fuel', 'unit' => 'L', 'oil' => 0, 'position' => 50),
			'NATURAL_GAS' => array('label' => 'Gaz naturel', 'category' => 'fuel', 'unit' => 'M3', 'oil' => 0, 'position' => 60),
			'ELECTRICITY' => array('label' => 'Électricité', 'category' => 'fuel', 'unit' => 'KWH', 'oil' => 0, 'position' => 70),
			'HYDROGEN' => array('label' => 'Hydrogène', 'category' => 'fuel', 'unit' => 'KG', 'oil' => 0, 'position' => 80),
			'COMPRESSED_AIR' => array('label' => 'Air comprimé', 'category' => 'fuel', 'unit' => 'M3', 'oil' => 0, 'position' => 90),
			'ADBLUE' => array('label' => 'AdBlue', 'category' => 'additive', 'unit' => 'L', 'oil' => 0, 'position' => 110),
			'OIL' => array('label' => 'Huile', 'category' => 'additive', 'unit' => 'L', 'oil' => 1, 'position' => 120),
			'WASHER_FLUID' => array('label' => 'Liquide lave-glace', 'category' => 'additive', 'unit' => 'L', 'oil' => 0, 'position' => 130),
			'COOLANT' => array('label' => 'Liquide de refroidissement', 'category' => 'additive', 'unit' => 'L', 'oil' => 0, 'position' => 140),
			'OTHER_ADDITIVE' => array('label' => 'Autre additif', 'category' => 'additive', 'unit' => 'L', 'oil' => 0, 'position' => 150),
		);
	}

	/**
	 * @return array<string,list<string>> P.3 code to consumable codes
	 */
	public static function getDefaultEnergyCompatibility()
	{
		$compatibility = array(
			'ES' => array('GASOLINE'), 'EG' => array('GASOLINE', 'LPG'), 'EN' => array('GASOLINE', 'NATURAL_GAS'),
			'EE' => array('GASOLINE', 'ELECTRICITY'), 'ER' => array('GASOLINE', 'LPG', 'ELECTRICITY'), 'EM' => array('GASOLINE', 'NATURAL_GAS', 'ELECTRICITY'),
			'EH' => array('GASOLINE', 'ELECTRICITY'), 'EQ' => array('GASOLINE', 'LPG', 'ELECTRICITY'), 'EP' => array('GASOLINE', 'NATURAL_GAS', 'ELECTRICITY'),
			'FE' => array('ETHANOL'), 'FG' => array('ETHANOL', 'LPG'), 'FN' => array('ETHANOL', 'NATURAL_GAS'),
			'FL' => array('ETHANOL', 'ELECTRICITY'), 'FH' => array('ETHANOL', 'ELECTRICITY'), 'FR' => array('ETHANOL', 'LPG', 'ELECTRICITY'),
			'FQ' => array('ETHANOL', 'LPG', 'ELECTRICITY'), 'FM' => array('ETHANOL', 'NATURAL_GAS', 'ELECTRICITY'), 'FP' => array('ETHANOL', 'NATURAL_GAS', 'ELECTRICITY'),
			'B1' => array('B100'), 'BL' => array('B100', 'ELECTRICITY'), 'BH' => array('B100', 'ELECTRICITY'),
			'GO' => array('DIESEL'), 'GL' => array('DIESEL', 'ELECTRICITY'), 'GH' => array('DIESEL', 'ELECTRICITY'),
			'GF' => array('DIESEL', 'NATURAL_GAS'), '1A' => array('DIESEL', 'NATURAL_GAS'), 'G2' => array('DIESEL', 'LPG'),
			'GM' => array('DIESEL', 'NATURAL_GAS', 'ELECTRICITY'), 'GQ' => array('DIESEL', 'NATURAL_GAS', 'ELECTRICITY'),
			'GP' => array('LPG'), 'PE' => array('LPG', 'ELECTRICITY'), 'PH' => array('LPG', 'ELECTRICITY'),
			'GN' => array('NATURAL_GAS'), 'NE' => array('NATURAL_GAS', 'ELECTRICITY'), 'NH' => array('NATURAL_GAS', 'ELECTRICITY'),
			'EL' => array('ELECTRICITY'), 'ET' => array('ETHANOL'), 'GA' => array('NATURAL_GAS'), 'GZ' => array('NATURAL_GAS'),
			'GG' => array('NATURAL_GAS', 'DIESEL'), 'GE' => array('NATURAL_GAS', 'GASOLINE'), 'PL' => array('DIESEL'),
			'H2' => array('HYDROGEN'), 'HE' => array('HYDROGEN', 'ELECTRICITY'), 'HH' => array('HYDROGEN', 'ELECTRICITY'),
			'AC' => array('COMPRESSED_AIR'),
		);

		$combustionConsumables = array('GASOLINE', 'DIESEL', 'B100', 'ETHANOL', 'LPG', 'NATURAL_GAS');
		$adBlueConsumables = array('DIESEL', 'B100');
		foreach ($compatibility as $energyCode => $consumableCodes) {
			if (!empty(array_intersect($consumableCodes, $combustionConsumables))) {
				$consumableCodes[] = 'OIL';
			}
			if (!empty(array_intersect($consumableCodes, $adBlueConsumables))) {
				$consumableCodes[] = 'ADBLUE';
			}
			$consumableCodes[] = 'WASHER_FLUID';
			$consumableCodes[] = 'COOLANT';
			$consumableCodes[] = 'OTHER_ADDITIVE';
			$compatibility[$energyCode] = array_values(array_unique($consumableCodes));
		}

		return $compatibility;
	}

	/** @return int<-1,1> */
	public function seedDefaults()
	{
		global $conf, $user;

		$entity = (int) $conf->entity;
		$this->db->begin();
		foreach (self::getDefaultDefinitions() as $code => $definition) {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable';
			$sql .= ' (entity, code, label, category, unit, requires_oil_reference, position, active, date_creation)';
			$sql .= " SELECT ".$entity.", '".$this->db->escape($code)."', '".$this->db->escape($definition['label'])."', '".$this->db->escape($definition['category'])."', '".$this->db->escape($definition['unit'])."', ".((int) $definition['oil']).", ".((int) $definition['position']).", 1, '".$this->db->idate(dol_now())."'";
			$sql .= ' WHERE NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable';
			$sql .= ' WHERE entity = '.$entity." AND code = '".$this->db->escape($code)."')";
			if (!$this->db->query($sql)) {
				return $this->rollbackError();
			}
		}

		foreach (self::getDefaultEnergyCompatibility() as $energyCode => $consumableCodes) {
			foreach ($consumableCodes as $consumableCode) {
				$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumable_energy';
				$sql .= ' (entity, fk_consumable, fk_energy, date_creation, fk_user_creat)';
				$sql .= ' SELECT '.$entity.", c.rowid, e.rowid, '".$this->db->idate(dol_now())."', ".(is_object($user) ? (int) $user->id : 'NULL');
				$sql .= ' FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable AS c';
				$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_energy AS e ON e.entity = c.entity';
				$sql .= " WHERE c.entity = ".$entity." AND c.code = '".$this->db->escape($consumableCode)."'";
				$sql .= " AND e.code = '".$this->db->escape($energyCode)."'";
				$sql .= ' AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumable_energy AS ce';
				$sql .= ' WHERE ce.entity = '.$entity.' AND ce.fk_consumable = c.rowid AND ce.fk_energy = e.rowid)';
				if (!$this->db->query($sql)) {
					return $this->rollbackError();
				}
			}
		}
		$this->db->commit();

		return 1;
	}

	/** @return int<-1,-1> */
	private function rollbackError()
	{
		$this->error = $this->db->lasterror();
		$this->errors[] = $this->error;
		$this->db->rollback();
		return -1;
	}

	/** @param int $id Row id @return int<-1,1> */
	public function fetch($id)
	{
		$sql = 'SELECT rowid, entity, code, label, category, unit, requires_oil_reference, active';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable';
		$sql .= ' WHERE rowid = '.((int) $id).' AND entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($row)) {
			return 0;
		}
		$this->id = (int) $row->rowid;
		$this->entity = (int) $row->entity;
		$this->code = (string) $row->code;
		$this->label = self::displayLabel((string) $row->label);
		$this->category = (string) $row->category;
		$this->unit = (string) $row->unit;
		$this->requires_oil_reference = (int) $row->requires_oil_reference;
		$this->active = (int) $row->active;
		return 1;
	}

	/**
	 * @param string $category Empty, fuel or additive
	 * @param int $vehicleId Optional vehicle compatibility filter for fuels
	 * @return array<int,string>
	 */
	public function getOptions($category = '', $vehicleId = 0)
	{
		$sql = 'SELECT DISTINCT c.rowid, c.code, c.label, c.unit';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable AS c';
		if ($category === 'fuel' && $vehicleId > 0) {
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumable_energy AS ce ON ce.fk_consumable = c.rowid AND ce.entity = c.entity';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.fk_energy = ce.fk_energy';
		}
		$sql .= ' WHERE c.entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').') AND c.active = 1';
		if (in_array($category, array('fuel', 'additive'), true)) {
			$sql .= " AND c.category = '".$this->db->escape($category)."'";
		}
		if ($category === 'fuel' && $vehicleId > 0) {
			$sql .= ' AND ce.entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').')';
			$sql .= ' AND v.rowid = '.((int) $vehicleId).' AND v.entity IN ('.getEntity('lmdbvehicle').')';
		}
		$sql .= ' ORDER BY c.position, c.code';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}
		$options = array();
		while (is_object($row = $this->db->fetch_object($resql))) {
			$options[(int) $row->rowid] = self::displayLabel((string) $row->label).' ('.self::unitLabel((string) $row->unit).')';
		}
		$this->db->free($resql);
		return $options;
	}

	/**
	 * Return consumable labels and units separately for vehicle capacity inputs.
	 *
	 * @param int $energyId Optional energy dictionary id used to restrict compatible consumables
	 * @return array<int,array{label:string,unit:string,energy_ids:array<int,int>}>
	 */
	public function getCapacityOptions($energyId = 0)
	{
		$sql = 'SELECT c.rowid, c.label, c.unit, GROUP_CONCAT(DISTINCT ce.fk_energy ORDER BY ce.fk_energy SEPARATOR \',\') AS energy_ids';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable AS c';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumable_energy AS ce ON ce.fk_consumable = c.rowid AND ce.entity = c.entity';
		$sql .= ' WHERE c.entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').') AND c.active = 1';
		$sql .= ' AND ce.entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').')';
		if ($energyId > 0) {
			$sql .= ' AND ce.fk_energy = '.((int) $energyId);
		}
		$sql .= ' GROUP BY c.rowid, c.label, c.unit, c.position, c.code';
		$sql .= ' ORDER BY c.position, c.code';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}

		$options = array();
		while (is_object($row = $this->db->fetch_object($resql))) {
			$options[(int) $row->rowid] = array(
				'label' => self::displayLabel((string) $row->label),
				'unit' => self::unitLabel((string) $row->unit),
				'energy_ids' => array_values(array_filter(array_map('intval', explode(',', (string) $row->energy_ids)))),
			);
		}
		$this->db->free($resql);

		return $options;
	}

	/**
	 * Native dictionary rendering contract, as for Ccountry: label without a card link.
	 *
	 * @param int $withpicto Native argument, unused for dictionary entries
	 * @param string $option Native link option, unused for dictionary entries
	 * @param int $notooltip Native tooltip option, unused for dictionary entries
	 * @param string $morecss Native CSS option, unused for dictionary entries
	 * @param int $save_lastsearch_value Native search option, unused for dictionary entries
	 * @return string HTML-escaped label of the already fetched row
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		return $this->id > 0 ? dol_escape_htmltag(self::displayLabel($this->label)) : '';
	}

	/** @param string $label Stored dictionary label @return string */
	public static function displayLabel($label)
	{
		return html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/** @param string $unit Unit code @return string */
	public static function unitLabel($unit)
	{
		$labels = array('L' => 'L', 'KWH' => 'kWh', 'KG' => 'kg', 'M3' => 'm³');
		return isset($labels[$unit]) ? $labels[$unit] : $unit;
	}
}
