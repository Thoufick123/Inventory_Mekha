<?php
// app/controllers/AdminController.php - Super Admin Features

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Core\ModuleRegistry;
use App\Models\Concern;
use App\Models\Asset;
use App\Services\AuditLog;
use Exception;

class AdminController extends Controller {
    private static bool $schemaEnsured = false;

    private function syncMasterModules(): array
    {
        foreach (ModuleRegistry::masterModules() as $module) {
            DB::query(
                "INSERT INTO modules (code, name, icon, sort_order, group_name, is_super_admin_only)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    icon = VALUES(icon),
                    sort_order = VALUES(sort_order),
                    group_name = VALUES(group_name),
                    is_super_admin_only = VALUES(is_super_admin_only)",
                [
                    $module['code'],
                    $module['name'],
                    $module['icon'],
                    $module['sort_order'],
                    $module['group_name'],
                    $module['is_super_admin_only'],
                ]
            );
        }

        $batchModule = DB::fetch("SELECT id FROM modules WHERE code = 'batch'");
        if ($batchModule) {
            $batchModuleId = (int)$batchModule['id'];
            DB::query("DELETE FROM user_permissions WHERE module_id = ?", [$batchModuleId]);
            DB::query("DELETE FROM role_permissions WHERE module_id = ?", [$batchModuleId]);
            DB::query("DELETE FROM concern_modules WHERE module_id = ?", [$batchModuleId]);
            DB::query("DELETE FROM modules WHERE id = ?", [$batchModuleId]);
        }

        return DB::fetchAll(
            "SELECT * FROM modules
             WHERE is_super_admin_only = 0
             ORDER BY sort_order ASC"
        );
    }

