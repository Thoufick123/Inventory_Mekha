<?php
// app/services/ExportCenterService.php - Data Export Center Engine (Counts, Filtering & Multi-Module Export)

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

        // Enabled concern module codes for "Select Concern Modules" quick button
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
     * Catalog of all exportable data modules grouped by category.
     */
    public static function getModuleDefinitions(): array {
        return [
            'PROCUREMENT & MATERIALS' => [
                [
                    'key' => 'purchases',
                    'title' => 'Purchases',
                    'description' => 'Procurement bills across fabric rolls, raw materials, third-party goods & tax totals.',
                    'icon' => 'bi-receipt',
                    'module_code' => 'purchase_billing',
                    'table' => 'purchase_bills',
                    'date_col' => 'bill_date',
                    'is_datetime' => false,
                    'unit_col' => null,
                ],
                [
                    'key' => 'fabric_rolls',
                    'title' => 'Fabric Rolls & Inwards',
                    'description' => 'Individual fabric roll allocations, piece tags, colors, weights & target pieces.',
                    'icon' => 'bi-layers',
                    'module_code' => 'catalog_program',
                    'table' => 'program_rolls',
                    'date_col' => 'created_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
                [
                    'key' => 'raw_materials',
                    'title' => 'Raw Materials Supply & Stock',
                    'description' => 'Procured line items, yarn/fabric quantities, unit rates & tax breakdowns.',
                    'icon' => 'bi-box-seam',
                    'module_code' => 'purchase_stocks',
                    'table' => 'purchase_bill_items',
                    'date_col' => 'pb.bill_date',
                    'is_datetime' => false,
                    'unit_col' => null,
                ],
                [
                    'key' => 'third_party',
                    'title' => 'Third-Party Products & Mapping',
                    'description' => 'Catalog variants, product codes, sizes, colors, MRP & selling prices.',
                    'icon' => 'bi-tags',
                    'module_code' => 'product_adding',
                    'table' => 'variants',
                    'date_col' => 'created_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
                [
                    'key' => 'qr_generations',
                    'title' => 'QR Generations',
                    'description' => 'QR & barcode label jobs across Third-Party, Stitching & QC sources.',
                    'icon' => 'bi-qr-code',
                    'module_code' => 'label_generation',
                    'table' => 'label_jobs',
                    'date_col' => 'created_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
                [
                    'key' => 'sellers',
                    'title' => 'Sellers',
                    'description' => 'Registered suppliers, contact details, GSTIN & payment terms.',
                    'icon' => 'bi-shop',
                    'module_code' => 'purchase_billing',
                    'table' => 'suppliers',
                    'date_col' => 'created_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
                [
                    'key' => 'inward',
                    'title' => 'Inward',
                    'description' => 'Warehouse & factory receipts, GRN numbers, vehicle & received status.',
                    'icon' => 'bi-box-arrow-in-down',
                    'module_code' => 'inward',
                    'table' => 'inwards',
                    'date_col' => 'inward_date',
                    'is_datetime' => false,
                    'unit_col' => 'production_unit_id',
                ],
            ],
            'MANUFACTURING' => [
                [
                    'key' => 'programs',
                    'title' => 'Programs',
                    'description' => 'Production programs, target quantities, statuses & timelines.',
                    'icon' => 'bi-diagram-3',
                    'module_code' => 'catalog_program',
                    'table' => 'production_programs',
                    'date_col' => 'created_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
                [
                    'key' => 'cutting',
                    'title' => 'Cutting',
                    'description' => 'Cutting logs, consumed fabric weight, cut pieces, wastage & cost per piece.',
                    'icon' => 'bi-scissors',
                    'module_code' => 'cutting_wastage',
                    'table' => 'cutting_logs',
                    'date_col' => 'created_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
                [
                    'key' => 'cutting_outward',
                    'title' => 'Cutting Outward',
                    'description' => 'Production outward batch transfers, cartons & dispatched piece counts.',
                    'icon' => 'bi-truck',
                    'module_code' => 'outward',
                    'table' => 'production_outward_batches',
                    'date_col' => 'created_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
                [
                    'key' => 'stitching',
                    'title' => 'Stitching',
                    'description' => 'Stitching batches, tailor assignments, stitched pieces & weight variance.',
                    'icon' => 'bi-grid-1x2',
                    'module_code' => 'stitching_wastage',
                    'table' => 'stitching_logs',
                    'date_col' => 'created_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
                [
                    'key' => 'qc_inspections',
                    'title' => 'QC Inspections',
                    'description' => 'Quality checks: inspected, passed, rework & rejected piece breakdowns.',
                    'icon' => 'bi-shield-check',
                    'module_code' => 'qc_stocks',
                    'table' => 'qc_inspections',
                    'date_col' => 'created_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
                [
                    'key' => 'wastage_sales',
                    'title' => 'Wastage & Scrap Sales',
                    'description' => 'Cutting & stitching wastage sales, buyer details & recovered revenue.',
                    'icon' => 'bi-recycle',
                    'module_code' => 'cutting_wastage',
                    'table' => 'cutting_wastage_sales',
                    'date_col' => 'sale_date',
                    'is_datetime' => false,
                    'unit_col' => null,
                ],
            ],
            'INVENTORY, SALES & DISPATCH' => [
                [
                    'key' => 'stocks',
                    'title' => 'Live Inventory Stocks',
                    'description' => 'Current on-hand stock balances by variant, location & valuation.',
                    'icon' => 'bi-boxes',
                    'module_code' => 'stocks',
                    'table' => 'stocks',
                    'date_col' => 'updated_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
                [
                    'key' => 'stock_ledger',
                    'title' => 'Stock Ledger',
                    'description' => 'Append-only stock movement audit trail with inward/outward quantities.',
                    'icon' => 'bi-journal-text',
                    'module_code' => 'stocks',
                    'table' => 'stock_ledger',
                    'date_col' => 'created_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
                [
                    'key' => 'sales_invoices',
                    'title' => 'Sales & POS Billing',
                    'description' => 'B2C & B2B invoices, discounts, GST taxes, payment modes & grand totals.',
                    'icon' => 'bi-cart-check',
                    'module_code' => 'billing',
                    'table' => 'sales_invoices',
                    'date_col' => 'invoice_date',
                    'is_datetime' => false,
                    'unit_col' => null,
                ],
                [
                    'key' => 'outwards',
                    'title' => 'Outward & Dispatch',
                    'description' => 'Inter-concern & customer dispatches, LR numbers, vehicles & box counts.',
                    'icon' => 'bi-send',
                    'module_code' => 'outward',
                    'table' => 'outwards',
                    'date_col' => 'outward_date',
                    'is_datetime' => false,
                    'unit_col' => 'production_unit_id',
                ],
                [
                    'key' => 'returns_exchanges',
                    'title' => 'Returns & Exchanges',
                    'description' => 'Customer returns, exchanges, credit notes & refund amounts.',
                    'icon' => 'bi-arrow-return-left',
                    'module_code' => 'return_exchange',
                    'table' => 'returns_exchanges',
                    'date_col' => 'return_date',
                    'is_datetime' => false,
                    'unit_col' => null,
                ],
                [
                    'key' => 'damages',
                    'title' => 'Concern Damages & Rework',
                    'description' => 'Quarantined damaged pieces, severity, resolution & loss valuation.',
                    'icon' => 'bi-exclamation-triangle',
                    'module_code' => 'concern_damage',
                    'table' => 'concern_damages',
                    'date_col' => 'created_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
            ],
            'FINANCE, SHIFTS & MASTERS' => [
                [
                    'key' => 'expenses',
                    'title' => 'Operating Expenses',
                    'description' => 'Expense entries by category, payment mode & approval notes.',
                    'icon' => 'bi-wallet2',
                    'module_code' => 'expense',
                    'table' => 'expenses',
                    'date_col' => 'expense_date',
                    'is_datetime' => false,
                    'unit_col' => null,
                ],
                [
                    'key' => 'cash_flow',
                    'title' => 'Cash & Bank Ledger',
                    'description' => 'Cash/bank inflows, outflows, linked modules & descriptions.',
                    'icon' => 'bi-cash-coin',
                    'module_code' => 'cash_flow',
                    'table' => 'cash_transactions',
                    'date_col' => 'txn_date',
                    'is_datetime' => false,
                    'unit_col' => null,
                ],
                [
                    'key' => 'cashier_shifts',
                    'title' => 'Cashier Shifts',
                    'description' => 'POS shift openings, cash sales, expected vs counted cash & variance.',
                    'icon' => 'bi-clock-history',
                    'module_code' => 'cash_flow',
                    'table' => 'cashier_shifts',
                    'date_col' => 'opened_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
                [
                    'key' => 'customers',
                    'title' => 'Customers',
                    'description' => 'Retail & B2B customer directory with GSTIN, phone & address.',
                    'icon' => 'bi-people',
                    'module_code' => 'customers',
                    'table' => 'customers',
                    'date_col' => 'created_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
                [
                    'key' => 'staff',
                    'title' => 'Staff Directory',
                    'description' => 'Employee profiles, designations, departments & salary structures.',
                    'icon' => 'bi-person-badge',
                    'module_code' => 'staff',
                    'table' => 'staff',
                    'date_col' => 'created_at',
                    'is_datetime' => true,
                    'unit_col' => null,
                ],
            ],
        ];
    }

    /**
     * Flat map of module key => definition.
     */
    public static function getFlatModuleMap(): array {
        $map = [];
        foreach (self::getModuleDefinitions() as $items) {
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
     * Compute live record counts per module for the active filters.
     */
    public static function getModuleCounts(string $startDate, string $endDate, array $concernIds, array $unitIds = []): array {
        $counts = [];
        foreach (self::getFlatModuleMap() as $key => $def) {
            try {
                if ($key === 'raw_materials') {
                    [$w, $p] = self::buildWhere('pb.concern_id', 'pb.bill_date', false, $startDate, $endDate, $concernIds);
                    $counts[$key] = (int)DB::fetchValue(
                        "SELECT COUNT(*) FROM purchase_bill_items pbi JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id $w",
                        $p
                    );
                } elseif ($key === 'wastage_sales') {
                    [$w1, $p1] = self::buildWhere('concern_id', 'sale_date', false, $startDate, $endDate, $concernIds);
                    $c1 = (int)DB::fetchValue("SELECT COUNT(*) FROM cutting_wastage_sales $w1", $p1);
                    $c2 = (int)DB::fetchValue("SELECT COUNT(*) FROM stitching_wastage_sales $w1", $p1);
                    $counts[$key] = $c1 + $c2;
                } elseif ($key === 'stocks') {
                    // Live inventory snapshot filtered by concern (all active stock lines)
                    $w = '';
                    $p = [];
                    if (!empty($concernIds)) {
                        $ph = implode(',', array_fill(0, count($concernIds), '?'));
                        $w = "WHERE concern_id IN ($ph)";
                        $p = $concernIds;
                    }
                    $counts[$key] = (int)DB::fetchValue("SELECT COUNT(*) FROM stocks $w", $p);
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
     * Fetch dataset headers & rows for a single module key.
     */
    public static function fetchModuleDataset(string $key, string $startDate, string $endDate, array $concernIds, array $unitIds = []): array {
        $map = self::getFlatModuleMap();
        if (!isset($map[$key])) {
            return ['title' => $key, 'headers' => [], 'rows' => []];
        }
        $def = $map[$key];

        switch ($key) {
            case 'purchases':
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

            case 'fabric_rolls':
                [$w, $p] = self::buildWhere('pr.concern_id', 'pr.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT pr.roll_piece_tag, c.name AS concern, pr.fabric_name, pr.color, pr.unit, pr.total_roll_qty, pr.allocated_qty, pr.roll_weight, pr.roll_price, pr.target_pieces, pr.created_at
                     FROM program_rolls pr LEFT JOIN concerns c ON pr.concern_id = c.id $w ORDER BY pr.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Roll Tag', 'Concern', 'Fabric', 'Color', 'Unit', 'Roll Qty', 'Allocated', 'Weight (kg)', 'Price', 'Target Pcs', 'Created At'], 'rows' => $rows];

            case 'raw_materials':
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

            case 'third_party':
                [$w, $p] = self::buildWhere('v.concern_id', 'v.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT v.product_code, v.name, c.name AS concern, v.size, v.weight, v.mrp, v.price, v.created_at
                     FROM variants v LEFT JOIN concerns c ON v.concern_id = c.id $w ORDER BY v.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Product Code', 'Variant Name', 'Concern', 'Size', 'Weight', 'MRP', 'Selling Price', 'Created At'], 'rows' => $rows];

            case 'qr_generations':
                [$w, $p] = self::buildWhere('lj.concern_id', 'lj.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT lj.id, c.name AS concern, lj.label_type, lj.source_module, lj.item_title, lj.item_code, lj.qty, lj.created_at
                     FROM label_jobs lj LEFT JOIN concerns c ON lj.concern_id = c.id $w ORDER BY lj.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Job ID', 'Concern', 'Label Type', 'Source', 'Item Title', 'Item Code', 'Qty', 'Created At'], 'rows' => $rows];

            case 'sellers':
                [$w, $p] = self::buildWhere('s.concern_id', 's.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT s.name, c.name AS concern, s.phone, s.email, s.gstin, s.address, s.created_at
                     FROM suppliers s LEFT JOIN concerns c ON s.concern_id = c.id $w ORDER BY s.name ASC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Supplier Name', 'Concern', 'Phone', 'Email', 'GSTIN', 'Address', 'Created At'], 'rows' => $rows];

            case 'inward':
                [$w, $p] = self::buildWhere('i.concern_id', 'i.inward_date', false, $startDate, $endDate, $concernIds, 'i.production_unit_id', $unitIds);
                $rows = DB::fetchAll(
                    "SELECT i.inward_no, i.grn_number, i.inward_date, c.name AS concern, i.inward_type, i.location, i.vehicle_number, i.status
                     FROM inwards i LEFT JOIN concerns c ON i.concern_id = c.id $w ORDER BY i.inward_date DESC, i.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Inward No', 'GRN', 'Date', 'Concern', 'Type', 'Location', 'Vehicle', 'Status'], 'rows' => $rows];

            case 'programs':
                [$w, $p] = self::buildWhere('pp.concern_id', 'pp.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT pp.program_no, pp.program_name, c.name AS concern, pp.status, pp.created_at
                     FROM production_programs pp LEFT JOIN concerns c ON pp.concern_id = c.id $w ORDER BY pp.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Program No', 'Program Name', 'Concern', 'Status', 'Created At'], 'rows' => $rows];

            case 'cutting':
                [$w, $p] = self::buildWhere('cl.concern_id', 'cl.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT cl.log_no, cl.batch_no, cl.cutting_name, c.name AS concern, cl.cutting_master_name, cl.total_roll_weight, cl.cut_pieces, cl.wastage_qty, cl.fabric_cost_per_piece, cl.status, cl.created_at
                     FROM cutting_logs cl LEFT JOIN concerns c ON cl.concern_id = c.id $w ORDER BY cl.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Log No', 'Batch No', 'Cutting Name', 'Concern', 'Master', 'Roll Wt (kg)', 'Cut Pcs', 'Wastage', 'Cost/Pc', 'Status', 'Created At'], 'rows' => $rows];

            case 'cutting_outward':
                [$w, $p] = self::buildWhere('pob.concern_id', 'pob.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT pob.id, c.name AS concern, pob.outward_id, pob.batch_id, pob.cartons_count, pob.pieces_count, pob.notes, pob.created_at
                     FROM production_outward_batches pob LEFT JOIN concerns c ON pob.concern_id = c.id $w ORDER BY pob.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['ID', 'Concern', 'Outward ID', 'Batch ID', 'Cartons', 'Pieces', 'Notes', 'Created At'], 'rows' => $rows];

            case 'stitching':
                [$w, $p] = self::buildWhere('sl.concern_id', 'sl.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT sl.log_no, sl.batch_no, sl.stitching_name, c.name AS concern, sl.tailor_name, sl.cut_pieces, sl.stitched_pieces, sl.wastage_pieces, sl.status, sl.created_at
                     FROM stitching_logs sl LEFT JOIN concerns c ON sl.concern_id = c.id $w ORDER BY sl.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Log No', 'Batch No', 'Stitching Name', 'Concern', 'Tailor', 'Cut Pcs', 'Stitched Pcs', 'Wastage Pcs', 'Status', 'Created At'], 'rows' => $rows];

            case 'qc_inspections':
                [$w, $p] = self::buildWhere('qc.concern_id', 'qc.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT qc.qc_no, qc.batch_no, c.name AS concern, qc.inspected_qty, qc.passed_qty, qc.rework_qty, qc.rejected_qty, qc.location, qc.status, qc.created_at
                     FROM qc_inspections qc LEFT JOIN concerns c ON qc.concern_id = c.id $w ORDER BY qc.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['QC No', 'Batch No', 'Concern', 'Inspected', 'Passed', 'Rework', 'Rejected', 'Location', 'Status', 'Created At'], 'rows' => $rows];

            case 'wastage_sales':
                [$w1, $p1] = self::buildWhere('ws.concern_id', 'ws.sale_date', false, $startDate, $endDate, $concernIds);
                $rows1 = DB::fetchAll(
                    "SELECT 'Cutting' AS source, ws.sale_no, ws.sale_date, c.name AS concern, ws.buyer_name, ws.wastage_weight, ws.total_price
                     FROM cutting_wastage_sales ws LEFT JOIN concerns c ON ws.concern_id = c.id $w1",
                    $p1
                );
                $rows2 = DB::fetchAll(
                    "SELECT 'Stitching' AS source, ws.sale_no, ws.sale_date, c.name AS concern, ws.buyer_name, ws.quantity AS wastage_weight, ws.total_price
                     FROM stitching_wastage_sales ws LEFT JOIN concerns c ON ws.concern_id = c.id $w1",
                    $p1
                );
                return ['title' => $def['title'], 'headers' => ['Source', 'Sale No', 'Sale Date', 'Concern', 'Buyer', 'Weight/Qty Sold', 'Total Price'], 'rows' => array_merge($rows1, $rows2)];

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

            case 'stock_ledger':
                [$w, $p] = self::buildWhere('sl.concern_id', 'sl.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT sl.id, c.name AS concern, v.product_code, v.name AS variant_name, sl.location, sl.qty_in, sl.qty_out, sl.ref_module, sl.note, sl.created_at
                     FROM stock_ledger sl
                     LEFT JOIN concerns c ON sl.concern_id = c.id
                     LEFT JOIN variants v ON sl.variant_id = v.id
                     $w ORDER BY sl.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['ID', 'Concern', 'Product Code', 'Variant', 'Location', 'Qty In', 'Qty Out', 'Ref Module', 'Note', 'Created At'], 'rows' => $rows];

            case 'sales_invoices':
                [$w, $p] = self::buildWhere('si.concern_id', 'si.invoice_date', false, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT si.invoice_no, si.invoice_date, c.name AS concern, IF(si.is_direct_bill = 1, 'DIRECT', 'STANDARD') AS invoice_type, si.customer_channel, si.payment_method, si.total_amount, si.discount_amount, si.tax_amount, si.net_amount
                     FROM sales_invoices si LEFT JOIN concerns c ON si.concern_id = c.id $w ORDER BY si.invoice_date DESC, si.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Invoice No', 'Date', 'Concern', 'Type', 'Channel', 'Payment Method', 'Subtotal', 'Discount', 'Tax', 'Net Amount'], 'rows' => $rows];

            case 'outwards':
                [$w, $p] = self::buildWhere('o.concern_id', 'o.outward_date', false, $startDate, $endDate, $concernIds, 'o.production_unit_id', $unitIds);
                $rows = DB::fetchAll(
                    "SELECT o.outward_no, o.outward_date, c.name AS concern, o.transfer_type, o.warehouse_name, o.total_boxes, o.vehicle_number, o.status
                     FROM outwards o LEFT JOIN concerns c ON o.concern_id = c.id $w ORDER BY o.outward_date DESC, o.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Outward No', 'Date', 'Concern', 'Transfer Type', 'Warehouse', 'Boxes', 'Vehicle', 'Status'], 'rows' => $rows];

            case 'returns_exchanges':
                [$w, $p] = self::buildWhere('re.concern_id', 're.return_date', false, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT re.return_no, re.return_date, c.name AS concern, re.type, re.returned_qty, re.exchange_qty, re.reason, re.created_at
                     FROM returns_exchanges re LEFT JOIN concerns c ON re.concern_id = c.id $w ORDER BY re.return_date DESC, re.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Return No', 'Date', 'Concern', 'Type', 'Returned Qty', 'Exchange Qty', 'Reason', 'Created At'], 'rows' => $rows];

            case 'damages':
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

            case 'expenses':
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

            case 'cashier_shifts':
                [$w, $p] = self::buildWhere('cs.concern_id', 'cs.opened_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT cs.shift_no, c.name AS concern, cs.cashier_name, cs.starting_float, cs.expected_closing_float, cs.closing_float, cs.variance, cs.status, cs.opened_at, cs.closed_at
                     FROM cashier_shifts cs LEFT JOIN concerns c ON cs.concern_id = c.id $w ORDER BY cs.id DESC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Shift No', 'Concern', 'Cashier', 'Opening Float', 'Expected Float', 'Closing Float', 'Variance', 'Status', 'Opened At', 'Closed At'], 'rows' => $rows];

            case 'customers':
                [$w, $p] = self::buildWhere('cu.concern_id', 'cu.created_at', true, $startDate, $endDate, $concernIds);
                $rows = DB::fetchAll(
                    "SELECT cu.name, c.name AS concern, cu.phone, cu.email, cu.gstin, cu.address, cu.created_at
                     FROM customers cu LEFT JOIN concerns c ON cu.concern_id = c.id $w ORDER BY cu.name ASC",
                    $p
                );
                return ['title' => $def['title'], 'headers' => ['Customer Name', 'Concern', 'Phone', 'Email', 'GSTIN', 'Address', 'Created At'], 'rows' => $rows];

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
