<?php
// tests/PurchaseFlowToStockTest.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;
use App\Services\RollRegistryService;
use App\Controllers\PurchaseController;
use App\Controllers\LabelController;
use App\Controllers\PurchaseStockController;

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['user'] = [
    'id'         => 1,
    'username'   => 'admin',
    'role_id'    => 1,
    'concern_id' => 1
];
$_SESSION['csrf_token'] = 'test_token_12345';
$_SESSION['enabled_modules'] = ['purchase_billing', 'label_generation', 'purchase_stocks', 'stocks'];

echo "======================================================\n";
echo "INVENTO: Purchase Flow -> Label Generation -> Purchase Stock Test\n";
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
// TEST GROUP 1: Inward Status Column & Schema Integrity
// -----------------------------------------------------------
echo "--- Group 1: Schema & Inward Status Tracking ---\n";

$pbCol = DB::fetchAll("SHOW COLUMNS FROM purchase_bills LIKE 'inward_status'");
assertCheck("purchase_bills has 'inward_status' column", !empty($pbCol));

$pbiCol = DB::fetchAll("SHOW COLUMNS FROM purchase_bill_items LIKE 'inward_status'");
assertCheck("purchase_bill_items has 'inward_status' column", !empty($pbiCol));

// -----------------------------------------------------------
// TEST GROUP 2: Purchase Bill Creation -> Default Pending Inward
// -----------------------------------------------------------
echo "\n--- Group 2: Purchase Creation & Initial Pending Inward ---\n";

// Insert test purchase bill with pending inward
$uniqueSuffix = time() . '_' . rand(100, 999);
$testBillNo = "PO-FLOW-{$uniqueSuffix}";

DB::query("INSERT INTO purchase_bills (
    concern_id, bill_no, supplier_invoice_no, supplier_id, bill_date,
    total_amount, tax_amount, net_amount, payment_status, payment_method,
    inward_status, created_by, created_at
) VALUES (?, ?, ?, 1, CURDATE(), 50000.00, 2500.00, 52500.00, 'unpaid', 'credit', 'pending', 1, NOW())",
    [1, $testBillNo, "INV-FLOW-{$uniqueSuffix}"]
);
$testBillId = (int)DB::lastInsertId();
assertCheck("Test Purchase Bill inserted with ID {$testBillId}", $testBillId > 0);

$billData = DB::fetch("SELECT * FROM purchase_bills WHERE id = ?", [$testBillId]);
assertCheck("Initial inward_status of bill is 'pending'", ($billData['inward_status'] ?? '') === 'pending');
$testPattern = "STRIPES {$uniqueSuffix}";

// Add 2 roll items with pending inward
DB::query("INSERT INTO purchase_bill_items (
    concern_id, purchase_bill_id, section_type, item_name, item_code,
    specification, color, pattern, designs_count, rq_qty, unit,
    gross_weight, net_weight, qty, inward_status, unit_price, gst_rate, total_price, tax_amount
) VALUES 
(1, ?, 'rolls', 'Premium Cotton', 'ROLL-T1', 'Combed 40s', 'SKY BLUE', ?, 1, 5, 'Rolls', 105.00, 100.00, 5.00, 'pending', 5000.00, 5.00, 25000.00, 1250.00),
(1, ?, 'rolls', 'Premium Cotton', 'ROLL-T2', 'Combed 40s', 'SKY BLUE', ?, 1, 5, 'Rolls', 105.00, 100.00, 5.00, 'pending', 5000.00, 5.00, 25000.00, 1250.00)",
[$testBillId, $testPattern, $testBillId, $testPattern]);

$itemsCount = (int)DB::fetchValue("SELECT COUNT(*) FROM purchase_bill_items WHERE purchase_bill_id = ?", [$testBillId]);
assertCheck("Bill items inserted with pending status", $itemsCount === 2);
RollRegistryService::syncSpecs(1);
$pendingRollSpec = DB::fetch(
    "SELECT * FROM fabric_roll_specs WHERE concern_id = 1 AND color = 'SKY BLUE' AND design_name = ?",
    [$testPattern]
);
assertCheck("Pending roll bill is absent from Purchase Stocks registry", empty($pendingRollSpec));

// -----------------------------------------------------------
// TEST GROUP 3: Label Generation -> "Add to Stock" Action
// -----------------------------------------------------------
echo "\n--- Group 3: Label Generation 'Add to Stock' Execution ---\n";

// Execute addToStock for the test bill
$_POST = [
    'csrf_token' => 'test_token_12345',
    'bill_id'    => $testBillId,
    'tab'        => 'rolls'
];

