CREATE TABLE llx_lmdbvehiclemanagement_insurance_contract_vehicle (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_contract integer NOT NULL,
	fk_vehicle integer NOT NULL,
	coverage_type varchar(32) DEFAULT 'primary' NOT NULL,
	date_start date NOT NULL,
	date_end date DEFAULT NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NOT NULL,
	fk_user_modif integer DEFAULT NULL
) ENGINE=innodb;
