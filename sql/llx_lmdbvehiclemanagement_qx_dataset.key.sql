ALTER TABLE llx_lmdbvehiclemanagement_qx_dataset ADD UNIQUE INDEX uk_qx_dataset_vehicle (entity, fk_vehicle);
ALTER TABLE llx_lmdbvehiclemanagement_qx_dataset ADD INDEX idx_qx_dataset_vehicle (fk_vehicle);
