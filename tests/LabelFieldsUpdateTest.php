<?php
// tests/LabelFieldsUpdateTest.php
session_start();

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;
use App\Controllers\LabelController;

echo "======================================================\n";
echo "INVENTO: Label Fields Update Test (Qty/Weight Removed, Design & Price Required)\n";
echo "======================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertCheck(string $title, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$title}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$title}" . ($detail ? " - {$detail}" : "") . "\n";
        $failCount++;
    }
}

// -----------------------------------------------------------
// TEST GROUP 1: View Template Inspection
// -----------------------------------------------------------
echo "--- Group 1: Template Structure & Field Presence ---\n";

$printSheetFile = dirname(__DIR__) . '/app/views/concern/label_print_sheet.php';
$printContent = file_get_contents($printSheetFile);

$genHubFile = dirname(__DIR__) . '/app/views/concern/label_generation.php';
$hubContent = file_get_contents($genHubFile);
$labelControllerFile = dirname(__DIR__) . '/app/controllers/LabelController.php';
$labelControllerContent = file_get_contents($labelControllerFile);

// Check print sheet details stack
assertCheck("Print sheet contains 'Design:' label", strpos($printContent, '<span class="detail-label">Design:</span>') !== false);
assertCheck("Print sheet renders each dynamic variant attribute", strpos($printContent, '$variantAttributes as $attributeName => $attributeValue') !== false);
assertCheck("Print sheet contains 'Price:' label", strpos($printContent, '<span class="detail-label">Price:</span>') !== false);
assertCheck("Print sheet does NOT have 'Weight:' in details stack", strpos($printContent, '<span class="detail-label">Weight:</span>') === false);
assertCheck("Print sheet does NOT have 'Qty:' in details stack", strpos($printContent, '<span class="detail-label">Qty:</span>') === false);
assertCheck("Print sheet does NOT have 'Quantity:' in details stack", strpos($printContent, '<span class="detail-label">Quantity:</span>') === false);

// Verify dynamically stored variant values render as individual label rows.
$labelData = [
    'concern_name' => 'Test Concern',
    'header_title' => 'Test Finished Good',
    'label_1_name' => 'Product',
    'label_1_val' => 'Apparel',
    'label_2_name' => 'Color',
    'label_2_val' => 'RED',
    'design' => 'STANDARD',
    'price' => 100,
    'tag_code' => 'FG-TEST',
    'variant_attributes' => ['Size' => 'S', 'Memory' => '4GB'],
];
$labelType = 'purchase_fg';
$qty = 1;
$multiLabels = [];
$qrCodeText = 'FG-TEST';
ob_start();
include $printSheetFile;
$dynamicLabelHtml = ob_get_clean();
assertCheck("Finished Goods label prints Size independently", strpos($dynamicLabelHtml, '>Size:</span>') !== false && strpos($dynamicLabelHtml, '>S</span>') !== false);
assertCheck("Finished Goods label prints custom attributes independently", strpos($dynamicLabelHtml, '>Memory:</span>') !== false && strpos($dynamicLabelHtml, '>4GB</span>') !== false);
$multiLabels = [];
foreach ([['S', 'RED', 2], ['M', 'RED', 2], ['L', 'BLUE', 6]] as [$size, $color, $copies]) {
    for ($copy = 0; $copy < $copies; $copy++) {
        $multiLabels[] = array_merge($labelData, [
            'variant_size' => $size,
            'variant_attributes' => ['Size' => $size, 'Memory' => '4GB'],
            'label_2_val' => $color,
            'tag_code' => 'SKU-' . $size . '-' . $color,
            'qr_payload' => 'SKU-' . $size . '-' . $color,
        ]);
    }
}
$labelData = $multiLabels[0];
$qty = count($multiLabels);
$variantCount = 3;
$batchNo = 'BILL-1';
ob_start();
include $printSheetFile;
$multiVariantLabelHtml = ob_get_clean();
assertCheck("Finished Goods print sheet expands labels to mapped quantities", substr_count($multiVariantLabelHtml, 'class="label-sticker"') === 10);
assertCheck("Finished Goods print sheet contains every mapped size and color", strpos($multiVariantLabelHtml, '>M</span>') !== false && strpos($multiVariantLabelHtml, '>L</span>') !== false && strpos($multiVariantLabelHtml, 'BLUE') !== false);
$copyAllocator = new ReflectionMethod(LabelController::class, 'allocateLabelCopies');
$copyAllocator->setAccessible(true);
assertCheck("Finished Goods label copies follow mapped variant quantities", $copyAllocator->invoke(null, [2, 2, 6], 10) === [2, 2, 6]);
assertCheck("Custom label copy counts preserve mapped proportions", $copyAllocator->invoke(null, [2, 2, 6], 5) === [1, 1, 3]);
$attributeBuilder = new ReflectionMethod(LabelController::class, 'buildLabelVariantAttributes');
$attributeBuilder->setAccessible(true);
assertCheck("Finished Goods labels combine mapped size and custom attributes", $attributeBuilder->invoke(null, ['color' => 'red', 'custom_attributes' => ['memory' => '4GB']], 'S') === ['Size' => 'S', 'Memory' => '4GB']);

