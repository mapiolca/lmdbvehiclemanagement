<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecertificate.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsuranceconfig.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('main', 'companies', 'contacts', 'mails', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$mode = GETPOST('mode', 'alpha');
$isModal = $mode === 'modal';
$contractId = GETPOSTINT('contract_id');
$newContract = GETPOSTINT('new_contract');
$certificateId = GETPOSTINT('certificate_id');
$downloadCertificate = GETPOSTINT('download_certificate');
$vehicle = new LmdbVehicle($db);
if ($id <= 0 || $vehicle->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));

$permissionWrite = $user->hasRight('lmdbvehiclemanagement', 'insurance', 'write');
$permissionUpload = $user->hasRight('lmdbvehiclemanagement', 'insurance', 'upload');
$permissionValidate = $user->hasRight('lmdbvehiclemanagement', 'insurance', 'validate');
$permissionDelete = $user->hasRight('lmdbvehiclemanagement', 'insurance', 'delete');
$insuranceConfig = new LmdbVehicleInsuranceConfig($db);
$eligibleUploader = $permissionUpload && ($permissionWrite || $insuranceConfig->userIsEligibleForVehicle($user, $id, (int) $vehicle->entity));

if ($newContract === 1) {
	$contractId = 0;
}

if ($certificateId > 0 && $contractId <= 0) {
	$targetCertificate = new LmdbVehicleInsuranceCertificate($db);
	if ($targetCertificate->fetch($certificateId) > 0) {
		$targetContract = new LmdbVehicleInsuranceContract($db);
		if ($targetContract->fetch((int) $targetCertificate->fk_contract) > 0 && in_array($id, $targetContract->getVehicleIds(), true) && (empty($targetCertificate->fk_vehicle) || (int) $targetCertificate->fk_vehicle === $id)) {
			$contractId = (int) $targetContract->id;
		}
	}
}

/** @return ?int */
function lmdbInsuranceGetDate($prefix)
{
	$day = GETPOSTINT($prefix.'day');
	$month = GETPOSTINT($prefix.'month');
	$year = GETPOSTINT($prefix.'year');
	if ($day <= 0 || $month <= 0 || $year <= 0) return null;

	return dol_mktime(12, 0, 0, $month, $day, $year);
}

/** @return array<int,string> */
function lmdbInsuranceMessages($object)
{
	global $langs;

	$messages = array();
	if (isset($object->error) && is_string($object->error) && $object->error !== '') $messages[] = $langs->trans($object->error);
	if (isset($object->errors) && is_array($object->errors)) {
		foreach ($object->errors as $error) if (is_string($error) && $error !== '') $messages[] = $langs->trans($error);
	}

	return array_values(array_unique($messages));
}

/** @return never */
function lmdbInsuranceFinish($success, $messages, $vehicleId, $modal)
{
	if ($modal) {
		header('Content-Type: application/json; charset=UTF-8');
		print json_encode(array('success' => (bool) $success, 'messages' => array_values($messages), 'refresh_parent' => (bool) $success));
		exit;
	}
	setEventMessages('', $messages, $success ? 'mesgs' : 'errors');
	header('Location: '.dol_buildpath('/lmdbvehiclemanagement/vehicle_insurance.php', 1).'?id='.((int) $vehicleId));
	exit;
}

/** @return bool */
function lmdbInsuranceContractCoversVehicle($contract, $vehicleId)
{
	return in_array((int) $vehicleId, $contract->getVehicleIds(), true);
}

if ($downloadCertificate === 1) {
	$certificate = new LmdbVehicleInsuranceCertificate($db);
	if ($certificateId <= 0 || $certificate->fetch($certificateId) <= 0) accessforbidden($langs->trans('RecordNotFound'));
	$contract = new LmdbVehicleInsuranceContract($db);
	if ($contract->fetch((int) $certificate->fk_contract) <= 0 || !lmdbInsuranceContractCoversVehicle($contract, $id) || (!empty($certificate->fk_vehicle) && (int) $certificate->fk_vehicle !== $id)) accessforbidden();
	$path = $certificate->getDocumentPath();
	if ($path === '' || !is_file($path)) accessforbidden($langs->trans('FileNotFound'));
	header('Content-Type: '.((string) $certificate->file_mime ?: 'application/octet-stream'));
	header('Content-Length: '.((int) filesize($path)));
	header('Content-Disposition: inline; filename="'.dol_sanitizeFileName(basename($path)).'"');
	header('X-Content-Type-Options: nosniff');
	readfile($path);
	exit;
}

