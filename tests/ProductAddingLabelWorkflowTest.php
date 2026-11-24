<?php

declare(strict_types=1);

$controllerSource = file_get_contents(__DIR__ . '/../app/controllers/LabelController.php');
$stockServiceSource = file_get_contents(__DIR__ . '/../app/services/StockService.php');
$purchaseSource = file_get_contents(__DIR__ . '/../app/controllers/PurchaseController.php');
$productAddingSource = file_get_contents(__DIR__ . '/../app/controllers/ProductAddingController.php');
$routesSource = file_get_contents(__DIR__ . '/../app/routes.php');
$formSource = file_get_contents(__DIR__ . '/../app/views/concern/purchase_billing_create.php');
$schemaSource = file_get_contents(__DIR__ . '/../database/schema.sql');
$sourceService = file_get_contents(__DIR__ . '/../app/services/PurchaseBillSourceService.php');
$stockOverrideSource = file_get_contents(__DIR__ . '/../app/services/PurchaseStockItemOverrideService.php');
$rollRegistrySource = file_get_contents(__DIR__ . '/../app/services/RollRegistryService.php');
$purchaseStockSource = file_get_contents(__DIR__ . '/../app/controllers/PurchaseStockController.php');

$checks = [
    'Product Adding bills are tagged separately without changing Purchase Billing defaults' =>
        str_contains($purchaseSource, "'product_adding' : 'purchase_billing'")
        && str_contains($purchaseSource, "source_module, supplier_id")
        && str_contains($schemaSource, "`source_module` varchar(50) NOT NULL DEFAULT 'purchase_billing'")
        && str_contains($sourceService, "ADD COLUMN source_module VARCHAR(50) NOT NULL DEFAULT 'purchase_billing'")
        && str_contains($schemaSource, "`source_module` varchar(50) NOT NULL DEFAULT 'label_generation'")
        && str_contains($sourceService, "ADD COLUMN source_module VARCHAR(50) NOT NULL DEFAULT 'label_generation'")
        && str_contains($sourceService, "ALTER TABLE label_jobs"),
    'A saved Product Adding bill returns to its label and stock panel' =>
        str_contains($purchaseSource, '$successRedirect . \'?bill_id=\' . $billId')
        && str_contains($productAddingSource, "pb.source_module = 'product_adding'"),
    'Saved supplier details are retained above labels instead of showing an empty purchase form' =>
        strpos($formSource, 'Purchase &amp; Supplier Details</h5>') < strpos($formSource, 'Labels &amp; Stock —')
        && str_contains($productAddingSource, 'c.name AS concern_name')
        && str_contains($formSource, '$savedBill[\'supplier_invoice_no\']')
        && str_contains($formSource, '$savedBill[\'net_amount\']')
        && str_contains($formSource, '<?= !empty($savedBill) ? \'d-none\' : \'\' ?>'),
    'Label count and the original bill quantity are shown independently' =>
        str_contains($productAddingSource, 'pbi.qty AS purchase_qty_original')
        && str_contains($productAddingSource, 'SELECT lj.qty')
        && str_contains($productAddingSource, 'ORDER BY lj.id DESC')
        && !str_contains($productAddingSource, 'SELECT SUM(lj.qty)')
        && str_contains($productAddingSource, '$savedItem[\'generated_label_qty\'] > 0')
        && str_contains($formSource, 'Stock qty: <?= htmlspecialchars((string)$purchaseQty)')
        && str_contains($formSource, 'data-purchase-qty="<?= htmlspecialchars((string)$purchaseQty) ?>"')
        && str_contains($formSource, 'const generatedCount = Number(event.data.itemCounts[itemId]) || 0;'),
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
        && str_contains($formSource, 'data-default-label-count="<?= $defaultLabelQty ?>"')
        && str_contains($formSource, 'target="productAddingLabelPreviewFrame"')
        && str_contains($formSource, 'data-label-count-item-id')
        && str_contains($formSource, "hiddenItemId.name = 'item_ids[]'")
        && str_contains($controllerSource, '$requestedLabelCounts[(int)$item[\'id\']]')
        && str_contains($controllerSource, 'array_keys($requestedLabelCounts)')
        && str_contains($controllerSource, '$totalCopies + $copies > 2000')
        && str_contains($controllerSource, "'generatedItemCounts' => \$generatedItemCounts"),
    'Product Adding inward opens the destination stock module by item type' =>
        str_contains($controllerSource, '$redirectUrl = \'/m/stocks\';')
        && str_contains($controllerSource, "'/m/purchase_stocks' .")
        && str_contains($controllerSource, '$targetTab === \'finished_goods\' ? \'/m/stocks\''),
    'Printed labels are only required for inwarding and do not set stock quantity' =>
        str_contains($routesSource, 'addProductAddingToStock')
        && str_contains($controllerSource, 'assertProductAddingLabelsGenerated($itemId)')
        && str_contains($controllerSource, "lj.source_module = 'product_adding'")
        && str_contains($controllerSource, 'lj.id > ?')
        && str_contains($controllerSource, 'if ($generatedQty < 1)')
        && str_contains($controllerSource, '$stockQuantities[(int)$selectedItem[\'id\']] = (float)$selectedItem[\'qty\'];')
        && str_contains($controllerSource, 'saveInwardQuantity($selectedItem, $stockQuantity, Auth::id())')
        && str_contains($controllerSource, 'already been added to stock')
        && str_contains($stockOverrideSource, 'public static function saveInwardQuantity(array $item, float $quantity, ?int $userId)')
        && str_contains($stockOverrideSource, 'SET qty = ?, total_value = ?, updated_by = ?'),
    'Previously inwarded short quantities can be corrected with an audit trail' =>
        str_contains($routesSource, '/m/product_adding/correct_stock_quantity')
        && str_contains($routesSource, 'correctProductAddingStockQuantity')
        && str_contains($controllerSource, "ref_module IN ('Purchase Inward Item', 'Product Adding Stock Correction')")
        && str_contains($controllerSource, "'Product Adding Stock Correction'")
        && str_contains($controllerSource, '$missingQty = round($purchaseQty - $ledgerQty, 2);')
        && str_contains($controllerSource, 'StockService::move(')
        && str_contains($controllerSource, "'CORRECT_STOCK_QUANTITY'")
        && str_contains($controllerSource, 'AuditLog::logOrThrow')
        && str_contains($controllerSource, 'FOR UPDATE')
        && str_contains($productAddingSource, 'AS finished_goods_inward_qty')
        && str_contains($formSource, '$needsStockCorrection')
        && str_contains($formSource, 'productAddingStockCorrectionForm'),
    'Purchase stock views and roll registry use the inward quantity override' =>
        str_contains($purchaseStockSource, 'PurchaseStockItemOverrideService::applyToItems($rolls)')
        && str_contains($rollRegistrySource, 'COALESCE(stock_override.qty, pbi.qty)')
        && str_contains($formSource, '$savedItem[\'labels_complete\']'),
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
    'Quick Fill applies the selected category to every variant and keeps variant names product-only' =>
        str_contains($formSource, "firstRow.querySelector('.fg-variant-category').value = categoryId;")
        && str_contains($formSource, "const productName = card.querySelector('.fg-input-product')?.value.trim() || '';")
        && str_contains($formSource, 'variantName.value = productName.slice(0, 150);')
        && str_contains($purchaseSource, '$variantName = $styleName;'),
    'Product Adding ensures valuation columns before stock ledger writes' =>
        str_contains($controllerSource, 'StockService::ensureLedgerValuationSchema();')
        && str_contains($stockServiceSource, 'SHOW COLUMNS FROM `stock_ledger`')
        && str_contains($stockServiceSource, 'ADD COLUMN `{$column}`'),
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
    'Bulk Add Stock uses selected lines and submits each purchased quantity' =>
        str_contains($formSource, 'id="productAddingBulkStockButton"')
        && str_contains($formSource, 'id="productAddingBulkStockForm"')
        && str_contains($formSource, 'form="productAddingBulkStockForm"')
        && str_contains($formSource, "input.dataset.labelsComplete === '1'")
        && str_contains($formSource, 'const eligibleItems = selectedItems.filter(function (input)')
        && str_contains($formSource, 'bulkStockForm.appendChild(itemId)')
        && str_contains($controllerSource, 'if ($isProductAddingRequest && $selectedItemIds)')
        && str_contains($controllerSource, '$this->assertProductAddingLabelsGenerated((int)$selectedItem[\'id\'])')
        && str_contains($controllerSource, '$stockQuantities[(int)$selectedItem[\'id\']] = (float)$selectedItem[\'qty\'];')
        && str_contains($controllerSource, '$this->inwardProductAddingFinishedGood($concernId, $selectedItem, $billNo)')
        && str_contains($controllerSource, 'RollRegistryService::syncSpecs($concernId)')
        && str_contains($controllerSource, "section_type'] === 'garment_other'")
        && str_contains($formSource, 'data-purchase-qty="<?= htmlspecialchars((string)$purchaseQty) ?>"')
        && str_contains($formSource, 'Stock qty: <?= htmlspecialchars((string)$purchaseQty)')
        && str_contains($formSource, 'checkbox.dataset.purchaseQty'),
    'Bulk checkboxes remain visible after printing and Select all includes pending lines' =>
        str_contains($formSource, 'product-adding-bulk-checkbox')
        && str_contains($formSource, 'id="productAddingSelectAllLines"')
        && str_contains($formSource, 'if (input.dataset.inStock !== \'1\')')
        && str_contains($formSource, 'input.checked = true;'),
];

$failed = false;
foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . "\n";
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
