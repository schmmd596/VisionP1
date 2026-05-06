<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once '../class/pressingarticle.class.php';
require_once '../class/pressingbonentree.class.php';

$langs->load("pressing@pressing");
if (!$user->rights->pressing->read) accessforbidden();

llxHeader('', 'Articles Pressing');
$form = new Form($db);

print load_fiche_titre('Liste de tous les Articles', '', '');

$sql = "SELECT pa.rowid, pa.ref_article, pa.fk_bon_entree, pa.longueur, pa.largeur, pa.surface, pa.price, pa.status,";
$sql .= " pb.ref as bon_ref FROM " . MAIN_DB_PREFIX . "pressing_article pa";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "pressing_bon_entree pb ON pb.rowid = pa.fk_bon_entree";
$sql .= " ORDER BY pa.rowid DESC LIMIT 100";

$resql = $db->query($sql);
if ($resql) {
	print '<table class="liste centpercent">';
	print '<tr class="liste_titre">';
	print '<td>Réf</td>';
	print '<td>Bon Entrée</td>';
	print '<td>Dimensions (L x l)</td>';
	print '<td>Surface (m²)</td>';
	print '<td>Prix</td>';
	print '<td>Statut</td>';
	print '<td>Actions</td>';
	print '</tr>';

	while ($obj = $db->fetch_object($resql)) {
		$art = new PressingArticle($db);
		print '<tr class="oddeven">';
		print '<td>' . $obj->ref_article . '</td>';
		print '<td><a href="' . DOL_URL_ROOT . '/custom/pressing/bon_entree/card.php?id='.$obj->fk_bon_entree.'">' . $obj->bon_ref . '</a></td>';
		print '<td>' . (empty($obj->longueur) ? '-' : ($obj->longueur . ' x ' . $obj->largeur)) . ' cm</td>';
		print '<td>' . (empty($obj->surface) ? '-' : number_format($obj->surface, 4)) . '</td>';
		print '<td>' . number_format($obj->price, 2) . ' €</td>';
		print '<td>';
		if ($obj->status == 0) print '<span class="badge badge-warning">Attente</span>';
		elseif ($obj->status == 1) print '<span class="badge badge-info">Traitement</span>';
		elseif ($obj->status == 2) print '<span class="badge badge-primary">Prêt</span>';
		else print '<span class="badge badge-success">Livré</span>';
		print '</td>';
		print '<td><a class="button" href="card.php?id='.$obj->rowid.'">Modifier</a></td>';
		print '</tr>';
	}
	print '</table>';
}

llxFooter();
$db->close();
