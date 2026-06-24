<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

$langs->loadLangs(array("companies", "bills", "banks", "exportation@exportation"));
$form = new Form($db);

// Filters
$date_from = GETPOST('date_from', 'alpha') ?: date('Y-01-01');
$date_to = GETPOST('date_to', 'alpha') ?: date('Y-m-d');
$zakat_rate = (float) (GETPOST('zakat_rate', 'alphanohtml') ?: 2.5);
$top_limit = (int) (GETPOST('top_limit', 'int') ?: 5);

// Commission block removed per user request

// ═══════════════════════════════════════════════════════════
// CALCULATIONS
// ═══════════════════════════════════════════════════════════

// 1. Sales Revenue (native Dolibarr: facture)
$res_sales = $db->query("SELECT COUNT(*) as nb, COALESCE(SUM(total_ht),0) as ht, COALESCE(SUM(total_ttc),0) as ttc, COALESCE(SUM(total_tva),0) as tva FROM " . MAIN_DB_PREFIX . "facture WHERE datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND fk_statut > 0 AND entity = " . (int) $conf->entity);
$ob_sales = $res_sales ? $db->fetch_object($res_sales) : (object)array('nb'=>0,'ht'=>0,'ttc'=>0,'tva'=>0);

// 2. Purchase Cost (native Dolibarr: facture_fourn)
$res_purch = $db->query("SELECT COUNT(*) as nb, COALESCE(SUM(total_ht),0) as ht, COALESCE(SUM(total_ttc),0) as ttc FROM " . MAIN_DB_PREFIX . "facture_fourn WHERE datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND fk_statut > 0 AND entity = " . (int) $conf->entity);
$ob_purchases = $res_purch ? $db->fetch_object($res_purch) : (object)array('nb'=>0,'ht'=>0,'ttc'=>0);

// 3. Expenses (custom: exportation_invoice_expenses + exportation_expenses validated)
$res_exp = $db->query("SELECT COALESCE(SUM(total),0) as total FROM (
	SELECT COALESCE(SUM(e.amount_local),0) as total FROM " . MAIN_DB_PREFIX . "exportation_invoice_expenses e INNER JOIN " . MAIN_DB_PREFIX . "facture_fourn f ON f.rowid = e.fk_facture_fourn WHERE f.datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND f.entity = " . (int) $conf->entity . "
	UNION ALL
	SELECT COALESCE(SUM(amount),0) as total FROM " . MAIN_DB_PREFIX . "exportation_expenses WHERE status = 'VALIDATED' AND DATE(expense_date) BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND entity = " . (int) $conf->entity . "
) as expenses");
$ob_expenses = $res_exp ? $db->fetch_object($res_exp) : (object)array('total'=>0);

