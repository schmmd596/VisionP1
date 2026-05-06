ALTER TABLE llx_pressing_bon_entree ADD KEY IF NOT EXISTS idx_fk_soc (fk_soc);
ALTER TABLE llx_pressing_bon_entree ADD KEY IF NOT EXISTS idx_status (status);
ALTER TABLE llx_pressing_bon_entree ADD KEY IF NOT EXISTS idx_date_entree (date_entree);
