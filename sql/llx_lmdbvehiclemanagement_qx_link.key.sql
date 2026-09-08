ALTER TABLE llx_lmdbvehiclemanagement_qx_link ADD UNIQUE INDEX uk_lmdbvm_qxl_vehicle (entity, fk_vehicle);
ALTER TABLE llx_lmdbvehiclemanagement_qx_link ADD UNIQUE INDEX uk_lmdbvm_qxl_remote (entity, remote_id);

ALTER TABLE llx_lmdbvehiclemanagement_qx_link ADD INDEX idx_qx_link_owner (fk_quartix);
