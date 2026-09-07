<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

define('CSRFCHECK_WITH_TOKEN', 1);
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once __DIR__.'/class/lmdbvehiclequartixroutes.class.php';
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */
$langs->loadLangs(array('other', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
header('Cache-Control: private, no-store');
header('Referrer-Policy: strict-origin');
header('X-Content-Type-Options: nosniff');
if (!LmdbVehicleQuartixConfig::can($user, 'location')) accessforbidden();
$dayId = GETPOSTINT('day');
$key = (string) GETPOST('trip', 'aZ09');
$action = GETPOST('action', 'aZ09');
$json = GETPOST('format', 'alpha') === 'json';
$routes = new LmdbVehicleQuartixRoutes($db);
$state = null; $error = '';
try {
	$day = $routes->day($dayId);
	// Always authorize the individual public trip before displaying even the modal shell.
	$state = $routes->view($dayId, $key);
	if ($action === 'retrieve') {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') accessforbidden();
		$routes->requestRoute($dayId, $key);
		if (!$json) { header('Location: '.$_SERVER['PHP_SELF'].'?day='.$dayId.'&trip='.$key); exit; }
		$state = $routes->view($dayId, $key);
	}
} catch (Exception $e) { $error = LmdbVehicleQuartixRoutes::safeError($e); }
if ($json) {
	header('Content-Type: application/json; charset=utf-8');
	if ($error !== '') {
		// Re-read an authorized last good cache after network failure; access/expiry never falls back.
		try { $state = $routes->view($dayId, $key); } catch (Exception $ignored) { $state = null; }
		if ($state === null) http_response_code(403);
	}
	if ($state !== null) {
		$state['message'] = $langs->transnoentities($error ?: $state['message']);
		$state['fetched_label'] = $state['fetched_at'] ? $langs->transnoentities('QxRouteFetched', dol_print_date($state['fetched_at'], 'dayhour')) : '';
	}
	print json_encode(array('data' => $state, 'error' => $error === '' ? '' : $langs->transnoentities($error)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	$db->close(); exit;
}
if ($error !== '') accessforbidden($langs->trans($error));
$cfg = (new LmdbVehicleQuartixConfig($db))->load((int) $day->entity);
$vehicle = $routes->vehicle((int) $day->fk_vehicle, 'location');
llxHeader('', $langs->trans('QxRouteTitle'), '', '', 0, 0,
	array('/includes/leaflet/leaflet.js', '/lmdbvehiclemanagement/js/quartix_route.js'),
	array('/includes/leaflet/leaflet.css', '/lmdbvehiclemanagement/css/quartix_route.css'));
print load_fiche_titre($langs->trans('QxRouteTitle').' — '.dol_escape_htmltag($vehicle->ref));
print '<p>'.$langs->trans('QxRouteHelp').'</p>';
print '<form id="qx-route-form" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="retrieve"><input type="hidden" name="day" value="'.$dayId.'"><input type="hidden" name="trip" value="'.dol_escape_htmltag($key).'">';
print '<p id="qx-route-status" role="status" aria-live="polite"></p><p id="qx-route-fetched" class="opacitymedium"></p>';
print '<p id="qx-route-provisional" class="warning hidden">'.$langs->trans('QxRouteProvisional').'</p>';
print '<div id="qx-route-map" class="hidden" role="region" aria-label="'.dol_escape_htmltag($langs->trans('QxRouteTitle')).'"></div>';
print '<p><button id="qx-route-load" class="button" type="submit">'.$langs->trans('QxRouteLoad').'</button></p>';
print '<noscript><p>'.$langs->trans('QxRouteRequiresJavascript').'</p></noscript></form>';
$options = array('tiles' => $cfg['TILE_URL'], 'attribution' => $cfg['TILE_ATTRIBUTION'], 'start' => $langs->transnoentities('QxDeparture'), 'end' => $langs->transnoentities('QxArrival'),
	'loading' => $langs->transnoentities('QxRouteLoading'), 'failure' => $langs->transnoentities('QxRouteDisplayError'), 'tilesFailure' => $langs->transnoentities('QxRouteTilesError'));
print '<script type="application/json" id="qx-route-options">'.json_encode($options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).'</script>';
llxFooter(); $db->close();
