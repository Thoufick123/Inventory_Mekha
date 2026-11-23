<?php
require_once __DIR__ . '/../app/core/Autoloader.php';

$checks = [];

// 1. Check StockController SQL recognizes 'Production QC' as 'own'
$controllerSource = file_get_contents(__DIR__ . '/../app/controllers/StockController.php');
$checks['StockController maps Production QC to own stock source'] =
    strpos($controllerSource, "WHEN 'Production QC' THEN 'own'") !== false;

$checks['StockController includes Production QC in latest_inbound subquery'] =
    preg_match("/ref_module\s+IN\s*\([^)]*'Production QC'[^)]*\)/", $controllerSource) === 1;

$checks['StockController includes fallback for qc_inspection_items, stitching_items and batches'] =
    strpos($controllerSource, "FROM qc_inspection_items") !== false
    && strpos($controllerSource, "FROM stitching_items") !== false
    && strpos($controllerSource, "FROM batches") !== false;

// 2. Check stockAdjustmentModule handles Production QC
$reflection = new ReflectionClass(\App\Controllers\StockController::class);
$method = $reflection->getMethod('stockAdjustmentModule');
$method->setAccessible(true);
$controllerInstance = $reflection->newInstanceWithoutConstructor();

$adjustmentModule = $method->invoke($controllerInstance, 'Production QC');
$checks['stockAdjustmentModule converts Production QC to Production Stock Adjustment'] =
    ($adjustmentModule === 'Production Stock Adjustment');

// 3. Check DamageController includes Production QC in 'own' filter
$damageSource = file_get_contents(__DIR__ . '/../app/controllers/DamageController.php');
$checks['DamageController includes Production QC in own source filter'] =
    strpos($damageSource, "'Production QC'") !== false;

// 4. View Rendering Test: Ensure QC stocks with stock_source = 'own' render in "Own Products" tab
$isSuperAdmin = false;
$selectedCid = 1;
$canEditStock = true;
$csrfToken = 'test-token';
$stocks = [
    [
        'stock_source' => 'own',
        'sku_code' => 'COT-B-MI-Free Size',
        'variant_name' => 'cotton (brown) - Free Size',
        'variant_attributes' => json_encode(['color' => 'brown', 'size' => 'Free Size']),
        'size' => 'Free Size',
        'weight' => 0.4,
        'weight_unit' => 'pcs',
        'category_name' => 'General Raw Materials',
        'subcategory_name' => '-',
        'location' => 'Main Store',
        'qty' => 70,
        'cost_price' => 0,
        'price' => 250,
        'mrp' => 500,
        'concern_id' => 1,
        'variant_id' => 101,
    ],
    [
        'stock_source' => 'own',
        'sku_code' => 'COT-B-MI-XXL',
        'variant_name' => 'cotton (brown) - XXL',
        'variant_attributes' => json_encode(['color' => 'brown', 'size' => 'XXL']),
        'size' => 'XXL',
        'weight' => 0.4,
        'weight_unit' => 'pcs',
        'category_name' => 'General Raw Materials',
        'subcategory_name' => '-',
        'location' => 'Main Store',
        'qty' => 60,
        'cost_price' => 0,
        'price' => 250,
        'mrp' => 500,
        'concern_id' => 1,
        'variant_id' => 102,
    ],
];
$totalUnits = 130;
$totalValuation = 0;
$exportTitle = 'Stock Export';
$exportSubtitle = 'Test';
$exportUrl = '/m/stocks/export';
$prefix = 'stock_test';

ob_start();
include __DIR__ . '/../app/views/concern/stocks.php';
$html = ob_get_clean();

$checks['Own Products tab displays correct count badge'] =
    strpos($html, 'data-stock-source-tab="own"') !== false
    && preg_match('/data-stock-source-tab="own"[^>]*>.*?Own Products.*?<span[^>]*>2<\/span>/s', $html) === 1;

$checks['Own Products tab is selected by default when own products exist'] =
    preg_match('/class="nav-link[^"]*active[^"]*"[^>]*data-stock-source-tab="own"/', $html) === 1;

$checks['QC products render with data-stock-source="own" and are not marked d-none initially'] =
    preg_match('/<tr data-stock-source="own" class="">.*?COT-B-MI-Free Size/s', $html) === 1;

$checks['Other Sources tab shows 0 when only own products exist'] =
    preg_match('/data-stock-source-tab="other"[^>]*>.*?Other Sources.*?<span[^>]*>0<\/span>/s', $html) === 1;

$allPassed = true;
foreach ($checks as $name => $passed) {
    if ($passed) {
        echo "[PASS] {$name}\n";
    } else {
        echo "[FAIL] {$name}\n";
        $allPassed = false;
    }
}

if (!$allPassed) {
    exit(1);
}
echo "\nAll QC -> Own Products stock tests passed successfully.\n";
