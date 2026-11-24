<?php
require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;

echo "--- Running Cutting & Wastage Schema Migration ---\n";

// 1. cutting_masters
DB::query("
CREATE TABLE IF NOT EXISTS cutting_masters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    concern_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    employee_code VARCHAR(50) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cm_concern (concern_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
echo "[OK] cutting_masters table created/verified.\n";

// 2. Add columns to cutting_logs if not exists
$existingCols = array_column(DB::fetchAll("DESCRIBE cutting_logs"), 'Field');

$colsToAdd = [
    'batch_no' => "VARCHAR(50) NULL AFTER log_no",
    'lot_no' => "VARCHAR(50) NULL AFTER batch_no",
    'cutting_name' => "VARCHAR(255) NULL AFTER lot_no",
    'status' => "ENUM('in_progress', 'planned', 'completed') DEFAULT 'in_progress' AFTER cutting_name",
    'cutting_master_id' => "INT NULL AFTER status",
    'cutting_master_name' => "VARCHAR(150) NULL AFTER cutting_master_id",
    'total_roll_weight' => "DECIMAL(12,2) DEFAULT 0.00 AFTER consumed_qty",
    'total_roll_price' => "DECIMAL(12,2) DEFAULT 0.00 AFTER total_roll_weight",
    'fabric_rate_per_kg' => "DECIMAL(12,2) DEFAULT 0.00 AFTER total_roll_price",
    'wastage_cost' => "DECIMAL(12,2) DEFAULT 0.00 AFTER wastage_qty",
    'wastage_percent' => "DECIMAL(5,2) DEFAULT 0.00 AFTER wastage_cost",
    'used_weight' => "DECIMAL(12,2) DEFAULT 0.00 AFTER wastage_percent",
    'fabric_cost_per_piece' => "DECIMAL(12,2) DEFAULT 0.00 AFTER used_weight",
    'sold_wastage_qty' => "DECIMAL(12,2) DEFAULT 0.00 AFTER wastage_unit",
    'updated_at' => "TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
];

foreach ($colsToAdd as $col => $def) {
    if (!in_array($col, $existingCols)) {
        DB::query("ALTER TABLE cutting_logs ADD COLUMN $col $def");
        echo "[ADD] Column '$col' added to cutting_logs.\n";
    } else {
        echo "[EXISTS] Column '$col' already in cutting_logs.\n";
    }
}

// Make raw_variant_id NULLable if needed
DB::query("ALTER TABLE cutting_logs MODIFY COLUMN raw_variant_id INT NULL");
echo "[OK] raw_variant_id modified to NULLable.\n";

// 3. cutting_items table
DB::query("
CREATE TABLE IF NOT EXISTS cutting_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cutting_id INT NOT NULL,
    program_roll_id INT NULL,
    variant_id INT NULL,
    size VARCHAR(50) NULL,
    sku_code VARCHAR(100) NULL,
    variant_name VARCHAR(255) NULL,
    cut_pieces INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ci_cutting (cutting_id),
    KEY idx_ci_roll (program_roll_id),
    KEY idx_ci_variant (variant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
echo "[OK] cutting_items table created/verified.\n";

// 4. cutting_wastage_sales table
DB::query("
CREATE TABLE IF NOT EXISTS cutting_wastage_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    concern_id INT NOT NULL,
    sale_no VARCHAR(50) NOT NULL,
    cutting_id INT NOT NULL,
    buyer_name VARCHAR(150) NOT NULL,
    buyer_phone VARCHAR(20) NOT NULL,
    buyer_company VARCHAR(200) NULL,
    wastage_category VARCHAR(100) NOT NULL,
    wastage_weight DECIMAL(12,2) NOT NULL,
    selling_rate DECIMAL(12,2) NOT NULL,
    total_price DECIMAL(12,2) NOT NULL,
    amount_received DECIMAL(12,2) DEFAULT 0.00,
    sale_date DATE NOT NULL,
    payment_status ENUM('paid', 'partial', 'pending') DEFAULT 'paid',
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cws_concern (concern_id),
    KEY idx_cws_cutting (cutting_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
echo "[OK] cutting_wastage_sales table created/verified.\n";

echo "--- Schema Migration Completed Successfully ---\n";
