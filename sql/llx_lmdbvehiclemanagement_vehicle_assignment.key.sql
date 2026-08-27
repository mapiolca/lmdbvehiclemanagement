ALTER TABLE llx_lmdbvehiclemanagement_vehicle_assignment ADD INDEX idx_lmdbvm_assign_entity (entity);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle_assignment ADD INDEX idx_lmdbvm_assign_vehicle (fk_vehicle);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle_assignment ADD INDEX idx_lmdbvm_assign_driver (fk_user_driver);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle_assignment ADD INDEX idx_lmdbvm_assign_dates (date_start, date_end);
ALTER TABLE llx_lmdbvehiclemanagement_vehicle_assignment ADD INDEX idx_lmdbvm_assign_primary (fk_vehicle, is_primary, status);

