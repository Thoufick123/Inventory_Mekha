<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../app/controllers/ProductionController.php');
$createView = file_get_contents(__DIR__ . '/../app/views/concern/stitching_create.php');
$editView = file_get_contents(__DIR__ . '/../app/views/concern/stitching_edit.php');

$methodStart = strpos($controller, 'public function apiQuickAddTailor(): void');
$methodEnd = strpos($controller, 'public function storeStitchingBatch(): void', $methodStart);
$method = substr($controller, $methodStart, $methodEnd - $methodStart);

$checks = [
    'Tailor creation no longer assumes concern ID 1' =>
        !str_contains($method, 'Auth::concernId() ?: 1')
        && str_contains($method, 'SELECT concern_id FROM cutting_logs WHERE id = ?'),
    'All-concerns requests derive the concern from a real cutting batch' =>
        str_contains($method, 'if (!$cid) {')
        && str_contains($method, '$cid = (int)$cutting[\'concern_id\'];')
        && str_contains($method, "status = 'active'"),
    'Create and edit forms supply their cutting batch context' =>
        str_contains($createView, "formData.append('cutting_id', document.getElementById('selectCuttingBatch').value)")
        && str_contains($editView, "formData.append('cutting_id', "),
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
