<?php
// config/app.php - Application Configuration

return [
    'name'      => 'GRM Inventory',
    'full_name' => 'GRM Inventory',
    'version'   => '1.0.0',
    'timezone'  => 'Asia/Kolkata',
    'debug'     => filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? false, FILTER_VALIDATE_BOOLEAN),
    'session'   => [
        'name'     => $_ENV['SESSION_NAME'] ?? 'INVENTO_SESSID',
        'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 86400), // 24 hours
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ],
];
