<?php
/**
 *	\file       custom/pressing/article/card.php
 *	\ingroup    pressing
 *	\brief      Card of pressing article
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once '../class/pressingarticle.class.php';

$langs->load("pressing@pressing");

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

if (!$user->rights->pressing->read) accessforbidden();

$object = new PressingArticle($db);
if ($id > 0) {
	$object->fetch($id);
}

// Actions
if ($action == 'update' && $user->rights->pressing->write) {
	$object->fk_entrepot = GETPOSTINT('fk_entrepot');
	$object->status = GETPOSTINT('status');
	$object->longueur = floatval(GETPOST('longueur', 'float'));
	$object->largeur = floatval(GETPOST('largeur', 'float'));
	$object->price = floatval(GETPOST('price', 'float'));
	$object->note_private = GETPOST('note_private', 'alpha');

	if ($object->longueur > 0 && $object->largeur > 0) {
		$object->surface = $object->calculateSurface($object->longueur, $object->largeur);
	}

	$res = $object->update($user);
	if ($res > 0) {
		setEventMessages('Article mis à jour', null, 'mesgs');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
		exit;
	} else {
		setEventMessages($object->error, null, 'errors');
	}
}

// View
llxHeader('', 'Fiche Article Pressing');

$form = new Form($db);
$formproduct = new FormProduct($db);

print load_fiche_titre('Article: ' . $object->ref_article, '', '');

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="id" value="'.$id.'">';

print '<div class="fichecenter">';
print '<table class="border centpercent">';

// Bon d'entrée
if ($object->fk_bon_entree > 0) {
	print '<tr><td class="titlefield">Bon d\'entrée</td><td>';
	print '<a href="' . DOL_URL_ROOT . '/custom/pressing/bon_entree/card.php?id='.$object->fk_bon_entree.'">Voir</a>';
	print '</td></tr>';
}

// Produit
if ($object->fk_product > 0) {
	$prod = new Product($db);
	$prod->fetch($object->fk_product);
	print '<tr><td>Produit</td><td>' . $prod->ref . ' - ' . $prod->label . '</td></tr>';
}

// Entrepôt
print '<tr><td>Entrepôt</td><td>';
$formproduct->selectWarehouses($object->fk_entrepot, 'fk_entrepot', '', 1);
print '</td></tr>';

// Dimensions
print '<tr><td>Longueur (cm)</td><td>';
print '<input type="number" name="longueur" id="longueur" value="'.$object->longueur.'" step="0.1" min="0">';
print '</td></tr>';

print '<tr><td>Largeur (cm)</td><td>';
print '<input type="number" name="largeur" id="largeur" value="'.$object->largeur.'" step="0.1" min="0">';
print '</td></tr>';

// Surface
print '<tr><td>Surface (m²)</td><td>';
print '<span id="surface_display">' . ($object->surface ? number_format($object->surface, 4) : '-') . '</span> m²';
print '</td></tr>';

// Prix
print '<tr><td>Prix (€)</td><td>';
print '<input type="number" name="price" id="price" value="' . number_format($object->price, 2) . '" step="0.01" min="0">';
print '</td></tr>';

// Statut
print '<tr><td>Statut</td><td>';
$status_array = array(
	0 => 'En attente',
	1 => 'En traitement',
	2 => 'Prêt à livrer',
	3 => 'Livré'
);
print $form->selectarray('status', $status_array, $object->status);
print '</td></tr>';

// Note
print '<tr><td>Note</td><td>';
print '<textarea name="note_private" rows="3" class="centpercent">'.$object->note_private.'</textarea>';
print '</td></tr>';

print '</table>';
print '</div>';

print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

// JavaScript for surface display
print '<script type="text/javascript">
document.getElementById("longueur").addEventListener("input", updateSurface);
document.getElementById("largeur").addEventListener("input", updateSurface);

function updateSurface() {
	const l = parseFloat(document.getElementById("longueur").value) || 0;
	const lg = parseFloat(document.getElementById("largeur").value) || 0;
	const surface = (l > 0 && lg > 0) ? ((l * lg) / 10000).toFixed(4) : "-";
	document.getElementById("surface_display").textContent = surface;
}
</script>';

llxFooter();
$db->close();
