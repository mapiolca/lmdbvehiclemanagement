INSERT INTO llx_c_type_contact (element, source, code, libelle, active, module, position)
SELECT 'lmdbinsurancecontract', 'internal', 'CONTRACTMANAGER', 'InsuranceContractInternalContactRole', 1, 'lmdbvehiclemanagement', 10
WHERE NOT EXISTS (
	SELECT 1 FROM llx_c_type_contact
	WHERE element = 'lmdbinsurancecontract' AND source = 'internal' AND code = 'CONTRACTMANAGER'
);

INSERT INTO llx_c_type_contact (element, source, code, libelle, active, module, position)
SELECT 'lmdbinsurancecontract', 'external', 'INSURANCECONTACT', 'InsuranceContractExternalContactRole', 1, 'lmdbvehiclemanagement', 20
WHERE NOT EXISTS (
	SELECT 1 FROM llx_c_type_contact
	WHERE element = 'lmdbinsurancecontract' AND source = 'external' AND code = 'INSURANCECONTACT'
);

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicle@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_VEHICLE_CREATE', 'Create vehicle', 'Create an agenda event when a vehicle is created', 1000
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_VEHICLE_CREATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicle@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_VEHICLE_UPDATE', 'Update vehicle', 'Create an agenda event when a vehicle is updated', 1010
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_VEHICLE_UPDATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicle@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_VEHICLE_DELETE', 'Delete vehicle', 'Create an agenda event when a vehicle is deleted', 1020
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_VEHICLE_DELETE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicleassignment@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_ASSIGNMENT_CREATE', 'Create vehicle assignment', 'Create an agenda event when a vehicle assignment is created', 1030
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_ASSIGNMENT_CREATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicleassignment@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_ASSIGNMENT_UPDATE', 'Update vehicle assignment', 'Create an agenda event when a vehicle assignment is updated', 1040
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_ASSIGNMENT_UPDATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicleassignment@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_ASSIGNMENT_DELETE', 'Delete vehicle assignment', 'Create an agenda event when a vehicle assignment is deleted', 1050
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_ASSIGNMENT_DELETE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicleodometerreading@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_ODOMETER_CREATE', 'Create odometer reading', 'Create an agenda event when an odometer reading is created', 1060
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_ODOMETER_CREATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicleodometerreading@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_ODOMETER_UPDATE', 'Update odometer reading', 'Create an agenda event when an odometer reading is updated', 1070
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_ODOMETER_UPDATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicleodometerreading@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_ODOMETER_DELETE', 'Delete odometer reading', 'Create an agenda event when an odometer reading is deleted', 1080
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_ODOMETER_DELETE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicleevent@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_EVENT_CREATE', 'Create vehicle event', 'Create an agenda event when a vehicle business event is created', 1090
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_EVENT_CREATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicleevent@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_EVENT_UPDATE', 'Update vehicle event', 'Create an agenda event when a vehicle business event is updated', 1100
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_EVENT_UPDATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicleevent@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_EVENT_DELETE', 'Delete vehicle event', 'Create an agenda event when a vehicle business event is deleted', 1110
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_EVENT_DELETE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbinsurancecontract@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_INSURANCE_CONTRACT_CREATE', 'Create insurance contract', 'Create an agenda event when an insurance contract is created', 1120
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_INSURANCE_CONTRACT_CREATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbinsurancecontract@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_INSURANCE_CONTRACT_UPDATE', 'Update insurance contract', 'Create an agenda event when an insurance contract is updated', 1130
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_INSURANCE_CONTRACT_UPDATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbinsurancecontract@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_INSURANCE_CONTRACT_DELETE', 'Delete insurance contract', 'Create an agenda event when an insurance contract is deleted', 1140
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_INSURANCE_CONTRACT_DELETE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbinsurancecertificate@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_CERTIFICATE_CREATE', 'Create insurance certificate', 'Create an agenda event when an insurance certificate is created', 1150
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_CERTIFICATE_CREATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbinsurancecertificate@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_CERTIFICATE_UPDATE', 'Update insurance certificate', 'Create an agenda event when an insurance certificate is updated', 1160
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_CERTIFICATE_UPDATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbinsurancecertificate@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_CERTIFICATE_DELETE', 'Delete insurance certificate', 'Create an agenda event when an insurance certificate is deleted', 1170
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_CERTIFICATE_DELETE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicleconsumption@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_CREATE', 'Create vehicle consumption', 'Create an agenda event when a fuel, recharge or additive entry is created', 1200
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_CREATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicleconsumption@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_UPDATE', 'Update vehicle consumption', 'Create an agenda event when a fuel, recharge or additive entry is updated', 1210
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_UPDATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicleconsumption@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_DELETE', 'Delete vehicle consumption', 'Create an agenda event when a fuel, recharge or additive entry is deleted', 1220
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_DELETE');
