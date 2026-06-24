-- Create table for pressing order lines (articles/items)
CREATE TABLE IF NOT EXISTS llx_pressing_commandedet (
    rowid                   INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_commande             INTEGER NOT NULL,
    rang                    SMALLINT DEFAULT 0,
    description             TEXT,
    type_article            VARCHAR(100),
    couleur                 VARCHAR(50),
    longueur                DOUBLE DEFAULT 0,
    largeur                 DOUBLE DEFAULT 0,
    prix_unitaire           DOUBLE(24,8) DEFAULT 0,
    tva_tx                  DOUBLE DEFAULT 0,
    total_ht                DOUBLE(24,8) DEFAULT 0,
    fk_entrepot             INTEGER DEFAULT NULL,
    fk_statut               TINYINT DEFAULT 0,
    datec                   DATETIME,
    tms                     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fk_commande) REFERENCES llx_pressing_commande(rowid) ON DELETE CASCADE,
    FOREIGN KEY (fk_entrepot) REFERENCES llx_entrepot(rowid) ON DELETE SET NULL,
    INDEX idx_commande (fk_commande),
    INDEX idx_entrepot (fk_entrepot),
    INDEX idx_statut (fk_statut)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
