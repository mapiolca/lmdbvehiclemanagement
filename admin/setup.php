<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formaccounting.class.php';
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclereferencemigration.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleevent.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumption.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumptionpayment.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleregulatorycontrol.class.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('admin', 'banks', 'accountancy', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (empty($user->admin)) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$objectType = GETPOST('object', 'aZ09');
$value = GETPOST('value', 'aZ09');
$backtopage = GETPOST('backtopage', 'alphanohtml');
/** @var array<string,array{label:string,class:class-string,models:array<int,string>,constant:string}> $models */
$models = array(
	'lmdbvehicle' => array(
		'label' => 'Vehicle',
		'class' => 'LmdbVehicle',
		'models' => array('mod_lmdbvehicle_standard', 'mod_lmdbvehicle_registration'),
		'constant' => 'LMDBVEHICLEMANAGEMENT_LMDBVEHICLE_ADDON',
	),
	'lmdbvehicleevent' => array(
		'label' => 'VehicleEvent',
		'class' => 'LmdbVehicleEvent',
		'models' => array('mod_lmdbvehicleevent_standard'),
		'constant' => 'LMDBVEHICLEMANAGEMENT_LMDBVEHICLEEVENT_ADDON',
	),
	'lmdbinsurancecontract' => array(
		'label' => 'InsuranceContract',
		'class' => 'LmdbVehicleInsuranceContract',
		'models' => array('mod_lmdbinsurancecontract_standard'),
		'constant' => 'LMDBVEHICLEMANAGEMENT_INSURANCECONTRACT_ADDON',
	),
	'lmdbvehicleconsumption' => array(
		'label' => 'ConsumptionEntry',
		'class' => 'LmdbVehicleConsumption',
		'models' => array('mod_lmdbvehicleconsumption_standard'),
		'constant' => 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_ADDON',
	),
	'lmdbvehicleregulatorycontrol' => array(
		'label' => 'RegulatoryControl',
		'class' => 'LmdbVehicleRegulatoryControl',
		'models' => array('mod_lmdbvehicleregulatorycontrol_standard'),
		'constant' => 'LMDBVEHICLEMANAGEMENT_REGULATORY_CONTROL_ADDON',
	),
);

if ($action === 'confirm_setmod' && GETPOST('confirm', 'alpha') === 'yes') {
	if (!isset($models[$objectType]) || !in_array($value, $models[$objectType]['models'], true)) {
		accessforbidden($langs->trans('ErrorBadValueForParameter', 'object'));
	}
	if ($objectType === 'lmdbvehicle') {
		$migration = new LmdbVehicleReferenceMigration($db);
		$result = $migration->migrateEntity($value, $user, (int) $conf->entity);
		if ($result <= 0) {
			lmdbVehicleManagementSetObjectErrors($migration);
		}
	} else {
		$result = dolibarr_set_const($db, $models[$objectType]['constant'], $value, 'chaine', 0, '', (int) $conf->entity);
	}
	if ($result > 0) {
		setEventMessages($objectType === 'lmdbvehicle' ? $langs->trans('VehicleReferencesMigrated') : $langs->trans('SettingsSaved'), null, 'mesgs');
	} elseif ($objectType !== 'lmdbvehicle') {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'save_consumption_od_settings') {
	$bankAccountId = GETPOSTINT('consumption_od_bank_account');
	$paymentModeId = GETPOSTINT('consumption_od_payment_mode');
	$accountingAccount = trim(GETPOST('consumption_od_accounting_account', 'alphanohtml'));
	$subledgerAccount = trim(GETPOST('consumption_od_subledger_account', 'alphanohtml'));
	if (isModEnabled('accounting')) {
		// Native account selects submit -1 for their empty option.
		$accountingAccount = $accountingAccount === '-1' ? '' : $accountingAccount;
		$subledgerAccount = $subledgerAccount === '-1' ? '' : $subledgerAccount;
	}
	$consumptionPayment = new LmdbVehicleConsumptionPayment($db);
	if ($consumptionPayment->validateConfiguration($bankAccountId, $paymentModeId, $accountingAccount, $subledgerAccount, (int) $conf->entity) > 0) {
		$settings = array(
			LmdbVehicleConsumptionPayment::CONST_BANK_ACCOUNT => array($bankAccountId, 'integer'),
			LmdbVehicleConsumptionPayment::CONST_PAYMENT_MODE => array($paymentModeId, 'integer'),
			LmdbVehicleConsumptionPayment::CONST_ACCOUNTING_ACCOUNT => array($accountingAccount, 'chaine'),
			LmdbVehicleConsumptionPayment::CONST_SUBLEDGER_ACCOUNT => array($subledgerAccount, 'chaine'),
		);
		$db->begin();
		$result = 1;
		foreach ($settings as $constant => $definition) {
			if (dolibarr_set_const($db, $constant, $definition[0], $definition[1], 0, '', (int) $conf->entity) <= 0) {
				$result = -1;
				break;
			}
		}
		if ($result > 0) {
			$db->commit();
			setEventMessages($langs->trans('SettingsSaved'), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		}
	} else {
		lmdbVehicleManagementSetObjectErrors($consumptionPayment);
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$title = $langs->trans('LmdbVehicleManagementSetup');
$linkback = '<a href="'.($backtopage ?: DOL_URL_ROOT.'/admin/modules.php?search_keyword=lmdbvehiclemanagement').'">'.img_picto('', 'back', 'class="pictofixedwidth"').$langs->trans('BackToModuleList').'</a>';

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-admin');
print load_fiche_titre($title, $linkback, 'title_setup');
$head = lmdbVehicleManagementAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $title, -1, 'car');

if ($action === 'setmod') {
	if (!isset($models[$objectType]) || !in_array($value, $models[$objectType]['models'], true)) {
		accessforbidden($langs->trans('ErrorBadValueForParameter', 'object'));
	}
	$form = new Form($db);
	$question = $langs->trans('ConfirmNumberingModelChange');
	$canConfirm = true;
	if ($objectType === 'lmdbvehicle') {
		$migration = new LmdbVehicleReferenceMigration($db);
		$preview = $migration->preview($value, (int) $conf->entity);
		if (!empty($preview['conflicts'])) {
			lmdbVehicleManagementSetObjectErrors($migration);
			$canConfirm = false;
		} else {
			$question = $langs->trans('ConfirmVehicleReferenceMigration', (int) $preview['count']);
		}
	}
	if ($canConfirm) {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?object='.urlencode($objectType).'&value='.urlencode($value),
			$langs->trans('ConfirmNumberingModelChangeTitle'),
			$question,
			'confirm_setmod',
			array(),
			0,
			1
		);
	}
}

print load_fiche_titre($langs->trans('NumberingModels'), '', 'hashtag');
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Name').'</td><td>'.$langs->trans('Description').'</td><td>'.$langs->trans('Example').'</td><td class="center">'.$langs->trans('Status').'</td><td class="center">'.$langs->trans('ShortInfo').'</td></tr>';

foreach ($models as $code => $definition) {
	$objectClass = $definition['class'];
	$specimen = new $objectClass($db);
	$specimen->date_creation = dol_now();
	$specimen->entity = (int) $conf->entity;
	if ($specimen instanceof LmdbVehicle) {
		$specimen->registration_number = 'AA-123-BB';
	}
	print '<tr class="liste_titre"><td colspan="5"><strong>'.$langs->trans($definition['label']).'</strong></td></tr>';
	foreach ($definition['models'] as $modelClass) {
		$modelFile = dol_buildpath('/lmdbvehiclemanagement/core/modules/lmdbvehiclemanagement/'.$modelClass.'.php', 0);
		require_once $modelFile;
		$numbering = new $modelClass();
		$active = getDolGlobalString($definition['constant']) === $modelClass;

		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($numbering->getName($langs)).'</td>';
		print '<td>'.$numbering->info($langs).'</td>';
		print '<td class="nowrap">'.dol_escape_htmltag($numbering->getExample()).'</td>';
		print '<td class="center">';
		if ($active) {
			print img_picto($langs->trans('Activated'), 'switch_on');
		} else {
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=setmod&token='.newToken().'&object='.urlencode($code).'&value='.urlencode($modelClass).'">'.img_picto($langs->trans('Disabled'), 'switch_off').'</a>';
		}
		print '</td>';
		$next = $numbering->getNextValue($specimen);
		$tooltip = $langs->trans('Version').': <b>'.dol_escape_htmltag($numbering->getVersion()).'</b><br>'.$langs->trans('NextValue').': '.dol_escape_htmltag(is_string($next) ? $next : $numbering->error);
		$form = new Form($db);
		print '<td class="center">'.$form->textwithpicto('', $tooltip, 1, 'info').'</td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';

$form = new Form($db);
$formAccounting = new FormAccounting($db);
$odBankAccount = getDolGlobalInt(LmdbVehicleConsumptionPayment::CONST_BANK_ACCOUNT);
$odPaymentMode = getDolGlobalInt(LmdbVehicleConsumptionPayment::CONST_PAYMENT_MODE);
$odAccountingAccount = getDolGlobalString(LmdbVehicleConsumptionPayment::CONST_ACCOUNTING_ACCOUNT);
$odSubledgerAccount = getDolGlobalString(LmdbVehicleConsumptionPayment::CONST_SUBLEDGER_ACCOUNT);
$odConfiguration = new LmdbVehicleConsumptionPayment($db);
$odConfigurationValid = $odConfiguration->validateConfiguration($odBankAccount, $odPaymentMode, $odAccountingAccount, $odSubledgerAccount, (int) $conf->entity) > 0;
$odEnabled = LmdbVehicleConsumptionPayment::isEnabled();

print load_fiche_titre($langs->trans('ConsumptionOdSettings'), '', 'bank');
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_consumption_od_settings">';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('ConsumptionOdSettingsDescription').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('EnableConsumptionOd').'</td><td>';
if ($odConfigurationValid || $odEnabled) {
	print ajax_constantonoff(LmdbVehicleConsumptionPayment::CONST_ENABLED);
} else {
	print img_picto($langs->trans('ConsumptionOdConfigurationRequiredBeforeEnable'), 'switch_off');
}
print '</td></tr>';
print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans('BankAccount').'</td><td>';
print $form->select_comptes($odBankAccount, 'consumption_od_bank_account', 0, '', 1, '', 1, 'minwidth300', 1);
print '</td></tr>';
print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans('PaymentMode').'</td><td>';
print $form->select_types_paiements($odPaymentMode, 'consumption_od_payment_mode', 'DBIT', 0, 1, 1, 0, 1, 'minwidth300', 1);
print '</td></tr>';
print '<tr class="oddeven"><td'.(isModEnabled('accounting') ? ' class="fieldrequired"' : '').'>'.$langs->trans('AccountAccounting').'</td><td>';
if (isModEnabled('accounting')) {
	print $formAccounting->select_account($odAccountingAccount, 'consumption_od_accounting_account', 1, array(), 1, 1, 'minwidth300');
} else {
	print '<input type="text" class="minwidth300 maxwidthonsmartphone" name="consumption_od_accounting_account" id="consumption_od_accounting_account" maxlength="32" value="'.dol_escape_htmltag($odAccountingAccount).'">';
}
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('SubledgerAccount').'</td><td>';
if (isModEnabled('accounting')) {
	print $formAccounting->select_auxaccount($odSubledgerAccount, 'consumption_od_subledger_account', 1, 'minwidth300');
} else {
	print '<input type="text" class="minwidth300 maxwidthonsmartphone" name="consumption_od_subledger_account" id="consumption_od_subledger_account" maxlength="32" value="'.dol_escape_htmltag($odSubledgerAccount).'">';
}
print '</td></tr>';
print '</table></div>';
if (!$odConfigurationValid) {
	print '<div class="warning">'.$langs->trans($odConfiguration->error ?: 'ConsumptionOdConfigurationIncomplete').'</div>';
}
print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';
print '<div class="underbanner opacitymedium">'.$langs->trans('NoMandatoryDependency').'. '.$langs->trans('OptionalDependencies').': Agenda, Multicompany, Ressources.</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
