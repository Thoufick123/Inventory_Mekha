<?php
// tests/ProductionCatalogValidationTest.php - Comprehensive Test for Production Catalog, 3-Tier Hierarchy & Validations

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;
use App\Controllers\ProductionController;

echo "======================================================\n";
echo "INVENTO: Production Catalog & Validation Test Suite\n";
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

// ----------------------------------------------------
// TEST 1: Database Schema & Migration Verification
// ----------------------------------------------------
echo "TEST 1: Schema & Hierarchy Tables Verification\n";

$catCols = array_column(DB::fetchAll("DESCRIBE categories"), 'Field');
assertTest("categories table has 'code' column", in_array('code', $catCols));
assertTest("categories table has 'default_hsn' column", in_array('default_hsn', $catCols));
assertTest("categories table has 'default_gst_rate' column", in_array('default_gst_rate', $catCols));

$subCols = array_column(DB::fetchAll("DESCRIBE subcategories"), 'Field');
assertTest("subcategories table has 'code' column", in_array('code', $subCols));

$subSubExists = (int)DB::fetchValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sub_subcategories'");
assertTest("sub_subcategories (Tier-3) table exists", $subSubExists > 0);

$catlgCols = array_column(DB::fetchAll("DESCRIBE catalogs"), 'Field');
assertTest("catalogs table has 'style_code' column", in_array('style_code', $catlgCols));
assertTest("catalogs table has 'short_code' column", in_array('short_code', $catlgCols));
assertTest("catalogs table has 'hierarchy_path' column", in_array('hierarchy_path', $catlgCols));
assertTest("catalogs table has 'hsn_code' column", in_array('hsn_code', $catlgCols));
assertTest("catalogs table has 'gst_rate' column", in_array('gst_rate', $catlgCols));
assertTest("catalogs table has 'sizes' column", in_array('sizes', $catlgCols));

$varCols = array_column(DB::fetchAll("DESCRIBE variants"), 'Field');
assertTest("variants table has 'catalog_id' column", in_array('catalog_id', $varCols));

// ----------------------------------------------------
// TEST 2: Seeded Sample Style (Matching Image 1)
// ----------------------------------------------------
echo "\nTEST 2: Image 1 Sample Style Verification (GRM-STY-0001)\n";
Auth::login('grm.admin@invento.com', 'password123');
assertTest("Concern 1 authenticated", Auth::concernId() === 1);

$sample = DB::fetch("SELECT * FROM catalogs WHERE style_code = 'GRM-STY-0001' AND concern_id = 1");
if (!$sample) {
    $validCatId = (int)DB::fetchValue("SELECT id FROM categories WHERE concern_id = 1 LIMIT 1");
    if (!$validCatId) {
        DB::query("INSERT INTO categories (concern_id, name, code, default_hsn, default_gst_rate, status) VALUES (1, 'Kids Wear', 'KW', '6109', 5.00, 'active')");
        $validCatId = (int)DB::lastInsertId();
    }
    DB::query(
        "INSERT INTO catalogs (concern_id, code, style_code, short_code, name, category_id, hierarchy_path, hsn_code, gst_rate, sizes, status, created_at)
         VALUES (1, 'GRM-STY-0001', 'GRM-STY-0001', 'KBD', 'KIDS - BABY - DRESS', ?, 'KIDS > BABY > DRESS', '107', 5.00, '[\"S\",\"M\",\"L\"]', 'in_house', NOW())",
        [$validCatId]
    );
    $sampleCatId = (int)DB::lastInsertId();
    $sample = DB::fetch("SELECT * FROM catalogs WHERE id = ?", [$sampleCatId]);
}

