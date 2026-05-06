<?php
/**
 *	\file       custom/pressing/facture/card.php
 *	\ingroup    pressing
 *	\brief      Card of pressing invoice and articles
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once '../class/pressingarticle.class.php';
require_once '../lib/pressing.lib.php';

$langs->load("pressing@pressing");
$langs->load("bills");

$facid = GETPOST('facid', 'int');
$action = GETPOST('action', 'alpha');

// Access control
if (!$user->rights->pressing->read) accessforbidden();

$facture = new Facture($db);
if ($facid > 0) {
	$facture->fetch($facid);
} else {
	dol_print_error($db, 'Missing facid');
	exit;
}

// Fetch articles
$articles = array();
$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "pressing_article WHERE fk_facture = " . $facid;
$resql = $db->query($sql);
$all_ready = true;
$has_undelivered = false;
$nb_articles = 0;

if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$art = new PressingArticle($db);
		$art->fetch($obj->rowid);
		$articles[] = $art;
		
		if ($art->status < 3) {
			$has_undelivered = true;
			if ($art->status < 2) {
				$all_ready = false;
			}
		}
		$nb_articles++;
	}
}

// Actions
if ($action == 'deliver' && $user->rights->pressing->deliver) {
	$error = 0;
	$db->begin();
	
	if ($all_ready && $has_undelivered) {
		foreach ($articles as $art) {
			if ($art->status == 2) {
				$res = pressing_deliver_article($db, $art, $user);
				if ($res < 0) {
					$error++;
					setEventMessages('Erreur lors de la livraison de l\'article ' . $art->ref_article, null, 'errors');
				}
			}
		}
	} else {
		$error++;
		setEventMessages('Tous les articles ne sont pas prêts à être livrés.', null, 'errors');
	}
	
	if (!$error) {
		$db->commit();
		setEventMessages('Livraison effectuée avec succès.', null, 'mesgs');
		
		// Refresh articles
		header("Location: ".$_SERVER["PHP_SELF"]."?facid=".$facid);
		exit;
	} else {
		$db->rollback();
	}
}

if ($action == 'add_article' && $user->rights->pressing->write) {
	$new_art = new PressingArticle($db);
	$new_art->fk_facture = $facid;
	$new_art->fk_product = GETPOST('fk_product', 'int');
	$new_art->ref_article = GETPOST('ref_article', 'alpha');
	$new_art->fk_entrepot = GETPOST('fk_entrepot', 'int');
	$new_art->longueur = GETPOST('longueur', 'float');
	$new_art->largeur = GETPOST('largeur', 'float');
	$new_art->price = GETPOST('price', 'float');

	if ($new_art->longueur > 0 && $new_art->largeur > 0) {
		$new_art->surface = ($new_art->longueur * $new_art->largeur) / 10000;
	}

	// If price not provided, calculate it
	if (empty($new_art->price)) {
		$tmp_product = new Product($db);
		if ($new_art->fk_product > 0) {
			$tmp_product->fetch($new_art->fk_product);
			if ($new_art->surface > 0) {
				$new_art->price = $new_art->surface * $tmp_product->price;
			} else {
				$new_art->price = $tmp_product->price;
			}
		}
	}

	$res = $new_art->create($user);
	if ($res > 0) {
		// Auto-add invoice line
		if ($new_art->fk_product > 0 && $new_art->price > 0) {
			$tmp_product = new Product($db);
			$tmp_product->fetch($new_art->fk_product);

			$desc = "Article Pressing: " . $new_art->ref_article;
			if ($new_art->longueur > 0 && $new_art->largeur > 0) {
				$desc .= " (" . $new_art->longueur . "x" . $new_art->largeur . " cm, " . number_format($new_art->surface, 4) . " m²)";
			}
			$facture->addline($desc, $new_art->price, 1, $tmp_product->tva_tx, 0, 0, $new_art->fk_product, 0, '', '', 0, 0, '', 'HT');
		}

		setEventMessages('Article ajouté avec succès', null, 'mesgs');
		header("Location: ".$_SERVER["PHP_SELF"]."?facid=".$facid);
		exit;
	} else {
		setEventMessages($new_art->error, $new_art->errors, 'errors');
	}
}


// View
llxHeader('', 'Gestion Facture Pressing');

$form = new Form($db);
$formproduct = new FormProduct($db);

$head = array();
$h = 0;
$head[$h][0] = DOL_URL_ROOT . '/compta/facture/card.php?facid=' . $facid;
$head[$h][1] = $langs->trans("Card");
$head[$h][2] = 'card';
$h++;

$head[$h][0] = dol_buildpath('/pressing/facture/card.php?facid=' . $facid, 1);
$head[$h][1] = 'Pressing';
$head[$h][2] = 'pressing';
$h++;

print dol_get_fiche_head($head, 'pressing', $langs->trans("Invoice"), -1, 'bill');

// Facture summary
$linkback = '<a href="'.DOL_URL_ROOT.'/compta/facture/list.php">'.$langs->trans("BackToList").'</a>';
dol_banner_tab($facture, 'ref', $linkback, 1, 'ref', 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '</div>';
print dol_get_fiche_end();

// Bouton Livrer
if ($nb_articles > 0 && $has_undelivered) {
	print '<div class="tabsAction">';
	if ($all_ready) {
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?facid='.$facid.'&action=deliver">Livrer (Générer Mouvement Stock)</a>';
	} else {
		print '<a class="butActionRefused" href="#" title="Tous les articles doivent être Prêt à livrer">Livrer (Générer Mouvement Stock)</a>';
	}
	print '</div>';
}

// Status summary
if ($nb_articles > 0) {
	$status_counts = array(0=>0, 1=>0, 2=>0, 3=>0);
	foreach ($articles as $art) {
		$status_counts[$art->status]++;
	}
	print '<div style="margin-bottom: 20px;">';
	print '<b>Résumé de l\'état de lavage/pressing :</b><br>';
	print '<ul>';
	print '<li>Pièces en réception (non lavées) : ' . $status_counts[0] . '</li>';
	print '<li>Pièces en cours de lavage : ' . $status_counts[1] . '</li>';
	print '<li>Pièces prêtes à être livrées : ' . $status_counts[2] . '</li>';
	print '<li>Pièces livrées au client : ' . $status_counts[3] . '</li>';
	print '</ul>';
	print '</div>';
}

print load_fiche_titre('Articles de cette facture', '', '');

print '<table class="liste centpercent">';
print '<tr class="liste_titre">';
print '<td>Réf Article</td>';
print '<td>Produit/Service</td>';
print '<td>Entrepôt</td>';
print '<td>Dimensions</td>';
print '<td>Statut</td>';
print '<td>Actions</td>';
print '</tr>';

if ($nb_articles > 0) {
	$product_static = new Product($db);
	$entrepot_static = new Entrepot($db);
	
	foreach ($articles as $art) {
		print '<tr class="oddeven">';
		print '<td>' . $art->ref_article . '</td>';
		
		$prod_label = '';
		if ($art->fk_product > 0) {
			$product_static->fetch($art->fk_product);
			$prod_label = $product_static->ref . ' - ' . $product_static->label;
		}
		print '<td>' . $prod_label . '</td>';
		
		$ent_label = '';
		if ($art->fk_entrepot > 0) {
			$entrepot_static->fetch($art->fk_entrepot);
			$ent_label = $entrepot_static->label;
		}
		print '<td>' . $ent_label . '</td>';
		
		print '<td>' . (empty($art->longueur) ? '' : ($art->longueur . ' x ' . $art->largeur)) . '</td>';
		print '<td>' . $art->getStatusLabel() . '</td>';
		print '<td><a href="article_card.php?id='.$art->id.'">Modifier</a></td>';
		print '</tr>';
	}
} else {
	print '<tr><td colspan="6" class="opacitymedium">Aucun article lié à cette facture.</td></tr>';
}

print '</table>';

// Ajouter un article
if ($user->rights->pressing->write) {
	print '<br>';
	print load_fiche_titre('Ajouter un article', '', '');
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?facid='.$facid.'" id="form_add_article">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add_article">';

	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">Réf Article (ex: code barre)</td><td><input type="text" name="ref_article" required></td></tr>';

	print '<tr><td>Produit/Service (Sert de base pour le prix/m²)</td><td>';
	$form->select_produits('', 'fk_product', '', 0, 0, -1, 2, '', 1, array(), 0, '1', 0, '', 0, '1');
	print ' <span id="product_price_info" style="margin-left: 10px; color: #666;"></span>';
	print '</td></tr>';

	print '<tr><td>Longueur (cm)</td><td><input type="number" name="longueur" id="longueur" value="" step="0.1" min="0"></td></tr>';
	print '<tr><td>Largeur (cm)</td><td><input type="number" name="largeur" id="largeur" value="" step="0.1" min="0"></td></tr>';

	print '<tr><td>Surface (m²)</td><td><span id="surface_display">-</span> m²</td></tr>';

	print '<tr><td>Prix calculé/modifié (€)</td><td>';
	print '<input type="number" name="price" id="price" value="" step="0.01" min="0" style="width: 150px;">';
	print ' <span id="price_formula" style="margin-left: 10px; color: #666; font-size: 0.9em;"></span>';
	print '</td></tr>';

	print '<tr><td>Entrepôt (Affectation)</td><td>';
	$formproduct->selectWarehouses('', 'fk_entrepot', '', 1);
	print '</td></tr>';

	print '</table>';
	print '<div class="center"><input type="submit" class="button" value="Ajouter"></div>';
	print '</form>';

	// JavaScript for real-time calculation
	print '<script type="text/javascript">
	document.addEventListener("DOMContentLoaded", function() {
		const formEl = document.getElementById("form_add_article");
		const longueurEl = document.getElementById("longueur");
		const largeurEl = document.getElementById("largeur");
		const priceEl = document.getElementById("price");
		const surfaceDisplayEl = document.getElementById("surface_display");
		const priceFormulaEl = document.getElementById("price_formula");
		const fkProductEl = document.querySelector("select[name=\"fk_product\"]");
		const productPriceInfoEl = document.getElementById("product_price_info");

		let productPrices = {};

		// Load product prices via AJAX
		function loadProductPrices() {
			const options = fkProductEl.querySelectorAll("option");
			options.forEach(option => {
				if (option.value) {
					fetch("'.DOL_URL_ROOT.'/product/ajax/products.php?action=fetch&id=" + option.value)
						.then(r => r.json())
						.then(d => {
							if (d && d.price) {
								productPrices[option.value] = d.price;
							}
						})
						.catch(e => console.log("Price load error"));
				}
			});
		}

		function updateCalculations() {
			const longueur = parseFloat(longueurEl.value) || 0;
			const largeur = parseFloat(largeurEl.value) || 0;
			const productId = fkProductEl.value;
			const productPrice = productPrices[productId] || 0;

			if (longueur > 0 && largeur > 0) {
				const surface = (longueur * largeur) / 10000; // cm² to m²
				surfaceDisplayEl.textContent = surface.toFixed(4);

				if (productPrice > 0) {
					const calculatedPrice = (surface * productPrice).toFixed(2);
					priceEl.value = calculatedPrice;
					priceFormulaEl.innerHTML = "(" + longueur.toFixed(1) + " × " + largeur.toFixed(1) + ") / 10000 × " + productPrice.toFixed(2) + " €/m² = " + calculatedPrice + " €";
				} else {
					priceFormulaEl.innerHTML = "Sélectionnez un produit avec prix";
				}
			} else {
				surfaceDisplayEl.textContent = "-";
				priceEl.value = "";
				priceFormulaEl.innerHTML = "";
			}
		}

		function updateProductInfo() {
			const productId = fkProductEl.value;
			const productPrice = productPrices[productId] || 0;
			if (productPrice > 0) {
				productPriceInfoEl.textContent = "Prix: " + productPrice.toFixed(2) + " €/m²";
			} else {
				productPriceInfoEl.textContent = "";
			}
			updateCalculations();
		}

		// Event listeners
		longueurEl.addEventListener("change", updateCalculations);
		longueurEl.addEventListener("input", updateCalculations);
		largeurEl.addEventListener("change", updateCalculations);
		largeurEl.addEventListener("input", updateCalculations);
		fkProductEl.addEventListener("change", updateProductInfo);

		// Initial load
		loadProductPrices();
	});
	</script>';
}

llxFooter();
$db->close();
