<?php
// tests/AllModulesExportTest.php - Comprehensive verification of export endpoints across all modules

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;

$passed = 0;
$failed = 0;

function assertExport(string $name, callable $fn) {
    global $passed, $failed;
    try {
        $fn();
        echo "  [PASS] {$name}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  [FAIL] {$name}: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "======================================================\n";
echo "ALL MODULES EXPORT COMPREHENSIVE VERIFICATION SUITE\n";
echo "======================================================\n\n";

// Authenticate as Super Admin
$authSuccess = Auth::login('admin@invento.com', 'password123');
if (!$authSuccess) {
    die("Authentication failed in test setup.\n");
}
echo "Super Admin Authenticated Successfully.\n\n";

$endpoints = [
    'Stocks' => ['App\Controllers\StockController', 'exportStocks'],
    'Stock Ledger' => ['App\Controllers\StockController', 'exportLedger'],
    'Inwards' => ['App\Controllers\InwardController', 'exportInward'],
    'Purchase Billing' => ['App\Controllers\PurchaseController', 'exportBills'],
    'Purchase Damage' => ['App\Controllers\PurchaseController', 'exportDamage'],
    'Concern Damage' => ['App\Controllers\DamageController', 'exportConcernDamage'],
    'Catalog Program' => ['App\Controllers\ProductionController', 'exportCatalogProgram'],
    'Cutting Wastage' => ['App\Controllers\ProductionController', 'exportCutting'],
    'Stitching Wastage' => ['App\Controllers\ProductionController', 'exportStitching'],
    'QC Stocks' => ['App\Controllers\ProductionController', 'exportQc'],
    'Label Generation' => ['App\Controllers\LabelController', 'exportJobs'],
    'Customer Requests' => ['App\Controllers\SalesController', 'exportRequests'],
    'Batches' => ['App\Controllers\SalesController', 'exportBatches'],
    'Sales POS Billing' => ['App\Controllers\SalesController', 'exportBilling'],
    'Outwards' => ['App\Controllers\SalesController', 'exportOutward'],
    'Dispatches' => ['App\Controllers\SalesController', 'exportDispatch'],
    'Returns & Exchanges' => ['App\Controllers\SalesController', 'exportReturnExchange'],
    'Variants' => ['App\Controllers\VariantController', 'exportVariants'],
    'Concerns' => ['App\Controllers\AdminController', 'exportConcerns'],
    'Assets' => ['App\Controllers\AdminController', 'exportAssets'],
    'Staff' => ['App\Controllers\StaffController', 'export'],
];

echo "TEST: Execution of All Export Controllers in Excel, PDF & CSV Formats\n";
foreach ($endpoints as $moduleName => [$ctrlClass, $methodName]) {
    // 1. Test Excel
    assertExport("{$moduleName} -> Excel (.xls)", function() use ($ctrlClass, $methodName) {
        $_GET = ['format' => 'excel', 'period' => 'all'];
        ob_start();
        $ctrl = new $ctrlClass();
        $ctrl->$methodName();
        $out = ob_get_clean();
        if (empty($out) || !str_contains($out, 'xmlns:x="urn:schemas-microsoft-com:office:excel"')) {
            throw new Exception("Invalid Excel output. Length: " . strlen($out));
        }
    });

    // 2. Test PDF
    assertExport("{$moduleName} -> PDF (.pdf view)", function() use ($ctrlClass, $methodName) {
        $_GET = ['format' => 'pdf', 'period' => 'this_month'];
        ob_start();
        $ctrl = new $ctrlClass();
        $ctrl->$methodName();
        $out = ob_get_clean();
        if (empty($out) || !str_contains($out, '<!DOCTYPE html>')) {
            throw new Exception("Invalid PDF view output. Length: " . strlen($out));
        }
    });

    // 3. Test CSV
    assertExport("{$moduleName} -> CSV (.csv)", function() use ($ctrlClass, $methodName) {
        $_GET = ['format' => 'csv', 'period' => 'single_day', 'single_date' => date('Y-m-d')];
        ob_start();
        $ctrl = new $ctrlClass();
        $ctrl->$methodName();
        $out = ob_get_clean();
        if (empty($out)) {
            throw new Exception("Empty CSV output");
        }
    });
}

echo "\n======================================================\n";
echo "SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "======================================================\n";

if ($failed > 0) exit(1);
