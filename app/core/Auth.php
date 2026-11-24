<?php
// app/core/Auth.php

namespace App\Core;

class Auth {
    private static bool $permissionsRefreshed = false;

    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            $config = require __DIR__ . '/../../config/app.php';
            $sessionDirectory = dirname(__DIR__, 2) . '/storage/sessions';
            if (!is_dir($sessionDirectory)) {
                @mkdir($sessionDirectory, 0775, true);
            }
            if (is_dir($sessionDirectory) && is_writable($sessionDirectory)) {
                ini_set('session.save_path', $sessionDirectory);
                session_save_path($sessionDirectory);
            }
            if (!headers_sent()) {
                session_name($config['session']['name'] ?? 'GRM_INVENTORY_SESSID');
                session_set_cookie_params([
                    'lifetime' => $config['session']['lifetime'] ?? 86400,
                    'path'     => $config['session']['path'] ?? '/',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                session_start();
            } else {
                @session_start();
            }
        }
    }

    public static function check(): bool {
        self::startSession();
        if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
            return false;
        }

        // Real-time active status check to immediately evict deactivated users
        try {
            $userId = (int)$_SESSION['user']['id'];
            $userStatus = DB::fetchValue("SELECT status FROM users WHERE id = ?", [$userId]);
            if ($userStatus !== 'active') {
                self::logout();
                return false;
            }

            $concernId = $_SESSION['user']['concern_id'] ?? null;
            if ($concernId !== null) {
                $concernStatus = DB::fetchValue("SELECT status FROM concerns WHERE id = ?", [(int)$concernId]);
                if ($concernStatus !== 'active') {
                    self::logout();
                    return false;
                }
            }

            // Real-time permission refresh so permission changes take effect immediately without re-login
            if (!self::$permissionsRefreshed && !self::isSuperAdmin()) {
                self::loadPermissions($userId, (int)($_SESSION['user']['role_id'] ?? 0));
                self::$permissionsRefreshed = true;
            }

            // Ensure Super Admin has super_admin_concern_id initialized to 0 (ALL CONCERNS) if not set
            if (self::isSuperAdmin() && !isset($_SESSION['super_admin_concern_id'])) {
                $_SESSION['super_admin_concern_id'] = 0;
            }
        } catch (\Throwable $e) {
            // In case of DB disconnection during check, fall back to false
            return false;
        }

