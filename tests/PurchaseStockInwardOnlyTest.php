<?php
// tests/PurchaseStockInwardOnlyTest.php
// Verification that items only enter Purchase Stock when "Add to Stock" is clicked, and remain absent otherwise.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;
use App\Controllers\LabelController;
use App\Controllers\PurchaseStockController;

echo "======================================================\n";
echo "INVENTO: Purchase Stock Inward-Only Verification Test\n";
echo "======================================================\n\n";

$db = DB::connect();

$passCount = 0;
$failCount = 0;

function assertTest(string $title, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$title}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$title}" . ($detail ? " - {$detail}" : "") . "\n";
        $failCount++;
    }
}

// ----------------------------------------------------
// Step 1: Tenant & Auth Setup
// ----------------------------------------------------
echo "--- Step 1: Authentication & Tenant Setup ---\n";
$concern = $db->query("SELECT id, name FROM concerns LIMIT 1")->fetch();
$concernId = (int)$concern['id'];
$concernName = $concern['name'];

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';
$_SESSION['concern_id'] = $concernId;
$_SESSION['csrf_token'] = 'test_token_inward_123';
$_SESSION['user'] = [
    'id'         => 1,
    'username'   => 'admin',
    'role_id'    => 1,
    'concern_id' => $concernId
];
$_SESSION['enabled_modules'] = ['purchase_billing', 'label_generation', 'purchase_stocks', 'stocks'];
$_SESSION['user_permissions'] = [
    'purchase_billing' => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 1],
    'purchase_stocks'  => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 1],
    'label_generation' => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 1],
    'stocks'           => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 1],
];

assertTest("Concern setup for concern #{$concernId} ({$concernName})", $concernId > 0);

// ----------------------------------------------------
// Step 2: Create Purchase Bill with All 3 Types (Pending)
// ----------------------------------------------------
echo "\n--- Step 2: Create Purchase Bill with Rolls, Trims, & Finished Goods ---\n";
$timeSuffix = time() . '_' . rand(100, 999);
$billNo = "PB-INW-{$timeSuffix}";
$supplierId = (int)(DB::fetchValue("SELECT id FROM suppliers WHERE concern_id = ? LIMIT 1", [$concernId]) ?: DB::fetchValue("SELECT id FROM suppliers LIMIT 1"));
if (!$supplierId) {
    DB::query("INSERT INTO suppliers (concern_id, name, company_name, phone, status, created_at) VALUES (?, 'Inward Test Supplier', 'Supplier Co', '9876543210', 'active', NOW())", [$concernId]);
    $supplierId = (int)DB::lastInsertId();
}

$db->prepare("INSERT INTO purchase_bills (
    concern_id, bill_no, supplier_id, bill_date, total_amount, tax_amount, net_amount,
    payment_status, payment_method, inward_status, created_by, created_at
) VALUES (?, ?, ?, CURDATE(), 30000.00, 1500.00, 31500.00, 'unpaid', 'credit', 'pending', 1, NOW())")
   ->execute([$concernId, $billNo, $supplierId]);
$billId = (int)$db->lastInsertId();
assertTest("Purchase Bill inserted with ID #{$billId} and inward_status='pending'", $billId > 0);

// Insert Roll (Pending)
$rollCode = "TEST-R-{$timeSuffix}";
$db->prepare("INSERT INTO purchase_bill_items (
    concern_id, purchase_bill_id, section_type, item_name, item_code,
    pattern, color, rq_qty, qty, unit, net_weight, gross_weight, unit_price, total_price, inward_status
) VALUES (?, ?, 'rolls', 'Pure Cotton', ?, 'GEO CHECK', 'NAVY', 10, 10, 'RQ', 150.00, 155.00, 1000.00, 10000.00, 'pending')")
   ->execute([$concernId, $billId, $rollCode]);
$rollItemId = (int)$db->lastInsertId();

