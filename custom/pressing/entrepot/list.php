<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once '../class/pressingarticle.class.php';
require_once '../class/pressingbonentree.class.php';

// Transfer article action
if (GETPOST('action') == 'transfer_article' && $user->rights->pressing->write) {
	$article_id = GETPOSTINT('article_id');
	$dest_entrepot = GETPOSTINT('dest_entrepot');

	if ($article_id > 0 && $dest_entrepot > 0) {
		$article = new PressingArticle($db);
		if ($article->fetch($article_id) > 0 && $article->fk_entrepot != $dest_entrepot) {
			require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
			$db->begin();
			
			$mouvstock = new MouvementStock($db);
			$mouvstock->fk_product = $article->fk_product;
			
			$mouvstock->fk_entrepot = $article->fk_entrepot;
			$mouvstock->label = "Transfert sortant - " . $article->ref_article;
			$res1 = $mouvstock->_create($user, $article->fk_product, $article->fk_entrepot, -$article->qty, 2, 0, $mouvstock->label);
			
			$mouvstock->fk_entrepot = $dest_entrepot;
			$mouvstock->label = "Transfert entrant - " . $article->ref_article;
			$res2 = $mouvstock->_create($user, $article->fk_product, $dest_entrepot, $article->qty, 3, 0, $mouvstock->label);
			
			if ($res1 >= 0 && $res2 >= 0) {
				$article->fk_entrepot = $dest_entrepot;
				if ($article->update($user) > 0) {
					$db->commit();
					setEventMessages('Article transféré avec succès', null, 'mesgs');
				} else {
					$db->rollback();
					setEventMessages('Erreur lors de la mise à jour de l\'article', null, 'errors');
				}
			} else {
				$db->rollback();
				setEventMessages('Erreur lors du transfert de stock', null, 'errors');
			}
		}
	}
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}

if (!$user->rights->pressing->read) accessforbidden();

llxHeader('', 'Entrepôts');

// Include pressing stylesheet
require_once '../includes/header.php';

print '<style>
.warehouse-list-header {
	background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
	color: white;
	border-radius: 12px;
	padding: 40px;
	margin-bottom: 30px;
	box-shadow: 0 8px 25px rgba(40,167,69,0.2);
}

.warehouse-list-header h1 {
	margin: 0;
	font-size: 32px;
	display: flex;
	align-items: center;
	gap: 15px;
}

.warehouse-cards {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	gap: 20px;
	margin-bottom: 30px;
}

.warehouse-card {
	background: white;
	border-radius: 12px;
	padding: 25px;
	box-shadow: 0 4px 15px rgba(0,0,0,0.1);
	transition: all 0.3s ease;
	border-top: 4px solid #28a745;
}

.warehouse-card:hover {
	transform: translateY(-5px);
	box-shadow: 0 8px 25px rgba(40,167,69,0.2);
}

.warehouse-card-title {
	font-size: 18px;
	font-weight: 700;
	color: #333;
	margin-bottom: 20px;
}

.warehouse-stats {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 12px;
	margin-bottom: 20px;
}

.warehouse-stat {
	background-color: #f8f9fa;
	padding: 12px;
	border-radius: 6px;
	text-align: center;
}

.warehouse-stat-label {
	font-size: 11px;
	color: #666;
	text-transform: uppercase;
	font-weight: 600;
	margin-bottom: 5px;
}

.warehouse-stat-value {
	font-size: 24px;
	font-weight: 700;
	color: #28a745;
}

.warehouse-card-total {
	padding-top: 15px;
	border-top: 1px solid #eee;
	text-align: center;
	margin-bottom: 15px;
}

.warehouse-card-total-label {
	font-size: 12px;
	color: #666;
	text-transform: uppercase;
	font-weight: 600;
}

.warehouse-card-total-value {
	font-size: 28px;
	font-weight: 700;
	color: #28a745;
}

.warehouse-card-button {
	width: 100%;
	padding: 12px;
	background-color: #28a745;
	color: white;
	border: none;
	border-radius: 6px;
	font-weight: 600;
	cursor: pointer;
	transition: all 0.2s ease;
	text-decoration: none;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
}

.warehouse-card-button:hover {
	background-color: #218838;
	transform: translateY(-2px);
}

.empty-state {
	text-align: center;
	padding: 60px 20px;
	color: #666;
}

.empty-state-icon {
	font-size: 64px;
	margin-bottom: 20px;
	opacity: 0.5;
	color: #999;
}

.empty-state-text {
	font-size: 18px;
	font-weight: 600;
}

