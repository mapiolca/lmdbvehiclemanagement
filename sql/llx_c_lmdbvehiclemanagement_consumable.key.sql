ALTER TABLE llx_c_lmdbvehiclemanagement_consumable ADD UNIQUE INDEX uk_lmdbvm_consumable_code (entity, code);
ALTER TABLE llx_c_lmdbvehiclemanagement_consumable ADD INDEX idx_lmdbvm_consumable_entity (entity);
ALTER TABLE llx_c_lmdbvehiclemanagement_consumable ADD INDEX idx_lmdbvm_consumable_category (category);
ALTER TABLE llx_c_lmdbvehiclemanagement_consumable ADD INDEX idx_lmdbvm_consumable_active (active);
