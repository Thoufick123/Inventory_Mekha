<?php

$controller = file_get_contents(__DIR__ . '/../app/controllers/ProductionStockRequestController.php');
$storeStart = strpos($controller, 'public function store(): void {');
$storeEnd = strpos($controller, 'public function view(', $storeStart);
$storeMethod = $storeStart !== false && $storeEnd !== false
    ? substr($controller, $storeStart, $storeEnd - $storeStart)
    : '';

$checks = [
    'Stock requisitions are persisted to the production request tables' =>
        strpos($storeMethod, 'INSERT INTO production_stock_requests') !== false
        && strpos($storeMethod, 'INSERT INTO production_stock_request_items') !== false,
    'Stock requisitions do not create a customer request with a hard-coded customer ID' =>
        strpos($storeMethod, 'INSERT INTO customer_requests') === false
        && strpos($storeMethod, 'VALUES (?, ?, 1,') === false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
