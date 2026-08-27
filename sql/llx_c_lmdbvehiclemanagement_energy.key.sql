ALTER TABLE llx_c_lmdbvehiclemanagement_energy ADD UNIQUE INDEX uk_c_lmdbvm_energy_entity_code (entity, code);
ALTER TABLE llx_c_lmdbvehiclemanagement_energy ADD INDEX idx_c_lmdbvm_energy_entity_active_position (entity, active, position);