// Insert Trim / Other Garments (Pending)
$trimName = "Brass Zippers 18cm {$timeSuffix}";
$db->prepare("INSERT INTO purchase_bill_items (
    concern_id, purchase_bill_id, section_type, item_name, item_code,
    specification, color, qty, unit, unit_price, total_price, inward_status
) VALUES (?, ?, 'garment_other', ?, 'ZIP-18', 'YKK Antique Brass', 'BRASS', 500, 'Pieces', 20.00, 10000.00, 'pending')")
   ->execute([$concernId, $billId, $trimName]);
$trimItemId = (int)$db->lastInsertId();

// Insert Finished Good (Pending)
$fgName = "Linen Summer Shirt {$timeSuffix}";
$db->prepare("INSERT INTO purchase_bill_items (
    concern_id, purchase_bill_id, section_type, item_name, item_code,
    specification, color, qty, unit, unit_price, mrp, total_price, inward_status
) VALUES (?, ?, 'finished_goods', ?, 'LSS-01', 'Regular Fit', 'BEIGE', 50, 'Pieces', 200.00, 599.00, 10000.00, 'pending')")
   ->execute([$concernId, $billId, $fgName]);
$fgItemId = (int)$db->lastInsertId();

assertTest("3 Purchase bill items inserted (Roll: {$rollItemId}, Trim: {$trimItemId}, FG: {$fgItemId})", $rollItemId > 0 && $trimItemId > 0 && $fgItemId > 0);

// ----------------------------------------------------
// Step 3: Verify ABSENCE from Purchase Stock Module
// ----------------------------------------------------
echo "\n--- Step 3: Verify Pending Items MUST NOT Appear in Purchase Stock ---\n";

$stockController = new class extends PurchaseStockController {
    public function captureView(string $view, array $data = []): string {
        extract($data);
        ob_start();
        include __DIR__ . '/../app/views/' . $view . '.php';
        return ob_get_clean();
    }
};

// Check Tab 1: Rolls
$_GET = ['tab' => 'rolls'];
ob_start();
$stockController->index();
$rollsHtml = ob_get_clean();

assertTest("Pending Roll '{$rollCode}' is ABSENT from Purchase Stocks Rolls view", strpos($rollsHtml, $rollCode) === false);
assertTest("Pending Roll Pattern 'GEO CHECK' is ABSENT from Consolidated Roll Registry view", strpos($rollsHtml, 'GEO CHECK') === false);

// Check Tab 2: Garment Other (Trims)
$_GET = ['tab' => 'garment_other'];
ob_start();
$stockController->index();
$otherHtml = ob_get_clean();

assertTest("Pending Trim '{$trimName}' is ABSENT from Purchase Stocks Other Garments view", strpos($otherHtml, $trimName) === false);

// Check Tab 3: Finished Goods
$_GET = ['tab' => 'finished_goods'];
ob_start();
$stockController->index();
$fgHtml = ob_get_clean();

assertTest("Pending Finished Good '{$fgName}' is ABSENT from Purchase Stocks Finished Goods view", strpos($fgHtml, $fgName) === false);

// ----------------------------------------------------
// Step 4: Click 'Add to Stock' on the Trim Item Only
// ----------------------------------------------------
echo "\n--- Step 4: Click 'Add to Stock' for Trim Item Only ---\n";

$labelController = new class extends LabelController {
    public ?string $lastRedirect = null;
    public ?string $lastFlashType = null;
    public function redirect(string $url): void { $this->lastRedirect = $url; }
    public function flash(string $type, string $message): void { $this->lastFlashType = $type; }
};

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf_token' => 'test_token_inward_123',
    'item_id'    => $trimItemId,
    'tab'        => 'garment_other'
];

$labelController->addToStock();

assertTest("Trim item 'Add to Stock' redirected to purchase_stocks?tab=garment_other", strpos($labelController->lastRedirect ?? '', '/m/purchase_stocks?tab=garment_other') !== false);
assertTest("Flash message success returned", $labelController->lastFlashType === 'success');

// Verify Trim status transitioned to completed
$trimStatus = DB::fetchValue("SELECT inward_status FROM purchase_bill_items WHERE id = ?", [$trimItemId]);
assertTest("Trim inward_status is NOW 'completed'", $trimStatus === 'completed');

