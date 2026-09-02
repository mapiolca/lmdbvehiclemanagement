CREATE TABLE llx_lmdbvehiclemanagement_regulatory_rule_profile (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_rule integer NOT NULL,
	fk_profile integer NOT NULL
) ENGINE=innodb;
