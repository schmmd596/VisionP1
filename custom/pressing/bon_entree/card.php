<?php
/**
 *	\file       custom/pressing/bon_entree/card_new.php
 *	\ingroup    pressing
 *	\brief      Card for pressing reception order (improved design)
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/client.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once '../class/pressingbonentree.class.php';
require_once '../class/pressingarticle.class.php';
require_once '../lib/pressing.lib.php';

$langs->load("pressing@pressing");
$langs->load("bills");
$langs->load("companies");
$langs->load("banks");

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

if (!$user->rights->pressing->read) accessforbidden();

$bon = new PressingBonEntree($db);
if ($id > 0) {
	$bon->fetch($id);
}

// Actions
if ($action == 'create' && $_SERVER['REQUEST_METHOD'] == 'POST' && $user->rights->pressing->write) {
	$bon->fk_soc = GETPOSTINT('fk_soc');

	if (empty($bon->fk_soc)) {
		setEventMessages('Veuillez sélectionner un client', null, 'errors');
	} else {
		$bon->entity = $conf->entity;
		
		$db->begin();
		
		$res = $bon->create($user);
		if ($res > 0) {
			// CREATE DRAFT INVOICE
			require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
			$facture = new Facture($db);
			$facture->socid = $bon->fk_soc;
			$facture->type = Facture::TYPE_STANDARD;
			$facture->date = dol_now();
			$facture->fk_user_author = $user->id;
			$facture->entity = $conf->entity;
			$facture->note_private = "Facture liée au bon d'entrée: " . $bon->ref;
			
			// Facture create will automatically create it as draft
			$idinvoice = $facture->create($user);
			if ($idinvoice > 0) {
				$bon->fk_facture = $idinvoice;
				$bon->update($user);
				
				$db->commit();
				setEventMessages('Bon d\'entrée et facture brouillon créés avec succès', null, 'mesgs');
				header("Location: ".$_SERVER["PHP_SELF"]."?id=".$res);
				exit;
			} else {
				$db->rollback();
				setEventMessages('Erreur lors de la création de la facture: ' . $facture->error, null, 'errors');
			}
		} else {
			$db->rollback();
			setEventMessages('Erreur lors de la création du bon: ' . $bon->error, null, 'errors');
		}
	}
} elseif ($action == 'create' && $_SERVER['REQUEST_METHOD'] == 'POST' && !$user->rights->pressing->write) {
	setEventMessages('Vous n\'avez pas les permissions pour créer un bon', null, 'errors');
}

// Add article
if ($action == 'add_article' && $user->rights->pressing->write && $id > 0) {
	$article = new PressingArticle($db);
	$article->fk_bon_entree = $id;
	$article->fk_product = GETPOSTINT('fk_product');
	
	// Auto-generate reference
	$sql_max = "SELECT MAX(rowid) as max_id FROM " . MAIN_DB_PREFIX . "pressing_article";
	$resql_max = $db->query($sql_max);
	$max_id = 0;
	if ($resql_max) {
		$obj_max = $db->fetch_object($resql_max);
		if ($obj_max) $max_id = $obj_max->max_id;
	}
	$article->ref_article = 'ART-' . sprintf('%05d', $max_id + 1);
	
	$article->fk_entrepot = GETPOSTINT('fk_entrepot');
	$article->qty = GETPOSTINT('qty');
	if (empty($article->qty)) {
		$article->qty = 1;
	}
	$article->price = price2num(GETPOST('price'), 'MU');
	$article->longueur = price2num(GETPOST('longueur'), 'MU');
	$article->largeur = price2num(GETPOST('largeur'), 'MU');

	if (empty($article->price) || $article->price <= 0) {
		setEventMessages('Le prix est requis et doit être supérieur à 0', null, 'errors');
	} elseif (empty($article->fk_entrepot)) {
		setEventMessages('L\'entrepôt est requis', null, 'errors');
	} else {
		if ($bon->fk_facture > 0) {
			$article->fk_facture = $bon->fk_facture;
		}
		
		$res = $article->create($user);
		if ($res > 0) {
			pressing_reception_article($db, $article, $user, $article->qty);
			
			// Add line to invoice
			if ($bon->fk_facture > 0) {
				require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
				$facture = new Facture($db);
				if ($facture->fetch($bon->fk_facture) > 0) {
					$desc = "Article Pressing: " . $article->ref_article;
					if (!empty($article->longueur) && !empty($article->largeur)) {
						$desc .= " (" . $article->longueur . "x" . $article->largeur . " cm)";
					}
					$facture->addline($desc, $article->price, $article->qty, 0, 0, 0, $article->fk_product, 0, '', '', 0, 0, 0, 'HT');
				}
			}
			
			setEventMessages('Article ajouté avec succès', null, 'mesgs');
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
			exit;
		} else {
			setEventMessages('Erreur lors de l\'ajout de l\'article: ' . $article->error, null, 'errors');
		}
	}
}

// Change article status
if ($action == 'change_status' && $user->rights->pressing->write) {
	$article_id = GETPOSTINT('article_id');
	$new_status = GETPOSTINT('new_status');

	if ($article_id > 0 && $new_status >= 0 && $new_status <= 3) {
		$article = new PressingArticle($db);
		if ($article->fetch($article_id) > 0) {
			// Only allow increasing status (0→1→2 and stop at 2)
			if ($new_status > $article->status && $new_status <= 2) {
				$article->status = $new_status;
				if ($article->update($user) > 0) {
					setEventMessages('Statut de l\'article mis à jour', null, 'mesgs');
				} else {
					setEventMessages('Erreur lors de la mise à jour du statut', null, 'errors');
				}
			}
		}
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
	exit;
}

// Process payment (Phase 2)
if ($action == 'process_payment' && $user->rights->pressing->write && $id > 0) {
	$payment_type = GETPOST('payment_type', 'aZ09'); // 'paid' or 'unpaid'
	$payment_amount = price2num(GETPOST('payment_amount'), 'MU');
	$fk_bank_account = GETPOSTINT('fk_bank_account');

	if ($payment_type == 'paid' && ($payment_amount <= 0 || empty($fk_bank_account))) {
		setEventMessages('Veuillez entrer un montant valide et sélectionner un compte bancaire', null, 'errors');
	} else {
		// Deliver the bon and create/validate invoice
		$idinvoice = pressing_deliver_bon($db, $bon, $user);
		if ($idinvoice > 0) {
			// Handle payment if paid
			if ($payment_type == 'paid') {
				require_once DOL_DOCUMENT_ROOT . '/compta/paiement/class/paiement.class.php';
				require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';
				
				$paiement = new Paiement($db);
				$paiement->datepaye = dol_now();
				$paiement->amounts = array($idinvoice => $payment_amount);
				$paiement->paiementid = 4; // Default to Cash
				$paiement->num_paiement = '';
				
				$paiement_id = $paiement->create($user);
				if ($paiement_id > 0) {
					$paiement->addPaymentToBank($user, 'payment', '(CustomerInvoicePayment)', $fk_bank_account, '', '');
				} else {
					setEventMessages('Erreur lors de la création du paiement: ' . $paiement->error, null, 'warnings');
				}

				// Create payment record on the bon
				$bon->payment_status = 1; // 1 = paid
				$bon->payment_amount = $payment_amount;
				$bon->fk_bank_account = $fk_bank_account;
				$bon->date_payment = dol_now();
			} else {
				$bon->payment_status = 0; // 0 = unpaid
			}

			if ($bon->update($user) >= 0) {
				setEventMessages('Bon livré avec succès! Facture validée.', null, 'mesgs');
				$bon->fetch($id);
			} else {
				setEventMessages('Bon livré mais erreur lors de la sauvegarde locale du paiement', null, 'warnings');
				$bon->fetch($id);
			}
		} else {
			$error_msg = 'Erreur lors de la livraison';
			if (!empty($GLOBALS['pressing_last_error'])) {
				$error_msg .= ': ' . $GLOBALS['pressing_last_error'];
			}
			setEventMessages($error_msg, null, 'errors');
		}
	}
}

// Deliver action
if ($action == 'deliver' && $user->rights->pressing->deliver && $id > 0) {
	if (!$bon->canDeliver()) {
		setEventMessages('Tous les articles ne sont pas au statut "Prêt à livrer". Veuillez mettre à jour les statuts des articles.', null, 'errors');
	} elseif (empty($bon->fk_soc)) {
		setEventMessages('Erreur: Aucun client assigné au bon d\'entrée.', null, 'errors');
	} elseif (!$user->rights->facture->creer) {
		setEventMessages('Erreur: Vous n\'avez pas les permissions nécessaires pour créer des factures.', null, 'errors');
	} else {
		// Show payment dialog instead of directly delivering
		// Payment dialog will be shown in the view section
	}
}

// Delete action
if ($action == 'delete' && $user->rights->pressing->delete && $id > 0) {
	require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';

	$db->begin();

	$articles = $bon->getArticles();

	foreach ($articles as $art) {
		if ($art->fk_entrepot > 0 && $art->fk_product > 0) {
			$mouvstock = new MouvementStock($db);
			$mouvstock->fk_product = $art->fk_product;
			$mouvstock->fk_entrepot = $art->fk_entrepot;
			$mouvstock->label = "Suppression pressing - " . $art->ref_article;
			$qty = (empty($art->qty) ? 1 : $art->qty);
			$result = $mouvstock->_create($user, $art->fk_product, $art->fk_entrepot, -$qty, 3, 0, $mouvstock->label);
			if ($result < 0) {
				$db->rollback();
				setEventMessages('Erreur lors de la suppression des mouvements de stock', null, 'errors');
				header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
				exit;
			}
		}
	}

	foreach ($articles as $art) {
		$sql = "DELETE FROM " . MAIN_DB_PREFIX . "pressing_article WHERE rowid = " . (int) $art->id;
		$resql = $db->query($sql);
		if (!$resql) {
			$db->rollback();
			setEventMessages('Erreur lors de la suppression des articles', null, 'errors');
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
			exit;
		}
	}

	$sql = "DELETE FROM " . MAIN_DB_PREFIX . "pressing_bon_entree WHERE rowid = " . (int) $id;
	$resql = $db->query($sql);
	if ($resql) {
		$db->commit();
		setEventMessages('Bon d\'entrée supprimé avec succès', null, 'mesgs');
		header("Location: list.php");
		exit;
	} else {
		$db->rollback();
		setEventMessages('Erreur lors de la suppression du bon', null, 'errors');
	}
}

// Get articles
$articles = $bon->getArticles();
$stats = $bon->getArticleStats();

// View
llxHeader('', 'Bon d\'Entrée Pressing');

// Include pressing stylesheet
require_once '../includes/header.php';

$form = new Form($db);
$formproduct = new FormProduct($db);

// Add CSS for better design
print '<style>
.pressing-creation-card {
	background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
	color: white;
	border-radius: 12px;
	padding: 40px;
	margin-bottom: 30px;
	box-shadow: 0 8px 25px rgba(40,167,69,0.2);
	min-height: 300px;
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
	text-align: center;
}

.pressing-creation-card h2 {
	font-size: 28px;
	margin-bottom: 20px;
	color: white;
}

.pressing-creation-card .form-group {
	width: 100%;
	max-width: 400px;
	margin-bottom: 20px;
}

.pressing-creation-card label {
	display: block;
	text-align: left;
	margin-bottom: 10px;
	font-weight: 600;
}

.pressing-creation-card select {
	width: 100%;
	padding: 12px;
	border: none;
	border-radius: 6px;
	font-size: 16px;
}

.pressing-creation-card .button {
	background-color: white;
	color: #28a745;
	border: none;
	padding: 14px 40px;
	font-size: 16px;
	font-weight: 700;
	border-radius: 6px;
	cursor: pointer;
	transition: all 0.3s ease;
	min-width: 200px;
}

.pressing-creation-card .button:hover {
	transform: translateY(-3px);
	box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.status-buttons {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

.status-btn {
	padding: 8px 14px;
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
}

.status-btn-disabled {
	background-color: #ccc;
	color: #666;
	cursor: not-allowed;
}

.article-status-0 { background-color: #fff3cd; color: #856404; padding: 6px 12px; border-radius: 20px; font-weight: 600; }
.article-status-1 { background-color: #cfe2ff; color: #084298; padding: 6px 12px; border-radius: 20px; font-weight: 600; }
.article-status-2 { background-color: #d1e7dd; color: #0f5132; padding: 6px 12px; border-radius: 20px; font-weight: 600; }
.article-status-3 { background-color: #d3d3d3; color: #383d41; padding: 6px 12px; border-radius: 20px; font-weight: 600; }

.payment-modal {
	position: fixed;
	z-index: 1000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0,0,0,0.4);
}

.payment-modal-content {
	background-color: white;
	margin: 10% auto;
	padding: 0;
	border: 1px solid #888;
	border-radius: 12px;
	width: 90%;
	max-width: 500px;
	box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.payment-modal-header {
	padding: 20px;
	background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
	color: white;
	border-radius: 12px 12px 0 0;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.payment-modal-header h2 {
	margin: 0;
	font-size: 20px;
}

.payment-modal-close {
	font-size: 28px;
	font-weight: bold;
	cursor: pointer;
	transition: all 0.2s ease;
}

.payment-modal-close:hover {
	transform: scale(1.2);
}

.payment-modal form {
	padding: 20px;
}

.payment-form-group {
	margin-bottom: 20px;
}

.payment-form-group label {
	display: block;
	margin-bottom: 10px;
	color: #333;
}

.payment-form-group input[type="number"],
.payment-form-group select {
	width: 100%;
	padding: 10px;
	border: 1px solid #ddd;
	border-radius: 6px;
	font-size: 14px;
}

.payment-radio-group {
	padding: 10px;
	background-color: #f8f9fa;
	border-radius: 6px;
}

.payment-radio-group div {
	margin-bottom: 8px;
}

.payment-modal-footer {
	padding: 15px 20px;
	background-color: #f8f9fa;
	border-top: 1px solid #dee2e6;
	border-radius: 0 0 12px 12px;
	display: flex;
	justify-content: flex-end;
	gap: 10px;
}

.payment-btn {
	padding: 10px 20px;
	border: none;
	border-radius: 6px;
	cursor: pointer;
	font-weight: 600;
	transition: all 0.2s ease;
	display: flex;
	align-items: center;
	gap: 8px;
}

.payment-btn-primary {
	background-color: #28a745;
	color: white;
}

.payment-btn-primary:hover {
	background-color: #218838;
	transform: translateY(-2px);
}

.payment-btn-secondary {
	background-color: #6c757d;
	color: white;
}

.payment-btn-secondary:hover {
	background-color: #5a6268;
	transform: translateY(-2px);
}

.pressing-btn {
	display: inline-block;
	padding: 12px 24px;
	margin-right: 10px;
	background-color: #28a745;
	color: white;
	border: none;
	border-radius: 6px;
	cursor: pointer;
	font-weight: 600;
	transition: all 0.2s ease;
}

.pressing-btn:hover {
	background-color: #218838;
	transform: translateY(-2px);
}

.pressing-btn-success {
	background-color: #28a745;
	color: white;
}

.pressing-btn-success:hover {
	background-color: #218838;
}

.pressing-btn-danger {
	background-color: #dc3545;
	color: white;
}

.pressing-btn-danger:hover {
	background-color: #c82333;
}

.pressing-btn-primary {
	background-color: #007bff;
	color: white;
}

.pressing-btn-primary:hover {
	background-color: #0056b3;
}

.pressing-actions {
	margin: 20px 0;
	padding: 20px;
	background-color: #f8f9fa;
	border-radius: 8px;
	display: flex;
	gap: 10px;
}
</style>';

if (!$id) {
	// Create form with beautiful design
	print '<div class="pressing-creation-card">';
	print '<h2><i class="fas fa-box-open"></i> Créer un Bon d\'Entrée</h2>';
	print '<p style="margin-bottom: 30px; font-size: 16px;">Sélectionnez un client pour commencer</p>';

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="create">';

	print '<div class="form-group">';
	print '<label for="fk_soc"><i class="fas fa-user-tie"></i> Choix du Client</label>';

	$sql = "SELECT rowid, nom as name FROM " . MAIN_DB_PREFIX . "societe WHERE client = 1 ORDER BY nom";
	$resql = $db->query($sql);
	$companies = array();
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$companies[$obj->rowid] = $obj->name;
		}
	}
	print $form->selectarray('fk_soc', $companies, '', 1, 0);
	print '</div>';

	print '<button type="submit" class="button"><i class="fas fa-check-circle"></i> CRÉER</button>';
	print '</form>';
	print '</div>';

} else {
	// Display bon with improved design
	print '<div class="pressing-header">';
	print '<h1><i class="fas fa-file-invoice"></i> Bon d\'Entrée: ' . $bon->ref . '</h1>';
	print '</div>';

	$soc = new Societe($db);
	$soc->fetch($bon->fk_soc);

	print '<div class="fichecenter">';
	print '<div class="pressing-card">';
	print '<table class="border centpercent">';
	print '<tr><td class="titlefield"><strong><i class="fas fa-building"></i> Client</strong></td><td>' . $soc->name . '</td></tr>';
	print '<tr><td><strong><i class="fas fa-calendar"></i> Date Entrée</strong></td><td>' . dol_print_date($bon->date_entree, 'day') . '</td></tr>';
	print '<tr><td><strong><i class="fas fa-circle"></i> Statut</strong></td><td>' . $bon->getStatusLabel() . '</td></tr>';
	print '</table>';
	print '</div>';
	print '</div>';

	// Buttons
	print '<div class="pressing-actions">';

	if ($bon->status < 2) {
		if ($bon->canDeliver()) {
			print '<button onclick="document.getElementById(\'payment_dialog\').style.display=\'block\'" class="pressing-btn pressing-btn-success">';
			print '<i class="fas fa-shipping-fast"></i> Livrer & Créer Facture';
			print '</button>';
		} else {
			print '<a class="pressing-btn" style="background-color: #ccc; color: #666; cursor: not-allowed;">';
			print '<i class="fas fa-lock"></i> Livrer (Articles non prêts)';
			print '</a>';
		}
	}

	if ($bon->status < 2 && $user->rights->pressing->delete) {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;" onsubmit="return confirm(\'Êtes-vous sûr de vouloir supprimer ce bon?\');">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="delete">';
		print '<input type="hidden" name="id" value="'.$id.'">';
		print '<button type="submit" class="pressing-btn pressing-btn-danger"><i class="fas fa-trash"></i> Supprimer</button>';
		print '</form>';
	}

	print '</div>';

	// Payment dialog (modal)
	print '
	<div id="payment_dialog" class="payment-modal" style="display:none;">
		<div class="payment-modal-content">
			<div class="payment-modal-header">
				<h2>Paiement & Livraison</h2>
				<span class="payment-modal-close" onclick="document.getElementById(\'payment_dialog\').style.display=\'none\'">&times;</span>
			</div>
			<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'" id="payment_form">
				<input type="hidden" name="token" value="'.newToken().'">
				<input type="hidden" name="action" value="process_payment">
				<input type="hidden" name="id" value="'.$id.'">

				<div class="payment-form-group">
					<label><strong>Mode de Paiement</strong></label>
					<div class="payment-radio-group">
						<div>
							<input type="radio" id="unpaid" name="payment_type" value="unpaid" checked onchange="togglePaymentFields()">
							<label for="unpaid" style="display:inline; margin-left: 10px;"><i class="fas fa-times-circle"></i> Laisser Impayé</label>
						</div>
						<div style="margin-top: 10px;">
							<input type="radio" id="paid" name="payment_type" value="paid" onchange="togglePaymentFields()">
							<label for="paid" style="display:inline; margin-left: 10px;"><i class="fas fa-check-circle"></i> Marquer Payé</label>
						</div>
					</div>
				</div>

				<div id="payment_fields" style="display:none;">
					<div class="payment-form-group">
						<label for="payment_amount"><strong>Montant à Payer</strong></label>';
						
	$total_amount = 0;
	if (is_array($articles)) {
		foreach ($articles as $art) {
			$total_amount += (empty($art->price) ? 0 : $art->price) * (empty($art->qty) ? 1 : $art->qty);
		}
	}
	
	print '					<input type="number" id="payment_amount" name="payment_amount" step="0.01" min="0" value="' . number_format($total_amount, 2, '.', '') . '" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
					</div>

					<div class="payment-form-group">
						<label for="fk_bank_account"><strong>Compte Bancaire</strong></label>';

	// Load bank accounts via direct SQL
	$banks = array();
	$sql_banks = "SELECT rowid, ref, label FROM " . MAIN_DB_PREFIX . "bank_account WHERE entity = " . (int)$conf->entity . " ORDER BY ref";
	$res_banks = $db->query($sql_banks);
	if ($res_banks) {
		while ($obj_bank = $db->fetch_object($res_banks)) {
			$banks[$obj_bank->rowid] = $obj_bank->ref . ' - ' . $obj_bank->label;
		}
	}
	print $form->selectarray('fk_bank_account', $banks, '', 1, 0);

	print '
					</div>
				</div>

				<div class="payment-modal-footer">
					<button type="button" onclick="document.getElementById(\'payment_dialog\').style.display=\'none\'" class="payment-btn payment-btn-secondary">
						<i class="fas fa-times"></i> Annuler
					</button>
					<button type="submit" class="payment-btn payment-btn-primary">
						<i class="fas fa-check"></i> Confirmer Livraison
					</button>
				</div>
			</form>
		</div>
	</div>

	<script>
	function togglePaymentFields() {
		const isPaid = document.getElementById("paid").checked;
		document.getElementById("payment_fields").style.display = isPaid ? "block" : "none";
		if (isPaid) {
			document.getElementById("payment_amount").required = true;
			document.getElementById("fk_bank_account").required = true;
		} else {
			document.getElementById("payment_amount").required = false;
			document.getElementById("fk_bank_account").required = false;
		}
	}

	// Close modal when clicking outside of it
	window.onclick = function(event) {
		const modal = document.getElementById("payment_dialog");
		if (event.target === modal) {
			modal.style.display = "none";
		}
	}
	</script>
	';

	// Show add form and summary only if not delivered
	$invoice_id = $bon->fk_facture;
	if (empty($invoice_id) && !empty($articles) && isset($articles[0]->fk_facture)) {
		$invoice_id = $articles[0]->fk_facture;
	}
	$invoice_link = DOL_URL_ROOT . '/compta/facture/card.php?id=' . $invoice_id;
	if (empty($invoice_id)) {
		$invoice_link = DOL_URL_ROOT . '/compta/facture/list.php?leftmenu=customers_bills';
	}
	
	if ($bon->status < 2) {
		// Add article form FIRST (more visible)
		print '<div style="margin-bottom: 30px; padding: 20px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 8px; color: white;">';
		// ... (keep the rest of the form)
	print '<h3 style="margin-top: 0; margin-bottom: 20px; color: white;"><i class="fas fa-plus-circle"></i> Ajouter un Article au Bon</h3>';

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'" id="form_add_article">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add_article">';
	print '<input type="hidden" name="id" value="'.$id.'">';

	print '<table class="border centpercent" style="background-color: white; color: #333; margin-bottom: 20px;">';

	// Product
	print '<tr style="background-color: #f8f9fa;">';
	print '<td class="titlefield" style="padding: 12px; font-weight: 600;"><i class="fas fa-box"></i> Produit</td>';
	print '<td style="padding: 12px;">';
	$sql = "SELECT rowid, ref, label, price FROM " . MAIN_DB_PREFIX . "product WHERE entity IN (0," . $conf->entity . ") ORDER BY ref";
	$resql = $db->query($sql);
	print '<select id="fk_product" name="fk_product" class="flat" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box;" onchange="calculatePrice()">';
	print '<option value="0" data-price="0">-- Sélectionner un produit --</option>';
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			print '<option value="' . $obj->rowid . '" data-price="' . $obj->price . '">' . $obj->ref . ' - ' . $obj->label . '</option>';
		}
	}
	print '</select>';
	print '</td>';
	print '</tr>';

	// Longueur
	print '<tr>';
	print '<td class="titlefield" style="padding: 12px; font-weight: 600;"><i class="fas fa-arrows-alt-v"></i> Longueur (cm)</td>';
	print '<td style="padding: 12px;"><input type="number" id="longueur" name="longueur" value="1" min="0" step="0.01" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box;" onchange="calculatePrice()" onkeyup="calculatePrice()"></td>';
	print '</tr>';

	// Largeur
	print '<tr style="background-color: #f8f9fa;">';
	print '<td class="titlefield" style="padding: 12px; font-weight: 600;"><i class="fas fa-arrows-alt-h"></i> Largeur (cm)</td>';
	print '<td style="padding: 12px;"><input type="number" id="largeur" name="largeur" value="1" min="0" step="0.01" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box;" onchange="calculatePrice()" onkeyup="calculatePrice()"></td>';
	print '</tr>';

	// Quantity
	print '<tr>';
	print '<td class="titlefield" style="padding: 12px; font-weight: 600;"><i class="fas fa-cubes"></i> Quantité</td>';
	print '<td style="padding: 12px;"><input type="number" id="qty" name="qty" value="1" min="1" step="1" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box;" onchange="calculatePrice()" onkeyup="calculatePrice()"></td>';
	print '</tr>';

	// Price
	print '<tr style="background-color: #f8f9fa;">';
	print '<td class="titlefield" style="padding: 12px; font-weight: 600;"><i class="fas fa-price-tag"></i> Prix Unitaire Total</td>';
	print '<td style="padding: 12px;"><input type="number" id="price" name="price" step="0.01" min="0" required placeholder="0.00" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; background-color: #e9ecef;"></td>';
	print '</tr>';

	// Warehouse
	print '<tr>';
	print '<td class="titlefield" style="padding: 12px; font-weight: 600;"><i class="fas fa-warehouse"></i> Entrepôt</td>';
	print '<td style="padding: 12px;">';
	$entrepot_sel = new Entrepot($db);
	$warehouses_raw = $entrepot_sel->list_array();
	$warehouses = array(0 => '-- S&eacute;lectionner un entrep&ocirc;t --');
	if (!empty($warehouses_raw)) {
		foreach ($warehouses_raw as $wid => $wlabel) {
			$warehouses[$wid] = $wlabel;
		}
	}
	print $form->selectarray('fk_entrepot', $warehouses, '', 0);
	print '</td>';
	print '</tr>';

	print '</table>';

	print '<script>
	function calculatePrice() {
		var selectProduct = document.getElementById("fk_product");
		var productPrice = 0;
		if (selectProduct.options[selectProduct.selectedIndex]) {
			productPrice = parseFloat(selectProduct.options[selectProduct.selectedIndex].getAttribute("data-price")) || 0;
		}
		
		var longueur = parseFloat(document.getElementById("longueur").value) || 0;
		var largeur = parseFloat(document.getElementById("largeur").value) || 0;
		var qty = parseFloat(document.getElementById("qty").value) || 1;
		
		// LONGEUR * LARGEUR * QUANTITE * PRIX UNITAIRE
		var totalPrice = longueur * largeur * qty * productPrice;
		document.getElementById("price").value = totalPrice.toFixed(2);
	}
	</script>';

	print '<div class="center" style="margin-top: 20px;">';
	print '<button type="submit" class="pressing-btn" style="background-color: white; color: #28a745; padding: 12px 30px; font-size: 15px;"><i class="fas fa-plus-circle"></i> Ajouter l\'Article</button>';
	print '</div>';
	print '</form>';
	print '</div>';

	// Status summary
	print '<div style="margin-bottom: 20px; padding: 20px; background-color: #f8f9fa; border-radius: 8px; border-left: 4px solid #28a745;">';
	print '<b><i class="fas fa-chart-bar"></i> Résumé Articles:</b><br><br>';
	print '<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">';
	print '<div style="text-align: center; padding: 15px; background: white; border-radius: 6px;">';
	print '<div style="font-size: 24px; font-weight: 700; color: #ffc107;">' . $stats[0] . '</div>';
	print '<div style="font-size: 12px; color: #666; margin-top: 5px;">En Attente</div>';
	print '</div>';
	print '<div style="text-align: center; padding: 15px; background: white; border-radius: 6px;">';
	print '<div style="font-size: 24px; font-weight: 700; color: #17a2b8;">' . $stats[1] . '</div>';
	print '<div style="font-size: 12px; color: #666; margin-top: 5px;">En Traitement</div>';
	print '</div>';
	print '<div style="text-align: center; padding: 15px; background: white; border-radius: 6px;">';
	print '<div style="font-size: 24px; font-weight: 700; color: #28a745;">' . $stats[2] . '</div>';
	print '<div style="font-size: 12px; color: #666; margin-top: 5px;">Prêts à Livrer</div>';
	print '</div>';
	print '<div style="text-align: center; padding: 15px; background: white; border-radius: 6px;">';
	print '<div style="font-size: 24px; font-weight: 700; color: #999;">' . $stats[3] . '</div>';
	print '<div style="font-size: 12px; color: #666; margin-top: 5px;">Livrés</div>';
	print '</div>';
	print '</div>';
	print '</div>';
	
		print '<div style="margin-bottom: 30px; padding: 20px; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">';
		print '<div>';
		print '<h3 style="margin: 0; color: #856404;"><i class="fas fa-file-invoice"></i> Une facture brouillon est associée à ce bon</h3>';
		print '<p style="margin: 5px 0 0 0; color: #666;">Les articles ajoutés y sont insérés automatiquement. La facture sera validée lors de la livraison.</p>';
		print '</div>';
		print '<a href="' . $invoice_link . '" class="pressing-btn" style="background-color: #ffc107; color: #333; text-decoration: none; padding: 10px 20px;"><i class="fas fa-file-invoice"></i> Voir la Facture (Brouillon)</a>';
		print '</div>';
		
	} else {
		// Show invoice link if delivered
		print '<div style="margin-bottom: 30px; padding: 20px; background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">';
		print '<div>';
		print '<h3 style="margin: 0; color: #28a745;"><i class="fas fa-check-circle"></i> Ce bon d\'entrée a été livré et facturé</h3>';
		print '<p style="margin: 5px 0 0 0; color: #666;">La facture correspondante a été générée et validée dans le système.</p>';
		print '</div>';
		print '<a href="' . $invoice_link . '" class="pressing-btn" style="background-color: #28a745; color: white; text-decoration: none; padding: 10px 20px;"><i class="fas fa-file-invoice"></i> Voir la Facture</a>';
		print '</div>';
	}

	// Articles list
	print load_fiche_titre('Articles du Bon', '', '');
	print '<table class="pressing-table centpercent">';
	print '<thead><tr>';
	print '<th><i class="fas fa-barcode"></i> Réf</th>';
	print '<th><i class="fas fa-box"></i> Produit</th>';
	print '<th><i class="fas fa-warehouse"></i> Entrepôt</th>';
	print '<th><i class="fas fa-cubes"></i> Qté</th>';
	print '<th><i class="fas fa-price-tag"></i> Prix</th>';
	print '<th><i class="fas fa-traffic-light"></i> Statut</th>';
	print '<th><i class="fas fa-cog"></i> Actions</th>';
	print '</tr></thead><tbody>';

	if (!empty($articles)) {
		$prod = new Product($db);
		$ent = new Entrepot($db);
		foreach ($articles as $art) {
			print '<tr class="oddeven">';
			print '<td><strong>' . $art->ref_article . '</strong></td>';

			$plabel = '';
			if ($art->fk_product > 0) {
				$prod->fetch($art->fk_product);
				$plabel = $prod->ref;
			}
			print '<td>' . $plabel . '</td>';

			$elabel = '';
			if ($art->fk_entrepot > 0) {
				$ent->fetch($art->fk_entrepot);
				$elabel = $ent->label;
			}
			print '<td>' . $elabel . '</td>';

			print '<td>' . $art->qty . '</td>';
			print '<td>' . price($art->price) . '</td>';

			// Status with badge
			$status_label = $art->getStatusLabel();
			$status_class = 'article-status-' . $art->status;
			print '<td><span class="' . $status_class . '">' . $status_label . '</span></td>';

			// Action buttons
			print '<td>';
			print '<div class="status-buttons">';

			// Button to next status (only if not delivered)
			if ($art->status < 2) {
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="change_status">';
				print '<input type="hidden" name="article_id" value="'.$art->id.'">';
				print '<input type="hidden" name="id" value="'.$id.'">';

				$next_status = $art->status + 1;
				$status_labels = array(1 => 'Traiter', 2 => 'Prêt');
				print '<input type="hidden" name="new_status" value="'.$next_status.'">';
				print '<button type="submit" class="status-btn status-btn-next">→ ' . $status_labels[$next_status] . '</button>';
				print '</form>';
			}

			// Edit button
			print '<a href="' . DOL_URL_ROOT . '/custom/pressing/article/card.php?id='.$art->id.'" class="status-btn" style="background-color: #007bff; color: white; text-decoration: none;">';
			print '<i class="fas fa-edit"></i>';
			print '</a>';

			print '</div>';
			print '</td>';
			print '</tr>';
		}
	} else {
		print '<tr><td colspan="7" class="opacitymedium" style="text-align: center; padding: 30px;"><i class="fas fa-inbox"></i> Aucun article pour le moment.</td></tr>';
	}
	print '</tbody></table>';
}

llxFooter();
$db->close();
?>
