<?php

namespace App\Services;

use App\Core\DB;

class PurchaseBillSourceService
{
    private static bool $schemaChecked = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaChecked || DB::pdo()->inTransaction()) {
            return;
        }

        $columns = array_column(DB::fetchAll('SHOW COLUMNS FROM purchase_bills'), 'Field');
        if (!in_array('source_module', $columns, true)) {
            DB::query(
                "ALTER TABLE purchase_bills
                 ADD COLUMN source_module VARCHAR(50) NOT NULL DEFAULT 'purchase_billing'
                 AFTER supplier_invoice_no"
            );
        }
        $labelColumns = array_column(DB::fetchAll('SHOW COLUMNS FROM label_jobs'), 'Field');
        if (!in_array('source_module', $labelColumns, true)) {
            DB::query(
                "ALTER TABLE label_jobs
                 ADD COLUMN source_module VARCHAR(50) NOT NULL DEFAULT 'label_generation'
                 AFTER label_type"
            );
        }
        self::$schemaChecked = true;
    }
}
