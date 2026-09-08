<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once __DIR__.'/class/lmdbvehiclequartixcron.class.php';
require_once __DIR__.'/lib/lmdbvehiclemanagement.lib.php';
require_once __DIR__.'/lib/lmdbvehiclequartix.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */
$langs->loadLangs(array('other', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!(isModEnabled('lmdbvehiclemanagement') && empty($user->socid) && $user->hasRight('lmdbvehiclemanagement', 'read')) || !LmdbVehicleQuartixConfig::supported()) accessforbidden();
$id = GETPOSTINT('id');
$service = new LmdbVehicleQuartixService($db);
$dataset = lmdbQuartixPageSource($service, $id);
$quartixId = (int) $dataset->id;
$object = null;
if (LmdbVehicleSharing::visible($db, 'lmdbvehicle', $id)) $object = $service->vehicle($id);
$link = $service->link($id, $quartixId);
$cfg = (new LmdbVehicleQuartixConfig($db))->load((int) $dataset->entity);
$retention = LmdbVehicleQuartixTrips::retention($cfg['TRIP_RETENTION_DAYS']);
require_once __DIR__.'/lib/lmdbvehiclesharing.lib.php';
lmdbSharingAction($dataset);
$cleanupAction = GETPOST('action', 'aZ09');
if ($cleanupAction === 'confirm_qx_cleanup') {
	$token = GETPOST('token', 'alphanohtml');
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $token === '' || empty($_SESSION['token']) || !hash_equals((string) $_SESSION['token'], $token)
		|| empty($user->admin) || (int) $dataset->entity !== (int) $conf->entity || $link !== null) accessforbidden();
	if (GETPOST('confirm', 'alpha') === 'yes') {
		$locked = false;
		try {
			if (!$service->lock((int) $conf->entity)) throw new RuntimeException('QxBusy');
			$locked = true;
			$service->disassociate($user, $id, 0, 'error');
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		} catch (Exception $e) { setEventMessages($langs->trans(LmdbVehicleQuartixCron::safeError($e)), null, 'errors'); }
		finally { if ($locked) $service->unlock((int) $conf->entity); }
	}
	header('Location: '.lmdbSharingObjectUrl($dataset)); exit;
}

