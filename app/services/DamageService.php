<?php
// app/services/DamageService.php - Unified single source of truth for damage lifecycle & stock ledger effects

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use Exception;

class DamageService {
    /**
     * Log a new damage incident and move stock from AVAILABLE to DAMAGED.
     *
     * @param int $concernId
     * @param int $variantId
     * @param string $locationType 'WAREHOUSE', 'SHOP', 'CONCERN', or 'PRODUCTION_UNIT'
     * @param mixed $locationId Warehouse ID, Shop ID, or Unit ID
     * @param float $qty
     * @param string $reason
     * @param string|null $notes
     * @param int|null $userId
     * @param string|null $photoUrl
     * @param string $severity 'MINOR', 'MODERATE', 'SEVERE', 'SCRAP'
     * @param string $tableType 'CONCERN', 'WAREHOUSE', or 'SHOP'
     * @return array ['id' => int, 'damage_no' => string]
     * @throws Exception
     */
    public static function log(
        int $concernId,
        int $variantId,
        string $locationType,
        $locationId,
        float $qty,
        string $reason,
        ?string $notes = null,
        ?int $userId = null,
        ?string $photoUrl = null,
        string $severity = 'MODERATE',
        string $tableType = 'CONCERN',
        bool $exactStockLocation = false
    ): array {
        if ($qty <= 0) {
            throw new Exception("Damage quantity must be greater than zero.");
        }

        $userId = $userId ?? Auth::id();
        $tableType = strtoupper(trim($tableType));
        $docType = match ($tableType) {
            'WAREHOUSE' => 'DAMAGE_WH',
            'SHOP' => 'DAMAGE_SHOP',
            default => 'DAMAGE_CONCERN',
        };

        $damageNo = DocumentNumberService::generate($docType, $concernId);
        if ($exactStockLocation) {
            $sourceLocation = trim((string)$locationId);
            if ($sourceLocation === '' || strlen($sourceLocation) > 100 || preg_match('/:DAMAGED$/i', $sourceLocation)) {
                throw new Exception('Select a valid available stock location.');
            }
        } else {
            $sourceLocation = StockService::formatLocation($locationType, $locationId, 'AVAILABLE');
        }
        $damagedLocation = self::locationWithState($sourceLocation, 'DAMAGED');

        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            $tableName = self::resolveTable($tableType);

            // 1. Insert record
            if ($tableType === 'WAREHOUSE') {
                DB::query(
                    "INSERT INTO warehouse_damages (concern_id, damage_no, warehouse_id, variant_id, qty, location, severity, status, resolution_reason, resolution_notes, photo_url, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'LOGGED', ?, ?, ?, ?, NOW())",
                    [$concernId, $damageNo, (int)($locationId ?: 1), $variantId, $qty, $sourceLocation, $severity, $reason, $notes, $photoUrl, $userId]
                );
            } elseif ($tableType === 'SHOP') {
                DB::query(
                    "INSERT INTO shop_damages (concern_id, damage_no, shop_id, variant_id, qty, location, severity, status, resolution_reason, resolution_notes, photo_url, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'LOGGED', ?, ?, ?, ?, NOW())",
                    [$concernId, $damageNo, (int)($locationId ?: 1), $variantId, $qty, $sourceLocation, $severity, $reason, $notes, $photoUrl, $userId]
                );
            } else {
                DB::query(
                    "INSERT INTO concern_damages (concern_id, damage_no, variant_id, qty, location, reason, severity, status, photo_url, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'LOGGED', ?, ?, NOW())",
                    [$concernId, $damageNo, $variantId, $qty, $sourceLocation, $reason, $severity, $photoUrl, $userId]
                );
            }

            $damageId = (int)DB::lastInsertId();

            // 2. Move stock: AVAILABLE -> DAMAGED at source location
            StockService::transferBatch(
                $concernId,
                [[
                    'variant_id'    => $variantId,
                    'from_location' => $sourceLocation,
                    'to_location'   => $damagedLocation,
                    'qty'           => $qty,
                    'note'          => "Logged damage [{$damageNo}]: {$reason}"
                ]],
                self::resolveRefModule($tableType),
                $damageId,
                "Logged damage [{$damageNo}]"
            );

            // 3. Audit
            AuditService::log('DAMAGE_LOG', $tableName, $damageId, null, [
                'damage_no' => $damageNo,
                'variant_id' => $variantId,
                'qty' => $qty,
                'location' => $sourceLocation,
                'severity' => $severity,
                'reason' => $reason
            ]);

            if (!$hasActiveTx) {
                DB::commit();
            }

            return ['id' => $damageId, 'damage_no' => $damageNo];
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            throw $e;
        }
    }

    /**
     * Inspect logged damage.
     */
    public static function inspect(
        int $damageId,
        string $severity,
        string $inspectionNotes,
        ?int $userId = null,
        string $tableType = 'CONCERN'
    ): void {
        $table = self::resolveTable($tableType);
        $dmg = DB::fetch("SELECT * FROM `{$table}` WHERE id = ?", [$damageId]);
        if (!$dmg) {
            throw new Exception("Damage record #{$damageId} not found in {$table}.");
        }
        if ($dmg['status'] === 'RESOLVED' || $dmg['status'] === 'CANCELLED') {
            throw new Exception("Cannot inspect damage in status '{$dmg['status']}'.");
        }

        $userId = $userId ?? Auth::id();

        DB::query(
            "UPDATE `{$table}` SET status = 'INSPECTED', severity = ?, resolution_notes = CONCAT(COALESCE(resolution_notes, ''), '\n[Inspection]: ', ?) WHERE id = ?",
            [$severity, $inspectionNotes, $damageId]
        );

        AuditService::log('DAMAGE_INSPECT', $table, $damageId, ['old_status' => $dmg['status']], ['status' => 'INSPECTED', 'severity' => $severity, 'notes' => $inspectionNotes]);
    }

    /**
     * Resolve damage and execute corresponding stock ledger movements.
     */
    public static function resolve(
        int $damageId,
        string $resolutionAction,
        array $params = [],
        ?int $userId = null,
        string $tableType = 'CONCERN'
    ): void {
        $table = self::resolveTable($tableType);
        $dmg = DB::fetch("SELECT * FROM `{$table}` WHERE id = ?", [$damageId]);
        if (!$dmg) {
            throw new Exception("Damage record #{$damageId} not found.");
        }
        if ($dmg['status'] === 'RESOLVED') {
            throw new Exception("Damage record #{$damageId} is already resolved.");
        }
        if ($dmg['status'] === 'CANCELLED') {
            throw new Exception("Cannot resolve cancelled damage record #{$damageId}.");
        }

        $userId = $userId ?? Auth::id();
        $concernId = (int)$dmg['concern_id'];
        $variantId = (int)$dmg['variant_id'];
        $qty = (float)$dmg['qty'];
        $sourceLocation = $dmg['location'] ?: 'Main Store';
        $damagedLocation = self::locationWithState($sourceLocation, 'DAMAGED');
        $refModule = self::resolveRefModule($tableType);

        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            $lossAmount = (float)($params['loss_amount'] ?? 0.00);
            $resolutionReason = trim((string)($params['resolution_reason'] ?? $resolutionAction));
            $resolutionNotes = trim((string)($params['resolution_notes'] ?? ''));
            $replacementQr = null;

            switch (strtoupper($resolutionAction)) {
                case 'SCRAP':
                case 'WRITE_OFF':
                    // Decrease DAMAGED stock (written off from inventory)
                    StockService::move(
                        $concernId,
                        $variantId,
                        $damagedLocation,
                        0.00,
                        $qty,
                        $refModule,
                        $damageId,
                        "Scrapped / Written Off: {$resolutionReason}",
                        $userId
                    );
                    break;

                case 'SALVAGE':
                case 'REPAIR':
                    // Restore DAMAGED -> AVAILABLE at source location
                    $availLocation = self::locationWithState($sourceLocation, 'AVAILABLE');
                    StockService::transferBatch(
                        $concernId,
                        [[
                            'variant_id'    => $variantId,
                            'from_location' => $damagedLocation,
                            'to_location'   => $availLocation,
                            'qty'           => $qty,
                            'note'          => "Salvaged / Repaired: {$resolutionReason}"
                        ]],
                        $refModule,
                        $damageId
                    );
                    break;

                case 'REWORK':
                    // Transfer DAMAGED -> PRODUCTION_UNIT:AVAILABLE
                    $prodLocation = 'PRODUCTION_UNIT:1:AVAILABLE';
                    StockService::transferBatch(
                        $concernId,
                        [[
                            'variant_id'    => $variantId,
                            'from_location' => $damagedLocation,
                            'to_location'   => $prodLocation,
                            'qty'           => $qty,
                            'note'          => "Sent to Rework: {$resolutionReason}"
                        ]],
                        $refModule,
                        $damageId
                    );
                    break;

                case 'RETURN_TO_WAREHOUSE':
                    // Transfer SHOP:DAMAGED -> WAREHOUSE:DAMAGED
                    $whDamaged = 'WAREHOUSE:1:DAMAGED';
                    StockService::transferBatch(
                        $concernId,
                        [[
                            'variant_id'    => $variantId,
                            'from_location' => $damagedLocation,
                            'to_location'   => $whDamaged,
                            'qty'           => $qty,
                            'note'          => "Shop damage returned to central warehouse: {$resolutionReason}"
                        ]],
                        $refModule,
                        $damageId
                    );
                    break;

                case 'SUPPLIER_REPLACEMENT':
                    // Generate unique replacement token/QR; stock increases on inward only!
                    $replacementQr = !empty($params['replacement_qr'])
                        ? trim((string)$params['replacement_qr'])
                        : 'RPL-' . $dmg['damage_no'] . '-' . substr((string)time(), -4);
                    // Deduct from DAMAGED stock awaiting supplier return
                    StockService::move(
                        $concernId,
                        $variantId,
                        $damagedLocation,
                        0.00,
                        $qty,
                        $refModule,
                        $damageId,
                        "Supplier Replacement Claim [QR: {$replacementQr}]: {$resolutionReason}",
                        $userId
                    );
                    break;

                default:
                    throw new Exception("Unsupported damage resolution action: {$resolutionAction}");
            }

            // Update damage record to RESOLVED
            DB::query(
                "UPDATE `{$table}` SET 
                    status = 'RESOLVED',
                    resolution_action = ?,
                    resolution_reason = ?,
                    resolution_notes = ?,
                    loss_amount = ?,
                    replacement_qr_code = COALESCE(?, replacement_qr_code),
                    resolved_by = ?,
                    resolved_at = NOW()
                 WHERE id = ?",
                [$resolutionAction, $resolutionReason, $resolutionNotes, $lossAmount, $replacementQr, $userId, $damageId]
            );

            AuditService::log('DAMAGE_RESOLVE', $table, $damageId, ['old_status' => $dmg['status']], [
                'status' => 'RESOLVED',
                'action' => $resolutionAction,
                'loss_amount' => $lossAmount,
                'replacement_qr' => $replacementQr
            ]);

            if (!$hasActiveTx) {
                DB::commit();
            }
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            throw $e;
        }
    }

    /**
     * Inward supplier replacement using scanned QR code.
     */
    public static function inwardReplacement(
        int $damageId,
        string $scannedQr,
        float $inwardQty,
        ?int $userId = null,
        string $tableType = 'CONCERN'
    ): void {
        $table = self::resolveTable($tableType);
        $dmg = DB::fetch("SELECT * FROM `{$table}` WHERE id = ?", [$damageId]);
        if (!$dmg) {
            throw new Exception("Damage record #{$damageId} not found.");
        }
        if (!empty($dmg['replacement_inwarded_at'])) {
            throw new Exception("Supplier replacement for '{$dmg['damage_no']}' has already been inwarded on {$dmg['replacement_inwarded_at']}.");
        }
        if ($dmg['resolution_action'] !== 'SUPPLIER_REPLACEMENT') {
            throw new Exception("Damage record #{$damageId} was not resolved as SUPPLIER_REPLACEMENT.");
        }

        $userId = $userId ?? Auth::id();
        $concernId = (int)$dmg['concern_id'];
        $variantId = !empty($dmg['replacement_variant_id']) ? (int)$dmg['replacement_variant_id'] : (int)$dmg['variant_id'];
        $sourceLocation = $dmg['location'] ?: 'Main Store';
        $destLocation = self::locationWithState($sourceLocation, 'AVAILABLE');
        $refModule = self::resolveRefModule($tableType);

        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            // Credit live available stock upon physical replacement receipt
            StockService::move(
                $concernId,
                $variantId,
                $destLocation,
                $inwardQty,
                0.00,
                $refModule,
                $damageId,
                "Inwarded Supplier Replacement [QR: {$scannedQr}] for Damage #{$dmg['damage_no']}",
                $userId
            );

            DB::query(
                "UPDATE `{$table}` SET replacement_qr_code = ?, replacement_inwarded_at = NOW() WHERE id = ?",
                [$scannedQr, $damageId]
            );

            AuditService::log('DAMAGE_REPLACEMENT_INWARD', $table, $damageId, null, [
                'damage_no' => $dmg['damage_no'],
                'replacement_qr' => $scannedQr,
                'inward_qty' => $inwardQty,
                'destination' => $destLocation
            ]);

            if (!$hasActiveTx) {
                DB::commit();
            }
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cancel a damage record and reverse DAMAGED stock back to AVAILABLE.
     */
    public static function cancel(
        int $damageId,
        string $cancelReason,
        ?int $userId = null,
        string $tableType = 'CONCERN'
    ): void {
        $table = self::resolveTable($tableType);
        $dmg = DB::fetch("SELECT * FROM `{$table}` WHERE id = ?", [$damageId]);
        if (!$dmg) {
            throw new Exception("Damage record #{$damageId} not found.");
        }
        if ($dmg['status'] === 'CANCELLED') {
            throw new Exception("Damage record #{$damageId} is already cancelled.");
        }
        if ($dmg['status'] === 'RESOLVED') {
            throw new Exception("Cannot cancel already resolved damage record #{$damageId}.");
        }

        $userId = $userId ?? Auth::id();
        $concernId = (int)$dmg['concern_id'];
        $variantId = (int)$dmg['variant_id'];
        $qty = (float)$dmg['qty'];
        $sourceLocation = $dmg['location'] ?: 'Main Store';
        $damagedLocation = self::locationWithState($sourceLocation, 'DAMAGED');
        $availLocation = self::locationWithState($sourceLocation, 'AVAILABLE');
        $refModule = self::resolveRefModule($tableType);

        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            // Reverse DAMAGED -> AVAILABLE
            StockService::transferBatch(
                $concernId,
                [[
                    'variant_id'    => $variantId,
                    'from_location' => $damagedLocation,
                    'to_location'   => $availLocation,
                    'qty'           => $qty,
                    'note'          => "Cancellation reversal for Damage [{$dmg['damage_no']}]: {$cancelReason}"
                ]],
                $refModule,
                $damageId
            );

            DB::query(
                "UPDATE `{$table}` SET status = 'CANCELLED', cancelled_by = ?, cancelled_at = NOW(), cancel_reason = ? WHERE id = ?",
                [$userId, $cancelReason, $damageId]
            );

            AuditService::log('DAMAGE_CANCEL', $table, $damageId, ['old_status' => $dmg['status']], [
                'status' => 'CANCELLED',
                'cancel_reason' => $cancelReason
            ]);

            if (!$hasActiveTx) {
                DB::commit();
            }
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            throw $e;
        }
    }

    private static function resolveTable(string $tableType): string {
        return match (strtoupper($tableType)) {
            'WAREHOUSE' => 'warehouse_damages',
            'SHOP' => 'shop_damages',
            default => 'concern_damages',
        };
    }

    private static function resolveRefModule(string $tableType): string {
        return match (strtoupper($tableType)) {
            'WAREHOUSE' => 'Warehouse Damage',
            'SHOP' => 'Shop Damage',
            default => 'Concern Damage',
        };
    }

    private static function locationWithState(string $location, string $state): string {
        $location = trim($location);
        if (preg_match('/:(AVAILABLE|DAMAGED)$/i', $location)) {
            return substr($location, 0, strrpos($location, ':') + 1) . strtoupper($state);
        }
        if (str_ends_with($location, ':' . strtoupper($state))) {
            return $location;
        }
        return $state === 'AVAILABLE' ? $location : $location . ':' . strtoupper($state);
    }
}