if ($action === 'upload_contract_document') {
	if (!$permissionWrite) accessforbidden();
	$contract = new LmdbVehicleInsuranceContract($db);
	if ($contractId <= 0 || $contract->fetch($contractId) <= 0 || !lmdbInsuranceContractCoversVehicle($contract, $id)) accessforbidden();
	$directory = getMultidirOutput($contract, 'lmdbvehiclemanagement', 1);
	if (!is_string($directory) || $directory === '' || strpos($directory, 'error-diroutput-') === 0) {
		$contract->error = 'ErrorInvalidDirectory';
		lmdbInsuranceFinish(false, lmdbInsuranceMessages($contract), $id, $isModal);
	}
	$result = dol_add_file_process($directory, 0, 1, 'contract_file', '', null, '', 0, $contract);
	if ($result <= 0) {
		$contract->error = 'InsuranceContractDocumentUploadFailed';
	}
	lmdbInsuranceFinish($result > 0, $result > 0 ? array($langs->trans('FileAdded')) : lmdbInsuranceMessages($contract), $id, $isModal);
}

if ($action === 'save_contract') {
	if (!$permissionWrite) accessforbidden();
	$contract = new LmdbVehicleInsuranceContract($db);
	$isNew = $contractId <= 0;
	if (!$isNew && ($contract->fetch($contractId) <= 0 || !lmdbInsuranceContractCoversVehicle($contract, $id))) accessforbidden();
	if ($isNew) $contract->entity = (int) $vehicle->entity;
	$contract->fk_soc = GETPOSTINT('fk_soc');
	$contactId = GETPOSTINT('fk_contact');
	$contract->fk_contact = $contactId > 0 ? $contactId : null;
	$contract->policy_number = trim(GETPOST('policy_number', 'alphanohtml'));
	$contract->label = trim(GETPOST('contract_label', 'alphanohtml'));
	$contract->coverage_formula = trim(GETPOST('coverage_formula', 'restricthtml')) ?: null;
	$contract->date_start = (int) lmdbInsuranceGetDate('contract_start');
	$contract->date_end = lmdbInsuranceGetDate('contract_end');
	$contract->renewal_mode = GETPOST('renewal_mode', 'alpha') ?: 'fixed';
	$contract->notice_date = lmdbInsuranceGetDate('notice_date');
	$contract->assistance_phone = trim(GETPOST('assistance_phone', 'alphanohtml')) ?: null;
	$contract->assistance_email = trim(GETPOST('assistance_email', 'alphanohtml')) ?: null;
	$contract->claim_phone = trim(GETPOST('claim_phone', 'alphanohtml')) ?: null;
	$contract->claim_email = trim(GETPOST('claim_email', 'alphanohtml')) ?: null;
	$contract->description = GETPOST('contract_description', 'restricthtml') ?: null;
	$vehicleIds = GETPOST('vehicle_ids', 'array:int');
	$vehicleIds = is_array($vehicleIds) ? array_map('intval', $vehicleIds) : array();
	if ($isNew && !in_array($id, $vehicleIds, true)) $vehicleIds[] = $id;
	$coverageType = GETPOST('coverage_type', 'alpha');
	$coverageStart = lmdbInsuranceGetDate('coverage_start') ?: $contract->date_start;
	$coverageEnd = lmdbInsuranceGetDate('coverage_end');
	if ($coverageEnd === null) $coverageEnd = $contract->date_end;
	$result = $contract->saveWithVehicleLinks($vehicleIds, $coverageType, (int) $coverageStart, $coverageEnd, $user);
	if ($result > 0) {
		lmdbInsuranceFinish(true, array($langs->trans($isNew ? 'InsuranceContractCreated' : 'InsuranceContractUpdated')), $id, $isModal);
	}
	lmdbInsuranceFinish(false, lmdbInsuranceMessages($contract), $id, $isModal);
}

