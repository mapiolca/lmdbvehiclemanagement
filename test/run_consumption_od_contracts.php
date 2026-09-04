<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$moduleRoot = dirname(__DIR__);

/** @param string $relativePath Module-relative path @return string */
function readConsumptionOdSource($relativePath)
{
	global $moduleRoot;

	$content = file_get_contents($moduleRoot.'/'.$relativePath);
	if (!is_string($content)) {
		fwrite(STDERR, 'Unable to read '.$relativePath.PHP_EOL);
		exit(1);
	}

	return $content;
}

$service = readConsumptionOdSource('class/lmdbvehicleconsumptionpayment.class.php');
$consumption = readConsumptionOdSource('class/lmdbvehicleconsumption.class.php');
$secureUpload = readConsumptionOdSource('class/lmdbvehiclemanagementsecureupload.class.php');
$card = readConsumptionOdSource('consumption_card.php');
$receiptRoute = readConsumptionOdSource('consumption_receipt.php');
$import = readConsumptionOdSource('class/lmdbvehicleconsumptionimport.class.php');
$setup = readConsumptionOdSource('admin/setup.php');
$descriptor = readConsumptionOdSource('core/modules/modLmdbVehicleManagement.class.php');
$sql = readConsumptionOdSource('sql/llx_lmdbvehiclemanagement_consumption.sql');
$keys = readConsumptionOdSource('sql/llx_lmdbvehiclemanagement_consumption.key.sql');
$compatibility = readConsumptionOdSource('class/lmdbvehiclemanagementcompatibility.class.php');
$frLang = readConsumptionOdSource('langs/fr_FR/lmdbvehiclemanagement.lang');
$enLang = readConsumptionOdSource('langs/en_US/lmdbvehiclemanagement.lang');

$checks = array();
$checks['consumption_links_native_project_and_various_payment'] = strpos($sql, 'fk_project integer DEFAULT NULL') !== false
	&& strpos($sql, 'fk_payment_various integer DEFAULT NULL') !== false
	&& strpos($keys, 'uk_lmdbvm_consumption_payment (entity, fk_payment_various)') !== false
	&& strpos($consumption, "'fk_project' => array('type' => 'integer:Project:") !== false
	&& strpos($consumption, "'fk_payment_various' => array('type' => 'integer:PaymentVarious:") !== false;
