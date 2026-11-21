<?php
// app/controllers/FinanceController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Services\CashService;
use Exception;

class FinanceController extends Controller {
    // 1. WAREHOUSE OPERATIONAL EXPENSES & PETTY CASH
    public function expense(): void {
        $cid = Auth::concernId();

        // 1. Filter Parameters
        $filterConcernId = isset($_GET['concern_id']) && $_GET['concern_id'] !== '' ? (int)$_GET['concern_id'] : ($cid ?: null);
        $fromDate = !empty($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
        $toDate   = !empty($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
        $category = trim($_GET['category'] ?? '');
        $payMode  = trim($_GET['payment_mode'] ?? '');
        $search   = trim($_GET['search'] ?? '');

        self::syncUnlinkedExpenses($filterConcernId);

        // 2. Base Query
        $sql = "SELECT e.*, ca.name AS account_name, u.name AS user_name,
                       con.name AS concern_name, con.code AS concern_code
                FROM expenses e
                LEFT JOIN cash_accounts ca ON e.account_id = ca.id
                LEFT JOIN users u ON e.created_by = u.id
                LEFT JOIN concerns con ON e.concern_id = con.id
                WHERE 1=1";
        $params = [];

        if ($filterConcernId) {
            $sql .= " AND e.concern_id = ?";
            $params[] = $filterConcernId;
        }

        if (!empty($fromDate)) {
            $sql .= " AND e.expense_date >= ?";
            $params[] = $fromDate;
        }

        if (!empty($toDate)) {
            $sql .= " AND e.expense_date <= ?";
            $params[] = $toDate;
        }

        if (!empty($category) && $category !== 'All Categories') {
            $sql .= " AND e.category = ?";
            $params[] = $category;
        }

        if (!empty($payMode) && $payMode !== 'All Modes') {
            $sql .= " AND e.payment_mode = ?";
            $params[] = $payMode;
        }

        if (!empty($search)) {
            $sql .= " AND (e.expense_no LIKE ? OR e.paid_to LIKE ? OR e.item_particulars LIKE ? OR e.description LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY e.expense_date DESC, e.id DESC";
        $expenses = DB::fetchAll($sql, $params);

        // 3. Compute KPI Aggregates
        $totalOutflow = 0.00;
        $cashDisbursed = 0.00;
        $bankDisbursed = 0.00;
        $pendingPayables = 0.00;

        foreach ($expenses as $exp) {
            $amt = (float)$exp['amount'];
            $st  = strtolower($exp['status'] ?? 'paid');
            $pm  = strtolower($exp['payment_mode'] ?? 'cash');

            if ($st === 'pending') {
                $pendingPayables += $amt;
            } else {
                $totalOutflow += $amt;
                if (str_contains($pm, 'cash') || str_contains($pm, 'petty')) {
                    $cashDisbursed += $amt;
                } elseif (str_contains($pm, 'upi') || str_contains($pm, 'bank') || str_contains($pm, 'neft') || str_contains($pm, 'online') || str_contains($pm, 'card')) {
                    $bankDisbursed += $amt;
                } else {
                    $cashDisbursed += $amt;
                }
            }
        }

        // Available concerns & accounts
        $allConcerns = DB::fetchAll("SELECT * FROM concerns WHERE status = 'active' ORDER BY id ASC");
        if ($filterConcernId) {
            $accounts = DB::fetchAll("SELECT * FROM cash_accounts WHERE concern_id = ? AND status = 'active'", [$filterConcernId]);
        } else {
            $accounts = DB::fetchAll("SELECT * FROM cash_accounts WHERE status = 'active'");
        }

        // Dynamic Expense Categories from database (no hardcoded defaults)
        $catRows = DB::fetchAll("SELECT name FROM expense_categories WHERE status = 'active' ORDER BY name ASC");
        $categoriesList = array_values(array_unique(array_filter(array_column($catRows, 'name'))));

        $paymentModes = [
            'Cash',
            'UPI / QR',
            'Bank Transfer / NEFT',
            'Card / POS',
            'Pending / Credit'
        ];

        $this->render('concern/expense', [
            'pageTitle'        => 'Warehouse Operational Expenses & Petty Cash',
            'currentModule'    => 'expense',
            'expenses'         => $expenses,
            'accounts'         => $accounts,
            'allConcerns'      => $allConcerns,
            'totalOutflow'     => $totalOutflow,
            'cashDisbursed'    => $cashDisbursed,
            'bankDisbursed'    => $bankDisbursed,
            'pendingPayables'  => $pendingPayables,
            'filterConcernId'  => $filterConcernId,
            'fromDate'         => $fromDate,
            'toDate'           => $toDate,
            'category'         => $category,
            'payMode'          => $payMode,
            'search'           => $search,
            'categoriesList'   => $categoriesList,
            'paymentModes'     => $paymentModes,
            'selectedCid'      => $cid,
        ]);
    }

    public function storeExpense(): void {
        $this->validateCsrf();
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);
        if (!$cid && Auth::isSuperAdmin()) {
            $cid = (int)DB::fetchValue("SELECT id FROM concerns WHERE status = 'active' ORDER BY id ASC LIMIT 1") ?: 1;
        }

        if (!$cid) {
            $this->flash('error', 'Please choose a warehouse / concern.');
            $this->redirect('/m/expense');
            return;
        }

        $expDate         = $_POST['expense_date'] ?: date('Y-m-d');
        $category        = trim($_POST['category'] ?? 'Miscellaneous');
        $desc            = trim($_POST['description'] ?? '');
        $particulars     = trim($_POST['item_particulars'] ?? ($desc ?: $category));
        $paidTo          = trim($_POST['paid_to'] ?? '');
        $amount          = (float)($_POST['amount'] ?? 0);
        $payMode         = trim($_POST['payment_mode'] ?? 'Cash');
        $status          = trim($_POST['status'] ?? 'Paid');
        $accountId       = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;

        if ($amount <= 0) {
            $this->flash('error', 'Expense amount must be greater than zero.');
            $this->redirect('/m/expense');
            return;
        }

        // Auto-resolve account if not explicitly specified
        if (!$accountId && $cid) {
            $payModeLower = strtolower($payMode);
            if (str_contains($payModeLower, 'bank') || str_contains($payModeLower, 'neft') || str_contains($payModeLower, 'upi') || str_contains($payModeLower, 'card') || str_contains($payModeLower, 'online')) {
                $acc = DB::fetch(
                    "SELECT id FROM cash_accounts WHERE concern_id = ? AND account_type IN ('bank', 'upi') AND status = 'active' ORDER BY id ASC LIMIT 1",
                    [$cid]
                );
                if ($acc) $accountId = (int)$acc['id'];
            }
            if (!$accountId) {
                $acc = DB::fetch(
                    "SELECT id FROM cash_accounts WHERE concern_id = ? AND account_type IN ('cash', 'petty_cash') AND status = 'active' ORDER BY id ASC LIMIT 1",
                    [$cid]
                ) ?: DB::fetch("SELECT id FROM cash_accounts WHERE concern_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1", [$cid]);
                if ($acc) $accountId = (int)$acc['id'];
            }
            if (!$accountId) {
                DB::query(
                    "INSERT INTO cash_accounts (concern_id, name, account_type, account_number, balance, status, created_at)
                     VALUES (?, 'Main Cash Drawer', 'cash', 'DRAWER-01', 0.00, 'active', NOW())",
                    [$cid]
                );
                $accountId = (int)DB::lastInsertId();
            }
        }

        DB::beginTransaction();
        try {
            $prefix = 'WH-EXP-' . date('Ymd') . '-';
            $expNo = $prefix . rand(1000, 9999);

            // Ensure category exists in expense_categories table
            if (!empty($category)) {
                $catExists = DB::fetch("SELECT id FROM expense_categories WHERE LOWER(name) = LOWER(?)", [$category]);
                if (!$catExists) {
                    DB::query(
                        "INSERT INTO expense_categories (concern_id, name, status, created_at) VALUES (?, ?, 'active', NOW())",
                        [$cid, $category]
                    );
                }
            }

            DB::query(
                "INSERT INTO expenses (concern_id, expense_no, expense_date, category, item_particulars, paid_to, amount, payment_mode, status, account_id, description, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$cid, $expNo, $expDate, $category, $particulars, $paidTo, $amount, $payMode, $status, $accountId, $desc, Auth::id()]
            );
            $expId = (int)DB::lastInsertId();

            // If Paid and linked to cash account, post debit transaction to Cash Flow
            if (strtolower($status) !== 'pending' && $accountId) {
                CashService::postTransaction(
                    $cid,
                    $accountId,
                    $expDate,
                    'debit',
                    $amount,
                    'Warehouse Expense',
                    $expId,
                    "Voucher #{$expNo} [{$category}]: {$particulars}" . ($paidTo ? " ({$paidTo})" : ''),
                    Auth::id()
                );
            }

            DB::commit();
            $this->flash('success', "Warehouse Expense Voucher '{$expNo}' recorded for ₹" . number_format($amount, 2) . ".");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to record expense voucher: ' . $e->getMessage());
        }

        $this->redirect('/m/expense');
    }

    public function updateExpense(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $cid = Auth::concernId();

        $expense = DB::fetch("SELECT * FROM expenses WHERE id = ?", [$id]);
        if (!$expense) {
            $this->flash('error', 'Expense voucher not found.');
            $this->redirect('/m/expense');
            return;
        }

        if ($cid && (int)$expense['concern_id'] !== $cid && !Auth::isSuperAdmin()) {
            $this->flash('error', 'Unauthorized access to this voucher.');
            $this->redirect('/m/expense');
            return;
        }

        $concernId   = (int)$expense['concern_id'];
        $expDate     = $_POST['expense_date'] ?: $expense['expense_date'];
        $category    = trim($_POST['category'] ?? $expense['category']);
        $paidTo      = trim($_POST['paid_to'] ?? '');
        $particulars = trim($_POST['item_particulars'] ?? $expense['item_particulars']);
        $amount      = (float)($_POST['amount'] ?? 0);
        $payMode     = trim($_POST['payment_mode'] ?? $expense['payment_mode']);
        $status      = trim($_POST['status'] ?? $expense['status']);
        $desc        = trim($_POST['description'] ?? '');
        $accountId   = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : (int)($expense['account_id'] ?? 0);

        if ($amount <= 0) {
            $this->flash('error', 'Expense amount must be greater than zero.');
            $this->redirect('/m/expense');
            return;
        }

        // Auto-resolve account if still missing
        if (!$accountId && $concernId) {
            $payModeLower = strtolower($payMode);
            if (str_contains($payModeLower, 'bank') || str_contains($payModeLower, 'neft') || str_contains($payModeLower, 'upi') || str_contains($payModeLower, 'card') || str_contains($payModeLower, 'online')) {
                $acc = DB::fetch(
                    "SELECT id FROM cash_accounts WHERE concern_id = ? AND account_type IN ('bank', 'upi') AND status = 'active' ORDER BY id ASC LIMIT 1",
                    [$concernId]
                );
                if ($acc) $accountId = (int)$acc['id'];
            }
            if (!$accountId) {
                $acc = DB::fetch(
                    "SELECT id FROM cash_accounts WHERE concern_id = ? AND account_type IN ('cash', 'petty_cash') AND status = 'active' ORDER BY id ASC LIMIT 1",
                    [$concernId]
                ) ?: DB::fetch("SELECT id FROM cash_accounts WHERE concern_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1", [$concernId]);
                if ($acc) $accountId = (int)$acc['id'];
            }
            if (!$accountId) {
                DB::query(
                    "INSERT INTO cash_accounts (concern_id, name, account_type, account_number, balance, status, created_at)
                     VALUES (?, 'Main Cash Drawer', 'cash', 'DRAWER-01', 0.00, 'active', NOW())",
                    [$concernId]
                );
                $accountId = (int)DB::lastInsertId();
            }
        }

        DB::beginTransaction();
        try {
            // Ensure category exists
            if (!empty($category)) {
                $catExists = DB::fetch("SELECT id FROM expense_categories WHERE LOWER(name) = LOWER(?)", [$category]);
                if (!$catExists) {
                    DB::query("INSERT INTO expense_categories (concern_id, name, status, created_at) VALUES (?, ?, 'active', NOW())", [$concernId, $category]);
                }
            }

            DB::query(
                "UPDATE expenses SET expense_date = ?, category = ?, item_particulars = ?, paid_to = ?, amount = ?, payment_mode = ?, status = ?, account_id = ?, description = ? WHERE id = ?",
                [$expDate, $category, $particulars, $paidTo, $amount, $payMode, $status, $accountId ?: null, $desc, $id]
            );

            // Reconcile linked cash transaction in Cash Flow
            $existingTx = DB::fetch(
                "SELECT * FROM cash_transactions WHERE ref_module IN ('Warehouse Expense', 'Expense') AND ref_id = ? LIMIT 1",
                [$id]
            );

            if (strtolower($status) === 'pending') {
                if ($existingTx) {
                    if ($existingTx['type'] === 'debit') {
                        DB::query("UPDATE cash_accounts SET balance = balance + ? WHERE id = ?", [(float)$existingTx['amount'], (int)$existingTx['account_id']]);
                    }
                    DB::query("DELETE FROM cash_transactions WHERE id = ?", [(int)$existingTx['id']]);
                }
            } else {
                // Status is Paid
                $descText = "Voucher #{$expense['expense_no']} [{$category}]: {$particulars}" . ($paidTo ? " ({$paidTo})" : '');
                if ($existingTx) {
                    if ($existingTx['type'] === 'debit') {
                        DB::query("UPDATE cash_accounts SET balance = balance + ? WHERE id = ?", [(float)$existingTx['amount'], (int)$existingTx['account_id']]);
                    }
                    DB::query("UPDATE cash_accounts SET balance = balance - ? WHERE id = ?", [$amount, $accountId]);
                    DB::query(
                        "UPDATE cash_transactions SET account_id = ?, txn_date = ?, amount = ?, description = ? WHERE id = ?",
                        [$accountId, $expDate, $amount, $descText, (int)$existingTx['id']]
                    );
                } else {
                    CashService::postTransaction(
                        $concernId,
                        $accountId,
                        $expDate,
                        'debit',
                        $amount,
                        'Warehouse Expense',
                        $id,
                        $descText,
                        Auth::id()
                    );
                }
            }

            DB::commit();
            $this->flash('success', "Expense voucher '{$expense['expense_no']}' updated successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to update expense voucher: ' . $e->getMessage());
        }

        $this->redirect('/m/expense');
    }

    public function deleteExpense(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $cid = Auth::concernId();

        $expense = DB::fetch("SELECT * FROM expenses WHERE id = ?", [$id]);
        if (!$expense) {
            $this->flash('error', 'Expense voucher not found.');
            $this->redirect('/m/expense');
            return;
        }

        if ($cid && (int)$expense['concern_id'] !== $cid && !Auth::isSuperAdmin()) {
            $this->flash('error', 'Unauthorized access to delete this voucher.');
            $this->redirect('/m/expense');
            return;
        }

        DB::beginTransaction();
        try {
            // Reconcile and delete linked cash transaction
            $linkedTx = DB::fetch(
                "SELECT * FROM cash_transactions WHERE ref_module IN ('Warehouse Expense', 'Expense') AND ref_id = ? LIMIT 1",
                [$id]
            );
            if ($linkedTx) {
                if ($linkedTx['type'] === 'debit') {
                    DB::query("UPDATE cash_accounts SET balance = balance + ? WHERE id = ?", [(float)$linkedTx['amount'], (int)$linkedTx['account_id']]);
                }
                DB::query("DELETE FROM cash_transactions WHERE id = ?", [(int)$linkedTx['id']]);
            }

            // Delete expense record
            DB::query("DELETE FROM expenses WHERE id = ?", [$id]);

            DB::commit();
            $this->flash('success', "Expense voucher '{$expense['expense_no']}' has been deleted.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to delete expense: ' . $e->getMessage());
        }

        $this->redirect('/m/expense');
    }

    public static function syncUnlinkedExpenses(?int $cid = null): void {
        $sql = "SELECT e.* FROM expenses e
                WHERE LOWER(e.status) != 'pending'
                  AND e.amount > 0
                  AND e.id NOT IN (
                      SELECT ref_id FROM cash_transactions 
                      WHERE ref_module IN ('Warehouse Expense', 'Expense')
                  )";
        $params = [];
        if ($cid) {
            $sql .= " AND e.concern_id = ?";
            $params[] = $cid;
        }
        $unlinked = DB::fetchAll($sql, $params);
        if (empty($unlinked)) return;

        foreach ($unlinked as $exp) {
            $concernId = (int)$exp['concern_id'];
            $accountId = !empty($exp['account_id']) ? (int)$exp['account_id'] : 0;

            if (!$accountId) {
                $payModeLower = strtolower($exp['payment_mode'] ?? '');
                if (str_contains($payModeLower, 'bank') || str_contains($payModeLower, 'neft') || str_contains($payModeLower, 'upi') || str_contains($payModeLower, 'card') || str_contains($payModeLower, 'online')) {
                    $acc = DB::fetch(
                        "SELECT id FROM cash_accounts WHERE concern_id = ? AND account_type IN ('bank', 'upi') AND status = 'active' ORDER BY id ASC LIMIT 1",
                        [$concernId]
                    );
                    if ($acc) $accountId = (int)$acc['id'];
                }
                if (!$accountId) {
                    $acc = DB::fetch(
                        "SELECT id FROM cash_accounts WHERE concern_id = ? AND account_type IN ('cash', 'petty_cash') AND status = 'active' ORDER BY id ASC LIMIT 1",
                        [$concernId]
                    ) ?: DB::fetch("SELECT id FROM cash_accounts WHERE concern_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1", [$concernId]);
                    if ($acc) $accountId = (int)$acc['id'];
                }
                if (!$accountId) {
                    DB::query(
                        "INSERT INTO cash_accounts (concern_id, name, account_type, account_number, balance, status, created_at)
                         VALUES (?, 'Main Cash Drawer', 'cash', 'DRAWER-01', 0.00, 'active', NOW())",
                        [$concernId]
                    );
                    $accountId = (int)DB::lastInsertId();
                }

                DB::query("UPDATE expenses SET account_id = ? WHERE id = ?", [$accountId, (int)$exp['id']]);
            }

            try {
                $part = $exp['item_particulars'] ?: ($exp['description'] ?: $exp['category']);
                $paid = $exp['paid_to'] ? " ({$exp['paid_to']})" : '';
                CashService::postTransaction(
                    $concernId,
                    $accountId,
                    $exp['expense_date'] ?: date('Y-m-d'),
                    'debit',
                    (float)$exp['amount'],
                    'Warehouse Expense',
                    (int)$exp['id'],
                    "Voucher #{$exp['expense_no']} [{$exp['category']}]: {$part}{$paid}",
                    (int)($exp['created_by'] ?: 1)
                );
            } catch (\Throwable $e) {
                error_log("Failed to sync expense #{$exp['id']} to cash flow: " . $e->getMessage());
            }
        }
    }

    public function storeCategory(): void {
        $this->validateCsrf();
        $name = trim($_POST['name'] ?? '');
        $cid  = Auth::concernId();

        if (empty($name)) {
            $this->renderJson(['success' => false, 'message' => 'Category name cannot be empty.'], 400);
            return;
        }

        // Check if category already exists (case-insensitive)
        $exists = DB::fetch("SELECT id, name FROM expense_categories WHERE LOWER(name) = LOWER(?)", [$name]);
        if (!$exists) {
            DB::query(
                "INSERT INTO expense_categories (concern_id, name, status, created_at) VALUES (?, ?, 'active', NOW())",
                [$cid, $name]
            );
            $id = (int)DB::lastInsertId();
        } else {
            $id = (int)$exists['id'];
            $name = $exists['name'];
        }

        $this->renderJson([
            'success' => true,
            'id'      => $id,
            'name'    => $name,
            'message' => 'Category saved successfully.'
        ]);
    }

    public function exportExpenses(): void {
        $cid = Auth::concernId();
        $format          = strtolower(trim($_GET['format'] ?? 'excel'));
        $filterConcernId = isset($_GET['concern_id']) && $_GET['concern_id'] !== '' ? (int)$_GET['concern_id'] : ($cid ?: null);
        $period          = trim($_GET['period'] ?? '');
        $singleDate      = trim($_GET['single_date'] ?? '');
        $rawFromDate     = trim($_GET['from_date'] ?? '');
        $rawToDate       = trim($_GET['to_date'] ?? '');
        $category        = trim($_GET['category'] ?? '');
        $payMode         = trim($_GET['payment_mode'] ?? '');
        $search          = trim($_GET['search'] ?? '');

        if (!empty($period)) {
            $range = \App\Services\ExportService::resolveDateRange($period, $singleDate, $rawFromDate, $rawToDate);
            $fromDate = $range['start_date'];
            $toDate   = $range['end_date'];
            $periodLabel = $range['label'] ?? 'All Time';
        } else {
            $fromDate = !empty($rawFromDate) ? $rawFromDate : date('Y-m-01');
            $toDate   = !empty($rawToDate) ? $rawToDate : date('Y-m-d');
            $periodLabel = date('d M Y', strtotime($fromDate)) . ' to ' . date('d M Y', strtotime($toDate));
        }

        $sql = "SELECT e.*, ca.name AS account_name, u.name AS user_name,
                       con.name AS concern_name, con.code AS concern_code
                FROM expenses e
                LEFT JOIN cash_accounts ca ON e.account_id = ca.id
                LEFT JOIN users u ON e.created_by = u.id
                LEFT JOIN concerns con ON e.concern_id = con.id
                WHERE 1=1";
        $params = [];

        if ($filterConcernId) {
            $sql .= " AND e.concern_id = ?";
            $params[] = $filterConcernId;
        }
        if (!empty($fromDate)) {
            $sql .= " AND e.expense_date >= ?";
            $params[] = $fromDate;
        }
        if (!empty($toDate)) {
            $sql .= " AND e.expense_date <= ?";
            $params[] = $toDate;
        }
        if (!empty($category) && $category !== 'All Categories') {
            $sql .= " AND e.category = ?";
            $params[] = $category;
        }
        if (!empty($payMode) && $payMode !== 'All Modes') {
            $sql .= " AND e.payment_mode = ?";
            $params[] = $payMode;
        }
        if (!empty($search)) {
            $sql .= " AND (e.expense_no LIKE ? OR e.paid_to LIKE ? OR e.item_particulars LIKE ? OR e.description LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
        }
        $sql .= " ORDER BY e.expense_date DESC, e.id DESC";
        $expenses = DB::fetchAll($sql, $params);

        $headers = [
            '#', 'Voucher #', 'Warehouse / Concern', 'Category', 'Particulars / Item', 'Paid To / Vendor', 'Payment Mode', 'Status', 'Amount (₹)', 'Date'
        ];

        $exportData = [];
        $totalAmount = 0.00;
        $sl = 1;
        foreach ($expenses as $e) {
            $amt = (float)$e['amount'];
            $totalAmount += $amt;
            $exportData[] = [
                $sl++,
                $e['expense_no'],
                $e['concern_name'] ?? 'Warehouse (WH-01)',
                $e['category'],
                $e['item_particulars'] ?: '-',
                $e['paid_to'] ?: '-',
                $e['payment_mode'],
                $e['status'] ?? 'Paid',
                '₹' . number_format($amt, 2),
                date('d M Y', strtotime($e['expense_date']))
            ];
        }

        $totalsRow = [
            'TOTAL EXPENSES', '', count($exportData) . ' Vouchers', '', '', '', '', '',
            '₹' . number_format($totalAmount, 2), ''
        ];

        $metadata = [
            'Report'       => 'Warehouse Operational Expenses & Petty Cash',
            'Period'       => $periodLabel,
            'Generated By' => Auth::user()['name'] ?? 'User',
            'Total Outflow'=> '₹' . number_format($totalAmount, 2)
        ];

        $filename = 'Warehouse_Expenses_' . date('Ymd_His');
        if ($format === 'excel' || $format === 'xls') {
            \App\Services\ExportService::downloadExcel($filename, 'Warehouse Operational Expenses & Petty Cash', $metadata, $headers, $exportData, $totalsRow);
        } elseif ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Warehouse Operational Expenses & Petty Cash', $metadata, $headers, $exportData, $totalsRow);
        } else {
            \App\Services\ExportService::downloadCsv($filename, $headers, $exportData, $totalsRow);
        }
    }

    // 2. CASH ACCOUNTS (Forward to unified Cash & Cash Flow module)
    public function cash(): void {
        $this->cashFlow();
    }

    public function storeAccount(): void {
        $this->validateCsrf();
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);

        if (!$cid) {
            $this->flash('error', 'Please choose a concern.');
            $this->redirect('/m/cash_flow?tab=accounts');
        }

        $name    = trim($_POST['name'] ?? '');
        $type    = $_POST['account_type'] ?? 'cash';
        $accNo   = trim($_POST['account_number'] ?? '');
        $opening = (float)($_POST['opening_balance'] ?? 0);

        if (empty($name)) {
            $this->flash('error', 'Account Name is required.');
            $this->redirect('/m/cash_flow?tab=accounts');
        }

        DB::query(
            "INSERT INTO cash_accounts (concern_id, name, account_type, account_number, balance, status, created_at)
             VALUES (?, ?, ?, ?, ?, 'active', NOW())",
            [$cid, $name, $type, $accNo, $opening]
        );

        $this->flash('success', "Cash Account '{$name}' created with opening balance of ₹" . number_format($opening, 2) . ".");
        $this->redirect('/m/cash_flow?tab=accounts');
    }

    public function updateAccount(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $account = DB::fetch("SELECT * FROM cash_accounts WHERE id = ?", [$id]);
        if (!$account) {
            $this->flash('error', 'Account not found.');
            $this->redirect('/m/cash_flow?tab=accounts');
        }

        $name   = trim($_POST['name'] ?? '');
        $type   = $_POST['account_type'] ?? $account['account_type'];
        $accNo  = trim($_POST['account_number'] ?? $account['account_number']);
        $status = $_POST['status'] ?? $account['status'];

        if (empty($name)) {
            $this->flash('error', 'Account Name is required.');
            $this->redirect('/m/cash_flow?tab=accounts');
        }

        DB::query(
            "UPDATE cash_accounts SET name = ?, account_type = ?, account_number = ?, status = ? WHERE id = ?",
            [$name, $type, $accNo, $status, $id]
        );

        $this->flash('success', "Account '{$name}' updated successfully.");
        $this->redirect('/m/cash_flow?tab=accounts');
    }

    public function deleteAccount(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $account = DB::fetch("SELECT * FROM cash_accounts WHERE id = ?", [$id]);
        if (!$account) {
            $this->flash('error', 'Account not found.');
            $this->redirect('/m/cash_flow?tab=accounts');
        }

        $txCount = (int)DB::fetchValue("SELECT COUNT(*) FROM cash_transactions WHERE account_id = ?", [$id]);
        if ($txCount > 0) {
            DB::query("UPDATE cash_accounts SET status = 'inactive' WHERE id = ?", [$id]);
            $this->flash('warning', "Account '{$account['name']}' has {$txCount} historical transactions, so it has been set to Inactive instead of deleting.");
        } else {
            DB::query("DELETE FROM cash_accounts WHERE id = ?", [$id]);
            $this->flash('success', "Account '{$account['name']}' deleted successfully.");
        }

        $this->redirect('/m/cash_flow?tab=accounts');
    }

    // 3. UNIFIED CASH & CASH FLOW MODULE
    public function cashFlow(): void {
        $cid = Auth::concernId();

        self::syncUnlinkedExpenses($cid);

        // A. Cash Flow Metrics
        $sqlIn = "SELECT COALESCE(SUM(amount), 0) FROM cash_transactions WHERE type = 'credit'";
        $sqlOut = "SELECT COALESCE(SUM(amount), 0) FROM cash_transactions WHERE type = 'debit'";
        $sqlFeed = "SELECT ct.*, ca.name AS account_name, con.name AS concern_name
                    FROM cash_transactions ct
                    JOIN cash_accounts ca ON ct.account_id = ca.id
                    LEFT JOIN concerns con ON ct.concern_id = con.id";
        
        $params = [];
        if ($cid) {
            $sqlIn .= " AND concern_id = ?";
            $sqlOut .= " AND concern_id = ?";
            $sqlFeed .= " WHERE ct.concern_id = ?";
            $params[] = $cid;
        }
        $sqlFeed .= " ORDER BY ct.txn_date DESC, ct.id DESC";

        $totalInflow = (float)DB::fetchValue($sqlIn, $params);
        $totalOutflow = (float)DB::fetchValue($sqlOut, $params);
        $netFlow = $totalInflow - $totalOutflow;
        $feed = DB::fetchAll($sqlFeed, $params);

        // B. Cash & Bank Accounts
        $sqlAcc = "SELECT ca.*, con.name AS concern_name, con.code AS concern_code,
                          (SELECT COUNT(*) FROM cash_transactions ct WHERE ct.account_id = ca.id) AS tx_count
                   FROM cash_accounts ca
                   LEFT JOIN concerns con ON ca.concern_id = con.id";
        $accParams = [];
        if ($cid) {
            $sqlAcc .= " WHERE ca.concern_id = ?";
            $accParams[] = $cid;
        }
        $sqlAcc .= " ORDER BY ca.id ASC";
        $accounts = DB::fetchAll($sqlAcc, $accParams);

        $sqlTx = "SELECT ct.*, ca.name AS account_name, u.name AS user_name, con.name AS concern_name
                  FROM cash_transactions ct
                  JOIN cash_accounts ca ON ct.account_id = ca.id
                  LEFT JOIN concerns con ON ct.concern_id = con.id
                  LEFT JOIN users u ON ct.created_by = u.id";
        $txParams = [];
        if ($cid) {
            $sqlTx .= " WHERE ct.concern_id = ?";
            $txParams[] = $cid;
        }
        $sqlTx .= " ORDER BY ct.id DESC LIMIT 20";
        $accountTransactions = DB::fetchAll($sqlTx, $txParams);

        $totalCashInHand = array_reduce($accounts, fn($sum, $a) => $sum + $a['balance'], 0);

        // C. Cashier Shift Register Management
        $activeShift = null;
        $shiftInflows = 0.00;
        $shiftOutflows = 0.00;
        $shiftExpectedFloat = 0.00;
        $prevClosedShift = null;
        $shiftsHistory = [];

        if ($cid) {
            $activeShift = DB::fetch(
                "SELECT cs.*, u.name as opener_name, con.name as concern_name 
                 FROM cashier_shifts cs 
                 LEFT JOIN users u ON cs.user_id = u.id 
                 LEFT JOIN concerns con ON cs.concern_id = con.id 
                 WHERE cs.concern_id = ? AND cs.status = 'open' 
                 ORDER BY cs.id DESC LIMIT 1",
                [$cid]
            );

            $prevClosedShift = DB::fetch(
                "SELECT * FROM cashier_shifts 
                 WHERE concern_id = ? AND status = 'closed' 
                 ORDER BY id DESC LIMIT 1",
                [$cid]
            );

            $shiftsHistory = DB::fetchAll(
                "SELECT cs.*, u.name as cashier_user_name, con.name as concern_name 
                 FROM cashier_shifts cs 
                 LEFT JOIN users u ON cs.user_id = u.id 
                 LEFT JOIN concerns con ON cs.concern_id = con.id 
                 WHERE cs.concern_id = ? 
                 ORDER BY cs.id DESC LIMIT 20",
                [$cid]
            );
        } else {
            // Super Admin consolidated view
            $activeShift = DB::fetch(
                "SELECT cs.*, u.name as opener_name, con.name as concern_name 
                 FROM cashier_shifts cs 
                 LEFT JOIN users u ON cs.user_id = u.id 
                 LEFT JOIN concerns con ON cs.concern_id = con.id 
                 WHERE cs.status = 'open' 
                 ORDER BY cs.id DESC LIMIT 1"
            );
            $prevClosedShift = DB::fetch(
                "SELECT * FROM cashier_shifts 
                 WHERE status = 'closed' 
                 ORDER BY id DESC LIMIT 1"
            );
            $shiftsHistory = DB::fetchAll(
                "SELECT cs.*, u.name as cashier_user_name, con.name as concern_name 
                 FROM cashier_shifts cs 
                 LEFT JOIN users u ON cs.user_id = u.id 
                 LEFT JOIN concerns con ON cs.concern_id = con.id 
                 ORDER BY cs.id DESC LIMIT 20"
            );
        }

        if ($activeShift) {
            $shiftCid = (int)$activeShift['concern_id'];
            $shiftInflows = (float)DB::fetchValue(
                "SELECT COALESCE(SUM(amount), 0) FROM cash_transactions 
                 WHERE concern_id = ? AND type = 'credit' AND ref_module != 'Shift Register' AND created_at >= ?",
                [$shiftCid, $activeShift['opened_at']]
            );
            $shiftOutflows = (float)DB::fetchValue(
                "SELECT COALESCE(SUM(amount), 0) FROM cash_transactions 
                 WHERE concern_id = ? AND type = 'debit' AND created_at >= ?",
                [$shiftCid, $activeShift['opened_at']]
            );
            $shiftExpectedFloat = (float)$activeShift['starting_float'] + $shiftInflows - $shiftOutflows;
        }

        $defaultStartingFloat = $prevClosedShift ? (float)$prevClosedShift['closing_float'] : 0.00;
        $currentUser = Auth::user();
        $currentCashierName = $currentUser['name'] ?? 'System Administrator';

        $this->render('concern/cash_flow', [
            'pageTitle'            => 'Cash & Cash Flow Overview',
            'currentModule'        => 'cash_flow',
            'totalInflow'          => $totalInflow,
            'totalOutflow'         => $totalOutflow,
            'netFlow'              => $netFlow,
            'feed'                 => $feed,
            'accounts'             => $accounts,
            'accountTransactions'  => $accountTransactions,
            'totalCashInHand'      => $totalCashInHand,
            'activeShift'          => $activeShift,
            'shiftInflows'         => $shiftInflows,
            'shiftOutflows'        => $shiftOutflows,
            'shiftExpectedFloat'   => $shiftExpectedFloat,
            'prevClosedShift'      => $prevClosedShift,
            'defaultStartingFloat' => $defaultStartingFloat,
            'shiftsHistory'        => $shiftsHistory,
            'currentCashierName'   => $currentCashierName,
            'selectedCid'          => $cid,
        ]);
    }

    // 4. OPEN CASHIER SHIFT REGISTER
    public function openShift(): void {
        $this->validateCsrf();
        $cid = (int)($_POST['concern_id'] ?? 0) ?: Auth::concernId();

        if (!$cid && Auth::isSuperAdmin()) {
            $cid = Auth::superAdminSelectedConcernId();
        }

        if (!$cid) {
            // Fallback to first active concern so it NEVER fails silently for Super Admin
            $cid = (int)DB::fetchValue("SELECT id FROM concerns WHERE status = 'active' ORDER BY id ASC LIMIT 1") ?: 1;
        }

        // Check if shift is already open for this concern
        $existing = DB::fetch("SELECT id, shift_no FROM cashier_shifts WHERE concern_id = ? AND status = 'open'", [$cid]);
        if ($existing) {
            $this->flash('error', "Shift #{$existing['shift_no']} is already currently OPEN. Please close it first before opening a new shift.");
            $this->redirect('/m/cash_flow');
        }

        $cashierName   = trim($_POST['cashier_name'] ?? '');
        if (empty($cashierName)) {
            $cashierName = Auth::user()['name'] ?? 'Cashier';
        }
        $openingNotes  = trim($_POST['opening_notes'] ?? '');
        $startingFloat = (float)($_POST['starting_float'] ?? 0);
        $isModified    = !empty($_POST['is_float_modified']) ? 1 : 0;

        $denoms = [
            '500'   => (int)($_POST['denom_500'] ?? 0),
            '200'   => (int)($_POST['denom_200'] ?? 0),
            '100'   => (int)($_POST['denom_100'] ?? 0),
            '50'    => (int)($_POST['denom_50'] ?? 0),
            '20'    => (int)($_POST['denom_20'] ?? 0),
            '10'    => (int)($_POST['denom_10'] ?? 0),
            '5'     => (int)($_POST['denom_5'] ?? 0),
            'coins' => (float)($_POST['denom_coins'] ?? 0),
        ];
        $denomTotal = ($denoms['500'] * 500) + ($denoms['200'] * 200) + ($denoms['100'] * 100) +
                      ($denoms['50'] * 50) + ($denoms['20'] * 20) + ($denoms['10'] * 10) +
                      ($denoms['5'] * 5) + $denoms['coins'];
        $denoms['total'] = $denomTotal;

        $shiftNo = 'SHF-' . date('Ymd') . '-' . rand(100, 999);
        $variance = ($denomTotal > 0) ? round($denomTotal - $startingFloat, 2) : 0.00;
        $varianceReason = trim($_POST['variance_reason'] ?? '');
        if ($variance != 0 && !empty($varianceReason)) {
            $notesSuffix = " [Mismatch Variance: " . ($variance > 0 ? "+₹" : "-₹") . number_format(abs($variance), 2) . " | Reason: {$varianceReason}]";
            $openingNotes = $openingNotes ? ($openingNotes . $notesSuffix) : ltrim($notesSuffix);
        }

        // If starting float was left at 0 but denominations were counted, use denomination total as effective float
        $effectiveFloat = ($startingFloat > 0) ? $startingFloat : $denomTotal;
        if ($startingFloat <= 0 && $denomTotal > 0) {
            $startingFloat = $denomTotal;
        }

        DB::beginTransaction();
        try {
            DB::query(
                "INSERT INTO cashier_shifts (concern_id, user_id, cashier_name, shift_no, opening_notes, starting_float, is_float_modified, denominations_json, variance, variance_reason, status, opened_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())",
                [$cid, Auth::id(), $cashierName, $shiftNo, $openingNotes, $startingFloat, $isModified, json_encode($denoms), $variance, $varianceReason]
            );

            $shiftId = (int)DB::lastInsertId();

            // Record transaction so starting cash float reflects in cash accounts and cash flow ledger
            if ($effectiveFloat > 0) {
                // Find or create cash till account for this concern
                $cashAccount = DB::fetch(
                    "SELECT id FROM cash_accounts WHERE concern_id = ? AND account_type IN ('cash', 'petty_cash') ORDER BY id ASC LIMIT 1",
                    [$cid]
                );
                if (!$cashAccount) {
                    $cashAccount = DB::fetch("SELECT id FROM cash_accounts WHERE concern_id = ? ORDER BY id ASC LIMIT 1", [$cid]);
                }
                if (!$cashAccount) {
                    DB::query(
                        "INSERT INTO cash_accounts (concern_id, name, account_type, account_number, balance, status, created_at)
                         VALUES (?, 'Cashier Till Drawer', 'cash', 'TILL-MAIN', 0.00, 'active', NOW())",
                        [$cid]
                    );
                    $cashAccountId = (int)DB::lastInsertId();
                } else {
                    $cashAccountId = (int)$cashAccount['id'];
                }

                $txDesc = "Shift #{$shiftNo} Starting Float [Cashier: {$cashierName}]";
                if (!empty($varianceReason)) {
                    $txDesc .= " (Reason: {$varianceReason})";
                }

                CashService::postTransaction(
                    $cid,
                    $cashAccountId,
                    date('Y-m-d'),
                    'credit',
                    $effectiveFloat,
                    'Shift Register',
                    $shiftId,
                    $txDesc,
                    Auth::id()
                );
            }

            \App\Services\AuditLog::log('SHIFT_OPEN', 'cashier_shifts', $shiftId, null, [
                'shift_no'       => $shiftNo,
                'cashier'        => $cashierName,
                'starting_float' => $startingFloat,
                'denom_total'    => $denomTotal,
            ]);

            DB::commit();

            $this->flash('success', "Cashier Shift '{$shiftNo}' opened successfully for {$cashierName} with starting cash float of ₹" . number_format($startingFloat, 2) . "! Till drawer is now OPEN.");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', "Failed to open shift register: " . $e->getMessage());
        }

        $this->redirect('/m/cash_flow');
    }

    // 5. CLOSE CASHIER SHIFT REGISTER
    public function closeShift(): void {
        $this->validateCsrf();
        $cid = (int)($_POST['concern_id'] ?? 0) ?: Auth::concernId();
        if (!$cid && Auth::isSuperAdmin()) {
            $cid = Auth::superAdminSelectedConcernId();
        }

        $activeShift = null;
        if ($cid) {
            $activeShift = DB::fetch(
                "SELECT * FROM cashier_shifts WHERE concern_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1",
                [$cid]
            );
        }
        if (!$activeShift) {
            $activeShift = DB::fetch(
                "SELECT * FROM cashier_shifts WHERE status = 'open' ORDER BY id DESC LIMIT 1"
            );
        }

        if (!$activeShift) {
            $this->flash('error', 'No open shift register found to close.');
            $this->redirect('/m/cash_flow');
        }

        $shiftCid = (int)$activeShift['concern_id'];
        $closingNotes = trim($_POST['closing_notes'] ?? '');
        $closingFloat = (float)($_POST['closing_float'] ?? 0);

        $closingDenoms = [
            '500'   => (int)($_POST['close_denom_500'] ?? 0),
            '200'   => (int)($_POST['close_denom_200'] ?? 0),
            '100'   => (int)($_POST['close_denom_100'] ?? 0),
            '50'    => (int)($_POST['close_denom_50'] ?? 0),
            '20'    => (int)($_POST['close_denom_20'] ?? 0),
            '10'    => (int)($_POST['close_denom_10'] ?? 0),
            '5'     => (int)($_POST['close_denom_5'] ?? 0),
            'coins' => (float)($_POST['close_denom_coins'] ?? 0),
        ];
        $denomTotal = ($closingDenoms['500'] * 500) + ($closingDenoms['200'] * 200) + ($closingDenoms['100'] * 100) +
                      ($closingDenoms['50'] * 50) + ($closingDenoms['20'] * 20) + ($closingDenoms['10'] * 10) +
                      ($closingDenoms['5'] * 5) + $closingDenoms['coins'];
        $closingDenoms['total'] = $denomTotal;

        // Calculate expected float (excluding opening shift float itself)
        $inflows = (float)DB::fetchValue(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_transactions 
             WHERE concern_id = ? AND type = 'credit' AND ref_module != 'Shift Register' AND created_at >= ?",
            [$shiftCid, $activeShift['opened_at']]
        );
        $outflows = (float)DB::fetchValue(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_transactions 
             WHERE concern_id = ? AND type = 'debit' AND created_at >= ?",
            [$shiftCid, $activeShift['opened_at']]
        );
        $expected = (float)$activeShift['starting_float'] + $inflows - $outflows;
        $variance = $closingFloat - $expected;

        DB::query(
            "UPDATE cashier_shifts SET 
                closing_notes = ?,
                closing_float = ?,
                closing_denominations_json = ?,
                expected_closing_float = ?,
                variance = ?,
                status = 'closed',
                closed_at = NOW()
             WHERE id = ?",
            [$closingNotes, $closingFloat, json_encode($closingDenoms), $expected, $variance, $activeShift['id']]
        );

        \App\Services\AuditLog::log('SHIFT_CLOSE', 'cashier_shifts', $activeShift['id'], null, [
            'shift_no'      => $activeShift['shift_no'],
            'closing_float' => $closingFloat,
            'expected'      => $expected,
            'variance'      => $variance,
        ]);

        $varMsg = $variance == 0 ? "Perfect Exact Match!" : ("Variance: " . ($variance > 0 ? "+₹" : "-₹") . number_format(abs($variance), 2));
        $this->flash('success', "Shift '{$activeShift['shift_no']}' closed successfully. {$varMsg}");
        $this->redirect('/m/cash_flow?tab=shift');
    }

    // 6. EXPORT CASH & CASH FLOW (Excel, PDF, CSV)
    public function exportCashFlow(): void {
        $cid = Auth::concernId();
        $format     = strtolower(trim($_GET['format'] ?? 'excel'));
        $period     = trim($_GET['period'] ?? 'all');
        $singleDate = trim($_GET['single_date'] ?? '');
        $fromDate   = trim($_GET['from_date'] ?? '');
        $toDate     = trim($_GET['to_date'] ?? '');

        $range = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT ct.*, ca.name AS account_name, u.name AS user_name, con.name AS concern_name
                FROM cash_transactions ct
                JOIN cash_accounts ca ON ct.account_id = ca.id
                LEFT JOIN concerns con ON ct.concern_id = con.id
                LEFT JOIN users u ON ct.created_by = u.id
                WHERE 1=1";
        $params = [];

        if ($cid) {
            $sql .= " AND ct.concern_id = ?";
            $params[] = $cid;
        }

        if (!empty($range['start_date']) && !empty($range['end_date'])) {
            $sql .= " AND ct.txn_date >= ? AND ct.txn_date <= ?";
            $params[] = $range['start_date'];
            $params[] = $range['end_date'];
        }

        $sql .= " ORDER BY ct.txn_date DESC, ct.id DESC";
        $transactions = DB::fetchAll($sql, $params);

        $headers = [
            '#', 'Transaction Date', 'Concern', 'Account Name', 'Flow Type', 'Ref Module', 'Description / Particulars', 'Cash In (+₹)', 'Cash Out (-₹)', 'Handled By'
        ];

        $exportData = [];
        $totalIn = 0.00;
        $totalOut = 0.00;
        $sl = 1;

        foreach ($transactions as $tx) {
            $amt = (float)$tx['amount'];
            $inStr = '-';
            $outStr = '-';

            if ($tx['type'] === 'credit') {
                $totalIn += $amt;
                $inStr = '₹' . number_format($amt, 2);
            } else {
                $totalOut += $amt;
                $outStr = '₹' . number_format($amt, 2);
            }

            $exportData[] = [
                $sl++,
                date('d M Y', strtotime($tx['txn_date'])),
                $tx['concern_name'] ?? 'Primary Enterprise',
                $tx['account_name'],
                strtoupper($tx['type']) === 'CREDIT' ? 'CASH INFLOW' : 'CASH OUTFLOW',
                $tx['ref_module'],
                $tx['description'],
                $inStr,
                $outStr,
                $tx['user_name'] ?? 'System'
            ];
        }

        $netPosition = $totalIn - $totalOut;
        $totalsRow = [
            'TOTAL FLOWS', '', count($exportData) . ' Transactions', '', 'NET: ₹' . number_format($netPosition, 2), '', '',
            '₹' . number_format($totalIn, 2),
            '₹' . number_format($totalOut, 2),
            ''
        ];

        $concernName = $cid ? (Auth::concern()['name'] ?? 'Concern') : 'All Concerns (Consolidated)';
        $metadata = [
            'Scope'        => $concernName,
            'Period Scope' => $range['label'],
            'Generated By' => Auth::user()['name'] ?? 'Super Administrator',
            'Net Liquid'   => ($netPosition >= 0 ? '+₹' : '-₹') . number_format(abs($netPosition), 2)
        ];

        $cleanPeriod = preg_replace('/[^a-zA-Z0-9_-]/', '_', $range['period']);
        $filename = 'Cash_Flow_Statement_' . $cleanPeriod . '_' . date('Ymd_His');

        if ($format === 'excel' || $format === 'xls' || $format === 'xlsx') {
            \App\Services\ExportService::downloadExcel($filename, 'Cash & Cash Flow Statement & Ledger', $metadata, $headers, $exportData, $totalsRow);
        } elseif ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Cash & Cash Flow Statement & Ledger', $metadata, $headers, $exportData, $totalsRow);
        } else {
            \App\Services\ExportService::downloadCsv($filename, $headers, $exportData, $totalsRow);
        }
    }
}
