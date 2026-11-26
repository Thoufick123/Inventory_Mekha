<?php
// app/services/WarehouseService.php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Auth;
use App\Models\Inward;
use App\Services\StockService;
use App\Services\DamageService;
use Exception;

class WarehouseService {
    /**
     * Reconcile & receive a production batch / outward consignment into destination warehouse stock.
     *
     * @param int $outwardId Consignment outward ID
     * @param int $destConcernId Active destination concern ID
     * @param array $itemsToInward Array of ['source_variant_id' => int, 'target_variant_id' => int, 'verified_qty' => float, 'damaged_qty' => float]
     * @param string $damageReason Defect reason for quarantined pieces
     * @param int|null $userId Receiving operator ID
     * @return array [inward_id, grn_number, accepted_qty, damaged_qty, is_complete]
     */
    public static function receiveProductionBatch(
        int $outwardId,
        int $destConcernId,
        array $itemsToInward,
        string $damageReason = 'Damaged during transit / handling',
        ?int $userId = null
    ): array {
        if ($outwardId <= 0) {
            throw new Exception("Invalid outward consignment ID.");
        }
        if ($destConcernId <= 0) {
            throw new Exception("Destination concern ID is required.");
        }
        if (empty($itemsToInward)) {
            throw new Exception("Please verify or record at least 1 piece before crediting stock.");
        }

        $userId = $userId ?? Auth::id();

        // 1. Fetch outward with lock
        $outward = DB::fetch("SELECT * FROM outwards WHERE id = ? FOR UPDATE", [$outwardId]);
        if (!$outward) {
            throw new Exception("Outward consignment #{$outwardId} not found.");
        }

        $assignedDest = (int)($outward['destination_concern_id'] ?? 0);
        if ($assignedDest > 0 && $assignedDest !== $destConcernId) {
            throw new Exception("Access Denied: Consignment #{$outwardId} was dispatched to a different concern.");
        }

        $currentStatus = strtolower((string)$outward['status']);
        if (!in_array($currentStatus, ['dispatched', 'partial'], true)) {
            throw new Exception("Only dispatched or partially received consignments can be inwarded.");
        }
        $isDamageTransfer = (int)($outward['damage_transfer'] ?? 0) === 1;

        // 2. Fetch dispatched items
        $dispatchedItems = DB::fetchAll(
            "SELECT oi.variant_id, SUM(oi.qty) AS qty, v.product_code
             FROM outward_items oi
             JOIN variants v ON v.id = oi.variant_id
             WHERE oi.outward_id = ?
             GROUP BY oi.variant_id, v.product_code",
            [$outwardId]
        );
        if (!$dispatchedItems) {
            $batchItem = DB::fetch(
                "SELECT b.variant_id, b.batch_qty AS qty, v.product_code
                 FROM production_outward_batches pob
                 JOIN batches b ON b.id = pob.batch_id
                 JOIN variants v ON v.id = b.variant_id
                 WHERE pob.outward_id = ?
                 ORDER BY pob.id ASC
                 LIMIT 1",
                [$outwardId]
            );
            $dispatchedItems = $batchItem ? [$batchItem] : [];
        }

        if (!$dispatchedItems) {
            throw new Exception("No exact dispatched items found for outward consignment #{$outwardId}.");
        }

        $dispatchedByVariant = [];
        $totalDispatched = 0.0;
        foreach ($dispatchedItems as $it) {
            $srcVarId = (int)$it['variant_id'];
            $dispatchedByVariant[$srcVarId] = [
                'qty' => (float)$it['qty'],
                'product_code' => (string)$it['product_code'],
            ];
            $totalDispatched += (float)$it['qty'];
        }

        // 3. Validate remaining balances
        $totalVerifiedThisRun = 0.0;
        $totalDamagedThisRun = 0.0;
        foreach ($itemsToInward as $item) {
            $srcVarId = (int)$item['source_variant_id'];
            $vQty = (float)($item['verified_qty'] ?? 0);
            $dQty = (float)($item['damaged_qty'] ?? 0);
            if ($isDamageTransfer) {
                $dQty += $vQty;
                $vQty = 0.0;
            }

            if ($vQty < 0 || $dQty < 0) {
                throw new Exception("Inward quantities cannot be negative.");
            }
            if ($vQty == 0 && $dQty == 0) {
                continue;
            }

            if (!isset($dispatchedByVariant[$srcVarId])) {
                throw new Exception("Variant #{$srcVarId} is not part of this consignment.");
            }

            $dispInfo = $dispatchedByVariant[$srcVarId];
            $prevInward = DB::fetch(
                "SELECT COALESCE(SUM(ii.accepted_qty), 0) AS accepted_qty,
                        COALESCE(SUM(ii.rejected_qty), 0) AS damaged_qty
                 FROM inwards inw
                 JOIN inward_items ii ON ii.inward_id = inw.id
                 JOIN variants iv ON iv.id = ii.variant_id
                 WHERE inw.outward_id = ? AND inw.concern_id = ?
                   AND (ii.variant_id = ? OR iv.product_code = ?)",
                [$outwardId, $destConcernId, $srcVarId, $dispInfo['product_code']]
            );

            $remainingQty = max(
                0.0,
                $dispInfo['qty'] - (float)($prevInward['accepted_qty'] ?? 0) - (float)($prevInward['damaged_qty'] ?? 0)
            );

            if ($vQty + $dQty > $remainingQty + 0.0001) {
                throw new Exception(
                    "Received quantity for {$dispInfo['product_code']} exceeds remaining outward balance ({$remainingQty})."
                );
            }

            $totalVerifiedThisRun += $vQty;
            $totalDamagedThisRun += $dQty;
        }

        if ($totalVerifiedThisRun <= 0 && $totalDamagedThisRun <= 0) {
            throw new Exception("Please verify or record at least 1 piece before crediting stock.");
        }

        // 4. Create Inward & GRN record
        $isProductionBatch = (bool)DB::fetchValue(
            "SELECT 1 FROM production_outward_batches WHERE outward_id = ? LIMIT 1",
            [$outwardId]
        );
        $inwardType = $isProductionBatch ? 'production' : 'purchase';

        $grnNumber = Inward::generateGrnNumber($destConcernId);
        $inwardNo = 'INW-' . date('Ymd') . '-' . str_pad((string)rand(100, 999), 4, '0', STR_PAD_LEFT);

        DB::query("
            INSERT INTO inwards (concern_id, outward_id, inward_no, grn_number, inward_type, production_unit_id, from_concern_id, inward_date, vehicle_number, location, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'Main Store', 'VERIFIED', ?)
        ", [
            $destConcernId,
            $outwardId,
            $inwardNo,
            $grnNumber,
            $inwardType,
            $outward['production_unit_id'],
            $outward['concern_id'],
            $outward['vehicle_number'],
            $userId
        ]);
        $inwardId = (int)DB::lastInsertId();

