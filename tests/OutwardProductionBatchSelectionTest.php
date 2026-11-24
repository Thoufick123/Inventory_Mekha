<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../app/controllers/OutwardController.php');
$routes = file_get_contents(__DIR__ . '/../app/routes.php');
$modules = file_get_contents(__DIR__ . '/../app/core/ModuleRegistry.php');
$outwardView = file_get_contents(__DIR__ . '/../app/views/concern/outward.php');
$labelView = file_get_contents(__DIR__ . '/../app/views/concern/label_generation.php');

$checks = [
    'Standalone batch CRUD routes are removed while batch print documents remain available' =>
        !str_contains($routes, "'/m/batch")
        && !str_contains($routes, "'/production/batches/store'")
        && str_contains($routes, '/production/batches/challan/{id}')
        && str_contains($routes, '/production/batches/label/{id}'),
    'Batch module is absent from navigation even if still present in stored modules' =>
        !str_contains($modules, '\'code\' => \'batch\'')
        && str_contains($modules, '$code !== \'batch\''),
    'Outward batch list is scoped to approved batches for the source concern with stock available' =>
        str_contains($controller, "UPPER(b.status) = 'APPROVED'")
        && str_contains($controller, 'WHERE b.concern_id = ?')
        && str_contains($controller, 'AS dispatch_qty')
        && str_contains($controller, 'SELECT SUM(bi2.qty)')
        && str_contains($controller, 'OR bi.concern_id <> b.concern_id')
        && str_contains($controller, 's.location IN (\'Main Store\', \'Main Store (WH-01)\')'),
    'Outward verifies the selected batch and its stock rows transactionally before dispatch' =>
        str_contains($controller, "AND UPPER(status) = 'APPROVED'")
        && str_contains($controller, 'LIMIT 1 FOR UPDATE')
        && str_contains($controller, 'deductMainStoreStock($cid, (int)$bv[\'variant_id\']')
        && str_contains($controller, "'Main Store (WH-01)'"),
    'Outward dropdown displays the remaining stocked quantity and label generation points to Outward' =>
        str_contains($outwardView, 'data-concern=')
        && str_contains($outwardView, 'pcs available')
        && str_contains($labelView, '/production/outward?tab=batch')
        && !str_contains($labelView, "url('/production/batches')"),
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