// Check Live Preview in Hub
assertCheck("Roll preview has id='roll_preview_design'", strpos($hubContent, 'id="roll_preview_design"') !== false);
assertCheck("Roll preview has id='roll_preview_price'", strpos($hubContent, 'id="roll_preview_price"') !== false);
assertCheck("Roll preview does NOT have id='roll_preview_weight'", strpos($hubContent, 'id="roll_preview_weight"') === false);

assertCheck("Other Garments preview has id='other_preview_design'", strpos($hubContent, 'id="other_preview_design"') !== false);
assertCheck("Other Garments preview has id='other_preview_price'", strpos($hubContent, 'id="other_preview_price"') !== false);
assertCheck("Other Garments preview does NOT have id='other_preview_qty'", strpos($hubContent, 'id="other_preview_qty"') === false);

assertCheck("FG preview has id='fg_preview_design'", strpos($hubContent, 'id="fg_preview_design"') !== false);
assertCheck("FG preview can render dynamic variant attributes", strpos($hubContent, 'id="fg_preview_variant_attributes"') !== false);
assertCheck("Finished Goods options carry saved variant attributes", strpos($hubContent, 'data-attributes=') !== false);
assertCheck("Finished Goods options carry the item's concern name", strpos($hubContent, 'data-concern="<?= htmlspecialchars($fg[\'item_concern_name\'] ?: $concernName') !== false);
assertCheck("Finished Goods preview updates to the selected item's concern", strpos($hubContent, 'concernPreview.textContent = opt.dataset.concern') !== false);
assertCheck("Finished Goods label loads concern name through the purchase item's concern ID", strpos($labelControllerContent, 'fg_concern.name AS item_concern_name') !== false && strpos($labelControllerContent, '$concernName = $itemConcernName;') !== false);
assertCheck("Finished Goods preview shows mapped variant quantities", strpos($hubContent, 'data-variant-summary=') !== false && strpos($hubContent, 'id="fg_preview_variant_summary"') !== false);
assertCheck("FG preview has id='fg_preview_price'", strpos($hubContent, 'id="fg_preview_price"') !== false);
assertCheck("FG preview does NOT have id='fg_preview_size_qty'", strpos($hubContent, 'id="fg_preview_size_qty"') === false);

assertCheck("Production preview has id='prod_preview_design'", strpos($hubContent, 'id="prod_preview_design"') !== false);
assertCheck("Production preview has id='prod_preview_price'", strpos($hubContent, 'id="prod_preview_price"') !== false);


