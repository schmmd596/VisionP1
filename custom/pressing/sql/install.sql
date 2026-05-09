-- ============================================================================
-- Installation Script for Pressing Module
-- ============================================================================
-- This file contains all SQL statements needed to install the pressing module
-- It creates tables, adds indexes, and handles migrations for existing tables
-- Safe to run on fresh installations and existing databases
-- ============================================================================

-- ============================================================================
-- 1. CREATE MAIN TABLES
-- ============================================================================

-- Table: llx_pressing_bon_entree (Reception Orders)
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

-- Table: llx_pressing_article (Articles)
CREATE TABLE IF NOT EXISTS llx_pressing_article (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  fk_bon_entree integer,
  fk_facture integer,
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

-- ============================================================================
-- 2. ADD INDEXES TO TABLES
-- ============================================================================

-- Indexes for llx_pressing_bon_entree
ALTER TABLE llx_pressing_bon_entree ADD KEY IF NOT EXISTS idx_fk_soc (fk_soc);
ALTER TABLE llx_pressing_bon_entree ADD KEY IF NOT EXISTS idx_status (status);
ALTER TABLE llx_pressing_bon_entree ADD KEY IF NOT EXISTS idx_date_entree (date_entree);

-- Indexes for llx_pressing_article
ALTER TABLE llx_pressing_article ADD INDEX IF NOT EXISTS idx_pressing_article_fk_bon_entree (fk_bon_entree);
ALTER TABLE llx_pressing_article ADD INDEX IF NOT EXISTS idx_pressing_article_fk_entrepot (fk_entrepot);
ALTER TABLE llx_pressing_article ADD INDEX IF NOT EXISTS idx_pressing_article_fk_product (fk_product);
ALTER TABLE llx_pressing_article ADD INDEX IF NOT EXISTS idx_pressing_article_status (status);

-- ============================================================================
-- 3. MIGRATIONS FOR EXISTING TABLES
-- ============================================================================
-- These ALTER statements safely add missing columns if they don't exist
-- They are skipped if columns already exist

-- Add payment columns to llx_pressing_bon_entree if they don't exist
ALTER TABLE llx_pressing_bon_entree ADD COLUMN IF NOT EXISTS payment_status INT DEFAULT 0;
ALTER TABLE llx_pressing_bon_entree ADD COLUMN IF NOT EXISTS payment_amount DOUBLE DEFAULT 0;
ALTER TABLE llx_pressing_bon_entree ADD COLUMN IF NOT EXISTS fk_bank_account INT;
ALTER TABLE llx_pressing_bon_entree ADD COLUMN IF NOT EXISTS date_payment DATETIME;

-- Add fk_bon_entree and qty columns to llx_pressing_article if they don't exist
ALTER TABLE llx_pressing_article ADD COLUMN IF NOT EXISTS fk_bon_entree INT;
ALTER TABLE llx_pressing_article ADD COLUMN IF NOT EXISTS qty INT DEFAULT 1;

-- Make fk_facture nullable for migration compatibility
ALTER TABLE llx_pressing_article MODIFY COLUMN IF EXISTS fk_facture integer NULL;

-- Add foreign key constraint for bon_entree relationship
-- Note: ALTER IGNORE is used to skip if constraint already exists
ALTER IGNORE TABLE llx_pressing_article ADD CONSTRAINT fk_bon_entree_article
  FOREIGN KEY (fk_bon_entree) REFERENCES llx_pressing_bon_entree(rowid);

-- ============================================================================
-- END OF INSTALLATION SCRIPT
-- ============================================================================
