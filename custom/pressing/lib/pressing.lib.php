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
 * @return int                      1 if OK, <0 if KO
 */
function pressing_reception_article($db, $article, $user)
{
	require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';

	if (empty($article->fk_entrepot) || empty($article->fk_product)) {
		return 0;
	}

	$db->begin();

	$mouvstock = new MouvementStock($db);
	$mouvstock->fk_product = $article->fk_product;
	$mouvstock->fk_entrepot = $article->fk_entrepot;
	$mouvstock->label = "Réception pressing - " . $article->ref_article;

	// Type 3 = Stock increase (real entry)
	$result = $mouvstock->_create($user, $article->fk_product, $article->fk_entrepot, 1, 3, 0, $mouvstock->label);

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

	// Get all articles
	$articles = $bon->getArticles();

	// Create invoice
	$facture = new Facture($db);
	$facture->fk_soc = $bon->fk_soc;
	$facture->date_valid = dol_now();
	$facture->fk_user_author = $user->id;

	// Add article lines to invoice
	foreach ($articles as $art) {
		$desc = "Article Pressing: " . $art->ref_article;
		if ($art->longueur > 0 && $art->largeur > 0) {
			$desc .= " (" . $art->longueur . "x" . $art->largeur . " cm, " . number_format($art->surface, 4) . " m²)";
		}

		$facture->addline($desc, $art->price, 1, 0, 0, 0, $art->fk_product, 0, '', '', 0, 0, '', 'HT');
	}

	// Create and validate invoice
	$idinvoice = $facture->create($user);
	if ($idinvoice < 0) {
		$db->rollback();
		return -1;
	}

	// Validate invoice
	$facture->fetch($idinvoice);
	$facture->validate($user);

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

			// Type 2 = Stock decrease (real exit)
			$result = $mouvstock->_create($user, $art->fk_product, $art->fk_entrepot, -1, 2, 0, $mouvstock->label);

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
	return 1;
}
