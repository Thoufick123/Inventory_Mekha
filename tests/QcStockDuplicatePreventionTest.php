<?php

require_once __DIR__ . '/../app/core/Autoloader.php';

$failed = false;
$controllerSource = file_get_contents(__DIR__ . '/../app/controllers/ProductionController.php');

$checks = [
    'Stitching no longer credits live stock directly' =>
        str_contains($controllerSource, 'Live stock is credited only after QC passes'),
    'QC creation rejects an existing QC batch' =>
        str_contains($controllerSource, 'already has QC') &&
        str_contains($controllerSource, 'cannot create another stock entry'),
    'QC completion rejects repeated terminal completion' =>
        str_contains($controllerSource, 'has already been completed. Stock is not credited again'),
    'QC inward uses variant-and-QC idempotency guard' =>
        str_contains($controllerSource, 'Idempotency: A QC record is allowed to credit each variant exactly once'),
];

foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . "\n";
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
