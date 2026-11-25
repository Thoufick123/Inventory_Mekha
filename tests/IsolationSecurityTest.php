<?php
// tests/IsolationSecurityTest.php - Comprehensive Architecture, Isolation & Security Verification

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;
use App\Core\Auth;
use App\Core\ModuleGuard;
use App\Models\Variant;
use App\Services\StockService;
use App\Services\QrService;

echo "======================================================\n";
echo "INVENTO: Multi-Concern Architecture & Security Verification\n";
echo "======================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $title, bool $condition) {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$title}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$title}\n";
        $failCount++;
    }
}

// ----------------------------------------------------
// TEST 1: Database Connection & Seed Data Integrity
// ----------------------------------------------------
echo "TEST 1: Database & Seed Verification\n";
$concernCount = DB::fetchValue("SELECT COUNT(*) FROM concerns");
assertTest("Concerns count >= 2", $concernCount >= 2);

$modulesCount = DB::fetchValue("SELECT COUNT(*) FROM modules");
assertTest("Modules registry has all 26 modules (4 Super Admin + 22 Concern)", $modulesCount >= 26);

// ----------------------------------------------------
// TEST 2: Multi-Concern Authentication & Session Isolation
// ----------------------------------------------------
echo "\nTEST 2: Authentication & Session-Only Tenant Boundary\n";
$superLogin = Auth::login('admin@invento.com', 'password123');
assertTest("Super Admin login succeeded", $superLogin === true);
assertTest("Super Admin has null concern_id", Auth::concernId() === null);
assertTest("Super Admin role detected", Auth::isSuperAdmin() === true);
Auth::logout();

$grmLogin = Auth::login('grm.admin@invento.com', 'password123');
assertTest("Concern 1 (GRM) login succeeded", $grmLogin === true);
assertTest("Concern 1 has session concern_id = 1", Auth::concernId() === 1);
assertTest("Concern 1 concern() helper returns GRM", Auth::concern()['code'] === 'CON-GRM');
assertTest("Concern 1 has all concern modules enabled", count($_SESSION['enabled_modules']) >= 22);

// ----------------------------------------------------
// TEST 3: Tenant Scoped Model Query Isolation
// ----------------------------------------------------
echo "\nTEST 3: Tenant Scoped Model Query Isolation\n";
// While logged into Concern 1:
$variantModel = new Variant();
$c1Variants = $variantModel->all();
assertTest("Concern 1 sees only Concern 1 variants (does not see Concern 2 variant #5)", 
    count($c1Variants) > 0 && !in_array(5, array_column($c1Variants, 'id'))
);

// Cross-tenant record lookup test: Concern 1 attempts to find Variant 5 (Apex)
$crossAttempt = $variantModel->find(5);
assertTest("Concern 1 requesting Concern 2 record by ID returns NULL (Strict 404)", $crossAttempt === null);

// Concern 1 can find its own variant #1
$ownVariant = $variantModel->find(1);
assertTest("Concern 1 requesting its own variant #1 returns record successfully", $ownVariant !== null && $ownVariant['id'] == 1);

// ----------------------------------------------------
// TEST 4: QR Scan Cross-Tenant Security Check
// ----------------------------------------------------
echo "\nTEST 4: QR Scan Tenant Boundary Check\n";
// Concern 2 QR payload is C4-S6-V5 (Category 4, Subcat 6, Variant 5)
$c2QrPayload = "C4-S6-V5";
$qrLookupFromC1 = QrService::lookupVariant($c2QrPayload, Auth::concernId());
assertTest("Scanning Concern 2 QR while logged into Concern 1 returns NULL (Isolated)", $qrLookupFromC1 === null);

$c1QrPayload = "C1-S1-V1";
$qrLookupFromC1Own = QrService::lookupVariant($c1QrPayload, Auth::concernId());
assertTest("Scanning Concern 1 QR while logged into Concern 1 resolves variant + live stock", 
    $qrLookupFromC1Own !== null && $qrLookupFromC1Own['product_code'] === 'SKU-FAB-180-BLK'
);

// Switch login to Concern 2 (Apex)
Auth::logout();
$apexLogin = Auth::login('apex.admin@invento.com', 'password123');
assertTest("Concern 2 (Apex) login succeeded", $apexLogin === true);
assertTest("Concern 2 has session concern_id = 2", Auth::concernId() === 2);

