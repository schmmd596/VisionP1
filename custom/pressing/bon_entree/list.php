<?php
/**
 *	\file       custom/pressing/bon_entree/list.php
 *	\ingroup    pressing
 *	\brief      List of pressing reception orders (bons d'entrée)
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/client.class.php';
require_once '../class/pressingbonentree.class.php';

$langs->load("pressing@pressing");
$langs->load("companies");

// Access control
if (!$user->rights->pressing->read) {
	accessforbidden();
}

$page = GETPOSTINT('page') ? GETPOSTINT('page') : 0;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');

// View
llxHeader('', $langs->trans('Reception Orders'));

// Include pressing stylesheet
require_once '../includes/header.php';

$form = new Form($db);

print '<div class="pressing-header">';
print '<h1><i class="fas fa-inbox"></i> ' . $langs->trans('Reception Orders') . '</h1>';
print '</div>';

// Filters
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<table class="pressing-table">';
print '<thead><tr>';
print '<th><i class="fas fa-barcode"></i> Réf</th>';
print '<th><i class="fas fa-user"></i> Client</th>';
print '<th><i class="fas fa-calendar"></i> Date Entrée</th>';
print '<th><i class="fas fa-circle"></i> Statut</th>';
print '<th><i class="fas fa-box"></i> Articles</th>';
print '<th><i class="fas fa-cog"></i> Actions</th>';
print '</tr></thead><tbody>';

$sql = "SELECT pa.rowid, pa.ref, pa.fk_soc, pa.date_entree, pa.status,";
$sql .= " COUNT(DISTINCT article.rowid) as nb_articles";
$sql .= " FROM " . MAIN_DB_PREFIX . "pressing_bon_entree pa";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "pressing_article article ON article.fk_bon_entree = pa.rowid";
$sql .= " GROUP BY pa.rowid";
$sql .= " ORDER BY pa.date_entree DESC";
$sql .= " LIMIT 0, 100";

$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		print '<tr class="oddeven">';

		// Ref with link
		print '<td>';
		print '<a href="card.php?id='.$obj->rowid.'">'.$obj->ref.'</a>';
		print '</td>';

		// Client
		$soc = new Societe($db);
		$soc->fetch($obj->fk_soc);
		print '<td>'.$soc->name.'</td>';

		// Date
		print '<td>'.dol_print_date($obj->date_entree, 'day').'</td>';

		// Status
		$bon = new PressingBonEntree($db);
		$bon->fetch($obj->rowid);
		print '<td>';
		if ($obj->status == 0) {
			print '<span class="status-badge status-brouillon">Brouillon</span>';
		} elseif ($obj->status == 1) {
			print '<span class="status-badge status-valide">Validé</span>';
		} else {
			print '<span class="status-badge status-livre">Livré</span>';
		}
		print '</td>';

		// Articles count
		print '<td><strong>' . $obj->nb_articles . '</strong></td>';

		// Actions
		print '<td>';
		print '<a class="pressing-btn pressing-btn-primary" href="card.php?id='.$obj->rowid.'">';
		print '<i class="fas fa-eye"></i> Voir';
		print '</a>';
		print '</td>';

		print '</tr>';
	}
} else {
	dol_print_error($db);
}

print '</tbody></table>';
print '</form>';

// New button
if ($user->rights->pressing->write) {
	print '<div class="pressing-actions">';
	print '<a class="pressing-btn pressing-btn-success" href="card.php?action=create">';
	print '<i class="fas fa-plus-circle"></i> ' . $langs->trans('NewReceptionOrder');
	print '</a>';
	print '</div>';
}

llxFooter();
$db->close();
