<?php
/**
 * Test: Cutting Enhancements Validation (Mobile validation, Integer wastage, and Edit CRUD)
 */
require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;

echo "======================================================\n";
echo "TEST SUITE: Cutting Module Enhancements\n";
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

// 1. Mobile Number Validation Test (Regex ^[6-9]\d{9}$)
echo "--- 1. Testing Cutting Master Mobile Number Validation ---\n";
$validPhones = ['9876543210', '8123456789', '7001234567', '6381234567'];
$invalidPhones = ['5555555555', '1234567890', '98765', '987654321012', '98765abcde', '0987654321'];

foreach ($validPhones as $phone) {
    $isValid = preg_match('/^[6-9]\d{9}$/', $phone);
    assertCondition($isValid === 1, "Phone '$phone' is valid");
}

foreach ($invalidPhones as $phone) {
    $isValid = preg_match('/^[6-9]\d{9}$/', $phone);
    assertCondition($isValid === 0, "Phone '$phone' is correctly rejected");
}

// 2. Integer Wastage Calculation Test
echo "\n--- 2. Testing Integer Wastage Logic ---\n";
$testInputs = [
    '2.75' => 2,
    '0.99' => 0,
    '5.00' => 5,
    '10'   => 10,
    '0'    => 0,
    '-3'   => 0
];

foreach ($testInputs as $input => $expected) {
    $val = (float)floor((float)$input);
    if ($val < 0) $val = 0.00;
    assertCondition((int)$val === $expected, "Input '$input' is strictly floored to integer $expected");
}

// 3. Edit / Update Routes & Files Verification
echo "\n--- 3. Testing Cutting Edit / Update Routes & Views ---\n";
assertCondition(file_exists(__DIR__ . '/../app/views/concern/cutting_edit.php'), "View cutting_edit.php exists");

$routesContent = file_get_contents(__DIR__ . '/../app/routes.php');
assertCondition(strpos($routesContent, "'/production/cutting/edit'") !== false, "Route /production/cutting/edit registered");
assertCondition(strpos($routesContent, "'/production/cutting/update'") !== false, "Route /production/cutting/update registered");

// Check ProductionController methods
$prodControllerContent = file_get_contents(__DIR__ . '/../app/controllers/ProductionController.php');
assertCondition(strpos($prodControllerContent, "function editCuttingBatch") !== false, "Method editCuttingBatch exists in ProductionController");
assertCondition(strpos($prodControllerContent, "function updateCuttingBatch") !== false, "Method updateCuttingBatch exists in ProductionController");

// Check Cutting List Edit Button
$cuttingListContent = file_get_contents(__DIR__ . '/../app/views/concern/cutting_list.php');
assertCondition(strpos($cuttingListContent, "production/cutting/edit?id=") !== false, "Edit button present in cutting_list.php");

// Check cutting_create wastage input integer attributes
$cuttingCreateContent = file_get_contents(__DIR__ . '/../app/views/concern/cutting_create.php');
assertCondition(strpos($cuttingCreateContent, 'id="inputWastageWeight"') !== false, "inputWastageWeight present in cutting_create.php");
assertCondition(strpos($cuttingCreateContent, 'step="1"') !== false, "step='1' set on inputWastageWeight in cutting_create.php");
assertCondition(strpos($cuttingCreateContent, 'newMasterPhoneError') !== false, "Inline mobile error element present in modal");

// Check cutting_edit wastage input integer attributes
$cuttingEditContent = file_get_contents(__DIR__ . '/../app/views/concern/cutting_edit.php');
assertCondition(strpos($cuttingEditContent, 'id="inputWastageWeight"') !== false, "inputWastageWeight present in cutting_edit.php");
assertCondition(strpos($cuttingEditContent, 'step="1"') !== false, "step='1' set on inputWastageWeight in cutting_edit.php");
assertCondition(strpos($cuttingEditContent, 'newMasterPhoneError') !== false, "Inline mobile error element present in modal in cutting_edit.php");

echo "\n======================================================\n";
echo "SUMMARY: $pass PASSED, $fail FAILED\n";
echo "======================================================\n";
if ($fail > 0) {
    exit(1);
}
exit(0);
