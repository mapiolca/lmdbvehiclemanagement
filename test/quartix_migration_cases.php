<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
require_once dirname(__DIR__).'/class/lmdbvehiclequartixmigration.class.php';
$tables = array('qx_link', 'qx_position', 'qx_usage', 'qx_tripday', 'odometer_reading');
$beforeMigration = array();
foreach ($tables as $table) {
	$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_'.$table.' SET fk_quartix=NULL');
	$beforeMigration[$table] = $service->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_'.$table.' ORDER BY rowid');
}
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_dataset');
qxCheck(LmdbVehicleQuartixMigration::run($db) === 1, 'Backfill from associations and retained histories succeeds');
$datasetsAfter = $service->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_dataset ORDER BY rowid');
qxCheck(LmdbVehicleQuartixMigration::run($db) === 1 && $datasetsAfter == $service->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_dataset ORDER BY rowid'), 'Replay preserves dataset identifiers and creates no duplicate');
foreach ($tables as $table) {
	$after = $service->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_'.$table.' ORDER BY rowid');
	foreach ($after as $row) $row->fk_quartix = null;
	qxCheck($after == $beforeMigration[$table], 'Migration preserves all historical identifiers, entities, measures and parameters: '.$table);
}
$orphan = $service->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_dataset WHERE entity=2 AND fk_vehicle=90');
qxCheck(count($orphan) === 1 && $orphan[0]->snapshot_vehicle_label === '', 'History without vehicle or association produces a private dataset without inventing identification');
$queriesStart = count($db->queries);
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link SET fk_quartix=999999 WHERE entity=1 AND fk_vehicle=1');
qxCheck(LmdbVehicleQuartixMigration::run($db) === -1, 'Inconsistent pre-existing ownership is reported rather than silently reassigned');
qxCheck(count(array_filter(array_slice($db->queries, $queriesStart), static function ($sql) { return stripos($sql, 'entity_element_sharing') !== false; })) === 0, 'Migration creates no native sharing authorization');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_link SET fk_quartix=NULL WHERE entity=1 AND fk_vehicle=1');
qxCheck(LmdbVehicleQuartixMigration::run($db) === 1, 'Migration can resume after an inconsistent link is repaired');
