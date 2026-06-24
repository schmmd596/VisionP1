<?php
// Exécution CLI facultative : php htdocs/custom/fiscalmauritanie/cron/fiscalmauritanie_cron.php
$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path = dirname(__FILE__).'/../../..';
require_once $path.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscalmauritanie/class/fiscalmauritaniecron.class.php';

if ($sapi_type !== 'cli') {
    accessforbidden('CLI only');
}

$cron = new FiscalMauritanieCron($db);
$cron->runNotifications();
print $cron->output."\n";
$cron->prepareDraftDeclarations();
print $cron->output."\n";
$db->close();
