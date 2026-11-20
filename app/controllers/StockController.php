<?php
// app/controllers/StockController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Core\ModuleGuard;
use App\Services\StockService;

class StockController extends Controller {
    public function index(): void {
        $cid = Auth::concernId();

        $sql = "SELECT s.*, v.name AS variant_name, v.sku_code, v.size, v.weight, v.weight_unit, v.price, v.mrp, v.catalog_id,
                       v.attributes AS variant_attributes,
                       c.name AS category_name, sub.name AS subcategory_name,
                       con.name AS concern_name, con.code AS concern_code,
                       (SELECT pbi.id
                        FROM purchase_bill_items pbi
                        WHERE pbi.concern_id = s.concern_id
                          AND pbi.section_type = 'finished_goods'
                          AND COALESCE(pbi.inward_status, 'pending') = 'completed'
                          AND (pbi.variant_id = v.id OR (v.catalog_id IS NOT NULL AND pbi.catalog_id = v.catalog_id))
                          AND (latest_purchase.ref_module IS NULL OR latest_purchase.ref_module <> 'Purchase Inward Item' OR pbi.id = latest_purchase.ref_id)
                          AND (latest_purchase.ref_module IS NULL OR latest_purchase.ref_module <> 'Purchase Inward' OR pbi.purchase_bill_id = latest_purchase.ref_id)
                        ORDER BY CASE
                                   WHEN latest_purchase.ref_module = 'Purchase Inward Item' AND pbi.id = latest_purchase.ref_id THEN 0
                                   WHEN pbi.variant_id = v.id THEN 1
                                   ELSE 2
                                 END,
                                 pbi.id DESC
                        LIMIT 1) AS purchase_item_id,
                       CASE latest_inbound.ref_module
                           WHEN 'Production Inward' THEN 'own'
                           WHEN 'Production Stock Adjustment' THEN 'own'
                           WHEN 'Purchase Inward' THEN 'purchase'
                           WHEN 'Purchase Inward Item' THEN 'purchase'
                           WHEN 'Purchase Stock Adjustment' THEN 'purchase'
                           ELSE 'other'
                       END AS stock_source
                FROM stocks s
                JOIN variants v ON s.variant_id = v.id
                LEFT JOIN concerns con ON s.concern_id = con.id
                LEFT JOIN categories c ON v.category_id = c.id
                LEFT JOIN subcategories sub ON v.subcategory_id = sub.id
                LEFT JOIN (
                    SELECT inbound.concern_id, inbound.variant_id, inbound.location, inbound.ref_module, inbound.ref_id
                    FROM stock_ledger inbound
                    JOIN (
                        SELECT concern_id, variant_id, location, MAX(id) AS latest_id
                        FROM stock_ledger
                        WHERE qty_in > 0
                        GROUP BY concern_id, variant_id, location
                    ) latest ON latest.latest_id = inbound.id
                ) latest_inbound
                  ON latest_inbound.concern_id = s.concern_id
                 AND latest_inbound.variant_id = s.variant_id
                 AND latest_inbound.location = s.location
                LEFT JOIN (
                    SELECT inbound.concern_id, inbound.variant_id, inbound.location, inbound.ref_module, inbound.ref_id
                    FROM stock_ledger inbound
                    JOIN (
                        SELECT concern_id, variant_id, location, MAX(id) AS latest_id
                        FROM stock_ledger
                        WHERE qty_in > 0 AND ref_module IN ('Purchase Inward', 'Purchase Inward Item')
                        GROUP BY concern_id, variant_id, location
                    ) latest ON latest.latest_id = inbound.id
                ) latest_purchase
                  ON latest_purchase.concern_id = s.concern_id
                 AND latest_purchase.variant_id = s.variant_id
                 AND latest_purchase.location = s.location";
        
        $sql .= " WHERE s.qty > 0
                  AND latest_inbound.ref_module IN (
                      'Production Inward', 'Production Stock Adjustment',
                      'Purchase Inward', 'Purchase Inward Item', 'Purchase Stock Adjustment'
                  )";
        $params = [];
        if ($cid) {
            $sql .= " AND s.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY FIELD(stock_source, 'own', 'purchase', 'other'), s.qty DESC";
        $stocks = DB::fetchAll($sql, $params);
        $totalUnits = array_sum(array_column($stocks, 'qty'));
        $totalValuation = array_reduce($stocks, fn($sum, $item) => $sum + ($item['qty'] * $item['price']), 0);

        $this->render('concern/stocks', [
            'pageTitle'      => 'Live Inventory Stocks',
            'currentModule'  => 'stocks',
            'stocks'         => $stocks,
            'totalUnits'     => $totalUnits,
            'totalValuation' => $totalValuation,
            'selectedCid'    => $cid,
            'canEditStock'   => Auth::isSuperAdmin() || Auth::can('stocks', 'edit'),
        ]);
    }

    public function updateQuantity(): void
    {
        $this->changeStockQuantity(false);
    }

    public function deleteStockLine(): void
    {
        $this->changeStockQuantity(true);
    }

    private function changeStockQuantity(bool $remove): void
    {
        ModuleGuard::enforce('stocks', 'edit');
        $this->validateCsrf();

        try {
            $concernId = $this->resolveStockActionConcern();
            $variantId = filter_var($_POST['variant_id'] ?? null, FILTER_VALIDATE_INT);
            $rawLocation = $_POST['location'] ?? null;
            if (!is_string($rawLocation)) {
                throw new \InvalidArgumentException('Select a valid stock location.');
            }
            $location = trim($rawLocation);
            if (!$variantId || $variantId < 1 || $location === '' || strlen($location) > 50) {
                throw new \InvalidArgumentException('Select a valid stock line.');
            }

            $newQuantity = 0;
            if (!$remove) {
                $rawQuantity = $_POST['qty'] ?? null;
                $newQuantity = filter_var($rawQuantity, FILTER_VALIDATE_INT);
                if ($newQuantity === false || $newQuantity < 0 || $newQuantity > 9999999999) {
                    throw new \InvalidArgumentException('Quantity must be a whole number between 0 and 9,999,999,999.');
                }
            }

            DB::beginTransaction();
            $stock = DB::fetch(
                "SELECT s.qty,
                        (SELECT inbound.ref_module
                         FROM stock_ledger inbound
                         WHERE inbound.concern_id = s.concern_id
                           AND inbound.variant_id = s.variant_id
                           AND inbound.location = s.location
                           AND inbound.qty_in > 0
                         ORDER BY inbound.id DESC
                         LIMIT 1) AS latest_inbound_module
                 FROM stocks s
                 JOIN variants v ON v.id = s.variant_id AND v.concern_id = s.concern_id
                 WHERE s.concern_id = ? AND s.variant_id = ? AND s.location = ?
                 FOR UPDATE",
                [$concernId, $variantId, $location]
            );
            if (!$stock) {
                throw new \RuntimeException('This stock line was not found for the selected concern.');
            }

            $currentQuantity = (float)$stock['qty'];
            $targetQuantity = $remove ? 0.0 : (float)$newQuantity;
            $difference = $targetQuantity - $currentQuantity;
            if (abs($difference) > 0.00001) {
                $refModule = $this->stockAdjustmentModule((string)$stock['latest_inbound_module']);
                StockService::move(
                    $concernId,
                    $variantId,
                    $location,
                    max(0, $difference),
                    max(0, -$difference),
                    $refModule,
                    null,
                    $remove
                        ? "Live stock line removed; balance adjusted from {$currentQuantity} to zero."
                        : "Live stock quantity adjusted from {$currentQuantity} to {$targetQuantity}.",
                    Auth::id()
                );
            }

            DB::commit();
            $this->flash(
                'success',
                $remove
                    ? 'Stock line was zeroed. Its movement history remains in the stock ledger.'
                    : 'Stock quantity updated and recorded in the stock ledger.'
            );
        } catch (\Throwable $e) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            $this->flash('error', 'Unable to update stock: ' . $e->getMessage());
        }

        $this->redirect('/m/stocks');
    }

