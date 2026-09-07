CREATE TABLE llx_lmdbvehiclemanagement_qx_routequeue (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_tripday integer NOT NULL,
	pending integer DEFAULT 1 NOT NULL,
	requested_at datetime NOT NULL,
	last_attempt datetime,
	synced_at datetime,
	last_error varchar(64)
) ENGINE=innodb;
