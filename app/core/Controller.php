<?php
// app/core/Controller.php

namespace App\Core;

abstract class Controller {
    protected static bool $baseTablesEnsured = false;

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
            if (!in_array('batch_no', $qcCols)) DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `batch_no` VARCHAR(50) NULL AFTER `qc_no`");
            if (!in_array('cutting_batch_id', $qcCols)) DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `cutting_batch_id` INT NULL AFTER `variant_id`");
            if (!in_array('stitching_batch_id', $qcCols)) DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `stitching_batch_id` INT NULL AFTER `cutting_batch_id`");
            if (!in_array('rework_qty', $qcCols)) DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `rework_qty` DECIMAL(12,2) DEFAULT 0.00 AFTER `passed_qty`");
            if (!in_array('rework_notes', $qcCols)) DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `rework_notes` TEXT NULL AFTER `defect_notes`");
            if (!in_array('status', $qcCols)) DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `status` VARCHAR(50) DEFAULT 'COMPLETED' AFTER `rework_notes`");

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
                    "SELECT m.* FROM concern_modules cm 
                     JOIN modules m ON cm.module_id = m.id 
                     WHERE cm.concern_id = ? AND cm.enabled = 1 
                     ORDER BY m.sort_order ASC",
                    [$cid]
                );
                $registeredModuleCount = (int)DB::fetchValue("SELECT COUNT(*) FROM modules");
                $sidebarModules = $registeredModuleCount < 5
                    ? ModuleRegistry::merge($databaseModules)
                    : $databaseModules;
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
            http_response_code(419);
            die("CSRF token validation failed. Please refresh and try again.");
        }
    }
}
