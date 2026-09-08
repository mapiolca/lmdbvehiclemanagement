<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/../class/lmdbvehiclequartixservice.class.php';

/**
 * Cached GPS is only rendered on internal vehicle pages, with its own permission.
 * No map provider, image, external request, document hook or export is involved.
 * @param LmdbVehicle|LmdbVehicleQuartix $vehicle Authorized vehicle or historical source
 * @param int $quartixId Source (0 resolves the local or sole authorized source) @return void
 */
function lmdbVehicleQuartixPrintPosition($vehicle, $quartixId = 0)
{
	global $db, $user, $langs;
	if (!LmdbVehicleQuartixConfig::supported() || !(isModEnabled('lmdbvehiclemanagement') && empty($user->socid) && $user->hasRight('lmdbvehiclemanagement', 'read') && $user->hasRight('lmdbvehiclemanagement', 'quartix', 'location'))) return;
	$langs->loadLangs(array('agenda', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
	$service = new LmdbVehicleQuartixService($db);
	try {
		$vehicleId = $vehicle instanceof LmdbVehicleQuartix ? (int) $vehicle->fk_vehicle : (int) $vehicle->id;
		$dataset = $service->dataset($vehicleId, $quartixId);
		$link = $service->link($vehicleId, (int) $dataset->id);
		$cfg = (new LmdbVehicleQuartixConfig($db))->load((int) $dataset->entity);
		$position = $service->position($vehicleId, (int) $dataset->id);
		print load_fiche_titre($langs->trans('QxLastPosition'), '', '');
		print '<div class="div-table-responsive-no-min"><table class="border centpercent">';
		if ($position === null) {
			print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
		} else {
			$stale = dol_now() - (int) $db->jdate($position->fetched_at) > 1800 || dol_now() - (int) $db->jdate($position->event_date) > 1800;
			$state = $link === null || !(int) $link->active || $cfg['ENABLED'] !== '1' ? 'QxPaused' : ((int) $position->non_tracking ? 'QxNonTracking' : ($stale ? 'QxStale' : 'QxTracking'));
			print '<tr><td class="titlefield">'.$langs->trans('Status').'</td><td>'.dolGetStatus($langs->trans($state), '', '', $state === 'QxTracking' ? 'status4' : 'status5', 5).'</td></tr>';
			print '<tr><td>'.$langs->trans('QxObservedAt').'</td><td>'.dol_print_date($db->jdate($position->event_date), 'dayhour').'</td></tr>';
			print '<tr><td>'.$langs->trans('QxFetchedAt').'</td><td>'.dol_print_date($db->jdate($position->fetched_at), 'dayhour').'</td></tr>';
			print '<tr><td>'.$langs->trans('Location').'</td><td>'.dol_escape_htmltag($position->location).'</td></tr>';
			print '<tr><td>'.$langs->trans('QxCoordinates').'</td><td>'.dol_escape_htmltag($position->latitude.', '.$position->longitude).'</td></tr>';
			if ($position->speed !== null) print '<tr><td>'.$langs->trans('QxSpeed').'</td><td>'.price($position->speed, 0, $langs, 1, -1, -1).' km/h</td></tr>';
		}
		print '</table></div>';
	} catch (Exception $e) {
		if (in_array($e->getMessage(), array('QxNoData', 'QxChooseSource'), true)) return;
		print '<div class="warning">'.$langs->trans('QxDataUnavailable').'</div>';
	}
}

/** Owner labels only for authorized sources, including an archived local source.
 * @param LmdbVehicleQuartixService $service @return array<int,string>
 */
function lmdbQuartixSourceEntities($service)
{
	global $conf, $langs;
	$rows = $service->rows('SELECT DISTINCT q.entity FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_dataset q WHERE '.LmdbVehicleSharing::sql($service->db, 'lmdbvehiclequartix', 'q'));
	$ids = array((int) $conf->entity);
	foreach ($rows as $row) $ids[] = (int) $row->entity;
	$options = array();
	if (isModEnabled('multicompany')) {
		foreach ($service->rows('SELECT rowid,label FROM '.MAIN_DB_PREFIX.'entity WHERE rowid IN ('.implode(',', array_unique($ids)).')') as $row) $options[(int) $row->rowid] = (string) $row->label;
	} else $options[(int) $conf->entity] = $langs->trans('MyCompany');
	return $options;
}

/** Select an authorized source before rendering or reading caches. @param LmdbVehicleQuartixService $service @param int $id Vehicle @return LmdbVehicleQuartix */
function lmdbQuartixPageSource($service, $id)
{
	global $langs, $db;
	header('Cache-Control: private, no-store');
	try { return $service->dataset($id, GETPOSTINT('quartix_id')); }
	catch (RuntimeException $e) {
		if (!in_array($e->getMessage(), array('QxChooseSource', 'QxNoData'), true)) accessforbidden($langs->trans('QxDataUnavailable'));
		llxHeader('', $langs->trans('QxTitle'));
		print load_fiche_titre($langs->trans($e->getMessage()), '', 'car');
		lmdbQuartixSourceSelector($service, $id, 0);
		print '<p><a href="'.dol_buildpath('/lmdbvehiclemanagement/quartix_dashboard.php', 1).'">'.$langs->trans('QxDashboard').'</a></p>';
		llxFooter(); $db->close(); exit;
	}
}

/** Native source selection, one dataset at a time. @param LmdbVehicleQuartixService $service @param int $id Vehicle @param int $selected Dataset @return void */
function lmdbQuartixSourceSelector($service, $id, $selected)
{
	global $langs;
	$options = array(); $entities = lmdbQuartixSourceEntities($service);
	foreach ($service->sources($id) as $source) $options[(int) $source->id] = $entities[(int) $source->entity] ?? $langs->trans('QxData');
	if (!$options) return;
	$form = new Form($service->db);
	print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'"><input type="hidden" name="id" value="'.$id.'">';
	if (GETPOST('view', 'alpha') === 'odometer') print '<input type="hidden" name="view" value="odometer">';
	foreach (array('startday', 'startmonth', 'startyear', 'endday', 'endmonth', 'endyear') as $name) {
		if (GETPOSTISSET($name)) print '<input type="hidden" name="'.$name.'" value="'.GETPOSTINT($name).'">';
	}
	foreach (array('group', 'search_status', 'sortfield', 'sortorder') as $name) {
		if (GETPOSTISSET($name)) print '<input type="hidden" name="'.$name.'" value="'.dol_escape_htmltag(GETPOST($name, 'aZ09')).'">';
	}
	print '<label for="quartix_id">'.$langs->trans('QxSource').'</label> '.$form->selectarray('quartix_id', $options, $selected, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200', 1);
	print ' <input type="submit" class="button" value="'.$langs->trans('Show').'"></form>';
}

/** Native vehicle card when accessible; historical identification only otherwise.
 * @param LmdbVehicleQuartixService $service @param LmdbVehicleQuartix $dataset @param LmdbVehicle|null $vehicle @param string $tab Active tab @return void
 */
function lmdbQuartixBanner($service, $dataset, $vehicle, $tab)
{
	global $langs, $user;
	$head = $vehicle !== null ? lmdbVehiclePrepareHead($vehicle) : array();
	if ($vehicle === null) {
		$head[] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_quartix.php', 1).'?id='.(int) $dataset->fk_vehicle, $langs->trans('QxUsage'), 'quartix');
		if ($user->hasRight('lmdbvehiclemanagement', 'quartix', 'location')) $head[] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_trips.php', 1).'?id='.(int) $dataset->fk_vehicle, $langs->trans('QxJournal'), 'trips');
	}
	foreach ($head as &$entry) if (in_array($entry[2], array('quartix', 'trips', 'odometer'), true)) $entry[0] .= '&quartix_id='.(int) $dataset->id;
	unset($entry);
	print dol_get_fiche_head($head, $tab, $langs->trans('QxData'), -1, 'car');
	$entities = lmdbQuartixSourceEntities($service);
	$sourceHtml = $langs->trans('QxSource').' <span class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entities[(int) $dataset->entity] ?? $langs->trans('QxData')).'</span></span>';
	if ($vehicle !== null) {
		lmdbVehiclePrintBanner($vehicle, $sourceHtml);
	} else {
		print load_fiche_titre(dol_escape_htmltag($dataset->snapshot_vehicle_label ?: $langs->trans('QxHistoricalVehicle')), '', 'car');
		print '<div class="refidno">'.$sourceHtml.'</div>';
		print '<div class="warning">'.$langs->trans('QxVehicleWithdrawn').'</div>';
	}
}
