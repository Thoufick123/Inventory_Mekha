<?php
// tests/ExpenseCashFlowInterconnectionTest.php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Core\Auth;
use App\Controllers\FinanceController;

class ExpenseCashFlowInterconnectionTest
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
        echo "  TEST: EXPENSE & CASH FLOW INTERCONNECTION TEST       \n";
        echo "======================================================\n\n";

        Auth::startSession();

        $admin = DB::fetch("SELECT * FROM users WHERE status = 'active' LIMIT 1");
        $_SESSION['user'] = $admin;
        $_SESSION['user_id'] = (int)$admin['id'];
        $_SESSION['concern_id'] = 4;
        $_SESSION['role_id'] = (int)$admin['role_id'];

        $concernId = 4;

        // Step 1: Test automatic sync of existing unlinked expenses
        FinanceController::syncUnlinkedExpenses($concernId);

        $syncedTxCount = (int)DB::fetchValue(
            "SELECT COUNT(*) FROM cash_transactions WHERE ref_module IN ('Warehouse Expense', 'Expense') AND concern_id = ?",
            [$concernId]
        );
        $this->assert($syncedTxCount >= 2, "Existing unlinked expenses were successfully synced to cash_transactions (count: {$syncedTxCount})");

        // Verify total outflows in cash_transactions
        $totalOutflow = (float)DB::fetchValue(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_transactions WHERE type = 'debit' AND concern_id = ?",
            [$concernId]
        );
        $this->assert($totalOutflow >= 5120.00, "Cash Outflows now reflects expenses (Outflow: ₹{$totalOutflow})");

        // Step 2: Create a new Expense via storeExpense() and verify immediate cash flow debit
        $_POST = [
            'csrf_token' => 'dummy',
            'concern_id' => $concernId,
            'expense_date' => date('Y-m-d'),
            'category' => 'Testing Logistics',
            'item_particulars' => 'Courier Delivery to Client',
            'paid_to' => 'DTDC Express',
            'amount' => '250.00',
            'payment_mode' => 'Cash',
            'status' => 'Paid',
            'account_id' => '',
            'description' => 'Test courier disbursement'
        ];

        // Bypass CSRF for controller simulation
        $controller = new class extends FinanceController {
            protected function validateCsrf(): void {}
            protected function redirect(string $url): void {}
            protected function flash(string $type, string $message): void {}
        };

        $controller->storeExpense();

        $newExp = DB::fetch(
            "SELECT * FROM expenses WHERE category = 'Testing Logistics' AND amount = 250.00 ORDER BY id DESC LIMIT 1"
        );
        $this->assert(!empty($newExp), 'New expense created successfully in expenses table');
        $this->assert(!empty($newExp['account_id']), 'New expense was automatically linked to a cash account (ID: ' . ($newExp['account_id'] ?? 'none') . ')');

        $newTx = DB::fetch(
            "SELECT * FROM cash_transactions WHERE ref_module = 'Warehouse Expense' AND ref_id = ?",
            [(int)$newExp['id']]
        );
        $this->assert(!empty($newTx), 'Cash debit transaction was posted immediately to cash_transactions');
        $this->assert((float)$newTx['amount'] === 250.00, 'Cash transaction amount matches expense amount (₹250.00)');
        $this->assert($newTx['type'] === 'debit', 'Cash transaction type is debit (outflow)');

        // Step 3: Test updating expense to new amount and verify cash flow balance adjustment
        $accBefore = (float)DB::fetchValue("SELECT balance FROM cash_accounts WHERE id = ?", [(int)$newExp['account_id']]);

        $_POST = [
            'csrf_token' => 'dummy',
            'id' => $newExp['id'],
            'expense_date' => date('Y-m-d'),
            'category' => 'Testing Logistics',
            'item_particulars' => 'Updated Courier Delivery',
            'paid_to' => 'DTDC Express',
            'amount' => '300.00', // Increased by 50
            'payment_mode' => 'Cash',
            'status' => 'Paid',
            'account_id' => $newExp['account_id'],
            'description' => 'Updated description'
        ];

        $controller->updateExpense();

        $accAfter = (float)DB::fetchValue("SELECT balance FROM cash_accounts WHERE id = ?", [(int)$newExp['account_id']]);
        $this->assert(abs(($accBefore - $accAfter) - 50.00) < 0.01, 'Cash account balance adjusted correctly after expense increase by ₹50');

        $updatedTx = DB::fetch(
            "SELECT * FROM cash_transactions WHERE ref_module = 'Warehouse Expense' AND ref_id = ?",
            [(int)$newExp['id']]
        );
        $this->assert((float)$updatedTx['amount'] === 300.00, 'Cash transaction amount updated to ₹300.00');

        // Step 4: Test deleting expense and verifying cash account refund and transaction deletion
        $accBeforeDelete = (float)DB::fetchValue("SELECT balance FROM cash_accounts WHERE id = ?", [(int)$newExp['account_id']]);

        $_POST = [
            'csrf_token' => 'dummy',
            'id' => $newExp['id']
        ];

        $controller->deleteExpense();

        $accAfterDelete = (float)DB::fetchValue("SELECT balance FROM cash_accounts WHERE id = ?", [(int)$newExp['account_id']]);
        $this->assert(abs(($accAfterDelete - $accBeforeDelete) - 300.00) < 0.01, 'Cash account balance restored/refunded by ₹300 after deleting expense');

        $deletedTx = DB::fetch(
            "SELECT * FROM cash_transactions WHERE ref_module = 'Warehouse Expense' AND ref_id = ?",
            [(int)$newExp['id']]
        );
        $this->assert(empty($deletedTx), 'Linked cash transaction was removed upon expense deletion');

        // Step 5: Verify view markups in expense.php and cash_flow.php
        $expenseView = file_get_contents(__DIR__ . '/../app/views/concern/expense.php');
        $this->assert(strpos($expenseView, 'name="account_id"') !== false, 'expense.php contains account_id selector in modalAddExpense');
        $this->assert(strpos($expenseView, 'id="edit_expense_account_id"') !== false, 'expense.php contains account_id selector in modalEditExpense');
        $this->assert(strpos($expenseView, 'Disbursed From Cash/Bank Account') !== false, 'expense.php contains Disbursed From Cash/Bank Account label');

        $cashFlowView = file_get_contents(__DIR__ . '/../app/views/concern/cash_flow.php');
        $this->assert(strpos($cashFlowView, 'Operating Outflow') !== false, 'cash_flow.php renders Operating Outflow badge for Warehouse Expense');

        echo "\n======================================================\n";
        echo "  SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }
}

(new ExpenseCashFlowInterconnectionTest())->run();
