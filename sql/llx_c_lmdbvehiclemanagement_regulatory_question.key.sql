ALTER TABLE llx_c_lmdbvehiclemanagement_regulatory_question ADD UNIQUE INDEX uk_lmdbvm_reg_question (entity, code);
ALTER TABLE llx_c_lmdbvehiclemanagement_regulatory_question ADD INDEX idx_lmdbvm_reg_question_entity (entity);
ALTER TABLE llx_c_lmdbvehiclemanagement_regulatory_question ADD INDEX idx_lmdbvm_reg_question_active (active, position);
