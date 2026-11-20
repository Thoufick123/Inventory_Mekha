<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Controllers\PurchaseController;

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

echo "=== Supplier Optional Email & Purchase Billing Required Fields Verification ===\n";

// 1. Check Supplier View HTML
$supplierViewContent = file_get_contents(__DIR__ . '/../app/views/concern/suppliers.php');
assertCondition(str_contains($supplierViewContent, 'Email Address <span class="text-muted small fw-normal">(Optional)</span>'), "Supplier create modal has Optional label for Email");
assertCondition(str_contains($supplierViewContent, '<input type="email" name="email" id="supplierEmail" class="form-control" placeholder="vendor@mill.com">'), "Supplier create modal input does not have required attribute");
assertCondition(str_contains($supplierViewContent, '<input type="email" name="email" id="editSupplierEmail" class="form-control" placeholder="vendor@mill.com">'), "Supplier edit modal input does not have required attribute");

// 2. Check Purchase Billing Create View HTML
$billingViewContent = file_get_contents(__DIR__ . '/../app/views/concern/purchase_billing_create.php');
assertCondition(str_contains($billingViewContent, 'Invoice / Bill Number <span class="text-danger">*</span>'), "Purchase billing has required asterisk for Invoice / Bill Number");
assertCondition(str_contains($billingViewContent, 'name="supplier_invoice_no" id="billSupplierInvoiceNo" class="form-control" placeholder="e.g. INV-2026-8891" value="<?= htmlspecialchars($isEdit ? ($bill[\'supplier_invoice_no\'] ?? \'\') : \'\') ?>" required>'), "Purchase billing input for supplier_invoice_no has required attribute");
assertCondition(str_contains($billingViewContent, 'Tax Invoice / Bill Attachment <span class="text-danger">*</span>'), "Purchase billing has required asterisk for Tax Invoice / Bill Attachment");
assertCondition(str_contains($billingViewContent, 'if (selectedFiles.length === 0 && !hasExistingAttachments) {'), "Purchase billing JS validation checks that at least one attachment is provided when no existing attachments exist");

// 3. Check Supplier Creation with Optional Email via PurchaseController
$concern = DB::fetch("SELECT id FROM concerns LIMIT 1");
$cid = (int)$concern['id'];

$uniqueSuffix = time() . '_' . mt_rand(100, 999);
$testPhone = '98' . str_pad((string)mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);

\App\Core\Auth::startSession();
$user = DB::fetch("SELECT * FROM users WHERE concern_id = ? AND status = 'active' LIMIT 1", [$cid]);
$_SESSION['user_id'] = $user['id'];
$_SESSION['concern_id'] = $cid;
$_SESSION['role'] = $user['role'] ?? 'concern_admin';

// Test creating supplier with empty email
$_POST = [
    'csrf_token' => 'test',
    'concern_id' => $cid,
    'name' => 'Test Vendor ' . $uniqueSuffix,
    'company_name' => 'Test Vendor Mills ' . $uniqueSuffix,
    'phone' => $testPhone,
    'email' => '', // Empty email
    'gstin' => '',
    'address' => '123 Textile Street',
    'district' => 'Karur',
    'state' => 'Tamil Nadu'
];

// Mock csrf
$_SESSION['csrf_token'] = 'test';

$controller = new class extends PurchaseController {
    public array $flashes = [];
    public ?string $redirectUrl = null;

    protected function validateCsrf(): void {}
    protected function flash(string $type, string $message): void {
        $this->flashes[$type] = $message;
    }
    protected function redirect(string $url): void {
        $this->redirectUrl = $url;
    }
};

$controller->storeSupplier();
$createdSupplier = DB::fetch("SELECT * FROM suppliers WHERE phone = ? LIMIT 1", [$testPhone]);
assertCondition(!empty($createdSupplier), "Supplier successfully created with empty email");
assertCondition($createdSupplier['email'] === null || $createdSupplier['email'] === '', "Supplier email stored as null or empty in database");

// Test updating supplier with empty email
if ($createdSupplier) {
    $_POST = [
        'csrf_token' => 'test',
        'supplier_id' => $createdSupplier['id'],
        'name' => 'Updated Vendor ' . $uniqueSuffix,
        'company_name' => 'Updated Mills ' . $uniqueSuffix,
        'phone' => $testPhone,
        'email' => '', // Empty email
        'gstin' => '',
        'address' => '456 Textile Lane',
        'district' => 'Tirupur',
        'state' => 'Tamil Nadu',
        'status' => 'active'
    ];
    $controller->updateSupplier();
    $updatedSupplier = DB::fetch("SELECT * FROM suppliers WHERE id = ?", [$createdSupplier['id']]);
    assertCondition($updatedSupplier['name'] === 'Updated Vendor ' . $uniqueSuffix, "Supplier update succeeded with empty email");
    assertCondition($updatedSupplier['email'] === null || $updatedSupplier['email'] === '', "Supplier email remains null/empty after update");
    
    // Clean up created test supplier
    DB::query("DELETE FROM suppliers WHERE id = ?", [$createdSupplier['id']]);
}

// 4. Test Purchase Bill creation validation for Invoice No & Attachment
$_POST = [
    'csrf_token' => 'test',
    'concern_id' => $cid,
    'supplier_id' => 1,
    'supplier_invoice_no' => '', // EMPTY invoice number
    'bill_date' => date('Y-m-d'),
    'rolls' => [
        [
            'fabric_name' => 'Test Cotton',
            'weight' => 50,
            'rate' => 100,
            'rq' => 1
        ]
    ]
];
$_FILES = [];

$billController = new class extends PurchaseController {
    public array $flashes = [];
    public ?string $redirectUrl = null;

    protected function validateCsrf(): void {}
    protected function flash(string $type, string $message): void {
        $this->flashes[$type] = $message;
    }
    protected function redirect(string $url): void {
        $this->redirectUrl = $url;
    }
};

$billController->storeBill();
assertCondition(
    isset($billController->flashes['error']) && str_contains($billController->flashes['error'], 'Invoice / Bill Number is required'),
    "storeBill rejects empty invoice / bill number"
);

// Now test with invoice no provided but no attachments
$_POST['supplier_invoice_no'] = 'INV-TEST-001';
$billController->flashes = [];
$billController->storeBill();
assertCondition(
    isset($billController->flashes['error']) && str_contains($billController->flashes['error'], 'Tax Invoice / Bill Attachment is required'),
    "storeBill rejects submission without invoice attachments"
);

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
