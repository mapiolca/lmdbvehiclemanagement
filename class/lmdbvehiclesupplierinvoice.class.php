<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/lmdbvehicleevent.class.php';
require_once __DIR__.'/lmdbvehicleregulatorycontrol.class.php';
require_once __DIR__.'/lmdbvehiclemanagementcompatibility.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';

/** Transactional relations between vehicle records and native supplier invoices. */
class LmdbVehicleSupplierInvoice
{
	/** @var array<string,string> Accepted native element identifiers. */
	public const SOURCE_TYPES = array(
		'event' => 'event', 'control' => 'control',
		'lmdbvehiclemanagement_lmdbvehicleevent' => 'event',
		'lmdbvehicleevent@lmdbvehiclemanagement' => 'event',
		'lmdbvehiclemanagement_lmdbvehicleregulatorycontrol' => 'control',
		'lmdbvehicleregulatorycontrol@lmdbvehiclemanagement' => 'control',
	);
	/** @var DoliDB */ private $db;
	/** @param DoliDB $db Database */
	public function __construct($db) { $this->db = $db; }

	/**
	 * Fetch through the object's native entity scope. Permissions remain explicit at callers.
	 * @param string $type Whitelisted source
	 * @param int $id Source id
	 * @return LmdbVehicleEvent|LmdbVehicleRegulatoryControl
	 * @throws RuntimeException
	 */
	public function fetchSource($type, $id)
	{
		if (!isset(self::SOURCE_TYPES[$type]) || $id <= 0) throw new RuntimeException('ErrorRecordNotFound');
		$source = self::SOURCE_TYPES[$type] === 'event' ? new LmdbVehicleEvent($this->db) : new LmdbVehicleRegulatoryControl($this->db);
		if ($source->fetch($id) <= 0) throw new RuntimeException('ErrorRecordNotFound');
		return $source;
	}

