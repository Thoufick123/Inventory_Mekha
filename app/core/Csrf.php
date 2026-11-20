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
     * Verify submitted CSRF token
     */
    public static function verify(?string $token): bool {
        return Auth::verifyCsrfToken($token);
    }
}
