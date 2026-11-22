<?php
// tests/PosCustomerLookupOrderCountTest.php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Core\Auth;
use App\Controllers\SalesController;

class PosCustomerLookupOrderCountTest
{
    private int $passed = 0;
    private int $failed = 0;

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$message}\n";
        }
    }

    public function run(): void
    {
        echo "\n======================================================\n";
        echo "  TEST: POS CUSTOMER LOOKUP & ORDER COUNT TEST        \n";
        echo "======================================================\n\n";

        Auth::startSession();

        $admin = DB::fetch("SELECT * FROM users WHERE concern_id IS NULL AND status = 'active' LIMIT 1");
        if (!$admin) {
            $admin = DB::fetch("SELECT * FROM users WHERE status = 'active' LIMIT 1");
        }
        $_SESSION['user'] = $admin;
        $_SESSION['user_id'] = (int)$admin['id'];
        $_SESSION['concern_id'] = 4;
        $_SESSION['role_id'] = (int)$admin['role_id'];

        $concernId = 4;

        // Ensure we have a test customer
        $testPhone = '9500904638';
        $testCustomer = DB::fetch("SELECT * FROM customers WHERE phone = ? LIMIT 1", [$testPhone]);
        if (!$testCustomer) {
            DB::query(
                "INSERT INTO customers (concern_id, code, name, phone, address, city, status, created_at)
                 VALUES (?, 'CUST-TEST950', 'mekha', ?, 'oiuytrewertyuiur', 'Retail Store', 'active', NOW())",
                [$concernId, $testPhone]
            );
            $testCustomer = DB::fetch("SELECT * FROM customers WHERE phone = ? LIMIT 1", [$testPhone]);
        }

        $custId = (int)$testCustomer['id'];

        // Ensure test customer has at least 3 orders
        $currentOrders = (int)DB::fetchValue("SELECT COUNT(*) FROM sales_invoices WHERE customer_id = ?", [$custId]);
        for ($i = $currentOrders; $i < 3; $i++) {
            $invNo = 'INV-TEST-' . ($i + 1) . '-' . time();
            DB::query(
                "INSERT INTO sales_invoices (concern_id, invoice_no, customer_id, invoice_date, total_amount, tax_amount, discount_amount, net_amount, payment_method, payment_status, created_at)
                 VALUES (?, ?, ?, CURDATE(), 300.00, 36.00, 0.00, 336.00, 'cash', 'paid', NOW())",
                [$concernId, $invNo, $custId]
            );
        }

        $controller = new class extends SalesController {
            public ?array $capturedJson = null;
            public ?int $capturedStatus = null;

            protected function renderJson(array $data, int $statusCode = 200): void {
                $this->capturedJson = $data;
                $this->capturedStatus = $statusCode;
            }
        };

        // Test 1: Empty phone
        $_GET['phone'] = '';
        $controller->customerLookup();
        $this->assert($controller->capturedJson['found'] === false, 'Empty phone lookup returns found: false');

        // Test 2: Non-existent phone
        $_GET['phone'] = '0000000000';
        $controller->customerLookup();
        $this->assert($controller->capturedJson['found'] === false, 'Non-existent phone lookup returns found: false');

        // Test 3: Existing customer lookup by exact phone
        $_GET['phone'] = $testPhone;
        $controller->customerLookup();
        $this->assert($controller->capturedJson['found'] === true, 'Existing customer lookup returns found: true');
        $this->assert(isset($controller->capturedJson['customer']), 'Customer object is present in response');
        $cust = $controller->capturedJson['customer'];
        $this->assert($cust['name'] === 'mekha', 'Customer name is mekha');
        $this->assert($cust['phone'] === $testPhone, 'Customer phone matches');
        $this->assert($cust['address'] === 'oiuytrewertyuiur', 'Customer address matches');
        $this->assert($cust['order_count'] >= 3, 'Customer order_count is at least 3 (returns: ' . $cust['order_count'] . ')');
        $this->assert($cust['total_spent'] > 0, 'Customer total_spent is calculated');

        // Test 4: Multiple suggestions returned in customers list
        $this->assert(!empty($controller->capturedJson['customers']), 'Customers suggestion array is populated');
        $firstSuggest = $controller->capturedJson['customers'][0];
        $this->assert($firstSuggest['order_count'] >= 3, 'First suggestion includes order_count');

        // Test 5: Partial phone search
        $_GET['phone'] = '95009';
        $controller->customerLookup();
        $this->assert($controller->capturedJson['found'] === true, 'Partial phone search returns found: true');
        $this->assert(!empty($controller->capturedJson['customers']), 'Partial phone returns customer suggestions');

        // Test 6: Check billing.php template contains UI elements
        $billingTemplate = file_get_contents(__DIR__ . '/../app/views/concern/billing.php');
        $this->assert(strpos($billingTemplate, 'id="customer_history_badge"') !== false, 'billing.php has #customer_history_badge');
        $this->assert(strpos($billingTemplate, 'id="cust_lookup_dropdown"') !== false, 'billing.php has #cust_lookup_dropdown');
        $this->assert(strpos($billingTemplate, 'posBillingForm.addEventListener(\'keydown\'') !== false, 'billing.php prevents Enter key submit in customer inputs');
        $this->assert(strpos($billingTemplate, 'window.history.replaceState') !== false, 'billing.php removes print_id from URL so refresh does not reload old bill');

        echo "\n======================================================\n";
        echo "  SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }
}

(new PosCustomerLookupOrderCountTest())->run();