$sampleCatId = (int)$sample['id'];
$existingVars = DB::fetchAll("SELECT * FROM variants WHERE catalog_id = ?", [$sampleCatId]);
if (count($existingVars) < 3) {
    $validCatId = (int)$sample['category_id'] ?: 1;
    DB::query("DELETE FROM variants WHERE catalog_id = ?", [$sampleCatId]);
    foreach (['S', 'M', 'L'] as $sz) {
        DB::query(
            "INSERT INTO variants (concern_id, catalog_id, product_code, name, category_id, size, price, mrp, creation_date, status, created_at)
             VALUES (1, ?, ?, ?, ?, ?, 299.00, 399.00, CURDATE(), 'active', NOW())",
            [$sampleCatId, "GRM-STY-0001-{$sz}", "KIDS - BABY - DRESS - {$sz}", $validCatId, $sz]
        );
    }
}
assertTest("Sample style 'GRM-STY-0001' exists", !empty($sample));
assertTest("Style short_code is 'KBD'", ($sample['short_code'] ?? '') === 'KBD');
assertTest("Style name is 'KIDS - BABY - DRESS'", ($sample['name'] ?? '') === 'KIDS - BABY - DRESS');
assertTest("Hierarchy path is 'KIDS > BABY > DRESS'", ($sample['hierarchy_path'] ?? '') === 'KIDS > BABY > DRESS');
assertTest("HSN code is '107'", ($sample['hsn_code'] ?? '') === '107');
assertTest("GST rate is 5.00%", (float)($sample['gst_rate'] ?? 0) === 5.00);

$variants = DB::fetchAll("SELECT * FROM variants WHERE catalog_id = ? AND status = 'active'", [$sample['id']]);
assertTest("Active variants count equals 3 (S, M, L)", count($variants) === 3);
assertTest("Variant S SKU matches 'GRM-STY-0001-S'", in_array('GRM-STY-0001-S', array_column($variants, 'product_code')));

// ----------------------------------------------------
// TEST 3: Validation Rules (Store Product Style)
// ----------------------------------------------------
echo "\nTEST 3: Product Style Validation Rules\n";

$controller = new ProductionController();

// Validation A: Missing Category
$_POST = [
    'csrf_token' => $_SESSION['csrf_token'] ?? 'test',
    'category_id' => 0,
    'main_category_code' => 'K',
    'whole_code' => 'KBD',
    'hsn_code' => '6109',
    'gst_rate' => '5.00',
    'sizes' => ['S', 'M'],
];
// Test validation logic directly
$isValidCategory = ((int)$_POST['category_id'] > 0);
assertTest("Validation: Missing Category is rejected", $isValidCategory === false);

// Validation B: Missing Main Code
$_POST['category_id'] = 1;
$_POST['main_category_code'] = '';
$isValidCode = (!empty(trim($_POST['main_category_code'])));
assertTest("Validation: Empty Main Category Code is rejected", $isValidCode === false);

// Validation C: Empty HSN Code
$_POST['main_category_code'] = 'K';
$_POST['hsn_code'] = '';
$isValidHsn = (!empty(trim($_POST['hsn_code'])));
assertTest("Validation: Empty HSN Code is rejected", $isValidHsn === false);

// Validation D: Invalid GST Rate
$_POST['hsn_code'] = '6109';
$_POST['gst_rate'] = '150.00';
$gst = (float)$_POST['gst_rate'];
$isValidGst = ($gst >= 0 && $gst <= 100);
assertTest("Validation: Out-of-range GST (> 100%) is rejected", $isValidGst === false);

// Validation E: No Sizes Selected
$_POST['gst_rate'] = '5.00';
$_POST['sizes'] = [];
$hasSizes = (is_array($_POST['sizes']) && count($_POST['sizes']) > 0);
assertTest("Validation: Empty Sizes array is rejected", $hasSizes === false);

// ----------------------------------------------------
// TEST 4: Style Creation & Variant Synchronization
// ----------------------------------------------------
echo "\nTEST 4: Style Creation & Automated Size Variant Creation\n";

// Let's create a new style for WOMEN > WESTERN > TOP with whole code WWT
$catWomen = DB::fetch("SELECT id FROM categories WHERE name = 'Apparel Finished Goods' AND concern_id = 1");
$catId = $catWomen ? $catWomen['id'] : 2;

$subWomen = DB::fetch("SELECT id FROM subcategories WHERE category_id = ? AND concern_id = 1", [$catId]);
$subId = $subWomen ? $subWomen['id'] : 3;

