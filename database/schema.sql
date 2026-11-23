-- =====================================================================
-- GRM MULTI-CONCERN ERP - MASTER DATABASE SCHEMA & MIGRATION
-- Unified Single Database File for Hostinger & Local Environments
-- Safe for both NEW installations and EXISTING live databases
-- Total Tables: 75
-- Generated: 2026-11-23 12:57:16
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

-- =====================================================================
-- PART 1: SAFE LIVE COLUMN UPGRADES FOR EXISTING DATABASES
-- (Safely adds any missing columns with zero data loss)
-- =====================================================================

DELIMITER $$
DROP PROCEDURE IF EXISTS `AddColumnIfNotExists`$$
CREATE PROCEDURE `AddColumnIfNotExists`(
    IN in_table_name VARCHAR(64),
    IN in_column_name VARCHAR(64),
    IN in_column_def TEXT
)
BEGIN
    DECLARE col_exists INT DEFAULT 0;
    DECLARE tbl_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO tbl_exists
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = in_table_name;

    IF tbl_exists > 0 THEN
        SELECT COUNT(*) INTO col_exists
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = in_table_name
          AND COLUMN_NAME = in_column_name;

        IF col_exists = 0 THEN
            SET @ddl = CONCAT('ALTER TABLE `', in_table_name, '` ADD COLUMN `', in_column_name, '` ', in_column_def);
            PREPARE stmt FROM @ddl;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$
DELIMITER ;

