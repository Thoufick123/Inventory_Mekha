<?php
// app/core/CSRF.php - Centralized CSRF Protection Helper

declare(strict_types=1);

namespace App\Core;

class CSRF {
    public static function token(): string {
        return Auth::csrfToken();
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
