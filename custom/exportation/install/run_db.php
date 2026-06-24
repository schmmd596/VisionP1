<?php
$databases = ['prg', 'prg1', 'prg2', 'prg3', 'prg4', 'prg5', 'prg6', 'prg7'];
$db_user = 'root';
$db_pass = '1234';
$db_port = 3307;

$sqls = [
    // ── Shipment header
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_shipment (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        ref varchar(50) NOT NULL,
        container_number varchar(255),
        tracking_number varchar(255),
        shipping_date date,
        arrival_date date,
        status integer DEFAULT 0,
        fk_warehouse integer,
        prix_cbm double(24,8) DEFAULT 0,
        note_public text,
        note_private text,
        entity integer DEFAULT 1,
        date_creation datetime,
        tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        fk_user_creat integer
    ) ENGINE=innodb;",

    "ALTER TABLE llxyv_exportation_shipment ADD COLUMN IF NOT EXISTS prix_cbm double(24,8) DEFAULT 0;",

    // ── Shipment lines
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_shipment_lines (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        fk_shipment integer NOT NULL,
        fk_facture_fourn_det integer NOT NULL,
        nb_cartons double(24,8) DEFAULT 0,
        cbm double(24,8) DEFAULT 0,
        cbm_carton double(24,8) DEFAULT 0,
        qty_carton integer DEFAULT 1,
        qty_shipped double(24,8) DEFAULT 0
    ) ENGINE=innodb;",

    "ALTER TABLE llxyv_exportation_shipment_lines ADD COLUMN IF NOT EXISTS cbm_carton double(24,8) DEFAULT 0;",
    "ALTER TABLE llxyv_exportation_shipment_lines ADD COLUMN IF NOT EXISTS qty_carton integer DEFAULT 1;",
    "ALTER TABLE llxyv_exportation_shipment_lines ADD COLUMN IF NOT EXISTS nb_cartons double(24,8) DEFAULT 0;",
    "ALTER TABLE llxyv_exportation_shipment_lines ADD COLUMN IF NOT EXISTS fk_warehouse integer DEFAULT 0;",

    // ── Shipment expenses
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_shipment_expenses (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        fk_shipment integer NOT NULL,
        label varchar(255),
        amount double(24,8) DEFAULT 0,
        currency_code varchar(3) DEFAULT 'USD',
        exchange_rate double(24,12) DEFAULT 1,
        amount_local double(24,8) DEFAULT 0,
        beneficiary varchar(255)
    ) ENGINE=innodb;",

    // ── Invoice info (currency & rate per invoice)
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_invoice_info (
        fk_facture_fourn integer PRIMARY KEY,
        currency_code varchar(10) DEFAULT 'MRU',
        exchange_rate double(24,12) DEFAULT 1
    ) ENGINE=innodb;",

    // ── Invoice line info (carton data per product line)
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_invoice_line_info (
        fk_facture_fourn_det integer PRIMARY KEY,
        cbm_carton double(24,8) DEFAULT 0,
        qty_carton integer DEFAULT 1,
        nb_cartons double(24,8) DEFAULT 0,
        pu_devise double(24,8) DEFAULT 0
    ) ENGINE=innodb;",

    // ── Invoice expenses
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_invoice_expenses (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        fk_facture_fourn integer NOT NULL,
        label varchar(255),
        amount double(24,8) DEFAULT 0,
        currency_code varchar(10) DEFAULT 'MRU',
        exchange_rate double(24,12) DEFAULT 1,
        amount_local double(24,8) DEFAULT 0
    ) ENGINE=innodb;",

    // ── Offset
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_offset (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        fk_soc integer NOT NULL,
        amount double(24,8) NOT NULL,
        date_offset datetime,
        fk_user_creat integer,
        note text
    ) ENGINE=innodb;",

    // ── Invoice payments
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_invoice_payments (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        fk_facture_fourn integer NOT NULL,
        fk_bank integer DEFAULT 0,
        datep date NOT NULL,
        amount double(24,8) DEFAULT 0,
        currency_code varchar(10) DEFAULT 'MRU',
        exchange_rate double(24,12) DEFAULT 1,
        amount_local double(24,8) DEFAULT 0,
        note text
    ) ENGINE=innodb;",

    "ALTER TABLE llxyv_exportation_invoice_payments ADD COLUMN IF NOT EXISTS fk_bank integer DEFAULT 0;",
    "ALTER TABLE llxyv_exportation_invoice_payments ADD COLUMN IF NOT EXISTS fk_paiement_fourn integer DEFAULT 0;",

    // ── Expenses columns
    "ALTER TABLE llxyv_exportation_invoice_expenses ADD COLUMN IF NOT EXISTS fk_bank integer DEFAULT 0;",
    "ALTER TABLE llxyv_exportation_invoice_expenses ADD COLUMN IF NOT EXISTS expense_target varchar(20) DEFAULT 'NOUS';",

    // ── Shipment docs
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_shipment_docs (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        fk_shipment integer NOT NULL,
        label varchar(255),
        filename varchar(255),
        date_upload datetime
    ) ENGINE=innodb;",

    // ── Unified account operations journal
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_account_operations (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        fk_soc integer NOT NULL,
        operation_date date NOT NULL,
        operation_type varchar(20) NOT NULL,
        label varchar(255),
        debit double(24,8) DEFAULT 0,
        credit double(24,8) DEFAULT 0,
        currency_code varchar(10) DEFAULT 'MRU',
        exchange_rate double(24,12) DEFAULT 1,
        amount_local double(24,8) DEFAULT 0,
        fk_origin integer DEFAULT 0,
        origin_type varchar(50),
        fk_user_creat integer,
        date_creation datetime
    ) ENGINE=innodb;",

    // ── Partners
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_partners (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        name varchar(255) NOT NULL,
        percentage double(10,4) DEFAULT 0,
        fk_user integer DEFAULT 0,
        active integer DEFAULT 1,
        note text,
        capital_initial double(24,8) DEFAULT 0,
        date_creation datetime,
        tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=innodb;",

    // ── Partner distributions
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_partner_distributions (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        fk_partner integer NOT NULL,
        dist_date date NOT NULL,
        amount double(24,8) DEFAULT 0,
        dist_type varchar(20) DEFAULT 'RETRAIT',
        label varchar(255),
        fk_user_creat integer,
        date_creation datetime
    ) ENGINE=innodb;",

    // ── Employees (decoupled from llxyv_user: an employee may or may not be a system user)
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_employees (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        name varchar(255) NOT NULL,
        salary double(24,8) DEFAULT 0,
        fk_user integer DEFAULT NULL,
        status tinyint DEFAULT 1,
        date_creation datetime,
        fk_user_creat integer,
        UNIQUE KEY uk_emp_fk_user (fk_user)
    ) ENGINE=innodb;",

    // ── Employee operations
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_employee_ops (
        rowid integer AUTO_INCREMENT PRIMARY KEY,
        fk_user integer NOT NULL,
        fk_employee integer DEFAULT NULL,
        op_date date NOT NULL,
        amount double(24,8) DEFAULT 0,
        op_type varchar(20) DEFAULT 'SALAIRE', -- SALAIRE, DEPENSE, AVANCE
        label varchar(255),
        fk_bank integer DEFAULT 0,
        cycle_close tinyint DEFAULT 0,
        fk_bank_line integer DEFAULT NULL,
        fk_user_creat integer,
        date_creation datetime
    ) ENGINE=innodb;",

    "ALTER TABLE llxyv_exportation_employee_ops ADD COLUMN IF NOT EXISTS fk_employee integer DEFAULT NULL;",
    "ALTER TABLE llxyv_exportation_employee_ops ADD COLUMN IF NOT EXISTS cycle_close tinyint DEFAULT 0;",
    "ALTER TABLE llxyv_exportation_employee_ops ADD COLUMN IF NOT EXISTS fk_bank_line integer DEFAULT NULL;",

    // ── Supplier Type
    "CREATE TABLE IF NOT EXISTS llxyv_exportation_supplier_type (
        fk_soc integer PRIMARY KEY,
        type varchar(20) DEFAULT 'INTERNE'
    ) ENGINE=innodb;",

    // ── Expenses bank columns
    "ALTER TABLE llxyv_exportation_expenses ADD COLUMN IF NOT EXISTS fk_bank integer DEFAULT NULL;",
    "ALTER TABLE llxyv_exportation_expenses ADD COLUMN IF NOT EXISTS fk_bank_line integer DEFAULT NULL;"
];

foreach ($databases as $db_name) {
    try {
        $pdo = new PDO("mysql:host=localhost;port=$db_port;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach ($sqls as $sql) {
            $pdo->exec($sql);
        }
        echo "OK: $db_name\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "OK (already updated): $db_name\n";
        } else {
            echo "ERROR $db_name: " . $e->getMessage() . "\n";
        }
    }
}
