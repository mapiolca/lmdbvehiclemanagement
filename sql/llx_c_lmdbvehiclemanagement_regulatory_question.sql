CREATE TABLE llx_c_lmdbvehiclemanagement_regulatory_question (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	description text,
	answer_type varchar(16) DEFAULT 'single' NOT NULL,
	date_label varchar(255) DEFAULT NULL,
	position integer DEFAULT 0 NOT NULL,
	active smallint DEFAULT 1 NOT NULL
) ENGINE=innodb;
