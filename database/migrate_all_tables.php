<?php
// database/migrate_all_tables.php
require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;

echo "========================================================\n";
echo "MIGRATING LOCAL DATABASE SCHEMA: All Missing Columns\n";
echo "========================================================\n\n";

// 1. PROGRAM_ROLLS
echo "1. Checking `program_rolls`...\n";
$cols = array_column(DB::fetchAll("SHOW COLUMNS FROM `program_rolls`"), 'Field');
if (!in_array('roll_weight', $cols)) {
    DB::query("ALTER TABLE `program_rolls` ADD COLUMN `roll_weight` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `allocated_qty`");
    echo "  + Added `roll_weight` to `program_rolls`\n";
} else {
    echo "  . `roll_weight` already exists\n";
}

if (!in_array('roll_price', $cols)) {
    DB::query("ALTER TABLE `program_rolls` ADD COLUMN `roll_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `roll_weight`");
    echo "  + Added `roll_price` to `program_rolls`\n";
} else {
    echo "  . `roll_price` already exists\n";
}

// 2. OUTWARDS
echo "\n2. Checking `outwards`...\n";
$cols = array_column(DB::fetchAll("SHOW COLUMNS FROM `outwards`"), 'Field');
if (!in_array('transfer_type', $cols)) {
    DB::query("ALTER TABLE `outwards` ADD COLUMN `transfer_type` ENUM('customer', 'concern') NOT NULL DEFAULT 'customer' AFTER `customer_id`");
    echo "  + Added `transfer_type` to `outwards`\n";
} else {
    echo "  . `transfer_type` already exists\n";
}

if (!in_array('destination_concern_id', $cols)) {
    DB::query("ALTER TABLE `outwards` ADD COLUMN `destination_concern_id` INT NULL AFTER `transfer_type`");
    echo "  + Added `destination_concern_id` to `outwards`\n";
} else {
    echo "  . `destination_concern_id` already exists\n";
}

DB::query("ALTER TABLE `outwards` MODIFY COLUMN `customer_id` INT NULL");
DB::query("ALTER TABLE `outwards` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'prepared'");
echo "  + Ensured `customer_id` is nullable and flexible `status` on `outwards`\n";

// 3. INWARDS
echo "\n3. Checking `inwards`...\n";
$cols = array_column(DB::fetchAll("SHOW COLUMNS FROM `inwards`"), 'Field');
if (!in_array('outward_id', $cols)) {
    DB::query("ALTER TABLE `inwards` ADD COLUMN `outward_id` INT NULL AFTER `purchase_bill_id`");
    echo "  + Added `outward_id` to `inwards`\n";
} else {
    echo "  . `outward_id` already exists\n";
}

if (!in_array('from_concern_id', $cols)) {
    DB::query("ALTER TABLE `inwards` ADD COLUMN `from_concern_id` INT NULL AFTER `outward_id`");
    echo "  + Added `from_concern_id` to `inwards`\n";
} else {
    echo "  . `from_concern_id` already exists\n";
}

if (!in_array('inward_type', $cols)) {
    DB::query("ALTER TABLE `inwards` ADD COLUMN `inward_type` ENUM('purchase', 'transfer') NOT NULL DEFAULT 'purchase' AFTER `from_concern_id`");
    echo "  + Added `inward_type` to `inwards`\n";
} else {
    echo "  . `inward_type` already exists\n";
}

if (!in_array('po_id', $cols)) {
    DB::query("ALTER TABLE `inwards` ADD COLUMN `po_id` INT NULL AFTER `grn_number`");
    echo "  + Added `po_id` to `inwards`\n";
    $cols[] = 'po_id';
} else {
    echo "  . `po_id` already exists\n";
}

if (!in_array('received_by', $cols)) {
    DB::query("ALTER TABLE `inwards` ADD COLUMN `received_by` INT NULL AFTER `vehicle_number`");
    echo "  + Added `received_by` to `inwards`\n";
} else {
    echo "  . `received_by` already exists\n";
}

// 4. CASHIER_SHIFTS
echo "\n4. Checking `cashier_shifts`...\n";
$cols = array_column(DB::fetchAll("SHOW COLUMNS FROM `cashier_shifts`"), 'Field');
if (!in_array('variance_reason', $cols)) {
    DB::query("ALTER TABLE `cashier_shifts` ADD COLUMN `variance_reason` VARCHAR(255) NULL AFTER `variance`");
    echo "  + Added `variance_reason` to `cashier_shifts`\n";
} else {
    echo "  . `variance_reason` already exists\n";
}

