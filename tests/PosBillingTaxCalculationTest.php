<?php

require_once __DIR__ . '/../app/core/Autoloader.php';

use App\Services\PosBillingCalculator;

$excluded = PosBillingCalculator::calculateLine(100, 2, 10, 5, 'exclude');
$included = PosBillingCalculator::calculateLine(105, 2, 10, 5, 'include');
$includedNoDiscount = PosBillingCalculator::calculateLine(105, 1, 0, 5, 'include');
$exempt = PosBillingCalculator::calculateLine(100, 2, 5, 0, 'include');
$billingView = file_get_contents(__DIR__ . '/../app/views/concern/billing.php');

$checks = [
    'Exclusive GST adds tax after quantity and discount' =>
        $excluded['base_unit_price'] === 100.0
        && $excluded['taxable_amount'] === 190.0
        && $excluded['gst_amount'] === 9.5
        && $excluded['billed_amount'] === 199.5,
    'Inclusive GST is extracted from the entered price' =>
        $included['base_unit_price'] === 100.0
        && $included['taxable_amount'] === 190.48
        && $included['gst_amount'] === 9.52
        && $included['billed_amount'] === 200.0,
    'Inclusive price without discount reconciles to base plus GST' =>
        $includedNoDiscount['taxable_amount'] + $includedNoDiscount['gst_amount'] === 105.0,
    'Exempt items do not add GST in either price mode' =>
        $exempt['taxable_amount'] === 195.0
        && $exempt['gst_amount'] === 0.0
        && $exempt['billed_amount'] === 195.0,
    'Billing form offers GST price mode and editable quantity' =>
        strpos($billingView, 'name="gst_price_mode"') !== false
        && strpos($billingView, 'value="include"') !== false
        && strpos($billingView, 'value="exclude"') !== false
        && strpos($billingView, 'item-qty-input') !== false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
