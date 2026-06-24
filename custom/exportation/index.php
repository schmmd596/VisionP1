<?php
require_once '../../main.inc.php';

if (!empty($user->admin)) {
    header("Location: seller_dashboard.php?idmenu=145&mainmenu=exportation&leftmenu=");
    exit;
} else {
    header("Location: employee_dashboard.php?idmenu=149&mainmenu=exportation&leftmenu=");
    exit;
}
?>
