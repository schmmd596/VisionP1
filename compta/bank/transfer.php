<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2019 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2015 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2012	   Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 * Copyright (C) 2015      Marcos García        <marcosgdf@gmail.com>
 * Copyright (C) 2018-2024  Frédéric France      <frederic.france@free.fr>
 * Copyright (C) 2023      Maxime Nicolas          <maxime@oarces.com>
 * Copyright (C) 2023      Benjamin GREMBI         <benjamin@oarces.com>
 * Copyright (C) 2024-2025	MDW						<mdeweerd@users.noreply.github.com>
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
 *    \file       htdocs/compta/bank/transfer.php
 *    \ingroup    bank
 *    \brief      Page for entering a bank transfer
 */

// Load Dolibarr environment
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/bank.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array('banks', 'categories', 'multicurrency'));

$action = GETPOST('action', 'aZ09');
$transfer_mode = GETPOST('transfer_mode', 'aZ09');
if (empty($transfer_mode)) {
	$transfer_mode = 'amount';
}

$hookmanager->initHooks(array('banktransfer'));

$socid = 0;
if ($user->socid > 0) {
	$socid = $user->socid;
}
if (!$user->hasRight('banque', 'transfer')) {
	accessforbidden();
}

$MAXLINESFORTRANSFERT = 20;

$error = 0;


/*
 * Actions
 */

