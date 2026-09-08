CREATE TABLE llx_lmdbvehiclemanagement_qx_dataset (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_vehicle integer NOT NULL,
	snapshot_vehicle_label varchar(255) DEFAULT '',
	date_creation datetime,
	fk_user_creat integer
) ENGINE=innodb;