        // 5. Line items & stock ledger credit
        foreach ($itemsToInward as $item) {
            $srcVarId = (int)$item['source_variant_id'];
            $targetVarId = (int)$item['target_variant_id'];
            $vQty = (float)($item['verified_qty'] ?? 0);
            $dQty = (float)($item['damaged_qty'] ?? 0);
            if ($isDamageTransfer) {
                $dQty += $vQty;
                $vQty = 0.0;
            }

            if ($vQty <= 0 && $dQty <= 0) {
                continue;
            }

            $productCode = (string)($item['product_code'] ?? DB::fetchValue("SELECT product_code FROM variants WHERE id = ?", [$targetVarId]));
            $isFgItem = str_starts_with($productCode, 'FG-') || !$isProductionBatch;
            $inboundRefModule = $isFgItem ? 'Purchase Inward' : 'Production Inward';

            DB::query("
                INSERT INTO inward_items (concern_id, inward_id, variant_id, qty, accepted_qty, rejected_qty, rejection_reason)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [
                $destConcernId,
                $inwardId,
                $targetVarId,
                $vQty + $dQty,
                $vQty,
                $dQty,
                $dQty > 0 ? $damageReason : null
            ]);

            // Good / Intact stock -> Available in Destination Warehouse
            if ($vQty > 0) {
                StockService::move(
                    $destConcernId,
                    $targetVarId,
                    'Main Store',
                    $vQty,
                    0.00,
                    $inboundRefModule,
                    $inwardId,
                    "GRN #{$grnNumber} Inward acceptance from Outward #{$outward['outward_no']}"
                );
            }

            // Damaged / Quarantined stock -> Isolated from billing
            if ($dQty > 0) {
                if ($isDamageTransfer) {
                    $transferReason = "Transferred damaged stock from concern #{$outward['concern_id']} via outward {$outward['outward_no']}";
                    DamageService::recordTransferredDamage(
                        $destConcernId,
                        $targetVarId,
                        $inwardId,
                        $dQty,
                        $transferReason,
                        $userId
                    );
                } else {
                    DamageService::recordInwardDamage(
                        $destConcernId,
                        $targetVarId,
                        $inwardId,
                        $dQty,
                        $damageReason,
                        (int)($outward['warehouse_id'] ?? 1),
                        $userId
                    );
                }
            }

            // Consume transit stock from origin concern
            $receiptQty = $vQty + $dQty;
            $transitLocation = "IN_TRANSIT:OUTWARD:{$outwardId}";
            $transitQty = DB::fetchValue(
                "SELECT qty FROM stocks
                 WHERE concern_id = ? AND variant_id = ? AND location = ?
                 FOR UPDATE",
                [(int)$outward['concern_id'], $srcVarId, $transitLocation]
            );
            $hasTransitLedger = DB::fetchValue(
                "SELECT id FROM stock_ledger
                 WHERE concern_id = ? AND variant_id = ? AND ref_module = 'Outward Transit' AND ref_id = ?
                 LIMIT 1",
                [(int)$outward['concern_id'], $srcVarId, $outwardId]
            );

            if ($hasTransitLedger && $transitQty !== false && $transitQty !== null && (float)$transitQty >= $receiptQty - 0.0001) {
                StockService::move(
                    (int)$outward['concern_id'],
                    $srcVarId,
                    $transitLocation,
                    0.00,
                    $receiptQty,
                    'Production Inward Transit',
                    $inwardId,
                    "Consumed in-transit quantity for GRN #{$grnNumber}"
                );
            }
        }

