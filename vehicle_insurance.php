<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecertificate.class.php');

/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('main', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();

$id = GETPOSTINT('id');
$contractId = GETPOSTINT('contract_id');
$certificateId = GETPOSTINT('certificate_id');
$vehicle = new LmdbVehicle($db);
if ($id <= 0 || $vehicle->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));

if ($certificateId > 0) {
	$certificate = new LmdbVehicleInsuranceCertificate($db);
	if ($certificate->fetch($certificateId) > 0 && (empty($certificate->fk_vehicle) || (int) $certificate->fk_vehicle === $id)) $contractId = (int) $certificate->fk_contract;
}

$target = null;
if ($contractId > 0) {
	$contract = new LmdbVehicleInsuranceContract($db);
	if ($contract->fetch($contractId) > 0 && in_array($id, $contract->getVehicleIds(), true)) $target = $contract;
}
if (!$target instanceof LmdbVehicleInsuranceContract) $target = LmdbVehicleInsuranceContract::getPrimaryForVehicle($db, $id);
if (!$target instanceof LmdbVehicleInsuranceContract) {
	$contracts = LmdbVehicleInsuranceContract::getForVehicle($db, $id);
	if (!empty($contracts)) $target = $contracts[0]['contract'];
}

if ($target instanceof LmdbVehicleInsuranceContract) {
	$url = dol_buildpath('/lmdbvehiclemanagement/insurancecontract_certificate.php', 1).'?id='.((int) $target->id);
	if ($certificateId > 0) $url .= '&certificate_id='.$certificateId;
} else {
	$url = dol_buildpath('/lmdbvehiclemanagement/vehicle_card.php', 1).'?id='.$id;
}
header('Location: '.$url);
exit;
