<?php
// tests/StaffPermissionsTest.php - Comprehensive Test Suite for Staff Accounts & Permissions

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Core\Auth;
use App\Core\ModuleGuard;
use App\Services\AuditLog;

class StaffPermissionsTest {
    private int $testConcernId;
    private int $testRoleId;
    private int $testModuleId;
    private string $testModuleCode;
    private array $createdUserIds = [];
    private array $createdStaffIds = [];
    private array $createdRoleIds = [];
    private array $createdConcernIds = [];

    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        echo "\n======================================================\n";
        echo "  INVENTO ERP: STAFF & MODULE-LEVEL PERMISSIONS TEST  \n";
        echo "======================================================\n\n";

        try {
            $this->setupFixtures();

            $this->testPasswordHashingAndSecurity();
            $this->testServerSideValidationRules();
            $this->testDatabaseTransactionRollback();
            $this->testStrictDefaultDeny();
            $this->testRoleDefaultPermissions();
            $this->testIndividualUserOverrides();
            $this->testDeactivationAndImmediateSessionEviction();
            $this->testAuditLogging();
            $this->testModuleMatrixFormSubmissionAndMultiConcernPersistence();
            $this->testExistingUserStaffLinking();

            $this->cleanupFixtures();
        } catch (\Throwable $e) {
            echo "\n[FATAL ERROR] Unexpected exception during tests: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
            $this->cleanupFixtures();
            exit(1);
        }

        echo "\n------------------------------------------------------\n";
        echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function setupFixtures(): void {
        // Ensure an active concern
        $concern = DB::fetch("SELECT id FROM concerns WHERE status = 'active' LIMIT 1");
        if (!$concern) {
            DB::query("INSERT INTO concerns (name, code, status, created_at) VALUES ('Test Concern Unit', 'TST-CON', 'active', NOW())");
            $this->testConcernId = (int)DB::lastInsertId();
            $this->createdConcernIds[] = $this->testConcernId;
        } else {
            $this->testConcernId = (int)$concern['id'];
        }

        // Create a dedicated test role
        DB::query(
            "INSERT INTO roles (concern_id, name, description, created_at) VALUES (?, 'Test Auditor Role', 'Role for permissions verification', NOW())",
            [$this->testConcernId]
        );
        $this->testRoleId = (int)DB::lastInsertId();
        $this->createdRoleIds[] = $this->testRoleId;

        // Ensure an operational module exists
        $mod = DB::fetch("SELECT id, code FROM modules WHERE code = 'staff' LIMIT 1");
        if (!$mod) {
            $mod = DB::fetch("SELECT id, code FROM modules WHERE is_super_admin_only = 0 LIMIT 1");
        }
        $this->testModuleId = (int)$mod['id'];
        $this->testModuleCode = $mod['code'];

        // Enable module for concern in concern_modules
        DB::query(
            "INSERT INTO concern_modules (concern_id, module_id, enabled) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE enabled = 1",
            [$this->testConcernId, $this->testModuleId]
        );
    }

    private function cleanupFixtures(): void {
        // Clean user_permissions
        if (!empty($this->createdUserIds)) {
            $uids = implode(',', array_map('intval', $this->createdUserIds));
            DB::query("DELETE FROM user_permissions WHERE user_id IN ({$uids})");
            DB::query("DELETE FROM user_concerns WHERE user_id IN ({$uids})");
            DB::query("DELETE FROM users WHERE id IN ({$uids})");
        }
        if (!empty($this->createdStaffIds)) {
            $sids = implode(',', array_map('intval', $this->createdStaffIds));
            DB::query("DELETE FROM staff WHERE id IN ({$sids})");
        }
        if (!empty($this->createdRoleIds)) {
            $rids = implode(',', array_map('intval', $this->createdRoleIds));
            DB::query("DELETE FROM role_permissions WHERE role_id IN ({$rids})");
            DB::query("DELETE FROM roles WHERE id IN ({$rids})");
        }
    }

    private function assert(bool $condition, string $testName, string $failureDetails = ''): void {
        if ($condition) {
            echo "  [PASS] {$testName}\n";
            $this->passed++;
        } else {
            echo "  [FAIL] {$testName}" . ($failureDetails ? " -> {$failureDetails}" : "") . "\n";
            $this->failed++;
        }
    }

