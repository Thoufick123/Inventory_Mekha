<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../app/controllers/ProductionController.php');
$routes = file_get_contents(__DIR__ . '/../app/routes.php');
$view = file_get_contents(__DIR__ . '/../app/views/concern/stitching_create.php');

$apiStart = strpos($controller, 'public function apiCuttingDetailsForStitching(): void');
$apiEnd = strpos($controller, 'public function apiQuickAddTailor(): void', $apiStart);
$apiMethod = substr($controller, $apiStart, $apiEnd - $apiStart);

$checks = [
    'Stitching create can load its details API with add permission' =>
        str_contains($routes, "'/api/stitching/cutting-details', [ProductionController::class, 'apiCuttingDetailsForStitching'], 'stitching_wastage', 'add'"),
    'Cutting item query avoids invalid non-aggregated grouping' =>
        !str_contains($apiMethod, 'GROUP BY ci.id'),
    'Stitching details are returned as JSON when server queries fail' =>
        str_contains($apiMethod, 'catch (\\Throwable $e)')
        && str_contains($apiMethod, 'http_response_code(500)')
        && str_contains($apiMethod, 'Unable to load cutting details. Please try again.'),
    'Catalog fallback is production-only and concern-scoped' =>
        str_contains($apiMethod, "c_roll.source_module = 'production_catalog'")
        && str_contains($apiMethod, "c_var.source_module = 'production_catalog'")
        && str_contains($apiMethod, "c1.source_module = 'production_catalog'")
        && str_contains($apiMethod, 'c1.concern_id = pr.concern_id'),
    'Stitching UI displays API errors instead of hiding them as network errors' =>
        str_contains($view, 'Unable to load cutting details (HTTP')
        && !str_contains($view, 'Network or server error while loading cutting details.'),
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
