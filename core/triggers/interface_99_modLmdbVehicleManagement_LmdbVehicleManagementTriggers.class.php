<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleagenda.class.php');

/**
 * Module CRUD triggers.
 *
 * The native Agenda remains a separate user-managed view. This trigger does
 * not create ActionComm rows, which prevents duplication with the business
 * timeline.
 */
class InterfaceLmdbVehicleManagementTriggers extends DolibarrTriggers
{
	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = 'Les Métiers du Bâtiment';
		$this->description = 'Vehicle management CRUD triggers';
		$this->version = self::VERSIONS['dev'];
		$this->picto = 'car';
	}

	/**
	 * @param string $action Trigger code
	 * @param CommonObject $object Source object
	 * @param User $user User
	 * @param Translate $langs Languages
	 * @param Conf $conf Configuration
	 * @return int
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('lmdbvehiclemanagement')) {
			return 0;
		}

		$actions = LmdbVehicleAgenda::getTriggerDefinitions();
		if (in_array($action, array('ECMFILES_CREATE', 'ECMFILES_MODIFY'), true) && $object->element === 'ecmfiles'
			&& preg_match('/^lmdb-dossier-[0-9]+\.(pdf|zip)$/i', (string) $object->filename) && !empty($object->share)) {
			$langs->load('lmdbvehiclemanagement@lmdbvehiclemanagement');
			$this->error = $langs->trans('LmdbDossierNoPublicShare');
			return -1;
		}
		if ($action === 'BILL_SUPPLIER_CREATE' && isset($object->context['lmdb_invoice_source'])) {
			require_once __DIR__.'/../../class/lmdbvehiclesupplierinvoice.class.php';
			$source = $object->context['lmdb_invoice_source'];
			try {
				if (!($object instanceof FactureFournisseur) || !is_array($source) || !isset($source['type'], $source['id']) || !is_string($source['type']) || !is_int($source['id'])) throw new RuntimeException('LmdbInvoiceLinkFailed');
				$service = new LmdbVehicleSupplierInvoice($this->db);
				$service->changeLink($source['type'], $source['id'], (int) $object->id, $user);
				return 1;
			} catch (Throwable $e) {
				$langs->load('lmdbvehiclemanagement@lmdbvehiclemanagement');
				$this->error = $langs->trans($e->getMessage());
				return -1;
			}
		}
		if (!isset($actions[$action])) {
			return 0;
		}

		dol_syslog(__METHOD__.' action='.$action.' object_id='.((int) $object->id), LOG_INFO);
		return 0;
	}
}

