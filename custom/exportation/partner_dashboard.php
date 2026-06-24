<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

$langs->loadLangs(array("companies", "bills", "banks", "exportation@exportation"));
$form = new Form($db);
$action = GETPOST('action', 'alpha');
$partner_id = GETPOST('partner_id', 'int');

// Filters
$date_from = GETPOST('date_from', 'alpha') ?: date('Y-01-01');
$date_to = GETPOST('date_to', 'alpha') ?: date('Y-m-d');

// ═══ ACTIONS ═══
if ($action == 'add_partner') {
    $name = GETPOST('p_name', 'alpha');
    $pct = (float) GETPOST('p_pct', 'alphanohtml');
    $capital = (float) GETPOST('p_capital', 'alphanohtml');
    $fk_user = (int) GETPOST('p_fk_user', 'int');
    $note = GETPOST('p_note', 'alpha');
    $date_effective = GETPOST('p_date_effective', 'alpha') ?: date('Y-m-d');
    if (!empty($name) && $pct > 0) {
        // Ensure date_effective column exists
        $db->query("ALTER TABLE " . MAIN_DB_PREFIX . "exportation_partners ADD COLUMN IF NOT EXISTS date_effective DATE DEFAULT NULL");
        $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_partners (name, percentage, fk_user, capital_initial, note, active, date_creation, date_effective) VALUES ('" . $db->escape($name) . "', " . $pct . ", " . $fk_user . ", " . $capital . ", '" . $db->escape($note) . "', 1, '" . $db->idate(dol_now()) . "', '" . $db->escape($date_effective) . "')");
        setEventMessages("Partenaire ajouté.", null, 'mesgs');
    }
    header("Location: partner_dashboard.php?date_from=" . $date_from . "&date_to=" . $date_to);
    exit;
}

if ($action == 'toggle_partner' && $partner_id > 0) {
    $db->query("UPDATE " . MAIN_DB_PREFIX . "exportation_partners SET active = IF(active=1,0,1) WHERE rowid = " . $partner_id);
    header("Location: partner_dashboard.php?date_from=" . $date_from . "&date_to=" . $date_to);
    exit;
}

if ($action == 'delete_partner' && $partner_id > 0) {
    $db->begin();
    $r1 = $db->query("DELETE FROM " . MAIN_DB_PREFIX . "exportation_partner_distributions WHERE fk_partner = " . $partner_id);
    $r2 = $db->query("DELETE FROM " . MAIN_DB_PREFIX . "exportation_partners WHERE rowid = " . $partner_id);
    if ($r1 !== false && $r2 !== false) {
        $db->commit();
        setEventMessages("Partenaire supprimé.", null, 'mesgs');
    } else {
        $db->rollback();
        setEventMessages("Erreur lors de la suppression.", null, 'errors');
    }
    header("Location: partner_dashboard.php?date_from=" . $date_from . "&date_to=" . $date_to);
    exit;
}

if ($action == 'update_partner' && $partner_id > 0) {
    $pct = (float) GETPOST('p_pct', 'alphanohtml');
    $capital = (float) GETPOST('p_capital', 'alphanohtml');
    $note = GETPOST('p_note', 'alpha');
    $db->query("UPDATE " . MAIN_DB_PREFIX . "exportation_partners SET percentage = " . $pct . ", capital_initial = " . $capital . ", note = '" . $db->escape($note) . "' WHERE rowid = " . $partner_id);
    header("Location: partner_dashboard.php?date_from=" . $date_from . "&date_to=" . $date_to);
    exit;
}

