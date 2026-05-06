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
$ent_obj = new Entrepot($db);
$warehouses = $ent_obj->list_array();
if (is_array($warehouses) && !empty($warehouses)) {
	print $form->selectarray('fk_entrepot', $warehouses, $object->fk_entrepot, 0, 0);
} else {
	print '<span class="error">Aucun entrepôt disponible</span>';
}
print '</td></tr>';

// Quantité
print '<tr><td>Quantité</td><td>';
print '<input type="number" name="qty" value="' . $object->qty . '" min="1" step="1">';
print '</td></tr>';

// Prix
print '<tr><td>Prix unitaire</td><td>';
print '<input type="number" name="price" id="price" value="' . $object->price . '" step="0.01" min="0" required>';
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

llxFooter();
$db->close();
