<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleevent.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleassignment.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleodometerreading.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecertificate.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclehistory.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('users', 'companies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
$id = GETPOSTINT('id');
$limit = GETPOSTINT('limit') ?: (int) $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? GETPOSTINT('pageplusone') - 1 : GETPOSTINT('page');
if ($page < 0 || GETPOST('button_removefilter', 'alpha')) $page = 0;
$offset = $limit * $page;
$sourceFilter = GETPOST('search_source', 'array:alpha');
if (!is_array($sourceFilter)) $sourceFilter = array();
if (GETPOST('button_removefilter', 'alpha')) $sourceFilter = array();

$vehicle = new LmdbVehicle($db);
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();
if ($id <= 0 || $vehicle->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));
$history = new LmdbVehicleHistory($db);
$total = $history->countTimeline($id, $sourceFilter);
$total = $total < 0 ? -1 : $total;
if ($total >= 0 && $offset > $total) {
	$page = 0;
	$offset = 0;
}
$entries = $history->getTimeline($id, $sourceFilter, $limit, $offset);
if (!is_array($entries) || $total < 0) {
	setEventMessages($langs->trans($history->error), null, 'errors');
	$entries = array();
	$total = 0;
}

$form = new Form($db);
llxHeader('', $vehicle->ref.' - '.$langs->trans('VehicleHistory'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');
$head = lmdbVehiclePrepareHead($vehicle);
print dol_get_fiche_head($head, 'history', $langs->trans('Vehicle'), -1, $vehicle->picto);
lmdbVehiclePrintBanner($vehicle);

$param = '&id='.$id;
foreach ($sourceFilter as $source) $param .= '&search_source[]='.urlencode($source);
$sourceOptions = array('event' => $langs->trans('TimelineSourceEvent'), 'assignment' => $langs->trans('TimelineSourceAssignment'), 'odometer' => $langs->trans('TimelineSourceOdometer'), 'insurance' => $langs->trans('TimelineSourceInsurance'));
$eventStatusObject = new LmdbVehicleEvent($db);
$assignmentStatusObject = new LmdbVehicleAssignment($db);
$odometerStatusObject = new LmdbVehicleOdometerReading($db);
$insuranceContractStatusObject = new LmdbVehicleInsuranceContract($db);
$insuranceCertificateStatusObject = new LmdbVehicleInsuranceCertificate($db);
$canManageAssignments = $user->hasRight('lmdbvehiclemanagement', 'assignment', 'write');
$canManageOdometer = $user->hasRight('lmdbvehiclemanagement', 'odometer', 'write');
$typeTranslations = array(
	'maintenance' => 'EventTypeMaintenance',
	'breakdown' => 'EventTypeBreakdown',
	'accident' => 'EventTypeAccident',
	'inspection' => 'EventTypeInspection',
	'administrative' => 'EventTypeAdministrative',
	'other' => 'EventTypeOther',
	'driver' => 'AssignmentTypeDriver',
	'custodian' => 'AssignmentTypeCustodian',
	'pool' => 'AssignmentTypePool',
	'standard' => 'ReadingKindStandard',
	'correction' => 'ReadingKindCorrection',
	'replacement' => 'ReadingKindReplacement',
	'contract_linked' => 'InsuranceHistoryContractLinked',
	'certificate_submitted' => 'InsuranceHistoryCertificateSubmitted',
	'certificate_validated' => 'InsuranceHistoryCertificateValidated',
	'certificate_rejected' => 'InsuranceHistoryCertificateRejected',
);
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'">';
print_barre_liste($langs->trans('Timeline'), $page, $_SERVER['PHP_SELF'], $param, 'event_timestamp', 'DESC', '', count($entries), $total, 'history', 0, '', '', $limit, 0, 0, 1);
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent liste">';
print '<tr class="liste_titre_filter"><td></td><td>'.$form->multiselectarray('search_source', $sourceOptions, $sourceFilter, 0, 0, 'minwidth200').'</td><td></td><td></td><td></td><td></td><td></td><td>'.$form->showFilterButtons().'</td></tr>';
print '<tr class="liste_titre"><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('TimelineSource').'</th><th>'.$langs->trans('Type').'</th><th>'.$langs->trans('Label').'</th><th class="right">'.$langs->trans('OdometerKm').'</th><th class="center">'.$langs->trans('Status').'</th><th class="center">'.$langs->trans('TimelineDocuments').'</th><th></th></tr>';
foreach ($entries as $entry) {
	$url = '';
	if ($entry['source'] === 'event') $url = dol_buildpath('/lmdbvehiclemanagement/vehicleevent_card.php', 1).'?id='.$entry['source_id'];
	elseif ($entry['source'] === 'assignment') {
		$url = dol_buildpath('/lmdbvehiclemanagement/vehicle_assignment.php', 1).'?id='.$id;
		$url .= ($canManageAssignments ? '&assignment_id='.$entry['source_id'].'&action=edit' : '#assignment-'.$entry['source_id']);
	} elseif ($entry['source'] === 'odometer') {
		$url = dol_buildpath('/lmdbvehiclemanagement/vehicle_odometer.php', 1).'?id='.$id;
		$url .= ($canManageOdometer ? '&reading_id='.$entry['source_id'].'&action=edit' : '#odometer-'.$entry['source_id']);
	} elseif ($entry['source'] === 'insurance') {
		if ($entry['source_object'] === 'lmdbinsurancecontract') {
			$url = dol_buildpath('/lmdbvehiclemanagement/insurancecontract_card.php', 1).'?id='.$entry['source_id'];
		} else {
			$url = dol_buildpath('/lmdbvehiclemanagement/vehicle_insurance.php', 1).'?id='.$id.'&certificate_id='.$entry['source_id'];
		}
	}
	$sourceLabel = $sourceOptions[$entry['source']];
	$typeLabel = isset($typeTranslations[$entry['type']]) ? $langs->trans($typeTranslations[$entry['type']]) : $entry['type'];
	if ($entry['source'] === 'event') $statusLabel = $eventStatusObject->LibStatut($entry['status'], 5);
	elseif ($entry['source'] === 'assignment') $statusLabel = $assignmentStatusObject->LibStatut($entry['status'], 5);
	elseif ($entry['source'] === 'odometer') $statusLabel = $odometerStatusObject->LibStatut($entry['status'], 5);
	elseif ($entry['source_object'] === 'lmdbinsurancecontract') $statusLabel = $insuranceContractStatusObject->LibStatut($entry['status'], 5);
	else $statusLabel = $insuranceCertificateStatusObject->LibStatut($entry['status'], 5);
	print '<tr class="oddeven"><td>'.dol_print_date($entry['date'], 'dayhour').'</td><td>'.$sourceLabel.'</td><td>'.dol_escape_htmltag($typeLabel).'</td>';
	print '<td>'.($url !== '' ? '<a href="'.$url.'">'.dol_escape_htmltag($entry['label']).'</a>' : dol_escape_htmltag($entry['label'])).'</td>';
	print '<td class="right">'.($entry['odometer_km'] !== null ? price($entry['odometer_km'], 0, $langs, 1, -1, -1).' km' : '').'</td>';
	print '<td class="center">'.$statusLabel.'</td><td class="center">'.($entry['has_documents'] ? img_picto($langs->trans('Documents'), 'paperclip').' '.((int) $entry['document_count']) : '').'</td><td></td></tr>';
}
if (empty($entries)) print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
print '</table></div></form>';
print dol_get_fiche_end();
llxFooter();
$db->close();
