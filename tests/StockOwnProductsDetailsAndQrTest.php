<?php
require_once __DIR__ . '/../app/core/Autoloader.php';

$checks = [];

// 1. Controller Code Inspection
$controllerSource = file_get_contents(__DIR__ . '/../app/controllers/StockController.php');

$checks['StockController generates QR payload in details()'] =
    strpos($controllerSource, "generateFinishedGoodsCode") !== false
    && strpos($controllerSource, "'qr'                 => \$qrData") !== false;

$checks['StockController includes production_journey in details()'] =
    strpos($controllerSource, "'production_journey' => \$productionJourney") !== false
    && strpos($controllerSource, "qc_inspections") !== false
    && strpos($controllerSource, "stitching_logs") !== false
    && strpos($controllerSource, "cutting_logs") !== false;

$checks['StockController resolves color via cascade fallback'] =
    strpos($controllerSource, "FROM qc_inspection_items WHERE variant_id = ?") !== false
    && strpos($controllerSource, "FROM stitching_items WHERE variant_id = ?") !== false
    && strpos($controllerSource, "FROM cutting_items WHERE") !== false
    && strpos($controllerSource, "preg_match('/\(([^)]+)\)/', \$variant['name'], \$m)") !== false;

$checks['StockController resolves HSN via cascade fallback'] =
    strpos($controllerSource, "catalog_hsn") !== false
    && strpos($controllerSource, "SELECT hsn_code FROM categories") !== false;

// 2. View Template Inspection
$viewSource = file_get_contents(__DIR__ . '/../app/views/concern/stocks.php');

$checks['View renders Product QR Code Card in details modal'] =
    strpos($viewSource, 'id="productQrCard"') !== false
    && strpos($viewSource, 'id="detailProductQrBox"') !== false
    && strpos($viewSource, 'id="detailQrTagCode"') !== false
    && strpos($viewSource, 'id="detailQrSkuCode"') !== false
    && strpos($viewSource, 'id="detailQrStyleCode"') !== false
    && strpos($viewSource, 'id="detailQrWholeCode"') !== false;

$checks['View includes QR action buttons (Print, Copy, Download)'] =
    strpos($viewSource, 'id="detailBtnPrintLabel"') !== false
    && strpos($viewSource, 'id="detailBtnCopyQr"') !== false
    && strpos($viewSource, 'id="detailBtnDownloadQr"') !== false;

$checks['View renders Production Journey Card in details modal'] =
    strpos($viewSource, 'id="productionJourneyCard"') !== false
    && strpos($viewSource, 'id="detailProdStyleCode"') !== false
    && strpos($viewSource, 'id="detailProdCuttingBatch"') !== false
    && strpos($viewSource, 'id="detailProdStitchingBatch"') !== false
    && strpos($viewSource, 'id="detailProdQcNo"') !== false;

$checks['View includes Quick QR modal and triggers'] =
    strpos($viewSource, 'id="quickStockQrModal"') !== false
    && strpos($viewSource, 'id="quickQrBox"') !== false
    && strpos($viewSource, 'class="btn btn-sm btn-outline-dark stock-qr-trigger"') !== false;

// 3. Render HTML Mock Test
$isSuperAdmin = false;
$selectedCid = 1;
$canEditStock = true;
$csrfToken = 'test-token-qr';
$stocks = [
    [
        'stock_source' => 'own',
        'product_code' => 'COT-B-MI-Free Size',
        'variant_name' => 'cotton (brown) - Free Size',
        'variant_attributes' => json_encode(['color' => 'brown', 'hsn' => '6109']),
        'size' => 'Free Size',
        'weight' => 0.4,
        'weight_unit' => 'pcs',
        'category_name' => 'General Raw Materials',
        'subcategory_name' => 'Cotton Fabric',
        'sub_subcategory_name' => 'Brown Cotton',
        'style_code' => 'COT-001',
        'whole_code' => 'COT-BRN-FS',
        'hierarchy_path' => 'General Raw Materials > Cotton Fabric > Brown Cotton',
        'location' => 'Main Store',
        'qty' => 70,
        'cost_price' => 150.00,
        'price' => 250.00,
        'mrp' => 500.00,
        'concern_id' => 1,
        'variant_id' => 201,
    ]
];
$totalUnits = 70;
$totalValuation = 10500;
$uniqueVariantCount = 1;
$stockSourceMetrics = [
    'own' => ['units' => 70, 'valuation' => 10500, 'variant_count' => 1],
    'purchase' => ['units' => 0, 'valuation' => 0, 'variant_count' => 0],
    'other' => ['units' => 0, 'valuation' => 0, 'variant_count' => 0],
];
$exportTitle = 'Stock Export';
$exportSubtitle = 'Test';
$exportUrl = '/m/stocks/export';
$prefix = 'stock_test';

ob_start();
include __DIR__ . '/../app/views/concern/stocks.php';
$html = ob_get_clean();

$checks['Rendered row has data-qr-payload and data-style-code'] =
    strpos($html, 'data-qr-payload="COT-BRN-FS"') !== false
    && strpos($html, 'data-style-code="COT-001"') !== false
    && strpos($html, 'data-color="brown"') !== false
    && strpos($html, 'data-hsn="6109"') !== false;

$checks['Rendered table contains interactive QR button in row'] =
    preg_match('/<button[^>]*class="[^"]*stock-qr-trigger[^"]*"[^>]*data-product-code="COT-B-MI-Free Size"/', $html) === 1;

$checks['Rendered modal contains Product QR Code section'] =
    strpos($html, 'Product QR Code & Identification') !== false;

$checks['Rendered modal contains Production & Manufacturing Audit Trail'] =
    strpos($html, 'Production & Manufacturing Audit Trail') !== false
    && strpos($html, '1. Product Style & Hierarchy') !== false
    && strpos($html, '2. Fabric Cutting Stage') !== false
    && strpos($html, '3. Stitching & Tailoring Stage') !== false
    && strpos($html, '4. Quality Control & Stock Inward') !== false;

echo "======================================================\n";
echo "  TEST SUITE: Stock Own Products View Details & QR    \n";
echo "======================================================\n\n";

$allPassed = true;
foreach ($checks as $name => $passed) {
    if ($passed) {
        echo "  [PASS] {$name}\n";
    } else {
        echo "  [FAIL] {$name}\n";
        $allPassed = false;
    }
}

echo "\n======================================================\n";
if ($allPassed) {
    echo "  RESULT: ALL " . count($checks) . " CHECKS PASSED!\n";
    echo "======================================================\n";
} else {
    echo "  RESULT: SOME CHECKS FAILED\n";
    echo "======================================================\n";
    exit(1);
}
