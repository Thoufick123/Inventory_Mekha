<?php
/**
 * Test: 3-Tier Category Hierarchy Edit Actions in Catalog Module Dropdowns
 */
require_once __DIR__ . '/../app/core/Autoloader.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

echo "======================================================\n";
echo "TEST SUITE: Category Hierarchy Edit Actions in Dropdowns\n";
echo "======================================================\n\n";

$pass = 0;
$fail = 0;

function assertCondition($cond, $desc) {
    global $pass, $fail;
    if ($cond) {
        echo "  [PASS] $desc\n";
        $pass++;
    } else {
        echo "  [FAIL] $desc\n";
        $fail++;
    }
}

// 1. Controller Methods Check
echo "--- 1. Testing ProductionController Methods ---\n";
$controllerContent = file_get_contents(__DIR__ . '/../app/controllers/ProductionController.php');

assertCondition(
    strpos($controllerContent, 'function updateCategoryAjax') !== false,
    "updateCategoryAjax method exists in ProductionController"
);
assertCondition(
    strpos($controllerContent, 'function updateSubcategoryAjax') !== false,
    "updateSubcategoryAjax method exists in ProductionController"
);
assertCondition(
    strpos($controllerContent, 'function updateSubSubcategoryAjax') !== false,
    "updateSubSubcategoryAjax method exists in ProductionController"
);

// 2. Routes Check
echo "\n--- 2. Testing Registered Routes in routes.php ---\n";
$routesContent = file_get_contents(__DIR__ . '/../app/routes.php');

assertCondition(
    strpos($routesContent, "'/api/categories/update'") !== false,
    "Route /api/categories/update registered in routes.php"
);
assertCondition(
    strpos($routesContent, "'/api/subcategories/update'") !== false,
    "Route /api/subcategories/update registered in routes.php"
);
assertCondition(
    strpos($routesContent, "'/api/subsubcategories/update'") !== false,
    "Route /api/subsubcategories/update registered in routes.php"
);

// 3. View Elements Check in catalog_create.php
echo "\n--- 3. Testing View Dropdown Elements & Modals in catalog_create.php ---\n";
$createContent = file_get_contents(__DIR__ . '/../app/views/concern/catalog_create.php');

assertCondition(
    strpos($createContent, 'cat-btn-edit-single') !== false,
    "Edit button (cat-btn-edit-single) rendered in dropdown items"
);
assertCondition(
    strpos($createContent, 'bi-pencil-square text-primary') !== false,
    "Pencil icon rendered on edit button in dropdown"
);
assertCondition(
    strpos($createContent, 'id="modalEditCategory"') !== false,
    "Edit Category modal (#modalEditCategory) exists"
);
assertCondition(
    strpos($createContent, 'id="modalEditSubcategory"') !== false,
    "Edit Subcategory modal (#modalEditSubcategory) exists"
);
assertCondition(
    strpos($createContent, 'id="modalEditSubSubcategory"') !== false,
    "Edit Sub-Subcategory modal (#modalEditSubSubcategory) exists"
);
assertCondition(
    strpos($createContent, "document.getElementById('formQuickEditCategory')") !== false,
    "AJAX submit handler for formQuickEditCategory present"
);
assertCondition(
    strpos($createContent, "document.getElementById('formQuickEditSubcategory')") !== false,
    "AJAX submit handler for formQuickEditSubcategory present"
);
assertCondition(
    strpos($createContent, "document.getElementById('formQuickEditSubSubcategory')") !== false,
    "AJAX submit handler for formQuickEditSubSubcategory present"
);

echo "\n======================================================\n";
echo "SUMMARY: $pass PASSED, $fail FAILED\n";
echo "======================================================\n";
if ($fail > 0) {
    exit(1);
}
exit(0);
