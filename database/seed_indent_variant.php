<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;

$v = DB::fetch('SELECT id FROM variants WHERE sku_code = ?', ['SKU-CAT-MEKHA-0003-L']);
if (!$v) {
    DB::query(
        "INSERT INTO variants (concern_id, sku_code, name, size, price, mrp, creation_date, status) 
         VALUES (4, 'SKU-CAT-MEKHA-0003-L', 'ICECREAM', 'L', 200.00, 400.00, CURDATE(), 'active')"
    );
    $vid = (int)DB::lastInsertId();
    DB::query(
        "INSERT INTO stocks (concern_id, variant_id, location, qty, updated_at) 
         VALUES (5, ?, 'Central Warehouse', 25.00, NOW()), (4, ?, 'Main Store', 10.00, NOW())",
        [$vid, $vid]
    );
    echo "Seeded variant ICECREAM with id: {$vid}\n";
} else {
    echo "Variant already exists: {$v['id']}\n";
}
