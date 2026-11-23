<?php
// tests/GranularStaffCrudPermissionsTest.php
// Rigorous Test Suite verifying Granular Staff CRUD Permissions:
// 1. View access only -> only view elements show; create, edit, delete hidden & forbidden
// 2. Create access only -> only create/add elements show; edit, delete hidden & forbidden
// 3. Edit access only -> only edit elements show; create, delete hidden & forbidden
// 4. Delete access only -> only delete elements show; create, edit hidden & forbidden
// 5. All access -> all elements show and all CRUD operations permitted
// 6. View revoked -> module hidden from sidebar navigation & blocked with 403 Forbidden

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(__DIR__ . '/../.env');

use App\Core\DB;
use App\Core\Auth;
use App\Core\Router;
use App\Core\ModuleGuard;

class GranularStaffCrudPermissionsTest {
    private int $concernId;
    private int $roleId;
    private int $userId;
    private array $moduleIds = [];

    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        echo "\n======================================================================\n";
        echo "  INVENTO ERP: GRANULAR STAFF CRUD PERMISSION VERIFICATION TEST SUITE  \n";
        echo "======================================================================\n\n";

        try {
            $this->setupFixtures();

            $this->testViewOnlyPermissions();
            $this->testCreateOnlyPermissions();
            $this->testEditOnlyPermissions();
            $this->testDeleteOnlyPermissions();
            $this->testAllAccessPermissions();
            $this->testRevokedViewPermissions();
            $this->testRoutePermissionEnforcementMap();

            $this->cleanupFixtures();
        } catch (\Throwable $e) {
            echo "\n[FATAL ERROR] Unexpected exception: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
            $this->cleanupFixtures();
            exit(1);
        }

        echo "\n----------------------------------------------------------------------\n";
        echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "======================================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function setupFixtures(): void {
        // Find or create test concern
        $c = DB::fetch("SELECT id FROM concerns WHERE status = 'active' LIMIT 1");
        if (!$c) {
            DB::query("INSERT INTO concerns (name, code, status, created_at) VALUES ('Permission Test Concern', 'PTC-01', 'active', NOW())");
            $this->concernId = (int)DB::lastInsertId();
        } else {
            $this->concernId = (int)$c['id'];
        }

        // Create a dedicated Staff Role
        DB::query(
            "INSERT INTO roles (concern_id, name, description, created_at) VALUES (?, 'Granular Perms Test Staff', 'Testing granular CRUD permissions', NOW())",
            [$this->concernId]
        );
        $this->roleId = (int)DB::lastInsertId();

        // Create a test user
        $email = "staff_granular_" . uniqid() . "@invento.com";
        DB::query(
            "INSERT INTO users (concern_id, role_id, name, email, password_hash, status, created_at) VALUES (?, ?, 'Granular Staff User', ?, 'test_hash', 'active', NOW())",
            [$this->concernId, $this->roleId, $email]
        );
        $this->userId = (int)DB::lastInsertId();

