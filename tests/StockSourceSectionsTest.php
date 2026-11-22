<?php
require_once __DIR__ . '/../app/core/Autoloader.php';

$isSuperAdmin = false;
$selectedCid = 1;
$canEditStock = true;
$csrfToken = 'stock-view-test-token';
$stocks = [
    [
        'stock_source' => 'own',
        'sku_code' => 'OWN-S',
        'variant_name' => 'Shirt - Red - S',
        'variant_attributes' => json_encode(['color' => 'red', 'custom_attributes' => ['memory' => '4GB']]),
        'size' => 'S',
        'weight' => 0.2,
        'weight_unit' => 'kg',
        'category_name' => 'Womens',
        'subcategory_name' => 'Feedfin',
        'location' => 'Main Store',
        'qty' => 4,
        'cost_price' => 250,
        'price' => 350,
        'mrp' => 400,
        'concern_id' => 1,
        'variant_id' => 11,
    ],
    [
        'stock_source' => 'purchase',
        'sku_code' => 'BUY-M',
        'variant_name' => 'Shirt - Blue - M',
        'variant_attributes' => json_encode(['color' => 'blue']),
        'size' => 'M',
        'weight' => 0.25,
        'weight_unit' => 'kg',
        'category_name' => 'Womens',
        'subcategory_name' => 'Feedfin',
        'location' => 'Main Store',
        'qty' => 2,
        'cost_price' => 260,
        'price' => 360,
        'mrp' => 420,
        'concern_id' => 1,
        'variant_id' => 12,
        'purchase_item_id' => 31,
    ],
    [
        'stock_source' => 'other',
        'sku_code' => 'RET-L',
        'variant_name' => 'Shirt - Red - L',
        'variant_attributes' => json_encode(['color' => 'red']),
        'size' => 'L',
        'weight' => 0.3,
        'weight_unit' => 'kg',
        'category_name' => 'Womens',
        'subcategory_name' => 'Feedfin',
        'location' => 'Main Store:DAMAGED',
        'qty' => 1,
        'cost_price' => 0,
        'price' => 350,
        'mrp' => 400,
        'concern_id' => 1,
        'variant_id' => 13,
    ],
];
$totalUnits = 7;
$totalValuation = 1520;
$exportTitle = 'Stock Export';
$exportSubtitle = 'Test';
$exportUrl = '/m/stocks/export';
$prefix = 'stock_test';

ob_start();
include __DIR__ . '/../app/views/concern/stocks.php';
$html = ob_get_clean();

$ownPosition = strpos($html, 'Own Products');
$purchasePosition = strpos($html, 'Purchase Products');
preg_match('/<tr data-stock-source="purchase"[^>]*>.*?<\/tr>/s', $html, $purchaseRowMatch);
$purchaseRowHtml = $purchaseRowMatch[0] ?? '';
$otherPosition = strpos($html, 'Other Sources');
$checks = [
    'Own and purchase stocks render as separate ordered sections' =>
        $ownPosition !== false && $purchasePosition !== false && $ownPosition < $purchasePosition,
    'Own, purchase, and other source sections are presented as one horizontal tab row' =>
        strpos($html, 'data-stock-source-tab="own"') !== false
        && strpos($html, 'data-stock-source-tab="purchase"') !== false
        && strpos($html, 'data-stock-source-tab="other"') !== false,
    'Positive stock without a known source remains visible in Other Sources' =>
        $otherPosition !== false
        && strpos($html, 'data-stock-source="other"') !== false
        && strpos($html, 'RET-L') !== false,
    'Storage Location column is removed' => strpos($html, '<th>Storage Location</th>') === false,
    'Quantity and net value use the requested column labels' =>
        strpos($html, '<th>Qty</th>') !== false
        && strpos($html, '<th>Net Value</th>') !== false
        && strpos($html, 'Live On-Hand Qty') === false
        && strpos($html, 'Line Valuation') === false,
    'Finished-goods stock quantity is shown as a whole number in pieces' =>
        strpos($html, '4 pcs') !== false && strpos($html, '4.00 pcs') === false,
    'Mapped label attributes are visible in stock details' =>
        strpos($html, 'Color: red') !== false && strpos($html, 'Memory: 4GB') !== false,
    'Stock display distinguishes selling rate from label MRP' =>
        strpos($html, '₹350.00') !== false && strpos($html, 'MRP ₹400.00') !== false,
    'Stock display and edit modal separate cost, selling, and MRP values' =>
        strpos($html, 'Cost ₹250.00') !== false
        && strpos($html, 'Selling ₹350.00') !== false
        && strpos($html, 'id="stockEditCostPrice"') !== false
        && strpos($html, 'id="stockEditPrice"') !== false
        && strpos($html, 'id="stockEditMrp"') !== false
        && strpos($html, 'Cost Price &lt; Selling Price &lt; MRP') !== false,
    'Stock valuation uses cost price' =>
        strpos($html, 'Total Cost Valuation') !== false
        && strpos($html, '₹1,520.00') !== false,
    'Stock actions include quantity edit and ledger-preserving removal' =>
        strpos($html, 'data-stock-edit') !== false
        && strpos($html, '/m/stocks/update-details') !== false
        && strpos($html, '/m/stocks/delete-line') !== false
        && strpos($html, 'stock ledger history will be preserved') !== false,
    'Purchase stock actions open the exact label and provide quantity CRUD' =>
        strpos($purchaseRowHtml, 'View Label') !== false
        && strpos($purchaseRowHtml, 'view-label?item_id=31') !== false
        && strpos($purchaseRowHtml, 'variant_id=12') !== false
        && strpos($purchaseRowHtml, 'Print Labels') === false
        && strpos($purchaseRowHtml, 'View QR') === false
        && strpos($purchaseRowHtml, 'data-stock-edit') !== false
        && strpos($purchaseRowHtml, '/m/stocks/delete-line') !== false
        && strpos($purchaseRowHtml, 'Remove') !== false,
];

