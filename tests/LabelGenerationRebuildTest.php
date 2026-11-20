<?php
// tests/LabelGenerationRebuildTest.php - Comprehensive verification of 3-section Label Generation

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;
use App\Controllers\LabelController;
use App\Services\QrService;

echo "======================================================\n";
echo "INVENTO: Rebuilt 3-Section Label Generation Verification\n";
echo "======================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $title, bool $condition) {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$title}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$title}\n";
        $failCount++;
    }
}

// 1. Authentication
echo "TEST 1: Authentication & Tenant Setup\n";
$login = Auth::login('grm.admin@invento.com', 'password123');
assertTest("Concern 1 login succeeded", $login);
$cid = Auth::concernId();
assertTest("Concern ID is 1", $cid === 1);
$_SESSION['csrf_token'] = 'test-token-labels';

$ctrl = new LabelController();

// 2. Hub View Rendering & Data Extraction
echo "\nTEST 2: Label Generation Hub (3 Sections Data Retrieval)\n";
ob_start();
$ctrl->index();
$hubHtml = ob_get_clean();

assertTest("Rendered hub contains '1. Purchase Label'", str_contains($hubHtml, '1. Purchase Label'));
assertTest("Rendered hub contains '2. Purchase Finished Goods'", str_contains($hubHtml, '2. Purchase Finished Goods'));
assertTest("Rendered hub contains '3. Production Label'", str_contains($hubHtml, '3. Production Label'));
assertTest("Rendered hub does NOT contain '4. Return Label'", !str_contains($hubHtml, '4. Return Label'));
assertTest("Rendered hub contains Recent Label Generation Jobs table", str_contains($hubHtml, 'Recent Label Generation Jobs'));

// Separate Options in Purchase Label: Fabric Rolls vs Other Garments
assertTest("Purchase Label contains 'Fabric Rolls' option", str_contains($hubHtml, 'Fabric Rolls'));
assertTest("Purchase Label contains 'Other Garments Products' option", str_contains($hubHtml, 'Other Garments Products'));
assertTest("Purchase Label contains dedicated 'Purchase Fabric Rolls Directory' table", str_contains($hubHtml, 'Purchase Fabric Rolls Directory'));
assertTest("Purchase Label contains dedicated 'Purchase Other Garments & Trims Directory' table", str_contains($hubHtml, 'Purchase Other Garments & Trims Directory'));
assertTest("Purchase Label contains roll select 'roll_item_select'", str_contains($hubHtml, 'id="roll_item_select"'));
assertTest("Purchase Label contains other garment select 'other_item_select'", str_contains($hubHtml, 'id="other_item_select"'));

// 3. Test Section 1: Purchase Label Generation (Raw Material / Roll)
echo "\nTEST 3: Section 1 - Purchase Label Generation (Rolls & Trims)\n";
$rawItem = DB::fetch(
    "SELECT pbi.* FROM purchase_bill_items pbi 
     WHERE pbi.concern_id = ? AND pbi.section_type = 'rolls' 
     ORDER BY pbi.id DESC LIMIT 1",
    [$cid]
);
assertTest("Found purchase roll item in Concern 1", !empty($rawItem));

$_POST = [
    'csrf_token' => Auth::csrfToken(),
    'label_type' => 'purchase_raw',
    'item_id'    => (int)$rawItem['id'],
    'qty'        => 6
];

ob_start();
$ctrl->generate();
$rawPrintHtml = ob_get_clean();

assertTest("Generated Purchase print sheet contains 6 copies", substr_count($rawPrintHtml, 'class="label-sticker"') === 6);
assertTest("Print sheet for roll contains 'FABRIC ROLL LABEL'", str_contains($rawPrintHtml, 'FABRIC ROLL LABEL'));
assertTest("Print sheet contains simplified tag code (3-prod, 1-col, 2-des)", (bool)preg_match('/[A-Z0-9]{3}-[A-Z0-9]-[A-Z0-9]{2}/', $rawPrintHtml));
assertTest("Print sheet contains dynamic concern name", str_contains($rawPrintHtml, 'concern-name-text'));

$latestJob1 = DB::fetch("SELECT * FROM label_jobs WHERE concern_id = ? ORDER BY id DESC LIMIT 1", [$cid]);
assertTest("Audit job logged with label_type = 'purchase_raw'", ($latestJob1['label_type'] ?? '') === 'purchase_raw');
assertTest("Audit job copies = 6", (int)($latestJob1['qty'] ?? 0) === 6);