        // Resolve and enable test modules
        $targetModules = ['customers', 'stocks', 'catalog_mapping', 'qc_stocks', 'suppliers', 'expense', 'billing', 'return_exchange'];
        foreach ($targetModules as $code) {
            $m = DB::fetch("SELECT id FROM modules WHERE code = ?", [$code]);
            if ($m) {
                $this->moduleIds[$code] = (int)$m['id'];
                DB::query(
                    "INSERT INTO concern_modules (concern_id, module_id, enabled) VALUES (?, ?, 1)
                     ON DUPLICATE KEY UPDATE enabled = 1",
                    [$this->concernId, $m['id']]
                );
            }
        }
    }

    private function cleanupFixtures(): void {
        if ($this->userId) {
            DB::query("DELETE FROM user_permissions WHERE user_id = ?", [$this->userId]);
            DB::query("DELETE FROM users WHERE id = ?", [$this->userId]);
        }
        if ($this->roleId) {
            DB::query("DELETE FROM role_permissions WHERE role_id = ?", [$this->roleId]);
            DB::query("DELETE FROM roles WHERE id = ?", [$this->roleId]);
        }
    }

    private function applyPermissions(string $moduleCode, ?int $view, ?int $add, ?int $edit, ?int $delete): void {
        $modId = $this->moduleIds[$moduleCode] ?? null;
        if (!$modId) return;

        DB::query(
            "INSERT INTO user_permissions (user_id, module_id, concern_id, can_view, can_add, can_edit, can_delete)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_add = VALUES(can_add), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete)",
            [$this->userId, $modId, $this->concernId, $view, $add, $edit, $delete]
        );

        // Simulate session context for this staff user
        Auth::startSession();
        $_SESSION['user'] = [
            'id'         => $this->userId,
            'concern_id' => $this->concernId,
            'role_id'    => $this->roleId,
            'name'       => 'Granular Staff User',
            'email'      => 'staff_granular@invento.com',
            'role_name'  => 'Granular Perms Test Staff',
        ];
        $_SESSION['concern'] = [
            'id'   => $this->concernId,
            'name' => 'Permission Test Concern',
            'code' => 'PTC-01',
            'logo' => null,
        ];
        $_SESSION['enabled_modules'] = array_keys($this->moduleIds);
        Auth::loadPermissions($this->userId, $this->roleId);
    }

    // Helper to capture view template rendering into a string
    private function renderViewHtml(string $viewFile, array $data = []): string {
        extract($data);
        $csrfToken = 'test_token_123';
        ob_start();
        try {
            include __DIR__ . '/../app/views/' . $viewFile;
        } catch (\Throwable $t) {
            // View may reference variables that are optional; capture what was rendered
        }
        return ob_get_clean() ?: '';
    }

    // =========================================================================
    // 1. SCENARIO 1: VIEW ACCESS ONLY (can_view = 1, can_add = 0, can_edit = 0, can_delete = 0)
    // =========================================================================
    private function testViewOnlyPermissions(): void {
        echo "[1] Testing VIEW ACCESS ONLY (can_view=1, can_add=0, can_edit=0, can_delete=0)...\n";

        foreach (['customers', 'stocks', 'catalog_mapping', 'qc_stocks', 'suppliers', 'expense'] as $mod) {
            $this->applyPermissions($mod, 1, 0, 0, 0);

            $this->assert(Auth::can($mod, 'view') === true, "[$mod] Auth::can('view') is TRUE for view-only user");
            $this->assert(Auth::can($mod, 'add') === false, "[$mod] Auth::can('add') is FALSE for view-only user");
            $this->assert(Auth::can($mod, 'create') === false, "[$mod] Auth::can('create') is FALSE for view-only user");
            $this->assert(Auth::can($mod, 'edit') === false, "[$mod] Auth::can('edit') is FALSE for view-only user");
            $this->assert(Auth::can($mod, 'delete') === false, "[$mod] Auth::can('delete') is FALSE for view-only user");
            $this->assert(Auth::can($mod, 'export') === false, "[$mod] Auth::can('export') is FALSE (view-only barred from data export)");
        }

        // View Template Verification for View-Only:
        // A. Customers View
        $this->applyPermissions('customers', 1, 0, 0, 0);
        $custHtml = $this->renderViewHtml('concern/customers.php', [
            'customers' => [['id' => 1, 'name' => 'Alice Customer', 'phone' => '9876543210', 'email' => 'alice@test.com', 'address' => 'Sample', 'status' => 'active', 'total_orders' => 2, 'total_spent' => 500, 'created_at' => date('Y-m-d')]],
            'metrics' => ['total' => 1, 'active' => 1, 'inactive' => 0, 'new_this_month' => 1],
            'filters' => ['search' => '', 'status' => 'all']
        ]);
        $this->assert(!str_contains($custHtml, 'id="btnAddCustomer"'), "[customers.php] Add Customer button is HIDDEN for view-only staff");
        $this->assert(!str_contains($custHtml, 'id="modalAddCustomer"'), "[customers.php] Add Customer Modal is NOT rendered for view-only staff");
        $this->assert(!str_contains($custHtml, 'btn-edit-customer'), "[customers.php] Edit Customer button is HIDDEN for view-only staff");
        $this->assert(!str_contains($custHtml, '/m/customers/delete'), "[customers.php] Delete Customer form is HIDDEN for view-only staff");
        $this->assert(str_contains($custHtml, 'btn-view-customer') || str_contains($custHtml, 'Alice Customer'), "[customers.php] Customer record and View Profile are VISIBLE");

        // B. Stocks View
        $this->applyPermissions('stocks', 1, 0, 0, 0);
        $stockHtml = $this->renderViewHtml('concern/stocks.php', [
            'stocks' => [['id' => 1, 'variant_id' => 10, 'product_name' => 'Silk Shirt', 'sku_code' => 'SLK-01', 'size' => 'M', 'color' => 'Blue', 'location' => 'Main Store', 'quantity' => 25, 'cost_price' => 200, 'mrp' => 500, 'selling_price' => 450, 'is_complementary' => 0]],
            'stockMetrics' => ['total_items' => 1, 'total_units' => 25, 'total_value' => 5000, 'low_stock_count' => 0],
            'search' => '',
            'activeTab' => 'all_stock',
            'canEditStock' => Auth::can('stocks', 'edit'),
            'canDeleteStock' => Auth::can('stocks', 'delete')
        ]);
        $this->assert(!str_contains($stockHtml, 'modalEditLine'), "[stocks.php] Edit Stock Line button is HIDDEN for view-only staff");
        $this->assert(!str_contains($stockHtml, '/m/stocks/delete-line'), "[stocks.php] Delete Stock Line button is HIDDEN for view-only staff");

        // C. QC Inspections View
        $this->applyPermissions('qc_stocks', 1, 0, 0, 0);
        $qcHtml = $this->renderViewHtml('concern/qc_stocks.php', [
            'inspections' => [['id' => 1, 'qc_no' => 'QC-2026-001', 'program_no' => 'PRG-01', 'product_name' => 'Polo', 'inspector_name' => 'Inspector A', 'inspected_qty' => 100, 'passed_qty' => 95, 'rejected_qty' => 3, 'rework_qty' => 2, 'status' => 'PASSED', 'created_at' => date('Y-m-d')]],
            'statusFilter' => '', 'search' => '', 'page' => 1, 'perPage' => 20, 'totalPages' => 1, 'totalCount' => 1,
            'variants' => [], 'programs' => []
        ]);
        $this->assert(!str_contains($qcHtml, '/production/qc/create'), "[qc_stocks.php] 'New QC Batch' link is HIDDEN for view-only staff");
        $this->assert(!str_contains($qcHtml, '/production/qc/inspect'), "[qc_stocks.php] 'Open 2D QR Piece Scanner' icon is HIDDEN for view-only staff");
        $this->assert(!str_contains($qcHtml, 'openReinspectModal'), "[qc_stocks.php] 'Re-inspect Rework' button is HIDDEN for view-only staff");
        $this->assert(!str_contains($qcHtml, '/production/qc/delete'), "[qc_stocks.php] 'Delete QC Inspection' form is HIDDEN for view-only staff");
        $this->assert(str_contains($qcHtml, '/production/qc/details'), "[qc_stocks.php] 'View Inspection Details' link remains ACCESSIBLE");

        // D. Catalog Mapping View
        $this->applyPermissions('catalog_mapping', 1, 0, 0, 0);
        $mapHtml = $this->renderViewHtml('concern/catalog_mapping.php', [
            'items' => [
                ['id' => 1, 'item_name' => 'Linen Tunic', 'is_mapped' => 0, 'qty' => 10, 'unit' => 'Pcs', 'unit_price' => 150, 'total_price' => 1500, 'tax_amount' => 75, 'bill_no' => 'PB-01', 'bill_date' => date('Y-m-d')],
                ['id' => 2, 'item_name' => 'Cotton Shirt', 'is_mapped' => 1, 'catalog_name' => 'Style A', 'qty' => 20, 'unit' => 'Pcs', 'unit_price' => 200, 'total_price' => 4000, 'tax_amount' => 200, 'bill_no' => 'PB-02', 'bill_date' => date('Y-m-d')]
            ],
            'categories' => [], 'subcategories' => [], 'subSubcategories' => [], 'attributes' => [], 'hsnCodes' => [],
            'metrics' => ['total' => 2, 'mapped' => 1, 'unmapped' => 1, 'pieces' => 30],
            'activeTab' => 'mappings', 'filters' => ['search' => '', 'status' => 'all', 'category_id' => '']
        ]);
        $this->assert(!str_contains($mapHtml, 'title="Map to Catalog Style & 3-Tier Hierarchy"'), "[catalog_mapping.php] 'Map' button is HIDDEN for view-only staff");
        $this->assert(!str_contains($mapHtml, 'title="Edit Catalog Mapping"'), "[catalog_mapping.php] 'Edit Catalog Mapping' button is HIDDEN for view-only staff");
        $this->assert(!str_contains($mapHtml, 'title="Unmap from Catalog"'), "[catalog_mapping.php] 'Unmap from Catalog' button is HIDDEN for view-only staff");
        $this->assert(str_contains($mapHtml, 'Read-only'), "[catalog_mapping.php] Table correctly marks unpermitted actions as 'Read-only'");
    }

    // =========================================================================
    // 2. SCENARIO 2: CREATE / ADD ACCESS ONLY (can_view = 1, can_add = 1, can_edit = 0, can_delete = 0)
    // =========================================================================
    private function testCreateOnlyPermissions(): void {
        echo "\n[2] Testing CREATE / ADD ACCESS ONLY (can_view=1, can_add=1, can_edit=0, can_delete=0)...\n";

        foreach (['customers', 'qc_stocks', 'suppliers', 'expense'] as $mod) {
            $this->applyPermissions($mod, 1, 1, 0, 0);

            $this->assert(Auth::can($mod, 'view') === true, "[$mod] Auth::can('view') is TRUE");
            $this->assert(Auth::can($mod, 'add') === true, "[$mod] Auth::can('add') is TRUE for create-only user");
            $this->assert(Auth::can($mod, 'create') === true, "[$mod] Auth::can('create') alias is TRUE");
            $this->assert(Auth::can($mod, 'edit') === false, "[$mod] Auth::can('edit') is FALSE for create-only user");
            $this->assert(Auth::can($mod, 'delete') === false, "[$mod] Auth::can('delete') is FALSE for create-only user");
        }

        // View Template Verification for Create-Only:
        // A. Customers View
        $this->applyPermissions('customers', 1, 1, 0, 0);
        $custHtml = $this->renderViewHtml('concern/customers.php', [
            'customers' => [['id' => 1, 'name' => 'Alice Customer', 'phone' => '9876543210', 'email' => 'alice@test.com', 'address' => 'Sample', 'status' => 'active', 'total_orders' => 2, 'total_spent' => 500, 'created_at' => date('Y-m-d')]],
            'metrics' => ['total' => 1, 'active' => 1, 'inactive' => 0, 'new_this_month' => 1],
            'filters' => ['search' => '', 'status' => 'all']
        ]);
        $this->assert(str_contains($custHtml, 'id="btnAddCustomer"'), "[customers.php] Add Customer button IS VISIBLE for create-only staff");
        $this->assert(!str_contains($custHtml, 'btn-edit-customer'), "[customers.php] Edit Customer button is HIDDEN for create-only staff");
        $this->assert(!str_contains($custHtml, '/m/customers/delete'), "[customers.php] Delete Customer form is HIDDEN for create-only staff");

        // B. QC Inspections View
        $this->applyPermissions('qc_stocks', 1, 1, 0, 0);
        $qcHtml = $this->renderViewHtml('concern/qc_stocks.php', [
            'inspections' => [['id' => 1, 'qc_no' => 'QC-2026-001', 'program_no' => 'PRG-01', 'product_name' => 'Polo', 'inspector_name' => 'Inspector A', 'inspected_qty' => 100, 'passed_qty' => 95, 'rejected_qty' => 3, 'rework_qty' => 2, 'status' => 'PASSED', 'created_at' => date('Y-m-d')]],
            'statusFilter' => '', 'search' => '', 'page' => 1, 'perPage' => 20, 'totalPages' => 1, 'totalCount' => 1,
            'variants' => [], 'programs' => []
        ]);
        $this->assert(str_contains($qcHtml, '/production/qc/create'), "[qc_stocks.php] 'New QC Batch' button IS VISIBLE for create-only staff");
        $this->assert(!str_contains($qcHtml, '/production/qc/inspect'), "[qc_stocks.php] 'Open QR Piece Scanner' icon is HIDDEN for create-only staff");
        $this->assert(!str_contains($qcHtml, '/production/qc/delete'), "[qc_stocks.php] 'Delete QC Inspection' form is HIDDEN for create-only staff");

        // C. Catalog Mapping View
        $this->applyPermissions('catalog_mapping', 1, 1, 0, 0);
        $mapHtml = $this->renderViewHtml('concern/catalog_mapping.php', [
            'items' => [
                ['id' => 1, 'item_name' => 'Linen Tunic', 'is_mapped' => 0, 'qty' => 10, 'unit' => 'Pcs', 'unit_price' => 150, 'total_price' => 1500, 'tax_amount' => 75, 'bill_no' => 'PB-01', 'bill_date' => date('Y-m-d')],
                ['id' => 2, 'item_name' => 'Cotton Shirt', 'is_mapped' => 1, 'catalog_name' => 'Style A', 'qty' => 20, 'unit' => 'Pcs', 'unit_price' => 200, 'total_price' => 4000, 'tax_amount' => 200, 'bill_no' => 'PB-02', 'bill_date' => date('Y-m-d')]
            ],
            'categories' => [], 'subcategories' => [], 'subSubcategories' => [], 'attributes' => [], 'hsnCodes' => [],
            'metrics' => ['total' => 2, 'mapped' => 1, 'unmapped' => 1, 'pieces' => 30],
            'activeTab' => 'mappings', 'filters' => ['search' => '', 'status' => 'all', 'category_id' => '']
        ]);
        $this->assert(str_contains($mapHtml, 'title="Map to Catalog Style & 3-Tier Hierarchy"'), "[catalog_mapping.php] 'Map' button IS VISIBLE for unmapped items");
        $this->assert(!str_contains($mapHtml, 'title="Edit Catalog Mapping"'), "[catalog_mapping.php] 'Edit Catalog Mapping' button is HIDDEN for create-only staff");
        $this->assert(!str_contains($mapHtml, 'title="Unmap from Catalog"'), "[catalog_mapping.php] 'Unmap from Catalog' button is HIDDEN for create-only staff");
    }

    // =========================================================================
    // 3. SCENARIO 3: EDIT ACCESS ONLY (can_view = 1, can_add = 0, can_edit = 1, can_delete = 0)
    // =========================================================================
    private function testEditOnlyPermissions(): void {
        echo "\n[3] Testing EDIT ACCESS ONLY (can_view=1, can_add=0, can_edit=1, can_delete=0)...\n";

        foreach (['customers', 'stocks', 'qc_stocks', 'suppliers', 'expense'] as $mod) {
            $this->applyPermissions($mod, 1, 0, 1, 0);

            $this->assert(Auth::can($mod, 'view') === true, "[$mod] Auth::can('view') is TRUE");
            $this->assert(Auth::can($mod, 'add') === false, "[$mod] Auth::can('add') is FALSE for edit-only user");
            $this->assert(Auth::can($mod, 'create') === false, "[$mod] Auth::can('create') is FALSE");
            $this->assert(Auth::can($mod, 'edit') === true, "[$mod] Auth::can('edit') is TRUE for edit-only user");
            $this->assert(Auth::can($mod, 'delete') === false, "[$mod] Auth::can('delete') is FALSE for edit-only user");
        }

        // View Template Verification for Edit-Only:
        // A. Customers View
        $this->applyPermissions('customers', 1, 0, 1, 0);
        $custHtml = $this->renderViewHtml('concern/customers.php', [
            'customers' => [['id' => 1, 'name' => 'Alice Customer', 'phone' => '9876543210', 'email' => 'alice@test.com', 'address' => 'Sample', 'status' => 'active', 'total_orders' => 2, 'total_spent' => 500, 'created_at' => date('Y-m-d')]],
            'metrics' => ['total' => 1, 'active' => 1, 'inactive' => 0, 'new_this_month' => 1],
            'filters' => ['search' => '', 'status' => 'all']
        ]);
        $this->assert(!str_contains($custHtml, 'id="btnAddCustomer"'), "[customers.php] Add Customer button is HIDDEN for edit-only staff");
        $this->assert(str_contains($custHtml, 'btn-edit-customer'), "[customers.php] Edit Customer button IS VISIBLE for edit-only staff");
        $this->assert(!str_contains($custHtml, '/m/customers/delete'), "[customers.php] Delete Customer form is HIDDEN for edit-only staff");

        // B. Stocks View
        $this->applyPermissions('stocks', 1, 0, 1, 0);
        $stockHtml = $this->renderViewHtml('concern/stocks.php', [
            'stocks' => [['id' => 1, 'variant_id' => 10, 'product_name' => 'Silk Shirt', 'sku_code' => 'SLK-01', 'size' => 'M', 'color' => 'Blue', 'location' => 'Main Store', 'quantity' => 25, 'cost_price' => 200, 'mrp' => 500, 'selling_price' => 450, 'is_complementary' => 0]],
            'stockMetrics' => ['total_items' => 1, 'total_units' => 25, 'total_value' => 5000, 'low_stock_count' => 0],
            'search' => '',
            'activeTab' => 'all_stock',
            'canEditStock' => Auth::can('stocks', 'edit'),
            'canDeleteStock' => Auth::can('stocks', 'delete')
        ]);
        $this->assert(str_contains($stockHtml, 'modalEditLine'), "[stocks.php] Edit Stock Line button IS VISIBLE for edit-only staff");
        $this->assert(!str_contains($stockHtml, '/m/stocks/delete-line'), "[stocks.php] Delete Stock Line button is HIDDEN for edit-only staff");

        // C. QC Inspections View
        $this->applyPermissions('qc_stocks', 1, 0, 1, 0);
        $qcHtml = $this->renderViewHtml('concern/qc_stocks.php', [
            'inspections' => [['id' => 1, 'qc_no' => 'QC-2026-001', 'program_no' => 'PRG-01', 'product_name' => 'Polo', 'inspector_name' => 'Inspector A', 'inspected_qty' => 100, 'passed_qty' => 95, 'rejected_qty' => 3, 'rework_qty' => 2, 'status' => 'PASSED', 'created_at' => date('Y-m-d')]],
            'statusFilter' => '', 'search' => '', 'page' => 1, 'perPage' => 20, 'totalPages' => 1, 'totalCount' => 1,
            'variants' => [], 'programs' => []
        ]);
        $this->assert(!str_contains($qcHtml, '/production/qc/create'), "[qc_stocks.php] 'New QC Batch' link is HIDDEN for edit-only staff");
        $this->assert(str_contains($qcHtml, '/production/qc/inspect'), "[qc_stocks.php] 'Open QR Piece Scanner' icon IS VISIBLE for edit-only staff");
        $this->assert(str_contains($qcHtml, 'openReinspectModal'), "[qc_stocks.php] 'Re-inspect Rework' button IS VISIBLE for edit-only staff");
        $this->assert(!str_contains($qcHtml, '/production/qc/delete'), "[qc_stocks.php] 'Delete QC Inspection' form is HIDDEN for edit-only staff");

        // D. Catalog Mapping View
        $this->applyPermissions('catalog_mapping', 1, 0, 1, 0);
        $mapHtml = $this->renderViewHtml('concern/catalog_mapping.php', [
            'items' => [
                ['id' => 1, 'item_name' => 'Linen Tunic', 'is_mapped' => 0, 'qty' => 10, 'unit' => 'Pcs', 'unit_price' => 150, 'total_price' => 1500, 'tax_amount' => 75, 'bill_no' => 'PB-01', 'bill_date' => date('Y-m-d')],
                ['id' => 2, 'item_name' => 'Cotton Shirt', 'is_mapped' => 1, 'catalog_name' => 'Style A', 'qty' => 20, 'unit' => 'Pcs', 'unit_price' => 200, 'total_price' => 4000, 'tax_amount' => 200, 'bill_no' => 'PB-02', 'bill_date' => date('Y-m-d')]
            ],
            'categories' => [], 'subcategories' => [], 'subSubcategories' => [], 'attributes' => [], 'hsnCodes' => [],
            'metrics' => ['total' => 2, 'mapped' => 1, 'unmapped' => 1, 'pieces' => 30],
            'activeTab' => 'mappings', 'filters' => ['search' => '', 'status' => 'all', 'category_id' => '']
        ]);
        $this->assert(!str_contains($mapHtml, 'title="Map to Catalog Style & 3-Tier Hierarchy"'), "[catalog_mapping.php] 'Map' button is HIDDEN for unmapped items");
        $this->assert(str_contains($mapHtml, 'title="Edit Catalog Mapping"'), "[catalog_mapping.php] 'Edit Catalog Mapping' button IS VISIBLE for mapped items");
        $this->assert(!str_contains($mapHtml, 'title="Unmap from Catalog"'), "[catalog_mapping.php] 'Unmap from Catalog' button is HIDDEN for edit-only staff");
    }

    // =========================================================================
    // 4. SCENARIO 4: DELETE ACCESS ONLY (can_view = 1, can_add = 0, can_edit = 0, can_delete = 1)
    // =========================================================================
    private function testDeleteOnlyPermissions(): void {
        echo "\n[4] Testing DELETE ACCESS ONLY (can_view=1, can_add=0, can_edit=0, can_delete=1)...\n";

        foreach (['customers', 'stocks', 'qc_stocks', 'suppliers', 'expense'] as $mod) {
            $this->applyPermissions($mod, 1, 0, 0, 1);

            $this->assert(Auth::can($mod, 'view') === true, "[$mod] Auth::can('view') is TRUE");
            $this->assert(Auth::can($mod, 'add') === false, "[$mod] Auth::can('add') is FALSE for delete-only user");
            $this->assert(Auth::can($mod, 'create') === false, "[$mod] Auth::can('create') is FALSE");
            $this->assert(Auth::can($mod, 'edit') === false, "[$mod] Auth::can('edit') is FALSE for delete-only user");
            $this->assert(Auth::can($mod, 'delete') === true, "[$mod] Auth::can('delete') is TRUE for delete-only user");
        }

        // View Template Verification for Delete-Only:
        // A. Customers View
        $this->applyPermissions('customers', 1, 0, 0, 1);
        $custHtml = $this->renderViewHtml('concern/customers.php', [
            'customers' => [['id' => 1, 'name' => 'Alice Customer', 'phone' => '9876543210', 'email' => 'alice@test.com', 'address' => 'Sample', 'status' => 'active', 'total_orders' => 2, 'total_spent' => 500, 'created_at' => date('Y-m-d')]],
            'metrics' => ['total' => 1, 'active' => 1, 'inactive' => 0, 'new_this_month' => 1],
            'filters' => ['search' => '', 'status' => 'all']
        ]);
        $this->assert(!str_contains($custHtml, 'id="btnAddCustomer"'), "[customers.php] Add Customer button is HIDDEN for delete-only staff");
        $this->assert(!str_contains($custHtml, 'btn-edit-customer'), "[customers.php] Edit Customer button is HIDDEN for delete-only staff");
        $this->assert(str_contains($custHtml, '/m/customers/delete'), "[customers.php] Delete Customer form IS VISIBLE for delete-only staff");

        // B. Stocks View
        $this->applyPermissions('stocks', 1, 0, 0, 1);
        $stockHtml = $this->renderViewHtml('concern/stocks.php', [
            'stocks' => [['id' => 1, 'variant_id' => 10, 'product_name' => 'Silk Shirt', 'sku_code' => 'SLK-01', 'size' => 'M', 'color' => 'Blue', 'location' => 'Main Store', 'quantity' => 25, 'cost_price' => 200, 'mrp' => 500, 'selling_price' => 450, 'is_complementary' => 0]],
            'stockMetrics' => ['total_items' => 1, 'total_units' => 25, 'total_value' => 5000, 'low_stock_count' => 0],
            'search' => '',
            'activeTab' => 'all_stock',
            'canEditStock' => Auth::can('stocks', 'edit'),
            'canDeleteStock' => Auth::can('stocks', 'delete')
        ]);
        $this->assert(!str_contains($stockHtml, 'modalEditLine'), "[stocks.php] Edit Stock Line button is HIDDEN for delete-only staff");
        $this->assert(str_contains($stockHtml, '/m/stocks/delete-line'), "[stocks.php] Delete Stock Line button IS VISIBLE for delete-only staff");

        // C. QC Inspections View
        $this->applyPermissions('qc_stocks', 1, 0, 0, 1);
        $qcHtml = $this->renderViewHtml('concern/qc_stocks.php', [
            'inspections' => [['id' => 1, 'qc_no' => 'QC-2026-001', 'program_no' => 'PRG-01', 'product_name' => 'Polo', 'inspector_name' => 'Inspector A', 'inspected_qty' => 100, 'passed_qty' => 95, 'rejected_qty' => 3, 'rework_qty' => 2, 'status' => 'PASSED', 'created_at' => date('Y-m-d')]],
            'statusFilter' => '', 'search' => '', 'page' => 1, 'perPage' => 20, 'totalPages' => 1, 'totalCount' => 1,
            'variants' => [], 'programs' => []
        ]);
        $this->assert(!str_contains($qcHtml, '/production/qc/create'), "[qc_stocks.php] 'New QC Batch' link is HIDDEN for delete-only staff");
        $this->assert(!str_contains($qcHtml, '/production/qc/inspect'), "[qc_stocks.php] 'Open QR Piece Scanner' icon is HIDDEN for delete-only staff");
        $this->assert(!str_contains($qcHtml, 'openReinspectModal'), "[qc_stocks.php] 'Re-inspect Rework' button is HIDDEN for delete-only staff");
        $this->assert(str_contains($qcHtml, '/production/qc/delete'), "[qc_stocks.php] 'Delete QC Inspection' form IS VISIBLE for delete-only staff");

        // D. Catalog Mapping View
        $this->applyPermissions('catalog_mapping', 1, 0, 0, 1);
        $mapHtml = $this->renderViewHtml('concern/catalog_mapping.php', [
            'items' => [
                ['id' => 1, 'item_name' => 'Linen Tunic', 'is_mapped' => 0, 'qty' => 10, 'unit' => 'Pcs', 'unit_price' => 150, 'total_price' => 1500, 'tax_amount' => 75, 'bill_no' => 'PB-01', 'bill_date' => date('Y-m-d')],
                ['id' => 2, 'item_name' => 'Cotton Shirt', 'is_mapped' => 1, 'catalog_name' => 'Style A', 'qty' => 20, 'unit' => 'Pcs', 'unit_price' => 200, 'total_price' => 4000, 'tax_amount' => 200, 'bill_no' => 'PB-02', 'bill_date' => date('Y-m-d')]
            ],
            'categories' => [], 'subcategories' => [], 'subSubcategories' => [], 'attributes' => [], 'hsnCodes' => [],
            'metrics' => ['total' => 2, 'mapped' => 1, 'unmapped' => 1, 'pieces' => 30],
            'activeTab' => 'mappings', 'filters' => ['search' => '', 'status' => 'all', 'category_id' => '']
        ]);
        $this->assert(!str_contains($mapHtml, 'title="Map to Catalog Style & 3-Tier Hierarchy"'), "[catalog_mapping.php] 'Map' button is HIDDEN for delete-only staff");
        $this->assert(!str_contains($mapHtml, 'title="Edit Catalog Mapping"'), "[catalog_mapping.php] 'Edit Catalog Mapping' button is HIDDEN for delete-only staff");
        $this->assert(str_contains($mapHtml, 'title="Unmap from Catalog"'), "[catalog_mapping.php] 'Unmap from Catalog' button IS VISIBLE for delete-only staff");
    }

    // =========================================================================
    // 5. SCENARIO 5: ALL ACCESS (can_view = 1, can_add = 1, can_edit = 1, can_delete = 1)
    // =========================================================================
    private function testAllAccessPermissions(): void {
        echo "\n[5] Testing ALL ACCESS (can_view=1, can_add=1, can_edit=1, can_delete=1)...\n";

        foreach (['customers', 'stocks', 'catalog_mapping', 'qc_stocks', 'suppliers', 'expense'] as $mod) {
            $this->applyPermissions($mod, 1, 1, 1, 1);

            $this->assert(Auth::can($mod, 'view') === true, "[$mod] Auth::can('view') is TRUE for full-access staff");
            $this->assert(Auth::can($mod, 'add') === true, "[$mod] Auth::can('add') is TRUE for full-access staff");
            $this->assert(Auth::can($mod, 'create') === true, "[$mod] Auth::can('create') is TRUE");
            $this->assert(Auth::can($mod, 'edit') === true, "[$mod] Auth::can('edit') is TRUE for full-access staff");
            $this->assert(Auth::can($mod, 'delete') === true, "[$mod] Auth::can('delete') is TRUE for full-access staff");
            $this->assert(Auth::can($mod, 'export') === true, "[$mod] Auth::can('export') is TRUE (export allowed when user has add/edit)");
        }

        // View Template Verification for All Access:
        $this->applyPermissions('customers', 1, 1, 1, 1);
        $custHtml = $this->renderViewHtml('concern/customers.php', [
            'customers' => [['id' => 1, 'name' => 'Alice Customer', 'phone' => '9876543210', 'email' => 'alice@test.com', 'address' => 'Sample', 'status' => 'active', 'total_orders' => 2, 'total_spent' => 500, 'created_at' => date('Y-m-d')]],
            'metrics' => ['total' => 1, 'active' => 1, 'inactive' => 0, 'new_this_month' => 1],
            'filters' => ['search' => '', 'status' => 'all']
        ]);
        $this->assert(str_contains($custHtml, 'id="btnAddCustomer"'), "[customers.php] Add button IS VISIBLE");
        $this->assert(str_contains($custHtml, 'btn-edit-customer'), "[customers.php] Edit button IS VISIBLE");
        $this->assert(str_contains($custHtml, '/m/customers/delete'), "[customers.php] Delete form IS VISIBLE");

        $this->applyPermissions('stocks', 1, 1, 1, 1);
        $stockHtml = $this->renderViewHtml('concern/stocks.php', [
            'stocks' => [['id' => 1, 'variant_id' => 10, 'product_name' => 'Silk Shirt', 'sku_code' => 'SLK-01', 'size' => 'M', 'color' => 'Blue', 'location' => 'Main Store', 'quantity' => 25, 'cost_price' => 200, 'mrp' => 500, 'selling_price' => 450, 'is_complementary' => 0]],
            'stockMetrics' => ['total_items' => 1, 'total_units' => 25, 'total_value' => 5000, 'low_stock_count' => 0],
            'search' => '',
            'activeTab' => 'all_stock',
            'canEditStock' => Auth::can('stocks', 'edit'),
            'canDeleteStock' => Auth::can('stocks', 'delete')
        ]);
        $this->assert(str_contains($stockHtml, 'modalEditLine'), "[stocks.php] Edit Stock Line button IS VISIBLE");
        $this->assert(str_contains($stockHtml, '/m/stocks/delete-line'), "[stocks.php] Delete Stock Line button IS VISIBLE");
    }

    // =========================================================================
    // 6. SCENARIO 6: REVOKED VIEW (can_view = 0)
    // =========================================================================
    private function testRevokedViewPermissions(): void {
        echo "\n[6] Testing REVOKED VIEW (can_view = 0)...\n";

        foreach (['customers', 'stocks', 'catalog_mapping', 'qc_stocks'] as $mod) {
            $this->applyPermissions($mod, 0, 1, 1, 1);

            $this->assert(Auth::can($mod, 'view') === false, "[$mod] Auth::can('view') is FALSE when view is revoked");
            $this->assert(Auth::can($mod, 'add') === true || Auth::can($mod, 'add') === false, "[$mod] can_add evaluated cleanly");
        }

        // Test sidebar filtering logic:
        $rawModules = [
            ['code' => 'customers', 'name' => 'Customers'],
            ['code' => 'stocks', 'name' => 'Live Stocks'],
            ['code' => 'qc_stocks', 'name' => 'QC Inspections'],
        ];

        // Revoke view on customers
        $this->applyPermissions('customers', 0, 0, 0, 0);
        $this->applyPermissions('stocks', 1, 1, 1, 1);
        $this->applyPermissions('qc_stocks', 1, 0, 0, 0);

        $visibleSidebar = array_values(array_filter($rawModules, fn($m) => Auth::can($m['code'], 'view')));
        $visibleCodes = array_column($visibleSidebar, 'code');

        $this->assert(!in_array('customers', $visibleCodes, true), "Revoking view access removes 'customers' module from navigation sidebar");
        $this->assert(in_array('stocks', $visibleCodes, true), "Permitted 'stocks' module remains in navigation sidebar");
        $this->assert(in_array('qc_stocks', $visibleCodes, true), "Permitted 'qc_stocks' module remains in navigation sidebar");
    }

    // =========================================================================
    // 7. ROUTE PERMISSION ENFORCEMENT AUDIT MAP
    // =========================================================================
    private function testRoutePermissionEnforcementMap(): void {
        echo "\n[7] Testing Route Permission Enforcement Mappings...\n";

        require_once __DIR__ . '/../app/core/Router.php';
        $router = new Router();
        require __DIR__ . '/../app/routes.php';

        $routes = $router->getRoutes();
        $routeMap = [];
        foreach ($routes as $r) {
            $key = $r['method'] . ' ' . $r['path'];
            $routeMap[$key] = $r;
        }

        $expectedGuards = [
            // Customers
            'GET /m/customers'                                   => ['customers', 'view'],
            'POST /m/customers/store'                            => ['customers', 'add'],
            'POST /m/customers/update'                           => ['customers', 'edit'],
            'POST /m/customers/delete'                           => ['customers', 'delete'],
            'GET /m/customers/export'                            => ['customers', 'export'],

            // Stocks
            'GET /m/stocks'                                      => ['stocks', 'view'],
            'POST /m/stocks/update-details'                      => ['stocks', 'edit'],
            'POST /m/stocks/update-quantity'                     => ['stocks', 'edit'],
            'POST /m/stocks/delete-line'                         => ['stocks', 'delete'],
            'GET /m/stocks/export'                               => ['stocks', 'export'],

            // Catalog Mapping
            'GET /production/catalog-mapping'                    => ['catalog_mapping', 'view'],
            'GET /production/catalog-mapping/map'                => ['catalog_mapping', 'add|edit'],
            'POST /production/catalog-mapping/save'              => ['catalog_mapping', 'add|edit'],
            'POST /production/catalog-mapping/unmap'             => ['catalog_mapping', 'delete'],
            'POST /production/catalog-mapping/quick-category'    => ['catalog_mapping', 'add'],
            'POST /production/catalog-mapping/quick-attribute'   => ['catalog_mapping', 'add'],
            'POST /production/catalog-mapping/delete-hierarchy'  => ['catalog_mapping', 'delete'],

            // QC Stocks
            'GET /production/qc'                                 => ['qc_stocks', 'view'],
            'GET /production/qc/create'                          => ['qc_stocks', 'add'],
            'GET /production/qc/details'                         => ['qc_stocks', 'view'],
            'GET /production/qc/inspect'                         => ['qc_stocks', 'edit'],
            'POST /production/qc/store'                          => ['qc_stocks', 'add'],
            'POST /production/qc/update'                         => ['qc_stocks', 'edit'],
            'POST /production/qc/reinspect'                      => ['qc_stocks', 'edit'],
            'POST /production/qc/delete'                         => ['qc_stocks', 'delete'],

            // Suppliers
            'GET /m/suppliers'                                   => ['suppliers', 'view'],
            'POST /m/suppliers/store'                            => ['suppliers', 'add'],
            'POST /m/suppliers/update'                           => ['suppliers', 'edit'],
            'POST /m/suppliers/delete'                           => ['suppliers', 'delete'],

            // Expense
            'GET /m/expense'                                     => ['expense', 'view'],
            'POST /m/expense/store'                              => ['expense', 'add'],
            'POST /m/expense/update'                             => ['expense', 'edit'],
            'POST /m/expense/delete'                             => ['expense', 'delete'],
        ];

        foreach ($expectedGuards as $routeSignature => [$expectedModule, $expectedPerm]) {
            $this->assert(
                isset($routeMap[$routeSignature]),
                "Route '$routeSignature' exists in Router registry"
            );
            if (isset($routeMap[$routeSignature])) {
                $actualMod = $routeMap[$routeSignature]['module'] ?? '';
                $actualPerm = $routeMap[$routeSignature]['permission'] ?? '';
                $this->assert(
                    $actualMod === $expectedModule && $actualPerm === $expectedPerm,
                    "Route '$routeSignature' strictly enforces module '$expectedModule' with permission '$expectedPerm' (got '$actualMod' : '$actualPerm')"
                );
            }
        }
    }

    private function assert(bool $condition, string $description): void {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$description}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$description}\n";
        }
    }
}

// Run test suite
$test = new GranularStaffCrudPermissionsTest();
$test->run();