// 5. PURCHASE_BILLS
echo "\n5. Checking `purchase_bills`...\n";
$cols = array_column(DB::fetchAll("SHOW COLUMNS FROM `purchase_bills`"), 'Field');
if (!in_array('inward_status', $cols)) {
    DB::query("ALTER TABLE `purchase_bills` ADD COLUMN `inward_status` ENUM('pending', 'completed') NOT NULL DEFAULT 'pending'");
    echo "  + Added `inward_status` to `purchase_bills`\n";
} else {
    echo "  . `inward_status` already exists\n";
}

if (!in_array('inward_at', $cols)) {
    DB::query("ALTER TABLE `purchase_bills` ADD COLUMN `inward_at` DATETIME NULL");
    echo "  + Added `inward_at` to `purchase_bills`\n";
} else {
    echo "  . `inward_at` already exists\n";
}

if (!in_array('attachment_paths', $cols)) {
    DB::query("ALTER TABLE `purchase_bills` ADD COLUMN `attachment_paths` JSON NULL AFTER `attachment_path`");
    echo "  + Added `attachment_paths` to `purchase_bills`\n";
} else {
    echo "  . `attachment_paths` already exists\n";
}

// 6. PURCHASE_BILL_ITEMS
echo "\n6. Checking `purchase_bill_items`...\n";
$cols = array_column(DB::fetchAll("SHOW COLUMNS FROM `purchase_bill_items`"), 'Field');
if (!in_array('inward_status', $cols)) {
    DB::query("ALTER TABLE `purchase_bill_items` ADD COLUMN `inward_status` ENUM('pending', 'completed') NOT NULL DEFAULT 'pending'");
    echo "  + Added `inward_status` to `purchase_bill_items`\n";
} else {
    echo "  . `inward_status` already exists\n";
}

if (!in_array('inward_at', $cols)) {
    DB::query("ALTER TABLE `purchase_bill_items` ADD COLUMN `inward_at` DATETIME NULL");
    echo "  + Added `inward_at` to `purchase_bill_items`\n";
} else {
    echo "  . `inward_at` already exists\n";
}

// 7. STITCHING_ITEMS
echo "\n7. Checking `stitching_items`...\n";
$cols = array_column(DB::fetchAll("SHOW COLUMNS FROM `stitching_items`"), 'Field');
if (!in_array('inward_status', $cols)) {
    DB::query("ALTER TABLE `stitching_items` ADD COLUMN `inward_status` VARCHAR(30) DEFAULT 'pending'");
    echo "  + Added `inward_status` to `stitching_items`\n";
} else {
    echo "  . `inward_status` already exists\n";
}

if (!in_array('inward_at', $cols)) {
    DB::query("ALTER TABLE `stitching_items` ADD COLUMN `inward_at` DATETIME NULL");
    echo "  + Added `inward_at` to `stitching_items`\n";
} else {
    echo "  . `inward_at` already exists\n";
}

// 8. STITCHING_LOGS
echo "\n8. Checking `stitching_logs`...\n";
$cols = array_column(DB::fetchAll("SHOW COLUMNS FROM `stitching_logs`"), 'Field');
if (!in_array('inward_status', $cols)) {
    DB::query("ALTER TABLE `stitching_logs` ADD COLUMN `inward_status` VARCHAR(30) DEFAULT 'pending'");
    echo "  + Added `inward_status` to `stitching_logs`\n";
} else {
    echo "  . `inward_status` already exists\n";
}

echo "\n9. Checking HSN/SAC and GST master...\n";
$categoryCols = array_column(DB::fetchAll("SHOW COLUMNS FROM `categories`"), 'Field');
if (!in_array('default_gst_rate', $categoryCols)) {
    DB::query("ALTER TABLE `categories` ADD COLUMN `default_gst_rate` DECIMAL(5,2) NULL DEFAULT NULL");
}
DB::query("ALTER TABLE `categories` MODIFY COLUMN `default_gst_rate` DECIMAL(5,2) NULL DEFAULT NULL");
$catalogCols = array_column(DB::fetchAll("SHOW COLUMNS FROM `catalogs`"), 'Field');
if (!in_array('gst_rate', $catalogCols)) {
    DB::query("ALTER TABLE `catalogs` ADD COLUMN `gst_rate` DECIMAL(5,2) NULL DEFAULT NULL");
}
DB::query("ALTER TABLE `catalogs` MODIFY COLUMN `gst_rate` DECIMAL(5,2) NULL DEFAULT NULL");
require_once __DIR__ . '/hsn_gst_master.php';
ensureHsnGstMaster();
echo "  + HSN/SAC master schema and textile classifications are up to date\n";

