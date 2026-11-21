-- ========================================================
-- INVENTO: Live-safe baseline seed
-- Preserves all concern, inventory, sales and operational data.
-- HSN/GST details and operational sequences are seeded by
-- database/seed_live.php for environments that use PHP seeding.
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;

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
