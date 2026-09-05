CREATE TABLE llx_lmdbvehiclemanagement_qx_trip (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_tripday integer NOT NULL,
	departure datetime DEFAULT NULL,
	arrival datetime DEFAULT NULL,
	start_location varchar(255) DEFAULT NULL,
	end_location varchar(255) DEFAULT NULL,
	distance double(24,8) NOT NULL,
	private_distance double(24,8) DEFAULT NULL,
	travel_time double(24,8) DEFAULT NULL,
	idling_time double(24,8) DEFAULT NULL,
	is_private integer DEFAULT 0 NOT NULL,
	in_progress integer DEFAULT NULL
) ENGINE=innodb;