CALL `AddColumnIfNotExists`('variants', 'catalog_id', 'INT NULL AFTER `concern_id`');
CALL `AddColumnIfNotExists`('variants', 'attributes', 'LONGTEXT NULL AFTER `weight_unit`');
CALL `AddColumnIfNotExists`('variants', 'cost_price', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `attributes`');
CALL `AddColumnIfNotExists`('variants', 'price', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `cost_price`');
CALL `AddColumnIfNotExists`('variants', 'mrp', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `price`');
CALL `AddColumnIfNotExists`('variants', 'is_complementary', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `mrp`');
CALL `AddColumnIfNotExists`('variants', 'brand_id', 'INT NULL AFTER `subcategory_id`');
CALL `AddColumnIfNotExists`('catalogs', 'category_id', 'INT NULL AFTER `concern_id`');
CALL `AddColumnIfNotExists`('catalogs', 'subcategory_id', 'INT NULL AFTER `category_id`');
CALL `AddColumnIfNotExists`('catalogs', 'sub_sub_category_id', 'INT NULL AFTER `subcategory_id`');
CALL `AddColumnIfNotExists`('catalogs', 'main_category_code', 'VARCHAR(20) NULL AFTER `sub_sub_category_id`');
CALL `AddColumnIfNotExists`('catalogs', 'sub_category_code', 'VARCHAR(20) NULL AFTER `main_category_code`');
CALL `AddColumnIfNotExists`('catalogs', 'sub_sub_category_code', 'VARCHAR(20) NULL AFTER `sub_category_code`');
CALL `AddColumnIfNotExists`('catalogs', 'whole_code', 'VARCHAR(50) NULL AFTER `sub_sub_category_code`');
CALL `AddColumnIfNotExists`('catalogs', 'hsn_code', 'VARCHAR(50) NULL AFTER `whole_code`');
CALL `AddColumnIfNotExists`('catalogs', 'default_gst_rate', 'DECIMAL(5,2) NULL AFTER `hsn_code`');
CALL `AddColumnIfNotExists`('catalogs', 'hierarchy_path', 'VARCHAR(255) NULL AFTER `default_gst_rate`');
CALL `AddColumnIfNotExists`('catalogs', 'style_code', 'VARCHAR(50) NULL AFTER `hierarchy_path`');
CALL `AddColumnIfNotExists`('catalogs', 'short_code', 'VARCHAR(50) NULL AFTER `style_code`');
CALL `AddColumnIfNotExists`('categories', 'code', 'VARCHAR(20) NULL AFTER `name`');
CALL `AddColumnIfNotExists`('categories', 'default_hsn', 'VARCHAR(50) NULL AFTER `code`');
CALL `AddColumnIfNotExists`('categories', 'default_gst_rate', 'DECIMAL(5,2) NULL AFTER `default_hsn`');
CALL `AddColumnIfNotExists`('subcategories', 'code', 'VARCHAR(20) NULL AFTER `name`');
CALL `AddColumnIfNotExists`('subcategories', 'default_hsn', 'VARCHAR(50) NULL AFTER `code`');
CALL `AddColumnIfNotExists`('subcategories', 'default_gst_rate', 'DECIMAL(5,2) NULL AFTER `default_hsn`');
CALL `AddColumnIfNotExists`('stitching_items', 'variant_id', 'INT NULL AFTER `stitching_id`');
CALL `AddColumnIfNotExists`('stitching_items', 'catalog_id', 'INT NULL AFTER `variant_id`');
CALL `AddColumnIfNotExists`('stitching_items', 'size', 'VARCHAR(50) NULL AFTER `catalog_id`');
CALL `AddColumnIfNotExists`('stitching_items', 'color', 'VARCHAR(50) NULL AFTER `size`');
CALL `AddColumnIfNotExists`('stitching_items', 'base_price', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `color`');
CALL `AddColumnIfNotExists`('stitching_items', 'discount_price', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `base_price`');
CALL `AddColumnIfNotExists`('stitching_items', 'stitching_qty', 'INT NOT NULL DEFAULT 0 AFTER `discount_price`');
CALL `AddColumnIfNotExists`('stitching_items', 'per_pc_wt', 'DECIMAL(10,3) NOT NULL DEFAULT 0.000 AFTER `stitching_qty`');
CALL `AddColumnIfNotExists`('stitching_items', 'total_stitching_wt', 'DECIMAL(10,3) NOT NULL DEFAULT 0.000 AFTER `per_pc_wt`');
CALL `AddColumnIfNotExists`('stitching_items', 'inward_status', 'VARCHAR(30) NOT NULL DEFAULT \'pending\' AFTER `total_stitching_wt`');
CALL `AddColumnIfNotExists`('stitching_items', 'inward_at', 'DATETIME NULL AFTER `inward_status`');
CALL `AddColumnIfNotExists`('stitching_logs', 'inward_status', 'VARCHAR(30) NOT NULL DEFAULT \'pending\' AFTER `status`');
CALL `AddColumnIfNotExists`('stitching_logs', 'program_id', 'INT NULL AFTER `concern_id`');
CALL `AddColumnIfNotExists`('stitching_logs', 'produced_variant_id', 'INT NULL AFTER `program_id`');
CALL `AddColumnIfNotExists`('cutting_items', 'program_roll_id', 'INT NULL AFTER `cutting_id`');
CALL `AddColumnIfNotExists`('cutting_items', 'catalog_id', 'INT NULL AFTER `program_roll_id`');
CALL `AddColumnIfNotExists`('program_rolls', 'roll_weight', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `allocated_qty`');
CALL `AddColumnIfNotExists`('program_rolls', 'roll_price', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `roll_weight`');
CALL `AddColumnIfNotExists`('program_rolls', 'catalog_id', 'INT NULL AFTER `roll_price`');
CALL `AddColumnIfNotExists`('program_rolls', 'category_id', 'INT NULL AFTER `catalog_id`');
CALL `AddColumnIfNotExists`('program_rolls', 'subcategory_id', 'INT NULL AFTER `category_id`');
CALL `AddColumnIfNotExists`('program_rolls', 'sub_sub_category_id', 'INT NULL AFTER `subcategory_id`');
CALL `AddColumnIfNotExists`('purchase_bills', 'inward_status', 'ENUM(\'pending\', \'completed\') NOT NULL DEFAULT \'pending\' AFTER `status`');
CALL `AddColumnIfNotExists`('purchase_bills', 'inward_at', 'DATETIME NULL AFTER `inward_status`');
CALL `AddColumnIfNotExists`('purchase_bills', 'attachment_paths', 'JSON NULL AFTER `attachment_path`');
CALL `AddColumnIfNotExists`('purchase_bill_items', 'inward_status', 'ENUM(\'pending\', \'completed\') NOT NULL DEFAULT \'pending\' AFTER `tax_amount`');
CALL `AddColumnIfNotExists`('purchase_bill_items', 'inward_at', 'DATETIME NULL AFTER `inward_status`');
CALL `AddColumnIfNotExists`('purchase_bill_items', 'catalog_id', 'INT NULL AFTER `variant_id`');
CALL `AddColumnIfNotExists`('purchase_damages', 'quarantine_status', 'VARCHAR(30) NOT NULL DEFAULT \'quarantined\' AFTER `status`');
CALL `AddColumnIfNotExists`('purchase_damages', 'resolution_status', 'VARCHAR(30) NOT NULL DEFAULT \'pending\' AFTER `quarantine_status`');
CALL `AddColumnIfNotExists`('purchase_damages', 'resolution_action', 'VARCHAR(50) NULL AFTER `resolution_status`');
CALL `AddColumnIfNotExists`('purchase_damages', 'resolution_notes', 'TEXT NULL AFTER `resolution_action`');
CALL `AddColumnIfNotExists`('purchase_damages', 'resolved_at', 'DATETIME NULL AFTER `resolution_notes`');
CALL `AddColumnIfNotExists`('purchase_damages', 'resolved_by', 'INT NULL AFTER `resolved_at`');
CALL `AddColumnIfNotExists`('purchase_damages', 'stock_ledger_id', 'INT NULL AFTER `resolved_by`');
CALL `AddColumnIfNotExists`('qc_inspections', 'rework_qty', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `rejected_qty`');
CALL `AddColumnIfNotExists`('qc_inspections', 'status', 'VARCHAR(50) NOT NULL DEFAULT \'COMPLETED\' AFTER `rework_qty`');
CALL `AddColumnIfNotExists`('sales_invoices', 'shipping_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `net_amount`');
CALL `AddColumnIfNotExists`('sales_invoices', 'paid_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `payment_status`');
CALL `AddColumnIfNotExists`('sales_invoices', 'is_direct_bill', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `paid_amount`');
CALL `AddColumnIfNotExists`('sales_invoices', 'billing_company_name', 'VARCHAR(150) NULL AFTER `is_direct_bill`');
CALL `AddColumnIfNotExists`('sales_invoices', 'billing_company_address', 'VARCHAR(255) NULL AFTER `billing_company_name`');
CALL `AddColumnIfNotExists`('sales_invoices', 'billing_company_phone', 'VARCHAR(30) NULL AFTER `billing_company_address`');
CALL `AddColumnIfNotExists`('sales_invoices', 'billing_company_email', 'VARCHAR(150) NULL AFTER `billing_company_phone`');
CALL `AddColumnIfNotExists`('sales_invoices', 'billing_company_gstin', 'VARCHAR(15) NULL AFTER `billing_company_email`');
CALL `AddColumnIfNotExists`('sales_invoice_items', 'production_batch_id', 'INT NULL AFTER `variant_id`');
CALL `AddColumnIfNotExists`('outwards', 'transfer_type', 'ENUM(\'customer\', \'concern\') NOT NULL DEFAULT \'customer\' AFTER `customer_id`');
CALL `AddColumnIfNotExists`('outwards', 'destination_concern_id', 'INT NULL AFTER `transfer_type`');
CALL `AddColumnIfNotExists`('inwards', 'outward_id', 'INT NULL AFTER `purchase_bill_id`');
CALL `AddColumnIfNotExists`('inwards', 'from_concern_id', 'INT NULL AFTER `outward_id`');
CALL `AddColumnIfNotExists`('inwards', 'inward_type', 'ENUM(\'purchase\', \'transfer\') NOT NULL DEFAULT \'purchase\' AFTER `from_concern_id`');
CALL `AddColumnIfNotExists`('inwards', 'po_id', 'INT NULL AFTER `grn_number`');
CALL `AddColumnIfNotExists`('inwards', 'received_by', 'INT NULL AFTER `vehicle_number`');
CALL `AddColumnIfNotExists`('cashier_shifts', 'variance_reason', 'VARCHAR(255) NULL AFTER `variance`');
CALL `AddColumnIfNotExists`('user_permissions', 'concern_id', 'INT NULL AFTER `id`');

-- Standardize table collations to utf8mb4_general_ci across all tables to avoid 1267 collation mismatch errors
ALTER TABLE `purchase_damages` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `purchase_damage_quarantine` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `qc_inspection_items` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `qc_piece_items` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `suppliers` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

DROP PROCEDURE IF EXISTS `AddColumnIfNotExists`;

-- =====================================================================
-- PART 2: COMPLETE SYSTEM TABLES (CREATE TABLE IF NOT EXISTS)
-- =====================================================================

-- Table: `assets`
CREATE TABLE IF NOT EXISTS `assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) DEFAULT NULL,
  `asset_code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `current_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `location` varchar(100) DEFAULT NULL,
  `status` enum('active','maintenance','disposed') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`),
  KEY `idx_asset_concern` (`concern_id`),
  CONSTRAINT `fk_asset_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `audit_log`
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `row_id` int(11) DEFAULT NULL,
  `old_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_json`)),
  `new_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_json`)),
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `batches`
CREATE TABLE IF NOT EXISTS `batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `production_unit_id` int(11) DEFAULT NULL,
  `batch_no` varchar(50) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `cutting_batch_id` int(11) DEFAULT NULL,
  `stitching_batch_id` int(11) DEFAULT NULL,
  `qc_batch_id` int(11) DEFAULT NULL,
  `additional_qc_batch_ids` text DEFAULT NULL,
  `batch_qty` decimal(12,2) NOT NULL,
  `total_boxes` int(11) DEFAULT 0,
  `warehouse_id` int(11) DEFAULT NULL,
  `warehouse_name` varchar(150) DEFAULT NULL,
  `packed_by_name` varchar(150) DEFAULT 'System Administrator',
  `rework_included` tinyint(1) DEFAULT 0,
  `qr_codes` longtext DEFAULT NULL,
  `taxable_value` decimal(12,2) DEFAULT 0.00,
  `gst_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `bill_no` varchar(100) DEFAULT NULL,
  `fabric_details` varchar(255) DEFAULT 'GREEN / BIRDS',
  `mfg_date` date DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bt_concern` (`concern_id`),
  KEY `fk_bt_var` (`variant_id`),
  CONSTRAINT `fk_bt_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bt_var` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `brands`
CREATE TABLE IF NOT EXISTS `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_brand_concern` (`concern_id`),
  CONSTRAINT `fk_brand_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `cash_accounts`
CREATE TABLE IF NOT EXISTS `cash_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `account_type` enum('cash','bank','petty_cash') NOT NULL DEFAULT 'cash',
  `account_number` varchar(50) DEFAULT NULL,
  `balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ca_concern` (`concern_id`),
  CONSTRAINT `fk_ca_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `cash_transactions`
CREATE TABLE IF NOT EXISTS `cash_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `txn_date` date NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `ref_module` varchar(50) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ctx_concern` (`concern_id`),
  KEY `fk_ctx_acc` (`account_id`),
  CONSTRAINT `fk_ctx_acc` FOREIGN KEY (`account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ctx_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `cashier_shifts`
CREATE TABLE IF NOT EXISTS `cashier_shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cashier_name` varchar(150) NOT NULL,
  `shift_no` varchar(50) NOT NULL,
  `opening_notes` text DEFAULT NULL,
  `starting_float` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_float_modified` tinyint(1) NOT NULL DEFAULT 0,
  `denominations_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`denominations_json`)),
  `closing_notes` text DEFAULT NULL,
  `closing_float` decimal(12,2) DEFAULT NULL,
  `closing_denominations_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`closing_denominations_json`)),
  `expected_closing_float` decimal(12,2) DEFAULT NULL,
  `variance` decimal(12,2) DEFAULT NULL,
  `variance_reason` varchar(255) DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cs_concern` (`concern_id`),
  KEY `idx_cs_user` (`user_id`),
  CONSTRAINT `fk_cs_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `catalog_mappings`
CREATE TABLE IF NOT EXISTS `catalog_mappings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `purchase_bill_item_id` int(11) NOT NULL,
  `catalog_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `sub_sub_category_id` int(11) DEFAULT NULL,
  `selected_sizes` text DEFAULT NULL,
  `selected_color` varchar(100) DEFAULT NULL,
  `status` enum('mapped','unmapped') NOT NULL DEFAULT 'mapped',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cmap_concern` (`concern_id`),
  KEY `idx_cmap_pbi` (`purchase_bill_item_id`),
  KEY `idx_cmap_catalog` (`catalog_id`),
  CONSTRAINT `fk_cmap_cat` FOREIGN KEY (`catalog_id`) REFERENCES `catalogs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cmap_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cmap_pbi` FOREIGN KEY (`purchase_bill_item_id`) REFERENCES `purchase_bill_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `catalogs`
CREATE TABLE IF NOT EXISTS `catalogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `style_code` varchar(50) DEFAULT NULL,
  `short_code` varchar(50) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `sub_sub_category_id` int(11) DEFAULT NULL,
  `main_category_code` varchar(20) DEFAULT NULL,
  `sub_category_code` varchar(20) DEFAULT NULL,
  `sub_sub_category_code` varchar(20) DEFAULT NULL,
  `hierarchy_path` varchar(255) DEFAULT NULL,
  `hsn_code` varchar(50) DEFAULT NULL,
  `gst_rate` decimal(5,2) DEFAULT NULL,
  `sizes` text DEFAULT NULL,
  `status` enum('active','inactive','in_house') NOT NULL DEFAULT 'in_house',
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_catlg_concern` (`concern_id`),
  CONSTRAINT `fk_catlg_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `categories`
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `default_hsn` varchar(50) DEFAULT NULL,
  `default_gst_rate` decimal(5,2) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cat_concern` (`concern_id`),
  CONSTRAINT `fk_cat_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `concern_damages`
CREATE TABLE IF NOT EXISTS `concern_damages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `damage_no` varchar(50) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `location` varchar(50) NOT NULL DEFAULT 'Main Store',
  `reason` text DEFAULT NULL,
  `status` enum('LOGGED','INSPECTED','RESOLVED','CANCELLED') NOT NULL DEFAULT 'LOGGED',
  `severity` enum('MINOR','MODERATE','SEVERE','SCRAP') NOT NULL DEFAULT 'MODERATE',
  `resolution_action` enum('SCRAP','WRITE_OFF','SALVAGE','REPAIR','REWORK','RETURN_TO_SUPPLIER','SUPPLIER_REPLACEMENT') DEFAULT NULL,
  `resolution_reason` varchar(255) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `loss_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `photo_url` varchar(255) DEFAULT NULL,
  `replacement_variant_id` int(11) DEFAULT NULL,
  `replacement_qr_code` varchar(100) DEFAULT NULL,
  `replacement_inwarded_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cd_concern` (`concern_id`),
  KEY `fk_cd_variant` (`variant_id`),
  CONSTRAINT `fk_cd_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cd_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `concern_modules`
CREATE TABLE IF NOT EXISTS `concern_modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_concern_module` (`concern_id`,`module_id`),
  KEY `fk_cm_module` (`module_id`),
  CONSTRAINT `fk_cm_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cm_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `concerns`
CREATE TABLE IF NOT EXISTS `concerns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `gstin` varchar(30) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `customer_requests`
CREATE TABLE IF NOT EXISTS `customer_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `request_no` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `requested_qty` decimal(12,2) NOT NULL,
  `expected_date` date DEFAULT NULL,
  `status` enum('pending','approved','allocated','rejected') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cr_concern` (`concern_id`),
  KEY `fk_cr_cust` (`customer_id`),
  KEY `fk_cr_var` (`variant_id`),
  CONSTRAINT `fk_cr_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cr_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_cr_var` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `customers`
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `gstin` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cust_concern` (`concern_id`),
  CONSTRAINT `fk_cust_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `cutting_items`
CREATE TABLE IF NOT EXISTS `cutting_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cutting_id` int(11) NOT NULL,
  `program_roll_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `sku_code` varchar(100) DEFAULT NULL,
  `variant_name` varchar(255) DEFAULT NULL,
  `cut_pieces` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ci_cutting` (`cutting_id`),
  KEY `idx_ci_roll` (`program_roll_id`),
  KEY `idx_ci_variant` (`variant_id`),
  CONSTRAINT `fk_ci_cutting` FOREIGN KEY (`cutting_id`) REFERENCES `cutting_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `cutting_logs`
CREATE TABLE IF NOT EXISTS `cutting_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `log_no` varchar(50) NOT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `lot_no` varchar(50) DEFAULT NULL,
  `cutting_name` varchar(255) DEFAULT NULL,
  `status` enum('in_progress','planned','completed') DEFAULT 'in_progress',
  `cutting_master_id` int(11) DEFAULT NULL,
  `cutting_master_name` varchar(150) DEFAULT NULL,
  `program_id` int(11) DEFAULT NULL,
  `raw_variant_id` int(11) DEFAULT NULL,
  `consumed_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_roll_weight` decimal(12,2) DEFAULT 0.00,
  `total_roll_price` decimal(12,2) DEFAULT 0.00,
  `fabric_rate_per_kg` decimal(12,2) DEFAULT 0.00,
  `cut_pieces` int(11) NOT NULL DEFAULT 0,
  `wastage_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `wastage_cost` decimal(12,2) DEFAULT 0.00,
  `wastage_percent` decimal(5,2) DEFAULT 0.00,
  `used_weight` decimal(12,2) DEFAULT 0.00,
  `fabric_cost_per_piece` decimal(12,2) DEFAULT 0.00,
  `wastage_unit` varchar(20) NOT NULL DEFAULT 'kg',
  `sold_wastage_qty` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cut_concern` (`concern_id`),
  KEY `fk_cut_prog` (`program_id`),
  KEY `fk_cut_var` (`raw_variant_id`),
  CONSTRAINT `fk_cut_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cut_prog` FOREIGN KEY (`program_id`) REFERENCES `production_programs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cut_var` FOREIGN KEY (`raw_variant_id`) REFERENCES `variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `cutting_masters`
CREATE TABLE IF NOT EXISTS `cutting_masters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `employee_code` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cm_concern` (`concern_id`),
  CONSTRAINT `fk_cutm_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `cutting_wastage_sales`
CREATE TABLE IF NOT EXISTS `cutting_wastage_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `sale_no` varchar(50) NOT NULL,
  `cutting_id` int(11) NOT NULL,
  `buyer_name` varchar(150) NOT NULL,
  `buyer_phone` varchar(20) NOT NULL,
  `buyer_company` varchar(200) DEFAULT NULL,
  `wastage_category` varchar(100) NOT NULL,
  `wastage_weight` decimal(12,2) NOT NULL,
  `selling_rate` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `amount_received` decimal(12,2) DEFAULT 0.00,
  `sale_date` date NOT NULL,
  `payment_status` enum('paid','partial','pending') DEFAULT 'paid',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cws_concern` (`concern_id`),
  KEY `idx_cws_cutting` (`cutting_id`),
  CONSTRAINT `fk_cws_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cws_cutting` FOREIGN KEY (`cutting_id`) REFERENCES `cutting_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `dispatches`
CREATE TABLE IF NOT EXISTS `dispatches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `dispatch_no` varchar(50) NOT NULL,
  `outward_id` int(11) NOT NULL,
  `transporter_name` varchar(100) DEFAULT NULL,
  `vehicle_no` varchar(50) DEFAULT NULL,
  `tracking_no` varchar(100) DEFAULT NULL,
  `dispatch_date` date NOT NULL,
  `status` enum('in_transit','delivered','returned') NOT NULL DEFAULT 'in_transit',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_disp_concern` (`concern_id`),
  KEY `fk_disp_outw` (`outward_id`),
  CONSTRAINT `fk_disp_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_disp_outw` FOREIGN KEY (`outward_id`) REFERENCES `outwards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `doc_sequences`
CREATE TABLE IF NOT EXISTS `doc_sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `doc_type` varchar(50) NOT NULL,
  `doc_year` int(11) NOT NULL,
  `prefix` varchar(20) NOT NULL,
  `current_seq` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_concern_doc_year` (`concern_id`,`doc_type`,`doc_year`),
  KEY `idx_ds_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `doc_series`
CREATE TABLE IF NOT EXISTS `doc_series` (
  `concern_id` int(11) NOT NULL,
  `doc_type` varchar(50) NOT NULL,
  `prefix` varchar(20) NOT NULL,
  `next_no` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`concern_id`,`doc_type`),
  CONSTRAINT `fk_ds_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `expense_categories`
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_concern_exp_cat` (`concern_id`,`name`),
  KEY `idx_exp_cat_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `expenses`
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `expense_no` varchar(50) NOT NULL,
  `expense_date` date NOT NULL,
  `category` varchar(100) NOT NULL,
  `paid_to` varchar(255) DEFAULT NULL,
  `item_particulars` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_mode` varchar(50) NOT NULL DEFAULT 'Cash',
  `status` varchar(50) NOT NULL DEFAULT 'Paid',
  `account_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exp_concern` (`concern_id`),
  CONSTRAINT `fk_exp_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `fabric_roll_spec_overrides`
CREATE TABLE IF NOT EXISTS `fabric_roll_spec_overrides` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `source_color` varchar(100) NOT NULL,
  `source_design_name` varchar(150) NOT NULL,
  `color` varchar(100) NOT NULL,
  `design_name` varchar(150) NOT NULL,
  `hsn_code` varchar(50) NOT NULL DEFAULT '',
  `qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_weight` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(50) NOT NULL DEFAULT 'Rolls',
  `last_unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_frso_source` (`concern_id`,`source_color`,`source_design_name`),
  KEY `idx_frso_concern` (`concern_id`),
  CONSTRAINT `fk_frso_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `fabric_roll_specs`
CREATE TABLE IF NOT EXISTS `fabric_roll_specs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `color` varchar(100) NOT NULL,
  `design_name` varchar(150) NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_weight` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(50) NOT NULL DEFAULT 'Rolls',
  `last_unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bills_count` int(11) NOT NULL DEFAULT 1,
  `last_bill_no` varchar(100) DEFAULT NULL,
  `last_supplier_name` varchar(150) DEFAULT NULL,
  `hsn_code` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_concern_color_design` (`concern_id`,`color`,`design_name`),
  KEY `idx_frs_concern` (`concern_id`),
  CONSTRAINT `fk_frs_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `hsn_masters`
CREATE TABLE IF NOT EXISTS `hsn_masters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) DEFAULT NULL,
  `code_type` enum('HSN','SAC') NOT NULL DEFAULT 'HSN',
  `hsn_code` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `gst_rate` decimal(5,2) DEFAULT NULL,
  `rate_note` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hsn_concern` (`concern_id`),
  KEY `idx_hsn_code` (`hsn_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `inward_items`
CREATE TABLE IF NOT EXISTS `inward_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `inward_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `accepted_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rejected_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_ini_concern` (`concern_id`),
  KEY `fk_ini_inward` (`inward_id`),
  KEY `fk_ini_variant` (`variant_id`),
  CONSTRAINT `fk_ini_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ini_inward` FOREIGN KEY (`inward_id`) REFERENCES `inwards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ini_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `inwards`
CREATE TABLE IF NOT EXISTS `inwards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `production_unit_id` int(11) DEFAULT NULL,
  `inward_no` varchar(50) NOT NULL,
  `grn_number` varchar(50) DEFAULT NULL,
  `po_id` int(11) DEFAULT NULL,
  `purchase_bill_id` int(11) DEFAULT NULL,
  `outward_id` int(11) DEFAULT NULL,
  `from_concern_id` int(11) DEFAULT NULL,
  `inward_type` enum('purchase','transfer') NOT NULL DEFAULT 'purchase',
  `supplier_id` int(11) DEFAULT NULL,
  `inward_date` date NOT NULL,
  `location` varchar(50) NOT NULL DEFAULT 'Main Store',
  `warehouse_id` int(11) DEFAULT NULL,
  `vehicle_number` varchar(50) DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'VERIFIED',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inw_concern` (`concern_id`),
  KEY `fk_inw_pb` (`purchase_bill_id`),
  KEY `fk_inw_sup` (`supplier_id`),
  CONSTRAINT `fk_inw_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inw_pb` FOREIGN KEY (`purchase_bill_id`) REFERENCES `purchase_bills` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inw_sup` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `label_jobs`
CREATE TABLE IF NOT EXISTS `label_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `label_type` varchar(50) NOT NULL DEFAULT 'production',
  `ref_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `item_title` varchar(255) DEFAULT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `qr_payload` varchar(255) DEFAULT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `printed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_label_concern` (`concern_id`),
  KEY `fk_lj_variant` (`variant_id`),
  CONSTRAINT `fk_lj_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lj_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `modules`
CREATE TABLE IF NOT EXISTS `modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(50) NOT NULL DEFAULT 'bi-grid',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `group_name` varchar(50) NOT NULL,
  `is_super_admin_only` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `outward_items`
CREATE TABLE IF NOT EXISTS `outward_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `outward_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_owi_concern` (`concern_id`),
  KEY `fk_owi_outw` (`outward_id`),
  KEY `fk_owi_var` (`variant_id`),
  CONSTRAINT `fk_owi_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_owi_outw` FOREIGN KEY (`outward_id`) REFERENCES `outwards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_owi_var` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `outwards`
CREATE TABLE IF NOT EXISTS `outwards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `production_unit_id` int(11) DEFAULT NULL,
  `outward_no` varchar(50) NOT NULL,
  `sales_invoice_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `transfer_type` enum('customer','concern') NOT NULL DEFAULT 'customer',
  `destination_concern_id` int(11) DEFAULT NULL,
  `outward_date` date NOT NULL,
  `location` varchar(50) NOT NULL DEFAULT 'Main Store',
  `warehouse_id` int(11) DEFAULT NULL,
  `warehouse_name` varchar(150) DEFAULT 'wareouse (WH-01)',
  `total_boxes` int(11) DEFAULT 1,
  `vehicle_number` varchar(100) DEFAULT NULL,
  `driver_name` varchar(150) DEFAULT NULL,
  `driver_contact` varchar(50) DEFAULT NULL,
  `dispatched_by_name` varchar(150) DEFAULT 'System Administrator',
  `notes` text DEFAULT NULL,
  `transporter_name` varchar(150) DEFAULT NULL,
  `lr_number` varchar(100) DEFAULT NULL,
  `gate_pass_no` varchar(50) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'prepared',
  `dispatched_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_outw_concern` (`concern_id`),
  KEY `fk_outw_si` (`sales_invoice_id`),
  KEY `fk_outw_cust` (`customer_id`),
  CONSTRAINT `fk_outw_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_outw_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_outw_si` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `product_attributes`
CREATE TABLE IF NOT EXISTS `product_attributes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `attribute_type` varchar(50) NOT NULL DEFAULT 'size',
  `attribute_name` varchar(100) NOT NULL,
  `attribute_value` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pattr_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `production_outward_batches`
CREATE TABLE IF NOT EXISTS `production_outward_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL DEFAULT 1,
  `outward_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `cartons_count` int(11) DEFAULT 0,
  `pieces_count` decimal(12,2) DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pob_outward` (`outward_id`),
  KEY `idx_pob_batch` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `production_programs`
CREATE TABLE IF NOT EXISTS `production_programs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `program_no` varchar(50) NOT NULL,
  `program_name` varchar(255) DEFAULT NULL,
  `planner_name` varchar(150) DEFAULT NULL,
  `catalog_id` int(11) DEFAULT NULL,
  `target_variant_id` int(11) DEFAULT NULL,
  `planned_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `status` enum('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prog_concern` (`concern_id`),
  KEY `fk_prog_cat` (`catalog_id`),
  KEY `fk_prog_var` (`target_variant_id`),
  CONSTRAINT `fk_prog_cat` FOREIGN KEY (`catalog_id`) REFERENCES `catalogs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prog_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prog_var` FOREIGN KEY (`target_variant_id`) REFERENCES `variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `production_return_exchange_items`
CREATE TABLE IF NOT EXISTS `production_return_exchange_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `direction` enum('IN','OUT') NOT NULL,
  `variant_id` int(11) NOT NULL,
  `tag_code` varchar(50) DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `condition_state` varchar(50) DEFAULT 'Good',
  `restock_action` enum('RESTOCK','REWORK','SCRAP') DEFAULT 'RESTOCK',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prei_return` (`return_id`),
  KEY `idx_prei_variant` (`variant_id`),
  KEY `idx_prei_direction` (`direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `production_return_items`
CREATE TABLE IF NOT EXISTS `production_return_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `tag_code` varchar(50) DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `condition_state` varchar(50) NOT NULL DEFAULT 'Good',
  `action` enum('RESTOCK','REWORK','SCRAP') NOT NULL DEFAULT 'RESTOCK',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pri_return` (`return_id`),
  KEY `idx_pri_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `production_returns`
CREATE TABLE IF NOT EXISTS `production_returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `return_no` varchar(50) NOT NULL,
  `type` enum('RETURN','EXCHANGE','CREDIT_NOTE') NOT NULL DEFAULT 'RETURN',
  `status` enum('RECEIVED','INSPECTED','RESTOCKED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'RECEIVED',
  `customer_id` int(11) DEFAULT NULL,
  `sales_invoice_id` int(11) DEFAULT NULL,
  `return_date` date NOT NULL,
  `action_type` enum('RESTOCKED','SENT_TO_REWORK','SCRAPPED') NOT NULL DEFAULT 'RESTOCKED',
  `exchange_of_return_id` int(11) DEFAULT NULL,
  `settlement_mode` varchar(50) DEFAULT 'Credit Note',
  `settlement_reference` varchar(100) DEFAULT NULL,
  `price_difference` decimal(12,2) NOT NULL DEFAULT 0.00,
  `refund_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `extra_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reason` text DEFAULT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `inspected_at` datetime DEFAULT NULL,
  `inspected_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_no` (`return_no`),
  KEY `idx_pr_concern` (`concern_id`),
  KEY `idx_pr_status` (`status`),
  KEY `idx_pr_invoice` (`sales_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `production_stock_request_audits`
CREATE TABLE IF NOT EXISTS `production_stock_request_audits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `qty` decimal(12,2) DEFAULT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_psra_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `production_stock_request_items`
CREATE TABLE IF NOT EXISTS `production_stock_request_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `requested_qty` decimal(12,2) NOT NULL,
  `dispatched_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `in_transit_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `received_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shortage_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_psri_request` (`request_id`),
  KEY `idx_psri_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `production_stock_requests`
CREATE TABLE IF NOT EXISTS `production_stock_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `source_concern_id` int(11) DEFAULT NULL,
  `destination_concern_id` int(11) DEFAULT NULL,
  `request_no` varchar(50) NOT NULL,
  `request_type` varchar(50) NOT NULL DEFAULT 'INTER_MODULE',
  `from_location_type` varchar(50) NOT NULL DEFAULT 'WAREHOUSE',
  `from_location_id` int(11) DEFAULT NULL,
  `from_module` varchar(50) DEFAULT NULL,
  `from_unit_id` int(11) DEFAULT NULL,
  `to_location_type` varchar(50) NOT NULL DEFAULT 'SHOP',
  `to_location_id` int(11) DEFAULT NULL,
  `to_module` varchar(50) DEFAULT NULL,
  `to_unit_id` int(11) DEFAULT NULL,
  `status` enum('PENDING','APPROVED','DISPATCHED','IN_TRANSIT','RECEIVED','REJECTED','CANCELLED','SHORTAGE') NOT NULL DEFAULT 'PENDING',
  `priority` varchar(20) NOT NULL DEFAULT 'NORMAL',
  `requested_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `dispatched_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `in_transit_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `received_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shortage_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expected_date` date DEFAULT NULL,
  `dispatch_challan_no` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  `dispatched_by` int(11) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_no` (`request_no`),
  KEY `idx_psr_concern` (`concern_id`),
  KEY `idx_psr_status` (`status`),
  KEY `idx_psr_from` (`from_location_type`,`from_location_id`),
  KEY `idx_psr_to` (`to_location_type`,`to_location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `production_units`
CREATE TABLE IF NOT EXISTS `production_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL DEFAULT 1,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `manager_name` varchar(150) DEFAULT NULL,
  `capacity_per_day` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_pu_concern` (`concern_id`),
  CONSTRAINT `production_units_ibfk_1` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `program_rolls`
CREATE TABLE IF NOT EXISTS `program_rolls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `purchase_bill_item_id` int(11) DEFAULT NULL,
  `roll_piece_tag` varchar(100) DEFAULT NULL,
  `fabric_name` varchar(255) DEFAULT NULL,
  `color` varchar(100) DEFAULT NULL,
  `unit` varchar(50) NOT NULL DEFAULT 'kg',
  `total_roll_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `allocated_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `roll_weight` decimal(12,2) NOT NULL DEFAULT 0.00,
  `roll_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `target_pieces` int(11) NOT NULL DEFAULT 0,
  `category_id` int(11) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `sub_sub_category_id` int(11) DEFAULT NULL,
  `catalog_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prgroll_concern` (`concern_id`),
  KEY `idx_prgroll_program` (`program_id`),
  KEY `idx_prgroll_rollitem` (`purchase_bill_item_id`),
  CONSTRAINT `fk_prgroll_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prgroll_program` FOREIGN KEY (`program_id`) REFERENCES `production_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `purchase_bill_items`
CREATE TABLE IF NOT EXISTS `purchase_bill_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `purchase_bill_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `catalog_id` int(11) DEFAULT NULL,
  `section_type` enum('rolls','garment_other','finished_goods') NOT NULL DEFAULT 'rolls',
  `item_name` varchar(255) DEFAULT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `hsn_code` varchar(30) DEFAULT NULL,
  `specification` varchar(255) DEFAULT NULL,
  `color` varchar(100) DEFAULT NULL,
  `pattern` varchar(150) DEFAULT NULL,
  `designs_count` int(11) NOT NULL DEFAULT 1,
  `rq_qty` int(11) NOT NULL DEFAULT 1,
  `unit` varchar(50) NOT NULL DEFAULT 'kg',
  `gross_weight` decimal(12,2) DEFAULT NULL,
  `net_weight` decimal(12,2) DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL,
  `inward_status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `unit_price` decimal(12,2) NOT NULL,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `gst_type` varchar(50) DEFAULT NULL,
  `tax_mode` varchar(30) NOT NULL DEFAULT 'Exclude GST',
  `total_price` decimal(12,2) NOT NULL,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `photo_path` varchar(255) DEFAULT NULL,
  `category_name` varchar(150) DEFAULT NULL,
  `product_type` varchar(100) DEFAULT NULL,
  `mrp` decimal(12,2) DEFAULT NULL,
  `selling_price` decimal(12,2) DEFAULT NULL,
  `inward_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pbi_concern` (`concern_id`),
  KEY `idx_pbi_catalog` (`catalog_id`),
  KEY `fk_pbi_bill` (`purchase_bill_id`),
  KEY `fk_pbi_variant` (`variant_id`),
  CONSTRAINT `fk_pbi_bill` FOREIGN KEY (`purchase_bill_id`) REFERENCES `purchase_bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pbi_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pbi_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `purchase_bills`
CREATE TABLE IF NOT EXISTS `purchase_bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `bill_no` varchar(50) NOT NULL,
  `supplier_invoice_no` varchar(100) DEFAULT NULL,
  `supplier_id` int(11) NOT NULL,
  `bill_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(50) NOT NULL DEFAULT 'credit',
  `payment_reference` varchar(100) DEFAULT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `inward_status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_paths` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachment_paths`)),
  `fabric_description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `inward_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pb_concern` (`concern_id`),
  KEY `fk_pb_supplier` (`supplier_id`),
  CONSTRAINT `fk_pb_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pb_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `purchase_damage_quarantine`
CREATE TABLE IF NOT EXISTS `purchase_damage_quarantine` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `damage_no` varchar(50) NOT NULL,
  `item_type` varchar(30) NOT NULL,
  `purchase_bill_item_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(150) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `tag_code` varchar(30) NOT NULL,
  `roll_source_color` varchar(100) DEFAULT NULL,
  `roll_source_design_name` varchar(150) DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `damaged_weight` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estimated_loss` decimal(12,2) NOT NULL DEFAULT 0.00,
  `location` varchar(100) NOT NULL DEFAULT 'Main Store',
  `reason` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Quarantined',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pdq_concern_date` (`concern_id`,`created_at`),
  KEY `idx_pdq_purchase_item` (`purchase_bill_item_id`),
  KEY `idx_pdq_variant` (`variant_id`),
  CONSTRAINT `fk_pdq_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `purchase_damage_reasons`
CREATE TABLE IF NOT EXISTS `purchase_damage_reasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pdr_concern_reason` (`concern_id`,`reason`),
  CONSTRAINT `fk_pdr_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `purchase_damages`
CREATE TABLE IF NOT EXISTS `purchase_damages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `damage_no` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `location` varchar(50) NOT NULL DEFAULT 'Main Store',
  `reason` text DEFAULT NULL,
  `action_taken` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pd_concern` (`concern_id`),
  KEY `fk_pd_supplier` (`supplier_id`),
  KEY `fk_pd_variant` (`variant_id`),
  CONSTRAINT `fk_pd_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pd_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_pd_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `purchase_stock_item_overrides`
CREATE TABLE IF NOT EXISTS `purchase_stock_item_overrides` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `purchase_bill_item_id` int(11) NOT NULL,
  `section_type` varchar(30) NOT NULL,
  `item_name` varchar(255) NOT NULL DEFAULT '',
  `item_code` varchar(100) NOT NULL DEFAULT '',
  `specification` varchar(255) NOT NULL DEFAULT '',
  `color` varchar(100) NOT NULL DEFAULT '',
  `unit` varchar(50) NOT NULL DEFAULT '',
  `qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `mrp` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `label_job_baseline_id` int(11) NOT NULL DEFAULT 0,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_psio_item` (`concern_id`,`purchase_bill_item_id`),
  KEY `idx_psio_section` (`concern_id`,`section_type`),
  KEY `fk_psio_item` (`purchase_bill_item_id`),
  CONSTRAINT `fk_psio_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psio_item` FOREIGN KEY (`purchase_bill_item_id`) REFERENCES `purchase_bill_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `qc_inspection_items`
CREATE TABLE IF NOT EXISTS `qc_inspection_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `qc_id` int(11) NOT NULL,
  `catalog_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `stitching_item_id` int(11) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `sku_code` varchar(100) DEFAULT NULL,
  `variant_name` varchar(255) DEFAULT NULL,
  `inspected_qty` int(11) NOT NULL DEFAULT 0,
  `passed_qty` int(11) NOT NULL DEFAULT 0,
  `rejected_qty` int(11) NOT NULL DEFAULT 0,
  `rework_qty` int(11) NOT NULL DEFAULT 0,
  `defect_notes` text DEFAULT NULL,
  `rework_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `qc_id` (`qc_id`),
  KEY `variant_id` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `qc_inspections`
CREATE TABLE IF NOT EXISTS `qc_inspections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `qc_no` varchar(50) NOT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `program_id` int(11) DEFAULT NULL,
  `cutting_batch_id` int(11) DEFAULT NULL,
  `stitching_batch_id` int(11) DEFAULT NULL,
  `inspected_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `passed_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rework_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rejected_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `location` varchar(50) NOT NULL DEFAULT 'Main Store',
  `defect_notes` text DEFAULT NULL,
  `rework_notes` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'COMPLETED',
  `inspector_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_qc_concern` (`concern_id`),
  KEY `fk_qc_var` (`variant_id`),
  CONSTRAINT `fk_qc_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qc_var` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `qc_piece_items`
CREATE TABLE IF NOT EXISTS `qc_piece_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `qc_id` int(11) NOT NULL,
  `qc_item_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `stitching_item_id` int(11) DEFAULT NULL,
  `tag_code` varchar(100) NOT NULL,
  `piece_number` int(11) NOT NULL DEFAULT 1,
  `total_variant_pieces` int(11) NOT NULL DEFAULT 1,
  `size` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `catalog_name` varchar(255) DEFAULT NULL,
  `verdict` enum('PENDING','PASSED','REJECTED','REWORK') NOT NULL DEFAULT 'PENDING',
  `defect_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `qc_id` (`qc_id`),
  KEY `tag_code` (`tag_code`),
  KEY `verdict` (`verdict`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `return_exchange_items`
CREATE TABLE IF NOT EXISTS `return_exchange_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `direction` enum('IN','OUT') NOT NULL,
  `variant_id` int(11) NOT NULL,
  `tag_code` varchar(50) DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `condition_state` varchar(50) DEFAULT 'Good',
  `restock_action` enum('RESTOCK','REWORK','SCRAP') DEFAULT 'RESTOCK',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rei_return` (`return_id`),
  KEY `idx_rei_variant` (`variant_id`),
  KEY `idx_rei_direction` (`direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `returns_exchanges`
CREATE TABLE IF NOT EXISTS `returns_exchanges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `return_no` varchar(50) NOT NULL,
  `type` enum('return','exchange') NOT NULL DEFAULT 'return',
  `customer_id` int(11) NOT NULL,
  `sales_invoice_id` int(11) DEFAULT NULL,
  `return_date` date NOT NULL,
  `returned_variant_id` int(11) NOT NULL,
  `returned_qty` decimal(12,2) NOT NULL,
  `exchange_variant_id` int(11) DEFAULT NULL,
  `exchange_qty` decimal(12,2) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_re_concern` (`concern_id`),
  KEY `fk_re_cust` (`customer_id`),
  KEY `fk_re_si` (`sales_invoice_id`),
  KEY `fk_re_ret_var` (`returned_variant_id`),
  KEY `fk_re_exc_var` (`exchange_variant_id`),
  CONSTRAINT `fk_re_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_re_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_re_exc_var` FOREIGN KEY (`exchange_variant_id`) REFERENCES `variants` (`id`),
  CONSTRAINT `fk_re_ret_var` FOREIGN KEY (`returned_variant_id`) REFERENCES `variants` (`id`),
  CONSTRAINT `fk_re_si` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `role_permissions`
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT 1,
  `can_add` tinyint(1) NOT NULL DEFAULT 0,
  `can_edit` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_module` (`role_id`,`module_id`),
  KEY `fk_rp_module` (`module_id`),
  CONSTRAINT `fk_rp_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `roles`
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_roles_concern` (`concern_id`),
  CONSTRAINT `fk_roles_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `sales_invoice_items`
CREATE TABLE IF NOT EXISTS `sales_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `sales_invoice_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `production_batch_id` int(11) DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sii_concern` (`concern_id`),
  KEY `fk_sii_inv` (`sales_invoice_id`),
  KEY `fk_sii_var` (`variant_id`),
  KEY `idx_sii_production_batch` (`production_batch_id`),
  CONSTRAINT `fk_sii_batch` FOREIGN KEY (`production_batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sii_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sii_inv` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sii_var` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `sales_invoices`
CREATE TABLE IF NOT EXISTS `sales_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shipping_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','bank_transfer','upi','credit') NOT NULL DEFAULT 'cash',
  `payment_status` enum('paid','partial','unpaid') NOT NULL DEFAULT 'paid',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_direct_bill` tinyint(1) NOT NULL DEFAULT 0,
  `billing_company_name` varchar(150) DEFAULT NULL,
  `billing_company_address` varchar(255) DEFAULT NULL,
  `billing_company_phone` varchar(30) DEFAULT NULL,
  `billing_company_email` varchar(150) DEFAULT NULL,
  `billing_company_gstin` varchar(15) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_si_concern` (`concern_id`),
  KEY `fk_si_cust` (`customer_id`),
  CONSTRAINT `fk_si_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_si_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `shop_damages`
CREATE TABLE IF NOT EXISTS `shop_damages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `damage_no` varchar(50) NOT NULL,
  `shop_id` int(11) NOT NULL DEFAULT 1,
  `variant_id` int(11) NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `location` varchar(100) NOT NULL DEFAULT 'Retail Shop',
  `damage_type` varchar(50) NOT NULL DEFAULT 'CUSTOMER_DAMAGE',
  `severity` enum('MINOR','MODERATE','SEVERE','SCRAP') NOT NULL DEFAULT 'MODERATE',
  `status` enum('LOGGED','INSPECTED','RESOLVED','CANCELLED') NOT NULL DEFAULT 'LOGGED',
  `resolution_action` enum('SCRAP','WRITE_OFF','SALVAGE','REPAIR','REWORK','RETURN_TO_WAREHOUSE','SUPPLIER_REPLACEMENT') DEFAULT NULL,
  `resolution_reason` varchar(255) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `loss_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `photo_url` varchar(255) DEFAULT NULL,
  `replacement_variant_id` int(11) DEFAULT NULL,
  `replacement_qr_code` varchar(100) DEFAULT NULL,
  `replacement_inwarded_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `damage_no` (`damage_no`),
  KEY `idx_shd_concern` (`concern_id`),
  KEY `idx_shd_status` (`status`),
  KEY `idx_shd_variant` (`variant_id`),
  KEY `idx_shd_repl_qr` (`replacement_qr_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `staff`
CREATE TABLE IF NOT EXISTS `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `staff_code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `salary_type` enum('monthly','daily','piece_rate','hourly') NOT NULL DEFAULT 'monthly',
  `base_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `joining_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_concern` (`concern_id`),
  KEY `idx_staff_user` (`user_id`),
  CONSTRAINT `fk_staff_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `stitching_items`
CREATE TABLE IF NOT EXISTS `stitching_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stitching_id` int(11) NOT NULL,
  `cutting_item_id` int(11) DEFAULT NULL,
  `catalog_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `color` varchar(100) DEFAULT NULL,
  `sku_code` varchar(100) DEFAULT NULL,
  `variant_name` varchar(255) DEFAULT NULL,
  `cut_qty` int(11) NOT NULL DEFAULT 0,
  `stitching_qty` int(11) NOT NULL DEFAULT 0,
  `per_pc_weight` decimal(8,3) DEFAULT 0.000,
  `total_weight` decimal(12,2) DEFAULT 0.00,
  `base_price` decimal(12,2) DEFAULT 0.00,
  `discount_price` decimal(12,2) DEFAULT 0.00,
  `inward_status` varchar(30) DEFAULT 'pending',
  `inward_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_si_stitching` (`stitching_id`),
  KEY `idx_si_variant` (`variant_id`),
  CONSTRAINT `fk_si_stitching` FOREIGN KEY (`stitching_id`) REFERENCES `stitching_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `stitching_logs`
CREATE TABLE IF NOT EXISTS `stitching_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `log_no` varchar(50) NOT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `stitching_name` varchar(255) DEFAULT NULL,
  `status` enum('in_progress','planned','completed') DEFAULT 'in_progress',
  `cutting_id` int(11) DEFAULT NULL,
  `tailor_id` int(11) DEFAULT NULL,
  `tailor_name` varchar(150) DEFAULT NULL,
  `program_id` int(11) DEFAULT NULL,
  `input_pieces` int(11) NOT NULL DEFAULT 0,
  `cut_pieces` int(11) NOT NULL DEFAULT 0,
  `stitched_pieces` int(11) NOT NULL DEFAULT 0,
  `cut_weight` decimal(12,2) DEFAULT 0.00,
  `stitch_weight` decimal(12,2) DEFAULT 0.00,
  `wastage_pieces` int(11) NOT NULL DEFAULT 0,
  `wastage_weight` decimal(12,2) DEFAULT 0.00,
  `sold_wastage_qty` decimal(12,2) DEFAULT 0.00,
  `inward_status` varchar(30) DEFAULT 'pending',
  `produced_variant_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stitch_concern` (`concern_id`),
  KEY `fk_stitch_prog` (`program_id`),
  KEY `fk_stitch_var` (`produced_variant_id`),
  CONSTRAINT `fk_stitch_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stitch_prog` FOREIGN KEY (`program_id`) REFERENCES `production_programs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stitch_var` FOREIGN KEY (`produced_variant_id`) REFERENCES `variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `stitching_masters`
CREATE TABLE IF NOT EXISTS `stitching_masters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `employee_code` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sm_concern` (`concern_id`),
  CONSTRAINT `fk_sm_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `stitching_wastage_sales`
CREATE TABLE IF NOT EXISTS `stitching_wastage_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `sale_no` varchar(50) NOT NULL,
  `stitching_id` int(11) NOT NULL,
  `buyer_name` varchar(150) NOT NULL,
  `buyer_phone` varchar(20) NOT NULL,
  `buyer_company` varchar(200) DEFAULT NULL,
  `wastage_category` varchar(100) NOT NULL,
  `unit_type` varchar(20) DEFAULT 'KG',
  `quantity` decimal(12,2) NOT NULL,
  `selling_rate` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `amount_received` decimal(12,2) DEFAULT 0.00,
  `sale_date` date NOT NULL,
  `payment_status` enum('paid','partial','pending') DEFAULT 'paid',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sws_concern` (`concern_id`),
  KEY `idx_sws_stitching` (`stitching_id`),
  CONSTRAINT `fk_sws_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sws_stitching` FOREIGN KEY (`stitching_id`) REFERENCES `stitching_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `stock_ledger`
CREATE TABLE IF NOT EXISTS `stock_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `location` varchar(50) NOT NULL DEFAULT 'Main Store',
  `qty_in` decimal(12,2) NOT NULL DEFAULT 0.00,
  `qty_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ref_module` varchar(50) NOT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ledger_concern` (`concern_id`),
  KEY `idx_ledger_variant` (`variant_id`),
  CONSTRAINT `fk_sl_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sl_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `stocks`
CREATE TABLE IF NOT EXISTS `stocks` (
  `concern_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `location` varchar(50) NOT NULL DEFAULT 'Main Store',
  `qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`concern_id`,`variant_id`,`location`),
  KEY `idx_stocks_concern` (`concern_id`),
  KEY `fk_stk_variant` (`variant_id`),
  CONSTRAINT `fk_stk_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stk_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `sub_subcategories`
CREATE TABLE IF NOT EXISTS `sub_subcategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `subcategory_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subsubcat_concern` (`concern_id`),
  KEY `idx_subsubcat_subcat` (`subcategory_id`),
  CONSTRAINT `fk_subsubcat_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subsubcat_subcat` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `subcategories`
CREATE TABLE IF NOT EXISTS `subcategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subcat_concern` (`concern_id`),
  KEY `fk_subcat_category` (`category_id`),
  CONSTRAINT `fk_subcat_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subcat_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `suppliers`
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `gstin` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sup_concern` (`concern_id`),
  CONSTRAINT `fk_sup_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `user_concerns`
CREATE TABLE IF NOT EXISTS `user_concerns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `concern_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_concern` (`user_id`,`concern_id`),
  KEY `idx_uc_user` (`user_id`),
  KEY `idx_uc_concern` (`concern_id`),
  CONSTRAINT `fk_uc_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `user_permissions`
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `concern_id` int(11) DEFAULT NULL,
  `module_id` int(11) NOT NULL,
  `can_view` tinyint(1) DEFAULT NULL,
  `can_add` tinyint(1) DEFAULT NULL,
  `can_edit` tinyint(1) DEFAULT NULL,
  `can_delete` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_concern_module` (`user_id`,`concern_id`,`module_id`),
  KEY `idx_up_user` (`user_id`),
  KEY `idx_up_module` (`module_id`),
  KEY `idx_up_concern` (`concern_id`),
  CONSTRAINT `fk_up_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `users`
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_concern` (`concern_id`),
  KEY `fk_users_role` (`role_id`),
  CONSTRAINT `fk_users_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `variants`
CREATE TABLE IF NOT EXISTS `variants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `catalog_id` int(11) DEFAULT NULL,
  `sku_code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `weight` decimal(10,3) NOT NULL DEFAULT 0.000,
  `weight_unit` varchar(20) NOT NULL DEFAULT 'kg',
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `mrp` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_complementary` tinyint(1) NOT NULL DEFAULT 0,
  `creation_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_concern_sku` (`concern_id`,`sku_code`),
  KEY `idx_var_concern` (`concern_id`),
  KEY `idx_var_catalog` (`catalog_id`),
  KEY `fk_var_cat` (`category_id`),
  KEY `fk_var_subcat` (`subcategory_id`),
  KEY `fk_var_brand` (`brand_id`),
  CONSTRAINT `fk_var_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_var_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_var_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_var_subcat` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: `warehouse_damages`
CREATE TABLE IF NOT EXISTS `warehouse_damages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `damage_no` varchar(50) NOT NULL,
  `warehouse_id` int(11) NOT NULL DEFAULT 1,
  `variant_id` int(11) NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `location` varchar(100) NOT NULL DEFAULT 'Warehouse Floor',
  `damage_type` varchar(50) NOT NULL DEFAULT 'PHYSICAL_DAMAGE',
  `severity` enum('MINOR','MODERATE','SEVERE','SCRAP') NOT NULL DEFAULT 'MODERATE',
  `status` enum('LOGGED','INSPECTED','RESOLVED','CANCELLED') NOT NULL DEFAULT 'LOGGED',
  `resolution_action` enum('SCRAP','WRITE_OFF','SALVAGE','REPAIR','REWORK','RETURN_TO_SUPPLIER','SUPPLIER_REPLACEMENT') DEFAULT NULL,
  `resolution_reason` varchar(255) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `loss_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `photo_url` varchar(255) DEFAULT NULL,
  `replacement_variant_id` int(11) DEFAULT NULL,
  `replacement_qr_code` varchar(100) DEFAULT NULL,
  `replacement_inwarded_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `damage_no` (`damage_no`),
  KEY `idx_whd_concern` (`concern_id`),
  KEY `idx_whd_status` (`status`),
  KEY `idx_whd_variant` (`variant_id`),
  KEY `idx_whd_repl_qr` (`replacement_qr_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- PART 3: MASTER BASELINE SEED DATA (SAFE IDEMPOTENT INSERTS)
-- =====================================================================

INSERT INTO `modules` (`code`, `name`, `icon`, `sort_order`, `group_name`, `is_super_admin_only`) VALUES
('admin_dashboard', 'Dashboard', 'bi-speedometer2', 1, 'Super Admin', 1),
('admin_concerns', 'Concern Management', 'bi-buildings', 2, 'Super Admin', 1),
('admin_assets', 'Asset Management', 'bi-building-gear', 3, 'Super Admin', 1),
('admin_reports', 'Consolidated Reports', 'bi-bar-chart-line', 4, 'Super Admin', 1),

('concern_dashboard', 'Concern Dashboard', 'bi-speedometer', 10, 'Dashboard', 0),
('suppliers', 'Supplier', 'bi-truck', 20, 'Purchase', 0),
('purchase_billing', 'Purchase Billing', 'bi-receipt', 21, 'Purchase', 0),
('purchase_stocks', 'Purchase Stocks', 'bi-boxes', 22, 'Purchase', 0),
('purchase_damage', 'Purchase Damage', 'bi-shield-x', 23, 'Purchase', 0),
('catalog_mapping', 'Catalog Mapping', 'bi-diagram-3-fill', 24, 'Purchase', 0),
('product_adding', 'Product Adding', 'bi-plus-square-dotted', 25, 'Purchase', 0),
('catalog_program', 'Catalog & Program', 'bi-journal-bookmark', 30, 'Production', 0),
('cutting_wastage', 'Cutting & Wastage', 'bi-scissors', 31, 'Production', 0),
('stitching_wastage', 'Stitching & Wastage', 'bi-layers', 32, 'Production', 0),
('label_generation', 'Label Generation', 'bi-qr-code', 33, 'Production', 0),
('qc_stocks', 'QC Stocks', 'bi-shield-check', 34, 'Production', 0),
('inward', 'Inward', 'bi-box-arrow-in-down', 40, 'Stock', 0),
('stocks', 'Stocks', 'bi-boxes', 41, 'Stock', 0),
('concern_damage', 'Concern Damage', 'bi-x-octagon', 42, 'Stock', 0),
('return_exchange', 'Return & Exchange', 'bi-arrow-left-right', 43, 'Stock', 0),
('customers', 'Customer Details', 'bi-people', 50, 'Shop', 0),
('billing', 'POS Billing', 'bi-calculator', 51, 'Shop', 0),
('request', 'Request', 'bi-file-earmark-text', 52, 'Sales', 0),
('batch', 'Batch', 'bi-collection', 53, 'Sales', 0),
('outward', 'Outward', 'bi-box-arrow-up-right', 54, 'Sales', 0),
('dispatch', 'Dispatch', 'bi-send', 55, 'Sales', 0),
('expense', 'Expense', 'bi-wallet2', 60, 'Finance', 0),
('cash', 'Cash', 'bi-cash-coin', 61, 'Finance', 0),
('cash_flow', 'Cash & Cash Flow', 'bi-cash-coin', 62, 'Finance', 0),
('staff', 'Staff', 'bi-people-fill', 90, 'Staff', 0)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `icon` = VALUES(`icon`),
    `sort_order` = VALUES(`sort_order`),
    `group_name` = VALUES(`group_name`),
    `is_super_admin_only` = VALUES(`is_super_admin_only`);

-- 2. SUPER ADMIN ROLE
INSERT INTO `roles` (`id`, `concern_id`, `name`, `description`) VALUES
(1, NULL, 'Super Admin', 'Full unrestricted platform administrative control')
ON DUPLICATE KEY UPDATE
    `concern_id` = NULL,
    `name` = VALUES(`name`),
    `description` = VALUES(`description`);

-- 3. SUPER ADMIN USER
-- Username: superadmin@invento.com
-- Password: SuperAdmin@123
INSERT INTO `users` (`id`, `concern_id`, `role_id`, `name`, `email`, `password_hash`, `phone`, `status`) VALUES
(1, NULL, 1, 'Master Super Admin', 'superadmin@invento.com',
 '$2y$10$NA6FVJowu1d8mbYeS1hI2uZ6hd2uskez9E9nKra3ZmUNOEkbO2mAW',
 '9999999999', 'active')
ON DUPLICATE KEY UPDATE
    `concern_id` = NULL,
    `role_id` = 1,
    `name` = VALUES(`name`),
    `email` = VALUES(`email`),
    `password_hash` = VALUES(`password_hash`),
    `phone` = VALUES(`phone`),
    `status` = VALUES(`status`);

-- 4. HSN & SAC MASTER REGISTRY
-- Comprehensive Indian GST Textile, Garment, Fabric, Packaging,
-- Machinery, Maintenance & Job Work Classifications (95 Feeds)
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
    INDEX `idx_hsn_code` (`hsn_code`),
    INDEX `idx_hsn_active_type` (`is_active`, `code_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DELETE FROM `hsn_masters` WHERE `concern_id` IS NULL;

INSERT INTO `hsn_masters` (`id`, `concern_id`, `code_type`, `hsn_code`, `description`, `category`, `gst_rate`, `rate_note`, `is_active`) VALUES
(1, NULL, 'HSN', '5201', 'Raw cotton, not carded or combed', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(2, NULL, 'HSN', '5202', 'Cotton waste, including yarn waste and garnetted stock', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(3, NULL, 'HSN', '5203', 'Cotton, carded or combed', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(4, NULL, 'HSN', '5204', 'Cotton sewing thread', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(5, NULL, 'HSN', '5205', 'Cotton yarn, containing 85% or more cotton, not put up for retail sale', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(6, NULL, 'HSN', '5206', 'Cotton yarn, containing less than 85% cotton, not put up for retail sale', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(7, NULL, 'HSN', '5207', 'Cotton yarn put up for retail sale', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(8, NULL, 'HSN', '5208', 'Woven cotton fabrics, containing 85% or more cotton, weighing not more than 200 g/m2', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(9, NULL, 'HSN', '5209', 'Woven cotton fabrics, containing 85% or more cotton, weighing more than 200 g/m2', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(10, NULL, 'HSN', '5210', 'Woven cotton fabrics, containing less than 85% cotton, mixed mainly or solely with man-made fibres', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(11, NULL, 'HSN', '5211', 'Woven cotton fabrics, containing less than 85% cotton, weighing more than 200 g/m2', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(12, NULL, 'HSN', '5212', 'Other woven cotton fabrics', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(13, NULL, 'HSN', '5401', 'Sewing thread of man-made filaments', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(14, NULL, 'HSN', '5402', 'Synthetic filament yarn, including high-tenacity yarn', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(15, NULL, 'HSN', '5404', 'Synthetic monofilament and strip, of an apparent width not exceeding 5 mm', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(16, NULL, 'HSN', '5407', 'Woven fabrics of synthetic filament yarn', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(17, NULL, 'HSN', '5408', 'Woven fabrics of artificial filament yarn', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(18, NULL, 'HSN', '5501', 'Synthetic filament tow', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(19, NULL, 'HSN', '5502', 'Artificial filament tow', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(20, NULL, 'HSN', '5503', 'Synthetic staple fibres, not carded, combed or otherwise processed for spinning', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(21, NULL, 'HSN', '5504', 'Artificial staple fibres, not carded, combed or otherwise processed for spinning', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(22, NULL, 'HSN', '5508', 'Sewing thread of man-made staple fibres', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(23, NULL, 'HSN', '5509', 'Yarn of synthetic staple fibres, not put up for retail sale', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(24, NULL, 'HSN', '5510', 'Yarn of artificial staple fibres, not put up for retail sale', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(25, NULL, 'HSN', '5511', 'Yarn of man-made staple fibres put up for retail sale', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(26, NULL, 'HSN', '5512', 'Woven fabrics containing 85% or more synthetic staple fibres', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(27, NULL, 'HSN', '5513', 'Woven fabrics containing less than 85% synthetic staple fibres, mixed mainly with cotton', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(28, NULL, 'HSN', '5514', 'Woven fabrics containing less than 85% synthetic staple fibres, mixed mainly with man-made fibres', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(29, NULL, 'HSN', '5515', 'Other woven fabrics of synthetic staple fibres', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(30, NULL, 'HSN', '5516', 'Woven fabrics of artificial staple fibres', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(31, NULL, 'HSN', '5101', 'Wool, not carded or combed', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(32, NULL, 'HSN', '5106', 'Yarn of carded wool, not put up for retail sale', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(33, NULL, 'HSN', '5111', 'Woven fabrics of carded wool or fine animal hair', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(34, NULL, 'HSN', '5112', 'Woven fabrics of combed wool or fine animal hair', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(35, NULL, 'HSN', '5007', 'Woven fabrics of silk or silk waste', 'Raw materials and fabrics', 5.00, 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.', 1),
(36, NULL, 'HSN', '6101', 'Men\'s or boys\' overcoats and similar garments, knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(37, NULL, 'HSN', '6102', 'Women\'s or girls\' overcoats and similar garments, knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(38, NULL, 'HSN', '6103', 'Men\'s or boys\' suits, jackets, trousers and shorts, knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(39, NULL, 'HSN', '6104', 'Women\'s or girls\' suits, dresses, skirts, trousers and shorts, knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(40, NULL, 'HSN', '6105', 'Men\'s or boys\' shirts, knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(41, NULL, 'HSN', '6106', 'Women\'s or girls\' blouses and shirts, knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(42, NULL, 'HSN', '6109', 'T-shirts, singlets and other vests, knitted or crocheted', 'Garments', NULL, 'Standard garment GST is conditional: confirm the applicable 5% or 12% rate against the garment sale value and current GST notification.', 1),
(43, NULL, 'HSN', '6110', 'Jerseys, pullovers, cardigans and similar articles, knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(44, NULL, 'HSN', '6111', 'Babies\' garments and clothing accessories, knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(45, NULL, 'HSN', '6112', 'Track suits, ski suits and swimwear, knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(46, NULL, 'HSN', '6113', 'Garments made from knitted or crocheted coated fabrics', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(47, NULL, 'HSN', '6114', 'Other garments, knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(48, NULL, 'HSN', '6115', 'Hosiery, including socks and tights', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(49, NULL, 'HSN', '6116', 'Gloves, mittens and mitts, knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(50, NULL, 'HSN', '6117', 'Other made-up clothing accessories, knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(51, NULL, 'HSN', '6201', 'Men\'s or boys\' overcoats and similar garments, not knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(52, NULL, 'HSN', '6202', 'Women\'s or girls\' overcoats and similar garments, not knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(53, NULL, 'HSN', '6203', 'Men\'s or boys\' suits, jackets, trousers and shorts, not knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(54, NULL, 'HSN', '6204', 'Women\'s or girls\' suits, dresses, skirts, trousers and shorts, not knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(55, NULL, 'HSN', '6205', 'Men\'s or boys\' shirts, not knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(56, NULL, 'HSN', '6206', 'Women\'s or girls\' blouses and shirts, not knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(57, NULL, 'HSN', '6207', 'Men\'s or boys\' undergarments and nightwear', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(58, NULL, 'HSN', '6208', 'Women\'s or girls\' undergarments and nightwear', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(59, NULL, 'HSN', '6209', 'Babies\' garments and clothing accessories, not knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(60, NULL, 'HSN', '6210', 'Garments made from fabrics of headings 5602, 5603, 5903, 5906 or 5907', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(61, NULL, 'HSN', '6211', 'Track suits, ski suits and swimwear, not knitted or crocheted', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(62, NULL, 'HSN', '6212', 'Brassieres, girdles, corsets, braces, suspenders and similar articles', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(63, NULL, 'HSN', '6214', 'Shawls, scarves, mufflers, mantillas, veils and similar articles', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(64, NULL, 'HSN', '6217', 'Other made-up clothing accessories', 'Garments', 5.00, 'Standard garment rate 5%; confirm the applicable 12% threshold against the piece value and current GST notification.', 1),
(65, NULL, 'HSN', '6301', 'Blankets and travelling rugs', 'Home and made-up textiles', 5.00, 'Standard rate 5% for made-up textile articles.', 1),
(66, NULL, 'HSN', '6302', 'Bed linen, table linen, toilet linen and kitchen linen, including towels and bedsheets', 'Home and made-up textiles', 5.00, 'Standard rate 5% for made-up textile articles.', 1),
(67, NULL, 'HSN', '6303', 'Curtains, drapes, interior blinds and bed valances', 'Home and made-up textiles', 5.00, 'Standard rate 5% for made-up textile articles.', 1),
(68, NULL, 'HSN', '6304', 'Other furnishing articles, including furnishing textiles', 'Home and made-up textiles', 5.00, 'Standard rate 5% for made-up textile articles.', 1),
(69, NULL, 'HSN', '6305', 'Sacks and bags used for packing goods, of textile materials', 'Home and made-up textiles', 5.00, 'Standard rate 5% for made-up textile articles.', 1),
(70, NULL, 'HSN', '6307', 'Other made-up textile articles, including certain labels and textile accessories', 'Home and made-up textiles', 5.00, 'Standard rate 5% for made-up textile articles.', 1),
(71, NULL, 'HSN', '5607', 'Twine, cordage, ropes and cables, whether or not plaited', 'Garment accessories and manufacturing materials', 5.00, 'Standard textile accessories rate 5%.', 1),
(72, NULL, 'HSN', '5804', 'Tulles, lace and embroidery lace in the piece, in strips or motifs', 'Garment accessories and manufacturing materials', 5.00, 'Standard textile accessories rate 5%.', 1),
(73, NULL, 'HSN', '5806', 'Narrow woven fabrics, including certain woven labels', 'Garment accessories and manufacturing materials', 5.00, 'Standard textile accessories rate 5%.', 1),
(74, NULL, 'HSN', '5807', 'Woven labels, badges and similar textile articles', 'Garment accessories and manufacturing materials', 5.00, 'Standard textile accessories rate 5%.', 1),
(75, NULL, 'HSN', '5808', 'Braids in the piece; ornamental trimmings in the piece', 'Garment accessories and manufacturing materials', 5.00, 'Standard textile accessories rate 5%.', 1),
(76, NULL, 'HSN', '5810', 'Embroidery in the piece, in strips or in motifs', 'Garment accessories and manufacturing materials', 5.00, 'Standard textile accessories rate 5%.', 1),
(77, NULL, 'HSN', '9606', 'Buttons, press-fasteners, snap-fasteners and parts thereof', 'Garment accessories and manufacturing materials', 12.00, 'Standard rate 12% for buttons and slide fasteners.', 1),
(78, NULL, 'HSN', '9607', 'Slide fasteners (zippers) and parts thereof', 'Garment accessories and manufacturing materials', 12.00, 'Standard rate 12% for buttons and slide fasteners.', 1),
(79, NULL, 'HSN', '3923', 'Articles for the conveyance or packing of goods, of plastics', 'Packaging materials', 18.00, 'Standard rate 18% for plastic packaging materials.', 1),
(80, NULL, 'HSN', '3919', 'Self-adhesive plates, sheets, film, foil, tape and other flat shapes, of plastics', 'Packaging materials', 18.00, 'Standard rate 18% for plastic packaging materials.', 1),
(81, NULL, 'HSN', '4819', 'Cartons, boxes, cases, bags and other packing containers of paper or paperboard', 'Packaging materials', 12.00, 'Standard rate 12% for paper boxes and labels.', 1),
(82, NULL, 'HSN', '4820', 'Registers, notebooks, folders, labels and similar articles of paper or paperboard', 'Packaging materials', 12.00, 'Standard rate 12% for paper boxes and labels.', 1),
(83, NULL, 'HSN', '4821', 'Paper or paperboard labels of all kinds, printed or not', 'Packaging materials', 12.00, 'Standard rate 12% for paper boxes and labels.', 1),
(84, NULL, 'HSN', '8444', 'Machines for extruding, drawing, texturing or cutting man-made textile materials', 'Machinery and parts', 18.00, 'Standard rate shown; confirm the machine or part classification and any applicable exemption before billing.', 1),
(85, NULL, 'HSN', '8445', 'Machines for preparing textile fibres and spinning, doubling or twisting textile yarns', 'Machinery and parts', 18.00, 'Standard rate shown; confirm the machine or part classification and any applicable exemption before billing.', 1),
(86, NULL, 'HSN', '8446', 'Weaving machines (looms)', 'Machinery and parts', 18.00, 'Standard rate shown; confirm the machine or part classification and any applicable exemption before billing.', 1),
(87, NULL, 'HSN', '8447', 'Knitting machines, stitch-bonding machines and machines for making net or lace', 'Machinery and parts', 18.00, 'Standard rate shown; confirm the machine or part classification and any applicable exemption before billing.', 1),
(88, NULL, 'HSN', '8448', 'Auxiliary machinery and parts for machines of headings 8444 to 8447', 'Machinery and parts', 18.00, 'Standard rate shown; confirm the machine or part classification and any applicable exemption before billing.', 1),
(89, NULL, 'HSN', '8451', 'Machinery for washing, bleaching, dyeing, dressing, finishing, coating or impregnating textile yarns or fabrics', 'Machinery and parts', 18.00, 'Standard rate shown; confirm the machine or part classification and any applicable exemption before billing.', 1),
(90, NULL, 'HSN', '8452', 'Sewing machines and furniture, bases and covers specially designed for sewing machines', 'Machinery and parts', 18.00, 'Standard rate shown; confirm the machine or part classification and any applicable exemption before billing.', 1),
(91, NULL, 'SAC', '9987', 'Maintenance, repair and installation services (except construction)', 'Maintenance and repair services', 18.00, 'Standard rate shown; verify the precise service, recipient and any applicable exemption.', 1),
(92, NULL, 'SAC', '998713', 'Maintenance and repair services of commercial and industrial machinery', 'Maintenance and repair services', 18.00, 'Standard rate shown; verify the precise service, recipient and any applicable exemption.', 1),
(93, NULL, 'SAC', '998719', 'Maintenance and repair services of other machinery and equipment', 'Maintenance and repair services', 18.00, 'Standard rate shown; verify the precise service, recipient and any applicable exemption.', 1),
(94, NULL, 'SAC', '9988', 'Manufacturing services on physical inputs (goods) owned by others', 'Job work and manufacturing services', NULL, 'Job-work rate depends on the goods and applicable service-rate conditions; verify before billing.', 1),
(95, NULL, 'SAC', '998821', 'Textile and apparel job work, including cutting, stitching, embroidery and finishing', 'Job work and manufacturing services', NULL, 'Rate depends on the goods and applicable job-work conditions; verify before billing.', 1)
ON DUPLICATE KEY UPDATE
    `code_type` = VALUES(`code_type`),
    `hsn_code` = VALUES(`hsn_code`),
    `description` = VALUES(`description`),
    `category` = VALUES(`category`),
    `gst_rate` = VALUES(`gst_rate`),
    `rate_note` = VALUES(`rate_note`),
    `is_active` = VALUES(`is_active`);

SET FOREIGN_KEY_CHECKS = 1;


SET FOREIGN_KEY_CHECKS = 1;
-- =====================================================================
-- END OF UNIFIED MASTER DATABASE SCHEMA
-- =====================================================================