assertCheck("Roll label quantity has decrement control", strpos($hubContent, "adjustCopies('roll_qty', -1)") !== false);
assertCheck("Roll label quantity has increment control", strpos($hubContent, "adjustCopies('roll_qty', 1)") !== false);
assertCheck("Roll label quantity starts at one instead of a fixed preset", strpos($hubContent, 'id="roll_qty" class="form-control text-center" min="1" max="500" value="1"') !== false);
assertCheck("Selecting a roll updates the label quantity", strpos($hubContent, 'onchange="updateRollPreview(); matchRollQty()"') !== false);
assertCheck("Matching labels uses purchase quantity before roll quantity", strpos($hubContent, "opt.dataset.qty || opt.dataset.rq || '1'") !== false);
assertCheck("Other label quantity has decrement control", strpos($hubContent, "adjustCopies('other_qty', -1)") !== false);
assertCheck("Other label quantity has increment control", strpos($hubContent, "adjustCopies('other_qty', 1)") !== false);
assertCheck("Label view does not contain old number preset controls", strpos($hubContent, "setCopies('roll_qty', 1)") === false);


// -----------------------------------------------------------
// TEST GROUP 2: Print Sheet Generation Execution
// -----------------------------------------------------------
echo "\n--- Group 2: Print Sheet Generation Output Execution ---\n";

Auth::login('grm.admin@invento.com', 'password123');
$cid = Auth::concernId();
$_SESSION['csrf_token'] = 'test_token_' . bin2hex(random_bytes(8));

// 1. Test Fabric Roll Item Print Sheet
$rollItem = DB::fetch("SELECT * FROM purchase_bill_items WHERE concern_id = ? AND section_type = 'rolls' ORDER BY id DESC LIMIT 1", [$cid]);

if ($rollItem) {
    $_POST = [
        'csrf_token' => $_SESSION['csrf_token'],
        'label_type' => 'purchase_rolls',
        'item_id'    => $rollItem['id'],
        'qty'        => 2,
    ];

    ob_start();
    $controller = new LabelController();
    $controller->generate();
    $output = ob_get_clean();

    assertCheck("Generated roll label renders 200 HTML", strlen($output) > 500);
    assertCheck("Roll label displays 'Design:'", strpos($output, '<span class="detail-label">Design:</span>') !== false);
    assertCheck("Roll label displays 'Price:'", strpos($output, '<span class="detail-label">Price:</span>') !== false);
    assertCheck("Roll label displays currency ₹", strpos($output, '₹') !== false);
    assertCheck("Roll label does NOT contain '<span class=\"detail-label\">Weight:</span>'", strpos($output, '<span class="detail-label">Weight:</span>') === false);
    assertCheck("Roll label does NOT contain '<span class=\"detail-label\">Qty:</span>'", strpos($output, '<span class="detail-label">Qty:</span>') === false);
} else {
    echo "  [SKIP] No roll item found for concern {$cid}\n";
}

// 2. Test Other Garment Trim Item Print Sheet
$trimItem = DB::fetch("SELECT * FROM purchase_bill_items WHERE concern_id = ? AND section_type = 'garment_other' ORDER BY id DESC LIMIT 1", [$cid]);

if ($trimItem) {
    $_POST = [
        'csrf_token' => $_SESSION['csrf_token'],
        'label_type' => 'purchase_other',
        'item_id'    => $trimItem['id'],
        'qty'        => 3,
    ];

    ob_start();
    $controller = new LabelController();
    $controller->generate();
    $output = ob_get_clean();

    assertCheck("Generated trim label renders HTML", strlen($output) > 500);
    assertCheck("Trim label displays 'Design:'", strpos($output, '<span class="detail-label">Design:</span>') !== false);
    assertCheck("Trim label displays 'Price:'", strpos($output, '<span class="detail-label">Price:</span>') !== false);
    assertCheck("Trim label does NOT display 'Qty: 50.00 spools' or '<span class=\"detail-label\">Qty:</span>'", strpos($output, '<span class="detail-label">Qty:</span>') === false);
    assertCheck("Trim label does NOT display '<span class=\"detail-label\">Weight:</span>'", strpos($output, '<span class="detail-label">Weight:</span>') === false);
} else {
    echo "  [SKIP] No trim item found for concern {$cid}\n";
}

// -----------------------------------------------------------
// TEST SUMMARY
// -----------------------------------------------------------
echo "\n======================================================\n";
echo "TEST RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "======================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
