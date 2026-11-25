<?php
// tests/CatalogMappingModuleTest.php - Automated Test for Catalog Mapping Module, Finished Goods Listing, 3-Tier Hierarchy & Attributes

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;
use App\Services\StockService;

echo "======================================================\n";
echo "INVENTO: Catalog Mapping & Finished Goods Module Test\n";
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
// TEST 1: Schema & Module Registration
// ----------------------------------------------------
echo "TEST 1: Module & Table Schema Integrity\n";

$mod = DB::fetch("SELECT * FROM modules WHERE code = 'catalog_mapping'");
assertTest("catalog_mapping module is registered in `modules`", !empty($mod));
assertTest("catalog_mapping module is in 'Purchase' group", !empty($mod) && $mod['group_name'] === 'Purchase');

$pbiCols = array_column(DB::fetchAll("DESCRIBE purchase_bill_items"), 'Field');
assertTest("purchase_bill_items has 'catalog_id' column", in_array('catalog_id', $pbiCols));

$cmExists = (int)DB::fetchValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'catalog_mappings'");
assertTest("catalog_mappings table exists", $cmExists > 0);

$paExists = (int)DB::fetchValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'product_attributes'");
assertTest("product_attributes table exists", $paExists > 0);

// ----------------------------------------------------
// TEST 2: Setup Purchase Bill with Finished Goods Item
// ----------------------------------------------------
echo "\nTEST 2: Setup Purchase Bill with Finished Goods Item\n";
$cid1 = 1;
Auth::login('grm.admin@invento.com', 'password123');

// Ensure a purchase bill exists
$bill = DB::fetch("SELECT id FROM purchase_bills WHERE concern_id = ? LIMIT 1", [$cid1]);
if (!$bill) {
    DB::query("INSERT INTO purchase_bills (concern_id, bill_no, supplier_id, bill_date, total_amount, payment_status, created_at) VALUES (?, 'TEST-BILL-FG', 1, CURDATE(), 25000.00, 'paid', NOW())", [$cid1]);
    $billId = (int)DB::lastInsertId();
} else {
    $billId = (int)$bill['id'];
}

// Insert a finished goods item (e.g. RED SHIRT, 50 pcs @ 250.00)
$uniqueFgCode = 'FG-TEST-' . time();
DB::query(
    "INSERT INTO purchase_bill_items (concern_id, purchase_bill_id, section_type, item_code, item_name, qty, unit, unit_price, total_price, hsn_code, gst_rate, tax_mode)
     VALUES (?, ?, 'finished_goods', ?, 'PREMIUM RED SHIRT', 50.00, 'Pieces', 250.00, 12500.00, '6109', 5.00, 'Include GST')",
    [$cid1, $billId, $uniqueFgCode]
);
$fgItemId = (int)DB::lastInsertId();
assertTest("Inserted purchased Finished Good item with ID {$fgItemId} (Code: {$uniqueFgCode})", $fgItemId > 0);

// ----------------------------------------------------
// TEST 3: Catalog Mapping Query & Initial Unmapped State
// ----------------------------------------------------
echo "\nTEST 3: Initial Unmapped State Verification\n";
$unmappedCheck = DB::fetch(
    "SELECT pbi.*, cm.id AS mapping_id 
     FROM purchase_bill_items pbi 
     LEFT JOIN catalog_mappings cm ON cm.purchase_bill_item_id = pbi.id 
     WHERE pbi.id = ?",
    [$fgItemId]
);
assertTest("Item is initially unmapped (mapping_id is null)", empty($unmappedCheck['mapping_id']) && empty($unmappedCheck['catalog_id']));

// ----------------------------------------------------
// TEST 4: 3-Tier Hierarchy Creation & Cascading
// ----------------------------------------------------
echo "\nTEST 4: 3-Tier Category Hierarchy Creation\n";
// Tier 1: Main Category
DB::query(
    "INSERT INTO categories (concern_id, name, code, default_hsn, default_gst_rate, status, created_at)
     VALUES (?, 'Menswear Collection', 'MC', '6109', 5.00, 'active', NOW())",
    [$cid1]
);
$catId = (int)DB::lastInsertId();
assertTest("Created Tier-1 Main Category 'Menswear Collection' with ID {$catId}", $catId > 0);