if ($action == 'add_distribution' && $partner_id > 0) {
    $amount = (float) GETPOST('d_amount', 'alphanohtml');
    $dtype = GETPOST('d_type', 'alpha');
    $label = GETPOST('d_label', 'alpha');
    $ddate = GETPOST('d_date', 'alpha');
    $fk_bank = (int) GETPOST('d_fk_bank', 'int');
    if ($amount > 0 && $fk_bank > 0) {
        // Ensure fk_bank column exists
        $db->query("ALTER TABLE " . MAIN_DB_PREFIX . "exportation_partner_distributions ADD COLUMN IF NOT EXISTS fk_bank INT DEFAULT NULL");
        $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_partner_distributions (fk_partner, dist_date, amount, dist_type, label, fk_user_creat, date_creation, fk_bank) VALUES (" . $partner_id . ", '" . $db->escape($ddate) . "', " . $amount . ", '" . $db->escape($dtype) . "', '" . $db->escape($label) . "', " . (int) $user->id . ", '" . $db->idate(dol_now()) . "', " . $fk_bank . ")");
        setEventMessages("Opération enregistrée.", null, 'mesgs');
    } else {
        setEventMessages("Erreur: veuillez sélectionner une banque.", null, 'errors');
    }
    header("Location: partner_dashboard.php?date_from=" . $date_from . "&date_to=" . $date_to);
    exit;
}

// Get banks
$banks = array();
$rb = $db->query("SELECT rowid, label FROM " . MAIN_DB_PREFIX . "bank_account WHERE entity = " . (int) $conf->entity . " AND clos = 0 ORDER BY label");
while ($rb && $bk = $db->fetch_object($rb)) { $banks[] = $bk; }

