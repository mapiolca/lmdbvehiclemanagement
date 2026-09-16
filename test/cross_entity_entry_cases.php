<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
$conf->entity=2; $conf->currency='EUR';
require_once dirname(__DIR__).'/class/lmdbvehicleevent.class.php';
require_once dirname(__DIR__).'/class/lmdbvehicleassignment.class.php';
require_once dirname(__DIR__).'/class/lmdbvehicleregulatorycontrol.class.php';
foreach (array('LmdbVehicleEvent','LmdbVehicleAssignment','LmdbVehicleOdometerReading') as $class) {
	$entry=new $class($db); $entry->fk_vehicle=$vehicleId; $entry->entity=3;
	$load=new ReflectionMethod($class,'loadVehicleEntity'); $load->setAccessible(true);
	checkSharing($load->invoke($entry)>0 && $entry->entity===2, $class.' new entry belongs to input entity');
	$entry->id=77; $entry->oldcopy=(object)array('entity'=>1);
	checkSharing($load->invoke($entry)>0 && $entry->entity===1, $class.' existing owner retained');
	$entry->fk_vehicle=$old->id;
	checkSharing($load->invoke($entry)<0, $class.' inaccessible parent rejected');
}
$control=new LmdbVehicleRegulatoryControl($db); $control->entity=3; $control->fk_vehicle=$vehicleId; $control->fk_requirement=(int)$req->rowid; $control->control_date=dol_now();
$validate=new ReflectionMethod($control,'validateBusinessRules'); $validate->setAccessible(true);
checkSharing($validate->invoke($control)>0 && $control->entity===2 && $control->fk_rule===1, 'New control uses input entity and vehicle-owned rule');
$control->fk_vehicle = $missing->id;
checkSharing($validate->invoke($control)<0 && $control->error==='InvalidRegulatoryRequirement', 'Requirement from another vehicle is rejected on the server');
$control->fk_vehicle = $vehicleId;
$control->fk_requirement = 0;
checkSharing($validate->invoke($control)<0 && $control->error==='RegulatoryRequirementRequired', 'Empty dependent selection is rejected on the server');

// The same code uses different dictionary rowids in each entity.
$db->query('INSERT INTO '.MAIN_DB_PREFIX."c_lmdbvehiclemanagement_energy (rowid,entity,code,label,active,date_creation) VALUES (101,1,'GO','Diesel',1,'2026-09-16'),(201,2,'GO','Diesel',1,'2026-09-16')");
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbvehiclemanagement_vehicle SET fk_energy=101 WHERE rowid='.$vehicleId);
$db->query('INSERT INTO '.MAIN_DB_PREFIX."c_lmdbvehiclemanagement_consumable (rowid,entity,code,label,category,unit,active,date_creation) VALUES (101,1,'DIESEL_L','Diesel','fuel','L',1,'2026-09-16'),(201,2,'DIESEL_L','Diesel','fuel','L',1,'2026-09-16')");
$db->query('INSERT INTO '.MAIN_DB_PREFIX."lmdbvehiclemanagement_consumable_energy (entity,fk_consumable,fk_energy,date_creation) VALUES (2,201,201,'2026-09-16')");
$db->query('INSERT INTO '.MAIN_DB_PREFIX."lmdbvehiclemanagement_vehicle_capacity (entity,fk_vehicle,fk_consumable,capacity,date_creation,fk_user_creat) VALUES (1,".$vehicleId.",101,60,'2026-09-16',1)");
$fuel=new LmdbVehicleConsumption($db); $fuel->entity=3; $fuel->fk_vehicle=$vehicleId; $fuel->fk_consumable=201; $fuel->reading_date=dol_now(); $fuel->odometer_km=100; $fuel->quantity=10; $fuel->total_ttc=20;
$validate=new ReflectionMethod($fuel,'validateBusinessRules'); $validate->setAccessible(true);
checkSharing($validate->invoke($fuel)>0 && $fuel->entity===2, 'Shared-vehicle fuel validates energy by stable code and keeps input owner');
checkSharing($fuel->getConfiguredCapacity()===60.0, 'Capacity resolves owner consumable with a different dictionary id');
$build=new ReflectionMethod($fuel,'buildReading'); $build->setAccessible(true);
checkSharing($build->invoke($fuel)->entity===2, 'Linked odometer reading has the consumption owner');
$dictionary=new LmdbVehicleConsumable($db);
checkSharing(isset($dictionary->getOptions('fuel',$vehicleId)[201]), 'Fuel selector accepts matching energy code across entities');
$capacityChoices = $dictionary->getCapacityOptions(101);
checkSharing(isset($capacityChoices[201]) && in_array(101, $capacityChoices[201]['energy_ids'], true), 'Capacity selector maps energy aliases by code across entities');
$dao->setSharingsByElement('lmdbvehicle',$vehicleId,array());
checkSharing($validate->invoke($fuel)<0 && $fuel->getConfiguredCapacity()===null, 'Withdrawal blocks new fuel and hides current capacity');
