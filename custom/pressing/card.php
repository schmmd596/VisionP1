<?php
/**
 * Main pressing order card page (create/view/edit)
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/pressing/class/pressingcommande.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/pressing/lib/pressing.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/client.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

$langs->loadLangs(array('pressing', 'bills', 'companies', 'compta'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');

$object = new PressingCommande($db);

$usercancreate = $user->hasRight('pressing', 'pressing_write');
$usercandelete = $user->hasRight('pressing', 'pressing_delete');

if ($action == 'add' && $usercancreate) {
	$ref = GETPOST('ref', 'alpha');
	$fk_soc = GETPOSTINT('fk_soc');
	$fk_facture = GETPOSTINT('fk_facture');
	$date_depot = dol_mktime(0, 0, 0, GETPOSTINT('date_depot_month'), GETPOSTINT('date_depot_day'), GETPOSTINT('date_depot_year'));
	$date_promesse = dol_mktime(0, 0, 0, GETPOSTINT('date_promesse_month'), GETPOSTINT('date_promesse_day'), GETPOSTINT('date_promesse_year'));

	if (empty($fk_soc)) {
		$action = 'create';
		setEventMessages($langs->trans('ErrorSelectClient'), null, 'errors');
	} else {
		$object->ref = $ref ?: 'PC-'.dol_print_date(dol_now(), '%Y%m%d%H%M%S');
		$object->fk_soc = $fk_soc;
		$object->fk_facture = $fk_facture > 0 ? $fk_facture : null;
		$object->date_depot = $date_depot;
		$object->date_promesse = $date_promesse;
		$object->fk_user_author = $user->id;

		$result = $object->create($user);
		if ($result > 0) {
			setEventMessages($langs->trans('PresssingCreated'), null, 'mesgs');
			header('Location: card.php?id='.$object->id);
			exit;
		} else {
			$action = 'create';
			setEventMessages($object->errors, null, 'errors');
		}
	}
}

if ($action == 'update' && $usercancreate) {
	$object->fetch($id);

	$object->ref = GETPOST('ref', 'alpha');
	$object->fk_soc = GETPOSTINT('fk_soc');
	$object->fk_facture = GETPOSTINT('fk_facture');
	$object->date_depot = dol_mktime(0, 0, 0, GETPOSTINT('date_depot_month'), GETPOSTINT('date_depot_day'), GETPOSTINT('date_depot_year'));
	$object->date_promesse = dol_mktime(0, 0, 0, GETPOSTINT('date_promesse_month'), GETPOSTINT('date_promesse_day'), GETPOSTINT('date_promesse_year'));
	$object->note_public = GETPOST('note_public', 'restricthtml');
	$object->note_private = GETPOST('note_private', 'restricthtml');

	$result = $object->update($user);
	if ($result >= 0) {
		setEventMessages($langs->trans('PresssingUpdated'), null, 'mesgs');
	} else {
		setEventMessages($object->errors, null, 'errors');
	}
}

if ($action == 'validate' && $usercancreate) {
	$object->fetch($id);
	$object->setStatut(PressingCommande::STATUS_RECEIVED);
	setEventMessages($langs->trans('PresssingValidated'), null, 'mesgs');
}

if ($action == 'inprogress' && $usercancreate) {
	$object->fetch($id);
	$object->setStatut(PressingCommande::STATUS_INPROGRESS);
	setEventMessages('Commande en cours', null, 'mesgs');
}

if ($action == 'ready' && $usercancreate) {
	$object->fetch($id);
	$object->setStatut(PressingCommande::STATUS_DONE);
	setEventMessages('Commande prêt à livrer', null, 'mesgs');
}

if ($action == 'deliver' && $usercancreate) {
	$object->fetch($id);
	$object->fetchLines();

	if ($object->getLinesStatuts()) {
		$result = $object->livrer($user);
		if ($result >= 0) {
			setEventMessages($langs->trans('PressingLivre'), null, 'mesgs');
		} else {
			setEventMessages('Erreur lors de la livraison', null, 'errors');
		}
	} else {
		setEventMessages($langs->trans('PressingItemsNotReady'), null, 'errors');
	}
}

if ($action == 'deleteline') {
	$lineid = GETPOSTINT('lineid');
	$sql = "DELETE FROM ".MAIN_DB_PREFIX."pressing_commandedet WHERE rowid = ".$lineid;
	if ($db->query($sql)) {
		setEventMessages('Ligne supprimée', null, 'mesgs');
	}
	$action = '';
}

if ($action == 'addline' && $usercancreate) {
	$object->fetch($id);
	$description = GETPOST('description', 'restricthtml');
	$type_article = GETPOST('type_article', 'alpha');
	$couleur = GETPOST('couleur', 'alpha');
	$longueur = GETPOSTFLOAT('longueur');
	$largeur = GETPOSTFLOAT('largeur');
	$fk_entrepot = GETPOSTINT('fk_entrepot');
	$prix_unitaire = GETPOSTFLOAT('prix_unitaire');

	$line_id = $object->addLine($description, $type_article, $couleur, $longueur, $largeur, $fk_entrepot);

	if ($line_id > 0) {
		if ($prix_unitaire > 0) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."pressing_commandedet SET prix_unitaire = ".$prix_unitaire;
			$sql .= " WHERE rowid = ".$line_id;
			$db->query($sql);
		}
		setEventMessages('Article ajouté', null, 'mesgs');
	}
}

if ($action == 'updateline' && $usercancreate) {
	$lineid = GETPOSTINT('lineid');
	$statut_article = GETPOSTINT('statut_article');

	$sql = "UPDATE ".MAIN_DB_PREFIX."pressing_commandedet";
	$sql .= " SET fk_statut = ".$statut_article;
	$sql .= " WHERE rowid = ".$lineid;

	if ($db->query($sql)) {
		setEventMessages('Ligne mise à jour', null, 'mesgs');
	}
}

if (!$action || $action == 'create') {
	if ($action == 'create' || $action == 'add') {
		$object->fk_user_author = $user->id;
		$object->date_depot = dol_now();
	} elseif ($id > 0) {
		$result = $object->fetch($id);
		if ($result <= 0) {
			$error++;
		}
	}
}

llxHeader();

if ($action == 'create') {
	print load_fiche_titre($langs->trans('CreatePressing'), '', 'fa-soap');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" class="tagtable nobottom">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="boxshadow withpadding">';
	print '<fieldset><legend>'.$langs->trans('Pressing').' Info</legend>';

	print '<table class="border nobottom" width="100%">';
	print '<tr><td class="fieldrequired titlefield">'.$langs->trans('Ref').'</td>';
	print '<td><input type="text" name="ref" value="'.$object->ref.'" size="30"></td></tr>';

	print '<tr><td class="fieldrequired">'.$langs->trans('Client').'</td><td>';
	print $form->select_company($object->fk_soc, 'fk_soc', '', 1);
	print '</td></tr>';

	print '</table>';
	print '</fieldset>';
	print '</div>';
	print '</div>';

	print '<div class="fichehalfright">';
	print '<div class="boxshadow withpadding">';
	print '<fieldset><legend>'.$langs->trans('Dates').'</legend>';

	print '<table class="border nobottom" width="100%">';
	print '<tr><td class="titlefield">'.$langs->trans('DateDepot').'</td>';
	print '<td>';
	$form->select_date($object->date_depot > 0 ? $object->date_depot : dol_now(), 'date_depot', 0, 0, 0, 'create');
	print '</td></tr>';

	print '<tr><td>'.$langs->trans('DatePromesse').'</td><td>';
	$form->select_date($object->date_promesse, 'date_promesse', 0, 0, 0, 'create');
	print '</td></tr>';
	print '</table>';
	print '</fieldset>';
	print '</div>';
	print '</div>';
	print '</div>';

	print '<div class="clearboth"></div>';
	print '<div class="boxshadow withpadding" style="margin-top: 20px;">';
	print '<fieldset><legend>'.$langs->trans('NotePublic').'</legend>';
	print '<textarea name="note_public" rows="4" style="width: 100%;">'.$object->note_public.'</textarea>';
	print '</fieldset>';
	print '</div>';

	print '<div style="margin-top: 20px; text-align: center;">';
	print '<input type="submit" value="'.$langs->trans('Create').'" class="button">';
	print ' <input type="button" value="'.$langs->trans('Cancel').'" class="button" onclick="history.back()">';
	print '</div>';

	print '</form>';
} elseif ($id > 0 && $object->id > 0) {
	$head = pressing_prepare_head($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('PresssingCard'), 0, 'fa-tshirt');

	print '<div class="fichecontainer">';
	print '<div class="underbanner clearboth"></div>';

	print '<div class="fichehandle" style="background: #f8f9fa; padding: 15px; border-radius: 3px;">';
	print '<h2>'.$object->ref.'</h2>';
	print '<table class="border" style="width: 100%; max-width: 500px;">';

	print '<tr><td class="label">'.$langs->trans('Ref').'</td><td>'.$object->ref.'</td></tr>';
	print '<tr><td class="label">'.$langs->trans('Statut').'</td><td>'.$object->getLibStatut(0).'</td></tr>';

	$soc = new Societe($db);
	$soc->fetch($object->fk_soc);
	print '<tr><td class="label">'.$langs->trans('Client').'</td><td>'.$soc->getNomUrl(1).'</td></tr>';

	if ($object->fk_facture > 0) {
		print '<tr><td class="label">'.$langs->trans('Invoice').'</td><td>';
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$facture = new Facture($db);
		$facture->fetch($object->fk_facture);
		print $facture->getNomUrl(1);
		print '</td></tr>';
	}

	print '<tr><td class="label">'.$langs->trans('DateDepot').'</td><td>'.dol_print_date($object->date_depot, 'day').'</td></tr>';

	if ($object->date_promesse) {
		print '<tr><td class="label">'.$langs->trans('DatePromesse').'</td><td>'.dol_print_date($object->date_promesse, 'day').'</td></tr>';
	}

	if ($object->date_livraison) {
		print '<tr><td class="label">'.$langs->trans('DateLivraison').'</td><td>'.dol_print_date($object->date_livraison, 'day').'</td></tr>';
	}

	print '</table>';
	print '</div>';

	print '<h3 style="margin-top: 30px;">'.$langs->trans('Articles').'</h3>';
	print '<table class="tagtable centpercent">';
	print '<thead><tr>';
	print '<th>Description</th>';
	print '<th>Type</th>';
	print '<th>Couleur</th>';
	print '<th>Dimensions (L×l)</th>';
	print '<th>Prix €</th>';
	print '<th>Entrepôt</th>';
	print '<th>Statut</th>';
	print '<th>Action</th>';
	print '</tr></thead><tbody>';

	$object->fetchLines();

	if (count($object->lines) > 0) {
		foreach ($object->lines as $line) {
			print '<tr>';
			print '<td>'.$line->description.'</td>';
			print '<td>'.$line->type_article.'</td>';
			print '<td>'.$line->couleur.'</td>';
			print '<td>'.$line->longueur.' × '.$line->largeur.' cm</td>';
			print '<td>'.$line->prix_unitaire.' €</td>';

			if (!empty($line->fk_entrepot)) {
				$entrepot = new Entrepot($db);
				$entrepot->fetch($line->fk_entrepot);
				print '<td>'.$entrepot->label.'</td>';
			} else {
				print '<td>-</td>';
			}

			print '<td>'.$line->getLibStatut(0).'</td>';
			print '<td>';
			if ($object->fk_statut != PressingCommande::STATUS_DELIVERED) {
				print '<a href="?id='.$id.'&action=updateline&lineid='.$line->id.'&statut_article='.(($line->fk_statut + 1) % 4).'" class="button">';
				print '<i class="fa fa-chevron-right"></i></a> ';
				print '<a href="?id='.$id.'&action=deleteline&lineid='.$line->id.'" class="button" onclick="return confirm(\'Supprimer ?\');">';
				print '<i class="fa fa-trash"></i></a>';
			}
			print '</td>';
			print '</tr>';
		}
	}

	print '</tbody></table>';

	if ($object->fk_statut != PressingCommande::STATUS_DELIVERED && $usercancreate) {
		print '<div style="margin-top: 15px;"><a href="#" onclick="toggleAddLine(); return false;" class="button">'.$langs->trans('AddArticle').'</a></div>';
		print '<div id="addline" style="display: none; margin-top: 20px; background: #f8f9fa; padding: 15px; border-radius: 3px;">';
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'" onsubmit="calculatePrice()">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="addline">';
		print '<table class="border centpercent">';
		print '<tr><td class="fieldrequired">'.$langs->trans('Description').'</td><td><input type="text" name="description" size="40"></td></tr>';
		print '<tr><td>'.$langs->trans('Type').'</td><td><input type="text" name="type_article" size="20"></td></tr>';
		print '<tr><td>'.$langs->trans('Couleur').'</td><td><input type="text" name="couleur" size="20"></td></tr>';
		print '<tr><td class="fieldrequired">'.$langs->trans('Longueur').' (cm)</td><td><input type="number" name="longueur" step="0.01" min="0" size="10" onchange="calculatePrice()"></td></tr>';
		print '<tr><td class="fieldrequired">'.$langs->trans('Largeur').' (cm)</td><td><input type="number" name="largeur" step="0.01" min="0" size="10" onchange="calculatePrice()"></td></tr>';
		print '<tr><td>'.$langs->trans('PrixUnitaire').' €</td><td><input type="number" name="prix_unitaire" step="0.01" min="0" id="prix_calc" size="10" readonly></td></tr>';
		print '<tr><td>'.$langs->trans('Entrepot').'</td><td>';
		print $form->select_warehouse(0, 'fk_entrepot', '', 1);
		print '</td></tr>';
		print '</table>';
		print '<input type="submit" value="'.$langs->trans('Add').'" class="button">';
		print '</form>';
		print '</div>';
	}

	print '<div class="tabsAction">';

	if ($object->fk_statut == PressingCommande::STATUS_DRAFT) {
		print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=validate&token='.newToken().'" class="butAction">'.$langs->trans('Validate').'</a>';
	}

	if ($object->fk_statut == PressingCommande::STATUS_RECEIVED) {
		print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=inprogress&token='.newToken().'" class="butAction">Mettre en cours</a>';
	}

	if ($object->fk_statut == PressingCommande::STATUS_INPROGRESS) {
		print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=ready&token='.newToken().'" class="butAction">Marquer prêt</a>';
	}

	if ($object->fk_statut == PressingCommande::STATUS_DONE) {
		$object->fetchLines();
		if ($object->getLinesStatuts()) {
			print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=deliver&token='.newToken().'" class="butAction">'.$langs->trans('LivrerPressing').'</a>';
		} else {
			print '<a class="butActionRefused classfortooltip" href="#" title="'.$langs->trans('PressingItemsNotReady').'">'.$langs->trans('LivrerPressing').'</a>';
		}
	}

	if ($usercandelete) {
		print dolGetButtonAction($langs->trans('Delete'), '', 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken(), 'delete', 1);
	}

	print '</div>';

	print '</div>';

	print dol_get_fiche_end();

	print '<script type="text/javascript">';
	print 'function toggleAddLine() { document.getElementById("addline").style.display = (document.getElementById("addline").style.display == "none" ? "block" : "none"); }';
	print 'function calculatePrice() {';
	print '  var longueur = parseFloat(document.querySelector("input[name=\"longueur\"]").value || 0);';
	print '  var largeur = parseFloat(document.querySelector("input[name=\"largeur\"]").value || 0);';
	print '  if (longueur > 0 && largeur > 0) {';
	print '    var prix = Math.round((longueur * largeur) / 100 * 100) / 100;';
	print '    document.getElementById("prix_calc").value = prix.toFixed(2);';
	print '  }';
	print '}';
	print '</script>';
}

llxFooter();
$db->close();
