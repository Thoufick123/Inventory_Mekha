<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class PosBillingCalculator {
    public static function calculateLine(
        float $unitPrice,
        float $quantity,
        float $discount,
        float $gstRate,
        string $priceMode
    ): array {
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
