-- ========================================================
-- INVENTO: Multi-Concern Inventory Management System Schema
-- Compatible with Local (XAMPP) & Live Hosting (Hostinger/cPanel)
-- ========================================================

-- Disable foreign key checks for clean creation
SET FOREIGN_KEY_CHECKS = 0;

-- 1. CONCERNS
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

-- 1.1 PRODUCTION UNITS (Workshops & Manufacturing Plants)
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
    CONSTRAINT `fk_pu_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. MODULES REGISTRY
CREATE TABLE IF NOT EXISTS `modules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `icon` VARCHAR(50) NOT NULL DEFAULT 'bi-grid',
    `sort_order` INT NOT NULL DEFAULT 0,
    `group_name` VARCHAR(50) NOT NULL, -- 'Super Admin', 'Dashboard', 'Purchase', 'Production', 'Stock', 'Sales', 'Finance'
    `is_super_admin_only` TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. CONCERN MODULES (Feature toggle per concern)
CREATE TABLE IF NOT EXISTS `concern_modules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `module_id` INT NOT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_concern_module` (`concern_id`, `module_id`),
    CONSTRAINT `fk_cm_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cm_module` FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. ROLES
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NULL, -- NULL = System Super Admin role
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_roles_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. ROLE PERMISSIONS
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `module_id` INT NOT NULL,
    `can_view` TINYINT(1) NOT NULL DEFAULT 1,
    `can_add` TINYINT(1) NOT NULL DEFAULT 0,
    `can_edit` TINYINT(1) NOT NULL DEFAULT 0,
    `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY `uk_role_module` (`role_id`, `module_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_module` FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. USERS
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NULL, -- NULL = Super Admin user
    `role_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `last_login` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. CATEGORIES, SUBCATEGORIES, BRANDS
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NULL,
    `default_hsn` VARCHAR(50) NULL,
    `default_gst_rate` DECIMAL(5,2) NULL DEFAULT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cat_concern` (`concern_id`),
    CONSTRAINT `fk_cat_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `subcategories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_subcat_concern` (`concern_id`),
    CONSTRAINT `fk_subcat_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_subcat_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sub_subcategories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `subcategory_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_subsubcat_concern` (`concern_id`),
    INDEX `idx_subsubcat_subcat` (`subcategory_id`),
    CONSTRAINT `fk_subsubcat_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_subsubcat_subcat` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `brands` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_brand_concern` (`concern_id`),
    CONSTRAINT `fk_brand_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. VARIANTS (Master product detail row)
CREATE TABLE IF NOT EXISTS `variants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `catalog_id` INT NULL,
    `sku_code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `category_id` INT NULL,
    `subcategory_id` INT NULL,
    `brand_id` INT NULL,
    `size` VARCHAR(50) NULL,
    `weight` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `weight_unit` VARCHAR(20) NOT NULL DEFAULT 'kg',
    `attributes` JSON NULL, -- color, fabric, gsm, cut, etc.
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `mrp` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `creation_date` DATE NOT NULL,
    `expiry_date` DATE NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_concern_sku` (`concern_id`, `sku_code`),
    INDEX `idx_var_concern` (`concern_id`),
    INDEX `idx_var_catalog` (`catalog_id`),
    CONSTRAINT `fk_var_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_var_cat` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_var_subcat` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_var_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. STOCKS (Live inventory balance)
CREATE TABLE IF NOT EXISTS `stocks` (
    `concern_id` INT NOT NULL,
    `variant_id` INT NOT NULL,
    `location` VARCHAR(50) NOT NULL DEFAULT 'Main Store',
    `qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`concern_id`, `variant_id`, `location`),
    INDEX `idx_stocks_concern` (`concern_id`),
    CONSTRAINT `fk_stk_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_stk_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. STOCK LEDGER (Append-only audit trail for all stock moves)
CREATE TABLE IF NOT EXISTS `stock_ledger` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `variant_id` INT NOT NULL,
    `location` VARCHAR(50) NOT NULL DEFAULT 'Main Store',
    `qty_in` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `qty_out` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `ref_module` VARCHAR(50) NOT NULL,
    `ref_id` INT NULL,
    `note` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ledger_concern` (`concern_id`),
    INDEX `idx_ledger_variant` (`variant_id`),
    CONSTRAINT `fk_sl_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sl_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. LABEL JOBS (Tracking generated QR labels)
CREATE TABLE IF NOT EXISTS `label_jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `label_type` VARCHAR(50) NOT NULL DEFAULT 'production', -- 'purchase_raw', 'purchase_fg', 'production', 'return'
    `ref_id` INT NULL,
    `variant_id` INT NULL,
    `item_title` VARCHAR(255) NULL,
    `item_code` VARCHAR(100) NULL,
    `qr_payload` VARCHAR(255) NULL,
    `qty` INT NOT NULL DEFAULT 1,
    `printed_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_label_concern` (`concern_id`),
    CONSTRAINT `fk_lj_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lj_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. DOC SERIES (Number sequence generation per doc type)
CREATE TABLE IF NOT EXISTS `doc_series` (
    `concern_id` INT NOT NULL,
    `doc_type` VARCHAR(50) NOT NULL, -- 'INVOICE', 'PO', 'BATCH', 'INWARD', 'OUTWARD', 'REQ', 'DAMAGE'
    `prefix` VARCHAR(20) NOT NULL,
    `next_no` INT NOT NULL DEFAULT 1,
    PRIMARY KEY (`concern_id`, `doc_type`),
    CONSTRAINT `fk_ds_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. AUDIT LOG (Changes across system)
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NULL,
    `user_id` INT NULL,
    `action` VARCHAR(50) NOT NULL,
    `table_name` VARCHAR(50) NOT NULL,
    `row_id` INT NULL,
    `old_json` JSON NULL,
    `new_json` JSON NULL,
    `ip_address` VARCHAR(50) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. SUPPLIERS
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `code` VARCHAR(50) NULL,
    `name` VARCHAR(150) NOT NULL,
    `company_name` VARCHAR(150) NULL,
    `phone` VARCHAR(30) NULL,
    `email` VARCHAR(100) NULL,
    `gstin` VARCHAR(30) NULL,
    `address` TEXT NULL,
    `city` VARCHAR(100) NULL,
    `district` VARCHAR(100) NULL,
    `state` VARCHAR(100) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sup_concern` (`concern_id`),
    CONSTRAINT `fk_sup_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. CUSTOMERS
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `code` VARCHAR(50) NULL,
    `name` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `email` VARCHAR(100) NULL,
    `gstin` VARCHAR(30) NULL,
    `address` TEXT NULL,
    `city` VARCHAR(100) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cust_concern` (`concern_id`),
    CONSTRAINT `fk_cust_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. PURCHASE BILLS & ITEMS
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

