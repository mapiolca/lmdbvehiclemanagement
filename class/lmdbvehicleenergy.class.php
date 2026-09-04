<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Access to the configurable vehicle-energy dictionary.
 */
class LmdbVehicleEnergy
{
	/** @var string Dictionary identifier used by native field rendering. */
	public $element = 'c_lmdbvehiclemanagement_energy';

	/** @var DoliDB */
	public $db;

	/** @var int */
	public $id = 0;

	/** @var string */
	public $code = '';

	/** @var string */
	public $label = '';

	/** @var int<0,1> */
	public $active = 1;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return the official French P.3 nomenclature distributed by default.
	 *
	 * @return array<string,string> Code to French label
	 */
	public static function getDefaultDefinitions()
	{
		return array(
			'ES' => 'Essence',
			'EG' => 'Essence-GPL',
			'EN' => 'Essence-gaz naturel',
			'EE' => 'Essence-électricité hybride rechargeable',
			'ER' => 'Essence-GPL-électricité hybride rechargeable',
			'EM' => 'Essence-gaz naturel-électricité hybride rechargeable',
			'EH' => 'Essence-électricité hybride non rechargeable',
			'EQ' => 'Essence-GPL-électricité hybride non rechargeable',
			'EP' => 'Essence-gaz naturel-électricité hybride non rechargeable',
			'FE' => 'Superéthanol',
			'FG' => 'Superéthanol-GPL',
			'FN' => 'Superéthanol-gaz naturel',
			'FL' => 'Superéthanol-électricité hybride rechargeable',
			'FH' => 'Superéthanol-électricité hybride non rechargeable',
			'FR' => 'Superéthanol-GPL-électricité hybride rechargeable',
			'FQ' => 'Superéthanol-GPL-électricité hybride non rechargeable',
			'FM' => 'Superéthanol-gaz naturel-électricité hybride rechargeable',
			'FP' => 'Superéthanol-gaz naturel-électricité hybride non rechargeable',
			'B1' => 'Biodiesel B100',
			'BL' => 'Biodiesel B100-électricité hybride rechargeable',
			'BH' => 'Biodiesel B100-électricité hybride non rechargeable',
			'GO' => 'Gazole',
			'GL' => 'Gazole-électricité hybride rechargeable',
			'GH' => 'Gazole-électricité hybride non rechargeable',
			'GF' => 'Gazole-gaz naturel dual fuel',
			'1A' => 'Gazole-gaz naturel dual fuel type 1A',
			'G2' => 'Gazole-GPL / dual fuel sans mode gazole seul',
			'GM' => 'Dual fuel gazole-gaz naturel-électricité hybride rechargeable',
			'GQ' => 'Dual fuel gazole-gaz naturel-électricité hybride non rechargeable',
			'GP' => 'GPL exclusif',
			'PE' => 'GPL-électricité hybride rechargeable',
			'PH' => 'GPL-électricité hybride non rechargeable',
			'GN' => 'Gaz naturel',
			'NE' => 'Gaz naturel-électricité hybride rechargeable',
			'NH' => 'Gaz naturel-électricité hybride non rechargeable',
			'EL' => 'Électricité',
			'ET' => 'Éthanol',
			'GA' => 'Gazogène',
			'GZ' => 'Autres hydrocarbures gazeux comprimés',
			'GG' => 'Gazogène-gazole',
			'GE' => 'Gazogène-essence',
			'PL' => 'Pétrole lampant',
			'AC' => 'Air comprimé',
			'H2' => 'Hydrogène',
			'HE' => 'Hydrogène-électricité hybride rechargeable',
			'HH' => 'Hydrogène-électricité hybride non rechargeable',
		);
	}

	/**
	 * Insert missing default values without changing administrator customizations.
	 *
	 * @return int<-1,1>
	 */
	public function seedDefaults()
	{
		global $conf;

		$entity = (int) $conf->entity;
		$position = 10;
		$this->db->begin();
		foreach (self::getDefaultDefinitions() as $code => $label) {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_energy (entity, code, label, position, active, date_creation)';
			$sql .= " SELECT ".$entity.", '".$this->db->escape($code)."', '".$this->db->escape($label)."', ".((int) $position).", 1, '".$this->db->idate(dol_now())."'";
			$sql .= ' WHERE NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_energy';
			$sql .= ' WHERE entity = '.$entity;
			$sql .= " AND code = '".$this->db->escape($code)."')";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->errors[] = $this->error;
				$this->db->rollback();
				return -1;
			}
			$position += 10;
		}
		$this->db->commit();

