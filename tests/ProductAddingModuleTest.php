<?php

require_once __DIR__ . '/../app/core/Autoloader.php';

$failed = false;

$routeSource = file_get_contents(__DIR__ . '/../app/routes.php');
$registrySource = file_get_contents(__DIR__ . '/../app/core/ModuleRegistry.php');
$controllerSource = file_get_contents(__DIR__ . '/../app/controllers/ProductAddingController.php');
$viewSource = file_get_contents(__DIR__ . '/../app/views/concern/product_adding.php');
$liveSql = file_get_contents(__DIR__ . '/../database/live_db_update.sql');

$checks = [
    'Product Adding route is registered' => str_contains($routeSource, "'/m/product_adding'"),
    'Product Adding store route is registered' => str_contains($routeSource, "'/m/product_adding/store'"),
    'Product Adding module is in the registry' => str_contains($registrySource, "'product_adding'"),
    'Product Adding controller exists' => str_contains($controllerSource, 'class ProductAddingController'),
    'Product Adding view includes label generation' => str_contains($viewSource, 'Generate Labels'),
    'Product Adding store credits opening stock' => str_contains($controllerSource, "StockService::move"),
    'Live SQL registers Product Adding module' => str_contains($liveSql, "'product_adding'"),
];

foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . "\n";
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