// ═══ GLOBAL FINANCIALS (period) ═══
$ob_sales = $db->fetch_object($db->query("SELECT COALESCE(SUM(total_ht),0) as ht FROM " . MAIN_DB_PREFIX . "facture WHERE datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND fk_statut > 0 AND entity = " . (int) $conf->entity));
$ob_purchases = $db->fetch_object($db->query("SELECT COALESCE(SUM(total_ht),0) as ht FROM " . MAIN_DB_PREFIX . "facture_fourn WHERE datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND fk_statut > 0 AND entity = " . (int) $conf->entity));
$ob_expenses = $db->fetch_object($db->query("SELECT COALESCE(SUM(e.amount_local),0) as total FROM " . MAIN_DB_PREFIX . "exportation_invoice_expenses e INNER JOIN " . MAIN_DB_PREFIX . "facture_fourn f ON f.rowid = e.fk_facture_fourn WHERE f.datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND f.entity = " . (int) $conf->entity));
$ob_commissions = $db->fetch_object($db->query("SELECT COALESCE(SUM(debit),0) as paid FROM " . MAIN_DB_PREFIX . "exportation_account_operations WHERE operation_type = 'COMMISSION' AND operation_date BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "'"));

$revenue = (float) $ob_sales->ht;
$cost_goods = (float) $ob_purchases->ht;
$expenses = (float) $ob_expenses->total;
$commissions = (float) $ob_commissions->paid;

// Gross profit from line items (selling price - cost price) × qty
$res_margins = $db->query("SELECT COALESCE(SUM((d.subprice - d.buy_price_ht) * d.qty),0) as total FROM " . MAIN_DB_PREFIX . "facturedet d INNER JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = d.fk_facture WHERE f.datef BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "' AND f.fk_statut > 0 AND f.entity = " . (int) $conf->entity);
$ob_margins = $res_margins ? $db->fetch_object($res_margins) : (object)array('total'=>0);
$gross_margin = (float) $ob_margins->total;

$net_profit = $gross_margin - $expenses - $commissions;

// Payments received
$ob_received = $db->fetch_object($db->query("SELECT COALESCE(SUM(pf.amount),0) as total FROM " . MAIN_DB_PREFIX . "paiement p INNER JOIN " . MAIN_DB_PREFIX . "paiement_facture pf ON pf.fk_paiement = p.rowid WHERE p.datep BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "'"));
$cash_in = (float) $ob_received->total;

// Partners
$rp = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_partners ORDER BY percentage DESC, name ASC");
$partners = array();
$total_pct = 0;
while ($rp !== false && ($p = $db->fetch_object($rp))) {
    $partners[] = $p;
    if ($p->active) $total_pct += (float) $p->percentage;
}

// ═══ UI ═══
llxHeader('', "Partenaires — الشركاء", '');
print '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">';
print '<div id="pd">';
print '<style>
#pd{max-width:1500px;margin:0 auto;font-family:"Outfit",sans-serif;padding:20px;color:#1e2a3a}
.pc{background:#fff;border-radius:16px;padding:24px 26px;box-shadow:0 2px 18px rgba(30,42,58,.06);border:1px solid #eef2f7;margin-bottom:20px}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #f0f4f8;gap:10px;flex-wrap:wrap}
.ph-l{display:flex;align-items:center;gap:10px}
.pi{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0}
.pi.bl{background:linear-gradient(135deg,#3b82f6,#2563eb)}.pi.gr{background:linear-gradient(135deg,#10b981,#059669)}
.pi.am{background:linear-gradient(135deg,#f59e0b,#d97706)}.pi.ro{background:linear-gradient(135deg,#f43f5e,#e11d48)}
.pi.pu{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}.pi.cy{background:linear-gradient(135deg,#06b6d4,#0891b2)}
.pt{font-size:16px;font-weight:800}.ps{font-size:11px;color:#94a3b8;margin-top:1px}
.pg{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
.pk{background:#f8fafc;border-radius:12px;padding:20px;border:1px solid #eef2f7;text-align:center}
.pk .kl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:6px}
.pk .kv{font-size:24px;font-weight:800}
.pk .ks{font-size:11px;color:#94a3b8;margin-top:3px}
.si{padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:"Outfit",sans-serif;background:#fff;box-sizing:border-box}
.si:focus{border-color:#3b82f6;outline:none}
.sb{background:#3b82f6;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px;transition:.15s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.sb.g{background:linear-gradient(135deg,#10b981,#059669)}.sb.pu{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}.sb:hover{filter:brightness(1.08);transform:translateY(-1px)}
.tb{width:100%;border-collapse:collapse;font-size:13px}
.tb th{background:#f8fafc;padding:9px 11px;text-align:left;color:#64748b;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #eef2f7;white-space:nowrap}
.tb td{padding:10px 11px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.tb tbody tr:hover td{background:#fafbfd}
.dp{background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.pct-bar{height:8px;border-radius:4px;overflow:hidden;background:#e2e8f0;margin-top:6px}
.pct-bar .fill{height:100%;border-radius:4px;transition:width .4s}
.partner-card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 4px 20px rgba(30,42,58,.06);border:1px solid #eef2f7;margin-bottom:16px}
.partner-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.partner-badge{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff;font-weight:800}
</style>';

// Header
print '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">';
print '<div style="display:flex;align-items:center;gap:12px;"><div class="pi pu"><i class="fa-solid fa-handshake"></i></div>';
print '<div><div style="font-size:22px;font-weight:800;">الشركاء Partenaires</div><div style="font-size:12px;color:#94a3b8;">Gestion des parts et répartition des bénéfices</div></div></div>';
print '<form method="GET" style="display:flex;gap:8px;align-items:center;">';
print '<input type="date" name="date_from" value="' . $date_from . '" class="si" style="width:140px;">';
print '<span style="color:#94a3b8;">→</span>';
print '<input type="date" name="date_to" value="' . $date_to . '" class="si" style="width:140px;">';
print '<button type="submit" class="sb"><i class="fa-solid fa-filter"></i></button></form></div>';

// ═══ GLOBAL KPIs ═══
$np_c = $net_profit >= 0 ? '#10b981' : '#ef4444';
print '<div class="pg">';
print '<div class="pk"><div class="kl"><i class="fa-solid fa-chart-line"></i> رقم الأعمال CA</div><div class="kv" style="color:#3b82f6;">' . price($revenue) . '</div></div>';
print '<div class="pk"><div class="kl"><i class="fa-solid fa-coins"></i> الهامش الإجمالي Marge Brute</div><div class="kv" style="color:#f59e0b;">' . price($gross_margin) . '</div></div>';
print '<div class="pk"><div class="kl"><i class="fa-solid fa-trophy"></i> صافي الربح Bénéfice Net</div><div class="kv" style="color:' . $np_c . ';">' . price($net_profit) . '</div></div>';
print '<div class="pk"><div class="kl"><i class="fa-solid fa-hand-holding-dollar"></i> المقبوضات Encaissé</div><div class="kv" style="color:#10b981;">' . price($cash_in) . '</div></div>';
print '<div class="pk"><div class="kl"><i class="fa-solid fa-users"></i> الشركاء Partenaires</div><div class="kv" style="color:#8b5cf6;">' . count($partners) . '</div><div class="ks">' . number_format($total_pct, 2) . '% total</div></div>';
print '</div>';

// ═══ PARTNER CARDS ═══
$colors = array('#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f43f5e', '#14b8a6');
foreach ($partners as $i => $p) {
    $pct = (float) $p->percentage;
    $is_active = $p->active;
    $color = $colors[$i % count($colors)];

    // Get effective date (use date_effective if exists, otherwise use date_creation)
    $p_date_start = isset($p->date_effective) && !empty($p->date_effective) ? $p->date_effective : $p->date_creation;
    $p_date_end = date('Y-m-d');

    // Calculate period financials for THIS partner (from their effective date)
    $p_sales = $db->fetch_object($db->query("SELECT COALESCE(SUM(total_ht),0) as ht FROM " . MAIN_DB_PREFIX . "facture WHERE datef BETWEEN '" . $db->escape($p_date_start) . "' AND '" . $db->escape($p_date_end) . "' AND fk_statut > 0 AND entity = " . (int) $conf->entity));
    $p_purchases = $db->fetch_object($db->query("SELECT COALESCE(SUM(total_ht),0) as ht FROM " . MAIN_DB_PREFIX . "facture_fourn WHERE datef BETWEEN '" . $db->escape($p_date_start) . "' AND '" . $db->escape($p_date_end) . "' AND fk_statut > 0 AND entity = " . (int) $conf->entity));
    $p_expenses = $db->fetch_object($db->query("SELECT COALESCE(SUM(e.amount_local),0) as total FROM " . MAIN_DB_PREFIX . "exportation_invoice_expenses e INNER JOIN " . MAIN_DB_PREFIX . "facture_fourn f ON f.rowid = e.fk_facture_fourn WHERE f.datef BETWEEN '" . $db->escape($p_date_start) . "' AND '" . $db->escape($p_date_end) . "' AND f.entity = " . (int) $conf->entity));
    $p_received = $db->fetch_object($db->query("SELECT COALESCE(SUM(pf.amount),0) as total FROM " . MAIN_DB_PREFIX . "paiement p INNER JOIN " . MAIN_DB_PREFIX . "paiement_facture pf ON pf.fk_paiement = p.rowid WHERE p.datep BETWEEN '" . $db->escape($p_date_start) . "' AND '" . $db->escape($p_date_end) . "'"));

    $p_revenue = (float) $p_sales->ht;
    $p_cost = (float) $p_purchases->ht;
    $p_expenses_amt = (float) $p_expenses->total;
    $p_margin = $p_revenue - $p_cost;
    $p_profit = $p_margin - $p_expenses_amt;
    $p_cash = (float) $p_received->total;

    // Partner's share of THEIR calculated financials
    $share_margin = $p_margin * ($pct / 100);
    $share_profit = $p_profit * ($pct / 100);
    $share_ca = $p_revenue * ($pct / 100);
    $share_cash = $p_cash * ($pct / 100);

    // Distributions paid
    $ob_dist = $db->fetch_object($db->query("SELECT COALESCE(SUM(CASE WHEN dist_type='RETRAIT' THEN amount ELSE 0 END),0) as paid, COALESCE(SUM(CASE WHEN dist_type='VERSEMENT' THEN amount ELSE 0 END),0) as received FROM " . MAIN_DB_PREFIX . "exportation_partner_distributions WHERE fk_partner = " . (int) $p->rowid . " AND dist_date BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "'"));
    $dist_paid = (float) $ob_dist->paid;
    $dist_received = (float) $ob_dist->received;
    $remaining_profit = max(0, $share_profit - $dist_paid + $dist_received);

    $opacity = $is_active ? 1 : 0.5;
    $initials = strtoupper(substr($p->name, 0, 2));

    print '<div class="partner-card" style="opacity:' . $opacity . ';">';

    // Header
    print '<div class="partner-header"><div style="display:flex;align-items:center;gap:14px;">';
    print '<div class="partner-badge" style="background:' . $color . ';">' . $initials . '</div>';
    print '<div><div style="font-size:18px;font-weight:800;">' . dol_escape_htmltag($p->name) . '</div>';
    print '<div style="font-size:12px;color:#94a3b8;">';
    if (!$is_active) print '<span style="background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;">معطل Inactif</span> ';
    print 'Capital: ' . price($p->capital_initial) . ' MRU';
    if ($p->note) print ' — ' . dol_escape_htmltag($p->note);
    print '</div></div></div>';

    // Percentage badge + actions
    print '<div style="display:flex;align-items:center;gap:8px;">';
    print '<div style="background:' . $color . '15;color:' . $color . ';padding:10px 20px;border-radius:14px;font-size:24px;font-weight:800;">' . number_format($pct, 2) . '%</div>';
    print '<a href="?action=toggle_partner&partner_id=' . $p->rowid . '&date_from=' . $date_from . '&date_to=' . $date_to . '" class="sb" style="padding:8px 12px;background:' . ($is_active ? '#ef4444' : '#10b981') . ';" onclick="return confirm(\'Confirmer?\');" title="' . ($is_active ? 'Désactiver' : 'Activer') . '"><i class="fa-solid fa-' . ($is_active ? 'pause' : 'play') . '"></i></a>';
    $confirm_del = 'Supprimer définitivement le partenaire « ' . dol_escape_js($p->name) . ' » ?\n\nToutes ses opérations (versements/retraits) seront aussi supprimées. Cette action est irréversible.';
    print '<a href="?action=delete_partner&partner_id=' . $p->rowid . '&date_from=' . $date_from . '&date_to=' . $date_to . '" class="sb" style="padding:8px 12px;background:#64748b;" onclick="return confirm(\'' . $confirm_del . '\');" title="Supprimer"><i class="fa-solid fa-trash"></i></a>';
    print '</div></div>';

    // KPIs row
    print '<div class="pg" style="margin-bottom:16px;">';
    print '<div class="pk"><div class="kl">حصة رقم الأعمال Part CA</div><div class="kv" style="color:#3b82f6;">' . price($share_ca) . '</div></div>';
    print '<div class="pk"><div class="kl">حصة الهامش Part Marge</div><div class="kv" style="color:#f59e0b;">' . price($share_margin) . '</div></div>';
    $sp_c = $share_profit >= 0 ? '#10b981' : '#ef4444';
    print '<div class="pk"><div class="kl">حصة الربح Part Bénéfice</div><div class="kv" style="color:' . $sp_c . ';">' . price($share_profit) . '</div></div>';
    print '<div class="pk"><div class="kl">المسحوب Retiré</div><div class="kv" style="color:#ef4444;">' . price($dist_paid) . '</div></div>';
    print '<div class="pk"><div class="kl">المتبقي Solde</div><div class="kv" style="color:' . ($remaining_profit >= 0 ? '#10b981' : '#ef4444') . ';">' . price($remaining_profit) . '</div>';
    if ($share_profit > 0) {
        $pct_paid = min(100, round(($dist_paid / $share_profit) * 100));
        print '<div class="pct-bar"><div class="fill" style="width:' . $pct_paid . '%;background:' . $color . ';"></div></div>';
        print '<div class="ks">' . $pct_paid . '% versé</div>';
    }
    print '</div></div>';

    // Distribution form
    print '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding-top:12px;border-top:1px dashed #e2e8f0;">';
    print '<form method="POST" action="?date_from=' . $date_from . '&date_to=' . $date_to . '" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">';
    print '<input type="hidden" name="action" value="add_distribution"><input type="hidden" name="partner_id" value="' . $p->rowid . '"><input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="date" name="d_date" value="' . date('Y-m-d') . '" class="si" style="width:130px;">';
    print '<select name="d_type" class="si" style="width:100px;"><option value="RETRAIT">سحب Retrait</option><option value="VERSEMENT">إيداع Versement</option></select>';
    print '<input type="number" step="0.01" name="d_amount" placeholder="المبلغ" class="si" style="width:90px;" required>';
    print '<select name="d_fk_bank" class="si" style="width:120px;" required><option value="">— Banque —</option>';
    foreach ($banks as $bk) { print '<option value="' . $bk->rowid . '">' . dol_escape_htmltag($bk->label) . '</option>'; }
    print '</select>';
    print '<input type="text" name="d_label" placeholder="ملاحظة Note..." class="si" style="width:130px;">';
    print '<button type="submit" class="sb g"><i class="fa-solid fa-plus"></i></button>';
    print '</form></div>';

    // Distribution history (last 5)
    $rd = $db->query("SELECT d.*, b.label as bank_name FROM " . MAIN_DB_PREFIX . "exportation_partner_distributions d LEFT JOIN " . MAIN_DB_PREFIX . "bank_account b ON b.rowid = d.fk_bank WHERE fk_partner = " . (int) $p->rowid . " ORDER BY dist_date DESC, d.rowid DESC LIMIT 5");
    $hd = false;
    print '<div style="margin-top:12px;">';
    while ($rd !== false && ($d = $db->fetch_object($rd))) {
        if (!$hd) {
            print '<table class="tb"><thead><tr><th>التاريخ</th><th>النوع</th><th>المبلغ</th><th>Banque</th><th>ملاحظة</th></tr></thead><tbody>';
            $hd = true;
        }
        $tc = $d->dist_type == 'RETRAIT' ? '#ef4444' : '#10b981';
        $ti = $d->dist_type == 'RETRAIT' ? 'fa-arrow-up' : 'fa-arrow-down';
        print '<tr><td>' . dol_print_date($db->jdate($d->dist_date), 'day') . '</td>';
        print '<td><span style="color:' . $tc . ';font-weight:700;"><i class="fa-solid ' . $ti . '"></i> ' . $d->dist_type . '</span></td>';
        print '<td style="font-weight:700;color:' . $tc . ';">' . price($d->amount) . '</td>';
        print '<td style="font-size:11px;color:#64748b;font-weight:600;">' . dol_escape_htmltag($d->bank_name ?: '-') . '</td>';
        print '<td style="color:#94a3b8;font-size:12px;">' . dol_escape_htmltag($d->label ?: '') . '</td></tr>';
    }
    if ($hd) print '</tbody></table>';
    print '</div>';

    print '</div>';
}

// ═══ ADD PARTNER FORM ═══
print '<div class="pc"><div class="ph"><div class="ph-l"><div class="pi gr"><i class="fa-solid fa-user-plus"></i></div>';
print '<div><div class="pt">إضافة شريك Nouveau Partenaire</div></div></div></div>';
print '<form method="POST" action="?date_from=' . $date_from . '&date_to=' . $date_to . '">';
print '<input type="hidden" name="action" value="add_partner"><input type="hidden" name="token" value="' . newToken() . '">';
print '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">';
print '<div><label style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;display:block;margin-bottom:4px;">الاسم Nom</label><input type="text" name="p_name" class="si" style="width:100%;" required></div>';
print '<div><label style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;display:block;margin-bottom:4px;">النسبة %</label><input type="number" step="0.01" name="p_pct" class="si" style="width:100%;" required></div>';
print '<div><label style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;display:block;margin-bottom:4px;">تاريخ البدء Date Effective</label><input type="date" name="p_date_effective" value="' . date('Y-m-d') . '" class="si" style="width:100%;" required></div>';
print '<div><label style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;display:block;margin-bottom:4px;">رأس المال Capital</label><input type="number" step="0.01" name="p_capital" value="0" class="si" style="width:100%;"></div>';
print '<div><label style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;display:block;margin-bottom:4px;">المستخدم Utilisateur</label><select name="p_fk_user" class="si" style="width:100%;"><option value="0">— Aucun —</option>';
$ru = $db->query("SELECT rowid, login, firstname, lastname FROM " . MAIN_DB_PREFIX . "user WHERE entity IN (0," . (int) $conf->entity . ") ORDER BY login");
while ($ru !== false && ($u = $db->fetch_object($ru))) {
    $uname = trim($u->firstname . ' ' . $u->lastname) ?: $u->login;
    print '<option value="' . $u->rowid . '">' . dol_escape_htmltag($uname) . '</option>';
}
print '</select></div>';
print '<div><label style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;display:block;margin-bottom:4px;">ملاحظة Note</label><input type="text" name="p_note" class="si" style="width:100%;"></div>';
print '<div style="display:flex;align-items:flex-end;"><button type="submit" class="sb g" style="width:100%;justify-content:center;"><i class="fa-solid fa-plus"></i> إضافة Ajouter</button></div>';
print '</div></form></div>';

// ═══ SUMMARY TABLE ═══
if (count($partners) > 0) {
    print '<div class="pc"><div class="ph"><div class="ph-l"><div class="pi am"><i class="fa-solid fa-table"></i></div>';
    print '<div><div class="pt">ملخص التوزيع Résumé</div></div></div></div>';
    print '<table class="tb"><thead><tr><th>الشريك</th><th>النسبة %</th><th>حصة CA</th><th>حصة الهامش</th><th>حصة الربح</th><th>المسحوب</th><th>المتبقي</th></tr></thead><tbody>';
    foreach ($partners as $p) {
        if (!$p->active) continue;
        $pct = (float) $p->percentage;

        // Get effective date for this partner
        $p_date_start = isset($p->date_effective) && !empty($p->date_effective) ? $p->date_effective : $p->date_creation;
        $p_date_end = date('Y-m-d');

        // Calculate period financials for THIS partner
        $p_sales = $db->fetch_object($db->query("SELECT COALESCE(SUM(total_ht),0) as ht FROM " . MAIN_DB_PREFIX . "facture WHERE datef BETWEEN '" . $db->escape($p_date_start) . "' AND '" . $db->escape($p_date_end) . "' AND fk_statut > 0 AND entity = " . (int) $conf->entity));
        $p_purchases = $db->fetch_object($db->query("SELECT COALESCE(SUM(total_ht),0) as ht FROM " . MAIN_DB_PREFIX . "facture_fourn WHERE datef BETWEEN '" . $db->escape($p_date_start) . "' AND '" . $db->escape($p_date_end) . "' AND fk_statut > 0 AND entity = " . (int) $conf->entity));
        $p_expenses = $db->fetch_object($db->query("SELECT COALESCE(SUM(e.amount_local),0) as total FROM " . MAIN_DB_PREFIX . "exportation_invoice_expenses e INNER JOIN " . MAIN_DB_PREFIX . "facture_fourn f ON f.rowid = e.fk_facture_fourn WHERE f.datef BETWEEN '" . $db->escape($p_date_start) . "' AND '" . $db->escape($p_date_end) . "' AND f.entity = " . (int) $conf->entity));

        $p_revenue = (float) $p_sales->ht;
        $p_cost = (float) $p_purchases->ht;
        $p_expenses_amt = (float) $p_expenses->total;
        $p_margin = $p_revenue - $p_cost;
        $p_profit = $p_margin - $p_expenses_amt;

        $sm = $p_margin * ($pct / 100);
        $sp = $p_profit * ($pct / 100);
        $sc = $p_revenue * ($pct / 100);
        $ob_d = $db->fetch_object($db->query("SELECT COALESCE(SUM(CASE WHEN dist_type='RETRAIT' THEN amount ELSE 0 END),0) as paid FROM " . MAIN_DB_PREFIX . "exportation_partner_distributions WHERE fk_partner = " . (int) $p->rowid . " AND dist_date BETWEEN '" . $db->escape($date_from) . "' AND '" . $db->escape($date_to) . "'"));
        $dp = (float) $ob_d->paid;
        $rem = max(0, $sp - $dp);
        print '<tr><td><strong>' . dol_escape_htmltag($p->name) . '</strong></td>';
        print '<td style="font-weight:700;">' . number_format($pct, 2) . '%</td>';
        print '<td>' . price($sc) . '</td>';
        print '<td style="color:#f59e0b;font-weight:700;">' . price($sm) . '</td>';
        print '<td style="color:' . ($sp >= 0 ? '#10b981' : '#ef4444') . ';font-weight:700;">' . price($sp) . '</td>';
        print '<td style="color:#ef4444;">' . price($dp) . '</td>';
        print '<td style="font-weight:800;color:' . ($rem >= 0 ? '#10b981' : '#ef4444') . ';">' . price($rem) . '</td></tr>';
    }
    print '</tbody></table></div>';
}

print '</div>';
llxFooter();
$db->close();
