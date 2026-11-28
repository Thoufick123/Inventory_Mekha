<?php

require_once __DIR__ . '/../app/core/Autoloader.php';

use App\Services\CouponService;

$couponView = file_get_contents(__DIR__ . '/../app/views/concern/coupons.php');
$couponController = file_get_contents(__DIR__ . '/../app/controllers/CouponController.php');
$couponService = file_get_contents(__DIR__ . '/../app/services/CouponService.php');
$couponPrintView = file_get_contents(__DIR__ . '/../app/views/concern/coupon_print.php');
$billingView = file_get_contents(__DIR__ . '/../app/views/concern/billing.php');
$salesController = file_get_contents(__DIR__ . '/../app/controllers/SalesController.php');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$couponTableStart = strpos($schema, 'CREATE TABLE IF NOT EXISTS `pos_coupons`');
$couponTableEnd = strpos($schema, 'CREATE TABLE IF NOT EXISTS `pos_customer_channels`', $couponTableStart);
$couponTableSchema = substr($schema, $couponTableStart, $couponTableEnd - $couponTableStart);

$legacyCoupon = [
    'code' => 'OPENCOUPON',
    'status' => 'ACTIVE',
    'expires_at' => null,
    'max_uses' => 0,
    'used_count' => 0,
    'customer_phone' => '9999999999',
    'customer_name' => 'Legacy Customer',
    'discount_type' => 'FIXED',
    'discount_value' => 100,
    'min_redemption_amount' => 0,
];
$couponResult = CouponService::validateAndCalculate($legacyCoupon, 'DIRECT', 500, 0);
$singleProductCouponResult = CouponService::validateAndCalculate($legacyCoupon, 'PRODUCT_BUY', 500, 250, 1);
$multipleCouponProductsRejected = false;
try {
    CouponService::validateAndCalculate($legacyCoupon, 'PRODUCT_BUY', 500, 250, 2);
} catch (InvalidArgumentException $exception) {
    $multipleCouponProductsRejected = strpos($exception->getMessage(), 'exactly one product') !== false;
}
$missingCouponProductRejected = false;
try {
    CouponService::validateAndCalculate($legacyCoupon, 'PRODUCT_BUY', 500, 0, 0);
} catch (InvalidArgumentException $exception) {
    $missingCouponProductRejected = strpos($exception->getMessage(), 'exactly one product') !== false;
}

$checks = [
    'Coupon create and edit forms no longer accept customer targeting' =>
        strpos($couponView, 'name="customer_phone"') === false
        && strpos($couponView, 'name="customer_name"') === false
        && strpos($couponView, 'editCouponPhone') === false
        && strpos($couponView, 'editCouponName') === false,
    'Coupon management and voucher no longer expose customer targeting' =>
        strpos($couponController, 'customer_phone') === false
        && strpos($couponController, 'customer_name') === false
        && strpos($couponPrintView, "coupon['customer_phone']") === false
        && strpos($couponPrintView, "coupon['customer_name']") === false,
    'Coupon checkout no longer restricts coupons by customer phone' =>
        strpos($billingView, 'data-phone=') === false
        && strpos($billingView, 'couponPhone') === false
        && strpos($couponService, 'customer_phone') === false
        && strpos($couponService, 'customer_name') === false
        && $couponResult['discount_amount'] === 100.0,
    'Product Buy coupons apply to exactly one selected cart item' =>
        $singleProductCouponResult['discount_amount'] === 100.0
        && $multipleCouponProductsRejected
        && $missingCouponProductRejected
        && strpos($billingView, 'type="radio" name="coupon_product_line"') !== false
        && strpos($billingView, 'Coupon product') !== false
        && strpos($salesController, '$couponProductCount++') !== false,
    'Customer-linked coupon issuance has been removed from POS' =>
        strpos($billingView, 'issue_coupon_enabled') === false
        && strpos($salesController, 'issue_coupon_enabled') === false
        && !method_exists(CouponService::class, 'issueCustomerCoupon'),
    'New coupon table schema omits customer-targeting columns' =>
        $couponTableStart !== false
        && $couponTableEnd !== false
        && strpos($couponTableSchema, 'customer_phone') === false
        && strpos($couponTableSchema, 'customer_name') === false,
];

$failed = false;
foreach ($checks as $title => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $title . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
