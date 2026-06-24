<?php
$res = 0;
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');
$langs->loadLangs(array('fiscalmauritanie@fiscalmauritanie'));
if (!$user->rights->fiscalmauritanie->declaration->read) accessforbidden();
llxHeader('', $langs->trans('Notifications'));
print load_fiche_titre($langs->trans('Notifications'), '', 'generic');
print '<table class="noborder centpercent"><tr class="liste_titre"><th>Date</th><th>Impôt</th><th>Canal</th><th>Sujet</th><th>Statut</th></tr>';
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."fiscalmauritanie_notification WHERE entity IN (".getEntity('fiscalmauritanie').") ORDER BY rowid DESC LIMIT 200";
$resql = $db->query($sql);
while ($resql && ($obj = $db->fetch_object($resql))) {
    print '<tr class="oddeven"><td>'.dol_print_date($db->jdate($obj->notification_date), 'dayhour').'</td><td>'.dol_escape_htmltag($obj->tax_type).'</td><td>'.dol_escape_htmltag($obj->channel).'</td><td>'.dol_escape_htmltag($obj->subject).'</td><td>'.($obj->status==1?'Envoyée':($obj->status<0?'Erreur':'Prévue')).'</td></tr>';
}
print '</table>';
llxFooter(); $db->close();