// Test Other Garments Trim Label Generation
$otherItem = DB::fetch(
    "SELECT pbi.* FROM purchase_bill_items pbi 
     WHERE pbi.concern_id = ? AND pbi.section_type = 'garment_other' 
     ORDER BY pbi.id DESC LIMIT 1",
    [$cid]
);
if ($otherItem) {
    $_POST = [
        'csrf_token' => Auth::csrfToken(),
        'label_type' => 'purchase_raw',
        'item_id'    => (int)$otherItem['id'],
        'qty'        => 4
    ];
    ob_start();
    $ctrl->generate();
    $otherPrintHtml = ob_get_clean();
    assertTest("Generated Other Garments print sheet contains 4 copies", substr_count($otherPrintHtml, 'class="label-sticker"') === 4);
    assertTest("Print sheet for other garments contains 'TRIMS' or 'PURCHASE TRIM'", str_contains($otherPrintHtml, 'TRIMS') || str_contains($otherPrintHtml, 'PURCHASE TRIM'));
}

// 4. Test Section 2: Purchase Finished Goods Label Generation (Catalog Mapped)
echo "\nTEST 4: Section 2 - Purchase Finished Goods Label Generation\n";
$fgItem = DB::fetch(
    "SELECT pbi.* FROM purchase_bill_items pbi 
     WHERE pbi.concern_id = ? AND pbi.section_type = 'finished_goods' 
     ORDER BY pbi.id DESC LIMIT 1",
    [$cid]
);
assertTest("Found purchase finished goods item in Concern 1", !empty($fgItem));

$_POST = [
    'csrf_token' => Auth::csrfToken(),
    'label_type' => 'purchase_fg',
    'item_id'    => (int)$fgItem['id'],
    'qty'        => 12
];

ob_start();
$ctrl->generate();
$fgPrintHtml = ob_get_clean();

assertTest("Generated FG print sheet contains 12 copies", substr_count($fgPrintHtml, 'class="label-sticker"') === 12);
assertTest("Print sheet contains 'PURCHASE FINISHED GOODS'", str_contains($fgPrintHtml, 'PURCHASE FINISHED GOODS'));
assertTest("Print sheet contains 'READY-MADE GARMENT'", str_contains($fgPrintHtml, 'READY-MADE GARMENT'));

$latestJob2 = DB::fetch("SELECT * FROM label_jobs WHERE concern_id = ? ORDER BY id DESC LIMIT 1", [$cid]);
assertTest("Audit job logged with label_type = 'purchase_fg'", ($latestJob2['label_type'] ?? '') === 'purchase_fg');
assertTest("Audit job copies = 12", (int)($latestJob2['qty'] ?? 0) === 12);

// 5. Test Section 3: Production Label Generation (After Stitching)
echo "\nTEST 5: Section 3 - Production Label Generation (After Stitching)\n";
$prodItem = DB::fetch(
    "SELECT sl.* FROM stitching_logs sl 
     WHERE sl.concern_id = ? 
     ORDER BY sl.id DESC LIMIT 1",
    [$cid]
);
assertTest("Found stitched production log in Concern 1", !empty($prodItem));

$_POST = [
    'csrf_token' => Auth::csrfToken(),
    'label_type' => 'production',
    'item_id'    => (int)$prodItem['id'],
    'qty'        => 15
];

ob_start();
$ctrl->generate();
$prodPrintHtml = ob_get_clean();

assertTest("Generated Production print sheet contains 15 copies", substr_count($prodPrintHtml, 'class="label-sticker"') === 15);
assertTest("Print sheet contains 'PRODUCTION LABEL'", str_contains($prodPrintHtml, 'PRODUCTION LABEL'));
assertTest("Print sheet contains 'IN-HOUSE STITCHED'", str_contains($prodPrintHtml, 'IN-HOUSE STITCHED'));

$latestJob3 = DB::fetch("SELECT * FROM label_jobs WHERE concern_id = ? ORDER BY id DESC LIMIT 1", [$cid]);
assertTest("Audit job logged with label_type = 'production'", ($latestJob3['label_type'] ?? '') === 'production');
assertTest("Audit job copies = 15", (int)($latestJob3['qty'] ?? 0) === 15);

// 7. Test Catalog Variant Mapping AJAX
echo "\nTEST 7: Catalog Variant Mapping AJAX Handler\n";
$_POST = [
    'csrf_token' => Auth::csrfToken(),
    'item_id'    => (int)$fgItem['id'],
    'variant_id' => 3 // Map to variant 3
];

ob_start();
$ctrl->mapVariant();
$mapJsonStr = ob_get_clean();
$mapResult = json_decode($mapJsonStr, true);

assertTest("Map variant returned success = true", ($mapResult['success'] ?? false) === true);
assertTest("Map variant returned valid SKU code", !empty($mapResult['sku_code']));
$updatedPbi = DB::fetch("SELECT variant_id FROM purchase_bill_items WHERE id = ?", [$fgItem['id']]);
assertTest("Purchase bill item updated with variant_id = 3", (int)$updatedPbi['variant_id'] === 3);

// Clean up test audit jobs
DB::query("DELETE FROM label_jobs WHERE id IN (?, ?, ?)", [$latestJob1['id'], $latestJob2['id'], $latestJob3['id']]);

echo "\n======================================================\n";
echo "SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "======================================================\n";

if ($failCount === 0) {
    echo ">>> REBUILT 4-SECTION LABEL GENERATION FULLY VERIFIED! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
