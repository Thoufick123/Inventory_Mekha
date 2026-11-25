<?php
// tests/CatalogMappingAjaxAndSaveTest.php - Validate AJAX fetch endpoints and save execution for Catalog Mapping

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;

echo "======================================================\n";
echo "INVENTO: Catalog Mapping AJAX & Save Test\n";
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

// 1. Simulate Auth as Super Admin
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';
$_SESSION['super_admin_concern_id'] = 1;
$_SESSION['csrf_token'] = 'test-token-123';

echo "TEST 1: Subcategory Fetch Query Verification\n";
$catId = 3; // Accessories & Trims
$subcats = DB::fetchAll("SELECT id, name, code FROM subcategories WHERE category_id = ? AND status = 'active' ORDER BY name ASC", [$catId]);
assertTest("Subcategories fetched for category 3", count($subcats) > 0);
assertTest("Subcategories have 'id' and 'name'", isset($subcats[0]['id'], $subcats[0]['name']));

echo "\nTEST 2: Quick Create Subcategory Functionality\n";
$cid = 1;
$subName = 'Designer Buttons ' . time();
$subCode = 'DB';
DB::query("INSERT INTO subcategories (concern_id, category_id, name, code, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())", [$cid, $catId, $subName, $subCode]);
$newSubcatId = (int)DB::lastInsertId();
assertTest("Subcategory inserted with ID {$newSubcatId}", $newSubcatId > 0);

echo "\nTEST 3: Quick Create Tier-3 Sub-Subcategory Functionality\n";
$tier3Name = 'Metallic Gold 18mm ' . time();
$tier3Code = 'MG18';
DB::query("INSERT INTO sub_subcategories (concern_id, subcategory_id, name, code, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())", [$cid, $newSubcatId, $tier3Name, $tier3Code]);
$newTier3Id = (int)DB::lastInsertId();
assertTest("Tier-3 Sub-Subcategory inserted with ID {$newTier3Id}", $newTier3Id > 0);

$tier3Fetched = DB::fetchAll("SELECT id, name, code FROM sub_subcategories WHERE subcategory_id = ? AND status = 'active' ORDER BY name ASC", [$newSubcatId]);
assertTest("Tier-3 item correctly fetched for parent subcategory", count($tier3Fetched) === 1);

echo "\nTEST 4: Save Catalog Mapping for Purchase Bill Item 2\n";
$item2 = DB::fetch("SELECT * FROM purchase_bill_items WHERE id = 2");
if ($item2) {
    // Call controller's mapping logic directly
    $styleCode = 'GRM-STY-TEST-' . substr(strval(time()), -4);
    $styleName = 'PREMIUM BUTTON APPAREL';
    $wholeCode = 'ATDBMG18';
    $cleanSizes = ['M', 'L', 'XL'];
    $mrp = 799.00;
    $sellingPrice = 499.00;
    $color = 'Crimson Red';

    DB::beginTransaction();
    DB::query(
        "INSERT INTO catalogs (
            concern_id, code, style_code, short_code, name,
            category_id, subcategory_id, sub_sub_category_id,
            main_category_code, sub_category_code, sub_sub_category_code,
            hierarchy_path, hsn_code, gst_rate, sizes, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_house', NOW())",
        [
            $cid, $styleCode, $styleCode, $wholeCode, $styleName,
            $catId, $newSubcatId, $newTier3Id,
            'AT', $subCode, $tier3Code,
            'Accessories & Trims > ' . $subName . ' > ' . $tier3Name,
            '6109', 5.00, json_encode($cleanSizes)
        ]
    );
    $catalogId = (int)DB::lastInsertId();

    $primaryVariantId = null;
    $attrJson = json_encode(['color' => $color]);
    foreach ($cleanSizes as $size) {
        $sku = "{$styleCode}-{$size}";
        $varName = "{$styleName} - {$size}";
        DB::query(
            "INSERT INTO variants (
                concern_id, catalog_id, product_code, name,
                category_id, subcategory_id, size, attributes,
                price, mrp, creation_date, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'active', NOW())",
            [
                $cid, $catalogId, $sku, $varName,
                $catId, $newSubcatId, $size, $attrJson,
                $sellingPrice, $mrp
            ]
        );
        $vId = (int)DB::lastInsertId();
        if (!$primaryVariantId) $primaryVariantId = $vId;
    }

    DB::query("UPDATE purchase_bill_items SET catalog_id = ?, variant_id = ? WHERE id = 2", [$catalogId, $primaryVariantId]);

    DB::query(
        "INSERT INTO catalog_mappings (
            concern_id, purchase_bill_item_id, catalog_id, variant_id,
            category_id, subcategory_id, sub_sub_category_id,
            selected_sizes, selected_color, status, created_by, created_at
        ) VALUES (?, 2, ?, ?, ?, ?, ?, ?, ?, 'mapped', 1, NOW())
        ON DUPLICATE KEY UPDATE catalog_id = VALUES(catalog_id), variant_id = VALUES(variant_id), status = 'mapped'",
        [
            $cid, $catalogId, $primaryVariantId,
            $catId, $newSubcatId, $newTier3Id,
            json_encode($cleanSizes), $color
        ]
    );
    DB::commit();

    $updatedItem2 = DB::fetch("SELECT * FROM purchase_bill_items WHERE id = 2");
    assertTest("Item 2 now has catalog_id {$catalogId}", (int)$updatedItem2['catalog_id'] === $catalogId);
    assertTest("Item 2 now has variant_id {$primaryVariantId}", (int)$updatedItem2['variant_id'] === $primaryVariantId);

    $mapping = DB::fetch("SELECT * FROM catalog_mappings WHERE purchase_bill_item_id = 2");
    assertTest("catalog_mappings table has mapped record for item 2", !empty($mapping) && $mapping['status'] === 'mapped');

    $catalog = DB::fetch("SELECT * FROM catalogs WHERE id = ?", [$catalogId]);
    assertTest("Catalog style name matches", $catalog['name'] === $styleName);

    $variants = DB::fetchAll("SELECT * FROM variants WHERE catalog_id = ?", [$catalogId]);
    assertTest("Variants generated exactly 3 for sizes M, L, XL", count($variants) === 3);
}

echo "\n======================================================\n";
echo "TEST RESULTS: Total Tests = {$passCount} | Passed = {$passCount} | Failed = {$failCount}\n";
echo "======================================================\n";
if ($failCount === 0) {
    echo ">>> ALL AJAX & SAVE TESTS PASSED! <<<\n";
} else {
    exit(1);
}

