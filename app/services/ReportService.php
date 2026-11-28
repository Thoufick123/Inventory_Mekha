<?php
// app/services/ReportService.php

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

class ReportService {
    /**
     * Resolve allowed concern IDs, date range, concerns list, and production units.
     */
    public static function resolveScope(
        array $requestedConcernIds = [],
        string $period = 'month',
        string $singleDate = '',
        string $fromDate = '',
        string $toDate = ''
    ): array {
        $isSuperAdmin = Auth::isSuperAdmin();
        $userCid = Auth::concernId();

        $concernIds = array_values(array_unique(array_filter(array_map('intval', $requestedConcernIds), static fn($id) => $id > 0)));
        if (!$isSuperAdmin) {
            $concernIds = $userCid ? [$userCid] : [];
        }

        $allConcerns = (!$isSuperAdmin && $userCid)
            ? DB::fetchAll("SELECT id, name, code, status FROM concerns WHERE id = ?", [$userCid])
            : DB::fetchAll("SELECT id, name, code, status FROM concerns ORDER BY name ASC");

        $validIds = array_map(static fn($c) => (int)$c['id'], $allConcerns);
        if (!empty($concernIds)) {
            $concernIds = array_values(array_intersect($concernIds, $validIds));
        }

        $range = ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $units = [];
        try {
            if (!empty($concernIds)) {
                $ph = implode(',', array_fill(0, count($concernIds), '?'));
                $units = DB::fetchAll("SELECT id, concern_id, code, name FROM production_units WHERE is_active = 1 AND concern_id IN ({$ph}) ORDER BY name ASC", $concernIds);
            } else {
                $units = DB::fetchAll("SELECT id, concern_id, code, name FROM production_units WHERE is_active = 1 ORDER BY name ASC");
            }
        } catch (\Throwable $e) {
            $units = [];
        }

        return [
            'is_super_admin' => $isSuperAdmin,
            'concern_ids'    => $concernIds,
            'all_concerns'   => $allConcerns,
            'units'          => $units,
            'range'          => $range,
            'period'         => $period,
            'single_date'    => $singleDate,
            'from_date'      => $fromDate,
            'to_date'        => $toDate,
        ];
    }

    /**
     * Build Overview / P&L report data scoped by date range and concerns.
     */
    public static function getOverviewPnlReport(?string $startDate, ?string $endDate, array $concernIds = [], ?int $unitId = null): array {
        $concernScope = self::buildConcernInClause($concernIds, 'concern_id');

        $salesDateWhere = self::buildDateRangeClause($startDate, $endDate, 'invoice_date');
        $purchaseDateWhere = self::buildDateRangeClause($startDate, $endDate, 'bill_date');
        $expenseDateWhere = self::buildDateRangeClause($startDate, $endDate, 'expense_date');
        $wastageDateWhere = self::buildDateRangeClause($startDate, $endDate, 'sale_date');
        $returnDateWhere = self::buildDateRangeClause($startDate, $endDate, 'return_date');
        $shiftDateWhere = self::buildDateRangeClause($startDate, $endDate, 'DATE(opened_at)');

        // 1. Sales Summary
        $salesRow = self::safeFetch(
            "SELECT COUNT(*) AS invoice_count,
                    COALESCE(SUM(total_amount), 0) AS gross_sales,
                    COALESCE(SUM(COALESCE(discount_amount, 0) + COALESCE(coupon_discount_amount, 0)), 0) AS discounts_coupons,
                    COALESCE(SUM(COALESCE(tax_amount, 0)), 0) AS gst_collected,
                    COALESCE(SUM(net_amount), 0) AS net_sales,
                    COALESCE(SUM(CASE WHEN is_direct_bill = 1 THEN net_amount ELSE 0 END), 0) AS direct_sales,
                    COALESCE(SUM(CASE WHEN is_direct_bill = 0 THEN net_amount ELSE 0 END), 0) AS standard_sales,
                    COALESCE(SUM(CASE WHEN billing_company_name IS NOT NULL AND TRIM(billing_company_name) != '' THEN net_amount ELSE 0 END), 0) AS b2b_sales,
                    COALESCE(SUM(CASE WHEN billing_company_name IS NULL OR TRIM(billing_company_name) = '' THEN net_amount ELSE 0 END), 0) AS b2c_sales,
                    SUM(CASE WHEN billing_company_name IS NOT NULL AND TRIM(billing_company_name) != '' THEN 1 ELSE 0 END) AS b2b_count,
                    SUM(CASE WHEN billing_company_name IS NULL OR TRIM(billing_company_name) = '' THEN 1 ELSE 0 END) AS b2c_count
             FROM sales_invoices
             WHERE 1=1 {$concernScope['sql']} {$salesDateWhere['sql']}",
            array_merge($concernScope['params'], $salesDateWhere['params'])
        );

        // 2. Purchases Summary
        $purchaseRow = self::safeFetch(
            "SELECT COUNT(*) AS purchase_count,
                    COALESCE(SUM(net_amount), 0) AS purchases_cost,
                    COALESCE(SUM(COALESCE(tax_amount, 0)), 0) AS purchase_tax
             FROM purchase_bills
             WHERE 1=1 {$concernScope['sql']} {$purchaseDateWhere['sql']}",
            array_merge($concernScope['params'], $purchaseDateWhere['params'])
        );

        // 3. Operating Expenses
        $expenseRow = self::safeFetch(
            "SELECT COUNT(*) AS expense_count,
                    COALESCE(SUM(amount), 0) AS operating_expenses
             FROM expenses
             WHERE 1=1 {$concernScope['sql']} {$expenseDateWhere['sql']}",
            array_merge($concernScope['params'], $expenseDateWhere['params'])
        );

        // 4. Wastage Sales (Cutting + Stitching)
        $cutWastage = (float)self::safeFetchValue(
            "SELECT COALESCE(SUM(total_price), 0) FROM cutting_wastage_sales WHERE 1=1 {$concernScope['sql']} {$wastageDateWhere['sql']}",
            array_merge($concernScope['params'], $wastageDateWhere['params'])
        );
        $stitchWastage = (float)self::safeFetchValue(
            "SELECT COALESCE(SUM(total_price), 0) FROM stitching_wastage_sales WHERE 1=1 {$concernScope['sql']} {$wastageDateWhere['sql']}",
            array_merge($concernScope['params'], $wastageDateWhere['params'])
        );
        $wastageSales = $cutWastage + $stitchWastage;

        // 5. Returns
        $retRow = self::safeFetch(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(returned_qty), 0) AS qty
             FROM returns_exchanges
             WHERE 1=1 {$concernScope['sql']} {$returnDateWhere['sql']}",
            array_merge($concernScope['params'], $returnDateWhere['params'])
        );

