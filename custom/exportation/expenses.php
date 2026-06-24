<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';

$langs->loadLangs(array("companies", "bills", "users", "banks", "exportation@exportation"));
$form = new Form($db);
$user_obj = new User($db);

// Permissions check
if (!$user->admin) {
	accessforbidden();
}

// Ensure schema (bank columns)
@$db->query("ALTER TABLE " . MAIN_DB_PREFIX . "exportation_expenses ADD COLUMN fk_bank integer DEFAULT NULL");
@$db->query("ALTER TABLE " . MAIN_DB_PREFIX . "exportation_expenses ADD COLUMN fk_bank_line integer DEFAULT NULL");

// Available banks (open accounts only)
$banks = array();
$rb = $db->query("SELECT rowid, label FROM " . MAIN_DB_PREFIX . "bank_account WHERE entity = " . (int) $conf->entity . " AND clos = 0 ORDER BY label");
while ($rb && ($bk = $db->fetch_object($rb))) { $banks[] = $bk; }

// Actions
$action = GETPOST('action', 'aZ09');
$id = GETPOST('id', 'int');
$status_filter = GETPOST('status', 'alpha') ?: '';
$date_from = GETPOST('date_from', 'alpha') ?: '';
$date_to = GETPOST('date_to', 'alpha') ?: '';
$employee_filter = GETPOST('employee', 'int') ?: '';

// Create expense
if ($action == 'create' && GETPOST('submitbutton')) {
	$amount = price2num(GETPOST('amount', 'alphanohtml'));
	$description = trim(GETPOST('description', 'alphanohtml'));
	$category = trim(GETPOST('category', 'alphanohtml'));
	$expense_date = GETPOST('expense_date', 'alpha');
	$fk_bank = (int) GETPOST('fk_bank', 'int');

	$error_msg = '';
	if (empty($expense_date)) $error_msg .= 'Date requise. ';
	if ($amount <= 0) $error_msg .= 'Montant doit être > 0. ';
	if (empty($description)) $error_msg .= 'Description requise. ';
	if ($fk_bank <= 0) $error_msg .= 'Banque/Caisse requise. ';

	if ($error_msg) {
		setEventMessage($error_msg, 'errors');
	} else {
		$db->begin();
		// Withdraw from the selected bank account (expense = debit)
		$fk_bank_line = 'NULL';
		$acc = new Account($db);
		if ($acc->fetch($fk_bank) > 0) {
			$bank_label = '(Dépense) ' . $description;
			$oper = ($acc->type == Account::TYPE_CASH) ? 'LIQ' : 'CHQ';
			$r_add = $acc->addline(strtotime($expense_date), $oper, $bank_label, -(float) $amount, 0, 0, $user);
			if ($r_add > 0) {
				$fk_bank_line = (int) $r_add;
			} else {
				$db->rollback();
				setEventMessage('Erreur retrait banque: ' . $acc->error, 'errors');
			}
		} else {
			$db->rollback();
			setEventMessage('Compte bancaire introuvable.', 'errors');
		}

		if ($fk_bank_line !== 'NULL') {
			$sql = "INSERT INTO " . MAIN_DB_PREFIX . "exportation_expenses (fk_user, amount, description, category, expense_date, status, created_date, validated_date, entity, fk_bank, fk_bank_line) VALUES ";
			$sql .= "(" . (int) $user->id . ", " . (float) $amount . ", '" . $db->escape($description) . "', '" . $db->escape($category) . "', '" . $db->escape($expense_date) . "', 'VALIDATED', NOW(), NOW(), " . (int) $conf->entity . ", " . $fk_bank . ", " . $fk_bank_line . ")";

			if ($db->query($sql)) {
				$db->commit();
				setEventMessage($langs->trans('ExpenseCreated'));
				header('Location: ' . $_SERVER['PHP_SELF']);
				exit;
			} else {
				$db->rollback();
				setEventMessage('Error: ' . $db->lasterror(), 'errors');
			}
		}
	}
}

