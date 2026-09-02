<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var HookManager $hookmanager */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('companies', 'users', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$object = new LmdbVehicleInsuranceContract($db);
if (!isModEnabled('lmdbvehiclemanagement') || !lmdbVehicleManagementCanDo($user, 'read') || !empty($user->socid)) accessforbidden();
if ($id <= 0 || $object->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));
$object->socid = (int) $object->fk_soc;
$object->fetch_thirdparty();
$permissionWrite = lmdbVehicleManagementCanDo($user, 'insurance', 'write');

$hookmanager->initHooks(array('lmdbinsurancecontractcontact', 'globalcard'));
$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
if (empty($reshook)) {
	if ($action === 'addcontact') {
		if (!$permissionWrite) accessforbidden();
		$contactId = GETPOSTINT('userid') ?: GETPOSTINT('contactid');
		$typeId = GETPOST('typecontact', 'aZ09') ?: GETPOST('type', 'aZ09');
		$result = $object->add_contact($contactId, $typeId, GETPOST('source', 'aZ09'));
		if ($result >= 0) {
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
			exit;
		}
		if ($object->error === 'DB_ERROR_RECORD_ALREADY_EXISTS') {
			$langs->load('errors');
			setEventMessages($langs->trans('ErrorThisContactIsAlreadyDefinedAsThisType'), null, 'errors');
		} else {
			lmdbVehicleManagementSetObjectErrors($object);
		}
	} elseif ($action === 'swapstatut') {
		if (!$permissionWrite) accessforbidden();
		if ($object->swapContactStatus(GETPOSTINT('ligne')) < 0) lmdbVehicleManagementSetObjectErrors($object);
	} elseif ($action === 'deletecontact') {
		if (!$permissionWrite) accessforbidden();
		if ($object->delete_contact(GETPOSTINT('lineid')) >= 0) {
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
			exit;
		}
		lmdbVehicleManagementSetObjectErrors($object);
	}
}

$form = new Form($db);
$formcompany = new FormCompany($db);
$formother = new FormOther($db);
$contactstatic = new Contact($db);
$userstatic = new User($db);
$usercancreate = $permissionWrite;
$permissiontoadd = $permissionWrite;
$socid = (int) $object->fk_soc;

llxHeader('', $object->ref.' - '.$langs->trans('ContactsAddresses'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card_contact');
$head = lmdbInsuranceContractPrepareHead($object);
print dol_get_fiche_head($head, 'contacts', $langs->trans('InsuranceContract'), -1, $object->picto);
lmdbInsuranceContractPrintBanner($object);
print '<div class="fichecenter"><div class="underbanner clearboth"></div>';
$dirtpls = array_merge($conf->modules_parts['tpl'], array('/core/tpl'));
foreach ($dirtpls as $reldir) {
	$res = @include dol_buildpath($reldir.'/contacts.tpl.php');
	if ($res) break;
}
print '</div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
