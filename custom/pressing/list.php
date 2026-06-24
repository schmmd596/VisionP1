<?php
/**
 * Pressing Module - All Orders List
 * Gestion complète de la liste des commandes
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/pressing/class/pressingcommande.class.php';

$langs->loadLangs(array('pressing', 'bills', 'companies', 'compta'));

$statut = GETPOSTINT('statut');
$page = GETPOSTINT('page') ? GETPOSTINT('page') : 0;
$limit = 25;
$offset = $limit * $page;

$sql = "SELECT pc.rowid, pc.ref, pc.fk_soc, pc.fk_statut, pc.date_depot, s.nom as societe";
$sql .= " FROM ".MAIN_DB_PREFIX."pressing_commande pc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON pc.fk_soc = s.rowid";
$sql .= " WHERE 1 = 1";

if ($statut >= 0) {
	$sql .= " AND pc.fk_statut = ".$statut;
}

$sql .= " ORDER BY pc.datec DESC";
$sql_count = str_replace('SELECT pc.rowid, pc.ref, pc.fk_soc, pc.fk_statut, pc.date_depot, s.nom as societe', 'SELECT COUNT(*) as cnt', $sql);

$res_count = $db->query($sql_count);
$count_obj = $db->fetch_object($res_count);
$total_records = $count_obj->cnt ?? 0;
$total_pages = ceil($total_records / $limit);

$sql .= " LIMIT ".$limit." OFFSET ".$offset;
$resql = $db->query($sql);

print '<style>
body { background-color: #f5f6fa; }
.pressing-container { display: flex; min-height: 100vh; }
.pressing-main { flex: 1; padding: 30px; background: white; margin: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.pressing-sidebar { width: 280px; background: linear-gradient(135deg, #1e3a5f 0%, #2c5aa0 100%); color: white; padding: 30px 20px; margin: 20px 20px 20px 0; border-radius: 8px; }
.sidebar-btn { width: 100%; padding: 12px; margin: 8px 0; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: bold; transition: all 0.3s; }
.sidebar-btn-primary { background: white; color: #1e3a5f; }
.sidebar-btn-primary:hover { background: #f0f0f0; }
.sidebar-btn-secondary { background: rgba(255,255,255,0.2); color: white; border: 1px solid white; }
.sidebar-btn-secondary:hover { background: rgba(255,255,255,0.3); }
.card { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin: 15px 0; }
.card-title { font-size: 18px; font-weight: bold; color: #1e3a5f; margin-bottom: 15px; }
.badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
.badge-pending { background: #ccc; color: white; }
.badge-progress { background: #ff8c00; color: white; }
.badge-ready { background: #28a745; color: white; }
.badge-delivered { background: #0066cc; color: white; }
.table-orders { width: 100%; border-collapse: collapse; }
.table-orders th { background: #f0f0f0; padding: 12px; text-align: left; border-bottom: 2px solid #1e3a5f; }
.table-orders td { padding: 12px; border-bottom: 1px solid #ddd; }
.table-orders tr:hover { background: #f9f9f9; }
.btn { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin: 2px; }
.btn-primary { background: #1e3a5f; color: white; text-decoration: none; }
.btn-primary:hover { background: #2c5aa0; }
.btn-secondary { background: #e0e0e0; color: #333; text-decoration: none; }
.filter-group { margin: 15px 0; }
.filter-group label { display: block; font-weight: bold; margin-bottom: 5px; }
.pagination { text-align: center; margin-top: 20px; }
.pagination a, .pagination span { padding: 8px 12px; margin: 0 2px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; }
.pagination a:hover { background: #f0f0f0; }
.pagination .current { background: #1e3a5f; color: white; border-color: #1e3a5f; }
</style>';

?>

<div class="pressing-container">
	<!-- SIDEBAR -->
	<div class="pressing-sidebar">
		<h3 style="margin-top: 0; font-size: 20px; margin-bottom: 30px;">📋 Filtres</h3>

		<div class="filter-group">
			<label>Filtrer par Statut</label>
			<div style="display: flex; flex-direction: column; gap: 8px;">
				<a href="list.php" class="btn sidebar-btn-<?php echo !isset($statut) || $statut < 0 ? 'primary' : 'secondary'; ?>" style="text-align: center; text-decoration: none;">
					Tous les statuts
				</a>
				<a href="list.php?statut=0" class="btn sidebar-btn-<?php echo $statut == 0 ? 'primary' : 'secondary'; ?>" style="text-align: center; text-decoration: none;">
					⚪ En attente
				</a>
				<a href="list.php?statut=1" class="btn sidebar-btn-<?php echo $statut == 1 ? 'primary' : 'secondary'; ?>" style="text-align: center; text-decoration: none;">
					🟠 En cours
				</a>
				<a href="list.php?statut=2" class="btn sidebar-btn-<?php echo $statut == 2 ? 'primary' : 'secondary'; ?>" style="text-align: center; text-decoration: none;">
					🟢 Prêt
				</a>
				<a href="list.php?statut=3" class="btn sidebar-btn-<?php echo $statut == 3 ? 'primary' : 'secondary'; ?>" style="text-align: center; text-decoration: none;">
					🔵 Livré
				</a>
			</div>
		</div>

		<hr style="border-color: rgba(255,255,255,0.2); margin: 20px 0;">

		<h4 style="margin-top: 0; font-size: 14px; opacity: 0.7; text-transform: uppercase;">ACTIONS</h4>

		<button class="sidebar-btn sidebar-btn-primary" onclick="window.location.href='order.php?action=create'">
			➕ Nouvelle Commande
		</button>

		<button class="sidebar-btn sidebar-btn-secondary" onclick="window.location.href='index.php'">
			📊 Tableau de Bord
		</button>

		<button class="sidebar-btn sidebar-btn-secondary" onclick="window.location.href='warehouse.php'">
			🏭 Entrepôts
		</button>

		<hr style="border-color: rgba(255,255,255,0.2); margin: 20px 0;">

		<div style="font-size: 12px; opacity: 0.8; line-height: 1.6;">
			<strong>Total:</strong> <?php echo $total_records; ?> commandes
		</div>
	</div>

	<!-- MAIN CONTENT -->
	<div class="pressing-main">
		<h1 style="margin-top: 0; color: #1e3a5f;">📦 Toutes les Commandes</h1>

		<div class="card">
			<div class="card-title">Liste complète</div>

			<?php if ($resql && $db->num_rows($resql) > 0) { ?>
				<table class="table-orders">
					<thead>
						<tr>
							<th>Référence</th>
							<th>Client</th>
							<th>Date de Dépôt</th>
							<th>Statut</th>
							<th style="text-align: center;">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php
						while ($row = $db->fetch_object($resql)) {
							$statut_labels = ['En attente', 'En cours', 'Prêt', 'Livré'];
							$statut_badges = ['badge-pending', 'badge-progress', 'badge-ready', 'badge-delivered'];
							$statut_icons = ['⚪', '🟠', '🟢', '🔵'];
							?>
							<tr>
								<td>
									<strong><a href="order.php?id=<?php echo $row->rowid; ?>" style="color: #1e3a5f; text-decoration: none;">
										<?php echo $row->ref; ?>
									</a></strong>
								</td>
								<td><?php echo $row->societe ?: 'N/A'; ?></td>
								<td><?php echo dol_print_date($db->jdate($row->date_depot), 'day'); ?></td>
								<td>
									<span class="badge <?php echo $statut_badges[$row->fk_statut] ?? 'badge-pending'; ?>">
										<?php echo $statut_icons[$row->fk_statut] ?? '⚪'; ?>
										<?php echo $statut_labels[$row->fk_statut] ?? 'Inconnu'; ?>
									</span>
								</td>
								<td style="text-align: center;">
									<a href="order.php?id=<?php echo $row->rowid; ?>" class="btn btn-secondary">Voir</a>
								</td>
							</tr>
						<?php } ?>
					</tbody>
				</table>

				<?php if ($total_pages > 1) { ?>
					<div class="pagination">
						<?php if ($page > 0) { ?>
							<a href="list.php?page=0<?php echo $statut >= 0 ? '&statut='.$statut : ''; ?>">« Première</a>
							<a href="list.php?page=<?php echo $page - 1; ?><?php echo $statut >= 0 ? '&statut='.$statut : ''; ?>">‹ Précédent</a>
						<?php } ?>

						<?php for ($i = 0; $i < $total_pages; $i++) { ?>
							<?php if ($i == $page) { ?>
								<span class="current"><?php echo $i + 1; ?></span>
							<?php } else { ?>
								<a href="list.php?page=<?php echo $i; ?><?php echo $statut >= 0 ? '&statut='.$statut : ''; ?>"><?php echo $i + 1; ?></a>
							<?php } ?>
						<?php } ?>

						<?php if ($page < $total_pages - 1) { ?>
							<a href="list.php?page=<?php echo $page + 1; ?><?php echo $statut >= 0 ? '&statut='.$statut : ''; ?>">Suivant ›</a>
							<a href="list.php?page=<?php echo $total_pages - 1; ?><?php echo $statut >= 0 ? '&statut='.$statut : ''; ?>">Dernière »</a>
						<?php } ?>
					</div>
				<?php } ?>
			<?php } else { ?>
				<div style="text-align: center; color: #999; padding: 40px;">
					<p style="font-size: 18px;">Aucune commande pour le moment</p>
					<a href="order.php?action=create" class="btn btn-primary" style="margin-top: 15px;">➕ Créer une commande</a>
				</div>
			<?php } ?>
		</div>
	</div>
</div>

<?php
$db->close();
?>
