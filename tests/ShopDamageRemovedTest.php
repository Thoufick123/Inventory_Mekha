<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Core\ModuleRegistry;
use App\Core\Auth;
use App\Controllers\ConcernDashboardController;

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

echo "=== Shop Damage Module Removal Verification Test ===\n";

// 1. Check ModuleRegistry
$registryModules = ModuleRegistry::masterModules();
$registryCodes = array_column($registryModules, 'code');
assertCondition(!in_array('shop_damage', $registryCodes, true), "ModuleRegistry::masterModules() does not contain 'shop_damage'");

$shopGroupModules = array_filter($registryModules, fn($m) => ($m['group_name'] ?? '') === 'Shop');
$shopCodes = array_column($shopGroupModules, 'code');
assertCondition($shopCodes === ['customers', 'billing'], "Shop group in ModuleRegistry only contains 'customers' and 'billing'");

// 2. Check Database modules table
$dbModule = DB::fetch("SELECT * FROM modules WHERE code = 'shop_damage'");
assertCondition(empty($dbModule), "Database table 'modules' does not contain 'shop_damage'");

// 3. Check Database concern_modules
$dbConcernModules = DB::fetchAll("SELECT cm.* FROM concern_modules cm JOIN modules m ON cm.module_id = m.id WHERE m.code = 'shop_damage'");
assertCondition(empty($dbConcernModules), "Database table 'concern_modules' has no records for 'shop_damage'");

// 4. Test Layout Rendering
Auth::startSession();
$concernUser = DB::fetch("SELECT u.* FROM users u WHERE u.status = 'active' LIMIT 1");
if ($concernUser) {
    $_SESSION['user_id'] = $concernUser['id'];
    $_SESSION['role'] = 'super_admin';
    $_SESSION['role_id'] = $concernUser['role_id'];
    $_SESSION['concern_id'] = $concernUser['concern_id'];
    $_SESSION['user'] = $concernUser;

    $controller = new ConcernDashboardController();
    ob_start();
    $controller->index();
    $html = ob_get_clean();

    assertCondition(!str_contains($html, 'Shop Damage'), "Sidebar does not render 'Shop Damage'");
    assertCondition(!str_contains($html, '/shop/damages'), "Sidebar does not contain '/shop/damages' link");
}

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
