<?php
$res = 0;
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');
require_once DOL_DOCUMENT_ROOT.'/custom/fiscalmauritanie/class/fiscalmauritanierule.class.php';
$langs->loadLangs(array('fiscalmauritanie@fiscalmauritanie'));
if (!$user->rights->fiscalmauritanie->setup->admin) accessforbidden();
llxHeader('', $langs->trans('TaxRules'));
print load_fiche_titre($langs->trans('TaxRules'), '<a class="butAction" href="card.php?action=create">Nouvelle règle</a>', 'generic');
$rule = new FiscalMauritanieRule($db);
$rules = $rule->getRules(false);
print '<table class="noborder centpercent"><tr class="liste_titre"><th>Code</th><th>Libellé</th><th>Type</th><th class="right">Taux</th><th>Fréquence</th><th>Méthode</th><th>Actif</th></tr>';
foreach ($rules as $r) {
    print '<tr class="oddeven"><td><a href="card.php?id='.(int)$r->id.'">'.dol_escape_htmltag($r->code).'</a></td><td>'.dol_escape_htmltag($r->label).'</td><td>'.dol_escape_htmltag($r->tax_type).'</td><td class="right">'.price($r->rate).'%</td><td>'.dol_escape_htmltag($r->frequency).'</td><td>'.dol_escape_htmltag($r->calculation_method).'</td><td>'.yn($r->active).'</td></tr>';
}
print '</table>';
llxFooter(); $db->close();