$parameters = array('socid' => $socid);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
if ($action == 'add' && $user->hasRight('banque', 'transfer')) {
	$langs->load('errors');
	$i = 1;

	$dateo = array();
	$label = array();
	$amount = array();
	$amountto = array();
	$accountfrom = array();
	$accountto = array();
	$type = array();
	$tabnum = array();
	$maxtab = 1;

	while ($i < $MAXLINESFORTRANSFERT) {
		$dateo[$i] = dol_mktime(12, 0, 0, GETPOSTINT($i.'_month'), GETPOSTINT($i.'_day'), GETPOSTINT($i.'_year'));
		$label[$i] = GETPOST($i.'_label', 'alpha');
		$amount[$i] = price2num(GETPOST($i.'_amount', 'alpha'), 'MT', 2);
		$amountto[$i] = price2num(GETPOST($i.'_amountto', 'alpha'), 'MT', 2);
		$accountfrom[$i] = GETPOSTINT($i.'_account_from');
		$accountto[$i] = GETPOSTINT($i.'_account_to');
		$type[$i] = GETPOSTINT($i.'_type');

		// In transaction mode, fetch amount, type and label from the selected bank transaction
		if ($transfer_mode == 'transaction') {
			$transactionid = GETPOSTINT($i.'_transaction_id');
			if ($transactionid > 0) {
				$sqltr  = "SELECT b.rowid, b.label, b.amount, b.dateo, b.fk_type FROM ".MAIN_DB_PREFIX."bank b";
				$sqltr .= " WHERE b.rowid = ".((int) $transactionid);
				$sqltr .= " AND b.fk_account = ".((int) $accountfrom[$i]);
				$resqltr = $db->query($sqltr);
				if ($resqltr && $db->num_rows($resqltr) > 0) {
					$objtr = $db->fetch_object($resqltr);
					$amount[$i] = (float) price2num(abs((float) $objtr->amount), 'MT', 2);
					$type[$i]   = $objtr->fk_type; // use the transaction's own payment type
					if (empty($label[$i])) {
						$label[$i] = $objtr->label;
					}
					if (empty($dateo[$i])) {
						$dateo[$i] = $db->jdate($objtr->dateo);
					}
				}
				if ($resqltr) {
					$db->free($resqltr);
				}
			}
		}

		$tabnum[$i] = 0;
		if (!empty($label[$i]) || !($amount[$i] <= 0) || !($accountfrom[$i] < 0) || !($accountto[$i]  < 0)) {
			$tabnum[$i] = 1;
			$maxtab = $i;
		}
		$i++;
	}

	$db->begin();

	$n = 1;
	while ($n < $MAXLINESFORTRANSFERT) {
		if ($tabnum[$n] === 1) {
			if ($accountfrom[$n] < 0) {
				$error++;
				setEventMessages($langs->trans("ErrorFieldRequired", '#'.$n. ' ' .$langs->transnoentities("TransferFrom")), null, 'errors');
			}
			if ($accountto[$n] < 0) {
				$error++;
				setEventMessages($langs->trans("ErrorFieldRequired", '#'.$n. ' ' .$langs->transnoentities("TransferTo")), null, 'errors');
			}
			if ($transfer_mode != 'transaction' && !$type[$n]) {
				$error++;
				setEventMessages($langs->trans("ErrorFieldRequired", '#'.$n. ' ' .$langs->transnoentities("Type")), null, 'errors');
			}
			if (!$dateo[$n]) {
				$error++;
				setEventMessages($langs->trans("ErrorFieldRequired", '#'.$n. ' ' .$langs->transnoentities("Date")), null, 'errors');
			}

			if (!($label[$n])) {
				$error++;
				setEventMessages($langs->trans("ErrorFieldRequired", '#'.$n. ' ' . $langs->transnoentities("Description")), null, 'errors');
			}
			if (!($amount[$n])) {
				$error++;
				setEventMessages($langs->trans("ErrorFieldRequired", '#'.$n. ' ' .$langs->transnoentities("Amount")), null, 'errors');
			}

			$tmpaccountfrom = new Account($db);
			$tmpaccountfrom->fetch(GETPOSTINT($n.'_account_from'));

			$tmpaccountto = new Account($db);
			$tmpaccountto->fetch(GETPOSTINT($n.'_account_to'));

			if ($transfer_mode == 'transaction' || $tmpaccountto->currency_code == $tmpaccountfrom->currency_code) {
				// In transaction mode, always mirror the amount (no multicurrency input)
				$amountto[$n] = $amount[$n];
			} else {
				if (!$amountto[$n]) {
					$error++;
					setEventMessages($langs->trans("ErrorFieldRequired", '#'.$n.' '.$langs->transnoentities("AmountToOthercurrency")), null, 'errors');
				}
			}
			if ($amountto[$n] < 0) {
				$error++;
				setEventMessages($langs->trans("AmountMustBePositive").' #'.$n, null, 'errors');
			}

			if ($tmpaccountto->id == $tmpaccountfrom->id) {
				$error++;
				setEventMessages($langs->trans("ErrorFromToAccountsMustDiffers").' #'.$n, null, 'errors');
			}

			if (!$error) {
				$bank_line_id_from = 0;
				$bank_line_id_to = 0;
				$result = 0;

				// By default, electronic transfer from bank to bank
				$typefrom = $type[$n];
				$typeto = $type[$n];
				if ($tmpaccountto->type == Account::TYPE_CASH || $tmpaccountfrom->type == Account::TYPE_CASH) {
					// This is transfer of change
					$typefrom = 'LIQ';
					$typeto = 'LIQ';
				}

				if (!$error) {
					$bank_line_id_from = $tmpaccountfrom->addline($dateo[$n], $typefrom, $label[$n], (float) price2num(-1 * (float) $amount[$n]), '', 0, $user);
				}
				if (!($bank_line_id_from > 0)) {
					$error++;
				}
				if (!$error) {
					$bank_line_id_to = $tmpaccountto->addline($dateo[$n], $typeto, $label[$n], (float) $amountto[$n], '', 0, $user);
				}
				if (!($bank_line_id_to > 0)) {
					$error++;
				}

				if (!$error) {
					$result = $tmpaccountfrom->add_url_line($bank_line_id_from, $bank_line_id_to, DOL_URL_ROOT.'/compta/bank/line.php?rowid=', '(banktransfert)', 'banktransfert');
				}
				if (!($result > 0)) {
					$error++;
				}
				if (!$error) {
					$result = $tmpaccountto->add_url_line($bank_line_id_to, $bank_line_id_from, DOL_URL_ROOT.'/compta/bank/line.php?rowid=', '(banktransfert)', 'banktransfert');
				}
				if (!($result > 0)) {
					$error++;
				}

				// In transaction mode: update the payment record so the invoice
				// now references the destination bank account (bank_line_id_to).
				if (!$error && $transfer_mode == 'transaction') {
					$transactionid_used = GETPOSTINT($n.'_transaction_id');
					if ($transactionid_used > 0) {
						// Find all payment-type links on the original transaction
						$sqlu  = "SELECT url_id, type FROM ".MAIN_DB_PREFIX."bank_url";
						$sqlu .= " WHERE fk_bank = ".((int) $transactionid_used);
						$sqlu .= " AND type IN ('payment','payment_supplier')";
						$resqlu = $db->query($sqlu);
						if ($resqlu) {
							while ($obju = $db->fetch_object($resqlu)) {
								if ($obju->type == 'payment') {
									$table = MAIN_DB_PREFIX.'paiement';
								} else {
									$table = MAIN_DB_PREFIX.'paiementfourn';
								}
								// Point the payment's fk_bank to the new destination bank line
								$sqlup  = "UPDATE ".$table;
								$sqlup .= " SET fk_bank = ".((int) $bank_line_id_to);
								$sqlup .= " WHERE fk_bank = ".((int) $transactionid_used);
								$sqlup .= " AND rowid = ".((int) $obju->url_id);
								if (!$db->query($sqlup)) {
									$error++;
									setEventMessages($db->lasterror(), null, 'errors');
								}
							}
							$db->free($resqlu);
						}
					}
				}

				if (!$error) {
					$mesg = $langs->trans("TransferFromToDone", '{s1}', '{s2}', $amount[$n], $langs->transnoentitiesnoconv("Currency".$conf->currency));
					$mesg = str_replace('{s1}', '<a href="bankentries_list.php?id='.$tmpaccountfrom->id.'&sortfield=b.datev,b.dateo,b.rowid&sortorder=desc">'.$tmpaccountfrom->label.'</a>', $mesg);
					$mesg = str_replace('{s2}', '<a href="bankentries_list.php?id='.$tmpaccountto->id.'">'.$tmpaccountto->label.'</a>', $mesg);
					setEventMessages($mesg, null, 'mesgs');
				} else {
					$error++;
					setEventMessages($tmpaccountfrom->error.' '.$tmpaccountto->error, null, 'errors');
				}
			}
		}
		$n++;
	}

	if (!$error) {
		$db->commit();

		header("Location: ".DOL_URL_ROOT.'/compta/bank/transfer.php');
		exit;
	} else {
		$db->rollback();
	}
}


