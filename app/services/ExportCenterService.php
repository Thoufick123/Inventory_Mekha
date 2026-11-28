<?php
// app/services/ExportCenterService.php - Dynamic Panel-Module Data Export Center Engine

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

class ExportCenterService {
    /**
     * Resolve user scope, concerns, warehouses/units, and enabled concern modules.
     */
    public static function resolveContext(array $requestedConcernIds = []): array {
        $isSuperAdmin = Auth::isSuperAdmin();
        $userCid = Auth::concernId();

        $allConcerns = (!$isSuperAdmin && $userCid)
            ? DB::fetchAll("SELECT id, code, name, gstin, address, status FROM concerns WHERE id = ? ORDER BY name ASC", [$userCid])
            : DB::fetchAll("SELECT id, code, name, gstin, address, status FROM concerns ORDER BY name ASC");

        $allowedIds = array_map(static fn($c) => (int)$c['id'], $allConcerns);
        $concernIds = array_values(array_unique(array_filter(
            array_map('intval', $requestedConcernIds),
            static fn($id) => $id > 0 && in_array($id, $allowedIds, true)
        )));

        if (!$isSuperAdmin) {
            $concernIds = $userCid ? [$userCid] : [];
        }

        // Consignee / Destination Warehouses (production_units)
        if (!empty($concernIds)) {
            $ph = implode(',', array_fill(0, count($concernIds), '?'));
            $warehouses = DB::fetchAll(
                "SELECT pu.id, pu.concern_id, pu.code, pu.name, pu.location, c.code AS concern_code
                 FROM production_units pu
                 JOIN concerns c ON pu.concern_id = c.id
                 WHERE pu.is_active = 1 AND pu.concern_id IN ($ph)
                 ORDER BY c.name ASC, pu.name ASC",
                $concernIds
            );
        } elseif (!$isSuperAdmin && $userCid) {
            $warehouses = DB::fetchAll(
                "SELECT pu.id, pu.concern_id, pu.code, pu.name, pu.location, c.code AS concern_code
                 FROM production_units pu
                 JOIN concerns c ON pu.concern_id = c.id
                 WHERE pu.is_active = 1 AND pu.concern_id = ?
                 ORDER BY pu.name ASC",
                [$userCid]
            );
        } else {
            $warehouses = DB::fetchAll(
                "SELECT pu.id, pu.concern_id, pu.code, pu.name, pu.location, c.code AS concern_code
                 FROM production_units pu
                 JOIN concerns c ON pu.concern_id = c.id
                 WHERE pu.is_active = 1
                 ORDER BY c.name ASC, pu.name ASC"
            );
        }

        // Enabled concern module codes for selected concerns (or user's concern)
        $enabledCodes = [];
        $targetCids = !empty($concernIds) ? $concernIds : ($userCid ? [$userCid] : $allowedIds);
        if (!empty($targetCids)) {
            $ph = implode(',', array_fill(0, count($targetCids), '?'));
            $rows = DB::fetchAll(
                "SELECT DISTINCT m.code
                 FROM concern_modules cm
                 JOIN modules m ON cm.module_id = m.id
                 WHERE cm.enabled = 1 AND cm.concern_id IN ($ph)",
                $targetCids
            );
            $enabledCodes = array_column($rows, 'code');
        }

        $activeConcern = null;
        if (!$isSuperAdmin && !empty($allConcerns)) {
            $activeConcern = $allConcerns[0];
        } elseif (count($concernIds) === 1) {
            foreach ($allConcerns as $c) {
                if ((int)$c['id'] === $concernIds[0]) {
                    $activeConcern = $c;
                    break;
                }
            }
        }

        return [
            'is_super_admin'  => $isSuperAdmin,
            'concerns'        => $allConcerns,
            'concern_ids'     => $concernIds,
            'active_concern'  => $activeConcern,
            'warehouses'      => $warehouses,
            'enabled_codes'   => $enabledCodes,
        ];
    }