		return 1;
	}

	/**
	 * Fetch by row id, code or label. The signature matches Dolibarr's native
	 * fetchidfromcodeorlabel import conversion contract.
	 *
	 * @param int|string $id Row id or code when called with one argument
	 * @param string $code Dictionary code
	 * @param string $label Dictionary label
	 * @return int<-1,1>
	 */
	public function fetch($id = 0, $code = '', $label = '')
	{
		global $conf;

		$this->id = 0;
		$this->code = '';
		$this->label = '';

		$sql = 'SELECT rowid, code, label, active FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_energy WHERE ';
		$sql .= 'entity IN ('.getEntity('c_lmdbvehiclemanagement_energy').') AND (';
		if (is_numeric($id) && (int) $id > 0) {
			$sql .= 'rowid = '.((int) $id);
		} else {
			if ($code === '' && is_string($id)) {
				$code = $id;
			}
			if ($code !== '') {
				$sql .= "UPPER(code) = UPPER('".$this->db->escape(trim($code))."')";
			} elseif ($label !== '') {
				$sql .= "LOWER(label) = LOWER('".$this->db->escape(trim($label))."')";
			} else {
				return 0;
			}
		}
		$sql .= ')';
		$sql .= ' ORDER BY CASE WHEN entity = '.((int) $conf->entity).' THEN 0 ELSE 1 END, rowid';
		$sql .= ' LIMIT 2';
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
		$this->code = (string) $row->code;
		$this->label = (string) $row->label;
		$this->active = (int) $row->active > 0 ? 1 : 0;

		return 1;
	}

	/**
	 * Return active options plus the currently selected inactive value.
	 *
	 * @param int $selectedId Selected row id
	 * @return array<int,string>
	 */
	public function getSelectOptions($selectedId = 0)
	{
		global $conf, $langs;

		$options = array();
		$sql = 'SELECT rowid, entity, code, label FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_energy';
		$sql .= ' WHERE entity IN ('.getEntity('c_lmdbvehiclemanagement_energy').')';
		$sql .= ' AND (active = 1';
		if ($selectedId > 0) {
			$sql .= ' OR rowid = '.((int) $selectedId);
		}
		$sql .= ')';
		$sql .= ' ORDER BY CASE WHEN rowid = '.((int) $selectedId).' THEN 0 ELSE 1 END,';
		$sql .= ' CASE WHEN entity = '.((int) $conf->entity).' THEN 0 ELSE 1 END, position, code';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $options;
		}
		$codes = array();
		while (is_object($row = $this->db->fetch_object($resql))) {
			$code = (string) $row->code;
			if (isset($codes[$code])) {
				continue;
			}
			$codes[$code] = true;
			$translated = $langs->trans('VehicleEnergy'.$code);
			$label = $translated !== 'VehicleEnergy'.$code ? $translated : (string) $row->label;
			$options[(int) $row->rowid] = $code.' — '.$label;
		}
		$this->db->free($resql);

		return $options;
	}

	/**
	 * Return a translated display label for one dictionary row.
	 *
	 * @param int|null $id Row id, or null to render the already fetched row
	 * @return string
	 */
	public function getDisplayLabel($id = null)
	{
		global $langs;

		if ($id !== null && $this->fetch($id) <= 0) {
			return '';
		}
		if ($this->id <= 0) {
			return '';
		}
		$langs->load('lmdbvehiclemanagement@lmdbvehiclemanagement');
		$translated = $langs->transnoentities('VehicleEnergy'.$this->code);
		$label = $translated !== 'VehicleEnergy'.$this->code ? $translated : $this->label;

		return $this->code.' — '.$label;
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
		return dol_escape_htmltag($this->getDisplayLabel());
	}
}
