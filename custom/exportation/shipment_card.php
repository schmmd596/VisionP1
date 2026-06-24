<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

$langs->loadLangs(array("facture", "companies", "stocks", "orders", "exportation@exportation"));

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');

// ═══════════════════════════════════════════════════════════════
// ACTIONS
// ═══════════════════════════════════════════════════════════════

// Migration for shipment_expenses
$db->query("ALTER TABLE " . MAIN_DB_PREFIX . "exportation_shipment_expenses ADD COLUMN fk_bank integer DEFAULT 0");

// ─── Create Shipment (no warehouse needed at creation)
if ($action == 'add_header') {
    $ref = 'SHP-' . date('ymd') . '-' . mt_rand(100, 999);
    $container = GETPOST('container_number', 'alpha');
    $tracking = GETPOST('tracking_number', 'alpha');
    $shipping_date = GETPOST('shipping_date', 'alpha');
    $arrival_date = GETPOST('arrival_date', 'alpha');
    $prix_cbm = (float) GETPOST('prix_cbm', 'alphanohtml');
    $note = GETPOST('note_public', 'alpha');

    $db->begin();
    $sql = "INSERT INTO " . MAIN_DB_PREFIX . "exportation_shipment ";
    $sql .= "(ref, container_number, tracking_number, shipping_date, arrival_date, fk_warehouse, prix_cbm, note_public, fk_user_creat, entity, date_creation) VALUES (";
    $sql .= "'" . $db->escape($ref) . "', '" . $db->escape($container) . "', '" . $db->escape($tracking) . "', ";
    $sql .= "'" . $db->escape($shipping_date) . "', " . ($arrival_date ? "'" . $db->escape($arrival_date) . "'" : "NULL") . ", ";
    $sql .= "0, " . $prix_cbm . ", '" . $db->escape($note) . "', " . (int) $user->id . ", " . (int) $conf->entity . ", '" . $db->idate(dol_now()) . "')";

    if ($db->query($sql)) {
        $id = $db->last_insert_id(MAIN_DB_PREFIX . "exportation_shipment");
        $db->commit();
        header("Location: shipment_card.php?id=" . $id);
        exit;
    } else {
        $db->rollback();
        setEventMessages("Erreur: " . $db->lasterror(), null, 'errors');
    }
}

// ─── Update Prix CBM
if ($action == 'update_prix_cbm' && $id > 0) {
    $new_prix = (float) GETPOST('prix_cbm', 'alphanohtml');
    $db->query("UPDATE " . MAIN_DB_PREFIX . "exportation_shipment SET prix_cbm = " . $new_prix . " WHERE rowid = " . $id);
    header("Location: shipment_card.php?id=" . $id);
    exit;
}

// ─── Link Invoice Lines (batch from a single invoice, with per-line warehouse)
if ($action == 'link_lines' && $id > 0) {
    $fk_invoice = (int) GETPOST('fk_invoice', 'int');
    $fk_wh_line = (int) GETPOST('fk_warehouse_line', 'int');
    // Get all det lines from that invoice
    $rd_all = $db->query("SELECT d.rowid as det_id FROM " . MAIN_DB_PREFIX . "facture_fourn_det d WHERE d.fk_facture_fourn = " . $fk_invoice);
    while ($rd_all !== false && ($dd = $db->fetch_object($rd_all))) {
        $fk_det = (int) $dd->det_id;
        $nb_cartons = (int) GETPOST('nb_crt_' . $fk_det, 'int');
        if ($nb_cartons <= 0) continue;

        // Get carton info from invoice line
        $rli = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_invoice_line_info WHERE fk_facture_fourn_det = " . $fk_det);
        $li = ($rli !== false) ? $db->fetch_object($rli) : null;
        $cbm_c = $li ? (float) $li->cbm_carton : 0;
        $qty_c = $li ? (int) $li->qty_carton : 1;
        if ($qty_c <= 0) $qty_c = 1;

        // Check available cartons
        $total_inv = $li ? (float) $li->nb_cartons : 0;
        $rsh = $db->query("SELECT COALESCE(SUM(nb_cartons), 0) as s FROM " . MAIN_DB_PREFIX . "exportation_shipment_lines WHERE fk_facture_fourn_det = " . $fk_det);
        $sh = ($rsh !== false) ? $db->fetch_object($rsh) : null;
        $already = $sh ? (float) $sh->s : 0;
        $available = $total_inv - $already;
        if ($nb_cartons > $available && $available > 0) $nb_cartons = (int) $available;
        if ($nb_cartons <= 0) continue;

        $qty_shipped = $nb_cartons * $qty_c;
        $total_cbm = $nb_cartons * $cbm_c;

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "exportation_shipment_lines ";
        $sql .= "(fk_shipment, fk_facture_fourn_det, nb_cartons, cbm_carton, qty_carton, cbm, qty_shipped, fk_warehouse) VALUES (";
        $sql .= $id . ", " . $fk_det . ", " . $nb_cartons . ", " . $cbm_c . ", " . $qty_c . ", " . $total_cbm . ", " . $qty_shipped . ", " . $fk_wh_line . ")";
        $db->query($sql);
    }
    header("Location: shipment_card.php?id=" . $id);
    exit;
}

// ─── Legacy single link_line (kept for compatibility)
if ($action == 'link_line' && $id > 0) {
    $fk_det = (int) GETPOST('fk_facture_fourn_det', 'int');
    $nb_cartons = (float) GETPOST('nb_cartons', 'alphanohtml');
    if ($nb_cartons <= 0) $nb_cartons = 1;
    if ($fk_det > 0) {
        $rli = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_invoice_line_info WHERE fk_facture_fourn_det = " . $fk_det);
        $li = ($rli !== false) ? $db->fetch_object($rli) : null;
        $cbm_c = $li ? (float) $li->cbm_carton : 0;
        $qty_c = $li ? (int) $li->qty_carton : 1;
        if ($qty_c <= 0) $qty_c = 1;
        $total_inv = $li ? (float) $li->nb_cartons : 0;
        $rsh = $db->query("SELECT COALESCE(SUM(nb_cartons), 0) as s FROM " . MAIN_DB_PREFIX . "exportation_shipment_lines WHERE fk_facture_fourn_det = " . $fk_det);
        $sh = ($rsh !== false) ? $db->fetch_object($rsh) : null;
        $already = $sh ? (float) $sh->s : 0;
        $available = $total_inv - $already;
        if ($nb_cartons > $available && $available > 0) $nb_cartons = $available;
        $qty_shipped = $nb_cartons * $qty_c;
        $total_cbm = $nb_cartons * $cbm_c;
        $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_shipment_lines (fk_shipment, fk_facture_fourn_det, nb_cartons, cbm_carton, qty_carton, cbm, qty_shipped, fk_warehouse) VALUES (" . $id . ", " . $fk_det . ", " . $nb_cartons . ", " . $cbm_c . ", " . $qty_c . ", " . $total_cbm . ", " . $qty_shipped . ", 0)");
    }
    header("Location: shipment_card.php?id=" . $id);
    exit;
}

