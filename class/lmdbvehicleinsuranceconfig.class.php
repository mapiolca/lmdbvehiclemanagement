<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Entity-scoped insurance reminder configuration and recipient resolver. */
class LmdbVehicleInsuranceConfig
{
	public const CONST_ENABLED = 'LMDBVEHICLEMANAGEMENT_INSURANCE_REMINDERS_ENABLED';
	public const CONST_INCLUDE_ASSIGNEES = 'LMDBVEHICLEMANAGEMENT_INSURANCE_INCLUDE_ASSIGNEES';
	public const CONST_ASSIGNMENT_TYPES = 'LMDBVEHICLEMANAGEMENT_INSURANCE_ASSIGNMENT_TYPES';
	public const CONST_BEFORE_DAYS = 'LMDBVEHICLEMANAGEMENT_INSURANCE_BEFORE_DAYS';
	public const CONST_OVERDUE_REPEAT = 'LMDBVEHICLEMANAGEMENT_INSURANCE_OVERDUE_REPEAT_DAYS';
	public const CONST_REVIEW_REPEAT = 'LMDBVEHICLEMANAGEMENT_INSURANCE_REVIEW_REPEAT_DAYS';
	public const CONST_REQUEST_TEMPLATE = 'LMDBVEHICLEMANAGEMENT_INSURANCE_REQUEST_TEMPLATE';
	public const CONST_REVIEW_TEMPLATE = 'LMDBVEHICLEMANAGEMENT_INSURANCE_REVIEW_TEMPLATE';
	public const REQUEST_TEMPLATE_TYPE = 'lmdbvehicle_insurance_request';
	public const REVIEW_TEMPLATE_TYPE = 'lmdbvehicle_insurance_review';

	/** @var DoliDB */ private $db;

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/** @return list<int> */
	public function getRecipientUserIds($entity)
	{
		return $this->getIds('lmdbvehiclemanagement_insurance_recipient_user', 'fk_user', $entity);
	}

	/** @return list<int> */
	public function getRecipientGroupIds($entity)
	{
		return $this->getIds('lmdbvehiclemanagement_insurance_recipient_group', 'fk_usergroup', $entity);
	}

	/**
	 * Persist normalized recipient relations.
	 *
	 * @param list<int> $userIds User ids
	 * @param list<int> $groupIds Group ids
	 * @param int $entity Entity id
	 * @param User $author Author
	 * @param bool $manageTransaction Manage the database transaction
	 * @return int<-1,1>
	 */
	public function saveRecipients($userIds, $groupIds, $entity, User $author, $manageTransaction = true)
	{
		$userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
		$groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
		if ($manageTransaction) {
			$this->db->begin();
		}
		foreach (array('lmdbvehiclemanagement_insurance_recipient_user', 'lmdbvehiclemanagement_insurance_recipient_group') as $table) {
			if (!$this->db->query('DELETE FROM '.MAIN_DB_PREFIX.$table.' WHERE entity = '.((int) $entity))) {
				if ($manageTransaction) $this->db->rollback();
				return -1;
			}
		}
		foreach ($userIds as $userId) {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_recipient_user (entity, fk_user, date_creation, fk_user_creat)';
			$sql .= ' SELECT '.((int) $entity).', u.rowid, \''.$this->db->idate(dol_now()).'\', '.((int) $author->id);
			$sql .= ' FROM '.MAIN_DB_PREFIX.'user AS u WHERE u.rowid = '.((int) $userId).' AND u.statut = 1 AND u.entity IN ('.getEntity('user').')';
			if (!$this->db->query($sql)) {
				if ($manageTransaction) $this->db->rollback();
				return -1;
			}
		}
		foreach ($groupIds as $groupId) {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_insurance_recipient_group (entity, fk_usergroup, date_creation, fk_user_creat)';
			$sql .= ' SELECT '.((int) $entity).', g.rowid, \''.$this->db->idate(dol_now()).'\', '.((int) $author->id);
			$sql .= ' FROM '.MAIN_DB_PREFIX.'usergroup AS g WHERE g.rowid = '.((int) $groupId).' AND g.entity IN ('.getEntity('usergroup').')';
			if (!$this->db->query($sql)) {
				if ($manageTransaction) $this->db->rollback();
				return -1;
			}
		}
		if ($manageTransaction) {
			$this->db->commit();
		}

		return 1;
	}

	/** @return list<int> */
	public static function getBeforeDays()
	{
		return self::decodePositiveIntegerList(getDolGlobalString(self::CONST_BEFORE_DAYS, '[30,15,7,1]'), array(30, 15, 7, 1));
	}

	/** @return list<string> */
	public static function getAssignmentTypes()
	{
		$decoded = json_decode(getDolGlobalString(self::CONST_ASSIGNMENT_TYPES, '["driver"]'), true);
		$allowed = array('driver', 'custodian', 'pool');
		$result = array();
		if (is_array($decoded)) {
			foreach ($decoded as $value) {
				if (is_string($value) && in_array($value, $allowed, true)) {
					$result[] = $value;
				}
			}
		}

		return array_values(array_unique($result));
	}

