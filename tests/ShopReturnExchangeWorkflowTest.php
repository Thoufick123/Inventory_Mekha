<?php

$routes = file_get_contents(__DIR__ . '/../app/routes.php');
$salesController = file_get_contents(__DIR__ . '/../app/controllers/SalesController.php');
$productionReturnController = file_get_contents(__DIR__ . '/../app/controllers/ProductionReturnController.php');
$returnView = file_get_contents(__DIR__ . '/../app/views/concern/return_exchange.php');

$updateStart = strpos($salesController, 'public function updateReturn(): void {');
$updateEnd = strpos($salesController, 'public function deleteReturn(): void {', $updateStart);
$updateMethod = substr($salesController, $updateStart, $updateEnd - $updateStart);
$deleteStart = $updateEnd;
$deleteEnd = strpos($salesController, 'public function ', $deleteStart + 1);
$deleteMethod = substr($salesController, $deleteStart, $deleteEnd - $deleteStart);

$storeStart = strpos($productionReturnController, 'public function store(): void {');
$storeEnd = strpos($productionReturnController, 'public function view(', $storeStart);
$storeMethod = substr($productionReturnController, $storeStart, $storeEnd - $storeStart);
$replacementStart = strpos($salesController, 'public function lookupShopReturnReplacement(): void {');
$replacementEnd = strpos($salesController, 'public function returnExchange(): void {', $replacementStart);
$replacementMethod = substr($salesController, $replacementStart, $replacementEnd - $replacementStart);

$checks = [
    'Shop return flow searches customers and lists eligible invoices' =>
        strpos($routes, '/api/shop-returns/customer-invoices') !== false
        && strpos($routes, "\$router->get('/production/returns/create', [SalesController::class, 'returnExchange']") !== false
        && strpos($salesController, 'public function lookupShopReturnInvoices(): void') !== false
        && strpos($returnView, 'Search Shop Customer Order') !== false
        && strpos($returnView, 'Already Used') !== false,
    'Invoice selection opens the item return and exchange workflow' =>
        strpos($routes, '/api/shop-returns/invoice') !== false
        && strpos($routes, '/api/shop-returns/replacement') !== false
        && strpos($salesController, 'QrService::formatFinishedGoodsCode(') !== false
        && strpos($returnView, 'Step 2: Select Items &amp; Action Type') !== false
        && strpos($returnView, 'STEP 2A: RETURNING OLD ITEMS') !== false
        && strpos($returnView, 'STEP 2B: ADD REPLACEMENT PRODUCTS') !== false,
    'Scanning selects the matching invoice item and highlights its row' =>
        strpos($returnView, 'data-tag-code=') !== false
        && strpos($returnView, "const itemCode = pieceTag ? 'Piece Tag: ' + pieceTag : String(item.sku_code || '')") !== false
        && strpos($returnView, 'Piece Tag: ') !== false
        && strpos($returnView, 'shop-return-scanned-row') !== false
        && strpos($returnView, 'scrollIntoView({behavior: \'smooth\', block: \'center\'})') !== false,
    'Replacement scans only return products with positive Main Store stock' =>
        strpos($replacementMethod, 'QrService::lookupVariant($sku, $invoiceConcernId)') !== false
        && strpos($replacementMethod, "COALESCE(s.qty, 0) > 0") !== false
        && strpos($storeMethod, "location = 'Main Store'") !== false
        && strpos($storeMethod, 'requestedOutQtyByVariant[$variantId] > $availableQty') !== false,
    'Return submission is invoice-linked and prevents an invoice being used twice' =>
        strpos($returnView, 'source_workflow" value="shop"') !== false
        && strpos($storeMethod, 'SELECT id, concern_id, customer_id, total_amount, tax_amount') !== false
        && strpos($storeMethod, 'FROM returns_exchanges WHERE sales_invoice_id = ? LIMIT 1 FOR UPDATE') !== false
        && strpos($storeMethod, 'FROM production_returns WHERE sales_invoice_id = ? LIMIT 1 FOR UPDATE') !== false
        && strpos($storeMethod, 'This invoice has already been used for a return or exchange.') !== false,
    'Invoice-based records cannot be edited or deleted after processing' =>
        strpos($updateMethod, 'Invoice-based returns are locked to preserve their full item and stock history.') !== false
        && strpos($deleteMethod, 'Invoice-based returns are locked to preserve their full item and stock history.') !== false,
    'Returned quantities are validated against the invoice and item details are stored' =>
        strpos($storeMethod, 'Returned quantity cannot exceed the quantity sold.') !== false
        && strpos($storeMethod, 'INSERT INTO return_exchange_items') !== false
        && strpos($storeMethod, 'StockService::move(') !== false
        && strpos($storeMethod, 'RMA Damaged Return Received') !== false
        && strpos($storeMethod, "'CONCERN',\n                        true") !== false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