CREATE TABLE IF NOT EXISTS `purchase_bills` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `bill_no` VARCHAR(50) NOT NULL,
    `supplier_invoice_no` VARCHAR(100) NULL,
    `supplier_id` INT NOT NULL,
    `bill_date` DATE NOT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `net_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'credit',
    `payment_reference` VARCHAR(100) NULL,
    `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `inward_status` ENUM('pending', 'completed') NOT NULL DEFAULT 'pending',
    `inward_at` DATETIME NULL,
    `notes` TEXT NULL,
    `attachment_path` VARCHAR(255) NULL,
    `attachment_paths` JSON NULL,
    `fabric_description` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pb_concern` (`concern_id`),
    CONSTRAINT `fk_pb_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pb_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `purchase_bill_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `purchase_bill_id` INT NOT NULL,
    `variant_id` INT NULL,
    `catalog_id` INT NULL,
    `section_type` ENUM('rolls', 'garment_other', 'finished_goods') NOT NULL DEFAULT 'rolls',
    `item_name` VARCHAR(255) NULL,
    `item_code` VARCHAR(100) NULL,
    `hsn_code` VARCHAR(30) NULL,
    `specification` VARCHAR(255) NULL,
    `color` VARCHAR(100) NULL,
    `pattern` VARCHAR(150) NULL,
    `designs_count` INT NOT NULL DEFAULT 1,
    `rq_qty` INT NOT NULL DEFAULT 1,
    `unit` VARCHAR(50) NOT NULL DEFAULT 'kg',
    `gross_weight` DECIMAL(12,2) NULL,
    `net_weight` DECIMAL(12,2) NULL,
    `qty` DECIMAL(12,2) NOT NULL,
    `inward_status` ENUM('pending', 'completed') NOT NULL DEFAULT 'pending',
    `inward_at` DATETIME NULL,
    `unit_price` DECIMAL(12,2) NOT NULL,
    `gst_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `gst_type` VARCHAR(50) NULL,
    `tax_mode` VARCHAR(30) NOT NULL DEFAULT 'Exclude GST',
    `total_price` DECIMAL(12,2) NOT NULL,
    `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `photo_path` VARCHAR(255) NULL,
    `category_name` VARCHAR(150) NULL,
    `product_type` VARCHAR(100) NULL,
    `mrp` DECIMAL(12,2) NULL,
    `selling_price` DECIMAL(12,2) NULL,
    INDEX `idx_pbi_concern` (`concern_id`),
    INDEX `idx_pbi_catalog` (`catalog_id`),
    CONSTRAINT `fk_pbi_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pbi_bill` FOREIGN KEY (`purchase_bill_id`) REFERENCES `purchase_bills`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pbi_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `purchase_stock_item_overrides` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `purchase_bill_item_id` INT NOT NULL,
    `section_type` VARCHAR(30) NOT NULL,
    `item_name` VARCHAR(255) NOT NULL DEFAULT '',
    `item_code` VARCHAR(100) NOT NULL DEFAULT '',
    `specification` VARCHAR(255) NOT NULL DEFAULT '',
    `color` VARCHAR(100) NOT NULL DEFAULT '',
    `unit` VARCHAR(50) NOT NULL DEFAULT '',
    `qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `mrp` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `label_job_baseline_id` INT NOT NULL DEFAULT 0,
    `updated_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_psio_item` (`concern_id`, `purchase_bill_item_id`),
    INDEX `idx_psio_section` (`concern_id`, `section_type`),
    CONSTRAINT `fk_psio_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_psio_item` FOREIGN KEY (`purchase_bill_item_id`) REFERENCES `purchase_bill_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16b. FABRIC ROLL SPECIFICATIONS & CONSOLIDATED STOCK REGISTRY
CREATE TABLE IF NOT EXISTS `fabric_roll_specs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `color` VARCHAR(100) NOT NULL,
    `design_name` VARCHAR(150) NOT NULL,
    `qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `net_weight` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `unit` VARCHAR(50) NOT NULL DEFAULT 'Rolls',
    `last_unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `bills_count` INT NOT NULL DEFAULT 1,
    `last_bill_no` VARCHAR(100) NULL,
    `last_supplier_name` VARCHAR(150) NULL,
    `hsn_code` VARCHAR(50) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_concern_color_design` (`concern_id`, `color`, `design_name`),
    INDEX `idx_frs_concern` (`concern_id`),
    CONSTRAINT `fk_frs_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16c. FABRIC ROLL SPECIFICATION OVERRIDES (Live roll editing and specifications)
