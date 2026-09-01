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
