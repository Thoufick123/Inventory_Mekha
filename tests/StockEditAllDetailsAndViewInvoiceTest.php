<?php
// tests/StockEditAllDetailsAndViewInvoiceTest.php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Core\Auth;
use App\Core\Csrf;
use App\Controllers\StockController;

class StockEditAllDetailsAndViewInvoiceTest
{
    private int $passed = 0;
    private int $failed = 0;

    private function assert(bool $condition, string $description, string $details = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$description}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$description}\n";
            if ($details !== '') {
                echo "         Details: {$details}\n";
            }
        }
    }

    public function run(): void
    {
        echo "\n======================================================\n";
        echo "  TEST: STOCKS EDIT ALL DETAILS & VIEW INVOICE TEST   \n";
        echo "======================================================\n\n";

        // Login as Super Admin or Concern 4 Admin
        $user = DB::fetch("SELECT * FROM users WHERE concern_id IS NULL LIMIT 1");
        if (!$user) {
            $user = DB::fetch("SELECT * FROM users WHERE status = 'active' LIMIT 1");
        }
        Auth::startSession();
        $_SESSION['user'] = $user;
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['concern_id'] = 4;
        $_SESSION['role_id'] = (int)$user['role_id'];

        $this->testDetailsJsonEndpoint();
        $this->testUpdateAllDetailsAndStockLedger();
        $this->testBulkComplementaryAndUndo();
        $this->testStocksViewHtmlAndModals();

        echo "\n======================================================\n";
        echo "  SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function testDetailsJsonEndpoint(): void
    {
        echo "Testing Details JSON Endpoint (/m/stocks/details):\n";

        // Find a stock item linked to purchase
        $stock = DB::fetch(
            "SELECT s.*, v.catalog_id
             FROM stocks s
             JOIN variants v ON s.variant_id = v.id
             WHERE s.qty > 0 AND s.concern_id = ?
             LIMIT 1",
            [$_SESSION['concern_id']]
        );

        $this->assert(!empty($stock), 'Found an active stock item for concern testing');
        if (!$stock) return;

        $_GET['variant_id'] = (string)$stock['variant_id'];
        $_GET['concern_id'] = (string)$stock['concern_id'];
        $_GET['location'] = (string)$stock['location'];

        ob_start();
        $controller = new StockController();
        try {
            $controller->details();
        } catch (\Throwable $e) {
            // output buffer will capture it
        }
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assert(is_array($data) && ($data['success'] ?? false) === true, 'Endpoint returns valid JSON with success: true');
        $this->assert(isset($data['variant']['name']), 'Response contains variant details with name: ' . ($data['variant']['name'] ?? ''));
        $this->assert(isset($data['variant']['product_code']), 'Response contains product_code: ' . ($data['variant']['product_code'] ?? ''));
        $this->assert(isset($data['stock']['qty']), 'Response contains stock quantity: ' . ($data['stock']['qty'] ?? ''));

        $this->assert(!array_key_exists('purchase_bill', $data), 'Response omits purchase billing and invoice details');
    }

    private function testUpdateAllDetailsAndStockLedger(): void
    {
        echo "\nTesting Update All Details (/m/stocks/update-details):\n";

        $concernId = (int)$_SESSION['concern_id'];

        // Create or find a test variant and stock line to safely modify
        $testVariant = DB::fetch("SELECT * FROM variants WHERE concern_id = ? AND product_code LIKE 'TEST-SKU-%' LIMIT 1", [$concernId]);
        if (!$testVariant) {
            $cat = DB::fetch("SELECT id FROM categories WHERE concern_id = ? LIMIT 1", [$concernId]);
            $sub = DB::fetch("SELECT id FROM subcategories WHERE concern_id = ? LIMIT 1", [$concernId]);
            DB::query(
                "INSERT INTO variants (concern_id, name, product_code, size, weight, weight_unit, price, mrp, category_id, subcategory_id, attributes, creation_date, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $concernId,
                    'Original Test Product',
                    'TEST-SKU-' . time(),
                    'M',
                    0.250,
                    'kg',
                    200.00,
                    250.00,
                    $cat['id'] ?? null,
                    $sub['id'] ?? null,
                    json_encode(['color' => 'Blue', 'hsn' => '6109']),
                    date('Y-m-d'),
                    'active',
                    date('Y-m-d H:i:s'),
                ]
            );
            $varId = (int)DB::lastInsertId();

            DB::query(
                "INSERT INTO stocks (concern_id, variant_id, location, qty, updated_at) VALUES (?, ?, ?, ?, ?)",
                [$concernId, $varId, 'Main Store', 10.00, date('Y-m-d H:i:s')]
            );
            DB::query(
                "INSERT INTO stock_ledger (concern_id, variant_id, location, qty_in, qty_out, ref_module, ref_id, note, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$concernId, $varId, 'Main Store', 10.00, 0, 'Purchase Inward', 1, 'Initial test inward', $_SESSION['user_id'], date('Y-m-d H:i:s')]
            );
            $testVariant = DB::fetch("SELECT * FROM variants WHERE id = ?", [$varId]);
        }

        $variantId = (int)$testVariant['id'];
        $location = 'Main Store';
        $currentStock = DB::fetch("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = ?", [$concernId, $variantId, $location]);
        $currentQty = (float)($currentStock['qty'] ?? 10);
        $newQty = (int)($currentQty + 3);

        $updatedName = 'Updated Test Product ' . rand(100, 999);
        $updatedSku = 'TEST-SKU-' . rand(1000, 9999);
        $updatedColor = 'Navy Blue';
        $updatedHsn = '6205';
        $updatedSize = 'XL';
        $updatedWeight = 0.350;
        $updatedCostPrice = 249.50;
        $updatedPrice = 299.50;
        $updatedMrp = 399.00;

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = Auth::csrfToken();
        $_POST = [
            'csrf_token' => $token,
            'concern_id' => (string)$concernId,
            'variant_id' => (string)$variantId,
            'location' => $location,
            'variant_name' => $updatedName,
            'product_code' => $updatedSku,
            'size' => $updatedSize,
            'color' => $updatedColor,
            'hsn_code' => $updatedHsn,
            'weight' => (string)$updatedWeight,
            'weight_unit' => 'kg',
            'cost_price' => (string)$updatedCostPrice,
            'price' => (string)$updatedPrice,
            'mrp' => (string)$updatedMrp,
            'qty' => (string)$newQty,
            'category_id' => (string)($testVariant['category_id'] ?? ''),
            'subcategory_id' => (string)($testVariant['subcategory_id'] ?? ''),
        ];

        ob_start();
        $controller = new StockController();
        try {
            $controller->updateDetails();
        } catch (\Throwable $e) {
            echo "Exception caught: " . $e->getMessage() . "\n";
        }
        $res = ob_get_clean();
        if (!empty($_SESSION['flash_error'])) {
            echo "Flash Error: " . $_SESSION['flash_error'] . "\n";
        }
        if (!empty($_SESSION['flash_success'])) {
            echo "Flash Success: " . $_SESSION['flash_success'] . "\n";
        }

        // Verify variant in DB
        $reloadedVariant = DB::fetch("SELECT * FROM variants WHERE id = ?", [$variantId]);
        $this->assert($reloadedVariant['name'] === $updatedName, 'Variant name updated to: ' . $reloadedVariant['name']);
        $this->assert($reloadedVariant['product_code'] === $updatedSku, 'SKU code updated to: ' . $reloadedVariant['product_code']);
        $this->assert($reloadedVariant['size'] === $updatedSize, 'Size updated to: ' . $reloadedVariant['size']);
        $this->assert((float)$reloadedVariant['cost_price'] === $updatedCostPrice, 'Cost price updated to: ' . $reloadedVariant['cost_price']);
        $this->assert((float)$reloadedVariant['price'] === $updatedPrice, 'Price updated to: ' . $reloadedVariant['price']);
        $this->assert((float)$reloadedVariant['mrp'] === $updatedMrp, 'MRP updated to: ' . $reloadedVariant['mrp']);

        $attributes = json_decode((string)$reloadedVariant['attributes'], true);
        $this->assert(($attributes['color'] ?? '') === $updatedColor, 'Variant attribute color updated to: ' . ($attributes['color'] ?? ''));
        $this->assert(($attributes['hsn'] ?? '') === $updatedHsn, 'Variant attribute HSN updated to: ' . ($attributes['hsn'] ?? ''));

        // Verify stock quantity in DB
        $reloadedStock = DB::fetch("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = ?", [$concernId, $variantId, $location]);
        $this->assert((int)$reloadedStock['qty'] === $newQty, 'Live stock quantity updated to: ' . $reloadedStock['qty']);

        // Verify stock ledger movement
        $latestLedger = DB::fetch(
            "SELECT * FROM stock_ledger WHERE concern_id = ? AND variant_id = ? AND location = ? ORDER BY id DESC LIMIT 1",
            [$concernId, $variantId, $location]
        );
        $this->assert(!empty($latestLedger), 'Stock ledger entry created for quantity update');
        $this->assert((float)$latestLedger['qty_in'] === 3.0, 'Ledger recorded correct inward delta of 3.0');

        echo "\nTesting stock edit price-order validation:\n";
        $validPost = $_POST;
        $invalidPrices = [
            ['cost_price' => $updatedPrice + 1, 'price' => $updatedPrice, 'mrp' => $updatedMrp],
            ['cost_price' => $updatedCostPrice, 'price' => $updatedMrp, 'mrp' => $updatedMrp],
            ['cost_price' => $updatedCostPrice, 'price' => $updatedMrp + 1, 'mrp' => $updatedMrp],
        ];
        foreach ($invalidPrices as $invalidPrice) {
            $_POST = array_merge($validPost, array_map('strval', $invalidPrice));
            ob_start();
            $invalidController = new StockController();
            $invalidController->updateDetails();
            ob_end_clean();
            $this->assert(
                strpos((string)($_SESSION['flash_error'] ?? ''), 'Cost Price < Selling Price < MRP') !== false,
                'Edit rejects price combinations outside the strict cost < selling < MRP order'
            );
        }
        $unchangedVariant = DB::fetch("SELECT cost_price, price, mrp FROM variants WHERE id = ?", [$variantId]);
        $this->assert(
            (float)$unchangedVariant['cost_price'] === $updatedCostPrice
            && (float)$unchangedVariant['price'] === $updatedPrice
            && (float)$unchangedVariant['mrp'] === $updatedMrp,
            'Invalid price order leaves stored prices unchanged'
        );

        // Cleanup test data
        DB::query("DELETE FROM stock_ledger WHERE variant_id = ?", [$variantId]);
        DB::query("DELETE FROM stocks WHERE variant_id = ?", [$variantId]);
        DB::query("DELETE FROM variants WHERE id = ?", [$variantId]);
    }

    private function testStocksViewHtmlAndModals(): void
    {
        echo "\nTesting Stocks View HTML and Modal Markups:\n";

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        ob_start();
        $controller = new StockController();
        $controller->index();
        $html = ob_get_clean();

        $this->assert(strpos($html, 'data-stock-view') !== false, 'HTML contains [data-stock-view] buttons');
        $this->assert(strpos($html, 'View Details') !== false, 'HTML displays "View Details" action label');
        $this->assert(strpos($html, 'data-stock-edit') !== false, 'HTML contains [data-stock-edit] buttons');
        $this->assert(strpos($html, 'id="stockDetailsModal"') !== false, 'HTML contains #stockDetailsModal for viewing product and stock details');
        $this->assert(strpos($html, 'id="stockEditModal"') !== false, 'HTML contains #stockEditModal for editing all details');
        $this->assert(strpos($html, 'Purchase Billing & Invoice Information') === false, 'Details modal omits purchase billing and invoice information');
        $this->assert(strpos($html, 'id="detailSupplierInvoiceNo"') === false, 'Details modal omits Supplier Invoice Number');
        $this->assert(strpos($html, 'id="detailAttachmentContainer"') === false, 'Details modal omits invoice attachments');
        $this->assert(strpos($html, 'id="stockEditName"') !== false, 'Edit modal includes Product Name input');
        $this->assert(strpos($html, 'id="stockEditSku"') !== false, 'Edit modal includes SKU Code input');
        $this->assert(strpos($html, 'id="stockEditCategory"') !== false, 'Edit modal includes Category select');
        $this->assert(strpos($html, 'id="stockEditSubcategory"') !== false, 'Edit modal includes Subcategory select');
        $this->assert(strpos($html, 'id="stockEditColor"') !== false, 'Edit modal includes Color input');
        $this->assert(strpos($html, 'id="stockEditHsn"') !== false, 'Edit modal includes HSN Code input');
        $this->assert(strpos($html, 'id="stockEditCostPrice"') !== false, 'Edit modal includes Cost Price input');
        $this->assert(strpos($html, 'id="stockEditPrice"') !== false, 'Edit modal includes Selling Price input');
        $this->assert(strpos($html, 'id="stockEditMrp"') !== false, 'Edit modal includes MRP input');
        $this->assert(strpos($html, 'id="stockEditQuantity"') !== false, 'Edit modal includes Live Stock Quantity input');
        $this->assert(strpos($html, 'Cost Price &lt; Selling Price &lt; MRP') !== false, 'Edit modal explains the required price order');
        $this->assert(strpos($html, '/m/stocks/update-details') !== false, 'Form action points to /m/stocks/update-details');
        $this->assert(strpos($html, 'data-bs-target="#bulkUndoComplementaryModal"') !== false, 'Bulk action bar contains Undo Complementary button');
        $this->assert(strpos($html, 'id="bulkUndoComplementaryModal"') !== false, 'HTML contains #bulkUndoComplementaryModal');
        $this->assert(strpos($html, '/m/stocks/bulk-unassign-complementary') !== false, 'Undo complementary form points to /m/stocks/bulk-unassign-complementary');
    }

    private function testBulkComplementaryAndUndo(): void
    {
        echo "\nTesting Bulk Complementary and Undo (/m/stocks/bulk-assign-complementary & /m/stocks/bulk-unassign-complementary):\n";

        $concernId = 4;
        $testSku = 'TEST-COMP-' . time();
        DB::query(
            "INSERT INTO variants (concern_id, product_code, name, cost_price, price, mrp, is_complementary, creation_date, status)
             VALUES (?, ?, ?, 100.00, 150.00, 200.00, 0, CURDATE(), 'active')",
            [$concernId, $testSku, 'Test Bulk Complementary Variant']
        );
        $variantId = (int)DB::pdo()->lastInsertId();
        $this->assert($variantId > 0, "Created test variant for complementary testing (ID: {$variantId})");

        // 1. Assign complementary
        $_POST = [
            'csrf_token' => Csrf::token(),
            'concern_id' => (string)$concernId,
            'variant_ids' => [(string)$variantId],
        ];

        ob_start();
        $controller = new StockController();
        $controller->bulkAssignComplementary();
        ob_end_clean();

        $assigned = DB::fetch("SELECT is_complementary FROM variants WHERE id = ?", [$variantId]);
        $this->assert((int)$assigned['is_complementary'] === 1, 'bulkAssignComplementary sets is_complementary = 1');

        // 2. Undo complementary (move back to normal)
        $_POST = [
            'csrf_token' => Csrf::token(),
            'concern_id' => (string)$concernId,
            'variant_ids' => [(string)$variantId],
        ];

        ob_start();
        $controller = new StockController();
        $controller->bulkUnassignComplementary();
        ob_end_clean();

        $unassigned = DB::fetch("SELECT is_complementary FROM variants WHERE id = ?", [$variantId]);
        $this->assert((int)$unassigned['is_complementary'] === 0, 'bulkUnassignComplementary restores is_complementary = 0 (moved into normal)');

        // Cleanup
        DB::query("DELETE FROM variants WHERE id = ?", [$variantId]);
    }
}

$test = new StockEditAllDetailsAndViewInvoiceTest();
$test->run();
