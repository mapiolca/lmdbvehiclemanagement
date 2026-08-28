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
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehicleinsurance.lib.php');

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
$failedContract = null;
$failedCoverage = null;
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

/**
 * Finish an insurance action with a native page message or a modal JSON payload.
 *
 * @param bool $success Success state
 * @param list<string> $messages Translated messages
 * @param int $vehicleId Vehicle id
 * @param bool $modal Modal mode
 * @return never
 */
function lmdbInsuranceFinish($success, $messages, $vehicleId, $modal)
{
	global $langs;

	if (empty($messages)) {
		$messages = array($langs->trans('Error'));
	}
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
	lmdbInsurancePopulateContractFromPost($contract);
	$coverage = lmdbInsuranceGetCoverageFromPost($contract);
	$vehicleIds = $coverage['vehicle_ids'];
	if ($isNew && !in_array($id, $vehicleIds, true)) $vehicleIds[] = $id;
	$coverageType = $coverage['coverage_type'];
	$coverageStart = $coverage['coverage_start'];
	$coverageEnd = $coverage['coverage_end'];
	$result = $contract->saveWithVehicleLinks($vehicleIds, $coverageType, (int) $coverageStart, $coverageEnd, $user);
	if ($result > 0) {
		lmdbInsuranceFinish(true, array($langs->trans($isNew ? 'InsuranceContractCreated' : 'InsuranceContractUpdated')), $id, $isModal);
	}
	if ($isModal) {
		lmdbInsuranceFinish(false, lmdbInsuranceMessages($contract), $id, true);
	}
	setEventMessages('', lmdbInsuranceMessages($contract), 'errors');
	$failedContract = $contract;
	$failedCoverage = array('vehicle_ids' => $vehicleIds, 'coverage_type' => $coverageType, 'coverage_start' => (int) $coverageStart, 'coverage_end' => $coverageEnd);
	$contractId = (int) $contract->id;
	$newContract = $isNew ? 1 : 0;
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
$editContract = $failedContract instanceof LmdbVehicleInsuranceContract ? $failedContract : ($selectedContract instanceof LmdbVehicleInsuranceContract ? $selectedContract : new LmdbVehicleInsuranceContract($db));

$vehicleOptions = lmdbInsuranceGetVehicleOptions($db, (int) $vehicle->entity);
$form = new Form($db);
$formfile = new FormFile($db);

if (!$isModal) {
	llxHeader('', $vehicle->ref.' - '.$langs->trans('Insurance'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');
	$head = lmdbVehiclePrepareHead($vehicle);
	print dol_get_fiche_head($head, '', $langs->trans('Vehicle'), -1, $vehicle->picto);
	lmdbVehiclePrintBanner($vehicle);
}

print '<div class="lmdb-insurance-content">';
print '<div class="lmdb-insurance-messages"></div>';
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
	$linkedIds = is_array($failedCoverage) ? $failedCoverage['vehicle_ids'] : ($editContract->id > 0 ? $editContract->getVehicleIds() : array($id));
	$currentEntry = null;
	foreach ($contracts as $entry) if ((int) $entry['contract']->id === (int) $editContract->id) $currentEntry = $entry;
	print load_fiche_titre($editContract->id > 0 ? $langs->trans('ModifyInsuranceContract') : $langs->trans('NewInsuranceContract'), '', 'edit');
	if (count($linkedIds) > 1) print '<div class="warning">'.$langs->trans('InsuranceSharedContractWarning', count($linkedIds)).'</div>';
	$coverageType = is_array($failedCoverage) ? $failedCoverage['coverage_type'] : ($currentEntry ? $currentEntry['coverage_type'] : LmdbVehicleInsuranceContract::COVERAGE_PRIMARY);
	$coverageStart = is_array($failedCoverage) ? $failedCoverage['coverage_start'] : ($currentEntry ? $currentEntry['date_start'] : ($editContract->date_start ?: 0));
	$coverageEnd = is_array($failedCoverage) ? $failedCoverage['coverage_end'] : ($currentEntry ? $currentEntry['date_end'] : $editContract->date_end);
	lmdbInsurancePrintContractForm(
		$editContract,
		$form,
		$vehicleOptions,
		$linkedIds,
		$coverageType,
		(int) $coverageStart,
		$coverageEnd,
		dol_buildpath('/lmdbvehiclemanagement/vehicle_insurance.php', 1),
		array('mode' => $isModal ? 'modal' : 'page', 'id' => $id, 'contract_id' => (int) $editContract->id, 'action' => 'save_contract')
	);
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
			print '<div class="center"><input type="file" name="contract_file"> <input type="submit" class="button" value="'.$langs->trans('AddFile').'"></div></form>';
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
