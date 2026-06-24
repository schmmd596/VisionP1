<?php
$res = 0;
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
$langs->loadLangs(array('admin', 'fiscalmauritanie@fiscalmauritanie'));
if (!$user->admin && !$user->rights->fiscalmauritanie->setup->admin) accessforbidden();
$action = GETPOST('action', 'aZ09');
if ($action === 'save') {
    dolibarr_set_const($db, 'FISCALMAURITANIE_DEFAULT_CURRENCY', GETPOST('currency', 'alpha'), 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'FISCALMAURITANIE_NOTIFY_DAYS', GETPOST('notify_days', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'FISCALMAURITANIE_ENABLE_EMAIL', GETPOSTINT('enable_email'), 'yesno', 0, '', $conf->entity);
    dolibarr_set_const($db, 'FISCALMAURITANIE_ENABLE_INTERNAL', GETPOSTINT('enable_internal'), 'yesno', 0, '', $conf->entity);
    dolibarr_set_const($db, 'FISCALMAURITANIE_ENABLE_SMS', GETPOSTINT('enable_sms'), 'yesno', 0, '', $conf->entity);
    dolibarr_set_const($db, 'FISCALMAURITANIE_ENABLE_WHATSAPP', GETPOSTINT('enable_whatsapp'), 'yesno', 0, '', $conf->entity);
    setEventMessages('Configuration enregistrée', null, 'mesgs');
}
llxHeader('', $langs->trans('Setup'));
print load_fiche_titre('Configuration Fiscal Mauritanie', '', 'generic');
print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save"><table class="border centpercent">';
print '<tr><td class="titlefield">Devise par défaut</td><td><input name="currency" value="'.dol_escape_htmltag(getDolGlobalString('FISCALMAURITANIE_DEFAULT_CURRENCY', 'MRU')).'"></td></tr>';
print '<tr><td>Jours de notification</td><td><input name="notify_days" value="'.dol_escape_htmltag(getDolGlobalString('FISCALMAURITANIE_NOTIFY_DAYS', '30,15,7,3,0')).'"> <span class="opacitymedium">ex. 30,15,7,3,0</span></td></tr>';
print '<tr><td>Email</td><td><input type="checkbox" name="enable_email" value="1"'.(getDolGlobalInt('FISCALMAURITANIE_ENABLE_EMAIL')?' checked':'').'></td></tr>';
print '<tr><td>Notification interne</td><td><input type="checkbox" name="enable_internal" value="1"'.(getDolGlobalInt('FISCALMAURITANIE_ENABLE_INTERNAL')?' checked':'').'></td></tr>';
print '<tr><td>SMS</td><td><input type="checkbox" name="enable_sms" value="1"'.(getDolGlobalInt('FISCALMAURITANIE_ENABLE_SMS')?' checked':'').'></td></tr>';
print '<tr><td>WhatsApp</td><td><input type="checkbox" name="enable_whatsapp" value="1"'.(getDolGlobalInt('FISCALMAURITANIE_ENABLE_WHATSAPP')?' checked':'').'></td></tr>';
print '</table><div class="center"><input class="button" type="submit" value="Enregistrer"></div></form>';
llxFooter(); $db->close();
