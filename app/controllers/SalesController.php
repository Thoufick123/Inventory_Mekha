<?php
// app/controllers/SalesController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Core\ModuleGuard;
use App\Services\StockService;
use App\Services\NumberSeries;
use App\Services\AuditLog;
use App\Services\CashService;
use App\Services\QrService;
use App\Services\PosBillingCalculator;
use Exception;

class SalesController extends Controller {
    // 1. CUSTOMERS & CRM DIRECTORY
    public function customers(): void {
        ModuleGuard::enforce('customers', 'view');
        $cid = Auth::concernId();
        if (!$cid && Auth::isSuperAdmin()) {
            $cid = Auth::superAdminSelectedConcernId();
        }

        $sql = "SELECT c.*, con.name AS concern_name, con.code AS concern_code,
                       COALESCE(inv_stats.orders_count, 0) AS orders_count,
                       COALESCE(inv_stats.total_spend, 0.00) AS total_spend,
                       COALESCE(inv_stats.total_pcs, 0) AS total_pcs,
                       inv_stats.last_order_date,
                       inv_stats.last_invoice_no
                FROM customers c
                LEFT JOIN concerns con ON c.concern_id = con.id
                LEFT JOIN (
                    SELECT 
                        si.customer_id,
                        COUNT(DISTINCT si.id) AS orders_count,
                        SUM(si.net_amount) AS total_spend,
                        COALESCE(SUM(sii.qty), 0) AS total_pcs,
                        MAX(si.invoice_date) AS last_order_date,
                        SUBSTRING_INDEX(GROUP_CONCAT(si.invoice_no ORDER BY si.id DESC SEPARATOR '|||'), '|||', 1) AS last_invoice_no
                    FROM sales_invoices si
                    LEFT JOIN sales_invoice_items sii ON sii.sales_invoice_id = si.id
                    GROUP BY si.customer_id
                ) inv_stats ON inv_stats.customer_id = c.id";
        $params = [];
        if ($cid) {
            $sql .= " WHERE c.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY c.id DESC";
        $customers = DB::fetchAll($sql, $params);

        // Aggregate CRM KPIs
        $totalCustomers = count($customers);
        $activeCustomers = 0;
        $inactiveCustomers = 0;
        $totalCustomerRevenue = 0.00;
        $payingCustomers = 0;
        $repeatBuyers = 0;
        $cities = [];

        foreach ($customers as $c) {
            if (($c['status'] ?? 'active') === 'active') {
                $activeCustomers++;
            } else {
                $inactiveCustomers++;
            }

            $spend = (float)($c['total_spend'] ?? 0);
            $orders = (int)($c['orders_count'] ?? 0);
            $totalCustomerRevenue += $spend;

            if ($orders > 0) {
                $payingCustomers++;
            }
            if ($orders >= 2) {
                $repeatBuyers++;
            }

            $city = trim($c['city'] ?? '');
            if (!empty($city) && !in_array($city, $cities)) {
                $cities[] = $city;
            }
        }
        sort($cities);

        $avgCustomerLtv = $payingCustomers > 0 ? ($totalCustomerRevenue / $payingCustomers) : 0.00;
        $repeatCustomerRate = $payingCustomers > 0 ? round(($repeatBuyers / $payingCustomers) * 100, 0) : 0;

        $this->render('concern/customers', [
            'pageTitle'            => 'Customer Directory & CRM',
            'currentModule'        => 'customers',
            'customers'            => $customers,
            'selectedCid'          => $cid,
            'totalCustomers'       => $totalCustomers,
            'activeCustomers'      => $activeCustomers,
            'inactiveCustomers'    => $inactiveCustomers,
            'totalCustomerRevenue' => $totalCustomerRevenue,
            'avgCustomerLtv'       => $avgCustomerLtv,
            'repeatCustomerRate'   => $repeatCustomerRate,
            'repeatBuyers'         => $repeatBuyers,
            'cities'               => $cities,
        ]);
    }

    public function storeCustomer(): void {
        ModuleGuard::enforce('customers', 'add');
        $this->validateCsrf();
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);
        if (!$cid && Auth::isSuperAdmin()) {
            $cid = Auth::superAdminSelectedConcernId();
        }
        if (!$cid) {
            $cid = (int)DB::fetchValue("SELECT id FROM concerns WHERE status = 'active' ORDER BY id ASC LIMIT 1") ?: 1;
        }

        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $gstin   = trim($_POST['gstin'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city    = trim($_POST['city'] ?? '');

        $redirectUrl = (!empty($_SERVER['HTTP_REFERER']) && str_contains($_SERVER['HTTP_REFERER'], '/shop/customers'))
            ? '/shop/customers'
            : '/m/customers';

        if (empty($name)) {
            $this->flash('error', 'Customer name is required.');
            $this->redirect($redirectUrl);
        }

        $code = 'CUST-' . str_pad((string)(DB::fetchValue("SELECT COUNT(*) FROM customers WHERE concern_id = ?", [$cid]) + 1), 3, '0', STR_PAD_LEFT);

        DB::query(
            "INSERT INTO customers (concern_id, code, name, phone, email, gstin, address, city, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())",
            [$cid, $code, $name, $phone, $email, $gstin, $address, $city]
        );

        $this->flash('success', "Customer '{$name}' created with code {$code}.");
        $this->redirect($redirectUrl);
    }

    public function updateCustomer(): void {
        ModuleGuard::enforce('customers', 'edit');
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);

        $redirectUrl = (!empty($_SERVER['HTTP_REFERER']) && str_contains($_SERVER['HTTP_REFERER'], '/shop/customers'))
            ? '/shop/customers'
            : '/m/customers';

        if (!$id) {
            $this->flash('error', 'Customer not found.');
            $this->redirect($redirectUrl);
        }

        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $status  = $_POST['status'] ?? 'active';

        if (empty($name)) {
            $this->flash('error', 'Customer name is required.');
            $this->redirect($redirectUrl);
        }

        $sql = "UPDATE customers SET name = ?, phone = ?, email = ?, address = ?, status = ? WHERE id = ?";
        $params = [$name, $phone, $email, $address, $status, $id];
        if ($cid) {
            $sql .= " AND concern_id = ?";
            $params[] = $cid;
        }

        DB::query($sql, $params);
        $this->flash('success', "Customer '{$name}' updated successfully.");
        $this->redirect($redirectUrl);
    }

    public function deleteCustomer(): void {
        ModuleGuard::enforce('customers', 'delete');
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $cid = Auth::concernId();

        $customer = DB::fetch("SELECT * FROM customers WHERE id = ?", [$id]);
        $redirectUrl = (!empty($_SERVER['HTTP_REFERER']) && str_contains($_SERVER['HTTP_REFERER'], '/shop/customers'))
            ? '/shop/customers'
            : '/m/customers';

        if (!$customer) {
            $this->flash('error', 'Customer not found.');
            $this->redirect($redirectUrl);
        }

        // Check if there are sales invoices or requests linked to this customer
        $invCount = (int)DB::fetchValue("SELECT COUNT(*) FROM sales_invoices WHERE customer_id = ?", [$id]);
        $reqCount = (int)DB::fetchValue("SELECT COUNT(*) FROM customer_requests WHERE customer_id = ?", [$id]);

        if ($invCount > 0 || $reqCount > 0) {
            DB::query("UPDATE customers SET status = 'inactive' WHERE id = ?", [$id]);
            $this->flash('warning', "Customer '{$customer['name']}' has {$invCount} sales invoices and {$reqCount} requests, so status was changed to Inactive instead of deleting.");
        } else {
            DB::query("DELETE FROM customers WHERE id = ?", [$id]);
            $this->flash('success', "Customer '{$customer['name']}' deleted successfully.");
        }

        $this->redirect($redirectUrl);
    }

    public function customerProfile(): void {
        $id = (int)($_GET['id'] ?? 0);
        $cid = Auth::concernId();

        $sql = "SELECT c.*, con.name AS concern_name 
                FROM customers c 
                LEFT JOIN concerns con ON c.concern_id = con.id 
                WHERE c.id = ?";
        $params = [$id];
        if ($cid) {
            $sql .= " AND c.concern_id = ?";
            $params[] = $cid;
        }
        $customer = DB::fetch($sql, $params);
        if (!$customer) {
            $this->renderJson(['success' => false, 'message' => 'Customer not found.'], 404);
            return;
        }

        $invoices = DB::fetchAll(
            "SELECT si.id, si.invoice_no, si.invoice_date, si.net_amount, si.payment_method, si.payment_status,
                    si.is_direct_bill,
                    (SELECT COUNT(*) FROM sales_invoice_items sii WHERE sii.sales_invoice_id = si.id) AS items_count,
                    (SELECT COALESCE(SUM(sii.qty), 0) FROM sales_invoice_items sii WHERE sii.sales_invoice_id = si.id) AS pcs_count
             FROM sales_invoices si
             WHERE si.customer_id = ?
             ORDER BY si.id DESC",
            [$id]
        );

        $totalSpend = array_sum(array_column($invoices, 'net_amount'));
        $ordersCount = count($invoices);
        $totalPcs = array_sum(array_column($invoices, 'pcs_count'));

        $this->renderJson([
            'success'     => true,
            'customer'    => $customer,
            'orders_count'=> $ordersCount,
            'total_spend' => $totalSpend,
            'total_pcs'   => $totalPcs,
            'invoices'    => $invoices,
        ]);
    }

    public function exportCustomers(): void {
        $cid = Auth::concernId();
        if (!$cid && Auth::isSuperAdmin()) {
            $cid = Auth::superAdminSelectedConcernId();
        }

        $format     = strtolower(trim($_GET['format'] ?? 'csv'));
        $period     = trim($_GET['period'] ?? 'all');
        $singleDate = trim($_GET['single_date'] ?? '');
        $fromDate   = trim($_GET['from_date'] ?? '');
        $toDate     = trim($_GET['to_date'] ?? '');
        $filterCity = trim($_GET['city'] ?? '');
        $scope      = trim($_GET['scope'] ?? 'all');

        $range = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        // Concern display details
        $concernName = 'All Concerns (Consolidated)';
        if ($cid) {
            $con = DB::fetch("SELECT name FROM concerns WHERE id = ?", [$cid]);
            if ($con) $concernName = $con['name'];
        }

        // Build SQL with date filter
        $subWhere = "1=1";
        $subParams = [];
        if (!empty($range['start_date']) && !empty($range['end_date'])) {
            $subWhere .= " AND si.invoice_date >= ? AND si.invoice_date <= ?";
            $subParams[] = $range['start_date'];
            $subParams[] = $range['end_date'];
        }

        $sql = "SELECT c.id, c.code, c.name, c.phone, c.email, c.gstin, c.city, c.address, c.status,
                       COALESCE(inv_stats.orders_count, 0) AS orders_count,
                       COALESCE(inv_stats.total_spend, 0.00) AS total_spend,
                       COALESCE(inv_stats.total_pcs, 0) AS total_pcs,
                       inv_stats.last_order_date,
                       inv_stats.last_invoice_no
                FROM customers c
                LEFT JOIN (
                    SELECT 
                        si.customer_id,
                        COUNT(DISTINCT si.id) AS orders_count,
                        SUM(si.net_amount) AS total_spend,
                        COALESCE(SUM(sii.qty), 0) AS total_pcs,
                        MAX(si.invoice_date) AS last_order_date,
                        SUBSTRING_INDEX(GROUP_CONCAT(si.invoice_no ORDER BY si.id DESC SEPARATOR '|||'), '|||', 1) AS last_invoice_no
                    FROM sales_invoices si
                    LEFT JOIN sales_invoice_items sii ON sii.sales_invoice_id = si.id
                    WHERE {$subWhere}
                    GROUP BY si.customer_id
                ) inv_stats ON inv_stats.customer_id = c.id
                WHERE 1=1";
        $params = $subParams;

        if ($cid) {
            $sql .= " AND c.concern_id = ?";
            $params[] = $cid;
        }
        if (!empty($filterCity)) {
            $sql .= " AND c.city = ?";
            $params[] = $filterCity;
        }
        if ($scope === 'orders_only') {
            $sql .= " AND inv_stats.orders_count > 0";
        }
        $sql .= " ORDER BY c.id DESC";

        $rows = DB::fetchAll($sql, $params);

        $headers = [
            '#', 'Customer Code', 'Customer Name', 'Phone', 'City', 'GSTIN',
            'Orders in Period', 'Pieces Bought', 'Total Spend (₹)', 'Avg / Order (₹)', 'Last Order Date', 'Last Invoice #'
        ];

        $exportData = [];
        $totalOrders = 0;
        $totalPcs = 0;
        $grandSpend = 0.00;
        $sl = 1;

        foreach ($rows as $r) {
            $orders = (int)$r['orders_count'];
            $spend = (float)$r['total_spend'];
            $pcs = (int)$r['total_pcs'];
            $avg = $orders > 0 ? round($spend / $orders, 2) : 0.00;

            $totalOrders += $orders;
            $totalPcs += $pcs;
            $grandSpend += $spend;

            $exportData[] = [
                $sl++,
                $r['code'] ?: 'CUST-' . $r['id'],
                $r['name'],
                $r['phone'] ?: '-',
                $r['city'] ?: ($r['address'] ?: '-'),
                $r['gstin'] ?: 'Unregistered',
                $orders,
                $pcs . ' pcs',
                '₹' . number_format($spend, 2),
                '₹' . number_format($avg, 2),
                $r['last_order_date'] ? date('d M Y', strtotime($r['last_order_date'])) : '-',
                $r['last_invoice_no'] ?: '-'
            ];
        }

        $overallAvg = $totalOrders > 0 ? round($grandSpend / $totalOrders, 2) : 0.00;
        $totalsRow = [
            'TOTALS', '', count($exportData) . ' Customers', '', '', '',
            $totalOrders, $totalPcs . ' pcs', '₹' . number_format($grandSpend, 2), '₹' . number_format($overallAvg, 2), '', ''
        ];

        $metadata = [
            'Concern / Outlet' => $concernName,
            'Period Scope'    => $range['label'],
            'City Filter'     => !empty($filterCity) ? $filterCity : 'All Cities',
            'Customers Found' => count($exportData) . ' records',
        ];

        $cleanPeriodName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $range['period']);
        $filename = 'Customer_Directory_' . $cleanPeriodName . '_' . date('Ymd_His');

        if ($format === 'excel' || $format === 'xls' || $format === 'xlsx') {
            \App\Services\ExportService::downloadExcel($filename, 'Customer Directory & CRM Performance Report', $metadata, $headers, $exportData, $totalsRow);
        } elseif ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Customer Directory & CRM Performance Report', $metadata, $headers, $exportData, $totalsRow);
        } else {
            \App\Services\ExportService::downloadCsv($filename, $headers, $exportData, $totalsRow);
        }
    }

    // 2. REQUEST
    public function request(): void {
        $this->redirect('/production/stock-requests');
    }

    public function storeRequest(): void {
        $this->validateCsrf();
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);

        if (!$cid) {
            $this->flash('error', 'Please choose a concern.');
            $this->redirect('/m/request');
        }

        $customerId   = (int)($_POST['customer_id'] ?? 0);
        $variantId    = (int)($_POST['variant_id'] ?? 0);
        $requestedQty = (float)($_POST['requested_qty'] ?? 0);
        $expectedDate = $_POST['expected_date'] ?: date('Y-m-d', strtotime('+7 days'));
        $notes        = trim($_POST['notes'] ?? '');

        if (!$customerId || !$variantId || $requestedQty <= 0) {
            $this->flash('error', 'Customer, Variant and Requested Quantity are required.');
            $this->redirect('/m/request');
        }

        $reqNo = NumberSeries::getNext($cid, 'REQ');

        DB::query(
            "INSERT INTO customer_requests (concern_id, request_no, customer_id, variant_id, requested_qty, expected_date, status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())",
            [$cid, $reqNo, $customerId, $variantId, $requestedQty, $expectedDate, $notes]
        );

        $this->flash('success', "Order Request '{$reqNo}' registered successfully.");
        $this->redirect('/m/request');
    }

    public function updateRequest(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $req = DB::fetch("SELECT * FROM customer_requests WHERE id = ?", [$id]);
        if (!$req) {
            $this->flash('error', 'Order request not found.');
            $this->redirect('/m/request');
        }

        $requestedQty = (float)($_POST['requested_qty'] ?? $req['requested_qty']);
        $expectedDate = $_POST['expected_date'] ?: $req['expected_date'];
        $status       = trim($_POST['status'] ?? $req['status']);
        $notes        = trim($_POST['notes'] ?? $req['notes']);

        try {
            DB::query(
                "UPDATE customer_requests SET requested_qty = ?, expected_date = ?, status = ?, notes = ? WHERE id = ?",
                [$requestedQty, $expectedDate, $status, $notes, $id]
            );
            $this->flash('success', "Order Request '{$req['request_no']}' updated successfully.");
        } catch (Exception $e) {
            $this->flash('error', 'Failed to update request: ' . $e->getMessage());
        }

        $this->redirect('/m/request');
    }

    public function deleteRequest(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $req = DB::fetch("SELECT * FROM customer_requests WHERE id = ?", [$id]);
        if (!$req) {
            $this->flash('error', 'Order request not found.');
            $this->redirect('/m/request');
        }

        try {
            DB::query("DELETE FROM customer_requests WHERE id = ?", [$id]);
            $this->flash('success', "Order Request '{$req['request_no']}' deleted successfully.");
        } catch (Exception $e) {
            $this->flash('error', 'Failed to delete request: ' . $e->getMessage());
        }

        $this->redirect('/m/request');
    }

    // 3. BATCH
    public function batch(): void {
        self::ensureBaseTables();
        $cid = Auth::concernId();

        $sql = "SELECT b.*, v.name AS variant_name, v.sku_code, v.size, v.weight_unit,
                       con.name AS concern_name, con.code AS concern_code,
                       pu.name AS production_unit_name, pu.code AS production_unit_code,
                       u.name AS approver_name
                FROM batches b
                JOIN variants v ON b.variant_id = v.id
                LEFT JOIN concerns con ON b.concern_id = con.id
                LEFT JOIN production_units pu ON b.production_unit_id = pu.id
                LEFT JOIN users u ON b.approved_by = u.id";
        $params = [];
        if ($cid) {
            $sql .= " WHERE b.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY b.id DESC";
        $batches = DB::fetchAll($sql, $params);

        if ($cid) {
            $variants = DB::fetchAll("SELECT id, sku_code, name, size FROM variants WHERE concern_id = ? AND status = 'active'", [$cid]);
            $productionUnits = DB::fetchAll("SELECT id, name, code FROM production_units WHERE concern_id = ? AND is_active = 1", [$cid]);
            $qcInspections = DB::fetchAll("SELECT id, qc_no, variant_id, passed_qty FROM qc_inspections WHERE concern_id = ? AND passed_qty > 0 ORDER BY id DESC LIMIT 50", [$cid]);
        } else {
            $variants = DB::fetchAll("SELECT id, sku_code, name, size FROM variants WHERE status = 'active'");
            $productionUnits = DB::fetchAll("SELECT id, name, code FROM production_units WHERE is_active = 1");
            $qcInspections = DB::fetchAll("SELECT id, qc_no, variant_id, passed_qty FROM qc_inspections WHERE passed_qty > 0 ORDER BY id DESC LIMIT 50");
        }

        $this->render('concern/batch', [
            'pageTitle'       => 'Packaging & Production Batches (PB-XXXXX)',
            'currentModule'   => 'batch',
            'batches'         => $batches,
            'variants'        => $variants,
            'productionUnits' => $productionUnits,
            'qcInspections'   => $qcInspections,
            'selectedCid'     => $cid,
        ]);
    }

    public function storeBatch(): void {
        $this->validateCsrf();
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);

        if (!$cid) {
            $this->flash('error', 'Please choose a concern.');
            $this->redirect('/m/batch');
        }

        $variantId = (int)($_POST['variant_id'] ?? 0);
        $qty       = (float)($_POST['batch_qty'] ?? 0);
        $mfgDate   = $_POST['mfg_date'] ?: date('Y-m-d');
        $expDate   = !empty($_POST['exp_date']) ? $_POST['exp_date'] : null;
        $productionUnitId = !empty($_POST['production_unit_id']) ? (int)$_POST['production_unit_id'] : null;
        $qcBatchId = !empty($_POST['qc_batch_id']) ? (int)$_POST['qc_batch_id'] : null;
        $totalBoxes = !empty($_POST['total_boxes']) ? (int)$_POST['total_boxes'] : 0;

        if (!$variantId || $qty <= 0) {
            $this->flash('error', 'Variant and Batch Quantity are required.');
            $this->redirect('/m/batch');
        }

        $batchNo = \App\Models\Batch::generateBatchNo($cid);

        DB::query(
            "INSERT INTO batches (concern_id, production_unit_id, qc_batch_id, batch_no, variant_id, batch_qty, total_boxes, mfg_date, exp_date, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT', NOW())",
            [$cid, $productionUnitId, $qcBatchId, $batchNo, $variantId, $qty, $totalBoxes, $mfgDate, $expDate]
        );

        $this->flash('success', "Packaging Batch '{$batchNo}' created in DRAFT status ({$totalBoxes} boxes). Supervisor approval required to credit available stock.");
        $this->redirect('/m/batch');
    }

    /**
     * Supervisor Verification Gate: Approve batch and register stock
     */
    public function approveBatch(): void {
        $this->validateCsrf();
        $batchId = (int)($_POST['id'] ?? 0);
        try {
            \App\Models\Batch::approveBatch($batchId, Auth::id());
            $this->flash('success', "Batch #{$batchId} APPROVED! Finished goods credited to Available Stock ledger.");
        } catch (\Throwable $e) {
            $this->flash('error', "Batch approval failed: " . $e->getMessage());
        }
        $this->redirect('/m/batch');
    }

    public function updateBatch(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $batch = DB::fetch("SELECT * FROM batches WHERE id = ?", [$id]);
        if (!$batch) {
            $this->flash('error', 'Batch not found.');
            $this->redirect('/m/batch');
        }

        $qty     = (float)($_POST['batch_qty'] ?? $batch['batch_qty']);
        $mfgDate = $_POST['mfg_date'] ?: $batch['mfg_date'];
        $expDate = !empty($_POST['exp_date']) ? $_POST['exp_date'] : $batch['exp_date'];
        $status  = trim($_POST['status'] ?? $batch['status']);

        try {
            DB::query(
                "UPDATE batches SET batch_qty = ?, mfg_date = ?, exp_date = ?, status = ? WHERE id = ?",
                [$qty, $mfgDate, $expDate, $status, $id]
            );
            $this->flash('success', "Batch lot '{$batch['batch_no']}' updated successfully.");
        } catch (Exception $e) {
            $this->flash('error', 'Failed to update batch: ' . $e->getMessage());
        }

        $this->redirect('/m/batch');
    }

    public function deleteBatch(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $batch = DB::fetch("SELECT * FROM batches WHERE id = ?", [$id]);
        if (!$batch) {
            $this->flash('error', 'Batch not found.');
            $this->redirect('/m/batch');
        }

        try {
            DB::query("DELETE FROM batches WHERE id = ?", [$id]);
            $this->flash('success', "Batch lot '{$batch['batch_no']}' deleted successfully.");
        } catch (Exception $e) {
            $this->flash('error', 'Failed to delete batch: ' . $e->getMessage());
        }

        $this->redirect('/m/batch');
    }

    public function billingOverview(): void {
        ModuleGuard::enforce('billing', 'view');
        $cid = Auth::concernId();
        if (!$cid && Auth::isSuperAdmin()) {
            $cid = Auth::superAdminSelectedConcernId();
        }

        $scopeSql = '';
        $scopeParams = [];
        if ($cid) {
            $scopeSql = ' WHERE concern_id = ?';
            $scopeParams[] = $cid;
        }
        $summary = DB::fetch(
            "SELECT COUNT(DISTINCT id) AS all_invoice_count,
                    COUNT(DISTINCT customer_id) AS customer_count,
                    COALESCE(SUM(net_amount), 0) AS all_revenue,
                    COALESCE(SUM(paid_amount), 0) AS paid_revenue,
                    COUNT(DISTINCT CASE WHEN payment_status = 'paid' THEN id END) AS paid_count,
                    COUNT(DISTINCT CASE WHEN payment_status <> 'paid' THEN id END) AS pending_count,
                    COALESCE(SUM(CASE WHEN is_direct_bill = 0 AND payment_method = 'cash'
                        AND (notes IS NULL OR notes NOT LIKE '%[Online Channel]%')
                        THEN paid_amount ELSE 0 END), 0) AS cash_revenue,
                    COALESCE(SUM(CASE WHEN is_direct_bill = 0 AND payment_method = 'cash'
                        AND (notes IS NULL OR notes NOT LIKE '%[Online Channel]%')
                        THEN 1 ELSE 0 END), 0) AS cash_count,
                    COALESCE(SUM(CASE WHEN is_direct_bill = 0 AND (payment_method <> 'cash'
                        OR notes LIKE '%[Online Channel]%'
                        ) THEN net_amount ELSE 0 END), 0) AS online_revenue,
                    COALESCE(SUM(CASE WHEN is_direct_bill = 0 AND (payment_method <> 'cash'
                        OR notes LIKE '%[Online Channel]%'
                        ) THEN 1 ELSE 0 END), 0) AS online_count,
                    COALESCE(SUM(CASE WHEN is_direct_bill = 1 THEN net_amount ELSE 0 END), 0) AS direct_revenue,
                    COUNT(DISTINCT CASE WHEN is_direct_bill = 1 THEN id END) AS direct_count
             FROM sales_invoices{$scopeSql}",
            $scopeParams
        ) ?: [
            'all_invoice_count' => 0,
            'customer_count' => 0,
            'all_revenue' => 0,
            'paid_revenue' => 0,
            'paid_count' => 0,
            'pending_count' => 0,
            'cash_revenue' => 0,
            'cash_count' => 0,
            'online_revenue' => 0,
            'online_count' => 0,
            'direct_revenue' => 0,
            'direct_count' => 0,
        ];

        $statusInput = $_GET['status'] ?? 'all';
        $status = is_string($statusInput) ? trim($statusInput) : 'all';
        if (!in_array($status, ['all', 'paid', 'pending'], true)) {
            $status = 'all';
        }
        $channelInput = $_GET['channel'] ?? 'all';
        $channel = is_string($channelInput) ? trim($channelInput) : 'all';
        if (!in_array($channel, ['all', 'cash', 'online', 'direct'], true)) {
            $channel = 'all';
        }
        $searchInput = $_GET['q'] ?? '';
        $search = is_string($searchInput) ? trim($searchInput) : '';
        $search = function_exists('mb_substr') ? mb_substr($search, 0, 100) : substr($search, 0, 100);
        $dateInput = static function ($value): string {
            if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return '';
            }
            [$year, $month, $day] = array_map('intval', explode('-', $value));
            return checkdate($month, $day, $year) ? $value : '';
        };
        $dateFrom = $dateInput($_GET['from'] ?? '');
        $dateTo = $dateInput($_GET['to'] ?? '');
        $categoryInput = $_GET['category_id'] ?? '';
        $categoryId = is_scalar($categoryInput) && ctype_digit((string)$categoryInput) && (int)$categoryInput > 0
            ? (int)$categoryInput
            : 0;
        $paymentInput = $_GET['payment'] ?? '';
        $payment = is_string($paymentInput) ? trim($paymentInput) : '';
        if (!in_array($payment, ['', 'cash', 'upi', 'card', 'split', 'bank_transfer', 'credit', 'online'], true)) {
            $payment = '';
        }
        $gstInput = $_GET['gst'] ?? '';
        $gst = is_string($gstInput) ? trim($gstInput) : '';
        if (!in_array($gst, ['', 'both', 'igst', 'exempt'], true)) {
            $gst = '';
        }

        $where = [];
        $params = [];
        if ($cid) {
            $where[] = 'si.concern_id = ?';
            $params[] = $cid;
        }
        if ($channel === 'direct') {
            $where[] = 'si.is_direct_bill = 1';
        } elseif (in_array($channel, ['cash', 'online'], true)) {
            $where[] = 'si.is_direct_bill = 0';
        }
        if ($channel === 'cash') {
            $where[] = "si.payment_method = 'cash'
                        AND (si.notes IS NULL OR si.notes NOT LIKE '%[Online Channel]%')";
        } elseif ($channel === 'online') {
            $where[] = "(si.payment_method <> 'cash' OR si.notes LIKE '%[Online Channel]%')";
        }
        if ($status === 'paid') {
            $where[] = "si.payment_status = 'paid'";
        } elseif ($status === 'pending') {
            $where[] = "si.payment_status <> 'paid'";
        }
        if ($dateFrom !== '') {
            $where[] = 'si.invoice_date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = 'si.invoice_date <= ?';
            $params[] = $dateTo;
        }
        if ($categoryId > 0) {
            $where[] = 'EXISTS (
                SELECT 1
                FROM sales_invoice_items category_sii
                JOIN variants category_v ON category_v.id = category_sii.variant_id
                WHERE category_sii.sales_invoice_id = si.id
                  AND category_v.concern_id = si.concern_id
                  AND category_v.category_id = ?
            )';
            $params[] = $categoryId;
        }
        if ($payment !== '') {
            if ($payment === 'online') {
                $where[] = "(si.payment_method <> 'cash' OR si.notes LIKE '%[Online Channel]%')";
            } else {
                $where[] = 'si.payment_method = ?';
                $params[] = $payment;
            }
        }
        if ($gst !== '') {
            $where[] = 'si.notes LIKE ?';
            $params[] = '%GST Type: ' . $gst . '%';
        }
        if ($search !== '') {
            $where[] = "(si.invoice_no LIKE ? OR COALESCE(si.billing_company_name, c.name) LIKE ?
                         OR COALESCE(si.billing_company_phone, c.phone, '') LIKE ? OR CAST(si.net_amount AS CHAR) LIKE ?
                         OR EXISTS (
                             SELECT 1
                             FROM sales_invoice_items search_sii
                             JOIN variants search_v ON search_v.id = search_sii.variant_id
                             WHERE search_sii.sales_invoice_id = si.id
                               AND (search_v.sku_code LIKE ? OR search_v.name LIKE ?)
                         ))";
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term, $term, $term, $term);
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $totalInvoices = (int)DB::fetchValue(
            "SELECT COUNT(DISTINCT si.id)
             FROM sales_invoices si
             JOIN customers c ON c.id = si.customer_id{$whereSql}",
            $params
        );
        $pageSize = 25;
        $totalPages = max(1, (int)ceil($totalInvoices / $pageSize));
        $pageInput = $_GET['page'] ?? 1;
        $requestedPage = is_scalar($pageInput) ? (int)$pageInput : 1;
        $page = min(max(1, $requestedPage), $totalPages);
        $offset = ($page - 1) * $pageSize;
        $invoices = DB::fetchAll(
            "SELECT si.id, si.invoice_no, si.invoice_date, si.net_amount, si.payment_method, si.payment_status, si.is_direct_bill,
            COALESCE(si.billing_company_name, c.name) AS customer_name,
            COALESCE(si.billing_company_phone, c.phone) AS customer_phone,
                    (SELECT COUNT(*) FROM sales_invoice_items sii WHERE sii.sales_invoice_id = si.id) AS items_count
             FROM sales_invoices si
             JOIN customers c ON c.id = si.customer_id{$whereSql}
             ORDER BY si.id DESC
             LIMIT {$pageSize} OFFSET {$offset}",
            $params
        );
        $categorySql = "SELECT DISTINCT cat.id, cat.name
                        FROM categories cat
                        JOIN variants v ON v.category_id = cat.id AND v.concern_id = cat.concern_id
                        WHERE cat.status = 'active'";
        $categoryParams = [];
        if ($cid) {
            $categorySql .= ' AND cat.concern_id = ?';
            $categoryParams[] = $cid;
        }
        $categorySql .= ' ORDER BY cat.name ASC';
        $categories = DB::fetchAll($categorySql, $categoryParams);

        $collectionRate = (float)$summary['all_revenue'] > 0
            ? ((float)$summary['paid_revenue'] / (float)$summary['all_revenue']) * 100
            : 0;
        $this->render('concern/billing_overview', [
            'pageTitle' => 'POS Billing',
            'currentModule' => 'billing',
            'summary' => $summary,
            'collectionRate' => $collectionRate,
            'invoices' => $invoices,
            'status' => $status,
            'channel' => $channel,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'categoryId' => $categoryId,
            'payment' => $payment,
            'gst' => $gst,
            'categories' => $categories,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalInvoices' => $totalInvoices,
            'hasFilters' => $channel !== 'all' || $status !== 'all' || $search !== '' || $dateFrom !== '' || $dateTo !== '' || $categoryId > 0 || $payment !== '' || $gst !== '',
        ]);
    }

    // POS billing terminal (Shop module).
    public function billing(): void {
        ModuleGuard::enforce('billing', 'view');
        \App\Services\StockService::consolidateLegacyLocations();
        $cid = Auth::concernId();
        if ($cid && !DB::fetchValue("SELECT id FROM concerns WHERE id = ? AND status = 'active'", [$cid])) {
            $cid = null;
        }
        if (!$cid && Auth::isSuperAdmin()) {
            $cid = (int)DB::fetchValue("SELECT id FROM concerns WHERE status = 'active' ORDER BY id ASC LIMIT 1") ?: null;
        }

        // Concern display details
        $concernName = 'GRM Retail Flagship Store';
        if ($cid) {
            $con = DB::fetch("SELECT name, code FROM concerns WHERE id = ?", [$cid]);
            if ($con) $concernName = $con['name'];
        } elseif (Auth::isSuperAdmin()) {
            $con = DB::fetch("SELECT name, code FROM concerns WHERE status = 'active' ORDER BY id ASC LIMIT 1");
            if ($con) $concernName = $con['name'];
        }
        $staffName = Auth::user()['name'] ?? 'System Administrator';

        // Load Sales Invoices for History / Print
        $sql = "SELECT si.*, COALESCE(si.billing_company_name, c.name) AS customer_name,
                       COALESCE(si.billing_company_phone, c.phone) AS customer_phone, con.name AS concern_name, con.code AS concern_code,
                       (SELECT COUNT(*) FROM sales_invoice_items sii WHERE sii.sales_invoice_id = si.id) AS items_count
                FROM sales_invoices si
                JOIN customers c ON si.customer_id = c.id
                LEFT JOIN concerns con ON si.concern_id = con.id";
        $params = [];
        if ($cid) {
            $sql .= " WHERE si.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY si.id DESC LIMIT 50";
        $invoices = DB::fetchAll($sql, $params);

        if ($cid) {
            $variants  = DB::fetchAll(
                "SELECT v.id, v.sku_code, v.name, v.price, v.mrp, v.size, v.attributes, v.is_complementary, COALESCE(s.qty, 0) as available_qty
                 FROM variants v
                 LEFT JOIN stocks s ON v.id = s.variant_id AND s.concern_id = ? AND s.location = 'Main Store'
                 WHERE v.concern_id = ? AND v.status = 'active'
                 ORDER BY v.name ASC",
                [$cid, $cid]
            );
        } else {
            $variants  = DB::fetchAll(
                "SELECT v.id, v.sku_code, v.name, v.price, v.mrp, v.size, v.attributes, v.is_complementary, COALESCE(s.qty, 0) as available_qty
                 FROM variants v
                 LEFT JOIN (SELECT variant_id, SUM(qty) as qty FROM stocks WHERE location = 'Main Store' GROUP BY variant_id) s ON v.id = s.variant_id
                 WHERE v.status = 'active'
                 ORDER BY v.name ASC"
            );
        }

        // Print preview if requested
        $printInvoice = null;
        $printItems = [];
        $printId = !empty($_GET['print_id']) ? (int)$_GET['print_id'] : 0;
        if ($printId > 0) {
            $printInvoice = DB::fetch(
                "SELECT si.*,
                        COALESCE(si.billing_company_name, c.name) AS customer_name,
                        COALESCE(si.billing_company_phone, c.phone) AS customer_phone,
                        COALESCE(si.billing_company_email, c.email) AS customer_email,
                        COALESCE(si.billing_company_gstin, c.gstin) AS customer_gstin,
                        COALESCE(si.billing_company_address, c.address) AS customer_address,
                        con.name AS concern_name, con.gstin AS concern_gstin, con.address AS concern_address, con.phone AS concern_phone
                 FROM sales_invoices si
                 JOIN customers c ON si.customer_id = c.id
                 LEFT JOIN concerns con ON si.concern_id = con.id
                 WHERE si.id = ?",
                [$printId]
            );
            if ($printInvoice) {
                $printItems = DB::fetchAll(
                    "SELECT sii.*, v.sku_code,
                            COALESCE(NULLIF(cat.name, ''), v.name) AS item_name,
                            v.name AS variant_name, v.size, b.batch_no
                     FROM sales_invoice_items sii
                     JOIN variants v ON sii.variant_id = v.id
                     LEFT JOIN catalogs cat ON cat.id = v.catalog_id
                     LEFT JOIN batches b ON b.id = sii.production_batch_id
                     WHERE sii.sales_invoice_id = ?",
                    [$printId]
                );
                foreach ($printItems as &$printItem) {
                    $scannedCodes = json_decode((string)($printItem['scanned_codes'] ?? ''), true);
                    $scannedCodeLabels = [];
                    if (is_array($scannedCodes)) {
                        foreach ($scannedCodes as $scannedCode) {
                            if (is_string($scannedCode) && trim($scannedCode) !== '') {
                                $scannedCodeLabels[] = $scannedCode;
                            }
                        }
                    }
                    $printItem['scanned_code_display'] = implode(', ', $scannedCodeLabels);
                }
                unset($printItem);
            }
        }

        $this->render('concern/billing', [
            'pageTitle'     => 'POS Billing Terminal',
            'currentModule' => 'billing',
            'concernName'   => $concernName,
            'staffName'     => $staffName,
            'invoices'      => $invoices,
            'variants'      => $variants,
            'selectedCid'   => $cid,
            'printInvoice'  => $printInvoice,
            'printItems'    => $printItems,
        ]);
    }

    public function variantLookup(): void {
        $query = trim($_GET['q'] ?? '');
        if (empty($query)) {
            $this->renderJson(['success' => false, 'message' => 'Empty search query.'], 400);
            return;
        }

        $cid = Auth::concernId() ?: Auth::superAdminSelectedConcernId();
        if (!$cid && Auth::isSuperAdmin()) {
            $cid = (int)DB::fetchValue("SELECT id FROM concerns WHERE status = 'active' ORDER BY id ASC LIMIT 1") ?: 1;
        }

        $variantId = null;
        $finishedGoodsCode = QrService::parseFinishedGoodsCode($query);
        if ($finishedGoodsCode) {
            if (!$cid || !QrService::lookupVariant($query, (int)$cid)) {
                $this->renderJson(['success' => false, 'message' => "Item '{$query}' not found."], 404);
                return;
            }
            $variantId = (int)$finishedGoodsCode['variant_id'];
        }

        // Check if QR format like C1-S1-V5
        if (!$variantId && preg_match('/V(\d+)$/i', $query, $matches)) {
            $variantId = (int)$matches[1];
        }

        $sql = "SELECT v.id, v.sku_code, v.name, v.price, v.mrp, v.size, v.attributes, v.is_complementary, c.hsn_code AS catalog_hsn, c.gst_rate AS catalog_gst_rate, COALESCE(s.qty, 0) AS available_qty
                FROM variants v
                LEFT JOIN (
                    SELECT variant_id, concern_id, SUM(qty) AS qty
                    FROM stocks
                    WHERE (location = 'Main Store' OR location = 'Main Store (WH-01)')
                    GROUP BY variant_id, concern_id
                ) s ON v.id = s.variant_id AND s.concern_id = ?
                LEFT JOIN catalogs c ON c.id = v.catalog_id AND c.concern_id = v.concern_id
                WHERE v.concern_id = ? AND v.status = 'active'";
        $params = [$cid, $cid];

        if ($variantId) {
            $sql .= " AND v.id = ?";
            $params[] = $variantId;
        } else {
            $sql .= " AND (v.sku_code = ? OR v.name = ? OR v.sku_code LIKE ? OR v.name LIKE ?)";
            $params[] = $query;
            $params[] = $query;
            $params[] = "%{$query}%";
            $params[] = "%{$query}%";
        }
        $sql .= " LIMIT 1";

        $variant = DB::fetch($sql, $params);
        if (!$variant) {
            $this->renderJson(['success' => false, 'message' => "Item '{$query}' not found."], 404);
            return;
        }

        $attrs = json_decode($variant['attributes'] ?? '{}', true) ?: [];
        $hsnCode = !empty($attrs['hsn']) ? $attrs['hsn'] : ($variant['catalog_hsn'] ?? '');
        $gstRate = $variant['catalog_gst_rate'] !== null ? (float)$variant['catalog_gst_rate'] : null;
        if ($gstRate === null && $hsnCode !== '') {
            $masterRate = DB::fetchValue(
                "SELECT gst_rate FROM hsn_masters
                 WHERE is_active = 1 AND code_type = 'HSN' AND hsn_code = ?
                   AND (concern_id = ? OR concern_id IS NULL)
                 ORDER BY concern_id DESC LIMIT 1",
                [$hsnCode, $cid]
            );
            $gstRate = $masterRate === null ? null : (float)$masterRate;
        }

        $this->renderJson([
            'success' => true,
            'variant' => [
                'id'            => (int)$variant['id'],
                'sku_code'      => $variant['sku_code'],
                'name'          => $variant['name'],
                'size'          => $variant['size'] ?: '-',
                'price'         => (int)$variant['is_complementary'] === 1 ? 0.0 : (float)$variant['price'],
                'mrp'           => (float)($variant['mrp'] > 0 ? $variant['mrp'] : $variant['price']),
                'is_complementary' => (int)$variant['is_complementary'] === 1,
                'available_qty' => (float)$variant['available_qty'],
                'hsn_code'      => $hsnCode,
                'gst_pct'       => $gstRate,
            ]
        ]);
    }

    public function customerLookup(): void {
        $phone = trim($_GET['phone'] ?? $_GET['q'] ?? '');
        $cleanPhone = preg_replace('/[^\d]/', '', $phone);
        if (empty($cleanPhone) && empty($phone)) {
            $this->renderJson(['found' => false, 'customers' => []]);
            return;
        }

        $searchQuery = !empty($cleanPhone) ? $cleanPhone : $phone;
        $cid = Auth::concernId() ?: Auth::superAdminSelectedConcernId();

        $sql = "SELECT c.id, c.concern_id, c.code, c.name, c.phone, c.email, c.gstin, c.address, c.city,
                       (SELECT COUNT(*) FROM sales_invoices si WHERE si.customer_id = c.id OR (c.phone != '' AND si.customer_id IN (SELECT c2.id FROM customers c2 WHERE c2.phone = c.phone))) AS order_count,
                       (SELECT COALESCE(SUM(si.net_amount), 0) FROM sales_invoices si WHERE si.customer_id = c.id OR (c.phone != '' AND si.customer_id IN (SELECT c2.id FROM customers c2 WHERE c2.phone = c.phone))) AS total_spent,
                       (SELECT MAX(si.invoice_date) FROM sales_invoices si WHERE si.customer_id = c.id OR (c.phone != '' AND si.customer_id IN (SELECT c2.id FROM customers c2 WHERE c2.phone = c.phone))) AS last_order_date
                FROM customers c
                WHERE (c.phone = ? OR c.phone LIKE ? OR c.name LIKE ?)";
        $params = [$searchQuery, $searchQuery . '%', '%' . $phone . '%'];
        if ($cid) {
            $sql .= " ORDER BY (c.concern_id = ?) DESC, (c.phone = ?) DESC, order_count DESC, c.id DESC LIMIT 5";
            $params[] = $cid;
            $params[] = $searchQuery;
        } else {
            $sql .= " ORDER BY (c.phone = ?) DESC, order_count DESC, c.id DESC LIMIT 5";
            $params[] = $searchQuery;
        }

        $list = DB::fetchAll($sql, $params);
        if (!empty($list)) {
            $formattedList = array_map(function($c) {
                return [
                    'id' => (int)$c['id'],
                    'concern_id' => (int)$c['concern_id'],
                    'name' => $c['name'],
                    'phone' => $c['phone'],
                    'address' => $c['address'] ?: ($c['city'] ?: ''),
                    'city' => $c['city'] ?: '',
                    'gstin' => $c['gstin'] ?: '',
                    'order_count' => (int)$c['order_count'],
                    'total_spent' => (float)$c['total_spent'],
                    'last_order_date' => $c['last_order_date'] ?: null,
                ];
            }, $list);

            $best = $formattedList[0];

            $this->renderJson([
                'found' => true,
                'customer' => $best,
                'customers' => $formattedList,
            ]);
        } else {
            $this->renderJson(['found' => false, 'customers' => []]);
        }
    }

    public function productionBatchLookup(): void {
        $cid = Auth::concernId() ?: Auth::superAdminSelectedConcernId();
        if (!$cid) {
            $this->renderJson(['success' => false, 'message' => 'Select a concern before loading production batches.'], 400);
            return;
        }

        $batches = DB::fetchAll(
            "SELECT b.id, b.batch_no, b.batch_qty, b.variant_id, v.attributes, v.is_complementary,
                    v.name AS product_name, v.sku_code, v.size, v.price, v.mrp,
                    COALESCE(c.gst_rate, cat.default_gst_rate) AS gst_pct,
                    COALESCE(c.hsn_code, '') AS hsn_code,
                    COALESCE(s.qty, 0) AS available_qty,
                    GREATEST(0, b.batch_qty - COALESCE((
                        SELECT SUM(sii.qty) FROM sales_invoice_items sii
                        WHERE sii.production_batch_id = b.id
                    ), 0)) AS batch_remaining_qty
             FROM batches b
             JOIN variants v ON v.id = b.variant_id AND v.concern_id = b.concern_id
             LEFT JOIN catalogs c ON c.id = v.catalog_id AND c.concern_id = v.concern_id
             LEFT JOIN categories cat ON cat.id = v.category_id
             LEFT JOIN stocks s ON s.concern_id = b.concern_id
                               AND s.variant_id = b.variant_id
                               AND s.location = 'Main Store'
             WHERE b.concern_id = ? AND UPPER(b.status) = 'APPROVED'
               AND b.batch_qty > 0 AND COALESCE(s.qty, 0) > 0
             ORDER BY b.created_at DESC, b.id DESC",
            [$cid]
        );

        foreach ($batches as &$batch) {
            $attributes = json_decode($batch['attributes'] ?? '{}', true) ?: [];
            $batch['id'] = (int)$batch['id'];
            $batch['variant_id'] = (int)$batch['variant_id'];
            $batch['batch_qty'] = (float)$batch['batch_qty'];
            $batch['available_qty'] = (float)$batch['available_qty'];
            $batch['batch_remaining_qty'] = (float)$batch['batch_remaining_qty'];
            $batch['is_complementary'] = (int)$batch['is_complementary'] === 1;
            $batch['price'] = $batch['is_complementary'] ? 0.0 : (float)$batch['price'];
            $batch['mrp'] = (float)$batch['mrp'];
            $batch['gst_pct'] = $batch['gst_pct'] === null ? null : (float)$batch['gst_pct'];
            $batch['hsn_code'] = !empty($attributes['hsn']) ? $attributes['hsn'] : $batch['hsn_code'];
            if ($batch['gst_pct'] === null && $batch['hsn_code'] !== '') {
                $masterRate = DB::fetchValue(
                    "SELECT gst_rate FROM hsn_masters
                     WHERE is_active = 1 AND code_type = 'HSN' AND hsn_code = ?
                       AND (concern_id = ? OR concern_id IS NULL)
                     ORDER BY concern_id DESC LIMIT 1",
                    [$batch['hsn_code'], $cid]
                );
                $batch['gst_pct'] = $masterRate === null ? null : (float)$masterRate;
            }
            unset($batch['attributes']);
        }
        unset($batch);

        $this->renderJson(['success' => true, 'batches' => $batches]);
    }

    public function completePosSale(): void {
        ModuleGuard::enforce('billing', 'add');
        $this->validateCsrf();
        \App\Services\StockService::consolidateLegacyLocations();
        $cid = Auth::concernId();
        if ($cid && !DB::fetchValue("SELECT id FROM concerns WHERE id = ? AND status = 'active'", [$cid])) {
            $cid = null;
        }
        if (!$cid && Auth::isSuperAdmin()) {
            $cid = (int)DB::fetchValue("SELECT id FROM concerns WHERE status = 'active' ORDER BY id ASC LIMIT 1") ?: null;
        }
        if (!$cid || !DB::fetchValue("SELECT id FROM concerns WHERE id = ? AND status = 'active'", [$cid])) {
            $this->flash('error', 'No active concern is available for POS billing. Select an active concern before completing a sale.');
            $this->redirect('/m/billing/create');
            return;
        }

        $custMobile  = trim($_POST['customer_mobile'] ?? '');
        $custName    = trim($_POST['customer_name'] ?? 'Walk-in Customer');
        $custAddress = trim($_POST['customer_address'] ?? 'Counter Walk-in / Local Store');
        $billingPartyType = $_POST['billing_party_type'] ?? 'customer';
        $companyName = $_POST['company_name'] ?? '';
        $companyAddress = $_POST['company_address'] ?? '';
        $companyPhone = $_POST['company_phone'] ?? '';
        $companyEmail = $_POST['company_email'] ?? '';
        $companyGstin = $_POST['company_gstin'] ?? '';
        $invoiceDateRaw = $_POST['invoice_date'] ?? date('Y-m-d');
        $directBillRaw = $_POST['is_direct_bill'] ?? '0';
        $gstType     = $_POST['gst_type'] ?? 'both'; // 'both', 'igst', 'exempt'
        $gstPriceMode = $_POST['gst_price_mode'] ?? 'exclude';
        $payMethod   = $_POST['payment_method'] ?? 'cash';
        $paymentStatus = $_POST['payment_status'] ?? 'paid';
        $isOnline    = $payMethod !== 'cash' || !empty($_POST['is_online']);
        $shippingRaw = $_POST['shipping_amount'] ?? '0';

        if (!is_string($payMethod) || !in_array($payMethod, ['cash', 'bank_transfer', 'upi', 'credit', 'card', 'split'], true)) {
            $this->flash('error', 'Choose a valid payment method.');
            $this->redirect('/m/billing/create');
            return;
        }
        if (!is_string($paymentStatus) || !in_array($paymentStatus, ['paid', 'partial', 'unpaid'], true)) {
            $this->flash('error', 'Choose a valid payment status.');
            $this->redirect('/m/billing/create');
            return;
        }
        if (!is_scalar($shippingRaw)) {
            $this->flash('error', 'Enter a valid shipping amount.');
            $this->redirect('/m/billing/create');
            return;
        }
        $shippingRaw = trim((string)$shippingRaw);
        if ($shippingRaw === '') {
            $shippingRaw = '0';
        }
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $shippingRaw) || (float)$shippingRaw > 9999999999.99) {
            $this->flash('error', 'Shipping must be a non-negative amount with up to two decimal places.');
            $this->redirect('/m/billing/create');
            return;
        }
        $shippingAmount = (float)$shippingRaw;
        $advancePaid = 0.00;
        if ($paymentStatus === 'partial') {
            $advanceRaw = $_POST['advance_paid'] ?? '0';
            if (!is_scalar($advanceRaw)) {
                $this->flash('error', 'Enter a valid advance payment.');
                $this->redirect('/m/billing/create');
                return;
            }
            $advanceRaw = trim((string)$advanceRaw);
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $advanceRaw) || (float)$advanceRaw > 9999999999.99) {
                $this->flash('error', 'Advance paid must be a non-negative amount with up to two decimal places.');
                $this->redirect('/m/billing/create');
                return;
            }
            $advancePaid = (float)$advanceRaw;
        }
        if (!in_array($gstType, ['both', 'igst', 'exempt'], true)) {
            $this->flash('error', 'Choose a valid GST type.');
            $this->redirect('/m/billing/create');
            return;
        }
        if (!in_array($gstPriceMode, ['include', 'exclude'], true)) {
            $this->flash('error', 'Choose whether the item prices include or exclude GST.');
            $this->redirect('/m/billing/create');
            return;
        }
        if (!in_array($billingPartyType, ['customer', 'company'], true)) {
            $this->flash('error', 'Choose whether this bill is for a customer or company.');
            $this->redirect('/m/billing/create');
            return;
        }
        if (!is_string($invoiceDateRaw)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $invoiceDateRaw)
            || !checkdate((int)substr($invoiceDateRaw, 5, 2), (int)substr($invoiceDateRaw, 8, 2), (int)substr($invoiceDateRaw, 0, 4))) {
            $this->flash('error', 'Enter a valid bill date.');
            $this->redirect('/m/billing/create');
            return;
        }
        if (!is_scalar($directBillRaw) || !in_array((string)$directBillRaw, ['0', '1'], true)) {
            $this->flash('error', 'Choose a valid bill classification.');
            $this->redirect('/m/billing/create');
            return;
        }
        $isDirectBill = (string)$directBillRaw === '1';
        if ($isDirectBill && $billingPartyType !== 'company') {
            $this->flash('error', 'Only company bills can be separated as direct bills.');
            $this->redirect('/m/billing/create');
            return;
        }
        if ($billingPartyType === 'company') {
            $companyFields = [
                'Company name' => $companyName,
                'Company address' => $companyAddress,
                'Company phone' => $companyPhone,
                'Company email' => $companyEmail,
                'Company GSTIN' => $companyGstin,
            ];
            foreach ($companyFields as $label => $value) {
                if (!is_string($value)) {
                    $this->flash('error', $label . ' must be valid text.');
                    $this->redirect('/m/billing/create');
                    return;
                }
            }
            $companyName = trim($companyName);
            $companyAddress = trim($companyAddress);
            $companyPhone = trim($companyPhone);
            $companyEmail = trim($companyEmail);
            $companyGstin = strtoupper(trim($companyGstin));
            if ($companyName === '' || strlen($companyName) > 150
                || strlen($companyAddress) > 255 || strlen($companyPhone) > 30
                || strlen($companyEmail) > 100 || strlen($companyGstin) > 15) {
                $this->flash('error', 'Check the company name, address, phone, email, and GSTIN lengths.');
                $this->redirect('/m/billing/create');
                return;
            }
            if ($companyEmail !== '' && !filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
                $this->flash('error', 'Enter a valid company email address.');
                $this->redirect('/m/billing/create');
                return;
            }
        }

        $cartItemsJson = $_POST['cart_items_json'] ?? '[]';
        $items = json_decode($cartItemsJson, true);

        if (empty($items) || !is_array($items)) {
            $this->flash('error', 'Cannot complete sale: Billing cart is empty.');
            $this->redirect('/m/billing/create');
        }

        DB::beginTransaction();
        try {
            // 1. Resolve or create the invoice customer.
            $customerId = null;
            if ($billingPartyType === 'company') {
                $company = null;
                if ($companyGstin !== '') {
                    $company = DB::fetch(
                        "SELECT id FROM customers
                         WHERE concern_id = ? AND status = 'active' AND gstin = ?
                         ORDER BY id ASC LIMIT 1",
                        [$cid, $companyGstin]
                    );
                } elseif ($companyPhone !== '' || $companyEmail !== '') {
                    $company = DB::fetch(
                        "SELECT id FROM customers
                         WHERE concern_id = ? AND status = 'active' AND name = ?
                           AND COALESCE(phone, '') = ? AND COALESCE(email, '') = ?
                           AND COALESCE(gstin, '') = ''
                         ORDER BY id ASC LIMIT 1",
                        [$cid, $companyName, $companyPhone, $companyEmail]
                    );
                }
                if (!$company) {
                    $companyCode = 'CUST-' . random_int(10000, 99999);
                    DB::query(
                        "INSERT INTO customers (concern_id, code, name, phone, email, gstin, address, city, status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, '', 'active', NOW())",
                        [$cid, $companyCode, $companyName, $companyPhone, $companyEmail, $companyGstin, $companyAddress]
                    );
                    $customerId = (int)DB::lastInsertId();
                } else {
                    $customerId = (int)$company['id'];
                }
            } else {
                if (!empty($custMobile)) {
                    $existingCust = DB::fetch(
                        "SELECT id FROM customers WHERE concern_id = ? AND phone = ? LIMIT 1",
                        [$cid, $custMobile]
                    );
                    if ($existingCust) {
                        $customerId = (int)$existingCust['id'];
                    }
                }
                if (!$customerId) {
                    $custCode = 'CUST-' . rand(10000, 99999);
                    DB::query(
                        "INSERT INTO customers (concern_id, code, name, phone, address, city, status, created_at)
                         VALUES (?, ?, ?, ?, ?, 'Retail Store', 'active', NOW())",
                        [$cid, $custCode, $custName, $custMobile, $custAddress]
                    );
                    $customerId = (int)DB::lastInsertId();
                }
            }

            // 2. Calculate Totals
            $taxableSubtotal = 0.00;
            $taxTotal = 0.00;
            $discountTotal = 0.00;
            $calculatedItems = [];
            $requestedQtyByVariant = [];
            $requestedQtyByBatch = [];

            foreach ($items as $item) {
                $variantId = (int)($item['id'] ?? 0);
                $qtyInput = $item['qty'] ?? 1;
                $priceInput = $item['price'] ?? null;
                $discountInput = $item['discount'] ?? 0;
                if ($variantId <= 0 || !is_numeric($qtyInput) || !is_numeric($priceInput) || !is_numeric($discountInput)) {
                    throw new \RuntimeException('A cart item has invalid product, quantity, price, or discount data.');
                }
                $rawScannedCodes = $item['scanned_codes'] ?? [];
                if (!is_array($rawScannedCodes) || count($rawScannedCodes) > 50) {
                    throw new \RuntimeException('A cart item has invalid scanned-code details.');
                }
                $scannedCodes = [];
                foreach ($rawScannedCodes as $rawScannedCode) {
                    if (!is_string($rawScannedCode)) {
                        throw new \RuntimeException('A scanned product code must be text.');
                    }
                    if (trim($rawScannedCode) === '' || strlen($rawScannedCode) > 255) {
                        throw new \RuntimeException('A scanned product code must contain between 1 and 255 characters.');
                    }
                    $scannedCodes[] = $rawScannedCode;
                }
                $scannedCodes = array_values(array_unique($scannedCodes));
                $scannedCodesJson = $scannedCodes
                    ? json_encode($scannedCodes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
                    : null;
                if ($scannedCodes && $scannedCodesJson === false) {
                    throw new \RuntimeException('Could not encode the scanned product codes.');
                }

                $qty = (float)$qtyInput;
                $itemDiscount = (float)$discountInput;
                $variant = DB::fetch(
                    "SELECT price, is_complementary
                     FROM variants
                     WHERE id = ? AND concern_id = ? AND status = 'active'
                     LIMIT 1 FOR UPDATE",
                    [$variantId, $cid]
                );
                if (!$variant) {
                    throw new \RuntimeException('A selected product is no longer available in this concern.');
                }
                $isComplementary = (int)$variant['is_complementary'] === 1;
                $unitPrice = (float)$variant['price'];
                $productionBatchId = (int)($item['batch_id'] ?? 0);
                if ($productionBatchId > 0) {
                    $batch = DB::fetch(
                        "SELECT id, batch_qty FROM batches
                         WHERE id = ? AND concern_id = ? AND variant_id = ? AND UPPER(status) = 'APPROVED'
                         LIMIT 1 FOR UPDATE",
                        [$productionBatchId, $cid, $variantId]
                    );
                    if (!$batch) {
                        throw new \RuntimeException('A selected production batch is invalid or no longer available.');
                    }
                    $requestedQtyByBatch[$productionBatchId] = ($requestedQtyByBatch[$productionBatchId] ?? 0) + $qty;
                }
                $gstRateInput = $item['gst_pct'] ?? null;
                if ($gstType !== 'exempt' && (!is_numeric($gstRateInput) || (float)$gstRateInput < 0 || (float)$gstRateInput > 100)) {
                    throw new \RuntimeException('A valid GST rate is required for every taxable item.');
                }
                $gstPct = $gstType === 'exempt' ? 0.00 : (float)$gstRateInput;

                $line = PosBillingCalculator::calculateLine(
                    $unitPrice,
                    $qty,
                    $itemDiscount,
                    $gstPct,
                    $gstPriceMode,
                    $isComplementary
                );

                $taxableSubtotal += $line['taxable_amount'];
                $taxTotal += $line['gst_amount'];
                $discountTotal += $line['discount_amount'];
                $calculatedItems[] = [
                    'variant_id' => $variantId,
                    'production_batch_id' => $productionBatchId ?: null,
                    'qty' => $qty,
                    'unit_price' => $line['base_unit_price'],
                    'total_price' => $line['taxable_amount'],
                    'scanned_codes' => $scannedCodesJson,
                ];
                $requestedQtyByVariant[$variantId] = ($requestedQtyByVariant[$variantId] ?? 0) + $qty;
            }

            ksort($requestedQtyByVariant);
            foreach ($requestedQtyByVariant as $variantId => $requestedQty) {
                $availableRows = DB::fetchAll(
                    "SELECT qty
                     FROM stocks
                     WHERE concern_id = ? AND variant_id = ?
                       AND qty > 0
                       AND location NOT LIKE '%:DAMAGED%'
                       AND location NOT LIKE '%:QUARANTINE%'
                       AND location NOT LIKE '%:RESERVED%'
                       AND location NOT LIKE '%:IN_TRANSIT%'
                     FOR UPDATE",
                    [$cid, (int)$variantId]
                );
                $availableQty = array_sum(array_map(
                    static fn(array $stockRow): float => (float)$stockRow['qty'],
                    $availableRows
                ));
                if ($requestedQty > $availableQty) {
                    $variantName = DB::fetchValue(
                        "SELECT name FROM variants WHERE id = ? AND concern_id = ?",
                        [(int)$variantId, $cid]
                    ) ?: "Variant #{$variantId}";
                    throw new \RuntimeException(
                        "Insufficient Main Store stock for '{$variantName}'. Available: {$availableQty}, requested: {$requestedQty}."
                    );
                }
            }
            foreach ($requestedQtyByBatch as $batchId => $requestedQty) {
                $batchQty = (float)DB::fetchValue("SELECT batch_qty FROM batches WHERE id = ?", [(int)$batchId]);
                $soldQty = (float)(DB::fetchValue(
                    "SELECT COALESCE(SUM(qty), 0) FROM sales_invoice_items WHERE production_batch_id = ?",
                    [(int)$batchId]
                ) ?: 0);
                $batchAvailableQty = max(0, $batchQty - $soldQty);
                if ($requestedQty > $batchAvailableQty) {
                    throw new \RuntimeException(
                        "Selected batch has only {$batchAvailableQty} unsold units; requested {$requestedQty}."
                    );
                }
            }

            $paymentTotals = PosBillingCalculator::calculateCheckoutPayment(
                $taxableSubtotal + $taxTotal,
                $shippingAmount,
                $paymentStatus,
                $advancePaid
            );
            $netPayable = $paymentTotals['net_amount'];
            $paidAmount = $paymentTotals['paid_amount'];
            $balanceDue = $paymentTotals['balance_due'];

            // 3. Generate Sequential Invoice Number
            $invoiceNo = NumberSeries::getNext($cid, 'INVOICE');
            $invDate = $invoiceDateRaw;
            $notes = "POS Terminal Sale. Billing For: {$billingPartyType}. GST Type: {$gstType}. GST Price Mode: {$gstPriceMode}. Payment Status: {$paymentStatus}" . ($isOnline ? " [Online Channel]" : "");

            DB::query(
                "INSERT INTO sales_invoices (concern_id, invoice_no, customer_id, invoice_date, total_amount, tax_amount, discount_amount, net_amount, shipping_amount, paid_amount, payment_method, payment_status, is_direct_bill, billing_company_name, billing_company_address, billing_company_phone, billing_company_email, billing_company_gstin, notes, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $cid, $invoiceNo, $customerId, $invDate, $taxableSubtotal, $taxTotal, $discountTotal, $netPayable,
                    $shippingAmount, $paidAmount, $payMethod, $paymentStatus, $isDirectBill ? 1 : 0,
                    $billingPartyType === 'company' ? $companyName : null,
                    $billingPartyType === 'company' ? $companyAddress : null,
                    $billingPartyType === 'company' ? $companyPhone : null,
                    $billingPartyType === 'company' ? $companyEmail : null,
                    $billingPartyType === 'company' ? $companyGstin : null,
                    $notes, Auth::id()
                ]
            );
            $invoiceId = (int)DB::lastInsertId();

            // 4. Record invoice lines before deducting the grouped variant quantities.
            foreach ($calculatedItems as $item) {
                DB::query(
                    "INSERT INTO sales_invoice_items (concern_id, sales_invoice_id, variant_id, production_batch_id, qty, unit_price, total_price, scanned_codes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$cid, $invoiceId, $item['variant_id'], $item['production_batch_id'], $item['qty'], $item['unit_price'], $item['total_price'], $item['scanned_codes']]
                );
            }

            // Deduct across eligible locations in priority order, without leaving any sold quantity in stocks.
            foreach ($requestedQtyByVariant as $variantId => $requestedQty) {
                $remainingQty = (float)$requestedQty;
                $stockRows = DB::fetchAll(
                    "SELECT location, qty
                     FROM stocks
                     WHERE concern_id = ? AND variant_id = ? AND qty > 0
                       AND location NOT LIKE '%:DAMAGED%'
                       AND location NOT LIKE '%:QUARANTINE%'
                       AND location NOT LIKE '%:RESERVED%'
                       AND location NOT LIKE '%:IN_TRANSIT%'
                     ORDER BY CASE
                         WHEN location = 'Main Store' THEN 0
                         WHEN location = 'Main Store (WH-01)' THEN 1
                         ELSE 2
                     END, qty DESC, location ASC
                     FOR UPDATE",
                    [$cid, (int)$variantId]
                );
                foreach ($stockRows as $stockRow) {
                    if ($remainingQty <= 0.00001) {
                        break;
                    }
                    $deductQty = min($remainingQty, (float)$stockRow['qty']);
                    if ($deductQty <= 0) {
                        continue;
                    }
                    StockService::move(
                        $cid,
                        (int)$variantId,
                        (string)$stockRow['location'],
                        0.00,
                        $deductQty,
                        'Billing',
                        $invoiceId,
                        "POS Sale #{$invoiceNo}",
                        Auth::id()
                    );
                    $remainingQty -= $deductQty;
                }
                if ($remainingQty > 0.00001) {
                    throw new \RuntimeException(
                        "Insufficient sellable stock for variant #{$variantId}. Remaining quantity to deduct: {$remainingQty}."
                    );
                }
            }

            // 5. Post to Cash Accounts / Cash Flow Ledger
            if ($paidAmount > 0 && in_array($payMethod, ['cash', 'upi', 'card', 'split'])) {
                $cashAccount = DB::fetch(
                    "SELECT id FROM cash_accounts WHERE concern_id = ? AND account_type IN ('cash', 'petty_cash') ORDER BY id ASC LIMIT 1",
                    [$cid]
                ) ?: DB::fetch("SELECT id FROM cash_accounts WHERE concern_id = ? ORDER BY id ASC LIMIT 1", [$cid]);

                if ($cashAccount) {
                    CashService::postTransaction(
                        $cid,
                        (int)$cashAccount['id'],
                        $invDate,
                        'credit',
                        $paidAmount,
                        'Billing',
                        $invoiceId,
                        "POS Sale Invoice #{$invoiceNo} [{$payMethod}]",
                        Auth::id()
                    );
                }
            }

            // 6. Audit Trail
            AuditLog::log('POS_SALE', 'sales_invoices', $invoiceId, null, [
                'invoice_no'  => $invoiceNo,
                'net_payable' => $netPayable,
                'shipping_amount' => $shippingAmount,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'payment_status' => $paymentStatus,
                'is_direct_bill' => $isDirectBill,
                'items_count' => count($items),
                'cashier'     => Auth::user()['name'] ?? 'Staff',
            ]);

            DB::commit();

            $this->flash('success', "Sale Completed! Invoice #{$invoiceNo} generated for ₹" . number_format($netPayable, 2) . ".");
            $this->redirect("/m/billing/create?print_id={$invoiceId}");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', "POS Transaction failed: " . $e->getMessage());
            $this->redirect('/m/billing/create');
        }
    }

    public function storeInvoice(): void {
        $this->completePosSale();
    }

    // 5. OUTWARD
    public function outward(): void {
        self::ensureBaseTables();
        $cid = Auth::concernId();

        $sql = "SELECT o.*, c.name AS customer_name, si.invoice_no, con.name AS concern_name, con.code AS concern_code,
                       dest_con.name AS dest_concern_name, dest_con.code AS dest_concern_code,
                       (SELECT COUNT(*) FROM outward_items oi WHERE oi.outward_id = o.id) AS items_count,
                       (SELECT COALESCE(SUM(qty), 0) FROM outward_items oi WHERE oi.outward_id = o.id) AS total_qty
                FROM outwards o
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN concerns dest_con ON o.destination_concern_id = dest_con.id
                LEFT JOIN sales_invoices si ON o.sales_invoice_id = si.id
                LEFT JOIN concerns con ON o.concern_id = con.id";
        $params = [];
        if ($cid) {
            $sql .= " WHERE o.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY o.id DESC";
        $outwards = DB::fetchAll($sql, $params);

        $allConcerns = DB::fetchAll("SELECT id, name, code FROM concerns WHERE status = 'active' ORDER BY name ASC");

        if ($cid) {
            $customers = DB::fetchAll("SELECT id, name FROM customers WHERE concern_id = ? AND status = 'active'", [$cid]);
            $invoices  = DB::fetchAll("SELECT id, invoice_no FROM sales_invoices WHERE concern_id = ?", [$cid]);
            $variants  = DB::fetchAll(
                "SELECT v.id, v.sku_code, v.name, v.size, COALESCE(s.qty, 0) AS stock_qty
                 FROM variants v
                 LEFT JOIN stocks s ON v.id = s.variant_id AND s.concern_id = ?
                 WHERE v.concern_id = ? AND v.status = 'active'",
                [$cid, $cid]
            );
        } else {
            $customers = DB::fetchAll("SELECT id, name FROM customers WHERE status = 'active'");
            $invoices  = DB::fetchAll("SELECT id, invoice_no FROM sales_invoices");
            $variants  = DB::fetchAll(
                "SELECT v.id, v.sku_code, v.name, v.size, COALESCE(s.qty, 0) AS stock_qty
                 FROM variants v
                 LEFT JOIN (SELECT variant_id, SUM(qty) as qty FROM stocks GROUP BY variant_id) s ON v.id = s.variant_id
                 WHERE v.status = 'active'"
            );
        }

        $batchSql = "SELECT b.*, v.name AS variant_name, v.sku_code, v.size 
                     FROM batches b 
                     JOIN variants v ON b.variant_id = v.id 
                     WHERE b.status = 'APPROVED'";
        $batchParams = [];
        if ($cid) {
            $batchSql .= " AND b.concern_id = ?";
            $batchParams[] = $cid;
        }
        $batchSql .= " ORDER BY b.id DESC";
        $approvedBatches = DB::fetchAll($batchSql, $batchParams);

        $this->render('concern/outward', [
            'pageTitle'       => 'Outward Manifest & Dispatch Gate Pass',
            'currentModule'   => 'outward',
            'outwards'        => $outwards,
            'customers'       => $customers,
            'invoices'        => $invoices,
            'variants'        => $variants,
            'approvedBatches' => $approvedBatches,
            'allConcerns'     => $allConcerns,
            'selectedCid'     => $cid,
        ]);
    }

    public function storeOutward(): void {
        $this->validateCsrf();
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);

        if (!$cid) {
            $this->flash('error', 'Please choose a source concern.');
            $this->redirect('/m/outward');
        }

        $transferType   = trim($_POST['transfer_type'] ?? 'customer');
        $destConcernId  = !empty($_POST['destination_concern_id']) ? (int)$_POST['destination_concern_id'] : null;
        $customerId     = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;

        if ($transferType === 'concern') {
            if (!$destConcernId) {
                $this->flash('error', 'Please select a destination concern / unit for inter-concern transfer.');
                $this->redirect('/m/outward');
            }
            if ($destConcernId === $cid) {
                $this->flash('error', 'Destination concern cannot be the same as the source concern.');
                $this->redirect('/m/outward');
            }
        } else {
            $transferType = 'customer';
            if (!$customerId && empty($_POST['selected_batches'])) {
                $this->flash('error', 'Customer, Variant and valid Quantity are required.');
                $this->redirect('/m/outward');
            }
        }

        $invoiceId        = !empty($_POST['sales_invoice_id']) ? (int)$_POST['sales_invoice_id'] : null;
        $outwDate         = $_POST['outward_date'] ?: date('Y-m-d');
        $location         = trim($_POST['location'] ?? 'Main Store');
        $variantId        = (int)($_POST['variant_id'] ?? 0);
        $qty              = (float)($_POST['qty'] ?? 0);
        $vehicleNumber    = trim($_POST['vehicle_number'] ?? '');
        $driverName       = trim($_POST['driver_name'] ?? '');
        $driverContact    = trim($_POST['driver_contact'] ?? '');
        $transporterName  = trim($_POST['transporter_name'] ?? '');
        $lrNumber         = trim($_POST['lr_number'] ?? '');
        $selectedBatches  = $_POST['selected_batches'] ?? [];

        if (empty($selectedBatches) && (!$variantId || $qty <= 0)) {
            $this->flash('error', 'Please either select at least one Approved Batch or specify a variant & quantity.');
            $this->redirect('/m/outward');
        }

        DB::beginTransaction();
        try {
            $outwardNo = NumberSeries::getNext($cid, 'OUTWARD');
            $gatePassNo = \App\Models\Outward::generateGatePassNo($cid);
            $status = 'DRAFT';

            DB::query(
                "INSERT INTO outwards (concern_id, outward_no, gate_pass_no, sales_invoice_id, customer_id, transfer_type, destination_concern_id, outward_date, location, vehicle_number, driver_name, driver_contact, transporter_name, lr_number, status, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$cid, $outwardNo, $gatePassNo, $invoiceId, $customerId, $transferType, $destConcernId, $outwDate, $location, $vehicleNumber, $driverName, $driverContact, $transporterName, $lrNumber, $status, Auth::id()]
            );
            $outwardId = (int)DB::lastInsertId();

            // Link selected production batches
            if (!empty($selectedBatches) && is_array($selectedBatches)) {
                foreach ($selectedBatches as $bId) {
                    $bId = (int)$bId;
                    $bInfo = DB::fetch("SELECT * FROM batches WHERE id = ?", [$bId]);
                    if ($bInfo) {
                        DB::query(
                            "INSERT INTO production_outward_batches (concern_id, outward_id, batch_id, cartons_count, pieces_count) VALUES (?, ?, ?, ?, ?)",
                            [$cid, $outwardId, $bId, (int)$bInfo['total_boxes'], (float)$bInfo['batch_qty']]
                        );
                        DB::query(
                            "INSERT INTO outward_items (concern_id, outward_id, variant_id, qty) VALUES (?, ?, ?, ?)",
                            [$cid, $outwardId, (int)$bInfo['variant_id'], (float)$bInfo['batch_qty']]
                        );
                    }
                }
            } elseif ($variantId && $qty > 0) {
                DB::query(
                    "INSERT INTO outward_items (concern_id, outward_id, variant_id, qty) VALUES (?, ?, ?, ?)",
                    [$cid, $outwardId, $variantId, $qty]
                );
            }

            DB::commit();

            $this->flash('success', "Outward Manifest '{$outwardNo}' (Gate Pass '{$gatePassNo}') created in DRAFT status! Click 'Dispatch' to shift into transit.");
            $this->redirect('/m/outward');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', "Outward creation failed: " . $e->getMessage());
            $this->redirect('/m/outward');
        }
    }

    /**
     * Dispatch Outward Action
     */
    public function dispatchOutward(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        try {
            \App\Models\Outward::dispatch($id, Auth::id());
            $this->flash('success', "Outward #{$id} DISPATCHED! Stock transferred to carrier in IN_TRANSIT status.");
        } catch (\Throwable $e) {
            $this->flash('error', "Dispatch failed: " . $e->getMessage());
        }
        $this->redirect('/m/outward');
    }

    /**
     * Receive / Reconcile Outward Action
     */
    public function receiveOutward(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $damagedQty = (float)($_POST['damaged_qty'] ?? 0);
        try {
            \App\Models\Outward::receive($id, Auth::id(), $damagedQty);
            $this->flash('success', "Outward #{$id} RECEIVED & Reconciled! Stock capitalized into Available warehouse inventory.");
        } catch (\Throwable $e) {
            $this->flash('error', "Receiving failed: " . $e->getMessage());
        }
        $this->redirect('/m/outward');
    }

    public function updateOutward(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $out = DB::fetch("SELECT * FROM outwards WHERE id = ?", [$id]);
        if (!$out) {
            $this->flash('error', 'Outward record not found.');
            $this->redirect('/m/outward');
        }

        $location = trim($_POST['location'] ?? $out['location']);
        $outwDate = $_POST['outward_date'] ?: $out['outward_date'];
        $status   = trim($_POST['status'] ?? $out['status']);

        try {
            DB::query(
                "UPDATE outwards SET location = ?, outward_date = ?, status = ? WHERE id = ?",
                [$location, $outwDate, $status, $id]
            );
            $this->flash('success', "Outward '{$out['outward_no']}' updated successfully.");
        } catch (Exception $e) {
            $this->flash('error', 'Failed to update outward: ' . $e->getMessage());
        }

        $this->redirect('/m/outward');
    }

    public function deleteOutward(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $out = DB::fetch("SELECT * FROM outwards WHERE id = ?", [$id]);
        if (!$out) {
            $this->flash('error', 'Outward record not found.');
            $this->redirect('/m/outward');
        }

        if ($out['status'] === 'received') {
            $this->flash('error', "Cannot delete outward consignment '{$out['outward_no']}' because it has already been received and inwarded by the destination unit.");
            $this->redirect('/m/outward');
        }

        $items = DB::fetchAll("SELECT * FROM outward_items WHERE outward_id = ?", [$id]);

        DB::beginTransaction();
        try {
            // Restore deducted stock
            foreach ($items as $item) {
                StockService::move(
                    $out['concern_id'],
                    $item['variant_id'],
                    $out['location'],
                    (float)$item['qty'],
                    0.00,
                    'Outward',
                    $id,
                    "Reversal of deleted Outward [{$out['outward_no']}]",
                    Auth::id()
                );
            }

            // Delete associated dispatches if any
            DB::query("DELETE FROM dispatches WHERE outward_id = ?", [$id]);
            DB::query("DELETE FROM outward_items WHERE outward_id = ?", [$id]);
            DB::query("DELETE FROM outwards WHERE id = ?", [$id]);

            DB::commit();
            $this->flash('success', "Outward consignment '{$out['outward_no']}' deleted and stock reversed.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to delete outward: ' . $e->getMessage());
        }

        $this->redirect('/m/outward');
    }

    // 6. DISPATCH
    public function dispatch(): void {
        $cid = Auth::concernId();

        $sql = "SELECT d.*, o.outward_no, c.name AS customer_name, con.name AS concern_name, con.code AS concern_code
                FROM dispatches d
                JOIN outwards o ON d.outward_id = o.id
                JOIN customers c ON o.customer_id = c.id
                LEFT JOIN concerns con ON d.concern_id = con.id";
        $params = [];
        if ($cid) {
            $sql .= " WHERE d.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY d.id DESC";
        $dispatches = DB::fetchAll($sql, $params);

        if ($cid) {
            $outwards = DB::fetchAll("SELECT id, outward_no FROM outwards WHERE concern_id = ? AND status = 'prepared'", [$cid]);
        } else {
            $outwards = DB::fetchAll("SELECT id, outward_no FROM outwards WHERE status = 'prepared'");
        }

        $this->render('concern/dispatch', [
            'pageTitle'     => 'Dispatch & Logistics Tracking',
            'currentModule' => 'dispatch',
            'dispatches'    => $dispatches,
            'outwards'      => $outwards,
            'selectedCid'   => $cid,
        ]);
    }

    public function storeDispatch(): void {
        $this->validateCsrf();
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);

        $outwardId    = (int)($_POST['outward_id'] ?? 0);
        $transporter  = trim($_POST['transporter_name'] ?? '');
        $vehicleNo    = trim($_POST['vehicle_no'] ?? '');
        $trackingNo   = trim($_POST['tracking_no'] ?? '');
        $dispatchDate = $_POST['dispatch_date'] ?: date('Y-m-d');
        $notes        = trim($_POST['notes'] ?? '');

        if (!$outwardId) {
            $this->flash('error', 'Please select an outward gate pass.');
            $this->redirect('/m/dispatch');
        }

        // If cid not set, read from outward
        if (!$cid) {
            $cid = (int)DB::fetchValue("SELECT concern_id FROM outwards WHERE id = ?", [$outwardId]);
        }

        DB::beginTransaction();
        try {
            $dispNo = 'DSP-' . date('Ymd') . '-' . rand(100, 999);

            DB::query(
                "INSERT INTO dispatches (concern_id, dispatch_no, outward_id, transporter_name, vehicle_no, tracking_no, dispatch_date, status, notes, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'in_transit', ?, ?, NOW())",
                [$cid, $dispNo, $outwardId, $transporter, $vehicleNo, $trackingNo, $dispatchDate, $notes, Auth::id()]
            );

            // Update outward status to dispatched
            DB::query("UPDATE outwards SET status = 'dispatched' WHERE id = ? AND concern_id = ?", [$outwardId, $cid]);

            DB::commit();

            $this->flash('success', "Dispatch record '{$dispNo}' logged. Shipment is in transit.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to record dispatch: ' . $e->getMessage());
        }

        $this->redirect('/m/dispatch');
    }

    // 7. RETURN & EXCHANGE
    public function lookupShopReturnInvoices(): void {
        $searchTerm = trim((string)($_GET['query'] ?? $_GET['phone'] ?? ''));
        if ($searchTerm === '') {
            $this->renderJson(['success' => false, 'message' => 'Enter a customer phone number or invoice number.'], 400);
            return;
        }

        $cid = Auth::concernId() ?: Auth::superAdminSelectedConcernId();
        $sql = "SELECT si.id AS invoice_id, si.concern_id, si.invoice_no, si.invoice_date, si.created_at, si.net_amount,
                       c.id AS customer_id, c.name AS customer_name, c.phone AS customer_phone,
                       con.name AS concern_name,
                       CASE WHEN EXISTS (
                           SELECT 1
                           FROM returns_exchanges re
                           LEFT JOIN production_returns linked_pr
                             ON linked_pr.return_no = re.return_no AND linked_pr.concern_id = re.concern_id
                           WHERE re.sales_invoice_id = si.id
                             AND (linked_pr.id IS NULL OR linked_pr.status <> 'CANCELLED')
                       ) OR EXISTS (
                           SELECT 1 FROM production_returns pr
                           WHERE pr.sales_invoice_id = si.id AND pr.status <> 'CANCELLED'
                       ) THEN 1 ELSE 0 END AS already_used
                FROM customers c
                JOIN sales_invoices si ON si.customer_id = c.id
                LEFT JOIN concerns con ON con.id = si.concern_id
                WHERE (c.phone = ? OR si.invoice_no = ?)";
        $params = [$searchTerm, $searchTerm];
        if ($cid) {
            $sql .= ' AND si.concern_id = ?';
            $params[] = $cid;
        }
        $sql .= ' ORDER BY si.invoice_date DESC, si.id DESC';

        $this->renderJson(['success' => true, 'invoices' => DB::fetchAll($sql, $params)]);
    }

    public function lookupShopReturnInvoice(): void {
        $invoiceId = (int)($_GET['invoice_id'] ?? 0);
        $cid = Auth::concernId() ?: Auth::superAdminSelectedConcernId();
        if ($invoiceId <= 0) {
            $this->renderJson(['success' => false, 'message' => 'Select a valid invoice.'], 400);
            return;
        }

        $sql = "SELECT si.id, si.concern_id, si.customer_id, si.invoice_no, si.invoice_date,
                       si.total_amount, si.tax_amount, si.net_amount, c.name AS customer_name, c.phone AS customer_phone
                FROM sales_invoices si
                JOIN customers c ON c.id = si.customer_id
                WHERE si.id = ?";
        $params = [$invoiceId];
        if ($cid) {
            $sql .= ' AND si.concern_id = ?';
            $params[] = $cid;
        }
        $invoice = DB::fetch($sql, $params);
        if (!$invoice) {
            $this->renderJson(['success' => false, 'message' => 'Invoice not found for this concern.'], 404);
            return;
        }

        $used = DB::fetchValue(
            "SELECT re.id
             FROM returns_exchanges re
             LEFT JOIN production_returns linked_pr
               ON linked_pr.return_no = re.return_no AND linked_pr.concern_id = re.concern_id
             WHERE re.sales_invoice_id = ?
               AND (linked_pr.id IS NULL OR linked_pr.status <> 'CANCELLED')
             UNION ALL
             SELECT id FROM production_returns
             WHERE sales_invoice_id = ? AND status <> 'CANCELLED'
             LIMIT 1",
            [$invoiceId, $invoiceId]
        );
        if ($used) {
            $this->renderJson(['success' => false, 'already_used' => true, 'message' => 'This invoice has already been used for a return or exchange.'], 409);
            return;
        }

        $items = DB::fetchAll(
            "SELECT sii.id AS invoice_item_id, sii.variant_id, sii.qty AS sold_qty, sii.unit_price, sii.scanned_codes,
                    sii.total_price, v.sku_code, v.name AS product_name, v.size,
                    COALESCE(NULLIF(cat.main_category_code, ''), c.code) AS qr_category_code,
                    COALESCE(NULLIF(cat.sub_category_code, ''), sc.code) AS qr_subcategory_code,
                    COALESCE(NULLIF(cat.sub_sub_category_code, ''), ssc.code) AS qr_sub_subcategory_code,
                    c.name AS qr_category_name, sc.name AS qr_subcategory_name, ssc.name AS qr_sub_subcategory_name,
                    ROUND(
                        (sii.total_price + CASE WHEN si.total_amount > 0
                            THEN si.tax_amount * sii.total_price / si.total_amount
                            ELSE 0 END) / NULLIF(sii.qty, 0),
                        2
                    ) AS paid_unit_price
             FROM sales_invoice_items sii
             JOIN sales_invoices si ON si.id = sii.sales_invoice_id
             JOIN variants v ON v.id = sii.variant_id
             LEFT JOIN catalogs cat ON cat.id = v.catalog_id
             LEFT JOIN categories c ON c.id = v.category_id
             LEFT JOIN subcategories sc ON sc.id = v.subcategory_id
             LEFT JOIN sub_subcategories ssc ON ssc.id = cat.sub_sub_category_id
             WHERE sii.sales_invoice_id = ?
             ORDER BY sii.id ASC",
            [$invoiceId]
        );
        foreach ($items as &$item) {
            $item['tag_code'] = QrService::formatFinishedGoodsCode(
                (int)$item['variant_id'],
                $item['qr_category_code'] ?? null,
                $item['qr_subcategory_code'] ?? null,
                $item['qr_sub_subcategory_code'] ?? null,
                $item['qr_category_name'] ?? null,
                $item['qr_subcategory_name'] ?? null,
                $item['qr_sub_subcategory_name'] ?? null
            );
            $invoiceScannedCodes = json_decode((string)($item['scanned_codes'] ?? ''), true);
            $item['invoice_scanned_codes'] = [];
            if (is_array($invoiceScannedCodes)) {
                foreach ($invoiceScannedCodes as $scannedCode) {
                    if (is_string($scannedCode) && trim($scannedCode) !== '') {
                        $item['invoice_scanned_codes'][] = $scannedCode;
                    }
                }
            }
        }
        unset($item);

        $this->renderJson(['success' => true, 'invoice' => $invoice, 'items' => $items]);
    }

    public function lookupShopReturnReplacement(): void {
        $invoiceId = (int)($_GET['invoice_id'] ?? 0);
        $sku = trim((string)($_GET['sku'] ?? ''));
        $cid = Auth::concernId() ?: Auth::superAdminSelectedConcernId();
        if ($invoiceId <= 0 || $sku === '') {
            $this->renderJson(['success' => false, 'message' => 'Scan or enter a replacement SKU.'], 400);
            return;
        }

        $invoiceSql = 'SELECT concern_id FROM sales_invoices WHERE id = ?';
        $invoiceParams = [$invoiceId];
        if ($cid) {
            $invoiceSql .= ' AND concern_id = ?';
            $invoiceParams[] = $cid;
        }
        $invoiceConcernId = (int)DB::fetchValue($invoiceSql, $invoiceParams);
        if (!$invoiceConcernId) {
            $this->renderJson(['success' => false, 'message' => 'Invoice not found for this concern.'], 404);
            return;
        }

        $variantId = (int)DB::fetchValue(
            "SELECT id FROM variants
             WHERE concern_id = ? AND status = 'active' AND sku_code = ?
             LIMIT 1",
            [$invoiceConcernId, $sku]
        );
        if (!$variantId) {
            $scannedVariant = QrService::lookupVariant($sku, $invoiceConcernId);
            $variantId = (int)($scannedVariant['id'] ?? 0);
        }
        if (!$variantId) {
            $this->renderJson(['success' => false, 'message' => 'Replacement SKU or product tag was not found for this shop.'], 404);
            return;
        }

        $variant = DB::fetch(
            "SELECT v.id, v.sku_code, v.name, v.size, v.price,
                    COALESCE(s.qty, 0) AS available_qty
             FROM variants v
             LEFT JOIN (
                 SELECT concern_id, variant_id, SUM(GREATEST(qty, 0)) AS qty
                 FROM stocks
                 WHERE location IN ('Main Store', 'Main Store (WH-01)')
                 GROUP BY concern_id, variant_id
             ) s ON s.concern_id = v.concern_id AND s.variant_id = v.id
             WHERE v.concern_id = ? AND v.status = 'active' AND v.id = ?
               AND COALESCE(s.qty, 0) > 0
             LIMIT 1",
            [$invoiceConcernId, $variantId]
        );
        if (!$variant) {
            $this->renderJson(['success' => false, 'message' => 'Replacement product has no good available shop stock.'], 404);
            return;
        }

        $this->renderJson(['success' => true, 'variant' => $variant]);
    }

    public function returnExchange(): void {
        $cid = Auth::concernId();

        $sql = "SELECT re.*, c.name AS customer_name, v1.name AS returned_variant_name, v1.sku_code AS returned_sku,
                       v2.name AS exchange_variant_name, v2.sku_code AS exchange_sku,
                       con.name AS concern_name, con.code AS concern_code, si.invoice_no,
                       pr.id AS production_return_id, pr.status AS production_status
                FROM returns_exchanges re
                JOIN customers c ON re.customer_id = c.id
                JOIN variants v1 ON re.returned_variant_id = v1.id
                LEFT JOIN variants v2 ON re.exchange_variant_id = v2.id
                LEFT JOIN sales_invoices si ON si.id = re.sales_invoice_id
                LEFT JOIN production_returns pr ON pr.return_no = re.return_no AND pr.concern_id = re.concern_id
                LEFT JOIN concerns con ON re.concern_id = con.id";
        $params = [];
        if ($cid) {
            $sql .= " WHERE re.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY re.id DESC";
        $returns = DB::fetchAll($sql, $params);
        $productionReturnIds = array_values(array_unique(array_filter(array_map(
            static fn($return) => (int)($return['production_return_id'] ?? 0),
            $returns
        ))));
        $scannedCodesByReturnId = [];
        if ($productionReturnIds) {
            $placeholders = implode(',', array_fill(0, count($productionReturnIds), '?'));
            $returnItemScans = DB::fetchAll(
                "SELECT return_id, tag_code, scanned_codes
                 FROM production_return_exchange_items
                 WHERE return_id IN ({$placeholders})
                 ORDER BY return_id ASC, id ASC",
                $productionReturnIds
            );
            foreach ($returnItemScans as $returnItemScan) {
                $returnId = (int)$returnItemScan['return_id'];
                $codes = json_decode((string)($returnItemScan['scanned_codes'] ?? ''), true);
                if (is_array($codes)) {
                    foreach ($codes as $code) {
                        if (is_string($code) && trim($code) !== '') {
                            $scannedCodesByReturnId[$returnId][] = $code;
                        }
                    }
                } elseif (!empty($returnItemScan['tag_code'])) {
                    $scannedCodesByReturnId[$returnId][] = (string)$returnItemScan['tag_code'];
                }
            }
        }
        foreach ($returns as &$return) {
            $return['scanned_code_display'] = implode(
                ', ',
                $scannedCodesByReturnId[(int)($return['production_return_id'] ?? 0)] ?? []
            );
        }
        unset($return);

        $this->render('concern/return_exchange', [
            'pageTitle'     => 'Shop Returns & Exchanges',
            'currentModule' => 'return_exchange',
            'returns'       => $returns,
            'selectedCid'   => $cid,
        ]);
    }

    public function storeReturn(): void {
        $this->validateCsrf();
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);

        if (!$cid) {
            $this->flash('error', 'Please choose a concern.');
            $this->redirect('/m/return_exchange');
        }

        $type        = $_POST['type'] ?? 'return';
        $customerId  = (int)($_POST['customer_id'] ?? 0);
        $returnDate  = $_POST['return_date'] ?: date('Y-m-d');
        $retVarId    = (int)($_POST['returned_variant_id'] ?? 0);
        $retQty      = (float)($_POST['returned_qty'] ?? 0);
        $excVarId    = !empty($_POST['exchange_variant_id']) ? (int)$_POST['exchange_variant_id'] : null;
        $excQty      = !empty($_POST['exchange_qty']) ? (float)$_POST['exchange_qty'] : null;
        $reason      = trim($_POST['reason'] ?? '');

        if (!$customerId || !$retVarId || $retQty <= 0) {
            $this->flash('error', 'Customer, Returned Variant, and Quantity are required.');
            $this->redirect('/m/return_exchange');
        }

        DB::beginTransaction();
        try {
            $returnNo = 'RET-' . date('Ymd') . '-' . rand(100, 999);

            DB::query(
                "INSERT INTO returns_exchanges (concern_id, return_no, type, customer_id, return_date, returned_variant_id, returned_qty, exchange_variant_id, exchange_qty, reason, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$cid, $returnNo, $type, $customerId, $returnDate, $retVarId, $retQty, $excVarId, $excQty, $reason, Auth::id()]
            );
            $retId = (int)DB::lastInsertId();

            // Return re-enters stocks
            StockService::move(
                $cid,
                $retVarId,
                'Main Store',
                $retQty,
                0.00,
                'Return & Exchange',
                $retId,
                "Customer return: {$reason}",
                Auth::id()
            );

            // If exchange, deduct exchange variant stock
            if ($type === 'exchange' && $excVarId && $excQty > 0) {
                StockService::move(
                    $cid,
                    $excVarId,
                    'Main Store',
                    0.00,
                    $excQty,
                    'Return & Exchange',
                    $retId,
                    "Exchange delivery for return {$returnNo}",
                    Auth::id()
                );
            }

            DB::commit();

            $this->flash('success', "Return/Exchange record '{$returnNo}' processed and inventory balances synchronized.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to process return: ' . $e->getMessage());
        }

        $this->redirect('/m/return_exchange');
    }

    public function updateDispatch(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $cid = Auth::concernId();

        $sql = "SELECT * FROM dispatches WHERE id = ?";
        $params = [$id];
        if ($cid) {
            $sql .= " AND concern_id = ?";
            $params[] = $cid;
        }
        $dispatch = DB::fetch($sql, $params);
        if (!$dispatch) {
            $this->flash('error', 'Dispatch record not found.');
            $this->redirect('/m/dispatch');
        }

        $transporter  = trim($_POST['transporter_name'] ?? '');
        $vehicleNo    = trim($_POST['vehicle_no'] ?? '');
        $trackingNo   = trim($_POST['tracking_no'] ?? '');
        $dispatchDate = $_POST['dispatch_date'] ?: $dispatch['dispatch_date'];
        $status       = trim($_POST['status'] ?? $dispatch['status']);
        $notes        = trim($_POST['notes'] ?? '');

        try {
            DB::query(
                "UPDATE dispatches SET transporter_name = ?, vehicle_no = ?, tracking_no = ?, dispatch_date = ?, status = ?, notes = ? WHERE id = ?",
                [$transporter, $vehicleNo, $trackingNo, $dispatchDate, $status, $notes, $id]
            );

            // If status is 'delivered', we can also update outward status
            if ($status === 'delivered') {
                DB::query("UPDATE outwards SET status = 'delivered' WHERE id = ?", [$dispatch['outward_id']]);
            }

            $this->flash('success', "Dispatch record '{$dispatch['dispatch_no']}' updated successfully.");
        } catch (Exception $e) {
            $this->flash('error', 'Failed to update dispatch: ' . $e->getMessage());
        }

        $this->redirect('/m/dispatch');
    }

    public function deleteDispatch(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $cid = Auth::concernId();

        $sql = "SELECT * FROM dispatches WHERE id = ?";
        $params = [$id];
        if ($cid) {
            $sql .= " AND concern_id = ?";
            $params[] = $cid;
        }
        $dispatch = DB::fetch($sql, $params);
        if (!$dispatch) {
            $this->flash('error', 'Dispatch record not found.');
            $this->redirect('/m/dispatch');
        }

        DB::beginTransaction();
        try {
            DB::query("DELETE FROM dispatches WHERE id = ?", [$id]);

            // Revert outward status to 'prepared'
            DB::query("UPDATE outwards SET status = 'prepared' WHERE id = ?", [$dispatch['outward_id']]);

            DB::commit();
            $this->flash('success', "Dispatch record '{$dispatch['dispatch_no']}' deleted and Outward Gate Pass reverted to Prepared.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to delete dispatch: ' . $e->getMessage());
        }

        $this->redirect('/m/dispatch');
    }

    public function updateReturn(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $cid = Auth::concernId() ?: Auth::superAdminSelectedConcernId();
        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }
        try {
            $sql = "SELECT * FROM returns_exchanges WHERE id = ?";
            $params = [$id];
            if ($cid) {
                $sql .= " AND concern_id = ?";
                $params[] = $cid;
            }
            $sql .= " LIMIT 1 FOR UPDATE";
            $return = DB::fetch($sql, $params);
            if (!$return) {
                throw new \RuntimeException('Return/Exchange record not found.');
            }

            $productionReturn = DB::fetch(
                "SELECT id, status FROM production_returns WHERE return_no = ? AND concern_id = ? LIMIT 1 FOR UPDATE",
                [$return['return_no'], $return['concern_id']]
            );
            if ($productionReturn && $productionReturn['status'] === 'CANCELLED') {
                throw new \RuntimeException('A cancelled return cannot be edited.');
            }

            $returnDate = trim((string)($_POST['return_date'] ?? $return['return_date']));
            $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $returnDate);
            if (!$parsedDate || $parsedDate->format('Y-m-d') !== $returnDate) {
                throw new \RuntimeException('Enter a valid return date.');
            }
            $reason = trim((string)($_POST['reason'] ?? ''));

            DB::query(
                "UPDATE returns_exchanges SET return_date = ?, reason = ? WHERE id = ?",
                [$returnDate, $reason, $id]
            );
            \App\Services\AuditService::log('RETURN_UPDATE', 'returns_exchanges', $id, [
                'return_date' => $return['return_date'],
                'reason' => $return['reason'],
            ], [
                'return_date' => $returnDate,
                'reason' => $reason,
            ]);
            if ($productionReturn) {
                DB::query(
                    "UPDATE production_returns SET return_date = ?, reason = ? WHERE id = ?",
                    [$returnDate, $reason, $productionReturn['id']]
                );
                \App\Services\AuditService::log('RETURN_UPDATE', 'production_returns', (int)$productionReturn['id'], [
                    'return_date' => $return['return_date'],
                    'reason' => $return['reason'],
                ], [
                    'return_date' => $returnDate,
                    'reason' => $reason,
                ]);
            }
            if (!$hasActiveTx) {
                DB::commit();
            }
            $this->flash('success', "Return/Exchange '{$return['return_no']}' updated successfully.");
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            $this->flash('error', 'Failed to update return: ' . $e->getMessage());
        }

        $this->redirect('/m/return_exchange');
    }

    public function deleteReturn(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $cid = Auth::concernId();

        $sql = "SELECT * FROM returns_exchanges WHERE id = ?";
        $params = [$id];
        if ($cid) {
            $sql .= " AND concern_id = ?";
            $params[] = $cid;
        }
        $return = DB::fetch($sql, $params);
        if (!$return) {
            $this->flash('error', 'Return/Exchange record not found.');
            $this->redirect('/m/return_exchange');
            return;
        }
        if (DB::fetchValue(
            "SELECT id FROM production_returns WHERE return_no = ? AND concern_id = ? LIMIT 1",
            [$return['return_no'], $return['concern_id']]
        )) {
            $this->flash('error', 'Use the RMA cancellation action to reverse this invoice-linked return safely.');
            $this->redirect('/m/return_exchange');
            return;
        }

        DB::beginTransaction();
        try {
            $cId = (int)$return['concern_id'];
            $retVarId = (int)$return['returned_variant_id'];
            $retQty = (float)$return['returned_qty'];

            // Reverse returned stock: originally was qtyIn, now qtyOut
            if ($retQty > 0) {
                StockService::move(
                    $cId,
                    $retVarId,
                    'Main Store',
                    0.00,
                    $retQty,
                    'Return & Exchange',
                    $id,
                    "Reversal of deleted return #{$return['return_no']}",
                    Auth::id()
                );
            }

            // Reverse exchange stock: originally was qtyOut, now qtyIn
            if ($return['type'] === 'exchange' && !empty($return['exchange_variant_id']) && (float)$return['exchange_qty'] > 0) {
                StockService::move(
                    $cId,
                    (int)$return['exchange_variant_id'],
                    'Main Store',
                    (float)$return['exchange_qty'],
                    0.00,
                    'Return & Exchange',
                    $id,
                    "Reversal of deleted exchange #{$return['return_no']}",
                    Auth::id()
                );
            }

            DB::query("DELETE FROM returns_exchanges WHERE id = ?", [$id]);

            DB::commit();
            $this->flash('success', "Return/Exchange '{$return['return_no']}' deleted and inventory movements reversed.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to delete return record: ' . $e->getMessage());
        }

        $this->redirect('/m/return_exchange');
    }

    public function exportRequests(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT cr.*, c.name AS customer_name, v.name AS variant_name, v.sku_code, v.price,
                       con.name AS concern_name, con.code AS concern_code
                FROM customer_requests cr
                JOIN customers c ON cr.customer_id = c.id
                JOIN variants v ON cr.variant_id = v.id
                LEFT JOIN concerns con ON cr.concern_id = con.id";
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "cr.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(cr.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY cr.id DESC";
        $requests = DB::fetchAll($sql, $params);

        $headers = [
            'Request No', 'Date', 'Customer Name', 'SKU Code', 'Variant / Style', 
            'Requested Qty', 'Est. Price (₹)', 'Est. Total (₹)', 'Priority', 'Status', 'Notes', 'Concern'
        ];

        $rows = [];
        $totQty = 0; $totAmt = 0;
        foreach ($requests as $r) {
            $qty = (float)($r['qty'] ?? $r['quantity'] ?? 0);
            $prc = (float)($r['price'] ?? 0);
            $amt = $qty * $prc;
            $totQty += $qty; $totAmt += $amt;

            $rows[] = [
                $r['request_no'],
                date('d M Y, h:i A', strtotime($r['created_at'])),
                $r['customer_name'],
                $r['sku_code'],
                $r['variant_name'],
                $qty,
                '₹' . number_format($prc, 2),
                '₹' . number_format($amt, 2),
                strtoupper($r['priority'] ?? 'MEDIUM'),
                strtoupper($r['status'] ?? 'PENDING'),
                $r['notes'] ?: '-',
                $r['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total Requests: ' . count($requests), '', '', '', '', $totQty, '', '₹' . number_format($totAmt, 2), '', '', '', ''];
        $metadata = [
            'Report Title' => 'Customer Product Booking & Inquiries Register',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Requests' => count($requests),
            'Total Requested Units' => number_format($totQty, 2),
            'Estimated Pipeline' => '₹' . number_format($totAmt, 2)
        ];

        $filename = 'customer_requests_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Customer Requests & Booking Inquiries Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Customer Requests & Booking Inquiries Report', $metadata, $headers, $rows, $totals);
        }
    }

    public function exportBatches(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT b.*, v.name AS variant_name, v.sku_code, v.size, v.weight_unit,
                       con.name AS concern_name, con.code AS concern_code
                FROM batches b
                JOIN variants v ON b.variant_id = v.id
                LEFT JOIN concerns con ON b.concern_id = con.id";
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "b.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(b.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY b.id DESC";
        $batches = DB::fetchAll($sql, $params);

        $headers = [
            'Batch No', 'Creation Date', 'SKU Code', 'Variant Name', 'Size', 
            'Batch Qty', 'Mfg Date', 'Expiry Date', 'Status', 'Concern'
        ];

        $rows = [];
        $totQty = 0;
        foreach ($batches as $b) {
            $qty = (float)$b['batch_qty'];
            $totQty += $qty;

            $rows[] = [
                $b['batch_no'],
                date('d M Y', strtotime($b['created_at'])),
                $b['sku_code'],
                $b['variant_name'],
                $b['size'] ?? '-',
                $qty,
                $b['mfg_date'] ? date('d M Y', strtotime($b['mfg_date'])) : '-',
                $b['exp_date'] ? date('d M Y', strtotime($b['exp_date'])) : 'N/A',
                strtoupper($b['status'] ?? 'ACTIVE'),
                $b['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total Batches: ' . count($batches), '', '', '', '', $totQty, '', '', '', ''];
        $metadata = [
            'Report Title' => 'Production Batch Lots & Stock Traceability',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Lots' => count($batches),
            'Total Lot Units' => number_format($totQty, 2)
        ];

        $filename = 'batch_lots_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Batch Lots & Allocation Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Batch Lots & Allocation Report', $metadata, $headers, $rows, $totals);
        }
    }

    public function exportBilling(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT si.*, c.name AS customer_name, c.phone AS customer_phone, con.name AS concern_name, con.code AS concern_code,
                       (SELECT COUNT(*) FROM sales_invoice_items sii WHERE sii.sales_invoice_id = si.id) AS items_count
                FROM sales_invoices si
                JOIN customers c ON si.customer_id = c.id
                LEFT JOIN concerns con ON si.concern_id = con.id";
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "si.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(si.invoice_date) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY si.id DESC";
        $invoices = DB::fetchAll($sql, $params);

        $headers = [
            'Invoice No', 'Date & Time', 'Customer Name', 'Phone', 'Pay Mode', 
            'Items', 'Subtotal (₹)', 'Discount (₹)', 'Tax (₹)', 'Grand Total (₹)', 'Paid (₹)', 'Status', 'Concern'
        ];

        $rows = [];
        $totSub = 0; $totDisc = 0; $totTax = 0; $totGrand = 0; $totPaid = 0;
        foreach ($invoices as $i) {
            $sub = (float)($i['subtotal'] ?? 0);
            $dsc = (float)($i['discount_amount'] ?? 0);
            $tax = (float)($i['tax_amount'] ?? 0);
            $grd = (float)($i['grand_total'] ?? 0);
            $pd  = (float)($i['paid_amount'] ?? 0);

            $totSub += $sub; $totDisc += $dsc; $totTax += $tax; $totGrand += $grd; $totPaid += $pd;

            $rows[] = [
                $i['invoice_no'],
                date('d M Y, h:i A', strtotime($i['created_at'] ?? $i['invoice_date'])),
                $i['customer_name'],
                $i['customer_phone'] ?: '-',
                strtoupper($i['payment_mode'] ?? 'CASH'),
                (int)($i['items_count'] ?? 1),
                '₹' . number_format($sub, 2),
                '₹' . number_format($dsc, 2),
                '₹' . number_format($tax, 2),
                '₹' . number_format($grd, 2),
                '₹' . number_format($pd, 2),
                strtoupper($i['status'] ?? 'PAID'),
                $i['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = [
            'Total: ' . count($invoices) . ' Invoices', '', '', '', '', '',
            '₹' . number_format($totSub, 2),
            '₹' . number_format($totDisc, 2),
            '₹' . number_format($totTax, 2),
            '₹' . number_format($totGrand, 2),
            '₹' . number_format($totPaid, 2),
            '', ''
        ];

        $metadata = [
            'Report Title' => 'Sales POS Invoices & Billing Register',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Invoices' => count($invoices),
            'Total Revenue' => '₹' . number_format($totGrand, 2),
            'Total Collected' => '₹' . number_format($totPaid, 2)
        ];

        $filename = 'sales_invoices_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('POS Sales Invoices & Billing Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'POS Sales Invoices & Billing Report', $metadata, $headers, $rows, $totals);
        }
    }

    public function exportOutward(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT o.*, c.name AS customer_name, si.invoice_no, con.name AS concern_name, con.code AS concern_code,
                       dest_con.name AS dest_concern_name, dest_con.code AS dest_concern_code,
                       (SELECT COUNT(*) FROM outward_items oi WHERE oi.outward_id = o.id) AS items_count,
                       (SELECT COALESCE(SUM(qty), 0) FROM outward_items oi WHERE oi.outward_id = o.id) AS total_qty
                FROM outwards o
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN concerns dest_con ON o.destination_concern_id = dest_con.id
                LEFT JOIN sales_invoices si ON o.sales_invoice_id = si.id
                LEFT JOIN concerns con ON o.concern_id = con.id";
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "o.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(o.outward_date) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY o.id DESC";
        $outwards = DB::fetchAll($sql, $params);

        $headers = [
            'Outward No', 'Date', 'Customer Name', 'Invoice Ref', 'Total Items', 
            'Total Qty', 'Transport Mode', 'Vehicle No', 'E-Way Bill', 'Status', 'Concern'
        ];

        $rows = [];
        $totItems = 0; $totQty = 0;
        foreach ($outwards as $o) {
            $itms = (int)($o['items_count'] ?? 0);
            $qty  = (float)($o['total_qty'] ?? 0);
            $totItems += $itms; $totQty += $qty;

            $rows[] = [
                $o['outward_no'],
                date('d M Y', strtotime($o['outward_date'])),
                $o['customer_name'] ?: ($o['dest_concern_name'] ? "Unit Transfer: {$o['dest_concern_name']} ({$o['dest_concern_code']})" : 'Inter-Unit Transfer'),
                $o['invoice_no'] ?: '-',
                $itms,
                $qty,
                $o['transport_mode'] ?: 'Road Transport',
                $o['vehicle_no'] ?: '-',
                $o['eway_bill_no'] ?: '-',
                strtoupper($o['status'] ?? 'PREPARED'),
                $o['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total Outwards: ' . count($outwards), '', '', '', $totItems, $totQty, '', '', '', '', ''];
        $metadata = [
            'Report Title' => 'Outward Consignments & Material Deliveries',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Outwards' => count($outwards),
            'Total Units Dispatched' => number_format($totQty, 2)
        ];

        $filename = 'outwards_report_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Outward Deliveries Register Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Outward Deliveries Register Report', $metadata, $headers, $rows, $totals);
        }
    }

    public function exportDispatch(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT d.*, o.outward_no, c.name AS customer_name, con.name AS concern_name, con.code AS concern_code
                FROM dispatches d
                JOIN outwards o ON d.outward_id = o.id
                JOIN customers c ON o.customer_id = c.id
                LEFT JOIN concerns con ON d.concern_id = con.id";
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "d.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(d.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY d.id DESC";
        $dispatches = DB::fetchAll($sql, $params);

        $headers = [
            'Dispatch No', 'Date & Time', 'Outward Ref', 'Customer / Consignee', 
            'Carrier / Courier', 'Tracking No', 'Delivery Status', 'Notes', 'Concern'
        ];

        $rows = [];
        foreach ($dispatches as $d) {
            $rows[] = [
                $d['dispatch_no'],
                date('d M Y, h:i A', strtotime($d['created_at'])),
                $d['outward_no'],
                $d['customer_name'],
                $d['transporter_name'] ?: 'Local Delivery',
                $d['tracking_number'] ?: '-',
                strtoupper($d['delivery_status'] ?? 'DISPATCHED'),
                $d['remarks'] ?: '-',
                $d['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total Dispatches: ' . count($dispatches), '', '', '', '', '', '', '', ''];
        $metadata = [
            'Report Title' => 'Dispatch Tracking & Courier Logistics Register',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Dispatches' => count($dispatches)
        ];

        $filename = 'dispatches_report_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Dispatch & Logistics Audit Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Dispatch & Logistics Audit Report', $metadata, $headers, $rows, $totals);
        }
    }

    public function exportReturnExchange(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT re.*, c.name AS customer_name, v1.name AS returned_variant_name, v1.sku_code AS returned_sku,
                       v2.name AS exchange_variant_name, v2.sku_code AS exchange_sku,
                       con.name AS concern_name, con.code AS concern_code
                FROM returns_exchanges re
                JOIN customers c ON re.customer_id = c.id
                JOIN variants v1 ON re.returned_variant_id = v1.id
                LEFT JOIN variants v2 ON re.exchange_variant_id = v2.id
                LEFT JOIN concerns con ON re.concern_id = con.id";
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "re.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(re.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY re.id DESC";
        $returns = DB::fetchAll($sql, $params);

        $headers = [
            'Return No', 'Date', 'Customer Name', 'Type', 'Returned SKU', 'Returned Item', 
            'Return Qty', 'Exchange SKU', 'Exchange Item', 'Exchange Qty', 'Refund (₹)', 'Reason', 'Concern'
        ];

        $rows = [];
        $totRetQty = 0; $totRefund = 0;
        foreach ($returns as $r) {
            $rqty = (float)$r['returned_qty'];
            $ref = (float)($r['refund_amount'] ?? 0);
            $totRetQty += $rqty; $totRefund += $ref;

            $rows[] = [
                $r['return_no'],
                date('d M Y', strtotime($r['created_at'])),
                $r['customer_name'],
                strtoupper($r['type']),
                $r['returned_sku'],
                $r['returned_variant_name'],
                $rqty,
                $r['exchange_sku'] ?: '-',
                $r['exchange_variant_name'] ?: '-',
                (float)($r['exchange_qty'] ?? 0),
                '₹' . number_format($ref, 2),
                $r['reason'] ?: '-',
                $r['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total Records: ' . count($returns), '', '', '', '', '', $totRetQty, '', '', '', '₹' . number_format($totRefund, 2), '', ''];
        $metadata = [
            'Report Title' => 'Customer Returns & Replacement Exchange Register',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Transactions' => count($returns),
            'Total Returned Units' => number_format($totRetQty, 2),
            'Total Refunds' => '₹' . number_format($totRefund, 2)
        ];

        $filename = 'returns_exchanges_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Returns & Exchanges Register Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Returns & Exchanges Register Report', $metadata, $headers, $rows, $totals);
        }
    }
}
