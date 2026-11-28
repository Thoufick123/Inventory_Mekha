<?php

namespace App\Services;

use App\Core\DB;

final class SalesInvoiceSchema {
    private static bool $ensured = false;

    public static function ensure(): void {
        if (self::$ensured) {
            return;
        }

        $columns = array_column(DB::fetchAll("SHOW COLUMNS FROM `sales_invoices`"), 'Field');
        if (!in_array('voided_at', $columns, true)) {
            DB::query("ALTER TABLE `sales_invoices` ADD COLUMN `voided_at` DATETIME NULL AFTER `notes`");
            $columns[] = 'voided_at';
        }
        if (!in_array('voided_by', $columns, true)) {
            DB::query("ALTER TABLE `sales_invoices` ADD COLUMN `voided_by` INT NULL AFTER `voided_at`");
            $columns[] = 'voided_by';
        }
        if (!in_array('void_reason', $columns, true)) {
            DB::query("ALTER TABLE `sales_invoices` ADD COLUMN `void_reason` VARCHAR(255) NULL AFTER `voided_by`");
        }

        $indexes = array_column(DB::fetchAll("SHOW INDEX FROM `sales_invoices`"), 'Key_name');
        if (!in_array('idx_si_voided_at', $indexes, true)) {
            DB::query("ALTER TABLE `sales_invoices` ADD INDEX `idx_si_voided_at` (`voided_at`)");
        }

        self::$ensured = true;
    }
}
