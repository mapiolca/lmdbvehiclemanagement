<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
require_once __DIR__.'/lmdbvehiclesharing.class.php';

/** Conservative native configuration migration. No record is individually shared by activation. */
class LmdbVehicleSharingMigration
{
	/** @param DoliDB $db Database @return int 1 or -1 */
	public static function run($db)
	{
		global $user;
		if (!LmdbVehicleSharing::available()) return 1;
		$db->begin();
		try {
			foreach (LmdbVehicleSharing::definitions() as $element => $definition) {
				if ($element === $definition['legacy']) continue;
				$oldName = 'MULTICOMPANY_'.strtoupper($definition['legacy']).'_SHARING_ENABLED';
				$newName = 'MULTICOMPANY_'.strtoupper($element).'_SHARING_ENABLED';
				$res = $db->query("SELECT value, entity FROM ".MAIN_DB_PREFIX."const WHERE name = '".$db->escape($oldName)."'");
				if (!$res) throw new RuntimeException('LmdbSharingDatabaseError');
				$values = array();
				while (is_object($row = $db->fetch_object($res))) $values[(int) $row->entity] = (string) $row->value;
				$db->free($res);
				foreach ($values as $entity => $value) {
					$exists = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."const WHERE name = '".$db->escape($newName)."' AND entity = ".$entity);
					if (!$exists) throw new RuntimeException('LmdbSharingDatabaseError');
					$found = is_object($db->fetch_object($exists)); $db->free($exists);
					if (!$found && dolibarr_set_const($db, $newName, $value, 'chaine', 0, '', $entity) <= 0) throw new RuntimeException('LmdbSharingDatabaseError');
				}
			}
			$dao = new DaoMulticompany($db);
			if ($dao->getEntities(true) < 0) throw new RuntimeException('LmdbSharingDatabaseError');
			foreach ($dao->entities as $entity) {
				$changed = false;
				if (!isset($entity->options['sharings']) || !is_array($entity->options['sharings'])) continue;
				foreach (LmdbVehicleSharing::definitions() as $element => $definition) {
					if (!array_key_exists($element, $entity->options['sharings']) && array_key_exists($definition['legacy'], $entity->options['sharings'])) {
						$entity->options['sharings'][$element] = $entity->options['sharings'][$definition['legacy']];
						$changed = true;
					}
				}
				if ($changed && $entity->update((int) $entity->id, $user, false) < 0) throw new RuntimeException('LmdbSharingDatabaseError');
			}
			$db->commit(); return 1;
		} catch (Throwable $e) {
			$db->rollback(); dol_syslog(__METHOD__.': configuration migration failed', LOG_ERR); return -1;
		}
	}
}
