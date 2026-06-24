<?php
if (!defined('NOREQUIREUSER')) define('NOREQUIREUSER', '1');
if (!defined('NOREQUIREDB')) define('NOREQUIREDB', '1');
if (!defined('NOREQUIRESOC')) define('NOREQUIRESOC', '1');
if (!defined('NOREQUIRETRAN')) define('NOREQUIRETRAN', '1');
require_once '../../main.inc.php';
header('Content-type: application/javascript');
?>
(function () {
    'use strict';
    window.FiscalMauritanie = window.FiscalMauritanie || {};
    window.FiscalMauritanie.confirmAction = function (message) {
        return window.confirm(message || 'Confirmer cette action ?');
    };
})();
