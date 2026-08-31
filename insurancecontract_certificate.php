<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecertificate.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsuranceconfig.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehicleinsurance.lib.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('main', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$certificateId = GETPOSTINT('certificate_id');
$downloadCertificate = GETPOSTINT('download_certificate');
$permissionWrite = $user->hasRight('lmdbvehiclemanagement', 'insurance', 'write');
$permissionUpload = $user->hasRight('lmdbvehiclemanagement', 'insurance', 'upload');
$permissionValidate = $user->hasRight('lmdbvehiclemanagement', 'insurance', 'validate');
$permissionDelete = $user->hasRight('lmdbvehiclemanagement', 'insurance', 'delete');
$contract = new LmdbVehicleInsuranceContract($db);
if ($id <= 0 || $contract->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));

$vehicleIds = $contract->getVehicleIds();
$vehicleOptions = array();
$eligibleVehicleIds = array();
$insuranceConfig = new LmdbVehicleInsuranceConfig($db);
foreach ($vehicleIds as $vehicleId) {
	$vehicle = new LmdbVehicle($db);
	if ($vehicle->fetch($vehicleId) > 0 && (int) $vehicle->entity === (int) $contract->entity) {
		$vehicleOptions[$vehicleId] = lmdbVehicleDisplayIdentifier($vehicle->ref, $vehicle->registration_number, $vehicle->label);
		if ($permissionWrite || $insuranceConfig->userIsEligibleForVehicle($user, $vehicleId, (int) $vehicle->entity)) $eligibleVehicleIds[] = $vehicleId;
	}
}

if ($downloadCertificate === 1) {
	$certificate = new LmdbVehicleInsuranceCertificate($db);
	if ($certificateId <= 0 || $certificate->fetch($certificateId) <= 0 || (int) $certificate->fk_contract !== $id || (int) $certificate->entity !== (int) $contract->entity) accessforbidden();
	$path = $certificate->getDocumentPath();
	if ($path === '' || !is_file($path)) accessforbidden($langs->trans('FileNotFound'));
	header('Content-Type: '.((string) $certificate->file_mime ?: 'application/octet-stream'));
	header('Content-Length: '.((int) filesize($path)));
	header('Content-Disposition: inline; filename="'.dol_sanitizeFileName(basename($path)).'"');
	header('X-Content-Type-Options: nosniff');
	readfile($path);
	exit;
}

