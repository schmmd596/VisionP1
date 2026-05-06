CREATE TABLE IF NOT EXISTS llx_pressing_bon_entree (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  ref varchar(30) NOT NULL UNIQUE,
  entity integer DEFAULT 1 NOT NULL,
  fk_soc integer NOT NULL,
  date_entree datetime,
  date_validation datetime,
  status integer DEFAULT 0,
  fk_user_author integer,
  fk_user_valid integer,
  note_private text,
  payment_status integer DEFAULT 0,
  payment_amount double DEFAULT 0,
  fk_bank_account integer,
  date_payment datetime,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
