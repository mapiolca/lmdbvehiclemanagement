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
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumption.class.php');
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
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'event_timestamp';
$sortorder = strtoupper(GETPOST('sortorder', 'alpha')) === 'ASC' ? 'ASC' : 'DESC';
$sourceFilter = GETPOST('search_source', 'array:alpha');
if (!is_array($sourceFilter)) $sourceFilter = array();
$searchType = GETPOST('search_type', 'alphanohtml');
$searchLabel = GETPOST('search_label', 'alphanohtml');
$searchOdometer = GETPOST('search_odometer', 'alphanohtml');
$searchStatus = GETPOSTISSET('search_status') && GETPOST('search_status', 'alphanohtml') !== '' ? GETPOSTINT('search_status') : null;
$searchDocuments = GETPOST('search_documents', 'alpha');
$dateStartDay = GETPOSTINT('search_date_startday');
$dateStartMonth = GETPOSTINT('search_date_startmonth');
$dateStartYear = GETPOSTINT('search_date_startyear');
$dateEndDay = GETPOSTINT('search_date_endday');
$dateEndMonth = GETPOSTINT('search_date_endmonth');
$dateEndYear = GETPOSTINT('search_date_endyear');
if (GETPOST('button_removefilter', 'alpha')) {
	$sourceFilter = array();
	$searchType = $searchLabel = $searchOdometer = $searchDocuments = '';
	$searchStatus = null;
	$dateStartDay = $dateStartMonth = $dateStartYear = 0;
	$dateEndDay = $dateEndMonth = $dateEndYear = 0;
}
$searchDateStart = $dateStartDay > 0 && $dateStartMonth > 0 && $dateStartYear > 0 ? dol_mktime(0, 0, 0, $dateStartMonth, $dateStartDay, $dateStartYear) : 0;
$searchDateEnd = $dateEndDay > 0 && $dateEndMonth > 0 && $dateEndYear > 0 ? dol_mktime(23, 59, 59, $dateEndMonth, $dateEndDay, $dateEndYear) : 0;
$filters = array(
	'date_start' => $searchDateStart,
	'date_end' => $searchDateEnd,
	'type' => $searchType,
	'label' => $searchLabel,
	'odometer' => $searchOdometer,
	'documents' => $searchDocuments,
);
if ($searchStatus !== null) $filters['status'] = $searchStatus;

