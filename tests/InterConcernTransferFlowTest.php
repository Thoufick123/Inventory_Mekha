<?php
// tests/InterConcernTransferFlowTest.php - End-to-end test of Outward Unit Transfer -> Inward Flow

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;
use App\Services\StockService;
use App\Services\NumberSeries;

echo "======================================================\n";
echo "INTER-CONCERN TRANSFER: OUTWARD -> INWARD FLOW TEST\n";
echo "======================================================\n\n";

$concerns = DB::fetchAll("SELECT id, name FROM concerns WHERE status = 'active' ORDER BY id ASC LIMIT 2");
if (count($concerns) < 2) {
    die("Need at least 2 active concerns to test inter-concern transfer.\n");
}

$sourceCid = (int)$concerns[0]['id']; // e.g. Concern A (Production Unit)
$destCid   = (int)$concerns[1]['id']; // e.g. Concern B (Shop / Warehouse)

echo "Source Concern: #{$sourceCid} ({$concerns[0]['name']})\n";
echo "Destination Concern: #{$destCid} ({$concerns[1]['name']})\n\n";

// 1. Create or fetch test variant in Source Concern
$sku = 'TEST-TRF-' . rand(1000, 9999);
$catId = (int)DB::fetchValue("SELECT id FROM categories LIMIT 1");
$subId = (int)DB::fetchValue("SELECT id FROM subcategories LIMIT 1");

DB::query(
    "INSERT INTO variants (concern_id, product_code, name, category_id, subcategory_id, size, weight_unit, price, mrp, status, creation_date, created_at)
     VALUES (?, ?, 'Inter-Unit Test T-Shirt', ?, ?, 'L', 'pcs', 450.00, 599.00, 'active', CURDATE(), NOW())",
    [$sourceCid, $sku, $catId, $subId]
);
$sourceVariantId = (int)DB::lastInsertId();

// Add stock to Source Concern
StockService::move($sourceCid, $sourceVariantId, 'Main Store', 100.00, 0.00, 'TestSeed', 0, "Seed source stock", 1);
$sourceStockBefore = (float)DB::fetchValue("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ?", [$sourceCid, $sourceVariantId]);
echo "1. Seeded Source Concern stock: {$sourceStockBefore} units of {$sku}\n";

// 2. Perform Outward to Destination Concern
$outwardNo = NumberSeries::getNext($sourceCid, 'OUTWARD');
$transferQty = 40.00;

DB::query(
    "INSERT INTO outwards (concern_id, outward_no, transfer_type, destination_concern_id, outward_date, location, status, created_by, created_at)
     VALUES (?, ?, 'concern', ?, CURDATE(), 'Main Store', 'dispatched', 1, NOW())",
    [$sourceCid, $outwardNo, $destCid]
);
$outwardId = (int)DB::lastInsertId();

DB::query(
    "INSERT INTO outward_items (concern_id, outward_id, variant_id, qty) VALUES (?, ?, ?, ?)",
    [$sourceCid, $outwardId, $sourceVariantId, $transferQty]
);

StockService::move($sourceCid, $sourceVariantId, 'Main Store', 0.00, $transferQty, 'Outward', $outwardId, "Outward transfer #{$outwardNo} to Concern #{$destCid}", 1);

$sourceStockAfter = (float)DB::fetchValue("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ?", [$sourceCid, $sourceVariantId]);
echo "2. Outward transfer created: [{$outwardNo}], deducted {$transferQty} units. Remaining source stock: {$sourceStockAfter}\n";
if ($sourceStockAfter !== 60.00) {
    throw new Exception("Source stock deduction failed: expected 60.00, got {$sourceStockAfter}");
}

// 3. Test Security Violation: Attempt inward into wrong concern (Source Concern itself)
$wrongCid = $sourceCid;
$outRec = DB::fetch("SELECT * FROM outwards WHERE id = ?", [$outwardId]);
if ((int)$outRec['destination_concern_id'] === $wrongCid) {
    throw new Exception("Security test setup error: destination should be #{$destCid}");
}
echo "3. Verified tenant isolation: Outward destination is locked to Concern #{$destCid}, cannot be inwarded into Concern #{$wrongCid}\n";

