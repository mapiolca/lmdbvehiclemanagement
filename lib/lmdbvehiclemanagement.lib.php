<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Translate and display errors returned by a module business object.
 *
 * Object methods may expose either a translated sentence or a stable
 * translation key. This boundary keeps persisted/business code independent
 * from the language selected by the current interface.
 *
 * @param object $object Object exposing error and errors properties
 * @return void
 */
function lmdbVehicleManagementSetObjectErrors($object)
{
	global $langs;

	$messages = array();
	if (isset($object->error) && is_string($object->error) && $object->error !== '') {
		$messages[] = $langs->trans($object->error);
	}
	if (isset($object->errors) && is_array($object->errors)) {
		foreach ($object->errors as $error) {
			if (is_string($error) && $error !== '') {
				$messages[] = $langs->trans($error);
			}
		}
	}
	$messages = array_values(array_unique($messages));
	if (!empty($messages)) {
		setEventMessages('', $messages, 'errors');
	}
}

/**
 * Build administration tabs.
 *
 * @return array<int,array{0:string,1:string,2:string}>
 */
function lmdbVehicleManagementAdminPrepareHead()
{
	global $langs;

	$langs->loadLangs(array('admin', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));

	$head = array();
	$h = 0;
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/admin/setup.php', 1), $langs->trans('Settings'), 'settings');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/admin/insurance.php', 1), $langs->trans('Insurance'), 'insurance');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/admin/regulatory.php', 1), $langs->trans('RegulatoryControls'), 'regulatory');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/admin/quartix.php', 1), $langs->trans('QxTitle'), 'quartix');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/admin/compatibility.php', 1), $langs->trans('Compatibility'), 'compatibility');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/admin/about.php', 1), $langs->trans('About'), 'about');

	return $head;
}

/**
 * Build vehicle tabs in native order.
 *
 * @param LmdbVehicle $object Vehicle
 * @return array<int,array{0:string,1:string,2:string}>
 */
function lmdbVehiclePrepareHead($object)
{
	global $langs, $user;

	$langs->loadLangs(array('companies', 'agenda', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
	$id = (int) $object->id;
	$head = array();
	$h = 0;
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_card.php', 1).'?id='.$id, $langs->trans('Card'), 'card');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_note.php', 1).'?id='.$id, $langs->trans('Notes'), 'notes');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_document.php', 1).'?id='.$id, $langs->trans('Documents'), 'documents');
	if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
		$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_agenda.php', 1).'?id='.$id, $langs->trans('EventsAgenda'), 'agenda');
	}

	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_assignment.php', 1).'?id='.$id, $langs->trans('VehicleAssignments'), 'assignments');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_odometer.php', 1).'?id='.$id, $langs->trans('OdometerReadings'), 'odometer');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_consumption.php', 1).'?id='.$id, $langs->trans('Consumption'), 'consumption');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_regulatory.php', 1).'?id='.$id, $langs->trans('RegulatoryControls'), 'regulatory');
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_history.php', 1).'?id='.$id, $langs->trans('VehicleHistory'), 'history');
	require_once __DIR__.'/../class/lmdbvehiclequartixconfig.class.php';
	if (LmdbVehicleQuartixConfig::supported() && LmdbVehicleQuartixConfig::can($user, 'read')) {
		$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_quartix.php', 1).'?id='.$id, $langs->trans('QxUsage'), 'quartix');
		if (LmdbVehicleQuartixConfig::can($user, 'location')) $head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/vehicle_trips.php', 1).'?id='.$id, $langs->trans('QxJournal'), 'trips');
	}

	return $head;
}

