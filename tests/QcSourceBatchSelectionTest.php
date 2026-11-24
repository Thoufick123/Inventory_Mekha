<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../app/controllers/ProductionController.php');
$view = file_get_contents(__DIR__ . '/../app/views/concern/qc_create.php');

$createStart = strpos($controller, 'public function createQc(): void');
$storeStart = strpos($controller, 'public function storeQc(): void', $createStart);
$inspectStart = strpos($controller, 'public function inspectQc(): void', $storeStart);
$createMethod = substr($controller, $createStart, $storeStart - $createStart);
$storeMethod = substr($controller, $storeStart, $inspectStart - $storeStart);

$checks = [
    'QC source batches use the active concern or all concerns for Super Admin without assuming concern 1' =>
        !str_contains($createMethod, 'Auth::concernId() ?: 1')
        && str_contains($createMethod, 'if ($cid)')
        && str_contains($createMethod, 'if (!$cid && !Auth::isSuperAdmin())'),
    'The selected batch is always validated against the displayed batch list before loading items' =>
        str_contains($createMethod, '$selectedStitchingId = (int)($selectedBatch[\'id\'] ?? 0);')
        && str_contains($createMethod, 'AND (sl.concern_id = ? OR sl.concern_id IS NULL)'),
    'Source batch options show batch, program, piece count, and variant count' =>
        str_contains($view, 'display_batch_no')
        && str_contains($view, 'program_no')
        && str_contains($view, 'total_pieces')
        && str_contains($view, 'variant_count'),
    'QC initialization no longer displays a destination location selector' =>
        !str_contains($view, 'Destination Warehouse / Location')
        && !str_contains($view, 'name="location"'),
    'QC submission derives concern from the selected batch and fixes stock location to Main Store' =>
        str_contains($storeMethod, '$cid = $authCid ?: (int)$batch[\'concern_id\'];')
        && str_contains($storeMethod, '$location         = \'Main Store\';'),
    'Dynamic QC batch summary uses the real item quantity total' =>
        str_contains($controller, 'AS total_pieces')
        && str_contains($view, 'const totalStitched = Number(batch.total_pieces'),
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
