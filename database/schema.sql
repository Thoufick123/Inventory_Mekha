-- Schema-only baseline; no table drops or application data.
-- Generated from invento_db

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

--
-- Table structure for table `assets`
--

CREATE TABLE IF NOT EXISTS `assets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int DEFAULT NULL,
  `asset_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `current_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','maintenance','disposed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`),
  KEY `idx_asset_concern` (`concern_id`),
  CONSTRAINT `fk_asset_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `audit_log`
--

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `row_id` int DEFAULT NULL,
  `old_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `new_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_concern` (`concern_id`),
  CONSTRAINT `audit_log_chk_1` CHECK (json_valid(`old_json`)),
  CONSTRAINT `audit_log_chk_2` CHECK (json_valid(`new_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `batch_items`
--

CREATE TABLE IF NOT EXISTS `batch_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `batch_id` int NOT NULL,
  `variant_id` int NOT NULL,
  `size` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `taxable_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `gst_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `qr_codes` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_batch_items_batch` (`batch_id`),
  KEY `idx_batch_items_variant` (`variant_id`),
  KEY `idx_batch_items_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `batches`
--

CREATE TABLE IF NOT EXISTS `batches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `production_unit_id` int DEFAULT NULL,
  `batch_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_id` int DEFAULT NULL,
  `program_id` int DEFAULT NULL,
  `cutting_batch_id` int DEFAULT NULL,
  `stitching_batch_id` int DEFAULT NULL,
  `qc_batch_id` int DEFAULT NULL,
  `additional_qc_batch_ids` text COLLATE utf8mb4_unicode_ci,
  `batch_qty` decimal(12,2) NOT NULL,
  `total_boxes` int DEFAULT '0',
  `warehouse_id` int DEFAULT NULL,
  `warehouse_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `packed_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT 'System Administrator',
  `rework_included` tinyint(1) DEFAULT '0',
  `qr_codes` longtext COLLATE utf8mb4_unicode_ci,
  `taxable_value` decimal(12,2) DEFAULT '0.00',
  `gst_amount` decimal(12,2) DEFAULT '0.00',
  `total_amount` decimal(12,2) DEFAULT '0.00',
  `bill_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outward_id` int DEFAULT NULL,
  `fabric_details` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'GREEN / BIRDS',
  `mfg_date` date DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bt_concern` (`concern_id`),
  KEY `fk_bt_var` (`variant_id`),
  CONSTRAINT `fk_bt_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bt_var` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `brands`
--

CREATE TABLE IF NOT EXISTS `brands` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_brand_concern` (`concern_id`),
  CONSTRAINT `fk_brand_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `cash_accounts`
--

CREATE TABLE IF NOT EXISTS `cash_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_type` enum('cash','bank','petty_cash') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `account_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ca_concern` (`concern_id`),
  CONSTRAINT `fk_ca_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `cash_transactions`
--

CREATE TABLE IF NOT EXISTS `cash_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `account_id` int NOT NULL,
  `txn_date` date NOT NULL,
  `type` enum('credit','debit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `ref_module` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_id` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ctx_concern` (`concern_id`),
  KEY `fk_ctx_acc` (`account_id`),
  CONSTRAINT `fk_ctx_acc` FOREIGN KEY (`account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ctx_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `cashier_shifts`
--

CREATE TABLE IF NOT EXISTS `cashier_shifts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `user_id` int NOT NULL,
  `cashier_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shift_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `opening_notes` text COLLATE utf8mb4_unicode_ci,
  `starting_float` decimal(12,2) NOT NULL DEFAULT '0.00',
  `is_float_modified` tinyint(1) NOT NULL DEFAULT '0',
  `denominations_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `closing_notes` text COLLATE utf8mb4_unicode_ci,
  `closing_float` decimal(12,2) DEFAULT NULL,
  `closing_denominations_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `expected_closing_float` decimal(12,2) DEFAULT NULL,
  `variance` decimal(12,2) DEFAULT NULL,
  `variance_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('open','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `opened_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cs_concern` (`concern_id`),
  KEY `idx_cs_user` (`user_id`),
  CONSTRAINT `fk_cs_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cashier_shifts_chk_1` CHECK (json_valid(`denominations_json`)),
  CONSTRAINT `cashier_shifts_chk_2` CHECK (json_valid(`closing_denominations_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `catalog_mappings`
--

CREATE TABLE IF NOT EXISTS `catalog_mappings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `purchase_bill_item_id` int NOT NULL,
  `catalog_id` int NOT NULL,
  `variant_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `subcategory_id` int DEFAULT NULL,
  `sub_sub_category_id` int DEFAULT NULL,
  `selected_sizes` text COLLATE utf8mb4_unicode_ci,
  `selected_color` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('mapped','unmapped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mapped',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cmap_concern` (`concern_id`),
  KEY `idx_cmap_pbi` (`purchase_bill_item_id`),
  KEY `idx_cmap_catalog` (`catalog_id`),
  CONSTRAINT `fk_cmap_cat` FOREIGN KEY (`catalog_id`) REFERENCES `catalogs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cmap_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cmap_pbi` FOREIGN KEY (`purchase_bill_item_id`) REFERENCES `purchase_bill_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `catalogs`
--

CREATE TABLE IF NOT EXISTS `catalogs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `style_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `subcategory_id` int DEFAULT NULL,
  `sub_sub_category_id` int DEFAULT NULL,
  `main_category_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_category_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_sub_category_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hierarchy_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hsn_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gst_rate` decimal(5,2) DEFAULT NULL,
  `sizes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive','in_house') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_house',
  `source_module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'production_catalog',
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_catlg_concern` (`concern_id`),
  CONSTRAINT `fk_catlg_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `categories`
--

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_hsn` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_gst_rate` decimal(5,2) DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cat_concern` (`concern_id`),
  CONSTRAINT `fk_cat_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `concern_damages`
--

CREATE TABLE IF NOT EXISTS `concern_damages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `damage_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_id` int NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `location` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Main Store',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `status` enum('LOGGED','INSPECTED','RESOLVED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LOGGED',
  `severity` enum('MINOR','MODERATE','SEVERE','SCRAP') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MODERATE',
  `resolution_action` enum('SCRAP','WRITE_OFF','SALVAGE','REPAIR','REWORK','RETURN_TO_SUPPLIER','SUPPLIER_REPLACEMENT') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `loss_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `photo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `replacement_variant_id` int DEFAULT NULL,
  `replacement_qr_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `replacement_inwarded_at` datetime DEFAULT NULL,
  `resolved_by` int DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `cancelled_by` int DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cd_concern` (`concern_id`),
  KEY `fk_cd_variant` (`variant_id`),
  CONSTRAINT `fk_cd_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cd_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `concern_modules`
--

CREATE TABLE IF NOT EXISTS `concern_modules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `module_id` int NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_concern_module` (`concern_id`,`module_id`),
  KEY `fk_cm_module` (`module_id`),
  CONSTRAINT `fk_cm_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cm_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `concerns`
--

CREATE TABLE IF NOT EXISTS `concerns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `gstin` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `customer_requests`
--

CREATE TABLE IF NOT EXISTS `customer_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `request_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int NOT NULL,
  `variant_id` int NOT NULL,
  `requested_qty` decimal(12,2) NOT NULL,
  `expected_date` date DEFAULT NULL,
  `status` enum('pending','approved','allocated','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr_concern` (`concern_id`),
  KEY `fk_cr_cust` (`customer_id`),
  KEY `fk_cr_var` (`variant_id`),
  CONSTRAINT `fk_cr_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cr_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_cr_var` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `customers`
--

CREATE TABLE IF NOT EXISTS `customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gstin` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cust_concern` (`concern_id`),
  CONSTRAINT `fk_cust_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `cutting_items`
--

CREATE TABLE IF NOT EXISTS `cutting_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cutting_id` int NOT NULL,
  `program_roll_id` int DEFAULT NULL,
  `variant_id` int DEFAULT NULL,
  `size` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cut_pieces` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ci_cutting` (`cutting_id`),
  KEY `idx_ci_roll` (`program_roll_id`),
  KEY `idx_ci_variant` (`variant_id`),
  CONSTRAINT `fk_ci_cutting` FOREIGN KEY (`cutting_id`) REFERENCES `cutting_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `cutting_logs`
--

CREATE TABLE IF NOT EXISTS `cutting_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `log_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lot_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cutting_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('in_progress','planned','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'in_progress',
  `cutting_master_id` int DEFAULT NULL,
  `cutting_master_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `program_id` int DEFAULT NULL,
  `raw_variant_id` int DEFAULT NULL,
  `consumed_qty` decimal(12,2) NOT NULL,
  `total_roll_weight` decimal(12,2) DEFAULT '0.00',
  `total_roll_price` decimal(12,2) DEFAULT '0.00',
  `fabric_rate_per_kg` decimal(12,2) DEFAULT '0.00',
  `cut_pieces` int NOT NULL,
  `wastage_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `wastage_cost` decimal(12,2) DEFAULT '0.00',
  `wastage_percent` decimal(5,2) DEFAULT '0.00',
  `used_weight` decimal(12,2) DEFAULT '0.00',
  `fabric_cost_per_piece` decimal(12,2) DEFAULT '0.00',
  `wastage_unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kg',
  `sold_wastage_qty` decimal(12,2) DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cut_concern` (`concern_id`),
  KEY `fk_cut_prog` (`program_id`),
  KEY `fk_cut_var` (`raw_variant_id`),
  CONSTRAINT `fk_cut_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cut_prog` FOREIGN KEY (`program_id`) REFERENCES `production_programs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cut_var` FOREIGN KEY (`raw_variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `cutting_masters`
--

CREATE TABLE IF NOT EXISTS `cutting_masters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cm_concern` (`concern_id`),
  CONSTRAINT `fk_cutm_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `cutting_wastage_sales`
--

CREATE TABLE IF NOT EXISTS `cutting_wastage_sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `sale_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cutting_id` int NOT NULL,
  `buyer_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `buyer_phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `buyer_company` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wastage_category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wastage_weight` decimal(12,2) NOT NULL,
  `selling_rate` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `amount_received` decimal(12,2) DEFAULT '0.00',
  `sale_date` date NOT NULL,
  `payment_status` enum('paid','partial','pending') COLLATE utf8mb4_unicode_ci DEFAULT 'paid',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cws_concern` (`concern_id`),
  KEY `idx_cws_cutting` (`cutting_id`),
  CONSTRAINT `fk_cws_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cws_cutting` FOREIGN KEY (`cutting_id`) REFERENCES `cutting_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `dispatches`
--

CREATE TABLE IF NOT EXISTS `dispatches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `dispatch_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `outward_id` int NOT NULL,
  `transporter_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dispatch_date` date NOT NULL,
  `status` enum('in_transit','delivered','returned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_transit',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_disp_concern` (`concern_id`),
  KEY `fk_disp_outw` (`outward_id`),
  CONSTRAINT `fk_disp_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_disp_outw` FOREIGN KEY (`outward_id`) REFERENCES `outwards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `doc_sequences`
--

CREATE TABLE IF NOT EXISTS `doc_sequences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `doc_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_year` int NOT NULL,
  `prefix` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_seq` int NOT NULL DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_concern_doc_year` (`concern_id`,`doc_type`,`doc_year`),
  KEY `idx_ds_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `doc_series`
--

CREATE TABLE IF NOT EXISTS `doc_series` (
  `concern_id` int NOT NULL,
  `doc_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `next_no` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`concern_id`,`doc_type`),
  CONSTRAINT `fk_ds_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `expense_categories`
--

CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_concern_exp_cat` (`concern_id`,`name`),
  KEY `idx_exp_cat_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `expenses`
--

CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `expense_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expense_date` date NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paid_to` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_particulars` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_mode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Cash',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Paid',
  `account_id` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_exp_concern` (`concern_id`),
  CONSTRAINT `fk_exp_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `fabric_roll_spec_overrides`
--

CREATE TABLE IF NOT EXISTS `fabric_roll_spec_overrides` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `source_color` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_design_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `design_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hsn_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `net_weight` decimal(12,2) NOT NULL DEFAULT '0.00',
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Rolls',
  `last_unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_frso_source` (`concern_id`,`source_color`,`source_design_name`),
  KEY `idx_frso_concern` (`concern_id`),
  CONSTRAINT `fk_frso_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `fabric_roll_specs`
--

CREATE TABLE IF NOT EXISTS `fabric_roll_specs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `color` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `design_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `net_weight` decimal(12,2) NOT NULL DEFAULT '0.00',
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Rolls',
  `last_unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `bills_count` int NOT NULL DEFAULT '1',
  `last_bill_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_supplier_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hsn_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_concern_color_design` (`concern_id`,`color`,`design_name`),
  KEY `idx_frs_concern` (`concern_id`),
  CONSTRAINT `fk_frs_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `hsn_masters`
--

CREATE TABLE IF NOT EXISTS `hsn_masters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int DEFAULT NULL,
  `code_type` enum('HSN','SAC') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'HSN',
  `hsn_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gst_rate` decimal(5,2) DEFAULT NULL,
  `rate_note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hsn_concern` (`concern_id`),
  KEY `idx_hsn_code` (`hsn_code`),
  KEY `idx_hsn_active_type` (`is_active`,`code_type`),
  KEY `idx_hsn_lookup` (`concern_id`,`code_type`,`hsn_code`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `inward_items`
--

CREATE TABLE IF NOT EXISTS `inward_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `inward_id` int NOT NULL,
  `variant_id` int NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `accepted_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `rejected_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_ini_concern` (`concern_id`),
  KEY `fk_ini_inward` (`inward_id`),
  KEY `fk_ini_variant` (`variant_id`),
  CONSTRAINT `fk_ini_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ini_inward` FOREIGN KEY (`inward_id`) REFERENCES `inwards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ini_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `inwards`
--

CREATE TABLE IF NOT EXISTS `inwards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `production_unit_id` int DEFAULT NULL,
  `inward_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grn_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `po_id` int DEFAULT NULL,
  `purchase_bill_id` int DEFAULT NULL,
  `outward_id` int DEFAULT NULL,
  `from_concern_id` int DEFAULT NULL,
  `inward_type` enum('purchase','transfer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'purchase',
  `supplier_id` int DEFAULT NULL,
  `inward_date` date NOT NULL,
  `location` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Main Store',
  `warehouse_id` int DEFAULT NULL,
  `vehicle_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_by` int DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VERIFIED',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inw_concern` (`concern_id`),
  KEY `fk_inw_pb` (`purchase_bill_id`),
  KEY `fk_inw_sup` (`supplier_id`),
  CONSTRAINT `fk_inw_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inw_pb` FOREIGN KEY (`purchase_bill_id`) REFERENCES `purchase_bills` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inw_sup` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `label_jobs`
--

CREATE TABLE IF NOT EXISTS `label_jobs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `label_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'production',
  `source_module` varchar(50) NOT NULL DEFAULT 'label_generation',
  `ref_id` int DEFAULT NULL,
  `variant_id` int DEFAULT NULL,
  `item_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_payload` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` int NOT NULL DEFAULT '1',
  `printed_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_label_concern` (`concern_id`),
  KEY `fk_lj_variant` (`variant_id`),
  CONSTRAINT `fk_lj_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lj_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `modules`
--

CREATE TABLE IF NOT EXISTS `modules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bi-grid',
  `sort_order` int NOT NULL DEFAULT '0',
  `group_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_super_admin_only` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `outward_items`
--

CREATE TABLE IF NOT EXISTS `outward_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `outward_id` int NOT NULL,
  `variant_id` int NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_owi_concern` (`concern_id`),
  KEY `fk_owi_outw` (`outward_id`),
  KEY `fk_owi_var` (`variant_id`),
  CONSTRAINT `fk_owi_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_owi_outw` FOREIGN KEY (`outward_id`) REFERENCES `outwards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_owi_var` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `outwards`
--

CREATE TABLE IF NOT EXISTS `outwards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `production_unit_id` int DEFAULT NULL,
  `outward_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sales_invoice_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `transfer_type` enum('customer','concern') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `destination_concern_id` int DEFAULT NULL,
  `damage_transfer` tinyint(1) NOT NULL DEFAULT '0',
  `outward_date` date NOT NULL,
  `location` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Main Store',
  `warehouse_id` int DEFAULT NULL,
  `warehouse_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT 'wareouse (WH-01)',
  `total_boxes` int DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `vehicle_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_contact` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dispatched_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT 'System Administrator',
  `transporter_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lr_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gate_pass_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'prepared',
  `dispatched_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_outw_concern` (`concern_id`),
  KEY `fk_outw_si` (`sales_invoice_id`),
  KEY `fk_outw_cust` (`customer_id`),
  CONSTRAINT `fk_outw_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_outw_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_outw_si` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `product_attributes`
--

CREATE TABLE IF NOT EXISTS `product_attributes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `subcategory_id` int DEFAULT NULL,
  `sub_sub_category_id` int DEFAULT NULL,
  `attribute_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'size',
  `attribute_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attribute_value` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pattr_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `production_outward_batches`
--

CREATE TABLE IF NOT EXISTS `production_outward_batches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL DEFAULT '1',
  `outward_id` int NOT NULL,
  `batch_id` int NOT NULL,
  `cartons_count` int DEFAULT '0',
  `pieces_count` decimal(12,2) DEFAULT '0.00',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pob_outward` (`outward_id`),
  KEY `idx_pob_batch` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `production_programs`
--

CREATE TABLE IF NOT EXISTS `production_programs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `program_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `program_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `planner_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `catalog_id` int DEFAULT NULL,
  `target_variant_id` int DEFAULT NULL,
  `planned_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `start_date` date DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `status` enum('planned','in_progress','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planned',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prog_concern` (`concern_id`),
  KEY `fk_prog_cat` (`catalog_id`),
  KEY `fk_prog_var` (`target_variant_id`),
  CONSTRAINT `fk_prog_cat` FOREIGN KEY (`catalog_id`) REFERENCES `catalogs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prog_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prog_var` FOREIGN KEY (`target_variant_id`) REFERENCES `variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `production_return_exchange_items`
--

CREATE TABLE IF NOT EXISTS `production_return_exchange_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `return_id` int NOT NULL,
  `direction` enum('IN','OUT') COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_id` int NOT NULL,
  `sales_invoice_item_id` int DEFAULT NULL,
  `tag_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scanned_codes` text COLLATE utf8mb4_unicode_ci,
  `qty` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `condition_state` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Good',
  `restock_action` enum('RESTOCK','REWORK','SCRAP') COLLATE utf8mb4_unicode_ci DEFAULT 'RESTOCK',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prei_return` (`return_id`),
  KEY `idx_prei_variant` (`variant_id`),
  KEY `idx_prei_direction` (`direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `production_return_items`
--

CREATE TABLE IF NOT EXISTS `production_return_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `return_id` int NOT NULL,
  `variant_id` int NOT NULL,
  `tag_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `condition_state` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Good',
  `action` enum('RESTOCK','REWORK','SCRAP') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RESTOCK',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pri_return` (`return_id`),
  KEY `idx_pri_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `production_returns`
--

CREATE TABLE IF NOT EXISTS `production_returns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `return_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('RETURN','EXCHANGE','CREDIT_NOTE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RETURN',
  `status` enum('RECEIVED','INSPECTED','RESTOCKED','COMPLETED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RECEIVED',
  `customer_id` int DEFAULT NULL,
  `sales_invoice_id` int DEFAULT NULL,
  `return_date` date NOT NULL,
  `action_type` enum('RESTOCKED','SENT_TO_REWORK','SCRAPPED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RESTOCKED',
  `exchange_of_return_id` int DEFAULT NULL,
  `settlement_mode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Credit Note',
  `settlement_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_difference` decimal(12,2) NOT NULL DEFAULT '0.00',
  `refund_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `extra_charge` decimal(12,2) NOT NULL DEFAULT '0.00',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `photo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspected_at` datetime DEFAULT NULL,
  `inspected_by` int DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_no` (`return_no`),
  KEY `idx_pr_concern` (`concern_id`),
  KEY `idx_pr_status` (`status`),
  KEY `idx_pr_invoice` (`sales_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `production_stock_request_audits`
--

CREATE TABLE IF NOT EXISTS `production_stock_request_audits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` int DEFAULT NULL,
  `qty` decimal(12,2) DEFAULT NULL,
  `actor_id` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_psra_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `production_stock_request_items`
--

CREATE TABLE IF NOT EXISTS `production_stock_request_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `variant_id` int NOT NULL,
  `requested_qty` decimal(12,2) NOT NULL,
  `dispatched_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `in_transit_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `received_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `shortage_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_psri_request` (`request_id`),
  KEY `idx_psri_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `production_stock_requests`
--

CREATE TABLE IF NOT EXISTS `production_stock_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `source_concern_id` int DEFAULT NULL,
  `destination_concern_id` int DEFAULT NULL,
  `request_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INTER_MODULE',
  `from_location_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'WAREHOUSE',
  `from_location_id` int DEFAULT NULL,
  `from_module` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_unit_id` int DEFAULT NULL,
  `to_location_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SHOP',
  `to_location_id` int DEFAULT NULL,
  `to_module` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_unit_id` int DEFAULT NULL,
  `status` enum('PENDING','APPROVED','DISPATCHED','IN_TRANSIT','RECEIVED','REJECTED','CANCELLED','SHORTAGE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NORMAL',
  `requested_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `dispatched_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `in_transit_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `received_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `shortage_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `expected_date` date DEFAULT NULL,
  `dispatch_challan_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `dispatched_at` datetime DEFAULT NULL,
  `dispatched_by` int DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `received_by` int DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_no` (`request_no`),
  KEY `idx_psr_concern` (`concern_id`),
  KEY `idx_psr_status` (`status`),
  KEY `idx_psr_from` (`from_location_type`,`from_location_id`),
  KEY `idx_psr_to` (`to_location_type`,`to_location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `production_units`
--

CREATE TABLE IF NOT EXISTS `production_units` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL DEFAULT '1',
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manager_id` int DEFAULT NULL,
  `manager_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity_per_day` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_pu_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `program_rolls`
--

CREATE TABLE IF NOT EXISTS `program_rolls` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `program_id` int NOT NULL,
  `purchase_bill_item_id` int DEFAULT NULL,
  `roll_piece_tag` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fabric_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kg',
  `total_roll_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `allocated_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `roll_weight` decimal(12,2) NOT NULL DEFAULT '0.00',
  `roll_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `target_pieces` int NOT NULL DEFAULT '0',
  `category_id` int DEFAULT NULL,
  `subcategory_id` int DEFAULT NULL,
  `sub_sub_category_id` int DEFAULT NULL,
  `catalog_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prgroll_concern` (`concern_id`),
  KEY `idx_prgroll_program` (`program_id`),
  KEY `idx_prgroll_rollitem` (`purchase_bill_item_id`),
  CONSTRAINT `fk_prgroll_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prgroll_program` FOREIGN KEY (`program_id`) REFERENCES `production_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `purchase_bill_items`
--

CREATE TABLE IF NOT EXISTS `purchase_bill_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `purchase_bill_id` int NOT NULL,
  `variant_id` int DEFAULT NULL,
  `catalog_id` int DEFAULT NULL,
  `section_type` enum('rolls','garment_other','finished_goods') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rolls',
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hsn_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specification` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pattern` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `designs_count` int NOT NULL DEFAULT '1',
  `rq_qty` int NOT NULL DEFAULT '1',
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kg',
  `gross_weight` decimal(12,2) DEFAULT NULL,
  `net_weight` decimal(12,2) DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL,
  `inward_status` enum('pending','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `unit_price` decimal(12,2) NOT NULL,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `gst_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_mode` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Exclude GST',
  `total_price` decimal(12,2) NOT NULL,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mrp` decimal(12,2) DEFAULT NULL,
  `selling_price` decimal(12,2) DEFAULT NULL,
  `design_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rolls_count` int DEFAULT NULL,
  `weight_per_roll` decimal(10,3) DEFAULT NULL,
  `fabric_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gsm` int DEFAULT NULL,
  `dia` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inward_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pbi_concern` (`concern_id`),
  KEY `fk_pbi_bill` (`purchase_bill_id`),
  KEY `fk_pbi_variant` (`variant_id`),
  CONSTRAINT `fk_pbi_bill` FOREIGN KEY (`purchase_bill_id`) REFERENCES `purchase_bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pbi_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pbi_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `purchase_bills`
--

CREATE TABLE IF NOT EXISTS `purchase_bills` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `bill_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_invoice_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_module` varchar(50) NOT NULL DEFAULT 'purchase_billing',
  `purchase_billing_completed` tinyint(1) NOT NULL DEFAULT '0',
  `product_adding_completed` tinyint(1) NOT NULL DEFAULT '0',
  `supplier_id` int NOT NULL,
  `bill_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('unpaid','partial','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'credit',
  `payment_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `inward_status` enum('pending','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_paths` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fabric_description` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `inward_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pb_concern` (`concern_id`),
  KEY `fk_pb_supplier` (`supplier_id`),
  CONSTRAINT `fk_pb_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pb_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `purchase_bills_chk_1` CHECK (json_valid(`attachment_paths`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `purchase_damage_quarantine`
--

CREATE TABLE IF NOT EXISTS `purchase_damage_quarantine` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `damage_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_bill_item_id` int DEFAULT NULL,
  `variant_id` int DEFAULT NULL,
  `supplier_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tag_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `roll_source_color` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `roll_source_design_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT '1.00',
  `damaged_weight` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estimated_loss` decimal(12,2) NOT NULL DEFAULT '0.00',
  `location` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Main Store',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Quarantined',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pdq_concern_date` (`concern_id`,`created_at`),
  KEY `idx_pdq_purchase_item` (`purchase_bill_item_id`),
  KEY `idx_pdq_variant` (`variant_id`),
  CONSTRAINT `fk_pdq_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `purchase_damage_reasons`
--

CREATE TABLE IF NOT EXISTS `purchase_damage_reasons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pdr_concern_reason` (`concern_id`,`reason`),
  CONSTRAINT `fk_pdr_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `purchase_damages`
--

CREATE TABLE IF NOT EXISTS `purchase_damages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `damage_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_id` int NOT NULL,
  `variant_id` int NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `location` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Main Store',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `action_taken` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pd_concern` (`concern_id`),
  KEY `fk_pd_supplier` (`supplier_id`),
  KEY `fk_pd_variant` (`variant_id`),
  CONSTRAINT `fk_pd_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pd_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_pd_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `purchase_stock_item_overrides`
--

CREATE TABLE IF NOT EXISTS `purchase_stock_item_overrides` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `purchase_bill_item_id` int NOT NULL,
  `section_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `item_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `specification` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `color` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `mrp` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `label_job_baseline_id` int NOT NULL DEFAULT '0',
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_psio_item` (`concern_id`,`purchase_bill_item_id`),
  KEY `idx_psio_section` (`concern_id`,`section_type`),
  KEY `fk_psio_item` (`purchase_bill_item_id`),
  CONSTRAINT `fk_psio_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psio_item` FOREIGN KEY (`purchase_bill_item_id`) REFERENCES `purchase_bill_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `qc_inspection_items`
--

CREATE TABLE IF NOT EXISTS `qc_inspection_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `qc_id` int NOT NULL,
  `catalog_id` int DEFAULT NULL,
  `variant_id` int DEFAULT NULL,
  `stitching_item_id` int DEFAULT NULL,
  `size` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspected_qty` int NOT NULL DEFAULT '0',
  `passed_qty` int NOT NULL DEFAULT '0',
  `rejected_qty` int NOT NULL DEFAULT '0',
  `rework_qty` int NOT NULL DEFAULT '0',
  `defect_notes` text COLLATE utf8mb4_unicode_ci,
  `rework_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `qc_id` (`qc_id`),
  KEY `variant_id` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `qc_inspections`
--

CREATE TABLE IF NOT EXISTS `qc_inspections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `qc_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant_id` int DEFAULT NULL,
  `program_id` int DEFAULT NULL,
  `cutting_batch_id` int DEFAULT NULL,
  `stitching_batch_id` int DEFAULT NULL,
  `inspected_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `passed_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `rework_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `rejected_qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `location` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Main Store',
  `defect_notes` text COLLATE utf8mb4_unicode_ci,
  `rework_notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'COMPLETED',
  `inspector_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qc_concern` (`concern_id`),
  KEY `fk_qc_var` (`variant_id`),
  CONSTRAINT `fk_qc_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qc_var` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `qc_piece_items`
--

CREATE TABLE IF NOT EXISTS `qc_piece_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `qc_id` int NOT NULL,
  `qc_item_id` int DEFAULT NULL,
  `variant_id` int DEFAULT NULL,
  `stitching_item_id` int DEFAULT NULL,
  `tag_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `piece_number` int NOT NULL DEFAULT '1',
  `total_variant_pieces` int NOT NULL DEFAULT '1',
  `size` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `catalog_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verdict` enum('PENDING','PASSED','REJECTED','REWORK') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `defect_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `qc_id` (`qc_id`),
  KEY `tag_code` (`tag_code`),
  KEY `verdict` (`verdict`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `return_exchange_items`
--

CREATE TABLE IF NOT EXISTS `return_exchange_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `return_id` int NOT NULL,
  `direction` enum('IN','OUT') COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_id` int NOT NULL,
  `tag_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `condition_state` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Good',
  `restock_action` enum('RESTOCK','REWORK','SCRAP') COLLATE utf8mb4_unicode_ci DEFAULT 'RESTOCK',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rei_return` (`return_id`),
  KEY `idx_rei_variant` (`variant_id`),
  KEY `idx_rei_direction` (`direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `returns_exchanges`
--

CREATE TABLE IF NOT EXISTS `returns_exchanges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `return_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('return','exchange') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'return',
  `customer_id` int NOT NULL,
  `sales_invoice_id` int DEFAULT NULL,
  `return_date` date NOT NULL,
  `returned_variant_id` int NOT NULL,
  `returned_qty` decimal(12,2) NOT NULL,
  `exchange_variant_id` int DEFAULT NULL,
  `exchange_qty` decimal(12,2) DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `role_permissions`
--

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_id` int NOT NULL,
  `module_id` int NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT '1',
  `can_add` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit` tinyint(1) NOT NULL DEFAULT '0',
  `can_delete` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_module` (`role_id`,`module_id`),
  KEY `fk_rp_module` (`module_id`),
  CONSTRAINT `fk_rp_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `roles`
--

CREATE TABLE IF NOT EXISTS `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_roles_concern` (`concern_id`),
  CONSTRAINT `fk_roles_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `sales_invoice_items`
--

CREATE TABLE IF NOT EXISTS `sales_invoice_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `sales_invoice_id` int NOT NULL,
  `variant_id` int NOT NULL,
  `production_batch_id` int DEFAULT NULL,
  `scanned_codes` text COLLATE utf8mb4_unicode_ci,
  `qty` decimal(12,2) NOT NULL,
  `inward_status` enum('pending','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `sales_invoices`
--

CREATE TABLE IF NOT EXISTS `sales_invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `invoice_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int NOT NULL,
  `invoice_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `shipping_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `payment_status` enum('paid','partial','unpaid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paid',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `is_direct_bill` tinyint(1) NOT NULL DEFAULT '0',
  `billing_company_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_company_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_company_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_company_email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_company_gstin` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_si_concern` (`concern_id`),
  KEY `fk_si_cust` (`customer_id`),
  CONSTRAINT `fk_si_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_si_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `shop_damages`
--

CREATE TABLE IF NOT EXISTS `shop_damages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `damage_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` int NOT NULL DEFAULT '1',
  `variant_id` int NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `location` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Retail Shop',
  `damage_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CUSTOMER_DAMAGE',
  `severity` enum('MINOR','MODERATE','SEVERE','SCRAP') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MODERATE',
  `status` enum('LOGGED','INSPECTED','RESOLVED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LOGGED',
  `resolution_action` enum('SCRAP','WRITE_OFF','SALVAGE','REPAIR','REWORK','RETURN_TO_WAREHOUSE','SUPPLIER_REPLACEMENT') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `loss_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `photo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `replacement_variant_id` int DEFAULT NULL,
  `replacement_qr_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `replacement_inwarded_at` datetime DEFAULT NULL,
  `resolved_by` int DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `cancelled_by` int DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `damage_no` (`damage_no`),
  KEY `idx_shd_concern` (`concern_id`),
  KEY `idx_shd_status` (`status`),
  KEY `idx_shd_variant` (`variant_id`),
  KEY `idx_shd_repl_qr` (`replacement_qr_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `staff`
--

CREATE TABLE IF NOT EXISTS `staff` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `staff_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `designation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salary_type` enum('monthly','daily','piece_rate','hourly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `base_salary` decimal(12,2) NOT NULL DEFAULT '0.00',
  `address` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `joining_date` date DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_concern` (`concern_id`),
  KEY `idx_staff_user` (`user_id`),
  KEY `idx_staff_concern_phone` (`concern_id`,`phone`),
  KEY `idx_staff_concern_email` (`concern_id`,`email`),
  CONSTRAINT `fk_staff_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `stitching_items`
--

CREATE TABLE IF NOT EXISTS `stitching_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stitching_id` int NOT NULL,
  `cutting_item_id` int DEFAULT NULL,
  `catalog_id` int DEFAULT NULL,
  `variant_id` int DEFAULT NULL,
  `size` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cut_qty` int NOT NULL DEFAULT '0',
  `stitching_qty` int NOT NULL DEFAULT '0',
  `per_pc_weight` decimal(8,3) DEFAULT '0.000',
  `total_weight` decimal(12,2) DEFAULT '0.00',
  `base_price` decimal(12,2) DEFAULT '0.00',
  `discount_price` decimal(12,2) DEFAULT '0.00',
  `inward_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `inward_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_si_stitching` (`stitching_id`),
  KEY `idx_si_variant` (`variant_id`),
  CONSTRAINT `fk_si_stitching` FOREIGN KEY (`stitching_id`) REFERENCES `stitching_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `stitching_logs`
--

CREATE TABLE IF NOT EXISTS `stitching_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `log_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stitching_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('in_progress','planned','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'in_progress',
  `cutting_id` int DEFAULT NULL,
  `tailor_id` int DEFAULT NULL,
  `tailor_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `program_id` int DEFAULT NULL,
  `input_pieces` int NOT NULL,
  `cut_pieces` int NOT NULL DEFAULT '0',
  `stitched_pieces` int NOT NULL,
  `cut_weight` decimal(12,2) DEFAULT '0.00',
  `stitch_weight` decimal(12,2) DEFAULT '0.00',
  `wastage_pieces` int NOT NULL DEFAULT '0',
  `wastage_weight` decimal(12,2) DEFAULT '0.00',
  `sold_wastage_qty` decimal(12,2) DEFAULT '0.00',
  `produced_variant_id` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `inward_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_stitch_concern` (`concern_id`),
  KEY `fk_stitch_prog` (`program_id`),
  KEY `fk_stitch_var` (`produced_variant_id`),
  CONSTRAINT `fk_stitch_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stitch_prog` FOREIGN KEY (`program_id`) REFERENCES `production_programs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stitch_var` FOREIGN KEY (`produced_variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `stitching_masters`
--

CREATE TABLE IF NOT EXISTS `stitching_masters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sm_concern` (`concern_id`),
  CONSTRAINT `fk_sm_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `stitching_wastage_sales`
--

CREATE TABLE IF NOT EXISTS `stitching_wastage_sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `sale_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stitching_id` int NOT NULL,
  `buyer_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `buyer_phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `buyer_company` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wastage_category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'KG',
  `quantity` decimal(12,2) NOT NULL,
  `selling_rate` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `amount_received` decimal(12,2) DEFAULT '0.00',
  `sale_date` date NOT NULL,
  `payment_status` enum('paid','partial','pending') COLLATE utf8mb4_unicode_ci DEFAULT 'paid',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sws_concern` (`concern_id`),
  KEY `idx_sws_stitching` (`stitching_id`),
  CONSTRAINT `fk_sws_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sws_stitching` FOREIGN KEY (`stitching_id`) REFERENCES `stitching_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `stock_ledger`
--

CREATE TABLE IF NOT EXISTS `stock_ledger` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `variant_id` int NOT NULL,
  `location` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Main Store',
  `qty_in` decimal(12,2) NOT NULL DEFAULT '0.00',
  `qty_out` decimal(12,2) NOT NULL DEFAULT '0.00',
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `mrp` decimal(12,2) NOT NULL DEFAULT '0.00',
  `selling_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `ref_module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_id` int DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ledger_concern` (`concern_id`),
  KEY `idx_ledger_variant` (`variant_id`),
  CONSTRAINT `fk_sl_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sl_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `stocks`
--

CREATE TABLE IF NOT EXISTS `stocks` (
  `concern_id` int NOT NULL,
  `variant_id` int NOT NULL,
  `location` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Main Store',
  `qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`concern_id`,`variant_id`,`location`),
  KEY `idx_stocks_concern` (`concern_id`),
  KEY `fk_stk_variant` (`variant_id`),
  CONSTRAINT `fk_stk_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stk_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `sub_subcategories`
--

CREATE TABLE IF NOT EXISTS `sub_subcategories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `subcategory_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subsubcat_concern` (`concern_id`),
  KEY `idx_subsubcat_subcat` (`subcategory_id`),
  CONSTRAINT `fk_subsubcat_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subsubcat_subcat` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `subcategories`
--

CREATE TABLE IF NOT EXISTS `subcategories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `category_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subcat_concern` (`concern_id`),
  KEY `fk_subcat_category` (`category_id`),
  CONSTRAINT `fk_subcat_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subcat_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `suppliers`
--

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gstin` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sup_concern` (`concern_id`),
  CONSTRAINT `fk_sup_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `user_concerns`
--

CREATE TABLE IF NOT EXISTS `user_concerns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `concern_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_concern` (`user_id`,`concern_id`),
  KEY `idx_uc_user` (`user_id`),
  KEY `idx_uc_concern` (`concern_id`),
  CONSTRAINT `fk_uc_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `user_permissions`
--

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `concern_id` int DEFAULT NULL,
  `module_id` int NOT NULL,
  `can_view` tinyint(1) DEFAULT NULL,
  `can_add` tinyint(1) DEFAULT NULL,
  `can_edit` tinyint(1) DEFAULT NULL,
  `can_delete` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_concern_module` (`user_id`,`concern_id`,`module_id`),
  KEY `idx_up_user` (`user_id`),
  KEY `idx_up_module` (`module_id`),
  CONSTRAINT `fk_up_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int DEFAULT NULL,
  `role_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_concern` (`concern_id`),
  KEY `fk_users_role` (`role_id`),
  KEY `idx_users_phone` (`phone`),
  CONSTRAINT `fk_users_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `variants`
--

CREATE TABLE IF NOT EXISTS `variants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `catalog_id` int DEFAULT NULL,
  `product_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int NOT NULL,
  `subcategory_id` int DEFAULT NULL,
  `sub_sub_category_id` int DEFAULT NULL,
  `brand_id` int DEFAULT NULL,
  `size` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight` decimal(10,3) NOT NULL DEFAULT '0.000',
  `weight_unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kg',
  `attributes` longtext COLLATE utf8mb4_unicode_ci,
  `cost_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `mrp` decimal(12,2) NOT NULL DEFAULT '0.00',
  `is_complementary` tinyint(1) NOT NULL DEFAULT '0',
  `creation_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_concern_product_code` (`concern_id`,`product_code`),
  KEY `idx_var_concern` (`concern_id`),
  KEY `idx_var_catalog` (`catalog_id`),
  KEY `fk_var_cat` (`category_id`),
  KEY `fk_var_subcat` (`subcategory_id`),
  KEY `fk_var_brand` (`brand_id`),
  CONSTRAINT `fk_var_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_var_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `fk_var_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_var_subcat` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `variants_chk_1` CHECK (json_valid(`attributes`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `warehouse_damages`
--

CREATE TABLE IF NOT EXISTS `warehouse_damages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concern_id` int NOT NULL,
  `damage_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` int NOT NULL DEFAULT '1',
  `variant_id` int NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `inward_id` int DEFAULT NULL,
  `location` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Warehouse Floor',
  `damage_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHYSICAL_DAMAGE',
  `severity` enum('MINOR','MODERATE','SEVERE','SCRAP') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MODERATE',
  `status` enum('LOGGED','INSPECTED','RESOLVED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LOGGED',
  `resolution_action` enum('SCRAP','WRITE_OFF','SALVAGE','REPAIR','REWORK','RETURN_TO_SUPPLIER','SUPPLIER_REPLACEMENT') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `loss_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `photo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `replacement_variant_id` int DEFAULT NULL,
  `replacement_qr_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `replacement_inwarded_at` datetime DEFAULT NULL,
  `resolved_by` int DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `cancelled_by` int DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `damage_no` (`damage_no`),
  KEY `idx_whd_concern` (`concern_id`),
  KEY `idx_whd_status` (`status`),
  KEY `idx_whd_variant` (`variant_id`),
  KEY `idx_whd_inward` (`inward_id`),
  KEY `idx_whd_repl_qr` (`replacement_qr_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