CREATE TABLE IF NOT EXISTS `fabric_roll_spec_overrides` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `source_color` VARCHAR(100) NOT NULL,
    `source_design_name` VARCHAR(150) NOT NULL,
    `color` VARCHAR(100) NOT NULL,
    `design_name` VARCHAR(150) NOT NULL,
    `hsn_code` VARCHAR(50) NOT NULL DEFAULT '',
    `qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `net_weight` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `unit` VARCHAR(50) NOT NULL DEFAULT 'Rolls',
    `last_unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_frso_source` (`concern_id`, `source_color`, `source_design_name`),
    INDEX `idx_frso_concern` (`concern_id`),
    CONSTRAINT `fk_frso_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. INWARDS & ITEMS
CREATE TABLE IF NOT EXISTS `inwards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `production_unit_id` INT NULL,
    `inward_no` VARCHAR(50) NOT NULL,
    `grn_number` VARCHAR(50) NULL,
    `po_id` INT NULL,
    `purchase_bill_id` INT NULL,
    `outward_id` INT NULL,
    `from_concern_id` INT NULL,
    `inward_type` ENUM('purchase', 'transfer') NOT NULL DEFAULT 'purchase',
    `supplier_id` INT NULL,
    `inward_date` DATE NOT NULL,
    `location` VARCHAR(50) NOT NULL DEFAULT 'Main Store',
    `warehouse_id` INT NULL,
    `vehicle_number` VARCHAR(50) NULL,
    `received_by` INT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'VERIFIED',
    `remarks` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_inw_concern` (`concern_id`),
    CONSTRAINT `fk_inw_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inw_pb` FOREIGN KEY (`purchase_bill_id`) REFERENCES `purchase_bills`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_inw_sup` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inward_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `inward_id` INT NOT NULL,
    `variant_id` INT NOT NULL,
    `qty` DECIMAL(12,2) NOT NULL,
    `accepted_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `rejected_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `rejection_reason` VARCHAR(255) NULL,
    `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    INDEX `idx_ini_concern` (`concern_id`),
    CONSTRAINT `fk_ini_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ini_inward` FOREIGN KEY (`inward_id`) REFERENCES `inwards`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ini_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. PURCHASE DAMAGE
CREATE TABLE IF NOT EXISTS `purchase_damages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `damage_no` VARCHAR(50) NOT NULL,
    `supplier_id` INT NOT NULL,
    `variant_id` INT NOT NULL,
    `qty` DECIMAL(12,2) NOT NULL,
    `location` VARCHAR(50) NOT NULL DEFAULT 'Main Store',
    `reason` TEXT NULL,
    `action_taken` VARCHAR(100) NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pd_concern` (`concern_id`),
    CONSTRAINT `fk_pd_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pd_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
    CONSTRAINT `fk_pd_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Purchase-stock damage records for scanned label quarantine
CREATE TABLE IF NOT EXISTS `purchase_damage_quarantine` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `damage_no` VARCHAR(50) NOT NULL,
    `item_type` VARCHAR(30) NOT NULL,
    `purchase_bill_item_id` INT NULL,
    `variant_id` INT NULL,
    `supplier_name` VARCHAR(150) NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `item_code` VARCHAR(100) NULL,
    `tag_code` VARCHAR(30) NOT NULL,
    `roll_source_color` VARCHAR(100) NULL,
    `roll_source_design_name` VARCHAR(150) NULL,
    `qty` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    `damaged_weight` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `estimated_loss` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `location` VARCHAR(100) NOT NULL DEFAULT 'Main Store',
    `reason` VARCHAR(255) NOT NULL,
    `notes` TEXT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'Quarantined',
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pdq_concern_date` (`concern_id`, `created_at`),
    INDEX `idx_pdq_purchase_item` (`purchase_bill_item_id`),
    INDEX `idx_pdq_variant` (`variant_id`),
    CONSTRAINT `fk_pdq_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `purchase_damage_reasons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_pdr_concern_reason` (`concern_id`, `reason`),
    CONSTRAINT `fk_pdr_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. CONCERN DAMAGE (Internal Stock Damage)
CREATE TABLE IF NOT EXISTS `concern_damages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `damage_no` VARCHAR(50) NOT NULL,
    `variant_id` INT NOT NULL,
    `qty` DECIMAL(12,2) NOT NULL,
    `location` VARCHAR(50) NOT NULL DEFAULT 'Main Store',
    `reason` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cd_concern` (`concern_id`),
    CONSTRAINT `fk_cd_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cd_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. PRODUCTION: CATALOG & PROGRAM
