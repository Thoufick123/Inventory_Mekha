<?php
// tests/ProductionCatalogProgramCreateStyleTest.php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Core\Auth;
use App\Controllers\ProductionController;

class ProductionCatalogProgramCreateStyleTest
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
        echo "  TEST: PRODUCTION CATALOG PROGRAM CREATE STYLE TEST  \n";
        echo "======================================================\n\n";

        Auth::startSession();

        $admin = DB::fetch("SELECT * FROM users WHERE status = 'active' LIMIT 1");
        $_SESSION['user'] = $admin;
        $_SESSION['user_id'] = (int)$admin['id'];
        $_SESSION['concern_id'] = 4;
        $_SESSION['role_id'] = (int)$admin['role_id'];

        $controller = new class extends ProductionController {
            public ?string $renderedView = null;
            public array $renderedData = [];

            protected function render(string $view, array $data = []): void {
                $this->renderedView = $view;
                $this->renderedData = $data;
            }

            protected function redirect(string $url): void {}
            protected function flash(string $type, string $message): void {}
        };

        // Test 1: createStyle() executes with concern_id set without error 3065
        try {
            $_SESSION['concern_id'] = 4;
            $controller->createStyle();
            $this->assert($controller->renderedView === 'concern/catalog_create', 'createStyle() rendered concern/catalog_create view');
            $this->assert(isset($controller->renderedData['attributeSizes']), 'attributeSizes is provided to the view');
            $this->assert(is_array($controller->renderedData['attributeSizes']), 'attributeSizes is a valid array');
        } catch (\Throwable $e) {
            $this->assert(false, 'createStyle() threw exception: ' . $e->getMessage());
        }

        // Test 2: createStyle() executes without concern_id (super admin mode)
        try {
            $_SESSION['concern_id'] = null;
            $controller->createStyle();
            $this->assert($controller->renderedView === 'concern/catalog_create', 'createStyle() works when concern_id is null');
        } catch (\Throwable $e) {
            $this->assert(false, 'createStyle() with null concern_id threw exception: ' . $e->getMessage());
        }

        // Test 3: editStyle() executes without error 3065
        try {
            $catalog = DB::fetch("SELECT id FROM catalogs WHERE source_module = 'production_catalog' ORDER BY id ASC LIMIT 1");
            if ($catalog) {
                $_GET['id'] = (string)$catalog['id'];
                $_SESSION['concern_id'] = null;
                $controller->editStyle();
                $this->assert($controller->renderedView === 'concern/catalog_create', 'editStyle() rendered concern/catalog_create view');
                $this->assert(isset($controller->renderedData['attributeSizes']), 'attributeSizes is provided to the edit view');
            } else {
                echo "  [SKIP] No catalogs found to test editStyle()\n";
            }
        } catch (\Throwable $e) {
            $this->assert(false, 'editStyle() threw exception: ' . $e->getMessage());
        }

        echo "\n======================================================\n";
        echo "  SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }
}

(new ProductionCatalogProgramCreateStyleTest())->run();
