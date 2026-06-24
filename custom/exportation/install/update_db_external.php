<?php
require_once __DIR__ . '/../../../main.inc.php';

$db->begin();

$sql1 = "ALTER TABLE " . MAIN_DB_PREFIX . "societe ADD COLUMN IF NOT EXISTS exportation_type VARCHAR(20) DEFAULT 'INTERNAL'";
$res1 = $db->query($sql1);
if (!$res1 && strpos($db->lasterror(), 'Duplicate column name') === false) {
    echo "Error 1: " . $db->lasterror() . "\n";
}

$sql2 = "ALTER TABLE " . MAIN_DB_PREFIX . "exportation_account_operations ADD COLUMN IF NOT EXISTS fk_bank INT DEFAULT NULL";
$res2 = $db->query($sql2);
if (!$res2 && strpos($db->lasterror(), 'Duplicate column name') === false) {
    echo "Error 2: " . $db->lasterror() . "\n";
}

$db->commit();
echo "Database updated successfully.\n";
