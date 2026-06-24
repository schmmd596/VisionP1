CREATE TABLE IF NOT EXISTS llx_fiscalmauritanie_audit (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  object_type varchar(50) NOT NULL,
  fk_object integer NOT NULL,
  field_name varchar(128) DEFAULT NULL,
  old_value text,
  new_value text,
  reason text,
  action varchar(50) NOT NULL,
  date_action datetime NOT NULL,
  fk_user integer DEFAULT NULL,
  ip_address varchar(64) DEFAULT NULL,
  KEY idx_fiscalmauritanie_audit_object (object_type, fk_object),
  KEY idx_fiscalmauritanie_audit_user (fk_user),
  KEY idx_fiscalmauritanie_audit_date (date_action)
) ENGINE=innodb;
