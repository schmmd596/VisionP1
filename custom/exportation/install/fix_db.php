<?php
require_once '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

// Check permissions
// if (!$user->admin) {
//     accessforbidden();
// }

$db->begin();

$sqls = [
    // Feature 1: Shipment / Containers
    "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "exportation_shipment (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        ref varchar(50) NOT NULL,
        container_number varchar(255),
        tracking_number varchar(255),
        shipping_date date,
        arrival_date date,
        status integer DEFAULT 0, -- 0:Draft, 1:Shipping, 2:Arrived, 3:Entered Stock
        fk_warehouse integer,
        note_public text,
        note_private text,
        entity integer DEFAULT 1,
        date_creation datetime,
        tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        fk_user_creat integer
    ) ENGINE=innodb;",

    // New structure for shipment lines (links per product)
    "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "exportation_shipment_lines (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        fk_shipment integer NOT NULL,
        fk_facture_fourn_det integer NOT NULL,
        cbm double(24,8) DEFAULT 0,
        qty_shipped double(24,8) DEFAULT 0
    ) ENGINE=innodb;",

    "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "exportation_shipment_expenses (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        fk_shipment integer NOT NULL,
        label varchar(255),
        amount double(24,8) DEFAULT 0,
        currency_code varchar(3) DEFAULT 'USD',
        exchange_rate double(24,12) DEFAULT 1,
        amount_local double(24,8) DEFAULT 0,
        beneficiary varchar(255)
    ) ENGINE=innodb;",

    "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "exportation_invoice_info (
        fk_facture_fourn integer PRIMARY KEY,
        currency_code varchar(10) DEFAULT 'MRU',
        exchange_rate double(24,12) DEFAULT 1
    ) ENGINE=innodb;",

    // Expenses specific to supplier invoice
    "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "exportation_invoice_expenses (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        fk_facture_fourn integer NOT NULL,
        label varchar(255),
        amount double(24,8) DEFAULT 0,
        currency_code varchar(10) DEFAULT 'MRU',
        exchange_rate double(24,12) DEFAULT 1,
        amount_local double(24,8) DEFAULT 0
    ) ENGINE=innodb;",

    // Feature 2: Offsetting / Unified Multi-payments
    "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "exportation_offset (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        fk_soc integer NOT NULL,
        amount double(24,8) NOT NULL,
        date_offset datetime,
        fk_user_creat integer,
        note text
    ) ENGINE=innodb;",
    
    // Add custom column to societe for risk level if not exist
    // Add custom column to societe for risk level if not exist
    "ALTER TABLE " . MAIN_DB_PREFIX . "societe ADD COLUMN IF NOT EXISTS exportation_risk varchar(20) DEFAULT 'ACTIVE';",
    
    // Add external type for distinct supplier management
    "ALTER TABLE " . MAIN_DB_PREFIX . "societe ADD COLUMN IF NOT EXISTS exportation_type VARCHAR(20) DEFAULT 'INTERNAL';",
    
    // Add bank reference to all operations
    "ALTER TABLE " . MAIN_DB_PREFIX . "exportation_account_operations ADD COLUMN IF NOT EXISTS fk_bank INT DEFAULT NULL;"
];

$errors = 0;
foreach ($sqls as $sql) {
    if (!$db->query($sql)) {
        if ($db->lasterror() && strpos($db->lasterror(), 'Duplicate column name') === false) {
            echo "Error: " . $db->lasterror() . "<br>";
            $errors++;
        }
    }
}

if ($errors == 0) {
    $db->commit();
    echo "Module Exportation: Database schema initialized successfully.<br>";
} else {
    $db->rollback();
    echo "Module Exportation: Initialization failed.<br>";
}
