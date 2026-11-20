<?php
// public/index.php - Front Controller

declare(strict_types=1);

// Global top-level exception handler to guarantee informative error display on live servers
set_exception_handler(function (\Throwable $e) {
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Application Error - GRM Inventory</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 2rem; display: flex; align-items: center; justify-content: center; min-height: 100vh; box-sizing: border-box; }
            .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 2rem; max-width: 620px; width: 100%; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5); }
            h2 { color: #f87171; margin-top: 0; font-size: 1.4rem; }
            p { color: #94a3b8; line-height: 1.6; font-size: 0.95rem; }
            .msg { background: #0f172a; border-left: 4px solid #ef4444; padding: 0.75rem 1rem; border-radius: 4px; font-family: monospace; color: #fca5a5; font-size: 0.85rem; word-break: break-all; margin: 1rem 0; }
            .steps { background: #334155; padding: 1rem; border-radius: 8px; font-size: 0.85rem; color: #e2e8f0; }
            .steps ol { margin: 0; padding-left: 1.25rem; }
            .steps li { margin-bottom: 0.35rem; }
            a { color: #38bdf8; text-decoration: none; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>Application Initialization Notice</h2>
            <p>The application encountered an issue while starting up:</p>
            <div class="msg"><?= htmlspecialchars($e->getMessage()) ?></div>
            <div class="steps">
                <strong>Next Steps:</strong>
                <ol>
                    <li>Visit <a href="/health.php">/health.php</a> to run the environment diagnostic test.</li>
                    <li>Ensure your <code>.env</code> file exists on Hostinger with valid database credentials.</li>
                    <li>Verify all tables are imported into phpMyAdmin.</li>
                </ol>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
});

// Load Autoloader
require_once __DIR__ . '/../app/core/Autoloader.php';

use App\Core\Auth;
use App\Core\Router;
use App\Core\Env;

// Load environment variables from .env if present
Env::load(dirname(__DIR__) . '/.env');

// Configure error reporting based on APP_DEBUG
$debug = filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? false, FILTER_VALIDATE_BOOLEAN);
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Set default timezone
$appConfig = require __DIR__ . '/../config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'Asia/Kolkata');

// Start secure session
Auth::startSession();

// Initialize Router & Load Web Routes
$router = new Router();
require_once __DIR__ . '/../app/routes.php';

// Dispatch current request
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $router->dispatch($requestUri, $requestMethod);
} catch (\Throwable $e) {
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    if ($debug) {
        http_response_code(500);
        echo "<div style='font-family:sans-serif;padding:2rem;background:#fee2e2;color:#991b1b;border-radius:8px;margin:2rem;'>";
        echo "<h2 style='margin-top:0;'>Application Runtime Error</h2>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre style='background:#fff;padding:1rem;border-radius:4px;overflow:auto;font-size:12px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</div>";
    } else {
        http_response_code(500);
        echo "<div style='font-family:sans-serif;padding:3rem;text-align:center;color:#334155;'>";
        echo "<h2 style='color:#ef4444;font-size:1.8rem;margin-bottom:0.5rem;'>System Temporarily Unavailable</h2>";
        echo "<p style='font-size:1rem;color:#64748b;'>An error occurred while connecting or initializing the application.</p>";
        echo "<p style='font-size:0.9rem;background:#f1f5f9;display:inline-block;padding:0.5rem 1rem;border-radius:6px;border:1px solid #cbd5e1;'><strong>Detail:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p style='margin-top:1.5rem;'><a href='/health.php' style='color:#2563eb;'>Run Health Check Diagnostic</a></p>";
        echo "</div>";
    }
}
