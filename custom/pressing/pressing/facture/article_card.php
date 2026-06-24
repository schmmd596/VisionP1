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

$article = new PressingArticle($db);

if ($id > 0) {
	if ($article->fetch($id) != 0) {
		setEventMessages('Article not found', array(), 'errors');
		header('Location: article_list.php');
		exit;
	}
}

if ($action == 'add' && $user->hasRight('pressing', 'articles', 'write')) {
	$article->description = GETPOST('description', 'alphanohtml');
	$article->status = GETPOSTINT('status');
	$article->fk_facture = GETPOSTINT('fk_facture');
	$article->fk_entrepot = GETPOSTINT('fk_entrepot');
	$article->longueur = GETPOSTFLOAT('longueur');
	$article->largeur = GETPOSTFLOAT('largeur');
	$article->date_livraison_prevue = GETPOST('date_livraison_prevue');
	$article->notes = GETPOST('notes', 'alphanohtml');
	$article->fk_user_responsible = GETPOSTINT('fk_user_responsible');

	if ($article->create($user) == 0) {
		setEventMessages('Article créé', array(), 'mesgs');
		header('Location: article_card.php?id=' . $article->id);
		exit;
	} else {
		setEventMessages('Erreur lors de la création', array(), 'errors');
	}
}

if ($action == 'update' && $id > 0 && $user->hasRight('pressing', 'articles', 'write')) {
	$article->description = GETPOST('description', 'alphanohtml');
	$article->status = GETPOSTINT('status');
	$article->fk_entrepot = GETPOSTINT('fk_entrepot');
	$article->longueur = GETPOSTFLOAT('longueur');
	$article->largeur = GETPOSTFLOAT('largeur');
	$article->date_livraison_prevue = GETPOST('date_livraison_prevue');
	$article->notes = GETPOST('notes', 'alphanohtml');
	$article->fk_user_responsible = GETPOSTINT('fk_user_responsible');

	if ($article->update($user) == 0) {
		setEventMessages('Article mis à jour', array(), 'mesgs');
	} else {
		setEventMessages('Erreur lors de la mise à jour', array(), 'errors');
	}
}

llxHeader('', $id > 0 ? 'Éditer article' : 'Nouvel article');

print '<div class="fichecenter">';
print load_fiche_titre($id > 0 ? 'Éditer article' : 'Nouvel article', '', 'images/pressing.png');

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="action" value="' . ($id > 0 ? 'update' : 'add') . '">';
if ($id > 0) {
	print '<input type="hidden" name="id" value="' . intval($id) . '">';
}

print '<table class="border">';

print '<tr>';
print '<td class="titlefield">Référence</td>';
print '<td>';
if ($id > 0) {
	print htmlspecialchars($article->reference);
	print '<input type="hidden" name="reference" value="' . htmlspecialchars($article->reference) . '">';
} else {
	print 'Auto-générée';
}
print '</td>';
print '</tr>';

print '<tr>';
print '<td>Facture</td>';
print '<td>';
if ($id == 0) {
	print '<input type="number" name="fk_facture" value="' . GETPOSTINT('fk_facture') . '" class="flat">';
} else {
	if (!empty($article->fk_facture)) {
		print '<a href="' . DOL_URL_ROOT . '/compta/facture/card.php?id=' . intval($article->fk_facture) . '">Facture #' . intval($article->fk_facture) . '</a>';
	} else {
		print '-';
	}
}
print '</td>';
print '</tr>';

print '<tr>';
print '<td>Description</td>';
print '<td><input type="text" name="description" value="' . htmlspecialchars($article->description) . '" class="flat" style="width: 100%;"></td>';
print '</tr>';

print '<tr>';
print '<td>Longueur (cm)</td>';
print '<td><input type="number" name="longueur" value="' . $article->longueur . '" step="0.01" class="flat"></td>';
print '</tr>';

print '<tr>';
print '<td>Largeur (cm)</td>';
print '<td><input type="number" name="largeur" value="' . $article->largeur . '" step="0.01" class="flat"></td>';
print '</tr>';

print '<tr>';
print '<td colspan="2" style="background-color: #f5f5f5; padding: 10px;">';
print '<strong>Prix = Longueur × Largeur :</strong> ';
if (!empty($article->longueur) && !empty($article->largeur)) {
	$price = $article->longueur * $article->largeur;
	print number_format($price, 2);
} else {
	print 'Remplissez les dimensions';
}
print '</td>';
print '</tr>';

print '<tr>';
print '<td>Entrepôt</td>';
print '<td>';
print '<select name="fk_entrepot" class="flat">';
print '<option value="">-- Sélectionner --</option>';

$sql = "SELECT rowid, label FROM " . MAIN_DB_PREFIX . "entrepot WHERE entity = " . intval($conf->entity) . " AND statut = 1 ORDER BY label";
$res = $db->query($sql);
if ($res) {
	while ($obj = $db->fetch_object($res)) {
		$selected = ($article->fk_entrepot == $obj->rowid) ? 'selected' : '';
		print '<option value="' . intval($obj->rowid) . '" ' . $selected . '>' . htmlspecialchars($obj->label) . '</option>';
	}
}
print '</select>';
print '</td>';
print '</tr>';

print '<tr>';
print '<td>Statut</td>';
print '<td>';
print '<select name="status" class="flat">';
$statuses = array(0 => 'En attente', 1 => 'En cours', 2 => 'Prêt', 3 => 'Livré');
foreach ($statuses as $key => $val) {
	$selected = ($article->status == $key) ? 'selected' : '';
	print '<option value="' . intval($key) . '" ' . $selected . '>' . $val . '</option>';
}
print '</select>';
print '</td>';
print '</tr>';

print '<tr>';
print '<td>Date prévue</td>';
print '<td><input type="date" name="date_livraison_prevue" value="' . substr($article->date_livraison_prevue, 0, 10) . '" class="flat"></td>';
print '</tr>';

if ($id > 0 && !empty($article->date_livraison)) {
	print '<tr>';
	print '<td>Date réelle</td>';
	print '<td>' . dol_print_date(strtotime($article->date_livraison), 'dayhour') . '</td>';
	print '</tr>';
}

print '<tr>';
print '<td>Notes</td>';
print '<td><textarea name="notes" class="flat" rows="4" style="width: 100%;">' . htmlspecialchars($article->notes) . '</textarea></td>';
print '</tr>';

print '</table>';

print '<div style="margin-top: 20px; text-align: center;">';
print '<button type="submit" class="btn btn-primary">Enregistrer</button>';
print ' <a href="article_list.php" class="btn btn-secondary">Annuler</a>';
print '</div>';

print '</form>';
print '</div>';

llxFooter();
$db->close();
?>
