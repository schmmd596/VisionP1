<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/paiementfourn.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';

$langs->loadLangs(array("companies", "bills", "banks", "exportation@exportation"));
$socid = GETPOST('socid', 'int');
$action = GETPOST('action', 'alpha');
$form = new Form($db);

// ═══ ACTION: Offsetting (Quite-Quite) ═══
if ($action == 'do_offset' && $socid > 0) {
    $amount_offset = (float) GETPOST('offset_amount', 'alphanohtml');
    
    if ($amount_offset > 0) {
        $db->begin();
        $error = 0;

        // Get unpaid client invoices (oldest first)
        $sqlc = "SELECT f.rowid, (f.total_ttc - COALESCE((SELECT SUM(pf2.amount) FROM " . MAIN_DB_PREFIX . "paiement_facture pf2 WHERE pf2.fk_facture = f.rowid),0)) as remain 
                 FROM " . MAIN_DB_PREFIX . "facture f 
                 WHERE f.fk_soc = " . $socid . " AND f.fk_statut = 1 
                 HAVING remain > 0.01 ORDER BY f.datef ASC";
        $resc = $db->query($sqlc);
        $client_invoices = array();
        while ($resc !== false && ($oc = $db->fetch_object($resc))) $client_invoices[] = $oc;

        // Get unpaid supplier invoices (oldest first)
        $sqls = "SELECT f.rowid, (f.total_ttc - COALESCE((SELECT SUM(pf2.amount) FROM " . MAIN_DB_PREFIX . "paiementfourn_facturefourn pf2 WHERE pf2.fk_facturefourn = f.rowid),0)) as remain 
                 FROM " . MAIN_DB_PREFIX . "facture_fourn f 
                 WHERE f.fk_soc = " . $socid . " AND f.fk_statut = 1 
                 HAVING remain > 0.01 ORDER BY f.datef ASC";
        $ress = $db->query($sqls);
        $supplier_invoices = array();
        while ($ress !== false && ($os = $db->fetch_object($ress))) $supplier_invoices[] = $os;

        // Pay client invoices
        $rem_c = $amount_offset;
        foreach ($client_invoices as $cinv) {
            if ($rem_c <= 0.01) break;
            $pay = min((float)$cinv->remain, $rem_c);
            $p = new Paiement($db);
            $p->datepaye = dol_now();
            $p->amounts = array($cinv->rowid => $pay);
            $p->paiementid = 1;
            $p->num_paiement = "OFFSET-" . date('YmdHis');
            $rid = $p->create($user);
            if ($rid <= 0) { $error++; }
            $rem_c -= $pay;
        }

        // Pay supplier invoices
        $rem_s = $amount_offset;
        foreach ($supplier_invoices as $sinv) {
            if ($rem_s <= 0.01) break;
            $pay = min((float)$sinv->remain, $rem_s);
            $pf = new PaiementFourn($db);
            $pf->datepaye = dol_now();
            $pf->amounts = array($sinv->rowid => $pay);
            $pf->paiementid = 1;
            $pf->num_paiement = "OFFSET-" . date('YmdHis');
            $rid = $pf->create($user);
            if ($rid <= 0) { $error++; }
            $rem_s -= $pay;
        }

        // Journal entry for the full offset
        $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_account_operations (fk_soc, operation_date, operation_type, label, debit, credit, currency_code, amount_local, fk_user_creat, date_creation) VALUES (" . (int) $socid . ", '" . date('Y-m-d') . "', 'OFFSET', 'Compensation تسوية', " . $amount_offset . ", " . $amount_offset . ", 'MRU', " . $amount_offset . ", " . (int) $user->id . ", '" . $db->idate(dol_now()) . "')");

        // Hidden entries to adjust manual balances for the portion of offset not applied to invoices
        if ($rem_c > 0.001) {
            $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_account_operations (fk_soc, operation_date, operation_type, label, debit, credit, currency_code, amount_local, fk_user_creat, date_creation) VALUES (" . (int) $socid . ", '" . date('Y-m-d') . "', 'OFFSET_MANUAL', 'Compensation Ajustement Client', 0, -" . $rem_c . ", 'MRU', 0, " . (int) $user->id . ", '" . $db->idate(dol_now()) . "')");
        }
        if ($rem_s > 0.001) {
            $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_account_operations (fk_soc, operation_date, operation_type, label, debit, credit, currency_code, amount_local, fk_user_creat, date_creation) VALUES (" . (int) $socid . ", '" . date('Y-m-d') . "', 'OFFSET_MANUAL', 'Compensation Ajustement Fournisseur', -" . $rem_s . ", 0, 'MRU', 0, " . (int) $user->id . ", '" . $db->idate(dol_now()) . "')");
        }

        if ($error == 0) {
            $db->commit();
            setEventMessages("Compensation effectuée: " . price($amount_offset) . " MRU", null, 'mesgs');
        } else {
            $db->rollback();
            setEventMessages("Erreur lors de la compensation", null, 'errors');
        }
        header("Location: unified_account.php?socid=" . $socid);
        exit;
    }
}

