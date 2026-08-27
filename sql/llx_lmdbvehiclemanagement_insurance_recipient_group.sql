CREATE TABLE llx_lmdbvehiclemanagement_insurance_recipient_group (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_usergroup integer NOT NULL,
	date_creation datetime NOT NULL,
	fk_user_creat integer NOT NULL
) ENGINE=innodb;
