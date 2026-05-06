CREATE TABLE IF NOT EXISTS llx_pressing_article (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  fk_bon_entree integer,
  fk_product integer NOT NULL,
  ref_article varchar(255) NOT NULL,
  fk_entrepot integer,
  qty integer DEFAULT 1,
  longueur double,
  largeur double,
  surface double,
  price double,
  status integer DEFAULT 0,
  date_reception datetime,
  date_livraison datetime,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  note_private text
) ENGINE=innodb;
