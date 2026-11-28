<?php

declare(strict_types=1);

$billingView = file_get_contents(__DIR__ . '/../app/views/concern/billing.php');
$salesController = file_get_contents(__DIR__ . '/../app/controllers/SalesController.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrate_all_tables.php');

$checks = [
    'POS cart stores every distinct code scanned for a variant' =>
        str_contains($billingView, 'scanned_codes: [query]')
        && str_contains($billingView, '!cart[existingIndex].scanned_codes.includes(query)')
        && str_contains($billingView, 'cart[existingIndex].scanned_codes.push(query)'),
    'POS cart displays scanned codes as escaped item details' =>
        str_contains($billingView, 'Scanned code: ${escapeHtml(scannedCodes.join(\', \'))}'),
    'POS cart and scan confirmation hide the internal product code' =>
        !str_contains($billingView, '${escapeHtml(item.product_code)} &bull; Size:')
        && !str_contains($billingView, "'Added: ' + v.product_code")
        && str_contains($billingView, "'Added: ' + v.name + ' (+' + qtyToAdd + ')'"),
    'Checkout persists scanned codes with each invoice line' =>
        str_contains($salesController, '\'scanned_codes\' => $scannedCodesJson')
        && str_contains($salesController, 'total_price, scanned_codes)')
        && str_contains($salesController, "\$item['scanned_codes']"),
    'Both invoice formats display saved scanned codes' =>
        substr_count($billingView, 'Scanned code: <?= htmlspecialchars($it[\'scanned_code_display\']) ?>') === 2
        && str_contains($salesController, "['scanned_code_display'] ="),
    'Fresh schema and migration include invoice scanned-code storage' =>
        str_contains($schema, '`scanned_codes` text DEFAULT NULL')
        && str_contains($migration, "ADD COLUMN `scanned_codes` TEXT NULL AFTER `production_batch_id`"),
];

$failed = false;
foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
