CREATE TABLE llx_c_lmdbvehiclemanagement_asset_type (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(32) NOT NULL,
	label varchar(128) NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active smallint DEFAULT 1 NOT NULL
) ENGINE=innodb;
