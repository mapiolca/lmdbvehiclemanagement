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
if (!LmdbVehicleQuartixConfig::can($user, 'read') || !LmdbVehicleQuartixConfig::supported()) accessforbidden();
$id = GETPOSTINT('id');
$service = new LmdbVehicleQuartixService($db);
try {
	$object = $service->vehicle($id);
	$link = $service->link($id);
	$cfg = (new LmdbVehicleQuartixConfig($db))->load((int) $object->entity);
} catch (Exception $e) {
	accessforbidden($langs->trans('QxDataUnavailable'));
	exit;
}
$group = GETPOST('group', 'alpha') === 'month' ? 'month' : 'day';
$limit = max(1, min(1000, GETPOSTINT('limit') ?: (int) $conf->liste_limit));
$page = max(0, GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page'));
$sortfield = GETPOST('sortfield', 'aZ09') ?: 'period';
$sortorder = GETPOST('sortorder', 'alpha') === 'ASC' ? 'ASC' : 'DESC';
$dates = array();
$reset = GETPOST('button_removefilter', 'alpha');
foreach (array('start', 'end') as $key) {
	$day = GETPOSTINT($key.'day'); $month = GETPOSTINT($key.'month'); $year = GETPOSTINT($key.'year');
	$default = dol_print_date(dol_now(), $key === 'start' ? '%Y-%m-01' : '%Y-%m-%d');
	$dates[$key] = !$reset && ($day || $month || $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : $default;
}
if ($reset) { $page = 0; $group = 'day'; }
$arrayfields = array(
	'period' => array('label' => 'Period', 'checked' => 1),
	'known_days' => array('label' => 'QxCoverage', 'checked' => 1),
	'distance' => array('label' => 'QxDistance', 'checked' => 1),
	'trips' => array('label' => 'QxTrips', 'checked' => 1),
	'travel' => array('label' => 'QxDriving', 'checked' => 1),
	'idling' => array('label' => 'QxIdling', 'checked' => 1),
);
$contextpage = 'lmdbvehiclequartix';
$action = GETPOST('action', 'aZ09');
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';
if (!isset($arrayfields[$sortfield])) $sortfield = 'period';
$rows = $allRows = array();
$validPeriod = true;
try {
	$allRows = $service->usage($id, $dates['start'], $dates['end'], $group, 0, 0, 'period', 'ASC');
	if ($page * $limit >= count($allRows)) $page = 0;
	$rows = $service->usage($id, $dates['start'], $dates['end'], $group, $limit, $page * $limit, $sortfield, $sortorder);
} catch (Exception $e) {
	$validPeriod = false;
	setEventMessages($langs->trans(LmdbVehicleQuartixCron::safeError($e)), null, 'errors');
}
$form = new Form($db);
llxHeader('', $object->ref.' — '.$langs->trans('QxUsage'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');
print dol_get_fiche_head(lmdbVehiclePrepareHead($object), 'quartix', $langs->trans('Vehicle'), -1, $object->picto);
lmdbVehiclePrintBanner($object);
print '<p>'.$langs->trans('QxUsageHelp').'</p>';
if ($link === null) print '<div class="warning">'.$langs->trans('QxNotAssociated').'</div>';
elseif (!(int) $link->active || $cfg['ENABLED'] !== '1') print '<div class="warning">'.$langs->trans('QxPaused').'</div>';
else print '<p class="opacitymedium">'.$langs->trans('QxReportingZone', dol_escape_htmltag($link->timezone), dol_escape_htmltag($link->shift_start)).'</p>';
if ($link !== null && !empty($link->sync_from)) print '<p>'.$langs->trans('QxSyncFrom').': '.dol_print_date($db->jdate($link->sync_from), 'dayhour').'</p>';
if ($cfg['DURATION_UNIT'] === '') print '<div class="warning">'.$langs->trans('QxDurationUnconfirmed').'</div>';
if ($link !== null) print '<p>'.$langs->trans('QxBackfill').': '.(!empty($link->usage_cursor) ? dol_print_date($db->jdate($link->usage_cursor), 'day') : $langs->trans('QxPending')).'</p>';

$param = '&id='.$id.'&group='.$group.'&limit='.$limit;
foreach ($dates as $key => $dayValue) {
	if ($validPeriod) {
		$d = LmdbVehicleQuartixRules::day($dayValue);
		$param .= '&'.$key.'day='.$d->format('d').'&'.$key.'month='.$d->format('m').'&'.$key.'year='.$d->format('Y');
	}
}
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $contextpage, !empty($conf->main_checkbox_left_column));
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" name="qxusage"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'"><input type="hidden" name="sortorder" value="'.$sortorder.'">';
print_barre_liste($langs->trans('QxUsage'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', count($rows), count($allRows), 'car', 0, '', '', $limit);
print '<div class="liste_titre">'.$form->selectDate($validPeriod ? LmdbVehicleQuartixRules::day($dates['start'])->getTimestamp() : -1, 'start', 0, 0, 0, '', 1, 0, 0, '', '', '', '', 1, '', '', 'gmt').' — '.$form->selectDate($validPeriod ? LmdbVehicleQuartixRules::day($dates['end'])->getTimestamp() : -1, 'end', 0, 0, 0, '', 1, 0, 0, '', '', '', '', 1, '', '', 'gmt');
print ' '.$form->selectarray('group', array('day' => $langs->trans('QxDaily'), 'month' => $langs->trans('QxMonthly')), $group, 0, 0, 0, '', 0, 0, 0, '', '', 1);
print ' <button type="submit" class="button">'.$langs->trans('Search').'</button><button class="button" type="submit" name="button_removefilter" value="1">'.$langs->trans('Reset').'</button> '.$selectedfields.'</div>';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre">';
$visible = array_filter($arrayfields, static function ($field) { return !empty($field['checked']); });
foreach ($visible as $key => $field) print getTitleFieldOfList($field['label'], 0, $_SERVER['PHP_SELF'], $key, '', $param, '', $sortfield, $sortorder);
print '</tr>';
foreach ($rows as $row) {
	$periodDay = LmdbVehicleQuartixRules::day($row->period.($group === 'month' ? '-01' : ''));
	$first = max($dates['start'], $periodDay->format('Y-m-d'));
	$last = min($dates['end'], $group === 'month' ? $periodDay->format('Y-m-t') : $first);
	$expected = 1 + (int) LmdbVehicleQuartixRules::day($first)->diff(LmdbVehicleQuartixRules::day($last))->days;
	print '<tr class="oddeven">';
	foreach ($visible as $key => $field) {
		print '<td'.($key === 'period' ? '' : ' class="right"').'>';
		if ($key === 'period') print dol_print_date($periodDay->getTimestamp(), $group === 'month' ? '%B %Y' : 'day', 'gmt');
		elseif ($key === 'known_days') print ((int) $row->known_days).' / '.$expected;
		else {
			$value = $row->{$key} !== null ? (float) $row->{$key} : null;
			if ($key === 'travel' || $key === 'idling') $value = LmdbVehicleQuartixRules::hours($value, $cfg['DURATION_UNIT']);
			print $value === null ? '<span class="opacitymedium">—</span>' : price($value, 0, $langs, 1, -1, -1).(in_array($key, array('travel', 'idling'), true) ? ' h' : ($key === 'distance' ? ' km' : ''));
		}
		print '</td>';
	}
	print '</tr>';
}
if (!$rows) print '<tr class="oddeven"><td colspan="'.max(1, count($visible)).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</table></div></form>';
// jflot is a native v20+ backend: no graph file containing usage data is generated.
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
	$graph = new DolGraph('jflot');
	$graph->SetData($series['data']);
	$graph->SetLegend($series['labels']);
	$graph->SetType(array_fill(0, count($series['labels']), 'bars'));
	$graph->SetWidth('100%');
	$graph->SetHeight(260);
	$graph->draw('qxusage_'.$key.'_'.$id);
	print $graph->show();
}
lmdbVehicleQuartixPrintPosition($object);
print dol_get_fiche_end();
llxFooter();
$db->close();
