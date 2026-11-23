<?php
// app/services/QrService.php - QR Code generator and parser

namespace App\Services;

use App\Core\DB;

class QrService {
    private const FINISHED_GOODS_CODE_PREFIX = 'GRM';

    /**
     * Build simplified tag code:
     * First 3 letters of product - first letter of color - 2 letters of design
     * E.g. FAB-R-FL, THR-W-SP, COT-B-ST
     */
    public static function generateTagCode(?string $product, ?string $color = '', ?string $design = '', string $fallbackId = ''): string {
        // 1. First 3 letters of product
        $p = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$product));
        if (strlen($p) < 3) {
            $p = str_pad($p, 3, 'X');
        }
        $prod3 = substr($p, 0, 3);

        // 2. First letter of color
        $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$color));
        $col1 = (strlen($c) > 0) ? substr($c, 0, 1) : 'X';

        // 3. 2 letters of design / pattern
        $d = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$design));
        if (strlen($d) === 0) {
            $cleanId = preg_replace('/[^A-Za-z0-9]/', '', (string)$fallbackId);
            $d = strlen($cleanId) >= 2 ? substr($cleanId, 0, 2) : (strlen($cleanId) === 1 ? '0' . $cleanId : '01');
        } elseif (strlen($d) === 1) {
            $d = '0' . $d;
        } else {
            $d = substr($d, 0, 2);
        }
        $des2 = $d;

        return "{$prod3}-{$col1}-{$des2}";
    }

    /**
     * Build standard payload: C{cat}-S{subcat}-V{var}
     */
    public static function generatePayload(int $categoryId, int $subcategoryId, int $variantId): string {
        return "C{$categoryId}-S{$subcategoryId}-V{$variantId}";
    }

    /**
     * Parse standard payload: C4-S12-V1087
     */
    public static function parsePayload(string $code): ?array {
        $code = trim($code);
        if (preg_match('/^C(\d+)-S(\d+)-V(\d+)$/i', $code, $matches)) {
            return [
                'category_id'    => (int)$matches[1],
                'subcategory_id' => (int)$matches[2],
                'variant_id'     => (int)$matches[3],
                'raw'            => $code,
            ];
        }
        return null;
    }

    public static function parseVariantId(string $code): ?int {
        $code = trim($code);
        if (!preg_match('/^\d+$/', $code)) {
            return null;
        }

        $variantId = filter_var($code, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $variantId === false ? null : $variantId;
    }

    public static function parseFinishedGoodsCode(string $code): ?array {
        $code = strtoupper(trim($code));
        if (!preg_match('/^GRM[A-Z0-9]{6}(\d{2,})$/', $code, $matches)) {
            return null;
        }

        $idDigits = ltrim($matches[1], '0');
        if ($idDigits === '') {
            return null;
        }

        $variantId = filter_var($idDigits, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($variantId === false) {
            return null;
        }

        return [
            'variant_id' => $variantId,
            'raw' => $code,
        ];
    }

    /** Build the GRM hierarchy code with a zero-padded minimum three-digit variant ID. */
    public static function formatFinishedGoodsCode(
        int $variantId,
        ?string $categoryCode,
        ?string $subcategoryCode,
        ?string $subSubcategoryCode,
        ?string $categoryName = null,
        ?string $subcategoryName = null,
        ?string $subSubcategoryName = null
    ): string {
        if ($variantId <= 0) {
            return '';
        }

        $categoryKey = self::hierarchyKey($categoryCode, $categoryName);
        $subcategoryKey = self::hierarchyKey($subcategoryCode, $subcategoryName);
        $subSubcategoryKey = self::hierarchyKey($subSubcategoryCode, $subSubcategoryName);

        // Format: Brand(GRM) + Category(2) + Subcategory(2) + SubSubcategory(2) + VariantID(3-digit padded e.g. 001)
        return self::FINISHED_GOODS_CODE_PREFIX
            . $categoryKey
            . $subcategoryKey
            . $subSubcategoryKey
            . str_pad((string)$variantId, 3, '0', STR_PAD_LEFT);
    }

    public static function generateFinishedGoodsCode(int $variantId): ?string {
        if ($variantId <= 0) {
            return null;
        }

        $variant = DB::fetch(
            "SELECT v.id,
                    COALESCE(NULLIF(cat.main_category_code, ''), c.code) AS category_code,
                    COALESCE(NULLIF(cat.sub_category_code, ''), sc.code) AS subcategory_code,
                    COALESCE(NULLIF(cat.sub_sub_category_code, ''), ssc.code) AS sub_subcategory_code,
                    c.name AS category_name,
                    sc.name AS subcategory_name,
                    ssc.name AS sub_subcategory_name
             FROM variants v
             LEFT JOIN catalogs cat ON cat.id = v.catalog_id
             LEFT JOIN categories c ON c.id = v.category_id
             LEFT JOIN subcategories sc ON sc.id = v.subcategory_id
             LEFT JOIN sub_subcategories ssc ON ssc.id = cat.sub_sub_category_id
             WHERE v.id = ?
             LIMIT 1",
            [$variantId]
        );

        if (!$variant) {
            return null;
        }

        return self::formatFinishedGoodsCode(
            (int)$variant['id'],
            $variant['category_code'] ?? null,
            $variant['subcategory_code'] ?? null,
            $variant['sub_subcategory_code'] ?? null,
            $variant['category_name'] ?? null,
            $variant['subcategory_name'] ?? null,
            $variant['sub_subcategory_name'] ?? null
        );
    }

    /**
     * Resolve QR scan strictly within session concern boundary
     */
    public static function lookupVariant(string $code, int $concernId): ?array {
        $finishedGoodsCode = self::parseFinishedGoodsCode($code);
        $variantId = self::parseVariantId($code);
        $variantId = $variantId ?? ($finishedGoodsCode['variant_id'] ?? null);
        $parsed = self::parsePayload($code);
        if (!$parsed && !$variantId) {
            return null;
        }

        if ($variantId) {
            $variantFilter = 'v.id = ? AND v.concern_id = ?';
            $variantParams = [$variantId, $concernId];
        } else {
            $variantFilter = 'v.id = ? AND v.category_id = ? AND v.subcategory_id = ? AND v.concern_id = ?';
            $variantParams = [
                $parsed['variant_id'],
                $parsed['category_id'],
                $parsed['subcategory_id'],
                $concernId,
            ];
        }

        $sql = "SELECT v.*, 
                       c.name AS category_name, 
                       c.code AS category_code,
                       s.name AS subcategory_name, 
                       s.code AS subcategory_code,
                       COALESCE(NULLIF(cat.main_category_code, ''), c.code) AS main_category_code,
                       COALESCE(NULLIF(cat.sub_category_code, ''), s.code) AS hierarchy_subcategory_code,
                       COALESCE(NULLIF(cat.sub_sub_category_code, ''), ssc.code) AS hierarchy_sub_sub_category_code,
                       ssc.name AS sub_subcategory_name,
                       b.name AS brand_name,
                       COALESCE(stk.total_qty, 0) AS current_stock
                FROM variants v
                LEFT JOIN categories c ON v.category_id = c.id
                LEFT JOIN subcategories s ON v.subcategory_id = s.id
                LEFT JOIN catalogs cat ON cat.id = v.catalog_id
                LEFT JOIN sub_subcategories ssc ON ssc.id = cat.sub_sub_category_id
                LEFT JOIN brands b ON v.brand_id = b.id
                LEFT JOIN (
                    SELECT variant_id, SUM(qty) AS total_qty 
                    FROM stocks 
                    WHERE concern_id = ? 
                    GROUP BY variant_id
                ) stk ON v.id = stk.variant_id
                WHERE {$variantFilter}
                LIMIT 1";

        $res = DB::fetch($sql, array_merge([$concernId], $variantParams));

        if ($res && !empty($res['attributes'])) {
            $res['attributes_parsed'] = json_decode($res['attributes'], true) ?: [];
        }

        if ($res && $finishedGoodsCode) {
            $expectedCode = self::formatFinishedGoodsCode(
                (int)$res['id'],
                $res['main_category_code'] ?? $res['category_code'] ?? null,
                $res['hierarchy_subcategory_code'] ?? $res['subcategory_code'] ?? null,
                $res['hierarchy_sub_sub_category_code'] ?? null,
                $res['category_name'] ?? null,
                $res['subcategory_name'] ?? null,
                $res['sub_subcategory_name'] ?? null
            );
            if (!hash_equals($expectedCode, strtoupper(trim($code)))) {
                return null;
            }
        }

        return $res ?: null;
    }

    private static function hierarchyKey(?string $code, ?string $name): string {
        foreach ([$code, $name] as $candidate) {
            $key = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$candidate));
            if ($key !== '') {
                return str_pad(substr($key, 0, 2), 2, 'X');
            }
        }

        return 'XX';
    }

    /**
     * Generates a clean vector data-uri or SVG QR using pure PHP or frontend script fallback
     */
    public static function getQrImageUrl(string $data, int $size = 150): string {
        // Generates an inline SVG or URL for standard barcode/qr rendering
        $encoded = urlencode($data);
        return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded}";
    }
}
