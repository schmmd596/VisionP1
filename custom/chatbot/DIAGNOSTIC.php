<?php
/**
 * Diagnostic script for Tafkir IA Chatbot
 * Run this directly via browser: http://localhost/projectVp/custom/chatbot/DIAGNOSTIC.php
 */

require_once 'master.inc.php';

echo "<h1>🔍 Diagnostic Chatbot Tafkir IA</h1>";
echo "<hr>";

// 1. Check database connection
echo "<h2>1. Database Connection</h2>";
try {
    $sql = "SELECT 1 as test";
    $res = $db->query($sql);
    echo "✅ Database connected<br>";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
    exit;
}

// 2. Check accounting_bookkeeping table
echo "<h2>2. Accounting Bookkeeping Table</h2>";
$sql = "SELECT COUNT(*) as cnt FROM " . MAIN_DB_PREFIX . "accounting_bookkeeping";
$res = $db->query($sql);
$row = $db->fetch_object($res);
$count = (int)($row->cnt ?? 0);
echo "Total entries: <strong>" . $count . "</strong><br>";

if ($count > 0) {
    echo "<h3>Recent entries:</h3>";
    $sql = "SELECT numero_compte, label_compte, label_operation, debit, credit
            FROM " . MAIN_DB_PREFIX . "accounting_bookkeeping
            ORDER BY rowid DESC LIMIT 10";
    $res = $db->query($sql);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Account</th><th>Label</th><th>Operation</th><th>Debit</th><th>Credit</th></tr>";
    while ($row = $db->fetch_object($res)) {
        echo "<tr>";
        echo "<td>" . $row->numero_compte . "</td>";
        echo "<td>" . $row->label_compte . "</td>";
        echo "<td>" . $row->label_operation . "</td>";
        echo "<td>" . $row->debit . "</td>";
        echo "<td>" . $row->credit . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "⚠️ No accounting entries found. Invoices may not be creating entries.<br>";
}

// 3. Check invoices
echo "<h2>3. Invoices Created</h2>";
$sql = "SELECT ref, status, total_ttc FROM " . MAIN_DB_PREFIX . "facture ORDER BY rowid DESC LIMIT 5";
$res = $db->query($sql);
$invoice_count = 0;
echo "<h3>Recent Customer Invoices:</h3>";
if ($db->num_rows($res) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Ref</th><th>Status</th><th>Total</th></tr>";
    while ($row = $db->fetch_object($res)) {
        $invoice_count++;
        echo "<tr>";
        echo "<td>" . $row->ref . "</td>";
        echo "<td>" . ($row->status == 1 ? '✅ VALIDATED' : '❌ DRAFT') . "</td>";
        echo "<td>" . number_format($row->total_ttc, 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No customer invoices found<br>";
}

$sql = "SELECT ref, status, total_ttc FROM " . MAIN_DB_PREFIX . "facture_fourn ORDER BY rowid DESC LIMIT 5";
$res = $db->query($sql);
echo "<h3>Recent Supplier Invoices:</h3>";
if ($db->num_rows($res) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Ref</th><th>Status</th><th>Total</th></tr>";
    while ($row = $db->fetch_object($res)) {
        echo "<tr>";
        echo "<td>" . $row->ref . "</td>";
        echo "<td>" . ($row->status == 1 ? '✅ VALIDATED' : '❌ DRAFT') . "</td>";
        echo "<td>" . number_format($row->total_ttc, 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No supplier invoices found<br>";
}

// 4. Check PHP error log
echo "<h2>4. Recent PHP Error Log</h2>";
$log_paths = [
    'C:/xampp/apache/logs/error.log',
    '/var/log/apache2/error.log',
    '/var/log/php-fpm.log',
    ini_get('error_log')
];

$log_found = false;
foreach ($log_paths as $path) {
    if (!empty($path) && file_exists($path)) {
        echo "Log file: <strong>" . $path . "</strong><br>";
        $lines = file_get_contents($path, false, null, max(0, filesize($path) - 2000));
        echo "<pre style='background: #f0f0f0; padding: 10px; max-height: 300px; overflow: auto;'>";
        // Filter for recent Accounting/Tafkir errors
        $all_lines = explode("\n", $lines);
        $recent = [];
        foreach (array_reverse($all_lines) as $line) {
            if (strpos($line, 'Accounting') !== false ||
                strpos($line, 'BookKeeping') !== false ||
                strpos($line, 'Tafkir') !== false ||
                strpos($line, 'createFromValues') !== false) {
                $recent[] = $line;
                if (count($recent) >= 10) break;
            }
        }
        if (empty($recent)) {
            echo "(No recent Accounting/Tafkir errors found - showing last 5 lines)";
            $recent = array_slice(array_reverse($all_lines), 0, 5);
        }
        echo htmlspecialchars(implode("\n", array_reverse($recent)));
        echo "</pre>";
        $log_found = true;
        break;
    }
}

if (!$log_found) {
    echo "⚠️ Could not find PHP error log. Check Apache configuration.<br>";
}

// 5. Summary
echo "<h2>5. Summary & Next Steps</h2>";
if ($count === 0 && $invoice_count > 0) {
    echo "<strong>🔴 PROBLEM FOUND:</strong> Invoices exist but no accounting entries created!<br>";
    echo "Likely causes:<br>";
    echo "1. auto_create_accounting_entries_for_invoice() not being called<br>";
    echo "2. BookKeeping::createFromValues() is failing silently<br>";
    echo "3. Check PHP error log for 'Accounting entry' messages<br>";
    echo "<br>";
    echo "<strong>Action:</strong> Create a test invoice and watch the error log for 'Accounting entry' messages<br>";
} else if ($count > 0) {
    echo "<strong>✅ Good:</strong> Accounting entries are being created<br>";
    echo "Balance sheet (bilan) should work now<br>";
} else {
    echo "<strong>⚠️ Create some invoices first</strong><br>";
}

echo "<hr>";
echo "Diagnostic completed at: " . date('Y-m-d H:i:s') . "<br>";
?>
