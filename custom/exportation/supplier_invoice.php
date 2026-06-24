<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/paiementfourn.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

$langs->loadLangs(array("facture", "companies", "products", "main", "banks", "exportation@exportation"));

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');

$form = new Form($db);

// ═══════════════════════════════════════════════════════════
// ACTIONS
// ═══════════════════════════════════════════════════════════

// ─── Quick Create Supplier
if ($action == 'add_thirdparty') {
    $nm = trim(GETPOST('new_soc_name', 'alpha'));
    if (!empty($nm)) {
        $ns = new Societe($db);
        $ns->name = $nm;
        $ns->fournisseur = 1;
        $is_external = ($user->admin) ? GETPOST('is_external', 'int') : 0;
        $ns->client = ($is_external) ? 0 : 1; // Interne = Client=1. Externe = Client=0.
        $ns->status = 1;
        $ns->typent_id = 0;
        $ns->entity = $conf->entity;
        if (!empty($mysoc->country_id)) {
            $ns->country_id = $mysoc->country_id;
            $ns->country_code = $mysoc->country_code;
        }
        $ns->code_fournisseur = -1;
        $ns->code_client = -1;
        $r = $ns->create($user);
        if ($r > 0) {
            $db->query("UPDATE " . MAIN_DB_PREFIX . "societe SET fournisseur=0, client=0, exportation_type='EXTERNE' WHERE rowid=".$r);
            setEventMessages("Fournisseur externe créé.", null, 'mesgs');
        } else {
            setEventMessages("Erreur: " . $ns->error, null, 'errors');
        }
    }
}

// ─── Quick Create Product
if ($action == 'add_product' && $id > 0) {
    $pl = trim(GETPOST('new_prod_label', 'alpha'));
    $pr = trim(GETPOST('new_prod_ref', 'alpha'));
    if (!empty($pl)) {
        $np = new Product($db);
        $np->label = $pl;
        $np->ref = $pr ? $pr : strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $pl), 0, 10)) . '-' . date('His');
        $np->type = 0;
        $np->status = 1;
        $np->status_buy = 1;
        $np->entity = $conf->entity;
        $np->price = 0;
        $np->price_ttc = 0;
        $np->tva_tx = 0;
        $r = $np->create($user);
        if ($r > 0)
            setEventMessages("Produit créé.", null, 'mesgs');
        else
            setEventMessages("Erreur: " . $np->error, null, 'errors');
    }
    header("Location: supplier_invoice.php?id=" . $id . "&show_new_product=1");
    exit;
}

// ─── Create Invoice
if ($action == 'add_invoice') {
    $socid = GETPOST('socid', 'int');
    if ($socid <= 0 && !empty($_POST['socid']))
        $socid = (int) $_POST['socid'];
    if ($socid <= 0) {
        setEventMessages("Sélectionnez un fournisseur.", null, 'errors');
    } else {
        $chk = new Societe($db);
        if ($chk->fetch($socid) <= 0) {
            setEventMessages("Fournisseur introuvable.", null, 'errors');
            $socid = 0;
        }
    }
    if ($socid > 0) {
        $fac = new FactureFournisseur($db);
        $fac->fk_soc = $socid;
        $fac->socid = $socid;
        $fac->ref_supplier = GETPOST('ref_fournisseur', 'alpha') ?: 'IMP-' . date('ymdHis');
        $fac->date = strtotime(GETPOST('datef', 'alpha'));
        $fac->type = FactureFournisseur::TYPE_STANDARD;
        $fac->entity = $conf->entity;
        $fac->cond_reglement_id = 0;
        $fac->mode_reglement_id = 0;
        $r = $fac->create($user);
        if ($r > 0) {
            $id = (int) $r;
            $cc = GETPOST('currency_code', 'alpha');
            $er = (float) GETPOST('exchange_rate', 'alphanohtml');
            if ($er <= 0)
                $er = 1;
            $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_invoice_info (fk_facture_fourn, currency_code, exchange_rate) VALUES (" . $id . ", '" . $db->escape($cc) . "', " . $er . ")");
            header("Location: supplier_invoice.php?id=" . $id);
            exit;
        } else {
            setEventMessages("Erreur: " . $fac->error, null, 'errors');
        }
    }
}

// ─── Update Product Line (Inline Edit)
if ($action == 'update_line' && $id > 0) {
    $lid = (int) GETPOST('lid', 'int');
    $fk_product = (int) GETPOST('fk_product', 'int');
    $cbm_carton = (float) GETPOST('cbm_carton', 'alphanohtml');
    $qty_carton = (int) GETPOST('qty_carton', 'int');
    $nb_cartons = (float) GETPOST('nb_cartons', 'alphanohtml');
    $pu_devise = (float) GETPOST('pu_devise', 'alphanohtml');
    if ($qty_carton <= 0) $qty_carton = 1;
    if ($nb_cartons <= 0) $nb_cartons = 1;
    $qty = $nb_cartons * $qty_carton;
    
    $ri = $db->query("SELECT exchange_rate FROM " . MAIN_DB_PREFIX . "exportation_invoice_info WHERE fk_facture_fourn = " . (int) $id);
    $ii = ($ri !== false) ? $db->fetch_object($ri) : null;
    $rate = ($ii && $ii->exchange_rate > 0) ? (float) $ii->exchange_rate : 1;
    $pu_ht_mru = $pu_devise * $rate;
    
    $fac = new FactureFournisseur($db);
    $fac->fetch($id);
    
    $oldstatut = $fac->statut;
    if ($oldstatut > 0) $db->query("UPDATE ".MAIN_DB_PREFIX."facture_fourn SET fk_statut=0 WHERE rowid=".$id);
    $fac->statut = 0;
    
    // Delete old line, recreate with updated values
    $fac->deleteline($lid);
    $r = $fac->addline("", $pu_ht_mru, 0, 0, 0, $qty, $fk_product, 0, 0, 0, 0, 0, 'HT', 0, -1, 0, array(), null, 0, 0, '');
    
    if ($oldstatut > 0) $db->query("UPDATE ".MAIN_DB_PREFIX."facture_fourn SET fk_statut=".$oldstatut." WHERE rowid=".$id);
    
    if ($r > 0) {
        $fac->update_price(1);
        $db->query("DELETE FROM " . MAIN_DB_PREFIX . "exportation_invoice_line_info WHERE fk_facture_fourn_det = " . (int) $lid);
        $rl = $db->query("SELECT MAX(rowid) as newlid FROM " . MAIN_DB_PREFIX . "facture_fourn_det WHERE fk_facture_fourn = " . (int) $id);
        $last = $db->fetch_object($rl);
        if ($last && $last->newlid > 0) {
            $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_invoice_line_info (fk_facture_fourn_det, cbm_carton, qty_carton, nb_cartons, pu_devise) VALUES (" . (int) $last->newlid . ", " . $cbm_carton . ", " . $qty_carton . ", " . $nb_cartons . ", " . $pu_devise . ")");
        }
    }
    $edit_arg = ($oldstatut > 0) ? '&edit_mode=1' : '';
    header("Location: supplier_invoice.php?id=" . $id . $edit_arg);
    exit;
}