// ═══ ACTION: Mass payment to internal supplier (pay supplier invoices) ═══
if ($action == 'mass_pay_supplier' && $socid > 0) {
    $amount = (float) GETPOST('amount', 'alphanohtml');
    $fk_bank = (int) GETPOST('fk_bank', 'int');

    if ($amount > 0 && $fk_bank > 0) {
        $sql = "SELECT f.rowid, f.total_ttc, COALESCE(p.pay,0) as paid
                FROM " . MAIN_DB_PREFIX . "facture_fourn f
                LEFT JOIN (SELECT fk_facturefourn, SUM(amount) as pay FROM " . MAIN_DB_PREFIX . "paiementfourn_facturefourn GROUP BY fk_facturefourn) p ON p.fk_facturefourn = f.rowid
                WHERE f.fk_soc = " . (int) $socid . " AND f.fk_statut = 1
                ORDER BY f.datef ASC, f.rowid ASC";
        $res = $db->query($sql);

        $amounts_to_pay = array();
        $remaining = $amount;
        while ($res && ($f = $db->fetch_object($res)) && $remaining > 0.01) {
            $rem = (float) $f->total_ttc - (float) $f->paid;
            if ($rem <= 0.01) continue;
            $pay = min($rem, $remaining);
            $amounts_to_pay[$f->rowid] = $pay;
            $remaining -= $pay;
        }

        if (count($amounts_to_pay) > 0) {
            $db->begin();
            $pf = new PaiementFourn($db);
            $pf->datepaye = dol_now();
            $pf->amounts = $amounts_to_pay;
            // Use LIQ (id=4) for cash accounts, Virement (id=1) otherwise
            $acc_type_check = new Account($db);
            $pf->paiementid = ($acc_type_check->fetch($fk_bank) > 0 && $acc_type_check->type == Account::TYPE_CASH) ? 4 : 1;
            $pf->num_paiement = '';
            $pf->note_public = 'Paiement en masse (Fourn. Int.)';

            $pid = $pf->create($user);
            if ($pid > 0) {
                $result = $pf->addPaymentToBank($user, 'payment_supplier', '(Paiement en masse fourn. int.)', $fk_bank, '', '');
                if ($result > 0) {
                    $db->commit();
                    setEventMessages("Paiement en masse (" . price($amount) . " MRU) réparti sur " . count($amounts_to_pay) . " factures.", null, 'mesgs');
                } else {
                    $db->rollback();
                    setEventMessages("Erreur d'ajout à la banque: " . $pf->error, null, 'errors');
                }
            } else {
                $db->rollback();
                setEventMessages("Erreur création paiement: " . $pf->error, null, 'errors');
            }
        } else {
            setEventMessages("Aucune facture fournisseur à payer.", null, 'warnings');
        }
    }
    header("Location: unified_account.php?socid=" . $socid);
    exit;
}

// ═══ ACTION: Mass receive from client (pay client invoices) ═══
if ($action == 'mass_pay_client' && $socid > 0) {
    $amount = (float) GETPOST('amount', 'alphanohtml');
    $fk_bank = (int) GETPOST('fk_bank', 'int');

    if ($amount > 0 && $fk_bank > 0) {
        $sql = "SELECT f.rowid, f.total_ttc, COALESCE(p.pay,0) as paid
                FROM " . MAIN_DB_PREFIX . "facture f
                LEFT JOIN (SELECT fk_facture, SUM(amount) as pay FROM " . MAIN_DB_PREFIX . "paiement_facture GROUP BY fk_facture) p ON p.fk_facture = f.rowid
                WHERE f.fk_soc = " . (int) $socid . " AND f.fk_statut = 1
                ORDER BY f.datef ASC, f.rowid ASC";
        $res = $db->query($sql);

        $amounts_to_pay = array();
        $remaining = $amount;
        while ($res && ($f = $db->fetch_object($res)) && $remaining > 0.01) {
            $rem = (float) $f->total_ttc - (float) $f->paid;
            if ($rem <= 0.01) continue;
            $pay = min($rem, $remaining);
            $amounts_to_pay[$f->rowid] = $pay;
            $remaining -= $pay;
        }

        if (count($amounts_to_pay) > 0) {
            $db->begin();
            $paiement = new Paiement($db);
            $paiement->datepaye = dol_now();
            $paiement->amounts = $amounts_to_pay;
            // Use LIQ (id=4) for cash accounts, Virement (id=1) otherwise
            $acc_type_check2 = new Account($db);
            $paiement->paiementid = ($acc_type_check2->fetch($fk_bank) > 0 && $acc_type_check2->type == Account::TYPE_CASH) ? 4 : 1;
            $paiement->num_paiement = '';
            $paiement->note_public = 'Encaissement en masse (Fourn. Int. / Client)';

            $pid = $paiement->create($user, 1);
            if ($pid > 0) {
                $result = $paiement->addPaymentToBank($user, 'payment', '(Encaissement en masse client)', $fk_bank, '', '');
                if ($result > 0) {
                    $db->commit();
                    setEventMessages("Encaissement en masse (" . price($amount) . " MRU) réparti sur " . count($amounts_to_pay) . " factures.", null, 'mesgs');
                } else {
                    $db->rollback();
                    setEventMessages("Erreur d'ajout à la banque: " . $paiement->error, null, 'errors');
                }
            } else {
                $db->rollback();
                setEventMessages("Erreur création paiement: " . $paiement->error, null, 'errors');
            }
        } else {
            setEventMessages("Aucune facture client à encaisser.", null, 'warnings');
        }
    }
    header("Location: unified_account.php?socid=" . $socid);
    exit;
}

