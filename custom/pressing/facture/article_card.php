<?php
/**
 *	\file       custom/pressing/facture/article_card.php
 *	\ingroup    pressing
 *	\brief      Card of pressing article
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once '../class/pressingarticle.class.php';

$langs->load("pressing@pressing");

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');

// Access control
if (!$user->rights->pressing->read) accessforbidden();

$object = new PressingArticle($db);
if ($id > 0) {
	$object->fetch($id);
}

// Actions
if ($action == 'update' && $user->rights->pressing->write) {
	$object->fk_entrepot = GETPOST('fk_entrepot', 'int');
	$object->status = GETPOST('status', 'int');
	$object->longueur = GETPOST('longueur', 'float');
	$object->largeur = GETPOST('largeur', 'float');
	$object->note_private = GETPOST('note_private', 'alpha');
	$object->price = GETPOST('price', 'float');

	if ($object->longueur > 0 && $object->largeur > 0) {
		$object->surface = ($object->longueur * $object->largeur) / 10000; // if cm -> m2
	}

	$res = $object->update($user);
	if ($res > 0) {
		setEventMessages('Mise à jour effectuée', null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// Recalculate price action
if ($action == 'recalc_price' && $user->rights->pressing->write) {
	$tmp_product = new Product($db);
	if ($object->fk_product > 0 && $object->longueur > 0 && $object->largeur > 0) {
		$tmp_product->fetch($object->fk_product);
		$object->surface = ($object->longueur * $object->largeur) / 10000;
		$object->price = round($object->surface * $tmp_product->price, 2);
		$res = $object->update($user);
		if ($res > 0) {
			setEventMessages('Prix recalculé automatiquement: ' . number_format($object->price, 2) . ' €', null, 'mesgs');
		}
	} else {
		setEventMessages('Impossible de recalculer: dimensions ou produit manquant', null, 'errors');
	}
}

// View
llxHeader('', 'Fiche Article Pressing');

$form = new Form($db);
$formproduct = new FormProduct($db);

print load_fiche_titre('Article: ' . $object->ref_article, '', 'title_generic.png');

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="id" value="'.$id.'">';

print '<div class="fichecenter">';
print '<table class="border centpercent">';

// Facture
print '<tr><td class="titlefield">Facture liée</td><td>';
print '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$object->fk_facture.'">Voir facture</a>';
print '</td></tr>';

// Entrepôt
print '<tr><td>Entrepôt</td><td>';
$formproduct->selectWarehouses($object->fk_entrepot, 'fk_entrepot', '', 1);
print '</td></tr>';

// Dimensions
print '<tr><td>Longueur (cm)</td><td>';
print '<input type="number" name="longueur" id="longueur_edit" value="'.$object->longueur.'" step="0.1" min="0">';
print '</td></tr>';

print '<tr><td>Largeur (cm)</td><td>';
print '<input type="number" name="largeur" id="largeur_edit" value="'.$object->largeur.'" step="0.1" min="0">';
print '</td></tr>';

// Surface and Price
print '<tr><td>Surface (m²)</td><td>';
print '<span id="surface_edit_display">' . ($object->surface ? number_format($object->surface, 4) : '-') . '</span> m²';
print '</td></tr>';

print '<tr><td>Prix Calculé/Manuel (€)</td><td>';
print '<input type="number" step="0.01" name="price" id="price_edit" value="'.$object->price.'" min="0">';
print ' <span id="price_info_edit" style="margin-left: 10px; color: #666; font-size: 0.9em;"></span>';
print '</td></tr>';

// Recalculate button
if ($object->fk_product > 0 && $object->longueur > 0 && $object->largeur > 0) {
	print '<tr><td colspan="2">';
	print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=recalc_price" class="button" style="margin-right: 10px;">🔄 Recalculer le prix</a>';
	$tmp_product = new Product($db);
	$tmp_product->fetch($object->fk_product);
	print '<span style="color: #666; font-size: 0.9em;">Basé sur: ' . number_format($object->surface, 4) . ' m² × ' . number_format($tmp_product->price, 2) . ' €/m²</span>';
	print '</td></tr>';
}

// Statut
print '<tr><td>Statut</td><td>';
$status_array = array(
	0 => 'Réception',
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

// JavaScript for real-time calculation in edit form
print '<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
	const longueurEl = document.getElementById("longueur_edit");
	const largeurEl = document.getElementById("largeur_edit");
	const surfaceDisplayEl = document.getElementById("surface_edit_display");

	function updateSurfaceDisplay() {
		const longueur = parseFloat(longueurEl.value) || 0;
		const largeur = parseFloat(largeurEl.value) || 0;

		if (longueur > 0 && largeur > 0) {
			const surface = (longueur * largeur) / 10000; // cm² to m²
			surfaceDisplayEl.textContent = surface.toFixed(4);
		} else {
			surfaceDisplayEl.textContent = "-";
		}
	}

	longueurEl.addEventListener("change", updateSurfaceDisplay);
	longueurEl.addEventListener("input", updateSurfaceDisplay);
	largeurEl.addEventListener("change", updateSurfaceDisplay);
	largeurEl.addEventListener("input", updateSurfaceDisplay);
});
</script>';

llxFooter();
$db->close();