/** @param LmdbVehicleRegulatoryControl $object Control @return array<int,array{0:string,1:string,2:string}> */
function lmdbVehicleRegulatoryControlPrepareHead($object)
{
	global $db, $langs, $user;

	$langs->loadLangs(array('agenda', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
	$id = (int) $object->id;
	$head = array();
	$head[] = array(dol_buildpath('/lmdbvehiclemanagement/regulatorycontrol_card.php', 1).'?id='.$id, $langs->trans('Card'), 'card');
	$noteCount = (!empty($object->note_public) ? 1 : 0) + (!empty($object->note_private) ? 1 : 0);
	$head[] = array(dol_buildpath('/lmdbvehiclemanagement/regulatorycontrol_note.php', 1).'?id='.$id, $langs->trans('Notes').($noteCount ? '<span class="badge marginleftonlyshort">'.$noteCount.'</span>' : ''), 'notes');
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	$uploadDir = getMultidirOutput($object, 'lmdbvehiclemanagement', 1);
	$fileCount = is_string($uploadDir) && $uploadDir !== '' && strpos($uploadDir, 'error-diroutput-') !== 0 ? count(dol_dir_list($uploadDir, 'files', 0, '', '(\.meta|_preview.*\.png)$')) : 0;
	$head[] = array(dol_buildpath('/lmdbvehiclemanagement/regulatorycontrol_document.php', 1).'?id='.$id, $langs->trans('Documents').($fileCount ? '<span class="badge marginleftonlyshort">'.$fileCount.'</span>' : ''), 'documents');
	if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
		$agendaCount = 0;
		$resql = $db->query('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'actioncomm AS a'.lmdbVehicleRegulatoryControlAgendaWhere($object, 'a'));
		if ($resql && is_object($row = $db->fetch_object($resql))) $agendaCount = (int) $row->total;
		if ($resql) $db->free($resql);
		$head[] = array(dol_buildpath('/lmdbvehiclemanagement/regulatorycontrol_agenda.php', 1).'?id='.$id, $langs->trans('EventsAgenda').($agendaCount ? '<span class="badge marginleftonlyshort">'.$agendaCount.'</span>' : ''), 'agenda');
	}
	return $head;
}

/** @param LmdbVehicleRegulatoryControl $object Control @param string $alias Alias @return string */
function lmdbVehicleRegulatoryControlAgendaWhere($object, $alias = 'a')
{
	global $user;
	$alias = preg_match('/^[a-z][a-z0-9_]*$/i', $alias) ? $alias : 'a';
	$where = " WHERE ".$alias.".elementtype = 'lmdbvehicleregulatorycontrol@lmdbvehiclemanagement'";
	$where .= ' AND '.$alias.'.fk_element = '.((int) $object->id).' AND '.$alias.'.entity IN ('.getEntity('agenda').')';
	if (!$user->hasRight('agenda', 'allactions', 'read')) {
		$where .= ' AND ('.$alias.'.fk_user_author = '.((int) $user->id).' OR '.$alias.'.fk_user_action = '.((int) $user->id).')';
	}
	return $where;
}

/** @param LmdbVehicleRegulatoryControl $object Control @return void */
function lmdbVehicleRegulatoryControlPrintBanner($object)
{
	global $langs;
	$linkback = '<a href="'.dol_buildpath('/lmdbvehiclemanagement/regulatorycontrol_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
	$more = '<div class="refidno">'.$langs->trans('RegulatoryControl').'</div>';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $more);
}

/**
 * Return accessible entity labels only when an element is actually shared.
 *
 * An empty result means that the environment badge, column and filter are not
 * useful: Multicompany is disabled or the current entity has no sharing scope
 * with another accessible entity for this element.
 *
 * @param string $element Multicompany sharing element
 * @return array<int,string>
 */
function lmdbVehicleManagementGetEntityOptions($element)
{
	global $db;

	if (!isModEnabled('multicompany')) {
		return array();
	}

	$entityIds = array_values(array_unique(array_filter(array_map('intval', explode(',', getEntity($element))), static function ($entityId) {
		return $entityId > 0;
	})));
	if (count($entityIds) <= 1) {
		return array();
	}

	$options = array();
	$sql = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'entity';
	$sql .= ' WHERE rowid IN ('.implode(',', $entityIds).') ORDER BY label';
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.': '.$db->lasterror(), LOG_ERR);
		return array();
	}
	while (is_object($row = $db->fetch_object($resql))) {
		$options[(int) $row->rowid] = (string) $row->label;
	}
	$db->free($resql);

	return count($options) > 1 ? $options : array();
}

/**
 * Render the native Multicompany badge for an entity.
 *
 * @param int $entityId Entity identifier
 * @param array<int,string> $entityOptions Accessible entity labels
 * @return string
 */
