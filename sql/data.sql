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
SELECT 'lmdbvehicleconsumption@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_CREATE', 'Create vehicle consumption', 'Create an agenda event when a fuel, recharge or additive entry is created', 1200
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_CREATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicleconsumption@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_UPDATE', 'Update vehicle consumption', 'Create an agenda event when a fuel, recharge or additive entry is updated', 1210
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_UPDATE');

INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang)
SELECT 'lmdbvehicleconsumption@lmdbvehiclemanagement', 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_DELETE', 'Delete vehicle consumption', 'Create an agenda event when a fuel, recharge or additive entry is deleted', 1220
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'LMDBVEHICLEMANAGEMENT_CONSUMPTION_DELETE');
