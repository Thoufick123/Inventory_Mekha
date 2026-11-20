<?php
// public/index.php - Front Controller

declare(strict_types=1);

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
    error_reporting(0);
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
        echo "<p style='margin-top:1.5rem;'><small style='color:#94a3b8;'>Please ensure your <code>.env</code> file has the correct database credentials for your hosting environment.</small></p>";
        echo "</div>";
    }
}
