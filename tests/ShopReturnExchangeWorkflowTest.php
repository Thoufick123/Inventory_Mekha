<?php

$routes = file_get_contents(__DIR__ . '/../app/routes.php');
$salesController = file_get_contents(__DIR__ . '/../app/controllers/SalesController.php');
$productionReturnController = file_get_contents(__DIR__ . '/../app/controllers/ProductionReturnController.php');
$returnView = file_get_contents(__DIR__ . '/../app/views/concern/return_exchange.php');
$productionReturnView = file_get_contents(__DIR__ . '/../app/views/production/returns/view.php');
$productionReturnIndex = file_get_contents(__DIR__ . '/../app/views/production/returns/index.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$migration = file_get_contents(__DIR__ . '/../database/migrate_all_tables.php');
$returnExchangeSchema = file_get_contents(__DIR__ . '/../app/services/ReturnExchangeSchema.php');

$updateStart = strpos($salesController, 'public function updateReturn(): void {');
$updateEnd = strpos($salesController, 'public function deleteReturn(): void {', $updateStart);
$updateMethod = substr($salesController, $updateStart, $updateEnd - $updateStart);
$deleteStart = $updateEnd;
$deleteEnd = strpos($salesController, 'public function ', $deleteStart + 1);
$deleteMethod = substr($salesController, $deleteStart, $deleteEnd - $deleteStart);

$storeStart = strpos($productionReturnController, 'public function store(): void {');
$storeEnd = strpos($productionReturnController, 'public function view(', $storeStart);
$storeMethod = str_replace("\r\n", "\n", substr($productionReturnController, $storeStart, $storeEnd - $storeStart));
$replacementItemInsertMatches = preg_match(
    '/INSERT INTO production_return_exchange_items \(\s*return_id, direction, variant_id, tag_code, scanned_codes, qty, unit_price, total_amount, created_at\s*\) VALUES \((.*?)\)",\s*\[(.*?)\]/s',
    $storeMethod,
    $replacementItemInsert
);
$replacementStart = strpos($salesController, 'public function lookupShopReturnReplacement(): void {');
$replacementEnd = strpos($salesController, 'public function returnExchange(): void {', $replacementStart);
$replacementMethod = substr($salesController, $replacementStart, $replacementEnd - $replacementStart);

$checks = [
    'Shop return flow searches customers and lists eligible invoices' =>
        strpos($routes, '/api/shop-returns/customer-invoices') !== false
        && strpos($routes, "\$router->get('/production/returns/create', [SalesController::class, 'returnExchange']") !== false
        && strpos($salesController, 'public function lookupShopReturnInvoices(): void') !== false
        && strpos($returnView, 'Search by Customer Phone or Invoice No.') !== false
        && strpos($returnView, 'Already Used') !== false,
    'Return lookup accepts exact customer phone or invoice number scans' =>
        strpos($salesController, 'c.phone = ? OR si.invoice_no = ?') !== false
        && strpos($salesController, "\$_GET['query'] ?? \$_GET['phone']") !== false
        && strpos($returnView, '?query=\' + encodeURIComponent(searchTerm)') !== false
        && strpos($returnView, 'Scan or enter customer phone or invoice number') !== false,
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
    'Replacement scans and delivery use only good shop stock' =>
        strpos($replacementMethod, 'QrService::lookupVariant($sku, $invoiceConcernId)') !== false
        && strpos($replacementMethod, "COALESCE(s.qty, 0) > 0") !== false
        && strpos($replacementMethod, "location IN ('Main Store', 'Main Store (WH-01)')") !== false
        && strpos($returnView, 'Replacement product has no good available shop stock.') !== false
        && strpos($storeMethod, "location IN ('Main Store', 'Main Store (WH-01)')") !== false
        && strpos($storeMethod, 'foreach ([\'Main Store\', \'Main Store (WH-01)\'] as $stockLocation)') !== false
        && strpos($storeMethod, 'requestedOutQtyByVariant[$variantId] > $availableQty') !== false,
    'Return submission is invoice-linked and prevents an invoice being used twice' =>
        strpos($returnView, 'source_workflow" value="shop"') !== false
        && strpos($storeMethod, 'SELECT id, concern_id, customer_id, total_amount, tax_amount') !== false
        && strpos($storeMethod, 'FROM returns_exchanges re') !== false
        && strpos($storeMethod, 'FROM production_returns') !== false
        && strpos($storeMethod, 'This invoice has already been used for a return or exchange.') !== false,
    'Invoice-linked records expose edit, detailed view, and reversible cancellation' =>
        strpos($updateMethod, 'UPDATE production_returns SET return_date = ?, reason = ?') !== false
        && strpos($updateMethod, "\$productionReturn['status'] === 'CANCELLED'") !== false
        && strpos($deleteMethod, 'Use the RMA cancellation action to reverse this invoice-linked return safely.') !== false
        && strpos($returnView, '/production/returns/view/\' . (int)$r[\'production_return_id\']') !== false
        && strpos($returnView, 'name="return_to" value="return_exchange"') !== false
        && strpos($productionReturnView, 'Settlement & Payment') !== false
        && strpos($productionReturnView, 'RMA Item Details') !== false,
    'Invoice-linked cancellation reverses ledger movements and allows a replacement RMA' =>
        strpos($productionReturnController, 'RMA Cancellation Reversal [{$return[\'return_no\']}]') !== false
        && strpos($productionReturnController, "UPDATE production_returns SET status = 'CANCELLED'") !== false
        && strpos($salesController, "linked_pr.status <> 'CANCELLED'") !== false
        && strpos($productionReturnController, "status <> 'CANCELLED'") !== false,
    'Production RMA registry exposes edit and stock-safe cancellation actions' =>
        strpos($productionReturnIndex, 'data-bs-target="#modalEditRma"') !== false
        && strpos($productionReturnIndex, 'data-bs-target="#modalCancelRma"') !== false
        && strpos($productionReturnIndex, 'return_to" value="production_returns"') !== false
        && strpos($routes, "'/production/returns/update', [ProductionReturnController::class, 'update'], 'return_exchange', 'edit'") !== false
        && strpos($routes, "'/production/returns/cancel', [ProductionReturnController::class, 'cancel'], 'return_exchange', 'delete'") !== false
        && strpos($productionReturnController, 'public function update(): void') !== false
        && strpos($productionReturnController, 'A cancelled return cannot be edited.') !== false
        && strpos($productionReturnController, "UPDATE returns_exchanges SET return_date = ?, reason = ?") !== false,
    'Invoice-linked RMA edits reuse creation fields and rebalance stock safely' =>
        strpos($productionReturnIndex, '/m/return_exchange?edit_rma=') !== false
        && strpos($returnView, 'shop_return_change_invoice') !== false
        && strpos($returnView, 'shop_return_settlement_reference_input') !== false
        && strpos($returnView, 'shop_return_photo_url_input') !== false
        && strpos($returnView, 'openRmaForEdit') !== false
        && strpos($routes, '/api/shop-returns/edit/{id}') !== false
        && strpos($productionReturnController, 'updateShopReturnTransaction') !== false
        && strpos($productionReturnController, 'RMA Edit Reversal [{$return[\'return_no\']}]') !== false
        && strpos($productionReturnController, 'DamageService::cancel(') !== false
        && strpos($productionReturnController, 'sales_invoice_item_id') !== false
        && strpos($salesController, 'lookupShopReturnForEdit(int $id)') !== false
        && strpos($salesController, '$returnId = $id;') !== false,
    'Return creation captures invoice, returned-item, and replacement scan codes' =>
        strpos($salesController, 'sii.scanned_codes') !== false
        && strpos($returnView, 'Invoice scanned:') !== false
        && strpos($returnView, 'item.return_scanned_codes.push(scannedCode)') !== false
        && strpos($returnView, 'existing.scanned_codes.push(sku)') !== false
        && strpos($storeMethod, 'encodeScannedCodes($item[\'scanned_codes\'] ?? [])') !== false
        && strpos($storeMethod, 'tag_code, scanned_codes, qty') !== false,
    'Replacement scan insert binds every persisted value' =>
        $replacementItemInsertMatches === 1
        && substr_count($replacementItemInsert[1], '?') === substr_count($replacementItemInsert[2], ',') + 1
        && strpos($replacementItemInsert[2], '$scannedCodesJson') !== false,
    'Return list and detailed view display captured scan codes' =>
        strpos($salesController, 'scanned_code_display') !== false
        && strpos($returnView, '<th>Scanned Codes</th>') !== false
        && strpos($returnView, '$r[\'scanned_code_display\']') !== false
        && strpos($productionReturnController, 'withScannedCodeDisplay($items)') !== false
        && strpos($productionReturnView, 'Scanned codes:') !== false,
    'Return-item scan storage is present in fresh and migrated databases' =>
        strpos($schema, '`scanned_codes` text DEFAULT NULL') !== false
        && strpos($migration, 'ADD COLUMN `scanned_codes` TEXT NULL AFTER `tag_code`') !== false
        && strpos($schema, '`sales_invoice_item_id` int(11) DEFAULT NULL') !== false
        && strpos($migration, 'ADD COLUMN `sales_invoice_item_id` INT NULL AFTER `variant_id`') !== false,
    'Return pages ensure existing databases have the scan and invoice-line columns' =>
        strpos($returnExchangeSchema, 'ALTER TABLE `production_return_exchange_items` ADD COLUMN `sales_invoice_item_id` INT NULL') !== false
        && strpos($returnExchangeSchema, 'ALTER TABLE `production_return_exchange_items` ADD COLUMN `scanned_codes` TEXT NULL') !== false
        && strpos($returnExchangeSchema, 'ALTER TABLE `sales_invoice_items` ADD COLUMN `scanned_codes` TEXT NULL') !== false
        && strpos($salesController, 'ReturnExchangeSchema::ensure();') !== false
        && strpos($productionReturnController, 'ReturnExchangeSchema::ensure();') !== false,
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
