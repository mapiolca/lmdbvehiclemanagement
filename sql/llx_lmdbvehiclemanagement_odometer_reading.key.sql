ALTER TABLE llx_lmdbvehiclemanagement_odometer_reading ADD INDEX idx_lmdbvm_odo_entity (entity);
ALTER TABLE llx_lmdbvehiclemanagement_odometer_reading ADD INDEX idx_lmdbvm_odo_vehicle (fk_vehicle);
ALTER TABLE llx_lmdbvehiclemanagement_odometer_reading ADD INDEX idx_lmdbvm_odo_date (reading_date);
ALTER TABLE llx_lmdbvehiclemanagement_odometer_reading ADD INDEX idx_lmdbvm_odo_vehicle_date (fk_vehicle, reading_date);
ALTER TABLE llx_lmdbvehiclemanagement_odometer_reading ADD UNIQUE INDEX uk_lmdbvm_odo_provider (entity, fk_vehicle, provider_key);
