<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Computed values required by the native Dolibarr import profile.
 */
class LmdbVehicleImport
{
	/** @var DoliDB */
	public $db;

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
	 * @param array<int,array<string,mixed>> $record Import row
	 * @param array<int,string> $fields Mapped fields
	 * @param int $index Current column
	 * @return int
	 */
	public function getCurrentEntityId(&$record, $fields, $index)
	{
		global $conf;

		return (int) $conf->entity;
	}

	/**
	 * @param array<int,array<string,mixed>> $record Import row
	 * @param array<int,string> $fields Mapped fields
	 * @param int $index Current column
	 * @return string
	 */
	public function getCreationDate(&$record, $fields, $index)
	{
		return $this->db->idate(dol_now());
	}
}
