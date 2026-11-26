-- ========================================================
-- INVENTO: Live-safe baseline seed
-- Preserves all concern, inventory, sales and operational data.
-- Includes master modules, Super Admin role/user, and 95 HSN/SAC GST rows.
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 2. MASTER MODULE REGISTRY
INSERT INTO `modules` (`code`, `name`, `icon`, `sort_order`, `group_name`, `is_super_admin_only`) VALUES
('admin_dashboard', 'Dashboard', 'bi-speedometer2', 1, 'Super Admin', 1),
('admin_concerns', 'Concern Management', 'bi-buildings', 2, 'Super Admin', 1),
('admin_assets', 'Asset Management', 'bi-building-gear', 3, 'Super Admin', 1),
('admin_reports', 'Consolidated Reports', 'bi-bar-chart-line', 4, 'Super Admin', 1),

('concern_dashboard', 'Concern Dashboard', 'bi-speedometer', 10, 'Dashboard', 0),
('purchase_billing', 'Purchase Billing', 'bi-receipt', 21, 'Purchase', 0),
('purchase_stocks', 'Purchase Stocks', 'bi-boxes', 22, 'Purchase', 0),
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
-- Machinery, Maintenance & Job Work Classifications (95 rows)
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
    INDEX `idx_hsn_active_type` (`is_active`, `code_type`),
    INDEX `idx_hsn_lookup` (`concern_id`, `code_type`, `hsn_code`, `is_active`)
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
