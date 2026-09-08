<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

// Synthetic geometry; Data{Summary,Trips} and field types verified live on 2026-09-06.
final class QxTestRoutes extends LmdbVehicleQuartixRoutes
{
	public $client;
	protected function createRouteClient($entity, $dayId) { return $this->client; }
}
$routes = new QxTestRoutes($db);
$user->admin = 1; $user->socid = 0; $conf->entity = 1;
$conf->global->LMDBVEHICLEMANAGEMENT_QX_ROUTES_ENABLED = '1';
$conf->global->LMDBVEHICLEMANAGEMENT_QX_TRIP_RETENTION_DAYS = '30';
$conf->global->LMDBVEHICLEMANAGEMENT_QX_ENABLED = '1';
$conf->global->LMDBVEHICLEMANAGEMENT_QX_TIME_MODE = 'qws';
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_job SET retry_at=NULL');

// Render the actual shared content: the dialog receives plain text, safely JSON encoded.
$routeTitleLangs = new Translate('', $conf);
$routeTitleLangs->setDefaultLang('fr_FR');
$routeTitleLangs->load('lmdbvehiclemanagement@lmdbvehiclemanagement');
$routeTitleHtml = (static function ($langs) {
	$routeInDialog = true; $dayId = 0; $key = '';
	$routeTitle = $langs->transnoentities('QxRouteView').' — Véhicule <A&B></script>';
	$cfg = array('TILE_URL' => LmdbVehicleQuartixConfig::TILE_URL, 'TILE_ATTRIBUTION' => LmdbVehicleQuartixConfig::TILE_ATTRIBUTION);
	ob_start(); include dirname(__DIR__).'/tpl/quartix_route.tpl.php'; return ob_get_clean();
})($routeTitleLangs);
preg_match('/<script type="application\/json" id="qx-route-options">([^<]+)<\/script>/', $routeTitleHtml, $routeOptionsMatch);
$routeRenderedOptions = json_decode($routeOptionsMatch[1] ?? '{}', true);
qxCheck(($routeRenderedOptions['title'] ?? '') === 'Voir le tracé — Véhicule <A&B></script>', 'Native route dialog title preserves accents and safely encodes special characters');
$db->query('UPDATE '.MAIN_DB_PREFIX.'cronjob SET status=1');
$trip['InProgress'] = false;
$trips->saveDay($tripLink, array($trip), $tripDay, 'qws');
$journal = $trips->journal(1, $tripDay, $tripDay);
$routeDayId = (int) $journal['rows'][0]->fk_tripday;
$routeKey = LmdbVehicleQuartixRoutes::tripKey($db->jdate($journal['rows'][0]->departure));
$rawRoute = $trip;
$rawRoute['Route'] = array(
	array('Latitude'=>48.1,'Longitude'=>2.1,'EventDateTime'=>$tripDay.'T12:00:00+02:00','SpeedData'=>array('private'=>'discard'), 'DriverID'=>500),
	array('Latitude'=>48.12,'Longitude'=>2.13,'EventDateTime'=>$tripDay.'T12:30:00+02:00'),
	array('Latitude'=>48.2,'Longitude'=>2.2,'EventDateTime'=>$tripDay.'T13:00:00+02:00'),
);
$routeReply = array(array('Summary'=>array('VehicleID'=>(int) $tripLink->remote_id), 'Trips'=>array($rawRoute)));
$normal = LmdbVehicleQuartixRoutes::normalize($routeReply, $tripLink, $tripDay, 'qws');
qxCheck(count($normal[$routeKey]['points']) === 3 && array_keys($normal[$routeKey]) === array('private','fingerprint','open_fingerprint','points'), 'Route decoder stores only geometry and synchronization metadata');
$routes->client = new QxTestClient($db, 1);
$routes->client->responses = array(qxResponse($routeReply[0]));
qxCheck($routes->view($routeDayId, $routeKey)['state'] === 'missing' && count($routes->client->calls) === 0, 'Opening cache view never queries QWS');
$routes->requestRoute($routeDayId, $routeKey);
$view = $routes->view($routeDayId, $routeKey);
qxCheck($view['state'] === 'ready' && count($view['points']) === 3 && !$db->locked, 'Owner click loads geometry and releases lock');
qxCheck($routes->client->calls[0]['values'] === array('VehicleID'=>(int) $tripLink->remote_id, 'StartDay'=>$tripDay), 'Route request is exactly one vehicle and reporting day');
$stored = $db->pdo->query('SELECT geometry FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route')->fetchColumn();
qxCheck(strpos($stored, 'dolcrypt:') === 0 && strpos($stored, '48.12') === false, 'Coordinates encrypted before SQL prevents their exposure in debug logs');
$oldTripId = $journal['rows'][0]->rowid;
$trips->saveDay($tripLink, array($trip), $tripDay, 'qws');
qxCheck($trips->journal(1,$tripDay,$tripDay)['rows'][0]->rowid !== $oldTripId && $routes->view($routeDayId,$routeKey)['state'] === 'ready', 'Same-day journal replay preserves a stable route despite recreated row IDs');
$routes->requestRoute($routeDayId,$routeKey);
qxCheck(count($routes->client->calls) === 1, 'Completed cached routes do not generate duplicate calls');

