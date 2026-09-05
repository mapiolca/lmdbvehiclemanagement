<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/lmdbvehiclequartixconfig.class.php';
require_once __DIR__.'/lmdbvehiclequartixrules.class.php';

/**
 * QWS v2 read client. Dedicated transport is necessary: getURLContent in v20 logs
 * POST credentials and AccessToken headers. Never log raw requests/responses here.
 */
class LmdbVehicleQuartixClient
{
	public const BASE = 'https://qws.quartix.net/v2/api';
	/** @var DoliDB */ private $db;
	/** @var int */ private $entity;
	/** @var array<string,string> */ private $config;
	/** @var string */ private $access = '';
	/** @var string */ private $refresh = '';
	/** @var int Seconds before another request is permitted */ public $retryAfter = 0;
	/** @var string Fixed endpoint, without query parameters */ private $lastEndpoint = '';
	/** @var int HTTP status, or zero when no response was received */ private $lastHttpStatus = 0;
	/** @var int cURL error number, without its potentially sensitive message */ private $lastCurlError = 0;

	/** @param DoliDB $db Database @param int $entity Current entity */
	public function __construct($db, $entity)
	{
		global $user;
		if (!LmdbVehicleQuartixConfig::can($user, 'sync') && !LmdbVehicleQuartixConfig::can($user, 'configure')) throw new RuntimeException('QxAccessDenied');
		$this->db = $db;
		$this->entity = $entity;
		$this->config = (new LmdbVehicleQuartixConfig($db))->load($entity, true);
	}

