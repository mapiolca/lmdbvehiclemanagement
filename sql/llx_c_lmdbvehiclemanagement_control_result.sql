CREATE TABLE llx_c_lmdbvehiclemanagement_control_result (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(128) NOT NULL,
	severity smallint DEFAULT 0 NOT NULL,
	requires_recheck smallint DEFAULT 0 NOT NULL,
	is_blocking smallint DEFAULT 0 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active smallint DEFAULT 1 NOT NULL
) ENGINE=innodb;
