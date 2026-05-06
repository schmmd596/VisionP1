<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once '../class/pressingarticle.class.php';
require_once '../class/pressingbonentree.class.php';

if (!$user->rights->pressing->read) accessforbidden();

$id = GETPOSTINT('id');

// Change status action
if (GETPOST('action') == 'change_status' && $user->rights->pressing->write) {
	$article_id = GETPOSTINT('article_id');
	$new_status = GETPOSTINT('new_status');

	if ($article_id > 0 && $new_status >= 0 && $new_status <= 3) {
		$article = new PressingArticle($db);
		if ($article->fetch($article_id) > 0) {
			if ($new_status > $article->status && $new_status <= 2) {
				$article->status = $new_status;
				if ($article->update($user) > 0) {
					setEventMessages('Statut mis à jour', null, 'mesgs');
				} else {
					setEventMessages('Erreur lors de la mise à jour du statut', null, 'errors');
				}
			}
		}
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
	exit;
}

$ent = new Entrepot($db);
$ent->fetch($id);

// Articles by status
$art = new PressingArticle($db);
$articles = $art->getByWarehouse($id);

$stats = array(0=>0, 1=>0, 2=>0, 3=>0);
foreach ($articles as $a) {
	$stats[$a->status]++;
}

llxHeader('', 'Entrepôt - ' . $ent->label);

print '<style>
.warehouse-header {
	background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
	color: white;
	border-radius: 12px;
	padding: 40px;
	margin-bottom: 30px;
	box-shadow: 0 8px 25px rgba(40,167,69,0.2);
}

.warehouse-header h1 {
	margin: 0;
	font-size: 32px;
	display: flex;
	align-items: center;
	gap: 15px;
}

.stats-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 15px;
	margin-bottom: 30px;
	margin-top: 20px;
}

.stat-card {
	background: white;
	padding: 20px;
	border-radius: 8px;
	text-align: center;
	box-shadow: 0 2px 10px rgba(0,0,0,0.1);
	border-left: 4px solid #28a745;
}

.stat-card-value {
	font-size: 32px;
	font-weight: 700;
	color: #28a745;
	margin-bottom: 10px;
}

.stat-card-label {
	font-size: 14px;
	color: #666;
	font-weight: 600;
}

