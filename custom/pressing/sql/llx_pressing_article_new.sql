CREATE TABLE llx_pressing_article (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  fk_bon_entree integer,
  fk_facture integer,
  fk_product integer NOT NULL,
  ref_article varchar(255) NOT NULL,
  fk_entrepot integer,
  longueur double,
  largeur double,
  surface double,
  price double,
  status integer DEFAULT 0,
  date_reception datetime,
  date_livraison datetime,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  note_private text,

  FOREIGN KEY (fk_bon_entree) REFERENCES llx_pressing_bon_entree(rowid),
  KEY idx_fk_bon_entree (fk_bon_entree),
  KEY idx_fk_facture (fk_facture),
  KEY idx_fk_product (fk_product),
  KEY idx_fk_entrepot (fk_entrepot),
  KEY idx_status (status)
) ENGINE=innodb;
