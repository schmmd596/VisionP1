<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

$langs->loadLangs(array("companies", "bills", "banks", "exportation@exportation"));

// Ensure schema (bank columns)
@$db->query("ALTER TABLE " . MAIN_DB_PREFIX . "exportation_expenses ADD COLUMN fk_bank integer DEFAULT NULL");
@$db->query("ALTER TABLE " . MAIN_DB_PREFIX . "exportation_expenses ADD COLUMN fk_bank_line integer DEFAULT NULL");

// Available banks (open accounts only)
$banks = array();
$rb = $db->query("SELECT rowid, label FROM " . MAIN_DB_PREFIX . "bank_account WHERE entity = " . (int) $conf->entity . " AND clos = 0 ORDER BY label");
while ($rb && ($bk = $db->fetch_object($rb))) { $banks[] = $bk; }

// Actions
$action = GETPOST('action', 'aZ09');

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
		// Bank is recorded now; the withdrawal happens when an admin validates the expense.
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . "exportation_expenses (fk_user, amount, description, category, expense_date, status, created_date, entity, fk_bank) VALUES ";
		$sql .= "(" . (int) $user->id . ", " . (float) $amount . ", '" . $db->escape($description) . "', '" . $db->escape($category) . "', '" . $db->escape($expense_date) . "', 'PENDING', NOW(), " . (int) $conf->entity . ", " . $fk_bank . ")";

		if ($db->query($sql)) {
			setEventMessage($langs->trans('ExpenseSubmitted'));
			header('Location: ' . $_SERVER['PHP_SELF']);
			exit;
		} else {
			setEventMessage('Error: ' . $db->lasterror(), 'errors');
		}
	}
}

// Get employee expenses
$sql = "SELECT e.*, ba.label as bank_label FROM " . MAIN_DB_PREFIX . "exportation_expenses e LEFT JOIN " . MAIN_DB_PREFIX . "bank_account ba ON ba.rowid = e.fk_bank WHERE e.fk_user = " . (int) $user->id . " AND e.entity = " . (int) $conf->entity . " ORDER BY e.expense_date DESC";
$res = $db->query($sql);

