<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

if (!$user->rights->pressing->read) accessforbidden();

llxHeader('', 'Entrepôts');

// Include pressing stylesheet
require_once '../includes/header.php';

print '<div class="pressing-header">';
print '<h1><i class="fas fa-warehouse"></i> Entrepôts - Vue Pressing</h1>';
print '</div>';

// Get all warehouses
$entrepot = new Entrepot($db);
$entrepots = $entrepot->list_array();

if (empty($entrepots)) {
	print '<div class="pressing-info">';
	print '<i class="fas fa-info-circle"></i> Aucun entrepôt disponible.';
	print '</div>';
} else {
	print '<table class="pressing-table">';
	print '<thead><tr>';
	print '<th><i class="fas fa-barcode"></i> Référence</th>';
	print '<th><i class="fas fa-tag"></i> Label</th>';
	print '<th><i class="fas fa-hourglass-start"></i> En Attente</th>';
	print '<th><i class="fas fa-spinner"></i> En Traitement</th>';
	print '<th><i class="fas fa-check-circle"></i> Prêt</th>';
	print '<th><i class="fas fa-truck"></i> Livré</th>';
	print '<th><i class="fas fa-boxes"></i> Total</th>';
	print '<th><i class="fas fa-cog"></i> Actions</th>';
	print '</tr></thead><tbody>';

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

		print '<tr>';
		print '<td><strong>' . $id . '</strong></td>';
		print '<td>' . $label . '</td>';
		print '<td><span class="status-badge status-attente">' . $stats[0] . '</span></td>';
		print '<td><span class="status-badge status-traitement">' . $stats[1] . '</span></td>';
		print '<td><span class="status-badge status-pret">' . $stats[2] . '</span></td>';
		print '<td><span class="status-badge status-livre">' . $stats[3] . '</span></td>';
		print '<td><strong>' . $total . '</strong></td>';
		print '<td><a class="pressing-btn pressing-btn-primary" href="view.php?id='.$id.'">';
		print '<i class="fas fa-eye"></i> Voir';
		print '</a></td>';
		print '</tr>';
	}

	print '</tbody></table>';
}

llxFooter();
$db->close();