CREATE TABLE IF NOT EXISTS `catalogs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `code` VARCHAR(50) NULL,
    `style_code` VARCHAR(50) NULL,
    `short_code` VARCHAR(50) NULL,
    `category_id` INT NULL,
    `subcategory_id` INT NULL,
    `sub_sub_category_id` INT NULL,
    `main_category_code` VARCHAR(20) NULL,
    `sub_category_code` VARCHAR(20) NULL,
    `sub_sub_category_code` VARCHAR(20) NULL,
    `hierarchy_path` VARCHAR(255) NULL,
    `hsn_code` VARCHAR(50) NULL,
    `gst_rate` DECIMAL(5,2) NULL DEFAULT NULL,
    `sizes` TEXT NULL,
    `status` ENUM('active', 'inactive', 'in_house') NOT NULL DEFAULT 'in_house',
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_catlg_concern` (`concern_id`),
    CONSTRAINT `fk_catlg_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `production_programs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `program_no` VARCHAR(50) NOT NULL,
    `program_name` VARCHAR(255) NULL,
    `planner_name` VARCHAR(150) NULL,
    `catalog_id` INT NULL,
    `target_variant_id` INT NULL,
    `planned_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `start_date` DATE NULL,
    `target_date` DATE NULL,
    `status` ENUM('planned', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'planned',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_prog_concern` (`concern_id`),
    CONSTRAINT `fk_prog_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_prog_cat` FOREIGN KEY (`catalog_id`) REFERENCES `catalogs`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_prog_var` FOREIGN KEY (`target_variant_id`) REFERENCES `variants`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `program_rolls` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `program_id` INT NOT NULL,
    `purchase_bill_item_id` INT NULL,
    `roll_piece_tag` VARCHAR(100) NULL,
    `fabric_name` VARCHAR(255) NULL,
    `color` VARCHAR(100) NULL,
    `unit` VARCHAR(50) NOT NULL DEFAULT 'kg',
    `total_roll_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `allocated_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `roll_weight` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `roll_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `target_pieces` INT NOT NULL DEFAULT 0,
    `category_id` INT NULL,
    `subcategory_id` INT NULL,
    `sub_sub_category_id` INT NULL,
    `catalog_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_prgroll_concern` (`concern_id`),
    INDEX `idx_prgroll_program` (`program_id`),
    INDEX `idx_prgroll_rollitem` (`purchase_bill_item_id`),
    CONSTRAINT `fk_prgroll_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_prgroll_program` FOREIGN KEY (`program_id`) REFERENCES `production_programs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20B. CATALOG MAPPINGS (PURCHASE FINISHED GOODS TO CATALOG)
CREATE TABLE IF NOT EXISTS `catalog_mappings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `purchase_bill_item_id` INT NOT NULL,
    `catalog_id` INT NOT NULL,
    `variant_id` INT NULL,
    `category_id` INT NULL,
    `subcategory_id` INT NULL,
    `sub_sub_category_id` INT NULL,
    `selected_sizes` TEXT NULL,
    `selected_color` VARCHAR(100) NULL,
    `status` ENUM('mapped', 'unmapped') NOT NULL DEFAULT 'mapped',
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cmap_concern` (`concern_id`),
    INDEX `idx_cmap_pbi` (`purchase_bill_item_id`),
    INDEX `idx_cmap_catalog` (`catalog_id`),
    CONSTRAINT `fk_cmap_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cmap_pbi` FOREIGN KEY (`purchase_bill_item_id`) REFERENCES `purchase_bill_items`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cmap_cat` FOREIGN KEY (`catalog_id`) REFERENCES `catalogs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20C. PRODUCT ATTRIBUTES (SIZES, COLORS, ATTRIBUTES)
CREATE TABLE IF NOT EXISTS `product_attributes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `attribute_type` VARCHAR(50) NOT NULL DEFAULT 'size',
    `attribute_name` VARCHAR(100) NOT NULL,
    `attribute_value` VARCHAR(100) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pattr_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 21. CUTTING & WASTAGE
