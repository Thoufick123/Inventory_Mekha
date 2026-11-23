<?php
// tests/HsnSeedDataTest.php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;

require_once __DIR__ . '/../database/hsn_gst_master.php';

class HsnSeedDataTest {
    private int $passed = 0;
    private int $failed = 0;

    private function assert(bool $condition, string $message): void {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$message}\n";
        }
    }

    public function run(): void {
        echo "\n======================================================\n";
        echo "  TEST: HSN MASTER SEED DATA INVENTO_DB TEST           \n";
        echo "======================================================\n\n";

        // 1. Verify live database invento_db
        echo "1. Checking live database invento_db hsn_masters table...\n";
        $dbRows = DB::fetchAll("SELECT * FROM hsn_masters WHERE is_active = 1 ORDER BY id ASC");
        $this->assert(count($dbRows) === 95, "Live database invento_db has exactly 95 active HSN/SAC master rows (found " . count($dbRows) . ")");
        $this->assert((int)$dbRows[0]['id'] === 1, "First record ID is 1");
        $this->assert((int)end($dbRows)['id'] === 95, "Last record ID is 95");

        // 2. Check essential codes
        echo "\n2. Checking essential codes in database...\n";
        $byCode = [];
        foreach ($dbRows as $r) {
            $byCode[$r['code_type'] . ':' . $r['hsn_code']] = $r;
        }

        $expectedCodes = [
            'HSN:5201' => 'Raw cotton',
            'HSN:5208' => 'Woven cotton fabrics',
            'HSN:6109' => 'T-shirts, singlets',
            'HSN:6111' => "Babies' garments",
            'HSN:6203' => "Men's or boys' suits",
            'HSN:6204' => "Women's or girls' suits",
            'HSN:6301' => 'Blankets',
            'HSN:6302' => 'Bed linen',
            'HSN:8452' => 'Sewing machines',
            'HSN:9606' => 'Buttons',
            'HSN:9607' => 'Slide fasteners',
            'SAC:9987' => 'Maintenance, repair',
            'SAC:998821' => 'Textile and apparel job work',
        ];

        foreach ($expectedCodes as $code => $label) {
            $this->assert(isset($byCode[$code]), "Master code {$code} ({$label}) exists in invento_db");
        }

        // Check conditional rate note for garments
        $this->assert(empty($byCode['HSN:6109']['gst_rate']), "HSN 6109 garment GST rate is null (conditional by price)");
        $this->assert(str_contains($byCode['HSN:6109']['rate_note'], 'conditional'), "HSN 6109 has conditional rate note");
        $this->assert((float)$byCode['HSN:8452']['gst_rate'] === 18.00, "HSN 8452 machinery rate is 18%");

        // 3. Verify seed files exist and contain HSN data
        echo "\n3. Checking database/seed.sql and database/seed_hsn.sql files...\n";
        $seedSqlPath = dirname(__DIR__) . '/database/seed.sql';
        $seedHsnPath = dirname(__DIR__) . '/database/seed_hsn.sql';

        $this->assert(file_exists($seedSqlPath), "database/seed.sql exists");
        $this->assert(file_exists($seedHsnPath), "database/seed_hsn.sql exists");

        $seedSql = file_get_contents($seedSqlPath);
        $seedHsn = file_get_contents($seedHsnPath);

        $this->assert(str_contains($seedSql, '-- 4. HSN & SAC MASTER REGISTRY'), "database/seed.sql contains Section 4 HSN & SAC MASTER REGISTRY");
        $this->assert(str_contains($seedSql, "'5201'"), "database/seed.sql contains HSN 5201");
        $this->assert(str_contains($seedSql, "'6109'"), "database/seed.sql contains HSN 6109");
        $this->assert(str_contains($seedSql, "'998821'"), "database/seed.sql contains SAC 998821");

        $this->assert(str_contains($seedHsn, 'CREATE TABLE IF NOT EXISTS `hsn_masters`'), "database/seed_hsn.sql creates hsn_masters table");
        $this->assert(str_contains($seedHsn, "'5201'"), "database/seed_hsn.sql contains HSN 5201");
        $this->assert(str_contains($seedHsn, "'6109'"), "database/seed_hsn.sql contains HSN 6109");
        $this->assert(str_contains($seedHsn, "'998821'"), "database/seed_hsn.sql contains SAC 998821");

        echo "\n======================================================\n";
        echo "  RESULTS: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }
}

$test = new HsnSeedDataTest();
$test->run();
