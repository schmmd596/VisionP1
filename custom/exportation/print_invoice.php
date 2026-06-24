<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';

$id = GETPOST('id', 'int');
if ($id <= 0) {
    die("Dossier introuvable.");
}

$fac = new FactureFournisseur($db);
if ($fac->fetch($id) <= 0) {
    die("Erreur de chargement de la facture.");
}
$fac->fetch_thirdparty();

// Fetch currency and totals
$ri = $db->query("SELECT currency_code, exchange_rate FROM " . MAIN_DB_PREFIX . "exportation_invoice_info WHERE fk_facture_fourn = " . $id);
$ii = ($ri !== false) ? $db->fetch_object($ri) : null;
$CC = $ii ? $ii->currency_code : 'MRU';
$ER = $ii ? (float) $ii->exchange_rate : 1;

$total_dev = ($ER > 0) ? ($fac->total_ht / $ER) : $fac->total_ht;

$rese = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_invoice_expenses WHERE fk_facture_fourn = " . (int)$id . " AND expense_target='NOUS'");
$total_exp = 0;
$total_exp_dev = 0;
$exp_nous = [];
while ($rese !== false && ($e = $db->fetch_object($rese))) {
    $total_exp += (float) $e->amount_local;
    $total_exp_dev += ($e->currency_code == $CC) ? (float) $e->amount : (($ER > 0) ? ((float) $e->amount_local / $ER) : (float) $e->amount);
    $exp_nous[] = $e;
}

$grand_dev = $total_dev;
$grand_mru = $fac->total_ht;

$total_paid = 0;
$total_paid_dev = 0;
$rp = $db->query("SELECT amount, amount_local FROM " . MAIN_DB_PREFIX . "exportation_invoice_payments WHERE fk_facture_fourn = " . $id);
while ($rp !== false && ($py = $db->fetch_object($rp))) {
    $total_paid += (float) $py->amount_local;
    $total_paid_dev += (float) $py->amount;
}
$remaining_dev = $grand_dev - $total_paid_dev;

