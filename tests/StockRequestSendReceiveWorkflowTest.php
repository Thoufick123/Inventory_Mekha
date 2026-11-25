<?php
// tests/StockRequestSendReceiveWorkflowTest.php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Core\Auth;
use App\Controllers\ProductionStockRequestController;
use App\Services\DocumentNumberService;

class StockRequestSendReceiveWorkflowTest {
    private int $passed = 0;
    private int $failed = 0;

    private function assert(bool $condition, string $message): void {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$message}\n";
        }
    }

    public function run(): void {
        echo "\n======================================================\n";
        echo "  TEST: STOCK REQUEST SEND & RECEIVE WORKFLOW TEST     \n";
        echo "======================================================\n\n";

        Auth::startSession();

        $admin = DB::fetch("SELECT * FROM users WHERE status = 'active' LIMIT 1");
        $_SESSION['user'] = $admin;
        $_SESSION['user_id'] = (int)$admin['id'];
        $_SESSION['concern_id'] = 4;
        $_SESSION['role_id'] = (int)$admin['role_id'];

        $concernSender = 5; // Central Warehouse & Hub
        $concernReceiver = 4; // GRM GARMENTS

        // 1. Verify Database columns
        echo "1. Checking database schema...\n";
        $cols = DB::fetchAll("DESCRIBE production_stock_requests");
        $fields = array_column($cols, 'Field');
        $this->assert(in_array('source_concern_id', $fields, true), "Column source_concern_id exists on production_stock_requests");
        $this->assert(in_array('destination_concern_id', $fields, true), "Column destination_concern_id exists on production_stock_requests");

        // 2. Ensure ICECREAM variant exists
        echo "\n2. Checking ICECREAM variant from screenshot...\n";
        $icecream = DB::fetch("SELECT * FROM variants WHERE product_code = 'SKU-CAT-MEKHA-0003-L'");
        if (!$icecream) {
            DB::query(
                "INSERT INTO variants (concern_id, product_code, name, size, price, mrp, creation_date, status)
                 VALUES (4, 'SKU-CAT-MEKHA-0003-L', 'ICECREAM', 'L', 200.00, 400.00, CURDATE(), 'active')"
            );
            $icecream = DB::fetch("SELECT * FROM variants WHERE product_code = 'SKU-CAT-MEKHA-0003-L'");
        }
        $this->assert(!empty($icecream), "Variant ICECREAM exists (ID: {$icecream['id']})");
        $this->assert($icecream['product_code'] === 'SKU-CAT-MEKHA-0003-L', "SKU code matches Image 2: SKU-CAT-MEKHA-0003-L");

        // Ensure stock exists in Central Warehouse
        $variantId = (int)$icecream['id'];
        $chkStk = DB::fetch("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = 'Central Warehouse'", [$concernSender, $variantId]);
        if (!$chkStk) {
            DB::query("INSERT INTO stocks (concern_id, variant_id, location, qty, updated_at) VALUES (?, ?, 'Central Warehouse', 25.00, NOW())", [$concernSender, $variantId]);
            $chkStk = ['qty' => 25.00];
        }
        $this->assert((float)$chkStk['qty'] >= 10.00, "Central Warehouse has stock for ICECREAM: " . (float)$chkStk['qty']);

        // 3. Test Stock Map calculation
        echo "\n3. Testing Concern Stock computation for modal...\n";
        $stocksRaw = DB::fetchAll(
            "SELECT concern_id, variant_id, SUM(qty) AS total_qty 
             FROM stocks 
             WHERE location NOT LIKE '%:DAMAGED%' AND location NOT LIKE '%:IN_TRANSIT%' 
             GROUP BY concern_id, variant_id"
        );
        $concernStocksMap = [];
        foreach ($stocksRaw as $stk) {
            $concernStocksMap[(int)$stk['concern_id']][(int)$stk['variant_id']] = (float)$stk['total_qty'];
        }
        $this->assert(isset($concernStocksMap[$concernSender][$variantId]), "Concern 5 (Central Warehouse) stock is mapped for ICECREAM");
        $this->assert($concernStocksMap[$concernSender][$variantId] >= 10.00, "Concern 5 stock is at least 10 pcs");

        // 4. Test Storing Indent Requisition
        echo "\n4. Testing creation of Stock Indent Requisition (Image 2 submission)...\n";
        $testReqNo = DocumentNumberService::generate('PRODUCTION_STOCK_REQUEST', 0, 'REQ');
        $this->assert(
            !DB::fetchValue("SELECT id FROM production_stock_requests WHERE request_no = ? LIMIT 1", [$testReqNo]),
            "Generated production requisition number is unique: {$testReqNo}"
        );
        DB::query(
            "INSERT INTO production_stock_requests (
                concern_id, source_concern_id, destination_concern_id, request_no, request_type,
                from_location_type, from_location_id, to_location_type, to_location_id,
                status, priority, requested_qty, expected_date, notes, created_by, created_at
             ) VALUES (?, ?, ?, ?, 'INTER_MODULE', 'WAREHOUSE', 1, 'SHOP', 1, 'PENDING', 'NORMAL', 10.00, CURDATE(), 'Weekend retail replenishment', ?, NOW())",
            [$concernReceiver, $concernSender, $concernReceiver, $testReqNo, $_SESSION['user_id']]
        );
        $reqId = (int)DB::lastInsertId();
        $this->assert($reqId > 0, "Stock request created with ID {$reqId}");

        DB::query(
            "INSERT INTO production_stock_request_items (request_id, variant_id, requested_qty, status, created_at)
             VALUES (?, ?, 10.00, 'PENDING', NOW())",
            [$reqId, $variantId]
        );

        $savedReq = DB::fetch("SELECT * FROM production_stock_requests WHERE id = ?", [$reqId]);
        $this->assert($savedReq['request_no'] === $testReqNo, "Request number saved correctly");
        $this->assert((int)$savedReq['source_concern_id'] === $concernSender, "Source concern ID saved as 5 (Central Warehouse)");
        $this->assert((int)$savedReq['destination_concern_id'] === $concernReceiver, "Destination concern ID saved as 4 (GRM GARMENTS)");
        $this->assert((float)$savedReq['requested_qty'] === 10.00, "Requested Qty saved as 10.00");

        // 5. Test Receive Section Query (Image 1)
        echo "\n5. Testing Receive section query (Incoming requisitions destined for this concern)...\n";
        $receiveSql = "SELECT psr.*, 
                              COALESCE(dest_con.name, 'Main Retail Store') AS destination_concern_name, 
                              dest_con.code AS destination_concern_code,
                              COALESCE(src_con.name, 'Central Warehouse & Hub') AS source_concern_name, 
                              src_con.code AS source_concern_code,
                              u.name AS creator_name
                       FROM production_stock_requests psr
                       LEFT JOIN concerns dest_con ON psr.destination_concern_id = dest_con.id
                       LEFT JOIN concerns src_con ON (psr.source_concern_id = src_con.id OR (psr.source_concern_id IS NULL AND psr.concern_id = src_con.id))
                       LEFT JOIN users u ON psr.created_by = u.id
                       WHERE psr.destination_concern_id = ? OR (psr.destination_concern_id IS NULL AND psr.to_location_type = 'SHOP')";
        $received = DB::fetchAll($receiveSql, [$concernReceiver]);
        $this->assert(!empty($received), "Receive list returns incoming requests for concern #{$concernReceiver}");
        $found = false;
        foreach ($received as $r) {
            if ($r['request_no'] === $testReqNo) {
                $found = true;
                $this->assert($r['destination_concern_name'] === 'GRM GARMENTS', "Destination Retail Store name is GRM GARMENTS");
                $this->assert($r['source_concern_name'] === 'Central Warehouse & Hub', "Source Warehouse name is Central Warehouse & Hub");
                break;
            }
        }
        $this->assert($found, "Created requisition {$testReqNo} is listed in Receive Section");

        // 6. Test View Template structure
        echo "\n6. Verifying View Template compliance with Image 1 & Image 2...\n";
        $viewFile = dirname(__DIR__) . '/app/views/production/stock_requests/index.php';
        $this->assert(file_exists($viewFile), "View file app/views/production/stock_requests/index.php exists");
        $viewContent = file_get_contents($viewFile);

        // Verification of methods: Send and Receive
        $this->assert(str_contains($viewContent, 'Receive Section'), "Nav pill for Receive Section exists");
        $this->assert(str_contains($viewContent, 'Send Section'), "Nav pill for Send Section exists");

        // Verification of Image 1 Table & Empty State
        $this->assert(str_contains($viewContent, 'Stock Requisitions List'), "Header 'Stock Requisitions List' exists matching Image 1");
        $this->assert(str_contains($viewContent, 'REQUEST #'), "Column 'REQUEST #' exists");
        $this->assert(str_contains($viewContent, 'DESTINATION RETAIL STORE'), "Column 'DESTINATION RETAIL STORE' exists");
        $this->assert(str_contains($viewContent, 'SOURCE WAREHOUSE'), "Column 'SOURCE WAREHOUSE' exists");
        $this->assert(str_contains($viewContent, 'REQUESTED QTY'), "Column 'REQUESTED QTY' exists");
        $this->assert(str_contains($viewContent, 'DISPATCHED QTY'), "Column 'DISPATCHED QTY' exists");
        $this->assert(str_contains($viewContent, 'REQUESTED BY'), "Column 'REQUESTED BY' exists");
        $this->assert(str_contains($viewContent, 'STATUS'), "Column 'STATUS' exists");
        $this->assert(str_contains($viewContent, 'ACTION'), "Column 'ACTION' exists");
        $this->assert(str_contains($viewContent, 'No Shop Requests Found'), "Empty state 'No Shop Requests Found' exists matching Image 1");
        $this->assert(str_contains($viewContent, 'No store stock requisitions match the selected criteria.'), "Empty subtitle matches Image 1");
        $this->assert(str_contains($viewContent, 'bi bi-shop'), "Shop icon 'bi bi-shop' exists matching Image 1");

        // Verification of Image 2 Modal
        $this->assert(str_contains($viewContent, 'id="modalIndentStock"'), "Modal '#modalIndentStock' exists matching Image 2");
        $this->assert(str_contains($viewContent, 'Indent Stock from'), "Modal title 'Indent Stock from' exists matching Image 2");
        $this->assert(str_contains($viewContent, 'Select items and enter the quantity you wish to request from the central warehouse.'), "Subtitle matches Image 2");
        $this->assert(str_contains($viewContent, 'name="destination_concern_id"'), "Destination Concern selection option exists");
        $this->assert(str_contains($viewContent, 'Search / Select Item to Indent'), "Variant picker label matches Image 2");
        $this->assert(str_contains($viewContent, '-- Choose Product Variant --'), "Variant picker placeholder matches Image 2");
        $this->assert(str_contains($viewContent, 'CONCERN STOCK'), "Table column 'CONCERN STOCK' exists matching requirement");
        $this->assert(str_contains($viewContent, 'REQUEST QTY'), "Table column 'REQUEST QTY' exists matching Image 2");
        $this->assert(str_contains($viewContent, 'Notes / Purpose'), "Input 'Notes / Purpose' exists matching Image 2");
        $this->assert(str_contains($viewContent, 'e.g. Weekend retail replenishment'), "Notes placeholder matches Image 2");
        $this->assert(str_contains($viewContent, 'Submit Request'), "Button 'Submit Request' exists matching Image 2");

        // 7. Test Routing
        echo "\n7. Verifying route mappings...\n";
        $routesContent = file_get_contents(dirname(__DIR__) . '/app/routes.php');
        $this->assert(str_contains($routesContent, "'/production/stock-requests'"), "Route /production/stock-requests exists");
        $this->assert(str_contains($routesContent, "'/api/stock-requests/variant-stocks'"), "API route /api/stock-requests/variant-stocks exists");
        $this->assert(str_contains($routesContent, "'/m/request'"), "Route /m/request exists");

        echo "\n======================================================\n";
        echo "  RESULTS: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }
}

$test = new StockRequestSendReceiveWorkflowTest();
$test->run();
