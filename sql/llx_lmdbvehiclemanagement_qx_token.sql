CREATE TABLE llx_lmdbvehiclemanagement_qx_token (
	entity integer PRIMARY KEY,
	access_token text NOT NULL,
	refresh_token text NOT NULL
) ENGINE=innodb;
