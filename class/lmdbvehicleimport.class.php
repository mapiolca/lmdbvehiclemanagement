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

	/**
	 * Derive a hidden imported reference from the mapped registration field.
	 *
	 * Dolibarr import rows have used both scalar and structured cells across
	 * supported versions, so the value is narrowed without relying on internals.
	 *
	 * @param array<int,mixed> $record Import row
	 * @param array<int,string> $fields Mapped fields
	 * @param int $index Current column
	 * @return string
	 */
	public function getRegistrationReference(&$record, $fields, $index)
	{
		$fieldIndex = array_search('t.registration_number', $fields, true);
		if ($fieldIndex === false || !array_key_exists($fieldIndex, $record)) {
			$this->error = 'RegistrationNumberRequiredForReference';
			$this->errors[] = $this->error;
			return '';
		}
		$value = $record[$fieldIndex];
		if (is_array($value)) {
			foreach (array('value', 'imported_value', 'raw') as $key) {
				if (isset($value[$key]) && is_scalar($value[$key])) {
					$value = $value[$key];
					break;
				}
			}
		}
		if (!is_scalar($value)) {
			$this->error = 'RegistrationNumberRequiredForReference';
			$this->errors[] = $this->error;
			return '';
		}

		return strtoupper(trim((string) $value));
	}
}
