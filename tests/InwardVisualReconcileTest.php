<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Core\Auth;
use App\Controllers\InwardController;
use App\Services\QrService;

echo "======================================================================\n";
echo "TEST SUITE: Inward Module Revamp (List, Scan, and Piece View Pages)\n";
echo "======================================================================\n\n";

$passed = 0;
$failed = 0;

function assertCondition(bool $condition, string $label, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$label}" . ($detail ? " ({$detail})" : "") . "\n";
        $passed++;
    } else {
        echo "  [FAIL] {$label}" . ($detail ? " ({$detail})" : "") . "\n";
        $failed++;
    }
}

// 1. Test Base Tables & Sample Batch Creation
echo "--- 1. Testing Schema and Sample Batch Initialization ---\n";
\App\Core\Controller::ensureBaseTables();

try {
    // Manually trigger the seed logic to see any error
    $puId = (int)DB::fetchValue("SELECT id FROM production_units LIMIT 1");
    if (!$puId) {
        DB::query("INSERT INTO production_units (concern_id, code, name, location, manager_name, capacity_per_day, is_active) VALUES (1, 'PU-01', 'Kadampadi', 'Kadampadi', 'Vanaja (9943557998)', 500, 1)");
        $puId = (int)DB::lastInsertId();
    }

    $varId = (int)DB::fetchValue("SELECT id FROM variants WHERE name LIKE '%KIDS - BABY - DRESS%' OR product_code = 'GRM-STY-0001-XL' LIMIT 1");
    if (!$varId) {
        $catId = (int)DB::fetchValue("SELECT id FROM categories LIMIT 1") ?: null;
        DB::query("INSERT INTO variants (concern_id, product_code, name, category_id, size, weight, weight_unit, price, mrp, status) VALUES (1, 'GRM-STY-0001-XL', 'KIDS - BABY - DRESS', ?, 'XL', 0.5, 'pcs', 700.00, 1000.00, 'active')", [$catId]);
        $varId = (int)DB::lastInsertId();
    }

    $progId = (int)DB::fetchValue("SELECT id FROM production_programs WHERE program_no = 'PRG-20261113-0001' LIMIT 1");
    if (!$progId) {
        DB::query("INSERT INTO production_programs (concern_id, program_no, program_name, target_variant_id, planned_qty, status) VALUES (1, 'PRG-20261113-0001', 'KIDS - BABY - DRESS', ?, 490, 'completed')", [$varId]);
        $progId = (int)DB::lastInsertId();
    }

    $batchId = (int)DB::fetchValue("SELECT id FROM batches WHERE batch_no = 'BAT-20261113-0001' LIMIT 1");
    if (!$batchId) {
        DB::query("INSERT INTO batches (concern_id, production_unit_id, batch_no, variant_id, program_id, batch_qty, total_boxes, status) VALUES (1, ?, 'BAT-20261113-0001', ?, ?, 490, 1, 'DISPATCHED')", [$puId, $varId, $progId]);
        $batchId = (int)DB::lastInsertId();
    }

        DB::query("DELETE FROM outwards WHERE outward_no = 'OUT-20261113-0001'");
        DB::query("INSERT INTO outwards (concern_id, production_unit_id, outward_no, gate_pass_no, destination_concern_id, outward_date, vehicle_number, driver_name, driver_contact, status, created_at) VALUES (1, ?, 'OUT-20261113-0001', 'OUT-20261113-0001', 1, '2026-11-13', 'rtt31232', 'Vanaja', '9943557998', 'partial', '2026-11-13 13:06:00')", [$puId]);
        $outwardId = (int)DB::lastInsertId();
        DB::query("INSERT INTO outward_items (concern_id, outward_id, variant_id, qty) VALUES (1, ?, ?, 490)", [$outwardId, $varId]);
        DB::query("INSERT INTO production_outward_batches (concern_id, outward_id, batch_id, cartons_count, pieces_count) VALUES (1, ?, ?, 1, 490)", [$outwardId, $batchId]);

    $inwId = (int)DB::fetchValue("SELECT id FROM inwards WHERE outward_id = ? LIMIT 1", [$outwardId]);
    if (!$inwId) {
        DB::query("INSERT INTO inwards (concern_id, outward_id, inward_no, grn_number, production_unit_id, inward_date, vehicle_number, location, status) VALUES (1, ?, 'INW-20261113-0001', 'GRN-20261113-0001', ?, '2026-11-13', 'rtt31232', 'Main Store', 'PARTIAL')", [$outwardId, $puId]);
        $inwId = (int)DB::lastInsertId();
        DB::query("INSERT INTO inward_items (concern_id, inward_id, variant_id, qty, accepted_qty, rejected_qty) VALUES (1, ?, ?, 3, 3, 0)", [$inwId, $varId]);
    }
} catch (\Throwable $e) {
    echo "Seed Error: " . $e->getMessage() . "\n";
}

$sampleOutward = DB::fetch("SELECT * FROM outwards WHERE outward_no = 'OUT-20261113-0001'");
assertCondition(!empty($sampleOutward), "Sample Outward dispatch initialized", $sampleOutward['outward_no'] ?? 'none');
assertCondition($sampleOutward['vehicle_number'] === 'rtt31232', "Vehicle number verified", $sampleOutward['vehicle_number'] ?? '');