/*
 * View
 */

$form = new Form($db);

$help_url = 'EN:Module_Banks_and_Cash|FR:Module_Banques_et_Caisses|ES:M&oacute;dulo_Bancos_y_Cajas';
$title = $langs->trans('MenuBankInternalTransfer');

llxHeader('', $title, $help_url);


print '<script type="text/javascript">
        	$(document).ready(function () {

				/* ---- Transfer mode toggle (Montant / Transaction) ---- */
				function toggleTransferMode(mode) {
					if (mode === "transaction") {
						$(".amount-mode-cell, .amount-mode-header").hide();
						$(".type-mode-cell, .type-mode-header").hide();
						$(".transaction-mode-cell, .transaction-mode-header").show();
						$("#tr_date_filter_bar").show();
						$(".multicurrency").hide();
					} else {
						$(".transaction-mode-cell, .transaction-mode-header").hide();
						$("#tr_date_filter_bar").hide();
						$(".amount-mode-cell, .amount-mode-header").show();
						$(".type-mode-cell, .type-mode-header").show();
						init_page(1);
					}
				}

				$("input[name=\"transfer_mode\"]").change(function() {
					toggleTransferMode($(this).val());
				});

				/* ---- AJAX: load transactions for a given bank account ---- */
				function loadTransactions(lineIndex, accountId) {
					var select = $("#select" + lineIndex + "_transaction");
					select.empty().append("<option value=\"\">-- Sélectionner une transaction --</option>");
					if (!accountId) return;

					var dateFrom = $("#tr_filter_date_from").val();
					var dateTo   = $("#tr_filter_date_to").val();

					$.ajax({
						url: "ajax/getbanktransactions.php",
						type: "POST",
						data: {
							account_id: accountId,
							token:      $("input[name=\"token\"]").val(),
							date_from:  dateFrom,
							date_to:    dateTo
						},
						dataType: "json",
						success: function(data) {
							$.each(data, function(idx, t) {
								var amountStr = "+" + t.amount;
								var optLabel  = t.date + " | " + t.label + " (" + amountStr + ")";
								var opt = $("<option></option>")
									.val(t.id)
									.attr("data-amount", t.amount)
									.attr("data-label", t.label)
									.attr("data-date-raw", t.date_raw)
									.attr("data-date-display", t.date)
									.text(optLabel);
								select.append(opt);
							});
						},
						error: function() {
							console.error("Erreur chargement transactions banque");
						}
					});
				}

				/* ---- Reload all visible transaction dropdowns (used when date filter changes) ---- */
				function reloadAllTransactions() {
					$(".transaction-select").each(function() {
						var id = $(this).attr("id"); // e.g. "select1_transaction"
						var lineIndex = id.replace("select", "").replace("_transaction", "");
						// find the "from" account for this line
						var accountId = $("#select" + lineIndex + "_account_from").val();
						if (!accountId) {
							// try the select2 hidden input
							accountId = $("select[name=\"" + lineIndex + "_account_from\"]").val();
						}
						if (accountId) {
							loadTransactions(lineIndex, accountId);
						}
					});
				}

				/* ---- Date filter change triggers reload ---- */
				$("#tr_filter_date_from, #tr_filter_date_to").on("change", function() {
					reloadAllTransactions();
				});

				/* ---- When a transaction is selected: auto-fill label ---- */
				$(document).on("change", ".transaction-select", function() {
					var name = $(this).attr("id"); // e.g. "select1_transaction"
					var lineIndex = name.replace("select", "").replace("_transaction", "");
					var selected = $(this).find("option:selected");
					var lbl = selected.attr("data-label");
					if (lbl) {
						$("input[name=\"" + lineIndex + "_label\"]").val(lbl);
					}
				});

				/* ---- Bank account change handler ---- */
    	  		$(".selectbankaccount").change(function() {
					var fieldname = $(this).attr("name");
					var i = fieldname.replace("_account_to", "").replace("_account_from", "");
					init_page(i);

					// If in transaction mode and "from" account changed, reload transactions
					if (fieldname.indexOf("_account_from") !== -1) {
						var mode = $("input[name=\"transfer_mode\"]:checked").val();
						if (mode === "transaction") {
							loadTransactions(i, $(this).val());
						}
					}
				});

				function init_page(i) {
					var atleast2differentcurrency = false;

					$(".selectbankaccount").each(function( index ) {
						// Scan all line i and set atleast2differentcurrency if there is 2 different values among all lines
	        			var account1 = $("#select"+index+"_account_from").val();
	        			var account2 = $("#select"+index+"_account_to").val();
						var currencycode1 = $("#select"+index+"_account_from option:selected").attr("data-currency-code");
						var currencycode2 = $("#select"+index+"_account_to option:selected").attr("data-currency-code");

						atleast2differentcurrency = (currencycode2!==currencycode1 && currencycode1 !== undefined && currencycode2 !== undefined && currencycode2!=="" && currencycode1!=="");
						if (atleast2differentcurrency) {
							return false;
						}
					});

					// Only show multicurrency if we are in amount mode
					var currentMode = $("input[name=\"transfer_mode\"]:checked").val();
					if (currentMode !== "transaction") {
						if (atleast2differentcurrency) {
	        				$(".multicurrency").show();
	        			} else {
							$(".multicurrency").hide();
						}
					}

					// Show all lines with view=view
					$("select").each(function() {
						if( $(this).attr("view")){
							$(this).closest("tr").removeClass("hidejs").removeClass("hideobject");
						}
					});
        		}

				// Initialize on page load
				var initialMode = $("input[name=\"transfer_mode\"]:checked").val() || "amount";
				toggleTransferMode(initialMode);
        	});
    		</script>';