$controllerSource = file_get_contents(__DIR__ . '/../app/controllers/StockController.php');
$checks['Production and purchase inward ledger entries determine stock source'] =
    strpos($controllerSource, "WHEN 'Production Inward' THEN 'own'") !== false
    && strpos($controllerSource, "WHEN 'Production Stock Adjustment' THEN 'own'") !== false
    && strpos($controllerSource, "WHEN 'Purchase Inward' THEN 'purchase'") !== false
    && strpos($controllerSource, "WHEN 'Purchase Inward Item' THEN 'purchase'") !== false
    && strpos($controllerSource, "WHEN 'Purchase Stock Adjustment' THEN 'purchase'") !== false
    && strpos($controllerSource, "WHERE s.qty > 0") !== false
    && strpos($controllerSource, 'COALESCE(latest_inbound.ref_module, latest_variant_source.ref_module)') !== false
    && strpos($controllerSource, 'latest_variant_source') !== false
    && strpos($controllerSource, 'AND latest_inbound.ref_module IN (') === false
    && strpos($controllerSource, "pbi.id = latest_purchase.ref_id") !== false
    && strpos($controllerSource, "WHERE qty_in > 0 AND ref_module IN ('Purchase Inward', 'Purchase Inward Item')") !== false
    && strpos($controllerSource, "'Purchase Inward Item'") !== false;

$canEditStock = false;
ob_start();
include __DIR__ . '/../app/views/concern/stocks.php';
$readOnlyHtml = ob_get_clean();
$checks['Quantity CRUD controls are hidden without stock edit permission'] =
    strpos($readOnlyHtml, 'aria-label="Edit details for') === false
    && strpos($readOnlyHtml, 'delete-line') === false;
$checks['Purchase label preview remains available in read-only mode'] =
    strpos($readOnlyHtml, 'View Label') !== false;

$pageTitle = 'Finished Goods Label';
$viewOnly = true;
$labelType = 'purchase_fg';
$labelData = [
    'concern_name' => 'Example Concern',
    'ref_no' => 'BILL-1',
    'header_title' => 'Shirt - Blue - M',
    'label_1_name' => 'Product',
    'label_1_val' => 'Shirt',
    'label_2_name' => 'Color',
    'label_2_val' => 'Blue',
    'variant_size' => 'M',
    'variant_attributes' => ['Fabric' => 'Cotton'],
    'design' => 'STANDARD',
    'price' => 420,
    'tag_code' => 'VAR-M-1',
    'qr_payload' => 'VAR-M-1',
    'section_title' => 'PURCHASE FINISHED GOODS',
    'section_badge' => 'READY-MADE GARMENT',
];
$multiLabels = [[
    'concern_name' => 'Example Concern',
    'ref_no' => 'BILL-1',
    'header_title' => 'Shirt - Blue - M',
    'label_1_name' => 'Product',
    'label_1_val' => 'Shirt',
    'label_2_name' => 'Color',
    'label_2_val' => 'Blue',
    'variant_size' => 'M',
    'variant_attributes' => ['Fabric' => 'Cotton'],
    'design' => 'STANDARD',
    'price' => 420,
    'tag_code' => 'VAR-M-1',
    'qr_payload' => 'VAR-M-1',
]];
$qty = 1;
$jobId = 0;
$variantCount = 1;
$batchNo = 'BILL-1';
$qrCodeText = 'VAR-M-1';
ob_start();
include __DIR__ . '/../app/views/concern/label_print_sheet.php';
$labelPreviewHtml = ob_get_clean();
$checks['View-only label preview keeps one exact sticker without print-job controls'] =
    substr_count($labelPreviewHtml, 'class="label-sticker"') === 1
    && strpos($labelPreviewHtml, 'VAR-M-1') !== false
    && strpos($labelPreviewHtml, 'Cotton') !== false
    && strpos($labelPreviewHtml, 'Print Labels Now') === false
    && strpos($labelPreviewHtml, 'Job #') === false;

$failed = false;
foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . "\n";
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
