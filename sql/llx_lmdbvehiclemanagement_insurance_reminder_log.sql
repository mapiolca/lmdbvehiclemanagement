CREATE TABLE llx_lmdbvehiclemanagement_insurance_reminder_log (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	reminder_key varchar(191) NOT NULL,
	fk_contract integer NOT NULL,
	fk_vehicle integer DEFAULT NULL,
	fk_certificate integer DEFAULT NULL,
	reminder_type varchar(32) NOT NULL,
	due_date date NOT NULL,
	sent_at datetime DEFAULT NULL,
	status smallint DEFAULT 0 NOT NULL,
	recipient_count integer DEFAULT 0 NOT NULL,
	error_message text,
	date_creation datetime NOT NULL
) ENGINE=innodb;
