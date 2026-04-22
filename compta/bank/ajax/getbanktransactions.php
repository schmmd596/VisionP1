<?php
/**
 * AJAX endpoint - returns bank transactions for a given account as JSON
 * Used by compta/bank/transfer.php in "Transaction" mode
 * Returns only credit transactions (amount > 0), optionally filtered by date range.
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

// Optional filters
$date_from_str = GETPOST('date_from', 'alpha');
$date_to_str   = GETPOST('date_to',   'alpha');
$reference     = GETPOST('reference', 'alpha');

$date_from_ts = null;
$date_to_ts   = null;

if (!empty($date_from_str)) {
	$date_from_ts = strtotime($date_from_str . ' 00:00:00');
}
if (!empty($date_to_str)) {
	$date_to_ts = strtotime($date_to_str . ' 23:59:59');
}

$sql  = "SELECT b.rowid, b.label, b.amount, b.dateo";
$sql .= " FROM ".MAIN_DB_PREFIX."bank b";
$sql .= " WHERE b.fk_account = ".((int) $account_id);
$sql .= " AND b.amount > 0"; // credits only
if ($date_from_ts !== null) {
	$sql .= " AND b.dateo >= '".$db->idate($date_from_ts)."'";
}
if ($date_to_ts !== null) {
	$sql .= " AND b.dateo <= '".$db->idate($date_to_ts)."'";
}
if (!empty($reference)) {
	$sql .= " AND b.label LIKE '%".$db->escape($reference)."%'";
}
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
