CREATE TABLE llx_lmdbvehiclemanagement_vehicle_assignment (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_vehicle integer NOT NULL,
	fk_user_driver integer NOT NULL,
	date_start datetime NOT NULL,
	date_end datetime DEFAULT NULL,
	assignment_type varchar(32) DEFAULT 'driver' NOT NULL,
	is_primary smallint DEFAULT 0 NOT NULL,
	reason text,
	status smallint DEFAULT 1 NOT NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NOT NULL,
	fk_user_modif integer DEFAULT NULL,
	import_key varchar(14) DEFAULT NULL
) ENGINE=innodb;