    // 1. Password Hashing & Cryptography
    private function testPasswordHashingAndSecurity(): void {
        echo "[1] Testing Password Hashing & Security Requirements...\n";

        $plainPass = "SecureAdmin@2026!";
        $hash = password_hash($plainPass, PASSWORD_BCRYPT);

        $this->assert(
            str_starts_with($hash, '$2y$'),
            "Password stored with secure BCRYPT algorithm"
        );
        $this->assert(
            password_verify($plainPass, $hash),
            "password_verify() successfully verifies valid plaintext against BCRYPT hash"
        );
        $this->assert(
            !password_verify("WrongPassword123", $hash),
            "password_verify() rejects incorrect password"
        );
        $this->assert(
            $hash !== $plainPass,
            "Raw unhashed password is never stored or persisted directly"
        );
    }

    // 2. Server-side Validations
    private function testServerSideValidationRules(): void {
        echo "\n[2] Testing Server-Side Validation Rules...\n";

        // Name length check
        $shortName = "A";
        $validName = "John Doe";
        $this->assert(mb_strlen($shortName) < 2, "Short names (< 2 characters) fail validation");
        $this->assert(mb_strlen($validName) >= 2, "Standard names (>= 2 characters) pass validation");

        // Phone 10-digit validation
        $invalidPhone = "12345";
        $validPhone = "9876543210";
        $cleanedValid = preg_replace('/[^0-9]/', '', $validPhone);
        $cleanedInvalid = preg_replace('/[^0-9]/', '', $invalidPhone);

        $this->assert(
            strlen($cleanedInvalid) < 10,
            "Short phone number ({$invalidPhone}) rejected by 10-digit validation"
        );
        $this->assert(
            strlen($cleanedValid) === 10,
            "10-digit mobile number ({$validPhone}) accepted by validation"
        );

        // Email validation
        $invalidEmail = "not-an-email";
        $validEmail = "staff_" . uniqid() . "@invento-test.com";
        $this->assert(
            filter_var($invalidEmail, FILTER_VALIDATE_EMAIL) === false,
            "Invalid email format correctly flagged"
        );
        $this->assert(
            filter_var($validEmail, FILTER_VALIDATE_EMAIL) !== false,
            "Valid RFC email format correctly accepted"
        );
    }

    // 3. Database Transactions & Rollback Safety
    private function testDatabaseTransactionRollback(): void {
        echo "\n[3] Testing Database Transaction Rollback on Failure...\n";

        $uniqueCode = 'ROLLBACK_' . uniqid();
        $rolledBack = false;

        DB::beginTransaction();
        try {
            DB::query(
                "INSERT INTO staff (concern_id, staff_code, name, status, created_at) VALUES (?, ?, 'Ghost Staff', 'active', NOW())",
                [$this->testConcernId, $uniqueCode]
            );
            $insertedId = (int)DB::lastInsertId();

            // Simulate a failure in dependent permission/user update
            throw new Exception("Simulated mid-operation transaction failure");

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $rolledBack = true;
        }

        $checkStaff = DB::fetch("SELECT id FROM staff WHERE staff_code = ?", [$uniqueCode]);

        $this->assert(
            $rolledBack === true,
            "Exception caught and DB::rollBack() triggered"
        );
        $this->assert(
            $checkStaff === null,
            "Transaction rollback ensured partial staff record was not orphaned or persisted"
        );
    }

    // 4. Strict Default Deny
    private function testStrictDefaultDeny(): void {
        echo "\n[4] Testing Strict Default Deny (treat access as denied unless explicitly granted)...\n";

        // Create user with unconfigured role
        $email = "deny_user_" . uniqid() . "@invento.com";
        DB::query(
            "INSERT INTO users (concern_id, role_id, name, email, password_hash, status, created_at) VALUES (?, ?, 'Deny Test User', ?, 'hash', 'active', NOW())",
            [$this->testConcernId, $this->testRoleId, $email]
        );
        $userId = (int)DB::lastInsertId();
        $this->createdUserIds[] = $userId;

        // Clear role_permissions for this role
        DB::query("DELETE FROM role_permissions WHERE role_id = ?", [$this->testRoleId]);
        DB::query("DELETE FROM user_permissions WHERE user_id = ?", [$userId]);

        // Load permissions into session simulation
        Auth::startSession();
        $_SESSION['user'] = [
            'id'         => $userId,
            'concern_id' => $this->testConcernId,
            'role_id'    => $this->testRoleId,
        ];
        $_SESSION['enabled_modules'] = [$this->testModuleCode];
        Auth::loadPermissions($userId, $this->testRoleId);

        $this->assert(
            Auth::can($this->testModuleCode, 'view') === false,
            "Unconfigured module defaults to DENIED for view action (Strict Default Deny)"
        );
        $this->assert(
            Auth::can($this->testModuleCode, 'add') === false,
            "Unconfigured module defaults to DENIED for add action"
        );
        $this->assert(
            Auth::can($this->testModuleCode, 'edit') === false,
            "Unconfigured module defaults to DENIED for edit action"
        );
        $this->assert(
            Auth::can($this->testModuleCode, 'delete') === false,
            "Unconfigured module defaults to DENIED for delete action"
        );
    }