// ─── Add Expense (MRU only for shipment expenses)
if ($action == 'add_expense' && $id > 0) {
    $label = GETPOST('label', 'alpha');
    $amount = (float) GETPOST('amount', 'alphanohtml');
    $fk_bank = (int) GETPOST('fk_bank', 'int');
    
    $db->begin();
    $sql = "INSERT INTO " . MAIN_DB_PREFIX . "exportation_shipment_expenses (fk_shipment, label, amount, currency_code, exchange_rate, amount_local, fk_bank) ";
    $sql .= "VALUES (" . $id . ", '" . $db->escape($label) . "', " . $amount . ", 'MRU', 1, " . $amount . ", " . $fk_bank . ")";
    
    if ($db->query($sql)) {
        if ($fk_bank > 0) {
            require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';
            $acc = new Account($db);
            if ($acc->fetch($fk_bank) > 0) {
                $acc->addline(dol_now(), 'CHQ', '(Shipment Exp) ' . $label, -$amount, 0, 0, $user);
            }
        }
        $db->commit();
    } else {
        $db->rollback();
    }
    header("Location: shipment_card.php?id=" . $id);
    exit;
}

// ─── Delete line / expense
if ($action == 'del_line' && $id > 0) {
    $db->query("DELETE FROM " . MAIN_DB_PREFIX . "exportation_shipment_lines WHERE rowid = " . (int) GETPOST('lid', 'int') . " AND fk_shipment = " . $id);
    header("Location: shipment_card.php?id=" . $id);
    exit;
}
if ($action == 'del_expense' && $id > 0) {
    $db->query("DELETE FROM " . MAIN_DB_PREFIX . "exportation_shipment_expenses WHERE rowid = " . (int) GETPOST('eid', 'int') . " AND fk_shipment = " . $id);
    header("Location: shipment_card.php?id=" . $id);
    exit;
}

// ─── Discharge (Tafreagh): PMP + Stock
// PMP = P.U Achat (MRU) + Frais Facture/Unité + (CBM/crt × prix_cbm_effectif) / Unités/crt
// prix_cbm_effectif = prix_cbm + (total_shipment_expenses / total_cbm)
if ($action == 'discharge' && $id > 0) {
    $db->begin();
    $rs = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_shipment WHERE rowid = " . $id);
    $ship = $db->fetch_object($rs);
    $prix_cbm = (float) $ship->prix_cbm;
    $fk_wh = 1; // default fallback

    // Total shipment expenses (MRU)
    $rse = $db->query("SELECT SUM(amount_local) as t FROM " . MAIN_DB_PREFIX . "exportation_shipment_expenses WHERE fk_shipment = " . $id);
    $se = ($rse !== false) ? $db->fetch_object($rse) : null;
    $total_ship_exp = $se ? (float) $se->t : 0;

    // Total CBM in this shipment
    $rcbm = $db->query("SELECT SUM(cbm) as t FROM " . MAIN_DB_PREFIX . "exportation_shipment_lines WHERE fk_shipment = " . $id);
    $cbmo = ($rcbm !== false) ? $db->fetch_object($rcbm) : null;
    $total_cbm_ship = $cbmo ? (float) $cbmo->t : 0;

    // Effective prix CBM = manual prix_cbm + (shipment expenses / total CBM)
    $ship_exp_per_cbm = ($total_cbm_ship > 0) ? ($total_ship_exp / $total_cbm_ship) : 0;
    $effective_prix_cbm = $prix_cbm + $ship_exp_per_cbm;

    $sql_l = "SELECT sl.*, d.fk_product, d.pu_ht, d.qty as line_qty, d.fk_facture_fourn FROM " . MAIN_DB_PREFIX . "exportation_shipment_lines sl ";
    $sql_l .= "JOIN " . MAIN_DB_PREFIX . "facture_fourn_det d ON d.rowid = sl.fk_facture_fourn_det WHERE sl.fk_shipment = " . $id;
    $rl = $db->query($sql_l);
    $errors = 0;
    
    $products_stock = array();

    while ($lk = $db->fetch_object($rl)) {
        if ($lk->fk_product > 0 && $lk->qty_shipped > 0) {
            $pid = (int) $lk->fk_product;
            if (!isset($products_stock[$pid])) {
                $products_stock[$pid] = array('qty' => 0, 'total_val' => 0, 'wh' => $fk_wh);
            }

            // 1. Invoice expenses per unit (proportional to purchase price)
            $re = $db->query("SELECT SUM(amount_local) as t FROM " . MAIN_DB_PREFIX . "exportation_invoice_expenses WHERE fk_facture_fourn = " . $lk->fk_facture_fourn);
            $ie = ($re !== false) ? $db->fetch_object($re) : null;
            $inv_exp = $ie ? (float) $ie->t : 0;
            $ri = $db->query("SELECT SUM(pu_ht * qty) as t FROM " . MAIN_DB_PREFIX . "facture_fourn_det WHERE fk_facture_fourn = " . $lk->fk_facture_fourn . " AND product_type = 0 AND fk_product > 0");
            $iv = ($ri !== false) ? $db->fetch_object($ri) : null;
            $inv_ht = $iv ? (float) $iv->t : 0;
            $inv_qty = (float) $lk->line_qty;
            $line_total_ht = $lk->pu_ht * $inv_qty;
            $percentage = ($inv_ht > 0) ? ($line_total_ht / $inv_ht) : 0;
            $exp_for_product = $inv_exp * $percentage;
            $exp_per_unit = ($inv_qty > 0) ? ($exp_for_product / $inv_qty) : 0;

            // 2. Shipping cost per unit = (cbm_carton × effective_prix_cbm) / qty_carton
            $cbm_c = (float) $lk->cbm_carton;
            $qty_c = (int) $lk->qty_carton;
            if ($qty_c <= 0) $qty_c = 1;
            $ship_per_unit = ($cbm_c * $effective_prix_cbm) / $qty_c;

            // 3. Line cost (MRU)
            $new_cost = $lk->pu_ht + $exp_per_unit + $ship_per_unit;
            
            $products_stock[$pid]['qty'] += $lk->qty_shipped;
            $products_stock[$pid]['total_val'] += ($new_cost * $lk->qty_shipped);
            if ($lk->fk_warehouse > 0) $products_stock[$pid]['wh'] = (int) $lk->fk_warehouse;
        }
    }

    // 4. Stock movement via Dolibarr reception()
    foreach ($products_stock as $pid => $data) {
        if ($data['qty'] > 0) {
            $avg_cost = $data['total_val'] / $data['qty'];
            $mv = new MouvementStock($db);
            $label_mv = "Tafreagh - " . $ship->container_number;
            $result = $mv->reception($user, $pid, $data['wh'], $data['qty'], $avg_cost, $label_mv);
            if ($result < 0) $errors++;
        }
    }

    if ($errors == 0) {
        $db->query("UPDATE " . MAIN_DB_PREFIX . "exportation_shipment SET status = 3 WHERE rowid = " . $id);
        $db->commit();
        setEventMessages("Déchargement effectué. Stock et PMP mis à jour.", null, 'mesgs');
    } else {
        $db->rollback();
        setEventMessages("Erreur stock.", null, 'errors');
    }
    header("Location: shipment_card.php?id=" . $id);
    exit;
}