        // 6. Current Inventory Valuation
        $stockConcernScope = self::buildConcernInClause($concernIds, 's.concern_id');
        $stockRow = self::safeFetch(
            "SELECT COALESCE(SUM(s.qty), 0) AS stock_qty,
                    COALESCE(SUM(s.qty * v.price), 0) AS stock_valuation
             FROM stocks s
             JOIN variants v ON s.variant_id = v.id
             WHERE 1=1 {$stockConcernScope['sql']}",
            $stockConcernScope['params']
        );

        $netSales = (float)($salesRow['net_sales'] ?? 0);
        $purchasesCost = (float)($purchaseRow['purchases_cost'] ?? 0);
        $operatingExpenses = (float)($expenseRow['operating_expenses'] ?? 0);
        $netPnl = $netSales + $wastageSales - $purchasesCost - $operatingExpenses;

        $summary = [
            'invoice_count'      => (int)($salesRow['invoice_count'] ?? 0),
            'gross_sales'        => (float)($salesRow['gross_sales'] ?? 0),
            'standard_sales'     => (float)($salesRow['standard_sales'] ?? 0),
            'direct_sales'       => (float)($salesRow['direct_sales'] ?? 0),
            'discounts_coupons'  => (float)($salesRow['discounts_coupons'] ?? 0),
            'gst_collected'      => (float)($salesRow['gst_collected'] ?? 0),
            'net_sales'          => $netSales,
            'b2b_sales'          => (float)($salesRow['b2b_sales'] ?? 0),
            'b2c_sales'          => (float)($salesRow['b2c_sales'] ?? 0),
            'b2b_count'          => (int)($salesRow['b2b_count'] ?? 0),
            'b2c_count'          => (int)($salesRow['b2c_count'] ?? 0),
            'purchase_count'     => (int)($purchaseRow['purchase_count'] ?? 0),
            'purchases_cost'     => $purchasesCost,
            'purchase_tax'       => (float)($purchaseRow['purchase_tax'] ?? 0),
            'expense_count'      => (int)($expenseRow['expense_count'] ?? 0),
            'operating_expenses' => $operatingExpenses,
            'wastage_sales'      => $wastageSales,
            'returns_count'      => (int)($retRow['cnt'] ?? 0),
            'returns_qty'        => (float)($retRow['qty'] ?? 0),
            'stock_qty'          => (float)($stockRow['stock_qty'] ?? 0),
            'stock_valuation'    => (float)($stockRow['stock_valuation'] ?? 0),
            'net_profit_loss'    => $netPnl,
        ];