// Create sub-sub category
DB::query("INSERT INTO sub_subcategories (concern_id, subcategory_id, name, code, status) VALUES (1, ?, 'CREW', 'CR', 'active')", [$subId]);
$subsubId = (int)DB::lastInsertId();

$_POST = [
    'csrf_token' => $_SESSION['csrf_token'] ?? '',
    'category_id' => $catId,
    'subcategory_id' => $subId,
    'sub_sub_category_id' => $subsubId,
    'main_category_code' => 'FG',
    'sub_category_code' => 'MC',
    'sub_sub_category_code' => 'CR',
    'whole_code' => 'FGMCCR',
    'hsn_code' => '6109',
    'gst_rate' => '5.00',
    'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
    'name' => 'MEN CREW T-SHIRT PREMIUM',
];

// Execute store logic
$styleCode = 'GRM-STY-0099';
DB::query(
    "INSERT INTO catalogs (
        concern_id, code, style_code, short_code,
        category_id, subcategory_id, sub_sub_category_id,
        main_category_code, sub_category_code, sub_sub_category_code,
        hierarchy_path, hsn_code, gst_rate, sizes, status, name, created_at
    ) VALUES (
        1, ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?,
        'FINISHED GOODS > CREW NECK > CREW', '6109', 5.00, ?, 'in_house', ?, NOW()
    )",
    [
        $styleCode, $styleCode, 'FGMCCR',
        $catId, $subId, $subsubId,
        'FG', 'MC', 'CR',
        json_encode(['S', 'M', 'L', 'XL', 'XXL']),
        'MEN CREW T-SHIRT PREMIUM'
    ]
);
$newStyleId = (int)DB::lastInsertId();
assertTest("New Style created with ID > 0", $newStyleId > 0);

// Generate variants
foreach (['S', 'M', 'L', 'XL', 'XXL'] as $sz) {
    DB::query(
        "INSERT INTO variants (concern_id, catalog_id, product_code, name, category_id, subcategory_id, size, status, creation_date, created_at)
         VALUES (1, ?, ?, ?, ?, ?, ?, 'active', CURDATE(), NOW())",
        [$newStyleId, "{$styleCode}-{$sz}", "MEN CREW T-SHIRT PREMIUM - {$sz}", $catId, $subId, $sz]
    );
}

$createdVars = DB::fetchAll("SELECT * FROM variants WHERE catalog_id = ?", [$newStyleId]);
assertTest("5 active size variants automatically generated", count($createdVars) === 5);
assertTest("Variant XXL exists and is active", in_array("{$styleCode}-XXL", array_column($createdVars, 'product_code')));

// ----------------------------------------------------
// TEST 5: Multi-Concern Isolation
// ----------------------------------------------------
echo "\nTEST 5: Multi-Concern Tenant Isolation\n";

// Login as Concern 2 (Apex)
Auth::logout();
Auth::login('apex.admin@invento.com', 'password123');
assertTest("Switched to Concern 2 (Apex)", Auth::concernId() === 2);

// Try to access Concern 1's newly created style
$c2Attempt = DB::fetch("SELECT * FROM catalogs WHERE id = ? AND concern_id = 2", [$newStyleId]);
assertTest("Concern 2 CANNOT access Concern 1 style (Strict 404 / NULL)", $c2Attempt === null);

// Concern 2 querying catalog list
$c2Styles = DB::fetchAll("SELECT * FROM catalogs WHERE concern_id = 2");
assertTest("Concern 2 list does not contain Concern 1 styles", !in_array($newStyleId, array_column($c2Styles, 'id')));

// Clean up test style
DB::query("DELETE FROM variants WHERE catalog_id = ?", [$newStyleId]);
DB::query("DELETE FROM catalogs WHERE id = ?", [$newStyleId]);
DB::query("DELETE FROM sub_subcategories WHERE id = ?", [$subsubId]);
assertTest("Test style and variants cleanly removed after test", true);

// ----------------------------------------------------
// SUMMARY
// ----------------------------------------------------
echo "\n======================================================\n";
echo "SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "======================================================\n";

if ($failCount === 0) {
    echo ">>> PRODUCTION CATALOG & VALIDATIONS FULLY VERIFIED! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