print load_fiche_titre($langs->trans("MenuBankInternalTransfer"), '', 'bank_account');

print '<span class="opacitymedium">'.$langs->trans("TransferDesc").'</span>';
print '<br><br>';

print '<form name="add" method="post" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="add">';

// Mode selector: Montant (default) vs Transaction
print '<div class="center" style="margin-bottom:16px;">';
print '<label style="margin-right:24px; font-size:1.05em; cursor:pointer;">';
print '<input type="radio" name="transfer_mode" id="mode_amount" value="amount"'.($transfer_mode !== 'transaction' ? ' checked' : '').'> ';
print '<strong>'.$langs->trans("Amount").'</strong>';
print '</label>';
print '<label style="font-size:1.05em; cursor:pointer;">';
print '<input type="radio" name="transfer_mode" id="mode_transaction" value="transaction"'.($transfer_mode === 'transaction' ? ' checked' : '').'> ';
print '<strong>Transaction</strong>';
print '</label>';
print '</div>';

// Date filter bar — visible only in Transaction mode
print '<div id="tr_date_filter_bar" class="transaction-mode-header" style="display:none; margin-bottom:14px; text-align:center;">';
print '<span style="font-weight:600; margin-right:10px;">Filtrer les transactions :</span>';
print '<label style="margin-right:6px;">Du</label>';
print '<input type="date" id="tr_filter_date_from" class="flat" style="margin-right:14px;">';
print '<label style="margin-right:6px;">Au</label>';
print '<input type="date" id="tr_filter_date_to" class="flat">';
print '</div>';

