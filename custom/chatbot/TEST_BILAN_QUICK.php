<?php
/**
 * Quick test for bilan function
 */
require_once 'master.inc.php';

echo "Testing bilan function...\n";
echo "========================\n\n";

$entity = (int)$GLOBALS['conf']->entity;
echo "Entity: $entity\n";

// Test 1: Check if table exists and has data
echo "\n1. Checking accounting_bookkeeping table:\n";
$count_sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."accounting_bookkeeping";
$res = $db->query($count_sql);
$row = $db->fetch_object($res);
$total = (int)($row->cnt ?? 0);
echo "   Total entries: $total\n";

if ($total === 0) {
    echo "   ⚠️ No accounting entries yet\n";
} else {
    echo "   ✅ Found $total accounting entries\n";
}

// Test 2: Test the optimized query
echo "\n2. Testing optimized bilan query (no GROUP BY):\n";
$start = microtime(true);

$sql = "SELECT SUM(COALESCE(debit, 0)) as deb, SUM(COALESCE(credit, 0)) as cred FROM ".MAIN_DB_PREFIX."accounting_bookkeeping WHERE entity = {$entity} LIMIT 1";
$res = $db->query($sql);
$row = $db->fetch_object($res);

$time = (microtime(true) - $start) * 1000;
echo "   Query time: " . number_format($time, 2) . " ms\n";

if ($row) {
    $deb = (float)($row->deb ?? 0);
    $cred = (float)($row->cred ?? 0);
    echo "   Debit total: ".number_format($deb, 0)." MRU\n";
    echo "   Credit total: ".number_format($cred, 0)." MRU\n";
    echo "   Balanced: " . (abs($deb - $cred) < 1 ? '✓ YES' : '❌ NO') . "\n";
} else {
    echo "   ❌ Query returned no result\n";
}

// Test 3: Test invoice count
echo "\n3. Checking invoices:\n";
$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."facture WHERE entity = {$entity}";
$res = $db->query($sql);
$row = $db->fetch_object($res);
echo "   Customer invoices: " . ($row->cnt ?? 0) . "\n";

$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."facture_fourn WHERE entity = {$entity}";
$res = $db->query($sql);
$row = $db->fetch_object($res);
echo "   Supplier invoices: " . ($row->cnt ?? 0) . "\n";

echo "\n✅ Test completed\n";
echo "If query time < 100ms and entries are created, bilan should work!\n";
?>