if (in_array($action, array('activate_contract', 'terminate_contract', 'delete_contract'), true)) {
	if (!$permissionWrite || ($action === 'delete_contract' && !$permissionDelete)) accessforbidden();
	$contract = new LmdbVehicleInsuranceContract($db);
	if ($contractId <= 0 || $contract->fetch($contractId) <= 0 || !lmdbInsuranceContractCoversVehicle($contract, $id)) accessforbidden();
	if ($action === 'activate_contract') $result = $contract->activate($user);
	elseif ($action === 'terminate_contract') $result = $contract->terminate($user);
	else $result = $contract->delete($user);
	lmdbInsuranceFinish($result > 0, $result > 0 ? array($langs->trans('InsuranceActionCompleted')) : lmdbInsuranceMessages($contract), $id, $isModal);
}

if ($action === 'save_certificate' || $action === 'submit_certificate') {
	if (!$eligibleUploader) accessforbidden();
	$contract = new LmdbVehicleInsuranceContract($db);
	if ($contractId <= 0 || $contract->fetch($contractId) <= 0 || !lmdbInsuranceContractCoversVehicle($contract, $id)) accessforbidden();
	$certificate = new LmdbVehicleInsuranceCertificate($db);
	$certificate->fk_contract = $contractId;
	$scope = GETPOST('certificate_scope', 'alpha');
	$certificate->fk_vehicle = $scope === 'fleet' && $permissionWrite ? null : $id;
	$certificate->validity_start = (int) lmdbInsuranceGetDate('certificate_start');
	$certificate->validity_end = (int) lmdbInsuranceGetDate('certificate_end');
	$upload = isset($_FILES['certificate_file']) && is_array($_FILES['certificate_file']) ? $_FILES['certificate_file'] : array();
	$result = $certificate->createWithUploadedFile($upload, $action === 'submit_certificate', $user);
	if ($result > 0) {
		lmdbInsuranceFinish(true, array($langs->trans($action === 'submit_certificate' ? 'InsuranceCertificateSubmitted' : 'InsuranceCertificateSaved')), $id, $isModal);
	}
	lmdbInsuranceFinish(false, lmdbInsuranceMessages($certificate), $id, $isModal);
}

if (in_array($action, array('submit_existing_certificate', 'validate_certificate', 'reject_certificate', 'archive_certificate', 'delete_certificate'), true)) {
	$certificate = new LmdbVehicleInsuranceCertificate($db);
	if ($certificateId <= 0 || $certificate->fetch($certificateId) <= 0) accessforbidden();
	$contract = new LmdbVehicleInsuranceContract($db);
	if ($contract->fetch((int) $certificate->fk_contract) <= 0 || !lmdbInsuranceContractCoversVehicle($contract, $id) || (!empty($certificate->fk_vehicle) && (int) $certificate->fk_vehicle !== $id)) accessforbidden();
	if ($action === 'submit_existing_certificate') {
		if (!$eligibleUploader) accessforbidden();
		$result = $certificate->submit($user);
	} elseif ($action === 'validate_certificate') {
		if (!$permissionValidate) accessforbidden();
		$result = $certificate->validateCertificate($user);
	} elseif ($action === 'reject_certificate') {
		if (!$permissionValidate) accessforbidden();
		$result = $certificate->reject($user, GETPOST('rejection_reason', 'restricthtml'));
	} elseif ($action === 'archive_certificate') {
		if (!$permissionWrite) accessforbidden();
		$result = $certificate->archive($user);
	} else {
		if (!$permissionDelete) accessforbidden();
		$result = $certificate->delete($user);
	}
	lmdbInsuranceFinish($result > 0, $result > 0 ? array($langs->trans('InsuranceActionCompleted')) : lmdbInsuranceMessages($certificate), $id, $isModal);
}

$contracts = LmdbVehicleInsuranceContract::getForVehicle($db, $id);
$selectedContract = null;
foreach ($contracts as $entry) {
	if ($contractId > 0 && (int) $entry['contract']->id === $contractId) $selectedContract = $entry['contract'];
}
if (!$selectedContract && !$newContract && !empty($contracts)) $selectedContract = $contracts[0]['contract'];
$editContract = $selectedContract instanceof LmdbVehicleInsuranceContract ? $selectedContract : new LmdbVehicleInsuranceContract($db);