// ─── Delete Product Line
if ($action == 'del_line' && $id > 0) {
    $lid = (int) GETPOST('lid', 'int');
    $fac = new FactureFournisseur($db);
    $fac->fetch($id);
    
    $oldstatut = $fac->statut;
    if ($oldstatut > 0) $db->query("UPDATE ".MAIN_DB_PREFIX."facture_fourn SET fk_statut=0 WHERE rowid=".$id);
    $fac->statut = 0;
    
    $fac->deleteline($lid);
    $fac->update_price(1);
    
    if ($oldstatut > 0) $db->query("UPDATE ".MAIN_DB_PREFIX."facture_fourn SET fk_statut=".$oldstatut." WHERE rowid=".$id);
    
    header("Location: supplier_invoice.php?id=" . $id);
    exit;
}

// ─── Add Product Line
if ($action == 'add_line' && $id > 0) {
    $fac = new FactureFournisseur($db);
    $fac->fetch($id);
    $fk_product = GETPOST('fk_product', 'int');
    $cbm_carton = (float) GETPOST('cbm_carton', 'alphanohtml');
    $qty_carton = (int) GETPOST('qty_carton', 'int');
    $nb_cartons = (float) GETPOST('nb_cartons', 'alphanohtml');
    $pu_devise = (float) GETPOST('pu_devise', 'alphanohtml');
    if ($qty_carton <= 0)
        $qty_carton = 1;
    if ($nb_cartons <= 0)
        $nb_cartons = 1;
    $qty = $nb_cartons * $qty_carton;
    $ri = $db->query("SELECT exchange_rate FROM " . MAIN_DB_PREFIX . "exportation_invoice_info WHERE fk_facture_fourn = " . (int) $id);
    $ii = ($ri !== false) ? $db->fetch_object($ri) : null;
    $rate = ($ii && $ii->exchange_rate > 0) ? (float) $ii->exchange_rate : 1;
    $pu_ht_mru = $pu_devise * $rate;
    
    $oldstatut = $fac->statut;
    if ($oldstatut > 0) $db->query("UPDATE ".MAIN_DB_PREFIX."facture_fourn SET fk_statut=0 WHERE rowid=".$id);
    $fac->statut = 0;
    
    $r = $fac->addline("", $pu_ht_mru, 0, 0, 0, $qty, $fk_product, 0, 0, 0, 0, 0, 'HT', 0, -1, 0, array(), null, 0, 0, '');
    
    if ($oldstatut > 0) $db->query("UPDATE ".MAIN_DB_PREFIX."facture_fourn SET fk_statut=".$oldstatut." WHERE rowid=".$id);

    if ($r > 0) {
        $fac->update_price(1);
        $rl = $db->query("SELECT MAX(rowid) as lid FROM " . MAIN_DB_PREFIX . "facture_fourn_det WHERE fk_facture_fourn = " . (int) $id);
        $last = $db->fetch_object($rl);
        if ($last && $last->lid > 0) {
            $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_invoice_line_info (fk_facture_fourn_det, cbm_carton, qty_carton, nb_cartons, pu_devise) VALUES (" . (int) $last->lid . ", " . $cbm_carton . ", " . $qty_carton . ", " . $nb_cartons . ", " . $pu_devise . ")");
        }
    } else {
        setEventMessages("Erreur: " . $fac->error, null, 'errors');
    }
    header("Location: supplier_invoice.php?id=" . $id);
    exit;
}

// ─── Validate
if ($action == 'validate' && $id > 0) {
    $fac = new FactureFournisseur($db);
    $fac->fetch($id);
    // Debit NOUS expenses
    $re = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_invoice_expenses WHERE fk_facture_fourn = " . (int) $id . " AND expense_target = 'NOUS'");
    if ($re) {
        $acc = new Account($db);
        while ($e = $db->fetch_object($re)) {
            if ($e->fk_bank > 0) {
                $acc->fetch($e->fk_bank);
                $debit_amount = ($acc->currency_code == $e->currency_code) ? $e->amount : $e->amount_local;
                $acc->addline(dol_now(), 'EXP', '(Dépense NOUS) ' . $fac->ref . ' - ' . $e->label, -$debit_amount, 0, 0, $user);
            }
        }
    }
    if ($fac->validate($user) > 0) {
        $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_account_operations (fk_soc, operation_date, operation_type, label, debit, currency_code, amount_local, fk_origin, origin_type, fk_user_creat, date_creation) VALUES (" . (int) $fac->socid . ", '" . date('Y-m-d') . "', 'ACHAT', '" . $db->escape('Facture ' . $fac->ref) . "', " . (float) $fac->total_ht . ", 'MRU', " . (float) $fac->total_ht . ", " . (int) $id . ", 'facture_fourn', " . (int) $user->id . ", '" . $db->idate(dol_now()) . "')");
    } else {
        setEventMessages("Erreur: " . $fac->error, null, 'errors');
    }
    header("Location: supplier_invoice.php?id=" . $id);
    exit;
}


// ─── Add Expense (FOURNISSEUR = on supplier invoice, NOUS = debit our bank on VALIDATE)
if ($action == 'add_expense' && $id > 0) {
    $label = GETPOST('label', 'alpha');
    $amt = (float) GETPOST('amount_curr', 'alphanohtml');
    $curr = GETPOST('expense_curr', 'alpha');
    $rate = (float) GETPOST('expense_rate', 'alphanohtml');
    $expense_target = GETPOST('expense_target', 'alpha'); // FOURNISSEUR or NOUS
    $fk_bank_exp = ($expense_target == 'NOUS') ? (int) GETPOST('fk_bank_exp', 'int') : 0;
    $amount_local = $amt * $rate;

    if ($expense_target == 'FOURNISSEUR') {
        $fac = new FactureFournisseur($db);
        $fac->fetch($id);
        
        $oldstatut = $fac->statut;
        if ($oldstatut > 0) $db->query("UPDATE ".MAIN_DB_PREFIX."facture_fourn SET fk_statut=0 WHERE rowid=".$id);
        $fac->statut = 0;
        
        // Add as a free text service line
        $r = $fac->addline($label, $amount_local, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 'HT', 0, -1, 0, array(), null, 0, 0, '');
        
        if ($oldstatut > 0) $db->query("UPDATE ".MAIN_DB_PREFIX."facture_fourn SET fk_statut=".$oldstatut." WHERE rowid=".$id);
        
        if ($r > 0) {
            $fac->update_price(1);
            $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_invoice_expenses (fk_facture_fourn, label, amount, currency_code, exchange_rate, amount_local, fk_bank, expense_target, fk_line) VALUES (" . (int) $id . ", '" . $db->escape($label) . "', " . $amt . ", '" . $db->escape($curr) . "', " . $rate . ", " . $amount_local . ", 0, 'FOURNISSEUR', " . $r . ")");
        }
    } else {
        $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_invoice_expenses (fk_facture_fourn, label, amount, currency_code, exchange_rate, amount_local, fk_bank, expense_target) VALUES (" . (int) $id . ", '" . $db->escape($label) . "', " . $amt . ", '" . $db->escape($curr) . "', " . $rate . ", " . $amount_local . ", " . $fk_bank_exp . ", 'NOUS')");
    }
    
    header("Location: supplier_invoice.php?id=" . $id);
    exit;
}