if ($action === 'save_certificate' || $action === 'submit_certificate') {
	if (!$permissionUpload) accessforbidden();
	$vehicleId = GETPOSTINT('certificate_vehicle_id');
	if ($vehicleId <= 0 && !$permissionWrite) accessforbidden();
	if ($vehicleId > 0 && !in_array($vehicleId, $eligibleVehicleIds, true)) accessforbidden();
	$certificate = new LmdbVehicleInsuranceCertificate($db);
	$certificate->fk_contract = $id;
	$certificate->fk_vehicle = $vehicleId > 0 ? $vehicleId : null;
	$certificate->validity_start = (int) lmdbInsuranceGetDate('certificate_start');
	$certificate->validity_end = (int) lmdbInsuranceGetDate('certificate_end');
	$upload = isset($_FILES['certificate_file']) && is_array($_FILES['certificate_file']) ? $_FILES['certificate_file'] : array();
	$result = $certificate->createWithUploadedFile($upload, $action === 'submit_certificate', $user);
	if ($result > 0) {
		setEventMessages($langs->trans($action === 'submit_certificate' ? 'InsuranceCertificateSubmitted' : 'InsuranceCertificateSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}
	setEventMessages('', lmdbInsuranceMessages($certificate), 'errors');
} elseif (in_array($action, array('submit_existing_certificate', 'validate_certificate', 'reject_certificate', 'archive_certificate', 'delete_certificate'), true)) {
	$certificate = new LmdbVehicleInsuranceCertificate($db);
	if ($certificateId <= 0 || $certificate->fetch($certificateId) <= 0 || (int) $certificate->fk_contract !== $id || (int) $certificate->entity !== (int) $contract->entity) accessforbidden();
	if ($action === 'submit_existing_certificate') {
		if (!$permissionUpload || (empty($certificate->fk_vehicle) && !$permissionWrite) || (!empty($certificate->fk_vehicle) && !in_array((int) $certificate->fk_vehicle, $eligibleVehicleIds, true))) accessforbidden();
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
	if ($result > 0) {
		setEventMessages($langs->trans('InsuranceActionCompleted'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}
	setEventMessages('', lmdbInsuranceMessages($certificate), 'errors');
}

$certificates = array();
$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_certificate';
$sql .= ' WHERE fk_contract = '.$id.' AND entity = '.((int) $contract->entity).' ORDER BY date_creation DESC, rowid DESC';
$resql = $db->query($sql);
if ($resql) {
	while (is_object($row = $db->fetch_object($resql))) {
		$certificate = new LmdbVehicleInsuranceCertificate($db);
		if ($certificate->fetch((int) $row->rowid) > 0) $certificates[] = $certificate;
	}
	$db->free($resql);
}

$form = new Form($db);
llxHeader('', $contract->ref.' - '.$langs->trans('InsuranceCertificates'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');
$head = lmdbInsuranceContractPrepareHead($contract);
print dol_get_fiche_head($head, 'certificates', $langs->trans('InsuranceContract'), -1, $contract->picto);
lmdbInsuranceContractPrintBanner($contract);
print load_fiche_titre($langs->trans('InsuranceCertificates'), '', 'file-shield');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('InsuranceCertificateScope').'</th><th>'.$langs->trans('Period').'</th><th>'.$langs->trans('InsuranceEvidence').'</th><th class="center">'.$langs->trans('Status').'</th><th>'.$langs->trans('InsuranceRejectionReason').'</th><th></th></tr>';
foreach ($certificates as $certificate) {
	$scope = $langs->trans('InsuranceScopeFleet');
	if (!empty($certificate->fk_vehicle)) {
		$vehicleId = (int) $certificate->fk_vehicle;
		$scope = isset($vehicleOptions[$vehicleId]) ? '<a href="'.dol_buildpath('/lmdbvehiclemanagement/vehicle_card.php', 1).'?id='.$vehicleId.'">'.img_picto('', 'car', 'class="pictofixedwidth"').dol_escape_htmltag($vehicleOptions[$vehicleId]).'</a>' : $langs->trans('InsuranceScopeVehicle');
	}
	print '<tr class="oddeven"><td>'.$scope.'</td><td>'.dol_print_date($certificate->validity_start, 'day').' — '.dol_print_date($certificate->validity_end, 'day').'</td>';
	$link = !empty($certificate->file_name) ? $_SERVER['PHP_SELF'].'?id='.$id.'&download_certificate=1&certificate_id='.((int) $certificate->id) : '';
	print '<td>'.($link ? '<a href="'.$link.'">'.img_picto('', 'paperclip', 'class="pictofixedwidth"').dol_escape_htmltag(basename((string) $certificate->file_name)).'</a>' : '').'</td>';
	print '<td class="center">'.$certificate->getLibStatut(5).'</td><td>'.dol_escape_htmltag((string) $certificate->rejection_reason).'</td><td class="right">';
	$canSubmit = $permissionUpload && ((!empty($certificate->fk_vehicle) && in_array((int) $certificate->fk_vehicle, $eligibleVehicleIds, true)) || (empty($certificate->fk_vehicle) && $permissionWrite));
	if ((int) $certificate->status === LmdbVehicleInsuranceCertificate::STATUS_DRAFT && $canSubmit) print lmdbInsuranceCertificatePostButton($id, (int) $certificate->id, 'submit_existing_certificate', $langs->trans('SubmitForReview'), 'button');
	if ((int) $certificate->status === LmdbVehicleInsuranceCertificate::STATUS_PENDING && $permissionValidate) {
		print lmdbInsuranceCertificatePostButton($id, (int) $certificate->id, 'validate_certificate', $langs->trans('Validate'), 'button');
		print '<form class="inline-block" method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="certificate_id" value="'.((int) $certificate->id).'"><input type="hidden" name="action" value="reject_certificate"><input class="flat" name="rejection_reason" placeholder="'.$langs->trans('InsuranceRejectionReason').'"> <input type="submit" class="button button-cancel" value="'.$langs->trans('Reject').'"></form>';
	}
	if (in_array((int) $certificate->status, array(LmdbVehicleInsuranceCertificate::STATUS_VALIDATED, LmdbVehicleInsuranceCertificate::STATUS_REJECTED), true) && $permissionWrite) print lmdbInsuranceCertificatePostButton($id, (int) $certificate->id, 'archive_certificate', $langs->trans('Archive'), 'button');
	if ((int) $certificate->status === LmdbVehicleInsuranceCertificate::STATUS_DRAFT && $permissionDelete) print lmdbInsuranceCertificatePostButton($id, (int) $certificate->id, 'delete_certificate', $langs->trans('Delete'), 'button-delete');
	print '</td></tr>';
}
if (empty($certificates)) print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</table></div>';

$canCreate = $permissionUpload && ($permissionWrite || !empty($eligibleVehicleIds));
if ($canCreate) {
	$certificateVehicleOptions = array();
	if ($permissionWrite) $certificateVehicleOptions[0] = $langs->trans('InsuranceScopeFleet');
	foreach ($eligibleVehicleIds as $eligibleVehicleId) $certificateVehicleOptions[$eligibleVehicleId] = $vehicleOptions[$eligibleVehicleId];
	print load_fiche_titre($langs->trans('NewInsuranceCertificate'), '', 'plus');
	print '<form method="POST" enctype="multipart/form-data" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'">';
	print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('InsuranceCertificateScope').'</td><td>'.$form->selectarray('certificate_vehicle_id', $certificateVehicleOptions, '', 0, 0, 0, '', 1, 0, 0, '', 'minwidth500').'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Period').'</td><td>'.$form->selectDate(-1, 'certificate_start', 0, 0, 1, '', 1, 1).' '.$form->selectDate(-1, 'certificate_end', 0, 0, 1, '', 1, 1).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('InsuranceEvidence').'</td><td><input type="file" name="certificate_file" accept="application/pdf,image/jpeg,image/png"></td></tr>';
	print '</table></div><div class="center"><button type="submit" class="button" name="action" value="save_certificate">'.$langs->trans('SaveDraft').'</button> <button type="submit" class="button button-save" name="action" value="submit_certificate">'.$langs->trans('SubmitForReview').'</button></div></form>';
}
print dol_get_fiche_end();
llxFooter();
$db->close();

/**
 * Render a token-protected certificate action.
 *
 * @param int $contractId Contract id
 * @param int $certificateId Certificate id
 * @param string $action Action
 * @param string $label Label
 * @param string $class Button class
 * @return string
 */
function lmdbInsuranceCertificatePostButton($contractId, $certificateId, $action, $label, $class)
{
	$out = '<form class="inline-block" method="POST" action="'.dol_buildpath('/lmdbvehiclemanagement/insurancecontract_certificate.php', 1).'">';
	$out .= '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.((int) $contractId).'">';
	$out .= '<input type="hidden" name="certificate_id" value="'.((int) $certificateId).'"><input type="hidden" name="action" value="'.dol_escape_htmltag($action).'">';
	$out .= '<input type="submit" class="'.dol_escape_htmltag($class).'" value="'.dol_escape_htmltag($label).'"></form>';
	return $out;
}
