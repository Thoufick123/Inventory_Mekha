<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../app/controllers/ProductionController.php');
$cuttingView = file_get_contents(__DIR__ . '/../app/views/concern/cutting_create.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrate_all_tables.php');
$productAdding = file_get_contents(__DIR__ . '/../app/controllers/ProductAddingController.php');

$catalogStart = strpos($controller, 'public function catalogProgram(): void');
$catalogEnd = strpos($controller, 'public function createStyle(): void', $catalogStart);
$catalogMethod = substr($controller, $catalogStart, $catalogEnd - $catalogStart);

$cuttingApiStart = strpos($controller, 'public function apiCuttingProgramDetails(): void');
$cuttingApiEnd = strpos($controller, 'public function ', $cuttingApiStart + 1);
$cuttingApi = substr($controller, $cuttingApiStart, $cuttingApiEnd - $cuttingApiStart);

$checks = [
    'Production Catalog lists only catalogs created for production' =>
        str_contains($catalogMethod, "WHERE c.source_module = 'production_catalog'")
        && str_contains($catalogMethod, "source_module = 'production_catalog'")
        && str_contains($catalogMethod, 'WHERE (pp.catalog_id IS NULL OR c.id IS NOT NULL)'),
    'Production styles retain a distinct source marker' =>
        str_contains($controller, "'production_catalog'")
        && str_contains($productAdding, "'product_adding'"),
    'Catalog schema and migration add the source marker and classify legacy active catalogs' =>
        str_contains($schema, "`source_module` varchar(50) NOT NULL DEFAULT 'production_catalog'")
        && str_contains($migration, "ADD COLUMN `source_module` VARCHAR(50)")
        && str_contains($migration, "WHERE `source_module` = 'production_catalog' AND `status` = 'active'"),
    'Cutting program details validate raw-roll type and concern ownership' =>
        str_contains($cuttingApi, "pbi.section_type = 'rolls'")
        && str_contains($cuttingApi, 'pr.concern_id = ?')
        && str_contains($cuttingApi, 'pbi.concern_id = pr.concern_id')
        && str_contains($cuttingApi, 'pp.catalog_id IS NULL OR c.id IS NOT NULL'),
    'Cutting details avoid non-aggregated GROUP BY and return JSON errors' =>
        !str_contains($cuttingApi, 'GROUP BY pr.id')
        && str_contains($cuttingApi, 'Unable to load program details.')
        && str_contains($cuttingApi, 'http_response_code(500)'),
    'Cutting UI distinguishes concerns and reports API failures instead of generic network errors' =>
        str_contains($cuttingView, '$prog[\'concern_name\']')
        && str_contains($cuttingView, 'Unable to load program details (HTTP')
        && !str_contains($cuttingView, 'Network error while loading program details.'),
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
