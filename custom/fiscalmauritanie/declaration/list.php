<?php
$res = 0;
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');
require_once DOL_DOCUMENT_ROOT.'/custom/fiscalmauritanie/class/fiscalmauritaniedeclaration.class.php';
$langs->loadLangs(array('fiscalmauritanie@fiscalmauritanie'));
if (!$user->rights->fiscalmauritanie->declaration->read) accessforbidden();

$action = GETPOST('action', 'aZ09');
if ($action === 'exportcsv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="declarations_fiscales.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array('Référence', 'Type', 'Période', 'Montant système', 'Pourcentage', 'Montant déclaré', 'Total', 'Échéance', 'Statut'));
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."fiscalmauritanie_declaration WHERE entity IN (".getEntity('fiscalmauritanie').") ORDER BY rowid DESC";
    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) fputcsv($out, array($obj->ref, $obj->tax_type, $obj->period, $obj->amount_system, $obj->declared_percentage, $obj->declared_amount, $obj->total_amount, $obj->due_date, $obj->status));
    fclose($out); exit;
}

llxHeader('', $langs->trans('Declarations'));
print load_fiche_titre($langs->trans('Declarations'), '<a class="butAction" href="card.php?action=create">'.$langs->trans('CreateDeclaration').'</a> <a class="butAction" href="?action=exportcsv">'.$langs->trans('ExportCSV').'</a>', 'generic');
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Référence</th><th>Type</th><th>Période</th><th class="right">Montant système</th><th class="right">% déclaré</th><th class="right">Total</th><th>Échéance</th><th>Statut</th></tr>';
$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."fiscalmauritanie_declaration WHERE entity IN (".getEntity('fiscalmauritanie').") ORDER BY rowid DESC";
$resql = $db->query($sql);
while ($resql && ($obj = $db->fetch_object($resql))) {
    $decl = new FiscalMauritanieDeclaration($db); $decl->fetch($obj->rowid);
    print '<tr class="oddeven"><td><a href="card.php?id='.(int)$decl->id.'">'.dol_escape_htmltag($decl->ref).'</a></td><td>'.dol_escape_htmltag($decl->tax_type).'</td><td>'.dol_escape_htmltag($decl->period).'</td><td class="right">'.price($decl->amount_system).'</td><td class="right">'.price($decl->declared_percentage).'%</td><td class="right">'.price($decl->total_amount).'</td><td>'.dol_print_date($db->jdate($decl->due_date), 'day').'</td><td>'.$decl->getLibStatut(1).'</td></tr>';
}
print '</table>';
llxFooter(); $db->close();
