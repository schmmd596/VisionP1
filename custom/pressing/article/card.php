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
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
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
	if ($object->status == 3) {
		setEventMessages('Modification impossible: l\'article est déjà livré.', null, 'errors');
	} else {
		$object->fk_entrepot = GETPOSTINT('fk_entrepot');
		$object->status = GETPOSTINT('status');
		$object->qty = GETPOSTINT('qty');
		$object->price = price2num(GETPOST('price'), 'MU');
		$object->note_private = GETPOST('note_private', 'alpha');

		if (empty($object->qty)) {
			$object->qty = 1;
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
}

// View
llxHeader('', 'Fiche Article Pressing');

// Include pressing stylesheet
require_once '../includes/header.php';

$form = new Form($db);
$formproduct = new FormProduct($db);

print load_fiche_titre('Article: ' . $object->ref_article, '', '');

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="id" value="'.$id.'">';

print '<div class="pressing-creation-card">';
if ($object->status == 3) {
	print '<div style="margin-bottom: 20px; padding: 15px; background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 8px; color: #28a745;">';
	print '<strong><i class="fas fa-check-circle"></i> Cet article a été livré.</strong> Les modifications ne sont plus possibles.';
	print '</div>';
}

print '<div class="pressing-form-group-card">';
print '<table class="pressing-table centpercent" style="border: none;">';

// Bon d'entrée
if ($object->fk_bon_entree > 0) {
	print '<tr style="border-bottom: 1px solid #eee;"><td class="titlefield" style="padding: 15px;"><i class="fas fa-file-invoice"></i> Bon d\'entrée</td><td style="padding: 15px;">';
	print '<a href="' . DOL_URL_ROOT . '/custom/pressing/bon_entree/card.php?id='.$object->fk_bon_entree.'" class="pressing-btn" style="background-color: #f8f9fa; color: #333; padding: 5px 15px;"><i class="fas fa-eye"></i> Voir le Bon</a>';
	print '</td></tr>';
}

// Produit
if ($object->fk_product > 0) {
	$prod = new Product($db);
	$prod->fetch($object->fk_product);
	print '<tr style="border-bottom: 1px solid #eee;"><td style="padding: 15px;"><i class="fas fa-box"></i> Produit</td><td style="padding: 15px;"><strong>' . $prod->ref . '</strong> - ' . $prod->label . '</td></tr>';
}

// Longueur / Largeur (Readonly on this page since it drives the price, but we display it if it exists)
if ($object->longueur > 0 || $object->largeur > 0) {
	print '<tr style="border-bottom: 1px solid #eee;"><td style="padding: 15px;"><i class="fas fa-ruler-combined"></i> Dimensions</td><td style="padding: 15px;">';
	print 'L: ' . $object->longueur . ' cm x l: ' . $object->largeur . ' cm (Surface: ' . number_format($object->surface, 4) . ' m²)';
	print '</td></tr>';
}

$disabled = ($object->status == 3) ? ' disabled' : '';

// Entrepôt
print '<tr style="border-bottom: 1px solid #eee;"><td style="padding: 15px;"><i class="fas fa-warehouse"></i> Entrepôt</td><td style="padding: 15px;">';
$ent_obj = new Entrepot($db);
$warehouses = $ent_obj->list_array();
if (is_array($warehouses) && !empty($warehouses)) {
	if ($object->status == 3) {
		print $warehouses[$object->fk_entrepot];
	} else {
		print $form->selectarray('fk_entrepot', $warehouses, $object->fk_entrepot, 0, 0);
	}
} else {
	print '<span class="error">Aucun entrepôt disponible</span>';
}
print '</td></tr>';

// Quantité
print '<tr style="border-bottom: 1px solid #eee;"><td style="padding: 15px;"><i class="fas fa-cubes"></i> Quantité</td><td style="padding: 15px;">';
print '<input type="number" name="qty" value="' . $object->qty . '" min="1" step="1" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;"' . $disabled . '>';
print '</td></tr>';

// Prix
print '<tr style="border-bottom: 1px solid #eee;"><td style="padding: 15px;"><i class="fas fa-price-tag"></i> Prix unitaire</td><td style="padding: 15px;">';
print '<input type="number" name="price" id="price" value="' . $object->price . '" step="0.01" min="0" required style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;"' . $disabled . '>';
print '</td></tr>';

// Statut
print '<tr style="border-bottom: 1px solid #eee;"><td style="padding: 15px;"><i class="fas fa-traffic-light"></i> Statut</td><td style="padding: 15px;">';
if ($object->status == 3) {
	print '<span class="article-status-3">Livré</span>';
} else {
	$status_array = array(
		0 => 'En attente',
		1 => 'En traitement',
		2 => 'Prêt à livrer'
		// Livré is not selectable manually
	);
	print $form->selectarray('status', $status_array, $object->status);
}
print '</td></tr>';

// Note
print '<tr><td style="padding: 15px;"><i class="fas fa-sticky-note"></i> Note</td><td style="padding: 15px;">';
print '<textarea name="note_private" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"' . $disabled . '>'.$object->note_private.'</textarea>';
print '</td></tr>';

print '</table>';
print '</div>';
print '</div>';

if ($object->status != 3) {
	print '<div class="center" style="margin-top: 20px;">';
	print '<button type="submit" class="pressing-btn"><i class="fas fa-save"></i> '.$langs->trans("Save").'</button>';
	print '</div>';
}

print '</form>';

llxFooter();
$db->close();
