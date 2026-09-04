<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/paymentvarious.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumption.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementsecureupload.class.php');

/**
 * Orchestrate the native Various Payment attached to a fuel/recharge entry.
 *
 * The module consumption remains the source of the date, amount, label and
 * project until the native bank line is reconciled or transferred to the
 * accounting ledger. Bank, payment mode and accounting accounts are creation
 * snapshots, matching the native Various Payment behavior.
 */
class LmdbVehicleConsumptionPayment
{
	public const CONST_ENABLED = 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_ENABLED';
	public const CONST_BANK_ACCOUNT = 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_BANK_ACCOUNT';
	public const CONST_PAYMENT_MODE = 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_PAYMENT_MODE';
	public const CONST_ACCOUNTING_ACCOUNT = 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_ACCOUNTING_ACCOUNT';
	public const CONST_SUBLEDGER_ACCOUNT = 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_SUBLEDGER_ACCOUNT';

	/** @var DoliDB */
	private $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/** @return bool */
	public static function isEnabled()
	{
		return getDolGlobalInt(self::CONST_ENABLED) === 1;
	}

	/**
	 * Validate settings used for one native Various Payment.
	 * Only double-entry accounting requires a chart account; auxiliary accounts
	 * remain optional. Other modes accept free account numbers for external use.
	 *
	 * @param int $bankAccountId Native bank account
	 * @param int $paymentModeId Native payment mode
	 * @param string $accountingAccount General accounting account number
	 * @param string $subledgerAccount Auxiliary accounting account number
	 * @param int $entity Owner entity
	 * @return int<-1,1>
	 */
	public function validateConfiguration($bankAccountId, $paymentModeId, $accountingAccount, $subledgerAccount, $entity)
	{
		$this->error = '';
		$this->errors = array();
		if (!isModEnabled('bank')) {
			return $this->businessError('ConsumptionOdRequiresBankModule');
		}
		$accountingAccount = trim($accountingAccount);
		$subledgerAccount = trim($subledgerAccount);
		if ($bankAccountId <= 0 || $paymentModeId <= 0 || (isModEnabled('accounting') && $accountingAccount === '')) {
			return $this->businessError('ConsumptionOdConfigurationIncomplete');
		}
		// Both native payment_various account columns are varchar(32) since v20.
		if (dol_strlen($accountingAccount) > 32) {
			return $this->businessError('ConsumptionOdAccountingAccountTooLong');
		}
		if (dol_strlen($subledgerAccount) > 32) {
			return $this->businessError('ConsumptionOdSubledgerAccountTooLong');
		}

		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'bank_account';
		$sql .= ' WHERE rowid = '.((int) $bankAccountId).' AND clos = 0 AND entity IN ('.getEntity('bank_account').')';
		if ($this->hasOneRow($sql) !== 1) {
			return $this->businessError($this->error !== '' ? $this->error : 'ConsumptionOdBankAccountInvalid');
		}
		$sql = 'SELECT id FROM '.MAIN_DB_PREFIX.'c_paiement';
		$sql .= ' WHERE id = '.((int) $paymentModeId).' AND active = 1 AND type IN (1, 2, 3)';
		$sql .= ' AND entity IN ('.getEntity('c_paiement').')';
		if ($this->hasOneRow($sql) !== 1) {
			return $this->businessError($this->error !== '' ? $this->error : 'ConsumptionOdPaymentModeInvalid');
		}
		if (!isModEnabled('accounting')) {
			return 1;
		}
		$chartId = getDolGlobalInt('CHARTOFACCOUNTS');
		if ($chartId <= 0) {
			return $this->businessError('ConsumptionOdAccountingAccountInvalid');
		}
		$sql = 'SELECT aa.rowid FROM '.MAIN_DB_PREFIX.'accounting_account AS aa';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'accounting_system AS ast ON ast.pcg_version = aa.fk_pcg_version';
		$sql .= ' WHERE aa.entity = '.((int) $entity).' AND aa.active = 1';
		$sql .= " AND aa.account_number = '".$this->db->escape($accountingAccount)."'";
		$sql .= ' AND ast.rowid = '.((int) $chartId);
		if ($this->hasOneRow($sql) !== 1) {
			return $this->businessError($this->error !== '' ? $this->error : 'ConsumptionOdAccountingAccountInvalid');
		}
		if ($subledgerAccount === '') {
			return 1;
		}
		$escapedSubledger = $this->db->escape($subledgerAccount);
		$sql = 'SELECT code FROM (';
		$sql .= 'SELECT accountancy_code AS code FROM '.MAIN_DB_PREFIX."user WHERE accountancy_code = '".$escapedSubledger."'";
		$sql .= ' AND entity IN ('.getEntity('user').')';
		$sql .= ' UNION SELECT code_compta AS code FROM '.MAIN_DB_PREFIX."societe WHERE code_compta = '".$escapedSubledger."' AND entity IN (".getEntity('societe').')';
		$sql .= ' UNION SELECT code_compta_fournisseur AS code FROM '.MAIN_DB_PREFIX."societe WHERE code_compta_fournisseur = '".$escapedSubledger."' AND entity IN (".getEntity('societe').')';
		$sql .= ') AS aux LIMIT 1';
		if ($this->hasOneRow($sql) !== 1) {
			return $this->businessError($this->error !== '' ? $this->error : 'ConsumptionOdSubledgerAccountInvalid');
		}

		return 1;
	}

