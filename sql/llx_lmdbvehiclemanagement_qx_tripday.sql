CREATE TABLE llx_lmdbvehiclemanagement_qx_tripday (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_vehicle integer NOT NULL,
	source_link_id integer NOT NULL,
	remote_id integer NOT NULL,
	trip_day date NOT NULL,
	timezone varchar(64) NOT NULL,
	shift_start varchar(16) NOT NULL,
	synced_at datetime NOT NULL,
	has_open integer DEFAULT 0 NOT NULL,
	trip_count integer DEFAULT 0 NOT NULL
) ENGINE=innodb;
