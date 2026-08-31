<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementcompatibility.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehicleinsurance.lib.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var HookManager $hookmanager */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('main', 'companies', 'contacts', 'other', 'agenda', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!isModEnabled('lmdbvehiclemanagement') || !$user->hasRight('lmdbvehiclemanagement', 'read') || !empty($user->socid)) accessforbidden();

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09') ?: ($id > 0 ? 'view' : 'create');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$permissionWrite = $user->hasRight('lmdbvehiclemanagement', 'insurance', 'write');
$permissionDelete = $user->hasRight('lmdbvehiclemanagement', 'insurance', 'delete');
$object = new LmdbVehicleInsuranceContract($db);
$coverage = array(
	'vehicle_ids' => array(),
	'coverage_type' => LmdbVehicleInsuranceContract::COVERAGE_PRIMARY,
	'coverage_start' => 0,
	'coverage_end' => null,
);
if ($id > 0 && $object->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));
$hookmanager->initHooks(array('lmdbinsurancecontractcard', 'globalcard'));

if ($cancel) {
	header('Location: '.($id > 0 ? $_SERVER['PHP_SELF'].'?id='.$id : dol_buildpath('/lmdbvehiclemanagement/insurancecontract_list.php', 1)));
	exit;
}

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
if (empty($reshook)) {
	if ($action === 'add' || $action === 'update') {
		if (!$permissionWrite || ($action === 'update' && $id <= 0)) accessforbidden();
		if ($action === 'add') $object->entity = (int) $conf->entity;
		lmdbInsurancePopulateContractFromPost($object);
		$coverage = lmdbInsuranceGetCoverageFromPost($object);
		$result = $object->saveWithVehicleLinks($coverage['vehicle_ids'], $coverage['coverage_type'], $coverage['coverage_start'], $coverage['coverage_end'], $user);
		if ($result > 0) {
			setEventMessages($langs->trans($action === 'add' ? 'InsuranceContractCreated' : 'InsuranceContractUpdated'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id));
			exit;
		}
		setEventMessages('', lmdbInsuranceMessages($object), 'errors');
		$action = $action === 'add' ? 'create' : 'edit';
	} elseif ($action === 'activate' || $action === 'terminate') {
		if (!$permissionWrite || $id <= 0) accessforbidden();
		$result = $action === 'activate' ? $object->activate($user) : $object->terminate($user);
		if ($result > 0) {
			setEventMessages($langs->trans('InsuranceActionCompleted'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
			exit;
		}
		setEventMessages('', lmdbInsuranceMessages($object), 'errors');
		$action = 'view';
	} elseif ($action === 'confirm_delete' && $confirm === 'yes') {
		if (!$permissionDelete || $id <= 0) accessforbidden();
		if ($object->delete($user) > 0) {
			setEventMessages($langs->trans('InsuranceContractDeleted'), null, 'mesgs');
			header('Location: '.dol_buildpath('/lmdbvehiclemanagement/insurancecontract_list.php', 1));
			exit;
		}
		setEventMessages('', lmdbInsuranceMessages($object), 'errors');
		$action = 'view';
	}
}

if ($id > 0 && empty($coverage['vehicle_ids'])) {
	$coverage['vehicle_ids'] = $object->getVehicleIds();
	$sqlCoverage = 'SELECT coverage_type, date_start, date_end FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle';
	$sqlCoverage .= ' WHERE fk_contract = '.((int) $object->id).' AND entity = '.((int) $object->entity).' ORDER BY rowid LIMIT 1';
	$resCoverage = $db->query($sqlCoverage);
	if ($resCoverage && is_object($coverageRow = $db->fetch_object($resCoverage))) {
		$coverage['coverage_type'] = (string) $coverageRow->coverage_type;
		$coverage['coverage_start'] = (int) $db->jdate($coverageRow->date_start);
		$coverage['coverage_end'] = !empty($coverageRow->date_end) ? (int) $db->jdate($coverageRow->date_end) : null;
	}
	if ($resCoverage) $db->free($resCoverage);
}
if ($coverage['coverage_start'] <= 0) $coverage['coverage_start'] = (int) $object->date_start;

$form = new Form($db);
$formfile = new FormFile($db);
$title = $id > 0 ? $object->ref : $langs->trans('NewInsuranceContract');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');

if ($action === 'delete' && $id > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('Delete'), $langs->trans('ConfirmDeleteInsuranceContract'), 'confirm_delete', '', 0, 1);
}

if ($action === 'create' || $action === 'edit') {
	if (!$permissionWrite) accessforbidden();
	if ($action === 'edit' && $id <= 0) accessforbidden();
	$ownerEntity = $id > 0 ? (int) $object->entity : (int) $conf->entity;
	$vehicleOptions = lmdbInsuranceGetVehicleOptions($db, $ownerEntity);
	print load_fiche_titre($title, '', 'shield-alt');
	if (empty($vehicleOptions)) print '<div class="warning">'.$langs->trans('InsuranceNoAccessibleVehicle').'</div>';
	if (count($coverage['vehicle_ids']) > 1) print '<div class="warning">'.$langs->trans('InsuranceSharedContractWarning', count($coverage['vehicle_ids'])).'</div>';
	lmdbInsurancePrintContractForm(
		$object,
		$form,
		$vehicleOptions,
		$coverage['vehicle_ids'],
		$coverage['coverage_type'],
		$coverage['coverage_start'],
		$coverage['coverage_end'],
		$_SERVER['PHP_SELF'],
		array('action' => $action === 'create' ? 'add' : 'update', 'id' => (int) $object->id),
		true
	);
} elseif ($id > 0) {
	$head = lmdbInsuranceContractPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('InsuranceContract'), -1, $object->picto);
	lmdbInsuranceContractPrintBanner($object);
	$company = new Societe($db);
	$companyLink = $company->fetch((int) $object->fk_soc) > 0 ? $company->getNomUrl(1) : '';
	$contactLink = '';
	if (!empty($object->fk_contact)) {
		$contact = new Contact($db);
		if ($contact->fetch((int) $object->fk_contact) > 0) $contactLink = $contact->getNomUrl(1);
	}
	print '<div class="fichecenter"><div class="fichehalfleft"><div class="underbanner clearboth"></div><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('InsuranceCompany').'</td><td>'.$companyLink.'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceContact').'</td><td>'.$contactLink.'</td></tr>';
	print '<tr><td>'.$langs->trans('InsurancePolicyNumber').'</td><td>'.dol_escape_htmltag($object->policy_number).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceCoverageFormula').'</td><td>'.dol_escape_htmltag((string) $object->coverage_formula).'</td></tr>';
	print '<tr><td>'.$langs->trans('Period').'</td><td>'.dol_print_date($object->date_start, 'day').' — '.($object->date_end ? dol_print_date($object->date_end, 'day') : $langs->trans('NoLimit')).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceRenewalMode').'</td><td>'.$langs->trans($object->renewal_mode === 'tacit' ? 'InsuranceRenewalTacit' : 'InsuranceRenewalFixed').'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceNoticeDate').'</td><td>'.($object->notice_date ? dol_print_date($object->notice_date, 'day') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceAssistancePhone').'</td><td>'.dol_escape_htmltag((string) $object->assistance_phone).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceAssistanceEmail').'</td><td>'.dol_escape_htmltag((string) $object->assistance_email).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceClaimPhone').'</td><td>'.dol_escape_htmltag((string) $object->claim_phone).'</td></tr>';
	print '<tr><td>'.$langs->trans('InsuranceClaimEmail').'</td><td>'.dol_escape_htmltag((string) $object->claim_email).'</td></tr>';
	$description = (string) $object->description;
	print '<tr><td class="tdtop">'.$langs->trans('Description').'</td><td>'.(dol_textishtml($description) ? $description : dol_htmlentitiesbr($description)).'</td></tr>';
	print '</table></div><div class="fichehalfright"><div class="underbanner clearboth"></div>';
	print load_fiche_titre($langs->trans('Vehicles'), '', 'car');
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('Vehicle').'</th><th>'.$langs->trans('InsuranceCoverageType').'</th><th>'.$langs->trans('Period').'</th></tr>';
	$sqlVehicles = 'SELECT v.rowid, v.ref, v.registration_number, v.label, cv.coverage_type, cv.date_start, cv.date_end FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle AS cv';
	$sqlVehicles .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid = cv.fk_vehicle AND v.entity = cv.entity';
	$sqlVehicles .= ' WHERE cv.fk_contract = '.((int) $object->id).' AND cv.entity = '.((int) $object->entity).' ORDER BY v.ref';
	$resVehicles = $db->query($sqlVehicles);
	$vehicleCount = 0;
	if ($resVehicles) {
		while (is_object($vehicleRow = $db->fetch_object($resVehicles))) {
			$vehicleCount++;
			$vehicleUrl = dol_buildpath('/lmdbvehiclemanagement/vehicle_card.php', 1).'?id='.((int) $vehicleRow->rowid);
			print '<tr class="oddeven"><td><a href="'.$vehicleUrl.'">'.img_picto('', 'car', 'class="pictofixedwidth"').dol_escape_htmltag(lmdbVehicleDisplayIdentifier((string) $vehicleRow->ref, (string) $vehicleRow->registration_number, (string) $vehicleRow->label)).'</a></td>';
			print '<td>'.$langs->trans($vehicleRow->coverage_type === LmdbVehicleInsuranceContract::COVERAGE_PRIMARY ? 'InsuranceCoveragePrimary' : 'InsuranceCoverageComplementary').'</td>';
			print '<td>'.dol_print_date($db->jdate($vehicleRow->date_start), 'day').' — '.(!empty($vehicleRow->date_end) ? dol_print_date($db->jdate($vehicleRow->date_end), 'day') : $langs->trans('NoLimit')).'</td></tr>';
		}
		$db->free($resVehicles);
	}
	if ($vehicleCount === 0) print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	print '</table></div></div></div>';
	print '<div class="clearboth"></div>';
	print dol_get_fiche_end();

	// Actions buttons
	print '<div class="tabsAction">';
	if ($permissionWrite) {
		print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=edit');
		if ((int) $object->status === LmdbVehicleInsuranceContract::STATUS_DRAFT) print lmdbInsuranceContractPostButton($id, 'activate', $langs->trans('Activate'));
		if ((int) $object->status === LmdbVehicleInsuranceContract::STATUS_ACTIVE) print lmdbInsuranceContractPostButton($id, 'terminate', $langs->trans('Terminate'));
	}
	if ($permissionDelete && (int) $object->status === LmdbVehicleInsuranceContract::STATUS_DRAFT) print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER['PHP_SELF'].'?id='.$id.'&action=delete&token='.newToken());
	print '</div>';

	print '<div class="fichecenter"><div class="fichehalfleft">';
	$uploadDir = getMultidirOutput($object, 'lmdbvehiclemanagement', 1);
	if (is_string($uploadDir) && $uploadDir !== '' && strpos($uploadDir, 'error-diroutput-') !== 0) {
		print $formfile->showdocuments('lmdbvehiclemanagement', dol_sanitizeFileName($object->ref), $uploadDir, $_SERVER['PHP_SELF'].'?id='.$id, 0, $permissionWrite, '', 1, 0, 0, 28, 0, '&entity='.((int) $object->entity));
	}
	$form->showLinkedObjectBlock($object);
	print '</div><div class="fichehalfright">';
	if (LmdbVehicleManagementCompatibility::isFeatureAvailable('native_agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
		$formActions = new FormActions($db);
		$formActions->showactions($object, $object->element.'@'.$object->module, 0, 1, '', 10);
	}
	print '</div></div>';
	print '<div class="clearboth"></div>';
}

llxFooter();
$db->close();

/**
 * Render a token-protected lifecycle button.
 *
 * @param int $contractId Contract id
 * @param string $action Action
 * @param string $label Label
 * @return string
 */
function lmdbInsuranceContractPostButton($contractId, $action, $label)
{
	$out = '<form class="inline-block" method="POST" action="'.dol_buildpath('/lmdbvehiclemanagement/insurancecontract_card.php', 1).'">';
	$out .= '<input type="hidden" name="token" value="'.newToken().'">';
	$out .= '<input type="hidden" name="id" value="'.((int) $contractId).'"><input type="hidden" name="action" value="'.dol_escape_htmltag($action).'">';
	$out .= '<button type="submit" class="butAction">'.dol_escape_htmltag($label).'</button>';
	$out .= '</form>';

	return $out;
}
