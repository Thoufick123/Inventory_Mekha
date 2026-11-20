<?php
require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;
use App\Controllers\LabelController;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$_SESSION['user_id'] = 1;
$_SESSION['concern_id'] = 1;
$_SESSION['role'] = 'super_admin';
$_SESSION['csrf_token'] = bin2hex(random_bytes(16));

echo "=== TEST 1: LABEL GENERATION HTML RENDERS STOCK VALIDATION ATTRIBUTES & BADGES ===\n";

$roll = DB::fetch("SELECT id, item_name, inward_status FROM purchase_bill_items WHERE section_type = 'rolls' LIMIT 1");
if (!$roll) {
    echo "No roll found in purchase_bill_items to test.\n";
    exit(1);
}
$rollId = (int)$roll['id'];
$origStatus = $roll['inward_status'] ?? 'pending';

// Set roll inward_status to 'completed' and render
DB::query("UPDATE purchase_bill_items SET inward_status = 'completed' WHERE id = ?", [$rollId]);

ob_start();
$labelCtrl = new LabelController();
$labelCtrl->index();
$htmlCompleted = ob_get_clean();

// Check for buttons and badges
assertContains($htmlCompleted, 'id="roll_add_stock_btn"', "roll_add_stock_btn missing");
assertContains($htmlCompleted, 'id="roll_in_stock_badge"', "roll_in_stock_badge missing");
assertContains($htmlCompleted, 'id="other_add_stock_btn"', "other_add_stock_btn missing");
assertContains($htmlCompleted, 'id="other_in_stock_badge"', "other_in_stock_badge missing");
assertContains($htmlCompleted, 'id="fg_add_stock_btn"', "fg_add_stock_btn missing");
assertContains($htmlCompleted, 'id="fg_in_stock_badge"', "fg_in_stock_badge missing");
assertContains($htmlCompleted, 'id="prod_add_stock_btn"', "prod_add_stock_btn missing");
assertContains($htmlCompleted, 'id="prod_in_stock_badge"', "prod_in_stock_badge missing");

// Check roll option has data-inward="completed" and [IN STOCK]
assertContains($htmlCompleted, 'value="' . $rollId . '"', "Roll option not rendered");
assertContains($htmlCompleted, 'data-inward="completed"', "data-inward completed missing on option");
assertContains($htmlCompleted, '[IN STOCK]', "[IN STOCK] text missing on completed roll option");

echo "PASSED: Roll rendered with data-inward='completed' and [IN STOCK] badge.\n";

// Set roll inward_status to 'pending' and render
DB::query("UPDATE purchase_bill_items SET inward_status = 'pending' WHERE id = ?", [$rollId]);

ob_start();
$labelCtrl = new LabelController();
$labelCtrl->index();
$htmlPending = ob_get_clean();

assertContains($htmlPending, 'data-inward="pending"', "data-inward pending missing on option");
echo "PASSED: Roll rendered with data-inward='pending'.\n";

echo "\n=== TEST 2: BACKEND VALIDATION REJECTS DUPLICATE ADD TO STOCK ===\n";

// Mark roll as 'completed'
DB::query("UPDATE purchase_bill_items SET inward_status = 'completed' WHERE id = ?", [$rollId]);

$_POST['csrf_token'] = $_SESSION['csrf_token'];
$_POST['item_id'] = $rollId;
$_POST['tab'] = 'rolls';
$_SERVER['REQUEST_METHOD'] = 'POST';

