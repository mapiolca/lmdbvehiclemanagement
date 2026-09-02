CREATE TABLE llx_lmdbvehiclemanagement_vehicle_regulatory_profile (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_vehicle integer NOT NULL,
	fk_profile integer NOT NULL,
	origin varchar(16) DEFAULT 'manual' NOT NULL,
	confirmed smallint DEFAULT 0 NOT NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NOT NULL,
	fk_user_modif integer DEFAULT NULL
) ENGINE=innodb;
