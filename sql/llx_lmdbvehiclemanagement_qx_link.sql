CREATE TABLE llx_lmdbvehiclemanagement_qx_link (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_vehicle integer NOT NULL,
	remote_id integer NOT NULL,
	active integer DEFAULT 1 NOT NULL,
	timezone varchar(64) NOT NULL,
	shift_start varchar(16) NOT NULL,
	sync_from datetime DEFAULT NULL,
	usage_cursor date DEFAULT NULL,
	usage_refreshed date DEFAULT NULL,
	odometer_synced date DEFAULT NULL,
	date_creation datetime NOT NULL,
	fk_user_creat integer NOT NULL
) ENGINE=innodb;
