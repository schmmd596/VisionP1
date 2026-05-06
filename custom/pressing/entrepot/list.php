<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

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
}

llxFooter();
$db->close();