$checks['settings_are_entity_scoped_and_disabled_by_default'] = strpos($descriptor, "'LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_ENABLED' => '0'") !== false
	&& substr_count($descriptor, 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_OD_') >= 5
	&& strpos($setup, "dolibarr_set_const(\$db, \$constant, \$definition[0], \$definition[1], 0, '', (int) \$conf->entity)") !== false;
$checks['settings_use_native_controls'] = strpos($setup, 'ajax_constantonoff(LmdbVehicleConsumptionPayment::CONST_ENABLED)') !== false
	&& strpos($setup, "select_comptes(\$odBankAccount, 'consumption_od_bank_account'") !== false
	&& strpos($setup, "select_types_paiements(\$odPaymentMode, 'consumption_od_payment_mode', 'DBIT'") !== false
	&& strpos($setup, "select_account(\$odAccountingAccount, 'consumption_od_accounting_account'") !== false
	&& strpos($setup, "select_auxaccount(\$odSubledgerAccount, 'consumption_od_subledger_account'") !== false;
$checks['configuration_uses_bank_and_conditional_accounting_validation'] = strpos($service, "isModEnabled('bank')") !== false
	&& strpos($service, "isModEnabled('accounting')") !== false
	&& strpos($service, 'ConsumptionOdRequiresAccountingModule') === false
	&& strpos($service, "MAIN_DB_PREFIX.'bank_account'") !== false
	&& strpos($service, "MAIN_DB_PREFIX.'c_paiement'") !== false
	&& strpos($service, "MAIN_DB_PREFIX.'accounting_account AS aa'") !== false;
$checks['native_various_payment_is_created_as_debit'] = strpos($service, 'new PaymentVarious($this->db)') !== false
	&& strpos($service, '$payment->sens = 0;') !== false
	&& strpos($service, "price2num(\$consumption->total_ttc, 'MT')") !== false
	&& strpos($service, "businessError('ConsumptionOdAmountMustBePositive')") !== false
	&& strpos($service, "trim((string) \$user->lastname.' '.(string) \$user->firstname)") !== false
	&& strpos($service, '$payment->num_payment = \'\';') !== false
	&& strpos($service, '$payment->chqbank = \'\';') !== false;
$checks['od_label_uses_consumption_vehicle_and_consumable'] = strpos($service, "\$consumption->ref.' - '.\$registration.' - '.\$consumableLabel") !== false;
$checks['creation_is_transactional_and_uses_consumption_permission'] = strpos($service, "\$user->hasRight('lmdbvehiclemanagement', 'consumption', 'write')") !== false
	&& strpos($service, "\$user->hasRight('banque'") === false
	&& strpos($service, '$this->db->begin();') !== false
	&& strpos($service, '$this->db->rollback();') !== false
	&& strpos($service, '$this->db->commit();') !== false;
$checks['receipt_accepts_only_pdf_jpeg_png_and_sanitizes_images'] = strpos($secureUpload, "'application/pdf' => 'pdf'") !== false
	&& strpos($secureUpload, "'image/jpeg' => 'jpg'") !== false
	&& strpos($secureUpload, "'image/png' => 'png'") !== false
	&& strpos($secureUpload, 'exif_read_data(') !== false
	&& strpos($secureUpload, 'imageflip(') !== false
	&& strpos($secureUpload, 'imagejpeg(') !== false
	&& strpos($secureUpload, 'imagepng(') !== false;
$checks['receipt_is_stored_in_native_bank_documents'] = strpos($service, '$conf->bank->multidir_output[$entity]') !== false
	&& strpos($service, "'ticket-'.((int) \$consumption->id)") !== false
	&& strpos($service, 'addFileIntoDatabaseIndex(') !== false;
$checks['card_collects_project_payment_mode_and_receipt'] = strpos($card, "select_projects(-1, !empty(\$object->fk_project)") !== false
	&& strpos($card, "select_types_paiements(getDolGlobalInt(LmdbVehicleConsumptionPayment::CONST_PAYMENT_MODE)") !== false
	&& strpos($card, 'name="receipt" accept="application/pdf,image/jpeg,image/png"') !== false
	&& strpos($card, 'createConsumption($object, $upload, GETPOSTINT(\'payment_mode_id\'), $user)') !== false;
$checks['receipt_route_is_scoped_to_consumption_readers'] = strpos($receiptRoute, "\$user->hasRight('lmdbvehiclemanagement', 'read')") !== false
	&& strpos($receiptRoute, '$consumption->fetch($id)') !== false
	&& strpos($receiptRoute, '$consumptionPayment->getReceiptPath($consumption)') !== false
	&& strpos($receiptRoute, 'X-Content-Type-Options: nosniff') !== false;
$checks['financial_lock_covers_reconciliation_and_accounting'] = strpos($service, '!empty($payment->rappro)') !== false
	&& strpos($service, '$payment->getVentilExportCompta()') !== false
	&& strpos($service, '(int) $consumption->entity !== (int) $conf->entity') !== false
	&& strpos($service, "businessError('ConsumptionOdLocked')") !== false
	&& strpos($card, '$financialLocked = $action === \'edit\' && $odLocked > 0;') !== false;
$checks['update_synchronizes_payment_and_bank_line'] = strpos($service, 'private function synchronizePayment(') !== false
	&& strpos($service, 'new AccountLine($this->db)') !== false
	&& strpos($service, '$line->amount = -abs(') !== false
	&& strpos($service, '$line->updateLabel()') !== false;
$checks['delete_removes_native_payment_and_bank_line'] = strpos($service, '$payment->delete($user)') !== false
	&& strpos($service, '$line->delete($user)') !== false
	&& strpos($service, '$consumption->delete($user)') !== false;
$checks['fuel_csv_import_is_blocked_when_feature_is_enabled'] = strpos($import, 'LmdbVehicleConsumptionPayment::isEnabled()') !== false
	&& strpos($import, "\$object->category_snapshot === 'fuel'") !== false
	&& strpos($import, 'ConsumptionImportFuelBlockedByOdOption') !== false;
$checks['compatibility_registry_covers_native_dependencies'] = strpos($compatibility, "'consumption_various_payment'") !== false
	&& strpos($compatibility, "'FeatureConsumptionVariousPayment'") !== false
	&& strpos($compatibility, "isModEnabled('bank')") !== false
	&& strpos($compatibility, "isModEnabled('accounting')") === false;
$checks['translations_exist_in_both_languages'] = preg_match('/^ConsumptionOdSettings=.+$/m', $frLang) === 1
	&& preg_match('/^ConsumptionReceiptRequired=.+$/m', $frLang) === 1
	&& preg_match('/^ConsumptionOdLocked=.+$/m', $frLang) === 1
	&& preg_match('/^ConsumptionOdSettings=.+$/m', $enLang) === 1
	&& preg_match('/^ConsumptionReceiptRequired=.+$/m', $enLang) === 1
	&& preg_match('/^ConsumptionOdLocked=.+$/m', $enLang) === 1;

$failed = array_keys(array_filter($checks, static function ($result) {
	return !$result;
}));
if (!empty($failed)) {
	fwrite(STDERR, 'Failed consumption OD contract checks: '.implode(', ', $failed).PHP_EOL);
	exit(1);
}

print count($checks).' consumption OD contract checks passed'.PHP_EOL;