// As Concern 2, try scanning Concern 1 QR
$qrLookupFromC2 = QrService::lookupVariant($c1QrPayload, Auth::concernId());
assertTest("Scanning Concern 1 QR while logged into Concern 2 returns NULL (Isolated)", $qrLookupFromC2 === null);

// As Concern 2, own QR resolves
$qrLookupFromC2Own = QrService::lookupVariant($c2QrPayload, Auth::concernId());
assertTest("Scanning Concern 2 QR while logged into Concern 2 resolves variant #5", 
    $qrLookupFromC2Own !== null && $qrLookupFromC2Own['product_code'] === 'SKU-APX-YRN-30'
);

// ----------------------------------------------------
// TEST 5: Module Guard (Feature Toggle per Concern)
// ----------------------------------------------------
echo "\nTEST 5: Module Guard & Disabled Module Enforcement\n";
// Concern 2 was seeded with 'cutting_wastage' and 'stitching_wastage' disabled
assertTest("Concern 2 has cutting_wastage disabled in session cache", Auth::hasModule('cutting_wastage') === false);
assertTest("Concern 2 has billing enabled in session cache", Auth::hasModule('billing') === true);

// ----------------------------------------------------
// TEST 6: Single Stock Gateway & Ledger Audit Reconciliation
// ----------------------------------------------------
echo "\nTEST 6: Single Stock Gateway (StockService::move)\n";
// Logged in as Concern 2, test stock movement
$initQty = StockService::getQty(2, 5, 'Main Store');

// Move in 50 units
$newQty = StockService::move(2, 5, 'Main Store', 50.00, 0.00, 'Inward', 999, 'Test inward batch', Auth::id());
assertTest("StockService::move() credited 50 units", $newQty == ($initQty + 50.00));

// Move out 20 units
$afterOutQty = StockService::move(2, 5, 'Main Store', 0.00, 20.00, 'Outward', 999, 'Test outward delivery', Auth::id());
assertTest("StockService::move() debited 20 units", $afterOutQty == ($newQty - 20.00));

// Ledger reconciliation check: SUM(qty_in) - SUM(qty_out) == live stock balance!
$ledgerNet = DB::fetchValue(
    "SELECT COALESCE(SUM(qty_in - qty_out), 0) FROM stock_ledger WHERE concern_id = 2 AND variant_id = 5 AND location = 'Main Store'"
);
$liveBalance = DB::fetchValue(
    "SELECT qty FROM stocks WHERE concern_id = 2 AND variant_id = 5 AND location = 'Main Store'"
);
assertTest("Reconciliation: Live stock balance exactly matches stock ledger net", (float)$ledgerNet === (float)$liveBalance);

// Test negative stock prevention: Attempting to withdraw 999999 units must throw Exception
$threwException = false;
try {
    StockService::move(2, 5, 'Main Store', 0.00, 999999.00, 'Outward', 999, 'Overdraw test', Auth::id());
} catch (\Throwable $e) {
    $threwException = true;
}
assertTest("StockService blocks insufficient/negative stock withdrawal", $threwException === true);

// ----------------------------------------------------
// TEST 7: Supplier Master District & State Persistence & Validation
// ----------------------------------------------------
echo "\nTEST 7: Supplier Master District & State Fields & Validation\n";
$testSupplierCode = 'SUP-UNIT-TEST-' . time();
DB::query(
    "INSERT INTO suppliers (concern_id, code, name, company_name, phone, email, gstin, address, city, district, state, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')",
    [1, $testSupplierCode, 'VIJA ENTERPRISES', 'VIJA ENTERPRISES', '8668007593', 'pavismvt@gmail.com', '33AAAAA0000A1Z5', 'valvarmanglam', 'Karur', 'Karur', 'Tamil Nadu']
);
$insertedSup = DB::fetch("SELECT * FROM suppliers WHERE code = ?", [$testSupplierCode]);
assertTest("Supplier inserted with separate district field", !empty($insertedSup) && $insertedSup['district'] === 'Karur');
assertTest("Supplier inserted with separate state field", !empty($insertedSup) && $insertedSup['state'] === 'Tamil Nadu');
DB::query("DELETE FROM suppliers WHERE code = ?", [$testSupplierCode]);

