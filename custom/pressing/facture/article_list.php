<?php
/**
 *	\file       custom/pressing/facture/article_list.php
 *	\ingroup    pressing
 *	\brief      List of pressing articles
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once '../class/pressingarticle.class.php';

$langs->load("pressing@pressing");

// Access control
if (!$user->rights->pressing->read) accessforbidden();

$action = GETPOST('action', 'alpha');

// View
llxHeader('', 'Liste des articles Pressing');

$form = new Form($db);

print load_fiche_titre('Articles Pressing', '', 'title_generic.png');

$sql = "SELECT pa.rowid, pa.ref_article, pa.fk_facture, pa.longueur, pa.largeur, pa.surface, pa.status, f.ref as facture_ref";
$sql .= " FROM " . MAIN_DB_PREFIX . "pressing_article as pa";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "facture as f ON f.rowid = pa.fk_facture";
$sql .= " ORDER BY pa.rowid DESC";

$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	$i = 0;

	print '<table class="liste centpercent">';
	print '<tr class="liste_titre">';
	print '<td>Réf Article</td>';
	print '<td>Facture</td>';
	print '<td>Dimensions (L x l)</td>';
	print '<td>Surface (m²)</td>';
	print '<td>Statut</td>';
	print '<td>Actions</td>';
	print '</tr>';

	$article_tmp = new PressingArticle($db);

	while ($i < $num) {
		$obj = $db->fetch_object($resql);
		
		print '<tr class="oddeven">';
		print '<td>' . $obj->ref_article . '</td>';
		print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj->fk_facture.'">' . $obj->facture_ref . '</a></td>';
		print '<td>' . (empty($obj->longueur) ? '' : ($obj->longueur . ' x ' . $obj->largeur)) . '</td>';
		print '<td>' . (empty($obj->surface) ? '' : $obj->surface) . '</td>';
		print '<td>' . $article_tmp->getStatusLabel($obj->status) . '</td>';
		print '<td><a href="article_card.php?id='.$obj->rowid.'">Modifier</a></td>';
		print '</tr>';
		$i++;
	}
	print '</table>';
} else {
	dol_print_error($db);
}

llxFooter();
$db->close();
