<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
$conf->entity = 2; $user->admin = 1;
foreach ($definitions as $element => $definition) $conf->global->{'MULTICOMPANY_'.strtoupper($element).'_SHARE_ALL_BY_DEFAULT'} = 0;
$db->query('INSERT INTO '.MAIN_DB_PREFIX."lmdbvehiclemanagement_qx_dataset (rowid,entity,fk_vehicle) VALUES (90,2,10),(91,1,10)");
$db->query('INSERT INTO '.MAIN_DB_PREFIX."lmdbvehiclemanagement_odometer_reading (rowid,entity,fk_vehicle,fk_quartix,is_estimate) VALUES (90,2,10,90,1)");
$dao->setSharingsByElement('lmdbvehicle', 10, array(2));
$dao->setSharingsByElement('lmdbvehicleodometerreading', 90, array(1,3));
foreach (array(1,3) as $destination) {
	DaoMulticompany::$fixtures[$destination]['sharings']['lmdbvehiclequartix'] = array(2);
	DaoMulticompany::$fixtures[$destination]['sharings']['lmdbvehicleodometerreading'] = array(2);
}
$source = new SharingRecord($db); $source->element = 'lmdbvehiclequartix'; $source->table_element = $definitions[$source->element]['table']; $source->TRIGGER_PREFIX = 'LMDBVEHICLEMANAGEMENT_QUARTIX';
checkSharing($source->fetch(90) > 0, 'B owns the dataset attached to the vehicle of A');
$conf->entity = 1;
checkSharing(!LmdbVehicleSharing::visible($db, 'lmdbvehiclequartix', 90) && !LmdbVehicleSharing::visible($db, 'lmdbvehicleodometerreading', 90), 'Vehicle ownership and ordinary mileage grants cannot expose B QUARTIX');
$conf->entity = 2;
checkSharing($source->setSharingEntities($user, array(3)) < 0, 'Grant rejected when C lacks vehicle access');
checkSharing($source->setSharingEntities($user, array(1)) > 0, 'B explicitly shares its dataset back to A');
$conf->entity = 1;
checkSharing(LmdbVehicleSharing::visible($db, 'lmdbvehiclequartix', 90) && LmdbVehicleSharing::visible($db, 'lmdbvehiclequartix', 91), 'Two independently owned sources are authorized on one vehicle');
$conf->global->MULTICOMPANY_LMDBVEHICLEODOMETERREADING_SHARING_ENABLED = 0;
checkSharing(LmdbVehicleSharing::visible($db, 'lmdbvehicleodometerreading', 90), 'QUARTIX mileage follows its dataset even without ordinary mileage sharing');
checkSharing($source->setSharingEntities($user, array(3)) < 0, 'Beneficiary A cannot re-share B dataset despite owning the vehicle');
$conf->entity = 3;
$dao->setSharingsByElement('lmdbvehiclequartix', 90, array(1,3));
checkSharing(!LmdbVehicleSharing::visible($db, 'lmdbvehiclequartix', 90) && !LmdbVehicleSharing::visible($db, 'lmdbvehicleodometerreading', 90), 'Even a stored QUARTIX grant cannot bypass missing vehicle access in C');
$dao->setSharingsByElement('lmdbvehicle', 10, array(2,3));
checkSharing(LmdbVehicleSharing::visible($db, 'lmdbvehiclequartix', 90), 'C reads only when both independent grants are present');
$dao->setSharingsByElement('lmdbvehicle', 10, array());
checkSharing(!LmdbVehicleSharing::visible($db, 'lmdbvehiclequartix', 90), 'Vehicle withdrawal revokes beneficiary access on the next query');
$conf->entity = 2;
checkSharing(LmdbVehicleSharing::visible($db, 'lmdbvehiclequartix', 90) && LmdbVehicleSharing::visible($db, 'lmdbvehicleodometerreading', 90), 'B retains its own dataset and mileage after vehicle withdrawal');
checkSharing($source->setSharingEntities($user, array()) > 0, 'Owner can revoke all grants after losing vehicle access');
$conf->entity = 1;
checkSharing(!LmdbVehicleSharing::visible($db, 'lmdbvehiclequartix', 90), 'QUARTIX grant revocation applies immediately in A');
$conf->global->MULTICOMPANY_LMDBVEHICLEODOMETERREADING_SHARING_ENABLED = 1;
$dao->setSharingsByElement('lmdbvehicle', 10, array(2));
foreach (array('qx_dataset', 'odometer_reading') as $table) $db->query('DELETE FROM '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_'.$table.' WHERE rowid IN (90,91)');
foreach (array('lmdbvehiclequartix', 'lmdbvehicleodometerreading') as $element) $dao->setSharingsByElement($element, 90, array());
