<?php

declare(strict_types=1);

$catalogView = file_get_contents(__DIR__ . '/../app/views/concern/catalog_create.php');
$programView = file_get_contents(__DIR__ . '/../app/views/concern/program_create.php');

$checks = [
    'New catalog styles leave category and size attributes unselected' =>
        str_contains($catalogView, '$selectedSizes = [];')
        && str_contains($catalogView, '$isSel = $isEdit && $style[\'category_id\'] == $cat[\'id\'];')
        && str_contains($catalogView, '$isChecked = in_array($sz, $selectedSizes);'),
    'Program roll selection leaves catalog hierarchy empty until manually selected' =>
        str_contains($programView, 'Catalog hierarchy is selected explicitly for each program.')
        && str_contains($programView, 'catalogSelect.value = \'\';')
        && str_contains($programView, 'catSelect.value = \'\';')
        && str_contains($programView, 'subcatSelect.innerHTML = \'<option value="">-- Select Category First --</option>\';')
        && str_contains($programView, 'subsubcatSelect.innerHTML = \'<option value="">-- Select Sub Category First --</option>\';'),
];

$failed = false;
foreach ($checks as $description => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $description . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
