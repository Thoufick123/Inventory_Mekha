<?php
/**
 * Test: Stitching to Label Generation and Stock Inward Flow
 */
require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;
use App\Controllers\LabelController;
use App\Controllers\StockController;

echo "=== STARTING STITCHING TO LABEL AND STOCK FLOW TEST ===\n";

// 1. Authenticate as Super Admin
$admin = DB::fetch("SELECT * FROM users WHERE email = 'admin@invento.com'");
if (!$admin) {
    echo "ERROR: Admin user not found.\n";
    exit(1);
}
$_SESSION['user_id'] = $admin['id'];
$_SESSION['role_id'] = $admin['role_id'];
$_SESSION['user_name'] = $admin['name'];
$_SESSION['concern_id'] = 2; // Test with Concern 2
$_SESSION['csrf_token'] = 'test_token_stitching_flow_123';

echo "[TEST 1] Testing LabelController::index() for Production Labels...\n";
$_GET['tab'] = 'production';
ob_start();
$labelCtrl = new LabelController();
$labelCtrl->index();
$html = ob_get_clean();

if (strpos($html, 'STCH-20261119-0001') !== false) {
    echo "  PASS: Batch STCH-20261119-0001 is present in the rendered HTML.\n";
} else {
    echo "  FAIL: Batch STCH-20261119-0001 not found in rendered HTML.\n";
    exit(1);
}

if (strpos($html, 'FLO-B-FL-XS') !== false) {
    echo "  PASS: Stitched item FLO-B-FL-XS is present in the rendered HTML dropdown/table.\n";
} else {
    echo "  FAIL: Stitched item FLO-B-FL-XS not found in HTML.\n";
    exit(1);
}

if (strpos($html, 'prod_item_select') !== false) {
    echo "  PASS: #prod_item_select dropdown exists.\n";
} else {
    echo "  FAIL: #prod_item_select dropdown missing.\n";
    exit(1);
}

if (strpos($html, 'Add to Stock') !== false) {
    echo "  PASS: 'Add to Stock' button exists in production label tab.\n";
} else {
    echo "  FAIL: 'Add to Stock' button missing.\n";
    exit(1);
}