	/**
	 * Create a consumption and, when required, its native OD and receipt.
	 *
	 * @param LmdbVehicleConsumption $consumption Consumption to create
	 * @param array<string,mixed> $upload Receipt upload
	 * @param int $paymentModeId Per-entry payment mode, or configured default
	 * @param User $user Author
	 * @return int<-1,max>
	 */
	public function createConsumption($consumption, $upload, $paymentModeId, User $user)
	{
		global $conf;

		if (!$user->hasRight('lmdbvehiclemanagement', 'consumption', 'write')) {
			return $this->businessError('NotEnoughPermissions');
		}
		$fuelResult = self::isEnabled() ? $this->consumableIsFuel((int) $consumption->fk_consumable) : 0;
		if ($fuelResult < 0) {
			return -1;
		}
		$requiresOd = self::isEnabled() && $fuelResult === 1;
		if (!empty($consumption->fk_project) && (!isModEnabled('project') || !$user->hasRight('projet', 'lire'))) {
			return $this->businessError('InvalidProject');
		}
		if (!$requiresOd) {
			return $consumption->create($user);
		}
		$vehicleEntity = $this->getVehicleEntity((int) $consumption->fk_vehicle);
		if ($vehicleEntity < 0) {
			return -1;
		}
		if ($vehicleEntity !== (int) $conf->entity) {
			return $this->businessError('CannotMoveObjectBetweenEntities');
		}
		if ((float) price2num($consumption->total_ttc, 'MT') <= 0) {
			return $this->businessError('ConsumptionOdAmountMustBePositive');
		}

		$bankAccountId = getDolGlobalInt(self::CONST_BANK_ACCOUNT);
		if ($paymentModeId <= 0) {
			$paymentModeId = getDolGlobalInt(self::CONST_PAYMENT_MODE);
		}
		$accountingAccount = getDolGlobalString(self::CONST_ACCOUNTING_ACCOUNT);
		$subledgerAccount = getDolGlobalString(self::CONST_SUBLEDGER_ACCOUNT);
		if ($this->validateConfiguration($bankAccountId, $paymentModeId, $accountingAccount, $subledgerAccount, (int) $conf->entity) < 0) {
			return -1;
		}
		$secureUpload = new LmdbVehicleManagementSecureUpload();
		$fileInfo = $secureUpload->inspect($upload, $this->receiptErrorKeys());
		if (!is_array($fileInfo)) {
			return $this->copyErrors($secureUpload);
		}

		$receiptPath = '';
		$payment = null;
		$this->db->begin();
		$result = $consumption->create($user, 1);
		if ($result > 0) {
			$payment = $this->buildPayment($consumption, $bankAccountId, $paymentModeId, $accountingAccount, $subledgerAccount, $user);
			$result = $payment->create($user);
			if ($result <= 0) {
				$this->copyErrors($payment);
			}
		}
		if ($result > 0 && is_object($payment)) {
			$consumption->fk_payment_various = (int) $payment->id;
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_consumption SET fk_payment_various = '.((int) $payment->id);
			$sql .= ' WHERE rowid = '.((int) $consumption->id).' AND entity = '.((int) $consumption->entity).' AND fk_payment_various IS NULL';
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->errors[] = $this->error;
				$result = -1;
			}
		}
		if ($result > 0 && is_object($payment)) {
			$receiptPath = $this->storeReceiptInternal($consumption, $payment, $upload, $fileInfo, $secureUpload);
			if ($receiptPath === '') {
				$result = -1;
			}
		}
		if ($result > 0) {
			$consumption->context['trigger_reason'] = 'create_with_various_payment';
			$consumption->context['changed_fields'] = array_keys($consumption->fields);
			if ($consumption->call_trigger($consumption->TRIGGER_PREFIX.'_CREATE', $user) < 0) {
				$this->copyErrors($consumption);
				$result = -1;
			}
		}
		if ($result > 0) {
			$this->db->commit();
			return (int) $consumption->id;
		}

