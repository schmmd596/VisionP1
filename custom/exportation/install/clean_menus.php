<?php
require_once '../../../main.inc.php';

// Clean old exportation menu entries so module can be re-activated
$sql = "DELETE FROM " . MAIN_DB_PREFIX . "menu WHERE module = 'exportation'";
$res = $db->query($sql);
if ($res) {
    echo "SUCCESS: Deleted exportation menu entries (" . $db->affected_rows($res) . " rows).<br>";
} else {
    echo "ERROR: " . $db->lasterror() . "<br>";
}

// Also try cleaning by mainmenu
$sql2 = "DELETE FROM " . MAIN_DB_PREFIX . "menu WHERE mainmenu = 'exportation'";
$res2 = $db->query($sql2);
if ($res2) {
    echo "SUCCESS: Deleted mainmenu=exportation entries (" . $db->affected_rows($res2) . " rows).<br>";
} else {
    echo "ERROR: " . $db->lasterror() . "<br>";
}

echo "<br><strong>Done! Now go to Admin > Modules and re-enable Exportation.</strong><br>";
echo '<br><a href="/PRG/admin/modules.php">Go to Modules Admin</a>';

$db->close();
