<?php
// app/core/Controller.php

namespace App\Core;

abstract class Controller {
    protected static bool $baseTablesEnsured = false;

    /**
     * SQL ORDER BY expression sorting garment sizes in standard order:
     * XS (1), S (2), M (3), L (4), XL (5), XXL (6), 3XL (7), 4XL (8), etc.
     */
    public static function sizeOrderSql(string $col = 'size'): string {
        $safeCol = preg_replace('/[^a-zA-Z0-9_.]/', '', $col) ?: 'size';
        return "CASE UPPER(TRIM({$safeCol}))
            WHEN 'XS' THEN 1
            WHEN 'S' THEN 2
            WHEN 'M' THEN 3
            WHEN 'L' THEN 4
            WHEN 'XL' THEN 5
            WHEN 'XXL' THEN 6
            WHEN '2XL' THEN 6
            WHEN '3XL' THEN 7
            WHEN '4XL' THEN 8
            WHEN '5XL' THEN 9
            WHEN '6XL' THEN 10
            ELSE 99
        END ASC";
    }

    public function __construct() {
        self::ensureBaseTables();
    }

    public static function ensureBaseTables(): void {
        if (self::$baseTablesEnsured) {
            return;
        }

        try {
            DB::query("
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
            ");

            $unitCount = (int)DB::fetchValue("SELECT COUNT(*) FROM `production_units`");
            if ($unitCount === 0) {
                $concerns = DB::fetchAll("SELECT id, name, code FROM `concerns` WHERE status = 'active'");
                foreach ($concerns as $c) {
                    $uCode = 'PU-' . strtoupper(str_replace(' ', '', $c['code'] ?? '01'));
                    DB::query(
                        "INSERT INTO `production_units` (`concern_id`, `code`, `name`, `location`, `capacity_per_day`, `is_active`) VALUES (?, ?, ?, 'Main Facility', 500, 1)",
                        [$c['id'], $uCode, $c['name'] . ' Production Unit 1']
                    );
                }
            }

            DB::query("
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
            ");

            // Self-healing QC schema and tables
            DB::query("
                CREATE TABLE IF NOT EXISTS `qc_inspections` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `concern_id` INT NULL,
                    `qc_no` VARCHAR(50) NOT NULL,
                    `batch_no` VARCHAR(50) NULL,
                    `variant_id` INT NULL,
                    `program_id` INT NULL,
                    `stitching_batch_id` INT NULL,
                    `cutting_batch_id` INT NULL,
                    `inspected_qty` DECIMAL(12,2) NOT NULL DEFAULT 0,
                    `passed_qty` DECIMAL(12,2) NOT NULL DEFAULT 0,
                    `rework_qty` DECIMAL(12,2) NOT NULL DEFAULT 0,
                    `rejected_qty` DECIMAL(12,2) NOT NULL DEFAULT 0,
                    `location` VARCHAR(100) NULL DEFAULT 'Main Store',
                    `defect_notes` TEXT NULL,
                    `rework_notes` TEXT NULL,
                    `status` VARCHAR(50) NOT NULL DEFAULT 'PENDING',
                    `inspector_id` INT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (`concern_id`),
                    INDEX (`stitching_batch_id`),
                    INDEX (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            DB::query("
                CREATE TABLE IF NOT EXISTS `qc_inspection_items` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `qc_id` INT NOT NULL,
                    `catalog_id` INT NULL,
                    `variant_id` INT NULL,
                    `stitching_item_id` INT NULL,
                    `size` VARCHAR(50) NULL,
                    `color` VARCHAR(50) NULL,
                    `product_code` VARCHAR(100) NULL,
                    `variant_name` VARCHAR(255) NULL,
                    `inspected_qty` INT NOT NULL DEFAULT 0,
                    `passed_qty` INT NOT NULL DEFAULT 0,
                    `rejected_qty` INT NOT NULL DEFAULT 0,
                    `rework_qty` INT NOT NULL DEFAULT 0,
                    `defect_notes` TEXT NULL,
                    `rework_notes` TEXT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (`qc_id`),
                    INDEX (`variant_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            DB::query("
                CREATE TABLE IF NOT EXISTS `qc_piece_items` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `qc_id` INT NOT NULL,
                    `qc_item_id` INT NULL,
                    `variant_id` INT NULL,
                    `stitching_item_id` INT NULL,
                    `tag_code` VARCHAR(100) NOT NULL,
                    `piece_number` INT NOT NULL DEFAULT 1,
                    `total_variant_pieces` INT NOT NULL DEFAULT 1,
                    `size` VARCHAR(50) NULL,
                    `color` VARCHAR(50) NULL,
                    `catalog_name` VARCHAR(255) NULL,
                    `verdict` ENUM('PENDING','PASSED','REJECTED','REWORK') NOT NULL DEFAULT 'PENDING',
                    `defect_reason` TEXT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (`qc_id`),
                    INDEX (`tag_code`),
                    INDEX (`verdict`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Self-healing: ensure sku_code -> product_code and variants.sub_sub_category_id on live/remote DBs
            $skuTables = [
                ['table' => 'variants', 'def' => 'VARCHAR(50) NOT NULL'],
                ['table' => 'batch_items', 'def' => 'VARCHAR(100) NULL'],
                ['table' => 'cutting_items', 'def' => 'VARCHAR(100) NULL'],
                ['table' => 'qc_inspection_items', 'def' => 'VARCHAR(100) NULL'],
                ['table' => 'stitching_items', 'def' => 'VARCHAR(100) NULL'],
            ];
            foreach ($skuTables as $st) {
                $tbl = $st['table'];
                try {
                    $tCols = array_column(DB::fetchAll("SHOW COLUMNS FROM `{$tbl}`"), 'Field');
                    if (in_array('sku_code', $tCols, true) && !in_array('product_code', $tCols, true)) {
                        DB::query("ALTER TABLE `{$tbl}` CHANGE COLUMN `sku_code` `product_code` {$st['def']}");
                        if ($tbl === 'variants') {
                            DB::query("UPDATE `variants` SET `product_code` = CAST(`id` AS CHAR)");
                        }
                    }
                } catch (\Throwable $e) {}
            }
            try {
                $varCols = array_column(DB::fetchAll("SHOW COLUMNS FROM `variants`"), 'Field');
                if (!in_array('sub_sub_category_id', $varCols, true)) {
                    DB::query("ALTER TABLE `variants` ADD COLUMN `sub_sub_category_id` INT NULL AFTER `subcategory_id`, ADD INDEX `idx_var_subsubcat` (`sub_sub_category_id`)");
                }
                $hasSkuIdx = DB::fetchValue("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'variants' AND INDEX_NAME = 'uk_concern_sku'");
                if ($hasSkuIdx) {
                    DB::query("ALTER TABLE `variants` DROP INDEX `uk_concern_sku`, ADD UNIQUE KEY `uk_concern_product_code` (`concern_id`, `product_code`)");
                }
                DB::query("UPDATE `modules` SET `name` = 'Catalog Hierarchy' WHERE `code` = 'catalog_mapping' AND `name` != 'Catalog Hierarchy'");
            } catch (\Throwable $e) {}

            // Self-healing columns for inwards, inward_items, batches, qc_inspections, outwards
            $inCols = array_column(DB::fetchAll("DESCRIBE `inwards`"), 'Field');
            if (!in_array('production_unit_id', $inCols)) DB::query("ALTER TABLE `inwards` ADD COLUMN `production_unit_id` INT NULL AFTER `concern_id`");
            if (!in_array('grn_number', $inCols)) DB::query("ALTER TABLE `inwards` ADD COLUMN `grn_number` VARCHAR(50) NULL AFTER `inward_no`");
            if (!in_array('warehouse_id', $inCols)) DB::query("ALTER TABLE `inwards` ADD COLUMN `warehouse_id` INT NULL AFTER `location`");
            if (!in_array('vehicle_number', $inCols)) DB::query("ALTER TABLE `inwards` ADD COLUMN `vehicle_number` VARCHAR(50) NULL AFTER `warehouse_id`");
            if (!in_array('status', $inCols)) DB::query("ALTER TABLE `inwards` ADD COLUMN `status` VARCHAR(50) DEFAULT 'VERIFIED' AFTER `remarks`");

            $inItemCols = array_column(DB::fetchAll("DESCRIBE `inward_items`"), 'Field');
            if (!in_array('accepted_qty', $inItemCols)) DB::query("ALTER TABLE `inward_items` ADD COLUMN `accepted_qty` DECIMAL(12,2) DEFAULT 0.00 AFTER `qty`");
            if (!in_array('rejected_qty', $inItemCols)) DB::query("ALTER TABLE `inward_items` ADD COLUMN `rejected_qty` DECIMAL(12,2) DEFAULT 0.00 AFTER `accepted_qty`");
            if (!in_array('rejection_reason', $inItemCols)) DB::query("ALTER TABLE `inward_items` ADD COLUMN `rejection_reason` VARCHAR(255) NULL AFTER `rejected_qty`");

            $batchCols = array_column(DB::fetchAll("DESCRIBE `batches`"), 'Field');
            if (!in_array('production_unit_id', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `production_unit_id` INT NULL AFTER `concern_id`");
            if (!in_array('program_id', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `program_id` INT NULL AFTER `variant_id`");
            if (!in_array('cutting_batch_id', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `cutting_batch_id` INT NULL AFTER `program_id`");
            if (!in_array('stitching_batch_id', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `stitching_batch_id` INT NULL AFTER `cutting_batch_id`");
            if (!in_array('qc_batch_id', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `qc_batch_id` INT NULL AFTER `stitching_batch_id`");
            if (!in_array('additional_qc_batch_ids', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `additional_qc_batch_ids` TEXT NULL AFTER `qc_batch_id`");
            if (!in_array('total_boxes', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `total_boxes` INT DEFAULT 0 AFTER `batch_qty`");
            if (!in_array('warehouse_id', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `warehouse_id` INT NULL AFTER `total_boxes`");
            if (!in_array('warehouse_name', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `warehouse_name` VARCHAR(150) NULL AFTER `warehouse_id`");
            if (!in_array('packed_by_name', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `packed_by_name` VARCHAR(150) DEFAULT 'System Administrator' AFTER `warehouse_name`");
            if (!in_array('rework_included', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `rework_included` TINYINT(1) DEFAULT 0 AFTER `packed_by_name`");
            if (!in_array('qr_codes', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `qr_codes` LONGTEXT NULL AFTER `rework_included`");
            if (!in_array('taxable_value', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `taxable_value` DECIMAL(12,2) DEFAULT 0.00 AFTER `qr_codes`");
            if (!in_array('gst_amount', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `gst_amount` DECIMAL(12,2) DEFAULT 0.00 AFTER `taxable_value`");
            if (!in_array('total_amount', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `total_amount` DECIMAL(12,2) DEFAULT 0.00 AFTER `gst_amount`");
            if (!in_array('bill_no', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `bill_no` VARCHAR(100) NULL AFTER `total_amount`");
            if (!in_array('fabric_details', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `fabric_details` VARCHAR(255) DEFAULT 'GREEN / BIRDS' AFTER `bill_no`");
            if (!in_array('approved_by', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `approved_by` INT NULL AFTER `status`");
            if (!in_array('approved_at', $batchCols)) DB::query("ALTER TABLE `batches` ADD COLUMN `approved_at` DATETIME NULL AFTER `approved_by`");

            $qcCols = array_column(DB::fetchAll("DESCRIBE `qc_inspections`"), 'Field');
            if (!in_array('batch_no', $qcCols)) DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `batch_no` VARCHAR(50) NULL");
            if (!in_array('program_id', $qcCols)) DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `program_id` INT NULL");
            if (!in_array('cutting_batch_id', $qcCols)) DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `cutting_batch_id` INT NULL");
            if (!in_array('stitching_batch_id', $qcCols)) DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `stitching_batch_id` INT NULL");
            if (!in_array('rework_qty', $qcCols)) DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `rework_qty` DECIMAL(12,2) DEFAULT 0.00");
            if (!in_array('rework_notes', $qcCols)) DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `rework_notes` TEXT NULL");
            if (!in_array('status', $qcCols)) DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `status` VARCHAR(50) DEFAULT 'COMPLETED'");
            // Ensure variant_id is nullable in qc_inspections
            DB::query("ALTER TABLE `qc_inspections` MODIFY COLUMN `variant_id` INT NULL");
            DB::query("ALTER TABLE `qc_inspections` MODIFY COLUMN `inspected_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
            DB::query("ALTER TABLE `qc_inspections` MODIFY COLUMN `passed_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
            DB::query("ALTER TABLE `qc_inspections` MODIFY COLUMN `rejected_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
            DB::query("ALTER TABLE `qc_inspections` MODIFY COLUMN `rework_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00");

            // Ensure operational concern modules are provisioned in concern_modules for all active concerns
            $allConcernMods = DB::fetchAll("SELECT id, code FROM modules WHERE is_super_admin_only = 0 AND code != 'dispatch'");
            if (!empty($allConcernMods)) {
                $concerns = DB::fetchAll("SELECT id FROM concerns WHERE status = 'active' OR status IS NULL OR status = ''");
                foreach ($concerns as $cn) {
                    foreach ($allConcernMods as $m) {
                        $hasCm = DB::fetchValue("SELECT id FROM concern_modules WHERE concern_id = ? AND module_id = ?", [$cn['id'], $m['id']]);
                        if (!$hasCm) {
                            DB::query("INSERT INTO concern_modules (concern_id, module_id, enabled) VALUES (?, ?, 1)", [$cn['id'], $m['id']]);
                        }
                    }
                }
                // Ensure concern_modules is enabled for any module where users or roles have active permissions
                DB::query("
                    UPDATE concern_modules cm
                    JOIN modules m ON cm.module_id = m.id
                    SET cm.enabled = 1
                    WHERE cm.enabled = 0
                      AND (
                          EXISTS (
                              SELECT 1 FROM user_permissions up
                              JOIN users u ON up.user_id = u.id
                              WHERE up.module_id = cm.module_id
                                AND (up.concern_id = cm.concern_id OR (up.concern_id IS NULL AND u.concern_id = cm.concern_id))
                                AND (up.can_view = 1 OR up.can_add = 1 OR up.can_edit = 1 OR up.can_delete = 1)
                          )
                          OR EXISTS (
                              SELECT 1 FROM role_permissions rp
                              JOIN roles r ON rp.role_id = r.id
                              WHERE rp.module_id = cm.module_id
                                AND (r.concern_id = cm.concern_id OR r.concern_id IS NULL)
                                AND (rp.can_view = 1 OR rp.can_add = 1 OR rp.can_edit = 1 OR rp.can_delete = 1)
                          )
                      )
                ");
                // Ensure Concern Administrator roles have permissions for all concern modules
                $adminRoles = DB::fetchAll("SELECT id FROM roles WHERE (name LIKE '%Administrator%' OR name LIKE '%Admin%') AND id > 1");
                foreach ($adminRoles as $ar) {
                    foreach ($allConcernMods as $m) {
                        $hasRp = DB::fetchValue("SELECT id FROM role_permissions WHERE role_id = ? AND module_id = ?", [$ar['id'], $m['id']]);
                        if (!$hasRp) {
                            DB::query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete) VALUES (?, ?, 1, 1, 1, 1)", [$ar['id'], $m['id']]);
                        }
                    }
                }
            }

            $outCols = array_column(DB::fetchAll("DESCRIBE `outwards`"), 'Field');
            if (!in_array('production_unit_id', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `production_unit_id` INT NULL AFTER `concern_id`");
            if (!in_array('warehouse_id', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `warehouse_id` INT NULL AFTER `location`");
            if (!in_array('vehicle_number', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `vehicle_number` VARCHAR(100) NULL AFTER `warehouse_id`");
            if (!in_array('driver_name', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `driver_name` VARCHAR(150) NULL AFTER `vehicle_number`");
            if (!in_array('driver_contact', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `driver_contact` VARCHAR(50) NULL AFTER `driver_name`");
            if (!in_array('transporter_name', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `transporter_name` VARCHAR(150) NULL AFTER `driver_contact`");
            if (!in_array('lr_number', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `lr_number` VARCHAR(100) NULL AFTER `transporter_name`");
            if (!in_array('gate_pass_no', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `gate_pass_no` VARCHAR(50) NULL AFTER `lr_number`");
            if (!in_array('dispatched_at', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `dispatched_at` DATETIME NULL AFTER `status`");
            if (!in_array('received_at', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `received_at` DATETIME NULL AFTER `dispatched_at`");

            // Auto-migrate legacy sku_code -> product_code across tables if not yet migrated
            $skuTables = [
                ['table' => 'variants', 'def' => 'VARCHAR(50) NOT NULL'],
                ['table' => 'batch_items', 'def' => 'VARCHAR(100) NULL'],
                ['table' => 'cutting_items', 'def' => 'VARCHAR(100) NULL'],
                ['table' => 'qc_inspection_items', 'def' => 'VARCHAR(100) NULL'],
                ['table' => 'stitching_items', 'def' => 'VARCHAR(100) NULL'],
            ];
            foreach ($skuTables as $st) {
                $tbl = $st['table'];
                try {
                    $cols = array_column(DB::fetchAll("SHOW COLUMNS FROM `{$tbl}`"), 'Field');
                    if (in_array('sku_code', $cols, true) && !in_array('product_code', $cols, true)) {
                        DB::query("ALTER TABLE `{$tbl}` CHANGE COLUMN `sku_code` `product_code` {$st['def']}");
                    }
                } catch (\Throwable $e) {}
            }
            try {
                $vCols = array_column(DB::fetchAll("SHOW COLUMNS FROM `variants`"), 'Field');
                if (!in_array('sub_sub_category_id', $vCols, true)) {
                    DB::query("ALTER TABLE `variants` ADD COLUMN `sub_sub_category_id` INT NULL AFTER `subcategory_id`, ADD INDEX `idx_var_subsubcat` (`sub_sub_category_id`)");
                }
            } catch (\Throwable $e) {}
            try {
                $hasIdx = DB::fetchValue("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'variants' AND INDEX_NAME = 'uk_concern_sku'");
                if ($hasIdx) {
                    DB::query("ALTER TABLE `variants` DROP INDEX `uk_concern_sku`, ADD UNIQUE KEY `uk_concern_product_code` (`concern_id`, `product_code`)");
                }
            } catch (\Throwable $e) {}

            self::$baseTablesEnsured = true;
        } catch (\Throwable $e) {
            // Suppress if already exists
        }
    }

    protected function render(string $viewPath, array $data = []): void {
        extract($data);

        // Standard context available in views
        $currentUser = Auth::user();
        $currentConcern = Auth::concern();
        $isSuperAdmin = Auth::isSuperAdmin();
        $csrfToken = Auth::csrfToken();
        $allConcerns = $data['allConcerns'] ?? Auth::allConcerns();
        $selectedConcernId = Auth::superAdminSelectedConcernId();

        // Flash messages
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashWarning = $_SESSION['flash_warning'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_warning'], $_SESSION['flash_error']);

        // Load modules for sidebar
        $sidebarModules = [];
        if (Auth::check()) {
            if ($isSuperAdmin) {
                // Super Admin sees both Super Admin management and ALL operational concern modules
                $databaseModules = DB::fetchAll(
                    "SELECT * FROM modules ORDER BY is_super_admin_only DESC, sort_order ASC"
                );
                $sidebarModules = ModuleRegistry::merge($databaseModules, true);
            } else {
                $cid = Auth::concernId();
                $databaseModules = DB::fetchAll(
                    "SELECT DISTINCT m.* FROM modules m
                     LEFT JOIN concern_modules cm ON cm.module_id = m.id AND cm.concern_id = ?
                     WHERE m.is_super_admin_only = 0 AND m.code != 'dispatch'
                       AND (cm.enabled = 1 OR cm.enabled IS NULL)
                     ORDER BY m.sort_order ASC",
                    [$cid]
                );
                $registeredModuleCount = (int)DB::fetchValue("SELECT COUNT(*) FROM modules");
                $sidebarModules = $registeredModuleCount < 5
                    ? ModuleRegistry::merge($databaseModules)
                    : $databaseModules;

                if (!$isSuperAdmin) {
                    $allowedSidebarModules = [];
                    foreach ($sidebarModules as $m) {
                        if (Auth::can($m['code'], 'view|add|edit|delete')) {
                            $allowedSidebarModules[] = $m;
                        }
                    }
                    $sidebarModules = $allowedSidebarModules;
                }
            }
        }

        // Determine view full path
        $contentView = __DIR__ . "/../views/{$viewPath}.php";

        if (!file_exists($contentView)) {
            die("View not found: " . htmlspecialchars($viewPath));
        }

        // Layout wrapping
        $layout = __DIR__ . "/../views/layouts/main.php";
        if (isset($noLayout) && $noLayout) {
            require $contentView;
        } else {
            require $layout;
        }
    }

    protected function renderJson(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (php_sapi_name() !== 'cli') {
            exit;
        }
    }

    protected function redirect(string $path): void {
        if (!headers_sent()) {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                header('Location: ' . $path);
            } else {
                header('Location: ' . self::url($path));
            }
        }
        if (php_sapi_name() !== 'cli') {
            exit;
        }
    }

    public static function url(string $path = ''): string {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        if ($base === '/' || $base === '.') {
            $base = '';
        }

        // If path already starts with base directory, prevent duplication
        if ($base !== '' && (str_starts_with($path, $base . '/') || $path === $base)) {
            return $path;
        }

        return $base . '/' . ltrim($path, '/');
    }

    protected function flash(string $type, string $message): void {
        Auth::startSession();
        $_SESSION["flash_{$type}"] = $message;
    }

    protected function validateCsrf(): void {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Auth::verifyCsrfToken($token)) {
            $this->flash('error', 'Your session expired or the form security token was invalid. Please try submitting again.');
            $referer = $_SERVER['HTTP_REFERER'] ?? null;
            if ($referer && filter_var($referer, FILTER_VALIDATE_URL)) {
                $this->redirect($referer);
                return;
            }
            $this->redirect('/admin/concerns');
            return;
        }
    }
}
