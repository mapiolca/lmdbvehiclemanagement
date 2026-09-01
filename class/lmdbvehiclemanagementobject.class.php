<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Shared business behavior for module objects.
 */
abstract class LmdbVehicleManagementObject extends CommonObject
{
	/** @var string */
	public $module = 'lmdbvehiclemanagement';

	/** @var int<0,1> */
	public $ismultientitymanaged = 1;

	/** @var int<0,1> */
	public $isextrafieldmanaged = 0;

	/** @var string */
	public $picto = 'car';

	/** @var string Element whose Multicompany scope governs this object */
	public $entity_scope_element = '';

	/** @var string Alternative element resolved only by the native Ajax tooltip */
	public $ajax_tooltip_element = '';

	/** @var string Stable prefix used for the object's CRUD triggers */
	public $TRIGGER_PREFIX = '';

	/** @var int<0,1> Whether this object owns a native document directory */
	public $has_document_storage = 0;

	/** @var array<string,mixed> */
	public $fields = array();

	/** @var int */
	public $rowid;

	/** @var ?int */
	public $status;

	/** @var ?int */
	public $date_creation;

	/** @var ?int */
	public $tms;

	/** @var ?int */
	public $fk_user_creat;

	/** @var ?int */
	public $fk_user_modif;

	/** @var ?string */
	public $import_key;

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs;

