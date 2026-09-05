CREATE TABLE llx_lmdbvehiclemanagement_qx_position (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_vehicle integer NOT NULL,
	event_date datetime NOT NULL,
	fetched_at datetime NOT NULL,
	latitude double(16,8) DEFAULT NULL,
	longitude double(16,8) DEFAULT NULL,
	speed double(16,8) DEFAULT NULL,
	heading integer DEFAULT NULL,
	location varchar(255) NOT NULL,
	non_tracking integer DEFAULT 0 NOT NULL
) ENGINE=innodb;
