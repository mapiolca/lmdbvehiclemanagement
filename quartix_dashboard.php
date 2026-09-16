<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once __DIR__.'/class/lmdbvehiclequartixdashboard.class.php';
require_once __DIR__.'/class/lmdbvehiclequartixcron.class.php';
require_once __DIR__.'/lib/lmdbvehiclemanagement.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */
$langs->loadLangs(array('other', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!(isModEnabled('lmdbvehiclemanagement') && empty($user->socid) && $user->hasRight('lmdbvehiclemanagement', 'read')) || !LmdbVehicleQuartixConfig::supported()) accessforbidden();
$service = new LmdbVehicleQuartixDashboard($db);
$form = new Form($db); $object = new LmdbVehicle($db);
$hookmanager->initHooks(array('lmdbvehiclequartixdashboard'));
$gps = (isModEnabled('lmdbvehiclemanagement') && empty($user->socid) && $user->hasRight('lmdbvehiclemanagement', 'read') && $user->hasRight('lmdbvehiclemanagement', 'quartix', 'location'));
require_once __DIR__.'/lib/lmdbvehiclequartix.lib.php';
header('Cache-Control: private, no-store');
$entityOptions = lmdbQuartixSourceEntities(new LmdbVehicleQuartixService($db));
$sourceEntity = GETPOSTINT('source_entity') ?: (int) $conf->entity;
if (!isset($entityOptions[$sourceEntity])) accessforbidden();
$entities = array($sourceEntity);
$search = GETPOST('search_vehicle', 'alphanohtml');
$association = GETPOST('search_association', 'alpha');
$limit = max(1, min(1000, GETPOSTINT('limit') ?: (int) $conf->liste_limit));
$page = max(0, GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page'));
$sortfield = GETPOST('sortfield', 'aZ09') ?: 'vehicle';
$sortorder = strtoupper(GETPOST('sortorder', 'alpha')) === 'DESC' ? 'DESC' : 'ASC';
$reset = GETPOSTISSET('button_removefilter_x') || GETPOSTISSET('button_removefilter');
if ($reset || GETPOSTISSET('button_search_x') || GETPOSTISSET('button_search')) $page = 0;
if ($reset) { $search = $association = ''; $entities = array((int) $conf->entity); }
$dates = array();
foreach (array('start', 'end') as $key) {
	$day = GETPOSTINT($key.'day'); $month = GETPOSTINT($key.'month'); $year = GETPOSTINT($key.'year');
	$default = (new DateTimeImmutable('@'.dol_now()))->modify($key === 'start' ? '-30 days' : '-1 day')->format('Y-m-d');
	$dates[$key] = !$reset && ($day || $month || $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : $default;
}
$arrayfields = array(
	'vehicle' => array('label' => 'Vehicle', 'checked' => 1, 'align' => 'left'),
	'association' => array('label' => 'QxAssociationState', 'checked' => 1, 'align' => 'center'),
	'distance' => array('label' => 'QxDistance', 'checked' => 1, 'align' => 'right'),
	'trips' => array('label' => 'QxTrips', 'checked' => 1, 'align' => 'right'),
	'active' => array('label' => 'QxActiveDays', 'checked' => 1, 'align' => 'right'),
	'coverage' => array('label' => 'QxCoverage', 'checked' => 1, 'align' => 'center'),
);
if ($entityOptions) $arrayfields['entity'] = array('label' => 'Environment', 'checked' => 1, 'align' => 'center');
if ($gps) $arrayfields['position'] = array('label' => 'QxLastPosition', 'checked' => 1, 'align' => 'left');
$action = GETPOST('action', 'aZ09'); $contextpage = 'lmdbvehiclequartixdashboard';
$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';
if (!isset($arrayfields[$sortfield])) $sortfield = 'vehicle';
$result = null; $valid = true;
try {
	$result = $service->report($dates['start'], $dates['end'], $search, $association, $entities, $limit, $page * $limit, $sortfield, $sortorder);
	if ($page && $page * $limit >= $result['total']) {
		$page = 0; $result = $service->report($dates['start'], $dates['end'], $search, $association, $entities, $limit, 0, $sortfield, $sortorder);
	}
} catch (Exception $e) { $valid = false; setEventMessages($langs->trans(LmdbVehicleQuartixCron::safeError($e)), null, 'errors'); }
llxHeader('', $langs->trans('QxDashboardTitle'));
print load_fiche_titre($langs->trans('QxDashboardTitle'), '', 'car');
print '<p>'.$langs->trans('QxDashboardHelp').'</p>';
$param = '&limit='.$limit.'&search_vehicle='.urlencode($search).'&search_association='.urlencode($association);
$param .= '&source_entity='.(int) $entities[0];
foreach ($dates as $key => $value) if ($valid) {
	$d = LmdbVehicleQuartixRules::day($value); $param .= '&'.$key.'day='.$d->format('d').'&'.$key.'month='.$d->format('m').'&'.$key.'year='.$d->format('Y');
}
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $contextpage, !empty($conf->main_checkbox_left_column));
$actionsLeft = !empty($conf->main_checkbox_left_column);
print '<form method="POST" id="searchFormList" action="'.$_SERVER['PHP_SELF'].'" name="qxfleet"><input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list"><input type="hidden" name="action" value="list"><input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'"><input type="hidden" name="sortorder" value="'.$sortorder.'">';
// The native navigation needs a count above limit when another page exists.
$num = min($limit + 1, max(0, ($result['total'] ?? 0) - $page * $limit));
print_barre_liste($langs->trans('QxDashboard'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $result['total'] ?? 0, 'car', 0, '', '', $limit);
print '<div class="liste_titre liste_titre_bydiv centpercent">';
foreach ($dates as $key => $value) print '<div class="divsearchfield">'.$langs->trans($key === 'start' ? 'From' : 'To').' '.$form->selectDate($valid ? LmdbVehicleQuartixRules::day($value)->getTimestamp() : -1, $key, 0, 0, 0, '', 1, 0, 0, '', '', '', '', 1, '', '', 'gmt').'</div>';
print '</div>';
$visible = array_filter($arrayfields, static function ($field) { return !empty($field['checked']); });
// Hidden columns must not silently remove an active SQL filter on the next POST.
if (!isset($visible['vehicle'])) print '<input type="hidden" name="search_vehicle" value="'.dol_escape_htmltag($search).'">';
if (!isset($visible['association'])) print '<input type="hidden" name="search_association" value="'.dol_escape_htmltag($association).'">';
if (!isset($visible['entity'])) print '<input type="hidden" name="source_entity" value="'.(int) $entities[0].'">';
$states = array('associated' => 'QxAssociated', 'suspended' => 'QxSuspended', 'unlinked' => 'QxUnassociated');
print '<div class="div-table-responsive-no-min"><table class="tagtable liste listwithfilterbefore" id="quartix-fleet-list"><thead><tr class="liste_titre_filter">';
if ($actionsLeft) print '<td class="liste_titre center maxwidthsearch actioncolumn">'.$form->showFilterButtons('left').'</td>';
foreach ($visible as $key => $field) {
	print '<td class="'.($field['align'] ?? 'left').'">';
	if ($key === 'vehicle') print '<input class="flat maxwidth150" name="search_vehicle" value="'.dol_escape_htmltag($search).'" aria-label="'.dol_escape_htmltag($langs->trans('Vehicle')).'">';
	elseif ($key === 'association') {
		$options = array('' => $langs->trans('All')); foreach ($states as $state => $label) $options[$state] = $langs->trans($label);
		print $form->selectarray('search_association', $options, $association, 0, 0, 0, '', 0, 0, 0, '', '', 1);
	} elseif ($key === 'entity') print $form->selectarray('source_entity', $entityOptions, $entities[0], 0, 0, 0, '', 0, 0, 0, '', 'minwidth150', 1);
	print '</td>';
}
if (!$actionsLeft) print '<td class="liste_titre center maxwidthsearch actioncolumn">'.$form->showFilterButtons().'</td>';
print '</tr><tr class="liste_titre">';
if ($actionsLeft) print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch actioncolumn ');
foreach ($visible as $key => $field) print getTitleFieldOfList($field['label'], 0, $_SERVER['PHP_SELF'], $key, '', $param, 'data-col="'.$key.'"', $sortfield, $sortorder, ($field['align'] ?? 'left').' ');
if (!$actionsLeft) print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch actioncolumn ');
print '</tr></thead><tbody>';
foreach ($result['rows'] ?? array() as $row) {
	$vehicle = new LmdbVehicleQuartix($db); $vehicle->id = (int) $row->quartix_id; $vehicle->fk_vehicle = (int) $row->rowid; $vehicle->snapshot_vehicle_label = $row->ref; $vehicle->entity = (int) $row->entity;
	print '<tr class="oddeven">';
	if ($actionsLeft) print '<td class="center actioncolumn"></td>';
	foreach ($visible as $key => $field) {
		print '<td class="'.($field['align'] ?? 'left').'" data-col="'.$key.'">';
		if ($key === 'vehicle') {
			print $vehicle->getNomUrl(1).' '.dol_escape_htmltag($row->label);
			if ($gps) print ' <a href="'.dol_buildpath('/lmdbvehiclemanagement/vehicle_trips.php', 1).'?id='.$vehicle->fk_vehicle.'&amp;quartix_id='.$vehicle->id.'">'.$langs->trans('QxJournal').'</a>';
		} elseif ($key === 'association') print dolGetStatus($langs->trans($states[$row->association]), '', '', $row->association === 'associated' ? 'status4' : 'status5', 5);
		elseif ($key === 'entity') print lmdbVehicleManagementEntityBadge((int) $row->entity, $entityOptions);
		elseif ($key === 'coverage') print ((int) $row->known_days).' / '.$result['expected'];
		elseif ($key === 'position' && $gps) {
			if ($row->event_date !== null) {
				$stale = dol_now() - $db->jdate($row->event_date) > 1800 || dol_now() - $db->jdate($row->fetched_at) > 1800;
				print dolGetStatus($langs->trans($stale ? 'QxStale' : 'QxRecentPosition'), '', '', $stale ? 'status5' : 'status4', 5);
				print '<br>'.dol_print_date($db->jdate($row->event_date), 'dayhour').'<br>'.dol_escape_htmltag((string) $row->location);
			} else print '<span class="opacitymedium">'.$langs->trans('QxDataUnavailable').'</span>';
		} elseif (in_array($key, array('distance', 'trips', 'active'), true)) {
			$column = $key === 'active' ? 'active_days' : $key;
			print $row->{$column} === null ? '<span class="opacitymedium">—</span>' : ($key === 'distance' ? price($row->{$column}, 0, $langs, 1, -1, -1) : (string) (int) $row->{$column});
		}
		print '</td>';
	}
	if (!$actionsLeft) print '<td class="center actioncolumn"></td>';
	print '</tr>';
}
if (empty($result['rows'])) print '<tr class="oddeven"><td colspan="'.(1 + count($visible)).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</tbody></table></div></form>';
if ($result !== null) {
	print load_fiche_titre($langs->trans('QxFleetTotals'), '', '');
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre">';
	foreach (array('QxKnownVehicles' => 'center', 'QxDistance' => 'right', 'QxTrips' => 'right', 'QxActiveVehicleDays' => 'right', 'QxCoverage' => 'center') as $label => $align) print '<th class="wrapcolumntitle '.$align.'">'.$langs->trans($label).'</th>';
	print '</tr><tr class="oddeven"><td class="center">'.((int) $result['totals']->known_vehicles).' / '.$result['total'].'</td>';
	foreach (array('distance', 'trips', 'active_days') as $key) print '<td class="right">'.($result['totals']->{$key} === null ? '—' : ($key === 'distance' ? price($result['totals']->{$key}, 0, $langs, 1, -1, -1) : (string) (int) $result['totals']->{$key})).'</td>';
	print '<td class="center">'.((int) $result['totals']->known_days).' / '.($result['expected'] * $result['total']).'</td></tr></table></div>';
	foreach (array('daily' => 'QxDailyFleetDistance', 'comparison' => 'QxVehicleComparison') as $kind => $title) {
		$data = array();
		foreach ($result[$kind] as $row) if ($row->distance !== null) $data[] = array($kind === 'daily' ? dol_print_date(LmdbVehicleQuartixRules::day($row->usage_day)->getTimestamp(), 'day', 'gmt') : $row->ref, (float) $row->distance);
		if (!$data) continue;
		print load_fiche_titre($langs->trans($title), '', '');
		$graph = new DolGraph(); $graph->SetData($data); $graph->SetLegend(array($langs->trans('QxDistance'))); $graph->SetType(array('bars')); $graph->SetWidth('100%'); $graph->SetHeight(260); $graph->draw('qxfleet_'.$kind); print $graph->show();
	}
	print load_fiche_titre($langs->trans('QxEntitySync'), '', '');
	print '<p>'.$langs->trans('QxEntitySyncHelp').'</p>';
	if ($gps) print '<p class="opacitymedium">'.$langs->trans('QxPositionAgeHelp').'</p>';
	if (!isModEnabled('cron')) print '<div class="warning">'.$langs->trans('QxTripsPurgeStopped').'</div>';
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre">';
	if ($entityOptions) print '<td>'.$langs->trans('Environment').'</td>';
	foreach (array('Label', 'Status', 'QxLastAttempt', 'QxLastSuccess', 'Error') as $label) print '<td>'.$langs->trans($label).'</td>';
	print '</tr>';
	$jobLabels = array('positions' => 'QxPositionJob', 'odometer' => 'QxOdometerJob', 'usage' => 'QxUsageJob', 'trips' => 'QxTripsJob');
	$tripJobEntities = array();
	foreach ($result['jobs'] as $job) {
		if ($job->methodename === 'trips') $tripJobEntities[(int) $job->entity] = true;
		print '<tr class="oddeven">';
		if ($entityOptions) print '<td class="center">'.lmdbVehicleManagementEntityBadge((int) $job->entity, $entityOptions).'</td>';
		print '<td>'.$langs->trans($jobLabels[$job->methodename]).'</td><td>'.dolGetStatus($langs->trans((int) $job->status ? 'Enabled' : 'Disabled'), '', '', (int) $job->status ? 'status4' : 'status5', 5);
		if ($job->methodename === 'trips' && !(int) $job->status) print '<br>'.$langs->trans('QxTripsPurgeStopped');
		print '</td><td>'.dol_print_date($db->jdate($job->last_attempt), 'dayhour').'</td><td>'.dol_print_date($db->jdate($job->last_success), 'dayhour').'</td><td>'.($job->last_error ? dol_escape_htmltag($langs->trans(LmdbVehicleQuartixCron::safeError(new RuntimeException($job->last_error)))) : '').'</td></tr>';
	}
	if (!$result['jobs']) print '<tr class="oddeven"><td colspan="'.($entityOptions ? 6 : 5).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	print '</table></div>';
	foreach ($result['rows'] as $row) {
		if (isset($tripJobEntities[(int) $row->entity])) continue;
		print '<div class="warning">'.($entityOptions ? lmdbVehicleManagementEntityBadge((int) $row->entity, $entityOptions).' ' : '').$langs->trans('QxTripsPurgeStopped').'</div>';
		$tripJobEntities[(int) $row->entity] = true;
	}
}
llxFooter(); $db->close();
