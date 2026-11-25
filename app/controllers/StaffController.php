<?php
// app/controllers/StaffController.php - Staff Management Module Controller

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Core\ModuleGuard;
use App\Services\NumberSeries;
use App\Services\ExportService;
use App\Services\AuditLog;
use Exception;

class StaffController extends Controller {
    private static bool $schemaEnsured = false;

    public function __construct() {
        $this->ensureSchema();
    }

    protected function ensureSchema(): void {
        if (self::$schemaEnsured) {
            return;
        }

        try {
            DB::query("CREATE TABLE IF NOT EXISTS `user_concerns` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `concern_id` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_user_concern` (`user_id`, `concern_id`),
                INDEX `idx_uc_user` (`user_id`),
                INDEX `idx_uc_concern` (`concern_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            DB::query("CREATE TABLE IF NOT EXISTS `user_permissions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `concern_id` INT NULL,
                `module_id` INT NOT NULL,
                `can_view` TINYINT(1) NOT NULL DEFAULT 1,
                `can_add` TINYINT(1) NOT NULL DEFAULT 0,
                `can_edit` TINYINT(1) NOT NULL DEFAULT 0,
                `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_user_concern_module` (`user_id`, `concern_id`, `module_id`),
                INDEX `idx_up_user` (`user_id`),
                INDEX `idx_up_concern` (`concern_id`),
                INDEX `idx_up_module` (`module_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            try {
                $upCols = array_column(DB::fetchAll("DESCRIBE `user_permissions`"), 'Field');
                if (!in_array('concern_id', $upCols)) {
                    DB::query("ALTER TABLE `user_permissions` ADD COLUMN `concern_id` INT NULL AFTER `user_id`");
                    try { DB::query("ALTER TABLE `user_permissions` DROP INDEX `uk_user_module`"); } catch (\Throwable $e) {}
                    try { DB::query("ALTER TABLE `user_permissions` ADD UNIQUE KEY `uk_user_concern_module` (`user_id`, `concern_id`, `module_id`)"); } catch (\Throwable $e) {}
                    try { DB::query("ALTER TABLE `user_permissions` ADD INDEX `idx_up_concern` (`concern_id`)"); } catch (\Throwable $e) {}
                }
            } catch (\Throwable $e) {}

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

            self::$schemaEnsured = true;
        } catch (\Throwable $e) {
            error_log('StaffController ensureSchema error: ' . $e->getMessage());
        }
    }

    public function index(): void {
        ModuleGuard::enforce('staff', 'view');
        $this->ensureSchema();
        $allConcerns = DB::fetchAll(
            "SELECT c.id, c.name, c.code, c.status,
                    (SELECT COUNT(*) FROM staff s WHERE s.concern_id = c.id) AS staff_count
             FROM concerns c
             WHERE c.status = 'active'
             ORDER BY c.name ASC"
        );

        // Concern selection logic
        $selectedCid = null;
        if (isset($_GET['concern_id']) && $_GET['concern_id'] !== '') {
            $selectedCid = (int)$_GET['concern_id'];
            $_SESSION['staff_selected_concern_id'] = $selectedCid;
        } elseif (!empty($_SESSION['staff_selected_concern_id'])) {
            $selectedCid = (int)$_SESSION['staff_selected_concern_id'];
        } else {
            $selectedCid = Auth::concernId();
        }

        // Only show roles belonging to this specific concern or global roles (Super Admin)
        if ($selectedCid) {
            $allRoles = DB::fetchAll(
                "SELECT id, name FROM roles WHERE concern_id = ? OR concern_id IS NULL ORDER BY (concern_id IS NULL) DESC, id ASC",
                [$selectedCid]
            );
        } else {
            $allRoles = DB::fetchAll("SELECT id, name FROM roles ORDER BY (concern_id IS NULL) DESC, id ASC");
        }
        $allDepartments = DB::fetchAll("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");

        // If force choose or if no concern selected yet
        $showConcernModal = false;
        if (isset($_GET['choose']) || empty($selectedCid)) {
            $showConcernModal = true;
        }

        $activeConcern = null;
        if ($selectedCid) {
            $activeConcern = DB::fetch("SELECT id, name, code FROM concerns WHERE id = ?", [$selectedCid]);
            if (!$activeConcern) {
                $selectedCid = null;
                $showConcernModal = true;
            }
        }

        // Filters: search, role_id, department, status
        $search = trim($_GET['search'] ?? '');
        $roleFilter = isset($_GET['role_id']) && $_GET['role_id'] !== '' ? (int)$_GET['role_id'] : null;
        $deptFilter = trim($_GET['department'] ?? '');
        $statusFilter = trim($_GET['status'] ?? '');

        $sql = "SELECT s.*, c.name AS concern_name, c.code AS concern_code,
                       u.email AS user_email, u.role_id, u.status AS user_status,
                       r.name AS role_name
                FROM staff s
                JOIN concerns c ON s.concern_id = c.id
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE 1=1";
        $params = [];

        if ($selectedCid) {
            $sql .= " AND s.concern_id = ?";
            $params[] = $selectedCid;
        }
        if ($search !== '') {
            $sql .= " AND (s.name LIKE ? OR s.staff_code LIKE ? OR s.phone LIKE ? OR s.email LIKE ? OR s.designation LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        if ($roleFilter) {
            $sql .= " AND u.role_id = ?";
            $params[] = $roleFilter;
        }
        if ($deptFilter !== '') {
            $sql .= " AND s.department = ?";
            $params[] = $deptFilter;
        }
        if ($statusFilter !== '') {
            $sql .= " AND s.status = ?";
            $params[] = $statusFilter;
        }

        $sql .= " ORDER BY s.id DESC";
        $staffMembers = DB::fetchAll($sql, $params);

        // Modules and grouping for permissions matrix (used in edit staff modal)
        $modules = DB::fetchAll(
            "SELECT * FROM modules WHERE is_super_admin_only = 0 AND code != 'cash' ORDER BY group_name ASC, sort_order ASC, id ASC"
        );
        $groupOrder = ['Purchase', 'Production', 'Stock', 'Sales', 'Shop', 'Finance', 'Staff'];
        $groupedModules = [];
        foreach ($modules as $m) {
            if ($m['code'] === 'cash') continue;
            if ($m['code'] === 'concern_dashboard' || ($m['group_name'] ?? '') === 'Dashboard') continue;
            if ($m['code'] === 'variants' || ($m['group_name'] ?? '') === 'Masters') continue;
            $grp = $m['group_name'] ?: 'General';
            $groupedModules[$grp][] = $m;
        }

        // Concern enabled modules mapping for permissions scoping
        $concernModules = [];
        $cmRows = DB::fetchAll("SELECT concern_id, module_id FROM concern_modules WHERE enabled = 1");
        foreach ($cmRows as $cm) {
            $concernModules[(int)$cm['concern_id']][] = (int)$cm['module_id'];
        }

        $openAddModal = !empty($_GET['action']) && $_GET['action'] === 'add';

        $this->render('concern/staff', [
            'pageTitle'        => 'Staff Management & Register',
            'currentModule'    => 'staff',
            'currentSubModule' => 'all',
            'allConcerns'      => $allConcerns,
            'allRoles'         => $allRoles,
            'allDepartments'   => $allDepartments,
            'selectedCid'      => $selectedCid,
            'activeConcern'    => $activeConcern,
            'staffMembers'     => $staffMembers,
            'modules'          => $modules,
            'groupedModules'   => $groupedModules,
            'groupOrder'       => $groupOrder,
            'concernModules'   => $concernModules,
            'showConcernModal' => $showConcernModal,
            'openAddModal'     => $openAddModal,
            'filters'          => [
                'search'     => $search,
                'role_id'    => $roleFilter,
                'department' => $deptFilter,
                'status'     => $statusFilter,
            ],
        ]);
    }

    public function create(): void {
        ModuleGuard::enforce('staff', 'create');
        $allConcerns = DB::fetchAll(
            "SELECT c.id, c.name, c.code, c.status
             FROM concerns c
             WHERE c.status = 'active'
             ORDER BY c.name ASC"
        );

        $selectedCid = null;
        if (!empty($_GET['concern_id'])) {
            $selectedCid = (int)$_GET['concern_id'];
            $_SESSION['staff_selected_concern_id'] = $selectedCid;
        } elseif (!empty($_SESSION['staff_selected_concern_id'])) {
            $selectedCid = (int)$_SESSION['staff_selected_concern_id'];
        } else {
            $selectedCid = Auth::concernId();
        }

        $activeConcern = null;
        if ($selectedCid) {
            $activeConcern = DB::fetch("SELECT id, name, code FROM concerns WHERE id = ?", [$selectedCid]);
        }

        $roles = DB::fetchAll("SELECT id, name, description FROM roles ORDER BY id ASC");

        // Operational modules matching sidebar (Images 1 & 2)
        $modules = DB::fetchAll(
            "SELECT * FROM modules WHERE is_super_admin_only = 0 AND code != 'cash' ORDER BY group_name ASC, sort_order ASC, id ASC"
        );

        // Concern enabled modules mapping
        $concernModules = [];
        $cmRows = DB::fetchAll("SELECT concern_id, module_id FROM concern_modules WHERE enabled = 1");
        foreach ($cmRows as $cm) {
            $concernModules[(int)$cm['concern_id']][] = (int)$cm['module_id'];
        }

        // Role permissions map for instant pre-fill upon role selection
        $allRolePerms = [];
        $rpRows = DB::fetchAll("SELECT role_id, module_id, can_view, can_add, can_edit, can_delete FROM role_permissions");
        foreach ($rpRows as $rp) {
            $allRolePerms[(int)$rp['role_id']][(int)$rp['module_id']] = [
                'view'   => (int)$rp['can_view'],
                'add'    => (int)$rp['can_add'],
                'edit'   => (int)$rp['can_edit'],
                'delete' => (int)$rp['can_delete'],
            ];
        }

        $oldInput = $_SESSION['_old_input'] ?? [];
        unset($_SESSION['_old_input']);

        $this->render('concern/staff_create', [
            'pageTitle'        => 'Add New Staff Member',
            'currentModule'    => 'staff',
            'currentSubModule' => 'create',
            'allConcerns'      => $allConcerns,
            'selectedCid'      => $selectedCid,
            'activeConcern'    => $activeConcern,
            'roles'            => $roles,
            'modules'          => $modules,
            'concernModules'   => $concernModules,
            'allRolePerms'     => $allRolePerms,
            'oldInput'         => $oldInput,
        ]);
    }

    public function store(): void {
        ModuleGuard::enforce('staff', 'create');
        $this->validateCsrf();
        $concernIds = array_map('intval', (array)($_POST['concern_ids'] ?? []));
        $cid = (int)($_POST['concern_id'] ?? 0);
        if (!$cid && !empty($concernIds)) {
            $cid = $concernIds[0];
        }
        if (!$cid) {
            $cid = Auth::concernId() ?: (int)($_SESSION['staff_selected_concern_id'] ?? 0);
        }

        if (empty($concernIds) && !$cid) {
            $_SESSION['_old_input'] = $_POST;
            $this->flash('error', 'Please select at least one business concern for this staff member.');
            $this->redirect('/m/staff/create');
        }

        // Verify concern exists and is active
        $concern = DB::fetch("SELECT id, name FROM concerns WHERE id = ? AND status = 'active'", [$cid]);
        if (!$concern) {
            $this->flash('error', 'Invalid or inactive business concern selected.');
            $this->redirect('/m/staff?choose=1');
        }

        $name        = trim($_POST['name'] ?? '');
        $username    = trim($_POST['username'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $password    = trim($_POST['password'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $roleNameInput = trim($_POST['role_name'] ?? '');
        $status      = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $department  = trim($_POST['department'] ?? 'General');
        $salaryType  = trim($_POST['salary_type'] ?? 'monthly');
        $baseSalary  = (float)($_POST['base_salary'] ?? 0);
        $joiningDate = !empty($_POST['joining_date']) ? $_POST['joining_date'] : null;
        $address     = trim($_POST['address'] ?? '');
        $selectedRole = !empty($_POST['role_id']) ? (int)$_POST['role_id'] : null;

        // Resolve role from editable role input or role_id
        if (!$selectedRole && $roleNameInput !== '') {
            $matchedRole = DB::fetch(
                "SELECT id, name FROM roles WHERE LOWER(name) = LOWER(?) AND (concern_id = ? OR concern_id IS NULL) LIMIT 1",
                [$roleNameInput, $cid]
            );
            if ($matchedRole) {
                $selectedRole = (int)$matchedRole['id'];
            } else {
                // Create custom role for this concern
                try {
                    DB::query("INSERT INTO roles (concern_id, name, description, created_at) VALUES (?, ?, ?, NOW())", [
                        $cid,
                        $roleNameInput,
                        'Custom role created from staff registration'
                    ]);
                    $selectedRole = (int)DB::lastInsertId();
                    
                    // Provision default view rights for active concern modules
                    $stmtRolePerm = DB::pdo()->prepare(
                        "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete) VALUES (?, ?, 1, 0, 0, 0)"
                    );
                    $availMods = DB::fetchAll("SELECT id FROM modules WHERE is_super_admin_only = 0 AND code != 'cash'");
                    foreach ($availMods as $am) {
                        $stmtRolePerm->execute([$selectedRole, (int)$am['id']]);
                    }
                } catch (\Throwable $e) {
                    error_log('Error creating role on the fly: ' . $e->getMessage());
                }
            }
        }

        // If designation is empty but role is selected or typed, populate designation
        if ($designation === '') {
            if ($roleNameInput !== '') {
                $designation = $roleNameInput;
            } elseif ($selectedRole) {
                $designation = (string)DB::fetchValue("SELECT name FROM roles WHERE id = ?", [$selectedRole]);
            }
        }

        // If email is empty but username is given, generate a consistent internal login email
        if ($email === '' && $username !== '') {
            $email = preg_replace('/[^a-zA-Z0-9._-]/', '', strtolower($username)) . '@invento.com';
        }

        // Server-side validation
        if (mb_strlen($name) < 2) {
            $_SESSION['_old_input'] = $_POST;
            $this->flash('error', 'Staff member full name is required (minimum 2 characters).');
            $this->redirect('/m/staff/create?concern_id=' . $cid);
        }

        $existingStaffId = null;
        $existingUserId = null;
        if ($username !== '') {
            $userByUsername = DB::fetch("SELECT id, concern_id FROM users WHERE name = ?", [$username]);
            if ($userByUsername) {
                if ((int)$userByUsername['concern_id'] === $cid) {
                    $existingUserId = (int)$userByUsername['id'];
                } else {
                    $_SESSION['_old_input'] = $_POST;
                    $this->flash('error', "The username '{$username}' is already in use by another user.");
                    $this->redirect('/m/staff/create?concern_id=' . $cid);
                }
            }
        }
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['_old_input'] = $_POST;
                $this->flash('error', 'Please provide a valid email address.');
                $this->redirect('/m/staff/create?concern_id=' . $cid);
            }
            $dupStaff = DB::fetch("SELECT id, concern_id, user_id FROM staff WHERE email = ?", [$email]);
            if ($dupStaff) {
                if ((int)$dupStaff['concern_id'] === $cid) {
                    // This staff member already exists in THIS concern; update them seamlessly
                    $existingStaffId = (int)$dupStaff['id'];
                    if (!empty($dupStaff['user_id'])) {
                        $existingUserId = (int)$dupStaff['user_id'];
                    }
                } else {
                    $_SESSION['_old_input'] = $_POST;
                    $this->flash('error', "The email '{$email}' is already registered with another account.");
                    $this->redirect('/m/staff/create?concern_id=' . $cid);
                }
            }
            $dupUser = DB::fetch("SELECT id, concern_id FROM users WHERE email = ?", [$email]);
            if ($dupUser) {
                if ((int)$dupUser['concern_id'] === $cid) {
                    $existingUserId = (int)$dupUser['id'];
                } else {
                    $_SESSION['_old_input'] = $_POST;
                    $this->flash('error', "The email '{$email}' is already registered with another account.");
                    $this->redirect('/m/staff/create?concern_id=' . $cid);
                }
            }
        }

        if ($phone !== '') {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 15) {
                $_SESSION['_old_input'] = $_POST;
                $this->flash('error', 'Phone / mobile number must be 10 digits.');
                $this->redirect('/m/staff/create?concern_id=' . $cid);
            }
            $dupPhone = DB::fetch("SELECT id FROM staff WHERE phone = ? AND concern_id = ?", [$phone, $cid]);
            if ($dupPhone && (!$existingStaffId || (int)$dupPhone['id'] !== $existingStaffId)) {
                $_SESSION['_old_input'] = $_POST;
                $this->flash('error', "A staff member with phone number '{$phone}' already exists in this concern.");
                $this->redirect('/m/staff/create?concern_id=' . $cid);
            }
        }

        if ($password !== '' && strlen($password) < 6) {
            $_SESSION['_old_input'] = $_POST;
            $this->flash('error', 'Password must be at least 6 characters long.');
            $this->redirect('/m/staff/create?concern_id=' . $cid);
        }

        DB::beginTransaction();
        try {
            $passwordHash = $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : null;
            $staffCode = trim($_POST['staff_code'] ?? '');
            if (!$staffCode) {
                $staffCode = NumberSeries::getNext($cid, 'STAFF');
            }

            $userId = null;
            $concernIds = array_map('intval', (array)($_POST['concern_ids'] ?? []));
            if (!in_array($cid, $concernIds, true)) {
                $concernIds[] = $cid;
            }

            // If user already exists in this concern without staff link (e.g. concern admin), update and reuse
            if ($existingUserId) {
                $userId = $existingUserId;
                $roleId = $selectedRole ?: (int)DB::fetchValue("SELECT role_id FROM users WHERE id = ?", [$userId]);
                if (!$roleId) {
                    $roleId = (int)DB::fetchValue("SELECT id FROM roles WHERE concern_id = ? LIMIT 1", [$cid]);
                }
                if (!$roleId) {
                    $roleId = (int)DB::fetchValue("SELECT id FROM roles WHERE id != 1 LIMIT 1") ?: 3;
                }
                $uSql = "UPDATE users SET name = ?, concern_id = ?, phone = ?, status = ?, role_id = ?";
                $userNameToSave = $username !== '' ? $username : $name;
                $uParams = [$userNameToSave, $cid, $phone, $status, $roleId];
                if ($passwordHash) {
                    $uSql .= ", password_hash = ?";
                    $uParams[] = $passwordHash;
                }
                $uSql .= " WHERE id = ?";
                $uParams[] = $userId;
                DB::query($uSql, $uParams);

                $stmtUc = DB::pdo()->prepare("INSERT IGNORE INTO user_concerns (user_id, concern_id) VALUES (?, ?)");
                foreach ($concernIds as $authCid) {
                    if ($authCid > 0) {
                        $stmtUc->execute([$userId, $authCid]);
                    }
                }

                // Save module-level CRUD permissions (View, Create, Edit, Delete) for this staff account
                if (!empty($_POST['perms'])) {
                    $this->persistUserPermissions($userId, (array)$_POST['perms'], $concernIds);
                }
            } elseif ($email !== '' && ($passwordHash || $selectedRole)) {
                $passHash = $passwordHash ?: password_hash('password123', PASSWORD_BCRYPT);
                $roleId = $selectedRole ?: (int)DB::fetchValue("SELECT id FROM roles WHERE concern_id = ? LIMIT 1", [$cid]);
                if (!$roleId) {
                    $roleId = (int)DB::fetchValue("SELECT id FROM roles WHERE id != 1 LIMIT 1") ?: 3;
                }

                $userNameToSave = $username !== '' ? $username : $name;
                DB::query(
                    "INSERT INTO users (concern_id, role_id, name, email, password_hash, phone, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$cid, $roleId, $userNameToSave, $email, $passHash, $phone, $status]
                );
                $userId = (int)DB::lastInsertId();

                $stmtUc = DB::pdo()->prepare("INSERT IGNORE INTO user_concerns (user_id, concern_id) VALUES (?, ?)");
                foreach ($concernIds as $authCid) {
                    if ($authCid > 0) {
                        $stmtUc->execute([$userId, $authCid]);
                    }
                }

                // Save module-level CRUD permissions (View, Create, Edit, Delete) for this staff account
                if (!empty($_POST['perms'])) {
                    $this->persistUserPermissions($userId, (array)$_POST['perms'], $concernIds);
                }
            }

            if ($existingStaffId) {
                $sSql = "UPDATE staff SET concern_id = ?, user_id = ?, name = ?, email = ?, phone = ?, designation = ?, department = ?, salary_type = ?, base_salary = ?, address = ?, status = ?, joining_date = ?";
                $sParams = [$cid, $userId, $name, $email, $phone, $designation, $department, $salaryType, $baseSalary, $address, $status, $joiningDate];
                if ($passwordHash) {
                    $sSql .= ", password_hash = ?";
                    $sParams[] = $passwordHash;
                }
                if ($staffCode) {
                    $sSql .= ", staff_code = ?";
                    $sParams[] = $staffCode;
                }
                $sSql .= " WHERE id = ?";
                $sParams[] = $existingStaffId;
                DB::query($sSql, $sParams);
                $staffId = $existingStaffId;

                AuditLog::record(
                    $cid,
                    'staff',
                    $staffId,
                    'STAFF_UPDATE',
                    null,
                    ['name' => $name, 'staff_code' => $staffCode, 'email' => $email, 'phone' => $phone, 'status' => $status, 'user_id' => $userId],
                    "Updated staff member {$name} [{$staffCode}]"
                );

                DB::commit();
                unset($_SESSION['_old_input']);
                $this->flash('success', "Staff member '{$name}' [{$staffCode}] updated successfully.");
                $this->redirect('/m/staff?concern_id=' . $cid);
            } else {
                DB::query(
                    "INSERT INTO staff (concern_id, user_id, staff_code, name, email, password_hash, phone, designation, department, salary_type, base_salary, address, status, joining_date, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$cid, $userId, $staffCode, $name, $email, $passwordHash, $phone, $designation, $department, $salaryType, $baseSalary, $address, $status, $joiningDate, Auth::id()]
                );
                $staffId = (int)DB::lastInsertId();

                AuditLog::record(
                    $cid,
                    'staff',
                    $staffId,
                    'STAFF_CREATE',
                    null,
                    ['name' => $name, 'staff_code' => $staffCode, 'email' => $email, 'phone' => $phone, 'status' => $status, 'user_id' => $userId],
                    "Registered staff member {$name} [{$staffCode}]"
                );

                DB::commit();
                unset($_SESSION['_old_input']);
                $this->flash('success', "Staff member '{$name}' [{$staffCode}] registered successfully.");
                $this->redirect('/m/staff?concern_id=' . $cid);
            }
        } catch (Exception $e) {
            DB::rollBack();
            $_SESSION['_old_input'] = $_POST;
            $this->flash('error', 'Failed to register staff: ' . $e->getMessage());
            $this->redirect('/m/staff/create?concern_id=' . $cid);
        }
    }

    public function update(): void {
        ModuleGuard::enforce('staff', 'edit');
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $staff = DB::fetch("SELECT * FROM staff WHERE id = ?", [$id]);
        if (!$staff) {
            $this->flash('error', 'Staff record not found.');
            $this->redirect('/m/staff');
        }

        $cid         = (int)($_POST['concern_id'] ?? $staff['concern_id']);
        $name        = trim($_POST['name'] ?? $staff['name']);
        $email       = trim($_POST['email'] ?? ($staff['email'] ?? ''));
        $password    = trim($_POST['password'] ?? '');
        $phone       = trim($_POST['phone'] ?? ($staff['phone'] ?? ''));
        $designation = trim($_POST['designation'] ?? ($staff['designation'] ?? ''));
        $status      = ($_POST['status'] ?? $staff['status']) === 'inactive' ? 'inactive' : 'active';
        $department  = trim($_POST['department'] ?? ($staff['department'] ?? 'General'));
        $salaryType  = trim($_POST['salary_type'] ?? ($staff['salary_type'] ?? 'monthly'));
        $baseSalary  = (float)($_POST['base_salary'] ?? $staff['base_salary']);
        $joiningDate = !empty($_POST['joining_date']) ? $_POST['joining_date'] : $staff['joining_date'];
        $address     = trim($_POST['address'] ?? ($staff['address'] ?? ''));
        $roleId      = !empty($_POST['role_id']) ? (int)$_POST['role_id'] : null;

        // Server-side validation
        if (mb_strlen($name) < 2) {
            $this->flash('error', 'Staff member full name is required (minimum 2 characters).');
            $this->redirect('/m/staff?concern_id=' . $cid);
        }

        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->flash('error', 'Please provide a valid email address.');
                $this->redirect('/m/staff?concern_id=' . $cid);
            }
            $existingUser = DB::fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, (int)($staff['user_id'] ?? 0)]);
            $existingStaff = DB::fetch("SELECT id FROM staff WHERE email = ? AND id != ?", [$email, $id]);
            if ($existingUser || $existingStaff) {
                $this->flash('error', "The email '{$email}' is already in use by another account.");
                $this->redirect('/m/staff?concern_id=' . $cid);
            }
        }

        if ($phone !== '') {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 15) {
                $this->flash('error', 'Phone / mobile number must be 10 digits.');
                $this->redirect('/m/staff?concern_id=' . $cid);
            }
            $dupPhone = DB::fetch("SELECT id FROM staff WHERE phone = ? AND concern_id = ? AND id != ?", [$phone, $cid, $id]);
            if ($dupPhone) {
                $this->flash('error', "A staff member with phone number '{$phone}' already exists in this concern.");
                $this->redirect('/m/staff?concern_id=' . $cid);
            }
        }

        if ($password !== '' && strlen($password) < 6) {
            $this->flash('error', 'Password must be at least 6 characters long.');
            $this->redirect('/m/staff?concern_id=' . $cid);
        }

        DB::beginTransaction();
        try {
            $passwordHash = $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : $staff['password_hash'];

            // Sync with users login table
            $userId = !empty($staff['user_id']) ? (int)$staff['user_id'] : null;
            if ($userId) {
                $uSql = "UPDATE users SET name = ?, email = ?, concern_id = ?, phone = ?, status = ?";
                $uParams = [$name, $email, $cid, $phone, $status];
                if ($password !== '') {
                    $uSql .= ", password_hash = ?";
                    $uParams[] = $passwordHash;
                }
                if ($roleId) {
                    $uSql .= ", role_id = ?";
                    $uParams[] = $roleId;
                }
                $uSql .= " WHERE id = ?";
                $uParams[] = $userId;
                DB::query($uSql, $uParams);
            } elseif ($email !== '' && ($password !== '' || $roleId)) {
                $assignedRole = $roleId ?: (int)DB::fetchValue("SELECT id FROM roles WHERE concern_id = ? LIMIT 1", [$cid]) ?: 3;
                $passHash = $passwordHash ?: password_hash('password123', PASSWORD_BCRYPT);
                DB::query(
                    "INSERT INTO users (concern_id, role_id, name, email, password_hash, phone, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$cid, $assignedRole, $name, $email, $passHash, $phone, $status]
                );
                $userId = (int)DB::lastInsertId();
            }

            DB::query(
                "UPDATE staff SET concern_id = ?, user_id = ?, name = ?, email = ?, password_hash = ?, phone = ?, designation = ?, department = ?, salary_type = ?, base_salary = ?, address = ?, status = ?, joining_date = ?
                 WHERE id = ?",
                [$cid, $userId, $name, $email, $passwordHash, $phone, $designation, $department, $salaryType, $baseSalary, $address, $status, $joiningDate, $id]
            );

            if ($userId) {
                if (isset($_POST['concern_ids'])) {
                    $updCids = array_map('intval', (array)$_POST['concern_ids']);
                    if (!in_array($cid, $updCids, true)) {
                        $updCids[] = $cid;
                    }
                    DB::query("DELETE FROM user_concerns WHERE user_id = ?", [$userId]);
                    $stmtUc = DB::pdo()->prepare("INSERT IGNORE INTO user_concerns (user_id, concern_id) VALUES (?, ?)");
                    foreach ($updCids as $authCid) {
                        if ($authCid > 0) {
                            $stmtUc->execute([$userId, $authCid]);
                        }
                    }
                } elseif ($cid) {
                    DB::query("INSERT IGNORE INTO user_concerns (user_id, concern_id) VALUES (?, ?)", [$userId, $cid]);
                }

                if (isset($_POST['has_perms_matrix']) || isset($_POST['perms'])) {
                    $activePermCids = isset($updCids) ? $updCids : [$cid];
                    $postedPerms = isset($_POST['perms']) && is_array($_POST['perms']) ? $_POST['perms'] : [];
                    $this->persistUserPermissions($userId, $postedPerms, $activePermCids);
                }
            }

            AuditLog::record(
                $cid,
                'staff',
                $id,
                'STAFF_UPDATE',
                $staff,
                ['name' => $name, 'email' => $email, 'phone' => $phone, 'designation' => $designation, 'department' => $department, 'status' => $status, 'user_id' => $userId],
                "Updated staff record {$name}"
            );

            DB::commit();
            $this->flash('success', "Staff member '{$name}' updated successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to update staff: ' . $e->getMessage());
        }

        $this->redirect('/m/staff?concern_id=' . $cid);
    }

    public function toggleStatus(): void {
        ModuleGuard::enforce('staff', 'edit');
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $staff = DB::fetch("SELECT * FROM staff WHERE id = ?", [$id]);
        if (!$staff) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Staff record not found.'], 404);
                return;
            }
            $this->flash('error', 'Staff record not found.');
            $this->redirect('/m/staff');
        }

        $newStatus = $staff['status'] === 'active' ? 'inactive' : 'active';

        DB::beginTransaction();
        try {
            DB::query("UPDATE staff SET status = ? WHERE id = ?", [$newStatus, $id]);

            if (!empty($staff['user_id'])) {
                DB::query("UPDATE users SET status = ? WHERE id = ?", [$newStatus, (int)$staff['user_id']]);
            }

            AuditLog::record(
                (int)$staff['concern_id'],
                'staff',
                $id,
                'STAFF_STATUS_CHANGE',
                ['status' => $staff['status']],
                ['status' => $newStatus],
                "Staff member '{$staff['name']}' status changed from {$staff['status']} to {$newStatus}"
            );

            DB::commit();

            $msg = "Staff member '{$staff['name']}' has been " . ($newStatus === 'active' ? 'activated' : 'deactivated') . ".";
            if ($this->isAjax()) {
                $this->json(['success' => true, 'new_status' => $newStatus, 'message' => $msg]);
                return;
            }
            $this->flash('success', $msg);
        } catch (Exception $e) {
            DB::rollBack();
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => $e->getMessage()], 500);
                return;
            }
            $this->flash('error', 'Failed to change staff status: ' . $e->getMessage());
        }

        $this->redirect('/m/staff?concern_id=' . $staff['concern_id']);
    }

    public function delete(): void {
        ModuleGuard::enforce('staff', 'delete');
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $staff = DB::fetch("SELECT * FROM staff WHERE id = ?", [$id]);
        if (!$staff) {
            $this->flash('error', 'Staff record not found.');
            $this->redirect('/m/staff');
        }

        $cid = (int)$staff['concern_id'];
        try {
            DB::query("DELETE FROM staff WHERE id = ?", [$id]);
            $this->flash('success', "Staff record '{$staff['name']}' [{$staff['staff_code']}] deleted successfully.");
        } catch (Exception $e) {
            $this->flash('error', 'Failed to delete staff: ' . $e->getMessage());
        }

        $this->redirect('/m/staff?concern_id=' . $cid);
    }

    public function export(): void {
        ModuleGuard::enforce('staff', 'view');
        $selectedCid = !empty($_GET['concern_id']) ? (int)$_GET['concern_id'] : Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');

        $sql = "SELECT s.*, c.name AS concern_name, c.code AS concern_code
                FROM staff s
                JOIN concerns c ON s.concern_id = c.id";
        $params = [];
        if ($selectedCid) {
            $sql .= " WHERE s.concern_id = ?";
            $params[] = $selectedCid;
        }
        $sql .= " ORDER BY s.id DESC";
        $staffList = DB::fetchAll($sql, $params);

        $headers = [
            'Staff Code', 'Name', 'Department', 'Designation', 'Phone / Mobile', 'Email', 
            'Salary Type', 'Base Salary (INR)', 'Status', 'Joining Date', 'Concern'
        ];

        $rows = [];
        $totSalary = 0;
        foreach ($staffList as $stf) {
            $sal = (float)($stf['base_salary'] ?? 0);
            $totSalary += $sal;
            $rows[] = [
                $stf['staff_code'],
                $stf['name'],
                $stf['department'] ?: 'General',
                $stf['designation'] ?: '-',
                $stf['phone'] ?: '-',
                $stf['email'] ?: '-',
                ucfirst($stf['salary_type'] ?: 'Monthly'),
                number_format($sal, 2),
                strtoupper($stf['status'] ?? 'ACTIVE'),
                $stf['joining_date'] ? date('d M Y', strtotime($stf['joining_date'])) : '-',
                $stf['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total Staff: ' . count($staffList), '', '', '', '', '', 'Total Payroll:', number_format($totSalary, 2), '', '', ''];

        $metadata = [
            'Report Title' => 'Staff Directory Register Report',
            'Generated At' => date('d M Y, h:i A'),
            'Total Staff'  => count($staffList),
            'Concern'      => $selectedCid ? ($staffList[0]['concern_name'] ?? 'Concern #' . $selectedCid) : 'All Concerns'
        ];

        $filename = 'staff_register_' . date('Ymd_His');

        if ($format === 'pdf') {
            ExportService::renderPdfView('Staff Directory Register Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            ExportService::downloadCsv($filename, $headers, $rows);
        } else {
            ExportService::downloadExcel($filename, 'Staff Directory Register Report', $metadata, $headers, $rows, $totals);
        }
    }

    public function roles(): void {
        ModuleGuard::enforce('staff', 'view');
        $allConcerns = DB::fetchAll("SELECT id, name, code, status FROM concerns WHERE status = 'active' ORDER BY name ASC");
        $concernFilter = isset($_GET['concern_id']) && $_GET['concern_id'] !== '' ? (int)$_GET['concern_id'] : null;
        if ($concernFilter) {
            $allRoles = DB::fetchAll(
                "SELECT id, name, description, concern_id FROM roles WHERE concern_id = ? OR concern_id IS NULL ORDER BY (concern_id IS NULL) DESC, id ASC",
                [$concernFilter]
            );
        } else {
            $allRoles = DB::fetchAll("SELECT id, name, description, concern_id FROM roles ORDER BY (concern_id IS NULL) DESC, id ASC");
        }
        $search = trim($_GET['search'] ?? '');

        $sql = "SELECT s.*, c.name AS concern_name, c.code AS concern_code,
                       u.email AS user_email, u.role_id, u.status AS user_status,
                       r.name AS role_name
                FROM staff s
                JOIN concerns c ON s.concern_id = c.id
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE 1=1";
        $params = [];

        if ($concernFilter) {
            $sql .= " AND s.concern_id = ?";
            $params[] = $concernFilter;
        }

        if ($search !== '') {
            $sql .= " AND (s.name LIKE ? OR s.staff_code LIKE ? OR s.phone LIKE ? OR s.email LIKE ? OR s.designation LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY s.id DESC";
        $staffMembers = DB::fetchAll($sql, $params);

        // Map user concerns for users
        $userConcernsMap = [];
        $userIds = array_values(array_filter(array_unique(array_column($staffMembers, 'user_id'))));
        if (!empty($userIds)) {
            $inClause = implode(',', array_map('intval', $userIds));
            $ucRows = DB::fetchAll("SELECT uc.user_id, uc.concern_id, c.name AS concern_name, c.code AS concern_code
                                    FROM user_concerns uc
                                    JOIN concerns c ON uc.concern_id = c.id
                                    WHERE uc.user_id IN ({$inClause})");
            foreach ($ucRows as $row) {
                $userConcernsMap[$row['user_id']][] = [
                    'id'   => (int)$row['concern_id'],
                    'name' => $row['concern_name'],
                    'code' => $row['concern_code']
                ];
            }
        }

        // Operational modules for CRUD permissions matrix
        $modules = DB::fetchAll(
            "SELECT * FROM modules WHERE is_super_admin_only = 0 AND code != 'cash' ORDER BY group_name ASC, sort_order ASC, id ASC"
        );

        $activeTab = ($_GET['tab'] ?? 'staff') === 'matrix' ? 'matrix' : 'staff';
        $selectedRoleId = (int)($_GET['matrix_role_id'] ?? 0);
        if (!$selectedRoleId && !empty($allRoles)) {
            $selectedRoleId = (int)$allRoles[0]['id'];
        }

        $matrixPerms = [];
        if ($selectedRoleId) {
            $rpRows = DB::fetchAll("SELECT * FROM role_permissions WHERE role_id = ?", [$selectedRoleId]);
            foreach ($rpRows as $rp) {
                $matrixPerms[$rp['module_id']] = [
                    'can_view'   => (int)$rp['can_view'],
                    'can_add'    => (int)$rp['can_add'],
                    'can_edit'   => (int)$rp['can_edit'],
                    'can_delete' => (int)$rp['can_delete'],
                ];
            }
        }

        $this->render('concern/staff_roles', [
            'pageTitle'        => 'Staff Role & Access Assignment',
            'currentModule'    => 'staff',
            'currentSubModule' => 'roles',
            'allConcerns'      => $allConcerns,
            'allRoles'         => $allRoles,
            'modules'          => $modules,
            'staffMembers'     => $staffMembers,
            'userConcernsMap'  => $userConcernsMap,
            'concernFilter'    => $concernFilter,
            'search'           => $search,
            'activeTab'        => $activeTab,
            'selectedRoleId'   => $selectedRoleId,
            'matrixPerms'      => $matrixPerms,
        ]);
    }

    public function assignRole(): void {
        $this->validateCsrf();

        $staffId = (int)($_POST['staff_id'] ?? 0);
        $roleId = (int)($_POST['role_id'] ?? 0);
        $concernIds = array_map('intval', (array)($_POST['concern_ids'] ?? []));
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $staff = DB::fetch("SELECT * FROM staff WHERE id = ?", [$staffId]);
        if (!$staff) {
            $this->flash('error', 'Staff record not found.');
            $this->redirect('/m/staff/roles');
        }

        DB::beginTransaction();
        try {
            $userId = !empty($staff['user_id']) ? (int)$staff['user_id'] : null;

            if (!$userId) {
                if (!$email) {
                    $email = $staff['email'] ?: ('staff_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $staff['staff_code'])) . '@invento.com');
                }
                $existingUser = DB::fetch("SELECT id FROM users WHERE email = ?", [$email]);
                if ($existingUser) {
                    $userId = (int)$existingUser['id'];
                    $updateSql = "UPDATE users SET role_id = ?, status = 'active'";
                    $uParams = [$roleId ?: 3];
                    if ($password) {
                        $updateSql .= ", password_hash = ?";
                        $uParams[] = password_hash($password, PASSWORD_BCRYPT);
                    }
                    $updateSql .= " WHERE id = ?";
                    $uParams[] = $userId;
                    DB::query($updateSql, $uParams);
                } else {
                    $pass = $password ?: 'password123';
                    $passwordHash = password_hash($pass, PASSWORD_BCRYPT);
                    DB::query(
                        "INSERT INTO users (concern_id, role_id, name, email, password_hash, phone, status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())",
                        [$staff['concern_id'], $roleId ?: 3, $staff['name'], $email, $passwordHash, $staff['phone']]
                    );
                    $userId = (int)DB::lastInsertId();
                }

                DB::query("UPDATE staff SET user_id = ?, email = ? WHERE id = ?", [$userId, $email, $staffId]);
            } else {
                $updateSql = "UPDATE users SET role_id = ?";
                $uParams = [$roleId];
                if ($email) {
                    $updateSql .= ", email = ?";
                    $uParams[] = $email;
                }
                if ($password) {
                    $updateSql .= ", password_hash = ?";
                    $uParams[] = password_hash($password, PASSWORD_BCRYPT);
                }
                $updateSql .= " WHERE id = ?";
                $uParams[] = $userId;
                DB::query($updateSql, $uParams);

                if ($email) {
                    DB::query("UPDATE staff SET email = ? WHERE id = ?", [$email, $staffId]);
                }
            }

            if ($userId) {
                // If custom permissions submitted for this staff member, save to user_permissions
                if (!empty($_POST['is_custom_perms']) && !empty($_POST['custom_perms'])) {
                    $stmtUp = DB::pdo()->prepare(
                        "INSERT INTO user_permissions (user_id, module_id, can_view, can_add, can_edit, can_delete)
                         VALUES (?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_add = VALUES(can_add), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete)"
                    );
                    $stmtDel = DB::pdo()->prepare("DELETE FROM user_permissions WHERE user_id = ? AND module_id = ?");
                    $allMods = DB::fetchAll("SELECT id FROM modules WHERE is_super_admin_only = 0");
                    $customPerms = (array)$_POST['custom_perms'];

                    foreach ($allMods as $m) {
                        $mid = (int)$m['id'];
                        $v = isset($customPerms[$mid]['view']) ? self::parseOverrideValue($customPerms[$mid]['view']) : null;
                        $a = isset($customPerms[$mid]['add']) ? self::parseOverrideValue($customPerms[$mid]['add']) : null;
                        $e = isset($customPerms[$mid]['edit']) ? self::parseOverrideValue($customPerms[$mid]['edit']) : null;
                        $d = isset($customPerms[$mid]['delete']) ? self::parseOverrideValue($customPerms[$mid]['delete']) : null;

                        if ($v === null && $a === null && $e === null && $d === null) {
                            $stmtDel->execute([$userId, $mid]);
                        } else {
                            $stmtUp->execute([$userId, $mid, $v, $a, $e, $d]);
                        }
                    }
                }

                DB::query("DELETE FROM user_concerns WHERE user_id = ?", [$userId]);

                if (empty($concernIds)) {
                    $concernIds = [(int)$staff['concern_id']];
                }

                $stmt = DB::pdo()->prepare("INSERT IGNORE INTO user_concerns (user_id, concern_id) VALUES (?, ?)");
                foreach ($concernIds as $cid) {
                    if ($cid > 0) {
                        $stmt->execute([$userId, $cid]);
                    }
                }
            }

            AuditLog::record(
                (int)$staff['concern_id'],
                'users',
                $userId,
                'ROLE_ASSIGN',
                null,
                ['staff_id' => $staffId, 'role_id' => $roleId, 'concern_ids' => $concernIds],
                "Assigned role #{$roleId} and location access for staff {$staff['name']}"
            );

            DB::commit();
            $this->flash('success', "Role and concern access updated successfully for '{$staff['name']}'.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to assign role: ' . $e->getMessage());
        }

        $this->redirect('/m/staff/roles');
    }

    public function apiGetUserPermissions(): void {
        $staffId = (int)($_GET['staff_id'] ?? 0);
        $userId = (int)($_GET['user_id'] ?? 0);

        $staff = null;
        if ($staffId) {
            $staff = DB::fetch("SELECT s.*, c.name AS concern_name, c.code AS concern_code FROM staff s JOIN concerns c ON s.concern_id = c.id WHERE s.id = ?", [$staffId]);
            if ($staff && !empty($staff['user_id'])) {
                $userId = (int)$staff['user_id'];
            }
        }

        $user = null;
        if ($userId) {
            $user = DB::fetch("SELECT u.*, r.name AS role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?", [$userId]);
            if (!$staff && $user) {
                $staff = DB::fetch("SELECT * FROM staff WHERE user_id = ?", [$userId]);
            }
        }

        if (!$user) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode([
                'success' => true,
                'has_user_account' => false,
                'staff' => $staff,
                'message' => 'This staff member does not have an active system login account yet. Please assign a role first.'
            ]);
            exit;
        }

        $roleId = (int)$user['role_id'];

        // 1. Role permissions
        $rolePerms = [];
        $rpRows = DB::fetchAll("SELECT module_id, can_view, can_add, can_edit, can_delete FROM role_permissions WHERE role_id = ?", [$roleId]);
        foreach ($rpRows as $rp) {
            $rolePerms[(int)$rp['module_id']] = [
                'view'   => (int)$rp['can_view'],
                'add'    => (int)$rp['can_add'],
                'edit'   => (int)$rp['can_edit'],
                'delete' => (int)$rp['can_delete'],
            ];
        }

        // 2. User specific overrides (null = inherit, 1 = grant, 0 = deny)
        $userOverrides = [];
        $upRows = DB::fetchAll("SELECT module_id, can_view, can_add, can_edit, can_delete FROM user_permissions WHERE user_id = ?", [$userId]);
        foreach ($upRows as $up) {
            $userOverrides[(int)$up['module_id']] = [
                'view'   => $up['can_view'] === null ? null : (int)$up['can_view'],
                'add'    => $up['can_add'] === null ? null : (int)$up['can_add'],
                'edit'   => $up['can_edit'] === null ? null : (int)$up['can_edit'],
                'delete' => $up['can_delete'] === null ? null : (int)$up['can_delete'],
            ];
        }

        // 3. Compute effective permissions
        $modules = DB::fetchAll("SELECT id, name, code, group_name FROM modules WHERE is_super_admin_only = 0 AND code != 'cash' ORDER BY group_name, sort_order, id");
        $effective = [];
        foreach ($modules as $m) {
            $mid = (int)$m['id'];
            $r = $rolePerms[$mid] ?? ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0];
            $u = $userOverrides[$mid] ?? ['view' => null, 'add' => null, 'edit' => null, 'delete' => null];

            $actions = ['view', 'add', 'edit', 'delete'];
            $eff = [];
            foreach ($actions as $act) {
                if (isset($u[$act]) && $u[$act] !== null) {
                    $eff[$act] = [
                        'allowed' => (bool)$u[$act],
                        'source'  => $u[$act] == 1 ? 'granted_override' : 'denied_override',
                    ];
                } else {
                    $eff[$act] = [
                        'allowed' => !empty($r[$act]),
                        'source'  => 'inherited_role',
                    ];
                }
            }
            $effective[$mid] = $eff;
        }

        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success'          => true,
            'has_user_account' => true,
            'staff'            => $staff,
            'user'             => [
                'id'        => (int)$user['id'],
                'name'      => $user['name'],
                'email'     => $user['email'],
                'role_id'   => $roleId,
                'role_name' => $user['role_name'],
                'status'    => $user['status'],
            ],
            'modules'          => $modules,
            'role_permissions' => $rolePerms,
            'user_overrides'   => $userOverrides,
            'effective'        => $effective,
        ]);
        exit;
    }

    public function saveUserPermissions(): void {
        $this->validateCsrf();

        $userId = (int)($_POST['user_id'] ?? 0);
        $staffId = (int)($_POST['staff_id'] ?? 0);

        if (!$userId && $staffId) {
            $userId = (int)DB::fetchValue("SELECT user_id FROM staff WHERE id = ?", [$staffId]);
        }

        if (!$userId) {
            if ($this->isAjax()) {
                if (!headers_sent()) header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'A valid user login account is required to assign custom permissions.']);
                exit;
            }
            $this->flash('error', 'A valid user login account is required to assign custom permissions.');
            $this->redirect('/m/staff/roles');
        }

        $overrides = (array)($_POST['overrides'] ?? []);

        DB::beginTransaction();
        try {
            $stmtInsert = DB::pdo()->prepare(
                "INSERT INTO user_permissions (user_id, module_id, can_view, can_add, can_edit, can_delete)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_add = VALUES(can_add), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete)"
            );
            $stmtDelete = DB::pdo()->prepare("DELETE FROM user_permissions WHERE user_id = ? AND module_id = ?");

            $modules = DB::fetchAll("SELECT id FROM modules WHERE is_super_admin_only = 0");
            $savedOverrides = [];

            foreach ($modules as $m) {
                $mid = (int)$m['id'];
                $modOv = $overrides[$mid] ?? [];

                $v = self::parseOverrideValue($modOv['view'] ?? null);
                $a = self::parseOverrideValue($modOv['add'] ?? null);
                $e = self::parseOverrideValue($modOv['edit'] ?? null);
                $d = self::parseOverrideValue($modOv['delete'] ?? null);

                if ($v === null && $a === null && $e === null && $d === null) {
                    $stmtDelete->execute([$userId, $mid]);
                } else {
                    $stmtInsert->execute([$userId, $mid, $v, $a, $e, $d]);
                    $savedOverrides[$mid] = ['view' => $v, 'add' => $a, 'edit' => $e, 'delete' => $d];
                }
            }

            AuditLog::record(
                Auth::concernId(),
                'user_permissions',
                $userId,
                'PERMISSIONS_UPDATE',
                null,
                $savedOverrides,
                "Updated individual permission overrides for user #{$userId}"
            );

            DB::commit();

            // If the current user's own permissions were modified, refresh session
            if ($userId === Auth::id()) {
                $roleId = (int)($_SESSION['user']['role_id'] ?? 0);
                Auth::loadPermissions($userId, $roleId);
            }

            $msg = "Individual permissions updated successfully.";
            if ($this->isAjax()) {
                if (!headers_sent()) header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $msg]);
                exit;
            }
            $this->flash('success', $msg);
        } catch (Exception $e) {
            DB::rollBack();
            if ($this->isAjax()) {
                if (!headers_sent()) header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $this->flash('error', 'Failed to save individual permissions: ' . $e->getMessage());
        }

        $this->redirect('/m/staff/roles');
    }

    public static function parseOverrideValue($val): ?int {
        if ($val === null || $val === '' || $val === 'inherit') {
            return null;
        }
        if ($val === 1 || $val === '1' || $val === 'grant') {
            return 1;
        }
        if ($val === 0 || $val === '0' || $val === 'deny') {
            return 0;
        }
        return null;
    }

    private function isAjax(): bool {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
    }

    public function apiGetRolePermissions(): void {
        $roleId = (int)($_GET['role_id'] ?? 0);
        $perms = [];
        if ($roleId) {
            $rows = DB::fetchAll("SELECT module_id, can_view, can_add, can_edit, can_delete FROM role_permissions WHERE role_id = ?", [$roleId]);
            foreach ($rows as $r) {
                $perms[(int)$r['module_id']] = [
                    'view'   => (int)$r['can_view'],
                    'add'    => (int)$r['can_add'],
                    'edit'   => (int)$r['can_edit'],
                    'delete' => (int)$r['can_delete'],
                ];
            }
        }
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => true, 'role_id' => $roleId, 'permissions' => $perms]);
        exit;
    }

    public function saveRolePermissions(): void {
        ModuleGuard::enforce('staff', 'edit');
        $this->validateCsrf();
        $roleId = (int)($_POST['role_id'] ?? 0);
        $permissions = $_POST['perms'] ?? [];

        if (!$roleId) {
            $this->flash('error', 'Please select a valid role to configure.');
            $this->redirect('/m/staff/roles?tab=matrix');
        }

        DB::beginTransaction();
        try {
            $modules = DB::fetchAll("SELECT id FROM modules WHERE is_super_admin_only = 0");
            $stmt = DB::pdo()->prepare(
                "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_add = VALUES(can_add), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete)"
            );

            foreach ($modules as $m) {
                $mid = (int)$m['id'];
                $canView = !empty($permissions[$mid]['view']) ? 1 : 0;
                $canAdd = !empty($permissions[$mid]['add']) ? 1 : 0;
                $canEdit = !empty($permissions[$mid]['edit']) ? 1 : 0;
                $canDelete = !empty($permissions[$mid]['delete']) ? 1 : 0;

                $stmt->execute([$roleId, $mid, $canView, $canAdd, $canEdit, $canDelete]);
            }

            $roleName = DB::fetchValue("SELECT name FROM roles WHERE id = ?", [$roleId]) ?: "Role #{$roleId}";

            AuditLog::record(
                Auth::concernId(),
                'role_permissions',
                $roleId,
                'ROLE_PERMISSIONS_UPDATE',
                null,
                ['role_id' => $roleId, 'permissions' => $permissions],
                "Updated CRUD permissions for role {$roleName}"
            );

            DB::commit();

            // If the current user has this role, refresh session
            if ((int)($_SESSION['user']['role_id'] ?? 0) === $roleId) {
                Auth::loadPermissions((int)Auth::id(), $roleId);
            }

            $this->flash('success', "CRUD permissions updated successfully for '{$roleName}'.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to update permissions: ' . $e->getMessage());
        }

        $this->redirect('/m/staff/roles?tab=matrix&matrix_role_id=' . $roleId);
    }

    public function createRoleView(): void {
        ModuleGuard::enforce('staff', 'create');
        $this->ensureSchema();
        $allConcerns = DB::fetchAll("SELECT id, name, code, status FROM concerns WHERE status = 'active' ORDER BY name ASC");
        $modules = DB::fetchAll("SELECT * FROM modules WHERE is_super_admin_only = 0 AND code != 'cash' ORDER BY group_name ASC, sort_order ASC, id ASC");
        
        $oldInput = $_SESSION['_old_input'] ?? [];
        unset($_SESSION['_old_input']);

        $this->render('concern/role_create', [
            'pageTitle'        => 'Add New Role',
            'currentModule'    => 'staff',
            'currentSubModule' => 'roles',
            'allConcerns'      => $allConcerns,
            'modules'          => $modules,
            'oldInput'         => $oldInput,
        ]);
    }

    public function storeRole(): void {
        ModuleGuard::enforce('staff', 'create');
        $this->validateCsrf();
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $concernId = Auth::concernId() ?: (!empty($_POST['concern_id']) ? (int)$_POST['concern_id'] : null);
        $perms = (array)($_POST['perms'] ?? []);

        if (!$name) {
            $_SESSION['_old_input'] = $_POST;
            $this->flash('error', 'Role name is required.');
            $this->redirect('/m/staff/roles/create');
        }

        DB::beginTransaction();
        try {
            DB::query(
                "INSERT INTO roles (concern_id, name, description, created_at) VALUES (?, ?, ?, NOW())",
                [$concernId, $name, $description]
            );
            $newRoleId = (int)DB::lastInsertId();

            $modules = DB::fetchAll("SELECT id FROM modules WHERE is_super_admin_only = 0 AND code != 'cash'");
            $stmt = DB::pdo()->prepare(
                "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)"
            );

            foreach ($modules as $m) {
                $mid = (int)$m['id'];
                $canView = !empty($perms[$mid]['view']) ? 1 : 0;
                $canAdd = !empty($perms[$mid]['add']) ? 1 : 0;
                $canEdit = !empty($perms[$mid]['edit']) ? 1 : 0;
                $canDelete = !empty($perms[$mid]['delete']) ? 1 : 0;
                $stmt->execute([$newRoleId, $mid, $canView, $canAdd, $canEdit, $canDelete]);
            }

            AuditLog::record(
                $concernId,
                'roles',
                $newRoleId,
                'ROLE_CREATE',
                null,
                ['name' => $name, 'concern_id' => $concernId],
                "Created role {$name}"
            );

            DB::commit();

            $this->flash('success', "Role '{$name}' created and permissions provisioned successfully.");
            $this->redirect('/m/staff/roles?tab=matrix&matrix_role_id=' . $newRoleId);
        } catch (\Throwable $e) {
            DB::rollBack();
            error_log('StaffController storeRole error: ' . $e->getMessage());
            $_SESSION['_old_input'] = $_POST;
            $this->flash('error', 'Failed to create role: ' . $e->getMessage());
            $this->redirect('/m/staff/roles/create');
        }
    }

    public function createRole(): void {
        ModuleGuard::enforce('staff', 'create');
        $this->validateCsrf();
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $concernId = !empty($_POST['concern_id']) ? (int)$_POST['concern_id'] : null;

        if (!$name) {
            $this->flash('error', 'Role name is required.');
            $this->redirect('/m/staff/roles?tab=matrix');
        }

        DB::beginTransaction();
        try {
            DB::query(
                "INSERT INTO roles (concern_id, name, description, created_at) VALUES (?, ?, ?, NOW())",
                [$concernId, $name, $description]
            );
            $newRoleId = (int)DB::lastInsertId();

            // Default all modules to view=1, add=0, edit=0, delete=0
            $modules = DB::fetchAll("SELECT id FROM modules WHERE is_super_admin_only = 0");
            $stmt = DB::pdo()->prepare(
                "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete) VALUES (?, ?, 1, 0, 0, 0)"
            );
            foreach ($modules as $m) {
                $stmt->execute([$newRoleId, (int)$m['id']]);
            }

            AuditLog::record(
                $concernId,
                'roles',
                $newRoleId,
                'ROLE_CREATE',
                null,
                ['name' => $name, 'concern_id' => $concernId],
                "Created role {$name}"
            );

            DB::commit();

            $this->flash('success', "Role '{$name}' created successfully. Now configure its CRUD permissions below.");
            $this->redirect('/m/staff/roles?tab=matrix&matrix_role_id=' . $newRoleId);
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to create role: ' . $e->getMessage());
            $this->redirect('/m/staff/roles?tab=matrix');
        }
    }

    /**
     * Persist module-level CRUD permissions for specific authorized concerns
     */
    private function persistUserPermissions(int $userId, array $postedPerms, array $concernIds): void {
        if ($userId <= 0) {
            return;
        }

        $stmtUp = DB::pdo()->prepare(
            "INSERT INTO user_permissions (user_id, concern_id, module_id, can_view, can_add, can_edit, can_delete)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_add = VALUES(can_add), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete)"
        );

        $allMods = DB::fetchAll("SELECT id, code FROM modules WHERE is_super_admin_only = 0");
        $cashModId = null;
        $cashFlowModId = null;
        $dashModId = null;
        foreach ($allMods as $m) {
            if ($m['code'] === 'cash') $cashModId = (int)$m['id'];
            if ($m['code'] === 'cash_flow') $cashFlowModId = (int)$m['id'];
            if ($m['code'] === 'concern_dashboard') $dashModId = (int)$m['id'];
        }

        // Clean existing permissions for this user to avoid stale permissions
        DB::query("DELETE FROM user_permissions WHERE user_id = ?", [$userId]);

        foreach ($concernIds as $authCid) {
            if ($authCid <= 0) continue;

            $concernPerms = [];
            if (isset($postedPerms[$authCid]) && is_array($postedPerms[$authCid])) {
                $concernPerms = $postedPerms[$authCid];
            } elseif (count($concernIds) === 1 && isset($postedPerms[array_key_first($postedPerms)])) {
                $concernPerms = $postedPerms;
            } elseif (!empty($postedPerms)) {
                $concernPerms = $postedPerms;
            }

            if ($cashModId && $cashFlowModId && isset($concernPerms[$cashFlowModId])) {
                $concernPerms[$cashModId] = $concernPerms[$cashFlowModId];
            }

            // Get modules enabled for this concern
            $cmRows = DB::fetchAll("SELECT module_id FROM concern_modules WHERE concern_id = ? AND enabled = 1", [$authCid]);
            $enabledMids = array_map('intval', array_column($cmRows, 'module_id'));
            if (empty($enabledMids)) {
                $enabledMids = array_map('intval', array_column($allMods, 'id'));
            }

            // Compute whether user has any operational add/edit access
            $hasAnyAdd = false;
            $hasAnyEdit = false;
            foreach ($enabledMids as $mid) {
                if ($dashModId && $mid === $dashModId) continue;
                if (!empty($concernPerms[$mid]['add'])) $hasAnyAdd = true;
                if (!empty($concernPerms[$mid]['edit'])) $hasAnyEdit = true;
            }

            foreach ($enabledMids as $mid) {
                // If this is the concern_dashboard base module, dynamically align its add/edit with operational permissions
                if ($dashModId && $mid === $dashModId) {
                    $v = 1;
                    $a = $hasAnyAdd ? 1 : 0;
                    $e = $hasAnyEdit ? 1 : 0;
                    $d = 0;
                } else {
                    $v = !empty($concernPerms[$mid]['view']) ? 1 : 0;
                    $a = !empty($concernPerms[$mid]['add']) ? 1 : 0;
                    $e = !empty($concernPerms[$mid]['edit']) ? 1 : 0;
                    $d = !empty($concernPerms[$mid]['delete']) ? 1 : 0;
                }
                $stmtUp->execute([$userId, $authCid, $mid, $v, $a, $e, $d]);
            }
        }
    }
}
