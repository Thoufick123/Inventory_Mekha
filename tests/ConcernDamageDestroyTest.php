<?php
// tests/ConcernDamageDestroyTest.php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Core\Auth;
use App\Core\Csrf;
use App\Controllers\DamageController;
use App\Services\DamageService;
use App\Services\StockService;

class ConcernDamageDestroyTest
{
    private int $passed = 0;
    private int $failed = 0;

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$message}\n";
        }
    }

    public function run(): void
    {
        echo "\n======================================================\n";
        echo "  TEST: CONCERN DAMAGE COMPLETE DELETE / DESTROY TEST \n";
        echo "======================================================\n\n";

        Auth::startSession();

        $admin = DB::fetch("SELECT * FROM users WHERE concern_id IS NULL AND status = 'active' LIMIT 1");
        if (!$admin) {
            $admin = DB::fetch("SELECT * FROM users WHERE status = 'active' LIMIT 1");
        }
        $_SESSION['user'] = $admin;
        $_SESSION['user_id'] = (int)$admin['id'];
        $_SESSION['concern_id'] = 4;
        $_SESSION['role_id'] = (int)$admin['role_id'];

        $concernId = 4;
        $location = 'Main Store';
        $damagedLocation = 'Main Store:DAMAGED';

        // 1. Create a test variant with initial stock
        $sku = 'TEST-DMG-DEL-' . time();
        DB::query(
            "INSERT INTO variants (concern_id, sku_code, name, cost_price, price, mrp, creation_date, status)
             VALUES (?, ?, ?, 100.00, 150.00, 200.00, CURDATE(), 'active')",
            [$concernId, $sku, 'Damage Delete Test Product']
        );
        $variantId = (int)DB::pdo()->lastInsertId();

        // Put 10 units in Main Store
        StockService::move($concernId, $variantId, $location, 10.0, 0.0, 'Production Inward', 1, 'Initial test stock', (int)$admin['id']);

        $initialAvail = (float)DB::fetchValue("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = ?", [$concernId, $variantId, $location]);
        $this->assert($initialAvail === 10.0, "Initial available stock is 10.0");

        // 2. Log damage of 2 units
        $damageLog = DamageService::log(
            $concernId,
            $variantId,
            'EXACT',
            $location,
            2.0,
            'Fabric tear during inspection',
            null,
            (int)$admin['id'],
            null,
            'MODERATE',
            'CONCERN',
            true
        );
        $damageId = (int)$damageLog['id'];
        $this->assert($damageId > 0, "Created concern damage record ID: {$damageId}");

        // Stock after logging damage: Available should be 8.0, Damaged should be 2.0
        $availAfterDamage = (float)DB::fetchValue("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = ?", [$concernId, $variantId, $location]);
        $quarantineStock = (float)DB::fetchValue("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = ?", [$concernId, $variantId, $damagedLocation]);
        $this->assert($availAfterDamage === 8.0, "Available stock decreased to 8.0 after damage log");
        $this->assert($quarantineStock === 2.0, "Quarantine damaged stock is 2.0");

        // 3. Call DamageController::destroy() to completely delete the damaged product quantity
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'csrf_token' => Csrf::token(),
            'id' => (string)$damageId,
        ];

        ob_start();
        $controller = new DamageController();
        $controller->destroy();
        ob_end_clean();

        // 4. Verify damage record is completely deleted from concern_damages
        $damageRecord = DB::fetch("SELECT * FROM concern_damages WHERE id = ?", [$damageId]);
        $this->assert(empty($damageRecord), "Damage record #{$damageId} was completely deleted from concern_damages");

        // 5. Verify quarantine stock was completely deleted/removed (not restored)
        $quarantineAfterDestroy = (float)(DB::fetchValue("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = ?", [$concernId, $variantId, $damagedLocation]) ?: 0.0);
        $this->assert($quarantineAfterDestroy === 0.0, "Damaged quarantine stock is 0.0 (completely deleted)");

        // 6. Verify available stock was NOT increased back (the 2 units are gone completely)
        $availAfterDestroy = (float)DB::fetchValue("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = ?", [$concernId, $variantId, $location]);
        $this->assert($availAfterDestroy === 8.0, "Available stock remained at 8.0 (product quantity was deleted completely, NOT restored)");

        // 7. Verify view HTML has the delete action button
        $viewPath = __DIR__ . '/../app/views/concern/concern_damage.php';
        $viewHtml = file_get_contents($viewPath);
        $this->assert(strpos($viewHtml, '/m/concern_damage/destroy') !== false, "concern_damage.php includes form posting to /m/concern_damage/destroy");
        $this->assert(strpos($viewHtml, 'title="Delete completely (discard stock)"') !== false, "concern_damage.php includes Delete completely button");
        $this->assert(strpos($viewHtml, '<i class="bi bi-trash"></i>') !== false, "concern_damage.php includes bi-trash icon for delete option");

        // Cleanup test data
        DB::query("DELETE FROM stock_ledger WHERE variant_id = ?", [$variantId]);
        DB::query("DELETE FROM stocks WHERE variant_id = ?", [$variantId]);
        DB::query("DELETE FROM variants WHERE id = ?", [$variantId]);

        echo "\n======================================================\n";
        echo "  SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }
}

(new ConcernDamageDestroyTest())->run();
