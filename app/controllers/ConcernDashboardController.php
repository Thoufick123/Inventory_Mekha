<?php
// app/controllers/ConcernDashboardController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Core\ModuleGuard;

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
            "SELECT COALESCE(SUM(net_amount), 0) FROM sales_invoices WHERE voided_at IS NULL {$cidWhereSales}",
            $cidParams
        );
        $totalPurchases = (float)DB::fetchValue(
            "SELECT COALESCE(SUM(net_amount), 0) FROM purchase_bills WHERE 1=1 {$cidWhereSales}",
            $cidParams
        );

        $dailySalesRows = DB::fetchAll(
            "SELECT DATE(invoice_date) AS dt, COALESCE(SUM(net_amount), 0) AS total
             FROM sales_invoices
             WHERE voided_at IS NULL AND invoice_date >= ? {$cidWhereSales}
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
             WHERE voided_at IS NULL {$cidWhereSales}
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

    public function reports(): void {
        ModuleGuard::enforce('concern_dashboard', 'view');
        $period     = trim($_GET['period'] ?? 'month');
        $singleDate = trim($_GET['single_date'] ?? '');
        $fromDate   = trim($_GET['from_date'] ?? '');
        $toDate     = trim($_GET['to_date'] ?? '');
        $unitId     = !empty($_GET['unit_id']) ? (int)$_GET['unit_id'] : null;

        $requestedIds = !empty($_GET['concern_id']) ? [(int)$_GET['concern_id']] : [];
        $scope = \App\Services\ReportService::resolveScope($requestedIds, $period, $singleDate, $fromDate, $toDate);
        $range = $scope['range'];
        $selectedConcernIds = $scope['concern_ids'];

        $reportData = \App\Services\ReportService::getOverviewPnlReport(
            $range['start_date'] ?? null,
            $range['end_date'] ?? null,
            $selectedConcernIds,
            $unitId
        );

        $this->render('reports/index', [
            'pageTitle'          => 'Financial & P&L Reports',
            'currentModule'      => 'reports',
            'reportBaseUrl'      => '/m/reports',
            'reportPrintUrl'     => '/m/reports/print',
            'reportExportUrl'    => '/m/reports/export',
            'reportData'         => $reportData,
            'concernStats'       => $reportData['concern_rows'] ?? [],
            'allConcerns'        => $scope['all_concerns'],
            'units'              => $scope['units'],
            'selectedConcernIds' => $selectedConcernIds,
            'selectedConcernId'  => (count($selectedConcernIds) === 1 ? $selectedConcernIds[0] : null),
            'selectedUnitId'     => $unitId,
            'period'             => $period,
            'singleDate'         => $singleDate,
            'fromDate'           => $fromDate,
            'toDate'             => $toDate,
            'rangeLabel'         => $range['label'],
            'startDate'          => $range['start_date'] ?? '',
            'endDate'            => $range['end_date'] ?? '',
            'isSuperAdmin'       => $scope['is_super_admin'],
        ]);
    }

    public function reportsPrint(): void {
        ModuleGuard::enforce('concern_dashboard', 'view');
        $period     = trim($_GET['period'] ?? 'month');
        $singleDate = trim($_GET['single_date'] ?? '');
        $fromDate   = trim($_GET['from_date'] ?? '');
        $toDate     = trim($_GET['to_date'] ?? '');
        $unitId     = !empty($_GET['unit_id']) ? (int)$_GET['unit_id'] : null;

        $requestedIds = !empty($_GET['concern_id']) ? [(int)$_GET['concern_id']] : [];
        $scope = \App\Services\ReportService::resolveScope($requestedIds, $period, $singleDate, $fromDate, $toDate);
        $range = $scope['range'];
        $selectedConcernIds = $scope['concern_ids'];

        $reportData = \App\Services\ReportService::getOverviewPnlReport(
            $range['start_date'] ?? null,
            $range['end_date'] ?? null,
            $selectedConcernIds,
            $unitId
        );

        $this->render('reports/print', [
            'pageTitle'          => 'P&L Report Print',
            'noLayout'           => true,
            'reportData'         => $reportData,
            'allConcerns'        => $scope['all_concerns'],
            'selectedConcernIds' => $selectedConcernIds,
            'rangeLabel'         => $range['label'],
            'generatedAt'        => date('d M Y, h:i A'),
        ]);
    }

    public function exportReports(): void {
        ModuleGuard::enforce('concern_dashboard', 'view');
        $format     = strtolower(trim($_GET['format'] ?? 'csv'));
        $period     = trim($_GET['period'] ?? 'month');
        $singleDate = trim($_GET['single_date'] ?? '');
        $fromDate   = trim($_GET['from_date'] ?? '');
        $toDate     = trim($_GET['to_date'] ?? '');

        $requestedIds = !empty($_GET['concern_id']) ? [(int)$_GET['concern_id']] : [];
        $scope = \App\Services\ReportService::resolveScope($requestedIds, $period, $singleDate, $fromDate, $toDate);
        $range = $scope['range'];
        $reportData = \App\Services\ReportService::getOverviewPnlReport(
            $range['start_date'] ?? null,
            $range['end_date'] ?? null,
            $scope['concern_ids'],
            null
        );

        $headers = ['Concern Code', 'Concern Name', 'Invoices', 'Net Sales (₹)', 'GST Collected (₹)', 'Discounts (₹)', 'Purchases (₹)', 'Expenses (₹)', 'Wastage Sales (₹)', 'Net P&L (₹)', 'Stock Valuation (₹)'];
        $rows = [];
        $totInv = 0; $totSales = 0; $totGst = 0; $totDisc = 0; $totPurch = 0; $totExp = 0; $totWast = 0; $totPnl = 0; $totVal = 0;
        foreach ($reportData['concern_rows'] as $cr) {
            $totInv += $cr['invoice_count'];
            $totSales += $cr['sales_net'];
            $totGst += $cr['gst_collected'];
            $totDisc += $cr['discounts'];
            $totPurch += $cr['purchases_net'];
            $totExp += $cr['expenses'];
            $totWast += $cr['wastage_sales'];
            $totPnl += $cr['net_pnl'];
            $totVal += $cr['stock_valuation'];
            $rows[] = [
                $cr['code'], $cr['name'], $cr['invoice_count'],
                number_format($cr['sales_net'], 2, '.', ''),
                number_format($cr['gst_collected'], 2, '.', ''),
                number_format($cr['discounts'], 2, '.', ''),
                number_format($cr['purchases_net'], 2, '.', ''),
                number_format($cr['expenses'], 2, '.', ''),
                number_format($cr['wastage_sales'], 2, '.', ''),
                number_format($cr['net_pnl'], 2, '.', ''),
                number_format($cr['stock_valuation'], 2, '.', ''),
            ];
        }
        $totals = ['TOTAL', '', $totInv, number_format($totSales, 2, '.', ''), number_format($totGst, 2, '.', ''), number_format($totDisc, 2, '.', ''), number_format($totPurch, 2, '.', ''), number_format($totExp, 2, '.', ''), number_format($totWast, 2, '.', ''), number_format($totPnl, 2, '.', ''), number_format($totVal, 2, '.', '')];
        $metadata = ['Report Title' => 'Overview & P&L Report', 'Period' => $range['label'], 'Generated At' => date('d M Y, h:i A')];
        $filename = 'pnl_report_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Overview & P&L Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'excel') {
            \App\Services\ExportService::downloadExcel($filename, 'Overview & P&L Report', $metadata, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        }
    }

    public function exportCenter(): void {
        ModuleGuard::enforce('concern_dashboard', 'view');
        $periodMode = trim($_GET['period_mode'] ?? 'month');
        $month = (int)($_GET['month'] ?? date('n'));
        $year = (int)($_GET['year'] ?? date('Y'));
        $fromDate = trim($_GET['from_date'] ?? date('Y-m-01'));
        $toDate = trim($_GET['to_date'] ?? date('Y-m-d'));
        $unitIds = array_values(array_unique(array_filter(array_map('intval', (array)($_GET['unit_ids'] ?? [])))));

        $ctx = \App\Services\ExportCenterService::resolveContext([]);
        $dateRange = \App\Services\ExportCenterService::resolveDatePeriod($periodMode, $month, $year, $fromDate, $toDate);
        $categories = \App\Services\ExportCenterService::getModuleDefinitions($ctx['concern_ids']);
        $counts = \App\Services\ExportCenterService::getModuleCounts(
            $dateRange['start_date'],
            $dateRange['end_date'],
            $ctx['concern_ids'],
            $unitIds
        );

        $this->render('export_center/index', [
            'pageTitle'      => 'Data Export Center',
            'currentModule'  => 'export_center',
            'ctx'            => $ctx,
            'dateRange'      => $dateRange,
            'categories'     => $categories,
            'counts'         => $counts,
            'selectedUnits'  => $unitIds,
            'basePath'       => '/m/export-center',
        ]);
    }

    public function exportCenterCounts(): void {
        ModuleGuard::enforce('concern_dashboard', 'view');
        $periodMode = trim($_GET['period_mode'] ?? 'month');
        $month = (int)($_GET['month'] ?? date('n'));
        $year = (int)($_GET['year'] ?? date('Y'));
        $fromDate = trim($_GET['from_date'] ?? date('Y-m-01'));
        $toDate = trim($_GET['to_date'] ?? date('Y-m-d'));
        $unitIds = array_values(array_unique(array_filter(array_map('intval', (array)($_GET['unit_ids'] ?? [])))));

        $ctx = \App\Services\ExportCenterService::resolveContext([]);
        $dateRange = \App\Services\ExportCenterService::resolveDatePeriod($periodMode, $month, $year, $fromDate, $toDate);
        $counts = \App\Services\ExportCenterService::getModuleCounts(
            $dateRange['start_date'],
            $dateRange['end_date'],
            $ctx['concern_ids'],
            $unitIds
        );

        header('Content-Type: application/json');
        echo json_encode(['counts' => $counts, 'date_range' => $dateRange, 'enabled_codes' => $ctx['enabled_codes']]);
        exit;
    }

    public function downloadExportCenter(): void {
        ModuleGuard::enforce('concern_dashboard', 'view');
        $periodMode = trim($_POST['period_mode'] ?? 'month');
        $month = (int)($_POST['month'] ?? date('n'));
        $year = (int)($_POST['year'] ?? date('Y'));
        $fromDate = trim($_POST['from_date'] ?? date('Y-m-01'));
        $toDate = trim($_POST['to_date'] ?? date('Y-m-d'));
        $unitIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['unit_ids'] ?? [])))));
        $modules = array_values(array_filter(array_map('trim', (array)($_POST['modules'] ?? []))));
        $format = strtolower(trim($_POST['format'] ?? 'csv'));

        $ctx = \App\Services\ExportCenterService::resolveContext([]);
        $dateRange = \App\Services\ExportCenterService::resolveDatePeriod($periodMode, $month, $year, $fromDate, $toDate);
        if (empty($modules)) {
            $modules = array_keys(\App\Services\ExportCenterService::getFlatModuleMap());
        }

        \App\Services\ExportCenterService::streamExport(
            $modules,
            $dateRange['start_date'],
            $dateRange['end_date'],
            $ctx['concern_ids'],
            $unitIds,
            $format
        );
    }
}
