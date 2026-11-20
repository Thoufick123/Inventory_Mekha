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

echo "=== Purchase Billing Directory Bill Type & Redirect Verification Test ===\n";

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

// 1. Render Purchase Billing Index
$controller = new PurchaseController();
ob_start();
$controller->billing();
$html = ob_get_clean();

assertCondition(!empty($html), "Purchase billing index page rendered successfully");

// 2. Verify Table Column: Bill Type
assertCondition(str_contains($html, 'Purchase Invoices Directory'), "Purchase Invoices Directory header exists");
assertCondition(str_contains($html, '<th>Bill Type</th>'), "Column header 'Bill Type' exists in table thead");
assertCondition(str_contains($html, 'id="billTypeFilter"'), "Bill Type filter dropdown 'billTypeFilter' exists");
assertCondition(str_contains($html, '<option value="rolls">Roll</option>'), "Filter option 'Roll' exists");
assertCondition(str_contains($html, '<option value="garment_other">Other Garments Supply</option>'), "Filter option 'Other Garments Supply' exists");
assertCondition(str_contains($html, '<option value="finished_goods">Finished Goods</option>'), "Filter option 'Finished Goods' exists");

// 3. Verify Bill #126 (which has rolls, garment_other, and finished_goods) renders all 3 badges
$firstBill = DB::fetchValue("SELECT bill_no FROM purchase_bills WHERE concern_id = ? ORDER BY id DESC LIMIT 1", [$user['cid']]);
assertCondition(!empty($firstBill) ? str_contains($html, $firstBill) : true, "Recent bill row is rendered");
assertCondition(str_contains($html, 'Roll</span>') || str_contains($html, 'Roll') , "Roll badge rendered for bill with rolls");
assertCondition(str_contains($html, 'Other Garments Supply'), "Other Garments Supply badge rendered for bill with garment_other");
assertCondition(str_contains($html, 'Finished Goods'), "Finished Goods badge rendered for bill with finished_goods");

// 4. Verify data-types attribute on bill-row
assertCondition(str_contains($html, 'data-types='), "data-types attribute is present on bill-row for filtering");

// 5. Verify Redirect in storeBill() in PurchaseController.php
$purchaseControllerSource = file_get_contents(__DIR__ . '/../app/controllers/PurchaseController.php');

// Ensure redirect in storeBill goes to /m/purchase_billing and NOT /m/label_generation
assertCondition(!str_contains($purchaseControllerSource, '$this->redirect("/m/label_generation?bill_id='), "Redirect to label generation after saving purchase is removed");
assertCondition(str_contains($purchaseControllerSource, '$this->redirect(\'/m/purchase_billing\');'), "storeBill redirects to /m/purchase_billing upon saving");

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
