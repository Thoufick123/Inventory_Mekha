<?php
// database/sync_all_modules.php
// Ensures all system modules exist, are enabled for all concerns, and granted to concern admin roles.

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\DB;

echo "======================================================\n";
echo "INVENTO: Master Modules & Concern Permissions Sync\n";
echo "======================================================\n\n";

// 1. Define complete master module registry (4 Super Admin + 25 Concern modules)
$masterModules = [
    // Super Admin Modules (4)
    ['code' => 'admin_dashboard',  'name' => 'Dashboard',            'icon' => 'bi-speedometer2',     'sort' => 1,  'group' => 'Super Admin', 'admin_only' => 1],
    ['code' => 'admin_concerns',   'name' => 'Concern Management',   'icon' => 'bi-buildings',        'sort' => 2,  'group' => 'Super Admin', 'admin_only' => 1],
    ['code' => 'admin_assets',     'name' => 'Asset Management',     'icon' => 'bi-building-gear',   'sort' => 3,  'group' => 'Super Admin', 'admin_only' => 1],
    ['code' => 'admin_reports',    'name' => 'Consolidated Reports', 'icon' => 'bi-bar-chart-line',  'sort' => 4,  'group' => 'Super Admin', 'admin_only' => 1],

    // Concern Operational Modules (25)
    ['code' => 'concern_dashboard', 'name' => 'Concern Dashboard',   'icon' => 'bi-speedometer',      'sort' => 10, 'group' => 'Dashboard',   'admin_only' => 0],
    ['code' => 'suppliers',         'name' => 'Supplier',            'icon' => 'bi-truck',            'sort' => 20, 'group' => 'Purchase',    'admin_only' => 0],
    ['code' => 'purchase_billing',  'name' => 'Purchase Billing',     'icon' => 'bi-receipt',          'sort' => 21, 'group' => 'Purchase',    'admin_only' => 0],
    ['code' => 'purchase_stocks',   'name' => 'Purchase Stocks',      'icon' => 'bi-boxes',            'sort' => 22, 'group' => 'Purchase',    'admin_only' => 0],
    ['code' => 'purchase_damage',   'name' => 'Purchase Damage',      'icon' => 'bi-shield-x',         'sort' => 23, 'group' => 'Purchase',    'admin_only' => 0],
    ['code' => 'catalog_mapping',   'name' => 'Catalog Mapping',      'icon' => 'bi-diagram-3-fill',   'sort' => 24, 'group' => 'Purchase',    'admin_only' => 0],
    ['code' => 'product_adding',    'name' => 'Product Adding',       'icon' => 'bi-plus-square-dotted', 'sort' => 25, 'group' => 'Purchase',  'admin_only' => 0],
    ['code' => 'catalog_program',   'name' => 'Catalog & Program',   'icon' => 'bi-journal-bookmark', 'sort' => 30, 'group' => 'Production',  'admin_only' => 0],
    ['code' => 'cutting_wastage',   'name' => 'Cutting & Wastage',    'icon' => 'bi-scissors',         'sort' => 31, 'group' => 'Production',  'admin_only' => 0],
    ['code' => 'stitching_wastage', 'name' => 'Stitching & Wastage',  'icon' => 'bi-layers',           'sort' => 32, 'group' => 'Production',  'admin_only' => 0],
    ['code' => 'label_generation',  'name' => 'Label Generation',     'icon' => 'bi-qr-code',          'sort' => 33, 'group' => 'Production',  'admin_only' => 0],
    ['code' => 'qc_stocks',         'name' => 'QC Stocks',            'icon' => 'bi-shield-check',     'sort' => 34, 'group' => 'Production',  'admin_only' => 0],
    ['code' => 'inward',            'name' => 'Inward',               'icon' => 'bi-box-arrow-in-down','sort' => 40, 'group' => 'Stock',       'admin_only' => 0],
    ['code' => 'stocks',            'name' => 'Stocks',               'icon' => 'bi-boxes',            'sort' => 41, 'group' => 'Stock',       'admin_only' => 0],
    ['code' => 'concern_damage',    'name' => 'Concern Damage',       'icon' => 'bi-x-octagon',        'sort' => 42, 'group' => 'Stock',       'admin_only' => 0],
    ['code' => 'return_exchange',   'name' => 'Return & Exchange',    'icon' => 'bi-arrow-left-right', 'sort' => 43, 'group' => 'Stock',       'admin_only' => 0],
    ['code' => 'customers',         'name' => 'Customer Details',     'icon' => 'bi-people',           'sort' => 50, 'group' => 'Shop',        'admin_only' => 0],
    ['code' => 'billing',           'name' => 'POS Billing',          'icon' => 'bi-calculator',       'sort' => 51, 'group' => 'Shop',        'admin_only' => 0],
    ['code' => 'request',           'name' => 'Request',              'icon' => 'bi-file-earmark-text','sort' => 52, 'group' => 'Sales',       'admin_only' => 0],
    ['code' => 'batch',             'name' => 'Batch',                'icon' => 'bi-collection',       'sort' => 53, 'group' => 'Sales',       'admin_only' => 0],
    ['code' => 'outward',           'name' => 'Outward',              'icon' => 'bi-box-arrow-up-right','sort' => 54, 'group' => 'Sales',      'admin_only' => 0],
    ['code' => 'dispatch',          'name' => 'Dispatch',             'icon' => 'bi-send',             'sort' => 55, 'group' => 'Sales',       'admin_only' => 0],
    ['code' => 'expense',           'name' => 'Expense',              'icon' => 'bi-wallet2',          'sort' => 60, 'group' => 'Finance',     'admin_only' => 0],
    ['code' => 'cash',              'name' => 'Cash',                 'icon' => 'bi-cash-coin',        'sort' => 61, 'group' => 'Finance',     'admin_only' => 0],
    ['code' => 'cash_flow',         'name' => 'Cash & Cash Flow',     'icon' => 'bi-cash-coin',        'sort' => 62, 'group' => 'Finance',     'admin_only' => 0],
    ['code' => 'staff',             'name' => 'Staff',                'icon' => 'bi-people-fill',      'sort' => 90, 'group' => 'Staff',       'admin_only' => 0],
];

