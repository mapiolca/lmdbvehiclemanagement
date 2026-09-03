CREATE TABLE llx_lmdbvehiclemanagement_control_reminder_log (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	reminder_key varchar(40) NOT NULL,
	fk_requirement integer NOT NULL,
	horizon_days integer NOT NULL,
	recipient_type varchar(16) NOT NULL,
	recipient_id integer DEFAULT NULL,
	recipient_email varchar(255) NOT NULL,
	due_date_snapshot date NOT NULL,
	sent_at datetime DEFAULT NULL,
	message_id varchar(255) DEFAULT NULL,
	status smallint DEFAULT 0 NOT NULL,
	error_message text,
	date_creation datetime NOT NULL
) ENGINE=innodb;
