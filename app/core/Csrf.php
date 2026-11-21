<?php
// app/core/Csrf.php

declare(strict_types=1);

namespace App\Core;

class Csrf {
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
     * Validate the CSRF token submitted with the current request
     */
    public static function validateRequest(): bool {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::verify(is_string($token) ? $token : null)) {
            http_response_code(419);
            die('CSRF token validation failed. Please refresh and try again.');
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
