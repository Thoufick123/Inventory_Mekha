<?php
// app/controllers/LabelController.php - Rebuilt Label Generation (4 Sections: Purchase Raw, Purchase FG, Production, Return)

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Core\ModuleGuard;
use App\Models\Variant;
use App\Services\QrService;
use App\Services\AuditLog;
use App\Services\StockService;
use App\Services\RollRegistryService;
use App\Services\PurchaseBillSourceService;
use App\Services\PurchaseStockItemOverrideService;
use Exception;

class LabelController extends Controller {
    public function __construct() {
        $this->ensureStitchingInwardSchema();
    }

    /**
     * Ensure schema tables and columns for cutting and stitching inward tracking
     */
    protected function ensureStitchingInwardSchema(): void {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            DB::query("
                CREATE TABLE IF NOT EXISTS `cutting_items` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `cutting_id` INT NOT NULL,
                    `program_roll_id` INT NULL,
                    `variant_id` INT NULL,
                    `size` VARCHAR(50) NULL,
                    `sku_code` VARCHAR(100) NULL,
                    `variant_name` VARCHAR(255) NULL,
                    `cut_pieces` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_ci_cutting` (`cutting_id`),
                    KEY `idx_ci_roll` (`program_roll_id`),
                    KEY `idx_ci_variant` (`variant_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            DB::query("
                CREATE TABLE IF NOT EXISTS `stitching_items` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `stitching_id` INT NOT NULL,
                    `cutting_item_id` INT NULL,
                    `catalog_id` INT NULL,
                    `variant_id` INT NULL,
                    `size` VARCHAR(50) NULL,
                    `color` VARCHAR(100) NULL,
                    `sku_code` VARCHAR(100) NULL,
                    `variant_name` VARCHAR(255) NULL,
                    `cut_qty` INT NOT NULL DEFAULT 0,
                    `stitching_qty` INT NOT NULL DEFAULT 0,
                    `per_pc_weight` DECIMAL(8,3) DEFAULT 0.000,
                    `total_weight` DECIMAL(12,2) DEFAULT 0.00,
                    `base_price` DECIMAL(12,2) DEFAULT 0.00,
                    `discount_price` DECIMAL(12,2) DEFAULT 0.00,
                    `inward_status` VARCHAR(30) DEFAULT 'pending',
                    `inward_at` DATETIME NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_si_stitching` (`stitching_id`),
                    KEY `idx_si_variant` (`variant_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $cols = array_column(DB::fetchAll("SHOW COLUMNS FROM stitching_items"), 'Field');
            if (!in_array('inward_status', $cols)) {
                DB::query("ALTER TABLE stitching_items ADD COLUMN inward_status VARCHAR(30) DEFAULT 'pending'");
            }
            if (!in_array('inward_at', $cols)) {
                DB::query("ALTER TABLE stitching_items ADD COLUMN inward_at DATETIME NULL");
            }

            $logCols = array_column(DB::fetchAll("SHOW COLUMNS FROM stitching_logs"), 'Field');
            if (!in_array('inward_status', $logCols)) {
                DB::query("ALTER TABLE stitching_logs ADD COLUMN inward_status VARCHAR(30) DEFAULT 'pending'");
            }
        } catch (\Throwable $e) {
            // Ignore if schema already present or DB error
        }
    }

    /**
     * Display the 3-Section Label Generation Hub
     */
    public function index(): void {
        PurchaseStockItemOverrideService::initTable();
        $cid = Auth::concernId();
        $variantModel = new Variant();
        $allVariants = $variantModel->allWithDetails();

        $activeBillId = (int)($_GET['bill_id'] ?? 0);
        $activeTab    = trim($_GET['tab'] ?? 'purchase_raw');
        if (!in_array($activeTab, ['purchase_raw', 'purchase_fg', 'production'], true)) {
            $activeTab = 'purchase_raw';
        }

        $activeBill = null;
        if ($activeBillId > 0) {
            $bSql = "SELECT pb.*, s.name AS supplier_name, s.company_name AS supplier_company 
                     FROM purchase_bills pb 
                     LEFT JOIN suppliers s ON pb.supplier_id = s.id 
                     WHERE pb.id = ?";
            $bParams = [$activeBillId];
            if ($cid) {
                $bSql .= " AND pb.concern_id = ?";
                $bParams[] = $cid;
            }
            $activeBill = DB::fetch($bSql, $bParams);
        }

        $activeSubTab = trim($_GET['subtab'] ?? '');

        // 1a. Purchase Fabric Rolls (Rolls only)
        $rollSql = "SELECT pbi.*, pb.bill_no, pb.bill_date, pb.supplier_invoice_no, pb.inward_status AS bill_inward_status,
                           COALESCE(pbi.inward_status, pb.inward_status) AS inward_status,
                           s.name AS supplier_name, s.company_name AS supplier_company
                    FROM purchase_bill_items pbi
                    JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                    LEFT JOIN suppliers s ON pb.supplier_id = s.id
                    WHERE pbi.section_type = 'rolls'";
        $rollParams = [];
        if ($cid) {
            $rollSql .= " AND pbi.concern_id = ?";
            $rollParams[] = $cid;
        }
        if ($activeBillId > 0) {
            $rollSql .= " ORDER BY (pb.id = {$activeBillId}) DESC, pbi.id DESC LIMIT 100";
        } else {
            $rollSql .= " ORDER BY pbi.id DESC LIMIT 100";
        }
        $purchaseRollItems = PurchaseStockItemOverrideService::applyToItems(DB::fetchAll($rollSql, $rollParams));

        // 1b. Purchase Other Garment Supplies & Trims (Trims, Accessories, Threads)
        $otherSql = "SELECT pbi.*, pb.bill_no, pb.bill_date, pb.supplier_invoice_no, pb.inward_status AS bill_inward_status,
                            COALESCE(pbi.inward_status, pb.inward_status) AS inward_status,
                            s.name AS supplier_name, s.company_name AS supplier_company
                     FROM purchase_bill_items pbi
                     JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                     LEFT JOIN suppliers s ON pb.supplier_id = s.id
                     WHERE pbi.section_type = 'garment_other'";
        $otherParams = [];
        if ($cid) {
            $otherSql .= " AND pbi.concern_id = ?";
            $otherParams[] = $cid;
        }
        if ($activeBillId > 0) {
            $otherSql .= " ORDER BY (pb.id = {$activeBillId}) DESC, pbi.id DESC LIMIT 100";
        } else {
            $otherSql .= " ORDER BY pbi.id DESC LIMIT 100";
        }
        $purchaseOtherItems = PurchaseStockItemOverrideService::applyToItems(DB::fetchAll($otherSql, $otherParams));

        // Determine default subtab
        if (!in_array($activeSubTab, ['rolls', 'garment_other'], true)) {
            $activeSubTab = (!empty($purchaseRollItems) || empty($purchaseOtherItems)) ? 'rolls' : 'garment_other';
        }

        $purchaseRawItems = array_merge($purchaseRollItems, $purchaseOtherItems);

        // 2. Purchase Finished Goods (Ready-made garments from vendors, mapped to catalog)
        $fgSql = "SELECT pbi.*, pb.bill_no, pb.bill_date, pb.supplier_invoice_no, pb.inward_status AS bill_inward_status,
                         COALESCE(pbi.inward_status, pb.inward_status) AS inward_status,
                         s.name AS supplier_name, s.company_name AS supplier_company,
                         fg_concern.name AS item_concern_name,
                         v.sku_code, v.name AS variant_name, v.size AS variant_size, v.catalog_id AS variant_catalog_id,
                         v.price AS variant_price, v.mrp AS variant_mrp, v.attributes AS variant_attributes,
                         v.category_id, v.subcategory_id,
                         c.name AS category_name, c.code AS category_code,
                         sc.name AS subcategory_name, sc.code AS subcategory_code,
                         COALESCE(NULLIF(fg_catalog.main_category_code, ''), c.code) AS main_category_code,
                         COALESCE(NULLIF(fg_catalog.sub_category_code, ''), sc.code) AS sub_category_code,
                         COALESCE(NULLIF(fg_catalog.sub_sub_category_code, ''), fg_subsub.code) AS sub_sub_category_code,
                         fg_subsub.name AS sub_subcategory_name,
                         b.name AS brand_name
                  FROM purchase_bill_items pbi
                  JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                  LEFT JOIN suppliers s ON pb.supplier_id = s.id
                  LEFT JOIN concerns fg_concern ON fg_concern.id = pbi.concern_id
                  LEFT JOIN variants v ON pbi.variant_id = v.id
                  LEFT JOIN categories c ON v.category_id = c.id
                  LEFT JOIN subcategories sc ON v.subcategory_id = sc.id
                  LEFT JOIN catalogs fg_catalog ON fg_catalog.id = v.catalog_id
                  LEFT JOIN sub_subcategories fg_subsub ON fg_subsub.id = fg_catalog.sub_sub_category_id
                  LEFT JOIN brands b ON v.brand_id = b.id
                  WHERE pbi.section_type = 'finished_goods'";
        $fgParams = [];
        if ($cid) {
            $fgSql .= " AND pbi.concern_id = ?";
            $fgParams[] = $cid;
        }
        if ($activeBillId > 0) {
            $fgSql .= " ORDER BY (pb.id = {$activeBillId}) DESC, pbi.id DESC LIMIT 100";
        } else {
            $fgSql .= " ORDER BY pbi.id DESC LIMIT 100";
        }
        $purchaseFgItems = PurchaseStockItemOverrideService::applyToItems(DB::fetchAll($fgSql, $fgParams));
        $fgCatalogIds = [];
        foreach ($purchaseFgItems as $fgItem) {
            $catalogId = (int) ($fgItem['catalog_id'] ?? 0);
            if ($catalogId <= 0) {
                $catalogId = (int) ($fgItem['variant_catalog_id'] ?? 0);
            }
            if ($catalogId > 0) {
                $fgCatalogIds[$catalogId] = $catalogId;
            }
        }
        $fgVariantsByCatalog = [];
        if ($fgCatalogIds) {
            $catalogIdList = array_values($fgCatalogIds);
            $catalogPlaceholders = implode(',', array_fill(0, count($catalogIdList), '?'));
            $variantParams = $catalogIdList;
            $variantWhere = "catalog_id IN ({$catalogPlaceholders}) AND status = 'active'";
            if ($cid) {
                array_unshift($variantParams, $cid);
                $variantWhere = "concern_id = ? AND {$variantWhere}";
            }
            $mappedVariants = DB::fetchAll(
                "SELECT catalog_id, sku_code, size, attributes
                 FROM variants
                 WHERE {$variantWhere}
                 ORDER BY catalog_id ASC, id ASC",
                $variantParams
            );
            foreach ($mappedVariants as $mappedVariant) {
                $variantData = json_decode((string) ($mappedVariant['attributes'] ?? ''), true);
                $variantData = is_array($variantData) ? $variantData : [];
                $allocatedQty = max(0, (int) round((float) ($variantData['allocated_qty'] ?? 0)));
                $details = [];
                $color = trim((string) ($variantData['color'] ?? ''));
                $size = trim((string) ($mappedVariant['size'] ?? ''));
                if ($color !== '') {
                    $details[] = $color;
                }
                if ($size !== '') {
                    $details[] = $size;
                }
                $customAttributes = [];
                foreach ($variantData as $attributeType => $attributeValue) {
                    $attributeType = strtolower(trim((string) $attributeType));
                    if (
                        $attributeType === '' ||
                        in_array($attributeType, ['color', 'size', 'hsn', 'weight', 'allocated_qty', 'custom_attributes'], true) ||
                        !is_scalar($attributeValue)
                    ) {
                        continue;
                    }
                    $customAttributes[$attributeType] = trim((string) $attributeValue);
                }
                if (is_array($variantData['custom_attributes'] ?? null)) {
                    $customAttributes = array_merge($customAttributes, $variantData['custom_attributes']);
                }
                foreach ($customAttributes as $attributeType => $attributeValue) {
                    if (!is_scalar($attributeValue)) {
                        continue;
                    }
                    $attributeValue = trim((string) $attributeValue);
                    if ($attributeValue !== '') {
                        $details[] = ucwords(str_replace(['_', '-'], ' ', (string) $attributeType)) . ' ' . $attributeValue;
                    }
                }
                $fgVariantsByCatalog[(int) $mappedVariant['catalog_id']][] = [
                    'details' => implode(' / ', $details),
                    'allocated_qty' => $allocatedQty,
                ];
            }
        }
        $fgVariantSummaries = [];
        foreach ($fgVariantsByCatalog as $catalogId => $variants) {
            $hasAllocations = array_sum(array_column($variants, 'allocated_qty')) > 0;
            foreach ($variants as $variant) {
                $quantity = $hasAllocations ? $variant['allocated_qty'] : 1;
                $fgVariantSummaries[$catalogId][] = $variant['details'] . ' ×' . $quantity;
            }
        }
        foreach ($purchaseFgItems as &$fgItem) {
            $catalogId = (int) ($fgItem['catalog_id'] ?? 0);
            if ($catalogId <= 0) {
                $catalogId = (int) ($fgItem['variant_catalog_id'] ?? 0);
            }
            $variantSummary = $fgVariantSummaries[$catalogId] ?? [];
            if (!$variantSummary) {
                $fallbackDetails = array_filter([
                    trim((string) ($fgItem['color'] ?? '')),
                    trim((string) ($fgItem['variant_size'] ?? '')),
                ]);
                $variantSummary = [implode(' / ', $fallbackDetails) . ' ×' . max(0, (int) ($fgItem['qty'] ?? 0))];
            }
            $fgItem['mapped_variant_summary'] = implode(' · ', $variantSummary);
        }
        unset($fgItem);

        // 3. Production Labels (After-Stitching assembled garments)
        $this->ensureStitchingInwardSchema();
        $prodSql = "SELECT si.id,
                           si.id AS stitching_item_id,
                           si.stitching_id,
                           si.variant_id,
                           si.variant_id AS produced_variant_id,
                           si.sku_code,
                           si.variant_name,
                           si.size AS variant_size,
                           si.color AS variant_color,
                           si.stitching_qty AS stitched_pieces,
                           si.per_pc_weight,
                           si.base_price AS variant_mrp,
                           si.discount_price AS variant_price,
                           si.inward_status,
                           sl.id AS stitching_log_id,
                           sl.batch_no AS log_no,
                           sl.batch_no,
                           sl.stitching_name,
                           sl.status AS batch_status,
                           sl.concern_id,
                           sl.created_at,
                           pp.program_no,
                           pp.program_name,
                           COALESCE(cat.id, pp.catalog_id) AS catalog_id,
                           cat.name AS catalog_name,
                           cat.style_code AS catalog_code,
                           c.name AS category_name, c.code AS category_code,
                           sc.name AS subcategory_name, sc.code AS subcategory_code,
                           COALESCE(NULLIF(vcat.main_category_code, ''), vc.code) AS main_category_code,
                           COALESCE(NULLIF(vcat.sub_category_code, ''), vsc.code) AS sub_category_code,
                           COALESCE(NULLIF(vcat.sub_sub_category_code, ''), vssc.code) AS sub_sub_category_code,
                           vssc.name AS sub_subcategory_name,
                           vc.name AS variant_category_name,
                           vsc.name AS variant_subcategory_name,
                           vssc.name AS variant_sub_subcategory_name,
                           b.name AS brand_name
                    FROM stitching_items si
                    JOIN stitching_logs sl ON si.stitching_id = sl.id
                    LEFT JOIN production_programs pp ON sl.program_id = pp.id
                    LEFT JOIN cutting_items ci ON si.cutting_item_id = ci.id
                    LEFT JOIN program_rolls pr ON ci.program_roll_id = pr.id
                    LEFT JOIN catalogs cat ON (si.catalog_id = cat.id OR pp.catalog_id = cat.id OR pr.catalog_id = cat.id)
                    LEFT JOIN categories c ON cat.category_id = c.id
                    LEFT JOIN subcategories sc ON cat.subcategory_id = sc.id
                    LEFT JOIN variants v ON si.variant_id = v.id
                    LEFT JOIN catalogs vcat ON vcat.id = v.catalog_id
                    LEFT JOIN categories vc ON vc.id = v.category_id
                    LEFT JOIN subcategories vsc ON vsc.id = v.subcategory_id
                    LEFT JOIN sub_subcategories vssc ON vssc.id = vcat.sub_sub_category_id
                    LEFT JOIN brands b ON v.brand_id = b.id
                    WHERE (sl.status IS NULL OR sl.status != 'cancelled')";
        $prodParams = [];
        if ($cid) {
            $prodSql .= " AND (sl.concern_id = ? OR sl.concern_id = 1 OR sl.concern_id IS NULL)";
            $prodParams[] = $cid;
        }
        $prodSql .= " ORDER BY si.id DESC LIMIT 200";
        $productionItems = DB::fetchAll($prodSql, $prodParams);

        // Fallback for legacy single-variant stitching logs without stitching_items breakdown
        $legacySql = "SELECT sl.id,
                             NULL AS stitching_item_id,
                             sl.id AS stitching_id,
                             sl.produced_variant_id AS variant_id,
                             sl.produced_variant_id,
                             v.sku_code, v.name AS variant_name, v.size AS variant_size,
                             v.price AS variant_price, v.mrp AS variant_mrp,
                             sl.inward_status,
                             sl.id AS stitching_log_id,
                             sl.batch_no AS log_no,
                             sl.batch_no,
                             sl.stitching_name,
                             sl.status AS batch_status,
                             sl.concern_id,
                             sl.created_at,
                             sl.stitched_pieces,
                             pp.program_no,
                             pp.program_name,
                             c.name AS category_name, c.code AS category_code,
                             sc.name AS subcategory_name, sc.code AS subcategory_code,
                             COALESCE(NULLIF(cat.main_category_code, ''), c.code) AS main_category_code,
                             COALESCE(NULLIF(cat.sub_category_code, ''), sc.code) AS sub_category_code,
                             COALESCE(NULLIF(cat.sub_sub_category_code, ''), ssc.code) AS sub_sub_category_code,
                             ssc.name AS sub_subcategory_name,
                             c.name AS variant_category_name,
                             sc.name AS variant_subcategory_name,
                             ssc.name AS variant_sub_subcategory_name,
                             b.name AS brand_name
                      FROM stitching_logs sl
                      JOIN variants v ON sl.produced_variant_id = v.id
                      LEFT JOIN production_programs pp ON sl.program_id = pp.id
                      LEFT JOIN categories c ON v.category_id = c.id
                      LEFT JOIN subcategories sc ON v.subcategory_id = sc.id
                      LEFT JOIN catalogs cat ON cat.id = v.catalog_id
                      LEFT JOIN sub_subcategories ssc ON ssc.id = cat.sub_sub_category_id
                      LEFT JOIN brands b ON v.brand_id = b.id
                      WHERE sl.id NOT IN (SELECT DISTINCT stitching_id FROM stitching_items)";
        $legacyParams = [];
        if ($cid) {
            $legacySql .= " AND (sl.concern_id = ? OR sl.concern_id = 1 OR sl.concern_id IS NULL)";
            $legacyParams[] = $cid;
        }
        $legacySql .= " ORDER BY sl.id DESC LIMIT 50";
        $legacyItems = DB::fetchAll($legacySql, $legacyParams);
        if (!empty($legacyItems)) {
            $productionItems = array_merge($productionItems, $legacyItems);
        }

        foreach ($productionItems as &$pItem) {
            $pItem['is_in_stock'] = (($pItem['inward_status'] ?? '') === 'completed');
        }
        unset($pItem);

        // Group production items by stitching batch for multi-variant and batch printing
        $productionBatches = [];
        foreach ($productionItems as $pItem) {
            $bId = (int)($pItem['stitching_id'] ?: ($pItem['stitching_log_id'] ?: $pItem['id']));
            if (!isset($productionBatches[$bId])) {
                $productionBatches[$bId] = [
                    'id'               => $bId,
                    'stitching_id'     => $bId,
                    'batch_no'         => $pItem['batch_no'] ?? ($pItem['log_no'] ?? ('STCH-' . $bId)),
                    'log_no'           => $pItem['log_no'] ?? ($pItem['batch_no'] ?? ('STCH-' . $bId)),
                    'stitching_name'   => $pItem['stitching_name'] ?? '',
                    'program_no'       => $pItem['program_no'] ?: 'Standard Program',
                    'program_name'     => $pItem['program_name'] ?? '',
                    'catalog_id'       => $pItem['catalog_id'] ?? null,
                    'catalog_name'     => $pItem['catalog_name'] ?? ($pItem['variant_name'] ?? 'In-House Garment'),
                    'catalog_code'     => $pItem['catalog_code'] ?? '',
                    'category_name'    => $pItem['category_name'] ?? 'Apparel',
                    'subcategory_name' => $pItem['subcategory_name'] ?? '',
                    'brand_name'       => $pItem['brand_name'] ?? '',
                    'created_at'       => $pItem['created_at'] ?? date('Y-m-d H:i:s'),
                    'total_pieces'     => 0,
                    'is_all_in_stock'  => true,
                    'has_in_stock'     => false,
                    'items'            => []
                ];
            }
            $pcs = (int)($pItem['stitched_pieces'] ?? ($pItem['stitching_qty'] ?? 1));
            $productionBatches[$bId]['total_pieces'] += $pcs;
            if (empty($pItem['is_in_stock'])) {
                $productionBatches[$bId]['is_all_in_stock'] = false;
            } else {
                $productionBatches[$bId]['has_in_stock'] = true;
            }
            $productionBatches[$bId]['items'][] = $pItem;
        }

        // Check which stitching batches already have labels generated in label_jobs
        $labeledStitchingJobs = DB::fetchAll(
            "SELECT ref_id, COUNT(*) AS job_count, SUM(qty) AS total_printed, MAX(created_at) AS last_printed_at, MAX(id) AS latest_job_id
             FROM label_jobs 
             WHERE label_type = 'production' AND ref_id IS NOT NULL 
             GROUP BY ref_id"
        );
        $labeledBatchMap = [];
        foreach ($labeledStitchingJobs as $lj) {
            $labeledBatchMap[(int)$lj['ref_id']] = $lj;
        }

        foreach ($productionBatches as &$pb) {
            $sId = (int)$pb['stitching_id'];
            if (isset($labeledBatchMap[$sId])) {
                $pb['labels_already_generated'] = true;
                $pb['label_job_info'] = $labeledBatchMap[$sId];
            } else {
                $pb['labels_already_generated'] = false;
                $pb['label_job_info'] = null;
            }
        }
        unset($pb);

        // 4. Recent Label Jobs (Audit Trail)
        $jobSql = "SELECT lj.*, u.name AS user_name, con.name AS concern_name, con.code AS concern_code,
                          v.name AS variant_name, v.sku_code, v.category_id, v.subcategory_id
                   FROM label_jobs lj
                   LEFT JOIN variants v ON lj.variant_id = v.id
                   LEFT JOIN concerns con ON lj.concern_id = con.id
                   LEFT JOIN users u ON lj.printed_by = u.id";
        $jobParams = [];
        if ($cid) {
            $jobSql .= " WHERE lj.concern_id = ?";
            $jobParams[] = $cid;
        }
        $jobSql .= " ORDER BY lj.id DESC LIMIT 15";
        $recentJobs = DB::fetchAll($jobSql, $jobParams);

        $this->render('concern/label_generation', [
            'pageTitle'          => 'Barcode & QR Label Generation',
            'currentModule'      => 'label_generation',
            'purchaseRollItems'  => $purchaseRollItems,
            'purchaseOtherItems' => $purchaseOtherItems,
            'purchaseRawItems'   => $purchaseRawItems,
            'purchaseFgItems'    => $purchaseFgItems,
            'productionItems'    => $productionItems,
            'productionBatches'  => array_values($productionBatches),
            'allVariants'        => $allVariants,
            'recentJobs'         => $recentJobs,
            'selectedCid'        => $cid,
            'activeBillId'       => $activeBillId,
            'activeBill'         => $activeBill,
            'activeTab'          => $activeTab,
            'activeSubTab'       => $activeSubTab,
        ]);
    }

    /**
     * AJAX endpoint to map an unmapped finished goods purchase item to a catalog variant
     */
    public function mapVariant(): void {
        $this->validateCsrf();
        $itemId    = (int)($_POST['item_id'] ?? 0);
        $variantId = (int)($_POST['variant_id'] ?? 0);
        $cid       = Auth::concernId();

        if (!$itemId || !$variantId) {
            $this->renderJson(['success' => false, 'message' => 'Invalid item or variant selected.'], 400);
            return;
        }

        // Verify variant belongs to tenant
        $variant = null;
        if ($cid) {
            $variant = DB::fetch("SELECT * FROM variants WHERE id = ? AND concern_id = ?", [$variantId, $cid]);
        } else {
            $variant = DB::fetch("SELECT * FROM variants WHERE id = ?", [$variantId]);
        }

        if (!$variant) {
            $this->renderJson(['success' => false, 'message' => 'Variant not found or access denied.'], 404);
            return;
        }

        $sql = "UPDATE purchase_bill_items SET variant_id = ? WHERE id = ?";
        $params = [$variantId, $itemId];
        if ($cid) {
            $sql .= " AND concern_id = ?";
            $params[] = $cid;
        }
        DB::query($sql, $params);

        $variantCode = QrService::generateFinishedGoodsCode((int)$variant['id']);
        if ($variantCode === null) {
            $this->renderJson(['success' => false, 'message' => 'Could not generate a code for the saved variant.'], 500);
            return;
        }

        $this->renderJson([
            'success'      => true,
            'message'      => "Item mapped to '{$variant['name']}' ({$variant['sku_code']}) successfully!",
            'variant_name' => $variant['name'],
            'sku_code'     => $variant['sku_code'],
            'variant_id'   => (int)$variant['id'],
            'variant_code' => $variantCode,
            'mrp'          => $variant['mrp'],
            'size'         => $variant['size'],
            'qr'           => $variantCode
        ]);
    }

    /**
     * Generate & render printable label sheet for any of the 3 sections
     */
    public function generate(): void {
        $this->validateCsrf();
        $this->renderGeneratedLabels(false);
    }

    public function generateProductAddingLabels(): void
    {
        $this->validateCsrf();
        PurchaseBillSourceService::ensureSchema();
        PurchaseStockItemOverrideService::initTable();

        $billId = (int)($_POST['bill_id'] ?? 0);
        $requestedLabelCounts = $_POST['label_counts'] ?? [];
        $rawItemIds = $_POST['item_ids'] ?? [];
        if (!is_array($rawItemIds)) {
            $rawItemIds = [];
        }
        if (isset($_POST['item_id'])) {
            $rawItemIds[] = $_POST['item_id'];
        }
        $itemIds = array_values(array_unique(array_filter(
            array_map('intval', $rawItemIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($billId <= 0 || !$itemIds || count($itemIds) > 100) {
            $this->failProductAddingLabel($billId, 'Choose one or more valid Product Adding lines to print labels.');
            return;
        }
        if (!is_array($requestedLabelCounts)) {
            $this->failProductAddingLabel($billId, 'Label counts must be provided as a list of line counts.');
            return;
        }

        $cid = Auth::concernId();
        $billSql = "SELECT pb.*, s.name AS supplier_name
                    FROM purchase_bills pb
                    LEFT JOIN suppliers s ON s.id = pb.supplier_id
                    WHERE pb.id = ? AND pb.source_module = 'product_adding'";
        $billParams = [$billId];
        if ($cid) {
            $billSql .= " AND pb.concern_id = ?";
            $billParams[] = $cid;
        }
        $bill = DB::fetch($billSql, $billParams);
        if (!$bill) {
            $this->failProductAddingLabel($billId, 'Product Adding bill not found or access denied.');
            return;
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $items = DB::fetchAll(
            "SELECT pbi.*, pb.bill_no, pb.bill_date, s.name AS supplier_name,
                    fg_concern.name AS item_concern_name,
                    v.sku_code, v.name AS variant_name, v.size AS variant_size,
                    v.price AS variant_price, v.mrp AS variant_mrp, v.attributes AS variant_attributes
             FROM purchase_bill_items pbi
             JOIN purchase_bills pb ON pb.id = pbi.purchase_bill_id
             LEFT JOIN suppliers s ON s.id = pb.supplier_id
             LEFT JOIN concerns fg_concern ON fg_concern.id = pbi.concern_id
             LEFT JOIN variants v ON v.id = pbi.variant_id
             WHERE pbi.purchase_bill_id = ? AND pbi.concern_id = ?
               AND pbi.id IN ({$placeholders})
             ORDER BY pbi.id ASC",
            array_merge([$billId, (int)$bill['concern_id']], $itemIds)
        );
        if (count($items) !== count($itemIds)) {
            $this->failProductAddingLabel($billId, 'One or more selected lines do not belong to this Product Adding bill.');
            return;
        }

        $items = PurchaseStockItemOverrideService::applyToItems($items, false);
        $multiLabels = [];
        $totalCopies = 0;
        $totalBillLines = count($items);
        $labelJobs = [];
        $generatedItemCounts = [];
        $concern = DB::fetch('SELECT name FROM concerns WHERE id = ?', [(int)$bill['concern_id']]);
        $concernName = $concern['name'] ?? 'GRM Inventory';
        $labelData = null;
        $batchNo = (string)$bill['bill_no'];

        foreach ($items as $item) {
            $defaultCopies = (int)ceil((float)$item['qty']);
            $rawCopies = $requestedLabelCounts[(int)$item['id']] ?? $defaultCopies;
            if (!is_scalar($rawCopies) || !preg_match('/^\d+$/D', (string)$rawCopies)) {
                $this->failProductAddingLabel($billId, "Enter a whole-number label count for '{$item['item_name']}'.");
                return;
            }
            $copies = (int)$rawCopies;
            if ($copies < 1 || $copies > 500 || $totalCopies + $copies > 2000) {
                $this->failProductAddingLabel($billId, 'Each line must have between 1 and 500 labels; a bulk print is limited to 2,000 labels.');
                return;
            }

            $sectionType = (string)$item['section_type'];
            if ($sectionType === 'finished_goods') {
                $variantId = (int)($item['variant_id'] ?? 0);
                $tagCode = QrService::generateFinishedGoodsCode($variantId);
                if ($variantId <= 0 || $tagCode === null) {
                    $this->failProductAddingLabel($billId, "Map '{$item['item_name']}' to a valid catalog variant before printing labels.");
                    return;
                }
                $attributes = json_decode((string)($item['variant_attributes'] ?? ''), true);
                $attributes = is_array($attributes) ? $attributes : [];
                $variantSize = trim((string)($item['variant_size'] ?? ''));
                $variantName = trim((string)($item['variant_name'] ?? '')) ?: $item['item_name'];
                $labelColor = trim((string)($attributes['color'] ?? ''))
                    ?: trim((string)($item['color'] ?? ''))
                    ?: 'STANDARD';
                $price = (float)($item['variant_mrp'] ?: ($item['mrp'] ?: $item['unit_price']));
                $variantAttributes = self::buildLabelVariantAttributes($attributes, $variantSize);
                $lineLabel = [
                    'concern_name' => $concernName,
                    'ref_no' => $bill['bill_no'],
                    'header_title' => $variantName,
                    'label_1_name' => 'Product',
                    'label_1_val' => $variantName,
                    'label_2_name' => 'Color',
                    'label_2_val' => $labelColor,
                    'variant_id' => $variantId,
                    'variant_size' => $variantSize,
                    'variant_attributes' => $variantAttributes,
                    'label_4_name' => 'Price',
                    'label_4_val' => '₹' . number_format($price, 2),
                    'price' => $price,
                    'price_mrp' => $price,
                    'tag_code' => $tagCode,
                    'qr_payload' => $tagCode,
                    'section_title' => 'PURCHASE FINISHED GOODS',
                    'section_badge' => 'READY-MADE GARMENT',
                    'item_title' => $variantName,
                    'item_code' => $tagCode,
                    'stock_tab' => 'finished_goods',
                    'extra_details' => $variantAttributes,
                ];
                $jobType = 'purchase_fg';
            } elseif (in_array($sectionType, ['rolls', 'garment_other'], true)) {
                $isRoll = $sectionType === 'rolls';
                $design = trim((string)($item['pattern'] ?? ''))
                    ?: trim((string)($item['specification'] ?? ''))
                    ?: ($isRoll ? 'PLAIN' : 'STANDARD');
                $productName = trim((string)($item['item_name'] ?? '')) ?: 'Purchase Item';
                $tagCode = QrService::generateTagCode(
                    $productName,
                    (string)($item['color'] ?? ''),
                    $design,
                    (string)$item['id']
                );
                $lineLabel = [
                    'concern_name' => $concernName,
                    'ref_no' => $bill['bill_no'],
                    'header_title' => $isRoll ? '' : $productName,
                    'label_1_name' => $isRoll ? 'Fabric' : 'Item',
                    'label_1_val' => $productName,
                    'label_2_name' => 'Color',
                    'label_2_val' => $item['color'] ?: 'STANDARD',
                    'label_3_name' => 'Design',
                    'label_3_val' => $design,
                    'design' => $design,
                    'label_4_name' => 'Price',
                    'label_4_val' => '₹' . number_format((float)$item['unit_price'], 2),
                    'price' => (float)$item['unit_price'],
                    'price_mrp' => (float)$item['unit_price'],
                    'tag_code' => $tagCode,
                    'qr_payload' => $tagCode,
                    'section_title' => $isRoll ? 'FABRIC ROLL LABEL' : 'TRIMS & ACCESSORIES',
                    'section_badge' => $isRoll ? 'RAW MATERIAL' : 'PURCHASE TRIM',
                    'item_title' => $productName,
                    'item_code' => $tagCode,
                    'stock_tab' => $isRoll ? 'rolls' : 'garment_other',
                    'extra_details' => [],
                ];
                $jobType = 'purchase_raw';
            } else {
                $this->failProductAddingLabel($billId, 'This Product Adding line type cannot generate purchase labels.');
                return;
            }

            if ($labelData === null) {
                $labelData = $lineLabel;
            }
            for ($copy = 0; $copy < $copies; $copy++) {
                $multiLabels[] = $lineLabel;
            }
            $totalCopies += $copies;
            $generatedItemCounts[(int)$item['id']] = $copies;
            $labelJobs[] = [
                'concern_id' => (int)$item['concern_id'],
                'label_type' => $jobType,
                'item_id' => (int)$item['id'],
                'variant_id' => (int)($item['variant_id'] ?? 0) ?: null,
                'item_title' => $lineLabel['item_title'],
                'item_code' => $lineLabel['item_code'],
                'qr_payload' => $lineLabel['qr_payload'],
                'qty' => $copies,
            ];
        }

        try {
            DB::beginTransaction();
            foreach ($labelJobs as $labelJob) {
                DB::query(
                    "INSERT INTO label_jobs (concern_id, label_type, source_module, ref_id, variant_id, item_title, item_code, qr_payload, qty, printed_by, created_at)
                     VALUES (?, ?, 'product_adding', ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $labelJob['concern_id'],
                        $labelJob['label_type'],
                        $labelJob['item_id'],
                        $labelJob['variant_id'],
                        $labelJob['item_title'],
                        $labelJob['item_code'],
                        $labelJob['qr_payload'],
                        $labelJob['qty'],
                        Auth::id(),
                    ]
                );
                $jobId = (int)DB::lastInsertId();
                AuditLog::log('GENERATE_LABELS', 'label_jobs', $jobId, null, [
                    'label_type' => $labelJob['label_type'],
                    'item_title' => $labelJob['item_title'],
                    'item_code' => $labelJob['item_code'],
                    'qty' => $labelJob['qty'],
                    'qr_payload' => $labelJob['qr_payload'],
                ]);
            }
            DB::commit();
        } catch (Exception $e) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            $this->failProductAddingLabel($billId, 'Could not save the Product Adding label jobs: ' . $e->getMessage());
            return;
        }
        $this->render('concern/label_print_sheet', [
            'pageTitle' => "Product Adding Labels - {$totalCopies} Copies",
            'noLayout' => true,
            'labelData' => $labelData,
            'qty' => $totalCopies,
            'qrCodeText' => $labelData['qr_payload'],
            'jobId' => 0,
            'labelType' => 'product_adding',
            'multiLabels' => $multiLabels,
            'variantCount' => $totalBillLines,
            'batchNo' => $batchNo,
            'generatedItemCounts' => $generatedItemCounts,
        ]);
    }

    private function failProductAddingLabel(int $billId, string $message): void
    {
        if (($_POST['inline_preview'] ?? '') === '1') {
            http_response_code(422);
            $this->render('concern/product_adding_label_error', [
                'pageTitle' => 'Product Adding Label Error',
                'noLayout' => true,
                'labelError' => $message,
            ]);
            return;
        }

        $this->flash('error', $message);
        $this->redirect($billId > 0 ? '/m/product_adding?bill_id=' . $billId : '/m/product_adding');
    }

    public function previewPurchaseFinishedGoodLabel(): void
    {
        ModuleGuard::enforce('stocks', 'view');

        $itemId = filter_var($_GET['item_id'] ?? null, FILTER_VALIDATE_INT);
        $variantId = filter_var($_GET['variant_id'] ?? null, FILTER_VALIDATE_INT);
        $postedConcernId = filter_var($_GET['concern_id'] ?? null, FILTER_VALIDATE_INT);
        $concernId = Auth::isSuperAdmin()
            ? (int)($postedConcernId ?: 0)
            : (int)(Auth::concernId() ?? 0);
        if (
            !$itemId || $itemId < 1 ||
            !$variantId || $variantId < 1 ||
            !$concernId ||
            (!Auth::isSuperAdmin() && $postedConcernId && (int)$postedConcernId !== $concernId)
        ) {
            $this->flash('error', 'Select a valid purchase item and variant to preview its label.');
            $this->redirect('/m/stocks');
            return;
        }

        $item = DB::fetch(
            "SELECT pbi.id, pbi.catalog_id, pbi.variant_id, primary_variant.catalog_id AS primary_catalog_id
             FROM purchase_bill_items pbi
             LEFT JOIN variants primary_variant ON primary_variant.id = pbi.variant_id
             WHERE pbi.id = ? AND pbi.concern_id = ? AND pbi.section_type = 'finished_goods'",
            [$itemId, $concernId]
        );
        $variant = DB::fetch(
            "SELECT id, catalog_id FROM variants
             WHERE id = ? AND concern_id = ? AND status = 'active'",
            [$variantId, $concernId]
        );
        if (!$item || !$variant) {
            $this->flash('error', 'The selected purchase item or variant could not be found.');
            $this->redirect('/m/stocks');
            return;
        }
        $catalogId = (int)($item['catalog_id'] ?: ($item['primary_catalog_id'] ?? 0));
        if ((int)$item['variant_id'] !== $variantId && ($catalogId <= 0 || (int)$variant['catalog_id'] !== $catalogId)) {
            $this->flash('error', 'The selected variant does not belong to this purchase item.');
            $this->redirect('/m/stocks');
            return;
        }

        $_POST = [
            'label_type' => 'purchase_fg',
            'item_id' => (string)$itemId,
            'variant_id' => (string)$variantId,
            'preview_variant_id' => (string)$variantId,
            'preview_only' => '1',
            'qty' => '1',
        ];
        $this->renderGeneratedLabels(true, $concernId);
    }

    private function renderGeneratedLabels(bool $previewOnly, ?int $previewConcernId = null): void
    {
        if (!$previewOnly) {
            PurchaseStockItemOverrideService::initTable();
        }

        $previewVariantId = $previewOnly ? (int)($_POST['preview_variant_id'] ?? 0) : 0;
        $labelType  = trim($_POST['label_type'] ?? 'variant');
        $qty        = max(1, min(500, (int)($_POST['qty'] ?? 12)));
        $multiLabels = [];
        $variantCount = 0;
        $batchNo = '';
        $rawItemId  = $_POST['item_id'] ?? 0;
        if (is_string($rawItemId) && strpos($rawItemId, 'prod_') === 0) {
            $itemId = (int)str_replace('prod_', '', $rawItemId);
            $labelType = 'production';
        } else {
            $itemId = (int)$rawItemId;
        }
        $variantId  = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
        $cid        = $previewOnly ? $previewConcernId : Auth::concernId();

        $concern = Auth::concern();
        if (!$concern && $cid) {
            $concern = DB::fetch("SELECT * FROM concerns WHERE id = ?", [$cid]);
        }
        $concernName = $concern['name'] ?? 'GRM Inventory';

        $labelData = [
            'concern_name'   => $concernName,
            'ref_no'         => 'REF-001',
            'header_title'   => 'Garment Product',
            'label_1_name'   => 'Fabric',
            'label_1_val'    => 'Standard',
            'label_2_name'   => 'Color',
            'label_2_val'    => 'STANDARD',
            'label_3_name'   => 'Design',
            'label_3_val'    => 'STANDARD',
            'design'         => 'STANDARD',
            'label_4_name'   => 'Price',
            'label_4_val'    => '₹0.00',
            'price'          => 0.00,
            'price_mrp'      => 0.00,
            'tag_code'       => 'GAR-S-01',
            'section_title'  => 'PRODUCT LABEL',
            'section_badge'  => 'STANDARD',
            'item_title'     => 'Garment Product',
            'item_code'      => 'GAR-S-01',
            'brand_name'     => $concernName,
            'category_name'  => 'General',
            'cost_or_ref'    => '',
            'qr_payload'     => 'GAR-S-01',
            'extra_details'  => [],
        ];

        // ----------------------------------------------------
        // 1. PURCHASE LABEL (Rolls & Other Garment Things / Trims)
        // ----------------------------------------------------
        if (in_array($labelType, ['purchase_raw', 'purchase_rolls', 'purchase_other'], true)) {
            $item = DB::fetch(
                "SELECT pbi.*, pb.bill_no, pb.bill_date, s.name AS supplier_name, s.company_name AS supplier_company
                 FROM purchase_bill_items pbi
                 JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                 LEFT JOIN suppliers s ON pb.supplier_id = s.id
                 WHERE pbi.id = ?" . ($cid ? " AND pbi.concern_id = ?" : ""),
                $cid ? [$itemId, $cid] : [$itemId]
            );

            if (!$item) {
                $this->flash('error', 'Purchase item record not found.');
                $this->redirect('/m/label_generation');
                return;
            }

            $item = PurchaseStockItemOverrideService::applyToItems([$item], !$previewOnly)[0];
            $cid = $cid ?: (int)$item['concern_id'];
            $isRoll = ($item['section_type'] === 'rolls');
            $designVal = trim($item['pattern'] ?: ($item['specification'] ?: ($isRoll ? 'PLAIN' : 'STANDARD')));
            $priceVal = (float)($item['unit_price'] ?: ($item['mrp'] ?: 0));

            // Dynamically resolve fabric name vs design name for fabric rolls
            if ($isRoll) {
                $codeVal = trim($item['item_code'] ?? '');
                $nameVal = trim($item['item_name'] ?? '');
                $patternVal = trim($item['pattern'] ?? '');

                $codeIsGenericRoll = preg_match('/^ROLL\s*#?\s*\d*$/i', $codeVal);
                $nameIsPatternFabric = !empty($patternVal) && (strcasecmp($nameVal, $patternVal . ' Fabric') === 0 || strcasecmp($nameVal, $patternVal) === 0);

                if (!empty($codeVal) && !$codeIsGenericRoll) {
                    $fabricName = $codeVal;
                } elseif (!empty($nameVal) && !$nameIsPatternFabric) {
                    $fabricName = $nameVal;
                } else {
                    $fabricName = !empty($codeVal) ? $codeVal : (!empty($nameVal) ? $nameVal : 'Fabric Material');
                }
            } else {
                $fabricName = $item['item_name'] ?: 'Garment Supply';
            }

            $tagCode = QrService::generateTagCode(
                $fabricName,
                $item['color'] ?? '',
                $designVal,
                (string)$item['id']
            );
            $qrText = $tagCode;

            // For fabric rolls: remove "ROLL #cotton" / "ROLL #..." header title completely as requested
            $headerTitle = $isRoll ? '' : $item['item_name'];

            $labelData = [
                'concern_name'   => $concernName,
                'ref_no'         => $item['bill_no'] ?: ('PO-' . $item['purchase_bill_id']),
                'header_title'   => $headerTitle,
                'is_roll'        => $isRoll,
                'label_1_name'   => $isRoll ? 'Fabric' : 'Item',
                'label_1_val'    => $fabricName,
                'label_2_name'   => 'Color',
                'label_2_val'    => $item['color'] ?: 'STANDARD',
                'label_3_name'   => 'Design',
                'label_3_val'    => $designVal,
                'design'         => $designVal,
                'label_4_name'   => 'Price',
                'label_4_val'    => '₹' . number_format($priceVal, 2),
                'price'          => $priceVal,
                'price_mrp'      => $priceVal,
                'tag_code'       => $tagCode,
                'qr_payload'     => $qrText,
                'section_title'  => $isRoll ? 'FABRIC ROLL LABEL' : 'TRIMS & ACCESSORIES',
                'section_badge'  => $isRoll ? 'RAW MATERIAL' : 'PURCHASE TRIM',
                'item_title'     => $isRoll ? $fabricName : $headerTitle,
                'item_code'      => $tagCode,
                'brand_name'     => $concernName,
                'category_name'  => $isRoll ? 'Fabric Rolls' : 'Garment Other Things',
                'cost_or_ref'    => 'Bill: ' . $item['bill_no'],
                'bill_id'        => (int)$item['purchase_bill_id'],
                'item_id'        => (int)$item['id'],
                'stock_tab'      => $isRoll ? 'rolls' : 'garment_other',
                'inward_status'  => $item['inward_status'] ?? 'pending',
                'extra_details'  => [
                    'Fabric'   => $fabricName,
                    'Design'   => $designVal,
                    'Price'    => '₹' . number_format($priceVal, 2),
                    'Bill No'  => $item['bill_no'],
                    'Date'     => $item['bill_date'] ? date('d-m-Y', strtotime($item['bill_date'])) : date('d-m-Y')
                ]
            ];
        }

        // ----------------------------------------------------
        // 2. PURCHASE FINISHED GOODS (Mapped to Catalog)
        // ----------------------------------------------------
        elseif ($labelType === 'purchase_fg') {
            $item = DB::fetch(
                "SELECT pbi.*, pb.bill_no, pb.bill_date, s.name AS supplier_name, s.company_name AS supplier_company,
                        fg_concern.name AS item_concern_name,
                        v.sku_code, v.name AS variant_name, v.size AS variant_size, v.price AS variant_price, v.mrp AS variant_mrp,
                        v.attributes AS variant_attributes, v.category_id, v.subcategory_id,
                        c.name AS category_name, sc.name AS subcategory_name, b.name AS brand_name
                 FROM purchase_bill_items pbi
                 JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                 LEFT JOIN suppliers s ON pb.supplier_id = s.id
                 LEFT JOIN concerns fg_concern ON fg_concern.id = pbi.concern_id
                 LEFT JOIN variants v ON pbi.variant_id = v.id
                 LEFT JOIN categories c ON v.category_id = c.id
                 LEFT JOIN subcategories sc ON v.subcategory_id = sc.id
                 LEFT JOIN brands b ON v.brand_id = b.id
                 WHERE pbi.id = ?" . ($cid ? " AND pbi.concern_id = ?" : ""),
                $cid ? [$itemId, $cid] : [$itemId]
            );

            if (!$item) {
                $this->flash('error', 'Purchase finished good record not found.');
                $this->redirect('/m/label_generation');
                return;
            }

            $item = PurchaseStockItemOverrideService::applyToItems([$item])[0];
            $cid = $cid ?: (int)$item['concern_id'];
            $itemConcernName = trim((string) ($item['item_concern_name'] ?? ''));
            if ($itemConcernName !== '') {
                $concernName = $itemConcernName;
            }
            $varId = (int)($item['variant_id'] ?: ($variantId ?: 0));

            // If not mapped, map now if variantId is provided
            if (!$item['variant_id'] && $variantId) {
                if (!$previewOnly) {
                    DB::query("UPDATE purchase_bill_items SET variant_id = ? WHERE id = ?", [$variantId, $item['id']]);
                }
                $varModel = new Variant();
                $v = $varModel->findWithDetails($variantId);
                if ($v) {
                    $item['variant_id']       = $v['id'];
                    $item['sku_code']         = $v['sku_code'];
                    $item['variant_name']     = $v['name'];
                    $item['variant_size']     = $v['size'];
                    $item['variant_mrp']      = $v['mrp'];
                    $item['variant_attributes'] = $v['attributes'] ?? '';
                    $item['category_id']      = $v['category_id'];
                    $item['subcategory_id']   = $v['subcategory_id'];
                    $item['category_name']    = $v['category_name'] ?? 'Apparel';
                    $item['brand_name']       = $v['brand_name'] ?? 'GRM';
                }
            }

            $prodName = $item['variant_name'] ?: ($item['item_name'] ?: 'Finished Garment');
            $variantAttributeData = json_decode((string) ($item['variant_attributes'] ?? ''), true);
            $variantAttributeData = is_array($variantAttributeData) ? $variantAttributeData : [];
            $labelColor = trim((string) ($variantAttributeData['color'] ?? ''))
                ?: trim((string) ($item['color'] ?? ''))
                ?: 'STANDARD';
            $variantSize = trim((string) ($item['variant_size'] ?? ''));
            $labelVariantAttributes = self::buildLabelVariantAttributes($variantAttributeData, $variantSize);
            $tagCode = QrService::generateFinishedGoodsCode($varId);
            if ($tagCode === null) {
                $this->flash('error', 'Could not generate a product code for the saved variant.');
                $this->redirect('/m/label_generation?tab=purchase_fg');
                return;
            }
            $qrText = $tagCode;
            $mrp = (float)($item['variant_mrp'] ?: ($item['mrp'] ?: $item['unit_price']));

            $labelData = [
                'concern_name'   => $concernName,
                'ref_no'         => $item['bill_no'] ?: ('PO-' . $item['purchase_bill_id']),
                'header_title'   => $prodName,
                'label_1_name'   => 'Product',
                'label_1_val'    => $prodName,
                'label_2_name'   => 'Color',
                'label_2_val'    => $labelColor,
                'variant_attributes' => $labelVariantAttributes,
                'label_4_name'   => 'Price',
                'label_4_val'    => '₹' . number_format($mrp, 2),
                'price'          => $mrp,
                'price_mrp'      => $mrp,
                'variant_id'     => $varId,
                'tag_code'       => $tagCode,
                'qr_payload'     => $qrText,
                'section_title'  => 'PURCHASE FINISHED GOODS',
                'section_badge'  => 'READY-MADE GARMENT',
                'item_title'     => $prodName,
                'item_code'      => $tagCode,
                'brand_name'     => $concernName,
                'category_name'  => $item['category_name'] ?: 'Apparel',
                'cost_or_ref'    => 'Vendor Bill: ' . $item['bill_no'],
                'bill_id'        => (int)$item['purchase_bill_id'],
                'item_id'        => (int)$item['id'],
                'stock_tab'      => 'finished_goods',
                'inward_status'  => $item['inward_status'] ?? 'pending',
                'extra_details'  => [
                    'Price'      => '₹' . number_format($mrp, 2),
                    'Bill No'    => $item['bill_no'],
                    'Supplier'   => $item['supplier_name'],
                    'Catalog SKU'=> $item['sku_code'] ?: 'Not Mapped',
                ] + $labelVariantAttributes
            ];

            $mappedCatalogId = (int) ($item['catalog_id'] ?? 0);
            if ($mappedCatalogId <= 0 && $varId > 0) {
                $mappedCatalogId = (int) DB::fetchValue(
                    'SELECT catalog_id FROM variants WHERE id = ? AND concern_id = ?',
                    [$varId, $cid]
                );
            }
            if ($mappedCatalogId > 0) {
                $mappedVariants = DB::fetchAll(
                    "SELECT id, sku_code, name, size, price, mrp, attributes
                     FROM variants
                     WHERE concern_id = ? AND catalog_id = ? AND status = 'active'
                     ORDER BY id ASC",
                    [$cid, $mappedCatalogId]
                );
                if (
                    $previewOnly &&
                    $mappedVariants &&
                    !in_array($previewVariantId, array_map('intval', array_column($mappedVariants, 'id')), true)
                ) {
                    $this->flash('error', 'The selected variant is not part of this mapped catalog.');
                    $this->redirect('/m/stocks');
                    return;
                }
                if ($previewOnly && !$mappedVariants && $previewVariantId !== $varId) {
                    $this->flash('error', 'The selected variant is not available for this purchase item.');
                    $this->redirect('/m/stocks');
                    return;
                }
                if ($mappedVariants) {
                    $variantAllocations = [];
                    foreach ($mappedVariants as $mappedVariant) {
                        $attributes = json_decode((string) ($mappedVariant['attributes'] ?? ''), true);
                        $attributes = is_array($attributes) ? $attributes : [];
                        $variantAllocations[] = max(0, (float) ($attributes['allocated_qty'] ?? 0));
                    }
                    if (array_sum($variantAllocations) <= 0) {
                        $variantAllocations = array_fill(0, count($mappedVariants), 1);
                    }
                    $labelCopiesByVariant = self::allocateLabelCopies($variantAllocations, $qty);
                    if ($previewOnly) {
                        foreach ($mappedVariants as $index => $mappedVariant) {
                            $labelCopiesByVariant[$index] = (int)$mappedVariant['id'] === $previewVariantId ? 1 : 0;
                        }
                    }
                    $itemColors = array_values(array_filter(array_map(
                        'trim',
                        preg_split('/[,;|]+/', (string) ($item['color'] ?? ''))
                    )));
                    $fallbackColor = count($itemColors) === 1 ? $itemColors[0] : '';
                    $includedVariantIds = [];
                    $multiLabels = [];

                    foreach ($mappedVariants as $index => $mappedVariant) {
                        $copyCount = $labelCopiesByVariant[$index] ?? 0;
                        if ($copyCount <= 0) {
                            continue;
                        }
                        $attributes = json_decode((string) ($mappedVariant['attributes'] ?? ''), true);
                        $attributes = is_array($attributes) ? $attributes : [];
                        $variantName = trim((string) ($mappedVariant['name'] ?? '')) ?: $prodName;
                        $variantSize = trim((string) ($mappedVariant['size'] ?? ''));
                        $variantColor = trim((string) ($attributes['color'] ?? ''));
                        if ($variantColor === '') {
                            $variantColor = (int) $mappedVariant['id'] === $varId ? $labelColor : $fallbackColor;
                        }
                        if ($variantColor === '') {
                            $variantColor = 'STANDARD';
                        }
                        $variantAttributes = self::buildLabelVariantAttributes($attributes, $variantSize);
                        $variantMrp = (float) ($mappedVariant['mrp'] ?: ($item['mrp'] ?: $item['unit_price']));
                        $variantTagCode = QrService::generateFinishedGoodsCode((int)$mappedVariant['id']);
                        if ($variantTagCode === null) {
                            $this->flash('error', 'Could not generate a product code for a mapped variant.');
                            $this->redirect('/m/label_generation?tab=purchase_fg');
                            return;
                        }

                        $variantLabel = $labelData;
                        $variantLabel['variant_id'] = (int)$mappedVariant['id'];
                        $variantLabel['header_title'] = $variantName;
                        $variantLabel['item_title'] = $variantName;
                        $variantLabel['label_1_val'] = $variantName;
                        $variantLabel['label_2_val'] = $variantColor;
                        $variantLabel['variant_size'] = $variantSize;
                        $variantLabel['variant_attributes'] = $variantAttributes;
                        $variantLabel['label_4_val'] = '₹' . number_format($variantMrp, 2);
                        $variantLabel['price'] = $variantMrp;
                        $variantLabel['price_mrp'] = $variantMrp;
                        $variantLabel['tag_code'] = $variantTagCode;
                        $variantLabel['item_code'] = $variantTagCode;
                        $variantLabel['qr_payload'] = $variantTagCode;
                        $variantLabel['extra_details'] = ($labelData['extra_details'] ?? []) + $variantAttributes;
                        $includedVariantIds[(int) $mappedVariant['id']] = true;
                        for ($copy = 0; $copy < $copyCount; $copy++) {
                            $multiLabels[] = $variantLabel;
                        }
                    }

                    if ($multiLabels) {
                        $labelData = $multiLabels[0];
                        $variantCount = count($includedVariantIds);
                        $batchNo = (string) ($item['bill_no'] ?? '');
                    }
                }
            }

            if ($multiLabels) {
                $variantId = (int)$multiLabels[0]['variant_id'];
            } else {
                $variantId = $varId;
            }
            if ($variantId <= 0) {
                $this->flash('error', 'Map this finished good to a saved catalog variant before generating labels.');
                $this->redirect('/m/label_generation?tab=purchase_fg');
                return;
            }
        }

        // ----------------------------------------------------
        // 3. PRODUCTION LABELS (After Stitching Products)
        // ----------------------------------------------------
        elseif ($labelType === 'production') {
            $prodPrintMode = trim($_POST['prod_print_mode'] ?? 'variant');
            $batchId = (int)($_POST['batch_id'] ?? 0);
            if (!$batchId && is_string($rawItemId) && strpos($rawItemId, 'batch_') === 0) {
                $batchId = (int)str_replace('batch_', '', $rawItemId);
                $prodPrintMode = 'batch';
            }

            // WAY 1: ENTIRE BATCH MULTI-VARIANT LABEL GENERATION (e.g. 70 Labels across 7 Variants)
            if ($prodPrintMode === 'batch' && $batchId > 0) {
                $batchItems = DB::fetchAll(
                    "SELECT si.*, 
                            si.id AS stitching_item_id,
                            si.size AS variant_size,
                            si.color AS variant_color,
                            si.base_price AS variant_mrp,
                            si.discount_price AS variant_price,
                            si.stitching_qty AS stitched_pieces,
                            sl.batch_no AS log_no,
                            sl.batch_no,
                            sl.stitching_name,
                            sl.concern_id,
                            pp.program_no,
                            pp.program_name,
                            COALESCE(cat.id, pp.catalog_id) AS catalog_id,
                            cat.name AS catalog_name,
                            cat.style_code AS catalog_code,
                            c.name AS category_name,
                            sc.name AS subcategory_name,
                            b.name AS brand_name,
                            v.sku_code AS v_sku,
                            v.name AS v_name,
                            v.mrp AS v_mrp,
                            v.price AS v_price
                     FROM stitching_items si
                     JOIN stitching_logs sl ON si.stitching_id = sl.id
                     LEFT JOIN production_programs pp ON sl.program_id = pp.id
                     LEFT JOIN cutting_items ci ON si.cutting_item_id = ci.id
                     LEFT JOIN program_rolls pr ON ci.program_roll_id = pr.id
                     LEFT JOIN catalogs cat ON (si.catalog_id = cat.id OR pp.catalog_id = cat.id OR pr.catalog_id = cat.id)
                     LEFT JOIN categories c ON cat.category_id = c.id
                     LEFT JOIN subcategories sc ON cat.subcategory_id = sc.id
                     LEFT JOIN variants v ON si.variant_id = v.id
                     LEFT JOIN brands b ON v.brand_id = b.id
                     WHERE si.stitching_id = ?" . ($cid ? " AND (sl.concern_id = ? OR sl.concern_id = 1 OR sl.concern_id IS NULL)" : "") . "
                     ORDER BY si.id ASC",
                    $cid ? [$batchId, $cid] : [$batchId]
                );

                if (empty($batchItems)) {
                    $sl = DB::fetch(
                        "SELECT sl.*, sl.batch_no AS log_no, v.sku_code, v.name AS variant_name, v.size AS variant_size,
                                v.price AS variant_price, v.mrp AS variant_mrp, c.name AS category_name, sc.name AS subcategory_name,
                                b.name AS brand_name, pp.program_no
                         FROM stitching_logs sl
                         LEFT JOIN variants v ON sl.produced_variant_id = v.id
                         LEFT JOIN production_programs pp ON sl.program_id = pp.id
                         LEFT JOIN categories c ON v.category_id = c.id
                         LEFT JOIN subcategories sc ON v.subcategory_id = sc.id
                         LEFT JOIN brands b ON v.brand_id = b.id
                         WHERE sl.id = ?" . ($cid ? " AND (sl.concern_id = ? OR sl.concern_id = 1 OR sl.concern_id IS NULL)" : ""),
                        $cid ? [$batchId, $cid] : [$batchId]
                    );
                    if ($sl) {
                        $batchItems = [[
                            'stitching_item_id' => 0,
                            'stitching_id'      => $sl['id'],
                            'variant_id'        => $sl['produced_variant_id'],
                            'variant_name'      => $sl['variant_name'] ?: 'In-House Garment',
                            'variant_size'      => $sl['variant_size'] ?: 'Standard',
                            'variant_color'     => $sl['color'] ?? 'STANDARD',
                            'variant_mrp'       => $sl['variant_mrp'] ?? 0,
                            'variant_price'     => $sl['variant_price'] ?? 0,
                            'stitched_pieces'   => $sl['pieces_produced'] ?? 1,
                            'log_no'            => $sl['log_no'],
                            'batch_no'          => $sl['log_no'],
                            'program_no'        => $sl['program_no'] ?? 'Standard Program',
                            'brand_name'        => $sl['brand_name'] ?? $concernName,
                            'category_name'     => $sl['category_name'] ?? 'Apparel',
                            'sku_code'          => $sl['sku_code'] ?? 'SKU',
                            'concern_id'        => $sl['concern_id'] ?? 1
                        ]];
                    }
                }

                if (empty($batchItems)) {
                    $this->flash('error', 'No stitched items found for batch #' . $batchId);
                    $this->redirect('/m/label_generation?tab=production');
                    return;
                }

                // Validation: Prevent duplicate label generation if labels have already been generated for this batch
                $existingJob = DB::fetch(
                    "SELECT id, qty, created_at FROM label_jobs WHERE label_type = 'production' AND ref_id = ? ORDER BY id DESC LIMIT 1",
                    [$batchId]
                );
                if ($existingJob && empty($_POST['force_reprint'])) {
                    $this->flash('error', "Validation Alert: Labels have already been generated for this stitching batch on " . date('d M Y, h:i A', strtotime($existingJob['created_at'])) . " (Job #{$existingJob['id']}, {$existingJob['qty']} copies). Duplicate label generation is restricted.");
                    $this->redirect('/m/label_generation?tab=production');
                    return;
                }

                $multiLabels = [];
                $batchNo = $batchItems[0]['log_no'] ?? ('STCH-' . $batchId);
                $batchConcernId = $cid ?: (int)($batchItems[0]['concern_id'] ?? 1);

                foreach ($batchItems as $bItem) {
                    $itemVariantId = !empty($bItem['variant_id']) ? (int)$bItem['variant_id'] : 0;
                    $itemSku = $bItem['sku_code'] ?? ($bItem['v_sku'] ?? '');
                    $itemSize = $bItem['variant_size'] ?: ($bItem['size'] ?: 'Standard');
                    $itemColor = $bItem['variant_color'] ?? ($bItem['color'] ?? 'STANDARD');
                    $itemProg = $bItem['program_no'] ?: 'Standard Program';
                    $itemMrp = (float)($bItem['variant_mrp'] ?: ($bItem['v_mrp'] ?? 0));
                    $itemPrice = (float)($bItem['variant_price'] ?: ($bItem['v_price'] ?? $itemMrp));
                    $itemPieces = max(1, (int)($bItem['stitched_pieces'] ?: 1));

                    if (!$itemVariantId && !empty($itemSku)) {
                        $existingVar = DB::fetch("SELECT id FROM variants WHERE concern_id = ? AND sku_code = ?", [$batchConcernId, $itemSku]);
                        if ($existingVar) {
                            $itemVariantId = (int)$existingVar['id'];
                        } else {
                            $catId = (int)($bItem['category_id'] ?: 1);
                            $validCat = DB::fetchValue("SELECT id FROM categories WHERE id = ?", [$catId]);
                            if (!$validCat) {
                                $catId = (int)DB::fetchValue("SELECT id FROM categories ORDER BY id ASC LIMIT 1") ?: 1;
                            }
                            $subcatId = !empty($bItem['subcategory_id']) ? (int)$bItem['subcategory_id'] : null;
                            if ($subcatId && !DB::fetchValue("SELECT id FROM subcategories WHERE id = ?", [$subcatId])) {
                                $subcatId = null;
                            }
                            $catalogId = !empty($bItem['catalog_id']) ? (int)$bItem['catalog_id'] : null;
                            DB::query(
                                "INSERT INTO variants (concern_id, catalog_id, sku_code, name, category_id, subcategory_id, size, weight, weight_unit, price, mrp, creation_date, status)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, 0.4, 'pcs', ?, ?, CURDATE(), 'active')",
                                [$batchConcernId, $catalogId, $itemSku, $bItem['variant_name'], $catId, $subcatId, $itemSize, $itemPrice, $itemMrp]
                            );
                            $itemVariantId = (int)DB::lastInsertId();
                            if (!empty($bItem['stitching_item_id'])) {
                                DB::query("UPDATE stitching_items SET variant_id = ? WHERE id = ?", [$itemVariantId, $bItem['stitching_item_id']]);
                            }
                        }
                    }

                    if ($itemVariantId <= 0) {
                        $this->flash('error', 'A saved catalog variant is required before generating finished-goods labels.');
                        $this->redirect('/m/label_generation?tab=production');
                        return;
                    }

                    $prodName = $bItem['variant_name'] ?: ($bItem['catalog_name'] ?: 'In-House Garment');
                    $tagCode = QrService::generateFinishedGoodsCode($itemVariantId);
                    if ($tagCode === null) {
                        $this->flash('error', 'Could not generate a product code for the saved variant.');
                        $this->redirect('/m/label_generation?tab=production');
                        return;
                    }
                    $designVal = 'SIZE: ' . strtoupper($itemSize);

                    $vLabelData = [
                        'concern_name'   => $concernName,
                        'ref_no'         => $itemProg ?: ('STITCH-' . $batchNo),
                        'header_title'   => $prodName,
                        'label_1_name'   => 'Program',
                        'label_1_val'    => $itemProg,
                        'label_2_name'   => 'Color',
                        'label_2_val'    => strtoupper($itemColor),
                        'label_3_name'   => 'Design',
                        'label_3_val'    => $designVal,
                        'design'         => $designVal,
                        'label_4_name'   => 'Price',
                        'label_4_val'    => '₹' . number_format($itemPrice, 2),
                        'price'          => $itemPrice,
                        'price_mrp'      => $itemMrp,
                        'variant_id'     => $itemVariantId,
                        'tag_code'       => $tagCode,
                        'qr_payload'     => $tagCode,
                        'section_title'  => 'PRODUCTION FINISHED GARMENT',
                        'section_badge'  => 'IN-HOUSE STITCHED',
                        'item_title'     => $prodName,
                        'item_code'      => $tagCode,
                        'item_id'        => (int)($bItem['stitching_item_id'] ?: 0),
                        'variant_size'   => $itemSize,
                        'sku_code'       => $itemSku ?: $tagCode,
                        'stock_tab'      => 'production',
                        'brand_name'     => $bItem['brand_name'] ?: $concernName,
                        'category_name'  => $bItem['category_name'] ?: 'Apparel',
                        'cost_or_ref'    => 'Batch #' . $batchNo,
                    ];

                    for ($c = 0; $c < $itemPieces; $c++) {
                        $multiLabels[] = $vLabelData;
                    }
                }

                $totalCopies = count($multiLabels);

                DB::query(
                    "INSERT INTO label_jobs (concern_id, label_type, ref_id, variant_id, item_title, item_code, qr_payload, qty, printed_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $batchConcernId,
                        'production',
                        $batchId,
                        null,
                        "Batch #{$batchNo} (" . count($batchItems) . " Variants)",
                        $batchNo,
                        "BATCH-{$batchNo}",
                        $totalCopies,
                        Auth::id()
                    ]
                );
                $jobId = (int)DB::lastInsertId();

                AuditLog::log('GENERATE_LABELS', 'label_jobs', $jobId, null, [
                    'label_type'    => 'production_batch',
                    'batch_no'      => $batchNo,
                    'variant_count' => count($batchItems),
                    'total_copies'  => $totalCopies
                ]);

                $this->render('concern/label_print_sheet', [
                    'pageTitle'    => "Print Labels - Batch #{$batchNo} ({$totalCopies} Copies across " . count($batchItems) . " Variants)",
                    'noLayout'     => true,
                    'labelData'    => $multiLabels[0],
                    'multiLabels'  => $multiLabels,
                    'qty'          => $totalCopies,
                    'jobId'        => $jobId,
                    'labelType'    => 'production',
                    'batchNo'      => $batchNo,
                    'batchId'      => $batchId,
                    'variantCount' => count($batchItems),
                ]);
                return;
            }

            // WAY 2: VARIANT-WISE / SINGLE ITEM GENERATION
            $item = DB::fetch(
                "SELECT si.*, 
                        si.id AS stitching_item_id,
                        si.size AS variant_size,
                        si.color AS variant_color,
                        si.base_price AS variant_mrp,
                        si.discount_price AS variant_price,
                        si.stitching_qty AS stitched_pieces,
                        sl.batch_no AS log_no,
                        sl.batch_no,
                        sl.stitching_name,
                        sl.concern_id,
                        pp.program_no,
                        pp.program_name,
                        COALESCE(cat.id, pp.catalog_id) AS catalog_id,
                        cat.name AS catalog_name,
                        cat.style_code AS catalog_code,
                        c.name AS category_name,
                        sc.name AS subcategory_name,
                        b.name AS brand_name
                 FROM stitching_items si
                 JOIN stitching_logs sl ON si.stitching_id = sl.id
                 LEFT JOIN production_programs pp ON sl.program_id = pp.id
                 LEFT JOIN cutting_items ci ON si.cutting_item_id = ci.id
                 LEFT JOIN program_rolls pr ON ci.program_roll_id = pr.id
                 LEFT JOIN catalogs cat ON (si.catalog_id = cat.id OR pp.catalog_id = cat.id OR pr.catalog_id = cat.id)
                 LEFT JOIN categories c ON cat.category_id = c.id
                 LEFT JOIN subcategories sc ON cat.subcategory_id = sc.id
                 LEFT JOIN variants v ON si.variant_id = v.id
                 LEFT JOIN brands b ON v.brand_id = b.id
                 WHERE si.id = ?" . ($cid ? " AND (sl.concern_id = ? OR sl.concern_id = 1 OR sl.concern_id IS NULL)" : ""),
                $cid ? [$itemId, $cid] : [$itemId]
            );

            if (!$item) {
                // Fallback to stitching_logs by batch id
                $item = DB::fetch(
                    "SELECT sl.*, 
                            sl.id AS stitching_log_id,
                            sl.batch_no AS log_no,
                            v.sku_code, v.name AS variant_name, v.size AS variant_size, v.price AS variant_price, v.mrp AS variant_mrp,
                            v.category_id, v.subcategory_id,
                            c.name AS category_name, sc.name AS subcategory_name, b.name AS brand_name,
                            pp.program_no
                     FROM stitching_logs sl
                     LEFT JOIN variants v ON sl.produced_variant_id = v.id
                     LEFT JOIN production_programs pp ON sl.program_id = pp.id
                     LEFT JOIN categories c ON v.category_id = c.id
                     LEFT JOIN subcategories sc ON v.subcategory_id = sc.id
                     LEFT JOIN brands b ON v.brand_id = b.id
                     WHERE sl.id = ?" . ($cid ? " AND (sl.concern_id = ? OR sl.concern_id = 1 OR sl.concern_id IS NULL)" : ""),
                    $cid ? [$itemId, $cid] : [$itemId]
                );
            }

            if (!$item) {
                // Check if user selected variant directly for production
                if ($variantId) {
                    $varModel = new Variant();
                    $v = $varModel->findWithDetails($variantId);
                    if ($v) {
                        $item = [
                            'id'                  => 0,
                            'concern_id'          => $v['concern_id'],
                            'log_no'              => 'STITCH-VAR-' . $v['id'],
                            'produced_variant_id' => $v['id'],
                            'variant_id'          => $v['id'],
                            'sku_code'            => $v['sku_code'],
                            'variant_name'        => $v['name'],
                            'variant_size'        => $v['size'],
                            'variant_mrp'         => $v['mrp'],
                            'variant_price'       => $v['price'],
                            'category_id'         => $v['category_id'],
                            'subcategory_id'      => $v['subcategory_id'],
                            'category_name'       => $v['category_name'] ?? 'In-House Production',
                            'brand_name'          => $v['brand_name'] ?? 'GRM Apparel',
                            'program_no'          => 'In-House Production'
                        ];
                    }
                }
            }

            if (!$item) {
                $this->flash('error', 'Production stitching batch record not found.');
                $this->redirect('/m/label_generation?tab=production');
                return;
            }

            $cid = $cid ?: (int)$item['concern_id'];
            $variantId = !empty($item['variant_id']) ? (int)$item['variant_id'] : (!empty($item['produced_variant_id']) ? (int)$item['produced_variant_id'] : 0);

            // Auto-resolve or create variant if not yet linked
            if (!$variantId && !empty($item['sku_code'])) {
                $existingVar = DB::fetch("SELECT id FROM variants WHERE concern_id = ? AND sku_code = ?", [$cid, $item['sku_code']]);
                if ($existingVar) {
                    $variantId = (int)$existingVar['id'];
                } else {
                    $catId = (int)($item['category_id'] ?: 1);
                    $validCat = DB::fetchValue("SELECT id FROM categories WHERE id = ?", [$catId]);
                    if (!$validCat) {
                        $catId = (int)DB::fetchValue("SELECT id FROM categories ORDER BY id ASC LIMIT 1") ?: 1;
                    }
                    $subcatId = !empty($item['subcategory_id']) ? (int)$item['subcategory_id'] : null;
                    if ($subcatId && !DB::fetchValue("SELECT id FROM subcategories WHERE id = ?", [$subcatId])) {
                        $subcatId = null;
                    }
                    $catalogId = !empty($item['catalog_id']) ? (int)$item['catalog_id'] : null;
                    $mrp = (float)($item['variant_mrp'] ?: 0.00);
                    $price = (float)($item['variant_price'] ?: $mrp);
                    $weight = (float)($item['per_pc_weight'] ?: 0.00);

                    DB::query(
                        "INSERT INTO variants (concern_id, catalog_id, sku_code, name, category_id, subcategory_id, size, weight, weight_unit, price, mrp, creation_date, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pcs', ?, ?, CURDATE(), 'active')",
                        [$cid, $catalogId, $item['sku_code'], $item['variant_name'], $catId, $subcatId, $item['variant_size'] ?: 'Standard', $weight, $price, $mrp]
                    );
                    $variantId = (int)DB::lastInsertId();
                    if (!empty($item['stitching_item_id'])) {
                        DB::query("UPDATE stitching_items SET variant_id = ? WHERE id = ?", [$variantId, $item['stitching_item_id']]);
                    }
                }
            }

            if ($variantId <= 0) {
                $this->flash('error', 'A saved catalog variant is required before generating finished-goods labels.');
                $this->redirect('/m/label_generation?tab=production');
                return;
            }

            $prodName = $item['variant_name'] ?: 'In-House Garment';
            $tagCode = QrService::generateFinishedGoodsCode($variantId);
            if ($tagCode === null) {
                $this->flash('error', 'Could not generate a product code for the saved variant.');
                $this->redirect('/m/label_generation?tab=production');
                return;
            }
            $qrText = $tagCode;

            $designVal = trim(!empty($item['variant_size']) ? ('SIZE: ' . $item['variant_size']) : (!empty($item['pattern']) ? $item['pattern'] : ($item['program_no'] ?: 'STANDARD')));
            $priceVal = (float)($item['variant_mrp'] ?: ($item['variant_price'] ?? 0));

            $labelData = [
                'concern_name'   => $concernName,
                'ref_no'         => $item['program_no'] ?: ('STITCH-' . ($item['log_no'] ?? '1')),
                'header_title'   => $prodName,
                'label_1_name'   => 'Program',
                'label_1_val'    => $item['program_no'] ?: 'In-House Production',
                'label_2_name'   => 'Color',
                'label_2_val'    => $item['variant_color'] ?? ($item['color'] ?? 'STANDARD'),
                'label_3_name'   => 'Design',
                'label_3_val'    => $designVal,
                'design'         => $designVal,
                'label_4_name'   => 'Price',
                'label_4_val'    => '₹' . number_format($priceVal, 2),
                'price'          => $priceVal,
                'price_mrp'      => $priceVal,
                'variant_id'     => $variantId,
                'tag_code'       => $tagCode,
                'qr_payload'     => $qrText,
                'section_title'  => 'PRODUCTION FINISHED GARMENT',
                'section_badge'  => 'IN-HOUSE STITCHED',
                'item_title'     => $prodName,
                'item_code'      => $tagCode,
                'item_id'        => $itemId,
                'variant_size'   => $item['variant_size'] ?: ($item['size'] ?: 'Free'),
                'sku_code'       => $item['sku_code'] ?? $tagCode,
                'stock_tab'      => 'production',
                'brand_name'     => $item['brand_name'] ?: $concernName,
                'category_name'  => $item['category_name'] ?: 'Apparel',
                'cost_or_ref'    => 'Batch #' . ($item['log_no'] ?? $item['batch_no'] ?? '1'),
                'extra_details'  => [
                    'Program'    => $item['program_no'] ?: 'Standard',
                    'Batch'      => $item['log_no'] ?? $item['batch_no'] ?? '1',
                    'Color'      => $item['variant_color'] ?? ($item['color'] ?? 'STANDARD'),
                    'Design'     => $designVal,
                    'Price'      => '₹' . number_format($priceVal, 2),
                    'SKU'        => $item['sku_code'] ?? 'SKU',
                    'Stitched'   => ($item['stitched_pieces'] ?? 1) . ' pcs',
                    'Assembled'  => date('d-m-Y')
                ]
            ];
        }

        // ----------------------------------------------------
        // 4. DIRECT VARIANT FALLBACK
        // ----------------------------------------------------
        else {
            $variantId = (int)($_POST['variant_id'] ?? 0);
            $varModel = new Variant();
            $v = $varModel->findWithDetails($variantId);

            if (!$v) {
                $this->flash('error', 'Selected variant was not found.');
                $this->redirect('/m/label_generation');
                return;
            }

            $cid = $cid ?: (int)$v['concern_id'];
            $designVal = $v['subcategory_name'] ?: 'STANDARD';
            $priceVal = (float)$v['mrp'];

            $variantId = (int)$v['id'];
            $tagCode = QrService::generateFinishedGoodsCode($variantId);
            if ($tagCode === null) {
                $this->flash('error', 'Could not generate a product code for the saved variant.');
                $this->redirect('/m/label_generation');
                return;
            }
            $qrText = $tagCode;
            $variantAttributes = json_decode((string)($v['attributes'] ?? ''), true);
            $variantAttributes = is_array($variantAttributes) ? $variantAttributes : [];
            $variantColor = trim((string)($variantAttributes['color'] ?? '')) ?: 'STANDARD';
            $variantSize = trim((string)($variantAttributes['size'] ?? $v['size'] ?? ''));

            $labelData = [
                'concern_name'   => $concernName,
                'ref_no'         => $v['sku_code'],
                'header_title'   => $v['name'],
                'label_1_name'   => 'Category',
                'label_1_val'    => $v['category_name'] ?? 'Apparel',
                'label_2_name'   => 'Color',
                'label_2_val'    => $variantColor,
                'label_3_name'   => 'Design',
                'label_3_val'    => $designVal,
                'design'         => $designVal,
                'label_4_name'   => 'Price',
                'label_4_val'    => '₹' . number_format($priceVal, 2),
                'price'          => $priceVal,
                'price_mrp'      => $priceVal,
                'variant_id'     => $variantId,
                'variant_size'   => $variantSize,
                'variant_attributes' => self::buildLabelVariantAttributes($variantAttributes, $variantSize),
                'tag_code'       => $tagCode,
                'qr_payload'     => $qrText,
                'section_title'  => 'PRODUCT CATALOG LABEL',
                'section_badge'  => 'CATALOG VARIANT',
                'item_title'     => $v['name'],
                'item_code'      => $tagCode,
                'brand_name'     => $concernName,
                'category_name'  => $v['category_name'] ?? 'Apparel',
                'cost_or_ref'    => 'Catalog',
                'stock_tab'      => 'finished_goods',
                'extra_details'  => [
                    'Design'     => $designVal,
                    'Price'      => '₹' . number_format($priceVal, 2),
                    'SKU'        => $v['sku_code'],
                ]
            ];
        }

        $jobId = 0;
        if (!$previewOnly) {
            DB::query(
                "INSERT INTO label_jobs (concern_id, label_type, ref_id, variant_id, item_title, item_code, qr_payload, qty, printed_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $cid,
                    $labelType,
                    $itemId ?: null,
                    $variantId ?: null,
                    $labelData['item_title'],
                    $labelData['item_code'],
                    $labelData['qr_payload'],
                    $qty,
                    Auth::id()
                ]
            );
            $jobId = (int)DB::lastInsertId();

            AuditLog::log('GENERATE_LABELS', 'label_jobs', $jobId, null, [
                'label_type' => $labelType,
                'item_title' => $labelData['item_title'],
                'item_code'  => $labelData['item_code'],
                'qty'        => $qty,
                'qr_payload' => $labelData['qr_payload']
            ]);
        }

        // Render printable sheet
        $this->render('concern/label_print_sheet', [
            'pageTitle'   => $previewOnly
                ? "Label Preview - {$labelData['item_code']}"
                : "Print Labels - {$labelData['item_code']} ({$qty} Copies)",
            'noLayout'    => true,
            'viewOnly'    => $previewOnly,
            'labelData'   => $labelData,
            'qty'         => $qty,
            'qrCodeText'  => $labelData['qr_payload'],
            'jobId'       => $jobId,
            'labelType'   => $labelType,
            'multiLabels' => $multiLabels,
            'variantCount' => $variantCount,
            'batchNo' => $batchNo,
        ]);
    }

    private static function allocateLabelCopies(array $allocations, int $requestedCopies): array
    {
        $totalAllocation = array_sum(array_map(static fn($value): float => max(0, (float) $value), $allocations));
        if ($totalAllocation <= 0 || !$allocations) {
            return [];
        }

        $counts = [];
        $remainders = [];
        $assignedCopies = 0;
        foreach ($allocations as $index => $allocation) {
            $exactCopies = $requestedCopies * max(0, (float) $allocation) / $totalAllocation;
            $wholeCopies = (int) floor($exactCopies);
            $counts[$index] = $wholeCopies;
            $assignedCopies += $wholeCopies;
            $remainders[] = ['index' => $index, 'fraction' => $exactCopies - $wholeCopies];
        }

        usort($remainders, static function (array $left, array $right): int {
            $fractionOrder = $right['fraction'] <=> $left['fraction'];
            return $fractionOrder !== 0 ? $fractionOrder : ($left['index'] <=> $right['index']);
        });
        for ($remaining = $requestedCopies - $assignedCopies, $i = 0; $remaining > 0; $remaining--, $i++) {
            $counts[$remainders[$i % count($remainders)]['index']]++;
        }
        ksort($counts);
        return array_values($counts);
    }

    private static function buildLabelVariantAttributes(array $attributeData, string $size): array
    {
        $labelAttributes = [];
        if ($size !== '') {
            $labelAttributes['Size'] = $size;
        }
        $customAttributes = [];
        foreach ($attributeData as $type => $value) {
            $type = strtolower(trim((string) $type));
            if (
                $type === '' ||
                in_array($type, ['color', 'size', 'hsn', 'weight', 'allocated_qty', 'custom_attributes'], true) ||
                !is_scalar($value)
            ) {
                continue;
            }
            $customAttributes[$type] = trim((string) $value);
        }
        if (is_array($attributeData['custom_attributes'] ?? null)) {
            $customAttributes = array_merge($customAttributes, $attributeData['custom_attributes']);
        }
        foreach ($customAttributes as $type => $value) {
            $type = strtolower(trim((string) $type));
            if (!is_scalar($value)) {
                continue;
            }
            $value = trim((string) $value);
            if ($type === '' || in_array($type, ['color', 'size'], true) || $value === '') {
                continue;
            }
            $labelAttributes[ucwords(str_replace(['_', '-'], ' ', $type))] = $value;
        }
        return $labelAttributes;
    }

    public function scanPage(): void {
        $this->render('concern/scan', [
            'pageTitle'     => 'Live QR / Barcode Scanner',
            'currentModule' => 'stocks',
        ]);
    }

    /**
     * AJAX endpoint to lookup scanned QR code
     */
    public function lookup(): void {
        $cid = Auth::concernId();
        $code = trim($_GET['code'] ?? $_POST['code'] ?? '');

        if (empty($code)) {
            $this->renderJson(['success' => false, 'message' => 'Empty barcode/QR code'], 400);
        }

        // 1. If Super Admin has not filtered to a single concern, detect variant concern
        if (Auth::isSuperAdmin() && !$cid) {
            $parsed = QrService::parsePayload($code);
            $finishedGoodsCode = QrService::parseFinishedGoodsCode($code);
            $variantId = QrService::parseVariantId($code)
                ?? ($finishedGoodsCode['variant_id'] ?? ($parsed['variant_id'] ?? null));
            if ($variantId) {
                $varCid = DB::fetchValue("SELECT concern_id FROM variants WHERE id = ?", [$variantId]);
                if ($varCid) {
                    $cid = (int)$varCid;
                }
            }
        }

        // 2. Check if this is a Purchase Roll QR: e.g. PUR-ROLL-{id}-...
        if (str_starts_with($code, 'PUR-ROLL-') || str_starts_with($code, 'PUR-TRIM-')) {
            $parts = explode('-', $code);
            $pbiId = isset($parts[2]) ? (int)$parts[2] : 0;
            if ($pbiId) {
                $item = DB::fetch(
                    "SELECT pbi.*, pb.bill_no, pb.bill_date, s.name AS supplier_name, s.company_name AS supplier_company
                     FROM purchase_bill_items pbi
                     JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                     LEFT JOIN suppliers s ON pb.supplier_id = s.id
                     WHERE pbi.id = ?" . ($cid ? " AND pbi.concern_id = ?" : ""),
                    $cid ? [$pbiId, $cid] : [$pbiId]
                );

                if ($item) {
                    $this->renderJson([
                        'success' => true,
                        'data'    => [
                            'name'          => $item['item_name'],
                            'sku_code'      => $item['item_code'],
                            'category_name' => ($item['section_type'] === 'rolls' ? 'Fabric Roll' : 'Trims & Accessories'),
                            'current_stock' => $item['qty'],
                            'price'         => $item['unit_price'],
                            'mrp'           => $item['mrp'] ?: $item['unit_price'],
                            'size'          => number_format((float)$item['qty'], 2) . ' ' . $item['unit'],
                            'brand_name'    => $item['supplier_company'] ?: $item['supplier_name'],
                            'attributes_parsed' => [
                                'Section'     => strtoupper($item['section_type']),
                                'Bill No'     => $item['bill_no'],
                                'Color'       => $item['color'] ?: '-',
                                'Net Weight'  => $item['net_weight'] ? $item['net_weight'] . ' kg' : '-',
                                'Gross Weight'=> $item['gross_weight'] ? $item['gross_weight'] . ' kg' : '-',
                            ]
                        ]
                    ]);
                    return;
                }
            }
        }

        // 2b. Check if this is a simplified tag code lookup from label_jobs or purchase_bill_items
        $job = DB::fetch(
            "SELECT * FROM label_jobs WHERE qr_payload = ?" . ($cid ? " AND concern_id = ?" : "") . " ORDER BY id DESC LIMIT 1",
            $cid ? [$code, $cid] : [$code]
        );
        if ($job) {
            if ($job['label_type'] === 'purchase_raw' && !empty($job['ref_id'])) {
                $pbi = DB::fetch(
                    "SELECT pbi.*, pb.bill_no, pb.bill_date, s.name AS supplier_name, s.company_name AS supplier_company
                     FROM purchase_bill_items pbi
                     JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                     LEFT JOIN suppliers s ON pb.supplier_id = s.id
                     WHERE pbi.id = ?" . ($cid ? " AND pbi.concern_id = ?" : ""),
                    $cid ? [$job['ref_id'], $cid] : [$job['ref_id']]
                );
                if ($pbi) {
                    $this->renderJson([
                        'success' => true,
                        'data'    => [
                            'name'          => $pbi['item_name'],
                            'sku_code'      => $job['item_code'],
                            'category_name' => ($pbi['section_type'] === 'rolls' ? 'Fabric Roll' : 'Trims & Accessories'),
                            'current_stock' => $pbi['qty'],
                            'price'         => $pbi['unit_price'],
                            'mrp'           => $pbi['mrp'] ?: $pbi['unit_price'],
                            'size'          => number_format((float)$pbi['qty'], 2) . ' ' . $pbi['unit'],
                            'brand_name'    => $pbi['supplier_company'] ?: $pbi['supplier_name'],
                            'attributes_parsed' => [
                                'Section'     => strtoupper($pbi['section_type']),
                                'Bill No'     => $pbi['bill_no'],
                                'Color'       => $pbi['color'] ?: '-',
                                'Net Weight'  => $pbi['net_weight'] ? $pbi['net_weight'] . ' kg' : '-',
                                'Gross Weight'=> $pbi['gross_weight'] ? $pbi['gross_weight'] . ' kg' : '-',
                                'Code'        => $job['item_code'],
                            ]
                        ]
                    ]);
                    return;
                }
            } elseif (!empty($job['variant_id'])) {
                $var = (new Variant())->findWithDetails((int)$job['variant_id']);
                if ($var && (!$cid || (int)$var['concern_id'] === $cid)) {
                    $this->renderJson([
                        'success' => true,
                        'data'    => $var
                    ]);
                    return;
                }
            }
        }

        // 3. Standard Variant QR Lookup
        if (!$cid) {
            $this->renderJson(['success' => false, 'message' => 'Variant not found.'], 404);
        }

        $variant = QrService::lookupVariant($code, $cid);

        if (!$variant) {
            $this->renderJson([
                'success' => false,
                'message' => "QR Code '{$code}' not found or belongs to another concern under tenant isolation."
            ], 404);
        }

        $this->renderJson([
            'success' => true,
            'data'    => $variant,
        ]);
    }

    public function exportJobs(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT lj.*, v.name AS variant_name, v.sku_code, u.name AS user_name,
                       con.name AS concern_name, con.code AS concern_code
                FROM label_jobs lj
                JOIN variants v ON lj.variant_id = v.id
                LEFT JOIN concerns con ON lj.concern_id = con.id
                LEFT JOIN users u ON lj.printed_by = u.id";
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "lj.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(lj.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY lj.id DESC";
        $jobs = DB::fetchAll($sql, $params);

        $headers = [
            'Job ID', 'Date & Time', 'SKU Code', 'Variant Name', 'Quantity', 
            'Barcode Type', 'Printed By', 'Concern'
        ];

        $rows = [];
        $totQty = 0;
        foreach ($jobs as $j) {
            $qty = (int)$j['quantity'];
            $totQty += $qty;
            $rows[] = [
                '#JOB-' . str_pad($j['id'], 4, '0', STR_PAD_LEFT),
                date('d M Y, h:i A', strtotime($j['created_at'])),
                $j['sku_code'],
                $j['variant_name'],
                $qty,
                strtoupper($j['barcode_type'] ?? 'QR CODE'),
                $j['user_name'] ?? 'System',
                $j['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total Jobs: ' . count($jobs), '', '', '', $totQty, '', '', ''];
        $metadata = [
            'Report Title' => 'QR Label & Barcode Generation Batch History',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Jobs' => count($jobs),
            'Total Labels Printed' => number_format($totQty)
        ];

        $filename = 'label_jobs_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Barcode & Label Print Jobs Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Barcode & Label Print Jobs Report', $metadata, $headers, $rows, $totals);
        }
    }

    /**
     * Inward purchase items / purchase bill directly into Purchase Stocks
     * Transitions inward status from pending to completed, syncs stock ledger & roll specs,
     * and redirects user to the Purchase Stocks module.
     */
    public function addToStock(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            if (!headers_sent()) {
                header('Allow: POST');
            }
            $this->flash('error', 'Use the Label Generation Add to Stock form to inward items.');
            $this->redirect('/m/label_generation');
            return;
        }
        $this->validateCsrf();
        PurchaseBillSourceService::ensureSchema();
        PurchaseStockItemOverrideService::initTable();

        $isProductAddingRequest = ($_POST['product_adding'] ?? '') === '1';
        $cid = Auth::concernId();
        $billId = (int)($_POST['bill_id'] ?? ($_GET['bill_id'] ?? 0));
        $rawItemId = $_POST['item_id'] ?? ($_GET['item_id'] ?? 0);
        $preferredTab = trim($_POST['tab'] ?? ($_GET['tab'] ?? ''));
        $stitchingId = (int)($_POST['stitching_id'] ?? ($_POST['batch_id'] ?? ($_GET['stitching_id'] ?? ($_GET['batch_id'] ?? 0))));

        if (is_string($rawItemId) && strpos($rawItemId, 'prod_') === 0) {
            $itemId = (int)str_replace('prod_', '', $rawItemId);
            $preferredTab = 'production';
        } elseif (is_string($rawItemId) && strpos($rawItemId, 'batch_') === 0) {
            $stitchingId = (int)str_replace('batch_', '', $rawItemId);
            $itemId = 0;
            $preferredTab = 'production';
        } else {
            $itemId = (int)$rawItemId;
        }
        $rawSelectedItemIds = $_POST['item_ids'] ?? [];
        $selectedItemIds = $isProductAddingRequest && is_array($rawSelectedItemIds)
            ? array_values(array_unique(array_filter(
                array_map('intval', $rawSelectedItemIds),
                static fn(int $id): bool => $id > 0
            )))
            : [];

        // Handle Production Stitched Items Inward - Must be inspected in QC first
        if ($preferredTab === 'production' || $stitchingId > 0) {
            $batchParam = $stitchingId > 0 ? "?stitching_batch_id={$stitchingId}" : "";
            $this->flash('info', 'Production garments must be inspected in Quality Control (QC) before entering live stock.');
            $this->redirect("/production/qc/create{$batchParam}");
            return;
        }

        // Auto-detect if itemId belongs to stitching_items (only if not explicitly a purchase tab)
        if ($itemId > 0 && empty($billId) && ($preferredTab === 'production' || empty($preferredTab))) {
            $this->ensureStitchingInwardSchema();
            $isStitching = DB::fetch("SELECT id, stitching_log_id FROM stitching_items WHERE id = ?", [$itemId]);
            if ($isStitching) {
                $stLogId = (int)($isStitching['stitching_log_id'] ?? 0);
                $batchParam = $stLogId > 0 ? "?stitching_batch_id={$stLogId}" : "";
                $this->flash('info', 'Production garments must be inspected in Quality Control (QC) before entering live stock.');
                $this->redirect("/production/qc/create{$batchParam}");
                return;
            }
        }

        if (!$billId && !$itemId) {
            $this->flash('error', 'Invalid request: No purchase bill or item specified for stock inwarding.');
            $this->redirect('/m/label_generation');
            return;
        }

        try {
            DB::beginTransaction();

            $targetTab = 'rolls';
            $billNo = '';

            if ($isProductAddingRequest && $selectedItemIds) {
                if ($billId <= 0 || count($selectedItemIds) > 100) {
                    throw new Exception('Choose up to 100 valid Product Adding lines from this bill.');
                }

                $placeholders = implode(',', array_fill(0, count($selectedItemIds), '?'));
                $selectedSql = "SELECT pbi.*, pb.bill_no, pb.concern_id AS b_cid, pb.source_module
                                FROM purchase_bill_items pbi
                                JOIN purchase_bills pb ON pb.id = pbi.purchase_bill_id
                                WHERE pb.id = ? AND pb.source_module = 'product_adding'
                                  AND pbi.id IN ({$placeholders})";
                $selectedParams = array_merge([$billId], $selectedItemIds);
                if ($cid) {
                    $selectedSql .= ' AND pbi.concern_id = ?';
                    $selectedParams[] = $cid;
                }
                $selectedSql .= ' ORDER BY pbi.id ASC';
                $selectedItems = DB::fetchAll($selectedSql, $selectedParams);
                if (count($selectedItems) !== count($selectedItemIds)) {
                    throw new Exception('One or more selected lines do not belong to this Product Adding bill.');
                }

                $concernId = (int)$selectedItems[0]['concern_id'];
                $billNo = (string)$selectedItems[0]['bill_no'];
                $hasRolls = false;
                $hasOtherItems = false;
                $hasFinishedGoods = false;
                foreach ($selectedItems as $selectedItem) {
                    $this->assertProductAddingLabelCoverage((int)$selectedItem['id']);
                    if (($selectedItem['inward_status'] ?? 'pending') === 'completed') {
                        throw new Exception("'{$selectedItem['item_name']}' has already been added to stock. Remove it from the selection and try again.");
                    }
                    if ($selectedItem['section_type'] === 'finished_goods' && empty($selectedItem['variant_id'])) {
                        throw new Exception("Finished good '{$selectedItem['item_name']}' must be mapped to a catalog variant before adding it to stock.");
                    }
                    $hasRolls = $hasRolls || $selectedItem['section_type'] === 'rolls';
                    $hasOtherItems = $hasOtherItems || $selectedItem['section_type'] === 'garment_other';
                    $hasFinishedGoods = $hasFinishedGoods || $selectedItem['section_type'] === 'finished_goods';
                }

                foreach ($selectedItems as $selectedItem) {
                    DB::query(
                        "UPDATE purchase_bill_items SET inward_status = 'completed' WHERE id = ? AND purchase_bill_id = ?",
                        [(int)$selectedItem['id'], $billId]
                    );
                    if ($selectedItem['section_type'] === 'rolls') {
                        $hasRolls = true;
                    } elseif ($selectedItem['section_type'] === 'finished_goods') {
                        $this->inwardProductAddingFinishedGood($concernId, $selectedItem, $billNo);
                    }
                }

                if ($hasRolls) {
                    RollRegistryService::syncSpecs($concernId);
                }
                $pendingCount = (int)DB::fetchValue(
                    "SELECT COUNT(*) FROM purchase_bill_items WHERE purchase_bill_id = ? AND inward_status = 'pending'",
                    [$billId]
                );
                DB::query(
                    "UPDATE purchase_bills SET inward_status = ? WHERE id = ?",
                    [$pendingCount === 0 ? 'completed' : 'pending', $billId]
                );
                if (DB::pdo()->inTransaction()) {
                    DB::commit();
                }

                $successMessage = count($selectedItems) . " selected line(s) from Bill '{$billNo}' added to stock successfully.";
                if ($hasFinishedGoods && ($hasRolls || $hasOtherItems)) {
                    $successMessage .= ' Finished goods are available in Stocks; rolls and other garments are in Purchase Stocks.';
                }
                $this->flash('success', $successMessage);
                $redirectUrl = '/m/product_adding?bill_id=' . $billId;
                if ($hasFinishedGoods && !$hasRolls && !$hasOtherItems) {
                    $redirectUrl = '/m/stocks';
                } elseif (!$hasFinishedGoods && ($hasRolls || $hasOtherItems)) {
                    $purchaseStockTab = $hasRolls && !$hasOtherItems
                        ? 'rolls'
                        : (!$hasRolls && $hasOtherItems ? 'garment_other' : '');
                    $redirectUrl = '/m/purchase_stocks' . ($purchaseStockTab !== '' ? '?tab=' . $purchaseStockTab : '');
                }
                $this->redirect($redirectUrl);
                return;
            }

            // Priority 1: Inward individual item strictly when item_id is provided
            if ($itemId > 0) {
                $itemSql = "SELECT pbi.*, pb.bill_no, pb.concern_id AS b_cid, pb.source_module
                            FROM purchase_bill_items pbi 
                            JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id 
                            WHERE pbi.id = ?";
                $itemParams = [$itemId];
                if ($cid) {
                    $itemSql .= " AND pbi.concern_id = ?";
                    $itemParams[] = $cid;
                }
                $item = DB::fetch($itemSql, $itemParams);
                if (!$item) {
                    throw new Exception("Purchase item #{$itemId} not found or access denied.");
                }

                if (($item['source_module'] ?? '') === 'product_adding') {
                    $this->assertProductAddingLabelCoverage($itemId);
                }
                if (($item['inward_status'] ?? '') === 'completed') {
                    $itemLabel = ($item['section_type'] === 'rolls') ? 'Fabric roll' : 'Purchase item';
                    throw new Exception("{$itemLabel} '{$item['item_name']}' has already been added to stock.");
                }
                if ($item['section_type'] === 'finished_goods' && empty($item['variant_id'])) {
                    throw new Exception("Finished good '{$item['item_name']}' must be mapped to a catalog variant before adding it to stock.");
                }

                $concernId = (int)$item['concern_id'];
                $billId = (int)$item['purchase_bill_id'];
                $billNo = $item['bill_no'];

                DB::query("UPDATE purchase_bill_items SET inward_status = 'completed' WHERE id = ?", [$itemId]);

                // Check remaining pending items across this bill
                $pendingCount = (int)DB::fetchValue(
                    "SELECT COUNT(*) FROM purchase_bill_items WHERE purchase_bill_id = ? AND inward_status = 'pending'",
                    [$billId]
                );
                if ($pendingCount === 0) {
                    DB::query("UPDATE purchase_bills SET inward_status = 'completed' WHERE id = ?", [$billId]);
                } else {
                    DB::query("UPDATE purchase_bills SET inward_status = 'pending' WHERE id = ?", [$billId]);
                }

                if ($item['section_type'] === 'rolls') {
                    RollRegistryService::syncSpecs($concernId);
                    $targetTab = 'rolls';
                } elseif ($item['section_type'] === 'garment_other') {
                    $targetTab = 'garment_other';
                } elseif ($item['section_type'] === 'finished_goods') {
                    $targetTab = 'finished_goods';
                    if (($item['source_module'] ?? '') === 'product_adding') {
                        $this->inwardProductAddingFinishedGood($concernId, $item, $billNo);
                    } else {
                        $this->inwardPurchaseFinishedGood($concernId, $item, $billNo);
                    }
                }

                if ($preferredTab && in_array($preferredTab, ['rolls', 'garment_other', 'finished_goods'], true)) {
                    $targetTab = $preferredTab;
                }

                if (DB::pdo()->inTransaction()) {
                    DB::commit();
                }

                $this->flash('success', "Item '{$item['item_name']}' from Bill '{$billNo}' added to stock successfully!");
                $this->redirect($targetTab === 'finished_goods' ? '/m/stocks' : "/m/purchase_stocks?tab={$targetTab}");
                return;
            }

            // Priority 2: Inward at bill level (restricted to specific section if tab is specified)
            if ($billId > 0) {
                $billSql = "SELECT * FROM purchase_bills WHERE id = ?";
                $billParams = [$billId];
                if ($cid) {
                    $billSql .= " AND concern_id = ?";
                    $billParams[] = $cid;
                }
                $bill = DB::fetch($billSql, $billParams);
                if (!$bill) {
                    throw new Exception("Purchase bill #{$billId} not found or access denied.");
                }

                if (($bill['source_module'] ?? '') === 'product_adding') {
                    $pendingSql = "SELECT id FROM purchase_bill_items
                                   WHERE purchase_bill_id = ?
                                     AND COALESCE(inward_status, 'pending') != 'completed'";
                    $pendingParams = [$billId];
                    if ($preferredTab && in_array($preferredTab, ['rolls', 'garment_other', 'finished_goods'], true)) {
                        $pendingSql .= " AND section_type = ?";
                        $pendingParams[] = $preferredTab;
                    }
                    foreach (DB::fetchAll($pendingSql, $pendingParams) as $pendingItem) {
                        $this->assertProductAddingLabelCoverage((int)$pendingItem['id']);
                    }
                }

                $concernId = (int)$bill['concern_id'];
                $billNo = $bill['bill_no'];

                $sectionFilter = null;
                $sectionLabel = 'All items';
                if ($preferredTab === 'rolls') {
                    $sectionFilter = 'rolls';
                    $sectionLabel = 'Fabric rolls';
                    $targetTab = 'rolls';
                } elseif ($preferredTab === 'garment_other') {
                    $sectionFilter = 'garment_other';
                    $sectionLabel = 'Other garments & trims';
                    $targetTab = 'garment_other';
                } elseif ($preferredTab === 'finished_goods') {
                    $sectionFilter = 'finished_goods';
                    $sectionLabel = 'Purchased finished goods';
                    $targetTab = 'finished_goods';
                }

                if ($sectionFilter !== null) {
                    if ($sectionFilter === 'finished_goods') {
                        $unmappedCount = (int) DB::fetchValue(
                            "SELECT COUNT(*) FROM purchase_bill_items
                             WHERE purchase_bill_id = ? AND section_type = 'finished_goods'
                               AND (variant_id IS NULL OR variant_id = 0)
                               AND COALESCE(inward_status, 'pending') != 'completed'",
                            [$billId]
                        );
                        if ($unmappedCount > 0) {
                            throw new Exception("Map all finished goods in this bill to catalog variants before adding them to stock.");
                        }
                    }
                    // Strictly inward ONLY items of the requested section
                    DB::query(
                        "UPDATE purchase_bill_items SET inward_status = 'completed' WHERE purchase_bill_id = ? AND section_type = ?",
                        [$billId, $sectionFilter]
                    );
                    $affectedItems = DB::fetchAll(
                        "SELECT * FROM purchase_bill_items WHERE purchase_bill_id = ? AND section_type = ?",
                        [$billId, $sectionFilter]
                    );
                } else {
                    // Blanket inward of all items
                    DB::query("UPDATE purchase_bill_items SET inward_status = 'completed' WHERE purchase_bill_id = ?", [$billId]);
                    $affectedItems = DB::fetchAll("SELECT * FROM purchase_bill_items WHERE purchase_bill_id = ?", [$billId]);
                }

                $hasRolls = false;
                foreach ($affectedItems as $it) {
                    if ($it['section_type'] === 'rolls') {
                        $hasRolls = true;
                    } elseif ($it['section_type'] === 'finished_goods' && !empty($it['variant_id'])) {
                        if (($bill['source_module'] ?? '') === 'product_adding') {
                            $this->inwardProductAddingFinishedGood($concernId, $it, $billNo);
                        } else {
                            $this->inwardPurchaseFinishedGood($concernId, $it, $billNo);
                        }
                    }
                }

                if ($hasRolls) {
                    RollRegistryService::syncSpecs($concernId);
                }

                // Check remaining pending items across all sections in the bill
                $pendingCount = (int)DB::fetchValue(
                    "SELECT COUNT(*) FROM purchase_bill_items WHERE purchase_bill_id = ? AND inward_status = 'pending'",
                    [$billId]
                );
                if ($pendingCount === 0) {
                    DB::query("UPDATE purchase_bills SET inward_status = 'completed' WHERE id = ?", [$billId]);
                } else {
                    DB::query("UPDATE purchase_bills SET inward_status = 'pending' WHERE id = ?", [$billId]);
                }

                if (DB::pdo()->inTransaction()) {
                    DB::commit();
                }

                $msg = ($pendingCount === 0)
                    ? "Purchase Bill '{$billNo}' items successfully added to stock! Purchase inventory updated."
                    : "{$sectionLabel} from Purchase Bill '{$billNo}' added to stock successfully! Other sections remain pending.";

                $this->flash('success', $msg);
                $this->redirect($isProductAddingRequest
                    ? '/m/product_adding?bill_id=' . $billId
                    : ($targetTab === 'finished_goods' ? '/m/stocks' : "/m/purchase_stocks?tab={$targetTab}"));
                return;
            }

        } catch (Exception $e) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            $this->flash('error', 'Error adding item to stock: ' . $e->getMessage());
            $this->redirect($isProductAddingRequest
                ? '/m/product_adding?bill_id=' . $billId
                : '/m/label_generation' . ($billId ? "?bill_id={$billId}" : ''));
        }
    }

    public function addProductAddingToStock(): void
    {
        PurchaseBillSourceService::ensureSchema();
        $billId = (int)($_POST['bill_id'] ?? 0);
        $rawItemIds = $_POST['item_ids'] ?? [];
        if (!is_array($rawItemIds)) {
            $rawItemIds = [];
        }
        if (isset($_POST['item_id'])) {
            $rawItemIds[] = $_POST['item_id'];
        }
        $itemIds = array_values(array_unique(array_filter(
            array_map('intval', $rawItemIds),
            static fn(int $id): bool => $id > 0
        )));
        $cid = Auth::concernId();
        if ($billId <= 0 || !$itemIds || count($itemIds) > 100) {
            $this->flash('error', 'Choose up to 100 valid Product Adding lines to add to stock.');
            $this->redirect($billId > 0 ? '/m/product_adding?bill_id=' . $billId : '/m/product_adding');
            return;
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $sql = "SELECT pbi.id
                FROM purchase_bill_items pbi
                JOIN purchase_bills pb ON pb.id = pbi.purchase_bill_id
                WHERE pb.id = ? AND pb.source_module = 'product_adding'
                  AND pbi.id IN ({$placeholders})";
        $params = array_merge([$billId], $itemIds);
        if ($cid) {
            $sql .= ' AND pbi.concern_id = ?';
            $params[] = $cid;
        }
        if (count(DB::fetchAll($sql, $params)) !== count($itemIds)) {
            $this->flash('error', 'One or more selected lines do not belong to this Product Adding bill.');
            $this->redirect('/m/product_adding?bill_id=' . $billId);
            return;
        }
        $_POST['product_adding'] = '1';
        $this->addToStock();
    }

    private function assertProductAddingLabelCoverage(int $itemId): void
    {
        $item = DB::fetch(
            "SELECT pbi.*, COALESCE(pso.label_job_baseline_id, 0) AS label_job_baseline_id
             FROM purchase_bill_items pbi
             LEFT JOIN purchase_stock_item_overrides pso
               ON pso.purchase_bill_item_id = pbi.id AND pso.concern_id = pbi.concern_id
             WHERE pbi.id = ?",
            [$itemId]
        );
        if (!$item) {
            throw new Exception("Product Adding line #{$itemId} was not found.");
        }
        $item = PurchaseStockItemOverrideService::applyToItems([$item], false)[0];
        $requiredQty = max(1, (int)ceil((float)$item['qty']));
        $labelType = $item['section_type'] === 'finished_goods' ? 'purchase_fg' : 'purchase_raw';
        $generatedQty = (int)DB::fetchValue(
            "SELECT COALESCE(SUM(qty), 0)
             FROM label_jobs
             WHERE concern_id = ? AND ref_id = ? AND label_type = ?
               AND source_module = 'product_adding' AND id > ?",
            [
                (int)$item['concern_id'],
                $itemId,
                $labelType,
                (int)($item['label_job_baseline_id'] ?? 0),
            ]
        );
        if ($generatedQty < $requiredQty) {
            throw new Exception(
                "Print at least {$requiredQty} labels for '{$item['item_name']}' before adding it to stock ({$generatedQty} generated)."
            );
        }
    }

    private function inwardPurchaseFinishedGood(int $concernId, array $item, string $billNo): void
    {
        $primaryVariantId = (int)($item['variant_id'] ?? 0);
        $catalogId = (int)($item['catalog_id'] ?? 0);
        if ($catalogId <= 0 && $primaryVariantId > 0) {
            $catalogId = (int)DB::fetchValue(
                'SELECT catalog_id FROM variants WHERE id = ? AND concern_id = ?',
                [$primaryVariantId, $concernId]
            );
        }

        $variants = [];
        if ($catalogId > 0) {
            $variants = DB::fetchAll(
                "SELECT id, attributes FROM variants
                 WHERE concern_id = ? AND catalog_id = ? AND status = 'active'
                 ORDER BY id ASC",
                [$concernId, $catalogId]
            );
        }
        if (!$variants) {
            $variants = [['id' => $primaryVariantId, 'attributes' => null]];
        }

        $allocations = self::allocatePurchaseStockQuantity((float)$item['qty'], $variants, $primaryVariantId);
        foreach ($allocations as $variantId => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $alreadyLedger = DB::fetchValue(
                "SELECT id FROM stock_ledger
                 WHERE concern_id = ? AND variant_id = ? AND ref_module = 'Purchase Inward Item' AND ref_id = ?
                 LIMIT 1",
                [$concernId, $variantId, (int)$item['id']]
            );
            if ($alreadyLedger) {
                continue;
            }

            StockService::move(
                $concernId,
                (int)$variantId,
                'Main Store',
                $quantity,
                0.00,
                'Purchase Inward Item',
                (int)$item['id'],
                "Item '{$item['item_name']}' inward from Bill #{$billNo}"
            );
        }
    }

    private function inwardProductAddingFinishedGood(int $concernId, array $item, string $billNo): void
    {
        $item = PurchaseStockItemOverrideService::applyToItems([$item], false)[0];
        $variantId = (int)($item['variant_id'] ?? 0);
        if ($variantId <= 0) {
            throw new Exception("Finished good '{$item['item_name']}' must be mapped to a catalog variant before adding it to stock.");
        }

        $alreadyLedger = DB::fetchValue(
            "SELECT id FROM stock_ledger
             WHERE concern_id = ? AND variant_id = ? AND ref_module = 'Purchase Inward Item' AND ref_id = ?
             LIMIT 1",
            [$concernId, $variantId, (int)$item['id']]
        );
        if ($alreadyLedger) {
            return;
        }

        StockService::move(
            $concernId,
            $variantId,
            'Main Store',
            (float)$item['qty'],
            0.00,
            'Purchase Inward Item',
            (int)$item['id'],
            "Product Adding item '{$item['item_name']}' inward from Bill #{$billNo}"
        );
    }

    private static function allocatePurchaseStockQuantity(float $quantity, array $variants, int $primaryVariantId): array
    {
        if ($quantity <= 0) {
            throw new Exception('Finished good purchase quantity must be greater than zero.');
        }

        $weights = [];
        foreach ($variants as $variant) {
            $variantId = (int)($variant['id'] ?? 0);
            $attributes = json_decode((string)($variant['attributes'] ?? ''), true);
            $weight = is_array($attributes) ? max(0, (float)($attributes['allocated_qty'] ?? 0)) : 0;
            if ($variantId > 0 && $weight > 0) {
                $weights[$variantId] = $weight;
            }
        }

        if (!$weights) {
            if ($primaryVariantId <= 0) {
                throw new Exception('Finished good must be mapped to a catalog variant before adding it to stock.');
            }
            return [$primaryVariantId => round($quantity, 2)];
        }

        $totalWeight = array_sum($weights);
        $totalCents = (int)round($quantity * 100);
        $assignedCents = 0;
        $remainders = [];
        $allocations = [];
        foreach ($weights as $variantId => $weight) {
            $exactCents = $totalCents * $weight / $totalWeight;
            $wholeCents = (int)floor($exactCents);
            $allocations[$variantId] = $wholeCents;
            $assignedCents += $wholeCents;
            $remainders[] = ['variant_id' => $variantId, 'fraction' => $exactCents - $wholeCents];
        }

        usort($remainders, static function (array $left, array $right): int {
            $fractionOrder = $right['fraction'] <=> $left['fraction'];
            return $fractionOrder !== 0
                ? $fractionOrder
                : ($left['variant_id'] <=> $right['variant_id']);
        });
        for ($remaining = $totalCents - $assignedCents, $index = 0; $remaining > 0; $remaining--, $index++) {
            $allocations[$remainders[$index % count($remainders)]['variant_id']]++;
        }

        foreach ($allocations as $variantId => $cents) {
            $allocations[$variantId] = $cents / 100;
        }
        return $allocations;
    }

    /**
     * Inward stitched production garments into Live Stocks (stocks & stock_ledger)
     */
    protected function inwardProductionToStock(int $itemId, int $stitchingId, ?int $cid): void {
        $this->ensureStitchingInwardSchema();
        try {
            DB::beginTransaction();

            $itemsToInward = [];

            if ($itemId > 0) {
                $sql = "SELECT si.*, sl.batch_no, sl.stitching_name, sl.concern_id AS log_cid, sl.program_id,
                               pp.program_no, pp.catalog_id AS prog_catalog_id,
                               cat.id AS cat_id, cat.category_id, cat.subcategory_id, cat.name AS catalog_name, cat.style_code
                        FROM stitching_items si
                        JOIN stitching_logs sl ON si.stitching_id = sl.id
                        LEFT JOIN production_programs pp ON sl.program_id = pp.id
                        LEFT JOIN cutting_items ci ON si.cutting_item_id = ci.id
                        LEFT JOIN program_rolls pr ON ci.program_roll_id = pr.id
                        LEFT JOIN catalogs cat ON (si.catalog_id = cat.id OR pp.catalog_id = cat.id OR pr.catalog_id = cat.id)
                        WHERE si.id = ?";
                $params = [$itemId];
                if ($cid) {
                    $sql .= " AND (sl.concern_id = ? OR sl.concern_id = 1 OR sl.concern_id IS NULL)";
                    $params[] = $cid;
                }
                $si = DB::fetch($sql, $params);
                if (!$si) {
                    throw new Exception("Stitched item #{$itemId} not found or access denied.");
                }
                if (($si['inward_status'] ?? '') === 'completed') {
                    throw new Exception("Stitched item #{$itemId} has already been added to stock.");
                }
                $itemsToInward[] = $si;
            } elseif ($stitchingId > 0) {
                $sql = "SELECT si.*, sl.batch_no, sl.stitching_name, sl.concern_id AS log_cid, sl.program_id,
                               pp.program_no, pp.catalog_id AS prog_catalog_id,
                               cat.id AS cat_id, cat.category_id, cat.subcategory_id, cat.name AS catalog_name, cat.style_code
                        FROM stitching_items si
                        JOIN stitching_logs sl ON si.stitching_id = sl.id
                        LEFT JOIN production_programs pp ON sl.program_id = pp.id
                        LEFT JOIN cutting_items ci ON si.cutting_item_id = ci.id
                        LEFT JOIN program_rolls pr ON ci.program_roll_id = pr.id
                        LEFT JOIN catalogs cat ON (si.catalog_id = cat.id OR pp.catalog_id = cat.id OR pr.catalog_id = cat.id)
                        WHERE si.stitching_id = ? AND (si.inward_status IS NULL OR si.inward_status != 'completed')";
                $params = [$stitchingId];
                if ($cid) {
                    $sql .= " AND (sl.concern_id = ? OR sl.concern_id = 1 OR sl.concern_id IS NULL)";
                    $params[] = $cid;
                }
                $itemsToInward = DB::fetchAll($sql, $params);
                if (empty($itemsToInward)) {
                    throw new Exception("No pending stitched items found for batch #{$stitchingId}.");
                }
            } else {
                throw new Exception("Invalid production stock inward request.");
            }

            $inwardedCount = 0;
            $totalQty = 0;
            $batchNo = '';
            $stitchingLogId = 0;
            $targetConcernId = 1;

            foreach ($itemsToInward as $item) {
                $itemConcernId = (int)($item['log_cid'] ?: ($cid ?: 1));
                $targetConcernId = $itemConcernId;
                $batchNo = $item['batch_no'];
                $stitchingLogId = (int)$item['stitching_id'];

                $variantId = !empty($item['variant_id']) ? (int)$item['variant_id'] : 0;

                // 1. Resolve or create variant
                if (!$variantId) {
                    $existingVar = DB::fetch(
                        "SELECT id FROM variants WHERE concern_id = ? AND sku_code = ?",
                        [$itemConcernId, $item['sku_code']]
                    );
                    if ($existingVar) {
                        $variantId = (int)$existingVar['id'];
                    } else {
                        // Create variant
                        $catId = (int)($item['category_id'] ?: 1);
                        $validCat = DB::fetchValue("SELECT id FROM categories WHERE id = ?", [$catId]);
                        if (!$validCat) {
                            $catId = (int)DB::fetchValue("SELECT id FROM categories ORDER BY id ASC LIMIT 1") ?: 1;
                        }
                        $subcatId = !empty($item['subcategory_id']) ? (int)$item['subcategory_id'] : null;
                        if ($subcatId && !DB::fetchValue("SELECT id FROM subcategories WHERE id = ?", [$subcatId])) {
                            $subcatId = null;
                        }
                        $catalogId = !empty($item['cat_id']) ? (int)$item['cat_id'] : (!empty($item['prog_catalog_id']) ? (int)$item['prog_catalog_id'] : null);
                        $mrp = (float)($item['base_price'] ?: 0.00);
                        $price = (float)($item['discount_price'] ?: $mrp);
                        $weight = (float)($item['per_pc_weight'] ?: 0.00);

                        DB::query(
                            "INSERT INTO variants (concern_id, catalog_id, sku_code, name, category_id, subcategory_id, size, weight, weight_unit, price, mrp, creation_date, status)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pcs', ?, ?, CURDATE(), 'active')",
                            [
                                $itemConcernId,
                                $catalogId,
                                $item['sku_code'],
                                $item['variant_name'],
                                $catId,
                                $subcatId,
                                $item['size'] ?: 'Standard',
                                $weight,
                                $price,
                                $mrp
                            ]
                        );
                        $variantId = (int)DB::lastInsertId();
                    }

                    // Save variant_id to stitching_items
                    DB::query("UPDATE stitching_items SET variant_id = ? WHERE id = ?", [$variantId, $item['id']]);
                }

                // 2. Add to Stock via StockService::move
                $qty = (float)$item['stitching_qty'];
                if ($qty > 0) {
                    // Check if already moved to stock ledger to avoid duplicates
                    $alreadyLedger = DB::fetchValue(
                        "SELECT id FROM stock_ledger WHERE concern_id = ? AND variant_id = ? AND ref_module = 'Production Inward' AND ref_id = ? LIMIT 1",
                        [$itemConcernId, $variantId, $stitchingLogId]
                    );

                    if (!$alreadyLedger) {
                        StockService::move(
                            $itemConcernId,
                            $variantId,
                            'Main Store',
                            $qty,
                            0.00,
                            'Production Inward',
                            $stitchingLogId,
                            "Stitched garment inward from Batch #{$batchNo} ({$item['sku_code']})"
                        );
                    }
                }

                // 3. Mark stitching_item as completed
                DB::query("UPDATE stitching_items SET inward_status = 'completed', inward_at = NOW() WHERE id = ?", [$item['id']]);

                $inwardedCount++;
                $totalQty += $qty;
            }

            // Check if all items in stitching_logs are now completed
            if ($stitchingLogId > 0) {
                $pendingCount = (int)DB::fetchValue(
                    "SELECT COUNT(*) FROM stitching_items WHERE stitching_id = ? AND (inward_status IS NULL OR inward_status != 'completed')",
                    [$stitchingLogId]
                );
                if ($pendingCount === 0) {
                    DB::query("UPDATE stitching_logs SET inward_status = 'completed', status = 'completed' WHERE id = ?", [$stitchingLogId]);
                } else {
                    DB::query("UPDATE stitching_logs SET inward_status = 'in_progress' WHERE id = ?", [$stitchingLogId]);
                }
            }

            if (DB::pdo()->inTransaction()) {
                DB::commit();
            }

            $itemDesc = count($itemsToInward) === 1 ? "'{$itemsToInward[0]['variant_name']}'" : "{$inwardedCount} variant items";
            $this->flash('success', "Stitched garment {$itemDesc} ({$totalQty} pcs) from Batch #{$batchNo} added to stock successfully! View below in Live Inventory Stocks.");
            $this->redirect("/m/stocks?concern_id={$targetConcernId}");
            return;
        } catch (\Throwable $e) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            $this->flash('error', 'Failed to add stitched production items to stock: ' . $e->getMessage());
            $this->redirect('/m/label_generation?tab=production');
            return;
        }
    }
}
