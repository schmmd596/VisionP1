<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

if (!$user->rights->pressing->read) accessforbidden();

llxHeader('', 'Entrepôts');

print load_fiche_titre('Entrepôts - Vue Pressing', '', '');

// Get all warehouses
$entrepot = new Entrepot($db);
$entrepots = $entrepot->list_array();

if (empty($entrepots)) {
	print '<p>Aucun entrepôt disponible.</p>';
} else {
	print '<table class="liste centpercent">';
	print '<tr class="liste_titre">';
	print '<td>Référence</td>';
	print '<td>Label</td>';
	print '<td>En Attente</td>';
	print '<td>En Traitement</td>';
	print '<td>Prêt</td>';
	print '<td>Livré</td>';
	print '<td>Total</td>';
	print '<td>Actions</td>';
	print '</tr>';

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

		print '<tr class="oddeven">';
		print '<td>' . $id . '</td>';
		print '<td>' . $label . '</td>';
		print '<td>' . $stats[0] . '</td>';
		print '<td>' . $stats[1] . '</td>';
		print '<td>' . $stats[2] . '</td>';
		print '<td>' . $stats[3] . '</td>';
		print '<td><b>' . $total . '</b></td>';
		print '<td><a class="button" href="view.php?id='.$id.'">Voir</a></td>';
		print '</tr>';
	}

	print '</table>';
}

llxFooter();
$db->close();
