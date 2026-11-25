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

    public static function attachInvoiceSyncStatus(array $bills, ?int $concernId = null): array
    {
        if (empty($bills)) {
            return $bills;
        }

        self::ensureSchema();
        $sql = "SELECT pb.concern_id, pb.supplier_id,
                       LOWER(TRIM(COALESCE(pb.supplier_invoice_no, ''))) AS inv_key,
                       COALESCE(NULLIF(pb.source_module, ''), 'purchase_billing') AS source_module,
                       COALESCE(SUM(pbi.qty), 0) AS total_qty
                FROM purchase_bills pb
                LEFT JOIN purchase_bill_items pbi ON pbi.purchase_bill_id = pb.id
                WHERE TRIM(COALESCE(pb.supplier_invoice_no, '')) != ''";
        $params = [];
        if ($concernId) {
            $sql .= " AND pb.concern_id = ?";
            $params[] = $concernId;
        }
        $sql .= " GROUP BY pb.concern_id, pb.supplier_id, inv_key, source_module";

        $stats = [];
        foreach (DB::fetchAll($sql, $params) as $row) {
            $key = (int)$row['concern_id'] . '|' . (int)$row['supplier_id'] . '|' . $row['inv_key'];
            if (!isset($stats[$key])) {
                $stats[$key] = ['has_pb' => false, 'has_pa' => false, 'pb_qty' => 0.0, 'pa_qty' => 0.0];
            }
            if ($row['source_module'] === 'product_adding') {
                $stats[$key]['has_pa'] = true;
                $stats[$key]['pa_qty'] += (float)$row['total_qty'];
            } else {
                $stats[$key]['has_pb'] = true;
                $stats[$key]['pb_qty'] += (float)$row['total_qty'];
            }
        }

        $fmtQty = static function (float $v): string {
            return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        };

        foreach ($bills as &$bill) {
            $invKey = strtolower(trim((string)($bill['supplier_invoice_no'] ?? '')));
            $key = (int)($bill['concern_id'] ?? 0) . '|' . (int)($bill['supplier_id'] ?? 0) . '|' . $invKey;
            $source = ($bill['source_module'] ?? 'purchase_billing') === 'product_adding' ? 'product_adding' : 'purchase_billing';

            $entry = ($invKey !== '' && isset($stats[$key])) ? $stats[$key] : [
                'has_pb' => $source === 'purchase_billing',
                'has_pa' => $source === 'product_adding',
                'pb_qty' => $source === 'purchase_billing' ? (float)($bill['total_qty'] ?? 0) : 0.0,
                'pa_qty' => $source === 'product_adding' ? (float)($bill['total_qty'] ?? 0) : 0.0,
            ];

            $bill['pb_total_qty'] = $entry['pb_qty'];
            $bill['pa_total_qty'] = $entry['pa_qty'];
            $bill['qty_mismatch'] = false;

            if ($entry['has_pb'] && $entry['has_pa']) {
                if (abs($entry['pb_qty'] - $entry['pa_qty']) > 0.0001) {
                    $bill['sync_status'] = 'qty_mismatch';
                    $bill['sync_label'] = 'Qty Mismatch (PB: ' . $fmtQty($entry['pb_qty']) . ' vs PA: ' . $fmtQty($entry['pa_qty']) . ')';
                    $bill['sync_badge_class'] = 'bg-danger-subtle text-danger border border-danger';
                    $bill['qty_mismatch'] = true;
                } else {
                    $bill['sync_status'] = 'completed';
                    $bill['sync_label'] = 'Completed';
                    $bill['sync_badge_class'] = 'bg-success-subtle text-success border border-success';
                }
            } elseif ($entry['has_pb']) {
                $bill['sync_status'] = 'product_adding_pending';
                $bill['sync_label'] = 'Product Adding Pending';
                $bill['sync_badge_class'] = 'bg-warning-subtle text-warning border border-warning';
            } else {
                $bill['sync_status'] = 'purchase_billing_pending';
                $bill['sync_label'] = 'Purchase Billing Pending';
                $bill['sync_badge_class'] = 'bg-info-subtle text-info border border-info';
            }
        }
        unset($bill);

        return $bills;
    }
}
