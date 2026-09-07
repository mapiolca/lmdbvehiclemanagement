CREATE TABLE llx_lmdbvehiclemanagement_qx_job (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	job_kind varchar(16) NOT NULL,
	last_attempt datetime DEFAULT NULL,
	last_success datetime DEFAULT NULL,
	last_error varchar(128) DEFAULT NULL,
	retry_at datetime DEFAULT NULL,
	last_vehicle integer DEFAULT 0 NOT NULL
) ENGINE=innodb;
