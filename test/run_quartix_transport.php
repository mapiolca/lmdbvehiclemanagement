<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

// Invoked by run_quartix_transport.py against its loopback HTTPS fixture only.
if (PHP_SAPI !== 'cli' || count($argv) !== 4 || !ctype_digit($argv[2]) || !is_file($argv[3])) {
	fwrite(STDERR, "Run test/run_quartix_transport.py <Dolibarr htdocs> instead.\n"); exit(2);
}
// Reuse the native bootstrap and in-memory SQL adapter, never a business database.
require __DIR__.'/run_quartix.php';

final class QxWireClient extends LmdbVehicleQuartixClient
{
	/** @var int */ private $port;
	/** @var string */ private $certificate;
	/** @var int */ public $connections = 0;
	/** @param QxTestDb $db Test database @param int $port Loopback port @param string $certificate Fixture CA */
	public function __construct($db, $port, $certificate)
	{
		parent::__construct($db, 1);
		$this->port = $port;
		$this->certificate = $certificate;
	}
	/** @param string $url Canonical QWS URL @return CurlHandle|false */
	protected function createCurlHandle($url)
	{
		$curl = parent::createCurlHandle($url);
		if ($curl !== false) {
			// Keep the QWS hostname and production TLS verification, route only to localhost.
			curl_setopt_array($curl, array(
				CURLOPT_CONNECT_TO => array('qws.quartix.net:443:127.0.0.1:'.$this->port),
				CURLOPT_CAINFO => $this->certificate, CURLOPT_PROXY => '',
			));
			$this->connections++;
		}
		return $curl;
	}
	/** @return void */
	public function invalidUtf8()
	{
		$this->request('POST', '/auth', array('Password' => "\xB1\x31"), '');
	}
}

$configuration->save($user, array('CUSTOMER' => 'test-company', 'USERNAME' => 'Test +é & "QWS"',
	'PASSWORD' => 'fake +&="\\é/', 'TIME_MODE' => 'offset', 'DURATION_UNIT' => ''));
$conf->global->MAIN_PROXY_USE = 0;
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_job SET retry_at=NULL WHERE entity=1');
$wire = new QxWireClient($db, (int) $argv[2], $argv[3]);
$checksBefore = $checks;
qxCheck($wire->get('/vehicles', array('VehicleIDList' => '10,20')) === array(array('VehicleID' => 10)), 'Real cURL auth, token refresh and catalogue read');
qxCheck($wire->connections === 4 && $wire->getDiagnostic()['http_status'] === 200, 'Exactly four HTTPS requests: auth, expired read, refresh, successful read');
qxReject(static function () use ($wire) { $wire->invalidUtf8(); }, 'QxInvalidSettings');
qxCheck($wire->connections === 4, 'Invalid UTF-8 never opens a connection or sends an empty JSON payload');
echo ($checks - $checksBefore)." additional real cURL checks passed against the local HTTPS fixture.\n";
