-- ==============================================================================
-- INVENTO / GRM INVENTORY: COMPREHENSIVE LIVE DATABASE SCHEMA & MIGRATION UPDATE
-- Compatible with Hostinger / cPanel / MariaDB 10.x / MySQL 8.0+
-- Safe & Non-Destructive: Preserves existing data while adding all missing tables & columns
-- Prerequisite: import schema.sql first if any core tables (for example `batches`) are missing.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

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
    `concern_id` INT NULL,
    `code_type` ENUM('HSN', 'SAC') NOT NULL DEFAULT 'HSN',
    `hsn_code` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL,
    `category` VARCHAR(100) NULL,
    `gst_rate` DECIMAL(5,2) NULL DEFAULT NULL,
    `rate_note` VARCHAR(500) NULL,
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

-- Expense Categories Table
CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_concern_exp_cat` (`concern_id`, `name`),
    INDEX `idx_exp_cat_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Document Sequences Table
CREATE TABLE IF NOT EXISTS `doc_sequences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `doc_type` VARCHAR(50) NOT NULL,
    `doc_year` INT NOT NULL,
    `prefix` VARCHAR(20) NOT NULL,
    `current_seq` INT NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_concern_doc_year` (`concern_id`, `doc_type`, `doc_year`),
    INDEX `idx_ds_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Warehouse Damages Table
CREATE TABLE IF NOT EXISTS `warehouse_damages` (
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
    INDEX `idx_whd_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shop Damages Table
CREATE TABLE IF NOT EXISTS `shop_damages` (
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
    INDEX `idx_shd_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Production Returns Master Table
CREATE TABLE IF NOT EXISTS `production_returns` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Production Return Items Table
CREATE TABLE IF NOT EXISTS `production_return_items` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Production Return Exchange Items Table
CREATE TABLE IF NOT EXISTS `production_return_exchange_items` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Return Exchange Items (Returns & Exchanges Parity)
CREATE TABLE IF NOT EXISTS `return_exchange_items` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Production Stock Requests Table
CREATE TABLE IF NOT EXISTS `production_stock_requests` (
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
    INDEX `idx_psr_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Production Stock Request Items Table
CREATE TABLE IF NOT EXISTS `production_stock_request_items` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Production Stock Request Audits Table
CREATE TABLE IF NOT EXISTS `production_stock_request_audits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `item_id` INT NULL,
    `qty` DECIMAL(12,2) NULL,
    `actor_id` INT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_psra_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------
-- 2. SAFE COLUMN ADDITION VIA STORED PROCEDURE
-- ------------------------------------------------------------------------------

DROP PROCEDURE IF EXISTS `AddColSafe`;

-- Register Product Adding module and enable it for existing concerns
INSERT INTO `modules` (`code`, `name`, `icon`, `sort_order`, `group_name`, `is_super_admin_only`)
SELECT 'product_adding', 'Product Adding', 'bi-plus-square-dotted', 25, 'Purchase', 0
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code` = 'product_adding');
UPDATE `modules`
SET `name` = 'Product Adding',
    `icon` = 'bi-plus-square-dotted',
    `sort_order` = 25,
    `group_name` = 'Purchase',
    `is_super_admin_only` = 0