.articles-table {
	width: 100%;
	border-collapse: collapse;
	background: white;
	border-radius: 8px;
	overflow: hidden;
	box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

.article-status-0 {
	background-color: #fff3cd;
	color: #856404;
	padding: 6px 12px;
	border-radius: 20px;
	font-weight: 600;
	display: inline-block;
}

.article-status-1 {
	background-color: #cfe2ff;
	color: #084298;
	padding: 6px 12px;
	border-radius: 20px;
	font-weight: 600;
	display: inline-block;
}

.article-status-2 {
	background-color: #d1e7dd;
	color: #0f5132;
	padding: 6px 12px;
	border-radius: 20px;
	font-weight: 600;
	display: inline-block;
}

.article-status-3 {
	background-color: #d3d3d3;
	color: #383d41;
	padding: 6px 12px;
	border-radius: 20px;
	font-weight: 600;
	display: inline-block;
}

.status-btn {
	padding: 6px 12px;
	border: none;
	border-radius: 5px;
	cursor: pointer;
	font-size: 12px;
	font-weight: 600;
	transition: all 0.2s ease;
}

.status-btn-next {
	background-color: #28a745;
	color: white;
}

.status-btn-next:hover {
	background-color: #218838;
	transform: translateY(-2px);
}

.status-btn-disabled {
	background-color: #ccc;
	color: #666;
	cursor: not-allowed;
}

.action-buttons {
	display: flex;
	gap: 8px;
	align-items: center;
}

.button-edit {
	background-color: #007bff;
	color: white;
	padding: 6px 12px;
	border-radius: 5px;
	text-decoration: none;
	font-size: 12px;
	font-weight: 600;
	transition: all 0.2s ease;
}

.button-edit:hover {
	background-color: #0056b3;
	text-decoration: none;
}

.back-link {
	color: white;
	text-decoration: none;
	font-weight: 600;
	transition: all 0.2s ease;
}

.back-link:hover {
	opacity: 0.9;
}

.empty-state {
	text-align: center;
	padding: 40px 20px;
	color: #666;
}

.empty-state-icon {
	font-size: 48px;
	margin-bottom: 20px;
	opacity: 0.5;
}
</style>';

print '<div class="warehouse-header">';
print '<h1><i class="fas fa-warehouse"></i> ' . $ent->label . '</h1>';
print '<a href="list.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour à la liste</a>';
print '</div>';

print '<div class="stats-grid">';
print '<div class="stat-card">';
print '<div class="stat-card-value" style="color: #ffc107;">' . $stats[0] . '</div>';
print '<div class="stat-card-label">En Attente</div>';
print '</div>';
print '<div class="stat-card">';
print '<div class="stat-card-value" style="color: #17a2b8;">' . $stats[1] . '</div>';
print '<div class="stat-card-label">En Traitement</div>';
print '</div>';
print '<div class="stat-card">';
print '<div class="stat-card-value" style="color: #28a745;">' . $stats[2] . '</div>';
print '<div class="stat-card-label">Prêts à Livrer</div>';
print '</div>';
print '<div class="stat-card">';
print '<div class="stat-card-value" style="color: #999;">' . $stats[3] . '</div>';
print '<div class="stat-card-label">Livrés</div>';
print '</div>';
print '</div>';

print '<h2 style="margin-bottom: 20px;"><i class="fas fa-list"></i> Articles dans cet Entrepôt</h2>';

if (!empty($articles)) {
	print '<table class="articles-table">';
	print '<thead><tr>';
	print '<th><i class="fas fa-barcode"></i> Réf</th>';
	print '<th><i class="fas fa-box"></i> Produit</th>';
	print '<th><i class="fas fa-cubes"></i> Qté</th>';
	print '<th><i class="fas fa-price-tag"></i> Prix</th>';
	print '<th><i class="fas fa-traffic-light"></i> Statut</th>';
	print '<th><i class="fas fa-cog"></i> Actions</th>';
	print '</tr></thead><tbody>';

	$prod = new Product($db);
	foreach ($articles as $article) {
		print '<tr>';
		print '<td><strong>' . $article->ref_article . '</strong></td>';

		$plabel = '';
		if ($article->fk_product > 0) {
			$prod->fetch($article->fk_product);
			$plabel = $prod->ref;
		}
		print '<td>' . $plabel . '</td>';
		print '<td>' . $article->qty . '</td>';
		print '<td>' . price($article->price) . '</td>';

		$status_label = $article->getStatusLabel();
		$status_class = 'article-status-' . $article->status;
		print '<td><span class="' . $status_class . '">' . $status_label . '</span></td>';

		print '<td><div class="action-buttons">';

		// Status change button
		if ($article->status < 2 && $user->rights->pressing->write) {
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'" style="display:inline;">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="change_status">';
			print '<input type="hidden" name="article_id" value="'.$article->id.'">';
			print '<input type="hidden" name="id" value="'.$id.'">';

			$next_status = $article->status + 1;
			$status_labels = array(1 => 'Traiter', 2 => 'Prêt');
			print '<input type="hidden" name="new_status" value="'.$next_status.'">';
			print '<button type="submit" class="status-btn status-btn-next">→ ' . $status_labels[$next_status] . '</button>';
			print '</form>';
		}

		// Edit button
		print '<a href="' . DOL_URL_ROOT . '/custom/pressing/article/card.php?id='.$article->id.'" class="button-edit">';
		print '<i class="fas fa-edit"></i> Modifier';
		print '</a>';

		// Link to reception order
		if ($article->fk_bon_entree > 0) {
			print '<a href="' . DOL_URL_ROOT . '/custom/pressing/bon_entree/card.php?id='.$article->fk_bon_entree.'" class="button-edit" style="background-color: #6c757d;">';
			print '<i class="fas fa-file-invoice"></i> Bon';
			print '</a>';
		}

		print '</div></td>';
		print '</tr>';
	}

	print '</tbody></table>';
} else {
	print '<div class="empty-state">';
	print '<div class="empty-state-icon"><i class="fas fa-inbox"></i></div>';
	print '<p>Aucun article dans cet entrepôt</p>';
	print '</div>';
}

llxFooter();
$db->close();
