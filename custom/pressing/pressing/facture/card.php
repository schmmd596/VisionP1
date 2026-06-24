<?php
/**
 * Copyright (C) 2025 Your Company
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file 	custom/pressing/pressing/facture/card.php
 * \ingroup pressing
 * \brief 	Card for pressing invoice - Management of articles
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res && file_exists("../../../../../main.inc.php")) $res = @include "../../../../../main.inc.php";
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once dirname(__FILE__).'/../../class/pressingarticle.class.php';
require_once dirname(__FILE__).'/../../lib/pressing.lib.php';

// Load translations
$langs->load("pressing@pressing");
$langs->load("bills");

// Get parameters
$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');

// Load invoice
$facture = new Facture($db);
if ($id > 0) {
	if ($facture->fetch($id) != 0) {
		setEventMessages('Invoice not found', array(), 'errors');
		header('Location: ' . DOL_URL_ROOT . '/compta/facture/list.php');
		exit;
	}
}

// Get pressing articles
$articles = getPressingArticlesForInvoice($db, $facture->id);
$can_deliver = canDeliverInvoice($db, $facture->id);

// Process actions
if ($action == 'deliver' && $user->hasRight('pressing', 'articles', 'write') && $can_deliver) {
	if (deliverInvoiceArticles($db, $facture->id, $user, $facture) == 0) {
		setEventMessages($langs->trans('InvoiceDelivered'), array(), 'mesgs');
		// Refresh articles
		$articles = getPressingArticlesForInvoice($db, $facture->id);
		$can_deliver = canDeliverInvoice($db, $facture->id);
	} else {
		setEventMessages($langs->trans('ErrorDeliveringInvoice'), array(), 'errors');
	}
}

// Display page
$page_title = $langs->trans('PresssingInvoiceManagement') . ' - ' . $facture->ref;
llxHeader('', $page_title);

print '<div class="fichecenter">';

print load_fiche_titre($page_title, '', 'images/pressing.png');

// Invoice info
print '<div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
print '<strong>Facture :</strong> ' . htmlspecialchars($facture->ref) . ' | ';
print '<strong>Client :</strong> ';
if (!empty($facture->fk_soc)) {
	print $facture->client->name;
}
print ' | ';
print '<strong>Montant :</strong> ' . price($facture->total_ttc) . ' ' . $conf->currency;
print '</div>';

// Articles section
print '<h3 class="titre">' . $langs->trans('PressingArticles') . ' (' . count($articles) . ')</h3>';

if (count($articles) == 0) {
	print '<p class="alert alert-info">Aucun article de pressing pour cette facture. <a href="article_card.php?fk_facture=' . intval($facture->id) . '">Ajouter un article</a></p>';
} else {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="liste">';

	print '<tr class="liste_titre">';
	print '<th>Référence</th>';
	print '<th>Description</th>';
	print '<th>Dimensions</th>';
	print '<th>Prix</th>';
	print '<th>Statut</th>';
	print '<th>Entrepôt</th>';
	print '<th>Actions</th>';
	print '</tr>';

	$total_article_price = 0;

	foreach ($articles as $article) {
		$status_info = PressingArticle::getStatusLabelWithColor($article->status);
		$total_article_price += $article->price_unit;

		print '<tr>';
		print '<td><a href="article_card.php?id=' . intval($article->id) . '">' . htmlspecialchars($article->reference) . '</a></td>';
		print '<td>' . htmlspecialchars($article->description) . '</td>';
		print '<td>' . (!empty($article->longueur) ? number_format($article->longueur, 2) . ' × ' . number_format($article->largeur, 2) . ' cm' : '-') . '</td>';
		print '<td>' . (!empty($article->price_unit) ? number_format($article->price_unit, 2) : '-') . '</td>';
		print '<td><span class="badge badge-' . $status_info['badge'] . '">' . $status_info['label'] . '</span></td>';
		print '<td>' . $article->getWarehouseLabel($article->fk_entrepot) . '</td>';
		print '<td><a class="btn btn-sm btn-primary" href="article_card.php?id=' . intval($article->id) . '">Éditer</a></td>';
		print '</tr>';
	}

	print '<tr style="background-color: #f0f0f0; font-weight: bold;">';
	print '<td colspan="3">Total des articles pressing</td>';
	print '<td>' . number_format($total_article_price, 2) . '</td>';
	print '<td colspan="3"></td>';
	print '</tr>';

	print '</table>';
	print '</div>';

	// Delivery button
	print '<div style="margin-top: 20px; margin-bottom: 20px;">';
	if ($can_deliver && $user->hasRight('pressing', 'articles', 'write')) {
		print '<form method="POST" style="display: inline;">';
		print '<input type="hidden" name="action" value="deliver">';
		print '<input type="hidden" name="id" value="' . intval($facture->id) . '">';
		print '<button type="submit" class="btn btn-success" onclick="return confirm(\'Êtes-vous sûr de vouloir livrer tous les articles ?\');">';
		print '<i class="fas fa-check"></i> Livrer la facture';
		print '</button>';
		print '</form>';
		print ' <p style="color: green; margin-top: 5px;"><i class="fas fa-info-circle"></i> Tous les articles sont prêts à livrer</p>';
	} else {
		print '<button type="button" class="btn btn-secondary" disabled>';
		print '<i class="fas fa-lock"></i> Livrer la facture';
		print '</button>';

		// Count articles by status
		$counts = array('waiting' => 0, 'processing' => 0, 'ready' => 0, 'delivered' => 0);
		foreach ($articles as $article) {
			switch ($article->status) {
				case 0: $counts['waiting']++; break;
				case 1: $counts['processing']++; break;
				case 2: $counts['ready']++; break;
				case 3: $counts['delivered']++; break;
			}
		}

		print '<p style="color: red; margin-top: 5px;"><i class="fas fa-exclamation-triangle"></i> ';
		print 'En attente: ' . $counts['waiting'] . ', En cours: ' . $counts['processing'] . ', Prêts: ' . $counts['ready'] . ', Livrés: ' . $counts['delivered'];
		print ' - Tous les articles doivent être en statut "Prêt à livrer" pour livrer la facture';
		print '</p>';
	}
	print '</div>';

	// Quick add article button
	print '<div style="margin-top: 20px;">';
	print '<a href="article_card.php?fk_facture=' . intval($facture->id) . '" class="btn btn-primary">';
	print '<i class="fas fa-plus"></i> Ajouter un article';
	print '</a>';
	print '</div>';
}

print '</div>';

llxFooter();
$db->close();
?>
