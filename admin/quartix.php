<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include str_replace('..', '', $_SERVER['CONTEXT_DOCUMENT_ROOT']).'/main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/../class/lmdbvehiclequartixcron.class.php';
require_once __DIR__.'/../lib/lmdbvehiclemanagement.lib.php';

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */
$langs->loadLangs(array('admin', 'other', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
if (!LmdbVehicleQuartixConfig::can($user, 'configure')) accessforbidden();
$entity = (int) $conf->entity;
$config = new LmdbVehicleQuartixConfig($db);
$service = new LmdbVehicleQuartixService($db);
$action = GETPOST('action', 'aZ09');
$sessionKey = 'lmdbvm_qx_catalog_'.$entity;
$values = array();
foreach (array('CUSTOMER', 'USERNAME', 'PASSWORD', 'TIME_MODE', 'DURATION_UNIT') as $key) {
	$raw = GETPOST('qx_'.$key, $key === 'PASSWORD' ? 'none' : 'alphanohtml');
	$values[$key] = is_string($raw) ? $raw : '';
}
// main.inc.php enforces the native CSRF token; mutations additionally require POST.
if (in_array($action, array('save', 'test', 'associate', 'toggle'), true)) {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') accessforbidden();
	$locked = false;
	$client = null;
	try {
		if (!LmdbVehicleQuartixConfig::supported()) throw new RuntimeException('QxRequiresCrypto');
		if (!$service->lock($entity)) throw new RuntimeException('QxBusy');
		$locked = true;
		if ($action === 'save') {
			$config->save($user, $values);
			unset($_SESSION[$sessionKey]);
		} elseif ($action === 'test' || $action === 'associate') {
			// A fresh catalogue prevents stale or forged remote associations.
			$client = new LmdbVehicleQuartixClient($db, $entity);
			$catalog = $client->get('/vehicles');
			$options = array();
			foreach ($catalog as $row) {
				if (!is_array($row)) throw new RuntimeException('QxInvalidResponse');
				$remoteId = LmdbVehicleQuartixRules::id($row['VehicleID'] ?? null);
				$label = $row['RegistrationNumber'] ?? '';
				$description = $row['Description'] ?? '';
				if (!is_string($label) || !is_string($description) || $label === '' && $description === '') throw new RuntimeException('QxInvalidResponse');
				$options[$remoteId] = dol_substr(trim($label.' — '.$description), 0, 255);
			}
			$_SESSION[$sessionKey] = array('expires' => dol_now() + 900, 'options' => $options);
			if ($action === 'associate') $service->associate($user, GETPOSTINT('vehicle_id'), GETPOSTINT('remote_id'), (string) GETPOST('timezone', 'alphanohtml'), $catalog);
		} else {
			$service->setActive($user, GETPOSTINT('vehicle_id'), GETPOSTINT('active'));
		}
		setEventMessages($langs->trans($action === 'test' ? 'QxConnectionOk' : 'RecordSaved'), null, 'mesgs');
	} catch (Exception $e) {
		$message = $langs->trans(LmdbVehicleQuartixCron::safeError($e));
		if ($client !== null) $message .= ' '.dol_escape_htmltag($client->getDiagnosticMessage($langs));
		setEventMessages($message, null, 'errors');
	} finally {
		if ($locked) $service->unlock($entity);
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$form = new Form($db);
$settings = $config->load($entity, true);
$settings['PASSWORD'] = ''; // Never render the stored password, even in a hidden field.
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=lmdbvehiclemanagement">'.$langs->trans('BackToModuleList').'</a>';
llxHeader('', $langs->trans('QxTitle'));
print load_fiche_titre($langs->trans('QxTitle'), $linkback, 'technic');
print dol_get_fiche_head(lmdbVehicleManagementAdminPrepareHead(), 'quartix', $langs->trans('QxTitle'), -1, 'car');
print '<div class="info">'.$langs->trans('QxSetupHelp').'</div>';
if (!LmdbVehicleQuartixConfig::supported()) {
	print '<div class="warning">'.$langs->trans('QxRequiresCrypto').'</div>';
	print dol_get_fiche_end(); llxFooter(); $db->close(); exit;
}
if (!isModEnabled('cron')) print '<div class="warning">'.$langs->trans('RequiresCronModule').'</div>';
print '<table class="noborder centpercent"><tr class="oddeven"><td>'.$langs->trans('QxEnabled').'</td><td>'.ajax_constantonoff(LmdbVehicleQuartixConfig::PREFIX.'ENABLED').'</td></tr></table>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
print '<div class="div-table-responsive-no-min"><table class="border centpercent">';
foreach (array('CUSTOMER' => 'QxCustomer', 'USERNAME' => 'Login', 'PASSWORD' => 'Password') as $key => $label) {
	print '<tr><td class="titlefield">'.$langs->trans($label).'</td><td><input class="flat minwidth200" type="'.($key === 'PASSWORD' ? 'password' : 'text').'" name="qx_'.$key.'" value="'.dol_escape_htmltag($settings[$key]).'" autocomplete="'.($key === 'PASSWORD' ? 'new-password' : 'off').'">';
	if ($key === 'PASSWORD') print ' <span class="opacitymedium">'.$langs->trans('QxKeepPassword').'</span>';
	print '</td></tr>';
}
$timeOptions = array('' => $langs->trans('QxUnconfirmed'), 'offset' => $langs->trans('QxTimeOffset'), 'local' => $langs->trans('QxTimeLocal'));
$unitOptions = array('' => $langs->trans('QxUnconfirmed'), 'seconds' => $langs->trans('Seconds'), 'minutes' => $langs->trans('Minutes'), 'hours' => $langs->trans('Hours'));
foreach (array('TIME_MODE' => array('QxTimeMode', $timeOptions), 'DURATION_UNIT' => array('QxDurationUnit', $unitOptions)) as $key => $definition) {
	print '<tr><td>'.$langs->trans($definition[0]).'</td><td>'.$form->selectarray('qx_'.$key, $definition[1], $settings[$key], 0, 0, 0, '', 0, 0, 0, '', 'minwidth200', 1).'</td></tr>';
}
print '</table></div><p class="opacitymedium">'.$langs->trans('QxSemanticsHelp').'</p><div class="center"><button class="button button-save" type="submit">'.$langs->trans('Save').'</button></div></form>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="test"><div class="center"><button class="button" type="submit">'.$langs->trans('QxTestConnection').'</button></div></form>';

print load_fiche_titre($langs->trans('QxAssociations'), '', '');
$catalogSession = $_SESSION[$sessionKey] ?? array();
$remoteOptions = is_array($catalogSession) && ($catalogSession['expires'] ?? 0) > dol_now() && isset($catalogSession['options']) && is_array($catalogSession['options']) ? $catalogSession['options'] : array();
try {
	$localOptions = array();
	$vehicles = $service->rows('SELECT v.rowid,v.ref,v.registration_number,v.label FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v WHERE v.entity='.$entity.' AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link AS l WHERE l.fk_vehicle=v.rowid AND l.entity=v.entity) ORDER BY v.ref');
	foreach ($vehicles as $v) $localOptions[(int) $v->rowid] = trim($v->ref.' — '.$v->registration_number.' '.$v->label);
	if ($remoteOptions && $localOptions) {
		$timezones = array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers());
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="associate">';
		print '<p>'.$langs->trans('QxAssociationHelp').'</p><div class="div-table-responsive-no-min"><table class="border centpercent">';
		print '<tr><td class="titlefield">'.$langs->trans('Vehicle').'</td><td>'.$form->selectarray('vehicle_id', $localOptions, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth200', 1).'</td></tr>';
		print '<tr><td>'.$langs->trans('QxRemoteVehicle').'</td><td>'.$form->selectarray('remote_id', $remoteOptions, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth200', 1).'</td></tr>';
		print '<tr><td>'.$langs->trans('QxTimezone').'</td><td>'.$form->selectarray('timezone', $timezones, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth200', 1).'</td></tr>';
		print '</table></div><div class="center"><button class="button" type="submit">'.$langs->trans('QxAssociate').'</button></div></form>';
	}
	$mapPage = max(0, GETPOSTINT('page'));
	$links = $service->rows('SELECT l.*,v.ref FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link AS l INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid=l.fk_vehicle AND v.entity=l.entity WHERE l.entity='.$entity.' ORDER BY v.ref LIMIT 101 OFFSET '.($mapPage * 100));
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre">';
	foreach (array('Vehicle', 'QxRemoteVehicle', 'QxTimezone', 'QxShiftStart', 'Status', 'QxBackfill', 'Action') as $label) print '<td>'.$langs->trans($label).'</td>';
	print '</tr>';
	foreach (array_slice($links, 0, 100) as $link) {
		print '<tr class="oddeven"><td><a href="'.dol_buildpath('/lmdbvehiclemanagement/vehicle_quartix.php', 1).'?id='.((int) $link->fk_vehicle).'">'.dol_escape_htmltag($link->ref).'</a></td><td>'.dol_escape_htmltag($remoteOptions[(int) $link->remote_id] ?? $langs->trans('QxAssociatedVehicle')).'</td><td>'.dol_escape_htmltag($link->timezone).'</td><td>'.dol_escape_htmltag($link->shift_start).'</td><td>'.dolGetStatus($langs->trans((int) $link->active ? 'Enabled' : 'Disabled'), '', '', (int) $link->active ? 'status4' : 'status5', 5).'</td><td>'.(!empty($link->usage_cursor) ? dol_print_date($db->jdate($link->usage_cursor), 'day') : $langs->trans('QxPending')).'</td><td>';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="toggle"><input type="hidden" name="vehicle_id" value="'.((int) $link->fk_vehicle).'"><input type="hidden" name="active" value="'.((int) $link->active ? 0 : 1).'"><button class="button small" type="submit" aria-label="'.$langs->trans((int) $link->active ? 'Disable' : 'Enable').'">'.img_picto($langs->trans((int) $link->active ? 'Enabled' : 'Disabled'), (int) $link->active ? 'switch_on' : 'switch_off').'</button></form></td></tr>';
	}
	if (!$links) print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	print '</table></div>';
	if ($mapPage > 0) print '<a class="button" href="'.$_SERVER['PHP_SELF'].'?page='.($mapPage - 1).'">'.$langs->trans('Previous').'</a>';
	if (count($links) > 100) print '<a class="button" href="'.$_SERVER['PHP_SELF'].'?page='.($mapPage + 1).'">'.$langs->trans('Next').'</a>';
	print load_fiche_titre($langs->trans('QxJobs'), '', '');
	print '<p>'.$langs->trans('QxJobsHelp').' <a href="'.DOL_URL_ROOT.'/cron/list.php">'.$langs->trans('QxJobs').'</a></p>';
	$nativeJobs = $service->rows('SELECT status FROM '.MAIN_DB_PREFIX."cronjob WHERE entity=".$entity." AND classesname='/lmdbvehiclemanagement/class/lmdbvehiclequartixcron.class.php' AND objectname='LmdbVehicleQuartixCron' AND methodename IN ('positions','odometer','usage')");
	$activeJobs = array_filter($nativeJobs, static function ($job) { return (int) $job->status === 1; });
	if (count($activeJobs) !== 3) print '<div class="warning">'.$langs->trans('QxJobsInactive').'</div>';
	$jobs = $service->rows('SELECT * FROM '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_job WHERE entity=".$entity." AND job_kind IN ('positions','odometer','usage') ORDER BY job_kind");
	$jobLabels = array('positions' => 'QxPositionJob', 'odometer' => 'QxOdometerJob', 'usage' => 'QxUsageJob');
	print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('Label').'</td><td>'.$langs->trans('QxLastAttempt').'</td><td>'.$langs->trans('QxLastSuccess').'</td><td>'.$langs->trans('Error').'</td></tr>';
	foreach ($jobs as $job) print '<tr class="oddeven"><td>'.$langs->trans($jobLabels[$job->job_kind] ?? 'QxJobs').'</td><td>'.dol_print_date($db->jdate($job->last_attempt), 'dayhour').'</td><td>'.dol_print_date($db->jdate($job->last_success), 'dayhour').'</td><td>'.(!empty($job->last_error) ? dol_escape_htmltag($langs->trans($job->last_error)) : '').'</td></tr>';
	if (!$jobs) print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	print '</table>';
} catch (Exception $e) {
	print '<div class="error">'.$langs->trans(LmdbVehicleQuartixCron::safeError($e)).'</div>';
}
print dol_get_fiche_end();
llxFooter();
$db->close();