// 2. Test Image 1 Data (List / Ledger View)
echo "\n--- 2. Testing Image 1 (Production Inward List / Ledger View) ---\n";
$batches = DB::fetchAll("
    SELECT o.id AS outward_id, o.outward_no, b.batch_no,
           coalesce((SELECT sum(qty) FROM outward_items WHERE outward_id = o.id), 0) AS total_qty,
           coalesce((SELECT sum(accepted_qty) FROM inwards inw JOIN inward_items ii ON inw.id = ii.inward_id WHERE inw.outward_id = o.id), 0) AS credited_qty
    FROM outwards o
    LEFT JOIN production_outward_batches pob ON o.id = pob.outward_id
    LEFT JOIN batches b ON pob.batch_id = b.id
    WHERE o.id = ?
", [$sampleOutward['id']]);

assertCondition(count($batches) > 0, "Outward batch present in ledger");
$b0 = $batches[0];
$totalQty = (float)$b0['total_qty'];
$creditedQty = (float)$b0['credited_qty'];
$shortQty = $totalQty - $creditedQty;

assertCondition($totalQty === 490.0, "Total dispatched matches Image 1 (490 pcs)", (string)$totalQty);
assertCondition($creditedQty === 3.0, "Credited in stock matches Image 1 (3 pcs)", (string)$creditedQty);
assertCondition($shortQty === 487.0, "Pending/Short matches Image 1 (487 pcs)", (string)$shortQty);

// 3. Test Image 2 Data (Scan / Cross-Check View)
echo "\n--- 3. Testing Image 2 (Complete Inward Shipment Cross-Check View) ---\n";
$variant = DB::fetch(
    "SELECT v.* FROM outward_items oi JOIN variants v ON v.id = oi.variant_id
     WHERE oi.outward_id = ? LIMIT 1",
    [(int)$sampleOutward['id']]
);
assertCondition(!empty($variant), "KIDS - BABY - DRESS variant exists", $variant['product_code'] ?? '');

$matchPct = round(($creditedQty / $totalQty) * 100);
assertCondition($matchPct === 1.0, "Verification Match percentage matches Image 2 (1%)", "{$matchPct}%");

$expectedTag = QrService::generateFinishedGoodsCode((int)$variant['id']);
assertCondition(!empty($expectedTag), "Outward variant has a generated QR code", (string)$expectedTag);

// 4. Test Image 3 Data (Individual Piece QR Codes Modal)
echo "\n--- 4. Testing Image 3 (Product Specifications & Individual Piece QR Codes Modal) ---\n";
$_GET['variant_id'] = $variant['id'];
$controller = new InwardController();
ob_start();
$controller->piecesJson((int)$sampleOutward['id']);
$jsonOutput = ob_get_clean();
$data = json_decode($jsonOutput, true);

assertCondition(!empty($data['success']), "piecesJson endpoint returns successful JSON response");
assertCondition($data['total_pieces'] === 490, "Total pieces in modal matches Image 3 (490)", (string)$data['total_pieces']);
assertCondition($data['credited_count'] === 3, "Verified pieces in modal matches Image 3 (3)", (string)$data['credited_count']);
assertCondition($data['pending_count'] === 487, "Mismatched pieces in modal matches Image 3 (487)", (string)$data['pending_count']);
assertCondition(count($data['pieces']) === 490, "All 490 piece records generated");
assertCondition((int)$data['variant_id'] === (int)$variant['id'], "Piece response is tied to the exact outward variant ID");

// Every piece uses the same valid variant QR code; outward_items stores variant quantities, not serial IDs.
$piece4 = $data['pieces'][3];
assertCondition($piece4['tag_code'] === $expectedTag, "Piece QR code matches the outward variant ID", $piece4['tag_code']);
assertCondition($piece4['status'] === 'unverified', "Piece #4 status is unverified (pending)", $piece4['status']);

$piece17 = $data['pieces'][16];
assertCondition($piece17['tag_code'] === $expectedTag, "All piece QR codes resolve to the same exact variant", $piece17['tag_code']);
assertCondition($piece17['discount_price'] == 700.00, "Piece #17 price matches Image 3 (₹700.00)", (string)$piece17['discount_price']);
assertCondition($piece17['mrp'] == 1000.00, "Piece #17 MRP matches Image 3 (₹1000.00)", (string)$piece17['mrp']);

$_GET['variant_id'] = (int)DB::fetchValue("SELECT COALESCE(MAX(id), 0) + 1 FROM variants");
ob_start();
$controller->piecesJson((int)$sampleOutward['id']);
$invalidJsonOutput = ob_get_clean();
$invalidData = json_decode($invalidJsonOutput, true);
assertCondition(empty($invalidData['success']), "A variant not dispatched in this outward is rejected");

echo "\n======================================================================\n";
echo "SUMMARY: {$passed} Passed, {$failed} Failed\n";
echo "======================================================================\n";
exit($failed === 0 ? 0 : 1);
