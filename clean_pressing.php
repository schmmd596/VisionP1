<?php
/**
 * Script pour nettoyer les références au module Pressing de la base de données
 */

require 'main.inc.php';

// Nettoyage des constantes du module
$sql = "DELETE FROM " . MAIN_DB_PREFIX . "const WHERE name LIKE '%PRESSING%'";
if ($db->query($sql)) {
    echo "✓ Constantes supprimées<br>";
}

// Nettoyage des menus
$sql = "DELETE FROM " . MAIN_DB_PREFIX . "menu WHERE titre LIKE '%Pressing%' OR titre LIKE '%pressing%'";
if ($db->query($sql)) {
    echo "✓ Menus supprimés<br>";
}

// Nettoyage de la table des droits
$sql = "DELETE FROM " . MAIN_DB_PREFIX . "rights_def WHERE rights_class = 'pressing'";
if ($db->query($sql)) {
    echo "✓ Droits supprimés<br>";
}

// Nettoyage de la cache
$sql = "DELETE FROM " . MAIN_DB_PREFIX . "const WHERE name = 'MAIN_MODULE_PRESSING'";
if ($db->query($sql)) {
    echo "✓ Cache du module supprimé<br>";
}

echo "<br><strong>Nettoyage terminé !</strong><br>";
echo "Le menu Pressing devrait disparaître après rafraîchissement de la page.<br>";
echo "Vous pouvez maintenant <a href='admin/modules.php'>aller aux modules</a><br>";

$db->close();
?>
