-- Migration: 2026_11_20_refactor_stock_request_locations.sql
-- Module 2: Location-based Stock Requisitions, Strict State Machine, and Audit Timeline

-- 1. Create production_stock_requests table
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
    INDEX `idx_psr_status` (`status`),
    INDEX `idx_psr_from` (`from_location_type`, `from_location_id`),
    INDEX `idx_psr_to` (`to_location_type`, `to_location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Create production_stock_request_items table
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
    INDEX `idx_psri_variant` (`variant_id`),
    CONSTRAINT `fk_psri_request` FOREIGN KEY (`request_id`) REFERENCES `production_stock_requests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create production_stock_request_audits table
CREATE TABLE IF NOT EXISTS `production_stock_request_audits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `item_id` INT NULL,
    `qty` DECIMAL(12,2) NULL,
    `actor_id` INT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_psra_request` (`request_id`),
    CONSTRAINT `fk_psra_request` FOREIGN KEY (`request_id`) REFERENCES `production_stock_requests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
