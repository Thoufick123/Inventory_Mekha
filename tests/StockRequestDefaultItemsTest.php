<?php

$view = file_get_contents(__DIR__ . '/../app/views/production/stock_requests/index.php');

$checks = [
    'Indent dialog starts with no selected item rows' =>
        strpos($view, '<tbody id="indentItemsTableBody">') !== false
        && strpos($view, '<!-- Selected items are added here -->') !== false,
    'No ICECREAM-specific item is automatically added' =>
        strpos($view, 'ICECREAM') === false
        && strpos($view, 'SKU-CAT-MEKHA') === false
        && strpos($view, 'addIndentItemRow(opt.value') === false,
    'Selecting a variant still adds it to the requisition' =>
        strpos($view, 'addIndentItemRow(variantId, name, sku, size)') !== false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
