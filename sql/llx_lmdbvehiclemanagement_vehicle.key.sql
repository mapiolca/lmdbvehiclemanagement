ALTER TABLE llx_lmdbvehiclemanagement_vehicle ADD UNIQUE INDEX uk_lmdbvm_vehicle_ref (entity, ref);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle ADD UNIQUE INDEX uk_lmdbvm_vehicle_reg (entity, registration_number);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle ADD UNIQUE INDEX uk_lmdbvm_vehicle_vin (entity, vin);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle ADD INDEX idx_lmdbvm_vehicle_entity (entity);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle ADD INDEX idx_lmdbvm_vehicle_status (status);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle ADD INDEX idx_lmdbvm_vehicle_owner (fk_soc_owner);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle ADD INDEX idx_lmdbvm_vehicle_resource (fk_resource);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle ADD INDEX idx_lmdbvm_vehicle_energy (fk_energy);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle ADD INDEX idx_lmdbvm_vehicle_asset_type (fk_asset_type);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle ADD INDEX idx_lmdbvm_vehicle_created (date_creation);

