<?php
// app/core/ModuleRegistry.php

namespace App\Core;

class ModuleRegistry
{
    public static function masterModules(): array
    {
        return [
            ['code' => 'admin_dashboard', 'name' => 'Dashboard', 'icon' => 'bi-speedometer2', 'sort_order' => 1, 'group_name' => 'Super Admin', 'is_super_admin_only' => 1],
            ['code' => 'admin_concerns', 'name' => 'Concern Management', 'icon' => 'bi-buildings', 'sort_order' => 2, 'group_name' => 'Super Admin', 'is_super_admin_only' => 1],
            ['code' => 'admin_assets', 'name' => 'Asset Management', 'icon' => 'bi-building-gear', 'sort_order' => 3, 'group_name' => 'Super Admin', 'is_super_admin_only' => 0],
            ['code' => 'admin_reports', 'name' => 'Consolidated Reports', 'icon' => 'bi-bar-chart-line', 'sort_order' => 4, 'group_name' => 'Super Admin', 'is_super_admin_only' => 0],

            ['code' => 'concern_dashboard', 'name' => 'Concern Dashboard', 'icon' => 'bi-speedometer', 'sort_order' => 10, 'group_name' => 'Dashboard', 'is_super_admin_only' => 0],
            ['code' => 'suppliers', 'name' => 'Supplier', 'icon' => 'bi-truck', 'sort_order' => 20, 'group_name' => 'Purchase', 'is_super_admin_only' => 0],
            ['code' => 'purchase_billing', 'name' => 'Purchase Billing', 'icon' => 'bi-receipt', 'sort_order' => 21, 'group_name' => 'Purchase', 'is_super_admin_only' => 0],
            ['code' => 'purchase_stocks', 'name' => 'Purchase Stocks', 'icon' => 'bi-boxes', 'sort_order' => 22, 'group_name' => 'Purchase', 'is_super_admin_only' => 0],
            ['code' => 'purchase_damage', 'name' => 'Purchase Damage', 'icon' => 'bi-shield-x', 'sort_order' => 23, 'group_name' => 'Purchase', 'is_super_admin_only' => 0],
            ['code' => 'catalog_mapping', 'name' => 'Catalog Mapping', 'icon' => 'bi-diagram-3-fill', 'sort_order' => 24, 'group_name' => 'Purchase', 'is_super_admin_only' => 0],
            ['code' => 'product_adding', 'name' => 'Product Adding', 'icon' => 'bi-plus-square-dotted', 'sort_order' => 25, 'group_name' => 'Purchase', 'is_super_admin_only' => 0],
            ['code' => 'catalog_program', 'name' => 'Catalog & Program', 'icon' => 'bi-journal-bookmark', 'sort_order' => 30, 'group_name' => 'Production', 'is_super_admin_only' => 0],
            ['code' => 'cutting_wastage', 'name' => 'Cutting & Wastage', 'icon' => 'bi-scissors', 'sort_order' => 31, 'group_name' => 'Production', 'is_super_admin_only' => 0],
            ['code' => 'stitching_wastage', 'name' => 'Stitching & Wastage', 'icon' => 'bi-layers', 'sort_order' => 32, 'group_name' => 'Production', 'is_super_admin_only' => 0],
            ['code' => 'label_generation', 'name' => 'Label Generation', 'icon' => 'bi-qr-code', 'sort_order' => 33, 'group_name' => 'Production', 'is_super_admin_only' => 0],
            ['code' => 'qc_stocks', 'name' => 'QC Stocks', 'icon' => 'bi-shield-check', 'sort_order' => 34, 'group_name' => 'Production', 'is_super_admin_only' => 0],
            ['code' => 'inward', 'name' => 'Inward', 'icon' => 'bi-box-arrow-in-down', 'sort_order' => 40, 'group_name' => 'Stock', 'is_super_admin_only' => 0],
            ['code' => 'stocks', 'name' => 'Stocks', 'icon' => 'bi-boxes', 'sort_order' => 41, 'group_name' => 'Stock', 'is_super_admin_only' => 0],
            ['code' => 'concern_damage', 'name' => 'Concern Damage', 'icon' => 'bi-x-octagon', 'sort_order' => 42, 'group_name' => 'Stock', 'is_super_admin_only' => 0],
            ['code' => 'return_exchange', 'name' => 'Return & Exchange', 'icon' => 'bi-arrow-left-right', 'sort_order' => 45, 'group_name' => 'Stock', 'is_super_admin_only' => 0],
            ['code' => 'customers', 'name' => 'Customer Details', 'icon' => 'bi-people', 'sort_order' => 50, 'group_name' => 'Shop', 'is_super_admin_only' => 0],
            ['code' => 'billing', 'name' => 'POS Billing', 'icon' => 'bi-calculator', 'sort_order' => 51, 'group_name' => 'Shop', 'is_super_admin_only' => 0],
            ['code' => 'request', 'name' => 'Request', 'icon' => 'bi-file-earmark-text', 'sort_order' => 52, 'group_name' => 'Sales', 'is_super_admin_only' => 0],
            ['code' => 'batch', 'name' => 'Batch', 'icon' => 'bi-collection', 'sort_order' => 53, 'group_name' => 'Sales', 'is_super_admin_only' => 0],
            ['code' => 'outward', 'name' => 'Outward', 'icon' => 'bi-box-arrow-up-right', 'sort_order' => 54, 'group_name' => 'Sales', 'is_super_admin_only' => 0],
            ['code' => 'expense', 'name' => 'Expense', 'icon' => 'bi-wallet2', 'sort_order' => 60, 'group_name' => 'Finance', 'is_super_admin_only' => 0],
            ['code' => 'cash', 'name' => 'Cash', 'icon' => 'bi-cash-coin', 'sort_order' => 61, 'group_name' => 'Finance', 'is_super_admin_only' => 0],
            ['code' => 'cash_flow', 'name' => 'Cash & Cash Flow', 'icon' => 'bi-cash-coin', 'sort_order' => 62, 'group_name' => 'Finance', 'is_super_admin_only' => 0],
            ['code' => 'staff', 'name' => 'Staff', 'icon' => 'bi-people-fill', 'sort_order' => 90, 'group_name' => 'Staff', 'is_super_admin_only' => 0],
        ];
    }

    public static function concernCodes(): array
    {
        return array_values(array_map(
            static fn(array $module): string => (string)$module['code'],
            array_filter(
                self::masterModules(),
                static fn(array $module): bool => (int)$module['is_super_admin_only'] === 0
            )
        ));
    }

    public static function merge(array $databaseModules, bool $includeSuperAdmin = false): array
    {
        $merged = [];
        foreach ($databaseModules as $module) {
            $merged[(string)$module['code']] = $module;
        }

        foreach (self::masterModules() as $module) {
            if (!$includeSuperAdmin && (int)$module['is_super_admin_only'] === 1) {
                continue;
            }
            if (!isset($merged[$module['code']])) {
                $merged[$module['code']] = $module;
            }
        }

        uasort($merged, static function (array $a, array $b): int {
            $adminDiff = ((int)($b['is_super_admin_only'] ?? 0)) - ((int)($a['is_super_admin_only'] ?? 0));
            if ($adminDiff !== 0) {
                return $adminDiff;
            }
            return ((int)($a['sort_order'] ?? 0)) - ((int)($b['sort_order'] ?? 0));
        });

        return array_values($merged);
    }
}
