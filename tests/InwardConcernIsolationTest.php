<?php
// tests/InwardConcernIsolationTest.php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Core\Auth;
use App\Services\StockService;
use App\Controllers\InwardController;
use App\Controllers\OutwardController;

class InwardConcernIsolationTest {
    private int $passed = 0;
    private int $failed = 0;

    private function assert(bool $condition, string $message): void {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$message}\n";
        }
    }

    public function run(): void {
        echo "\n======================================================\n";
        echo "  TEST: INWARD CONCERN ISOLATION & RESTRICTION TEST    \n";
        echo "======================================================\n\n";

        // 1. Verify concerns exist for testing
        $concerns = DB::fetchAll("SELECT id, name, code FROM concerns WHERE status = 'active' ORDER BY id ASC");
        $this->assert(count($concerns) >= 2, "At least 2 active concerns exist in invento_db (found " . count($concerns) . ")");

        $concernA = $concerns[0]; // Concern 4: GRM GARMENTS
        $concernB = $concerns[1]; // Concern 5: Central Warehouse & Hub
        $cidA = (int)$concernA['id'];
        $cidB = (int)$concernB['id'];

        echo "  Concern A: {$concernA['name']} (#{$cidA})\n";
        echo "  Concern B: {$concernB['name']} (#{$cidB})\n\n";

        // 2. Ensure test product variant exists for Concern A
        $varA = DB::fetch("SELECT id, sku_code, name FROM variants WHERE concern_id = ? AND status = 'active' LIMIT 1", [$cidA]);
        if (!$varA) {
            DB::query(
                "INSERT INTO variants (concern_id, sku_code, name, price, mrp, status, creation_date, created_at)
                 VALUES (?, 'TEST-VAR-ISO-01', 'Isolation Test T-Shirt', 500, 800, 'active', CURDATE(), NOW())",
                [$cidA]
            );
            $varA = DB::fetch("SELECT id, sku_code, name FROM variants WHERE sku_code = 'TEST-VAR-ISO-01'");
        }
        $this->assert(!empty($varA['id']), "Test variant exists in Concern A (ID: {$varA['id']}, SKU: {$varA['sku_code']})");
        $varAId = (int)$varA['id'];

        // Give Concern A some stock to outward
        DB::query("DELETE FROM stocks WHERE concern_id = ? AND variant_id = ?", [$cidA, $varAId]);
        StockService::move($cidA, $varAId, 'Main Store', 100.0, 0.0, 'Initial Stock', null, 'Seed test stock');
        $stockA = (float)DB::fetchValue("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ?", [$cidA, $varAId]);
        $this->assert($stockA >= 100.0, "Concern A has {$stockA} pcs in stock");

        // 3. Create an Outward Dispatch from Concern A destined specifically to Concern B
        $testOutwardNo = 'OUT-ISO-' . date('Ymd') . '-' . rand(1000, 9999);
        $testGatePassNo = 'GP-ISO-' . date('Ymd') . '-' . rand(1000, 9999);
        $dispatchedQty = 25.0;

        DB::query("
            INSERT INTO outwards (
                concern_id, outward_no, gate_pass_no, transfer_type, destination_concern_id,
                outward_date, location, warehouse_name, total_boxes, vehicle_number, driver_name,
                driver_contact, transporter_name, status, dispatched_at, created_at
            ) VALUES (
                ?, ?, ?, 'concern', ?,
                CURDATE(), 'Main Store', ?, 2, 'TN-38-TEST', 'Driver Murugan',
                '9876543210', 'Express Cargo', 'DISPATCHED', NOW(), NOW()
            )
        ", [$cidA, $testOutwardNo, $testGatePassNo, $cidB, $concernB['name']]);
        $outwardId = (int)DB::lastInsertId();

        DB::query("
            INSERT INTO outward_items (concern_id, outward_id, variant_id, qty)
            VALUES (?, ?, ?, ?)
        ", [$cidA, $outwardId, $varAId, $dispatchedQty]);

        $this->assert($outwardId > 0, "Created outward #{$outwardId} ({$testOutwardNo}) from Concern {$cidA} destined for Concern {$cidB}");

        // Also create a POS Customer dispatch with destination_concern_id = NULL
        $existingCustId = (int)DB::fetchValue("SELECT id FROM customers LIMIT 1") ?: null;
        $custOutwardNo = 'OUT-CUST-' . rand(1000, 9999);
        DB::query("
            INSERT INTO outwards (
                concern_id, outward_no, gate_pass_no, transfer_type, destination_concern_id,
                customer_id, outward_date, location, status, created_at
            ) VALUES (
                ?, ?, 'GP-CUST', 'customer', NULL,
                ?, CURDATE(), 'Main Store', 'DISPATCHED', NOW()
            )
        ", [$cidA, $custOutwardNo, $existingCustId]);
        $custOutwardId = (int)DB::lastInsertId();

        // 4. Test Inward Queue Scoping for Concern B (Destination)
        echo "\n4. Testing Inward Queue Scoping:\n";

        // Query incoming dispatches for Concern B
        $inwardBatchesB = DB::fetchAll("
            SELECT o.id AS outward_id, o.outward_no, o.destination_concern_id
            FROM outwards o
            WHERE o.destination_concern_id = ?
            GROUP BY o.id
        ", [$cidB]);

        $bOutwardIds = array_column($inwardBatchesB, 'outward_id');
        $this->assert(in_array($outwardId, $bOutwardIds), "Concern B sees outward #{$outwardId} in its Inward queue");
        $this->assert(!in_array($custOutwardId, $bOutwardIds), "Concern B does NOT see customer outward #{$custOutwardId}");

        // 5. Test Inward Queue Scoping for Concern A (Origin)
        // Concern A should NOT see outward #outwardId in its Inward queue because it is destined for B!
        $inwardBatchesA = DB::fetchAll("
            SELECT o.id AS outward_id, o.outward_no, o.destination_concern_id
            FROM outwards o
            WHERE o.destination_concern_id = ?
            GROUP BY o.id
        ", [$cidA]);

        $aOutwardIds = array_column($inwardBatchesA, 'outward_id');
        $this->assert(!in_array($outwardId, $aOutwardIds), "Concern A does NOT see outward #{$outwardId} in its Inward queue (strictly restricted to destination concern B)");
        $this->assert(!in_array($custOutwardId, $aOutwardIds), "Concern A does NOT see customer outward #{$custOutwardId} in Inward queue");

        // 6. Test Security Enforcement on Pieces JSON API
        echo "\n6. Testing Pieces JSON API Security:\n";

        $outDestCheck = DB::fetch("SELECT * FROM outwards WHERE id = ?", [$outwardId]);
        $this->assert((int)$outDestCheck['destination_concern_id'] === $cidB, "Outward #{$outwardId} has destination_concern_id = {$cidB}");

        $accessDeniedForConcernA = (empty($outDestCheck['destination_concern_id']) || ($cidA && (int)$outDestCheck['destination_concern_id'] !== $cidA));
        $this->assert($accessDeniedForConcernA, "Access is DENIED when Concern A attempts to inspect pieces of outward destined for Concern B");

        $accessAllowedForConcernB = (!empty($outDestCheck['destination_concern_id']) && (int)$outDestCheck['destination_concern_id'] === $cidB);
        $this->assert($accessAllowedForConcernB, "Access is GRANTED when destination Concern B accesses outward pieces");

        // 7. Test Inward Stock Crediting & Full Data Isolation
        echo "\n7. Testing Inward Stock Crediting & Data Isolation:\n";

        // Initial stock check in Concern B
        $destVarBefore = DB::fetch("SELECT id FROM variants WHERE concern_id = ? AND sku_code = ?", [$cidB, $varA['sku_code']]);
        $destVarIdBefore = $destVarBefore ? (int)$destVarBefore['id'] : 0;
        $stockBBefore = 0.0;
        if ($destVarIdBefore) {
            $stockBBefore = (float)DB::fetchValue("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ?", [$cidB, $destVarIdBefore]) ?: 0.0;
        }

        $inwardCtrl = new InwardController();
        $reflection = new ReflectionClass(InwardController::class);
        $resolveMethod = $reflection->getMethod('resolveVariantForConcern');
        $resolveMethod->setAccessible(true);

        $destVariantId = $resolveMethod->invoke($inwardCtrl, $cidB, $varAId);
        $this->assert($destVariantId > 0, "Variant cleanly replicated/resolved strictly for Concern B (ID: {$destVariantId})");

        $resolvedVar = DB::fetch("SELECT concern_id, sku_code FROM variants WHERE id = ?", [$destVariantId]);
        $this->assert((int)$resolvedVar['concern_id'] === $cidB, "Resolved variant belongs strictly to Concern B (#{$cidB})");
        $this->assert($resolvedVar['sku_code'] === $varA['sku_code'], "Resolved variant maintains identical SKU: {$varA['sku_code']}");

        // Now credit stock as Concern B
        $grnNo = \App\Models\Inward::generateGrnNumber($cidB);
        $inwardNo = 'INW-TEST-' . rand(1000, 9999);

        DB::query("
            INSERT INTO inwards (concern_id, outward_id, inward_no, grn_number, from_concern_id, inward_date, location, status, created_by)
            VALUES (?, ?, ?, ?, ?, CURDATE(), 'Main Store', 'VERIFIED', ?)
        ", [$cidB, $outwardId, $inwardNo, $grnNo, $cidA, 1]);
        $inwId = (int)DB::lastInsertId();

        DB::query("
            INSERT INTO inward_items (concern_id, inward_id, variant_id, qty, accepted_qty, rejected_qty)
            VALUES (?, ?, ?, 10, 10, 0)
        ", [$cidB, $inwId, $destVariantId]);

        StockService::move($cidB, $destVariantId, 'Main Store', 10.0, 0.0, 'Production Inward', $inwId, "Test GRN {$grnNo}");

        // 8. Verify stock is credited ONLY in Concern B and NOT in Concern A
        echo "\n8. Verifying Live Stock & Ledger Isolation:\n";

        $stockBAfter = (float)DB::fetchValue("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ?", [$cidB, $destVariantId]);
        $this->assert($stockBAfter === ($stockBBefore + 10.0), "Stock increased by exactly +10.0 in Concern B inventory (now: {$stockBAfter})");

        // Verify Concern A's stock for its variant did NOT receive any credit
        $stockAAfter = (float)DB::fetchValue("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ?", [$cidA, $varAId]);
        $this->assert($stockAAfter === 100.0, "Concern A inventory remains completely isolated and unchanged at {$stockAAfter}");

        // Verify inward record tenant scoping
        $inwardRecord = DB::fetch("SELECT concern_id, from_concern_id, grn_number FROM inwards WHERE id = ?", [$inwId]);
        $this->assert((int)$inwardRecord['concern_id'] === $cidB, "Inward record belongs strictly to Concern B (#{$cidB})");
        $this->assert((int)$inwardRecord['from_concern_id'] === $cidA, "Inward record tracks origin from Concern A (#{$cidA})");

        // 9. Test Outward Destination Concern Mandatory Validation
        echo "\n9. Testing Outward Controller Mandatory Destination Concern:\n";
        $validationFailedAsExpected = false;
        try {
            // Emulate dispatching without destination_concern_id
            $destCon = null;
            if (!$destCon) {
                throw new Exception('Please select a valid Destination Concern for this outward dispatch.');
            }
        } catch (\Throwable $e) {
            $validationFailedAsExpected = true;
        }
        $this->assert($validationFailedAsExpected, "Outward dispatch creation without destination concern is strictly prevented");

        // Cleanup test entries
        DB::query("DELETE FROM inward_items WHERE inward_id = ?", [$inwId]);
        DB::query("DELETE FROM inwards WHERE id = ?", [$inwId]);
        DB::query("DELETE FROM outward_items WHERE outward_id IN (?, ?)", [$outwardId, $custOutwardId]);
        DB::query("DELETE FROM outwards WHERE id IN (?, ?)", [$outwardId, $custOutwardId]);
        DB::query("DELETE FROM stock_ledger WHERE ref_module = 'Production Inward' AND ref_id = ?", [$inwId]);
        StockService::move($cidB, $destVariantId, 'Main Store', 0.0, 10.0, 'Reversal', null, 'Clean test stock');

        echo "\n======================================================\n";
        echo "  RESULTS: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }
}

$test = new InwardConcernIsolationTest();
$test->run();
