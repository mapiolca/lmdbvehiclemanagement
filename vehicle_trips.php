<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once __DIR__.'/class/lmdbvehiclequartixcron.class.php';
require_once __DIR__.'/lib/lmdbvehiclemanagement.lib.php';

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */
$langs->loadLangs(array('other', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!LmdbVehicleQuartixConfig::supported() || !LmdbVehicleQuartixConfig::can($user, 'location')) accessforbidden();
$id = GETPOSTINT('id');
$service = new LmdbVehicleQuartixTrips($db);
try {
	$object = $service->vehicle($id, 'location');
	$link = $service->link($id);
	$cfg = (new LmdbVehicleQuartixConfig($db))->load((int) $object->entity);
	$retention = LmdbVehicleQuartixTrips::retention($cfg['TRIP_RETENTION_DAYS']);
} catch (Exception $e) { accessforbidden($langs->trans('QxDataUnavailable')); exit; }
$hookmanager->initHooks(array('lmdbvehicletripslist'));
$action = GETPOST('action', 'aZ09');
$limit = max(1, min(1000, GETPOSTINT('limit') ?: (int) $conf->liste_limit));
$page = max(0, GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page'));
$sortfield = GETPOST('sortfield', 'aZ09') ?: 'departure';
$sortorder = GETPOST('sortorder', 'alpha') === 'ASC' ? 'ASC' : 'DESC';
$status = GETPOST('search_status', 'alpha');
$reset = GETPOST('button_removefilter', 'alpha');
if ($reset || GETPOST('button_search', 'alpha')) $page = 0;
if ($reset) $status = '';
$today = $link ? LmdbVehicleQuartixTrips::reportingDay(dol_now(), $link->timezone, $link->shift_start) : gmdate('Y-m-d', dol_now());
$dates = array();
foreach (array('start', 'end') as $key) {
	$day = GETPOSTINT($key.'day'); $month = GETPOSTINT($key.'month'); $year = GETPOSTINT($key.'year');
	$default = $key === 'start' ? LmdbVehicleQuartixRules::day($today)->modify('-6 days')->format('Y-m-d') : $today;
	$dates[$key] = !$reset && ($day || $month || $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : $default;
}
$arrayfields = array(
	'day' => array('label' => 'Date', 'checked' => 1),
	'departure' => array('label' => 'QxDeparture', 'checked' => 1),
	'arrival' => array('label' => 'QxArrival', 'checked' => 1),
	'from' => array('label' => 'QxStartLocation', 'checked' => 1),
	'to' => array('label' => 'QxEndLocation', 'checked' => 1),
	'distance' => array('label' => 'QxDistance', 'checked' => 1),
	'private_distance' => array('label' => 'QxPrivateDistance', 'checked' => 0),
	'status' => array('label' => 'Status', 'checked' => 1),
);
if ($cfg['DURATION_UNIT'] !== '') {
	$arrayfields['travel'] = array('label' => 'QxDriving', 'checked' => 0);
	$arrayfields['idling'] = array('label' => 'QxIdling', 'checked' => 0);
}
$contextpage = 'lmdbvehicletrips';
$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';
if (!isset($arrayfields[$sortfield])) $sortfield = 'departure';
$result = array('rows' => array(), 'total' => 0, 'days' => array()); $valid = true;
try {
	$result = $service->journal($id, $dates['start'], $dates['end'], $status, $limit, $page * $limit, $sortfield, $sortorder);
	if ($page && $page * $limit >= $result['total']) {
		$page = 0; $result = $service->journal($id, $dates['start'], $dates['end'], $status, $limit, 0, $sortfield, $sortorder);
	}
} catch (Exception $e) { $valid = false; setEventMessages($langs->trans(LmdbVehicleQuartixCron::safeError($e)), null, 'errors'); }
$form = new Form($db);
llxHeader('', $object->ref.' — '.$langs->trans('QxJournal'));
print dol_get_fiche_head(lmdbVehiclePrepareHead($object), 'trips', $langs->trans('Vehicle'), -1, $object->picto);
lmdbVehiclePrintBanner($object);
print '<p>'.$langs->trans('QxJournalHelp').'</p><p class="opacitymedium">'.$langs->trans('QxJournalRetention', $retention).'</p>';
if ($link === null) print '<div class="warning">'.$langs->trans('QxNotAssociated').'</div>';
elseif (!(int) $link->active || $cfg['ENABLED'] !== '1') print '<div class="warning">'.$langs->trans('QxPaused').'</div>';
if ($link) print '<p>'.$langs->trans('QxReportingZone', dol_escape_htmltag($link->timezone), dol_escape_htmltag($link->shift_start)).'</p>';
if ($cfg['DURATION_UNIT'] === '') print '<p class="opacitymedium">'.$langs->trans('QxDurationUnconfirmed').'</p>';
if ($valid) {
	$expected = $result['start'] > $result['end'] ? 0 : 1 + (int) LmdbVehicleQuartixRules::day($result['start'])->diff(LmdbVehicleQuartixRules::day($result['end']))->days;
	print '<p>'.$langs->trans('QxJournalCoverage', count($result['days']), $expected).'</p>';
}
$param = '&id='.$id.'&limit='.$limit.'&search_status='.urlencode($status);
foreach ($dates as $key => $value) if ($valid) {
	$d = LmdbVehicleQuartixRules::day($value); $param .= '&'.$key.'day='.$d->format('d').'&'.$key.'month='.$d->format('m').'&'.$key.'year='.$d->format('Y');
}
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $contextpage, !empty($conf->main_checkbox_left_column));
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" name="qxtrips"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'"><input type="hidden" name="sortorder" value="'.$sortorder.'">';
print_barre_liste($langs->trans('QxJournal'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', count($result['rows']), $result['total'], 'car', 0, '', '', $limit);
print '<div class="liste_titre">';
foreach ($dates as $key => $value) print $form->selectDate($valid ? LmdbVehicleQuartixRules::day($value)->getTimestamp() : -1, $key, 0, 0, 0, '', 1, 0, 0, '', '', '', '', 1, '', '', 'gmt').' ';
print $form->selectarray('search_status', array('' => $langs->trans('All'), 'open' => $langs->trans('QxTripOpen'), 'done' => $langs->trans('QxTripDone'), 'private' => $langs->trans('QxTripPrivate')), $status, 0, 0, 0, '', 0, 0, 0, '', '', 1);
print ' <button class="button" name="button_search" value="1">'.$langs->trans('Search').'</button><button class="button" name="button_removefilter" value="1">'.$langs->trans('Reset').'</button> '.$selectedfields.'</div>';
$visible = array_filter($arrayfields, static function ($field) { return !empty($field['checked']); });
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre">';
foreach ($visible as $key => $field) print getTitleFieldOfList($field['label'], 0, $_SERVER['PHP_SELF'], $key, '', $param, '', $sortfield, $sortorder);
print '</tr>';
foreach ($result['rows'] as $row) {
	print '<tr class="oddeven">';
	foreach ($visible as $key => $field) {
		print '<td>';
		if ($key === 'day') print dol_print_date(LmdbVehicleQuartixRules::day($row->trip_day)->getTimestamp(), 'day', 'gmt');
		elseif ($key === 'status') print dolGetStatus($langs->trans($row->is_private ? 'QxTripPrivate' : ($row->in_progress ? 'QxTripOpen' : 'QxTripDone')), '', '', $row->is_private ? 'status5' : ($row->in_progress ? 'status1' : 'status4'), 5);
		elseif ($key === 'departure' || $key === 'arrival') print $row->{$key} !== null ? dol_print_date($db->jdate($row->{$key}), 'dayhour') : '<span class="opacitymedium">—</span>';
		elseif ($key === 'from' || $key === 'to') print dol_escape_htmltag((string) ($key === 'from' ? $row->start_location : $row->end_location));
		elseif (in_array($key, array('distance', 'private_distance', 'travel', 'idling'), true)) {
			$column = $key === 'travel' ? 'travel_time' : ($key === 'idling' ? 'idling_time' : $key);
			$value = $row->{$column} === null ? null : (float) $row->{$column};
			if ($key === 'travel' || $key === 'idling') $value = LmdbVehicleQuartixRules::hours($value, $cfg['DURATION_UNIT']);
			print $value === null ? '<span class="opacitymedium">—</span>' : price($value, 0, $langs, 1, -1, -1);
		}
		print '</td>';
	}
	print '</tr>';
}
if (!$result['rows']) print '<tr class="oddeven"><td colspan="'.max(1, count($visible)).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</table></div></form>';
print dol_get_fiche_end(); llxFooter(); $db->close();