	/** @param string $path Allowed read endpoint @param array<string,int|string> $query Parameters @return array<int,mixed> */
	public function get($path, $query = array())
	{
		$this->lastEndpoint = ''; $this->lastHttpStatus = 0; $this->lastCurlError = 0;
		if (!in_array($path, array('/vehicles', '/vehicles/live', '/vehicles/odometer', '/vehicles/tripsummary'), true)) throw new RuntimeException('QxInvalidEndpoint');
		$res = $this->db->query('SELECT MAX(retry_at) AS retry_at FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_job WHERE entity='.$this->entity);
		if (!$res) throw new RuntimeException('QxDatabaseError');
		$throttle = $this->db->fetch_object($res);
		$this->db->free($res);
		if (is_object($throttle) && !empty($throttle->retry_at) && $this->db->jdate($throttle->retry_at) > dol_now()) throw new RuntimeException('QxRateLimited');
		if ($this->access === '') $this->loadTokens();
		if ($this->access === '') $this->authenticate(false);
		$response = $this->exchange('GET', $path, $query, $this->access);
		if ($response['status'] === 401) {
			$this->authenticate($this->refresh !== '');
			$response = $this->exchange('GET', $path, $query, $this->access);
		}
		$data = $this->decode($response);
		if (!is_array($data) || array_keys($data) !== range(0, count($data) - 1) && $data !== array()) throw new RuntimeException('QxInvalidResponse');
		if (!$this->db->query('UPDATE '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_job SET retry_at=NULL,last_error=NULL WHERE entity=".$this->entity." AND job_kind='api'")) throw new RuntimeException('QxDatabaseError');
		return $data;
	}

	/** @return void */
	private function loadTokens()
	{
		$res = $this->db->query('SELECT access_token, refresh_token FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_token WHERE entity = '.$this->entity);
		if (!$res) throw new RuntimeException('QxDatabaseError');
		$row = $this->db->fetch_object($res);
		$this->db->free($res);
		if (is_object($row)) {
			$this->access = dolDecrypt((string) $row->access_token);
			$this->refresh = dolDecrypt((string) $row->refresh_token);
		}
	}

	/** @param bool $refresh Refresh existing tokens @return void */
	private function authenticate($refresh)
	{
		$values = $refresh ? array('RefreshToken' => $this->refresh) : array('CustomerID' => $this->config['CUSTOMER'], 'UserName' => $this->config['USERNAME'], 'Password' => $this->config['PASSWORD'], 'Application' => 'lmdbvehiclemanagement');
		$response = $this->exchange('POST', $refresh ? '/auth/refresh' : '/auth', $values, '');
		if ($refresh && in_array($response['status'], array(401, 403), true)) { $this->authenticate(false); return; }
		$data = $this->decode($response);
		if (!is_array($data) || !isset($data['AccessToken'], $data['RefreshToken']) || !is_string($data['AccessToken']) || !is_string($data['RefreshToken'])) throw new RuntimeException('QxInvalidResponse');
		foreach (array('AccessToken', 'RefreshToken') as $key) if ($data[$key] === '' || strlen($data[$key]) > 16000 || preg_match('/[\x00-\x20\x7f]/', $data[$key])) throw new RuntimeException('QxInvalidResponse');
		$this->access = $data['AccessToken'];
		$this->refresh = $data['RefreshToken'];
		$access = $this->db->escape(LmdbVehicleQuartixConfig::encrypt($this->access));
		$token = $this->db->escape(LmdbVehicleQuartixConfig::encrypt($this->refresh));
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_token (entity,access_token,refresh_token) VALUES (".$this->entity.",'".$access."','".$token."') ON DUPLICATE KEY UPDATE access_token=VALUES(access_token),refresh_token=VALUES(refresh_token)";
		if (!$this->db->query($sql)) throw new RuntimeException('QxDatabaseError');
	}

	/** @param array{status:int,body:string,retry:int} $response HTTP response @return mixed Validated JSON Data */
	private function decode($response)
	{
		if ($response['status'] === 429) {
			$this->retryAfter = max(60, min(86400, $response['retry']));
			// Share a provider quota across scheduled jobs and manual connection tests.
			$until = $this->db->idate(dol_now() + $this->retryAfter);
			if (!$this->db->query('INSERT INTO '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_job (entity,job_kind,retry_at,last_error) VALUES (".$this->entity.",'api','".$until."','QxRateLimited') ON DUPLICATE KEY UPDATE retry_at=VALUES(retry_at),last_error=VALUES(last_error)")) throw new RuntimeException('QxDatabaseError');
			throw new RuntimeException('QxRateLimited');
		}
		if (in_array($response['status'], array(401, 403), true)) throw new RuntimeException('QxAuthenticationFailed');
		if ($response['status'] === 422) throw new RuntimeException('QxRequestRejected');
		if ($response['status'] !== 200) throw new RuntimeException('QxRemoteError');
		$data = json_decode($response['body'], true);
		if (!is_array($data) || !array_key_exists('Data', $data) || !isset($data['Meta']) || !is_array($data['Meta']) || !isset($data['Meta']['Code']) || !in_array($data['Meta']['Code'], array(0, 200), true)) throw new RuntimeException('QxInvalidResponse');
		return $data['Data'];
	}

	/** @return array{endpoint:string,http_status:int,curl_error:int} Metadata safe for support; never credentials or response content */
	public function getDiagnostic()
	{
		return array('endpoint' => $this->lastEndpoint, 'http_status' => $this->lastHttpStatus, 'curl_error' => $this->lastCurlError);
	}

	/** @param Translate $langs Output language @return string Safe detail for the native error message */
	public function getDiagnosticMessage($langs)
	{
		if ($this->lastEndpoint === '') return '';
		$parts = array();
		if ($this->lastHttpStatus > 0) $parts[] = $langs->transnoentities('QxHttpDiagnostic', $this->lastEndpoint, $this->lastHttpStatus);
		if ($this->lastCurlError > 0) $parts[] = $langs->transnoentities('QxNetworkDiagnostic', $this->lastEndpoint, $this->lastCurlError);
		return implode(' ', $parts);
	}

	/**
	 * Record only the fixed endpoint and numeric transport result, including auth failures.
	 * The protected transport remains replaceable in offline tests.
	 * @param string $method GET/POST @param string $path Allowed endpoint @param array<string,int|string> $values Parameters @param string $token Token
	 * @return array{status:int,body:string,retry:int}
	 */
	private function exchange($method, $path, $values, $token)
	{
		$this->lastEndpoint = $path;
		$this->lastHttpStatus = 0;
		$this->lastCurlError = 0;
		try {
			$response = $this->request($method, $path, $values, $token);
			$this->lastHttpStatus = $response['status'];
			return $response;
		} finally {
			dol_syslog('QUARTIX entity='.$this->entity.' endpoint='.$this->lastEndpoint.' http='.$this->lastHttpStatus.' curl='.$this->lastCurlError, $this->lastHttpStatus === 200 ? LOG_DEBUG : LOG_WARNING);
		}
	}

	/**
	 * Build and execute requests; HTTPS protocol tests keep this method intact. Fixed host, no redirects,
	 * verified TLS, bounded response and timeouts; no cookies or verbose cURL output.
	 * @param string $method GET/POST @param string $path Endpoint @param array<string,int|string> $values Parameters @param string $token Token
	 * @return array{status:int,body:string,retry:int}
	 */
	protected function request($method, $path, $values, $token)
	{
		if (!LmdbVehicleQuartixConfig::supported()) throw new RuntimeException('QxRequiresCrypto');
		// Send auth fields as one JSON object, including during token renewal.
		$payload = null;
		if ($method === 'POST') {
			$payload = json_encode($values, JSON_UNESCAPED_SLASHES);
			if ($payload === false) throw new RuntimeException('QxInvalidSettings');
		}
		$url = self::BASE.$path.($method === 'GET' && $values ? '?'.http_build_query($values, '', '&', PHP_QUERY_RFC3986) : '');
		$curl = $this->createCurlHandle($url);
		if ($curl === false) throw new RuntimeException('QxNetworkError');
		$body = '';
		$retry = 900;
		$options = array(CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CONNECTTIMEOUT => max(1, min(15, getDolGlobalInt('MAIN_USE_CONNECT_TIMEOUT', 5))), CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTPHEADER => array('Accept: application/json'), CURLOPT_VERBOSE => false,
			CURLOPT_WRITEFUNCTION => static function ($handle, $chunk) use (&$body) { if (strlen($body) + strlen($chunk) > 8388608) return 0; $body .= $chunk; return strlen($chunk); },
			CURLOPT_HEADERFUNCTION => static function ($handle, $line) use (&$retry) { if (stripos($line, 'Retry-After:') === 0) { $value = trim(substr($line, 12)); $retry = ctype_digit($value) ? (int) $value : max(60, (int) strtotime($value) - time()); } return strlen($line); });
		if ($token !== '') $options[CURLOPT_HTTPHEADER][] = 'AccessToken: '.$token;
		if ($method === 'POST') {
			$options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = $payload;
		}
		if (getDolGlobalInt('MAIN_PROXY_USE')) {
			$options[CURLOPT_PROXY] = getDolGlobalString('MAIN_PROXY_HOST');
			$options[CURLOPT_PROXYPORT] = getDolGlobalInt('MAIN_PROXY_PORT');
			$options[CURLOPT_PROXYUSERPWD] = getDolGlobalString('MAIN_PROXY_USER').':'.getDolGlobalString('MAIN_PROXY_PASS');
		}
		curl_setopt_array($curl, $options);
		$result = curl_exec($curl);
		$status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$this->lastHttpStatus = $status;
		$this->lastCurlError = curl_errno($curl);
		unset($curl); // PHP 8 uses CurlHandle objects; curl_close() has no effect.
		if ($result === false) throw new RuntimeException('QxNetworkError');
		return array('status' => $status, 'body' => $body, 'retry' => $retry);
	}

	/**
	 * Local HTTPS tests override connection routing and CA trust on this handle only.
	 * Production keeps the fixed QWS URL and the verified TLS options above.
	 * @param string $url Fixed QWS URL @return CurlHandle|false
	 */
	protected function createCurlHandle($url)
	{
		return curl_init($url);
	}
}
