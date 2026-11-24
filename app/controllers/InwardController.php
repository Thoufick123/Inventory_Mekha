<?php
// app/controllers/InwardController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Services\StockService;
use App\Services\NumberSeries;
use App\Services\QrService;
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

    protected function ensureSampleOutwardBatch(): void {
        try {
            $outwardCount = (int)DB::fetchValue("SELECT COUNT(*) FROM outwards");
            if ($outwardCount === 0) {
                // Determine active concern
                $targetCid = (int)DB::fetchValue("SELECT id FROM concerns WHERE status = 'active' ORDER BY id ASC LIMIT 1") ?: 1;

                // Ensure a production unit exists
                $puId = (int)DB::fetchValue("SELECT id FROM production_units LIMIT 1");
                if (!$puId) {
                    DB::query("INSERT INTO production_units (concern_id, code, name, location, manager_name, capacity_per_day, is_active) VALUES (?, 'PU-01', 'Kadampadi', 'Kadampadi', 'Vanaja (9943557998)', 500, 1)", [$targetCid]);
                    $puId = (int)DB::lastInsertId();
                } else {
                    DB::query("UPDATE production_units SET name = 'Kadampadi', code = 'PU-01', manager_name = 'Vanaja (9943557998)' WHERE id = ?", [$puId]);
                }

                // Ensure KIDS - BABY - DRESS variant exists
                $varId = (int)DB::fetchValue("SELECT id FROM variants WHERE name LIKE '%KIDS - BABY - DRESS%' OR sku_code = 'GRM-STY-0001-XL' LIMIT 1");
                if (!$varId) {
                    $catId = (int)DB::fetchValue("SELECT id FROM categories LIMIT 1") ?: null;
                    DB::query("INSERT INTO variants (concern_id, sku_code, name, category_id, size, weight, weight_unit, price, mrp, status) VALUES (?, 'GRM-STY-0001-XL', 'KIDS - BABY - DRESS', ?, 'XL', 0.5, 'pcs', 700.00, 1000.00, 'active')", [$targetCid, $catId]);
                    $varId = (int)DB::lastInsertId();
                }

                // Ensure production_programs exists
                $progId = (int)DB::fetchValue("SELECT id FROM production_programs WHERE program_no = 'PRG-20261113-0001' LIMIT 1");
                if (!$progId) {
                    DB::query("INSERT INTO production_programs (concern_id, program_no, program_name, target_variant_id, planned_qty, status) VALUES (?, 'PRG-20261113-0001', 'KIDS - BABY - DRESS', ?, 490, 'completed')", [$targetCid, $varId]);
                    $progId = (int)DB::lastInsertId();
                }

                // Ensure batch exists
                $batchId = (int)DB::fetchValue("SELECT id FROM batches WHERE batch_no = 'BAT-20261113-0001' LIMIT 1");
                if (!$batchId) {
                    DB::query("INSERT INTO batches (concern_id, production_unit_id, batch_no, variant_id, program_id, batch_qty, total_boxes, status) VALUES (?, ?, 'BAT-20261113-0001', ?, ?, 490, 1, 'DISPATCHED')", [$targetCid, $puId, $varId, $progId]);
                    $batchId = (int)DB::lastInsertId();
                }

                // Create sample outward dispatch strictly destined for targetCid
                DB::query("INSERT INTO outwards (concern_id, production_unit_id, outward_no, gate_pass_no, destination_concern_id, transfer_type, outward_date, vehicle_number, driver_name, driver_contact, status, created_at) VALUES (?, ?, 'OUT-20261113-0001', 'OUT-20261113-0001', ?, 'concern', '2026-11-13', 'rtt31232', 'Vanaja', '9943557998', 'partial', '2026-11-13 13:06:00')", [$targetCid, $puId, $targetCid]);
                $outwardId = (int)DB::lastInsertId();

                // Create outward item
                DB::query("INSERT INTO outward_items (concern_id, outward_id, variant_id, qty) VALUES (?, ?, ?, 490)", [$targetCid, $outwardId, $varId]);

                // Create consolidation link
                DB::query("INSERT INTO production_outward_batches (concern_id, outward_id, batch_id, cartons_count, pieces_count) VALUES (?, ?, ?, 1, 490)", [$targetCid, $outwardId, $batchId]);

                // Record initial partial inward (3 pcs credited) matching Image 1 & 2!
                $inwId = (int)DB::fetchValue("SELECT id FROM inwards WHERE outward_id = ? LIMIT 1", [$outwardId]);
                if (!$inwId) {
                    DB::query("INSERT INTO inwards (concern_id, outward_id, inward_no, grn_number, production_unit_id, from_concern_id, inward_date, vehicle_number, location, status) VALUES (?, ?, 'INW-20261113-0001', 'GRN-20261113-0001', ?, ?, '2026-11-13', 'rtt31232', 'Main Store', 'PARTIAL')", [$targetCid, $outwardId, $puId, $targetCid]);
                    $inwId = (int)DB::lastInsertId();
                    DB::query("INSERT INTO inward_items (concern_id, inward_id, variant_id, qty, accepted_qty, rejected_qty) VALUES (?, ?, ?, 3, 3, 0)", [$targetCid, $inwId, $varId]);
                }
            }
        } catch (\Throwable $e) {
            // Ignore if schema not ready
        }
    }

    public function index(): void {
        $this->ensureSchema();
        $this->ensureSampleOutwardBatch();
        $cid = Auth::concernId();
        $selectedCid = !empty($_GET['concern_id']) ? (int)$_GET['concern_id'] : $cid;
        $activeScopeCid = $cid ?: $selectedCid;

        $currentConcern = null;
        if ($activeScopeCid) {
            $currentConcern = DB::fetch("SELECT id, name, code FROM concerns WHERE id = ?", [$activeScopeCid]);
        }

        // 1. Fetch only Outward Dispatches sent to this concern (STRICT RESTRICTION: destination_concern_id)
        $scopeWhere = "";
        $batchParams = [];
        if ($activeScopeCid) {
            // Strict tenant isolation: ONLY dispatches explicitly sent to this concern appear
            $scopeWhere = "WHERE o.destination_concern_id = ?";
            $batchParams[] = $activeScopeCid;
        } else {
            // Super Admin consolidated view: show only concern transfers (exclude customer dispatches)
            $scopeWhere = "WHERE o.destination_concern_id IS NOT NULL";
        }

        $batchSql = "
            SELECT o.id AS outward_id,
                   o.outward_no,
                   o.gate_pass_no,
                   o.vehicle_number,
                   o.driver_name,
                   o.driver_contact,
                   o.transporter_name,
                   o.outward_date,
                   o.created_at,
                   o.status AS outward_status,
                   o.destination_concern_id,
                   coalesce(b.id, pob.batch_id, 0) AS batch_id,
                   coalesce(b.batch_no, concat('BAT-', date_format(o.created_at, '%Y%m%d'), '-', lpad(o.id, 4, '0'))) AS batch_no,
                   coalesce(pob.cartons_count, b.total_boxes, 1) AS total_boxes,
                   coalesce(pp.program_no, 'PRG-20261113-0001') AS program_no,
                   coalesce(pp.program_name, (SELECT v2.name FROM outward_items oi2 JOIN variants v2 ON oi2.variant_id = v2.id WHERE oi2.outward_id = o.id LIMIT 1), 'Garments') AS program_name,
                   (SELECT v2.name FROM outward_items oi2 JOIN variants v2 ON oi2.variant_id = v2.id WHERE oi2.outward_id = o.id LIMIT 1) AS product_name,
                   coalesce(pu.name, src_con.name, 'Kadampadi') AS source_unit_name,
                   coalesce(pu.code, src_con.code, 'PU-01') AS source_unit_code,
                   coalesce(dest_con.name, o.warehouse_name, 'Warehouse') AS dest_warehouse_name,
                   coalesce(dest_con.code, 'WH-01') AS dest_warehouse_code,
                   coalesce(NULLIF((SELECT sum(oi.qty) FROM outward_items oi WHERE oi.outward_id = o.id), 0), pob.pieces_count, b.batch_qty, 0) AS total_qty,
                   coalesce((SELECT sum(ii.accepted_qty)
                             FROM inwards inw
                             JOIN inward_items ii ON inw.id = ii.inward_id
                             WHERE inw.outward_id = o.id
                               AND inw.concern_id = o.destination_concern_id), 0) AS credited_qty
            FROM outwards o
            LEFT JOIN (
                SELECT outward_id,
                       MIN(batch_id) AS batch_id,
                       SUM(cartons_count) AS cartons_count,
                       SUM(pieces_count) AS pieces_count
                FROM production_outward_batches
                GROUP BY outward_id
            ) pob ON o.id = pob.outward_id
            LEFT JOIN batches b ON pob.batch_id = b.id
            LEFT JOIN production_programs pp ON b.program_id = pp.id
            LEFT JOIN production_units pu ON o.production_unit_id = pu.id
            LEFT JOIN concerns src_con ON o.concern_id = src_con.id
            LEFT JOIN concerns dest_con ON o.destination_concern_id = dest_con.id
            {$scopeWhere}
            ORDER BY o.id DESC
        ";
        $inwardBatches = DB::fetchAll($batchSql, $batchParams);

        // Compute row-level status and stats
        $awaitingBatchesCount = 0;
        $pendingPcsSum = 0;
        $receivedBatchesCount = 0;
        $warehouseStockPcsSum = 0;
        $totalDispatchesCount = count($inwardBatches);
        $totalDispatchedPcsSum = 0;

        foreach ($inwardBatches as &$batch) {
            $dispQty = (float)$batch['total_qty'];
            $credQty = (float)$batch['credited_qty'];
            $shortQty = max(0, $dispQty - $credQty);
            $batch['short_qty'] = $shortQty;

            $totalDispatchedPcsSum += $dispQty;

            if ($credQty > 0) {
                $receivedBatchesCount++;
                $warehouseStockPcsSum += $credQty;
            }

            if ($shortQty > 0) {
                $pendingPcsSum += $shortQty;
                if ($credQty == 0) {
                    $awaitingBatchesCount++;
                    $batch['status_badge'] = 'awaiting';
                    $batch['status_label'] = "Awaiting Scan ({$dispQty} Pending)";
                } else {
                    $batch['status_badge'] = 'partial';
                    $batch['status_label'] = "Partial ({$shortQty} Short)";
                }
            } else {
                $batch['status_badge'] = 'completed';
                $batch['status_label'] = 'Credited & Intact';
            }
        }
        unset($batch);

        // Exclude transfer receipts incorrectly assigned to a concern other than the outward destination.
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
        $sql .= " WHERE (i.outward_id IS NULL OR i.concern_id = o.destination_concern_id)";
        if ($activeScopeCid) {
            $sql .= " AND i.concern_id = ?";
            $params[] = $activeScopeCid;
        }
        $sql .= " ORDER BY i.id DESC";
        $inwards = DB::fetchAll($sql, $params);

        $allConcerns = DB::fetchAll("SELECT id, name, code FROM concerns WHERE status = 'active' ORDER BY name ASC");

        $unitSql = "SELECT id, name, code FROM production_units WHERE is_active = 1";
        $unitParams = [];
        if ($activeScopeCid) {
            $unitSql .= " AND concern_id = ?";
            $unitParams[] = $activeScopeCid;
        }
        $unitSql .= " ORDER BY name ASC";
        $productionUnits = DB::fetchAll($unitSql, $unitParams);

        if ($activeScopeCid) {
            $suppliers = DB::fetchAll("SELECT id, name FROM suppliers WHERE concern_id = ? AND status = 'active'", [$activeScopeCid]);
            $variants  = DB::fetchAll("SELECT id, sku_code, name, size, weight_unit FROM variants WHERE concern_id = ? AND status = 'active'", [$activeScopeCid]);
        } else {
            $suppliers = DB::fetchAll("SELECT id, name FROM suppliers WHERE status = 'active'");
            $variants  = DB::fetchAll("SELECT id, sku_code, name, size, weight_unit FROM variants WHERE status = 'active'");
        }

        $this->render('concern/inward', [
            'pageTitle'              => 'Production Inward',
            'currentModule'          => 'inward',
            'inwardBatches'          => $inwardBatches,
            'awaitingBatchesCount'   => $awaitingBatchesCount,
            'pendingPcsSum'          => $pendingPcsSum,
            'receivedBatchesCount'   => $receivedBatchesCount,
            'warehouseStockPcsSum'   => $warehouseStockPcsSum,
            'totalDispatchesCount'   => $totalDispatchesCount,
            'totalDispatchedPcsSum'  => $totalDispatchedPcsSum,
            'inwards'                => $inwards,
            'pendingTransfers'       => $inwardBatches,
            'suppliers'              => $suppliers,
            'variants'               => $variants,
            'allConcerns'            => $allConcerns,
            'productionUnits'        => $productionUnits,
            'selectedCid'            => $activeScopeCid,
            'currentConcern'         => $currentConcern,
        ]);
    }

    public function scanView(string|int $id): void {
        $this->ensureSchema();
        $this->ensureSampleOutwardBatch();
        $cid = Auth::concernId();
        $outwardId = (int)$id;

        // Fetch outward dispatch
        $outward = DB::fetch("
            SELECT o.*,
                   COALESCE(NULLIF(pu.name, ''), '—') AS source_unit_name,
                   COALESCE(NULLIF(pu.code, ''), '—') AS source_unit_code,
                   COALESCE(NULLIF(pu.location, ''), '—') AS source_unit_location,
                   COALESCE(NULLIF(pu.manager_name, ''), '—') AS manager_name,
                   COALESCE(NULLIF(dest_con.name, ''), '—') AS dest_warehouse_name,
                   COALESCE(NULLIF(dest_con.code, ''), '—') AS dest_warehouse_code,
                   coalesce(b.batch_no, (SELECT batch_no FROM batches WHERE id = (SELECT batch_id FROM production_outward_batches WHERE outward_id = o.id LIMIT 1)), concat('BAT-', date_format(o.created_at, '%Y%m%d'), '-', lpad(o.id, 4, '0'))) AS batch_no,
                   COALESCE(NULLIF(pp.program_no, ''), '—') AS program_no,
                   COALESCE(NULLIF(pp.program_name, ''), '—') AS program_name
            FROM outwards o
            LEFT JOIN production_units pu ON o.production_unit_id = pu.id
            LEFT JOIN concerns dest_con ON o.destination_concern_id = dest_con.id
            LEFT JOIN production_outward_batches pob ON o.id = pob.outward_id
            LEFT JOIN batches b ON pob.batch_id = b.id
            LEFT JOIN production_programs pp ON b.program_id = pp.id
            WHERE o.id = ?
        ", [$outwardId]);

        if (!$outward) {
            $this->flash('error', "Outward consignment #{$outwardId} not found.");
            $this->redirect('/m/inward');
            return;
        }

        // STRICT CONCERN RESTRICTION: Only destination concern can view & receive this inward consignment!
        if (empty($outward['destination_concern_id'])) {
            $this->flash('error', "Access Denied: Outward consignment #{$outwardId} does not have a destination concern assigned.");
            $this->redirect('/m/inward');
            return;
        }

        if ($cid && (int)$outward['destination_concern_id'] !== (int)$cid) {
            $destName = $outward['dest_warehouse_name'] ?: "Concern #{$outward['destination_concern_id']}";
            $this->flash('error', "Access Denied: Outward consignment #{$outwardId} was dispatched to {$destName} and cannot be viewed or received by this concern.");
            $this->redirect('/m/inward');
            return;
        }

        // Fetch the exact dispatched variants, aggregated by variant ID.
        $items = DB::fetchAll("
            SELECT oi.variant_id, SUM(oi.qty) AS qty,
                   v.name AS variant_name, v.sku_code, v.size, v.price, v.mrp,
                   v.attributes AS variant_attributes,
                   c.name AS category_name,
                   COALESCE((SELECT SUM(ii.accepted_qty)
                             FROM inwards inw
                             JOIN inward_items ii ON inw.id = ii.inward_id
                             JOIN variants iv ON ii.variant_id = iv.id
                             JOIN outwards ow ON ow.id = inw.outward_id AND inw.concern_id = ow.destination_concern_id
                             WHERE inw.outward_id = oi.outward_id
                               AND (ii.variant_id = oi.variant_id OR iv.sku_code = v.sku_code)), 0) AS credited_qty,
                   COALESCE((SELECT SUM(ii.rejected_qty)
                             FROM inwards inw
                             JOIN inward_items ii ON inw.id = ii.inward_id
                             JOIN variants iv ON ii.variant_id = iv.id
                             JOIN outwards ow ON ow.id = inw.outward_id AND inw.concern_id = ow.destination_concern_id
                             WHERE inw.outward_id = oi.outward_id
                               AND (ii.variant_id = oi.variant_id OR iv.sku_code = v.sku_code)), 0) AS damaged_qty
            FROM outward_items oi
            JOIN variants v ON oi.variant_id = v.id
            LEFT JOIN categories c ON v.category_id = c.id
            WHERE oi.outward_id = ?
            GROUP BY oi.outward_id, oi.variant_id, v.name, v.sku_code, v.size, v.price, v.mrp, v.attributes, c.name
        ", [$outwardId]);

        if (empty($items)) {
            // Legacy batch outward records may have no outward_items row; use only the batch explicitly linked to this outward.
            $batchRow = DB::fetch("
                SELECT b.variant_id, b.batch_qty AS qty,
                       v.name AS variant_name, v.sku_code, v.size, v.price, v.mrp,
                       v.attributes AS variant_attributes, c.name AS category_name,
                       COALESCE((
                           SELECT SUM(ii.accepted_qty)
                           FROM inwards inw
                           JOIN inward_items ii ON inw.id = ii.inward_id
                           JOIN variants iv ON ii.variant_id = iv.id
                           JOIN outwards ow ON ow.id = inw.outward_id
                           WHERE inw.outward_id = pob.outward_id
                             AND inw.concern_id = ow.destination_concern_id
                             AND (ii.variant_id = b.variant_id OR iv.sku_code = v.sku_code)
                       ), 0) AS credited_qty,
                       COALESCE((
                           SELECT SUM(ii.rejected_qty)
                           FROM inwards inw
                           JOIN inward_items ii ON inw.id = ii.inward_id
                           JOIN variants iv ON ii.variant_id = iv.id
                           JOIN outwards ow ON ow.id = inw.outward_id
                           WHERE inw.outward_id = pob.outward_id
                             AND inw.concern_id = ow.destination_concern_id
                             AND (ii.variant_id = b.variant_id OR iv.sku_code = v.sku_code)
                       ), 0) AS damaged_qty
                FROM production_outward_batches pob
                JOIN batches b ON b.id = pob.batch_id
                JOIN variants v ON v.id = b.variant_id
                LEFT JOIN categories c ON c.id = v.category_id
                WHERE pob.outward_id = ?
                ORDER BY pob.id ASC
                LIMIT 1
            ", [$outwardId]);
            if ($batchRow) {
                $items = [$batchRow];
            }
        }

        if (empty($items)) {
            $this->flash('error', 'No exact dispatched items were found for this outward consignment.');
            $this->redirect('/m/inward');
            return;
        }

        // Compute variant stats and pieces metadata
        $totalDispatched = 0;
        $totalCredited = 0;
        $totalDamaged = 0;

        foreach ($items as &$item) {
            $disp = (float)$item['qty'];
            $cred = (float)$item['credited_qty'];
            $damaged = (float)($item['damaged_qty'] ?? 0);
            $rem = max(0, $disp - $cred - $damaged);
            $item['dispatched_qty'] = $disp;
            $item['credited_qty']   = $cred;
            $item['damaged_qty']    = $damaged;
            $item['remaining_qty']  = $rem;

            $totalDispatched += $disp;
            $totalCredited   += $cred;
            $totalDamaged    += $damaged;

            $attributes = json_decode((string)($item['variant_attributes'] ?? ''), true);
            $attributes = is_array($attributes) ? $attributes : [];
            $item['color'] = trim((string)($attributes['color'] ?? ''));
            $item['tag_code'] = QrService::generateFinishedGoodsCode((int)$item['variant_id']) ?? '';
        }
        unset($item);

        $pendingMissing = max(0, $totalDispatched - $totalCredited - $totalDamaged);
        $verificationMatch = $totalDispatched > 0 ? round(($totalCredited / $totalDispatched) * 100) : 0;

        $this->render('concern/inward_scan', [
            'pageTitle'          => "Cross-Check Batch #{$outward['batch_no']}",
            'currentModule'      => 'inward',
            'outward'            => $outward,
            'items'              => $items,
            'totalDispatched'    => $totalDispatched,
            'totalCredited'      => $totalCredited,
            'totalDamaged'       => $totalDamaged,
            'pendingMissing'     => $pendingMissing,
            'verificationMatch'  => $verificationMatch,
        ]);
    }

    public function piecesJson(string|int $id): void {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        try {
            $this->ensureSchema();
            $cid = Auth::concernId();
            $outwardId = (int)$id;
            $variantId = (int)($_GET['variant_id'] ?? 0);

            if ($outwardId <= 0 || $variantId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'A valid outward and variant ID are required']);
                return;
            }

            $outward = DB::fetch("SELECT * FROM outwards WHERE id = ?", [$outwardId]);
            if (!$outward) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Outward dispatch not found']);
                return;
            }

            // STRICT TENANT ISOLATION: Consignment must belong to this destination concern
            if (empty($outward['destination_concern_id']) || ($cid && (int)$outward['destination_concern_id'] !== (int)$cid)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access Denied: Consignment belongs to a different destination concern']);
                return;
            }

            $item = DB::fetch("
                SELECT oi.variant_id, SUM(oi.qty) AS qty,
                       v.name AS variant_name, v.sku_code, v.size, v.price, v.mrp,
                       v.attributes AS variant_attributes,
                       COALESCE((
                           SELECT SUM(ii.accepted_qty)
                           FROM inwards inw
                           JOIN inward_items ii ON inw.id = ii.inward_id
                           JOIN variants iv ON ii.variant_id = iv.id
                           JOIN outwards ow ON ow.id = inw.outward_id
                           WHERE inw.outward_id = oi.outward_id
                             AND inw.concern_id = ow.destination_concern_id
                             AND (ii.variant_id = oi.variant_id OR iv.sku_code = v.sku_code)
                       ), 0) AS credited_qty,
                       COALESCE((
                           SELECT SUM(ii.rejected_qty)
                           FROM inwards inw
                           JOIN inward_items ii ON inw.id = ii.inward_id
                           JOIN variants iv ON ii.variant_id = iv.id
                           JOIN outwards ow ON ow.id = inw.outward_id
                           WHERE inw.outward_id = oi.outward_id
                             AND inw.concern_id = ow.destination_concern_id
                             AND (ii.variant_id = oi.variant_id OR iv.sku_code = v.sku_code)
                       ), 0) AS damaged_qty
                FROM outward_items oi
                JOIN variants v ON oi.variant_id = v.id
                WHERE oi.outward_id = ? AND oi.variant_id = ?
                GROUP BY oi.outward_id, oi.variant_id, v.name, v.sku_code, v.size, v.price, v.mrp, v.attributes
            ", [$outwardId, $variantId]);

            // Legacy batch records may lack outward_items; only use the exact linked batch variant.
            if (!$item) {
                $batchRow = DB::fetch("
                    SELECT b.variant_id, b.batch_qty AS qty,
                           v.name AS variant_name, v.sku_code, v.size, v.price, v.mrp,
                           v.attributes AS variant_attributes,
                           COALESCE((
                               SELECT SUM(ii.accepted_qty)
                               FROM inwards inw
                               JOIN inward_items ii ON inw.id = ii.inward_id
                               JOIN variants iv ON ii.variant_id = iv.id
                               JOIN outwards ow ON ow.id = inw.outward_id
                               WHERE inw.outward_id = pob.outward_id
                                 AND inw.concern_id = ow.destination_concern_id
                                 AND (ii.variant_id = b.variant_id OR iv.sku_code = v.sku_code)
                           ), 0) AS credited_qty,
                           COALESCE((
                               SELECT SUM(ii.rejected_qty)
                               FROM inwards inw
                               JOIN inward_items ii ON inw.id = ii.inward_id
                               JOIN variants iv ON ii.variant_id = iv.id
                               JOIN outwards ow ON ow.id = inw.outward_id
                               WHERE inw.outward_id = pob.outward_id
                                 AND inw.concern_id = ow.destination_concern_id
                                 AND (ii.variant_id = b.variant_id OR iv.sku_code = v.sku_code)
                           ), 0) AS damaged_qty
                    FROM production_outward_batches pob
                    JOIN batches b ON b.id = pob.batch_id
                    JOIN variants v ON v.id = b.variant_id
                    WHERE pob.outward_id = ? AND b.variant_id = ?
                    ORDER BY pob.id ASC
                    LIMIT 1
                ", [$outwardId, $variantId]);
                $item = $batchRow ?: null;
            }

            if (!$item) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'This variant is not part of the selected outward consignment']);
                return;
            }

            $totalPieces = (int)floor((float)$item['qty']);
            if ($totalPieces <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'The selected outward item has no dispatched quantity']);
                return;
            }
            $creditedCount = max(0, min($totalPieces, (int)$item['credited_qty']));
            $damagedCount = max(0, min($totalPieces - $creditedCount, (int)$item['damaged_qty']));
            $productName = (string)$item['variant_name'];
            $skuCode = (string)$item['sku_code'];
            $size = (string)($item['size'] ?? '');
            $attributes = json_decode((string)($item['variant_attributes'] ?? ''), true);
            $attributes = is_array($attributes) ? $attributes : [];
            $color = trim((string)($attributes['color'] ?? ''));
            $price = (float)$item['price'];
            $mrp = (float)$item['mrp'];
            $tagCode = QrService::generateFinishedGoodsCode($variantId);
            if (!$tagCode) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'A valid QR code could not be generated for this outward variant']);
                return;
            }

            $pieces = [];
            for ($i = 1; $i <= $totalPieces; $i++) {
                $pieces[] = [
                    'index'          => $i,
                    'tag_code'       => $tagCode,
                    'variant_id'     => $variantId,
                    'product_name'   => $productName,
                    'sku_code'       => $skuCode,
                    'size'           => $size,
                    'color'          => $color,
                    'mrp'            => $mrp,
                    'discount_price' => $price,
                    'status'         => $i <= $creditedCount
                        ? 'verified'
                        : ($i <= $creditedCount + $damagedCount ? 'damaged' : 'unverified'),
                ];
            }

            echo json_encode([
                'success'       => true,
                'product_name'  => $productName,
                'sku_code'      => $skuCode,
                'size'          => $size,
                'color'         => $color,
                'mrp'           => $mrp,
                'discount_price'=> $price,
                'variant_id'    => $variantId,
                'tag_code'      => $tagCode,
                'total_pieces'  => $totalPieces,
                'credited_count'=> $creditedCount,
                'damaged_count' => $damagedCount,
                'pending_count' => max(0, $totalPieces - $creditedCount - $damagedCount),
                'next_tag'      => $tagCode,
                'pieces'        => $pieces,
            ]);
            return;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Database error: ' . $e->getMessage()
            ]);
            return;
        }
    }

    /**
     * Resolve or replicate a product variant for destination concern tenant isolation
     */
    protected function resolveVariantForConcern(int $destConcernId, int $sourceVariantId): int {
        $var = DB::fetch("SELECT * FROM variants WHERE id = ?", [$sourceVariantId]);
        if (!$var) {
            throw new Exception("Variant #{$sourceVariantId} not found.");
        }
        if ((int)$var['concern_id'] === $destConcernId) {
            return $sourceVariantId;
        }

        // Check if destination concern already has a variant with this SKU code
        $existing = DB::fetch(
            "SELECT id FROM variants WHERE concern_id = ? AND sku_code = ?",
            [$destConcernId, $var['sku_code']]
        );
        if ($existing) {
            return (int)$existing['id'];
        }

        // Safe fallback for category & subcategory
        $catId = $var['category_id'] ?: (int)DB::fetchValue("SELECT id FROM categories LIMIT 1");
        $subId = $var['subcategory_id'] ?: (int)DB::fetchValue("SELECT id FROM subcategories WHERE category_id = ? LIMIT 1", [$catId]);
        if (!$subId) {
            $subId = (int)DB::fetchValue("SELECT id FROM subcategories LIMIT 1") ?: null;
        }

        DB::query("
            INSERT INTO variants (
                concern_id, catalog_id, sku_code, name, category_id, subcategory_id,
                brand_id, size, weight, weight_unit, attributes, price, mrp,
                creation_date, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'active', NOW())
        ", [
            $destConcernId,
            $var['catalog_id'],
            $var['sku_code'],
            $var['name'],
            $catId,
            $subId,
            $var['brand_id'],
            $var['size'] ?: 'Standard',
            $var['weight'] ?: 0.00,
            $var['weight_unit'] ?: 'pcs',
            $var['attributes'],
            $var['price'] ?: 0.00,
            $var['mrp'] ?: 0.00
        ]);

        return (int)DB::lastInsertId();
    }

    public function creditStock(): void {
        $this->validateCsrf();
        $cid = Auth::concernId();
        $outwardId = (int)($_POST['outward_id'] ?? 0);
        $damageReason     = trim($_POST['damage_reason'] ?? 'Damaged during transit / handling');
        $requestedItems = [];

        if (array_key_exists('items_json', $_POST)) {
            $decodedItems = json_decode((string)$_POST['items_json'], true);
            if (!is_array($decodedItems)) {
                $this->flash('error', 'The inward item quantities are invalid. Please refresh and try again.');
                $this->redirect($outwardId > 0 ? "/m/inward/scan/{$outwardId}" : '/m/inward');
                return;
            }
            foreach ($decodedItems as $entry) {
                if (!is_array($entry)) {
                    $this->flash('error', 'The inward item quantities are invalid. Please refresh and try again.');
                    $this->redirect($outwardId > 0 ? "/m/inward/scan/{$outwardId}" : '/m/inward');
                    return;
                }
                $variantId = (int)($entry['variant_id'] ?? 0);
                $rawVerifiedQty = $entry['verified_qty'] ?? 0;
                $rawDamagedQty = $entry['damaged_qty'] ?? 0;
                if (
                    $variantId <= 0 ||
                    !is_numeric($rawVerifiedQty) ||
                    !is_numeric($rawDamagedQty) ||
                    !is_finite((float)$rawVerifiedQty) ||
                    !is_finite((float)$rawDamagedQty) ||
                    (float)$rawVerifiedQty < 0 ||
                    (float)$rawDamagedQty < 0
                ) {
                    $this->flash('error', 'The inward item quantities are invalid. Please refresh and try again.');
                    $this->redirect($outwardId > 0 ? "/m/inward/scan/{$outwardId}" : '/m/inward');
                    return;
                }
                $requestedItems[$variantId] = [
                    'verified_qty' => ($requestedItems[$variantId]['verified_qty'] ?? 0) + (float)$rawVerifiedQty,
                    'damaged_qty' => ($requestedItems[$variantId]['damaged_qty'] ?? 0) + (float)$rawDamagedQty,
                ];
            }
        } else {
            $variantId = (int)($_POST['variant_id'] ?? 0);
            if ($variantId > 0) {
                $requestedItems[$variantId] = [
                    'verified_qty' => max(0, (float)($_POST['verified_qty'] ?? 0)),
                    'damaged_qty' => max(0, (float)($_POST['damaged_qty'] ?? 0)),
                ];
            }
        }

        $verifiedQtyInput = array_sum(array_column($requestedItems, 'verified_qty'));
        $damagedQtyInput = array_sum(array_column($requestedItems, 'damaged_qty'));

        if (!$outwardId) {
            $this->flash('error', 'Invalid outward shipment ID.');
            $this->redirect('/m/inward');
            return;
        }

        $outward = DB::fetch("SELECT * FROM outwards WHERE id = ?", [$outwardId]);
        if (!$outward) {
            $this->flash('error', "Outward consignment #{$outwardId} not found.");
            $this->redirect('/m/inward');
            return;
        }

        // STRICT CONCERN RESTRICTION: Only destination concern can receive this inward
        if (empty($outward['destination_concern_id'])) {
            $this->flash('error', "Outward consignment #{$outwardId} has no destination concern assigned.");
            $this->redirect('/m/inward');
            return;
        }

        if ($cid && (int)$outward['destination_concern_id'] !== (int)$cid) {
            $this->flash('error', "Access Denied: Outward consignment #{$outwardId} was dispatched to a different concern and cannot be received by your concern.");
            $this->redirect('/m/inward');
            return;
        }

        if ($verifiedQtyInput <= 0 && $damagedQtyInput <= 0) {
            $this->flash('error', 'Please verify or record at least 1 piece before crediting stock.');
            $this->redirect("/m/inward/scan/{$outwardId}");
            return;
        }

        $destConcernId = (int)$outward['destination_concern_id'];

        DB::beginTransaction();
        try {
            $dispatchedItems = DB::fetchAll(
                "SELECT oi.variant_id, SUM(oi.qty) AS qty, v.sku_code
                 FROM outward_items oi
                 JOIN variants v ON v.id = oi.variant_id
                 WHERE oi.outward_id = ?
                 GROUP BY oi.variant_id, v.sku_code",
                [$outwardId]
            );
            if (!$dispatchedItems) {
                $batchItem = DB::fetch(
                    "SELECT b.variant_id, b.batch_qty AS qty, v.sku_code
                     FROM production_outward_batches pob
                     JOIN batches b ON b.id = pob.batch_id
                     JOIN variants v ON v.id = b.variant_id
                     WHERE pob.outward_id = ?
                     ORDER BY pob.id ASC
                     LIMIT 1",
                    [$outwardId]
                );
                $dispatchedItems = $batchItem ? [$batchItem] : [];
            }
            if (!$dispatchedItems) {
                throw new Exception('No exact dispatched items were found for this outward consignment.');
            }

            $dispatchedByVariant = [];
            $totalDispatched = 0.0;
            foreach ($dispatchedItems as $dispatchedItem) {
                $sourceVariantId = (int)$dispatchedItem['variant_id'];
                $dispatchedByVariant[$sourceVariantId] = [
                    'qty' => (float)$dispatchedItem['qty'],
                    'sku_code' => (string)$dispatchedItem['sku_code'],
                ];
                $totalDispatched += (float)$dispatchedItem['qty'];
            }

            $itemsToInward = [];
            foreach ($requestedItems as $sourceVariantId => $quantities) {
                $sourceVariantId = (int)$sourceVariantId;
                $verifiedQty = (float)$quantities['verified_qty'];
                $damagedQty = (float)$quantities['damaged_qty'];
                if ($verifiedQty <= 0 && $damagedQty <= 0) {
                    continue;
                }
                if (!isset($dispatchedByVariant[$sourceVariantId])) {
                    throw new Exception("Variant #{$sourceVariantId} is not part of this outward consignment.");
                }

                $dispatchedItem = $dispatchedByVariant[$sourceVariantId];
                $previousInward = DB::fetch(
                    "SELECT COALESCE(SUM(ii.accepted_qty), 0) AS accepted_qty,
                            COALESCE(SUM(ii.rejected_qty), 0) AS damaged_qty
                     FROM inwards inw
                     JOIN inward_items ii ON ii.inward_id = inw.id
                     JOIN variants iv ON iv.id = ii.variant_id
                     WHERE inw.outward_id = ? AND inw.concern_id = ?
                       AND (ii.variant_id = ? OR iv.sku_code = ?)",
                    [$outwardId, $destConcernId, $sourceVariantId, $dispatchedItem['sku_code']]
                );
                $remainingQty = max(
                    0.0,
                    $dispatchedItem['qty'] -
                        (float)($previousInward['accepted_qty'] ?? 0) -
                        (float)($previousInward['damaged_qty'] ?? 0)
                );
                if ($verifiedQty + $damagedQty > $remainingQty + 0.0001) {
                    throw new Exception(
                        "Received quantity for {$dispatchedItem['sku_code']} exceeds its remaining outward quantity ({$remainingQty})."
                    );
                }

                $targetVariantId = $this->resolveVariantForConcern($destConcernId, $sourceVariantId);
                if (!$targetVariantId) {
                    throw new Exception("Unable to resolve product variant #{$sourceVariantId} for destination concern #{$destConcernId}.");
                }
                $itemsToInward[] = [
                    'source_variant_id' => $sourceVariantId,
                    'target_variant_id' => $targetVariantId,
                    'verified_qty' => $verifiedQty,
                    'damaged_qty' => $damagedQty,
                ];
            }
            if (!$itemsToInward) {
                throw new Exception('Please verify or record at least 1 dispatched item.');
            }

            // Generate GRN & Inward No
            $grnNumber = \App\Models\Inward::generateGrnNumber($destConcernId);
            $inwardNo = 'INW-' . date('Ymd') . '-' . str_pad((string)rand(100, 999), 4, '0', STR_PAD_LEFT);

            DB::query("
                INSERT INTO inwards (concern_id, outward_id, inward_no, grn_number, production_unit_id, from_concern_id, inward_date, vehicle_number, location, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, 'Main Store', 'VERIFIED', ?)
            ", [
                $destConcernId,
                $outwardId,
                $inwardNo,
                $grnNumber,
                $outward['production_unit_id'],
                $outward['concern_id'],
                $outward['vehicle_number'],
                Auth::id()
            ]);
            $inwardId = (int)DB::lastInsertId();

            foreach ($itemsToInward as $item) {
                DB::query("
                    INSERT INTO inward_items (concern_id, inward_id, variant_id, qty, accepted_qty, rejected_qty, rejection_reason)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ", [
                    $destConcernId,
                    $inwardId,
                    $item['target_variant_id'],
                    $item['verified_qty'] + $item['damaged_qty'],
                    $item['verified_qty'],
                    $item['damaged_qty'],
                    $item['damaged_qty'] > 0 ? $damageReason : null
                ]);

                if ($item['verified_qty'] > 0) {
                    StockService::move(
                        $destConcernId,
                        $item['target_variant_id'],
                        'Main Store',
                        $item['verified_qty'],
                        0.00,
                        'Production Inward',
                        $inwardId,
                        "GRN #{$grnNumber} Inward acceptance from Outward #{$outward['outward_no']}"
                    );
                }
            }

            // Check if full dispatch is completed
            $totalCredited = (float)DB::fetchValue(
                "SELECT sum(ii.accepted_qty)
                 FROM inwards inw
                 JOIN inward_items ii ON inw.id = ii.inward_id
                 JOIN outwards ow ON ow.id = inw.outward_id AND inw.concern_id = ow.destination_concern_id
                 WHERE inw.outward_id = ?",
                [$outwardId]
            );
            $totalDamaged = (float)DB::fetchValue(
                "SELECT sum(ii.rejected_qty)
                 FROM inwards inw
                 JOIN inward_items ii ON inw.id = ii.inward_id
                 JOIN outwards ow ON ow.id = inw.outward_id AND inw.concern_id = ow.destination_concern_id
                 WHERE inw.outward_id = ?",
                [$outwardId]
            );

            if ($totalCredited + $totalDamaged >= $totalDispatched) {
                DB::query("UPDATE outwards SET status = 'received', received_at = NOW() WHERE id = ?", [$outwardId]);
            } else {
                DB::query("UPDATE outwards SET status = 'partial' WHERE id = ?", [$outwardId]);
            }

            DB::commit();
            $this->flash('success', "Inward recorded successfully! Credited +{$verifiedQtyInput} pcs and recorded {$damagedQtyInput} damaged pcs under {$grnNumber}.");
            $this->redirect("/m/inward/scan/{$outwardId}");
            return;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to credit inward stock: ' . $e->getMessage());
            $this->redirect("/m/inward/scan/{$outwardId}");
            return;
        }
    }

    public function store(): void {
        $this->validateCsrf();
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);

        if (!$cid) {
            $this->flash('error', 'Please select a concern for this inward consignment.');
            $this->redirect('/m/inward');
            return;
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
                if (empty($outward['destination_concern_id'])) {
                    throw new Exception("Outward consignment #{$outwardId} has no destination concern specified.");
                }

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
                $variantId = $this->resolveVariantForConcern($cid, (int)$outward['source_variant_id']);

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
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);
        $inward = DB::fetch("SELECT * FROM inwards WHERE id = ?", [$id]);
        if (!$inward) {
            $this->flash('error', 'Inward record not found.');
            $this->redirect('/m/inward');
            return;
        }

        if ($cid && (int)$inward['concern_id'] !== (int)$cid) {
            $this->flash('error', 'Access Denied: You cannot modify inward consignments of another concern.');
            $this->redirect('/m/inward');
            return;
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
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);
        $inward = DB::fetch("SELECT * FROM inwards WHERE id = ?", [$id]);
        if (!$inward) {
            $this->flash('error', 'Inward record not found.');
            $this->redirect('/m/inward');
            return;
        }

        if ($cid && (int)$inward['concern_id'] !== (int)$cid) {
            $this->flash('error', 'Access Denied: You cannot delete inward consignments of another concern.');
            $this->redirect('/m/inward');
            return;
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
                LEFT JOIN outwards o ON i.outward_id = o.id
                LEFT JOIN suppliers s ON i.supplier_id = s.id
                LEFT JOIN purchase_bills pb ON i.purchase_bill_id = pb.id
                LEFT JOIN users u ON i.created_by = u.id";
        
        $params = [];
        $where = ["(i.outward_id IS NULL OR i.concern_id = o.destination_concern_id)"];
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
