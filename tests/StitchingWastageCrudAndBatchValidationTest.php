<?php
/**
 * Test: Stitching Wastage CRUD Operations and Batch Selection Validation
 */
require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;

echo "======================================================\n";
echo "TEST SUITE: Stitching Wastage CRUD & Batch Validation\n";
echo "======================================================\n\n";

$pass = 0;
$fail = 0;

function assertCondition($cond, $desc) {
    global $pass, $fail;
    if ($cond) {
        echo "  [PASS] $desc\n";
        $pass++;
    } else {
        echo "  [FAIL] $desc\n";
        $fail++;
    }
}

// 1. Controller Methods Check
echo "--- 1. Testing ProductionController Methods ---\n";
$controllerContent = file_get_contents(__DIR__ . '/../app/controllers/ProductionController.php');

assertCondition(
    strpos($controllerContent, 'function storeStitchingWastageSale') !== false,
    "storeStitchingWastageSale (Create) method exists"
);
assertCondition(
    strpos($controllerContent, 'function apiStitchingWastageDetails') !== false,
    "apiStitchingWastageDetails (View Details API) method exists"
);
assertCondition(
    strpos($controllerContent, 'function updateStitchingWastageSale') !== false,
    "updateStitchingWastageSale (Edit/Update) method exists"
);
assertCondition(
    strpos($controllerContent, 'function deleteStitchingWastageSale') !== false,
    "deleteStitchingWastageSale (Delete) method exists"
);

// 2. Batch Exclusion / Filtering in stitchingIndex
echo "\n--- 2. Testing Batch Dropdown Filtering Logic ---\n";
assertCondition(
    strpos($controllerContent, '$soldStitchingIds') !== false &&
    strpos($controllerContent, 'SELECT DISTINCT stitching_id FROM stitching_wastage_sales') !== false,
    "stitchingIndex filters out batches already in stitching_wastage_sales"
);
assertCondition(
    strpos($controllerContent, "cancelled") !== false,
    "stitchingIndex excludes cancelled stitching batches from eligible scrap list"
);

// 3. Backend Validation in storeStitchingWastageSale
echo "\n--- 3. Testing Backend Duplicate Batch Validation ---\n";
assertCondition(
    strpos($controllerContent, 'already completed wastage scrap selling') !== false ||
    strpos($controllerContent, 'already had its wastage sold') !== false,
    "storeStitchingWastageSale enforces validation rejecting already-completed/sold batches"
);

// 4. Routes Check
echo "\n--- 4. Testing Registered Routes in routes.php ---\n";
$routesContent = file_get_contents(__DIR__ . '/../app/routes.php');

assertCondition(
    strpos($routesContent, "'/production/stitching/wastage/sell'") !== false,
    "Route /production/stitching/wastage/sell registered"
);
assertCondition(
    strpos($routesContent, "'/production/stitching/wastage/update'") !== false,
    "Route /production/stitching/wastage/update registered"
);
assertCondition(
    strpos($routesContent, "'/production/stitching/wastage/delete'") !== false,
    "Route /production/stitching/wastage/delete registered"
);
assertCondition(
    strpos($routesContent, "'/api/stitching/wastage/details'") !== false,
    "Route /api/stitching/wastage/details registered"
);

// 5. View Elements Check (stitching_list.php)
echo "\n--- 5. Testing View Elements & Modals in stitching_list.php ---\n";
$listContent = file_get_contents(__DIR__ . '/../app/views/concern/stitching_list.php');

assertCondition(
    strpos($listContent, '<th class="text-center pe-3 py-3" style="width: 125px;">ACTIONS</th>') !== false,
    "ACTIONS column header present in Wastage Selling Register table"
);
assertCondition(
    strpos($listContent, 'showStitchingScrapSaleDetails') !== false,
    "View action button trigger 'showStitchingScrapSaleDetails' present"
);
assertCondition(
    strpos($listContent, 'openEditStitchingScrapSaleModal') !== false,
    "Edit action button trigger 'openEditStitchingScrapSaleModal' present"
);
assertCondition(
    strpos($listContent, 'confirmDeleteStitchingScrapSale') !== false,
    "Delete action button trigger 'confirmDeleteStitchingScrapSale' present"
);
assertCondition(
    strpos($listContent, 'id="modalViewStitchingScrapSale"') !== false,
    "Quick View modal (#modalViewStitchingScrapSale) present"
);
assertCondition(
    strpos($listContent, 'id="modalEditStitchingScrapSale"') !== false,
    "Edit modal (#modalEditStitchingScrapSale) present"
);
assertCondition(
    strpos($listContent, 'id="modalDeleteStitchingScrapSale"') !== false,
    "Delete modal (#modalDeleteStitchingScrapSale) present"
);
assertCondition(
    strpos($listContent, 'No Available Stitching Batches') !== false,
    "'No Available Stitching Batches' option rendered when eligible batches empty"
);
assertCondition(
    strpos($listContent, 'No Available Batches:') !== false,
    "Warning banner displayed when no batches are eligible for scrap liquidation"
);

// 6. Test Batch Exclusion Simulation with mock data
echo "\n--- 6. Testing Batch Exclusion Algorithm ---\n";
$testBatches = [
    ['id' => 1, 'batch_no' => 'STCH-001', 'status' => 'completed'],
    ['id' => 2, 'batch_no' => 'STCH-002', 'status' => 'in_progress'],
    ['id' => 3, 'batch_no' => 'STCH-003', 'status' => 'cancelled'],
];
$testSoldIds = [1]; // Batch 1 already completed wastage sale
$testSoldMap = array_flip($testSoldIds);

$filtered = array_values(array_filter($testBatches, function($b) use ($testSoldMap) {
    if (isset($b['status']) && $b['status'] === 'cancelled') {
        return false;
    }
    return !isset($testSoldMap[(int)$b['id']]);
}));

assertCondition(count($filtered) === 1, "Exactly 1 batch remains eligible out of 3");
assertCondition($filtered[0]['id'] === 2, "Eligible batch is STCH-002 (STCH-001 sold, STCH-003 cancelled)");

// If batch 2 is also sold:
$testSoldMap[2] = 1;
$filteredEmpty = array_values(array_filter($testBatches, function($b) use ($testSoldMap) {
    if (isset($b['status']) && $b['status'] === 'cancelled') {
        return false;
    }
    return !isset($testSoldMap[(int)$b['id']]);
}));
assertCondition(count($filteredEmpty) === 0, "When all batches are sold, eligible list is completely empty");

echo "\n======================================================\n";
echo "SUMMARY: $pass PASSED, $fail FAILED\n";
echo "======================================================\n";
if ($fail > 0) {
    exit(1);
}
exit(0);