$vehicleOptions = array();
$resql = $db->query('SELECT rowid, ref, registration_number, label FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle WHERE entity = '.((int) $vehicle->entity).' ORDER BY ref');
if ($resql) {
	while (is_object($row = $db->fetch_object($resql))) $vehicleOptions[(int) $row->rowid] = (string) $row->ref.' — '.(string) $row->registration_number.' — '.(string) $row->label;
	$db->free($resql);
}
$form = new Form($db);
$formfile = new FormFile($db);

if (!$isModal) {
	llxHeader('', $vehicle->ref.' - '.$langs->trans('Insurance'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');
	$head = lmdbVehiclePrepareHead($vehicle);
	print dol_get_fiche_head($head, '', $langs->trans('Vehicle'), -1, $vehicle->picto);
	lmdbVehiclePrintBanner($vehicle);
}

print '<div class="lmdb-insurance-content">';
print load_fiche_titre($langs->trans('InsuranceContracts'), '', 'shield-alt');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('InsurancePolicyNumber').'</th><th>'.$langs->trans('InsuranceCoverageType').'</th><th>'.$langs->trans('Period').'</th><th class="center">'.$langs->trans('Status').'</th><th></th></tr>';
foreach ($contracts as $entry) {
	$contract = $entry['contract'];
	$url = dol_buildpath('/lmdbvehiclemanagement/vehicle_insurance.php', 1).'?id='.$id.'&contract_id='.((int) $contract->id).($isModal ? '&mode=modal' : '');
	print '<tr class="oddeven"><td><a href="'.$url.'">'.dol_escape_htmltag($contract->ref).'</a></td><td>'.dol_escape_htmltag($contract->policy_number).'</td>';
	print '<td>'.$langs->trans($entry['coverage_type'] === LmdbVehicleInsuranceContract::COVERAGE_PRIMARY ? 'InsuranceCoveragePrimary' : 'InsuranceCoverageComplementary').'</td>';
	print '<td>'.dol_print_date($entry['date_start'], 'day').' — '.($entry['date_end'] ? dol_print_date($entry['date_end'], 'day') : $langs->trans('NoLimit')).'</td>';
	print '<td class="center">'.$contract->getLibStatut(5).'</td><td class="right"><a href="'.$url.'">'.img_picto($langs->trans('View'), 'view').'</a></td></tr>';
}
if (empty($contracts)) print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</table></div>';

if ($permissionWrite) {
	$newContractUrl = dol_buildpath('/lmdbvehiclemanagement/vehicle_insurance.php', 1).'?id='.$id.'&new_contract=1'.($isModal ? '&mode=modal' : '');
	print '<div class="tabsAction">'.dolGetButtonAction('', $langs->trans('NewInsuranceContract'), 'default', $newContractUrl).'</div>';
}

if ($permissionWrite) {
	$linkedIds = $editContract->id > 0 ? $editContract->getVehicleIds() : array($id);
	$currentEntry = null;
	foreach ($contracts as $entry) if ((int) $entry['contract']->id === (int) $editContract->id) $currentEntry = $entry;
	print load_fiche_titre($editContract->id > 0 ? $langs->trans('ModifyInsuranceContract') : $langs->trans('NewInsuranceContract'), '', 'edit');
	if (count($linkedIds) > 1) print '<div class="warning">'.$langs->trans('InsuranceSharedContractWarning', count($linkedIds)).'</div>';
	print '<form class="lmdb-insurance-ajax-form" method="POST" action="'.dol_buildpath('/lmdbvehiclemanagement/vehicle_insurance.php', 1).'">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="mode" value="'.($isModal ? 'modal' : 'page').'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="contract_id" value="'.((int) $editContract->id).'"><input type="hidden" name="action" value="save_contract">';
	print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('InsuranceCompany').'</td><td>'.$form->select_company($editContract->fk_soc ?: '', 'fk_soc', '', '-1', 0, 0, array(), 0, 'minwidth300').'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceContact').'</td><td>'.$form->selectcontacts(0, (int) $editContract->fk_contact, 'fk_contact', 1, '', '', 0, 'minwidth300', 0, 1).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('InsurancePolicyNumber').'</td><td><input class="flat minwidth300" name="policy_number" value="'.dol_escape_htmltag($editContract->policy_number).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Label').'</td><td><input class="flat minwidth500" name="contract_label" value="'.dol_escape_htmltag($editContract->label).'"></td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceCoverageFormula').'</td><td><input class="flat minwidth500" name="coverage_formula" value="'.dol_escape_htmltag((string) $editContract->coverage_formula).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Period').'</td><td>'.$form->selectDate($editContract->date_start ?: -1, 'contract_start', 0, 0, 1, '', 1, 1).' '.$form->selectDate($editContract->date_end ?: -1, 'contract_end', 0, 0, 1, '', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceRenewalMode').'</td><td>'.$form->selectarray('renewal_mode', array('fixed' => $langs->trans('InsuranceRenewalFixed'), 'tacit' => $langs->trans('InsuranceRenewalTacit')), $editContract->renewal_mode, 0, 0, 0, '', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceNoticeDate').'</td><td>'.$form->selectDate($editContract->notice_date ?: -1, 'notice_date', 0, 0, 1, '', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceAssistancePhone').'</td><td><input class="flat" name="assistance_phone" value="'.dol_escape_htmltag((string) $editContract->assistance_phone).'"></td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceAssistanceEmail').'</td><td><input class="flat minwidth300" name="assistance_email" value="'.dol_escape_htmltag((string) $editContract->assistance_email).'"></td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceClaimPhone').'</td><td><input class="flat" name="claim_phone" value="'.dol_escape_htmltag((string) $editContract->claim_phone).'"></td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceClaimEmail').'</td><td><input class="flat minwidth300" name="claim_email" value="'.dol_escape_htmltag((string) $editContract->claim_email).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Vehicles').'</td><td>'.$form->multiselectarray('vehicle_ids', $vehicleOptions, $linkedIds, 0, 0, 'minwidth500').'</td></tr>';
	$coverageType = $currentEntry ? $currentEntry['coverage_type'] : LmdbVehicleInsuranceContract::COVERAGE_PRIMARY;
	print '<tr><td>'.$langs->trans('InsuranceCoverageType').'</td><td>'.$form->selectarray('coverage_type', array('primary' => $langs->trans('InsuranceCoveragePrimary'), 'complementary' => $langs->trans('InsuranceCoverageComplementary')), $coverageType, 0, 0, 0, '', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceCoveragePeriod').'</td><td>'.$form->selectDate($currentEntry ? $currentEntry['date_start'] : ($editContract->date_start ?: -1), 'coverage_start', 0, 0, 1, '', 1, 1).' '.$form->selectDate($currentEntry && $currentEntry['date_end'] ? $currentEntry['date_end'] : -1, 'coverage_end', 0, 0, 1, '', 1, 1).'</td></tr>';
	print '<tr><td class="tdtop">'.$langs->trans('Description').'</td><td>';
	$contractEditor = new DolEditor('contract_description', (string) $editContract->description, '', 100, 'dolibarr_notes', 'In', true, false, isModEnabled('fckeditor'), ROWS_5, '100%');
	print $contractEditor->Create(1);
	print '</td></tr>';
	print '</table></div><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div></form>';
}

if ($selectedContract instanceof LmdbVehicleInsuranceContract) {
	print load_fiche_titre($langs->trans('InsuranceCertificates'), '', 'file-shield');
	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_certificate WHERE fk_contract = '.((int) $selectedContract->id);
	$sql .= ' AND (fk_vehicle = '.$id.' OR fk_vehicle IS NULL) AND entity = '.((int) $selectedContract->entity).' ORDER BY date_creation DESC, rowid DESC';
	$resql = $db->query($sql);
	$certificates = array();
	if ($resql) {
		while (is_object($row = $db->fetch_object($resql))) {
			$certificate = new LmdbVehicleInsuranceCertificate($db);
			if ($certificate->fetch((int) $row->rowid) > 0) $certificates[] = $certificate;
		}
		$db->free($resql);
	}
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('InsuranceCertificateScope').'</th><th>'.$langs->trans('Period').'</th><th>'.$langs->trans('InsuranceEvidence').'</th><th class="center">'.$langs->trans('Status').'</th><th>'.$langs->trans('InsuranceRejectionReason').'</th><th></th></tr>';
	foreach ($certificates as $certificate) {
		print '<tr class="oddeven"><td>'.$langs->trans(!empty($certificate->fk_vehicle) ? 'InsuranceScopeVehicle' : 'InsuranceScopeFleet').'</td><td>'.dol_print_date($certificate->validity_start, 'day').' — '.dol_print_date($certificate->validity_end, 'day').'</td>';
		$link = !empty($certificate->file_name) ? dol_buildpath('/lmdbvehiclemanagement/vehicle_insurance.php', 1).'?id='.$id.'&download_certificate=1&certificate_id='.((int) $certificate->id) : '';
		print '<td>'.($link ? '<a href="'.$link.'">'.img_picto('', 'paperclip', 'class="pictofixedwidth"').dol_escape_htmltag(basename((string) $certificate->file_name)).'</a>' : '').'</td>';
		print '<td class="center">'.$certificate->getLibStatut(5).'</td><td>'.dol_escape_htmltag((string) $certificate->rejection_reason).'</td><td class="right">';
		if ((int) $certificate->status === LmdbVehicleInsuranceCertificate::STATUS_DRAFT && $eligibleUploader) print lmdbInsurancePostButton($id, (int) $selectedContract->id, (int) $certificate->id, 'submit_existing_certificate', $langs->trans('SubmitForReview'), 'button');
		if ((int) $certificate->status === LmdbVehicleInsuranceCertificate::STATUS_PENDING && $permissionValidate) {
			print lmdbInsurancePostButton($id, (int) $selectedContract->id, (int) $certificate->id, 'validate_certificate', $langs->trans('Validate'), 'button');
			print '<form class="lmdb-insurance-ajax-form inline-block" method="POST" action="'.dol_buildpath('/lmdbvehiclemanagement/vehicle_insurance.php', 1).'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="mode" value="'.($isModal ? 'modal' : 'page').'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="certificate_id" value="'.((int) $certificate->id).'"><input type="hidden" name="action" value="reject_certificate"><input class="flat" name="rejection_reason" placeholder="'.$langs->trans('InsuranceRejectionReason').'"> <input type="submit" class="button button-cancel" value="'.$langs->trans('Reject').'"></form>';
		}
		if (in_array((int) $certificate->status, array(LmdbVehicleInsuranceCertificate::STATUS_VALIDATED, LmdbVehicleInsuranceCertificate::STATUS_REJECTED), true) && $permissionWrite) print lmdbInsurancePostButton($id, (int) $selectedContract->id, (int) $certificate->id, 'archive_certificate', $langs->trans('Archive'), 'button');
		if ((int) $certificate->status === LmdbVehicleInsuranceCertificate::STATUS_DRAFT && $permissionDelete) print lmdbInsurancePostButton($id, (int) $selectedContract->id, (int) $certificate->id, 'delete_certificate', $langs->trans('Delete'), 'button-delete');
		print '</td></tr>';
	}
	if (empty($certificates)) print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	print '</table></div>';

	if ($eligibleUploader) {
		print '<form class="lmdb-insurance-ajax-form" method="POST" enctype="multipart/form-data" action="'.dol_buildpath('/lmdbvehiclemanagement/vehicle_insurance.php', 1).'">';
		print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="mode" value="'.($isModal ? 'modal' : 'page').'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="contract_id" value="'.((int) $selectedContract->id).'">';
		print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield">';
		$scopeOptions = array('vehicle' => $langs->trans('InsuranceScopeVehicle'));
		if ($permissionWrite) $scopeOptions['fleet'] = $langs->trans('InsuranceScopeFleet');
		print '<tr><td class="titlefieldcreate">'.$langs->trans('InsuranceCertificateScope').'</td><td>'.$form->selectarray('certificate_scope', $scopeOptions, 'vehicle', 0, 0, 0, '', 1).'</td></tr>';
		print '<tr><td class="fieldrequired">'.$langs->trans('Period').'</td><td>'.$form->selectDate(-1, 'certificate_start', 0, 0, 1, '', 1, 1).' '.$form->selectDate(-1, 'certificate_end', 0, 0, 1, '', 1, 1).'</td></tr>';
		print '<tr><td class="fieldrequired">'.$langs->trans('InsuranceEvidence').'</td><td><input type="file" name="certificate_file" accept="application/pdf,image/jpeg,image/png"></td></tr>';
		print '</table></div><div class="center"><button type="submit" class="button" name="action" value="save_certificate">'.$langs->trans('SaveDraft').'</button> <button type="submit" class="button button-save" name="action" value="submit_certificate">'.$langs->trans('SubmitForReview').'</button></div></form>';
	}

	print '<div class="tabsAction">';
	if ($permissionWrite && (int) $selectedContract->status === LmdbVehicleInsuranceContract::STATUS_DRAFT) print lmdbInsurancePostButton($id, (int) $selectedContract->id, 0, 'activate_contract', $langs->trans('Activate'), 'button');
	if ($permissionWrite && (int) $selectedContract->status === LmdbVehicleInsuranceContract::STATUS_ACTIVE) print lmdbInsurancePostButton($id, (int) $selectedContract->id, 0, 'terminate_contract', $langs->trans('Terminate'), 'button');
	if ($permissionDelete && (int) $selectedContract->status === LmdbVehicleInsuranceContract::STATUS_DRAFT) print lmdbInsurancePostButton($id, (int) $selectedContract->id, 0, 'delete_contract', $langs->trans('Delete'), 'button-delete');
	print '</div>';

	$directory = getMultidirOutput($selectedContract, 'lmdbvehiclemanagement', 1);
	if (is_string($directory) && $directory !== '' && strpos($directory, 'error-diroutput-') !== 0) {
		if ($permissionWrite) {
			print '<form class="lmdb-insurance-ajax-form" method="POST" enctype="multipart/form-data" action="'.dol_buildpath('/lmdbvehiclemanagement/vehicle_insurance.php', 1).'">';
			print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="mode" value="'.($isModal ? 'modal' : 'page').'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="contract_id" value="'.((int) $selectedContract->id).'"><input type="hidden" name="action" value="upload_contract_document">';
			print '<div class="center"><input type="file" name="contract_file" required> <input type="submit" class="button" value="'.$langs->trans('AddFile').'"></div></form>';
		}
		print $formfile->showdocuments('lmdbvehiclemanagement', dol_sanitizeFileName($selectedContract->ref), $directory, dol_buildpath('/lmdbvehiclemanagement/vehicle_insurance.php', 1).'?id='.$id.'&contract_id='.((int) $selectedContract->id), 0, 0, '', 1, 0, 0, 28, 0, '&entity='.((int) $selectedContract->entity));
	}
}
print '</div>';

if (!$isModal) {
	print dol_get_fiche_end();
	llxFooter();
	$db->close();
}

/**
 * Render a small token-protected action form.
 *
 * @param int $vehicleId Vehicle id
 * @param int $contractId Contract id
 * @param int $certificateId Certificate id
 * @param string $action Action
 * @param string $label Label
 * @param string $class Button class
 * @return string
 */
function lmdbInsurancePostButton($vehicleId, $contractId, $certificateId, $action, $label, $class)
{
	$out = '<form class="lmdb-insurance-ajax-form inline-block" method="POST" action="'.dol_buildpath('/lmdbvehiclemanagement/vehicle_insurance.php', 1).'">';
	$out .= '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="mode" value="'.(GETPOST('mode', 'alpha') === 'modal' ? 'modal' : 'page').'">';
	$out .= '<input type="hidden" name="id" value="'.((int) $vehicleId).'"><input type="hidden" name="contract_id" value="'.((int) $contractId).'">';
	if ($certificateId > 0) $out .= '<input type="hidden" name="certificate_id" value="'.((int) $certificateId).'">';
	$out .= '<input type="hidden" name="action" value="'.dol_escape_htmltag($action).'"><input type="submit" class="'.dol_escape_htmltag($class).'" value="'.dol_escape_htmltag($label).'"></form>';

	return $out;
}
