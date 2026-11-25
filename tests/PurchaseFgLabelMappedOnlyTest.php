<?php
// tests/PurchaseFgLabelMappedOnlyTest.php - Verify Purchase Finished Goods Label Dropdown (Mapped Only) & Empty Copies Field

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;
use App\Controllers\LabelController;

echo "======================================================\n";
echo "INVENTO: Purchase FG Label (Mapped Only & Empty Copies)\n";
echo "======================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $title, bool $condition) {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$title}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$title}\n";
        $failCount++;
    }
}

// ----------------------------------------------------
// Setup Tenant & Auth
// ----------------------------------------------------
echo "--- Group 1: Setup Mapped and Unmapped Finished Goods ---\n";
$user = DB::fetch("SELECT * FROM users WHERE email = 'grm.admin@invento.com'");
if (!$user) {
    $user = DB::fetch("SELECT * FROM users WHERE role = 'concern_admin' LIMIT 1");
}
assertTest("Found valid concern user", !empty($user));
$_SESSION['user_id'] = $user['id'];
$_SESSION['concern_id'] = $user['concern_id'] ?: 1;
$_SESSION['user_role'] = $user['role'] ?? ($user['role_name'] ?? 'concern_admin');
$cid = (int)$_SESSION['concern_id'];

// Create or fetch Purchase Bill
$supplier = DB::fetch("SELECT id FROM suppliers WHERE concern_id = ? LIMIT 1", [$cid]);
$supplierId = (int)($supplier['id'] ?? 1);

$billNo = 'GRM-PO-FG-' . rand(1000, 9999);
DB::query(
    "INSERT INTO purchase_bills (concern_id, bill_no, supplier_id, bill_date, total_amount, payment_status, created_at)
     VALUES (?, ?, ?, CURDATE(), 5000.00, 'paid', NOW())",
    [$cid, $billNo, $supplierId]
);
$billId = (int)DB::lastInsertId();

// 1. Insert an UNMAPPED finished good item (variant_id is NULL)
$unmappedName = 'UNMAPPED TEST GARMENT ' . rand(1000, 9999);
$unmappedCode = 'FG-UNMAP-' . rand(1000, 9999);
DB::query(
    "INSERT INTO purchase_bill_items (
        concern_id, purchase_bill_id, section_type, item_name, item_code,
        qty, unit, unit_price, mrp, total_price, variant_id
    ) VALUES (?, ?, 'finished_goods', ?, ?, 50.00, 'Pieces', 100.00, 200.00, 5000.00, NULL)",
    [$cid, $billId, $unmappedName, $unmappedCode]
);
$unmappedId = (int)DB::lastInsertId();
assertTest("Created UNMAPPED finished good item (ID: {$unmappedId}, Code: {$unmappedCode})", $unmappedId > 0);

// 2. Fetch or create a variant for MAPPED finished good item
$variant = DB::fetch("SELECT * FROM variants WHERE concern_id = ? AND status = 'active' LIMIT 1", [$cid]);
if (!$variant) {
    $variant = DB::fetch("SELECT * FROM variants LIMIT 1");
}
assertTest("Found variant for mapping", !empty($variant));
$varId = (int)$variant['id'];

// Insert a MAPPED finished good item (variant_id is set)
$mappedName = 'MAPPED TEST GARMENT ' . rand(1000, 9999);
$mappedCode = 'FG-MAP-' . rand(1000, 9999);
DB::query(
    "INSERT INTO purchase_bill_items (
        concern_id, purchase_bill_id, section_type, item_name, item_code,
        qty, unit, unit_price, mrp, total_price, variant_id
    ) VALUES (?, ?, 'finished_goods', ?, ?, 30.00, 'Pieces', 150.00, 350.00, 4500.00, ?)",
    [$cid, $billId, $mappedName, $mappedCode, $varId]
);
$mappedId = (int)DB::lastInsertId();
assertTest("Created MAPPED finished good item (ID: {$mappedId}, Code: {$mappedCode}, Variant: {$varId})", $mappedId > 0);