// ═══════════════════════════════════════════════════════════════
// UI RENDERING
// ═══════════════════════════════════════════════════════════════
llxHeader('', "Gestion des Expéditions (Imports)", '');

$formproduct = new FormProduct($db);
$form = new Form($db);

$obj = null;
if ($id > 0) {
    $r = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_shipment WHERE rowid = " . $id);
    $obj = ($r !== false) ? $db->fetch_object($r) : null;
}

print '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">';
print '<div id="sw">';
print '<style>
#sw{max-width:1500px;margin:0 auto;font-family:"Outfit",sans-serif;padding:20px;color:#1e2a3a}
.sc{background:#fff;border-radius:16px;padding:24px 26px;box-shadow:0 2px 18px rgba(30,42,58,.06);border:1px solid #eef2f7;margin-bottom:20px}
.sh{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #f0f4f8;gap:10px;flex-wrap:wrap}
.sh-l{display:flex;align-items:center;gap:10px}
.si2{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:15px;color:#fff;flex-shrink:0}
.si2.bl{background:linear-gradient(135deg,#3b82f6,#2563eb)}.si2.am{background:linear-gradient(135deg,#f59e0b,#d97706)}
.si2.gr{background:linear-gradient(135deg,#10b981,#059669)}.si2.in{background:linear-gradient(135deg,#6366f1,#4f46e5)}
.si2.ro{background:linear-gradient(135deg,#f43f5e,#e11d48)}.si2.cy{background:linear-gradient(135deg,#06b6d4,#0891b2)}
.tt{font-size:16px;font-weight:800}.su{font-size:11px;color:#94a3b8;margin-top:1px}
.sg{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px}
.st{background:#f8fafc;border-radius:11px;padding:12px 14px;border:1px solid #eef2f7}
.st .l{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:3px;display:flex;align-items:center;gap:4px}
.st .v{font-size:15px;font-weight:700;color:#1e2a3a}
.si3{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:"Outfit",sans-serif;box-sizing:border-box;background:#fff;transition:.15s}
.si3:focus{border-color:#3b82f6;outline:none;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.sl{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;display:block}
.sb{background:#3b82f6;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px;transition:.15s;display:inline-flex;align-items:center;gap:6px;text-decoration:none;white-space:nowrap}
.sb.g{background:linear-gradient(135deg,#10b981,#059669)}.sb.s{background:#64748b}.sb.sm{padding:6px 12px;font-size:12px}
.sb:hover{filter:brightness(1.08);transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.1)}
.tb{width:100%;border-collapse:collapse;font-size:13px}
.tb th{background:#f8fafc;padding:9px 11px;text-align:left;color:#64748b;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #eef2f7;white-space:nowrap}
.tb td{padding:10px 11px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.tb tbody tr:last-child td{border-bottom:none}.tb tbody tr:hover td{background:#fafbfd}
.ar td{background:#f0f9ff!important;border-top:2px dashed #bfdbfe!important;padding:10px!important}
.dp{background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.bd{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;padding:4px 11px;border-radius:20px}
.bd.dr{background:#fef9c3;color:#a16207}.bd.dn{background:#dcfce7;color:#166534}
.sum{background:linear-gradient(135deg,#1e2a3a,#2d3f55);border-radius:16px;padding:24px 28px;color:#fff;margin-bottom:20px}
.sum .sl2{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#93c5fd;margin-bottom:3px}
.sum .sv{font-size:22px;font-weight:800}.sum .sv.big{font-size:36px;color:#60a5fa}
.steps{display:flex;gap:0;margin-bottom:18px}
.step{flex:1;text-align:center;padding:10px 4px;font-size:10.5px;font-weight:700;color:#94a3b8;background:#f8fafc;border:1px solid #eef2f7}
.step:first-child{border-radius:10px 0 0 10px}.step:last-child{border-radius:0 10px 10px 0}
.step.active{background:#3b82f6;color:#fff;border-color:#3b82f6}.step.done{background:#dcfce7;color:#15803d;border-color:#d1fae5}
.step i{display:block;font-size:15px;margin-bottom:3px}
.es{text-align:center;padding:28px;color:#94a3b8;font-size:13px}.es i{font-size:28px;display:block;margin-bottom:8px}
.nav{display:inline-flex;align-items:center;gap:6px;color:#64748b;font-weight:600;font-size:13px;text-decoration:none;margin-bottom:12px}.nav:hover{color:#3b82f6}
.pmp td{font-size:12px;padding:8px 10px}.pmp th{font-size:10px;padding:8px 10px}
.ph{background:#f0fdf4!important;font-weight:800;color:#15803d}
.del{color:#ef4444;font-size:13px;cursor:pointer}.del:hover{color:#dc2626}
.prix-cbm-box{background:linear-gradient(135deg,#fef3c7,#fde68a);border:2px solid #fbbf24;border-radius:14px;padding:16px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:18px}
.prix-cbm-box .label{font-size:12px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.5px}
.prix-cbm-box .val{font-size:28px;font-weight:800;color:#78350f}
select.flat{padding:9px 12px!important;border:1.5px solid #e2e8f0!important;border-radius:8px!important;font-size:13px!important;font-family:"Outfit",sans-serif!important;background:#fff!important;width:100%!important;box-sizing:border-box!important}
</style>';


// ═══════════════════════════════════════════════════════════════
// PAGE A: LIST / CREATE
// ═══════════════════════════════════════════════════════════════
if (!$obj) {
    print '<div class="nav"><i class="fa-solid fa-ship"></i> Gestion des Expéditions</div>';

    // Create form
    print '<div class="sc"><div class="sh"><div class="sh-l"><div class="si2 bl"><i class="fa-solid fa-ship"></i></div>';
    print '<div><div class="tt">Nouvelle Expédition</div><div class="su">Créer un dossier de conteneur</div></div></div></div>';
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '"><input type="hidden" name="action" value="add_header"><input type="hidden" name="token" value="' . newToken() . '">';
    print '<div class="sg">';
    print '<div><label class="sl"><i class="fa-solid fa-hashtag"></i> N° Conteneur *</label><input type="text" name="container_number" class="si3" placeholder="MSCU1234567" required></div>';
    print '<div><label class="sl"><i class="fa-solid fa-satellite-dish"></i> Tracking</label><input type="text" name="tracking_number" class="si3" placeholder="BL2025001"></div>';
    print '<div><label class="sl"><i class="fa-solid fa-calendar-check"></i> Date expédition *</label><input type="date" name="shipping_date" class="si3" value="' . date('Y-m-d') . '" required></div>';
    print '<div><label class="sl"><i class="fa-solid fa-calendar-day"></i> Date arrivée</label><input type="date" name="arrival_date" class="si3"></div>';
    print '<div><label class="sl"><i class="fa-solid fa-coins"></i> Prix CBM (MRU/m³) *</label><input type="number" step="0.01" name="prix_cbm" class="si3" placeholder="80000" required></div>';
    print '</div>';
    print '<div style="margin-top:18px;text-align:right;"><button type="submit" class="sb"><i class="fa-solid fa-plus-circle"></i> Créer</button></div></form></div>';

    // Recent list
    $rr = $db->query("SELECT s.rowid, s.ref, s.container_number, s.shipping_date, s.status, s.prix_cbm FROM " . MAIN_DB_PREFIX . "exportation_shipment s WHERE s.entity = " . (int) $conf->entity . " ORDER BY s.rowid DESC LIMIT 5000");
    print '<div class="sc"><div class="sh"><div class="sh-l"><div class="si2 in"><i class="fa-solid fa-list-ul"></i></div><div><div class="tt">Expéditions Récentes</div></div></div></div>';
    print '<table class="tb" id="exp-recentes" data-pg="1"><thead><tr><th>Réf</th><th>Conteneur</th><th>Date</th><th>Prix CBM</th><th>Statut</th><th></th></tr></thead><tbody>';
    $has = false;
    if ($rr) {
        while ($r = $db->fetch_object($rr)) {
            $has = true;
            $sc = ($r->status == 3) ? array('Dischargé', 'dn') : array('Brouillon', 'dr');
            print '<tr>';
            print '<td><a href="?id=' . $r->rowid . '" style="font-weight:700;color:#3b82f6;">' . htmlspecialchars($r->ref) . '</a></td>';
            print '<td><span class="dp"><i class="fa-solid fa-box"></i> ' . dol_escape_htmltag($r->container_number) . '</span></td>';
            print '<td>' . dol_print_date($db->jdate($r->shipping_date), 'day') . '</td>';
            print '<td><strong>' . price($r->prix_cbm) . '</strong> MRU/m³</td>';
            print '<td><span class="bd ' . $sc[1] . '"><i class="fa-solid ' . ($r->status == 3 ? 'fa-circle-check' : 'fa-pen') . '"></i> ' . $sc[0] . '</span></td>';
            print '<td><a href="?id=' . $r->rowid . '" class="sb sm"><i class="fa-solid fa-arrow-right"></i></a></td>';
            print '</tr>';
        }
    }
    if (!$has)
        print '<tr><td colspan="6"><div class="es"><i class="fa-solid fa-ship"></i> Aucune expédition</div></td></tr>';
    print '</tbody></table></div>';

} else {
    // ═══════════════════════════════════════════════════════════════
// PAGE B: SHIPMENT DETAIL
// ═══════════════════════════════════════════════════════════════
    $is_done = ($obj->status == 3);
    $prix_cbm = (float) $obj->prix_cbm;


    print '<a href="' . $_SERVER['PHP_SELF'] . '" class="nav"><i class="fa-solid fa-arrow-left"></i> Retour</a>';

    // Steps
    $sc = (int) $obj->status;
    $sdef = array(0 => array('Brouillon', 'fa-pen'), 1 => array('En transit', 'fa-ship'), 2 => array('Arrivé', 'fa-anchor'), 3 => array('Dischargé', 'fa-circle-check'));
    print '<div class="steps">';
    foreach ($sdef as $i => $d) {
        $c = ($i < $sc ? 'done' : ($i == $sc ? 'active' : ''));
        print '<div class="step ' . $c . '"><i class="fa-solid ' . $d[1] . '"></i>' . $d[0] . '</div>';
    }
    print '</div>';

    // Header card
    print '<div class="sc"><div class="sh"><div class="sh-l"><div class="si2 bl"><i class="fa-solid fa-ship"></i></div><div><div class="tt">' . htmlspecialchars($obj->ref) . '</div></div></div>';
    $st_b = $is_done ? 'dn' : 'dr';
    $st_l = $is_done ? 'Dischargé' : 'Brouillon';
    print '<span class="bd ' . $st_b . '"><i class="fa-solid ' . ($is_done ? 'fa-circle-check' : 'fa-pen') . '"></i> ' . $st_l . '</span></div>';
    print '<div class="sg">';
    print '<div class="st"><div class="l"><i class="fa-solid fa-hashtag"></i> Conteneur</div><div class="v">' . dol_escape_htmltag($obj->container_number ?: '—') . '</div></div>';
    print '<div class="st"><div class="l"><i class="fa-solid fa-satellite-dish"></i> Tracking</div><div class="v">' . dol_escape_htmltag($obj->tracking_number ?: '—') . '</div></div>';
    print '<div class="st"><div class="l"><i class="fa-solid fa-calendar-check"></i> Expédition</div><div class="v">' . dol_print_date($db->jdate($obj->shipping_date), 'day') . '</div></div>';
    print '</div></div>';

    // ── Prix CBM box (editable if not final)
    print '<div class="prix-cbm-box">';
    print '<div style="flex:1;"><div class="label"><i class="fa-solid fa-calculator"></i> Prix par CBM (m³)</div>';
    print '<div class="val">' . price($prix_cbm) . ' <span style="font-size:14px;">MRU/m³</span></div></div>';
    if (!$is_done) {
        print '<form method="POST" action="?id=' . $id . '" style="display:flex;gap:6px;align-items:center;">';
        print '<input type="hidden" name="action" value="update_prix_cbm"><input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="number" step="0.01" name="prix_cbm" value="' . number_format($prix_cbm, 2, '.', '') . '" class="si3" style="width:130px;" required>';
        print '<button type="submit" class="sb sm"><i class="fa-solid fa-save"></i></button></form>';
    }
    print '</div>';

    // ── Fetch lines (with warehouse name)
    $rl = $db->query("SELECT sl.*, f.ref as fref, d.fk_facture_fourn as fid, d.pu_ht, d.qty as line_qty, COALESCE(p.label, d.description) as plbl, p.ref as pref, e.ref as wh_ref FROM " . MAIN_DB_PREFIX . "exportation_shipment_lines sl JOIN " . MAIN_DB_PREFIX . "facture_fourn_det d ON d.rowid = sl.fk_facture_fourn_det JOIN " . MAIN_DB_PREFIX . "facture_fourn f ON f.rowid = d.fk_facture_fourn LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = d.fk_product LEFT JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = sl.fk_warehouse WHERE sl.fk_shipment = " . $id);
    $total_cbm = 0;
    $lines = array();
    $lines_by_invoice = array();
    if ($rl) {
        while ($l = $db->fetch_object($rl)) {
            $total_cbm += (float) $l->cbm;
            $lines[] = $l;
            $lines_by_invoice[$l->fref][] = $l;
        }
    }

    // ── Products table (grouped by invoice)
    print '<div class="sc"><div class="sh"><div class="sh-l"><div class="si2 am"><i class="fa-solid fa-boxes-stacked"></i></div>';
    print '<div><div class="tt">Produits</div><div class="su">Regroupés par dossier d\'achat — Entrepôt par facture</div></div></div>';
    print '<span style="font-size:12px;color:#94a3b8;"><i class="fa-solid fa-cube"></i> Total: <strong>' . number_format($total_cbm, 3) . ' m³</strong></span></div>';

    print '<div style="overflow-x:auto;"><table class="tb"><thead><tr>';
    print '<th>Facture</th><th>Produit</th><th>P.U (MRU)</th><th>CBM/Crt</th><th>Unités/Crt</th><th>Nb Crt</th><th>CBM Total</th><th>Qté Total</th><th>Entrepôt</th>';
    if (!$is_done) print '<th></th>';
    print '</tr></thead><tbody>';

    foreach ($lines_by_invoice as $inv_ref => $inv_lines) {
        $first = true;
        foreach ($inv_lines as $l) {
            print '<tr>';
            if ($first) {
                print '<td rowspan="' . count($inv_lines) . '" style="vertical-align:top;border-right:3px solid #3b82f6;"><a href="supplier_invoice.php?id=' . (int) $l->fid . '" style="color:#3b82f6;font-weight:700;">' . htmlspecialchars($l->fref) . '</a></td>';
                $first = false;
            }
            print '<td><strong>' . dol_escape_htmltag($l->plbl) . '</strong>';
            if ($l->pref) print ' <span style="color:#94a3b8;font-size:10px;">(' . dol_escape_htmltag($l->pref) . ')</span>';
            print '</td>';
            print '<td>' . price($l->pu_ht) . '</td>';
            print '<td><span class="dp">' . number_format((float) $l->cbm_carton, 4) . '</span></td>';
            print '<td>' . (int) $l->qty_carton . '</td>';
            print '<td><strong>' . number_format((float) $l->nb_cartons, 0) . '</strong></td>';
            print '<td>' . number_format((float) $l->cbm, 4) . ' m³</td>';
            print '<td><strong>' . number_format((float) $l->qty_shipped, 0) . '</strong></td>';
            print '<td><span class="dp">' . dol_escape_htmltag($l->wh_ref ?: '—') . '</span></td>';
            if (!$is_done)
                print '<td><a href="?id=' . $id . '&action=del_line&lid=' . $l->rowid . '&token=' . newToken() . '" class="del" onclick="return confirm(\'Supprimer ?\');"><i class="fa-solid fa-trash-can"></i></a></td>';
            print '</tr>';
        }
    }

    // Add lines: modal-based invoice selector
    if (!$is_done) {
        // Get invoices from external suppliers with available cartons
        $ri = $db->query("SELECT DISTINCT f.rowid, f.ref, s.nom as fourn_name FROM " . MAIN_DB_PREFIX . "facture_fourn f JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc JOIN " . MAIN_DB_PREFIX . "facture_fourn_det d ON d.fk_facture_fourn = f.rowid LEFT JOIN " . MAIN_DB_PREFIX . "exportation_invoice_line_info li ON li.fk_facture_fourn_det = d.rowid WHERE s.exportation_type = 'EXTERNE' AND f.fk_statut >= 1 AND f.entity = " . (int) $conf->entity . " AND (li.nb_cartons IS NULL OR li.nb_cartons > COALESCE((SELECT SUM(sl2.nb_cartons) FROM " . MAIN_DB_PREFIX . "exportation_shipment_lines sl2 WHERE sl2.fk_facture_fourn_det = d.rowid), 0)) ORDER BY f.ref DESC");
        $invoices_data = array();
        while ($ri !== false && ($inv = $db->fetch_object($ri))) {
            if (!isset($invoices_data[$inv->rowid])) {
                $invoices_data[$inv->rowid] = $inv;
            }
        }

        print '<tr class="ar"><td colspan="10" style="padding:16px!important;">';
        print '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
        print '<span style="font-weight:700;color:#3b82f6;font-size:13px;"><i class="fa-solid fa-plus-circle"></i> Ajouter depuis un dossier d\'achat:</span>';
        print '<select id="inv_select" class="si3" style="width:300px;" onchange="openInvModal(this.value)">';
        print '<option value="">-- Rechercher par réf / fournisseur --</option>';
        foreach ($invoices_data as $inv) {
            print '<option value="' . $inv->rowid . '">' . htmlspecialchars($inv->ref . ' — ' . $inv->fourn_name) . '</option>';
        }
        print '</select></div></td></tr>';
    }

    if (empty($lines) && $is_done)
        print '<tr><td colspan="10"><div class="es"><i class="fa-solid fa-boxes-stacked"></i> Aucun produit</div></td></tr>';
    print '</tbody></table></div></div>';

    // ── MODAL for selecting cartons from invoice
    if (!$is_done) {
        // Build all invoice product data as JSON for the JS modal
        $all_inv_products = array();
        $ri2 = $db->query("SELECT d.rowid as det_id, d.fk_facture_fourn, d.pu_ht, COALESCE(p.label, d.description) as plbl, p.ref as pref, f.ref as fref, li.cbm_carton, li.qty_carton, li.nb_cartons, COALESCE((SELECT SUM(sl2.nb_cartons) FROM " . MAIN_DB_PREFIX . "exportation_shipment_lines sl2 WHERE sl2.fk_facture_fourn_det = d.rowid), 0) as shipped FROM " . MAIN_DB_PREFIX . "facture_fourn_det d JOIN " . MAIN_DB_PREFIX . "facture_fourn f ON f.rowid = d.fk_facture_fourn JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = d.fk_product LEFT JOIN " . MAIN_DB_PREFIX . "exportation_invoice_line_info li ON li.fk_facture_fourn_det = d.rowid WHERE s.exportation_type = 'EXTERNE' AND f.fk_statut >= 1 AND f.entity = " . (int) $conf->entity . " AND d.fk_product > 0 AND d.product_type = 0 ORDER BY f.ref, plbl");
        while ($ri2 !== false && ($pr = $db->fetch_object($ri2))) {
            $avail = (int) $pr->nb_cartons - (int) $pr->shipped;
            if ($avail <= 0 && $pr->nb_cartons > 0) continue;
            $all_inv_products[] = array(
                'det_id' => (int) $pr->det_id,
                'fk_inv' => (int) $pr->fk_facture_fourn,
                'fref' => $pr->fref,
                'plbl' => $pr->plbl ?: '?',
                'pref' => $pr->pref ?: '',
                'pu' => (float) $pr->pu_ht,
                'cbm' => (float) $pr->cbm_carton,
                'qty_c' => (int) $pr->qty_carton,
                'nb_crt' => (int) $pr->nb_cartons,
                'shipped' => (int) $pr->shipped,
                'avail' => max(0, $avail)
            );
        }

        // Get warehouses for the dropdown
        $wh_opts = '';
        $rwh2 = $db->query("SELECT rowid, ref FROM " . MAIN_DB_PREFIX . "entrepot WHERE entity = " . (int) $conf->entity . " AND statut = 1 ORDER BY ref");
        while ($rwh2 !== false && ($wh2 = $db->fetch_object($rwh2))) {
            $wh_opts .= '<option value="' . $wh2->rowid . '">' . dol_escape_htmltag($wh2->ref) . '</option>';
        }

        print '<div id="inv_modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">';
        print '<div style="background:#fff;border-radius:18px;max-width:900px;width:95%;max-height:85vh;overflow-y:auto;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.2);">';
        print '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;"><div style="font-size:18px;font-weight:800;"><i class="fa-solid fa-boxes-stacked" style="color:#3b82f6;"></i> Sélectionner les cartons</div><button onclick="closeInvModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#94a3b8;">&times;</button></div>';
        print '<form method="POST" action="?id=' . $id . '" id="modal_form">';
        print '<input type="hidden" name="action" value="link_lines"><input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="fk_invoice" id="modal_fk_inv" value="0">';
        print '<div style="margin-bottom:14px;"><label class="sl"><i class="fa-solid fa-warehouse"></i> Entrepôt pour cette facture *</label><select name="fk_warehouse_line" class="si3" required>' . $wh_opts . '</select></div>';
        print '<table class="tb" id="modal_table"><thead><tr><th>Produit</th><th>P.U</th><th>CBM/Crt</th><th>U/Crt</th><th>Total Crt</th><th>Expédié</th><th>Dispo</th><th>Nb à expédier</th></tr></thead><tbody id="modal_body"></tbody></table>';
        print '<div style="margin-top:18px;text-align:right;"><button type="button" onclick="closeInvModal()" class="sb s" style="margin-right:8px;"><i class="fa-solid fa-xmark"></i> Annuler</button><button type="submit" class="sb g"><i class="fa-solid fa-plus"></i> Ajouter au conteneur</button></div>';
        print '</form></div></div>';

        print '<script>';
        print 'var invProducts=' . json_encode($all_inv_products) . ';';
        print 'function openInvModal(invId){if(!invId)return;document.getElementById("modal_fk_inv").value=invId;var body=document.getElementById("modal_body");body.innerHTML="";var items=invProducts.filter(function(p){return p.fk_inv==parseInt(invId);});';
        print 'items.forEach(function(p){var tr=document.createElement("tr");tr.innerHTML="<td><strong>"+p.plbl+"</strong>"+(p.pref?" <span style=color:#94a3b8;font-size:10px>("+p.pref+")</span>":"")+"</td><td>"+p.pu.toFixed(2)+"</td><td><span class=dp>"+p.cbm.toFixed(4)+"</span></td><td>"+p.qty_c+"</td><td><strong>"+p.nb_crt+"</strong></td><td>"+p.shipped+"</td><td style=color:#10b981;font-weight:700>"+p.avail+"</td><td><input type=number name=nb_crt_"+p.det_id+" min=0 max="+p.avail+" value="+p.avail+" class=si3 style=width:80px></td>";body.appendChild(tr);});';
        print 'document.getElementById("inv_modal").style.display="flex";document.getElementById("inv_select").value="";}';
        print 'function closeInvModal(){document.getElementById("inv_modal").style.display="none";}';
        print 'document.getElementById("inv_modal").addEventListener("click",function(e){if(e.target===this)closeInvModal();});';
        print '</script>';
    }

    // ── Expenses
    $re = $db->query("SELECT e.*, ba.label as bank_name FROM " . MAIN_DB_PREFIX . "exportation_shipment_expenses e LEFT JOIN " . MAIN_DB_PREFIX . "bank_account ba ON ba.rowid = e.fk_bank WHERE e.fk_shipment = " . $id);
    $texp = 0;
    $exps = array();
    if ($re) {
        while ($e = $db->fetch_object($re)) {
            $texp += (float) $e->amount_local;
            $exps[] = $e;
        }
    }

    print '<div class="sc"><div class="sh"><div class="sh-l"><div class="si2 ro"><i class="fa-solid fa-receipt"></i></div>';
    print '<div><div class="tt">Frais d\'Expédition (MRU)</div></div></div>';
    print '<span style="font-size:12px;color:#94a3b8;">Total: <strong style="color:#ef4444;">' . price($texp) . ' MRU</strong></span></div>';

    print '<table class="tb"><thead><tr><th>Description</th><th>Montant (MRU)</th><th>Caisse / Banque</th>';
    if (!$is_done) print '<th></th>';
    print '</tr></thead><tbody>';
    foreach ($exps as $e) {
        print '<tr>';
        print '<td><strong>' . dol_escape_htmltag($e->label) . '</strong></td>';
        print '<td><strong>' . price($e->amount_local) . ' MRU</strong></td>';
        print '<td>' . dol_escape_htmltag($e->bank_name ?: '—') . '</td>';
        if (!$is_done)
            print '<td><a href="?id=' . $id . '&action=del_expense&eid=' . $e->rowid . '&token=' . newToken() . '" class="del" onclick="return confirm(\'Supprimer ?\');"><i class="fa-solid fa-trash-can"></i></a></td>';
        print '</tr>';
    }
    if (!$is_done) {
        // Get banks
        $banks = array();
        $rb = $db->query("SELECT rowid, label FROM " . MAIN_DB_PREFIX . "bank_account WHERE entity = " . (int) $conf->entity . " AND clos = 0 ORDER BY label");
        while ($rb && $bk = $db->fetch_object($rb)) { $banks[] = $bk; }

        print '<tr class="ar"><form method="POST" action="?id=' . $id . '"><input type="hidden" name="action" value="add_expense"><input type="hidden" name="token" value="' . newToken() . '">';
        print '<td><input type="text" name="label" placeholder="Douane, Fret, Transport..." class="si3" required></td>';
        print '<td><input type="number" step="0.01" name="amount" placeholder="Montant MRU" class="si3" style="width:140px;" required></td>';
        print '<td><div style="display:flex;gap:4px;"><select name="fk_bank" class="si3" style="width:150px;" required><option value="">— Banque —</option>';
        foreach($banks as $b) print '<option value="'.$b->rowid.'">'.htmlspecialchars($b->label).'</option>';
        print '</select><button type="submit" class="sb"><i class="fa-solid fa-plus"></i></button></div></td>';
        if (!$is_done) print '<td></td>';
        print '</form></tr>';
    }
    print '</tbody></table></div>';

    // ── Payments from linked invoices
    $inv_ids = array();
    foreach ($lines as $l) {
        $fid = (int) $l->fid;
        if ($fid > 0 && !in_array($fid, $inv_ids)) $inv_ids[] = $fid;
    }
    if (count($inv_ids) > 0) {
        $inv_ids_str = implode(',', $inv_ids);
        $rpy = $db->query("SELECT p.*, ba.label as bank_label, ba.currency_code as bank_currency, f.ref as fref FROM " . MAIN_DB_PREFIX . "exportation_invoice_payments p LEFT JOIN " . MAIN_DB_PREFIX . "bank_account ba ON ba.rowid = p.fk_bank LEFT JOIN " . MAIN_DB_PREFIX . "facture_fourn f ON f.rowid = p.fk_facture_fourn WHERE p.fk_facture_fourn IN (" . $inv_ids_str . ") ORDER BY p.datep ASC");
        $ship_payments = array();
        $ship_total_paid = 0;
        while ($rpy !== false && ($spy = $db->fetch_object($rpy))) {
            $ship_total_paid += (float) $spy->amount_local;
            $ship_payments[] = $spy;
        }

        print '<div class="sc"><div class="sh"><div class="sh-l"><div class="si2 gr" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);"><i class="fa-solid fa-hand-holding-dollar"></i></div>';
        print '<div><div class="tt">Paiements Factures Liées</div><div class="su">' . count($ship_payments) . ' paiement(s) — Total: <strong>' . price($ship_total_paid) . ' MRU</strong></div></div></div></div>';

        if (count($ship_payments) > 0) {
            print '<table class="tb"><thead><tr><th>Facture</th><th>Date</th><th>Banque</th><th>Montant</th><th>Taux</th><th>MRU</th><th>Note</th></tr></thead><tbody>';
            foreach ($ship_payments as $spy) {
                print '<tr>';
                print '<td><a href="supplier_invoice.php?id=' . (int) $spy->fk_facture_fourn . '" style="color:#3b82f6;font-weight:700;">' . htmlspecialchars($spy->fref) . '</a></td>';
                print '<td>' . dol_print_date($db->jdate($spy->datep), 'day') . '</td>';
                print '<td><strong>' . dol_escape_htmltag($spy->bank_label ?: '—') . '</strong> <span class="dp">' . dol_escape_htmltag($spy->bank_currency ?: $spy->currency_code) . '</span></td>';
                print '<td><strong>' . price($spy->amount) . '</strong> ' . dol_escape_htmltag($spy->currency_code) . '</td>';
                print '<td>&times; ' . number_format($spy->exchange_rate, 4) . '</td>';
                print '<td><strong>' . price($spy->amount_local) . '</strong></td>';
                print '<td style="color:#94a3b8;font-size:12px;">' . dol_escape_htmltag($spy->note ?: '') . '</td>';
                print '</tr>';
            }
            print '</tbody></table>';
        } else {
            print '<div class="es"><i class="fa-solid fa-hand-holding-dollar"></i> Aucun paiement enregistré</div>';
        }
        print '</div>';
    }

    // ── PMP Analysis Table (cost breakdown per product)
    if (count($lines) > 0) {
        // Effective prix CBM = manual prix_cbm + (shipment expenses / total CBM)
        $ship_exp_per_cbm = ($total_cbm > 0) ? ($texp / $total_cbm) : 0;
        $eff_prix_cbm = $prix_cbm + $ship_exp_per_cbm;

        print '<div class="sc"><div class="sh"><div class="sh-l"><div class="si2 cy"><i class="fa-solid fa-calculator"></i></div>';
        print '<div><div class="tt">Analyse du Coût — Calcul PMP</div>';
        print '<div class="su">Prix CBM = ' . price($prix_cbm) . ' + Frais Expéd. ' . price($ship_exp_per_cbm) . ' = <strong>' . price($eff_prix_cbm) . ' MRU/m³</strong></div></div></div></div>';

        print '<div style="overflow-x:auto;"><table class="tb pmp"><thead><tr>';
        print '<th>Produit</th><th>P.U Achat (MRU)</th><th>CBM/Crt</th><th>U/Crt</th>';
        print '<th>Frais Fact./U (MRU)</th>';
        print '<th>Coût CBM/U (MRU)</th>';
        print '<th>Frais Expéd./U (MRU)</th>';
        print '<th style="background:#ecfdf5;">PMP (MRU)</th>';
        print '<th>Qté</th><th>Valeur Stock (MRU)</th>';
        print '</tr></thead><tbody>';

        foreach ($lines as $l) {
            $cbm_c = (float) $l->cbm_carton;
            $qty_c = (int) $l->qty_carton;
            if ($qty_c <= 0)
                $qty_c = 1;

            // a) CBM cost per unit (from prix_cbm)
            $cbm_cost_u = ($cbm_c * $prix_cbm) / $qty_c;

            // b) Shipment expenses per unit (from frais d'expédition, distributed by CBM)
            $ship_exp_u = ($cbm_c * $ship_exp_per_cbm) / $qty_c;

            // c) Invoice expenses per unit (proportional to purchase price)
            $fid = (int) $l->fid;
            $rie = $db->query("SELECT SUM(amount_local) as t FROM " . MAIN_DB_PREFIX . "exportation_invoice_expenses WHERE fk_facture_fourn = " . $fid);
            $ie = ($rie !== false) ? $db->fetch_object($rie) : null;
            $i_exp = $ie ? (float) $ie->t : 0;
            $riv = $db->query("SELECT SUM(pu_ht * qty) as t FROM " . MAIN_DB_PREFIX . "facture_fourn_det WHERE fk_facture_fourn = " . $fid . " AND product_type = 0 AND fk_product > 0");
            $iv = ($riv !== false) ? $db->fetch_object($riv) : null;
            $i_ht = $iv ? (float) $iv->t : 0;
            $pu = (float) $l->pu_ht;
            $inv_qty = (float) $l->line_qty;
            $line_total_ht = $pu * $inv_qty;
            $percentage = ($i_ht > 0) ? ($line_total_ht / $i_ht) : 0;
            $exp_for_product = $i_exp * $percentage;
            $inv_exp_u = ($inv_qty > 0) ? ($exp_for_product / $inv_qty) : 0;

            // PMP = Purchase + Invoice expenses + CBM cost + Shipment expenses
            $pmp = $pu + $inv_exp_u + $cbm_cost_u + $ship_exp_u;
            $qty = (float) $l->qty_shipped;
            $val = $pmp * $qty;

            print '<tr>';
            print '<td><strong>' . dol_escape_htmltag($l->plbl) . '</strong></td>';
            print '<td>' . price($pu) . '</td>';
            print '<td>' . number_format($cbm_c, 4) . '</td>';
            print '<td>' . $qty_c . '</td>';
            print '<td>' . price($inv_exp_u) . '</td>';
            print '<td>' . price($cbm_cost_u) . '</td>';
            print '<td>' . price($ship_exp_u) . '</td>';
            print '<td class="ph">' . price($pmp) . '</td>';
            print '<td>' . number_format($qty, 0) . '</td>';
            print '<td><strong>' . price($val) . '</strong></td>';
            print '</tr>';
        }
        print '</tbody></table></div></div>';
    }

    // ── Discharge button
    if (!$is_done && count($lines) > 0) {
        print '<div class="sc" style="text-align:center;padding:28px;">';
        print '<p style="color:#64748b;font-size:13px;margin-bottom:14px;"><i class="fa-solid fa-circle-info"></i> Mettra à jour le stock et le PMP de chaque produit</p>';
        print '<form method="POST" action="?id=' . $id . '" style="display:inline;">';
        print '<input type="hidden" name="action" value="discharge"><input type="hidden" name="token" value="' . newToken() . '">';
        print '<button type="submit" class="sb g" style="font-size:15px;padding:13px 36px;" onclick="return confirm(\'Confirmer le déchargement ?\');">';
        print '<i class="fa-solid fa-truck-ramp-box"></i> Valider le Déchargement (التفريغ)</button></form></div>';
    }

    if ($is_done) {
        print '<div class="sc" style="text-align:center;padding:20px;">';
        print '<span class="bd dn" style="font-size:14px;padding:10px 24px;"><i class="fa-solid fa-circle-check"></i> Expédition Terminée — Stock mis à jour</span></div>';
    }
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
?>