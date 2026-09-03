<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/lmdbvehiclemanagement/lib/lmdbvehiclemanagement.lib.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsuranceconfig.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecron.class.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('admin', 'users', 'groups', 'mails', 'cron', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (empty($user->admin)) accessforbidden();

$action = GETPOST('action', 'aZ09');
$config = new LmdbVehicleInsuranceConfig($db);
$entity = (int) $conf->entity;

if ($action === 'save') {
	$recipientUsers = GETPOST('recipient_users', 'array:int');
	$recipientGroups = GETPOST('recipient_groups', 'array:int');
	$assignmentTypes = GETPOST('assignment_types', 'array:alpha');
	$beforeDays = GETPOST('before_days', 'array:int');
	$recipientUsers = is_array($recipientUsers) ? array_map('intval', $recipientUsers) : array();
	$recipientGroups = is_array($recipientGroups) ? array_map('intval', $recipientGroups) : array();
	$assignmentTypes = is_array($assignmentTypes) ? array_values(array_intersect(array('driver', 'custodian', 'pool'), $assignmentTypes)) : array();
	$allowedDays = array(90, 60, 45, 30, 15, 7, 1);
	$beforeDays = is_array($beforeDays) ? array_values(array_intersect($allowedDays, array_map('intval', $beforeDays))) : array();
	$overdueRepeat = GETPOSTINT('overdue_repeat');
	$reviewRepeat = GETPOSTINT('review_repeat');
	$requestTemplate = GETPOSTINT('request_template');
	$reviewTemplate = GETPOSTINT('review_template');
	$error = 0;
	$db->begin();
	if ($config->saveRecipients($recipientUsers, $recipientGroups, $entity, $user, false) < 0) {
		$error++;
	}
	$values = array(
		LmdbVehicleInsuranceConfig::CONST_ASSIGNMENT_TYPES => json_encode($assignmentTypes),
		LmdbVehicleInsuranceConfig::CONST_BEFORE_DAYS => json_encode($beforeDays),
		LmdbVehicleInsuranceConfig::CONST_OVERDUE_REPEAT => in_array($overdueRepeat, array(1, 3, 7, 14, 30), true) ? (string) $overdueRepeat : '7',
		LmdbVehicleInsuranceConfig::CONST_REVIEW_REPEAT => in_array($reviewRepeat, array(1, 3, 7, 14), true) ? (string) $reviewRepeat : '3',
		LmdbVehicleInsuranceConfig::CONST_REQUEST_TEMPLATE => (string) $requestTemplate,
		LmdbVehicleInsuranceConfig::CONST_REVIEW_TEMPLATE => (string) $reviewTemplate,
	);
	foreach ($values as $name => $value) {
		if (!is_string($value) || dolibarr_set_const($db, $name, $value, 'chaine', 0, '', $entity) <= 0) {
			$error++;
		}
	}
	if ($error) {
		$db->rollback();
		setEventMessages($db->lasterror(), null, 'errors');
	} else {
		$db->commit();
		setEventMessages($langs->trans('SettingsSaved'), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'send_test') {
	$cron = new LmdbVehicleInsuranceCron($db);
	if ($cron->sendTest($user) > 0) setEventMessages($langs->trans('InsuranceTestEmailSent'), null, 'mesgs');
	else setEventMessages($langs->trans($cron->error), $cron->errors, 'errors');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

/** @return array<int,string> */
function lmdbInsuranceAdminOptions($db, $sql, $idField, $labelFields)
{
	$options = array();
	$resql = $db->query($sql);
	if (!$resql) return $options;
	while (is_object($row = $db->fetch_object($resql))) {
		$parts = array();
		foreach ($labelFields as $field) {
			if (isset($row->{$field}) && trim((string) $row->{$field}) !== '') $parts[] = trim((string) $row->{$field});
		}
		$options[(int) $row->{$idField}] = implode(' ', $parts);
	}
	$db->free($resql);

	return $options;
}

$userOptions = lmdbInsuranceAdminOptions($db, 'SELECT rowid, firstname, lastname, login, email FROM '.MAIN_DB_PREFIX.'user WHERE statut = 1 AND email IS NOT NULL AND email <> \'\' AND entity IN ('.getEntity('user').') ORDER BY lastname, firstname, login', 'rowid', array('firstname', 'lastname', 'login', 'email'));
$groupOptions = lmdbInsuranceAdminOptions($db, 'SELECT rowid, nom FROM '.MAIN_DB_PREFIX.'usergroup WHERE entity IN ('.getEntity('usergroup').') ORDER BY nom', 'rowid', array('nom'));
$requestTemplateOptions = lmdbInsuranceAdminOptions($db, 'SELECT rowid, label FROM '.MAIN_DB_PREFIX."c_email_templates WHERE type_template = '".$db->escape(LmdbVehicleInsuranceConfig::REQUEST_TEMPLATE_TYPE)."' AND active = 1 AND entity IN (0, ".$entity.') ORDER BY position, label', 'rowid', array('label'));
$reviewTemplateOptions = lmdbInsuranceAdminOptions($db, 'SELECT rowid, label FROM '.MAIN_DB_PREFIX."c_email_templates WHERE type_template = '".$db->escape(LmdbVehicleInsuranceConfig::REVIEW_TEMPLATE_TYPE)."' AND active = 1 AND entity IN (0, ".$entity.') ORDER BY position, label', 'rowid', array('label'));
$cronStatus = null;
$resql = $db->query('SELECT status, datelastresult, lastresult FROM '.MAIN_DB_PREFIX."cronjob WHERE module_name = 'lmdbvehiclemanagement' AND methodename = 'sendCertificateReminders' AND entity IN (0, ".$entity.') ORDER BY entity DESC, rowid DESC LIMIT 1');
if ($resql) {
	$row = $db->fetch_object($resql);
	$cronStatus = is_object($row) ? array('status' => (int) $row->status, 'last_result_date' => !empty($row->datelastresult) ? (int) $db->jdate($row->datelastresult) : 0, 'last_result' => (string) $row->lastresult) : null;
	$db->free($resql);
}
$form = new Form($db);
$title = $langs->trans('InsuranceSettings');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=lmdbvehiclemanagement">'.img_picto('', 'back', 'class="pictofixedwidth"').$langs->trans('BackToModuleList').'</a>';

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lmdbvehiclemanagement page-admin');
print load_fiche_titre($title, $linkback, 'shield-alt');
$head = lmdbVehicleManagementAdminPrepareHead();
print dol_get_fiche_head($head, 'insurance', $title, -1, 'car');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('InsuranceReminderSettings').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('InsuranceRemindersEnabled').'</td><td>'.ajax_constantonoff(LmdbVehicleInsuranceConfig::CONST_ENABLED).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('InsuranceRecipientUsers').'</td><td>'.$form->multiselectarray('recipient_users', $userOptions, $config->getRecipientUserIds($entity), 0, 0, 'minwidth500').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('InsuranceRecipientGroups').'</td><td>'.$form->multiselectarray('recipient_groups', $groupOptions, $config->getRecipientGroupIds($entity), 0, 0, 'minwidth500').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('InsuranceIncludeAssignees').'</td><td>'.ajax_constantonoff(LmdbVehicleInsuranceConfig::CONST_INCLUDE_ASSIGNEES).'</td></tr>';
$assignmentOptions = array('driver' => $langs->trans('AssignmentTypeDriver'), 'custodian' => $langs->trans('AssignmentTypeCustodian'), 'pool' => $langs->trans('AssignmentTypePool'));
print '<tr class="oddeven"><td>'.$langs->trans('InsuranceAssignmentTypes').'</td><td>'.$form->multiselectarray('assignment_types', $assignmentOptions, LmdbVehicleInsuranceConfig::getAssignmentTypes(), 0, 0, 'minwidth300').'</td></tr>';
$beforeOptions = array(90 => 'J-90', 60 => 'J-60', 45 => 'J-45', 30 => 'J-30', 15 => 'J-15', 7 => 'J-7', 1 => 'J-1');
print '<tr class="oddeven"><td>'.$langs->trans('InsuranceBeforeExpiryDays').'</td><td>'.$form->multiselectarray('before_days', $beforeOptions, LmdbVehicleInsuranceConfig::getBeforeDays(), 0, 0, 'minwidth300').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('InsuranceOverdueRepeat').'</td><td>'.$form->selectarray('overdue_repeat', array(1 => '1', 3 => '3', 7 => '7', 14 => '14', 30 => '30'), getDolGlobalInt(LmdbVehicleInsuranceConfig::CONST_OVERDUE_REPEAT, 7), 0, 0, 0, '', 1).' '.$langs->trans('days').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('InsuranceReviewRepeat').'</td><td>'.$form->selectarray('review_repeat', array(1 => '1', 3 => '3', 7 => '7', 14 => '14'), getDolGlobalInt(LmdbVehicleInsuranceConfig::CONST_REVIEW_REPEAT, 3), 0, 0, 0, '', 1).' '.$langs->trans('days').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('InsuranceRequestEmailTemplate').'</td><td>'.$form->selectarray('request_template', $requestTemplateOptions, getDolGlobalInt(LmdbVehicleInsuranceConfig::CONST_REQUEST_TEMPLATE), 1, 0, 0, '', 1, 0, 0, '', 'minwidth500', 1).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('InsuranceReviewEmailTemplate').'</td><td>'.$form->selectarray('review_template', $reviewTemplateOptions, getDolGlobalInt(LmdbVehicleInsuranceConfig::CONST_REVIEW_TEMPLATE), 1, 0, 0, '', 1, 0, 0, '', 'minwidth500', 1).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('CronList').'</td><td>';
if (!isModEnabled('cron')) {
	print dolGetStatus($langs->trans('Unavailable'), '', '', 'status6', 5).' — '.$langs->trans('RequiresCronModule');
} elseif (is_array($cronStatus)) {
	print dolGetStatus($langs->trans($cronStatus['status'] === 1 ? 'Enabled' : 'Disabled'), '', '', $cronStatus['status'] === 1 ? 'status4' : 'status6', 5);
	if ($cronStatus['last_result_date'] > 0) print ' — '.$langs->trans('LastResult').' : '.dol_print_date($cronStatus['last_result_date'], 'dayhour').' ('.dol_escape_htmltag($cronStatus['last_result']).')';
} else {
	print dolGetStatus($langs->trans('NoRecordFound'), '', '', 'status6', 5);
}
print ' — <a href="'.DOL_URL_ROOT.'/cron/list.php?search_module_name=lmdbvehiclemanagement">'.$langs->trans('InsuranceOpenScheduledJobs').'</a></td></tr>';
print '</table></div>';
print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="send_test">';
print '<div class="center"><input type="submit" class="button" value="'.$langs->trans('InsuranceSendTestEmail').'"></div></form>';
print dol_get_fiche_end();
llxFooter();
$db->close();
