<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';

$langs->loadLangs(array("companies", "bills", "exportation@exportation"));
$socid = GETPOST('socid', 'int');
$action = GETPOST('action', 'alpha');
$form = new Form($db);

// ═══ ACTIONS ═══
if ($socid > 0) {
    $soc = new Societe($db);
    $soc->fetch($socid);

    if ($action == 'update_risk') {
        $risk = GETPOST('risk', 'alpha');
        $db->query("UPDATE " . MAIN_DB_PREFIX . "societe SET exportation_risk = '" . $db->escape($risk) . "' WHERE rowid = " . $socid);
        $soc->exportation_risk = $risk;
    }
    if ($action == 'update_credit') {
        $limit = (float) GETPOST('credit_limit', 'alphanohtml');
        // Use native Dolibarr field: encours (outstanding/credit limit)
        $soc->encours = $limit;
        $soc->update($soc->id, $user);
    }
    if ($action == 'update_note') {
        $note = GETPOST('note_private', 'restricthtml');
        // Use native Dolibarr field: note_private
        $soc->note_private = $note;
        $soc->update_note($note, '_private');
    }
    if ($action == 'add_mass_payment_client' && $socid > 0) {
        $amount_form = (float) GETPOST('amount', 'alphanohtml');
        $currency = GETPOST('currency', 'alpha') ?: 'MRU';
        $exchange_rate = (float) GETPOST('exchange_rate', 'alphanohtml') ?: 1;
        if ($currency == 'MRU') $exchange_rate = 1;

        $amount_paid_mru = $amount_form * $exchange_rate;
        $fk_bank = GETPOST('fk_bank', 'int');

        // Detect if the selected account is a cash account → use LIQ (id=4), otherwise use Virement (id=1)
        $mode_reglement_id = 1;
        if ($fk_bank > 0) {
            $acc_check = new Account($db);
            if ($acc_check->fetch($fk_bank) > 0 && $acc_check->type == Account::TYPE_CASH) {
                $mode_reglement_id = 4; // LIQ - Cash/Espèce
            }
        }

        if ($amount_paid_mru > 0 && $fk_bank > 0) {
            // Get all unpaid invoices for this client using the same logic as the unpaid table
            $sql = "SELECT f.rowid, f.ref, f.datef, f.total_ttc, COALESCE(p.pay,0) as paid FROM " . MAIN_DB_PREFIX . "facture f LEFT JOIN (SELECT fk_facture, SUM(amount) as pay FROM " . MAIN_DB_PREFIX . "paiement_facture GROUP BY fk_facture) p ON p.fk_facture = f.rowid WHERE f.fk_soc = " . $socid . " AND f.fk_statut = 1 ORDER BY f.datef ASC, f.rowid ASC";
            $res = $db->query($sql);

            $amounts_to_pay = array();
            $invoices_to_pay = array();
            $remaining_budget = $amount_paid_mru;

            while ($res && ($fac_obj = $db->fetch_object($res)) && $remaining_budget > 0) {
                $already_paid = (float)$fac_obj->paid;
                $fac_remaining = (float)$fac_obj->total_ttc - $already_paid;

                if ($fac_remaining <= 0) continue;

                $payment_for_this = min($fac_remaining, $remaining_budget);
                $amounts_to_pay[$fac_obj->rowid] = $payment_for_this;
                $remaining_budget -= $payment_for_this;
            }

            if (count($amounts_to_pay) > 0) {
                $paiement = new Paiement($db);
                $paiement->datepaye = dol_now();
                $paiement->amounts = $amounts_to_pay;
                $paiement->paiementid = $mode_reglement_id;
                $paiement->num_paiement = '';
                $paiement->note_public = 'Paiement en masse (' . $currency . ')';

                $db->begin();
                $pid = $paiement->create($user, 1);
                if ($pid > 0) {
                    $result = $paiement->addPaymentToBank($user, 'payment_invoice', '(Paiement en masse client)', $fk_bank, '', '');
                    if ($result > 0) {
                        $db->commit();
                        setEventMessages("Paiement en masse (" . price($amount_paid_mru) . " MRU) enregistré sur " . count($amounts_to_pay) . " factures.", null, 'mesgs');
                    } else {
                        $db->rollback();
                        setEventMessages("Erreur d'ajout à la banque: " . $paiement->error, null, 'errors');
                    }
                } else {
                    $db->rollback();
                    setEventMessages("Erreur création paiement: " . $paiement->error, null, 'errors');
                }
            } else {
                setEventMessages("Aucune facture à payer trouvée pour ce client.", null, 'warnings');
            }
        }
        header("Location: customer_dashboard.php?socid=" . $socid);
        exit;
    }
}

