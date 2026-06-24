CREATE TABLE IF NOT EXISTS llx_fiscalmauritanie_barreme (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  fk_rule integer NOT NULL,
  tax_type varchar(50) NOT NULL,
  label varchar(255) DEFAULT NULL,
  tranche_min double(24,8) DEFAULT 0,
  tranche_max double(24,8) DEFAULT NULL,
  rate double(24,8) DEFAULT 0,
  fixed_amount double(24,8) DEFAULT 0,
  active tinyint DEFAULT 1,
  date_creation datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user_creat integer DEFAULT NULL,
  fk_user_modif integer DEFAULT NULL,
  KEY idx_fiscalmauritanie_barreme_rule (fk_rule),
  KEY idx_fiscalmauritanie_barreme_tax_type (tax_type)
) ENGINE=innodb;