// 10. USER_PERMISSIONS (Multi-tenant concern scoping)
echo "\n10. Checking `user_permissions`...\n";
$upCols = array_column(DB::fetchAll("SHOW COLUMNS FROM `user_permissions`"), 'Field');
if (!in_array('concern_id', $upCols)) {
    DB::query("ALTER TABLE `user_permissions` ADD COLUMN `concern_id` INT NULL AFTER `user_id`");
    try { DB::query("ALTER TABLE `user_permissions` DROP INDEX `uk_user_module`"); } catch (\Throwable $e) {}
    try { DB::query("ALTER TABLE `user_permissions` ADD UNIQUE KEY `uk_user_concern_module` (`user_id`, `concern_id`, `module_id`)"); } catch (\Throwable $e) {}
    try { DB::query("ALTER TABLE `user_permissions` ADD INDEX `idx_up_concern` (`concern_id`)"); } catch (\Throwable $e) {}
    echo "  + Added `concern_id` and updated unique constraint on `user_permissions`\n";
} else {
    echo "  . `concern_id` already exists on `user_permissions`\n";
}

// 11. EXPENSE_CATEGORIES
echo "\n11. Checking `expense_categories`...\n";
DB::query("CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_concern_exp_cat` (`concern_id`, `name`),
    INDEX `idx_exp_cat_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "  + `expense_categories` table verified\n";

// 12. DOC_SEQUENCES
echo "\n12. Checking `doc_sequences`...\n";
DB::query("CREATE TABLE IF NOT EXISTS `doc_sequences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `doc_type` VARCHAR(50) NOT NULL,
    `doc_year` INT NOT NULL,
    `prefix` VARCHAR(20) NOT NULL,
    `current_seq` INT NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_concern_doc_year` (`concern_id`, `doc_type`, `doc_year`),
    INDEX `idx_ds_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "  + `doc_sequences` table verified\n";

// 13. CONCERN_DAMAGES (Lifecycle & Resolution Fields)
echo "\n13. Checking `concern_damages`...\n";
$cdCols = array_column(DB::fetchAll("SHOW COLUMNS FROM `concern_damages`"), 'Field');
$cdMissing = [
    'status'                  => "ENUM('LOGGED', 'INSPECTED', 'RESOLVED', 'CANCELLED') NOT NULL DEFAULT 'LOGGED' AFTER `reason`",
    'severity'                => "ENUM('MINOR', 'MODERATE', 'SEVERE', 'SCRAP') NOT NULL DEFAULT 'MODERATE' AFTER `status`",
    'resolution_action'       => "ENUM('SCRAP', 'WRITE_OFF', 'SALVAGE', 'REPAIR', 'REWORK', 'RETURN_TO_SUPPLIER', 'SUPPLIER_REPLACEMENT') NULL AFTER `severity`",
    'resolution_reason'       => "VARCHAR(255) NULL AFTER `resolution_action`",
    'resolution_notes'        => "TEXT NULL AFTER `resolution_reason`",
    'loss_amount'             => "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `resolution_notes`",
    'photo_url'               => "VARCHAR(255) NULL AFTER `loss_amount`",
    'replacement_variant_id'  => "INT NULL AFTER `photo_url`",
    'replacement_qr_code'     => "VARCHAR(100) NULL AFTER `replacement_variant_id`",
    'replacement_inwarded_at' => "DATETIME NULL AFTER `replacement_qr_code`",
    'resolved_by'             => "INT NULL AFTER `replacement_inwarded_at`",
    'resolved_at'             => "DATETIME NULL AFTER `resolved_by`",
    'cancelled_by'            => "INT NULL AFTER `resolved_at`",
    'cancelled_at'            => "DATETIME NULL AFTER `cancelled_by`",
    'cancel_reason'           => "TEXT NULL AFTER `cancelled_at`",
];
foreach ($cdMissing as $col => $def) {
    if (!in_array($col, $cdCols)) {
        DB::query("ALTER TABLE `concern_damages` ADD COLUMN `{$col}` {$def}");
        echo "  + Added `{$col}` to `concern_damages`\n";
    }
}

// 14. WAREHOUSE & SHOP DAMAGES
echo "\n14. Checking `warehouse_damages` & `shop_damages`...\n";
DB::query("CREATE TABLE IF NOT EXISTS `warehouse_damages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `damage_no` VARCHAR(50) NOT NULL UNIQUE,
    `warehouse_id` INT NOT NULL DEFAULT 1,
    `variant_id` INT NOT NULL,
    `qty` DECIMAL(12,2) NOT NULL,
    `location` VARCHAR(100) NOT NULL DEFAULT 'Warehouse Floor',
    `damage_type` VARCHAR(50) NOT NULL DEFAULT 'PHYSICAL_DAMAGE',
    `severity` ENUM('MINOR', 'MODERATE', 'SEVERE', 'SCRAP') NOT NULL DEFAULT 'MODERATE',
    `status` ENUM('LOGGED', 'INSPECTED', 'RESOLVED', 'CANCELLED') NOT NULL DEFAULT 'LOGGED',
    `resolution_action` ENUM('SCRAP', 'WRITE_OFF', 'SALVAGE', 'REPAIR', 'REWORK', 'RETURN_TO_SUPPLIER', 'SUPPLIER_REPLACEMENT') NULL,
    `resolution_reason` VARCHAR(255) NULL,
    `resolution_notes` TEXT NULL,
    `loss_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `photo_url` VARCHAR(255) NULL,
    `replacement_variant_id` INT NULL,
    `replacement_qr_code` VARCHAR(100) NULL,
    `replacement_inwarded_at` DATETIME NULL,
    `resolved_by` INT NULL,
    `resolved_at` DATETIME NULL,
    `cancelled_by` INT NULL,
    `cancelled_at` DATETIME NULL,
    `cancel_reason` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_whd_concern` (`concern_id`),
    INDEX `idx_whd_status` (`status`),
    INDEX `idx_whd_variant` (`variant_id`),
    INDEX `idx_whd_repl_qr` (`replacement_qr_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

DB::query("CREATE TABLE IF NOT EXISTS `shop_damages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `damage_no` VARCHAR(50) NOT NULL UNIQUE,
    `shop_id` INT NOT NULL DEFAULT 1,
    `variant_id` INT NOT NULL,
    `qty` DECIMAL(12,2) NOT NULL,
    `location` VARCHAR(100) NOT NULL DEFAULT 'Retail Shop',
    `damage_type` VARCHAR(50) NOT NULL DEFAULT 'CUSTOMER_DAMAGE',
    `severity` ENUM('MINOR', 'MODERATE', 'SEVERE', 'SCRAP') NOT NULL DEFAULT 'MODERATE',
    `status` ENUM('LOGGED', 'INSPECTED', 'RESOLVED', 'CANCELLED') NOT NULL DEFAULT 'LOGGED',
    `resolution_action` ENUM('SCRAP', 'WRITE_OFF', 'SALVAGE', 'REPAIR', 'REWORK', 'RETURN_TO_WAREHOUSE', 'SUPPLIER_REPLACEMENT') NULL,
    `resolution_reason` VARCHAR(255) NULL,
    `resolution_notes` TEXT NULL,
    `loss_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `photo_url` VARCHAR(255) NULL,
    `replacement_variant_id` INT NULL,
    `replacement_qr_code` VARCHAR(100) NULL,
    `replacement_inwarded_at` DATETIME NULL,
    `resolved_by` INT NULL,
    `resolved_at` DATETIME NULL,
    `cancelled_by` INT NULL,
    `cancelled_at` DATETIME NULL,
    `cancel_reason` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_shd_concern` (`concern_id`),
    INDEX `idx_shd_status` (`status`),
    INDEX `idx_shd_variant` (`variant_id`),
    INDEX `idx_shd_repl_qr` (`replacement_qr_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "  + `warehouse_damages` and `shop_damages` tables verified\n";

// 15. PRODUCTION_RETURNS & EXCHANGES
echo "\n15. Checking `production_returns` & exchange tables...\n";
DB::query("CREATE TABLE IF NOT EXISTS `production_returns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `return_no` VARCHAR(50) NOT NULL UNIQUE,
    `type` ENUM('RETURN', 'EXCHANGE', 'CREDIT_NOTE') NOT NULL DEFAULT 'RETURN',
    `status` ENUM('RECEIVED', 'INSPECTED', 'RESTOCKED', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'RECEIVED',
    `customer_id` INT NULL,
    `sales_invoice_id` INT NULL,
    `return_date` DATE NOT NULL,
    `action_type` ENUM('RESTOCKED', 'SENT_TO_REWORK', 'SCRAPPED') NOT NULL DEFAULT 'RESTOCKED',
    `exchange_of_return_id` INT NULL,
    `settlement_mode` VARCHAR(50) NULL DEFAULT 'Credit Note',
    `settlement_reference` VARCHAR(100) NULL,
    `price_difference` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `refund_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `extra_charge` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `reason` TEXT NULL,
    `photo_url` VARCHAR(255) NULL,
    `inspected_at` DATETIME NULL,
    `inspected_by` INT NULL,
    `cancelled_at` DATETIME NULL,
    `cancelled_by` INT NULL,
    `cancel_reason` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pr_concern` (`concern_id`),
    INDEX `idx_pr_status` (`status`),
    INDEX `idx_pr_invoice` (`sales_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

DB::query("CREATE TABLE IF NOT EXISTS `production_return_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `return_id` INT NOT NULL,
    `variant_id` INT NOT NULL,
    `tag_code` VARCHAR(50) NULL,
    `qty` DECIMAL(12,2) NOT NULL,
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `condition_state` VARCHAR(50) NOT NULL DEFAULT 'Good',
    `action` ENUM('RESTOCK', 'REWORK', 'SCRAP') NOT NULL DEFAULT 'RESTOCK',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pri_return` (`return_id`),
    INDEX `idx_pri_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

DB::query("CREATE TABLE IF NOT EXISTS `production_return_exchange_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `return_id` INT NOT NULL,
    `direction` ENUM('IN', 'OUT') NOT NULL,
    `variant_id` INT NOT NULL,
    `tag_code` VARCHAR(50) NULL,
    `qty` DECIMAL(12,2) NOT NULL,
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `condition_state` VARCHAR(50) NULL DEFAULT 'Good',
    `restock_action` ENUM('RESTOCK', 'REWORK', 'SCRAP') NULL DEFAULT 'RESTOCK',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_prei_return` (`return_id`),
    INDEX `idx_prei_variant` (`variant_id`),
    INDEX `idx_prei_direction` (`direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

DB::query("CREATE TABLE IF NOT EXISTS `return_exchange_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `return_id` INT NOT NULL,
    `direction` ENUM('IN', 'OUT') NOT NULL,
    `variant_id` INT NOT NULL,
    `tag_code` VARCHAR(50) NULL,
    `qty` DECIMAL(12,2) NOT NULL,
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `condition_state` VARCHAR(50) NULL DEFAULT 'Good',
    `restock_action` ENUM('RESTOCK', 'REWORK', 'SCRAP') NULL DEFAULT 'RESTOCK',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_rei_return` (`return_id`),
    INDEX `idx_rei_variant` (`variant_id`),
    INDEX `idx_rei_direction` (`direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "  + `production_returns` and exchange item tables verified\n";

// 16. PRODUCTION_STOCK_REQUESTS
echo "\n16. Checking `production_stock_requests` & audit tables...\n";
DB::query("CREATE TABLE IF NOT EXISTS `production_stock_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `source_concern_id` INT NULL,
    `destination_concern_id` INT NULL,
    `request_no` VARCHAR(50) NOT NULL UNIQUE,
    `request_type` VARCHAR(50) NOT NULL DEFAULT 'INTER_MODULE',
    `from_location_type` VARCHAR(50) NOT NULL DEFAULT 'WAREHOUSE',
    `from_location_id` INT NULL,
    `from_module` VARCHAR(50) NULL,
    `from_unit_id` INT NULL,
    `to_location_type` VARCHAR(50) NOT NULL DEFAULT 'SHOP',
    `to_location_id` INT NULL,
    `to_module` VARCHAR(50) NULL,
    `to_unit_id` INT NULL,
    `status` ENUM('PENDING', 'APPROVED', 'DISPATCHED', 'IN_TRANSIT', 'RECEIVED', 'REJECTED', 'CANCELLED', 'SHORTAGE') NOT NULL DEFAULT 'PENDING',
    `priority` VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
    `requested_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `dispatched_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `in_transit_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `received_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `shortage_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `expected_date` DATE NULL,
    `dispatch_challan_no` VARCHAR(50) NULL,
    `notes` TEXT NULL,
    `dispatched_at` DATETIME NULL,
    `dispatched_by` INT NULL,
    `received_at` DATETIME NULL,
    `received_by` INT NULL,
    `cancelled_at` DATETIME NULL,
    `cancelled_by` INT NULL,
    `cancel_reason` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_psr_concern` (`concern_id`),
    INDEX `idx_psr_status` (`status`),
    INDEX `idx_psr_from` (`from_location_type`, `from_location_id`),
    INDEX `idx_psr_to` (`to_location_type`, `to_location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$chkSrc = DB::fetchAll("SHOW COLUMNS FROM `production_stock_requests` LIKE 'source_concern_id'");
if (empty($chkSrc)) {
    DB::query("ALTER TABLE `production_stock_requests` ADD COLUMN `source_concern_id` INT NULL AFTER `concern_id`");
}
$chkDest = DB::fetchAll("SHOW COLUMNS FROM `production_stock_requests` LIKE 'destination_concern_id'");
if (empty($chkDest)) {
    DB::query("ALTER TABLE `production_stock_requests` ADD COLUMN `destination_concern_id` INT NULL AFTER `source_concern_id`");
}

DB::query("CREATE TABLE IF NOT EXISTS `production_stock_request_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT NOT NULL,
    `variant_id` INT NOT NULL,
    `requested_qty` DECIMAL(12,2) NOT NULL,
    `dispatched_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `in_transit_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `received_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `shortage_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_psri_request` (`request_id`),
    INDEX `idx_psri_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

DB::query("CREATE TABLE IF NOT EXISTS `production_stock_request_audits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `item_id` INT NULL,
    `qty` DECIMAL(12,2) NULL,
    `actor_id` INT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_psra_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "  + `production_stock_requests` and audit tables verified\n";

// 17. BATCHES
echo "\n17. Checking `batches` status...\n";
DB::query("ALTER TABLE `batches` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'active'");
echo "  + Ensured flexible `status` on `batches`\n";

// 18. POS PRODUCTION BATCH TRACEABILITY
echo "\n18. Checking POS production batch traceability...\n";
$salesItemCols = array_column(DB::fetchAll("SHOW COLUMNS FROM `sales_invoice_items`"), 'Field');
if (!in_array('production_batch_id', $salesItemCols, true)) {
    DB::query("ALTER TABLE `sales_invoice_items` ADD COLUMN `production_batch_id` INT NULL AFTER `variant_id`");
    echo "  + Added `production_batch_id` to `sales_invoice_items`\n";
} else {
    echo "  . `production_batch_id` already exists\n";
}
$salesItemIndexes = array_column(DB::fetchAll("SHOW INDEX FROM `sales_invoice_items`"), 'Key_name');
if (!in_array('idx_sii_production_batch', $salesItemIndexes, true)) {
    DB::query("ALTER TABLE `sales_invoice_items` ADD INDEX `idx_sii_production_batch` (`production_batch_id`)");
}
$batchForeignKey = DB::fetchValue(
    "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales_invoice_items'
       AND CONSTRAINT_NAME = 'fk_sii_batch' LIMIT 1"
);
if (!$batchForeignKey) {
    DB::query("ALTER TABLE `sales_invoice_items`
               ADD CONSTRAINT `fk_sii_batch` FOREIGN KEY (`production_batch_id`)
               REFERENCES `batches` (`id`) ON DELETE SET NULL");
}
echo "  + Production batch traceability is ready\n";

// 19. POS DIRECT BILL AND COMPANY INVOICE DETAILS
echo "\n19. Checking POS direct bill and company invoice fields...\n";
$invoiceCols = array_column(DB::fetchAll("SHOW COLUMNS FROM `sales_invoices`"), 'Field');
if (!in_array('shipping_amount', $invoiceCols, true)) {
    DB::query("ALTER TABLE `sales_invoices` ADD COLUMN `shipping_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `net_amount`");
    $invoiceCols[] = 'shipping_amount';
    echo "  + Added `shipping_amount` to `sales_invoices`\n";
}
if (!in_array('paid_amount', $invoiceCols, true)) {
    DB::query("ALTER TABLE `sales_invoices` ADD COLUMN `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `payment_status`");
    $invoiceCols[] = 'paid_amount';
    echo "  + Added `paid_amount` to `sales_invoices`\n";
    DB::query("UPDATE `sales_invoices` SET `paid_amount` = `net_amount` WHERE `payment_status` = 'paid' AND `paid_amount` = 0 AND `net_amount` > 0");
}
$companyInvoiceColumns = [
    'is_direct_bill' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `paid_amount`',
    'billing_company_name' => 'VARCHAR(150) NULL AFTER `is_direct_bill`',
    'billing_company_address' => 'VARCHAR(255) NULL AFTER `billing_company_name`',
    'billing_company_phone' => 'VARCHAR(30) NULL AFTER `billing_company_address`',
    'billing_company_email' => 'VARCHAR(150) NULL AFTER `billing_company_phone`',
    'billing_company_gstin' => 'VARCHAR(15) NULL AFTER `billing_company_email`',
];
foreach ($companyInvoiceColumns as $column => $definition) {
    if (!in_array($column, $invoiceCols, true)) {
        DB::query("ALTER TABLE `sales_invoices` ADD COLUMN `{$column}` {$definition}");
        $invoiceCols[] = $column;
        echo "  + Added `{$column}` to `sales_invoices`\n";
    }
}
echo "  + POS direct bill and company invoice fields are ready\n";

// 20. Align nullability and defaults with schema.sql
echo "\n20. Aligning required column constraints...\n";
DB::query("UPDATE `inwards` SET `status` = 'VERIFIED' WHERE `status` IS NULL");
DB::query("ALTER TABLE `inwards` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'VERIFIED'");

DB::query("UPDATE `inward_items` SET `accepted_qty` = 0.00 WHERE `accepted_qty` IS NULL");
DB::query("ALTER TABLE `inward_items` MODIFY COLUMN `accepted_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
DB::query("UPDATE `inward_items` SET `rejected_qty` = 0.00 WHERE `rejected_qty` IS NULL");
DB::query("ALTER TABLE `inward_items` MODIFY COLUMN `rejected_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00");

DB::query("UPDATE `qc_inspections` SET `rework_qty` = 0.00 WHERE `rework_qty` IS NULL");
DB::query("ALTER TABLE `qc_inspections` MODIFY COLUMN `rework_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
DB::query("UPDATE `qc_inspections` SET `status` = 'COMPLETED' WHERE `status` IS NULL");
DB::query("ALTER TABLE `qc_inspections` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'COMPLETED'");

foreach ([
    'can_view' => 1,
    'can_add' => 0,
    'can_edit' => 0,
    'can_delete' => 0,
] as $permission => $default) {
    DB::query("UPDATE `user_permissions` SET `{$permission}` = {$default} WHERE `{$permission}` IS NULL");
    DB::query("ALTER TABLE `user_permissions` MODIFY COLUMN `{$permission}` TINYINT(1) NOT NULL DEFAULT {$default}");
}
echo "  + Required columns and defaults aligned with schema.sql\n";

// 21. VARIANTS (cost_price, is_complementary)
echo "\n21. Checking `variants`...\n";
$varCols = array_column(DB::fetchAll("SHOW COLUMNS FROM `variants`"), 'Field');
if (!in_array('cost_price', $varCols, true)) {
    DB::query("ALTER TABLE `variants` ADD COLUMN `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `attributes`");
    echo "  + Added `cost_price` to `variants`\n";
} else {
    echo "  . `cost_price` already exists\n";
}
if (!in_array('is_complementary', $varCols, true)) {
    DB::query("ALTER TABLE `variants` ADD COLUMN `is_complementary` TINYINT(1) NOT NULL DEFAULT 0 AFTER `mrp`");
    echo "  + Added `is_complementary` to `variants`\n";
} else {
    echo "  . `is_complementary` already exists\n";
}

echo "\n========================================================\n";
echo "SUCCESS: All tables in local DB migrated successfully!\n";
echo "========================================================\n";
