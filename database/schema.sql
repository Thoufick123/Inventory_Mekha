/*M!999999\- enable the sandbox mode */
-- MariaDB dump 10.19  Distrib 10.11.16-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: invento_db
-- ------------------------------------------------------
-- Server version	10.11.16-MariaDB
-- Schema-only baseline; no table drops or application data.
-- Existing installations should also run the repository migration scripts.

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `assets`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`),
  KEY `idx_asset_concern` (`concern_id`),
  CONSTRAINT `fk_asset_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_concern` (`concern_id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `batches`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `production_unit_id` int(11) DEFAULT NULL,
  `batch_no` varchar(50) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
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
  `outward_id` int(11) DEFAULT NULL,
  `fabric_details` varchar(255) DEFAULT 'GREEN / BIRDS',
  `mfg_date` date DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bt_concern` (`concern_id`),
  KEY `fk_bt_var` (`variant_id`),
  CONSTRAINT `fk_bt_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bt_var` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `batch_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `batch_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `size` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `sku_code` varchar(100) DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `taxable_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gst_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `qr_codes` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_batch_items_batch` (`batch_id`),
  KEY `idx_batch_items_variant` (`variant_id`),
  KEY `idx_batch_items_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `brands`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_brand_concern` (`concern_id`),
  CONSTRAINT `fk_brand_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cash_accounts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `cash_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `account_type` enum('cash','bank','petty_cash') NOT NULL DEFAULT 'cash',
  `account_number` varchar(50) DEFAULT NULL,
  `balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ca_concern` (`concern_id`),
  CONSTRAINT `fk_ca_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cash_transactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ctx_concern` (`concern_id`),
  KEY `fk_ctx_acc` (`account_id`),
  CONSTRAINT `fk_ctx_acc` FOREIGN KEY (`account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ctx_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cashier_shifts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `opened_at` timestamp NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cs_concern` (`concern_id`),
  KEY `idx_cs_user` (`user_id`),
  CONSTRAINT `fk_cs_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `catalog_mappings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cmap_concern` (`concern_id`),
  KEY `idx_cmap_pbi` (`purchase_bill_item_id`),
  KEY `idx_cmap_catalog` (`catalog_id`),
  CONSTRAINT `fk_cmap_cat` FOREIGN KEY (`catalog_id`) REFERENCES `catalogs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cmap_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cmap_pbi` FOREIGN KEY (`purchase_bill_item_id`) REFERENCES `purchase_bill_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `catalogs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `source_module` varchar(50) NOT NULL DEFAULT 'production_catalog',
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_catlg_concern` (`concern_id`),
  CONSTRAINT `fk_catlg_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `default_hsn` varchar(50) DEFAULT NULL,
  `default_gst_rate` decimal(5,2) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cat_concern` (`concern_id`),
  CONSTRAINT `fk_cat_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `concern_damages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cd_concern` (`concern_id`),
  KEY `fk_cd_variant` (`variant_id`),
  CONSTRAINT `fk_cd_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cd_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `concern_modules`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `concern_modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_concern_module` (`concern_id`,`module_id`),
  KEY `fk_cm_module` (`module_id`),
  CONSTRAINT `fk_cm_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cm_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1253 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `concerns`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `customer_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cr_concern` (`concern_id`),
  KEY `fk_cr_cust` (`customer_id`),
  KEY `fk_cr_var` (`variant_id`),
  CONSTRAINT `fk_cr_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cr_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_cr_var` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `customers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cust_concern` (`concern_id`),
  CONSTRAINT `fk_cust_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cutting_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `cutting_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cutting_id` int(11) NOT NULL,
  `program_roll_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `sku_code` varchar(100) DEFAULT NULL,
  `variant_name` varchar(255) DEFAULT NULL,
  `cut_pieces` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ci_cutting` (`cutting_id`),
  KEY `idx_ci_roll` (`program_roll_id`),
  KEY `idx_ci_variant` (`variant_id`),
  CONSTRAINT `fk_ci_cutting` FOREIGN KEY (`cutting_id`) REFERENCES `cutting_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cutting_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `consumed_qty` decimal(12,2) NOT NULL,
  `total_roll_weight` decimal(12,2) DEFAULT 0.00,
  `total_roll_price` decimal(12,2) DEFAULT 0.00,
  `fabric_rate_per_kg` decimal(12,2) DEFAULT 0.00,
  `cut_pieces` int(11) NOT NULL,
  `wastage_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `wastage_cost` decimal(12,2) DEFAULT 0.00,
  `wastage_percent` decimal(5,2) DEFAULT 0.00,
  `used_weight` decimal(12,2) DEFAULT 0.00,
  `fabric_cost_per_piece` decimal(12,2) DEFAULT 0.00,
  `wastage_unit` varchar(20) NOT NULL DEFAULT 'kg',
  `sold_wastage_qty` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cut_concern` (`concern_id`),
  KEY `fk_cut_prog` (`program_id`),
  KEY `fk_cut_var` (`raw_variant_id`),
  CONSTRAINT `fk_cut_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cut_prog` FOREIGN KEY (`program_id`) REFERENCES `production_programs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cut_var` FOREIGN KEY (`raw_variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cutting_masters`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `cutting_masters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `employee_code` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cm_concern` (`concern_id`),
  CONSTRAINT `fk_cutm_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cutting_wastage_sales`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cws_concern` (`concern_id`),
  KEY `idx_cws_cutting` (`cutting_id`),
  CONSTRAINT `fk_cws_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cws_cutting` FOREIGN KEY (`cutting_id`) REFERENCES `cutting_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dispatches`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_disp_concern` (`concern_id`),
  KEY `fk_disp_outw` (`outward_id`),
  CONSTRAINT `fk_disp_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_disp_outw` FOREIGN KEY (`outward_id`) REFERENCES `outwards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `doc_sequences`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `doc_sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `doc_type` varchar(50) NOT NULL,
  `doc_year` int(11) NOT NULL,
  `prefix` varchar(20) NOT NULL,
  `current_seq` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_concern_doc_year` (`concern_id`,`doc_type`,`doc_year`),
  KEY `idx_ds_concern` (`concern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `doc_series`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `doc_series` (
  `concern_id` int(11) NOT NULL,
  `doc_type` varchar(50) NOT NULL,
  `prefix` varchar(20) NOT NULL,
  `next_no` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`concern_id`,`doc_type`),
  CONSTRAINT `fk_ds_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expense_categories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_concern_exp_cat` (`concern_id`,`name`),
  KEY `idx_exp_cat_concern` (`concern_id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expenses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exp_concern` (`concern_id`),
  CONSTRAINT `fk_exp_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fabric_roll_spec_overrides`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_frso_source` (`concern_id`,`source_color`,`source_design_name`),
  KEY `idx_frso_concern` (`concern_id`),
  CONSTRAINT `fk_frso_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fabric_roll_specs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_concern_color_design` (`concern_id`,`color`,`design_name`),
  KEY `idx_frs_concern` (`concern_id`),
  CONSTRAINT `fk_frs_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hsn_masters`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hsn_concern` (`concern_id`),
  KEY `idx_hsn_code` (`hsn_code`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inward_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inwards`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inw_concern` (`concern_id`),
  KEY `fk_inw_pb` (`purchase_bill_id`),
  KEY `fk_inw_sup` (`supplier_id`),
  CONSTRAINT `fk_inw_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inw_pb` FOREIGN KEY (`purchase_bill_id`) REFERENCES `purchase_bills` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inw_sup` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `label_jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `label_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `label_type` varchar(50) NOT NULL DEFAULT 'production',
  `source_module` varchar(50) NOT NULL DEFAULT 'label_generation',
  `ref_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `item_title` varchar(255) DEFAULT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `qr_payload` varchar(255) DEFAULT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `printed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_label_concern` (`concern_id`),
  KEY `fk_lj_variant` (`variant_id`),
  CONSTRAINT `fk_lj_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lj_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `modules`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3880 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `outward_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `outwards`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `notes` text DEFAULT NULL,
  `vehicle_number` varchar(100) DEFAULT NULL,
  `driver_name` varchar(150) DEFAULT NULL,
  `driver_contact` varchar(50) DEFAULT NULL,
  `dispatched_by_name` varchar(150) DEFAULT 'System Administrator',
  `transporter_name` varchar(150) DEFAULT NULL,
  `lr_number` varchar(100) DEFAULT NULL,
  `gate_pass_no` varchar(50) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'prepared',
  `dispatched_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_outw_concern` (`concern_id`),
  KEY `fk_outw_si` (`sales_invoice_id`),
  KEY `fk_outw_cust` (`customer_id`),
  CONSTRAINT `fk_outw_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_outw_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_outw_si` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `product_attributes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `product_attributes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `attribute_type` varchar(50) NOT NULL DEFAULT 'size',
  `attribute_name` varchar(100) NOT NULL,
  `attribute_value` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pattr_concern` (`concern_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `production_outward_batches`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `production_programs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prog_concern` (`concern_id`),
  KEY `fk_prog_cat` (`catalog_id`),
  KEY `fk_prog_var` (`target_variant_id`),
  CONSTRAINT `fk_prog_cat` FOREIGN KEY (`catalog_id`) REFERENCES `catalogs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prog_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prog_var` FOREIGN KEY (`target_variant_id`) REFERENCES `variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `production_return_exchange_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `production_return_exchange_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `direction` enum('IN','OUT') NOT NULL,
  `variant_id` int(11) NOT NULL,
  `tag_code` varchar(50) DEFAULT NULL,
  `scanned_codes` text DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `condition_state` varchar(50) DEFAULT 'Good',
  `restock_action` enum('RESTOCK','REWORK','SCRAP') DEFAULT 'RESTOCK',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prei_return` (`return_id`),
  KEY `idx_prei_variant` (`variant_id`),
  KEY `idx_prei_direction` (`direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `production_return_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `production_return_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `tag_code` varchar(50) DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `condition_state` varchar(50) NOT NULL DEFAULT 'Good',
  `action` enum('RESTOCK','REWORK','SCRAP') NOT NULL DEFAULT 'RESTOCK',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pri_return` (`return_id`),
  KEY `idx_pri_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `production_returns`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_no` (`return_no`),
  KEY `idx_pr_concern` (`concern_id`),
  KEY `idx_pr_status` (`status`),
  KEY `idx_pr_invoice` (`sales_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `production_stock_request_audits`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `production_stock_request_audits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `qty` decimal(12,2) DEFAULT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_psra_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `production_stock_request_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_psri_request` (`request_id`),
  KEY `idx_psri_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `production_stock_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_no` (`request_no`),
  KEY `idx_psr_concern` (`concern_id`),
  KEY `idx_psr_status` (`status`),
  KEY `idx_psr_from` (`from_location_type`,`from_location_id`),
  KEY `idx_psr_to` (`to_location_type`,`to_location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `production_units`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  KEY `idx_pu_concern` (`concern_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `program_rolls`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prgroll_concern` (`concern_id`),
  KEY `idx_prgroll_program` (`program_id`),
  KEY `idx_prgroll_rollitem` (`purchase_bill_item_id`),
  CONSTRAINT `fk_prgroll_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prgroll_program` FOREIGN KEY (`program_id`) REFERENCES `production_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `purchase_bill_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `design_name` varchar(150) DEFAULT NULL,
  `rolls_count` int(11) DEFAULT NULL,
  `weight_per_roll` decimal(10,3) DEFAULT NULL,
  `fabric_type` varchar(100) DEFAULT NULL,
  `gsm` int(11) DEFAULT NULL,
  `dia` varchar(50) DEFAULT NULL,
  `inward_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pbi_concern` (`concern_id`),
  KEY `fk_pbi_bill` (`purchase_bill_id`),
  KEY `fk_pbi_variant` (`variant_id`),
  CONSTRAINT `fk_pbi_bill` FOREIGN KEY (`purchase_bill_id`) REFERENCES `purchase_bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pbi_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pbi_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `purchase_bills`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `purchase_bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `bill_no` varchar(50) NOT NULL,
  `supplier_invoice_no` varchar(100) DEFAULT NULL,
  `source_module` varchar(50) NOT NULL DEFAULT 'purchase_billing',
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `inward_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pb_concern` (`concern_id`),
  KEY `fk_pb_supplier` (`supplier_id`),
  CONSTRAINT `fk_pb_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pb_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `purchase_damage_quarantine`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pdq_concern_date` (`concern_id`,`created_at`),
  KEY `idx_pdq_purchase_item` (`purchase_bill_item_id`),
  KEY `idx_pdq_variant` (`variant_id`),
  CONSTRAINT `fk_pdq_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `purchase_damage_reasons`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `purchase_damage_reasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pdr_concern_reason` (`concern_id`,`reason`),
  CONSTRAINT `fk_pdr_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `purchase_damages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pd_concern` (`concern_id`),
  KEY `fk_pd_supplier` (`supplier_id`),
  KEY `fk_pd_variant` (`variant_id`),
  CONSTRAINT `fk_pd_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pd_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_pd_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `purchase_stock_item_overrides`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_psio_item` (`concern_id`,`purchase_bill_item_id`),
  KEY `idx_psio_section` (`concern_id`,`section_type`),
  KEY `fk_psio_item` (`purchase_bill_item_id`),
  CONSTRAINT `fk_psio_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psio_item` FOREIGN KEY (`purchase_bill_item_id`) REFERENCES `purchase_bill_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `qc_inspection_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `qc_id` (`qc_id`),
  KEY `variant_id` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `qc_inspections`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_qc_concern` (`concern_id`),
  KEY `fk_qc_var` (`variant_id`),
  CONSTRAINT `fk_qc_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qc_var` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `qc_piece_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `qc_id` (`qc_id`),
  KEY `tag_code` (`tag_code`),
  KEY `verdict` (`verdict`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `return_exchange_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rei_return` (`return_id`),
  KEY `idx_rei_variant` (`variant_id`),
  KEY `idx_rei_direction` (`direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `returns_exchanges`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_permissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=987 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_roles_concern` (`concern_id`),
  CONSTRAINT `fk_roles_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sales_invoice_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `sales_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `sales_invoice_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `production_batch_id` int(11) DEFAULT NULL,
  `scanned_codes` text DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL,
  `inward_status` enum('pending','completed') NOT NULL DEFAULT 'pending',
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sales_invoices`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `payment_method` varchar(30) NOT NULL DEFAULT 'cash',
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_si_concern` (`concern_id`),
  KEY `fk_si_cust` (`customer_id`),
  CONSTRAINT `fk_si_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_si_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shop_damages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `damage_no` (`damage_no`),
  KEY `idx_shd_concern` (`concern_id`),
  KEY `idx_shd_status` (`status`),
  KEY `idx_shd_variant` (`variant_id`),
  KEY `idx_shd_repl_qr` (`replacement_qr_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_concern` (`concern_id`),
  KEY `idx_staff_user` (`user_id`),
  KEY `idx_staff_concern_phone` (`concern_id`,`phone`),
  KEY `idx_staff_concern_email` (`concern_id`,`email`),
  CONSTRAINT `fk_staff_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stitching_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_si_stitching` (`stitching_id`),
  KEY `idx_si_variant` (`variant_id`),
  CONSTRAINT `fk_si_stitching` FOREIGN KEY (`stitching_id`) REFERENCES `stitching_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stitching_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `input_pieces` int(11) NOT NULL,
  `cut_pieces` int(11) NOT NULL DEFAULT 0,
  `stitched_pieces` int(11) NOT NULL,
  `cut_weight` decimal(12,2) DEFAULT 0.00,
  `stitch_weight` decimal(12,2) DEFAULT 0.00,
  `wastage_pieces` int(11) NOT NULL DEFAULT 0,
  `wastage_weight` decimal(12,2) DEFAULT 0.00,
  `sold_wastage_qty` decimal(12,2) DEFAULT 0.00,
  `produced_variant_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `inward_status` varchar(30) DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_stitch_concern` (`concern_id`),
  KEY `fk_stitch_prog` (`program_id`),
  KEY `fk_stitch_var` (`produced_variant_id`),
  CONSTRAINT `fk_stitch_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stitch_prog` FOREIGN KEY (`program_id`) REFERENCES `production_programs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stitch_var` FOREIGN KEY (`produced_variant_id`) REFERENCES `variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stitching_masters`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `stitching_masters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `employee_code` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sm_concern` (`concern_id`),
  CONSTRAINT `fk_sm_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stitching_wastage_sales`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sws_concern` (`concern_id`),
  KEY `idx_sws_stitching` (`stitching_id`),
  CONSTRAINT `fk_sws_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sws_stitching` FOREIGN KEY (`stitching_id`) REFERENCES `stitching_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock_ledger`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `stock_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `location` varchar(50) NOT NULL DEFAULT 'Main Store',
  `qty_in` decimal(12,2) NOT NULL DEFAULT 0.00,
  `qty_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `mrp` decimal(12,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ref_module` varchar(50) NOT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ledger_concern` (`concern_id`),
  KEY `idx_ledger_variant` (`variant_id`),
  CONSTRAINT `fk_sl_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sl_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stocks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `stocks` (
  `concern_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `location` varchar(50) NOT NULL DEFAULT 'Main Store',
  `qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`concern_id`,`variant_id`,`location`),
  KEY `idx_stocks_concern` (`concern_id`),
  KEY `fk_stk_variant` (`variant_id`),
  CONSTRAINT `fk_stk_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stk_variant` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sub_subcategories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `sub_subcategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `subcategory_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subsubcat_concern` (`concern_id`),
  KEY `idx_subsubcat_subcat` (`subcategory_id`),
  CONSTRAINT `fk_subsubcat_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subsubcat_subcat` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subcategories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `subcategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subcat_concern` (`concern_id`),
  KEY `fk_subcat_category` (`category_id`),
  CONSTRAINT `fk_subcat_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subcat_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suppliers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sup_concern` (`concern_id`),
  CONSTRAINT `fk_sup_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_concerns`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `user_concerns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `concern_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_concern` (`user_id`,`concern_id`),
  KEY `idx_uc_user` (`user_id`),
  KEY `idx_uc_concern` (`concern_id`),
  CONSTRAINT `fk_uc_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_permissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `concern_id` int(11) DEFAULT NULL,
  `module_id` int(11) NOT NULL,
  `can_view` tinyint(1) DEFAULT NULL,
  `can_add` tinyint(1) DEFAULT NULL,
  `can_edit` tinyint(1) DEFAULT NULL,
  `can_delete` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_concern_module` (`user_id`,`concern_id`,`module_id`),
  KEY `idx_up_user` (`user_id`),
  KEY `idx_up_module` (`module_id`),
  CONSTRAINT `fk_up_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=306 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_concern` (`concern_id`),
  KEY `fk_users_role` (`role_id`),
  KEY `idx_users_phone` (`phone`),
  CONSTRAINT `fk_users_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `variants`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `variants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `catalog_id` int(11) DEFAULT NULL,
  `sku_code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category_id` int(11) NOT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `weight` decimal(10,3) NOT NULL DEFAULT 0.000,
  `weight_unit` varchar(20) NOT NULL DEFAULT 'kg',
  `attributes` longtext DEFAULT NULL CHECK (json_valid(`attributes`)),
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `mrp` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_complementary` tinyint(1) NOT NULL DEFAULT 0,
  `creation_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_concern_sku` (`concern_id`,`sku_code`),
  KEY `idx_var_concern` (`concern_id`),
  KEY `idx_var_catalog` (`catalog_id`),
  KEY `fk_var_cat` (`category_id`),
  KEY `fk_var_subcat` (`subcategory_id`),
  KEY `fk_var_brand` (`brand_id`),
  CONSTRAINT `fk_var_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_var_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `fk_var_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_var_subcat` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `warehouse_damages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `warehouse_damages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concern_id` int(11) NOT NULL,
  `damage_no` varchar(50) NOT NULL,
  `warehouse_id` int(11) NOT NULL DEFAULT 1,
  `variant_id` int(11) NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `inward_id` int(11) DEFAULT NULL,
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `damage_no` (`damage_no`),
  KEY `idx_whd_concern` (`concern_id`),
  KEY `idx_whd_status` (`status`),
  KEY `idx_whd_variant` (`variant_id`),
  KEY `idx_whd_inward` (`inward_id`),
  KEY `idx_whd_repl_qr` (`replacement_qr_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-24  9:24:29
