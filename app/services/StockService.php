<?php
// app/services/StockService.php - Single stock gateway with atomic multi-line batch movements

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use Exception;

class StockService {
    private static bool $ledgerValuationSchemaEnsured = false;

    public static function ensureLedgerValuationSchema(): void {
        if (self::$ledgerValuationSchemaEnsured) {
            return;
        }

        $columns = array_column(DB::fetchAll("SHOW COLUMNS FROM `stock_ledger`"), 'Field');
        foreach ([
            'unit_cost' => "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `qty_out`",
            'mrp' => "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `unit_cost`",
            'selling_price' => "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `mrp`",
        ] as $column => $definition) {
            if (in_array($column, $columns, true)) {
                continue;
            }
            if (DB::pdo()->inTransaction()) {
                throw new Exception("The stock ledger is missing '{$column}'. Run the database migration before moving stock.");
            }
            DB::query("ALTER TABLE `stock_ledger` ADD COLUMN `{$column}` {$definition}");
            $columns[] = $column;
        }

        self::$ledgerValuationSchemaEnsured = true;
    }

    /**
     * Consolidate legacy 'Main Store (WH-01)' rows to unified 'Main Store'
     */
    public static function consolidateLegacyLocations(): void {
        static $done = false;
        if ($done) return;
        $done = true;

        try {
            $whStocks = DB::fetchAll("SELECT * FROM stocks WHERE location = 'Main Store (WH-01)'");
            foreach ($whStocks as $ws) {
                $mainStock = DB::fetch(
                    "SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = 'Main Store'",
                    [$ws['concern_id'], $ws['variant_id']]
                );
                if ($mainStock) {
                    DB::query(
                        "UPDATE stocks SET qty = qty + ?, updated_at = NOW() WHERE concern_id = ? AND variant_id = ? AND location = 'Main Store'",
                        [$ws['qty'], $ws['concern_id'], $ws['variant_id']]
                    );
                    DB::query(
                        "DELETE FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = 'Main Store (WH-01)'",
                        [$ws['concern_id'], $ws['variant_id']]
                    );
                } else {
                    DB::query(
                        "UPDATE stocks SET location = 'Main Store', updated_at = NOW() WHERE concern_id = ? AND variant_id = ? AND location = 'Main Store (WH-01)'",
                        [$ws['concern_id'], $ws['variant_id']]
                    );
                }
            }

            $whDamaged = DB::fetchAll("SELECT * FROM stocks WHERE location = 'Main Store (WH-01):DAMAGED'");
            foreach ($whDamaged as $wd) {
                $mainDamaged = DB::fetch(
                    "SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = 'Main Store:DAMAGED'",
                    [$wd['concern_id'], $wd['variant_id']]
                );
                if ($mainDamaged) {
                    DB::query(
                        "UPDATE stocks SET qty = qty + ?, updated_at = NOW() WHERE concern_id = ? AND variant_id = ? AND location = 'Main Store:DAMAGED'",
                        [$wd['qty'], $wd['concern_id'], $wd['variant_id']]
                    );
                    DB::query(
                        "DELETE FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = 'Main Store (WH-01):DAMAGED'",
                        [$wd['concern_id'], $wd['variant_id']]
                    );
                } else {
                    DB::query(
                        "UPDATE stocks SET location = 'Main Store:DAMAGED', updated_at = NOW() WHERE concern_id = ? AND variant_id = ? AND location = 'Main Store (WH-01):DAMAGED'",
                        [$wd['concern_id'], $wd['variant_id']]
                    );
                }
            }

            DB::query("UPDATE stock_ledger SET location = 'Main Store' WHERE location = 'Main Store (WH-01)'");
            DB::query("UPDATE stock_ledger SET location = 'Main Store:DAMAGED' WHERE location = 'Main Store (WH-01):DAMAGED'");
            DB::query("UPDATE qc_inspections SET location = 'Main Store' WHERE location = 'Main Store (WH-01)'");
            DB::query("UPDATE batches SET warehouse_name = 'Main Store' WHERE warehouse_name = 'Main Store (WH-01)'");
            DB::query("UPDATE concern_damages SET location = 'Main Store' WHERE location = 'Main Store (WH-01)'");
        } catch (\Throwable $e) {}
    }

