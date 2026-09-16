<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Real object persistence and worker boundaries; native grant SQL is exercised in run_sharing.php.
$conf->entity = 1; $user->admin = 1; $user->socid = 0;
$user->rights->lmdbvehiclemanagement->quartix = (object) array('location' => 1, 'sync' => 1);
$user->rights->lmdbvehiclemanagement->odometer = (object) array('write' => 1);
$db->query("INSERT INTO ".MAIN_DB_PREFIX."lmdbvehiclemanagement_vehicle (rowid,entity,ref,label,fk_user_creat,date_creation) VALUES (90,1,'A-SHARED','Private live label',1,'2026-01-01')");
$ownership = new QxTestService($db);
$conf->entity = 2;
$mc = new class { public function getEntity($element, $shared = 1, $object = null) { global $conf; return $element === 'lmdbvehicle' ? '1,2' : (string) $conf->entity; } };
$vehicleEvents = QxTestVehicle::$events;
$ownership->associate($user, 90, 90, 'Europe/Paris', array(array('VehicleID' => 90, 'ShiftStartTime' => '00:00')), strtotime('2026-08-01'));
$sourceB = $ownership->dataset(90);
$linkB = $ownership->link(90);
qxCheck((int) $sourceB->entity === 2 && (int) $linkB->fk_quartix === (int) $sourceB->id && QxTestVehicle::$events === $vehicleEvents, 'B associates a shared vehicle without mutating A vehicle or dispatching its trigger');
$summaryB = array_replace($summary, array('VehicleID' => 90, 'Distance' => 234.5));
$ownership->saveUsage($linkB, array($summaryB), '2026-08-30', '2026-08-30');
$positionB = array_replace($position, array('VehicleID' => 90, 'LastEventDatetime' => '2026-08-30T12:00:00Z'));
$ownership->savePosition($linkB, $positionB, 'offset');
$readingB = new QxTestReading($db);
$readingBId = $readingB->saveQuartix($user, 90, 90, strtotime('2026-08-30T12:00:00Z'), '2026-08-30', 1234.0);
qxCheck($readingBId > 0 && (int) $readingB->entity === 2 && (int) $readingB->fk_quartix === (int) $sourceB->id, 'Shared-vehicle mileage belongs to its collector');
$conf->entity = 1;
qxReject(static function () use ($ownership, $sourceB) { $ownership->dataset(90, (int) $sourceB->id); }, 'QxAccessDenied');
qxCheck((new QxTestReading($db))->fetch($readingBId) <= 0, 'A cannot read B mileage even though A owns the vehicle');
$ownership->associate($user, 90, 91, 'Europe/Paris', array(array('VehicleID' => 91, 'ShiftStartTime' => '00:00')), strtotime('2026-08-01'));
$sourceA = $ownership->dataset(90); $linkA = $ownership->link(90);
$ownership->saveUsage($linkA, array(array_replace($summary, array('VehicleID' => 91, 'Distance' => 17.0))), '2026-08-30', '2026-08-30');
qxCheck($sourceA->id !== $sourceB->id && $ownership->usage(90, '2026-08-30', '2026-08-30', 'day')[0]->distance == 17, 'Two collector links coexist; local source is the default');
$conf->entity = 2;
qxCheck($ownership->usage(90, '2026-08-30', '2026-08-30', 'day')[0]->distance == 234.5, 'B totals never include the A source');
// Vehicle access revoked while credentials, association and requests remain active.
$mc = null;
qxCheck($ownership->dataset(90)->snapshot_vehicle_label === 'A-SHARED', 'Historical identity is retained without reading the current vehicle');
qxReject(static function () use ($ownership) { $ownership->vehicle(90); }, 'QxAccessDenied');
qxCheck($ownership->usage(90, '2026-08-30', '2026-08-30', 'day')[0]->distance == 234.5 && $ownership->position(90) !== null, 'Owner can read retained usage and position after withdrawal');
qxCheck((new QxTestReading($db))->fetch($readingBId) > 0, 'Owner mileage history survives vehicle withdrawal');
qxCheck($ownership->readings(90, (int) $sourceB->id, 20, 0)['total'] === 1, 'Historical mileage page queries only the authorized source after withdrawal');
qxReject(static function () use ($ownership, $linkB, $summaryB) { $ownership->saveUsage($linkB, array($summaryB), '2026-08-30', '2026-08-30'); }, 'QxAccessDenied');
$blockedClient = new QxTestClient($db, 2);
qxReject(static function () use ($blockedClient) { $blockedClient->get('/vehicles/live', array('VehicleIDList' => '90')); }, 'QxAccessDenied');
qxCheck($blockedClient->calls === array(), 'No authentication or QWS data request after withdrawal');
$blockedCron = new QxTestCron($db); $blockedCron->client = $blockedClient;
$conf->global->LMDBVEHICLEMANAGEMENT_QX_ENABLED = 1;
// Other entity-2 fixtures are suspended so the collector cohort is deterministic.
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link SET active=0 WHERE entity=2 AND fk_vehicle<>90');
foreach (array('positions', 'odometer', 'usage', 'trips') as $kind) {
	$blockedCron->run($kind);
	qxCheck($blockedClient->calls === array(), $kind.' does not contact QWS for inaccessible vehicles');
}
$ownership->setActive($user, 90, 0);
$mc = new class { public function getEntity($element, $shared = 1, $object = null) { global $conf; return $element === 'lmdbvehicle' ? '1,2' : (string) $conf->entity; } };
qxReject(static function () use ($ownership, $linkB) { $ownership->assertOwner($linkB); }, 'QxAccessDenied');
$ownership->setActive($user, 90, 1);
$ownership->assertOwner($ownership->link(90));
qxCheck(true, 'Restored access resumes enabled associations, while manual suspension remains effective');
$mc = null;
$ownership->disassociate($user, 90, (int) $linkB->rowid, 'reassignment');
qxCheck($ownership->link(90) === null && (int) $ownership->dataset(90)->id === (int) $sourceB->id, 'Dissociation after withdrawal preserves data identity');
$conf->entity = 1;
$ownership->disassociate($user, 90, (int) $linkA->rowid, 'error');
$deletedVehicle = new QxTestVehicle($db); $deletedVehicle->fetch(90); $deletedVehicle->has_document_storage = false;
qxCheck($deletedVehicle->delete($user) > 0, 'Native vehicle deletion in A succeeds independently of B history');
$conf->entity = 2;
qxCheck((new QxTestReading($db))->fetch($readingBId) > 0 && (int) $ownership->dataset(90)->id === (int) $sourceB->id, 'Deleting A association and vehicle leaves B historical data intact');
$user->admin = 0;
qxReject(static function () use ($ownership, $user) { $ownership->disassociate($user, 90, 0, 'error'); }, 'QxAccessDenied');
$user->admin = 1;
$db->begin(); // Roll back this cleanup so migration still exercises orphan history.
$ownership->disassociate($user, 90, 0, 'error');
qxCheck($ownership->readings(90, (int) $sourceB->id)['total'] === 0 && $ownership->dataset(90)->id === $sourceB->id, 'Authorized cleanup without vehicle or association preserves persistent source identity');
$db->rollback();
$conf->entity = 1; $mc = null;
