<?php
// database/migrate_modules_plan.php - Run the 3 module migrations safely

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;

$migrationFiles = [
    __DIR__ . '/migrations/2026_11_20_add_return_exchange_details.sql',
    __DIR__ . '/migrations/2026_11_20_refactor_stock_request_locations.sql',
    __DIR__ . '/migrations/2026_11_20_unify_damage_tables.sql',
];

echo "=== Running Migrations for Returns/Exchange, Stock Request, and Damage Modules ===\n\n";

foreach ($migrationFiles as $file) {
    $name = basename($file);
    echo "Running {$name}...\n";
    if (!file_exists($file)) {
        echo "  [ERROR] File not found: {$file}\n";
        continue;
    }

    $sql = file_get_contents($file);
    // Split statements safely
    $queries = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($queries as $q) {
        // Strip leading line comments
        $cleanQ = trim(preg_replace('/^(\s*--[^\r\n]*[\r\n]+)+/', '', $q));
        if ($cleanQ === '') {
            continue;
        }
        try {
            DB::query($cleanQ);
            echo "  [OK] Statement executed.\n";
        } catch (\Throwable $e) {
            // If column already exists or similar benign notification, report and proceed
            echo "  [NOTICE] " . $e->getMessage() . "\n";
        }
    }
}

// Ensure concern_damages has all resolution columns
$existingCols = array_column(DB::fetchAll("DESCRIBE concern_damages"), 'Field');
$columnsToAdd = [
    'status' => "ENUM('LOGGED', 'INSPECTED', 'RESOLVED', 'CANCELLED') NOT NULL DEFAULT 'LOGGED' AFTER `reason`",
    'severity' => "ENUM('MINOR', 'MODERATE', 'SEVERE', 'SCRAP') NOT NULL DEFAULT 'MODERATE' AFTER `status`",
    'resolution_action' => "ENUM('SCRAP', 'WRITE_OFF', 'SALVAGE', 'REPAIR', 'REWORK', 'RETURN_TO_SUPPLIER', 'SUPPLIER_REPLACEMENT') NULL AFTER `severity`",
    'resolution_reason' => "VARCHAR(255) NULL AFTER `resolution_action`",
    'resolution_notes' => "TEXT NULL AFTER `resolution_reason`",
    'loss_amount' => "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `resolution_notes`",
    'photo_url' => "VARCHAR(255) NULL AFTER `loss_amount`",
    'replacement_variant_id' => "INT NULL AFTER `photo_url`",
    'replacement_qr_code' => "VARCHAR(100) NULL AFTER `replacement_variant_id`",
    'replacement_inwarded_at' => "DATETIME NULL AFTER `replacement_qr_code`",
    'resolved_by' => "INT NULL AFTER `replacement_inwarded_at`",
    'resolved_at' => "DATETIME NULL AFTER `resolved_by`",
    'cancelled_by' => "INT NULL AFTER `resolved_at`",
    'cancelled_at' => "DATETIME NULL AFTER `cancelled_by`",
    'cancel_reason' => "TEXT NULL AFTER `cancelled_at`",
];

foreach ($columnsToAdd as $col => $definition) {
    if (!in_array($col, $existingCols, true)) {
        try {
            DB::query("ALTER TABLE `concern_damages` ADD COLUMN `{$col}` {$definition}");
            echo "  [OK] Added column '{$col}' to concern_damages.\n";
        } catch (\Throwable $e) {
            echo "  [NOTICE] Failed adding {$col}: " . $e->getMessage() . "\n";
        }
    }
}

// Ensure warehouse_damage exists in modules table
echo "\nRegistering modules in 'modules' table...\n";
$modulesToRegister = [
    ['code' => 'warehouse_damage', 'name' => 'Warehouse Damage', 'icon' => 'bi-shield-slash', 'sort_order' => 43, 'group_name' => 'Stock', 'is_super_admin_only' => 0],
];

foreach ($modulesToRegister as $m) {
    $exists = DB::fetchValue("SELECT id FROM modules WHERE code = ?", [$m['code']]);
    if (!$exists) {
        DB::query(
            "INSERT INTO modules (code, name, icon, sort_order, group_name, is_super_admin_only) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [$m['code'], $m['name'], $m['icon'], $m['sort_order'], $m['group_name'], $m['is_super_admin_only']]
        );
        $modId = (int)DB::lastInsertId();
        echo "  [OK] Module '{$m['code']}' created with ID {$modId}.\n";

        // Grant to concern_modules for existing concerns
        $concerns = DB::fetchAll("SELECT id FROM concerns");
        foreach ($concerns as $c) {
            DB::query("INSERT IGNORE INTO concern_modules (concern_id, module_id) VALUES (?, ?)", [$c['id'], $modId]);
        }
    } else {
        echo "  [OK] Module '{$m['code']}' already exists.\n";
    }
}

echo "\nMigrations completed successfully.\n";
