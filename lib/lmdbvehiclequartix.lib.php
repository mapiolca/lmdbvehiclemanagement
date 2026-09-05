<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/../class/lmdbvehiclequartixservice.class.php';

/**
 * Cached GPS is only rendered on internal vehicle pages, with its own permission.
 * No map provider, image, external request, document hook or export is involved.
 * @param LmdbVehicle $vehicle Authorized vehicle @return void
 */
function lmdbVehicleQuartixPrintPosition($vehicle)
{
	global $db, $user, $langs;
	if (!LmdbVehicleQuartixConfig::supported() || !LmdbVehicleQuartixConfig::can($user, 'location')) return;
	$langs->loadLangs(array('agenda', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
	$service = new LmdbVehicleQuartixService($db);
	try {
		$link = $service->link((int) $vehicle->id);
		if ($link === null) return;
		$cfg = (new LmdbVehicleQuartixConfig($db))->load((int) $vehicle->entity);
		$position = $service->position((int) $vehicle->id);
		print load_fiche_titre($langs->trans('QxLastPosition'), '', '');
		print '<div class="div-table-responsive-no-min"><table class="border centpercent">';
		if ($position === null) {
			print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
		} else {
			$stale = dol_now() - (int) $db->jdate($position->fetched_at) > 1800 || dol_now() - (int) $db->jdate($position->event_date) > 1800;
			$state = !(int) $link->active || $cfg['ENABLED'] !== '1' ? 'QxPaused' : ((int) $position->non_tracking ? 'QxNonTracking' : ($stale ? 'QxStale' : 'QxTracking'));
			print '<tr><td class="titlefield">'.$langs->trans('Status').'</td><td>'.dolGetStatus($langs->trans($state), '', '', $state === 'QxTracking' ? 'status4' : 'status5', 5).'</td></tr>';
			print '<tr><td>'.$langs->trans('QxObservedAt').'</td><td>'.dol_print_date($db->jdate($position->event_date), 'dayhour').'</td></tr>';
			print '<tr><td>'.$langs->trans('QxFetchedAt').'</td><td>'.dol_print_date($db->jdate($position->fetched_at), 'dayhour').'</td></tr>';
			print '<tr><td>'.$langs->trans('Location').'</td><td>'.dol_escape_htmltag($position->location).'</td></tr>';
			print '<tr><td>'.$langs->trans('QxCoordinates').'</td><td>'.dol_escape_htmltag($position->latitude.', '.$position->longitude).'</td></tr>';
			if ($position->speed !== null) print '<tr><td>'.$langs->trans('QxSpeed').'</td><td>'.price($position->speed, 0, $langs, 1, -1, -1).' km/h</td></tr>';
		}
		print '</table></div>';
	} catch (Exception $e) {
		print '<div class="warning">'.$langs->trans('QxDataUnavailable').'</div>';
	}
}