// Capture redirect from addToStock
$labelController = new class extends LabelController {
    public ?string $lastRedirect = null;
    public ?string $lastFlashType = null;
    public ?string $lastFlashMsg = null;

    public function redirect(string $url): void {
        $this->lastRedirect = $url;
    }

    public function flash(string $type, string $message): void {
        $this->lastFlashType = $type;
        $this->lastFlashMsg = $message;
    }
};

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['bill_id' => $testBillId];
$labelController->addToStock();
$billAfterGet = DB::fetch("SELECT inward_status FROM purchase_bills WHERE id = ?", [$testBillId]);
assertCheck("GET request cannot add purchase bill to stock", ($billAfterGet['inward_status'] ?? '') === 'pending');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET = [];
$labelController->addToStock();

assertCheck("addToStock redirected user to purchase_stocks", strpos($labelController->lastRedirect ?? '', '/m/purchase_stocks?tab=rolls') !== false, "Redirected to: " . ($labelController->lastRedirect ?? ''));
assertCheck("Success flash message set", $labelController->lastFlashType === 'success');

// Verify database status transition
$updatedBill = DB::fetch("SELECT * FROM purchase_bills WHERE id = ?", [$testBillId]);
assertCheck("purchase_bills inward_status transitioned to 'completed'", ($updatedBill['inward_status'] ?? '') === 'completed');
$inwardedRollSpec = DB::fetch(
    "SELECT * FROM fabric_roll_specs WHERE concern_id = 1 AND color = 'SKY BLUE' AND design_name = ?",
    [$testPattern]
);
assertCheck("Roll appears in Purchase Stocks after Add to Stock", !empty($inwardedRollSpec) && (float)$inwardedRollSpec['qty'] === 10.0);

$pendingItems = (int)DB::fetchValue("SELECT COUNT(*) FROM purchase_bill_items WHERE purchase_bill_id = ? AND inward_status = 'pending'", [$testBillId]);
assertCheck("All bill items transitioned to 'completed'", $pendingItems === 0);

// -----------------------------------------------------------
// TEST GROUP 4: Roll Spec Registry Synchronization
// -----------------------------------------------------------
echo "\n--- Group 4: Roll Spec Registry Synchronization ---\n";

$skyBlueSpec = DB::fetch(
    "SELECT * FROM fabric_roll_specs WHERE concern_id = 1 AND color = 'SKY BLUE' AND design_name = ?",
    [$testPattern]
);
assertCheck("Fabric roll spec registered for SKY BLUE / test pattern", !empty($skyBlueSpec));
assertCheck("Accumulated roll qty reflects inwarded items (at least 10 Rolls)", (float)($skyBlueSpec['qty'] ?? 0) >= 10.0);

// -----------------------------------------------------------
// TEST GROUP 5: Finished Goods Inward to Live Stocks & Ledger
// -----------------------------------------------------------
echo "\n--- Group 5: Finished Goods Inward & Stock Ledger ---\n";

// Create purchase bill with finished goods item
$fgBillNo = "PO-FG-{$uniqueSuffix}";
DB::query("INSERT INTO purchase_bills (
    concern_id, bill_no, supplier_invoice_no, supplier_id, bill_date,
    total_amount, tax_amount, net_amount, payment_status, payment_method,
    inward_status, created_by, created_at
) VALUES (1, ?, 'INV-FG-1', 1, CURDATE(), 4500.00, 225.00, 4725.00, 'unpaid', 'credit', 'pending', 1, NOW())",
    [$fgBillNo]
);
$fgBillId = (int)DB::lastInsertId();

DB::query(
    "INSERT INTO catalogs (concern_id, code, name, sizes, status, created_at)
     VALUES (1, ?, 'Test Polo T-Shirt', 'S,M,L', 'in_house', NOW())",
    ["CAT-FG-{$uniqueSuffix}"]
);
$fgCatalogId = (int)DB::lastInsertId();
$fgVariants = [];
foreach (['S' => 2, 'M' => 2, 'L' => 6] as $size => $allocatedQty) {
    DB::query(
        "INSERT INTO variants (
            concern_id, catalog_id, product_code, name, size, attributes, price, mrp, creation_date, status, created_at
        ) VALUES (1, ?, ?, ?, ?, ?, 450.00, 799.00, CURDATE(), 'active', NOW())",
        [
            $fgCatalogId,
            "SKU-FG-{$uniqueSuffix}-{$size}",
            "Test Polo T-Shirt - {$size}",
            $size,
            json_encode(['color' => 'Red', 'allocated_qty' => $allocatedQty]),
        ]
    );
    $fgVariants[$size] = (int)DB::lastInsertId();
}
$primaryVariantId = $fgVariants['S'];

