<?php
// tests/RollRegistryAndConsolidatedStockTest.php

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;
use App\Services\RollRegistryService;
use App\Controllers\PurchaseController;
use App\Controllers\PurchaseStockController;

echo "======================================================\n";
echo "INVENTO: Roll Color, Design & Qty Accumulation Test\n";
echo "======================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertCheck(string $title, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$title}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$title}" . ($detail ? " - {$detail}" : "") . "\n";
        $failCount++;
    }
}

// -----------------------------------------------------------
// TEST GROUP 1: Database Table & Master Registry
// -----------------------------------------------------------
echo "--- Group 1: Database Table & Master Registry ---\n";

$testConcernId = (int)(DB::fetchValue("SELECT id FROM concerns LIMIT 1") ?: 4);

RollRegistryService::initTable();

$tableExists = DB::fetchValue("SHOW TABLES LIKE 'fabric_roll_specs'");
assertCheck("Table 'fabric_roll_specs' exists in database", $tableExists === 'fabric_roll_specs');

// Sync existing rolls
RollRegistryService::syncSpecs($testConcernId);

$savedSpecs = RollRegistryService::getSavedSpecs($testConcernId);
assertCheck("Saved specs can be fetched for Concern {$testConcernId}", is_array($savedSpecs));

$savedColors = RollRegistryService::getSavedColors($testConcernId);
assertCheck("Saved colors can be fetched for future use", is_array($savedColors));

$savedDesigns = RollRegistryService::getSavedDesigns($testConcernId);
assertCheck("Saved designs can be fetched for future use", is_array($savedDesigns));

// -----------------------------------------------------------
// TEST GROUP 2: Duplicate Register Entry Removal & Qty Accumulation
// -----------------------------------------------------------
echo "\n--- Group 2: Duplicate Entry Removal & Qty Accumulation ---\n";

// Clear test specific records if any
DB::query("DELETE FROM purchase_bill_items WHERE item_code LIKE 'TEST-ROLL-%'");
DB::query("DELETE FROM fabric_roll_specs WHERE concern_id = ? AND color = 'BLACK' AND design_name = 'FLORAL 01'", [$testConcernId]);
$supplierId = (int)DB::fetchValue("SELECT id FROM suppliers WHERE concern_id = ? ORDER BY id LIMIT 1", [$testConcernId]);
if (!$supplierId) {
    $supplierId = (int)DB::fetchValue("SELECT id FROM suppliers ORDER BY id LIMIT 1");
}
if (!$supplierId) {
    DB::query("INSERT INTO suppliers (concern_id, name, company_name, phone, status, created_at) VALUES (?, 'Test Supplier', 'Test Company', '9876543210', 'active', NOW())", [$testConcernId]);
    $supplierId = (int)DB::lastInsertId();
}
$testBillNo = 'TEST-ROLL-STOCK-' . time();
DB::query(
    "INSERT INTO purchase_bills (concern_id, bill_no, supplier_id, bill_date, total_amount, tax_amount, net_amount, payment_status, inward_status)
     VALUES (?, ?, ?, CURDATE(), 0, 0, 0, 'unpaid', 'pending')",
    [$testConcernId, $testBillNo, $supplierId]
);
$testBillId = (int)DB::lastInsertId();

// 1. Insert First Bill Item: 10 Rolls of BLACK / FLORAL 01
DB::query(
    "INSERT INTO purchase_bill_items (concern_id, purchase_bill_id, section_type, item_name, item_code, color, pattern, qty, net_weight, unit, unit_price, total_price, inward_status)
     VALUES (?, ?, 'rolls', 'FLORAL 01 Fabric', 'TEST-ROLL-01', 'BLACK', 'FLORAL 01', 10.0, 20.0, 'Rolls', 500.0, 5000.0, 'pending')",
    [$testConcernId, $testBillId]
);

// 2. Insert Second Duplicate Bill Item (e.g. from same or another bill): 5 Rolls of BLACK / FLORAL 01
DB::query(
    "INSERT INTO purchase_bill_items (concern_id, purchase_bill_id, section_type, item_name, item_code, color, pattern, qty, net_weight, unit, unit_price, total_price, inward_status)
     VALUES (?, ?, 'rolls', 'FLORAL 01 Fabric', 'TEST-ROLL-02', 'BLACK', 'FLORAL 01', 5.0, 10.0, 'Rolls', 500.0, 2500.0, 'pending')",
    [$testConcernId, $testBillId]
);