$group = GETPOST('group', 'alpha') === 'month' ? 'month' : 'day';
$limit = max(1, min(1000, GETPOSTINT('limit') ?: (int) $conf->liste_limit));
$page = max(0, GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page'));
$sortfield = GETPOST('sortfield', 'aZ09') ?: 'period';
$sortorder = strtoupper(GETPOST('sortorder', 'alpha')) === 'ASC' ? 'ASC' : 'DESC';
$dates = array();
$reset = GETPOSTISSET('button_removefilter_x') || GETPOSTISSET('button_removefilter');
if ($reset || GETPOSTISSET('button_search_x') || GETPOSTISSET('button_search')) $page = 0;
foreach (array('start', 'end') as $key) {
	$day = GETPOSTINT($key.'day'); $month = GETPOSTINT($key.'month'); $year = GETPOSTINT($key.'year');
	$default = dol_print_date(dol_now(), $key === 'start' ? '%Y-%m-01' : '%Y-%m-%d');
	$dates[$key] = !$reset && ($day || $month || $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : $default;
}
if ($reset) { $page = 0; $group = 'day'; }
$arrayfields = array(
	'period' => array('label' => 'Period', 'checked' => 1, 'align' => 'center'),
	'known_days' => array('label' => 'QxCoverage', 'checked' => 1, 'align' => 'center'),
	'distance' => array('label' => 'QxDistance', 'checked' => 1, 'align' => 'right'),
	'trips' => array('label' => 'QxTrips', 'checked' => 1, 'align' => 'right'),
	'travel' => array('label' => 'QxDriving', 'checked' => 1, 'align' => 'right'),
	'idling' => array('label' => 'QxIdling', 'checked' => 1, 'align' => 'right'),
);
$contextpage = 'lmdbvehiclequartix';
$action = GETPOST('action', 'aZ09');
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';
if (!isset($arrayfields[$sortfield])) $sortfield = 'period';
$rows = $allRows = array();
$validPeriod = true;
try {
	$allRows = $service->usage($id, $dates['start'], $dates['end'], $group, 0, 0, 'period', 'ASC', $quartixId);
	if ($page * $limit >= count($allRows)) $page = 0;
	$rows = $service->usage($id, $dates['start'], $dates['end'], $group, $limit, $page * $limit, $sortfield, $sortorder, $quartixId);
} catch (Exception $e) {
	$validPeriod = false;
	setEventMessages($langs->trans(LmdbVehicleQuartixCron::safeError($e)), null, 'errors');
}
$form = new Form($db);
llxHeader('', $dataset->snapshot_vehicle_label.' — '.$langs->trans('QxUsage'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');
lmdbQuartixBanner($service, $dataset, $object, 'quartix');
if ($link === null && !empty($user->admin) && (int) $dataset->entity === (int) $conf->entity) {
	if ($cleanupAction === 'qx_cleanup') print $form->formconfirm(lmdbSharingObjectUrl($dataset), $langs->trans('QxCleanup'), $langs->trans('QxCleanupConfirm'), 'confirm_qx_cleanup', '', 0, 1);
	print '<div class="tabsAction">'.dolGetButtonAction('', $langs->trans('QxCleanup'), 'default', lmdbSharingObjectUrl($dataset).'&action=qx_cleanup&token='.newToken()).'</div>';
}
$mileageView = GETPOST('view', 'alpha') === 'odometer';
print '<p><a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&amp;quartix_id='.$quartixId.($mileageView ? '' : '&amp;view=odometer').'">'.$langs->trans($mileageView ? 'QxUsage' : 'QxOdometerHistory').'</a></p>';
if ($mileageView) {
	include __DIR__.'/tpl/quartix_odometer.tpl.php';
	print dol_get_fiche_end(); llxFooter(); $db->close(); exit;
}
print '<p>'.$langs->trans('QxUsageHelp').'</p>';
if ($link === null) print '<div class="warning">'.$langs->trans('QxNotAssociated').'</div>';
elseif (!(int) $link->active || $cfg['ENABLED'] !== '1') print '<div class="warning">'.$langs->trans('QxPaused').'</div>';
else print '<p class="opacitymedium">'.$langs->trans('QxReportingZone', dol_escape_htmltag($link->timezone), dol_escape_htmltag($link->shift_start)).'</p>';
if ($link !== null && !empty($link->sync_from)) print '<p>'.$langs->trans('QxSyncFrom').': '.dol_print_date($db->jdate($link->sync_from), 'dayhour').'</p>';
if ($cfg['DURATION_UNIT'] === '') print '<div class="warning">'.$langs->trans('QxDurationUnconfirmed').'</div>';
if ($link !== null) print '<p>'.$langs->trans('QxBackfill').': '.(!empty($link->usage_cursor) ? dol_print_date($db->jdate($link->usage_cursor), 'day') : $langs->trans('QxPending')).'</p>';

$param = '&id='.$id.'&quartix_id='.$quartixId.'&group='.$group.'&limit='.$limit;
foreach ($dates as $key => $dayValue) {
	if ($validPeriod) {
		$d = LmdbVehicleQuartixRules::day($dayValue);
		$param .= '&'.$key.'day='.$d->format('d').'&'.$key.'month='.$d->format('m').'&'.$key.'year='.$d->format('Y');
	}
}
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $contextpage, !empty($conf->main_checkbox_left_column));
$actionsLeft = !empty($conf->main_checkbox_left_column);
print '<form method="POST" id="searchFormList" action="'.$_SERVER['PHP_SELF'].'" name="qxusage"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'">';
print '<input type="hidden" name="quartix_id" value="'.$quartixId.'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list"><input type="hidden" name="action" value="list"><input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'"><input type="hidden" name="sortorder" value="'.$sortorder.'">';
// The native navigation needs a count above limit when another page exists.
$num = min($limit + 1, max(0, count($allRows) - $page * $limit));
print_barre_liste($langs->trans('QxUsage'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, count($allRows), 'car', 0, '', '', $limit);
print '<div class="liste_titre liste_titre_bydiv centpercent">';
foreach ($dates as $key => $value) print '<div class="divsearchfield">'.$langs->trans($key === 'start' ? 'From' : 'To').' '.$form->selectDate($validPeriod ? LmdbVehicleQuartixRules::day($value)->getTimestamp() : -1, $key, 0, 0, 0, '', 1, 0, 0, '', '', '', '', 1, '', '', 'gmt').'</div>';
print '<div class="divsearchfield">'.$form->selectarray('group', array('day' => $langs->trans('QxDaily'), 'month' => $langs->trans('QxMonthly')), $group, 0, 0, 0, '', 0, 0, 0, '', '', 1).'</div></div>';
$visible = array_filter($arrayfields, static function ($field) { return !empty($field['checked']); });
print '<div class="div-table-responsive-no-min"><table class="tagtable liste listwithfilterbefore" id="quartix-usage-list"><thead><tr class="liste_titre_filter">';
if ($actionsLeft) print '<td class="liste_titre center maxwidthsearch actioncolumn">'.$form->showFilterButtons('left').'</td>';
foreach ($visible as $field) print '<td class="'.$field['align'].'"></td>';
if (!$actionsLeft) print '<td class="liste_titre center maxwidthsearch actioncolumn">'.$form->showFilterButtons().'</td>';
print '</tr><tr class="liste_titre">';
if ($actionsLeft) print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch actioncolumn ');
foreach ($visible as $key => $field) print getTitleFieldOfList($field['label'], 0, $_SERVER['PHP_SELF'], $key, '', $param, 'data-col="'.$key.'"', $sortfield, $sortorder, $field['align'].' ');
if (!$actionsLeft) print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch actioncolumn ');
print '</tr></thead><tbody>';
foreach ($rows as $row) {
	$periodDay = LmdbVehicleQuartixRules::day($row->period.($group === 'month' ? '-01' : ''));
	$first = max($dates['start'], $periodDay->format('Y-m-d'));
	$last = min($dates['end'], $group === 'month' ? $periodDay->format('Y-m-t') : $first);
	$expected = 1 + (int) LmdbVehicleQuartixRules::day($first)->diff(LmdbVehicleQuartixRules::day($last))->days;
	print '<tr class="oddeven">';
	if ($actionsLeft) print '<td class="center actioncolumn"></td>';
	foreach ($visible as $key => $field) {
		print '<td class="'.$field['align'].'" data-col="'.$key.'">';
		if ($key === 'period') print dol_print_date($periodDay->getTimestamp(), $group === 'month' ? '%B %Y' : 'day', 'gmt');
		elseif ($key === 'known_days') print ((int) $row->known_days).' / '.$expected;
		elseif ($key === 'trips') print $row->trips === null ? '<span class="opacitymedium">—</span>' : (string) (int) $row->trips;
		else {
			$value = $row->{$key} !== null ? (float) $row->{$key} : null;
			if ($key === 'travel' || $key === 'idling') $value = LmdbVehicleQuartixRules::hours($value, $cfg['DURATION_UNIT']);
			print $value === null ? '<span class="opacitymedium">—</span>' : price($value, 0, $langs, 1, -1, -1).(in_array($key, array('travel', 'idling'), true) ? ' h' : ($key === 'distance' ? ' km' : ''));
		}
		print '</td>';
	}
	if (!$actionsLeft) print '<td class="center actioncolumn"></td>';
	print '</tr>';
}
if (!$rows) print '<tr class="oddeven"><td colspan="'.(1 + count($visible)).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</tbody></table></div></form>';
// Use the native backend selected by Dolibarr so it matches the scripts in llxHeader().
$chartSeries = array(
	'distance' => array('labels' => array($langs->trans('QxDistance')), 'data' => array()),
	'duration' => array('labels' => array($langs->trans('QxDriving'), $langs->trans('QxIdling')), 'data' => array()),
);
foreach ($allRows as $row) {
	$label = dol_print_date(LmdbVehicleQuartixRules::day($row->period.($group === 'month' ? '-01' : ''))->getTimestamp(), $group === 'month' ? '%b %Y' : 'day', 'gmt');
	if ($row->distance !== null) $chartSeries['distance']['data'][] = array($label, (float) $row->distance);
	if ($row->travel !== null && $row->idling !== null && in_array($cfg['DURATION_UNIT'], array('seconds', 'minutes', 'hours'), true)) {
		$chartSeries['duration']['data'][] = array($label, LmdbVehicleQuartixRules::hours((float) $row->travel, $cfg['DURATION_UNIT']), LmdbVehicleQuartixRules::hours((float) $row->idling, $cfg['DURATION_UNIT']));
	}
}
foreach ($chartSeries as $key => $series) {
	if (!$series['data']) continue;
	$graph = new DolGraph();
	$graph->SetData($series['data']);
	$graph->SetLegend($series['labels']);
	$graph->SetType(array_fill(0, count($series['labels']), 'bars'));
	$graph->SetWidth('100%');
	$graph->SetHeight(260);
	$graph->draw('qxusage_'.$key.'_'.$id);
	print $graph->show();
}
lmdbVehicleQuartixPrintPosition($dataset, $quartixId);
lmdbSharingRender($dataset);
print dol_get_fiche_end();
llxFooter();
$db->close();
