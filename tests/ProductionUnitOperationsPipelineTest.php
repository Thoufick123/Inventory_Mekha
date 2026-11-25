<?php
// tests/ProductionUnitOperationsPipelineTest.php
// Full verification test suite for Inward, Batch Lifecycle, Outward Manifest & Dispatch Engine

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Core\Auth;
use App\Models\Inward;
use App\Models\Batch;
use App\Models\Outward;
use App\Services\StockService;

echo "======================================================================\n";
echo "TEST SUITE: Production Unit Operations Blueprint Implementation\n";
echo "======================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(bool $condition, string $testName) {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$testName}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$testName}\n";
        $failCount++;
    }
}

// Setup baseline test environment
$concern = DB::fetch("SELECT id FROM concerns WHERE status = 'active' LIMIT 1");
$cid = $concern ? (int)$concern['id'] : 1;

$variant = DB::fetch("SELECT id, name, product_code FROM variants WHERE concern_id = ? AND status = 'active' LIMIT 1", [$cid]);
if (!$variant) {
    // Create temporary test variant
    DB::query(
        "INSERT INTO variants (concern_id, product_code, name, size, weight_unit, price, status, created_at)
         VALUES (?, 'TEST-PIPELINE-SKU', 'Test Pipeline Garment', 'L', 'pcs', 450.00, 'active', NOW())",
        [$cid]
    );
    $variantId = (int)DB::lastInsertId();
} else {
    $variantId = (int)$variant['id'];
}

// -------------------------------------------------------------------
// 1. INWARD OPERATIONS ENGINE & GRN
// -------------------------------------------------------------------
echo "--- 1. Testing Inward Engine & GRN Generation ---\n";
$grnNo = Inward::generateGrnNumber($cid);
assertCondition(str_starts_with($grnNo, 'GRN-') && strlen($grnNo) >= 15, "GRN Number format matches 'GRN-YYYYMMDD-XXXX': {$grnNo}");

$stockBefore = StockService::getQty($cid, $variantId, 'Main Store');

$inwardNo = 'INW-TEST-' . time();
DB::query(
    "INSERT INTO inwards (concern_id, inward_no, grn_number, inward_type, inward_date, location, vehicle_number, status, remarks, created_at)
     VALUES (?, ?, ?, 'purchase', CURDATE(), 'Main Store', 'TN-99-TEST-1234', 'VERIFIED', 'Automated Test Inward', NOW())",
    [$cid, $inwardNo, $grnNo]
);
$inwardId = (int)DB::lastInsertId();

$testAcceptedQty = 25.00;
$testRejectedQty = 5.00;
DB::query(
    "INSERT INTO inward_items (concern_id, inward_id, variant_id, qty, accepted_qty, rejected_qty, rejection_reason, unit_cost)
     VALUES (?, ?, ?, 30.00, ?, ?, 'Stitching defects', 120.00)",
    [$cid, $inwardId, $variantId, $testAcceptedQty, $testRejectedQty]
);

// Double-entry stock credit via StockService
StockService::increase(
    $variantId,
    'WAREHOUSE',
    'Main Store',
    $testAcceptedQty,
    'PURCHASE_INWARD',
    $inwardId,
    "GRN Inward receipt #{$grnNo}",
    $cid
);

$stockAfter = StockService::getQty($cid, $variantId, 'Main Store');
assertCondition($stockAfter == ($stockBefore + $testAcceptedQty), "StockService accurately credited accepted items (+{$testAcceptedQty}) to Main Store");

// -------------------------------------------------------------------
// 2. BATCH LIFECYCLE & SUPERVISOR VERIFICATION GATE
// -------------------------------------------------------------------
echo "\n--- 2. Testing Batch Lifecycle & Packaging Approval Gate ---\n";
$batchNo = Batch::generateBatchNo($cid);
assertCondition(str_starts_with($batchNo, 'PB-') && strlen($batchNo) >= 14, "Packaging Batch number format matches 'PB-YYYYMMDD-XXXX': {$batchNo}");

$plannedPackQty = 50.00;
DB::query(
    "INSERT INTO batches (concern_id, batch_no, variant_id, batch_qty, total_boxes, mfg_date, status, created_at)
     VALUES (?, ?, ?, ?, 5, CURDATE(), 'DRAFT', NOW())",
    [$cid, $batchNo, $variantId, $plannedPackQty]
);
$batchId = (int)DB::lastInsertId();

$unitStockBefore = StockService::getQty($cid, $variantId, 'PRODUCTION_UNIT:1');
assertCondition($unitStockBefore >= 0, "Initial Production Unit stock verified");

// Trigger supervisor approval
$approved = Batch::approveBatch($batchId, 1);
assertCondition($approved === true, "Supervisor verification approved Packaging Batch #{$batchId}");

