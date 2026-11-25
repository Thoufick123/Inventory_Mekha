<?php

namespace App\Services;

use App\Core\DB;
use RuntimeException;

class PurchaseDamageService {
    private static bool $tableChecked = false;

    public static function initTable(): void {
        if (self::$tableChecked || DB::pdo()->inTransaction()) {
            return;
        }

        self::$tableChecked = true;
        DB::query("CREATE TABLE IF NOT EXISTS `purchase_damage_quarantine` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `concern_id` INT NOT NULL,
            `damage_no` VARCHAR(50) NOT NULL,
            `item_type` VARCHAR(30) NOT NULL,
            `purchase_bill_item_id` INT NULL,
            `variant_id` INT NULL,
            `supplier_name` VARCHAR(150) NULL,
            `item_name` VARCHAR(255) NOT NULL,
            `item_code` VARCHAR(100) NULL,
            `tag_code` VARCHAR(30) NOT NULL,
            `roll_source_color` VARCHAR(100) NULL,
            `roll_source_design_name` VARCHAR(150) NULL,
            `qty` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
            `damaged_weight` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `estimated_loss` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `location` VARCHAR(100) NOT NULL DEFAULT 'Main Store',
            `reason` VARCHAR(255) NOT NULL,
            `notes` TEXT NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'Quarantined',
            `created_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_pdq_concern_date` (`concern_id`, `created_at`),
            INDEX `idx_pdq_purchase_item` (`purchase_bill_item_id`),
            INDEX `idx_pdq_variant` (`variant_id`),
            CONSTRAINT `fk_pdq_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $columns = DB::fetchAll("SHOW COLUMNS FROM purchase_damage_quarantine");
        $existingColumns = array_column($columns, 'Field');
        if (!in_array('roll_source_color', $existingColumns, true)) {
            DB::query("ALTER TABLE purchase_damage_quarantine ADD COLUMN roll_source_color VARCHAR(100) NULL AFTER tag_code");
        }
        if (!in_array('roll_source_design_name', $existingColumns, true)) {
            DB::query("ALTER TABLE purchase_damage_quarantine ADD COLUMN roll_source_design_name VARCHAR(150) NULL AFTER roll_source_color");
        }

        DB::query("CREATE TABLE IF NOT EXISTS `purchase_damage_reasons` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `concern_id` INT NOT NULL,
            `reason` VARCHAR(255) NOT NULL,
            `created_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_pdr_concern_reason` (`concern_id`, `reason`),
            CONSTRAINT `fk_pdr_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::backfillRollDamageItemIds();
    }

    public static function backfillRollDamageItemIds(?int $concernId = null): void {
        try {
            $where = "item_type = 'rolls' AND (purchase_bill_item_id IS NULL OR purchase_bill_item_id = 0)";
            $params = [];
            if ($concernId) {
                $where .= " AND concern_id = ?";
                $params[] = $concernId;
            }
            $unlinked = DB::fetchAll("SELECT id, concern_id, tag_code, roll_source_color, roll_source_design_name, item_name, item_code FROM purchase_damage_quarantine WHERE {$where}", $params);
            if (empty($unlinked)) {
                return;
            }

            foreach ($unlinked as $u) {
                $uCid = (int)$u['concern_id'];
                $uTag = strtoupper(trim((string)$u['tag_code']));
                $uColor = strtoupper(trim((string)$u['roll_source_color']));
                $uDesign = strtoupper(trim((string)$u['roll_source_design_name']));
                $uItemName = strtoupper(trim((string)$u['item_name']));

                $pbiList = DB::fetchAll(
                    "SELECT id, item_code, item_name, color, pattern, specification 
                     FROM purchase_bill_items 
                     WHERE concern_id = ? AND section_type = 'rolls' 
                     ORDER BY id DESC",
                    [$uCid]
                );

                $matchedPbiId = null;
                // First pass: Match by tag_code or item_code
                foreach ($pbiList as $pbi) {
                    $candTag = strtoupper(trim(QrService::generateTagCode(
                        $pbi['item_name'] ?: 'Fabric',
                        $pbi['color'] ?? '',
                        $pbi['pattern'] ?: ($pbi['specification'] ?? ''),
                        (string)$pbi['id']
                    )));
                    $pbiCode = strtoupper(trim((string)$pbi['item_code']));
                    if (($uTag !== '' && ($candTag === $uTag || $pbiCode === $uTag)) ||
                        ($u['item_code'] && $pbiCode === strtoupper(trim((string)$u['item_code'])))) {
                        $matchedPbiId = (int)$pbi['id'];
                        break;
                    }
                }

                // Second pass: Match by Color & Design/Pattern or Item Name
                if (!$matchedPbiId) {
                    foreach ($pbiList as $pbi) {
                        $pbiColor = strtoupper(trim((string)$pbi['color']));
                        $pbiDesign = strtoupper(trim((string)($pbi['pattern'] ?: $pbi['item_name'])));
                        $pbiName = strtoupper(trim((string)$pbi['item_name']));

                        if ($uColor !== '' && $pbiColor === $uColor) {
                            if (($uDesign !== '' && $pbiDesign === $uDesign) || ($uItemName !== '' && $pbiName === $uItemName)) {
                                $matchedPbiId = (int)$pbi['id'];
                                break;
                            }
                        }
                    }
                }

                if ($matchedPbiId) {
                    DB::query(
                        "UPDATE purchase_damage_quarantine SET purchase_bill_item_id = ? WHERE id = ?",
                        [$matchedPbiId, (int)$u['id']]
                    );
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore backfill errors
        }
    }

    public static function getDamageReasons(int $concernId): array {
        self::initTable();
        if (!$concernId) {
            return [];
        }

        return DB::fetchAll(
            "SELECT reason FROM purchase_damage_reasons WHERE concern_id = ? ORDER BY reason ASC",
            [$concernId]
        );
    }

    public static function addDamageReason(int $concernId, string $reason, ?int $userId): string {
        self::initTable();
        $reason = trim($reason);
        if (!$concernId || $reason === '' || strlen($reason) > 255) {
            throw new RuntimeException('Enter a damage reason with a maximum of 255 characters.');
        }

        DB::query(
            "INSERT INTO purchase_damage_reasons (concern_id, reason, created_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)",
            [$concernId, $reason, $userId]
        );
        $reasonId = (int)DB::lastInsertId();
        $saved = DB::fetch(
            "SELECT reason FROM purchase_damage_reasons WHERE id = ? AND concern_id = ?",
            [$reasonId, $concernId]
        );
        if (!$saved) {
            throw new RuntimeException('Could not save the damage reason.');
        }

        return $saved['reason'];
    }

    public static function resolveTag(
        int $concernId,
        string $tagCode,
        bool $syncRolls = true,
        bool $includeInternal = false
    ): array {
        self::initTable();
        PurchaseStockItemOverrideService::initTable();

        $tagCode = strtoupper(trim($tagCode));
        if (!$concernId || $tagCode === '' || strlen($tagCode) > 30) {
            throw new RuntimeException('Scan a valid purchase label first.');
        }

        if ($syncRolls) {
            RollRegistryService::syncSpecs($concernId);
        }

        $rollRows = [];
        foreach (RollRegistryService::getConsolidatedStock($concernId) as $row) {
            $rollRows[strtoupper(trim($row['source_color'])) . "\0" . strtoupper(trim($row['source_design_name']))] = $row;
        }

        $purchaseItems = PurchaseStockItemOverrideService::applyToItems(DB::fetchAll(
            "SELECT pbi.*, pb.bill_no, s.name AS supplier_name,
                    v.name AS variant_name, v.product_code AS variant_product_code,
                    COALESCE(stk.qty, 0) AS live_qty,
                    COALESCE(purchases.total_qty, 0) AS purchased_qty,
                    COALESCE(purchases.total_weight, 0) AS purchased_weight,
                    COALESCE(purchases.total_value, 0) AS purchased_value,
                    COALESCE(quarantined.damaged_qty, 0) AS quarantined_qty,
                    COALESCE(quarantined.damaged_weight, 0) AS quarantined_weight,
                    COALESCE(quarantined.estimated_loss, 0) AS quarantined_loss
             FROM purchase_bill_items pbi
             JOIN purchase_bills pb ON pb.id = pbi.purchase_bill_id
             LEFT JOIN suppliers s ON s.id = pb.supplier_id
             LEFT JOIN variants v ON v.id = pbi.variant_id AND v.concern_id = pbi.concern_id
             LEFT JOIN (
                 SELECT concern_id, variant_id, SUM(qty) AS qty
                 FROM stocks
                 WHERE location = 'Main Store'
                 GROUP BY concern_id, variant_id
             ) stk ON stk.concern_id = pbi.concern_id AND stk.variant_id = pbi.variant_id
             LEFT JOIN (
                 SELECT pbi2.concern_id, pbi2.variant_id,
                        SUM(COALESCE(stock_override.qty, pbi2.qty)) AS total_qty,
                        SUM(COALESCE(pbi2.net_weight, 0)) AS total_weight,
                        SUM(COALESCE(stock_override.total_value, pbi2.total_price, 0)) AS total_value
                 FROM purchase_bill_items pbi2
                 JOIN purchase_bills pb2 ON pb2.id = pbi2.purchase_bill_id
                 LEFT JOIN purchase_stock_item_overrides stock_override
                   ON stock_override.concern_id = pbi2.concern_id
                  AND stock_override.purchase_bill_item_id = pbi2.id
                  AND stock_override.section_type = pbi2.section_type
                 WHERE pbi2.section_type = 'finished_goods'
                   AND COALESCE(NULLIF(pbi2.inward_status, ''), pb2.inward_status, 'pending') = 'completed'
                 GROUP BY pbi2.concern_id, pbi2.variant_id
             ) purchases ON purchases.concern_id = pbi.concern_id AND purchases.variant_id = pbi.variant_id
             LEFT JOIN (
                 SELECT concern_id, variant_id, SUM(qty) AS damaged_qty,
                        SUM(damaged_weight) AS damaged_weight, SUM(estimated_loss) AS estimated_loss
                 FROM purchase_damage_quarantine
                 WHERE item_type = 'finished_goods'
                 GROUP BY concern_id, variant_id
             ) quarantined ON quarantined.concern_id = pbi.concern_id AND quarantined.variant_id = pbi.variant_id
             WHERE pbi.concern_id = ?
               AND COALESCE(NULLIF(pbi.inward_status, ''), pb.inward_status, 'pending') = 'completed'
               AND pbi.section_type IN ('rolls', 'garment_other', 'finished_goods')
             ORDER BY pbi.id ASC",
            [$concernId]
        ));

        $otherDamages = DB::fetchAll(
            "SELECT purchase_bill_item_id, SUM(qty) AS damaged_qty,
                    SUM(damaged_weight) AS damaged_weight, SUM(estimated_loss) AS estimated_loss
             FROM purchase_damage_quarantine
             WHERE concern_id = ? AND item_type = 'garment_other'
             GROUP BY purchase_bill_item_id",
            [$concernId]
        );
        $otherDamageByItem = [];
        foreach ($otherDamages as $row) {
            $otherDamageByItem[(int)$row['purchase_bill_item_id']] = $row;
        }

        $matches = [];
        foreach ($purchaseItems as $item) {
            $section = $item['section_type'];
            $tag = self::tagForPurchaseItem($item);
            if ($tag !== $tagCode) {
                continue;
            }

            if ($section === 'rolls') {
                $sourceColor = strtoupper(trim((string)$item['color']));
                $sourceDesign = strtoupper(trim((string)($item['pattern'] ?: $item['item_name'])));
                if ($sourceColor === '' || $sourceDesign === '') {
                    continue;
                }

                $sourceKey = $sourceColor . "\0" . $sourceDesign;
                $row = $rollRows[$sourceKey] ?? null;
                $key = hash('sha256', "rolls\0" . $sourceKey);
                $matches[$key] = [
                    'key' => $key,
                    'item_type' => 'rolls',
                    'item_name' => $item['item_name'] ?: 'Fabric Roll',
                    'item_code' => $item['item_code'] ?: ('ROLL-' . $item['id']),
                    'tag_code' => $tag,
                    'available_qty' => $row ? (float)$row['qty'] : 0.0,
                    'unit_weight' => $row && (float)$row['qty'] > 0 ? (float)$row['net_weight'] / (float)$row['qty'] : 0.0,
                    'unit_loss' => $row && (float)$row['qty'] > 0 ? (float)$row['total_value'] / (float)$row['qty'] : 0.0,
                    'supplier_name' => $item['supplier_name'] ?? '',
                    'purchase_bill_item_id' => (int)$item['id'],
                    'variant_id' => null,
                    'roll_row' => $row,
                    'source_items' => [],
                ];
                continue;
            }

            if ($section === 'garment_other') {
                $itemId = (int)$item['id'];
                $priorDamage = $otherDamageByItem[$itemId] ?? ['damaged_qty' => 0, 'damaged_weight' => 0, 'estimated_loss' => 0];
                $qty = max(0.0, (float)$item['qty'] - (float)$priorDamage['damaged_qty']);
                $remainingWeight = max(0.0, (float)$item['net_weight'] - (float)$priorDamage['damaged_weight']);
                $remainingValue = max(0.0, (float)$item['total_price'] - (float)$priorDamage['estimated_loss']);
                $key = hash('sha256', "garment_other\0" . $tag . "\0" . $itemId);

                $matches[$key] = [
                    'key' => $key,
                    'item_type' => 'garment_other',
                    'item_name' => $item['item_name'] ?: 'Garment Supply',
                    'item_code' => $item['item_code'] ?: ('TRIM-' . $itemId),
                    'tag_code' => $tag,
                    'available_qty' => $qty,
                    'unit_weight' => $qty > 0 ? $remainingWeight / $qty : 0.0,
                    'unit_loss' => $qty > 0 ? $remainingValue / $qty : 0.0,
                    'supplier_name' => $item['supplier_name'] ?? '',
                    'purchase_bill_item_id' => $itemId,
                    'variant_id' => null,
                    'source_items' => [[
                        'id' => $itemId,
                        'qty' => $qty,
                        'unit_weight' => $qty > 0 ? $remainingWeight / $qty : 0.0,
                        'unit_loss' => $qty > 0 ? $remainingValue / $qty : 0.0,
                        'item' => $item,
                    ]],
                ];
                continue;
            }

            $variantId = (int)($item['variant_id'] ?? 0);
            if (!$variantId || !$item['variant_name']) {
                continue;
            }
            $purchasedRemaining = max(0.0, (float)$item['purchased_qty'] - (float)$item['quarantined_qty']);
            $available = min((float)$item['live_qty'], $purchasedRemaining);
            $weightRemaining = max(0.0, (float)$item['purchased_weight'] - (float)$item['quarantined_weight']);
            $valueRemaining = max(0.0, (float)$item['purchased_value'] - (float)$item['quarantined_loss']);
            $key = hash('sha256', "finished_goods\0" . $variantId);

            $matches[$key] = [
                'key' => $key,
                'item_type' => 'finished_goods',
                'item_name' => $item['variant_name'],
                'item_code' => $item['variant_product_code'] ?: ($item['item_code'] ?: ('FG-' . $item['id'])),
                'tag_code' => $tag,
                'available_qty' => $available,
                'unit_weight' => $purchasedRemaining > 0 ? $weightRemaining / $purchasedRemaining : 0.0,
                'unit_loss' => $purchasedRemaining > 0 ? $valueRemaining / $purchasedRemaining : 0.0,
                'supplier_name' => $item['supplier_name'] ?? '',
                'purchase_bill_item_id' => null,
                'variant_id' => $variantId,
                'source_items' => [],
            ];
        }

        if (!$matches) {
            throw new RuntimeException('This label does not match an inwarded Purchase Stock item.');
        }
        if (count($matches) > 1) {
            throw new RuntimeException('This label matches more than one Purchase Stock item. Use a unique label to avoid deducting the wrong stock.');
        }

        $match = reset($matches);
        if ($match['available_qty'] <= 0) {
            throw new RuntimeException('No available Purchase Stock remains for this label.');
        }

        if (!$includeInternal) {
            unset($match['roll_row'], $match['source_items']);
        }
        return $match;
    }

    public static function saveScannedDamage(
        int $concernId,
        array $scannedItems,
        string $reason,
        string $notes,
        ?int $userId
    ): array {
        self::initTable();
        if (!$concernId || !$scannedItems || trim($reason) === '') {
            throw new RuntimeException('Scan at least one label and enter a damage reason.');
        }

        if (strlen($reason) > 255 || strlen($notes) > 2000) {
            throw new RuntimeException('Damage reason or notes exceed the allowed length.');
        }
        if (!DB::fetch(
            "SELECT id FROM purchase_damage_reasons WHERE concern_id = ? AND reason = ?",
            [$concernId, trim($reason)]
        )) {
            throw new RuntimeException('Select a damage reason configured for this concern.');
        }
        if (count($scannedItems) > 500) {
            throw new RuntimeException('A maximum of 500 labels can be recorded at once.');
        }

        RollRegistryService::syncSpecs($concernId);
        $tagQuantities = [];
        foreach ($scannedItems as $scannedItem) {
            if (!is_array($scannedItem)) {
                throw new RuntimeException('The scanned label list contains an invalid entry.');
            }
            $tagCode = $scannedItem['tag_code'] ?? null;
            $rawQty = $scannedItem['qty'] ?? null;
            if ((!is_string($tagCode) && !is_numeric($tagCode)) || !is_scalar($rawQty) || !is_numeric($rawQty)) {
                throw new RuntimeException('The scanned label list contains an invalid value.');
            }
            $tagCode = strtoupper(trim((string)$tagCode));
            if ($tagCode === '' || strlen($tagCode) > 30) {
                throw new RuntimeException('The scanned label list contains an invalid code.');
            }
            $qty = (float)$rawQty;
            if (!is_finite($qty) || $qty <= 0 || abs($qty - round($qty)) > 0.000001) {
                throw new RuntimeException('Enter a whole-number quantity greater than zero.');
            }
            $tagQuantities[$tagCode] = ($tagQuantities[$tagCode] ?? 0) + (int)round($qty);
        }

        $grouped = [];
        foreach ($tagQuantities as $tagCode => $qty) {
            $item = self::resolveTag($concernId, $tagCode, false, true);
            $key = $item['key'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['item' => $item, 'qty' => $qty];
            } else {
                $grouped[$key]['qty'] += $qty;
            }
        }

        DB::beginTransaction();
        try {
            $damageNo = NumberSeries::getNext($concernId, 'DAMAGE');
            $savedCount = 0;
            $damagedWeight = 0.0;
            $estimatedLoss = 0.0;

            foreach ($grouped as $group) {
                $item = $group['item'];
                $qty = (float)$group['qty'];
                if ($qty - (float)$item['available_qty'] > 0.00001) {
                    throw new RuntimeException("Only {$item['available_qty']} unit(s) remain for {$item['item_name']}.");
                }

                $weight = $item['unit_weight'] * $qty;
                $loss = $item['unit_loss'] * $qty;

                if ($item['item_type'] === 'rolls') {
                    $base = DB::fetch(
                        "SELECT * FROM fabric_roll_specs
                         WHERE concern_id = ? AND color = ? AND design_name = ? FOR UPDATE",
                        [$concernId, $item['roll_row']['source_color'], $item['roll_row']['source_design_name']]
                    );
                    if (!$base) {
                        throw new RuntimeException('The scanned roll is no longer available in Purchase Stock.');
                    }
                    $current = DB::fetch(
                        "SELECT * FROM fabric_roll_spec_overrides
                         WHERE concern_id = ? AND source_color = ? AND source_design_name = ? FOR UPDATE",
                        [$concernId, $item['roll_row']['source_color'], $item['roll_row']['source_design_name']]
                    );
                    if (!$current) {
                        $current = $base;
                    }
                    if ($qty - (float)$current['qty'] > 0.00001) {
                        throw new RuntimeException("Only {$current['qty']} roll(s) remain for {$item['item_name']}.");
                    }
                    $weight = (float)$current['qty'] > 0
                        ? (float)$current['net_weight'] / (float)$current['qty'] * $qty
                        : 0.0;
                    $loss = (float)$current['qty'] > 0
                        ? (float)$current['total_value'] / (float)$current['qty'] * $qty
                        : 0.0;
                    $newQty = max(0.0, (float)$current['qty'] - $qty);
                    $newWeight = max(0.0, (float)$current['net_weight'] - $weight);
                    $newValue = max(0.0, (float)$current['total_value'] - $loss);
                    $saved = RollRegistryService::saveConsolidatedOverride(
                        $concernId,
                        $item['roll_row']['source_color'],
                        $item['roll_row']['source_design_name'],
                        [
                            'color' => $current['color'],
                            'design_name' => $current['design_name'],
                            'hsn_code' => $current['hsn_code'] ?? '',
                            'qty' => $newQty,
                            'net_weight' => $newWeight,
                            'unit' => $current['unit'],
                            'last_unit_price' => $current['last_unit_price'],
                            'total_value' => $newValue,
                        ]
                    );
                    if (!$saved) {
                        throw new RuntimeException('Could not update the consolidated roll stock. Please retry.');
                    }
                    self::insertQuarantineRow($concernId, $damageNo, $item, $item['purchase_bill_item_id'] ?? null, null, $qty, $weight, $loss, $reason, $notes, $userId);
                    $damagedWeight += $weight;
                    $estimatedLoss += $loss;
                    $savedCount += $qty;
                    continue;
                }

                if ($item['item_type'] === 'garment_other') {
                    $source = DB::fetch(
                        "SELECT pbi.*, s.name AS supplier_name
                         FROM purchase_bill_items pbi
                         JOIN purchase_bills pb ON pb.id = pbi.purchase_bill_id
                         LEFT JOIN suppliers s ON s.id = pb.supplier_id
                         WHERE pbi.id = ? AND pbi.concern_id = ?
                         FOR UPDATE",
                        [$item['purchase_bill_item_id'], $concernId]
                    );
                    $prior = DB::fetch(
                        "SELECT COALESCE(SUM(qty), 0) AS damaged_qty,
                                COALESCE(SUM(damaged_weight), 0) AS damaged_weight,
                                COALESCE(SUM(estimated_loss), 0) AS estimated_loss
                         FROM purchase_damage_quarantine
                         WHERE concern_id = ? AND purchase_bill_item_id = ? AND item_type = 'garment_other'",
                        [$concernId, $item['purchase_bill_item_id']]
                    );
                    $remainingQty = max(0.0, (float)$source['qty'] - (float)$prior['damaged_qty']);
                    if ($qty - $remainingQty > 0.00001) {
                        throw new RuntimeException("Only {$remainingQty} unit(s) remain for {$item['item_name']}.");
                    }
                    $unitWeight = max(0.0, (float)$source['net_weight'] - (float)$prior['damaged_weight']) / $remainingQty;
                    $unitLoss = max(0.0, (float)$source['total_price'] - (float)$prior['estimated_loss']) / $remainingQty;
                    self::insertQuarantineRow(
                        $concernId,
                        $damageNo,
                        $item,
                        (int)$source['id'],
                        null,
                        $qty,
                        $unitWeight * $qty,
                        $unitLoss * $qty,
                        $reason,
                        $notes,
                        $userId,
                        $source['supplier_name'] ?? ''
                    );
                    $damagedWeight += $unitWeight * $qty;
                    $estimatedLoss += $unitLoss * $qty;
                    $savedCount += $qty;
                    continue;
                }

                $currentQty = StockService::getQty($concernId, (int)$item['variant_id']);
                if ($qty - $currentQty > 0.00001) {
                    throw new RuntimeException("Only {$currentQty} unit(s) remain for {$item['item_name']}.");
                }
                $damageId = self::insertQuarantineRow(
                    $concernId,
                    $damageNo,
                    $item,
                    null,
                    (int)$item['variant_id'],
                    $qty,
                    $weight,
                    $loss,
                    $reason,
                    $notes,
                    $userId
                );
                StockService::move(
                    $concernId,
                    (int)$item['variant_id'],
                    'Main Store',
                    0.0,
                    $qty,
                    'Purchase Damage',
                    $damageId,
                    "Purchase Damage {$damageNo}: {$reason}",
                    $userId
                );
                $damagedWeight += $weight;
                $estimatedLoss += $loss;
                $savedCount += $qty;
            }

            DB::commit();
            return [
                'damage_no' => $damageNo,
                'count' => $savedCount,
                'damaged_weight' => $damagedWeight,
                'estimated_loss' => $estimatedLoss,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function updateQuarantineRecord(
        int $concernId,
        int $damageId,
        float $newQty,
        string $reason,
        string $notes,
        ?int $userId
    ): array {
        self::initTable();
        PurchaseStockItemOverrideService::initTable();
        RollRegistryService::syncSpecs($concernId);

        if (!$concernId || !$damageId || !is_finite($newQty) || $newQty <= 0 || $newQty > 500) {
            throw new RuntimeException('Enter a valid quarantine quantity.');
        }
        if (trim($reason) === '' || strlen($reason) > 255 || strlen($notes) > 2000) {
            throw new RuntimeException('Enter a valid reason and notes within the allowed length.');
        }

        DB::beginTransaction();
        try {
            $record = DB::fetch(
                "SELECT * FROM purchase_damage_quarantine
                 WHERE id = ? AND concern_id = ? FOR UPDATE",
                [$damageId, $concernId]
            );
            if (!$record || ($record['status'] ?? '') !== 'Quarantined') {
                throw new RuntimeException('This quarantine record is no longer available to edit.');
            }

            $oldQty = (float)$record['qty'];
            $unitWeight = $oldQty > 0 ? (float)$record['damaged_weight'] / $oldQty : 0.0;
            $unitLoss = $oldQty > 0 ? (float)$record['estimated_loss'] / $oldQty : 0.0;
            $newWeight = round($unitWeight * $newQty, 2);
            $newLoss = round($unitLoss * $newQty, 2);

            self::reconcileQuarantineStock($concernId, $record, $newQty, $newWeight, $newLoss, $userId);

            DB::query(
                "UPDATE purchase_damage_quarantine
                 SET qty = ?, damaged_weight = ?, estimated_loss = ?, reason = ?, notes = ?
                 WHERE id = ? AND concern_id = ?",
                [
                    $newQty,
                    $newWeight,
                    $newLoss,
                    trim($reason),
                    trim($notes) !== '' ? trim($notes) : null,
                    $damageId,
                    $concernId,
                ]
            );
            $after = DB::fetch(
                "SELECT * FROM purchase_damage_quarantine WHERE id = ? AND concern_id = ?",
                [$damageId, $concernId]
            );
            DB::commit();

            return ['before' => $record, 'after' => $after];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function deleteQuarantineRecord(
        int $concernId,
        int $damageId,
        ?int $userId
    ): array {
        self::initTable();
        PurchaseStockItemOverrideService::initTable();
        RollRegistryService::syncSpecs($concernId);

        if (!$concernId || !$damageId) {
            throw new RuntimeException('Select a valid quarantine record.');
        }

        DB::beginTransaction();
        try {
            $record = DB::fetch(
                "SELECT * FROM purchase_damage_quarantine
                 WHERE id = ? AND concern_id = ? FOR UPDATE",
                [$damageId, $concernId]
            );
            if (!$record || ($record['status'] ?? '') !== 'Quarantined') {
                throw new RuntimeException('This quarantine record is no longer available to delete.');
            }

            self::reconcileQuarantineStock($concernId, $record, 0.0, 0.0, 0.0, $userId);
            DB::query(
                "DELETE FROM purchase_damage_quarantine WHERE id = ? AND concern_id = ?",
                [$damageId, $concernId]
            );
            DB::commit();

            return ['before' => $record];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private static function reconcileQuarantineStock(
        int $concernId,
        array $record,
        float $newQty,
        float $newWeight,
        float $newLoss,
        ?int $userId
    ): void {
        $oldQty = (float)$record['qty'];
        $oldWeight = (float)$record['damaged_weight'];
        $oldLoss = (float)$record['estimated_loss'];

        if ($record['item_type'] === 'rolls') {
            [$sourceColor, $sourceDesign] = self::rollSourceCoordinates($concernId, $record);
            $base = DB::fetch(
                "SELECT * FROM fabric_roll_specs
                 WHERE concern_id = ? AND color = ? AND design_name = ? FOR UPDATE",
                [$concernId, $sourceColor, $sourceDesign]
            );
            if (!$base) {
                throw new RuntimeException('The source fabric roll specification could not be found.');
            }
            $current = DB::fetch(
                "SELECT * FROM fabric_roll_spec_overrides
                 WHERE concern_id = ? AND source_color = ? AND source_design_name = ? FOR UPDATE",
                [$concernId, $sourceColor, $sourceDesign]
            ) ?: $base;

            $qtyDelta = $newQty - $oldQty;
            if ($qtyDelta > 0 && (float)$current['qty'] < $qtyDelta) {
                throw new RuntimeException('The remaining fabric roll stock is too low for this quantity.');
            }
            $saved = RollRegistryService::saveConsolidatedOverride(
                $concernId,
                $sourceColor,
                $sourceDesign,
                [
                    'color' => $current['color'],
                    'design_name' => $current['design_name'],
                    'hsn_code' => $current['hsn_code'] ?? '',
                    'qty' => max(0.0, (float)$current['qty'] - $qtyDelta),
                    'net_weight' => max(0.0, (float)$current['net_weight'] - ($newWeight - $oldWeight)),
                    'unit' => $current['unit'],
                    'last_unit_price' => $current['last_unit_price'],
                    'total_value' => max(0.0, (float)$current['total_value'] - ($newLoss - $oldLoss)),
                ]
            );
            if (!$saved) {
                throw new RuntimeException('Could not reconcile the consolidated fabric stock.');
            }

            if (empty($record['roll_source_color']) || empty($record['roll_source_design_name'])) {
                DB::query(
                    "UPDATE purchase_damage_quarantine
                     SET roll_source_color = ?, roll_source_design_name = ?
                     WHERE id = ? AND concern_id = ?",
                    [$sourceColor, $sourceDesign, $record['id'], $concernId]
                );
            }
            return;
        }

        if ($record['item_type'] === 'garment_other') {
            $source = DB::fetch(
                "SELECT pbi.*, pb.inward_status AS bill_inward_status
                 FROM purchase_bill_items pbi
                 JOIN purchase_bills pb ON pb.id = pbi.purchase_bill_id
                 WHERE pbi.id = ? AND pbi.concern_id = ? AND pbi.section_type = 'garment_other'
                 FOR UPDATE",
                [$record['purchase_bill_item_id'], $concernId]
            );
            if (!$source) {
                throw new RuntimeException('The source garment supply could not be found.');
            }
            $source = PurchaseStockItemOverrideService::applyToItems([$source])[0];
            $otherDamagedQty = (float)DB::fetchValue(
                "SELECT COALESCE(SUM(qty), 0)
                 FROM purchase_damage_quarantine
                 WHERE concern_id = ? AND purchase_bill_item_id = ? AND item_type = 'garment_other' AND id <> ?",
                [$concernId, $record['purchase_bill_item_id'], $record['id']]
            );
            if ($newQty > max(0.0, (float)$source['qty'] - $otherDamagedQty)) {
                throw new RuntimeException('The revised quantity exceeds the available supply stock.');
            }
            return;
        }

        if ($record['item_type'] === 'finished_goods') {
            $variantId = (int)$record['variant_id'];
            $purchasedQty = (float)DB::fetchValue(
                "SELECT COALESCE(SUM(COALESCE(stock_override.qty, pbi.qty)), 0)
                 FROM purchase_bill_items pbi
                 JOIN purchase_bills pb ON pb.id = pbi.purchase_bill_id
                 LEFT JOIN purchase_stock_item_overrides stock_override
                   ON stock_override.concern_id = pbi.concern_id
                  AND stock_override.purchase_bill_item_id = pbi.id
                  AND stock_override.section_type = pbi.section_type
                 WHERE pbi.concern_id = ? AND pbi.variant_id = ? AND pbi.section_type = 'finished_goods'
                   AND COALESCE(NULLIF(pbi.inward_status, ''), pb.inward_status, 'pending') = 'completed'",
                [$concernId, $variantId]
            );
            $otherDamagedQty = (float)DB::fetchValue(
                "SELECT COALESCE(SUM(qty), 0)
                 FROM purchase_damage_quarantine
                 WHERE concern_id = ? AND variant_id = ? AND item_type = 'finished_goods' AND id <> ?",
                [$concernId, $variantId, $record['id']]
            );
            if ($newQty > max(0.0, $purchasedQty - $otherDamagedQty)) {
                throw new RuntimeException('The revised quantity exceeds the available purchased quantity.');
            }

            $qtyDelta = $newQty - $oldQty;
            if (abs($qtyDelta) > 0.00001) {
                StockService::move(
                    $concernId,
                    $variantId,
                    $record['location'] ?: 'Main Store',
                    $qtyDelta < 0 ? abs($qtyDelta) : 0.0,
                    $qtyDelta > 0 ? $qtyDelta : 0.0,
                    'Purchase Damage Correction',
                    (int)$record['id'],
                    "Purchase Damage {$record['damage_no']} quantity adjustment",
                    $userId
                );
            }
            return;
        }

        throw new RuntimeException('This quarantine item type cannot be edited.');
    }

    private static function rollSourceCoordinates(int $concernId, array $record): array {
        $sourceColor = strtoupper(trim((string)($record['roll_source_color'] ?? '')));
        $sourceDesign = strtoupper(trim((string)($record['roll_source_design_name'] ?? '')));
        if ($sourceColor !== '' && $sourceDesign !== '') {
            return [$sourceColor, $sourceDesign];
        }

        $items = DB::fetchAll(
            "SELECT pbi.*
             FROM purchase_bill_items pbi
             JOIN purchase_bills pb ON pb.id = pbi.purchase_bill_id
             WHERE pbi.concern_id = ? AND pbi.section_type = 'rolls'
               AND COALESCE(NULLIF(pbi.inward_status, ''), pb.inward_status, 'pending') = 'completed'
             ORDER BY pbi.id ASC",
            [$concernId]
        );
        $items = PurchaseStockItemOverrideService::applyToItems($items);
        foreach ($items as $item) {
            if (self::tagForPurchaseItem($item) !== strtoupper(trim((string)$record['tag_code']))) {
                continue;
            }
            $sourceColor = strtoupper(trim((string)$item['color']));
            $sourceDesign = strtoupper(trim((string)($item['pattern'] ?: $item['item_name'])));
            if ($sourceColor === '' || $sourceDesign === '') {
                break;
            }
            return [$sourceColor, $sourceDesign];
        }

        throw new RuntimeException('Could not map this older roll quarantine record to its source stock. No stock was changed.');
    }

    private static function tagForPurchaseItem(array $item): string {
        $section = $item['section_type'];
        if ($section === 'rolls' || $section === 'garment_other') {
            return QrService::generateTagCode(
                $item['item_name'] ?: ($section === 'rolls' ? 'Fabric' : 'Trims'),
                $item['color'] ?? '',
                $item['pattern'] ?: ($item['specification'] ?? ''),
                (string)$item['id']
            );
        }

        return QrService::generateTagCode(
            $item['variant_name'] ?: ($item['item_name'] ?: 'Garment'),
            $item['color'] ?? '',
            $item['specification'] ?? '',
            (string)$item['id']
        );
    }

    private static function insertQuarantineRow(
        int $concernId,
        string $damageNo,
        array $item,
        ?int $purchaseBillItemId,
        ?int $variantId,
        float $qty,
        float $weight,
        float $loss,
        string $reason,
        string $notes,
        ?int $userId,
        ?string $supplierName = null
    ): int {
        DB::query(
            "INSERT INTO purchase_damage_quarantine
             (concern_id, damage_no, item_type, purchase_bill_item_id, variant_id, supplier_name,
              item_name, item_code, tag_code, roll_source_color, roll_source_design_name, qty, damaged_weight, estimated_loss, location,
              reason, notes, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Main Store', ?, ?, 'Quarantined', ?, NOW())",
            [
                $concernId,
                $damageNo,
                $item['item_type'],
                $purchaseBillItemId,
                $variantId,
                $supplierName ?? ($item['supplier_name'] ?? ''),
                $item['item_name'],
                $item['item_code'],
                $item['tag_code'],
                $item['roll_row']['source_color'] ?? null,
                $item['roll_row']['source_design_name'] ?? null,
                $qty,
                $weight,
                $loss,
                $reason,
                $notes !== '' ? $notes : null,
                $userId,
            ]
        );
        return (int)DB::lastInsertId();
    }
}
