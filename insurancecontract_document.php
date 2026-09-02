<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php');
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');

/** @var DoliDB $db */
/** @var HookManager $hookmanager */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('companies', 'other', 'mails', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$backtopage = GETPOST('backtopage', 'alphanohtml');
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'name';
$sortorder = strtoupper(GETPOST('sortorder', 'alpha')) === 'DESC' ? 'DESC' : 'ASC';
$object = new LmdbVehicleInsuranceContract($db);
if (!isModEnabled('lmdbvehiclemanagement') || !lmdbVehicleManagementCanDo($user, 'read') || !empty($user->socid)) accessforbidden();
if ($id <= 0 || $object->fetch($id) <= 0) accessforbidden($langs->trans('RecordNotFound'));

$permissiontoread = true;
$permissiontoadd = lmdbVehicleManagementCanDo($user, 'insurance', 'write') ? 1 : 0;
$permissiontodelete = $permissiontoadd;
$upload_dirold = '';
$forceFullTextIndexation = '';
$upload_dir = getMultidirOutput($object, 'lmdbvehiclemanagement', 1);
if (!is_string($upload_dir) || $upload_dir === '' || strpos($upload_dir, 'error-diroutput-') === 0) accessforbidden($langs->trans('AccessDeniedForEntity'));
$modulepart = 'lmdbvehiclemanagement';
$hookmanager->initHooks(array('lmdbinsurancecontractdocument', 'globalcard'));
include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';
if (GETPOST('sendit', 'alpha') && empty($error)) {
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
	exit;
}

$form = new Form($db);
$formfile = new FormFile($db);
llxHeader('', $object->ref.' - '.$langs->trans('Files'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card_document');
$head = lmdbInsuranceContractPrepareHead($object);
print dol_get_fiche_head($head, 'documents', $langs->trans('InsuranceContract'), -1, $object->picto);
$filearray = dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$', $sortfield, $sortorder === 'DESC' ? SORT_DESC : SORT_ASC, 1);
$totalsize = 0;
foreach ($filearray as $file) $totalsize += (int) $file['size'];
lmdbInsuranceContractPrintBanner($object);
print '<div class="fichecenter"><div class="underbanner clearboth"></div><table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('NbOfAttachedFiles').'</td><td>'.count($filearray).'</td></tr>';
print '<tr><td>'.$langs->trans('TotalSizeOfAttachedFiles').'</td><td>'.$totalsize.' '.$langs->trans('bytes').'</td></tr></table></div>';
print dol_get_fiche_end();
$param = '&id='.$id;
$moreparam = '&entity='.((int) $object->entity);
$savingdocmask = '';
$withproject = 0;
$relativepathwithnofile = dol_sanitizeFileName($object->ref).'/';
include DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_post_headers.tpl.php';
llxFooter();
$db->close();
