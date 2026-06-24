<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/paiementfourn.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';

if (!$user->admin) {
    accessforbidden("Accès réservé aux administrateurs.");
}

$langs->loadLangs(array("companies", "bills", "banks", "exportation@exportation"));
$socid = GETPOST('socid', 'int');
$action = GETPOST('action', 'alpha');
$form = new Form($db);

// ═══ ACTION: Make Payment ═══
if ($action == 'make_payment' && $socid > 0) {
    $fk_bank = GETPOST('fk_bank', 'int');
    $amount_devise = (float) GETPOST('amount', 'alphanohtml');
    $currency = GETPOST('currency', 'alpha') ?: 'MRU';
    $exchange_rate = (float) GETPOST('exchange_rate', 'alphanohtml') ?: 1;
    if ($currency == 'MRU') $exchange_rate = 1;

    $amount_mru = $amount_devise * $exchange_rate;
    $fk_facturefourn = GETPOST('fk_facturefourn', 'int');
    $datepaye = GETPOST('datepaye', 'alpha');
    $note = GETPOST('note', 'alpha');

    if ($amount_mru > 0 && $fk_bank > 0 && $fk_facturefourn > 0) {
        $db->begin();

        $facture_fourn = new FactureFournisseur($db);
        $facture_fourn->fetch($fk_facturefourn);

        $bank_account = new Account($db);
        $bank_account->fetch($fk_bank);

        $pf = new PaiementFourn($db);
        $pf->datepaye = strtotime($datepaye) ?: dol_now();
        $pf->amounts = array($fk_facturefourn => $amount_mru);
        $pf->multicurrency_amounts = array($fk_facturefourn => $amount_devise);
        $pf->multicurrency_code = array($fk_facturefourn => $currency);
        $pf->multicurrency_tx = array($fk_facturefourn => $exchange_rate);
        $pf->fk_account = $fk_bank;
        $pf->paiementid = 1;

        if ($bank_account->type == Account::TYPE_CASH) {
            $pf->paiementcode = 'LIQ';
        } else {
            $pf->paiementcode = 'VIR';
        }

        $pf->num_paiement = "EXT-" . time();
        $pf->note_private = $note;

        $pid = $pf->create($user);
        if ($pid > 0) {
            $res = $pf->addPaymentToBank($user, 'payment_supplier', '(Paiement Fournisseur Externe)', $fk_bank, '', '');
            if ($res > 0) {
                $sql_custom = "INSERT INTO " . MAIN_DB_PREFIX . "exportation_invoice_payments (fk_facture_fourn, fk_paiement_fourn, fk_bank, datep, amount, currency_code, exchange_rate, amount_local, note) ";
                $sql_custom .= " VALUES (" . (int)$fk_facturefourn . ", " . (int)$pid . ", " . (int)$fk_bank . ", '" . $db->idate($pf->datepaye) . "', " . $amount_devise . ", '" . $db->escape($currency) . "', " . $exchange_rate . ", " . $amount_mru . ", '" . $db->escape($note) . "')";
                $db->query($sql_custom);

                $db->commit();
                setEventMessages("Paiement enregistré avec succès (" . price($amount_mru) . " MRU).", null, 'mesgs');
            } else {
                $db->rollback();
                $error_msg = !empty($pf->error) ? $pf->error : "Erreur lors de l'enregistrement bancaire";
                if (!empty($pf->errors) && is_array($pf->errors)) {
                    $error_msg = implode(", ", $pf->errors);
                }
                setEventMessages($error_msg, null, 'errors');
            }
        } else {
            $db->rollback();
            $error_msg = !empty($pf->error) ? $pf->error : "Erreur de paiement";
            if (!empty($pf->errors) && is_array($pf->errors)) {
                $error_msg = implode(", ", $pf->errors);
            }
            setEventMessages($error_msg, null, 'errors');
        }
    } else {
        setEventMessages("Veuillez remplir tous les champs obligatoires pour le paiement.", null, 'errors');
    }
    header("Location: external_supplier.php?socid=" . $socid);
    exit;
}

// ═══ ACTION: Mass Payment ═══
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
            $pf->paiementid = 1;
            $pf->num_paiement = '';
            $pf->note_public = 'Paiement en masse (Fournisseur Externe)';

            $pid = $pf->create($user);
            if ($pid > 0) {
                $result = $pf->addPaymentToBank($user, 'payment_supplier', '(Paiement en masse fourn. ext.)', $fk_bank, '', '');
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
    header("Location: external_supplier.php?socid=" . $socid);
    exit;
}