        return true;
    }

    public static function user(): ?array {
        self::startSession();
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int {
        return self::user()['id'] ?? null;
    }

    public static function isSuperAdmin(): bool {
        $u = self::user();
        return $u !== null && $u['concern_id'] === null;
    }

    /**
     * Session tenant identifier.
     * For concern users: fixed to their own concern_id.
     * For super admin: can be switched to a specific concern or null (all concerns).
     */
    public static function concernId(): ?int {
        $u = self::user();
        if (!$u) {
            return null;
        }
        if ($u['concern_id'] !== null) {
            return (int)$u['concern_id'];
        }
        // Super admin selected concern
        if (isset($_SESSION['super_admin_concern_id'])) {
            return $_SESSION['super_admin_concern_id'] > 0 ? (int)$_SESSION['super_admin_concern_id'] : null;
        }
        // Super Admin default: null (ALL CONCERNS - consolidated view across all concerns)
        return null;
    }

    public static function superAdminSelectedConcernId(): ?int {
        return self::concernId();
    }

    /**
     * Get active scoped production unit ID
     */
    public static function productionUnitId(): ?int {
        $u = self::user();
        if (!$u) {
            return null;
        }
        if (self::isSuperAdmin() || in_array($u['role'] ?? '', ['admin', 'owner'])) {
            return !empty($_SESSION['active_unit_id']) 
                ? (int)$_SESSION['active_unit_id'] 
                : (!empty($u['production_unit_id']) ? (int)$u['production_unit_id'] : null);
        }
        return !empty($u['production_unit_id']) ? (int)$u['production_unit_id'] : null;
    }

    /**
     * Switch active production unit in session
     */
    public static function switchProductionUnit(?int $unitId): void {
        self::startSession();
        if ($unitId && $unitId > 0) {
            $_SESSION['active_unit_id'] = (int)$unitId;
        } else {
            unset($_SESSION['active_unit_id']);
        }
    }

    public static function switchConcern(?int $concernId): void {
        self::startSession();
        if (!self::isSuperAdmin()) {
            return;
        }
        if ($concernId && $concernId > 0) {
            $_SESSION['super_admin_concern_id'] = (int)$concernId;
        } else {
            $_SESSION['super_admin_concern_id'] = 0;
        }
    }

    public static function currentConcernId(): ?int {
        return self::concernId();
    }

    public static function currentWarehouseId(): int {
        return (int)($_SESSION['active_warehouse_id'] ?? 1);
    }

    public static function currentShopId(): int {
        return (int)($_SESSION['active_shop_id'] ?? 1);
    }

    public static function currentProductionUnitId(): ?int {
        return self::productionUnitId() ?: 1;
    }

    public static function allConcerns(): array {
        return DB::fetchAll("SELECT id, name, code, status FROM concerns WHERE status = 'active' OR status IS NULL OR status = '' ORDER BY name ASC");
    }

    /**
     * Get all active concerns a specific user is authorized to access.
     */
    public static function getUserConcerns(int $userId): array {
        return DB::fetchAll(
            "SELECT DISTINCT c.id, c.name, c.code, c.status, c.logo
             FROM concerns c
             WHERE c.status = 'active'
               AND c.id IN (
                   SELECT concern_id FROM user_concerns WHERE user_id = ?
                   UNION
                   SELECT concern_id FROM users WHERE id = ? AND concern_id IS NOT NULL AND status = 'active'
               )
             ORDER BY c.name ASC",
            [$userId, $userId]
        );
    }

    /**
     * Check if a concern is a retail shop / store
     */
    public static function isShopConcern(?int $concernId): bool {
        if (!$concernId) {
            return false;
        }
        $c = DB::fetch("SELECT name, code FROM concerns WHERE id = ?", [$concernId]);
        if (!$c) {
            return false;
        }
        $nameLower = strtolower($c['name']);
        $codeLower = strtolower($c['code']);
        return str_contains($nameLower, 'shop') || str_contains($nameLower, 'store') || str_contains($nameLower, 'retail') || str_contains($codeLower, 'sh');
    }

    /**
     * Set active concern context for current session
     */
    public static function setSessionConcern(int $concernId): bool {
        self::startSession();
        $c = DB::fetch("SELECT id, name, code, status, logo FROM concerns WHERE id = ? AND status = 'active'", [$concernId]);
        if (!$c) {
            return false;
        }

        if (isset($_SESSION['user'])) {
            $_SESSION['user']['concern_id'] = (int)$c['id'];
        }

        $_SESSION['concern'] = [
            'id'   => (int)$c['id'],
            'name' => $c['name'],
            'code' => $c['code'],
            'logo' => $c['logo'],
        ];

        // Cache enabled modules for fast checking
        $mods = DB::fetchAll(
            "SELECT m.code, m.id FROM concern_modules cm 
             JOIN modules m ON cm.module_id = m.id 
             WHERE cm.concern_id = ? AND cm.enabled = 1",
            [(int)$c['id']]
        );
        $registeredModuleCount = (int)DB::fetchValue("SELECT COUNT(*) FROM modules");
        $_SESSION['enabled_modules'] = $registeredModuleCount < 5
            ? array_values(array_unique(array_merge(array_column($mods, 'code'), ModuleRegistry::concernCodes())))
            : array_column($mods, 'code');

        // Cache role permissions and user-specific overrides
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $roleId = (int)($_SESSION['user']['role_id'] ?? 0);
        self::loadPermissions($userId, $roleId);

        return true;
    }

    /**
     * Load role defaults and user-specific granular overrides
     */
    public static function loadPermissions(int $userId, int $roleId): void {
        // 1. Role-level permissions (defaults)
        $roleMap = [];
        if ($roleId > 0) {
            $perms = DB::fetchAll(
                "SELECT m.code, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete 
                 FROM role_permissions rp
                 JOIN modules m ON rp.module_id = m.id
                 WHERE rp.role_id = ?",
                [$roleId]
            );
            foreach ($perms as $p) {
                $roleMap[$p['code']] = [
                    'view'   => (bool)$p['can_view'],
                    'add'    => (bool)$p['can_add'],
                    'edit'   => (bool)$p['can_edit'],
                    'delete' => (bool)$p['can_delete'],
                ];
            }
        }

        // If seeded role/module permissions have been cleared, keep Concern
        // Administrators usable by granting the hardcoded master module set.
        $roleName = (string)($_SESSION['user']['role_name'] ?? '');
        if (empty($roleMap) && (stripos($roleName, 'Administrator') !== false || stripos($roleName, 'Admin') !== false)) {
            foreach (ModuleRegistry::concernCodes() as $moduleCode) {
                $roleMap[$moduleCode] = [
                    'view'   => true,
                    'add'    => true,
                    'edit'   => true,
                    'delete' => true,
                ];
            }
        }
        $_SESSION['role_permissions'] = $roleMap;

        // 2. User-level permission overrides (NULL = inherit, 1 = grant, 0 = deny)
        $userMap = [];
        if ($userId > 0) {
            $cid = (int)($_SESSION['user']['concern_id'] ?? ($_SESSION['concern']['id'] ?? 0));
            $params = [$userId];
            $sql = "SELECT m.code, up.can_view, up.can_add, up.can_edit, up.can_delete 
                    FROM user_permissions up
                    JOIN modules m ON up.module_id = m.id
                    WHERE up.user_id = ?";
            $upCols = array_column(DB::fetchAll("DESCRIBE user_permissions"), 'Field');
            $hasConcernCol = in_array('concern_id', $upCols, true);
            if ($hasConcernCol && $cid > 0) {
                $sql .= " AND (up.concern_id = ? OR up.concern_id IS NULL) ORDER BY (up.concern_id IS NOT NULL) ASC";
                $params[] = $cid;
            } elseif ($hasConcernCol) {
                $sql .= " AND up.concern_id IS NULL";
            }
            $userPerms = DB::fetchAll($sql, $params);
            foreach ($userPerms as $up) {
                $userMap[$up['code']] = [
                    'view'   => $up['can_view'] === null ? null : (int)$up['can_view'],
                    'add'    => $up['can_add'] === null ? null : (int)$up['can_add'],
                    'edit'   => $up['can_edit'] === null ? null : (int)$up['can_edit'],
                    'delete' => $up['can_delete'] === null ? null : (int)$up['can_delete'],
                ];
            }
        }
        $_SESSION['user_permissions'] = $userMap;
    }

    public static function concern(): ?array {
        if (!self::isSuperAdmin()) {
            return $_SESSION['concern'] ?? null;
        }
        $selectedId = self::concernId();
        if ($selectedId) {
            return DB::fetch("SELECT id, name, code, logo FROM concerns WHERE id = ?", [$selectedId]);
        }
        return null;
    }

    public static function login(string $email, string $password): bool {
        self::startSession();

        $email = trim($email);
        if (strtolower($email) === 'root') {
            $email = 'superadmin';
        }
        
        $sql = "SELECT u.*, r.name AS role_name, c.name AS concern_name, c.code AS concern_code, c.status AS concern_status, c.logo AS concern_logo
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                LEFT JOIN concerns c ON u.concern_id = c.id
                WHERE (u.email = ? OR u.email = CONCAT(?, '@invento.com') OR u.name = ?) AND u.status = 'active'
                LIMIT 1";
        
        $user = DB::fetch($sql, [$email, $email, $email]);
        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        // If user belongs to a concern, ensure concern is active
        if ($user['concern_id'] !== null && $user['concern_status'] !== 'active') {
            return false;
        }

        // Prevent session fixation
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }

        $_SESSION['user'] = [
            'id'           => (int)$user['id'],
            'name'         => $user['name'],
            'email'        => $user['email'],
            'concern_id'   => $user['concern_id'] !== null ? (int)$user['concern_id'] : null,
            'role_id'      => (int)$user['role_id'],
            'role_name'    => $user['role_name'],
            'phone'        => $user['phone'],
        ];

        if ($user['concern_id'] !== null) {
            $_SESSION['concern'] = [
                'id'     => (int)$user['concern_id'],
                'name'   => $user['concern_name'],
                'code'   => $user['concern_code'],
                'logo'   => $user['concern_logo'],
            ];

            // Cache enabled modules for fast checking
            $mods = DB::fetchAll(
                "SELECT m.code, m.id FROM concern_modules cm 
                 JOIN modules m ON cm.module_id = m.id 
                 WHERE cm.concern_id = ? AND cm.enabled = 1",
                [(int)$user['concern_id']]
            );
            $registeredModuleCount = (int)DB::fetchValue("SELECT COUNT(*) FROM modules");
            $_SESSION['enabled_modules'] = $registeredModuleCount < 5
                ? array_values(array_unique(array_merge(array_column($mods, 'code'), ModuleRegistry::concernCodes())))
                : array_column($mods, 'code');

            // Cache permissions (role defaults + individual user overrides)
            self::loadPermissions((int)$user['id'], (int)$user['role_id']);
        } else {
            $_SESSION['concern'] = null;
            $_SESSION['enabled_modules'] = [];
            $_SESSION['role_permissions'] = [];
            $_SESSION['user_permissions'] = [];
            $_SESSION['super_admin_concern_id'] = 0; // Default to ALL CONCERNS for Super Admin
        }

        // Update last login
        DB::query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

        // Audit log
        \App\Services\AuditLog::log('LOGIN', 'users', $user['id'], null, ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);

        return true;
    }

    public static function logout(): void {
        self::startSession();
        if (isset($_SESSION['user']['id'])) {
            \App\Services\AuditLog::log('LOGOUT', 'users', $_SESSION['user']['id'], null, null);
        }
        $_SESSION = [];
        if (!headers_sent() && ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
    }

    public static function hasModule(string $moduleCode): bool {
        if (self::isSuperAdmin()) {
            return true;
        }
        if ($moduleCode === 'cash') {
            $moduleCode = 'cash_flow';
        }
        $mods = $_SESSION['enabled_modules'] ?? [];
        return in_array($moduleCode, $mods, true);
    }

    public static function can(string $moduleCode, string $action = 'view'): bool {
        if (self::isSuperAdmin()) {
            return true;
        }
        if ($moduleCode === 'cash') {
            $moduleCode = 'cash_flow';
        }
        if ($moduleCode === 'variants' || $moduleCode === 'stock_ledger') {
            $moduleCode = 'stocks';
        }
        if (!self::hasModule($moduleCode)) {
            return false;
        }

        // Support alternative actions separated by '|' (e.g. 'add|edit')
        if (str_contains($action, '|')) {
            $actions = explode('|', $action);
            foreach ($actions as $subAction) {
                if (self::can($moduleCode, trim($subAction))) {
                    return true;
                }
            }
            return false;
        }

        $act = strtolower($action);

        // concern_dashboard is a base shell/dashboard module for every tenant concern.
        // It should inherit the user's operational level: if user can add/edit anywhere or has general access, allow view/export.
        if ($moduleCode === 'concern_dashboard') {
            if ($act === 'view') {
                return true;
            }
            if ($act === 'export' || $act === 'add' || $act === 'edit') {
                // If user has create/edit access in any operational business module, allow exporting the operations dashboard
                $userPerms = $_SESSION['user_permissions'] ?? [];
                foreach ($userPerms as $mCode => $p) {
                    if ($mCode === 'concern_dashboard') continue;
                    if (!empty($p['add']) || !empty($p['edit'])) {
                        return true;
                    }
                }
                $rolePerms = $_SESSION['role_permissions'] ?? [];
                foreach ($rolePerms as $mCode => $p) {
                    if ($mCode === 'concern_dashboard') continue;
                    if (!empty($p['add']) || !empty($p['edit'])) {
                        return true;
                    }
                }
                return false;
            }
        }

        // Action normalization: support 'view', 'add'/'create', 'edit', 'delete', plus operational actions
        $actionAlias = null;
        if ($act === 'create') {
            $act = 'add';
        } elseif (in_array($act, ['process', 'dispatch', 'receive', 'inward', 'resolve', 'inspect'], true)) {
            $actionAlias = in_array($act, ['dispatch', 'receive', 'resolve', 'process']) ? 'edit' : 'add';
        } elseif (in_array($act, ['cancel', 'reverse', 'reject'], true)) {
            $actionAlias = 'delete';
        } elseif ($act === 'export') {
            // View-only users cannot export data. Exporting requires create (add) or edit permissions.
            return self::can($moduleCode, 'add') || self::can($moduleCode, 'edit');
        }

        // 1. Check user-specific granular overrides
        // NULL = inherit from role, 1 = explicitly granted, 0 = explicitly denied
        $userPerms = $_SESSION['user_permissions'] ?? [];
        if (isset($userPerms[$moduleCode]) && array_key_exists($act, $userPerms[$moduleCode])) {
            $val = $userPerms[$moduleCode][$act];
            if ($val !== null) {
                return ((int)$val === 1);
            }
        }
        if ($actionAlias && isset($userPerms[$moduleCode]) && array_key_exists($actionAlias, $userPerms[$moduleCode])) {
            $val = $userPerms[$moduleCode][$actionAlias];
            if ($val !== null) {
                return ((int)$val === 1);
            }
        }

        // 2. Check role-level default permissions
        $rolePerms = $_SESSION['role_permissions'] ?? [];
        if (isset($rolePerms[$moduleCode]) && !empty($rolePerms[$moduleCode][$act])) {
            return true;
        }
        if ($actionAlias && isset($rolePerms[$moduleCode]) && !empty($rolePerms[$moduleCode][$actionAlias])) {
            return true;
        }

        // 3. Strict Default Deny: access is denied unless explicitly granted
        return false;
    }

    public static function csrfToken(): string {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken(?string $token): bool {
        self::startSession();
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