$caughtException = false;
$flashMsg = null;
try {
    ob_start();
    // Catch redirect / exit by overriding or inspecting flash message
    $labelCtrl = new LabelController();
    $labelCtrl->addToStock();
    ob_end_clean();
} catch (\Throwable $e) {
    $caughtException = true;
    echo "Caught expected duplicate error: " . $e->getMessage() . "\n";
    if (strpos($e->getMessage(), 'already been added to stock') === false) {
        echo "FAILED: Exception message does not contain 'already been added to stock'. Got: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Check flash message if redirect happened instead of throw
if (!$caughtException) {
    $flashMsg = $_SESSION['flash_warning'] ?? ($_SESSION['flash_error'] ?? null);
    if ($flashMsg && strpos($flashMsg, 'already been added to stock') !== false) {
        echo "Caught expected flash warning: " . $flashMsg . "\n";
    } else {
        echo "FAILED: Duplicate add to stock was not rejected! Flash: " . var_export($flashMsg, true) . "\n";
        exit(1);
    }
}
echo "PASSED: Backend successfully blocked duplicate add to stock for completed roll.\n";

echo "\n=== TEST 3: JAVASCRIPT LOGIC & HANDLERS VERIFICATION ===\n";

// Verify updateRollPreview and submitRollToStock have the checks
assertContains($htmlPending, 'function updateRollPreview()', "updateRollPreview missing");
assertContains($htmlPending, 'opt.dataset.inward === \'completed\'', "inward completed check missing in JS");
assertContains($htmlPending, 'roll_add_stock_btn', "roll_add_stock_btn reference missing in JS");
assertContains($htmlPending, 'roll_in_stock_badge', "roll_in_stock_badge reference missing in JS");
assertContains($htmlPending, 'This fabric roll has already been added to stock.', "Validation alert missing in submitRollToStock");

assertContains($htmlPending, 'function updateOtherPreview()', "updateOtherPreview missing");
assertContains($htmlPending, 'other_add_stock_btn', "other_add_stock_btn reference missing in JS");
assertContains($htmlPending, 'other_in_stock_badge', "other_in_stock_badge reference missing in JS");
assertContains($htmlPending, 'This item has already been added to stock.', "Validation alert missing in submitOtherToStock");

assertContains($htmlPending, 'function updateFgPreview()', "updateFgPreview missing");
assertContains($htmlPending, 'fg_add_stock_btn', "fg_add_stock_btn reference missing in JS");
assertContains($htmlPending, 'fg_in_stock_badge', "fg_in_stock_badge reference missing in JS");
assertContains($htmlPending, 'This finished goods item has already been added to stock.', "Validation alert missing in submitFgToStock");
$labelControllerSource = file_get_contents(__DIR__ . '/../app/controllers/LabelController.php');
$labelGenerationSource = file_get_contents(__DIR__ . '/../app/views/concern/label_generation.php');
assertContains($labelControllerSource, "targetTab === 'finished_goods' ? '/m/stocks'", "Finished Goods inward does not redirect to Live Stocks");
assertContains($labelControllerSource, 'must be mapped to a catalog variant before adding it to stock', "Unmapped finished goods can be marked complete without stock");
assertContains($labelGenerationSource, 'Map to catalog first', "Unmapped Finished Goods still show an Add to Stock action");
assertContains($labelGenerationSource, "curSectionTab === 'finished_goods' ? '/m/stocks'", "Finished Goods completion banner does not link to Live Stocks");

assertContains($htmlPending, 'function updateProdPreview()', "updateProdPreview missing");
assertContains($htmlPending, 'prod_add_stock_btn', "prod_add_stock_btn reference missing in JS");
assertContains($htmlPending, 'prod_in_stock_badge', "prod_in_stock_badge reference missing in JS");
assertContains($htmlPending, 'This stitched production item has already been added to stock.', "Validation alert missing in submitProdToStock");

echo "PASSED: All JavaScript handlers and dynamic visibility checks present and verified.\n";

// Restore original status
DB::query("UPDATE purchase_bill_items SET inward_status = ? WHERE id = ?", [$origStatus, $rollId]);
echo "PASSED: Restored test roll inward_status to {$origStatus}.\n";

echo "\n=== ALL TESTS PASSED SUCCESSFULLY! ===\n";

function assertContains($haystack, $needle, $msg) {
    if (strpos($haystack, $needle) === false) {
        echo "FAILED: $msg (Needle '$needle' not found in content)\n";
        exit(1);
    }
}
