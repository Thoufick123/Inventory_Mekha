<?php

declare(strict_types=1);

$controllerSource = file_get_contents(__DIR__ . '/../app/controllers/LabelController.php');
$purchaseSource = file_get_contents(__DIR__ . '/../app/controllers/PurchaseController.php');
$productAddingSource = file_get_contents(__DIR__ . '/../app/controllers/ProductAddingController.php');
$routesSource = file_get_contents(__DIR__ . '/../app/routes.php');
$formSource = file_get_contents(__DIR__ . '/../app/views/concern/purchase_billing_create.php');
$migrationSource = file_get_contents(__DIR__ . '/../database/live_db_update.sql');
$schemaSource = file_get_contents(__DIR__ . '/../database/schema.sql');
$sourceService = file_get_contents(__DIR__ . '/../app/services/PurchaseBillSourceService.php');

$checks = [
    'Product Adding bills are tagged separately without changing Purchase Billing defaults' =>
        str_contains($purchaseSource, "'product_adding' : 'purchase_billing'")
        && str_contains($purchaseSource, "source_module, supplier_id")
        && str_contains($schemaSource, "`source_module` VARCHAR(50) NOT NULL DEFAULT 'purchase_billing'")
        && str_contains($migrationSource, "AddColSafe('purchase_bills', 'source_module'")
        && str_contains($schemaSource, "`source_module` VARCHAR(50) NOT NULL DEFAULT 'label_generation'")
        && str_contains($migrationSource, "AddColSafe('label_jobs', 'source_module'")
        && str_contains($sourceService, "ALTER TABLE label_jobs"),
    'A saved Product Adding bill returns to its label and stock panel' =>
        str_contains($purchaseSource, '$successRedirect . \'?bill_id=\' . $billId')
        && str_contains($productAddingSource, "pb.source_module = 'product_adding'"),
    'Individual and checked-line bulk label routes are Product Adding scoped' =>
        str_contains($routesSource, '/m/product_adding/generate_labels')
        && str_contains($routesSource, '/m/product_adding/add_to_stock')
        && str_contains($controllerSource, "WHERE pb.id = ? AND pb.source_module = 'product_adding'")
        && str_contains($controllerSource, 'pbi.id IN ({$placeholders})'),
    'Printed label jobs cover the rounded-up quantity and remain item-linked' =>
        str_contains($controllerSource, '$defaultCopies = (int)ceil((float)$item[\'qty\'])')
        && str_contains($controllerSource, '(int)$item[\'id\'],')
        && str_contains($controllerSource, "'qty' => \$copies")
        && str_contains($formSource, 'name="item_ids[]"'),
    'Product Adding finished-goods labels use the product name and shared unique variant-code flow' =>
        str_contains($controllerSource, "'label_1_val' => \$variantName")
        && str_contains($controllerSource, 'QrService::generateFinishedGoodsCode($variantId)')
        && str_contains($controllerSource, "'variant_id' => \$variantId"),
    'In-stock finished-goods labels use each variant name and the same unique code generator' =>
        str_contains($controllerSource, "'label_1_val'    => \$prodName")
        && str_contains($controllerSource, "\$variantLabel['label_1_val'] = \$variantName;")
        && str_contains($controllerSource, 'QrService::generateFinishedGoodsCode((int)$mappedVariant[\'id\'])'),
    'Product Adding label printing asks per-line counts and embeds the preview on the bill page' =>
        str_contains($formSource, 'id="productAddingLabelCountModal"')
        && str_contains($formSource, 'data-default-label-count="<?= $requiredLabelQty ?>"')
        && str_contains($formSource, 'target="productAddingLabelPreviewFrame"')
        && str_contains($formSource, 'data-label-count-item-id')
        && str_contains($controllerSource, '$requestedLabelCounts[(int)$item[\'id\']]')
        && str_contains($controllerSource, '$totalCopies + $copies > 2000')
        && str_contains($controllerSource, "'generatedItemCounts' => \$generatedItemCounts"),
    'Product Adding inward opens the destination stock module by item type' =>
        str_contains($controllerSource, '$redirectUrl = \'/m/stocks\';')
        && str_contains($controllerSource, "'/m/purchase_stocks' .")
        && str_contains($controllerSource, '$targetTab === \'finished_goods\' ? \'/m/stocks\''),
    'Product Adding stock inward is denied until baseline-aware label coverage is complete' =>
        str_contains($routesSource, 'addProductAddingToStock')
        && str_contains($controllerSource, 'assertProductAddingLabelCoverage($itemId)')
        && str_contains($controllerSource, "source_module = 'product_adding' AND id > ?")
        && str_contains($controllerSource, '$generatedQty < $requiredQty'),
    'The saved-bill UI only enables stock after label coverage' =>
        str_contains($formSource, '$savedItem[\'labels_complete\']')
        && str_contains($formSource, 'productAddingStockUrl'),
    'Finished-goods attributes can generate combinations and quick-fill shared fields' =>
        str_contains($formSource, 'data-variant-attribute-selector')
        && str_contains($formSource, 'data-variant-attribute-option')
        && str_contains($formSource, 'generateProductAddingVariantRows')
        && str_contains($formSource, 'applyProductAddingQuickFill')
        && str_contains($formSource, 'Quick Fill (All Columns)')
        && str_contains($formSource, 'data-quick-fill-auto-balance'),
    'Quick Fill precedes the matrix and untouched auto-named rows are replaced' =>
        strpos($formSource, '<div class="fg-quick-fill-panel') < strpos($formSource, '<div class="table-responsive fg-variant-matrix-scroll">')
        && str_contains($formSource, 'const starterRows = rows.filter(row => {')
        && str_contains($formSource, "nameInput?.dataset.userEdited !== '1' && !hasVariantData")
        && str_contains($formSource, 'starterRows.forEach(row => row.remove())'),
    'Quick Fill supports category hierarchy and uses HSN-derived GST' =>
        str_contains($formSource, 'data-quick-fill-hierarchy="category"')
        && str_contains($formSource, 'data-quick-fill-hierarchy="subcategory"')
        && str_contains($formSource, 'data-quick-fill-hierarchy="sub-subcategory"')
        && str_contains($formSource, 'updateProductAddingQuickFillHierarchy')
        && str_contains($formSource, 'data-quick-fill-field="hsn" data-hsn-autocomplete')
        && !str_contains($formSource, 'data-quick-fill-field="gst"')
        && str_contains($formSource, 'onFinishedVariantHsnChange(fgId, parseInt(row.dataset.rowIndex, 10))'),
    'Finished-goods prices are ordered and GST is sourced from the registered HSN' =>
        str_contains($purchaseSource, 'Every finished-goods variant must satisfy Supplier Cost < Selling Price < MRP')
        && str_contains($purchaseSource, '$sellingPrice <= $supplierCost || $mrp <= $sellingPrice')
        && str_contains($purchaseSource, 'catalogGstRate = (float)$hsnMaster[\'gst_rate\']'),
    'Duplicate supplier invoices are checked inline before the form submits' =>
        str_contains($routesSource, '/m/product_adding/check_invoice')
        && str_contains($routesSource, '/m/purchase_billing/check_invoice')
        && str_contains($purchaseSource, 'checkSupplierInvoiceNumberAjax')
        && str_contains($formSource, 'id="billSupplierInvoiceFeedback"')
        && str_contains($formSource, 'checkSupplierInvoiceNumber().then')
        && str_contains($formSource, 'form.requestSubmit'),
    'Quick Fill fields follow the finished-goods matrix order' =>
        strpos($formSource, 'for="fgQuickCategory_${id}"')
            < strpos($formSource, 'for="fgQuickSubcategory_${id}"')
        && strpos($formSource, 'for="fgQuickSubcategory_${id}"')
            < strpos($formSource, 'for="fgQuickSubSubcategory_${id}"')
        && strpos($formSource, 'for="fgQuickSubSubcategory_${id}"')
            < strpos($formSource, 'for="fgQuickQty_${id}"')
        && strpos($formSource, 'for="fgQuickQty_${id}"')
            < strpos($formSource, 'for="fgQuickWeight_${id}"')
        && strpos($formSource, 'for="fgQuickWeight_${id}"')
            < strpos($formSource, 'for="fgQuickHsn_${id}"')
        && strpos($formSource, 'for="fgQuickHsn_${id}"')
            < strpos($formSource, 'data-quick-fill-gst-preview')
        && strpos($formSource, 'data-quick-fill-gst-preview')
            < strpos($formSource, 'for="fgQuickCost_${id}"')
        && strpos($formSource, 'for="fgQuickCost_${id}"')
            < strpos($formSource, 'for="fgQuickMrp_${id}"')
        && strpos($formSource, 'for="fgQuickMrp_${id}"')
            < strpos($formSource, 'for="fgQuickSelling_${id}"')
        && str_contains($formSource, 'data-hsn-gst-target="#fgQuickGst_${id}"'),
    'Bulk Add Stock uses selected lines and enforces label coverage per item' =>
        str_contains($formSource, 'id="productAddingBulkStockButton"')
        && str_contains($formSource, 'formaction="<?= $productAddingStockUrl ?>"')
        && str_contains($formSource, "input.dataset.labelsComplete === '1'")
        && str_contains($controllerSource, 'if ($isProductAddingRequest && $selectedItemIds)')
        && str_contains($controllerSource, '$this->assertProductAddingLabelCoverage((int)$selectedItem[\'id\']);')
        && str_contains($controllerSource, '$this->inwardProductAddingFinishedGood($concernId, $selectedItem, $billNo)'),
];

$failed = false;
foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . "\n";
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