function lmdbVehicleManagementEntityBadge($entityId, $entityOptions)
{
	if ($entityId <= 0 || count($entityOptions) <= 1) {
		return '';
	}

	$entityLabel = isset($entityOptions[$entityId]) ? $entityOptions[$entityId] : (string) $entityId;

	return '<div class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityLabel).'</span></div>';
}

/**
 * Build consumption tabs in native order.
 *
 * @param LmdbVehicleConsumption $object Consumption
 * @return array<int,array{0:string,1:string,2:string}>
 */
function lmdbVehicleConsumptionPrepareHead($object)
{
	global $db, $langs, $user;

	$langs->loadLangs(array('agenda', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
	$id = (int) $object->id;
	$head = array();
	$head[] = array(dol_buildpath('/lmdbvehiclemanagement/consumption_card.php', 1).'?id='.$id, $langs->trans('Card'), 'card');
	$noteCount = (!empty($object->note_public) ? 1 : 0) + (!empty($object->note_private) ? 1 : 0);
	$head[] = array(dol_buildpath('/lmdbvehiclemanagement/consumption_note.php', 1).'?id='.$id, $langs->trans('Notes').($noteCount ? '<span class="badge marginleftonlyshort">'.$noteCount.'</span>' : ''), 'notes');
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	$uploadDir = getMultidirOutput($object, 'lmdbvehiclemanagement', 1);
	$fileCount = is_string($uploadDir) && $uploadDir !== '' && strpos($uploadDir, 'error-diroutput-') !== 0 ? count(dol_dir_list($uploadDir, 'files', 0, '', '(\.meta|_preview.*\.png)$')) : 0;
	$head[] = array(dol_buildpath('/lmdbvehiclemanagement/consumption_document.php', 1).'?id='.$id, $langs->trans('Documents').($fileCount ? '<span class="badge marginleftonlyshort">'.$fileCount.'</span>' : ''), 'documents');
	if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
		$agendaCount = 0;
		$resql = $db->query('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'actioncomm AS a'.lmdbVehicleConsumptionAgendaWhere($object, 'a'));
		if ($resql && is_object($row = $db->fetch_object($resql))) $agendaCount = (int) $row->total;
		if ($resql) $db->free($resql);
		$head[] = array(dol_buildpath('/lmdbvehiclemanagement/consumption_agenda.php', 1).'?id='.$id, $langs->trans('EventsAgenda').($agendaCount ? '<span class="badge marginleftonlyshort">'.$agendaCount.'</span>' : ''), 'agenda');
	}
	return $head;
}

/** @param LmdbVehicleConsumption $object Consumption @param string $alias Alias @return string */
function lmdbVehicleConsumptionAgendaWhere($object, $alias = 'a')
{
	global $user;
	$alias = preg_match('/^[a-z][a-z0-9_]*$/i', $alias) ? $alias : 'a';
	$where = " WHERE ".$alias.".elementtype = 'lmdbvehicleconsumption@lmdbvehiclemanagement'";
	$where .= ' AND '.$alias.'.fk_element = '.((int) $object->id).' AND '.$alias.'.entity IN ('.getEntity('agenda').')';
	if (!$user->hasRight('agenda', 'allactions', 'read')) {
		$where .= ' AND ('.$alias.'.fk_user_author = '.((int) $user->id).' OR '.$alias.'.fk_user_action = '.((int) $user->id);
		$where .= ' OR EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'actioncomm_resources AS lmdbvm_ar WHERE lmdbvm_ar.fk_actioncomm = '.$alias.'.id';
		$where .= " AND lmdbvm_ar.element_type = 'user' AND lmdbvm_ar.fk_element = ".((int) $user->id).'))';
	}
	return $where;
}

/** @param LmdbVehicleConsumption $object Consumption @return void */
function lmdbVehicleConsumptionPrintBanner($object)
{
	global $langs;
	$linkback = '<a href="'.dol_buildpath('/lmdbvehiclemanagement/consumption_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
	$moreHtmlRef = '<div class="refidno">'.$langs->trans($object->category_snapshot === 'fuel' ? 'FuelOrRecharge' : 'Additive');
	$entityBadge = lmdbVehicleManagementEntityBadge((int) $object->entity, lmdbVehicleManagementGetEntityOptions('lmdbvehicleconsumption'));
	if ($entityBadge !== '') {
		$moreHtmlRef .= '<br>'.$entityBadge;
	}
	$moreHtmlRef .= '</div>';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $moreHtmlRef);
}

/**
 * Render one native DolGraph series in a stable native table.
 *
 * @param array<int,array<string,int|float|string|null>> $seriesRows Rows for one consumable and unit
 * @param string $metric unit_price, quantity, capacity_percent or consumption_100
 * @param string $title Graph title
 * @param string $graphKey Stable cache key
 * @return string
 */
function lmdbVehicleConsumptionRenderGraph($seriesRows, $metric, $title, $graphKey)
{
	global $conf, $langs;

	$data = array();
	$previousKm = null;
	foreach ($seriesRows as $row) {
		$value = null;
		if ($metric === 'unit_price' && $row['total_ttc'] !== null && (float) $row['quantity'] > 0) $value = (float) $row['total_ttc'] / (float) $row['quantity'];
		if ($metric === 'quantity') $value = (float) $row['quantity'];
		if ($metric === 'capacity_percent' && $row['capacity'] !== null && (float) $row['capacity'] > 0) $value = (float) $row['quantity'] / (float) $row['capacity'] * 100;
		if ($metric === 'consumption_100' && $previousKm !== null) {
			$distance = (float) $row['odometer_km'] - $previousKm;
			if ($distance > 0 && (string) $row['reading_kind'] === 'standard') $value = (float) $row['quantity'] / $distance * 100;
		}
		$previousKm = (float) $row['odometer_km'];
		if ($value !== null) $data[] = array(dol_print_date((int) $row['date'], 'day'), price2num($value, 'MU'));
	}

	$content = '<span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span>';
	if (!empty($data)) {
		$tempDir = $conf->lmdbvehiclemanagement->dir_temp.'/consumption';
		if (dol_mkdir($tempDir) < 0) {
			$content = '<span class="error">'.$langs->trans('ErrorFailedToCreateDir').'</span>';
		} else {
			$fileName = 'graph_'.sha1($graphKey.serialize($data)).'.png';
			$file = $tempDir.'/'.$fileName;
			$fileUrl = DOL_URL_ROOT.'/viewimage.php?modulepart=lmdbvehiclemanagement_temp&file=/consumption/'.$fileName;
			$graph = new DolGraph();
			$graph->SetData($data);
			$graph->SetLegend(array($title));
			$graph->SetWidth(DolGraph::getDefaultGraphSizeForStats('width', '600'));
			$graph->SetHeight(DolGraph::getDefaultGraphSizeForStats('height', '220'));
			$graph->SetType(array('lines'));
			$graph->setBgColor('onglet');
			$graph->setBgColorGrid(array(255, 255, 255));
			$graph->draw($file, $fileUrl);
			$content = $graph->show();
		}
	}

	$html = '<div class="div-table-responsive-no-min">';
	$html .= '<table class="noborder centpercent tableforfield" style="table-layout: fixed;">';
	$html .= '<tr class="liste_titre"><th>'.dol_escape_htmltag($title).'</th></tr>';
	$html .= '<tr class="oddeven"><td class="center valignmiddle" style="height: 220px;">'.$content.'</td></tr>';
	$html .= '</table>';
	$html .= '</div>';

	return $html;
}

/**
 * Build a readable vehicle identifier without repeating registration-based refs.
 *
 * @param string $ref Vehicle reference
 * @param string $registration Registration number
 * @param string $label Vehicle label
 * @return string
 */
function lmdbVehicleDisplayIdentifier($ref, $registration, $label = '')
{
	$parts = array();
	if ($ref !== '') {
		$parts[] = $ref;
	}
	if ($registration !== '' && strcasecmp($registration, $ref) !== 0) {
		$parts[] = $registration;
	}
	if ($label !== '') {
		$parts[] = $label;
	}

	return implode(' — ', $parts);
}

/**
 * Print the common native banner used by every vehicle tab.
 *
 * @param LmdbVehicle $object Vehicle
 * @return void
 */
function lmdbVehiclePrintBanner($object)
{
	global $langs;

	$linkback = '<a href="'.dol_buildpath('/lmdbvehiclemanagement/vehicle_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
	$secondaryIdentifier = strcasecmp((string) $object->ref, (string) $object->registration_number) === 0
		? (string) $object->label
		: (string) $object->registration_number.' — '.(string) $object->label;
	$moreHtmlRef = '<div class="refidno">'.dol_escape_htmltag($secondaryIdentifier);
	$entityBadge = lmdbVehicleManagementEntityBadge((int) $object->entity, lmdbVehicleManagementGetEntityOptions('lmdbvehicle'));
	if ($entityBadge !== '') {
		$moreHtmlRef .= '<br>'.$entityBadge;
	}
	$moreHtmlRef .= '</div>';

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $moreHtmlRef);
}

/**
 * Build insurance contract tabs in native Dolibarr order.
 *
 * @param LmdbVehicleInsuranceContract $object Insurance contract
 * @return array<int,array{0:string,1:string,2:string}>
 */
function lmdbInsuranceContractPrepareHead($object)
{
	global $db, $langs, $user;

	$langs->loadLangs(array('companies', 'agenda', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
	$id = (int) $object->id;
	$head = array();
	$h = 0;
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/insurancecontract_card.php', 1).'?id='.$id, $langs->trans('Card'), 'card');

	$contacts = array_merge($object->liste_contact(-1, 'internal'), $object->liste_contact(-1, 'external'));
	$contactLabel = $langs->trans('ContactsAddresses');
	if (count($contacts) > 0) {
		$contactLabel .= '<span class="badge marginleftonlyshort">'.count($contacts).'</span>';
	}
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/insurancecontract_contact.php', 1).'?id='.$id, $contactLabel, 'contacts');

	$noteCount = (!empty($object->note_public) ? 1 : 0) + (!empty($object->note_private) ? 1 : 0);
	$noteLabel = $langs->trans('Notes');
	if ($noteCount > 0) {
		$noteLabel .= '<span class="badge marginleftonlyshort">'.$noteCount.'</span>';
	}
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/insurancecontract_note.php', 1).'?id='.$id, $noteLabel, 'notes');

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	$uploadDir = getMultidirOutput($object, 'lmdbvehiclemanagement', 1);
	$fileCount = 0;
	if (is_string($uploadDir) && $uploadDir !== '' && strpos($uploadDir, 'error-diroutput-') !== 0) {
		$fileCount = count(dol_dir_list($uploadDir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
	}
	$documentLabel = $langs->trans('Documents');
	if ($fileCount > 0) {
		$documentLabel .= '<span class="badge marginleftonlyshort">'.$fileCount.'</span>';
	}
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/insurancecontract_document.php', 1).'?id='.$id, $documentLabel, 'documents');

	if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
		$agendaCount = 0;
		$sql = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'actioncomm AS a'.lmdbInsuranceContractAgendaWhere($object, 'a');
		$resql = $db->query($sql);
		if ($resql && is_object($row = $db->fetch_object($resql))) {
			$agendaCount = (int) $row->total;
		}
		if ($resql) {
			$db->free($resql);
		}
		$agendaLabel = $langs->trans('EventsAgenda');
		if ($agendaCount > 0) {
			$agendaLabel .= '<span class="badge marginleftonlyshort">'.$agendaCount.'</span>';
		}
		$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/insurancecontract_agenda.php', 1).'?id='.$id, $agendaLabel, 'agenda');
	}

	$certificateCount = 0;
	$sql = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_certificate';
	$sql .= ' WHERE fk_contract = '.$id.' AND entity = '.((int) $object->entity);
	$resql = $db->query($sql);
	if ($resql && is_object($row = $db->fetch_object($resql))) {
		$certificateCount = (int) $row->total;
	}
	if ($resql) {
		$db->free($resql);
	}
	$certificateLabel = $langs->trans('InsuranceCertificates');
	if ($certificateCount > 0) {
		$certificateLabel .= '<span class="badge marginleftonlyshort">'.$certificateCount.'</span>';
	}
	$head[$h++] = array(dol_buildpath('/lmdbvehiclemanagement/insurancecontract_certificate.php', 1).'?id='.$id, $certificateLabel, 'certificates');

	return $head;
}

/**
 * Return the native Agenda visibility filter for one insurance contract.
 *
 * @param LmdbVehicleInsuranceContract $object Insurance contract
 * @param string $alias Agenda table alias
 * @return string SQL WHERE clause
 */
function lmdbInsuranceContractAgendaWhere($object, $alias = 'a')
{
	global $user;

	$alias = preg_match('/^[a-z][a-z0-9_]*$/i', $alias) ? $alias : 'a';
	$where = " WHERE ".$alias.".elementtype = 'lmdbinsurancecontract@lmdbvehiclemanagement'";
	$where .= ' AND '.$alias.'.fk_element = '.((int) $object->id);
	$where .= ' AND '.$alias.'.entity IN ('.getEntity('agenda').')';
	if (!$user->hasRight('agenda', 'allactions', 'read')) {
		$where .= ' AND ('.$alias.'.fk_user_author = '.((int) $user->id);
		$where .= ' OR '.$alias.'.fk_user_action = '.((int) $user->id);
		$where .= ' OR EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'actioncomm_resources AS lmdbvm_ar';
		$where .= ' WHERE lmdbvm_ar.fk_actioncomm = '.$alias.'.id';
		$where .= " AND lmdbvm_ar.element_type = 'user' AND lmdbvm_ar.fk_element = ".((int) $user->id).'))';
	}

	return $where;
}

/**
 * Print the common native banner used by every insurance contract tab.
 *
 * @param LmdbVehicleInsuranceContract $object Insurance contract
 * @return void
 */
function lmdbInsuranceContractPrintBanner($object)
{
	global $langs;

	$linkback = '<a href="'.dol_buildpath('/lmdbvehiclemanagement/insurancecontract_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
	$moreHtmlRef = '<div class="refidno">'.dol_escape_htmltag($object->label);
	$entityBadge = lmdbVehicleManagementEntityBadge((int) $object->entity, lmdbVehicleManagementGetEntityOptions('lmdbvehicle'));
	if ($entityBadge !== '') {
		$moreHtmlRef .= '<br>'.$entityBadge;
	}
	$moreHtmlRef .= '</div>';

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $moreHtmlRef);
}

/**
 * Print the insurance at-a-glance block for a vehicle.
 *
 * @param LmdbVehicle $object Vehicle
 * @return void
 */
function lmdbVehiclePrintInsuranceBlock($object)
{
	global $db, $langs, $user;

	dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php');
	dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecertificate.class.php');
	dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsuranceconfig.class.php');
	$allContracts = LmdbVehicleInsuranceContract::getForVehicle($db, (int) $object->id);
	$contract = LmdbVehicleInsuranceContract::getPrimaryForVehicle($db, (int) $object->id);
	$headerActions = '';
	if ($contract instanceof LmdbVehicleInsuranceContract) {
		$headerActions = $contract->getNomUrl(1);
	} elseif (!empty($allContracts)) {
		$headerActions = $allContracts[0]['contract']->getNomUrl(1);
	} elseif ($user->hasRight('lmdbvehiclemanagement', 'insurance', 'write')) {
		$linkUrl = dol_buildpath('/lmdbvehiclemanagement/vehicle_insurance_link.php', 1).'?id='.((int) $object->id);
		$createUrl = dol_buildpath('/lmdbvehiclemanagement/insurancecontract_card.php', 1).'?action=create&vehicle_id='.((int) $object->id);
		$headerActions = dolGetButtonTitle($langs->trans('LinkInsuranceContract'), '', 'fa fa-link', $linkUrl);
		$headerActions .= dolGetButtonTitle($langs->trans('NewInsuranceContract'), '', 'fa fa-plus-circle', $createUrl);
	}
	print '<div class="underbanner clearboth"></div>';
	print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield">';
	print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('InsuranceContract');
	print '<span class="right marginleftonly">'.$headerActions.'</span></th></tr>';
	if (!$contract instanceof LmdbVehicleInsuranceContract) {
		print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('InsuranceNoActiveContract').'</span></td></tr>';
		print '</table></div>';
		return;
	}

	require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
	$insurer = new Societe($db);
	$insurerLink = $insurer->fetch((int) $contract->fk_soc) > 0 ? $insurer->getNomUrl(1) : '';
	$certificate = LmdbVehicleInsuranceCertificate::getApplicable($db, (int) $contract->id, (int) $object->id);
	$complementary = 0;
	foreach ($allContracts as $entry) if ($entry['coverage_type'] === LmdbVehicleInsuranceContract::COVERAGE_COMPLEMENTARY && (int) $entry['contract']->status === LmdbVehicleInsuranceContract::STATUS_ACTIVE) $complementary++;

	print '<tr><td class="titlefield">'.$langs->trans('InsuranceCompany').'</td><td>'.$insurerLink.'</td></tr>';
	print '<tr><td>'.$langs->trans('InsurancePolicyNumber').'</td><td>'.dol_escape_htmltag($contract->policy_number).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceCoverageFormula').'</td><td>'.dol_escape_htmltag((string) $contract->coverage_formula).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceContractPeriod').'</td><td>'.dol_print_date($contract->date_start, 'day').' — '.($contract->date_end ? dol_print_date($contract->date_end, 'day') : $langs->trans('NoLimit')).'</td></tr>';
	$status = dolGetStatus($langs->trans('InsuranceCertificateMissing'), '', '', 'status8', 5);
	$certificatePeriod = '';
	$evidence = '';
	if ($certificate instanceof LmdbVehicleInsuranceCertificate) {
		$certificatePeriod = dol_print_date($certificate->validity_start, 'day').' — '.dol_print_date($certificate->validity_end, 'day');
		if ((int) $certificate->status === LmdbVehicleInsuranceCertificate::STATUS_VALIDATED) {
			$today = dol_mktime(0, 0, 0, (int) dol_print_date(dol_now(), '%m'), (int) dol_print_date(dol_now(), '%d'), (int) dol_print_date(dol_now(), '%Y'));
			$days = (int) floor(((int) $certificate->validity_end - $today) / 86400);
			$reminderDays = LmdbVehicleInsuranceConfig::getBeforeDays();
			$soonThreshold = empty($reminderDays) ? 30 : max($reminderDays);
			if ($days < 0) $status = dolGetStatus($langs->trans('InsuranceCertificateExpired'), '', '', 'status8', 5);
			elseif ($days <= $soonThreshold) $status = dolGetStatus($langs->trans('InsuranceCertificateExpiring', $days), '', '', 'status1', 5);
			else $status = dolGetStatus($langs->trans('InsuranceCertificateValid'), '', '', 'status4', 5);
		} else {
			$status = $certificate->getLibStatut(5);
		}
		if (!empty($certificate->file_name)) {
			$url = dol_buildpath('/lmdbvehiclemanagement/insurancecontract_certificate.php', 1).'?id='.((int) $contract->id).'&download_certificate=1&certificate_id='.((int) $certificate->id);
			$evidence = '<a href="'.$url.'">'.img_picto('', 'paperclip', 'class="pictofixedwidth"').$langs->trans('Download').'</a>';
		}
	}
	print '<tr><td>'.$langs->trans('InsuranceCertificatePeriod').'</td><td>'.$certificatePeriod.'</td></tr>';
	print '<tr><td>'.$langs->trans('Status').'</td><td>'.$status.'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceEvidence').'</td><td>'.$evidence.'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceComplementaryContracts').'</td><td>'.((int) $complementary).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceAssistancePhone').'</td><td>'.dol_escape_htmltag((string) $contract->assistance_phone).'</td></tr>';
	print '</table></div>';
}

/**
 * Build event tabs.
 *
 * @param LmdbVehicleEvent $object Vehicle event
 * @return array<int,array{0:string,1:string,2:string}>
 */
function lmdbVehicleEventPrepareHead($object)
{
	global $langs;

	$langs->loadLangs(array('companies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
	$id = (int) $object->id;

	return array(
		array(dol_buildpath('/lmdbvehiclemanagement/vehicleevent_card.php', 1).'?id='.$id, $langs->trans('Card'), 'card'),
		array(dol_buildpath('/lmdbvehiclemanagement/vehicleevent_note.php', 1).'?id='.$id, $langs->trans('Notes'), 'notes'),
		array(dol_buildpath('/lmdbvehiclemanagement/vehicleevent_document.php', 1).'?id='.$id, $langs->trans('Documents'), 'documents'),
	);
}
