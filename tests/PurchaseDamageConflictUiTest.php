<?php
require_once __DIR__ . '/../app/core/Autoloader.php';

$isSuperAdmin = true;
$selectedCid = 1;
$allConcerns = [['id' => 1, 'name' => 'Test Concern']];
$csrfToken = 'purchase-damage-test-token';
$damageReasons = [];
$canAddDamage = true;
$canEditDamage = true;
$canDeleteDamage = true;
$damages = [];

ob_start();
include __DIR__ . '/../app/views/concern/purchase_damage.php';
$html = ob_get_clean();

$checks = [
    'Purchase damage page renders without merge-conflict markers' =>
        strpos($html, '<<<<<<<') === false
        && strpos($html, '=======') === false
        && strpos($html, '>>>>>>>') === false,
    'Purchase damage scanner, export card, and history table render together' =>
        strpos($html, 'Scan Purchase Stock Label') !== false
        && strpos($html, 'Export Purchase Damage Records') !== false
        && strpos($html, 'Quarantine history') !== false
        && strpos($html, 'Damage No.') !== false,
    'Obsolete duplicate damage row is not rendered' =>
        strpos($html, 'openViewPurchaseDamageModal') === false
        && strpos($html, '$d[\'qty\']') === false,
    'Selected concern is carried to the export endpoint' =>
        strpos($html, 'concern_id=1') !== false,
];

$failed = false;
foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . "\n";
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
