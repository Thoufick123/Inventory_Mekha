<?php
// app/controllers/ConcernDashboardController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;

class ConcernDashboardController extends Controller {
    public function index(): void {
        $cid = Auth::concernId();

        if ($cid) {
            $variantsCount = (int)DB::fetchValue("SELECT COUNT(*) FROM variants WHERE concern_id = ?", [$cid]);
            $totalStockQty = (float)DB::fetchValue("SELECT COALESCE(SUM(qty), 0) FROM stocks WHERE concern_id = ?", [$cid]);
            $stockValuation = (float)DB::fetchValue(
                "SELECT COALESCE(SUM(s.qty * v.price), 0) FROM stocks s JOIN variants v ON s.variant_id = v.id WHERE s.concern_id = ?",
                [$cid]
            );
            $totalCustomers = (int)DB::fetchValue("SELECT COUNT(*) FROM customers WHERE concern_id = ?", [$cid]);
            $totalSuppliers = (int)DB::fetchValue("SELECT COUNT(*) FROM suppliers WHERE concern_id = ?", [$cid]);
            $cashBalance = (float)DB::fetchValue("SELECT COALESCE(SUM(balance), 0) FROM cash_accounts WHERE concern_id = ?", [$cid]);

            $recentLedger = DB::fetchAll(
                "SELECT sl.*, v.name AS variant_name, v.product_code, u.name AS user_name, con.name AS concern_name
                 FROM stock_ledger sl
                 JOIN variants v ON sl.variant_id = v.id
                 LEFT JOIN concerns con ON sl.concern_id = con.id
                 LEFT JOIN users u ON sl.created_by = u.id
                 WHERE sl.concern_id = ?
                 ORDER BY sl.id DESC LIMIT 8",
                [$cid]
            );

            $lowStocks = DB::fetchAll(
                "SELECT s.*, v.name AS variant_name, v.product_code, v.size, v.weight_unit, con.name AS concern_name
                 FROM stocks s
                 JOIN variants v ON s.variant_id = v.id
                 LEFT JOIN concerns con ON s.concern_id = con.id
                 WHERE s.concern_id = ? AND s.qty < 50
                 ORDER BY s.qty ASC LIMIT 5",
                [$cid]
            );
        } else {
            // Super Admin Consolidated View (All Concerns)
            $variantsCount = (int)DB::fetchValue("SELECT COUNT(*) FROM variants");
            $totalStockQty = (float)DB::fetchValue("SELECT COALESCE(SUM(qty), 0) FROM stocks");
            $stockValuation = (float)DB::fetchValue("SELECT COALESCE(SUM(s.qty * v.price), 0) FROM stocks s JOIN variants v ON s.variant_id = v.id");
            $totalCustomers = (int)DB::fetchValue("SELECT COUNT(*) FROM customers");
            $totalSuppliers = (int)DB::fetchValue("SELECT COUNT(*) FROM suppliers");
            $cashBalance = (float)DB::fetchValue("SELECT COALESCE(SUM(balance), 0) FROM cash_accounts");

            $recentLedger = DB::fetchAll(
                "SELECT sl.*, v.name AS variant_name, v.product_code, u.name AS user_name, con.name AS concern_name
                 FROM stock_ledger sl
                 JOIN variants v ON sl.variant_id = v.id
                 LEFT JOIN concerns con ON sl.concern_id = con.id
                 LEFT JOIN users u ON sl.created_by = u.id
                 ORDER BY sl.id DESC LIMIT 8"
            );

            $lowStocks = DB::fetchAll(
                "SELECT s.*, v.name AS variant_name, v.product_code, v.size, v.weight_unit, con.name AS concern_name
                 FROM stocks s
                 JOIN variants v ON s.variant_id = v.id
                 LEFT JOIN concerns con ON s.concern_id = con.id
                 WHERE s.qty < 50
                 ORDER BY s.qty ASC LIMIT 5"
            );
        }

        $sinceDate = date('Y-m-d', strtotime('-6 days'));
        $cidWhereSales = $cid ? "AND concern_id = ?" : "";
        $cidParams = $cid ? [$cid] : [];

        $totalSales = (float)DB::fetchValue(
            "SELECT COALESCE(SUM(net_amount), 0) FROM sales_invoices WHERE 1=1 {$cidWhereSales}",
            $cidParams
        );
        $totalPurchases = (float)DB::fetchValue(
            "SELECT COALESCE(SUM(net_amount), 0) FROM purchase_bills WHERE 1=1 {$cidWhereSales}",
            $cidParams
        );

        $dailySalesRows = DB::fetchAll(
            "SELECT DATE(invoice_date) AS dt, COALESCE(SUM(net_amount), 0) AS total
             FROM sales_invoices
             WHERE invoice_date >= ? {$cidWhereSales}
             GROUP BY DATE(invoice_date)",
            array_merge([$sinceDate], $cidParams)
        );
        $dailyPurchaseRows = DB::fetchAll(
            "SELECT DATE(bill_date) AS dt, COALESCE(SUM(net_amount), 0) AS total
             FROM purchase_bills
             WHERE bill_date >= ? {$cidWhereSales}
             GROUP BY DATE(bill_date)",
            array_merge([$sinceDate], $cidParams)
        );

        $salesByDate = [];
        foreach ($dailySalesRows as $r) {
            $salesByDate[$r['dt']] = (float)$r['total'];
        }
        $purchasesByDate = [];
        foreach ($dailyPurchaseRows as $r) {
            $purchasesByDate[$r['dt']] = (float)$r['total'];
        }

        $dailyLabels = [];
        $dailySales = [];
        $dailyPurchases = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $dailyLabels[] = date('d M', strtotime($d));
            $dailySales[] = $salesByDate[$d] ?? 0.0;
            $dailyPurchases[] = $purchasesByDate[$d] ?? 0.0;
        }

