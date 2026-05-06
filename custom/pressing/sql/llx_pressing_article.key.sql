ALTER TABLE llx_pressing_article ADD INDEX idx_pressing_article_fk_facture (fk_facture);
ALTER TABLE llx_pressing_article ADD INDEX idx_pressing_article_fk_product (fk_product);
ALTER TABLE llx_pressing_article ADD INDEX idx_pressing_article_status (status);
