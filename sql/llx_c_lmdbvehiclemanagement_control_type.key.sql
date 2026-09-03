ALTER TABLE llx_c_lmdbvehiclemanagement_control_type ADD UNIQUE INDEX uk_lmdbvm_control_type (entity, code);
ALTER TABLE llx_c_lmdbvehiclemanagement_control_type ADD INDEX idx_lmdbvm_control_type_entity (entity);
ALTER TABLE llx_c_lmdbvehiclemanagement_control_type ADD INDEX idx_lmdbvm_control_type_active (active);
