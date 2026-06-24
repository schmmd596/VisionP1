<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

if (!$user->admin) {
    accessforbidden("Accès réservé aux administrateurs.");
}

$action = GETPOST('action', 'alpha');
$eid_sel = GETPOST('eid', 'int');
$date_filter = GETPOST('date_filter', 'alpha') ?: date('Y-m');

// ---------------------------------------------------------------------------
// Ensure schema (fallback so the page works without running install/run_db.php)
// ---------------------------------------------------------------------------
$db->query("CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."exportation_employees (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    name varchar(255) NOT NULL,
    salary double(24,8) DEFAULT 0,
    fk_user integer DEFAULT NULL,
    status tinyint DEFAULT 1,
    date_creation datetime,
    fk_user_creat integer,
    UNIQUE KEY uk_emp_fk_user (fk_user)
) ENGINE=innodb;");

$db->query("CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."exportation_employee_ops (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    fk_user integer NOT NULL,
    op_date date NOT NULL,
    amount double(24,8) DEFAULT 0,
    op_type varchar(20) DEFAULT 'SALAIRE',
    label varchar(255),
    fk_bank integer DEFAULT 0,
    fk_bank_line integer DEFAULT NULL,
    fk_user_creat integer,
    date_creation datetime
) ENGINE=innodb;");
// silently ignored if columns already exist
@$db->query("ALTER TABLE ".MAIN_DB_PREFIX."exportation_employee_ops ADD COLUMN fk_employee integer DEFAULT NULL");
@$db->query("ALTER TABLE ".MAIN_DB_PREFIX."exportation_employee_ops ADD COLUMN cycle_close tinyint DEFAULT 0");
@$db->query("ALTER TABLE ".MAIN_DB_PREFIX."exportation_employee_ops ADD COLUMN fk_bank_line integer DEFAULT NULL");

// Auto-migrate legacy users (employee=1) into exportation_employees
$rm = $db->query("SELECT u.rowid, u.login, u.firstname, u.lastname FROM ".MAIN_DB_PREFIX."user u LEFT JOIN ".MAIN_DB_PREFIX."exportation_employees e ON e.fk_user = u.rowid WHERE u.statut=1 AND u.admin=0 AND u.employee=1 AND e.rowid IS NULL");
while ($rm && ($urow = $db->fetch_object($rm))) {
    $nm = trim($urow->firstname.' '.$urow->lastname);
    if ($nm === '') $nm = $urow->login;
    $db->query("INSERT INTO ".MAIN_DB_PREFIX."exportation_employees (name, salary, fk_user, status, date_creation, fk_user_creat) VALUES ('".$db->escape($nm)."', 0, ".(int)$urow->rowid.", 1, NOW(), ".(int)$user->id.")");
}
// Backfill fk_employee on legacy operations
$db->query("UPDATE ".MAIN_DB_PREFIX."exportation_employee_ops o INNER JOIN ".MAIN_DB_PREFIX."exportation_employees e ON e.fk_user = o.fk_user SET o.fk_employee = e.rowid WHERE (o.fk_employee IS NULL OR o.fk_employee = 0)");

// Backfill fk_bank_line on legacy operations
$res_legacy = $db->query("SELECT o.rowid, o.op_date, o.amount, o.op_type, o.label, o.fk_bank FROM ".MAIN_DB_PREFIX."exportation_employee_ops o WHERE o.fk_bank > 0 AND (o.fk_bank_line IS NULL OR o.fk_bank_line = 0)");
while ($res_legacy && ($op_row = $db->fetch_object($res_legacy))) {
    $label_pattern = '('.$op_row->op_type.') '.$op_row->label;
    $sql_match = "SELECT rowid FROM ".MAIN_DB_PREFIX."bank WHERE fk_account = ".(int)$op_row->fk_bank." AND amount = ".(-(float)$op_row->amount)." AND dateo = '".$db->escape($op_row->op_date)."' AND label = '".$db->escape($label_pattern)."' LIMIT 1";
    $match_res = $db->query($sql_match);
    if ($match_res && ($match_row = $db->fetch_object($match_res))) {
        $db->query("UPDATE ".MAIN_DB_PREFIX."exportation_employee_ops SET fk_bank_line = ".(int)$match_row->rowid." WHERE rowid = ".(int)$op_row->rowid);
    }
}

// ---------------------------------------------------------------------------
// Banks
// ---------------------------------------------------------------------------
$banks = array();
$rb = $db->query("SELECT rowid, label FROM " . MAIN_DB_PREFIX . "bank_account WHERE entity = " . (int) $conf->entity . " AND clos = 0 ORDER BY label");
while ($rb && $bk = $db->fetch_object($rb)) { $banks[] = $bk; }

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------
if ($action == 'add_employee') {
    $name   = trim(GETPOST('name', 'alphanohtml'));
    $salary = (float) GETPOST('salary', 'alphanohtml');
    $is_user = (int) GETPOST('is_user', 'int');
    $login  = trim(GETPOST('login', 'alpha'));
    $pass   = trim(GETPOST('pwd', 'alpha'));

    if ($name === '') {
        setEventMessages("Le nom est obligatoire.", null, 'errors');
    } elseif ($is_user && ($login === '' || $pass === '')) {
        setEventMessages("Login et mot de passe sont obligatoires si l'employé est aussi utilisateur.", null, 'errors');
    } else {
        $fk_user_id = 'NULL';
        $ok = true;
        if ($is_user) {
            $nu = new User($db);
            $nu->login = $login;
            $nu->pass  = $pass;
            $nu->firstname = $name;
            $nu->statut = 1;
            $nu->admin = 0;
            $nu->employee = 1;
            $r = $nu->create($user);
            if ($r > 0) {
                $fk_user_id = (int) $r;
            } else {
                setEventMessages("Erreur création utilisateur: " . $nu->error, null, 'errors');
                $ok = false;
            }
        }
        if ($ok) {
            $db->query("INSERT INTO ".MAIN_DB_PREFIX."exportation_employees (name, salary, fk_user, status, date_creation, fk_user_creat) VALUES ('".$db->escape($name)."', ".$salary.", ".$fk_user_id.", 1, NOW(), ".(int)$user->id.")");
            setEventMessages("Employé créé avec succès.", null, 'mesgs');
        }
    }
}

if ($action == 'update_salary' && $eid_sel > 0) {
    $salary = (float) GETPOST('salary', 'alphanohtml');
    $db->query("UPDATE ".MAIN_DB_PREFIX."exportation_employees SET salary=".$salary." WHERE rowid=".$eid_sel);
    setEventMessages("Salaire mis à jour.", null, 'mesgs');
}

if ($action == 'delete_employee' && $eid_sel > 0) {
    $emp = $db->fetch_object($db->query("SELECT * FROM ".MAIN_DB_PREFIX."exportation_employees WHERE rowid=".$eid_sel));
    if ($emp) {
        $has_history = false;
        // Check employee ops
        $res_ops = $db->query("SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."exportation_employee_ops WHERE fk_employee=".$eid_sel);
        if ($res_ops) {
            $obj_ops = $db->fetch_object($res_ops);
            if ($obj_ops->nb > 0) $has_history = true;
        }
        // Check user history (invoices/payments)
        if ($emp->fk_user) {
            $res_fac = $db->query("SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."facture WHERE fk_user_author=".(int)$emp->fk_user);
            if ($res_fac) {
                $obj_fac = $db->fetch_object($res_fac);
                if ($obj_fac->nb > 0) $has_history = true;
            }
            $res_pay = $db->query("SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."paiement WHERE fk_user_creat=".(int)$emp->fk_user);
            if ($res_pay) {
                $obj_pay = $db->fetch_object($res_pay);
                if ($obj_pay->nb > 0) $has_history = true;
            }
        }
        
        if ($has_history) {
            setEventMessages("Impossible de supprimer cet employé : historique d'opérations existant.", null, 'errors');
        } else {
            $db->query("DELETE FROM ".MAIN_DB_PREFIX."exportation_employees WHERE rowid=".$eid_sel);
            setEventMessages("Employé supprimé avec succès.", null, 'mesgs');
            header("Location: admin_employees.php");
            exit;
        }
    }
}

if ($action == 'add_op' && $eid_sel > 0) {
    $op_date = GETPOST('op_date', 'alpha') ?: date('Y-m-d');
    $amount  = (float) GETPOST('amount', 'alphanohtml');
    $op_type = GETPOST('op_type', 'alpha');
    $label   = GETPOST('label', 'alpha');
    $fk_bank = (int) GETPOST('fk_bank', 'int');

    if ($amount > 0) {
        $emp = $db->fetch_object($db->query("SELECT * FROM ".MAIN_DB_PREFIX."exportation_employees WHERE rowid=".$eid_sel));
        $fk_user_link = ($emp && $emp->fk_user) ? (int)$emp->fk_user : 0;

        // Determine if this SALAIRE operation closes the salary cycle
        $cycle_close = 0;
        if ($op_type == 'SALAIRE' && $emp && (float)$emp->salary > 0) {
            $last_close = $db->fetch_object($db->query("SELECT COALESCE(MAX(rowid),0) as rid FROM ".MAIN_DB_PREFIX."exportation_employee_ops WHERE fk_employee=".$eid_sel." AND cycle_close=1"));
            $last_close_rid = (int) $last_close->rid;
            $sum = $db->fetch_object($db->query("SELECT COALESCE(SUM(amount),0) as t FROM ".MAIN_DB_PREFIX."exportation_employee_ops WHERE fk_employee=".$eid_sel." AND op_type IN ('SALAIRE','AVANCE','DEPENSE') AND rowid > ".$last_close_rid));
            $paid_in_cycle = (float) $sum->t;
            $remaining_net = (float)$emp->salary - $paid_in_cycle;
            // Tolerance of 0.01 to handle rounding
            if ($amount + 0.01 >= $remaining_net) {
                $cycle_close = 1;
            }
        }

        $fk_bank_line_id = 'NULL';
        if ($fk_bank > 0) {
            require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';
            $acc = new Account($db);
            if ($acc->fetch($fk_bank) > 0) {
                $r_add = $acc->addline(strtotime($op_date), 'CHQ', '('.$op_type.') '.$label, -$amount, 0, 0, $user);
                if ($r_add > 0) {
                    $fk_bank_line_id = (int) $r_add;
                }
            }
        }

        $db->query("INSERT INTO " . MAIN_DB_PREFIX . "exportation_employee_ops (fk_user, fk_employee, op_date, amount, op_type, label, fk_bank, fk_bank_line, fk_user_creat, date_creation, cycle_close) VALUES (".$fk_user_link.", ".(int)$eid_sel.", '".$db->escape($op_date)."', ".$amount.", '".$db->escape($op_type)."', '".$db->escape($label)."', ".$fk_bank.", ".$fk_bank_line_id.", ".(int)$user->id.", NOW(), ".$cycle_close.")");

        setEventMessages($cycle_close ? "Opération enregistrée. Cycle salaire complété — nouveau cycle commencé." : "Opération enregistrée.", null, 'mesgs');
    }
}

if ($action == 'edit_op' && $eid_sel > 0) {
    $op_id = GETPOST('op_id', 'int');
    $op_date = GETPOST('op_date', 'alpha') ?: date('Y-m-d');
    $amount  = (float) GETPOST('amount', 'alphanohtml');
    $op_type = GETPOST('op_type', 'alpha');
    $label   = GETPOST('label', 'alpha');
    $fk_bank = (int) GETPOST('fk_bank', 'int');

    if ($op_id > 0 && $amount > 0) {
        $old_op = $db->fetch_object($db->query("SELECT * FROM ".MAIN_DB_PREFIX."exportation_employee_ops WHERE rowid=".$op_id));
        if ($old_op) {
            $fk_bank_line = $old_op->fk_bank_line;

            require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';

            if ($old_op->fk_bank > 0 && $old_op->fk_bank_line > 0) {
                if ($fk_bank > 0) {
                    // Update existing bank line
                    $sql_update_bank = "UPDATE ".MAIN_DB_PREFIX."bank SET 
                        dateo = '".$db->idate(strtotime($op_date))."',
                        datev = '".$db->idate(strtotime($op_date))."',
                        label = '".$db->escape('('.$op_type.') '.$label)."',
                        amount = ".(-$amount).",
                        fk_account = ".$fk_bank."
                        WHERE rowid = ".(int)$old_op->fk_bank_line;
                    $db->query($sql_update_bank);
                } else {
                    // Delete bank line
                    $accline = new AccountLine($db);
                    if ($accline->fetch($old_op->fk_bank_line) > 0) {
                        $accline->delete($user);
                    }
                    $fk_bank_line = 'NULL';
                }
            } else if ($fk_bank > 0) {
                // Create new bank line
                $acc = new Account($db);
                if ($acc->fetch($fk_bank) > 0) {
                    $bank_line_id = $acc->addline(strtotime($op_date), 'CHQ', '('.$op_type.') '.$label, -$amount, 0, 0, $user);
                    if ($bank_line_id > 0) {
                        $fk_bank_line = (int)$bank_line_id;
                    }
                }
            }

            // Update operation record
            $sql_update_op = "UPDATE ".MAIN_DB_PREFIX."exportation_employee_ops SET 
                op_date = '".$db->escape($op_date)."',
                amount = ".$amount.",
                op_type = '".$db->escape($op_type)."',
                label = '".$db->escape($label)."',
                fk_bank = ".$fk_bank.",
                fk_bank_line = ".($fk_bank_line > 0 ? (int)$fk_bank_line : 'NULL')."
                WHERE rowid = ".$op_id;
            $db->query($sql_update_op);

            setEventMessages("Opération modifiée avec succès.", null, 'mesgs');
        }
    }
}

if ($action == 'delete_op' && $eid_sel > 0) {
    $op_id = GETPOST('op_id', 'int');
    if ($op_id > 0) {
        $old_op = $db->fetch_object($db->query("SELECT * FROM ".MAIN_DB_PREFIX."exportation_employee_ops WHERE rowid=".$op_id));
        if ($old_op) {
            if ($old_op->fk_bank_line > 0) {
                require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';
                $accline = new AccountLine($db);
                if ($accline->fetch($old_op->fk_bank_line) > 0) {
                    $accline->delete($user);
                }
            }
            $db->query("DELETE FROM ".MAIN_DB_PREFIX."exportation_employee_ops WHERE rowid=".$op_id);
            setEventMessages("Opération supprimée avec succès.", null, 'mesgs');
        }
    }
}

llxHeader('', "Gestion des Employés");
?>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
#emp{max-width:1400px;margin:0 auto;font-family:"Outfit",sans-serif;padding:20px;color:#1e2a3a;display:grid;grid-template-columns:350px 1fr;gap:24px}
.ec{background:#fff;border-radius:16px;padding:24px;box-shadow:0 2px 18px rgba(30,42,58,.06);border:1px solid #eef2f7}
.eh{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #f0f4f8;gap:10px}
.ei{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0}
.ei.bl{background:linear-gradient(135deg,#3b82f6,#2563eb)}.ei.gr{background:linear-gradient(135deg,#10b981,#059669)}
.ei.am{background:linear-gradient(135deg,#f59e0b,#d97706)}.ei.pu{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.et{font-size:16px;font-weight:800}.es{font-size:11px;color:#94a3b8;margin-top:1px}
.si{width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:"Outfit",sans-serif;box-sizing:border-box;background:#f8fafc;transition:.15s}
.si:focus{border-color:#3b82f6;outline:none;background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.sl{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:block}
.sb{background:#3b82f6;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px;transition:.15s;display:inline-flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;width:100%}
.sb.g{background:linear-gradient(135deg,#10b981,#059669)}.sb.r{background:linear-gradient(135deg,#f43f5e,#e11d48)}
.sb:hover{filter:brightness(1.08);transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.1)}
.ul{list-style:none;padding:0;margin:0}
.ul li{margin-bottom:8px}
.ul a{display:flex;align-items:center;gap:12px;padding:12px 16px;background:#f8fafc;border-radius:10px;text-decoration:none;color:#1e2a3a;border:1px solid #eef2f7;transition:.2s}
.ul a:hover,.ul a.act{background:#eff6ff;border-color:#bfdbfe;box-shadow:0 4px 12px rgba(59,130,246,.08);color:#2563eb}
.ul a i{color:#94a3b8}.ul a.act i{color:#3b82f6}
.avt{width:36px;height:36px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-weight:700;color:#64748b;font-size:14px}
.ul a.act .avt{background:#3b82f6;color:#fff}
.tb{width:100%;border-collapse:collapse;font-size:13px;margin-top:20px}
.tb th{background:#f8fafc;padding:12px;text-align:left;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid #eef2f7}
.tb td{padding:14px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle;font-weight:500}
.dp{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;display:inline-block}
.d-SALAIRE{background:#dcfce7;color:#166534}.d-AVANCE{background:#fef3c7;color:#92400e}.d-DEPENSE{background:#fee2e2;color:#b91c1c}
.st{background:#f8fafc;border-radius:12px;padding:20px;text-align:center;border:1px solid #eef2f7}
.st strong{display:block;font-size:24px;color:#1e2a3a;margin-bottom:4px}
.st span{font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600;letter-spacing:1px}
.cb-wrap{display:flex;align-items:center;gap:8px;background:#f1f5f9;padding:10px 12px;border-radius:8px;margin-bottom:12px;cursor:pointer}
.cb-wrap input{width:18px;height:18px;cursor:pointer}
.cb-wrap label{font-size:13px;font-weight:600;cursor:pointer;color:#1e2a3a;flex:1}
.sal-pill{background:linear-gradient(135deg,#10b981,#059669);color:#fff;padding:6px 14px;border-radius:20px;font-weight:700;font-size:13px;display:inline-flex;align-items:center;gap:6px}
.sal-edit{background:transparent;border:none;color:#fff;cursor:pointer;opacity:.7;padding:0 0 0 6px;font-size:11px}
.sal-edit:hover{opacity:1}
</style>

<div id="emp">
    <!-- LEFT PANEL: LIST + CREATION -->
    <div>
        <div class="ec" style="margin-bottom:24px;">
            <div class="eh">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="ei bl"><i class="fa-solid fa-users"></i></div>
                    <div><div class="et">Liste des Employés</div><div class="es">Sélectionnez pour gérer</div></div>
                </div>
            </div>
            <ul class="ul">
            <?php
            $ru = $db->query("SELECT e.rowid, e.name, e.salary, e.fk_user FROM ".MAIN_DB_PREFIX."exportation_employees e WHERE e.status=1 ORDER BY e.name");
            while ($ru && ($e = $db->fetch_object($ru))) {
                $ini = strtoupper(substr($e->name,0,1));
                $act = ($eid_sel == $e->rowid) ? 'act' : '';
                $badge = $e->fk_user ? '<i class="fa-solid fa-key" style="font-size:10px;color:#10b981;margin-left:4px;" title="Aussi utilisateur"></i>' : '';
                print '<li><a href="?eid='.$e->rowid.'" class="'.$act.'"><div class="avt">'.$ini.'</div> <div style="font-weight:600;flex:1;">'.dol_escape_htmltag($e->name).$badge.'</div></a></li>';
            }
            if (!$ru || $db->num_rows($ru) === 0) {
                print '<li style="color:#94a3b8;text-align:center;padding:14px;font-size:12px;">Aucun employé.</li>';
            }
            ?>
            </ul>
        </div>

        <div class="ec">
            <div class="eh" style="border:none;padding:0;margin-bottom:16px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="ei gr" style="width:34px;height:34px;font-size:14px;"><i class="fa-solid fa-user-plus"></i></div>
                    <div class="et" style="font-size:15px;">Nouvel Employé</div>
                </div>
            </div>
            <form method="POST" id="emp-form">
                <input type="hidden" name="action" value="add_employee">
                <input type="hidden" name="token" value="<?php echo newToken(); ?>">

                <div style="margin-bottom:12px;"><label class="sl">Nom *</label><input type="text" name="name" class="si" required></div>
                <div style="margin-bottom:12px;"><label class="sl">Salaire mensuel (MRU) *</label><input type="number" step="0.01" min="0" name="salary" class="si" required value="0"></div>

                <div class="cb-wrap" id="cb_wrap" onclick="document.getElementById('cb_isuser').click();">
                    <input type="checkbox" name="is_user" value="1" id="cb_isuser">
                    <label for="cb_isuser"><i class="fa-solid fa-key" style="color:#10b981;"></i> Aussi utilisateur du système</label>
                </div>

                <div id="user_fields" style="display:none;">
                    <div style="margin-bottom:12px;"><label class="sl">Login / Identifiant *</label><input type="text" name="login" id="fld_login" class="si" autocomplete="off"></div>
                    <div style="margin-bottom:16px;"><label class="sl">Mot de passe *</label><input type="password" name="pwd" id="fld_pwd" class="si" autocomplete="new-password"></div>
                </div>

                <button type="submit" class="sb g"><i class="fa-solid fa-check"></i> Créer Employé</button>
            </form>
            <script>
            (function(){
                var cb = document.getElementById('cb_isuser');
                var box = document.getElementById('user_fields');
                var wrap = document.getElementById('cb_wrap');
                var lg = document.getElementById('fld_login');
                var pw = document.getElementById('fld_pwd');
                cb.addEventListener('click', function(ev){ ev.stopPropagation(); });
                function sync(){
                    var on = cb.checked;
                    box.style.display = on ? 'block' : 'none';
                    lg.required = on; pw.required = on;
                    wrap.style.background = on ? '#dcfce7' : '#f1f5f9';
                }
                cb.addEventListener('change', sync);
                sync();
            })();
            </script>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div>
        <?php if ($eid_sel > 0):
            $emp = $db->fetch_object($db->query("SELECT * FROM ".MAIN_DB_PREFIX."exportation_employees WHERE rowid=".$eid_sel));
            if ($emp):
                $m_start = date('Y-m-01');
                $sales = 0; $recettes = 0;
                if ($emp->fk_user) {
                    $obs = $db->fetch_object($db->query("SELECT COALESCE(SUM(total_ttc),0) as t FROM ".MAIN_DB_PREFIX."facture WHERE fk_user_author=".(int)$emp->fk_user." AND datef >='".$m_start."'"));
                    $obp = $db->fetch_object($db->query("SELECT COALESCE(SUM(pf.amount),0) as t FROM ".MAIN_DB_PREFIX."paiement p INNER JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON p.rowid=pf.fk_paiement WHERE p.fk_user_creat=".(int)$emp->fk_user." AND p.datep >='".$m_start."'"));
                    $sales = (float) $obs->t;
                    $recettes = (float) $obp->t;
                }

                // Cycle-aware: locate last cycle close
                $last_close = $db->fetch_object($db->query("SELECT COALESCE(MAX(rowid),0) as rid FROM ".MAIN_DB_PREFIX."exportation_employee_ops WHERE fk_employee=".$eid_sel." AND cycle_close=1"));
                $last_close_rid = (int) $last_close->rid;

                // Salaire/Avance in current cycle
                $obsal = $db->fetch_object($db->query("SELECT COALESCE(SUM(amount),0) as t FROM ".MAIN_DB_PREFIX."exportation_employee_ops WHERE fk_employee=".$eid_sel." AND op_type IN ('SALAIRE','AVANCE') AND rowid > ".$last_close_rid));
                $cycle_paid = (float) $obsal->t;

                // Dépenses (monthly + current cycle)
                $obex = $db->fetch_object($db->query("SELECT COALESCE(SUM(amount),0) as t FROM ".MAIN_DB_PREFIX."exportation_employee_ops WHERE fk_employee=".$eid_sel." AND op_type='DEPENSE' AND op_date >='".$m_start."'"));
                $cycle_dep_obj = $db->fetch_object($db->query("SELECT COALESCE(SUM(amount),0) as t FROM ".MAIN_DB_PREFIX."exportation_employee_ops WHERE fk_employee=".$eid_sel." AND op_type='DEPENSE' AND rowid > ".$last_close_rid));
                $cycle_dep = (float) $cycle_dep_obj->t;

                $remaining_for_salaire = max(0, (float)$emp->salary - $cycle_paid - $cycle_dep);
        ?>
        <div class="ec" style="margin-bottom:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:16px;">
                <div style="display:flex;align-items:center;gap:16px;">
                    <div class="avt" style="width:60px;height:60px;font-size:24px;background:#3b82f6;color:#fff;"><?php echo strtoupper(substr($emp->name,0,1)); ?></div>
                    <div>
                        <div style="font-size:24px;font-weight:800;"><?php echo dol_escape_htmltag($emp->name); ?></div>
                        <div style="color:#64748b;font-weight:600;font-size:13px;margin-top:4px;">
                            <i class="fa-solid fa-id-badge"></i> Actif &bull; Dossier N°<?php echo $emp->rowid; ?>
                            <?php if ($emp->fk_user): ?> &bull; <i class="fa-solid fa-key" style="color:#10b981;"></i> Utilisateur lié<?php endif; ?>
                        </div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="sal-pill" id="sal-display">
                        <i class="fa-solid fa-money-bill-wave"></i>
                        Salaire : <?php echo price($emp->salary); ?> MRU
                        <button type="button" class="sal-edit" onclick="document.getElementById('sal-display').style.display='none';document.getElementById('sal-form').style.display='inline-flex';"><i class="fa-solid fa-pen"></i></button>
                    </div>
                    <form method="POST" id="sal-form" style="display:none;align-items:center;gap:6px;">
                        <input type="hidden" name="action" value="update_salary">
                        <input type="hidden" name="eid" value="<?php echo $eid_sel; ?>">
                        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                        <input type="number" step="0.01" name="salary" value="<?php echo (float)$emp->salary; ?>" class="si" style="width:140px;padding:6px 10px;">
                        <button type="submit" class="sb g" style="width:auto;padding:7px 12px;"><i class="fa-solid fa-check"></i></button>
                    </form>
                    <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet employé ?');" style="display:inline;">
                        <input type="hidden" name="action" value="delete_employee">
                        <input type="hidden" name="eid" value="<?php echo $eid_sel; ?>">
                        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                        <button type="submit" class="sb r" style="width:auto;padding:8px 12px;display:inline-flex;gap:6px;border-radius:20px;height:34px;align-items:center;">
                            <i class="fa-solid fa-trash"></i> Supprimer
                        </button>
                    </form>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
                <div class="st"><strong><?php echo price($sales); ?></strong><span style="color:#3b82f6;"><i class="fa-solid fa-arrow-trend-up"></i> Ventes (Mois)</span></div>
                <div class="st"><strong><?php echo price($recettes); ?></strong><span style="color:#10b981;"><i class="fa-solid fa-hand-holding-dollar"></i> Recettes (Mois)</span></div>
                <div class="st">
                    <strong><?php echo price($cycle_paid); ?></strong>
                    <span style="color:#f59e0b;"><i class="fa-solid fa-money-check-dollar"></i> Salaire/Avance</span>
                    <?php if ($emp->salary > 0): ?>
                        <div style="font-size:10px;color:#94a3b8;margin-top:4px;">Reste : <?php echo price($remaining_for_salaire); ?></div>
                    <?php endif; ?>
                </div>
                <div class="st"><strong><?php echo price($obex->t); ?></strong><span style="color:#ef4444;"><i class="fa-solid fa-receipt"></i> Dépenses/Frais</span></div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 300px;gap:24px;">
            <!-- History -->
            <div class="ec">
                <div class="eh">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="ei pu"><i class="fa-solid fa-clock-rotate-left"></i></div>
                        <div class="et">Historique des Opérations (Salaire, Frais)</div>
                    </div>
                </div>
                <table class="tb">
                    <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Montant (MRU)</th><th>Banque</th><th style="text-align:right;">Actions</th></tr></thead>
                    <tbody>
                    <?php
                    $ro = $db->query("SELECT o.*, b.label as bank_name FROM ".MAIN_DB_PREFIX."exportation_employee_ops o LEFT JOIN ".MAIN_DB_PREFIX."bank_account b ON b.rowid = o.fk_bank WHERE o.fk_employee=".$eid_sel." ORDER BY o.op_date DESC, o.rowid DESC LIMIT 30");
                    $has = false;
                    while ($ro && ($o = $db->fetch_object($ro))) {
                        $has = true;
                        $cc = $o->cycle_close ? ' <i class="fa-solid fa-flag-checkered" style="color:#10b981;" title="Cycle salaire clôturé"></i>' : '';
                        print '<tr><td>'.dol_print_date($db->jdate($o->op_date),'day').'</td>';
                        print '<td><span class="dp d-'.$o->op_type.'">'.$o->op_type.'</span>'.$cc.'</td>';
                        print '<td>'.dol_escape_htmltag($o->label).'</td>';
                        print '<td style="font-weight:800;">'.price($o->amount).'</td>';
                        print '<td style="font-size:11px;color:#64748b;">'.dol_escape_htmltag($o->bank_name ?: '-').'</td>';
                        print '<td style="text-align:right;white-space:nowrap;">';
                        print '<button type="button" class="sb" style="width:auto;padding:6px 10px;margin-right:6px;display:inline-flex;align-items:center;background:#3b82f6;" onclick="editOp('.$o->rowid.', \''.$o->op_date.'\', \''.$o->op_type.'\', '.$o->amount.', \''.dol_escape_js($o->label).'\', '.$o->fk_bank.')" title="Modifier"><i class="fa-solid fa-pen" style="font-size:10px;"></i></button>';
                        print '<form method="POST" action="" onsubmit="return confirm(\'Êtes-vous sûr de vouloir supprimer cette opération ?\');" style="display:inline;">';
                        print '<input type="hidden" name="action" value="delete_op">';
                        print '<input type="hidden" name="op_id" value="'.$o->rowid.'">';
                        print '<input type="hidden" name="eid" value="'.$eid_sel.'">';
                        print '<input type="hidden" name="token" value="'.newToken().'">';
                        print '<button type="submit" class="sb r" style="width:auto;padding:6px 10px;display:inline-flex;align-items:center;" title="Supprimer"><i class="fa-solid fa-trash" style="font-size:10px;"></i></button>';
                        print '</form>';
                        print '</td></tr>';
                    }
                    if (!$has) print '<tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:20px;">Aucune opération.</td></tr>';
                    ?>
                    </tbody>
                </table>
            </div>

            <!-- Add Form -->
            <div class="ec" style="align-self:start;">
                <div class="eh" style="padding-bottom:10px;margin-bottom:16px;">
                    <div class="et" id="form-title"><i class="fa-solid fa-plus-circle"></i> Saisir Opération</div>
                </div>
                <form method="POST" id="op-form">
                    <input type="hidden" name="action" id="op-action" value="add_op">
                    <input type="hidden" name="op_id" id="op-id" value="">
                    <input type="hidden" name="eid" value="<?php echo $eid_sel; ?>">
                    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                    <div style="margin-bottom:12px;"><label class="sl">Date *</label><input type="date" name="op_date" id="op_date" class="si" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div style="margin-bottom:12px;"><label class="sl">Type *</label>
                        <select name="op_type" id="op_type_sel" class="si">
                            <option value="SALAIRE">Paiement Salaire</option>
                            <option value="AVANCE">Avance sur Salaire</option>
                            <option value="DEPENSE">Dépense / Frais de mission</option>
                        </select>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label class="sl">Montant (MRU) *</label>
                        <input type="number" step="0.01" name="amount" id="op_amount" class="si" required>
                        <span id="auto-hint" style="display:none;font-size:11px;color:#10b981;margin-top:4px;">
                            <i class="fa-solid fa-magic"></i> Reste du salaire suggéré automatiquement.
                        </span>
                    </div>
            
                    <div style="margin-bottom:12px;"><label class="sl">Caisse / Banque *</label>
                        <select name="fk_bank" id="op_fk_bank" class="si" required><option value="">— Sélectionner —</option>
                        <?php foreach($banks as $bk) { echo '<option value="'.$bk->rowid.'">'.dol_escape_htmltag($bk->label).'</option>'; } ?>
                        </select>
                    </div>
                    <div style="margin-bottom:16px;"><label class="sl">Description</label><input type="text" name="label" id="op_label" class="si" placeholder="Ex: Salaire Avril"></div>
                    <button type="button" class="sb" id="cancel-edit-btn" style="display:none;background:linear-gradient(135deg,#64748b,#475569);margin-bottom:12px;" onclick="cancelEdit()"><i class="fa-solid fa-times-circle"></i> Annuler</button>
                    <button type="submit" class="sb" id="submit-btn"><i class="fa-solid fa-save"></i> Enregistrer</button>
                </form>
            </div>
        </div>

        <script>
        const remaining = <?php echo json_encode(round($remaining_for_salaire, 2)); ?>;
        
        function editOp(id, date, type, amount, label, fk_bank) {
            document.getElementById('form-title').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Modifier Opération';
            document.getElementById('op-action').value = 'edit_op';
            document.getElementById('op-id').value = id;
            document.getElementById('op_date').value = date;
            document.getElementById('op_type_sel').value = type;
            document.getElementById('op_amount').value = amount;
            document.getElementById('op_fk_bank').value = fk_bank;
            document.getElementById('op_label').value = label;
            document.getElementById('cancel-edit-btn').style.display = 'block';
            document.getElementById('submit-btn').innerHTML = '<i class="fa-solid fa-save"></i> Mettre à jour';
            document.getElementById('auto-hint').style.display = 'none';
            document.getElementById('op-form').scrollIntoView({ behavior: 'smooth' });
        }

        function cancelEdit() {
            document.getElementById('form-title').innerHTML = '<i class="fa-solid fa-plus-circle"></i> Saisir Opération';
            document.getElementById('op-action').value = 'add_op';
            document.getElementById('op-id').value = '';
            document.getElementById('op_date').value = '<?php echo date('Y-m-d'); ?>';
            document.getElementById('op_type_sel').value = 'SALAIRE';
            document.getElementById('op_amount').value = '';
            document.getElementById('op_fk_bank').value = '';
            document.getElementById('op_label').value = '';
            document.getElementById('cancel-edit-btn').style.display = 'none';
            document.getElementById('submit-btn').innerHTML = '<i class="fa-solid fa-save"></i> Enregistrer';
            applyHint();
        }

        const sel = document.getElementById('op_type_sel');
        const amt = document.getElementById('op_amount');
        const hint = document.getElementById('auto-hint');
        
        function applyHint() {
            if (document.getElementById('op-action').value === 'add_op') {
                if (sel.value === 'SALAIRE' && remaining > 0) {
                    amt.value = remaining.toFixed(2);
                    hint.style.display = 'block';
                } else {
                    if (sel.value !== 'SALAIRE') amt.value = '';
                    hint.style.display = 'none';
                }
            }
        }
        
        sel.addEventListener('change', applyHint);
        applyHint();
        </script>

        <?php else: ?>
        <div class="ec" style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:400px;color:#94a3b8;text-align:center;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size:48px;margin-bottom:16px;color:#fbbf24;"></i>
            <div style="font-size:18px;font-weight:700;color:#64748b;">Employé introuvable</div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="ec" style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:400px;color:#94a3b8;text-align:center;">
            <i class="fa-solid fa-users-viewfinder" style="font-size:48px;margin-bottom:16px;color:#cbd5e1;"></i>
            <div style="font-size:18px;font-weight:700;color:#64748b;margin-bottom:6px;">Aucun employé sélectionné</div>
            <div style="font-size:14px;">Sélectionnez un employé dans la liste pour voir ses performances et historique.</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
llxFooter();
$db->close();
?>