CREATE TABLE IF NOT EXISTS `cutting_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `log_no` VARCHAR(50) NOT NULL,
    `batch_no` VARCHAR(50) NULL,
    `lot_no` VARCHAR(50) NULL,
    `cutting_name` VARCHAR(255) NULL,
    `status` ENUM('in_progress', 'planned', 'completed') DEFAULT 'in_progress',
    `cutting_master_id` INT NULL,
    `cutting_master_name` VARCHAR(150) NULL,
    `program_id` INT NULL,
    `raw_variant_id` INT NULL,
    `consumed_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_roll_weight` DECIMAL(12,2) DEFAULT 0.00,
    `total_roll_price` DECIMAL(12,2) DEFAULT 0.00,
    `fabric_rate_per_kg` DECIMAL(12,2) DEFAULT 0.00,
    `cut_pieces` INT NOT NULL DEFAULT 0,
    `wastage_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `wastage_cost` DECIMAL(12,2) DEFAULT 0.00,
    `wastage_percent` DECIMAL(5,2) DEFAULT 0.00,
    `used_weight` DECIMAL(12,2) DEFAULT 0.00,
    `fabric_cost_per_piece` DECIMAL(12,2) DEFAULT 0.00,
    `wastage_unit` VARCHAR(20) NOT NULL DEFAULT 'kg',
    `sold_wastage_qty` DECIMAL(12,2) DEFAULT 0.00,
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cut_concern` (`concern_id`),
    CONSTRAINT `fk_cut_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cut_prog` FOREIGN KEY (`program_id`) REFERENCES `production_programs`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_cut_var` FOREIGN KEY (`raw_variant_id`) REFERENCES `variants`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 22. STITCHING & WASTAGE
CREATE TABLE IF NOT EXISTS `stitching_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `log_no` VARCHAR(50) NOT NULL,
    `batch_no` VARCHAR(50) NULL,
    `stitching_name` VARCHAR(255) NULL,
    `status` ENUM('in_progress', 'planned', 'completed') DEFAULT 'in_progress',
    `cutting_id` INT NULL,
    `tailor_id` INT NULL,
    `tailor_name` VARCHAR(150) NULL,
    `program_id` INT NULL,
    `input_pieces` INT NOT NULL DEFAULT 0,
    `cut_pieces` INT NOT NULL DEFAULT 0,
    `stitched_pieces` INT NOT NULL DEFAULT 0,
    `cut_weight` DECIMAL(12,2) DEFAULT 0.00,
    `stitch_weight` DECIMAL(12,2) DEFAULT 0.00,
    `wastage_pieces` INT NOT NULL DEFAULT 0,
    `wastage_weight` DECIMAL(12,2) DEFAULT 0.00,
    `sold_wastage_qty` DECIMAL(12,2) DEFAULT 0.00,
    `inward_status` VARCHAR(30) DEFAULT 'pending',
    `produced_variant_id` INT NULL,
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_stitch_concern` (`concern_id`),
    CONSTRAINT `fk_stitch_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_stitch_prog` FOREIGN KEY (`program_id`) REFERENCES `production_programs`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_stitch_var` FOREIGN KEY (`produced_variant_id`) REFERENCES `variants`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 23. QC STOCKS
CREATE TABLE IF NOT EXISTS `qc_inspections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `qc_no` VARCHAR(50) NOT NULL,
    `batch_no` VARCHAR(50) NULL,
    `variant_id` INT NOT NULL,
    `program_id` INT NULL,
    `cutting_batch_id` INT NULL,
    `stitching_batch_id` INT NULL,
    `inspected_qty` DECIMAL(12,2) NOT NULL,
    `passed_qty` DECIMAL(12,2) NOT NULL,
    `rework_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `rejected_qty` DECIMAL(12,2) NOT NULL,
    `location` VARCHAR(50) NOT NULL DEFAULT 'Main Store',
    `defect_notes` TEXT NULL,
    `rework_notes` TEXT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'COMPLETED',
    `inspector_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_qc_concern` (`concern_id`),
    CONSTRAINT `fk_qc_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_qc_var` FOREIGN KEY (`variant_id`) REFERENCES `variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 24. REQUEST (Customer / Branch Demand)
