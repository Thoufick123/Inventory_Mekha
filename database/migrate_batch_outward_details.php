<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;

$columnsByTable = [
    'batches' => [
        'warehouse_name' => "VARCHAR(150) NULL AFTER `warehouse_id`",
        'packed_by_name' => "VARCHAR(150) DEFAULT 'System Administrator' AFTER `warehouse_name`",
        'rework_included' => 'TINYINT(1) DEFAULT 0 AFTER `packed_by_name`',
        'qr_codes' => 'LONGTEXT NULL AFTER `rework_included`',
        'taxable_value' => 'DECIMAL(12,2) DEFAULT 0.00 AFTER `qr_codes`',
        'gst_amount' => 'DECIMAL(12,2) DEFAULT 0.00 AFTER `taxable_value`',
        'total_amount' => 'DECIMAL(12,2) DEFAULT 0.00 AFTER `gst_amount`',
        'bill_no' => 'VARCHAR(100) NULL AFTER `total_amount`',
        'fabric_details' => "VARCHAR(255) DEFAULT 'GREEN / BIRDS' AFTER `bill_no`",
    ],
    'outwards' => [
        'warehouse_name' => "VARCHAR(150) DEFAULT 'wareouse (WH-01)' AFTER `warehouse_id`",
        'total_boxes' => 'INT DEFAULT 1 AFTER `warehouse_name`',
        'dispatched_by_name' => "VARCHAR(150) DEFAULT 'System Administrator' AFTER `gate_pass_no`",
        'notes' => 'TEXT NULL AFTER `dispatched_by_name`',
    ],
];

foreach ($columnsByTable as $table => $columns) {
    $tableExists = DB::fetchValue(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$table]
    );
    if (!$tableExists) {
        throw new RuntimeException("Required table '{$table}' does not exist.");
    }

    $existingColumns = array_column(DB::fetchAll("SHOW COLUMNS FROM `{$table}`"), 'Field');
    foreach ($columns as $column => $definition) {
        if (in_array($column, $existingColumns, true)) {
            echo "[SKIP] {$table}.{$column} already exists.\n";
            continue;
        }

        DB::query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        echo "[ADDED] {$table}.{$column}\n";
        $existingColumns[] = $column;
    }
}

echo "Batch and outward schema migration completed successfully.\n";
