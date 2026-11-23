<?php
/**
 * Test: Stitching Enhancements Validation (View CRUD operation and Tailor mobile number validation)
 */
require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;

echo "======================================================\n";
echo "TEST SUITE: Stitching Module Enhancements\n";
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
echo "--- 1. Testing Tailor / Stitching Master Mobile Validation ---\n";
$validPhones = ['9876543210', '8123456789', '7001234567', '6381234567'];
$invalidPhones = ['5555555555', '1234567890', '98765', '987654321012', '98765abcde', '0987654321', '67889999988888'];

foreach ($validPhones as $phone) {
    $isValid = preg_match('/^[6-9]\d{9}$/', $phone);
    assertCondition($isValid === 1, "Phone '$phone' is valid");
}

foreach ($invalidPhones as $phone) {
    $isValid = preg_match('/^[6-9]\d{9}$/', $phone);
    assertCondition($isValid === 0, "Phone '$phone' is correctly rejected");
}

// Check apiQuickAddTailor in ProductionController
$prodControllerContent = file_get_contents(__DIR__ . '/../app/controllers/ProductionController.php');
assertCondition(
    strpos($prodControllerContent, "function apiQuickAddTailor") !== false,
    "apiQuickAddTailor method exists in ProductionController"
);
assertCondition(
    strpos($prodControllerContent, "/^[6-9]\d{9}$/") !== false,
    "Mobile number regex validation present in ProductionController"
);

// Check modal & inline phone validation in stitching_create.php
$stitchingCreateContent = file_get_contents(__DIR__ . '/../app/views/concern/stitching_create.php');
assertCondition(
    strpos($stitchingCreateContent, 'id="quickTailorPhone"') !== false,
    "quickTailorPhone input present in stitching_create.php"
);
assertCondition(
    strpos($stitchingCreateContent, 'id="quickTailorPhoneError"') !== false,
    "Inline phone error element present in stitching_create.php"
);
assertCondition(
    strpos($stitchingCreateContent, 'validateTailorPhoneInline') !== false,
    "validateTailorPhoneInline JS function present in stitching_create.php"
);

// Check modal & inline phone validation in stitching_edit.php
$stitchingEditContent = file_get_contents(__DIR__ . '/../app/views/concern/stitching_edit.php');
assertCondition(
    strpos($stitchingEditContent, 'id="quickTailorPhone"') !== false,
    "quickTailorPhone input present in stitching_edit.php"
);
assertCondition(
    strpos($stitchingEditContent, 'id="quickTailorPhoneError"') !== false,
    "Inline phone error element present in stitching_edit.php"
);
assertCondition(
    strpos($stitchingEditContent, 'validateTailorPhoneInline') !== false,
    "validateTailorPhoneInline JS function present in stitching_edit.php"
);

// 2. Stitching View CRUD Operation Test
echo "\n--- 2. Testing Stitching View CRUD Operation ---\n";
assertCondition(
    file_exists(__DIR__ . '/../app/views/concern/stitching_view.php'),
    "View template stitching_view.php exists"
);

$routesContent = file_get_contents(__DIR__ . '/../app/routes.php');
assertCondition(
    strpos($routesContent, "'/production/stitching/view'") !== false,
    "Route /production/stitching/view registered in routes.php"
);
assertCondition(
    strpos($routesContent, "'/m/stitching_wastage/view'") !== false,
    "Route /m/stitching_wastage/view registered in routes.php"
);

assertCondition(
    strpos($prodControllerContent, "function viewStitchingBatch") !== false,
    "viewStitchingBatch method exists in ProductionController"
);

$stitchingListContent = file_get_contents(__DIR__ . '/../app/views/concern/stitching_list.php');
assertCondition(
    strpos($stitchingListContent, "/production/stitching/view?id=") !== false,
    "View action button linking to /production/stitching/view present in stitching_list.php"
);
assertCondition(
    strpos($stitchingListContent, 'bi-eye') !== false,
    "bi-eye icon present in stitching_list.php action buttons"
);

echo "\n======================================================\n";
echo "SUMMARY: $pass PASSED, $fail FAILED\n";
echo "======================================================\n";
if ($fail > 0) {
    exit(1);
}
exit(0);
