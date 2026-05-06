ALTER TABLE llx_pressing_article ADD INDEX IF NOT EXISTS idx_pressing_article_fk_bon_entree (fk_bon_entree);
ALTER TABLE llx_pressing_article ADD INDEX IF NOT EXISTS idx_pressing_article_fk_entrepot (fk_entrepot);
ALTER TABLE llx_pressing_article ADD INDEX IF NOT EXISTS idx_pressing_article_fk_product (fk_product);
ALTER TABLE llx_pressing_article ADD INDEX IF NOT EXISTS idx_pressing_article_status (status);
