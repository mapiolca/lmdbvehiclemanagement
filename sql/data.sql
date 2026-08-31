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