// ----------------------------------------------------
// Group 2: View Rendering Verification
// ----------------------------------------------------
echo "\n--- Group 2: Dropdown List Filtering (Mapped Only) ---\n";
$_GET['tab'] = 'purchase_fg';

$ctrl = new LabelController();
ob_start();
try {
    $ctrl->index();
    $html = ob_get_clean();
} catch (\Throwable $e) {
    ob_end_clean();
    $html = '';
    echo "Controller error: " . $e->getMessage() . "\n";
}

assertTest("Rendered label generation HTML successfully", !empty($html));

// Extract fg_item_select block
preg_match('/<select[^>]*id="fg_item_select"[^>]*>(.*?)<\/select>/s', $html, $matches);
$selectHtml = $matches[1] ?? '';
assertTest("Found #fg_item_select in HTML", !empty($selectHtml));

// Verification: Mapped item MUST be present inside the dropdown
assertTest("Mapped item option is PRESENT in #fg_item_select", str_contains($selectHtml, "value=\"{$mappedId}\""));
assertTest("Mapped item SKU or Name is PRESENT in #fg_item_select", str_contains($selectHtml, $variant['product_code']) || str_contains($selectHtml, $variant['name']) || str_contains($selectHtml, $mappedCode));

// Verification: Unmapped item MUST NOT be present inside the dropdown
assertTest("Unmapped item option is STRICTLY ABSENT from #fg_item_select", !str_contains($selectHtml, "value=\"{$unmappedId}\""));
assertTest("Unmapped item code is STRICTLY ABSENT from #fg_item_select", !str_contains($selectHtml, $unmappedCode));

// ----------------------------------------------------
// Group 3: Number of Copies Field Verification (Empty on Load)
// ----------------------------------------------------
echo "\n--- Group 3: Copies Print Field is Empty on Load ---\n";

// Check #fg_qty input attributes
preg_match('/<input[^>]*id="fg_qty"[^>]*>/', $html, $inputMatches);
$inputHtml = $inputMatches[0] ?? '';
assertTest("Found #fg_qty input element", !empty($inputHtml));

// Must have value="" and NOT value="24"
assertTest("#fg_qty has value=\"\"", str_contains($inputHtml, 'value=""'));
assertTest("#fg_qty does NOT have value=\"24\"", !str_contains($inputHtml, 'value="24"'));
assertTest("#fg_qty has placeholder for copies count", str_contains($inputHtml, 'placeholder="Enter copies count"'));

// ----------------------------------------------------
// Group 4: Directory Table Action Links
// ----------------------------------------------------
echo "\n--- Group 4: Directory Table Action Buttons ---\n";

// Table should show "Map to Catalog" button for unmapped items
assertTest("Table has 'Map to Catalog' link for unmapped items", str_contains($html, "catalog-mapping/map?id={$unmappedId}"));

// Table should have "Print" button for mapped items
assertTest("Table has 'selectFgItem' print handler for mapped item", str_contains($html, "selectFgItem({$mappedId})"));

// ----------------------------------------------------
// Cleanup Test Data (Keep Live Database Clean)
// ----------------------------------------------------
if (!empty($billId)) {
    DB::query("DELETE FROM purchase_bill_items WHERE purchase_bill_id = ?", [$billId]);
    DB::query("DELETE FROM purchase_bills WHERE id = ?", [$billId]);
}

// ----------------------------------------------------
// SUMMARY
// ----------------------------------------------------
echo "\n======================================================\n";
echo "TEST RESULTS: Total = " . ($passCount + $failCount) . " | Passed = {$passCount} | Failed = {$failCount}\n";
echo "======================================================\n";

if ($failCount === 0) {
    echo ">>> PURCHASE FG LABEL MAPPED ONLY & EMPTY COPIES FULLY VERIFIED! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
