<?php
require_once __DIR__ . '/../app/core/Autoloader.php';

$isSuperAdmin = false;
$canAddDamage = true;
$canEditDamage = true;
$canDeleteDamage = true;
$selectedCid = 1;
$csrfToken = 'concern-damage-test-token';
$allConcerns = [];
$search = '';
$sourceType = '';
$selectedLocation = '';
$status = '';
$metrics = [
    'damaged_qty' => 8,
    'rework_qty' => 0,
    'estimated_loss' => 670,
    'recovered_qty' => 0,
    'recovered_value' => 0,
];
$locations = ['Main Store'];
$damages = [[
    'id' => 1,
    'damage_no' => 'DMG-20261113-0001',
    'variant_name' => 'Red Shirt',
    'sku_code' => 'SKU-RED-M',
    'size' => 'M',
    'category_name' => 'Shirts',
    'location' => 'Main Store',
    'qty' => 2,
    'unit_price' => 335,
    'mrp' => 400,
    'loss_amount' => 0,
    'reason' => 'Handling fall',
    'user_name' => 'Staff',
    'created_at' => '2026-11-13 12:00:00',
    'current_status' => 'LOGGED',
    'has_quarantine' => 1,
    'origin_module' => 'Purchase Inward Item',
    'origin_ref_id' => 17,
]];

ob_start();
include __DIR__ . '/../app/views/concern/concern_damage.php';
$listHtml = ob_get_clean();

$checks = [
    'Damage list mirrors the dashboard and case table structure' =>
        strpos($listHtml, 'Damaged Units (Quarantine)') !== false
        && strpos($listHtml, 'Factory Rework Units') !== false
        && strpos($listHtml, 'Estimated Loss Valuation') !== false
        && strpos($listHtml, 'Salvaged / Repaired') !== false
        && strpos($listHtml, 'Source &amp; Origin Batch') !== false
        && strpos($listHtml, 'Loss / Valuation') !== false,
    'Damage list links to the dedicated scan-entry page' =>
        strpos($listHtml, '/m/concern_damage/new') !== false
        && strpos($listHtml, 'Scan &amp; Log Damage / Rework') !== false,
];

$canAddDamage = false;
$canEditDamage = false;
$canDeleteDamage = false;
ob_start();
include __DIR__ . '/../app/views/concern/concern_damage.php';
$restrictedListHtml = ob_get_clean();
$checks['Damage list hides actions the user cannot access'] =
    strpos($restrictedListHtml, 'Scan &amp; Log Damage / Rework') === false
    && strpos($restrictedListHtml, 'title="Edit reason"') === false
    && strpos($restrictedListHtml, 'title="Resolve / rework"') === false
    && strpos($restrictedListHtml, '/m/concern_damage/delete') === false
    && strpos($restrictedListHtml, 'openViewConcernDamageModal') !== false;

$locations = ['Main Store', 'WAREHOUSE:1:AVAILABLE'];
ob_start();
include __DIR__ . '/../app/views/concern/concern_damage_entry.php';
$entryHtml = ob_get_clean();
$checks['Damage entry provides location-scoped QR scanning and a queue'] =
    strpos($entryHtml, 'Live Concern QR Scanner') !== false
    && strpos($entryHtml, 'Source Warehouse / Location') !== false
    && strpos($entryHtml, 'data-scan-url=') !== false
    && strpos($entryHtml, 'Damaged / Rework Items Queue') !== false
    && strpos($entryHtml, 'Damage Reason *') !== false
    && strpos($entryHtml, 'Available') !== false
    && strpos($entryHtml, 'scanned_items') !== false;

$locationMethod = new ReflectionMethod(App\Services\DamageService::class, 'locationWithState');
$locationMethod->setAccessible(true);
$checks['Quarantine location mapping preserves the exact available location'] =
    $locationMethod->invoke(null, 'Main Store', 'DAMAGED') === 'Main Store:DAMAGED'
    && $locationMethod->invoke(null, 'Main Store', 'AVAILABLE') === 'Main Store'
    && $locationMethod->invoke(null, 'WAREHOUSE:1:AVAILABLE', 'DAMAGED') === 'WAREHOUSE:1:DAMAGED'
    && $locationMethod->invoke(null, 'WAREHOUSE:1:AVAILABLE', 'AVAILABLE') === 'WAREHOUSE:1:AVAILABLE';

$failed = false;
foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . "\n";
    $failed = $failed || !$passed;
}
exit($failed ? 1 : 0);