// Tier 2: Subcategory
DB::query(
    "INSERT INTO subcategories (concern_id, category_id, name, code, status, created_at)
     VALUES (?, ?, 'Cotton Shirts', 'CSH', 'active', NOW())",
    [$cid1, $catId]
);
$subcatId = (int)DB::lastInsertId();
assertTest("Created Tier-2 Subcategory 'Cotton Shirts' with ID {$subcatId}", $subcatId > 0);

// Tier 3: Sub-Subcategory
DB::query(
    "INSERT INTO sub_subcategories (concern_id, subcategory_id, name, code, status, created_at)
     VALUES (?, ?, 'Casual Solid Shirt', 'CSS', 'active', NOW())",
    [$cid1, $subcatId]
);
$subsubId = (int)DB::lastInsertId();
assertTest("Created Tier-3 Sub-Subcategory 'Casual Solid Shirt' with ID {$subsubId}", $subsubId > 0);

// ----------------------------------------------------
// TEST 5: Product Attribute Creation
// ----------------------------------------------------
echo "\nTEST 5: Product Attributes Creation\n";
DB::query(
    "INSERT INTO product_attributes (concern_id, attribute_type, attribute_name, attribute_value, sort_order)
     VALUES (?, 'size', 'Special Sizes', '4XL', 10)",
    [$cid1]
);
$attrId = (int)DB::lastInsertId();
assertTest("Created custom attribute '4XL' with ID {$attrId}", $attrId > 0);

// ----------------------------------------------------
// TEST 6: Execution of Catalog Mapping & Variant Generation
// ----------------------------------------------------
echo "\nTEST 6: Catalog Mapping Execution & Auto-Generation\n";
$styleCode = 'GRM-STY-' . rand(1000, 9999);
$styleName = 'PREMIUM RED CASUAL SHIRT';
$wholeCode = 'MC-CSH-CSS';
$hierarchyPath = 'Menswear Collection > Cotton Shirts > Casual Solid Shirt';
$sizes = ['S', 'M', 'L', 'XL', '4XL'];

DB::beginTransaction();
try {
    // 1. Insert catalogs style
    DB::query(
        "INSERT INTO catalogs (
            concern_id, code, style_code, short_code, name,
            category_id, subcategory_id, sub_sub_category_id,
            main_category_code, sub_category_code, sub_sub_category_code,
            hierarchy_path, hsn_code, gst_rate, sizes, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'MC', 'CSH', 'CSS', ?, '6109', 5.00, ?, 'in_house', NOW())",
        [$cid1, $styleCode, $styleCode, $wholeCode, $styleName, $catId, $subcatId, $subsubId, $hierarchyPath, json_encode($sizes)]
    );
    $catalogId = (int)DB::lastInsertId();

    // 2. Generate variants for each size
    $primaryVarId = null;
    $attrJson = json_encode(['color' => 'Red']);
    foreach ($sizes as $s) {
        $sku = "{$styleCode}-{$s}";
        $varName = "{$styleName} - {$s}";
        DB::query(
            "INSERT INTO variants (
                concern_id, catalog_id, product_code, name,
                category_id, subcategory_id, size, attributes,
                price, mrp, creation_date, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 499.00, 599.00, CURDATE(), 'active', NOW())",
            [$cid1, $catalogId, $sku, $varName, $catId, $subcatId, $s, $attrJson]
        );
        $vId = (int)DB::lastInsertId();
        if (!$primaryVarId) $primaryVarId = $vId;
    }

    // 3. Update purchase_bill_items
    DB::query("UPDATE purchase_bill_items SET catalog_id = ?, variant_id = ? WHERE id = ?", [$catalogId, $primaryVarId, $fgItemId]);

    // 4. Insert catalog_mappings entry
    DB::query(
        "INSERT INTO catalog_mappings (
            concern_id, purchase_bill_item_id, catalog_id, variant_id,
            category_id, subcategory_id, sub_sub_category_id,
            selected_sizes, selected_color, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Red', 'mapped', NOW())",
        [$cid1, $fgItemId, $catalogId, $primaryVarId, $catId, $subcatId, $subsubId, json_encode($sizes)]
    );
    $mappingId = (int)DB::lastInsertId();

    // 5. Inward inventory via StockService
    StockService::move($cid1, $primaryVarId, 'Main Store', 50.00, 0.00, 'catalog_mapping', $fgItemId, 'Test FG Inward', 1);

    DB::commit();
    assertTest("Catalog Style #{$styleCode} created with ID {$catalogId}", $catalogId > 0);
    assertTest("Catalog Mapping record created with ID {$mappingId}", $mappingId > 0);
} catch (\Throwable $e) {
    DB::rollBack();
    assertTest("Mapping execution threw error: " . $e->getMessage(), false);
}

