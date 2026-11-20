-- ==============================================================================
-- INVENTO / GRM INVENTORY: COMPREHENSIVE LIVE DATABASE SCHEMA & MIGRATION UPDATE
-- Compatible with Hostinger / cPanel / MariaDB 10.x / MySQL 8.0+
-- Safe & Non-Destructive: Preserves existing data while adding all missing tables & columns
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ------------------------------------------------------------------------------
-- 1. ENSURE CRITICAL MULTI-TENANT & LOGISTICS TABLES
-- ------------------------------------------------------------------------------

-- Concerns Table
CREATE TABLE IF NOT EXISTS `concerns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `address` TEXT NULL,
    `gstin` VARCHAR(30) NULL,
    `phone` VARCHAR(30) NULL,
    `email` VARCHAR(100) NULL,
    `logo` VARCHAR(255) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Production Units Table (Workshops & Manufacturing Plants)
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

-- Production Outward Batches (Consolidation Mapping)
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

-- HSN / SAC Masters
CREATE TABLE IF NOT EXISTS `hsn_masters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `code_type` ENUM('hsn', 'sac') NOT NULL DEFAULT 'hsn',
    `hsn_code` VARCHAR(20) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'fabrics',
    `gst_rate` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    `rate_note` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_hsn_concern` (`concern_id`),
    INDEX `idx_hsn_code` (`hsn_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Staff Table
CREATE TABLE IF NOT EXISTS `staff` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `user_id` INT NULL,
    `staff_code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `email` VARCHAR(100) NULL,
    `password_hash` VARCHAR(255) NULL,
    `designation` VARCHAR(100) NULL,
    `department` VARCHAR(100) NULL,
    `salary_type` ENUM('monthly', 'daily', 'piece_rate') NOT NULL DEFAULT 'monthly',
    `base_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `address` TEXT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `joining_date` DATE NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_staff_concern` (`concern_id`),
    INDEX `idx_staff_code` (`staff_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cashier Shifts Table
CREATE TABLE IF NOT EXISTS `cashier_shifts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `cashier_name` VARCHAR(100) NOT NULL,
    `shift_no` VARCHAR(50) NOT NULL,
    `opening_notes` TEXT NULL,
    `starting_float` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `is_float_modified` TINYINT(1) NOT NULL DEFAULT 0,
    `denominations_json` JSON NULL,
    `closing_notes` TEXT NULL,
    `closing_float` DECIMAL(12,2) NULL,
    `closing_denominations_json` JSON NULL,
    `expected_closing_float` DECIMAL(12,2) NULL,
    `variance` DECIMAL(12,2) NULL,
    `variance_reason` VARCHAR(255) NULL,
    `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    `opened_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `closed_at` TIMESTAMP NULL,
    INDEX `idx_shift_concern` (`concern_id`),
    INDEX `idx_shift_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 2. SAFE COLUMN ADDITION VIA STORED PROCEDURE
-- ------------------------------------------------------------------------------