    private function resolveStockActionConcern(): int
    {
        $rawConcernId = $_POST['concern_id'] ?? null;
        $postedConcernId = is_scalar($rawConcernId)
            ? filter_var($rawConcernId, FILTER_VALIDATE_INT)
            : false;
        if (Auth::isSuperAdmin()) {
            if ($postedConcernId === false || $postedConcernId < 1) {
                throw new \InvalidArgumentException('Select a concern for this stock action.');
            }
            return $postedConcernId;
        }

        $concernId = (int)(Auth::concernId() ?? 0);
        if (
            $concernId < 1 ||
            ($postedConcernId !== false && $postedConcernId > 0 && $postedConcernId !== $concernId)
        ) {
            throw new \InvalidArgumentException('This stock line is outside your concern.');
        }
        return $concernId;
    }

    private function stockAdjustmentModule(string $latestInboundModule): string
    {
        if (in_array($latestInboundModule, ['Production Inward', 'Production Stock Adjustment'], true)) {
            return 'Production Stock Adjustment';
        }
        if (in_array($latestInboundModule, ['Purchase Inward', 'Purchase Inward Item', 'Purchase Stock Adjustment'], true)) {
            return 'Purchase Stock Adjustment';
        }
        throw new \RuntimeException('Only own-product and purchase-product stock can be adjusted here.');
    }

