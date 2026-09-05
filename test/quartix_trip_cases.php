<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Synthetic values; field names/types verified against an authenticated QWS response.
$trips = new LmdbVehicleQuartixTrips($db);
$tripLink = $trips->link(1);
$tripDay = (new DateTimeImmutable('@'.dol_now()))->modify('-1 day')->format('Y-m-d');
$previousDay = LmdbVehicleQuartixRules::day($tripDay)->modify('-1 day')->format('Y-m-d');
$trip = array('VehicleID' => (int) $tripLink->remote_id, 'DriverID' => 500, 'Timezone' => 'Untrusted provider zone',
	'StartDateTimeLocal' => $tripDay.'T12:00:00', 'StartDateTime' => $tripDay.'T12:00:00+02:00', 'StartLocation' => 'Test departure', 'StartLat' => 48.1, 'StartLong' => 2.1,
	'EndDateTimeLocal' => $tripDay.'T13:00:00', 'EndDateTime' => $tripDay.'T13:00:00+02:00', 'EndLocation' => 'Test arrival', 'EndLat' => 48.2, 'EndLong' => 2.2,
	'TravelTime' => 0.04, 'IdlingTime' => 0.001, 'Distance' => 42.0, 'PrivacyDistance' => 0.0, 'Distances' => array(), 'AvgSpeed' => 42.0, 'MaxSpeed' => 70.0, 'InProgress' => true, 'IsPrivate' => false);
