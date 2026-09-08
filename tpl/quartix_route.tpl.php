<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/**
 * Shared route content for the journal dialog and direct consultation.
 * @var Translate $langs
 * @var array<string,string> $cfg Owner settings
 * @var bool $routeInDialog
 * @var string $routeTitle Plain dialog title
 * @var int $dayId
 * @var string $key
 */
if (!defined('DOL_DOCUMENT_ROOT')) exit;

print '<div id="'.($routeInDialog ? 'qx-route-dialog' : 'qx-route-content').'"'.($routeInDialog ? ' class="hidden"' : '').'>';
print '<div class="info">'.$langs->trans('QxRouteHelp').'</div>';
print '<form id="qx-route-form" method="POST" action="'.dol_escape_htmltag(dol_buildpath('/lmdbvehiclemanagement/vehicle_route.php', 1)).'">';
print '<input type="hidden" name="quartix_id" value="'.(int) ($quartixId ?? 0).'">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="retrieve"><input type="hidden" name="day" value="'.((int) $dayId).'"><input type="hidden" name="trip" value="'.dol_escape_htmltag($key).'">';
print '<div id="qx-route-status" class="info hidden" role="status" aria-live="polite"></div><p id="qx-route-fetched" class="opacitymedium"></p>';
print '<div id="qx-route-provisional" class="warning hidden">'.$langs->trans('QxRouteProvisional').'</div>';
print '<div id="qx-route-map" class="hidden" role="region" aria-label="'.dol_escape_htmltag($langs->trans('QxRouteTitle')).'"></div>';
print '<div id="qx-route-actions"'.($routeInDialog ? ' class="hidden"' : '').'><button id="qx-route-load" class="button" type="submit">'.$langs->trans('QxRouteLoad').'</button></div>';
print '<noscript><div class="warning">'.$langs->trans('QxRouteRequiresJavascript').'</div></noscript></form>';
print '</div>';
$routeOptions = array('title' => $routeTitle, 'tiles' => $cfg['TILE_URL'], 'attribution' => $cfg['TILE_ATTRIBUTION'],
	'start' => $langs->transnoentities('QxDeparture'), 'end' => $langs->transnoentities('QxArrival'),
	'loading' => $langs->transnoentities('QxRouteLoading'), 'failure' => $langs->transnoentities('QxRouteDisplayError'), 'tilesFailure' => $langs->transnoentities('QxRouteTilesError'));
print '<script type="application/json" id="qx-route-options">'.json_encode($routeOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).'</script>';
