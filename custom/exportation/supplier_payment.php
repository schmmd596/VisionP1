<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/paiementfourn.class.php';

if ($user->admin == 0) {
    // Only internal providers can be paid by employee? Actually anyone can pay if they have access. Let's not restrict it yet.
}

$action = GETPOST('action', 'alpha');
$socid = GETPOST('socid', 'int');
$amount_form = (float) GETPOST('amount', 'alphanohtml');
$currency = GETPOST('currency', 'alpha') ?: 'MRU';
$exchange_rate = (float) GETPOST('exchange_rate', 'alphanohtml') ?: 1;
if ($currency == 'MRU') $exchange_rate = 1;

$amount_paid_mru = $amount_form * $exchange_rate;
$fk_bank = GETPOST('fk_bank', 'int');
$mode_reglement_id = 1; // Default to species or transfer. Let's use 1.

if ($action == 'add_mass_payment' && $socid > 0 && $amount_paid_mru > 0 && $fk_bank > 0) {
    // We get all unpaid invoices for this supplier
    // Only those that are > 0 remaining
    $sql = "SELECT f.rowid, f.ref, f.total_ttc, f.fk_statut FROM " . MAIN_DB_PREFIX . "facture_fourn as f WHERE f.fk_soc = ".$socid." AND f.fk_statut = 1 AND f.paye = 0 ORDER BY f.datef ASC, f.rowid ASC";
    $res = $db->query($sql);
    
    $amounts_to_pay = array();
    $invoices_to_pay = array();
    $remaining_budget = $amount_paid_mru;
    
    while ($res && ($fac_obj = $db->fetch_object($res)) && $remaining_budget > 0) {
        // Calculate remaining for this invoice
        $fac = new FactureFournisseur($db);
        $fac->fetch($fac_obj->rowid);
        
        // Sum existing payments
        $r_pay = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM " . MAIN_DB_PREFIX . "exportation_invoice_payments WHERE fk_facture_fourn = " . $fac->id);
        $op = $db->fetch_object($r_pay);
        $already_paid = $op ? (float)$op->t : 0;
        
        $fac_remaining = $fac->total_ht - $already_paid; // Custom module assumes total_ht is the total in MRU
        if ($fac_remaining <= 0) {
            continue; // Already fully paid by custom system
        }
        
        // How much can we pay?
        $payment_for_this = min($fac_remaining, $remaining_budget);
        
        $amounts_to_pay[$fac->id] = $payment_for_this;
        $invoices_to_pay[] = $fac;
        
        $remaining_budget -= $payment_for_this;
    }
    
    // Now create a single Dolibarr Paiement that links to all these invoices
    if (count($amounts_to_pay) > 0) {
        $paiement = new PaiementFourn($db);
        $paiement->datepaye = dol_now();
        $paiement->amounts = $amounts_to_pay; // Array of invoice rowid => amount
        $paiement->paiementid = $mode_reglement_id;
        $paiement->num_paiement = '';
        $paiement->note_public = 'Paiement en masse ('.$currency.') depuis le portail Logistique';
        
        $db->begin();
        $pid = $paiement->create($user, 1); // 1 = non-interactive
        if ($pid > 0) {
            $result = $paiement->addPaymentToBank($user, 'payment_supplier', '(Paiement de masse fournisseur)', $fk_bank, '', '');
            if ($result > 0) {
                // Now also add to our custom table
                foreach ($amounts_to_pay as $f_id => $amt) {
                    // $amt is in MRU. We want to record what it represents in the payment currency
                    $amt_devise = ($exchange_rate > 0) ? ($amt / $exchange_rate) : $amt;
                    
                    // If paying in MRU, we might want to check the invoice's original currency for record keeping,
                    // but the user's request is specifically about the payment currency.
                    $CC = $currency;
                    $ER = $exchange_rate;
                    
                    $sql_p = "INSERT INTO " . MAIN_DB_PREFIX . "exportation_invoice_payments (fk_facture_fourn, fk_paiement_fourn, amount, currency_code, exchange_rate, amount_local, datep, fk_bank, note)";
                    $sql_p .= " VALUES (".$f_id.", ".$pid.", ".$amt_devise.", '".$db->escape($CC)."', ".$ER.", ".$amt.", '".date('Y-m-d')."', ".(int)$fk_bank.", 'Mass payment')";
                    $db->query($sql_p);
                }
                $db->commit();
                setEventMessages("Paiement de masse ( ".price($amount_paid_mru)." MRU ) enregistré avec succès sur ".count($amounts_to_pay)." factures.", null, 'mesgs');
            } else {
                $db->rollback();
                setEventMessages("Erreur d'ajout à la banque: ".$paiement->error, null, 'errors');
            }
        } else {
            $db->rollback();
            setEventMessages("Erreur création paiement: ".$paiement->error, null, 'errors');
        }
    } else {
        setEventMessages("Aucune facture impayée trouvée pour ce fournisseur, ou montant insuffisant.", null, 'warnings');
    }
}

