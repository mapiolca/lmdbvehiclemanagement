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

		$this->context['trigger_reason'] = 'create';
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

		return parent::call_trigger($triggerName, $user);
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
		$label = property_exists($this, 'ref') && !empty($this->ref) ? (string) $this->ref : (string) $this->id;
		$url = dol_buildpath('/lmdbvehiclemanagement/'.$this->getCardPage(), 1).'?'.$this->getCardUrlParameters();
		$link = '<a href="'.$url.'" class="'.dol_escape_htmltag($morecss).'">';
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