    protected function ensureSchema(): void {
        if (self::$schemaEnsured) {
            return;
        }

        try {
            // 1. Ensure user_concerns table
            DB::query("CREATE TABLE IF NOT EXISTS `user_concerns` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `concern_id` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_user_concern` (`user_id`, `concern_id`),
                INDEX `idx_uc_user` (`user_id`),
                INDEX `idx_uc_concern` (`concern_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 2. Ensure user_permissions table
            DB::query("CREATE TABLE IF NOT EXISTS `user_permissions` (
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
                INDEX `idx_up_module` (`module_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 3. Ensure staff table and columns
            DB::query("CREATE TABLE IF NOT EXISTS `staff` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `concern_id` INT NOT NULL,
                `staff_code` VARCHAR(50) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `phone` VARCHAR(30) NULL,
                `email` VARCHAR(120) NULL,
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
                INDEX `idx_staff_concern` (`concern_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $staffCols = array_column(DB::fetchAll('DESCRIBE `staff`'), 'Field');
            if (!in_array('user_id', $staffCols, true)) {
                DB::query('ALTER TABLE `staff` ADD COLUMN `user_id` INT NULL AFTER `concern_id`');
                DB::query('ALTER TABLE `staff` ADD INDEX `idx_staff_user` (`user_id`)');
            }
            if (!in_array('password_hash', $staffCols, true)) {
                DB::query('ALTER TABLE `staff` ADD COLUMN `password_hash` VARCHAR(255) NULL AFTER `email`');
            }

            // Remove dispatch module if present in DB
            $dispatchMod = DB::fetch("SELECT id FROM modules WHERE code = 'dispatch'");
            if ($dispatchMod) {
                $did = (int)$dispatchMod['id'];
                DB::query("DELETE FROM user_permissions WHERE module_id = ?", [$did]);
                DB::query("DELETE FROM role_permissions WHERE module_id = ?", [$did]);
                DB::query("DELETE FROM concern_modules WHERE module_id = ?", [$did]);
                DB::query("DELETE FROM modules WHERE id = ?", [$did]);
            }

            // Ensure concern_dashboard is always enabled for all concerns and roles
            $dashMod = DB::fetch("SELECT id FROM modules WHERE code = 'concern_dashboard'");
            if ($dashMod) {
                $dashModId = (int)$dashMod['id'];
                DB::query("INSERT INTO concern_modules (concern_id, module_id, enabled)
                           SELECT id, ?, 1 FROM concerns
                           ON DUPLICATE KEY UPDATE enabled = 1", [$dashModId]);
                DB::query("INSERT IGNORE INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                           SELECT id, ?, 1, 1, 1, 1 FROM roles", [$dashModId]);
            }

            // Self-heal: ensure admin_assets and admin_reports are provisionable and enabled for ALL concerns
            DB::query("UPDATE modules SET is_super_admin_only = 0 WHERE code IN ('admin_assets', 'admin_reports')");
            DB::query("INSERT INTO concern_modules (concern_id, module_id, enabled)
                       SELECT c.id, m.id, 1
                       FROM concerns c
                       CROSS JOIN modules m
                       WHERE m.code IN ('admin_assets', 'admin_reports')
                       ON DUPLICATE KEY UPDATE enabled = 1");
            DB::query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                       SELECT r.id, m.id, 1, 1, 1, 1
                       FROM roles r
                       CROSS JOIN modules m
                       WHERE (r.name LIKE '%Administrator%' OR r.name LIKE '%Admin%')
                         AND m.code IN ('admin_assets', 'admin_reports')
                       ON DUPLICATE KEY UPDATE can_view = 1, can_add = 1, can_edit = 1, can_delete = 1");

            self::$schemaEnsured = true;
        } catch (\Throwable $e) {
            error_log('AdminController ensureSchema error: ' . $e->getMessage());
        }
    }

    public function switchConcern(): void {
        $concernId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        Auth::switchConcern($concernId > 0 ? $concernId : null);
        
        $this->flash('success', $concernId > 0 ? 'Switched active workspace view to concern.' : 'Switched to Consolidated View (All Concerns).');
        
        $referer = $_SERVER['HTTP_REFERER'] ?? self::url('/admin/dashboard');
        header('Location: ' . $referer);
        exit;
    }

    public function dashboard(): void {
        $concernsCount = DB::fetchValue("SELECT COUNT(*) FROM concerns WHERE status = 'active'");
        $usersCount = DB::fetchValue("SELECT COUNT(*) FROM users WHERE status = 'active'");
        $assetsCount = DB::fetchValue("SELECT COUNT(*) FROM assets WHERE status = 'active'");
        $assetsValuation = DB::fetchValue("SELECT COALESCE(SUM(current_value), 0) FROM assets");
        
        $concerns = DB::fetchAll(
            "SELECT c.*, 
                    (SELECT COUNT(*) 
                     FROM concern_modules cm 
                     JOIN modules m ON cm.module_id = m.id 
                     WHERE cm.concern_id = c.id 
                       AND cm.enabled = 1 
                       AND m.code != 'concern_dashboard' 
                       AND m.is_super_admin_only = 0 
                       AND m.code != 'cash') AS active_modules_count,
                    (SELECT COUNT(*) FROM users u WHERE u.concern_id = c.id) AS users_count
             FROM concerns c 
             ORDER BY c.id DESC"
        );

        $concernChartData = DB::fetchAll(
            "SELECT c.id, c.code, c.name,
                    (SELECT COUNT(*) FROM variants v WHERE v.concern_id = c.id) AS total_variants,
                    (SELECT COALESCE(SUM(s.qty), 0) FROM stocks s WHERE s.concern_id = c.id) AS total_stock_qty,
                    (SELECT COALESCE(SUM(s.qty * v.price), 0) FROM stocks s JOIN variants v ON s.variant_id = v.id WHERE s.concern_id = c.id) AS stock_valuation,
                    (SELECT COALESCE(SUM(net_amount), 0) FROM sales_invoices si WHERE si.concern_id = c.id) AS total_sales,
                    (SELECT COALESCE(SUM(net_amount), 0) FROM purchase_bills pb WHERE pb.concern_id = c.id) AS total_purchases
             FROM concerns c
             WHERE c.status = 'active'
             ORDER BY c.id ASC"
        );

        $recentAudits = DB::fetchAll(
            "SELECT a.*, u.name AS user_name, c.name AS concern_name 
             FROM audit_log a
             LEFT JOIN users u ON a.user_id = u.id
             LEFT JOIN concerns c ON a.concern_id = c.id
             ORDER BY a.id DESC LIMIT 10"
        );

        $this->render('admin/dashboard', [
            'pageTitle'        => 'Super Admin Dashboard',
            'currentModule'    => 'admin_dashboard',
            'concernsCount'    => (int)$concernsCount,
            'usersCount'       => (int)$usersCount,
            'assetsCount'      => (int)$assetsCount,
            'assetsValuation'  => (float)$assetsValuation,
            'concerns'         => $concerns,
            'concernChartData' => $concernChartData,
            'recentAudits'     => $recentAudits,
        ]);
    }

    public function concerns(): void {
        $this->ensureSchema();
        $concerns = DB::fetchAll(
            "SELECT c.*, 
                    (SELECT COUNT(*) 
                     FROM concern_modules cm 
                     JOIN modules m ON cm.module_id = m.id 
                     WHERE cm.concern_id = c.id 
                       AND cm.enabled = 1 
                       AND m.code != 'concern_dashboard' 
                       AND m.is_super_admin_only = 0 
                       AND m.code != 'cash') AS active_modules_count,
                    (SELECT COUNT(*) FROM users u WHERE u.concern_id = c.id) AS users_count,
                    (SELECT COUNT(*) FROM variants v WHERE v.concern_id = c.id) AS variants_count
             FROM concerns c 
             ORDER BY c.id DESC"
        );

        $cmMap = [];
        $cmRows = DB::fetchAll("SELECT concern_id, module_id FROM concern_modules WHERE enabled = 1");
        foreach ($cmRows as $cm) {
            $cmMap[(int)$cm['concern_id']][] = (int)$cm['module_id'];
        }
        foreach ($concerns as &$c) {
            $c['enabled_module_ids'] = $cmMap[(int)$c['id']] ?? [];
        }
        unset($c);

        $allModules = $this->syncMasterModules();

        $this->render('admin/concerns', [
            'pageTitle'     => 'Concern Management',
            'currentModule' => 'admin_concerns',
            'concerns'      => $concerns,
            'allModules'    => $allModules,
        ]);
    }

    public function storeConcern(): void {
        $this->ensureSchema();
        $this->validateCsrf();
        $this->syncMasterModules();

        $errors = [];
        $readField = static function (string $field, bool $trim = true) use (&$errors): string {
            $value = $_POST[$field] ?? '';
            if (!is_string($value)) {
                $errors[] = 'Invalid form value for ' . str_replace('_', ' ', $field) . '.';
                return '';
            }
            return $trim ? trim($value) : $value;
        };

        $name = $readField('name');
        $gstin = strtoupper($readField('gstin'));
        $phone = $readField('phone');
        $email = strtolower($readField('email'));
        $address = $readField('address');
        $adminName = $readField('admin_name');
        $adminEmail = strtolower($readField('admin_email'));
        $adminPass = $readField('admin_password', false);

        $textLength = static function (string $value): int {
            return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        };

        if ($textLength($name) < 2 || $textLength($name) > 150 || preg_match('/\p{L}/u', $name) !== 1) {
            $errors[] = 'Concern name must be 2 to 150 characters and contain a letter.';
        } else {
            $dupConcern = DB::fetch("SELECT id FROM concerns WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1", [$name]);
            if ($dupConcern) {
                $errors[] = 'A business concern named "' . htmlspecialchars($name) . '" already exists. Concern names must be unique.';
            }
        }
        if ($gstin !== '' && !preg_match('/^[0-9A-Z]{15}$/D', $gstin)) {
            $errors[] = 'A valid 15-character alphanumeric GSTIN is required.';
        }
        if ($phone === '') {
            $errors[] = 'Phone number is required (10 digits starting with 6, 7, 8, or 9, or prefixed with +91).';
        } else {
            $cleanDigits = ltrim($phone, '+');
            if (!ctype_digit($cleanDigits)) {
                $errors[] = 'Phone number can only contain digits with an optional leading +.';
            } else {
                $len = strlen($cleanDigits);
                if ($len === 10) {
                    $firstDigit = $cleanDigits[0];
                    if (!in_array($firstDigit, ['6', '7', '8', '9'], true)) {
                        $errors[] = 'Phone number must start with 6, 7, 8, or 9. Numbers starting with ' . $firstDigit . ' are not allowed.';
                    }
                } elseif ($len === 12 && substr($cleanDigits, 0, 2) === '91') {
                    $mobileFirstDigit = $cleanDigits[2] ?? '';
                    if (!in_array($mobileFirstDigit, ['6', '7', '8', '9'], true)) {
                        $errors[] = 'Phone number must start with 6, 7, 8, or 9. Numbers starting with ' . $mobileFirstDigit . ' are not allowed.';
                    }
                } else {
                    if ($len === 12) {
                        $errors[] = '12-digit phone numbers must begin with country code 91 or +91.';
                    } else {
                        $errors[] = 'Phone number must be exactly 10 digits (or prefixed with +91). Entered ' . $len . ' digits.';
                    }
                }
            }
        }
        if ($textLength($address) < 5 || $textLength($address) > 1000) {
            $errors[] = 'Registered physical address is required (minimum 5 characters).';
        }
        if ($adminEmail !== '') {
            if ($textLength($adminName) < 2 || $textLength($adminName) > 100 || preg_match('/\p{L}/u', $adminName) !== 1) {
                $errors[] = 'Admin name must be 2 to 100 characters and contain a letter.';
            }
            if (strlen($adminEmail) > 120 || filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Enter a valid admin login email address.';
            }
            if (strlen($adminPass) < 8 || strlen($adminPass) > 72 || !preg_match('/[A-Za-z]/', $adminPass) || !preg_match('/[0-9]/', $adminPass)) {
                $errors[] = 'Admin password must be 8 to 72 characters and include at least one letter and one number.';
            }
            if (DB::fetch("SELECT id FROM users WHERE email = ? LIMIT 1", [$adminEmail])) {
                $errors[] = 'That admin login email is already in use.';
            }
        }

        $allConcernModules = DB::fetchAll("SELECT id FROM modules WHERE is_super_admin_only = 0 AND code != 'dispatch'");
        $allowedModuleIds = array_fill_keys(array_map('strval', array_column($allConcernModules, 'id')), true);
        $rawModuleIds = $_POST['modules'] ?? [];
        $pickedModuleIds = [];
        if (!is_array($rawModuleIds) || empty($rawModuleIds)) {
            $errors[] = 'Please select at least one module for the concern from the Module Picker Checklist.';
        } else {
            foreach ($rawModuleIds as $moduleId) {
                if ((!is_string($moduleId) && !is_int($moduleId))
                    || !preg_match('/^[1-9][0-9]*$/D', (string)$moduleId)
                    || !isset($allowedModuleIds[(string)$moduleId])) {
                    $errors[] = 'The selected module list contains an invalid module.';
                    break;
                }
                $pickedModuleIds[] = (int)$moduleId;
            }
            $autoMods = DB::fetchAll("SELECT id FROM modules WHERE code IN ('concern_dashboard', 'admin_assets', 'admin_reports')");
            foreach ($autoMods as $am) {
                $pickedModuleIds[] = (int)$am['id'];
            }
            $pickedModuleIds = array_values(array_unique($pickedModuleIds));
        }
        if (!$pickedModuleIds) {
            $errors[] = 'Select at least one module for the concern.';
        }

        if ($errors) {
            $this->flash('error', implode(' ', array_unique($errors)));
            $this->redirect('/admin/concerns');
            return;
        }

        DB::beginTransaction();
        try {
            $code = $this->generateConcernCode($name);

            // 1. Create Concern
            DB::query(
                "INSERT INTO concerns (code, name, address, gstin, phone, email, status) VALUES (?, ?, ?, ?, ?, ?, 'active')",
                [
                    $code,
                    $name,
                    $address !== '' ? $address : null,
                    $gstin !== '' ? $gstin : null,
                    $phone !== '' ? $phone : null,
                    $email !== '' ? $email : null,
                ]
            );
            $concernId = (int)DB::lastInsertId();

            // 2. Assign Chosen Modules
            $insertCm = DB::pdo()->prepare("INSERT INTO concern_modules (concern_id, module_id, enabled) VALUES (?, ?, ?)");
            foreach ($allConcernModules as $m) {
                $enabled = in_array((int)$m['id'], $pickedModuleIds, true) ? 1 : 0;
                $insertCm->execute([$concernId, $m['id'], $enabled]);
            }

            // 3. Create Default Concern Admin Role
            DB::query(
                "INSERT INTO roles (concern_id, name, description) VALUES (?, 'Concern Administrator', 'Full concern access')",
                [$concernId]
            );
            $roleId = (int)DB::lastInsertId();

            // 4. Assign permissions to role for enabled modules
            $insertRp = DB::pdo()->prepare("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete) VALUES (?, ?, 1, 1, 1, 1)");
            foreach ($pickedModuleIds as $modId) {
                $insertRp->execute([$roleId, (int)$modId]);
            }

            // 5. Create Admin User (if provided)
            if ($adminEmail !== '' && $adminName !== '' && $adminPass !== '') {
                $passwordHash = password_hash($adminPass, PASSWORD_BCRYPT);
                DB::query(
                    "INSERT INTO users (concern_id, role_id, name, email, password_hash, phone, status) VALUES (?, ?, ?, ?, ?, ?, 'active')",
                    [$concernId, $roleId, $adminName, $adminEmail, $passwordHash, $phone !== '' ? $phone : null]
                );
                $adminUserId = (int)DB::lastInsertId();

                DB::query("INSERT IGNORE INTO user_concerns (user_id, concern_id) VALUES (?, ?)", [$adminUserId, $concernId]);

                // 5b. Create Initial Staff Profile for Concern Admin
                $staffCode = 'STF-' . str_pad((string)$concernId, 3, '0', STR_PAD_LEFT) . '-001';
                DB::query(
                    "INSERT INTO staff (concern_id, user_id, staff_code, name, email, password_hash, phone, designation, department, salary_type, base_salary, address, status, joining_date, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'Concern Administrator', 'Management', 'monthly', 0.00, ?, 'active', CURDATE(), NOW())",
                    [$concernId, $adminUserId, $staffCode, $adminName, $adminEmail, $passwordHash, $phone !== '' ? $phone : null, $address !== '' ? $address : null]
                );

                // 5c. Assign user_permissions for enabled modules
                $insertUp = DB::pdo()->prepare(
                    "INSERT INTO user_permissions (user_id, module_id, can_view, can_add, can_edit, can_delete)
                     VALUES (?, ?, 1, 1, 1, 1)
                     ON DUPLICATE KEY UPDATE can_view = 1, can_add = 1, can_edit = 1, can_delete = 1"
                );
                foreach ($pickedModuleIds as $modId) {
                    $insertUp->execute([$adminUserId, (int)$modId]);
                }
            }

            // 6. Seed Default Document Series
            $prefix = strtoupper(substr($code, 0, 3));
            $docTypes = ['INVOICE', 'PO', 'BATCH', 'INWARD', 'OUTWARD', 'REQ', 'DAMAGE'];
            $insertDs = DB::pdo()->prepare("INSERT INTO doc_series (concern_id, doc_type, prefix, next_no) VALUES (?, ?, ?, 101)");
            foreach ($docTypes as $dt) {
                $insertDs->execute([$concernId, $dt, "{$prefix}-{$dt}-"]);
            }

            // 7. Seed Default Cash Accounts
            DB::query(
                "INSERT INTO cash_accounts (concern_id, name, account_type, balance) VALUES (?, 'Main Cash Drawer', 'cash', 0.00)",
                [$concernId]
            );

            // 8. Seed Default Master Categories
            DB::query("INSERT INTO categories (concern_id, name) VALUES (?, 'General Raw Materials')", [$concernId]);
            $catId = (int)DB::lastInsertId();
            DB::query("INSERT INTO subcategories (concern_id, category_id, name) VALUES (?, ?, 'Default')", [$concernId, $catId]);

            DB::commit();

            AuditLog::log('CREATE_CONCERN', 'concerns', $concernId, null, ['name' => $name, 'code' => $code]);

            $this->flash('success', "Concern '{$name}' ({$code}) successfully created with " . count($pickedModuleIds) . " active modules! You can download the Concern Creation Dossier anytime from the Actions column.");
            $this->redirect('/admin/concerns');
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to create concern: ' . $e->getMessage());
            $this->redirect('/admin/concerns');
        }
    }

    private function generateConcernCode(string $name): string {
        $words = preg_split('/[^A-Z0-9]+/', strtoupper($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $token = '';
        foreach (array_slice($words, 0, 3) as $word) {
            $token .= $word[0];
        }
        if (strlen($token) < 2) {
            $token = substr(implode('', $words), 0, 3);
        }
        if ($token === '') {
            $token = 'NEW';
        }

        $baseCode = 'CON-' . $token;
        $code = $baseCode;
        $suffix = 2;
        while (DB::fetch("SELECT id FROM concerns WHERE code = ? LIMIT 1", [$code])) {
            $code = $baseCode . '-' . $suffix;
            $suffix++;
        }
        return $code;
    }

    public function toggleModule(): void {
        $this->validateCsrf();

        $concernId = (int)($_POST['concern_id'] ?? 0);
        $moduleId  = (int)($_POST['module_id'] ?? 0);
        $enabled   = (int)($_POST['enabled'] ?? 0);

        if (!$concernId || !$moduleId) {
            $this->renderJson(['success' => false, 'message' => 'Invalid parameters'], 400);
        }

        DB::query(
            "INSERT INTO concern_modules (concern_id, module_id, enabled) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)",
            [$concernId, $moduleId, $enabled]
        );

        AuditLog::log('TOGGLE_MODULE', 'concern_modules', $concernId, null, ['module_id' => $moduleId, 'enabled' => $enabled]);

        $this->renderJson(['success' => true, 'message' => 'Module status updated successfully!']);
    }

    public function updateConcern(): void {
        $this->ensureSchema();
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $concern = DB::fetch("SELECT * FROM concerns WHERE id = ?", [$id]);
        if (!$concern) {
            $this->flash('error', 'Concern not found.');
            $this->redirect('/admin/concerns');
            return;
        }

        $name    = trim($_POST['name'] ?? $concern['name']);
        $code    = strtoupper(trim($_POST['code'] ?? $concern['code']));
        $gstin   = strtoupper(trim($_POST['gstin'] ?? $concern['gstin']));
        $phone   = trim($_POST['phone'] ?? $concern['phone']);
        $email   = trim($_POST['email'] ?? $concern['email']);
        $address = trim($_POST['address'] ?? $concern['address']);
        $status  = trim($_POST['status'] ?? $concern['status']);

        if ($name === '') {
            $this->flash('error', 'Concern name is required.');
            $this->redirect('/admin/concerns');
            return;
        }
        $dupConcern = DB::fetch("SELECT id FROM concerns WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND id != ? LIMIT 1", [$name, $id]);
        if ($dupConcern) {
            $this->flash('error', 'A business concern named "' . htmlspecialchars($name) . '" already exists. Concern names must be unique.');
            $this->redirect('/admin/concerns');
            return;
        }
        if ($gstin !== '' && !preg_match('/^[0-9A-Z]{15}$/D', $gstin)) {
            $this->flash('error', 'A valid 15-character alphanumeric GSTIN is required.');
            $this->redirect('/admin/concerns');
            return;
        }
        if ($email !== '' && (strlen($email) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $this->flash('error', 'Enter a valid corporate email address or leave the field blank.');
            $this->redirect('/admin/concerns');
            return;
        }
        if ($phone === '') {
            $this->flash('error', 'Phone number is required (10 digits starting with 6, 7, 8, or 9, or prefixed with +91).');
            $this->redirect('/admin/concerns');
            return;
        } else {
            $cleanDigits = ltrim($phone, '+');
            if (!ctype_digit($cleanDigits)) {
                $this->flash('error', 'Phone number can only contain digits with an optional leading +.');
                $this->redirect('/admin/concerns');
                return;
            }
            $len = strlen($cleanDigits);
            if ($len === 10) {
                $firstDigit = $cleanDigits[0];
                if (!in_array($firstDigit, ['6', '7', '8', '9'], true)) {
                    $this->flash('error', 'Phone number must start with 6, 7, 8, or 9. Numbers starting with ' . $firstDigit . ' are not allowed.');
                    $this->redirect('/admin/concerns');
                    return;
                }
            } elseif ($len === 12 && substr($cleanDigits, 0, 2) === '91') {
                $mobileFirstDigit = $cleanDigits[2] ?? '';
                if (!in_array($mobileFirstDigit, ['6', '7', '8', '9'], true)) {
                    $this->flash('error', 'Phone number must start with 6, 7, 8, or 9. Numbers starting with ' . $mobileFirstDigit . ' are not allowed.');
                    $this->redirect('/admin/concerns');
                    return;
                }
            } else {
                if ($len === 12) {
                    $this->flash('error', '12-digit phone numbers must begin with country code 91 or +91.');
                } else {
                    $this->flash('error', 'Phone number must be exactly 10 digits (or prefixed with +91). Entered ' . $len . ' digits.');
                }
                $this->redirect('/admin/concerns');
                return;
            }
        }
        $addrLen = function_exists('mb_strlen') ? mb_strlen($address, 'UTF-8') : strlen($address);
        if ($addrLen < 5 || $addrLen > 1000) {
            $this->flash('error', 'Registered physical address is required (minimum 5 characters).');
            $this->redirect('/admin/concerns');
            return;
        }

        $rawModuleIds = $_POST['modules'] ?? [];
        $hasModulePicker = isset($_POST['modules_present']);
        if ($hasModulePicker && (empty($rawModuleIds) || !is_array($rawModuleIds))) {
            $this->flash('error', 'Please select at least one module for the concern from the Module Picker Checklist.');
            $this->redirect('/admin/concerns');
            return;
        }
        $allConcernModules = DB::fetchAll("SELECT id FROM modules WHERE is_super_admin_only = 0 AND code != 'dispatch'");
        $allowedModuleIds = [];
        foreach ($allConcernModules as $m) {
            $allowedModuleIds[(int)$m['id']] = true;
        }

        $pickedModuleIds = [];
        if (is_array($rawModuleIds)) {
            foreach ($rawModuleIds as $modId) {
                $mid = (int)$modId;
                if (isset($allowedModuleIds[$mid])) {
                    $pickedModuleIds[] = $mid;
                }
            }
        }

        $dashMod = DB::fetch("SELECT id FROM modules WHERE code = 'concern_dashboard'");
        if ($dashMod) {
            $pickedModuleIds[] = (int)$dashMod['id'];
        }
        $pickedModuleIds = array_values(array_unique($pickedModuleIds));

        if ($hasModulePicker && empty($pickedModuleIds)) {
            $this->flash('error', 'Select at least one module for the concern.');
            $this->redirect('/admin/concerns');
            return;
        }

        DB::beginTransaction();
        try {
            DB::query(
                "UPDATE concerns SET name = ?, code = ?, gstin = ?, phone = ?, email = ?, address = ?, status = ? WHERE id = ?",
                [
                    $name,
                    $code,
                    $gstin !== '' ? $gstin : null,
                    $phone !== '' ? $phone : null,
                    $email !== '' ? $email : null,
                    $address !== '' ? $address : null,
                    $status,
                    $id
                ]
            );

            if ($hasModulePicker) {
                $upsertCm = DB::pdo()->prepare(
                    "INSERT INTO concern_modules (concern_id, module_id, enabled) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)"
                );
                foreach ($allConcernModules as $m) {
                    $enabled = in_array((int)$m['id'], $pickedModuleIds, true) ? 1 : 0;
                    $upsertCm->execute([$id, $m['id'], $enabled]);
                }

                $role = DB::fetch("SELECT id FROM roles WHERE concern_id = ? ORDER BY id ASC LIMIT 1", [$id]);
                if ($role) {
                    $roleId = (int)$role['id'];
                    $insertRp = DB::pdo()->prepare("INSERT IGNORE INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete) VALUES (?, ?, 1, 1, 1, 1)");
                    foreach ($pickedModuleIds as $modId) {
                        $insertRp->execute([$roleId, $modId]);
                    }
                }
            }

            DB::commit();
            $this->flash('success', "Concern '{$name}' updated successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to update concern: ' . $e->getMessage());
        }

        $this->redirect('/admin/concerns');
    }

    public function deleteConcern(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $concern = DB::fetch("SELECT * FROM concerns WHERE id = ?", [$id]);
        if (!$concern) {
            $this->flash('error', 'Concern not found.');
            $this->redirect('/admin/concerns');
        }

        DB::beginTransaction();
        try {
            // Explicit clean deletion of all concern data in proper child -> parent order
            $childTables = [
                'stock_ledger',
                'stocks',
                'inward_items',
                'inwards',
                'outward_items',
                'outwards',
                'dispatches',
                'purchase_bill_items',
                'purchase_bills',
                'purchase_damages',
                'concern_damages',
                'returns_exchanges',
                'cutting_logs',
                'stitching_logs',
                'qc_inspections',
                'label_jobs',
                'production_programs',
                'catalogs',
                'customer_requests',
                'sales_invoice_items',
                'sales_invoices',
                'batches',
                'variants',
                'subcategories',
                'categories',
                'brands',
                'customers',
                'suppliers',
                'assets',
                'expenses',
                'cash_transactions',
                'cashier_shifts',
                'cash_accounts',
                'doc_series',
                'concern_modules',
                'users',
                'roles'
            ];

            foreach ($childTables as $tbl) {
                DB::query("DELETE FROM {$tbl} WHERE concern_id = ?", [$id]);
            }

            DB::query("DELETE FROM concerns WHERE id = ?", [$id]);

            // If the user's active session was pinned to this deleted concern, reset to Consolidated view
            if (Auth::concernId() === $id) {
                Auth::switchConcern(null);
            }

            DB::commit();

            AuditLog::log('DELETE_CONCERN', 'concerns', $id, ['name' => $concern['name'], 'code' => $concern['code']]);

            $this->flash('success', "Concern '{$concern['name']}' ({$concern['code']}) and all associated records deleted successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to delete concern: ' . $e->getMessage());
        }

        $this->redirect('/admin/concerns');
    }

    public function assets(): void {
        $cid = !Auth::isSuperAdmin() ? Auth::concernId() : null;
        if ($cid) {
            $assets = DB::fetchAll(
                "SELECT a.*, c.name AS concern_name 
                 FROM assets a
                 LEFT JOIN concerns c ON a.concern_id = c.id
                 WHERE a.concern_id = ?
                 ORDER BY a.id DESC",
                [$cid]
            );
            $concerns = DB::fetchAll("SELECT id, name FROM concerns WHERE id = ? AND status = 'active'", [$cid]);
        } else {
            $assets = DB::fetchAll(
                "SELECT a.*, c.name AS concern_name 
                 FROM assets a
                 LEFT JOIN concerns c ON a.concern_id = c.id
                 ORDER BY a.id DESC"
            );
            $concerns = DB::fetchAll("SELECT id, name FROM concerns WHERE status = 'active'");
        }

        $this->render('admin/assets', [
            'pageTitle'     => 'Asset Management',
            'currentModule' => 'admin_assets',
            'assets'        => $assets,
            'concerns'      => $concerns,
        ]);
    }

    public function storeAsset(): void {
        $this->validateCsrf();

        $code      = trim($_POST['asset_code'] ?? '');
        $name      = trim($_POST['name'] ?? '');
        $category  = trim($_POST['category'] ?? '');
        $cost      = (float)($_POST['purchase_cost'] ?? 0);
        $value     = (float)($_POST['current_value'] ?? $cost);
        $date      = $_POST['purchase_date'] ?: date('Y-m-d');
        $location  = trim($_POST['location'] ?? '');
        $concernId = !empty($_POST['concern_id']) ? (int)$_POST['concern_id'] : null;
        if (!Auth::isSuperAdmin()) {
            $concernId = Auth::concernId();
        }
        $notes     = trim($_POST['notes'] ?? '');

        if (empty($code) || empty($name)) {
            $this->flash('error', 'Asset Code and Name are required.');
            $this->redirect('/admin/assets');
        }

        try {
            DB::query(
                "INSERT INTO assets (concern_id, asset_code, name, category, purchase_date, purchase_cost, current_value, location, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)",
                [$concernId, $code, $name, $category, $date, $cost, $value, $location, $notes]
            );

            $this->flash('success', "Asset '{$name}' ({$code}) recorded successfully.");
        } catch (Exception $e) {
            $this->flash('error', 'Failed to save asset: ' . $e->getMessage());
        }

        $this->redirect('/admin/assets');
    }

    public function updateAsset(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $asset = DB::fetch("SELECT * FROM assets WHERE id = ?", [$id]);
        if (!$asset) {
            $this->flash('error', 'Asset not found.');
            $this->redirect('/admin/assets');
        }

        $code      = trim($_POST['asset_code'] ?? $asset['asset_code']);
        $name      = trim($_POST['name'] ?? $asset['name']);
        $category  = trim($_POST['category'] ?? $asset['category']);
        $cost      = (float)($_POST['purchase_cost'] ?? $asset['purchase_cost']);
        $value     = (float)($_POST['current_value'] ?? $asset['current_value']);
        $date      = $_POST['purchase_date'] ?: $asset['purchase_date'];
        $location  = trim($_POST['location'] ?? $asset['location']);
        $status    = trim($_POST['status'] ?? $asset['status']);
        $notes     = trim($_POST['notes'] ?? $asset['notes']);

        try {
            DB::query(
                "UPDATE assets SET asset_code = ?, name = ?, category = ?, purchase_cost = ?, current_value = ?, purchase_date = ?, location = ?, status = ?, notes = ? WHERE id = ?",
                [$code, $name, $category, $cost, $value, $date, $location, $status, $notes, $id]
            );
            $this->flash('success', "Asset '{$name}' updated successfully.");
        } catch (Exception $e) {
            $this->flash('error', 'Failed to update asset: ' . $e->getMessage());
        }

        $this->redirect('/admin/assets');
    }

    public function deleteAsset(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $asset = DB::fetch("SELECT * FROM assets WHERE id = ?", [$id]);
        if (!$asset) {
            $this->flash('error', 'Asset not found.');
            $this->redirect('/admin/assets');
        }

        try {
            DB::query("DELETE FROM assets WHERE id = ?", [$id]);
            $this->flash('success', "Asset '{$asset['name']}' ({$asset['asset_code']}) deleted successfully.");
        } catch (Exception $e) {
            $this->flash('error', 'Failed to delete asset: ' . $e->getMessage());
        }

        $this->redirect('/admin/assets');
    }

    public function reports(): void {
        $period     = trim($_GET['period'] ?? 'all');
        $singleDate = trim($_GET['single_date'] ?? '');
        $fromDate   = trim($_GET['from_date'] ?? '');
        $toDate     = trim($_GET['to_date'] ?? '');

        // Resolve concern IDs (supports single ID, array of IDs, or comma-separated string)
        $selectedConcernIds = [];
        if (!empty($_GET['concern_ids']) && is_array($_GET['concern_ids'])) {
            $selectedConcernIds = array_values(array_unique(array_map('intval', array_filter($_GET['concern_ids']))));
        } elseif (!empty($_GET['concern_id'])) {
            if (is_array($_GET['concern_id'])) {
                $selectedConcernIds = array_values(array_unique(array_map('intval', array_filter($_GET['concern_id']))));
            } else {
                $parts = explode(',', (string)$_GET['concern_id']);
                $selectedConcernIds = array_values(array_unique(array_map('intval', array_filter($parts))));
            }
        }

        if (!Auth::isSuperAdmin()) {
            $userCid = Auth::concernId();
            if ($userCid) {
                $selectedConcernIds = [$userCid];
            }
        }

        $range = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $salesDateFilter = "";
        $purchaseDateFilter = "";

        if (!empty($range['start_date']) && !empty($range['end_date'])) {
            $startDateEsc = addslashes($range['start_date']);
            $endDateEsc = addslashes($range['end_date']);
            $salesDateFilter = " AND si.invoice_date >= '{$startDateEsc}' AND si.invoice_date <= '{$endDateEsc}'";
            $purchaseDateFilter = " AND pb.bill_date >= '{$startDateEsc}' AND pb.bill_date <= '{$endDateEsc}'";
        }

        if (!Auth::isSuperAdmin()) {
            $userCid = Auth::concernId();
            if ($userCid) {
                $selectedConcernIds = [$userCid];
            }
        }

        $allConcerns = !Auth::isSuperAdmin() && Auth::concernId()
            ? DB::fetchAll("SELECT id, name, code, status FROM concerns WHERE id = ?", [Auth::concernId()])
            : DB::fetchAll("SELECT id, name, code, status FROM concerns ORDER BY name ASC");

        $concernWhere = "";
        $params = [];
        if (!empty($selectedConcernIds)) {
            $placeholders = implode(',', array_fill(0, count($selectedConcernIds), '?'));
            $concernWhere = " WHERE c.id IN ({$placeholders}) ";
            $params = $selectedConcernIds;
        }

        $concernStats = DB::fetchAll(
            "SELECT c.id, c.code, c.name,
                    (SELECT COUNT(*) FROM variants v WHERE v.concern_id = c.id) AS total_variants,
                    (SELECT COALESCE(SUM(s.qty), 0) FROM stocks s WHERE s.concern_id = c.id) AS total_stock_qty,
                    (SELECT COALESCE(SUM(s.qty * v.price), 0) FROM stocks s JOIN variants v ON s.variant_id = v.id WHERE s.concern_id = c.id) AS stock_valuation,
                    (SELECT COALESCE(SUM(net_amount), 0) FROM sales_invoices si WHERE si.concern_id = c.id {$salesDateFilter}) AS total_sales,
                    (SELECT COALESCE(SUM(net_amount), 0) FROM purchase_bills pb WHERE pb.concern_id = c.id {$purchaseDateFilter}) AS total_purchases
             FROM concerns c
             {$concernWhere}
             ORDER BY c.id ASC",
            $params
        );

        $this->render('admin/reports', [
            'pageTitle'          => 'Consolidated Multi-Concern Reports',
            'currentModule'      => 'admin_reports',
            'concernStats'       => $concernStats,
            'allConcerns'        => $allConcerns,
            'selectedConcernIds' => $selectedConcernIds,
            'selectedConcernId'  => (count($selectedConcernIds) === 1 ? $selectedConcernIds[0] : null),
            'period'             => $period,
            'singleDate'         => $singleDate,
            'fromDate'           => $fromDate,
            'toDate'             => $toDate,
            'rangeLabel'         => $range['label'],
        ]);
    }

    public function exportReports(): void {
        $format            = strtolower(trim($_GET['format'] ?? 'excel'));
        $period            = trim($_GET['period'] ?? 'all');
        $singleDate        = trim($_GET['single_date'] ?? '');
        $fromDate          = trim($_GET['from_date'] ?? '');
        $toDate            = trim($_GET['to_date'] ?? '');

        // Resolve concern IDs (supports single ID, array of IDs, or comma-separated string)
        $selectedConcernIds = [];
        if (!empty($_GET['concern_ids']) && is_array($_GET['concern_ids'])) {
            $selectedConcernIds = array_values(array_unique(array_map('intval', array_filter($_GET['concern_ids']))));
        } elseif (!empty($_GET['concern_id'])) {
            if (is_array($_GET['concern_id'])) {
                $selectedConcernIds = array_values(array_unique(array_map('intval', array_filter($_GET['concern_id']))));
            } else {
                $parts = explode(',', (string)$_GET['concern_id']);
                $selectedConcernIds = array_values(array_unique(array_map('intval', array_filter($parts))));
            }
        }

        if (!Auth::isSuperAdmin()) {
            $userCid = Auth::concernId();
            if ($userCid) {
                $selectedConcernIds = [$userCid];
            }
        }

        $range = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $salesDateFilter = "";
        $purchaseDateFilter = "";

        if (!empty($range['start_date']) && !empty($range['end_date'])) {
            $startDateEsc = addslashes($range['start_date']);
            $endDateEsc = addslashes($range['end_date']);
            $salesDateFilter = " AND si.invoice_date >= '{$startDateEsc}' AND si.invoice_date <= '{$endDateEsc}'";
            $purchaseDateFilter = " AND pb.bill_date >= '{$startDateEsc}' AND pb.bill_date <= '{$endDateEsc}'";
        }

        $concernWhere = "";
        $params = [];
        $targetConcern = null;
        $selectedConcernsList = [];

        if (!empty($selectedConcernIds)) {
            $placeholders = implode(',', array_fill(0, count($selectedConcernIds), '?'));
            $concernWhere = " WHERE c.id IN ({$placeholders}) ";
            $params = $selectedConcernIds;

            if (count($selectedConcernIds) === 1) {
                $targetConcern = DB::fetch("SELECT name, code FROM concerns WHERE id = ?", [$selectedConcernIds[0]]);
            } else {
                $selectedConcernsList = DB::fetchAll("SELECT name, code FROM concerns WHERE id IN ({$placeholders}) ORDER BY name ASC", $selectedConcernIds);
            }
        }

        $stats = DB::fetchAll(
            "SELECT c.id, c.code, c.name,
                    (SELECT COUNT(*) FROM variants v WHERE v.concern_id = c.id) AS total_variants,
                    (SELECT COALESCE(SUM(s.qty), 0) FROM stocks s WHERE s.concern_id = c.id) AS total_stock_qty,
                    (SELECT COALESCE(SUM(s.qty * v.price), 0) FROM stocks s JOIN variants v ON s.variant_id = v.id WHERE s.concern_id = c.id) AS stock_valuation,
                    (SELECT COALESCE(SUM(net_amount), 0) FROM sales_invoices si WHERE si.concern_id = c.id {$salesDateFilter}) AS total_sales,
                    (SELECT COALESCE(SUM(net_amount), 0) FROM purchase_bills pb WHERE pb.concern_id = c.id {$purchaseDateFilter}) AS total_purchases
             FROM concerns c
             {$concernWhere}
             ORDER BY c.id ASC",
            $params
        );

        $headers = [
            '#', 'Concern Code', 'Concern Name', 'Variants Cataloged', 'Stock Qty In Hand',
            'Live Inventory Valuation (₹)', 'Gross Purchases in Period (₹)', 'Gross Sales in Period (₹)'
        ];

        $exportData = [];
        $grandStockQty = 0;
        $grandValuation = 0.00;
        $grandPurchases = 0.00;
        $grandSales = 0.00;
        $sl = 1;

        foreach ($stats as $cs) {
            $stockQty = (float)$cs['total_stock_qty'];
            $val = (float)$cs['stock_valuation'];
            $purch = (float)$cs['total_purchases'];
            $sales = (float)$cs['total_sales'];

            $grandStockQty += $stockQty;
            $grandValuation += $val;
            $grandPurchases += $purch;
            $grandSales += $sales;

            $exportData[] = [
                $sl++,
                $cs['code'],
                $cs['name'],
                number_format($cs['total_variants']) . ' items',
                number_format($stockQty, 2),
                '₹' . number_format($val, 2),
                '₹' . number_format($purch, 2),
                '₹' . number_format($sales, 2)
            ];
        }

        if ($targetConcern) {
            $scopeLabel = "Concern: {$targetConcern['name']} ({$targetConcern['code']})";
            $reportTitle = "Performance Report - {$targetConcern['name']}";
            $cleanCode = preg_replace('/[^a-zA-Z0-9_-]/', '_', $targetConcern['code']);
            $prefixFileName = 'Report_' . $cleanCode . '_';
        } elseif (!empty($selectedConcernsList)) {
            $codes = array_column($selectedConcernsList, 'code');
            $scopeLabel = "Selected Concerns (" . count($selectedConcernsList) . "): " . implode(', ', $codes);
            $reportTitle = "Multi-Concern Performance Report (" . count($selectedConcernsList) . " Selected Concerns)";
            $prefixFileName = 'Performance_Report_' . implode('_', array_slice($codes, 0, 3)) . '_';
        } else {
            $scopeLabel = 'Consolidated All Registered Concerns';
            $reportTitle = 'Consolidated Multi-Concern Performance Report';
            $prefixFileName = 'Consolidated_Performance_Report_';
        }

        $totalsRow = [
            'TOTAL CONSOLIDATED', '', count($exportData) . ' Concern(s)', '', number_format($grandStockQty, 2),
            '₹' . number_format($grandValuation, 2), '₹' . number_format($grandPurchases, 2), '₹' . number_format($grandSales, 2)
        ];

        $metadata = [
            'Scope'        => $scopeLabel,
            'Period Scope' => $range['label'],
            'Generated By' => Auth::user()['name'] ?? 'Super Administrator',
        ];

        $cleanPeriodName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $range['period']);
        $filename = $prefixFileName . $cleanPeriodName . '_' . date('Ymd_His');

        if ($format === 'excel' || $format === 'xls' || $format === 'xlsx') {
            \App\Services\ExportService::downloadExcel($filename, $reportTitle, $metadata, $headers, $exportData, $totalsRow);
        } elseif ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView($reportTitle, $metadata, $headers, $exportData, $totalsRow);
        } else {
            \App\Services\ExportService::downloadCsv($filename, $headers, $exportData, $totalsRow);
        }
        exit;
    }

    public function exportConcerns(): void {
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT c.*, 
                       (SELECT COUNT(*) 
                        FROM concern_modules cm 
                        JOIN modules m ON cm.module_id = m.id 
                        WHERE cm.concern_id = c.id 
                          AND cm.enabled = 1 
                          AND m.code != 'concern_dashboard' 
                          AND m.is_super_admin_only = 0 
                          AND m.code != 'cash') AS active_modules_count,
                       (SELECT COUNT(*) FROM users u WHERE u.concern_id = c.id) AS users_count,
                       (SELECT COUNT(*) FROM variants v WHERE v.concern_id = c.id) AS variants_count
                FROM concerns c";
        $params = [];
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $sql .= " WHERE DATE(c.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        $sql .= " ORDER BY c.id DESC";
        $concerns = DB::fetchAll($sql, $params);

        $headers = [
            'Concern Code', 'Business Name', 'GSTIN', 'Phone', 'Email', 
            'Active Modules', 'Assigned Users', 'Total Products', 'Status', 'Registered Date'
        ];

        $rows = [];
        foreach ($concerns as $c) {
            $rows[] = [
                $c['code'],
                $c['name'],
                $c['gstin'] ?: '-',
                $c['phone'] ?: '-',
                $c['email'] ?: '-',
                (int)$c['active_modules_count'],
                (int)$c['users_count'],
                (int)$c['variants_count'],
                strtoupper($c['status'] ?? 'ACTIVE'),
                date('d M Y', strtotime($c['created_at']))
            ];
        }

        $totals = ['Total Registered Concerns: ' . count($concerns), '', '', '', '', '', '', '', '', ''];
        $metadata = [
            'Report Title' => 'Registered Enterprise Concerns & Tenant Directory',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Tenants' => count($concerns)
        ];

        $filename = 'concerns_directory_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Enterprise Concerns Directory Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Enterprise Concerns Directory Report', $metadata, $headers, $rows, $totals);
        }
    }

    public function downloadConcernDossier(): void {
        $id = (int)($_GET['id'] ?? 0);
        $format = strtolower(trim($_GET['format'] ?? 'pdf'));

        $concern = DB::fetch("SELECT * FROM concerns WHERE id = ?", [$id]);
        if (!$concern) {
            $this->flash('error', 'Concern not found for dossier generation.');
            $this->redirect('/admin/concerns');
            return;
        }

        // 1. Fetch Primary Admin User
        $adminUser = DB::fetch(
            "SELECT u.name, u.email, u.phone, u.status, u.created_at, r.name as role_name
             FROM users u
             LEFT JOIN roles r ON u.role_id = r.id
             WHERE u.concern_id = ?
             ORDER BY u.id ASC LIMIT 1",
            [$id]
        );

        // 2. Fetch Modules with enabled status
        $modules = DB::fetchAll(
            "SELECT m.id, m.code, m.name, m.group_name, m.icon, COALESCE(cm.enabled, 0) as enabled
             FROM modules m
             LEFT JOIN concern_modules cm ON m.id = cm.module_id AND cm.concern_id = ?
             WHERE m.is_super_admin_only = 0
             ORDER BY m.sort_order ASC",
            [$id]
        );

        // 3. Fetch Document Series
        $docSeries = DB::fetchAll(
            "SELECT doc_type, prefix, next_no FROM doc_series WHERE concern_id = ? ORDER BY doc_type ASC",
            [$id]
        );

        // 4. Fetch Cash Accounts
        $cashAccounts = DB::fetchAll(
            "SELECT name, account_type, balance FROM cash_accounts WHERE concern_id = ? ORDER BY id ASC",
            [$id]
        );

        // 5. Fetch Categories
        $categories = DB::fetchAll(
            "SELECT c.name as category_name, s.name as subcategory_name
             FROM categories c
             LEFT JOIN subcategories s ON c.id = s.category_id
             WHERE c.concern_id = ?
             ORDER BY c.id ASC",
            [$id]
        );

        if ($format === 'pdf') {
            $this->renderConcernDossierPdf($concern, $adminUser, $modules, $docSeries, $cashAccounts, $categories);
            return;
        }

        $activeModulesCount = 0;
        foreach ($modules as $m) {
            if (!empty($m['enabled'])) {
                $activeModulesCount++;
            }
        }

        $headers = ['Section', 'Attribute / Parameter', 'Configured Value', 'System Provisioning Status'];
        $rows = [
            ['1. Concern Business Profile', 'Concern Business Legal Name', $concern['name'], 'CONFIRMED'],
            ['1. Concern Business Profile', 'Concern Reference Code', $concern['code'], 'SYSTEM ALLOCATED'],
            ['1. Concern Business Profile', 'GSTIN / Tax Registration', $concern['gstin'] ?: 'Unregistered / Exempted', $concern['gstin'] ? 'REGISTERED' : 'UNREGISTERED'],
            ['1. Concern Business Profile', 'Primary Contact Phone', $concern['phone'] ?: 'Not specified', '-'],
            ['1. Concern Business Profile', 'Corporate Email', $concern['email'] ?: 'Not specified', '-'],
            ['1. Concern Business Profile', 'Registered Business Address', $concern['address'] ?: 'Not specified', '-'],
            ['1. Concern Business Profile', 'Concern Operating Status', strtoupper($concern['status']), strtoupper($concern['status'])],
            ['1. Concern Business Profile', 'Creation & Provisioning Timestamp', date('d M Y, h:i A', strtotime($concern['created_at'])), 'AUDITED'],

            ['2. Initial Tenant Admin Account', 'Administrator Full Name', $adminUser['name'] ?? 'Not Provisioned', 'ACTIVE'],
            ['2. Initial Tenant Admin Account', 'Admin Login Email', $adminUser['email'] ?? 'Not Provisioned', 'CREDENTIAL SEEDED'],
            ['2. Initial Tenant Admin Account', 'Admin Contact Phone', $adminUser['phone'] ?? '-', '-'],
            ['2. Initial Tenant Admin Account', 'Assigned Security Role', $adminUser['role_name'] ?? 'Concern Administrator', 'SUPER PRIVILEGED'],
            ['2. Initial Tenant Admin Account', 'Tenant Sign-in URL', \App\Core\Controller::url('/login'), 'ONLINE'],

            ['3. Baseline Document Series', 'Total Series Initialized', count($docSeries) . ' Series Configured', 'ACTIVE'],
        ];

        foreach ($docSeries as $ds) {
            $rows[] = [
                '3. Baseline Document Series',
                'Series: ' . $ds['doc_type'],
                'Prefix: ' . $ds['prefix'] . ' (Starts at #' . $ds['next_no'] . ')',
                'INITIALIZED'
            ];
        }

        foreach ($cashAccounts as $ca) {
            $rows[] = [
                '4. Seeded Financial Accounts',
                $ca['name'] . ' (' . ucfirst($ca['account_type']) . ')',
                'Initial Balance: ₹' . number_format((float)$ca['balance'], 2),
                'OPEN'
            ];
        }

        foreach ($categories as $cat) {
            $rows[] = [
                '5. Master Inventory Classification',
                $cat['category_name'],
                'Subcategory: ' . ($cat['subcategory_name'] ?? 'Default'),
                'SEEDED'
            ];
        }

        foreach ($modules as $mod) {
            $isEn = !empty($mod['enabled']);
            $rows[] = [
                '6. Module Entitlements (' . $mod['group_name'] . ')',
                $mod['name'] . ' [' . $mod['code'] . ']',
                $mod['group_name'] . ' Functional Unit',
                $isEn ? 'PROVISIONED & ACTIVE' : 'RESTRICTED / DISABLED'
            ];
        }

        $metadata = [
            'Dossier Type' => 'Business Unit Creation & Tenant Provisioning Dossier',
            'Concern Code' => $concern['code'],
            'Business Name' => $concern['name'],
            'GSTIN' => $concern['gstin'] ?: 'Unregistered',
            'Tenant Admin' => ($adminUser['name'] ?? '-') . ' (' . ($adminUser['email'] ?? '-') . ')',
            'Provisioned Modules' => $activeModulesCount . ' of ' . count($modules) . ' Modules Active',
            'Dossier Generated' => date('d M Y, h:i A'),
        ];

        $totals = ['Total Modules Evaluated: ' . count($modules), $activeModulesCount . ' Provisioned & Active', '', ''];
        $cleanCode = preg_replace('/[^a-zA-Z0-9]+/', '_', $concern['code']);
        $filename = 'concern_creation_dossier_' . strtolower($cleanCode) . '_' . date('Ymd_His');

        if ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Concern Creation & Tenant Provisioning Dossier: ' . $concern['name'], $metadata, $headers, $rows, $totals);
        }
    }

    private function renderConcernDossierPdf(array $concern, ?array $adminUser, array $modules, array $docSeries, array $cashAccounts, array $categories): void {
        $activeModulesCount = 0;
        $groupedModules = [];
        foreach ($modules as $m) {
            if (!empty($m['enabled'])) {
                $activeModulesCount++;
            }
            $grp = $m['code'] === 'catalog_mapping' ? 'Purchase' : $m['group_name'];
            $groupedModules[$grp][] = $m;
        }

        $dossierRef = 'DOS-' . preg_replace('/[^A-Z0-9]/', '', strtoupper($concern['code'])) . '-' . date('Ymd');
        $backUrl = \App\Core\Controller::url('/admin/concerns');
        $excelUrl = \App\Core\Controller::url('/admin/concerns/download?id=' . $concern['id'] . '&format=excel');
        $csvUrl = \App\Core\Controller::url('/admin/concerns/download?id=' . $concern['id'] . '&format=csv');
        $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . \App\Core\Controller::url('/login');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title><?= htmlspecialchars($concern['name']) ?> (<?= htmlspecialchars($concern['code']) ?>) - Concern Creation Dossier</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
            <style>
                @page {
                    size: A4 portrait;
                    margin: 10mm 12mm 12mm 12mm;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    background: #f1f5f9;
                    color: #0f172a;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .dossier-wrapper {
                    max-width: 900px;
                    margin: 20px auto 40px auto;
                    background: #ffffff;
                    padding: 36px 42px;
                    border-radius: 14px;
                    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08), 0 8px 10px -6px rgba(0,0,0,0.04);
                    border: 1px solid #e2e8f0;
                }
                .action-bar {
                    background: #0f172a;
                    color: #ffffff;
                    padding: 14px 24px;
                    border-radius: 12px;
                    margin-bottom: 24px;
                    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
                }
                .dossier-header {
                    border-bottom: 2px solid #2563eb;
                    padding-bottom: 18px;
                    margin-bottom: 22px;
                }
                .section-header {
                    background: #f8fafc;
                    border-left: 4px solid #2563eb;
                    padding: 8px 14px;
                    font-weight: 700;
                    font-size: 12.5px;
                    color: #1e3a8a;
                    margin-bottom: 12px;
                    margin-top: 18px;
                    border-radius: 0 6px 6px 0;
                }
                .prop-label {
                    color: #64748b;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    margin-bottom: 2px;
                }
                .prop-value {
                    color: #0f172a;
                    font-weight: 600;
                    font-size: 12.5px;
                }
                .table-dossier th {
                    background: #1e293b !important;
                    color: #ffffff !important;
                    font-size: 10.5px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    padding: 7px 10px !important;
                }
                .table-dossier td {
                    padding: 6px 10px !important;
                    font-size: 11.5px;
                    border-color: #e2e8f0 !important;
                    vertical-align: middle;
                }
                .sign-box {
                    border: 1px dashed #94a3b8;
                    border-radius: 8px;
                    padding: 16px;
                    min-height: 110px;
                    background: #fafafa;
                }
                @media print {
                    body {
                        background: #ffffff !important;
                    }
                    .dossier-wrapper {
                        box-shadow: none !important;
                        padding: 0 !important;
                        margin: 0 !important;
                        max-width: 100% !important;
                        border: none !important;
                    }
                    .no-print {
                        display: none !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="dossier-wrapper">
                <!-- Action Toolbar for Browser Screen -->
                <div class="no-print action-bar d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(37,99,235,0.2); display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                            <i class="bi bi-file-earmark-lock text-info"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-6">Concern Creation Dossier Ready</div>
                            <div class="small text-light text-opacity-75">Print to PDF, export to Excel, or save for tenant onboarding records.</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-outline-light px-3">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                        <a href="<?= htmlspecialchars($excelUrl) ?>" class="btn btn-sm btn-success px-3 fw-semibold">
                            <i class="bi bi-file-earmark-excel me-1"></i> Download Excel
                        </a>
                        <a href="<?= htmlspecialchars($csvUrl) ?>" class="btn btn-sm btn-secondary px-3 fw-semibold">
                            <i class="bi bi-file-earmark-text me-1"></i> Download CSV
                        </a>
                        <button type="button" class="btn btn-sm btn-primary px-4 fw-bold shadow-sm" onclick="window.print()" style="background: #2563eb; border: none;">
                            <i class="bi bi-printer me-1"></i> Print / Save as PDF
                        </button>
                    </div>
                </div>

                <!-- Document Header -->
                <div class="dossier-header d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-uppercase tracking-wider small fw-bold text-primary mb-1">
                            <i class="bi bi-shield-check me-1"></i> GRM Inventory
                        </div>
                        <h3 class="fw-bold text-dark mb-1">Concern Creation & Onboarding Dossier</h3>
                        <p class="text-muted small mb-0">Official Business Unit Provisioning Certificate & Tenant Configuration Record</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-dark font-monospace fs-6 px-3 py-2"><?= htmlspecialchars($concern['code']) ?></span>
                        <div class="small text-muted mt-1">Ref: <span class="font-monospace text-dark fw-semibold"><?= htmlspecialchars($dossierRef) ?></span></div>
                        <div class="small text-muted">Created: <?= date('d M Y, h:i A', strtotime($concern['created_at'])) ?></div>
                    </div>
                </div>

                <!-- Section 1: Business Unit Profile -->
                <div class="section-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-building me-2"></i> 1. Legal Entity & Business Profile</span>
                    <span class="badge <?= $concern['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?> text-uppercase"><?= htmlspecialchars($concern['status']) ?></span>
                </div>
                <div class="card border rounded-3 p-3 mb-3 bg-white">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <div class="prop-label">Business Legal Name</div>
                            <div class="prop-value fs-6 text-primary"><?= htmlspecialchars($concern['name']) ?></div>
                        </div>
                        <div class="col-md-5">
                            <div class="prop-label">Concern System Code</div>
                            <div class="prop-value font-monospace"><?= htmlspecialchars($concern['code']) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="prop-label">GSTIN / Tax ID</div>
                            <div class="prop-value font-monospace"><?= htmlspecialchars($concern['gstin'] ?: 'Unregistered / Exempt') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="prop-label">Primary Contact Phone</div>
                            <div class="prop-value"><?= htmlspecialchars($concern['phone'] ?: 'Not specified') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="prop-label">Corporate Email</div>
                            <div class="prop-value"><?= htmlspecialchars($concern['email'] ?: 'Not specified') ?></div>
                        </div>
                        <div class="col-12">
                            <div class="prop-label">Registered Business Address</div>
                            <div class="prop-value text-secondary fw-normal"><?= htmlspecialchars($concern['address'] ?: 'No physical address specified') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Tenant Admin Account -->
                <div class="section-header">
                    <i class="bi bi-person-badge me-2"></i> 2. Initial Tenant Administrator Credentials & Access
                </div>
                <div class="card border rounded-3 p-3 mb-3 bg-white">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="prop-label">Designated Administrator</div>
                            <div class="prop-value text-dark"><?= htmlspecialchars($adminUser['name'] ?? 'Not Provisioned') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="prop-label">Login Email Address</div>
                            <div class="prop-value font-monospace text-primary"><?= htmlspecialchars($adminUser['email'] ?? 'Not Provisioned') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="prop-label">Security Role</div>
                            <div class="prop-value"><?= htmlspecialchars($adminUser['role_name'] ?? 'Concern Administrator') ?></div>
                        </div>
                        <div class="col-md-8">
                            <div class="prop-label">Tenant Login Portal URL</div>
                            <div class="prop-value font-monospace small text-muted"><?= htmlspecialchars($loginUrl) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="prop-label">Credential Status</div>
                            <div class="prop-value text-success"><i class="bi bi-check-circle-fill me-1"></i> Provisioned (bcrypt)</div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Module Licensing Matrix -->
                <div class="section-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-grid-3x3-gap me-2"></i> 3. Module Licensing & Feature Provisioning Matrix</span>
                    <span class="badge bg-primary"><?= $activeModulesCount ?> / <?= count($modules) ?> Active</span>
                </div>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered table-dossier mb-0">
                        <thead>
                            <tr>
                                <th style="width: 22%;">Functional Group</th>
                                <th style="width: 38%;">Module Name</th>
                                <th style="width: 20%;">Identifier Code</th>
                                <th style="width: 20%; text-align: center;">Provisioning Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groupedModules as $grp => $mods): ?>
                                <?php foreach ($mods as $idx => $m): ?>
                                    <tr>
                                        <?php if ($idx === 0): ?>
                                            <td rowspan="<?= count($mods) ?>" class="fw-bold bg-light align-middle text-dark">
                                                <?= htmlspecialchars($grp) ?>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <i class="bi <?= htmlspecialchars($m['icon']) ?> text-muted me-1"></i>
                                            <?= htmlspecialchars($m['name']) ?>
                                        </td>
                                        <td class="font-monospace text-muted small"><?= htmlspecialchars($m['code']) ?></td>
                                        <td class="text-center">
                                            <?php if (!empty($m['enabled'])): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                                    <i class="bi bi-check-circle-fill me-1"></i> PROVISIONED
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1">
                                                    <i class="bi bi-slash-circle me-1"></i> RESTRICTED
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Section 4: Document Series & Baseline Financial Setup -->
                <div class="section-header">
                    <i class="bi bi-receipt me-2"></i> 4. Baseline Document Series & Initial Master Setup
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card border rounded-3 p-3 h-100 bg-white">
                            <h6 class="fw-bold text-dark mb-2 small"><i class="bi bi-123 text-primary me-1"></i> Document Numbering Series</h6>
                            <table class="table table-sm table-borderless small mb-0">
                                <thead>
                                    <tr class="text-muted border-bottom">
                                        <th>Series Type</th>
                                        <th>Prefix Pattern</th>
                                        <th class="text-end">Next No</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($docSeries as $ds): ?>
                                        <tr>
                                            <td class="fw-semibold text-dark"><?= htmlspecialchars($ds['doc_type']) ?></td>
                                            <td class="font-monospace text-muted"><?= htmlspecialchars($ds['prefix']) ?></td>
                                            <td class="font-monospace text-end text-primary">#<?= (int)$ds['next_no'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border rounded-3 p-3 h-100 bg-white">
                            <h6 class="fw-bold text-dark mb-2 small"><i class="bi bi-wallet2 text-success me-1"></i> Seeded Financial & Master Setup</h6>
                            <div class="mb-3">
                                <div class="prop-label">Initial Cash Drawer</div>
                                <?php if (!empty($cashAccounts)): ?>
                                    <?php foreach ($cashAccounts as $ca): ?>
                                        <div class="prop-value small text-dark d-flex justify-content-between">
                                            <span><?= htmlspecialchars($ca['name']) ?> (<?= htmlspecialchars(ucfirst($ca['account_type'])) ?>)</span>
                                            <span class="font-monospace text-success">₹<?= number_format((float)$ca['balance'], 2) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted small">Main Cash Drawer provisioned</div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="prop-label">Master Raw Material Classification</div>
                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <div class="prop-value small text-dark">
                                            <i class="bi bi-tag text-muted me-1"></i> <?= htmlspecialchars($cat['category_name']) ?>
                                            <span class="badge bg-light text-secondary border ms-1"><?= htmlspecialchars($cat['subcategory_name'] ?? 'Default') ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted small">General Raw Materials (Default)</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 5: Authorization & Handover Sign-off -->
                <div class="section-header">
                    <i class="bi bi-pen me-2"></i> 5. Enterprise Verification, Authorization & Handover Sign-off
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="sign-box d-flex flex-column justify-content-between">
                            <div>
                                <div class="prop-label">Provisioned & Authorized By</div>
                                <div class="fw-bold text-dark mt-1">Enterprise Super Administrator</div>
                                <div class="small text-muted">GRM Inventory Central Management</div>
                            </div>
                            <div class="pt-4 border-top mt-3 d-flex justify-content-between small text-muted">
                                <span>Signature: ______________________</span>
                                <span>Date: <?= date('d M Y') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="sign-box d-flex flex-column justify-content-between">
                            <div>
                                <div class="prop-label">Received & Accepted By</div>
                                <div class="fw-bold text-dark mt-1"><?= htmlspecialchars($adminUser['name'] ?? $concern['name']) ?></div>
                                <div class="small text-muted">Authorized Concern Signatory & Administrator</div>
                            </div>
                            <div class="pt-4 border-top mt-3 d-flex justify-content-between small text-muted">
                                <span>Signature: ______________________</span>
                                <span>Seal / Date: _________________</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Document Footer Note -->
                <div class="border-top pt-3 text-center text-muted small">
                    <span>GRM Inventory &bull; Confidential Business Unit Provisioning Dossier &bull; Generated <?= date('d M Y, h:i A') ?></span>
                </div>
            </div>
        </body>
        </html>
        <?php
        if (php_sapi_name() !== 'cli') {
            exit;
        }
    }

    public function downloadConcernTemplate(): void {
        $format = strtolower(trim($_GET['format'] ?? 'excel'));

        $headers = [
            'Concern Legal Name *',
            'Code Prefix (2-5 Uppercase Chars)',
            'GSTIN (15 Alphanumeric)',
            'Primary Phone *',
            'Corporate Email',
            'Registered Business Address',
            'Admin Full Name *',
            'Admin Login Email *',
            'Admin Initial Password *',
            'Initial Cash Account Name',
            'Master Raw Material Category',
            'Assigned Module Codes (Comma-separated or ALL)'
        ];

        $sampleRow1 = [
            'Apex Apparel Mills',
            'APEX',
            '33AAAAA0000A1Z5',
            '9876543210',
            'info@apexapparel.com',
            'Plot 45, SIPCOT Industrial Park, Tirupur - 641603',
            'Rajesh Sharma',
            'admin@apexapparel.com',
            'ApexSecure#2026',
            'Main Cash Drawer',
            'General Raw Materials',
            'ALL'
        ];

        $sampleRow2 = [
            'Sundaram Spinning Co',
            'SSC',
            '33BBBBB1111B2Z6',
            '9842100055',
            'admin@sundaramspinning.com',
            '12 Weavers Colony, Coimbatore - 641001',
            'Suresh Kumar',
            'suresh@sundaram.com',
            'SpinMaster@2026',
            'Main Cash Drawer',
            'General Raw Materials',
            'purchase_billing, purchase_stocks, catalog_mapping, stocks, inward'
        ];

        $blankTemplateRow = [
            '[Enter Business Legal Name]',
            '[e.g. GRM]',
            '[15-digit GSTIN or leave blank]',
            '[10 to 15 digits]',
            '[Email address or leave blank]',
            '[Full street address]',
            '[Administrator Name]',
            '[Unique Admin Email]',
            '[Minimum 8 chars with letter & number]',
            'Main Cash Drawer',
            'General Raw Materials',
            'ALL'
        ];

        $allModules = DB::fetchAll("SELECT code, name, group_name FROM modules WHERE is_super_admin_only = 0 ORDER BY sort_order ASC");
        $moduleListStr = implode(', ', array_column($allModules, 'code'));

        $rows = [
            $sampleRow1,
            $sampleRow2,
            $blankTemplateRow,
            ['', '', '', '', '', '', '', '', '', '', '', ''],
            ['--- MODULE CODE REFERENCE DIRECTORY ---', '', '', '', '', '', '', '', '', '', '', ''],
            ['Specify individual module codes separated by comma or specify ALL to grant all modules.', '', '', '', '', '', '', '', '', '', '', ''],
            ['All Available Module Codes:', $moduleListStr, '', '', '', '', '', '', '', '', '', '']
        ];

        foreach ($allModules as $mod) {
            $rows[] = [
                'Module Code: ' . $mod['code'],
                'Name: ' . $mod['name'],
                'Functional Group: ' . $mod['group_name'],
                'Valid input for Assigned Module Codes column',
                '', '', '', '', '', '', '', ''
            ];
        }

        $metadata = [
            'Template' => 'GRM Inventory - Concern Creation & Tenant Onboarding Specification Sheet',
            'Purpose' => 'Offline concern setup data collection, drafting and onboarding specification',
            'Instructions' => 'Fill the required fields marked with *. For Assigned Module Codes, enter ALL or comma-separated module codes from the directory below.',
            'Available Modules Count' => count($allModules) . ' Modules available for provisioning',
            'Generated On' => date('d M Y, h:i A')
        ];

        $totals = ['Module Codes Available: ' . count($allModules), 'Ready for Super Admin Provisioning', '', '', '', '', '', '', '', '', '', ''];
        $filename = 'concern_creation_template_' . date('Ymd_His');

        if ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, array_slice($rows, 0, 3), []);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'GRM Inventory - Concern Creation Onboarding Template', $metadata, $headers, $rows, $totals);
        }
    }

    public function exportAssets(): void {
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT a.*, c.name AS concern_name 
                FROM assets a
                LEFT JOIN concerns c ON a.concern_id = c.id";
        $params = [];
        $whereClauses = [];
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $whereClauses[] = "DATE(a.purchase_date) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!Auth::isSuperAdmin() && Auth::concernId()) {
            $whereClauses[] = "a.concern_id = ?";
            $params[] = Auth::concernId();
        }
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $sql .= " ORDER BY a.id DESC";
        $assets = DB::fetchAll($sql, $params);

        $headers = [
            'Asset Code', 'Asset Name', 'Concern / Location', 'Purchase Date', 
            'Cost Value (₹)', 'Current Value (₹)', 'Status'
        ];

        $rows = [];
        $totCost = 0; $totCurrent = 0;
        foreach ($assets as $a) {
            $cost = (float)($a['cost_value'] ?? 0);
            $curr = (float)($a['current_value'] ?? 0);
            $totCost += $cost; $totCurrent += $curr;

            $rows[] = [
                $a['asset_code'] ?? ('AST-' . str_pad($a['id'], 4, '0', STR_PAD_LEFT)),
                $a['name'],
                $a['concern_name'] ?? 'General Corporate',
                $a['purchase_date'] ? date('d M Y', strtotime($a['purchase_date'])) : '-',
                '₹' . number_format($cost, 2),
                '₹' . number_format($curr, 2),
                strtoupper($a['status'] ?? 'ACTIVE')
            ];
        }

        $totals = ['Total: ' . count($assets) . ' Assets', '', '', '', '₹' . number_format($totCost, 2), '₹' . number_format($totCurrent, 2), ''];
        $metadata = [
            'Report Title' => 'Corporate Capital Assets & Plant Equipment Register',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Assets Count' => count($assets),
            'Total Capital Cost' => '₹' . number_format($totCost, 2),
            'Total Book Valuation' => '₹' . number_format($totCurrent, 2)
        ];

        $filename = 'assets_register_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Corporate Asset Register Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Corporate Asset Register Report', $metadata, $headers, $rows, $totals);
        }
    }
}
