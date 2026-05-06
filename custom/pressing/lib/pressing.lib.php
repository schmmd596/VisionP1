<?php
/**
 * \file       custom/pressing/lib/pressing.lib.php
 * \ingroup    pressing
 * \brief      Library file for pressing module
 */

/**
 * Prepare admin head tabs
 */
function pressing_admin_prepare_head()
{
	global $langs;
	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/pressing/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	return $head;
}

/**
 * Reception d'un article pressing
 *
 * @param DoliDB          $db       Database handler
 * @param PressingArticle $article  Pressing article object
 * @param User            $user     User object
 * @param int             $qty      Quantity to add (default: from article->qty)
 * @return int                      1 if OK, <0 if KO
 */
function pressing_reception_article($db, $article, $user, $qty = null)
{
	require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';

	if (empty($article->fk_entrepot) || empty($article->fk_product)) {
		return 0;
	}

	if (is_null($qty)) {
		$qty = (empty($article->qty) ? 1 : $article->qty);
	}

	$db->begin();

	$mouvstock = new MouvementStock($db);
	$mouvstock->fk_product = $article->fk_product;
	$mouvstock->fk_entrepot = $article->fk_entrepot;
	$mouvstock->label = "Réception pressing - " . $article->ref_article;

	// Type 3 = Stock increase (real entry)
	$result = $mouvstock->_create($user, $article->fk_product, $article->fk_entrepot, $qty, 3, 0, $mouvstock->label);

	if ($result < 0) {
		$db->rollback();
		return -1;
	}

	$db->commit();
	return 1;
}

/**
 * Deliver a pressing reception order and generate invoice + stock movements
 *
 * @param DoliDB          $db       Database handler
 * @param PressingBonEntree $bon    Pressing bon object
 * @param User            $user     User object
 * @return int                      1 if OK, <0 if KO
 */
function pressing_deliver_bon($db, $bon, $user)
{
	global $conf;
	require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
	require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
	require_once __DIR__ . '/../class/pressingarticle.class.php';

	$db->begin();

	// Check if bon has a client
	if (empty($bon->fk_soc)) {
		dol_syslog("Erreur: Le bon d'entrée " . $bon->id . " n'a pas de client", LOG_ERR);
		$db->rollback();
		return -1;
	}

	// Get all articles
	$articles = $bon->getArticles();

	if (empty($articles)) {
		dol_syslog("Erreur: Aucun article dans le bon d'entrée " . $bon->id, LOG_ERR);
		$db->rollback();
		return -1;
	}

	// Check if user has permission to create invoices
	if (empty($user->rights->facture->creer)) {
		dol_syslog("Erreur: Utilisateur " . $user->login . " n'a pas la permission de créer des factures", LOG_ERR);
		$db->rollback();
		return -1;
	}

	// Create invoice
	$facture = new Facture($db);
	$facture->socid = $bon->fk_soc;
	$facture->type = Facture::TYPE_STANDARD; // Standard invoice
	$facture->date = dol_now();
	$facture->fk_user_author = $user->id;
	$facture->entity = $conf->entity;
	// Ref will be auto-generated, no need to set it

	// Add article lines to invoice BEFORE creating
	foreach ($articles as $art) {
		$desc = "Article Pressing: " . $art->ref_article;
		if (!empty($art->longueur) && !empty($art->largeur)) {
			$desc .= " (" . $art->longueur . "x" . $art->largeur . " cm, " . number_format($art->surface, 4) . " m²)";
		}

		$qty = (empty($art->qty) ? 1 : $art->qty);
		$price_unit = (empty($art->price) ? 0 : $art->price);

		// Parameters: desc, pu_ht, qty, txtva, txlocaltax1, txlocaltax2, fk_product, remise_percent, date_start, date_end, fk_code_ventilation, info_bits, fk_remise_except, price_base_type
		$result = $facture->addline($desc, $price_unit, $qty, 0, 0, 0, $art->fk_product, 0, '', '', 0, 0, 0, 'HT');
		if ($result < 0) {
			dol_syslog("Erreur addline: " . $facture->error, LOG_ERR);
			$db->rollback();
			return -1;
		}
	}

	// Create invoice (NOT validate first)
	$idinvoice = $facture->create($user);
	if ($idinvoice < 0) {
		$error_msg = "Erreur création facture: " . $facture->error;
		if (!empty($facture->errors) && is_array($facture->errors)) {
			$error_msg .= " | " . implode(" | ", $facture->errors);
		}
		dol_syslog($error_msg, LOG_ERR);
		// Store error in global for later retrieval
		$GLOBALS['pressing_last_error'] = $error_msg;
		$db->rollback();
		return -1;
	}

	// Validate the invoice so it can be paid
	$result = $facture->validate($user);
	if ($result < 0) {
		$error_msg = "Erreur validation facture: " . $facture->error;
		dol_syslog($error_msg, LOG_ERR);
		$GLOBALS['pressing_last_error'] = $error_msg;
		$db->rollback();
		return -1;
	}

	// Create stock movements and update articles
	foreach ($articles as $art) {
		// Mark as delivered
		$art->status = 3;
		$art->date_livraison = dol_now();
		$art->fk_facture = $idinvoice;

		if ($art->update($user) < 0) {
			$db->rollback();
			return -2;
		}

		// Create stock movement (exit)
		if ($art->fk_entrepot > 0 && $art->fk_product > 0) {
			$mouvstock = new MouvementStock($db);
			$mouvstock->fk_product = $art->fk_product;
			$mouvstock->fk_entrepot = $art->fk_entrepot;
			$mouvstock->label = "Livraison pressing - " . $art->ref_article;

			$qty = (empty($art->qty) ? 1 : $art->qty);
			// Type 2 = Stock decrease (real exit)
			$result = $mouvstock->_create($user, $art->fk_product, $art->fk_entrepot, -$qty, 2, 0, $mouvstock->label);

			if ($result < 0) {
				$db->rollback();
				return -3;
			}
		}
	}

	// Update bon status
	$bon->status = 2;
	$bon->date_validation = dol_now();
	$bon->fk_user_valid = $user->id;

	if ($bon->update($user) < 0) {
		$db->rollback();
		return -4;
	}

	$db->commit();
	return $idinvoice;
}
