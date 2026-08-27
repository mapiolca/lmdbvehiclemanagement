CREATE TABLE llx_lmdbvehiclemanagement_odometer_reading (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_vehicle integer NOT NULL,
	reading_date datetime NOT NULL,
	odometer_km double(24,8) NOT NULL,
	source varchar(32) DEFAULT 'manual' NOT NULL,
	reading_kind varchar(32) DEFAULT 'standard' NOT NULL,
	reason text,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NOT NULL,
	fk_user_modif integer DEFAULT NULL,
	import_key varchar(14) DEFAULT NULL
) ENGINE=innodb;

