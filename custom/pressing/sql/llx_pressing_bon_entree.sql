CREATE TABLE llx_pressing_bon_entree (
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
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