print '<div>';

print '<div class="div-table-responsive-no-min">';
print '<table id="tablemouvbank" class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<th>'.$langs->trans("TransferFrom").'</th>';
print '<th class="transaction-mode-header">Transaction</th>';
print '<th>'.$langs->trans("TransferTo").'</th>';
print '<th class="type-mode-cell type-mode-header">'.$langs->trans("Type").'</th>';
print '<th>'.$langs->trans("Date").'</th>';
print '<th>'.$langs->trans("Description").'</th>';
print '<th class="right amount-mode-header">'.$langs->trans("Amount").'</th>';
print '<td class="hideobject multicurrency right">'.$langs->trans("AmountToOthercurrency").'</td>';
print '</tr>';

for ($i = 1 ; $i < $MAXLINESFORTRANSFERT; $i++) {
	$label = '';
	$amount = '';
	$amountto = '';

	if ($error) {
		$label = GETPOST($i.'_label', 'alpha');
		$amount = GETPOST($i.'_amount', 'alpha');
		$amountto = GETPOST($i.'_amountto', 'alpha');
	}

	if ($i == 1) {
		$classi = 'numvir number'.$i;
		$classi .= ' active';
	} else {
		$classi = 'numvir number'.$i;
		$classi .= ' hidejs hideobject';
	}

	// De (From)
	print '<tr class="oddeven nowraponall '.$classi.'"><td>';
	print img_picto('', 'bank_account', 'class="paddingright"');
	$form->select_comptes(($error ? GETPOSTINT($i.'_account_from') : ''), $i.'_account_from', 0, '', 1, '', isModEnabled('multicurrency') ? 1 : 0, 'minwidth100');
	print '</td>';

	// Transaction select (visible in "Transaction" mode) — placed between De and Vers
	print '<td class="transaction-mode-cell">';
	print '<select name="'.$i.'_transaction_id" id="select'.$i.'_transaction" class="flat minwidth250 transaction-select">';
	print '<option value="">-- Sélectionner une transaction --</option>';
	// Re-render options server-side when returning after a validation error
	if ($error && $transfer_mode === 'transaction' && GETPOSTINT($i.'_account_from') > 0) {
		$sqltrl  = "SELECT b.rowid, b.label, b.amount, b.dateo FROM ".MAIN_DB_PREFIX."bank b";
		$sqltrl .= " WHERE b.fk_account = ".((int) GETPOSTINT($i.'_account_from'));
		$sqltrl .= " ORDER BY b.dateo DESC, b.rowid DESC LIMIT 300";
		$resqltrl = $db->query($sqltrl);
		if ($resqltrl) {
			while ($objtrl = $db->fetch_object($resqltrl)) {
				$selattr = (GETPOSTINT($i.'_transaction_id') == $objtrl->rowid) ? ' selected' : '';
				$amtDisplay = ($objtrl->amount >= 0 ? '+' : '').price($objtrl->amount);
				print '<option value="'.$objtrl->rowid.'"'.$selattr
					.' data-amount="'.dol_escape_htmltag($objtrl->amount).'"'
					.' data-label="'.dol_escape_htmltag($objtrl->label).'">';
				print dol_print_date($db->jdate($objtrl->dateo), 'day').' | '.dol_escape_htmltag($objtrl->label).' ('.$amtDisplay.')';
				print '</option>';
			}
			$db->free($resqltrl);
		}
	}
	print '</select>';
	print '</td>';

	// Vers (To)
	print '<td class="nowraponall">';
	print img_picto('', 'bank_account', 'class="paddingright"');
	$form->select_comptes(($error ? GETPOSTINT($i.'_account_to') : ''), $i.'_account_to', 0, '', 1, '', isModEnabled('multicurrency') ? 1 : 0, 'minwidth100');
	print "</td>\n";

	// Payment mode (hidden in transaction mode — type is taken from the transaction itself)
	print '<td class="nowraponall type-mode-cell">';
	$idpaymentmodetransfer = dol_getIdFromCode($db, 'VIR', 'c_paiement');
	$form->select_types_paiements(($error ? GETPOST($i.'_type', 'aZ09') : $idpaymentmodetransfer), $i.'_type', '', 0, 1, 0, 0, 1, 'minwidth100');
	print "</td>\n";

	// Date
	print '<td class="nowraponall">';
	print $form->selectDate((!empty($dateo[$i]) ? $dateo[$i] : ''), $i.'_', 0, 0, 0, 'add');
	print "</td>\n";

	// Description
	print '<td><input name="'.$i.'_label" class="flat quatrevingtpercent selectjs" type="text" value="'.dol_escape_htmltag($label).'"></td>';

	// Amount (visible in "Montant" mode only)
	print '<td class="right amount-mode-cell"><input name="'.$i.'_amount" class="flat right selectjs" type="text" size="6" value="'.dol_escape_htmltag($amount).'"></td>';

	// AmountToOthercurrency (Montant mode only)
	print '<td class="hideobject multicurrency right"><input name="'.$i.'_amountto" class="flat right" type="text" size="6" value="'.dol_escape_htmltag($amountto).'"></td>';

	print '</tr>';
}

print '</table>';
print '</div>';
print '</div>';
print '<div id="btncont" style="display: flex; align-items: center">';
print '<a id="btnincrement" style="margin-left:35%" class="btnTitle btnTitlePlus" onclick="increment()" title="'.dol_escape_htmltag($langs->trans("Add")).'">
		<span class="fa fa-plus-circle valignmiddle btnTitle-icon">
		</span>
	   </a>';
print '<br><div  class=""><input type="submit" class="button" value="'.$langs->trans("Save").'"></div>';
print '</div>';

print '</form>';

print '<script type="text/javascript">
			function increment() {
				console.log("We click to show next line");
				$(".numvir").nextAll(".hidejs:first").removeClass("hidejs").removeClass("hideobject").addClass("active").show();
			}
		</script>
	 ';

// End of page
llxFooter();

$db->close();
