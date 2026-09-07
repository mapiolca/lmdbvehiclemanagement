-- Imported daily snapshots of QWS metrics, not copies of native vehicle data.
-- Durations retain the API unit; conversion requires a confirmed connection setting.
CREATE TABLE llx_lmdbvehiclemanagement_qx_usage (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_vehicle integer NOT NULL,
	usage_day date NOT NULL,
	has_data integer DEFAULT 0 NOT NULL,
	trip_count integer DEFAULT NULL,
	distance double(24,8) DEFAULT NULL,
	travel_time double(24,8) DEFAULT NULL,
	idling_time double(24,8) DEFAULT NULL,
	date_sync datetime NOT NULL
) ENGINE=innodb;