// Update status
if ($action == 'validate' && $id) {
	// Fetch the expense before validating
	$er = $db->query("SELECT rowid, amount, description, expense_date, status, fk_bank, fk_bank_line FROM " . MAIN_DB_PREFIX . "exportation_expenses WHERE rowid = " . (int) $id . " AND entity = " . (int) $conf->entity);
	$exp = $er ? $db->fetch_object($er) : null;

	if ($exp && $exp->status == 'PENDING') {
		$db->begin();
		$error = 0;
		$fk_bank_line = ($exp->fk_bank_line > 0) ? (int) $exp->fk_bank_line : 'NULL';

		// Withdraw from bank only if not already done and a bank was selected
		if ($exp->fk_bank > 0 && !($exp->fk_bank_line > 0)) {
			$acc = new Account($db);
			if ($acc->fetch((int) $exp->fk_bank) > 0) {
				$oper = ($acc->type == Account::TYPE_CASH) ? 'LIQ' : 'CHQ';
				$r_add = $acc->addline(strtotime($exp->expense_date), $oper, '(Dépense) ' . $exp->description, -(float) $exp->amount, 0, 0, $user);
				if ($r_add > 0) {
					$fk_bank_line = (int) $r_add;
				} else {
					$error++;
					setEventMessage('Erreur retrait banque: ' . $acc->error, 'errors');
				}
			} else {
				$error++;
				setEventMessage('Compte bancaire introuvable.', 'errors');
			}
		}

		if (!$error) {
			$sql = "UPDATE " . MAIN_DB_PREFIX . "exportation_expenses SET status = 'VALIDATED', validated_date = NOW(), fk_bank_line = " . $fk_bank_line . " WHERE rowid = " . (int) $id . " AND entity = " . (int) $conf->entity . " AND status = 'PENDING'";
			if ($db->query($sql)) {
				$db->commit();
				setEventMessage($langs->trans('ExpenseValidated'));
				header('Location: ' . $_SERVER['PHP_SELF']);
				exit;
			} else {
				$db->rollback();
				setEventMessage('Error: ' . $db->lasterror(), 'errors');
			}
		} else {
			$db->rollback();
		}
	}
	header('Location: ' . $_SERVER['PHP_SELF']);
	exit;
}

if ($action == 'refuse' && $id) {
	$sql = "UPDATE " . MAIN_DB_PREFIX . "exportation_expenses SET status = 'REFUSED' WHERE rowid = " . (int) $id . " AND entity = " . (int) $conf->entity;
	if ($db->query($sql)) {
		setEventMessage($langs->trans('ExpenseRefused'));
		header('Location: ' . $_SERVER['PHP_SELF']);
		exit;
	}
}

// Build WHERE clause
$where = "WHERE e.entity = " . (int) $conf->entity;
if ($status_filter) {
	$where .= " AND e.status = '" . $db->escape($status_filter) . "'";
}
if ($date_from) {
	$where .= " AND DATE(e.expense_date) >= '" . $db->escape($date_from) . "'";
}
if ($date_to) {
	$where .= " AND DATE(e.expense_date) <= '" . $db->escape($date_to) . "'";
}
if ($employee_filter) {
	$where .= " AND e.fk_user = " . (int) $employee_filter;
}

// Get expenses — fetch all into array immediately before any other query runs
$sql = "SELECT e.*, u.firstname, u.lastname, ba.label as bank_label FROM " . MAIN_DB_PREFIX . "exportation_expenses e LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = e.fk_user LEFT JOIN " . MAIN_DB_PREFIX . "bank_account ba ON ba.rowid = e.fk_bank " . $where . " ORDER BY e.expense_date DESC";
$res = $db->query($sql);
$expenses_list = array();
$total_validated = 0;
if (!$res) {
	setEventMessage('SQL Error: ' . $db->lasterror(), 'errors');
} else {
	while ($exp_row = $db->fetch_object($res)) {
		$expenses_list[] = $exp_row;
		if ($exp_row->status == 'VALIDATED') {
			$total_validated += (float) $exp_row->amount;
		}
	}
}