$trips->saveDay($tripLink, array($trip), $tripDay, 'qws');
$journal = $trips->journal(1, $tripDay, $tripDay);
qxCheck($journal['total'] === 1 && $journal['rows'][0]->arrival === null && $journal['rows'][0]->end_location === null, 'Open trip never invents arrival from provisional API fields');
qxCheck($journal['rows'][0]->in_progress == 1 && $journal['days'][0]->has_open == 1, 'Open status drives day refresh');
$trip['InProgress'] = false;
$trips->saveDay($tripLink, array($trip, $trip), $tripDay, 'qws');
$journal = $trips->journal(1, $tripDay, $tripDay);
qxCheck($journal['total'] === 1 && $journal['rows'][0]->arrival !== null && $journal['days'][0]->has_open == 0, 'Completion replaces snapshot and exact duplicate is removed');
$beforeRows = $db->pdo->query('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_trip')->fetchAll(PDO::FETCH_ASSOC);
$badTrip = $trip; $badTrip['Distance'] = -1;
qxReject(static function () use ($trips, $tripLink, $tripDay, $trip, $badTrip) { $trips->saveDay($tripLink, array($trip, $badTrip), $tripDay, 'qws'); }, 'QxInvalidResponse');
qxCheck($beforeRows === $db->pdo->query('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_trip')->fetchAll(PDO::FETCH_ASSOC), 'Conflicting snapshot cannot replace the previous cache');
$badTrip = $trip; $badTrip['EndDateTime'] = $previousDay.'T10:00:00Z';
qxReject(static function () use ($trips, $tripLink, $tripDay, $badTrip) { $trips->saveDay($tripLink, array($badTrip), $tripDay, 'qws'); }, 'QxInvalidResponse');
$db->failPattern = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_trip (';
qxReject(static function () use ($trips, $tripLink, $tripDay, $trip) { $trips->saveDay($tripLink, array($trip), $tripDay, 'qws'); }, 'QxDatabaseError');
$db->failPattern = '';
qxCheck($beforeRows === $db->pdo->query('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_trip')->fetchAll(PDO::FETCH_ASSOC), 'Insert failure rolls back child deletion and manifest update');
$overlap = $trip; $overlap['StartDateTime'] = $previousDay.'T23:00:00+02:00';
$trips->saveDay($tripLink, array($trip, $overlap), $tripDay, 'qws');
qxCheck($trips->journal(1, $tripDay, $tripDay)['total'] === 1, 'Trips returned because they end in the day are excluded');
qxCheck(LmdbVehicleQuartixTrips::reportingDay(strtotime($tripDay.'T07:59:00+02:00'), 'Europe/Paris', '08:00') === $previousDay, 'Non-midnight reporting day uses departure before shift');
$installedLink = clone $tripLink; $installedLink->sync_from = $tripDay.' 11:00:00';
qxCheck(LmdbVehicleQuartixTrips::normalize(array($trip), $installedLink, $tripDay, 'qws')['rows'] === array(), 'Partial installation day discards earlier trips');
$winter = $trip; $winter['StartDateTime'] = '2025-10-26T02:15:00+02:00'; $winter['EndDateTime'] = '2025-10-26T02:15:00+01:00';
$winterLink = clone $tripLink; $winterLink->shift_start = '00:00'; $winterLink->sync_from = null;
$normalized = LmdbVehicleQuartixTrips::normalize(array($winter), $winterLink, '2025-10-26', 'qws');
qxCheck($normalized['rows'][0]['arrival'] - $normalized['rows'][0]['departure'] === 3600, 'DST offset preserves elapsed hour');
$winter['StartDateTime'] = '2025-10-26T02:15:00';
qxReject(static function () use ($winter, $winterLink) { LmdbVehicleQuartixTrips::normalize(array($winter), $winterLink, '2025-10-26', 'qws'); }, 'QxAmbiguousTime');
foreach (array('distance', 'flag', 'unknown') as $privacyCase) {
	$private = $trip;
	if ($privacyCase === 'distance') $private['PrivacyDistance'] = 2.0;
	elseif ($privacyCase === 'flag') $private['IsPrivate'] = true;
	else { unset($private['IsPrivate'], $private['PrivacyDistance']); }
	$trips->saveDay($tripLink, array($private), $tripDay, 'qws');
	$row = $trips->journal(1, $tripDay, $tripDay, 'private')['rows'][0];
	qxCheck($row->is_private == 1 && $row->distance == 42 && $row->in_progress === null, 'Private summary keeps distance without precise status: '.$privacyCase);
	foreach (array('departure', 'arrival', 'start_location', 'end_location', 'travel_time', 'idling_time') as $column) qxCheck($row->{$column} === null, 'Private '.$column.' never stored: '.$privacyCase);
	qxCheck(strpos(json_encode($row), 'Test departure') === false && !property_exists($row, 'DriverID') && !property_exists($row, 'StartLat'), 'No driver, route or locations in private cache');
}
$trips->saveDay($tripLink, array(), $tripDay, 'qws');
$journal = $trips->journal(1, $tripDay, $tripDay);
qxCheck($journal['total'] === 0 && count($journal['days']) === 1 && $journal['days'][0]->trip_count == 0, 'Successful empty day is distinct from unsynchronized day');
$trips->saveDay($tripLink, array($trip), $tripDay, 'qws');
$second = $trip; $second['StartDateTime'] = $tripDay.'T14:00:00+02:00'; $second['EndDateTime'] = $tripDay.'T15:00:00+02:00'; $second['Distance'] = 10.0;
$trips->saveDay($tripLink, array($trip, $second), $tripDay, 'qws');
$firstPage = $trips->journal(1, $tripDay, $tripDay, 'done', 1, 0, 'distance', 'ASC');
$secondPage = $trips->journal(1, $tripDay, $tripDay, 'done', 1, 1, 'distance', 'ASC');
qxCheck($firstPage['total'] === 2 && count($firstPage['rows']) === 1 && $firstPage['rows'][0]->distance == 10 && $secondPage['rows'][0]->distance == 42, 'Journal filters, sorting and SQL pagination remain coherent');
$user->admin = 0;
qxReject(static function () use ($trips, $tripDay) { $trips->journal(1, $tripDay, $tripDay); }, 'QxAccessDenied');
$user->rights->lmdbvehiclemanagement->quartix = (object) array('location' => 1);
qxCheck($trips->journal(1, $tripDay, $tripDay)['total'] === 2, 'Standard user with GPS and read rights can read journal');
$user->rights->lmdbvehiclemanagement->quartix = (object) array();
$user->admin = 1; $user->socid = 3;
qxReject(static function () use ($trips, $tripDay) { $trips->journal(1, $tripDay, $tripDay); }, 'QxAccessDenied');
$user->socid = 0;
$conf->entity = 2;
qxReject(static function () use ($trips, $tripLink, $tripDay, $trip) { $trips->saveDay($tripLink, array($trip), $tripDay, 'qws'); }, 'QxAccessDenied');
$mc = new class { public function getEntity($element, $shared = 1, $object = null) { return '1,2'; } };
qxCheck($trips->journal(1, $tripDay, $tripDay)['total'] === 2, 'Shared journal reads owner data and retention');
$mc = null; $conf->entity = 1;
foreach (array('', '0', '-1', '1.5', '2e2', ' 30', '9999999999') as $invalid) qxReject(static function () use ($invalid) { LmdbVehicleQuartixTrips::retention($invalid); }, 'QxInvalidRetention');
qxCheck(LmdbVehicleQuartixTrips::retention('30') === 30 && LmdbVehicleQuartixTrips::retention('3650') === 3650, 'Retention allows positive integers beyond twelve months');
$conf->global->LMDBVEHICLEMANAGEMENT_QX_TRIP_RETENTION_DAYS = '1';
qxCheck($trips->journal(1, $tripDay, $tripDay)['total'] === 0 && count($trips->journal(1, $tripDay, $tripDay)['days']) === 0, 'Reduced retention immediately hides rows and coverage before purge');
$conf->global->LMDBVEHICLEMANAGEMENT_QX_ENABLED = 0;
$tripCron = new QxTestCron($db); $tripCron->client = new QxTestClient($db, 1);
qxCheck($tripCron->trips() === 0 && !$tripCron->client->calls, 'Paused synchronization still purges without API calls');
qxCheck((int) $db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_trip')->fetchColumn() === 0, 'Paused purge removes expired trips');
$conf->global->LMDBVEHICLEMANAGEMENT_QX_ENABLED = 1;
$conf->global->LMDBVEHICLEMANAGEMENT_QX_TRIP_RETENTION_DAYS = '30';
$trips->saveDay($tripLink, array($trip), $tripDay, 'qws');
$db->query('UPDATE '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_tripday SET source_link_id=999 WHERE entity=1 AND fk_vehicle=1");
qxReject(static function () use ($trips, $tripLink, $tripDay, $trip) { $trips->saveDay($tripLink, array($trip), $tripDay, 'qws'); }, 'QxAssociationHistoryOverlap');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday SET source_link_id='.((int) $tripLink->rowid).' WHERE entity=1 AND fk_vehicle=1');

// Native worker: today's snapshot first, then open days and daily rereads; restarts skip committed days.
$cfg = $configuration->load(1);
$client = new QxTestClient($db, 1); $client->responses = array_fill(0, 12, qxResponse(array()));
qxCheck(!$trips->synchronize($client, $tripLink, $cfg, microtime(true)-1) && !$client->calls, 'Expired budget performs no request');
$trips->synchronize($client, $tripLink, $cfg, microtime(true)+45);
$today = LmdbVehicleQuartixTrips::reportingDay(dol_now(), $tripLink->timezone, $tripLink->shift_start);
qxCheck($client->calls[0]['values']['StartDay'] === $today && $client->calls[0]['values']['StartDay'] === $client->calls[0]['values']['EndDay'], 'Current day gets first single-day request');
$before = count($client->calls);
$trips->synchronize($client, $tripLink, $cfg, microtime(true)+45);
qxCheck(count($client->calls) === $before + 1, 'Replay skips current and recent days, progressively resumes one historical day');
$client->responses = array(qxResponse(null, 429));
$cfg['TRIP_RETENTION_DAYS'] = '60';
qxReject(static function () use ($trips, $client, $tripLink, $cfg) { $trips->synchronize($client, $tripLink, $cfg, microtime(true)+45); }, 'QxRateLimited');

// Dashboard aggregate correctness, all vehicles, missing data, scoped SQL and no GPS leakage.
require_once dirname(__DIR__).'/class/lmdbvehiclequartixdashboard.class.php';
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'cronjob (rowid integer PRIMARY KEY, entity integer, methodename text, status integer, classesname text, objectname text)');
$db->query("INSERT INTO ".MAIN_DB_PREFIX."cronjob VALUES (1,1,'trips',0,'/lmdbvehiclemanagement/class/lmdbvehiclequartixcron.class.php','LmdbVehicleQuartixCron')");
foreach (array(1, 3) as $fixtureId) $db->query("INSERT INTO ".MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_usage (entity,fk_vehicle,usage_day,has_data,trip_count,distance,date_sync) VALUES (1,".$fixtureId.",'2026-07-30',1,2,42.5,'2026-07-31')");
$fleet = new LmdbVehicleQuartixDashboard($db);
$report = $fleet->report('2026-07-01', '2026-07-31', '', '', array(), 1);
qxCheck($report['total'] === 3 && count($report['rows']) === 1 && $report['totals']->known_vehicles == 2 && $report['totals']->distance == 85, 'Fleet totals include filtered cohort beyond pagination and distinguish missing vehicles');
qxCheck(count($report['comparison']) === 2 && count($report['jobs']) === 1 && $report['jobs'][0]->status == 0, 'Charts ignore missing values; jobs remain entity-scoped');
$unlinked = $fleet->report('2026-07-01', '2026-07-31', '', 'unlinked');
qxCheck($unlinked['total'] === 1 && $unlinked['rows'][0]->rowid == 3 && $unlinked['totals']->distance == 42.5, 'Unlinked vehicles keep their historical usage');
$user->admin = 0;
$withoutGps = $fleet->report('2026-07-01', '2026-07-31');
qxCheck(!property_exists($withoutGps['rows'][0], 'event_date') && !property_exists($withoutGps['rows'][0], 'location'), 'Read-only dashboard query never selects GPS fields');
qxReject(static function () use ($fleet) { $fleet->report('2026-07-01', '2026-07-31', '', 'forged'); }, 'QxInvalidSettings');
$user->socid = 3;
qxReject(static function () use ($fleet) { $fleet->report('2026-07-01', '2026-07-31'); }, 'QxAccessDenied');
$user->socid = 0; $user->admin = 1;
qxCheck($fleet->report('2026-07-01', '2026-07-31', '', '', array(2))['total'] === 0, 'Forged entity filter cannot broaden scope');
$mc = new class { public function getEntity($element, $shared = 1, $object = null) { return '1,2'; } };
qxCheck($fleet->report('2026-07-01', '2026-07-31', '', '', array(2))['total'] === 1, 'Explicit shared scope enables second entity');
$mc = null;

// Retained journal provenance survives unlink; erroneous associations purge only QWS cache.
$link4 = $trips->link(4); $trip4 = $trip; $trip4['VehicleID'] = (int) $link4->remote_id;
$trips->saveDay($link4, array($trip4), $tripDay, 'qws');
$associationService->disassociate($user, 4, (int) $link4->rowid, 'reassignment');
qxCheck($trips->journal(4, $tripDay, $tripDay)['total'] === 1, 'Reassignment retains journal without a live association');
qxReject(static function () use ($associationService, $user, $catalog, $tripDay) { $associationService->associate($user, 4, 30, 'Europe/Paris', $catalog, strtotime($tripDay.'T15:00:00+02:00')); }, 'QxAssociationHistoryOverlap');
$associationService->associate($user, 4, 30, 'Europe/Paris', $catalog, dol_now());
$new4 = $trips->link(4);
$db->failPattern = 'DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday';
qxReject(static function () use ($associationService, $user, $new4) { $associationService->disassociate($user, 4, (int) $new4->rowid, 'error'); }, 'QxDatabaseError');
$db->failPattern = '';
qxCheck($trips->journal(4, $tripDay, $tripDay)['total'] === 1 && $trips->link(4) !== null, 'Purge failure restores journal and association');
$associationService->disassociate($user, 4, (int) $new4->rowid, 'error');
qxCheck($trips->journal(4, $tripDay, $tripDay)['total'] === 0 && $manual->fetch((int) $manual->id) > 0, 'Erroneous association deletes trip history and preserves real readings');
// Purge manifests from unlinked vehicles, with a strict batch bound.
for ($n = 0; $n < 102; $n++) {
	$oldDay = LmdbVehicleQuartixRules::day($tripDay)->modify('-'.(100+$n).' days')->format('Y-m-d');
	$db->query("INSERT INTO ".MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_tripday (entity,fk_vehicle,source_link_id,remote_id,trip_day,timezone,shift_start,synced_at) VALUES (1,4,999,30,'".$oldDay."','Europe/Paris','08:00','2026-01-01')");
}
$trips->purge(1, 30);
qxCheck((int) $db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday WHERE entity=1 AND fk_vehicle=4')->fetchColumn() === 2, 'Unlinked purge is bounded to 100 days per run');
$trips->purge(1, 30);
qxCheck((int) $db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday WHERE entity=1 AND fk_vehicle=4')->fetchColumn() === 0, 'Second purge resumes remaining expired days');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_job SET retry_at=NULL WHERE entity=1');
// Restore active open state to verify that the seven-day reread does not downgrade its priority.
$trip['InProgress'] = true; $trips->saveDay($tripLink, array($trip), $tripDay, 'qws');
$db->query('UPDATE '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_tripday SET synced_at='".$db->idate(dol_now()-1000)."' WHERE entity=1 AND fk_vehicle=1 AND trip_day='".$tripDay."'");
$client = new QxTestClient($db, 1); $client->responses = array_fill(0, 4, qxResponse(array()));
$trips->synchronize($client, $tripLink, $configuration->load(1), microtime(true)+45);
qxCheck($client->calls[0]['values']['StartDay'] === $tripDay, 'Recent open day is refreshed after 15 minutes even when synced today');

// Authorized vehicle deletion uses the same owner lock and atomic cache cleanup.
foreach (array('vehicle_assignment', 'vehicle_capacity', 'consumption', 'vehicle_event', 'insurance_contract_vehicle', 'insurance_certificate', 'vehicle_regulatory_profile', 'control_requirement', 'regulatory_control') as $table) {
	$db->query('CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_'.$table.' (rowid integer PRIMARY KEY, entity integer, fk_vehicle integer)');
}
$db->query("INSERT INTO ".MAIN_DB_PREFIX."lmdbvehiclemanagement_vehicle (rowid,entity,ref,label,fk_user_creat,date_creation) VALUES (7,1,'QX-DELETE','Delete cache fixture',1,'2026-01-01')");
$db->query("INSERT INTO ".MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_tripday (entity,fk_vehicle,source_link_id,remote_id,trip_day,timezone,shift_start,synced_at) VALUES (1,7,999,70,'".$tripDay."','Europe/Paris','00:00','2026-01-01')");
$deletable = new QxTestVehicle($db); $deletable->fetch(7); $deletable->has_document_storage = false;
$db->failPattern = 'DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle WHERE';
qxCheck($deletable->delete($user) < 0 && !$db->locked, 'Failed vehicle deletion releases the entity lock');
$db->failPattern = '';
qxCheck((int) $db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday WHERE entity=1 AND fk_vehicle=7')->fetchColumn() === 1, 'Failed vehicle deletion restores journal manifests');
qxCheck($deletable->delete($user) > 0 && !$db->locked && (int) $db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_tripday WHERE entity=1 AND fk_vehicle=7')->fetchColumn() === 0, 'Successful vehicle deletion removes journal and releases lock');
