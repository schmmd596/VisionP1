<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

$langs->loadLangs(array("companies", "bills", "banks", "exportation@exportation"));
$form = new Form($db);
$uid = $user->id;
$date_filter = date('Y-m-d');
$date_from = GETPOST('date_from', 'alpha') ?: date('Y-m-d', strtotime('-1 day'));
$date_to = GETPOST('date_to', 'alpha') ?: date('Y-m-d');

llxHeader('', "Mon Espace — لوحة الموظف", '');
print '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">';
print '<div id="ed">';
print '<style>
#ed{max-width:1500px;margin:0 auto;font-family:"Outfit",sans-serif;padding:20px;color:#1e2a3a}
.ec{background:#fff;border-radius:16px;padding:24px 26px;box-shadow:0 2px 18px rgba(30,42,58,.06);border:1px solid #eef2f7;margin-bottom:20px}
.eh{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #f0f4f8;gap:10px;flex-wrap:wrap}
.eh-l{display:flex;align-items:center;gap:10px}
.ei{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0}
.ei.bl{background:linear-gradient(135deg,#3b82f6,#2563eb)}.ei.gr{background:linear-gradient(135deg,#10b981,#059669)}
.ei.am{background:linear-gradient(135deg,#f59e0b,#d97706)}.ei.ro{background:linear-gradient(135deg,#f43f5e,#e11d48)}
.ei.pu{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}.ei.cy{background:linear-gradient(135deg,#06b6d4,#0891b2)}
.ei.tl{background:linear-gradient(135deg,#14b8a6,#0d9488)}
.et{font-size:16px;font-weight:800}.es{font-size:11px;color:#94a3b8;margin-top:1px}
.eg{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
.ek{background:#f8fafc;border-radius:12px;padding:20px;border:1px solid #eef2f7;text-align:center;transition:.2s}
.ek:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(30,42,58,.08)}
.ek .kl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:6px}
.ek .kv{font-size:24px;font-weight:800}
.ek .ks{font-size:11px;color:#94a3b8;margin-top:3px}
.tb{width:100%;border-collapse:collapse;font-size:13px}
.tb th{background:#f8fafc;padding:9px 11px;text-align:left;color:#64748b;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #eef2f7;white-space:nowrap}
.tb td{padding:10px 11px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.tb tbody tr:hover td{background:#fafbfd}
.dp{background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.si{padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:"Outfit",sans-serif;background:#fff}
.date-filter-form{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.sb{background:#3b82f6;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px;transition:.15s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.sb:hover{filter:brightness(1.08);transform:translateY(-1px)}
.qg{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
.ql{display:flex;align-items:center;gap:12px;padding:16px 18px;background:#f8fafc;border-radius:12px;border:1px solid #eef2f7;text-decoration:none;color:#1e2a3a;font-weight:600;font-size:13px;transition:.2s}
.ql:hover{background:#eff6ff;transform:translateY(-2px);box-shadow:0 4px 16px rgba(30,42,58,.08)}
.ql i{font-size:20px}
.avatar{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;font-weight:800;flex-shrink:0}
</style>';

// ═══ HEADER ═══
$initials = strtoupper(substr($user->firstname, 0, 1) . substr($user->lastname, 0, 1));
if (strlen($initials) < 2) $initials = strtoupper(substr($user->login, 0, 2));
print '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:14px;">';
print '<div style="display:flex;align-items:center;gap:16px;"><div class="avatar">' . $initials . '</div>';
print '<div><div style="font-size:24px;font-weight:800;">مرحبا ' . dol_escape_htmltag($user->firstname ?: $user->login) . '</div>';
print '<div style="font-size:13px;color:#94a3b8;">Mon Espace — لوحة الموظف</div></div></div>';
print '<form method="GET" class="date-filter-form">';
print '<div style="display:flex;align-items:center;gap:8px;"><label style="font-size:12px;font-weight:600;">من</label><input type="date" name="date_from" value="' . $date_from . '" class="si" onchange="this.form.submit()"></div>';
print '<div style="display:flex;align-items:center;gap:8px;"><label style="font-size:12px;font-weight:600;">إلى</label><input type="date" name="date_to" value="' . $date_to . '" class="si" onchange="this.form.submit()"></div>';
print '</form>';
print '</div>';

// ═══ KPIs — Employee's own activity ═══
// My sales today
$ob_my_sales = $db->fetch_object($db->query("SELECT COUNT(*) as nb, COALESCE(SUM(total_ttc),0) as total FROM " . MAIN_DB_PREFIX . "facture WHERE fk_user_author = " . $uid . " AND datef = '" . $db->escape($date_filter) . "' AND fk_statut > 0 AND entity = " . (int) $conf->entity));

// My total sales this month
$m_start = date('Y-m-01');
$ob_my_month = $db->fetch_object($db->query("SELECT COUNT(*) as nb, COALESCE(SUM(total_ttc),0) as total FROM " . MAIN_DB_PREFIX . "facture WHERE fk_user_author = " . $uid . " AND datef >= '" . $m_start . "' AND fk_statut > 0 AND entity = " . (int) $conf->entity));

// My internal supplier invoices (standard Dolibarr facture_fourn — NOT external import dossiers)
$ob_my_fourn = $db->fetch_object($db->query("SELECT COUNT(*) as nb, COALESCE(SUM(total_ttc),0) as total FROM " . MAIN_DB_PREFIX . "facture_fourn WHERE fk_user_author = " . $uid . " AND entity = " . (int) $conf->entity));

// My payments registered today
$ob_my_pay = $db->fetch_object($db->query("SELECT COALESCE(SUM(pf.amount),0) as total FROM " . MAIN_DB_PREFIX . "paiement p INNER JOIN " . MAIN_DB_PREFIX . "paiement_facture pf ON pf.fk_paiement = p.rowid WHERE p.fk_user_creat = " . $uid . " AND p.datep = '" . $db->escape($date_filter) . "'"));

// Clients I manage
$ob_my_clients = $db->fetch_object($db->query("SELECT COUNT(DISTINCT fk_soc) as nb FROM " . MAIN_DB_PREFIX . "facture WHERE fk_user_author = " . $uid . " AND fk_statut > 0 AND entity = " . (int) $conf->entity));

// Bank balances - Use Account class to get balances
require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';
$bank_sql = "SELECT ba.rowid, ba.label, ba.currency_code,
    (SELECT COALESCE(SUM(b.amount),0) FROM " . MAIN_DB_PREFIX . "bank b WHERE b.fk_account = ba.rowid AND b.dateo <= '" . $db->escape($date_to) . "') as solde_to,
    (SELECT COALESCE(SUM(b.amount),0) FROM " . MAIN_DB_PREFIX . "bank b WHERE b.fk_account = ba.rowid AND b.dateo BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "') as flux
    FROM " . MAIN_DB_PREFIX . "bank_account ba ORDER BY ba.label";
$ob_banks = $db->query($bank_sql);

// Debug: Store error message for display
$bank_error = '';
if (!$ob_banks) {
    $bank_error = $db->lasterror();
}

print '<div class="eg">';
print '<div class="ek"><div class="kl"><i class="fa-solid fa-receipt"></i> مبيعاتي اليوم Mes Ventes</div><div class="kv" style="color:#3b82f6;">' . price($ob_my_sales->total) . '</div><div class="ks">' . (int) $ob_my_sales->nb . ' facture(s)</div></div>';
print '<div class="ek"><div class="kl"><i class="fa-solid fa-calendar"></i> هذا الشهر Ce Mois</div><div class="kv" style="color:#8b5cf6;">' . price($ob_my_month->total) . '</div><div class="ks">' . (int) $ob_my_month->nb . ' facture(s)</div></div>';
print '<div class="ek"><div class="kl"><i class="fa-solid fa-hand-holding-dollar"></i> مقبوضاتي Encaissements</div><div class="kv" style="color:#10b981;">' . price($ob_my_pay->total) . '</div><div class="ks">' . $date_filter . '</div></div>';
print '<div class="ek"><div class="kl"><i class="fa-solid fa-truck-field"></i> فواتير الموردين الداخلية Fourn. Internes</div><div class="kv" style="color:#f59e0b;">' . (int) $ob_my_fourn->nb . '</div><div class="ks">' . price($ob_my_fourn->total) . '</div></div>';
print '<div class="ek"><div class="kl"><i class="fa-solid fa-user-tie"></i> زبائني Clients</div><div class="kv" style="color:#f43f5e;">' . (int) $ob_my_clients->nb . '</div></div>';
print '</div>';

// ═══ Quick Access — Only employee-authorized modules ═══
print '<div class="ec" style="margin-top:20px;"><div class="eh"><div class="eh-l"><div class="ei bl"><i class="fa-solid fa-grid-2"></i></div><div><div class="et">اختصاراتي Mes Raccourcis</div><div class="es">Accès rapide aux modules autorisés</div></div></div></div>';
print '<div class="qg">';
$my_links = array(
    array('unified_account.php', 'fa-building-columns', '#06b6d4', 'الحسابات الموحدة Compte Unifié'),
    array('customer_dashboard.php', 'fa-user-tie', '#8b5cf6', 'الزبائن Dashboard Client'),
    array(DOL_URL_ROOT . '/compta/facture/list.php', 'fa-cash-register', '#3b82f6', 'فواتير البيع Factures Client'),
    array(DOL_URL_ROOT . '/fourn/facture/list.php', 'fa-truck-field', '#f59e0b', 'فواتير الموردين الداخلية Fourn. Internes'),
    array(DOL_URL_ROOT . '/compta/paiement/list.php', 'fa-hand-holding-dollar', '#10b981', 'المقبوضات Encaissements'),
);
foreach ($my_links as $lk) {
    print '<a href="' . $lk[0] . '" class="ql"><i class="fa-solid ' . $lk[1] . '" style="color:' . $lk[2] . ';"></i> ' . $lk[3] . '</a>';
}
print '</div></div>';

// ═══ Two-column: My Recent Client Sales + My Internal Supplier Invoices ═══
print '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">';

// My recent client sales
print '<div class="ec"><div class="eh"><div class="eh-l"><div class="ei gr"><i class="fa-solid fa-receipt"></i></div><div><div class="et">آخر مبيعاتي Mes Dernières Ventes</div><div class="es">Factures client créées par moi</div></div></div></div>';
$rs = $db->query("SELECT f.rowid, f.ref, f.datef, f.total_ttc, s.nom, f.fk_statut FROM " . MAIN_DB_PREFIX . "facture f INNER JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc WHERE f.fk_user_author = " . $uid . " AND f.entity = " . (int) $conf->entity . " ORDER BY f.datef DESC, f.rowid DESC LIMIT 15");
print '<table class="tb"><thead><tr><th>المرجع Réf</th><th>التاريخ Date</th><th>الزبون Client</th><th>المبلغ Total</th><th>الحالة</th></tr></thead><tbody>';
$hs = false;
while ($rs !== false && ($sl = $db->fetch_object($rs))) {
    $hs = true;
    $st_badge = $sl->fk_statut == 0 ? '<span class="dp" style="background:#fef3c7;color:#92400e;">مسودة</span>' : '<span class="dp" style="background:#dcfce7;color:#15803d;">مصادق</span>';
    print '<tr><td><a href="' . DOL_URL_ROOT . '/compta/facture/card.php?facid=' . $sl->rowid . '" style="color:#3b82f6;font-weight:700;">' . $sl->ref . '</a></td>';
    print '<td>' . dol_print_date($db->jdate($sl->datef), 'day') . '</td>';
    print '<td>' . dol_escape_htmltag($sl->nom) . '</td>';
    print '<td style="font-weight:700;">' . price($sl->total_ttc) . '</td>';
    print '<td>' . $st_badge . '</td></tr>';
}
if (!$hs) print '<tr><td colspan="5" style="text-align:center;padding:20px;color:#94a3b8;">لا توجد فواتير</td></tr>';
print '</tbody></table></div>';

// My internal supplier invoices (standard Dolibarr facture_fourn)
print '<div class="ec"><div class="eh"><div class="eh-l"><div class="ei am"><i class="fa-solid fa-truck-field"></i></div><div><div class="et">فواتير الموردين الداخلية Factures Fournisseur Interne</div><div class="es">Les factures fournisseur dans Dolibarr</div></div></div></div>';
$rf = $db->query("SELECT f.rowid, f.ref, f.datef, f.total_ttc, s.nom, f.fk_statut FROM " . MAIN_DB_PREFIX . "facture_fourn f INNER JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc WHERE f.fk_user_author = " . $uid . " AND f.entity = " . (int) $conf->entity . " ORDER BY f.datef DESC, f.rowid DESC LIMIT 15");
print '<table class="tb"><thead><tr><th>المرجع Réf</th><th>التاريخ Date</th><th>المورد Fournisseur Interne</th><th>المبلغ Total</th><th>الحالة</th></tr></thead><tbody>';
$hf = false;
while ($rf !== false && ($fl = $db->fetch_object($rf))) {
    $hf = true;
    $st = $fl->fk_statut == 0 ? '<span class="dp" style="background:#fef3c7;color:#92400e;">مسودة</span>' : '<span class="dp" style="background:#dcfce7;color:#15803d;">مصادق</span>';
    print '<tr><td><a href="' . DOL_URL_ROOT . '/fourn/facture/card.php?facid=' . $fl->rowid . '" style="color:#3b82f6;font-weight:700;">' . $fl->ref . '</a></td>';
    print '<td>' . dol_print_date($db->jdate($fl->datef), 'day') . '</td>';
    print '<td>' . dol_escape_htmltag($fl->nom) . '</td>';
    print '<td style="font-weight:700;">' . price($fl->total_ttc) . '</td>';
    print '<td>' . $st . '</td></tr>';
}
if (!$hf) print '<tr><td colspan="5" style="text-align:center;padding:20px;color:#94a3b8;">لا توجد فواتير مورد داخلية</td></tr>';
print '</tbody></table></div>';

print '</div>';

// ═══ Caisses & Banques ═══
print '<div class="ec" style="margin-top:20px;"><div class="eh"><div class="eh-l"><div class="ei gr"><i class="fa-solid fa-vault"></i></div><div><div class="et">الصندوق Caisses &amp; Banques</div><div class="es">من ' . $date_from . ' إلى ' . $date_to . '</div></div></div></div>';
print '<table class="tb"><thead><tr><th>الحساب Compte</th><th>العملة Devise</th><th>الحركة Flux (' . $date_from . '→' . $date_to . ')</th></tr></thead><tbody>';
$bank_count = 0;
$total_flux = 0;
$debug_info = '';
if ($ob_banks) {
    $db_num_rows = $db->num_rows($ob_banks);
    $debug_info = "Total rows: " . $db_num_rows;
    while ($bk = $db->fetch_object($ob_banks)) {
        $bank_count++;
        $solde = (float) $bk->solde_to;
        $flux  = (float) $bk->flux;
        $total_flux += $flux;
        $sc = $solde >= 0 ? 'color:#10b981;' : 'color:#ef4444;';
        $fc = $flux >= 0  ? 'color:#10b981;' : 'color:#ef4444;';
        print '<tr><td><strong>' . dol_escape_htmltag($bk->label) . '</strong></td>';
        print '<td><span class="dp">' . $bk->currency_code . '</span></td>';
        print '<td style="font-weight:700;' . $fc . '">' . ($flux >= 0 ? '+' : '') . price($flux) . '</td></tr>';
    }
} else {
    $debug_info = !empty($bank_error) ? "SQL Error: " . $bank_error : "No data";
}
if ($bank_count === 0) {
    print '<tr><td colspan="3" style="text-align:center;padding:20px;color:#94a3b8;">Aucun compte bancaire trouvé' . ($user->admin ? '<br><small style="color:#e11d48;font-size:10px;">' . $debug_info . '</small>' : '') . '</td></tr>';
}
if ($bank_count > 0) {
    $tc = $total_flux >= 0 ? 'color:#10b981;' : 'color:#ef4444;';
    print '<tr style="border-top:3px double #cbd5e1;background:#f8fafc;"><td colspan="2" style="font-weight:800;text-align:right;padding:12px 11px;">الإجمالي Total</td>';
    print '<td style="font-weight:900;font-size:15px;' . $tc . 'padding:12px 11px;">' . ($total_flux >= 0 ? '+' : '') . price($total_flux) . '</td></tr>';
}
print '</tbody></table></div>';

print '</div>';
llxFooter();
$db->close();
