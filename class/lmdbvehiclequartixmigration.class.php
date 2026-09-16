<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Additive backfill. No sharing grants, measures, identifiers or settings are rewritten. */
class LmdbVehicleQuartixMigration
{
	/** @param DoliDB $db Database @return int */
	public static function run($db)
	{
		$prefix = MAIN_DB_PREFIX.'lmdbvehiclemanagement_';
		$sources = array('qx_link', 'qx_position', 'qx_usage', 'qx_tripday', 'odometer_reading');
		$db->begin();
		foreach ($sources as $table) {
			$condition = $table === 'odometer_reading' ? " AND s.is_estimate=1 AND s.source='external' AND s.provider_key IS NOT NULL" : '';
			// Only legacy data already owned by the vehicle entity may read its current label.
			$sql = 'INSERT INTO '.$prefix.'qx_dataset (entity,fk_vehicle,snapshot_vehicle_label,date_creation) SELECT DISTINCT s.entity,s.fk_vehicle,COALESCE(v.ref,\'\'),NULL FROM '.$prefix.$table.' s LEFT JOIN '.$prefix.'vehicle v ON v.rowid=s.fk_vehicle AND v.entity=s.entity WHERE s.entity>0 AND s.fk_vehicle>0'.$condition.' AND NOT EXISTS (SELECT 1 FROM '.$prefix.'qx_dataset q WHERE q.entity=s.entity AND q.fk_vehicle=s.fk_vehicle)';
			if (!$db->query($sql)) { $db->rollback(); return -1; }
			$sql = 'UPDATE '.$prefix.$table.' s INNER JOIN '.$prefix.'qx_dataset q ON q.entity=s.entity AND q.fk_vehicle=s.fk_vehicle SET s.fk_quartix=q.rowid WHERE s.fk_quartix IS NULL'.$condition;
			if (!$db->query($sql)) { $db->rollback(); return -1; }
			$res = $db->query('SELECT s.rowid FROM '.$prefix.$table.' s LEFT JOIN '.$prefix.'qx_dataset q ON q.rowid=s.fk_quartix AND q.entity=s.entity AND q.fk_vehicle=s.fk_vehicle WHERE q.rowid IS NULL'.$condition.' LIMIT 1');
			if (!$res) { $db->rollback(); return -1; }
			$invalid = is_object($db->fetch_object($res)); $db->free($res);
			if ($invalid) { $db->rollback(); return -1; }
		}
		$db->commit(); return 1;
	}
}