$vehicle = new LmdbVehicle($db);
if (!isModEnabled('lmdbvehiclemanagement') || !lmdbVehicleManagementCanDo($user, 'read') || !empty($user->socid)) accessforbidden();
if ($id <= 0 || $vehicle->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));
$history = new LmdbVehicleHistory($db);
$total = $history->countTimeline($id, $sourceFilter, $filters);
$total = $total < 0 ? -1 : $total;
if ($total >= 0 && $offset > $total) {
	$page = 0;
	$offset = 0;
}
$entries = $history->getTimeline($id, $sourceFilter, $limit, $offset, $filters, $sortfield, $sortorder);
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
if ($searchType !== '') $param .= '&search_type='.urlencode($searchType);
if ($searchLabel !== '') $param .= '&search_label='.urlencode($searchLabel);
if ($searchOdometer !== '') $param .= '&search_odometer='.urlencode($searchOdometer);
if ($searchStatus !== null) $param .= '&search_status='.((int) $searchStatus);
if ($searchDocuments !== '') $param .= '&search_documents='.urlencode($searchDocuments);
if ($searchDateStart > 0) {
	$param .= '&search_date_startday='.dol_print_date($searchDateStart, '%d').'&search_date_startmonth='.dol_print_date($searchDateStart, '%m').'&search_date_startyear='.dol_print_date($searchDateStart, '%Y');
}
if ($searchDateEnd > 0) {
	$param .= '&search_date_endday='.dol_print_date($searchDateEnd, '%d').'&search_date_endmonth='.dol_print_date($searchDateEnd, '%m').'&search_date_endyear='.dol_print_date($searchDateEnd, '%Y');
}
$sourceOptions = array(
	'event' => $langs->trans('TimelineSourceEvent'),
	'assignment' => $langs->trans('TimelineSourceAssignment'),
	'odometer' => $langs->trans('TimelineSourceOdometer'),
	'consumption' => $langs->trans('TimelineSourceConsumption'),
	'insurance' => $langs->trans('TimelineSourceInsurance'),
);
$eventStatusObject = new LmdbVehicleEvent($db);
$assignmentStatusObject = new LmdbVehicleAssignment($db);
$odometerStatusObject = new LmdbVehicleOdometerReading($db);
$consumptionStatusObject = new LmdbVehicleConsumption($db);
$insuranceContractStatusObject = new LmdbVehicleInsuranceContract($db);
$insuranceCertificateStatusObject = new LmdbVehicleInsuranceCertificate($db);
$canManageAssignments = lmdbVehicleManagementCanDo($user, 'assignment', 'write');
$canManageOdometer = lmdbVehicleManagementCanDo($user, 'odometer', 'write');
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
	'fuel' => 'FuelOrRecharge',
	'additive' => 'Additive',
	'contract_linked' => 'InsuranceHistoryContractLinked',
	'certificate_submitted' => 'InsuranceHistoryCertificateSubmitted',
	'certificate_validated' => 'InsuranceHistoryCertificateValidated',
	'certificate_rejected' => 'InsuranceHistoryCertificateRejected',
);
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'">';
print_barre_liste($langs->trans('Timeline'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', count($entries), $total, 'history', 0, '', '', $limit, 0, 0, 1);
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent liste">';
print '<tr class="liste_titre_filter">';
print '<td class="nowraponall">'.$form->selectDate($searchDateStart ?: -1, 'search_date_start', 0, 0, 1, '', 1, 0).' '.$form->selectDate($searchDateEnd ?: -1, 'search_date_end', 0, 0, 1, '', 1, 0).'</td>';
print '<td>'.$form->multiselectarray('search_source', $sourceOptions, $sourceFilter, 0, 0, 'minwidth150').'</td>';
print '<td><input type="text" class="flat maxwidth100" name="search_type" value="'.dol_escape_htmltag($searchType).'"></td>';
print '<td><input type="text" class="flat minwidth150" name="search_label" value="'.dol_escape_htmltag($searchLabel).'"></td>';
print '<td class="right"><input type="text" class="flat maxwidth75" name="search_odometer" value="'.dol_escape_htmltag($searchOdometer).'"></td>';
$statusOptions = array(0 => $langs->trans('TimelineStatusValue0'), 1 => $langs->trans('TimelineStatusValue1'), 2 => $langs->trans('TimelineStatusValue2'), 3 => $langs->trans('TimelineStatusValue3'), 9 => $langs->trans('TimelineStatusValue9'));
print '<td class="center">'.$form->selectarray('search_status', $statusOptions, $searchStatus === null ? '' : $searchStatus, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth125').'</td>';
print '<td class="center">'.$form->selectarray('search_documents', array('with' => $langs->trans('WithDocuments'), 'without' => $langs->trans('WithoutDocuments')), $searchDocuments, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth125').'</td>';
print '<td class="center nowraponall">'.$form->showFilterButtons().'</td></tr>';
print '<tr class="liste_titre">';
print getTitleFieldOfList('Date', 0, $_SERVER['PHP_SELF'], 'event_timestamp', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList('TimelineSource', 0, $_SERVER['PHP_SELF'], 'source_code', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList('Type', 0, $_SERVER['PHP_SELF'], 'entry_type', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList('Label', 0, $_SERVER['PHP_SELF'], 'source_label', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList('OdometerKm', 0, $_SERVER['PHP_SELF'], 'odometer_km', '', $param, 'class="right"', $sortfield, $sortorder, 'right ');
print getTitleFieldOfList('Status', 0, $_SERVER['PHP_SELF'], 'status', '', $param, 'class="center"', $sortfield, $sortorder, 'center ');
print getTitleFieldOfList('TimelineDocuments', 0, $_SERVER['PHP_SELF'], 'document_count', '', $param, 'class="center"', $sortfield, $sortorder, 'center ');
print getTitleFieldOfList('', 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder);
print '</tr>';
foreach ($entries as $entry) {
	$url = '';
	if ($entry['source'] === 'event') $url = dol_buildpath('/lmdbvehiclemanagement/vehicleevent_card.php', 1).'?id='.$entry['source_id'];
	elseif ($entry['source'] === 'assignment') {
		$url = dol_buildpath('/lmdbvehiclemanagement/vehicle_assignment.php', 1).'?id='.$id;
		$url .= ($canManageAssignments ? '&assignment_id='.$entry['source_id'].'&action=edit' : '#assignment-'.$entry['source_id']);
	} elseif ($entry['source'] === 'odometer') {
		$url = dol_buildpath('/lmdbvehiclemanagement/vehicle_odometer.php', 1).'?id='.$id;
		$url .= ($canManageOdometer ? '&reading_id='.$entry['source_id'].'&action=edit' : '#odometer-'.$entry['source_id']);
	} elseif ($entry['source'] === 'consumption') {
		$url = dol_buildpath('/lmdbvehiclemanagement/consumption_card.php', 1).'?id='.$entry['source_id'];
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
	elseif ($entry['source'] === 'consumption') $statusLabel = $consumptionStatusObject->LibStatut($entry['status'], 5);
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
