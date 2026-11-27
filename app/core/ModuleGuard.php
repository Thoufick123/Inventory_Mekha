<?php
// app/core/ModuleGuard.php

namespace App\Core;

class ModuleGuard {
    /**
     * Enforce module availability and role permission for current route
     */
    public static function enforce(string $moduleCode, string $action = 'view'): void {
        if (!Auth::check()) {
            $_SESSION['flash_error'] = 'Please sign in to access this page.';
            header('Location: ' . self::url('/login'));
            exit;
        }

        // If user is super admin, check if trying to access super admin module
        if (Auth::isSuperAdmin()) {
            return;
        }

        // For concern users:
        // 1. Check if concern has this module enabled
        if (!Auth::hasModule($moduleCode)) {
            http_response_code(403);
            self::render403("The module '{$moduleCode}' is not enabled for your organization.");
            exit;
        }

        // 2. Check role permission for this module (allow module entry if user has any CRUD permission)
        $requiredAction = strtolower($action) === 'view' ? 'view|add|edit|delete' : $action;
        if (!Auth::can($moduleCode, $requiredAction)) {
            http_response_code(403);
            self::render403("You do not have permission to '{$action}' in module '{$moduleCode}'.");
            exit;
        }
    }

    public static function enforceSuperAdmin(): void {
        if (!Auth::check()) {
            header('Location: ' . self::url('/login'));
            exit;
        }

        if (!Auth::isSuperAdmin()) {
            http_response_code(403);
            self::render403("This section is restricted to Super Administrators only.");
            exit;
        }
    }

    private static function render403(string $message): void {
        $isAjaxOrApi = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
                    || (isset($_SERVER['REQUEST_URI']) && str_contains($_SERVER['REQUEST_URI'], '/api/'));
        if ($isAjaxOrApi) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $message]);
            exit;
        }

        $viewPath = __DIR__ . '/../views/errors/403.php';
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            echo "<h1>403 Forbidden</h1><p>" . htmlspecialchars($message) . "</p>";
        }
    }

    private static function url(string $path): string {
        return Controller::url($path);
    }
}