// Step 1: Insert or update all modules in modules table
echo "1. Registering/Updating Master Modules Table...\n";
foreach ($masterModules as $m) {
    DB::query(
        "INSERT INTO modules (code, name, icon, sort_order, group_name, is_super_admin_only)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), icon = VALUES(icon), sort_order = VALUES(sort_order), group_name = VALUES(group_name), is_super_admin_only = VALUES(is_super_admin_only)",
        [$m['code'], $m['name'], $m['icon'], $m['sort'], $m['group'], $m['admin_only']]
    );
}
echo "  [OK] Master modules table synced (" . count($masterModules) . " modules).\n\n";

// Step 2: Fetch all active concerns and all concern-level modules
$concerns = DB::fetchAll("SELECT id, name, code FROM concerns");
$concernModuleRows = DB::fetchAll("SELECT id, code, name FROM modules WHERE is_super_admin_only = 0");

echo "2. Synchronizing concern_modules for all concerns...\n";
foreach ($concerns as $c) {
    $cid = (int)$c['id'];
    echo "  -> Concern #{$cid}: {$c['name']}\n";
    foreach ($concernModuleRows as $m) {
        $mid = (int)$m['id'];
        DB::query(
            "INSERT INTO concern_modules (concern_id, module_id, enabled) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE enabled = 1",
            [$cid, $mid]
        );
    }
}
echo "  [OK] All concern modules enabled for all active concerns.\n\n";

// Step 3: Grant permissions in role_permissions to Concern Administrator roles
echo "3. Synchronizing role_permissions for Concern Administrator roles...\n";
$adminRoles = DB::fetchAll("SELECT id, concern_id, name FROM roles WHERE name LIKE '%Administrator%' OR name LIKE '%Admin%'");
foreach ($adminRoles as $r) {
    $rid = (int)$r['id'];
    echo "  -> Role #{$rid}: {$r['name']} (Concern: {$r['concern_id']})\n";
    foreach ($concernModuleRows as $m) {
        $mid = (int)$m['id'];
        DB::query(
            "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
             VALUES (?, ?, 1, 1, 1, 1)
             ON DUPLICATE KEY UPDATE can_view = 1, can_add = 1, can_edit = 1, can_delete = 1",
            [$rid, $mid]
        );
    }
}
echo "  [OK] Full permissions assigned to administrator roles.\n\n";

echo "======================================================\n";
echo ">>> MASTER MODULES & PERMISSIONS SYNC COMPLETE! <<<\n";
echo "======================================================\n";