CREATE TABLE IF NOT EXISTS `customer_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `request_no` VARCHAR(50) NOT NULL,
    `customer_id` INT NOT NULL,
    `variant_id` INT NOT NULL,
    `requested_qty` DECIMAL(12,2) NOT NULL,
    `expected_date` DATE NULL,
    `status` ENUM('pending', 'approved', 'allocated', 'rejected') NOT NULL DEFAULT 'pending',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cr_concern` (`concern_id`),
    CONSTRAINT `fk_cr_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cr_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`),
    CONSTRAINT `fk_cr_var` FOREIGN KEY (`variant_id`) REFERENCES `variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 25. BATCH (Production & Packaging Batches PB-YYYYMMDD-XXX)
CREATE TABLE IF NOT EXISTS `batches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `production_unit_id` INT NULL,
    `batch_no` VARCHAR(50) NOT NULL,
    `variant_id` INT NOT NULL,
    `program_id` INT NULL,
    `cutting_batch_id` INT NULL,
    `stitching_batch_id` INT NULL,
    `qc_batch_id` INT NULL,
    `additional_qc_batch_ids` TEXT NULL,
    `batch_qty` DECIMAL(12,2) NOT NULL,
    `total_boxes` INT DEFAULT 0,
    `warehouse_id` INT NULL,
    `warehouse_name` VARCHAR(150) NULL,
    `packed_by_name` VARCHAR(150) DEFAULT 'System Administrator',
    `rework_included` TINYINT(1) DEFAULT 0,
    `qr_codes` LONGTEXT NULL,
    `taxable_value` DECIMAL(12,2) DEFAULT 0.00,
    `gst_amount` DECIMAL(12,2) DEFAULT 0.00,
    `total_amount` DECIMAL(12,2) DEFAULT 0.00,
    `bill_no` VARCHAR(100) NULL,
    `fabric_details` VARCHAR(255) DEFAULT 'GREEN / BIRDS',
    `mfg_date` DATE NULL,
    `exp_date` DATE NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'active',
    `approved_by` INT NULL,
    `approved_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_bt_concern` (`concern_id`),
    CONSTRAINT `fk_bt_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bt_var` FOREIGN KEY (`variant_id`) REFERENCES `variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 26. BILLING (Sales Invoices & Items)
CREATE TABLE IF NOT EXISTS `sales_invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `invoice_no` VARCHAR(50) NOT NULL,
    `customer_id` INT NOT NULL,
    `invoice_date` DATE NOT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `net_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_method` ENUM('cash', 'bank_transfer', 'upi', 'credit') NOT NULL DEFAULT 'cash',
    `payment_status` ENUM('paid', 'partial', 'unpaid') NOT NULL DEFAULT 'paid',
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_si_concern` (`concern_id`),
    CONSTRAINT `fk_si_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_si_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sales_invoice_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `sales_invoice_id` INT NOT NULL,
    `variant_id` INT NOT NULL,
    `qty` DECIMAL(12,2) NOT NULL,
    `unit_price` DECIMAL(12,2) NOT NULL,
    `total_price` DECIMAL(12,2) NOT NULL,
    INDEX `idx_sii_concern` (`concern_id`),
    CONSTRAINT `fk_sii_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sii_inv` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sii_var` FOREIGN KEY (`variant_id`) REFERENCES `variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 27. OUTWARD & ITEMS (Triggers stock deduction via StockService)
