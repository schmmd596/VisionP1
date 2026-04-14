<?php
/**
 * Copyright (C) 2018    	Andreu Bisquerra   		<jove@bisquerra.com>
 * Copyright (C) 2021    	Nicolas ZABOURI    		<info@inovea-conseil.com>
 * Copyright (C) 2022-2023	Christophe Battarel		<christophe.battarel@altairis.fr>
 * Copyright (C) 2024-2025	MDW						<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024		Frédéric France			<frederic.france@free.fr>
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
 *    \file       htdocs/takepos/invoice.php
 *    \ingroup    takepos
 *    \brief      Page to generate section with list of lines
 */

// if (! defined('NOREQUIREUSER')) 		define('NOREQUIREUSER', '1'); 		// Not disabled cause need to load personalized language
// if (! defined('NOREQUIREDB')) 		define('NOREQUIREDB', '1'); 		// Not disabled cause need to load personalized language
// if (! defined('NOREQUIRESOC')) 		define('NOREQUIRESOC', '1');
// if (! defined('NOREQUIRETRAN')) 		define('NOREQUIRETRAN', '1');

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

// Load Dolibarr environment
if (!defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
	require '../main.inc.php';
}
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Societe $mysoc
 * @var Translate $langs
 * @var User $user
 */

$hookmanager->initHooks(array('takeposinvoice'));

$langs->loadLangs(array("companies", "commercial", "bills", "cashdesk", "stocks", "banks"));

$action = GETPOST('action', 'aZ09');
$idproduct = GETPOSTINT('idproduct');
$place = (GETPOST('place', 'aZ09') ? GETPOST('place', 'aZ09') : 0); // $place is id of table for Bar or Restaurant
$placeid = 0; // $placeid is ID of invoice
$mobilepage = GETPOST('mobilepage', 'alpha');

// Terminal is stored into $_SESSION["takeposterminal"];

if (!$user->hasRight('takepos', 'run') && !defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
	accessforbidden('No permission to use the TakePOS');
}

if ((getDolGlobalString('TAKEPOS_PHONE_BASIC_LAYOUT') == 1 && $conf->browser->layout == 'phone') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
	// DIRECT LINK TO THIS PAGE FROM MOBILE AND NO TERMINAL SELECTED
	if ($_SESSION["takeposterminal"] == "") {
		if (getDolGlobalString('TAKEPOS_NUM_TERMINALS') == "1") {
			$_SESSION["takeposterminal"] = 1;
		} else {
			header("Location: ".DOL_URL_ROOT."/takepos/index.php");
			exit;
		}
	}
}


$takeposterminal = isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '';

// When session has expired (selected terminal has been lost from session), redirect to the terminal selection.
if (empty($takeposterminal)) {
	if (getDolGlobalInt('TAKEPOS_NUM_TERMINALS') == 1) {
		$_SESSION["takeposterminal"] = 1; // Use terminal 1 if there is only 1 terminal
		$takeposterminal = 1;
	} elseif (!empty($_COOKIE["takeposterminal"])) {
		$_SESSION["takeposterminal"] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_COOKIE["takeposterminal"]); // Restore takeposterminal from previous session
		$takeposterminal = $_SESSION["takeposterminal"];
	} else {
		print <<<SCRIPT
<script language="javascript">
	$( document ).ready(function() {
		ModalBox('ModalTerminal');
	});
</script>
SCRIPT;
		exit;
	}
}


/**
 * Abort invoice creation with a given error message
 *
 * @param   string  $message        Message explaining the error to the user
 * @return	never
 */
function fail($message)
{
	header($_SERVER['SERVER_PROTOCOL'].' 500 Internal Server Error', true, 500);
	die($message);
}



$number = (float) GETPOST('number', 'alpha');
$idline = GETPOSTINT('idline');
$selectedline = GETPOSTINT('selectedline');
$desc = GETPOST('desc', 'alphanohtml');
$pay = GETPOST('pay', 'aZ09');
$amountofpayment = GETPOSTFLOAT('amount');

$invoiceid = GETPOSTINT('invoiceid');

$paycode = $pay;
if ($pay == 'cash') {
	$paycode = 'LIQ'; // For backward compatibility
}
if ($pay == 'card') {
	$paycode = 'CB'; // For backward compatibility
}
if ($pay == 'cheque') {
	$paycode = 'CHQ'; // For backward compatibility
}

// Retrieve paiementid
$paiementid = 0;
if ($paycode) {
	$sql = "SELECT id FROM ".MAIN_DB_PREFIX."c_paiement";
	$sql .= " WHERE entity IN (".getEntity('c_paiement').")";
	$sql .= " AND code = '".$db->escape($paycode)."'";
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj) {
			$paiementid = $obj->id;
		}
	}
}

// Vente / Achat mode
$takeposmode = (!empty($_SESSION['takeposmode'])) ? $_SESSION['takeposmode'] : 'vente';
$invoiceclass = ($takeposmode === 'achat') ? 'FactureFournisseur' : 'Facture';

$invoice = new $invoiceclass($db);
$achat_prov_ref  = 'POSACH-'.$takeposterminal.'-'.$place;   // stable provisional ref for achat
$achat_session_key = 'takepos_achat_place_'.$takeposterminal.'_'.$place;

// For achat mode: clean up any stuck (PROV) or (PROV-*) placeholder records (DELETE lines first because of FK)
// Note: FactureFournisseur::create() now uses CONCAT('(PROV-', CONNECTION_ID(), ')') to avoid unique-key
// conflicts when two requests create simultaneously. We clean both the old '(PROV)' and new '(PROV-%)' forms.
if ($takeposmode === 'achat' && $invoiceid <= 0) {
	$res_prov = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."facture_fourn WHERE ref='(PROV)' OR ref LIKE '(PROV-%)'");
	if ($res_prov) {
		while ($obj_prov = $db->fetch_object($res_prov)) {
			$db->query("DELETE FROM ".MAIN_DB_PREFIX."facture_fourn_det WHERE fk_facture_fourn=".((int) $obj_prov->rowid));
		}
		$db->query("DELETE FROM ".MAIN_DB_PREFIX."facture_fourn WHERE ref='(PROV)' OR ref LIKE '(PROV-%)'");
	}
}

if ($invoiceid > 0) {
	$ret = $invoice->fetch($invoiceid);
} elseif ($takeposmode === 'achat') {
	// Achat: first try session-stored ID, then try the stable provisional ref
	if (!empty($_SESSION[$achat_session_key])) {
		$ret = $invoice->fetch((int) $_SESSION[$achat_session_key]);
		if ($ret <= 0) {
			unset($_SESSION[$achat_session_key]);  // stale, clear it
			$ret = $invoice->fetch(0, $achat_prov_ref);
		}
	} else {
		$ret = $invoice->fetch(0, $achat_prov_ref);
	}
	if ($ret > 0) {
		$_SESSION[$achat_session_key] = $invoice->id;  // keep session in sync
	}
} else {
	$ret = $invoice->fetch(0, '(PROV-POS'.$takeposterminal.'-'.$place.')');
}
if ($ret > 0) {
	$placeid = $invoice->id;
}

$constforcompanyid = 'CASHDESK_ID_THIRDPARTY'.$takeposterminal;

$soc = new Societe($db);
if ($invoice->socid > 0) {
	$soc->fetch($invoice->socid);
} else {
	$soc->fetch(getDolGlobalInt($constforcompanyid));
}

// Assign a default project, if relevant
if (isModEnabled('project') && getDolGlobalInt("CASHDESK_ID_PROJECT".$takeposterminal)) {
	$invoice->fk_project = getDolGlobalInt("CASHDESK_ID_PROJECT".$takeposterminal);
}

// Change the currency of invoice if it was modified
if (isModEnabled('multicurrency') && !empty($_SESSION["takeposcustomercurrency"])) {
	if ($invoice->multicurrency_code != $_SESSION["takeposcustomercurrency"]) {
		$invoice->setMulticurrencyCode($_SESSION["takeposcustomercurrency"]);
	}
}

$term = empty($_SESSION["takeposterminal"]) ? 1 : $_SESSION["takeposterminal"];

/*
 * Actions
 */
$error = 0;
$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $invoice, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