	/**
	 * Check the configurable business scope of an upload user.
	 *
	 * Native permission checks remain the responsibility of the caller.
	 *
	 * @param User $user User
	 * @param int $vehicleId Vehicle id
	 * @param int $entity Vehicle entity
	 * @return bool
	 */
	public function userIsEligibleForVehicle(User $user, $vehicleId, $entity)
	{
		if (in_array((int) $user->id, $this->getRecipientUserIds($entity), true)) {
			return true;
		}
		$groupIds = $this->getRecipientGroupIds($entity);
		if (!empty($groupIds)) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'usergroup_user WHERE fk_user = '.((int) $user->id);
			$sql .= ' AND fk_usergroup IN ('.implode(',', $groupIds).') AND entity = '.((int) $entity).' LIMIT 1';
			$resql = $this->db->query($sql);
			if ($resql) {
				$found = $this->db->num_rows($resql) > 0;
				$this->db->free($resql);
				if ($found) {
					return true;
				}
			}
		}
		if (!getDolGlobalInt(self::CONST_INCLUDE_ASSIGNEES)) {
			return false;
		}
		$types = self::getAssignmentTypes();
		if (empty($types)) {
			return false;
		}
		$quoted = array();
		foreach ($types as $type) {
			$quoted[] = "'".$this->db->escape($type)."'";
		}
		$now = $this->db->idate(dol_now());
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_assignment';
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_vehicle = '.((int) $vehicleId).' AND fk_user_driver = '.((int) $user->id);
		$sql .= ' AND status = 1 AND assignment_type IN ('.implode(',', $quoted).')';
		$sql .= " AND date_start <= '".$now."' AND (date_end IS NULL OR date_end >= '".$now."') LIMIT 1";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$found = $this->db->num_rows($resql) > 0;
		$this->db->free($resql);

		return $found;
	}

	/**
	 * Resolve and deduplicate recipient addresses.
	 *
	 * @param int $vehicleId Vehicle id
	 * @param int $entity Entity id
	 * @param bool $includeAssignees Include configured active assignments
	 * @return array<string,array{id:int,name:string,email:string}>
	 */
	public function getRecipientEmails($vehicleId, $entity, $includeAssignees)
	{
		$userIds = $this->getRecipientUserIds($entity);
		$groupIds = $this->getRecipientGroupIds($entity);
		if (!empty($groupIds)) {
			$sql = 'SELECT DISTINCT fk_user FROM '.MAIN_DB_PREFIX.'usergroup_user WHERE entity = '.((int) $entity).' AND fk_usergroup IN ('.implode(',', $groupIds).')';
			$resql = $this->db->query($sql);
			if ($resql) {
				while (is_object($row = $this->db->fetch_object($resql))) {
					$userIds[] = (int) $row->fk_user;
				}
				$this->db->free($resql);
			}
		}
		if ($includeAssignees && getDolGlobalInt(self::CONST_INCLUDE_ASSIGNEES)) {
			$types = self::getAssignmentTypes();
			if (!empty($types)) {
				$quoted = array();
				foreach ($types as $type) {
					$quoted[] = "'".$this->db->escape($type)."'";
				}
				$now = $this->db->idate(dol_now());
				$sql = 'SELECT DISTINCT fk_user_driver FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle_assignment';
				$sql .= ' WHERE entity = '.((int) $entity).' AND fk_vehicle = '.((int) $vehicleId).' AND status = 1';
				$sql .= ' AND assignment_type IN ('.implode(',', $quoted).')';
				$sql .= " AND date_start <= '".$now."' AND (date_end IS NULL OR date_end >= '".$now."')";
				$resql = $this->db->query($sql);
				if ($resql) {
					while (is_object($row = $this->db->fetch_object($resql))) {
						$userIds[] = (int) $row->fk_user_driver;
					}
					$this->db->free($resql);
				}
			}
		}
		$userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
		if (empty($userIds)) {
			return array();
		}
		$sql = 'SELECT rowid, firstname, lastname, login, email FROM '.MAIN_DB_PREFIX.'user';
		$sql .= ' WHERE rowid IN ('.implode(',', $userIds).') AND statut = 1 AND email IS NOT NULL AND email <> \'\'';
		$sql .= ' ORDER BY FIELD(rowid, '.implode(',', $userIds).')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return array();
		}
		$recipients = array();
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

	/** @return list<int> */
	private function getIds($table, $column, $entity)
	{
		$sql = 'SELECT '.$column.' FROM '.MAIN_DB_PREFIX.$table.' WHERE entity = '.((int) $entity).' ORDER BY '.$column;
		$resql = $this->db->query($sql);
		if (!$resql) {
			return array();
		}
		$result = array();
		while (is_object($row = $this->db->fetch_object($resql))) {
			$result[] = (int) $row->{$column};
		}
		$this->db->free($resql);

		return $result;
	}

	/** @param string $json Encoded list @param list<int> $fallback Fallback @return list<int> */
	private static function decodePositiveIntegerList($json, $fallback)
	{
		$decoded = json_decode($json, true);
		if (!is_array($decoded)) {
			return $fallback;
		}
		$result = array_values(array_unique(array_filter(array_map('intval', $decoded), static function ($value) {
			return $value > 0;
		})));

		return empty($result) ? $fallback : $result;
	}
}
