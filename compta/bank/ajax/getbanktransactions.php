<?php
/**
 * AJAX endpoint - returns bank transactions for a given account as JSON
 * Used by compta/bank/transfer.php in "Transaction" mode
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
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}

// Load Dolibarr environment
require '../../../main.inc.php';

header('Content-Type: application/json; charset=utf-8');

// Security check — same right required as the transfer page
if (!$user->hasRight('banque', 'transfer')) {
	http_response_code(403);
	echo json_encode(array('error' => 'Access denied'));
	exit;
}

$account_id = GETPOSTINT('account_id');
if (!$account_id) {
	echo json_encode(array());
	exit;
}

$sql  = "SELECT b.rowid, b.label, b.amount, b.dateo";
$sql .= " FROM ".MAIN_DB_PREFIX."bank b";
$sql .= " WHERE b.fk_account = ".((int) $account_id);
$sql .= " ORDER BY b.dateo DESC, b.rowid DESC";
$sql .= " LIMIT 300";

$resql = $db->query($sql);
$transactions = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$transactions[] = array(
			'id'       => (int) $obj->rowid,
			'label'    => $obj->label,
			'amount'   => (float) $obj->amount,
			'date'     => dol_print_date($db->jdate($obj->dateo), 'day'),
			'date_raw' => (int) $db->jdate($obj->dateo),
		);
	}
	$db->free($resql);
}

echo json_encode($transactions);
$db->close();
exit;