// ─── Delete Expense
if ($action == 'del_expense' && $id > 0) {
    $eid = (int) GETPOST('eid', 'int');
    $fac = new FactureFournisseur($db);
    $fac->fetch($id);
    $re = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_invoice_expenses WHERE rowid = " . $eid);
    $e = $re ? $db->fetch_object($re) : null;
    if ($e) {
        if (!empty($e->fk_line) && $e->fk_line > 0) {
            $oldstatut = $fac->statut;
            if ($oldstatut > 0) $db->query("UPDATE ".MAIN_DB_PREFIX."facture_fourn SET fk_statut=0 WHERE rowid=".$id);
            $fac->statut = 0;
            
            $fac->deleteline($e->fk_line);
            $fac->update_price(1);
            
            if ($oldstatut > 0) $db->query("UPDATE ".MAIN_DB_PREFIX."facture_fourn SET fk_statut=".$oldstatut." WHERE rowid=".$id);
        }
        $db->query("DELETE FROM " . MAIN_DB_PREFIX . "exportation_invoice_expenses WHERE rowid = " . $eid);
    }
    header("Location: supplier_invoice.php?id=" . $id);
    exit;
}

// ─── Add Payment (native PaiementFourn → debits bank)
if ($action == 'add_payment' && $id > 0) {
    $fac = new FactureFournisseur($db);
    $fac->fetch($id);
    // Block payment on draft invoices
    if ($fac->statut == 0) {
        setEventMessages("Impossible: la facture est en brouillon. Validez-la d'abord.", null, 'errors');
        header("Location: supplier_invoice.php?id=" . $id);
        exit;
    }
    $fk_bank = (int) GETPOST('fk_bank', 'int');
    $datep = GETPOST('datep', 'alpha');
    $amount = (float) GETPOST('pay_amount', 'alphanohtml');
    $pay_rate = (float) GETPOST('pay_rate', 'alphanohtml');
    $note = GETPOST('pay_note', 'alpha');
    if ($pay_rate <= 0)
        $pay_rate = 1;
    $amount_local = $amount * $pay_rate;

    // Get bank currency
    $bank_curr = 'MRU';
    if ($fk_bank > 0) {
        $rb = $db->query("SELECT currency_code FROM " . MAIN_DB_PREFIX . "bank_account WHERE rowid = " . $fk_bank);
        $bo = ($rb !== false) ? $db->fetch_object($rb) : null;
        if ($bo && $bo->currency_code)
            $bank_curr = $bo->currency_code;
    }

    $db->begin();
    $fk_pf = 0;

    // Create native Dolibarr PaiementFourn → debits the bank
    $pf = new PaiementFourn($db);
    $pf->datepaye = strtotime($datep);
    $pf->amounts = array($id => $amount_local);
    $pf->multicurrency_amounts = array($id => $amount);
    $pf->paiementid = 6; // Virement
    $pf->num_paiement = 'EXP-' . date('ymdHis');
    $pf->note_private = $note;
    $result_pf = $pf->create($user);
    if ($result_pf > 0) {
        $fk_pf = $result_pf;
        $pf->addPaymentToBank($user, 'payment_supplier', '(EXP) ' . $note, $fk_bank, '', '');
    }

    // Also record in custom table for our enhanced UI
    $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_invoice_payments (fk_facture_fourn, fk_bank, datep, amount, currency_code, exchange_rate, amount_local, note, fk_paiement_fourn) VALUES (" . (int) $id . ", " . $fk_bank . ", '" . $db->escape($datep) . "', " . $amount . ", '" . $db->escape($bank_curr) . "', " . $pay_rate . ", " . $amount_local . ", '" . $db->escape($note) . "', " . (int) $fk_pf . ")");

    // Record credit in unified account journal
    $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_account_operations (fk_soc, operation_date, operation_type, label, credit, currency_code, exchange_rate, amount_local, fk_origin, origin_type, fk_user_creat, date_creation) VALUES (" . (int) $fac->socid . ", '" . $db->escape($datep) . "', 'PAIEMENT', '" . $db->escape('Paiement ' . $fac->ref) . "', " . $amount_local . ", '" . $db->escape($bank_curr) . "', " . $pay_rate . ", " . $amount_local . ", " . (int) $id . ", 'facture_fourn', " . (int) $user->id . ", '" . $db->idate(dol_now()) . "')");

    $db->commit();
    header("Location: supplier_invoice.php?id=" . $id);
    exit;
}

// ─── Delete Payment
if ($action == 'del_payment' && $id > 0) {
    $pid = (int) GETPOST('pid', 'int');
    $db->query("DELETE FROM " . MAIN_DB_PREFIX . "exportation_invoice_payments WHERE rowid = " . $pid . " AND fk_facture_fourn = " . (int) $id);
    header("Location: supplier_invoice.php?id=" . $id);
    exit;
}

// ═══════════════════════════════════════════════════════════
// UI RENDERING
// ═══════════════════════════════════════════════════════════
llxHeader('', "Dossier d'Achat (Import)", '');

