ALTER TABLE llx_lmdbvehiclemanagement_qx_route ADD UNIQUE INDEX uk_lmdbvm_qxr_trip (entity, fk_tripday, trip_key);
