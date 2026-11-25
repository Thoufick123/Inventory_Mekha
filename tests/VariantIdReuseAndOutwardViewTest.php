<?php
// tests/VariantIdReuseAndOutwardViewTest.php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Core\Auth;
use App\Services\VariantService;
use App\Services\StockService;
use App\Controllers\OutwardController;

Auth::startSession();

class VariantIdReuseAndOutwardViewTest
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
        echo "=== Running VariantIdReuseAndOutwardViewTest ===\n\n";

        $concern = DB::fetch("SELECT id FROM concerns WHERE status = 'active' ORDER BY id ASC LIMIT 1");
        $cid = (int)($concern['id'] ?? 1);
        $cat = DB::fetch("SELECT id FROM categories WHERE status = 'active' ORDER BY id ASC LIMIT 1");
        $catId = (int)($cat['id'] ?? 1);
        $sub = DB::fetch("SELECT id FROM subcategories WHERE category_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1", [$catId]);
        $subId = $sub ? (int)$sub['id'] : null;

        $uniqueName = 'VAR-REUSE-TEST-' . rand(1000, 9999);
        $createdIds = [];

        try {
            // 1. First creation -> generates new variant_id with product_code === (string)variant_id
            $res1 = VariantService::findOrCreate($cid, [
                'name' => $uniqueName,
                'category_id' => $catId,
                'subcategory_id' => $subId,
                'sub_sub_category_id' => null,
                'size' => 'M',
                'color' => 'Navy',
                'hsn_code' => '6109',
                'weight' => 0.25,
                'cost_price' => 200.00,
                'price' => 350.00,
                'mrp' => 499.00,
                'custom_attributes' => ['fit' => 'Regular'],
            ]);
            $vid1 = (int)$res1['variant_id'];
            $createdIds[] = $vid1;

            $this->assert($vid1 > 0, "Created initial variant ID #{$vid1}.");
            $this->assert($res1['reused'] === false, "First call created a new variant (reused = false).");
            $this->assert($res1['product_code'] === (string)$vid1, "product_code equals variant ID ('{$vid1}').");

            // Credit initial stock = 10
            StockService::move($cid, $vid1, 'Main Store', 10.0, 0.0, 'Test Stock', null, 'Initial');
            $qty1 = (float)DB::fetchValue("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = 'Main Store'", [$cid, $vid1]);
            $this->assert($qty1 === 10.0, "Initial stock quantity is 10.");

            // 2. Second entry with IDENTICAL details -> MUST reuse same variant_id and update qty (10 + 5 = 15)
            $res2 = VariantService::findOrCreate($cid, [
                'name' => ' ' . strtolower($uniqueName) . ' ',
                'category_id' => $catId,
                'subcategory_id' => $subId,
                'sub_sub_category_id' => null,
                'size' => 'm',
                'color' => 'navy',
                'hsn_code' => '6109',
                'weight' => 0.25,
                'cost_price' => 200.00,
                'price' => 350.00,
                'mrp' => 499.00,
                'custom_attributes' => ['Fit' => 'regular'],
            ]);
            $vid2 = (int)$res2['variant_id'];

            $this->assert($res2['reused'] === true, "Identical details reused existing variant (reused = true).");
            $this->assert($vid2 === $vid1, "Same variant ID #{$vid1} returned (no duplicate ID created).");

            StockService::move($cid, $vid2, 'Main Store', 5.0, 0.0, 'Test Stock', null, 'Second');
            $qty2 = (float)DB::fetchValue("SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = 'Main Store'", [$cid, $vid2]);
            $this->assert($qty2 === 15.0, "Stock quantity updated cleanly to old+new (10 + 5 = 15).");

            // 3. Third entry with CHANGED selling price -> MUST generate a NEW variant_id
            $res3 = VariantService::findOrCreate($cid, [
                'name' => $uniqueName,
                'category_id' => $catId,
                'subcategory_id' => $subId,
                'sub_sub_category_id' => null,
                'size' => 'M',
                'color' => 'Navy',
                'hsn_code' => '6109',
                'weight' => 0.25,
                'cost_price' => 200.00,
                'price' => 399.00, // Changed selling price!
                'mrp' => 499.00,
                'custom_attributes' => ['fit' => 'Regular'],
            ]);
            $vid3 = (int)$res3['variant_id'];
            $createdIds[] = $vid3;

            $this->assert($res3['reused'] === false, "Changed selling price generated a new variant (reused = false).");
            $this->assert($vid3 !== $vid1, "New variant ID #{$vid3} is distinct from #{$vid1}.");
            $this->assert($res3['product_code'] === (string)$vid3, "New variant product_code equals new variant ID ('{$vid3}').");

            // 4. Test OutwardController::traceability() returns valid JSON without SQL error
            $outward = DB::fetch("SELECT id, concern_id FROM outwards ORDER BY id DESC LIMIT 1");
            if ($outward) {
                $_SESSION['user'] = ['id' => 1, 'name' => 'Admin', 'role' => 'super_admin', 'concern_id' => null];
                $_GET['concern_id'] = (string)$outward['concern_id'];

                $oc = new OutwardController();
                ob_start();
                $oc->traceability((string)$outward['id']);
                $jsonOut = ob_get_clean();
                $decoded = json_decode((string)$jsonOut, true);

                $this->assert(is_array($decoded) && !empty($decoded['success']), "OutwardController::traceability() returned valid JSON with success=true.");
                $this->assert(array_key_exists('items', $decoded), "Traceability JSON includes items array.");
            }
        } finally {
            foreach ($createdIds as $id) {
                DB::query("DELETE FROM stock_ledger WHERE variant_id = ?", [$id]);
                DB::query("DELETE FROM stocks WHERE variant_id = ?", [$id]);
                DB::query("DELETE FROM variants WHERE id = ?", [$id]);
            }
        }

        echo "\n=== Results: {$this->passed} Passed, {$this->failed} Failed ===\n";
        if ($this->failed > 0) {
            exit(1);
        }
    }
}

$t = new VariantIdReuseAndOutwardViewTest();
$t->run();
