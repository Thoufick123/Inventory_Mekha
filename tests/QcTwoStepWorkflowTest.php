<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../app/controllers/ProductionController.php');
$createView = file_get_contents(__DIR__ . '/../app/views/concern/qc_create.php');
$inspectView = file_get_contents(__DIR__ . '/../app/views/concern/qc_inspect.php');
$routes = file_get_contents(__DIR__ . '/../app/routes.php');

$storeStart = strpos($controller, 'public function storeQc(): void');
$saveStart = strpos($controller, 'public function saveQcInspection(): void', $storeStart);
$completeStart = strpos($controller, 'public function completeQcInspection(): void', $saveStart);
$viewStart = strpos($controller, 'public function viewQc(): void', $completeStart);
$storeMethod = substr($controller, $storeStart, $saveStart - $storeStart);
$saveMethod = substr($controller, $saveStart, $completeStart - $saveStart);
$completeMethod = substr($controller, $completeStart, $viewStart - $completeStart);

$checks = [
    'QC creation only initializes an inspection and does not directly inward stock' =>
        str_contains($storeMethod, '$submitAction !== \'save_and_inspect\'')
        && str_contains($storeMethod, '$pVerdict = \'PENDING\'')
        && !str_contains($storeMethod, 'inwardVariantToStock'),
    'The scanner only offers a save action that returns to QC review' =>
        str_contains($inspectView, "/production/qc/save-inspection")
        && str_contains($inspectView, 'Save Inspection')
        && !str_contains($inspectView, 'complete-inspection'),
    'Initial QC form does not allow manually entered verdict quantities or direct inward' =>
        !str_contains($createView, 'name="items[<?= $idx ?>][passed_qty]"')
        && !str_contains($createView, 'name="items[<?= $idx ?>][rejected_qty]"')
        && !str_contains($createView, 'name="items[<?= $idx ?>][rework_qty]"')
        && !str_contains($createView, 'direct_inward'),
    'Scanner save requires every piece to have a verdict and returns to the review page' =>
        str_contains($saveMethod, '($counts[\'pending\'] ?? 0) > 0')
        && str_contains($saveMethod, '/production/qc/create?stitching_batch_id='),
    'The review-page add action reaches guarded stock inward' =>
        str_contains($createView, "submitQc('add_to_stock')")
        && str_contains($completeMethod, 'Every garment must have a saved verdict')
        && str_contains($completeMethod, 'FOR UPDATE')
        && str_contains($completeMethod, 'inwardVariantToStock'),
    'Save and add-to-stock actions have explicit QC routes' =>
        str_contains($routes, "'/production/qc/save-inspection'")
        && str_contains($routes, "'/production/qc/complete-inspection'"),
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
