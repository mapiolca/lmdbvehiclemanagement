<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/lmdbvehiclemanagement/class/lmdbvehicle.class.php');

/**
 * Transactional migration of vehicle references and their native documents.
 *
 * Database writes are rolled back together. Filesystem moves are journaled and
 * reversed when one database, document or trigger operation fails.
 */
class LmdbVehicleReferenceMigration
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/** @var array<int,array{from:string,to:string,type:string}> */
	private $moveJournal = array();

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return the number of references that will change and any blocking conflict.
	 *
	 * @param string $targetModel Numbering model class
	 * @param int $entity Entity id
	 * @return array{count:int,conflicts:array<int,string>}
	 */
	public function preview($targetModel, $entity)
	{
		$plans = $this->buildPlans($targetModel, $entity);
		if (!is_array($plans)) {
			return array('count' => 0, 'conflicts' => $this->errors);
		}
		$directoryConflicts = $this->findDirectoryConflicts($plans);
		if (!empty($directoryConflicts)) {
			$this->error = 'VehicleDocumentDirectoryConflict';
			$this->errors = $directoryConflicts;
			return array('count' => 0, 'conflicts' => $directoryConflicts);
		}
		$count = 0;
		foreach ($plans as $plan) {
			if ($plan['old_ref'] !== $plan['new_ref']) {
				$count++;
			}
		}

		return array('count' => $count, 'conflicts' => array());
	}

	/**
	 * Migrate all vehicle references of one entity and select the new model.
	 *
	 * @param string $targetModel Numbering model class
	 * @param User $user Author
	 * @param int $entity Entity id
	 * @return int<-1,1>
	 */
	public function migrateEntity($targetModel, User $user, $entity)
	{
		$plans = $this->buildPlans($targetModel, $entity);
		if (!is_array($plans)) {
			return -1;
		}
		$directoryConflicts = $this->findDirectoryConflicts($plans);
		if (!empty($directoryConflicts)) {
			$this->error = 'VehicleDocumentDirectoryConflict';
			$this->errors = $directoryConflicts;
			return -1;
		}
		$this->moveJournal = array();
		$this->db->begin();

		foreach ($plans as $index => $plan) {
			if ($plan['old_ref'] === $plan['new_ref']) {
				continue;
			}
			$tempRef = '__LMDBVM_TMP_'.((int) $plan['vehicle']->id).'_'.substr(sha1($plan['old_ref'].'|'.$plan['new_ref']), 0, 10);
			$plans[$index]['temp_ref'] = $tempRef;
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle';
			$sql .= " SET ref = '".$this->db->escape($tempRef)."'";
			$sql .= ' WHERE rowid = '.((int) $plan['vehicle']->id).' AND entity = '.((int) $entity);
			if (!$this->db->query($sql)) {
				return $this->rollbackWithError($this->db->lasterror());
			}
		}

		// Release every final directory name before assigning any new one.
		foreach ($plans as $plan) {
			if ($plan['old_ref'] === $plan['new_ref']) {
				continue;
			}
			$temporaryVehicle = clone $plan['vehicle'];
			$temporaryVehicle->ref = $plan['temp_ref'];
			if ($this->moveVehicleDirectory($plan['vehicle'], $temporaryVehicle) < 0) {
				return $this->rollbackWithError($this->error);
			}
		}

		foreach ($plans as $plan) {
			if ($plan['old_ref'] === $plan['new_ref']) {
				continue;
			}
			$temporaryVehicle = clone $plan['vehicle'];
			$temporaryVehicle->ref = $plan['temp_ref'];
			$targetVehicle = clone $plan['vehicle'];
			$targetVehicle->ref = $plan['new_ref'];
			$targetVehicle->last_main_doc = $this->replaceReferenceInPath((string) $plan['vehicle']->last_main_doc, $plan['old_ref'], $plan['new_ref']);

			if ($this->renameDocumentFiles($temporaryVehicle, $plan['old_ref'], $plan['new_ref']) < 0
				|| $this->moveVehicleDirectory($temporaryVehicle, $targetVehicle) < 0) {
				return $this->rollbackWithError($this->error);
			}

			$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle';
			$sql .= " SET ref = '".$this->db->escape($plan['new_ref'])."',";
			$sql .= " last_main_doc = ".($targetVehicle->last_main_doc !== '' ? "'".$this->db->escape($targetVehicle->last_main_doc)."'" : 'NULL').',';
			$sql .= ' fk_user_modif = '.((int) $user->id);
			$sql .= ' WHERE rowid = '.((int) $plan['vehicle']->id).' AND entity = '.((int) $entity);
			if (!$this->db->query($sql) || $this->updateEcmIndex($plan['vehicle'], $plan['old_ref'], $plan['new_ref']) < 0) {
				return $this->rollbackWithError($this->error !== '' ? $this->error : $this->db->lasterror());
			}

			$targetVehicle->oldcopy = $plan['vehicle'];
			$targetVehicle->context['trigger_reason'] = 'reference_migration';
			$targetVehicle->context['changed_fields'] = array('ref', 'last_main_doc');
			$targetVehicle->context['old_ref'] = $plan['old_ref'];
			$targetVehicle->context['new_ref'] = $plan['new_ref'];
			if ($targetVehicle->call_trigger($targetVehicle->TRIGGER_PREFIX.'_UPDATE', $user) < 0) {
				$this->error = $targetVehicle->error;
				$this->errors = $targetVehicle->errors;
				return $this->rollbackWithError($this->error);
			}
		}

		if (dolibarr_set_const($this->db, 'LMDBVEHICLEMANAGEMENT_LMDBVEHICLE_ADDON', $targetModel, 'chaine', 0, '', (int) $entity) <= 0) {
			return $this->rollbackWithError($this->db->lasterror());
		}
		$this->db->commit();
		$this->moveJournal = array();

		return 1;
	}

	/**
	 * Move documents while an ordinary vehicle update changes its reference.
	 *
	 * @param LmdbVehicle $oldVehicle Persisted vehicle
	 * @param LmdbVehicle $newVehicle Edited vehicle
	 * @return int<-1,1>
	 */
	public function relocateUpdatedVehicle($oldVehicle, $newVehicle)
	{
		$this->moveJournal = array();
		if ((string) $oldVehicle->ref === (string) $newVehicle->ref) {
			return 1;
		}
		if ($this->moveVehicleDirectory($oldVehicle, $newVehicle) < 0) {
			return -1;
		}
		if ($this->renameDocumentFiles($newVehicle, (string) $oldVehicle->ref, (string) $newVehicle->ref) < 0) {
			$this->rollbackFilesystem();
			return -1;
		}

		return 1;
	}

	/** Reverse filesystem changes made by the current operation. */
	public function rollbackFilesystem()
	{
		foreach (array_reverse($this->moveJournal) as $move) {
			if ($move['type'] === 'directory') {
				@rename(dol_osencode($move['to']), dol_osencode($move['from']));
			} else {
				dol_move($move['to'], $move['from'], '0', 0, 0, 0);
			}
		}
		$this->moveJournal = array();
	}

	/** Mark the current filesystem changes as committed. */
	public function commitFilesystem()
	{
		$this->moveJournal = array();
	}

	/**
	 * Keep the native ECM index aligned with a renamed vehicle directory.
	 *
	 * @param LmdbVehicle $vehicle Vehicle
	 * @param string $oldRef Old reference
	 * @param string $newRef New reference
	 * @return int<-1,1>
	 */
	public function updateEcmIndex($vehicle, $oldRef, $newRef)
	{
		$oldLeaf = dol_sanitizeFileName($oldRef);
		$newLeaf = dol_sanitizeFileName($newRef);
		if ($oldLeaf === '' || $newLeaf === '' || $oldLeaf === $newLeaf) {
			return 1;
		}
		$sourceTypes = array($vehicle->table_element, $vehicle->table_element.'@'.$vehicle->module, $vehicle->element, $vehicle->element.'@'.$vehicle->module);
		$quotedTypes = array();
		foreach ($sourceTypes as $sourceType) {
			$quotedTypes[] = "'".$this->db->escape($sourceType)."'";
		}
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'ecm_files SET';
		$sql .= " filepath = REPLACE(filepath, '".$this->db->escape($oldLeaf)."', '".$this->db->escape($newLeaf)."'),";
		$sql .= " filename = REPLACE(filename, '".$this->db->escape($oldLeaf)."', '".$this->db->escape($newLeaf)."'),";
		$sql .= " fullpath_orig = REPLACE(fullpath_orig, '".$this->db->escape($oldLeaf)."', '".$this->db->escape($newLeaf)."')";
		$sql .= ' WHERE entity = '.((int) $vehicle->entity).' AND src_object_id = '.((int) $vehicle->id);
		$sql .= ' AND src_object_type IN ('.implode(', ', $quotedTypes).')';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		return 1;
	}

	/** @param string $path Path @param string $oldRef Old ref @param string $newRef New ref @return string */
	public function replaceReferenceInPath($path, $oldRef, $newRef)
	{
		if ($path === '') {
			return '';
		}

		return str_replace(array($oldRef, dol_sanitizeFileName($oldRef)), array($newRef, dol_sanitizeFileName($newRef)), $path);
	}

	/**
	 * @param string $targetModel Numbering model class
	 * @param int $entity Entity id
	 * @return array<int,array{vehicle:LmdbVehicle,old_ref:string,new_ref:string,temp_ref?:string}>|int<-1>
	 */
	private function buildPlans($targetModel, $entity)
	{
		if (!in_array($targetModel, array('mod_lmdbvehicle_standard', 'mod_lmdbvehicle_registration'), true)) {
			$this->error = 'ErrorBadValueForParameter';
			$this->errors[] = $this->error;
			return -1;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle';
		$sql .= ' WHERE entity = '.((int) $entity).' ORDER BY date_creation ASC, rowid ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
		$plans = array();
		$used = array();
		$sequence = 0;
		while (is_object($row = $this->db->fetch_object($resql))) {
			$vehicle = new LmdbVehicle($this->db);
			if ($vehicle->fetch((int) $row->rowid) <= 0) {
				$this->db->free($resql);
				$this->error = $vehicle->error !== '' ? $vehicle->error : 'RecordNotFound';
				$this->errors = $vehicle->errors;
				return -1;
			}
			$sequence++;
			if ($targetModel === 'mod_lmdbvehicle_registration') {
				$newRef = LmdbVehicle::normalizeRegistrationNumber((string) $vehicle->registration_number);
			} else {
				$date = !empty($vehicle->date_creation) ? (int) $vehicle->date_creation : dol_now();
				$newRef = 'VEH'.dol_print_date($date, '%y%m').'-'.($sequence >= 10000 ? (string) $sequence : sprintf('%04u', $sequence));
			}
			if ($newRef === '') {
				$this->db->free($resql);
				$this->error = 'RegistrationNumberRequiredForReference';
				$this->errors[] = $this->error;
				return -1;
			}
			$uniqueKey = strtoupper($newRef);
			if (isset($used[$uniqueKey])) {
				$this->db->free($resql);
				$this->error = 'VehicleReferenceMigrationConflict';
				$this->errors[] = $newRef;
				return -1;
			}
			$used[$uniqueKey] = (int) $vehicle->id;
			$plans[] = array('vehicle' => $vehicle, 'old_ref' => (string) $vehicle->ref, 'new_ref' => $newRef);
		}
		$this->db->free($resql);

		return $plans;
	}

	/**
	 * Detect target directories that are not released by the same migration.
	 *
	 * @param array<int,array{vehicle:LmdbVehicle,old_ref:string,new_ref:string,temp_ref?:string}> $plans Migration plans
	 * @return array<int,string>
	 */
	private function findDirectoryConflicts($plans)
	{
		$sourceDirectories = array();
		foreach ($plans as $plan) {
			$sourceDirectory = getMultidirOutput($plan['vehicle'], 'lmdbvehiclemanagement', 1);
			if (is_string($sourceDirectory) && $sourceDirectory !== '') {
				$sourceDirectories[$sourceDirectory] = true;
			}
		}
		$conflicts = array();
		foreach ($plans as $plan) {
			if ($plan['old_ref'] === $plan['new_ref']) {
				continue;
			}
			$targetVehicle = clone $plan['vehicle'];
			$targetVehicle->ref = $plan['new_ref'];
			$targetDirectory = getMultidirOutput($targetVehicle, 'lmdbvehiclemanagement', 1);
			if (is_string($targetDirectory) && dol_is_dir($targetDirectory) && !isset($sourceDirectories[$targetDirectory])) {
				$conflicts[] = $targetDirectory;
			}
		}

		return $conflicts;
	}

	/** @param LmdbVehicle $source Source @param LmdbVehicle $target Target @return int<-1,1> */
	private function moveVehicleDirectory($source, $target)
	{
		$sourceDir = getMultidirOutput($source, 'lmdbvehiclemanagement', 1);
		$targetDir = getMultidirOutput($target, 'lmdbvehiclemanagement', 1);
		if (!$this->validVehicleDirectory($sourceDir, (string) $source->ref) || !$this->validVehicleDirectory($targetDir, (string) $target->ref)) {
			$this->error = 'ErrorInvalidDirectory';
			$this->errors[] = $this->error;
			return -1;
		}
		if ($sourceDir === $targetDir || !dol_is_dir($sourceDir)) {
			return 1;
		}
		if (dol_is_dir($targetDir)) {
			$this->error = 'VehicleDocumentDirectoryConflict';
			$this->errors[] = $targetDir;
			return -1;
		}
		if (dol_mkdir(dirname($targetDir)) < 0 || !@rename(dol_osencode($sourceDir), dol_osencode($targetDir))) {
			$this->error = 'ErrorFailToRenameDir';
			$this->errors[] = $sourceDir.' -> '.$targetDir;
			return -1;
		}
		$this->moveJournal[] = array('from' => $sourceDir, 'to' => $targetDir, 'type' => 'directory');

		return 1;
	}

	/** @param LmdbVehicle $vehicle Vehicle @param string $oldRef Old ref @param string $newRef New ref @return int<-1,1> */
	private function renameDocumentFiles($vehicle, $oldRef, $newRef)
	{
		$directory = getMultidirOutput($vehicle, 'lmdbvehiclemanagement', 1);
		if (!dol_is_dir($directory)) {
			return 1;
		}
		$oldLeaf = dol_sanitizeFileName($oldRef);
		$newLeaf = dol_sanitizeFileName($newRef);
		foreach (dol_dir_list($directory, 'files', 1, '', null, 'fullname', SORT_ASC, 0, 1) as $file) {
			$name = (string) $file['name'];
			$newName = str_replace(array($oldRef, $oldLeaf), array($newRef, $newLeaf), $name);
			if ($newName === $name) {
				continue;
			}
			$destination = (string) $file['path'].'/'.$newName;
			if (dol_is_file($destination) || !dol_move((string) $file['fullname'], $destination, '0', 0, 0, 0)) {
				$this->error = 'ErrorFailToRenameFile';
				$this->errors[] = (string) $file['fullname'].' -> '.$destination;
				return -1;
			}
			$this->moveJournal[] = array('from' => (string) $file['fullname'], 'to' => $destination, 'type' => 'file');
		}

		return 1;
	}

	/** @param string $directory Directory @param string $ref Expected ref @return bool */
	private function validVehicleDirectory($directory, $ref)
	{
		return is_string($directory) && $directory !== '' && strpos($directory, 'error-diroutput-') !== 0
			&& dol_sanitizeFileName($ref) !== '' && basename($directory) === dol_sanitizeFileName($ref);
	}

	/** @param string $message Error @return int<-1> */
	private function rollbackWithError($message)
	{
		if ($message !== '') {
			$this->error = $message;
			if (!in_array($message, $this->errors, true)) {
				$this->errors[] = $message;
			}
		}
		$this->db->rollback();
		$this->rollbackFilesystem();

		return -1;
	}
}
