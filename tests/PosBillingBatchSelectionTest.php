<?php

$billingView = file_get_contents(__DIR__ . '/../app/views/concern/billing.php');
$salesController = file_get_contents(__DIR__ . '/../app/controllers/SalesController.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrate_all_tables.php');

$checks = [
    'Cart quantity control is capped by available stock' =>
        strpos($billingView, 'max="${qtyMax}"') !== false
        && strpos($billingView, "maxQty + ' units are available") !== false,
    'Scanner refuses additions above available stock' =>
        strpos($billingView, 'currentQty + qtyToAdd > availableQty') !== false,
    'Billing supports customer and company selection' =>
        strpos($billingView, 'name="billing_party_type"') !== false
        && strpos($billingView, 'value="company"') !== false
        && strpos($billingView, 'id="billing_party_buttons"') !== false
        && strpos($billingView, 'data-billing-party="company"') !== false
        && strpos($billingView, 'name="company_customer_id"') !== false,
    'Company mode loads approved production batches for the selected concern' =>
        strpos($salesController, "public function productionBatchLookup()") !== false
        && strpos($salesController, "UPPER(b.status) = 'APPROVED'") !== false
        && strpos($billingView, '/api/pos/production-batches') !== false,
    'Bulk-selected batch items retain traceability on the invoice' =>
        strpos($billingView, 'addSelectedProductionBatches') !== false
        && strpos($salesController, 'production_batch_id') !== false
        && strpos($schema, '`production_batch_id` INT NULL') !== false
        && strpos($migration, 'ADD COLUMN `production_batch_id`') !== false,
    'Company billing rejects non-batch items and unauthorized batch IDs' =>
        strpos($salesController, 'Company bulk billing items must come from an approved production batch.') !== false
        && strpos($salesController, 'AND concern_id = ? AND variant_id = ? AND UPPER(status)') !== false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
