<?php
$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res) die('Include of main fails');
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscalmauritanie/class/fiscalmauritaniedeclaration.class.php';

$langs->loadLangs(array('fiscalmauritanie@fiscalmauritanie'));
if (!$user->rights->fiscalmauritanie->declaration->read) accessforbidden();

$title = $langs->trans('FiscalDashboard');
llxHeader('', $title);
print load_fiche_titre($title, '', 'generic');

$stats = array('total_due' => 0, 'late' => 0, 'draft' => 0, 'validated' => 0, 'paid' => 0);
$sql = "SELECT status, payment_status, due_date, total_amount FROM ".MAIN_DB_PREFIX."fiscalmauritanie_declaration WHERE entity IN (".getEntity('fiscalmauritanie').")";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        if ((int) $obj->status === 0) $stats['draft']++;
        if ((int) $obj->status === 1) $stats['validated']++;
        if ((int) $obj->payment_status === 2) $stats['paid']++;
        if ((int) $obj->payment_status !== 2) $stats['total_due'] += (float) $obj->total_amount;
        if (!empty($obj->due_date) && strtotime($obj->due_date) < dol_now() && (int) $obj->payment_status !== 2) $stats['late']++;
    }
}

print '<div class="fiscalmauritanie-dashboard">';
print '<div class="fiscalmauritanie-card"><div class="fiscalmauritanie-card-title">Impôts à payer</div><div class="fiscalmauritanie-card-value">'.price($stats['total_due']).' MRU</div></div>';
print '<div class="fiscalmauritanie-card"><div class="fiscalmauritanie-card-title">Déclarations en retard</div><div class="fiscalmauritanie-card-value">'.(int) $stats['late'].'</div></div>';
print '<div class="fiscalmauritanie-card"><div class="fiscalmauritanie-card-title">Brouillons</div><div class="fiscalmauritanie-card-value">'.(int) $stats['draft'].'</div></div>';
print '<div class="fiscalmauritanie-card"><div class="fiscalmauritanie-card-title">Validées</div><div class="fiscalmauritanie-card-value">'.(int) $stats['validated'].'</div></div>';
print '</div>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Type</th><th>Période</th><th>Échéance</th><th class="right">Montant</th><th>Statut</th></tr>';
$sql = "SELECT rowid, ref, tax_type, period, due_date, total_amount, status, payment_status FROM ".MAIN_DB_PREFIX."fiscalmauritanie_declaration WHERE entity IN (".getEntity('fiscalmauritanie').") ORDER BY due_date ASC LIMIT 10";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $decl = new FiscalMauritanieDeclaration($db);
        $decl->fetch($obj->rowid);
        print '<tr class="oddeven">';
        print '<td><a href="'.dol_buildpath('/fiscalmauritanie/declaration/card.php', 1).'?id='.(int) $obj->rowid.'">'.dol_escape_htmltag($obj->tax_type).'</a></td>';
        print '<td>'.dol_escape_htmltag($obj->period).'</td>';
        print '<td>'.dol_print_date($db->jdate($obj->due_date), 'day').'</td>';
        print '<td class="right">'.price($obj->total_amount).'</td>';
        print '<td>'.$decl->getLibStatut(1).'</td>';
        print '</tr>';
    }
}
print '</table></div>';

print '<div class="tabsAction"><a class="butAction" href="'.dol_buildpath('/fiscalmauritanie/declaration/card.php?action=create', 1).'">Créer une déclaration</a></div>';
llxFooter();
$db->close();
