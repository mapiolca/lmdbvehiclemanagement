CREATE TABLE llx_c_lmdbvehiclemanagement_regulatory_question_choice (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_question integer NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	profile_code varchar(64) DEFAULT NULL,
	requires_date smallint DEFAULT 0 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active smallint DEFAULT 1 NOT NULL
) ENGINE=innodb;
