<?php
// app/services/VariantService.php

namespace App\Services;

use App\Core\DB;

class VariantService
{
    /**
     * Find an existing active variant in the same concern whose details match identically,
     * or create a new variant with product_code equal to its variant ID.
     *
     * Matching attributes: name, category_id, subcategory_id, sub_sub_category_id,
     * size, color, custom_attributes, hsn_code, weight, cost_price, price, mrp.
     */
    public static function findOrCreate(int $concernId, array $data): array
    {
        $name = trim((string)($data['name'] ?? 'Product'));
        $catalogId = !empty($data['catalog_id']) ? (int)$data['catalog_id'] : null;
        $categoryId = (int)($data['category_id'] ?? 0);
        $subcategoryId = !empty($data['subcategory_id']) ? (int)$data['subcategory_id'] : null;
        $subSubcategoryId = !empty($data['sub_sub_category_id']) ? (int)$data['sub_sub_category_id'] : null;
        $size = trim((string)($data['size'] ?? '')) ?: 'Free Size';
        $color = trim((string)($data['color'] ?? '')) ?: 'Standard';
        $hsnCode = strtoupper(trim((string)($data['hsn_code'] ?? '')));
        $weight = round((float)($data['weight'] ?? 0), 3);
        $weightUnit = trim((string)($data['weight_unit'] ?? 'kg')) ?: 'kg';
        $costPrice = round((float)($data['cost_price'] ?? 0), 2);
        $price = round((float)($data['price'] ?? 0), 2);
        $mrp = round((float)($data['mrp'] ?? 0), 2);

        $customAttrs = self::normalizeCustomAttributes($data['custom_attributes'] ?? []);

        // Search existing active variants in the same concern with matching core fields
        $candidates = DB::fetchAll(
            "SELECT * FROM variants
             WHERE concern_id = ?
               AND status = 'active'
               AND LOWER(TRIM(name)) = LOWER(TRIM(?))
               AND category_id = ?
               AND COALESCE(subcategory_id, 0) = ?
               AND COALESCE(sub_sub_category_id, 0) = ?
               AND LOWER(TRIM(COALESCE(size, ''))) = LOWER(TRIM(?))
               AND ROUND(price, 2) = ?
               AND ROUND(cost_price, 2) = ?
               AND ROUND(mrp, 2) = ?
             ORDER BY id ASC",
            [
                $concernId,
                $name,
                $categoryId,
                $subcategoryId ?? 0,
                $subSubcategoryId ?? 0,
                $size,
                $price,
                $costPrice,
                $mrp,
            ]
        );

        foreach ($candidates as $cand) {
            $candAttrs = json_decode((string)($cand['attributes'] ?? ''), true);
            $candAttrs = is_array($candAttrs) ? $candAttrs : [];

            $candColor = trim((string)($candAttrs['color'] ?? 'Standard'));
            $candHsn = strtoupper(trim((string)($candAttrs['hsn'] ?? '')));
            $candWeight = round((float)($cand['weight'] ?? ($candAttrs['weight'] ?? 0)), 3);
            $candCustom = self::extractCustomAttributes($candAttrs);

            if (
                strcasecmp($candColor, $color) === 0
                && $candHsn === $hsnCode
                && abs($candWeight - $weight) < 0.001
                && $candCustom === $customAttrs
            ) {
                $existingId = (int)$cand['id'];
                if ((string)($cand['product_code'] ?? '') !== (string)$existingId) {
                    DB::query("UPDATE variants SET product_code = ? WHERE id = ?", [(string)$existingId, $existingId]);
                }
                return [
                    'variant_id' => $existingId,
                    'catalog_id' => !empty($cand['catalog_id']) ? (int)$cand['catalog_id'] : $catalogId,
                    'product_code' => (string)$existingId,
                    'reused' => true,
                ];
            }
        }

        // No identical variant found -> create a new variant and set product_code = variant_id
        $variantAttributes = array_merge(
            [
                'size' => $size,
                'color' => $color,
                'hsn' => $hsnCode,
                'weight' => $weight,
                'custom_attributes' => $customAttrs,
            ],
            $customAttrs
        );

        $tempCode = 'TMP-' . bin2hex(random_bytes(6));
        DB::query(
            "INSERT INTO variants (
                concern_id, catalog_id, product_code, name, category_id, subcategory_id, sub_sub_category_id, size,
                weight, weight_unit, attributes, cost_price, price, mrp, is_complementary,
                creation_date, status, created_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, CURDATE(), 'active', NOW())",
            [
                $concernId,
                $catalogId,
                $tempCode,
                $name,
                $categoryId,
                $subcategoryId,
                $subSubcategoryId,
                $size,
                $weight,
                $weightUnit,
                json_encode($variantAttributes),
                $costPrice,
                $price,
                $mrp,
            ]
        );
        $variantId = (int)DB::lastInsertId();
        $productCode = (string)$variantId;
        DB::query("UPDATE variants SET product_code = ? WHERE id = ?", [$productCode, $variantId]);

