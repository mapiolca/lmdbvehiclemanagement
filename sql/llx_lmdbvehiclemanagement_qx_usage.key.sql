ALTER TABLE llx_lmdbvehiclemanagement_qx_usage ADD UNIQUE INDEX uk_lmdbvm_qxu_day (entity, fk_vehicle, usage_day);

ALTER TABLE llx_lmdbvehiclemanagement_qx_usage ADD INDEX idx_qx_usage_owner (fk_quartix);