    public function ledger(): void {
        $cid = Auth::concernId();

        $sql = "SELECT sl.*, v.name AS variant_name, v.sku_code, v.weight_unit, u.name AS user_name,
                       con.name AS concern_name, con.code AS concern_code
                FROM stock_ledger sl
                JOIN variants v ON sl.variant_id = v.id
                LEFT JOIN concerns con ON sl.concern_id = con.id
                LEFT JOIN users u ON sl.created_by = u.id";
        
        $params = [];
        if ($cid) {
            $sql .= " WHERE sl.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY sl.id DESC";
        $ledger = DB::fetchAll($sql, $params);

        $this->render('concern/stock_ledger', [
            'pageTitle'     => 'Stock Movement Ledger (Audit Trail)',
            'currentModule' => 'stocks',
            'ledger'        => $ledger,
            'selectedCid'   => $cid,
        ]);
    }

    public function exportStocks(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT s.*, v.name AS variant_name, v.sku_code, v.size, v.weight, v.weight_unit, v.price, v.mrp,
                       c.name AS category_name, sub.name AS subcategory_name,
                       con.name AS concern_name, con.code AS concern_code
                FROM stocks s
                JOIN variants v ON s.variant_id = v.id
                LEFT JOIN concerns con ON s.concern_id = con.id
                LEFT JOIN categories c ON v.category_id = c.id
                LEFT JOIN subcategories sub ON v.subcategory_id = sub.id";
        
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "s.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(s.updated_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY s.qty DESC";
        $stocks = DB::fetchAll($sql, $params);

        $headers = [
            'SKU Code', 'Variant Name', 'Category', 'Subcategory', 'Quantity', 'Unit', 
            'Cost Price (₹)', 'Selling MRP (₹)', 'Valuation (₹)', 'Concern'
        ];

        $rows = [];
        $totalQty = 0;
        $totalVal = 0;
        foreach ($stocks as $s) {
            $val = (float)$s['qty'] * (float)$s['price'];
            $totalQty += (float)$s['qty'];
            $totalVal += $val;
            $rows[] = [
                $s['sku_code'],
                $s['variant_name'],
                $s['category_name'] ?? 'General',
                $s['subcategory_name'] ?? '-',
                (float)$s['qty'],
                $s['weight_unit'] ?: 'Pcs',
                '₹' . number_format((float)$s['price'], 2),
                '₹' . number_format((float)$s['mrp'], 2),
                '₹' . number_format($val, 2),
                $s['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total', '', '', '', $totalQty, '', '', '', '₹' . number_format($totalVal, 2), ''];
        $metadata = [
            'Report Title' => 'Current Live Stock Balances & Valuation',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total SKUs' => count($stocks),
            'Total Units' => number_format($totalQty, 2),
            'Total Valuation' => '₹' . number_format($totalVal, 2)
        ];

        $filename = 'stocks_report_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Stocks Inventory & Valuation Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Stocks Inventory & Valuation Report', $metadata, $headers, $rows, $totals);
        }
    }

    public function exportLedger(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT sl.*, v.name AS variant_name, v.sku_code, v.weight_unit, u.name AS user_name,
                       con.name AS concern_name, con.code AS concern_code
                FROM stock_ledger sl
                JOIN variants v ON sl.variant_id = v.id
                LEFT JOIN concerns con ON sl.concern_id = con.id
                LEFT JOIN users u ON sl.created_by = u.id";

        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "sl.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(sl.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY sl.id DESC";
        $ledger = DB::fetchAll($sql, $params);

        $headers = [
            'ID', 'Date & Time', 'SKU Code', 'Variant Name', 'Tx Type', 
            'Qty In', 'Qty Out', 'Balance', 'Ref Type', 'Ref ID', 'User', 'Concern'
        ];

        $rows = [];
        $totIn = 0;
        $totOut = 0;
        foreach ($ledger as $l) {
            $in = (float)($l['qty_in'] ?? 0);
            $out = (float)($l['qty_out'] ?? 0);
            $totIn += $in;
            $totOut += $out;
            $rows[] = [
                '#' . $l['id'],
                date('d M Y, h:i A', strtotime($l['created_at'])),
                $l['sku_code'],
                $l['variant_name'],
                $l['ref_module'] ?: 'Movement',
                $in,
                $out,
                $l['location'] ?? 'Main Store',
                $l['ref_id'] ? '#' . $l['ref_id'] : '-',
                $l['note'] ?: '-',
                $l['user_name'] ?? 'System',
                $l['concern_name'] ?? 'Consolidated'
            ];
        }

        $headers = [
            'ID', 'Date & Time', 'SKU Code', 'Variant Name', 'Module', 
            'Qty In (+)', 'Qty Out (-)', 'Location', 'Ref ID', 'Notes', 'User', 'Concern'
        ];

        $totals = ['Total: ' . count($ledger) . ' Records', '', '', '', '', $totIn, $totOut, '', '', '', '', ''];
        $metadata = [
            'Report Title' => 'Stock Movement Ledger (Audit Trail)',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Transactions' => count($ledger)
        ];

        $filename = 'stock_ledger_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Stock Movement Ledger Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Stock Movement Ledger Report', $metadata, $headers, $rows, $totals);
        }
    }
}
