<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../app/controllers/ProductionController.php');
$createView = file_get_contents(__DIR__ . '/../app/views/concern/cutting_create.php');
$editView = file_get_contents(__DIR__ . '/../app/views/concern/cutting_edit.php');

$methodStart = strpos($controller, 'public function apiQuickAddCuttingMaster(): void');
$methodEnd = strpos($controller, 'public function storeCuttingBatch(): void', $methodStart);
$method = substr($controller, $methodStart, $methodEnd - $methodStart);

$checks = [
    'Cutting master concern is no longer guessed as concern ID 1' =>
        !str_contains($method, 'Auth::concernId() ?: 1')
        && str_contains($method, 'SELECT concern_id FROM production_programs WHERE id = ?'),
    'All-concerns requests derive the concern from a real production program' =>
        str_contains($method, 'if (!$cid) {')
        && str_contains($method, '$cid = (int)$program[\'concern_id\'];')
        && str_contains($method, "status = 'active'"),
    'Create and edit forms supply their production program context' =>
        str_contains($createView, "formData.append('program_id', document.getElementById('programSelect').value)")
        && str_contains($editView, "formData.append('program_id', "),
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
