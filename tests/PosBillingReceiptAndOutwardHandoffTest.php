<?php

$billingView = file_get_contents(__DIR__ . '/../app/views/concern/billing.php');
$outwardView = file_get_contents(__DIR__ . '/../app/views/concern/outward.php');
$outwardController = file_get_contents(__DIR__ . '/../app/controllers/OutwardController.php');

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
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