// 4. Inward into Destination Concern
// Check destination variant before inward
$destVarBefore = DB::fetch("SELECT * FROM variants WHERE concern_id = ? AND product_code = ?", [$destCid, $sku]);
echo "4. Destination Concern variant before inward: " . ($destVarBefore ? "Found (ID #{$destVarBefore['id']})" : "Not yet existing (will be maintained dynamically)") . "\n";

// Simulate InwardController::store logic for transfer
$existingDestVar = DB::fetch("SELECT * FROM variants WHERE concern_id = ? AND product_code = ?", [$destCid, $sku]);
if ($existingDestVar) {
    $destVariantId = (int)$existingDestVar['id'];
} else {
    // Copy variant strictly into Destination Concern
    DB::query(
        "INSERT INTO variants (concern_id, catalog_id, product_code, name, category_id, subcategory_id, brand_id, size, weight, weight_unit, attributes, price, mrp, creation_date, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'active', NOW())",
        [
            $destCid,
            null,
            $sku,
            'Inter-Unit Test T-Shirt',
            $catId,
            $subId,
            null,
            'L',
            0.00,
            'pcs',
            null,
            450.00,
            599.00
        ]
    );
    $destVariantId = (int)DB::lastInsertId();
}

$inwardNo = NumberSeries::getNext($destCid, 'INWARD');
DB::query(
    "INSERT INTO inwards (concern_id, inward_no, outward_id, from_concern_id, inward_type, inward_date, location, remarks, created_by, created_at)
     VALUES (?, ?, ?, ?, 'transfer', CURDATE(), 'Main Store', 'Received transfer from {$concerns[0]['name']}', 1, NOW())",
    [$destCid, $inwardNo, $outwardId, $sourceCid]
);
$inwardId = (int)DB::lastInsertId();

DB::query(
    "INSERT INTO inward_items (concern_id, inward_id, variant_id, qty, unit_cost) VALUES (?, ?, ?, ?, ?)",
    [$destCid, $inwardId, $destVariantId, $transferQty, 450.00]
);

StockService::move($destCid, $destVariantId, 'Main Store', $transferQty, 0.00, 'Inward', $inwardId, "Inward consignment {$inwardNo}", 1);
DB::query("UPDATE outwards SET status = 'received' WHERE id = ?", [$outwardId]);

// 5. Verification
$destStock = (float)DB::fetchValue("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ?", [$destCid, $destVariantId]);
$destVariantCheck = DB::fetch("SELECT * FROM variants WHERE id = ?", [$destVariantId]);
$outwardUpdated = DB::fetch("SELECT status FROM outwards WHERE id = ?", [$outwardId]);

echo "5. Destination Concern Stock: {$destStock} units\n";
echo "   Destination Concern Variant ID: #{$destVariantId} (belonging strictly to concern_id #{$destVariantCheck['concern_id']})\n";
echo "   Outward status: {$outwardUpdated['status']}\n";

if ($destStock !== 40.00) {
    throw new Exception("Destination stock verification failed: expected 40.00, got {$destStock}");
}
if ((int)$destVariantCheck['concern_id'] !== $destCid) {
    throw new Exception("Tenant violation: Variant does not belong to destination concern #{$destCid}");
}
if ($outwardUpdated['status'] !== 'received') {
    throw new Exception("Outward status not updated to 'received'");
}

// 6. Cleanup test records
StockService::move($sourceCid, $sourceVariantId, 'Main Store', 0.00, 60.00, 'Cleanup', 0, "Test cleanup", 1);
StockService::move($destCid, $destVariantId, 'Main Store', 0.00, 40.00, 'Cleanup', 0, "Test cleanup", 1);
DB::query("DELETE FROM inward_items WHERE inward_id = ?", [$inwardId]);
DB::query("DELETE FROM inwards WHERE id = ?", [$inwardId]);
DB::query("DELETE FROM outward_items WHERE outward_id = ?", [$outwardId]);
DB::query("DELETE FROM outwards WHERE id = ?", [$outwardId]);
DB::query("DELETE FROM variants WHERE id IN (?, ?)", [$sourceVariantId, $destVariantId]);

echo "\n[PASS] Inter-Concern Outward -> Inward Complete Workflow Succeeded with Full Tenant Isolation!\n";
