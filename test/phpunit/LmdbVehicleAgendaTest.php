<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/class/lmdbvehicleagenda.class.php';

/**
 * Verify the native Agenda contract without requiring a Dolibarr database.
 */
final class LmdbVehicleAgendaTest extends TestCase
{
	/** @var string */
	private $moduleRoot;

	protected function setUp(): void
	{
		$this->moduleRoot = dirname(__DIR__, 2);
	}

	public function testCrudMatrixContainsSevenObjectsAndTwentyOneTriggers(): void
	{
		$objects = LmdbVehicleAgenda::getObjectDefinitions();
		$triggers = LmdbVehicleAgenda::getTriggerDefinitions();

		self::assertCount(7, $objects);
		self::assertCount(21, $triggers);
		foreach ($objects as $object) {
			foreach (array('CREATE', 'UPDATE', 'DELETE') as $operation) {
				$code = $object['trigger_prefix'].'_'.$operation;
				self::assertArrayHasKey($code, $triggers);
				self::assertSame($object['elementtype'], $triggers[$code]['elementtype']);
				self::assertSame($operation, $triggers[$code]['operation']);
			}
		}
	}

	public function testEveryTriggerIsDeclaredIdempotentlyAndTranslated(): void
	{
		$sql = $this->readFile('sql/data.sql');
		$fr = $this->readFile('langs/fr_FR/lmdbvehiclemanagement.lang');
		$en = $this->readFile('langs/en_US/lmdbvehiclemanagement.lang');

		foreach (LmdbVehicleAgenda::getTriggerDefinitions() as $code => $definition) {
			self::assertSame(1, substr_count($sql, "SELECT '".$definition['elementtype']."', '".$code."'"));
			self::assertSame(1, substr_count($sql, "WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = '".$code."')"));
			self::assertMatchesRegularExpression('/^Notify_'.preg_quote($code, '/').'=.+$/m', $fr);
			self::assertMatchesRegularExpression('/^Notify_'.preg_quote($code, '/').'=.+$/m', $en);
			self::assertMatchesRegularExpression('/_(CREATE|UPDATE|DELETE)$/', $code);
		}
	}

	public function testEveryObjectUsesAnExplicitCrudMessage(): void
	{
		$expected = array(
			'lmdbvehicle' => array('AgendaVehicleCreated', 'AgendaVehicleUpdated', 'AgendaVehicleDeleted'),
			'lmdbvehicleassignment' => array('AgendaAssignmentCreated', 'AgendaAssignmentUpdated', 'AgendaAssignmentDeleted'),
			'lmdbvehicleodometerreading' => array('AgendaOdometerCreated', 'AgendaOdometerUpdated', 'AgendaOdometerDeleted'),
			'lmdbvehicleconsumption' => array('AgendaConsumptionCreated', 'AgendaConsumptionUpdated', 'AgendaConsumptionDeleted'),
			'lmdbvehicleevent' => array('AgendaVehicleEventCreated', 'AgendaVehicleEventUpdated', 'AgendaVehicleEventDeleted'),
			'lmdbinsurancecontract' => array('AgendaInsuranceContractCreated', 'AgendaInsuranceContractUpdated', 'AgendaInsuranceContractDeleted'),
			'lmdbinsurancecertificate' => array('AgendaInsuranceCertificateCreated', 'AgendaInsuranceCertificateUpdated', 'AgendaInsuranceCertificateDeleted'),
		);

		foreach ($expected as $element => $keys) {
			foreach (array('CREATE', 'UPDATE', 'DELETE') as $index => $operation) {
				$definition = LmdbVehicleAgenda::getMessageDefinition($element, $operation);
				self::assertSame($keys[$index], $definition['key']);
			}
		}
	}

	public function testBusinessTransitionsUseSpecificMessages(): void
	{
		$cases = array(
			array('lmdbvehicle', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 2), 'AgendaVehiclePutInService'),
			array('lmdbvehicle', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 4), 'AgendaVehicleSold'),
			array('lmdbinsurancecontract', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 1), 'AgendaInsuranceContractActivated'),
			array('lmdbinsurancecontract', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 9), 'AgendaInsuranceContractTerminated'),
			array('lmdbinsurancecontract', 'UPDATE', array('trigger_reason' => 'vehicle_link'), 'AgendaInsuranceVehicleLinked'),
			array('lmdbinsurancecertificate', 'CREATE', array('trigger_reason' => 'create_and_submit'), 'AgendaInsuranceCertificateUploadedAndSubmitted'),
			array('lmdbinsurancecertificate', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 2), 'AgendaInsuranceCertificateValidated'),
			array('lmdbinsurancecertificate', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 3), 'AgendaInsuranceCertificateRejected'),
			array('lmdbinsurancecertificate', 'UPDATE', array('trigger_reason' => 'status_change', 'new_status' => 9), 'AgendaInsuranceCertificateArchived'),
		);

		foreach ($cases as $case) {
			$definition = LmdbVehicleAgenda::getMessageDefinition($case[0], $case[1], $case[2]);
			self::assertSame($case[3], $definition['key']);
		}
	}

	public function testEveryAgendaMessageIsTranslatedInFrenchAndEnglish(): void
	{
		$agendaClass = $this->readFile('class/lmdbvehicleagenda.class.php');
		$fr = $this->readFile('langs/fr_FR/lmdbvehiclemanagement.lang');
		$en = $this->readFile('langs/en_US/lmdbvehiclemanagement.lang');
		preg_match_all("/'(Agenda[A-Za-z0-9]+)'/", $agendaClass, $matches);

		foreach (array_unique($matches[1]) as $translationKey) {
			self::assertMatchesRegularExpression('/^'.preg_quote($translationKey, '/').'=.+$/m', $fr);
			self::assertMatchesRegularExpression('/^'.preg_quote($translationKey, '/').'=.+$/m', $en);
		}
	}

	public function testAgendaDefaultsAreConservativeAndNoManualActionCommIsCreated(): void
	{
		$descriptor = $this->readFile('core/modules/modLmdbVehicleManagement.class.php');
		$trigger = $this->readFile('core/triggers/interface_99_modLmdbVehicleManagement_LmdbVehicleManagementTriggers.class.php');

		self::assertStringContainsString("'MAIN_AGENDA_ACTIONAUTO_'.\$triggerCode", $descriptor);
		self::assertStringContainsString('if ($constantExists === 0)', $descriptor);
		self::assertStringNotContainsString('new ActionComm', $trigger);
		self::assertStringNotContainsString("MAIN_DB_PREFIX.'actioncomm'", $trigger);
	}

	private function readFile(string $relativePath): string
	{
		$content = file_get_contents($this->moduleRoot.'/'.$relativePath);
		self::assertNotFalse($content, 'Unable to read '.$relativePath);

		return $content;
	}
}