$title = "Facture Fournisseur " . $fac->ref;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?php echo $title; ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap');
body { font-family: 'Outfit', sans-serif; color:#1e293b; background:#edeff3; margin:0; padding:40px; font-size:13px; line-height:1.5; }
.container { max-width: 900px; margin: 0 auto; padding: 40px; background: #fff; box-shadow: 0 10px 40px rgba(0,0,0,0.08); border-radius: 16px; border: 1px solid #e2e8f0; }
.header { display:flex; justify-content:space-between; border-bottom: 3px solid #059669; padding-bottom: 24px; margin-bottom: 34px; }
.header-left h1 { margin:0; font-size:32px; color:#059669; font-weight:800; letter-spacing:-0.5px; }
.header-left p { margin:6px 0 0; font-size:15px; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:1px; }
.header-right { text-align:right; }
.info-row { display:flex; gap: 24px; margin-bottom: 40px; }
.info-box { flex:1; background:#f8fafc; padding:20px; border-radius:12px; border:1px solid #e2e8f0; }
.info-box h3 { margin:0 0 8px; font-size:12px; text-transform:uppercase; color:#94a3b8; letter-spacing:1.5px; font-weight:700; }
.info-box p { margin:0; font-size:20px; font-weight:800; color:#0f172a; }
.info-box .sub { font-size:14px; color:#64748b; font-weight:500; margin-top:6px; }
table.items { width:100%; border-collapse: separate; border-spacing: 0; margin-bottom:34px; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; }
table.items th { background:#f1f5f9; color:#64748b; text-transform:uppercase; font-size:11px; padding:16px 14px; text-align:left; border-bottom:2px solid #cbd5e1; font-weight:800; letter-spacing:0.5px; }
table.items td { padding:14px; border-bottom:1px solid #f1f5f9; font-size:14px; font-weight:500; }
table.items tr:last-child td { border-bottom:none; }
table.items th.right, table.items td.right { text-align:right; }
.totals { width: 380px; margin-left:auto; }
.total-line { display:flex; justify-content:space-between; padding:10px 0; font-size:15px; color:#475569; font-weight:500; }
.total-line.bold { font-weight:800; color:#0f172a; font-size:18px; border-top:2px solid #e2e8f0; padding-top:16px; margin-top:6px; }
.total-line.grand { background:#ecfdf5; padding:18px 24px; border-radius:12px; color:#059669; font-size:22px; font-weight:800; margin-top:14px; border:2px solid #34d399; box-shadow: 0 4px 12px rgba(16,185,129,0.15); }
.badge { display:inline-block; padding:6px 14px; border-radius:30px; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin-top:16px; }
.bd-success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
.bd-warning { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
.bd-draft { background:#f8fafc; color:#64748b; border:1px solid #e2e8f0; }
.footer { margin-top: 60px; text-align:center; font-size:12px; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:24px; font-weight:500; }
@media print {
    body { padding:0; background:#fff; }
    .container { max-width:100%; box-shadow:none; border:none; padding:0; }
    .no-print { display:none !important; }
}
.btn-print { border:none; background:linear-gradient(135deg, #059669, #10b981); color:#fff; padding:14px 28px; font-size:15px; font-weight:800; border-radius:10px; cursor:pointer; font-family:'Outfit', sans-serif; display:inline-flex; align-items:center; gap:10px; box-shadow:0 6px 16px rgba(5,150,105,0.25); margin-bottom:30px; transition:all 0.2s; outline:none; }
.btn-print:hover { transform:translateY(-3px); box-shadow:0 8px 20px rgba(5,150,105,0.3); }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="no-print" style="text-align:center;">
        <button class="btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer ou Sauvegarder en PDF</button>
    </div>
    
<div class="container">
    <div class="header">
        <div class="header-left">
            <h1><?php echo htmlspecialchars($fac->ref); ?></h1>
            <p>Facture Fournisseur (Import)</p>
            <div>
                <?php
                if ($fac->statut == 0) echo '<span class="badge bd-draft"><i class="fa-solid fa-pen"></i> Brouillon</span>';
                elseif ($remaining_dev <= 0.01) echo '<span class="badge bd-success"><i class="fa-solid fa-check"></i> Payée & Soldée</span>';
                else echo '<span class="badge bd-warning"><i class="fa-solid fa-clock"></i> En cours de paiement</span>';
                ?>
            </div>
        </div>
        <div class="header-right">
            <h2 style="margin:0;color:#0f172a;font-size:28px;font-weight:800;letter-spacing:-0.5px;"><?php echo dol_escape_htmltag($fac->thirdparty->name); ?></h2>
            <div style="color:#64748b;margin-top:10px;font-size:15px;font-weight:600;"><i class="fa-solid fa-calendar-day"></i> <?php echo dol_print_date($fac->date, 'day'); ?></div>
        </div>
    </div>

    <div class="info-row">
        <div class="info-box">
            <h3><i class="fa-solid fa-money-bill-wave"></i> Total Facturé</h3>
            <p><?php echo price($total_dev).' '.$CC; ?></p>
            <div class="sub"><?php echo price($fac->total_ht); ?> MRU</div>
        </div>
        <div class="info-box">
            <h3><i class="fa-solid fa-coins"></i> Devise / Taux</h3>
            <p><?php echo $CC; ?></p>
            <div class="sub">1 <?php echo $CC; ?> = <?php echo number_format($ER, 4); ?> MRU</div>
        </div>
        <div class="info-box">
            <h3><i class="fa-solid fa-scale-unbalanced"></i> Reste à Payer</h3>
            <p style="color:<?php echo $remaining_dev > 0.01 ? '#ef4444' : '#059669'; ?>;"><?php echo price($remaining_dev).' '.$CC; ?></p>
            <div class="sub">Payé : <?php echo price($total_paid_dev).' '.$CC; ?></div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Produit / Description</th>
                <th class="right">CBM/Crt</th>
                <th>Cartons</th>
                <th class="right">Qté Unité</th>
                <th class="right">P.U (<?php echo $CC; ?>)</th>
                <th class="right">Total (<?php echo $CC; ?>)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($fac->lines as $line) {
                if ($line->product_type == 1 || $line->fk_product == 0) continue;
                $rli = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_invoice_line_info WHERE fk_facture_fourn_det = " . (int) $line->id);
                $li = ($rli !== false) ? $db->fetch_object($rli) : null;
                $cbm_c = $li ? (float) $li->cbm_carton : 0;
                $nb_c = $li ? (float) $li->nb_cartons : 0;
                $pu_d = $li ? (float) $li->pu_devise : (($ER > 0) ? ($line->pu_ht / $ER) : $line->pu_ht);
                $total_d = $pu_d * $line->qty;
                
                print '<tr>';
                print '<td><strong style="color:#1e293b;font-size:15px;">'.dol_escape_htmltag($line->product_label ?: $line->description).'</strong>';
                if ($line->product_ref) print '<br><span style="color:#94a3b8;font-size:12px;font-weight:600;">Ref: '.dol_escape_htmltag($line->product_ref).'</span>';
                print '</td>';
                print '<td class="right" style="color:#64748b;">'.($cbm_c > 0 ? number_format($cbm_c, 4) : '—').'</td>';
                print '<td>'.($nb_c > 0 ? number_format($nb_c, 0) : '—').'</td>';
                print '<td class="right" style="color:#3b82f6;font-weight:700;">'.number_format($line->qty, 0).'</td>';
                print '<td class="right">'.price($pu_d).'</td>';
                print '<td class="right" style="font-weight:800;color:#0f172a;font-size:15px;">'.price($total_d).'</td>';
                print '</tr>';
            }
            ?>
        </tbody>
    </table>

    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
        <div style="flex:1; padding-right:50px;">
            <?php if (count($exp_nous) > 0): ?>
            <div style="background:#fefce8; border:2px dashed #fde047; padding:20px; border-radius:16px;">
                <h4 style="margin:0 0 12px; color:#b45309; font-size:13px; text-transform:uppercase; font-weight:800; letter-spacing:1px;"><i class="fa-solid fa-circle-exclamation"></i> Charges Annexes  <span style="font-size:10px;display:block;color:#ca8a04;margin-top:2px;font-weight:600;letter-spacing:0;">Non incluses dans le total fournisseur (Dépenses pour NOUS)</span></h4>
                <table style="width:100%; font-size:14px; color:#92400e; border-collapse:collapse; font-weight:600;">
                <?php foreach ($exp_nous as $e): ?>
                    <tr>
                        <td style="padding:6px 0; border-bottom:1px solid #fef08a;"><i class="fa-solid fa-caret-right" style="color:#facc15;margin-right:6px;"></i> <?php echo dol_escape_htmltag($e->label); ?></td>
                        <td style="text-align:right; padding:6px 0; border-bottom:1px solid #fef08a;"><?php echo price($e->amount).' <span style="font-size:11px;">'.$e->currency_code.'</span>'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="totals">
            <div class="total-line">
                <span>Total des Produits HT</span>
                <span><?php echo price($total_dev).' '.$CC; ?></span>
            </div>
            <div class="total-line bold">
                <span>TOTAL FOURNISSEUR</span>
                <span><?php echo price($total_dev).' '.$CC; ?></span>
            </div>
            <div class="total-line grand">
                <span style="display:flex;align-items:center;gap:10px;"><i class="fa-solid fa-scale-unbalanced"></i> RESTE</span>
                <span><?php echo price($remaining_dev).' '.$CC; ?></span>
            </div>
        </div>
    </div>
    
    <div class="footer">
        Document formaté pour impression &bull; Logiciel de Transit &bull; Généré le <?php echo date('d/m/Y à H:i'); ?>
    </div>
</div>
</body>
</html>
