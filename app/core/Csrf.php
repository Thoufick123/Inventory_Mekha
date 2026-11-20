<?php
// app/core/CSRF.php - Centralized CSRF Protection Helper

declare(strict_types=1);

namespace App\Core;

class CSRF {
    public static function token(): string {
        return Auth::csrfToken();
    }

    public static function field(): string {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    public static function validateRequest(): bool {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Auth::verifyCsrfToken($token)) {
            http_response_code(419);
            die("CSRF token validation failed. Please refresh and try again.");
        }
        return true;
    }

    public static function verify(?string $token): bool {
        return Auth::verifyCsrfToken($token);
    }
}

if (!class_exists('App\Core\Csrf', false)) {
    class_alias(CSRF::class, 'App\Core\Csrf');
}