		$this->db->rollback();
		if ($receiptPath !== '' && is_object($payment)) {
			$this->deleteReceiptFile($receiptPath, $payment, (int) $consumption->entity);
		}
		$consumption->id = 0;
		$consumption->rowid = 0;
		$consumption->fk_payment_various = null;
		return -1;
	}

	/**
	 * Update a consumption and synchronize its unlocked native OD.
	 *
	 * @param LmdbVehicleConsumption $consumption Posted consumption
	 * @param User $user Author
	 * @return int<-1,max>
	 */
	public function updateConsumption($consumption, User $user)
	{
		if (!$user->hasRight('lmdbvehiclemanagement', 'consumption', 'write')) {
			return $this->businessError('NotEnoughPermissions');
		}
		$current = new LmdbVehicleConsumption($this->db);
		if (empty($consumption->id) || $current->fetch((int) $consumption->id) <= 0) {
			return $this->copyErrors($current, 'RecordNotFound');
		}
		$consumption->fk_payment_various = $current->fk_payment_various;
		if (empty($current->fk_payment_various)) {
			if ((int) $current->fk_project !== (int) $consumption->fk_project && !empty($consumption->fk_project)
				&& (!isModEnabled('project') || !$user->hasRight('projet', 'lire'))) {
				return $this->businessError('InvalidProject');
			}
			return $consumption->update($user);
		}
		if ($this->consumableIsFuel((int) $consumption->fk_consumable) !== 1) {
			return $this->businessError('ConsumptionOdCannotChangeNature');
		}
		$financialChanged = $this->financialSourceChanged($current, $consumption);
		if ((int) $current->fk_project !== (int) $consumption->fk_project && !empty($consumption->fk_project)
			&& (!isModEnabled('project') || !$user->hasRight('projet', 'lire'))) {
			return $this->businessError('InvalidProject');
		}
		$locked = $this->isLocked($current);
		if ($locked < 0) {
			return -1;
		}
		if ($locked > 0 && $financialChanged) {
			return $this->businessError('ConsumptionOdLocked');
		}

		$this->db->begin();
		$result = $consumption->update($user, 1);
		if ($result > 0 && $financialChanged) {
			$result = $this->synchronizePayment($consumption, $user);
		}
		if ($result > 0) {
			$consumption->context['trigger_reason'] = 'update_with_various_payment';
			if ($consumption->call_trigger($consumption->TRIGGER_PREFIX.'_UPDATE', $user) < 0) {
				$this->copyErrors($consumption);
				$result = -1;
			}
		}
		if ($result > 0) {
			$this->db->commit();
			return $result;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Delete a consumption and its unlocked native OD without requiring a bank permission.
	 *
	 * @param LmdbVehicleConsumption $consumption Consumption to delete
	 * @param User $user Author
	 * @return int<-1,1>
	 */
	public function deleteConsumption($consumption, User $user)
	{
		if (!$user->hasRight('lmdbvehiclemanagement', 'consumption', 'delete')) {
			return $this->businessError('NotEnoughPermissions');
		}
		if (empty($consumption->fk_payment_various)) {
			return $consumption->delete($user);
		}
		$locked = $this->isLocked($consumption);
		if ($locked < 0) {
			return -1;
		}
		if ($locked > 0) {
			return $this->businessError('ConsumptionOdLocked');
		}
		$payment = $this->fetchPayment($consumption);
		if (!is_object($payment)) {
			return -1;
		}
		$receiptPath = $this->getReceiptPath($consumption);
		$line = new AccountLine($this->db);
		if ($line->fetch((int) $payment->fk_bank) <= 0) {
			return $this->copyErrors($line, 'ConsumptionOdBankLineMissing');
		}

		$this->db->begin();
		$result = $payment->delete($user);
		if ($result > 0) {
			$result = $line->delete($user);
			if ($result <= 0) {
				$this->copyErrors($line);
			}
		}
		if ($result > 0) {
			$result = $consumption->delete($user);
			if ($result <= 0) {
				$this->copyErrors($consumption);
			}
		}
		if ($result > 0) {
			$this->db->commit();
			if ($receiptPath !== '' && !$this->deleteReceiptFile($receiptPath, $payment, (int) $consumption->entity)) {
				dol_syslog(__METHOD__.': failed to delete receipt '.$receiptPath, LOG_WARNING);
			}
			return 1;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Replace the receipt of an unlocked OD.
	 *
	 * @param LmdbVehicleConsumption $consumption Consumption
	 * @param array<string,mixed> $upload Receipt upload
	 * @param User $user Author
	 * @return int<-1,1>
	 */
	public function replaceReceipt($consumption, $upload, User $user)
	{
		if (!$user->hasRight('lmdbvehiclemanagement', 'consumption', 'write')) {
			return $this->businessError('NotEnoughPermissions');
		}
		$locked = $this->isLocked($consumption);
		if ($locked < 0) {
			return -1;
		}
		if ($locked > 0) {
			return $this->businessError('ConsumptionOdLocked');
		}
		$payment = $this->fetchPayment($consumption);
		if (!is_object($payment)) {
			return -1;
		}
		$secureUpload = new LmdbVehicleManagementSecureUpload();
		$fileInfo = $secureUpload->inspect($upload, $this->receiptErrorKeys());
		if (!is_array($fileInfo)) {
			return $this->copyErrors($secureUpload);
		}
		$path = $this->storeReceiptInternal($consumption, $payment, $upload, $fileInfo, $secureUpload);

		return $path !== '' ? 1 : -1;
	}

	/**
	 * Return whether the linked bank line is reconciled or accounted.
	 *
	 * @param LmdbVehicleConsumption $consumption Consumption
	 * @return int<-1,1>
	 */
	public function isLocked($consumption)
	{
		global $conf;

		if (empty($consumption->fk_payment_various)) {
			return 0;
		}
		// A linked financial operation can only be mutated from its owner entity.
		if ((int) $consumption->entity !== (int) $conf->entity) {
			return 1;
		}
		$payment = $this->fetchPayment($consumption);
		if (!is_object($payment)) {
			return -1;
		}
		if (!empty($payment->rappro)) {
			return 1;
		}
		$accountingLock = $payment->getVentilExportCompta();
		if ($accountingLock < 0) {
			return $this->copyErrors($payment);
		}

		return $accountingLock > 0 ? 1 : 0;
	}

	/** @param LmdbVehicleConsumption $consumption Consumption @return PaymentVarious|null */
	public function fetchPayment($consumption)
	{
		if (empty($consumption->fk_payment_various)) {
			$this->businessError('ConsumptionOdMissing');
			return null;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'payment_various';
		$sql .= ' WHERE rowid = '.((int) $consumption->fk_payment_various).' AND entity = '.((int) $consumption->entity);
		if ($this->hasOneRow($sql) !== 1) {
			$this->businessError($this->error !== '' ? $this->error : 'ConsumptionOdMissing');
			return null;
		}
		$payment = new PaymentVarious($this->db);
		if ($payment->fetch((int) $consumption->fk_payment_various) <= 0) {
			$this->copyErrors($payment, 'ConsumptionOdMissing');
			return null;
		}

		return $payment;
	}

	/** @param LmdbVehicleConsumption $consumption Consumption @return string */
	public function getReceiptPath($consumption)
	{
		$payment = $this->fetchPayment($consumption);
		if (!is_object($payment)) {
			return '';
		}
		$directory = $this->getPaymentDirectory((int) $payment->id, (int) $consumption->entity);
		if ($directory === '') {
			return '';
		}
		foreach (array('pdf', 'jpg', 'png') as $extension) {
			$path = $directory.'/ticket-'.((int) $consumption->id).'.'.$extension;
			if (is_file($path)) {
				return $path;
			}
		}

		return '';
	}

	/** @param LmdbVehicleConsumption $consumption Consumption @return string */
	public function getPaymentUrl($consumption)
	{
		return !empty($consumption->fk_payment_various)
			? DOL_URL_ROOT.'/compta/bank/various_payment/card.php?id='.((int) $consumption->fk_payment_various)
			: '';
	}

	/** @param int $consumableId Consumable @return int<-1,1> */
	private function consumableIsFuel($consumableId)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable';
		$sql .= " WHERE rowid = ".((int) $consumableId)." AND category = 'fuel' AND active = 1";
		$sql .= ' AND entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').')';

		return $this->hasOneRow($sql);
	}

	/** @param int $vehicleId Vehicle @return int Entity or -1 on error */
	private function getVehicleEntity($vehicleId)
	{
		$sql = 'SELECT entity FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle';
		$sql .= ' WHERE rowid = '.((int) $vehicleId).' AND entity IN ('.getEntity('lmdbvehicle').')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($row)) {
			return $this->businessError('InvalidVehicle');
		}

		return (int) $row->entity;
	}

	/**
	 * @param LmdbVehicleConsumption $consumption Consumption
	 * @param int $bankAccountId Bank account
	 * @param int $paymentModeId Payment mode
	 * @param string $accountingAccount General account
	 * @param string $subledgerAccount Auxiliary account
	 * @param User $user Author
	 * @return PaymentVarious
	 */
	private function buildPayment($consumption, $bankAccountId, $paymentModeId, $accountingAccount, $subledgerAccount, User $user)
	{
		$payment = new PaymentVarious($this->db);
		$payment->datep = (int) $consumption->reading_date;
		// PaymentVarious::create() and Account::addline() normalize an empty value date to the payment date.
		$payment->datev = (int) $consumption->reading_date;
		$payment->sens = 0;
		$payment->amount = (float) price2num($consumption->total_ttc, 'MT');
		$payment->type_payment = $paymentModeId;
		$payment->num_payment = '';
		$payment->chqemetteur = trim((string) $user->lastname.' '.(string) $user->firstname);
		$payment->chqbank = '';
		$payment->label = $this->buildLabel($consumption);
		$payment->accountancy_code = trim($accountingAccount);
		$payment->subledger_account = trim($subledgerAccount);
		$payment->fk_project = !empty($consumption->fk_project) ? (int) $consumption->fk_project : 0;
		$payment->fk_account = $bankAccountId;
		$payment->note = '';

		return $payment;
	}

	/** @param LmdbVehicleConsumption $consumption Consumption @return string */
	private function buildLabel($consumption)
	{
		$registration = '';
		$consumableLabel = '';
		$sql = 'SELECT registration_number, ref FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle';
		$sql .= ' WHERE rowid = '.((int) $consumption->fk_vehicle).' AND entity = '.((int) $consumption->entity);
		$resql = $this->db->query($sql);
		if ($resql && is_object($row = $this->db->fetch_object($resql))) {
			$registration = trim((string) $row->registration_number) !== '' ? (string) $row->registration_number : (string) $row->ref;
		}
		if ($resql) {
			$this->db->free($resql);
		}
		$sql = 'SELECT label FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_consumable';
		$sql .= ' WHERE rowid = '.((int) $consumption->fk_consumable).' AND entity IN ('.getEntity('c_lmdbvehiclemanagement_consumable').')';
		$resql = $this->db->query($sql);
		if ($resql && is_object($row = $this->db->fetch_object($resql))) {
			$consumableLabel = (string) $row->label;
		}
		if ($resql) {
			$this->db->free($resql);
		}

		return trim((string) $consumption->ref.' - '.$registration.' - '.$consumableLabel, ' -');
	}

	/**
	 * @param LmdbVehicleConsumption $old Old consumption
	 * @param LmdbVehicleConsumption $new New consumption
	 * @return bool
	 */
	private function financialSourceChanged($old, $new)
	{
		return (int) $old->fk_vehicle !== (int) $new->fk_vehicle
			|| (int) $old->fk_consumable !== (int) $new->fk_consumable
			|| (int) $old->reading_date !== (int) $new->reading_date
			|| (float) price2num($old->quantity) !== (float) price2num($new->quantity)
			|| (float) price2num($old->total_ttc, 'MT') !== (float) price2num($new->total_ttc, 'MT')
			|| (int) $old->fk_project !== (int) $new->fk_project;
	}

	/** @param LmdbVehicleConsumption $consumption Consumption @param User $user Author @return int<-1,1> */
	private function synchronizePayment($consumption, User $user)
	{
		$payment = $this->fetchPayment($consumption);
		if (!is_object($payment)) {
			return -1;
		}
		$payment->datep = (int) $consumption->reading_date;
		$payment->datev = (int) $consumption->reading_date;
		$payment->amount = (float) price2num($consumption->total_ttc, 'MT');
		$payment->label = $this->buildLabel($consumption);
		$payment->fk_project = !empty($consumption->fk_project) ? (int) $consumption->fk_project : 0;
		$payment->fk_user_modif = (int) $user->id;
		$payment->tms = dol_now();
		if ($payment->update($user) <= 0) {
			return $this->copyErrors($payment);
		}
		$line = new AccountLine($this->db);
		if ($line->fetch((int) $payment->fk_bank) <= 0) {
			return $this->copyErrors($line, 'ConsumptionOdBankLineMissing');
		}
		$line->amount = -abs((float) price2num($consumption->total_ttc, 'MT'));
		$line->dateo = (int) $consumption->reading_date;
		$line->datev = (int) $consumption->reading_date;
		if ($line->update($user) <= 0) {
			return $this->copyErrors($line);
		}
		$line->label = $payment->label;
		if ($line->updateLabel() <= 0) {
			return $this->copyErrors($line);
		}

		return 1;
	}

	/**
	 * @param LmdbVehicleConsumption $consumption Consumption
	 * @param PaymentVarious $payment Native OD
	 * @param array<string,mixed> $upload Upload
	 * @param array{mime:string,extension:string} $fileInfo Inspected file
	 * @param LmdbVehicleManagementSecureUpload $secureUpload Secure upload helper
	 * @return string Stored absolute path or empty string
	 */
	private function storeReceiptInternal($consumption, $payment, $upload, $fileInfo, $secureUpload)
	{
		$directory = $this->getPaymentDirectory((int) $payment->id, (int) $consumption->entity);
		if ($directory === '' || dol_mkdir($directory) < 0) {
			$this->businessError('ErrorCanNotCreateDir');
			return '';
		}
		$fileName = 'ticket-'.((int) $consumption->id).'.'.$fileInfo['extension'];
		$destination = $directory.'/'.$fileName;
		$oldPath = $this->getReceiptPath($consumption);
		$sourceName = dol_sanitizeFileName(basename((string) $upload['name']));
		$temporary = $directory.'/.ticket-'.((int) $consumption->id).'-'.((int) getmypid()).'-'.dol_now().'.'.$fileInfo['extension'];
		if ($secureUpload->store($upload, $temporary, $fileInfo['mime'], $this->receiptErrorKeys()) < 0) {
			$this->copyErrors($secureUpload);
			return '';
		}
		$backup = '';
		if (is_file($destination)) {
			$backup = $destination.'.backup-'.((int) getmypid()).'-'.dol_now();
			if (!@rename($destination, $backup)) {
				@unlink($temporary);
				$this->businessError('ConsumptionReceiptUploadFailed');
				return '';
			}
		}
		if (!@rename($temporary, $destination)) {
			if ($backup !== '') @rename($backup, $destination);
			@unlink($temporary);
			$this->businessError('ConsumptionReceiptUploadFailed');
			return '';
		}
		$this->withOwnerEntity((int) $consumption->entity, static function () use ($directory, $fileName) {
			deleteFilesIntoDatabaseIndex($directory, $fileName, 'uploaded');
		});
		$indexResult = $this->withOwnerEntity((int) $consumption->entity, static function () use ($directory, $fileName, $sourceName, $payment) {
			return addFileIntoDatabaseIndex($directory, $fileName, $sourceName, 'uploaded', 0, $payment);
		});
		if (!is_int($indexResult) || $indexResult < 0) {
			$this->businessError('WarningFailedToAddFileIntoDatabaseIndex');
			$this->deleteReceiptFile($destination, $payment, (int) $consumption->entity);
			if ($backup !== '') {
				@rename($backup, $destination);
				$this->withOwnerEntity((int) $consumption->entity, static function () use ($directory, $fileName, $sourceName, $payment) {
					return addFileIntoDatabaseIndex($directory, $fileName, $sourceName, 'uploaded', 0, $payment);
				});
			}
			return '';
		}
		if ($backup !== '') @unlink($backup);
		if ($oldPath !== '' && $oldPath !== $destination && is_file($oldPath)) {
			$this->deleteReceiptFile($oldPath, $payment, (int) $consumption->entity);
		}

		return $destination;
	}

	/** @param int $paymentId Payment id @param int $entity Owner entity @return string */
	private function getPaymentDirectory($paymentId, $entity)
	{
		global $conf;

		if ($paymentId <= 0 || !isset($conf->bank) || !is_object($conf->bank)) {
			$this->businessError('ErrorInvalidDirectory');
			return '';
		}
		$root = '';
		if (isset($conf->bank->multidir_output) && is_array($conf->bank->multidir_output) && !empty($conf->bank->multidir_output[$entity])) {
			$root = (string) $conf->bank->multidir_output[$entity];
		} elseif ((int) $conf->entity === $entity && !empty($conf->bank->dir_output)) {
			$root = (string) $conf->bank->dir_output;
		}
		$directory = rtrim($root, '/\\').'/'.dol_sanitizeFileName((string) $paymentId);
		if ($root === '' || basename($directory) !== (string) $paymentId) {
			$this->businessError('ErrorInvalidDirectory');
			return '';
		}

		return $directory;
	}

	/** @param string $path Receipt path @param PaymentVarious $payment Native OD @param int $entity Owner entity @return bool */
	private function deleteReceiptFile($path, $payment, $entity)
	{
		return (bool) $this->withOwnerEntity($entity, static function () use ($path, $payment) {
			return dol_delete_file($path, 1, 0, 0, $payment);
		});
	}

	/**
	 * Execute one native ECM helper in the owner entity context.
	 *
	 * @param int $entity Owner entity
	 * @param callable $callback Callback
	 * @return mixed
	 */
	private function withOwnerEntity($entity, $callback)
	{
		global $conf;

		$currentEntity = (int) $conf->entity;
		$conf->entity = $entity;
		try {
			return $callback();
		} finally {
			$conf->entity = $currentEntity;
		}
	}

	/** @return array<string,string> */
	private function receiptErrorKeys()
	{
		return array(
			'invalid_upload' => 'ConsumptionReceiptRequired',
			'invalid_mime' => 'ConsumptionReceiptMimeInvalid',
			'library' => 'ConsumptionReceiptImageLibraryUnavailable',
			'invalid_image' => 'ConsumptionReceiptImageInvalid',
			'save' => 'ConsumptionReceiptUploadFailed',
		);
	}

	/** @param string $sql Select returning at most one useful row @return int<-1,1> */
	private function hasOneRow($sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
		$found = $this->db->num_rows($resql) > 0;
		$this->db->free($resql);

		return $found ? 1 : 0;
	}

	/** @param object $source Error source @param string $fallback Fallback @return int<-1,-1> */
	private function copyErrors($source, $fallback = 'Error')
	{
		$this->error = isset($source->error) && is_string($source->error) && $source->error !== '' ? $source->error : $fallback;
		$this->errors = isset($source->errors) && is_array($source->errors) && !empty($source->errors) ? $source->errors : array($this->error);

		return -1;
	}

	/** @param string $error Error key or message @return int<-1,-1> */
	private function businessError($error)
	{
		$this->error = $error;
		if (!in_array($error, $this->errors, true)) {
			$this->errors[] = $error;
		}

		return -1;
	}
}