// ═══ ACTION: Create External Supplier ═══
if ($action == 'create_supplier' && $user->admin) {
    $nom = GETPOST('nom', 'alpha');
    $address = GETPOST('address', 'alpha');
    $town = GETPOST('town', 'alpha');
    $country = GETPOST('country', 'alpha');
    $phone = GETPOST('phone', 'alpha');
    $email = GETPOST('email', 'alpha');

    $s = new Societe($db);
    $s->name = $nom;
    $s->address = $address;
    $s->town = $town;
    $s->country = $country;
    $s->phone = $phone;
    $s->email = $email;
    $s->client = 0;
    $s->fournisseur = 0; // IMPORTANT: Hide from native Dolibarr invoice dropdowns
    
    $sid = $s->create($user);
    if ($sid > 0) {
        // Force external type
        $db->query("UPDATE " . MAIN_DB_PREFIX . "societe SET exportation_type = 'EXTERNE' WHERE rowid = " . $sid);
        setEventMessages("Fournisseur externe créé avec succès.", null, 'mesgs');
        header("Location: external_supplier.php?socid=" . $sid);
        exit;
    } else {
        setEventMessages("Erreur de création: " . $s->error, null, 'errors');
    }
}

llxHeader('', "Fournisseur Externe — الموردين الخارجيين", '');
print '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">';
print '<div id="es">';
print '<style>
#es{max-width:1400px;margin:0 auto;font-family:"Outfit",sans-serif;padding:20px;color:#1e2a3a}
.ec{background:#fff;border-radius:16px;padding:24px 26px;box-shadow:0 2px 18px rgba(30,42,58,.06);border:1px solid #eef2f7;margin-bottom:20px}
.eh{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #f0f4f8;gap:10px;flex-wrap:wrap}
.eh-l{display:flex;align-items:center;gap:10px}
.ei{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0}
.ei.ro{background:linear-gradient(135deg,#f43f5e,#e11d48)}
.ei.bl{background:linear-gradient(135deg,#3b82f6,#2563eb)}
.ei.gr{background:linear-gradient(135deg,#10b981,#059669)}
.ei.pu{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.et{font-size:16px;font-weight:800}.es{font-size:11px;color:#94a3b8;margin-top:1px}
.tb{width:100%;border-collapse:collapse;font-size:13px}
.tb th{background:#f8fafc;padding:10px 12px;text-align:left;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid #eef2f7}
.tb td{padding:12px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.tb tr:hover td{background:#fafbfd}
.si{padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:"Outfit",sans-serif;background:#fff;width:100%}
.si:focus{border-color:#3b82f6;outline:none;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.sb{background:#3b82f6;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px;transition:.15s;display:inline-flex;align-items:center;justify-content:center;gap:6px;text-decoration:none}
.sb:hover{filter:brightness(1.08);transform:translateY(-1px)}
.sb.gr{background:linear-gradient(135deg,#10b981,#059669)}
.stats-grid{display:grid;grid-template-columns:repeat(3, 1fr);gap:16px;margin-bottom:24px}
.stat-card{background:#f8fafc;border-radius:12px;padding:20px;border:1px solid #eef2f7;text-align:center}
.stat-val{font-size:24px;font-weight:800;margin-bottom:4px}
.stat-lbl{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px}
.badge{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;display:inline-block}
</style>';

if ($socid <= 0) {
    print '<div class="ec"><div class="eh"><div class="eh-l"><div class="ei ro"><i class="fa-solid fa-earth-americas"></i></div><div><div class="et">Fournisseurs Externes — الموردين الخارجيين</div><div class="es">Sélectionnez ou créez un fournisseur (Uniquement pour Dossiers d\'Achat)</div></div></div></div>';
    
    // Quick Creation Form
    print '<div class="ec" style="border:2px dashed #bfdbfe;background:#f8fafc;"><strong style="display:block;margin-bottom:12px;color:#3b82f6;"><i class="fa-solid fa-plus-circle"></i> Créer un Nouveau Fournisseur Externe</strong>';
    print '<form method="POST" action="external_supplier.php">';
    print '<input type="hidden" name="action" value="create_supplier">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:10px;">';
    print '<input type="text" name="nom" class="si" placeholder="Nom de la société *" required>';
    print '<input type="text" name="town" class="si" placeholder="Ville">';
    print '<input type="text" name="country" class="si" placeholder="Pays">';
    print '<button type="submit" class="sb gr"><i class="fa-solid fa-check"></i> Créer & Cacher de Dolibarr</button>';
    print '</div></form></div>';

    // List of exclusive external suppliers
    $sql = "SELECT s.rowid, s.nom, s.address, s.town, c.label as country,
            COALESCE(SUM(f.total_ht), 0) as total_achats,
            COUNT(f.rowid) as nb_factures
            FROM " . MAIN_DB_PREFIX . "societe s
            LEFT JOIN " . MAIN_DB_PREFIX . "facture_fourn f ON f.fk_soc = s.rowid
            LEFT JOIN " . MAIN_DB_PREFIX . "c_country c ON c.rowid = s.fk_pays
            WHERE s.exportation_type = 'EXTERNE'
            AND s.entity IN (" . getEntity('societe') . ")
            GROUP BY s.rowid, s.nom, s.address, s.town, c.label
            ORDER BY s.nom ASC";
    
    $res = $db->query($sql);
    if ($res) {
        if ($db->num_rows($res) > 0) {
            print '<table class="tb"><thead><tr><th>Fournisseur</th><th>Localisation</th><th>Dossiers/Factures</th><th>Total Achats</th><th></th></tr></thead><tbody>';
            while ($obj = $db->fetch_object($res)) {
                print '<tr><td><strong style="color:#1e2a3a;font-size:14px;">' . dol_escape_htmltag($obj->nom) . '</strong></td>';
                print '<td>' . dol_escape_htmltag($obj->town . ($obj->country ? ', ' . $obj->country : '')) . '</td>';
                print '<td><span class="badge" style="background:#eff6ff;color:#3b82f6;">' . $obj->nb_factures . ' dossiers</span></td>';
                print '<td style="font-weight:700;color:#1e2a3a;">' . price($obj->total_achats) . '</td>';
                print '<td><a href="?socid=' . $obj->rowid . '" class="sb" style="padding:6px 12px;"><i class="fa-solid fa-eye"></i></a></td></tr>';
            }
            print '</tbody></table>';
        } else {
            print '<div style="text-align:center;padding:40px;color:#64748b;background:#f8fafc;border-radius:12px;border:1px dashed #cbd5e1;">';
            print '<i class="fa-solid fa-folder-open" style="font-size:32px;margin-bottom:12px;display:block;color:#cbd5e1;"></i>';
            print 'Aucun fournisseur externe trouvé.<br><span style="font-size:12px;">Utilisez le formulaire ci-dessus pour en créer un.</span></div>';
        }
    } else {
        print '<div class="error">' . $db->lasterror() . '</div>';
    }
    print '</div>';
} else {
    $soc = new Societe($db);
    $soc->fetch($socid);

    print '<a href="' . $_SERVER['PHP_SELF'] . '" style="display:inline-flex;align-items:center;gap:6px;color:#64748b;font-weight:600;font-size:13px;text-decoration:none;margin-bottom:16px;"><i class="fa-solid fa-arrow-left"></i> Retour à la liste</a>';

    // Headers
    print '<div style="display:grid;grid-template-columns:1fr 350px;gap:20px;">';
    
    // Left col: Info & Stats
    print '<div>';
    print '<div class="ec"><div class="eh"><div class="eh-l"><div class="ei ro"><i class="fa-solid fa-building"></i></div><div><div class="et">' . dol_escape_htmltag($soc->name) . '</div><div class="es">Fiche Fournisseur Externe</div></div></div></div>';
    
    // Calculate stats grouped by currency
    // For each invoice: read its currency (default MRU) and exchange rate from exportation_invoice_info
    // Convert total & paid into invoice's currency so cumulative figures are devise-correct
    $stats_by_curr = array(); // [currency => ['nb','total','paid']]
    $nb_total = 0;
    $rs = $db->query("
        SELECT f.rowid as fid, f.total_ttc,
               COALESCE(info.currency_code,'MRU') as curr,
               COALESCE(info.exchange_rate,1) as rate,
               COALESCE((SELECT SUM(amount) FROM " . MAIN_DB_PREFIX . "paiementfourn_facturefourn WHERE fk_facturefourn = f.rowid),0) as paid_mru
        FROM " . MAIN_DB_PREFIX . "facture_fourn f
        LEFT JOIN " . MAIN_DB_PREFIX . "exportation_invoice_info info ON info.fk_facture_fourn = f.rowid
        WHERE f.fk_soc = " . (int)$socid . " AND f.fk_statut > 0
    ");
    while ($rs && ($row = $db->fetch_object($rs))) {
        $nb_total++;
        $curr = $row->curr ?: 'MRU';
        $rate = (float)$row->rate ?: 1;
        if (!isset($stats_by_curr[$curr])) $stats_by_curr[$curr] = array('nb'=>0,'total'=>0,'paid'=>0);
        $stats_by_curr[$curr]['nb']++;

        $total_devise = ($curr == 'MRU' || $rate <= 0) ? (float)$row->total_ttc : (float)$row->total_ttc / $rate;
        $stats_by_curr[$curr]['total'] += $total_devise;

        // Paid in same currency from exportation_invoice_payments (devise amount, direct sum)
        $r_same = $db->fetch_object($db->query("SELECT COALESCE(SUM(amount),0) as p FROM " . MAIN_DB_PREFIX . "exportation_invoice_payments WHERE fk_facture_fourn = " . (int)$row->fid . " AND currency_code = '" . $db->escape($curr) . "'"));
        $paid_same = $r_same ? (float)$r_same->p : 0;

        // Paid in other currencies → MRU amount stored at payment time, convert to invoice currency
        $r_other = $db->fetch_object($db->query("SELECT COALESCE(SUM(amount_local),0) as p FROM " . MAIN_DB_PREFIX . "exportation_invoice_payments WHERE fk_facture_fourn = " . (int)$row->fid . " AND currency_code != '" . $db->escape($curr) . "'"));
        $other_mru = $r_other ? (float)$r_other->p : 0;
        $paid_other_in_curr = ($curr == 'MRU') ? $other_mru : ($rate > 0 ? $other_mru / $rate : 0);

        // Total tracked in MRU = actual sum of amount_local (uses payment-time rate, not invoice rate)
        $r_tracked = $db->fetch_object($db->query("SELECT COALESCE(SUM(amount_local),0) as p FROM " . MAIN_DB_PREFIX . "exportation_invoice_payments WHERE fk_facture_fourn = " . (int)$row->fid));
        $tracked_mru = $r_tracked ? (float)$r_tracked->p : 0;

        // Legacy: native paiementfourn rows not recorded in our custom tracking table
        $legacy_mru = max(0, (float)$row->paid_mru - $tracked_mru);
        $legacy_in_curr = ($curr == 'MRU') ? $legacy_mru : ($rate > 0 ? $legacy_mru / $rate : 0);

        $stats_by_curr[$curr]['paid'] += $paid_same + $paid_other_in_curr + $legacy_in_curr;
    }

    // Devise symbol helper
    $sym = array('MRU'=>'MRU','USD'=>'$','EUR'=>'€','RMB'=>'¥');
    $fmt = function($v) { return number_format((float)$v, 2, '.', ' '); };

    print '<div class="stats-grid">';
    print '<div class="stat-card"><div class="stat-val" style="color:#1e2a3a;">' . (int)$nb_total . '</div><div class="stat-lbl">Dossiers</div></div>';

    // Total Payé — per currency
    print '<div class="stat-card"><div class="stat-lbl" style="margin-bottom:6px;">Total Payé</div>';
    if (count($stats_by_curr) === 0) {
        print '<div class="stat-val" style="color:#10b981;font-size:18px;">0.00</div>';
    } else {
        foreach ($stats_by_curr as $cur => $vals) {
            $sm = $sym[$cur] ?? $cur;
            print '<div style="color:#10b981;font-weight:800;font-size:' . (count($stats_by_curr) > 1 ? '15' : '22') . 'px;line-height:1.3;">' . $fmt($vals['paid']) . ' <span style="font-size:11px;color:#64748b;">' . $sm . '</span></div>';
        }
    }
    print '</div>';

    // Reste à payer — per currency
    print '<div class="stat-card" style="border-color:#fecdd3;background:#fff5f6;"><div class="stat-lbl" style="color:#be123c;margin-bottom:6px;">Reste à payer</div>';
    if (count($stats_by_curr) === 0) {
        print '<div class="stat-val" style="color:#e11d48;font-size:18px;">0.00</div>';
    } else {
        foreach ($stats_by_curr as $cur => $vals) {
            $sm = $sym[$cur] ?? $cur;
            $remain = max(0, $vals['total'] - $vals['paid']);
            print '<div style="color:#e11d48;font-weight:800;font-size:' . (count($stats_by_curr) > 1 ? '15' : '22') . 'px;line-height:1.3;">' . $fmt($remain) . ' <span style="font-size:11px;color:#64748b;">' . $sm . '</span></div>';
        }
    }
    print '</div>';
    print '</div>';
    
    // Info details
    print '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">';
    print '<div style="padding:12px;background:#f8fafc;border-radius:8px;"><strong>Adresse :</strong><br>' . dol_escape_htmltag($soc->address ?: 'Non renseignée') . '</div>';
    print '<div style="padding:12px;background:#f8fafc;border-radius:8px;"><strong>Ville/Pays :</strong><br>' . dol_escape_htmltag($soc->town ?: '') . ' ' . dol_escape_htmltag($soc->country ?: '') . '</div>';
    print '<div style="padding:12px;background:#f8fafc;border-radius:8px;"><strong>Email :</strong><br>' . dol_escape_htmltag($soc->email ?: 'Non renseigné') . '</div>';
    print '<div style="padding:12px;background:#f8fafc;border-radius:8px;"><strong>Téléphone :</strong><br>' . dol_escape_htmltag($soc->phone ?: 'Non renseigné') . '</div>';
    print '</div>';
    print '</div>';
    
    // Unpaid Invoices List
    print '<div class="ec"><div class="eh"><div class="eh-l"><div class="ei bl"><i class="fa-solid fa-file-invoice"></i></div><div><div class="et">Historique des Dossiers / Factures</div></div></div></div>';
    
    $resf = $db->query("SELECT f.rowid, f.ref, f.datef, f.total_ttc, f.fk_statut,
                        COALESCE((SELECT SUM(amount) FROM " . MAIN_DB_PREFIX . "paiementfourn_facturefourn WHERE fk_facturefourn = f.rowid),0) as paid_mru,
                        COALESCE(info.currency_code,'MRU') as curr,
                        COALESCE(info.exchange_rate,1) as rate
                        FROM " . MAIN_DB_PREFIX . "facture_fourn f
                        LEFT JOIN " . MAIN_DB_PREFIX . "exportation_invoice_info info ON info.fk_facture_fourn = f.rowid
                        WHERE f.fk_soc = " . $socid . " ORDER BY f.datef DESC");

    print '<table class="tb" data-pg="1" data-pg-default="20"><thead><tr><th>Dossier</th><th>Date</th><th>Devise</th><th>Total</th><th>Payé</th><th>Reste</th></tr></thead><tbody>';
    $has_f = false;
    while ($resf && $f = $db->fetch_object($resf)) {
        $has_f = true;
        $curr = $f->curr ?: 'MRU';
        $rate = (float)$f->rate ?: 1;
        $sm = $sym[$curr] ?? $curr;
        $total_dev = ($curr == 'MRU' || $rate <= 0) ? (float)$f->total_ttc : (float)$f->total_ttc / $rate;

        // Paid in devise: same currency from invoice_payments + other currencies converted + legacy native
        $r_same = $db->fetch_object($db->query("SELECT COALESCE(SUM(amount),0) as p FROM " . MAIN_DB_PREFIX . "exportation_invoice_payments WHERE fk_facture_fourn = " . (int)$f->rowid . " AND currency_code = '" . $db->escape($curr) . "'"));
        $paid_same = $r_same ? (float)$r_same->p : 0;
        $r_other = $db->fetch_object($db->query("SELECT COALESCE(SUM(amount_local),0) as p FROM " . MAIN_DB_PREFIX . "exportation_invoice_payments WHERE fk_facture_fourn = " . (int)$f->rowid . " AND currency_code != '" . $db->escape($curr) . "'"));
        $other_mru = $r_other ? (float)$r_other->p : 0;
        $tracked_mru = $paid_same * ($curr == 'MRU' ? 1 : $rate) + $other_mru;
        $legacy_mru = max(0, (float)$f->paid_mru - $tracked_mru);
        $paid_dev = $paid_same
            + (($curr == 'MRU') ? $other_mru : ($rate > 0 ? $other_mru / $rate : 0))
            + (($curr == 'MRU') ? $legacy_mru : ($rate > 0 ? $legacy_mru / $rate : 0));

        $rem_dev = $total_dev - $paid_dev;

        print '<tr><td><a href="' . DOL_URL_ROOT . '/fourn/facture/card.php?facid=' . $f->rowid . '" style="color:#3b82f6;font-weight:700;">' . $f->ref . '</a></td>';
        print '<td>' . dol_print_date($db->jdate($f->datef), 'day') . '</td>';
        print '<td><span style="background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;">' . $sm . '</span></td>';
        print '<td style="font-weight:700;">' . $fmt($total_dev) . '</td>';
        print '<td style="color:#10b981;">' . $fmt($paid_dev) . '</td>';
        print '<td style="font-weight:800;color:' . ($rem_dev > 0.01 ? '#e11d48' : '#cbd5e1') . ';">' . $fmt($rem_dev) . '</td></tr>';
    }
    if (!$has_f) print '<tr><td colspan="6" style="text-align:center;padding:16px;">Aucun dossier trouvé.</td></tr>';
    print '</tbody></table></div>';
    print '</div>';

    // Right col: Make Payment & Payment History
    print '<div>';
    
    // Payment Form
    print '<div class="ec" style="border:2px solid #bfdbfe;background:linear-gradient(180deg,#fff,#f8fafc);"><div class="eh"><div class="eh-l"><div class="ei gr"><i class="fa-solid fa-money-bill-transfer"></i></div><div><div class="et">Effectuer un paiement</div><div class="es">Enregistrer un décaissement</div></div></div></div>';
    
    // Get unpaid invoices for select, with devise info
    $unpaid = array();
    $resu = $db->query("SELECT f.rowid, f.ref,
                        f.total_ttc - COALESCE((SELECT SUM(amount) FROM " . MAIN_DB_PREFIX . "paiementfourn_facturefourn WHERE fk_facturefourn = f.rowid),0) as remain_mru,
                        COALESCE(info.currency_code,'MRU') as curr,
                        COALESCE(info.exchange_rate,1) as rate
                        FROM " . MAIN_DB_PREFIX . "facture_fourn f
                        LEFT JOIN " . MAIN_DB_PREFIX . "exportation_invoice_info info ON info.fk_facture_fourn = f.rowid
                        WHERE f.fk_soc = " . $socid . " AND f.fk_statut = 1
                        HAVING remain_mru > 0.01 ORDER BY f.datef ASC");
    while ($resu && $u = $db->fetch_object($resu)) { $unpaid[] = $u; }

    if (count($unpaid) > 0) {
        print '<form method="POST" action="external_supplier.php?socid=' . $socid . '">';
        print '<input type="hidden" name="action" value="make_payment">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';

        print '<div style="margin-bottom:12px;"><label style="font-size:11px;font-weight:700;color:#64748b;display:block;margin-bottom:4px;">Dossier / Facture *</label>';
        print '<select name="fk_facturefourn" class="si" required><option value="">— Choisir un dossier —</option>';
        foreach($unpaid as $u) {
            $cur = $u->curr ?: 'MRU';
            $r = (float)$u->rate ?: 1;
            $rem_dev = ($cur == 'MRU' || $r <= 0) ? (float)$u->remain_mru : (float)$u->remain_mru / $r;
            $smu = $sym[$cur] ?? $cur;
            print '<option value="' . $u->rowid . '">' . $u->ref . ' (Reste: ' . $fmt($rem_dev) . ' ' . $smu . ')</option>';
        }
        print '</select></div>';

        print '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">';
        print '<div><label style="font-size:11px;font-weight:700;color:#64748b;display:block;margin-bottom:4px;">Devise *</label>';
        print '<select name="currency" id="currency" class="si" onchange="toggleER()">';
        print '<option value="MRU">MRU</option><option value="USD">USD</option><option value="EUR">EUR</option><option value="RMB">RMB</option>';
        print '</select></div>';
        print '<div id="div_er" style="display:none;"><label style="font-size:11px;font-weight:700;color:#64748b;display:block;margin-bottom:4px;">Taux *</label>';
        print '<input type="number" step="0.000001" name="exchange_rate" id="exchange_rate" class="si" value="1" oninput="calcTotal()"></div>';
        print '</div>';
        
        print '<div style="margin-bottom:12px;"><label id="lbl_amount" style="font-size:11px;font-weight:700;color:#64748b;display:block;margin-bottom:4px;">Montant à payer (MRU) *</label>';
        print '<div style="position:relative;"><span id="symbol" style="position:absolute;left:12px;top:10px;color:#94a3b8;font-weight:700;font-size:14px;">MRU</span>';
        print '<input type="number" step="0.01" name="amount" id="amount" class="si" style="font-size:18px;font-weight:800;color:#10b981;padding-left:55px;" required oninput="calcTotal()"></div></div>';
        
        print '<div id="conv_box" style="display:none; margin-bottom:12px; padding:10px; background:#f0f9ff; border-radius:8px; border:1px solid #bae6fd;">';
        print '<div style="font-size:12px; color:#0369a1; font-weight:600;"><i class="fa-solid fa-calculator"></i> Conversion : <span id="span_total">0.00</span> MRU</div></div>';

        print '<div style="margin-bottom:12px;"><label style="font-size:11px;font-weight:700;color:#64748b;display:block;margin-bottom:4px;">Date *</label>';
        print '<input type="date" name="datepaye" class="si" value="' . date('Y-m-d') . '" required></div>';
        
        print '<div style="margin-bottom:12px;"><label style="font-size:11px;font-weight:700;color:#64748b;display:block;margin-bottom:4px;">Caisse / Banque MRU *</label>';
        print '<select name="fk_bank" class="si" required><option value="">— Choisir la banque —</option>';
        $rb = $db->query("SELECT rowid, label, currency_code FROM " . MAIN_DB_PREFIX . "bank_account WHERE entity = " . (int)$conf->entity . " AND clos = 0 ORDER BY label");
        while ($rb && $bk = $db->fetch_object($rb)) {
            print '<option value="' . $bk->rowid . '">' . dol_escape_htmltag($bk->label) . ' ('.$bk->currency_code.')</option>';
        }
        print '</select></div>';
        
        print '<div style="margin-bottom:16px;"><label style="font-size:11px;font-weight:700;color:#64748b;display:block;margin-bottom:4px;">Note (Optionnel)</label>';
        print '<input type="text" name="note" class="si" placeholder="Ex: Transfert virement #1234"></div>';
        
        print '<button type="submit" class="sb gr" style="width:100%;font-size:15px;padding:12px;"><i class="fa-solid fa-check"></i> Enregistrer Paiement</button>';
        print '</form>';

        print '<script>
        function toggleER() {
            var c = document.getElementById("currency").value;
            var d = document.getElementById("div_er");
            var s = document.getElementById("symbol");
            var l = document.getElementById("lbl_amount");
            var cb = document.getElementById("conv_box");
            s.innerText = c;
            if(c==="MRU") {
                d.style.display="none"; cb.style.display="none"; l.innerText="Montant à payer (MRU) *";
                document.getElementById("exchange_rate").value=1;
            } else {
                d.style.display="block"; cb.style.display="block"; l.innerText="Montant en "+c+" *";
            }
            calcTotal();
        }
        function calcTotal() {
            var a = parseFloat(document.getElementById("amount").value) || 0;
            var r = parseFloat(document.getElementById("exchange_rate").value) || 1;
            document.getElementById("span_total").innerText = (a*r).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
        }
        </script>';
    } else {
        print '<div style="text-align:center;padding:20px 10px;color:#10b981;font-weight:700;background:#dcfce7;border-radius:10px;"><i class="fa-solid fa-circle-check" style="font-size:24px;display:block;margin-bottom:8px;"></i> Aucun solde dû pour ce fournisseur.</div>';
    }

    // Calculate total unpaid amount for mass payment
    $total_unpaid = 0;
    $res_total = $db->query("SELECT SUM(f.total_ttc - COALESCE((SELECT SUM(amount) FROM " . MAIN_DB_PREFIX . "paiementfourn_facturefourn WHERE fk_facturefourn = f.rowid),0)) as total
                             FROM " . MAIN_DB_PREFIX . "facture_fourn f
                             WHERE f.fk_soc = " . $socid . " AND f.fk_statut = 1");
    if ($res_total) {
        $obj_total = $db->fetch_object($res_total);
        $total_unpaid = max(0, (float)($obj_total->total ?: 0));
    }

    // Mass payment form
    if ($total_unpaid > 0.01) {
        print '<div class="ec" style="border:2px solid #fecdd3;background:linear-gradient(180deg,#fff,#fef2f2);margin-top:16px;"><div class="eh"><div class="eh-l"><div class="ei ro"><i class="fa-solid fa-money-bill-transfer"></i></div><div><div class="et">Paiement en Masse</div><div class="es">Répartition automatique (plus anciennes d\'abord)</div></div></div></div>';
        print '<form method="POST" action="external_supplier.php?socid=' . $socid . '">';
        print '<input type="hidden" name="action" value="mass_pay_supplier"><input type="hidden" name="token" value="' . newToken() . '">';
        print '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">';
        print '<div><label style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px;">Montant (MRU) *</label>';
        print '<input type="number" step="0.01" min="0.01" max="' . number_format($total_unpaid, 2, '.', '') . '" name="amount" placeholder="Max: ' . number_format($total_unpaid, 2, '.', '') . '" class="si" required></div>';
        print '<div><label style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px;">Caisse / Banque *</label>';
        print '<select name="fk_bank" class="si" required><option value="">— Sélectionner —</option>';
        $rb = $db->query("SELECT rowid, label FROM " . MAIN_DB_PREFIX . "bank_account WHERE entity = " . (int)$conf->entity . " AND clos = 0 ORDER BY label");
        while ($rb && ($bk = $db->fetch_object($rb))) print '<option value="' . $bk->rowid . '">' . dol_escape_htmltag($bk->label) . '</option>';
        print '</select></div></div>';
        print '<button type="submit" class="sb" style="background:linear-gradient(135deg,#f43f5e,#e11d48);width:100%;justify-content:center;padding:11px;"><i class="fa-solid fa-bolt"></i> Payer en Masse</button>';
        print '</form></div>';
    }

    print '</div>';
    
    // Payment History
    print '<div class="ec"><div class="eh"><div class="eh-l"><div class="ei pu"><i class="fa-solid fa-clock-rotate-left"></i></div><div><div class="et">Historique des Paiements</div></div></div></div>';
    
    $resp = $db->query("SELECT p.rowid, p.datep, pf.amount as amount_mru, f.ref, f.rowid as fid, ba.label as bank,
                        eip.amount as amount_dev, eip.currency_code as curr_paid
                        FROM " . MAIN_DB_PREFIX . "paiementfourn p
                        INNER JOIN " . MAIN_DB_PREFIX . "paiementfourn_facturefourn pf ON pf.fk_paiementfourn = p.rowid
                        INNER JOIN " . MAIN_DB_PREFIX . "facture_fourn f ON f.rowid = pf.fk_facturefourn
                        LEFT JOIN " . MAIN_DB_PREFIX . "exportation_invoice_payments eip ON eip.fk_paiement_fourn = p.rowid AND eip.fk_facture_fourn = f.rowid
                        LEFT JOIN " . MAIN_DB_PREFIX . "bank_url bu ON bu.url_id = p.rowid AND bu.type = 'payment_supplier'
                        LEFT JOIN " . MAIN_DB_PREFIX . "bank b ON b.rowid = bu.fk_bank
                        LEFT JOIN " . MAIN_DB_PREFIX . "bank_account ba ON ba.rowid = b.fk_account
                        WHERE f.fk_soc = " . $socid . " ORDER BY p.datep DESC LIMIT 20");

    print '<table class="tb"><thead><tr><th>Date</th><th>Dossier</th><th>Montant (devise)</th><th>Banque</th></tr></thead><tbody>';
    $has_p = false;
    while ($resp && $p = $db->fetch_object($resp)) {
        $has_p = true;
        if ($p->amount_dev !== null && $p->curr_paid) {
            $smp = $sym[$p->curr_paid] ?? $p->curr_paid;
            $disp_amount = $fmt($p->amount_dev) . ' ' . $smp;
            $mru_extra = ($p->curr_paid !== 'MRU') ? ' <span style="font-size:10px;color:#94a3b8;">(' . $fmt($p->amount_mru) . ' MRU)</span>' : '';
        } else {
            $disp_amount = $fmt($p->amount_mru) . ' MRU';
            $mru_extra = '';
        }
        print '<tr><td>' . dol_print_date($db->jdate($p->datep), 'day') . '</td>';
        print '<td><strong>' . $p->ref . '</strong></td>';
        print '<td style="color:#10b981;font-weight:700;">' . $disp_amount . $mru_extra . '</td>';
        print '<td style="font-size:11px;color:#64748b;">' . dol_escape_htmltag($p->bank ?: '-') . '</td></tr>';
    }
    if (!$has_p) print '<tr><td colspan="4" style="text-align:center;padding:16px;">Aucun paiement.</td></tr>';
    print '</tbody></table></div>';
    
    print '</div>'; // End right col
    print '</div>'; // End grid
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
