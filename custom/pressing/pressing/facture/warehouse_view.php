<?php

$res = 0;
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res && file_exists("../../../../../main.inc.php")) {
	$res = @include "../../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once dirname(__FILE__).'/../../class/pressingarticle.class.php';

$langs->load("pressing@pressing");
$langs->load("bills");

if (!$user->hasRight('pressing', 'articles', 'read')) {
	accessforbidden();
}

$sql = "SELECT DISTINCT pa.fk_entrepot, e.label ";
$sql .= "FROM " . MAIN_DB_PREFIX . "pressing_article pa ";
$sql .= "JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = pa.fk_entrepot ";
$sql .= "WHERE pa.entity = " . intval($conf->entity);
$sql .= " AND pa.fk_entrepot IS NOT NULL ";
$sql .= "ORDER BY e.label";

$warehouses = array();
$res = $db->query($sql);
if ($res) {
	while ($obj = $db->fetch_object($res)) {
		$warehouses[] = $obj;
	}
}

llxHeader('', 'Pressing par Entrepôt');

print '<div class="fichecenter">';
print load_fiche_titre('Pressing par Entrepôt', '', 'images/pressing.png');

if (empty($warehouses)) {
	print '<p class="alert alert-info">Aucun article en entrepôt</p>';
} else {
	foreach ($warehouses as $warehouse) {
		$sql = "SELECT status, COUNT(*) as count FROM " . MAIN_DB_PREFIX . "pressing_article ";
		$sql .= "WHERE fk_entrepot = " . intval($warehouse->fk_entrepot) . " AND entity = " . intval($conf->entity);
		$sql .= " GROUP BY status";

		$stats = array('waiting' => 0, 'processing' => 0, 'ready' => 0, 'delivered' => 0);
		$total = 0;

		$res = $db->query($sql);
		if ($res) {
			while ($obj = $db->fetch_object($res)) {
				$total += $obj->count;
				switch ($obj->status) {
					case 0: $stats['waiting'] = $obj->count; break;
					case 1: $stats['processing'] = $obj->count; break;
					case 2: $stats['ready'] = $obj->count; break;
					case 3: $stats['delivered'] = $obj->count; break;
				}
			}
		}

		print '<div style="margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; border-radius: 5px;">';
		print '<h3>' . htmlspecialchars($warehouse->label) . '</h3>';

		print '<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px;">';
		print '<div style="background: #FFC107; padding: 10px; text-align: center; border-radius: 3px;">';
		print '<div style="font-size: 24px; font-weight: bold;">' . intval($stats['waiting']) . '</div>';
		print '<div style="font-size: 12px;">En attente</div>';
		print '</div>';

		print '<div style="background: #2196F3; padding: 10px; text-align: center; border-radius: 3px;">';
		print '<div style="font-size: 24px; font-weight: bold; color: white;">' . intval($stats['processing']) . '</div>';
		print '<div style="font-size: 12px; color: white;">En cours</div>';
		print '</div>';

		print '<div style="background: #4CAF50; padding: 10px; text-align: center; border-radius: 3px;">';
		print '<div style="font-size: 24px; font-weight: bold; color: white;">' . intval($stats['ready']) . '</div>';
		print '<div style="font-size: 12px; color: white;">Prêt</div>';
		print '</div>';

		print '<div style="background: #888; padding: 10px; text-align: center; border-radius: 3px;">';
		print '<div style="font-size: 24px; font-weight: bold; color: white;">' . intval($total) . '</div>';
		print '<div style="font-size: 12px; color: white;">Total</div>';
		print '</div>';
		print '</div>';

		$statuses = array(0 => 'En attente', 1 => 'En cours', 2 => 'Prêt', 3 => 'Livré');
		foreach ($statuses as $status_code => $status_label) {
			$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "pressing_article ";
			$sql .= "WHERE fk_entrepot = " . intval($warehouse->fk_entrepot) . " AND status = " . intval($status_code);
			$sql .= " AND entity = " . intval($conf->entity);
			$sql .= " ORDER BY datec";

			$articles = array();
			$res = $db->query($sql);
			if ($res) {
				while ($obj = $db->fetch_object($res)) {
					$article = new PressingArticle($db);
					$article->fetch($obj->rowid);
					$articles[] = $article;
				}
			}

			if (count($articles) > 0) {
				print '<h4 style="margin-top: 15px;">' . $status_label . ' (' . count($articles) . ')</h4>';

				print '<table class="liste" style="width: 100%; margin-bottom: 15px;">';
				print '<tr class="liste_titre">';
				print '<th>Référence</th>';
				print '<th>Description</th>';
				print '<th>Prix</th>';
				print '<th>Dépôt</th>';
				print '</tr>';

				foreach ($articles as $article) {
					print '<tr>';
					print '<td><a href="article_card.php?id=' . intval($article->id) . '">' . htmlspecialchars($article->reference) . '</a></td>';
					print '<td>' . htmlspecialchars($article->description) . '</td>';
					print '<td>' . (!empty($article->price_unit) ? number_format($article->price_unit, 2) : '-') . '</td>';
					print '<td>' . dol_print_date(strtotime($article->datec), 'day') . '</td>';
					print '</tr>';
				}
				print '</table>';
			}
		}

		print '</div>';
	}
}

print '</div>';

llxFooter();
$db->close();
?>
