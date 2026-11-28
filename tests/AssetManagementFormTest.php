<?php

$assetView = file_get_contents(__DIR__ . '/../app/views/admin/assets.php');
$adminController = file_get_contents(__DIR__ . '/../app/controllers/AdminController.php');
$outwardController = file_get_contents(__DIR__ . '/../app/controllers/OutwardController.php');
$inwardController = file_get_contents(__DIR__ . '/../app/controllers/InwardController.php');
$inwardView = file_get_contents(__DIR__ . '/../app/views/concern/inward.php');
$assetTransferSchema = file_get_contents(__DIR__ . '/../app/services/AssetTransferSchema.php');

$checks = [
    'Asset categories include saved values and allow adding a custom category' =>
        strpos($adminController, 'SELECT DISTINCT category FROM assets') !== false
        && strpos($assetView, 'value="__add_new__">+ Add New Category</option>') !== false
        && strpos($assetView, 'name="category" id="add_asset_category"') !== false
        && strpos($assetView, 'name="category" id="edit_asset_category"') !== false,
    'Asset code is previewed from category and generated from the saved asset ID' =>
        strpos($assetView, 'id="add_asset_code"') !== false
        && strpos($assetView, 'updateAssetCodePreview') !== false
        && strpos($adminController, '$this->generatedAssetCode($category, $assetId)') !== false
        && strpos($adminController, "'MACHINERY' => 'MCH'") !== false,
    'Unassigned asset label is Not a Particular Concern' =>
        strpos($assetView, '<option value="">Not a Particular Concern</option>') !== false
        && strpos($assetView, 'Not a Particular Concern</span>') !== false
        && strpos($assetView, 'Other options are active concerns.') !== false
        && strpos($adminController, '$concernId = !empty($_POST[\'concern_id\']) ? (int)$_POST[\'concern_id\'] : null;') !== false
        && strpos($adminController, 'UPDATE assets SET concern_id = ?, asset_code = ?') !== false,
    'Asset actions provide a printable label with the saved asset details' =>
        strpos($assetView, 'title="Print Asset Label"') !== false
        && strpos($assetView, 'printAssetLabel(') !== false
        && strpos($assetView, 'size:60mm 40mm') !== false
        && strpos($assetView, 'grid-template-columns:22mm minmax(0,1fr)') !== false
        && strpos($assetView, 'label.append(qr, details)') !== false
        && strpos($assetView, 'id = \'asset-label-qr\'') !== false
        && strpos($assetView, '/assets/js/qrcode.min.js') !== false
        && strpos($assetView, 'text: String(asset.id || \'\')') !== false
        && strpos($assetView, "element.textContent = text") !== false,
    'Asset outward requires an active source and destination and blocks pending transfers' =>
        strpos($assetView, '/admin/assets/outward') !== false
        && strpos($assetView, 'name="source_concern_id"') !== false
        && strpos($outwardController, 'public function storeAssetTransfer(): void') !== false
        && strpos($outwardController, 'already has an outward transfer awaiting inward receipt') !== false
        && strpos($outwardController, "SELECT id FROM concerns WHERE id = ? AND status = 'active'") !== false,
    'Asset inward records receipt and assigns the asset to the destination concern' =>
        strpos($inwardController, 'public function receiveAssetTransfer(): void') !== false
        && strpos($inwardController, 'INSERT INTO inward_asset_items') !== false
        && strpos($inwardController, 'UPDATE assets SET concern_id = ? WHERE id = ?') !== false
        && strpos($inwardView, '/m/inward/receive-assets') !== false
        && strpos($inwardView, 'FIXED ASSET OUTWARD') !== false,
    'Asset transfer schema is created for both outward and inward tracking' =>
        strpos($assetTransferSchema, 'CREATE TABLE IF NOT EXISTS `outward_asset_items`') !== false
        && strpos($assetTransferSchema, 'CREATE TABLE IF NOT EXISTS `inward_asset_items`') !== false
        && strpos($inwardView, '<<<<<<<') === false
        && strpos($inwardView, '>>>>>>>') === false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