// 3b. Employee Salaries (custom: exportation_employee_ops with type 'SALAIRE')
$res_salaries = $db->query("SELECT COALESCE(SUM(amount),0) as total FROM " . MAIN_DB_PREFIX . "exportation_employee_ops WHERE op_type = 'SALAIRE' AND op_date BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "'");
$ob_salaries = $res_salaries ? $db->fetch_object($res_salaries) : (object)array('total'=>0);
$salaries_total = (float) $ob_salaries->total;

// 4. Commissions paid (from journal)
$res_comm = $db->query("SELECT COALESCE(SUM(debit),0) as paid, COALESCE(SUM(credit),0) as received FROM " . MAIN_DB_PREFIX . "exportation_account_operations WHERE operation_type = 'COMMISSION' AND operation_date BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "'");
$ob_commissions = $res_comm ? $db->fetch_object($res_comm) : (object)array('paid'=>0,'received'=>0);

// 5. Payments received (native: paiement)
$res_rec = $db->query("SELECT COALESCE(SUM(pf.amount),0) as total FROM " . MAIN_DB_PREFIX . "paiement p INNER JOIN " . MAIN_DB_PREFIX . "paiement_facture pf ON pf.fk_paiement = p.rowid WHERE p.datep BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "'");
$ob_received = $res_rec ? $db->fetch_object($res_rec) : (object)array('total'=>0);

// 6. Payments sent (native: paiementfourn)
$res_sent = $db->query("SELECT COALESCE(SUM(pf.amount),0) as total FROM " . MAIN_DB_PREFIX . "paiementfourn p INNER JOIN " . MAIN_DB_PREFIX . "paiementfourn_facturefourn pf ON pf.fk_paiementfourn = p.rowid WHERE p.datep BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "'");
$ob_sent = $res_sent ? $db->fetch_object($res_sent) : (object)array('total'=>0);

// 7. Bank accounts
$res_banks = $db->query("SELECT rowid, label, currency_code FROM " . MAIN_DB_PREFIX . "bank_account WHERE entity = " . (int) $conf->entity . " AND clos = 0 ORDER BY label");

// Calculations
$revenue = (float) $ob_sales->ht;
$cost_goods = (float) $ob_purchases->ht;
$expenses = (float) $ob_expenses->total;
$commissions_paid = (float) $ob_commissions->paid;

// 8. Gross profit: sum of margins from invoice lines (selling price - cost price) × qty
$res_margins = $db->query("SELECT COALESCE(SUM((d.subprice - d.buy_price_ht) * d.qty),0) as total FROM " . MAIN_DB_PREFIX . "facturedet d INNER JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = d.fk_facture WHERE f.datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND f.fk_statut > 0 AND f.entity = " . (int) $conf->entity);
$ob_margins = $res_margins ? $db->fetch_object($res_margins) : (object)array('total'=>0);
$gross_profit = (float) $ob_margins->total;

$net_profit = $gross_profit - $expenses - $commissions_paid - $salaries_total;
$margin_pct = ($revenue > 0) ? round(($net_profit / $revenue) * 100, 2) : 0;

// ZAKAT CALCULATION (Capital based)
// 1. Stock selling value
$sql_stock = "SELECT COALESCE(SUM(stock * price),0) as val FROM " . MAIN_DB_PREFIX . "product WHERE entity IN (0," . (int)$conf->entity . ") AND stock > 0";
$res_stock = $db->query($sql_stock);
$zakat_stock = 0;
if ($res_stock) {
    $stock_val_ob = $db->fetch_object($res_stock);
    $zakat_stock = $stock_val_ob ? (float) $stock_val_ob->val : 0;
}

// 2. Banks (Net variation or balance in period)
$sql_bank = "SELECT COALESCE(SUM(b.amount),0) as val FROM " . MAIN_DB_PREFIX . "bank b INNER JOIN " . MAIN_DB_PREFIX . "bank_account ba ON ba.rowid = b.fk_account WHERE ba.entity = " . (int)$conf->entity . " AND ba.clos = 0 AND b.datev BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "'";
$res_bank = $db->query($sql_bank);
$zakat_bank = 0;
if ($res_bank) {
    $bank_val_ob = $db->fetch_object($res_bank);
    $zakat_bank = $bank_val_ob ? (float) $bank_val_ob->val : 0;
}

// 3. Client receivables (Créances)
$crea_q = "SELECT SUM(f.total_ttc - COALESCE(pa.paid, 0)) as remain 
           FROM " . MAIN_DB_PREFIX . "facture f 
           LEFT JOIN (SELECT fk_facture, SUM(amount) as paid FROM " . MAIN_DB_PREFIX . "paiement_facture GROUP BY fk_facture) pa ON pa.fk_facture = f.rowid 
           WHERE f.fk_statut IN (1, 2) AND f.paye = 0 AND f.datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND f.entity = " . (int)$conf->entity;
$res_crea = $db->query($crea_q);
$zakat_creances = 0;
if ($res_crea) {
    $creap_ob = $db->fetch_object($res_crea);
    $zakat_creances = $creap_ob ? (float) $creap_ob->remain : 0;
}
if ($zakat_creances < 0) $zakat_creances = 0;

// 4. Supplier debts (Dettes)
$dett_q = "SELECT SUM(f.total_ttc - COALESCE(pa.paid, 0)) as remain 
           FROM " . MAIN_DB_PREFIX . "facture_fourn f 
           LEFT JOIN (SELECT fk_facturefourn, SUM(amount) as paid FROM " . MAIN_DB_PREFIX . "paiementfourn_facturefourn GROUP BY fk_facturefourn) pa ON pa.fk_facturefourn = f.rowid 
           WHERE f.fk_statut IN (1, 2) AND f.paye = 0 AND f.datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND f.entity = " . (int)$conf->entity;
$res_dett = $db->query($dett_q);
$zakat_dettes = 0;
if ($res_dett) {
    $dett_ob = $db->fetch_object($res_dett);
    $zakat_dettes = $dett_ob ? (float) $dett_ob->remain : 0;
}
if ($zakat_dettes < 0) $zakat_dettes = 0;

$zakat_base = $zakat_stock + $zakat_bank + $zakat_creances - $zakat_dettes;
$zakat_amount = round($zakat_base * ($zakat_rate / 100), 2);

// Cash flow
$cash_in = (float) $ob_received->total;
$cash_out = (float) $ob_sent->total + $expenses + $commissions_paid;
$net_cash = $cash_in - $cash_out;

// Monthly data for charts (Last 6 months)
$months_data = array();
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i month"));
    $m_lbl = date('M Y', strtotime("-$i month"));
    $res_m_s = $db->query("SELECT COALESCE(SUM(total_ht),0) as ht FROM " . MAIN_DB_PREFIX . "facture WHERE datef LIKE '" . $m . "%' AND fk_statut > 0 AND entity = " . (int) $conf->entity);
    $ob_m_s = $db->fetch_object($res_m_s);
    $res_m_m = $db->query("SELECT COALESCE(SUM((d.subprice - d.buy_price_ht) * d.qty),0) as total FROM " . MAIN_DB_PREFIX . "facturedet d INNER JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = d.fk_facture WHERE f.datef LIKE '" . $m . "%' AND f.fk_statut > 0 AND f.entity = " . (int) $conf->entity);
    $ob_m_m = $db->fetch_object($res_m_m);
    $months_data[] = array('label' => $m_lbl, 'sales' => (float)$ob_m_s->ht, 'purchases' => 0, 'margin' => (float)$ob_m_m->total);
}

// ═══════════════════════════════════════════════════════════
// UI
// ═══════════════════════════════════════════════════════════
llxHeader('', "Rapports Financiers — التقارير المالية", '');
print '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">';
print '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
print '<div id="rp">';
print '<style>
#rp{max-width:1500px;margin:0 auto;font-family:"Outfit",sans-serif;padding:20px;color:#1e2a3a}
.rc{background:#fff;border-radius:16px;padding:24px 26px;box-shadow:0 2px 18px rgba(30,42,58,.06);border:1px solid #eef2f7;margin-bottom:20px}
.rh{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #f0f4f8;gap:10px;flex-wrap:wrap}
.rh-l{display:flex;align-items:center;gap:10px}
.ri{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0}
.ri.bl{background:linear-gradient(135deg,#3b82f6,#2563eb)}.ri.gr{background:linear-gradient(135deg,#10b981,#059669)}
.ri.am{background:linear-gradient(135deg,#f59e0b,#d97706)}.ri.ro{background:linear-gradient(135deg,#f43f5e,#e11d48)}
.ri.pu{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}.ri.cy{background:linear-gradient(135deg,#06b6d4,#0891b2)}
.rt{font-size:16px;font-weight:800}.rs{font-size:11px;color:#94a3b8;margin-top:1px}
.rg{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
.rk{background:#f8fafc;border-radius:12px;padding:20px;border:1px solid #eef2f7;text-align:center}
.rk .kl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:6px}
.rk .kv{font-size:24px;font-weight:800}
.rk .ks{font-size:11px;color:#94a3b8;margin-top:3px}
.si{padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:"Outfit",sans-serif;background:#fff;box-sizing:border-box}
.si:focus{border-color:#3b82f6;outline:none}
.sb{background:#3b82f6;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px;transition:.15s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.sb.g{background:linear-gradient(135deg,#10b981,#059669)}.sb.pu{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}.sb:hover{filter:brightness(1.08);transform:translateY(-1px)}
.tb{width:100%;border-collapse:collapse;font-size:13px}
.tb th{background:#f8fafc;padding:9px 11px;text-align:left;color:#64748b;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #eef2f7;white-space:nowrap}
.tb td{padding:10px 11px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.tb tbody tr:hover td{background:#fafbfd}
.dp{background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.pnl-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9;font-size:14px}
.pnl-row:last-child{border-bottom:none}.pnl-row.total{border-top:3px double #cbd5e1;padding-top:14px;font-size:16px}
.pnl-label{color:#64748b;font-weight:600}.pnl-value{font-weight:800}
.zakat-box{background:linear-gradient(135deg,#fef3c7,#fde68a);border:2px solid #f59e0b;border-radius:14px;padding:24px;text-align:center;page-break-inside:avoid;break-inside:avoid}
@media print {
    .noprint, #id-left, .side-nav-container, .top-nav, #tmenu_tooltip, .side-nav, #id-top { display: none !important; }
    #id-right, .right-main-container { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
    body, #rp, .fiche { background: #fff !important; margin: 0 !important; padding: 0 !important; outline: none !important; box-shadow: none !important; border: none !important;}
    .rc { box-shadow: none !important; border: 1px solid #ddd; margin-bottom: 20px; page-break-inside: avoid; }
    .tb th { background: #eee !important; color: #000; }
    .rk { border: 1px solid #ccc !important; box-shadow: none !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-header { display: block !important; margin-bottom: 30px !important; }
}
@media screen {
    .print-header { display: none !important; }
}
.chart-container{position:relative;height:300px;width:100%}
</style>';

// ═══ PRINT HEADER (HIDDEN ON SCREEN) ═══
print '<div class="print-header" style="text-align:center; padding-bottom: 20px; border-bottom: 3px double #cbd5e1;">';
print '<h1 style="font-family:\'Outfit\',sans-serif;font-weight:800;font-size:32px;color:#1e2a3a;margin:0;">Rapport Financier — التقارير المالية</h1>';
print '<div style="font-size:16px;color:#64748b;font-weight:600;margin-top:8px;">Période: ' . dol_print_date(strtotime($date_from), 'day') . ' → ' . dol_print_date(strtotime($date_to), 'day') . '</div>';
print '<div style="font-size:12px;color:#94a3b8;margin-top:6px;">Généré le ' . date('d/m/Y H:i') . ' par ' . dol_escape_htmltag($user->getFullName($langs)) . '</div>';
print '</div>';

// ═══ HEADER + FILTER ═══
print '<div class="noprint" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">';
print '<div style="display:flex;align-items:center;gap:12px;"><div class="ri bl"><i class="fa-solid fa-chart-pie"></i></div>';
print '<div><div style="font-size:22px;font-weight:800;">التقارير المالية</div><div style="font-size:12px;color:#94a3b8;">Rapports Financiers & Comptabilité</div></div></div>';
print '<form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
print '<input type="number" name="top_limit" value="' . $top_limit . '" class="si" style="width:70px;" title="Nombre de top (5 par défaut)">';
print '<input type="date" name="date_from" value="' . $date_from . '" class="si" style="width:140px;">';
print '<span style="color:#94a3b8;">→</span>';
print '<input type="date" name="date_to" value="' . $date_to . '" class="si" style="width:140px;">';
print '<button type="submit" class="sb"><i class="fa-solid fa-filter"></i> Filtrer</button>';
print '<button type="button" onclick="window.print()" class="sb g" style="margin-left:10px;"><i class="fa-solid fa-print"></i> Exporter PDF</button>';
print '</form></div>';

// ═══ KPI CARDS ═══
print '<div class="rg" style="margin-bottom:20px;">';
$p_color = $net_profit >= 0 ? '#10b981' : '#ef4444';
print '<div class="rk"><div class="kl"><i class="fa-solid fa-chart-line"></i> إجمالي المبيعات Chiffre d\'Affaires</div><div class="kv" style="color:#3b82f6;">' . price($revenue) . '</div><div class="ks">' . (int) $ob_sales->nb . ' factures</div></div>';
print '<div class="rk"><div class="kl"><i class="fa-solid fa-cart-shopping"></i> تكلفة الشراء Coût Achats</div><div class="kv" style="color:#ef4444;">' . price($cost_goods) . '</div><div class="ks">' . (int) $ob_purchases->nb . ' factures</div></div>';
print '<div class="rk"><div class="kl"><i class="fa-solid fa-coins"></i> الربح الإجمالي Marge Brute</div><div class="kv" style="color:#f59e0b;">' . price($gross_profit) . '</div></div>';
print '<div class="rk"><div class="kl"><i class="fa-solid fa-trophy"></i> صافي الربح Bénéfice Net</div><div class="kv" style="color:' . $p_color . ';">' . price($net_profit) . '</div><div class="ks">' . $margin_pct . '% marge</div></div>';
print '<div class="rk"><div class="kl"><i class="fa-solid fa-arrow-down"></i> المقبوضات Encaissements</div><div class="kv" style="color:#10b981;">' . price($cash_in) . '</div></div>';
print '<div class="rk"><div class="kl"><i class="fa-solid fa-arrow-up"></i> المدفوعات Décaissements</div><div class="kv" style="color:#ef4444;">' . price($cash_out) . '</div></div>';
print '</div>';

// ═══ P&L STATEMENT ═══
print '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">';

// Profit & Loss
print '<div class="rc"><div class="rh"><div class="rh-l"><div class="ri gr"><i class="fa-solid fa-scale-balanced"></i></div>';
print '<div><div class="rt">حساب النتائج Compte de Résultat</div><div class="rs">Période: ' . $date_from . ' → ' . $date_to . '</div></div></div></div>';

print '<div class="pnl-row"><span class="pnl-label"><i class="fa-solid fa-plus" style="color:#10b981;"></i> Ventes (المبيعات)</span><span class="pnl-value" style="color:#10b981;">' . price($revenue) . '</span></div>';
print '<div class="pnl-row"><span class="pnl-label"><i class="fa-solid fa-minus" style="color:#ef4444;"></i> Achats (المشتريات)</span><span class="pnl-value" style="color:#ef4444;">-' . price($cost_goods) . '</span></div>';
$gp_c = $gross_profit >= 0 ? '#10b981' : '#ef4444';
print '<div class="pnl-row" style="background:#f0fdf4;padding:10px 12px;border-radius:8px;margin:6px 0;"><span class="pnl-label" style="color:#1e2a3a;">= Marge Brute (الربح الإجمالي)</span><span class="pnl-value" style="color:' . $gp_c . ';">' . price($gross_profit) . '</span></div>';
print '<div class="pnl-row"><span class="pnl-label"><i class="fa-solid fa-minus" style="color:#ef4444;"></i> Dépenses (المصاريف)</span><span class="pnl-value" style="color:#ef4444;">-' . price($expenses) . '</span></div>';
print '<div class="pnl-row"><span class="pnl-label"><i class="fa-solid fa-minus" style="color:#ef4444;"></i> Salaires (الرواتب)</span><span class="pnl-value" style="color:#ef4444;">-' . price($salaries_total) . '</span></div>';
print '<div class="pnl-row"><span class="pnl-label"><i class="fa-solid fa-minus" style="color:#ef4444;"></i> Commissions (العمولات)</span><span class="pnl-value" style="color:#ef4444;">-' . price($commissions_paid) . '</span></div>';
print '<div class="pnl-row"><span class="pnl-label"><i class="fa-solid fa-plus" style="color:#10b981;"></i> Facturation Extra (extra)</span><span class="pnl-value" style="color:#10b981;">+' . price($ob_commissions->received) . '</span></div>';
$np_c = $net_profit >= 0 ? '#10b981' : '#ef4444';
print '<div class="pnl-row total"><span class="pnl-label" style="color:#1e2a3a;font-size:16px;">= Bénéfice Net (صافي الربح)</span><span class="pnl-value" style="color:' . $np_c . ';font-size:20px;">' . price($net_profit + (float)$ob_commissions->received) . '</span></div>';
print '<div class="pnl-row"><span class="pnl-label">Marge Nette (incl. extra)</span><span class="pnl-value">' . round((($net_profit + (float)$ob_commissions->received) / ($revenue ?: 1)) * 100, 2) . '%</span></div>';
print '</div>';

// Cash Flow
print '<div class="rc"><div class="rh"><div class="rh-l"><div class="ri cy"><i class="fa-solid fa-money-bill-transfer"></i></div>';
print '<div><div class="rt">التدفق النقدي Flux de Trésorerie</div><div class="rs">Cash Flow</div></div></div></div>';

print '<div class="pnl-row"><span class="pnl-label"><i class="fa-solid fa-arrow-down" style="color:#10b981;"></i> Encaissements Clients</span><span class="pnl-value" style="color:#10b981;">+' . price($cash_in) . '</span></div>';
print '<div class="pnl-row"><span class="pnl-label"><i class="fa-solid fa-arrow-up" style="color:#ef4444;"></i> Paiements Fournisseurs</span><span class="pnl-value" style="color:#ef4444;">-' . price((float) $ob_sent->total) . '</span></div>';
print '<div class="pnl-row"><span class="pnl-label"><i class="fa-solid fa-arrow-up" style="color:#ef4444;"></i> Dépenses opérationnelles</span><span class="pnl-value" style="color:#ef4444;">-' . price($expenses) . '</span></div>';
print '<div class="pnl-row"><span class="pnl-label"><i class="fa-solid fa-arrow-up" style="color:#ef4444;"></i> Commissions</span><span class="pnl-value" style="color:#ef4444;">-' . price($commissions_paid) . '</span></div>';
$nc_c = $net_cash >= 0 ? '#10b981' : '#ef4444';
print '<div class="pnl-row total"><span class="pnl-label" style="color:#1e2a3a;">= Flux Net</span><span class="pnl-value" style="color:' . $nc_c . ';font-size:20px;">' . price($net_cash) . '</span></div>';

// Bank balances inside cash flow
print '<div style="margin-top:16px;padding-top:14px;border-top:2px solid #f0f4f8;"><div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px;"><i class="fa-solid fa-vault"></i> Variation / Soldes Bancaires (Période)</div>';
while ($res_banks !== false && ($bk = $db->fetch_object($res_banks))) {
    // Standard Dolibarr balance calculation restricted to the period
    $res_bal = $db->query("SELECT COALESCE(SUM(amount),0) as s FROM " . MAIN_DB_PREFIX . "bank b WHERE b.fk_account = " . $bk->rowid . " AND b.datev BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "'");
    $bal_obj = $db->fetch_object($res_bal);
    $current_solde = (float) $bal_obj->s;
    $sc = $current_solde >= 0 ? '#10b981' : '#ef4444';
    print '<div class="pnl-row"><span class="pnl-label">' . dol_escape_htmltag($bk->label) . ' <span class="dp">' . $bk->currency_code . '</span></span><span class="pnl-value" style="color:' . $sc . ';">' . price($current_solde) . '</span></div>';
}
print '</div></div>';

print '</div>';

// ═══ ZAKAT + COMMISSION ═══
print '<div style="display:grid;grid-template-columns:1fr;gap:20px;">';

// Zakat
print '<div class="zakat-box">';
print '<div style="font-size:28px;margin-bottom:8px;">☪</div>';
print '<div style="font-size:18px;font-weight:800;color:#92400e;">الزكاة Zakat (Basé sur le Capital et Créances)</div>';

print '<div style="display:flex;justify-content:center;gap:30px;margin:16px 0;font-size:14px;color:#78350f;flex-wrap:wrap;">';
print '<div><div style="font-weight:700;"><i class="fa-solid fa-boxes-stacked"></i> Stock (PV)</div><div style="font-size:16px;">' . price($zakat_stock) . '</div></div>';
print '<div><div style="font-weight:700;"><i class="fa-solid fa-vault"></i> Banques</div><div style="font-size:16px;">' . price($zakat_bank) . '</div></div>';
print '<div><div style="font-weight:700;"><i class="fa-solid fa-hand-holding-dollar"></i> Créances (+)</div><div style="font-size:16px;color:#15803d;">' . price($zakat_creances) . '</div></div>';
print '<div><div style="font-weight:700;"><i class="fa-solid fa-file-invoice-dollar"></i> Dettes (-)</div><div style="font-size:16px;color:#b91c1c;">' . price($zakat_dettes) . '</div></div>';
print '</div>';

print '<form method="GET" class="noprint" style="display:inline-flex;gap:8px;align-items:center;margin-bottom:12px;">';
print '<input type="hidden" name="date_from" value="' . $date_from . '"><input type="hidden" name="date_to" value="' . $date_to . '">';
print '<span style="font-size:13px;color:#78716c;font-weight:600;">Taux:</span>';
print '<input type="number" step="0.01" name="zakat_rate" value="' . number_format($zakat_rate, 2, '.', '') . '" class="si" style="width:80px;text-align:center;">';
print '<span style="font-size:13px;color:#78716c;">%</span>';
print '<button type="submit" class="sb" style="background:#92400e;padding:6px 12px;"><i class="fa-solid fa-calculator"></i> Appliquer</button></form>';

print '<div style="margin-top:10px;">';
print '<div style="font-size:14px;color:#78716c;">Base imposable = <strong>' . price($zakat_base) . ' MRU</strong></div>';
print '<div style="font-size:32px;font-weight:800;color:#92400e;margin-top:8px;">' . price(max(0, $zakat_amount)) . ' <span style="font-size:14px;">MRU</span></div>';
print '<div style="font-size:12px;color:#a1876e;margin-top:4px;">Calcul = (Stock + Banques + Créances - Dettes) × ' . number_format($zakat_rate, 2) . '%</div>';
print '</div></div>';

print '</div>';

print '</div>';

// ═══ TOP CLIENTS & SUPPLIERS ═══
print '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">';

// Top clients by revenue
print '<div class="rc"><div class="rh"><div class="rh-l"><div class="ri gr"><i class="fa-solid fa-ranking-star"></i></div>';
print '<div><div class="rt">أفضل الزبائن Top Clients</div><div class="rs">Par chiffre d\'affaires</div></div></div></div>';
$rtc = $db->query("SELECT s.rowid, s.nom, COUNT(f.rowid) as nb, SUM(f.total_ht) as total FROM " . MAIN_DB_PREFIX . "facture f INNER JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc WHERE f.datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND f.fk_statut > 0 AND f.entity = " . (int) $conf->entity . " GROUP BY s.rowid, s.nom ORDER BY total DESC LIMIT " . $top_limit);
print '<table class="tb"><thead><tr><th>#</th><th>Client</th><th>Factures</th><th>Montant</th></tr></thead><tbody>';
$rank = 0;
while ($rtc !== false && ($tc = $db->fetch_object($rtc))) {
    $rank++;
    $medal = $rank <= 3 ? array('🥇', '🥈', '🥉')[$rank - 1] : $rank;
    print '<tr><td style="font-weight:700;">' . $medal . '</td>';
    print '<td><a href="customer_dashboard.php?socid=' . $tc->rowid . '" style="color:#3b82f6;font-weight:700;">' . dol_escape_htmltag($tc->nom) . '</a></td>';
    print '<td>' . (int) $tc->nb . '</td>';
    print '<td style="font-weight:800;color:#10b981;">' . price($tc->total) . '</td></tr>';
}
if ($rank == 0) print '<tr><td colspan="4" style="text-align:center;">Aucune donnée</td></tr>';
print '</tbody></table></div>';

// Top suppliers by cost
print '<div class="rc"><div class="rh"><div class="rh-l"><div class="ri ro"><i class="fa-solid fa-truck-field"></i></div>';
print '<div><div class="rt">أهم الموردين Top Fournisseurs</div><div class="rs">Par volume d\'achat</div></div></div></div>';
$rts = $db->query("SELECT s.rowid, s.nom, COUNT(f.rowid) as nb, SUM(f.total_ht) as total FROM " . MAIN_DB_PREFIX . "facture_fourn f INNER JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc WHERE f.datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND f.fk_statut > 0 AND f.entity = " . (int) $conf->entity . " GROUP BY s.rowid, s.nom ORDER BY total DESC LIMIT " . $top_limit);
print '<table class="tb"><thead><tr><th>#</th><th>Fournisseur</th><th>Factures</th><th>Montant</th></tr></thead><tbody>';
$rank = 0;
while ($rts !== false && ($ts = $db->fetch_object($rts))) {
    $rank++;
    $medal = $rank <= 3 ? array('🥇', '🥈', '🥉')[$rank - 1] : $rank;
    print '<tr><td style="font-weight:700;">' . $medal . '</td>';
    print '<td><a href="unified_account.php?socid=' . $ts->rowid . '" style="color:#3b82f6;font-weight:700;">' . dol_escape_htmltag($ts->nom) . '</a></td>';
    print '<td>' . (int) $ts->nb . '</td>';
    print '<td style="font-weight:800;color:#ef4444;">' . price($ts->total) . '</td></tr>';
}
if ($rank == 0) print '<tr><td colspan="4" style="text-align:center;">Aucune donnée</td></tr>';
print '</tbody></table></div>';

// Top Products Purchased
print '<div class="rc"><div class="rh"><div class="rh-l"><div class="ri bl" style="background:linear-gradient(135deg,#6366f1,#4f46e5);"><i class="fa-solid fa-boxes-packing"></i></div>';
print '<div><div class="rt">المنتجات الأكثر شراءً Top Produits Achetés</div><div class="rs">Par volume financier</div></div></div></div>';
$rtp = $db->query("SELECT p.ref, p.label, SUM(d.qty) as qty, SUM(d.total_ht) as total FROM " . MAIN_DB_PREFIX . "facture_fourn_det d INNER JOIN " . MAIN_DB_PREFIX . "facture_fourn f ON f.rowid=d.fk_facture_fourn LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid=d.fk_product WHERE f.datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND f.fk_statut > 0 AND f.entity = " . (int) $conf->entity . " AND d.fk_product > 0 GROUP BY p.rowid, p.ref, p.label ORDER BY total DESC LIMIT " . $top_limit);
print '<table class="tb"><thead><tr><th>#</th><th>Produit</th><th>Qté</th><th>Montant Achats</th></tr></thead><tbody>';
$rank = 0;
while ($rtp !== false && ($tp = $db->fetch_object($rtp))) {
    $rank++;
    $medal = $rank <= 3 ? array('🥇', '🥈', '🥉')[$rank - 1] : $rank;
    $lbl = $tp->label ?: 'Produit inconnu';
    print '<tr><td style="font-weight:700;">' . $medal . '</td>';
    print '<td><strong>' . dol_escape_htmltag($lbl) . '</strong> <span style="color:#94a3b8;font-size:10px;">(' . dol_escape_htmltag($tp->ref) . ')</span></td>';
    print '<td>' . (int) $tp->qty . '</td>';
    print '<td style="font-weight:800;color:#ef4444;">' . price($tp->total) . '</td></tr>';
}
if ($rank == 0) print '<tr><td colspan="4" style="text-align:center;">Aucune donnée</td></tr>';
print '</tbody></table></div>';

// Top Products Sold (Marges)
print '<div class="rc"><div class="rh"><div class="rh-l"><div class="ri pu" style="background:linear-gradient(135deg,#c026d3,#9333ea);"><i class="fa-solid fa-chart-line"></i></div>';
print '<div><div class="rt">أفضل المنتجات مبيعاً Top Produits Marge</div><div class="rs">Estimé sur le PMP / Prix de vente HT</div></div></div></div>';
$rtm = $db->query("SELECT p.ref, p.label, SUM(d.qty) as qty, SUM(d.total_ht) as ventre_tot, SUM(d.buy_price_ht * d.qty) as achat_tot FROM " . MAIN_DB_PREFIX . "facturedet d INNER JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid=d.fk_facture LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid=d.fk_product WHERE f.datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND f.fk_statut > 0 AND f.entity = " . (int) $conf->entity . " AND d.fk_product > 0 GROUP BY p.rowid, p.ref, p.label ORDER BY (SUM(d.total_ht) - SUM(d.buy_price_ht * d.qty)) DESC LIMIT " . $top_limit);
print '<table class="tb"><thead><tr><th>#</th><th>Produit</th><th>Qté Vendue</th><th>Marge Nette HT</th></tr></thead><tbody>';
$rank = 0;
while ($rtm !== false && ($tm = $db->fetch_object($rtm))) {
    $rank++;
    $medal = $rank <= 3 ? array('🥇', '🥈', '🥉')[$rank - 1] : $rank;
    $lbl = $tm->label ?: 'Produit inconnu';
    $marge = (float) $tm->ventre_tot - (float) $tm->achat_tot;
    print '<tr><td style="font-weight:700;">' . $medal . '</td>';
    print '<td><strong>' . dol_escape_htmltag($lbl) . '</strong> <span style="color:#94a3b8;font-size:10px;">(' . dol_escape_htmltag($tm->ref) . ')</span></td>';
    print '<td>' . (int) $tm->qty . '</td>';
    print '<td style="font-weight:800;color:#10b981;">' . price($marge) . '</td></tr>';
}
if ($rank == 0) print '<tr><td colspan="4" style="text-align:center;">Aucune donnée</td></tr>';
print '</tbody></table></div>';

print '</div>';

// ═══ FINANCIAL CHARTS ═══
print '<div class="rc"><div class="rh"><div class="rh-l"><div class="ri pu"><i class="fa-solid fa-chart-area"></i></div>';
print '<div><div class="rt">الاتجاهات المالية Tendances Financières</div><div class="rs">Evolution mensuelle (Ventes vs Achats)</div></div></div></div>';
print '<div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;">';
print '<div><div class="chart-container"><canvas id="mainChart"></canvas></div></div>';
print '<div><div class="chart-container"><canvas id="marginChart"></canvas></div></div>';
print '</div></div>';

print '<script>
const months = ' . json_encode(array_column($months_data, 'label')) . ';
const sales = ' . json_encode(array_column($months_data, 'sales')) . ';
const purchases = ' . json_encode(array_column($months_data, 'purchases')) . ';
const margins = ' . json_encode(array_column($months_data, 'margin')) . ';

new Chart(document.getElementById("mainChart"), {
    type: "bar",
    data: {
        labels: months,
        datasets: [
            { label: "Ventes", data: sales, backgroundColor: "rgba(59, 130, 246, 0.7)", borderRadius: 6 },
            { label: "Achats", data: purchases, backgroundColor: "rgba(244, 63, 94, 0.7)", borderRadius: 6 }
        ]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "top" } } }
});

new Chart(document.getElementById("marginChart"), {
    type: "line",
    data: {
        labels: months,
        datasets: [{ label: "Marge Nette", data: margins, borderColor: "#10b981", tension: 0.4, fill: true, backgroundColor: "rgba(16, 185, 129, 0.1)" }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "top" } } }
});
</script>';



print '</div>';
llxFooter();
$db->close();
