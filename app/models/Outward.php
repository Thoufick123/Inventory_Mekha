<?php
namespace App\Models;
use App\Core\Model;

class Outward extends Model {
    protected string $table = 'outwards';
    protected bool $tenantScoped = true;

    /**
     * Generate sequential Gate Pass Number e.g. GP-20261120-0001
     */
    public static function generateGatePassNo(?int $concernId = null): string {
        $cid = $concernId ?: \App\Core\Auth::concernId() ?: 1;
        $datePrefix = date('Ymd');
        $prefix = "GP-{$datePrefix}-";
        
        $last = \App\Core\DB::fetchValue(
            "SELECT gate_pass_no FROM outwards WHERE concern_id = ? AND gate_pass_no LIKE ? ORDER BY id DESC LIMIT 1",
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
     * Dispatch Outward Manifest: Transition to IN_TRANSIT and shift stock ownership to carrier
     */
    public static function dispatch(int $outwardId, int $userId): bool {
        $outward = \App\Core\DB::fetch("SELECT * FROM outwards WHERE id = ?", [$outwardId]);
        if (!$outward || !in_array(strtoupper($outward['status']), ['DRAFT', 'PREPARED'])) {
            throw new \Exception("Outward record #{$outwardId} is invalid or already dispatched.");
        }

        $batches = \App\Core\DB::fetchAll(
            "SELECT pb.*, pob.cartons_count, pob.pieces_count 
             FROM batches pb
             JOIN production_outward_batches pob ON pb.id = pob.batch_id
             WHERE pob.outward_id = ?",
            [$outwardId]
        );

        $outwardItems = \App\Core\DB::fetchAll("SELECT * FROM outward_items WHERE outward_id = ?", [$outwardId]);

        $hasActiveTx = \App\Core\DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            \App\Core\DB::beginTransaction();
        }

        try {
            $destConcernId = (int)$outward['destination_concern_id'];
            $destLocation = $destConcernId ? "CONCERN:{$destConcernId}" : ($outward['warehouse_id'] ? "WAREHOUSE:{$outward['warehouse_id']}" : "WAREHOUSE:1");

            // 1. Process linked batches if multi-batch manifest
            foreach ($batches as $batch) {
                $varId = (int)$batch['variant_id'];
                $qty = (float)$batch['pieces_count'] > 0 ? (float)$batch['pieces_count'] : (float)$batch['batch_qty'];

                // Decrease AVAILABLE at Production Unit
                \App\Services\StockService::decrease(
                    $varId,
                    'PRODUCTION_UNIT',
                    $batch['production_unit_id'] ?: '1',
                    $qty,
                    'OUTWARD_DISPATCH',
                    $outwardId,
                    "Dispatched on Vehicle {$outward['vehicle_number']} (Gate Pass {$outward['gate_pass_no']})",
                    (int)$batch['concern_id'],
                    'AVAILABLE'
                );

                // Increase IN_TRANSIT assigned to destination
                \App\Services\StockService::increase(
                    $varId,
                    'TRANSIT',
                    $destLocation,
                    $qty,
                    'STOCK_IN_TRANSIT',
                    $outwardId,
                    "In-transit under LR {$outward['lr_number']}",
                    (int)$batch['concern_id'],
                    'IN_TRANSIT'
                );

                \App\Core\DB::query("UPDATE batches SET status = 'DISPATCHED' WHERE id = ?", [$batch['id']]);
            }

            // 2. Process direct outward items if any exist
            foreach ($outwardItems as $item) {
                if (empty($batches)) {
                    $varId = (int)$item['variant_id'];
                    $qty = (float)$item['qty'];

                    \App\Services\StockService::decrease(
                        $varId,
                        'WAREHOUSE',
                        $outward['location'] ?: 'Main Store',
                        $qty,
                        'OUTWARD_DISPATCH',
                        $outwardId,
                        "Dispatched on Vehicle {$outward['vehicle_number']}",
                        (int)$outward['concern_id'],
                        'AVAILABLE'
                    );

                    if ($destConcernId) {
                        \App\Services\StockService::increase(
                            $varId,
                            'TRANSIT',
                            $destLocation,
                            $qty,
                            'STOCK_IN_TRANSIT',
                            $outwardId,
                            "In-transit under LR {$outward['lr_number']}",
                            (int)$outward['concern_id'],
                            'IN_TRANSIT'
                        );
                    }
                }
            }

            // 3. Mark outward as DISPATCHED
            \App\Core\DB::query(
                "UPDATE outwards SET status = 'DISPATCHED', dispatched_at = NOW() WHERE id = ?",
                [$outwardId]
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

    /**
     * Destination Receiving: Reconcile Gate Pass and shift IN_TRANSIT stock to AVAILABLE
     */
    public static function receive(int $outwardId, int $userId, float $damagedQty = 0.00): bool {
        $outward = \App\Core\DB::fetch("SELECT * FROM outwards WHERE id = ?", [$outwardId]);
        if (!$outward || strtoupper($outward['status']) !== 'DISPATCHED') {
            throw new \Exception("Outward record #{$outwardId} is not in DISPATCHED status.");
        }

        $batches = \App\Core\DB::fetchAll(
            "SELECT pb.*, pob.cartons_count, pob.pieces_count 
             FROM batches pb
             JOIN production_outward_batches pob ON pb.id = pob.batch_id
             WHERE pob.outward_id = ?",
            [$outwardId]
        );

        $outwardItems = \App\Core\DB::fetchAll("SELECT * FROM outward_items WHERE outward_id = ?", [$outwardId]);

        $hasActiveTx = \App\Core\DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            \App\Core\DB::beginTransaction();
        }

        try {
            $destConcernId = (int)$outward['destination_concern_id'];
            $destLocation = $destConcernId ? "CONCERN:{$destConcernId}" : ($outward['warehouse_id'] ? "WAREHOUSE:{$outward['warehouse_id']}" : "WAREHOUSE:1");
            $targetCid = $destConcernId ?: (int)$outward['concern_id'];

            $itemsToProcess = !empty($batches) ? $batches : $outwardItems;
            foreach ($itemsToProcess as $row) {
                $varId = (int)$row['variant_id'];
                $totalQty = isset($row['pieces_count']) && (float)$row['pieces_count'] > 0 
                    ? (float)$row['pieces_count'] 
                    : (float)($row['qty'] ?? $row['batch_qty']);

                // Deduct from IN_TRANSIT
                \App\Services\StockService::decrease(
                    $varId,
                    'TRANSIT',
                    $destLocation,
                    $totalQty,
                    'RECONCILIATION',
                    $outwardId,
                    "Reconciled arrival at destination from Pass #{$outward['gate_pass_no']}",
                    (int)$outward['concern_id'],
                    'IN_TRANSIT'
                );

                $receivedGoodQty = max(0.00, $totalQty - $damagedQty);
                // Credit to AVAILABLE at destination
                if ($receivedGoodQty > 0) {
                    \App\Services\StockService::increase(
                        $varId,
                        'WAREHOUSE',
                        'Main Store',
                        $receivedGoodQty,
                        'WAREHOUSE_RECEIPT',
                        $outwardId,
                        "Received arrival from Pass #{$outward['gate_pass_no']}",
                        $targetCid,
                        'AVAILABLE'
                    );
                }

                // If any pieces were damaged in transit, record to DAMAGED state
                if ($damagedQty > 0) {
                    \App\Services\StockService::increase(
                        $varId,
                        'WAREHOUSE',
                        'Quarantine Bay',
                        $damagedQty,
                        'TRANSIT_DAMAGE',
                        $outwardId,
                        "Damaged in transit under LR #{$outward['lr_number']}",
                        $targetCid,
                        'DAMAGED'
                    );
                }
            }

            \App\Core\DB::query(
                "UPDATE outwards SET status = 'RECEIVED', received_at = NOW() WHERE id = ?",
                [$outwardId]
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