    /**
     * Atomically move stock and record ledger entry.
     * Guaranteed single point of stock mutations.
     *
     * @param int $concernId
     * @param int $variantId
     * @param string $location
     * @param float $qtyIn
     * @param float $qtyOut
     * @param string $refModule e.g. 'Inward', 'Outward', 'Purchase Damage', 'Concern Damage', 'Warehouse Damage', 'Shop Damage', 'Return & Exchange', 'Stock Request', 'Cutting'
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
        self::ensureLedgerValuationSchema();

        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            // 1. Verify variant belongs to concern
            $var = DB::fetch(
                "SELECT id, name, product_code, cost_price, price, mrp FROM variants WHERE id = ? AND concern_id = ?",
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
                throw new Exception("Insufficient stock for '{$var['name']}' ({$var['product_code']}) at '{$location}'. Available: {$currentQty}, Requested: {$qtyOut}");
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
                "INSERT INTO stock_ledger
                    (concern_id, variant_id, location, qty_in, qty_out, unit_cost, mrp, selling_price, ref_module, ref_id, note, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $concernId,
                    $variantId,
                    $location,
                    $qtyIn,
                    $qtyOut,
                    (float)$var['cost_price'],
                    (float)$var['mrp'],
                    (float)$var['price'],
                    $refModule,
                    $refId,
                    $note,
                    $userId,
                ]
            );

            // Audit
            AuditLog::log('STOCK_MOVE', 'stocks', $variantId, ['old_qty' => $currentQty, 'location' => $location], ['new_qty' => $newQty, 'location' => $location, 'ref_module' => $refModule, 'ref_id' => $refId]);

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
     * Move stock with explicit audit metadata.
     */
    public static function moveWithAudit(
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
        return self::move($concernId, $variantId, $location, $qtyIn, $qtyOut, $refModule, $refId, $note, $userId);
    }

