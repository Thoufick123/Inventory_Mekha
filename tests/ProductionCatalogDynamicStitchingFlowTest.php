<?php
@session_start();
require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;
use App\Controllers\ProductionController;

$_SESSION['user'] = ['id' => 1, 'name' => 'Super Administrator', 'role' => 'super_admin', 'concern_id' => null];

$passCount = 0;
function assertTest($condition, $message) {
    global $passCount;
    if ($condition) {
        echo "  [PASS] $message\n";
        $passCount++;
    } else {
        echo "  [FAIL] $message\n";
        exit(1);
    }
}

echo "======================================================\n";
echo "TEST: Production Catalog, Cutting & Dynamic Stitching Flow\n";
echo "======================================================\n\n";

// TEST 1: Cross-concern categories and subcategories query
echo "TEST 1: Subcategories & Sub-Subcategories Cross-Concern Fallback\n";
$cid = 2; // user switched to concern 2
$catWhere = $cid ? "(concern_id = ? OR concern_id = 1 OR concern_id IS NULL)" : "1=1";
$subcats = DB::fetchAll("SELECT id, name, code FROM subcategories WHERE category_id = 14 AND {$catWhere} AND status = 'active' ORDER BY name ASC", [$cid]);
assertTest(!empty($subcats), "Found subcategories under category 14 with concern_id = 2 fallback");
assertTest(strtolower($subcats[0]['name']) === 'maternity', "Subcategory name is 'Maternity'");

$subSubcats = DB::fetchAll("SELECT id, name, code FROM sub_subcategories WHERE subcategory_id = ? AND {$catWhere} AND status = 'active' ORDER BY name ASC", [$subcats[0]['id'], $cid]);
assertTest(!empty($subSubcats), "Found sub-subcategories under subcategory with concern_id = 2 fallback");
assertTest($subSubcats[0]['name'] === 'normal sleeve', "Sub-subcategory name is 'normal sleeve'");

// TEST 2: Catalogs query with cross-concern fallback
echo "\nTEST 2: Catalogs Query with Cross-Concern Fallback\n";
$catalogs = DB::fetchAll("SELECT id, style_code, short_code, name, category_id, subcategory_id, sub_sub_category_id FROM catalogs WHERE {$catWhere} AND status != 'inactive' ORDER BY id DESC", [$cid]);
assertTest(!empty($catalogs), "Found catalogs with concern_id = 2 fallback");
$catIds = array_column($catalogs, 'id');
assertTest(in_array(24, $catIds), "Catalog 24 found in catalogs list ('women - maternity - normal sleeve')");

// TEST 3: Program Rolls join with category and catalog
echo "\nTEST 3: Program 20 Rolls with Joined Catalog & Hierarchy\n";
$existingRolls = DB::fetchAll(
    "SELECT pr.*, 
            pbi.item_code AS current_roll_code, pbi.item_name AS current_roll_name, pbi.color AS current_roll_color,
            cat.name AS category_name, sub.name AS subcategory_name, subsub.name AS subsub_name,
            COALESCE(c.id, c_match.id) AS resolved_catalog_id,
            COALESCE(c.name, c_match.name) AS catalog_name,
            COALESCE(c.style_code, c_match.style_code) AS catalog_style_code
     FROM program_rolls pr
     LEFT JOIN purchase_bill_items pbi ON pr.purchase_bill_item_id = pbi.id
     LEFT JOIN categories cat ON pr.category_id = cat.id
     LEFT JOIN subcategories sub ON pr.subcategory_id = sub.id
     LEFT JOIN sub_subcategories subsub ON pr.sub_sub_category_id = subsub.id
     LEFT JOIN catalogs c ON pr.catalog_id = c.id
     LEFT JOIN catalogs c_match ON (pr.sub_sub_category_id IS NOT NULL AND pr.sub_sub_category_id = c_match.sub_sub_category_id)
     WHERE pr.program_id = 20
     ORDER BY pr.id ASC"
);
assertTest(!empty($existingRolls), "Found existing rolls for program 20");
assertTest($existingRolls[0]['category_name'] === 'women', "Roll has category_name 'women'");
assertTest($existingRolls[0]['subcategory_name'] === 'maternity', "Roll has subcategory_name 'maternity'");
assertTest($existingRolls[0]['subsub_name'] === 'normal sleeve', "Roll has subsub_name 'normal sleeve'");
assertTest($existingRolls[0]['catalog_name'] === 'women - maternity - normal sleeve', "Roll has catalog_name 'women - maternity - normal sleeve'");

// TEST 4: Stitching API query logic for cutting batch 18
echo "\nTEST 4: Stitching Dynamic Catalog Extraction for Batch 18\n";
$cutting = DB::fetch("SELECT cl.*, pp.catalog_id AS prog_catalog_id FROM cutting_logs cl LEFT JOIN production_programs pp ON cl.program_id = pp.id WHERE cl.id = 18");
assertTest(!empty($cutting), "Cutting batch 18 found");

