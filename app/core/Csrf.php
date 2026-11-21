<?php
// app/core/CSRF.php - Centralized CSRF Protection Helper

declare(strict_types=1);

namespace App\Core;

class CSRF {
    /**
     * Get or generate the current session CSRF token
     */
    public static function token(): string {
        return Auth::csrfToken();
    }

    /**
     * Generate standard hidden CSRF form input field
     */
    public static function field(): string {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Validate CSRF token for the current incoming request
     */
    public static function validateRequest(): bool {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Auth::verifyCsrfToken($token)) {
            http_response_code(419);
            die("CSRF token validation failed. Please refresh and try again.");
        }
        return true;
    }

    /**
     * Verify submitted CSRF token
     */
    public static function verify(?string $token): bool {
        return Auth::verifyCsrfToken($token);
    }
}

// Support both naming conventions (Csrf and CSRF)
if (!class_exists('App\Core\Csrf', false)) {
    class_alias(CSRF::class, 'App\Core\Csrf');
}
if (!class_exists('App\Core\CSRF', false)) {
    class_alias(Csrf::class, 'App\Core\CSRF');
}
