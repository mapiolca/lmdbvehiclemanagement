<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumption.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumptionpayment.class.php');

/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('main', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$consumption = new LmdbVehicleConsumption($db);
if ($id <= 0 || $consumption->fetch($id) <= 0 || empty($consumption->fk_payment_various)) {
	accessforbidden($langs->trans('RecordNotFound'));
}

$consumptionPayment = new LmdbVehicleConsumptionPayment($db);
$path = $consumptionPayment->getReceiptPath($consumption);
if ($path === '' || !is_file($path)) {
	accessforbidden($langs->trans('FileNotFound'));
}

$mimeByExtension = array(
	'pdf' => 'application/pdf',
	'jpg' => 'image/jpeg',
	'png' => 'image/png',
);
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if (!isset($mimeByExtension[$extension])) {
	accessforbidden($langs->trans('FileNotFound'));
}

header('Content-Type: '.$mimeByExtension[$extension]);
header('Content-Length: '.((int) filesize($path)));
header('Content-Disposition: inline; filename="'.dol_sanitizeFileName(basename($path)).'"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
