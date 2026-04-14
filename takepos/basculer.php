<?php
/* Copyright (C) 2025  Project customization
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       htdocs/takepos/basculer.php
 * \ingroup    takepos
 * \brief      Popup to switch from Achat mode to Vente mode (Basculer)
 *             Validates the supplier invoice (no stock), adds stock IN, creates sale invoice.
 */

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
require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("bills", "cashdesk"));

$place     = (GETPOST('place', 'aZ09') ? GETPOST('place', 'aZ09') : '0');
$invoiceid = GETPOSTINT('invoiceid');

if (!$user->hasRight('takepos', 'run')) {
	accessforbidden();
}

// Only available in achat mode
$takeposmode = (!empty($_SESSION['takeposmode'])) ? $_SESSION['takeposmode'] : 'vente';
if ($takeposmode !== 'achat') {
	accessforbidden('Only available in achat mode');
}

/*
 * View
 */
$arrayofcss = array('/takepos/css/pos.css.php');
top_htmlhead('', '', 0, 0, array(), $arrayofcss);
?>
<body>
<script>
/**
 * Trigger basculer with a payment method, then reload the parent page in vente mode.
 * @param {string} pay  'LIQ' for cash, 'delayed' for deferred payment
 */
function doBascule(pay) {
	parent.$("#poslines").load(
		"<?php echo DOL_URL_ROOT; ?>/takepos/invoice.php?action=basculer&token=<?php echo newToken(); ?>&invoiceid=<?php echo (int) $invoiceid; ?>&place=<?php echo dol_escape_js($place); ?>&pay=" + pay,
		function(response, status) {
			parent.$.colorbox.close();
			// Full page reload so the button panel switches from achat → vente
			parent.window.location.href = "<?php echo DOL_URL_ROOT; ?>/takepos/index.php?place=<?php echo dol_escape_js($place); ?>";
		}
	);
}
</script>

<br>
<center>
	<h3 style="margin-bottom:20px;">Basculer en mode Vente</h3>
	<p style="margin-bottom:24px;">
		La facture fournisseur sera validée, le stock sera approvisionné,<br>
		et une facture de vente sera créée avec les mêmes produits.
	</p>
	<button type="button" class="button takepospay" style="font-size:1.1em; padding:12px 28px;" onclick="doBascule('LIQ');">
		<span class="fas fa-coins paddingrightonly"></span>Espèce
	</button>
	&nbsp;&nbsp;&nbsp;
	<button type="button" class="button takepospay" style="font-size:1.1em; padding:12px 28px;" onclick="doBascule('delayed');">
		<span class="far fa-clock paddingrightonly"></span>Différer
	</button>
</center>

</body>
</html>
