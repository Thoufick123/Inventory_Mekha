<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Controllers\PurchaseController;

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

echo "=== Purchase Billing Form Enhancement Verification Test ===\n";

// Set up concern session
$user = DB::fetch("SELECT u.*, c.id AS cid, c.name AS cname FROM users u JOIN concerns c ON u.concern_id = c.id WHERE u.status = 'active' AND u.concern_id IS NOT NULL LIMIT 1");
if (!$user) {
    echo "No valid concern user found.\n";
    exit(1);
}

\App\Core\Auth::startSession();
$_SESSION['user_id'] = $user['id'];
$_SESSION['concern_id'] = $user['cid'];
$_SESSION['role'] = $user['role'] ?? 'concern_admin';

$controller = new PurchaseController();
ob_start();
$controller->createBill();
$html = ob_get_clean();

assertCondition(!empty($html), "Purchase billing create form rendered successfully");

// 1. Rolls initially empty
assertCondition(str_contains($html, '<span class="badge bg-primary rounded-pill" id="rollBadgeCount">0</span>'), "Roll badge count is initially 0 (not 1)");
assertCondition(str_contains($html, '<span id="rollCountHeader">0</span>'), "Roll header count is initially 0 (not 1)");
assertCondition(str_contains($html, '<div id="rollsContainer"></div>'), "Rolls container starts completely empty (no hardcoded Roll #1)");
assertCondition(str_contains($html, 'id="noRollsPlaceholder"'), "No rolls placeholder banner exists");

// 2. Roll removal restriction removed
assertCondition(!str_contains($html, 'At least one fabric roll is recommended'), "Blocking alert 'At least one fabric roll is recommended' is removed");

// 3. Roll field labels: overall weight and unit price
assertCondition(str_contains($html, 'Overall Weight (kg)'), "Roll card dynamic template uses 'Overall Weight (kg)' label");
assertCondition(str_contains($html, 'Unit Price (₹)'), "Roll card dynamic template uses 'Unit Price (₹)' label");
assertCondition(str_contains($html, 'OVERALL WEIGHT (KG)'), "Roll inventory table header contains 'OVERALL WEIGHT (KG)'");
assertCondition(str_contains($html, 'UNIT PRICE (₹)'), "Roll inventory table header contains 'UNIT PRICE (₹)'");

// 4. Finished goods: supplier cost label -> Unit Price of Supplier Cost, weight removed
assertCondition(str_contains($html, 'Unit Price of Supplier Cost (₹)'), "Finished goods card dynamic template uses 'Unit Price of Supplier Cost (₹)' label");
assertCondition(str_contains($html, 'UNIT PRICE OF SUPPLIER COST (₹)'), "Finished goods table header uses 'UNIT PRICE OF SUPPLIER COST (₹)'");
assertCondition(!str_contains($html, 'fg-input-weight'), "Finished goods input card has no weight input (fg-input-weight removed)");

// 5. Finished goods table does not have WEIGHT column
$finishedGoodsTableStart = strpos($html, 'id="finishedGoodsTable"');
if ($finishedGoodsTableStart !== false) {
    $finishedGoodsTableHtml = substr($html, $finishedGoodsTableStart, 600);
    assertCondition(!str_contains($finishedGoodsTableHtml, '<th>WEIGHT (KG)</th>'), "Finished goods table header does not contain 'WEIGHT (KG)' column");
} else {
    assertCondition(false, "finishedGoodsTable found in HTML");
}

// 6. Test purchase_billing_view.php rendering
$bill = DB::fetch("SELECT * FROM purchase_bills WHERE id = 126 LIMIT 1");
if ($bill) {
    $_GET['id'] = $bill['id'];
    ob_start();
    $controller->viewBill();
    $viewHtml = ob_get_clean();
    assertCondition(!empty($viewHtml), "Purchase billing view rendered successfully");
    assertCondition(str_contains($viewHtml, 'OVERALL WEIGHT (KG)'), "View modal uses 'OVERALL WEIGHT (KG)' for fabric rolls");
    assertCondition(str_contains($viewHtml, 'UNIT PRICE OF SUPPLIER COST (₹)'), "View modal uses 'UNIT PRICE OF SUPPLIER COST (₹)' for finished goods");
    
    // Check finished goods table in view doesn't have weight header
    $viewFgTableStart = strpos($viewHtml, 'Finished Goods / Apparel Items');
    if ($viewFgTableStart !== false) {
        $viewFgTableHtml = substr($viewHtml, $viewFgTableStart, 1500);
        assertCondition(!str_contains($viewFgTableHtml, '<th>WEIGHT (KG)</th>'), "View modal finished goods table does not have 'WEIGHT (KG)' column");
    }
}

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
