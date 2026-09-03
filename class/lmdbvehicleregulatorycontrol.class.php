<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/lmdbvehiclemanagement/class/lmdbvehiclemanagementobject.class.php');
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicleregulatoryservice.class.php');

/** Documentary record of a regulatory control performed by a competent body. */
class LmdbVehicleRegulatoryControl extends LmdbVehicleManagementObject
{
	public const STATUS_DRAFT = 0;
	public const STATUS_VALIDATED = 1;
	public const STATUS_CANCELLED = 2;
	public const STATUS_ARCHIVED = 3;

	/** @var string */ public $element = 'lmdbvehicleregulatorycontrol';
	/** @var string */ public $table_element = 'lmdbvehiclemanagement_regulatory_control';
	/** @var string */ public $TRIGGER_PREFIX = 'LMDBVEHICLEMANAGEMENT_REGULATORY_CONTROL';
	/** @var string */ public $entity_scope_element = 'lmdbvehicleregulatorycontrol';
	/** @var string */ public $picto = 'clipboard-check';
	/** @var int<0,1> */ public $has_document_storage = 1;

	/** @var array<string,mixed> */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'position' => 5, 'notnull' => 1, 'visible' => 0, 'default' => 1, 'index' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'searchall' => 1),
		'fk_vehicle' => array('type' => 'integer:LmdbVehicle:lmdbvehiclemanagement/class/lmdbvehicle.class.php:0', 'label' => 'VehicleOrEquipment', 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_requirement' => array('type' => 'integer', 'label' => 'RegulatoryRequirement', 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_rule' => array('type' => 'integer', 'label' => 'RegulatoryRule', 'position' => 40, 'notnull' => 1, 'visible' => 0, 'index' => 1),
		'control_kind' => array('type' => 'varchar(32)', 'label' => 'ControlKind', 'position' => 50, 'notnull' => 1, 'visible' => 1, 'arrayofkeyval' => array('periodic' => 'ControlKindPeriodic', 'initial' => 'ControlKindInitial', 'recommissioning' => 'ControlKindRecommissioning', 'recheck' => 'ControlKindRecheck')),
		'control_date' => array('type' => 'datetime', 'label' => 'ControlDate', 'position' => 60, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_soc_provider' => array('type' => 'integer:Societe:societe/class/societe.class.php:1', 'label' => 'ControlBody', 'position' => 70, 'notnull' => -1, 'visible' => 1, 'index' => 1),
		'document_ref' => array('type' => 'varchar(128)', 'label' => 'ControlDocumentRef', 'position' => 80, 'notnull' => -1, 'visible' => 1),
		'result_code' => array('type' => 'varchar(64)', 'label' => 'ControlResult', 'position' => 90, 'notnull' => -1, 'visible' => 1),
		'official_valid_until' => array('type' => 'date', 'label' => 'OfficialValidUntil', 'position' => 100, 'notnull' => -1, 'visible' => 1),
		'calculated_valid_until' => array('type' => 'date', 'label' => 'CalculatedValidUntil', 'position' => 110, 'notnull' => -1, 'visible' => 1),
		'retained_valid_until' => array('type' => 'date', 'label' => 'RetainedValidUntil', 'position' => 120, 'notnull' => -1, 'visible' => 1),
		'due_override_reason' => array('type' => 'text', 'label' => 'DueDateOverrideReason', 'position' => 130, 'notnull' => -1, 'visible' => 1),
		'fk_previous_control' => array('type' => 'integer:LmdbVehicleRegulatoryControl:lmdbvehiclemanagement/class/lmdbvehicleregulatorycontrol.class.php', 'label' => 'PreviousControl', 'position' => 140, 'notnull' => -1, 'visible' => 1, 'index' => 1),
		'cancellation_reason' => array('type' => 'text', 'label' => 'CancellationReason', 'position' => 150, 'notnull' => -1, 'visible' => 1),
		'observations' => array('type' => 'text', 'label' => 'Observations', 'position' => 160, 'notnull' => -1, 'visible' => 1),
		'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'position' => 170, 'notnull' => -1, 'visible' => 0),
		'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'position' => 180, 'notnull' => -1, 'visible' => 0),
		'status' => array('type' => 'integer', 'label' => 'Status', 'position' => 200, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'default' => 0),
		'date_validation' => array('type' => 'datetime', 'label' => 'DateValidation', 'position' => 210, 'notnull' => -1, 'visible' => -2),
		'fk_user_valid' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'ValidatedBy', 'position' => 220, 'notnull' => -1, 'visible' => -2),
		'date_cancellation' => array('type' => 'datetime', 'label' => 'CancellationDate', 'position' => 230, 'notnull' => -1, 'visible' => -2),
		'fk_user_cancel' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'CancelledBy', 'position' => 240, 'notnull' => -1, 'visible' => -2),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'position' => 501, 'notnull' => 0, 'visible' => -2, 'noteditable' => 1),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'model_pdf' => array('type' => 'varchar(255)', 'label' => 'Model', 'position' => 1010, 'notnull' => -1, 'visible' => 0),
		'last_main_doc' => array('type' => 'varchar(255)', 'label' => 'LastMainDoc', 'position' => 1020, 'notnull' => -1, 'visible' => 0),
	);

	/** @var string */ public $ref = '';
	/** @var int */ public $fk_vehicle = 0;
	/** @var int */ public $fk_requirement = 0;
	/** @var int */ public $fk_rule = 0;
	/** @var string */ public $control_kind = 'periodic';
	/** @var int */ public $control_date = 0;
	/** @var ?int */ public $fk_soc_provider;
	/** @var ?string */ public $document_ref;
	/** @var ?string */ public $result_code;
	/** @var ?int */ public $official_valid_until;
	/** @var ?int */ public $calculated_valid_until;
	/** @var ?int */ public $retained_valid_until;
	/** @var ?string */ public $due_override_reason;
	/** @var ?int */ public $fk_previous_control;
	/** @var ?string */ public $cancellation_reason;
	/** @var ?string */ public $observations;
	/** @var ?string */ public $note_public;
	/** @var ?string */ public $note_private;
	/** @var ?int */ public $date_validation;
	/** @var ?int */ public $fk_user_valid;
	/** @var ?int */ public $date_cancellation;
	/** @var ?int */ public $fk_user_cancel;
	/** @var ?string */ public $model_pdf;
	/** @var ?string */ public $last_main_doc;
	/** @var bool */ private $transitionInProgress = false;

	/** @param DoliDB $db Database */
	public function __construct($db) { parent::__construct($db); $this->status = self::STATUS_DRAFT; }

	/** @inheritdoc */
	public function create(User $user, $notrigger = 0)
	{
		$this->status = self::STATUS_DRAFT;
		if ($this->control_date <= 0) $this->control_date = dol_now();
		$this->context['trigger_reason'] = !empty($this->import_key) ? 'import_draft' : 'create_draft';
		return parent::create($user, $notrigger);
	}

	/** @inheritdoc */
	public function update(User $user, $notrigger = 0)
	{
		$current = new self($this->db);
		if (empty($this->id) || $current->fetch((int) $this->id) <= 0) return $this->copyError($current);
		if (!$this->transitionInProgress && (int) $current->status !== self::STATUS_DRAFT) return $this->businessError('ValidatedRegulatoryControlIsImmutable');
		if (!$this->transitionInProgress) $this->status = self::STATUS_DRAFT;
		return parent::update($user, $notrigger);
	}

	/** Validate a draft after checking its simplified result and supporting document. @param User $user Validator @param int<0,1> $notrigger Disable triggers @return int<-1,max> */
	public function validate(User $user, $notrigger = 0)
	{
		$current = new self($this->db);
		if (empty($this->id) || $current->fetch((int) $this->id) <= 0) return $this->copyError($current);
		if ((int) $current->status !== self::STATUS_DRAFT) return $this->businessError('OnlyDraftControlCanBeValidated');
		$this->copyFrom($current);
		if (empty($this->result_code)) return $this->businessError('ControlResultRequired');
		if (!$this->hasSupportingDocument()) return $this->businessError('ControlSupportingDocumentRequired');
		$this->calculated_valid_until = $this->calculateNextDueDate();
		$defaultRetained = !empty($this->official_valid_until) ? (int) $this->official_valid_until : (int) $this->calculated_valid_until;
		if (empty($this->retained_valid_until)) $this->retained_valid_until = $defaultRetained ?: null;
		if (!empty($this->retained_valid_until) && ($defaultRetained <= 0 || (int) $this->retained_valid_until !== $defaultRetained) && trim((string) $this->due_override_reason) === '') return $this->businessError('DueDateOverrideReasonRequired');
		$this->status = self::STATUS_VALIDATED;
		$this->date_validation = dol_now();
		$this->fk_user_valid = (int) $user->id;
		$this->context['trigger_reason'] = 'validation';
		$this->context['old_status'] = self::STATUS_DRAFT;
		$this->context['new_status'] = self::STATUS_VALIDATED;
		$this->transitionInProgress = true;
		$this->db->begin();
		$result = parent::update($user, $notrigger);
		$this->transitionInProgress = false;
		if ($result <= 0) { $this->db->rollback(); return $result; }
		$service = new LmdbVehicleRegulatoryService($this->db);
		if ($service->ensureRecheckRequirement($this, $user) < 0) { $this->error = $service->error; $this->errors = $service->errors; $this->db->rollback(); return -1; }
		if ($service->recalculateVehicle((int) $this->fk_vehicle, (int) $this->entity) < 0) { $this->error = $service->error; $this->errors = $service->errors; $this->db->rollback(); return -1; }
		$this->db->commit();
		return $result;
	}

	/** Cancel a validated control without rewriting it. @param string $reason Reason @param User $user Author @return int<-1,max> */
	public function cancel($reason, User $user)
	{
		$current = new self($this->db);
		if (empty($this->id) || $current->fetch((int) $this->id) <= 0) return $this->copyError($current);
		if ((int) $current->status !== self::STATUS_VALIDATED || trim($reason) === '') return $this->businessError('ControlCancellationReasonRequired');
		$this->copyFrom($current);
		$this->status = self::STATUS_CANCELLED;
		$this->cancellation_reason = trim($reason);
		$this->date_cancellation = dol_now();
		$this->fk_user_cancel = (int) $user->id;
		$this->context['trigger_reason'] = 'cancellation';
		$this->context['old_status'] = self::STATUS_VALIDATED;
		$this->context['new_status'] = self::STATUS_CANCELLED;
		$this->transitionInProgress = true;
		$this->db->begin();
		$result = parent::update($user);
		$this->transitionInProgress = false;
		if ($result <= 0) { $this->db->rollback(); return $result; }
		$service = new LmdbVehicleRegulatoryService($this->db);
		if ($service->recalculateVehicle((int) $this->fk_vehicle, (int) $this->entity) < 0) { $this->error = $service->error; $this->errors = $service->errors; $this->db->rollback(); return -1; }
		$this->db->commit();
		return $result;
	}

	/** Archive a cancelled or formally replaced control while preserving evidence. @param User $user Author @return int<-1,max> */
	public function archive(User $user)
	{
		$current = new self($this->db);
		if (empty($this->id) || $current->fetch((int) $this->id) <= 0) return $this->copyError($current);
		$archiveCheck = $this->canBeArchived();
		if ($archiveCheck < 0) return -1;
		if ($archiveCheck === 0) return $this->businessError('OnlyCancelledOrReplacedControlCanBeArchived');
		$this->copyFrom($current);
		$this->status = self::STATUS_ARCHIVED;
		$this->context['trigger_reason'] = 'archiving';
		$this->context['old_status'] = (int) $current->status;
		$this->context['new_status'] = self::STATUS_ARCHIVED;
		$this->transitionInProgress = true;
		$result = parent::update($user);
		$this->transitionInProgress = false;
		return $result;
	}

	/**
	 * Check whether evidence may be manually archived.
	 *
	 * @return int<-1,1> 1 when archivable, 0 when forbidden, -1 on database error
	 */
	public function canBeArchived()
	{
		$current = new self($this->db);
		if (empty($this->id) || $current->fetch((int) $this->id) <= 0) {
			$this->copyError($current);
			return -1;
		}
		if ((int) $current->status === self::STATUS_CANCELLED && trim((string) $current->cancellation_reason) !== '') return 1;
		if ((int) $current->status !== self::STATUS_VALIDATED) return 0;

		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_control';
		$sql .= ' WHERE entity = '.((int) $current->entity).' AND fk_previous_control = '.((int) $current->id).' AND status = '.self::STATUS_VALIDATED.' LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
		$archivable = $this->db->num_rows($resql) > 0;
		$this->db->free($resql);

		return $archivable ? 1 : 0;
	}

	/** @inheritdoc */
	public function delete(User $user, $notrigger = 0)
	{
		if ((int) $this->status !== self::STATUS_DRAFT) return $this->businessError('OnlyDraftControlCanBeDeleted');
		return parent::delete($user, $notrigger);
	}

	/** @inheritdoc */
	protected function validateBusinessRules()
	{
		if ($this->fk_vehicle <= 0 || $this->fk_requirement <= 0) return $this->businessError('RegulatoryRequirementRequired');
		$sql = 'SELECT req.entity, req.fk_vehicle, req.fk_rule FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_control_requirement AS req WHERE req.rowid = '.((int) $this->fk_requirement).' AND req.active = 1 AND req.entity IN ('.getEntity('lmdbvehicleregulatorycontrol').')';
		$resql = $this->db->query($sql);
		if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
		$row = $this->db->fetch_object($resql); $this->db->free($resql);
		if (!is_object($row) || (int) $row->fk_vehicle !== (int) $this->fk_vehicle) return $this->businessError('InvalidRegulatoryRequirement');
		$this->entity = (int) $row->entity; $this->fk_rule = (int) $row->fk_rule;
		if (!in_array($this->control_kind, array('periodic', 'initial', 'recommissioning', 'recheck'), true)) return $this->businessError('InvalidControlKind');
		if ($this->control_date <= 0) return $this->businessError('ControlDateRequired');
		if (!empty($this->result_code)) {
			$resql = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'c_lmdbvehiclemanagement_control_result WHERE entity IN ('.getEntity('c_lmdbvehiclemanagement_control_result').") AND code = '".$this->db->escape((string) $this->result_code)."' AND active = 1");
			if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
			$valid = $this->db->num_rows($resql) > 0; $this->db->free($resql);
			if (!$valid) return $this->businessError('InvalidControlResult');
		}
		if (!empty($this->fk_soc_provider)) {
			$resql = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE rowid = '.((int) $this->fk_soc_provider).' AND entity IN ('.getEntity('societe').')');
			if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
			$valid = $this->db->num_rows($resql) > 0; $this->db->free($resql);
			if (!$valid) return $this->businessError('InvalidControlBody');
		}
		if (!empty($this->fk_previous_control)) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_control';
			$sql .= ' WHERE rowid = '.((int) $this->fk_previous_control).' AND entity = '.((int) $this->entity);
			$sql .= ' AND fk_vehicle = '.((int) $this->fk_vehicle).' AND status = '.self::STATUS_CANCELLED;
			$resql = $this->db->query($sql);
			if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
			$valid = $this->db->num_rows($resql) === 1; $this->db->free($resql);
			if (!$valid) return $this->businessError('InvalidPreviousRegulatoryControl');
		}
		return 1;
	}

	/** @return bool */
	private function hasSupportingDocument()
	{
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		$dir = getMultidirOutput($this, 'lmdbvehiclemanagement', 1);
		return is_string($dir) && $dir !== '' && strpos($dir, 'error-diroutput-') !== 0 && count(dol_dir_list($dir, 'files', 0, '', '(\.meta|_preview.*\.png)$')) > 0;
	}

	/** @return ?int */
	private function calculateNextDueDate()
	{
		$sql = 'SELECT r.code, r.recurrence_months, r.recurrence_days, r.recheck_days, v.regulatory_territory, v.eu_category FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_regulatory_rule AS r';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle AS v ON v.rowid = '.((int) $this->fk_vehicle).' AND v.entity = r.entity';
		$sql .= ' WHERE r.rowid = '.((int) $this->fk_rule).' AND r.entity = '.((int) $this->entity);
		$resql = $this->db->query($sql);
		if (!$resql) return null;
		$row = $this->db->fetch_object($resql); $this->db->free($resql);
		if (!is_object($row)) return null;
		if ((string) $this->result_code === 'critical') return (int) $this->control_date;
		if (in_array((string) $this->result_code, array('recheck_required', 'non_compliant'), true) && !empty($row->recheck_days)) {
			$recheckDays = $this->resolveRecheckDays($row);
			return dol_time_plus_duree((int) $this->control_date, $recheckDays, 'd');
		}
		if (!empty($row->recurrence_months)) return dol_time_plus_duree((int) $this->control_date, (int) $row->recurrence_months, 'm');
		if (!empty($row->recurrence_days)) return dol_time_plus_duree((int) $this->control_date, (int) $row->recurrence_days, 'd');
		return null;
	}

	/** @param object $row Rule and vehicle context @return int */
	private function resolveRecheckDays($row)
	{
		$code = (string) $row->code;
		if ($code === 'FR_CATEGORY_L' || $code === 'FR_ROAD_LIGHT' || strpos($code, 'FR_SPECIAL_') === 0) return 60;
		if (in_array($code, array('FR_ROAD_HEAVY', 'FR_PUBLIC_TRANSPORT'), true)) {
			if (strpos(strtoupper(trim((string) $row->eu_category)), 'M1') === 0) return 60;
			if (in_array((string) $row->regulatory_territory, array('FR_GUADELOUPE', 'FR_MARTINIQUE', 'FR_GUYANE', 'FR_REUNION', 'FR_MAYOTTE'), true)) return 60;
			return 30;
		}

		return (int) $row->recheck_days;
	}

	/** @param self $source Source @return void */
	private function copyFrom($source)
	{
		foreach (array_keys($this->fields) as $field) if (property_exists($this, $field) && property_exists($source, $field)) $this->{$field} = $source->{$field};
		$this->id = (int) $source->id; $this->rowid = (int) $source->id;
	}
	/** @param object $source Source @return int<-1,-1> */ private function copyError($source) { $this->error = !empty($source->error) ? $source->error : 'RecordNotFound'; $this->errors = !empty($source->errors) ? $source->errors : array($this->error); return -1; }
	/** @param string $error Error @return int<-1,-1> */ private function businessError($error) { $this->error = $error; $this->errors[] = $error; return -1; }

	/** @inheritdoc */
	protected function getNextNumRef()
	{
		global $langs;
		$model = getDolGlobalString('LMDBVEHICLEMANAGEMENT_REGULATORY_CONTROL_ADDON', 'mod_lmdbvehicleregulatorycontrol_standard');
		$file = dol_buildpath('/lmdbvehiclemanagement/core/modules/lmdbvehiclemanagement/'.$model.'.php', 0);
		if ($model === '' || !is_readable($file)) { $this->error = $langs->trans('ErrorNumRefModelNotFound'); return -1; }
		require_once $file;
		if (!class_exists($model)) { $this->error = $langs->trans('ErrorNumRefModelNotFound'); return -1; }
		$numbering = new $model(); $next = $numbering->getNextValue($this);
		if (!is_string($next)) $this->error = $numbering->error;
		return $next;
	}
	/** @inheritdoc */ protected function getNumberingLockScope() { return $this->TRIGGER_PREFIX.':'.getEntity('lmdbvehicleregulatorycontrolnumber', 1, $this); }
	/** @inheritdoc */ public function LibStatut($status, $mode = 0) { global $langs; $labels = array(0 => 'ControlStatusDraft', 1 => 'ControlStatusValidated', 2 => 'ControlStatusCancelled', 3 => 'ControlStatusArchived'); $types = array(0 => 'status0', 1 => 'status4', 2 => 'status6', 3 => 'status9'); $label = $langs->trans(isset($labels[$status]) ? $labels[$status] : 'Unknown'); return dolGetStatus($label, $label, '', isset($types[$status]) ? $types[$status] : 'status0', $mode); }
	/** @inheritdoc */ protected function getCardPage() { return 'regulatorycontrol_card.php'; }
	/** @inheritdoc */ protected function getCardUrlParameters() { return 'id='.((int) $this->id); }
}