DROP PROCEDURE IF EXISTS `AddColSafe`;
DELIMITER $$
CREATE PROCEDURE `AddColSafe`(
    IN in_table VARCHAR(64),
    IN in_col VARCHAR(64),
    IN in_def TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_schema = DATABASE() 
          AND table_name = in_table 
          AND column_name = in_col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', in_table, '` ADD COLUMN `', in_col, '` ', in_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- Align `batches` table
CALL AddColSafe('batches', 'production_unit_id', 'INT NULL AFTER `concern_id`');
CALL AddColSafe('batches', 'program_id', 'INT NULL AFTER `variant_id`');
CALL AddColSafe('batches', 'cutting_batch_id', 'INT NULL AFTER `program_id`');
CALL AddColSafe('batches', 'stitching_batch_id', 'INT NULL AFTER `cutting_batch_id`');
CALL AddColSafe('batches', 'qc_batch_id', 'INT NULL AFTER `stitching_batch_id`');
CALL AddColSafe('batches', 'additional_qc_batch_ids', 'TEXT NULL AFTER `qc_batch_id`');
CALL AddColSafe('batches', 'total_boxes', 'INT DEFAULT 1 AFTER `batch_qty`');
CALL AddColSafe('batches', 'warehouse_id', 'INT NULL AFTER `total_boxes`');
CALL AddColSafe('batches', 'warehouse_name', 'VARCHAR(150) NULL AFTER `warehouse_id`');
CALL AddColSafe('batches', 'packed_by_name', "VARCHAR(150) DEFAULT 'System Administrator' AFTER `warehouse_name`");
CALL AddColSafe('batches', 'rework_included', 'TINYINT(1) DEFAULT 0 AFTER `packed_by_name`');
CALL AddColSafe('batches', 'qr_codes', 'LONGTEXT NULL AFTER `rework_included`');
CALL AddColSafe('batches', 'taxable_value', 'DECIMAL(12,2) DEFAULT 0.00 AFTER `qr_codes`');
CALL AddColSafe('batches', 'gst_amount', 'DECIMAL(12,2) DEFAULT 0.00 AFTER `taxable_value`');
CALL AddColSafe('batches', 'total_amount', 'DECIMAL(12,2) DEFAULT 0.00 AFTER `gst_amount`');
CALL AddColSafe('batches', 'bill_no', 'VARCHAR(100) NULL AFTER `total_amount`');
CALL AddColSafe('batches', 'fabric_details', "VARCHAR(255) DEFAULT 'GREEN / BIRDS' AFTER `bill_no`");
CALL AddColSafe('batches', 'approved_by', 'INT NULL AFTER `status`');
CALL AddColSafe('batches', 'approved_at', 'DATETIME NULL AFTER `approved_by`');

-- Align `outwards` table
CALL AddColSafe('outwards', 'production_unit_id', 'INT NULL AFTER `concern_id`');
CALL AddColSafe('outwards', 'transfer_type', "ENUM('customer', 'concern') NOT NULL DEFAULT 'customer' AFTER `customer_id`");
CALL AddColSafe('outwards', 'destination_concern_id', 'INT NULL AFTER `transfer_type`');
CALL AddColSafe('outwards', 'warehouse_id', 'INT NULL AFTER `location`');
CALL AddColSafe('outwards', 'warehouse_name', "VARCHAR(150) DEFAULT 'wareouse (WH-01)' AFTER `warehouse_id`");
CALL AddColSafe('outwards', 'total_boxes', 'INT DEFAULT 1 AFTER `warehouse_name`');
CALL AddColSafe('outwards', 'vehicle_number', 'VARCHAR(100) NULL AFTER `total_boxes`');
CALL AddColSafe('outwards', 'driver_name', 'VARCHAR(150) NULL AFTER `vehicle_number`');
CALL AddColSafe('outwards', 'driver_contact', 'VARCHAR(50) NULL AFTER `driver_name`');
CALL AddColSafe('outwards', 'transporter_name', 'VARCHAR(150) NULL AFTER `driver_contact`');
CALL AddColSafe('outwards', 'lr_number', 'VARCHAR(100) NULL AFTER `transporter_name`');
CALL AddColSafe('outwards', 'gate_pass_no', 'VARCHAR(50) NULL AFTER `lr_number`');
CALL AddColSafe('outwards', 'dispatched_by_name', "VARCHAR(150) DEFAULT 'System Administrator' AFTER `gate_pass_no`");
CALL AddColSafe('outwards', 'notes', 'TEXT NULL AFTER `dispatched_by_name`');
CALL AddColSafe('outwards', 'dispatched_at', 'DATETIME NULL AFTER `status`');
CALL AddColSafe('outwards', 'received_at', 'DATETIME NULL AFTER `dispatched_at`');

-- Align `inwards` table
CALL AddColSafe('inwards', 'production_unit_id', 'INT NULL AFTER `concern_id`');
CALL AddColSafe('inwards', 'grn_number', 'VARCHAR(50) NULL AFTER `inward_no`');
CALL AddColSafe('inwards', 'po_id', 'INT NULL AFTER `grn_number`');
CALL AddColSafe('inwards', 'outward_id', 'INT NULL AFTER `purchase_bill_id`');
CALL AddColSafe('inwards', 'from_concern_id', 'INT NULL AFTER `outward_id`');
CALL AddColSafe('inwards', 'inward_type', "ENUM('purchase', 'transfer') NOT NULL DEFAULT 'purchase' AFTER `from_concern_id`");
CALL AddColSafe('inwards', 'warehouse_id', 'INT NULL AFTER `location`');
CALL AddColSafe('inwards', 'vehicle_number', 'VARCHAR(50) NULL AFTER `warehouse_id`');
CALL AddColSafe('inwards', 'received_by', 'INT NULL AFTER `vehicle_number`');

-- Align `inward_items` table
CALL AddColSafe('inward_items', 'accepted_qty', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `qty`');
CALL AddColSafe('inward_items', 'rejected_qty', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `accepted_qty`');
CALL AddColSafe('inward_items', 'rejection_reason', 'VARCHAR(255) NULL AFTER `rejected_qty`');

-- Align `program_rolls` table
CALL AddColSafe('program_rolls', 'roll_weight', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `allocated_qty`');
CALL AddColSafe('program_rolls', 'roll_price', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `roll_weight`');

-- Align `purchase_bills` table
CALL AddColSafe('purchase_bills', 'inward_status', "ENUM('pending', 'completed') NOT NULL DEFAULT 'pending'");
CALL AddColSafe('purchase_bills', 'inward_at', 'DATETIME NULL');
CALL AddColSafe('purchase_bills', 'attachment_paths', 'JSON NULL');
CALL AddColSafe('purchase_bills', 'fabric_description', 'VARCHAR(255) NULL');

-- Align `purchase_bill_items` table
CALL AddColSafe('purchase_bill_items', 'inward_status', "ENUM('pending', 'completed') NOT NULL DEFAULT 'pending'");
CALL AddColSafe('purchase_bill_items', 'inward_at', 'DATETIME NULL');
CALL AddColSafe('purchase_bill_items', 'category_name', 'VARCHAR(150) NULL');
CALL AddColSafe('purchase_bill_items', 'product_type', 'VARCHAR(100) NULL');
CALL AddColSafe('purchase_bill_items', 'mrp', 'DECIMAL(12,2) DEFAULT 0.00');
CALL AddColSafe('purchase_bill_items', 'selling_price', 'DECIMAL(12,2) DEFAULT 0.00');

-- Align `stitching_items` table
CALL AddColSafe('stitching_items', 'inward_status', "VARCHAR(30) DEFAULT 'pending'");
CALL AddColSafe('stitching_items', 'inward_at', 'DATETIME NULL');
CALL AddColSafe('stitching_items', 'per_pc_weight', 'DECIMAL(10,3) DEFAULT 0.000');
CALL AddColSafe('stitching_items', 'total_weight', 'DECIMAL(10,3) DEFAULT 0.000');
CALL AddColSafe('stitching_items', 'base_price', 'DECIMAL(12,2) DEFAULT 0.00');
CALL AddColSafe('stitching_items', 'discount_price', 'DECIMAL(12,2) DEFAULT 0.00');

-- Align `stitching_logs` table
CALL AddColSafe('stitching_logs', 'inward_status', "VARCHAR(30) DEFAULT 'pending'");
CALL AddColSafe('stitching_logs', 'sold_wastage_qty', 'DECIMAL(12,2) DEFAULT 0.00');
CALL AddColSafe('stitching_logs', 'produced_variant_id', 'INT NULL');

-- Align `qc_inspections` table
CALL AddColSafe('qc_inspections', 'batch_no', 'VARCHAR(50) NULL AFTER `qc_no`');
CALL AddColSafe('qc_inspections', 'cutting_batch_id', 'INT NULL AFTER `variant_id`');
CALL AddColSafe('qc_inspections', 'stitching_batch_id', 'INT NULL AFTER `cutting_batch_id`');
CALL AddColSafe('qc_inspections', 'rework_qty', 'DECIMAL(12,2) DEFAULT 0.00 AFTER `passed_qty`');
CALL AddColSafe('qc_inspections', 'rework_notes', 'TEXT NULL AFTER `defect_notes`');

-- Align `categories` & `catalogs` table
CALL AddColSafe('categories', 'default_gst_rate', 'DECIMAL(5,2) NULL DEFAULT NULL');
CALL AddColSafe('categories', 'default_hsn', 'VARCHAR(20) NULL DEFAULT NULL');
CALL AddColSafe('catalogs', 'gst_rate', 'DECIMAL(5,2) NULL DEFAULT NULL');

-- Flexible Statuses on outwards and batches
ALTER TABLE `outwards` MODIFY COLUMN `customer_id` INT NULL;
ALTER TABLE `outwards` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'DISPATCHED';
ALTER TABLE `batches` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'active';

DROP PROCEDURE IF EXISTS `AddColSafe`;

-- ------------------------------------------------------------------------------
-- 3. ENSURE BASELINE SEED DATA (Matches Live Visuals)
-- ------------------------------------------------------------------------------

-- Ensure primary concern GRM Garments
INSERT IGNORE INTO `concerns` (`id`, `code`, `name`, `status`) 
VALUES (1, 'CON-GRM', 'GRM Garments Pvt Ltd', 'active'),
       (2, 'CON-APEX', 'Apex Fashion Mills', 'active');

-- Ensure Production Unit PU-01 Kadampadi
INSERT IGNORE INTO `production_units` (`id`, `concern_id`, `code`, `name`, `location`, `manager_name`, `capacity_per_day`, `is_active`)
VALUES (1, 1, 'PU-01', 'Kadampadi', 'Kadampadi', 'Vanaja (9943557998)', 500, 1);

-- Ensure Category & Variant KIDS - BABY - DRESS
INSERT IGNORE INTO `categories` (`id`, `concern_id`, `name`, `code`, `status`)
VALUES (1, 1, 'Kids Wear', 'CAT-KIDS', 'active');

INSERT IGNORE INTO `variants` (`id`, `concern_id`, `sku_code`, `name`, `category_id`, `size`, `price`, `mrp`, `status`)
VALUES (27, 1, 'GRM-STY-0001-XL', 'KIDS - BABY - DRESS', 1, 'XL', 700.00, 1000.00, 'active');

-- Ensure Finished Batches BAT-20261113-0001 and BAT-20261113-0002
INSERT IGNORE INTO `batches` (
    `id`, `concern_id`, `production_unit_id`, `batch_no`, `variant_id`, 
    `batch_qty`, `total_boxes`, `warehouse_name`, `packed_by_name`, `fabric_details`, `status`, `created_at`
) VALUES 
(1, 1, 1, 'BAT-20261113-0001', 27, 490.00, 1, 'wareouse', 'System Administrator', 'GREEN / BIRDS', 'RECEIVED', '2026-11-13 13:06:00'),
(2, 1, 1, 'BAT-20261113-0002', 27, 70.00, 1, 'wareouse', 'System Administrator', 'GREEN / BIRDS', 'PREPARING', '2026-11-13 13:06:00');

-- Ensure Outward Gate Pass OUT-20261113-0001
INSERT IGNORE INTO `outwards` (
    `id`, `concern_id`, `production_unit_id`, `outward_no`, `gate_pass_no`, `destination_concern_id`,
    `outward_date`, `location`, `warehouse_name`, `total_boxes`, `vehicle_number`, `driver_name`, `driver_contact`,
    `dispatched_by_name`, `status`, `dispatched_at`, `created_at`
) VALUES (
    1, 1, 1, 'OUT-20261113-0001', 'OUT-20261113-0001', 1,
    '2026-11-13', 'Main Store', 'wareouse (WH-01)', 1, 'rtt31232', 'Vanaja', '9943557998',
    'System Administrator', 'DISPATCHED', '2026-11-13 13:06:00', '2026-11-13 13:06:00'
);

-- Link Outward to Batch
INSERT IGNORE INTO `production_outward_batches` (`id`, `concern_id`, `outward_id`, `batch_id`, `cartons_count`, `pieces_count`)
VALUES (1, 1, 1, 1, 1, 490.00);

INSERT IGNORE INTO `outward_items` (`id`, `concern_id`, `outward_id`, `variant_id`, `qty`)
VALUES (1, 1, 1, 27, 490.00);

-- Ensure Inward INW-20261113-0001 (Partial: 3 Credited, 487 Pending)
INSERT IGNORE INTO `inwards` (
    `id`, `concern_id`, `production_unit_id`, `outward_id`, `inward_no`, `grn_number`,
    `inward_date`, `location`, `vehicle_number`, `status`, `created_at`
) VALUES (
    1, 1, 1, 1, 'INW-20261113-0001', 'GRN-20261113-0001',
    '2026-11-13', 'Main Store', 'rtt31232', 'PARTIAL', '2026-11-13 13:06:00'
);

INSERT IGNORE INTO `inward_items` (`id`, `concern_id`, `inward_id`, `variant_id`, `qty`, `accepted_qty`, `rejected_qty`)
VALUES (1, 1, 1, 27, 490.00, 3.00, 0.00);

SET FOREIGN_KEY_CHECKS = 1;
