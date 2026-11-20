<?php
// scripts/migrate_production_blueprint.php
// Production Unit Operations Blueprint - Non-Breaking Schema Migration

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;

echo "=== GRM Inventory ERP: Production Unit Blueprint Migration ===\n\n";

$pdo = DB::pdo();

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

// 1. Create `production_units` table
echo "[1/7] Ensuring `production_units` table exists...\n";
$pdo->exec("
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
    INDEX `idx_pu_concern` (`concern_id`),
    FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
echo "  ✓ `production_units` verified.\n";

// Seed baseline production unit for active concerns if empty
$existingUnits = (int)$pdo->query("SELECT COUNT(*) FROM `production_units`")->fetchColumn();
if ($existingUnits === 0) {
    echo "  Seeding default production units for active concerns...\n";
    $concerns = DB::fetchAll("SELECT id, name, code FROM `concerns` WHERE status = 'active'");
    foreach ($concerns as $c) {
        $unitCode = 'PU-' . strtoupper(str_replace(' ', '', $c['code'] ?? '01'));
        $stmt = $pdo->prepare("INSERT INTO `production_units` (`concern_id`, `code`, `name`, `location`, `capacity_per_day`, `is_active`) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$c['id'], $unitCode, $c['name'] . ' Production Unit 1', 'Main Facility', 500]);
        echo "    ✓ Created unit `{$unitCode}` for concern `{$c['name']}`\n";
    }
}

// 2. Expand `inwards` table
echo "[2/7] Expanding `inwards` table with GRN & logistics fields...\n";
$inwardColumns = [
    'production_unit_id' => "INT NULL AFTER `concern_id`",
    'grn_number'         => "VARCHAR(50) NULL AFTER `inward_no`",
    'po_id'              => "INT NULL AFTER `grn_number`",
    'warehouse_id'       => "INT NULL AFTER `location`",
    'vehicle_number'     => "VARCHAR(50) NULL AFTER `warehouse_id`",
    'received_by'        => "INT NULL AFTER `vehicle_number`",
    'status'             => "VARCHAR(50) DEFAULT 'VERIFIED' AFTER `remarks`"
];
foreach ($inwardColumns as $col => $def) {
    if (!columnExists($pdo, 'inwards', $col)) {
        $pdo->exec("ALTER TABLE `inwards` ADD COLUMN `{$col}` {$def}");
        echo "  ✓ Added `inwards`.`{$col}`\n";
    }
}

// 3. Expand `inward_items` table
echo "[3/7] Expanding `inward_items` table with acceptance/rejection fields...\n";
$inwardItemColumns = [
    'accepted_qty'     => "DECIMAL(12,2) DEFAULT 0.00 AFTER `qty`",
    'rejected_qty'     => "DECIMAL(12,2) DEFAULT 0.00 AFTER `accepted_qty`",
    'rejection_reason' => "VARCHAR(255) NULL AFTER `rejected_qty`"
];
foreach ($inwardItemColumns as $col => $def) {
    if (!columnExists($pdo, 'inward_items', $col)) {
        $pdo->exec("ALTER TABLE `inward_items` ADD COLUMN `{$col}` {$def}");
        echo "  ✓ Added `inward_items`.`{$col}`\n";
    }
}
// Sync existing inward_items accepted_qty = qty where accepted_qty is 0
$pdo->exec("UPDATE `inward_items` SET `accepted_qty` = `qty` WHERE `accepted_qty` = 0 AND `qty` > 0");

// 4. Expand `batches` table (Packaging & Production Batches PB-YYYYMMDD-XXX)
echo "[4/7] Expanding `batches` table with pipeline & supervisor approval fields...\n";
$batchColumns = [
    'production_unit_id'      => "INT NULL AFTER `concern_id`",
    'program_id'              => "INT NULL AFTER `variant_id`",
    'cutting_batch_id'        => "INT NULL AFTER `program_id`",
    'stitching_batch_id'      => "INT NULL AFTER `cutting_batch_id`",
    'qc_batch_id'             => "INT NULL AFTER `stitching_batch_id`",
    'additional_qc_batch_ids' => "TEXT NULL AFTER `qc_batch_id`",
    'total_boxes'             => "INT DEFAULT 0 AFTER `batch_qty`",
    'approved_by'             => "INT NULL AFTER `status`",
    'approved_at'             => "DATETIME NULL AFTER `approved_by`"
];
foreach ($batchColumns as $col => $def) {
    if (!columnExists($pdo, 'batches', $col)) {
        $pdo->exec("ALTER TABLE `batches` ADD COLUMN `{$col}` {$def}");
        echo "  ✓ Added `batches`.`{$col}`\n";
    }
}
// Modify status column on batches to allow modern lifecycle states
$pdo->exec("ALTER TABLE `batches` MODIFY COLUMN `status` VARCHAR(50) DEFAULT 'active'");

// 5. Expand `qc_inspections` table
echo "[5/7] Expanding `qc_inspections` with batch linking and rework tracking...\n";
$qcColumns = [
    'batch_no'           => "VARCHAR(50) NULL AFTER `qc_no`",
    'cutting_batch_id'   => "INT NULL AFTER `variant_id`",
    'stitching_batch_id' => "INT NULL AFTER `cutting_batch_id`",
    'rework_qty'         => "DECIMAL(12,2) DEFAULT 0.00 AFTER `passed_qty`",
    'rework_notes'       => "TEXT NULL AFTER `defect_notes`",
    'status'             => "VARCHAR(50) DEFAULT 'COMPLETED' AFTER `rework_notes`"
];
foreach ($qcColumns as $col => $def) {
    if (!columnExists($pdo, 'qc_inspections', $col)) {
        $pdo->exec("ALTER TABLE `qc_inspections` ADD COLUMN `{$col}` {$def}");
        echo "  ✓ Added `qc_inspections`.`{$col}`\n";
    }
}

// 6. Expand `outwards` table (Logistics & Gate Pass)
echo "[6/7] Expanding `outwards` with multi-batch gate pass and carrier transit fields...\n";
$outwardColumns = [
    'production_unit_id' => "INT NULL AFTER `concern_id`",
    'warehouse_id'       => "INT NULL AFTER `location`",
    'vehicle_number'     => "VARCHAR(100) NULL AFTER `warehouse_id`",
    'driver_name'        => "VARCHAR(150) NULL AFTER `vehicle_number`",
    'driver_contact'     => "VARCHAR(50) NULL AFTER `driver_name`",
    'transporter_name'   => "VARCHAR(150) NULL AFTER `driver_contact`",
    'lr_number'          => "VARCHAR(100) NULL AFTER `transporter_name`",
    'gate_pass_no'       => "VARCHAR(50) NULL AFTER `lr_number`",
    'dispatched_at'      => "DATETIME NULL AFTER `status`",
    'received_at'        => "DATETIME NULL AFTER `dispatched_at`"
];
foreach ($outwardColumns as $col => $def) {
    if (!columnExists($pdo, 'outwards', $col)) {
        $pdo->exec("ALTER TABLE `outwards` ADD COLUMN `{$col}` {$def}");
        echo "  ✓ Added `outwards`.`{$col}`\n";
    }
}

// 7. Create `production_outward_batches` pivot table
echo "[7/7] Ensuring `production_outward_batches` consolidation table exists...\n";
$pdo->exec("
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
echo "  ✓ `production_outward_batches` verified.\n\n";

echo "=== All 7 Schema Alignment Steps Completed Successfully! ===\n";
