<?php

$res = 0;
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res && file_exists("../../../../../main.inc.php")) {
	$res = @include "../../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once dirname(__FILE__).'/../../class/pressingarticle.class.php';

$langs->load("pressing@pressing");
$langs->load("bills");

if (!$user->hasRight('pressing', 'articles', 'read')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$status = GETPOSTINT('status', -1);

if ($action == 'delete' && $id && $user->hasRight('pressing', 'articles', 'delete')) {
	$article = new PressingArticle($db);
	if ($article->fetch($id) == 0) {
		if ($article->delete() == 0) {
			setEventMessages($langs->trans('ArticleDeleted'), array(), 'mesgs');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "pressing_article";
$sql .= " WHERE entity = " . intval($conf->entity);

if ($status >= 0) {
	$sql .= " AND status = " . intval($status);
}

$sql .= " ORDER BY datec DESC";

$articles = array();
$res = $db->query($sql);
if ($res) {
	while ($obj = $db->fetch_object($res)) {
		$article = new PressingArticle($db);
		$article->fetch($obj->rowid);
		$articles[] = $article;
	}
}

llxHeader('', 'Articles Pressing');

print '<div class="fichecenter">';
print load_fiche_titre('Articles Pressing', '', 'images/pressing.png');

print '<div class="div-table-responsive-no-min">';
print '<table class="liste">';

print '<tr class="liste_titre">';
print '<th>Référence</th>';
print '<th>Facture</th>';
print '<th>Description</th>';
print '<th>Statut</th>';
print '<th>Entrepôt</th>';
print '<th>Prix</th>';
print '<th>Dépôt</th>';
print '<th>Actions</th>';
print '</tr>';

$i = 0;
foreach ($articles as $article) {
	$i++;
	$status_label = PressingArticle::getStatusLabel($article->status);

	print '<tr class="' . ($i % 2 == 0 ? 'pair' : 'impair') . '">';
	print '<td><a href="article_card.php?id=' . intval($article->id) . '">' . htmlspecialchars($article->reference) . '</a></td>';
	print '<td>';
	if (!empty($article->fk_facture)) {
		print '<a href="' . DOL_URL_ROOT . '/compta/facture/card.php?id=' . intval($article->fk_facture) . '">' . intval($article->fk_facture) . '</a>';
	}
	print '</td>';
	print '<td>' . htmlspecialchars($article->description) . '</td>';
	print '<td>' . $status_label . '</td>';
	print '<td>' . $article->getWarehouseLabel($article->fk_entrepot) . '</td>';
	print '<td>' . (!empty($article->price_unit) ? number_format($article->price_unit, 2) : '-') . '</td>';
	print '<td>' . dol_print_date(strtotime($article->datec), 'day') . '</td>';
	print '<td>';
	print '<a class="btn btn-sm btn-primary" href="article_card.php?id=' . intval($article->id) . '">Éditer</a> ';
	if ($user->hasRight('pressing', 'articles', 'delete')) {
		print '<a class="btn btn-sm btn-danger" href="' . $_SERVER['PHP_SELF'] . '?action=delete&id=' . intval($article->id) . '" onclick="return confirm(\'Supprimer ?\')">Supprimer</a>';
	}
	print '</td>';
	print '</tr>';
}

if (count($articles) == 0) {
	print '<tr><td colspan="8" class="center">Aucun article</td></tr>';
}

print '</table>';
print '</div>';

if ($user->hasRight('pressing', 'articles', 'write')) {
	print '<div style="margin-top: 20px;">';
	print '<a href="article_card.php" class="btn btn-primary">Ajouter un article</a>';
	print '</div>';
}

print '</div>';

llxFooter();
$db->close();
?>