// ═══ ACTION: Add Manual Operation ═══
if ($action == 'add_operation' && $socid > 0) {
    $op_date = GETPOST('op_date', 'alpha');
    $op_type = GETPOST('op_type', 'alpha');
    $op_label = GETPOST('op_label', 'alpha');
    $op_amount = (float) GETPOST('op_amount', 'alphanohtml');
    $op_side = GETPOST('op_side', 'alpha');
    $fk_bank = (int) GETPOST('fk_bank', 'int');
    $debit = ($op_side == 'debit') ? $op_amount : 0;
    $credit = ($op_side == 'credit') ? $op_amount : 0;
    $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_account_operations (fk_soc, operation_date, operation_type, label, debit, credit, currency_code, amount_local, fk_user_creat, date_creation, fk_bank) VALUES (" . (int) $socid . ", '" . $db->escape($op_date) . "', '" . $db->escape($op_type) . "', '" . $db->escape($op_label) . "', " . $debit . ", " . $credit . ", 'MRU', " . $op_amount . ", " . (int) $user->id . ", '" . $db->idate(dol_now()) . "', " . ($fk_bank > 0 ? $fk_bank : 'NULL') . ")");
    
    if ($fk_bank > 0) {
        require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';
        $acc = new Account($db);
        if ($acc->fetch($fk_bank) > 0) {
            $bank_amt = ($op_side == 'credit') ? $op_amount : -$op_amount;
            $acc->addline(strtotime($op_date), 'CHQ', '('.$op_type.') '.$op_label, $bank_amt, 0, 0, $user);
        }
    }
    
    setEventMessages("Opération enregistrée.", null, 'mesgs');
    header("Location: unified_account.php?socid=" . $socid);
    exit;
}

// ═══ ACTION: Update note ═══
if ($action == 'update_note' && $socid > 0) {
    $soc_n = new Societe($db);
    $soc_n->fetch($socid);
    $note = GETPOST('note_private', 'restricthtml');
    $soc_n->update_note($note, '_private');
    header("Location: unified_account.php?socid=" . $socid);
    exit;
}

