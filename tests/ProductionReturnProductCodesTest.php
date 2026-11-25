<?php

$controller = file_get_contents(__DIR__ . '/../app/controllers/ProductionReturnController.php');
$registryView = file_get_contents(__DIR__ . '/../app/views/production/returns/index.php');
$detailView = file_get_contents(__DIR__ . '/../app/views/production/returns/view.php');
$shopHistoryView = file_get_contents(__DIR__ . '/../app/views/concern/return_exchange.php');

$checks = [
    'RMA registry loads product names and SKU codes for each record' =>
        strpos($controller, 'AS product_codes') !== false
        && strpos($controller, 'JOIN variants v ON v.id = prei.variant_id') !== false
        && strpos($registryView, 'Products / SKU Codes') !== false
        && strpos($registryView, '$r[\'product_codes\']') !== false,
    'Return and exchange item details label each product SKU clearly' =>
        substr_count($detailView, 'SKU: <?= htmlspecialchars($item[\'product_code\']) ?>') === 2,
    'Shop return history shows SKU codes for both returned and exchanged products' =>
        strpos($shopHistoryView, '$r[\'returned_product_code\']') !== false
        && strpos($shopHistoryView, '$r[\'exchange_product_code\']') !== false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
