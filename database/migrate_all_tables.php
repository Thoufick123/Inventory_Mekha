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

// 10. BATCHES
echo "\n10. Checking `batches` status...\n";
DB::query("ALTER TABLE `batches` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'active'");
echo "  + Ensured flexible `status` on `batches`\n";

echo "\n11. Checking POS production batch traceability...\n";
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

echo "\n========================================================\n";
echo "SUCCESS: All tables in local DB migrated successfully!\n";
echo "========================================================\n";