	/**
	 * One orientation, one transaction, one CRUD notification per actual change.
	 * The source row lock serializes concurrent requests for the same source.
	 * @param string $type Source type
	 * @param int $sourceId Source id
	 * @param int $invoiceId Invoice id
	 * @param User $user Actor
	 * @param bool $remove Unlink instead of link
	 * @return int 1 changed, 0 already in requested state
	 * @throws RuntimeException
	 */
	public function changeLink($type, $sourceId, $invoiceId, User $user, $remove = false)
	{
		global $conf;
		if (!LmdbVehicleManagementCompatibility::isFeatureAvailable('supplier_invoice_links') || !isModEnabled('lmdbvehiclemanagement')) throw new RuntimeException('LmdbRequiresSupplierInvoices');
		$source = $this->fetchSource($type, $sourceId);
		$right = $source instanceof LmdbVehicleEvent ? 'event' : 'regulatorycontrol';
		if (!empty($user->socid) || !$user->hasRight('lmdbvehiclemanagement', 'read') || !$user->hasRight('lmdbvehiclemanagement', $right, 'write')
			|| !$user->hasRight('fournisseur', 'facture', 'lire') || !$user->hasRight('fournisseur', 'facture', 'creer')) throw new RuntimeException('NotEnoughPermissions');
		$invoice = new FactureFournisseur($this->db);
		if ($invoice->fetch($invoiceId) <= 0) throw new RuntimeException('ErrorRecordNotFound');
		if ((int) $source->entity !== (int) $conf->entity || (int) $invoice->entity !== (int) $conf->entity) throw new RuntimeException('LmdbInvoiceSameEntity');
		$this->db->begin();
		try {
			$lock = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.$source->table_element.' WHERE rowid = '.((int) $source->id).' AND entity = '.((int) $conf->entity).' FOR UPDATE');
			if (!$lock || $this->db->num_rows($lock) !== 1) throw new RuntimeException('LmdbInvoiceLinkFailed');
			$this->db->free($lock);
			$aliases = array($source->getElementType(), $source->element.'@lmdbvehiclemanagement');
			$quoted = array_map(function ($value) { return "'".$this->db->escape($value)."'"; }, $aliases);
			// element_element has no entity column: both endpoints were checked above.
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'element_element WHERE (sourcetype IN ('.implode(',', $quoted).') AND fk_source = '.((int) $source->id)." AND targettype = 'invoice_supplier' AND fk_target = ".((int) $invoice->id).')';
			$sql .= " OR (sourcetype = 'invoice_supplier' AND fk_source = ".((int) $invoice->id).' AND targettype IN ('.implode(',', $quoted).') AND fk_target = '.((int) $source->id).')';
			$res = $this->db->query($sql);
			if (!$res) throw new RuntimeException('LmdbInvoiceLinkFailed');
			$ids = array();
			while (is_object($row = $this->db->fetch_object($res))) $ids[] = (int) $row->rowid;
			$this->db->free($res);
			if (($remove && !$ids) || (!$remove && $ids)) { $this->db->commit(); return 0; }
			if ($remove) {
				foreach ($ids as $linkId) {
					if ($invoice->deleteObjectLinked(0, '', 0, '', $linkId) < 0) throw new RuntimeException('LmdbInvoiceLinkFailed');
				}
			} elseif ($invoice->add_object_linked($source->getElementType(), $source->id, $user) <= 0) {
				throw new RuntimeException('LmdbInvoiceLinkFailed');
			}
			$source->oldcopy = clone $source;
			$source->context['trigger_reason'] = $remove ? 'supplier_invoice_unlink' : 'supplier_invoice_link';
			$source->context['changed_fields'] = array('linked_supplier_invoices');
			$source->context['supplier_invoice_id'] = (int) $invoice->id;
			if ($source->call_trigger($source->TRIGGER_PREFIX.'_UPDATE', $user) < 0) throw new RuntimeException('LmdbInvoiceLinkFailed');
			$source->clearObjectLinkedCache();
			$invoice->clearObjectLinkedCache();
			$this->db->commit();
			return 1;
		} catch (Throwable $e) {
			$this->db->rollback();
			throw $e;
		}
	}

	/**
	 * Resolve a native unlink request, checking that the displayed object is an endpoint.
	 * @param int $linkId Relation id
	 * @param CommonObject $current Displayed object
	 * @param User $user Actor
	 * @return bool False for unrelated native links (left to the core)
	 */
	public function unlink($linkId, $current, User $user)
	{
		$res = $this->db->query('SELECT sourcetype, fk_source, targettype, fk_target FROM '.MAIN_DB_PREFIX.'element_element WHERE rowid = '.((int) $linkId));
		if (!$res) throw new RuntimeException('LmdbInvoiceLinkFailed');
		$row = $this->db->fetch_object($res);
		$this->db->free($res);
		if (!is_object($row)) throw new RuntimeException('ErrorRecordNotFound');
		if (isset(self::SOURCE_TYPES[$row->sourcetype]) && $row->targettype === 'invoice_supplier') {
			$type = $row->sourcetype; $sourceId = (int) $row->fk_source; $invoiceId = (int) $row->fk_target;
		} elseif (isset(self::SOURCE_TYPES[$row->targettype]) && $row->sourcetype === 'invoice_supplier') {
			$type = $row->targettype; $sourceId = (int) $row->fk_target; $invoiceId = (int) $row->fk_source;
		} else return false;
		$currentType = $current->getElementType();
		if (!(($current->element === 'invoice_supplier' && (int) $current->id === $invoiceId)
			|| (isset(self::SOURCE_TYPES[$currentType]) && self::SOURCE_TYPES[$currentType] === self::SOURCE_TYPES[$type] && (int) $current->id === $sourceId))) throw new RuntimeException('NotEnoughPermissions');
		$this->changeLink($type, $sourceId, $invoiceId, $user, true);
		return true;
	}
}
