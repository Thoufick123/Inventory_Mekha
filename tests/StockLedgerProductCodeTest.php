<?php

$stockController = file_get_contents(__DIR__ . '/../app/controllers/StockController.php');
$stockLedgerView = file_get_contents(__DIR__ . '/../app/views/concern/stock_ledger.php');
$exportStocksStart = strpos($stockController, 'public function exportStocks(): void {');
$exportLedgerStart = strpos($stockController, 'public function exportLedger(): void {');
$exportStocks = substr($stockController, $exportStocksStart, $exportLedgerStart - $exportStocksStart);
$exportLedger = substr($stockController, $exportLedgerStart);

$checks = [
    'Stock ledger displays generated QR/tag code beside SKU' =>
        strpos($stockController, "generateFinishedGoodsCode(\$variantId)") !== false
        && strpos($stockLedgerView, 'QR / Tag:') !== false
        && strpos($stockLedgerView, '$l[\'qr_tag_code\']') !== false,
    'Stock ledger export includes QR/tag code and all ledger rows' =>
        strpos($exportLedger, 'Product QR / Tag Code') !== false
        && strpos($exportLedger, '"s.qty > 0"') === false
        && strpos($exportLedger, "s.location NOT LIKE") === false,
    'Current stock export keeps its existing live-stock filters' =>
        strpos($exportStocks, '"s.qty > 0"') !== false
        && strpos($exportStocks, "s.location NOT LIKE '%:DAMAGED%'") !== false
        && strpos($exportStocks, "s.location NOT LIKE '%:QUARANTINE%'") !== false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
