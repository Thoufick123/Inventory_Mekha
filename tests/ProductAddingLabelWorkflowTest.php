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
        str_contains($controllerSource, '$copies = (int)ceil((float)$item[\'qty\'])')
        && str_contains($controllerSource, '(int)$item[\'id\'],')
        && str_contains($formSource, 'name="item_ids[]"'),
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
        && str_contains($formSource, 'generateProductAddingVariantRows')
        && str_contains($formSource, 'quickFillProductAddingVariants')
        && str_contains($formSource, 'Quick fill'),
];

$failed = false;
foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . "\n";
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