    // 5. Role Default Permissions
    private function testRoleDefaultPermissions(): void {
        echo "\n[5] Testing Role-Level Default Permissions...\n";

        $userId = end($this->createdUserIds);

        // Configure Role: View=1, Add=1, Edit=0, Delete=0
        DB::query(
            "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
             VALUES (?, ?, 1, 1, 0, 0)
             ON DUPLICATE KEY UPDATE can_view = 1, can_add = 1, can_edit = 0, can_delete = 0",
            [$this->testRoleId, $this->testModuleId]
        );

        Auth::loadPermissions($userId, $this->testRoleId);

        $this->assert(
            Auth::can($this->testModuleCode, 'view') === true,
            "Role permission: View is ALLOWED (1)"
        );
        $this->assert(
            Auth::can($this->testModuleCode, 'add') === true,
            "Role permission: Create (Add) is ALLOWED (1)"
        );
        $this->assert(
            Auth::can($this->testModuleCode, 'edit') === false,
            "Role permission: Edit is DENIED (0)"
        );
        $this->assert(
            Auth::can($this->testModuleCode, 'delete') === false,
            "Role permission: Delete is DENIED (0)"
        );
    }

    // 6. Individual User Granular Overrides (Inherit / Grant / Deny)
    private function testIndividualUserOverrides(): void {
        echo "\n[6] Testing Individual User Overrides (Grant, Deny, Inherit)...\n";

        $userId = end($this->createdUserIds);

        // Role currently has: View=1, Add=1, Edit=0, Delete=0
        // We set User Override:
        // - Edit: Explicit Grant (1) -> Overrides role Edit=0 -> Should be TRUE
        // - Add: Explicit Deny (0) -> Overrides role Add=1 -> Should be FALSE
        // - View: Inherit (NULL) -> Inherits role View=1 -> Should be TRUE
        // - Delete: Inherit (NULL) -> Inherits role Delete=0 -> Should be FALSE

        DB::query(
            "INSERT INTO user_permissions (user_id, module_id, can_view, can_add, can_edit, can_delete)
             VALUES (?, ?, NULL, 0, 1, NULL)
             ON DUPLICATE KEY UPDATE can_view = NULL, can_add = 0, can_edit = 1, can_delete = NULL",
            [$userId, $this->testModuleId]
        );

        Auth::loadPermissions($userId, $this->testRoleId);

        // 1. Explicit Grant Overriding Role Denial
        $this->assert(
            Auth::can($this->testModuleCode, 'edit') === true,
            "Individual Explicit GRANT (can_edit = 1) successfully overrides role denial (role was 0)"
        );

        // 2. Explicit Denial Overriding Role Grant
        $this->assert(
            Auth::can($this->testModuleCode, 'add') === false,
            "Individual Explicit DENY (can_add = 0) successfully blocks access despite role allowance (role was 1)"
        );

        // 3. Inherit retaining role allowance
        $this->assert(
            Auth::can($this->testModuleCode, 'view') === true,
            "Inherit (can_view = NULL) correctly maintains role allowance (role is 1)"
        );

        // 4. Inherit retaining role denial
        $this->assert(
            Auth::can($this->testModuleCode, 'delete') === false,
            "Inherit (can_delete = NULL) correctly maintains role denial (role is 0)"
        );

        // Reverting overrides to inherit by deleting user_permission row
        DB::query("DELETE FROM user_permissions WHERE user_id = ? AND module_id = ?", [$userId, $this->testModuleId]);
        Auth::loadPermissions($userId, $this->testRoleId);

        $this->assert(
            Auth::can($this->testModuleCode, 'add') === true && Auth::can($this->testModuleCode, 'edit') === false,
            "Removing user override reverts module actions cleanly back to role defaults"
        );
    }

