<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Hooks restricted to the native invoice and the two vehicle source cards. */
trait LmdbVehicleSupplierInvoiceHooks
{
	/**
	 * @param array<string,mixed> $parameters
	 * @param CommonObject $object
	 * @param string $action
	 * @param HookManager $hookmanager
	 * @return int
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $conf, $langs;
		if (!isModEnabled('lmdbvehiclemanagement')) return 0;
		if ($object instanceof LmdbVehicle && $action === 'builddoc') {
			require_once __DIR__.'/lmdbvehicledossier.class.php';
			if (!$user->hasRight('lmdbvehiclemanagement', 'read') || !$user->hasRight('lmdbvehiclemanagement', 'lmdbvehicle', 'write') || !$user->hasRight('fournisseur', 'facture', 'lire') || !empty($user->socid)) accessforbidden();
			if ($object->generateDocument(GETPOST('model', 'aZ09'), $langs) > 0) setEventMessages($langs->trans('FileSuccessfullyBuilt'), null, 'mesgs');
			else setEventMessages($object->error, $object->errors, 'errors');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id));
			exit;
		}
		$contexts = explode(':', (string) ($parameters['context'] ?? ''));
		if (!array_intersect($contexts, array('invoicesuppliercard', 'lmdbvehicleeventcard', 'lmdbvehicleregulatorycontrolcard'))) return 0;
		require_once __DIR__.'/lmdbvehiclesupplierinvoice.class.php';
		$langs->loadLangs(array('bills', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
		$service = new LmdbVehicleSupplierInvoice($this->db);
		$type = GETPOST('lmdb_source_type', 'aZ09');
		$sourceId = GETPOSTINT('lmdb_source_id');
		if (GETPOST('cancel', 'alpha')) return 0;
		try {
			if ($object instanceof FactureFournisseur && $action === 'add' && ($type !== '' || $sourceId > 0)) {
				$source = $service->fetchSource($type, $sourceId);
				$right = $source instanceof LmdbVehicleEvent ? 'event' : 'regulatorycontrol';
				if (!LmdbVehicleManagementCompatibility::isFeatureAvailable('supplier_invoice_links') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !$user->hasRight('lmdbvehiclemanagement', $right, 'write') || !$user->hasRight('fournisseur', 'facture', 'lire') || !$user->hasRight('fournisseur', 'facture', 'creer') || !empty($user->socid)) throw new RuntimeException('NotEnoughPermissions');
				if ((int) $source->entity !== (int) $conf->entity) throw new RuntimeException('LmdbInvoiceSameEntity');
				$object->context['lmdb_invoice_source'] = array('type' => $type, 'id' => $sourceId);
				return 0; // Core creates the draft; BILL_SUPPLIER_CREATE links before commit.
			}
			$handled = false;
			if ($action === 'lmdb_link_invoice' && ($object instanceof LmdbVehicleEvent || $object instanceof LmdbVehicleRegulatoryControl)) {
				$service->changeLink($object->getElementType(), (int) $object->id, GETPOSTINT('lmdb_invoice_id'), $user);
				$handled = true;
			} elseif ($action === 'dellink' && GETPOSTINT('dellinkid') > 0) {
				// Native invoice doActions runs before its usual fetch.
				if (empty($object->id) && $object->fetch(GETPOSTINT('id')) <= 0) throw new RuntimeException('ErrorRecordNotFound');
				$handled = $service->unlink(GETPOSTINT('dellinkid'), $object, $user);
			} elseif ($object instanceof FactureFournisseur && in_array($action, array('addlink', 'addlinkbyref'), true) && isset(LmdbVehicleSupplierInvoice::SOURCE_TYPES[GETPOST('addlink', 'alpha')])) {
				if ($object->fetch(GETPOSTINT('id')) <= 0) throw new RuntimeException('ErrorRecordNotFound');
				$type = GETPOST('addlink', 'alpha');
				if ($action === 'addlinkbyref') {
					$source = LmdbVehicleSupplierInvoice::SOURCE_TYPES[$type] === 'event' ? new LmdbVehicleEvent($this->db) : new LmdbVehicleRegulatoryControl($this->db);
					if ($source->fetch(0, GETPOST('reftolinkto', 'alphanohtml')) <= 0) throw new RuntimeException('ErrorRecordNotFound');
					$ids = array((int) $source->id);
				} else {
					// The native selector changed from one id to multiple ids in v21.
					$ids = version_compare(DOL_VERSION, '21.0.0', '>=') ? GETPOST('idtolinkto', 'array:int') : array(GETPOSTINT('idtolinkto'));
					if (!is_array($ids) || !$ids) throw new RuntimeException('ErrorRecordNotFound');
				}
				$this->db->begin();
				try {
					foreach ($ids as $id) $service->changeLink($type, $id, (int) $object->id, $user);
					$this->db->commit();
				} catch (Throwable $e) { $this->db->rollback(); throw $e; }
				$handled = true;
			}
			if (!$handled) return 0;
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id));
			exit;
		} catch (Throwable $e) {
			$this->error = $langs->trans($e->getMessage());
			$action = $action === 'add' ? 'create' : 'view';
			return -1;
		}
	}

	/**
	 * @param array<string,mixed> $parameters
	 * @param CommonObject $object
	 * @param string $action
	 * @param HookManager $hookmanager
	 * @return int
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		$this->resprints = '';
		if ($object->element !== 'invoice_supplier' || !in_array($action, array('create', 'add'), true)) return 0;
		$type = GETPOST('lmdb_source_type', 'aZ09');
		$id = GETPOSTINT('lmdb_source_id');
		if ($type !== '' || $id > 0) {
			$this->resprints = '<input type="hidden" name="lmdb_source_type" value="'.dol_escape_htmltag($type).'"><input type="hidden" name="lmdb_source_id" value="'.$id.'">';
		}
		return 0;
	}

	/**
	 * @param array<string,mixed> $parameters
	 * @param CommonObject $object
	 * @param string $action
	 * @param HookManager $hookmanager
	 * @return int
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;
		$this->resprints = '';
		if (!($object instanceof LmdbVehicleEvent) && !($object instanceof LmdbVehicleRegulatoryControl)) return 0;
		$right = $object instanceof LmdbVehicleEvent ? 'event' : 'regulatorycontrol';
		if (!LmdbVehicleManagementCompatibility::isFeatureAvailable('supplier_invoice_links') || (int) $object->entity !== (int) $conf->entity || !$user->hasRight('lmdbvehiclemanagement', 'read') || !$user->hasRight('lmdbvehiclemanagement', $right, 'write') || !$user->hasRight('fournisseur', 'facture', 'lire') || !$user->hasRight('fournisseur', 'facture', 'creer') || !empty($user->socid)) return 0;
		$type = $object instanceof LmdbVehicleEvent ? 'event' : 'control';
		$supplierId = (int) ($type === 'event' ? $object->fk_soc : $object->fk_soc_provider);
		$url = DOL_URL_ROOT.'/fourn/facture/card.php?action=create&lmdb_source_type='.$type.'&lmdb_source_id='.((int) $object->id);
		if ($supplierId > 0) {
			$sql = 'SELECT s.rowid FROM '.MAIN_DB_PREFIX.'societe s WHERE s.rowid = '.$supplierId.' AND s.fournisseur = 1 AND s.entity IN ('.getEntity('societe').')';
			if (!$user->hasRight('societe', 'client', 'voir')) $sql .= ' AND EXISTS (SELECT sc.rowid FROM '.MAIN_DB_PREFIX.'societe_commerciaux sc WHERE sc.fk_soc = s.rowid AND sc.fk_user = '.((int) $user->id).')';
			$res = $this->db->query($sql);
			if ($res) { if ($this->db->num_rows($res) === 1) $url .= '&socid='.$supplierId; $this->db->free($res); }
		}
		$this->resprints = dolGetButtonAction('', $langs->trans('LmdbCreateSupplierInvoice'), 'default', $url);
		return 0;
	}

	/**
	 * Extend the invoice's native Link to menu, including sources without a provider.
	 * @param array<string,mixed> $parameters
	 * @param CommonObject $object
	 * @param string $action
	 * @param HookManager $hookmanager
	 * @return int
	 */
	public function showLinkToObjectBlock($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;
		$this->results = array();
		if ($object->element !== 'invoice_supplier' || !LmdbVehicleManagementCompatibility::isFeatureAvailable('supplier_invoice_links') || (int) $object->entity !== (int) $conf->entity || !$user->hasRight('lmdbvehiclemanagement', 'read') || !$user->hasRight('fournisseur', 'facture', 'lire') || !$user->hasRight('fournisseur', 'facture', 'creer') || !empty($user->socid)) return 0;
		$langs->load('lmdbvehiclemanagement@lmdbvehiclemanagement');
		foreach (array('event' => array('vehicle_event', 'lmdbvehicleevent', 'VehicleEvent'), 'regulatorycontrol' => array('regulatory_control', 'lmdbvehicleregulatorycontrol', 'RegulatoryControl')) as $right => $definition) {
			if (!$user->hasRight('lmdbvehiclemanagement', $right, 'write')) continue;
			$this->results['lmdbvehiclemanagement_'.$definition[1]] = array('enabled' => true, 'perms' => true, 'label' => $definition[2],
				'sql' => 'SELECT t.rowid, t.ref, 0 AS socid, v.label AS name, 0 AS client, 0 AS fournisseur, NULL AS ref_supplier, NULL AS total_ht FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_'.$definition[0].' t INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle v ON v.rowid = t.fk_vehicle AND v.entity IN ('.getEntity('lmdbvehicle').') WHERE t.entity = '.((int) $conf->entity).' ORDER BY t.ref');
		}
		return 0;
	}

	/**
	 * Filter confidential invoice rows before the native renderer.
	 * @param array<string,mixed> $parameters
	 * @param CommonObject $object
	 * @param string $action
	 * @param HookManager $hookmanager
	 * @return int
	 */
	public function showLinkedObjectBlock($parameters, &$object, &$action, $hookmanager)
	{
		global $user;
		$this->resprints = '';
		if ($object instanceof LmdbVehicleEvent || $object instanceof LmdbVehicleRegulatoryControl) {
			foreach (($object->linkedObjects['invoice_supplier'] ?? array()) as $key => $invoice) {
				if (!$user->hasRight('fournisseur', 'facture', 'lire') || !in_array((int) $invoice->entity, array_map('intval', explode(',', getEntity('supplier_invoice'))), true)) unset($object->linkedObjects['invoice_supplier'][$key]);
			}
			if (empty($object->linkedObjects['invoice_supplier'])) unset($object->linkedObjects['invoice_supplier']);
		}
		return 0;
	}
}
