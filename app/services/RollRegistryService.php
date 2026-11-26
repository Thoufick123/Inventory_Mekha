<?php
namespace App\Services;

use App\Core\DB;

class RollRegistryService {

    private static bool $tableChecked = false;

    /**
     * Ensure fabric_roll_specs table exists
     */
    public static function initTable(): void {
        if (self::$tableChecked || DB::pdo()->inTransaction()) {
            return;
        }
        self::$tableChecked = true;
        DB::query("CREATE TABLE IF NOT EXISTS `fabric_roll_specs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `concern_id` INT NOT NULL,
            `color` VARCHAR(100) NOT NULL,
            `design_name` VARCHAR(150) NOT NULL,
            `qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `net_weight` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `unit` VARCHAR(50) NOT NULL DEFAULT 'Rolls',
            `last_unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `total_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `bills_count` INT NOT NULL DEFAULT 1,
            `last_bill_no` VARCHAR(100) NULL,
            `last_supplier_name` VARCHAR(150) NULL,
            `hsn_code` VARCHAR(50) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_concern_color_design` (`concern_id`, `color`, `design_name`),
            INDEX `idx_frs_concern` (`concern_id`),
            CONSTRAINT `fk_frs_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        DB::query("CREATE TABLE IF NOT EXISTS `fabric_roll_spec_overrides` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `concern_id` INT NOT NULL,
            `source_color` VARCHAR(100) NOT NULL,
            `source_design_name` VARCHAR(150) NOT NULL,
            `color` VARCHAR(100) NOT NULL,
            `design_name` VARCHAR(150) NOT NULL,
            `hsn_code` VARCHAR(50) NOT NULL DEFAULT '',
            `qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `net_weight` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `unit` VARCHAR(50) NOT NULL DEFAULT 'Rolls',
            `last_unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `total_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_frso_source` (`concern_id`, `source_color`, `source_design_name`),
            INDEX `idx_frso_concern` (`concern_id`),
            CONSTRAINT `fk_frso_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * Synchronize and consolidate fabric_roll_specs from purchase_bill_items.
     * Consolidates duplicate color & design entries by accumulating their quantities and weights.
     */
    public static function syncSpecs(?int $concernId = null): void {
        self::initTable();

        $hasStockOverrides = (int)DB::fetchValue(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'purchase_stock_item_overrides'"
        ) > 0;
        $stockQty = $hasStockOverrides ? 'COALESCE(stock_override.qty, pbi.qty)' : 'pbi.qty';
        $stockUnit = $hasStockOverrides
            ? "COALESCE(NULLIF(TRIM(stock_override.unit), ''), NULLIF(TRIM(pbi.unit), ''), 'Rolls')"
            : "COALESCE(NULLIF(TRIM(pbi.unit), ''), 'Rolls')";
        $stockUnitPrice = $hasStockOverrides
            ? 'COALESCE(stock_override.unit_price, pbi.unit_price)'
            : 'pbi.unit_price';
        $overrideJoin = $hasStockOverrides
            ? "LEFT JOIN purchase_stock_item_overrides stock_override
                 ON stock_override.purchase_bill_item_id = pbi.id
                AND stock_override.concern_id = pbi.concern_id
                AND stock_override.section_type = 'rolls'"
            : '';

        $whereClause = "";
        $params = [];
        if ($concernId) {
            $whereClause = "AND pbi.concern_id = ?";
            $params[] = $concernId;
        }

        // Aggregate unique (concern_id, color, design_name) from purchase_bill_items
        // Deducting rolls allocated to production programs and rolls quarantined as damages
        $sql = "SELECT pbi.concern_id,
                       UPPER(TRIM(COALESCE(NULLIF(pbi.color, ''), 'STANDARD'))) AS color,
                       UPPER(TRIM(COALESCE(NULLIF(pbi.pattern, ''), pbi.item_name))) AS design_name,
                       SUM(GREATEST(0.00, {$stockQty} - COALESCE(prog.allocated_qty, 0.00) - COALESCE(dmg.damaged_qty, 0.00))) AS total_qty,
                       SUM(GREATEST(0.00, COALESCE(pbi.net_weight, 0.00) - COALESCE(prog.allocated_weight, 0.00) - COALESCE(dmg.damaged_weight, 0.00))) AS total_weight,
                       SUBSTRING_INDEX(GROUP_CONCAT({$stockUnit} ORDER BY pbi.id DESC), ',', 1) AS unit,
                       SUBSTRING_INDEX(GROUP_CONCAT({$stockUnitPrice} ORDER BY pbi.id DESC), ',', 1) AS last_price,
                       SUM(GREATEST(0.00, (GREATEST(0.00, {$stockQty} - COALESCE(prog.allocated_qty, 0.00) - COALESCE(dmg.damaged_qty, 0.00))) * {$stockUnitPrice})) AS total_val,
                       COUNT(DISTINCT pb.id) AS bills_count,
                       SUBSTRING_INDEX(GROUP_CONCAT(pb.bill_no ORDER BY pb.id DESC), ',', 1) AS last_bill,
                       SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(s.name, '') ORDER BY pb.id DESC), ',', 1) AS last_supplier,
                       SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(pbi.hsn_code, '') ORDER BY pbi.id DESC), ',', 1) AS last_hsn
                FROM purchase_bill_items pbi
                JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                LEFT JOIN suppliers s ON pb.supplier_id = s.id
                {$overrideJoin}
                LEFT JOIN (
                    SELECT pr.purchase_bill_item_id,
                           SUM(pr.allocated_qty) AS allocated_qty,
                           SUM(pr.roll_weight) AS allocated_weight
                    FROM program_rolls pr
                    JOIN production_programs pp ON pr.program_id = pp.id
                    WHERE pp.status != 'cancelled' AND pr.purchase_bill_item_id IS NOT NULL
                    GROUP BY pr.purchase_bill_item_id
                ) prog ON prog.purchase_bill_item_id = pbi.id
                LEFT JOIN (
                    SELECT purchase_bill_item_id,
                           SUM(qty) AS damaged_qty,
                           SUM(damaged_weight) AS damaged_weight
                    FROM purchase_damage_quarantine
                    WHERE item_type = 'rolls' AND purchase_bill_item_id IS NOT NULL
                    GROUP BY purchase_bill_item_id
                ) dmg ON dmg.purchase_bill_item_id = pbi.id
                WHERE pbi.section_type = 'rolls'
                  AND COALESCE(NULLIF(pbi.inward_status, ''), pb.inward_status, 'pending') = 'completed'
                  AND (TRIM(COALESCE(pbi.pattern, '')) != '' OR TRIM(COALESCE(pbi.item_name, '')) != '')
                  {$whereClause}
                GROUP BY pbi.concern_id, UPPER(TRIM(COALESCE(NULLIF(pbi.color, ''), 'STANDARD'))), UPPER(TRIM(COALESCE(NULLIF(pbi.pattern, ''), pbi.item_name)))";

        $rows = DB::fetchAll($sql, $params);

        // Fetch unlinked spec-level roll damage quarantine (where purchase_bill_item_id is null)
        $unlinkedDamageSql = "SELECT concern_id,
                                     UPPER(TRIM(roll_source_color)) AS color,
                                     UPPER(TRIM(roll_source_design_name)) AS design_name,
                                     SUM(qty) AS unlinked_qty,
                                     SUM(damaged_weight) AS unlinked_weight,
                                     SUM(estimated_loss) AS unlinked_loss
                              FROM purchase_damage_quarantine
                              WHERE item_type = 'rolls'
                                AND (purchase_bill_item_id IS NULL OR purchase_bill_item_id = 0)
                                AND roll_source_color IS NOT NULL AND TRIM(roll_source_color) != ''
                                AND roll_source_design_name IS NOT NULL AND TRIM(roll_source_design_name) != ''
                              GROUP BY concern_id, UPPER(TRIM(roll_source_color)), UPPER(TRIM(roll_source_design_name))";
        $unlinkedDamages = DB::fetchAll($unlinkedDamageSql);
        $unlinkedDmgMap = [];
        foreach ($unlinkedDamages as $ud) {
            $key = (int)$ud['concern_id'] . '|' . $ud['color'] . '|' . $ud['design_name'];
            $unlinkedDmgMap[$key] = $ud;
        }

        if ($concernId) {
            DB::query("DELETE FROM fabric_roll_specs WHERE concern_id = ?", [$concernId]);
        } else {
            DB::query("DELETE FROM fabric_roll_specs");
        }

        foreach ($rows as $r) {
            $c = trim($r['color']);
            $d = trim($r['design_name']);
            if (empty($c) || empty($d)) continue;

            $unit = ($r['unit'] === 'RQ' || empty($r['unit'])) ? 'Rolls' : $r['unit'];
            $tQty = (float)$r['total_qty'];
            $tWeight = (float)$r['total_weight'];
            $tVal = (float)$r['total_val'];
            $lastPrice = (float)$r['last_price'];

            $key = (int)$r['concern_id'] . '|' . $c . '|' . $d;
            if (isset($unlinkedDmgMap[$key])) {
                $ud = $unlinkedDmgMap[$key];
                $tQty = max(0.00, $tQty - (float)$ud['unlinked_qty']);
                $tWeight = max(0.00, $tWeight - (float)$ud['unlinked_weight']);
                $tVal = max(0.00, $tVal - (float)($ud['unlinked_loss'] ?: ($ud['unlinked_qty'] * $lastPrice)));
            }

            DB::query(
                "INSERT INTO fabric_roll_specs 
                 (concern_id, color, design_name, qty, net_weight, unit, last_unit_price, total_value, bills_count, last_bill_no, last_supplier_name, hsn_code, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE 
                    qty = VALUES(qty),
                    net_weight = VALUES(net_weight),
                    unit = VALUES(unit),
                    last_unit_price = VALUES(last_unit_price),
                    total_value = VALUES(total_value),
                    bills_count = VALUES(bills_count),
                    last_bill_no = VALUES(last_bill_no),
                    last_supplier_name = VALUES(last_supplier_name),
                    hsn_code = VALUES(hsn_code),
                    updated_at = NOW()",
                [
                    (int)$r['concern_id'],
                    $c,
                    $d,
                    $tQty,
                    $tWeight,
                    $unit,
                    $lastPrice,
                    $tVal,
                    (int)$r['bills_count'],
                    $r['last_bill'] ?: null,
                    $r['last_supplier'] ?: null,
                    $r['last_hsn'] ?: null,
                ]
            );
        }
    }

    /**
     * Record a newly entered roll item into the registry.
     * If the same Color & Design already exists for this concern,
     * the new quantity and weight will add to the previous count.
     */
    public static function recordRoll(
        int $concernId,
        string $color,
        string $designName,
        float $qty,
        float $netWeight,
        float $unitPrice,
        string $billNo = '',
        string $supplierName = '',
        string $hsnCode = '',
        string $unit = 'Rolls'
    ): array {
        self::initTable();

        $c = strtoupper(trim($color));
        $d = strtoupper(trim($designName));
        if (empty($c) || empty($d) || $qty <= 0) {
            return ['success' => false, 'message' => 'Invalid color, design or qty'];
        }

        if ($unit === 'RQ' || empty($unit)) {
            $unit = 'Rolls';
        }

        // Fetch existing record to calculate previous count
        $existing = DB::fetch(
            "SELECT * FROM fabric_roll_specs WHERE concern_id = ? AND color = ? AND design_name = ?",
            [$concernId, $c, $d]
        );

        $prevQty = $existing ? (float)$existing['qty'] : 0.0;
        $prevWeight = $existing ? (float)$existing['net_weight'] : 0.0;
        $prevValue = $existing ? (float)$existing['total_value'] : 0.0;
        $prevBills = $existing ? (int)$existing['bills_count'] : 0;

        $newQty = $prevQty + $qty;
        $newWeight = $prevWeight + $netWeight;
        $newValue = $prevValue + ($qty * $unitPrice);
        $newBills = $prevBills + 1;

        DB::query(
            "INSERT INTO fabric_roll_specs 
             (concern_id, color, design_name, qty, net_weight, unit, last_unit_price, total_value, bills_count, last_bill_no, last_supplier_name, hsn_code, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE 
                qty = qty + VALUES(qty),
                net_weight = net_weight + VALUES(net_weight),
                total_value = total_value + VALUES(total_value),
                last_unit_price = VALUES(last_unit_price),
                bills_count = bills_count + 1,
                last_bill_no = VALUES(last_bill_no),
                last_supplier_name = VALUES(last_supplier_name),
                hsn_code = COALESCE(VALUES(hsn_code), hsn_code),
                updated_at = NOW()",
            [
                $concernId,
                $c,
                $d,
                $qty,
                $netWeight,
                $unit,
                $unitPrice,
                ($qty * $unitPrice),
                1,
                $billNo ?: null,
                $supplierName ?: null,
                $hsnCode ?: null,
            ]
        );

        return [
            'success'       => true,
            'color'         => $c,
            'design_name'   => $d,
            'previous_qty'  => $prevQty,
            'added_qty'     => $qty,
            'new_total_qty' => $newQty,
            'new_weight'    => $newWeight,
        ];
    }

    /**
     * Get all saved unique roll specifications for quick selection and auto-fill in Purchase Billing Create
     */
    public static function getSavedSpecs(int $concernId): array {
        self::initTable();
        return DB::fetchAll(
            "SELECT id, color, design_name, qty, net_weight, unit, last_unit_price, hsn_code, bills_count, last_bill_no, last_supplier_name 
             FROM fabric_roll_specs 
             WHERE concern_id = ? 
             ORDER BY design_name ASC, color ASC",
            [$concernId]
        );
    }

    /**
     * Save an edit to a consolidated row without changing its purchase-bill source items.
     */
    public static function saveConsolidatedOverride(
        int $concernId,
        string $sourceColor,
        string $sourceDesignName,
        array $values
    ): ?array {
        self::initTable();

        $source = DB::fetch(
            "SELECT * FROM fabric_roll_specs WHERE concern_id = ? AND color = ? AND design_name = ?",
            [$concernId, $sourceColor, $sourceDesignName]
        );
        if (!$source) {
            return null;
        }

        $collisionCount = (int)DB::fetchValue(
            "SELECT COUNT(*)
             FROM fabric_roll_specs frs
             LEFT JOIN fabric_roll_spec_overrides fro
               ON fro.concern_id = frs.concern_id
              AND fro.source_color = frs.color
              AND fro.source_design_name = frs.design_name
             WHERE frs.concern_id = ?
               AND COALESCE(fro.color, frs.color) = ?
               AND COALESCE(fro.design_name, frs.design_name) = ?
               AND NOT (frs.color = ? AND frs.design_name = ?)",
            [
                $concernId,
                $values['color'],
                $values['design_name'],
                $sourceColor,
                $sourceDesignName,
            ]
        );
        if ($collisionCount > 0) {
            return null;
        }

        $existing = DB::fetch(
            "SELECT * FROM fabric_roll_spec_overrides
             WHERE concern_id = ? AND source_color = ? AND source_design_name = ?",
            [$concernId, $sourceColor, $sourceDesignName]
        );
        $before = $existing ?: [
            'color' => $source['color'],
            'design_name' => $source['design_name'],
            'hsn_code' => $source['hsn_code'] ?? '',
            'qty' => $source['qty'],
            'net_weight' => $source['net_weight'],
            'unit' => $source['unit'],
            'last_unit_price' => $source['last_unit_price'],
            'total_value' => $source['total_value'],
        ];

        DB::query(
            "INSERT INTO fabric_roll_spec_overrides
             (concern_id, source_color, source_design_name, color, design_name, hsn_code, qty, net_weight, unit, last_unit_price, total_value)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                color = VALUES(color),
                design_name = VALUES(design_name),
                hsn_code = VALUES(hsn_code),
                qty = VALUES(qty),
                net_weight = VALUES(net_weight),
                unit = VALUES(unit),
                last_unit_price = VALUES(last_unit_price),
                total_value = VALUES(total_value),
                updated_at = NOW()",
            [
                $concernId,
                $sourceColor,
                $sourceDesignName,
                $values['color'],
                $values['design_name'],
                $values['hsn_code'],
                $values['qty'],
                $values['net_weight'],
                $values['unit'],
                $values['last_unit_price'],
                $values['total_value'],
            ]
        );

        $saved = DB::fetch(
            "SELECT * FROM fabric_roll_spec_overrides
             WHERE concern_id = ? AND source_color = ? AND source_design_name = ?",
            [$concernId, $sourceColor, $sourceDesignName]
        );
        if (!$saved) {
            return null;
        }

        return [
            'id' => (int)$saved['id'],
            'before' => $before,
            'after' => $values,
        ];
    }

    /**
     * Create or update a roll design specification on the fly.
     */
    public static function createOrUpdateSpec(
        int $concernId,
        string $color,
        string $designName,
        float $unitPrice = 0.0,
        string $hsnCode = '',
        float $initialQty = 0.0,
        float $netWeight = 0.0,
        string $supplierName = ''
    ): array {
        self::initTable();

        $c = strtoupper(trim($color));
        $d = strtoupper(trim($designName));
        if (empty($c) || empty($d)) {
            return ['success' => false, 'message' => 'Both Color / Shade and Design Name / Pattern are required.'];
        }

        $existing = DB::fetch(
            "SELECT * FROM fabric_roll_specs WHERE concern_id = ? AND color = ? AND design_name = ?",
            [$concernId, $c, $d]
        );

        if ($existing) {
            $updates = ["updated_at = NOW()"];
            $params = [];
            if ($unitPrice > 0) {
                $updates[] = "last_unit_price = ?";
                $params[] = $unitPrice;
            }
            if (!empty($hsnCode)) {
                $updates[] = "hsn_code = ?";
                $params[] = $hsnCode;
            }
            if ($initialQty > 0) {
                $updates[] = "qty = qty + ?";
                $params[] = $initialQty;
            }
            if ($netWeight > 0) {
                $updates[] = "net_weight = net_weight + ?";
                $params[] = $netWeight;
            }
            if (!empty($supplierName)) {
                $updates[] = "last_supplier_name = ?";
                $params[] = $supplierName;
            }
            $params[] = $existing['id'];
            DB::query("UPDATE fabric_roll_specs SET " . implode(', ', $updates) . " WHERE id = ?", $params);
            $record = DB::fetch("SELECT * FROM fabric_roll_specs WHERE id = ?", [$existing['id']]);
            return ['success' => true, 'action' => 'updated', 'spec' => $record];
        } else {
            DB::query(
                "INSERT INTO fabric_roll_specs 
                 (concern_id, color, design_name, qty, net_weight, unit, last_unit_price, total_value, bills_count, last_supplier_name, hsn_code, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 'Rolls', ?, ?, ?, ?, ?, NOW(), NOW())",
                [
                    $concernId,
                    $c,
                    $d,
                    $initialQty,
                    $netWeight,
                    $unitPrice,
                    ($initialQty * $unitPrice),
                    $initialQty > 0 ? 1 : 0,
                    $supplierName ?: null,
                    $hsnCode ?: null,
                ]
            );
            $newId = (int)DB::lastInsertId();
            $record = DB::fetch("SELECT * FROM fabric_roll_specs WHERE id = ?", [$newId]);
            return ['success' => true, 'action' => 'created', 'spec' => $record];
        }
    }

    /**
     * Get list of unique colors for autocomplete
     */
    public static function getSavedColors(int $concernId): array {
        self::initTable();
        return DB::fetchAll(
            "SELECT DISTINCT color FROM (
                SELECT color FROM fabric_roll_specs WHERE concern_id = ?
                UNION
                SELECT UPPER(TRIM(color)) as color FROM purchase_bill_items WHERE concern_id = ? AND color IS NOT NULL AND TRIM(color) != ''
            ) t ORDER BY color ASC",
            [$concernId, $concernId]
        );
    }

    /**
     * Get list of unique designs / patterns for autocomplete
     */
    public static function getSavedDesigns(int $concernId): array {
        self::initTable();
        return DB::fetchAll(
            "SELECT DISTINCT design_name FROM (
                SELECT design_name FROM fabric_roll_specs WHERE concern_id = ?
                UNION
                SELECT UPPER(TRIM(pattern)) as design_name FROM purchase_bill_items WHERE concern_id = ? AND pattern IS NOT NULL AND TRIM(pattern) != ''
            ) t ORDER BY design_name ASC",
            [$concernId, $concernId]
        );
    }

    /**
     * Fetch consolidated roll stock records for Purchase Stocks module
     */
    public static function getConsolidatedStock(?int $concernId = null, string $search = ''): array {
        self::initTable();

        $where = [];
        $params = [];
        if ($concernId) {
            $where[] = "frs.concern_id = ?";
            $params[] = $concernId;
        }

        if (!empty($search)) {
            $like = "%{$search}%";
            $where[] = "(COALESCE(fro.color, frs.color) LIKE ? OR COALESCE(fro.design_name, frs.design_name) LIKE ? OR frs.last_bill_no LIKE ? OR frs.last_supplier_name LIKE ? OR COALESCE(fro.hsn_code, frs.hsn_code) LIKE ?)";
            $params = array_merge($params, [$like, $like, $like, $like, $like]);
        }

        $whereClause = !empty($where) ? ("WHERE " . implode(' AND ', $where)) : "";

        $rows = DB::fetchAll(
            "SELECT frs.*, c.name AS concern_name, c.code AS concern_code,
                    fro.id AS override_id,
                    fro.color AS override_color,
                    fro.design_name AS override_design_name,
                    fro.hsn_code AS override_hsn_code,
                    fro.qty AS override_qty,
                    fro.net_weight AS override_net_weight,
                    fro.unit AS override_unit,
                    fro.last_unit_price AS override_last_unit_price,
                    fro.total_value AS override_total_value,
                    latest_item.id AS last_item_id,
                    latest_item.item_name AS last_item_name,
                    latest_item.item_code AS last_item_code,
                    latest_item.color AS last_item_color,
                    latest_item.pattern AS last_item_pattern,
                    latest_item.specification AS last_item_specification,
                    latest_item.unit_price AS last_item_unit_price,
                    latest_item.mrp AS last_item_mrp,
                    latest_item.purchase_bill_id AS last_item_bill_id
             FROM fabric_roll_specs frs
             LEFT JOIN concerns c ON frs.concern_id = c.id
             LEFT JOIN fabric_roll_spec_overrides fro
               ON fro.concern_id = frs.concern_id
              AND fro.source_color = frs.color
              AND fro.source_design_name = frs.design_name
             LEFT JOIN purchase_bill_items latest_item ON latest_item.id = (
                 SELECT pbi.id
                 FROM purchase_bill_items pbi
                 JOIN purchase_bills pb ON pb.id = pbi.purchase_bill_id
                 WHERE pbi.concern_id = frs.concern_id
                   AND pbi.section_type = 'rolls'
                   AND COALESCE(NULLIF(pbi.inward_status, ''), pb.inward_status, 'pending') = 'completed'
                   AND UPPER(TRIM(COALESCE(NULLIF(pbi.color, ''), 'STANDARD'))) = frs.color
                   AND UPPER(TRIM(COALESCE(NULLIF(pbi.pattern, ''), pbi.item_name))) = frs.design_name
                 ORDER BY pbi.id DESC
                 LIMIT 1
             )
             {$whereClause}
             ORDER BY COALESCE(fro.qty, frs.qty) DESC,
                      COALESCE(fro.design_name, frs.design_name) ASC,
                      COALESCE(fro.color, frs.color) ASC",
            $params
        );

        foreach ($rows as &$row) {
            $row['source_color'] = $row['color'];
            $row['source_design_name'] = $row['design_name'];
            if ($row['override_id'] !== null) {
                $row['color'] = $row['override_color'];
                $row['design_name'] = $row['override_design_name'];
                $row['hsn_code'] = $row['override_hsn_code'];
                $row['qty'] = $row['override_qty'];
                $row['net_weight'] = $row['override_net_weight'];
                $row['unit'] = $row['override_unit'];
                $row['last_unit_price'] = $row['override_last_unit_price'];
                $row['total_value'] = $row['override_total_value'];
            }
        }
        unset($row);

        return $rows;
    }
}
