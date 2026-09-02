<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleconsumptionimport.class.php');

/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('main', 'imports', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');
if (!isModEnabled('lmdbvehiclemanagement') || !lmdbVehicleManagementCanDo($user, 'consumption', 'import') || !empty($user->socid)) accessforbidden();
$action = GETPOST('action', 'aZ09');
if ($action === 'import') {
	$upload = isset($_FILES['import_file']) && is_array($_FILES['import_file']) ? $_FILES['import_file'] : array();
	$tmpName = isset($upload['tmp_name']) ? (string) $upload['tmp_name'] : '';
	$errorCode = isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;
	$name = isset($upload['name']) ? dol_sanitizeFileName((string) $upload['name']) : '';
	$mime = '';
	if ($errorCode === UPLOAD_ERR_OK && is_uploaded_file($tmpName) && extension_loaded('fileinfo')) {
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = $finfo !== false ? (string) finfo_file($finfo, $tmpName) : '';
		if ($finfo !== false) finfo_close($finfo);
	}
	$allowedMimes = array('text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel');
	if ($errorCode !== UPLOAD_ERR_OK || !is_uploaded_file($tmpName) || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'csv' || !in_array($mime, $allowedMimes, true)) {
		setEventMessages($langs->trans('ConsumptionImportInvalidFile'), null, 'errors');
	} else {
		$importer = new LmdbVehicleConsumptionImport($db);
		$result = $importer->importFile($tmpName, $user);
		if ($result < 0) lmdbVehicleManagementSetObjectErrors($importer);
		else {
			setEventMessages($langs->trans('ConsumptionImportCompleted', $result), $importer->errors, !empty($importer->errors) ? 'warnings' : 'mesgs');
			header('Location: '.dol_buildpath('/lmdbvehiclemanagement/consumption_list.php', 1));
			exit;
		}
	}
}

llxHeader('', $langs->trans('ConsumptionImport'), '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-card');
print load_fiche_titre($langs->trans('ConsumptionImport'), '', 'file-import');
print '<div class="info">'.$langs->trans('ConsumptionImportHelp').'</div>';
print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="import">';
print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield"><tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('File').'</td><td><input type="file" class="flat" name="import_file" accept=".csv,text/csv"></td></tr></table></div>';
print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Import').'"> &nbsp; <a class="button button-cancel" href="'.dol_buildpath('/lmdbvehiclemanagement/consumption_list.php', 1).'">'.$langs->trans('Cancel').'</a></div></form>';
llxFooter();
$db->close();
