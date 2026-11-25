<?php
// tests/CatalogMappingQuickFillValidationTest.php - Verify Subcategory Ajax, Color Attributes Extraction, Quick Fill & Strict Validations

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';

use App\Core\Auth;
use App\Core\DB;
use App\Core\Env;
use App\Controllers\CatalogMappingController;
use App\Controllers\ProductionController;

Env::load(__DIR__ . '/../.env');
Auth::startSession();

// Mock Super Admin session
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'super_admin';
$_SESSION['user_name'] = 'Super Administrator';
$_SESSION['concern_id'] = 1;

function assertCondition(bool $cond, string $msg): void {
    if (!$cond) {
        echo "  [FAIL] $msg\n";
        exit(1);
    }
    echo "  [PASS] $msg\n";
}

echo "=== Catalog Mapping Quick Fill & Strict Validation Tests ===\n";

// 1. Test Subcategory AJAX Endpoint dynamically using existing category & subcategory
$existingSub = DB::fetch("SELECT s.*, c.name as cat_name FROM subcategories s JOIN categories c ON s.category_id = c.id LIMIT 1");
if ($existingSub) {
    $testCatId = (int)$existingSub['category_id'];
    $subName = trim($existingSub['name']);
    $catName = trim($existingSub['cat_name']);
    $ajaxOutput = shell_exec("php -r \"require 'app/core/Autoloader.php'; App\Core\Env::load('.env'); App\Core\Auth::startSession(); \$_SESSION['user_id'] = 1; \$_SESSION['concern_id'] = 1; \$_GET['category_id'] = {$testCatId}; (new App\Controllers\ProductionController())->getSubcategoriesAjax();\"");
    $ajaxData = json_decode($ajaxOutput, true);

    assertCondition(is_array($ajaxData) && ($ajaxData['success'] ?? false) === true, "Subcategories AJAX returned success: true");
    assertCondition(isset($ajaxData['data']) && count($ajaxData['data']) > 0, "Subcategories AJAX returned non-empty data array for {$catName} (catId = {$testCatId})");
    $foundSub = false;
    foreach ($ajaxData['data'] as $sub) {
        if (trim($sub['name']) === $subName) {
            $foundSub = true;
            break;
        }
    }
    assertCondition($foundSub, "Subcategory '{$subName}' found under category '{$catName}'");
} else {
    echo "  [SKIP] No category with subcategories found in DB.\n";
}

