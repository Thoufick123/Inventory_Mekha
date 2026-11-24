<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class ReturnExchangeSchema {
    private static bool $ensured = false;

    public static function ensure(): void {
        if (self::$ensured) {
            return;
        }

        $returnItemColumns = array_column(
            DB::fetchAll("SHOW COLUMNS FROM `production_return_exchange_items`"),
            'Field'
        );
        if (!in_array('sales_invoice_item_id', $returnItemColumns, true)) {
            DB::query("ALTER TABLE `production_return_exchange_items` ADD COLUMN `sales_invoice_item_id` INT NULL");
        }
        if (!in_array('scanned_codes', $returnItemColumns, true)) {
            DB::query("ALTER TABLE `production_return_exchange_items` ADD COLUMN `scanned_codes` TEXT NULL");
        }

        $invoiceItemColumns = array_column(
            DB::fetchAll("SHOW COLUMNS FROM `sales_invoice_items`"),
            'Field'
        );
        if (!in_array('scanned_codes', $invoiceItemColumns, true)) {
            DB::query("ALTER TABLE `sales_invoice_items` ADD COLUMN `scanned_codes` TEXT NULL");
        }

        self::$ensured = true;
    }
}
