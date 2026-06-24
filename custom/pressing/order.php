<?php
/**
 * Pressing Module - Order Management
 * Fiche complète de gestion d'une commande
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/pressing/class/pressingcommande.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

$langs->loadLangs(array('pressing', 'bills', 'companies', 'compta'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

if (!isModEnabled('pressing')) {
	http_response_code(403);
	exit('Module not enabled');
}

llxHeader();

$order = new PressingCommande($db);

if ($id > 0) {
	$order->fetch($id);
}

// CSS
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
.form-group { margin: 15px 0; }
.form-label { display: block; font-weight: bold; margin-bottom: 5px; color: #333; }
.form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
.badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
.badge-pending { background: #ccc; color: white; }
.badge-progress { background: #ff8c00; color: white; }
.badge-ready { background: #28a745; color: white; }
.badge-delivered { background: #0066cc; color: white; }
.table-items { width: 100%; border-collapse: collapse; margin: 15px 0; }
.table-items th { background: #f0f0f0; padding: 12px; text-align: left; border-bottom: 2px solid #1e3a5f; }
.table-items td { padding: 12px; border-bottom: 1px solid #ddd; }
.table-items tr:hover { background: #f9f9f9; }
.btn { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin: 5px; }
.btn-primary { background: #1e3a5f; color: white; }
.btn-primary:hover { background: #2c5aa0; }
.btn-danger { background: #dc3545; color: white; }
.btn-danger:hover { background: #c82333; }
.btn-secondary { background: #e0e0e0; color: #333; }
.item-row { display: flex; gap: 15px; padding: 15px; background: white; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0; }
.item-info { flex: 1; }
.item-status { display: flex; gap: 10px; align-items: center; }
</style>';

?>

<div class="pressing-container">
	<!-- SIDEBAR -->
	<div class="pressing-sidebar">
		<h3 style="margin-top: 0; font-size: 20px; margin-bottom: 30px;">📦 <?php echo $order->ref ?: 'Nouvelle'; ?></h3>

		<?php if ($id > 0) { ?>
			<div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 5px; margin-bottom: 20px;">
				<div style="font-size: 13px; opacity: 0.8; margin-bottom: 5px;">Statut Actuel</div>
				<div style="font-size: 18px; font-weight: bold;">
					<?php
					$statut_labels = ['En attente', 'En cours', 'Prêt', 'Livré'];
					$statut_icons = ['⚪', '🟠', '🟢', '🔵'];
					echo $statut_icons[$order->fk_statut ?? 0] . ' ' . $statut_labels[$order->fk_statut ?? 0];
					?>
				</div>
			</div>

			<button class="sidebar-btn sidebar-btn-primary" onclick="changeStatus(this)">
				↪️ Changer le Statut
			</button>

			<button class="sidebar-btn sidebar-btn-secondary" onclick="editOrder(this)">
				✏️ Modifier
			</button>

			<button class="sidebar-btn sidebar-btn-secondary" onclick="deleteOrder(this)" style="background: rgba(220,53,69,0.2);">
				🗑️ Supprimer
			</button>

			<hr style="border-color: rgba(255,255,255,0.2); margin: 20px 0;">

			<h4 style="margin-top: 0; font-size: 14px; opacity: 0.7; text-transform: uppercase;">ARTICLES</h4>

			<button class="sidebar-btn sidebar-btn-primary" onclick="addItem(this)">
				➕ Ajouter Article
			</button>

			<hr style="border-color: rgba(255,255,255,0.2); margin: 20px 0;">

			<h4 style="margin-top: 0; font-size: 14px; opacity: 0.7; text-transform: uppercase;">LIVRAISON</h4>

			<?php if ($order->fk_statut == 2) { ?>
				<button class="sidebar-btn" style="background: #28a745; color: white;" onclick="deliverOrder(this)">
					🚚 Livrer
				</button>
			<?php } else { ?>
				<button class="sidebar-btn" style="background: #aaa; color: white;" disabled>
					🚚 Livrer
				</button>
			<?php } ?>

			<div style="font-size: 12px; opacity: 0.7; margin-top: 10px;">
				✓ Activé quand tous les articles sont prêts
			</div>
		<?php } ?>

		<a href="index.php" class="sidebar-btn sidebar-btn-secondary" style="text-align: center;">
			← Retour au Tableau de Bord
		</a>
	</div>

	<!-- MAIN CONTENT -->
	<div class="pressing-main">
		<?php if ($action == 'create') { ?>
			<h1 style="margin-top: 0; color: #1e3a5f;">➕ Nouvelle Commande</h1>

			<form method="POST" action="order.php">
				<input type="hidden" name="token" value="<?php echo newToken(); ?>">
				<input type="hidden" name="action" value="save">

				<div class="card">
					<div class="card-title">👤 Informations Client</div>

					<div class="form-group">
						<label class="form-label">* Client</label>
						<select name="fk_soc" class="form-control" required>
							<option value="">-- Sélectionner un client --</option>
							<?php
							$sql = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE client = 1 ORDER BY nom";
							$res = $db->query($sql);
							while ($row = $db->fetch_object($res)) {
								print '<option value="'.$row->rowid.'">'.$row->nom.'</option>';
							}
							?>
						</select>
					</div>
				</div>

				<div class="card">
					<div class="card-title">📅 Dates</div>

					<div class="form-group">
						<label class="form-label">Date de Dépôt</label>
						<input type="date" name="date_depot" class="form-control" value="<?php echo date('Y-m-d'); ?>">
					</div>

					<div class="form-group">
						<label class="form-label">Date Promise de Livraison</label>
						<input type="date" name="date_promesse" class="form-control">
					</div>
				</div>

				<div class="card">
					<div class="card-title">📝 Notes</div>

					<div class="form-group">
						<label class="form-label">Notes (publiques)</label>
						<textarea name="note_public" class="form-control" rows="3"></textarea>
					</div>
				</div>

				<div style="text-align: center;">
					<button type="submit" class="btn btn-primary">✅ Créer la Commande</button>
					<a href="index.php" class="btn btn-secondary">❌ Annuler</a>
				</div>
			</form>

		<?php } elseif ($id > 0) { ?>
			<h1 style="margin-top: 0; color: #1e3a5f;">📦 <?php echo $order->ref; ?></h1>

			<div class="card">
				<div class="card-title">👤 Informations</div>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
					<div>
						<div style="opacity: 0.7; font-size: 12px;">Référence</div>
						<div style="font-weight: bold; font-size: 16px;"><?php echo $order->ref; ?></div>
					</div>
					<div>
						<div style="opacity: 0.7; font-size: 12px;">Client</div>
						<div style="font-weight: bold; font-size: 16px;">
							<?php
							$soc = new Societe($db);
							$soc->fetch($order->fk_soc);
							echo $soc->nom;
							?>
						</div>
					</div>
					<div>
						<div style="opacity: 0.7; font-size: 12px;">Date de Dépôt</div>
						<div style="font-weight: bold;">
							<?php echo dol_print_date($order->date_depot, 'day'); ?>
						</div>
					</div>
					<div>
						<div style="opacity: 0.7; font-size: 12px;">Statut</div>
						<div>
							<span class="badge badge-<?php echo ['pending', 'progress', 'ready', 'delivered'][$order->fk_statut]; ?>">
								<?php echo ['En attente', 'En cours', 'Prêt', 'Livré'][$order->fk_statut]; ?>
							</span>
						</div>
					</div>
				</div>
			</div>

			<!-- ARTICLES -->
			<div class="card">
				<div class="card-title">📦 Articles (<?php echo count($order->lines) ?? 0; ?>)</div>

				<?php
				if (is_array($order->lines) && count($order->lines) > 0) {
					foreach ($order->lines as $line) {
						$statut_icons = ['⚪', '🟠', '🟢', '🔵'];
						$statut_labels = ['En attente', 'En cours', 'Prêt', 'Livré'];
						?>
						<div class="item-row">
							<div class="item-info">
								<div style="font-weight: bold; font-size: 16px;"><?php echo $line->description; ?></div>
								<div style="opacity: 0.7; font-size: 12px; margin-top: 5px;">
									<?php echo $line->type_article . ' • ' . $line->couleur . ' • ' . $line->longueur . '×' . $line->largeur . 'cm'; ?>
								</div>
								<div style="margin-top: 5px; color: #1e3a5f; font-weight: bold;">
									<?php echo $line->prix_unitaire; ?> €
								</div>
							</div>
							<div class="item-status">
								<span class="badge badge-<?php echo ['pending', 'progress', 'ready', 'delivered'][$line->fk_statut]; ?>">
									<?php echo $statut_icons[$line->fk_statut] . ' ' . $statut_labels[$line->fk_statut]; ?>
								</span>
								<?php if ($order->fk_statut != 3) { ?>
									<button class="btn btn-secondary" onclick="nextStatus(<?php echo $line->id; ?>)">➜</button>
									<button class="btn btn-danger" onclick="removeItem(<?php echo $line->id; ?>)">−</button>
								<?php } ?>
							</div>
						</div>
					<?php }
				} else {
					print '<div style="text-align: center; color: #999; padding: 30px;">Aucun article pour le moment</div>';
				}
				?>
			</div>

		<?php } ?>
	</div>
</div>

<script>
function changeStatus(btn) {
	const newStatus = prompt('Nouveau statut:\n0 = En attente\n1 = En cours\n2 = Prêt\n3 = Livré');
	if (newStatus !== null) {
		const form = document.createElement('form');
		form.method = 'POST';
		form.action = 'order.php';
		form.innerHTML = '<input type="hidden" name="action" value="change_status">';
		form.innerHTML += '<input type="hidden" name="id" value="<?php echo $id; ?>">';
		form.innerHTML += '<input type="hidden" name="statut" value="' + newStatus + '">';
		form.innerHTML += '<input type="hidden" name="token" value="<?php echo newToken(); ?>">';
		document.body.appendChild(form);
		form.submit();
	}
}

function editOrder(btn) {
	if (confirm('Mode édition - non disponible pour le moment')) {
		// À implémenter
	}
}

function deleteOrder(btn) {
	if (confirm('Êtes-vous sûr de vouloir supprimer cette commande ?')) {
		const form = document.createElement('form');
		form.method = 'POST';
		form.action = 'order.php';
		form.innerHTML = '<input type="hidden" name="action" value="delete">';
		form.innerHTML += '<input type="hidden" name="id" value="<?php echo $id; ?>">';
		form.innerHTML += '<input type="hidden" name="token" value="<?php echo newToken(); ?>">';
		document.body.appendChild(form);
		form.submit();
	}
}

function addItem(btn) {
	const description = prompt('Description de l\'article:');
	if (description) {
		const type = prompt('Type d\'article (chemise, pantalon, etc.):');
		const couleur = prompt('Couleur:');
		const longueur = parseFloat(prompt('Longueur (cm):'));
		const largeur = parseFloat(prompt('Largeur (cm):'));

		if (type && couleur && longueur > 0 && largeur > 0) {
			const form = document.createElement('form');
			form.method = 'POST';
			form.action = 'order.php';
			form.innerHTML = '<input type="hidden" name="action" value="add_line">';
			form.innerHTML += '<input type="hidden" name="id" value="<?php echo $id; ?>">';
			form.innerHTML += '<input type="hidden" name="description" value="' + description + '">';
			form.innerHTML += '<input type="hidden" name="type_article" value="' + type + '">';
			form.innerHTML += '<input type="hidden" name="couleur" value="' + couleur + '">';
			form.innerHTML += '<input type="hidden" name="longueur" value="' + longueur + '">';
			form.innerHTML += '<input type="hidden" name="largeur" value="' + largeur + '">';
			form.innerHTML += '<input type="hidden" name="token" value="<?php echo newToken(); ?>">';
			document.body.appendChild(form);
			form.submit();
		}
	}
}

function nextStatus(itemId) {
	const form = document.createElement('form');
	form.method = 'POST';
	form.action = 'order.php';
	form.innerHTML = '<input type="hidden" name="action" value="next_status">';
	form.innerHTML += '<input type="hidden" name="id" value="<?php echo $id; ?>">';
	form.innerHTML += '<input type="hidden" name="line_id" value="' + itemId + '">';
	form.innerHTML += '<input type="hidden" name="token" value="<?php echo newToken(); ?>">';
	document.body.appendChild(form);
	form.submit();
}

function removeItem(itemId) {
	if (confirm('Êtes-vous sûr de vouloir supprimer cet article ?')) {
		const form = document.createElement('form');
		form.method = 'POST';
		form.action = 'order.php';
		form.innerHTML = '<input type="hidden" name="action" value="delete_line">';
		form.innerHTML += '<input type="hidden" name="id" value="<?php echo $id; ?>">';
		form.innerHTML += '<input type="hidden" name="line_id" value="' + itemId + '">';
		form.innerHTML += '<input type="hidden" name="token" value="<?php echo newToken(); ?>">';
		document.body.appendChild(form);
		form.submit();
	}
}

function deliverOrder(btn) {
	if (confirm('Êtes-vous sûr ? Cela marquera tous les articles comme livrés.')) {
		const form = document.createElement('form');
		form.method = 'POST';
		form.action = 'order.php';
		form.innerHTML = '<input type="hidden" name="action" value="deliver">';
		form.innerHTML += '<input type="hidden" name="id" value="<?php echo $id; ?>">';
		form.innerHTML += '<input type="hidden" name="token" value="<?php echo newToken(); ?>">';
		document.body.appendChild(form);
		form.submit();
	}
}
</script>

<?php
// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($action)) {
	if (!$user->hasRight('pressing', 'write')) {
		header('HTTP/1.0 403 Forbidden');
		exit('Accès refusé');
	}

	if ($action == 'save' && empty($id)) {
		$order->fk_soc = GETPOSTINT('fk_soc');
		$order->date_depot = strtotime(GETPOST('date_depot'));
		$order->date_promesse = GETPOST('date_promesse') ? strtotime(GETPOST('date_promesse')) : null;
		$order->note_public = GETPOST('note_public');

		if ($order->create($user) > 0) {
			setEventMessages('Commande créée avec succès', null, 'mesgs');
			header('Location: order.php?id='.$order->id);
			exit;
		} else {
			setEventMessages('Erreur lors de la création', null, 'errors');
		}
	} elseif ($action == 'change_status' && $id > 0) {
		$statut = GETPOSTINT('statut');
		$order->setStatut($statut);
		setEventMessages('Statut modifié', null, 'mesgs');
		header('Location: order.php?id='.$id);
		exit;
	} elseif ($action == 'add_line' && $id > 0) {
		$order->addLine(
			GETPOST('description'),
			GETPOST('type_article'),
			GETPOST('couleur'),
			GETPOSTFLOAT('longueur'),
			GETPOSTFLOAT('largeur')
		);
		setEventMessages('Article ajouté', null, 'mesgs');
		header('Location: order.php?id='.$id);
		exit;
	} elseif ($action == 'next_status' && $id > 0) {
		$line_id = GETPOSTINT('line_id');
		$sql = "UPDATE ".MAIN_DB_PREFIX."pressing_commandedet SET fk_statut = (fk_statut + 1) WHERE rowid = ".$line_id." AND fk_statut < 3";
		$db->query($sql);
		setEventMessages('Statut article modifié', null, 'mesgs');
		header('Location: order.php?id='.$id);
		exit;
	} elseif ($action == 'delete_line' && $id > 0) {
		$line_id = GETPOSTINT('line_id');
		$order->deleteLine($line_id);
		setEventMessages('Article supprimé', null, 'mesgs');
		header('Location: order.php?id='.$id);
		exit;
	} elseif ($action == 'delete' && $id > 0) {
		if ($order->delete($user) > 0) {
			setEventMessages('Commande supprimée', null, 'mesgs');
			header('Location: list.php');
			exit;
		}
	} elseif ($action == 'deliver' && $id > 0) {
		$order->fetchLines();
		$all_ready = true;
		foreach ($order->lines as $line) {
			if ($line->fk_statut != 2) {
				$all_ready = false;
				break;
			}
		}

		if ($all_ready) {
			$order->livrer($user);
			setEventMessages('Commande livrée avec succès', null, 'mesgs');
		} else {
			setEventMessages('Tous les articles doivent être prêts avant livraison', null, 'errors');
		}
		header('Location: order.php?id='.$id);
		exit;
	}
}
?>

<?php
llxFooter();
$db->close();
?>
