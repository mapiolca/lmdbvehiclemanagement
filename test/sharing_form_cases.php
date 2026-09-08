<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Executed with the real MC form, module controller and persistence; SQL/DAO doubles.
$savedFixtures = DaoMulticompany::$fixtures;
$conf->entity = 1; $user->admin = 1; $user->entity = 0;
$user->rights = array('lmdbvehiclemanagement.read' => 1);
$conf->global->MULTICOMPANY_LMDBVEHICLE_SHARE_ALL_BY_DEFAULT = 0;
$vehicleFormObject = new SharingRecord($db);
$vehicleFormObject->element = 'lmdbvehicle';
$vehicleFormObject->table_element = $definitions['lmdbvehicle']['table'];
$vehicleFormObject->TRIGGER_PREFIX = 'LMDBVEHICLE';
$vehicleFormObject->fetch(10);
$dao->setSharingsByElement('lmdbvehicle', 10, array(2));
$_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = array('action' => 'edit'); $_POST = array();
ob_start(); lmdbSharingRender($vehicleFormObject, true); $embeddedHtml = ob_get_clean();
$dom = new DOMDocument(); @$dom->loadHTML($embeddedHtml); $xpath = new DOMXPath($dom);
checkSharing($xpath->query('//form|//input[@type="submit"]')->length === 0, 'Embedded sharing has no nested form or second Save');
checkSharing($xpath->query('//input[@name="lmdb_sharing_present" and @disabled]')->length === 1, 'Selection is ignored until native initialization so failed JavaScript never revokes grants');
checkSharing($xpath->query('//select[@name="lmdbvehicle_to[]"]/option[@value="2"]')->length === 1, 'Edit form preselects saved grants');
checkSharing($xpath->query('//select[@name="from[]"]/option[@value="2"]')->length === 0, 'Saved grants never appear on both sides');
$newVehicle = clone $vehicleFormObject; $newVehicle->id = 0; $newVehicle->entity = null;
$_GET = array('action' => 'create');
foreach (array(0, 1) as $exclusion) {
	$conf->global->MULTICOMPANY_LMDBVEHICLE_SHARE_ALL_BY_DEFAULT = $exclusion;
	ob_start(); lmdbSharingRender($newVehicle, true); $html = ob_get_clean();
	$dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
	checkSharing($xpath->query('//select[@name="lmdbvehicle_to[]"]/option')->length === ($exclusion ? 2 : 0), 'Create form follows the configured native default without a persisted object');
	checkSharing($xpath->query('//select[@name="from[]"]/option')->length === ($exclusion ? 0 : 2), 'New selection and available destinations are complementary');
}
checkSharing(empty($newVehicle->id) && $newVehicle->entity === null, 'Rendering a new selector does not mutate the object');
$conf->global->MULTICOMPANY_LMDBVEHICLE_SHARE_ALL_BY_DEFAULT = 0;
$_SESSION['token'] = 'fixture'; $_SERVER['REQUEST_METHOD'] = 'POST'; $_GET = array();
foreach (array(array('3'), array()) as $selection) {
	$_POST = array('action' => 'update', 'token' => 'fixture', 'lmdb_sharing_present' => 'lmdbvehicle', 'lmdbvehicle_to' => $selection);
	ob_start(); lmdbSharingRender($vehicleFormObject, true); $html = ob_get_clean();
	$dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
	$options = $xpath->query('//select[@name="lmdbvehicle_to[]"]/option');
	checkSharing($options->length === count($selection) && (!$selection || $options->item(0)->getAttribute('value') === '3'), 'Validation failure preserves changed and cleared selections');
}
$_POST = array('token' => 'fixture');
checkSharing(lmdbSharingSavePosted($vehicleFormObject, true) > 0 && LmdbVehicleSharing::stored($db, 'lmdbvehicle', 10) === array(2), 'An editor without a rendered selector cannot erase grants');
$_POST['lmdb_sharing_present'] = 'lmdbvehicle';
$_POST['lmdbvehicle_to'] = array('3');
$db->begin();
$db->query('UPDATE '.MAIN_DB_PREFIX.$vehicleFormObject->table_element." SET ref='CHANGED' WHERE rowid=10 AND entity=1");
checkSharing(lmdbSharingSavePosted($vehicleFormObject, true) > 0, 'Embedded selection uses the native sharing mutation');
$db->rollback();
checkSharing(LmdbVehicleSharing::stored($db, 'lmdbvehicle', 10) === array(2), 'Sharing rolls back with the enclosing object transaction');
foreach (array(array('token' => ''), array('token' => 'invalid'), array('lmdbvehicle_to' => array(array(2))), array('lmdbvehicle_to' => array('2x')), array('lmdbvehicle_to' => array('999'))) as $invalid) {
	$_POST = array_replace(array('token' => 'fixture', 'lmdb_sharing_present' => 'lmdbvehicle', 'lmdbvehicle_to' => array('3')), $invalid);
	$db->begin();
	$db->query('INSERT INTO '.MAIN_DB_PREFIX.$vehicleFormObject->table_element." (rowid,entity,ref) VALUES (99,1,'TEMP')");
	$created = clone $vehicleFormObject; $created->id = 99;
	checkSharing(lmdbSharingSavePosted($created, true) < 0, 'Invalid embedded sharing is refused');
	$db->rollback();
	checkSharing((int) $db->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.$vehicleFormObject->table_element.' WHERE rowid=99')->fetchColumn() === 0, 'Refused sharing leaves no partial creation');
}
$_POST = array('token' => 'fixture', 'lmdb_sharing_present' => 'lmdbvehicle');
checkSharing(lmdbSharingSavePosted($vehicleFormObject, true) > 0 && LmdbVehicleSharing::stored($db, 'lmdbvehicle', 10) === array(), 'Explicit empty selection revokes every grant');
foreach (array(array(0, 1), array(1, 0)) as $profile) {
	$user->admin = $profile[0]; $user->rights['lmdbvehiclemanagement.read'] = $profile[1];
	ob_start(); lmdbSharingRender($vehicleFormObject, true); $html = ob_get_clean();
	checkSharing($html === '' && lmdbSharingSavePosted($vehicleFormObject, true) < 0, 'Role and functional rights independently protect embedded sharing');
}
$user->admin = 1; $user->rights['lmdbvehiclemanagement.read'] = 1;
$_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = array(); $_GET = array();
$dataset = new SharingRecord($db); $dataset->element = 'lmdbvehiclequartix';
$dataset->table_element = $definitions['lmdbvehiclequartix']['table']; $dataset->fetch(10);
$dao->setSharingsByElement('lmdbvehiclequartix', 10, array(2));
$mileageView = false;
$sourceSelectorHtml = '';
ob_start(); include dirname(__DIR__).'/tpl/quartix_sharing.tpl.php'; $modalHtml = ob_get_clean();
$dom = new DOMDocument(); @$dom->loadHTML($modalHtml); $xpath = new DOMXPath($dom);
checkSharing($xpath->query('//*[@id="qx-sharing-dialog" and @hidden]//form[@method="POST"]')->length === 1, 'QUARTIX sharing form starts hidden behind its action');
checkSharing($xpath->query('//*[@id="qx-sharing-dialog"]//form//select[@name="lmdbvehiclequartix_to[]"]')->length === 1, 'QUARTIX selection remains inside its own POST form');
checkSharing($xpath->query('//div[contains(concat(" ", @class, " "), " tabsAction ")]//a')->length === 2 && $xpath->query('//*[@id="qx-sharing-open"]')->length === 1 && $xpath->query('//input[@name="token"]')->length === 1, 'Mileage and sharing actions share the native action bar with one protected form');
$_GET = array('action' => 'qx_sharing');
ob_start(); include dirname(__DIR__).'/tpl/quartix_sharing.tpl.php'; $html = ob_get_clean();
checkSharing(strpos($html, 'id="qx-sharing-dialog" hidden') === false && strpos($html, 'data-auto-open="1"') !== false, 'Direct action has a usable fallback and opens the modal');
$_GET = array(); $conf->entity = 2;
ob_start(); include dirname(__DIR__).'/tpl/quartix_sharing.tpl.php'; $html = ob_get_clean();
$dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
checkSharing($xpath->query('//form|//*[@id="qx-sharing-open"]')->length === 0 && $xpath->query('//*[@id="qx-view-switch"]')->length === 1, 'Beneficiaries retain usage/mileage navigation without QUARTIX sharing controls');
$conf->entity = 1;
if (getenv('LMDB_SHARING_FIXTURE')) {
	$sharingFixture = true; $conf->global->MAIN_HTML_FOOTER = '';
	ob_start(); include dirname(__DIR__).'/tpl/quartix_sharing.tpl.php'; $fixtureModal = ob_get_clean();
	$_GET = array('action' => 'create');
	ob_start(); lmdbSharingRender($newVehicle, true); $fixtureCreate = ob_get_clean();
	file_put_contents(getenv('LMDB_SHARING_FIXTURE'), '<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/jquery-ui.css"><style>body{font:14px sans-serif} [hidden]{display:none!important}</style><script src="/jquery.js"></script><script src="/jquery-ui.js"></script><h1>Synthetic sharing forms</h1>'.$fixtureModal.'<h2>Create vehicle</h2><form method="POST" action="/save"><input name="label" value="Synthetic vehicle"><input name="token" type="hidden" value="fixture">'.$fixtureCreate.'<button type="submit">Save vehicle</button></form>'.$conf->global->MAIN_HTML_FOOTER.'</html>');
	$sharingFixture = false;
}
$_GET = $_POST = array();
DaoMulticompany::$fixtures = $savedFixtures;