CREATE TABLE IF NOT EXISTS `outwards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `production_unit_id` INT NULL,
    `outward_no` VARCHAR(50) NOT NULL,
    `sales_invoice_id` INT NULL,
    `customer_id` INT NULL,
    `transfer_type` ENUM('customer', 'concern') NOT NULL DEFAULT 'customer',
    `destination_concern_id` INT NULL,
    `outward_date` DATE NOT NULL,
    `location` VARCHAR(50) NOT NULL DEFAULT 'Main Store',
    `warehouse_id` INT NULL,
    `warehouse_name` VARCHAR(150) DEFAULT 'wareouse (WH-01)',
    `total_boxes` INT DEFAULT 1,
    `vehicle_number` VARCHAR(100) NULL,
    `driver_name` VARCHAR(150) NULL,
    `driver_contact` VARCHAR(50) NULL,
    `transporter_name` VARCHAR(150) NULL,
    `lr_number` VARCHAR(100) NULL,
    `gate_pass_no` VARCHAR(50) NULL,
    `dispatched_by_name` VARCHAR(150) DEFAULT 'System Administrator',
    `notes` TEXT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'prepared',
    `dispatched_at` DATETIME NULL,
    `received_at` DATETIME NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_outw_concern` (`concern_id`),
    CONSTRAINT `fk_outw_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_outw_si` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_outw_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `outward_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `outward_id` INT NOT NULL,
    `variant_id` INT NOT NULL,
    `qty` DECIMAL(12,2) NOT NULL,
    INDEX `idx_owi_concern` (`concern_id`),
    CONSTRAINT `fk_owi_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_owi_outw` FOREIGN KEY (`outward_id`) REFERENCES `outwards`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_owi_var` FOREIGN KEY (`variant_id`) REFERENCES `variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 27.1 PRODUCTION OUTWARD BATCHES (Multi-batch consolidation mapping)
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

-- 28. DISPATCH
CREATE TABLE IF NOT EXISTS `dispatches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `dispatch_no` VARCHAR(50) NOT NULL,
    `outward_id` INT NOT NULL,
    `transporter_name` VARCHAR(100) NULL,
    `vehicle_no` VARCHAR(50) NULL,
    `tracking_no` VARCHAR(100) NULL,
    `dispatch_date` DATE NOT NULL,
    `status` ENUM('in_transit', 'delivered', 'returned') NOT NULL DEFAULT 'in_transit',
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_disp_concern` (`concern_id`),
    CONSTRAINT `fk_disp_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_disp_outw` FOREIGN KEY (`outward_id`) REFERENCES `outwards`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 29. RETURN & EXCHANGE
CREATE TABLE IF NOT EXISTS `returns_exchanges` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `return_no` VARCHAR(50) NOT NULL,
    `type` ENUM('return', 'exchange') NOT NULL DEFAULT 'return',
    `customer_id` INT NOT NULL,
    `sales_invoice_id` INT NULL,
    `return_date` DATE NOT NULL,
    `returned_variant_id` INT NOT NULL,
    `returned_qty` DECIMAL(12,2) NOT NULL,
    `exchange_variant_id` INT NULL,
    `exchange_qty` DECIMAL(12,2) NULL,
    `reason` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_re_concern` (`concern_id`),
    CONSTRAINT `fk_re_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_re_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`),
    CONSTRAINT `fk_re_si` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_re_ret_var` FOREIGN KEY (`returned_variant_id`) REFERENCES `variants`(`id`),
    CONSTRAINT `fk_re_exc_var` FOREIGN KEY (`exchange_variant_id`) REFERENCES `variants`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 30. EXPENSES
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `expense_no` VARCHAR(50) NOT NULL,
    `expense_date` DATE NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `paid_to` VARCHAR(255) NULL,
    `item_particulars` VARCHAR(255) NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `payment_mode` VARCHAR(50) NOT NULL DEFAULT 'Cash',
    `status` VARCHAR(50) NOT NULL DEFAULT 'Paid',
    `account_id` INT NULL,
    `description` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_exp_concern` (`concern_id`),
    CONSTRAINT `fk_exp_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 31. CASH ACCOUNTS & CASH TRANSACTIONS
CREATE TABLE IF NOT EXISTS `cash_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `account_type` ENUM('cash', 'bank', 'petty_cash') NOT NULL DEFAULT 'cash',
    `account_number` VARCHAR(50) NULL,
    `balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ca_concern` (`concern_id`),
    CONSTRAINT `fk_ca_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cash_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `account_id` INT NOT NULL,
    `txn_date` DATE NOT NULL,
    `type` ENUM('credit', 'debit') NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `ref_module` VARCHAR(50) NULL, -- 'Billing', 'Purchase', 'Expense', 'Manual'
    `ref_id` INT NULL,
    `description` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ctx_concern` (`concern_id`),
    CONSTRAINT `fk_ctx_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ctx_acc` FOREIGN KEY (`account_id`) REFERENCES `cash_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 32. ASSETS (Super Admin Asset Module - Company Assets)
CREATE TABLE IF NOT EXISTS `assets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NULL, -- NULL = Company Headquarter Asset, or assigned to a concern
    `asset_code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(100) NULL, -- 'Machinery', 'Vehicles', 'IT Equipment', 'Furniture'
    `purchase_date` DATE NULL,
    `purchase_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `current_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `location` VARCHAR(100) NULL,
    `status` ENUM('active', 'maintenance', 'disposed') NOT NULL DEFAULT 'active',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_asset_concern` (`concern_id`),
    CONSTRAINT `fk_asset_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 33. CASHIER SHIFTS (Cashier Shift Register & Float Management)
CREATE TABLE IF NOT EXISTS `cashier_shifts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `cashier_name` VARCHAR(150) NOT NULL,
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
    INDEX `idx_cs_concern` (`concern_id`),
    INDEX `idx_cs_user` (`user_id`),
    CONSTRAINT `fk_cs_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 34. USER CONCERNS (Multi-concern access mapping)
CREATE TABLE IF NOT EXISTS `user_concerns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `concern_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user_concern` (`user_id`, `concern_id`),
    INDEX `idx_uc_user` (`user_id`),
    INDEX `idx_uc_concern` (`concern_id`),
    CONSTRAINT `fk_uc_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_uc_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 35. USER PERMISSIONS (Direct user-level module permission overrides)
CREATE TABLE IF NOT EXISTS `user_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `module_id` INT NOT NULL,
    `can_view` TINYINT(1) NOT NULL DEFAULT 1,
    `can_add` TINYINT(1) NOT NULL DEFAULT 0,
    `can_edit` TINYINT(1) NOT NULL DEFAULT 0,
    `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user_module` (`user_id`, `module_id`),
    INDEX `idx_up_user` (`user_id`),
    INDEX `idx_up_module` (`module_id`),
    CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_up_module` FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 36. STAFF (Staff members directory & HR details)
CREATE TABLE IF NOT EXISTS `staff` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `user_id` INT NULL,
    `staff_code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `email` VARCHAR(120) NULL,
    `password_hash` VARCHAR(255) NULL,
    `designation` VARCHAR(100) NULL,
    `department` VARCHAR(100) NULL,
    `salary_type` ENUM('monthly','daily','piece_rate','hourly') NOT NULL DEFAULT 'monthly',
    `base_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `address` TEXT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `joining_date` DATE NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_staff_concern` (`concern_id`),
    INDEX `idx_staff_user` (`user_id`),
    CONSTRAINT `fk_staff_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 37. CUTTING MASTERS (Cutting Master / Operator Registry)
CREATE TABLE IF NOT EXISTS `cutting_masters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `employee_code` VARCHAR(50) NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cm_concern` (`concern_id`),
    CONSTRAINT `fk_cutm_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 38. CUTTING ITEMS (Per-size cut breakdown from cutting log)
