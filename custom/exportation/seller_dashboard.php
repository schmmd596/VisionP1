<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

$langs->loadLangs(array("companies", "bills", "banks", "exportation@exportation"));

// Non-admin employees → redirect to Mon Espace (employee dashboard)
if (!$user->admin) {
    header("Location: " . dol_buildpath('/custom/exportation/employee_dashboard.php', 1));
    exit;
}

$date_from = GETPOST('date_from', 'alpha') ?: date('Y-m-d', strtotime('-1 day'));
$date_to = GETPOST('date_to', 'alpha') ?: date('Y-m-d');

llxHeader('', "Tableau de Bord - لوحة التحكم", '');
print '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">';
print '<div id="sb">';
print '<style>
#sb{max-width:1500px;margin:0 auto;font-family:"Outfit",sans-serif;padding:20px;color:#1e2a3a}
.sd{background:#fff;border-radius:16px;padding:24px 26px;box-shadow:0 2px 18px rgba(30,42,58,.06);border:1px solid #eef2f7;margin-bottom:20px}
.sdh{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #f0f4f8;gap:10px;flex-wrap:wrap}
.sdh-l{display:flex;align-items:center;gap:10px}
.sdi{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0}
.sdi.bl{background:linear-gradient(135deg,#3b82f6,#2563eb)}.sdi.gr{background:linear-gradient(135deg,#10b981,#059669)}
.sdi.am{background:linear-gradient(135deg,#f59e0b,#d97706)}.sdi.ro{background:linear-gradient(135deg,#f43f5e,#e11d48)}
.sdi.pu{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}.sdi.cy{background:linear-gradient(135deg,#06b6d4,#0891b2)}
.sdt{font-size:16px;font-weight:800}.sds{font-size:11px;color:#94a3b8;margin-top:1px}
.qg{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}
.qc{background:#fff;border-radius:14px;padding:22px;box-shadow:0 4px 20px rgba(30,42,58,.06);border:1px solid #eef2f7;display:flex;align-items:center;gap:16px;cursor:pointer;transition:.2s;text-decoration:none;color:#1e2a3a}
.qc:hover{transform:translateY(-3px);box-shadow:0 8px 30px rgba(30,42,58,.1)}
.qi{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0}
.ql{font-size:11px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px}
.qv{font-size:24px;font-weight:800;margin-top:3px}
.tb{width:100%;border-collapse:collapse;font-size:13px}
.tb th{background:#f8fafc;padding:9px 11px;text-align:left;color:#64748b;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #eef2f7;white-space:nowrap}
.tb td{padding:10px 11px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.tb tbody tr:hover td{background:#fafbfd}
.dp{background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.si{padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:"Outfit",sans-serif;background:#fff}
.date-filter-form{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
</style>';

// Date filter
print '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">';
print '<div style="display:flex;align-items:center;gap:12px;"><div class="sdi bl"><i class="fa-solid fa-rocket"></i></div>';
print '<div><div style="font-size:22px;font-weight:800;">لوحة التحكم السريعة</div><div style="font-size:12px;color:#94a3b8;">Tableau de Bord — Accès Rapide</div></div></div>';
print '<form method="GET" class="date-filter-form">';
print '<div style="display:flex;align-items:center;gap:8px;"><label style="font-size:12px;font-weight:600;">من</label><input type="date" name="date_from" value="' . $date_from . '" class="si" onchange="this.form.submit()"></div>';
print '<div style="display:flex;align-items:center;gap:8px;"><label style="font-size:12px;font-weight:600;">إلى</label><input type="date" name="date_to" value="' . $date_to . '" class="si" onchange="this.form.submit()"></div>';
print '</form>';
print '</div>';

// ═══ DAILY KPIs ═══
// Daily sales
$ob_sales = $db->fetch_object($db->query("SELECT COUNT(*) as nb, COALESCE(SUM(total_ttc),0) as total FROM " . MAIN_DB_PREFIX . "facture WHERE datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND entity = " . (int) $conf->entity . " AND fk_statut > 0"));

// Daily paid sales (clients) - factures with paid status
$pay_c_sql = "SELECT COUNT(*) as cnt, COALESCE(SUM(total_ttc),0) as total FROM " . MAIN_DB_PREFIX . "facture WHERE fk_statut = 2 AND datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND entity = " . (int) $conf->entity;
$pay_c_result = $db->query($pay_c_sql);
if (!$pay_c_result) {
    $ob_pay_c = new stdClass();
    $ob_pay_c->total = 0;
    $ob_pay_c->cnt = 0;
} else {
    $ob_pay_c = $db->fetch_object($pay_c_result);
}

// Daily paid purchases (suppliers) - factures_fourn with paid status
$pay_s_sql = "SELECT COUNT(*) as cnt, COALESCE(SUM(total_ttc),0) as total FROM " . MAIN_DB_PREFIX . "facture_fourn WHERE fk_statut = 2 AND datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND entity = " . (int) $conf->entity;
$pay_s_result = $db->query($pay_s_sql);
if (!$pay_s_result) {
    $ob_pay_s = new stdClass();
    $ob_pay_s->total = 0;
    $ob_pay_s->cnt = 0;
} else {
    $ob_pay_s = $db->fetch_object($pay_s_result);
}

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

// Clients with debts
$ob_clients = $db->fetch_object($db->query("SELECT COUNT(DISTINCT f.fk_soc) as nb FROM " . MAIN_DB_PREFIX . "facture f WHERE f.fk_statut = 1 AND f.entity = " . (int) $conf->entity));

// Suppliers with debts
$ob_suppliers = $db->fetch_object($db->query("SELECT COUNT(DISTINCT f.fk_soc) as nb FROM " . MAIN_DB_PREFIX . "facture_fourn f WHERE f.fk_statut = 1 AND f.entity = " . (int) $conf->entity));

// Overdue invoices
$ob_overdue = $db->fetch_object($db->query("SELECT COUNT(*) as nb, COALESCE(SUM(total_ttc),0) as total FROM " . MAIN_DB_PREFIX . "facture WHERE fk_statut = 1 AND date_lim_reglement < CURRENT_DATE AND entity = " . (int) $conf->entity));

// ═══ Quick Access Cards ═══
print '<div class="qg">';

print '<a class="qc" href="' . DOL_URL_ROOT . '/compta/facture/list.php"><div class="qi" style="background:linear-gradient(135deg,#3b82f6,#2563eb);"><i class="fa-solid fa-cash-register"></i></div><div><div class="ql">المبيعات اليومية Ventes</div><div class="qv" style="color:#3b82f6;">' . price($ob_sales->total) . '</div><div style="font-size:11px;color:#94a3b8;">' . (int) $ob_sales->nb . ' فاتورة</div></div></a>';

print '<a class="qc" href="' . DOL_URL_ROOT . '/compta/facture/list.php"><div class="qi" style="background:linear-gradient(135deg,#10b981,#059669);"><i class="fa-solid fa-arrow-down"></i></div><div><div class="ql">المقبوضات Encaissements</div><div class="qv" style="color:#10b981;">' . price($ob_pay_c->total) . '</div><div style="font-size:11px;color:#94a3b8;">' . $date_from . ' → ' . $date_to . ' (' . (int)$ob_pay_c->cnt . ')</div></div></a>';

print '<a class="qc" href="' . DOL_URL_ROOT . '/fourn/facture/list.php"><div class="qi" style="background:linear-gradient(135deg,#f43f5e,#e11d48);"><i class="fa-solid fa-arrow-up"></i></div><div><div class="ql">المدفوعات للموردين Décaissements</div><div class="qv" style="color:#ef4444;">' . price($ob_pay_s->total) . '</div><div style="font-size:11px;color:#94a3b8;">' . $date_from . ' → ' . $date_to . ' (' . (int)$ob_pay_s->cnt . ')</div></div></a>';

print '<a class="qc" href="customer_dashboard.php"><div class="qi" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);"><i class="fa-solid fa-user-tie"></i></div><div><div class="ql">الزبائن Clients</div><div class="qv" style="color:#8b5cf6;">' . (int) $ob_clients->nb . '</div><div style="font-size:11px;color:#94a3b8;">avec solde</div></div></a>';

print '<a class="qc" href="supplier_invoice.php"><div class="qi" style="background:linear-gradient(135deg,#f59e0b,#d97706);"><i class="fa-solid fa-truck-field"></i></div><div><div class="ql">الموردين Fournisseurs</div><div class="qv" style="color:#f59e0b;">' . (int) $ob_suppliers->nb . '</div><div style="font-size:11px;color:#94a3b8;">avec solde</div></div></a>';

print '<a class="qc" href="unified_account.php"><div class="qi" style="background:linear-gradient(135deg,#06b6d4,#0891b2);"><i class="fa-solid fa-building-columns"></i></div><div><div class="ql">الحساب الموحد Unifié</div><div class="qv" style="color:#06b6d4;">&rarr;</div><div style="font-size:11px;color:#94a3b8;">Comptes</div></div></a>';

print '</div>';

// ═══ Two-column: Banks + Overdue ═══
print '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">';

print '<div class="sd"><div class="sdh"><div class="sdh-l"><div class="sdi gr"><i class="fa-solid fa-vault"></i></div><div><div class="sdt">الصندوق Caisses &amp; Banques</div><div class="sds">من ' . $date_from . ' إلى ' . $date_to . '</div></div></div></div>';
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

// Overdue
print '<div class="sd"><div class="sdh"><div class="sdh-l"><div class="sdi ro"><i class="fa-solid fa-triangle-exclamation"></i></div><div><div class="sdt">متأخرات Retards</div><div class="sds">Factures en retard de paiement</div></div></div></div>';
print '<div style="text-align:center;padding:20px;"><div style="font-size:48px;font-weight:800;color:#ef4444;">' . (int) $ob_overdue->nb . '</div>';
print '<div style="font-size:14px;color:#64748b;margin-top:5px;">فاتورة متأخرة — ' . price($ob_overdue->total) . ' MRU</div></div>';

// List top overdue
$ro = $db->query("SELECT f.ref, f.datef, f.date_lim_reglement, f.total_ttc, s.nom FROM " . MAIN_DB_PREFIX . "facture f INNER JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc WHERE f.fk_statut = 1 AND f.date_lim_reglement < CURRENT_DATE AND f.entity = " . (int) $conf->entity . " ORDER BY f.date_lim_reglement ASC LIMIT 10");
print '<table class="tb"><thead><tr><th>المرجع Réf</th><th>الزبون Client</th><th>الاستحقاق Échéance</th><th>المبلغ Total</th></tr></thead><tbody>';
while ($ro !== false && ($ov = $db->fetch_object($ro))) {
    $days = round((time() - strtotime($ov->date_lim_reglement)) / 86400);
    print '<tr><td><strong>' . $ov->ref . '</strong></td>';
    print '<td>' . dol_escape_htmltag($ov->nom) . '</td>';
    print '<td style="color:#ef4444;">' . dol_print_date($db->jdate($ov->date_lim_reglement), 'day') . ' <span style="font-size:10px;">(' . $days . 'j)</span></td>';
    print '<td style="font-weight:700;">' . price($ov->total_ttc) . '</td></tr>';
}
print '</tbody></table></div>';

print '</div>';

// ═══ Recent Sales ═══
print '<div class="sd"><div class="sdh"><div class="sdh-l"><div class="sdi bl"><i class="fa-solid fa-receipt"></i></div><div><div class="sdt">آخر المبيعات Dernières Ventes</div><div class="sds">Les 10 dernières factures</div></div></div></div>';
$rsales = $db->query("SELECT f.ref, f.datef, f.total_ttc, s.nom, f.fk_statut FROM " . MAIN_DB_PREFIX . "facture f INNER JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc WHERE f.entity = " . (int) $conf->entity . " ORDER BY f.datef DESC, f.rowid DESC LIMIT 10");
print '<table class="tb"><thead><tr><th>المرجع Réf</th><th>التاريخ Date</th><th>الزبون Client</th><th>المبلغ Total</th><th>الحالة Statut</th></tr></thead><tbody>';
while ($rsales !== false && ($sl = $db->fetch_object($rsales))) {
    $st_badge = $sl->fk_statut == 0 ? '<span class="dp" style="background:#fef3c7;color:#92400e;">Brouillon</span>' : '<span class="dp" style="background:#dcfce7;color:#15803d;">Validée</span>';
    print '<tr><td><strong>' . $sl->ref . '</strong></td>';
    print '<td>' . dol_print_date($db->jdate($sl->datef), 'day') . '</td>';
    print '<td>' . dol_escape_htmltag($sl->nom) . '</td>';
    print '<td style="font-weight:700;">' . price($sl->total_ttc) . '</td>';
    print '<td>' . $st_badge . '</td></tr>';
}
print '</tbody></table></div>';

// ═══ Quick Links ═══
print '<div class="sd"><div class="sdh"><div class="sdh-l"><div class="sdi cy"><i class="fa-solid fa-link"></i></div><div><div class="sdt">اختصارات Raccourcis</div></div></div></div>';
print '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">';
$links = array(
    array('supplier_invoice.php', 'fa-file-invoice', '#3b82f6', 'فواتير الموردين Factures Fourn.'),
    array('shipment_card.php', 'fa-ship', '#10b981', 'الشحنات Expéditions'),
    array('customer_dashboard.php', 'fa-user-tie', '#8b5cf6', 'الزبائن Clients'),
    array('unified_account.php', 'fa-building-columns', '#06b6d4', 'الحساب الموحد Unifié'),
    array('reports.php', 'fa-chart-pie', '#f43f5e', 'التقارير Rapports'),
    array('employee_dashboard.php', 'fa-id-badge', '#14b8a6', 'لوحتي Mon Espace'),
    array('partner_dashboard.php', 'fa-handshake', '#8b5cf6', 'الشركاء Partenaires'),
    array(DOL_URL_ROOT . '/compta/bank/list.php', 'fa-vault', '#f59e0b', 'البنوك Banques'),
);
foreach ($links as $lk) {
    print '<a href="' . $lk[0] . '" style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:#f8fafc;border-radius:10px;border:1px solid #eef2f7;text-decoration:none;color:#1e2a3a;font-weight:600;font-size:13px;transition:.15s;" onmouseover="this.style.background=\'#eff6ff\'" onmouseout="this.style.background=\'#f8fafc\'">';
    print '<i class="fa-solid ' . $lk[1] . '" style="color:' . $lk[2] . ';font-size:18px;"></i> ' . $lk[3] . '</a>';
}
print '</div></div>';

print '</div>';
llxFooter();
$db->close();
