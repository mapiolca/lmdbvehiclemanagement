<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';

/** Entity-scoped configuration and access policy for the QUARTIX integration. */
class LmdbVehicleQuartixConfig
{
	public const PREFIX = 'LMDBVEHICLEMANAGEMENT_QX_';
	/** @var DoliDB */ private $db;
	/** @param DoliDB $db Database */
	public function __construct($db) { $this->db = $db; }

	/** @param User $user User @return bool */
	public static function isAdmin($user)
	{
		// Multicompany entity administrators are exposed as admin in their active entity.
		return empty($user->socid) && !empty($user->admin);
	}

	/** @param User $user User @param string $action read, location, sync or configure @return bool */
	public static function can($user, $action)
	{
		if (!empty($user->socid) || !isModEnabled('lmdbvehiclemanagement')) return false;
		if (self::isAdmin($user)) return true;
		if ($action === 'configure' || !$user->hasRight('lmdbvehiclemanagement', 'read')) return false;
		return $action === 'read' || (in_array($action, array('location', 'sync'), true) && $user->hasRight('lmdbvehiclemanagement', 'quartix', $action));
	}

	/** @return bool Native encryption and transport exist on the supported baseline. */
	public static function supported()
	{
		global $conf;
		return version_compare(DOL_VERSION, '20.0.0', '>=') && version_compare(PHP_VERSION, '8.0', '>=')
			&& function_exists('curl_init') && function_exists('openssl_encrypt') && function_exists('dolEncrypt')
			&& !empty($conf->file->instance_unique_id);
	}

	/** Shared compatibility policy used by the registry and workers. @param string $feature Feature @return string Empty when available */
	public static function unavailableReason($feature)
	{
		if (!self::supported()) return 'QxRequiresCrypto';
		if ($feature === 'jobs') {
			if (!isModEnabled('cron')) return 'RequiresCronModule';
			if (!getDolGlobalInt(self::PREFIX.'ENABLED')) return 'QxDisabled';
		} elseif ($feature === 'timestamps' && !in_array(getDolGlobalString(self::PREFIX.'TIME_MODE'), array('local', 'offset'), true)) {
			return 'QxTimeUnconfirmed';
		} elseif ($feature === 'durations' && !in_array(getDolGlobalString(self::PREFIX.'DURATION_UNIT'), array('seconds', 'minutes', 'hours'), true)) {
			return 'QxDurationUnconfirmed';
		}
		return '';
	}

	/**
	 * Cross-entity reads use explicit SQL: native global helpers only expose the current entity.
	 * Callers must authorize the vehicle before requesting its owner's public settings.
	 * @param int $entity Owner entity @param bool $secrets Load credentials only for current entity
	 * @return array<string,string>
	 */
	public function load($entity, $secrets = false)
	{
		global $conf;
		$keys = array('ENABLED', 'TIME_MODE', 'DURATION_UNIT');
		if ($secrets) {
			if ($entity !== (int) $conf->entity) throw new RuntimeException('QxAccessDenied');
			$keys = array_merge($keys, array('CUSTOMER', 'USERNAME', 'PASSWORD'));
		}
		$result = array_fill_keys($keys, '');
		if ($entity === (int) $conf->entity) {
			foreach ($keys as $key) $result[$key] = getDolGlobalString(self::PREFIX.$key);
		} else {
			$names = array_map(static function ($key) { return "'".self::PREFIX.$key."'"; }, $keys);
			$res = $this->db->query('SELECT name, value FROM '.MAIN_DB_PREFIX.'const WHERE entity = '.((int) $entity).' AND name IN ('.implode(',', $names).')');
			if (!$res) throw new RuntimeException('QxDatabaseError');
			while (is_object($row = $this->db->fetch_object($res))) $result[substr((string) $row->name, strlen(self::PREFIX))] = (string) $row->value;
			$this->db->free($res);
		}
		if ($secrets && $result['PASSWORD'] !== '') $result['PASSWORD'] = dolDecrypt($result['PASSWORD']);
		return $result;
	}

	/** @param string $secret Secret @return string Ciphertext; plaintext fallback forbidden */
	public static function encrypt($secret)
	{
		if (!self::supported()) throw new RuntimeException('QxRequiresCrypto');
		$encrypted = dolEncrypt($secret);
		if (!is_string($encrypted) || strpos($encrypted, 'dolcrypt:') !== 0 || $encrypted === $secret) throw new RuntimeException('QxRequiresCrypto');
		return $encrypted;
	}

	/** @param User $user Administrator @param array<string,string> $values Form settings @return void */
	public function save($user, $values)
	{
		global $conf;
		if (!self::can($user, 'configure')) throw new RuntimeException('QxAccessDenied');
		if (!in_array($values['TIME_MODE'], array('', 'offset', 'local'), true) || !in_array($values['DURATION_UNIT'], array('', 'seconds', 'minutes', 'hours'), true)) throw new RuntimeException('QxInvalidSettings');
		foreach (array('CUSTOMER', 'USERNAME') as $key) {
			if ($values[$key] === '' || strlen($values[$key]) > 128 || preg_match('/[\x00-\x1f]/', $values[$key])) throw new RuntimeException('QxInvalidSettings');
		}
		$old = $this->load((int) $conf->entity, true);
		if ($values['PASSWORD'] === '') $values['PASSWORD'] = $old['PASSWORD'];
		if ($values['PASSWORD'] === '' || strlen($values['PASSWORD']) > 1024) throw new RuntimeException('QxInvalidSettings');
		if ($old['CUSTOMER'] !== '' && $old['CUSTOMER'] !== $values['CUSTOMER']) {
			$res = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link WHERE entity = '.((int) $conf->entity).' LIMIT 1');
			if (!$res) throw new RuntimeException('QxDatabaseError');
			$hasLinks = $this->db->num_rows($res) > 0;
			$this->db->free($res);
			if ($hasLinks) throw new RuntimeException('QxAccountInUse');
		}
		$values['PASSWORD'] = self::encrypt($values['PASSWORD']);
		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		$this->db->begin();
		try {
			foreach (array('CUSTOMER', 'USERNAME', 'PASSWORD', 'TIME_MODE', 'DURATION_UNIT') as $key) {
				if (dolibarr_set_const($this->db, self::PREFIX.$key, $values[$key], 'chaine', 0, '', (int) $conf->entity) <= 0) throw new RuntimeException('QxDatabaseError');
			}
			if (!$this->db->query('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_token WHERE entity = '.((int) $conf->entity))) throw new RuntimeException('QxDatabaseError');
			$this->db->commit();
		} catch (Exception $e) { $this->db->rollback(); throw $e; }
	}
}
