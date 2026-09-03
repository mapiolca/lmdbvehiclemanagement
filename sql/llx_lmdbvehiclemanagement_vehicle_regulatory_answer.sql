CREATE TABLE llx_lmdbvehiclemanagement_vehicle_regulatory_answer (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_vehicle integer NOT NULL,
	fk_question integer NOT NULL,
	fk_choice integer NOT NULL,
	origin varchar(16) DEFAULT 'questionnaire' NOT NULL,
	applicable_since date DEFAULT NULL,
	note text,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NOT NULL,
	fk_user_modif integer DEFAULT NULL
) ENGINE=innodb;
