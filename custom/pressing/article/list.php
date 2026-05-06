<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once '../class/pressingarticle.class.php';
require_once '../class/pressingbonentree.class.php';

$langs->load("pressing@pressing");
if (!$user->rights->pressing->read) accessforbidden();

llxHeader('', 'Articles Pressing');

// Include pressing stylesheet
require_once '../includes/header.php';

$form = new Form($db);

print '<div class="pressing-header">';
print '<h1><i class="fas fa-cubes"></i> Liste de tous les Articles</h1>';
print '</div>';

$sql = "SELECT pa.rowid, pa.ref_article, pa.fk_bon_entree, pa.qty, pa.price, pa.status,";
$sql .= " pb.ref as bon_ref FROM " . MAIN_DB_PREFIX . "pressing_article pa";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "pressing_bon_entree pb ON pb.rowid = pa.fk_bon_entree";
$sql .= " ORDER BY pa.rowid DESC LIMIT 100";

$resql = $db->query($sql);
if ($resql) {
	print '<table class="pressing-table">';
	print '<thead><tr>';
	print '<th><i class="fas fa-barcode"></i> Réf</th>';
	print '<th><i class="fas fa-inbox"></i> Bon Entrée</th>';
	print '<th><i class="fas fa-box"></i> Qté</th>';
	print '<th><i class="fas fa-coins"></i> Prix unitaire</th>';
	print '<th><i class="fas fa-circle"></i> Statut</th>';
	print '<th><i class="fas fa-cog"></i> Actions</th>';
	print '</tr></thead><tbody>';

	while ($obj = $db->fetch_object($resql)) {
		$art = new PressingArticle($db);
		print '<tr>';
		print '<td><strong>' . $obj->ref_article . '</strong></td>';
		print '<td><a href="' . DOL_URL_ROOT . '/custom/pressing/bon_entree/card.php?id='.$obj->fk_bon_entree.'">' . $obj->bon_ref . '</a></td>';
		print '<td>' . $obj->qty . '</td>';
		print '<td><strong>' . price($obj->price) . '</strong></td>';
		print '<td>';
		$status_class = '';
		if ($obj->status == 0) {
			print '<span class="status-badge status-attente">Attente</span>';
		} elseif ($obj->status == 1) {
			print '<span class="status-badge status-traitement">Traitement</span>';
		} elseif ($obj->status == 2) {
			print '<span class="status-badge status-pret">Prêt</span>';
		} else {
			print '<span class="status-badge status-livre">Livré</span>';
		}
		print '</td>';
		print '<td><a class="pressing-btn pressing-btn-primary" href="card.php?id='.$obj->rowid.'">';
		print '<i class="fas fa-edit"></i> Modifier';
		print '</a></td>';
		print '</tr>';
	}
	print '</tbody></table>';
}

llxFooter();
$db->close();
