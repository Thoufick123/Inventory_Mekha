<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../app/controllers/OutwardController.php');
$routes = file_get_contents(__DIR__ . '/../app/routes.php');
$modules = file_get_contents(__DIR__ . '/../app/core/ModuleRegistry.php');
$outwardView = file_get_contents(__DIR__ . '/../app/views/concern/outward.php');
$labelView = file_get_contents(__DIR__ . '/../app/views/concern/label_generation.php');

$checks = [
    'Standalone batch CRUD routes are removed while batch print documents remain available' =>
        !str_contains($routes, "'/m/batch")
        && !str_contains($routes, "'/production/batches/store'")
        && str_contains($routes, '/production/batches/challan/{id}')
        && str_contains($routes, '/production/batches/label/{id}'),
    'Batch module is absent from navigation even if still present in stored modules' =>
        !str_contains($modules, '\'code\' => \'batch\'')
        && str_contains($modules, '$code !== \'batch\''),
    'Outward batch list is scoped to QC-inwarded batches with remaining stock' =>
        str_contains($controller, "UPPER(b.status) = 'APPROVED'")
        && str_contains($controller, 'WHERE b.concern_id = ?')
        && str_contains($controller, 'JOIN qc_inspections qi ON qi.id = b.qc_batch_id')
        && str_contains($controller, "qc_ledger.ref_module = 'Production QC'")
        && str_contains($controller, 'AS dispatch_qty')
        && str_contains($controller, 'LEAST(batch_line.qty')
        && str_contains($controller, 's.location IN (\'Main Store\', \'Main Store (WH-01)\')'),
    'Outward locks the selected batch and dispatches no more than remaining stock' =>
        str_contains($controller, "AND UPPER(status) = 'APPROVED'")
        && str_contains($controller, 'LIMIT 1 FOR UPDATE')
        && str_contains($controller, '$dispatchQty = min($requestedQty, $this->mainStoreAvailableQty')
        && str_contains($controller, '$this->deductMainStoreStock($cid, $line[\'variant_id\'], $line[\'qty\']')
        && str_contains($controller, "ref_module = 'Production QC'")
        && str_contains($controller, "'Main Store (WH-01)'"),
    'Unified outward scanner exposes batch identifiers and label generation points to Outward' =>
        str_contains($outwardView, 'data-concern=')
        && str_contains($outwardView, 'data-available-qty=')
        && str_contains($outwardView, 'data-qc-no=')
        && str_contains($outwardView, 'id="outwardScanInput"')
        && str_contains($outwardView, 'id="dispatchBatchSelect" class="d-none"')
        && str_contains($outwardView, 'PRODUCT CODE / BATCH NO.')
        && str_contains($outwardView, '<span class="font-monospace fw-semibold text-primary">${it.sku}</span>')
        && strpos($outwardView, 'id="fgScannedTbody"') < strpos($outwardView, 'id="totalBoxesInput"')
        && str_contains($labelView, '/production/outward?tab=batch')
        && !str_contains($labelView, "url('/production/batches')"),
    'One scanner selects either an available QC-passed batch or a stocked finished good' =>
        str_contains($outwardView, 'function processOutwardScan(scannedCode)')
        && str_contains($outwardView, 'function processFinishedGoodScan(code)')
        && str_contains($outwardView, 'batchSelect.value = batch.value;')
        && str_contains($outwardView, 'One production batch is allowed per outward manifest.')
        && !str_contains($outwardView, 'id="mode_batch"')
        && !str_contains($outwardView, 'id="mode_fg"')
        && str_contains($controller, "UPPER(b.status) = 'APPROVED'")
        && str_contains($controller, "s.location IN ('Main Store', 'Main Store (WH-01)')"),
    'Unified dispatch combines one batch and scanned goods with locked shared stock validation' =>
        str_contains($controller, '($_POST[\'dispatch_mode\'] ?? \'\') === \'unified\'')
        && str_contains($controller, 'private function storeUnifiedDispatch()')
        && str_contains($controller, '$this->lockMainStoreAvailableQty($cid, (int)$variantId)')
        && str_contains($controller, '$batchQuantity + $finishedQuantity > $availableByVariant[$variantId]')
        && str_contains($controller, 'INSERT INTO production_outward_batches')
        && str_contains($controller, 'INSERT INTO outward_items (concern_id, outward_id, variant_id, qty)')
        && str_contains($controller, 'COALESCE((SELECT SUM(qty) FROM outward_items oi WHERE oi.outward_id = o.id), pob.pieces_count'),
    'Outward source uses the logged-in concern and destination excludes that source' =>
        str_contains($controller, '$sourceConcerns = $cid')
        && str_contains($controller, '$cid = $authConcernId ?:')
        && str_contains($controller, 'The destination concern must be different from the source concern.')
        && str_contains($outwardView, 'foreach ($sourceConcerns as $con)')
        && str_contains($outwardView, "excludeSourceConcernFromDestination('dispatchConcernSelect', conId)")
        && str_contains($outwardView, 'name="source_concern_id" id="sourceConcernSelect"')
        && str_contains($outwardView, "excludeSourceConcernFromDestination('edit_destination_concern_id', o.concern_id)"),
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