		$this->db = $db;
		if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
			$this->fields['entity']['enabled'] = 0;
		}

		if (is_object($langs)) {
			foreach ($this->fields as $key => $definition) {
				if (!empty($definition['arrayofkeyval']) && is_array($definition['arrayofkeyval'])) {
					foreach ($definition['arrayofkeyval'] as $value => $label) {
						$this->fields[$key]['arrayofkeyval'][$value] = $langs->trans($label);
					}
				}
			}
		}
	}

	/**
	 * Create a validated object.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,max>
	 */
	public function create(User $user, $notrigger = 0)
	{
		global $conf;

		$this->entity = !empty($this->entity) ? (int) $this->entity : (int) $conf->entity;
		if (empty($this->date_creation)) {
			$this->date_creation = dol_now();
		}

		if ($this->validateBusinessRules() < 0) {
			return -1;
		}

		$numberingLock = '';
		if (isset($this->fields['ref']) && property_exists($this, 'ref')) {
			/** @var string $currentRef */
			$currentRef = (string) $this->ref;
			if ($currentRef === '' || $currentRef === '(PROV)') {
				$numberingLock = 'lmdbvm_num_'.sha1($this->getNumberingLockScope());
				if ($this->acquireNumberingLock($numberingLock) < 0) {
					return -1;
				}
				$next = $this->getNextNumRef();
				if (!is_string($next) || $next === '') {
					$this->releaseNumberingLock($numberingLock);
					return -1;
				}
				$this->ref = $next;
			}
		}

		if (empty($this->context['trigger_reason'])) {
			$this->context['trigger_reason'] = 'create';
		}
		$this->context['changed_fields'] = array_keys($this->fields);

		$result = $this->createCommon($user, $notrigger);
		if ($numberingLock !== '') {
			$this->releaseNumberingLock($numberingLock);
		}

		return $result;
	}

	/**
	 * Fetch an object only from an entity accessible for its element.
	 *
	 * @param int $id Object id
	 * @param ?string $ref Reference
	 * @return int<-4,1>
	 */
	public function fetch($id, $ref = null)
	{
		$scopeElement = $this->entity_scope_element !== '' ? $this->entity_scope_element : $this->element;
		$morewhere = ' AND t.entity IN ('.getEntity($scopeElement).')';

		return $this->fetchCommon($id, $ref, $morewhere);
	}

	/**
	 * Update after loading the previous persisted state.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,max>
	 */
	public function update(User $user, $notrigger = 0)
	{
		if (empty($this->id)) {
			$this->error = 'RecordNotFound';
			return -1;
		}

		$oldcopy = new static($this->db);
		$result = $oldcopy->fetch((int) $this->id);
		if ($result <= 0) {
			$this->error = $oldcopy->error ?: 'RecordNotFound';
			$this->errors = $oldcopy->errors;
			return -1;
		}
		$this->oldcopy = $oldcopy;
		$this->entity = (int) $oldcopy->entity;

		if ($this->validateBusinessRules() < 0) {
			return -1;
		}

		$changedFields = array();
		foreach (array_keys($this->fields) as $field) {
			if (property_exists($this, $field) && property_exists($oldcopy, $field) && $this->{$field} != $oldcopy->{$field}) {
				$changedFields[] = $field;
			}
		}
		if (empty($this->context['trigger_reason'])) {
			$this->context['trigger_reason'] = 'update';
		}
		$this->context['changed_fields'] = $changedFields;

		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete an object through the native transaction and document cleanup.
	 *
	 * @param User $user Author
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int<-1,1>
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$this->context['trigger_reason'] = 'delete';
		if (empty($this->id) || empty($this->entity)) {
			$this->error = 'RecordNotFound';
			$this->errors[] = $this->error;
			return -1;
		}

		$this->db->begin();
		if (!$notrigger && $this->call_trigger($this->TRIGGER_PREFIX.'_DELETE', $user) < 0) {
			$this->db->rollback();
			return -1;
		}
		if ($this->has_document_storage) {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
			// Uploads are normally indexed with table_element, while a native
			// document rescan can use element. Cover both v20 code paths.
			$sourceTypes = array(
				$this->table_element,
				$this->table_element.'@'.$this->module,
				$this->element,
				$this->element.'@'.$this->module,
			);
			$quotedSourceTypes = array();
			foreach ($sourceTypes as $sourceType) {
				$quotedSourceTypes[] = "'".$this->db->escape($sourceType)."'";
			}
			$sourceTypeFilter = implode(', ', $quotedSourceTypes);
			$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'ecm_files_extrafields WHERE fk_object IN (';
			$sql .= 'SELECT rowid FROM '.MAIN_DB_PREFIX.'ecm_files WHERE src_object_type IN ('.$sourceTypeFilter.')';
			$sql .= ' AND src_object_id = '.((int) $this->id).' AND entity = '.((int) $this->entity).')';
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->errors[] = $this->error;
				$this->db->rollback();
				return -1;
			}
			$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'ecm_files WHERE src_object_type IN ('.$sourceTypeFilter.')';
			$sql .= ' AND src_object_id = '.((int) $this->id).' AND entity = '.((int) $this->entity);
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->errors[] = $this->error;
				$this->db->rollback();
				return -1;
			}
			$directory = getMultidirOutput($this, 'lmdbvehiclemanagement', 1);
			$expectedLeaf = dol_sanitizeFileName((string) $this->ref);
			if (!is_string($directory) || $directory === '' || strpos($directory, 'error-diroutput-') === 0 || $expectedLeaf === '' || basename($directory) !== $expectedLeaf) {
				$this->error = 'ErrorInvalidDirectory';
				$this->errors[] = $this->error;
				$this->db->rollback();
				return -1;
			}
			if (dol_is_dir($directory) && !dol_delete_dir_recursive($directory)) {
				$this->error = 'ErrorFailToDeleteDir';
				$this->errors[] = $this->error;
				$this->db->rollback();
				return -1;
			}
		}
		$sql = 'DELETE ec FROM '.MAIN_DB_PREFIX.'element_contact AS ec';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_type_contact AS ctc ON ctc.rowid = ec.fk_c_type_contact';
		$sql .= ' WHERE ec.element_id = '.((int) $this->id);
		$sql .= " AND ctc.element = '".$this->db->escape($this->element)."'";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			$this->db->rollback();
			return -1;
		}
		$result = $this->deleteObjectLinked();
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE rowid = '.((int) $this->id).' AND entity = '.((int) $this->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		return 1;
	}

	/**
	 * Map CommonObject's class-based trigger names to stable module CRUD codes.
	 *
	 * @param string $triggerName Trigger requested by CommonObject
	 * @param ?User $user User
	 * @return int
	 */
	public function call_trigger($triggerName, $user)
	{
		$classTriggerPrefix = strtoupper(get_class($this));
		if ($triggerName === $classTriggerPrefix.'_CREATE') {
			$triggerName = $this->TRIGGER_PREFIX.'_CREATE';
		} elseif ($triggerName === $classTriggerPrefix.'_MODIFY') {
			$triggerName = $this->TRIGGER_PREFIX.'_UPDATE';
		} elseif ($triggerName === $classTriggerPrefix.'_DELETE') {
			$triggerName = $this->TRIGGER_PREFIX.'_DELETE';
		}
		$this->prepareAgendaTriggerContext($triggerName);

		return parent::call_trigger($triggerName, $user);
	}

	/**
	 * Provide native Agenda with translated, deletion-safe event content.
	 *
	 * @param string $triggerName Stable CRUD trigger code
	 * @return void
	 */
	private function prepareAgendaTriggerContext($triggerName)
	{
		global $langs;

		$operation = '';
		foreach (array('CREATE', 'UPDATE', 'DELETE') as $candidate) {
			if ($triggerName === $this->TRIGGER_PREFIX.'_'.$candidate) {
				$operation = $candidate;
				break;
			}
		}
		if ($operation === '' || !is_object($langs)) {
			return;
		}
		if (!is_array($this->context)) {
			$this->context = array();
		}

		$langs->loadLangs(array('main', 'agenda', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
		$objectLabelKeys = array(
			'lmdbvehicle' => 'Vehicle',
			'lmdbvehicleassignment' => 'VehicleAssignment',
			'lmdbvehicleodometerreading' => 'OdometerReading',
			'lmdbvehicleconsumption' => 'ConsumptionEntry',
			'lmdbvehicleevent' => 'VehicleEvent',
			'lmdbinsurancecontract' => 'InsuranceContract',
			'lmdbinsurancecertificate' => 'InsuranceCertificate',
		);
		$objectLabel = $langs->transnoentitiesnoconv(isset($objectLabelKeys[$this->element]) ? $objectLabelKeys[$this->element] : 'Record');
		$identifier = '';
		foreach (array('ref', 'registration_number', 'label') as $identifierField) {
			if (property_exists($this, $identifierField) && trim((string) $this->{$identifierField}) !== '') {
				$identifier = trim((string) $this->{$identifierField});
				break;
			}
		}
		if ($identifier === '' && !empty($this->id)) {
			$identifier = '#'.((int) $this->id);
		}

		$titleKeys = array(
			'CREATE' => 'AgendaCreateTitle',
			'UPDATE' => 'AgendaUpdateTitle',
			'DELETE' => 'AgendaDeleteTitle',
		);
		if (empty($this->context['actionmsg2'])) {
			$this->context['actionmsg2'] = $langs->transnoentitiesnoconv($titleKeys[$operation], $objectLabel, $identifier);
		}

		$reasonKeys = array(
			'create' => 'AgendaReasonCreate',
			'create_draft' => 'AgendaReasonCreateDraft',
			'create_and_submit' => 'AgendaReasonCreateAndSubmit',
			'create_with_coverage' => 'AgendaReasonCreateWithCoverage',
			'update' => 'AgendaReasonUpdate',
			'delete' => 'AgendaReasonDelete',
			'status_change' => 'AgendaReasonStatusChange',
			'reference_sync' => 'AgendaReasonReferenceSync',
			'document_upload' => 'AgendaReasonDocumentUpload',
			'coverage_change' => 'AgendaReasonCoverageChange',
			'vehicle_link' => 'AgendaReasonVehicleLink',
			'import' => 'AgendaReasonImport',
		);
		$reason = isset($this->context['trigger_reason']) ? (string) $this->context['trigger_reason'] : strtolower($operation);
		$reasonKey = isset($reasonKeys[$reason]) ? $reasonKeys[$reason] : $reasonKeys[strtolower($operation)];
		$description = $langs->transnoentitiesnoconv('AgendaEventDescription', $objectLabel, $identifier, $langs->transnoentitiesnoconv($reasonKey));

		if ($operation === 'UPDATE' && !empty($this->context['changed_fields']) && is_array($this->context['changed_fields'])) {
			$changedLabels = array();
			foreach ($this->context['changed_fields'] as $fieldName) {
				$fieldName = (string) $fieldName;
				if (isset($this->fields[$fieldName]['label'])) {
					$changedLabels[] = $langs->transnoentitiesnoconv((string) $this->fields[$fieldName]['label']);
				} elseif ($fieldName === 'vehicle_links') {
					$changedLabels[] = $langs->transnoentitiesnoconv('InsuranceVehicleLinks');
				}
			}
			$changedLabels = array_values(array_unique(array_filter($changedLabels)));
			if (!empty($changedLabels)) {
				$description .= ' '.$langs->transnoentitiesnoconv('AgendaChangedFields', implode(', ', $changedLabels));
			}
		}
		if (empty($this->context['actionmsg'])) {
			$this->context['actionmsg'] = $description;
		}
	}

	/**
	 * Return the main object data displayed by native Dolibarr tooltips.
	 *
	 * Object classes can override this method when linked data must be resolved,
	 * as the insurance contract does for its insurer.
	 *
	 * @param array<string,mixed> $params Tooltip parameters
	 * @return array<string,string>
	 */
	public function getTooltipContentArray($params)
	{
		global $langs;

		$langs->loadLangs(array('main', 'other', 'companies', 'users', 'agenda', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
		$titleKeys = array(
			'lmdbvehicle' => 'Vehicle',
			'lmdbvehicleassignment' => 'VehicleAssignment',
			'lmdbvehicleodometerreading' => 'OdometerReading',
			'lmdbvehicleevent' => 'VehicleEvent',
			'lmdbvehicleconsumption' => 'ConsumptionEntry',
			'lmdbinsurancecontract' => 'InsuranceContract',
			'lmdbinsurancecertificate' => 'InsuranceCertificate',
		);
		$title = $langs->trans(isset($titleKeys[$this->element]) ? $titleKeys[$this->element] : 'Record');
		if (getDolGlobalString('MAIN_OPTIMIZEFORTEXTBROWSER')) {
			return array('optimize' => $title);
		}

		$datas = array();
		$statusBadge = isset($this->fields['status']) && $this->status !== null ? ' '.$this->getLibStatut(5) : '';
		$datas['picto'] = img_picto('', $this->picto).' <u class="paddingrightonly">'.dol_escape_htmltag($title).'</u>'.$statusBadge;
		if (property_exists($this, 'ref') && (string) $this->ref !== '') {
			$datas['ref'] = '<br><b>'.$langs->trans('Ref').':</b> '.dol_escape_htmltag((string) $this->ref);
		}
		if (property_exists($this, 'label') && (string) $this->label !== '') {
			$datas['label'] = '<br><b>'.$langs->trans('Label').':</b> '.dol_escape_htmltag((string) $this->label);
		}

		$excludedFields = array('rowid', 'entity', 'ref', 'label', 'description', 'reason', 'rejection_reason', 'note_public', 'note_private', 'status', 'date_creation', 'tms', 'fk_user_creat', 'fk_user_modif', 'import_key', 'model_pdf', 'last_main_doc');
		$fieldCount = 0;
		foreach ($this->fields as $fieldName => $definition) {
			if ($fieldCount >= 6 || in_array($fieldName, $excludedFields, true) || strpos($fieldName, 'fk_') === 0) {
				continue;
			}
			if (empty($definition['visible']) || (int) $definition['visible'] <= 0 || !property_exists($this, $fieldName)) {
				continue;
			}
			$type = isset($definition['type']) ? (string) $definition['type'] : '';
			$value = $this->{$fieldName};
			if ($value === null || $value === '' || (($type === 'date' || $type === 'datetime' || $type === 'timestamp') && (int) $value <= 0)) {
				continue;
			}
			if ($fieldName === 'registration_number' && property_exists($this, 'ref') && (string) $value === (string) $this->ref) {
				continue;
			}

			if ($type === 'date') {
				$formattedValue = dol_print_date((int) $value, 'day');
			} elseif ($type === 'datetime' || $type === 'timestamp') {
				$formattedValue = dol_print_date((int) $value, 'dayhour');
			} elseif ($type === 'boolean') {
				$formattedValue = $langs->trans(!empty($value) ? 'Yes' : 'No');
			} elseif (!empty($definition['arrayofkeyval']) && is_array($definition['arrayofkeyval']) && isset($definition['arrayofkeyval'][$value])) {
				$formattedValue = $langs->trans((string) $definition['arrayofkeyval'][$value]);
			} elseif (is_scalar($value)) {
				$formattedValue = (string) $value;
			} else {
				continue;
			}

			$fieldLabel = isset($definition['label']) ? $langs->trans((string) $definition['label']) : $fieldName;
			$datas['field_'.$fieldName] = '<br><b>'.dol_escape_htmltag($fieldLabel).':</b> '.dol_escape_htmltag($formattedValue);
			$fieldCount++;
		}

		return $datas;
	}

	/**
	 * Return a native object link.
	 *
	 * @param int<0,1> $withpicto Include icon
	 * @param string $option Link option
	 * @param int<0,1> $notooltip Disable tooltip
	 * @param string $morecss Extra CSS class
	 * @param int<-1,1> $save_lastsearch_value Preserve last search
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $conf, $langs;

		if (!empty($conf->dol_no_mouse_hover)) {
			$notooltip = 1;
		}

		$params = array(
			'id' => (int) $this->id,
			'objecttype' => $this->element.'@'.$this->module,
			'option' => $option,
			'nofetch' => 1,
		);
		if ($this->ajax_tooltip_element !== '') {
			$params['objecttype'] = $this->ajax_tooltip_element.'@'.$this->module;
		}
		$classForTooltip = 'classfortooltip';
		$dataParams = '';
		$tooltipLabel = '';
		if (getDolGlobalInt('MAIN_ENABLE_AJAX_TOOLTIP')) {
			$classForTooltip = 'classforajaxtooltip';
			$dataParams = ' data-params="'.dol_escape_htmltag((string) json_encode($params)).'"';
		} else {
			$tooltipLabel = implode($this->getTooltipContentArray($params));
		}

		$linkAttributes = '';
		$linkCss = 'nowraponall'.($morecss !== '' ? ' '.$morecss : '');
		if (empty($notooltip)) {
			if (getDolGlobalString('MAIN_OPTIMIZEFORTEXTBROWSER')) {
				$tooltipLabel = $langs->trans('ShowCard');
				$linkAttributes .= ' alt="'.dol_escape_htmltag($tooltipLabel, 1, 1).'"';
			}
			$linkAttributes .= $tooltipLabel !== '' ? ' title="'.dol_escape_htmltag($tooltipLabel, 1, 1).'"' : ' title="tocomplete"';
			$linkAttributes .= $dataParams.' class="'.$linkCss.' '.$classForTooltip.'"';
		} else {
			$linkAttributes .= ' class="'.$linkCss.'"';
		}

		$label = property_exists($this, 'ref') && !empty($this->ref) ? (string) $this->ref : (property_exists($this, 'label') && !empty($this->label) ? (string) $this->label : (string) $this->id);
		$url = dol_buildpath('/lmdbvehiclemanagement/'.$this->getCardPage(), 1).'?'.$this->getCardUrlParameters();
		$saveLastSearch = $save_lastsearch_value === 1;
		if ($save_lastsearch_value === -1 && isset($_SERVER['PHP_SELF']) && preg_match('/list\.php/', $_SERVER['PHP_SELF'])) {
			$saveLastSearch = true;
		}
		if ($saveLastSearch) {
			$url .= '&save_lastsearch_values=1';
		}

		$link = '<a href="'.dol_escape_htmltag($url).'"'.$linkAttributes.'>';
		if ($withpicto) {
			$link .= img_picto('', $this->picto, 'class="pictofixedwidth"');
		}
		$link .= dol_escape_htmltag($label).'</a>';

		return $link;
	}

	/**
	 * Render a native status badge.
	 *
	 * @param int<0,6> $mode Rendering mode
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut((int) $this->status, $mode);
	}

	/**
	 * Render a native status badge.
	 *
	 * @param int $status Status
	 * @param int<0,6> $mode Rendering mode
	 * @return string
	 */
	abstract public function LibStatut($status, $mode = 0);

	/**
	 * Validate object-specific invariants.
	 *
	 * @return int<-1,1>
	 */
	protected function validateBusinessRules()
	{
		return 1;
	}

	/**
	 * Return next reference for ref-bearing objects.
	 *
	 * @return string|int<-1,0>
	 */
	protected function getNextNumRef()
	{
		return -1;
	}

	/**
	 * Return the scope serialized while an automatic reference is allocated.
	 *
	 * @return string
	 */
	protected function getNumberingLockScope()
	{
		return $this->TRIGGER_PREFIX.':'.((int) $this->entity);
	}

	/**
	 * Acquire a MySQL/MariaDB advisory lock for reference allocation.
	 *
	 * @param string $lockName Lock name (already bounded by SHA-1)
	 * @return int<-1,1>
	 */
	private function acquireNumberingLock($lockName)
	{
		$sql = "SELECT GET_LOCK('".$this->db->escape($lockName)."', 10) AS lock_acquired";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($obj) || (int) $obj->lock_acquired !== 1) {
			$this->error = 'NumberingLockTimeout';
			$this->errors[] = $this->error;
			return -1;
		}

		return 1;
	}

	/**
	 * Release an advisory numbering lock.
	 *
	 * A release failure is logged after persistence; it must not make a
	 * successfully-created object look like a failed creation to the caller.
	 *
	 * @param string $lockName Lock name
	 * @return void
	 */
	private function releaseNumberingLock($lockName)
	{
		$sql = "SELECT RELEASE_LOCK('".$this->db->escape($lockName)."') AS lock_released";
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.': '.$this->db->lasterror(), LOG_WARNING);
			return;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($obj) || (int) $obj->lock_released !== 1) {
			dol_syslog(__METHOD__.': numbering lock was not released', LOG_WARNING);
		}
	}

	/**
	 * Serialize validations that depend on the current state of one vehicle.
	 *
	 * The caller must already have opened a transaction.
	 *
	 * @param int $vehicleId Vehicle id
	 * @return int<-1,1>
	 */
	protected function lockVehicleRow($vehicleId)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle';
		$sql .= ' WHERE rowid = '.((int) $vehicleId);
		$sql .= ' AND entity IN ('.getEntity('lmdbvehicle').') FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
		$found = $this->db->num_rows($resql) > 0;
		$this->db->free($resql);
		if (!$found) {
			$this->error = 'InvalidVehicle';
			$this->errors[] = $this->error;
			return -1;
		}

		return 1;
	}

	/**
	 * Return the query string that identifies this object on its card page.
	 *
	 * @return string
	 */
	protected function getCardUrlParameters()
	{
		return 'id='.((int) $this->id);
	}

	/**
	 * @return string
	 */
	abstract protected function getCardPage();
}
