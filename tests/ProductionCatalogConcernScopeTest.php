<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../app/controllers/ProductionController.php');
$view = file_get_contents(__DIR__ . '/../app/views/concern/catalog_create.php');
$quickCategoryStart = strpos($controller, 'public function quickAddCategoryAjax(): void');
$quickSubcategoryStart = strpos($controller, 'public function quickAddSubcategoryAjax(): void');
$quickCategoryMethod = substr($controller, $quickCategoryStart, $quickSubcategoryStart - $quickCategoryStart);

$checks = [
    'Product style category lookup accepts the selected concern and shared categories' =>
        str_contains($controller, 'OR concern_id = 1 OR concern_id IS NULL')
        && str_contains($controller, 'Selected Main Category does not belong to the selected concern.'),
    'Product style validates that selected lower hierarchy levels belong to their parents' =>
        str_contains($controller, 'Selected Sub Category does not belong to the chosen Main Category.')
        && str_contains($controller, 'Selected Sub Sub Category does not belong to the chosen Sub Category.'),
    'Quick category creation requires a real concern rather than assuming concern ID 1' =>
        str_contains($quickCategoryMethod, 'Auth::concernId() ?: (int)($_POST[\'concern_id\'] ?? 0)')
        && str_contains($quickCategoryMethod, 'Select a valid concern before creating a category.')
        && !str_contains($quickCategoryMethod, '$cid = Auth::concernId() ?: 1;'),
    'Super Admin can choose the target concern when creating a category with no concern selected' =>
        str_contains($view, 'name="concern_id" class="form-select" required')
        && str_contains($view, 'id="catalogStyleConcernId" class="form-select" required')
        && str_contains($view, 'data-concern='),
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