llxHeader('', "Paiement en Masse Fournisseur");
$form = new Form($db);
?>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
#mp{max-width:700px;margin:40px auto;font-family:"Outfit",sans-serif;color:#1e2a3a}
.ec{background:#fff;border-radius:16px;padding:34px;box-shadow:0 6px 24px rgba(30,42,58,.08);border:1px solid #eef2f7}
.eh{display:flex;align-items:center;margin-bottom:24px;padding-bottom:20px;border-bottom:2px solid #f0f4f8;gap:16px}
.ei{width:56px;height:56px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;background:linear-gradient(135deg,#10b981,#059669)}
.et{font-size:22px;font-weight:800}.es{font-size:14px;color:#64748b;margin-top:2px;font-weight:500}
.si{width:100%;padding:12px 16px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:15px;font-family:"Outfit",sans-serif;box-sizing:border-box;background:#f8fafc;transition:.15s}
.si:focus{border-color:#10b981;outline:none;background:#fff;box-shadow:0 0 0 4px rgba(16,185,129,.1)}
.sl{font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;display:block}
.sb{background:#10b981;color:#fff;border:none;padding:14px 24px;border-radius:10px;font-weight:800;font-size:15px;cursor:pointer;transition:.15s;display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%}
.sb:hover{background:#059669;transform:translateY(-2px);box-shadow:0 6px 16px rgba(16,185,129,.2)}
select.flat,#socid{padding:12px 16px!important;border:1.5px solid #e2e8f0!important;border-radius:10px!important;font-size:15px!important;font-family:"Outfit",sans-serif!important;background:#f8fafc!important}
</style>

<div id="mp">
    <div style="margin-bottom:20px;"><a href="supplier_invoice.php" style="color:#64748b;font-weight:600;text-decoration:none;"><i class="fa-solid fa-arrow-left"></i> Retour aux dossiers d'achat</a></div>
    
    <div class="ec">
        <div class="eh">
            <div class="ei"><i class="fa-solid fa-money-bills-wave"></i></div>
            <div><div class="et">Paiement Partagé Automatique</div><div class="es">Répartit un paiement unique sur les plus vieilles factures impayées.</div></div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_mass_payment">
            <input type="hidden" name="token" value="<?php echo newToken(); ?>">
            
            <div style="margin-bottom:20px;">
                <label class="sl">Sélectionnez le Fournisseur *</label>
                <?php
                $filter = ($user->admin) ? 'fournisseur=1' : 'fournisseur=1 AND client IN (1,3)';
                print $form->select_company($socid, 'socid', $filter, 1, 0, 1, array(), 0, 'si');
                ?>
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                <div>
                    <label class="sl">Devise du Paiement *</label>
                    <select name="currency" id="currency" class="si" onchange="toggleExchangeRate()">
                        <option value="MRU">MRU (Ouguiya)</option>
                        <option value="USD">USD (Dollar)</option>
                        <option value="EUR">EUR (Euro)</option>
                        <option value="RMB">RMB (Yuan)</option>
                    </select>
                </div>
                <div id="div_rate" style="display:none;">
                    <label class="sl">Taux de Change *</label>
                    <input type="number" step="0.000001" name="exchange_rate" id="exchange_rate" class="si" value="1" oninput="calculateTotal()">
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label class="sl" id="label_amount">Montant à Payer (MRU) *</label>
                <div style="position:relative;">
                    <span id="currency_symbol" style="position:absolute;left:16px;top:13px;color:#94a3b8;font-weight:700;">MRU</span>
                    <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="si" style="padding-left:60px;" required oninput="calculateTotal()">
                </div>
            </div>

            <div id="conversion_info" style="margin-bottom:20px; padding:12px; background:#f0f9ff; border-radius:10px; border:1px solid #bae6fd; display:none;">
                <div style="font-size:13px; color:#0369a1; font-weight:600;">
                    <i class="fa-solid fa-calculator"></i> Conversion : <span id="span_conversion">0.00</span> MRU
                    <div style="font-size:11px; font-weight:500; color:#0c4a6e; margin-top:4px;">Ce montant sera débité de la banque (MRU).</div>
                </div>
            </div>

            <div style="margin-bottom:30px;">
                <label class="sl">Source du Paiement (Caisse / Banque MRU) *</label>
                <select name="fk_bank" class="si" required>
                    <option value="">-- Sélectionnez une caisse --</option>
                    <?php
                    $rb = $db->query("SELECT rowid, ref, label, currency_code FROM " . MAIN_DB_PREFIX . "bank_account WHERE entity = " . (int) $conf->entity . " AND clos = 0 ORDER BY label");
                    while ($rb && ($bk = $db->fetch_object($rb))) {
                        print '<option value="'.$bk->rowid.'">'.$bk->label.' ('.$bk->currency_code.')</option>';
                    }
                    ?>
                </select>
            </div>
            
            <button type="submit" class="sb"><i class="fa-solid fa-bolt"></i> Appliquer et Répartir le Paiement</button>
        </form>
    </div>
</div>

<script>
function toggleExchangeRate() {
    var curr = document.getElementById('currency').value;
    var divRate = document.getElementById('div_rate');
    var symbol = document.getElementById('currency_symbol');
    var label = document.getElementById('label_amount');
    var convInfo = document.getElementById('conversion_info');

    symbol.innerText = curr;
    if (curr === 'MRU') {
        divRate.style.display = 'none';
        label.innerText = 'Montant à Payer (MRU) *';
        convInfo.style.display = 'none';
        document.getElementById('exchange_rate').value = 1;
    } else {
        divRate.style.display = 'block';
        label.innerText = 'Montant en ' + curr + ' *';
        convInfo.style.display = 'block';
        if (document.getElementById('exchange_rate').value == 1) {
            // Default rates if needed, or just let user enter
        }
    }
    calculateTotal();
}

function calculateTotal() {
    var curr = document.getElementById('currency').value;
    var amt = parseFloat(document.getElementById('amount').value) || 0;
    var rate = parseFloat(document.getElementById('exchange_rate').value) || 1;
    var totalMRU = amt * rate;

    if (curr !== 'MRU') {
        document.getElementById('span_conversion').innerText = totalMRU.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
}
</script>

<?php 
llxFooter();
$db->close();
?>
