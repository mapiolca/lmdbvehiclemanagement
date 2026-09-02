<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecertificate.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsuranceconfig.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementrules.class.php');

/** Daily insurance certificate reminders. */
class LmdbVehicleInsuranceCron
{
	/** @var DoliDB */ public $db;
	/** @var string */ public $error = '';
	/** @var array<int,string> */ public $errors = array();
	/** @var string */ public $output = '';

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Send due certificate and review reminders.
	 *
	 * @return int<-1,0>
	 */
	public function sendCertificateReminders()
	{
		global $conf, $langs;

		if (!isModEnabled('lmdbvehiclemanagement') || !getDolGlobalInt(LmdbVehicleInsuranceConfig::CONST_ENABLED)) {
			$this->output = 'Insurance reminders disabled';
			return 0;
		}
		$langs->loadLangs(array('mails', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
		$entity = (int) $conf->entity;
		$today = $this->dayStart(dol_now());
		$sql = 'SELECT cv.fk_contract, cv.fk_vehicle, cv.date_start AS coverage_start, v.ref AS vehicle_ref, v.registration_number, v.label AS vehicle_label';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract_vehicle AS cv';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_contract AS c ON c.rowid = cv.fk_contract AND c.entity = cv.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid = cv.fk_vehicle AND v.entity = cv.entity';
		$sql .= ' WHERE cv.entity = '.$entity.' AND c.status = '.LmdbVehicleInsuranceContract::STATUS_ACTIVE.' AND v.status <> 4';
		$sql .= " AND cv.date_start <= '".$this->db->idate($today)."' AND (cv.date_end IS NULL OR cv.date_end >= '".$this->db->idate($today)."')";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$sent = 0;
		$failed = 0;
		while (is_object($row = $this->db->fetch_object($resql))) {
			$contract = new LmdbVehicleInsuranceContract($this->db);
			if ($contract->fetch((int) $row->fk_contract) <= 0) {
				$failed++;
				continue;
			}
			$certificate = LmdbVehicleInsuranceCertificate::getApplicable($this->db, (int) $contract->id, (int) $row->fk_vehicle);
			$due = $this->getDueReminder($certificate, (int) $this->db->jdate($row->coverage_start), $today);
			if ($due === null) {
				continue;
			}
			$result = $this->sendReminder($contract, $certificate, (int) $row->fk_vehicle, (string) $row->vehicle_ref, (string) $row->registration_number, (string) $row->vehicle_label, $due, $entity);
			if ($result > 0) {
				$sent++;
			} elseif ($result < 0) {
				$failed++;
			}
		}
		$this->db->free($resql);
		$this->output = 'Insurance reminders sent: '.$sent.'; failures: '.$failed;

		return $failed > 0 ? -1 : 0;
	}

	/**
	 * Send the request template to the current administrator.
	 *
	 * @param User $user Recipient
	 * @return int<-1,1>
	 */
	public function sendTest(User $user)
	{
		if (trim((string) $user->email) === '') {
			$this->error = 'InsuranceTestRecipientEmailMissing';
			return -1;
		}
		$template = $this->fetchTemplate(getDolGlobalInt(LmdbVehicleInsuranceConfig::CONST_REQUEST_TEMPLATE), LmdbVehicleInsuranceConfig::REQUEST_TEMPLATE_TYPE);
		if ($template === null) {
			return -1;
		}
		$replacements = array(
			'__RECIPIENT_NAME__' => trim((string) $user->firstname.' '.(string) $user->lastname),
			'__VEHICLE_REF__' => 'VEH-TEST',
			'__VEHICLE_REGISTRATION__' => 'AA-123-BB',
			'__VEHICLE_LABEL__' => 'Véhicule de test',
			'__INSURANCE_POLICY__' => 'POLICE-TEST',
			'__INSURANCE_EXPIRY_DATE__' => dol_print_date(dol_now(), 'day'),
			'__INSURANCE_URL__' => dol_buildpath('/lmdbvehiclemanagement/vehicle_list.php', 2),
		);

		return $this->sendMail((string) $user->email, $template, $replacements);
	}

	/**
	 * Determine one due reminder and its idempotency bucket.
	 *
	 * @param LmdbVehicleInsuranceCertificate|null $certificate Certificate
	 * @param int $coverageStart Coverage start
	 * @param int $today Today midnight
	 * @return array{type:string,key_suffix:string,due_date:int,review:bool}|null
	 */
	private function getDueReminder($certificate, $coverageStart, $today)
	{
		if ($certificate instanceof LmdbVehicleInsuranceCertificate && (int) $certificate->status === LmdbVehicleInsuranceCertificate::STATUS_PENDING) {
			$days = max(0, (int) floor(($today - $this->dayStart((int) $certificate->date_submitted)) / 86400));
			$repeat = max(1, getDolGlobalInt(LmdbVehicleInsuranceConfig::CONST_REVIEW_REPEAT, 3));
			if ($days % $repeat !== 0) {
				return null;
			}
			return array('type' => 'review', 'key_suffix' => 'review:'.((int) $certificate->id).':'.LmdbVehicleManagementRules::insuranceReminderBucket($days, $repeat), 'due_date' => $today, 'review' => true);
		}
		if ($certificate instanceof LmdbVehicleInsuranceCertificate && (int) $certificate->status === LmdbVehicleInsuranceCertificate::STATUS_VALIDATED) {
			$expiry = dol_mktime(0, 0, 0, (int) dol_print_date((int) $certificate->validity_end, '%m'), (int) dol_print_date((int) $certificate->validity_end, '%d'), (int) dol_print_date((int) $certificate->validity_end, '%Y'));
			$remaining = (int) floor(($expiry - $today) / 86400);
			if (in_array($remaining, LmdbVehicleInsuranceConfig::getBeforeDays(), true)) {
				return array('type' => 'before_expiry', 'key_suffix' => 'before:'.((int) $certificate->id).':'.$remaining, 'due_date' => $today, 'review' => false);
			}
			if ($remaining >= 0) {
				return null;
			}
			$days = abs($remaining);
			$repeat = max(1, getDolGlobalInt(LmdbVehicleInsuranceConfig::CONST_OVERDUE_REPEAT, 7));
			if ($days % $repeat !== 0) {
				return null;
			}
			return array('type' => 'expired', 'key_suffix' => 'expired:'.((int) $certificate->id).':'.LmdbVehicleManagementRules::insuranceReminderBucket($days, $repeat), 'due_date' => $today, 'review' => false);
		}

		$anchor = $coverageStart;
		if ($certificate instanceof LmdbVehicleInsuranceCertificate && (int) $certificate->status === LmdbVehicleInsuranceCertificate::STATUS_REJECTED && !empty($certificate->date_reviewed)) {
			$anchor = $this->dayStart((int) $certificate->date_reviewed);
		}
		$days = max(0, (int) floor(($today - $anchor) / 86400));
		$repeat = max(1, getDolGlobalInt(LmdbVehicleInsuranceConfig::CONST_OVERDUE_REPEAT, 7));
		if ($days % $repeat !== 0) {
			return null;
		}

		return array('type' => $certificate instanceof LmdbVehicleInsuranceCertificate ? 'rejected' : 'missing', 'key_suffix' => 'missing:'.($certificate instanceof LmdbVehicleInsuranceCertificate ? (int) $certificate->id : 0).':'.LmdbVehicleManagementRules::insuranceReminderBucket($days, $repeat), 'due_date' => $today, 'review' => false);
	}

	/** @param int $timestamp Timestamp @return int */
	private function dayStart($timestamp)
	{
		return dol_mktime(0, 0, 0, (int) dol_print_date($timestamp, '%m'), (int) dol_print_date($timestamp, '%d'), (int) dol_print_date($timestamp, '%Y'));
	}

	/**
	 * Send and persist one due reminder.
	 *
	 * @param LmdbVehicleInsuranceContract $contract Contract
	 * @param LmdbVehicleInsuranceCertificate|null $certificate Certificate
	 * @param int $vehicleId Vehicle id
	 * @param string $vehicleRef Vehicle ref
	 * @param string $registration Registration
	 * @param string $vehicleLabel Vehicle label
	 * @param array{type:string,key_suffix:string,due_date:int,review:bool} $due Due reminder
	 * @param int $entity Entity
	 * @return int<-1,1>
	 */
	private function sendReminder($contract, $certificate, $vehicleId, $vehicleRef, $registration, $vehicleLabel, $due, $entity)
	{
		$isFleetReview = $due['review'] && $certificate instanceof LmdbVehicleInsuranceCertificate && empty($certificate->fk_vehicle);
		$logVehicleId = $isFleetReview ? 0 : $vehicleId;
		$key = sha1($contract->id.':'.$logVehicleId.':'.$due['key_suffix']);
		$sql = 'INSERT IGNORE INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_reminder_log';
		$sql .= ' (entity, reminder_key, fk_contract, fk_vehicle, fk_certificate, reminder_type, due_date, status, recipient_count, date_creation) VALUES (';
		$sql .= $entity.", '".$this->db->escape($key)."', ".((int) $contract->id).', '.($logVehicleId > 0 ? (string) $logVehicleId : 'NULL').', ';
		$sql .= $certificate instanceof LmdbVehicleInsuranceCertificate ? (int) $certificate->id : 'NULL';
		$sql .= ", '".$this->db->escape($due['type'])."', '".$this->db->idate($due['due_date'])."', 0, 0, '".$this->db->idate(dol_now())."')";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if ($this->db->affected_rows($resql) === 0) {
			return 0;
		}
		$config = new LmdbVehicleInsuranceConfig($this->db);
		$recipients = $config->getRecipientEmails($vehicleId, $entity, !$due['review']);
		$templateId = getDolGlobalInt($due['review'] ? LmdbVehicleInsuranceConfig::CONST_REVIEW_TEMPLATE : LmdbVehicleInsuranceConfig::CONST_REQUEST_TEMPLATE);
		$templateType = $due['review'] ? LmdbVehicleInsuranceConfig::REVIEW_TEMPLATE_TYPE : LmdbVehicleInsuranceConfig::REQUEST_TEMPLATE_TYPE;
		$template = $this->fetchTemplate($templateId, $templateType);
		$success = 0;
		$errorMessages = array();
		if ($template !== null) {
			foreach ($recipients as $recipient) {
				$replacements = array(
					'__RECIPIENT_NAME__' => $recipient['name'],
					'__VEHICLE_REF__' => $vehicleRef,
					'__VEHICLE_REGISTRATION__' => $registration,
					'__VEHICLE_LABEL__' => $vehicleLabel,
					'__INSURANCE_POLICY__' => (string) $contract->policy_number,
					'__INSURANCE_EXPIRY_DATE__' => $certificate instanceof LmdbVehicleInsuranceCertificate ? dol_print_date((int) $certificate->validity_end, 'day') : '',
					'__INSURANCE_URL__' => dol_buildpath('/lmdbvehiclemanagement/insurancecontract_certificate.php', 2).'?id='.((int) $contract->id),
				);
				if ($this->sendMail($recipient['email'], $template, $replacements) > 0) {
					$success++;
				} else {
					$errorMessages[] = $this->error;
				}
			}
		}
		$status = $template !== null && $success === count($recipients) && $success > 0 ? 1 : -1;
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_reminder_log SET status = '.$status.', recipient_count = '.$success;
		$sql .= ", sent_at = '".$this->db->idate(dol_now())."', error_message = ";
		$error = $template === null ? $this->error : (empty($recipients) ? 'InsuranceNoReminderRecipient' : implode('; ', array_unique($errorMessages)));
		$sql .= $error !== '' ? "'".$this->db->escape($error)."'" : 'NULL';
		$sql .= " WHERE entity = ".$entity." AND reminder_key = '".$this->db->escape($key)."'";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return $status > 0 ? 1 : -1;
	}

	/**
	 * Fetch a compatible native email template.
	 *
	 * @param int $templateId Template id
	 * @param string $type Exact template type
	 * @return array{subject:string,content:string}|null
	 */
	private function fetchTemplate($templateId, $type)
	{
		global $conf;

		if ($templateId <= 0) {
			$this->error = 'InsuranceEmailTemplateMissing';
			return null;
		}
		$sql = 'SELECT topic, content FROM '.MAIN_DB_PREFIX.'c_email_templates';
		$sql .= ' WHERE rowid = '.((int) $templateId)." AND type_template = '".$this->db->escape($type)."' AND active = 1";
		$sql .= ' AND entity IN (0, '.((int) $conf->entity).')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return null;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($row)) {
			$this->error = 'InsuranceEmailTemplateMissing';
			return null;
		}

		return array('subject' => (string) $row->topic, 'content' => (string) $row->content);
	}

	/**
	 * Send one email with Dolibarr's native mail stack.
	 *
	 * @param string $email Recipient
	 * @param array{subject:string,content:string} $template Template
	 * @param array<string,string> $replacements Substitutions
	 * @return int<-1,1>
	 */
	private function sendMail($email, $template, $replacements)
	{
		require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

		if (!isValidEmail($email)) {
			$this->error = 'ErrorBadEMail';
			return -1;
		}
		$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
		if ($from === '') {
			$this->error = 'InsuranceSenderEmailMissing';
			return -1;
		}
		$subject = html_entity_decode(strtr($template['subject'], $replacements), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$content = strtr($template['content'], $replacements);
		$mail = new CMailFile($subject, $email, $from, $content, array(), array(), array(), '', '', 0, -1);
		if (!$mail->sendfile()) {
			$this->error = is_string($mail->error) && $mail->error !== '' ? $mail->error : 'InsuranceEmailSendFailed';
			return -1;
		}

		return 1;
	}
}
