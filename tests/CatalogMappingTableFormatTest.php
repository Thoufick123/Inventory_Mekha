<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Controllers\CatalogMappingController;

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

echo "=== Catalog Mapping Table Format Verification Test ===\n";

// Set up concern session
$user = DB::fetch("SELECT u.*, c.id AS cid, c.name AS cname FROM users u JOIN concerns c ON u.concern_id = c.id WHERE u.status = 'active' AND u.concern_id IS NOT NULL LIMIT 1");
if (!$user) {
    echo "No valid concern user found.\n";
    exit(1);
}

\App\Core\Auth::startSession();
$_SESSION['user_id'] = $user['id'];
$_SESSION['concern_id'] = $user['cid'];
$_SESSION['role'] = $user['role'] ?? 'concern_admin';

// Fetch or create a test finished good
$fgItem = DB::fetch("SELECT * FROM purchase_bill_items WHERE section_type = 'finished_goods' LIMIT 1");
if (!$fgItem) {
    echo "No finished goods item found.\n";
    exit(1);
}

$_GET['id'] = $fgItem['id'];

$controller = new CatalogMappingController();
ob_start();
$controller->mapItemForm();
$html = ob_get_clean();

assertCondition(!empty($html), "Catalog mapping form rendered successfully");

// 1. Verify Baseline Details in Table Format (Net Weight removed)
assertCondition(str_contains($html, '1. Purchased Product Baseline Details'), "Section 1 Baseline Details header exists");
assertCondition(str_contains($html, 'Product Identity') && str_contains($html, 'Supplier Profile') && str_contains($html, 'Invoice & Accounting References'), "Table 1.1: Product Identity & Supplier Profile table exists");
assertCondition(str_contains($html, 'Purchased Qty') && str_contains($html, 'Supplier Unit Cost') && str_contains($html, 'Total Line Valuation'), "Table 1.2: Quantities & Financial Accounting table exists");
assertCondition(!str_contains($html, 'Net Weight'), "Net Weight field is removed from Table 1.2 Baseline Details as requested");

// 2. Verify 3-Tier Hierarchy & HSN Removal
assertCondition(str_contains($html, '2. Category Hierarchy (3-Tier), HSN & Product Attributes'), "Combined hierarchy, HSN, and product attributes header exists");
assertCondition(str_contains($html, 'Hierarchy Tier') && str_contains($html, 'Category Selection') && str_contains($html, 'Short Code'), "Table 2.1: 3-Tier Category Hierarchy Matrix table exists");
$hierarchyStart = strpos($html, 'id="cardCategoryHierarchy"');
$variantsStart = $hierarchyStart === false ? false : strpos($html, 'id="cardProductVariants"', $hierarchyStart);
$hierarchyAndAttributesHtml = ($hierarchyStart !== false && $variantsStart !== false)
    ? substr($html, $hierarchyStart, $variantsStart - $hierarchyStart)
    : '';
assertCondition(
    $hierarchyStart !== false &&
    $variantsStart !== false &&
    substr_count($hierarchyAndAttributesHtml, '<table') === 1 &&
    str_contains($hierarchyAndAttributesHtml, 'id="tableHierarchyAttributes"') &&
    str_contains($hierarchyAndAttributesHtml, 'colspan="3"') &&
    str_contains($hierarchyAndAttributesHtml, 'colspan="2"') &&
    !str_contains($hierarchyAndAttributesHtml, 'id="cardProductAttributes"'),
    "Hierarchy and product attributes share one table with aligned column groups"
);
assertCondition(
    str_contains($hierarchyAndAttributesHtml, '<col style="width: 12%;">') &&
    str_contains($hierarchyAndAttributesHtml, '<col style="width: 8%;">') &&
    str_contains($hierarchyAndAttributesHtml, '<col style="width: 14%;">'),
    "Hierarchy Tier, Short Code, and Product Attribute columns use compact widths"
);
assertCondition(!str_contains($html, 'Select Mapping Action:') && !str_contains($html, 'Link to Existing Catalog Style'), "Catalog mapping has one create-style action");
assertCondition(!str_contains($html, 'HSN Code & GST Tax Classification Table'), "Table 2.2: HSN Code & GST Tax Classification Table is removed as requested");
assertCondition(str_contains($html, 'id="inputHsnCode"'), "Hidden HSN input exists for form mapping");

// 3. Verify Product Attributes & Variations in Table Format and saved edit state
assertCondition(str_contains($html, 'Product Attributes & Variations'), "Product Attributes & Variations appear in the combined hierarchy table");
assertCondition(str_contains($html, 'Product Style Code') && str_contains($html, 'Product Style Name') && str_contains($html, 'Color Attributes') && str_contains($html, 'Size Attributes'), "Attribute Table rows exist");
assertCondition(
    str_contains($html, 'customDimensionsByType') &&
    str_contains($html, 'variant_attribute_values[') &&
    str_contains($html, 'remove-variant-row') &&
    str_contains($html, 'restoreDeletedVariants'),
    "Variant matrix uses selected dynamic attributes and supports row deletion/restoration"
);
assertCondition(!str_contains($html, 'Attribute Description & Usage'), "Attribute Description & Usage column is removed");

assertCondition(
    str_contains($html, 'const savedVariantRows =') &&
    str_contains($html, 'savedVariantsPending') &&
    str_contains($html, 'savedRow.custom_attributes') &&
    str_contains($html, 'savedRow.selling_price'),
    "Existing variants restore their dynamic attributes and saved editable values"
);

