<?php
require_once __DIR__ . '/../app/core/Autoloader.php';

use App\Services\QrService;

function renderFinishedGoodsLabel(array $labelData, string $labelType, array $multiLabels, int $qty, string $qrCodeText): string {
    $pageTitle = 'Finished Goods Product Code Test';
    $viewOnly = true;
    $jobId = 1;
    $variantCount = count($multiLabels);
    $batchNo = 'TEST-BATCH';
    ob_start();
    include __DIR__ . '/../app/views/concern/label_print_sheet.php';
    return ob_get_clean();
}

$checks = [
    'Numeric QR value parses as a stored variant ID' => QrService::parseVariantId('27') === 27,
    'Zero is rejected as a variant ID' => QrService::parseVariantId('0') === null,
    'Legacy descriptive tag is not parsed as a variant ID' => QrService::parseVariantId('SHI-R-0L') === null,
    'Hierarchy code uses compact keys and a two-digit variant ID' =>
        QrService::formatFinishedGoodsCode(1, 'AP', 'SH', 'RL') === 'GRMAPSHRL01',
    'Composite label code parses its variant ID' =>
        (QrService::parseFinishedGoodsCode('GRMAPSHRL01')['variant_id'] ?? null) === 1,
    'Screenshot product code parses variant ID 8' =>
        (QrService::parseFinishedGoodsCode('GRMWOFENS08')['variant_id'] ?? null) === 8,
    'Variant IDs longer than two digits are not truncated' =>
        QrService::formatFinishedGoodsCode(123, 'AP', 'SH', 'RL') === 'GRMAPSHRL123',
];

$singleLabel = [
    'concern_name' => 'GRM Garments',
    'ref_no' => 'PO-TEST',
    'header_title' => 'Shirt - Red - L',
    'label_1_name' => 'Product',
    'label_1_val' => 'Apparel',
    'label_2_name' => 'Color',
    'label_2_val' => 'Red',
    'label_3_name' => 'Design',
    'label_3_val' => 'L',
    'design' => 'L',
    'price' => 400,
    'price_mrp' => 400,
    'variant_id' => 2147483647,
    'main_category_code' => 'AP',
    'sub_category_code' => 'SH',
    'sub_sub_category_code' => 'RL',
    'tag_code' => 'GRMAPSHRL2147483647',
    'qr_payload' => 'GRMAPSHRL2147483647',
    'stock_tab' => 'finished_goods',
    'section_title' => 'PURCHASE FINISHED GOODS',
    'section_badge' => 'READY-MADE GARMENT',
    'item_title' => 'Shirt - Red - L',
];
$singleHtml = renderFinishedGoodsLabel($singleLabel, 'purchase_fg', [], 1, 'SHI-R-0L');
$checks['Finished-goods label displays and encodes the formatted product code'] =
    strpos($singleHtml, 'Product Code:') !== false
    && strpos($singleHtml, '>GRMAPSHRL2147483647</span>') !== false
    && strpos($singleHtml, 'data-code="GRMAPSHRL2147483647"') !== false
    && strpos($singleHtml, 'data-code="SHI-R-0L"') === false;

$secondLabel = $singleLabel;
$secondLabel['variant_id'] = 2147483646;
$secondLabel['tag_code'] = 'GRMAPSHRL2147483646';
$secondLabel['qr_payload'] = 'GRMAPSHRL2147483646';
$multiHtml = renderFinishedGoodsLabel(
    $singleLabel,
    'production',
    [$singleLabel, $secondLabel],
    2,
    'OLD-TAG'
);
$checks["Multi-variant labels encode each variant's own ID"] =
    strpos($multiHtml, 'data-code="GRMAPSHRL2147483647"') !== false
    && strpos($multiHtml, 'data-code="GRMAPSHRL2147483646"') !== false
    && strpos($multiHtml, '>GRMAPSHRL2147483647</span>') !== false
    && strpos($multiHtml, '>GRMAPSHRL2147483646</span>') !== false
    && strpos($multiHtml, 'data-code="OLD-TAG"') === false;

$failed = false;
foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . "\n";
    $failed = $failed || !$passed;
}
exit($failed ? 1 : 0);
