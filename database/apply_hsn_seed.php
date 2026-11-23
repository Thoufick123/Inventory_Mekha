<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;

require_once __DIR__ . '/hsn_gst_master.php';

$rows = hsnGstMasterRows();
$pdo = DB::pdo();

// 1. Build the SQL rows
$sqlValues = [];
$id = 1;
foreach ($rows as $r) {
    $cType = $pdo->quote($r['code_type']);
    $hCode = $pdo->quote($r['hsn_code']);
    $desc = $r['description'] !== null ? $pdo->quote($r['description']) : "NULL";
    $cat = $r['category'] !== null ? $pdo->quote($r['category']) : "NULL";
    $rate = $r['gst_rate'] !== null ? number_format((float)$r['gst_rate'], 2, '.', '') : "NULL";
    $note = $r['rate_note'] !== null ? $pdo->quote($r['rate_note']) : "NULL";

    $sqlValues[] = "({$id}, NULL, {$cType}, {$hCode}, {$desc}, {$cat}, {$rate}, {$note}, 1)";
    $id++;
}

$valuesString = implode(",\n", $sqlValues);

// 2. Standalone database/seed_hsn.sql
$standaloneSql = <<<SQL
-- ========================================================
-- INVENTO: HSN & SAC Master Feeds Seed (XAMPP invento_db)
-- Comprehensive Indian GST Textile, Garment, Fabric, Packaging,
-- Machinery, Maintenance & Job Work Classifications
-- Total records: 95
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `hsn_masters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concern_id` INT NULL,
    `code_type` ENUM('HSN', 'SAC') NOT NULL DEFAULT 'HSN',
    `hsn_code` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL,
    `category` VARCHAR(100) NULL,
    `gst_rate` DECIMAL(5,2) NULL DEFAULT NULL,
    `rate_note` VARCHAR(500) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_hsn_concern` (`concern_id`),
    INDEX `idx_hsn_code` (`hsn_code`),
    INDEX `idx_hsn_active_type` (`is_active`, `code_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clean existing global HSN feeds to ensure fresh standardized IDs 1-95
DELETE FROM `hsn_masters` WHERE `concern_id` IS NULL;

INSERT INTO `hsn_masters` (`id`, `concern_id`, `code_type`, `hsn_code`, `description`, `category`, `gst_rate`, `rate_note`, `is_active`) VALUES
{$valuesString}
ON DUPLICATE KEY UPDATE
    `code_type` = VALUES(`code_type`),
    `hsn_code` = VALUES(`hsn_code`),
    `description` = VALUES(`description`),
    `category` = VALUES(`category`),
    `gst_rate` = VALUES(`gst_rate`),
    `rate_note` = VALUES(`rate_note`),
    `is_active` = VALUES(`is_active`);

SET FOREIGN_KEY_CHECKS = 1;

SQL;

file_put_contents(__DIR__ . '/seed_hsn.sql', $standaloneSql);
echo "[OK] Generated database/seed_hsn.sql (95 records)\n";

// 3. Update database/seed.sql
$seedSqlFile = __DIR__ . '/seed.sql';
$seedSqlContent = file_get_contents($seedSqlFile);

// Remove any existing section 4 or insert fresh section 4
$hsnSectionMarker = "-- 4. HSN & SAC MASTER REGISTRY";
if (str_contains($seedSqlContent, $hsnSectionMarker)) {
    $seedSqlContent = preg_replace(
        '/-- 4\. HSN & SAC MASTER REGISTRY.*?(?=SET FOREIGN_KEY_CHECKS = 1;)/s',
        "",
        $seedSqlContent
    );
}

$hsnSectionSql = <<<SQL
-- 4. HSN & SAC MASTER REGISTRY
-- Comprehensive Indian GST Textile, Garment, Fabric, Packaging,
-- Machinery, Maintenance & Job Work Classifications (95 Feeds)
DELETE FROM `hsn_masters` WHERE `concern_id` IS NULL;

INSERT INTO `hsn_masters` (`id`, `concern_id`, `code_type`, `hsn_code`, `description`, `category`, `gst_rate`, `rate_note`, `is_active`) VALUES
{$valuesString}
ON DUPLICATE KEY UPDATE
    `code_type` = VALUES(`code_type`),
    `hsn_code` = VALUES(`hsn_code`),
    `description` = VALUES(`description`),
    `category` = VALUES(`category`),
    `gst_rate` = VALUES(`gst_rate`),
    `rate_note` = VALUES(`rate_note`),
    `is_active` = VALUES(`is_active`);

SQL;

// Insert before SET FOREIGN_KEY_CHECKS = 1;
if (str_contains($seedSqlContent, 'SET FOREIGN_KEY_CHECKS = 1;')) {
    $updatedSeedSql = str_replace(
        'SET FOREIGN_KEY_CHECKS = 1;',
        $hsnSectionSql . "\nSET FOREIGN_KEY_CHECKS = 1;",
        $seedSqlContent
    );
} else {
    $updatedSeedSql = $seedSqlContent . "\n" . $hsnSectionSql . "\nSET FOREIGN_KEY_CHECKS = 1;\n";
}

// Update header note in seed.sql
$updatedSeedSql = str_replace(
    "-- HSN/GST details and operational sequences are seeded by\n-- database/seed_live.php for environments that use PHP seeding.",
    "-- HSN/GST standard master details are included in section 4.\n-- Operational sequences are seeded by database/seed_live.php.",
    $updatedSeedSql
);

file_put_contents($seedSqlFile, $updatedSeedSql);
echo "[OK] Updated database/seed.sql with all 95 HSN feeds\n";

// 4. Apply directly to live XAMPP database (invento_db)
echo "[INFO] Applying HSN feeds to live database invento_db...\n";
DB::query("SET FOREIGN_KEY_CHECKS = 0;");
DB::query("DELETE FROM `hsn_masters` WHERE `concern_id` IS NULL;");

// Insert in chunks or all at once
$insertQuery = "INSERT INTO `hsn_masters` (`id`, `concern_id`, `code_type`, `hsn_code`, `description`, `category`, `gst_rate`, `rate_note`, `is_active`) VALUES\n" . $valuesString;
DB::query($insertQuery);
DB::query("SET FOREIGN_KEY_CHECKS = 1;");

$count = (int)DB::fetch("SELECT COUNT(*) as cnt FROM `hsn_masters` WHERE `is_active` = 1")['cnt'];
echo "[SUCCESS] Live database invento_db now has {$count} active HSN/SAC master feeds.\n";
