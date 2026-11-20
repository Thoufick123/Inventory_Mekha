-- Migration: 2026_11_20_add_return_exchange_details.sql
-- Module 1: Return & Exchange RMA, Real Exchanges, Multi-line Ledger Tracking, and Cancellations

-- 1. Create production_returns master table
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

-- 2. Create production_return_items (items received from customer)
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
    INDEX `idx_pri_variant` (`variant_id`),
    CONSTRAINT `fk_pri_return` FOREIGN KEY (`return_id`) REFERENCES `production_returns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create production_return_exchange_items (multi-line exchange direction IN/OUT)
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
    INDEX `idx_prei_direction` (`direction`),
    CONSTRAINT `fk_prei_return` FOREIGN KEY (`return_id`) REFERENCES `production_returns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create return_exchange_items for returns_exchanges table parity
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
    INDEX `idx_rei_direction` (`direction`),
    CONSTRAINT `fk_rei_return` FOREIGN KEY (`return_id`) REFERENCES `returns_exchanges`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
