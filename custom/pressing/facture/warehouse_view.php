<?php
/**
 *	\file       custom/pressing/facture/warehouse_view.php
 *	\ingroup    pressing
 *	\brief      Warehouse view for pressing articles
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once '../class/pressingarticle.class.php';

$langs->load("pressing@pressing");

// Access control
if (!$user->rights->pressing->read) accessforbidden();

$fk_entrepot = GETPOST('fk_entrepot', 'int');

// View
llxHeader('', 'Vue par Entrepôt - Pressing');

$form = new Form($db);
$formproduct = new FormProduct($db);

print load_fiche_titre('Vue par Entrepôt', '', 'title_generic.png');

print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">Sélectionner un entrepôt</td>';
print '<td>';
$formproduct->selectWarehouses($fk_entrepot, 'fk_entrepot', '', 1);
print ' <input type="submit" class="button" value="Rechercher">';
print '</td></tr>';
print '</table>';
print '</form>';
print '<br>';

if ($fk_entrepot > 0) {
	$sql = "SELECT pa.rowid, pa.ref_article, pa.fk_facture, pa.fk_product, pa.status, f.ref as facture_ref";
	$sql .= " FROM " . MAIN_DB_PREFIX . "pressing_article as pa";
	$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "facture as f ON f.rowid = pa.fk_facture";
	$sql .= " WHERE pa.fk_entrepot = " . $fk_entrepot;
	$sql .= " AND pa.status != 3"; // exclude delivered
	$sql .= " ORDER BY pa.rowid DESC";

	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		$i = 0;

		print '<table class="liste centpercent">';
		print '<tr class="liste_titre">';
		print '<td>Réf Article</td>';
		print '<td>Facture</td>';
		print '<td>Statut</td>';
		print '<td>Actions</td>';
		print '</tr>';

		$article_tmp = new PressingArticle($db);
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$prod_static = new Product($db);

		// Calculate stats and store rows
		$status_counts = array(0=>0, 1=>0, 2=>0);
		$rows = array();
		while ($i < $num) {
			$obj = $db->fetch_object($resql);
			$status_counts[$obj->status]++;
			$rows[] = $obj;
			$i++;
		}
		
		// 1. Stock details for this warehouse
		print load_fiche_titre('Stock des Produits dans cet Entrepôt', '', '');
		$sql_stock = "SELECT p.ref, p.label, ps.reel FROM " . MAIN_DB_PREFIX . "product_stock as ps";
		$sql_stock .= " INNER JOIN " . MAIN_DB_PREFIX . "product as p ON p.rowid = ps.fk_product";
		$sql_stock .= " WHERE ps.fk_entrepot = " . $fk_entrepot . " AND ps.reel > 0";
		$res_stock = $db->query($sql_stock);
		
		print '<table class="liste centpercent" style="margin-bottom: 20px;">';
		print '<tr class="liste_titre">';
		print '<td>Produit/Service</td>';
		print '<td class="right">Stock Réel</td>';
		print '</tr>';
		if ($res_stock && $db->num_rows($res_stock) > 0) {
			while ($obj_st = $db->fetch_object($res_stock)) {
				print '<tr class="oddeven">';
				print '<td>' . $obj_st->ref . ' - ' . $obj_st->label . '</td>';
				print '<td class="right"><b>' . $obj_st->reel . '</b></td>';
				print '</tr>';
			}
		} else {
			print '<tr><td colspan="2" class="opacitymedium">Aucun stock disponible.</td></tr>';
		}
		print '</table>';

		// 2. Status Summary
		print load_fiche_titre('Résumé des Pièces en Entrepôt (Non livrées)', '', '');
		print '<div style="margin-bottom: 20px;">';
		print '<ul>';
		print '<li>Pièces en réception (non lavées) : <b>' . $status_counts[0] . '</b></li>';
		print '<li>Pièces en cours de lavage : <b>' . $status_counts[1] . '</b></li>';
		print '<li>Pièces prêtes à être livrées : <b>' . $status_counts[2] . '</b></li>';
		print '</ul>';
		print '</div>';

		// 3. Articles list
		print load_fiche_titre('Liste des Pièces', '', '');
		print '<table class="liste centpercent">';
		print '<tr class="liste_titre">';
		print '<td>Réf Article</td>';
		print '<td>Produit</td>';
		print '<td>Facture</td>';
		print '<td>Statut</td>';
		print '<td>Actions</td>';
		print '</tr>';

		foreach ($rows as $obj) {
			
			print '<tr class="oddeven">';
			print '<td>' . $obj->ref_article . '</td>';
			$prod_label = '';
			if ($obj->fk_product > 0) {
				$prod_static->fetch($obj->fk_product);
				$prod_label = $prod_static->ref;
			}
			print '<td>' . $prod_label . '</td>';
			print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj->fk_facture.'">' . $obj->facture_ref . '</a></td>';
			print '<td>' . $article_tmp->getStatusLabel($obj->status) . '</td>';
			print '<td><a href="article_card.php?id='.$obj->rowid.'">Gérer</a></td>';
			print '</tr>';
		}
		print '</table>';
	} else {
		dol_print_error($db);
	}
}

llxFooter();
$db->close();
