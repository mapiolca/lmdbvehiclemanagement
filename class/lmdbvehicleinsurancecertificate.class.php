<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementobject.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementrules.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementsecureupload.class.php');

/** Insurance certificate submitted for a contract or one covered vehicle. */
class LmdbVehicleInsuranceCertificate extends LmdbVehicleManagementObject
{
	public const STATUS_DRAFT = 0;
	public const STATUS_PENDING = 1;
	public const STATUS_VALIDATED = 2;
	public const STATUS_REJECTED = 3;
	public const STATUS_ARCHIVED = 9;

	/** @var string */ public $element = 'lmdbinsurancecertificate';
	/** @var string */ public $table_element = 'lmdbvehiclemanagement_insurance_certificate';
	/** @var string */ public $TRIGGER_PREFIX = 'LMDBVEHICLEMANAGEMENT_CERTIFICATE';
	/** @var string */ public $entity_scope_element = 'lmdbvehicle';
	/** @var string */ public $picto = 'file-shield';

	/** @var array<string,mixed> */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'position' => 10, 'notnull' => 1, 'visible' => 0, 'default' => 1, 'index' => 1),
		'fk_contract' => array('type' => 'integer:LmdbVehicleInsuranceContract:lmdbvehiclemanagement/class/lmdbvehicleinsurancecontract.class.php', 'label' => 'InsuranceContract', 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_vehicle' => array('type' => 'integer:LmdbVehicle:lmdbvehiclemanagement/class/lmdbvehicle.class.php', 'label' => 'Vehicle', 'position' => 30, 'notnull' => -1, 'visible' => 1, 'index' => 1),
		'validity_start' => array('type' => 'date', 'label' => 'InsuranceValidityStart', 'position' => 40, 'notnull' => 1, 'visible' => 1),
		'validity_end' => array('type' => 'date', 'label' => 'InsuranceValidityEnd', 'position' => 50, 'notnull' => 1, 'visible' => 1),
		'file_name' => array('type' => 'varchar(255)', 'label' => 'InsuranceEvidence', 'position' => 60, 'notnull' => -1, 'visible' => 1),
		'file_mime' => array('type' => 'varchar(128)', 'label' => 'MimeType', 'position' => 61, 'notnull' => -1, 'visible' => 0),
		'status' => array('type' => 'integer', 'label' => 'Status', 'position' => 70, 'notnull' => 1, 'visible' => 1, 'default' => 0, 'arrayofkeyval' => array(0 => 'InsuranceCertificateStatusDraft', 1 => 'InsuranceCertificateStatusPending', 2 => 'InsuranceCertificateStatusValidated', 3 => 'InsuranceCertificateStatusRejected', 9 => 'InsuranceCertificateStatusArchived')),
		'date_submitted' => array('type' => 'datetime', 'label' => 'InsuranceDateSubmitted', 'position' => 80, 'notnull' => -1, 'visible' => 1),
		'fk_user_submit' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'InsuranceSubmittedBy', 'position' => 90, 'notnull' => -1, 'visible' => 1),
		'date_reviewed' => array('type' => 'datetime', 'label' => 'InsuranceDateReviewed', 'position' => 100, 'notnull' => -1, 'visible' => 1),
		'fk_user_review' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'InsuranceReviewedBy', 'position' => 110, 'notnull' => -1, 'visible' => 1),
		'rejection_reason' => array('type' => 'text', 'label' => 'InsuranceRejectionReason', 'position' => 120, 'notnull' => -1, 'visible' => 3),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'position' => 501, 'notnull' => 0, 'visible' => -2, 'noteditable' => 1),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'position' => 511, 'notnull' => -1, 'visible' => -2),
	);

	/** @var int */ public $fk_contract = 0;
	/** @var ?int */ public $fk_vehicle;
	/** @var int */ public $validity_start = 0;
	/** @var int */ public $validity_end = 0;
	/** @var ?string */ public $file_name;
	/** @var ?string */ public $file_mime;
	/** @var ?int */ public $date_submitted;
	/** @var ?int */ public $fk_user_submit;
	/** @var ?int */ public $date_reviewed;
	/** @var ?int */ public $fk_user_review;
	/** @var ?string */ public $rejection_reason;
	/** @var bool */ private $transitionInProgress = false;

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->status = self::STATUS_DRAFT;
	}

	/** @inheritdoc */
	public function create(User $user, $notrigger = 0)
	{
		$this->status = self::STATUS_DRAFT;

		return parent::create($user, $notrigger);
	}

	/** @inheritdoc */
	public function update(User $user, $notrigger = 0)
	{
		if (!$this->transitionInProgress && !empty($this->id)) {
			$current = new self($this->db);
			if ($current->fetch((int) $this->id) <= 0) {
				$this->error = 'RecordNotFound';
				return -1;
			}
			if ((int) $current->status !== self::STATUS_DRAFT || (int) $this->status !== self::STATUS_DRAFT) {
				$this->error = 'InsuranceCertificateImmutable';
				$this->errors[] = $this->error;
				return 0;
			}
		}

		return parent::update($user, $notrigger);
	}

	/**
	 * Store and sanitize an uploaded evidence file.
	 *
	 * @param array<string,mixed> $upload One $_FILES entry
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable trigger
	 * @return int<-1,1>
	 */
	public function storeUploadedFile($upload, User $user, $notrigger = 0)
	{
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		if (empty($this->id)) {
			$this->error = 'InsuranceEvidenceUploadInvalid';
			$this->errors[] = $this->error;
			return -1;
		}
		$errorKeys = array(
			'invalid_upload' => 'InsuranceEvidenceUploadInvalid',
			'invalid_mime' => 'InsuranceEvidenceMimeInvalid',
			'library' => 'InsuranceImageLibraryUnavailable',
			'invalid_image' => 'InsuranceEvidenceImageInvalid',
			'save' => 'InsuranceEvidenceUploadFailed',
		);
		$secureUpload = new LmdbVehicleManagementSecureUpload();
		$fileInfo = $secureUpload->inspect($upload, $errorKeys);
		if (!is_array($fileInfo)) {
			$this->error = $secureUpload->error;
			$this->errors = $secureUpload->errors;
			return -1;
		}
		$mime = $fileInfo['mime'];

		$contract = new LmdbVehicleInsuranceContract($this->db);
		if ($contract->fetch((int) $this->fk_contract) <= 0 || (int) $contract->entity !== (int) $this->entity) {
			$this->error = 'InsuranceContractInvalid';
			return -1;
		}
		$contractDir = getMultidirOutput($contract, 'lmdbvehiclemanagement', 1);
		if (!is_string($contractDir) || $contractDir === '' || strpos($contractDir, 'error-diroutput-') === 0) {
			$this->error = 'ErrorInvalidDirectory';
			return -1;
		}
		$directory = $contractDir.'/certificates';
		if (dol_mkdir($directory) < 0) {
			$this->error = 'ErrorCanNotCreateDir';
			return -1;
		}
		$scope = !empty($this->fk_vehicle) ? 'vehicle-'.((int) $this->fk_vehicle) : 'fleet';
		$fileName = 'certificate-'.$scope.'-'.((int) $this->id).'.'.$fileInfo['extension'];
		$destination = $directory.'/'.$fileName;
		$oldPath = $this->getDocumentPath();
		if ($secureUpload->store($upload, $destination, $mime, $errorKeys) < 0) {
			$this->error = $secureUpload->error;
			$this->errors = $secureUpload->errors;
			return -1;
		}
		$sourceName = dol_sanitizeFileName(basename((string) $upload['name']));
		$indexResult = addFileIntoDatabaseIndex($directory, $fileName, $sourceName, 'uploaded', 0, $contract);
		if ($indexResult < 0) {
			$this->error = 'WarningFailedToAddFileIntoDatabaseIndex';
			$this->deleteDocumentFile($destination);
			return -1;
		}

		$this->file_name = 'certificates/'.$fileName;
		$this->file_mime = $mime;
		$this->context['trigger_reason'] = 'document_upload';

		$result = $this->update($user, $notrigger);
		if ($result <= 0) {
			$this->deleteDocumentFile($destination);
			return -1;
		}
		if ($oldPath !== '' && $oldPath !== $destination && is_file($oldPath)) {
			$this->deleteDocumentFile($oldPath);
		}

		return 1;
	}

	/**
	 * Create an attestation, store its evidence and optionally submit it.
	 *
	 * @param array<string,mixed> $upload One $_FILES entry
	 * @param bool $submit Submit after storing the evidence
	 * @param User $user Author
	 * @return int<-1,max>
	 */
	public function createWithUploadedFile($upload, $submit, User $user)
	{
		$createdId = 0;
		$triggerResult = 0;
		$triggerError = '';
		$triggerErrors = array();
		$this->db->begin();
		$result = $this->create($user, 1);
		if ($result > 0) {
			$createdId = (int) $this->id;
		}
		if ($result > 0) {
			$result = $this->storeUploadedFile($upload, $user, 1);
		}
		if ($result > 0 && $submit) {
			$result = $this->submit($user, 1);
		}
		if ($result > 0) {
			$this->context['trigger_reason'] = $submit ? 'create_and_submit' : 'create_draft';
			$this->context['changed_fields'] = array_keys($this->fields);
			$triggerResult = $this->call_trigger($this->TRIGGER_PREFIX.'_CREATE', $user);
			if ($triggerResult < 0) {
				$triggerError = (string) $this->error;
				$triggerErrors = is_array($this->errors) ? $this->errors : array();
				$result = -1;
			}
		}
		if ($result > 0) {
			$this->db->commit();
			return 1;
		}
		$path = $this->getDocumentPath();
		$this->db->rollback();
		if ($path !== '' && is_file($path)) {
			$this->deleteDocumentFile($path);
		}
		if ($createdId > 0) {
			$this->id = 0;
			$this->rowid = 0;
		}
		if ($triggerResult < 0) {
			$this->error = $triggerError;
			$this->errors = $triggerErrors;
		}

		return -1;
	}

	/** @param User $user Author @param int<0,1> $notrigger Disable trigger @return int<-1,max> */
	public function submit(User $user, $notrigger = 0)
	{
		if (empty($this->file_name) || !$this->documentExists()) {
			$this->error = 'InsuranceEvidenceRequired';
			$this->errors[] = $this->error;
			return 0;
		}

		return $this->changeStatus(self::STATUS_PENDING, $user, '', $notrigger);
	}

	/** @param User $user Reviewer @param int<0,1> $notrigger Disable trigger @return int<-1,max> */
	public function validateCertificate(User $user, $notrigger = 0)
	{
		return $this->changeStatus(self::STATUS_VALIDATED, $user, '', $notrigger);
	}

	/** @param User $user Reviewer @param string $reason Rejection reason @param int<0,1> $notrigger Disable trigger @return int<-1,max> */
	public function reject(User $user, $reason, $notrigger = 0)
	{
		$reason = trim($reason);
		if ($reason === '') {
			$this->error = 'InsuranceRejectionReasonRequired';
			$this->errors[] = $this->error;
			return 0;
		}

		return $this->changeStatus(self::STATUS_REJECTED, $user, $reason, $notrigger);
	}

	/** @param User $user Author @param int<0,1> $notrigger Disable trigger @return int<-1,max> */
	public function archive(User $user, $notrigger = 0)
	{
		return $this->changeStatus(self::STATUS_ARCHIVED, $user, '', $notrigger);
	}

	/** @inheritdoc */
	public function delete(User $user, $notrigger = 0)
	{
		if ((int) $this->status !== self::STATUS_DRAFT) {
			$this->error = 'InsuranceOnlyDraftCanBeDeleted';
			$this->errors[] = $this->error;
			return 0;
		}
		$path = $this->getDocumentPath();
		$result = parent::delete($user, $notrigger);
		if ($result > 0 && $path !== '' && is_file($path)) {
			$this->deleteDocumentFile($path);
		}

		return $result;
	}

	/**
	 * Return the most recent vehicle-specific certificate, then fleet certificate.
	 *
	 * @param DoliDB $db Database handler
	 * @param int $contractId Contract id
	 * @param int $vehicleId Vehicle id
	 * @return LmdbVehicleInsuranceCertificate|null
	 */
	public static function getApplicable($db, $contractId, $vehicleId)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_certificate';
		$sql .= ' WHERE fk_contract = '.((int) $contractId).' AND (fk_vehicle = '.((int) $vehicleId).' OR fk_vehicle IS NULL)';
		$sql .= ' AND entity IN ('.getEntity('lmdbvehicle').') AND status NOT IN ('.self::STATUS_DRAFT.', '.self::STATUS_ARCHIVED.')';
		$sql .= ' ORDER BY (fk_vehicle = '.((int) $vehicleId).') DESC, date_creation DESC, rowid DESC LIMIT 1';
		$resql = $db->query($sql);
		if (!$resql) {
			return null;
		}
		$row = $db->fetch_object($resql);
		$db->free($resql);
		if (!is_object($row)) {
			return null;
		}
		$certificate = new self($db);

		return $certificate->fetch((int) $row->rowid) > 0 ? $certificate : null;
	}

	/** Return a verified absolute document path. @return string */
	public function getDocumentPath()
	{
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		if (empty($this->file_name) || basename((string) $this->file_name) === '' || strpos((string) $this->file_name, '..') !== false) {
			return '';
		}
		$contract = new LmdbVehicleInsuranceContract($this->db);
		if ($contract->fetch((int) $this->fk_contract) <= 0 || (int) $contract->entity !== (int) $this->entity) {
			return '';
		}
		$directory = getMultidirOutput($contract, 'lmdbvehiclemanagement', 1);
		if (!is_string($directory) || $directory === '' || strpos($directory, 'error-diroutput-') === 0) {
			return '';
		}
		$path = $directory.'/'.ltrim((string) $this->file_name, '/\\');
		$normalizedRoot = str_replace('\\', '/', rtrim($directory, '/\\')).'/';
		$normalizedPath = str_replace('\\', '/', $path);

		return strpos($normalizedPath, $normalizedRoot) === 0 ? $path : '';
	}

	/** @inheritdoc */
	protected function validateBusinessRules()
	{
		if ($this->fk_contract <= 0 || $this->validity_start <= 0 || $this->validity_end <= 0 || $this->validity_end < $this->validity_start) {
			$this->error = 'InsuranceCertificateRequiredFields';
			$this->errors[] = $this->error;
			return -1;
		}
		$contract = new LmdbVehicleInsuranceContract($this->db);
		if ($contract->fetch((int) $this->fk_contract) <= 0) {
			$this->error = 'InsuranceContractInvalid';
			return -1;
		}
		$this->entity = (int) $contract->entity;
		if (!empty($this->fk_vehicle) && !in_array((int) $this->fk_vehicle, $contract->getVehicleIds(), true)) {
			$this->error = 'InsuranceVehicleNotCovered';
			$this->errors[] = $this->error;
			return -1;
		}
		if (!in_array((int) $this->status, array(self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_VALIDATED, self::STATUS_REJECTED, self::STATUS_ARCHIVED), true)) {
			$this->error = 'ErrorBadValueForParameter';
			return -1;
		}

		return 1;
	}

	/** @inheritdoc */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;

		$labels = array(self::STATUS_DRAFT => 'InsuranceCertificateStatusDraft', self::STATUS_PENDING => 'InsuranceCertificateStatusPending', self::STATUS_VALIDATED => 'InsuranceCertificateStatusValidated', self::STATUS_REJECTED => 'InsuranceCertificateStatusRejected', self::STATUS_ARCHIVED => 'InsuranceCertificateStatusArchived');
		$classes = array(self::STATUS_DRAFT => 'status0', self::STATUS_PENDING => 'status1', self::STATUS_VALIDATED => 'status4', self::STATUS_REJECTED => 'status8', self::STATUS_ARCHIVED => 'status6');
		$label = isset($labels[$status]) ? $langs->trans($labels[$status]) : (string) $status;

		return dolGetStatus($label, '', '', isset($classes[$status]) ? $classes[$status] : 'status0', $mode);
	}

	/** @inheritdoc */
	protected function getCardPage()
	{
		return 'insurancecontract_certificate.php';
	}

	/** @inheritdoc */
	protected function getCardUrlParameters()
	{
		return 'id='.((int) $this->fk_contract).'&certificate_id='.((int) $this->id);
	}

	/** @return bool */
	private function documentExists()
	{
		$path = $this->getDocumentPath();

		return $path !== '' && is_file($path);
	}

	/**
	 * Delete a certificate file and its native ECM index in the owner entity.
	 *
	 * @param string $path Absolute path
	 * @return bool
	 */
	private function deleteDocumentFile($path)
	{
		$contract = new LmdbVehicleInsuranceContract($this->db);
		if ($contract->fetch((int) $this->fk_contract) <= 0 || (int) $contract->entity !== (int) $this->entity) {
			return false;
		}

		return dol_delete_file($path, 0, 0, 0, $contract);
	}

	/**
	 * Apply a controlled status transition.
	 *
	 * @param int $targetStatus Target status
	 * @param User $user Actor
	 * @param string $reason Rejection reason
	 * @param int<0,1> $notrigger Disable trigger
	 * @return int<-1,max>
	 */
	private function changeStatus($targetStatus, User $user, $reason, $notrigger)
	{
		$current = new self($this->db);
		if ($current->fetch((int) $this->id) <= 0) {
			$this->error = 'RecordNotFound';
			return -1;
		}
		if (!LmdbVehicleManagementRules::insuranceCertificateStatusTransitionIsAllowed((int) $current->status, $targetStatus)) {
			$this->error = 'InsuranceInvalidCertificateStatusTransition';
			$this->errors[] = $this->error;
			return 0;
		}
		$this->status = $targetStatus;
		if ($targetStatus === self::STATUS_PENDING) {
			$this->date_submitted = dol_now();
			$this->fk_user_submit = (int) $user->id;
			$this->date_reviewed = null;
			$this->fk_user_review = null;
			$this->rejection_reason = null;
		} elseif ($targetStatus === self::STATUS_VALIDATED || $targetStatus === self::STATUS_REJECTED) {
			$this->date_reviewed = dol_now();
			$this->fk_user_review = (int) $user->id;
			$this->rejection_reason = $targetStatus === self::STATUS_REJECTED ? $reason : null;
		}
		$this->context['trigger_reason'] = 'status_change';
		$this->context['old_status'] = (int) $current->status;
		$this->context['new_status'] = $targetStatus;
		$this->transitionInProgress = true;
		$result = parent::update($user, $notrigger);
		$this->transitionInProgress = false;

		return $result;
	}

}
