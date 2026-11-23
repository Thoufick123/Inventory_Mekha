<?php
// tests/HsnCatalogMappingCrudTest.php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Core\Auth;
use App\Controllers\CatalogMappingController;

// Start session before any output
Auth::startSession();

class HsnCatalogMappingCrudTest
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
        echo "=== Running HsnCatalogMappingCrudTest ===\n\n";

        $csrf = Auth::csrfToken();

        // Mock superadmin user (Super Admin has concern_id === null)
        $_SESSION['user'] = [
            'id' => 1,
            'name' => 'Test Super Admin',
            'role' => 'super_admin',
            'is_super_admin' => 1,
            'concern_id' => null,
        ];
        $_SESSION['csrf_token'] = $csrf;

        $testCode = 'TEST' . rand(1000, 9999);
        $testSacCode = 'SAC' . rand(1000, 9999);

        try {
            // Clean up any previous test leftovers
            DB::query("DELETE FROM hsn_masters WHERE hsn_code LIKE 'TEST%' OR hsn_code LIKE 'SAC%'");

            $controller = new CatalogMappingController();

            // 1. Test Create HSN Code (Global)
            echo "\n1. Test Create HSN (Global)\n";
            $_POST = [
                'csrf_token' => $csrf,
                'code_type' => 'HSN',
                'hsn_code' => $testCode,
                'category' => 'Testing Category',
                'gst_rate' => '12.00',
                'rate_note' => 'Test note for HSN',
                'description' => 'Test HSN description',
                'scope' => 'global',
                'return_url' => '/production/catalog-mapping?tab=hierarchy',
            ];

            $controller->createHsn();

            $created = DB::fetch("SELECT * FROM hsn_masters WHERE hsn_code = ?", [$testCode]);
            $this->assert($created !== false && !empty($created['id']), "HSN master code '{$testCode}' was inserted into database.");
            $this->assert($created['code_type'] === 'HSN', "code_type is 'HSN'.");
            $this->assert((float)$created['gst_rate'] === 12.0, "gst_rate is 12.00.");
            $this->assert($created['category'] === 'Testing Category', "category is 'Testing Category'.");
            $this->assert($created['concern_id'] === null, "Global scope sets concern_id to NULL.");
            $this->assert((int)$created['is_active'] === 1, "is_active is 1.");

            $createdId = (int)$created['id'];

            // 2. Test Create SAC Code (Concern-Scoped)
            echo "\n2. Test Create SAC (Concern-Scoped)\n";
            $_POST = [
                'csrf_token' => $csrf,
                'code_type' => 'SAC',
                'hsn_code' => $testSacCode,
                'category' => 'Job Work Services',
                'gst_rate' => '5.00',
                'rate_note' => '5% for textile stitching',
                'description' => 'Stitching and tailoring services',
                'scope' => 'concern',
                'concern_id' => '1',
                'return_url' => '/production/catalog-mapping?tab=hierarchy',
            ];

            $controller->createHsn();

            $createdSac = DB::fetch("SELECT * FROM hsn_masters WHERE hsn_code = ?", [$testSacCode]);
            $this->assert($createdSac !== false && !empty($createdSac['id']), "SAC master code '{$testSacCode}' was inserted.");
            $this->assert($createdSac['code_type'] === 'SAC', "code_type is 'SAC'.");
            $this->assert((int)$createdSac['concern_id'] === 1, "concern_id is 1 for concern-scoped SAC.");

            // 3. Test Duplicate Code Prevention
            echo "\n3. Test Duplicate Code Prevention\n";
            $_POST = [
                'csrf_token' => $csrf,
                'code_type' => 'HSN',
                'hsn_code' => $testCode,
                'scope' => 'global',
                'return_url' => '/production/catalog-mapping?tab=hierarchy',
            ];

            $controller->createHsn();

            $count = (int)DB::fetchValue("SELECT COUNT(*) FROM hsn_masters WHERE hsn_code = ? AND code_type = 'HSN'", [$testCode]);
            $this->assert($count === 1, "Duplicate active HSN code was rejected (count remains 1).");

            // 4. Test Update HSN Code
            echo "\n4. Test Update HSN\n";
            $_POST = [
                'csrf_token' => $csrf,
                'id' => (string)$createdId,
                'code_type' => 'HSN',
                'hsn_code' => $testCode,
                'category' => 'Updated Category',
                'gst_rate' => '18.00',
                'rate_note' => 'Updated note 18%',
                'description' => 'Updated description text',
                'is_active' => '1',
                'scope' => 'global',
                'return_url' => '/production/catalog-mapping?tab=hierarchy',
            ];

            $controller->updateHsn();

            $updated = DB::fetch("SELECT * FROM hsn_masters WHERE id = ?", [$createdId]);
            $this->assert((float)$updated['gst_rate'] === 18.0, "Updated gst_rate to 18.00.");
            $this->assert($updated['category'] === 'Updated Category', "Updated category to 'Updated Category'.");
            $this->assert($updated['description'] === 'Updated description text', "Updated description.");

            // 5. Test Delete HSN Code (Unreferenced -> Hard Delete)
            echo "\n5. Test Delete HSN (Unreferenced)\n";
            $_POST = [
                'csrf_token' => $csrf,
                'id' => (string)$createdSac['id'],
                'return_url' => '/production/catalog-mapping?tab=hierarchy',
            ];

            $controller->deleteHsn();

            $checkDeleted = DB::fetch("SELECT * FROM hsn_masters WHERE id = ?", [$createdSac['id']]);
            $this->assert(empty($checkDeleted), "Unreferenced SAC code was permanently deleted.");

            // 6. Test Delete HSN Code (Referenced in Categories -> Soft Delete / Deactivate)
            echo "\n6. Test Delete HSN (Referenced in Categories)\n";
            $testCat = DB::fetch("SELECT id FROM categories LIMIT 1");
            if ($testCat) {
                DB::query("UPDATE categories SET default_hsn = ? WHERE id = ?", [$testCode, $testCat['id']]);

                $_POST = [
                    'csrf_token' => $csrf,
                    'id' => (string)$createdId,
                    'return_url' => '/production/catalog-mapping?tab=hierarchy',
                ];

                $controller->deleteHsn();

                $checkSoftDeleted = DB::fetch("SELECT * FROM hsn_masters WHERE id = ?", [$createdId]);
                $this->assert($checkSoftDeleted !== false, "Referenced HSN record still exists in database.");
                $this->assert((int)$checkSoftDeleted['is_active'] === 0, "Referenced HSN record was deactivated (is_active = 0).");

                // Clean up category reference
                DB::query("UPDATE categories SET default_hsn = NULL WHERE id = ?", [$testCat['id']]);
            }

            // 7. Test deleteHierarchy with type='hsn'
            echo "\n7. Test deleteHierarchy fallback for HSN\n";
            $_POST = [
                'csrf_token' => $csrf,
                'type' => 'hsn',
                'id' => (string)$createdId,
                'return_url' => '/production/catalog-mapping?tab=hierarchy',
            ];

            $controller->deleteHierarchy();

            $finalCheck = DB::fetch("SELECT * FROM hsn_masters WHERE id = ?", [$createdId]);
            $this->assert(empty($finalCheck), "deleteHierarchy permanently deleted unreferenced HSN code.");

        } finally {
            // Clean up
            DB::query("DELETE FROM hsn_masters WHERE hsn_code LIKE 'TEST%' OR hsn_code LIKE 'SAC%'");
        }

        echo "\n=== Test Results: {$this->passed} Passed, {$this->failed} Failed ===\n";
        if ($this->failed > 0) {
            exit(1);
        }
    }
}

$test = new HsnCatalogMappingCrudTest();
$test->run();