// 4. Verify Pricing Configuration Table Removal
assertCondition(!str_contains($html, 'id="cardPricing"'), "Section 4 Pricing Configuration table is removed as requested");

// 5. Verify Generated Product Variants Matrix Table in Editable Format
assertCondition(str_contains($html, 'id="cardProductVariants"'), "Card 'cardProductVariants' exists");
assertCondition(str_contains($html, 'Product Variants Matrix Table (Editable Format)'), "Product Variants Matrix Table (Editable Format) header exists");
assertCondition(str_contains($html, 'Quick Fill (All Columns):'), "Quick Fill (All Columns) bar exists");
assertCondition(
    !str_contains($html, 'id="quickColorInput"') &&
    str_contains($html, 'id="quickQtyInput"') &&
    str_contains($html, 'id="quickWeightInput"') &&
    str_contains($html, 'id="quickHsnInput"') &&
    str_contains($html, 'id="quickMrpInput"') &&
    str_contains($html, 'id="quickSellingInput"') &&
    str_contains($html, '>QTY</label>') &&
    !str_contains($html, 'QTY / SIZE'),
    "Quick fill omits Color and labels the quantity field QTY"
);
assertCondition(str_contains($html, 'id="colorSuggestions"'), "Color suggestions datalist exists");
assertCondition(str_contains($html, 'applyQuickFillAll()'), "JavaScript applyQuickFillAll() function exists");
assertCondition(str_contains($html, 'id="tableProductVariants"'), "Table 'tableProductVariants' exists");
assertCondition(
    str_contains($html, 'variant-matrix-scroll') &&
    str_contains($html, 'matrix-col-sku') &&
    str_contains($html, 'matrix-col-actions') &&
    str_contains($html, 'Scroll horizontally to view every field'),
    "Variant matrix keeps SKU and row actions visible and identifies horizontal scrolling"
);
assertCondition(
    str_contains($html, 'Variant SKU Code *') &&
    str_contains($html, 'Product Variant Name *') &&
    str_contains($html, 'Hierarchy QR Key') &&
    str_contains($html, 'Color') &&
    str_contains($html, 'Qty *') &&
    str_contains($html, 'Weight (kg)') &&
    str_contains($html, 'HSN Code') &&
    str_contains($html, 'Cost (₹)') &&
    str_contains($html, 'MRP (₹) *') &&
    str_contains($html, 'Selling Price (₹) *'),
    "Editable Variant Table headers exist with: Qty, Weight (kg), HSN Code, Hierarchy QR Key, Color"
);
assertCondition(str_contains($html, 'variant-qr-key-badge'), "QR key badge class variant-qr-key-badge exists in JS render");
assertCondition(str_contains($html, 'variant_weights['), "Weight input variant_weights exists in JS render");
assertCondition(str_contains($html, 'id="variantsTableBody"'), "Tbody 'variantsTableBody' exists for dynamic editable SKU population");
assertCondition(str_contains($html, 'applyBatchPrices()'), "JavaScript applyBatchPrices() function exists");
assertCondition(str_contains($html, 'Math.abs(totalAlloc - purchasedBatchQty) > 0.001'), "Quantity validation (sum equals total qty) exists in JS");
assertCondition(str_contains($html, 'sp <= supplierUnitCost') && str_contains($html, 'mrp <= sp'), "Pricing validation (Supplier Cost < Selling Price < MRP) exists in JS");

// 6. Verify Mapping Summary & Stock Projection Removal
assertCondition(!str_contains($html, 'id="cardSummary"'), "Section 5 Mapping Summary & Stock Projection table is removed as requested");

// 7. Verify Bottom Actions
assertCondition(str_contains($html, 'id="btnSaveMapping"'), "Submit button 'btnSaveMapping' exists");

// 8. Verify Attribute Modal Refinements (from catalog_mapping.php)
ob_start();
$_GET['tab'] = 'hierarchy';
$controller->index();
$indexHtml = ob_get_clean();

assertCondition(str_contains($indexHtml, 'id="modalAddAttribute"'), "Modal modalAddAttribute exists");
assertCondition(str_contains($indexHtml, '+ Create New Attribute Type...'), "+ Create New Attribute Type... option exists in dropdown");
assertCondition(!str_contains($indexHtml, 'Group / Display Label'), "Group / Display Label input field is removed as requested");
assertCondition(!str_contains($indexHtml, 'Size (e.g. S, M, XL, 3XL, Free Size)'), "Hardcoded static options removed from attribute type select");

// 9. Verify Category database is free of static dummy/seed data (e.g. KIDS, BABY, DRESS)
$kidsCount = (int)DB::fetchValue("SELECT COUNT(*) FROM categories WHERE name = 'KIDS'");
$babyCount = (int)DB::fetchValue("SELECT COUNT(*) FROM subcategories WHERE name = 'BABY'");
$dressCount = (int)DB::fetchValue("SELECT COUNT(*) FROM sub_subcategories WHERE name = 'DRESS'");
$catalogCount = (int)DB::fetchValue("SELECT COUNT(*) FROM catalogs WHERE name IN ('KIDS', 'BABY', 'DRESS')");
assertCondition($kidsCount === 0, "Static seed category 'KIDS' is not in database");
assertCondition($babyCount === 0, "Static seed subcategory 'BABY' is not in database");
assertCondition($dressCount === 0, "Static seed sub-subcategory 'DRESS' is not in database");
assertCondition($catalogCount === 0, "Static seed catalog entries are not in database ({$catalogCount})");

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
