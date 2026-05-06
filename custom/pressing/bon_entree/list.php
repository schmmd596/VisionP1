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

$form = new Form($db);

print load_fiche_titre($langs->trans('Reception Orders'), '', 'title_generic.png');

// Filters
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre">';
print '<td>Réf</td>';
print '<td>Client</td>';
print '<td>Date Entrée</td>';
print '<td>Statut</td>';
print '<td>Articles</td>';
print '<td>Actions</td>';
print '</tr>';

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
			print '<span class="badge badge-warning">Brouillon</span>';
		} elseif ($obj->status == 1) {
			print '<span class="badge badge-info">Validé</span>';
		} else {
			print '<span class="badge badge-success">Livré</span>';
		}
		print '</td>';

		// Articles count
		print '<td>'.$obj->nb_articles.'</td>';

		// Actions
		print '<td>';
		print '<a class="button" href="card.php?id='.$obj->rowid.'">Voir</a>';
		print '</td>';

		print '</tr>';
	}
} else {
	dol_print_error($db);
}

print '</table>';
print '</form>';

// New button
if ($user->rights->pressing->write) {
	print '<br>';
	print '<a class="butAction" href="card.php?action=create">'.$langs->trans('NewReceptionOrder').'</a>';
}

llxFooter();
$db->close();
