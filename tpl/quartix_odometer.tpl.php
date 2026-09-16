<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Included only after the QUARTIX page has authorized its selected dataset.
$mileage = $service->readings($id, $quartixId, $limit, $page);
$param = '&id='.$id.'&quartix_id='.$quartixId.'&view=odometer&limit='.$limit;
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="quartix_id" value="'.$quartixId.'"><input type="hidden" name="view" value="odometer">';
print_barre_liste($langs->trans('QxOdometerHistory'), $mileage['page'], $_SERVER['PHP_SELF'], $param, '', '', '', count($mileage['rows']), $mileage['total'], 'car', 0, '', '', $limit);
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('ReadingDate').'</th><th class="right">'.$langs->trans('OdometerKm').'</th><th>'.$langs->trans('ReadingReason').'</th></tr>';
foreach ($mileage['rows'] as $row) {
	print '<tr class="oddeven"><td>'.dol_print_date($db->jdate($row->reading_date), 'dayhour').'</td><td class="right">'.price($row->odometer_km, 0, $langs, 1, -1, -1).' km</td><td>'.dol_escape_htmltag((string) $row->reason).'</td></tr>';
}
if (!$mileage['rows']) print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</table></div></form>';
