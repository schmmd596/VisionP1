<?php
$databases = ['prg', 'prg1', 'prg2', 'prg3', 'prg4', 'prg5', 'prg6', 'prg7'];
foreach ($databases as $db_name) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=$db_name", 'root', '');
        $res = $pdo->query("SELECT rowid FROM llxyv_entrepot WHERE ref = 'MAGAZAIN DEFAULT'");
        if (!$res->fetch()) {
            $pdo->exec("INSERT INTO llxyv_entrepot (ref, label, entity, statut, fk_user_author, datec) VALUES ('MAGAZAIN DEFAULT', 'MAGAZAIN DEFAULT', 1, 1, 1, NOW())");
            echo "Warehouse created in $db_name\n";
        } else {
            echo "Warehouse already exists in $db_name\n";
        }
    } catch (Exception $e) {
        echo "Error in $db_name: " . $e->getMessage() . "\n";
    }
}
