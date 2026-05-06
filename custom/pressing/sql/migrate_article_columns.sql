-- Migration: Add fk_bon_entree column to llx_pressing_article
-- This migration adds support for linking articles to reception orders (bons d'entrée)
-- Note: The column may already exist if the table was created fresh

-- The init() function in modPressing will handle these migrations
-- MySQL will skip ALTER TABLE ADD if column already exists in some configurations