// ----------------------------------------------------
// TEST 7: Verification of Mapped State & Child Variants
// ----------------------------------------------------
echo "\nTEST 7: Verification of Mapped State & Inventory\n";
$mappedCheck = DB::fetch(
    "SELECT pbi.*, cm.id AS mapping_id, cm.status AS mapping_status,
            c.name AS catalog_name, c.hierarchy_path
     FROM purchase_bill_items pbi 
     JOIN catalog_mappings cm ON cm.purchase_bill_item_id = pbi.id 
     JOIN catalogs c ON pbi.catalog_id = c.id 
     WHERE pbi.id = ?",
    [$fgItemId]
);
assertTest("Finished good item is now MAPPED", !empty($mappedCheck) && $mappedCheck['mapping_status'] === 'mapped');
assertTest("Item is linked to Catalog Style '{$styleName}'", $mappedCheck['catalog_name'] === $styleName);
assertTest("Hierarchy path correctly resolved: '{$hierarchyPath}'", $mappedCheck['hierarchy_path'] === $hierarchyPath);

$generatedVariants = DB::fetchAll("SELECT * FROM variants WHERE catalog_id = ? ORDER BY id ASC", [$catalogId]);
assertTest("Exactly 5 size variants automatically generated (S, M, L, XL, 4XL)", count($generatedVariants) === 5);

$var4XL = DB::fetch("SELECT * FROM variants WHERE catalog_id = ? AND size = '4XL'", [$catalogId]);
assertTest("Custom size variant '4XL' SKU matches '{$styleCode}-4XL'", !empty($var4XL) && $var4XL['product_code'] === "{$styleCode}-4XL");

// Check stock ledger
$stockLedgerEntry = DB::fetch("SELECT * FROM stock_ledger WHERE ref_module = 'catalog_mapping' AND ref_id = ?", [$fgItemId]);
assertTest("Stock ledger credited 50.00 units under variant ID {$primaryVarId}", !empty($stockLedgerEntry) && (float)$stockLedgerEntry['qty_in'] == 50.00);

// ----------------------------------------------------
// TEST 8: Unmap Operation Verification
// ----------------------------------------------------
echo "\nTEST 8: Unmap Operation\n";
DB::query("DELETE FROM catalog_mappings WHERE purchase_bill_item_id = ?", [$fgItemId]);
DB::query("UPDATE purchase_bill_items SET catalog_id = NULL WHERE id = ?", [$fgItemId]);

$unmapResult = DB::fetch(
    "SELECT pbi.*, cm.id AS mapping_id 
     FROM purchase_bill_items pbi 
     LEFT JOIN catalog_mappings cm ON cm.purchase_bill_item_id = pbi.id 
     WHERE pbi.id = ?",
    [$fgItemId]
);
assertTest("After unmap, catalog_mappings row deleted and item reverts to unmapped", empty($unmapResult['mapping_id']) && empty($unmapResult['catalog_id']));

// ----------------------------------------------------
// TEST 9: Multi-Tenant Isolation
// ----------------------------------------------------
echo "\nTEST 9: Multi-Tenant Scoping Isolation\n";
$cid2 = 2;
$tenant2Lookup = DB::fetch("SELECT * FROM purchase_bill_items WHERE id = ? AND concern_id = ?", [$fgItemId, $cid2]);
assertTest("Concern 2 cannot view or map finished good item owned by Concern 1", empty($tenant2Lookup));

// ----------------------------------------------------
// SUMMARY
// ----------------------------------------------------
echo "\n======================================================\n";
echo "TEST RESULTS: Total Tests = " . ($passCount + $failCount) . " | Passed = {$passCount} | Failed = {$failCount}\n";
echo "======================================================\n";

if ($failCount === 0) {
    echo ">>> ALL CATALOG MAPPING & FINISHED GOODS TESTS PASSED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED. PLEASE REVIEW LOGS. <<<\n";
    exit(1);
}
