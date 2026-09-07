ALTER TABLE llx_lmdbvehiclemanagement_qx_routequeue ADD UNIQUE INDEX uk_lmdbvm_qxrq_day (entity, fk_tripday);
ALTER TABLE llx_lmdbvehiclemanagement_qx_routequeue ADD INDEX idx_lmdbvm_qxrq_pending (entity, pending, requested_at);
