<?php
require_once __DIR__.'/lmdbvehiclesharing.class.php';
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';

/** Entity-scoped configuration and access policy for the QUARTIX integration. */
class LmdbVehicleQuartixConfig
{
	public const PREFIX = 'LMDBVEHICLEMANAGEMENT_QX_';
	public const TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
	public const TILE_ATTRIBUTION = '© OpenStreetMap contributors';
	/** @var DoliDB */ private $db;
	/** @param DoliDB $db Database */
	public function __construct($db) { $this->db = $db; }

	/** @param User $user User @return bool */
	public static function isAdmin($user)
	{
		// Multicompany entity administrators are exposed as admin in their active entity.
		return empty($user->socid) && !empty($user->admin);
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
		if ($feature === 'routes') {
			require_once __DIR__.'/lmdbvehiclequartixroutes.class.php';
			return LmdbVehicleQuartixRoutes::unavailable(array('ROUTES_ENABLED' => getDolGlobalString(self::PREFIX.'ROUTES_ENABLED'), 'TILE_URL' => getDolGlobalString(self::PREFIX.'TILE_URL', self::TILE_URL), 'TILE_ATTRIBUTION' => getDolGlobalString(self::PREFIX.'TILE_ATTRIBUTION', self::TILE_ATTRIBUTION)));
		} elseif ($feature === 'jobs') {
			if (!isModEnabled('cron')) return 'RequiresCronModule';
			if (!getDolGlobalInt(self::PREFIX.'ENABLED')) return 'QxDisabled';
			try { self::validateApplication(getDolGlobalString(self::PREFIX.'APPLICATION')); }
			catch (RuntimeException $e) { return $e->getMessage(); }
		} elseif ($feature === 'timestamps' && !in_array(getDolGlobalString(self::PREFIX.'TIME_MODE'), array('local', 'offset', 'qws'), true)) {
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
		$keys = array('ENABLED', 'TIME_MODE', 'DURATION_UNIT', 'TRIP_RETENTION_DAYS', 'ROUTES_ENABLED', 'TILE_URL', 'TILE_ATTRIBUTION');
		if ($secrets) {
			if ($entity !== (int) $conf->entity) throw new RuntimeException('QxAccessDenied');
			$keys = array_merge($keys, array('CUSTOMER', 'USERNAME', 'PASSWORD', 'APPLICATION'));
		}
		$result = array_fill_keys($keys, '');
		$result['TRIP_RETENTION_DAYS'] = '30';
		$result['TILE_URL'] = self::TILE_URL;
		$result['TILE_ATTRIBUTION'] = self::TILE_ATTRIBUTION;
		if ($entity === (int) $conf->entity) {
			foreach ($keys as $key) $result[$key] = getDolGlobalString(self::PREFIX.$key, $result[$key]);
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

	/** Validate the provider-assigned application name before saving or making requests. @param string $application QWS name @return void */
	public static function validateApplication($application)
	{
		if (trim($application) === '') throw new RuntimeException('QxApplicationRequired');
		if (strlen($application) > 128 || preg_match('/[\x00-\x1f\x7f]/', $application)) throw new RuntimeException('QxInvalidSettings');
	}

	/** Browser-only tile template. Never evaluated as JS or fetched by the PHP server. @param string $url HTTPS template @param string $attribution Plain text @return void */
	public static function validateTiles($url, $attribution)
	{
		$parsed = parse_url(str_replace(array('{z}', '{x}', '{y}'), '0', $url));
		if (!is_array($parsed) || ($parsed['scheme'] ?? '') !== 'https' || empty($parsed['host']) || isset($parsed['user']) || isset($parsed['pass']) || isset($parsed['fragment'])
			|| strlen($url) > 1024 || preg_match('/[\x00-\x20\x7f<>"\x27]/', $url)
			|| strpos($url, '{z}') === false || strpos($url, '{x}') === false || strpos($url, '{y}') === false
			|| preg_match('/[{}]/', str_replace(array('{z}', '{x}', '{y}'), '', $url))
			|| trim($attribution) === '' || strlen($attribution) > 255 || strip_tags($attribution) !== $attribution || preg_match('/[\x00-\x1f\x7f]/', $attribution)) throw new RuntimeException('QxInvalidTileSettings');
	}

	/** @param User $user Administrator @param array<string,string> $values Form settings @return void */
	public function save($user, $values)
	{
		global $conf;
		if (!(isModEnabled('lmdbvehiclemanagement') && empty($user->socid) && $user->hasRight('lmdbvehiclemanagement', 'read') && !empty($user->admin))) throw new RuntimeException('QxAccessDenied');
		self::validateApplication($values['APPLICATION'] ?? '');
		if (!in_array($values['TIME_MODE'], array('', 'offset', 'local', 'qws'), true) || !in_array($values['DURATION_UNIT'], array('', 'seconds', 'minutes', 'hours'), true)) throw new RuntimeException('QxInvalidSettings');
		foreach (array('CUSTOMER', 'USERNAME') as $key) {
			if ($values[$key] === '' || strlen($values[$key]) > 128 || preg_match('/[\x00-\x1f]/', $values[$key])) throw new RuntimeException('QxInvalidSettings');
		}
		$old = $this->load((int) $conf->entity, true);
		require_once __DIR__.'/lmdbvehiclequartixtrips.class.php';
		$values['TRIP_RETENTION_DAYS'] = $values['TRIP_RETENTION_DAYS'] ?? $old['TRIP_RETENTION_DAYS'];
		LmdbVehicleQuartixTrips::retention($values['TRIP_RETENTION_DAYS']);
		foreach (array('TILE_URL', 'TILE_ATTRIBUTION') as $key) $values[$key] = $values[$key] ?? $old[$key];
		self::validateTiles($values['TILE_URL'], $values['TILE_ATTRIBUTION']);
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
			foreach (array('CUSTOMER', 'USERNAME', 'PASSWORD', 'APPLICATION', 'TIME_MODE', 'DURATION_UNIT', 'TRIP_RETENTION_DAYS', 'TILE_URL', 'TILE_ATTRIBUTION') as $key) {
				if (dolibarr_set_const($this->db, self::PREFIX.$key, $values[$key], 'chaine', 0, '', (int) $conf->entity) <= 0) throw new RuntimeException('QxDatabaseError');
			}
			if (!$this->db->query('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_token WHERE entity = '.((int) $conf->entity))) throw new RuntimeException('QxDatabaseError');
			$this->db->commit();
		} catch (Exception $e) { $this->db->rollback(); throw $e; }
	}
}
