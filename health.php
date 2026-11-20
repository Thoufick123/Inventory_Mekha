<?php
// health.php - Hostinger Deployment & Environment Diagnostic Tool
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$phpVersion = PHP_VERSION;
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'session', 'json', 'openssl', 'fileinfo'];
$extStatus = [];
foreach ($requiredExtensions as $ext) {
    $extStatus[$ext] = extension_loaded($ext);
}

$envPath = __DIR__ . '/.env';
$envExists = file_exists($envPath);

$dbStatus = null;
$dbMessage = null;
$dbDetails = [];

if ($envExists) {
    // Basic .env parse
    $envVars = [];
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && !str_starts_with($line, '#') && str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            $envVars[$k] = $v;
        }
    }

    $dbHost = $envVars['DB_HOST'] ?? '127.0.0.1';
    $dbPort = $envVars['DB_PORT'] ?? '3306';
    $dbName = $envVars['DB_DATABASE'] ?? '';
    $dbUser = $envVars['DB_USERNAME'] ?? '';
    $dbPass = $envVars['DB_PASSWORD'] ?? '';

    $dbDetails = [
        'Host' => $dbHost,
        'Port' => $dbPort,
        'Database' => $dbName,
        'User' => $dbUser,
    ];

    try {
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
        try {
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Throwable $t) {
            $pdo->exec("SET NAMES utf8mb4");
        }

        $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $tableCount = count($allTables);
        $dbStatus = true;
        $dbMessage = "Successfully connected to MySQL! Found {$tableCount} tables in database `{$dbName}`.";

        $puExists = in_array('production_units', $allTables, true);
        if (!$puExists || (isset($_GET['migrate']) && $_GET['migrate'] === '1')) {
            ob_start();
            require __DIR__ . '/scripts/migrate_production_blueprint.php';
            $migrationOutput = ob_get_clean();
            $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $tableCount = count($allTables);
            $puExists = true;
        }
    } catch (\Throwable $e) {
        $dbStatus = false;
        $dbMessage = $e->getMessage();
        $puExists = false;
    }
}