// CSS
llxHeader('', "Gestion des Dépenses — إدارة المصاريف", '');
print '<style>
.exp-container { max-width: 1400px; margin: 0 auto; padding: 20px; font-family: Outfit, sans-serif; color: #1e2a3a; }
.exp-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.exp-title { font-size: 28px; font-weight: 800; }
.exp-btn { background: #3b82f6; color: #fff; border: none; padding: 10px 18px; border-radius: 6px; cursor: pointer; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
.exp-btn:hover { filter: brightness(1.1); }
.exp-btn.green { background: #10b981; }
.exp-btn.red { background: #ef4444; }
.exp-form { background: #fff; border-radius: 8px; padding: 20px; border: 1px solid #eef2f7; margin-bottom: 20px; display: none; }
.exp-form.active { display: block; }
.exp-form-group { margin-bottom: 12px; }
.exp-label { font-weight: 700; font-size: 13px; margin-bottom: 4px; display: block; color: #475569; }
.exp-input { width: 100%; padding: 8px 11px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; font-family: Outfit, sans-serif; box-sizing: border-box; }
.exp-input:focus { border-color: #3b82f6; outline: none; }
.exp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 12px; }
.exp-filters { background: #fff; border-radius: 8px; padding: 16px; border: 1px solid #eef2f7; margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.exp-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #eef2f7; }
.exp-table th { background: #f8fafc; padding: 12px; text-align: left; font-weight: 700; font-size: 12px; color: #64748b; border-bottom: 2px solid #eef2f7; }
.exp-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
.exp-table tr:hover td { background: #fafbfd; }
.exp-status { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; }
.exp-status.pending { background: #fef3c7; color: #92400e; }
.exp-status.validated { background: #dcfce7; color: #15803d; }
.exp-status.refused { background: #fee2e2; color: #991b1b; }
.exp-actions { display: flex; gap: 6px; }
.exp-actions button { padding: 6px 10px; font-size: 11px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
.exp-actions .validate { background: #d1fae5; color: #065f46; }
.exp-actions .refuse { background: #fee2e2; color: #7f1d1d; }
.exp-actions .validate:hover { background: #a7f3d0; }
.exp-actions .refuse:hover { background: #fecaca; }
@media print {
	.noprint, .exp-header, .exp-filters, .exp-actions { display: none !important; }
	.exp-container { background: #fff !important; }
}
.exp-total-top{margin-bottom:16px;padding:16px 20px;background:#f0fdf4;border-radius:10px;border:1px solid #d1fae5;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.exp-total-top .lbl{font-weight:700;color:#15803d;font-size:14px}
.exp-total-top .val{font-size:24px;font-weight:800;color:#15803d}
</style>';

print '<div class="exp-container">';
print '<div class="exp-header">';
print '<div><div class="exp-title">📋 Gestion des Dépenses</div><div style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Créer, valider et gérer les dépenses</div></div>';
print '<button type="button" class="exp-btn" onclick="document.getElementById(\'formCreate\').classList.toggle(\'active\');">➕ Nouvelle Dépense</button>';
print '</div>';

// Create form
print '<div id="formCreate" class="exp-form">';
print '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">';
print '<h3 style="margin: 0;">Créer une dépense</h3>';
print '<button type="button" onclick="document.getElementById(\'formCreate\').classList.remove(\'active\');" style="background: none; border: none; font-size: 24px; cursor: pointer;">✕</button>';
print '</div>';
print '<form method="POST">';
print '<input type="hidden" name="action" value="create">';
print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
print '<div class="exp-grid">';
print '<div class="exp-form-group"><label class="exp-label">Date</label><input type="date" name="expense_date" class="exp-input" required></div>';
print '<div class="exp-form-group"><label class="exp-label">Montant (MRU)</label><input type="number" name="amount" class="exp-input" step="0.01" min="0" required></div>';
print '<div class="exp-form-group"><label class="exp-label">Catégorie</label><input type="text" name="category" class="exp-input" placeholder="Transport, Nourriture..."></div>';
print '<div class="exp-form-group"><label class="exp-label">Banque / Caisse</label><select name="fk_bank" class="exp-input" required><option value="">— Sélectionner —</option>';
foreach ($banks as $bk) { print '<option value="' . $bk->rowid . '">' . dol_escape_htmltag($bk->label) . '</option>'; }
print '</select></div>';
print '</div>';
print '<div class="exp-form-group"><label class="exp-label">Description</label><textarea name="description" class="exp-input" style="min-height: 80px;" required></textarea></div>';
print '<div style="display: flex; gap: 8px;">';
print '<button type="submit" name="submitbutton" value="1" class="exp-btn">✓ Créer</button>';
print '<button type="button" onclick="document.getElementById(\'formCreate\').classList.remove(\'active\');" class="exp-btn" style="background: #94a3b8;">Annuler</button>';
print '</div>';
print '</form></div>';

// Filters
print '<div class="exp-filters">';
print '<form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; width: 100%;">';
print '<div style="min-width: 120px;"><label class="exp-label">Statut</label><select name="status" class="exp-input">';
print '<option value="">Tous</option>';
print '<option value="PENDING"' . ($status_filter == 'PENDING' ? ' selected' : '') . '>En attente</option>';
print '<option value="VALIDATED"' . ($status_filter == 'VALIDATED' ? ' selected' : '') . '>Validée</option>';
print '<option value="REFUSED"' . ($status_filter == 'REFUSED' ? ' selected' : '') . '>Refusée</option>';
print '</select></div>';
print '<div style="min-width: 120px;"><label class="exp-label">Date de</label><input type="date" name="date_from" class="exp-input" value="' . $date_from . '"></div>';
print '<div style="min-width: 120px;"><label class="exp-label">Date à</label><input type="date" name="date_to" class="exp-input" value="' . $date_to . '"></div>';
print '<div style="min-width: 140px;"><label class="exp-label">Employé</label><select name="employee" class="exp-input">';
print '<option value="">Tous</option>';
$users_sql = "SELECT rowid, firstname, lastname FROM " . MAIN_DB_PREFIX . "user WHERE active = 1 AND admin = 0 ORDER BY firstname";
$users_res = $db->query($users_sql);
while ($users_res && ($u = $db->fetch_object($users_res))) {
	print '<option value="' . $u->rowid . '"' . ($employee_filter == $u->rowid ? ' selected' : '') . '>' . $u->firstname . ' ' . $u->lastname . '</option>';
}
print '</select></div>';
print '<button type="submit" class="exp-btn" style="margin-top: 24px;">🔍 Filtrer</button>';
print '</form></div>';

// Total dépenses validées (en haut)
print '<div class="exp-total-top">';
print '<div class="lbl">Total dépenses validées</div>';
print '<div class="val">' . price($total_validated) . '</div>';
print '</div>';

// Table
print '<table class="exp-table" data-pg="1" data-pg-default="20">';
print '<thead><tr><th>Date</th><th>Employé</th><th>Catégorie</th><th>Description</th><th>Banque/Caisse</th><th style="text-align: right;">Montant</th><th>Statut</th><th>Actions</th></tr></thead>';
print '<tbody>';

$has_expenses = false;

foreach ($expenses_list as $exp) {
	$has_expenses = true;
	$status_label = $exp->status == 'PENDING' ? 'En attente' : ($exp->status == 'VALIDATED' ? 'Validée' : 'Refusée');
	$status_class = strtolower($exp->status);

	print '<tr>';
	print '<td>' . dol_print_date(strtotime($exp->expense_date), 'day') . '</td>';
	print '<td>' . (isset($exp->firstname) && $exp->firstname ? dol_escape_htmltag($exp->firstname . ' ' . $exp->lastname) : 'Admin') . '</td>';
	print '<td>' . dol_escape_htmltag($exp->category ?: 'Autre') . '</td>';
	print '<td>' . dol_escape_htmltag($exp->description) . '</td>';
	print '<td>' . ($exp->bank_label ? dol_escape_htmltag($exp->bank_label) : '<span style="color:#cbd5e1;">—</span>') . '</td>';
	print '<td style="text-align: right; font-weight: 700; color: #3b82f6;">' . price($exp->amount) . '</td>';
	print '<td><span class="exp-status ' . $status_class . '">' . $status_label . '</span></td>';
	print '<td><div class="exp-actions">';

	if ($exp->status == 'PENDING') {
		print '<form method="POST" style="display: inline;"><input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '"><input type="hidden" name="action" value="validate"><input type="hidden" name="id" value="' . $exp->rowid . '"><button type="submit" class="validate">✓ Valider</button></form>';
		print '<form method="POST" style="display: inline;"><input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '"><input type="hidden" name="action" value="refuse"><input type="hidden" name="id" value="' . $exp->rowid . '"><button type="submit" class="refuse">✕ Refuser</button></form>';
	}

	print '</div></td></tr>';
}

if (!$has_expenses) {
	print '<tr><td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">Aucune dépense trouvée</td></tr>';
}

print '</tbody></table>';

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
.pg-scroll thead th{position:sticky;top:0;z-index:5;background:#f8fafc;}
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

print '</div>';
llxFooter();
$db->close();