// Pending purchase items must not count as stock until the inward action completes.
RollRegistryService::syncSpecs($testConcernId);

// Verify ONLY 1 row exists in fabric_roll_specs for BLACK + FLORAL 01
$count = (int)DB::fetchValue(
    "SELECT COUNT(*) FROM fabric_roll_specs WHERE concern_id = ? AND color = 'BLACK' AND design_name = 'FLORAL 01'",
    [$testConcernId]
);
assertCheck("Pending roll items are excluded from stock registry", $count === 0);

DB::query("UPDATE purchase_bill_items SET inward_status = 'completed' WHERE item_code LIKE 'TEST-ROLL-%'");
RollRegistryService::syncSpecs($testConcernId);
$count = (int)DB::fetchValue(
    "SELECT COUNT(*) FROM fabric_roll_specs WHERE concern_id = ? AND color = 'BLACK' AND design_name = 'FLORAL 01'",
    [$testConcernId]
);
assertCheck("Duplicate register entry removed: Exactly 1 row in fabric_roll_specs for BLACK / FLORAL 01", $count === 1);

$finalRow = DB::fetch(
    "SELECT * FROM fabric_roll_specs WHERE concern_id = ? AND color = 'BLACK' AND design_name = 'FLORAL 01'",
    [$testConcernId]
);
assertCheck("Consolidated quantity accumulated correctly (10 + 5 = 15 Rolls)", (float)$finalRow['qty'] === 15.0);
assertCheck("Consolidated weight accumulated correctly (20 + 10 = 30 kg)", (float)$finalRow['net_weight'] === 30.0);
assertCheck("Batch count is tracked accurately", (int)$finalRow['bills_count'] >= 1);

// -----------------------------------------------------------
// TEST GROUP 3: Purchase Billing Dynamic Suggestion & Notice
// -----------------------------------------------------------
echo "\n--- Group 3: Purchase Billing Dynamic Suggestion & Notice ---\n";

Auth::startSession();
$_SESSION['user'] = ['id' => 1, 'concern_id' => $testConcernId, 'role_name' => 'Admin', 'role_id' => 1];
$_SESSION['user_name'] = 'Admin User';
$_SESSION['concern_id'] = $testConcernId;
$_SESSION['enabled_modules'] = ['purchase_billing', 'purchase_stocks', 'suppliers'];
$_SESSION['user_permissions'] = [
    'purchase_billing' => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 1],
    'purchase_stocks'  => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 1],
    'suppliers'        => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 1]
];

$purchaseController = new PurchaseController();
ob_start();
$purchaseController->createBill();
$createHtml = ob_get_clean();

assertCheck("Quick-select dropdown for saved color & design present", strpos($createHtml, 'roll-quick-select') !== false);
assertCheck("Live roll stock notice element present", strpos($createHtml, 'rollStockNotice_') !== false);
assertCheck("JavaScript contains onRollColorOrPatternChange()", strpos($createHtml, 'onRollColorOrPatternChange') !== false);

// -----------------------------------------------------------
// TEST GROUP 4: Purchase Stocks Reflection & Report Export
// -----------------------------------------------------------
echo "\n--- Group 4: Purchase Stocks Reflection & Report Export ---\n";

$stockController = new PurchaseStockController();

// 1. Consolidated View
$_GET = ['tab' => 'rolls', 'concern_id' => (string)$testConcernId, 'view' => 'consolidated'];
ob_start();
$stockController->index();
$stockHtml = ob_get_clean();

assertCheck("Purchase Stocks renders consolidated table", strpos($stockHtml, 'viewRollConsolidated') !== false);
assertCheck("Consolidated table displays 'FLORAL 01'", strpos($stockHtml, 'FLORAL 01') !== false);
assertCheck("Consolidated table displays 'BLACK'", strpos($stockHtml, 'BLACK') !== false);
assertCheck("Consolidated table displays accumulated 15 Rolls", strpos($stockHtml, '15') !== false);
assertCheck("View mode switcher button group removed as requested", strpos($stockHtml, 'id="btnRollConsolidated"') === false);
assertCheck("Consolidated stock table uses compact row styling", strpos($stockHtml, 'table-sm align-middle') !== false);
assertCheck("Consolidated stock rows provide the exact fabric-label preview", strpos($stockHtml, 'id="rollQrModal"') !== false && strpos($stockHtml, 'onclick="showRollQr(this)"') !== false && strpos($stockHtml, 'id="rollQrFabric"') !== false && strpos($stockHtml, 'id="rollQrCodeText"') !== false);
assertCheck("Consolidated stock rows provide an edit-only action", strpos($stockHtml, 'onclick="editConsolidatedRoll(this)"') !== false && strpos($stockHtml, 'id="editConsolidatedRollModal"') !== false);
assertCheck("Edit action is prefilled with the row's current values", strpos($stockHtml, 'data-design="FLORAL 01"') !== false && strpos($stockHtml, 'data-qty="15.00"') !== false);