.articles-table {
	width: 100%;
	border-collapse: collapse;
	background: white;
	border-radius: 8px;
	overflow: hidden;
	box-shadow: 0 2px 10px rgba(0,0,0,0.1);
	margin-top: 20px;
}
.articles-table thead {
	background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
	color: white;
}
.articles-table th {
	padding: 15px;
	text-align: left;
	font-weight: 600;
}
.articles-table td {
	padding: 15px;
	border-bottom: 1px solid #eee;
}
.articles-table tbody tr:hover {
	background-color: #f8f9fa;
}

.article-status-0 { background-color: #fff3cd; color: #856404; padding: 6px 12px; border-radius: 20px; font-weight: 600; display: inline-block; }
.article-status-1 { background-color: #cfe2ff; color: #084298; padding: 6px 12px; border-radius: 20px; font-weight: 600; display: inline-block; }
.article-status-2 { background-color: #d1e7dd; color: #0f5132; padding: 6px 12px; border-radius: 20px; font-weight: 600; display: inline-block; }
.article-status-3 { background-color: #d3d3d3; color: #383d41; padding: 6px 12px; border-radius: 20px; font-weight: 600; display: inline-block; }

.transfer-form { display: inline-flex; gap: 5px; align-items: center; }
.transfer-select { padding: 4px; border: 1px solid #ddd; border-radius: 4px; font-size: 11px; max-width: 120px; }
.transfer-btn { background-color: #fd7e14; color: white; border: none; padding: 5px 8px; border-radius: 4px; cursor: pointer; font-size: 11px; }
.transfer-btn:hover { background-color: #e86e04; }
.button-edit { background-color: #007bff; color: white; padding: 6px 12px; border-radius: 5px; text-decoration: none; font-size: 12px; font-weight: 600; }
.button-edit:hover { background-color: #0056b3; }

</style>';

print '<div class="warehouse-list-header">';
print '<h1><i class="fas fa-warehouse"></i> Gestion des Entrepôts</h1>';
print '</div>';

// Get all warehouses
$entrepot = new Entrepot($db);
$entrepots = $entrepot->list_array();

if (empty($entrepots)) {
	print '<div class="empty-state">';
	print '<div class="empty-state-icon"><i class="fas fa-inbox"></i></div>';
	print '<div class="empty-state-text">Aucun entrepôt disponible</div>';
	print '</div>';
} else {
	print '<div class="warehouse-cards">';

	foreach ($entrepots as $id => $label) {
		// Count articles by status for this warehouse
		$sql_count = "SELECT status, COUNT(*) as cnt FROM " . MAIN_DB_PREFIX . "pressing_article WHERE fk_entrepot = " . intval($id) . " GROUP BY status";
		$resql_count = $db->query($sql_count);

		$stats = array(0=>0, 1=>0, 2=>0, 3=>0);
		if ($resql_count) {
			while ($obj_count = $db->fetch_object($resql_count)) {
				$stats[$obj_count->status] = $obj_count->cnt;
			}
		}

		$total = array_sum($stats);

		print '<div class="warehouse-card">';
		print '<div class="warehouse-card-title">';
		print '<i class="fas fa-building"></i> ' . $label;
		print '</div>';

		print '<div class="warehouse-stats">';
		print '<div class="warehouse-stat">';
		print '<div class="warehouse-stat-label">En Attente</div>';
		print '<div class="warehouse-stat-value" style="color: #ffc107;">' . $stats[0] . '</div>';
		print '</div>';

		print '<div class="warehouse-stat">';
		print '<div class="warehouse-stat-label">En Traitement</div>';
		print '<div class="warehouse-stat-value" style="color: #17a2b8;">' . $stats[1] . '</div>';
		print '</div>';

		print '<div class="warehouse-stat">';
		print '<div class="warehouse-stat-label">Prêt</div>';
		print '<div class="warehouse-stat-value" style="color: #28a745;">' . $stats[2] . '</div>';
		print '</div>';

		print '<div class="warehouse-stat">';
		print '<div class="warehouse-stat-label">Livré</div>';
		print '<div class="warehouse-stat-value" style="color: #999;">' . $stats[3] . '</div>';
		print '</div>';
		print '</div>';

		print '<div class="warehouse-card-total">';
		print '<div class="warehouse-card-total-label">Total Articles</div>';
		print '<div class="warehouse-card-total-value">' . $total . '</div>';
		print '</div>';

		print '<a href="view.php?id='.$id.'" class="warehouse-card-button">';
		print '<i class="fas fa-eye"></i> Voir Détails';
		print '</a>';

		print '</div>';
	}

	print '</div>';

	// ============================================
	// Global list of articles across all warehouses
	// ============================================
	print '<h2 style="margin-bottom: 20px; margin-top: 40px;"><i class="fas fa-list"></i> Tous les Articles en Stock</h2>';

	$sql_art = "SELECT rowid FROM " . MAIN_DB_PREFIX . "pressing_article ORDER BY date_reception DESC";
	$resql_art = $db->query($sql_art);
	if ($resql_art && $db->num_rows($resql_art) > 0) {
		print '<table class="articles-table">';
		print '<thead><tr>';
		print '<th><i class="fas fa-barcode"></i> Réf</th>';
		print '<th><i class="fas fa-warehouse"></i> Entrepôt</th>';
		print '<th><i class="fas fa-user"></i> Client</th>';
		print '<th><i class="fas fa-file-invoice"></i> Réf Facture</th>';
		print '<th><i class="fas fa-box"></i> Produit</th>';
		print '<th><i class="fas fa-cubes"></i> Qté</th>';
		print '<th><i class="fas fa-traffic-light"></i> Statut</th>';
		print '<th><i class="fas fa-exchange-alt"></i> Transférer</th>';
		print '</tr></thead><tbody>';

		$prod = new Product($db);
		$soc = new Societe($db);
		$fact = new Facture($db);
		$ent_cache = array(); // Cache for warehouse names

		while ($obj_art = $db->fetch_object($resql_art)) {
			$article = new PressingArticle($db);
			$article->fetch($obj_art->rowid);

			print '<tr>';
			print '<td><strong>' . $article->ref_article . '</strong></td>';

			// Warehouse
			$elabel = '';
			if ($article->fk_entrepot > 0) {
				if (!isset($ent_cache[$article->fk_entrepot])) {
					$tmp_ent = new Entrepot($db);
					$tmp_ent->fetch($article->fk_entrepot);
					$ent_cache[$article->fk_entrepot] = $tmp_ent->label;
				}
				$elabel = $ent_cache[$article->fk_entrepot];
			}
			print '<td><span style="font-weight:600; color:#28a745;">' . $elabel . '</span></td>';

			// Client
			$client_name = '';
			if ($article->fk_bon_entree > 0) {
				$bon = new PressingBonEntree($db);
				if ($bon->fetch($article->fk_bon_entree) > 0 && $bon->fk_soc > 0) {
					if ($soc->fetch($bon->fk_soc) > 0) {
						$client_name = $soc->name;
					}
				}
			}
			print '<td>' . $client_name . '</td>';

			// Facture
			$facture_ref = '';
			if ($article->fk_facture > 0) {
				if ($fact->fetch($article->fk_facture) > 0) {
					$facture_ref = '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?id='.$article->fk_facture.'">'.$fact->ref.'</a>';
				}
			}
			print '<td>' . $facture_ref . '</td>';

			// Produit
			$plabel = '';
			if ($article->fk_product > 0) {
				$prod->fetch($article->fk_product);
				$plabel = $prod->ref;
			}
			print '<td>' . $plabel . '</td>';
			
			// Qty
			print '<td>' . $article->qty . '</td>';

			// Status
			$status_label = $article->getStatusLabel();
			$status_class = 'article-status-' . $article->status;
			print '<td><span class="' . $status_class . '">' . $status_label . '</span></td>';

			// Transfer Form
			print '<td>';
			if ($article->status < 3 && $user->rights->pressing->write) {
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" class="transfer-form">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="transfer_article">';
				print '<input type="hidden" name="article_id" value="'.$article->id.'">';
				print '<select name="dest_entrepot" class="transfer-select" required>';
				print '<option value="">Transférer vers...</option>';
				foreach ($entrepots as $eid => $elabel_opt) {
					if ($eid != $article->fk_entrepot) {
						print '<option value="'.$eid.'">'.$elabel_opt.'</option>';
					}
				}
				print '</select>';
				print '<button type="submit" class="transfer-btn" title="Transférer"><i class="fas fa-exchange-alt"></i></button>';
				print '</form>';
			}
			print '</td>';

			print '</tr>';
		}
		print '</tbody></table>';
	} else {
		print '<div class="empty-state">';
		print '<div class="empty-state-icon"><i class="fas fa-inbox"></i></div>';
		print '<p>Aucun article en stock</p>';
		print '</div>';
	}

}

llxFooter();
$db->close();