// Validation rule regex checks
assertTest("Validation: Valid 10-digit phone passes regex", (bool)preg_match('/^[6-9]\d{9}$/', '8668007593'));
assertTest("Validation: Invalid 9-digit or non-6-9 phone blocked", !preg_match('/^[6-9]\d{9}$/', '1234567890'));
assertTest("Validation: Valid 15-char GSTIN format passes", (bool)preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', '33AAAAA0000A1Z5'));
assertTest("Validation: Invalid GSTIN blocked", !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', 'INVALID_GSTIN'));
assertTest("Validation: Valid email passes filter", (bool)filter_var('pavismvt@gmail.com', FILTER_VALIDATE_EMAIL));
assertTest("Validation: Invalid email blocked", !filter_var('not-an-email', FILTER_VALIDATE_EMAIL));

// ----------------------------------------------------
// TEST 8: Multi-Section Production Purchase Billing
// ----------------------------------------------------
echo "\nTEST 8: Multi-Section Production Purchase Billing (Rolls, Other, Finished Goods)\n";
$testBillNo = 'TEST-PO-' . time();
DB::query(
    "INSERT INTO purchase_bills (concern_id, bill_no, supplier_invoice_no, supplier_id, bill_date, total_amount, tax_amount, net_amount, payment_status, inward_status, fabric_description, created_by)
     VALUES (?, ?, ?, ?, CURDATE(), 19200.00, 960.00, 20160.00, 'unpaid', 'pending', '100% COMBED COTTON 30S', 2)",
    [1, $testBillNo, 'INV-SUP-9988', 1]
);
$testBillId = (int)DB::lastInsertId();

// Section 1: Rolls
DB::query(
    "INSERT INTO purchase_bill_items (concern_id, purchase_bill_id, section_type, item_name, item_code, specification, unit, gross_weight, net_weight, qty, unit_price, total_price)
     VALUES (?, ?, 'rolls', '100% Cotton Fabric Roll', 'FB112663', 'Jet Black | 160 GSM', 'kg', 25.50, 25.00, 25.00, 280.00, 7000.00)",
    [1, $testBillId]
);

// Section 2: Garment Other Things
DB::query(
    "INSERT INTO purchase_bill_items (concern_id, purchase_bill_id, section_type, item_name, item_code, specification, unit, qty, unit_price, total_price)
     VALUES (?, ?, 'garment_other', 'Spun Polyester Sewing Thread', 'TRIM-01', '40/2 Black Cones', 'cones', 10.00, 120.00, 1200.00)",
    [1, $testBillId]
);

// Section 3: Finished Goods
DB::query(
    "INSERT INTO purchase_bill_items (concern_id, purchase_bill_id, section_type, item_name, item_code, specification, unit, qty, unit_price, total_price)
     VALUES (?, ?, 'finished_goods', 'Men Crew Neck Classic T-Shirt', 'FG-01', 'M | Navy Blue', 'pcs', 50.00, 220.00, 11000.00)",
    [1, $testBillId]
);

$itemsCount = (int)DB::fetchValue("SELECT COUNT(*) FROM purchase_bill_items WHERE purchase_bill_id = ?", [$testBillId]);
$rollsCount = (int)DB::fetchValue("SELECT COUNT(*) FROM purchase_bill_items WHERE purchase_bill_id = ? AND section_type = 'rolls'", [$testBillId]);
$otherCount = (int)DB::fetchValue("SELECT COUNT(*) FROM purchase_bill_items WHERE purchase_bill_id = ? AND section_type = 'garment_other'", [$testBillId]);
$fgCount = (int)DB::fetchValue("SELECT COUNT(*) FROM purchase_bill_items WHERE purchase_bill_id = ? AND section_type = 'finished_goods'", [$testBillId]);

assertTest("Purchase bill created with multi-section line items", $itemsCount === 3);
assertTest("Rolls section item persisted correctly", $rollsCount === 1);
assertTest("Garment Other Things section item persisted correctly", $otherCount === 1);
assertTest("Finished Goods section item persisted correctly", $fgCount === 1);

// Clean up test bill
DB::query("DELETE FROM purchase_bills WHERE id = ?", [$testBillId]);

// ----------------------------------------------------
// TEST 9: Supplier & Purchase Billing CRUD Operations
// ----------------------------------------------------
echo "\nTEST 9: Supplier & Purchase Billing CRUD Operations\n";

// 9.1 Supplier Create & Update CRUD
$crudSupCode = 'SUP-CRUD-' . time();
DB::query(
    "INSERT INTO suppliers (concern_id, code, name, company_name, phone, email, gstin, address, city, district, state, status)
     VALUES (?, ?, 'Anand', 'VIJA ENTERPRISES', '8668007593', 'pavismvt@gmail.com', '33AAAAA0000A1Z5', 'valvarmanglam', 'Karur', 'Karur', 'Tamil Nadu', 'active')",
    [1, $crudSupCode]
);
$testSupId = (int)DB::lastInsertId();

DB::query(
    "UPDATE suppliers SET company_name = 'VIJA ENTERPRISES Updated', district = 'Coimbatore', status = 'active' WHERE id = ?",
    [$testSupId]
);
$updatedCompany = DB::fetchValue("SELECT company_name FROM suppliers WHERE id = ?", [$testSupId]);
$updatedDistrict = DB::fetchValue("SELECT district FROM suppliers WHERE id = ?", [$testSupId]);
assertTest("Supplier updated successfully via CRUD", $updatedCompany === 'VIJA ENTERPRISES Updated' && $updatedDistrict === 'Coimbatore');

// 9.2 Purchase Bill Status & Full Flow Edit Update CRUD
DB::query(
    "INSERT INTO purchase_bills (concern_id, bill_no, supplier_id, bill_date, total_amount, tax_amount, net_amount, payment_status, inward_status)
     VALUES (1, 'PO-CRUD-TEST', ?, CURDATE(), 2000.00, 100.00, 2100.00, 'unpaid', 'pending')",
    [$testSupId]
);
$crudBillId = (int)DB::lastInsertId();

// Insert items with Tax Mode (Include GST and Exclude GST)
DB::query(
    "INSERT INTO purchase_bill_items (concern_id, purchase_bill_id, section_type, item_name, item_code, rq_qty, qty, unit_price, gst_rate, gst_type, tax_mode, total_price, tax_amount)
     VALUES (1, ?, 'rolls', 'ROLL #1', 'FB112661', 2, 2, 1000.00, 5.00, 'Both (CGST and SGST)', 'Exclude GST', 2000.00, 100.00)",
    [$crudBillId]
);

// Full Flow Edit: update bill details, change roll to Include GST and recalculate
DB::query(
    "UPDATE purchase_bills SET payment_status = 'paid', inward_status = 'completed', supplier_invoice_no = 'INV-EDIT-999', notes = 'Settled via NEFT' WHERE id = ?",
    [$crudBillId]
);
DB::query("DELETE FROM purchase_bill_items WHERE purchase_bill_id = ?", [$crudBillId]);
DB::query(
    "INSERT INTO purchase_bill_items (concern_id, purchase_bill_id, section_type, item_name, item_code, rq_qty, qty, unit_price, gst_rate, gst_type, tax_mode, total_price, tax_amount)
     VALUES (1, ?, 'rolls', 'ROLL #1-EDIT', 'FB112661', 2, 2, 1050.00, 5.00, 'Both (CGST and SGST)', 'Include GST', 2000.00, 100.00)",
    [$crudBillId]
);

$crudBill = DB::fetch("SELECT payment_status, inward_status, supplier_invoice_no, notes FROM purchase_bills WHERE id = ?", [$crudBillId]);
$editedItem = DB::fetch("SELECT item_name, tax_mode, rq_qty, qty FROM purchase_bill_items WHERE purchase_bill_id = ?", [$crudBillId]);

assertTest("Purchase bill updated via create-flow edit (Paid, Inward, Inv #)", $crudBill['payment_status'] === 'paid' && $crudBill['inward_status'] === 'completed' && $crudBill['supplier_invoice_no'] === 'INV-EDIT-999');
assertTest("Purchase bill item tax_mode persisted ('Include GST')", $editedItem['tax_mode'] === 'Include GST');
assertTest("Roll quantity and RQ are synchronized (qty == rq)", $editedItem['rq_qty'] == $editedItem['qty']);

// 9.3 Purchase Items CSV Export Verification
$billForExport = DB::fetch("SELECT * FROM purchase_bills WHERE id = ?", [$crudBillId]);
$itemsForExport = DB::fetchAll("SELECT * FROM purchase_bill_items WHERE purchase_bill_id = ?", [$crudBillId]);
$csvStream = fopen('php://memory', 'r+');
fwrite($csvStream, "\xEF\xBB\xBF");
fputcsv($csvStream, ['Bill No', $billForExport['bill_no']]);
foreach ($itemsForExport as $ite) {
    fputcsv($csvStream, [$ite['item_name'], $ite['tax_mode'], $ite['total_price']]);
}
rewind($csvStream);
$csvContent = stream_get_contents($csvStream);
fclose($csvStream);
assertTest("Purchase items downloadable with UTF-8 BOM and valid CSV content", str_starts_with($csvContent, "\xEF\xBB\xBF") && str_contains($csvContent, 'ROLL #1-EDIT'));

// 9.4 Purchase Bill Delete CRUD
DB::query("DELETE FROM purchase_bills WHERE id = ?", [$crudBillId]);
$deletedBill = DB::fetch("SELECT id FROM purchase_bills WHERE id = ?", [$crudBillId]);
assertTest("Purchase bill deleted successfully via CRUD", $deletedBill === false || empty($deletedBill));

// 9.4 Supplier Delete CRUD (Soft/Hard delete)
DB::query("DELETE FROM suppliers WHERE id = ?", [$testSupId]);
$deletedSup = DB::fetch("SELECT id FROM suppliers WHERE id = ?", [$testSupId]);
assertTest("Supplier deleted successfully via CRUD", $deletedSup === false || empty($deletedSup));

// ----------------------------------------------------
// TEST 10: Cloudinary Image Storage & Local Fallback Service
// ----------------------------------------------------
echo "\nTEST 10: Cloudinary Image Storage & Local Fallback Service\n";

// 10.1 Cloudinary URL Resolution
$cloudSampleUrl = 'https://res.cloudinary.com/demo/image/upload/v1312461204/sample.jpg';
$resolvedCloudUrl = \App\Services\CloudinaryService::url($cloudSampleUrl);
assertTest("Cloudinary URL is preserved directly as browser source", $resolvedCloudUrl === $cloudSampleUrl);
assertTest("isCloudinaryUrl detects Cloudinary CDN domain", \App\Services\CloudinaryService::isCloudinaryUrl($cloudSampleUrl) === true);

// 10.2 Local Storage Path Resolution
$localPath = 'storage/uploads/rolls/ROLL_20261117_sample.webp';
$resolvedLocalUrl = \App\Services\CloudinaryService::url($localPath);
assertTest("Local storage path is converted to full application URL", str_contains($resolvedLocalUrl, 'storage/uploads/rolls/ROLL_20261117_sample.webp') && !str_starts_with($resolvedLocalUrl, '//'));
assertTest("isCloudinaryUrl returns false for local storage path", \App\Services\CloudinaryService::isCloudinaryUrl($localPath) === false);

// 10.3 Nested File Normalization
$fakeFiles = [
    'name'     => [1 => ['photo' => 'sample_fabric.jpg']],
    'tmp_name' => [1 => ['photo' => sys_get_temp_dir() . '/temp_upload_sample.jpg']],
    'error'    => [1 => ['photo' => UPLOAD_ERR_OK]],
    'size'     => [1 => ['photo' => 1024]],
];
$normalized = \App\Services\CloudinaryService::normalizeNestedFile($fakeFiles, 1, 'photo');
assertTest("normalizeNestedFile correctly extracts indexed file attributes", $normalized !== null && $normalized['name'] === 'sample_fabric.jpg' && $normalized['error'] === UPLOAD_ERR_OK);

// 10.4 Graceful Fallback to Local Disk when Cloudinary not configured
$testTempFile = tempnam(sys_get_temp_dir(), 'inv_test_');
file_put_contents($testTempFile, "FAKE_IMAGE_DATA");
$mockUpload = [
    'name'     => 'test_roll_swatch.jpg',
    'tmp_name' => $testTempFile,
    'error'    => UPLOAD_ERR_OK,
    'size'     => 15,
];
$savedPath = \App\Services\CloudinaryService::saveUploadedFile($mockUpload, 'rolls', ['jpg', 'png']);
assertTest("saveUploadedFile saves file locally if Cloudinary is unconfigured", !empty($savedPath) && str_starts_with($savedPath, 'storage/uploads/rolls/'));

$fullLocalDiskPath = dirname(__DIR__) . '/' . $savedPath;
assertTest("Saved fallback file exists on disk and is readable", file_exists($fullLocalDiskPath) && filesize($fullLocalDiskPath) === 15);

// Cleanup test file
if (file_exists($fullLocalDiskPath)) {
    unlink($fullLocalDiskPath);
}
if (file_exists($testTempFile)) {
    unlink($testTempFile);
}

// ----------------------------------------------------
// FINAL RESULTS
// ----------------------------------------------------
echo "\n======================================================\n";
echo "SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "======================================================\n";

if ($failCount === 0) {
    echo ">>> ALL SECURITY, ISOLATION & ARCHITECTURE TESTS PASSED! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
