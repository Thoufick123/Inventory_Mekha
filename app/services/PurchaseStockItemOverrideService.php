<?php
namespace App\Services;

use App\Core\DB;

class PurchaseStockItemOverrideService {
    private static bool $tableChecked = false;

    public static function initTable(): void {
        if (self::$tableChecked || DB::pdo()->inTransaction()) {
            return;
        }

        self::$tableChecked = true;
        DB::query("CREATE TABLE IF NOT EXISTS `purchase_stock_item_overrides` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `concern_id` INT NOT NULL,
            `purchase_bill_item_id` INT NOT NULL,
            `section_type` VARCHAR(30) NOT NULL,
            `item_name` VARCHAR(255) NOT NULL DEFAULT '',
            `item_code` VARCHAR(100) NOT NULL DEFAULT '',
            `specification` VARCHAR(255) NOT NULL DEFAULT '',
            `color` VARCHAR(100) NOT NULL DEFAULT '',
            `unit` VARCHAR(50) NOT NULL DEFAULT '',
            `qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `mrp` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `total_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `label_job_baseline_id` INT NOT NULL DEFAULT 0,
            `updated_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_psio_item` (`concern_id`, `purchase_bill_item_id`),
            INDEX `idx_psio_section` (`concern_id`, `section_type`),
            CONSTRAINT `fk_psio_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_psio_item` FOREIGN KEY (`purchase_bill_item_id`) REFERENCES `purchase_bill_items`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function applyToItems(array $items, bool $initializeTable = true): array {
        if (!$items) {
            return $items;
        }

        if ($initializeTable) {
            self::initTable();
        } elseif (!(int)DB::fetchValue(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'purchase_stock_item_overrides'"
        )) {
            return $items;
        }
        $ids = array_values(array_unique(array_map(
            static fn(array $item): int => (int)$item['id'],
            $items
        )));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $overrides = DB::fetchAll(
            "SELECT * FROM purchase_stock_item_overrides WHERE purchase_bill_item_id IN ({$placeholders})",
            $ids
        );
        $byItemId = [];
        foreach ($overrides as $override) {
            $byItemId[(int)$override['purchase_bill_item_id']] = $override;
        }

        foreach ($items as &$item) {
            $override = $byItemId[(int)$item['id']] ?? null;
            if (!$override || $override['section_type'] !== $item['section_type']) {
                continue;
            }

            foreach (['item_name', 'item_code', 'specification', 'color', 'unit', 'qty', 'unit_price', 'mrp'] as $field) {
                $item[$field] = $override[$field];
            }
            $item['total_price'] = $override['total_value'];
            $item['label_job_baseline_id'] = (int)$override['label_job_baseline_id'];
        }
        unset($item);

        return $items;
    }

    public static function saveItemOverride(
        int $concernId,
        int $itemId,
        string $sectionType,
        array $values,
        ?int $userId
    ): ?array {
        self::initTable();
        $before = DB::fetch(
            "SELECT * FROM purchase_stock_item_overrides
             WHERE concern_id = ? AND purchase_bill_item_id = ?",
            [$concernId, $itemId]
        );
        $labelJobBaselineId = (int)DB::fetchValue(
            "SELECT COALESCE(MAX(id), 0)
             FROM label_jobs
             WHERE concern_id = ? AND ref_id = ? AND label_type = 'purchase_fg'",
            [$concernId, $itemId]
        );

        DB::query(
            "INSERT INTO purchase_stock_item_overrides
                (concern_id, purchase_bill_item_id, section_type, item_name, item_code, specification, color, unit,
                 qty, unit_price, mrp, total_value, label_job_baseline_id, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                section_type = VALUES(section_type),
                item_name = VALUES(item_name),
                item_code = VALUES(item_code),
                specification = VALUES(specification),
                color = VALUES(color),
                unit = VALUES(unit),
                qty = VALUES(qty),
                unit_price = VALUES(unit_price),
                mrp = VALUES(mrp),
                total_value = VALUES(total_value),
                label_job_baseline_id = VALUES(label_job_baseline_id),
                updated_by = VALUES(updated_by)",
            [
                $concernId,
                $itemId,
                $sectionType,
                $values['item_name'],
                $values['item_code'],
                $values['specification'],
                $values['color'],
                $values['unit'],
                $values['qty'],
                $values['unit_price'],
                $values['mrp'],
                $values['total_value'],
                $labelJobBaselineId,
                $userId
            ]
        );

        $after = DB::fetch(
            "SELECT * FROM purchase_stock_item_overrides
             WHERE concern_id = ? AND purchase_bill_item_id = ?",
            [$concernId, $itemId]
        );
        if (!$after) {
            return null;
        }

        return [
            'id' => (int)$after['id'],
            'before' => $before,
            'after' => $after,
        ];
    }
}