        return [
            'variant_id' => $variantId,
            'catalog_id' => $catalogId,
            'product_code' => $productCode,
            'reused' => false,
        ];
    }

    private static function normalizeCustomAttributes(array $attrs): array
    {
        $normalized = [];
        foreach ($attrs as $k => $v) {
            $key = strtolower(trim((string)$k));
            $val = is_scalar($v) ? trim((string)$v) : '';
            if ($key === '' || $val === '' || in_array($key, ['size', 'color', 'hsn', 'weight', 'custom_attributes'], true)) {
                continue;
            }
            $normalized[$key] = strtolower($val);
        }
        ksort($normalized);
        return $normalized;
    }

    private static function extractCustomAttributes(array $decodedAttrs): array
    {
        $raw = [];
        if (!empty($decodedAttrs['custom_attributes']) && is_array($decodedAttrs['custom_attributes'])) {
            $raw = $decodedAttrs['custom_attributes'];
        }
        foreach ($decodedAttrs as $k => $v) {
            if (!in_array((string)$k, ['size', 'color', 'hsn', 'weight', 'custom_attributes'], true) && is_scalar($v)) {
                $raw[(string)$k] = $v;
            }
        }
        return self::normalizeCustomAttributes($raw);
    }

    /**
     * Fetch full Variant ID details with 3-tier hierarchy, attributes, pricing, created date, and live stock.
     */
    public static function getVariantDetailsList(?int $cid): array
    {
        $sql = "SELECT v.id AS variant_id, v.concern_id, v.product_code, v.name, v.size,
                       v.weight, v.weight_unit, v.attributes, v.cost_price, v.price, v.mrp,
                       v.creation_date, v.status, v.created_at,
                       cat.name AS category_name, sub.name AS subcategory_name, ssc.name AS sub_subcategory_name,
                       c.style_code, c.hsn_code AS catalog_hsn, c.gst_rate AS catalog_gst_rate, c.hierarchy_path,
                       con.name AS concern_name,
                       COALESCE(stk.total_qty, 0) AS stock_qty
                FROM variants v
                LEFT JOIN categories cat ON cat.id = v.category_id
                LEFT JOIN subcategories sub ON sub.id = v.subcategory_id
                LEFT JOIN catalogs c ON c.id = v.catalog_id
                LEFT JOIN sub_subcategories ssc ON ssc.id = COALESCE(v.sub_sub_category_id, c.sub_sub_category_id)
                LEFT JOIN concerns con ON con.id = v.concern_id
                LEFT JOIN (
                    SELECT variant_id, SUM(qty) AS total_qty
                    FROM stocks
                    GROUP BY variant_id
                ) stk ON stk.variant_id = v.id
                WHERE 1=1";
        $params = [];
        if ($cid) {
            $sql .= " AND v.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY v.id DESC";

        $rows = DB::fetchAll($sql, $params);
        foreach ($rows as &$r) {
            $attrs = json_decode((string)($r['attributes'] ?? ''), true);
            $attrs = is_array($attrs) ? $attrs : [];
            $r['color'] = trim((string)($attrs['color'] ?? ''));
            $r['hsn_code'] = trim((string)($r['catalog_hsn'] ?? '')) ?: trim((string)($attrs['hsn'] ?? ''));
            if (empty($r['hierarchy_path'])) {
                $r['hierarchy_path'] = implode(' › ', array_filter([
                    $r['category_name'] ?? '',
                    $r['subcategory_name'] ?? '',
                    $r['sub_subcategory_name'] ?? '',
                ]));
            } else {
                $r['hierarchy_path'] = str_replace(' > ', ' › ', (string)$r['hierarchy_path']);
            }
            $custom = $attrs['custom_attributes'] ?? [];
            if (!is_array($custom)) {
                $custom = [];
            }
            foreach ($attrs as $k => $v) {
                if (!in_array((string)$k, ['size', 'color', 'hsn', 'weight', 'custom_attributes'], true) && is_scalar($v) && trim((string)$v) !== '') {
                    $custom[(string)$k] = trim((string)$v);
                }
            }
            $r['custom_attributes'] = $custom;
        }
        unset($r);
        return $rows;
    }
}