        // 7. Per-Concern P&L Breakdown
        $cScopeC = self::buildConcernInClause($concernIds, 'c.id');
        $concernsList = DB::fetchAll(
            "SELECT c.id, c.code, c.name FROM concerns c WHERE 1=1 {$cScopeC['sql']} ORDER BY c.id ASC",
            $cScopeC['params']
        );

        $salesByCon = self::indexByConcern(
            "SELECT concern_id, COUNT(*) AS cnt, COALESCE(SUM(net_amount), 0) AS net_sales,
                    COALESCE(SUM(COALESCE(tax_amount, 0)), 0) AS gst_collected,
                    COALESCE(SUM(COALESCE(discount_amount, 0) + COALESCE(coupon_discount_amount, 0)), 0) AS discounts
             FROM sales_invoices WHERE 1=1 {$concernScope['sql']} {$salesDateWhere['sql']} GROUP BY concern_id",
            array_merge($concernScope['params'], $salesDateWhere['params'])
        );
        $purchByCon = self::indexByConcern(
            "SELECT concern_id, COALESCE(SUM(net_amount), 0) AS purchases_net
             FROM purchase_bills WHERE 1=1 {$concernScope['sql']} {$purchaseDateWhere['sql']} GROUP BY concern_id",
            array_merge($concernScope['params'], $purchaseDateWhere['params'])
        );
        $expByCon = self::indexByConcern(
            "SELECT concern_id, COALESCE(SUM(amount), 0) AS expenses
             FROM expenses WHERE 1=1 {$concernScope['sql']} {$expenseDateWhere['sql']} GROUP BY concern_id",
            array_merge($concernScope['params'], $expenseDateWhere['params'])
        );
        $cutWastByCon = self::indexByConcern(
            "SELECT concern_id, COALESCE(SUM(total_price), 0) AS wastage
             FROM cutting_wastage_sales WHERE 1=1 {$concernScope['sql']} {$wastageDateWhere['sql']} GROUP BY concern_id",
            array_merge($concernScope['params'], $wastageDateWhere['params'])
        );
        $stWastByCon = self::indexByConcern(
            "SELECT concern_id, COALESCE(SUM(total_price), 0) AS wastage
             FROM stitching_wastage_sales WHERE 1=1 {$concernScope['sql']} {$wastageDateWhere['sql']} GROUP BY concern_id",
            array_merge($concernScope['params'], $wastageDateWhere['params'])
        );
        $stockByCon = self::indexByConcern(
            "SELECT s.concern_id, COALESCE(SUM(s.qty), 0) AS stock_qty, COALESCE(SUM(s.qty * v.price), 0) AS stock_val
             FROM stocks s JOIN variants v ON s.variant_id = v.id WHERE 1=1 {$stockConcernScope['sql']} GROUP BY s.concern_id",
            $stockConcernScope['params']
        );

        $concernRows = [];
        foreach ($concernsList as $c) {
            $cidKey = (int)$c['id'];
            $cSales = (float)($salesByCon[$cidKey]['net_sales'] ?? 0);
            $cGst = (float)($salesByCon[$cidKey]['gst_collected'] ?? 0);
            $cDisc = (float)($salesByCon[$cidKey]['discounts'] ?? 0);
            $cPurch = (float)($purchByCon[$cidKey]['purchases_net'] ?? 0);
            $cExp = (float)($expByCon[$cidKey]['expenses'] ?? 0);
            $cWast = (float)($cutWastByCon[$cidKey]['wastage'] ?? 0) + (float)($stWastByCon[$cidKey]['wastage'] ?? 0);
            $cPnl = $cSales + $cWast - $cPurch - $cExp;
            $concernRows[] = [
                'id'              => $cidKey,
                'code'            => $c['code'],
                'name'            => $c['name'],
                'invoice_count'   => (int)($salesByCon[$cidKey]['cnt'] ?? 0),
                'sales_net'       => $cSales,
                'gst_collected'   => $cGst,
                'discounts'       => $cDisc,
                'purchases_net'   => $cPurch,
                'expenses'        => $cExp,
                'wastage_sales'   => $cWast,
                'net_pnl'         => $cPnl,
                'stock_qty'       => (float)($stockByCon[$cidKey]['stock_qty'] ?? 0),
                'stock_valuation' => (float)($stockByCon[$cidKey]['stock_val'] ?? 0),
            ];
        }

