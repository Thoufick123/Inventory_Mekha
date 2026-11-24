<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class PosBillingCalculator {
    public static function calculateCheckoutPayment(
        float $itemsTotal,
        float $shippingAmount,
        string $paymentStatus,
        float $advancePaid
    ): array {
        $maxAmount = 9999999999.99;
        if (!is_finite($itemsTotal) || $itemsTotal < 0 || $itemsTotal > $maxAmount) {
            throw new InvalidArgumentException('Item total must be between 0 and 9,999,999,999.99.');
        }
        if (!is_finite($shippingAmount) || $shippingAmount < 0 || $shippingAmount > $maxAmount) {
            throw new InvalidArgumentException('Shipping must be between 0 and 9,999,999,999.99.');
        }
        if (!is_finite($advancePaid) || $advancePaid < 0 || $advancePaid > $maxAmount) {
            throw new InvalidArgumentException('Advance paid must be between 0 and 9,999,999,999.99.');
        }
        if (!in_array($paymentStatus, ['paid', 'partial', 'unpaid'], true)) {
            throw new InvalidArgumentException('Choose a valid payment status.');
        }

        $netAmount = round($itemsTotal + $shippingAmount, 2);
        if ($netAmount > $maxAmount) {
            throw new InvalidArgumentException('Invoice total exceeds the supported amount.');
        }

        if ($paymentStatus === 'paid') {
            $paidAmount = $netAmount;
        } elseif ($paymentStatus === 'partial') {
            if ($advancePaid <= 0 || $advancePaid >= $netAmount) {
                throw new InvalidArgumentException('Advance paid must be greater than zero and less than the invoice total.');
            }
            $paidAmount = round($advancePaid, 2);
        } else {
            if ($advancePaid > 0) {
                throw new InvalidArgumentException('Unpaid / COD invoices cannot have an advance payment.');
            }
            $paidAmount = 0.00;
        }

        return [
            'net_amount' => $netAmount,
            'paid_amount' => $paidAmount,
            'balance_due' => round($netAmount - $paidAmount, 2),
        ];
    }

    public static function calculateLine(
        float $unitPrice,
        float $quantity,
        float $discount,
        float $gstRate,
        string $priceMode,
        bool $isComplementary = false
    ): array {
        if ($isComplementary) {
            $unitPrice = 0.0;
        }
        if (!is_finite($unitPrice) || $unitPrice < 0) {
            throw new InvalidArgumentException('Unit price must be a non-negative number.');
        }
        if (!is_finite($quantity) || $quantity < 1 || floor($quantity) !== $quantity) {
            throw new InvalidArgumentException('Quantity must be a positive whole number.');
        }
        if (!is_finite($discount) || $discount < 0) {
            throw new InvalidArgumentException('Discount must be a non-negative number.');
        }
        if (!is_finite($gstRate) || $gstRate < 0 || $gstRate > 100) {
            throw new InvalidArgumentException('GST rate must be between 0 and 100.');
        }
        if (!in_array($priceMode, ['include', 'exclude'], true)) {
            throw new InvalidArgumentException('GST price mode must be include or exclude.');
        }

        $grossAmount = round($unitPrice * $quantity, 2);
        $appliedDiscount = min($discount, $grossAmount);
        $discountedAmount = round(max(0, $grossAmount - $appliedDiscount), 2);

        if ($priceMode === 'include' && $gstRate > 0) {
            $baseUnitPrice = round($unitPrice / (1 + ($gstRate / 100)), 2);
            $taxableAmount = round($discountedAmount / (1 + ($gstRate / 100)), 2);
            $gstAmount = round($discountedAmount - $taxableAmount, 2);
            $billedAmount = $discountedAmount;
        } else {
            $baseUnitPrice = round($unitPrice, 2);
            $taxableAmount = $discountedAmount;
            $gstAmount = round($taxableAmount * ($gstRate / 100), 2);
            $billedAmount = round($taxableAmount + $gstAmount, 2);
        }

        return [
            'base_unit_price' => $baseUnitPrice,
            'taxable_amount' => $taxableAmount,
            'gst_amount' => $gstAmount,
            'discount_amount' => $appliedDiscount,
            'billed_amount' => $billedAmount,
        ];
    }
}