$rawItems = DB::fetchAll(
    "SELECT ci.*, 
            pr.catalog_id AS roll_catalog_id,
            pr.category_id, pr.subcategory_id, pr.sub_sub_category_id,
            cat.name AS category_name, sub.name AS subcategory_name, subsub.name AS subsub_name,
            COALESCE(c.id, c_hier.id, " . ($cutting['prog_catalog_id'] ? (int)$cutting['prog_catalog_id'] : "NULL") . ") AS cat_id,
            COALESCE(c.name, c_hier.name, c_prog.name) AS cat_name,
            COALESCE(c.style_code, c_hier.style_code, c_prog.style_code) AS cat_code,
            v.id AS var_id, v.name AS var_name, v.product_code AS var_product_code, v.size AS var_size,
            v.catalog_id AS var_cat_id, v.mrp AS var_mrp, v.price AS var_sp,
            COALESCE(pr.color, pbi.color, 'Standard') AS roll_color
     FROM cutting_items ci
     LEFT JOIN program_rolls pr ON ci.program_roll_id = pr.id
     LEFT JOIN purchase_bill_items pbi ON pr.purchase_bill_item_id = pbi.id
     LEFT JOIN categories cat ON pr.category_id = cat.id
     LEFT JOIN subcategories sub ON pr.subcategory_id = sub.id
     LEFT JOIN sub_subcategories subsub ON pr.sub_sub_category_id = subsub.id
     LEFT JOIN catalogs c ON pr.catalog_id = c.id
     LEFT JOIN catalogs c_hier ON (pr.sub_sub_category_id IS NOT NULL AND pr.sub_sub_category_id = c_hier.sub_sub_category_id)
     LEFT JOIN catalogs c_prog ON " . ($cutting['prog_catalog_id'] ? (int)$cutting['prog_catalog_id'] : "0") . " = c_prog.id
     LEFT JOIN variants v ON ci.variant_id = v.id
     WHERE ci.cutting_id = 18
     ORDER BY ci.id ASC"
);

$catalogsMap = [];
foreach ($rawItems as $it) {
    $catId = $it['cat_id'] ?: ($it['var_cat_id'] ?: ($cutting['prog_catalog_id'] ?: 0));
    $catName = $it['cat_name'];
    $catCode = $it['cat_code'];

    if (!$catName) {
        $hierParts = array_filter([$it['category_name'] ?? '', $it['subcategory_name'] ?? '', $it['subsub_name'] ?? '']);
        if (!empty($hierParts)) {
            $catName = implode(' > ', $hierParts);
            $catCode = 'HIER';
            if (!$catId) {
                $catId = 'cat_' . ($it['category_id'] ?? 0) . '_' . ($it['subcategory_id'] ?? 0) . '_' . ($it['sub_sub_category_id'] ?? 0);
            }
        } else {
            $catName = 'Catalog Item';
            $catCode = 'CAT';
        }
    }

    $basePrice = (float)($it['var_mrp'] ?: 0.00);
    $discPrice = (float)($it['var_sp'] ?: 0.00);

    if ($catId && !isset($catalogsMap[$catId])) {
        $catalogsMap[$catId] = [
            'id'             => $catId,
            'name'           => $catName,
            'style_code'     => $catCode,
            'base_price'     => $basePrice,
            'discount_price' => $discPrice,
        ];
    }
}

assertTest(!empty($catalogsMap), "Catalogs map populated dynamically");
assertTest(isset($catalogsMap[24]), "Catalog 24 is in the catalogs map");
assertTest($catalogsMap[24]['name'] === 'women - maternity - normal sleeve', "Catalog name matches 'women - maternity - normal sleeve'");
assertTest($catalogsMap[24]['style_code'] === 'GRM-STY-0001', "Catalog code matches 'GRM-STY-0001'");
assertTest(count($rawItems) === 7, "All 7 cutting items extracted with catalog identities");

// TEST 5: Verify already-stitched cutting batch is filtered out of new stitching dropdown
echo "\nTEST 5: Exclude Already Stitched Cutting Batches from New Stitching Dropdown\n";
$cutSql = "SELECT cl.id, cl.batch_no 
           FROM cutting_logs cl 
           WHERE (cl.status IS NULL OR cl.status != 'cancelled') 
             AND cl.id NOT IN (
                 SELECT DISTINCT cutting_id 
                 FROM stitching_logs 
                 WHERE cutting_id IS NOT NULL AND (status IS NULL OR status != 'cancelled')
             )";
$availableCuttingBatches = DB::fetchAll($cutSql);
$availableIds = array_column($availableCuttingBatches, 'id');
assertTest(!in_array(18, $availableIds), "Cutting Batch 18 (already stitched) is NOT present in available cutting dropdown");

// Verify server-side validation against duplicate stitching assignment
$alreadyStitched = DB::fetch(
    "SELECT sl.id, COALESCE(sl.batch_no, sl.log_no) AS stitching_batch_no, sl.stitching_name 
     FROM stitching_logs sl 
     WHERE sl.cutting_id = 18 AND (sl.status IS NULL OR sl.status != 'cancelled') 
     LIMIT 1"
);
assertTest(!empty($alreadyStitched), "Validation detects that Cutting Batch 18 is already assigned to Stitching Batch {$alreadyStitched['stitching_batch_no']}");

echo "\n======================================================\n";
echo "TEST RESULTS: Total = $passCount | Passed = $passCount | Failed = 0\n";
echo ">>> ALL PRODUCTION CATALOG & DYNAMIC STITCHING TESTS PASSED! <<<\n";
echo "======================================================\n";