// 2. Test Color Attribute Extraction & View Rendering in mapItemForm()
// Ensure sample bill item 1 exists or query from purchase_bill_items
$item = DB::fetch("SELECT * FROM purchase_bill_items WHERE section_type = 'finished_goods' LIMIT 1");
if (!$item) {
    echo "  [SKIP] No finished_goods item found to test mapItemForm rendering.\n";
} else {
    $_GET['id'] = $item['id'];
    $mapCtrl = new CatalogMappingController();
    ob_start();
    $mapCtrl->mapItemForm();
    $formHtml = ob_get_clean();

    assertCondition(str_contains($formHtml, 'id="colorSuggestions"'), "Color suggestions datalist rendered in form");
    assertCondition(str_contains($formHtml, 'value="red"') || str_contains($formHtml, 'pickSavedColor(\'red\')'), "Color 'red' available in suggestions");
    assertCondition(str_contains($formHtml, 'value="blue"') || str_contains($formHtml, 'pickSavedColor(\'blue\')'), "Color 'blue' available in suggestions");

    // 3. Test Quick Fill Bar inputs in form
    assertCondition(str_contains($formHtml, 'Quick Fill (All Columns):'), "Quick Fill bar header rendered");
    assertCondition(!str_contains($formHtml, 'id="quickColorInput"'), "Quick fill Color input is removed");
    assertCondition(str_contains($formHtml, 'id="quickQtyInput"'), "Quick fill Qty input exists");
    assertCondition(str_contains($formHtml, '>QTY</label>') && !str_contains($formHtml, 'QTY / SIZE'), "Quick fill quantity label is QTY");
    assertCondition(str_contains($formHtml, 'id="quickWeightInput"'), "Quick fill Weight input exists");
    assertCondition(str_contains($formHtml, 'id="quickHsnInput"'), "Quick fill HSN input exists");
    assertCondition(str_contains($formHtml, 'id="quickMrpInput"'), "Quick fill MRP input exists");
    assertCondition(str_contains($formHtml, 'id="quickSellingInput"'), "Quick fill Selling Price input exists");
    assertCondition(str_contains($formHtml, 'applyQuickFillAll()'), "applyQuickFillAll function exists");

    // 4. Test Client Validations inside <script>
    assertCondition(str_contains($formHtml, 'Math.abs(totalAlloc - purchasedBatchQty) > 0.001'), "Client-side quantity sum validation alert exists");
    assertCondition(str_contains($formHtml, 'sp <= supplierUnitCost'), "Client-side selling price > supplier cost validation exists");
    assertCondition(str_contains($formHtml, 'mrp <= sp'), "Client-side MRP > selling price validation exists");

    // 5. Test Server-side Quantity Validation (< and > total qty)
    $purchasedQty = (float)($item['qty'] ?? 10);
    $_SESSION['csrf_token'] = 'test_csrf_token_val';
    $csrfToken = 'test_csrf_token_val';

    // Case A: sum < purchasedQty
    $_POST = [
        'csrf_token' => $csrfToken,
        'purchase_bill_item_id' => $item['id'],
        'category_id' => 1,
        'style_code' => 'TEST-STY-VAL1',
        'style_name' => 'Test Style Val 1',
        'sizes' => ['S', 'M'],
        'variant_product_codes' => ['S' => 'TEST-STY-VAL1-S', 'M' => 'TEST-STY-VAL1-M'],
        'variant_names' => ['S' => 'Test S', 'M' => 'Test M'],
        'variant_colors' => ['S' => 'Red', 'M' => 'Red'],
        'variant_qtys' => ['S' => 1, 'M' => 1], // Sum is 2, assuming purchasedQty >= 10
        'variant_mrps' => ['S' => 500, 'M' => 500],
        'variant_selling_prices' => ['S' => 300, 'M' => 300],
    ];

    if ($purchasedQty > 2) {
        $mapCtrl->saveMapping();
        $flash = $_SESSION['flash_error'] ?? '';
        assertCondition(str_contains($flash, 'Quantity Validation Error'), "Server-side blocks submission when sum of quantities < total purchased qty");
    }

    // Case B: sum > purchasedQty
    $_POST['variant_qtys'] = ['S' => $purchasedQty + 5, 'M' => 5]; // Sum is purchasedQty + 10
    $mapCtrl->saveMapping();
    $flash = $_SESSION['flash_error'] ?? '';
    assertCondition(str_contains($flash, 'Quantity Validation Error'), "Server-side blocks submission when sum of quantities > total purchased qty");

    // Case C: Pricing Violation: Selling Price <= Supplier Cost
    $supplierCost = (float)($item['unit_price'] ?? 100);
    $_POST['variant_qtys'] = ['S' => $purchasedQty];
    $_POST['sizes'] = ['S'];
    $_POST['variant_selling_prices'] = ['S' => $supplierCost - 10]; // Lower than cost
    $_POST['variant_mrps'] = ['S' => $supplierCost + 100];
    $mapCtrl->saveMapping();
    $flash = $_SESSION['flash_error'] ?? '';
    assertCondition(str_contains($flash, 'Pricing Validation Error') && str_contains($flash, 'strictly greater than Supplier Cost'), "Server-side blocks submission when Selling Price <= Supplier Cost");

    // Case D: Pricing Violation: MRP <= Selling Price
    $_POST['variant_selling_prices'] = ['S' => $supplierCost + 50];
    $_POST['variant_mrps'] = ['S' => $supplierCost + 40]; // MRP < Selling Price
    $mapCtrl->saveMapping();
    $flash = $_SESSION['flash_error'] ?? '';
    assertCondition(str_contains($flash, 'Pricing Validation Error') && str_contains($flash, 'strictly greater than Selling Price'), "Server-side blocks submission when MRP <= Selling Price");
}

echo "\nSummary: All Quick Fill & Strict Validation Tests Passed Successfully!\n";
