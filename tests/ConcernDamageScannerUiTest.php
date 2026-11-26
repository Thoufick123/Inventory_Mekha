<?php
require_once __DIR__ . '/../app/core/Autoloader.php';

$isSuperAdmin = false;
$canAddDamage = true;
$canEditDamage = true;
$canDeleteDamage = true;
$selectedCid = 1;
$csrfToken = 'concern-damage-test-token';
$concernName = 'GRM Garments';
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
    'product_code' => 'SKU-RED-M',
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
        && strpos($listHtml, 'Scan &amp; Log Damage / Rework') !== false
        && strpos($listHtml, '/m/concern_damage/outward') !== false,
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

$locations = ['1', 'Main Store', 'WAREHOUSE:1:AVAILABLE'];
ob_start();
include __DIR__ . '/../app/views/concern/concern_damage_entry.php';
$entryHtml = ob_get_clean();
$checks['Damage entry provides concern-scoped QR scanning and a queue without a location selector'] =
    strpos($entryHtml, 'Live Concern QR Scanner') !== false
    && strpos($entryHtml, 'Source Warehouse / Location') === false
    && strpos($entryHtml, 'damageSourceLocation') === false
    && strpos($entryHtml, 'locationSelect') === false
    && strpos($entryHtml, 'name="location"') === false
    && strpos($entryHtml, 'data-scan-url=') !== false
    && strpos($entryHtml, 'Damaged / Rework Items Queue') !== false
    && strpos($entryHtml, 'GRMAPSHRL01') !== false
    && strpos($entryHtml, 'Damage Reason *') !== false
    && strpos($entryHtml, 'Available') !== false
    && strpos($entryHtml, 'scanned_items') !== false
    && strpos($entryHtml, 'Products and available stock are checked within the selected concern.') !== false;
$checks['Concern users see their fixed concern name instead of a concern selector'] =
    strpos($entryHtml, 'GRM Garments') !== false
    && strpos($entryHtml, 'id="entryConcernId"') === false;

$allConcerns = [
    ['id' => 1, 'name' => 'GRM Garments', 'code' => 'GRM'],
    ['id' => 2, 'name' => 'Apex Fashion', 'code' => 'APX'],
];
$outwards = [];
ob_start();
include __DIR__ . '/../app/views/concern/concern_damage_outward.php';
$damageOutwardHtml = ob_get_clean();
$checks['Damage outward scans stock, displays damaged availability, and selects a destination concern'] =
    strpos($damageOutwardHtml, 'Scan Damaged Stock') !== false
    && strpos($damageOutwardHtml, 'Available Damaged Qty') !== false
    && strpos($damageOutwardHtml, 'name="destination_concern_id"') !== false
    && strpos($damageOutwardHtml, 'name="scanned_items_json"') !== false
    && strpos($damageOutwardHtml, '/m/concern_damage/outward/scan') !== false
    && strpos($damageOutwardHtml, '/m/concern_damage/outward/store') !== false;

$damageController = file_get_contents(__DIR__ . '/../app/controllers/DamageController.php');
$checks['Damage outward scanner and dispatch accept only stock in DAMAGED locations'] =
    strpos($damageController, 'private function getVariantDamagedStockRows') !== false
    && strpos($damageController, "location LIKE '%:DAMAGED'") !== false
    && strpos($damageController, "'damage_transfer'") !== false
    && strpos($damageController, "'Outward Transit'") !== false;
$warehouseService = file_get_contents(__DIR__ . '/../app/services/WarehouseService.php');
$checks['Damage outward receipt is always recorded as quarantined damaged stock'] =
    strpos($warehouseService, '$isDamageTransfer') !== false
    && strpos($warehouseService, 'DamageService::recordTransferredDamage(') !== false;
$inwardController = file_get_contents(__DIR__ . '/../app/controllers/InwardController.php');
$inwardScanView = file_get_contents(__DIR__ . '/../app/views/concern/inward_scan.php');
$checks['Damage inward shows the transfer manifest and keeps label-generation actions hidden'] =
    strpos($inwardController, 'source_concern_name') !== false
    && strpos($inwardController, '$receivedQtyForMatch = $isDamageTransfer ? $totalDamaged : $totalCredited') !== false
    && strpos($inwardScanView, 'Damage Transfer Manifest') !== false
    && strpos($inwardScanView, 'if (!$isDamageTransfer)') !== false
    && strpos($inwardScanView, 'Receive Damaged Stock') !== false;

$isSuperAdmin = true;
$allConcerns = [
    ['id' => 1, 'name' => 'GRM Garments', 'code' => 'GRM'],
    ['id' => 2, 'name' => 'Apex Fashion', 'code' => 'APX'],
];
ob_start();
include __DIR__ . '/../app/views/concern/concern_damage_entry.php';
$adminEntryHtml = ob_get_clean();
$checks['Super admins can select a concern and scan on the same entry page'] =
    strpos($adminEntryHtml, 'id="entryConcernId"') !== false
    && strpos($adminEntryHtml, 'value="1" selected') !== false
    && strpos($adminEntryHtml, 'Live Concern QR Scanner') !== false
    && strpos($adminEntryHtml, 'Continue') === false;

$locationMethod = new ReflectionMethod(App\Services\DamageService::class, 'locationWithState');
$locationMethod->setAccessible(true);
$checks['Quarantine location mapping preserves the exact available location'] =
    $locationMethod->invoke(null, 'Main Store', 'DAMAGED') === 'Main Store:DAMAGED'
    && $locationMethod->invoke(null, 'Main Store', 'AVAILABLE') === 'Main Store'
    && $locationMethod->invoke(null, 'WAREHOUSE:1:AVAILABLE', 'DAMAGED') === 'WAREHOUSE:1:DAMAGED'
    && $locationMethod->invoke(null, 'WAREHOUSE:1:AVAILABLE', 'AVAILABLE') === 'WAREHOUSE:1:AVAILABLE';

$damageController = file_get_contents(__DIR__ . '/../app/controllers/DamageController.php');
$checks['Finished-goods damage labels resolve by embedded variant ID within the selected concern'] =
    strpos($damageController, 'QrService::parseFinishedGoodsCode($tagCode)') !== false
    && strpos($damageController, '$finishedGoodsCode[\'variant_id\']') !== false
    && strpos($damageController, 'WHERE v.id = ? AND v.concern_id = ?') !== false;
$checks['Damage scanning accepts stocked inactive variants and checks all usable concern locations'] =
    strpos($damageController, 'private function getVariantAvailableStockRows') !== false
    && strpos($damageController, 'AND location NOT LIKE \'%:DAMAGED\'') !== false
    && strpos($damageController, '$availableStockRows = $this->getVariantAvailableStockRows') !== false
    && strpos($damageController, '$this->getVariantAvailableStockRows($cid, $item[\'variant_id\'], true)') !== false
    && strpos($damageController, '$damageQty = min($remainingQty, (float)$stock[\'qty\'])') !== false
    && strpos($damageController, 'DamageService::log(') !== false
    && strpos($damageController, 'WHERE id = ? AND concern_id = ?') !== false
    && strpos($damageController, 'StockService::getQty($concernId') === false;

$failed = false;
foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . "\n";
    $failed = $failed || !$passed;
}
exit($failed ? 1 : 0);