        // 8. Payment Mode Split
        $paymentModes = self::safeFetchAll(
            "SELECT COALESCE(NULLIF(TRIM(payment_method), ''), 'cash') AS mode,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(net_amount), 0) AS amount
             FROM sales_invoices
             WHERE 1=1 {$concernScope['sql']} {$salesDateWhere['sql']}
             GROUP BY mode
             ORDER BY amount DESC",
            array_merge($concernScope['params'], $salesDateWhere['params'])
        );

        // 9. Daily Sales vs Outflow Trend (last 14 active days or within range)
        $dailySales = self::safeFetchAll(
            "SELECT DATE(invoice_date) AS dt, COALESCE(SUM(net_amount), 0) AS amt
             FROM sales_invoices WHERE 1=1 {$concernScope['sql']} {$salesDateWhere['sql']}
             GROUP BY DATE(invoice_date) ORDER BY dt ASC",
            array_merge($concernScope['params'], $salesDateWhere['params'])
        );
        $dailyExp = self::safeFetchAll(
            "SELECT DATE(expense_date) AS dt, COALESCE(SUM(amount), 0) AS amt
             FROM expenses WHERE 1=1 {$concernScope['sql']} {$expenseDateWhere['sql']}
             GROUP BY DATE(expense_date) ORDER BY dt ASC",
            array_merge($concernScope['params'], $expenseDateWhere['params'])
        );
        $trendMap = [];
        foreach ($dailySales as $ds) {
            $trendMap[$ds['dt']]['date'] = $ds['dt'];
            $trendMap[$ds['dt']]['sales'] = (float)$ds['amt'];
            $trendMap[$ds['dt']]['expenses'] = $trendMap[$ds['dt']]['expenses'] ?? 0.0;
        }
        foreach ($dailyExp as $de) {
            $trendMap[$de['dt']]['date'] = $de['dt'];
            $trendMap[$de['dt']]['sales'] = $trendMap[$de['dt']]['sales'] ?? 0.0;
            $trendMap[$de['dt']]['expenses'] = (float)$de['amt'];
        }
        ksort($trendMap);
        $dailyTrend = array_slice(array_values($trendMap), -14);

        // 10. Expense Category Breakdown
        $expenseCategories = self::safeFetchAll(
            "SELECT category, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
             FROM expenses
             WHERE 1=1 {$concernScope['sql']} {$expenseDateWhere['sql']}
             GROUP BY category
             ORDER BY total DESC
             LIMIT 10",
            array_merge($concernScope['params'], $expenseDateWhere['params'])
        );

        // 11. Cashier Shifts Summary
        $shifts = self::safeFetchAll(
            "SELECT shift_no, cashier_name, starting_float, expected_closing_float, closing_float, variance, status, opened_at
             FROM cashier_shifts
             WHERE 1=1 {$concernScope['sql']} {$shiftDateWhere['sql']}
             ORDER BY id DESC
             LIMIT 10",
            array_merge($concernScope['params'], $shiftDateWhere['params'])
        );

        return [
            'summary'            => $summary,
            'concern_rows'       => $concernRows,
            'payment_modes'      => $paymentModes,
            'daily_trend'        => $dailyTrend,
            'expense_categories' => $expenseCategories,
            'shifts'             => $shifts,
        ];
    }

    private static function buildConcernInClause(array $concernIds, string $col): array {
        if (empty($concernIds)) {
            return ['sql' => '', 'params' => []];
        }
        $ph = implode(',', array_fill(0, count($concernIds), '?'));
        return ['sql' => " AND {$col} IN ({$ph})", 'params' => $concernIds];
    }

    private static function buildDateRangeClause(?string $startDate, ?string $endDate, string $col): array {
        if (!$startDate || !$endDate) {
            return ['sql' => '', 'params' => []];
        }
        return ['sql' => " AND {$col} >= ? AND {$col} <= ?", 'params' => [$startDate, $endDate]];
    }

    private static function safeFetch(string $sql, array $params = []): array {
        try {
            return DB::fetch($sql, $params) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function safeFetchValue(string $sql, array $params = []) {
        try {
            return DB::fetchValue($sql, $params) ?: 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function safeFetchAll(string $sql, array $params = []): array {
        try {
            return DB::fetchAll($sql, $params) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function indexByConcern(string $sql, array $params = []): array {
        $rows = self::safeFetchAll($sql, $params);
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['concern_id']] = $r;
        }
        return $out;
    }
}
