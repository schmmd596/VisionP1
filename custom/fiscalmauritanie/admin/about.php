<?php
$res = 0;
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');
$langs->loadLangs(array('fiscalmauritanie@fiscalmauritanie'));
llxHeader('', 'À propos');
print load_fiche_titre('Module Fiscal Mauritanie', '', 'generic');
print '<p>Version 1.0.0 — Module Dolibarr pour la gestion fiscale et sociale mauritanienne.</p>';
llxFooter(); $db->close();
