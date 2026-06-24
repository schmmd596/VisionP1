-- Réparation Fiscal Mauritanie : création manuelle des tables si activation partielle.
-- À exécuter dans la base Dolibarr uniquement si les tables llx_fiscalmauritanie_* sont absentes.
-- Adapter le préfixe llx_ si votre installation Dolibarr utilise un autre préfixe.

CREATE TABLE IF NOT EXISTS llx_fiscalmauritanie_rule (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  code varchar(50) NOT NULL,
  label varchar(255) NOT NULL,
  tax_type varchar(50) NOT NULL,
  rate double(24,8) DEFAULT 0,
  minimum_amount double(24,8) DEFAULT 0,
  maximum_amount double(24,8) DEFAULT NULL,
  ceiling_amount double(24,8) DEFAULT NULL,
  frequency varchar(20) DEFAULT 'monthly',
  calculation_method varchar(50) DEFAULT 'rate',
  declared_percentage double(24,8) DEFAULT 100,
  due_day integer DEFAULT NULL,
  due_month integer DEFAULT NULL,
  active tinyint DEFAULT 1,
  note text,
  date_start date DEFAULT NULL,
  date_end date DEFAULT NULL,
  date_creation datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user_creat integer DEFAULT NULL,
  fk_user_modif integer DEFAULT NULL,
  import_key varchar(14) DEFAULT NULL,
  UNIQUE KEY uk_fiscalmauritanie_rule_code_entity (code, entity),
  KEY idx_fiscalmauritanie_rule_tax_type (tax_type),
  KEY idx_fiscalmauritanie_rule_active (active)
) ENGINE=innodb;
;

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
;

CREATE TABLE IF NOT EXISTS llx_fiscalmauritanie_declaration (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  ref varchar(128) NOT NULL,
  entity integer DEFAULT 1 NOT NULL,
  fk_soc integer DEFAULT NULL,
  company_nif varchar(64) DEFAULT NULL,
  tax_type varchar(50) NOT NULL,
  period varchar(20) NOT NULL,
  period_start date DEFAULT NULL,
  period_end date DEFAULT NULL,
  mode_declaration varchar(50) DEFAULT 'real',
  amount_system double(24,8) DEFAULT 0,
  declared_percentage double(24,8) DEFAULT 100,
  declared_amount double(24,8) DEFAULT 0,
  adjustment_amount double(24,8) DEFAULT 0,
  penalty_amount double(24,8) DEFAULT 0,
  total_amount double(24,8) DEFAULT 0,
  currency_code varchar(3) DEFAULT 'MRU',
  status integer DEFAULT 0,
  payment_status integer DEFAULT 0,
  due_date date DEFAULT NULL,
  payment_date date DEFAULT NULL,
  fk_rule integer DEFAULT NULL,
  note_private text,
  note_public text,
  model_pdf varchar(255) DEFAULT NULL,
  last_main_doc varchar(255) DEFAULT NULL,
  date_validation datetime DEFAULT NULL,
  fk_user_valid integer DEFAULT NULL,
  date_creation datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user_creat integer DEFAULT NULL,
  fk_user_modif integer DEFAULT NULL,
  import_key varchar(14) DEFAULT NULL,
  UNIQUE KEY uk_fiscalmauritanie_declaration_ref_entity (ref, entity),
  KEY idx_fiscalmauritanie_declaration_tax_type (tax_type),
  KEY idx_fiscalmauritanie_declaration_period (period),
  KEY idx_fiscalmauritanie_declaration_due_date (due_date),
  KEY idx_fiscalmauritanie_declaration_status (status),
  KEY idx_fiscalmauritanie_declaration_payment_status (payment_status),
  KEY idx_fiscalmauritanie_declaration_fk_soc (fk_soc)
) ENGINE=innodb;
;

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
;

CREATE TABLE IF NOT EXISTS llx_fiscalmauritanie_notification (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  fk_declaration integer DEFAULT NULL,
  fk_user integer DEFAULT NULL,
  tax_type varchar(50) DEFAULT NULL,
  channel varchar(50) DEFAULT 'internal',
  subject varchar(255) DEFAULT NULL,
  message text,
  notification_date datetime DEFAULT NULL,
  status integer DEFAULT 0,
  error_message text,
  date_creation datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user_creat integer DEFAULT NULL,
  fk_user_modif integer DEFAULT NULL,
  KEY idx_fiscalmauritanie_notification_decl (fk_declaration),
  KEY idx_fiscalmauritanie_notification_user (fk_user),
  KEY idx_fiscalmauritanie_notification_date (notification_date),
  KEY idx_fiscalmauritanie_notification_status (status)
) ENGINE=innodb;
;

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
;

INSERT IGNORE INTO llx_fiscalmauritanie_rule (entity, code, label, tax_type, rate, minimum_amount, maximum_amount, ceiling_amount, frequency, calculation_method, declared_percentage, due_day, due_month, active, note, date_creation) VALUES
(1, 'ITS_PROGRESSIF', 'Impôt sur les traitements et salaires - barème progressif', 'ITS', 0, 0, NULL, NULL, 'monthly', 'progressive', 100, 15, NULL, 1, 'Barème à ajuster selon la réglementation en vigueur', NOW()),
(1, 'CNSS_EMPLOYEUR', 'Cotisation CNSS employeur', 'CNSS', 15, 0, NULL, NULL, 'monthly', 'rate', 100, 15, NULL, 1, 'Taux indicatif à vérifier', NOW()),
(1, 'CNAM_EMPLOYEUR', 'Cotisation CNAM employeur', 'CNAM', 5, 0, NULL, NULL, 'monthly', 'rate', 100, 15, NULL, 1, 'Taux indicatif à vérifier', NOW()),
(1, 'IS_STANDARD', 'Impôt sur les sociétés', 'IS', 25, 0, NULL, NULL, 'annual', 'profit_rate', 100, 31, 3, 1, 'Taux indicatif à vérifier', NOW()),
(1, 'IMF_STANDARD', 'Impôt minimum forfaitaire', 'IMF', 2.5, 0, NULL, NULL, 'annual', 'minimum_compare', 100, 31, 3, 1, 'Taux indicatif à vérifier', NOW()),
(1, 'TA_STANDARD', 'Taxe d’apprentissage', 'TA', 0.6, 0, NULL, NULL, 'monthly', 'rate', 100, 15, NULL, 1, 'Taux indicatif à vérifier', NOW());
