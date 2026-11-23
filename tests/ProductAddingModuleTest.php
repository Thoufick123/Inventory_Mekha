<?php

require_once __DIR__ . '/../app/core/Autoloader.php';

$failed = false;

$routeSource = file_get_contents(__DIR__ . '/../app/routes.php');
$registrySource = file_get_contents(__DIR__ . '/../app/core/ModuleRegistry.php');
$controllerSource = file_get_contents(__DIR__ . '/../app/controllers/ProductAddingController.php');
$purchaseControllerSource = file_get_contents(__DIR__ . '/../app/controllers/PurchaseController.php');
$viewSource = file_get_contents(__DIR__ . '/../app/views/concern/purchase_billing_create.php');
$listViewSource = file_get_contents(__DIR__ . '/../app/views/concern/product_adding_list.php');
$liveSql = file_get_contents(__DIR__ . '/../database/live_db_update.sql');

$checks = [
    'Product Adding route is registered' => str_contains($routeSource, "'/m/product_adding'"),
    'Product Adding opens a bill list with overall purchase metrics' => str_contains($controllerSource, 'private function renderBillList')
        && str_contains($controllerSource, "WHERE pb.source_module = 'product_adding'")
        && str_contains($listViewSource, 'Product Purchase Bills')
        && str_contains($listViewSource, 'Net Purchases')
        && str_contains($listViewSource, 'Pending Stock Bills')
        && str_contains($listViewSource, 'Labels &amp; Stock'),
    'Create Product Purchase opens the existing Product Adding form' => str_contains($routeSource, "'/m/product_adding/create', [ProductAddingController::class, 'create']")
        && str_contains($listViewSource, 'Create Product Purchase')
        && str_contains($controllerSource, '$this->renderPurchaseForm();'),
    'Product Adding store route is registered' => str_contains($routeSource, "'/m/product_adding/store'"),
    'Product Adding supports secure quick category hierarchy creation' => str_contains($routeSource, "'/m/product_adding/categories/store'")
        && str_contains($controllerSource, 'function storeHierarchyAjax')
        && str_contains($controllerSource, '$this->validateCsrf()')
        && str_contains($viewSource, 'quickAddFinishedHierarchy'),
    'Product Adding supports secure quick attribute creation and values' => str_contains($routeSource, "'/m/product_adding/attributes/store'")
        && str_contains($controllerSource, 'function storeAttributeAjax')
        && str_contains($controllerSource, 'INSERT INTO product_attributes')
        && str_contains($controllerSource, '$this->validateCsrf()')
        && str_contains($controllerSource, 'reservedTypes')
        && str_contains($viewSource, 'quickAttributeSaveUrl')
        && str_contains($viewSource, 'promptAddProductAddingAttribute')
        && str_contains($viewSource, 'handleProductAddingAttributeChange')
        && str_contains($viewSource, 'saveProductAddingAttributeValue')
        && str_contains($viewSource, '+ Add New Value...'),
    'Product Adding has a scoped quick-supplier save route' => str_contains($routeSource, "'/m/product_adding/suppliers/store'")
        && str_contains($routeSource, 'storeSupplierAjax'),
    'Product Adding invoices use the Purchase Billing persistence path' => str_contains($routeSource, "'/m/product_adding/store', [PurchaseController::class, 'storeBill']")
        && str_contains($purchaseControllerSource, 'INSERT INTO purchase_bills')
        && str_contains($purchaseControllerSource, 'INSERT INTO purchase_bill_items')
        && str_contains($purchaseControllerSource, 'FROM purchase_bills pb'),
    'Quick supplier creation writes to the shared suppliers table' => str_contains($purchaseControllerSource, 'function storeSupplierAjax')
        && str_contains($purchaseControllerSource, 'INSERT INTO suppliers')
        && str_contains($purchaseControllerSource, 'SELECT id FROM suppliers WHERE concern_id = ? AND phone = ?'),
    'Product Adding module is in the registry' => str_contains($registrySource, "'product_adding'"),
    'Product Adding controller exists' => str_contains($controllerSource, 'class ProductAddingController'),
    'Product Adding uses the complete Purchase Billing replica' => str_contains($viewSource, 'Purchase & Supplier Details')
        && str_contains($viewSource, 'Individual Fabric Rolls')
        && str_contains($viewSource, 'Other Garment Supplies')
        && str_contains($viewSource, 'Finished Goods')
        && str_contains($controllerSource, "'allowQuickSupplier'")
        && str_contains($viewSource, 'Save &amp; Select Supplier')
        && str_contains($viewSource, 'supplierSelect.add(option)')
        && str_contains($viewSource, 'Finished Goods Variant Matrix')
        && str_contains($viewSource, 'fg-variant-category')
        && str_contains($viewSource, 'fg-variant-subcategory')
        && str_contains($viewSource, 'fg-variant-sub-subcategory')
        && str_contains($viewSource, 'fg-variant-gst-rate')
        && str_contains($viewSource, 'readonly aria-label="GST rate from HSN code"')
        && !str_contains($viewSource, 'VARIANT SKU CODE')
        && str_contains($viewSource, 'fg-variant-matrix-scroll')
        && str_contains($viewSource, 'finished_goods[${cardId}][variants][${rowIndex}]')
        && str_contains($viewSource, 'fg-variant-gst-rate')
        && str_contains($viewSource, 'fg-input-selling'),
    'Product Adding enforces supplier cost below selling price below MRP' => str_contains($viewSource, 'Supplier cost must be lower than selling price.')
        && str_contains($viewSource, 'MRP must be greater than selling price.')
        && str_contains($purchaseControllerSource, 'if ($supplierCost <= 0 || $sellingPrice <= $supplierCost || $mrp <= $sellingPrice)')
        && str_contains($purchaseControllerSource, 'Supplier Cost < Selling Price < MRP.'),
    'Product Adding always creates new styles without existing-catalog actions' => str_contains($viewSource, 'initializeProductAddingFinishedRow')
        && str_contains($viewSource, 'Style code is generated automatically.')
        && !str_contains($viewSource, 'finished_goods[${id}][style_code]')
        && str_contains($purchaseControllerSource, "random_bytes(4)")
        && !str_contains($viewSource, 'Catalog action')
        && !str_contains($viewSource, 'Existing Catalog Variant')
        && !str_contains($controllerSource, '$catalogVariants = DB::fetchAll'),
    'Product Adding creates and links new catalog variants to purchase items' => str_contains($purchaseControllerSource, 'createProductAddingCatalogVariant')
        && str_contains($purchaseControllerSource, '$finishedItem[\'variants\']')
        && str_contains($purchaseControllerSource, 'array_merge($finishedItem, $variantRow)')
        && str_contains($purchaseControllerSource, 'if ($isProductAddingInvoice && is_array($finishedRaw))')
        && str_contains($purchaseControllerSource, 'INSERT INTO catalogs')
        && str_contains($purchaseControllerSource, 'INSERT INTO variants')
        && str_contains($purchaseControllerSource, 'UPDATE purchase_bill_items SET catalog_id = ?'),
    'Product Adding store credits opening stock' => str_contains($controllerSource, "StockService::move"),
    'Live SQL registers Product Adding module' => str_contains($liveSql, "'product_adding'"),
];

foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . "\n";
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