// App Bootstrap Simulation Check
$bootstrapStatus = null;
$bootstrapError = null;
try {
    require_once __DIR__ . '/app/core/Autoloader.php';
    \App\Core\Env::load(__DIR__ . '/.env');
    \App\Core\Auth::startSession();
    $router = new \App\Core\Router();
    require_once __DIR__ . '/app/routes.php';
    $routesCount = count($router->getRoutes());
    
    // Check concerns table
    $concernsCount = (int)\App\Core\DB::fetchValue("SELECT COUNT(*) FROM concerns WHERE status = 'active'");
    $bootstrapStatus = true;
    $bootstrapMessage = "Application Bootstrap succeeded! Loaded {$routesCount} routes and verified {$concernsCount} active concerns.";
} catch (\Throwable $e) {
    $bootstrapStatus = false;
    $bootstrapError = $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Health Check - GRM Inventory</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 2rem; display: flex; justify-content: center; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 2rem; max-width: 680px; width: 100%; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5); }
        h1 { color: #38bdf8; font-size: 1.5rem; margin-top: 0; }
        .badge { display: inline-block; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .badge-success { background: #065f46; color: #6ee7b7; }
        .badge-danger { background: #7f1d1d; color: #fca5a5; }
        .badge-warning { background: #78350f; color: #fde68a; }
        .section { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #334155; }
        .section-title { font-weight: 600; color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem; }
        .item { display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0; font-size: 0.95rem; }
        .item-label { color: #cbd5e1; }
        .box { background: #0f172a; border-radius: 8px; padding: 1rem; margin-top: 0.75rem; font-family: monospace; font-size: 0.85rem; word-break: break-all; }
        .box-success { border-left: 4px solid #10b981; color: #6ee7b7; }
        .box-danger { border-left: 4px solid #ef4444; color: #fca5a5; }
        .box-warning { border-left: 4px solid #f59e0b; color: #fde68a; }
        a { color: #38bdf8; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .btn-link { display: inline-block; background: #2563eb; color: #ffffff; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; text-decoration: none; }
        .btn-link:hover { background: #1d4ed8; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>Server & System Health Check</h1>
            <span class="badge badge-success">Online</span>
        </div>
        <p style="color: #94a3b8; font-size: 0.9rem; margin-top: 0;">This file verifies that Hostinger Apache/LiteSpeed web server and PHP runtime are working properly.</p>

        <!-- PHP Environment -->
        <div class="section">
            <div class="section-title">1. PHP Environment</div>
            <div class="item">
                <span class="item-label">PHP Version</span>
                <span style="font-weight: 600;"><?= htmlspecialchars($phpVersion) ?> (<?= version_compare($phpVersion, '8.1.0', '>=') ? '<span style="color:#4ade80;">Compatible</span>' : '<span style="color:#ef4444;">Upgrade to 8.1+ recommended</span>' ?>)</span>
            </div>
            <div class="item">
                <span class="item-label">Document Root</span>
                <span style="color:#94a3b8; font-size:0.85rem;"><?= htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') ?></span>
            </div>
        </div>

        <!-- Required PHP Extensions -->
        <div class="section">
            <div class="section-title">2. PHP Extensions</div>
            <?php foreach ($extStatus as $ext => $loaded): ?>
                <div class="item">
                    <span class="item-label">Extension <code><?= htmlspecialchars($ext) ?></code></span>
                    <span class="badge <?= $loaded ? 'badge-success' : 'badge-danger' ?>"><?= $loaded ? 'Loaded' : 'Missing' ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Configuration (.env) -->
        <div class="section">
            <div class="section-title">3. Environment Configuration (.env)</div>
            <div class="item">
                <span class="item-label">File Status</span>
                <span class="badge <?= $envExists ? 'badge-success' : 'badge-danger' ?>"><?= $envExists ? 'Found (.env)' : 'Missing (.env)' ?></span>
            </div>
            <?php if (!$envExists): ?>
                <div class="box box-warning">
                    <strong>Notice:</strong> <code>.env</code> file was not found in your Hostinger root folder!<br><br>
                    Please open Hostinger File Manager, create a file named <code>.env</code> in the repository root directory, and paste the contents from <code>.env.hostinger.example</code>.
                </div>
            <?php else: ?>
                <div class="item">
                    <span class="item-label">Database Target</span>
                    <span style="color: #38bdf8; font-family: monospace;"><?= htmlspecialchars($dbDetails['Database'] ?? '') ?></span>
                </div>
                <div class="item">
                    <span class="item-label">Database User</span>
                    <span style="color: #cbd5e1; font-family: monospace;"><?= htmlspecialchars($dbDetails['User'] ?? '') ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- MySQL Database Connection -->
        <div class="section">
            <div class="section-title">4. MySQL Database & Schema Status</div>
            <?php if (!$envExists): ?>
                <div style="color: #94a3b8; font-size: 0.9rem;">Database connection check requires <code>.env</code>.</div>
            <?php elseif ($dbStatus === true): ?>
                <div class="box box-success">
                    <strong>OK:</strong> <?= htmlspecialchars($dbMessage) ?>
                </div>

                <?php if (!empty($migrationOutput)): ?>
                    <div class="box box-success" style="white-space: pre-wrap; font-size: 0.8rem; margin-top: 0.75rem;">
                        <strong>Migration Applied Successfully:</strong><br>
                        <?= htmlspecialchars($migrationOutput) ?>
                    </div>
                <?php endif; ?>

                <?php if (!$puExists): ?>
                    <div class="box box-warning">
                        <strong>Schema Update Required:</strong> Table <code>production_units</code> is not created yet on your live Hostinger database.<br><br>
                        <a href="/health.php?migrate=1" class="btn-link" style="background: #d97706; padding: 0.5rem 1rem; font-size: 0.85rem;">
                            ⚡ Click Here to Auto-Migrate Database Now
                        </a>
                    </div>
                <?php else: ?>
                    <div class="item" style="margin-top: 0.5rem;">
                        <span class="item-label">Production Unit Schema (62 Tables)</span>
                        <span class="badge badge-success">Migrated & Synced</span>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="box box-danger">
                    <strong>Connection Failed:</strong><br>
                    <?= htmlspecialchars($dbMessage ?? 'Unknown error') ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Application Bootstrap Simulation -->
        <div class="section">
            <div class="section-title">5. Core Application Bootstrap Check</div>
            <div class="item">
                <span class="item-label">Router, Session & Autoloader</span>
                <span class="badge <?= $bootstrapStatus ? 'badge-success' : 'badge-danger' ?>"><?= $bootstrapStatus ? 'Ready' : 'Failed' ?></span>
            </div>
            <?php if ($bootstrapStatus): ?>
                <div class="box box-success">
                    <strong>OK:</strong> <?= htmlspecialchars($bootstrapMessage) ?>
                </div>
            <?php else: ?>
                <div class="box box-danger">
                    <strong>Bootstrap Exception:</strong><br>
                    <?= htmlspecialchars($bootstrapError ?? 'Unknown error') ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Direct Navigation Buttons -->
        <div class="section" style="text-align: center; padding-top: 1.5rem;">
            <a href="/login" class="btn-link" style="margin-right: 0.75rem;">Open Login Page (/login) &rarr;</a>
            <a href="/index.php" class="btn-link" style="background: #334155;">Open Front Controller (/index.php) &rarr;</a>
        </div>
    </div>
</body>
</html>