    /**
     * Atomically transfer multiple stock lines across locations in a single transaction.
     * Prevents partial dispatches / receipts and guarantees double-entry ledger balance.
     *
     * @param int $concernId
     * @param array $lines Each element: ['variant_id' => int, 'from_location' => string, 'to_location' => string, 'qty' => float, 'note' => ?string]
     * @param string $refModule e.g. 'Stock Request', 'Damage Resolution', 'Return & Exchange', 'Warehouse Transfer'
     * @param int|null $refId
     * @param string|null $note
     * @param int|null $userId
     * @return array Array of execution details
     * @throws Exception
     */
    public static function transferBatch(
        int $concernId,
        array $lines,
        string $refModule,
        ?int $refId = null,
        ?string $note = null,
        ?int $userId = null
    ): array {
        if (empty($lines)) {
            return [];
        }

        $userId = $userId ?? Auth::id();
        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        $results = [];

        try {
            foreach ($lines as $index => $line) {
                $variantId = (int)($line['variant_id'] ?? 0);
                $fromLocation = trim((string)($line['from_location'] ?? ''));
                $toLocation = trim((string)($line['to_location'] ?? ''));
                $qty = (float)($line['qty'] ?? 0);
                $lineNote = trim((string)($line['note'] ?? $note ?? ''));

                if ($variantId <= 0) {
                    throw new Exception("Batch transfer line #{$index}: Invalid variant ID.");
                }
                if ($qty <= 0) {
                    continue; // Skip zero quantity lines
                }
                if ($fromLocation === '' || $toLocation === '') {
                    throw new Exception("Batch transfer line #{$index}: Both from_location and to_location are required.");
                }
                if ($fromLocation === $toLocation) {
                    throw new Exception("Batch transfer line #{$index}: Cannot transfer to the same location ('{$fromLocation}').");
                }

                // 1. Debit from source location
                $sourceBalAfter = self::move(
                    $concernId,
                    $variantId,
                    $fromLocation,
                    0.00,
                    $qty,
                    $refModule,
                    $refId,
                    "Transfer OUT to '{$toLocation}': {$lineNote}",
                    $userId
                );

                // 2. Credit to destination location
                $destBalAfter = self::move(
                    $concernId,
                    $variantId,
                    $toLocation,
                    $qty,
                    0.00,
                    $refModule,
                    $refId,
                    "Transfer IN from '{$fromLocation}': {$lineNote}",
                    $userId
                );

                $results[] = [
                    'variant_id' => $variantId,
                    'qty' => $qty,
                    'from_location' => $fromLocation,
                    'to_location' => $toLocation,
                    'source_balance' => $sourceBalAfter,
                    'dest_balance' => $destBalAfter,
                ];
            }

            if (!$hasActiveTx) {
                DB::commit();
            }

            return $results;
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
     * Alias for available stock check.
     */
    public static function getAvailable(
        int $variantId,
        string $locationType = 'WAREHOUSE',
        $locationTarget = 'Main Store',
        ?int $concernId = null,
        string $stockState = 'AVAILABLE'
    ): float {
        return self::getBalance($variantId, $locationType, $locationTarget, $stockState, $concernId);
    }

    /**
     * Reserve stock at source location (marks as RESERVED).
     */
    public static function reserve(
        int $concernId,
        int $variantId,
        string $location,
        float $qty,
        string $refModule,
        ?int $refId = null
    ): void {
        $availLoc = self::formatLocation('', $location, 'AVAILABLE');
        $resLoc = self::formatLocation('', $location, 'RESERVED');
        self::transferBatch(
            $concernId,
            [['variant_id' => $variantId, 'from_location' => $availLoc, 'to_location' => $resLoc, 'qty' => $qty]],
            $refModule,
            $refId,
            "Reserved stock"
        );
    }

    /**
     * Release reserved stock back to available.
     */
    public static function release(
        int $concernId,
        int $variantId,
        string $location,
        float $qty,
        string $refModule,
        ?int $refId = null
    ): void {
        $availLoc = self::formatLocation('', $location, 'AVAILABLE');
        $resLoc = self::formatLocation('', $location, 'RESERVED');
        self::transferBatch(
            $concernId,
            [['variant_id' => $variantId, 'from_location' => $resLoc, 'to_location' => $availLoc, 'qty' => $qty]],
            $refModule,
            $refId,
            "Released reserved stock"
        );
    }

    /**
     * Helper to format consistent location keys (e.g. "Main Store", "PRODUCTION_UNIT:2", "WAREHOUSE:1:IN_TRANSIT", "SHOP:1:DAMAGED")
     */
    public static function formatLocation(string $locationType, $locationTarget, string $stockState = 'AVAILABLE'): string {
        $type = strtoupper(trim($locationType));
        $target = $locationTarget !== null ? trim((string)$locationTarget) : '';

        if ($type === '' || $type === 'WAREHOUSE' || $type === 'STORE') {
            $base = $target ?: 'Main Store';
        } else {
            $base = $target !== '' ? "{$type}:{$target}" : $type;
        }

        $cleanState = strtoupper(trim($stockState));
        if ($cleanState !== '' && $cleanState !== 'AVAILABLE') {
            // Avoid double suffix if already contains state
            if (!str_ends_with($base, ":{$cleanState}")) {
                $base .= ':' . $cleanState;
            }
        }
        return $base;
    }

    /**
     * Get live stock for a variant
     */
    public static function getQty(int $concernId, int $variantId, string $location = 'Main Store'): float {
        $locations = [$location];
        if ($location === 'Main Store') {
            $locations[] = 'Main Store (WH-01)';
        } elseif ($location === 'Main Store (WH-01)') {
            $locations[] = 'Main Store';
        }
        $inClause = implode(',', array_fill(0, count($locations), '?'));
        $res = DB::fetchValue(
            "SELECT COALESCE(SUM(qty), 0) FROM stocks WHERE concern_id = ? AND variant_id = ? AND location IN ($inClause)",
            array_merge([$concernId, $variantId], $locations)
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