$sectionwithinvoicelink = '';
$CUSTOMER_DISPLAY_line1 = '';
$CUSTOMER_DISPLAY_line2 = '';
$headerorder = '';
$footerorder = '';
$printer = null;
$idoflineadded = 0;
if (empty($reshook)) {
	// Action to record a payment on a TakePOS invoice
	$canvalid = ($takeposmode === 'achat') ? $user->hasRight('fournisseur', 'facture', 'creer') : $user->hasRight('facture', 'creer');
	if ($action == 'valid' && $canvalid) {
		$bankaccount = 0;
		$error = 0;

		if (getDolGlobalString('TAKEPOS_CAN_FORCE_BANK_ACCOUNT_DURING_PAYMENT')) {
			$bankaccount = GETPOSTINT('accountid');
		} else {
			if ($pay == 'LIQ') {
				$bankaccount = getDolGlobalInt('CASHDESK_ID_BANKACCOUNT_CASH'.$_SESSION["takeposterminal"]);            // For backward compatibility
			} elseif ($pay == "CHQ") {
				$bankaccount = getDolGlobalInt('CASHDESK_ID_BANKACCOUNT_CHEQUE'.$_SESSION["takeposterminal"]);    // For backward compatibility
			} else {
				$accountname = "CASHDESK_ID_BANKACCOUNT_".$pay.$_SESSION["takeposterminal"];
				$bankaccount = getDolGlobalInt($accountname);
			}
		}

		if ($bankaccount <= 0 && $pay != "delayed" && isModEnabled("bank")) {
			$errormsg = $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("BankAccount"));
			$error++;
		}

		$now = dol_now();
		$res = 0;

		$invoice = new $invoiceclass($db);
		$invoice->fetch($placeid);

		$db->begin();

		if ($invoice->total_ttc < 0) {
			$invoice->type = $invoice::TYPE_CREDIT_NOTE;

			$invoice_table = ($takeposmode === 'achat') ? MAIN_DB_PREFIX."facture_fourn" : MAIN_DB_PREFIX."facture";
			$sql = "SELECT rowid FROM ".$invoice_table;
			$sql .= " WHERE entity IN (".getEntity('invoice').")";
			$sql .= " AND fk_soc = ".((int) $invoice->socid);
			$sql .= " AND type <> ".$invoiceclass::TYPE_CREDIT_NOTE;
			$sql .= " AND fk_statut >= ".$invoice::STATUS_VALIDATED;
			$sql .= " ORDER BY rowid DESC";

			$fk_source = 0;
			$resql = $db->query($sql);
			if ($resql) {
				$obj = $db->fetch_object($resql);
				$fk_source = $obj->rowid;
				if ((int) $fk_source == 0) {
					fail($langs->transnoentitiesnoconv("NoPreviousBillForCustomer"));
				}
			} else {
				fail($langs->transnoentitiesnoconv("NoPreviousBillForCustomer"));
			}
			$invoice->fk_facture_source = $fk_source;
			$invoice->update($user);
		}

		$constantforkey = 'CASHDESK_NO_DECREASE_STOCK'.(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '');
		$allowstockchange = (getDolGlobalString($constantforkey) != "1");

		if ($error) {
			dol_htmloutput_errors($errormsg, [], 1);
		} elseif ($invoice->status != $invoiceclass::STATUS_DRAFT) {
			//If invoice is validated but it is not fully paid is not error and make the payment
			$remaintopay = $invoice->getRemainToPay();
			if (($remaintopay > 0 && $invoice->type != $invoiceclass::TYPE_CREDIT_NOTE) || ($remaintopay < 0 && $invoice->type == $invoiceclass::TYPE_CREDIT_NOTE)) {
				$res = 1;
			} else {
				dol_syslog("Sale already validated");
				dol_htmloutput_errors($langs->trans("InvoiceIsAlreadyValidated", "TakePos"), [], 1);
			}
		} elseif (count($invoice->lines) == 0) {
			$error++;
			dol_syslog('Sale without lines');
			dol_htmloutput_errors($langs->trans("NoLinesToBill", "TakePos"), [], 1);
		} elseif (isModEnabled('stock') && !isModEnabled('productbatch') && $allowstockchange) {
			// Validation of invoice with change into stock when produt/lot module is NOT enabled and stock change NOT disabled.
			// The case for isModEnabled('productbatch') is processed few lines later.
			$savconst = getDolGlobalString('STOCK_CALCULATE_ON_BILL');

			$conf->global->STOCK_CALCULATE_ON_BILL = 1;	// To force the change of stock during invoice validation

			$constantforkey = 'CASHDESK_ID_WAREHOUSE'.(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '');
			dol_syslog("Validate invoice with stock change. Warehouse defined into constant ".$constantforkey." = ".getDolGlobalString($constantforkey));

			// For achat: POSACH-X-Y doesn't match /^[\(]?PROV/i so validate() would keep it as final ref.
			// Reset to (PROV{id}) so FactureFournisseur::validate() calls getNextNumRef() for a real sequential number.
			if ($takeposmode === 'achat') {
				$invoice->ref = '(PROV'.$invoice->id.')';
			}

			// Validate invoice with stock change into warehouse getDolGlobalInt($constantforkey)
			// Label of stock movement will be the same as when we validate invoice "Invoice XXXX validated"
			$batch_rule = 0;	// Module productbatch is disabled here, so no need for a batch_rule.
			$res = $invoice->validate($user, '', getDolGlobalInt($constantforkey), 0, $batch_rule);

			// Restore setup
			$conf->global->STOCK_CALCULATE_ON_BILL = $savconst;
		} else {
			// Validation of invoice with no change into stock (because param $idwarehouse is not fill)
			// For achat: POSACH-X-Y doesn't match /^[\(]?PROV/i so validate() would keep it as final ref.
			// Reset to (PROV{id}) so FactureFournisseur::validate() calls getNextNumRef() for a real sequential number.
			if ($takeposmode === 'achat') {
				$invoice->ref = '(PROV'.$invoice->id.')';
			}
			$res = $invoice->validate($user);
			if ($res < 0) {
				$error++;
				$langs->load("admin");
				dol_htmloutput_errors($invoice->error == 'NotConfigured' ? $langs->trans("NotConfigured").' (TakePos numbering module)' : $invoice->error, $invoice->errors, 1);
			}
		}

		// Add the payment
		if (!$error && $res >= 0) {
			$remaintopay = $invoice->getRemainToPay();
			if ($remaintopay > 0) {
				if ($takeposmode === 'achat') {
					// Supplier invoice payment via PaiementFourn
					$payment = new PaiementFourn($db);
					$payment->datepaye = $now;
					$payment->fk_account = $bankaccount;
					$payment->amounts[$invoice->id] = ($amountofpayment <= 0 || $amountofpayment > $remaintopay) ? $remaintopay : $amountofpayment;
					$payment->paiementid = $paiementid;
					$payment->num_payment = $invoice->ref;
					// Initialize multicurrency arrays to avoid "Undefined array key" warnings in paiementfourn.class.php
					// The class iterates over amounts[] and reads multicurrency_code[$key] / multicurrency_tx[$key]
					$payment->multicurrency_code[$invoice->id] = !empty($invoice->multicurrency_code) ? $invoice->multicurrency_code : $conf->currency;
					$payment->multicurrency_tx[$invoice->id]   = !empty($invoice->multicurrency_tx)   ? $invoice->multicurrency_tx   : 1;
					$payment->multicurrency_amounts[$invoice->id] = 0; // will be computed by create()
					if ($pay != "delayed") {
						$res = $payment->create($user, 1); // 1 = close paid invoices
						if ($res < 0) {
							$error++;
							dol_htmloutput_errors($langs->trans('Error').' '.$payment->error, $payment->errors, 1);
						} else {
							$res = $payment->addPaymentToBank($user, 'payment_supplier', '(SupplierInvoicePayment)', $bankaccount, '', '');
							if ($res < 0) {
								$error++;
								dol_htmloutput_errors($langs->trans('ErrorNoPaymentDefined').' '.$payment->error, $payment->errors, 1);
							}
						}
						$remaintopay = $invoice->getRemainToPay();
					} elseif (getDolGlobalInt("TAKEPOS_DELAYED_TERMS")) {
						$invoice->setPaymentTerms(getDolGlobalInt("TAKEPOS_DELAYED_TERMS"));
					}
				} else {
					// Customer invoice payment via Paiement
					$payment = new Paiement($db);
					$payment->datepaye = $now;
					$payment->fk_account = $bankaccount;
					if ($pay == 'LIQ') {
						$payment->pos_change = GETPOSTFLOAT('excess');
					}
					$payment->amounts[$invoice->id] = $amountofpayment;
					if ($amountofpayment <= 0 || $amountofpayment > $remaintopay) {
						$payment->amounts[$invoice->id] = $remaintopay;
					}
					$payment->paiementid = $paiementid;
					$payment->num_payment = $invoice->ref;
					if ($pay != "delayed") {
						$res = $payment->create($user);
						if ($res < 0) {
							$error++;
							dol_htmloutput_errors($langs->trans('Error').' '.$payment->error, $payment->errors, 1);
						} else {
							$res = $payment->addPaymentToBank($user, 'payment', '(CustomerInvoicePayment)', $bankaccount, '', '');
							if ($res < 0) {
								$error++;
								dol_htmloutput_errors($langs->trans('ErrorNoPaymentDefined').' '.$payment->error, $payment->errors, 1);
							}
						}
						$remaintopay = $invoice->getRemainToPay();
					} elseif (getDolGlobalInt("TAKEPOS_DELAYED_TERMS")) {
						$invoice->setPaymentTerms(getDolGlobalInt("TAKEPOS_DELAYED_TERMS"));
					}
				}
			}

			if ($remaintopay == 0) {
				dol_syslog("Invoice is paid, so we set it to status Paid");
				$result = $invoice->setPaid($user);
				if ($result > 0) {
					$invoice->paye = 1;
					$invoice->status = $invoice::STATUS_CLOSED;
				}
				$invoice->setPaymentMethods($paiementid);
				// Clear achat session after validation
				if ($takeposmode === 'achat') {
					unset($_SESSION[$achat_session_key]);
				}
			} else {
				dol_syslog("Invoice is not paid, remain to pay = ".$remaintopay);
			}
		} else {
			dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
		}

		$warehouseid = 0;
		// Update stock for batch products
		if (!$error && $res >= 0) {
			if (isModEnabled('stock') && isModEnabled('productbatch') && $allowstockchange) {
				// Update stocks
				dol_syslog("Now we record the stock movement for each qualified line");

				// The case !isModEnabled('productbatch') was processed few lines before.
				require_once DOL_DOCUMENT_ROOT . "/product/stock/class/mouvementstock.class.php";
				$constantforkey = 'CASHDESK_ID_WAREHOUSE'.$_SESSION["takeposterminal"];
				$inventorycode = dol_print_date(dol_now(), 'dayhourlog');
				// Label of stock movement will be "TakePOS - Invoice XXXX"
				$labeltakeposmovement = 'TakePOS - '.$langs->trans("Invoice").' '.$invoice->ref;

				foreach ($invoice->lines as $line) {
					// Use the warehouse id defined on invoice line else in the setup
					$warehouseid = ($line->fk_warehouse ? $line->fk_warehouse : getDolGlobalInt($constantforkey));

					// var_dump('fk_product='.$line->fk_product.' batch='.$line->batch.' warehouse='.$line->fk_warehouse.' qty='.$line->qty);
					if ($line->batch != '' && $warehouseid > 0) {
						$prod_batch = new Productbatch($db);
						$prod_batch->find(0, '', '', $line->batch, $warehouseid);

						$mouvP = new MouvementStock($db);
						$mouvP->setOrigin($invoice->element, $invoice->id);

						$res = $mouvP->livraison($user, $line->fk_product, $warehouseid, $line->qty, $line->price, $labeltakeposmovement, '', '', '', $prod_batch->batch, $prod_batch->id, $inventorycode);
						if ($res < 0) {
							dol_htmloutput_errors($mouvP->error, $mouvP->errors, 1);
							$error++;
						}
					} else {
						$mouvP = new MouvementStock($db);
						$mouvP->setOrigin($invoice->element, $invoice->id);

						$res = $mouvP->livraison($user, $line->fk_product, $warehouseid, $line->qty, $line->price, $labeltakeposmovement, '', '', '', '', 0, $inventorycode);
						if ($res < 0) {
							dol_htmloutput_errors($mouvP->error, $mouvP->errors, 1);
							$error++;
						}
					}
				}
			}
		}

		if (!$error && $res >= 0) {
			$db->commit();
		} else {
			$db->rollback();
		}
	}
	$creditnote = null;
	if ($action == 'creditnote' && $user->hasRight('facture', 'creer')) {
		$db->begin();

		$creditnote = new $invoiceclass($db);
		$creditnote->socid = $invoice->socid;
		$creditnote->date = dol_now();
		$creditnote->module_source = 'takepos';
		$creditnote->pos_source =  isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '' ;
		$creditnote->type = $invoiceclass::TYPE_CREDIT_NOTE;
		$creditnote->fk_facture_source = $placeid;
		//$creditnote->remise_absolue = $invoice->remise_absolue;
		//$creditnote->remise_percent = $invoice->remise_percent;
		$creditnote->create($user);

		$fk_parent_line = 0; // Initialise

		foreach ($invoice->lines as $line) {
			// Reset fk_parent_line for no child products and special product
			if (($line->product_type != 9 && empty($line->fk_parent_line)) || $line->product_type == 9) {
				$fk_parent_line = 0;
			}

			if (getDolGlobalInt('INVOICE_USE_SITUATION')) {
				if (!empty($invoice->situation_counter)) {
					$source_fk_prev_id = $line->fk_prev_id; // temporary storing situation invoice fk_prev_id
					$line->fk_prev_id  = $line->id; // The new line of the new credit note we are creating must be linked to the situation invoice line it is created from
					if (!empty($invoice->tab_previous_situation_invoice)) {
						// search the last standard invoice in cycle and the possible credit note between this last and invoice
						// TODO Move this out of loop of $invoice->lines
						$tab_jumped_credit_notes = array();
						$lineIndex = count($invoice->tab_previous_situation_invoice) - 1;
						$searchPreviousInvoice = true;
						while ($searchPreviousInvoice) {
							if ($invoice->tab_previous_situation_invoice[$lineIndex]->situation_cycle_ref || $lineIndex < 1) {
								$searchPreviousInvoice = false; // find, exit;
								break;
							} else {
								if ($invoice->tab_previous_situation_invoice[$lineIndex]->type == $invoiceclass::TYPE_CREDIT_NOTE) {
									$tab_jumped_credit_notes[$lineIndex] = $invoice->tab_previous_situation_invoice[$lineIndex]->id;
								}
								$lineIndex--; // go to previous invoice in cycle
							}
						}

						$maxPrevSituationPercent = 0;
						foreach ($invoice->tab_previous_situation_invoice[$lineIndex]->lines as $prevLine) {
							if ($prevLine->id == $source_fk_prev_id) {
								$maxPrevSituationPercent = max($maxPrevSituationPercent, $prevLine->situation_percent);

								//$line->subprice  = $line->subprice - $prevLine->subprice;
								$line->total_ht  -= $prevLine->total_ht;
								$line->total_tva -= $prevLine->total_tva;
								$line->total_ttc -= $prevLine->total_ttc;
								$line->total_localtax1 -= $prevLine->total_localtax1;
								$line->total_localtax2 -= $prevLine->total_localtax2;

								$line->multicurrency_subprice  -= $prevLine->multicurrency_subprice;
								$line->multicurrency_total_ht  -= $prevLine->multicurrency_total_ht;
								$line->multicurrency_total_tva -= $prevLine->multicurrency_total_tva;
								$line->multicurrency_total_ttc -= $prevLine->multicurrency_total_ttc;
							}
						}

						// prorata
						$line->situation_percent = $maxPrevSituationPercent - $line->situation_percent;

						//print 'New line based on invoice id '.$invoice->tab_previous_situation_invoice[$lineIndex]->id.' fk_prev_id='.$source_fk_prev_id.' will be fk_prev_id='.$line->fk_prev_id.' '.$line->total_ht.' '.$line->situation_percent.'<br>';

						// If there is some credit note between last situation invoice and invoice used for credit note generation (note: credit notes are stored as delta)
						$maxPrevSituationPercent = 0;
						foreach ($tab_jumped_credit_notes as $index => $creditnoteid) {
							foreach ($invoice->tab_previous_situation_invoice[$index]->lines as $prevLine) {
								if ($prevLine->fk_prev_id == $source_fk_prev_id) {
									$maxPrevSituationPercent = $prevLine->situation_percent;

									$line->total_ht  -= $prevLine->total_ht;
									$line->total_tva -= $prevLine->total_tva;
									$line->total_ttc -= $prevLine->total_ttc;
									$line->total_localtax1 -= $prevLine->total_localtax1;
									$line->total_localtax2 -= $prevLine->total_localtax2;

									$line->multicurrency_subprice  -= $prevLine->multicurrency_subprice;
									$line->multicurrency_total_ht  -= $prevLine->multicurrency_total_ht;
									$line->multicurrency_total_tva -= $prevLine->multicurrency_total_tva;
									$line->multicurrency_total_ttc -= $prevLine->multicurrency_total_ttc;
								}
							}
						}

						// prorata
						$line->situation_percent += $maxPrevSituationPercent;

						//print 'New line based on invoice id '.$invoice->tab_previous_situation_invoice[$lineIndex]->id.' fk_prev_id='.$source_fk_prev_id.' will be fk_prev_id='.$line->fk_prev_id.' '.$line->total_ht.' '.$line->situation_percent.'<br>';
					}
				}
			}

			// We update field for credit notes
			$line->fk_facture = $creditnote->id;
			$line->fk_parent_line = $fk_parent_line;

			$line->subprice = -$line->subprice; // invert price for object
			// $line->pa_ht = $line->pa_ht; // we chose to have the buy/cost price always positive, so no inversion of the sign here
			$line->total_ht = -$line->total_ht;
			$line->total_tva = -$line->total_tva;
			$line->total_ttc = -$line->total_ttc;
			$line->total_localtax1 = -$line->total_localtax1;
			$line->total_localtax2 = -$line->total_localtax2;

			$line->multicurrency_subprice = -$line->multicurrency_subprice;
			$line->multicurrency_total_ht = -$line->multicurrency_total_ht;
			$line->multicurrency_total_tva = -$line->multicurrency_total_tva;
			$line->multicurrency_total_ttc = -$line->multicurrency_total_ttc;

			$result = $line->insert(0, 1); // When creating credit note with same lines than source, we must ignore error if discount already linked

			$creditnote->lines[] = $line; // insert new line in current object

			// Defined the new fk_parent_line
			if ($result > 0 && $line->product_type == 9) {
				$fk_parent_line = $result;
			}
		}
		$creditnote->update_price(1);

		// The credit note is create here. We must now validate it.

		$constantforkey = 'CASHDESK_NO_DECREASE_STOCK'.(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '');
		$allowstockchange = getDolGlobalString($constantforkey) != "1";

		if (isModEnabled('stock') && !isModEnabled('productbatch') && $allowstockchange) {
			// If module stock is enabled and we do not setup takepo to disable stock decrease
			// The case for isModEnabled('productbatch') is processed few lines later.
			$savconst = getDolGlobalString('STOCK_CALCULATE_ON_BILL');
			$conf->global->STOCK_CALCULATE_ON_BILL = 1;	// We force setup to have update of stock on invoice validation/unvalidation

			$constantforkey = 'CASHDESK_ID_WAREHOUSE'.(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '');

			dol_syslog("Validate invoice with stock change into warehouse defined into constant ".$constantforkey." = ".getDolGlobalString($constantforkey)." or warehouseid= ".$warehouseid." if defined.");

			// Validate invoice with stock change into warehouse getDolGlobalInt($constantforkey)
			// Label of stock movement will be the same as when we validate invoice "Invoice XXXX validated"
			$batch_rule = 0;	// Module productbatch is disabled here, so no need for a batch_rule.
			$res = $creditnote->validate($user, '', getDolGlobalInt($constantforkey), 0, $batch_rule);
			if ($res < 0) {
				$error++;
				dol_htmloutput_errors($creditnote->error, $creditnote->errors, 1);
			}

			// Restore setup
			$conf->global->STOCK_CALCULATE_ON_BILL = $savconst;
		} else {
			$res = $creditnote->validate($user);
		}

		// Update stock for batch products
		if (!$error && $res >= 0) {
			if (isModEnabled('stock') && isModEnabled('productbatch') && $allowstockchange) {
				// Update stocks
				dol_syslog("Now we record the stock movement for each qualified line");

				// The case !isModEnabled('productbatch') was processed few lines before.
				require_once DOL_DOCUMENT_ROOT . "/product/stock/class/mouvementstock.class.php";
				$constantforkey = 'CASHDESK_ID_WAREHOUSE'.$_SESSION["takeposterminal"];
				$inventorycode = dol_print_date(dol_now(), 'dayhourlog');
				// Label of stock movement will be "TakePOS - Invoice XXXX"
				$labeltakeposmovement = 'TakePOS - '.$langs->trans("CreditNote").' '.$creditnote->ref;

				foreach ($creditnote->lines as $line) {
					// Use the warehouse id defined on invoice line else in the setup
					$warehouseid = ($line->fk_warehouse ? $line->fk_warehouse : getDolGlobalInt($constantforkey));
					//var_dump('fk_product='.$line->fk_product.' batch='.$line->batch.' warehouse='.$line->fk_warehouse.' qty='.$line->qty);exit;

					if ($line->batch != '' && $warehouseid > 0) {
						//$prod_batch = new Productbatch($db);
						//$prod_batch->find(0, '', '', $line->batch, $warehouseid);

						$mouvP = new MouvementStock($db);
						$mouvP->setOrigin($creditnote->element, $creditnote->id);

						$res = $mouvP->reception($user, $line->fk_product, $warehouseid, $line->qty, $line->price, $labeltakeposmovement, '', '', $line->batch, '', 0, $inventorycode);
						if ($res < 0) {
							dol_htmloutput_errors($mouvP->error, $mouvP->errors, 1);
							$error++;
						}
					} else {
						$mouvP = new MouvementStock($db);
						$mouvP->setOrigin($creditnote->element, $creditnote->id);

						$res = $mouvP->reception($user, $line->fk_product, $warehouseid, $line->qty, $line->price, $labeltakeposmovement, '', '', '', '', 0, $inventorycode);
						if ($res < 0) {
							dol_htmloutput_errors($mouvP->error, $mouvP->errors, 1);
							$error++;
						}
					}
				}
			}
		}

		if (!$error && $res >= 0) {
			$db->commit();
		} else {
			$creditnote->id = $placeid;	// Creation has failed, we reset to ID of source invoice so we go back to this one in action=history
			$db->rollback();
		}
	}

	if (($action == 'history' || $action == 'creditnote') && $user->hasRight('takepos', 'run')) {
		if ($action == 'creditnote' && $creditnote !== null && $creditnote->id > 0) {	// Test on permission already done
			$placeid = $creditnote->id;
		} else {
			$placeid = GETPOSTINT('placeid');
		}

		$invoice = new $invoiceclass($db);
		$invoice->fetch($placeid);
	}

	// Achat history: output a mini list of recent supplier invoices and exit
	if ($action == 'achat_history' && $user->hasRight('takepos', 'run')) {
		// --- Filters from GET parameters ---
		$filter_ref    = GETPOST('filter_ref', 'alpha');
		$filter_soc    = GETPOST('filter_soc', 'alpha');
		$filter_status = GETPOST('filter_status', 'int');
		$filter_date1  = GETPOST('filter_date1', 'alpha');
		$filter_date2  = GETPOST('filter_date2', 'alpha');

		$sql  = "SELECT f.rowid, f.ref, f.datef, f.total_ttc, f.fk_statut, s.nom as supplier";
		$sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn f";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc";
		$sql .= " WHERE f.entity IN (".getEntity('invoice').")";
		$sql .= " AND f.fk_statut >= 0";  // only real invoices (exclude PROV)
		if ($filter_ref)    $sql .= " AND f.ref LIKE '%".$db->escape($filter_ref)."%'";
		if ($filter_soc)    $sql .= " AND s.nom LIKE '%".$db->escape($filter_soc)."%'";
		if ($filter_status !== '' && $filter_status >= 0) $sql .= " AND f.fk_statut = ".((int)$filter_status);
		if ($filter_date1)  $sql .= " AND f.datef >= '".$db->idate(dol_mktime(0, 0, 0, (int)substr($filter_date1, 5, 2), (int)substr($filter_date1, 8, 2), (int)substr($filter_date1, 0, 4)))."'";
		if ($filter_date2)  $sql .= " AND f.datef <= '".$db->idate(dol_mktime(23, 59, 59, (int)substr($filter_date2, 5, 2), (int)substr($filter_date2, 8, 2), (int)substr($filter_date2, 0, 4)))."'";
		$sql .= " ORDER BY f.rowid DESC LIMIT 100";
		$resql = $db->query($sql);

		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Historique achats</title>';
		echo '<link rel="stylesheet" href="css/pos.css.php">';
		echo '<style>
		body{font-family:sans-serif;font-size:13px;padding:8px;margin:0;background:#fff;}
		h3{margin:0 0 8px;font-size:15px;color:#333;}
		.filter-bar{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;align-items:center;}
		.filter-bar input,.filter-bar select{padding:4px 7px;border:1px solid #ccc;border-radius:4px;font-size:12px;}
		.filter-bar label{font-size:11px;color:#555;margin-bottom:2px;}
		.filter-group{display:flex;flex-direction:column;}
		.btn-filter{background:#555;color:#fff;border:none;padding:5px 12px;border-radius:4px;cursor:pointer;font-size:12px;}
		.btn-reset{background:#aaa;color:#fff;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:12px;}
		table{width:100%;border-collapse:collapse;}
		th{background:#f0f0f0;font-size:12px;font-weight:600;}
		th,td{padding:6px 8px;border-bottom:1px solid #e0e0e0;text-align:left;white-space:nowrap;}
		tr:hover td{background:#fafafa;}
		.btn-load{background:#e07b39;color:#fff;border:none;padding:4px 12px;border-radius:4px;cursor:pointer;font-size:12px;}
		.s0{color:#aaa;} .s1{color:#27ae60;font-weight:600;} .s2{color:#2980b9;font-weight:600;}
		</style></head><body>';

		echo '<h3>'.$langs->trans("SupplierInvoices").'</h3>';

		// --- Filter bar ---
		$self_url = 'invoice.php?action=achat_history&token='.newToken().'&place='.$place;
		echo '<form method="GET" action="invoice.php">';
		echo '<input type="hidden" name="action" value="achat_history">';
		echo '<input type="hidden" name="token" value="'.newToken().'">';
		echo '<input type="hidden" name="place" value="'.dol_escape_htmltag($place).'">';
		echo '<div class="filter-bar">';
		echo '<div class="filter-group"><label>Référence</label><input type="text" name="filter_ref" value="'.dol_escape_htmltag($filter_ref).'" placeholder="Nº facture"></div>';
		echo '<div class="filter-group"><label>Fournisseur</label><input type="text" name="filter_soc" value="'.dol_escape_htmltag($filter_soc).'" placeholder="Nom fournisseur"></div>';
		echo '<div class="filter-group"><label>Du</label><input type="date" name="filter_date1" value="'.dol_escape_htmltag($filter_date1).'"></div>';
		echo '<div class="filter-group"><label>Au</label><input type="date" name="filter_date2" value="'.dol_escape_htmltag($filter_date2).'"></div>';
		echo '<div class="filter-group"><label>Statut</label><select name="filter_status">';
		echo '<option value="-1"'.($filter_status === '' || $filter_status < 0 ? ' selected' : '').'>Tous</option>';
		echo '<option value="0"'.($filter_status == 0 ? ' selected' : '').'>Brouillon</option>';
		echo '<option value="1"'.($filter_status == 1 ? ' selected' : '').'>Validée</option>';
		echo '<option value="2"'.($filter_status == 2 ? ' selected' : '').'>Payée</option>';
		echo '</select></div>';
		echo '<div class="filter-group" style="justify-content:flex-end;padding-top:14px;">';
		echo '<button type="submit" class="btn-filter">Filtrer</button>&nbsp;';
		echo '<a href="invoice.php?action=achat_history&token='.newToken().'&place='.urlencode($place).'" style="text-decoration:none;"><button type="button" class="btn-reset">✕</button></a>';
		echo '</div>';
		echo '</div></form>';

		// --- Table ---
		echo '<table>';
		echo '<tr><th>#</th><th>Référence</th><th>Date</th><th>Fournisseur</th><th>Total TTC</th><th>Statut</th><th></th></tr>';
		if ($resql) {
			$nb = 0;
			while ($obj = $db->fetch_object($resql)) {
				$nb++;
				// Decode any HTML entities that Dolibarr may have stored in database (e.g. &eacute; → é)
				$supplier_name = html_entity_decode((string)$obj->supplier, ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$statuslabel = $obj->fk_statut == 0 ? 'Brouillon' : ($obj->fk_statut == 1 ? 'Validée' : 'Payée');
				$statusclass = 's'.$obj->fk_statut;
				echo '<tr>';
				echo '<td>'.((int)$obj->rowid).'</td>';
				echo '<td>'.htmlspecialchars($obj->ref, ENT_QUOTES, 'UTF-8').'</td>';
				echo '<td>'.dol_print_date($db->jdate($obj->datef), 'day').'</td>';
				echo '<td>'.htmlspecialchars($supplier_name, ENT_QUOTES, 'UTF-8').'</td>';
				echo '<td>'.price($obj->total_ttc).'</td>';
				echo '<td class="'.$statusclass.'">'.$statuslabel.'</td>';
				echo '<td><button class="btn-load" onclick="loadInvoice('.((int)$obj->rowid).')">Ouvrir</button></td>';
				echo '</tr>';
			}
			if ($nb == 0) {
				echo '<tr><td colspan="7" style="text-align:center;color:#aaa;padding:20px;">Aucune facture trouvée</td></tr>';
			}
		}
		echo '</table>';
		echo '<script>';
		echo 'function loadInvoice(id) {';
		echo '  var url = "invoice.php?action=history&token='.newToken().'&place='.$place.'&placeid="+id;';
		echo '  parent.$("#poslines").load(url, function(){ parent.$.colorbox.close(); });';
		echo '  if (typeof parent.resetEditBar === "function") parent.resetEditBar();';
		echo '}';
		echo '</script>';
		echo '</body></html>';
		exit;
	}


	// If we add a line and no invoice yet, we create the invoice
	if (($action == "addline" || $action == "freezone") && $placeid == 0 && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
		$invoice->socid = getDolGlobalInt($constforcompanyid);

		$dolnowtzuserrel = dol_now('tzuserrel');	// If user is 02 january 22:00, we want to store '02 january'
		$monthuser = dol_print_date($dolnowtzuserrel, '%m', 'gmt');
		$dayuser = dol_print_date($dolnowtzuserrel, '%d', 'gmt');
		$yearuser = dol_print_date($dolnowtzuserrel, '%Y', 'gmt');
		$dateinvoice = dol_mktime(0, 0, 0, (int) $monthuser, (int) $dayuser, (int) $yearuser, 'tzserver');	// If we enter the 02 january, we need to save the 02 january for server

		include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
		$invoice->date = $dateinvoice;		// Invoice::create() needs a date with no hours

		/*
		print "monthuser=".$monthuser." dayuser=".$dayuser." yearuser=".$yearuser.'<br>';
		print '---<br>';
		print 'TZSERVER: '.dol_print_date(dol_now('tzserver'), 'dayhour', 'gmt').'<br>';
		print 'TZUSER: '.dol_print_date(dol_now('tzuserrel'), 'dayhour', 'gmt').'<br>';
		print 'GMT: '.dol_print_date(dol_now('gmt'), 'dayhour', 'gmt').'<br>';	// Hour in greenwich
		print '---<br>';
		print dol_print_date($invoice->date, 'dayhour', 'gmt').'<br>';
		print "IN SQL, we will got: ".dol_print_date($db->idate($invoice->date), 'dayhour', 'gmt').'<br>';
		print dol_print_date($db->idate($invoice->date, 'gmt'), 'dayhour', 'gmt').'<br>';
		*/

		$invoice->module_source = 'takepos';
		$invoice->pos_source =  isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '' ;
		$invoice->entity = !empty($_SESSION["takeposinvoiceentity"]) ? $_SESSION["takeposinvoiceentity"] : $conf->entity;

		if ($invoice->socid <= 0) {
			$langs->load('errors');
			dol_htmloutput_errors($langs->trans("ErrorModuleSetupNotComplete", "TakePos"), [], 1);
		} elseif ($takeposmode === 'achat') {
			// ACHAT MODE: FactureFournisseur manages its own transaction internally.
			// Do NOT wrap in an outer begin/commit (causes nested-transaction issues with (PROV) placeholder).
			//
			// IMPORTANT: llx_facture_fourn has a UNIQUE constraint on (ref_supplier, fk_soc, entity).
			// TakePos does not collect a supplier reference number, so we set a unique placeholder here
			// to avoid "ErrorRefAlreadyExists" when a previous invoice for the same supplier already has
			// ref_supplier=''. The user can update ref_supplier later from the supplier invoice card.
			if (empty($invoice->ref_supplier)) {
				$invoice->ref_supplier = $achat_prov_ref.'-'.uniqid('', true);
			}
			$placeid = $invoice->create($user);
			if ($placeid > 0) {
				// Remove any OTHER stale invoice with the same POSACH provisional ref (different rowid)
				$res_stale = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."facture_fourn WHERE ref='".$db->escape($achat_prov_ref)."' AND rowid<>".((int) $placeid)." AND fk_statut=0");
				if ($res_stale) {
					while ($obj_stale = $db->fetch_object($res_stale)) {
						$db->query("DELETE FROM ".MAIN_DB_PREFIX."facture_fourn_det WHERE fk_facture_fourn=".((int) $obj_stale->rowid));
						$db->query("DELETE FROM ".MAIN_DB_PREFIX."facture_fourn WHERE rowid=".((int) $obj_stale->rowid));
					}
				}
				// Stamp our new invoice with the stable POSACH provisional ref
				$db->query("UPDATE ".MAIN_DB_PREFIX."facture_fourn SET ref='".$db->escape($achat_prov_ref)."' WHERE rowid=".((int) $placeid));
				$invoice->ref = $achat_prov_ref;
				$_SESSION[$achat_session_key] = $placeid;
			} else {
				// create() failed — possibly a concurrent request already created the invoice.
				// Try to reuse the existing POSACH invoice instead of showing an error.
				$invoice_concurrent = new FactureFournisseur($db);
				$ret_concurrent = $invoice_concurrent->fetch(0, $achat_prov_ref);
				if ($ret_concurrent > 0) {
					// Concurrent request already stamped the invoice — reuse it
					$invoice = $invoice_concurrent;
					$placeid = $invoice->id;
					$_SESSION[$achat_session_key] = $placeid;
				} else {
					dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
				}
			}
		} else {
			$db->begin();

			// Create invoice (vente mode)
			$placeid = $invoice->create($user);

			if ($placeid < 0) {
				dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
			}
			$sql = "UPDATE ".MAIN_DB_PREFIX.$invoice->table_element;
			$sql .= " SET ref='(PROV-POS".$_SESSION["takeposterminal"]."-".$place.")'";
			$sql .= " WHERE rowid = ".((int) $placeid);
			$resql = $db->query($sql);
			if (!$resql) {
				$error++;
			}

			if (!$error) {
				$db->commit();
			} else {
				$db->rollback();
			}
		}
	}

	$tva_npr = 0;
	// If we add a line by click on product (invoice exists here because it was created juste before if it didn't exists)
	if ($action == "addline" && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
		$prod = new Product($db);
		$prod->fetch($idproduct);

		$customer = new Societe($db);
		$customer->fetch($invoice->socid);

		$datapriceofproduct = $prod->getSellPrice($mysoc, $customer, 0);

		$qty = GETPOSTISSET('qty') ? GETPOSTFLOAT('qty') : 1;
		$price = $datapriceofproduct['pu_ht'];
		$price_ttc = $datapriceofproduct['pu_ttc'];
		//$price_min = $datapriceofproduct['price_min'];
		$price_base_type = empty($datapriceofproduct['price_base_type']) ? 'HT' : $datapriceofproduct['price_base_type'];
		$tva_tx = $datapriceofproduct['tva_tx'];
		$tva_npr = (int) $datapriceofproduct['tva_npr'];

		// Local Taxes
		$localtax1_tx = get_localtax($tva_tx, 1, $customer, $mysoc, $tva_npr);
		$localtax2_tx = get_localtax($tva_tx, 2, $customer, $mysoc, $tva_npr);


		if (isModEnabled('productbatch') && isModEnabled('stock')) {
			$batch = GETPOST('batch', 'alpha');

			if (!empty($batch)) {	// We have just clicked on a batch number, we will execute action=setbatch later...
				$action = "setbatch";
			} elseif ($prod->status_batch > 0) {
				// If product need a lot/serial, we show the list of lot/serial available for the product...

				// Set nb of suggested with nb of batch into the warehouse of the terminal
				$nbofsuggested = 0;
				$prod->load_stock('warehouseopen');

				$constantforkey = 'CASHDESK_ID_WAREHOUSE'.$_SESSION["takeposterminal"];
				$warehouseid = getDolGlobalInt($constantforkey);

				//var_dump($prod->stock_warehouse);
				foreach ($prod->stock_warehouse as $tmpwarehouseid => $tmpval) {
					if (getDolGlobalInt($constantforkey) && $tmpwarehouseid != getDolGlobalInt($constantforkey)) {
						// Product to select is not on the warehouse configured for terminal, so we ignore this warehouse
						continue;
					}
					if (!empty($prod->stock_warehouse[$tmpwarehouseid]) && is_array($prod->stock_warehouse[$tmpwarehouseid]->detail_batch)) {
						if (is_object($prod->stock_warehouse[$tmpwarehouseid]) && count($prod->stock_warehouse[$tmpwarehouseid]->detail_batch)) {
							foreach ($prod->stock_warehouse[$tmpwarehouseid]->detail_batch as $dbatch) {
								$nbofsuggested++;
							}
						}
					}
				}
				//var_dump($prod->stock_warehouse);

				echo "<script>\n";
				echo "function addbatch(batch, warehouseid) {\n";
				echo "console.log('We add batch '+batch+' from warehouse id '+warehouseid);\n";
				echo '$("#poslines").load("'.DOL_URL_ROOT.'/takepos/invoice.php?action=addline&batch="+encodeURI(batch)+"&warehouseid="+warehouseid+"&place='.$place.'&idproduct='.$idproduct.'&token='.newToken().'", function() {});'."\n";
				echo "}\n";
				echo "</script>\n";

				$suggestednb = 1;
				echo "<center>".$langs->trans("SearchIntoBatch").": <b> $nbofsuggested </b></center><br><table>";
				foreach ($prod->stock_warehouse as $tmpwarehouseid => $tmpval) {
					if (getDolGlobalInt($constantforkey) && $tmpwarehouseid != getDolGlobalInt($constantforkey)) {
						// Not on the forced warehouse, so we ignore this warehouse
						continue;
					}
					if (!empty($prod->stock_warehouse[$tmpwarehouseid]) && is_array($prod->stock_warehouse[$tmpwarehouseid]->detail_batch)) {
						foreach ($prod->stock_warehouse[$tmpwarehouseid]->detail_batch as $dbatch) {	// $dbatch is instance of Productbatch
							$batchStock = + $dbatch->qty; // To get a numeric
							$quantityToBeDelivered = 1;
							$deliverableQty = min($quantityToBeDelivered, $batchStock);
							print '<tr>';
							print '<!-- subj='.$suggestednb.'/'.$nbofsuggested.' -->';
							print '<!-- Show details of lot/serial in warehouseid='.$tmpwarehouseid.' -->';
							print '<td class="left">';
							$detail = '';
							$detail .= '<span class="opacitymedium">'.$langs->trans("LotSerial").':</span> '.$dbatch->batch;
							//if (!getDolGlobalString('PRODUCT_DISABLE_SELLBY')) {
							//$detail .= ' - '.$langs->trans("SellByDate").': '.dol_print_date($dbatch->sellby, "day");
							//}
							//if (!getDolGlobalString('PRODUCT_DISABLE_EATBY')) {
							//$detail .= ' - '.$langs->trans("EatByDate").': '.dol_print_date($dbatch->eatby, "day");
							//}
							$detail .= '</td><td>';
							$detail .= '<span class="opacitymedium">'.$langs->trans("Qty").':</span> '.$dbatch->qty;
							$detail .= '</td><td>';
							$detail .= ' <button class="marginleftonly" onclick="addbatch(\''.dol_escape_js($dbatch->batch).'\', '.$tmpwarehouseid.')">'.$langs->trans("Select")."</button>";
							$detail .= '<br>';
							print $detail;

							$quantityToBeDelivered -= $deliverableQty;
							if ($quantityToBeDelivered < 0) {
								$quantityToBeDelivered = 0;
							}
							$suggestednb++;
							print '</td></tr>';
						}
					}
				}
				print "</table>";

				print '</body></html>';
				exit;
			}
		}


		if (getDolGlobalString('TAKEPOS_SUPPLEMENTS')) {
			require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
			$cat = new Categorie($db);
			$categories = $cat->containing($idproduct, 'product');
			$found = (array_search(getDolGlobalInt('TAKEPOS_SUPPLEMENTS_CATEGORY'), array_column($categories, 'id')));
			if ($found !== false) { // If this product is a supplement
				$sql = "SELECT fk_parent_line FROM ".MAIN_DB_PREFIX."facturedet where rowid = ".((int) $selectedline);
				$resql = $db->query($sql);
				$row = $db->fetch_array($resql);
				if ($row[0] == null) {
					$parent_line = $selectedline;
				} else {
					$parent_line = $row[0]; //If the parent line is already a supplement, add the supplement to the main  product
				}
			}
		}

		$err = 0;
		// Group if enabled. Skip group if line already sent to the printer
		if (getDolGlobalString('TAKEPOS_GROUP_SAME_PRODUCT')) {
			foreach ($invoice->lines as $line) {
				if ($line->product_ref == $prod->ref) {
					if ($line->special_code == 4) {
						continue;
					} // If this line is sended to printer create new line
					// check if qty in stock
					if (getDolGlobalString('TAKEPOS_QTY_IN_STOCK') && (($line->qty + $qty) > $prod->stock_reel)) {
						$invoice->error = $langs->trans("ErrorStockIsNotEnough");
						dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
						$err++;
						break;
					}
					// FactureFournisseur::updateline() has a different signature than Facture::updateline()
					if ($takeposmode === 'achat') {
						// FactureFournisseur::updateline($id, $desc, $pu, $vatrate, $txlocaltax1, $txlocaltax2, $qty, $idproduct, $price_base_type, $info_bits, $type, $remise_percent, ...)
						$result = $invoice->updateline($line->id, $line->desc, $line->subprice, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, $line->qty + $qty, $line->fk_product, 'HT', $line->info_bits, $line->product_type, $line->remise_percent, 0, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);
					} else {
						// Facture::updateline($id, $desc, $pu, $qty, $remise_percent, $date_start, $date_end, $tva_tx, ...)
						$result = $invoice->updateline($line->id, $line->desc, $line->subprice, $line->qty + $qty, $line->remise_percent, $line->date_start, $line->date_end, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
					}
					if ($result < 0) {
						dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
					} else {
						$idoflineadded = $line->id;
					}
					break;
				}
			}
		}
		if ($idoflineadded <= 0 && empty($err)) {
			$invoice->fetch_thirdparty();
			$array_options = array();

			$line = array('description' => $prod->description, 'price' => $price, 'tva_tx' => $tva_tx, 'localtax1_tx' => $localtax1_tx, 'localtax2_tx' => $localtax2_tx, 'remise_percent' => $customer->remise_percent, 'price_ttc' => $price_ttc, 'array_options' => $array_options);

			/* setup of margin calculation */
			if (getDolGlobalString('MARGIN_TYPE')) {
				if (getDolGlobalString('MARGIN_TYPE') == 'pmp' && !empty($prod->pmp)) {
					$line['fk_fournprice'] = null;
					$line['pa_ht'] = $prod->pmp;
				} elseif (getDolGlobalString('MARGIN_TYPE') == 'costprice' && !empty($prod->cost_price)) {
					$line['fk_fournprice'] = null;
					$line['pa_ht'] = $prod->cost_price;
				} else {
					// default is fournprice
					require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
					$pf = new ProductFournisseur($db);
					if ($pf->find_min_price_product_fournisseur($idproduct, $qty) > 0) {
						$line['fk_fournprice'] = $pf->product_fourn_price_id;
						$line['pa_ht'] = $pf->fourn_unitprice_with_discount;
						if (getDolGlobalString('PRODUCT_CHARGES') && $pf->fourn_charges > 0) {
							$line['pa_ht'] += (float) $pf->fourn_charges / $pf->fourn_qty;
						}
					}
				}
			}

			// complete line by hook
			$parameters = array('prod' => $prod, 'line' => $line);
			$reshook = $hookmanager->executeHooks('completeTakePosAddLine', $parameters, $invoice, $action);    // Note that $action and $line may have been modified by some hooks
			if ($reshook < 0) {
				setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
			}


			if (empty($reshook)) {
				if (!empty($hookmanager->resArray)) {
					$line = $hookmanager->resArray;
				}

				// check if qty in stock
				if (getDolGlobalString('TAKEPOS_QTY_IN_STOCK') && $qty > $prod->stock_reel) {
					$invoice->error = $langs->trans("ErrorStockIsNotEnough");
					dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
					$err++;
				}

				if (empty($err)) {
					if ($takeposmode === 'achat') {
						// FactureFournisseur::addline() has a different parameter order than Facture::addline()
						// Signature: addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product, $remise_percent, ...)
						$idoflineadded = $invoice->addline(
							$line['description'],
							$line['price'],
							$line['tva_tx'],
							$line['localtax1_tx'],
							$line['localtax2_tx'],
							$qty,
							$idproduct,
							(float) $line['remise_percent'],
							0,
							0,
							0,
							0,
							$price_base_type,
							$prod->type,
							-1,
							0,
							$line['array_options'],
							null,
							0,
							0,
							'',
							0,
							(empty($parent_line) ? '' : $parent_line)
						);
					} else {
						$idoflineadded = $invoice->addline($line['description'], $line['price'], $qty, $line['tva_tx'], $line['localtax1_tx'], $line['localtax2_tx'], $idproduct, (float) $line['remise_percent'], '', 0, 0, 0, 0, $price_base_type, $line['price_ttc'], $prod->type, -1, 0, '', 0, (empty($parent_line) ? '' : $parent_line), (empty($line['fk_fournprice']) ? 0 : $line['fk_fournprice']), (empty($line['pa_ht']) ? '' : $line['pa_ht']), '', $line['array_options'], 100, 0, null, 0);
					}
				}
			}

			if (getDolGlobalString('TAKEPOS_CUSTOMER_DISPLAY')) {
				$CUSTOMER_DISPLAY_line1 = $prod->label;
				$CUSTOMER_DISPLAY_line2 = price($price_ttc);
			}
		}

		$invoice->fetch($placeid);
	}

	// If we add a line by submitting freezone form (invoice exists here because it was created just before if it didn't exist)
	if ($action == "freezone" && $user->hasRight('takepos', 'run')) {
		$customer = new Societe($db);
		$customer->fetch($invoice->socid);

		$tva_tx = GETPOST('tva_tx', 'alpha');
		if ($tva_tx != '') {
			if (!preg_match('/\((.*)\)/', $tva_tx)) {
				$tva_tx = price2num($tva_tx);
			}
		} else {
			$tva_tx = get_default_tva($mysoc, $customer);
		}

		// Local Taxes
		$localtax1_tx = get_localtax($tva_tx, 1, $customer, $mysoc, $tva_npr);
		$localtax2_tx = get_localtax($tva_tx, 2, $customer, $mysoc, $tva_npr);

		// Always use 'HT' so the entered price is stored as-is (no TTC→HT rounding/conversion)
		if ($takeposmode === 'achat') {
			// FactureFournisseur::addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product, $remise_percent, ..., $price_base_type, ...)
			$res = $invoice->addline($desc, $number, $tva_tx, $localtax1_tx, $localtax2_tx, 1, 0, 0, 0, 0, 0, 0, 'HT', 0);
		} else {
			// Facture::addline($desc, $pu_ht, $qty, $tva_tx, ..., $price_base_type, $pu_ttc, ...)
			$res = $invoice->addline($desc, $number, 1, $tva_tx, $localtax1_tx, $localtax2_tx, 0, 0, '', 0, 0, 0, 0, 'HT', $number, 0, -1, 0, '', 0, 0, 0, 0, '', array(), 100, 0, null, 0);
		}
		if ($res < 0) {
			dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
		}
		$invoice->fetch($placeid);
	}

	// Basculer: validate supplier invoice (no stock), add stock IN, create customer invoice, switch to vente mode
	if ($action == "basculer" && $takeposmode === 'achat'
		&& $user->hasRight('fournisseur', 'facture', 'creer') && $user->hasRight('facture', 'creer')) {

		$basculer_pay = $paycode; // already normalized: 'LIQ', 'CB', 'CHQ', 'delayed', ...
		$bankaccount  = 0;
		if ($basculer_pay == 'LIQ') {
			$bankaccount = getDolGlobalInt('CASHDESK_ID_BANKACCOUNT_CASH'.$term);
		} elseif ($basculer_pay == 'CHQ') {
			$bankaccount = getDolGlobalInt('CASHDESK_ID_BANKACCOUNT_CHEQUE'.$term);
		} elseif ($basculer_pay && $basculer_pay != 'delayed') {
			$bankaccount = getDolGlobalInt('CASHDESK_ID_BANKACCOUNT_'.$basculer_pay.$term);
		}

		$now = dol_now();
		$db->begin();
		$basculer_error = 0;

		// --- Step 1: validate supplier invoice WITHOUT stock change ---
		if (count($invoice->lines) == 0) {
			$basculer_error++;
			dol_htmloutput_errors($langs->trans("NoLinesToBill", "TakePos"), array(), 1);
		}

		if (!$basculer_error) {
			// Reset ref so validate() calls getNextNumRef() for a sequential ref
			$invoice->ref = '(PROV'.$invoice->id.')';
			$res = $invoice->validate($user); // no warehouse => no stock change
			if ($res < 0) {
				$basculer_error++;
				dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
			}
		}

		// --- Step 2: record payment for supplier invoice ---
		if (!$basculer_error) {
			$remaintopay = $invoice->getRemainToPay();
			if ($remaintopay > 0) {
				if ($basculer_pay != 'delayed') {
					$payment = new PaiementFourn($db);
					$payment->datepaye = $now;
					$payment->fk_account = $bankaccount;
					$payment->amounts[$invoice->id] = $remaintopay;
					$payment->paiementid = $paiementid;
					$payment->num_payment = $invoice->ref;
					$payment->multicurrency_code[$invoice->id] = !empty($invoice->multicurrency_code) ? $invoice->multicurrency_code : $conf->currency;
					$payment->multicurrency_tx[$invoice->id]   = !empty($invoice->multicurrency_tx)   ? $invoice->multicurrency_tx   : 1;
					$payment->multicurrency_amounts[$invoice->id] = 0;
					$res = $payment->create($user, 1);
					if ($res < 0) {
						$basculer_error++;
						dol_htmloutput_errors($langs->trans('Error').' '.$payment->error, $payment->errors, 1);
					} else {
						$res = $payment->addPaymentToBank($user, 'payment_supplier', '(SupplierInvoicePayment)', $bankaccount, '', '');
						if ($res < 0) {
							$basculer_error++;
							dol_htmloutput_errors($langs->trans('Error').' '.$payment->error, $payment->errors, 1);
						}
					}
					if (!$basculer_error) {
						$invoice->setPaid($user);
						$invoice->setPaymentMethods($paiementid);
					}
				} elseif (getDolGlobalInt("TAKEPOS_DELAYED_TERMS")) {
					$invoice->setPaymentTerms(getDolGlobalInt("TAKEPOS_DELAYED_TERMS"));
				}
			}
		}

		// --- Step 3: add stock IN for each product line ---
		if (!$basculer_error && isModEnabled('stock')) {
			require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
			$constantforkey = 'CASHDESK_ID_WAREHOUSE'.$term;
			$warehouseid    = getDolGlobalInt($constantforkey);
			$labelmouv      = 'TakePOS Basculer - '.$invoice->ref;
			$inventorycode  = dol_print_date($now, 'dayhourlog');

			if ($warehouseid > 0) {
				foreach ($invoice->lines as $line) {
					if ($line->fk_product > 0 && $line->qty > 0) {
						$wh = ($line->fk_warehouse ? $line->fk_warehouse : $warehouseid);
						$mouvP = new MouvementStock($db);
						$mouvP->setOrigin($invoice->element, $invoice->id);
						$res = $mouvP->reception($user, $line->fk_product, $wh, $line->qty, $line->subprice, $labelmouv, '', '', '', '', 0, $inventorycode);
						if ($res < 0) {
							dol_htmloutput_errors($mouvP->error, $mouvP->errors, 1);
							$basculer_error++;
						}
					}
				}
			}
		}

		// --- Step 4: create customer invoice (Facture) with same lines ---
		if (!$basculer_error) {
			$newInvoice = new Facture($db);
			$constforcompanyid = 'CASHDESK_ID_THIRDPARTY'.$term;
			$newInvoice->socid        = getDolGlobalInt($constforcompanyid);
			$newInvoice->date         = $now;
			$newInvoice->module_source = 'takepos';
			$newInvoice->pos_source   = $term;
			if (isModEnabled('project') && getDolGlobalInt('CASHDESK_ID_PROJECT'.$term)) {
				$newInvoice->fk_project = getDolGlobalInt('CASHDESK_ID_PROJECT'.$term);
			}

			$res = $newInvoice->create($user);
			if ($res <= 0) {
				$basculer_error++;
				dol_htmloutput_errors($newInvoice->error, $newInvoice->errors, 1);
			} else {
				// Set provisional ref to the standard TakePos vente format
				$db->query(
					"UPDATE ".MAIN_DB_PREFIX."facture SET ref='(PROV-POS".$db->escape($term.'-'.$place).")'"
					." WHERE rowid=".((int) $newInvoice->id)
				);

				// Copy lines from supplier invoice to customer invoice
				foreach ($invoice->lines as $line) {
					$res = $newInvoice->addline(
						$line->desc,
						$line->subprice,   // pu_ht
						$line->qty,
						$line->tva_tx,
						$line->localtax1_tx,
						$line->localtax2_tx,
						$line->fk_product,
						$line->remise_percent,
						'',                // date_start
						0, 0, 0, 0,
						'HT',
						$line->subprice,   // pu_ttc (ignored when HT)
						0,
						-1,                // rang
						0,
						'',
						0, 0, 0, 0,
						'',
						array(),
						100,
						0,
						null,
						0
					);
					if ($res < 0) {
						$basculer_error++;
						dol_htmloutput_errors($newInvoice->error, $newInvoice->errors, 1);
						break;
					}
				}

				if (!$basculer_error) {
					$newInvoice->fetch($newInvoice->id);
					// Switch context so the page renders the new vente invoice
					$invoice    = $newInvoice;
					$placeid    = $newInvoice->id;
					$invoiceclass = 'Facture';
					$takeposmode  = 'vente';
				}
			}
		}

		// --- Step 5: switch session to vente mode ---
		if (!$basculer_error) {
			$_SESSION['takeposmode'] = 'vente';
			unset($_SESSION[$achat_session_key]);
			$db->commit();
		} else {
			$db->rollback();
		}
	}

	if ($action == "addnote" && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
		$desc = GETPOST('addnote', 'alpha');
		if ($idline == 0) {
			$invoice->update_note($desc, '_public');
		} else {
			foreach ($invoice->lines as $line) {
				if ($line->id == $idline) {
					if ($takeposmode === 'achat') {
						$result = $invoice->updateline($line->id, $desc, $line->subprice, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, $line->qty, $line->fk_product, 'HT', $line->info_bits, $line->product_type, $line->remise_percent, 0, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);
					} else {
						$result = $invoice->updateline($line->id, $desc, $line->subprice, $line->qty, $line->remise_percent, $line->date_start, $line->date_end, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
					}
				}
			}
		}
		$invoice->fetch($placeid);
	}

	if ($action == "deleteline" && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
		/*
		$permissiontoupdateline = ($user->hasRight('takepos', 'editlines') && ($user->hasRight('takepos', 'editorderedlines') || $line->special_code != "4"));
		if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
			if ($invoice->status == $invoice::STATUS_DRAFT && $invoice->pos_source && $invoice->module_source == 'takepos') {
				$permissiontoupdateline = true;
				// TODO Add also a test on $_SESSION('publicobjectid'] defined at creation of object
				// TODO Check also that invoice->ref is (PROV-POS1-2) with 1 = terminal and 2, the table ID
			}
		}*/

		if ($idline > 0 && $placeid > 0) { // If invoice exists and line selected. To avoid errors if deleted from another device or no line selected.
			$invoice->deleteLine($idline);
			$invoice->fetch($placeid);
		} elseif ($placeid > 0) {             // If invoice exists but no line selected, proceed to delete last line.
			$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facturedet where fk_facture = ".((int) $placeid)." ORDER BY rowid DESC";
			$resql = $db->query($sql);
			$row = $db->fetch_array($resql);
			$deletelineid = $row[0];
			$invoice->deleteLine($deletelineid);
			$invoice->fetch($placeid);
		}

		if (count($invoice->lines) == 0) {
			$invoice->delete($user);

			if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
				header("Location: ".DOL_URL_ROOT."/takepos/public/auto_order.php");
			} else {
				header("Location: ".DOL_URL_ROOT."/takepos/invoice.php");
			}
			exit;
		}
	}

	// Action to delete or discard an invoice
	if ($action == "delete" && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
		// $placeid is the invoice id (it differs from place) and is defined if the place is set and
		// the ref of invoice is '(PROV-POS'.$_SESSION["takeposterminal"].'-'.$place.')', so the fetch at beginning of page works.
		if ($placeid > 0) {
			$result = $invoice->fetch($placeid);

			if ($result > 0 && $invoice->status == $invoiceclass::STATUS_DRAFT) {
				$db->begin();

				// We delete the lines
				$resdeletelines = 1;
				foreach ($invoice->lines as $line) {
					// @phan-suppress-next-line PhanPluginSuspiciousParamPosition
					$tmpres = $invoice->deleteLine($line->id);
					if ($tmpres < 0) {
						$resdeletelines = 0;
						break;
					}
				}

				$varforconst = 'CASHDESK_ID_THIRDPARTY'.$_SESSION["takeposterminal"];
				if ($takeposmode === 'achat') {
					// Achat: clear session + delete the now-empty draft supplier invoice entirely
					unset($_SESSION[$achat_session_key]);
					// Delete the supplier invoice itself (lines already deleted above)
					$resql1 = $db->query("DELETE FROM ".MAIN_DB_PREFIX."facture_fourn WHERE rowid=".((int) $placeid)." AND fk_statut=0");
					if (!$resql1) {
						$resql1 = true; // don't block commit if delete fails, just proceed
					}
				} else {
					$sql = "UPDATE ".MAIN_DB_PREFIX."facture";
					$sql .= " SET fk_soc = ".((int) getDolGlobalString($varforconst)).", ";
					$sql .= " datec = '".$db->idate(dol_now())."'";
					$sql .= " WHERE entity IN (".getEntity('invoice').")";
					$sql .= " AND ref = '(PROV-POS".$db->escape($_SESSION["takeposterminal"]."-".$place).")'";
					$resql1 = $db->query($sql);
				}

				if ($resdeletelines && $resql1) {
					$db->commit();
				} else {
					$db->rollback();
				}

				if ($takeposmode === 'achat') {
					// Reinitialize object so lines array is empty — the invoice was deleted from DB
					$invoice = new $invoiceclass($db);
					$placeid = 0;
				} else {
					$invoice->fetch($placeid);
				}
			}
		}
	}

	if ($action == "updateqty") {	// Test on permission is done later
		foreach ($invoice->lines as $line) {
			if ($line->id == $idline) {
				$permissiontoupdateline = ($user->hasRight('takepos', 'editlines') && ($user->hasRight('takepos', 'editorderedlines') || $line->special_code != "4"));
				if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
					if ($invoice->status == $invoice::STATUS_DRAFT && $invoice->pos_source && $invoice->module_source == 'takepos') {
						$permissiontoupdateline = true;
						// TODO Add also a test on $_SESSION('publicobjectid'] defined at creation of object
						// TODO Check also that invoice->ref is (PROV-POS1-2) with 1 = terminal and 2, the table ID
					}
				}
				if (!$permissiontoupdateline) {
					dol_htmloutput_errors($langs->trans("NotEnoughPermissions", "TakePos").' - No permission to updateqty', [], 1);
				} else {
					$vatratecode = $line->tva_tx;
					if ($line->vat_src_code) {
						$vatratecode .= ' ('.$line->vat_src_code.')';
					}

					if ($takeposmode === 'achat') {
						$result = $invoice->updateline($line->id, $line->desc, $line->subprice, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, $number, $line->fk_product, 'HT', $line->info_bits, $line->product_type, $line->remise_percent, 0, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);
					} else {
						$result = $invoice->updateline($line->id, $line->desc, $line->subprice, $number, $line->remise_percent, $line->date_start, $line->date_end, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
					}
				}
			}
		}

		$invoice->fetch($placeid);
	}

	if ($action == "updateprice") {	// Test on permission is done later
		$customer = new Societe($db);
		$customer->fetch($invoice->socid);

		foreach ($invoice->lines as $line) {
			if ($line->id == $idline) {
				$prod = new Product($db);
				$prod->fetch($line->fk_product);
				$datapriceofproduct = $prod->getSellPrice($mysoc, $customer, 0);
				$price_min = $datapriceofproduct['price_min'];
				$usercanproductignorepricemin = ((getDolGlobalString('MAIN_USE_ADVANCED_PERMS') && !$user->hasRight('produit', 'ignore_price_min_advance')) || !getDolGlobalString('MAIN_USE_ADVANCED_PERMS'));

				$vatratecleaned = $line->tva_tx;
				$reg = array();
				if (preg_match('/^(.*)\s*\((.*)\)$/', (string) $line->tva_tx, $reg)) {     // If vat is "xx (yy)"
					$vatratecleaned = trim($reg[1]);
					//$vatratecode = $reg[2];
				}

				$pu_ht = price2num((float) price2num($number, 'MU') / (1 + ((float) $vatratecleaned / 100)), 'MU');
				// Check min price
				if ($usercanproductignorepricemin && (!empty($price_min) && ((float) price2num($pu_ht) * (1 - (float) price2num($line->remise_percent) / 100) < price2num($price_min)))) {
					$langs->load("products");
					dol_htmloutput_errors($langs->trans("CantBeLessThanMinPrice", price(price2num($price_min, 'MU'), 0, $langs, 0, 0, -1, $conf->currency)));
					// echo $langs->trans("CantBeLessThanMinPrice");
				} else {
					$permissiontoupdateline = ($user->hasRight('takepos', 'editlines') && ($user->hasRight('takepos', 'editorderedlines') || $line->special_code != "4"));
					if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
						if ($invoice->status == $invoice::STATUS_DRAFT && $invoice->pos_source && $invoice->module_source == 'takepos') {
							$permissiontoupdateline = true;
							// TODO Add also a test on $_SESSION('publicobjectid'] defined at creation of object
							// TODO Check also that invoice->ref is (PROV-POS1-2) with 1 = terminal and 2, the table ID
						}
					}

					$vatratecode = $line->tva_tx;
					if ($line->vat_src_code) {
						$vatratecode .= ' ('.$line->vat_src_code.')';
					}

					if (!$permissiontoupdateline) {
						dol_htmloutput_errors($langs->trans("NotEnoughPermissions", "TakePos").' - No permission to updateprice', [], 1);
					} elseif (getDolGlobalInt('TAKEPOS_CHANGE_PRICE_HT')  == 1) {
						if ($takeposmode === 'achat') {
							$result = $invoice->updateline($line->id, $line->desc, $number, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, $line->qty, $line->fk_product, 'HT', $line->info_bits, $line->product_type, $line->remise_percent, 0, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);
						} else {
							$result = $invoice->updateline($line->id, $line->desc, $number, $line->qty, $line->remise_percent, $line->date_start, $line->date_end, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
						}
					} else {
						if ($takeposmode === 'achat') {
							$result = $invoice->updateline($line->id, $line->desc, $number, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, $line->qty, $line->fk_product, 'TTC', $line->info_bits, $line->product_type, $line->remise_percent, 0, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);
						} else {
							$result = $invoice->updateline($line->id, $line->desc, $number, $line->qty, $line->remise_percent, $line->date_start, $line->date_end, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, 'TTC', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
						}
					}
				}
			}
		}

		// Reload data
		$invoice->fetch($placeid);
	}

	if ($action == "updatereduction") {	// Test on permission is done later
		$customer = new Societe($db);
		$customer->fetch($invoice->socid);

		foreach ($invoice->lines as $line) {
			if ($line->id == $idline) {
				dol_syslog("updatereduction Process line ".$line->id.' to apply discount of '.$number.'%');

				$prod = new Product($db);
				$prod->fetch($line->fk_product);

				$datapriceofproduct = $prod->getSellPrice($mysoc, $customer, 0);
				$price_min = $datapriceofproduct['price_min'];
				$usercanproductignorepricemin = ((getDolGlobalString('MAIN_USE_ADVANCED_PERMS') && !$user->hasRight('produit', 'ignore_price_min_advance')) || !getDolGlobalString('MAIN_USE_ADVANCED_PERMS'));

				$pu_ht = price2num($line->subprice / (1 + ($line->tva_tx / 100)), 'MU');

				// Check min price
				if ($usercanproductignorepricemin && (!empty($price_min) && ((float) price2num($line->subprice) * (1 - (float) price2num($number) / 100) < (float) price2num($price_min)))) {
					$langs->load("products");
					dol_htmloutput_errors($langs->trans("CantBeLessThanMinPrice", price(price2num($price_min, 'MU'), 0, $langs, 0, 0, -1, $conf->currency)));
				} else {
					$permissiontoupdateline = ($user->hasRight('takepos', 'editlines') && ($user->hasRight('takepos', 'editorderedlines') || $line->special_code != "4"));
					if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
						if ($invoice->status == $invoice::STATUS_DRAFT && $invoice->pos_source && $invoice->module_source == 'takepos') {
							$permissiontoupdateline = true;
							// TODO Add also a test on $_SESSION('publicobjectid'] defined at creation of object
							// TODO Check also that invoice->ref is (PROV-POS1-2) with 1 = terminal and 2, the table ID
						}
					}
					if (!$permissiontoupdateline) {
						dol_htmloutput_errors($langs->trans("NotEnoughPermissions", "TakePos"), [], 1);
					} else {
						$vatratecode = $line->tva_tx;
						if ($line->vat_src_code) {
							$vatratecode .= ' ('.$line->vat_src_code.')';
						}
						
						if ($takeposmode === 'achat') {
							$result = $invoice->updateline($line->id, $line->desc, $line->subprice, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, $line->qty, $line->fk_product, 'HT', $line->info_bits, $line->product_type, $number, 0, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);
						} else {
							$result = $invoice->updateline($line->id, $line->desc, $line->subprice, $line->qty, $number, $line->date_start, $line->date_end, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
						}
					}
				}
			}
		}

		// Reload data
		$invoice->fetch($placeid);
	} elseif ($action == 'update_reduction_global' && $user->hasRight('takepos', 'editlines')) {
		foreach ($invoice->lines as $line) {
			$vatratecode = $line->tva_tx;
			if ($line->vat_src_code) {
				$vatratecode .= ' ('.$line->vat_src_code.')';
			}
			
			if ($takeposmode === 'achat') {
				$result = $invoice->updateline($line->id, $line->desc, $line->subprice, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, $line->qty, $line->fk_product, 'HT', $line->info_bits, $line->product_type, $number, 0, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);
			} else {
				$result = $invoice->updateline($line->id, $line->desc, $line->subprice, $line->qty, $number, $line->date_start, $line->date_end, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
			}
		}

		$invoice->fetch($placeid);
	}

	if ($action == "setbatch" && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
		$constantforkey = 'CASHDESK_ID_WAREHOUSE'.$_SESSION["takeposterminal"];
		$warehouseid = (GETPOSTINT('warehouseid') > 0 ? GETPOSTINT('warehouseid') : getDolGlobalInt($constantforkey));	// Get the warehouse id from GETPOSTINT('warehouseid'), otherwise use default setup.
		$sql = "UPDATE ".MAIN_DB_PREFIX."facturedet SET batch = '".$db->escape($batch)."', fk_warehouse = ".((int) $warehouseid);
		$sql .= " WHERE rowid=".((int) $idoflineadded);
		$db->query($sql);
	}

	if ($action == "order" && $placeid != 0 && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
		include_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
		if ((isModEnabled('receiptprinter') && getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0) || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "receiptprinter" || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector") {
			require_once DOL_DOCUMENT_ROOT.'/core/class/dolreceiptprinter.class.php';
			$printer = new dolReceiptPrinter($db);
		}

		$sql = "SELECT label FROM ".MAIN_DB_PREFIX."takepos_floor_tables where rowid=".((int) $place);
		$resql = $db->query($sql);
		$row = $db->fetch_object($resql);
		$headerorder = '<html><br><b>'.$langs->trans('Place').' '.$row->label.'<br><table width="65%"><thead><tr><th class="left">'.$langs->trans("Label").'</th><th class="right">'.$langs->trans("Qty").'</th></tr></thead><tbody>';
		$footerorder = '</tbody></table>'.dol_print_date(dol_now(), 'dayhour').'<br></html>';
		$order_receipt_printer1 = "";
		$order_receipt_printer2 = "";
		$order_receipt_printer3 = "";
		$catsprinter1 = explode(';', getDolGlobalString('TAKEPOS_PRINTED_CATEGORIES_1'));
		$catsprinter2 = explode(';', getDolGlobalString('TAKEPOS_PRINTED_CATEGORIES_2'));
		$catsprinter3 = explode(';', getDolGlobalString('TAKEPOS_PRINTED_CATEGORIES_3'));
		$linestoprint = 0;
		foreach ($invoice->lines as $line) {
			if ($line->special_code == "4") {
				continue;
			}
			$c = new Categorie($db);
			$existing = $c->containing($line->fk_product, Categorie::TYPE_PRODUCT, 'id');
			$result = array_intersect($catsprinter1, $existing);
			$count = count($result);
			if (!$line->fk_product) {
				$count++; // Print Free-text item (Unassigned printer) to Printer 1
			}
			if ($count > 0) {
				$linestoprint++;
				$sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='1' where rowid=".$line->id; //Set to print on printer 1
				$db->query($sql);
				$order_receipt_printer1 .= '<tr><td class="left">';
				if ($line->fk_product) {
					$order_receipt_printer1 .= $line->product_label;
				} else {
					$order_receipt_printer1 .= $line->description;
				}
				$order_receipt_printer1 .= '</td><td class="right">'.$line->qty;
				if (!empty($line->array_options['options_order_notes'])) {
					$order_receipt_printer1 .= "<br>(".$line->array_options['options_order_notes'].")";
				}
				$order_receipt_printer1 .= '</td></tr>';
			}
		}
		if (((isModEnabled('receiptprinter') && getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0) || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "receiptprinter" || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector") && $linestoprint > 0 && $printer !== null) {
			$invoice->fetch($placeid); //Reload object before send to printer
			$printer->orderprinter = 1;
			echo "<script>";
			echo "var orderprinter1esc='";
			$ret = $printer->sendToPrinter($invoice, getDolGlobalInt('TAKEPOS_TEMPLATE_TO_USE_FOR_ORDERS'.$_SESSION["takeposterminal"]), getDolGlobalInt('TAKEPOS_ORDER_PRINTER1_TO_USE'.$_SESSION["takeposterminal"])); // PRINT TO PRINTER 1
			echo "';</script>";
		}
		$sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='4' where special_code='1' and fk_facture=".$invoice->id; // Set as printed
		$db->query($sql);
		$invoice->fetch($placeid); //Reload object after set lines as printed
		$linestoprint = 0;

		foreach ($invoice->lines as $line) {
			if ($line->special_code == "4") {
				continue;
			}
			$c = new Categorie($db);
			$existing = $c->containing($line->fk_product, Categorie::TYPE_PRODUCT, 'id');
			$result = array_intersect($catsprinter2, $existing);
			$count = count($result);
			if ($count > 0) {
				$linestoprint++;
				$sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='2' where rowid=".$line->id; //Set to print on printer 2
				$db->query($sql);
				$order_receipt_printer2 .= '<tr>'.$line->product_label.'<td class="right">'.$line->qty;
				if (!empty($line->array_options['options_order_notes'])) {
					$order_receipt_printer2 .= "<br>(".$line->array_options['options_order_notes'].")";
				}
				$order_receipt_printer2 .= '</td></tr>';
			}
		}
		if (((isModEnabled('receiptprinter') && getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0) || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "receiptprinter" || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector") && $linestoprint > 0) {
			$invoice->fetch($placeid); //Reload object before send to printer
			$printer->orderprinter = 2;
			echo "<script>";
			echo "var orderprinter2esc='";
			$ret = $printer->sendToPrinter($invoice, getDolGlobalInt('TAKEPOS_TEMPLATE_TO_USE_FOR_ORDERS'.$_SESSION["takeposterminal"]), getDolGlobalInt('TAKEPOS_ORDER_PRINTER2_TO_USE'.$_SESSION["takeposterminal"])); // PRINT TO PRINTER 2
			echo "';</script>";
		}
		$sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='4' where special_code='2' and fk_facture=".$invoice->id; // Set as printed
		$db->query($sql);
		$invoice->fetch($placeid); //Reload object after set lines as printed
		$linestoprint = 0;

		foreach ($invoice->lines as $line) {
			if ($line->special_code == "4") {
				continue;
			}
			$c = new Categorie($db);
			$existing = $c->containing($line->fk_product, Categorie::TYPE_PRODUCT, 'id');
			$result = array_intersect($catsprinter3, $existing);
			$count = count($result);
			if ($count > 0) {
				$linestoprint++;
				$sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='3' where rowid=".$line->id; //Set to print on printer 3
				$db->query($sql);
				$order_receipt_printer3 .= '<tr>'.$line->product_label.'<td class="right">'.$line->qty;
				if (!empty($line->array_options['options_order_notes'])) {
					$order_receipt_printer3 .= "<br>(".$line->array_options['options_order_notes'].")";
				}
				$order_receipt_printer3 .= '</td></tr>';
			}
		}
		if (((isModEnabled('receiptprinter') && getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0) || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "receiptprinter" || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector") && $linestoprint > 0 && $printer !== null) {
			$invoice->fetch($placeid); //Reload object before send to printer
			$printer->orderprinter = 3;
			echo "<script>";
			echo "var orderprinter3esc='";
			$ret = $printer->sendToPrinter($invoice, getDolGlobalInt('TAKEPOS_TEMPLATE_TO_USE_FOR_ORDERS'.$_SESSION["takeposterminal"]), getDolGlobalInt('TAKEPOS_ORDER_PRINTER3_TO_USE'.$_SESSION["takeposterminal"])); // PRINT TO PRINTER 3
			echo "';</script>";
		}
		$sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='4' where special_code='3' and fk_facture=".$invoice->id; // Set as printed
		$db->query($sql);
		$invoice->fetch($placeid); //Reload object after set lines as printed
	}

	$sectionwithinvoicelink = '';
	if (($action == "valid" || $action == "history" || $action == 'creditnote' || ($action == 'addline' && $invoice->status == $invoice::STATUS_CLOSED)) && $user->hasRight('takepos', 'run')) {
		$sectionwithinvoicelink .= '<!-- Section with invoice link -->'."\n";
		$sectionwithinvoicelink .= '<span style="font-size:120%;" class="center inline-block marginbottomonly">';
		$sectionwithinvoicelink .= $invoice->getNomUrl(1, '', 0, 0, '', 0, 0, -1, '_backoffice')." - ";
		$remaintopay = $invoice->getRemainToPay();
		if ($remaintopay > 0) {
			$sectionwithinvoicelink .= $langs->trans('RemainToPay').': <span class="amountremaintopay" style="font-size: unset">'.price($remaintopay, 1, $langs, 1, -1, -1, $conf->currency).'</span>';
		} else {
			$sectionwithinvoicelink .= $invoice->getLibStatut(2);
		}

		$sectionwithinvoicelink .= '</span><br>';
		if (getDolGlobalInt('TAKEPOS_PRINT_INVOICE_DOC_INSTEAD_OF_RECEIPT')) {
			$sectionwithinvoicelink .= ' <a target="_blank" class="button" href="' . DOL_URL_ROOT . '/document.php?token=' . newToken() . '&modulepart=facture&file=' . $invoice->ref . '/' . $invoice->ref . '.pdf">Invoice</a>';
		} elseif (getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector") {
			if (getDolGlobalString('TAKEPOS_PRINT_SERVER') && filter_var(getDolGlobalString('TAKEPOS_PRINT_SERVER'), FILTER_VALIDATE_URL) == true) {
				$sectionwithinvoicelink .= ' <button id="buttonprint" type="button" onclick="TakeposConnector('.$placeid.')">'.$langs->trans('PrintTicket').'</button>';
			} else {
				$sectionwithinvoicelink .= ' <button id="buttonprint" type="button" onclick="TakeposPrinting('.$placeid.')">'.$langs->trans('PrintTicket').'</button>';
			}
		} elseif ((isModEnabled('receiptprinter') && getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0) || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "receiptprinter") {
			$sectionwithinvoicelink .= ' <button id="buttonprint" type="button" onclick="DolibarrTakeposPrinting('.$placeid.')">'.$langs->trans('PrintTicket').'</button>';
		} else {
			$sectionwithinvoicelink .= ' <button id="buttonprint" type="button" onclick="Print('.$placeid.')">'.$langs->trans('PrintTicket').'</button>';
			if (getDolGlobalString('TAKEPOS_PRINT_WITHOUT_DETAILS')) {
				$sectionwithinvoicelink .= ' <button id="buttonprint" type="button" onclick="PrintBox('.$placeid.', \'without_details\')">'.$langs->trans('PrintWithoutDetails').'</button>';
			}
			if (getDolGlobalString('TAKEPOS_GIFT_RECEIPT')) {
				$sectionwithinvoicelink .= ' <button id="buttonprint" type="button" onclick="Print('.$placeid.', 1)">'.$langs->trans('GiftReceipt').'</button>';
			}
		}
		if (getDolGlobalString('TAKEPOS_EMAIL_TEMPLATE_INVOICE') && getDolGlobalInt('TAKEPOS_EMAIL_TEMPLATE_INVOICE') > 0) {
			$sectionwithinvoicelink .= ' <button id="buttonsend" type="button" onclick="SendTicket('.$placeid.')">'.$langs->trans('SendTicket').'</button>';
		}

		if ($remaintopay <= 0 && getDolGlobalString('TAKEPOS_AUTO_PRINT_TICKETS') && $action != "history") {
			$sectionwithinvoicelink .= '<script type="text/javascript">$("#buttonprint").click();</script>';
		}
	}
}


/*
 * View
 */

$form = new Form($db);

// llxHeader
if ((getDolGlobalString('TAKEPOS_PHONE_BASIC_LAYOUT') == 1 && $conf->browser->layout == 'phone') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
	$title = 'TakePOS - Dolibarr '.DOL_VERSION;
	if (getDolGlobalString('MAIN_APPLICATION_TITLE')) {
		$title = 'TakePOS - ' . getDolGlobalString('MAIN_APPLICATION_TITLE');
	}
	$head = '<meta name="apple-mobile-web-app-title" content="TakePOS"/>
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>';
	$arrayofcss = array(
		'/takepos/css/pos.css.php',
	);
	$arrayofjs = array('/takepos/js/jquery.colorbox-min.js');
	$disablejs = 0;
	$disablehead = 0;
	top_htmlhead($head, $title, $disablejs, $disablehead, $arrayofjs, $arrayofcss);

	print '<body>'."\n";
} else {
	top_httphead('text/html', 1);
}

?>
<!-- invoice.php -->
<script type="text/javascript">
var selectedline=0;
var selectedtext="";
<?php if ($action == "valid") {
	echo "var place=0;";
}?> // Set to default place after close sale
var placeid=<?php echo($placeid > 0 ? $placeid : 0); ?>;
$(document).ready(function() {
	var idoflineadded = <?php echo(empty($idoflineadded) ? 0 : $idoflineadded); ?>;

	$('.posinvoiceline').click(function(){
		console.log("Click done on "+this.id);
		$('.posinvoiceline').removeClass("selected");
		$(this).addClass("selected");
		if (!this.id) {
			return;
		}
		if (selectedline == this.id) {
			return; // If is already selected
		} else {
			selectedline = this.id;
		}
		selectedtext=$('#'+selectedline).find("td:first").html();
		<?php
		if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
			print '$("#phonediv1").load("'.DOL_URL_ROOT.'/takepos/public/auto_order.php?action=editline&token='.newToken().'&placeid="+placeid+"&selectedline="+selectedline, function() {
			});';
		}
		?>
		// Ouvrir la barre d'édition inline
		if (typeof openInlineEdit === 'function') {
			var label = $(this).data('label') || $(this).find("td:first").text().trim().substring(0, 60);
			openInlineEdit(
				this.id,
				label,
				$(this).data('qty'),
				$(this).data('price'),
				$(this).data('reduction')
			);
		}
	});

	/* Autoselect the line */
	if (idoflineadded > 0)
	{
		console.log("Auto select "+idoflineadded);
		$('.posinvoiceline#'+idoflineadded).click();
	}
<?php

if ($action == "order" && !empty($order_receipt_printer1)) {
	if (filter_var(getDolGlobalString('TAKEPOS_PRINT_SERVER'), FILTER_VALIDATE_URL) == true) {
		?>
		$.ajax({
			type: "POST",
			url: '<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>/printer/index.php',
			data: 'invoice='+orderprinter1esc
		});
		<?php
	} else {
		?>
		$.ajax({
			type: "POST",
			url: 'http://<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>:8111/print',
			data: '<?php
			print $headerorder.$order_receipt_printer1.$footerorder; ?>'
		});
		<?php
	}
}

if ($action == "order" && !empty($order_receipt_printer2)) {
	if (filter_var(getDolGlobalString('TAKEPOS_PRINT_SERVER'), FILTER_VALIDATE_URL) == true) {
		?>
		$.ajax({
			type: "POST",
			url: '<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>/printer/index.php?printer=2',
			data: 'invoice='+orderprinter2esc
		});
		<?php
	} else {
		?>
		$.ajax({
			type: "POST",
			url: 'http://<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>:8111/print2',
			data: '<?php
			print $headerorder.$order_receipt_printer2.$footerorder; ?>'
		});
		<?php
	}
}

if ($action == "order" && !empty($order_receipt_printer3)) {
	if (filter_var(getDolGlobalString('TAKEPOS_PRINT_SERVER'), FILTER_VALIDATE_URL) == true) {
		?>
		$.ajax({
			type: "POST",
			url: '<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>/printer/index.php?printer=3',
			data: 'invoice='+orderprinter3esc
		});
		<?php
	}
}

// Set focus to search field
if ($action == "search" || $action == "valid") {
	?>
	parent.ClearSearch(true);
	<?php
}


if ($action == "temp" && !empty($ticket_printer1)) {
	?>
	$.ajax({
		type: "POST",
		url: 'http://<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>:8111/print',
		data: '<?php
		print $header_soc.$header_ticket.$body_ticket.$ticket_printer1.$ticket_total.$footer_ticket; ?>'
	});
	<?php
}

if ($action == "search") {
	?>
	$('#search').focus();
	<?php
}

?>

});

function SendTicket(id)
{
	console.log("Open box to select the Print/Send form");
	$.colorbox({href:"send.php?facid="+id, width:"70%", height:"30%", transition:"none", iframe:"true", title:'<?php echo dol_escape_js($langs->trans("SendTicket")); ?>'});
	return true;
}

function PrintBox(id, action) {
	console.log("Open box before printing");
	$.colorbox({href:"printbox.php?facid="+id+"&action="+action+"&token=<?php echo newToken(); ?>", width:"80%", height:"200px", transition:"none", iframe:"true", title:"<?php echo $langs->trans("PrintWithoutDetails"); ?>"});
	return true;
}

function Print(id, gift){
	console.log("Call Print() to generate the receipt.");
	$.colorbox({href:"receipt.php?facid="+id+"&gift="+gift, width:"40%", height:"90%", transition:"none", iframe:"true", title:'<?php echo dol_escape_js($langs->trans("PrintTicket")); ?>'});
	return true;
}

function TakeposPrinting(id){
	var receipt;
	console.log("TakeposPrinting" + id);
	$.get("receipt.php?facid="+id, function(data, status) {
		receipt=data.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '');
		$.ajax({
			type: "POST",
			url: 'http://<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>:8111/print',
			data: receipt
		});
	});
	return true;
}

function TakeposConnector(id){
	console.log("TakeposConnector" + id);
	$.get("<?php echo DOL_URL_ROOT; ?>/takepos/ajax/ajax.php?action=printinvoiceticket&token=<?php echo newToken(); ?>&term=<?php echo urlencode(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : ''); ?>&id="+id+"&token=<?php echo currentToken(); ?>", function(data, status) {
		$.ajax({
			type: "POST",
			url: '<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>/printer/index.php',
			data: 'invoice='+data
		});
	});
	return true;
}

// Call the ajax to execute the print.
// With some external module another method may be called.
function DolibarrTakeposPrinting(id) {
	console.log("DolibarrTakeposPrinting Printing invoice ticket " + id);
	$.ajax({
		type: "GET",
		data: { token: '<?php echo currentToken(); ?>' },
		url: "<?php print DOL_URL_ROOT.'/takepos/ajax/ajax.php?action=printinvoiceticket&token='.newToken().'&term='.urlencode(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '').'&id='; ?>" + id,

	});
	return true;
}

// Call url to generate a credit note (with same lines) from existing invoice
function CreditNote() {
	$("#poslines").load("<?php print DOL_URL_ROOT; ?>/takepos/invoice.php?action=creditnote&token=<?php echo newToken() ?>&invoiceid="+placeid, function() {	});
	return true;
}

// Call url to add notes
function SetNote() {
	$("#poslines").load("<?php print DOL_URL_ROOT; ?>/takepos/invoice.php?action=addnote&token=<?php echo newToken() ?>&invoiceid="+placeid+"&idline="+selectedline, { "addnote": $("#textinput").val() });
	return true;
}


$( document ).ready(function() {
	console.log("Set customer info and sales in header placeid=<?php echo $placeid; ?> status=<?php echo $invoice->statut; ?>");

	<?php
	if ($takeposmode === 'achat') {
		// Achat mode: show supplier name
		$s = $langs->trans("Supplier");
		if ($invoice->id > 0 && $invoice->socid > 0) {
			$s = $soc->name;
		}
	} else {
		// Vente mode: show customer name
		$s = $langs->trans("Customer");
		if ($invoice->id > 0 && ($invoice->socid != getDolGlobalString($constforcompanyid))) {
			$s = $soc->name;
			if (getDolGlobalInt('TAKEPOS_CHOOSE_CONTACT')) {
				$contactids = $invoice->getIdContact('external', 'BILLING');
				$contactid = $contactids[0];
				if ($contactid > 0) {
					$contact = new Contact($db);
					$contact->fetch($contactid);
					$s .= " - " . $contact->getFullName($langs);
				}
			}
		} elseif (getDolGlobalInt("TAKEPOS_NO_GENERIC_THIRDPARTY")) {
			print '$("#idcustomer").val("");';
		}
	}
	?>

	$("#customerandsales").html('');
	$("#shoppingcart").html('');

	<?php if ($takeposmode === 'achat') { ?>
		$("#customerandsales").append('<a class="valignmiddle tdoverflowmax100 minwidth100" id="customer" onclick="Fournisseur();" title="<?php print dol_escape_js(dol_escape_htmltag((string) $s)); ?>"><span class="fas fa-truck paddingrightonly"></span><?php print dol_escape_js((string) $s); ?></a>');
	<?php } elseif (getDolGlobalInt('TAKEPOS_CHOOSE_CONTACT') == 0) { ?>
		$("#customerandsales").append('<a class="valignmiddle tdoverflowmax100 minwidth100" id="customer" onclick="Customer();" title="<?php print dol_escape_js(dol_escape_htmltag((string) $s)); ?>"><span class="fas fa-building paddingrightonly"></span><?php print dol_escape_js((string) $s); ?></a>');
	<?php } else { ?>
		$("#customerandsales").append('<a class="valignmiddle tdoverflowmax300 minwidth100" id="contact" onclick="Contact();" title="<?php print dol_escape_js(dol_escape_htmltag((string) $s)); ?>"><span class="fas fa-building paddingrightonly"></span><?php print dol_escape_js((string) $s); ?></a>');
	<?php } ?>

	<?php
	$sql = "SELECT rowid, datec, ref FROM ".MAIN_DB_PREFIX."facture";
	$sql .= " WHERE entity IN (".getEntity('invoice').")";
	if (!getDolGlobalString('TAKEPOS_CAN_EDIT_IF_ALREADY_VALIDATED')) {
		// By default, only invoices with a ref not already defined can in list of open invoice we can edit.
		$sql .= " AND ref LIKE '(PROV-POS".$db->escape(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '')."-0%'";
	} else {
		// If TAKEPOS_CAN_EDIT_IF_ALREADY_VALIDATED set, we show also draft invoice that already has a reference defined
		$sql .= " AND pos_source = '".$db->escape((string) $_SESSION["takeposterminal"])."'";
		$sql .= " AND module_source = 'takepos'";
	}

	$sql .= $db->order('datec', 'ASC');
	$resql = $db->query($sql);
	if ($resql) {
		$max_sale = 0;
		while ($obj = $db->fetch_object($resql)) {
			echo '$("#shoppingcart").append(\'';
			echo '<a class="valignmiddle" title="'.dol_escape_js($langs->trans("SaleStartedAt", dol_print_date($db->jdate($obj->datec), '%H:%M', 'tzuser')).' - '.$obj->ref).'" onclick="place=\\\'';
			$num_sale = str_replace(")", "", str_replace("(PROV-POS".$_SESSION["takeposterminal"]."-", "", $obj->ref));
			echo $num_sale;
			if (str_replace("-", "", $num_sale) > $max_sale) {
				$max_sale = str_replace("-", "", $num_sale);
			}
			echo '\\\'; invoiceid=\\\'';
			echo $obj->rowid;
			echo '\\\'; Refresh();">';
			if ($placeid == $obj->rowid) {
				echo '<span class="basketselected">';
			} else {
				echo '<span class="basketnotselected">';
			}
			echo '<span class="fa fa-shopping-cart paddingright"></span>'.dol_print_date($db->jdate($obj->datec), '%H:%M', 'tzuser');
			echo '</span>';
			echo '</a>\');';
		}
		echo '$("#shoppingcart").append(\'<a onclick="place=\\\'0-';
		echo $max_sale + 1;
		echo '\\\'; invoiceid=0; Refresh();"><div><span class="fa fa-plus" title="'.dol_escape_htmltag($langs->trans("StartAParallelSale")).'"><span class="fa fa-shopping-cart"></span></div></a>\');';
	} else {
		dol_print_error($db);
	}

	$s = '';

	$idwarehouse = 0;
	$constantforkey = 'CASHDESK_NO_DECREASE_STOCK'. (isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '');
	if (isModEnabled('stock')) {
		if (getDolGlobalString($constantforkey) != "1") {
			$constantforkey = 'CASHDESK_ID_WAREHOUSE'. (isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '');
			$idwarehouse = getDolGlobalInt($constantforkey);
			if ($idwarehouse > 0) {
				$s = '<span class="small">';
				$warehouse = new Entrepot($db);
				$warehouse->fetch($idwarehouse);
				$s .= '<span class="hideonsmartphone">'.$langs->trans("Warehouse").'<br></span>'.$warehouse->ref;
				if ($warehouse->statut == Entrepot::STATUS_CLOSED) {
					$s .= ' ('.$langs->trans("Closed").')';
				}
				$s .= '</span>';
				print "$('#infowarehouse').html('".dol_escape_js($s)."');";
				print '$("#infowarehouse").css("display", "inline-block");';
			} else {
				$s = '<span class="small hideonsmartphone">';
				$s .= $langs->trans("StockChangeDisabled").'<br>'.$langs->trans("NoWarehouseDefinedForTerminal");
				$s .= '</span>';
				print "$('#infowarehouse').html('".dol_escape_js($s)."');";
				if (!empty($conf->dol_optimize_smallscreen)) {
					print '$("#infowarehouse").css("display", "none");';
				}
			}
		} else {
			$s = '<span class="small hideonsmartphone">'.$langs->trans("StockChangeDisabled").'</span>';
			print "$('#infowarehouse').html('".dol_escape_js($s)."');";
			if (!empty($conf->dol_optimize_smallscreen)) {
				print '$("#infowarehouse").css("display", "none");';
			}
		}
	}


	// Module Adherent
	$s = '';
	if (isModEnabled('member') && $invoice->socid > 0 && $invoice->socid != getDolGlobalInt($constforcompanyid)) {
		$s = '<span class="small">';
		require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';
		$langs->load("members");
		$s .= $langs->trans("Member").': ';
		$adh = new Adherent($db);
		$result = $adh->fetch(0, '', $invoice->socid);
		if ($result > 0) {
			$adh->ref = $adh->getFullName($langs);
			if (empty($adh->statut) || $adh->statut == Adherent::STATUS_EXCLUDED) {
				$s .= "<s>";
			}
			$s .= $adh->getFullName($langs);
			$s .= ' - '.$adh->type;
			if ($adh->datefin) {
				$s .= '<br>'.$langs->trans("SubscriptionEndDate").': '.dol_print_date($adh->datefin, 'day');
				if ($adh->hasDelay()) {
					$s .= " ".img_warning($langs->trans("Late"));
				}
			} else {
				$s .= '<br>'.$langs->trans("SubscriptionNotReceived");
				if ($adh->statut > 0) {
					$s .= " ".img_warning($langs->trans("Late")); // displays delay Pictogram only if not a draft and not terminated
				}
			}
			if (empty($adh->statut) || $adh->statut == Adherent::STATUS_EXCLUDED) {
				$s .= "</s>";
			}
		} else {
			$s .= '<br>'.$langs->trans("ThirdpartyNotLinkedToMember");
		}
		$s .= '</span>';
	}
	?>
	$("#moreinfo").html('<?php print dol_escape_js($s); ?>');

});


<?php
if (getDolGlobalString('TAKEPOS_CUSTOMER_DISPLAY')) {
	echo "function CustomerDisplay(){";
	echo "var line1='".$CUSTOMER_DISPLAY_line1."'.substring(0,20);";
	echo "line1=line1.padEnd(20);";
	echo "var line2='".$CUSTOMER_DISPLAY_line2."'.substring(0,20);";
	echo "line2=line2.padEnd(20);";
	echo "$.ajax({
		type: 'GET',
		data: { text: line1+line2 },
		url: '".getDolGlobalString('TAKEPOS_PRINT_SERVER')."/display/index.php',
	});";
	echo "}";
}
?>

</script>

<?php
// Add again js for footer because this content is injected into index.php page so all init
// for tooltip and other js beautifiers must be reexecuted too.
if (!empty($conf->use_javascript_ajax)) {
	print "\n".'<!-- Includes JS Footer of Dolibarr -->'."\n";
	print '<script src="'.DOL_URL_ROOT.'/core/js/lib_foot.js.php?lang='.$langs->defaultlang.'"></script>'."\n";
}

$usediv = (GETPOST('format') == 'div');

print '<!-- invoice.php place='.(int) $place.' invoice='.$invoice->ref.' usediv='.json_encode($usediv).', mobilepage='.(empty($mobilepage) ? '' : $mobilepage).' $_SESSION["basiclayout"]='.(empty($_SESSION["basiclayout"]) ? '' : $_SESSION["basiclayout"]).' conf TAKEPOS_BAR_RESTAURANT='.getDolGlobalString('TAKEPOS_BAR_RESTAURANT').' -->'."\n";
print '<div class="div-table-responsive-no-min invoice">';
if ($usediv) {
	print '<div id="tablelines">';
} else {
	print '<table id="tablelines" class="noborder noshadow postablelines centpercent">';
}

$buttontocreatecreditnote = '';
if (($action == "valid" || $action == "history" ||  ($action == "addline" && $invoice->status == $invoice::STATUS_CLOSED)) && $invoice->type != $invoiceclass::TYPE_CREDIT_NOTE && !getDolGlobalString('TAKEPOS_NO_CREDITNOTE')) {
	$buttontocreatecreditnote .= ' &nbsp; <!-- Show button to create a credit note -->'."\n";
	$buttontocreatecreditnote .= '<button id="buttonprint" type="button" onclick="ModalBox(\'ModalCreditNote\')">'.$langs->trans('CreateCreditNote').'</button>';
	if (getDolGlobalInt('TAKEPOS_PRINT_INVOICE_DOC_INSTEAD_OF_RECEIPT')) {
		$buttontocreatecreditnote .= ' <a target="_blank" class="button" href="' . DOL_URL_ROOT . '/document.php?token=' . newToken() . '&modulepart=facture&file=' . urlencode($invoice->ref . '/' . $invoice->ref . '.pdf').'">'.$langs->trans("Invoice").'</a>';
	}
}

// Show the ref of invoice
if ($sectionwithinvoicelink && ($mobilepage == "invoice" || $mobilepage == "")) {
	print '<!-- Print table line with link to invoice ref -->';
	if (getDolGlobalString('TAKEPOS_SHOW_HT')) {
		print '<tr><td colspan="5" class="paddingtopimp paddingbottomimp" style="padding-top: 10px !important; padding-bottom: 10px !important;">';
		print $sectionwithinvoicelink;
		print $buttontocreatecreditnote;
		print '</td></tr>';
	} else {
		print '<tr><td colspan="4" class="paddingtopimp paddingbottomimp" style="padding-top: 10px !important; padding-bottom: 10px !important;">';
		print $sectionwithinvoicelink;
		print $buttontocreatecreditnote;
		print '</td></tr>';
	}
}

// Show the list of selected product
if (!$usediv) {
	print '<tr class="liste_titre nodrag nodrop">';
	print '<td class="linecoldescription">';
}
// In phone version only show when it is invoice page
if (empty($mobilepage) || $mobilepage == "invoice") {
	print '<!-- hidden var used by some js functions -->';
	print '<input type="hidden" name="invoiceid" id="invoiceid" value="'.$invoice->id.'">';
	print '<input type="hidden" name="thirdpartyid" id="thirdpartyid" value="'.$invoice->socid.'">';
}
if (!$usediv) {
	if (getDolGlobalString('TAKEPOS_BAR_RESTAURANT')) {
		$sql = "SELECT floor, label FROM ".MAIN_DB_PREFIX."takepos_floor_tables where rowid=".((int) $place);
		$resql = $db->query($sql);
		$obj = $db->fetch_object($resql);
		if ($obj) {
			$label = $obj->label;
			$floor = $obj->floor;
		}
		if ($mobilepage == "invoice" || $mobilepage == "") {
			// If not on smartphone version or if it is the invoice page
			//print 'mobilepage='.$mobilepage;
			print '<span class="opacitymedium">'.$langs->trans('Place')."</span> <b>".(empty($label) ? '?' : $label)."</b><br>";
			print '<span class="opacitymedium">'.$langs->trans('Floor')."</span> <b>".(empty($floor) ? '?' : $floor)."</b>";
		}
	}
	print '</td>';
}

// Complete header by hook
$parameters = array();
$reshook = $hookmanager->executeHooks('completeTakePosInvoiceHeader', $parameters, $invoice, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
print $hookmanager->resPrint;

if (empty($_SESSION["basiclayout"]) || $_SESSION["basiclayout"] != 1) {
	if (getDolGlobalInt("TAKEPOS_SHOW_SUBPRICE")) {
		print '<td class="linecolqty right">'.$langs->trans('PriceUHT').'</td>';
	}
	print '<td class="linecolqty right">'.$langs->trans('ReductionShort').'</td>';
	print '<td class="linecolqty right">'.$langs->trans('Qty').'</td>';
	if (getDolGlobalString('TAKEPOS_SHOW_HT')) {
		print '<td class="linecolht right nowraponall">';
		print '<span class="opacitymedium small">' . $langs->trans('TotalHTShort') . '</span><br>';
		// In phone version only show when it is invoice page
		if (empty($mobilepage) || $mobilepage == "invoice") {
			print '<span id="linecolht-span-total" style="font-size:1.3em; font-weight: bold;">' . price($invoice->total_ht, 1, '', 1, -1, -1, $conf->currency) . '</span>';
			if (isModEnabled('multicurrency') && !empty($_SESSION["takeposcustomercurrency"]) && $conf->currency != $_SESSION["takeposcustomercurrency"]) {
				//Only show customer currency if multicurrency module is enabled, if currency selected and if this currency selected is not the same as main currency
				include_once DOL_DOCUMENT_ROOT . '/multicurrency/class/multicurrency.class.php';
				$multicurrency = new MultiCurrency($db);
				$multicurrency->fetch(0, $_SESSION["takeposcustomercurrency"]);
				print '<br><span id="linecolht-span-total" style="font-size:0.9em; font-style:italic;">(' . price($invoice->total_ht * $multicurrency->rate->rate) . ' ' . $_SESSION["takeposcustomercurrency"] . ')</span>';
			}
		}
		print '</td>';
	}
	print '<td class="linecolht right nowraponall">';
	print '<span class="opacitymedium small">'.$langs->trans('TotalTTCShort').'</span><br>';
	// In phone version only show when it is invoice page
	if (empty($mobilepage) || $mobilepage == "invoice") {
		print '<span id="linecolht-span-total" style="font-size:1.3em; font-weight: bold;">'.price($invoice->total_ttc, 1, '', 1, -1, -1, $conf->currency).'</span>';
		if (isModEnabled('multicurrency') && !empty($_SESSION["takeposcustomercurrency"]) && $conf->currency != $_SESSION["takeposcustomercurrency"]) {
			//Only show customer currency if multicurrency module is enabled, if currency selected and if this currency selected is not the same as main currency
			include_once DOL_DOCUMENT_ROOT.'/multicurrency/class/multicurrency.class.php';
			$multicurrency = new MultiCurrency($db);
			$multicurrency->fetch(0, $_SESSION["takeposcustomercurrency"]);
			print '<br><span id="linecolht-span-total" style="font-size:0.9em; font-style:italic;">('.price($invoice->total_ttc * $multicurrency->rate->rate).' '.$_SESSION["takeposcustomercurrency"].')</span>';
		}
	}
	print '</td>';
} elseif ($mobilepage == "invoice") {
	print '<td class="linecolqty right">'.$langs->trans('Qty').'</td>';
}
if (!$usediv) {
	print "</tr>\n";
}

if (!empty($_SESSION["basiclayout"]) && $_SESSION["basiclayout"] == 1) {
	if ($mobilepage == "cats") {
		require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
		$categorie = new Categorie($db);
		$categories = $categorie->get_full_arbo('product');
		$htmlforlines = '';
		foreach ($categories as $row) {
			if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
				$htmlforlines .= '<div class="leftcat"';
			} else {
				$htmlforlines .= '<tr class="drag drop oddeven posinvoiceline"';
			}
			$htmlforlines .= ' onclick="LoadProducts('.$row['id'].');">';
			if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
				$htmlforlines .= '<img class="imgwrapper" width="33%" src="'.DOL_URL_ROOT.'/takepos/public/auto_order.php?genimg=cat&query=cat&id='.$row['id'].'"><br>';
			} else {
				$htmlforlines .= '<td class="left">';
			}
			$htmlforlines .= $row['label'];
			if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
				$htmlforlines .= '</div>'."\n";
			} else {
				$htmlforlines .= '</td></tr>'."\n";
			}
		}
		print $htmlforlines;
	}

	if ($mobilepage == "products") {
		require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
		$object = new Categorie($db);
		$catid = GETPOSTINT('catid');
		$result = $object->fetch($catid);
		$prods = $object->getObjectsInCateg("product");
		$htmlforlines = '';
		foreach ($prods as $row) {
			if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
				$htmlforlines .= '<div class="leftcat"';
			} else {
				$htmlforlines .= '<tr class="drag drop oddeven posinvoiceline"';
			}
			$htmlforlines .= ' onclick="AddProduct(\''.$place.'\', '.$row->id.')"';
			$htmlforlines .= '>';
			if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
				$htmlforlines .= '<img class="imgwrapper" width="33%" src="'.DOL_URL_ROOT.'/takepos/public/auto_order.php?genimg=pro&query=pro&id='.$row->id.'"><br>';
				$htmlforlines .= $row->label.' '.price($row->price_ttc, 1, $langs, 1, -1, -1, $conf->currency);
				$htmlforlines .= '</div>'."\n";
			} else {
				$htmlforlines .= '<td class="left">';
				$htmlforlines .= $row->label;
				$htmlforlines .= '<div class="right">'.price($row->price_ttc, 1, $langs, 1, -1, -1, $conf->currency).'</div>';
				$htmlforlines .= '</td>';
				$htmlforlines .= '</tr>'."\n";
			}
		}
		print $htmlforlines;
	}

	if ($mobilepage == "places") {
		$sql = "SELECT rowid, entity, label, leftpos, toppos, floor FROM ".MAIN_DB_PREFIX."takepos_floor_tables";
		$resql = $db->query($sql);

		$rows = array();
		$htmlforlines = '';
		while ($row = $db->fetch_array($resql)) {
			$rows[] = $row;
			$htmlforlines .= '<tr class="drag drop oddeven posinvoiceline';
			$htmlforlines .= '" onclick="LoadPlace(\''.$row['label'].'\')">';
			$htmlforlines .= '<td class="left">';
			$htmlforlines .= $row['label'];
			$htmlforlines .= '</td>';
			$htmlforlines .= '</tr>'."\n";
		}
		print $htmlforlines;
	}
}

if ($placeid > 0) {
	//In Phone basic layout hide some content depends situation
	if (!empty($_SESSION["basiclayout"]) && $_SESSION["basiclayout"] == 1 && $mobilepage != "invoice" && $action != "order") {
		return;
	}

	// Loop on each lines on invoice
	if (is_array($invoice->lines) && count($invoice->lines)) {
		print '<!-- invoice.php show lines of invoices -->'."\n";
		$tmplines = array_reverse($invoice->lines);
		$htmlsupplements = array();
		foreach ($tmplines as $line) {
			if ($line->fk_parent_line != false) {
				$htmlsupplements[$line->fk_parent_line] .= '<tr class="drag drop oddeven posinvoiceline';
				if ($line->special_code == "4") {
					$htmlsupplements[$line->fk_parent_line] .= ' order';
				}
				$htmlsupplements[$line->fk_parent_line] .= '" id="'.$line->id.'"';
				if ($line->special_code == "4") {
					$htmlsupplements[$line->fk_parent_line] .= ' title="'.dol_escape_htmltag($langs->trans("AlreadyPrinted")).'"';
				}
				$htmlsupplements[$line->fk_parent_line] .= '>';
				$htmlsupplements[$line->fk_parent_line] .= '<td class="left">';
				$htmlsupplements[$line->fk_parent_line] .= img_picto('', 'rightarrow');
				if ($line->product_label) {
					$htmlsupplements[$line->fk_parent_line] .= $line->product_label;
				}
				if ($line->product_label && $line->desc) {
					$htmlsupplements[$line->fk_parent_line] .= '<br>';
				}
				if ($line->product_label != $line->desc) {
					$firstline = dolGetFirstLineOfText($line->desc);
					if ($firstline != $line->desc) {
						$htmlsupplements[$line->fk_parent_line] .= $form->textwithpicto(dolGetFirstLineOfText($line->desc), $line->desc);
					} else {
						$htmlsupplements[$line->fk_parent_line] .= $line->desc;
					}
				}
				$htmlsupplements[$line->fk_parent_line] .= '</td>';

				// complete line by hook
				$parameters = array('line' => $line);
				$reshook = $hookmanager->executeHooks('completeTakePosInvoiceParentLine', $parameters, $invoice, $action);    // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0) {
					setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
				}
				$htmlsupplements[$line->fk_parent_line] .= $hookmanager->resPrint;

				if (empty($_SESSION["basiclayout"]) || $_SESSION["basiclayout"] != 1) {
					$htmlsupplements[$line->fk_parent_line] .= '<td class="right">'.vatrate(price2num($line->remise_percent), true).'</td>';
					$htmlsupplements[$line->fk_parent_line] .= '<td class="right">'.$line->qty.'</td>';
					$htmlsupplements[$line->fk_parent_line] .= '<td class="right">'.price($line->total_ttc).'</td>';
				}
				$htmlsupplements[$line->fk_parent_line] .= '</tr>'."\n";
				continue;
			}
			$htmlforlines = '';

			$htmlforlines .= '<tr class="drag drop oddeven posinvoiceline';
			if ($line->special_code == "4") {
				$htmlforlines .= ' order';
			}
			$htmlforlines .= '" id="'.$line->id.'"';
			if ($line->special_code == "4") {
				$htmlforlines .= ' title="'.dol_escape_htmltag($langs->trans("AlreadyPrinted")).'"';
			}
			$htmlforlines .= ' data-product-id="'.(int)$line->fk_product.'"';
			$htmlforlines .= ' data-qty="'.price2num($line->qty).'"';
			if (getDolGlobalInt('TAKEPOS_CHANGE_PRICE_HT') == 1) {
				$htmlforlines .= ' data-price="'.price2num($line->subprice, 6).'"';
			} else {
				$htmlforlines .= ' data-price="'.price2num($line->qty != 0 ? $line->total_ttc / $line->qty : $line->subprice, 6).'"';
			}
			$htmlforlines .= ' data-reduction="'.price2num($line->remise_percent).'"';
			$htmlforlines .= ' data-label="'.dol_escape_htmltag($line->product_label ? $line->product_label : ($line->product_ref ? $line->product_ref : dolGetFirstLineOfText($line->desc, 1))).'"';
			$htmlforlines .= '>';
			$htmlforlines .= '<td class="left">';
			if (!empty($_SESSION["basiclayout"]) && $_SESSION["basiclayout"] == 1) {
				$htmlforlines .= '<span class="phoneqty">'.$line->qty."</span> x ";
			}
			if (isset($line->product_type)) {
				if (empty($line->product_type)) {
					$htmlforlines .= img_object('', 'product').' ';
				} else {
					$htmlforlines .= img_object('', 'service').' ';
				}
			}
			$tooltiptext = '';
			if (!getDolGlobalString('TAKEPOS_SHOW_N_FIRST_LINES')) {
				if ($line->product_ref) {
					$tooltiptext .= '<b>'.$langs->trans("Ref").'</b> : '.$line->product_ref.'<br>';
					$tooltiptext .= '<b>'.$langs->trans("Label").'</b> : '.$line->product_label.'<br>';
					if (!empty($line->batch)) {
						$tooltiptext .= '<br><b>'.$langs->trans("LotSerial").'</b> : '.$line->batch.'<br>';
					}
					if (!empty($line->fk_warehouse)) {
						$tooltiptext .= '<b>'.$langs->trans("Warehouse").'</b> : '.$line->fk_warehouse.'<br>';
					}
					if ($line->product_label != $line->desc) {
						if ($line->desc) {
							$tooltiptext .= '<br>';
						}
						$tooltiptext .= $line->desc;
					}
				}
				if (getDolGlobalInt('TAKEPOS_SHOW_PRODUCT_REFERENCE') == 1) {
					$htmlforlines .= $form->textwithpicto($line->product_label ? '<b>' . $line->product_ref . '</b> - ' . $line->product_label : dolGetFirstLineOfText($line->desc, 1), $tooltiptext);
				} elseif (getDolGlobalInt('TAKEPOS_SHOW_PRODUCT_REFERENCE') == 2) {
					$htmlforlines .= $form->textwithpicto($line->product_ref ? '<b>'.$line->product_ref.'<b>' : dolGetFirstLineOfText($line->desc, 1), $tooltiptext);
				} else {
					$htmlforlines .= $form->textwithpicto($line->product_label ? $line->product_label : ($line->product_ref ? $line->product_ref : dolGetFirstLineOfText($line->desc, 1)), $tooltiptext);
				}
			} else {
				if ($line->product_ref) {
					$tooltiptext .= '<b>'.$langs->trans("Ref").'</b> : '.$line->product_ref.'<br>';
					$tooltiptext .= '<b>'.$langs->trans("Label").'</b> : '.$line->product_label.'<br>';
				}
				if (!empty($line->batch)) {
					$tooltiptext .= '<br><b>'.$langs->trans("LotSerial").'</b> : '.$line->batch.'<br>';
				}
				if (!empty($line->fk_warehouse)) {
					$tooltiptext .= '<b>'.$langs->trans("Warehouse").'</b> : '.$line->fk_warehouse.'<br>';
				}

				if ($line->product_label) {
					$htmlforlines .= $line->product_label;
				}
				if ($line->product_label != $line->desc) {
					if ($line->product_label && $line->desc) {
						$htmlforlines .= '<br>';
					}
					$firstline = dolGetFirstLineOfText($line->desc, getDolGlobalInt('TAKEPOS_SHOW_N_FIRST_LINES'));
					if ($firstline != $line->desc) {
						$htmlforlines .= $form->textwithpicto(dolGetFirstLineOfText($line->desc), $line->desc);
					} else {
						$htmlforlines .= $line->desc;
					}
				}
			}
			if (!empty($line->array_options['options_order_notes'])) {
				$htmlforlines .= "<br>(".$line->array_options['options_order_notes'].")";
			}
			if (!empty($_SESSION["basiclayout"]) && $_SESSION["basiclayout"] == 1) {
				$htmlforlines .= '</td><td class="right phonetable"><button type="button" onclick="SetQty(place, '.$line->rowid.', '.($line->qty - 1).');" class="publicphonebutton2 phonered">-</button>&nbsp;&nbsp;<button type="button" onclick="SetQty(place, '.$line->rowid.', '.($line->qty + 1).');" class="publicphonebutton2 phonegreen">+</button>';
			}
			if (empty($_SESSION["basiclayout"]) || $_SESSION["basiclayout"] != 1) {
				$moreinfo = '';
				$moreinfo .= $langs->transcountry("TotalHT", $mysoc->country_code).': '.price($line->total_ht);
				if ($line->vat_src_code) {
					$moreinfo .= '<br>'.$langs->trans("VATCode").': '.$line->vat_src_code;
				}
				$moreinfo .= '<br>'.$langs->transcountry("TotalVAT", $mysoc->country_code).': '.price($line->total_tva);
				$moreinfo .= '<br>'.$langs->transcountry("TotalLT1", $mysoc->country_code).': '.price($line->total_localtax1);
				$moreinfo .= '<br>'.$langs->transcountry("TotalLT2", $mysoc->country_code).': '.price($line->total_localtax2);
				$moreinfo .= '<hr>';
				$moreinfo .= $langs->transcountry("TotalTTC", $mysoc->country_code).': '.price($line->total_ttc);
				//$moreinfo .= $langs->trans("TotalHT").': '.$line->total_ht;
				if ($line->date_start || $line->date_end) {
					$htmlforlines .= '<br><div class="clearboth nowraponall">'.get_date_range($line->date_start, $line->date_end).'</div>';
				}
				$htmlforlines .= '</td>';

				// complete line by hook
				$parameters = array('line' => $line);
				$reshook = $hookmanager->executeHooks('completeTakePosInvoiceLine', $parameters, $invoice, $action);    // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0) {
					setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
				}
				$htmlforlines .= $hookmanager->resPrint;

				if (getDolGlobalInt("TAKEPOS_SHOW_SUBPRICE")) {
					$htmlforlines .= '<td class="right">'.price($line->subprice).'</td>';
				}
				$htmlforlines .= '<td class="right">'.vatrate(price2num($line->remise_percent), true).'</td>';
				$htmlforlines .= '<td class="right">';
				$htmlforlines .= $line->qty;
				if (isModEnabled('stock') && $user->hasRight('stock', 'mouvement', 'lire')) {
					$constantforkey = 'CASHDESK_ID_WAREHOUSE'.$_SESSION["takeposterminal"];
					if (getDolGlobalString($constantforkey) && $line->fk_product > 0 && !getDolGlobalString('TAKEPOS_HIDE_STOCK_ON_LINE')) {
						$productChildrenNb = 0;
						if (getDolGlobalInt('PRODUIT_SOUSPRODUITS')) {
							if (empty($line->product) || !($line->product->id > 0)) {
								$line->fetch_product();
							}
							if (!empty($line->product)) {
								$productChildrenNb = $line->product->hasFatherOrChild(1);
							}
						}
						if ($productChildrenNb == 0) {
							$sql = "SELECT e.rowid, e.ref, e.lieu, e.fk_parent, e.statut, ps.reel, ps.rowid as product_stock_id, p.pmp";
							$sql .= " FROM ".MAIN_DB_PREFIX."entrepot as e,";
							$sql .= " ".MAIN_DB_PREFIX."product_stock as ps";
							$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = ps.fk_product";
							$sql .= " WHERE ps.reel != 0";
							$sql .= " AND ps.fk_entrepot = ".((int) getDolGlobalString($constantforkey));
							$sql .= " AND e.entity IN (".getEntity('stock').")";
							$sql .= " AND ps.fk_product = ".((int) $line->fk_product);
							$resql = $db->query($sql);
							if ($resql) {
								$stock_real = 0;
								$obj = $db->fetch_object($resql);
								if ($obj) {
									$stock_real = price2num($obj->reel, 'MS');
								}
								$htmlforlines .= '&nbsp; ';
								$htmlforlines .= '<span class="opacitylow" title="'.$langs->trans("Stock").' '.price($stock_real, 1, '', 1, 0).'">';
								$htmlforlines .= '(';
								if ($line->qty && $line->qty > $stock_real) {
									$htmlforlines .= '<span style="color: var(--amountremaintopaycolor)">';
								}
								$htmlforlines .= img_picto('', 'stock', 'class="pictofixedwidth"').price($stock_real, 1, '', 1, 0);
								if ($line->qty && $line->qty > $stock_real) {
									$htmlforlines .= "</span>";
								}
								$htmlforlines .= ')';
								$htmlforlines .= '</span>';
							} else {
								dol_print_error($db);
							}
						}
					}
				}

				$htmlforlines .= '</td>';
				if (getDolGlobalInt('TAKEPOS_SHOW_HT')) {
					$htmlforlines .= '<td class="right classfortooltip" title="'.$moreinfo.'">';
					$htmlforlines .= price($line->total_ht, 1, '', 1, -1, -1, $conf->currency);
					if (isModEnabled('multicurrency') && !empty($_SESSION["takeposcustomercurrency"]) && $conf->currency != $_SESSION["takeposcustomercurrency"]) {
						//Only show customer currency if multicurrency module is enabled, if currency selected and if this currency selected is not the same as main currency
						include_once DOL_DOCUMENT_ROOT.'/multicurrency/class/multicurrency.class.php';
						$multicurrency = new MultiCurrency($db);
						$multicurrency->fetch(0, $_SESSION["takeposcustomercurrency"]);
						$htmlforlines .= '<br><span id="linecolht-span-total" style="font-size:0.9em; font-style:italic;">('.price($line->total_ht * $multicurrency->rate->rate).' '.$_SESSION["takeposcustomercurrency"].')</span>';
					}
					$htmlforlines .= '</td>';
				}
				$htmlforlines .= '<td class="right classfortooltip" title="'.$moreinfo.'">';
				$htmlforlines .= price($line->total_ttc, 1, '', 1, -1, -1, $conf->currency);
				if (isModEnabled('multicurrency') && !empty($_SESSION["takeposcustomercurrency"]) && $conf->currency != $_SESSION["takeposcustomercurrency"]) {
					//Only show customer currency if multicurrency module is enabled, if currency selected and if this currency selected is not the same as main currency
					include_once DOL_DOCUMENT_ROOT.'/multicurrency/class/multicurrency.class.php';
					$multicurrency = new MultiCurrency($db);
					$multicurrency->fetch(0, $_SESSION["takeposcustomercurrency"]);
					$htmlforlines .= '<br><span id="linecolht-span-total" style="font-size:0.9em; font-style:italic;">('.price($line->total_ttc * $multicurrency->rate->rate).' '.$_SESSION["takeposcustomercurrency"].')</span>';
				}
				$htmlforlines .= '</td>';
			}
			$htmlforlines .= '</tr>'."\n";
			$htmlforlines .= empty($htmlsupplements[$line->id]) ? '' : $htmlsupplements[$line->id];

			print $htmlforlines;
		}
	} else {
		print '<tr class="drag drop oddeven"><td class="left"><span class="opacitymedium">'.$langs->trans("Empty").'</span></td><td></td>';
		if (empty($_SESSION["basiclayout"]) || $_SESSION["basiclayout"] != 1) {
			print '<td></td><td></td>';
			if (getDolGlobalString('TAKEPOS_SHOW_HT')) {
				print '<td></td>';
			}
		}
		print '</tr>';
	}
} else {      // No invoice generated yet
	print '<tr class="drag drop oddeven"><td class="left"><span class="opacitymedium">'.$langs->trans("Empty").'</span></td><td></td>';
	if (empty($_SESSION["basiclayout"]) || $_SESSION["basiclayout"] != 1) {
		print '<td></td><td></td>';
		if (getDolGlobalString('TAKEPOS_SHOW_HT')) {
			print '<td></td>';
		}
	}
	print '</tr>';
}

if ($usediv) {
	print '</div>';
} else {
	print '</table>';
}

if ($action == "search") {
	print '<center>
	<input type="text" id="search" class="input-nobottom" name="search" onkeyup="Search2(\'\', null);" style="width: 80%; font-size: 150%;" placeholder="'.dol_escape_htmltag($langs->trans('Search')).'">
	</center>';
}

print '</div>';

// llxFooter
if ((getDolGlobalString('TAKEPOS_PHONE_BASIC_LAYOUT') == 1 && $conf->browser->layout == 'phone') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
	print '</body></html>';
}
