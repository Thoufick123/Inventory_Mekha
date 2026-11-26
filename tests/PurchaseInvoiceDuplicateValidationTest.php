<?php

declare(strict_types=1);

$controllerSource = file_get_contents(__DIR__ . '/../app/controllers/PurchaseController.php');
$formSource = file_get_contents(__DIR__ . '/../app/views/concern/purchase_billing_create.php');
$directorySource = file_get_contents(__DIR__ . '/../app/views/concern/purchase_billing.php');
$sourceService = file_get_contents(__DIR__ . '/../app/services/PurchaseBillSourceService.php');
$schemaSource = file_get_contents(__DIR__ . '/../database/schema.sql');
$migrationSource = file_get_contents(__DIR__ . '/../database/migrate_all_tables.php');

$checks = [
    'Invoice number duplicate check is scoped by concern and supplier' =>
        str_contains($controllerSource, 'private function supplierInvoiceNumberExists')
        && str_contains($controllerSource, 'WHERE concern_id = ? AND supplier_id = ?')
        && str_contains($controllerSource, 'LOWER(TRIM(supplier_invoice_no)) = LOWER(TRIM(?))'),
    'Creating bills rejects an existing supplier invoice number' =>
        str_contains($controllerSource, '$this->supplierInvoiceNumberExists($cid, $supplierId, $supplierInvoiceNo, null, \'purchase_billing\')')
        && str_contains($controllerSource, 'This supplier invoice number has already been recorded for the selected supplier.'),
    'Editing bills excludes the current bill from duplicate checks' =>
        str_contains($controllerSource, '$this->supplierInvoiceNumberExists($cid, $supplierId, $supplierInvoiceNo, $billId)')
        && str_contains($controllerSource, '$sql .= \' AND id != ?\''),
    'Supplier is optional in Product Adding but remains required in Purchase Billing' =>
        str_contains($formSource, '$supplierIsOptional = $isProductAddingForm;')
        && str_contains($formSource, '<?= $supplierIsOptional ? \'\' : \'required\' ?>')
        && str_contains($controllerSource, 'if (!$isProductAddingInvoice && $supplierId === null)'),
    'Purchase bills support saving an optional supplier' =>
        str_contains($schemaSource, '`supplier_id` int DEFAULT NULL,')
        && str_contains($sourceService, 'ALTER TABLE purchase_bills MODIFY supplier_id INT NULL')
        && str_contains($migrationSource, 'MODIFY COLUMN `supplier_id` INT NULL'),
    'Invoice verification handles non-JSON server responses without a parse error' =>
        str_contains($formSource, 'const responseText = await response.text();')
        && str_contains($formSource, 'Invoice pre-check is unavailable; duplicates will still be checked when saving.'),
    'The entered supplier invoice number remains visible in the billing list' =>
        str_contains($formSource, 'name="supplier_invoice_no"')
        && str_contains($directorySource, '<th>Supplier Inv #</th>')
        && str_contains($directorySource, '$b[\'supplier_invoice_no\']'),
];

$failed = false;
foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . "\n";
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