// ═══ UI ═══
llxHeader('', "Fournisseur Interne — الموردين الداخليين", '');
print '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">';
print '<div id="uw">';
print '<style>
#uw{max-width:1500px;margin:0 auto;font-family:"Outfit",sans-serif;padding:20px;color:#1e2a3a}
.uc{background:#fff;border-radius:16px;padding:24px 26px;box-shadow:0 2px 18px rgba(30,42,58,.06);border:1px solid #eef2f7;margin-bottom:20px}
.uh{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #f0f4f8;gap:10px;flex-wrap:wrap}
.uh-l{display:flex;align-items:center;gap:10px}
.ui2{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:15px;color:#fff;flex-shrink:0}
.ui2.bl{background:linear-gradient(135deg,#3b82f6,#2563eb)}.ui2.gr{background:linear-gradient(135deg,#10b981,#059669)}
.ui2.ro{background:linear-gradient(135deg,#f43f5e,#e11d48)}.ui2.pu{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.ui2.am{background:linear-gradient(135deg,#f59e0b,#d97706)}.ui2.cy{background:linear-gradient(135deg,#06b6d4,#0891b2)}
.ut{font-size:16px;font-weight:800}.us{font-size:11px;color:#94a3b8;margin-top:1px}
.ug{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}
.uk{background:#f8fafc;border-radius:11px;padding:16px 18px;border:1px solid #eef2f7;text-align:center}
.uk .kl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:5px}
.uk .kv{font-size:22px;font-weight:800}
.usi{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:"Outfit",sans-serif;box-sizing:border-box;background:#fff}
.usi:focus{border-color:#3b82f6;outline:none}
.usb{background:#3b82f6;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px;transition:.15s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.usb.g{background:linear-gradient(135deg,#10b981,#059669)}.usb.pu{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}.usb.am{background:linear-gradient(135deg,#f59e0b,#d97706)}.usb:hover{filter:brightness(1.08);transform:translateY(-1px)}
.tb{width:100%;border-collapse:collapse;font-size:13px}
.tb th{background:#f8fafc;padding:9px 11px;text-align:left;color:#64748b;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #eef2f7;white-space:nowrap}
.tb td{padding:10px 11px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.tb tbody tr:hover td{background:#fafbfd}
.dp{background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.bd{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;padding:4px 11px;border-radius:20px}
.bd.cr{background:#dcfce7;color:#15803d}.bd.db{background:#fee2e2;color:#b91c1c}.bd.of{background:#dbeafe;color:#1d4ed8}.bd.cm{background:#fef3c7;color:#92400e}
.off-box{background:linear-gradient(135deg,#eff6ff,#dbeafe);border:2px dashed #3b82f6;border-radius:14px;padding:20px 24px;text-align:center;margin-bottom:20px}
.off-icon{display:flex;align-items:center;justify-content:center;margin:16px auto;width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;font-size:22px;cursor:pointer;transition:.3s;box-shadow:0 6px 20px rgba(139,92,246,.3);animation:pulse-offset 2s infinite}
@keyframes pulse-offset{0%,100%{box-shadow:0 6px 20px rgba(139,92,246,.3)}50%{box-shadow:0 6px 30px rgba(139,92,246,.5);transform:scale(1.05)}}
.off-icon:hover{transform:scale(1.1);box-shadow:0 8px 30px rgba(139,92,246,.4)}
.off-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center}
.off-modal.show{display:flex}
.off-card{background:#fff;border-radius:20px;padding:30px;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.15)}
</style>';

// ═══ SELECTION PAGE ═══
if ($socid <= 0) {
    print '<div class="uc"><div class="uh"><div class="uh-l"><div class="ui2 bl"><i class="fa-solid fa-truck-field"></i></div><div><div class="ut">Fournisseur Interne — الموردين الداخليين</div><div class="us">اختيار المورد الداخلي / الزبون</div></div></div></div>';
    print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '"><div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;"><div style="flex:1;min-width:250px;">';
    print $form->select_company($socid, 'socid', '', 1, 0, 0, array(), 0, 'usi');
    print '</div><div><button type="submit" class="usb"><i class="fa-solid fa-arrow-right"></i> Ouvrir</button></div></div></form>';

    // List tiers (Exclude EXTERNAL) - Only suppliers (and those who are both client+supplier)
    $rl = $db->query("SELECT s.rowid, s.nom, s.fournisseur, s.client FROM " . MAIN_DB_PREFIX . "societe s WHERE s.entity = " . (int) $conf->entity . " AND s.fournisseur = 1 AND (s.exportation_type IS NULL OR s.exportation_type != 'EXTERNE') ORDER BY s.nom ASC LIMIT 100");
    if ($rl && $db->num_rows($rl) > 0) {
        print '<div style="margin-top:20px;"><table class="tb"><thead><tr><th>Tiers المورد/الزبون</th><th>Type النوع</th><th>Solde Client الرصيد</th><th>Solde Fourn. الرصيد</th><th></th></tr></thead><tbody>';
        while ($r = $db->fetch_object($rl)) {
            $type = ($r->fournisseur && $r->client) ? 'Fourn. Int. + Client' : ($r->fournisseur ? 'Fournisseur Interne' : 'Client');
            // Quick balance calc
            $obc = $db->fetch_object($db->query("SELECT COALESCE(SUM(f.total_ttc - COALESCE((SELECT SUM(pf2.amount) FROM " . MAIN_DB_PREFIX . "paiement_facture pf2 WHERE pf2.fk_facture = f.rowid),0)),0) as bal FROM " . MAIN_DB_PREFIX . "facture f WHERE f.fk_soc = " . $r->rowid . " AND f.fk_statut = 1"));
            $obs = $db->fetch_object($db->query("SELECT COALESCE(SUM(f.total_ttc - COALESCE((SELECT SUM(pf2.amount) FROM " . MAIN_DB_PREFIX . "paiementfourn_facturefourn pf2 WHERE pf2.fk_facturefourn = f.rowid),0)),0) as bal FROM " . MAIN_DB_PREFIX . "facture_fourn f WHERE f.fk_soc = " . $r->rowid . " AND f.fk_statut = 1"));
            $cb = max(0,(float)$obc->bal); $sb = max(0,(float)$obs->bal);
            
            $ops_q = $db->fetch_object($db->query("SELECT COALESCE(SUM(credit),0) as c, COALESCE(SUM(debit),0) as d FROM " . MAIN_DB_PREFIX . "exportation_account_operations WHERE fk_soc = " . $r->rowid . " AND (fk_bank IS NULL OR fk_bank = 0) AND operation_type NOT IN ('ACHAT', 'VENTE', 'PAIEMENT', 'OFFSET')"));
            if ($ops_q) {
                $cb += (float) $ops_q->c;
                $sb += (float) $ops_q->d;
            }
            print '<tr><td><a href="?socid=' . $r->rowid . '" style="font-weight:700;color:#3b82f6;">' . dol_escape_htmltag($r->nom) . '</a></td>';
            print '<td><span class="dp">' . $type . '</span></td>';
            print '<td style="color:#10b981;font-weight:700;">' . ($cb > 0 ? price($cb) : '—') . '</td>';
            print '<td style="color:#ef4444;font-weight:700;">' . ($sb > 0 ? price($sb) : '—') . '</td>';
            print '<td><a href="?socid=' . $r->rowid . '" class="usb" style="padding:5px 12px;font-size:12px;"><i class="fa-solid fa-arrow-right"></i></a></td></tr>';
        }
        print '</tbody></table></div>';
    }
    print '</div>';

} else {
    // ═══ ACCOUNT DETAIL ═══
    $soc = new Societe($db);
    $soc->fetch($socid);

    // Balances
    $ob_c = $db->fetch_object($db->query("SELECT COALESCE(SUM(f.total_ttc - COALESCE((SELECT SUM(pf2.amount) FROM " . MAIN_DB_PREFIX . "paiement_facture pf2 WHERE pf2.fk_facture = f.rowid),0)),0) as bal FROM " . MAIN_DB_PREFIX . "facture f WHERE f.fk_soc = " . $socid . " AND f.fk_statut = 1"));
    $client_balance = max(0, (float) $ob_c->bal);

    $ob_s = $db->fetch_object($db->query("SELECT COALESCE(SUM(f.total_ttc - COALESCE((SELECT SUM(pf2.amount) FROM " . MAIN_DB_PREFIX . "paiementfourn_facturefourn pf2 WHERE pf2.fk_facturefourn = f.rowid),0)),0) as bal FROM " . MAIN_DB_PREFIX . "facture_fourn f WHERE f.fk_soc = " . $socid . " AND f.fk_statut = 1"));
    $supplier_balance = max(0, (float) $ob_s->bal);
    
    // Incorporate manual unpaid operations in balances
    $rm_ops = $db->fetch_object($db->query("SELECT COALESCE(SUM(credit),0) as c, COALESCE(SUM(debit),0) as d FROM " . MAIN_DB_PREFIX . "exportation_account_operations WHERE fk_soc = " . $socid . " AND (fk_bank IS NULL OR fk_bank = 0) AND operation_type NOT IN ('ACHAT', 'VENTE', 'PAIEMENT', 'OFFSET')"));
    if ($rm_ops) {
        $client_balance += (float) $rm_ops->c;
        $supplier_balance += (float) $rm_ops->d;
    }

    $net = $client_balance - $supplier_balance;
    $max_offset = min($client_balance, $supplier_balance);

    // Get available banks
    $banks = array();
    $rb = $db->query("SELECT rowid, label, currency_code FROM " . MAIN_DB_PREFIX . "bank_account WHERE entity = " . (int) $conf->entity . " AND clos = 0 ORDER BY label");
    while ($rb !== false && ($bk = $db->fetch_object($rb))) {
        $banks[] = $bk;
    }

    print '<a href="' . $_SERVER['PHP_SELF'] . '" style="display:inline-flex;align-items:center;gap:6px;color:#64748b;font-weight:600;font-size:13px;text-decoration:none;margin-bottom:12px;"><i class="fa-solid fa-arrow-left"></i> Tous les Fournisseurs Internes</a>';

    // Header
    print '<div class="uc"><div class="uh"><div class="uh-l"><div class="ui2 bl"><i class="fa-solid fa-truck-field"></i></div>';
    print '<div><div class="ut">' . dol_escape_htmltag($soc->name) . '</div><div class="us">Fournisseur Interne — ';
    $types = array();
    if ($soc->fournisseur) $types[] = 'مورد داخلي Fournisseur Int.';
    if ($soc->client) $types[] = 'زبون Client';
    print implode(' + ', $types) . '</div></div></div></div>';

    // KPIs
    print '<div class="ug">';
    print '<div class="uk"><div class="kl"><i class="fa-solid fa-arrow-up"></i> Il nous doit (Client)</div><div class="kv" style="color:#10b981;">' . price($client_balance) . '</div></div>';
    print '<div class="uk"><div class="kl"><i class="fa-solid fa-arrow-down"></i> On lui doit (Fourn. Int.)</div><div class="kv" style="color:#ef4444;">' . price($supplier_balance) . '</div></div>';
    $nc = $net >= 0 ? '#10b981' : '#ef4444';
    print '<div class="uk"><div class="kl"><i class="fa-solid fa-scale-balanced"></i> Balance Nette</div><div class="kv" style="color:' . $nc . ';">' . price(abs($net)) . ' ' . ($net >= 0 ? '(Crédit)' : '(Débit)') . '</div></div>';
    if ($max_offset > 0.01) {
        print '<div class="uk" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border-color:#bfdbfe;cursor:pointer;" onclick="document.getElementById(\'offsetModal\').classList.add(\'show\')"><div class="kl" style="color:#6d28d9;"><i class="fa-solid fa-arrows-rotate"></i> Compensation Possible</div><div class="kv" style="color:#7c3aed;">' . price($max_offset) . '</div></div>';
    }
    print '</div></div>';

    // ═══ INVOICES SIDE BY SIDE WITH OFFSET ICON ═══
    print '<div style="display:grid;grid-template-columns:1fr auto 1fr;gap:0;align-items:start;">';

    // === Client invoices (LEFT) ===
    print '<div class="uc" style="margin:0;border-radius:16px 0 0 16px;border-right:none;">';
    print '<div class="uh"><div class="uh-l"><div class="ui2 gr"><i class="fa-solid fa-file-invoice-dollar"></i></div><div><div class="ut">Ventes (Client) — المبيعات</div></div></div></div>';
    $resc = $db->query("SELECT f.rowid, f.ref, f.datef, f.total_ttc, COALESCE((SELECT SUM(pf2.amount) FROM " . MAIN_DB_PREFIX . "paiement_facture pf2 WHERE pf2.fk_facture = f.rowid),0) as paid FROM " . MAIN_DB_PREFIX . "facture f WHERE f.fk_soc = " . $socid . " AND f.fk_statut = 1 ORDER BY f.datef DESC");
    print '<table class="tb"><thead><tr><th>Réf</th><th>Date</th><th>Total</th><th>Payé</th><th>Reste</th></tr></thead><tbody>';
    $hc = false;
    while ($resc !== false && ($oc = $db->fetch_object($resc))) {
        $hc = true;
        $rem = (float) $oc->total_ttc - (float) $oc->paid;
        $rc = $rem > 0.01 ? 'color:#ef4444;font-weight:800;' : 'color:#10b981;';
        print '<tr><td style="font-weight:700;"><a href="' . DOL_URL_ROOT . '/compta/facture/card.php?facid=' . $oc->rowid . '" style="color:#3b82f6;">' . $oc->ref . '</a></td>';
        print '<td>' . dol_print_date($db->jdate($oc->datef), 'day') . '</td>';
        print '<td>' . price($oc->total_ttc) . '</td><td style="color:#10b981;">' . price($oc->paid) . '</td>';
        print '<td style="' . $rc . '">' . price($rem) . '</td></tr>';
    }
    if (!$hc) print '<tr><td colspan="5" style="text-align:center;padding:20px;color:#94a3b8;">لا توجد فواتير</td></tr>';
    print '</tbody></table></div>';

    // === OFFSET ICON (CENTER) ===
    print '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:0 6px;min-height:200px;">';
    if ($max_offset > 0.01) {
        print '<div class="off-icon" onclick="document.getElementById(\'offsetModal\').classList.add(\'show\')" title="Compensation: ' . price($max_offset) . ' MRU">';
        print '<i class="fa-solid fa-arrows-rotate"></i></div>';
        print '<div style="font-size:10px;font-weight:800;color:#7c3aed;text-align:center;margin-top:6px;max-width:70px;line-height:1.2;">تسوية<br>' . price($max_offset) . '</div>';
    } else {
        print '<div style="width:40px;height:40px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#cbd5e1;font-size:16px;"><i class="fa-solid fa-minus"></i></div>';
    }
    print '</div>';

    // === Supplier invoices (RIGHT) ===
    print '<div class="uc" style="margin:0;border-radius:0 16px 16px 0;border-left:none;">';
    print '<div class="uh"><div class="uh-l"><div class="ui2 ro"><i class="fa-solid fa-file-invoice"></i></div><div><div class="ut">Achats (Fourn. Int.) — المشتريات</div></div></div></div>';
    $ress = $db->query("SELECT f.rowid, f.ref, f.datef, f.total_ttc, COALESCE((SELECT SUM(pf2.amount) FROM " . MAIN_DB_PREFIX . "paiementfourn_facturefourn pf2 WHERE pf2.fk_facturefourn = f.rowid),0) as paid FROM " . MAIN_DB_PREFIX . "facture_fourn f WHERE f.fk_soc = " . $socid . " AND f.fk_statut = 1 ORDER BY f.datef DESC");
    print '<table class="tb"><thead><tr><th>Réf</th><th>Date</th><th>Total</th><th>Payé</th><th>Reste</th></tr></thead><tbody>';
    $hs = false;
    while ($ress !== false && ($os = $db->fetch_object($ress))) {
        $hs = true;
        $rem = (float) $os->total_ttc - (float) $os->paid;
        $rc = $rem > 0.01 ? 'color:#ef4444;font-weight:800;' : 'color:#10b981;';
        print '<tr><td><a href="' . DOL_URL_ROOT . '/fourn/facture/card.php?facid=' . $os->rowid . '" style="font-weight:700;color:#3b82f6;">' . $os->ref . '</a></td>';
        print '<td>' . dol_print_date($db->jdate($os->datef), 'day') . '</td>';
        print '<td>' . price($os->total_ttc) . '</td><td style="color:#10b981;">' . price($os->paid) . '</td>';
        print '<td style="' . $rc . '">' . price($rem) . '</td></tr>';
    }
    if (!$hs) print '<tr><td colspan="5" style="text-align:center;padding:20px;color:#94a3b8;">لا توجد فواتير</td></tr>';
    print '</tbody></table></div>';

    print '</div>'; // end grid

    // ═══ OFFSET MODAL ═══
    if ($max_offset > 0.01) {
        print '<div id="offsetModal" class="off-modal" onclick="if(event.target===this)this.classList.remove(\'show\')">';
        print '<div class="off-card">';
        print '<div style="text-align:center;margin-bottom:20px;"><div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 12px;"><i class="fa-solid fa-arrows-rotate"></i></div>';
        print '<h3 style="font-size:18px;font-weight:800;color:#1e2a3a;margin:0;">Compensation — تسوية</h3>';
        print '<p style="color:#64748b;font-size:13px;margin-top:6px;">Il nous doit <strong style="color:#10b981;">' . price($client_balance) . '</strong> | On lui doit <strong style="color:#ef4444;">' . price($supplier_balance) . '</strong></p></div>';
        print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?socid=' . $socid . '">';
        print '<input type="hidden" name="action" value="do_offset"><input type="hidden" name="token" value="' . newToken() . '">';
        print '<div style="margin-bottom:14px;"><label style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;margin-bottom:4px;"><i class="fa-solid fa-money-bill-wave"></i> Montant de compensation</label>';
        print '<input type="number" step="0.01" name="offset_amount" value="' . number_format($max_offset, 2, '.', '') . '" max="' . $max_offset . '" class="usi" style="font-size:18px;font-weight:800;text-align:center;" required></div>';
        print '<button type="submit" class="usb pu" style="width:100%;justify-content:center;padding:12px;font-size:15px;margin-top:10px;" onclick="return confirm(\'Confirmer la compensation de \' + document.querySelector(\'[name=offset_amount]\').value + \' MRU ?\');"><i class="fa-solid fa-arrows-rotate"></i> Appliquer la Compensation</button>';
        print '</form>';
        print '<button onclick="document.getElementById(\'offsetModal\').classList.remove(\'show\')" style="width:100%;margin-top:10px;padding:10px;border:1.5px solid #e2e8f0;border-radius:8px;background:#f8fafc;cursor:pointer;font-weight:600;color:#64748b;font-size:13px;">Annuler</button>';
        print '</div></div>';
    }

    // ═══ MASS PAYMENT CARDS ═══
    if ($client_balance > 0.01 || $supplier_balance > 0.01) {
        print '<div style="display:grid;grid-template-columns:' . (($client_balance > 0.01 && $supplier_balance > 0.01) ? '1fr 1fr' : '1fr') . ';gap:20px;margin-bottom:20px;">';

        // Receive from client
        if ($client_balance > 0.01) {
            print '<div class="uc" style="margin:0;border:2px solid #bbf7d0;background:linear-gradient(180deg,#fff,#f0fdf4);">';
            print '<div class="uh"><div class="uh-l"><div class="ui2 gr"><i class="fa-solid fa-hand-holding-dollar"></i></div><div><div class="ut">Encaissement en Masse — Client</div><div class="us">Répartition automatique (plus ancienne d\'abord)</div></div></div></div>';
            print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?socid=' . $socid . '">';
            print '<input type="hidden" name="action" value="mass_pay_client"><input type="hidden" name="token" value="' . newToken() . '">';
            print '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">';
            print '<div><label style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px;">Montant (MRU) *</label>';
            print '<input type="number" step="0.01" min="0.01" max="' . number_format($client_balance, 2, '.', '') . '" name="amount" placeholder="Max: ' . number_format($client_balance, 2, '.', '') . '" class="usi" required></div>';
            print '<div><label style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px;">Caisse / Banque *</label>';
            print '<select name="fk_bank" class="usi" required><option value="">— Sélectionner —</option>';
            foreach ($banks as $bk) print '<option value="' . $bk->rowid . '">' . dol_escape_htmltag($bk->label) . '</option>';
            print '</select></div></div>';
            print '<button type="submit" class="usb g" style="width:100%;justify-content:center;padding:11px;"><i class="fa-solid fa-bolt"></i> Encaisser en Masse</button>';
            print '</form></div>';
        }

        // Pay supplier
        if ($supplier_balance > 0.01) {
            print '<div class="uc" style="margin:0;border:2px solid #fecdd3;background:linear-gradient(180deg,#fff,#fef2f2);">';
            print '<div class="uh"><div class="uh-l"><div class="ui2 ro"><i class="fa-solid fa-money-bill-transfer"></i></div><div><div class="ut">Paiement en Masse — Fourn. Int.</div><div class="us">Répartition automatique (plus ancienne d\'abord)</div></div></div></div>';
            print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?socid=' . $socid . '">';
            print '<input type="hidden" name="action" value="mass_pay_supplier"><input type="hidden" name="token" value="' . newToken() . '">';
            print '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">';
            print '<div><label style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px;">Montant (MRU) *</label>';
            print '<input type="number" step="0.01" min="0.01" max="' . number_format($supplier_balance, 2, '.', '') . '" name="amount" placeholder="Max: ' . number_format($supplier_balance, 2, '.', '') . '" class="usi" required></div>';
            print '<div><label style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px;">Caisse / Banque *</label>';
            print '<select name="fk_bank" class="usi" required><option value="">— Sélectionner —</option>';
            foreach ($banks as $bk) print '<option value="' . $bk->rowid . '">' . dol_escape_htmltag($bk->label) . '</option>';
            print '</select></div></div>';
            print '<button type="submit" class="usb" style="background:linear-gradient(135deg,#f43f5e,#e11d48);width:100%;justify-content:center;padding:11px;"><i class="fa-solid fa-bolt"></i> Payer en Masse</button>';
            print '</form></div>';
        }

        print '</div>'; // end mass payment grid
    }

    // Operations Journal
    print '<div class="uc"><div class="uh"><div class="uh-l"><div class="ui2 pu"><i class="fa-solid fa-scroll"></i></div>';
    print '<div><div class="ut">Journal des Opérations — سجل العمليات</div><div class="us">Historique chronologique</div></div></div></div>';

    $ro = $db->query("SELECT o.*, u.login FROM " . MAIN_DB_PREFIX . "exportation_account_operations o LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = o.fk_user_creat WHERE o.fk_soc = " . $socid . " AND o.operation_type != 'OFFSET_MANUAL' ORDER BY o.operation_date DESC, o.rowid DESC LIMIT 100");

    print '<div style="overflow-x:auto;"><table class="tb"><thead><tr><th>Date</th><th>Type</th><th>Libellé</th><th>Débit</th><th>Crédit</th><th>Utilisateur</th></tr></thead><tbody>';
    $has_ops = false;
    while ($ro !== false && ($op = $db->fetch_object($ro))) {
        $has_ops = true;
        $tc = 'cm';
        if ($op->operation_type == 'ACHAT') $tc = 'db';
        elseif ($op->operation_type == 'PAIEMENT' || $op->operation_type == 'VENTE') $tc = 'cr';
        elseif ($op->operation_type == 'OFFSET') $tc = 'of';
        print '<tr>';
        print '<td>' . dol_print_date($db->jdate($op->operation_date), 'day') . '</td>';
        print '<td><span class="bd ' . $tc . '"><i class="fa-solid fa-circle fa-xs"></i> ' . dol_escape_htmltag($op->operation_type) . '</span></td>';
        print '<td><strong>' . dol_escape_htmltag($op->label) . '</strong></td>';
        print '<td style="color:#ef4444;font-weight:700;">' . ($op->debit > 0 ? price($op->debit) : '') . '</td>';
        print '<td style="color:#10b981;font-weight:700;">' . ($op->credit > 0 ? price($op->credit) : '') . '</td>';
        print '<td style="color:#94a3b8;font-size:12px;">' . dol_escape_htmltag($op->login ?: '—') . '</td>';
        print '</tr>';
    }

    // Manual add row with bank selection
    print '<tr style="background:#f0f9ff;border-top:2px dashed #bfdbfe;"><form method="POST" action="' . $_SERVER['PHP_SELF'] . '?socid=' . $socid . '">';
    print '<input type="hidden" name="action" value="add_operation"><input type="hidden" name="token" value="' . newToken() . '">';
    print '<td><input type="date" name="op_date" value="' . date('Y-m-d') . '" class="usi" style="width:120px;" required></td>';
    print '<td><select name="op_type" class="usi" style="width:120px;" required><option value="COMMISSION">عمولة Commission</option><option value="SERVICE">خدمة Service</option><option value="AUTRE">أخرى Autre</option></select></td>';
    print '<td><input type="text" name="op_label" placeholder="الوصف / Description..." class="usi" style="width:170px;" required>';
    print '<select name="fk_bank" class="usi" style="width:150px;margin-left:5px;" required><option value="">— Caisse/Banque —</option>';
    foreach ($banks as $bk) { print '<option value="' . $bk->rowid . '">' . $bk->label . '</option>'; }
    print '</select></td>';
    print '<td colspan="2"><div style="display:flex;gap:6px;align-items:center;"><input type="number" step="0.01" name="op_amount" placeholder="المبلغ" class="usi" style="width:90px;" required>';
    print '<select name="op_side" class="usi" style="width:80px;"><option value="debit">Débit</option><option value="credit">Crédit</option></select></div></td>';
    print '<td><button type="submit" class="usb g"><i class="fa-solid fa-plus"></i></button></td>';
    print '</form></tr>';

    if (!$has_ops)
        print '<tr><td colspan="6" style="text-align:center;padding:28px;color:#94a3b8;"><i class="fa-solid fa-inbox" style="font-size:26px;display:block;margin-bottom:8px;"></i>Aucune opération</td></tr>';
    print '</tbody></table></div></div>';

    // Notes section
    print '<div class="uc"><div class="uh"><div class="uh-l"><div class="ui2 bl"><i class="fa-solid fa-note-sticky"></i></div><div><div class="ut">Notes Privées — ملاحظات</div></div></div></div>';
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?socid=' . $socid . '"><input type="hidden" name="action" value="update_note"><input type="hidden" name="token" value="' . newToken() . '">';
    print '<textarea name="note_private" class="usi" style="min-height:80px;resize:vertical;">' . dol_escape_htmltag($soc->note_private ?: '') . '</textarea>';
    print '<div style="margin-top:10px;"><button type="submit" class="usb"><i class="fa-solid fa-save"></i> Enregistrer</button></div>';
    print '</form></div>';
}

print '</div>';
llxFooter();
$db->close();
