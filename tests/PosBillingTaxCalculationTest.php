<?php

require_once __DIR__ . '/../app/core/Autoloader.php';

use App\Services\PosBillingCalculator;

$excluded = PosBillingCalculator::calculateLine(100, 2, 10, 5, 'exclude');
$included = PosBillingCalculator::calculateLine(105, 2, 10, 5, 'include');
$includedNoDiscount = PosBillingCalculator::calculateLine(105, 1, 0, 5, 'include');
$exempt = PosBillingCalculator::calculateLine(100, 2, 5, 0, 'include');
$complementary = PosBillingCalculator::calculateLine(100, 2, 0, 18, 'exclude', true);
$paidCheckout = PosBillingCalculator::calculateCheckoutPayment(190, 10, 'paid', 0);
$partialCheckout = PosBillingCalculator::calculateCheckoutPayment(190, 10, 'partial', 50);
$unpaidCheckout = PosBillingCalculator::calculateCheckoutPayment(190, 10, 'unpaid', 0);
$salesController = file_get_contents(__DIR__ . '/../app/controllers/SalesController.php');
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
    'Complementary products are free regardless of their stored selling price' =>
        $complementary['base_unit_price'] === 0.0
        && $complementary['taxable_amount'] === 0.0
        && $complementary['gst_amount'] === 0.0
        && $complementary['billed_amount'] === 0.0,
    'POS applies the complementary flag in the product lookup and checkout' =>
        str_contains($salesController, 'v.is_complementary')
        && str_contains($salesController, 'SELECT price, is_complementary')
        && str_contains($salesController, '$isComplementary')
        && str_contains($billingView, 'price: isComplementary ? 0 : Number(v.price)'),
    'Shipping is added to paid, partial, and unpaid invoice totals' =>
        $paidCheckout === ['net_amount' => 200.0, 'paid_amount' => 200.0, 'balance_due' => 0.0]
        && $partialCheckout === ['net_amount' => 200.0, 'paid_amount' => 50.0, 'balance_due' => 150.0]
        && $unpaidCheckout === ['net_amount' => 200.0, 'paid_amount' => 0.0, 'balance_due' => 200.0],
    'Partial payment rejects zero and full-total advances' =>
        (static function (): bool {
            foreach ([0.0, 200.0] as $advance) {
                try {
                    PosBillingCalculator::calculateCheckoutPayment(190, 10, 'partial', $advance);
                } catch (InvalidArgumentException $e) {
                    continue;
                }
                return false;
            }
            return true;
        })(),
    'Billing form offers GST price mode and editable quantity' =>
        strpos($billingView, 'name="gst_price_mode"') !== false
        && strpos($billingView, 'value="include"') !== false
        && strpos($billingView, 'value="exclude"') !== false
        && strpos($billingView, 'item-qty-input') !== false,
    'Payment selector offers four accessible modes and split breakdown' =>
        substr_count($billingView, 'class="pay-method-btn') === 4
        && strpos($billingView, 'aria-pressed="true"') !== false
        && strpos($billingView, 'id="split_payment_section"') !== false
        && strpos($billingView, 'id="split_auto_balance"') !== false
        && strpos($billingView, 'id="split_balance_status"') !== false,
    'Payment details match the compact billing layout' =>
        strpos($billingView, 'id="pos_shipping_amount"') !== false
        && strpos($billingView, 'id="pos_payment_status" name="payment_status"') !== false
        && strpos($billingView, 'id="pos_payment_status" class="form-select fw-semibold" disabled') === false
        && strpos($billingView, 'id="pos_payment_screenshot"') !== false
        && strpos($billingView, 'value="partial">Partially Paid / COD') !== false
        && strpos($billingView, 'id="advance_paid_amount"') !== false
        && strpos($billingView, 'id="pending_cod_amount"') !== false,
    'Shipping updates the invoice total before payment calculations' =>
        strpos($billingView, 'itemsTotal + shippingAmount') !== false
        && strpos($billingView, "shippingAmountInput.addEventListener('input'") !== false,
    'Cash reconciliation accepts an entered amount and calculates due and change' =>
        strpos($billingView, 'id="cash_customer_gave"') !== false
        && strpos($billingView, 'id="display_amount_due"') !== false
        && strpos($billingView, 'id="display_change_return"') !== false
        && strpos($billingView, 'denom-cash-input') === false
        && strpos($billingView, 'display_change_hint') === false
        && strpos($billingView, 'const amountDue = Math.max(0, netPayable)') !== false
        && strpos($billingView, 'Math.max(0, cashGiven - netPayable)') !== false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
