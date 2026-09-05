ALTER TABLE llx_lmdbvehiclemanagement_qx_tripday ADD UNIQUE INDEX uk_lmdbvm_qxt_day (entity, fk_vehicle, trip_day);
ALTER TABLE llx_lmdbvehiclemanagement_qx_tripday ADD INDEX idx_lmdbvm_qxt_retention (entity, trip_day);
