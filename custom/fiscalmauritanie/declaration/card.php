<?php
$res = 0;
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscalmauritanie/class/fiscalmauritaniedeclaration.class.php';
$langs->loadLangs(array('fiscalmauritanie@fiscalmauritanie'));
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
if (!$user->rights->fiscalmauritanie->declaration->read) accessforbidden();
$form = new Form($db);
$object = new FiscalMauritanieDeclaration($db);
if ($id > 0) $object->fetch($id);

if (($action === 'add' || $action === 'update') && $user->rights->fiscalmauritanie->declaration->write) {
    $object->tax_type = GETPOST('tax_type', 'alpha');
    $object->period = GETPOST('period', 'alphanohtml');
    $object->period_start = dol_mktime(0,0,0, GETPOSTINT('period_startmonth'), GETPOSTINT('period_startday'), GETPOSTINT('period_startyear'));
    $object->period_end = dol_mktime(0,0,0, GETPOSTINT('period_endmonth'), GETPOSTINT('period_endday'), GETPOSTINT('period_endyear'));
    $object->mode_declaration = GETPOST('mode_declaration', 'alpha');
    $object->amount_system = price2num(GETPOST('amount_system', 'alpha'));
    $object->declared_percentage = price2num(GETPOST('declared_percentage', 'alpha'));
    $object->declared_amount = price2num(GETPOST('declared_amount', 'alpha'));
    $object->adjustment_amount = price2num(GETPOST('adjustment_amount', 'alpha'));
    $object->penalty_amount = price2num(GETPOST('penalty_amount', 'alpha'));
    $object->due_date = dol_mktime(0,0,0, GETPOSTINT('due_datemonth'), GETPOSTINT('due_dateday'), GETPOSTINT('due_dateyear'));
    $object->note_private = GETPOST('note_private', 'restricthtml');
    if ($action === 'add') $res = $object->create($user); else $res = $object->update($user);
    if ($res > 0) { header('Location: card.php?id='.$object->id); exit; }
    setEventMessages($object->error, null, 'errors');
}
if ($action === 'validate' && $id > 0 && $user->rights->fiscalmauritanie->declaration->validate) { $object->validate($user); header('Location: card.php?id='.$id); exit; }

llxHeader('', $langs->trans('TaxDeclaration'));
$title = $action === 'create' ? $langs->trans('CreateDeclaration') : $langs->trans('TaxDeclaration').' '.$object->ref;
print load_fiche_titre($title, '', 'generic');
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="'.($action === 'create' ? 'add' : 'update').'">';
if ($id > 0) print '<input type="hidden" name="id" value="'.(int)$id.'">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">Type impôt</td><td><select name="tax_type">';
foreach (array('ITS','CNSS','CNAM','IS','TA','PATENTE','IMF','IMF_HONORAIRES') as $type) print '<option value="'.$type.'"'.($object->tax_type===$type?' selected':'').'>'.$type.'</option>';
print '</select></td></tr>';
print '<tr><td>Période</td><td><input name="period" value="'.dol_escape_htmltag($object->period).'" placeholder="2026-01"></td></tr>';
print '<tr><td>Début période</td><td>'.$form->selectDate($object->period_start ?: dol_now(), 'period_start', 0, 0, 0, '', 1, 1).'</td></tr>';
print '<tr><td>Fin période</td><td>'.$form->selectDate($object->period_end ?: dol_now(), 'period_end', 0, 0, 0, '', 1, 1).'</td></tr>';
print '<tr><td>Mode</td><td><select name="mode_declaration"><option value="real">Réel système</option><option value="percentage"'.($object->mode_declaration==='percentage'?' selected':'').'>Pourcentage du système</option><option value="manual"'.($object->mode_declaration==='manual'?' selected':'').'>Manuel</option></select></td></tr>';
print '<tr><td>Montant système</td><td><input name="amount_system" value="'.price($object->amount_system).'" class="right"></td></tr>';
print '<tr><td>Pourcentage déclaré</td><td><input name="declared_percentage" value="'.price($object->declared_percentage ?: 100).'" class="right"> %</td></tr>';
print '<tr><td>Montant déclaré manuel</td><td><input name="declared_amount" value="'.price($object->declared_amount).'" class="right"></td></tr>';
print '<tr><td>Ajustement</td><td><input name="adjustment_amount" value="'.price($object->adjustment_amount).'" class="right"></td></tr>';
print '<tr><td>Pénalité</td><td><input name="penalty_amount" value="'.price($object->penalty_amount).'" class="right"></td></tr>';
print '<tr><td>Échéance</td><td>'.$form->selectDate($object->due_date ?: dol_now(), 'due_date', 0, 0, 0, '', 1, 1).'</td></tr>';
print '<tr><td>Note interne</td><td><textarea name="note_private" class="flat" rows="3">'.dol_escape_htmltag($object->note_private).'</textarea></td></tr>';
print '</table><div class="center"><input type="submit" class="button" value="Enregistrer"></div></form>';
if ($id > 0) {
    print '<div class="tabsAction">';
    if ($object->status == 0 && $user->rights->fiscalmauritanie->declaration->validate) print '<a class="butAction" href="card.php?id='.(int)$id.'&action=validate&token='.newToken().'">Valider</a>';
    print '<a class="butAction" href="../core/modules/fiscalmauritanie/pdf_standard.modules.php?id='.(int)$id.'">PDF</a>';
    print '</div>';
}
llxFooter(); $db->close();
