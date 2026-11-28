<?php

$adminController = file_get_contents(__DIR__ . '/../app/controllers/AdminController.php');
$updateConcernStart = strpos($adminController, 'public function updateConcern(): void {');
$updateConcernEnd = strpos($adminController, 'public function ', $updateConcernStart + 1);
$updateConcernMethod = substr($adminController, $updateConcernStart, $updateConcernEnd - $updateConcernStart);

$checks = [
    'Concern module updates grant selected modules to all concern administrator roles' =>
        strpos($updateConcernMethod, "WHERE concern_id = ? AND (name LIKE '%Administrator%' OR name LIKE '%Admin%')") !== false
        && strpos($updateConcernMethod, 'foreach ($adminRoles as $adminRole)') !== false
        && strpos($updateConcernMethod, 'foreach ($pickedModuleIds as $modId)') !== false,
    'Concern module updates restore existing administrator role permissions' =>
        strpos($updateConcernMethod, 'ON DUPLICATE KEY UPDATE can_view = 1, can_add = 1, can_edit = 1, can_delete = 1') !== false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
