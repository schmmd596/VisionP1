<?php
/**
 * Warehouses and stock overview for pressing module
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/pressing/class/pressingcommande.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/pressing/lib/pressing.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

$langs->loadLangs(array('pressing', 'stock', 'compta'));

llxHeader();

print '<h1>'.$langs->trans('Pressing').' - '.$langs->trans('ListWarehouses').'</h1>';

$entrepot = new Entrepot($db);
$entrepots = $entrepot->list_array(0, 0, 0, 1);

if (is_array($entrepots) && !empty($entrepots)) {
	foreach ($entrepots as $entrepot_id => $entrepot_name) {
		$ent = new Entrepot($db);
		$ent->fetch($entrepot_id);

		print '<div style="margin: 30px 0; border: 1px solid #ddd; border-radius: 5px; padding: 20px;">';
		print '<h3>'.$ent->label.'</h3>';

		$sql = "SELECT pcd.rowid, pcd.description, pcd.type_article, pcd.couleur, pcd.longueur, pcd.largeur,";
		$sql .= " pcd.prix_unitaire, pcd.fk_statut, pc.ref";
		$sql .= " FROM ".MAIN_DB_PREFIX."pressing_commandedet as pcd";
		$sql .= " JOIN ".MAIN_DB_PREFIX."pressing_commande as pc ON pcd.fk_commande = pc.rowid";
		$sql .= " WHERE pcd.fk_entrepot = ".$entrepot_id;
		$sql .= " AND pcd.fk_statut != 3";
		$sql .= " ORDER BY pcd.fk_statut ASC, pc.ref ASC";

		$resql = $db->query($sql);

		if ($resql && $db->num_rows($resql) > 0) {
			$pending = array();
			$inprogress = array();
			$done = array();

			while ($obj = $db->fetch_object($resql)) {
				if ($obj->fk_statut == 0) {
					$pending[] = $obj;
				} elseif ($obj->fk_statut == 1) {
					$inprogress[] = $obj;
				} elseif ($obj->fk_statut == 2) {
					$done[] = $obj;
				}
			}

			print '<table class="tagtable centpercent" style="margin-top: 15px;">';
			print '<thead><tr style="background-color: #f8f9fa;">';
			print '<th>Commande</th>';
			print '<th>Description</th>';
			print '<th>Type</th>';
			print '<th>Couleur</th>';
			print '<th>Dimensions</th>';
			print '<th>Prix</th>';
			print '<th>Statut</th>';
			print '</tr></thead>';

			print '<tbody>';

			if (!empty($pending)) {
				print '<tr style="background-color: #f0f0f0;"><td colspan="7"><strong style="color: #999;">En attente</strong></td></tr>';
				foreach ($pending as $item) {
					print '<tr>';
					print '<td><a href="card.php?id='.$item->rowid.'">'.$item->ref.'</a></td>';
					print '<td>'.$item->description.'</td>';
					print '<td>'.$item->type_article.'</td>';
					print '<td>'.$item->couleur.'</td>';
					print '<td>'.$item->longueur.' × '.$item->largeur.' cm</td>';
					print '<td>'.$item->prix_unitaire.' €</td>';
					print '<td><span style="background-color: #999; color: white; padding: 2px 6px; border-radius: 3px;">En attente</span></td>';
					print '</tr>';
				}
			}

			if (!empty($inprogress)) {
				print '<tr style="background-color: #f0f0f0;"><td colspan="7"><strong style="color: #ff8c00;">En cours</strong></td></tr>';
				foreach ($inprogress as $item) {
					print '<tr>';
					print '<td><a href="card.php?id='.$item->rowid.'">'.$item->ref.'</a></td>';
					print '<td>'.$item->description.'</td>';
					print '<td>'.$item->type_article.'</td>';
					print '<td>'.$item->couleur.'</td>';
					print '<td>'.$item->longueur.' × '.$item->largeur.' cm</td>';
					print '<td>'.$item->prix_unitaire.' €</td>';
					print '<td><span style="background-color: #ff8c00; color: white; padding: 2px 6px; border-radius: 3px;">En cours</span></td>';
					print '</tr>';
				}
			}

			if (!empty($done)) {
				print '<tr style="background-color: #f0f0f0;"><td colspan="7"><strong style="color: #28a745;">Prêts</strong></td></tr>';
				foreach ($done as $item) {
					print '<tr>';
					print '<td><a href="card.php?id='.$item->rowid.'">'.$item->ref.'</a></td>';
					print '<td>'.$item->description.'</td>';
					print '<td>'.$item->type_article.'</td>';
					print '<td>'.$item->couleur.'</td>';
					print '<td>'.$item->longueur.' × '.$item->largeur.' cm</td>';
					print '<td>'.$item->prix_unitaire.' €</td>';
					print '<td><span style="background-color: #28a745; color: white; padding: 2px 6px; border-radius: 3px;">Prêt</span></td>';
					print '</tr>';
				}
			}

			print '</tbody>';
			print '</table>';
		} else {
			print '<p><em>Aucun article en attente ou en cours dans cet entrepôt</em></p>';
		}

		print '</div>';
	}
} else {
	print '<div class="info">';
	print $langs->trans('NoWarehouseDefined');
	print '</div>';
}

llxFooter();
$db->close();