        // 6. Check consignment completion
        $totalCredited = (float)DB::fetchValue(
            "SELECT sum(ii.accepted_qty)
             FROM inwards inw
             JOIN inward_items ii ON inw.id = ii.inward_id
             JOIN outwards ow ON ow.id = inw.outward_id AND inw.concern_id = ow.destination_concern_id
             WHERE inw.outward_id = ?",
            [$outwardId]
        );
        $totalDamaged = (float)DB::fetchValue(
            "SELECT sum(ii.rejected_qty)
             FROM inwards inw
             JOIN inward_items ii ON inw.id = ii.inward_id
             JOIN outwards ow ON ow.id = inw.outward_id AND inw.concern_id = ow.destination_concern_id
             WHERE inw.outward_id = ?",
            [$outwardId]
        );

        $isComplete = ($totalCredited + $totalDamaged >= $totalDispatched - 0.0001);

        if ($isComplete) {
            DB::query("UPDATE outwards SET status = 'received', received_at = NOW() WHERE id = ?", [$outwardId]);
            DB::query(
                "UPDATE batches b
                 JOIN production_outward_batches pob ON pob.batch_id = b.id
                 SET b.status = 'RECEIVED'
                 WHERE pob.outward_id = ? AND pob.concern_id = ?",
                [$outwardId, (int)$outward['concern_id']]
            );
        } else {
            $remainingQty = max(0, $totalDispatched - $totalCredited - $totalDamaged);
            $discrepancyNote = "[GRN {$grnNumber}] Partial receipt: " . number_format($remainingQty, 2, '.', '') . " pieces remain pending.";
            DB::query(
                "UPDATE outwards
                 SET status = 'partial',
                     notes = CONCAT(COALESCE(notes, ''), CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE '\n' END, ?)
                 WHERE id = ?",
                [$discrepancyNote, $outwardId]
            );
        }

        return [
            'success'      => true,
            'inward_id'    => $inwardId,
            'inward_no'    => $inwardNo,
            'grn_number'   => $grnNumber,
            'verified_qty' => $totalVerifiedThisRun,
            'damaged_qty'  => $totalDamagedThisRun,
            'is_complete'  => $isComplete,
            'outward_id'   => $outwardId,
        ];
    }
}
