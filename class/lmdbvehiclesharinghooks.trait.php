<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Narrow native integration for module sharing; no global permission override. */
trait LmdbVehicleSharingHooks
{
	/** @param array<string,mixed> $parameters @param object|null $object @param string $action @param HookManager $hookmanager @return int */
	public function hookGetEntity($parameters, &$object, &$action, $hookmanager)
	{
		$this->resprints = '';
		$requested = (string) ($parameters['element'] ?? '');
		$element = LmdbVehicleSharing::element($requested);
		if ($element === '' || $requested === $element) return 0;
		$this->resprints = getEntity(LmdbVehicleSharing::scopeElement($element), (int) ($parameters['shared'] ?? 1), $object);
		return 1;
	}

	/**
	 * The native tooltip infers a read subpermission from write-only object branches.
	 * Authorize only this GET read, after the complete module/record/parent check.
	 * Old core versions provide objectid only: use the already fetched global object.
	 * @param array<string,mixed> $parameters @param object|null $unused @param string $action @param HookManager $hookmanager @return int
	 */
	public function restrictedArea($parameters, &$unused, &$action, $hookmanager)
	{
		global $user, $object;
		$this->results = array();
		if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'ajaxtooltip.php' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
			|| ($parameters['features'] ?? '') !== 'lmdbvehiclemanagement' || GETPOST('action', 'aZ09') !== '') return 0;
		$target = isset($parameters['object']) && is_object($parameters['object']) ? $parameters['object'] : $object;
		if (!($target instanceof LmdbVehicleManagementObject) || (int) $target->id !== (int) ($parameters['objectid'] ?? 0)) return 0;
		$this->results['result'] = LmdbVehicleSharing::canReadObject($this->db, $user, $target) ? 1 : 0;
		return $this->results['result'] === 1 ? 1 : 0;
	}

	/** @param array<string,mixed> $parameters @param CommonObject $object @param string $action @param HookManager $hookmanager @return int */
	public function printFieldListWhere($parameters, &$object, &$action, $hookmanager)
	{
		$this->resprints = '';
		if (!empty($parameters['showrefnav']) && $object instanceof LmdbVehicleManagementObject) {
			if (version_compare(DOL_VERSION, '24.0.0', '>=')) {
				// v24 native navigation consumes USF, not a raw SQL condition.
				$ids = array(0);
				$res = $this->db->query('SELECT te.rowid FROM '.MAIN_DB_PREFIX.$object->table_element.' te WHERE '.LmdbVehicleSharing::sql($this->db, $object->element, 'te'));
				if ($res) { while (is_object($row = $this->db->fetch_object($res))) $ids[] = (int) $row->rowid; $this->db->free($res); }
				$this->resprints = ' AND (te.rowid:in:'.implode(',', $ids).')';
			} else {
				$this->resprints = ' AND '.LmdbVehicleSharing::sql($this->db, $object->element, 'te');
			}
		}
		return 0;
	}

}