// Verify Roll and FG are STILL pending
$rollStatus = DB::fetchValue("SELECT inward_status FROM purchase_bill_items WHERE id = ?", [$rollItemId]);
$fgStatus   = DB::fetchValue("SELECT inward_status FROM purchase_bill_items WHERE id = ?", [$fgItemId]);
assertTest("Roll is STILL 'pending'", $rollStatus === 'pending');
assertTest("Finished Good is STILL 'pending'", $fgStatus === 'pending');

// Re-check Purchase Stocks:
// - Trim MUST NOW APPEAR
$_GET = ['tab' => 'garment_other'];
ob_start();
$stockController->index();
$otherHtmlAfter = ob_get_clean();
assertTest("Trim '{$trimName}' NOW APPEARS in Purchase Stocks after clicking Add to Stock", strpos($otherHtmlAfter, $trimName) !== false);

// - Roll MUST STILL BE ABSENT
$_GET = ['tab' => 'rolls'];
ob_start();
$stockController->index();
$rollsHtmlStill = ob_get_clean();
assertTest("Roll '{$rollCode}' STILL ABSENT from Purchase Stocks", strpos($rollsHtmlStill, $rollCode) === false);

// - FG MUST STILL BE ABSENT
$_GET = ['tab' => 'finished_goods'];
ob_start();
$stockController->index();
$fgHtmlStill = ob_get_clean();
assertTest("Finished Good '{$fgName}' STILL ABSENT from Purchase Stocks", strpos($fgHtmlStill, $fgName) === false);

// ----------------------------------------------------
// Step 5: Click 'Add to Stock' for Entire Bill
// ----------------------------------------------------
echo "\n--- Step 5: Click 'Add to Stock' for Entire Bill ---\n";

$_GET = [];
$_POST = [
    'csrf_token' => 'test_token_inward_123',
    'bill_id'    => $billId,
    'tab'        => 'all'
];

$labelController->addToStock();

assertTest("Bill 'Add to Stock' completed successfully", $labelController->lastFlashType === 'success');

$billFinalStatus = DB::fetchValue("SELECT inward_status FROM purchase_bills WHERE id = ?", [$billId]);
$rollFinalStatus = DB::fetchValue("SELECT inward_status FROM purchase_bill_items WHERE id = ?", [$rollItemId]);
$fgFinalStatus   = DB::fetchValue("SELECT inward_status FROM purchase_bill_items WHERE id = ?", [$fgItemId]);

assertTest("Purchase bill inward_status is 'completed'", $billFinalStatus === 'completed');
assertTest("Roll inward_status is 'completed'", $rollFinalStatus === 'completed');
assertTest("Finished good inward_status is 'completed'", $fgFinalStatus === 'completed');

// Re-check Purchase Stocks:
// - Rolls MUST NOW APPEAR
$_GET = ['tab' => 'rolls', 'view' => 'detailed'];
ob_start();
$stockController->index();
$rollsHtmlFinal = ob_get_clean();
assertTest("Roll '{$rollCode}' NOW APPEARS in Purchase Stocks after Add to Stock", strpos($rollsHtmlFinal, $rollCode) !== false);

// - Finished Goods MUST NOW APPEAR
$_GET = ['tab' => 'finished_goods'];
ob_start();
$stockController->index();
$fgHtmlFinal = ob_get_clean();
assertTest("Finished Good '{$fgName}' NOW APPEARS in Purchase Stocks after Add to Stock", strpos($fgHtmlFinal, $fgName) !== false);

// ----------------------------------------------------
// Cleanup Test Data
// ----------------------------------------------------
$db->prepare("DELETE FROM purchase_bill_items WHERE purchase_bill_id = ?")->execute([$billId]);
$db->prepare("DELETE FROM purchase_bills WHERE id = ?")->execute([$billId]);
$db->prepare("DELETE FROM fabric_roll_specs WHERE concern_id = ? AND design_name = 'GEO CHECK'")->execute([$concernId]);

echo "\n======================================================\n";
echo "TEST RESULTS: Total = {$passCount} Passed | {$failCount} Failed\n";
echo "======================================================\n";
if ($failCount === 0) {
    echo ">>> PURCHASE STOCK INWARD-ONLY RULE FULLY VERIFIED! <<<\n";
}
