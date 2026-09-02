<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehicleinsurance.lib.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('main', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !lmdbVehicleManagementCanDo($user, 'read') || !lmdbVehicleManagementCanDo($user, 'insurance', 'write') || !empty($user->socid)) accessforbidden();

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$contractId = GETPOSTINT('contract_id');
$coverageType = GETPOST('coverage_type', 'alpha') ?: LmdbVehicleInsuranceContract::COVERAGE_PRIMARY;
$coverageStart = (int) (lmdbInsuranceGetDate('coverage_start') ?: 0);
$coverageEnd = lmdbInsuranceGetDate('coverage_end');
$vehicle = new LmdbVehicle($db);
if ($id <= 0 || $vehicle->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));

$backUrl = dol_buildpath('/lmdbvehiclemanagement/vehicle_card.php', 1).'?id='.$id;
if ($cancel) {
	header('Location: '.$backUrl);
	exit;
}

$contracts = array();
$contractObjects = array();
$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract';
$sql .= ' WHERE entity = '.((int) $vehicle->entity).' AND status IN ('.LmdbVehicleInsuranceContract::STATUS_DRAFT.', '.LmdbVehicleInsuranceContract::STATUS_ACTIVE.')';
$sql .= ' AND rowid NOT IN (SELECT fk_contract FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle';
$sql .= ' WHERE entity = '.((int) $vehicle->entity).' AND fk_vehicle = '.$id.') ORDER BY ref';
$resql = $db->query($sql);
if ($resql) {
	while (is_object($row = $db->fetch_object($resql))) {
		$contract = new LmdbVehicleInsuranceContract($db);
		if ($contract->fetch((int) $row->rowid) > 0) {
			$contracts[(int) $contract->id] = $contract->ref.' — '.$contract->policy_number.' — '.$contract->label;
			$contractObjects[(int) $contract->id] = $contract;
		}
	}
	$db->free($resql);
}

if ($action === 'link_contract') {
	if (!isset($contractObjects[$contractId])) {
		setEventMessages($langs->trans('InsuranceContractInvalid'), null, 'errors');
	} else {
		$contract = $contractObjects[$contractId];
		if ($coverageStart <= 0) $coverageStart = (int) $contract->date_start;
		if ($coverageEnd === null) $coverageEnd = $contract->date_end;
		$result = $contract->linkVehicle($id, $coverageType, $coverageStart, $coverageEnd, $user);
		if ($result > 0) {
			setEventMessages($langs->trans('InsuranceContractLinked'), null, 'mesgs');
			header('Location: '.$backUrl);
			exit;
		}
		setEventMessages('', lmdbInsuranceMessages($contract), 'errors');
	}
}

$form = new Form($db);
llxHeader('', $langs->trans('LinkInsuranceContract'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');
$head = lmdbVehiclePrepareHead($vehicle);
print dol_get_fiche_head($head, 'card', $langs->trans('Vehicle'), -1, $vehicle->picto);
lmdbVehiclePrintBanner($vehicle);
print load_fiche_titre($langs->trans('LinkInsuranceContract'), '', 'link');
if (empty($contracts)) {
	print '<div class="warning">'.$langs->trans('InsuranceNoContractAvailableToLink').'</div>';
	print '<div class="tabsAction">'.dolGetButtonAction('', $langs->trans('NewInsuranceContract'), 'default', dol_buildpath('/lmdbvehiclemanagement/insurancecontract_card.php', 1).'?action=create&vehicle_id='.$id).'</div>';
} else {
	if ($coverageStart <= 0 && $contractId > 0 && isset($contractObjects[$contractId])) $coverageStart = (int) $contractObjects[$contractId]->date_start;
	print '<form class="lmdb-responsive-form" method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="link_contract">';
	print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('InsuranceContract').'</td><td>'.$form->selectarray('contract_id', $contracts, $contractId, 1, 0, 0, '', 1, 0, 0, '', 'minwidth500').'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('InsuranceCoverageType').'</td><td>'.$form->selectarray('coverage_type', array(LmdbVehicleInsuranceContract::COVERAGE_PRIMARY => $langs->trans('InsuranceCoveragePrimary'), LmdbVehicleInsuranceContract::COVERAGE_COMPLEMENTARY => $langs->trans('InsuranceCoverageComplementary')), $coverageType, 0, 0, 0, '', 1).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('InsuranceCoveragePeriod').'</td><td>'.$form->selectDate($coverageStart ?: -1, 'coverage_start', 0, 0, 1, '', 1, 1).' '.$form->selectDate($coverageEnd ?: -1, 'coverage_end', 0, 0, 1, '', 1, 1).'</td></tr>';
	print '</table></div><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Link').'"> ';
	print '<input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'" formnovalidate></div></form>';
}
print dol_get_fiche_end();
llxFooter();
$db->close();
