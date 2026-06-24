<?php
/**
 * Accounting Tools for Chatbot
 * Functions to retrieve accounting data: balance sheets, account balances, transactions
 */

// Get balance sheet (Bilan)
function get_balance_sheet($db, $start_date = null, $end_date = null) {
    require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
    require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';

    if (empty($start_date) || empty($end_date)) {
        // Use current fiscal year
        $sql = "SELECT date_start, date_end FROM ".MAIN_DB_PREFIX."accounting_fiscalyear ";
        $sql .= " WHERE date_start < '".date('Y-m-d')."' AND date_end > '".date('Y-m-d')."'";
        $sql .= " ORDER BY date_start DESC LIMIT 1";
        $res = $db->query($sql);
        if ($db->num_rows($res) > 0) {
            $row = $db->fetch_object($res);
            $start_date = strtotime($row->date_start);
            $end_date = strtotime($row->date_end);
        }
    }

    $balance_data = [
        'assets' => [],
        'liabilities' => [],
        'equity' => [],
        'total_assets' => 0,
        'total_liabilities' => 0,
        'total_equity' => 0,
        'period' => [
            'start' => date('Y-m-d', $start_date),
            'end' => date('Y-m-d', $end_date)
        ]
    ];

    // Get accounts with balances
    $sql = "SELECT DISTINCT(t.numero_compte) as account_number, ac.label as account_label ";
    $sql .= "FROM ".MAIN_DB_PREFIX."bookkeeping as t ";
    $sql .= "LEFT JOIN ".MAIN_DB_PREFIX."accounting_account as ac ON ac.account_number = t.numero_compte ";
    $sql .= "WHERE t.doc_date >= '".date('Y-m-d', $start_date)."' ";
    $sql .= "AND t.doc_date <= '".date('Y-m-d', $end_date)."' ";
    $sql .= "ORDER BY t.numero_compte ASC";

    $res = $db->query($sql);
    $accounts = [];
    while ($row = $db->fetch_object($res)) {
        $accounts[] = $row;
    }

    // Calculate balance for each account
    foreach ($accounts as $account) {
        $sql = "SELECT SUM(IF(sens='D', debit, -debit)) as balance ";
        $sql .= "FROM ".MAIN_DB_PREFIX."bookkeeping ";
        $sql .= "WHERE numero_compte = '".$db->escape($account->account_number)."' ";
        $sql .= "AND doc_date >= '".date('Y-m-d', $start_date)."' ";
        $sql .= "AND doc_date <= '".date('Y-m-d', $end_date)."'";

        $res = $db->query($sql);
        $balance_row = $db->fetch_object($res);
        $balance = floatval($balance_row->balance ?? 0);

        $account_data = [
            'account_number' => $account->account_number,
            'account_label' => $account->account_label ?? 'Unknown',
            'balance' => $balance,
            'balance_formatted' => number_format($balance, 2, ',', ' ')
        ];

        // Classify by account type (1=Assets, 2=Liabilities, 3=Equity)
        $first_digit = (int)substr($account->account_number, 0, 1);
        if ($first_digit == 1) {
            $balance_data['assets'][] = $account_data;
            $balance_data['total_assets'] += $balance;
        } elseif ($first_digit == 2) {
            $balance_data['liabilities'][] = $account_data;
            $balance_data['total_liabilities'] += $balance;
        } elseif ($first_digit == 3) {
            $balance_data['equity'][] = $account_data;
            $balance_data['total_equity'] += $balance;
        }
    }

    return $balance_data;
}

// Get account balance (Solde)
function get_account_balance($db, $account_number, $start_date = null, $end_date = null) {
    if (empty($start_date) || empty($end_date)) {
        $sql = "SELECT date_start, date_end FROM ".MAIN_DB_PREFIX."accounting_fiscalyear ";
        $sql .= " WHERE date_start < '".date('Y-m-d')."' AND date_end > '".date('Y-m-d')."'";
        $res = $db->query($sql);
        if ($db->num_rows($res) > 0) {
            $row = $db->fetch_object($res);
            $start_date = strtotime($row->date_start);
            $end_date = strtotime($row->date_end);
        }
    }

    $sql = "SELECT SUM(IF(sens='D', debit, -debit)) as balance, ";
    $sql .= "SUM(IF(sens='D', debit, 0)) as total_debit, ";
    $sql .= "SUM(IF(sens='C', credit, 0)) as total_credit ";
    $sql .= "FROM ".MAIN_DB_PREFIX."bookkeeping ";
    $sql .= "WHERE numero_compte = '".$db->escape($account_number)."' ";
    $sql .= "AND doc_date >= '".date('Y-m-d', $start_date)."' ";
    $sql .= "AND doc_date <= '".date('Y-m-d', $end_date)."'";

    $res = $db->query($sql);
    $row = $db->fetch_object($res);

    return [
        'account_number' => $account_number,
        'balance' => floatval($row->balance ?? 0),
        'total_debit' => floatval($row->total_debit ?? 0),
        'total_credit' => floatval($row->total_credit ?? 0),
        'period' => [
            'start' => date('Y-m-d', $start_date),
            'end' => date('Y-m-d', $end_date)
        ]
    ];
}

