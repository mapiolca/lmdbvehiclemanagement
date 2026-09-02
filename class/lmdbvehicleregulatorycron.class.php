<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleregulatoryservice.class.php');

/** Daily regulatory deadline synchronization and reminders. */
class LmdbVehicleRegulatoryCron
{
	private const AGENDA_EVENT_CODE = 'AC_LMDB_REGULATORY_DUE';
	private const AGENDA_FALLBACK_TYPE_CODE = 'AC_OTH_AUTO';

	/** @var DoliDB */ public $db;
	/** @var string */ public $error = '';
	/** @var array<int,string> */ public $errors = array();
	/** @var string */ public $output = '';
	/** @var array{id:int,code:string}|null */ private $agendaType = null;
	/** @var bool */ private $agendaTypeResolved = false;

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Run the daily synchronization.
	 *
	 * A manual execution confirmed from the native Scheduled Jobs card is a
	 * forced test: due reminders are sent again without consuming or replacing
	 * the automatic run's daily idempotency key.
	 *
	 * @param int $force 1 to force reminder sends during a direct test call
	 * @return int<-1,0>
	 */
	public function runDaily($force = 0)
	{
		global $conf, $langs, $user;

		if (!isModEnabled('lmdbvehiclemanagement')) {
			$this->output = 'Regulatory controls disabled';
			return 0;
		}
		$langs->loadLangs(array('agenda', 'mails', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
		$force = $this->isForcedExecution($force);
		$forcedRunKey = $force ? sha1((string) microtime(true).':'.(string) dol_now().':'.(string) getmypid()) : '';
		$entity = (int) $conf->entity;
		$lockName = 'lmdbvm_regulatory_cron_'.sha1((string) $entity);
		$lockResult = $this->acquireCronLock($lockName);
		if ($lockResult < 0) return -1;
		if ($lockResult === 0) {
			$this->output = 'Regulatory controls already being processed for this entity';
			return 0;
		}
		try {
			$service = new LmdbVehicleRegulatoryService($this->db);
			if ($service->synchronizeEntityRequirements($entity, $user) < 0) {
				$this->error = $service->error;
				return -1;
			}
			if ($this->removeObsoleteAgendaEvents($entity, $user) < 0) return -1;

			$sql = 'SELECT req.rowid, req.entity, req.fk_vehicle, req.fk_actioncomm, req.retained_due_date, req.status, rr.label AS rule_label,';
			$sql .= ' v.ref AS vehicle_ref, v.registration_number, v.label AS vehicle_label';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement AS req';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule AS rr ON rr.rowid = req.fk_rule';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid = req.fk_vehicle AND v.entity = req.entity';
			$sql .= ' WHERE req.entity = '.$entity.' AND req.active = 1 AND req.retained_due_date IS NOT NULL AND v.status <> 4';
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$events = 0;
			$sent = 0;
			$failed = 0;
			while (is_object($row = $this->db->fetch_object($resql))) {
				$dueDate = $this->db->jdate($row->retained_due_date);
				if ($dueDate <= 0) continue;
				$eventResult = $this->synchronizeAgendaEvent($row, $dueDate, $user);
				if ($eventResult > 0) $events++;
				elseif ($eventResult < 0) $failed++;
				if (getDolGlobalInt('LMDBVEHICLEMANAGEMENT_REGULATORY_REMINDERS_ENABLED')) {
					$result = $this->sendDueReminders($row, $dueDate, $entity, $forcedRunKey);
					if ($result > 0) $sent += $result;
					elseif ($result < 0) $failed++;
				}
			}
			$this->db->free($resql);
			$this->output = 'Execution mode: '.($force ? 'forced' : 'automatic').'; regulatory events synchronized: '.$events.'; reminders sent: '.$sent.'; failures: '.$failed;
			return $failed > 0 ? -1 : 0;
		} finally {
			$this->releaseCronLock($lockName);
		}
	}

	/**
	 * Detect a direct forced call or the native Scheduled Jobs manual run.
	 *
	 * Dolibarr v20-v24 invokes cron methods without a force argument from
	 * cron/card.php, while keeping the confirmed action in the request.
	 *
	 * @param int $force Explicit direct-call flag
	 * @return bool
	 */
	private function isForcedExecution($force)
	{
		if ((int) $force > 0) return true;
		if (PHP_SAPI === 'cli') {
			global $argv;

			return is_array($argv) && in_array('--force', $argv, true);
		}

		return GETPOST('action', 'aZ09') === 'confirm_execute' && GETPOST('confirm', 'alpha') === 'yes';
	}

	/** @param string $lockName Advisory lock name @return int<-1,1> */
	private function acquireCronLock($lockName)
	{
		$resql = $this->db->query("SELECT GET_LOCK('".$this->db->escape($lockName)."', 0) AS lock_acquired");
		if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
		$row = $this->db->fetch_object($resql); $this->db->free($resql);
		return is_object($row) ? (int) $row->lock_acquired : -1;
	}

	/** @param string $lockName Advisory lock name @return void */
	private function releaseCronLock($lockName)
	{
		$resql = $this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($lockName)."') AS lock_released");
		if ($resql) $this->db->free($resql);
		else dol_syslog(__METHOD__.': '.$this->db->lasterror(), LOG_WARNING);
	}

	/**
	 * Resolve both values required by ActionComm::create().
	 *
	 * The module type is preferred. The native automatic type keeps the cron
	 * operational when code has been deployed before the module initialization
	 * has registered the custom dictionary row.
	 *
	 * @return array{id:int,code:string}|null
	 */
	private function resolveAgendaType()
	{
		global $langs;

		if ($this->agendaTypeResolved) return $this->agendaType;
		$this->agendaTypeResolved = true;
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/cactioncomm.class.php';
		foreach (array(self::AGENDA_EVENT_CODE, self::AGENDA_FALLBACK_TYPE_CODE) as $typeCode) {
			$type = new CActionComm($this->db);
			$result = $type->fetch($typeCode);
			if ($result > 0 && (int) $type->id > 0 && (string) $type->code !== '') {
				$this->agendaType = array('id' => (int) $type->id, 'code' => (string) $type->code);
				return $this->agendaType;
			}
			if ($result < 0) {
				$this->error = $type->error;
				return null;
			}
		}

		$this->error = $langs->trans('RegulatoryAgendaTypeMissing', self::AGENDA_EVENT_CODE, self::AGENDA_FALLBACK_TYPE_CODE);
		return null;
	}

	/** @param object $row Requirement row @param int $dueDate Due date @param User $user Cron user @return int<-1,1> */
	private function synchronizeAgendaEvent($row, $dueDate, User $user)
	{
		global $langs;

		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		$agendaType = $this->resolveAgendaType();
		if ($agendaType === null) return -1;
		$this->db->begin();
		$event = new ActionComm($this->db);
		$existing = !empty($row->fk_actioncomm) ? $event->fetch((int) $row->fk_actioncomm) : 0;
		$isNew = $existing <= 0;
		if ($isNew) $event = new ActionComm($this->db);
		$vehicle = trim((string) $row->registration_number) !== '' ? (string) $row->registration_number : (string) $row->vehicle_ref;
		$ruleLabel = $langs->trans((string) $row->rule_label);
		$event->type_id = $agendaType['id'];
		$event->type_code = $agendaType['code'];
		$event->code = self::AGENDA_EVENT_CODE;
		$event->label = $langs->trans('RegulatoryDueAgendaTitle', $ruleLabel, $vehicle);
		$event->note_private = $langs->trans('RegulatoryDueAgendaDescription', $ruleLabel, $vehicle, dol_print_date($dueDate, 'day'));
		$event->datep = $dueDate;
		$event->datef = $dueDate;
		$event->entity = (int) $row->entity;
		$event->percentage = -1;
		$event->userownerid = (int) $user->id;
		$event->elementtype = 'lmdbvehicle@lmdbvehiclemanagement';
		$event->fk_element = (int) $row->fk_vehicle;
		$result = $isNew ? $event->create($user) : $event->update($user);
		if ($result <= 0) {
			$this->error = $event->error;
			$this->errors = $event->errors;
			$this->db->rollback();
			return -1;
		}
		if ($isNew) {
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement SET fk_actioncomm = '.((int) $event->id);
			$sql .= ' WHERE rowid = '.((int) $row->rowid).' AND entity = '.((int) $row->entity);
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
		}
		$this->db->commit();
		return 1;
	}

	/** Remove the generated deadline event when its requirement no longer has a date or its vehicle is sold. @return int<-1,1> */
	private function removeObsoleteAgendaEvents($entity, User $user)
	{
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		$sql = 'SELECT req.rowid, req.fk_vehicle, req.fk_actioncomm FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement AS req';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid = req.fk_vehicle AND v.entity = req.entity';
		$sql .= ' WHERE req.entity = '.((int) $entity).' AND req.fk_actioncomm IS NOT NULL';
		$sql .= ' AND (req.active = 0 OR req.retained_due_date IS NULL OR v.status = 4)';
		$resql = $this->db->query($sql);
		if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
		while (is_object($row = $this->db->fetch_object($resql))) {
			$this->db->begin();
			$event = new ActionComm($this->db);
			if ($event->fetch((int) $row->fk_actioncomm) > 0) {
				$isOwnedDeadline = (string) $event->code === self::AGENDA_EVENT_CODE
					&& (string) $event->elementtype === 'lmdbvehicle@lmdbvehiclemanagement'
					&& (int) $event->fk_element === (int) $row->fk_vehicle;
				if ($isOwnedDeadline && $event->delete($user) < 0) {
					$this->error = $event->error;
					$this->db->rollback();
					$this->db->free($resql);
					return -1;
				}
			}
			$sqlUpdate = 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement SET fk_actioncomm = NULL';
			$sqlUpdate .= ' WHERE rowid = '.((int) $row->rowid).' AND entity = '.((int) $entity);
			if (!$this->db->query($sqlUpdate)) { $this->error = $this->db->lasterror(); $this->db->rollback(); $this->db->free($resql); return -1; }
			$this->db->commit();
		}
		$this->db->free($resql);

		return 1;
	}

	/**
	 * @param object $row Requirement row
	 * @param int $dueDate Due date
	 * @param int $entity Entity
	 * @param string $forcedRunKey Non-empty key for a forced manual execution
	 * @return int
	 */
	private function sendDueReminders($row, $dueDate, $entity, $forcedRunKey = '')
	{
		$today = dol_mktime(0, 0, 0, (int) dol_print_date(dol_now(), '%m'), (int) dol_print_date(dol_now(), '%d'), (int) dol_print_date(dol_now(), '%Y'));
		$remaining = (int) floor(($dueDate - $today) / 86400);
		$horizons = json_decode(getDolGlobalString('LMDBVEHICLEMANAGEMENT_REGULATORY_REMINDER_HORIZONS', '[90,60,30,7,0]'), true);
		$horizons = is_array($horizons) ? array_values(array_unique(array_map('intval', $horizons))) : array(90, 60, 30, 7, 0);
		$dailyOverdueReminder = $remaining < 0 && getDolGlobalInt('LMDBVEHICLEMANAGEMENT_REGULATORY_DAILY_OVERDUE_REMINDERS') > 0;
		if (!in_array($remaining, $horizons, true) && !$dailyOverdueReminder) return 0;
		$template = $this->fetchTemplate(getDolGlobalInt('LMDBVEHICLEMANAGEMENT_REGULATORY_REMINDER_TEMPLATE'));
		if ($template === null) return -1;
		$recipients = $this->getRecipients((int) $row->fk_vehicle, $entity);
		if (empty($recipients)) return 0;
		$sent = 0;
		global $langs;
		$ruleLabel = $langs->trans((string) $row->rule_label);
		foreach ($recipients as $recipient) {
			$email = $recipient['email'];
			$keySource = ((int) $row->rowid).':'.$remaining.':'.$email.':'.dol_print_date($dueDate, '%Y-%m-%d');
			if ($forcedRunKey !== '') $keySource .= ':forced:'.$forcedRunKey;
			$key = sha1($keySource);
			$sql = 'INSERT IGNORE INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_reminder_log';
			$sql .= ' (entity, reminder_key, fk_requirement, horizon_days, recipient_type, recipient_id, due_date_snapshot, recipient_email, status, date_creation) VALUES (';
			$sql .= $entity.", '".$this->db->escape($key)."', ".((int) $row->rowid).", ".$remaining.", 'user', ".((int) $recipient['id']).", '".$this->db->idate($dueDate)."', '".$this->db->escape($email)."', 0, '".$this->db->idate(dol_now())."')";
			$resql = $this->db->query($sql);
			if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
			// A persisted attempt is the idempotency boundary. Failed sends remain
			// visible in the log and require an explicit retry by an administrator.
			if ($this->db->affected_rows($resql) === 0) continue;
			$vehicle = trim((string) $row->registration_number) !== '' ? (string) $row->registration_number : (string) $row->vehicle_ref;
			$replacements = array(
				'__RECIPIENT_NAME__' => $recipient['name'],
				'__VEHICLE_REF__' => $vehicle,
				'__VEHICLE_LABEL__' => (string) $row->vehicle_label,
				'__CONTROL_LABEL__' => $ruleLabel,
				'__CONTROL_DUE_DATE__' => dol_print_date($dueDate, 'day'),
				'__CONTROL_URL__' => dol_buildpath('/lmdbvehiclemanagement/vehicle_regulatory.php', 2).'?id='.((int) $row->fk_vehicle),
			);
			$result = $this->sendMail($email, $template, $replacements);
			$status = $result > 0 ? 1 : -1;
			$update = 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_reminder_log SET status = '.$status.", sent_at = '".$this->db->idate(dol_now())."', error_message = ";
			$update .= $result > 0 ? 'NULL' : "'".$this->db->escape($this->error)."'";
			$update .= " WHERE entity = ".$entity." AND reminder_key = '".$this->db->escape($key)."'";
			if (!$this->db->query($update)) { $this->error = $this->db->lasterror(); return -1; }
			if ($result > 0) $sent++;
		}
		return $sent;
	}

	/**
	 * Resolve configured users, groups and the optional assigned driver.
	 *
	 * The user id remains attached to its current email and display name so the
	 * reminder log can identify the exact Dolibarr account used for each send.
	 * When several accounts share an address, the first configured account wins
	 * and only one message is sent to that mailbox.
	 *
	 * @param int $vehicleId Vehicle id
	 * @param int $entity Entity id
	 * @return array<string,array{id:int,name:string,email:string}>
	 */
	private function getRecipients($vehicleId, $entity)
	{
		$userIds = json_decode(getDolGlobalString('LMDBVEHICLEMANAGEMENT_REGULATORY_RECIPIENT_USERS', '[]'), true);
		$userIds = is_array($userIds) ? array_map('intval', $userIds) : array();
		$groupIds = json_decode(getDolGlobalString('LMDBVEHICLEMANAGEMENT_REGULATORY_RECIPIENT_GROUPS', '[]'), true);
		$groupIds = is_array($groupIds) ? array_map('intval', $groupIds) : array();
		if (!empty($groupIds)) {
			$resql = $this->db->query('SELECT DISTINCT fk_user FROM '.MAIN_DB_PREFIX.'usergroup_user WHERE entity = '.((int) $entity).' AND fk_usergroup IN ('.implode(',', $groupIds).')');
			if ($resql) { while (is_object($row = $this->db->fetch_object($resql))) $userIds[] = (int) $row->fk_user; $this->db->free($resql); }
		}
		if (getDolGlobalInt('LMDBVEHICLEMANAGEMENT_REGULATORY_INCLUDE_DRIVER')) {
			$sql = 'SELECT fk_user_driver FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_assignment WHERE entity = '.$entity.' AND fk_vehicle = '.$vehicleId.' AND status = 1';
			$sql .= ' AND date_start <= NOW() AND (date_end IS NULL OR date_end >= NOW()) ORDER BY date_start DESC, rowid DESC LIMIT 1';
			$resql = $this->db->query($sql);
			if ($resql && is_object($row = $this->db->fetch_object($resql))) $userIds[] = (int) $row->fk_user_driver;
			if ($resql) $this->db->free($resql);
		}
		$userIds = array_values(array_unique(array_filter($userIds)));
		if (empty($userIds)) return array();
		$recipients = array();
		$sql = 'SELECT rowid, email, firstname, lastname, login FROM '.MAIN_DB_PREFIX.'user WHERE rowid IN ('.implode(',', $userIds).") AND statut = 1 AND email IS NOT NULL AND email <> ''";
		$sql .= ' ORDER BY FIELD(rowid, '.implode(',', $userIds).')';
		$resql = $this->db->query($sql);
		if (!$resql) return array();
		while (is_object($row = $this->db->fetch_object($resql))) {
			$email = trim((string) $row->email);
			if ($email === '' || !isValidEmail($email)) continue;
			$emailKey = strtolower($email);
			if (isset($recipients[$emailKey])) continue;
			$name = trim((string) $row->firstname.' '.(string) $row->lastname);
			$recipients[$emailKey] = array(
				'id' => (int) $row->rowid,
				'name' => $name !== '' ? $name : (string) $row->login,
				'email' => $email,
			);
		}
		$this->db->free($resql);
		return $recipients;
	}

	/** @param int $templateId @return array{subject:string,content:string}|null */
	private function fetchTemplate($templateId)
	{
		global $conf;
		$sql = 'SELECT topic, content FROM '.MAIN_DB_PREFIX.'c_email_templates WHERE rowid = '.((int) $templateId);
		$sql .= " AND type_template = 'lmdbvehicle_regulatory_reminder' AND active = 1 AND entity IN (0, ".((int) $conf->entity).')';
		$resql = $this->db->query($sql);
		if (!$resql) { $this->error = $this->db->lasterror(); return null; }
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($row)) { $this->error = 'RegulatoryReminderEmailTemplateMissing'; return null; }
		return array('subject' => (string) $row->topic, 'content' => (string) $row->content);
	}

	/** @param string $email @param array{subject:string,content:string} $template @param array<string,string> $replacements @return int<-1,1> */
	private function sendMail($email, $template, $replacements)
	{
		require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		if (!isValidEmail($email)) { $this->error = 'ErrorBadEMail'; return -1; }
		$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
		if ($from === '') { $this->error = 'RegulatorySenderEmailMissing'; return -1; }
		$subject = html_entity_decode(strtr($template['subject'], $replacements), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$mail = new CMailFile($subject, $email, $from, strtr($template['content'], $replacements), array(), array(), array(), '', '', 0, -1);
		if (!$mail->sendfile()) { $this->error = is_string($mail->error) ? $mail->error : 'RegulatoryEmailSendFailed'; return -1; }
		return 1;
	}
}
