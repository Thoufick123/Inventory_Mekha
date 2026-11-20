<?php
// app/controllers/InwardController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Services\StockService;
use App\Services\NumberSeries;
use Exception;

class InwardController extends Controller {
    private static bool $schemaEnsured = false;

    protected function ensureSchema(): void {
        if (self::$schemaEnsured) {
            return;
        }

        try {
            // Ensure production_units exists
            DB::query("
                CREATE TABLE IF NOT EXISTS `production_units` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `concern_id` INT NOT NULL DEFAULT 1,
                    `code` VARCHAR(50) UNIQUE NOT NULL,
                    `name` VARCHAR(150) NOT NULL,
                    `location` VARCHAR(255) NULL,
                    `manager_id` INT NULL,
                    `manager_name` VARCHAR(150) NULL,
                    `capacity_per_day` INT DEFAULT 0,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_pu_concern` (`concern_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // Seed default unit if empty
            $unitCount = (int)DB::fetchValue("SELECT COUNT(*) FROM `production_units`");
            if ($unitCount === 0) {
                $concerns = DB::fetchAll("SELECT id, name, code FROM `concerns` WHERE status = 'active'");
                foreach ($concerns as $c) {
                    $uCode = 'PU-' . strtoupper(str_replace(' ', '', $c['code'] ?? '01'));
                    DB::query("INSERT INTO `production_units` (`concern_id`, `code`, `name`, `location`, `capacity_per_day`, `is_active`) VALUES (?, ?, ?, 'Main Facility', 500, 1)", [$c['id'], $uCode, $c['name'] . ' Production Unit 1']);
                }
            }

            // Ensure production_outward_batches exists
            DB::query("
                CREATE TABLE IF NOT EXISTS `production_outward_batches` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `concern_id` INT NOT NULL DEFAULT 1,
                    `outward_id` INT NOT NULL,
                    `batch_id` INT NOT NULL,
                    `cartons_count` INT DEFAULT 0,
                    `pieces_count` DECIMAL(12,2) DEFAULT 0.00,
                    `notes` VARCHAR(255) NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_pob_outward` (`outward_id`),
                    INDEX `idx_pob_batch` (`batch_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $inCols = array_column(DB::fetchAll("DESCRIBE `inwards`"), 'Field');
            if (!in_array('production_unit_id', $inCols)) {
                DB::query("ALTER TABLE `inwards` ADD COLUMN `production_unit_id` INT NULL AFTER `concern_id`");
            }
            if (!in_array('grn_number', $inCols)) {
                DB::query("ALTER TABLE `inwards` ADD COLUMN `grn_number` VARCHAR(50) NULL AFTER `inward_no`");
            }
            if (!in_array('vehicle_number', $inCols)) {
                DB::query("ALTER TABLE `inwards` ADD COLUMN `vehicle_number` VARCHAR(50) NULL AFTER `location`");
            }
            if (!in_array('status', $inCols)) {
                DB::query("ALTER TABLE `inwards` ADD COLUMN `status` VARCHAR(50) DEFAULT 'VERIFIED' AFTER `remarks`");
            }
            if (!in_array('outward_id', $inCols)) {
                DB::query("ALTER TABLE `inwards` ADD COLUMN `outward_id` INT NULL AFTER `purchase_bill_id`");
            }
            if (!in_array('from_concern_id', $inCols)) {
                DB::query("ALTER TABLE `inwards` ADD COLUMN `from_concern_id` INT NULL AFTER `outward_id`");
            }
            if (!in_array('inward_type', $inCols)) {
                DB::query("ALTER TABLE `inwards` ADD COLUMN `inward_type` ENUM('purchase', 'transfer') NOT NULL DEFAULT 'purchase' AFTER `from_concern_id`");
            }

            $inItemCols = array_column(DB::fetchAll("DESCRIBE `inward_items`"), 'Field');
            if (!in_array('accepted_qty', $inItemCols)) {
                DB::query("ALTER TABLE `inward_items` ADD COLUMN `accepted_qty` DECIMAL(12,2) DEFAULT 0.00 AFTER `qty`");
            }
            if (!in_array('rejected_qty', $inItemCols)) {
                DB::query("ALTER TABLE `inward_items` ADD COLUMN `rejected_qty` DECIMAL(12,2) DEFAULT 0.00 AFTER `accepted_qty`");
            }
            if (!in_array('rejection_reason', $inItemCols)) {
                DB::query("ALTER TABLE `inward_items` ADD COLUMN `rejection_reason` VARCHAR(255) NULL AFTER `rejected_qty`");
            }

            $outCols = array_column(DB::fetchAll("DESCRIBE `outwards`"), 'Field');
            if (!in_array('transfer_type', $outCols)) {
                DB::query("ALTER TABLE `outwards` ADD COLUMN `transfer_type` ENUM('customer', 'concern') NOT NULL DEFAULT 'customer' AFTER `customer_id`");
            }
            if (!in_array('destination_concern_id', $outCols)) {
                DB::query("ALTER TABLE `outwards` ADD COLUMN `destination_concern_id` INT NULL AFTER `transfer_type`");
            }
            if (!in_array('gate_pass_no', $outCols)) {
                DB::query("ALTER TABLE `outwards` ADD COLUMN `gate_pass_no` VARCHAR(50) NULL AFTER `lr_number`");
            }
            if (!in_array('vehicle_number', $outCols)) {
                DB::query("ALTER TABLE `outwards` ADD COLUMN `vehicle_number` VARCHAR(100) NULL AFTER `warehouse_id`");
            }
            if (!in_array('driver_name', $outCols)) {
                DB::query("ALTER TABLE `outwards` ADD COLUMN `driver_name` VARCHAR(150) NULL AFTER `vehicle_number`");
            }
            if (!in_array('transporter_name', $outCols)) {
                DB::query("ALTER TABLE `outwards` ADD COLUMN `transporter_name` VARCHAR(150) NULL AFTER `driver_contact`");
            }
            if (!in_array('lr_number', $outCols)) {
                DB::query("ALTER TABLE `outwards` ADD COLUMN `lr_number` VARCHAR(100) NULL AFTER `transporter_name`");
            }
            if (!in_array('dispatched_at', $outCols)) {
                DB::query("ALTER TABLE `outwards` ADD COLUMN `dispatched_at` DATETIME NULL AFTER `status`");
            }
            if (!in_array('received_at', $outCols)) {
                DB::query("ALTER TABLE `outwards` ADD COLUMN `received_at` DATETIME NULL AFTER `dispatched_at`");
            }

            self::$schemaEnsured = true;
        } catch (\Throwable $e) {
            // Ignore if columns exist
        }
    }

    public function index(): void {
        $this->ensureSchema();
        $cid = Auth::concernId();

        $sql = "SELECT i.*, s.name AS supplier_name, pb.bill_no, u.name AS user_name,
                       con.name AS concern_name, con.code AS concern_code,
                       fc.name AS from_concern_name, fc.code AS from_concern_code,
                       pu.name AS production_unit_name, pu.code AS production_unit_code,
                       o.outward_no,
                       (SELECT COUNT(*) FROM inward_items ii WHERE ii.inward_id = i.id) AS items_count,
                       (SELECT COALESCE(SUM(qty), 0) FROM inward_items ii WHERE ii.inward_id = i.id) AS total_qty,
                       (SELECT COALESCE(SUM(accepted_qty), 0) FROM inward_items ii WHERE ii.inward_id = i.id) AS total_accepted_qty,
                       (SELECT COALESCE(SUM(rejected_qty), 0) FROM inward_items ii WHERE ii.inward_id = i.id) AS total_rejected_qty
                FROM inwards i
                LEFT JOIN concerns con ON i.concern_id = con.id
                LEFT JOIN concerns fc ON i.from_concern_id = fc.id
                LEFT JOIN production_units pu ON i.production_unit_id = pu.id
                LEFT JOIN outwards o ON i.outward_id = o.id
                LEFT JOIN suppliers s ON i.supplier_id = s.id
                LEFT JOIN purchase_bills pb ON i.purchase_bill_id = pb.id
                LEFT JOIN users u ON i.created_by = u.id";
        
        $params = [];
        if ($cid) {
            $sql .= " WHERE i.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY i.id DESC";
        $inwards = DB::fetchAll($sql, $params);

        // Fetch pending incoming transfers from production units / other concerns dispatched specifically to this concern
        $pendingTransfersSql = "SELECT o.*, oi.variant_id, oi.qty AS transfer_qty,
                                       con.name AS from_concern_name, con.code AS from_concern_code,
                                       v.name AS variant_name, v.sku_code, v.size, v.weight, v.weight_unit, v.price, v.mrp,
                                       v.category_id, v.subcategory_id, v.brand_id, v.catalog_id, v.attributes
                                FROM outwards o
                                JOIN outward_items oi ON o.id = oi.outward_id
                                JOIN concerns con ON o.concern_id = con.id
                                JOIN variants v ON oi.variant_id = v.id
                                WHERE o.transfer_type = 'concern' AND o.status IN ('prepared', 'dispatched')";
        $pendingParams = [];
        if ($cid) {
            $pendingTransfersSql .= " AND o.destination_concern_id = ?";
            $pendingParams[] = $cid;
        }
        $pendingTransfersSql .= " ORDER BY o.id DESC";
        $pendingTransfers = DB::fetchAll($pendingTransfersSql, $pendingParams);

        $allConcerns = DB::fetchAll("SELECT id, name, code FROM concerns WHERE status = 'active' ORDER BY name ASC");

        $unitSql = "SELECT id, name, code FROM production_units WHERE is_active = 1";
        $unitParams = [];
        if ($cid) {
            $unitSql .= " AND concern_id = ?";
            $unitParams[] = $cid;
        }
        $unitSql .= " ORDER BY name ASC";
        $productionUnits = DB::fetchAll($unitSql, $unitParams);

        if ($cid) {
            $suppliers = DB::fetchAll("SELECT id, name FROM suppliers WHERE concern_id = ? AND status = 'active'", [$cid]);
            $variants  = DB::fetchAll("SELECT id, sku_code, name, size, weight_unit FROM variants WHERE concern_id = ? AND status = 'active'", [$cid]);
        } else {
            $suppliers = DB::fetchAll("SELECT id, name FROM suppliers WHERE status = 'active'");
            $variants  = DB::fetchAll("SELECT id, sku_code, name, size, weight_unit FROM variants WHERE status = 'active'");
        }

        $this->render('concern/inward', [
            'pageTitle'        => 'Inward Stock Consignments & GRN',
            'currentModule'    => 'inward',
            'inwards'          => $inwards,
            'pendingTransfers' => $pendingTransfers,
            'suppliers'        => $suppliers,
            'variants'         => $variants,
            'allConcerns'      => $allConcerns,
            'productionUnits'  => $productionUnits,
            'selectedCid'      => $cid,
        ]);
    }

    public function store(): void {
        $this->validateCsrf();
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);

        if (!$cid) {
            $this->flash('error', 'Please select a concern for this inward consignment.');
            $this->redirect('/m/inward');
        }

        $inwardType = trim($_POST['inward_type'] ?? 'purchase');
        $outwardId  = !empty($_POST['outward_id']) ? (int)$_POST['outward_id'] : null;

        $inwardDate = $_POST['inward_date'] ?: date('Y-m-d');
        $location   = trim($_POST['location'] ?? 'Main Store');
        $remarks    = trim($_POST['remarks'] ?? '');

        DB::beginTransaction();
        try {
            $fromConcernId = null;
            $supplierId = null;

            if ($inwardType === 'transfer' && $outwardId) {
                // Inter-concern transfer receiving
                $outward = DB::fetch(
                    "SELECT o.*, oi.variant_id AS source_variant_id, oi.qty AS transfer_qty,
                            con.name AS from_concern_name, con.code AS from_concern_code
                     FROM outwards o
                     JOIN outward_items oi ON o.id = oi.outward_id
                     JOIN concerns con ON o.concern_id = con.id
                     WHERE o.id = ?",
                    [$outwardId]
                );

                if (!$outward) {
                    throw new Exception("Outward transfer consignment #{$outwardId} not found.");
                }

                // STRICT TENANT ISOLATION: Must match destination concern selected during outward!
                if ((int)$outward['destination_concern_id'] !== (int)$cid) {
                    throw new Exception("Security Violation: This outward was dispatched to concern #{$outward['destination_concern_id']} and cannot be inwarded into this concern.");
                }

                if ($outward['status'] === 'received') {
                    throw new Exception("This outward consignment has already been inwarded.");
                }

                $fromConcernId = (int)$outward['concern_id'];
                $qty = (float)($_POST['qty'] ?? $outward['transfer_qty']);
                if ($qty <= 0) {
                    $qty = (float)$outward['transfer_qty'];
                }
                $unitCost = (float)($_POST['unit_cost'] ?? 0);

                // Resolve/maintain product variant in the receiving concern ($cid)
                $srcVariant = DB::fetch("SELECT * FROM variants WHERE id = ?", [$outward['source_variant_id']]);
                if (!$srcVariant) {
                    throw new Exception("Source variant not found for inward transfer.");
                }

                // Check if receiving concern already has this variant SKU/name
                $existingVariant = DB::fetch(
                    "SELECT * FROM variants WHERE concern_id = ? AND sku_code = ?",
                    [$cid, $srcVariant['sku_code']]
                );

                if ($existingVariant) {
                    $variantId = (int)$existingVariant['id'];
                } else {
                    // Safe category and subcategory fallback for integrity constraints
                    $catId = $srcVariant['category_id'] ?: (int)DB::fetchValue("SELECT id FROM categories LIMIT 1");
                    $subId = $srcVariant['subcategory_id'] ?: (int)DB::fetchValue("SELECT id FROM subcategories WHERE category_id = ? LIMIT 1", [$catId]);
                    if (!$subId) {
                        $subId = (int)DB::fetchValue("SELECT id FROM subcategories LIMIT 1");
                    }

                    // Create a separate product variant record belonging strictly to this receiving concern
                    DB::query(
                        "INSERT INTO variants (concern_id, catalog_id, sku_code, name, category_id, subcategory_id, brand_id, size, weight, weight_unit, attributes, price, mrp, creation_date, status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'active', NOW())",
                        [
                            $cid,
                            $srcVariant['catalog_id'],
                            $srcVariant['sku_code'],
                            $srcVariant['name'],
                            $catId,
                            $subId,
                            $srcVariant['brand_id'],
                            $srcVariant['size'] ?: 'Standard',
                            $srcVariant['weight'] ?: 0.00,
                            $srcVariant['weight_unit'] ?: 'pcs',
                            $srcVariant['attributes'],
                            $srcVariant['price'] ?: 0.00,
                            $srcVariant['mrp'] ?: 0.00
                        ]
                    );
                    $variantId = (int)DB::lastInsertId();
                }

                $transferRemarks = "Received transfer from {$outward['from_concern_name']} (Gate Pass #{$outward['outward_no']})";
                $remarks = $remarks ? "{$transferRemarks} - {$remarks}" : $transferRemarks;

            } else {
                // Standard Purchase / Supplier Inward
                $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
                $variantId  = (int)($_POST['variant_id'] ?? 0);
                $qty        = (float)($_POST['qty'] ?? 0);
                $unitCost   = (float)($_POST['unit_cost'] ?? 0);

                if (!$variantId || $qty <= 0) {
                    throw new Exception("Please select a valid variant and specify a quantity greater than zero.");
                }
            }

            $inwardNo = NumberSeries::getNext($cid, 'INWARD');
            $grnNumber = !empty($_POST['grn_number']) ? trim($_POST['grn_number']) : Inward::generateGrnNumber($cid);
            $productionUnitId = !empty($_POST['production_unit_id']) ? (int)$_POST['production_unit_id'] : null;
            $warehouseId = !empty($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : null;
            $vehicleNumber = !empty($_POST['vehicle_number']) ? trim($_POST['vehicle_number']) : null;
            $poId = !empty($_POST['po_id']) ? (int)$_POST['po_id'] : null;

            $acceptedQty = isset($_POST['accepted_qty']) ? (float)$_POST['accepted_qty'] : $qty;
            if ($acceptedQty > $qty) {
                $acceptedQty = $qty;
            }
            $rejectedQty = isset($_POST['rejected_qty']) ? (float)$_POST['rejected_qty'] : max(0.00, $qty - $acceptedQty);
            $rejectionReason = !empty($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : null;

            DB::query(
                "INSERT INTO inwards (concern_id, production_unit_id, inward_no, grn_number, po_id, outward_id, from_concern_id, inward_type, supplier_id, inward_date, location, warehouse_id, vehicle_number, received_by, status, remarks, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'VERIFIED', ?, ?, NOW())",
                [$cid, $productionUnitId, $inwardNo, $grnNumber, $poId, $outwardId, $fromConcernId, $inwardType, $supplierId, $inwardDate, $location, $warehouseId, $vehicleNumber, Auth::id(), $remarks, Auth::id()]
            );
            $inwardId = (int)DB::lastInsertId();

            DB::query(
                "INSERT INTO inward_items (concern_id, inward_id, variant_id, qty, accepted_qty, rejected_qty, rejection_reason, unit_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$cid, $inwardId, $variantId, $qty, $acceptedQty, $rejectedQty, $rejectionReason, $unitCost]
            );

            // Single stock gateway: Double-entry credit for accepted items strictly to this concern
            if ($acceptedQty > 0) {
                StockService::move(
                    $cid,
                    $variantId,
                    $location,
                    $acceptedQty,
                    0.00,
                    'PURCHASE_INWARD',
                    $inwardId,
                    "GRN Inward receipt #{$grnNumber} (Inward {$inwardNo})" . ($remarks ? ": {$remarks}" : ""),
                    Auth::id()
                );
            }

            // If this was from an outward, update outward status to 'received'
            if ($outwardId) {
                DB::query("UPDATE outwards SET status = 'received' WHERE id = ?", [$outwardId]);
            }

            DB::commit();

            $this->flash('success', "Inward consignment '{$inwardNo}' recorded successfully and {$qty} units added to live stock!");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to record inward: ' . $e->getMessage());
        }

        $this->redirect('/m/inward');
    }

    public function update(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $inward = DB::fetch("SELECT * FROM inwards WHERE id = ?", [$id]);
        if (!$inward) {
            $this->flash('error', 'Inward record not found.');
            $this->redirect('/m/inward');
        }

        $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : $inward['supplier_id'];
        $location   = trim($_POST['location'] ?? $inward['location']);
        $remarks    = trim($_POST['remarks'] ?? $inward['remarks']);
        $inwardDate = $_POST['inward_date'] ?: $inward['inward_date'];

        DB::beginTransaction();
        try {
            DB::query(
                "UPDATE inwards SET supplier_id = ?, location = ?, remarks = ?, inward_date = ? WHERE id = ?",
                [$supplierId, $location, $remarks, $inwardDate, $id]
            );
            DB::commit();
            $this->flash('success', "Inward consignment '{$inward['inward_no']}' updated successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to update inward: ' . $e->getMessage());
        }

        $this->redirect('/m/inward');
    }

    public function delete(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $inward = DB::fetch("SELECT * FROM inwards WHERE id = ?", [$id]);
        if (!$inward) {
            $this->flash('error', 'Inward record not found.');
            $this->redirect('/m/inward');
        }

        $items = DB::fetchAll("SELECT * FROM inward_items WHERE inward_id = ?", [$id]);

        DB::beginTransaction();
        try {
            // Deduct added quantities from stock
            foreach ($items as $item) {
                StockService::move(
                    $inward['concern_id'],
                    $item['variant_id'],
                    $inward['location'],
                    0.00,
                    (float)$item['qty'],
                    'Inward',
                    $id,
                    "Reversal of deleted Inward [{$inward['inward_no']}]",
                    Auth::id()
                );
            }

            // Revert outward status to 'dispatched' if linked
            if (!empty($inward['outward_id'])) {
                DB::query("UPDATE outwards SET status = 'dispatched' WHERE id = ?", [$inward['outward_id']]);
            }

            DB::query("DELETE FROM inward_items WHERE inward_id = ?", [$id]);
            DB::query("DELETE FROM inwards WHERE id = ?", [$id]);

            DB::commit();
            $this->flash('success', "Inward consignment '{$inward['inward_no']}' deleted and stock reversed.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to delete inward: ' . $e->getMessage());
        }

        $this->redirect('/m/inward');
    }

    public function exportInward(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT i.*, s.name AS supplier_name, pb.bill_no, u.name AS user_name,
                       con.name AS concern_name, con.code AS concern_code,
                       fc.name AS from_concern_name, fc.code AS from_concern_code,
                       (SELECT COUNT(*) FROM inward_items ii WHERE ii.inward_id = i.id) AS items_count,
                       (SELECT COALESCE(SUM(qty), 0) FROM inward_items ii WHERE ii.inward_id = i.id) AS total_qty
                FROM inwards i
                LEFT JOIN concerns con ON i.concern_id = con.id
                LEFT JOIN concerns fc ON i.from_concern_id = fc.id
                LEFT JOIN suppliers s ON i.supplier_id = s.id
                LEFT JOIN purchase_bills pb ON i.purchase_bill_id = pb.id
                LEFT JOIN users u ON i.created_by = u.id";
        
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "i.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(i.inward_date) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY i.id DESC";
        $inwards = DB::fetchAll($sql, $params);

        $headers = [
            'Inward No', 'Date', 'Supplier / Vendor', 'Purchase Bill Ref', 'Items Count', 
            'Total Received Qty', 'Carrier / Ref', 'Received By', 'Concern'
        ];

        $rows = [];
        $totItems = 0;
        $totQty = 0;
        foreach ($inwards as $in) {
            $totItems += (int)($in['items_count'] ?? 0);
            $totQty += (float)($in['total_qty'] ?? 0);
            $rows[] = [
                $in['inward_no'],
                date('d M Y', strtotime($in['inward_date'])),
                $in['supplier_name'] ?: ($in['from_concern_name'] ? "Transfer from {$in['from_concern_name']}" : 'Direct Consignment'),
                $in['bill_no'] ?: '-',
                (int)($in['items_count'] ?? 0),
                (float)($in['total_qty'] ?? 0),
                $in['reference_no'] ?? '-',
                $in['user_name'] ?? 'System',
                $in['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total Consignments: ' . count($inwards), '', '', '', $totItems, $totQty, '', '', ''];
        $metadata = [
            'Report Title' => 'Inward Stock Consignments & Material Receipts',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Consignments' => count($inwards),
            'Total Units Received' => number_format($totQty, 2)
        ];

        $filename = 'inwards_report_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Inward Stock Consignments Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Inward Stock Consignments Report', $metadata, $headers, $rows, $totals);
        }
    }
}