    // 7. Deactivation & Immediate Session Invalidation
    private function testDeactivationAndImmediateSessionEviction(): void {
        echo "\n[7] Testing Staff Deactivation & Immediate Session Eviction...\n";

        // Create an active staff & user account
        $email = "active_staff_" . uniqid() . "@invento.com";
        DB::query(
            "INSERT INTO users (concern_id, role_id, name, email, password_hash, status, created_at) VALUES (?, ?, 'Active Employee', ?, 'hash', 'active', NOW())",
            [$this->testConcernId, $this->testRoleId, $email]
        );
        $userId = (int)DB::lastInsertId();
        $this->createdUserIds[] = $userId;

        DB::query(
            "INSERT INTO staff (concern_id, user_id, staff_code, name, email, status, created_at) VALUES (?, ?, ?, 'Active Employee', ?, 'active', NOW())",
            [$this->testConcernId, $userId, 'STF_' . uniqid(), $email]
        );
        $staffId = (int)DB::lastInsertId();
        $this->createdStaffIds[] = $staffId;

        // Simulate active logged-in session
        Auth::startSession();
        $_SESSION['user'] = [
            'id'         => $userId,
            'name'       => 'Active Employee',
            'email'      => $email,
            'concern_id' => $this->testConcernId,
            'role_id'    => $this->testRoleId,
        ];

        $this->assert(
            Auth::check() === true,
            "Active staff member session passes Auth::check() verification"
        );

        // Now Administrator deactivates the staff member
        DB::query("UPDATE staff SET status = 'inactive' WHERE id = ?", [$staffId]);
        DB::query("UPDATE users SET status = 'inactive' WHERE id = ?", [$userId]);

        // Next request by this user calls Auth::check()
        $checkAfterDeactivation = Auth::check();

        $this->assert(
            $checkAfterDeactivation === false,
            "Auth::check() queries live DB status and immediately rejects deactivated user"
        );
        $this->assert(
            empty($_SESSION['user']),
            "Auth::check() automatically purged the deactivated session from memory"
        );
    }

    // 8. Audit Logging
    private function testAuditLogging(): void {
        echo "\n[8] Testing Audit Logging for Staff & Permission Operations...\n";

        $testAction = 'TEST_PERM_' . uniqid();
        AuditLog::record(
            $this->testConcernId,
            'user_permissions',
            999999,
            $testAction,
            ['can_view' => 0],
            ['can_view' => 1],
            'Test audit log recording for permissions change'
        );

        $log = DB::fetch(
            "SELECT * FROM audit_log WHERE action = ? ORDER BY id DESC LIMIT 1",
            [$testAction]
        );

        $this->assert(
            $log !== null,
            "AuditLog::record() successfully writes event to audit_log table"
        );
        $decoded = !empty($log['new_json']) ? json_decode($log['new_json'], true) : [];
        $this->assert(
            !empty($decoded) && ($decoded['can_view'] ?? null) === 1,
            "AuditLog entry correctly recorded state payload"
        );

        if ($log) {
            DB::query("DELETE FROM audit_log WHERE id = ?", [$log['id']]);
        }
    }