    /**
     * Resolve date range from Month-Wise or Custom Date Range selection.
     */
    public static function resolveDatePeriod(string $mode, int $month, int $year, string $fromDate, string $toDate): array {
        $mode = ($mode === 'custom') ? 'custom' : 'month';
        if ($mode === 'month') {
            $month = max(1, min(12, $month ?: (int)date('n')));
            $year = max(2020, min(2100, $year ?: (int)date('Y')));
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = date('Y-m-t', strtotime($start));
            return [
                'mode'       => 'month',
                'month'      => $month,
                'year'       => $year,
                'start_date' => $start,
                'end_date'   => $end,
                'label'      => date('F Y', strtotime($start)),
            ];
        }

        $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) ? $fromDate : date('Y-m-01');
        $end = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) ? $toDate : date('Y-m-d');
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }
        return [
            'mode'       => 'custom',
            'month'      => (int)date('n', strtotime($start)),
            'year'       => (int)date('Y', strtotime($start)),
            'start_date' => $start,
            'end_date'   => $end,
            'label'      => date('d M Y', strtotime($start)) . ' to ' . date('d M Y', strtotime($end)),
        ];
    }

    /**
     * Query-backed metadata for each panel module code in the `modules` table.
     */
    private static function getModuleQuerySpecs(): array {
        return [
            'purchase_billing' => [
                'description' => 'Purchase bills, supplier invoices, tax & net payable totals.',
                'table' => 'purchase_bills', 'date_col' => 'bill_date', 'is_datetime' => false, 'unit_col' => null,
            ],
            'purchase_stocks' => [
                'description' => 'Procured line items, quantities, unit rates & tax breakdowns.',
                'table' => 'purchase_bill_items', 'date_col' => 'pb.bill_date', 'is_datetime' => false, 'unit_col' => null,
            ],
            'catalog_mapping' => [
                'description' => 'Catalog hierarchy mappings, selected sizes, colors & variants.',
                'table' => 'catalog_mappings', 'date_col' => 'created_at', 'is_datetime' => true, 'unit_col' => null,
            ],
            'product_adding' => [
                'description' => 'Product variants, product codes, sizes, MRP & selling prices.',
                'table' => 'variants', 'date_col' => 'created_at', 'is_datetime' => true, 'unit_col' => null,
            ],
            'catalog_program' => [
                'description' => 'Production programs, planned quantities, target dates & statuses.',
                'table' => 'production_programs', 'date_col' => 'created_at', 'is_datetime' => true, 'unit_col' => null,
            ],
            'cutting_wastage' => [
                'description' => 'Cutting logs, roll weight, cut pieces, wastage & cost per piece.',
                'table' => 'cutting_logs', 'date_col' => 'created_at', 'is_datetime' => true, 'unit_col' => null,
            ],
            'stitching_wastage' => [
                'description' => 'Stitching batches, tailor assignments, stitched pieces & variance.',
                'table' => 'stitching_logs', 'date_col' => 'created_at', 'is_datetime' => true, 'unit_col' => null,
            ],
            'qc_stocks' => [
                'description' => 'QC inspections: inspected, passed, rework & rejected piece counts.',
                'table' => 'qc_inspections', 'date_col' => 'created_at', 'is_datetime' => true, 'unit_col' => null,
            ],
            'inward' => [
                'description' => 'Warehouse & factory receipts, GRN numbers, vehicle & status.',
                'table' => 'inwards', 'date_col' => 'inward_date', 'is_datetime' => false, 'unit_col' => 'production_unit_id',
            ],
            'stocks' => [
                'description' => 'Current live stock balances by variant, location & valuation.',
                'table' => 'stocks', 'date_col' => 'updated_at', 'is_datetime' => true, 'unit_col' => null,
            ],
            'concern_damage' => [
                'description' => 'Quarantined damaged pieces, severity, resolution & loss valuation.',
                'table' => 'concern_damages', 'date_col' => 'created_at', 'is_datetime' => true, 'unit_col' => null,
            ],
            'return_exchange' => [
                'description' => 'Customer returns, exchanges, returned quantities & reasons.',
                'table' => 'returns_exchanges', 'date_col' => 'return_date', 'is_datetime' => false, 'unit_col' => null,
            ],
            'billing' => [
                'description' => 'POS & B2B sales invoices, discounts, GST tax & net totals.',
                'table' => 'sales_invoices', 'date_col' => 'invoice_date', 'is_datetime' => false, 'unit_col' => null,
            ],
            'request' => [
                'description' => 'Stock transfer requests, requested/dispatched/received quantities.',
                'table' => 'production_stock_requests', 'date_col' => 'created_at', 'is_datetime' => true, 'unit_col' => null,
            ],
            'coupons' => [
                'description' => 'POS discount coupons, discount values, usage limits, expiry & status.',
                'table' => 'pos_coupons', 'date_col' => 'created_at', 'is_datetime' => true, 'unit_col' => null,
            ],
            'outward' => [
                'description' => 'Inter-concern & warehouse dispatches, boxes, vehicles & status.',
                'table' => 'outwards', 'date_col' => 'outward_date', 'is_datetime' => false, 'unit_col' => 'production_unit_id',
            ],
            'expense' => [
                'description' => 'Operating expense entries by category, payee & payment mode.',
                'table' => 'expenses', 'date_col' => 'expense_date', 'is_datetime' => false, 'unit_col' => null,
            ],
            'cash_flow' => [
                'description' => 'Cash & bank transactions across inflows, outflows & modules.',
                'table' => 'cash_transactions', 'date_col' => 'txn_date', 'is_datetime' => false, 'unit_col' => null,
            ],
            'staff' => [
                'description' => 'Staff directory, designations, departments & salary details.',
                'table' => 'staff', 'date_col' => 'created_at', 'is_datetime' => true, 'unit_col' => null,
            ],
        ];
    }

    /**
     * Dynamically load operational panel modules from `modules` + `concern_modules`.
     */
    public static function getModuleDefinitions(array $concernIds = []): array {
        $specs = self::getModuleQuerySpecs();
        $supportedCodes = array_keys($specs);
        $phCodes = implode(',', array_fill(0, count($supportedCodes), '?'));

        if (!empty($concernIds)) {
            $phCids = implode(',', array_fill(0, count($concernIds), '?'));
            $rows = DB::fetchAll(
                "SELECT DISTINCT m.code, m.name, m.icon, m.group_name, m.sort_order, m.id
                 FROM modules m
                 JOIN concern_modules cm ON cm.module_id = m.id
                 WHERE cm.enabled = 1
                   AND cm.concern_id IN ($phCids)
                   AND m.code IN ($phCodes)
                 ORDER BY m.sort_order ASC, m.id ASC",
                array_merge($concernIds, $supportedCodes)
            );
        } else {
            $rows = DB::fetchAll(
                "SELECT m.code, m.name, m.icon, m.group_name, m.sort_order, m.id
                 FROM modules m
                 WHERE m.code IN ($phCodes)
                 ORDER BY m.sort_order ASC, m.id ASC",
                $supportedCodes
            );
        }

        $grouped = [];
        foreach ($rows as $row) {
            $code = $row['code'];
            $spec = $specs[$code];
            $group = strtoupper(trim((string)($row['group_name'] ?: 'Modules')));
            $grouped[$group][] = [
                'key'         => $code,
                'title'       => $row['name'],
                'description' => $spec['description'],
                'icon'        => $row['icon'] ?: 'bi-grid',
                'module_code' => $code,
                'table'       => $spec['table'],
                'date_col'    => $spec['date_col'],
                'is_datetime' => $spec['is_datetime'],
                'unit_col'    => $spec['unit_col'],
            ];
            if ($code === 'request') {
                $cSpec = $specs['coupons'];
                $grouped[$group][] = [
                    'key'         => 'coupons',
                    'title'       => 'Coupons',
                    'description' => $cSpec['description'],
                    'icon'        => 'bi-ticket-perforated',
                    'module_code' => 'coupons',
                    'table'       => $cSpec['table'],
                    'date_col'    => $cSpec['date_col'],
                    'is_datetime' => $cSpec['is_datetime'],
                    'unit_col'    => $cSpec['unit_col'],
                ];
            }
        }

        return $grouped;
    }

    /**
     * Flat map of module code => definition.
     */
    public static function getFlatModuleMap(array $concernIds = []): array {
        $map = [];
        foreach (self::getModuleDefinitions($concernIds) as $items) {
            foreach ($items as $item) {
                $map[$item['key']] = $item;
            }
        }
        return $map;
    }

    /**
     * Build SARGable date + concern + unit WHERE clause.
     */
    private static function buildWhere(
        string $concernCol,
        string $dateCol,
        bool $isDatetime,
        string $startDate,
        string $endDate,
        array $concernIds,
        ?string $unitCol = null,
        array $unitIds = []
    ): array {
        $where = [];
        $params = [];

        if (!empty($concernIds)) {
            $ph = implode(',', array_fill(0, count($concernIds), '?'));
            $where[] = "$concernCol IN ($ph)";
            array_push($params, ...$concernIds);
        }

        if ($unitCol !== null && !empty($unitIds)) {
            $ph = implode(',', array_fill(0, count($unitIds), '?'));
            $where[] = "$unitCol IN ($ph)";
            array_push($params, ...$unitIds);
        }

        if ($isDatetime) {
            $where[] = "$dateCol >= ? AND $dateCol < DATE_ADD(?, INTERVAL 1 DAY)";
            $params[] = $startDate . ' 00:00:00';
            $params[] = $endDate;
        } else {
            $where[] = "$dateCol >= ? AND $dateCol <= ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    /**
     * Compute live record counts per active panel module.
     */
    public static function getModuleCounts(string $startDate, string $endDate, array $concernIds, array $unitIds = []): array {
        $counts = [];
        foreach (self::getFlatModuleMap($concernIds) as $key => $def) {
            try {
                if ($key === 'purchase_stocks') {
                    [$w, $p] = self::buildWhere('pb.concern_id', 'pb.bill_date', false, $startDate, $endDate, $concernIds);
                    $counts[$key] = (int)DB::fetchValue(
                        "SELECT COUNT(*) FROM purchase_bill_items pbi JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id $w",
                        $p
                    );
                } elseif ($key === 'stocks') {
                    $w = '';
                    $p = [];
                    if (!empty($concernIds)) {
                        $ph = implode(',', array_fill(0, count($concernIds), '?'));
                        $w = "WHERE concern_id IN ($ph)";
                        $p = $concernIds;
                    }
                    $counts[$key] = (int)DB::fetchValue("SELECT COUNT(*) FROM stocks $w", $p);
                } elseif ($key === 'coupons') {
                    CouponService::ensureSchema();
                    [$w, $p] = self::buildWhere('concern_id', 'created_at', true, $startDate, $endDate, $concernIds);
                    $counts[$key] = (int)DB::fetchValue("SELECT COUNT(*) FROM pos_coupons $w", $p);
                } else {
                    [$w, $p] = self::buildWhere(
                        'concern_id',
                        $def['date_col'],
                        (bool)$def['is_datetime'],
                        $startDate,
                        $endDate,
                        $concernIds,
                        $def['unit_col'],
                        $unitIds
                    );
                    $counts[$key] = (int)DB::fetchValue("SELECT COUNT(*) FROM `{$def['table']}` $w", $p);
                }
            } catch (\Throwable $e) {
                $counts[$key] = 0;
            }
        }
        return $counts;
    }

    /**
     * Fetch dataset headers & rows for a single panel module code.
     */
    public static function fetchModuleDataset(string $key, string $startDate, string $endDate, array $concernIds, array $unitIds = []): array {
        $map = self::getFlatModuleMap();
        if (!isset($map[$key])) {
            return ['title' => $key, 'headers' => [], 'rows' => []];
        }
        $def = $map[$key];

        switch ($key) {
            case 'purchase_billing':
                [$w, $p] = self::buildWhere('pb.concern_id', 'pb.bill_date', false, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT pb.bill_no, pb.bill_date, c.name AS concern, s.name AS supplier, pb.total_amount, pb.tax_amount, pb.net_amount, pb.payment_status
                     FROM purchase_bills pb
                     LEFT JOIN concerns c ON pb.concern_id = c.id
                     LEFT JOIN suppliers s ON pb.supplier_id = s.id
                     $w ORDER BY pb.bill_date DESC, pb.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Bill No', 'Bill Date', 'Concern', 'Supplier', 'Subtotal', 'Tax', 'Net Amount', 'Payment Status'], 'rows' => $rows];

            case 'purchase_stocks':
                [$w, $p] = self::buildWhere('pb.concern_id', 'pb.bill_date', false, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT pb.bill_no, pb.bill_date, c.name AS concern, pbi.item_name, pbi.qty, pbi.unit_price, pbi.tax_amount, pbi.total_price
                     FROM purchase_bill_items pbi
                     JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                     LEFT JOIN concerns c ON pb.concern_id = c.id
                     $w ORDER BY pb.bill_date DESC, pbi.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Bill No', 'Bill Date', 'Concern', 'Item Name', 'Qty', 'Unit Price', 'Tax', 'Total Price'], 'rows' => $rows];

            case 'catalog_mapping':
                [$w, $p] = self::buildWhere('cm.concern_id', 'cm.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT cm.id, c.name AS concern, cat.name AS catalog_name, v.product_code, cm.selected_sizes, cm.selected_color, cm.status, cm.created_at
                     FROM catalog_mappings cm
                     LEFT JOIN concerns c ON cm.concern_id = c.id
                     LEFT JOIN catalogs cat ON cm.catalog_id = cat.id
                     LEFT JOIN variants v ON cm.variant_id = v.id
                     $w ORDER BY cm.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['ID', 'Concern', 'Catalog', 'Product Code', 'Sizes', 'Color', 'Status', 'Created At'], 'rows' => $rows];

            case 'product_adding':
                [$w, $p] = self::buildWhere('v.concern_id', 'v.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT v.product_code, v.name, c.name AS concern, v.size, v.weight, v.mrp, v.price, v.created_at
                     FROM variants v LEFT JOIN concerns c ON v.concern_id = c.id $w ORDER BY v.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Product Code', 'Variant Name', 'Concern', 'Size', 'Weight', 'MRP', 'Selling Price', 'Created At'], 'rows' => $rows];

            case 'catalog_program':
                [$w, $p] = self::buildWhere('pp.concern_id', 'pp.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT pp.program_no, pp.program_name, c.name AS concern, pp.planned_qty, pp.status, pp.created_at
                     FROM production_programs pp LEFT JOIN concerns c ON pp.concern_id = c.id $w ORDER BY pp.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Program No', 'Program Name', 'Concern', 'Planned Qty', 'Status', 'Created At'], 'rows' => $rows];

            case 'cutting_wastage':
                [$w, $p] = self::buildWhere('cl.concern_id', 'cl.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT cl.log_no, cl.batch_no, cl.cutting_name, c.name AS concern, cl.cutting_master_name, cl.total_roll_weight, cl.cut_pieces, cl.wastage_qty, cl.fabric_cost_per_piece, cl.status, cl.created_at
                     FROM cutting_logs cl LEFT JOIN concerns c ON cl.concern_id = c.id $w ORDER BY cl.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Log No', 'Batch No', 'Cutting Name', 'Concern', 'Master', 'Roll Wt (kg)', 'Cut Pcs', 'Wastage', 'Cost/Pc', 'Status', 'Created At'], 'rows' => $rows];

            case 'stitching_wastage':
                [$w, $p] = self::buildWhere('sl.concern_id', 'sl.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT sl.log_no, sl.batch_no, sl.stitching_name, c.name AS concern, sl.tailor_name, sl.cut_pieces, sl.stitched_pieces, sl.wastage_pieces, sl.status, sl.created_at
                     FROM stitching_logs sl LEFT JOIN concerns c ON sl.concern_id = c.id $w ORDER BY sl.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Log No', 'Batch No', 'Stitching Name', 'Concern', 'Tailor', 'Cut Pcs', 'Stitched Pcs', 'Wastage Pcs', 'Status', 'Created At'], 'rows' => $rows];

            case 'qc_stocks':
                [$w, $p] = self::buildWhere('qc.concern_id', 'qc.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT qc.qc_no, qc.batch_no, c.name AS concern, qc.inspected_qty, qc.passed_qty, qc.rework_qty, qc.rejected_qty, qc.location, qc.status, qc.created_at
                     FROM qc_inspections qc LEFT JOIN concerns c ON qc.concern_id = c.id $w ORDER BY qc.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['QC No', 'Batch No', 'Concern', 'Inspected', 'Passed', 'Rework', 'Rejected', 'Location', 'Status', 'Created At'], 'rows' => $rows];

            case 'inward':
                [$w, $p] = self::buildWhere('i.concern_id', 'i.inward_date', false, $startDate, $endDate, $concernIds, 'i.production_unit_id', $unitIds);
                $rows = DB::fetchAll(
                    "SELECT i.inward_no, i.grn_number, i.inward_date, c.name AS concern, i.inward_type, i.location, i.vehicle_number, i.status
                     FROM inwards i LEFT JOIN concerns c ON i.concern_id = c.id $w ORDER BY i.inward_date DESC, i.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Inward No', 'GRN', 'Date', 'Concern', 'Type', 'Location', 'Vehicle', 'Status'], 'rows' => $rows];

            case 'stocks':
                $w = '';
                $p = [];
                if (!empty($concernIds)) {
                    $ph = implode(',', array_fill(0, count($concernIds), '?'));
                    $w = "WHERE s.concern_id IN ($ph)";
                    $p = $concernIds;
                }
                $rows = DB::fetchAll(
                    "SELECT v.product_code, v.name AS variant_name, c.name AS concern, s.location, s.qty, v.price, ROUND(s.qty * v.price, 2) AS stock_value, s.updated_at
                     FROM stocks s
                     JOIN variants v ON s.variant_id = v.id
                     LEFT JOIN concerns c ON s.concern_id = c.id
                     $w ORDER BY c.name ASC, v.name ASC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Product Code', 'Variant', 'Concern', 'Location', 'Qty', 'Selling Price', 'Valuation', 'Updated At'], 'rows' => $rows];

            case 'concern_damage':
                [$w, $p] = self::buildWhere('cd.concern_id', 'cd.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT cd.damage_no, c.name AS concern, v.product_code, v.name AS variant_name, cd.qty, cd.severity, cd.status, cd.loss_amount, cd.reason, cd.created_at
                     FROM concern_damages cd
                     LEFT JOIN concerns c ON cd.concern_id = c.id
                     LEFT JOIN variants v ON cd.variant_id = v.id
                     $w ORDER BY cd.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Damage No', 'Concern', 'Code', 'Variant', 'Qty', 'Severity', 'Status', 'Loss Amount', 'Reason', 'Created At'], 'rows' => $rows];

            case 'return_exchange':
                [$w, $p] = self::buildWhere('re.concern_id', 're.return_date', false, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT re.return_no, re.return_date, c.name AS concern, re.type, re.returned_qty, re.exchange_qty, re.reason, re.created_at
                     FROM returns_exchanges re LEFT JOIN concerns c ON re.concern_id = c.id $w ORDER BY re.return_date DESC, re.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Return No', 'Date', 'Concern', 'Type', 'Returned Qty', 'Exchange Qty', 'Reason', 'Created At'], 'rows' => $rows];

            case 'coupons':
                CouponService::ensureSchema();
                [$w, $p] = self::buildWhere('pc.concern_id', 'pc.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT pc.code, pc.title, c.name AS concern, pc.discount_type, pc.discount_value, pc.min_redemption_amount, pc.max_uses, pc.used_count, pc.expires_at, pc.status, pc.created_at
                     FROM pos_coupons pc LEFT JOIN concerns c ON pc.concern_id = c.id $w ORDER BY pc.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Coupon Code', 'Title', 'Concern', 'Discount Type', 'Discount Value', 'Min Redemption', 'Max Uses', 'Used Count', 'Expires At', 'Status', 'Created At'], 'rows' => $rows];

            case 'billing':
                [$w, $p] = self::buildWhere('si.concern_id', 'si.invoice_date', false, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT si.invoice_no, si.invoice_date, c.name AS concern, IF(si.is_direct_bill = 1, 'DIRECT', 'STANDARD') AS invoice_type, si.customer_channel, si.payment_method, si.total_amount, si.discount_amount, si.tax_amount, si.net_amount
                     FROM sales_invoices si LEFT JOIN concerns c ON si.concern_id = c.id $w ORDER BY si.invoice_date DESC, si.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Invoice No', 'Date', 'Concern', 'Type', 'Channel', 'Payment Method', 'Subtotal', 'Discount', 'Tax', 'Net Amount'], 'rows' => $rows];

            case 'request':
                [$w, $p] = self::buildWhere('psr.concern_id', 'psr.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT psr.request_no, c.name AS concern, psr.request_type, psr.requested_qty, psr.dispatched_qty, psr.received_qty, psr.status, psr.created_at
                     FROM production_stock_requests psr LEFT JOIN concerns c ON psr.concern_id = c.id $w ORDER BY psr.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Request No', 'Concern', 'Type', 'Requested Qty', 'Dispatched Qty', 'Received Qty', 'Status', 'Created At'], 'rows' => $rows];

            case 'outward':
                [$w, $p] = self::buildWhere('o.concern_id', 'o.outward_date', false, $startDate, $endDate, $concernIds, 'o.production_unit_id', $unitIds);
                $rows = DB::fetchAll(
                    "SELECT o.outward_no, o.outward_date, c.name AS concern, o.transfer_type, o.warehouse_name, o.total_boxes, o.vehicle_number, o.status
                     FROM outwards o LEFT JOIN concerns c ON o.concern_id = c.id $w ORDER BY o.outward_date DESC, o.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Outward No', 'Date', 'Concern', 'Transfer Type', 'Warehouse', 'Boxes', 'Vehicle', 'Status'], 'rows' => $rows];

            case 'expense':
                [$w, $p] = self::buildWhere('e.concern_id', 'e.expense_date', false, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT e.expense_no, e.expense_date, c.name AS concern, COALESCE(e.category, 'General') AS category, e.paid_to, e.payment_mode, e.amount, e.description
                     FROM expenses e
                     LEFT JOIN concerns c ON e.concern_id = c.id
                     $w ORDER BY e.expense_date DESC, e.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Expense No', 'Date', 'Concern', 'Category', 'Paid To', 'Payment Mode', 'Amount', 'Description'], 'rows' => $rows];

            case 'cash_flow':
                [$w, $p] = self::buildWhere('ct.concern_id', 'ct.txn_date', false, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT ct.txn_date, c.name AS concern, ct.type, ct.amount, ct.ref_module, ct.description, ct.created_at
                     FROM cash_transactions ct LEFT JOIN concerns c ON ct.concern_id = c.id $w ORDER BY ct.txn_date DESC, ct.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Date', 'Concern', 'Type', 'Amount', 'Module', 'Description', 'Created At'], 'rows' => $rows];

            case 'staff':
                [$w, $p] = self::buildWhere('st.concern_id', 'st.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT st.staff_code, st.name, c.name AS concern, st.designation, st.department, st.phone, st.salary_type, st.base_salary, st.status
                     FROM staff st LEFT JOIN concerns c ON st.concern_id = c.id $w ORDER BY st.name ASC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Staff Code', 'Name', 'Concern', 'Designation', 'Department', 'Phone', 'Salary Type', 'Base Salary', 'Status'], 'rows' => $rows];
        }

        return ['title' => $def['title'], 'headers' => [], 'rows' => []];
    }

    /**
     * Stream a consolidated multi-module UTF-8 BOM CSV or Excel (.xls) download.
     */
    public static function streamExport(array $moduleKeys, string $startDate, string $endDate, array $concernIds, array $unitIds, string $format = 'csv'): void {
        $filenameBase = 'Data_Export_Center_' . $startDate . '_to_' . $endDate;
        $datasets = [];
        foreach ($moduleKeys as $key) {
            $datasets[] = self::fetchModuleDataset($key, $startDate, $endDate, $concernIds, $unitIds);
        }

        if ($format === 'xls') {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filenameBase . '.xls"');
            echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
            echo '<h2>Data Export Center (' . htmlspecialchars($startDate . ' to ' . $endDate) . ')</h2>';
            foreach ($datasets as $ds) {
                echo '<h3>' . htmlspecialchars($ds['title']) . ' (' . count($ds['rows']) . ' records)</h3>';
                if (empty($ds['rows'])) {
                    echo '<p>No records found for selected filters.</p>';
                    continue;
                }
                echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
                foreach ($ds['headers'] as $h) {
                    echo '<th style="background:#f1f5f9;">' . htmlspecialchars((string)$h) . '</th>';
                }
                echo '</tr></thead><tbody>';
                foreach ($ds['rows'] as $row) {
                    echo '<tr>';
                    foreach ($row as $cell) {
                        echo '<td>' . htmlspecialchars((string)($cell ?? '')) . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table><br>';
            }
            echo '</body></html>';
            exit;
        }

        // Default: UTF-8 BOM CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Data Export Center', 'Period: ' . $startDate . ' to ' . $endDate, 'Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($out, []);

        foreach ($datasets as $ds) {
            fputcsv($out, ['=== ' . strtoupper($ds['title']) . ' (' . count($ds['rows']) . ' records) ===']);
            if (!empty($ds['headers'])) {
                fputcsv($out, $ds['headers']);
            }
            if (empty($ds['rows'])) {
                fputcsv($out, ['No records found for selected filters.']);
            } else {
                foreach ($ds['rows'] as $row) {
                    fputcsv($out, array_values($row));
                }
            }
            fputcsv($out, []);
        }
        fclose($out);
        exit;
    }
}