// Profiles and route-only client must not grant the general sync capability.
$savedRights = clone $user->rights;
$user->admin = 0;
$user->rights = (object) array('lmdbvehiclemanagement'=>(object) array('read'=>1,'quartix'=>(object) array('location'=>0,'sync'=>1)));
qxReject(static function () use ($routes,$routeDayId,$routeKey) { $routes->view($routeDayId,$routeKey); }, 'QxAccessDenied');
$user->rights->lmdbvehiclemanagement->quartix->location = 1;
$user->rights->lmdbvehiclemanagement->quartix->sync = 0;
$scoped = new QxTestClient($db,1,$routeDayId);
qxReject(static function () use ($db) { new QxTestClient($db,1); }, 'QxAccessDenied');
qxReject(static function () use ($scoped) { $scoped->get('/vehicles'); }, 'QxAccessDenied');
qxReject(static function () use ($scoped,$tripDay) { $scoped->get('/vehicles/route',array('VehicleID'=>999,'StartDay'=>$tripDay)); }, 'QxAccessDenied');
$scoped->responses = array(qxResponse($routeReply[0]));
qxCheck(count($scoped->get('/vehicles/route',array('VehicleID'=>(int)$tripLink->remote_id,'StartDay'=>$tripDay))) === 1, 'GPS reader has a day-scoped route client without sync permission');
$user->socid = 99; $user->admin = 1;
qxReject(static function () use ($routes,$routeDayId,$routeKey) { $routes->view($routeDayId,$routeKey); }, 'QxAccessDenied');
$user->socid = 0; $user->rights = $savedRights;
qxReject(static function () use ($routes,$routeDayId) { $routes->view($routeDayId,str_repeat('a',64)); }, 'QxRouteUnavailable');
$conf->global->LMDBVEHICLEMANAGEMENT_QX_ROUTES_ENABLED = '0';
qxReject(static function () use ($routes,$routeDayId,$routeKey) { $routes->view($routeDayId,$routeKey); }, 'QxRoutesDisabled');
$conf->global->LMDBVEHICLEMANAGEMENT_QX_ROUTES_ENABLED = '1';