DB::query("INSERT INTO purchase_bill_items (
    concern_id, purchase_bill_id, variant_id, catalog_id, section_type, item_name, item_code,
    specification, qty, inward_status, unit_price, gst_rate, total_price, tax_amount
) VALUES (1, ?, ?, ?, 'finished_goods', 'Test Polo T-Shirt', 'SKU-FG-TEST', 'S,M,L', 10.00, 'pending', 450.00, 5.00, 4500.00, 225.00)",
    [$fgBillId, $primaryVariantId, $fgCatalogId]
);
$fgItemId = (int)DB::lastInsertId();

// Now inward the item via LabelController::addToStock
$_POST = [
    'csrf_token' => 'test_token_12345',
    'item_id'    => $fgItemId,
    'tab'        => 'finished_goods'
];

$labelController->lastRedirect = null;
$labelController->addToStock();

assertCheck("addToStock for Finished Goods redirected to Live Stocks",
    strpos($labelController->lastRedirect ?? '', '/m/stocks') !== false,
    "Redirected to: " . ($labelController->lastRedirect ?? '')
);

$expectedVariantStock = ['S' => 2.0, 'M' => 2.0, 'L' => 6.0];
$newVariantStocks = [];
foreach ($fgVariants as $size => $variantId) {
    $newVariantStocks[$size] = (float)DB::fetchValue(
        "SELECT qty FROM stocks WHERE concern_id = 1 AND variant_id = ? AND location = 'Main Store'",
        [$variantId]
    );
}
assertCheck("Mapped variant stock is allocated as S=2, M=2, L=6", $newVariantStocks == $expectedVariantStock);
assertCheck("Total Live Stock equals the 10-piece purchase quantity", array_sum($newVariantStocks) == 10.0);

$ledgerEntries = DB::fetchAll(
    "SELECT variant_id, qty_in FROM stock_ledger
     WHERE concern_id = 1 AND ref_module = 'Purchase Inward Item' AND ref_id = ?",
    [$fgItemId]
);
assertCheck("Three stock ledger entries are logged against the purchase item", count($ledgerEntries) === 3);

$_POST = [
    'csrf_token' => 'test_token_12345',
    'item_id'    => $fgItemId,
    'tab'        => 'finished_goods'
];
$labelController->addToStock();
$stockAfterRetry = [];
foreach ($fgVariants as $size => $variantId) {
    $stockAfterRetry[$size] = (float)DB::fetchValue(
        "SELECT qty FROM stocks WHERE concern_id = 1 AND variant_id = ? AND location = 'Main Store'",
        [$variantId]
    );
}
assertCheck("Retrying completed inward does not duplicate any variant stock", $stockAfterRetry == $newVariantStocks);

// -----------------------------------------------------------
// TEST GROUP 6: Tenant Isolation Security
// -----------------------------------------------------------
echo "\n--- Group 6: Tenant Isolation Security ---\n";

// Switch to Concern 2
$_SESSION['user']['concern_id'] = 2;

$_POST = [
    'csrf_token' => 'test_token_12345',
    'bill_id'    => $testBillId // Concern 1 bill!
];

$labelController->lastFlashType = null;
$labelController->addToStock();

assertCheck("Concern 2 blocked from adding Concern 1 bill to stock", $labelController->lastFlashType === 'error');

// Clean up test records
DB::query("DELETE FROM purchase_bills WHERE id IN (?, ?)", [$testBillId, $fgBillId]);
DB::query("DELETE FROM stock_ledger WHERE ref_module = 'Purchase Inward Item' AND ref_id = ?", [$fgItemId]);
RollRegistryService::syncSpecs(1);
foreach ($fgVariants as $variantId) {
    DB::query("DELETE FROM stocks WHERE concern_id = 1 AND variant_id = ?", [$variantId]);
    DB::query("DELETE FROM variants WHERE id = ?", [$variantId]);
}
DB::query("DELETE FROM catalogs WHERE id = ?", [$fgCatalogId]);

// -----------------------------------------------------------
// SUMMARY
// -----------------------------------------------------------
echo "\n======================================================\n";
echo "TEST RESULTS: {$passCount} Passed, {$failCount} Failed\n";
echo "======================================================\n";

if ($failCount > 0) {
    exit(1);
}
