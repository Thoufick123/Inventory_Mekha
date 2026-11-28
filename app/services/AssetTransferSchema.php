<?php

namespace App\Services;

use App\Core\DB;

final class AssetTransferSchema {
    private static bool $ensured = false;

    public static function ensure(): void {
        if (self::$ensured) {
            return;
        }

        DB::query("
            CREATE TABLE IF NOT EXISTS `outward_asset_items` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `concern_id` INT NOT NULL,
                `outward_id` INT NOT NULL,
                `asset_id` INT NOT NULL,
                `asset_source_concern_id` INT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_oai_outward_asset` (`outward_id`, `asset_id`),
                KEY `idx_oai_asset` (`asset_id`),
                KEY `idx_oai_concern` (`concern_id`),
                CONSTRAINT `fk_oai_outward` FOREIGN KEY (`outward_id`) REFERENCES `outwards` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_oai_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        DB::query("
            CREATE TABLE IF NOT EXISTS `inward_asset_items` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `concern_id` INT NOT NULL,
                `inward_id` INT NOT NULL,
                `outward_asset_item_id` INT NOT NULL,
                `asset_id` INT NOT NULL,
                `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_iai_outward_asset_item` (`outward_asset_item_id`),
                KEY `idx_iai_asset` (`asset_id`),
                KEY `idx_iai_concern` (`concern_id`),
                CONSTRAINT `fk_iai_inward` FOREIGN KEY (`inward_id`) REFERENCES `inwards` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_iai_outward_asset_item` FOREIGN KEY (`outward_asset_item_id`) REFERENCES `outward_asset_items` (`id`),
                CONSTRAINT `fk_iai_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::$ensured = true;
    }
}
