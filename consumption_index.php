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

/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('main', 'currencies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();
$vehicleId = GETPOSTINT('vehicle_id');
$driverId = GETPOSTINT('driver_id');
$consumableId = GETPOSTINT('consumable_id');
$category = GETPOST('category', 'alpha');
$dateStartDay = GETPOSTINT('date_startday');
$dateStartMonth = GETPOSTINT('date_startmonth');
$dateStartYear = GETPOSTINT('date_startyear');
$dateEndDay = GETPOSTINT('date_endday');
$dateEndMonth = GETPOSTINT('date_endmonth');
$dateEndYear = GETPOSTINT('date_endyear');
$entityIds = GETPOSTISARRAY('entity_ids') ? GETPOST('entity_ids', 'array:int') : array();
if (GETPOST('button_removefilter', 'alpha')) {
	$vehicleId = $driverId = $consumableId = 0;
	$category = '';
	$dateStartDay = $dateStartMonth = $dateStartYear = 0;
	$dateEndDay = $dateEndMonth = $dateEndYear = 0;
	$entityIds = array();
}
$dateStart = $dateStartDay > 0 && $dateStartMonth > 0 && $dateStartYear > 0 ? dol_mktime(0, 0, 0, $dateStartMonth, $dateStartDay, $dateStartYear) : 0;
$dateEnd = $dateEndDay > 0 && $dateEndMonth > 0 && $dateEndYear > 0 ? dol_mktime(23, 59, 59, $dateEndMonth, $dateEndDay, $dateEndYear) : 0;
$form = new Form($db);
$dictionary = new LmdbVehicleConsumable($db);
$vehicleOptions = array();
$resql = $db->query('SELECT rowid, ref, registration_number, label FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle WHERE entity IN ('.getEntity('lmdbvehicle').') ORDER BY ref');
if ($resql) {
	while (is_object($row = $db->fetch_object($resql))) $vehicleOptions[(int) $row->rowid] = lmdbVehicleDisplayIdentifier((string) $row->ref, (string) $row->registration_number, (string) $row->label);
	$db->free($resql);
}
$entityScope = getEntity('lmdbvehicleconsumption');
$allowedEntities = array_values(array_filter(array_map('intval', explode(',', $entityScope))));
$entityOptions = array();
if (isModEnabled('multicompany') && count($allowedEntities) > 1) {
	$resql = $db->query('SELECT rowid, label FROM '.MAIN_DB_PREFIX.'entity WHERE rowid IN ('.$entityScope.') ORDER BY label');
	if ($resql) {
		while (is_object($row = $db->fetch_object($resql))) $entityOptions[(int) $row->rowid] = (string) $row->label;
		$db->free($resql);
	}
}
$safeEntities = array_values(array_intersect($allowedEntities, array_map('intval', $entityIds)));
$invalidEntityFilter = !empty($entityIds) && empty($safeEntities);
$service = new LmdbVehicleConsumptionStats($db);
$rows = $invalidEntityFilter ? array() : $service->fetchRows(array('vehicle_id' => $vehicleId, 'user_id' => $driverId, 'consumable_id' => $consumableId, 'category' => $category, 'date_start' => $dateStart, 'date_end' => $dateEnd, 'entity_ids' => $safeEntities));
if (!is_array($rows)) { setEventMessages($service->error, null, 'errors'); $rows = array(); }
$groups = $service->summarize($rows);

llxHeader('', $langs->trans('ConsumptionSummary'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-list');
print load_fiche_titre($langs->trans('ConsumptionSummary'), '', 'chart-line');
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'"><div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Period').'</th><th>'.$langs->trans('Vehicle').'</th><th>'.$langs->trans('Driver').'</th><th>'.$langs->trans('Consumable').'</th><th>'.$langs->trans('ConsumptionNature').'</th>';
if (!empty($entityOptions)) print '<th>'.$langs->trans('Environment').'</th>';
print '<th></th></tr><tr class="oddeven"><td>'.$form->selectDate($dateStart, 'date_start', 0, 0, 1).' '.$form->selectDate($dateEnd, 'date_end', 0, 0, 1).'</td>';
print '<td>'.$form->selectarray('vehicle_id', $vehicleOptions, $vehicleId, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth250', 1).'</td>';
print '<td>'.$form->select_dolusers($driverId ?: '', 'driver_id', 1, null, 0, '', '', '', 0, 1, '', 0, '', 'maxwidth200', 0, 0, false, 1).'</td>';
print '<td>'.$form->selectarray('consumable_id', $dictionary->getOptions(), $consumableId, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth200', 1).'</td>';
print '<td>'.$form->selectarray('category', array('fuel' => $langs->trans('FuelOrRecharge'), 'additive' => $langs->trans('Additive')), $category, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth200', 1).'</td>';
if (!empty($entityOptions)) print '<td>'.$form->multiselectarray('entity_ids', $entityOptions, $safeEntities, 0, 0, 'maxwidth200', 1).'</td>';
print '<td class="center nowraponall">'.$form->showFilterButtons().'</td></tr></table></div></form>';

print '<div class="tabsAction">';
print dolGetButtonAction('', $langs->trans('ConsumptionList'), 'default', dol_buildpath('/lmdbvehiclemanagement/consumption_list.php', 1));
if ($user->hasRight('lmdbvehiclemanagement', 'consumption', 'write')) print dolGetButtonAction('', $langs->trans('NewConsumption'), 'default', dol_buildpath('/lmdbvehiclemanagement/consumption_card.php', 1).'?action=create');
print '</div>';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('Vehicle').'</th><th>'.$langs->trans('Consumable').'</th><th>'.$langs->trans('Unit').'</th><th class="right">'.$langs->trans('Entries').'</th><th class="right">'.$langs->trans('TotalQuantity').'</th><th class="right">'.$langs->trans('TotalCost').'</th><th class="right">'.$langs->trans('AverageConsumption100').'</th><th class="right">'.$langs->trans('WeightedUnitPrice').'</th><th class="right">'.$langs->trans('PeakQuantity').'</th><th class="right">'.$langs->trans('PeakUnitPrice').'</th><th class="right">'.$langs->trans('PeakConsumption100').'</th><th class="right">'.$langs->trans('ExcludedIntervals').'</th>';
if (!empty($entityOptions)) print '<th class="center">'.$langs->trans('Environment').'</th>';
print '</tr>';
foreach ($groups as $group) {
	$unit = LmdbVehicleConsumable::unitLabel((string) $group['unit']);
	print '<tr class="oddeven"><td>'.dol_escape_htmltag(lmdbVehicleDisplayIdentifier((string) $group['vehicle_ref'], (string) $group['registration_number'])).'</td><td>'.dol_escape_htmltag((string) $group['consumable_label']).'</td><td>'.dol_escape_htmltag($unit).'</td><td class="right">'.((int) $group['count']).'</td><td class="right">'.price($group['total_quantity']).'</td><td class="right">'.price($group['total_cost']).' '.dol_escape_htmltag((string) $group['currency']).'</td><td class="right">'.($group['consumption_100'] !== null ? price($group['consumption_100']) : '').'</td><td class="right">'.price($group['weighted_unit_price']).' '.dol_escape_htmltag((string) $group['currency']).'/'.dol_escape_htmltag($unit).'</td><td class="right">'.price($group['peak_quantity']).'</td><td class="right">'.price($group['peak_unit_price']).' '.dol_escape_htmltag((string) $group['currency']).'/'.dol_escape_htmltag($unit).'</td><td class="right">'.($group['peak_consumption_100'] !== null ? price($group['peak_consumption_100']) : '').'</td><td class="right">'.((int) $group['excluded_intervals']).'</td>';
	if (!empty($entityOptions)) {
		$entityLabel = isset($entityOptions[(int) $group['entity']]) ? $entityOptions[(int) $group['entity']] : (string) $group['entity'];
		print '<td class="center"><div class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityLabel).'</span></div></td>';
	}
	print '</tr>';
}
if (empty($groups)) print '<tr class="oddeven"><td colspan="'.(!empty($entityOptions) ? 13 : 12).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</table></div>';

foreach ($groups as $key => $group) {
	$series = array_values(array_filter($rows, static function ($row) use ($group) {
		return (int) $row['entity'] === (int) $group['entity'] && (int) $row['vehicle_id'] === (int) $group['vehicle_id'] && (int) $row['consumable_id'] === (int) $group['consumable_id'] && (string) $row['unit'] === (string) $group['unit'] && (string) $row['currency'] === (string) $group['currency'];
	}));
	print '<div class="fichecenter"><div class="fichehalfleft">'.lmdbVehicleConsumptionRenderGraph($series, (string) $group['category'] === 'fuel' ? 'consumption_100' : 'quantity', $langs->trans((string) $group['category'] === 'fuel' ? 'AverageConsumption100' : 'Quantity').' - '.(string) $group['consumable_label'], 'global-'.$key.'-main').'</div>';
	print '<div class="fichehalfright">'.lmdbVehicleConsumptionRenderGraph($series, 'unit_price', $langs->trans('UnitPrice').' - '.(string) $group['consumable_label'], 'global-'.$key.'-price').'</div></div><div class="clearboth"></div>';
	if ((string) $group['category'] === 'fuel') {
		print '<div class="fichecenter"><div class="fichehalfleft">'.lmdbVehicleConsumptionRenderGraph($series, 'quantity', $langs->trans('Quantity').' - '.(string) $group['consumable_label'], 'global-'.$key.'-quantity').'</div>';
		print '<div class="fichehalfright">'.lmdbVehicleConsumptionRenderGraph($series, 'capacity_percent', $langs->trans('RecoveredCapacity').' - '.(string) $group['consumable_label'], 'global-'.$key.'-capacity').'</div></div><div class="clearboth"></div>';
	}
}
llxFooter();
$db->close();
