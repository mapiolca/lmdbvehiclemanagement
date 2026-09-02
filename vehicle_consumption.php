<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumption.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumptionstats.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('main', 'currencies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !lmdbVehicleManagementCanDo($user, 'read') || !empty($user->socid)) accessforbidden();
$id = GETPOSTINT('id');
$vehicle = new LmdbVehicle($db);
if ($id <= 0 || $vehicle->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));

$statsService = new LmdbVehicleConsumptionStats($db);
$rows = $statsService->fetchRows(array('vehicle_id' => $id));
if (!is_array($rows)) {
	setEventMessages($statsService->error, null, 'errors');
	$rows = array();
}
$groups = $statsService->summarize($rows);

llxHeader('', $vehicle->ref.' - '.$langs->trans('Consumption'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');
$head = lmdbVehiclePrepareHead($vehicle);
print dol_get_fiche_head($head, 'consumption', $langs->trans('Vehicle'), -1, $vehicle->picto);
lmdbVehiclePrintBanner($vehicle);
if (lmdbVehicleManagementCanDo($user, 'consumption', 'write')) {
	print '<div class="tabsAction">'.dolGetButtonAction('', $langs->trans('NewConsumption'), 'default', dol_buildpath('/lmdbvehiclemanagement/consumption_card.php', 1).'?action=create&vehicle_id='.$id).'</div>';
}

print load_fiche_titre($langs->trans('ConsumptionSummary'), '', 'chart-line');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Consumable').'</th><th class="right">'.$langs->trans('Entries').'</th><th class="right">'.$langs->trans('AverageQuantity').'</th><th class="right">'.$langs->trans('AverageFrequency').'</th><th class="right">'.$langs->trans('AverageDistance').'</th><th class="right">'.$langs->trans('AverageConsumption100').'</th><th class="right">'.$langs->trans('WeightedUnitPrice').'</th><th class="right">'.$langs->trans('TotalQuantity').'</th><th class="right">'.$langs->trans('TotalCost').'</th><th class="right">'.$langs->trans('RecoveredCapacity').'</th><th class="right">'.$langs->trans('WltpPassageRatio').'</th><th>'.$langs->trans('LastConsumptionEntry').'</th><th class="right">'.$langs->trans('PeakQuantity').'</th><th class="right">'.$langs->trans('PeakUnitPrice').'</th><th class="right">'.$langs->trans('PeakConsumption100').'</th><th class="right">'.$langs->trans('ExcludedIntervals').'</th></tr>';
foreach ($groups as $group) {
	$unit = LmdbVehicleConsumable::unitLabel((string) $group['unit']);
	print '<tr class="oddeven"><td>'.dol_escape_htmltag((string) $group['consumable_label']).'</td><td class="right">'.((int) $group['count']).'</td>';
	print '<td class="right">'.price($group['average_quantity']).' '.dol_escape_htmltag($unit).'</td>';
	print '<td class="right">'.($group['average_days'] !== null ? price($group['average_days']).' '.$langs->trans('Days') : '').'</td>';
	print '<td class="right">'.($group['average_distance'] !== null ? price($group['average_distance']).' '.$langs->trans('UnitKm') : '').'</td>';
	print '<td class="right">'.($group['consumption_100'] !== null ? price($group['consumption_100']).' '.dol_escape_htmltag($unit).'/100 km' : '').'</td>';
	print '<td class="right">'.price($group['weighted_unit_price']).' '.dol_escape_htmltag((string) $group['currency']).'/'.dol_escape_htmltag($unit).'</td>';
	print '<td class="right">'.price($group['total_quantity']).' '.dol_escape_htmltag($unit).'</td><td class="right">'.price($group['total_cost']).' '.dol_escape_htmltag((string) $group['currency']).'</td>';
	print '<td class="right">'.($group['average_capacity_percent'] !== null ? price($group['average_capacity_percent']).' %' : '').'</td>';
	print '<td class="right">'.($group['wltp_passage_ratio'] !== null ? price($group['wltp_passage_ratio']).' %' : '').'</td>';
	print '<td>'.($group['last_date'] !== null ? dol_print_date((int) $group['last_date'], 'dayhour') : '').'</td>';
	print '<td class="right">'.price($group['peak_quantity']).' '.dol_escape_htmltag($unit).'</td>';
	print '<td class="right">'.price($group['peak_unit_price']).' '.dol_escape_htmltag((string) $group['currency']).'/'.dol_escape_htmltag($unit).'</td>';
	print '<td class="right">'.($group['peak_consumption_100'] !== null ? price($group['peak_consumption_100']).' '.dol_escape_htmltag($unit).'/100 km' : '').'</td>';
	print '<td class="right">'.((int) $group['excluded_intervals']).'</td></tr>';
}
if (empty($groups)) print '<tr class="oddeven"><td colspan="16"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</table></div>';

foreach ($groups as $key => $group) {
	$series = array_values(array_filter($rows, static function ($row) use ($group) {
		return (int) $row['entity'] === (int) $group['entity'] && (int) $row['consumable_id'] === (int) $group['consumable_id'] && (string) $row['unit'] === (string) $group['unit'] && (string) $row['currency'] === (string) $group['currency'];
	}));
	print '<div class="fichecenter"><div class="fichehalfleft">'.lmdbVehicleConsumptionRenderGraph($series, (string) $group['category'] === 'fuel' ? 'consumption_100' : 'quantity', $langs->trans((string) $group['category'] === 'fuel' ? 'AverageConsumption100' : 'Quantity').' - '.(string) $group['consumable_label'], 'vehicle-'.$id.'-'.$key.'-main').'</div>';
	print '<div class="fichehalfright">'.lmdbVehicleConsumptionRenderGraph($series, 'unit_price', $langs->trans('UnitPrice').' - '.(string) $group['consumable_label'], 'vehicle-'.$id.'-'.$key.'-price').'</div></div><div class="clearboth"></div>';
	if ((string) $group['category'] === 'fuel') {
		print '<div class="fichecenter"><div class="fichehalfleft">'.lmdbVehicleConsumptionRenderGraph($series, 'quantity', $langs->trans('Quantity').' - '.(string) $group['consumable_label'], 'vehicle-'.$id.'-'.$key.'-quantity').'</div>';
		print '<div class="fichehalfright">'.lmdbVehicleConsumptionRenderGraph($series, 'capacity_percent', $langs->trans('RecoveredCapacity').' - '.(string) $group['consumable_label'], 'vehicle-'.$id.'-'.$key.'-capacity').'</div></div><div class="clearboth"></div>';
	}
}

foreach (array('fuel' => 'FuelsAndRecharges', 'additive' => 'Additives') as $category => $label) {
	print load_fiche_titre($langs->trans($label), '', $category === 'fuel' ? 'gas-pump' : 'flask');
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('Consumable').'</th><th class="right">'.$langs->trans('Quantity').'</th><th class="right">'.$langs->trans('OdometerKm').'</th><th class="right">'.$langs->trans('TotalTTC').'</th><th>'.$langs->trans('OilReference').'</th></tr>';
	$count = 0;
	foreach (array_reverse($rows) as $row) {
		if ((string) $row['category'] !== $category) continue;
		$entry = new LmdbVehicleConsumption($db);
		$entry->id = (int) $row['id']; $entry->ref = (string) $row['ref'];
		print '<tr class="oddeven"><td>'.$entry->getNomUrl(1).'</td><td>'.dol_print_date((int) $row['date'], 'dayhour').'</td><td>'.dol_escape_htmltag((string) $row['consumable_label']).'</td><td class="right">'.price($row['quantity']).' '.dol_escape_htmltag(LmdbVehicleConsumable::unitLabel((string) $row['unit'])).'</td><td class="right">'.price($row['odometer_km']).' '.$langs->trans('UnitKm').'</td><td class="right">'.price($row['total_ttc']).' '.dol_escape_htmltag((string) $row['currency']).'</td><td>'.dol_escape_htmltag((string) $row['oil_reference']).'</td></tr>';
		$count++;
	}
	if ($count === 0) print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	print '</table></div>';
}
print dol_get_fiche_end();
llxFooter();
$db->close();
