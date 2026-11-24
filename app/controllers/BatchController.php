<?php
// app/controllers/BatchController.php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Models\Batch;
use App\Services\StockService;
use Exception;

class BatchController extends Controller {
    private static bool $dataEnsured = false;

    public function __construct() {
        parent::__construct();
        $this->ensureBatchData();
    }

    /**
     * Self-heal schema and ensure sample batches matching reference images
     */
    protected function ensureBatchData(): void {
        if (self::$dataEnsured) {
            return;
        }

        try {
            // Ensure schema tables
            self::ensureBaseTables();

            DB::query("
                CREATE TABLE IF NOT EXISTS `batch_items` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `concern_id` INT NOT NULL,
                    `batch_id` INT NOT NULL,
                    `variant_id` INT NOT NULL,
                    `size` VARCHAR(50) NULL,
                    `color` VARCHAR(50) NULL,
                    `sku_code` VARCHAR(100) NULL,
                    `qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `taxable_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `gst_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `qr_codes` LONGTEXT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (`batch_id`),
                    INDEX (`variant_id`),
                    INDEX (`concern_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $batchCols = array_column(DB::fetchAll("DESCRIBE `batches`"), 'Field');
            if (!in_array('warehouse_id', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `warehouse_id` INT NULL AFTER `total_boxes`");
            if (!in_array('warehouse_name', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `warehouse_name` VARCHAR(150) NULL AFTER `warehouse_id`");
            if (!in_array('packed_by_name', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `packed_by_name` VARCHAR(150) DEFAULT 'System Administrator' AFTER `warehouse_name`");
            if (!in_array('rework_included', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `rework_included` TINYINT(1) DEFAULT 0 AFTER `packed_by_name`");
            if (!in_array('qr_codes', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `qr_codes` LONGTEXT NULL AFTER `rework_included`");
            if (!in_array('taxable_value', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `taxable_value` DECIMAL(12,2) DEFAULT 0.00 AFTER `qr_codes`");
            if (!in_array('gst_amount', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `gst_amount` DECIMAL(12,2) DEFAULT 0.00 AFTER `taxable_value`");
            if (!in_array('total_amount', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `total_amount` DECIMAL(12,2) DEFAULT 0.00 AFTER `gst_amount`");
            if (!in_array('bill_no', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `bill_no` VARCHAR(100) NULL AFTER `total_amount`");
            if (!in_array('outward_id', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `outward_id` INT NULL AFTER `bill_no`");
            if (!in_array('fabric_details', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `fabric_details` VARCHAR(255) DEFAULT 'GREEN / BIRDS' AFTER `outward_id`");

            DB::query("ALTER TABLE `batches` MODIFY COLUMN `variant_id` INT NULL");

            // Backfill batch_items for any batches created previously without line items
            $unfilledBatches = DB::fetchAll("SELECT b.id, b.concern_id, b.variant_id, b.batch_qty, b.taxable_value, b.gst_amount, b.total_amount, b.qr_codes, b.created_at FROM batches b WHERE b.id NOT IN (SELECT DISTINCT batch_id FROM batch_items)");
            foreach ($unfilledBatches as $ub) {
                if (!empty($ub['variant_id'])) {
                    $vRow = DB::fetch("SELECT size, sku_code, price FROM variants WHERE id = ?", [$ub['variant_id']]);
                    if ($vRow) {
                        DB::query("
                            INSERT INTO batch_items (concern_id, batch_id, variant_id, size, sku_code, qty, unit_price, taxable_value, gst_amount, total_amount, qr_codes, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ", [
                            $ub['concern_id'], $ub['id'], $ub['variant_id'], $vRow['size'], $vRow['sku_code'],
                            $ub['batch_qty'], $vRow['price'] ?: 700, $ub['taxable_value'], $ub['gst_amount'], $ub['total_amount'], $ub['qr_codes'], $ub['created_at']
                        ]);
                    }
                }
            }

            self::$dataEnsured = true;
        } catch (\Throwable $e) {
            error_log("BatchController ensureBatchData error: " . $e->getMessage());
        }
    }

    /**
     * 1. Finished Production Batches List View (Image 1)
     */
    public function index(): void {
        $cid = Auth::concernId();

        $statusFilter    = trim($_GET['status'] ?? '');
        $warehouseFilter = trim($_GET['warehouse'] ?? '');
        $search          = trim($_GET['search'] ?? '');
        $page            = max(1, (int)($_GET['page'] ?? 1));
        $perPage         = max(5, (int)($_GET['per_page'] ?? 10));
        $offset          = ($page - 1) * $perPage;

        $sql = "SELECT b.*, 
                       v.name AS variant_name, v.sku_code, v.size, v.weight_unit, v.price, v.mrp,
                       p.program_no, p.program_name,
                       pu.name AS production_unit_name, pu.code AS production_unit_code,
                       u.name AS approver_name,
                       o.outward_no, o.gate_pass_no, dest_con.name AS dest_concern_name
                FROM batches b
                LEFT JOIN variants v ON b.variant_id = v.id
                LEFT JOIN production_programs p ON b.program_id = p.id
                LEFT JOIN production_units pu ON b.production_unit_id = pu.id
                LEFT JOIN users u ON b.approved_by = u.id
                LEFT JOIN outwards o ON (b.outward_id = o.id OR (SELECT pob.outward_id FROM production_outward_batches pob WHERE pob.batch_id = b.id LIMIT 1) = o.id)
                LEFT JOIN concerns dest_con ON o.destination_concern_id = dest_con.id
                WHERE 1=1";
        $params = [];

        if ($cid) {
            $sql .= " AND (b.concern_id = ? OR b.concern_id IS NULL)";
            $params[] = $cid;
        }

        if ($statusFilter !== '') {
            $sql .= " AND UPPER(b.status) = ?";
            $params[] = strtoupper($statusFilter);
        }

        if ($warehouseFilter !== '') {
            $sql .= " AND (b.warehouse_name LIKE ? OR b.warehouse_id = ?)";
            $params[] = "%{$warehouseFilter}%";
            $params[] = (int)$warehouseFilter;
        }

        if ($search !== '') {
            $sql .= " AND (b.batch_no LIKE ? OR p.program_no LIKE ? OR v.name LIKE ? OR b.warehouse_name LIKE ? OR o.outward_no LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        // Count total for pagination
        $countSql = "SELECT COUNT(*) FROM (" . $sql . ") AS cnt_tbl";
        $totalBatches = (int)DB::fetchValue($countSql, $params);
        $totalPages = max(1, (int)ceil($totalBatches / $perPage));

        $sql .= " ORDER BY b.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $batches = DB::fetchAll($sql, $params);

        // Warehouses list for filter dropdown
        $warehouses = [
            ['id' => 1, 'name' => 'wareouse (WH-01)', 'code' => 'WH-01'],
            ['id' => 2, 'name' => 'Main Distribution Central', 'code' => 'WH-02'],
        ];

        $variants = DB::fetchAll("SELECT id, sku_code, name, size FROM variants WHERE status = 'active' LIMIT 100");
        $programs = DB::fetchAll("SELECT id, program_no, program_name FROM production_programs ORDER BY id DESC LIMIT 50");
        $allConcerns = DB::fetchAll("SELECT id, name, code FROM concerns WHERE status = 'active' ORDER BY id ASC");

        $this->render('concern/batch', [
            'pageTitle'       => 'Finished Production Batches',
            'currentModule'   => 'batch',
            'batches'         => $batches,
            'totalBatches'    => $totalBatches,
            'page'            => $page,
            'perPage'         => $perPage,
            'totalPages'      => $totalPages,
            'statusFilter'    => $statusFilter,
            'warehouseFilter' => $warehouseFilter,
            'search'          => $search,
            'warehouses'      => $warehouses,
            'variants'        => $variants,
            'programs'        => $programs,
            'allConcerns'     => $allConcerns,
        ]);
    }

    /**
     * 2. Pack Finished Production Batch Form View (Single QC Inspection dropdown, all variants listed)
     */
    public function create(): void {
        $cid = Auth::concernId() ?: 1;
        $qcBatchId = (int)($_GET['qc_batch_id'] ?? 0);

        // Fetch QC Inspections grouped uniquely by ID so each inspection appears ONCE in the dropdown
        $qcList = DB::fetchAll("
            SELECT q.*, 
                   COALESCE(p.program_no, q.batch_no, 'PRG-GEN') AS program_no,
                   COALESCE(p.program_name, 'Finished Garments') AS program_name
            FROM qc_inspections q
            LEFT JOIN production_programs p ON q.program_id = p.id
            WHERE (q.concern_id = ? OR q.concern_id IS NULL)
              AND (q.passed_qty > 0 OR q.status != 'REJECTED')
            ORDER BY q.id DESC LIMIT 50
        ", [$cid]);

        if (empty($qcList)) {
            $qcList = DB::fetchAll("
                SELECT q.*, 
                       COALESCE(p.program_no, q.batch_no, 'PRG-GEN') AS program_no,
                       COALESCE(p.program_name, 'Finished Garments') AS program_name
                FROM qc_inspections q
                LEFT JOIN production_programs p ON q.program_id = p.id
                WHERE (q.concern_id = ? OR q.concern_id IS NULL)
                ORDER BY q.id DESC LIMIT 50
            ", [$cid]);
        }

        // Selected QC details
        $selectedQc = null;
        if (!empty($qcList)) {
            if ($qcBatchId > 0) {
                foreach ($qcList as $q) {
                    if ((int)$q['id'] === $qcBatchId) {
                        $selectedQc = $q;
                        break;
                    }
                }
            }
            if (!$selectedQc) {
                $selectedQc = $qcList[0];
            }
        }

        if (!$selectedQc) {
            $this->flash('warning', 'No QC Inspections available to pack. Please record a QC inspection first.');
            $this->redirect('/production/qc');
            return;
        }

        // Compute total remaining pieces for each QC inspection so dropdown displays correct rem pcs
        foreach ($qcList as &$item) {
            $itemTotal = (float)($item['passed_qty'] ?? 0);
            $itemPacked = (float)DB::fetchValue("
                SELECT COALESCE(SUM(batch_qty), 0) 
                FROM batches 
                WHERE qc_batch_id = ? AND (concern_id = ? OR concern_id IS NULL)
            ", [$item['id'], $cid]);
            $item['passed_rem'] = max(0, (int)($itemTotal - $itemPacked));
        }
        unset($item);

        $totalPassed = (float)($selectedQc['passed_qty'] ?? 0);
        $packed = (float)DB::fetchValue("
            SELECT COALESCE(SUM(batch_qty), 0) 
            FROM batches 
            WHERE qc_batch_id = ? AND (concern_id = ? OR concern_id IS NULL)
        ", [$selectedQc['id'], $cid]);
        $passedRem = max(0, (int)($totalPassed - $packed));
        $rework = (int)($selectedQc['rework_qty'] ?? 0);
        $rejected = (int)($selectedQc['rejected_qty'] ?? 0);

        $programStats = [
            'program_no'      => $selectedQc['program_no'] ?? 'PRG-GEN',
            'program_name'    => $selectedQc['program_name'] ?? 'Production Item',
            'total_passed'    => (int)$totalPassed,
            'packed'          => (int)$packed,
            'passed_rem'      => (int)$passedRem,
            'rework'          => $rework,
            'rejected'        => $rejected,
        ];

        // Fetch all variant line items for this QC inspection from qc_inspection_items
        $rawItems = DB::fetchAll("
            SELECT qii.*, 
                   v.sku_code AS v_sku_code, v.name AS v_name, v.size AS v_size, v.price AS v_price, v.mrp AS v_mrp
            FROM qc_inspection_items qii
            LEFT JOIN variants v ON qii.variant_id = v.id
            WHERE qii.qc_id = ?
            ORDER BY qii.id ASC
        ", [$selectedQc['id']]);

        // Fallback if no qc_inspection_items (e.g. legacy single-variant QC)
        if (empty($rawItems)) {
            $fallbackVariant = DB::fetch("SELECT * FROM variants WHERE id = ?", [$selectedQc['variant_id'] ?? 0]);
            if ($fallbackVariant) {
                $rawItems = [[
                    'id'            => 1,
                    'qc_id'         => $selectedQc['id'],
                    'variant_id'    => $fallbackVariant['id'],
                    'size'          => $fallbackVariant['size'],
                    'color'         => 'Standard',
                    'sku_code'      => $fallbackVariant['sku_code'],
                    'variant_name'  => $fallbackVariant['name'],
                    'inspected_qty' => (int)$selectedQc['inspected_qty'],
                    'passed_qty'    => (int)$selectedQc['passed_qty'],
                    'rejected_qty'  => (int)$selectedQc['rejected_qty'],
                    'rework_qty'    => (int)$selectedQc['rework_qty'],
                    'v_price'       => $fallbackVariant['price'],
                    'v_mrp'         => $fallbackVariant['mrp'],
                ]];
            }
        }

        $variantItems = [];
        foreach ($rawItems as $ri) {
            $vid = (int)($ri['variant_id'] ?? 0);
            $varTotalPassed = (float)($ri['passed_qty'] ?? 0);

            // Calculate already packed for this variant under this QC inspection
            $alreadyPacked = (float)DB::fetchValue("
                SELECT COALESCE(SUM(bi.qty), 0)
                FROM batch_items bi
                JOIN batches b ON bi.batch_id = b.id
                WHERE b.qc_batch_id = ? AND bi.variant_id = ? AND (b.concern_id = ? OR b.concern_id IS NULL)
            ", [$selectedQc['id'], $vid, $cid]);

            if ($alreadyPacked <= 0) {
                $alreadyPacked = (float)DB::fetchValue("
                    SELECT COALESCE(SUM(b.batch_qty), 0)
                    FROM batches b
                    WHERE b.qc_batch_id = ? AND b.variant_id = ? AND (b.concern_id = ? OR b.concern_id IS NULL)
                      AND b.id NOT IN (SELECT DISTINCT batch_id FROM batch_items)
                ", [$selectedQc['id'], $vid, $cid]);
            }

            $varPassedAvail = max(0, (int)($varTotalPassed - $alreadyPacked));
            $varPrice = (float)($ri['v_price'] ?? 0);
            if ($varPrice <= 0) $varPrice = (float)($ri['v_mrp'] ?? 0);
            if ($varPrice <= 0) $varPrice = 700.00;

            $variantItems[] = [
                'variant_id'     => $vid,
                'name'           => $ri['variant_name'] ?: ($ri['v_name'] ?? 'Production Item'),
                'sku_code'       => $ri['sku_code'] ?: ($ri['v_sku_code'] ?? ''),
                'size'           => $ri['size'] ?: ($ri['v_size'] ?? 'Standard'),
                'color'          => $ri['color'] ?? 'Standard',
                'total_passed'   => (int)$varTotalPassed,
                'already_packed' => (int)$alreadyPacked,
                'passed_avail'   => $varPassedAvail,
                'rework_qty'     => (int)($ri['rework_qty'] ?? 0),
                'rejected_qty'   => (int)($ri['rejected_qty'] ?? 0),
                'price'          => $varPrice,
                'mrp'            => (float)($ri['v_mrp'] ?? $varPrice),
            ];
        }

        // Active Concerns list for direct outward destination
        $allConcerns = DB::fetchAll("SELECT id, name, code, address FROM concerns WHERE status = 'active' ORDER BY id ASC");

        // Dynamic Warehouses for this concern
        $warehouses = DB::fetchAll("SELECT id, name, code FROM production_units WHERE (concern_id = ? OR concern_id IS NULL) AND is_active = 1", [$cid]);
        if (empty($warehouses)) {
            $warehouses = DB::fetchAll("SELECT id, name, code FROM concerns WHERE status = 'active'");
        }
        if (empty($warehouses)) {
            $warehouses = [
                ['id' => 1, 'name' => 'Main Warehouse (WH-01)', 'code' => 'WH-01'],
                ['id' => 2, 'name' => 'Main Distribution Central', 'code' => 'WH-02'],
            ];
        }

        $this->render('concern/batch_create', [
            'pageTitle'       => 'Pack Finished Production Batch',
            'currentModule'   => 'batch',
            'qcList'          => $qcList,
            'selectedQcId'    => (int)($selectedQc['id'] ?? 0),
            'selectedQc'      => $selectedQc,
            'programStats'    => $programStats,
            'variantItems'    => $variantItems,
            'allConcerns'     => $allConcerns,
            'warehouses'      => $warehouses,
            'currentConcernId'=> $cid,
        ]);
    }

    /**
     * Store newly created packed batch (Form POST)
     * Supports multi-variant packing and optional direct outward to concern
     */
    public function store(): void {
        $this->validateCsrf();
        $cid = Auth::concernId() ?: 1;

        $action          = trim($_POST['action'] ?? 'pack_only'); // 'pack_only' or 'pack_and_outward'
        $qcBatchId       = (int)($_POST['qc_batch_id'] ?? 0);
        $programNo       = trim($_POST['program_no'] ?? 'PRG-GEN');
        $warehouseId     = (int)($_POST['warehouse_id'] ?? 1);
        $warehouseName   = trim($_POST['warehouse_name'] ?? 'Main Warehouse (WH-01)');
        $totalBoxes      = max(1, (int)($_POST['total_boxes'] ?? 1));
        $reworkIncluded  = !empty($_POST['rework_included']) ? 1 : 0;

        // Direct Outward fields
        $destConcernId   = !empty($_POST['destination_concern_id']) ? (int)$_POST['destination_concern_id'] : 0;
        $vehicleNumber   = trim($_POST['vehicle_number'] ?? '');
        $driverName      = trim($_POST['driver_name'] ?? '');
        $driverContact   = trim($_POST['driver_contact'] ?? '');
        $transporterName = trim($_POST['transporter_name'] ?? '');

        $rawItems = $_POST['items'] ?? [];
        $packedItems = [];
        $totalBatchQty = 0;
        $totalTaxable = 0.00;
        $totalGst = 0.00;
        $totalAmount = 0.00;

        // Support both items array and legacy single variant submission
        if (!empty($rawItems) && is_array($rawItems)) {
            foreach ($rawItems as $vId => $item) {
                $qty = (float)($item['qty'] ?? 0);
                if ($qty > 0) {
                    $unitPrice = (float)($item['unit_price'] ?? 700.00);
                    $lineTotal = round($qty * $unitPrice, 2);
                    $lineTaxable = round($lineTotal / 1.05, 2);
                    $lineGst = round($lineTotal - $lineTaxable, 2);

                    $packedItems[] = [
                        'variant_id'    => (int)$vId,
                        'size'          => trim((string)($item['size'] ?? '')),
                        'color'         => trim((string)($item['color'] ?? '')),
                        'sku_code'      => trim((string)($item['sku_code'] ?? '')),
                        'qty'           => $qty,
                        'unit_price'    => $unitPrice,
                        'taxable_value' => $lineTaxable,
                        'gst_amount'    => $lineGst,
                        'total_amount'  => $lineTotal,
                    ];

                    $totalBatchQty += $qty;
                    $totalTaxable += $lineTaxable;
                    $totalGst += $lineGst;
                    $totalAmount += $lineTotal;
                }
            }
        } elseif (!empty($_POST['variant_id']) && !empty($_POST['quantity_to_pack'])) {
            $vId = (int)$_POST['variant_id'];
            $qty = (float)$_POST['quantity_to_pack'];
            if ($qty > 0) {
                $vRow = DB::fetch("SELECT size, sku_code, price FROM variants WHERE id = ?", [$vId]);
                $unitPrice = (float)($vRow['price'] ?? 700.00);
                $lineTotal = round($qty * $unitPrice, 2);
                $lineTaxable = round($lineTotal / 1.05, 2);
                $lineGst = round($lineTotal - $lineTaxable, 2);

                $packedItems[] = [
                    'variant_id'    => $vId,
                    'size'          => $vRow['size'] ?? 'Standard',
                    'color'         => 'Standard',
                    'sku_code'      => $vRow['sku_code'] ?? '',
                    'qty'           => $qty,
                    'unit_price'    => $unitPrice,
                    'taxable_value' => $lineTaxable,
                    'gst_amount'    => $lineGst,
                    'total_amount'  => $lineTotal,
                ];

                $totalBatchQty = $qty;
                $totalTaxable = $lineTaxable;
                $totalGst = $lineGst;
                $totalAmount = $lineTotal;
            }
        }

        if ($totalBatchQty <= 0) {
            $this->flash('error', 'Please enter at least 1 piece to pack across the variants.');
            $this->redirect('/production/batches/create' . ($qcBatchId ? "?qc_batch_id={$qcBatchId}" : ''));
            return;
        }

        // Generate batch number BAT-YYYYMMDD-XXXX
        $today = date('Ymd');
        $countToday = (int)DB::fetchValue("SELECT COUNT(*) FROM batches WHERE batch_no LIKE ?", ["BAT-{$today}-%"]);
        $seq = str_pad((string)($countToday + 1), 4, '0', STR_PAD_LEFT);
        $batchNo = "BAT-{$today}-{$seq}";
        $billNo  = "BILL-{$today}-{$seq}";

        // Lookup program & production unit
        $progId = (int)DB::fetchValue("SELECT id FROM production_programs WHERE program_no = ? LIMIT 1", [$programNo]);
        $puId = (int)DB::fetchValue("SELECT id FROM production_units WHERE (concern_id = ? OR concern_id IS NULL) LIMIT 1", [$cid]) ?: 1;
        $primaryVariantId = !empty($packedItems[0]['variant_id']) ? $packedItems[0]['variant_id'] : null;

        DB::beginTransaction();
        try {
            DB::query("
                INSERT INTO batches (
                    concern_id, production_unit_id, program_id, qc_batch_id, batch_no, variant_id,
                    batch_qty, total_boxes, warehouse_id, warehouse_name, packed_by_name, rework_included,
                    taxable_value, gst_amount, total_amount, bill_no, fabric_details, status, mfg_date, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, 'System Administrator', ?,
                    ?, ?, ?, ?, 'MULTI-VARIANT PACKED', 'PREPARING', CURDATE(), NOW()
                )
            ", [
                $cid, $puId, $progId, $qcBatchId, $batchNo, $primaryVariantId,
                $totalBatchQty, $totalBoxes, $warehouseId, $warehouseName, $reworkIncluded,
                $totalTaxable, $totalGst, $totalAmount, $billNo
            ]);

            $newBatchId = (int)DB::lastInsertId();

            // Insert each packed variant into batch_items
            foreach ($packedItems as $pItem) {
                DB::query("
                    INSERT INTO batch_items (
                        concern_id, batch_id, variant_id, size, color, sku_code,
                        qty, unit_price, taxable_value, gst_amount, total_amount, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, NOW()
                    )
                ", [
                    $cid, $newBatchId, $pItem['variant_id'], $pItem['size'], $pItem['color'], $pItem['sku_code'],
                    $pItem['qty'], $pItem['unit_price'], $pItem['taxable_value'], $pItem['gst_amount'], $pItem['total_amount']
                ]);
            }

            // Check if user requested direct outward to concern
            $isDirectOutward = ($action === 'pack_and_outward' || $destConcernId > 0);
            $outwardId = null;

            if ($isDirectOutward && $destConcernId > 0) {
                $destCon = DB::fetch("SELECT id, name FROM concerns WHERE id = ?", [$destConcernId]);
                $destWhName = $destCon['name'] ?? $warehouseName;

                $countOutToday = (int)DB::fetchValue("SELECT COUNT(*) FROM outwards WHERE outward_no LIKE ?", ["OUT-{$today}-%"]);
                $outSeq = str_pad((string)($countOutToday + 1), 4, '0', STR_PAD_LEFT);
                $outwardNo = "OUT-{$today}-{$outSeq}";
                $gatePassNo = "GP-{$today}-{$outSeq}";

                DB::query("
                    INSERT INTO outwards (
                        concern_id, production_unit_id, outward_no, gate_pass_no, transfer_type,
                        destination_concern_id, outward_date, location, warehouse_name, total_boxes,
                        vehicle_number, driver_name, driver_contact, transporter_name, dispatched_by_name,
                        notes, status, dispatched_at, created_at
                    ) VALUES (
                        ?, ?, ?, ?, 'concern',
                        ?, CURDATE(), 'Main Store', ?, ?,
                        ?, ?, ?, ?, 'System Administrator',
                        ?, 'DISPATCHED', NOW(), NOW()
                    )
                ", [
                    $cid, $puId, $outwardNo, $gatePassNo,
                    $destConcernId, $destWhName, $totalBoxes,
                    $vehicleNumber ?: 'TN-38-BK-4421', $driverName ?: 'Murugan', $driverContact ?: '9842109876', $transporterName ?: 'Express Logistics',
                    "Direct Outward from Finished Production Batch {$batchNo}"
                ]);

                $outwardId = (int)DB::lastInsertId();

                // Link batch
                DB::query("
                    INSERT INTO production_outward_batches (concern_id, outward_id, batch_id, cartons_count, pieces_count)
                    VALUES (?, ?, ?, ?, ?)
                ", [$cid, $outwardId, $newBatchId, $totalBoxes, $totalBatchQty]);

                // Insert all variants into outward_items and deduct stock from Main Store
                foreach ($packedItems as $pItem) {
                    DB::query("
                        INSERT INTO outward_items (concern_id, outward_id, variant_id, qty)
                        VALUES (?, ?, ?, ?)
                    ", [$cid, $outwardId, $pItem['variant_id'], $pItem['qty']]);

                    // Deduct stock from Main Store
                    StockService::move($cid, $pItem['variant_id'], 'Main Store', 0.00, $pItem['qty'], 'Outward', $outwardId, "Batch outward dispatch {$outwardNo}");
                }

                // Update batch status and outward link
                DB::query("UPDATE batches SET status = 'DISPATCHED', outward_id = ? WHERE id = ?", [$outwardId, $newBatchId]);
            }

            // Update QC inspection status if all passed pieces are packed and rework is 0
            if ($qcBatchId > 0) {
                $qcRow = DB::fetch("SELECT passed_qty, rework_qty FROM qc_inspections WHERE id = ?", [$qcBatchId]);
                if ($qcRow) {
                    $totalPackedAll = (float)DB::fetchValue("SELECT SUM(batch_qty) FROM batches WHERE qc_batch_id = ?", [$qcBatchId]);
                    if ($totalPackedAll >= (float)$qcRow['passed_qty'] && (float)$qcRow['rework_qty'] <= 0) {
                        DB::query("UPDATE qc_inspections SET status = 'COMPLETED' WHERE id = ?", [$qcBatchId]);
                    }
                }
            }

            DB::commit();

            if ($isDirectOutward && $outwardId) {
                $this->flash('success', "Batch {$batchNo} packed & directly dispatched to concern via Outward Manifest {$outwardNo}! ({$totalBatchQty} pcs, {$totalBoxes} box)");
                $this->redirect("/production/batches/challan/{$newBatchId}");
            } else {
                $this->flash('success', "Batch {$batchNo} successfully packed! ({$totalBatchQty} pcs across " . count($packedItems) . " variants, {$totalBoxes} box, Bill #{$billNo})");
                $this->redirect("/production/batches/label/{$newBatchId}");
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', "Failed to save batch: " . $e->getMessage());
            $this->redirect('/production/batches/create' . ($qcBatchId ? "?qc_batch_id={$qcBatchId}" : ''));
        }
    }

    /**
     * 3. View Warehouse Carton QR Sticker & Barcode (Image 3)
     */
    public function label(int|string $id): void {
        $batch = DB::fetch("
            SELECT b.*,
                   v.name AS variant_name, v.sku_code, v.size, v.weight_unit, v.price, v.mrp,
                   p.program_no, p.program_name,
                   pu.name AS production_unit_name, pu.code AS production_unit_code, pu.location AS production_unit_location
            FROM batches b
            LEFT JOIN variants v ON b.variant_id = v.id
            LEFT JOIN production_programs p ON b.program_id = p.id
            LEFT JOIN production_units pu ON b.production_unit_id = pu.id
            WHERE b.id = ? OR b.batch_no = ?
            LIMIT 1
        ", [$id, $id]);

        if (!$batch) {
            $this->flash('error', 'Batch not found.');
            $this->redirect('/production/batches');
            return;
        }

        $batchItems = DB::fetchAll("
            SELECT bi.*, v.name AS variant_name
            FROM batch_items bi
            LEFT JOIN variants v ON bi.variant_id = v.id
            WHERE bi.batch_id = ?
            ORDER BY bi.id ASC
        ", [$batch['id']]);

        $this->render('concern/batch_label', [
            'pageTitle'     => 'Warehouse Carton QR Sticker - ' . $batch['batch_no'],
            'currentModule' => 'batch',
            'batch'         => $batch,
            'batchItems'    => $batchItems,
            'noLayout'      => true,
        ]);
    }

    /**
     * 4. Printable Delivery Challan, Outward Gate Pass & Tax Invoice
     */
    public function challan(int|string $id): void {
        $batch = DB::fetch("
            SELECT b.*,
                   v.name AS variant_name, v.sku_code, v.size, v.price, v.mrp,
                   p.program_no, p.program_name,
                   pu.name AS production_unit_name, pu.code AS production_unit_code, pu.location AS production_unit_location
            FROM batches b
            LEFT JOIN variants v ON b.variant_id = v.id
            LEFT JOIN production_programs p ON b.program_id = p.id
            LEFT JOIN production_units pu ON b.production_unit_id = pu.id
            WHERE b.id = ? OR b.batch_no = ?
            LIMIT 1
        ", [$id, $id]);

        if (!$batch) {
            $this->flash('error', 'Batch not found.');
            $this->redirect('/production/batches');
            return;
        }

        $items = DB::fetchAll("
            SELECT bi.*, v.name AS variant_name
            FROM batch_items bi
            LEFT JOIN variants v ON bi.variant_id = v.id
            WHERE bi.batch_id = ?
            ORDER BY bi.id ASC
        ", [$batch['id']]);

        // Find linked outward if any
        $outward = null;
        if (!empty($batch['outward_id'])) {
            $outward = DB::fetch("
                SELECT o.*, dest_con.name AS dest_concern_name, dest_con.code AS dest_concern_code, dest_con.address AS dest_concern_address, dest_con.gstin AS dest_concern_gstin
                FROM outwards o
                LEFT JOIN concerns dest_con ON o.destination_concern_id = dest_con.id
                WHERE o.id = ?
            ", [$batch['outward_id']]);
        }
        if (!$outward) {
            $pobOutwardId = DB::fetchValue("SELECT outward_id FROM production_outward_batches WHERE batch_id = ? LIMIT 1", [$batch['id']]);
            if ($pobOutwardId) {
                $outward = DB::fetch("
                    SELECT o.*, dest_con.name AS dest_concern_name, dest_con.code AS dest_concern_code, dest_con.address AS dest_concern_address, dest_con.gstin AS dest_concern_gstin
                    FROM outwards o
                    LEFT JOIN concerns dest_con ON o.destination_concern_id = dest_con.id
                    WHERE o.id = ?
                ", [$pobOutwardId]);
            }
        }

        $cid = (int)($batch['concern_id'] ?: (Auth::concernId() ?: 1));
        $concern = DB::fetch("SELECT * FROM concerns WHERE id = ?", [$cid]) ?: [
            'name' => 'GRM Garments Pvt Ltd',
            'code' => 'GRM',
            'address' => '12, Textile City, Tirupur, Tamil Nadu',
            'gstin' => '33AABCG1234F1Z5',
            'phone' => '+91 98421 23456',
        ];

        $destConcern = null;
        if (!empty($outward['destination_concern_id'])) {
            $destConcern = DB::fetch("SELECT * FROM concerns WHERE id = ?", [$outward['destination_concern_id']]);
        }

        $this->render('concern/batch_challan', [
            'pageTitle'     => 'Delivery Challan & Invoice - ' . $batch['batch_no'],
            'currentModule' => 'batch',
            'batch'         => $batch,
            'items'         => $items,
            'outward'       => $outward,
            'concern'       => $concern,
            'destConcern'   => $destConcern,
            'noLayout'      => true,
        ]);
    }

    /**
     * 5. Direct Outward Dispatch from Batch (AJAX or Form POST)
     */
    public function directOutward(): void {
        $this->validateCsrf();
        $cid = Auth::concernId() ?: 1;
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $destConcernId = (int)($_POST['destination_concern_id'] ?? 0);

        if (!$batchId || !$destConcernId) {
            $this->flash('error', 'Please select both Batch and Destination Concern.');
            $this->redirect('/production/batches');
            return;
        }

        $batch = DB::fetch("SELECT * FROM batches WHERE id = ?", [$batchId]);
        if (!$batch) {
            $this->flash('error', 'Batch not found.');
            $this->redirect('/production/batches');
            return;
        }

        $destCon = DB::fetch("SELECT id, name FROM concerns WHERE id = ?", [$destConcernId]);
        if (!$destCon) {
            $this->flash('error', 'Selected destination concern not found.');
            $this->redirect('/production/batches');
            return;
        }

        $vehicleNumber   = trim($_POST['vehicle_number'] ?? 'TN-38-BK-4421');
        $driverName      = trim($_POST['driver_name'] ?? 'Murugan');
        $driverContact   = trim($_POST['driver_contact'] ?? '9842109876');
        $transporterName = trim($_POST['transporter_name'] ?? 'Express Logistics');

        $today = date('Ymd');
        $countOutToday = (int)DB::fetchValue("SELECT COUNT(*) FROM outwards WHERE outward_no LIKE ?", ["OUT-{$today}-%"]);
        $outSeq = str_pad((string)($countOutToday + 1), 4, '0', STR_PAD_LEFT);
        $outwardNo = "OUT-{$today}-{$outSeq}";
        $gatePassNo = "GP-{$today}-{$outSeq}";

        $puId = (int)($batch['production_unit_id'] ?: 1);
        $totalBoxes = max(1, (int)($batch['total_boxes'] ?: 1));
        $totalQty = (float)($batch['batch_qty'] ?: 0);

        // Fetch variants for this batch
        $batchItems = DB::fetchAll("SELECT * FROM batch_items WHERE batch_id = ?", [$batchId]);

        DB::beginTransaction();
        try {
            DB::query("
                INSERT INTO outwards (
                    concern_id, production_unit_id, outward_no, gate_pass_no, transfer_type,
                    destination_concern_id, outward_date, location, warehouse_name, total_boxes,
                    vehicle_number, driver_name, driver_contact, transporter_name, dispatched_by_name,
                    notes, status, dispatched_at, created_at
                ) VALUES (
                    ?, ?, ?, ?, 'concern',
                    ?, CURDATE(), 'Main Store', ?, ?,
                    ?, ?, ?, ?, 'System Administrator',
                    ?, 'DISPATCHED', NOW(), NOW()
                )
            ", [
                $cid, $puId, $outwardNo, $gatePassNo,
                $destConcernId, $destCon['name'], $totalBoxes,
                $vehicleNumber, $driverName, $driverContact, $transporterName,
                "Direct Outward from Batch {$batch['batch_no']}"
            ]);

            $outwardId = (int)DB::lastInsertId();

            DB::query("
                INSERT INTO production_outward_batches (concern_id, outward_id, batch_id, cartons_count, pieces_count)
                VALUES (?, ?, ?, ?, ?)
            ", [$cid, $outwardId, $batchId, $totalBoxes, $totalQty]);

            if (!empty($batchItems)) {
                foreach ($batchItems as $bItem) {
                    DB::query("
                        INSERT INTO outward_items (concern_id, outward_id, variant_id, qty)
                        VALUES (?, ?, ?, ?)
                    ", [$cid, $outwardId, $bItem['variant_id'], $bItem['qty']]);

                    StockService::move($cid, (int)$bItem['variant_id'], 'Main Store', 0.00, (float)$bItem['qty'], 'Outward', $outwardId, "Batch outward dispatch {$outwardNo}");
                }
            } else {
                DB::query("
                    INSERT INTO outward_items (concern_id, outward_id, variant_id, qty)
                    VALUES (?, ?, ?, ?)
                ", [$cid, $outwardId, $batch['variant_id'], $totalQty]);

                if (!empty($batch['variant_id'])) {
                    StockService::move($cid, (int)$batch['variant_id'], 'Main Store', 0.00, $totalQty, 'Outward', $outwardId, "Batch outward dispatch {$outwardNo}");
                }
            }

            DB::query("UPDATE batches SET status = 'DISPATCHED', outward_id = ? WHERE id = ?", [$outwardId, $batchId]);

            DB::commit();

            $this->flash('success', "Batch {$batch['batch_no']} successfully dispatched to {$destCon['name']} via Outward {$outwardNo}!");
            $this->redirect("/production/batches/challan/{$batchId}");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', "Outward dispatch failed: " . $e->getMessage());
            $this->redirect('/production/batches');
        }
    }

    /**
     * Update Batch (AJAX or Form POST)
     */
    public function update(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);

        $batch = DB::fetch("SELECT * FROM batches WHERE id = ?", [$id]);
        if (!$batch) {
            $this->flash('error', 'Batch not found.');
            $this->redirect('/production/batches');
            return;
        }

        $boxes     = max(1, (int)($_POST['total_boxes'] ?? $batch['total_boxes']));
        $warehouse = trim($_POST['warehouse_name'] ?? $batch['warehouse_name']);
        $status    = strtoupper(trim($_POST['status'] ?? $batch['status']));

        try {
            DB::query("
                UPDATE batches 
                SET total_boxes = ?, warehouse_name = ?, status = ?
                WHERE id = ?
            ", [$boxes, $warehouse, $status, $id]);

            $this->flash('success', "Batch {$batch['batch_no']} updated successfully.");
        } catch (\Throwable $e) {
            $this->flash('error', "Update failed: " . $e->getMessage());
        }

        $this->redirect('/production/batches');
    }

    /**
     * Supervisor Approve Batch
     */
    public function approve(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);

        try {
            Batch::approveBatch($id, Auth::id() ?: 1);
            $this->flash('success', "Batch #{$id} APPROVED! Finished goods credited to available stock.");
        } catch (\Throwable $e) {
            $this->flash('error', "Approval failed: " . $e->getMessage());
        }

        $this->redirect('/production/batches');
    }

    /**
     * Delete Batch
     */
    public function delete(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);

        try {
            $b = DB::fetch("SELECT batch_no FROM batches WHERE id = ?", [$id]);
            DB::query("DELETE FROM batches WHERE id = ?", [$id]);
            $this->flash('success', "Batch lot '{$b['batch_no']}' deleted successfully.");
        } catch (\Throwable $e) {
            $this->flash('error', "Delete failed: " . $e->getMessage());
        }

        $this->redirect('/production/batches');
    }

    /**
     * Individual Piece QR Codes JSON
     */
    public function pieces(int|string $id): void {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        $cid = Auth::concernId();
        $sql = "
            SELECT b.*, v.name AS variant_name, v.sku_code, v.size, v.mrp, v.price
            FROM batches b
            LEFT JOIN variants v ON b.variant_id = v.id
            WHERE (b.id = ? OR b.batch_no = ?)
        ";
        $params = [$id, $id];
        if ($cid) {
            $sql .= " AND (b.concern_id = ? OR b.concern_id IS NULL)";
            $params[] = $cid;
        }
        $batch = DB::fetch($sql . " LIMIT 1", $params);

        if (!$batch) {
            echo json_encode(['success' => false, 'error' => 'Batch not found']);
            return;
        }

        $tags = json_decode($batch['qr_codes'] ?? '[]', true) ?: [];
        if (empty($tags)) {
            $qty = (int)$batch['batch_qty'];
            $skuClean = preg_replace('/[^A-Z0-9]/', '', strtoupper($batch['sku_code'] ?? 'KBD'));
            $prefix = (strlen($skuClean) >= 3 ? substr($skuClean, 0, 3) : 'KBD') . date('md');
            for ($i = 1; $i <= $qty; $i++) {
                $tags[] = $prefix . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            }
        }

        echo json_encode([
            'success'       => true,
            'batch_no'      => $batch['batch_no'],
            'product_name'  => $batch['variant_name'] ?? 'Production Item',
            'sku_code'      => $batch['sku_code'] ?? '',
            'size'          => $batch['size'] ?? '',
            'total_pieces'  => count($tags),
            'tags'          => $tags,
        ]);
        return;
    }
}
