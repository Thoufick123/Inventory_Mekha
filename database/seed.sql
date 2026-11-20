-- ========================================================
-- GRM Inventory: System Seed Data
-- Modules and Super Admin only
-- Compatible with Local & Live Hosting (Hostinger/cPanel)
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. CLEAN ALL CONCERN DATA (keep only modules and Super Admin)
DELETE FROM `user_permissions`;
DELETE FROM `user_concerns`;
DELETE FROM `role_permissions`;
DELETE FROM `concern_modules`;
DELETE FROM `users` WHERE `concern_id` IS NOT NULL OR `role_id` <> 1;
DELETE FROM `roles` WHERE `id` <> 1 OR `concern_id` IS NOT NULL;

DELETE FROM `cutting_wastage_sales`;
DELETE FROM `cutting_items`;
DELETE FROM `cutting_masters`;
DELETE FROM `cutting_logs`;
DELETE FROM `stitching_wastage_sales`;
DELETE FROM `stitching_items`;
DELETE FROM `stitching_masters`;
DELETE FROM `stitching_logs`;
DELETE FROM `catalog_mappings`;
DELETE FROM `program_rolls`;
DELETE FROM `production_programs`;
DELETE FROM `catalogs`;
DELETE FROM `product_attributes`;
DELETE FROM `qc_inspections`;
DELETE FROM `purchase_damage_quarantine`;
DELETE FROM `purchase_damage_reasons`;
DELETE FROM `purchase_damages`;
DELETE FROM `concern_damages`;
DELETE FROM `inward_items`;
DELETE FROM `inwards`;
DELETE FROM `purchase_stock_item_overrides`;
DELETE FROM `fabric_roll_spec_overrides`;
DELETE FROM `fabric_roll_specs`;
DELETE FROM `purchase_bill_items`;
DELETE FROM `purchase_bills`;
DELETE FROM `outward_items`;
DELETE FROM `outwards`;
DELETE FROM `dispatches`;
DELETE FROM `sales_invoice_items`;
DELETE FROM `sales_invoices`;
DELETE FROM `customer_requests`;
DELETE FROM `batches`;
DELETE FROM `returns_exchanges`;
DELETE FROM `stock_ledger`;
DELETE FROM `stocks`;
DELETE FROM `label_jobs`;
DELETE FROM `doc_series`;
DELETE FROM `cash_transactions`;
DELETE FROM `cashier_shifts`;
DELETE FROM `cash_accounts`;
DELETE FROM `expenses`;
DELETE FROM `assets` WHERE `concern_id` IS NOT NULL;
DELETE FROM `audit_log` WHERE `concern_id` IS NOT NULL;
DELETE FROM `suppliers`;
DELETE FROM `customers`;
DELETE FROM `hsn_masters`;
DELETE FROM `variants`;
DELETE FROM `sub_subcategories`;
DELETE FROM `subcategories`;
DELETE FROM `categories`;
DELETE FROM `brands`;
DELETE FROM `staff`;
DELETE FROM `concerns`;

-- 2. MASTER MODULE REGISTRY
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

SET FOREIGN_KEY_CHECKS = 1;
