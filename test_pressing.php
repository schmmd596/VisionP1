<?php
require 'main.inc.php';

echo "Test du module Pressing<br><br>";

try {
    // Essayer de charger la classe du module
    $module_path = DOL_DOCUMENT_ROOT . '/custom/pressing/core/modules/modPressing.class.php';

    if (!file_exists($module_path)) {
        echo "❌ Fichier du module non trouvé: " . $module_path . "<br>";
    } else {
        echo "✓ Fichier du module trouvé<br>";

        require_once $module_path;

        if (!class_exists('modPressing')) {
            echo "❌ Classe modPressing non trouvée<br>";
        } else {
            echo "✓ Classe modPressing chargée<br>";

            $mod = new modPressing($db);
            echo "✓ Module instancié<br>";
            echo "  - Name: " . $mod->name . "<br>";
            echo "  - Description: " . $mod->description . "<br>";
            echo "  - Number: " . $mod->number . "<br>";

            // Essayer d'initialiser le module
            echo "<br>Test de l'initialisation...<br>";
            $result = $mod->init();
            if ($result == 0) {
                echo "✓ Module initialisé avec succès<br>";
            } else {
                echo "❌ Erreur lors de l'initialisation: " . $result . "<br>";
            }
        }
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

$db->close();
?>