// Get bank transactions
function get_bank_transactions($db, $account_id = null, $limit = 20, $period = 'month') {
    require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

    // Calculate date range based on period
    $end_date = time();
    switch ($period) {
        case 'week':
            $start_date = strtotime('-1 week');
            break;
        case 'month':
            $start_date = strtotime('-1 month');
            break;
        case 'quarter':
            $start_date = strtotime('-3 months');
            break;
        case 'year':
            $start_date = strtotime('-1 year');
            break;
        default:
            $start_date = strtotime('-1 month');
    }

    $sql = "SELECT t.rowid, t.dateoperation, t.label, t.debit, t.credit, t.fk_account ";
    $sql .= "FROM ".MAIN_DB_PREFIX."bank as t ";

    if (!empty($account_id)) {
        $sql .= "WHERE t.fk_account = ".intval($account_id)." ";
    } else {
        $sql .= "WHERE 1 = 1 ";
    }

    $sql .= "AND t.dateoperation >= '".date('Y-m-d', $start_date)."' ";
    $sql .= "AND t.dateoperation <= '".date('Y-m-d', $end_date)."' ";
    $sql .= "ORDER BY t.dateoperation DESC ";
    $sql .= "LIMIT ".intval($limit);

    $res = $db->query($sql);
    $transactions = [];
    $total_in = 0;
    $total_out = 0;

    while ($row = $db->fetch_object($res)) {
        $amount = floatval($row->debit ?? 0) ?: floatval($row->credit ?? 0);
        $direction = !empty($row->debit) ? 'IN' : 'OUT';

        if ($direction === 'IN') {
            $total_in += $amount;
        } else {
            $total_out += $amount;
        }

        $transactions[] = [
            'date' => $row->dateoperation,
            'label' => $row->label,
            'amount' => $amount,
            'direction' => $direction,
            'account_id' => $row->fk_account
        ];
    }

    return [
        'transactions' => $transactions,
        'summary' => [
            'period' => $period,
            'total_in' => $total_in,
            'total_out' => $total_out,
            'net_balance' => $total_in - $total_out,
            'count' => count($transactions)
        ],
        'dates' => [
            'start' => date('Y-m-d', $start_date),
            'end' => date('Y-m-d', $end_date)
        ]
    ];
}

// Get detailed transactions for a date range
function get_accounting_summary($db, $start_date = null, $end_date = null) {
    if (empty($start_date) || empty($end_date)) {
        $sql = "SELECT date_start, date_end FROM ".MAIN_DB_PREFIX."accounting_fiscalyear ";
        $sql .= " WHERE date_start < '".date('Y-m-d')."' AND date_end > '".date('Y-m-d')."'";
        $res = $db->query($sql);
        if ($db->num_rows($res) > 0) {
            $row = $db->fetch_object($res);
            $start_date = strtotime($row->date_start);
            $end_date = strtotime($row->date_end);
        }
    }

    $summary = [
        'period' => [
            'start' => date('Y-m-d', $start_date),
            'end' => date('Y-m-d', $end_date)
        ],
        'total_transactions' => 0,
        'total_debits' => 0,
        'total_credits' => 0,
        'by_journal' => []
    ];

    $sql = "SELECT journal_code, COUNT(*) as count, SUM(debit) as total_debit, SUM(credit) as total_credit ";
    $sql .= "FROM ".MAIN_DB_PREFIX."bookkeeping ";
    $sql .= "WHERE doc_date >= '".date('Y-m-d', $start_date)."' ";
    $sql .= "AND doc_date <= '".date('Y-m-d', $end_date)."' ";
    $sql .= "GROUP BY journal_code ";
    $sql .= "ORDER BY journal_code ASC";

    $res = $db->query($sql);
    while ($row = $db->fetch_object($res)) {
        $summary['by_journal'][] = [
            'journal' => $row->journal_code,
            'count' => intval($row->count ?? 0),
            'total_debit' => floatval($row->total_debit ?? 0),
            'total_credit' => floatval($row->total_credit ?? 0)
        ];
        $summary['total_transactions'] += intval($row->count ?? 0);
        $summary['total_debits'] += floatval($row->total_debit ?? 0);
        $summary['total_credits'] += floatval($row->total_credit ?? 0);
    }

    return $summary;
}
