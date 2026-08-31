CREATE TABLE llx_c_lmdbvehiclemanagement_consumable (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	code varchar(32) NOT NULL,
	label varchar(255) NOT NULL,
	category varchar(16) NOT NULL,
	unit varchar(16) NOT NULL,
	requires_oil_reference smallint DEFAULT 0 NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active smallint DEFAULT 1 NOT NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