// 2. Test Label Generation for a Stitched Item
echo "[TEST 2] Testing LabelController::generate() for Production Label...\n";
$stitchingItem = DB::fetch("SELECT * FROM stitching_items WHERE stitching_id = 3 AND sku_code = 'FLO-B-FL-XS' LIMIT 1");
if (!$stitchingItem) {
    echo "ERROR: Stitching item FLO-B-FL-XS not found in database.\n";
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$token = Auth::csrfToken();
$_SESSION['csrf_token'] = $token;
$_POST['csrf_token'] = $token;
$_POST['label_type'] = 'production';
$_POST['item_id'] = $stitchingItem['id'];
$_POST['qty'] = 10;

ob_start();
$labelCtrl->generate();
$printHtml = ob_get_clean();

if (strpos($printHtml, 'FLO-B-FL-XS') !== false || strpos($printHtml, 'floral Fabric') !== false) {
    echo "  PASS: Printable sheet successfully generated for stitching item!\n";
} else {
    echo "  FAIL: Printable sheet did not contain garment details.\n";
    exit(1);
}

if (strpos($printHtml, 'Add to Stock') === false) {
    echo "  PASS: 'Add to Stock' button cleanly removed from printable sheet!\n";
} else {
    echo "  FAIL: 'Add to Stock' button still present on printable sheet.\n";
    exit(1);
}

// 3. Test Inwarding Stitched Production Item to Stock
echo "[TEST 3] Testing LabelController::addToStock() for Production Item...\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['csrf_token'] = $_SESSION['csrf_token'];
$_POST['item_id'] = $stitchingItem['id'];
$_POST['tab'] = 'production';

// We expect addToStock to redirect to /m/stocks
ob_start();
try {
    $labelCtrl->addToStock();
} catch (\Throwable $e) {
    echo "CAUGHT EXCEPTION: " . $e->getMessage() . "\n";
}
ob_end_clean();
if (!empty($_SESSION['flash'])) {
    echo "SESSION FLASH: ";
    print_r($_SESSION['flash']);
}

// Check if variant was created
$var = DB::fetch("SELECT * FROM variants WHERE concern_id = 2 AND sku_code = 'FLO-B-FL-XS'");
if ($var) {
    echo "  PASS: Variant successfully created/linked in variants table! ID: {$var['id']}, Name: {$var['name']}\n";
} else {
    echo "  FAIL: Variant not created in variants table.\n";
    exit(1);
}

// Check if stock row exists
$stock = DB::fetch("SELECT * FROM stocks WHERE concern_id = 2 AND variant_id = ?", [$var['id']]);
if ($stock && (float)$stock['qty'] >= 10.00) {
    echo "  PASS: Stock successfully updated in stocks table! Qty: {$stock['qty']} pcs at Location: {$stock['location']}\n";
} else {
    echo "  FAIL: Stock not found or incorrect qty in stocks table.\n";
    print_r($stock);
    exit(1);
}

// Check stock ledger
$ledger = DB::fetch("SELECT * FROM stock_ledger WHERE concern_id = 2 AND variant_id = ? AND ref_module = 'Production Inward'", [$var['id']]);
if ($ledger) {
    echo "  PASS: Stock ledger entry recorded! QtyIn: {$ledger['qty_in']}, Note: {$ledger['note']}\n";
} else {
    echo "  FAIL: Stock ledger entry missing.\n";
    exit(1);
}

// 4. Test StockController::index() to ensure it displays on Live Stocks page
echo "[TEST 4] Testing StockController::index() displays the inwarded garment...\n";
$_SESSION['concern_id'] = 2;
ob_start();
$stockCtrl = new StockController();
$stockCtrl->index();
$stockHtml = ob_get_clean();

if (strpos($stockHtml, 'FLO-B-FL-XS') !== false) {
    echo "  PASS: FLO-B-FL-XS appears in Live Inventory Stocks table!\n";
} else {
    echo "  FAIL: FLO-B-FL-XS not found on Live Inventory Stocks page.\n";
    exit(1);
}

if (strpos($stockHtml, 'floral Fabric (black) - XS') !== false) {
    echo "  PASS: Variant description appears correctly in Live Inventory Stocks!\n";
} else {
    echo "  FAIL: Variant description missing from Live Stocks.\n";
    exit(1);
}

// 5. Test Label Generation page now marks the item as In Stock
echo "[TEST 5] Testing LabelController::index() shows item as 'In Stock'...\n";
$_GET['tab'] = 'production';
ob_start();
$labelCtrl->index();
$updatedLabelHtml = ob_get_clean();

if (strpos($updatedLabelHtml, 'In Stock') !== false) {
    echo "  PASS: Item is now shown with 'In Stock' badge on Label Generation page!\n";
} else {
    echo "  FAIL: Item not marked with 'In Stock' badge.\n";
    exit(1);
}

// 6. Test Tab 2 (Purchase Finished Goods) also includes stitched items
echo "[TEST 6] Testing Tab 2 (Purchase Finished Goods) includes stitched finished garments...\n";
$_GET['tab'] = 'purchase_fg';
ob_start();
$labelCtrl->index();
$tab2Html = ob_get_clean();

if (strpos($tab2Html, 'Stitched Production Finished Goods') !== false && strpos($tab2Html, 'value="prod_') !== false) {
    echo "  PASS: Tab 2 dropdown includes Stitched Production Finished Goods optgroup!\n";
} else {
    echo "  FAIL: Tab 2 does not include stitched production items.\n";
    exit(1);
}

echo "=== ALL TESTS PASSED SUCCESSFULLY! ===\n";
