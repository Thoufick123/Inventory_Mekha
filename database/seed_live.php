<?php
// Idempotent production baseline seeding. This script never deletes live records.
declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;

echo "========================================================\n";
echo "INVENTO: Live Database Baseline Seed\n";
echo "========================================================\n\n";

require_once __DIR__ . '/hsn_gst_master.php';
ensureHsnGstMaster();
echo "[OK] HSN/SAC master ensured\n";

include __DIR__ . '/sync_all_modules.php';
include __DIR__ . '/sync_concern_admin_staff.php';

$concerns = DB::fetchAll("SELECT id, code, name FROM concerns WHERE status = 'active' ORDER BY id");

foreach ($concerns as $concern) {
    $cid = (int)$concern['id'];
    $code = (string)$concern['code'];

    $productionUnit = DB::fetch(
        "SELECT id FROM production_units WHERE concern_id = ? LIMIT 1",
        [$cid]
    );
    if (!$productionUnit) {
        $unitCode = substr(preg_replace('/[^A-Z0-9]+/', '-', strtoupper($code)), 0, 30) . '-PU-001';
        $unitCode = trim($unitCode, '-');
        DB::query(
            "INSERT INTO production_units
                (concern_id, code, name, location, capacity_per_day, is_active, created_at)
             VALUES (?, ?, 'Main Production Unit', 'Main Production Floor', 0, 1, NOW())",
            [$cid, $unitCode]
        );
    }

    $cashAccount = DB::fetch(
        "SELECT id FROM cash_accounts WHERE concern_id = ? AND account_type = 'cash' AND status = 'active' LIMIT 1",
        [$cid]
    );
    if (!$cashAccount) {
        DB::query(
            "INSERT INTO cash_accounts
                (concern_id, name, account_type, account_number, balance, status, created_at)
             VALUES (?, 'Main Cash', 'cash', NULL, 0.00, 'active', NOW())",
            [$cid]
        );
    }

    foreach ([
        'Raw Materials',
        'Packing',
        'Transport',
        'Wages',
        'Electricity',
        'Maintenance',
        'Office',
        'Other',
    ] as $expenseCategory) {
        DB::query(
            "INSERT INTO expense_categories (concern_id, name, status, created_at)
             VALUES (?, ?, 'active', NOW())
             ON DUPLICATE KEY UPDATE status = 'active'",
            [$cid, $expenseCategory]
        );
    }

    foreach ([
        'PO' => 'PO-',
        'INWARD' => 'IN-',
        'DAMAGE' => 'DMG-',
        'REQ' => 'REQ-',
        'INVOICE' => 'INV-',
        'OUTWARD' => 'OUT-',
        'STAFF' => 'STF-',
    ] as $docType => $prefix) {
        DB::query(
            "INSERT INTO doc_series (concern_id, doc_type, prefix, next_no)
             VALUES (?, ?, ?, 1001)
             ON DUPLICATE KEY UPDATE prefix = VALUES(prefix)",
            [$cid, $docType, $prefix]
        );
    }

    echo "[OK] Concern #{$cid} baseline operational records ensured\n";
}

echo "\n========================================================\n";
echo "SUCCESS: Live database baseline seed completed\n";
echo "========================================================\n";