WHERE `code` = 'product_adding';
INSERT IGNORE INTO `concern_modules` (`concern_id`, `module_id`)
SELECT c.id, m.id
FROM `concerns` c
JOIN `modules` m ON m.code = 'product_adding';
DELIMITER $$
CREATE PROCEDURE `AddColSafe`(
    IN in_table VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    IN in_col VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    IN in_def TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE CONVERT(table_schema USING utf8mb4) COLLATE utf8mb4_unicode_ci =
              CONVERT(DATABASE() USING utf8mb4) COLLATE utf8mb4_unicode_ci
          AND CONVERT(table_name USING utf8mb4) COLLATE utf8mb4_unicode_ci =
              CONVERT(in_table USING utf8mb4) COLLATE utf8mb4_unicode_ci
          AND CONVERT(column_name USING utf8mb4) COLLATE utf8mb4_unicode_ci =
              CONVERT(in_col USING utf8mb4) COLLATE utf8mb4_unicode_ci
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', in_table, '` ADD COLUMN `', in_col, '` ', in_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- Align production stock request locations for existing installations
CALL AddColSafe('production_stock_requests', 'source_concern_id', 'INT NULL AFTER `concern_id`');
CALL AddColSafe('production_stock_requests', 'destination_concern_id', 'INT NULL AFTER `source_concern_id`');

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

-- Align `hsn_masters` with the application seed and allow global master records.
CALL AddColSafe('hsn_masters', 'concern_id', 'INT NULL AFTER `id`');
CALL AddColSafe('hsn_masters', 'code_type', "ENUM('HSN', 'SAC') NOT NULL DEFAULT 'HSN' AFTER `concern_id`");
CALL AddColSafe('hsn_masters', 'hsn_code', 'VARCHAR(50) NOT NULL AFTER `code_type`');
CALL AddColSafe('hsn_masters', 'description', 'VARCHAR(255) NULL AFTER `hsn_code`');
CALL AddColSafe('hsn_masters', 'category', 'VARCHAR(100) NULL AFTER `description`');
CALL AddColSafe('hsn_masters', 'gst_rate', 'DECIMAL(5,2) NULL DEFAULT NULL AFTER `category`');
CALL AddColSafe('hsn_masters', 'rate_note', 'VARCHAR(500) NULL AFTER `gst_rate`');
CALL AddColSafe('hsn_masters', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `rate_note`');
ALTER TABLE `hsn_masters`
    MODIFY COLUMN `concern_id` INT NULL,
    MODIFY COLUMN `code_type` ENUM('HSN', 'SAC') NOT NULL DEFAULT 'HSN',
    MODIFY COLUMN `hsn_code` VARCHAR(50) NOT NULL,
    MODIFY COLUMN `description` VARCHAR(255) NULL,
    MODIFY COLUMN `category` VARCHAR(100) NULL,
    MODIFY COLUMN `gst_rate` DECIMAL(5,2) NULL DEFAULT NULL,
    MODIFY COLUMN `rate_note` VARCHAR(500) NULL,
    MODIFY COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1;

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

-- Store cost independently from the existing selling price.
CALL AddColSafe('variants', 'cost_price', 'DECIMAL(12,2) NULL DEFAULT NULL AFTER `attributes`');
CALL AddColSafe('variants', 'is_complementary', 'TINYINT(1) NOT NULL DEFAULT 0');
UPDATE variants v
SET v.cost_price = COALESCE(
    (SELECT pbi.unit_price
     FROM purchase_bill_items pbi
     WHERE pbi.concern_id = v.concern_id
       AND (pbi.variant_id = v.id OR (v.catalog_id IS NOT NULL AND pbi.catalog_id = v.catalog_id))
       AND pbi.unit_price > 0
     ORDER BY CASE WHEN pbi.variant_id = v.id THEN 0 ELSE 1 END, pbi.id DESC
     LIMIT 1),
    (SELECT ii.unit_cost
     FROM inward_items ii
     WHERE ii.concern_id = v.concern_id AND ii.variant_id = v.id AND ii.unit_cost > 0
     ORDER BY ii.id DESC
     LIMIT 1),
    0
)
WHERE v.cost_price IS NULL;
ALTER TABLE `variants` MODIFY COLUMN `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00;

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
CALL AddColSafe('qc_inspections', 'program_id', 'INT NULL AFTER `variant_id`');
CALL AddColSafe('qc_inspections', 'cutting_batch_id', 'INT NULL AFTER `program_id`');
CALL AddColSafe('qc_inspections', 'stitching_batch_id', 'INT NULL AFTER `cutting_batch_id`');
CALL AddColSafe('qc_inspections', 'rework_qty', 'DECIMAL(12,2) DEFAULT 0.00 AFTER `passed_qty`');
CALL AddColSafe('qc_inspections', 'rework_notes', 'TEXT NULL AFTER `defect_notes`');

-- Align `categories` & `catalogs` table
CALL AddColSafe('categories', 'default_gst_rate', 'DECIMAL(5,2) NULL DEFAULT NULL');
CALL AddColSafe('categories', 'default_hsn', 'VARCHAR(20) NULL DEFAULT NULL');
CALL AddColSafe('catalogs', 'gst_rate', 'DECIMAL(5,2) NULL DEFAULT NULL');

-- Align `user_permissions` table (multi-tenant concern scoping)
CALL AddColSafe('user_permissions', 'concern_id', 'INT NULL AFTER `user_id`');

-- Align `concern_damages` table (lifecycle & resolution fields)
CALL AddColSafe('concern_damages', 'status', "ENUM('LOGGED', 'INSPECTED', 'RESOLVED', 'CANCELLED') NOT NULL DEFAULT 'LOGGED' AFTER `reason`");
CALL AddColSafe('concern_damages', 'severity', "ENUM('MINOR', 'MODERATE', 'SEVERE', 'SCRAP') NOT NULL DEFAULT 'MODERATE' AFTER `status`");
CALL AddColSafe('concern_damages', 'resolution_action', "ENUM('SCRAP', 'WRITE_OFF', 'SALVAGE', 'REPAIR', 'REWORK', 'RETURN_TO_SUPPLIER', 'SUPPLIER_REPLACEMENT') NULL AFTER `severity`");
CALL AddColSafe('concern_damages', 'resolution_reason', 'VARCHAR(255) NULL AFTER `resolution_action`');
CALL AddColSafe('concern_damages', 'resolution_notes', 'TEXT NULL AFTER `resolution_reason`');
CALL AddColSafe('concern_damages', 'loss_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `resolution_notes`');
CALL AddColSafe('concern_damages', 'photo_url', 'VARCHAR(255) NULL AFTER `loss_amount`');
CALL AddColSafe('concern_damages', 'replacement_variant_id', 'INT NULL AFTER `photo_url`');
CALL AddColSafe('concern_damages', 'replacement_qr_code', 'VARCHAR(100) NULL AFTER `replacement_variant_id`');
CALL AddColSafe('concern_damages', 'replacement_inwarded_at', 'DATETIME NULL AFTER `replacement_qr_code`');
CALL AddColSafe('concern_damages', 'resolved_by', 'INT NULL AFTER `replacement_inwarded_at`');
CALL AddColSafe('concern_damages', 'resolved_at', 'DATETIME NULL AFTER `resolved_by`');
CALL AddColSafe('concern_damages', 'cancelled_by', 'INT NULL AFTER `resolved_at`');
CALL AddColSafe('concern_damages', 'cancelled_at', 'DATETIME NULL AFTER `cancelled_by`');
CALL AddColSafe('concern_damages', 'cancel_reason', 'TEXT NULL AFTER `cancelled_at`');

-- POS shipping and partial payment
CALL AddColSafe('sales_invoices', 'shipping_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `net_amount`');
CALL AddColSafe('sales_invoices', 'paid_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `payment_status`');
CALL AddColSafe('sales_invoices', 'is_direct_bill', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `paid_amount`');
CALL AddColSafe('sales_invoices', 'billing_company_name', 'VARCHAR(150) NULL AFTER `is_direct_bill`');
CALL AddColSafe('sales_invoices', 'billing_company_address', 'VARCHAR(255) NULL AFTER `billing_company_name`');
CALL AddColSafe('sales_invoices', 'billing_company_phone', 'VARCHAR(30) NULL AFTER `billing_company_address`');
CALL AddColSafe('sales_invoices', 'billing_company_email', 'VARCHAR(150) NULL AFTER `billing_company_phone`');
CALL AddColSafe('sales_invoices', 'billing_company_gstin', 'VARCHAR(15) NULL AFTER `billing_company_email`');
ALTER TABLE `sales_invoices` MODIFY COLUMN `payment_method` VARCHAR(30) NOT NULL DEFAULT 'cash';
UPDATE `sales_invoices`
SET `paid_amount` = `net_amount`
WHERE `payment_status` = 'paid' AND `paid_amount` = 0 AND `net_amount` > 0;

-- Flexible Statuses on outwards and batches
ALTER TABLE `outwards` MODIFY COLUMN `customer_id` INT NULL;
ALTER TABLE `outwards` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'DISPATCHED';
ALTER TABLE `batches` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'active';

-- Align required columns and defaults with schema.sql
UPDATE `inwards` SET `status` = 'VERIFIED' WHERE `status` IS NULL;
ALTER TABLE `inwards` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'VERIFIED';

UPDATE `inward_items` SET `accepted_qty` = 0.00 WHERE `accepted_qty` IS NULL;
ALTER TABLE `inward_items` MODIFY COLUMN `accepted_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00;
UPDATE `inward_items` SET `rejected_qty` = 0.00 WHERE `rejected_qty` IS NULL;
ALTER TABLE `inward_items` MODIFY COLUMN `rejected_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00;

UPDATE `qc_inspections` SET `rework_qty` = 0.00 WHERE `rework_qty` IS NULL;
ALTER TABLE `qc_inspections` MODIFY COLUMN `rework_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00;
UPDATE `qc_inspections` SET `status` = 'COMPLETED' WHERE `status` IS NULL;
ALTER TABLE `qc_inspections` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'COMPLETED';

UPDATE `user_permissions` SET `can_view` = 1 WHERE `can_view` IS NULL;
ALTER TABLE `user_permissions` MODIFY COLUMN `can_view` TINYINT(1) NOT NULL DEFAULT 1;
UPDATE `user_permissions` SET `can_add` = 0 WHERE `can_add` IS NULL;
ALTER TABLE `user_permissions` MODIFY COLUMN `can_add` TINYINT(1) NOT NULL DEFAULT 0;
UPDATE `user_permissions` SET `can_edit` = 0 WHERE `can_edit` IS NULL;
ALTER TABLE `user_permissions` MODIFY COLUMN `can_edit` TINYINT(1) NOT NULL DEFAULT 0;
UPDATE `user_permissions` SET `can_delete` = 0 WHERE `can_delete` IS NULL;
ALTER TABLE `user_permissions` MODIFY COLUMN `can_delete` TINYINT(1) NOT NULL DEFAULT 0;

DROP PROCEDURE IF EXISTS `AddColSafe`;

-- ------------------------------------------------------------------------------
-- 3. UNIFY TABLE COLLATIONS TO PREVENT MARIA DB 11.4+ COLLATION MISMATCH (ERROR 1267)
-- ------------------------------------------------------------------------------
ALTER TABLE `concerns` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `production_units` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `batches` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `outwards` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `outward_items` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `production_outward_batches` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `inwards` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `inward_items` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `variants` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `production_programs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `qc_inspections` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
