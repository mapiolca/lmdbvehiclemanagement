ALTER TABLE llx_c_lmdbvehiclemanagement_asset_type ADD UNIQUE INDEX uk_lmdbvm_asset_type (entity, code);
ALTER TABLE llx_c_lmdbvehiclemanagement_asset_type ADD INDEX idx_lmdbvm_asset_type_entity (entity);
ALTER TABLE llx_c_lmdbvehiclemanagement_asset_type ADD INDEX idx_lmdbvm_asset_type_active (active);