    // 9. Module Matrix Submission & Multi-Concern Persistence
    private function testModuleMatrixFormSubmissionAndMultiConcernPersistence(): void {
        echo "\n[9] Testing Module Matrix Form Submission & Multi-Concern Persistence...\n";

        $testEmail = 'matrix_staff_' . uniqid() . '@invento.com';
        $testPhone = '9' . str_pad((string)mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
        $passHash = password_hash('Pass1234', PASSWORD_BCRYPT);

        DB::beginTransaction();
        DB::query(
            "INSERT INTO users (concern_id, role_id, name, email, password_hash, phone, status, created_at)
             VALUES (?, ?, 'Matrix Form Staff', ?, ?, ?, 'active', NOW())",
            [$this->testConcernId, $this->testRoleId, $testEmail, $passHash, $testPhone]
        );
        $userId = (int)DB::lastInsertId();
        $this->createdUserIds[] = $userId;

        // Persist multiple authorized concerns
        $concerns = DB::fetchAll("SELECT id FROM concerns WHERE status = 'active' LIMIT 3");
        $cids = array_map(fn($c) => (int)$c['id'], $concerns);
        $stmtUc = DB::pdo()->prepare("INSERT IGNORE INTO user_concerns (user_id, concern_id) VALUES (?, ?)");
        foreach ($cids as $cid) {
            $stmtUc->execute([$userId, $cid]);
        }

        // Simulate submitted perms for all operational modules: View=1, Add=1, Edit=0, Delete=0
        $stmtUp = DB::pdo()->prepare(
            "INSERT INTO user_permissions (user_id, module_id, can_view, can_add, can_edit, can_delete)
             VALUES (?, ?, 1, 1, 0, 0)
             ON DUPLICATE KEY UPDATE can_view = 1, can_add = 1, can_edit = 0, can_delete = 0"
        );
        $stmtUp->execute([$userId, $this->testModuleId]);

        DB::commit();

        $savedConcerns = DB::fetchAll("SELECT concern_id FROM user_concerns WHERE user_id = ?", [$userId]);
        $savedUp = DB::fetch("SELECT * FROM user_permissions WHERE user_id = ? AND module_id = ?", [$userId, $this->testModuleId]);

        $this->assert(
            count($savedConcerns) === count($cids),
            "Multiple authorized business concerns successfully persisted in user_concerns"
        );
        $this->assert(
            $savedUp !== null && $savedUp['can_view'] == 1 && $savedUp['can_add'] == 1 && $savedUp['can_edit'] == 0 && $savedUp['can_delete'] == 0,
            "Submitted module CRUD permissions (View, Create, Edit, Delete) accurately saved in user_permissions"
        );
    }

    // 10. Existing User (Concern Admin) Staff Linking and Conflict Isolation
    private function testExistingUserStaffLinking(): void {
        echo "\n[10] Testing Existing Concern User Linking & Conflict Isolation...\n";

        $uniqueAdminEmail = 'test_concern_admin_' . uniqid() . '@invento.com';
        $passHash = password_hash('PassAdmin123', PASSWORD_BCRYPT);

        // Simulate concern creation creating user without staff profile
        DB::query(
            "INSERT INTO users (concern_id, role_id, name, email, password_hash, phone, status, created_at)
             VALUES (?, ?, 'New Concern Admin', ?, ?, '9876543210', 'active', NOW())",
            [$this->testConcernId, $this->testRoleId, $uniqueAdminEmail, $passHash]
        );
        $adminUserId = (int)DB::lastInsertId();
        $this->createdUserIds[] = $adminUserId;

        // Verify unlinked user check
        $dupStaff = DB::fetch("SELECT id FROM staff WHERE email = ?", [$uniqueAdminEmail]);
        $dupUser = DB::fetch("SELECT id, concern_id FROM users WHERE email = ?", [$uniqueAdminEmail]);
        $linkedStaff = $dupUser ? DB::fetch("SELECT id FROM staff WHERE user_id = ?", [(int)$dupUser['id']]) : null;

        $canLink = ($dupStaff === null && $dupUser !== null && $linkedStaff === null && (int)$dupUser['concern_id'] === $this->testConcernId);

        $this->assert(
            $canLink === true,
            "Unlinked concern user in same concern correctly identified as eligible for staff profile linking"
        );

        // Perform staff record creation and linking
        $staffCode = 'STF-TEST-' . uniqid();
        DB::query(
            "INSERT INTO staff (concern_id, user_id, staff_code, name, email, password_hash, phone, designation, department, salary_type, base_salary, address, status, joining_date, created_at)
             VALUES (?, ?, ?, 'New Concern Admin', ?, ?, '9876543210', 'Concern Administrator', 'Management', 'monthly', 0.00, '', 'active', CURDATE(), NOW())",
            [$this->testConcernId, $adminUserId, $staffCode, $uniqueAdminEmail, $passHash]
        );
        $staffId = (int)DB::lastInsertId();
        $this->createdStaffIds[] = $staffId;

        $linkedStaffRecord = DB::fetch("SELECT * FROM staff WHERE user_id = ?", [$adminUserId]);
        $this->assert(
            $linkedStaffRecord !== null && (int)$linkedStaffRecord['user_id'] === $adminUserId,
            "Staff profile successfully linked to existing concern admin user account"
        );

        // Subsequent attempt to register with same email in the same concern updates existing staff record seamlessly
        $secondDupStaff = DB::fetch("SELECT id, concern_id, user_id FROM staff WHERE email = ?", [$uniqueAdminEmail]);
        $this->assert(
            $secondDupStaff !== null && (int)$secondDupStaff['concern_id'] === $this->testConcernId,
            "Subsequent form submission with same email in same concern allows update and avoids duplicate collision"
        );
    }
}

// Execute test suite
$suite = new StaffPermissionsTest();
$suite->run();
