<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once '../class/pressingarticle.class.php';

if (!$user->rights->pressing->read) accessforbidden();

$id = GETPOSTINT('id');
$ent = new Entrepot($db);
$ent->fetch($id);

llxHeader('', 'Entrepôt - ' . $ent->label);

print load_fiche_titre('Entrepôt: ' . $ent->label, '<a href="list.php">Retour</a>', '');

// Articles by status
$art = new PressingArticle($db);
$articles = $art->getByWarehouse($id);

$stats = array(0=>0, 1=>0, 2=>0, 3=>0);
foreach ($articles as $a) {
	$stats[$a->status]++;
}

print '<div style="margin-bottom: 20px;">';
print '<b>Résumé:</b> ';
print 'En attente: ' . $stats[0] . ' | ';
print 'En traitement: ' . $stats[1] . ' | ';
print 'Prêt: ' . $stats[2] . ' | ';
print 'Livré: ' . $stats[3];
print '</div>';

// Articles list
print load_fiche_titre('Articles dans cet Entrepôt', '', '');

print '<table class="liste centpercent">';
print '<tr class="liste_titre">';
print '<td>Réf</td>';
print '<td>Bon</td>';
print '<td>Dimensions</td>';
print '<td>Surface</td>';
print '<td>Prix</td>';
print '<td>Statut</td>';
print '<td>Actions</td>';
print '</tr>';

if (!empty($articles)) {
	foreach ($articles as $article) {
		print '<tr class="oddeven">';
		print '<td>' . $article->ref_article . '</td>';
		print '<td>';
		if ($article->fk_bon_entree > 0) {
			print '<a href="' . DOL_URL_ROOT . '/custom/pressing/bon_entree/card.php?id='.$article->fk_bon_entree.'">Voir bon</a>';
		}
		print '</td>';
		print '<td>' . (empty($article->longueur) ? '-' : ($article->longueur . 'x' . $article->largeur . ' cm')) . '</td>';
		print '<td>' . (empty($article->surface) ? '-' : number_format($article->surface, 4)) . ' m²</td>';
		print '<td>' . number_format($article->price, 2) . ' €</td>';
		print '<td>';
		if ($article->status == 0) print '<span class="badge badge-warning">Attente</span>';
		elseif ($article->status == 1) print '<span class="badge badge-info">Traitement</span>';
		elseif ($article->status == 2) print '<span class="badge badge-primary">Prêt</span>';
		else print '<span class="badge badge-success">Livré</span>';
		print '</td>';
		print '<td><a class="button" href="' . DOL_URL_ROOT . '/custom/pressing/article/card.php?id='.$article->id.'">Modifier</a></td>';
		print '</tr>';
	}
} else {
	print '<tr><td colspan="7" class="opacitymedium">Aucun article.</td></tr>';
}
print '</table>';

llxFooter();
$db->close();
