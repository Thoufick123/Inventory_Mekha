<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../app/controllers/LabelController.php');
$view = file_get_contents(__DIR__ . '/../app/views/concern/label_generation.php');

$checks = [
    'Stitching item joins resolve one preferred catalog instead of multiplying rows across matching catalogs' =>
        substr_count($controller, 'LEFT JOIN catalogs cat ON cat.id = COALESCE(si.catalog_id, pr.catalog_id, pp.catalog_id)') === 5
        && !str_contains($controller, 'si.catalog_id = cat.id OR pp.catalog_id = cat.id'),
    'Production label batch counts and quantities are based on the item rows returned by the query' =>
        str_contains($controller, '$productionBatches[$bId][\'items\'][] = $pItem;')
        && str_contains($controller, '$productionBatches[$bId][\'total_pieces\'] += $pcs;')
        && str_contains($view, 'count($batch[\'items\'])'),
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
