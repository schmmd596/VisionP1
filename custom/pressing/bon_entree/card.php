<?php
/**
 *	\file       custom/pressing/bon_entree/card.php
 *	\ingroup    pressing
 *	\brief      Card for pressing reception order
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/client.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once '../class/pressingbonentree.class.php';
require_once '../class/pressingarticle.class.php';
require_once '../lib/pressing.lib.php';

$langs->load("pressing@pressing");
$langs->load("bills");
$langs->load("companies");

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

if (!$user->rights->pressing->read) accessforbidden();

$bon = new PressingBonEntree($db);
if ($id > 0) {
	$bon->fetch($id);
}

// Actions
if ($action == 'create' && $_SERVER['REQUEST_METHOD'] == 'POST' && $user->rights->pressing->write) {
	$bon->fk_soc = GETPOSTINT('fk_soc');

	// Check that a client is selected
	if (empty($bon->fk_soc)) {
		setEventMessages('Veuillez sélectionner un client', null, 'errors');
	} else {
		$bon->entity = $conf->entity;
		$res = $bon->create($user);
		if ($res > 0) {
			setEventMessages('Bon d\'entrée créé avec succès', null, 'mesgs');
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$res);
			exit;
		} else {
			setEventMessages('Erreur lors de la création du bon: ' . $bon->error, null, 'errors');
		}
	}
} elseif ($action == 'create' && $_SERVER['REQUEST_METHOD'] == 'POST' && !$user->rights->pressing->write) {
	setEventMessages('Vous n\'avez pas les permissions pour créer un bon', null, 'errors');
}

if ($action == 'add_article' && $user->rights->pressing->write && $id > 0) {
	$article = new PressingArticle($db);
	$article->fk_bon_entree = $id;
	$article->fk_product = GETPOSTINT('fk_product');
	$article->ref_article = GETPOST('ref_article', 'alpha');
	$article->fk_entrepot = GETPOSTINT('fk_entrepot');
	$article->longueur = GETPOST('longueur', 'float');
	$article->largeur = GETPOST('largeur', 'float');
	$article->price = GETPOST('price', 'float');

	if ($article->longueur > 0 && $article->largeur > 0) {
		$article->surface = $article->calculateSurface($article->longueur, $article->largeur);
	}

	if (empty($article->price) && $article->fk_product > 0 && $article->surface > 0) {
		$prod = new Product($db);
		$prod->fetch($article->fk_product);
		$article->price = round($article->surface * $prod->price, 2);
	}

	$res = $article->create($user);
	if ($res > 0) {
		// Create stock movement (reception)
		pressing_reception_article($db, $article, $user);
		setEventMessages('Article ajouté', null, 'mesgs');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
		exit;
	} else {
		setEventMessages($article->error, null, 'errors');
	}
}

// Deliver action
if ($action == 'deliver' && $user->rights->pressing->deliver && $id > 0) {
	if ($bon->canDeliver()) {
		$result = pressing_deliver_bon($db, $bon, $user);
		if ($result >= 0) {
			setEventMessages('Bon livré avec succès', null, 'mesgs');
			$bon->fetch($id);
		} else {
			setEventMessages('Erreur lors de la livraison', null, 'errors');
		}
	} else {
		setEventMessages('Tous les articles ne sont pas prêts à livrer', null, 'errors');
	}
}

// Delete action
if ($action == 'delete' && $user->rights->pressing->delete && $id > 0) {
	require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';

	$db->begin();

	// Get all articles
	$articles = $bon->getArticles();

	// Delete stock movements for each article (reverse reception)
	foreach ($articles as $art) {
		if ($art->fk_entrepot > 0 && $art->fk_product > 0) {
			$mouvstock = new MouvementStock($db);
			$mouvstock->fk_product = $art->fk_product;
			$mouvstock->fk_entrepot = $art->fk_entrepot;
			$mouvstock->label = "Suppression pressing - " . $art->ref_article;
			// Type 3 = Stock increase reverse (return)
			$result = $mouvstock->_create($user, $art->fk_product, $art->fk_entrepot, -1, 3, 0, $mouvstock->label);
			if ($result < 0) {
				$db->rollback();
				setEventMessages('Erreur lors de la suppression des mouvements de stock', null, 'errors');
				header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
				exit;
			}
		}
	}

	// Delete all articles
	foreach ($articles as $art) {
		$sql = "DELETE FROM " . MAIN_DB_PREFIX . "pressing_article WHERE rowid = " . (int) $art->id;
		$resql = $db->query($sql);
		if (!$resql) {
			$db->rollback();
			setEventMessages('Erreur lors de la suppression des articles', null, 'errors');
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
			exit;
		}
	}

	// Delete bon
	$sql = "DELETE FROM " . MAIN_DB_PREFIX . "pressing_bon_entree WHERE rowid = " . (int) $id;
	$resql = $db->query($sql);
	if ($resql) {
		$db->commit();
		setEventMessages('Bon d\'entrée supprimé avec succès', null, 'mesgs');
		header("Location: list.php");
		exit;
	} else {
		$db->rollback();
		setEventMessages('Erreur lors de la suppression du bon', null, 'errors');
	}
}

// Get articles
$articles = $bon->getArticles();
$stats = $bon->getArticleStats();

// View
llxHeader('', 'Bon d\'Entrée Pressing');

$form = new Form($db);
$formproduct = new FormProduct($db);

if (!$id) {
	// Create form
	print load_fiche_titre('Créer un bon d\'entrée', '', '');
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="create">';

	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">Client</td><td>';

	// Get list of companies
	$sql = "SELECT rowid, nom as name FROM " . MAIN_DB_PREFIX . "societe WHERE client = 1 ORDER BY nom";
	$resql = $db->query($sql);
	$companies = array();
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$companies[$obj->rowid] = $obj->name;
		}
	}
	print $form->selectarray('fk_soc', $companies, '', 1, 0);
	print '</td></tr>';
	print '</table>';

	print '<div class="center"><input type="submit" class="button" value="Créer"></div>';
	print '</form>';
} else {
	// Display bon
	print load_fiche_titre('Bon d\'Entrée: ' . $bon->ref, '', '');

	$soc = new Societe($db);
	$soc->fetch($bon->fk_soc);

	print '<div class="fichecenter">';
	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">Référence</td><td>' . $bon->ref . '</td></tr>';
	print '<tr><td>Client</td><td>' . $soc->name . '</td></tr>';
	print '<tr><td>Date entrée</td><td>' . dol_print_date($bon->date_entree, 'day') . '</td></tr>';
	print '<tr><td>Statut</td><td>' . $bon->getStatusLabel() . '</td></tr>';
	print '</table>';
	print '</div>';

	// Buttons
	print '<div class="tabsAction">';

	// Button Deliver
	if ($bon->status < 2) {
		if ($bon->canDeliver()) {
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="deliver">';
			print '<input type="hidden" name="id" value="'.$id.'">';
			print '<input type="submit" class="butAction" value="Livrer (créer facture)">';
			print '</form>';
		} else {
			print '<a class="butActionRefused" href="#">Livrer (tous articles doivent être prêts)</a>';
		}
	}

	// Button Delete (always available except if delivered)
	if ($bon->status < 2 && $user->rights->pressing->delete) {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;" onsubmit="return confirm(\'Êtes-vous sûr de vouloir supprimer ce bon et tous ses articles ?\');">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="delete">';
		print '<input type="hidden" name="id" value="'.$id.'">';
		print '<input type="submit" class="butActionDelete" value="Supprimer le bon">';
		print '</form>';
	}

	print '</div>';

	// Status summary
	print '<div style="margin-bottom: 20px;">';
	print '<b>Résumé articles:</b><br>';
	print '<ul>';
	print '<li>En attente: ' . $stats[0] . '</li>';
	print '<li>En traitement: ' . $stats[1] . '</li>';
	print '<li>Prêts à livrer: ' . $stats[2] . '</li>';
	print '<li>Livrés: ' . $stats[3] . '</li>';
	print '</ul>';
	print '</div>';

	// Articles list
	print load_fiche_titre('Articles du bon', '', '');
	print '<table class="liste centpercent">';
	print '<tr class="liste_titre">';
	print '<td>Réf</td>';
	print '<td>Produit</td>';
	print '<td>Entrepôt</td>';
	print '<td>Dimensions</td>';
	print '<td>Prix</td>';
	print '<td>Statut</td>';
	print '<td>Actions</td>';
	print '</tr>';

	if (!empty($articles)) {
		$prod = new Product($db);
		$ent = new Entrepot($db);
		foreach ($articles as $art) {
			print '<tr class="oddeven">';
			print '<td>' . $art->ref_article . '</td>';

			$plabel = '';
			if ($art->fk_product > 0) {
				$prod->fetch($art->fk_product);
				$plabel = $prod->ref;
			}
			print '<td>' . $plabel . '</td>';

			$elabel = '';
			if ($art->fk_entrepot > 0) {
				$ent->fetch($art->fk_entrepot);
				$elabel = $ent->label;
			}
			print '<td>' . $elabel . '</td>';

			print '<td>' . (empty($art->longueur) ? '' : ($art->longueur . 'x' . $art->largeur . ' cm')) . '</td>';
			print '<td>' . number_format($art->price, 2) . ' €</td>';
			print '<td>' . $art->getStatusLabel() . '</td>';
			print '<td><a href="' . DOL_URL_ROOT . '/custom/pressing/article/card.php?id='.$art->id.'">Modifier</a></td>';
			print '</tr>';
		}
	} else {
		print '<tr><td colspan="7" class="opacitymedium">Aucun article.</td></tr>';
	}
	print '</table>';

	// Add article form
	if ($user->rights->pressing->write) {
		print '<br>';
		print load_fiche_titre('Ajouter un article', '', '');
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'" id="form_add_article">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="add_article">';

		print '<table class="border centpercent">';
		print '<tr><td class="titlefield">Réf Article</td><td><input type="text" name="ref_article" required></td></tr>';
		print '<tr><td>Produit</td><td>';
		$form->select_produits('', 'fk_product', '', 0, 0, -1, 2, '', 1, array(), 0, '1', 0, '', 0, '1');
		print '</td></tr>';
		print '<tr><td>Longueur (cm)</td><td><input type="number" name="longueur" id="longueur" step="0.1" min="0"></td></tr>';
		print '<tr><td>Largeur (cm)</td><td><input type="number" name="largeur" id="largeur" step="0.1" min="0"></td></tr>';
		print '<tr><td>Surface (m²)</td><td><span id="surface_display">-</span> m²</td></tr>';
		print '<tr><td>Prix (€)</td><td><input type="number" name="price" id="price" step="0.01" min="0"></td></tr>';
		print '<tr><td>Entrepôt</td><td>';
		$formproduct->selectWarehouses('', 'fk_entrepot', '', 1);
		print '</td></tr>';
		print '</table>';

		print '<div class="center"><input type="submit" class="button" value="Ajouter"></div>';
		print '</form>';

		// JavaScript for surface calculation
		print '<script type="text/javascript">
		document.getElementById("longueur").addEventListener("input", function() {
			updateSurface();
		});
		document.getElementById("largeur").addEventListener("input", function() {
			updateSurface();
		});
		function updateSurface() {
			const l = parseFloat(document.getElementById("longueur").value) || 0;
			const lg = parseFloat(document.getElementById("largeur").value) || 0;
			const surface = (l > 0 && lg > 0) ? ((l * lg) / 10000).toFixed(4) : "-";
			document.getElementById("surface_display").textContent = surface;
		}
		</script>';
	}
}

llxFooter();
$db->close();
