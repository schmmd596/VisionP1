CREATE TABLE IF NOT EXISTS llx_fiscalmauritanie_payment (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  fk_declaration integer NOT NULL,
  ref varchar(128) DEFAULT NULL,
  amount double(24,8) DEFAULT 0,
  currency_code varchar(3) DEFAULT 'MRU',
  payment_date date DEFAULT NULL,
  payment_mode varchar(50) DEFAULT NULL,
  payment_reference varchar(255) DEFAULT NULL,
  status integer DEFAULT 0,
  note text,
  date_creation datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user_creat integer DEFAULT NULL,
  fk_user_modif integer DEFAULT NULL,
  KEY idx_fiscalmauritanie_payment_decl (fk_declaration),
  KEY idx_fiscalmauritanie_payment_date (payment_date)
) ENGINE=innodb;
