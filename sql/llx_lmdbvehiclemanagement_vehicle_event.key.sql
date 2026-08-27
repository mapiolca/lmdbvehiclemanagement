ALTER TABLE llx_lmdbvehiclemanagement_vehicle_event ADD UNIQUE INDEX uk_lmdbvm_event_ref (entity, ref);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle_event ADD INDEX idx_lmdbvm_event_entity (entity);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle_event ADD INDEX idx_lmdbvm_event_vehicle (fk_vehicle);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle_event ADD INDEX idx_lmdbvm_event_date (event_date);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle_event ADD INDEX idx_lmdbvm_event_driver (fk_user_driver);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle_event ADD INDEX idx_lmdbvm_event_soc (fk_soc);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle_event ADD INDEX idx_lmdbvm_event_status (status);

