CREATE TABLE llx_lmdbvehiclemanagement_consumable_energy (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_consumable integer NOT NULL,
	fk_energy integer NOT NULL,
	date_creation datetime NOT NULL,
	fk_user_creat integer DEFAULT NULL
) ENGINE=innodb;