// Open journeys invalidate stale geometry and allow refresh only after the minimum interval.
$trip['InProgress'] = true; $rawRoute['InProgress'] = false;
$routeReply[0]['Trips'] = array($rawRoute);
$trips->saveDay($tripLink,array($trip),$tripDay,'qws');
qxCheck($routes->view($routeDayId,$routeKey)['state'] === 'missing', 'Changed journal fingerprint revokes stale route');
$queueFilter = ' WHERE entity=1 AND fk_tripday='.$routeDayId;
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue SET last_attempt=NULL'.$queueFilter);
$routes->client->responses = array(qxResponse($routeReply[0]));
$routes->requestRoute($routeDayId,$routeKey);
qxCheck($routes->view($routeDayId,$routeKey)['in_progress'] && !$routes->view($routeDayId,$routeKey)['can_request'], 'Open route is provisional and throttled for fifteen minutes');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue SET last_attempt=NULL'.$queueFilter);
$beforeGeometry = $db->pdo->query('SELECT geometry FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route')->fetchColumn();
$routes->client->responses = array(qxResponse(null,500));
qxReject(static function () use ($routes,$routeDayId,$routeKey) { $routes->requestRoute($routeDayId,$routeKey); }, 'QxRemoteError');
qxCheck($routes->view($routeDayId,$routeKey)['state'] === 'ready' && $beforeGeometry === $db->pdo->query('SELECT geometry FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route')->fetchColumn(), 'Network error preserves the last complete cache');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue SET last_attempt=NULL'.$queueFilter);
$routes->client->responses = array(qxResponse($routeReply[0]));
$db->failPattern = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route (';
qxCheck($routes->processPending($routes->client,1,microtime(true)+45) === 'QxDatabaseError', 'Storage failure is reported by queue worker');
$db->failPattern = '';
qxCheck($beforeGeometry === $db->pdo->query('SELECT geometry FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route')->fetchColumn(), 'Transaction failure restores deleted geometry');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue SET last_attempt=NULL'.$queueFilter);
$routes->client->responses = array(qxResponse($routeReply[0]));
qxCheck($routes->processPending($routes->client,1,microtime(true)+45) === '' && !(int)$db->pdo->query('SELECT pending FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue'.$queueFilter)->fetchColumn(), 'Interrupted or failed work can be replayed and completes once');

// Private routes from either source fail closed and contain no detailed data at rest.
$privateRoute = $rawRoute; $privateRoute['IsPrivate'] = true; $privateRoute['Route'] = 'ignored-private-payload';
$privateReply = array(array('Summary'=>array('VehicleID'=>(int)$tripLink->remote_id),'Trips'=>array($privateRoute)));
$privateNormal = LmdbVehicleQuartixRoutes::normalize($privateReply,$tripLink,$tripDay,'qws');
qxCheck($privateNormal[$routeKey]['private'] && $privateNormal[$routeKey]['points'] === array() && $privateNormal[$routeKey]['fingerprint'] === '', 'Private response never normalizes precise coordinates or detailed metadata');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue SET last_attempt=NULL'.$queueFilter);
$privateReply[0]['Trips'][] = array('Route' => 'invalid unrelated trip');
$routes->client->responses = array(qxResponse($privateReply[0]));
qxReject(static function () use ($routes,$routeDayId,$routeKey) { $routes->requestRoute($routeDayId,$routeKey); }, 'QxRoutePrivate');
qxCheck($routes->view($routeDayId,$routeKey)['points'] === array() && (int)$db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route')->fetchColumn() === 0, 'Provider privacy revokes a former public cache immediately');
$trip['IsPrivate'] = true;
$trips->saveDay($tripLink,array($trip),$tripDay,'qws');
qxReject(static function () use ($routes,$routeDayId,$routeKey) { $routes->view($routeDayId,$routeKey); }, 'QxRouteUnavailable');
qxCheck((int)$db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue'.$queueFilter)->fetchColumn() === 0, 'Private journal snapshot cancels pending retrieval');

// Beneficiaries can read cached routes, but cannot request new traces or load secrets.
$trip['IsPrivate'] = false; $trip['InProgress'] = false;
$rawRoute['InProgress'] = false; $routeReply[0]['Trips'] = array($rawRoute);
$trips->saveDay($tripLink,array($trip),$tripDay,'qws');
$conf->entity = 2;
qxReject(static function () use ($routes,$routeDayId,$routeKey) { $routes->view($routeDayId,$routeKey); }, 'QxAccessDenied');
$mc = new class { public function getEntity($element,$shared=1,$object=null) { return '1,2'; } };
foreach (array('ROUTES_ENABLED'=>'1','ENABLED'=>'1','TRIP_RETENTION_DAYS'=>'30') as $k=>$v) $db->query("INSERT INTO ".MAIN_DB_PREFIX."const (entity,name,value) VALUES (1,'LMDBVEHICLEMANAGEMENT_QX_".$k."','".$v."')");
$calls = count($routes->client->calls);
qxReject(static function () use ($routes,$routeDayId,$routeKey) { $routes->requestRoute($routeDayId,$routeKey); }, 'QxAccessDenied');
qxCheck(count($routes->client->calls) === $calls && !$routes->view($routeDayId,$routeKey)['can_request'], 'Beneficiary cannot enqueue or request a route');
qxReject(static function () use ($db,$routeDayId) { new QxTestClient($db,1,$routeDayId); }, 'QxAccessDenied');
qxReject(static function () use ($routes) { $routes->processPending($routes->client,1,microtime(true)+45); }, 'QxAccessDenied');
$conf->entity = 1; $mc = null;
$routes->client->responses = array(qxResponse($routeReply[0]));
$routes->requestRoute($routeDayId,$routeKey);
qxCheck($routes->processPending($routes->client,1,microtime(true)+45) === '' && $routes->view($routeDayId,$routeKey)['state'] === 'ready', 'Owner alone requests and retrieves the route');
$lifecycle = new QxTestService($db);
$db->begin();
$lifecycle->disassociate($user,1,(int)$tripLink->rowid,'reassignment');
qxCheck($routes->view($routeDayId,$routeKey)['state'] === 'ready' && !$routes->view($routeDayId,$routeKey)['can_request'], 'Reassignment retains geometry but prevents use of a new association for old days');
qxCheck((int)$db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue'.$queueFilter)->fetchColumn() === 0, 'Dissociation cancels queued requests');
$db->rollback();
$db->begin();
$lifecycle->disassociate($user,1,(int)$tripLink->rowid,'error');
qxCheck((int)$db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route'.$queueFilter)->fetchColumn() === 0, 'Erroneous association removes geometry with the journal');
$db->rollback();
$db->begin();
$lifecycle->deleteTripCache(1,1);
qxCheck((int)$db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route'.$queueFilter)->fetchColumn() === 0 && (int)$db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue'.$queueFilter)->fetchColumn() === 0, 'Authorized vehicle cleanup removes geometry and requests');
$db->rollback();

// A deadline cannot cause an additional QWS call, including authentication.
$limited = new QxTestClient($db,1);
$limited->setDeadline(microtime(true)-1);
qxReject(static function () use ($limited,$tripLink,$tripDay) { $limited->get('/vehicles/route',array('VehicleID'=>(int)$tripLink->remote_id,'StartDay'=>$tripDay)); }, 'QxNetworkError');
qxCheck(count($limited->calls) === 0, 'Expired batch budget prevents transport');

$conf->global->LMDBVEHICLEMANAGEMENT_QX_ENABLED = '0';
qxCheck($routes->view($routeDayId,$routeKey)['state'] === 'ready' && !$routes->view($routeDayId,$routeKey)['can_request'], 'Suspension retains cache without further calls');
$conf->global->LMDBVEHICLEMANAGEMENT_QX_ENABLED = '1';
$db->query('UPDATE '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_tripday SET trip_day='2020-01-01' WHERE entity=1 AND rowid=".$routeDayId);
qxReject(static function () use ($routes,$routeDayId,$routeKey) { $routes->view($routeDayId,$routeKey); }, 'QxRouteExpired');
$conf->global->LMDBVEHICLEMANAGEMENT_QX_ENABLED = '0';
$trips->purge(1,30);
qxCheck((int)$db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_route'.$queueFilter)->fetchColumn() === 0 && (int)$db->pdo->query('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_routequeue'.$queueFilter)->fetchColumn() === 0, 'Retention purges geometry and queue even during global suspension');
$conf->global->LMDBVEHICLEMANAGEMENT_QX_ENABLED = '1';

foreach (array('https://tiles.test/{z}/{x}/{y}.png','https://tiles.test/{z}/{x}/{y}?style=roads') as $url) { LmdbVehicleQuartixConfig::validateTiles($url,'Provider'); qxCheck(true,'HTTPS tile template accepted'); }
foreach (array('http://tiles.test/{z}/{x}/{y}', 'https://login:secret@tiles.test/{z}/{x}/{y}', 'https://tiles.test/{vehicle}/{z}/{x}/{y}', 'https://tiles.test/{z}/{x}', 'javascript:alert(1)') as $url) qxReject(static function () use ($url) { LmdbVehicleQuartixConfig::validateTiles($url,'Provider'); },'QxInvalidTileSettings');
qxReject(static function () { LmdbVehicleQuartixConfig::validateTiles(LmdbVehicleQuartixConfig::TILE_URL,'<script>bad</script>'); },'QxInvalidTileSettings');
foreach (array('latitude','order','outside','missing') as $case) {
	$bad = $routeReply;
	if ($case === 'latitude') $bad[0]['Trips'][0]['Route'][1]['Latitude'] = 91;
	if ($case === 'order') $bad[0]['Trips'][0]['Route'][1]['EventDateTime'] = $tripDay.'T11:00:00+02:00';
	if ($case === 'outside') $bad[0]['Trips'][0]['Route'][2]['EventDateTime'] = $tripDay.'T14:00:00+02:00';
	if ($case === 'missing') unset($bad[0]['Trips'][0]['Route']);
	qxReject(static function () use ($bad,$tripLink,$tripDay) { LmdbVehicleQuartixRoutes::normalize($bad,$tripLink,$tripDay,'qws'); },'QxInvalidResponse');
}
$duplicate = $routeReply; $duplicate[0]['Trips'][] = $duplicate[0]['Trips'][0];
qxCheck(count(LmdbVehicleQuartixRoutes::normalize($duplicate,$tripLink,$tripDay,'qws')) === 1, 'Duplicate routes are discarded deterministically');
$overlapRoute = $rawRoute; $overlapRoute['StartDateTime'] = $previousDay.'T23:00:00+02:00';
$duplicate[0]['Trips'][] = $overlapRoute;
qxCheck(count(LmdbVehicleQuartixRoutes::normalize($duplicate,$tripLink,$tripDay,'qws')) === 1, 'Routes starting in the preceding reporting day are excluded');
qxCheck(LmdbVehicleQuartixRoutes::normalize(array(),$tripLink,$tripDay,'qws') === array(), 'Empty successful day is distinguished from invalid responses');
foreach (array(0,1) as $count) {
	$short = $routeReply; $short[0]['Trips'][0]['Route'] = array_slice($rawRoute['Route'],0,$count);
	qxCheck(count(LmdbVehicleQuartixRoutes::normalize($short,$tripLink,$tripDay,'qws')[$routeKey]['points']) === $count, 'Insufficient points remain insufficient: no invented straight route');
}
$privateDistanceRoute = $routeReply;
$privateDistanceRoute[0]['Trips'][0]['PrivacyDistance'] = 0.1;
qxCheck(LmdbVehicleQuartixRoutes::normalize($privateDistanceRoute,$tripLink,$tripDay,'qws')[$routeKey]['private'], 'Positive private distance prevents route exposure even with IsPrivate=false');
$quotaClient = new QxTestClient($db,1);
$quotaClient->responses = array(qxResponse(null,429));
qxReject(static function () use ($quotaClient,$tripLink,$tripDay) { $quotaClient->get('/vehicles/route',array('VehicleID'=>(int)$tripLink->remote_id,'StartDay'=>$tripDay)); },'QxRateLimited');
qxReject(static function () use ($quotaClient,$tripLink,$tripDay) { $quotaClient->get('/vehicles/route',array('VehicleID'=>(int)$tripLink->remote_id,'StartDay'=>$tripDay)); },'QxRateLimited');
qxCheck(count($quotaClient->calls) === 1,'Route quota prevents repeated HTTP requests across readers');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_job SET retry_at=NULL');