// CSS
llxHeader('', "Mes Dépenses — مصاريفي", '');
print '<style>
.emp-exp-container { max-width: 1000px; margin: 0 auto; padding: 20px; font-family: Outfit, sans-serif; color: #1e2a3a; }
.emp-exp-header { margin-bottom: 24px; }
.emp-exp-title { font-size: 28px; font-weight: 800; }
.emp-exp-subtitle { font-size: 13px; color: #94a3b8; margin-top: 4px; }
.emp-exp-btn { background: #3b82f6; color: #fff; border: none; padding: 10px 18px; border-radius: 6px; cursor: pointer; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-top: 12px; }
.emp-exp-btn:hover { filter: brightness(1.1); }
.emp-exp-form { background: #fff; border-radius: 8px; padding: 20px; border: 1px solid #eef2f7; margin-bottom: 20px; display: none; }
.emp-exp-form.active { display: block; }
.emp-exp-form-group { margin-bottom: 12px; }
.emp-exp-label { font-weight: 700; font-size: 13px; margin-bottom: 4px; display: block; color: #475569; }
.emp-exp-input { width: 100%; padding: 8px 11px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; font-family: Outfit, sans-serif; box-sizing: border-box; }
.emp-exp-input:focus { border-color: #3b82f6; outline: none; }
.emp-exp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 12px; }
.emp-exp-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #eef2f7; }
.emp-exp-table th { background: #f8fafc; padding: 12px; text-align: left; font-weight: 700; font-size: 12px; color: #64748b; border-bottom: 2px solid #eef2f7; }
.emp-exp-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
.emp-exp-table tr:hover td { background: #fafbfd; }
.emp-exp-status { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; }
.emp-exp-status.pending { background: #fef3c7; color: #92400e; }
.emp-exp-status.validated { background: #dcfce7; color: #15803d; }
.emp-exp-status.refused { background: #fee2e2; color: #991b1b; }
.emp-exp-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
.emp-exp-stat-card { background: #fff; border-radius: 8px; padding: 16px; border: 1px solid #eef2f7; text-align: center; }
.emp-exp-stat-label { font-size: 12px; color: #94a3b8; font-weight: 600; }
.emp-exp-stat-value { font-size: 24px; font-weight: 800; margin-top: 6px; color: #3b82f6; }
.emp-exp-stat-value.green { color: #10b981; }
.emp-exp-stat-value.red { color: #ef4444; }
</style>';

print '<div class="emp-exp-container">';
print '<div class="emp-exp-header">';
print '<div class="emp-exp-title">📋 Mes Dépenses</div>';
print '<div class="emp-exp-subtitle">Soumettre et suivre vos dépenses professionnelles</div>';
print '<button type="button" class="emp-exp-btn" onclick="document.getElementById(\'formCreate\').classList.toggle(\'active\');">➕ Ajouter une dépense</button>';
print '</div>';

// Create form
print '<div id="formCreate" class="emp-exp-form">';
print '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">';
print '<h3 style="margin: 0;">Soumettre une dépense</h3>';
print '<button type="button" onclick="document.getElementById(\'formCreate\').classList.remove(\'active\');" style="background: none; border: none; font-size: 24px; cursor: pointer;">✕</button>';
print '</div>';
print '<form method="POST">';
print '<input type="hidden" name="action" value="create">';
print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
print '<div class="emp-exp-grid">';
print '<div class="emp-exp-form-group"><label class="emp-exp-label">Date</label><input type="date" name="expense_date" class="emp-exp-input" required></div>';
print '<div class="emp-exp-form-group"><label class="emp-exp-label">Montant (MRU)</label><input type="number" name="amount" class="emp-exp-input" step="0.01" min="0" required></div>';
print '<div class="emp-exp-form-group"><label class="emp-exp-label">Catégorie</label><input type="text" name="category" class="emp-exp-input" placeholder="Transport, Repas..."></div>';
print '<div class="emp-exp-form-group"><label class="emp-exp-label">Banque / Caisse</label><select name="fk_bank" class="emp-exp-input" required><option value="">— Sélectionner —</option>';
foreach ($banks as $bk) { print '<option value="' . $bk->rowid . '">' . dol_escape_htmltag($bk->label) . '</option>'; }
print '</select></div>';
print '</div>';
print '<div class="emp-exp-form-group"><label class="emp-exp-label">Description</label><textarea name="description" class="emp-exp-input" style="min-height: 80px;" placeholder="Détails de la dépense..." required></textarea></div>';
print '<div style="display: flex; gap: 8px;">';
print '<button type="submit" name="submitbutton" value="1" class="emp-exp-btn">✓ Soumettre</button>';
print '<button type="button" onclick="document.getElementById(\'formCreate\').classList.remove(\'active\');" class="emp-exp-btn" style="background: #94a3b8;">Annuler</button>';
print '</div>';
print '</form></div>';

// Stats
$stats = array(
	'pending' => 0,
	'validated' => 0,
	'refused' => 0,
);

$temp_res = $db->query($sql);
while ($temp_res && ($exp = $db->fetch_object($temp_res))) {
	if ($exp->status == 'PENDING') {
		$stats['pending'] += (float) $exp->amount;
	} elseif ($exp->status == 'VALIDATED') {
		$stats['validated'] += (float) $exp->amount;
	} elseif ($exp->status == 'REFUSED') {
		$stats['refused'] += (float) $exp->amount;
	}
}

print '<div class="emp-exp-stats">';
print '<div class="emp-exp-stat-card"><div class="emp-exp-stat-label">En attente</div><div class="emp-exp-stat-value" style="color: #f59e0b;">' . price($stats['pending']) . '</div></div>';
print '<div class="emp-exp-stat-card"><div class="emp-exp-stat-label">Validées</div><div class="emp-exp-stat-value green">' . price($stats['validated']) . '</div></div>';
print '<div class="emp-exp-stat-card"><div class="emp-exp-stat-label">Refusées</div><div class="emp-exp-stat-value red">' . price($stats['refused']) . '</div></div>';
print '</div>';

// Table
print '<table class="emp-exp-table">';
print '<thead><tr><th>Date</th><th>Catégorie</th><th>Description</th><th>Banque/Caisse</th><th style="text-align: right;">Montant</th><th>Statut</th></tr></thead>';
print '<tbody>';

$res = $db->query($sql);
if ($res && $db->num_rows($res) == 0) {
	print '<tr><td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">Aucune dépense soumise</td></tr>';
} else {
	while ($res && ($exp = $db->fetch_object($res))) {
		$status_label = $exp->status == 'PENDING' ? 'En attente' : ($exp->status == 'VALIDATED' ? 'Validée' : 'Refusée');
		$status_class = strtolower($exp->status);

		print '<tr>';
		print '<td>' . dol_print_date(strtotime($exp->expense_date), 'day') . '</td>';
		print '<td>' . dol_escape_htmltag($exp->category ?: 'Autre') . '</td>';
		print '<td>' . dol_escape_htmltag($exp->description) . '</td>';
		print '<td>' . ($exp->bank_label ? dol_escape_htmltag($exp->bank_label) : '<span style="color:#cbd5e1;">—</span>') . '</td>';
		print '<td style="text-align: right; font-weight: 700; color: #3b82f6;">' . price($exp->amount) . '</td>';
		print '<td><span class="emp-exp-status ' . $status_class . '">' . $status_label . '</span></td>';
		print '</tr>';
	}
}

print '</tbody></table>';

print '</div>';
llxFooter();
$db->close();
