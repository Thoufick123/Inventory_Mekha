<?php
// app/services/CouponService.php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use InvalidArgumentException;

class CouponService
{
    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        try {
            DB::query("
                CREATE TABLE IF NOT EXISTS `pos_coupons` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `concern_id` INT NOT NULL,
                    `code` VARCHAR(50) NOT NULL,
                    `title` VARCHAR(150) NOT NULL,
                    `discount_type` ENUM('FIXED', 'PERCENT') NOT NULL DEFAULT 'FIXED',
                    `discount_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `min_redemption_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `max_uses` INT NOT NULL DEFAULT 1,
                    `used_count` INT NOT NULL DEFAULT 0,
                    `expires_at` DATE NULL,
                    `status` ENUM('ACTIVE', 'INACTIVE', 'REDEEMED', 'EXPIRED', 'CANCELLED') NOT NULL DEFAULT 'ACTIVE',
                    `issued_bill_id` INT NULL,
                    `redeemed_bill_id` INT NULL,
                    `redeemed_at` DATETIME NULL,
                    `created_by` INT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_coupon_concern_code` (`concern_id`, `code`),
                    INDEX `idx_coupon_concern` (`concern_id`),
                    INDEX `idx_coupon_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $siCols = array_column(DB::fetchAll("DESCRIBE `sales_invoices`"), 'Field');
            if (!in_array('applied_coupon_id', $siCols, true)) {
                DB::query("ALTER TABLE `sales_invoices` ADD COLUMN `applied_coupon_id` INT NULL AFTER `discount_amount`");
            }
            if (!in_array('applied_coupon_code', $siCols, true)) {
                DB::query("ALTER TABLE `sales_invoices` ADD COLUMN `applied_coupon_code` VARCHAR(50) NULL AFTER `applied_coupon_id`");
            }
            if (!in_array('coupon_discount_amount', $siCols, true)) {
                DB::query("ALTER TABLE `sales_invoices` ADD COLUMN `coupon_discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `applied_coupon_code`");
            }
            if (!in_array('coupon_mode', $siCols, true)) {
                DB::query("ALTER TABLE `sales_invoices` ADD COLUMN `coupon_mode` VARCHAR(20) NULL AFTER `coupon_discount_amount`");
            }

            // Auto-expire past-due active coupons
            DB::query("UPDATE `pos_coupons` SET `status` = 'EXPIRED' WHERE `status` = 'ACTIVE' AND `expires_at` IS NOT NULL AND `expires_at` < CURDATE()");

            self::$schemaEnsured = true;
        } catch (\Throwable $e) {
            error_log('CouponService ensureSchema error: ' . $e->getMessage());
        }
    }

    public static function getActiveCouponsForPos(int $concernId): array
    {
        self::ensureSchema();
        $sql = "SELECT id, concern_id, code, title, discount_type, discount_value, min_redemption_amount,
                       max_uses, used_count, expires_at, status
                FROM pos_coupons
                WHERE concern_id = ?
                  AND status = 'ACTIVE'
                  AND (expires_at IS NULL OR expires_at >= CURDATE())
                  AND (max_uses = 0 OR used_count < max_uses)";
        $params = [$concernId];

        $sql .= " ORDER BY id DESC";
        return DB::fetchAll($sql, $params);
    }

    /**
     * Validate a coupon against cart totals and calculate the discount amount.
     *
     * @return array{discount_amount: float, mode: string}
     */
    public static function validateAndCalculate(
        array $coupon,
        string $mode,
        float $billTotal,
        float $extraProductsTotal,
        int $selectedProductCount = 0
    ): array {
        $mode = strtoupper(trim($mode));
        if (!in_array($mode, ['DIRECT', 'PRODUCT_BUY'], true)) {
            throw new InvalidArgumentException('Choose a valid coupon application mode (DIRECT or PRODUCT_BUY).');
        }

        if (strtoupper((string) ($coupon['status'] ?? '')) !== 'ACTIVE') {
            throw new InvalidArgumentException('Selected coupon is no longer active.');
        }

        $expiresAt = trim((string) ($coupon['expires_at'] ?? ''));
        if ($expiresAt !== '' && $expiresAt < date('Y-m-d')) {
            throw new InvalidArgumentException('Selected coupon has expired.');
        }

        $maxUses = (int) ($coupon['max_uses'] ?? 1);
        $usedCount = (int) ($coupon['used_count'] ?? 0);
        if ($maxUses > 0 && $usedCount >= $maxUses) {
            throw new InvalidArgumentException('Selected coupon has reached its maximum usage limit.');
        }

        $discountType = strtoupper((string) ($coupon['discount_type'] ?? 'FIXED'));
        $discountValue = round((float) ($coupon['discount_value'] ?? 0), 2);
        $minRedemption = round((float) ($coupon['min_redemption_amount'] ?? 0), 2);

        if ($discountValue <= 0) {
            throw new InvalidArgumentException('Selected coupon has an invalid discount value.');
        }

        if ($mode === 'DIRECT') {
            $requiredMin = $discountType === 'FIXED' ? max($minRedemption, $discountValue) : $minRedemption;
            if ($billTotal + 0.0001 < $requiredMin) {
                throw new InvalidArgumentException(
                    'Bill total (₹' . number_format($billTotal, 2) . ') must be at least ₹' . number_format($requiredMin, 2) . ' to apply this coupon.'
                );
            }
            $rawDiscount = $discountType === 'PERCENT'
                ? round($billTotal * ($discountValue / 100), 2)
                : $discountValue;
            $appliedDiscount = round(min($billTotal, max(0.0, $rawDiscount)), 2);
        } else {
            if ($selectedProductCount !== 1) {
                throw new InvalidArgumentException('Select exactly one product to apply this coupon.');
            }
            if ($extraProductsTotal <= 0) {
                throw new InvalidArgumentException('The selected coupon product must have a billable amount.');
            }
            if ($billTotal + 0.0001 < $minRedemption) {
                throw new InvalidArgumentException(
                    'Overall bill total (₹' . number_format($billTotal, 2) . ') must be at least ₹' . number_format($minRedemption, 2) . ' to apply this coupon.'
                );
            }
            if ($discountType === 'FIXED' && ($extraProductsTotal + 0.0001 < $discountValue)) {
                throw new InvalidArgumentException(
                    'Coupon product total (₹' . number_format($extraProductsTotal, 2) . ') must be at least ₹' . number_format($discountValue, 2) . '.'
                );
            }
            $rawDiscount = $discountType === 'PERCENT'
                ? round($extraProductsTotal * ($discountValue / 100), 2)
                : $discountValue;
            $appliedDiscount = round(min($extraProductsTotal, max(0.0, $rawDiscount)), 2);
        }

        return [
            'discount_amount' => $appliedDiscount,
            'mode' => $mode,
        ];
    }

    public static function redeemCoupon(int $couponId, int $billId): void
    {
        $coupon = DB::fetch("SELECT id, max_uses, used_count FROM pos_coupons WHERE id = ? FOR UPDATE", [$couponId]);
        if (!$coupon) {
            return;
        }
        $nextUsed = (int) $coupon['used_count'] + 1;
        $maxUses = (int) $coupon['max_uses'];
        $nextStatus = ($maxUses > 0 && $nextUsed >= $maxUses) ? 'REDEEMED' : 'ACTIVE';

        DB::query(
            "UPDATE pos_coupons
             SET used_count = ?, status = ?, redeemed_bill_id = ?, redeemed_at = NOW(), updated_at = NOW()
             WHERE id = ?",
            [$nextUsed, $nextStatus, $billId, $couponId]
        );
    }

}
