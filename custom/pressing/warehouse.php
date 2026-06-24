<?php
/**
 * Pressing Module - Warehouse Management
 * Gestion des entrepôts et du stock des articles
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/pressing/class/pressingcommande.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

$langs->loadLangs(array('pressing', 'bills', 'companies', 'compta', 'stocks'));

$warehouse_id = GETPOSTINT('warehouse');
$statut_filter = GETPOSTINT('statut');

// Récupérer les entrepôts disponibles
$sql_warehouses = "SELECT rowid, nom, lieu FROM ".MAIN_DB_PREFIX."entrepot WHERE statut = 1 ORDER BY nom";
$res_warehouses = $db->query($sql_warehouses);

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
.warehouse-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px; }
.warehouse-card { background: white; border: 2px solid #1e3a5f; border-radius: 8px; padding: 20px; }
.warehouse-card-title { font-size: 16px; font-weight: bold; color: #1e3a5f; margin-bottom: 15px; }
.status-group { margin: 15px 0; }
.status-group-title { font-weight: bold; color: #666; font-size: 12px; text-transform: uppercase; margin-bottom: 8px; }
.article-item { background: #f9f9f9; padding: 12px; border-left: 4px solid #1e3a5f; margin: 8px 0; border-radius: 4px; }
.article-item-text { font-weight: bold; margin-bottom: 4px; }
.article-item-details { font-size: 12px; color: #666; }
.badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
.badge-pending { background: #ccc; color: white; }
.badge-progress { background: #ff8c00; color: white; }
.badge-ready { background: #28a745; color: white; }
.badge-delivered { background: #0066cc; color: white; }
.btn { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin: 2px; }
.btn-secondary { background: #e0e0e0; color: #333; text-decoration: none; }
.btn-secondary:hover { background: #d0d0d0; }
</style>';

?>

<div class="pressing-container">
	<!-- SIDEBAR -->
	<div class="pressing-sidebar">
		<h3 style="margin-top: 0; font-size: 20px; margin-bottom: 30px;">🏭 Entrepôts</h3>

		<?php if ($res_warehouses && $db->num_rows($res_warehouses) > 0) { ?>
			<div style="font-size: 13px; margin-bottom: 20px; opacity: 0.9;">
				<strong>Sélectionner un Entrepôt:</strong>
			</div>

			<?php
			$warehouses = array();
			while ($row = $db->fetch_object($res_warehouses)) {
				$warehouses[] = $row;
				?>
				<button class="sidebar-btn sidebar-btn-<?php echo ($warehouse_id == $row->rowid) ? 'primary' : 'secondary'; ?>"
					onclick="window.location.href='warehouse.php?warehouse=<?php echo $row->rowid; ?>'">
					<?php echo $row->nom; ?>
				</button>
			<?php } ?>
		<?php } else { ?>
			<div style="font-size: 13px; color: rgba(255,255,255,0.8);">
				Aucun entrepôt disponible
			</div>
		<?php } ?>

		<hr style="border-color: rgba(255,255,255,0.2); margin: 20px 0;">

		<h4 style="margin-top: 0; font-size: 14px; opacity: 0.7; text-transform: uppercase;">FILTRES</h4>

		<div style="font-size: 12px; line-height: 1.8; opacity: 0.9;">
			<div>📊 <strong>Affichage:</strong></div>
			<div style="margin-top: 8px;">
				<a href="warehouse.php<?php echo $warehouse_id ? '?warehouse='.$warehouse_id : ''; ?>"
					style="color: white; text-decoration: <?php echo !isset($statut_filter) ? 'underline' : 'none'; ?>; opacity: <?php echo !isset($statut_filter) ? '1' : '0.7'; ?>;">
					Tous les statuts
				</a>
			</div>
			<div>
				<a href="warehouse.php?statut=0<?php echo $warehouse_id ? '&warehouse='.$warehouse_id : ''; ?>"
					style="color: white; text-decoration: <?php echo $statut_filter == 0 ? 'underline' : 'none'; ?>; opacity: <?php echo $statut_filter == 0 ? '1' : '0.7'; ?>;">
					⚪ En attente
				</a>
			</div>
			<div>
				<a href="warehouse.php?statut=1<?php echo $warehouse_id ? '&warehouse='.$warehouse_id : ''; ?>"
					style="color: white; text-decoration: <?php echo $statut_filter == 1 ? 'underline' : 'none'; ?>; opacity: <?php echo $statut_filter == 1 ? '1' : '0.7'; ?>;">
					🟠 En cours
				</a>
			</div>
			<div>
				<a href="warehouse.php?statut=2<?php echo $warehouse_id ? '&warehouse='.$warehouse_id : ''; ?>"
					style="color: white; text-decoration: <?php echo $statut_filter == 2 ? 'underline' : 'none'; ?>; opacity: <?php echo $statut_filter == 2 ? '1' : '0.7'; ?>;">
					🟢 Prêt
				</a>
			</div>
		</div>

		<hr style="border-color: rgba(255,255,255,0.2); margin: 20px 0;">

		<h4 style="margin-top: 0; font-size: 14px; opacity: 0.7; text-transform: uppercase;">ACTIONS</h4>

		<button class="sidebar-btn sidebar-btn-secondary" onclick="window.location.href='index.php'">
			📊 Tableau de Bord
		</button>

		<button class="sidebar-btn sidebar-btn-secondary" onclick="window.location.href='list.php'">
			📋 Toutes les Commandes
		</button>
	</div>

	<!-- MAIN CONTENT -->
	<div class="pressing-main">
		<h1 style="margin-top: 0; color: #1e3a5f;">🏭 Gestion des Entrepôts</h1>

		<?php if ($warehouse_id > 0) { ?>
			<!-- Vue par entrepôt sélectionné -->
			<?php
			$sql = "SELECT pcd.rowid, pcd.fk_commande, pcd.description, pcd.type_article, pcd.couleur, pcd.longueur, pcd.largeur, pcd.prix_unitaire, pcd.fk_statut, pc.ref
					FROM ".MAIN_DB_PREFIX."pressing_commandedet pcd
					LEFT JOIN ".MAIN_DB_PREFIX."pressing_commande pc ON pcd.fk_commande = pc.rowid
					WHERE pcd.fk_entrepot = ".$warehouse_id;

			if (isset($statut_filter) && $statut_filter >= 0) {
				$sql .= " AND pcd.fk_statut = ".$statut_filter;
			}

			$sql .= " ORDER BY pcd.fk_statut DESC, pcd.fk_commande";
			$res = $db->query($sql);

			$warehouse_obj = null;
			foreach ($warehouses as $w) {
				if ($w->rowid == $warehouse_id) {
					$warehouse_obj = $w;
					break;
				}
			}

			if ($warehouse_obj) {
				?>
				<div class="card">
					<div class="card-title">
						🏢 <?php echo $warehouse_obj->nom; ?>
						<?php if ($warehouse_obj->lieu) { ?>
							<span style="font-size: 14px; color: #666;">(<?php echo $warehouse_obj->lieu; ?>)</span>
						<?php } ?>
					</div>

					<?php
					if ($res && $db->num_rows($res) > 0) {
						// Grouper par statut
						$statuts = [0 => [], 1 => [], 2 => []];
						$statut_labels = ['En attente de lavage', 'En cours de traitement', 'Prêt à livrer'];
						$statut_colors = ['#ccc', '#ff8c00', '#28a745'];
						$statut_icons = ['⚪', '🟠', '🟢'];

						while ($row = $db->fetch_object($res)) {
							$statuts[$row->fk_statut][] = $row;
						}

						foreach ($statuts as $status_code => $articles) {
							if (!empty($articles)) {
								?>
								<div class="status-group">
									<div class="status-group-title">
										<?php echo $statut_icons[$status_code] ?? '⚪'; ?>
										<?php echo $statut_labels[$status_code] ?? 'Inconnu'; ?>
										(<?php echo count($articles); ?>)
									</div>

									<?php foreach ($articles as $article) { ?>
										<div class="article-item">
											<div class="article-item-text">
												<?php echo $article->description; ?>
												<span class="badge badge-<?php echo ['pending', 'progress', 'ready'][$article->fk_statut] ?? 'pending'; ?>" style="margin-left: 8px;">
													<?php echo $article->type_article; ?>
												</span>
											</div>
											<div class="article-item-details">
												<strong>Commande:</strong> <a href="order.php?id=<?php echo $article->fk_commande; ?>" style="color: #1e3a5f;">
													<?php echo $article->ref; ?>
												</a><br>
												<strong>Couleur:</strong> <?php echo $article->couleur; ?> |
												<strong>Dimensions:</strong> <?php echo $article->longueur; ?>×<?php echo $article->largeur; ?> cm |
												<strong>Prix:</strong> <?php echo $article->prix_unitaire; ?> €
											</div>
										</div>
									<?php } ?>
								</div>
								<?php
							}
						}
					} else {
						?>
						<div style="text-align: center; color: #999; padding: 30px;">
							Aucun article dans cet entrepôt
						</div>
						<?php
					}
					?>
				</div>
				<?php
			}
		} else {
			// Vue synthétique de tous les entrepôts
			?>
			<div class="warehouse-grid">
				<?php foreach ($warehouses as $warehouse) { ?>
					<?php
					$sql_count = "SELECT COUNT(*) as cnt, fk_statut FROM ".MAIN_DB_PREFIX."pressing_commandedet WHERE fk_entrepot = ".$warehouse->rowid." GROUP BY fk_statut ORDER BY fk_statut";
					$res_count = $db->query($sql_count);

					$counts = [0 => 0, 1 => 0, 2 => 0];
					$total_items = 0;
					while ($count_row = $db->fetch_object($res_count)) {
						$counts[$count_row->fk_statut] = $count_row->cnt;
						$total_items += $count_row->cnt;
					}
					?>
					<div class="warehouse-card">
						<div class="warehouse-card-title">
							🏢 <?php echo $warehouse->nom; ?>
						</div>
						<?php if ($warehouse->lieu) { ?>
							<div style="font-size: 12px; color: #666; margin-bottom: 15px;">
								📍 <?php echo $warehouse->lieu; ?>
							</div>
						<?php } ?>

						<div style="background: #f0f0f0; padding: 12px; border-radius: 5px; margin-bottom: 15px; text-align: center;">
							<div style="font-size: 24px; font-weight: bold; color: #1e3a5f;">
								<?php echo $total_items; ?>
							</div>
							<div style="font-size: 12px; color: #666;">Articles total</div>
						</div>

						<div style="font-size: 12px; line-height: 2;">
							<div style="padding: 8px; background: rgba(204,204,204,0.2); border-radius: 4px; margin-bottom: 8px;">
								⚪ En attente: <strong><?php echo $counts[0]; ?></strong>
							</div>
							<div style="padding: 8px; background: rgba(255,140,0,0.2); border-radius: 4px; margin-bottom: 8px;">
								🟠 En cours: <strong><?php echo $counts[1]; ?></strong>
							</div>
							<div style="padding: 8px; background: rgba(40,167,69,0.2); border-radius: 4px;">
								🟢 Prêt: <strong><?php echo $counts[2]; ?></strong>
							</div>
						</div>

						<a href="warehouse.php?warehouse=<?php echo $warehouse->rowid; ?>" class="btn btn-secondary" style="display: block; text-align: center; margin-top: 12px; width: 100%; box-sizing: border-box;">
							Voir Détails
						</a>
					</div>
				<?php } ?>
			</div>
			<?php
		}
		?>
	</div>
</div>

<?php
$db->close();
?>
