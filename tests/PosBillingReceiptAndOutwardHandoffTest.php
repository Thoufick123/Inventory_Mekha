<?php

$billingView = file_get_contents(__DIR__ . '/../app/views/concern/billing.php');
$outwardView = file_get_contents(__DIR__ . '/../app/views/concern/outward.php');
$outwardController = file_get_contents(__DIR__ . '/../app/controllers/OutwardController.php');
$addModalStart = strpos($outwardView, 'id="modalAddOutward"');
$editModalStart = strpos($outwardView, 'id="modalEditOutward"', $addModalStart);
$addModalView = substr($outwardView, $addModalStart, $editModalStart - $addModalStart);

$checks = [
    'POS success modal offers A4 and thermal receipt formats' =>
        strpos($billingView, "printReceiptArea('a4')") !== false
        && strpos($billingView, "printReceiptArea('pos')") !== false
        && strpos($billingView, '@page{size:A4') !== false
        && strpos($billingView, '@page{size:80mm') !== false
        && strpos($billingView, 'id="a4_invoice_print_area"') !== false
        && strpos($billingView, '<h3>Bill To</h3>') !== false
        && strpos($billingView, '<th>Invoice Total</th>') !== false
        && strpos($billingView, "format === 'a4' ? 'a4_invoice_print_area'") !== false,
    'POS success modal sends the invoice to the Outward workflow' =>
        strpos($billingView, 'Move to Outward') !== false
        && strpos($billingView, 'pos_invoice_id=\' . (int)$printInvoice[\'id\']') !== false,
    'Outward opens with concern-scoped invoice items prefilled' =>
        strpos($outwardController, 'WHERE si.id = ?') !== false
        && strpos($outwardController, 'AND si.concern_id = ?') !== false
        && strpos($outwardController, 'GROUP BY sii.variant_id, v.sku_code, v.name, v.size') !== false
        && strpos($outwardView, 'initialPosInvoiceItems') !== false
        && strpos($outwardView, 'openNewDispatchModal(\'finished_goods\')') !== false,
    'Invoice handoff is one-time and does not deduct POS stock twice' =>
        strpos($outwardController, 'already linked to an outward') !== false
        && strpos($outwardController, 'outward items must match the POS invoice items') !== false
        && strpos($outwardController, 'if ($posInvoiceId === 0)') !== false
        && strpos($outwardController, 'if (empty($out[\'sales_invoice_id\']))') !== false
        && strpos($outwardController, 'sales_invoice_id, gate_pass_no') !== false,
    'Transferred outward retains its POS invoice reference' =>
        strpos($outwardController, 'si.invoice_no AS sales_invoice_no') !== false
        && strpos($outwardView, '$o[\'sales_invoice_no\']') !== false,
    'Outward scanner availability matches the concern Main Store stock deducted on dispatch' =>
        strpos($outwardController, "s.concern_id = v.concern_id") !== false
        && strpos($outwardController, "s.location = 'Main Store'") !== false
        && strpos($outwardView, 'refreshFgStockAvailability') !== false
        && strpos($outwardView, 'scannedFgMap[id].qty >= stock') !== false
        && strpos($outwardView, 'item.qty = item.stock') !== false
        && strpos($outwardView, 'max="${it.stock}"') !== false
        && strpos($outwardView, 'it.qty >= it.stock') !== false
        && strpos($outwardView, 'item.qty > item.stock') !== false,
    'New outward form omits destination warehouse and starts logistics fields blank' =>
        strpos($addModalView, 'Destination Warehouse') === false
        && strpos($addModalView, 'name="warehouse_name"') === false
        && strpos($addModalView, 'destination_warehouse_fg') === false
        && strpos($addModalView, 'name="vehicle_number"') !== false
        && strpos($addModalView, 'name="driver_name"') !== false
        && strpos($addModalView, 'name="driver_contact"') !== false
        && strpos($addModalView, 'name="transporter_name"') !== false
        && strpos($addModalView, 'name="total_boxes" id="fgTotalBoxesInput" class="form-control form-control-sm" value=') === false
        && strpos($addModalView, 'value="rtt31232"') === false
        && strpos($addModalView, 'value="Vanaja"') === false
        && strpos($addModalView, 'value="9943557998"') === false
        && strpos($addModalView, 'value="GRM In-house Transport"') === false
        && strpos($outwardController, "trim(\$_POST['vehicle_number'] ?? '')") !== false
        && strpos($outwardController, "trim(\$_POST['driver_name'] ?? '')") !== false
        && strpos($outwardController, "trim(\$_POST['transporter_name'] ?? '')") !== false,
    'Outward list and detail actions are restricted to the source concern' =>
        strpos($outwardController, 'AND o.concern_id = ?') !== false
        && strpos($outwardController, 'OR o.destination_concern_id = ?') === false
        && strpos($outwardController, 'isOutwardInSourceScope($out)') !== false
        && strpos($outwardController, 'if ($authConcernId && $authConcernId !== $cid)') !== false
        && strpos($outwardView, 'concernScope') !== false
        && strpos($outwardView, 'name="concern_id" value="<?= (int)($selectedConcernId ?? 0) ?>"') !== false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