// 2. Export CSV Check
ob_start();
// Test export logic directly
RollRegistryService::syncSpecs($testConcernId);
$csvRows = RollRegistryService::getConsolidatedStock($testConcernId);
assertCheck("Consolidated stock records ready for export", count($csvRows) >= 1);

$foundBlackFloral = false;
foreach ($csvRows as $r) {
    if ($r['color'] === 'BLACK' && $r['design_name'] === 'FLORAL 01' && (float)$r['qty'] == 15.0) {
        $foundBlackFloral = true;
        break;
    }
}
assertCheck("Export dataset has merged entry for BLACK / FLORAL 01 with accumulated qty = 15.0", $foundBlackFloral);

// Edited consolidated values must survive registry rebuilds without changing purchase history.
$_SESSION['csrf_token'] = 'test-roll-stock-edit-token';
$_POST = [
    'csrf_token' => $_SESSION['csrf_token'],
    'concern_id' => $testConcernId,
    'source_color' => 'BLACK',
    'source_design_name' => 'FLORAL 01',
    'color' => 'BLACK',
    'design_name' => 'FLORAL 01 EDITED',
    'hsn_code' => '6543',
    'qty' => '17.00',
    'net_weight' => '32.00',
    'unit' => 'Rolls',
    'last_unit_price' => '510.00',
    'total_value' => '8670.00',
];
$editController = new class extends PurchaseStockController {
    public ?string $lastRedirect = null;
    public ?string $lastFlashType = null;

    public function redirect(string $url): void {
        $this->lastRedirect = $url;
    }

    public function flash(string $type, string $message): void {
        $this->lastFlashType = $type;
    }
};
$editController->updateConsolidatedRoll();
assertCheck("Consolidated edit endpoint accepts a valid CSRF-protected update", $editController->lastFlashType === 'success');
RollRegistryService::syncSpecs($testConcernId);
$editedRows = RollRegistryService::getConsolidatedStock($testConcernId);
$editedRow = null;
foreach ($editedRows as $r) {
    if ($r['source_color'] === 'BLACK' && $r['source_design_name'] === 'FLORAL 01') {
        $editedRow = $r;
        break;
    }
}
assertCheck("Edited consolidated values persist after stock synchronization", !empty($editedRow) && $editedRow['design_name'] === 'FLORAL 01 EDITED' && (float)$editedRow['qty'] === 17.0 && (float)$editedRow['net_weight'] === 32.0 && (float)$editedRow['total_value'] === 8670.0);
assertCheck("Edited consolidated values remain searchable", count(RollRegistryService::getConsolidatedStock($testConcernId, 'EDITED')) === 1);
$sourceQty = (float)DB::fetchValue("SELECT SUM(qty) FROM purchase_bill_items WHERE item_code LIKE 'TEST-ROLL-%'");
assertCheck("Editing consolidated values preserves source purchase quantities", $sourceQty === 15.0);

// Clean up test entry
DB::query("DELETE FROM fabric_roll_spec_overrides WHERE concern_id = ? AND source_color = 'BLACK' AND source_design_name = 'FLORAL 01'", [$testConcernId]);
DB::query("DELETE FROM purchase_bill_items WHERE item_code LIKE 'TEST-ROLL-%'");
DB::query("DELETE FROM purchase_bills WHERE id = ?", [$testBillId]);
DB::query("DELETE FROM fabric_roll_specs WHERE concern_id = ? AND color = 'BLACK' AND design_name = 'FLORAL 01'", [$testConcernId]);
RollRegistryService::syncSpecs($testConcernId);

// -----------------------------------------------------------
// SUMMARY
// -----------------------------------------------------------
echo "\n======================================================\n";
echo "SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
echo "======================================================\n";

if ($failCount > 0) {
    exit(1);
}
