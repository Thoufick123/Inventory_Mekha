<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../app/controllers/StockController.php');
$view = file_get_contents(__DIR__ . '/../app/views/concern/stocks.php');

$checks = [
    'Stock rows include the catalog product name instead of reusing the category label' =>
        str_contains($controller, 'cat.name AS catalog_name')
        && str_contains($controller, '$stock[\'product_name\'] = trim((string)($stock[\'catalog_name\'] ?? \'\'))'),
    'Production color lookup is guarded against tables without a color column' =>
        str_contains($controller, "hasTableColumn('qc_inspection_items', 'color')")
        && str_contains($controller, "hasTableColumn('qc_piece_items', 'color')")
        && str_contains($controller, "hasTableColumn('stitching_items', 'color')")
        && !str_contains($controller, 'SELECT color FROM cutting_items')
        && !str_contains($controller, 'SELECT color FROM production_programs')
        && str_contains($controller, "'resolved_color'"),
    'Label design prefers the pattern of the production roll linked to the stitched variant' =>
        str_contains($controller, 'JOIN cutting_items ci ON ci.id = si.cutting_item_id')
        && str_contains($controller, 'JOIN program_rolls pr ON pr.id = ci.program_roll_id')
        && str_contains($controller, 'JOIN purchase_bill_items pbi ON pbi.id = pr.purchase_bill_item_id')
        && str_contains($controller, 'pbi.pattern')
        && str_contains($controller, 'pbi.specification'),
    'Stock QR preview displays product name, design, and the resolved color' =>
        str_contains($view, 'Product Name:')
        && str_contains($view, 'id="quickQrDesign"')
        && str_contains($view, 'id="quickQrProduct">Product</strong></div>')
        && str_contains($view, 'class="text-break"><span class="text-muted">Product Name:')
        && str_contains($view, 'data-product-name=')
        && str_contains($view, 'data-design=')
        && str_contains($view, '($s[\'resolved_color\'] ??'),
    'Quick QR modal populates its product and design from the stock row' =>
        str_contains($view, 'd.productName')
        && str_contains($view, 'd.design')
        && str_contains($view, 'quickQrProduct')
        && str_contains($view, 'quickQrDesign'),
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
