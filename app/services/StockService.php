<?php
// app/services/StockService.php - Single stock gateway

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use Exception;

class StockService {
    /**
     * Atomically move stock and record ledger entry.
     * Guaranteed single point of stock mutations.
     *
     * @param int $concernId
     * @param int $variantId
     * @param string $location
     * @param float $qtyIn
     * @param float $qtyOut
     * @param string $refModule e.g. 'Inward', 'Outward', 'Purchase Damage', 'Concern Damage', 'QC Stocks', 'Return & Exchange', 'Cutting'
     * @param int|null $refId
     * @param string|null $note
     * @param int|null $userId
     * @return float New stock quantity
     * @throws Exception
     */
    public static function move(
        int $concernId,
        int $variantId,
        string $location,
        float $qtyIn,
        float $qtyOut,
        string $refModule,
        ?int $refId = null,
        ?string $note = null,
        ?int $userId = null
    ): float {
        if ($qtyIn < 0 || $qtyOut < 0) {
            throw new Exception("Stock quantities must be non-negative.");
        }
        if ($qtyIn == 0 && $qtyOut == 0) {
            throw new Exception("At least one of qtyIn or qtyOut must be greater than zero.");
        }

        $userId = $userId ?? Auth::id();
        $location = trim($location) ?: 'Main Store';

        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            // 1. Verify variant belongs to concern
            $var = DB::fetch(
                "SELECT id, name, sku_code FROM variants WHERE id = ? AND concern_id = ?",
                [$variantId, $concernId]
            );
            if (!$var) {
                throw new Exception("Variant #{$variantId} does not belong to concern #{$concernId}.");
            }

            // 2. Lock stock row for update
            $currentStock = DB::fetch(
                "SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = ? FOR UPDATE",
                [$concernId, $variantId, $location]
            );

            $currentQty = $currentStock ? (float)$currentStock['qty'] : 0.00;
            $newQty = $currentQty + $qtyIn - $qtyOut;

            if ($newQty < 0) {
                throw new Exception("Insufficient stock for '{$var['name']}' ({$var['sku_code']}). Available: {$currentQty}, Requested: {$qtyOut}");
            }

            // 3. Update or Insert stocks record
            if ($currentStock) {
                DB::query(
                    "UPDATE stocks SET qty = ?, updated_at = NOW() WHERE concern_id = ? AND variant_id = ? AND location = ?",
                    [$newQty, $concernId, $variantId, $location]
                );
            } else {
                DB::query(
                    "INSERT INTO stocks (concern_id, variant_id, location, qty, updated_at) VALUES (?, ?, ?, ?, NOW())",
                    [$concernId, $variantId, $location, $newQty]
                );
            }

            // 4. Append to immutable stock ledger
            DB::query(
                "INSERT INTO stock_ledger (concern_id, variant_id, location, qty_in, qty_out, ref_module, ref_id, note, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$concernId, $variantId, $location, $qtyIn, $qtyOut, $refModule, $refId, $note, $userId]
            );

            // Audit
            AuditLog::log('STOCK_MOVE', 'stocks', $variantId, ['old_qty' => $currentQty], ['new_qty' => $newQty, 'ref_module' => $refModule, 'ref_id' => $refId]);

            if (!$hasActiveTx) {
                DB::commit();
            }

            return $newQty;
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            throw $e;
        }
    }

    /**
     * Increase stock (credit) in multi-location double-entry ledger.
     */
    public static function increase(
        int $variantId,
        string $locationType,
        $locationTarget,
        float $qty,
        string $refModule,
        ?int $refId = null,
        ?string $note = null,
        ?int $concernId = null,
        string $stockState = 'AVAILABLE'
    ): float {
        $cid = $concernId ?: Auth::concernId() ?: 1;
        $loc = self::formatLocation($locationType, $locationTarget, $stockState);
        return self::move($cid, $variantId, $loc, $qty, 0.00, $refModule, $refId, $note);
    }

    /**
     * Decrease stock (debit) with zero-negative protection.
     */
    public static function decrease(
        int $variantId,
        string $locationType,
        $locationTarget,
        float $qty,
        string $refModule,
        ?int $refId = null,
        ?string $note = null,
        ?int $concernId = null,
        string $stockState = 'AVAILABLE'
    ): float {
        $cid = $concernId ?: Auth::concernId() ?: 1;
        $loc = self::formatLocation($locationType, $locationTarget, $stockState);
        return self::move($cid, $variantId, $loc, 0.00, $qty, $refModule, $refId, $note);
    }

    /**
     * Get balance for a variant at a scoped location & state.
     */
    public static function getBalance(
        int $variantId,
        string $locationType,
        $locationTarget,
        string $stockState = 'AVAILABLE',
        ?int $concernId = null
    ): float {
        $cid = $concernId ?: Auth::concernId() ?: 1;
        $loc = self::formatLocation($locationType, $locationTarget, $stockState);
        return self::getQty($cid, $variantId, $loc);
    }

    /**
     * Helper to format consistent location keys (e.g. "Main Store", "PRODUCTION_UNIT:2", "WAREHOUSE:1:IN_TRANSIT")
     */
    private static function formatLocation(string $locationType, $locationTarget, string $stockState = 'AVAILABLE'): string {
        $type = strtoupper(trim($locationType));
        $target = $locationTarget !== null ? trim((string)$locationTarget) : '';

        if ($type === '' || $type === 'WAREHOUSE' || $type === 'STORE') {
            $base = $target ?: 'Main Store';
        } else {
            $base = $target !== '' ? "{$type}:{$target}" : $type;
        }

        if (strtoupper($stockState) !== 'AVAILABLE') {
            $base .= ':' . strtoupper(trim($stockState));
        }
        return $base;
    }

    /**
     * Get live stock for a variant
     */
    public static function getQty(int $concernId, int $variantId, string $location = 'Main Store'): float {
        $res = DB::fetchValue(
            "SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = ?",
            [$concernId, $variantId, $location]
        );
        return $res !== false ? (float)$res : 0.00;
    }

    /**
     * Get total stock across all locations for a variant
     */
    public static function getTotalQty(int $concernId, int $variantId): float {
        $res = DB::fetchValue(
            "SELECT COALESCE(SUM(qty), 0) FROM stocks WHERE concern_id = ? AND variant_id = ?",
            [$concernId, $variantId]
        );
        return (float)$res;
    }
}
