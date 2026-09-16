<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
$conf->entity = 2; $user->admin = 1;
foreach ($definitions as $element => $definition) $conf->global->{'MULTICOMPANY_'.strtoupper($element).'_SHARE_ALL_BY_DEFAULT'} = 0;
$db->query('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_qx_dataset (rowid,entity,fk_vehicle) VALUES (90,2,10),(91,1,10)');
$db->query('INSERT INTO '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_odometer_reading (rowid,entity,fk_vehicle,fk_quartix,is_estimate) VALUES (90,2,10,90,1)');
$dao->setSharingsByElement('lmdbvehicle', 10, array(2));
foreach (array(1,3) as $destination) {
	DaoMulticompany::$fixtures[$destination]['sharings']['lmdbvehiclequartix'] = array(2);
	DaoMulticompany::$fixtures[$destination]['sharings']['lmdbvehicleodometerreading'] = array(2);
}
$conf->entity = 1;
$conf->global->MULTICOMPANY_LMDBVEHICLEQUARTIX_SHARING_ENABLED = 0;
checkSharing(!LmdbVehicleSharing::visible($db, 'lmdbvehiclequartix', 90) && !LmdbVehicleSharing::visible($db, 'lmdbvehicleodometerreading', 90), 'Ordinary mileage never grants private QUARTIX observations');
$conf->global->MULTICOMPANY_LMDBVEHICLEQUARTIX_SHARING_ENABLED = 1;
checkSharing(LmdbVehicleSharing::visible($db, 'lmdbvehiclequartix', 90), 'Vehicle owner reads collector data through the QUARTIX family scope');
$conf->global->MULTICOMPANY_LMDBVEHICLEODOMETERREADING_SHARING_ENABLED = 0;
checkSharing(LmdbVehicleSharing::visible($db, 'lmdbvehicleodometerreading', 90), 'Provider mileage follows its dataset independently of manual mileage');
$conf->entity = 3;
checkSharing(!LmdbVehicleSharing::visible($db, 'lmdbvehiclequartix', 90), 'Global QUARTIX family still requires vehicle access');
$dao->setSharingsByElement('lmdbvehicle', 10, array(2,3));
checkSharing(LmdbVehicleSharing::visible($db, 'lmdbvehiclequartix', 90), 'Vehicle and family both shared to C');
$dao->setSharingsByElement('lmdbvehicle', 10, array());
checkSharing(!LmdbVehicleSharing::visible($db, 'lmdbvehiclequartix', 90), 'Withdrawal takes effect immediately for foreign dataset');
$conf->entity = 2;
checkSharing(LmdbVehicleSharing::visible($db, 'lmdbvehiclequartix', 90) && LmdbVehicleSharing::visible($db, 'lmdbvehicleodometerreading', 90), 'Collector retains local archives after vehicle withdrawal');
$conf->global->MULTICOMPANY_LMDBVEHICLEODOMETERREADING_SHARING_ENABLED = 1;
$conf->entity = 1;
$dao->setSharingsByElement('lmdbvehicle', 10, array(2));
foreach (array('qx_dataset', 'odometer_reading') as $table) $db->query('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_'.$table.' WHERE rowid IN (90,91)');