CREATE TABLE IF NOT EXISTS `cutting_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cutting_id` INT NOT NULL,
    `program_roll_id` INT NULL,
    `variant_id` INT NULL,
    `size` VARCHAR(50) NULL,
    `sku_code` VARCHAR(100) NULL,
    `variant_name` VARCHAR(255) NULL,
    `cut_pieces` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ci_cutting` (`cutting_id`),
    INDEX `idx_ci_roll` (`program_roll_id`),
    INDEX `idx_ci_variant` (`variant_id`),
    CONSTRAINT `fk_ci_cutting` FOREIGN KEY (`cutting_id`) REFERENCES `cutting_logs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 39. CUTTING WASTAGE SALES (Fabric wastage scrap sales)
CREATE TABLE IF NOT EXISTS `cutting_wastage_sales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `sale_no` VARCHAR(50) NOT NULL,
    `cutting_id` INT NOT NULL,
    `buyer_name` VARCHAR(150) NOT NULL,
    `buyer_phone` VARCHAR(20) NOT NULL,
    `buyer_company` VARCHAR(200) NULL,
    `wastage_category` VARCHAR(100) NOT NULL,
    `wastage_weight` DECIMAL(12,2) NOT NULL,
    `selling_rate` DECIMAL(12,2) NOT NULL,
    `total_price` DECIMAL(12,2) NOT NULL,
    `amount_received` DECIMAL(12,2) DEFAULT 0.00,
    `sale_date` DATE NOT NULL,
    `payment_status` ENUM('paid', 'partial', 'pending') DEFAULT 'paid',
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cws_concern` (`concern_id`),
    INDEX `idx_cws_cutting` (`cutting_id`),
    CONSTRAINT `fk_cws_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cws_cutting` FOREIGN KEY (`cutting_id`) REFERENCES `cutting_logs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 40. STITCHING MASTERS (Tailor / Stitching Master Registry)
CREATE TABLE IF NOT EXISTS `stitching_masters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `employee_code` VARCHAR(50) NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sm_concern` (`concern_id`),
    CONSTRAINT `fk_sm_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 41. STITCHING ITEMS (Per-size assembled garment item details)
CREATE TABLE IF NOT EXISTS `stitching_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stitching_id` INT NOT NULL,
    `cutting_item_id` INT NULL,
    `catalog_id` INT NULL,
    `variant_id` INT NULL,
    `size` VARCHAR(50) NULL,
    `color` VARCHAR(100) NULL,
    `sku_code` VARCHAR(100) NULL,
    `variant_name` VARCHAR(255) NULL,
    `cut_qty` INT NOT NULL DEFAULT 0,
    `stitching_qty` INT NOT NULL DEFAULT 0,
    `per_pc_weight` DECIMAL(8,3) DEFAULT 0.000,
    `total_weight` DECIMAL(12,2) DEFAULT 0.00,
    `base_price` DECIMAL(12,2) DEFAULT 0.00,
    `discount_price` DECIMAL(12,2) DEFAULT 0.00,
    `inward_status` VARCHAR(30) DEFAULT 'pending',
    `inward_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_si_stitching` (`stitching_id`),
    INDEX `idx_si_variant` (`variant_id`),
    CONSTRAINT `fk_si_stitching` FOREIGN KEY (`stitching_id`) REFERENCES `stitching_logs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 42. STITCHING WASTAGE SALES (Stitching scrap / trim sales)
CREATE TABLE IF NOT EXISTS `stitching_wastage_sales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
    `sale_no` VARCHAR(50) NOT NULL,
    `stitching_id` INT NOT NULL,
    `buyer_name` VARCHAR(150) NOT NULL,
    `buyer_phone` VARCHAR(20) NOT NULL,
    `buyer_company` VARCHAR(200) NULL,
    `wastage_category` VARCHAR(100) NOT NULL,
    `unit_type` VARCHAR(20) DEFAULT 'KG',
    `quantity` DECIMAL(12,2) NOT NULL,
    `selling_rate` DECIMAL(12,2) NOT NULL,
    `total_price` DECIMAL(12,2) NOT NULL,
    `amount_received` DECIMAL(12,2) DEFAULT 0.00,
    `sale_date` DATE NOT NULL,
    `payment_status` ENUM('paid', 'partial', 'pending') DEFAULT 'paid',
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sws_concern` (`concern_id`),
    INDEX `idx_sws_stitching` (`stitching_id`),
    CONSTRAINT `fk_sws_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sws_stitching` FOREIGN KEY (`stitching_id`) REFERENCES `stitching_logs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 43. PRODUCTION RETURNS & RMA
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
    INDEX `idx_pr_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- 44. PRODUCTION STOCK REQUESTS
CREATE TABLE IF NOT EXISTS `production_stock_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NOT NULL,
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

-- 45. WAREHOUSE & SHOP DAMAGES
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
    INDEX `idx_whd_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    INDEX `idx_shd_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

