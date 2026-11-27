<?php
// app/controllers/StockController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Core\ModuleGuard;
use App\Services\StockService;

class StockController extends Controller {
    private static bool $schemaEnsured = false;
    private static array $columnCache = [];

    private static function ensureVariantSchema(): void {
        if (self::$schemaEnsured) return;
        try {
            $variantColumns = array_column(DB::fetchAll("DESCRIBE variants"), 'Field');
            if (!in_array('cost_price', $variantColumns, true)) {
                DB::query("ALTER TABLE variants ADD COLUMN cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER attributes");
            }
            if (!in_array('is_complementary', $variantColumns, true)) {
                DB::query("ALTER TABLE variants ADD COLUMN is_complementary TINYINT(1) NOT NULL DEFAULT 0 AFTER mrp");
            }
        } catch (\Throwable $e) {
            // Silently fall through
        }
        self::$schemaEnsured = true;
    }

    private static function hasTableColumn(string $table, string $column): bool {
        if (!array_key_exists($table, self::$columnCache)) {
            self::$columnCache[$table] = array_column(DB::fetchAll("SHOW COLUMNS FROM `{$table}`"), 'Field');
        }

        return in_array($column, self::$columnCache[$table], true);
    }

    private static function resolveStockLabelColor(array $variantAttributes, int $variantId, string $variantName, array $variant = []): string {
        $color = trim((string)($variantAttributes['color'] ?? ''));
        if ($color !== '') {
            return $color;
        }

        $colorQueries = [];
        if (self::hasTableColumn('qc_inspection_items', 'color')) {
            $colorQueries[] = [
                "SELECT color FROM qc_inspection_items WHERE variant_id = ? AND color IS NOT NULL AND TRIM(color) != '' ORDER BY id DESC LIMIT 1",
                [$variantId]
            ];
        }
        if (self::hasTableColumn('qc_piece_items', 'color')) {
            $colorQueries[] = [
                "SELECT color FROM qc_piece_items WHERE variant_id = ? AND color IS NOT NULL AND TRIM(color) != '' ORDER BY id DESC LIMIT 1",
                [$variantId]
            ];
        }
        if (self::hasTableColumn('stitching_items', 'color')) {
            $colorQueries[] = [
                "SELECT color FROM stitching_items WHERE variant_id = ? AND color IS NOT NULL AND TRIM(color) != '' ORDER BY id DESC LIMIT 1",
                [$variantId]
            ];
        }
        if (self::hasTableColumn('cutting_items', 'color')) {
            $colorQueries[] = [
                "SELECT color FROM cutting_items WHERE (variant_id = ? OR (catalog_id IS NOT NULL AND catalog_id = ?)) AND color IS NOT NULL AND TRIM(color) != '' ORDER BY id DESC LIMIT 1",
                [$variantId, (int)($variant['catalog_id'] ?? 0)]
            ];
        }

        foreach ($colorQueries as [$sql, $params]) {
            $sourceColor = DB::fetchValue($sql, $params);
            if ($sourceColor !== false && $sourceColor !== null && trim((string)$sourceColor) !== '') {
                return trim((string)$sourceColor);
            }
        }

        if (preg_match('/\(([^)]+)\)/', $variant['name'], $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\(([^)]+)\)/', $variantName, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    private static function resolveStockLabelDesign(array $stock, array $variantAttributes, int $variantId): string {
        $rollDesign = DB::fetchValue(
            "SELECT COALESCE(NULLIF(TRIM(pbi.pattern), ''), NULLIF(TRIM(pbi.specification), ''))
             FROM stitching_items si
             JOIN cutting_items ci ON ci.id = si.cutting_item_id
             JOIN program_rolls pr ON pr.id = ci.program_roll_id
             JOIN purchase_bill_items pbi ON pbi.id = pr.purchase_bill_item_id
             WHERE si.variant_id = ?
               AND (
                   TRIM(COALESCE(pbi.pattern, '')) != ''
                   OR TRIM(COALESCE(pbi.specification, '')) != ''
               )
             ORDER BY si.id DESC
             LIMIT 1",
            [$variantId]
        );
        if ($rollDesign !== false && $rollDesign !== null && trim((string)$rollDesign) !== '') {
            return trim((string)$rollDesign);
        }

        foreach (array_merge($variantAttributes, (array)($variantAttributes['custom_attributes'] ?? [])) as $name => $value) {
            if (in_array(strtolower(trim((string)$name)), ['design', 'pattern'], true) && is_scalar($value) && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }

        $design = trim((string)($stock['sub_subcategory_name'] ?? ''));
        if ($design === '') {
            $pathParts = array_values(array_filter(array_map('trim', explode('>', (string)($stock['hierarchy_path'] ?? '')))));
            if (count($pathParts) > 1) {
                $design = (string)end($pathParts);
            }
        }
        if ($design === '') {
            $design = trim((string)($stock['subcategory_name'] ?? ''));
        }

        return $design !== '' && $design !== '-' ? $design : 'STANDARD';
    }

    public function index(): void {
        self::ensureVariantSchema();
        \App\Services\StockService::consolidateLegacyLocations();
        $cid = Auth::concernId();

        $sql = "SELECT s.*, v.name AS variant_name, v.product_code, v.size, v.weight, v.weight_unit, v.cost_price, v.price, v.mrp, v.catalog_id,
                       v.category_id, v.subcategory_id, v.is_complementary,
                       v.attributes AS variant_attributes,
                       COALESCE(c.name, 'General') AS category_name, 
                       COALESCE(sub.name, ssc.name, '-') AS subcategory_name,
                       ssc.name AS sub_subcategory_name,
                       cat.name AS catalog_name, cat.style_code, cat.short_code AS whole_code, cat.hierarchy_path, cat.hsn_code AS catalog_hsn,
                       con.name AS concern_name, con.code AS concern_code,
                       (SELECT pbi.id
                        FROM purchase_bill_items pbi
                        WHERE pbi.section_type = 'finished_goods'
                          AND COALESCE(pbi.inward_status, 'pending') = 'completed'
                          AND (
                              (pbi.concern_id = s.concern_id AND pbi.variant_id = v.id)
                              OR (v.catalog_id IS NOT NULL AND pbi.catalog_id = v.catalog_id)
                              OR pbi.item_code = v.product_code
                              OR (cat.style_code IS NOT NULL AND pbi.item_code = cat.style_code)
                          )
                          AND (latest_purchase.ref_module IS NULL OR latest_purchase.ref_module <> 'Purchase Inward Item' OR pbi.id = latest_purchase.ref_id)
                          AND (latest_purchase.ref_module IS NULL OR latest_purchase.ref_module <> 'Purchase Inward' OR pbi.purchase_bill_id = latest_purchase.ref_id)
                        ORDER BY CASE
                                   WHEN latest_purchase.ref_module = 'Purchase Inward Item' AND pbi.id = latest_purchase.ref_id THEN 0
                                   WHEN pbi.variant_id = v.id THEN 1
                                   ELSE 2
                                 END,
                                 pbi.id DESC
                        LIMIT 1) AS purchase_item_id,
                       COALESCE(
                           NULLIF((SELECT MIN(v_src.id) FROM variants v_src WHERE v_src.product_code = v.product_code AND v.product_code IS NOT NULL AND v.product_code != ''), 0),
                           v.id
                       ) AS canonical_variant_id,
                       CASE
                           WHEN v.product_code LIKE 'FG-%'
                             OR cat.style_code LIKE 'FG-%'
                             OR cat.code LIKE 'FG-%'
                             OR cat.source_module = 'product_adding'
                             OR EXISTS (
                                 SELECT 1 FROM purchase_bill_items pbi_fg
                                 WHERE ((pbi_fg.concern_id = s.concern_id AND pbi_fg.variant_id = v.id)
                                        OR (v.catalog_id IS NOT NULL AND pbi_fg.catalog_id = v.catalog_id)
                                        OR pbi_fg.item_code = v.product_code
                                        OR (cat.style_code IS NOT NULL AND pbi_fg.item_code = cat.style_code))
                                   AND pbi_fg.section_type = 'finished_goods'
                             )
                             OR EXISTS (
                                 SELECT 1 FROM inward_items inwi
                                 JOIN inwards inw ON inwi.inward_id = inw.id
                                 WHERE inwi.variant_id = v.id AND inw.inward_type = 'purchase'
                             )
                           THEN 'purchase'
                           ELSE CASE COALESCE(latest_inbound.ref_module, latest_variant_source.ref_module)
                                WHEN 'Production Inward' THEN 'own'
                                WHEN 'Production Stock Adjustment' THEN 'own'
                                WHEN 'Production QC' THEN 'own'
                                WHEN 'Purchase Inward' THEN 'purchase'
                                WHEN 'Purchase Inward Item' THEN 'purchase'
                                WHEN 'Purchase Stock Adjustment' THEN 'purchase'
                                ELSE CASE
                           WHEN EXISTS (
                               SELECT 1 FROM purchase_bill_items pbi_chk
                               WHERE (pbi_chk.variant_id = v.id OR (v.catalog_id IS NOT NULL AND pbi_chk.catalog_id = v.catalog_id) OR pbi_chk.item_code = v.product_code)
                           ) THEN 'purchase'
                           WHEN EXISTS (
                               SELECT 1 FROM stock_ledger sl_prod 
                               WHERE sl_prod.concern_id = s.concern_id 
                                 AND sl_prod.variant_id = s.variant_id 
                                 AND sl_prod.ref_module IN ('Production QC', 'Production Inward', 'Production Stock Adjustment')
                           ) THEN 'own'
                           WHEN EXISTS (
                               SELECT 1 FROM qc_inspection_items qii_chk 
                               WHERE qii_chk.variant_id = s.variant_id
                           ) THEN 'own'
                           WHEN EXISTS (
                               SELECT 1 FROM stitching_items sti_chk 
                               WHERE sti_chk.variant_id = s.variant_id
                           ) THEN 'own'
                           WHEN EXISTS (
                               SELECT 1 FROM batches b_chk 
                               WHERE b_chk.variant_id = s.variant_id
                           ) THEN 'own'
                           ELSE 'other'
                                END
                            END
                        END AS stock_source
                FROM stocks s
                JOIN variants v ON s.variant_id = v.id
                LEFT JOIN catalogs cat ON v.catalog_id = cat.id
                LEFT JOIN concerns con ON s.concern_id = con.id
                LEFT JOIN categories c ON COALESCE(v.category_id, cat.category_id) = c.id
                LEFT JOIN subcategories sub ON COALESCE(v.subcategory_id, cat.subcategory_id) = sub.id
                LEFT JOIN sub_subcategories ssc ON cat.sub_sub_category_id = ssc.id
                LEFT JOIN (
                    SELECT inbound.concern_id, inbound.variant_id, inbound.location, inbound.ref_module, inbound.ref_id
                    FROM stock_ledger inbound
                    JOIN (
                        SELECT concern_id, variant_id, location, MAX(id) AS latest_id
                        FROM stock_ledger
                        WHERE qty_in > 0
                          AND ref_module IN (
                              'Production Inward', 'Production Stock Adjustment', 'Production QC',
                              'Purchase Inward', 'Purchase Inward Item', 'Purchase Stock Adjustment'
                          )
                        GROUP BY concern_id, variant_id, location
                    ) latest ON latest.latest_id = inbound.id
                ) latest_inbound
                  ON latest_inbound.concern_id = s.concern_id
                 AND latest_inbound.variant_id = s.variant_id
                 AND latest_inbound.location = s.location
                LEFT JOIN (
                    SELECT inbound.concern_id, inbound.variant_id, inbound.ref_module, inbound.ref_id
                    FROM stock_ledger inbound
                    JOIN (
                        SELECT concern_id, variant_id, MAX(id) AS latest_id
                        FROM stock_ledger
                        WHERE qty_in > 0
                          AND ref_module IN (
                              'Production Inward', 'Production Stock Adjustment', 'Production QC',
                              'Purchase Inward', 'Purchase Inward Item', 'Purchase Stock Adjustment'
                          )
                        GROUP BY concern_id, variant_id
                    ) latest ON latest.latest_id = inbound.id
                ) latest_variant_source
                  ON latest_variant_source.concern_id = s.concern_id
                 AND latest_variant_source.variant_id = s.variant_id
                LEFT JOIN (
                    SELECT inbound.concern_id, inbound.variant_id, inbound.location, inbound.ref_module, inbound.ref_id
                    FROM stock_ledger inbound
                    JOIN (
                        SELECT concern_id, variant_id, location, MAX(id) AS latest_id
                        FROM stock_ledger
                        WHERE qty_in > 0 AND ref_module IN ('Purchase Inward', 'Purchase Inward Item')
                        GROUP BY concern_id, variant_id, location
                    ) latest ON latest.latest_id = inbound.id
                ) latest_purchase
                  ON latest_purchase.concern_id = s.concern_id
                 AND latest_purchase.variant_id = s.variant_id
                 AND latest_purchase.location = s.location";
        
        $sql .= " WHERE s.qty > 0 
                   AND s.location NOT LIKE '%:DAMAGED%' 
                   AND s.location NOT LIKE '%:QUARANTINE%'
                   AND s.location NOT LIKE '%:RESERVED%'
                   AND s.location NOT LIKE '%:IN_TRANSIT%'";
        $params = [];
        if ($cid) {
            $sql .= " AND s.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY FIELD(stock_source, 'own', 'purchase', 'other'), COALESCE(cat.name, v.name) ASC, " . self::sizeOrderSql('v.size') . ", s.qty DESC";
        $stocks = DB::fetchAll($sql, $params);
        foreach ($stocks as &$stock) {
            $variantAttributes = json_decode((string)($stock['variant_attributes'] ?? ''), true);
            $variantAttributes = is_array($variantAttributes) ? $variantAttributes : [];
            $stock['resolved_color'] = self::resolveStockLabelColor(
                $variantAttributes,
                (int)$stock['variant_id'],
                (string)$stock['variant_name']
            );
            $stock['product_name'] = trim((string)($stock['catalog_name'] ?? '')) ?: (string)$stock['variant_name'];
            $stock['design_name'] = self::resolveStockLabelDesign($stock, $variantAttributes, (int)$stock['variant_id']);
        }
        unset($stock);
        $totalUnits = array_sum(array_column($stocks, 'qty'));
        $totalValuation = array_reduce($stocks, fn($sum, $item) => $sum + ((float)$item['qty'] * (float)$item['price']), 0);
        $stockSourceMetrics = [
            'own' => ['units' => 0.0, 'valuation' => 0.0, 'variants' => []],
            'purchase' => ['units' => 0.0, 'valuation' => 0.0, 'variants' => []],
            'other' => ['units' => 0.0, 'valuation' => 0.0, 'variants' => []],
        ];
        $uniqueVariantKeys = [];
        foreach ($stocks as $stock) {
            $source = (string)($stock['stock_source'] ?? 'other');
            if (!isset($stockSourceMetrics[$source])) {
                $source = 'other';
            }
            $variantKey = (int)$stock['concern_id'] . ':' . (int)$stock['variant_id'];
            $quantity = (float)$stock['qty'];
            $value = $quantity * (float)$stock['price'];
            $stockSourceMetrics[$source]['units'] += $quantity;
            $stockSourceMetrics[$source]['valuation'] += $value;
            $stockSourceMetrics[$source]['variants'][$variantKey] = true;
            $uniqueVariantKeys[$variantKey] = true;
        }
        foreach ($stockSourceMetrics as &$sourceMetrics) {
            $sourceMetrics['variant_count'] = count($sourceMetrics['variants']);
            unset($sourceMetrics['variants']);
        }
        unset($sourceMetrics);
        $uniqueVariantCount = count($uniqueVariantKeys);

        $categories = DB::fetchAll(
            "SELECT id, name, concern_id FROM categories WHERE status = 1" . ($cid ? " AND concern_id = ?" : "") . " ORDER BY name ASC",
            $cid ? [$cid] : []
        );
        $subcategories = DB::fetchAll(
            "SELECT id, category_id, name, concern_id FROM subcategories WHERE status = 1" . ($cid ? " AND concern_id = ?" : "") . " ORDER BY name ASC",
            $cid ? [$cid] : []
        );

        $this->render('concern/stocks', [
            'pageTitle'      => 'Live Inventory Stocks',
            'currentModule'  => 'stocks',
            'stocks'         => $stocks,
            'totalUnits'     => $totalUnits,
            'totalValuation' => $totalValuation,
            'stockSourceMetrics' => $stockSourceMetrics,
            'uniqueVariantCount' => $uniqueVariantCount,
            'selectedCid'    => $cid,
            'canEditStock'   => Auth::isSuperAdmin() || Auth::can('stocks', 'edit'),
            'canDeleteStock' => Auth::isSuperAdmin() || Auth::can('stocks', 'delete'),
            'categories'     => $categories,
            'subcategories'  => $subcategories,
        ]);
    }

    public function details(): void
    {
        self::ensureVariantSchema();
        try {
            $variantId = filter_var($_GET['variant_id'] ?? null, FILTER_VALIDATE_INT);
            $rawConcernId = $_GET['concern_id'] ?? null;
            $concernId = is_scalar($rawConcernId) ? filter_var($rawConcernId, FILTER_VALIDATE_INT) : null;
            $location = trim((string)($_GET['location'] ?? ''));

            if (!$variantId || $variantId < 1) {
                $this->renderJson(['success' => false, 'message' => 'Invalid variant ID.'], 400);
                return;
            }

            $sessionConcernId = Auth::concernId();
            if (!Auth::isSuperAdmin() && $sessionConcernId) {
                if ($concernId && (int)$sessionConcernId !== (int)$concernId) {
                    $this->renderJson(['success' => false, 'message' => 'Unauthorized concern access.'], 403);
                    return;
                }
                $concernId = (int)$sessionConcernId;
            }

            $variant = DB::fetch(
                "SELECT v.*, 
                        c.name AS category_name, c.code AS category_code,
                        sub.name AS subcategory_name, sub.code AS subcategory_code,
                        ssc.name AS sub_subcategory_name, ssc.code AS sub_subcategory_code,
                        cat.id AS catalog_style_id, cat.style_code, cat.short_code AS whole_code, cat.name AS style_name,
                        cat.hierarchy_path, cat.hsn_code AS catalog_hsn, cat.gst_rate AS catalog_gst_rate,
                        con.name AS concern_name, con.code AS concern_code
                 FROM variants v
                 LEFT JOIN catalogs cat ON v.catalog_id = cat.id
                 LEFT JOIN categories c ON COALESCE(v.category_id, cat.category_id) = c.id
                 LEFT JOIN subcategories sub ON COALESCE(v.subcategory_id, cat.subcategory_id) = sub.id
                 LEFT JOIN sub_subcategories ssc ON cat.sub_sub_category_id = ssc.id
                 LEFT JOIN concerns con ON v.concern_id = con.id
                 WHERE v.id = ?" . ($concernId ? " AND v.concern_id = ?" : ""),
                $concernId ? [$variantId, $concernId] : [$variantId]
            );

            if (!$variant) {
                $this->renderJson(['success' => false, 'message' => 'Product variant not found.'], 404);
                return;
            }

            $concernId = (int)$variant['concern_id'];

            // Attempt to resolve catalog if catalog_id was missing on variant
            $catId = (int)($variant['catalog_id'] ?? ($variant['catalog_style_id'] ?? 0));
            if (!$catId && !empty($variant['product_code'])) {
                $lastDash = strrpos($variant['product_code'], '-');
                $candidateStyleCode = $lastDash !== false ? substr($variant['product_code'], 0, $lastDash) : $variant['product_code'];
                $matchedCat = DB::fetch(
                    "SELECT cat.*, c.name AS category_name, sub.name AS subcategory_name, ssc.name AS sub_subcategory_name
                     FROM catalogs cat
                     LEFT JOIN categories c ON cat.category_id = c.id
                     LEFT JOIN subcategories sub ON cat.subcategory_id = sub.id
                     LEFT JOIN sub_subcategories ssc ON cat.sub_sub_category_id = ssc.id
                     WHERE (cat.style_code = ? OR cat.code = ?) " . ($concernId ? "AND (cat.concern_id = ? OR cat.concern_id = 1 OR cat.concern_id IS NULL) " : "") . "LIMIT 1",
                    $concernId ? [$candidateStyleCode, $candidateStyleCode, $concernId] : [$candidateStyleCode, $candidateStyleCode]
                );
                if ($matchedCat) {
                    $catId = (int)$matchedCat['id'];
                    $variant['catalog_id'] = $catId;
                    $variant['style_code'] = $matchedCat['style_code'];
                    $variant['whole_code'] = $matchedCat['short_code'];
                    $variant['style_name'] = $matchedCat['name'];
                    $variant['hierarchy_path'] = $matchedCat['hierarchy_path'];
                    $variant['catalog_hsn'] = $matchedCat['hsn_code'];
                    $variant['catalog_gst_rate'] = $matchedCat['gst_rate'];
                    if (empty($variant['category_name']) || $variant['category_name'] === 'General') {
                        $variant['category_name'] = $matchedCat['category_name'];
                    }
                    if (empty($variant['subcategory_name']) || $variant['subcategory_name'] === '-') {
                        $variant['subcategory_name'] = $matchedCat['subcategory_name'];
                    }
                    if (empty($variant['sub_sub_category_name']) || $variant['sub_sub_category_name'] === '-') {
                        $variant['sub_sub_category_name'] = $matchedCat['sub_sub_category_name'];
                    }
                }
            }

            // Fallback for subcategory from hierarchy_path
            if ((empty($variant['subcategory_name']) || $variant['subcategory_name'] === '-') && !empty($variant['hierarchy_path'])) {
                $hpParts = array_map('trim', explode('>', $variant['hierarchy_path']));
                if (!empty($hpParts[1])) {
                    $variant['subcategory_name'] = $hpParts[1];
                }
                if (!empty($hpParts[2])) {
                    $variant['sub_sub_category_name'] = $hpParts[2];
                }
            }

            $stock = null;
            if ($location !== '') {
                $stock = DB::fetch(
                    "SELECT s.*,
                            (SELECT inbound.ref_module
                             FROM stock_ledger inbound
                             WHERE inbound.concern_id = s.concern_id
                               AND inbound.variant_id = s.variant_id
                               AND inbound.location = s.location
                               AND inbound.qty_in > 0
                             ORDER BY inbound.id DESC
                             LIMIT 1) AS latest_inbound_module
                     FROM stocks s
                     WHERE s.concern_id = ? AND s.variant_id = ? AND s.location = ?",
                    [$concernId, $variantId, $location]
                );
            }

            $totalStock = DB::fetch(
                "SELECT COALESCE(SUM(qty), 0) AS total_qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND qty > 0",
                [$concernId, $variantId]
            );

            $variantAttributes = json_decode((string)($variant['attributes'] ?? ''), true);
            $variantAttributes = is_array($variantAttributes) ? $variantAttributes : [];

            $color = self::resolveStockLabelColor($variantAttributes, $variantId, (string)$variant['name']);

            // Resolve HSN Code
            $hsn = trim((string)($variantAttributes['hsn'] ?? ''));
            if ($hsn === '' && !empty($variant['hsn_code'])) {
                $hsn = trim((string)$variant['hsn_code']);
            }
            if ($hsn === '' && !empty($variant['catalog_hsn'])) {
                $hsn = trim((string)$variant['catalog_hsn']);
            }
            if ($hsn === '') {
                $catHsn = DB::fetchValue("SELECT hsn_code FROM categories WHERE id = ?", [$variant['category_id'] ?: 0]);
                if ($catHsn) {
                    $hsn = trim((string)$catHsn);
                }
            }

            // Resolve GST Rate
            $gstRate = isset($variant['catalog_gst_rate']) && $variant['catalog_gst_rate'] !== null ? (float)$variant['catalog_gst_rate'] : null;
            if ($gstRate === null) {
                $catGst = DB::fetchValue("SELECT gst_rate FROM categories WHERE id = ?", [$variant['category_id'] ?: 0]);
                if ($catGst !== false && $catGst !== null) {
                    $gstRate = (float)$catGst;
                }
            }

            // QR Code Data
            $qrPayload = \App\Services\QrService::formatBrandVariantCode($variantId);
            if (!$qrPayload) {
                $qrPayload = $variant['product_code'];
            }
            $qrData = [
                'payload'    => $qrPayload,
                'tag_code'   => $qrPayload,
                'product_code'   => $variant['product_code'],
                'whole_code' => $variant['whole_code'] ?? '',
                'style_code' => $variant['style_code'] ?? '',
                'print_url'  => \App\Core\Controller::url('/m/label_generation?variant_id=' . $variantId),
            ];

            // Production & Manufacturing Journey Data
            $qcRecord = DB::fetch(
                "SELECT qi.*, u.name AS inspector_name,
                        qii.color AS item_color, qii.size AS item_size, qii.inspected_qty AS item_inspected_qty,
                        qii.passed_qty AS item_passed_qty, qii.rejected_qty AS item_rejected_qty,
                        qii.rework_qty AS item_rework_qty, qii.defect_notes AS item_defect_notes
                 FROM qc_inspections qi
                 JOIN qc_inspection_items qii ON qii.qc_id = qi.id
                 LEFT JOIN users u ON qi.inspector_id = u.id
                 WHERE qii.variant_id = ?
                 ORDER BY qi.id DESC LIMIT 1",
                [$variantId]
            );

            $stitchingRecord = DB::fetch(
                "SELECT sl.*, sm.name AS master_tailor_name, sm.phone AS tailor_phone,
                        si.color AS item_color, si.size AS item_size, si.stitching_qty AS item_stitched_qty,
                        si.cut_qty AS item_cut_qty, si.per_pc_weight, si.total_weight AS item_total_weight
                 FROM stitching_logs sl
                 JOIN stitching_items si ON si.stitching_id = sl.id
                 LEFT JOIN stitching_masters sm ON sl.tailor_id = sm.id
                 WHERE si.variant_id = ?
                 ORDER BY sl.id DESC LIMIT 1",
                [$variantId]
            );

            $cuttingId = $stitchingRecord['cutting_id'] ?? ($qcRecord['cutting_batch_id'] ?? null);
            $cuttingRecord = null;
            if ($cuttingId) {
                $cuttingRecord = DB::fetch(
                    "SELECT cl.*, ci.cut_qty AS item_cut_qty, ci.color AS item_color
                     FROM cutting_logs cl
                     LEFT JOIN cutting_items ci ON ci.cutting_id = cl.id AND (ci.variant_id = ? OR (ci.catalog_id IS NOT NULL AND ci.catalog_id = ?))
                     WHERE cl.id = ? LIMIT 1",
                    [$variantId, $catId, $cuttingId]
                );
            }
            if (!$cuttingRecord) {
                $cuttingRecord = DB::fetch(
                    "SELECT cl.*, ci.cut_qty AS item_cut_qty, ci.color AS item_color
                     FROM cutting_logs cl
                     JOIN cutting_items ci ON ci.cutting_id = cl.id
                     WHERE ci.variant_id = ?
                     ORDER BY cl.id DESC LIMIT 1",
                    [$variantId]
                );
            }

            $programId = $qcRecord['program_id'] ?? ($stitchingRecord['program_id'] ?? ($cuttingRecord['program_id'] ?? null));
            $programRecord = null;
            if ($programId) {
                $programRecord = DB::fetch(
                    "SELECT pp.*, c.name AS catalog_name, con.name AS concern_name
                     FROM production_programs pp
                     LEFT JOIN catalogs c ON pp.catalog_id = c.id
                     LEFT JOIN concerns con ON pp.concern_id = con.id
                     WHERE pp.id = ? LIMIT 1",
                    [$programId]
                );
            }

            $batchRecord = DB::fetch(
                "SELECT b.*, con.name AS concern_name
                 FROM batches b
                 LEFT JOIN concerns con ON b.concern_id = con.id
                 WHERE b.variant_id = ?
                 ORDER BY b.id DESC LIMIT 1",
                [$variantId]
            );

            $prodLedger = DB::fetch(
                "SELECT sl.*, u.name AS user_name
                 FROM stock_ledger sl
                 LEFT JOIN users u ON sl.created_by = u.id
                 WHERE sl.variant_id = ? AND sl.concern_id = ? AND sl.ref_module IN ('Production QC', 'Production Inward', 'Production Stock Adjustment')
                 ORDER BY sl.id DESC LIMIT 1",
                [$variantId, $concernId]
            );

            $productionJourney = null;
            if ($qcRecord || $stitchingRecord || $cuttingRecord || $programRecord || $batchRecord || $catId > 0) {
                $productionJourney = [
                    'has_production' => true,
                    'catalog' => [
                        'id'             => $catId,
                        'style_code'     => $variant['style_code'] ?? '-',
                        'style_name'     => $variant['style_name'] ?? $variant['name'],
                        'whole_code'     => $variant['whole_code'] ?? '-',
                        'hierarchy_path' => $variant['hierarchy_path'] ?? '-',
                    ],
                    'program' => $programRecord ? [
                        'id'           => (int)$programRecord['id'],
                        'program_no'   => $programRecord['program_no'],
                        'style_name'   => $programRecord['style_name'] ?: ($variant['style_name'] ?? '-'),
                        'order_qty'    => (float)$programRecord['order_qty'],
                        'status'       => $programRecord['status'] ?: 'Completed',
                        'start_date'   => $programRecord['start_date'] ?: null,
                        'delivery_date'=> $programRecord['delivery_date'] ?: null,
                    ] : null,
                    'cutting' => $cuttingRecord ? [
                        'id'              => (int)$cuttingRecord['id'],
                        'batch_no'        => $cuttingRecord['batch_no'],
                        'master_name'     => $cuttingRecord['cutting_master_name'] ?: ($cuttingRecord['cutting_name'] ?: 'Master Cutter'),
                        'cut_pieces'      => (float)($cuttingRecord['item_cut_qty'] ?: $cuttingRecord['cut_pieces']),
                        'total_cut_pieces'=> (float)$cuttingRecord['cut_pieces'],
                        'fabric_consumed' => (float)($cuttingRecord['consumed_qty'] ?: $cuttingRecord['total_roll_weight']),
                        'cost_per_piece'  => (float)($cuttingRecord['fabric_cost_per_piece'] ?? 0),
                        'status'          => $cuttingRecord['status'] ?: 'Completed',
                        'date'            => $cuttingRecord['created_at'],
                    ] : null,
                    'stitching' => $stitchingRecord ? [
                        'id'              => (int)$stitchingRecord['id'],
                        'batch_no'        => $stitchingRecord['batch_no'],
                        'tailor_name'     => $stitchingRecord['tailor_name'] ?: ($stitchingRecord['master_tailor_name'] ?: 'Production Tailor'),
                        'tailor_phone'    => $stitchingRecord['tailor_phone'] ?: '-',
                        'machine_no'      => $stitchingRecord['machine_no'] ?: '-',
                        'stitched_pieces' => (float)($stitchingRecord['item_stitched_qty'] ?: $stitchingRecord['stitched_pieces']),
                        'total_stitched'  => (float)$stitchingRecord['stitched_pieces'],
                        'stitch_weight'   => (float)($stitchingRecord['item_total_weight'] ?: $stitchingRecord['stitch_weight']),
                        'status'          => $stitchingRecord['status'] ?: 'Completed',
                        'date'            => $stitchingRecord['created_at'],
                    ] : null,
                    'qc' => $qcRecord ? [
                        'id'              => (int)$qcRecord['id'],
                        'qc_no'           => $qcRecord['qc_no'],
                        'inspector_name'  => $qcRecord['inspector_name'] ?: 'Quality Inspector',
                        'status'          => $qcRecord['status'] ?: 'PASSED',
                        'inspected_qty'   => (float)($qcRecord['item_inspected_qty'] ?: $qcRecord['inspected_qty']),
                        'passed_qty'      => (float)($qcRecord['item_passed_qty'] ?: $qcRecord['passed_qty']),
                        'rework_qty'      => (float)($qcRecord['item_rework_qty'] ?: $qcRecord['rework_qty']),
                        'rejected_qty'    => (float)($qcRecord['item_rejected_qty'] ?: $qcRecord['rejected_qty']),
                        'location'        => $qcRecord['location'] ?: 'Main Store',
                        'defect_notes'    => $qcRecord['defect_notes'] ?: ($qcRecord['item_defect_notes'] ?: ''),
                        'date'            => $qcRecord['created_at'],
                    ] : null,
                    'batch' => $batchRecord ? [
                        'id'              => (int)$batchRecord['id'],
                        'batch_no'        => $batchRecord['batch_no'],
                        'batch_qty'       => (float)$batchRecord['batch_qty'],
                        'warehouse_name'  => $batchRecord['warehouse_name'] ?: 'Main Store',
                        'status'          => $batchRecord['status'] ?: 'APPROVED',
                        'mfg_date'        => $batchRecord['mfg_date'] ?: null,
                    ] : null,
                    'ledger' => $prodLedger ? [
                        'module'          => $prodLedger['ref_module'],
                        'ref_id'          => $prodLedger['ref_id'],
                        'qty_in'          => (float)$prodLedger['qty_in'],
                        'note'            => $prodLedger['note'] ?: '-',
                        'created_at'      => $prodLedger['created_at'],
                        'user_name'       => $prodLedger['user_name'] ?: 'System',
                    ] : null,
                ];
            }

            echo json_encode([
                'success' => true,
                'variant' => [
                    'id'                    => (int)$variant['id'],
                    'concern_id'            => (int)$variant['concern_id'],
                    'concern_name'          => $variant['concern_name'] ?? 'Concern #' . $variant['concern_id'],
                    'concern_code'          => $variant['concern_code'] ?? '',
                    'name'                  => $variant['name'],
                    'product_code'              => $variant['product_code'],
                    'catalog_id'            => $catId ?: null,
                    'style_code'            => $variant['style_code'] ?? '',
                    'whole_code'            => $variant['whole_code'] ?? '',
                    'style_name'            => $variant['style_name'] ?? '',
                    'hierarchy_path'        => $variant['hierarchy_path'] ?? '',
                    'category_id'           => $variant['category_id'] ? (int)$variant['category_id'] : null,
                    'category_name'         => $variant['category_name'] ?? 'General',
                    'subcategory_id'        => $variant['subcategory_id'] ? (int)$variant['subcategory_id'] : null,
                    'subcategory_name'      => $variant['subcategory_name'] ?? '-',
                    'sub_sub_category_name' => $variant['sub_sub_category_name'] ?? '-',
                    'size'                  => $variant['size'] ?: 'Free',
                    'weight'                => (float)$variant['weight'],
                    'weight_unit'           => $variant['weight_unit'] ?: 'pcs',
                    'cost_price'            => (float)$variant['cost_price'],
                    'price'                 => (float)$variant['price'],
                    'mrp'                   => (float)$variant['mrp'],
                    'color'                 => $color !== '' ? $color : '-',
                    'hsn'                   => $hsn !== '' ? $hsn : '-',
                    'gst_rate'              => $gstRate,
                    'attributes'            => $variantAttributes,
                ],
                'stock' => $stock ? [
                    'location'              => $stock['location'],
                    'qty'                   => (float)$stock['qty'],
                    'net_value'             => (float)$stock['qty'] * (float)$variant['price'],
                    'latest_inbound_module' => $stock['latest_inbound_module'],
                ] : null,
                'total_stock_qty'    => (float)($totalStock['total_qty'] ?? 0),
                'qr'                 => $qrData,
                'production_journey' => $productionJourney,
            ]);
        } catch (\Throwable $e) {
            $this->renderJson(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function updateDetails(): void
    {
        ModuleGuard::enforce('stocks', 'edit');
        $this->validateCsrf();

        try {
            $concernId = $this->resolveStockActionConcern();
            $variantId = filter_var($_POST['variant_id'] ?? null, FILTER_VALIDATE_INT);
            $rawLocation = $_POST['location'] ?? null;
            if (!is_string($rawLocation)) {
                throw new \InvalidArgumentException('Select a valid stock location.');
            }
            $location = trim($rawLocation);
            if (!$variantId || $variantId < 1 || $location === '' || strlen($location) > 50) {
                throw new \InvalidArgumentException('Select a valid stock line.');
            }

            $variantName = trim((string)($_POST['variant_name'] ?? ''));
            if ($variantName === '' || strlen($variantName) > 190) {
                throw new \InvalidArgumentException('Variant name is required and cannot exceed 190 characters.');
            }

            $productCode = trim((string)($_POST['product_code'] ?? ''));
            if ($productCode === '' || strlen($productCode) > 100) {
                throw new \InvalidArgumentException('Product code is required and cannot exceed 100 characters.');
            }

            $size = trim((string)($_POST['size'] ?? ''));
            $color = trim((string)($_POST['color'] ?? ''));
            $hsnCode = trim((string)($_POST['hsn_code'] ?? ''));

            $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
            $categoryId = ($categoryId !== false && $categoryId > 0) ? $categoryId : null;

            $subcategoryId = filter_var($_POST['subcategory_id'] ?? null, FILTER_VALIDATE_INT);
            $subcategoryId = ($subcategoryId !== false && $subcategoryId > 0) ? $subcategoryId : null;

            $weight = filter_var($_POST['weight'] ?? 0, FILTER_VALIDATE_FLOAT);
            $weight = ($weight !== false && $weight >= 0) ? $weight : 0.0;

            $weightUnit = trim((string)($_POST['weight_unit'] ?? 'kg'));
            if (!in_array($weightUnit, ['kg', 'g', 'pcs', 'm', 'roll'], true)) {
                $weightUnit = 'kg';
            }

            $readPrice = static function (string $field, string $label): float {
                $rawValue = $_POST[$field] ?? null;
                if (!is_scalar($rawValue) || trim((string)$rawValue) === '') {
                    throw new \InvalidArgumentException("{$label} is required.");
                }
                $rawValue = trim((string)$rawValue);
                $value = filter_var($rawValue, FILTER_VALIDATE_FLOAT);
                if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $rawValue)
                    || $value === false
                    || !is_finite((float)$value)
                    || $value < 0
                    || $value > 9999999999.99) {
                    throw new \InvalidArgumentException("Enter a valid {$label} between ₹0.00 and ₹9,999,999,999.99.");
                }
                return (float)$value;
            };
            $costPrice = $readPrice('cost_price', 'cost price');
            $price = $readPrice('price', 'selling price');
            $mrp = $readPrice('mrp', 'MRP');
            if (!($costPrice < $price && $price < $mrp)) {
                throw new \InvalidArgumentException('Prices must follow: Cost Price < Selling Price < MRP.');
            }

            $rawQuantity = $_POST['qty'] ?? null;
            $newQuantity = filter_var($rawQuantity, FILTER_VALIDATE_INT);
            if ($newQuantity === false || $newQuantity < 0 || $newQuantity > 9999999999) {
                throw new \InvalidArgumentException('Quantity must be a whole number between 0 and 9,999,999,999.');
            }

            DB::beginTransaction();

            $stock = DB::fetch(
                "SELECT s.qty,
                        (SELECT inbound.ref_module
                         FROM stock_ledger inbound
                         WHERE inbound.concern_id = s.concern_id
                           AND inbound.variant_id = s.variant_id
                           AND inbound.location = s.location
                           AND inbound.qty_in > 0
                         ORDER BY inbound.id DESC
                         LIMIT 1) AS latest_inbound_module
                 FROM stocks s
                 JOIN variants v ON v.id = s.variant_id AND v.concern_id = s.concern_id
                 WHERE s.concern_id = ? AND s.variant_id = ? AND s.location = ?
                 FOR UPDATE",
                [$concernId, $variantId, $location]
            );
            if (!$stock) {
                throw new \RuntimeException('This stock line was not found for the selected concern.');
            }

            $existingSku = DB::fetch(
                "SELECT id FROM variants WHERE concern_id = ? AND product_code = ? AND id != ?",
                [$concernId, $productCode, $variantId]
            );
            if ($existingSku) {
                throw new \InvalidArgumentException("Product code '{$productCode}' is already used by another variant.");
            }

            $currentVariant = DB::fetch(
                "SELECT attributes, catalog_id FROM variants WHERE id = ? AND concern_id = ? FOR UPDATE",
                [$variantId, $concernId]
            );
            $attributes = json_decode((string)($currentVariant['attributes'] ?? ''), true);
            $attributes = is_array($attributes) ? $attributes : [];
            if ($color !== '') {
                $attributes['color'] = $color;
            } else {
                unset($attributes['color']);
            }
            if ($hsnCode !== '') {
                $attributes['hsn'] = $hsnCode;
            } else {
                unset($attributes['hsn']);
            }
            $attributes['weight'] = $weight;

            DB::query(
                "UPDATE variants
                 SET name = ?, product_code = ?, size = ?, weight = ?, weight_unit = ?,
                     cost_price = ?, price = ?, mrp = ?, category_id = ?, subcategory_id = ?, attributes = ?
                 WHERE id = ? AND concern_id = ?",
                [
                    $variantName, $productCode, $size, $weight, $weightUnit,
                    $costPrice, $price, $mrp, $categoryId, $subcategoryId, json_encode($attributes),
                    $variantId, $concernId
                ]
            );

            $catalogId = $currentVariant['catalog_id'] ?? null;
            DB::query(
                "UPDATE purchase_bill_items
                 SET item_name = ?,
                     item_code = ?,
                     hsn_code = ?,
                     color = ?,
                     specification = ?,
                     unit_price = ?,
                     mrp = ?,
                     selling_price = ?
                 WHERE concern_id = ?
                   AND (variant_id = ? OR (catalog_id IS NOT NULL AND catalog_id = ?))",
                [
                    $variantName, $productCode, $hsnCode, $color, $size,
                    $costPrice, $mrp, $price,
                    $concernId, $variantId, $catalogId ?: 0
                ]
            );

            $currentQuantity = (float)$stock['qty'];
            $targetQuantity = (float)$newQuantity;
            $difference = $targetQuantity - $currentQuantity;
            if (abs($difference) > 0.00001) {
                $refModule = $this->stockAdjustmentModule((string)$stock['latest_inbound_module'], $concernId, $variantId);
                StockService::move(
                    $concernId,
                    $variantId,
                    $location,
                    max(0, $difference),
                    max(0, -$difference),
                    $refModule,
                    null,
                    "Stock details & balance adjusted from {$currentQuantity} to {$targetQuantity}.",
                    Auth::id()
                );
            }

            DB::commit();
            $this->flash('success', 'Product details and stock balance updated successfully.');
        } catch (\Throwable $e) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            $this->flash('error', 'Unable to update stock details: ' . $e->getMessage());
        }

        $this->redirect('/m/stocks');
    }

    public function updateQuantity(): void
    {
        $this->changeStockQuantity(false);
    }

    public function deleteStockLine(): void
    {
        $this->changeStockQuantity(true);
    }

    private function changeStockQuantity(bool $remove): void
    {
        ModuleGuard::enforce('stocks', $remove ? 'delete' : 'edit');
        $this->validateCsrf();

        try {
            $concernId = $this->resolveStockActionConcern();
            $variantId = filter_var($_POST['variant_id'] ?? null, FILTER_VALIDATE_INT);
            $rawLocation = $_POST['location'] ?? null;
            if (!is_string($rawLocation)) {
                throw new \InvalidArgumentException('Select a valid stock location.');
            }
            $location = trim($rawLocation);
            if (!$variantId || $variantId < 1 || $location === '' || strlen($location) > 50) {
                throw new \InvalidArgumentException('Select a valid stock line.');
            }

            $newQuantity = 0;
            if (!$remove) {
                $rawQuantity = $_POST['qty'] ?? null;
                $newQuantity = filter_var($rawQuantity, FILTER_VALIDATE_INT);
                if ($newQuantity === false || $newQuantity < 0 || $newQuantity > 9999999999) {
                    throw new \InvalidArgumentException('Quantity must be a whole number between 0 and 9,999,999,999.');
                }
            }

            DB::beginTransaction();
            $stock = DB::fetch(
                "SELECT s.qty,
                        (SELECT inbound.ref_module
                         FROM stock_ledger inbound
                         WHERE inbound.concern_id = s.concern_id
                           AND inbound.variant_id = s.variant_id
                           AND inbound.location = s.location
                           AND inbound.qty_in > 0
                         ORDER BY inbound.id DESC
                         LIMIT 1) AS latest_inbound_module
                 FROM stocks s
                 JOIN variants v ON v.id = s.variant_id AND v.concern_id = s.concern_id
                 WHERE s.concern_id = ? AND s.variant_id = ? AND s.location = ?
                 FOR UPDATE",
                [$concernId, $variantId, $location]
            );
            if (!$stock) {
                throw new \RuntimeException('This stock line was not found for the selected concern.');
            }

            $currentQuantity = (float)$stock['qty'];
            $targetQuantity = $remove ? 0.0 : (float)$newQuantity;
            $difference = $targetQuantity - $currentQuantity;
            if (abs($difference) > 0.00001) {
                $refModule = $this->stockAdjustmentModule((string)$stock['latest_inbound_module'], $concernId, $variantId);
                StockService::move(
                    $concernId,
                    $variantId,
                    $location,
                    max(0, $difference),
                    max(0, -$difference),
                    $refModule,
                    null,
                    $remove
                        ? "Live stock line removed; balance adjusted from {$currentQuantity} to zero."
                        : "Live stock quantity adjusted from {$currentQuantity} to {$targetQuantity}.",
                    Auth::id()
                );
            }

            DB::commit();
            $this->flash(
                'success',
                $remove
                    ? 'Stock line was zeroed. Its movement history remains in the stock ledger.'
                    : 'Stock quantity updated and recorded in the stock ledger.'
            );
        } catch (\Throwable $e) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            $this->flash('error', 'Unable to update stock: ' . $e->getMessage());
        }

        $this->redirect('/m/stocks');
    }

    public function bulkUpdatePrice(): void
    {
        ModuleGuard::enforce('stocks', 'edit');
        $this->validateCsrf();

        try {
            $concernId = $this->resolveStockActionConcern();

            $rawIds = $_POST['variant_ids'] ?? [];
            if (!is_array($rawIds) || empty($rawIds)) {
                throw new \InvalidArgumentException('No products selected.');
            }
            $variantIds = array_values(array_filter(
                array_map(fn($id) => filter_var($id, FILTER_VALIDATE_INT), $rawIds),
                fn($id) => $id !== false && $id > 0
            ));
            if (empty($variantIds)) {
                throw new \InvalidArgumentException('Invalid product selection.');
            }
            $variantIds = array_values(array_unique($variantIds));

            $rawNewPrice = $_POST['price'] ?? null;
            if (!is_scalar($rawNewPrice)) {
                throw new \InvalidArgumentException('Enter a valid selling price (≥ 0).');
            }
            $newPrice = filter_var(trim((string)$rawNewPrice), FILTER_VALIDATE_FLOAT);
            if ($newPrice === false || !preg_match('/^\d+(?:\.\d{1,2})?$/D', trim((string)$rawNewPrice)) || $newPrice < 0 || $newPrice > 9999999999.99) {
                throw new \InvalidArgumentException('Enter a valid selling price (≥ 0).');
            }

            $newMrp = null;
            $rawNewMrp = $_POST['mrp'] ?? null;
            if ($rawNewMrp !== null && !is_scalar($rawNewMrp)) {
                throw new \InvalidArgumentException('Enter a valid MRP (≥ 0).');
            }
            if (is_scalar($rawNewMrp) && trim((string)$rawNewMrp) !== '') {
                $rawNewMrp = trim((string)$rawNewMrp);
                $newMrp = filter_var($rawNewMrp, FILTER_VALIDATE_FLOAT);
                if ($newMrp === false
                    || !preg_match('/^\d+(?:\.\d{1,2})?$/D', $rawNewMrp)
                    || $newMrp < 0
                    || $newMrp > 9999999999.99) {
                    throw new \InvalidArgumentException('Enter a valid MRP (≥ 0).');
                }
            }

            $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
            $selectedVariants = DB::fetchAll(
                "SELECT id, cost_price, mrp FROM variants WHERE concern_id = ? AND id IN ({$placeholders})",
                array_merge([$concernId], $variantIds)
            );
            if (count($selectedVariants) !== count($variantIds)) {
                throw new \InvalidArgumentException('One or more selected products are not available in this concern.');
            }
            foreach ($selectedVariants as $selectedVariant) {
                $targetMrp = $newMrp ?? (float)$selectedVariant['mrp'];
                if (!((float)$selectedVariant['cost_price'] < $newPrice && $newPrice < $targetMrp)) {
                    throw new \InvalidArgumentException('Bulk prices must follow Cost Price < Selling Price < MRP for every selected product.');
                }
            }

            if ($newMrp !== null) {
                $params = array_merge([$newPrice, $newMrp], $variantIds, [$concernId]);
                DB::query("UPDATE variants SET price = ?, mrp = ? WHERE id IN ({$placeholders}) AND concern_id = ?", $params);
            } else {
                $params = array_merge([$newPrice], $variantIds, [$concernId]);
                DB::query("UPDATE variants SET price = ? WHERE id IN ({$placeholders}) AND concern_id = ?", $params);
            }

            $count = count($variantIds);
            $this->flash('success', "Price updated to ₹" . number_format($newPrice, 2) . " for {$count} product(s).");
        } catch (\Throwable $e) {
            $this->flash('error', 'Unable to update prices: ' . $e->getMessage());
        }

        $this->redirect('/m/stocks');
    }

    public function bulkAssignComplementary(): void
    {
        ModuleGuard::enforce('stocks', 'edit');
        $this->validateCsrf();

        try {
            $concernId = $this->resolveStockActionConcern();

            $rawIds = $_POST['variant_ids'] ?? [];
            if (!is_array($rawIds) || empty($rawIds)) {
                throw new \InvalidArgumentException('No products selected.');
            }
            $variantIds = array_values(array_filter(
                array_map(fn($id) => filter_var($id, FILTER_VALIDATE_INT), $rawIds),
                fn($id) => $id !== false && $id > 0
            ));
            if (empty($variantIds)) {
                throw new \InvalidArgumentException('Invalid product selection.');
            }

            $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
            $params = array_merge($variantIds, [$concernId]);
            DB::query("UPDATE variants SET is_complementary = 1 WHERE id IN ({$placeholders}) AND concern_id = ?", $params);

            $count = count($variantIds);
            $this->flash('success', "{$count} product(s) marked as complementary.");
        } catch (\Throwable $e) {
            $this->flash('error', 'Unable to assign complementary: ' . $e->getMessage());
        }

        $this->redirect('/m/stocks');
    }

    public function bulkUnassignComplementary(): void
    {
        ModuleGuard::enforce('stocks', 'edit');
        $this->validateCsrf();

        try {
            $concernId = $this->resolveStockActionConcern();

            $rawIds = $_POST['variant_ids'] ?? [];
            if (!is_array($rawIds) || empty($rawIds)) {
                throw new \InvalidArgumentException('No products selected.');
            }
            $variantIds = array_values(array_filter(
                array_map(fn($id) => filter_var($id, FILTER_VALIDATE_INT), $rawIds),
                fn($id) => $id !== false && $id > 0
            ));
            if (empty($variantIds)) {
                throw new \InvalidArgumentException('Invalid product selection.');
            }

            $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
            $params = array_merge($variantIds, [$concernId]);
            DB::query("UPDATE variants SET is_complementary = 0 WHERE id IN ({$placeholders}) AND concern_id = ?", $params);

            $count = count($variantIds);
            $this->flash('success', "{$count} product(s) restored to normal (complementary flag removed).");
        } catch (\Throwable $e) {
            $this->flash('error', 'Unable to undo complementary: ' . $e->getMessage());
        }

        $this->redirect('/m/stocks');
    }

    private function resolveStockActionConcern(): int
    {
        $rawConcernId = $_POST['concern_id'] ?? null;
        $postedConcernId = is_scalar($rawConcernId)
            ? filter_var($rawConcernId, FILTER_VALIDATE_INT)
            : false;
        if (Auth::isSuperAdmin()) {
            if ($postedConcernId === false || $postedConcernId < 1) {
                throw new \InvalidArgumentException('Select a concern for this stock action.');
            }
            return $postedConcernId;
        }

        $concernId = (int)(Auth::concernId() ?? 0);
        if (
            $concernId < 1 ||
            ($postedConcernId !== false && $postedConcernId > 0 && $postedConcernId !== $concernId)
        ) {
            throw new \InvalidArgumentException('This stock line is outside your concern.');
        }
        return $concernId;
    }

    private function stockAdjustmentModule(string $latestInboundModule, ?int $concernId = null, ?int $variantId = null): string
    {
        if (in_array($latestInboundModule, ['Production Inward', 'Production Stock Adjustment', 'Production QC'], true)) {
            return 'Production Stock Adjustment';
        }
        if (in_array($latestInboundModule, ['Purchase Inward', 'Purchase Inward Item', 'Purchase Stock Adjustment'], true)) {
            return 'Purchase Stock Adjustment';
        }
        if ($concernId && $variantId) {
            $hasProd = DB::fetchValue(
                "SELECT 1 FROM stock_ledger WHERE concern_id = ? AND variant_id = ? AND ref_module IN ('Production QC', 'Production Inward', 'Production Stock Adjustment') LIMIT 1",
                [$concernId, $variantId]
            );
            if ($hasProd) {
                return 'Production Stock Adjustment';
            }
            $hasPurch = DB::fetchValue(
                "SELECT 1 FROM stock_ledger WHERE concern_id = ? AND variant_id = ? AND ref_module IN ('Purchase Inward', 'Purchase Inward Item', 'Purchase Stock Adjustment') LIMIT 1",
                [$concernId, $variantId]
            );
            if ($hasPurch) {
                return 'Purchase Stock Adjustment';
            }
        }
        throw new \RuntimeException('Only own-product and purchase-product stock can be adjusted here.');
    }

    public function ledger(): void {
        $cid = Auth::concernId();

        $sql = "SELECT sl.*, v.name AS variant_name, v.product_code, v.weight_unit, u.name AS user_name,
                       con.name AS concern_name, con.code AS concern_code,
                       COALESCE(stk.current_stock, 0) AS current_stock
                FROM stock_ledger sl
                JOIN variants v ON sl.variant_id = v.id
                LEFT JOIN concerns con ON sl.concern_id = con.id
                LEFT JOIN users u ON sl.created_by = u.id
                LEFT JOIN (
                    SELECT concern_id, variant_id, COALESCE(SUM(CASE WHEN qty > 0 THEN qty ELSE 0 END), 0) AS current_stock
                    FROM stocks
                    GROUP BY concern_id, variant_id
                ) stk ON stk.concern_id = sl.concern_id AND stk.variant_id = sl.variant_id";
        
        $params = [];
        if ($cid) {
            $sql .= " WHERE sl.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY sl.id DESC";
        $ledger = DB::fetchAll($sql, $params);
        $unifiedCodes = [];
        foreach ($ledger as &$entry) {
            $variantId = (int)$entry['variant_id'];
            if (!array_key_exists($variantId, $unifiedCodes)) {
                $unifiedCodes[$variantId] = \App\Services\QrService::formatBrandVariantCode($variantId) ?: ($entry['product_code'] ?? '');
            }
            $entry['product_code'] = $unifiedCodes[$variantId];
        }
        unset($entry);

        $this->render('concern/stock_ledger', [
            'pageTitle'     => 'Stock Movement Ledger (Audit Trail)',
            'currentModule' => 'stocks',
            'ledger'        => $ledger,
            'selectedCid'   => $cid,
        ]);
    }

    public function exportStocks(): void {
        self::ensureVariantSchema();
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT s.*, v.name AS variant_name, v.product_code, v.size, v.weight, v.weight_unit, v.cost_price, v.price, v.mrp,
                       COALESCE(c.name, 'General') AS category_name, COALESCE(sub.name, ssc.name, '-') AS subcategory_name,
                       con.name AS concern_name, con.code AS concern_code
                FROM stocks s
                JOIN variants v ON s.variant_id = v.id
                LEFT JOIN catalogs cat ON v.catalog_id = cat.id
                LEFT JOIN concerns con ON s.concern_id = con.id
                LEFT JOIN categories c ON COALESCE(v.category_id, cat.category_id) = c.id
                LEFT JOIN subcategories sub ON COALESCE(v.subcategory_id, cat.subcategory_id) = sub.id
                LEFT JOIN sub_subcategories ssc ON cat.sub_sub_category_id = ssc.id";
        
        $params = [];
        $where = [
            "s.qty > 0",
            "s.location NOT LIKE '%:DAMAGED%'",
            "s.location NOT LIKE '%:QUARANTINE%'",
            "s.location NOT LIKE '%:RESERVED%'",
            "s.location NOT LIKE '%:IN_TRANSIT%'"
        ];
        if ($cid) {
            $where[] = "s.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(s.updated_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY s.qty DESC";
        $stocks = DB::fetchAll($sql, $params);

        $headers = [
            'Product Code', 'Variant Name', 'Category', 'Subcategory', 'Quantity', 'Unit', 
            'Cost Price (₹)', 'Selling Price (₹)', 'Selling MRP (₹)', 'Cost Valuation (₹)', 'Concern'
        ];

        $rows = [];
        $totalQty = 0;
        $totalVal = 0;
        foreach ($stocks as $s) {
            $val = (float)$s['qty'] * (float)$s['price'];
            $totalQty += (float)$s['qty'];
            $totalVal += $val;
            $rows[] = [
                $s['product_code'],
                $s['variant_name'],
                $s['category_name'] ?? 'General',
                $s['subcategory_name'] ?? '-',
                (float)$s['qty'],
                $s['weight_unit'] ?: 'Pcs',
                '₹' . number_format((float)$s['cost_price'], 2),
                '₹' . number_format((float)$s['price'], 2),
                '₹' . number_format((float)$s['mrp'], 2),
                '₹' . number_format($val, 2),
                $s['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total', '', '', '', $totalQty, '', '', '', '', '₹' . number_format($totalVal, 2), ''];
        $metadata = [
            'Report Title' => 'Current Live Stock Balances & Valuation',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Products' => count($stocks),
            'Total Units' => number_format($totalQty, 2),
            'Total Cost Valuation' => '₹' . number_format($totalVal, 2)
        ];

        $filename = 'stocks_report_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Stocks Inventory & Valuation Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Stocks Inventory & Valuation Report', $metadata, $headers, $rows, $totals);
        }
    }

    public function exportLedger(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT sl.*, v.name AS variant_name, v.product_code, v.weight_unit, u.name AS user_name,
                       con.name AS concern_name, con.code AS concern_code,
                       COALESCE(stk.current_stock, 0) AS current_stock
                FROM stock_ledger sl
                JOIN variants v ON sl.variant_id = v.id
                LEFT JOIN concerns con ON sl.concern_id = con.id
                LEFT JOIN users u ON sl.created_by = u.id
                LEFT JOIN (
                    SELECT concern_id, variant_id, COALESCE(SUM(CASE WHEN qty > 0 THEN qty ELSE 0 END), 0) AS current_stock
                    FROM stocks
                    GROUP BY concern_id, variant_id
                ) stk ON stk.concern_id = sl.concern_id AND stk.variant_id = sl.variant_id";

        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "sl.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(sl.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY sl.id DESC";
        $ledger = DB::fetchAll($sql, $params);
        $unifiedCodes = [];

        $rows = [];
        $totIn = 0;
        $totOut = 0;
        foreach ($ledger as $l) {
            $variantId = (int)$l['variant_id'];
            if (!array_key_exists($variantId, $unifiedCodes)) {
                $unifiedCodes[$variantId] = \App\Services\QrService::formatBrandVariantCode($variantId) ?: ($l['product_code'] ?? '');
            }
            $in = (float)($l['qty_in'] ?? 0);
            $out = (float)($l['qty_out'] ?? 0);
            $totIn += $in;
            $totOut += $out;
            $rows[] = [
                '#' . $l['id'],
                date('d M Y, h:i A', strtotime($l['created_at'])),
                $unifiedCodes[$variantId],
                $l['variant_name'],
                $l['ref_module'] ?: 'Movement',
                $in,
                $out,
                $l['ref_id'] ? '#' . $l['ref_id'] : '-',
                $l['note'] ?: '-',
                $l['user_name'] ?? 'System',
                $l['concern_name'] ?? 'Consolidated'
            ];
        }

        $headers = [
            'ID', 'Date & Time', 'Product Code', 'Variant Name', 'Module',
            'Qty In (+)', 'Qty Out (-)', 'Ref ID', 'Notes', 'User', 'Concern'
        ];

        $totals = ['Total: ' . count($ledger) . ' Records', '', '', '', '', $totIn, $totOut, '', '', '', ''];
        $metadata = [
            'Report Title' => 'Stock Movement Ledger (Audit Trail)',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Transactions' => count($ledger)
        ];

        $filename = 'stock_ledger_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Stock Movement Ledger Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Stock Movement Ledger Report', $metadata, $headers, $rows, $totals);
        }
    }
}