print '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">';
print '<div id="xw">';
print '<style>
#xw{max-width:1500px;margin:0 auto;font-family:"Outfit",sans-serif;padding:20px;color:#1e2a3a}
.xc{background:#fff;border-radius:16px;padding:24px 26px;box-shadow:0 2px 18px rgba(30,42,58,.06);border:1px solid #eef2f7;margin-bottom:20px}
.xh{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #f0f4f8;gap:10px;flex-wrap:wrap}
.xh-l{display:flex;align-items:center;gap:10px}
.xi{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:15px;color:#fff;flex-shrink:0}
.xi.bl{background:linear-gradient(135deg,#3b82f6,#2563eb)}.xi.am{background:linear-gradient(135deg,#f59e0b,#d97706)}
.xi.gr{background:linear-gradient(135deg,#10b981,#059669)}.xi.in{background:linear-gradient(135deg,#6366f1,#4f46e5)}
.xi.pu{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}.xi.ro{background:linear-gradient(135deg,#f43f5e,#e11d48)}
.xt{font-size:16px;font-weight:800}.xs2{font-size:11px;color:#94a3b8;margin-top:1px}
.sg{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px}
.st{background:#f8fafc;border-radius:11px;padding:12px 14px;border:1px solid #eef2f7}
.st .l{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:3px;display:flex;align-items:center;gap:4px}
.st .v{font-size:15px;font-weight:700;color:#1e2a3a}.st .v.big{font-size:24px;color:#3b82f6}
.si{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:"Outfit",sans-serif;box-sizing:border-box;background:#fff;transition:.15s}
.si:focus{border-color:#3b82f6;outline:none;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.sl{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;display:block}
.sb{background:#3b82f6;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px;transition:.15s;display:inline-flex;align-items:center;gap:6px;text-decoration:none;white-space:nowrap}
.sb.g{background:linear-gradient(135deg,#10b981,#059669)}.sb.s{background:#64748b}.sb.sm{padding:6px 12px;font-size:12px}
.sb.ic{width:34px;height:34px;padding:0;border-radius:8px;justify-content:center;flex-shrink:0}
.sb.pu{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}.sb.ro{background:linear-gradient(135deg,#f43f5e,#e11d48)}
.sb:hover{filter:brightness(1.08);transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.1)}
.tb{width:100%;border-collapse:collapse;font-size:13px}
.tb th{background:#f8fafc;padding:9px 11px;text-align:left;color:#64748b;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #eef2f7;white-space:nowrap}
.tb td{padding:10px 11px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.tb tbody tr:last-child td{border-bottom:none}.tb tbody tr:hover td{background:#fafbfd}
.ar td{background:#f0f9ff!important;border-top:2px dashed #bfdbfe!important;padding:10px!important}
.dp{background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.bd{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;padding:4px 11px;border-radius:20px}
.bd.d{background:#fef9c3;color:#a16207}.bd.v{background:#dcfce7;color:#15803d}
.bd.p{background:#dbeafe;color:#1d4ed8}.bd.x{background:#fee2e2;color:#b91c1c}
.bd.sh{background:#f0fdf4;color:#166534}.bd.ns{background:#fef3c7;color:#92400e}.bd.ps{background:#dbeafe;color:#1d4ed8}
.qb{background:#f0f9ff;border:1.5px dashed #7dd3fc;padding:16px 18px;border-radius:11px;margin-bottom:16px;display:none}
.qb-t{font-size:13px;font-weight:700;color:#0369a1;margin-bottom:12px;display:flex;align-items:center;gap:7px}
.sw{display:flex;align-items:center;gap:5px;min-width:180px}.sw select{flex:1;min-width:0}
.sum{background:linear-gradient(135deg,#1e2a3a,#2d3f55);border-radius:16px;padding:24px 28px;color:#fff;margin-bottom:20px}
.sum .sl2{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#93c5fd;margin-bottom:3px}
.sum .sv{font-size:22px;font-weight:800}.sum .sv.big{font-size:32px;color:#60a5fa}
select.flat,#socid{padding:9px 12px!important;border:1.5px solid #e2e8f0!important;border-radius:8px!important;font-size:13px!important;font-family:"Outfit",sans-serif!important;background:#fff!important}
.s0{background:#fef9c3;color:#a16207}.s1{background:#dcfce7;color:#15803d}.s2{background:#dbeafe;color:#1d4ed8}.s3{background:#fee2e2;color:#b91c1c}
.del{color:#ef4444;font-size:13px;cursor:pointer}.del:hover{color:#dc2626}
</style>';

// ═══════════════════════════════════════════════════════════
// PAGE A: LIST / CREATE
// ═══════════════════════════════════════════════════════════
if ($id <= 0) {
    print '<div class="xc">';
    print '<div class="xh"><div class="xh-l"><div class="xi bl"><i class="fa-solid fa-file-invoice"></i></div><div><div class="xt">Dossier d\'Achat — Facture Import</div></div></div></div>';

    // Quick supplier
    print '<div id="qbox_s" class="qb"><div class="qb-t"><i class="fa-solid fa-building-circle-arrow-right"></i> Nouveau Fournisseur</div>';
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '"><input type="hidden" name="action" value="add_thirdparty"><input type="hidden" name="token" value="' . newToken() . '">';
    print '<div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">';
    print '<div style="flex:2;min-width:180px;"><label class="sl">Nom *</label><input type="text" name="new_soc_name" class="si" placeholder="Shenzhen Trading Co." required></div>';
    print '<div><button type="submit" class="sb g"><i class="fa-solid fa-check"></i> Créer Fournisseur Externe</button></div></div></form></div>';

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '"><input type="hidden" name="action" value="add_invoice"><input type="hidden" name="token" value="' . newToken() . '">';
    print '<div class="sg">';
    print '<div><label class="sl">Fournisseur Externe * <a href="#" onclick="var b=document.getElementById(\'qbox_s\');b.style.display=(b.style.display==\'none\'||!b.style.display?\'block\':\'none\');return false;" style="color:#3b82f6;font-size:12px;"><i class="fa-solid fa-circle-plus"></i> Nouveau</a></label>';
    
    // Custom select for external suppliers
    print '<select name="socid" class="si" required><option value="">-- Sélectionner fournisseur --</option>';
    $rsoc = $db->query("SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int)$conf->entity." AND exportation_type='EXTERNE' ORDER BY nom ASC");
    while ($rsoc && $s = $db->fetch_object($rsoc)) {
        print '<option value="'.$s->rowid.'">'.dol_escape_htmltag($s->nom).'</option>';
    }
    print '</select></div>';
    print '<div><label class="sl">Date *</label><input type="date" name="datef" value="' . date('Y-m-d') . '" class="si" required></div>';
    print '<div><label class="sl">Réf. Pro-forma</label><input type="text" name="ref_fournisseur" class="si" placeholder="PI-2024-001"></div>';
    print '<div><label class="sl">Devise Source</label><select name="currency_code" class="si"><option value="RMB">RMB</option><option value="EUR">EUR</option><option value="USD">USD</option><option value="MRU" selected>MRU</option></select></div>';
    print '<div><label class="sl">Taux → MRU</label><input type="number" step="0.0001" min="0.0001" name="exchange_rate" value="1.0000" class="si" required></div>';
    print '</div>';
    print '<div style="margin-top:18px;text-align:right;"><button type="submit" class="sb"><i class="fa-solid fa-folder-open"></i> Créer</button></div></form></div>';

    // Recent list
    $sqlList = "SELECT ff.rowid, ff.ref, s.nom as sname, ff.datef, ff.total_ht, ff.fk_statut, ei.currency_code, ei.exchange_rate FROM " . MAIN_DB_PREFIX . "facture_fourn ff INNER JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = ff.fk_soc LEFT JOIN " . MAIN_DB_PREFIX . "exportation_invoice_info ei ON ei.fk_facture_fourn = ff.rowid WHERE ff.entity = " . (int) $conf->entity . " AND s.exportation_type = 'EXTERNE' ORDER BY ff.rowid DESC LIMIT 5000";
    $rl = $db->query($sqlList);
    $stcfg = array(0 => array('Brouillon', 's0'), 1 => array('Validée', 's1'), 2 => array('Payée', 's2'), 3 => array('Abandonnée', 's3'));
    print '<div class="xc"><div class="xh"><div class="xh-l"><div class="xi in"><i class="fa-solid fa-list-ul"></i></div><div><div class="xt">Dossiers Récents</div></div></div>';
    print '<div><a href="supplier_payment.php" class="sb g"><i class="fa-solid fa-money-bills-wave"></i> Paiement en Masse</a></div></div>';
    print '<table class="tb" id="dossiers-recents" data-pg="1"><thead><tr><th>Réf</th><th>Fournisseur</th><th>Date</th><th>Devise</th><th>Total</th><th>Statut</th><th></th></tr></thead><tbody>';
    $has = false;
    if ($rl) {
        while ($r = $db->fetch_object($rl)) {
            $has = true;
            $sc = isset($stcfg[$r->fk_statut]) ? $stcfg[$r->fk_statut] : array($r->fk_statut, '');
            $cc = $r->currency_code ?: 'MRU';
            $er = $r->exchange_rate ?: 1;
            $td = ($er > 0) ? ($r->total_ht / $er) : $r->total_ht;
            print '<tr><td><a href="?id=' . $r->rowid . '" style="font-weight:700;color:#3b82f6;">' . htmlspecialchars($r->ref) . '</a></td>';
            print '<td>' . dol_escape_htmltag($r->sname) . '</td><td>' . dol_print_date($r->datef, 'day') . '</td>';
            print '<td><span class="dp">' . $cc . ' &times; ' . number_format($er, 2) . '</span></td>';
            print '<td><strong>' . price($td) . ' ' . $cc . '</strong></td>';
            print '<td><span class="bd ' . $sc[1] . '">' . $sc[0] . '</span></td>';
            print '<td><a href="?id=' . $r->rowid . '" class="sb sm"><i class="fa-solid fa-arrow-right"></i></a></td></tr>';
        }
    }
    if (!$has)
        print '<tr><td colspan="7" style="text-align:center;padding:28px;color:#94a3b8;"><i class="fa-solid fa-inbox" style="font-size:26px;display:block;margin-bottom:8px;"></i>Aucun dossier</td></tr>';
    print '</tbody></table></div>';

} else {
    // ═══════════════════════════════════════════════════════════
// PAGE B: INVOICE DETAIL
// ═══════════════════════════════════════════════════════════
    $fac = new FactureFournisseur($db);
    $fac->fetch($id);
    $fac->fetch_lines();
    $fac->fetch_thirdparty();
    $ri = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_invoice_info WHERE fk_facture_fourn = " . (int) $id);
    $ii = ($ri !== false) ? $db->fetch_object($ri) : null;
    $CC = ($ii && $ii->currency_code) ? $ii->currency_code : 'MRU';
    $ER = ($ii && $ii->exchange_rate > 0) ? (float) $ii->exchange_rate : 1;
    $show_np = GETPOST('show_new_product', 'int');
    $sm = array(0 => array('Brouillon', 'd'), 1 => array('Validée', 'v'), 2 => array('Payée', 'p'), 3 => array('Abandonnée', 'x'));
    $si = isset($sm[$fac->statut]) ? $sm[$fac->statut] : array($fac->statut, '');
    $total_dev = ($ER > 0) ? ($fac->total_ht / $ER) : $fac->total_ht;

    // Get bank accounts for payment section (build JS map)
    $banks = array();
    $rb = $db->query("SELECT rowid, ref, label, currency_code FROM " . MAIN_DB_PREFIX . "bank_account WHERE entity = " . (int) $conf->entity . " AND clos = 0 ORDER BY label");
    while ($rb && ($bk = $db->fetch_object($rb))) {
        $banks[] = $bk;
    }

    // ── Back + Edit Mode + PDF
    $edit_mode = GETPOST('edit_mode', 'int');
    $can_edit = ($fac->statut == 0 || $edit_mode == 1);
    
    print '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">';
    print '<div style="display:flex;gap:12px;align-items:center;">';
    print '<a href="' . $_SERVER['PHP_SELF'] . '" style="display:inline-flex;align-items:center;gap:6px;color:#64748b;font-weight:600;font-size:13px;text-decoration:none;"><i class="fa-solid fa-arrow-left"></i> Retour</a>';
    if ($fac->statut > 0 && !$can_edit) {
        print '<a href="?id='.$id.'&edit_mode=1" class="sb" style="background:#f59e0b;padding:6px 14px;font-size:12px;"><i class="fa-solid fa-unlock"></i> Modifier la facture</a>';
    } elseif ($fac->statut > 0 && $can_edit) {
        print '<a href="?id='.$id.'" class="sb" style="background:#64748b;padding:6px 14px;font-size:12px;"><i class="fa-solid fa-lock"></i> Terminer les modifications</a>';
    }
    print '</div>';
    print '<a href="print_invoice.php?id=' . $id . '" target="_blank" class="sb g"><i class="fa-solid fa-print"></i> Imprimer PDF</a>';
    print '</div>';

    // ── Header card
    print '<div class="xc"><div class="xh"><div class="xh-l"><div class="xi bl"><i class="fa-solid fa-file-invoice-dollar"></i></div>';
    print '<div><div class="xt">Facture ' . htmlspecialchars($fac->ref) . '</div></div></div>';
    print '<span class="bd ' . $si[1] . '"><i class="fa-solid fa-circle fa-xs"></i> ' . $si[0] . '</span></div>';
    print '<div class="sg">';
    print '<div class="st"><div class="l"><i class="fa-solid fa-building"></i> Fournisseur</div><div class="v">' . dol_escape_htmltag($fac->thirdparty->name) . '</div></div>';
    print '<div class="st"><div class="l"><i class="fa-solid fa-calendar-day"></i> Date</div><div class="v">' . dol_print_date($fac->date, 'day') . '</div></div>';
    print '<div class="st"><div class="l"><i class="fa-solid fa-coins"></i> Devise & Taux</div><div class="v"><span class="dp" style="font-size:14px;">' . $CC . '</span> &times; ' . number_format($ER, 4) . '</div></div>';
    print '<div class="st"><div class="l"><i class="fa-solid fa-money-bill-wave"></i> Total facturé (' . $CC . ')</div><div class="v big" style="color:#059669;">' . price($total_dev) . ' ' . $CC . '</div></div>';
    print '</div></div>';

    $total_products_dev = 0;
    // ── Products card
    print '<div class="xc"><div class="xh"><div class="xh-l"><div class="xi am"><i class="fa-solid fa-boxes-stacked"></i></div>';
    print '<div><div class="xt">Produits</div><div class="xs2">En ' . $CC . ' — CBM, cartons, expédition</div></div></div></div>';
    // Quick product
    print '<div id="qbox_p" class="qb" style="' . ($show_np ? 'display:block;' : '') . '"><div class="qb-t"><i class="fa-solid fa-box-open"></i> Nouveau Produit</div>';
    print '<form method="POST" action="?id=' . $id . '"><input type="hidden" name="action" value="add_product"><input type="hidden" name="token" value="' . newToken() . '">';
    print '<div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;"><div style="flex:2;min-width:160px;"><label class="sl">Libellé *</label><input type="text" name="new_prod_label" class="si" required></div>';
    print '<div style="flex:1;min-width:120px;"><label class="sl">Réf</label><input type="text" name="new_prod_ref" class="si"></div>';
    print '<div><button type="submit" class="sb g"><i class="fa-solid fa-check"></i></button></div></div></form></div>';
    print '<div style="overflow-x:auto;"><table class="tb"><thead><tr><th>Produit</th><th>CBM/CTN</th><th>U/CTN</th><th>Nb Crt</th><th>Qté</th><th>P.U (' . $CC . ')</th><th>Total (' . $CC . ')</th><th>Expédition</th><th></th></tr></thead><tbody>';
    foreach ($fac->lines as $line) {
        // Skip service lines that were added as FOURNISSEUR expenses
        if ($line->product_type == 1 || $line->fk_product == 0) continue;

        $rli = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_invoice_line_info WHERE fk_facture_fourn_det = " . (int) $line->id);
        $li = ($rli !== false) ? $db->fetch_object($rli) : null;
        $cbm_c = $li ? (float) $li->cbm_carton : 0;
        $qty_c = $li ? (int) $li->qty_carton : 1;
        $nb_c = $li ? (float) $li->nb_cartons : 0;
        $pu_d = $li ? (float) $li->pu_devise : (($ER > 0) ? ($line->pu_ht / $ER) : $line->pu_ht);
        $total_d = $pu_d * $line->qty;
        $total_products_dev += $total_d;
        $rsh = $db->query("SELECT COALESCE(SUM(sl.nb_cartons), 0) as shipped FROM " . MAIN_DB_PREFIX . "exportation_shipment_lines sl WHERE sl.fk_facture_fourn_det = " . (int) $line->id);
        $shd = ($rsh !== false) ? $db->fetch_object($rsh) : null;
        $shipped_c = $shd ? (float) $shd->shipped : 0;
        if ($nb_c <= 0)
            $sb = '<span class="bd ns"><i class="fa-solid fa-circle-minus fa-xs"></i> N/A</span>';
        elseif ($shipped_c >= $nb_c)
            $sb = '<span class="bd sh"><i class="fa-solid fa-circle-check fa-xs"></i> ' . (int) $shipped_c . '/' . (int) $nb_c . '</span>';
        elseif ($shipped_c > 0)
            $sb = '<span class="bd ps"><i class="fa-solid fa-truck fa-xs"></i> ' . (int) $shipped_c . '/' . (int) $nb_c . '</span>';
        else
            $sb = '<span class="bd ns"><i class="fa-solid fa-clock fa-xs"></i> 0/' . (int) $nb_c . '</span>';
        if ($action == 'edit_line' && GETPOST('lid', 'int') == $line->id) {
            print '<tr class="ar"><form method="POST" action="?id=' . $id . '"><input type="hidden" name="action" value="update_line">';
            print '<input type="hidden" name="lid" value="' . $line->id . '"><input type="hidden" name="fk_product" value="'.$line->fk_product.'"><input type="hidden" name="token" value="' . newToken() . '">';
            print '<td><strong style="color:#1e293b;">' . dol_escape_htmltag($line->product_label ?: $line->description) . '</strong></td>';
            print '<td><input type="number" step="0.0001" name="cbm_carton" value="'.$cbm_c.'" class="si" style="width:75px;" required></td>';
            print '<td><input type="number" step="1" min="1" name="qty_carton" value="'.$qty_c.'" class="si" style="width:70px;" required></td>';
            print '<td><input type="number" step="1" min="1" name="nb_cartons" value="'.$nb_c.'" class="si" style="width:65px;" required></td>';
            print '<td><span style="color:#94a3b8;font-size:11px;">auto</span></td>';
            print '<td><input type="number" step="0.01" name="pu_devise" value="'.$pu_d.'" class="si" style="width:90px;" required></td>';
            print '<td></td><td></td>';
            print '<td style="white-space:nowrap;"><button type="submit" class="sb g" style="padding:6px 10px;margin-right:4px;"><i class="fa-solid fa-save"></i></button>';
            print '<a href="?id='.$id.($edit_mode ? '&edit_mode=1' : '').'" class="sb s" style="padding:6px 10px;"><i class="fa-solid fa-times"></i></a></td></form></tr>';
        } else {
            print '<tr><td><strong>' . dol_escape_htmltag($line->product_label ?: $line->description) . '</strong>';
            if ($line->product_ref)
                print ' <span style="color:#94a3b8;font-size:10px;">(' . dol_escape_htmltag($line->product_ref) . ')</span>';
            print '</td><td>' . ($cbm_c > 0 ? number_format($cbm_c, 4) : '—') . '</td>';
            print '<td>' . ($qty_c > 0 ? $qty_c : '—') . '</td><td><strong>' . ($nb_c > 0 ? number_format($nb_c, 0) : '—') . '</strong></td>';
            print '<td>' . number_format($line->qty, 0) . '</td><td>' . price($pu_d) . '</td><td><strong>' . price($total_d) . '</strong></td>';
            print '<td>' . $sb . '</td>';
            if ($can_edit) {
                print '<td style="white-space:nowrap;">';
                print '<a href="?id='.$id.'&action=edit_line&lid='.$line->id.($edit_mode ? '&edit_mode=1' : '').'" style="color:#3b82f6;margin-right:8px;"><i class="fa-solid fa-pen"></i></a>';
                print '<a href="?id='.$id.'&action=del_line&lid='.$line->id.'&token='.newToken().'" class="del" onclick="return confirm(\'Voulez-vous vraiment supprimer ce produit de la facture ?\');"><i class="fa-solid fa-trash-can"></i></a></td>';
            } else {
                print '<td></td>';
            }
            print '</tr>';
        }
    }
    
    // Always show the product addition form IF in edit mode or draft
    if ($can_edit) {
        print '<tr class="ar"><form method="POST" action="?id=' . $id . '"><input type="hidden" name="action" value="add_line"><input type="hidden" name="token" value="' . newToken() . '">';
        print '<td><div class="sw"><select name="fk_product" class="si" style="width:100%;" required><option value="">-- Produit --</option>';
        $rp = $db->query("SELECT p.rowid, p.label, p.stock FROM " . MAIN_DB_PREFIX . "product p WHERE p.entity IN (0," . (int) $conf->entity . ") AND p.fk_product_type = 0 ORDER BY p.label");
        while ($rp !== false && ($pr = $db->fetch_object($rp))) {
            print '<option value="' . $pr->rowid . '">' . dol_escape_htmltag($pr->label) . ' [' . (int) $pr->stock . ']</option>';
        }
        print '</select>';
        print '<button type="button" class="sb ic" style="background:linear-gradient(135deg,#10b981,#059669);" onclick="var p=document.getElementById(\'qbox_p\');p.style.display=(p.style.display==\'none\'||!p.style.display?\'block\':\'none\');return false;"><i class="fa-solid fa-plus"></i></button></div></td>';
        print '<td><input type="number" step="0.0001" name="cbm_carton" placeholder="0.11" class="si" style="width:75px;" required></td>';
        print '<td><input type="number" step="1" min="1" name="qty_carton" placeholder="250" class="si" style="width:70px;" required></td>';
        print '<td><input type="number" step="1" min="1" name="nb_cartons" placeholder="10" class="si" style="width:65px;" required></td>';
        print '<td><span style="color:#94a3b8;font-size:11px;">auto</span></td>';
        print '<td><input type="number" step="0.01" name="pu_devise" placeholder="P.U ' . $CC . '" class="si" style="width:90px;" required></td>';
        print '<td></td><td><button type="submit" class="sb"><i class="fa-solid fa-plus"></i></button></td>';
        print '</form></tr>';
    }
    print '</tbody></table></div></div>';

    // ── Expenses
    $total_exp = 0;
    $total_exp_dev = 0;
    $rese = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_invoice_expenses WHERE fk_facture_fourn = " . (int) $id);
    $expenses = array();
    while ($rese !== false && ($e = $db->fetch_object($rese))) {
        $total_exp += (float) $e->amount_local;
        // Convert expense to invoice currency ($CC):
        // - If expense is already in $CC → use amount directly
        // - If expense is MRU and $CC != MRU → divide by ER (inverse)
        // - Otherwise (other foreign currency) → amount_local is in MRU, divide by ER
        if ($e->currency_code == $CC) {
            $total_exp_dev += (float) $e->amount;
        } elseif ($CC != 'MRU' && $ER > 0) {
            // amount_local is in MRU, convert to invoice currency
            $total_exp_dev += (float) $e->amount_local / $ER;
        } else {
            // $CC = MRU → just use amount_local
            $total_exp_dev += (float) $e->amount_local;
        }
        $expenses[] = $e;
    }
    print '<div class="xc"><div class="xh"><div class="xh-l"><div class="xi gr"><i class="fa-solid fa-receipt"></i></div><div><div class="xt">Dépenses</div><div class="xs2">Pour fournisseur (dans la facture) ou pour nous (décaissé banque)</div></div></div>';
    print '<span style="font-size:12px;color:#94a3b8;">Total: <strong style="color:#ef4444;">' . price($total_exp_dev) . ' ' . $CC . '</strong></span></div>';
    print '<table class="tb"><thead><tr><th>Description</th><th>Montant</th><th>Taux</th><th>Total (MRU)</th><th>Type</th><th>Banque</th></tr></thead><tbody>';
    foreach ($expenses as $e) {
        $exp_bank_lbl = '—';
        if ($e->fk_bank > 0) {
            $reb = $db->query("SELECT label, currency_code FROM " . MAIN_DB_PREFIX . "bank_account WHERE rowid = " . (int) $e->fk_bank);
            $ebo = ($reb !== false) ? $db->fetch_object($reb) : null;
            if ($ebo)
                $exp_bank_lbl = dol_escape_htmltag($ebo->label) . ' <span class="dp">' . $ebo->currency_code . '</span>';
        }
        $tgt = ($e->expense_target == 'FOURNISSEUR') ? '<span style="background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;">Fournisseur</span>' : '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;">Pour nous</span>';
        print '<tr><td><strong>' . dol_escape_htmltag($e->label) . '</strong></td>';
        print '<td>' . price($e->amount) . ' <span class="dp">' . dol_escape_htmltag($e->currency_code) . '</span></td>';
        print '<td>&times; ' . number_format($e->exchange_rate, 4) . '</td>';
        print '<td><strong>' . price($e->amount_local) . ' MRU</strong></td>';
        print '<td>' . $tgt . '</td>';
        print '<td>' . $exp_bank_lbl . '</td>';
        if ($can_edit) {
            print '<td><a href="?id='.$id.'&action=del_expense&eid='.$e->rowid.'&token='.newToken().'" class="del" onclick="return confirm(\'Voulez-vous supprimer cette dépense ?\');"><i class="fa-solid fa-trash-can"></i></a></td>';
        } else {
            print '<td></td>';
        }
        print '</tr>';
    }
    
    if ($can_edit) {
        // Default rate: if invoice currency is MRU → 1, else ER
        $default_exp_rate = ($CC == 'MRU') ? '1.0000' : number_format($ER, 4);
        print '<tr class="ar"><form method="POST" action="?id=' . $id . '"><input type="hidden" name="action" value="add_expense"><input type="hidden" name="token" value="' . newToken() . '">';
        print '<td><input type="text" name="label" placeholder="Transport, Commission..." class="si" required></td>';
        print '<td style="display:flex;gap:4px;align-items:center;"><input type="number" step="0.01" name="amount_curr" placeholder="Montant" class="si" style="width:100px;" required>';
        $currs = array('RMB', 'EUR', 'USD', 'MRU');
        print '<select name="expense_curr" id="exp_curr" class="si" style="width:70px;" onchange="toggleExpRate(this.value)">';
        foreach ($currs as $c) {
            $sel = ($c == $CC) ? ' selected' : '';
            print '<option' . $sel . '>' . $c . '</option>';
        }
        print '</select></td>';
        // Rate cell — hidden if MRU is selected as expense currency
        $rate_hidden = ($CC == 'MRU') ? ' style="display:none;"' : '';
        print '<td id="td_exp_rate"' . $rate_hidden . '><input type="number" step="0.0001" name="expense_rate" id="inp_exp_rate" value="' . $default_exp_rate . '" class="si" style="width:90px;"></td>';
        print '<td></td>';
        print '<td><select name="expense_target" id="exp_target" class="si" style="width:110px;" onchange="toggleBankExp()"><option value="NOUS">Pour nous</option><option value="FOURNISSEUR">Fournisseur</option></select></td>';
        print '<td id="td_bank_exp" style="display:flex;gap:4px;align-items:center;"><select name="fk_bank_exp" id="sel_bank_exp" class="si" style="width:130px;"><option value="">-- Banque --</option>';
        foreach ($banks as $bk) {
            print '<option value="' . $bk->rowid . '">' . dol_escape_htmltag($bk->label) . ' (' . $bk->currency_code . ')</option>';
        }
        print '</select><button type="submit" class="sb"><i class="fa-solid fa-plus"></i></button></td>';
        print '<td style="background:transparent;border:0;"></td>'; // empty cell for delete column
        print '</form></tr>';
        print '<script>
// Toggle bank field based on target
function toggleBankExp(){
    var t=document.getElementById("exp_target").value;
    var b=document.getElementById("sel_bank_exp");
    if(t=="FOURNISSEUR"){b.removeAttribute("required");b.value="";b.parentNode.style.opacity="0.3";}
    else{b.setAttribute("required","required");b.parentNode.style.opacity="1";}
}
// Toggle rate field based on currency: MRU needs no rate (always 1)
function toggleExpRate(curr){
    var td = document.getElementById("td_exp_rate");
    var inp = document.getElementById("inp_exp_rate");
    if(curr === "MRU"){
        td.style.display = "none";
        inp.value = "1.0000";
        inp.removeAttribute("required");
    } else {
        td.style.display = "";
        inp.setAttribute("required","required");
        // Suggest the invoice exchange rate
        if(parseFloat(inp.value) <= 0 || inp.value === "1.0000") inp.value = "' . number_format($ER, 4) . '";
    }
}
// Init on load
toggleExpRate(document.getElementById("exp_curr") ? document.getElementById("exp_curr").value : "' . $CC . '");
</script>';
    }
    print '</tbody></table></div>';

    // ═══════════════════════════════════════════════════════
    // ── PAYMENTS SECTION
    // ═══════════════════════════════════════════════════════
    $total_paid = 0;
    $total_paid_dev = 0;
    $rp = $db->query("SELECT p.*, ba.label as bank_label, ba.currency_code as bank_currency FROM " . MAIN_DB_PREFIX . "exportation_invoice_payments p LEFT JOIN " . MAIN_DB_PREFIX . "bank_account ba ON ba.rowid = p.fk_bank WHERE p.fk_facture_fourn = " . (int) $id . " ORDER BY p.datep ASC");
    $payments = array();
    while ($rp !== false && ($py = $db->fetch_object($rp))) {
        $total_paid += (float) $py->amount_local;
        $total_paid_dev += (float) $py->amount;
        $payments[] = $py;
    }
    $grand_dev = $total_dev; // Removed + $total_exp_dev
    $remaining_dev = $grand_dev - $total_paid_dev;
    $grand_mru = $fac->total_ht; // Removed + $total_exp
    $remaining_mru = $grand_mru - $total_paid;

    print '<div class="xc"><div class="xh"><div class="xh-l"><div class="xi pu"><i class="fa-solid fa-hand-holding-dollar"></i></div>';
    print '<div><div class="xt">Paiements</div><div class="xs2">Chaque paiement a son propre taux de change</div></div></div>';
    // Remaining badge
    if ($remaining_dev > 0.01)
        print '<span class="bd ns"><i class="fa-solid fa-clock fa-xs"></i> Reste: ' . price($remaining_dev) . ' ' . $CC . '</span>';
    else
        print '<span class="bd sh"><i class="fa-solid fa-circle-check fa-xs"></i> Soldé</span>';
    print '</div>';

    print '<table class="tb"><thead><tr><th>Date</th><th>Banque</th><th>Montant (' . $CC . ')</th><th>Taux</th><th>Montant (MRU)</th><th>Note</th><th></th></tr></thead><tbody>';
    foreach ($payments as $py) {
        $same_curr = ($py->bank_currency == $CC || $py->currency_code == $CC);
        print '<tr><td>' . dol_print_date($db->jdate($py->datep), 'day') . '</td>';
        print '<td><strong>' . dol_escape_htmltag($py->bank_label ?: '—') . '</strong> <span class="dp">' . dol_escape_htmltag($py->bank_currency ?: $py->currency_code) . '</span></td>';
        print '<td><strong>' . price($py->amount) . '</strong></td>';
        print '<td>' . ($same_curr ? '<span style="color:#94a3b8;">—</span>' : '&times; ' . number_format($py->exchange_rate, 4)) . '</td>';
        print '<td><strong>' . price($py->amount_local) . ' MRU</strong></td>';
        print '<td style="color:#94a3b8;font-size:12px;">' . dol_escape_htmltag($py->note ?: '') . '</td>';
        print '<td><a href="?id=' . $id . '&action=del_payment&pid=' . $py->rowid . '&token=' . newToken() . '" class="del" onclick="return confirm(\'Supprimer ?\');"><i class="fa-solid fa-trash-can"></i></a></td>';
        print '</tr>';
    }

    // Add payment form — only for validated invoices
    if ($fac->statut >= 1) {
        print '<tr class="ar"><form method="POST" action="?id=' . $id . '">';
        print '<input type="hidden" name="action" value="add_payment"><input type="hidden" name="token" value="' . newToken() . '">';
        print '<td><input type="date" name="datep" value="' . date('Y-m-d') . '" class="si" style="width:130px;" required></td>';
        print '<td><select name="fk_bank" id="sel_bank" class="si" style="width:160px;" onchange="bankChanged()" required>';
        print '<option value="">-- Banque --</option>';
        foreach ($banks as $bk) {
            print '<option value="' . $bk->rowid . '" data-curr="' . dol_escape_htmltag($bk->currency_code) . '">' . dol_escape_htmltag($bk->label) . ' (' . $bk->currency_code . ')</option>';
        }
        print '</select></td>';
        print '<td><input type="number" step="0.01" name="pay_amount" placeholder="' . $CC . '" class="si" style="width:100px;" required></td>';
        print '<td id="td_rate"><input type="number" step="0.0001" name="pay_rate" id="inp_rate" value="' . number_format($ER, 4) . '" class="si" style="width:90px;" required></td>';
        print '<td></td>';
        print '<td><input type="text" name="pay_note" placeholder="Note..." class="si" style="width:100px;"></td>';
        print '<td><button type="submit" class="sb g"><i class="fa-solid fa-plus"></i></button></td>';
        print '</form></tr>';
    } else {
        print '<tr><td colspan="7" style="text-align:center;padding:16px;color:#f59e0b;font-weight:600;"><i class="fa-solid fa-triangle-exclamation"></i> Validez la facture pour pouvoir ajouter des paiements</td></tr>';
    }
    print '</tbody></table>';

    // JS: auto-detect bank currency
    print '<script>
    var INV_CURR = "' . $CC . '";
    var INV_RATE = ' . $ER . ';
    function bankChanged() {
        var sel = document.getElementById("sel_bank");
        var opt = sel.options[sel.selectedIndex];
        var bc = opt ? opt.getAttribute("data-curr") : "";
        var tdRate = document.getElementById("td_rate");
        var inpRate = document.getElementById("inp_rate");
        if (bc && bc === INV_CURR) {
            // Same currency: rate = invoice rate, field subtle
            inpRate.value = INV_RATE.toFixed(4);
            tdRate.style.opacity = "0.5";
        } else {
            // Different currency: user must enter rate
            tdRate.style.opacity = "1";
            inpRate.focus();
        }
    }
    </script>';
    print '</div>';

    // ── Summary bar
    print '<div class="sum">';
    print '<div style="display:flex;justify-content:space-around;flex-wrap:wrap;gap:18px;text-align:center;padding:4px 0;">';
    print '<div><div class="sl2"><i class="fa-solid fa-boxes-stacked"></i> Total Produits</div><div class="sv">' . price($total_products_dev) . ' ' . $CC . '</div></div>';
    print '<div><div class="sl2"><i class="fa-solid fa-receipt"></i> Dépenses (Pour NOUS)</div><div class="sv">' . price($total_exp_dev) . ' ' . $CC . '</div><div style="font-size:10px;color:#94a3b8;max-width:130px;line-height:1.2;margin:2px auto 0;">Non inclus dans la facture</div></div>';
    print '<div><div class="sl2" style="color:#059669;"><i class="fa-solid fa-sigma"></i> TOTAL FACTURE</div><div class="sv big" style="color:#059669;">' . price($grand_dev) . ' ' . $CC . '</div>';
    print '<div style="font-size:12px;color:#34d399;">' . price($grand_mru) . ' MRU</div></div>';
    print '<div style="border-left:1px solid rgba(255,255,255,.15);padding-left:18px;">';
    print '<div class="sl2" style="color:#86efac;"><i class="fa-solid fa-hand-holding-dollar"></i> PAYÉ</div><div class="sv" style="color:#86efac;">' . price($total_paid_dev) . ' ' . $CC . '</div></div>';
    print '<div style="border-left:1px solid rgba(255,255,255,.15);padding-left:18px;">';
    $rc = ($remaining_dev > 0.01) ? 'color:#fca5a5;' : 'color:#86efac;';
    print '<div class="sl2" style="' . $rc . '"><i class="fa-solid fa-scale-unbalanced"></i> RESTE</div>';
    print '<div class="sv" style="' . $rc . '">' . price($remaining_dev) . ' ' . $CC . '</div></div>';
    print '</div>';

    if ($fac->statut == 0) {
        print '<div style="margin-top:20px;text-align:center;"><form method="POST" action="?id=' . $id . '" style="display:inline;">';
        print '<input type="hidden" name="action" value="validate"><input type="hidden" name="token" value="' . newToken() . '">';
        print '<button type="submit" class="sb g" style="font-size:14px;padding:12px 32px;"><i class="fa-solid fa-circle-check"></i> Valider</button></form></div>';
    }
    print '</div>';
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

llxFooter();
$db->close();
