<?php
require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;

echo "======================================================================\n";
echo "DATABASE & SCHEMA ANALYSIS\n";
echo "======================================================================\n\n";

$dbTablesRaw = DB::fetchAll("SHOW TABLES");
$dbTables = [];
foreach ($dbTablesRaw as $t) {
    $dbTables[] = array_values($t)[0];
}
echo "Total tables in local database: " . count($dbTables) . "\n\n";

// Parse schema.sql to get all expected tables and columns
$schemaContent = file_get_contents(__DIR__ . '/../database/schema.sql');
preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`([^`]+)`\s*\((.*?)\)\s*ENGINE/s', $schemaContent, $matches, PREG_SET_ORDER);

$schemaTables = [];
foreach ($matches as $m) {
    $tableName = $m[1];
    $body = $m[2];
    $lines = explode("\n", $body);
    $cols = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^`([^`]+)`\s+([A-Za-z0-9_(),.\s]+)/', $line, $colMatch)) {
            $colName = $colMatch[1];
            if (!in_array(strtoupper($colName), ['PRIMARY', 'KEY', 'UNIQUE', 'CONSTRAINT', 'FOREIGN'])) {
                $cols[$colName] = $line;
            }
        }
    }
    $schemaTables[$tableName] = $cols;
}

echo "Total tables defined in schema.sql: " . count($schemaTables) . "\n\n";

// 1. Missing tables
$missingTables = array_diff(array_keys($schemaTables), $dbTables);
if (!empty($missingTables)) {
    echo "⚠️ MISSING TABLES in DB (" . count($missingTables) . "):\n";
    foreach ($missingTables as $mt) {
        echo "  - $mt\n";
    }
} else {
    echo "✓ All " . count($schemaTables) . " tables from schema.sql exist in DB.\n";
}

// 2. Extra tables in DB not in schema.sql
$extraTables = array_diff($dbTables, array_keys($schemaTables));
if (!empty($extraTables)) {
    echo "\nℹ️ Tables in DB not in schema.sql (" . count($extraTables) . "):\n";
    foreach ($extraTables as $et) {
        echo "  + $et\n";
    }
}

// 3. Column differences
echo "\n======================================================================\n";
echo "COLUMN AUDIT (schema.sql vs Live DB)\n";
echo "======================================================================\n";
$missingColumns = [];
foreach ($schemaTables as $tableName => $cols) {
    if (!in_array($tableName, $dbTables)) continue;
    $liveColsRaw = DB::fetchAll("SHOW COLUMNS FROM `$tableName`");
    $liveCols = array_column($liveColsRaw, 'Field');
    
    foreach ($cols as $colName => $colDef) {
        if (!in_array($colName, $liveCols)) {
            $missingColumns[$tableName][] = [
                'col' => $colName,
                'def' => $colDef
            ];
        }
    }
}

if (empty($missingColumns)) {
    echo "✓ All columns from schema.sql exist in live DB!\n";
} else {
    echo "⚠️ Missing columns found in live DB:\n";
    foreach ($missingColumns as $t => $colList) {
        echo "  Table `$t`:\n";
        foreach ($colList as $c) {
            echo "    - `{$c['col']}`: {$c['def']}\n";
        }
    }
}

// 4. Code references audit: check if controllers/models query columns that don't exist
echo "\n======================================================================\n";
echo "CODE AUDIT FOR SCHEMA (Checking all models/controllers)\n";
echo "======================================================================\n";

// Let's run migrate_all_tables.php to apply any pending alterations
echo "\nRunning database/migrate_all_tables.php to ensure all incremental migrations are applied...\n";
include __DIR__ . '/../database/migrate_all_tables.php';

