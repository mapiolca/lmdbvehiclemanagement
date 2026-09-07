CREATE TABLE llx_lmdbvehiclemanagement_qx_route (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_tripday integer NOT NULL,
	trip_key varchar(64) NOT NULL,
	fingerprint varchar(64) NOT NULL,
	geometry mediumtext NOT NULL,
	fetched_at datetime NOT NULL
) ENGINE=innodb;
