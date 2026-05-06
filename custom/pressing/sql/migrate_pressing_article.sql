-- Migration: Ajouter fk_bon_entree et rendre fk_facture nullable

ALTER TABLE llx_pressing_article ADD COLUMN fk_bon_entree integer NULL AFTER fk_facture;
ALTER TABLE llx_pressing_article MODIFY COLUMN fk_facture integer NULL;
ALTER TABLE llx_pressing_article ADD KEY idx_fk_bon_entree (fk_bon_entree);

-- Optionnel: Supprimer les anciennes colonnes qui ne servent plus (après vérification)
-- ALTER TABLE llx_pressing_article DROP COLUMN fk_facture;

-- Ajouter les nouvelles clés étrangères
ALTER TABLE llx_pressing_article ADD CONSTRAINT fk_bon_entree_article
  FOREIGN KEY (fk_bon_entree) REFERENCES llx_pressing_bon_entree(rowid);
