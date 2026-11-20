<?php
namespace App\Models;
use App\Core\Model;

class Batch extends Model {
    protected string $table = 'batches';
    protected bool $tenantScoped = true;

    /**
     * Generate sequential Packaging / Production Batch number e.g. PB-20261120-0001
     */
    public static function generateBatchNo(?int $concernId = null): string {
        $cid = $concernId ?: \App\Core\Auth::concernId() ?: 1;
        $datePrefix = date('Ymd');
        $prefix = "PB-{$datePrefix}-";
        
        $last = \App\Core\DB::fetchValue(
            "SELECT batch_no FROM batches WHERE concern_id = ? AND batch_no LIKE ? ORDER BY id DESC LIMIT 1",
            [$cid, "{$prefix}%"]
        );

        $nextSeq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $seq = (int)end($parts);
            $nextSeq = $seq + 1;
        }

        return $prefix . str_pad((string)$nextSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Supervisor verification gate: Approve Packaging Batch and register into Available stock
     */
    public static function approveBatch(int $batchId, int $userId): bool {
        $batch = \App\Core\DB::fetch("SELECT * FROM batches WHERE id = ?", [$batchId]);
        if (!$batch) {
            throw new \Exception("Batch #{$batchId} not found.");
        }
        if (in_array(strtoupper($batch['status']), ['APPROVED', 'DISPATCHED'])) {
            throw new \Exception("Batch #{$batch['batch_no']} is already approved or dispatched.");
        }

        $batchQty = (float)$batch['batch_qty'];
        if ($batchQty <= 0) {
            throw new \Exception("Cannot approve batch with zero quantity.");
        }

        $hasActiveTx = \App\Core\DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            \App\Core\DB::beginTransaction();
        }

        try {
            \App\Core\DB::query(
                "UPDATE batches SET status = 'APPROVED', approved_by = ?, approved_at = NOW() WHERE id = ?",
                [$userId, $batchId]
            );

            // Register manufactured goods into Unit Available Stock ledger
            \App\Services\StockService::increase(
                (int)$batch['variant_id'],
                'PRODUCTION_UNIT',
                $batch['production_unit_id'] ?: '1',
                $batchQty,
                'PRODUCTION_BATCH',
                $batchId,
                "Finished Batch #{$batch['batch_no']} packed and approved ({$batch['total_boxes']} cartons)",
                (int)$batch['concern_id'],
                'AVAILABLE'
            );

            if (!$hasActiveTx) {
                \App\Core\DB::commit();
            }
            return true;
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                \App\Core\DB::rollBack();
            }
            throw $e;
        }
    }
}
