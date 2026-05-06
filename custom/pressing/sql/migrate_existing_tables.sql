-- Migration Script for Existing Pressing Module
-- This script updates existing tables to support the new payment and bon_entree structure
-- Run this manually in PhpMyAdmin or MySQL CLI if you have existing data

-- Add payment columns to llx_pressing_bon_entree if they don't exist
ALTER TABLE llx_pressing_bon_entree ADD COLUMN IF NOT EXISTS payment_status INT DEFAULT 0;
ALTER TABLE llx_pressing_bon_entree ADD COLUMN IF NOT EXISTS payment_amount DOUBLE DEFAULT 0;
ALTER TABLE llx_pressing_bon_entree ADD COLUMN IF NOT EXISTS fk_bank_account INT;
ALTER TABLE llx_pressing_bon_entree ADD COLUMN IF NOT EXISTS date_payment DATETIME;

-- Add fk_bon_entree and qty columns to llx_pressing_article if they don't exist
ALTER TABLE llx_pressing_article ADD COLUMN IF NOT EXISTS fk_bon_entree INT;
ALTER TABLE llx_pressing_article ADD COLUMN IF NOT EXISTS qty INT DEFAULT 1;

-- Add indexes if they don't exist
ALTER TABLE llx_pressing_article ADD INDEX IF NOT EXISTS idx_pressing_article_fk_bon_entree (fk_bon_entree);
ALTER TABLE llx_pressing_article ADD INDEX IF NOT EXISTS idx_pressing_article_fk_entrepot (fk_entrepot);

-- Optional: If you want to migrate old fk_facture to fk_bon_entree relationship
-- UPDATE llx_pressing_article SET fk_bon_entree = 0 WHERE fk_facture > 0;

-- Optional: Drop old fk_facture column if no longer needed (backup first!)
-- ALTER TABLE llx_pressing_article DROP COLUMN IF EXISTS fk_facture;