$batchRow = DB::fetch("SELECT status, approved_by, approved_at FROM batches WHERE id = ?", [$batchId]);
assertCondition($batchRow['status'] === 'APPROVED' && !empty($batchRow['approved_at']), "Batch status transitioned to 'APPROVED' with timestamp");

$unitStockAfter = StockService::getQty($cid, $variantId, 'PRODUCTION_UNIT:1');
assertCondition($unitStockAfter == ($unitStockBefore + $plannedPackQty), "Available stock capitalized at Production Unit (+{$plannedPackQty}) upon approval");

// Block duplicate approval
$blocked = false;
try {
    Batch::approveBatch($batchId, 1);
} catch (\Throwable $e) {
    $blocked = true;
}
assertCondition($blocked === true, "Re-approving an already APPROVED batch is strictly blocked");

// -------------------------------------------------------------------
// 3. OUTWARD MANIFEST, GATE PASS & DISPATCH ENGINE
// -------------------------------------------------------------------
echo "\n--- 3. Testing Outward Manifest & Dispatch State Machine ---\n";
$gatePassNo = Outward::generateGatePassNo($cid);
assertCondition(str_starts_with($gatePassNo, 'GP-') && strlen($gatePassNo) >= 14, "Gate Pass Number format matches 'GP-YYYYMMDD-XXXX': {$gatePassNo}");

$outwardNo = 'OUT-TEST-' . time();
DB::query(
    "INSERT INTO outwards (concern_id, outward_no, gate_pass_no, transfer_type, outward_date, location, vehicle_number, driver_name, transporter_name, lr_number, status, created_at)
     VALUES (?, ?, ?, 'customer', CURDATE(), 'Main Store', 'TN-01-EXP-8888', 'Ravi Kumar', 'Speed Logistics', 'LR-998811', 'DRAFT', NOW())",
    [$cid, $outwardNo, $gatePassNo]
);
$outwardId = (int)DB::lastInsertId();

// Map batch onto outward manifest
DB::query(
    "INSERT INTO production_outward_batches (concern_id, outward_id, batch_id, cartons_count, pieces_count)
     VALUES (?, ?, ?, 5, ?)",
    [$cid, $outwardId, $batchId, $plannedPackQty]
);

// Execute Dispatch
$dispatched = Outward::dispatch($outwardId, 1);
assertCondition($dispatched === true, "Outward Manifest #{$outwardId} successfully dispatched");

$outwardRow = DB::fetch("SELECT status, dispatched_at FROM outwards WHERE id = ?", [$outwardId]);
assertCondition($outwardRow['status'] === 'DISPATCHED' && !empty($outwardRow['dispatched_at']), "Outward manifest status transitioned to 'DISPATCHED'");

$batchAfterDispatch = DB::fetchValue("SELECT status FROM batches WHERE id = ?", [$batchId]);
assertCondition($batchAfterDispatch === 'DISPATCHED', "Linked Packaging Batch marked as 'DISPATCHED'");

$unitStockAfterDispatch = StockService::getQty($cid, $variantId, 'PRODUCTION_UNIT:1');
assertCondition($unitStockAfterDispatch == $unitStockBefore, "Production Unit Available stock debited by -{$plannedPackQty} upon departure");

$transitStock = StockService::getQty($cid, $variantId, 'TRANSIT:WAREHOUSE:1:IN_TRANSIT');
assertCondition($transitStock == $plannedPackQty, "Carrier stock ledger credited with +{$plannedPackQty} in IN_TRANSIT state");

// Destination Receiving & Reconciliation
$received = Outward::receive($outwardId, 1, 2.00); // 2 pcs damaged during transit
assertCondition($received === true, "Destination facility received and reconciled shipment");

$outwardFinal = DB::fetch("SELECT status, received_at FROM outwards WHERE id = ?", [$outwardId]);
assertCondition($outwardFinal['status'] === 'RECEIVED' && !empty($outwardFinal['received_at']), "Outward manifest marked 'RECEIVED'");

$transitStockAfter = StockService::getQty($cid, $variantId, 'TRANSIT:WAREHOUSE:1:IN_TRANSIT');
assertCondition($transitStockAfter == 0.00, "IN_TRANSIT stock cleared down to 0.00 upon arrival");

// Clean up test records
DB::query("DELETE FROM production_outward_batches WHERE outward_id = ?", [$outwardId]);
DB::query("DELETE FROM outwards WHERE id = ?", [$outwardId]);
DB::query("DELETE FROM batches WHERE id = ?", [$batchId]);
DB::query("DELETE FROM inward_items WHERE inward_id = ?", [$inwardId]);
DB::query("DELETE FROM inwards WHERE id = ?", [$inwardId]);

echo "\n======================================================================\n";
echo "SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
echo "======================================================================\n";

if ($failCount > 0) {
    exit(1);
}
