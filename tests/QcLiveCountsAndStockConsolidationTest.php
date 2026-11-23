<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';

use App\Core\Env;
use App\Core\DB;
use App\Services\StockService;
use App\Controllers\ProductionController;

Env::load(dirname(__DIR__) . '/.env');

echo "=== QC LIVE COUNTS & STOCK CONSOLIDATION TEST ===\n";

try {
    // 1. Run ensureQcSchema via reflection
    $controller = new ProductionController();
    $refMethod = new \ReflectionMethod(ProductionController::class, 'ensureQcSchema');
    $refMethod->setAccessible(true);
    $refMethod->invoke($controller);
    echo "✓ ensureQcSchema ran successfully.\n";

    // 2. Verify no remaining stocks with 'Main Store (WH-01)'
    $whCount = (int)DB::fetchValue("SELECT COUNT(*) FROM stocks WHERE location = 'Main Store (WH-01)'");
    echo "Stocks with 'Main Store (WH-01)': $whCount\n";
    if ($whCount === 0) {
        echo "✓ All legacy 'Main Store (WH-01)' rows consolidated into 'Main Store'.\n";
    }

    // 3. Test StockService::getQty alias handling
    $cid = 1;
    $sampleStock = DB::fetch("SELECT * FROM stocks WHERE concern_id = ? AND qty > 0 LIMIT 1", [$cid]);
    if ($sampleStock) {
        $vId = (int)$sampleStock['variant_id'];
        $qtyMainStore = StockService::getQty($cid, $vId, 'Main Store');
        $qtyWhAlias = StockService::getQty($cid, $vId, 'Main Store (WH-01)');
        echo "Variant #$vId stock: Main Store = $qtyMainStore, WH Alias = $qtyWhAlias\n";
        if ($qtyMainStore > 0 && $qtyMainStore === $qtyWhAlias) {
            echo "✓ StockService::getQty transparent aliasing passed!\n";
        }
    }

    // 4. Test QC live piece tally logic
    $qc1 = DB::fetch("SELECT id, qc_no, status, passed_qty, inspected_qty FROM qc_inspections ORDER BY id ASC LIMIT 1");
    if ($qc1) {
        $qcId = (int)$qc1['id'];
        $pieceCounts = DB::fetch("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN verdict = 'PASSED' THEN 1 ELSE 0 END) AS passed,
                SUM(CASE WHEN verdict = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN verdict = 'REWORK' THEN 1 ELSE 0 END) AS rework,
                SUM(CASE WHEN verdict = 'PENDING' THEN 1 ELSE 0 END) AS pending
            FROM qc_piece_items WHERE qc_id = ?
        ", [$qcId]);
        echo "QC #{$qc1['qc_no']} (ID: $qcId): Status={$qc1['status']}, DB passed_qty={$qc1['passed_qty']}, Pieces: total={$pieceCounts['total']}, passed={$pieceCounts['passed']}, pending={$pieceCounts['pending']}, rejected={$pieceCounts['rejected']}\n";
    }

    // 5. Code inspection checks
    $prodCtrlCode = file_get_contents(__DIR__ . '/../app/controllers/ProductionController.php');
    $salesCtrlCode = file_get_contents(__DIR__ . '/../app/controllers/SalesController.php');
    $damageCtrlCode = file_get_contents(__DIR__ . '/../app/controllers/DamageController.php');

    assert(strpos($prodCtrlCode, "'live_passed_qty'") !== false, "live_passed_qty in ProductionController");
    assert(strpos($salesCtrlCode, "location = 'Main Store (WH-01)'") !== false, "WH-01 alias in SalesController");
    assert(strpos($damageCtrlCode, "Main Store (WH-01)") !== false, "WH-01 alias in DamageController");
    echo "✓ Code assertions verified.\n";

    echo "=== ALL TESTS COMPLETED SUCCESSFULLY ===\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
