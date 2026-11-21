<?php

$billingView = file_get_contents(__DIR__ . '/../app/views/concern/billing.php');
$salesController = file_get_contents(__DIR__ . '/../app/controllers/SalesController.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrate_all_tables.php');
$liveUpdate = file_get_contents(__DIR__ . '/../database/live_db_update.sql');

$checks = [
    'Cart quantity control is capped by available stock' =>
        strpos($billingView, 'max="${qtyMax}"') !== false
        && strpos($billingView, "maxQty + ' units are available") !== false,
    'Scanner refuses additions above available stock' =>
        strpos($billingView, 'currentQty + qtyToAdd > availableQty') !== false,
    'Billing supports customer and manual company buyer details without a company dropdown' =>
        strpos($billingView, 'name="billing_party_type"') !== false
        && strpos($billingView, 'value="company"') !== false
        && strpos($billingView, 'id="billing_party_buttons"') !== false
        && strpos($billingView, 'data-billing-party="company"') !== false
        && strpos($billingView, 'name="company_customer_id"') === false
        && strpos($billingView, 'name="company_name"') !== false
        && strpos($billingView, 'name="company_address"') !== false
        && strpos($billingView, 'name="company_phone"') !== false
        && strpos($billingView, 'name="company_email"') !== false
        && strpos($billingView, 'name="company_gstin"') !== false,
    'Company billing removes the approved batch picker and uses shared stock scanning' =>
        strpos($billingView, 'company_batch_wrap') === false
        && strpos($billingView, 'APPROVED PRODUCTION BATCHES') === false
        && strpos($billingView, '/api/pos/production-batches') === false
        && strpos($billingView, 'posScannerCard.classList.remove(\'d-none\')') !== false
        && strpos($billingView, 'currentQty + qtyToAdd > availableQty') !== false,
    'Billed-by staff and GST controls are available for both billing party types' =>
        strpos($billingView, 'id="billed_by_staff"') !== false
        && strpos($billingView, 'name="gst_type"') !== false
        && strpos($billingView, 'name="gst_price_mode"') !== false
        && strpos($billingView, 'GST MODE') !== false,
    'Company details resolve or create a customer and remain snapshotted on the invoice' =>
        strpos($salesController, 'WHERE concern_id = ? AND status = \'active\' AND gstin = ?') !== false
        && strpos($salesController, 'INSERT INTO customers (concern_id, code, name, phone, email, gstin, address, city, status, created_at)') !== false
        && strpos($schema, '`production_batch_id` INT NULL') !== false
        && strpos($salesController, 'billing_company_name, billing_company_address, billing_company_phone, billing_company_email, billing_company_gstin') !== false,
    'Explicit production batch IDs remain concern-scoped and validated when supplied' =>
        strpos($salesController, 'production_batch_id') !== false
        && strpos($salesController, 'AND concern_id = ? AND variant_id = ? AND UPPER(status)') !== false
        && strpos($salesController, 'Company bulk billing items must come from an approved production batch.') === false,
    'Company billing collects and persists editable buyer details' =>
        strpos($billingView, 'name="company_name"') !== false
        && strpos($billingView, 'name="company_address"') !== false
        && strpos($billingView, 'name="company_phone"') !== false
        && strpos($billingView, 'name="company_email"') !== false
        && strpos($billingView, 'name="company_gstin"') !== false
        && strpos($salesController, 'billing_company_name, billing_company_address, billing_company_phone, billing_company_email, billing_company_gstin') !== false
        && strpos($schema, '`billing_company_gstin` VARCHAR(15) NULL') !== false
        && strpos($liveUpdate, "AddColSafe('sales_invoices', 'billing_company_gstin'") !== false,
    'Company mode keeps the shared scanner and adds scanned variants normally' =>
        strpos($billingView, "posScannerCard.classList.remove('d-none')") !== false
        && strpos($billingView, 'const existingIndex = cart.findIndex(item => String(item.id) === String(v.id) && !item.batch_id)') !== false,
    'POS supports invoice dates and direct-bill classification with migration support' =>
        strpos($billingView, 'name="invoice_date"') !== false
        && strpos($billingView, 'name="is_direct_bill"') !== false
        && strpos($salesController, 'payment_status, is_direct_bill, billing_company_name') !== false
        && strpos($schema, '`is_direct_bill` TINYINT(1) NOT NULL DEFAULT 0') !== false
        && strpos($schema, '`billing_company_gstin` VARCHAR(15) NULL') !== false
        && strpos($migration, "'is_direct_bill' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `paid_amount`'") !== false
        && strpos($liveUpdate, "AddColSafe('sales_invoices', 'is_direct_bill'") !== false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