// ═══ UI ═══
llxHeader('', "Gestion Client Avancée", '');
print '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">';
print '<div id="cd">';
print '<style>
#cd{max-width:1500px;margin:0 auto;font-family:"Outfit",sans-serif;padding:20px;color:#1e2a3a}
.cc{background:#fff;border-radius:16px;padding:24px 26px;box-shadow:0 2px 18px rgba(30,42,58,.06);border:1px solid #eef2f7;margin-bottom:20px}
.ch{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #f0f4f8;gap:10px;flex-wrap:wrap}
.ch-l{display:flex;align-items:center;gap:10px}
.ci{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:15px;color:#fff;flex-shrink:0}
.ci.bl{background:linear-gradient(135deg,#3b82f6,#2563eb)}.ci.gr{background:linear-gradient(135deg,#10b981,#059669)}
.ci.am{background:linear-gradient(135deg,#f59e0b,#d97706)}.ci.ro{background:linear-gradient(135deg,#f43f5e,#e11d48)}
.ci.pu{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.ct{font-size:16px;font-weight:800}.cs{font-size:11px;color:#94a3b8;margin-top:1px}
.cg{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}
.ck{background:#f8fafc;border-radius:11px;padding:16px 18px;border:1px solid #eef2f7;text-align:center}
.ck .kl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:5px}
.ck .kv{font-size:22px;font-weight:800}
.csi{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:"Outfit",sans-serif;box-sizing:border-box;background:#fff}
.csi:focus{border-color:#3b82f6;outline:none}
.csb{background:#3b82f6;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px;transition:.15s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.csb.g{background:linear-gradient(135deg,#10b981,#059669)}.csb:hover{filter:brightness(1.08);transform:translateY(-1px)}
.tb{width:100%;border-collapse:collapse;font-size:13px}
.tb th{background:#f8fafc;padding:9px 11px;text-align:left;color:#64748b;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #eef2f7;white-space:nowrap}
.tb td{padding:10px 11px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.tb tbody tr:hover td{background:#fafbfd}
.dp{background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.risk-bar{display:flex;height:6px;border-radius:3px;overflow:hidden;margin-top:8px;background:#e2e8f0}
.risk-bar .fill{transition:width .4s ease}
</style>';

// ═══ SELECTION PAGE ═══
if ($socid <= 0) {
    print '<div class="cc"><div class="ch"><div class="ch-l"><div class="ci bl"><i class="fa-solid fa-user-tie"></i></div><div><div class="ct">Dashboard Client — الزبائن</div><div class="cs">Sélectionnez un client</div></div></div></div>';
    print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '"><div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;"><div style="flex:1;min-width:250px;">';
    print $form->select_company($socid, 'socid', 'client>=1', 1, 0, 0, array(), 0, 'csi');
    print '</div><div><button type="submit" class="csb"><i class="fa-solid fa-arrow-right"></i> Ouvrir</button></div></div></form>';

    // Client list
    $rl = $db->query("SELECT s.rowid, s.nom, s.exportation_risk, s.encours, COALESCE((SELECT SUM(f.total_ttc - COALESCE(p.pay,0)) FROM " . MAIN_DB_PREFIX . "facture f LEFT JOIN (SELECT fk_facture, SUM(amount) as pay FROM " . MAIN_DB_PREFIX . "paiement_facture GROUP BY fk_facture) p ON p.fk_facture = f.rowid WHERE f.fk_soc = s.rowid AND f.fk_statut = 1),0) as due, (SELECT COUNT(*) FROM " . MAIN_DB_PREFIX . "facture WHERE fk_soc = s.rowid AND fk_statut = 1 AND date_lim_reglement < CURRENT_DATE) as late FROM " . MAIN_DB_PREFIX . "societe s WHERE s.entity = " . (int) $conf->entity . " AND s.client >= 1 ORDER BY s.nom ASC LIMIT 100");
    if ($rl && $db->num_rows($rl) > 0) {
        print '<table class="tb" style="margin-top:20px;"><thead><tr><th>Client</th><th>Solde Dû</th><th>Retards</th><th>Risque</th><th>Crédit</th><th></th></tr></thead><tbody>';
        while ($r = $db->fetch_object($rl)) {
            $risk = $r->exportation_risk ?: 'ACTIVE';
            $rc = array('ACTIVE' => '#10b981', 'RISK' => '#f59e0b', 'DEFAULT' => '#ef4444');
            $dc = (float) $r->due > 0 ? 'color:#ef4444;' : 'color:#10b981;';
            print '<tr><td><a href="?socid=' . $r->rowid . '" style="font-weight:700;color:#3b82f6;">' . dol_escape_htmltag($r->nom) . '</a></td>';
            print '<td style="font-weight:700;' . $dc . '">' . price($r->due) . '</td>';
            print '<td style="font-weight:700;color:' . ((int) $r->late > 0 ? '#ef4444' : '#10b981') . ';">' . (int) $r->late . '</td>';
            print '<td><span style="background:' . ($rc[$risk] ?? '#94a3b8') . '20;color:' . ($rc[$risk] ?? '#94a3b8') . ';padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">' . $risk . '</span></td>';
            print '<td>' . price($r->encours) . '</td>';
            print '<td><a href="?socid=' . $r->rowid . '" class="csb" style="padding:5px 12px;font-size:12px;"><i class="fa-solid fa-arrow-right"></i></a></td></tr>';
        }
        print '</tbody></table>';
    }
    print '</div>';

} else {
    // ═══ CLIENT DETAIL ═══

    // Stats
    $ob_bal = $db->fetch_object($db->query("SELECT COALESCE(SUM(f.total_ttc - COALESCE(p.pay,0)),0) as bal FROM " . MAIN_DB_PREFIX . "facture f LEFT JOIN (SELECT fk_facture, SUM(amount) as pay FROM " . MAIN_DB_PREFIX . "paiement_facture GROUP BY fk_facture) p ON p.fk_facture = f.rowid WHERE f.fk_soc = " . $socid . " AND f.fk_statut = 1"));
    $due = (float) $ob_bal->bal;

    $ob_late = $db->fetch_object($db->query("SELECT COUNT(*) as nb FROM " . MAIN_DB_PREFIX . "facture WHERE fk_soc = " . $socid . " AND fk_statut = 1 AND date_lim_reglement < CURRENT_DATE"));
    $late_count = (int) $ob_late->nb;

    $ob_total = $db->fetch_object($db->query("SELECT COUNT(*) as nb, COALESCE(SUM(total_ttc),0) as total FROM " . MAIN_DB_PREFIX . "facture WHERE fk_soc = " . $socid . " AND entity = " . (int) $conf->entity));
    $ob_paid = $db->fetch_object($db->query("SELECT COALESCE(SUM(pf.amount),0) as paid FROM " . MAIN_DB_PREFIX . "paiement_facture pf INNER JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = pf.fk_facture WHERE f.fk_soc = " . $socid));

    $risk_lvl = $soc->exportation_risk ?: 'ACTIVE';
    $credit_limit = (float) ($soc->encours ?: 0); // Native Dolibarr field
    $usage_pct = ($credit_limit > 0) ? min(100, round(($due / $credit_limit) * 100)) : 0;
    $risk_cfg = array('ACTIVE' => array('#10b981', 'نشط Actif'), 'RISK' => array('#f59e0b', 'مخاطر Risque'), 'DEFAULT' => array('#ef4444', 'متعثر Défaillant'));
    $rc = isset($risk_cfg[$risk_lvl]) ? $risk_cfg[$risk_lvl] : $risk_cfg['ACTIVE'];

    print '<a href="' . $_SERVER['PHP_SELF'] . '" style="display:inline-flex;align-items:center;gap:6px;color:#64748b;font-weight:600;font-size:13px;text-decoration:none;margin-bottom:12px;"><i class="fa-solid fa-arrow-left"></i> Tous les Clients</a>';

    // Header
    print '<div class="cc"><div class="ch"><div class="ch-l"><div class="ci bl"><i class="fa-solid fa-user-tie"></i></div>';
    print '<div><div class="ct">' . dol_escape_htmltag($soc->name) . '</div><div class="cs">إدارة الزبون — Gestion Client Avancée</div></div></div>';
    // Risk + Credit
    print '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
    print '<span style="background:' . $rc[0] . '20;color:' . $rc[0] . ';padding:6px 14px;border-radius:20px;font-weight:700;font-size:12px;">' . $rc[1] . '</span>';
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?socid=' . $socid . '" style="display:inline;"><input type="hidden" name="action" value="update_risk">';
    print '<select name="risk" onchange="this.form.submit()" class="csi" style="width:110px;">';
    foreach (array('ACTIVE' => 'Actif', 'RISK' => 'Risque', 'DEFAULT' => 'Défaillant') as $rk => $rlbl)
        print '<option value="' . $rk . '" ' . ($rk == $risk_lvl ? 'selected' : '') . '>' . $rlbl . '</option>';
    print '</select></form></div></div>';

    // KPIs
    print '<div class="cg">';
    print '<div class="ck"><div class="kl"><i class="fa-solid fa-coins"></i> الرصيد المستحق Solde Dû</div><div class="kv" style="color:#ef4444;">' . price($due) . '</div></div>';
    print '<div class="ck"><div class="kl"><i class="fa-solid fa-credit-card"></i> الحد الائتماني Crédit</div><div class="kv" style="color:#3b82f6;">' . price($credit_limit) . '</div>';
    if ($credit_limit > 0) {
        $bcolor = $usage_pct > 80 ? '#ef4444' : ($usage_pct > 50 ? '#f59e0b' : '#10b981');
        print '<div class="risk-bar"><div class="fill" style="width:' . $usage_pct . '%;background:' . $bcolor . ';"></div></div>';
        print '<div style="font-size:10px;color:#94a3b8;margin-top:3px;">' . $usage_pct . '% مستخدم</div>';
    }
    print '</div>';
    print '<div class="ck"><div class="kl"><i class="fa-solid fa-clock-rotate-left"></i> فواتير متأخرة Retards</div><div class="kv" style="color:' . ($late_count > 0 ? '#ef4444' : '#10b981') . ';">' . $late_count . '</div></div>';
    print '<div class="ck"><div class="kl"><i class="fa-solid fa-file-invoice"></i> إجمالي الفواتير Total</div><div class="kv" style="color:#1e2a3a;">' . (int) $ob_total->nb . '</div></div>';
    print '<div class="ck"><div class="kl"><i class="fa-solid fa-chart-line"></i> حجم الأعمال Volume</div><div class="kv" style="color:#8b5cf6;">' . price($ob_total->total) . '</div></div>';
    print '<div class="ck"><div class="kl"><i class="fa-solid fa-hand-holding-dollar"></i> إجمالي المدفوع Payé</div><div class="kv" style="color:#10b981;">' . price($ob_paid->paid) . '</div></div>';
    print '</div>';

    // Credit limit update
    print '<div style="margin-top:14px;display:flex;gap:10px;align-items:center;">';
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?socid=' . $socid . '" style="display:flex;gap:8px;align-items:center;"><input type="hidden" name="action" value="update_credit">';
    print '<span style="font-size:12px;color:#64748b;font-weight:600;">Modifier limite:</span>';
    print '<input type="number" step="0.01" name="credit_limit" value="' . number_format($credit_limit, 2, '.', '') . '" class="csi" style="width:140px;">';
    print '<button type="submit" class="csb" style="padding:6px 14px;"><i class="fa-solid fa-save"></i></button></form></div>';
    print '</div>';

    // Two-column layout
    print '<div style="display:grid;grid-template-columns:1fr 1.5fr;gap:20px;">';

    // Mass payment form
    print '<div class="cc"><div class="ch"><div class="ch-l"><div class="ci am"><i class="fa-solid fa-money-bills-wave"></i></div><div><div class="ct" style="font-size:15px;">Paiement Masse</div><div class="cs" style="font-size:10px;">Répartir un paiement</div></div></div></div>';
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?socid=' . $socid . '" style="font-size:12px;">';
    print '<input type="hidden" name="action" value="add_mass_payment_client"><input type="hidden" name="token" value="' . newToken() . '">';

    print '<div style="margin-bottom:8px;"><label style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:2px;">Client</label>';
    print '<select name="socid" id="socid_select" class="csi" onchange="window.location=\'?socid=\'+this.value" style="font-size:11px;padding:6px 8px;">';
    $rc = $db->query("SELECT s.rowid, s.nom FROM " . MAIN_DB_PREFIX . "societe s WHERE s.entity = " . (int) $conf->entity . " AND s.client >= 1 ORDER BY s.nom ASC LIMIT 50");
    while ($rc && ($r = $db->fetch_object($rc))) {
        $selected = ($r->rowid == $socid) ? ' selected' : '';
        print '<option value="' . $r->rowid . '"' . $selected . '>' . dol_escape_htmltag($r->nom) . '</option>';
    }
    print '</select></div>';

    print '<div style="margin-bottom:8px;"><label style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:2px;">Devise</label>';
    print '<select name="currency" id="currency" class="csi" onchange="toggleExchangeRate()" style="font-size:11px;padding:6px 8px;">';
    print '<option value="MRU">MRU</option><option value="USD">USD</option><option value="EUR">EUR</option><option value="RMB">RMB</option></select></div>';

    print '<div id="div_rate" style="display:none;margin-bottom:8px;"><label style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:2px;">Taux</label>';
    print '<input type="number" step="0.000001" name="exchange_rate" id="exchange_rate" class="csi" value="1" style="font-size:11px;padding:6px 8px;" oninput="calculateTotal()"></div>';

    print '<div style="margin-bottom:8px;"><label style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:2px;"><span id="label_amount">Montant (MRU)</span></label>';
    print '<input type="number" step="0.01" min="0.01" name="amount" id="amount" class="csi" required style="font-size:11px;padding:6px 8px;" oninput="calculateTotal()"></div>';

    print '<div style="margin-bottom:8px;"><label style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:2px;">Caisse</label>';
    print '<select name="fk_bank" class="csi" required style="font-size:11px;padding:6px 8px;">';
    print '<option value="">-- Sélectionnez --</option>';
    $rb = $db->query("SELECT rowid, label, currency_code FROM " . MAIN_DB_PREFIX . "bank_account WHERE entity = " . (int) $conf->entity . " ORDER BY label");
    while ($rb && ($bk = $db->fetch_object($rb))) {
        print '<option value="' . $bk->rowid . '">' . dol_escape_htmltag($bk->label) . '</option>';
    }
    print '</select></div>';

    print '<button type="submit" class="csb g" style="width:100%;justify-content:center;font-size:11px;padding:8px 12px;"><i class="fa-solid fa-bolt"></i> Payer</button>';
    print '</form></div>';

    // Payment history
    print '<div class="cc"><div class="ch"><div class="ch-l"><div class="ci gr"><i class="fa-solid fa-clock-rotate-left"></i></div><div><div class="ct">سجل المدفوعات Paiements</div><div class="cs">آخر 20 دفعة</div></div></div></div>';
    $resp = $db->query("SELECT p.rowid, p.datep, pf.amount, f.ref FROM " . MAIN_DB_PREFIX . "paiement p INNER JOIN " . MAIN_DB_PREFIX . "paiement_facture pf ON pf.fk_paiement = p.rowid INNER JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = pf.fk_facture WHERE f.fk_soc = " . $socid . " ORDER BY p.datep DESC LIMIT 5000");
    print '<table class="tb" id="paiements-list" data-pg="1"><thead><tr><th>التاريخ Date</th><th>الفاتورة Facture</th><th>المبلغ Versé</th></tr></thead><tbody>';
    $hp = false;
    while ($resp !== false && ($op = $db->fetch_object($resp))) {
        $hp = true;
        print '<tr><td>' . dol_print_date($db->jdate($op->datep), 'day') . '</td>';
        print '<td><strong>' . dol_escape_htmltag($op->ref) . '</strong></td>';
        print '<td style="font-weight:700;color:#10b981;">+' . price($op->amount) . '</td></tr>';
    }
    if (!$hp) print '<tr><td colspan="3" style="text-align:center;padding:20px;color:#94a3b8;">لا توجد مدفوعات</td></tr>';
    print '</tbody></table></div>';
    print '</div>';

    // Unpaid invoices
    print '<div class="cc"><div class="ch"><div class="ch-l"><div class="ci ro"><i class="fa-solid fa-file-circle-exclamation"></i></div><div><div class="ct">الفواتير غير المسددة Impayées</div></div></div></div>';
    $rui = $db->query("SELECT f.rowid, f.ref, f.datef, f.date_lim_reglement, f.total_ttc, COALESCE(p.pay,0) as paid FROM " . MAIN_DB_PREFIX . "facture f LEFT JOIN (SELECT fk_facture, SUM(amount) as pay FROM " . MAIN_DB_PREFIX . "paiement_facture GROUP BY fk_facture) p ON p.fk_facture = f.rowid WHERE f.fk_soc = " . $socid . " AND f.fk_statut = 1 ORDER BY f.datef DESC");
    print '<table class="tb" id="impayees-list" data-pg="1"><thead><tr><th>المرجع Réf</th><th>التاريخ Date</th><th>الاستحقاق Échéance</th><th>الإجمالي Total</th><th>المدفوع Payé</th><th>الباقي Reste</th><th>الحالة Statut</th></tr></thead><tbody>';
    $hui = false;
    while ($rui !== false && ($ui = $db->fetch_object($rui))) {
        $hui = true;
        $remain = (float) $ui->total_ttc - (float) $ui->paid;
        $is_late = ($ui->date_lim_reglement && strtotime($ui->date_lim_reglement) < time());
        print '<tr><td><strong>' . $ui->ref . '</strong></td>';
        print '<td>' . dol_print_date($db->jdate($ui->datef), 'day') . '</td>';
        print '<td>' . ($ui->date_lim_reglement ? dol_print_date($db->jdate($ui->date_lim_reglement), 'day') : '—') . '</td>';
        print '<td>' . price($ui->total_ttc) . '</td>';
        print '<td style="color:#10b981;font-weight:700;">' . price($ui->paid) . '</td>';
        print '<td style="color:#ef4444;font-weight:800;">' . price($remain) . '</td>';
        if ($is_late)
            print '<td><span style="background:#fee2e2;color:#b91c1c;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;"><i class="fa-solid fa-circle-exclamation"></i> تأخر Retard</span></td>';
        else
            print '<td><span style="background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;"><i class="fa-solid fa-clock"></i> جارٍ En cours</span></td>';
        print '</tr>';
    }
    if (!$hui) print '<tr><td colspan="7" style="text-align:center;padding:20px;color:#94a3b8;">لا توجد فواتير غير مسددة</td></tr>';
    print '</tbody></table></div>';

    // Notes
    print '<div class="cc"><div class="ch"><div class="ch-l"><div class="ci pu"><i class="fa-solid fa-note-sticky"></i></div><div><div class="ct">ملاحظات خاصة Notes</div></div></div></div>';
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?socid=' . $socid . '"><input type="hidden" name="action" value="update_note"><input type="hidden" name="token" value="' . newToken() . '">';
    print '<textarea name="note_private" class="csi" style="min-height:80px;resize:vertical;">' . dol_escape_htmltag($soc->note_private ?: '') . '</textarea>';
    print '<div style="margin-top:10px;"><button type="submit" class="csb"><i class="fa-solid fa-save"></i> حفظ Enregistrer</button></div>';
    print '</form></div>';
}

print '</div>';

// ═══ Pagination CSS + JS (réutilisable) ═══
print '<style>
.pg-bar{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin:12px 0;font-family:"Outfit",sans-serif;}
.pg-l{display:flex;align-items:center;gap:8px;font-size:12px;color:#64748b;font-weight:600;}
.pg-sel{padding:6px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:12px;font-family:inherit;background:#fff;cursor:pointer;font-weight:600;color:#1e2a3a;}
.pg-info{color:#94a3b8;font-weight:500;}
.pg-nav{display:flex;gap:4px;flex-wrap:wrap;}
.pg-btn{padding:5px 10px;border:1.5px solid #e2e8f0;background:#fff;color:#1e2a3a;border-radius:6px;font-weight:700;font-size:12px;cursor:pointer;font-family:inherit;min-width:32px;transition:.15s;}
.pg-btn:hover:not(:disabled):not(.a){background:#f8fafc;border-color:#3b82f6;color:#3b82f6;}
.pg-btn.a{background:#3b82f6;color:#fff;border-color:#3b82f6;cursor:default;}
.pg-btn:disabled{opacity:.4;cursor:not-allowed;}
.pg-scroll{max-height:560px;overflow:auto;border-radius:8px;border:1px solid #f1f5f9;}
.pg-scroll table{margin:0;}
.pg-scroll thead th{position:sticky;top:0;z-index:5;}
</style>';
print '<script>
(function(){
  function init(table, def){
    if(!table) return;
    var tbody = table.querySelector("tbody");
    if(!tbody) return;
    var rows = Array.prototype.slice.call(tbody.querySelectorAll(":scope > tr")).filter(function(tr){
      if(tr.classList.contains("ar")) return false;
      var td = tr.querySelector("td");
      if(td && td.getAttribute("colspan")) return false;
      return true;
    });
    if(rows.length === 0) return;
    var perPage = def || 20, page = 1;
    var bar = document.createElement("div");
    bar.className = "pg-bar";
    bar.innerHTML = \'<div class="pg-l"><span>Afficher</span><select class="pg-sel"><option value="20">20</option><option value="50">50</option><option value="100">100</option><option value="500">500</option></select><span class="pg-info"></span></div><div class="pg-nav"></div>\';
    table.parentNode.insertBefore(bar, table);
    var sw = document.createElement("div");
    sw.className = "pg-scroll";
    table.parentNode.insertBefore(sw, table);
    sw.appendChild(table);
    var sel = bar.querySelector(".pg-sel"); sel.value = perPage;
    var info = bar.querySelector(".pg-info"), nav = bar.querySelector(".pg-nav");
    function btn(t,p,dis,act){return \'<button type="button" class="pg-btn\'+(act?" a":"")+\'" data-p="\'+p+\'"\'+(dis?" disabled":"")+\'>\'+t+\'</button>\';}
    function render(){
      var total = rows.length, tot = Math.max(1, Math.ceil(total/perPage));
      if(page > tot) page = tot;
      var s = (page-1)*perPage, e = s + perPage;
      rows.forEach(function(r,i){ r.style.display = (i>=s && i<e) ? "" : "none"; });
      info.textContent = (total ? s+1 : 0) + "-" + Math.min(e,total) + " / " + total;
      var h = btn("«",1,page===1) + btn("‹",page-1,page===1);
      var mv = 5, sp = Math.max(1, page - Math.floor(mv/2)), ep = Math.min(tot, sp+mv-1);
      sp = Math.max(1, ep - mv + 1);
      for(var p = sp; p <= ep; p++) h += btn(p,p,false,p===page);
      h += btn("›",page+1,page===tot) + btn("»",tot,page===tot);
      nav.innerHTML = h;
      nav.querySelectorAll(".pg-btn").forEach(function(b){
        b.addEventListener("click", function(){
          if(this.disabled || this.classList.contains("a")) return;
          page = parseInt(this.getAttribute("data-p"),10);
          render();
        });
      });
    }
    sel.addEventListener("change", function(){ perPage = parseInt(sel.value,10); page = 1; render(); });
    render();
  }
  document.querySelectorAll("table[data-pg]").forEach(function(t){
    init(t, parseInt(t.getAttribute("data-pg-default") || "20", 10));
  });
})();
</script>';
?>
<script>
function toggleExchangeRate() {
    var curr = document.getElementById('currency').value;
    var divRate = document.getElementById('div_rate');
    var label = document.getElementById('label_amount');

    if (curr === 'MRU') {
        divRate.style.display = 'none';
        label.innerText = 'Montant (MRU)';
        document.getElementById('exchange_rate').value = 1;
    } else {
        divRate.style.display = 'block';
        label.innerText = 'Montant (' + curr + ')';
        if (document.getElementById('exchange_rate').value == 1) {
            // Let user enter rate
        }
    }
}

function calculateTotal() {
    var curr = document.getElementById('currency').value;
    var amt = parseFloat(document.getElementById('amount').value) || 0;
    var rate = parseFloat(document.getElementById('exchange_rate').value) || 1;
    // Just for display, not stored
}
</script>
<?php
llxFooter();
$db->close();