        $invoiceCols = array_column(DB::fetchAll("SHOW COLUMNS FROM `sales_invoices`"), 'Field');
        if (in_array('customer_channel', $invoiceCols, true)) {
            $channelExpr = "COALESCE(NULLIF(TRIM(customer_channel), ''), 'Direct Customer')";
        } elseif (in_array('payment_method', $invoiceCols, true)) {
            $channelExpr = "COALESCE(NULLIF(TRIM(payment_method), ''), 'cash')";
        } else {
            $channelExpr = "'Direct Customer'";
        }
        $salesByChannel = DB::fetchAll(
            "SELECT {$channelExpr} AS label, COALESCE(SUM(net_amount), 0) AS total, COUNT(*) AS cnt
             FROM sales_invoices
             WHERE 1=1 {$cidWhereSales}
             GROUP BY label
             ORDER BY total DESC",
            $cidParams
        );

        $this->render('concern/dashboard', [
            'pageTitle'       => 'Concern Dashboard',
            'currentModule'   => 'concern_dashboard',
            'variantsCount'   => $variantsCount,
            'totalStockQty'   => $totalStockQty,
            'stockValuation'  => $stockValuation,
            'totalCustomers'  => $totalCustomers,
            'totalSuppliers'  => $totalSuppliers,
            'cashBalance'     => $cashBalance,
            'recentLedger'    => $recentLedger,
            'lowStocks'       => $lowStocks,
            'selectedCid'     => $cid,
            'totalSales'      => $totalSales,
            'totalPurchases'  => $totalPurchases,
            'dailyChartData'  => [
                'labels'    => $dailyLabels,
                'sales'     => $dailySales,
                'purchases' => $dailyPurchases,
            ],
            'salesByChannel'  => $salesByChannel,
        ]);
    }

    public function exportDashboard(): void {
        $cid = Auth::concernId();
        $format     = strtolower(trim($_GET['format'] ?? 'excel'));
        $period     = trim($_GET['period'] ?? 'all');
        $singleDate = trim($_GET['single_date'] ?? '');
        $fromDate   = trim($_GET['from_date'] ?? '');
        $toDate     = trim($_GET['to_date'] ?? '');

        $range = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT sl.*, v.name AS variant_name, v.product_code, v.price, u.name AS user_name, con.name AS concern_name
                FROM stock_ledger sl
                JOIN variants v ON sl.variant_id = v.id
                LEFT JOIN concerns con ON sl.concern_id = con.id
                LEFT JOIN users u ON sl.created_by = u.id
                WHERE 1=1";
        $params = [];

        if ($cid) {
            $sql .= " AND sl.concern_id = ?";
            $params[] = $cid;
        }

        if (!empty($range['start_date']) && !empty($range['end_date'])) {
            $sql .= " AND DATE(sl.created_at) >= ? AND DATE(sl.created_at) <= ?";
            $params[] = $range['start_date'];
            $params[] = $range['end_date'];
        }

        $sql .= " ORDER BY sl.id DESC";
        $records = DB::fetchAll($sql, $params);

        $headers = [
            '#', 'Timestamp', 'Concern', 'Product Code', 'Item Description', 'Ref Module', 'In (+Qty)', 'Out (-Qty)', 'Recorded By'
        ];

        $exportData = [];
        $totalIn = 0.0;
        $totalOut = 0.0;
        $sl = 1;

        foreach ($records as $r) {
            $inQty = (float)($r['qty_in'] ?? 0);
            $outQty = (float)($r['qty_out'] ?? 0);
            $totalIn += $inQty;
            $totalOut += $outQty;

            $exportData[] = [
                $sl++,
                date('d M Y H:i', strtotime($r['created_at'])),
                $r['concern_name'] ?? 'Main Enterprise',
                $r['product_code'],
                $r['variant_name'],
                $r['ref_module'],
                $inQty > 0 ? number_format($inQty, 2) : '-',
                $outQty > 0 ? number_format($outQty, 2) : '-',
                $r['user_name'] ?? 'System'
            ];
        }

        $totalsRow = [
            'TOTAL MOVEMENTS', '', count($exportData) . ' Movements', '', '', '',
            number_format($totalIn, 2),
            number_format($totalOut, 2),
            'NET: ' . number_format($totalIn - $totalOut, 2)
        ];

        $concernName = $cid ? (Auth::concern()['name'] ?? 'Concern') : 'Consolidated (All Concerns)';
        $metadata = [
            'Scope'        => $concernName,
            'Period Scope' => $range['label'],
            'Generated By' => Auth::user()['name'] ?? 'User',
        ];

        $cleanPeriod = preg_replace('/[^a-zA-Z0-9_-]/', '_', $range['period']);
        $filename = 'Dashboard_Ledger_Report_' . $cleanPeriod . '_' . date('Ymd_His');

        if ($format === 'excel' || $format === 'xls' || $format === 'xlsx') {
            \App\Services\ExportService::downloadExcel($filename, 'Concern Operations & Stock Movement Report', $metadata, $headers, $exportData, $totalsRow);
        } elseif ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Concern Operations & Stock Movement Report', $metadata, $headers, $exportData, $totalsRow);
        } else {
            \App\Services\ExportService::downloadCsv($filename, $headers, $exportData, $totalsRow);
        }
    }
}
