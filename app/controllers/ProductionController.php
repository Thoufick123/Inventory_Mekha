<?php
// app/controllers/ProductionController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Core\ModuleGuard;
use App\Services\StockService;
use App\Services\AuditLog;
use App\Services\QrService;
use Exception;

class ProductionController extends Controller {

    private static bool $schemaEnsured = false;

    /**
     * Self-healing schema guard ensuring all 3-tier hierarchy & catalog & program tables exist
     */
    private function ensureCatalogSchema(): void {
        if (self::$schemaEnsured) return;
        try {
            // 1. Ensure sub_subcategories exists
            DB::query("CREATE TABLE IF NOT EXISTS `sub_subcategories` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `concern_id` INT NOT NULL,
                `subcategory_id` INT NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `code` VARCHAR(20) NULL,
                `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_subsubcat_concern` (`concern_id`),
                INDEX `idx_subsubcat_subcat` (`subcategory_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 2. Ensure catalogs columns
            $catlgCols = array_column(DB::fetchAll('DESCRIBE `catalogs`'), 'Field');
            if (!in_array('style_code', $catlgCols)) {
                DB::query('ALTER TABLE `catalogs` ADD COLUMN style_code VARCHAR(50) NULL AFTER code');
            }
            if (!in_array('short_code', $catlgCols)) {
                DB::query('ALTER TABLE `catalogs` ADD COLUMN short_code VARCHAR(50) NULL AFTER style_code');
            }
            if (!in_array('category_id', $catlgCols)) {
                DB::query('ALTER TABLE `catalogs` ADD COLUMN category_id INT NULL AFTER short_code');
            }
            if (!in_array('subcategory_id', $catlgCols)) {
                DB::query('ALTER TABLE `catalogs` ADD COLUMN subcategory_id INT NULL AFTER category_id');
            }
            if (!in_array('sub_sub_category_id', $catlgCols)) {
                DB::query('ALTER TABLE `catalogs` ADD COLUMN sub_sub_category_id INT NULL AFTER subcategory_id');
            }
            if (!in_array('main_category_code', $catlgCols)) {
                DB::query('ALTER TABLE `catalogs` ADD COLUMN main_category_code VARCHAR(20) NULL AFTER sub_sub_category_id');
            }
            if (!in_array('sub_category_code', $catlgCols)) {
                DB::query('ALTER TABLE `catalogs` ADD COLUMN sub_category_code VARCHAR(20) NULL AFTER main_category_code');
            }
            if (!in_array('sub_sub_category_code', $catlgCols)) {
                DB::query('ALTER TABLE `catalogs` ADD COLUMN sub_sub_category_code VARCHAR(20) NULL AFTER sub_category_code');
            }
            if (!in_array('hierarchy_path', $catlgCols)) {
                DB::query('ALTER TABLE `catalogs` ADD COLUMN hierarchy_path VARCHAR(255) NULL AFTER sub_sub_category_code');
            }
            if (!in_array('hsn_code', $catlgCols)) {
                DB::query('ALTER TABLE `catalogs` ADD COLUMN hsn_code VARCHAR(50) NULL AFTER hierarchy_path');
            }
            if (!in_array('gst_rate', $catlgCols)) {
                DB::query('ALTER TABLE `catalogs` ADD COLUMN gst_rate DECIMAL(5,2) NULL DEFAULT NULL AFTER hsn_code');
            }
            DB::query('ALTER TABLE `catalogs` MODIFY COLUMN gst_rate DECIMAL(5,2) NULL DEFAULT NULL');
            if (!in_array('sizes', $catlgCols)) {
                DB::query('ALTER TABLE `catalogs` ADD COLUMN sizes TEXT NULL AFTER gst_rate');
            }
            if (!in_array('status', $catlgCols)) {
                DB::query("ALTER TABLE `catalogs` ADD COLUMN status ENUM('active', 'inactive', 'in_house') NOT NULL DEFAULT 'in_house' AFTER sizes");
            }
            if (!in_array('source_module', $catlgCols)) {
                DB::query("ALTER TABLE `catalogs` ADD COLUMN source_module VARCHAR(50) NOT NULL DEFAULT 'production_catalog' AFTER status");
            }
            DB::query("UPDATE `catalogs` SET source_module = 'product_adding' WHERE source_module = 'production_catalog' AND status = 'active'");
            $purchaseItemCols = array_column(DB::fetchAll('DESCRIBE `purchase_bill_items`'), 'Field');
            if (in_array('catalog_id', $purchaseItemCols, true) && in_array('section_type', $purchaseItemCols, true)) {
                DB::query("
                    UPDATE `catalogs` c
                    JOIN `purchase_bill_items` pbi ON pbi.catalog_id = c.id AND pbi.section_type = 'finished_goods'
                    SET c.source_module = 'product_adding'
                    WHERE c.source_module = 'production_catalog'
                ");
            }

            // 3. Ensure categories columns
            $catCols = array_column(DB::fetchAll('DESCRIBE `categories`'), 'Field');
            if (!in_array('code', $catCols)) {
                DB::query('ALTER TABLE `categories` ADD COLUMN code VARCHAR(20) NULL AFTER name');
            }
            if (!in_array('default_hsn', $catCols)) {
                DB::query('ALTER TABLE `categories` ADD COLUMN default_hsn VARCHAR(50) NULL AFTER code');
            }
            if (!in_array('default_gst_rate', $catCols)) {
                DB::query('ALTER TABLE `categories` ADD COLUMN default_gst_rate DECIMAL(5,2) NULL DEFAULT NULL AFTER default_hsn');
            }
            DB::query('ALTER TABLE `categories` MODIFY COLUMN default_gst_rate DECIMAL(5,2) NULL DEFAULT NULL');

            // 4. Ensure subcategories columns
            $subCols = array_column(DB::fetchAll('DESCRIBE `subcategories`'), 'Field');
            if (!in_array('code', $subCols)) {
                DB::query('ALTER TABLE `subcategories` ADD COLUMN code VARCHAR(20) NULL AFTER name');
            }

            // 5. Ensure variants table columns
            $varCols = array_column(DB::fetchAll('DESCRIBE `variants`'), 'Field');
            if (!in_array('catalog_id', $varCols)) {
                DB::query('ALTER TABLE `variants` ADD COLUMN catalog_id INT NULL AFTER concern_id');
                DB::query('ALTER TABLE `variants` ADD INDEX idx_var_catalog (catalog_id)');
            }
            DB::query('ALTER TABLE `variants` MODIFY COLUMN subcategory_id INT NULL');

            // 6. Ensure production_programs columns & program_rolls table
            $progCols = array_column(DB::fetchAll('DESCRIBE `production_programs`'), 'Field');
            if (!in_array('program_name', $progCols)) {
                DB::query('ALTER TABLE `production_programs` ADD COLUMN program_name VARCHAR(255) NULL AFTER program_no');
            }
            if (!in_array('planner_name', $progCols)) {
                DB::query('ALTER TABLE `production_programs` ADD COLUMN planner_name VARCHAR(150) NULL AFTER program_name');
            }
            if (!in_array('updated_at', $progCols)) {
                DB::query('ALTER TABLE `production_programs` ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP');
            }
            DB::query('ALTER TABLE `production_programs` MODIFY COLUMN target_variant_id INT NULL');
            DB::query('ALTER TABLE `production_programs` MODIFY COLUMN planned_qty DECIMAL(12,2) NOT NULL DEFAULT 0.00');

            DB::query("CREATE TABLE IF NOT EXISTS `program_rolls` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `concern_id` INT NOT NULL,
                `program_id` INT NOT NULL,
                `purchase_bill_item_id` INT NULL,
                `roll_piece_tag` VARCHAR(100) NULL,
                `fabric_name` VARCHAR(255) NULL,
                `color` VARCHAR(100) NULL,
                `unit` VARCHAR(50) NOT NULL DEFAULT 'kg',
                `total_roll_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `allocated_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `roll_weight` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `roll_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `target_pieces` INT NOT NULL DEFAULT 0,
                `category_id` INT NULL,
                `subcategory_id` INT NULL,
                `sub_sub_category_id` INT NULL,
                `catalog_id` INT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_prgroll_concern` (`concern_id`),
                INDEX `idx_prgroll_program` (`program_id`),
                INDEX `idx_prgroll_rollitem` (`purchase_bill_item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $prCols = array_column(DB::fetchAll('DESCRIBE `program_rolls`'), 'Field');
            if (!in_array('roll_weight', $prCols)) {
                DB::query('ALTER TABLE `program_rolls` ADD COLUMN roll_weight DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER allocated_qty');
            }
            if (!in_array('roll_price', $prCols)) {
                DB::query('ALTER TABLE `program_rolls` ADD COLUMN roll_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER roll_weight');
            }

            // Align table collations to prevent "1267 Illegal mix of collations" error
            try {
                $alignTables = [
                    'purchase_bill_items',
                    'purchase_bills',
                    'purchase_damages',
                    'purchase_damage_quarantine',
                    'purchase_damage_reasons',
                    'program_rolls',
                    'production_programs',
                    'catalogs',
                    'categories',
                    'subcategories',
                    'sub_subcategories',
                    'variants',
                    'batches',
                    'batch_items',
                    'qc_inspections',
                    'qc_inspection_items',
                    'qc_piece_items',
                    'suppliers'
                ];
                foreach ($alignTables as $tbl) {
                    try {
                        DB::query("ALTER TABLE `{$tbl}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    } catch (\Throwable $t) {}
                }
            } catch (\Throwable $colEx) {
                // Ignore if lacks alter privileges
            }

            self::$schemaEnsured = true;
        } catch (\Throwable $e) {
            error_log("Schema ensure error: " . $e->getMessage());
        }
    }

    // ========================================================
    // 1. PRODUCTION CATALOG (MANUFACTURED GARMENT STYLES)
    // ========================================================

    /**
     * Display Production Catalog list view matching Image 1
     */
    public function catalogProgram(): void {
        ModuleGuard::enforce('catalog_program', 'view');
        $this->ensureCatalogSchema();
        $cid = Auth::concernId();

        // Filters
        $search = trim($_GET['search'] ?? '');
        $catFilter = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $subcatFilter = !empty($_GET['subcategory_id']) ? (int)$_GET['subcategory_id'] : null;

        // Build Catalog query
        $sql = "SELECT c.*, 
                       cat.name AS category_name, cat.code AS category_code_ref,
                       sub.name AS subcategory_name, sub.code AS subcategory_code_ref,
                       subsub.name AS subsub_name,
                       con.name AS concern_name, con.code AS concern_code,
                       (SELECT COUNT(*) FROM variants v WHERE v.catalog_id = c.id AND v.status = 'active') AS active_variants_count
                FROM catalogs c
                LEFT JOIN categories cat ON c.category_id = cat.id
                LEFT JOIN subcategories sub ON c.subcategory_id = sub.id
                LEFT JOIN sub_subcategories subsub ON c.sub_sub_category_id = subsub.id
                LEFT JOIN concerns con ON c.concern_id = con.id
                WHERE c.source_module = 'production_catalog'";
        
        $params = [];
        if ($cid) {
            $sql .= " AND c.concern_id = ?";
            $params[] = $cid;
        }

        if (!empty($search)) {
            $sql .= " AND (c.style_code LIKE ? OR c.short_code LIKE ? OR c.name LIKE ? OR c.hierarchy_path LIKE ? OR c.hsn_code LIKE ?)";
            $term = "%{$search}%";
            $params = array_merge($params, [$term, $term, $term, $term, $term]);
        }

        if ($catFilter) {
            $sql .= " AND c.category_id = ?";
            $params[] = $catFilter;
        }

        if ($subcatFilter) {
            $sql .= " AND c.subcategory_id = ?";
            $params[] = $subcatFilter;
        }

        $sql .= " ORDER BY c.id DESC";
        $styles = DB::fetchAll($sql, $params);

        // Fetch categories and subcategories for filter dropdowns
        if ($cid) {
            $categories = DB::fetchAll("SELECT * FROM categories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND status = 'active' ORDER BY name ASC", [$cid]);
            $subcategories = DB::fetchAll("SELECT * FROM subcategories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND status = 'active' ORDER BY name ASC", [$cid]);
            $variants = DB::fetchAll("SELECT id, product_code, name FROM variants WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND status = 'active'", [$cid]);
            $catalogs = DB::fetchAll("SELECT id, name, style_code FROM catalogs WHERE source_module = 'production_catalog' AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) ORDER BY id DESC", [$cid]);
        } else {
            $categories = DB::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC");
            $subcategories = DB::fetchAll("SELECT * FROM subcategories WHERE status = 'active' ORDER BY name ASC");
            $variants = DB::fetchAll("SELECT id, product_code, name FROM variants WHERE status = 'active'");
            $catalogs = DB::fetchAll("SELECT id, name, style_code FROM catalogs WHERE source_module = 'production_catalog' ORDER BY id DESC");
        }

        // Fetch Production Programs register for the tab
        $progSql = "SELECT pp.*, c.name AS catalog_name, v.name AS target_variant_name, v.product_code,
                           con.name AS concern_name, con.code AS concern_code,
                           (SELECT COUNT(*) FROM program_rolls pr WHERE pr.program_id = pp.id) AS rolls_count,
                           (SELECT GROUP_CONCAT(DISTINCT COALESCE(rc.name, pc.name) SEPARATOR ', ')
                            FROM program_rolls pr
                            LEFT JOIN catalogs rc ON pr.catalog_id = rc.id AND rc.source_module = 'production_catalog'
                                AND (rc.concern_id = pr.concern_id OR rc.concern_id = 1 OR rc.concern_id IS NULL)
                            LEFT JOIN catalogs pc ON pc.id = pp.catalog_id AND pc.source_module = 'production_catalog'
                                AND (pc.concern_id = pp.concern_id OR pc.concern_id = 1 OR pc.concern_id IS NULL)
                            WHERE pr.program_id = pp.id) AS roll_catalog_names
                    FROM production_programs pp
                    LEFT JOIN catalogs c ON pp.catalog_id = c.id AND c.source_module = 'production_catalog'
                        AND (c.concern_id = pp.concern_id OR c.concern_id = 1 OR c.concern_id IS NULL)
                    LEFT JOIN variants v ON pp.target_variant_id = v.id
                    LEFT JOIN concerns con ON pp.concern_id = con.id
                    WHERE (pp.catalog_id IS NULL OR c.id IS NOT NULL)";
        $progParams = [];
        if ($cid) {
            $progSql .= " AND pp.concern_id = ?";
            $progParams[] = $cid;
        }
        $progSql .= " ORDER BY pp.id DESC";
        $programs = DB::fetchAll($progSql, $progParams);

        $programIds = array_column($programs, 'id');
        $programRollsMap = [];
        if (!empty($programIds)) {
            $inClause = implode(',', array_map('intval', $programIds));
            $allRolls = DB::fetchAll("
                SELECT pr.*, 
                       pbi.item_code AS original_piece_tag, pbi.item_name AS roll_material, pbi.color AS roll_color, pbi.unit AS roll_unit, pbi.qty AS original_roll_qty,
                       cat.name AS category_name, sub.name AS subcategory_name, subsub.name AS subsub_name,
                       COALESCE(c.name, CONCAT_WS(' > ', cat.name, sub.name, subsub.name)) AS catalog_style_name,
                       c.style_code AS catalog_style_code
                FROM program_rolls pr
                LEFT JOIN purchase_bill_items pbi ON pr.purchase_bill_item_id = pbi.id
                LEFT JOIN categories cat ON pr.category_id = cat.id
                LEFT JOIN subcategories sub ON pr.subcategory_id = sub.id
                LEFT JOIN sub_subcategories subsub ON pr.sub_sub_category_id = subsub.id
                LEFT JOIN catalogs c ON pr.catalog_id = c.id AND c.source_module = 'production_catalog'
                    AND (c.concern_id = pr.concern_id OR c.concern_id = 1 OR c.concern_id IS NULL)
                WHERE pr.program_id IN ($inClause)
                ORDER BY pr.id ASC
            ");
            foreach ($allRolls as $r) {
                $programRollsMap[(int)$r['program_id']][] = $r;
            }
        }
        foreach ($programs as &$p) {
            $p['rolls'] = $programRollsMap[(int)$p['id']] ?? [];
        }
        unset($p);

        $this->render('concern/catalog_program', [
            'pageTitle'     => 'Production Catalog',
            'currentModule' => 'catalog_program',
            'styles'        => $styles,
            'programs'      => $programs,
            'categories'    => $categories,
            'subcategories' => $subcategories,
            'variants'      => $variants,
            'catalogs'      => $catalogs,
            'selectedCid'   => $cid,
            'filters'       => [
                'search'         => $search,
                'category_id'    => $catFilter,
                'subcategory_id' => $subcatFilter,
            ],
        ]);
    }

    /**
     * Create Product Style Form matching Image 2
     */
    public function createStyle(): void {
        ModuleGuard::enforce('catalog_program', 'add');
        $this->ensureCatalogSchema();
        $cid = Auth::concernId();

        if ($cid) {
            $categories = DB::fetchAll("SELECT * FROM categories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND status = 'active' ORDER BY name ASC", [$cid]);
            $hsnList = DB::fetchAll("SELECT * FROM hsn_masters WHERE is_active = 1 AND code_type = 'HSN' AND (concern_id = ? OR concern_id IS NULL) ORDER BY hsn_code ASC", [$cid]);
        } else {
            $categories = DB::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC");
            $hsnList = DB::fetchAll("SELECT * FROM hsn_masters WHERE is_active = 1 AND code_type = 'HSN' ORDER BY hsn_code ASC");
        }
        $attributeSizes = DB::fetchAll("SELECT attribute_value, MIN(sort_order) AS sort_order, MIN(id) AS min_id FROM product_attributes WHERE attribute_type = 'size' AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) GROUP BY attribute_value ORDER BY sort_order ASC, min_id ASC", [$cid ?: 1]);

        $this->render('concern/catalog_create', [
            'pageTitle'      => 'Add Product Style',
            'currentModule'  => 'catalog_program',
            'categories'     => $categories,
            'hsnList'        => $hsnList,
            'attributeSizes' => $attributeSizes,
            'selectedCid'    => $cid,
            'allConcerns'    => Auth::isSuperAdmin()
                ? DB::fetchAll("SELECT id, name, code FROM concerns WHERE status = 'active' ORDER BY name ASC")
                : [],
            'isSuperAdmin'   => Auth::isSuperAdmin(),
            'isEdit'         => false,
            'style'          => null,
        ]);
    }

    /**
     * Edit Product Style Form matching Image 2
     */
    public function editStyle(): void {
        $this->ensureCatalogSchema();
        $cid = Auth::concernId();
        $id = (int)($_GET['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid Product Style ID.');
            $this->redirect('/production/catalog');
        }

        $sql = "SELECT * FROM catalogs WHERE id = ? AND source_module = 'production_catalog'";
        $params = [$id];
        if ($cid) {
            $sql .= " AND concern_id = ?";
            $params[] = $cid;
        }
        $style = DB::fetch($sql, $params);

        if (!$style) {
            $this->flash('error', 'Product Style not found or access denied.');
            $this->redirect('/production/catalog');
        }

        if ($cid) {
            $categories = DB::fetchAll("SELECT * FROM categories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND status = 'active' ORDER BY name ASC", [$cid]);
            $hsnList = DB::fetchAll("SELECT * FROM hsn_masters WHERE is_active = 1 AND code_type = 'HSN' AND (concern_id = ? OR concern_id IS NULL) ORDER BY hsn_code ASC", [$cid]);
            $subcategories = $style['category_id'] ? DB::fetchAll("SELECT * FROM subcategories WHERE category_id = ? AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) ORDER BY name ASC", [$style['category_id'], $cid]) : [];
            $subSubcategories = $style['subcategory_id'] ? DB::fetchAll("SELECT * FROM sub_subcategories WHERE subcategory_id = ? AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) ORDER BY name ASC", [$style['subcategory_id'], $cid]) : [];
        } else {
            $categories = DB::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC");
            $hsnList = DB::fetchAll("SELECT * FROM hsn_masters WHERE is_active = 1 AND code_type = 'HSN' ORDER BY hsn_code ASC");
            $subcategories = $style['category_id'] ? DB::fetchAll("SELECT * FROM subcategories WHERE category_id = ? ORDER BY name ASC", [$style['category_id']]) : [];
            $subSubcategories = $style['subcategory_id'] ? DB::fetchAll("SELECT * FROM sub_subcategories WHERE subcategory_id = ? ORDER BY name ASC", [$style['subcategory_id']]) : [];
        }

        $attributeSizes = DB::fetchAll("SELECT attribute_value, MIN(sort_order) AS sort_order, MIN(id) AS min_id FROM product_attributes WHERE attribute_type = 'size' AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) GROUP BY attribute_value ORDER BY sort_order ASC, min_id ASC", [$cid ?: 1]);

        $this->render('concern/catalog_create', [
            'pageTitle'        => 'Edit Product Style',
            'currentModule'    => 'catalog_program',
            'categories'       => $categories,
            'subcategories'    => $subcategories,
            'subSubcategories' => $subSubcategories,
            'hsnList'          => $hsnList,
            'attributeSizes'   => $attributeSizes,
            'selectedCid'      => $cid,
            'allConcerns'      => Auth::isSuperAdmin()
                ? DB::fetchAll("SELECT id, name, code FROM concerns WHERE status = 'active' ORDER BY name ASC")
                : [],
            'isSuperAdmin'     => Auth::isSuperAdmin(),
            'isEdit'           => true,
            'style'            => $style,
        ]);
    }

    /**
     * Store new Product Style with comprehensive field validations
     */
    public function storeStyle(): void {
        ModuleGuard::enforce('catalog_program', 'add');
        $this->ensureCatalogSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();

        // 1. Validate Category Hierarchy
        $categoryId = (int)($_POST['category_id'] ?? 0);
        if ($categoryId <= 0) {
            $this->flash('error', 'Main Category is required. Please select a valid Category.');
            $this->redirect('/production/catalog/create');
        }

        $categorySql = "SELECT * FROM categories WHERE id = ? AND status = 'active'";
        $categoryParams = [$categoryId];
        if ($cid) {
            $categorySql .= " AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)";
            $categoryParams[] = $cid;
        }
        $cat = DB::fetch($categorySql, $categoryParams);
        if (!$cat) {
            $this->flash('error', 'Selected Main Category does not exist or does not belong to your concern.');
            $this->redirect('/production/catalog/create');
        }
        if (!$cid) {
            $cid = (int)($_POST['concern_id'] ?? 0);
            if ($cid <= 0) {
                $cid = (int)($cat['concern_id'] ?? 0);
            }
        }
        if ($cid <= 0 || !DB::fetchValue("SELECT id FROM concerns WHERE id = ? AND status = 'active'", [$cid])) {
            $this->flash('error', 'Select a valid concern before saving this product style.');
            $this->redirect('/production/catalog/create');
        }
        $categoryConcernId = (int)($cat['concern_id'] ?? 0);
        if ($categoryConcernId > 1 && $categoryConcernId !== $cid) {
            $this->flash('error', 'Selected Main Category does not belong to the selected concern.');
            $this->redirect('/production/catalog/create');
        }

        $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $subSubcategoryId = !empty($_POST['sub_sub_category_id']) ? (int)$_POST['sub_sub_category_id'] : null;
        if ($subcategoryId) {
            $subcategory = DB::fetch(
                "SELECT id, category_id, concern_id, name
                 FROM subcategories
                 WHERE id = ? AND category_id = ? AND status = 'active'
                   AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
                [$subcategoryId, $categoryId, $cid]
            );
            if (!$subcategory) {
                $this->flash('error', 'Selected Sub Category does not belong to the chosen Main Category.');
                $this->redirect('/production/catalog/create');
            }
        }
        if ($subSubcategoryId) {
            if (!$subcategoryId) {
                $this->flash('error', 'Choose a Sub Category before selecting a Sub Sub Category.');
                $this->redirect('/production/catalog/create');
            }
            $subSubcategory = DB::fetch(
                "SELECT id
                 FROM sub_subcategories
                 WHERE id = ? AND subcategory_id = ? AND status = 'active'
                   AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
                [$subSubcategoryId, $subcategoryId, $cid]
            );
            if (!$subSubcategory) {
                $this->flash('error', 'Selected Sub Sub Category does not belong to the chosen Sub Category.');
                $this->redirect('/production/catalog/create');
            }
        }

        // Category Codes Validation
        $mainCatCode = strtoupper(trim($_POST['main_category_code'] ?? ''));
        $subCatCode = strtoupper(trim($_POST['sub_category_code'] ?? ''));
        $subSubCatCode = strtoupper(trim($_POST['sub_sub_category_code'] ?? ''));
        $wholeCode = strtoupper(trim($_POST['whole_code'] ?? ''));

        if (empty($mainCatCode)) {
            $this->flash('error', 'Main Category short code is required.');
            $this->redirect('/production/catalog/create');
        }

        if (empty($wholeCode)) {
            $wholeCode = $mainCatCode . $subCatCode . $subSubCatCode;
        }

        if (strlen($wholeCode) < 1 || strlen($wholeCode) > 30) {
            $this->flash('error', 'Whole code for QR code must be between 1 and 30 characters.');
            $this->redirect('/production/catalog/create');
        }

        // 2. Validate HSN & GST Tax Classification
        $hsnCode = trim($_POST['hsn_code'] ?? '');
        $gstRateInput = trim((string)($_POST['gst_rate'] ?? ''));

        if (!preg_match('/^\d{4}(?:\d{2}){0,2}$/', $hsnCode)) {
            $this->flash('error', 'Enter a valid 4-, 6-, or 8-digit HSN code.');
            $this->redirect('/production/catalog/create');
        }

        if ($gstRateInput === '' || !is_numeric($gstRateInput) || (float)$gstRateInput < 0 || (float)$gstRateInput > 100) {
            $this->flash('error', 'GST Rate must be between 0.00% and 100.00%.');
            $this->redirect('/production/catalog/create');
        }
        $gstRate = (float)$gstRateInput;

        // 3. Validate Product Attributes (Sizes)
        $sizes = $_POST['sizes'] ?? [];
        if (!is_array($sizes) || empty($sizes)) {
            $this->flash('error', 'At least one size attribute must be selected.');
            $this->redirect('/production/catalog/create');
        }

        $cleanSizes = [];
        foreach ($sizes as $s) {
            $val = trim($s);
            if ($val !== '' && !in_array($val, $cleanSizes)) {
                $cleanSizes[] = $val;
            }
        }

        if (empty($cleanSizes)) {
            $this->flash('error', 'Please select at least one valid size.');
            $this->redirect('/production/catalog/create');
        }

        // Resolve Hierarchy Path & Product Style Name
        $pathParts = [$cat['name']];
        if ($subcategoryId) {
            $sub = DB::fetch("SELECT name FROM subcategories WHERE id = ?", [$subcategoryId]);
            if ($sub) $pathParts[] = $sub['name'];
        }
        if ($subSubcategoryId) {
            $subsub = DB::fetch("SELECT name FROM sub_subcategories WHERE id = ?", [$subSubcategoryId]);
            if ($subsub) $pathParts[] = $subsub['name'];
        }
        $hierarchyPath = implode(' > ', $pathParts);
        $styleName = trim($_POST['name'] ?? '') ?: implode(' - ', $pathParts);

        // Generate Style Code e.g. GRM-STY-0001
        $con = DB::fetch("SELECT code FROM concerns WHERE id = ?", [$cid]);
        $conPrefix = 'STY';
        if ($con && !empty($con['code'])) {
            $cleanConCode = preg_replace('/[^A-Za-z0-9]/', '', str_replace(['CON-', 'CON'], '', strtoupper($con['code'])));
            $conPrefix = $cleanConCode ?: 'STY';
        }

        DB::beginTransaction();
        try {
            $nextSeq = (int)DB::fetchValue("SELECT COUNT(*) FROM catalogs WHERE concern_id = ?", [$cid]) + 1;
            $styleCode = sprintf("%s-STY-%04d", $conPrefix, $nextSeq);

            $dup = DB::fetch("SELECT id FROM catalogs WHERE concern_id = ? AND style_code = ?", [$cid, $styleCode]);
            if ($dup) {
                $styleCode = sprintf("%s-STY-%04d", $conPrefix, $nextSeq + rand(10, 99));
            }

            // Insert into catalogs
            DB::query(
                "INSERT INTO catalogs (
                    concern_id, code, style_code, short_code, source_module,
                    category_id, subcategory_id, sub_sub_category_id,
                    main_category_code, sub_category_code, sub_sub_category_code,
                    hierarchy_path, hsn_code, gst_rate, sizes, status, name, created_at
                ) VALUES (?, ?, ?, ?, 'production_catalog', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_house', ?, NOW())",
                [
                    $cid,
                    $styleCode,
                    $styleCode,
                    $wholeCode,
                    $categoryId,
                    $subcategoryId,
                    $subSubcategoryId,
                    $mainCatCode,
                    $subCatCode,
                    $subSubCatCode,
                    $hierarchyPath,
                    $hsnCode,
                    $gstRate,
                    json_encode($cleanSizes),
                    $styleName,
                ]
            );
            $catalogId = (int)DB::lastInsertId();

            // Create Variants for each selected size
            foreach ($cleanSizes as $size) {
                $sku = "{$styleCode}-{$size}";
                $varName = "{$styleName} - {$size}";

                $existVar = DB::fetch("SELECT id FROM variants WHERE concern_id = ? AND product_code = ?", [$cid, $sku]);
                if (!$existVar) {
                    DB::query(
                        "INSERT INTO variants (
                            concern_id, catalog_id, product_code, name, 
                            category_id, subcategory_id, size, 
                            price, mrp, creation_date, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, 0.00, CURDATE(), 'active', NOW())",
                        [
                            $cid,
                            $catalogId,
                            $sku,
                            $varName,
                            $categoryId,
                            $subcategoryId ?: null,
                            $size,
                        ]
                    );
                } else {
                    DB::query("UPDATE variants SET catalog_id = ?, status = 'active' WHERE id = ?", [$catalogId, $existVar['id']]);
                }
            }

            // Audit Log
            AuditLog::log(
                'CREATE',
                'catalogs',
                $catalogId,
                null,
                [
                    'style_code'     => $styleCode,
                    'name'           => $styleName,
                    'hierarchy_path' => $hierarchyPath,
                    'hsn_code'       => $hsnCode,
                    'gst_rate'       => $gstRate,
                    'sizes'          => $cleanSizes,
                ]
            );

            DB::commit();
            $this->flash('success', "Product Style '{$styleCode}' ({$styleName}) saved successfully with " . count($cleanSizes) . " variants.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to save product style: ' . $e->getMessage());
            $this->redirect('/production/catalog/create');
        }

        $this->redirect('/production/catalog');
    }

    /**
     * Update existing Product Style
     */
    public function updateStyle(): void {
        $this->ensureCatalogSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid Style ID.');
            $this->redirect('/production/catalog');
        }

        $style = DB::fetch("SELECT * FROM catalogs WHERE id = ? AND source_module = 'production_catalog'" . ($cid ? " AND concern_id = ?" : ""), $cid ? [$id, $cid] : [$id]);
        if (!$style) {
            $this->flash('error', 'Style not found or access denied.');
            $this->redirect('/production/catalog');
        }

        $categoryId = (int)($_POST['category_id'] ?? 0);
        if ($categoryId <= 0) {
            $this->flash('error', 'Main Category is required.');
            $this->redirect('/production/catalog/edit?id=' . $id);
        }

        $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $subSubcategoryId = !empty($_POST['sub_sub_category_id']) ? (int)$_POST['sub_sub_category_id'] : null;

        $mainCatCode = strtoupper(trim($_POST['main_category_code'] ?? ''));
        $subCatCode = strtoupper(trim($_POST['sub_category_code'] ?? ''));
        $subSubCatCode = strtoupper(trim($_POST['sub_sub_category_code'] ?? ''));
        $wholeCode = strtoupper(trim($_POST['whole_code'] ?? ''));
        if (empty($wholeCode)) {
            $wholeCode = $mainCatCode . $subCatCode . $subSubCatCode;
        }

        $hsnCode = trim($_POST['hsn_code'] ?? '');
        $gstRateInput = trim((string)($_POST['gst_rate'] ?? ''));

        if (!preg_match('/^\d{4}(?:\d{2}){0,2}$/', $hsnCode)) {
            $this->flash('error', 'Enter a valid 4-, 6-, or 8-digit HSN code.');
            $this->redirect('/production/catalog/edit?id=' . $id);
        }
        if ($gstRateInput === '' || !is_numeric($gstRateInput) || (float)$gstRateInput < 0 || (float)$gstRateInput > 100) {
            $this->flash('error', 'Enter the verified GST rate for this HSN code.');
            $this->redirect('/production/catalog/edit?id=' . $id);
        }
        $gstRate = (float)$gstRateInput;

        $sizes = $_POST['sizes'] ?? [];
        $cleanSizes = [];
        if (is_array($sizes)) {
            foreach ($sizes as $s) {
                $val = trim($s);
                if ($val !== '' && !in_array($val, $cleanSizes)) {
                    $cleanSizes[] = $val;
                }
            }
        }

        if (empty($cleanSizes)) {
            $this->flash('error', 'Please select at least one size.');
            $this->redirect('/production/catalog/edit?id=' . $id);
        }

        $cat = DB::fetch("SELECT name FROM categories WHERE id = ?", [$categoryId]);
        $pathParts = [$cat['name'] ?? ''];
        if ($subcategoryId) {
            $sub = DB::fetch("SELECT name FROM subcategories WHERE id = ?", [$subcategoryId]);
            if ($sub) $pathParts[] = $sub['name'];
        }
        if ($subSubcategoryId) {
            $subsub = DB::fetch("SELECT name FROM sub_subcategories WHERE id = ?", [$subSubcategoryId]);
            if ($subsub) $pathParts[] = $subsub['name'];
        }
        $hierarchyPath = implode(' > ', $pathParts);
        $styleName = trim($_POST['name'] ?? '') ?: implode(' - ', $pathParts);

        DB::beginTransaction();
        try {
            DB::query(
                "UPDATE catalogs SET 
                    short_code = ?,
                    category_id = ?,
                    subcategory_id = ?,
                    sub_sub_category_id = ?,
                    main_category_code = ?,
                    sub_category_code = ?,
                    sub_sub_category_code = ?,
                    hierarchy_path = ?,
                    hsn_code = ?,
                    gst_rate = ?,
                    sizes = ?,
                    name = ?,
                    updated_at = NOW()
                 WHERE id = ?",
                [
                    $wholeCode,
                    $categoryId,
                    $subcategoryId,
                    $subSubcategoryId,
                    $mainCatCode,
                    $subCatCode,
                    $subSubCatCode,
                    $hierarchyPath,
                    $hsnCode,
                    $gstRate,
                    json_encode($cleanSizes),
                    $styleName,
                    $id
                ]
            );

            // Synchronize variants
            foreach ($cleanSizes as $size) {
                $sku = "{$style['style_code']}-{$size}";
                $varName = "{$styleName} - {$size}";
                $existVar = DB::fetch("SELECT id FROM variants WHERE concern_id = ? AND catalog_id = ? AND size = ?", [$style['concern_id'], $id, $size]);
                if (!$existVar) {
                    DB::query(
                        "INSERT INTO variants (
                            concern_id, catalog_id, product_code, name, 
                            category_id, subcategory_id, size, 
                            price, mrp, creation_date, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, 0.00, CURDATE(), 'active', NOW())",
                        [
                            $style['concern_id'],
                            $id,
                            $sku,
                            $varName,
                            $categoryId,
                            $subcategoryId ?: null,
                            $size,
                        ]
                    );
                } else {
                    DB::query("UPDATE variants SET status = 'active', name = ? WHERE id = ?", [$varName, $existVar['id']]);
                }
            }

            DB::commit();
            $this->flash('success', "Product Style '{$style['style_code']}' updated successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to update style: ' . $e->getMessage());
        }

        $this->redirect('/production/catalog');
    }

    /**
     * Delete Product Style
     */
    public function deleteStyle(): void {
        $this->ensureCatalogSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid Style ID.');
            $this->redirect('/production/catalog');
        }

        $style = DB::fetch("SELECT * FROM catalogs WHERE id = ? AND source_module = 'production_catalog'" . ($cid ? " AND concern_id = ?" : ""), $cid ? [$id, $cid] : [$id]);
        if (!$style) {
            $this->flash('error', 'Product Style not found or access denied.');
            $this->redirect('/production/catalog');
        }

        $hasStockLedger = (int)DB::fetchValue(
            "SELECT COUNT(*) FROM stock_ledger sl 
             JOIN variants v ON sl.variant_id = v.id 
             WHERE v.catalog_id = ?",
            [$id]
        );

        if ($hasStockLedger > 0) {
            DB::query("UPDATE catalogs SET status = 'inactive' WHERE id = ?", [$id]);
            DB::query("UPDATE variants SET status = 'inactive' WHERE catalog_id = ?", [$id]);
            $this->flash('warning', "Style '{$style['style_code']}' has stock transaction history and has been archived as Inactive.");
        } else {
            DB::beginTransaction();
            try {
                DB::query("DELETE FROM variants WHERE catalog_id = ?", [$id]);
                DB::query("DELETE FROM catalogs WHERE id = ?", [$id]);
                DB::commit();
                $this->flash('success', "Product Style '{$style['style_code']}' and its variants deleted successfully.");
            } catch (Exception $e) {
                DB::rollBack();
                $this->flash('error', 'Delete failed: ' . $e->getMessage());
            }
        }

        $this->redirect('/production/catalog');
    }

    // ========================================================
    // AJAX ENDPOINTS FOR DYNAMIC 3-TIER HIERARCHY & HSN
    // ========================================================

    public function getSubcategoriesAjax(): void {
        header('Content-Type: application/json');
        $this->ensureCatalogSchema();
        $cid = Auth::concernId();
        $catId = (int)($_GET['category_id'] ?? 0);

        if (!$catId) {
            echo json_encode(['success' => false, 'data' => []]);
            exit;
        }

        $sql = "SELECT id, name, code FROM subcategories WHERE category_id = ? AND status = 'active'";
        $params = [$catId];
        if ($cid) {
            $sql .= " AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)";
            $params[] = $cid;
        }
        $sql .= " ORDER BY name ASC";

        $rows = DB::fetchAll($sql, $params);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    public function getSubSubcategoriesAjax(): void {
        header('Content-Type: application/json');
        $this->ensureCatalogSchema();
        $cid = Auth::concernId();
        $subcatId = (int)($_GET['subcategory_id'] ?? 0);

        if (!$subcatId) {
            echo json_encode(['success' => false, 'data' => []]);
            exit;
        }

        $sql = "SELECT id, name, code FROM sub_subcategories WHERE subcategory_id = ? AND status = 'active'";
        $params = [$subcatId];
        if ($cid) {
            $sql .= " AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)";
            $params[] = $cid;
        }
        $sql .= " ORDER BY name ASC";

        $rows = DB::fetchAll($sql, $params);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    public function quickAddCategoryAjax(): void {
        header('Content-Type: application/json');
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $hsn  = trim($_POST['default_hsn'] ?? '');
        $gstInput = trim((string)($_POST['default_gst_rate'] ?? ''));
        $gst = $gstInput === '' ? null : (is_numeric($gstInput) ? (float)$gstInput : -1.0);

        if ($cid <= 0 || !DB::fetchValue("SELECT id FROM concerns WHERE id = ? AND status = 'active'", [$cid])) {
            echo json_encode(['success' => false, 'message' => 'Select a valid concern before creating a category.']);
            exit;
        }
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Category name is required.']);
            exit;
        }
        if (($hsn !== '' && !preg_match('/^\d{4}(?:\d{2}){0,2}$/', $hsn)) || ($gst !== null && ($gst < 0 || $gst > 100))) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid HSN code and GST rate, or leave these optional defaults blank.']);
            exit;
        }

        if (empty($code)) {
            $code = strtoupper(substr($name, 0, 2));
        }

        // Validate uniqueness (case-insensitive)
        $existing = DB::fetch(
            "SELECT id, name FROM categories 
             WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) 
             AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
            [$name, $cid]
        );
        if ($existing) {
            echo json_encode([
                'success' => false, 
                'message' => "Category '{$existing['name']}' already exists.",
                'existing_id' => $existing['id']
            ]);
            exit;
        }

        try {
            // Strict duplicate checks
            $dupCat = DB::fetch(
                "SELECT id FROM categories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND status != 'deleted'",
                [$cid, $name]
            );
            if ($dupCat) {
                echo json_encode(['success' => false, 'message' => "Duplicate Error: Category '{$name}' already exists. Duplicates are not allowed."]);
                exit;
            }
            if (!empty($code)) {
                $dupCode = DB::fetch(
                    "SELECT id FROM categories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND code = ? AND status != 'deleted'",
                    [$cid, $code]
                );
                if ($dupCode) {
                    echo json_encode(['success' => false, 'message' => "Duplicate Error: Category code '{$code}' already exists. Duplicates are not allowed."]);
                    exit;
                }
            }

            DB::query(
                "INSERT INTO categories (concern_id, name, code, default_hsn, default_gst_rate, status) VALUES (?, ?, ?, ?, ?, 'active')",
                [$cid, $name, $code, $hsn, $gst]
            );
            $id = (int)DB::lastInsertId();
            echo json_encode([
                'success' => true, 
                'id' => $id, 
                'concern_id' => $cid,
                'name' => $name, 
                'code' => $code, 
                'default_hsn' => $hsn,
                'default_gst_rate' => $gst
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function quickAddSubcategoryAjax(): void {
        header('Content-Type: application/json');
        $catId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));

        if (!$catId || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Parent Category and Subcategory name are required.']);
            exit;
        }
        $parentCategory = DB::fetch("SELECT concern_id FROM categories WHERE id = ? AND status = 'active'", [$catId]);
        $authCid = Auth::concernId();
        $parentCid = (int)($parentCategory['concern_id'] ?? 0);
        $cid = $authCid ?: (int)($_POST['concern_id'] ?? 0);
        if (!$cid && $parentCid > 1) {
            $cid = $parentCid;
        }
        if (!$parentCategory || !$cid || !DB::fetchValue("SELECT id FROM concerns WHERE id = ? AND status = 'active'", [$cid])) {
            echo json_encode(['success' => false, 'message' => 'Select a valid parent category and concern before creating a subcategory.']);
            exit;
        }
        if (($authCid && $parentCid !== $authCid && $parentCid !== 1)
            || ($parentCid > 1 && $parentCid !== $cid)) {
            echo json_encode(['success' => false, 'message' => 'The selected parent category belongs to a different concern.']);
            exit;
        }

        if (empty($code)) {
            $code = strtoupper(substr($name, 0, 2));
        }

        // Validate uniqueness under parent Category (case-insensitive)
        $existing = DB::fetch(
            "SELECT id, name FROM subcategories 
             WHERE category_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) 
             AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
            [$catId, $name, $cid]
        );
        if ($existing) {
            echo json_encode([
                'success' => false, 
                'message' => "Subcategory '{$existing['name']}' already exists under this Category.",
                'existing_id' => $existing['id']
            ]);
            exit;
        }

        try {
            // Strict duplicate check under parent category
            $dupSub = DB::fetch(
                "SELECT id FROM subcategories WHERE category_id = ? AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND status != 'deleted'",
                [$catId, $cid, $name]
            );
            if ($dupSub) {
                echo json_encode(['success' => false, 'message' => "Duplicate Error: Subcategory '{$name}' already exists under this category. Duplicates are not allowed."]);
                exit;
            }

            DB::query(
                "INSERT INTO subcategories (concern_id, category_id, name, code, status) VALUES (?, ?, ?, ?, 'active')",
                [$cid, $catId, $name, $code]
            );
            $id = (int)DB::lastInsertId();
            echo json_encode(['success' => true, 'id' => $id, 'concern_id' => $cid, 'name' => $name, 'code' => $code]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function quickAddSubSubcategoryAjax(): void {
        header('Content-Type: application/json');
        $subcatId = (int)($_POST['subcategory_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));

        if (!$subcatId || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Parent Subcategory and Sub-Subcategory name are required.']);
            exit;
        }
        $parentSubcategory = DB::fetch("SELECT concern_id FROM subcategories WHERE id = ? AND status = 'active'", [$subcatId]);
        $authCid = Auth::concernId();
        $cid = $authCid ?: (int)($_POST['concern_id'] ?? 0);
        $parentCid = (int)($parentSubcategory['concern_id'] ?? 0);
        if (!$cid && $parentCid > 1) {
            $cid = $parentCid;
        }
        if (!$parentSubcategory || !$cid || !DB::fetchValue("SELECT id FROM concerns WHERE id = ? AND status = 'active'", [$cid])) {
            echo json_encode(['success' => false, 'message' => 'Select a valid parent subcategory and concern before creating a sub-subcategory.']);
            exit;
        }
        if (($authCid && $parentCid !== $authCid && $parentCid !== 1)
            || ($parentCid > 1 && $parentCid !== $cid)) {
            echo json_encode(['success' => false, 'message' => 'The selected parent subcategory belongs to a different concern.']);
            exit;
        }

        if (empty($code)) {
            $code = strtoupper(substr($name, 0, 2));
        }

        // Validate uniqueness under parent Subcategory (case-insensitive)
        $existing = DB::fetch(
            "SELECT id, name FROM sub_subcategories 
             WHERE subcategory_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) 
             AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
            [$subcatId, $name, $cid]
        );
        if ($existing) {
            echo json_encode([
                'success' => false, 
                'message' => "Sub-Subcategory '{$existing['name']}' already exists under this Subcategory.",
                'existing_id' => $existing['id']
            ]);
            exit;
        }

        try {
            // Strict duplicate check under parent subcategory
            $dupSubSub = DB::fetch(
                "SELECT id FROM sub_subcategories WHERE subcategory_id = ? AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND status != 'deleted'",
                [$subcatId, $cid, $name]
            );
            if ($dupSubSub) {
                echo json_encode(['success' => false, 'message' => "Duplicate Error: Sub-Subcategory '{$name}' already exists under this subcategory. Duplicates are not allowed."]);
                exit;
            }

            DB::query(
                "INSERT INTO sub_subcategories (concern_id, subcategory_id, name, code, status) VALUES (?, ?, ?, ?, 'active')",
                [$cid, $subcatId, $name, $code]
            );
            $id = (int)DB::lastInsertId();
            echo json_encode(['success' => true, 'id' => $id, 'concern_id' => $cid, 'name' => $name, 'code' => $code]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function quickAddAttributeAjax(): void {
        header('Content-Type: application/json');
        $cid = Auth::concernId() ?: 1;
        $type = strtolower(trim($_POST['attribute_type'] ?? 'size'));
        $val = trim($_POST['attribute_value'] ?? '');
        $name = trim($_POST['attribute_name'] ?? ucfirst($type));
        $actionMode = trim($_POST['action_mode'] ?? '');

        if (empty($val)) {
            echo json_encode(['success' => false, 'message' => 'Attribute value cannot be empty.']);
            exit;
        }

        // Validate uniqueness under attribute type (case-insensitive)
        $existing = DB::fetch(
            "SELECT id, attribute_value FROM product_attributes 
             WHERE attribute_type = ? AND LOWER(TRIM(attribute_value)) = LOWER(TRIM(?)) 
             AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
            [$type, $val, $cid]
        );
        if ($existing) {
            if ($actionMode === 'style_builder') {
                echo json_encode([
                    'success' => true, 
                    'id' => (int)$existing['id'], 
                    'attribute_type' => $type, 
                    'attribute_value' => $existing['attribute_value'], 
                    'already_existed' => true,
                    'message' => "Attribute '{$existing['attribute_value']}' selected."
                ]);
                exit;
            }
            echo json_encode([
                'success' => false, 
                'message' => "Attribute '{$existing['attribute_value']}' already exists for '" . ucfirst($type) . "'.",
                'existing_id' => $existing['id']
            ]);
            exit;
        }

        try {
            DB::query(
                "INSERT INTO product_attributes (concern_id, attribute_type, attribute_name, attribute_value, sort_order)
                 VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_attributes pa WHERE pa.concern_id = ?))",
                [$cid, $type, $name, $val, $cid]
            );
            $id = (int)DB::lastInsertId();
            echo json_encode([
                'success' => true, 
                'id' => $id, 
                'attribute_type' => $type, 
                'attribute_value' => $val, 
                'message' => "Attribute '{$val}' added successfully."
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function deleteAttributeAjax(): void {
        header('Content-Type: application/json');
        $cid = Auth::concernId() ?: 1;
        $type = strtolower(trim($_POST['attribute_type'] ?? 'size'));
        $val = trim($_POST['attribute_value'] ?? '');

        if (empty($val)) {
            echo json_encode(['success' => false, 'message' => 'Attribute value is required.']);
            exit;
        }

        try {
            DB::query(
                "DELETE FROM product_attributes 
                 WHERE attribute_type = ? AND LOWER(TRIM(attribute_value)) = LOWER(TRIM(?)) 
                 AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
                [$type, $val, $cid]
            );
            echo json_encode(['success' => true, 'message' => "Attribute '{$val}' deleted successfully."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function deleteCategoryAjax(): void {
        header('Content-Type: application/json');
        if (!Auth::check()) {
            echo json_encode(['success' => false, 'message' => 'Please sign in to delete categories.']);
            exit;
        }

        $cid = Auth::concernId();
        $isSuperAdmin = Auth::isSuperAdmin();

        $ids = [];
        if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = array_values(array_filter(array_map('intval', $_POST['ids'])));
        } elseif (!empty($_POST['id'])) {
            $ids = [(int)$_POST['id']];
        }

        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No category selected for deletion.']);
            exit;
        }

        $deletedIds = [];
        $skipped = [];

        foreach ($ids as $id) {
            $catSql = "SELECT * FROM categories WHERE id = ?";
            $catParams = [$id];
            if ($cid && !$isSuperAdmin) {
                $catSql .= " AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)";
                $catParams[] = $cid;
            }
            $cat = DB::fetch($catSql, $catParams);
            if (!$cat) {
                continue;
            }

            $used = (int)DB::fetchValue("SELECT COUNT(*) FROM catalogs WHERE category_id = ?", [$id]);
            if ($used > 0) {
                $skipped[] = "{$cat['name']} (linked to {$used} style(s))";
                continue;
            }

            try {
                $subIds = DB::fetchAll("SELECT id FROM subcategories WHERE category_id = ?", [$id]);
                foreach ($subIds as $s) {
                    DB::query("DELETE FROM sub_subcategories WHERE subcategory_id = ?", [$s['id']]);
                }
                DB::query("DELETE FROM subcategories WHERE category_id = ?", [$id]);
                DB::query("DELETE FROM categories WHERE id = ?", [$id]);
                $deletedIds[] = $id;
            } catch (\Throwable $e) {
                $skipped[] = "{$cat['name']} (" . $e->getMessage() . ")";
            }
        }

        if (empty($deletedIds)) {
            $msg = !empty($skipped) 
                ? ("Cannot delete category: " . implode(', ', $skipped))
                : "No categories could be deleted.";
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }

        $msg = count($deletedIds) . " category(s) deleted successfully.";
        if (!empty($skipped)) {
            $msg .= " Note: " . implode(', ', $skipped) . " could not be deleted because they are used in catalogs.";
        }

        echo json_encode([
            'success' => true,
            'message' => $msg,
            'deleted_ids' => $deletedIds,
            'skipped' => $skipped
        ]);
        exit;
    }

    public function deleteSubcategoryAjax(): void {
        header('Content-Type: application/json');
        if (!Auth::check()) {
            echo json_encode(['success' => false, 'message' => 'Please sign in to delete subcategories.']);
            exit;
        }

        $cid = Auth::concernId();
        $isSuperAdmin = Auth::isSuperAdmin();

        $ids = [];
        if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = array_values(array_filter(array_map('intval', $_POST['ids'])));
        } elseif (!empty($_POST['id'])) {
            $ids = [(int)$_POST['id']];
        }

        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No subcategory selected for deletion.']);
            exit;
        }

        $deletedIds = [];
        $skipped = [];

        foreach ($ids as $id) {
            $subSql = "SELECT * FROM subcategories WHERE id = ?";
            $subParams = [$id];
            if ($cid && !$isSuperAdmin) {
                $subSql .= " AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)";
                $subParams[] = $cid;
            }
            $sub = DB::fetch($subSql, $subParams);
            if (!$sub) {
                continue;
            }

            $used = (int)DB::fetchValue("SELECT COUNT(*) FROM catalogs WHERE subcategory_id = ?", [$id]);
            if ($used > 0) {
                $skipped[] = "{$sub['name']} (linked to {$used} style(s))";
                continue;
            }

            try {
                DB::query("DELETE FROM sub_subcategories WHERE subcategory_id = ?", [$id]);
                DB::query("DELETE FROM subcategories WHERE id = ?", [$id]);
                $deletedIds[] = $id;
            } catch (\Throwable $e) {
                $skipped[] = "{$sub['name']} (" . $e->getMessage() . ")";
            }
        }

        if (empty($deletedIds)) {
            $msg = !empty($skipped) 
                ? ("Cannot delete subcategory: " . implode(', ', $skipped))
                : "No subcategories could be deleted.";
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }

        $msg = count($deletedIds) . " subcategory(s) deleted successfully.";
        if (!empty($skipped)) {
            $msg .= " Note: " . implode(', ', $skipped) . " could not be deleted because they are used in catalogs.";
        }

        echo json_encode([
            'success' => true,
            'message' => $msg,
            'deleted_ids' => $deletedIds,
            'skipped' => $skipped
        ]);
        exit;
    }

    public function deleteSubSubcategoryAjax(): void {
        header('Content-Type: application/json');
        if (!Auth::check()) {
            echo json_encode(['success' => false, 'message' => 'Please sign in to delete sub-subcategories.']);
            exit;
        }

        $cid = Auth::concernId();
        $isSuperAdmin = Auth::isSuperAdmin();

        $ids = [];
        if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = array_values(array_filter(array_map('intval', $_POST['ids'])));
        } elseif (!empty($_POST['id'])) {
            $ids = [(int)$_POST['id']];
        }

        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No sub-subcategory selected for deletion.']);
            exit;
        }

        $deletedIds = [];
        $skipped = [];

        foreach ($ids as $id) {
            $subSubSql = "SELECT * FROM sub_subcategories WHERE id = ?";
            $subSubParams = [$id];
            if ($cid && !$isSuperAdmin) {
                $subSubSql .= " AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)";
                $subSubParams[] = $cid;
            }
            $subsub = DB::fetch($subSubSql, $subSubParams);
            if (!$subsub) {
                continue;
            }

            $used = (int)DB::fetchValue("SELECT COUNT(*) FROM catalogs WHERE sub_sub_category_id = ?", [$id]);
            if ($used > 0) {
                $skipped[] = "{$subsub['name']} (linked to {$used} style(s))";
                continue;
            }

            try {
                DB::query("DELETE FROM sub_subcategories WHERE id = ?", [$id]);
                $deletedIds[] = $id;
            } catch (\Throwable $e) {
                $skipped[] = "{$subsub['name']} (" . $e->getMessage() . ")";
            }
        }

        if (empty($deletedIds)) {
            $msg = !empty($skipped) 
                ? ("Cannot delete sub-subcategory: " . implode(', ', $skipped))
                : "No sub-subcategories could be deleted.";
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }

        $msg = count($deletedIds) . " sub-subcategory(s) deleted successfully.";
        if (!empty($skipped)) {
            $msg .= " Note: " . implode(', ', $skipped) . " could not be deleted because they are used in catalogs.";
        }

        echo json_encode([
            'success' => true,
            'message' => $msg,
            'deleted_ids' => $deletedIds,
            'skipped' => $skipped
        ]);
        exit;
    }

    public function updateCategoryAjax(): void {
        header('Content-Type: application/json');
        if (!Auth::check()) {
            echo json_encode(['success' => false, 'message' => 'Please sign in to update categories.']);
            exit;
        }

        $cid = Auth::concernId() ?: 1;
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $hsn  = trim($_POST['default_hsn'] ?? '');
        $gstInput = trim((string)($_POST['default_gst_rate'] ?? ''));
        $gst = $gstInput === '' ? null : (is_numeric($gstInput) ? (float)$gstInput : -1.0);

        if (!$id || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Category ID and Category name are required.']);
            exit;
        }

        if (($hsn !== '' && !preg_match('/^\d{4}(?:\d{2}){0,2}$/', $hsn)) || ($gst !== null && ($gst < 0 || $gst > 100))) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid HSN code and GST rate, or leave these optional defaults blank.']);
            exit;
        }

        if (empty($code)) {
            $code = strtoupper(substr($name, 0, 2));
        }

        // Check if category exists
        $category = DB::fetch("SELECT * FROM categories WHERE id = ?", [$id]);
        if (!$category) {
            echo json_encode(['success' => false, 'message' => 'Category not found.']);
            exit;
        }

        // Check duplicate name excluding self
        $dupName = DB::fetch(
            "SELECT id FROM categories 
             WHERE id != ? AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) 
               AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND status != 'deleted'",
            [$id, $cid, $name]
        );
        if ($dupName) {
            echo json_encode(['success' => false, 'message' => "Duplicate Error: Category '{$name}' already exists. Duplicates are not allowed."]);
            exit;
        }

        // Check duplicate code excluding self
        if (!empty($code)) {
            $dupCode = DB::fetch(
                "SELECT id FROM categories 
                 WHERE id != ? AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) 
                   AND code = ? AND status != 'deleted'",
                [$id, $cid, $code]
            );
            if ($dupCode) {
                echo json_encode(['success' => false, 'message' => "Duplicate Error: Category code '{$code}' already exists. Duplicates are not allowed."]);
                exit;
            }
        }

        try {
            DB::query(
                "UPDATE categories SET name = ?, code = ?, default_hsn = ?, default_gst_rate = ? WHERE id = ?",
                [$name, $code, $hsn, $gst, $id]
            );

            echo json_encode([
                'success' => true,
                'id' => $id,
                'name' => $name,
                'code' => $code,
                'default_hsn' => $hsn,
                'default_gst_rate' => $gst,
                'message' => "Category '{$name}' updated successfully."
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    public function updateSubcategoryAjax(): void {
        header('Content-Type: application/json');
        if (!Auth::check()) {
            echo json_encode(['success' => false, 'message' => 'Please sign in to update subcategories.']);
            exit;
        }

        $cid = Auth::concernId() ?: 1;
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));

        if (!$id || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Subcategory ID and name are required.']);
            exit;
        }

        if (empty($code)) {
            $code = strtoupper(substr($name, 0, 2));
        }

        $sub = DB::fetch("SELECT * FROM subcategories WHERE id = ?", [$id]);
        if (!$sub) {
            echo json_encode(['success' => false, 'message' => 'Subcategory not found.']);
            exit;
        }

        $catId = (int)$sub['category_id'];

        // Check duplicate name under parent category excluding self
        $dupSub = DB::fetch(
            "SELECT id FROM subcategories 
             WHERE id != ? AND category_id = ? AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) 
               AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND status != 'deleted'",
            [$id, $catId, $cid, $name]
        );
        if ($dupSub) {
            echo json_encode(['success' => false, 'message' => "Duplicate Error: Subcategory '{$name}' already exists under this category."]);
            exit;
        }

        try {
            DB::query(
                "UPDATE subcategories SET name = ?, code = ? WHERE id = ?",
                [$name, $code, $id]
            );

            echo json_encode([
                'success' => true,
                'id' => $id,
                'category_id' => $catId,
                'name' => $name,
                'code' => $code,
                'message' => "Subcategory '{$name}' updated successfully."
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    public function updateSubSubcategoryAjax(): void {
        header('Content-Type: application/json');
        if (!Auth::check()) {
            echo json_encode(['success' => false, 'message' => 'Please sign in to update sub-subcategories.']);
            exit;
        }

        $cid = Auth::concernId() ?: 1;
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));

        if (!$id || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Sub-Subcategory ID and name are required.']);
            exit;
        }

        if (empty($code)) {
            $code = strtoupper(substr($name, 0, 2));
        }

        $subsub = DB::fetch("SELECT * FROM sub_subcategories WHERE id = ?", [$id]);
        if (!$subsub) {
            echo json_encode(['success' => false, 'message' => 'Sub-Subcategory not found.']);
            exit;
        }

        $subcatId = (int)$subsub['subcategory_id'];

        // Check duplicate name under parent subcategory excluding self
        $dupSubSub = DB::fetch(
            "SELECT id FROM sub_subcategories 
             WHERE id != ? AND subcategory_id = ? AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) 
               AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND status != 'deleted'",
            [$id, $subcatId, $cid, $name]
        );
        if ($dupSubSub) {
            echo json_encode(['success' => false, 'message' => "Duplicate Error: Sub-Subcategory '{$name}' already exists under this subcategory."]);
            exit;
        }

        try {
            DB::query(
                "UPDATE sub_subcategories SET name = ?, code = ? WHERE id = ?",
                [$name, $code, $id]
            );

            echo json_encode([
                'success' => true,
                'id' => $id,
                'subcategory_id' => $subcatId,
                'name' => $name,
                'code' => $code,
                'message' => "Sub-Subcategory '{$name}' updated successfully."
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    public function searchHsnAjax(): void {
        header('Content-Type: application/json');
        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Please sign in to search HSN codes.']);
            exit;
        }

        $allowedModules = ['catalog_program', 'catalog_mapping', 'purchase_billing', 'purchase_stocks'];
        $canSearchHsn = Auth::isSuperAdmin();
        foreach ($allowedModules as $moduleCode) {
            if ($canSearchHsn) {
                break;
            }
            if (!Auth::hasModule($moduleCode)) {
                continue;
            }
            foreach (['view', 'add', 'edit'] as $permission) {
                if (Auth::can($moduleCode, $permission)) {
                    $canSearchHsn = true;
                    break 2;
                }
            }
        }
        if (!$canSearchHsn) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to search HSN codes.']);
            exit;
        }

        $cid = Auth::concernId();
        $q = trim($_GET['q'] ?? '');
        $codeType = strtoupper(trim($_GET['type'] ?? 'HSN'));
        if (!in_array($codeType, ['HSN', 'SAC'], true)) {
            $codeType = 'HSN';
        }

        $sql = "SELECT code_type, hsn_code, description, category, gst_rate, rate_note FROM hsn_masters WHERE is_active = 1 AND code_type = ?";
        $params = [$codeType];
        if ($cid) {
            $sql .= " AND (concern_id = ? OR concern_id IS NULL)";
            $params[] = $cid;
        }
        if (!empty($q)) {
            $sql .= " AND (hsn_code LIKE ? OR description LIKE ?)";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }
        $sql .= " ORDER BY hsn_code ASC LIMIT 15";

        $rows = DB::fetchAll($sql, $params);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    public function quickAddHsnAjax(): void {
        header('Content-Type: application/json');
        $cid = Auth::concernId();
        $codeType = 'HSN';
        $hsn = trim($_POST['hsn_code'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $rateNote = trim($_POST['rate_note'] ?? '');
        $gstInput = trim((string)($_POST['gst_rate'] ?? ''));
        $gst = $gstInput === '' ? null : (is_numeric($gstInput) ? (float)$gstInput : -1.0);

        if (!preg_match('/^\d{4}(?:\d{2}){0,2}$/', $hsn) || strlen($desc) > 255 || strlen($category) > 100 || strlen($rateNote) > 500 || ($gst !== null && (!is_finite($gst) || $gst < 0 || $gst > 100))) {
            echo json_encode(['success' => false, 'message' => 'Use a valid 4-, 6-, or 8-digit HSN code and GST percentage from 0 to 100.']);
            exit;
        }

        try {
            $existing = DB::fetch(
                "SELECT id, concern_id, code_type, hsn_code, description, category, gst_rate, rate_note
                 FROM hsn_masters
                 WHERE is_active = 1 AND (concern_id = ? OR concern_id IS NULL) AND code_type = ? AND hsn_code = ?
                 ORDER BY concern_id DESC LIMIT 1",
                [$cid, $codeType, $hsn]
            );
            if ($existing && $existing['concern_id'] === null) {
                echo json_encode([
                    'success' => true,
                    'code_type' => $existing['code_type'],
                    'hsn_code' => $existing['hsn_code'],
                    'description' => $existing['description'],
                    'category' => $existing['category'],
                    'gst_rate' => $existing['gst_rate'],
                    'rate_note' => $existing['rate_note'],
                ]);
                exit;
            } elseif ($existing) {
                DB::query(
                    "UPDATE hsn_masters SET description = ?, category = ?, gst_rate = ?, rate_note = ?, is_active = 1 WHERE id = ?",
                    [$desc, $category, $gst, $rateNote, $existing['id']]
                );
            } else {
                DB::query(
                    "INSERT INTO hsn_masters (concern_id, code_type, hsn_code, description, category, gst_rate, rate_note) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$cid, $codeType, $hsn, $desc, $category, $gst, $rateNote]
                );
            }
            echo json_encode(['success' => true, 'code_type' => $codeType, 'hsn_code' => $hsn, 'description' => $desc, 'category' => $category, 'gst_rate' => $gst, 'rate_note' => $rateNote]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function styleDetailsAjax(): void {
        header('Content-Type: application/json');
        $cid = Auth::concernId();
        $id = (int)($_GET['id'] ?? 0);

        $style = DB::fetch("SELECT * FROM catalogs WHERE id = ? AND source_module = 'production_catalog'" . ($cid ? " AND concern_id = ?" : ""), $cid ? [$id, $cid] : [$id]);
        if (!$style) {
            echo json_encode(['success' => false, 'message' => 'Style not found.']);
            exit;
        }

        $variants = DB::fetchAll("SELECT * FROM variants WHERE catalog_id = ?" . ($cid ? " AND concern_id = ?" : ""), $cid ? [$id, $cid] : [$id]);

        echo json_encode([
            'success'  => true,
            'style'    => $style,
            'variants' => $variants,
        ]);
        exit;
    }

    // ========================================================
    // 2. PRODUCTION PROGRAMS (MULTI-ROLL SCHEDULING - IMAGE 2)
    // ========================================================

    /**
     * Programs list view (tab)
     */
    public function programsList(): void {
        $this->ensureCatalogSchema();
        $cid = Auth::concernId();
        $progSql = "SELECT pp.*, c.name AS catalog_name, v.name AS target_variant_name, v.product_code,
                           con.name AS concern_name, con.code AS concern_code,
                           (SELECT COUNT(*) FROM program_rolls pr WHERE pr.program_id = pp.id) AS rolls_count,
                           (SELECT GROUP_CONCAT(DISTINCT COALESCE(rc.name, pc.name) SEPARATOR ', ')
                            FROM program_rolls pr
                            LEFT JOIN catalogs rc ON pr.catalog_id = rc.id AND rc.source_module = 'production_catalog'
                                AND (rc.concern_id = pr.concern_id OR rc.concern_id = 1 OR rc.concern_id IS NULL)
                            LEFT JOIN catalogs pc ON pc.id = pp.catalog_id AND pc.source_module = 'production_catalog'
                                AND (pc.concern_id = pp.concern_id OR pc.concern_id = 1 OR pc.concern_id IS NULL)
                            WHERE pr.program_id = pp.id) AS roll_catalog_names
                    FROM production_programs pp
                    LEFT JOIN catalogs c ON pp.catalog_id = c.id AND c.source_module = 'production_catalog'
                        AND (c.concern_id = pp.concern_id OR c.concern_id = 1 OR c.concern_id IS NULL)
                    LEFT JOIN variants v ON pp.target_variant_id = v.id
                    LEFT JOIN concerns con ON pp.concern_id = con.id
                    WHERE (pp.catalog_id IS NULL OR c.id IS NOT NULL)";
        $progParams = [];
        if ($cid) {
            $progSql .= " AND pp.concern_id = ?";
            $progParams[] = $cid;
        }
        $progSql .= " ORDER BY pp.id DESC";
        $programs = DB::fetchAll($progSql, $progParams);

        $programIds = array_column($programs, 'id');
        $programRollsMap = [];
        if (!empty($programIds)) {
            $inClause = implode(',', array_map('intval', $programIds));
            $allRolls = DB::fetchAll("
                SELECT pr.*, 
                       pbi.item_code AS original_piece_tag, pbi.item_name AS roll_material, pbi.color AS roll_color, pbi.unit AS roll_unit, pbi.qty AS original_roll_qty,
                       cat.name AS category_name, sub.name AS subcategory_name, subsub.name AS subsub_name,
                       COALESCE(c.name, CONCAT_WS(' > ', cat.name, sub.name, subsub.name)) AS catalog_style_name,
                       c.style_code AS catalog_style_code
                FROM program_rolls pr
                LEFT JOIN purchase_bill_items pbi ON pr.purchase_bill_item_id = pbi.id
                LEFT JOIN categories cat ON pr.category_id = cat.id
                LEFT JOIN subcategories sub ON pr.subcategory_id = sub.id
                LEFT JOIN sub_subcategories subsub ON pr.sub_sub_category_id = subsub.id
                LEFT JOIN catalogs c ON pr.catalog_id = c.id AND c.source_module = 'production_catalog'
                    AND (c.concern_id = pr.concern_id OR c.concern_id = 1 OR c.concern_id IS NULL)
                WHERE pr.program_id IN ($inClause)
                ORDER BY pr.id ASC
            ");
            foreach ($allRolls as $r) {
                $programRollsMap[(int)$r['program_id']][] = $r;
            }
        }
        foreach ($programs as &$p) {
            $p['rolls'] = $programRollsMap[(int)$p['id']] ?? [];
        }
        unset($p);

        if ($cid) {
            $catalogs = DB::fetchAll("SELECT * FROM catalogs WHERE concern_id = ? AND source_module = 'production_catalog'", [$cid]);
            $variants = DB::fetchAll("SELECT id, product_code, name FROM variants WHERE concern_id = ? AND status = 'active'", [$cid]);
        } else {
            $catalogs = DB::fetchAll("SELECT * FROM catalogs WHERE source_module = 'production_catalog'");
            $variants = DB::fetchAll("SELECT id, product_code, name FROM variants WHERE status = 'active'");
        }

        $this->render('concern/catalog_program', [
            'pageTitle'     => 'Production Programs',
            'currentModule' => 'catalog_program',
            'styles'        => [],
            'programs'      => $programs,
            'categories'    => [],
            'subcategories' => [],
            'variants'      => $variants,
            'catalogs'      => $catalogs,
            'selectedCid'   => $cid,
            'activeTab'     => 'programs',
        ]);
    }

    /**
     * Legacy single-variant program store (backward compatibility)
     */
    public function storeProgram(): void {
        ModuleGuard::enforce('catalog_program', 'add');
        $this->ensureCatalogSchema();
        $this->validateCsrf();
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);

        if (!$cid) {
            $this->flash('error', 'Please choose a concern.');
            $this->redirect('/production/catalog');
        }

        $targetVarId = (int)($_POST['target_variant_id'] ?? 0);
        $plannedQty  = (float)($_POST['planned_qty'] ?? 0);
        $startDate   = $_POST['start_date'] ?: date('Y-m-d');
        $targetDate  = $_POST['target_date'] ?: date('Y-m-d', strtotime('+14 days'));
        $catalogId   = !empty($_POST['catalog_id']) ? (int)$_POST['catalog_id'] : null;

        if (!$targetVarId || $plannedQty <= 0) {
            $this->flash('error', 'Target Variant and Planned Quantity are required.');
            $this->redirect('/production/catalog');
        }

        DB::beginTransaction();
        try {
            $progNo = 'PRG-' . date('Ymd') . '-' . rand(100, 999);

            DB::query(
                "INSERT INTO production_programs (concern_id, program_no, catalog_id, target_variant_id, planned_qty, start_date, target_date, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'in_progress', NOW())",
                [$cid, $progNo, $catalogId, $targetVarId, $plannedQty, $startDate, $targetDate]
            );

            DB::commit();
            $this->flash('success', "Production Program '{$progNo}' scheduled for {$plannedQty} units.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to schedule program: ' . $e->getMessage());
        }

        $this->redirect('/production/catalog?tab=programs');
    }

    /**
     * Create Production Program Form matching Image 2 (Excluding Section 4)
     */
    public function createProgram(): void {
        ModuleGuard::enforce('catalog_program', 'add');
        $this->ensureCatalogSchema();
        $cid = Auth::concernId();

        \App\Services\PurchaseDamageService::initTable();
        \App\Services\PurchaseDamageService::backfillRollDamageItemIds($cid);

        // Auto-generate program code: # PRG-YYYYMMDD-XXXX
        $datePrefix = date('Ymd');
        $progCount = (int)DB::fetchValue("SELECT COUNT(*) FROM production_programs" . ($cid ? " WHERE concern_id = ?" : ""), $cid ? [$cid] : []) + 1;
        $suggestedCode = sprintf("PRG-%s-%04d", $datePrefix, $progCount);

        // Planner Name
        $plannerName = Auth::user()['name'] ?? 'System Administrator';

        // Load Available Fabric Rolls from purchase_bill_items
        $rollsSql = "SELECT pbi.id, pbi.concern_id, pbi.item_code, pbi.item_name, pbi.pattern, pbi.color, pbi.unit,
                            pbi.specification, pbi.net_weight, pbi.gross_weight, pbi.unit_price, pbi.qty AS total_qty,
                            pbi.catalog_id, pb.bill_no, pb.bill_date, s.name AS supplier_name, s.company_name AS supplier_company,
                            COALESCE((
                                SELECT SUM(pr.allocated_qty) 
                                FROM program_rolls pr 
                                JOIN production_programs pp ON pr.program_id = pp.id 
                                WHERE pr.purchase_bill_item_id = pbi.id 
                                  AND pp.status != 'cancelled'
                            ), 0.00) AS allocated_qty,
                            COALESCE((
                                SELECT SUM(pdq.qty)
                                FROM purchase_damage_quarantine pdq
                                WHERE pdq.item_type = 'rolls'
                                  AND (
                                      pdq.purchase_bill_item_id = pbi.id
                                      OR (
                                          (pdq.purchase_bill_item_id IS NULL OR pdq.purchase_bill_item_id = 0)
                                          AND pdq.concern_id = pbi.concern_id
                                          AND (
                                              (pdq.tag_code IS NOT NULL AND pdq.tag_code != '' AND pdq.tag_code COLLATE utf8mb4_unicode_ci = pbi.item_code COLLATE utf8mb4_unicode_ci)
                                              OR (
                                                  pdq.roll_source_color IS NOT NULL 
                                                  AND UPPER(TRIM(pdq.roll_source_color)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(pbi.color)) COLLATE utf8mb4_unicode_ci
                                                  AND (
                                                      (pdq.roll_source_design_name IS NOT NULL AND UPPER(TRIM(pdq.roll_source_design_name)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(COALESCE(NULLIF(pbi.pattern, ''), pbi.item_name))) COLLATE utf8mb4_unicode_ci)
                                                      OR (pdq.item_name IS NOT NULL AND UPPER(TRIM(pdq.item_name)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(pbi.item_name)) COLLATE utf8mb4_unicode_ci)
                                                  )
                                              )
                                          )
                                      )
                                  )
                            ), 0.00) AS damaged_qty
                     FROM purchase_bill_items pbi
                     JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                     LEFT JOIN suppliers s ON pb.supplier_id = s.id
                     WHERE pbi.section_type = 'rolls'
                       AND (pbi.inward_status = 'completed' OR (pbi.inward_status IS NULL AND pb.inward_status = 'completed'))" . ($cid ? " AND pbi.concern_id = ?" : "") . "
                     ORDER BY pbi.id DESC";
        $rawRolls = DB::fetchAll($rollsSql, $cid ? [$cid] : []);

        $availableRolls = [];
        foreach ($rawRolls as $r) {
            $total = (float)$r['total_qty'];
            $allocated = (float)$r['allocated_qty'];
            $damaged = (float)$r['damaged_qty'];
            $remaining = max(0.00, $total - $allocated - $damaged);
            if ($remaining <= 0.0001) {
                continue;
            }
            $r['remaining_qty'] = $remaining;
            $unit = $r['unit'] ?: 'kg';
            $r['unit_price'] = (float)($r['unit_price'] ?? 0.00);
            $origWeight = (float)($r['net_weight'] ?: ($r['gross_weight'] ?: 0.00));
            $r['weight'] = ($total > 0 && $origWeight > 0) ? round(($remaining / $total) * $origWeight, 2) : ($unit === 'kg' ? $remaining : 0.00);
            $r['supplier_display'] = $r['supplier_name'] ?: ($r['supplier_company'] ?: 'Direct');
            $r['out_of_label'] = sprintf("%.2f %s out of %.2f %s", $remaining, $unit, $total, $unit);
            $r['tag_code'] = QrService::generateTagCode($r['item_name'], $r['color'], $r['pattern'] ?: ($r['specification'] ?? ''), (string)$r['id']);

            if (!empty($r['catalog_id'])) {
                $cat = DB::fetch(
                    "SELECT id, name, style_code, hierarchy_path, category_id, subcategory_id, sub_sub_category_id 
                     FROM catalogs WHERE id = ?",
                    [$r['catalog_id']]
                );
                if ($cat) {
                    $r['catalog_style_code'] = $cat['style_code'];
                    $r['catalog_name'] = $cat['name'];
                    $r['hierarchy_path'] = $cat['hierarchy_path'];
                    $r['category_id'] = $cat['category_id'];
                    $r['subcategory_id'] = $cat['subcategory_id'];
                    $r['sub_sub_category_id'] = $cat['sub_sub_category_id'];
                }
            }
            $availableRolls[] = $r;
        }

        // Calculate accumulated stock per fabric specification (pattern/design + color)
        $accumulatedBySpec = [];
        foreach ($availableRolls as $r) {
            $specKey = strtoupper(trim(($r['pattern'] ?: $r['item_name']) . '|' . ($r['color'] ?: '')));
            if (!isset($accumulatedBySpec[$specKey])) {
                $accumulatedBySpec[$specKey] = [
                    'design_name' => strtoupper($r['pattern'] ?: $r['item_name']),
                    'color'       => strtoupper($r['color'] ?: 'STANDARD'),
                    'total_stock' => 0.00,
                    'unit'        => $r['unit'] ?: 'kg',
                    'unit_price'  => (float)($r['unit_price'] ?? 0),
                    'bill_no'     => $r['bill_no'],
                    'supplier'    => $r['supplier_display'],
                ];
            }
            $accumulatedBySpec[$specKey]['total_stock'] += (float)$r['remaining_qty'];
        }

        foreach ($availableRolls as &$r) {
            $specKey = strtoupper(trim(($r['pattern'] ?: $r['item_name']) . '|' . ($r['color'] ?: '')));
            $r['design_accumulated_stock'] = $accumulatedBySpec[$specKey]['total_stock'] ?? $r['remaining_qty'];
            $r['design_name'] = $accumulatedBySpec[$specKey]['design_name'] ?? $r['item_name'];
        }
        unset($r);

        // Load Categories for 3-tier hierarchy
        if ($cid) {
            $categories = DB::fetchAll("SELECT * FROM categories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND status = 'active' ORDER BY name ASC", [$cid]);
            $catalogs = DB::fetchAll("SELECT id, style_code, short_code, name, category_id, subcategory_id, sub_sub_category_id FROM catalogs WHERE source_module = 'production_catalog' AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND status != 'inactive' ORDER BY id DESC", [$cid]);
        } else {
            $categories = DB::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC");
            $catalogs = DB::fetchAll("SELECT id, style_code, short_code, name, category_id, subcategory_id, sub_sub_category_id FROM catalogs WHERE source_module = 'production_catalog' AND status != 'inactive' ORDER BY id DESC");
        }

        $this->render('concern/program_create', [
            'pageTitle'      => 'Create Production Program',
            'currentModule'  => 'catalog_program',
            'suggestedCode'  => $suggestedCode,
            'plannerName'    => $plannerName,
            'availableRolls' => $availableRolls,
            'categories'     => $categories,
            'catalogs'       => $catalogs,
            'selectedCid'    => $cid,
        ]);
    }

    /**
     * AJAX endpoint returning available rolls with calculated "out of qty"
     */
    public function apiAvailableRolls(): void {
        header('Content-Type: application/json');
        $this->ensureCatalogSchema();
        $cid = Auth::concernId();

        $rollsSql = "SELECT pbi.id, pbi.concern_id, pbi.item_code, pbi.item_name, pbi.pattern, pbi.color, pbi.unit,
                            pbi.specification, pbi.net_weight, pbi.gross_weight, pbi.unit_price, pbi.qty AS total_qty,
                            pbi.catalog_id, pb.bill_no, pb.bill_date, s.name AS supplier_name, s.company_name AS supplier_company,
                            COALESCE((
                                SELECT SUM(pr.allocated_qty) 
                                FROM program_rolls pr 
                                JOIN production_programs pp ON pr.program_id = pp.id 
                                WHERE pr.purchase_bill_item_id = pbi.id 
                                  AND pp.status != 'cancelled'
                            ), 0.00) AS allocated_qty,
                            COALESCE((
                                SELECT SUM(pdq.qty)
                                FROM purchase_damage_quarantine pdq
                                WHERE pdq.item_type = 'rolls'
                                  AND (
                                      pdq.purchase_bill_item_id = pbi.id
                                      OR (
                                          (pdq.purchase_bill_item_id IS NULL OR pdq.purchase_bill_item_id = 0)
                                          AND pdq.concern_id = pbi.concern_id
                                          AND (
                                              (pdq.tag_code IS NOT NULL AND pdq.tag_code != '' AND pdq.tag_code COLLATE utf8mb4_unicode_ci = pbi.item_code COLLATE utf8mb4_unicode_ci)
                                              OR (
                                                  pdq.roll_source_color IS NOT NULL 
                                                  AND UPPER(TRIM(pdq.roll_source_color)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(pbi.color)) COLLATE utf8mb4_unicode_ci
                                                  AND (
                                                      (pdq.roll_source_design_name IS NOT NULL AND UPPER(TRIM(pdq.roll_source_design_name)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(COALESCE(NULLIF(pbi.pattern, ''), pbi.item_name))) COLLATE utf8mb4_unicode_ci)
                                                      OR (pdq.item_name IS NOT NULL AND UPPER(TRIM(pdq.item_name)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(pbi.item_name)) COLLATE utf8mb4_unicode_ci)
                                                  )
                                              )
                                          )
                                      )
                                  )
                            ), 0.00) AS damaged_qty
                     FROM purchase_bill_items pbi
                     JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                     LEFT JOIN suppliers s ON pb.supplier_id = s.id
                     WHERE pbi.section_type = 'rolls'
                       AND (pbi.inward_status = 'completed' OR (pbi.inward_status IS NULL AND pb.inward_status = 'completed'))" . ($cid ? " AND pbi.concern_id = ?" : "") . "
                     ORDER BY pbi.id DESC";
        $rawRolls = DB::fetchAll($rollsSql, $cid ? [$cid] : []);

        $data = [];
        foreach ($rawRolls as $r) {
            $total = (float)$r['total_qty'];
            $allocated = (float)$r['allocated_qty'];
            $damaged = (float)$r['damaged_qty'];
            $remaining = max(0.00, $total - $allocated - $damaged);
            if ($remaining <= 0.0001) {
                continue;
            }
            $r['remaining_qty'] = $remaining;
            $unit = $r['unit'] ?: 'kg';
            $r['unit_price'] = (float)($r['unit_price'] ?? 0.00);
            $origWeight = (float)($r['net_weight'] ?: ($r['gross_weight'] ?: 0.00));
            $r['weight'] = ($total > 0 && $origWeight > 0) ? round(($remaining / $total) * $origWeight, 2) : ($unit === 'kg' ? $remaining : 0.00);
            $r['supplier_display'] = $r['supplier_name'] ?: ($r['supplier_company'] ?: 'Direct Purchase');
            $r['out_of_label'] = sprintf("%.2f %s out of %.2f %s", $remaining, $unit, $total, $unit);
            $r['tag_code'] = QrService::generateTagCode($r['item_name'], $r['color'], $r['pattern'] ?: ($r['specification'] ?? ''), (string)$r['id']);
            $r['tag_code'] = QrService::generateTagCode($r['item_name'], $r['color'], $r['pattern'] ?: ($r['specification'] ?? ''), (string)$r['id']);

            if (!empty($r['catalog_id'])) {
                $cat = DB::fetch(
                    "SELECT id, name, style_code, hierarchy_path, category_id, subcategory_id, sub_sub_category_id 
                     FROM catalogs WHERE id = ?",
                    [$r['catalog_id']]
                );
                if ($cat) {
                    $r['catalog_style_code'] = $cat['style_code'];
                    $r['catalog_name'] = $cat['name'];
                    $r['hierarchy_path'] = $cat['hierarchy_path'];
                    $r['category_id'] = $cat['category_id'];
                    $r['subcategory_id'] = $cat['subcategory_id'];
                    $r['sub_sub_category_id'] = $cat['sub_sub_category_id'];
                }
            }
            $data[] = $r;
        }

        // Calculate accumulated stock
        $accumBySpec = [];
        foreach ($data as $d) {
            $k = strtoupper(trim(($d['pattern'] ?: $d['item_name']) . '|' . ($d['color'] ?: '')));
            if (!isset($accumBySpec[$k])) $accumBySpec[$k] = 0.00;
            $accumBySpec[$k] += (float)$d['remaining_qty'];
        }
        foreach ($data as &$d) {
            $k = strtoupper(trim(($d['pattern'] ?: $d['item_name']) . '|' . ($d['color'] ?: '')));
            $d['design_accumulated_stock'] = $accumBySpec[$k] ?? $d['remaining_qty'];
        }
        unset($d);

        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    /**
     * AJAX endpoint for roll barcode / QR tag scan lookup
     */
    public function apiRollLookup(): void {
        header('Content-Type: application/json');
        $this->ensureCatalogSchema();
        $cid = Auth::concernId();
        $tag = trim($_GET['tag'] ?? '');

        if (empty($tag)) {
            echo json_encode(['success' => false, 'message' => 'Piece tag or barcode required.']);
            exit;
        }

        $sql = "SELECT pbi.id, pbi.concern_id, pbi.item_code, pbi.item_name, pbi.pattern, pbi.color, pbi.unit,
                       pbi.specification, pbi.net_weight, pbi.gross_weight, pbi.unit_price, pbi.qty AS total_qty,
                       pbi.catalog_id, pb.bill_no, pb.bill_date, s.name AS supplier_name, s.company_name AS supplier_company,
                       COALESCE((
                           SELECT SUM(pr.allocated_qty) 
                           FROM program_rolls pr 
                           JOIN production_programs pp ON pr.program_id = pp.id 
                           WHERE pr.purchase_bill_item_id = pbi.id 
                             AND pp.status != 'cancelled'
                       ), 0.00) AS allocated_qty,
                       COALESCE((
                           SELECT SUM(pdq.qty)
                           FROM purchase_damage_quarantine pdq
                           WHERE pdq.item_type = 'rolls'
                             AND (
                                 pdq.purchase_bill_item_id = pbi.id
                                 OR (
                                     (pdq.purchase_bill_item_id IS NULL OR pdq.purchase_bill_item_id = 0)
                                     AND pdq.concern_id = pbi.concern_id
                                     AND (
                                         (pdq.tag_code IS NOT NULL AND pdq.tag_code != '' AND pdq.tag_code COLLATE utf8mb4_unicode_ci = pbi.item_code COLLATE utf8mb4_unicode_ci)
                                         OR (
                                             pdq.roll_source_color IS NOT NULL 
                                             AND UPPER(TRIM(pdq.roll_source_color)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(pbi.color)) COLLATE utf8mb4_unicode_ci
                                             AND (
                                                 (pdq.roll_source_design_name IS NOT NULL AND UPPER(TRIM(pdq.roll_source_design_name)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(COALESCE(NULLIF(pbi.pattern, ''), pbi.item_name))) COLLATE utf8mb4_unicode_ci)
                                                 OR (pdq.item_name IS NOT NULL AND UPPER(TRIM(pdq.item_name)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(pbi.item_name)) COLLATE utf8mb4_unicode_ci)
                                             )
                                         )
                                     )
                                 )
                             )
                       ), 0.00) AS damaged_qty
                FROM purchase_bill_items pbi
                JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                LEFT JOIN suppliers s ON pb.supplier_id = s.id
                WHERE pbi.section_type = 'rolls'
                  AND (pbi.inward_status = 'completed' OR (pbi.inward_status IS NULL AND pb.inward_status = 'completed'))" . ($cid ? " AND pbi.concern_id = ?" : "") . "
                  AND (pbi.item_code = ? OR pbi.item_name LIKE ? OR pbi.pattern LIKE ? OR pb.bill_no = ?)
                LIMIT 1";
        $params = $cid ? [$cid, $tag, "%{$tag}%", "%{$tag}%", $tag] : [$tag, "%{$tag}%", "%{$tag}%", $tag];
        $row = DB::fetch($sql, $params);

        // 2. If not found by direct match, match against generated QR / barcode tag codes (e.g. FLO-B-FL)
        if (!$row) {
            $allRollsSql = "SELECT pbi.id, pbi.concern_id, pbi.item_code, pbi.item_name, pbi.pattern, pbi.color, pbi.unit,
                                   pbi.specification, pbi.net_weight, pbi.gross_weight, pbi.unit_price, pbi.qty AS total_qty,
                                   pbi.catalog_id, pb.bill_no, pb.bill_date, s.name AS supplier_name, s.company_name AS supplier_company,
                                   COALESCE((
                                       SELECT SUM(pr.allocated_qty) 
                                       FROM program_rolls pr 
                                       JOIN production_programs pp ON pr.program_id = pp.id 
                                       WHERE pr.purchase_bill_item_id = pbi.id 
                                         AND pp.status != 'cancelled'
                                   ), 0.00) AS allocated_qty,
                                   COALESCE((
                                       SELECT SUM(pdq.qty)
                                       FROM purchase_damage_quarantine pdq
                                       WHERE pdq.item_type = 'rolls'
                                         AND (
                                             pdq.purchase_bill_item_id = pbi.id
                                             OR (
                                                 (pdq.purchase_bill_item_id IS NULL OR pdq.purchase_bill_item_id = 0)
                                                 AND pdq.concern_id = pbi.concern_id
                                                 AND (
                                                     (pdq.tag_code IS NOT NULL AND pdq.tag_code != '' AND pdq.tag_code COLLATE utf8mb4_unicode_ci = pbi.item_code COLLATE utf8mb4_unicode_ci)
                                                     OR (
                                                         pdq.roll_source_color IS NOT NULL 
                                                         AND UPPER(TRIM(pdq.roll_source_color)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(pbi.color)) COLLATE utf8mb4_unicode_ci
                                                         AND (
                                                             (pdq.roll_source_design_name IS NOT NULL AND UPPER(TRIM(pdq.roll_source_design_name)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(COALESCE(NULLIF(pbi.pattern, ''), pbi.item_name))) COLLATE utf8mb4_unicode_ci)
                                                             OR (pdq.item_name IS NOT NULL AND UPPER(TRIM(pdq.item_name)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(pbi.item_name)) COLLATE utf8mb4_unicode_ci)
                                                         )
                                                     )
                                                 )
                                             )
                                         )
                                   ), 0.00) AS damaged_qty
                            FROM purchase_bill_items pbi
                            JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                            LEFT JOIN suppliers s ON pb.supplier_id = s.id
                            WHERE pbi.section_type = 'rolls'
                              AND (pbi.inward_status = 'completed' OR (pbi.inward_status IS NULL AND pb.inward_status = 'completed'))" . ($cid ? " AND pbi.concern_id = ?" : "");
            $candidates = DB::fetchAll($allRollsSql, $cid ? [$cid] : []);
            foreach ($candidates as $cand) {
                $candTag = QrService::generateTagCode($cand['item_name'], $cand['color'], $cand['pattern'] ?: ($cand['specification'] ?? ''), (string)$cand['id']);
                if (strcasecmp($candTag, $tag) === 0 || strcasecmp($cand['item_code'] ?? '', $tag) === 0) {
                    $row = $cand;
                    break;
                }
            }
        }

        if (!$row) {
            echo json_encode(['success' => false, 'message' => "No purchased fabric roll found matching '{$tag}'."]);
            exit;
        }

        $total = (float)$row['total_qty'];
        $allocated = (float)$row['allocated_qty'];
        $damaged = (float)$row['damaged_qty'];
        $remaining = max(0.00, $total - $allocated - $damaged);
        if ($remaining <= 0.0001) {
            echo json_encode(['success' => false, 'message' => 'This roll is already programmed and has 0 remaining stock.']);
            exit;
        }
        $row['remaining_qty'] = $remaining;
        $unit = $row['unit'] ?: 'kg';
        $row['unit_price'] = (float)($row['unit_price'] ?? 0.00);
        $origWeight = (float)($row['net_weight'] ?: ($row['gross_weight'] ?: 0.00));
        $row['weight'] = ($total > 0 && $origWeight > 0) ? round(($remaining / $total) * $origWeight, 2) : ($unit === 'kg' ? $remaining : 0.00);
        $row['supplier_display'] = $row['supplier_name'] ?: ($row['supplier_company'] ?: 'Direct Purchase');
        $row['out_of_label'] = sprintf("%.2f %s out of %.2f %s", $remaining, $unit, $total, $unit);
        $row['tag_code'] = QrService::generateTagCode($row['item_name'], $row['color'], $row['pattern'] ?: ($row['specification'] ?? ''), (string)$row['id']);

        if (!empty($row['catalog_id'])) {
            $cat = DB::fetch(
                "SELECT id, name, style_code, hierarchy_path, category_id, subcategory_id, sub_sub_category_id 
                 FROM catalogs WHERE id = ?",
                [$row['catalog_id']]
            );
            if ($cat) {
                $row['catalog_style_code'] = $cat['style_code'];
                $row['catalog_name'] = $cat['name'];
                $row['hierarchy_path'] = $cat['hierarchy_path'];
                $row['category_id'] = $cat['category_id'];
                $row['subcategory_id'] = $cat['subcategory_id'];
                $row['sub_sub_category_id'] = $cat['sub_sub_category_id'];
            }
        }

        echo json_encode(['success' => true, 'roll' => $row]);
        exit;
    }

    /**
     * Store Production Program with Multi-Roll Allocation & "Out of Qty" Validations (Image 2)
     */
    public function storeProgramV2(): void {
        ModuleGuard::enforce('catalog_program', 'add');
        $this->ensureCatalogSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();

        // 1. Program Overview Validations
        $programName = trim($_POST['program_name'] ?? '');
        $programCode = trim($_POST['program_no'] ?? '');
        $plannerName = trim($_POST['planner_name'] ?? (Auth::user()['name'] ?? 'System Administrator'));

        if (empty($programName) || strlen($programName) < 3) {
            $this->flash('error', 'Program Name is required (minimum 3 characters).');
            $this->redirect('/production/programs/create');
        }

        if (empty($programCode)) {
            $progCount = (int)DB::fetchValue("SELECT COUNT(*) FROM production_programs" . ($cid ? " WHERE concern_id = ?" : ""), $cid ? [$cid] : []) + 1;
            $programCode = sprintf("PRG-%s-%04d", date('Ymd'), $progCount);
        }

        // 2. Timeline Validations
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
        $targetDate = !empty($_POST['target_date']) ? $_POST['target_date'] : date('Y-m-d', strtotime('+14 days'));

        if (strtotime($targetDate) < strtotime($startDate)) {
            $this->flash('error', 'Target Completion Date cannot precede Programming Start Date.');
            $this->redirect('/production/programs/create');
        }

        // 3. Rolls Allocation Validations
        $rolls = $_POST['rolls'] ?? [];
        if (!is_array($rolls) || empty($rolls)) {
            $this->flash('error', 'Please assign at least one raw material fabric roll.');
            $this->redirect('/production/programs/create');
        }

        // Group and validate total requested allocated quantities per roll item across all cards
        $rollAllocationsByItem = [];
        foreach ($rolls as $idx => $r) {
            $rollItemId = (int)($r['purchase_bill_item_id'] ?? 0);
            $allocatedQty = (float)floor((float)($r['allocated_qty'] ?? 0));
            if ($rollItemId > 0) {
                if (!isset($rollAllocationsByItem[$rollItemId])) {
                    $rollAllocationsByItem[$rollItemId] = [
                        'total_requested' => 0.0,
                        'card_numbers'    => [],
                    ];
                }
                $rollAllocationsByItem[$rollItemId]['total_requested'] += $allocatedQty;
                $rollAllocationsByItem[$rollItemId]['card_numbers'][] = ($idx + 1);
            }
        }

        foreach ($rollAllocationsByItem as $rollItemId => $allocData) {
            $rollSql = "SELECT pbi.*, 
                               COALESCE((
                                   SELECT SUM(pr.allocated_qty) 
                                   FROM program_rolls pr 
                                   JOIN production_programs pp ON pr.program_id = pp.id 
                                   WHERE pr.purchase_bill_item_id = pbi.id 
                                     AND pp.status != 'cancelled'
                               ), 0.00) AS used_qty,
                               COALESCE((
                                   SELECT SUM(pdq.qty)
                                   FROM purchase_damage_quarantine pdq
                                   WHERE pdq.item_type = 'rolls'
                                     AND (
                                         pdq.purchase_bill_item_id = pbi.id
                                         OR (
                                             (pdq.purchase_bill_item_id IS NULL OR pdq.purchase_bill_item_id = 0)
                                             AND pdq.concern_id = pbi.concern_id
                                             AND (
                                                 (pdq.tag_code IS NOT NULL AND pdq.tag_code != '' AND pdq.tag_code COLLATE utf8mb4_unicode_ci = pbi.item_code COLLATE utf8mb4_unicode_ci)
                                                 OR (
                                                     pdq.roll_source_color IS NOT NULL 
                                                     AND UPPER(TRIM(pdq.roll_source_color)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(pbi.color)) COLLATE utf8mb4_unicode_ci
                                                     AND (
                                                         (pdq.roll_source_design_name IS NOT NULL AND UPPER(TRIM(pdq.roll_source_design_name)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(COALESCE(NULLIF(pbi.pattern, ''), pbi.item_name))) COLLATE utf8mb4_unicode_ci)
                                                         OR (pdq.item_name IS NOT NULL AND UPPER(TRIM(pdq.item_name)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(pbi.item_name)) COLLATE utf8mb4_unicode_ci)
                                                     )
                                                 )
                                             )
                                         )
                                     )
                               ), 0.00) AS damaged_qty
                        FROM purchase_bill_items pbi 
                        JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                        WHERE pbi.id = ?
                          AND (pbi.inward_status = 'completed' OR (pbi.inward_status IS NULL AND pb.inward_status = 'completed'))" . ($cid ? " AND pbi.concern_id = ?" : "");
            $rollItem = DB::fetch($rollSql, $cid ? [$rollItemId, $cid] : [$rollItemId]);

            if (!$rollItem) {
                $this->flash('error', "Invalid purchased roll (Item #{$rollItemId}) or roll has not been added to stock yet.");
                $this->redirect('/production/programs/create');
            }

            $totalRollQty = (float)$rollItem['qty'];
            $usedQty = (float)$rollItem['used_qty'];
            $damagedQty = (float)$rollItem['damaged_qty'];
            $availableQty = max(0.00, $totalRollQty - $usedQty - $damagedQty);

            if ($allocData['total_requested'] > ($availableQty + 0.001)) {
                $tag = $rollItem['item_code'] ?: ('Item #' . $rollItem['id']);
                $cardsStr = implode(', ', array_map(fn($num) => "Roll #{$num}", $allocData['card_numbers']));
                $unit = $rollItem['unit'] ?: 'kg';
                $this->flash('error', "Total allocated quantity ({$allocData['total_requested']} {$unit}) across {$cardsStr} for [{$tag}] exceeds available stock balance ({$availableQty} {$unit} out of {$totalRollQty} {$unit}).");
                $this->redirect('/production/programs/create');
            }
        }

        $validatedRolls = [];
        $totalPlannedQty = 0.00;

        foreach ($rolls as $index => $r) {
            $rollItemId = (int)($r['purchase_bill_item_id'] ?? 0);
            $allocatedQty = (float)floor((float)($r['allocated_qty'] ?? 0));
            $targetPieces = (int)($r['target_pieces'] ?? 0);
            $catId = !empty($r['category_id']) ? (int)$r['category_id'] : null;
            $subcatId = !empty($r['subcategory_id']) ? (int)$r['subcategory_id'] : null;
            $subSubcatId = !empty($r['sub_sub_category_id']) ? (int)$r['sub_sub_category_id'] : null;
            $catalogId = !empty($r['catalog_id']) ? (int)$r['catalog_id'] : null;
            $rollConcernId = $cid ?: (int)DB::fetchValue(
                "SELECT concern_id FROM purchase_bill_items WHERE id = ?",
                [$rollItemId]
            );
            $catalogScopeSql = " AND source_module = 'production_catalog' AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)";
            $catalogScopeParams = [$rollConcernId];

            // Auto-resolve catalog from 3-tier hierarchy if not explicitly chosen
            if (!$catalogId && $subSubcatId) {
                $matchedCat = DB::fetch(
                    "SELECT id FROM catalogs WHERE sub_sub_category_id = ? {$catalogScopeSql} LIMIT 1",
                    array_merge([$subSubcatId], $catalogScopeParams)
                );
                if ($matchedCat) {
                    $catalogId = (int)$matchedCat['id'];
                }
            }
            if (!$catalogId && $subcatId && $catId) {
                $matchedCat = DB::fetch(
                    "SELECT id FROM catalogs WHERE subcategory_id = ? AND category_id = ? {$catalogScopeSql} LIMIT 1",
                    array_merge([$subcatId, $catId], $catalogScopeParams)
                );
                if ($matchedCat) {
                    $catalogId = (int)$matchedCat['id'];
                }
            }
            if (!$catalogId && $catId) {
                $matchedCat = DB::fetch(
                    "SELECT id FROM catalogs WHERE category_id = ? AND (subcategory_id IS NULL OR subcategory_id = 0) {$catalogScopeSql} LIMIT 1",
                    array_merge([$catId], $catalogScopeParams)
                );
                if ($matchedCat) {
                    $catalogId = (int)$matchedCat['id'];
                }
            }
            if ($catalogId) {
                $selectedCatalog = DB::fetch(
                    "SELECT id FROM catalogs WHERE id = ? AND source_module = 'production_catalog' AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
                    [$catalogId, $rollConcernId]
                );
                if (!$selectedCatalog) {
                    $this->flash('error', "Roll #" . ($index + 1) . ": Select a product style belonging to the roll's concern.");
                    $this->redirect('/production/programs/create');
                }
            }

            if ($allocatedQty <= 0) {
                $this->flash('error', "Roll #" . ($index + 1) . ": Allocated quantity must be greater than zero.");
                $this->redirect('/production/programs/create');
            }

            // Fetch roll and verify balance (respecting concern if selected, or discovering from roll)
            $rollSql = "SELECT pbi.*, 
                               COALESCE((
                                   SELECT SUM(pr.allocated_qty) 
                                   FROM program_rolls pr 
                                   JOIN production_programs pp ON pr.program_id = pp.id 
                                   WHERE pr.purchase_bill_item_id = pbi.id 
                                     AND pp.status != 'cancelled'
                               ), 0.00) AS used_qty,
                               COALESCE((
                                   SELECT SUM(qty)
                                   FROM purchase_damage_quarantine
                                   WHERE item_type = 'rolls' AND purchase_bill_item_id = pbi.id
                               ), 0.00) AS damaged_qty
                        FROM purchase_bill_items pbi 
                        JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                        WHERE pbi.id = ?
                          AND (pbi.inward_status = 'completed' OR (pbi.inward_status IS NULL AND pb.inward_status = 'completed'))" . ($cid ? " AND pbi.concern_id = ?" : "");
            $rollItem = DB::fetch($rollSql, $cid ? [$rollItemId, $cid] : [$rollItemId]);

            if (!$rollItem) {
                $this->flash('error', "Roll #" . ($index + 1) . ": Invalid purchased roll or roll has not been added to stock yet.");
                $this->redirect('/production/programs/create');
            }

            // If concern was not set (Super Admin with All Concerns), inherit from roll
            if (!$cid && !empty($rollItem['concern_id'])) {
                $cid = (int)$rollItem['concern_id'];
            }

            $totalRollQty = (float)$rollItem['qty'];
            $usedQty = (float)$rollItem['used_qty'];
            $damagedQty = (float)$rollItem['damaged_qty'];
            $availableQty = max(0.00, $totalRollQty - $usedQty - $damagedQty);

            // Strict Out-of-Qty validation!
            if ($allocatedQty > ($availableQty + 0.001)) {
                $tag = $rollItem['item_code'] ?: ('Item #' . $rollItem['id']);
                $this->flash('error', "Roll #" . ($index + 1) . " ({$tag}): Allocated quantity ({$allocatedQty} {$rollItem['unit']}) exceeds available balance ({$availableQty} {$rollItem['unit']} out of {$totalRollQty} {$rollItem['unit']}).");
                $this->redirect('/production/programs/create');
            }

            $totalPlannedQty += $allocatedQty;

            $rollWeight = isset($r['roll_weight']) ? (float)$r['roll_weight'] : 0.00;
            if ($rollWeight <= 0) {
                if ($rollItem['unit'] === 'kg') {
                    $rollWeight = $allocatedQty;
                } elseif ($totalRollQty > 0 && (float)($rollItem['net_weight'] ?? 0) > 0) {
                    $rollWeight = round(($allocatedQty / $totalRollQty) * (float)$rollItem['net_weight'], 2);
                }
            }

            $rollPrice = isset($r['roll_price']) ? (float)$r['roll_price'] : 0.00;
            if ($rollPrice <= 0) {
                $unitRate = (float)($rollItem['unit_price'] ?? 0.00);
                $rollPrice = round($allocatedQty * $unitRate, 2);
            }

            $tagCode = QrService::generateTagCode($rollItem['item_name'], $rollItem['color'], $rollItem['pattern'] ?: ($rollItem['specification'] ?? ''), (string)$rollItem['id']);

            $validatedRolls[] = [
                'purchase_bill_item_id' => $rollItemId,
                'roll_piece_tag'        => $tagCode ?: ($rollItem['item_code'] ?? ''),
                'fabric_name'           => $rollItem['item_name'] ?? '',
                'color'                 => $rollItem['color'] ?? '',
                'unit'                  => $rollItem['unit'] ?? 'kg',
                'total_roll_qty'        => $totalRollQty,
                'allocated_qty'         => $allocatedQty,
                'roll_weight'           => $rollWeight,
                'roll_price'            => $rollPrice,
                'target_pieces'         => $targetPieces,
                'category_id'           => $catId,
                'subcategory_id'        => $subcatId,
                'sub_sub_category_id'   => $subSubcatId,
                'catalog_id'            => $catalogId,
            ];
        }

        DB::beginTransaction();
        try {
            // Insert production_program
            DB::query(
                "INSERT INTO production_programs (
                    concern_id, program_no, program_name, planner_name,
                    catalog_id, target_variant_id, planned_qty,
                    start_date, target_date, status, created_at
                ) VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, 'planned', NOW())",
                [
                    $cid,
                    $programCode,
                    $programName,
                    $plannerName,
                    $validatedRolls[0]['catalog_id'] ?? null,
                    $totalPlannedQty,
                    $startDate,
                    $targetDate
                ]
            );
            $programId = (int)DB::lastInsertId();

            // Insert program_rolls mappings
            foreach ($validatedRolls as $vr) {
                DB::query(
                    "INSERT INTO program_rolls (
                        concern_id, program_id, purchase_bill_item_id,
                        roll_piece_tag, fabric_name, color, unit,
                        total_roll_qty, allocated_qty, roll_weight, roll_price, target_pieces,
                        category_id, subcategory_id, sub_sub_category_id, catalog_id,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $cid,
                        $programId,
                        $vr['purchase_bill_item_id'],
                        $vr['roll_piece_tag'],
                        $vr['fabric_name'],
                        $vr['color'],
                        $vr['unit'],
                        $vr['total_roll_qty'],
                        $vr['allocated_qty'],
                        $vr['roll_weight'],
                        $vr['roll_price'],
                        $vr['target_pieces'],
                        $vr['category_id'],
                        $vr['subcategory_id'],
                        $vr['sub_sub_category_id'],
                        $vr['catalog_id'],
                    ]
                );
            }

            // Audit Log
            AuditLog::log(
                'CREATE',
                'production_programs',
                $programId,
                null,
                [
                    'program_no'   => $programCode,
                    'program_name' => $programName,
                    'planner'      => $plannerName,
                    'total_qty'    => $totalPlannedQty,
                    'rolls_count'  => count($validatedRolls),
                ]
            );

            DB::commit();
            $this->flash('success', "Production Program '{$programCode}' ({$programName}) scheduled successfully with " . count($validatedRolls) . " fabric roll allocations.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to create program: ' . $e->getMessage());
            $this->redirect('/production/programs/create');
        }

        $this->redirect('/production/catalog?tab=programs');
    }

    /**
     * View Production Program details (Full-Page View)
     */
    public function viewProgram(): void {
        $this->ensureCatalogSchema();
        $cid = Auth::concernId();
        $id = (int)($_GET['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid program ID.');
            $this->redirect('/production/catalog?tab=programs');
        }

        $sql = "SELECT pp.*, c.name AS catalog_name, c.style_code AS catalog_code,
                       v.name AS target_variant_name, v.product_code,
                       con.name AS concern_name, con.code AS concern_code
                FROM production_programs pp
                LEFT JOIN catalogs c ON pp.catalog_id = c.id AND c.source_module = 'production_catalog'
                    AND (c.concern_id = pp.concern_id OR c.concern_id = 1 OR c.concern_id IS NULL)
                LEFT JOIN variants v ON pp.target_variant_id = v.id
                LEFT JOIN concerns con ON pp.concern_id = con.id
                WHERE pp.id = ?";
        $params = [$id];
        if ($cid) {
            $sql .= " AND pp.concern_id = ?";
            $params[] = $cid;
        }

        $program = DB::fetch($sql, $params);
        if (!$program) {
            $this->flash('error', 'Program not found or access denied.');
            $this->redirect('/production/catalog?tab=programs');
        }

        $rolls = DB::fetchAll(
            "SELECT pr.*, 
                    pbi.item_code AS original_piece_tag, pbi.item_name AS roll_material, pbi.color AS roll_color, pbi.unit AS roll_unit, pbi.qty AS original_roll_qty,
                    cat.name AS category_name, sub.name AS subcategory_name, subsub.name AS subsub_name,
                    COALESCE(c.name, CONCAT_WS(' > ', cat.name, sub.name, subsub.name)) AS catalog_style_name,
                    c.style_code AS catalog_style_code
             FROM program_rolls pr
             LEFT JOIN purchase_bill_items pbi ON pr.purchase_bill_item_id = pbi.id
             LEFT JOIN categories cat ON pr.category_id = cat.id
             LEFT JOIN subcategories sub ON pr.subcategory_id = sub.id
             LEFT JOIN sub_subcategories subsub ON pr.sub_sub_category_id = subsub.id
             LEFT JOIN catalogs c ON pr.catalog_id = c.id AND c.source_module = 'production_catalog'
                 AND (c.concern_id = pr.concern_id OR c.concern_id = 1 OR c.concern_id IS NULL)
             WHERE pr.program_id = ?
             ORDER BY pr.id ASC",
            [$id]
        );

        $cuttingStats = DB::fetch(
            "SELECT COUNT(*) AS total_lots, COALESCE(SUM(consumed_qty), 0) AS total_consumed, COALESCE(SUM(cut_pieces), 0) AS total_cut_pieces 
             FROM cutting_logs WHERE program_id = ?",
            [$id]
        );

        $this->render('concern/program_view', [
            'pageTitle'     => 'Production Program: ' . $program['program_no'],
            'currentModule' => 'catalog_program',
            'program'       => $program,
            'rolls'         => $rolls,
            'cutting'       => $cuttingStats,
            'selectedCid'   => $cid,
        ]);
    }

    /**
     * AJAX endpoint for Production Program details (Used by Quick View Modal)
     */
    public function apiProgramDetails(): void {
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        $this->ensureCatalogSchema();
        $cid = Auth::concernId();
        $id = (int)($_GET['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid program ID.']);
            exit;
        }

        $sql = "SELECT pp.*, c.name AS catalog_name, c.style_code AS catalog_code,
                       v.name AS target_variant_name, v.product_code,
                       con.name AS concern_name, con.code AS concern_code
                FROM production_programs pp
                LEFT JOIN catalogs c ON pp.catalog_id = c.id AND c.source_module = 'production_catalog'
                    AND (c.concern_id = pp.concern_id OR c.concern_id = 1 OR c.concern_id IS NULL)
                LEFT JOIN variants v ON pp.target_variant_id = v.id
                LEFT JOIN concerns con ON pp.concern_id = con.id
                WHERE pp.id = ?";
        $params = [$id];
        if ($cid) {
            $sql .= " AND pp.concern_id = ?";
            $params[] = $cid;
        }

        $program = DB::fetch($sql, $params);
        if (!$program) {
            echo json_encode(['success' => false, 'message' => 'Program not found or access denied.']);
            exit;
        }

        $rolls = DB::fetchAll(
            "SELECT pr.*, 
                    pbi.item_code AS original_piece_tag, pbi.item_name AS roll_material, pbi.color AS roll_color, pbi.unit AS roll_unit, pbi.qty AS original_roll_qty,
                    cat.name AS category_name, sub.name AS subcategory_name, subsub.name AS subsub_name,
                    COALESCE(c.name, CONCAT_WS(' > ', cat.name, sub.name, subsub.name)) AS catalog_style_name,
                    c.style_code AS catalog_style_code
             FROM program_rolls pr
             LEFT JOIN purchase_bill_items pbi ON pr.purchase_bill_item_id = pbi.id
             LEFT JOIN categories cat ON pr.category_id = cat.id
             LEFT JOIN subcategories sub ON pr.subcategory_id = sub.id
             LEFT JOIN sub_subcategories subsub ON pr.sub_sub_category_id = subsub.id
             LEFT JOIN catalogs c ON pr.catalog_id = c.id AND c.source_module = 'production_catalog'
                 AND (c.concern_id = pr.concern_id OR c.concern_id = 1 OR c.concern_id IS NULL)
             WHERE pr.program_id = ?
             ORDER BY pr.id ASC",
            [$id]
        );

        $cuttingStats = DB::fetch(
            "SELECT COUNT(*) AS total_lots, COALESCE(SUM(consumed_qty), 0) AS total_consumed, COALESCE(SUM(cut_pieces), 0) AS total_cut_pieces 
             FROM cutting_logs WHERE program_id = ?",
            [$id]
        );

        echo json_encode([
            'success' => true,
            'program' => $program,
            'rolls'   => $rolls,
            'cutting' => $cuttingStats,
        ]);
        exit;
    }

    /**
     * Edit Production Program Form
     */
    public function editProgram(): void {
        $this->ensureCatalogSchema();
        $cid = Auth::concernId();
        $id = (int)($_GET['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid program ID.');
            $this->redirect('/production/catalog?tab=programs');
        }

        $program = DB::fetch(
            "SELECT * FROM production_programs WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""),
            $cid ? [$id, $cid] : [$id]
        );
        if (!$program) {
            $this->flash('error', 'Program not found or access denied.');
            $this->redirect('/production/catalog?tab=programs');
        }
        if (!$cid && !empty($program['concern_id'])) {
            $cid = (int)$program['concern_id'];
        }

        $existingRolls = DB::fetchAll(
            "SELECT pr.*, 
                    pbi.item_code AS current_roll_code, pbi.item_name AS current_roll_name, pbi.color AS current_roll_color, pbi.unit AS current_unit, pbi.qty AS original_total_qty,
                    cat.name AS category_name, sub.name AS subcategory_name, subsub.name AS subsub_name,
                    c.id AS resolved_catalog_id,
                    c.name AS catalog_name,
                    c.style_code AS catalog_style_code
             FROM program_rolls pr
             LEFT JOIN purchase_bill_items pbi ON pr.purchase_bill_item_id = pbi.id
             LEFT JOIN categories cat ON pr.category_id = cat.id
             LEFT JOIN subcategories sub ON pr.subcategory_id = sub.id
             LEFT JOIN sub_subcategories subsub ON pr.sub_sub_category_id = subsub.id
             LEFT JOIN catalogs c ON c.id = COALESCE(
                 (SELECT c0.id FROM catalogs c0 WHERE c0.id = pr.catalog_id AND c0.source_module = 'production_catalog' AND (c0.concern_id = pr.concern_id OR c0.concern_id = 1 OR c0.concern_id IS NULL)),
                 (SELECT c_m.id FROM catalogs c_m WHERE c_m.source_module = 'production_catalog' AND (c_m.concern_id = pr.concern_id OR c_m.concern_id = 1 OR c_m.concern_id IS NULL) AND pr.sub_sub_category_id IS NOT NULL AND c_m.sub_sub_category_id = pr.sub_sub_category_id ORDER BY c_m.id ASC LIMIT 1),
                 (SELECT c_s.id FROM catalogs c_s WHERE c_s.source_module = 'production_catalog' AND (c_s.concern_id = pr.concern_id OR c_s.concern_id = 1 OR c_s.concern_id IS NULL) AND pr.subcategory_id IS NOT NULL AND c_s.subcategory_id = pr.subcategory_id AND (pr.category_id IS NULL OR c_s.category_id = pr.category_id) ORDER BY c_s.id ASC LIMIT 1),
                 (SELECT c_c.id FROM catalogs c_c WHERE c_c.source_module = 'production_catalog' AND (c_c.concern_id = pr.concern_id OR c_c.concern_id = 1 OR c_c.concern_id IS NULL) AND pr.category_id IS NOT NULL AND c_c.category_id = pr.category_id ORDER BY c_c.id ASC LIMIT 1)
             )
             WHERE pr.program_id = ?
             ORDER BY pr.id ASC",
            [$id]
        );

        foreach ($existingRolls as &$er) {
            if (empty($er['catalog_id']) && !empty($er['resolved_catalog_id'])) {
                $er['catalog_id'] = $er['resolved_catalog_id'];
            } elseif (empty($er['resolved_catalog_id'])) {
                $er['catalog_id'] = null;
            }
        }
        unset($er);

        // Load Available Fabric Rolls for this concern, discounting allocations in OTHER programs and damaged rolls
        $rollsSql = "SELECT pbi.id, pbi.concern_id, pbi.item_code, pbi.item_name, pbi.pattern, pbi.color, pbi.unit,
                            pbi.net_weight, pbi.gross_weight, pbi.unit_price, pbi.qty AS total_qty,
                            pbi.catalog_id, pb.bill_no, pb.bill_date, s.name AS supplier_name, s.company_name AS supplier_company,
                            COALESCE((
                                SELECT SUM(pr.allocated_qty) 
                                FROM program_rolls pr 
                                JOIN production_programs pp ON pr.program_id = pp.id 
                                WHERE pr.purchase_bill_item_id = pbi.id 
                                  AND pp.status != 'cancelled'
                                  AND pp.id != ?
                            ), 0.00) AS allocated_in_other_programs,
                            COALESCE((
                                SELECT SUM(pdq.qty)
                                FROM purchase_damage_quarantine pdq
                                WHERE pdq.item_type = 'rolls'
                                  AND (
                                      pdq.purchase_bill_item_id = pbi.id
                                      OR (
                                          (pdq.purchase_bill_item_id IS NULL OR pdq.purchase_bill_item_id = 0)
                                          AND pdq.concern_id = pbi.concern_id
                                          AND (
                                              (pdq.tag_code IS NOT NULL AND pdq.tag_code != '' AND pdq.tag_code COLLATE utf8mb4_unicode_ci = pbi.item_code COLLATE utf8mb4_unicode_ci)
                                              OR (
                                                  pdq.roll_source_color IS NOT NULL 
                                                  AND UPPER(TRIM(pdq.roll_source_color)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(pbi.color)) COLLATE utf8mb4_unicode_ci
                                                  AND (
                                                      (pdq.roll_source_design_name IS NOT NULL AND UPPER(TRIM(pdq.roll_source_design_name)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(COALESCE(NULLIF(pbi.pattern, ''), pbi.item_name))) COLLATE utf8mb4_unicode_ci)
                                                      OR (pdq.item_name IS NOT NULL AND UPPER(TRIM(pdq.item_name)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(pbi.item_name)) COLLATE utf8mb4_unicode_ci)
                                                  )
                                              )
                                          )
                                      )
                                  )
                            ), 0.00) AS damaged_qty
                     FROM purchase_bill_items pbi
                     JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                     LEFT JOIN suppliers s ON pb.supplier_id = s.id
                     WHERE pbi.section_type = 'rolls'
                       AND (pbi.inward_status = 'completed' OR (pbi.inward_status IS NULL AND pb.inward_status = 'completed'))" . ($cid ? " AND pbi.concern_id = ?" : "") . "
                     ORDER BY pbi.id DESC";
        $rawRolls = DB::fetchAll($rollsSql, $cid ? [$id, $cid] : [$id]);

        $availableRolls = [];
        foreach ($rawRolls as $r) {
            $total = (float)$r['total_qty'];
            $allocatedOther = (float)$r['allocated_in_other_programs'];
            $damaged = (float)$r['damaged_qty'];
            $remaining = max(0.00, $total - $allocatedOther - $damaged);
            $r['remaining_qty'] = $remaining;
            $unit = $r['unit'] ?: 'kg';
            $r['unit_price'] = (float)($r['unit_price'] ?? 0.00);
            $origWeight = (float)($r['net_weight'] ?: ($r['gross_weight'] ?: 0.00));
            $r['weight'] = ($total > 0 && $origWeight > 0) ? round(($remaining / $total) * $origWeight, 2) : ($unit === 'kg' ? $remaining : 0.00);
            $r['supplier_display'] = $r['supplier_name'] ?: ($r['supplier_company'] ?: 'Direct Purchase');
            $r['out_of_label'] = sprintf("%.2f %s out of %.2f %s", $remaining, $unit, $total, $unit);
            $r['tag_code'] = QrService::generateTagCode($r['item_name'], $r['color'], $r['pattern'] ?: ($r['specification'] ?? ''), (string)$r['id']);

            if (!empty($r['catalog_id'])) {
                $cat = DB::fetch(
                    "SELECT id, name, style_code, hierarchy_path, category_id, subcategory_id, sub_sub_category_id 
                     FROM catalogs WHERE id = ?",
                    [$r['catalog_id']]
                );
                if ($cat) {
                    $r['catalog_style_code'] = $cat['style_code'];
                    $r['catalog_name'] = $cat['name'];
                    $r['hierarchy_path'] = $cat['hierarchy_path'];
                    $r['category_id'] = $cat['category_id'];
                    $r['subcategory_id'] = $cat['subcategory_id'];
                    $r['sub_sub_category_id'] = $cat['sub_sub_category_id'];
                }
            }
            $availableRolls[] = $r;
        }

        // Calculate accumulated stock per fabric specification (pattern/design + color)
        $accumulatedBySpec = [];
        foreach ($availableRolls as $r) {
            $specKey = strtoupper(trim(($r['pattern'] ?: $r['item_name']) . '|' . ($r['color'] ?: '')));
            if (!isset($accumulatedBySpec[$specKey])) {
                $accumulatedBySpec[$specKey] = [
                    'design_name' => strtoupper($r['pattern'] ?: $r['item_name']),
                    'color'       => strtoupper($r['color'] ?: 'STANDARD'),
                    'total_stock' => 0.00,
                    'unit'        => $r['unit'] ?: 'kg',
                    'unit_price'  => (float)($r['unit_price'] ?? 0),
                    'bill_no'     => $r['bill_no'],
                    'supplier'    => $r['supplier_display'],
                ];
            }
            $accumulatedBySpec[$specKey]['total_stock'] += (float)$r['remaining_qty'];
        }

        foreach ($availableRolls as &$r) {
            $specKey = strtoupper(trim(($r['pattern'] ?: $r['item_name']) . '|' . ($r['color'] ?: '')));
            $r['design_accumulated_stock'] = $accumulatedBySpec[$specKey]['total_stock'] ?? $r['remaining_qty'];
            $r['design_name'] = $accumulatedBySpec[$specKey]['design_name'] ?? $r['item_name'];
        }
        unset($r);

        $catWhere = $cid ? "(concern_id = ? OR concern_id = 1 OR concern_id IS NULL)" : "1=1";
        $catParams = $cid ? [$cid] : [];

        $categories = DB::fetchAll("SELECT * FROM categories WHERE {$catWhere} AND status = 'active' ORDER BY name ASC", $catParams);
        $subcategories = DB::fetchAll("SELECT * FROM subcategories WHERE {$catWhere} AND status = 'active' ORDER BY name ASC", $catParams);
        $subSubcategories = DB::fetchAll("SELECT * FROM sub_subcategories WHERE {$catWhere} AND status = 'active' ORDER BY name ASC", $catParams);
        $catalogs = DB::fetchAll("SELECT id, style_code, short_code, name, category_id, subcategory_id, sub_sub_category_id FROM catalogs WHERE source_module = 'production_catalog' AND {$catWhere} AND status != 'inactive' ORDER BY id DESC", $catParams);

        $this->render('concern/program_edit', [
            'pageTitle'        => 'Edit Production Program: ' . $program['program_no'],
            'currentModule'    => 'catalog_program',
            'program'          => $program,
            'existingRolls'    => $existingRolls,
            'availableRolls'   => $availableRolls,
            'categories'       => $categories,
            'subcategories'    => $subcategories,
            'subSubcategories' => $subSubcategories,
            'catalogs'         => $catalogs,
            'selectedCid'      => $cid,
        ]);
    }

    /**
     * Update Production Program
     */
    public function updateProgram(): void {
        $this->ensureCatalogSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid program ID.');
            $this->redirect('/production/catalog?tab=programs');
        }

        $program = DB::fetch(
            "SELECT * FROM production_programs WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""),
            $cid ? [$id, $cid] : [$id]
        );
        if (!$program) {
            $this->flash('error', 'Program not found or access denied.');
            $this->redirect('/production/catalog?tab=programs');
        }

        $programConcernId = !empty($program['concern_id']) ? (int)$program['concern_id'] : ($cid ?: 1);

        $programName = trim($_POST['program_name'] ?? '');
        $status = $_POST['status'] ?? $program['status'];
        if (!in_array($status, ['planned', 'in_progress', 'completed', 'cancelled'])) {
            $status = 'planned';
        }

        if (empty($programName) || strlen($programName) < 3) {
            $this->flash('error', 'Program Name is required (minimum 3 characters).');
            $this->redirect('/production/programs/edit?id=' . $id);
        }

        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : $program['start_date'];
        $targetDate = !empty($_POST['target_date']) ? $_POST['target_date'] : $program['target_date'];

        if (strtotime($targetDate) < strtotime($startDate)) {
            $this->flash('error', 'Target Completion Date cannot precede Programming Start Date.');
            $this->redirect('/production/programs/edit?id=' . $id);
        }

        $rolls = $_POST['rolls'] ?? [];
        if (!is_array($rolls) || empty($rolls)) {
            $this->flash('error', 'Please assign at least one raw material fabric roll.');
            $this->redirect('/production/programs/edit?id=' . $id);
        }

        // Group and validate total requested allocated quantities per roll item across all cards
        $rollAllocationsByItem = [];
        foreach ($rolls as $idx => $r) {
            $rollItemId = (int)($r['purchase_bill_item_id'] ?? 0);
            $allocatedQty = (float)floor((float)($r['allocated_qty'] ?? 0));
            if ($rollItemId > 0) {
                if (!isset($rollAllocationsByItem[$rollItemId])) {
                    $rollAllocationsByItem[$rollItemId] = [
                        'total_requested' => 0.0,
                        'card_numbers'    => [],
                    ];
                }
                $rollAllocationsByItem[$rollItemId]['total_requested'] += $allocatedQty;
                $rollAllocationsByItem[$rollItemId]['card_numbers'][] = ($idx + 1);
            }
        }

        foreach ($rollAllocationsByItem as $rollItemId => $allocData) {
            $rollItem = DB::fetch(
                "SELECT pbi.*, 
                        COALESCE((
                            SELECT SUM(pr.allocated_qty) 
                            FROM program_rolls pr 
                            JOIN production_programs pp ON pr.program_id = pp.id 
                            WHERE pr.purchase_bill_item_id = pbi.id 
                              AND pp.status != 'cancelled'
                              AND pp.id != ?
                        ), 0.00) AS used_qty_other
                 FROM purchase_bill_items pbi 
                 JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                 WHERE pbi.id = ?
                   AND (pbi.inward_status = 'completed' OR (pbi.inward_status IS NULL AND pb.inward_status = 'completed'))" . ($cid ? " AND pbi.concern_id = ?" : ""),
                $cid ? [$id, $rollItemId, $cid] : [$id, $rollItemId]
            );

            if (!$rollItem) {
                $this->flash('error', "Invalid purchased roll (Item #{$rollItemId}) or roll has not been added to stock yet.");
                $this->redirect('/production/programs/edit?id=' . $id);
            }

            $totalRollQty = (float)$rollItem['qty'];
            $usedQtyOther = (float)$rollItem['used_qty_other'];
            $availableQty = max(0.00, $totalRollQty - $usedQtyOther);

            if ($allocData['total_requested'] > ($availableQty + 0.001)) {
                $tag = $rollItem['item_code'] ?: ('Item #' . $rollItem['id']);
                $cardsStr = implode(', ', array_map(fn($num) => "Roll #{$num}", $allocData['card_numbers']));
                $unit = $rollItem['unit'] ?: 'kg';
                $this->flash('error', "Total allocated quantity ({$allocData['total_requested']} {$unit}) across {$cardsStr} for [{$tag}] exceeds available stock balance ({$availableQty} {$unit} out of {$totalRollQty} {$unit}).");
                $this->redirect('/production/programs/edit?id=' . $id);
            }
        }

        // Keep existing allocation IDs stable so cutting/stitching links and
        // saved roll categories survive an edit. New rolls are inserted, removed
        // rolls are deleted after the rest are reconciled.
        $existingProgramRolls = DB::fetchAll(
            "SELECT id, purchase_bill_item_id, catalog_id FROM program_rolls WHERE program_id = ?",
            [$id]
        );
        $existingRollMap = [];
        foreach ($existingProgramRolls as $existingRoll) {
            $existingRollMap[(int)$existingRoll['id']] = $existingRoll;
        }

        $validatedRolls = [];
        $totalPlannedQty = 0.00;
        $firstCatalogId = null;
        $newRollCatalogId = null;
        $changedCatalogId = null;

        foreach ($rolls as $index => $r) {
            $programRollId = !empty($r['program_roll_id']) ? (int)$r['program_roll_id'] : 0;
            $rollItemId = (int)($r['purchase_bill_item_id'] ?? 0);
            $allocatedQty = (float)floor((float)($r['allocated_qty'] ?? 0));
            $targetPieces = (int)($r['target_pieces'] ?? 0);
            $catId = !empty($r['category_id']) ? (int)$r['category_id'] : null;
            $subcatId = !empty($r['subcategory_id']) ? (int)$r['subcategory_id'] : null;
            $subSubcatId = !empty($r['sub_sub_category_id']) ? (int)$r['sub_sub_category_id'] : null;
            $catalogId = !empty($r['catalog_id']) ? (int)$r['catalog_id'] : null;

            // Auto-resolve catalog from 3-tier hierarchy if not explicitly chosen
            if (!$catalogId && $subSubcatId) {
                $matchedCat = DB::fetch("SELECT id FROM catalogs WHERE sub_sub_category_id = ? AND source_module = 'production_catalog' AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) LIMIT 1", [$subSubcatId, $programConcernId]);
                if ($matchedCat) {
                    $catalogId = (int)$matchedCat['id'];
                }
            }
            if (!$catalogId && $subcatId && $catId) {
                $matchedCat = DB::fetch("SELECT id FROM catalogs WHERE subcategory_id = ? AND category_id = ? AND source_module = 'production_catalog' AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) LIMIT 1", [$subcatId, $catId, $programConcernId]);
                if ($matchedCat) {
                    $catalogId = (int)$matchedCat['id'];
                }
            }

            if ($catalogId) {
                $selectedCatalog = DB::fetch(
                    "SELECT id FROM catalogs WHERE id = ? AND source_module = 'production_catalog' AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
                    [$catalogId, $programConcernId]
                );
                if (!$selectedCatalog) {
                    $this->flash('error', "Roll #" . ($index + 1) . ": Select a product style belonging to this program's concern.");
                    $this->redirect('/production/programs/edit?id=' . $id);
                }
                if ($firstCatalogId === null) {
                    $firstCatalogId = $catalogId;
                }
                if ($programRollId && isset($existingRollMap[$programRollId])) {
                    $previousCatalogId = !empty($existingRollMap[$programRollId]['catalog_id'])
                        ? (int)$existingRollMap[$programRollId]['catalog_id']
                        : null;
                    if ($previousCatalogId !== $catalogId) {
                        $changedCatalogId = $catalogId;
                    }
                } else {
                    $newRollCatalogId = $catalogId;
                }
            }

            if ($allocatedQty <= 0) {
                $this->flash('error', "Roll #" . ($index + 1) . ": Allocated quantity must be greater than zero.");
                $this->redirect('/production/programs/edit?id=' . $id);
            }

            // Fetch roll and verify balance (excluding this program's current allocation and damaged rolls)
            $rollItem = DB::fetch(
                "SELECT pbi.*, 
                        COALESCE((
                            SELECT SUM(pr.allocated_qty) 
                            FROM program_rolls pr 
                            JOIN production_programs pp ON pr.program_id = pp.id 
                            WHERE pr.purchase_bill_item_id = pbi.id 
                              AND pp.status != 'cancelled'
                              AND pp.id != ?
                        ), 0.00) AS used_qty_other,
                        COALESCE((
                            SELECT SUM(pdq.qty)
                            FROM purchase_damage_quarantine pdq
                            WHERE pdq.item_type = 'rolls'
                              AND (
                                  pdq.purchase_bill_item_id = pbi.id
                                  OR (
                                      (pdq.purchase_bill_item_id IS NULL OR pdq.purchase_bill_item_id = 0)
                                      AND pdq.concern_id = pbi.concern_id
                                      AND (
                                          (pdq.tag_code IS NOT NULL AND pdq.tag_code != '' AND pdq.tag_code COLLATE utf8mb4_unicode_ci = pbi.item_code COLLATE utf8mb4_unicode_ci)
                                          OR (
                                              pdq.roll_source_color IS NOT NULL 
                                              AND UPPER(TRIM(pdq.roll_source_color)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(pbi.color)) COLLATE utf8mb4_unicode_ci
                                              AND (
                                                  (pdq.roll_source_design_name IS NOT NULL AND UPPER(TRIM(pdq.roll_source_design_name)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(COALESCE(NULLIF(pbi.pattern, ''), pbi.item_name))) COLLATE utf8mb4_unicode_ci)
                                                  OR (pdq.item_name IS NOT NULL AND UPPER(TRIM(pdq.item_name)) COLLATE utf8mb4_unicode_ci = UPPER(TRIM(pbi.item_name)) COLLATE utf8mb4_unicode_ci)
                                              )
                                          )
                                      )
                                  )
                              )
                        ), 0.00) AS damaged_qty
                 FROM purchase_bill_items pbi 
                 JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                 WHERE pbi.id = ?
                   AND (pbi.inward_status = 'completed' OR (pbi.inward_status IS NULL AND pb.inward_status = 'completed'))" . ($cid ? " AND pbi.concern_id = ?" : ""),
                $cid ? [$id, $rollItemId, $cid] : [$id, $rollItemId]
            );

            if (!$rollItem) {
                $this->flash('error', "Roll #" . ($index + 1) . ": Invalid purchased roll or roll has not been added to stock yet.");
                $this->redirect('/production/programs/edit?id=' . $id);
            }

            $totalRollQty = (float)$rollItem['qty'];
            $usedQtyOther = (float)$rollItem['used_qty_other'];
            $damagedQty = (float)$rollItem['damaged_qty'];
            $availableQty = max(0.00, $totalRollQty - $usedQtyOther - $damagedQty);

            if ($allocatedQty > ($availableQty + 0.001)) {
                $tag = $rollItem['item_code'] ?: ('Item #' . $rollItem['id']);
                $this->flash('error', "Roll #" . ($index + 1) . " ({$tag}): Allocated quantity ({$allocatedQty} {$rollItem['unit']}) exceeds available balance ({$availableQty} {$rollItem['unit']} out of {$totalRollQty} {$rollItem['unit']}).");
                $this->redirect('/production/programs/edit?id=' . $id);
            }

            $totalPlannedQty += $allocatedQty;

            $rollWeight = isset($r['roll_weight']) ? (float)$r['roll_weight'] : 0.00;
            if ($rollWeight <= 0) {
                if ($rollItem['unit'] === 'kg') {
                    $rollWeight = $allocatedQty;
                } elseif ($totalRollQty > 0 && (float)($rollItem['net_weight'] ?? 0) > 0) {
                    $rollWeight = round(($allocatedQty / $totalRollQty) * (float)$rollItem['net_weight'], 2);
                }
            }

            $rollPrice = isset($r['roll_price']) ? (float)$r['roll_price'] : 0.00;
            if ($rollPrice <= 0) {
                $unitRate = (float)($rollItem['unit_price'] ?? 0.00);
                $rollPrice = round($allocatedQty * $unitRate, 2);
            }

            $tagCode = QrService::generateTagCode($rollItem['item_name'], $rollItem['color'], $rollItem['pattern'] ?: ($rollItem['specification'] ?? ''), (string)$rollItem['id']);

            $validatedRolls[] = [
                'program_roll_id'       => $programRollId,
                'purchase_bill_item_id' => $rollItemId,
                'roll_piece_tag'        => $tagCode ?: ($rollItem['item_code'] ?? ''),
                'fabric_name'           => $rollItem['item_name'] ?? '',
                'color'                 => $rollItem['color'] ?? '',
                'unit'                  => $rollItem['unit'] ?? 'kg',
                'total_roll_qty'        => $totalRollQty,
                'allocated_qty'         => $allocatedQty,
                'roll_weight'           => $rollWeight,
                'roll_price'            => $rollPrice,
                'target_pieces'         => $targetPieces,
                'category_id'           => $catId,
                'subcategory_id'        => $subcatId,
                'sub_sub_category_id'   => $subSubcatId,
                'catalog_id'            => $catalogId,
            ];
        }

        // Prefer the catalog that the user just changed or added on a roll so the
        // program register reflects the newly assigned style immediately.
        $programCatalogId = $changedCatalogId ?: ($newRollCatalogId ?: $firstCatalogId);

        DB::beginTransaction();
        try {
            DB::query(
                "UPDATE production_programs SET
                    program_name = ?,
                    catalog_id = ?,
                    planned_qty = ?,
                    start_date = ?,
                    target_date = ?,
                    status = ?,
                    updated_at = NOW()
                 WHERE id = ?",
                [
                    $programName,
                    $programCatalogId,
                    $totalPlannedQty,
                    $startDate,
                    $targetDate,
                    $status,
                    $id
                ]
            );

            $keptRollIds = [];
            foreach ($validatedRolls as $vr) {
                $rollValues = [
                    $vr['purchase_bill_item_id'],
                    $vr['roll_piece_tag'],
                    $vr['fabric_name'],
                    $vr['color'],
                    $vr['unit'],
                    $vr['total_roll_qty'],
                    $vr['allocated_qty'],
                    $vr['roll_weight'],
                    $vr['roll_price'],
                    $vr['target_pieces'],
                    $vr['category_id'],
                    $vr['subcategory_id'],
                    $vr['sub_sub_category_id'],
                    $vr['catalog_id'],
                ];

                if ($vr['program_roll_id'] && isset($existingRollMap[$vr['program_roll_id']])) {
                    $programRollId = (int)$vr['program_roll_id'];
                    DB::query(
                        "UPDATE program_rolls SET
                            concern_id = ?,
                            purchase_bill_item_id = ?,
                            roll_piece_tag = ?,
                            fabric_name = ?,
                            color = ?,
                            unit = ?,
                            total_roll_qty = ?,
                            allocated_qty = ?,
                            roll_weight = ?,
                            roll_price = ?,
                            target_pieces = ?,
                            category_id = ?,
                            subcategory_id = ?,
                            sub_sub_category_id = ?,
                            catalog_id = ?
                         WHERE id = ? AND program_id = ?",
                        array_merge([$programConcernId], $rollValues, [$programRollId, $id])
                    );
                } else {
                    DB::query(
                        "INSERT INTO program_rolls (
                            concern_id, program_id, purchase_bill_item_id,
                            roll_piece_tag, fabric_name, color, unit,
                            total_roll_qty, allocated_qty, roll_weight, roll_price, target_pieces,
                            category_id, subcategory_id, sub_sub_category_id, catalog_id,
                            created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                        array_merge([$programConcernId, $id], $rollValues)
                    );
                    $programRollId = (int)DB::lastInsertId();
                }
                $keptRollIds[] = $programRollId;

                // Persist the roll's catalog on the purchased item as well so the
                // category auto-syncs when this roll is used in another program.
                if ($vr['catalog_id']) {
                    $pbiSql = "UPDATE purchase_bill_items SET catalog_id = ? WHERE id = ?";
                    $pbiParams = [$vr['catalog_id'], $vr['purchase_bill_item_id']];
                    if ($programConcernId) {
                        $pbiSql .= " AND concern_id = ?";
                        $pbiParams[] = $programConcernId;
                    }
                    DB::query($pbiSql, $pbiParams);
                }
            }

            // Remove only allocations that were actually removed from the form.
            if (!empty($keptRollIds)) {
                $placeholders = implode(',', array_fill(0, count($keptRollIds), '?'));
                DB::query(
                    "DELETE FROM program_rolls WHERE program_id = ? AND id NOT IN ({$placeholders})",
                    array_merge([$id], $keptRollIds)
                );
            }

            AuditLog::log('UPDATE', 'production_programs', $id, $program, [
                'program_name' => $programName,
                'status'       => $status,
                'planned_qty'  => $totalPlannedQty,
                'rolls_count'  => count($validatedRolls),
            ]);

            DB::commit();
            $this->flash('success', "Production Program '{$program['program_no']}' updated successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to update program: ' . $e->getMessage());
            $this->redirect('/production/programs/edit?id=' . $id);
        }

        $this->redirect('/production/catalog?tab=programs');
    }

    /**
     * Delete or Cancel Production Program
     */
    public function deleteProgram(): void {
        $this->ensureCatalogSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid program ID.');
            $this->redirect('/production/catalog?tab=programs');
        }

        $program = DB::fetch("SELECT * FROM production_programs WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""), $cid ? [$id, $cid] : [$id]);
        if (!$program) {
            $this->flash('error', 'Program not found or access denied.');
            $this->redirect('/production/catalog?tab=programs');
        }

        // Check if downstream logs exist
        $hasCutting = (int)DB::fetchValue("SELECT COUNT(*) FROM cutting_logs WHERE program_id = ?", [$id]);
        $hasStitching = (int)DB::fetchValue("SELECT COUNT(*) FROM stitching_logs WHERE program_id = ?", [$id]);

        if ($hasCutting > 0 || $hasStitching > 0) {
            // Cannot hard delete because cutting/stitching consumed goods. Mark cancelled.
            DB::query("UPDATE production_programs SET status = 'cancelled', updated_at = NOW() WHERE id = ?", [$id]);
            AuditLog::log('UPDATE', 'production_programs', $id, $program, ['status' => 'cancelled']);
            $this->flash('warning', "Program '{$program['program_no']}' has active cutting/production history. It has been marked as Cancelled rather than deleted to protect ledger integrity.");
        } else {
            DB::beginTransaction();
            try {
                DB::query("DELETE FROM program_rolls WHERE program_id = ?", [$id]);
                DB::query("DELETE FROM production_programs WHERE id = ?", [$id]);
                AuditLog::log('DELETE', 'production_programs', $id, $program, null);
                DB::commit();
                $this->flash('success', "Production Program '{$program['program_no']}' deleted successfully and fabric rolls released back to stock.");
            } catch (Exception $e) {
                DB::rollBack();
                $this->flash('error', 'Delete failed: ' . $e->getMessage());
            }
        }

        $this->redirect('/production/catalog?tab=programs');
    }

    /**
     * Quick status update for Production Program
     */
    public function updateProgramStatus(): void {
        $this->ensureCatalogSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (!in_array($status, ['planned', 'in_progress', 'completed', 'cancelled'])) {
            $this->flash('error', 'Invalid status specified.');
            $this->redirect('/production/catalog?tab=programs');
        }

        $program = DB::fetch("SELECT * FROM production_programs WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""), $cid ? [$id, $cid] : [$id]);
        if (!$program) {
            $this->flash('error', 'Program not found or access denied.');
            $this->redirect('/production/catalog?tab=programs');
        }

        DB::query("UPDATE production_programs SET status = ?, updated_at = NOW() WHERE id = ?", [$status, $id]);
        AuditLog::log('UPDATE', 'production_programs', $id, $program, ['status' => $status]);
        $this->flash('success', "Program '{$program['program_no']}' status changed to " . ucfirst(str_replace('_', ' ', $status)) . ".");
        $this->redirect('/production/catalog?tab=programs');
    }

    // ========================================================
    // 3. CUTTING & WASTAGE FLOW (MATCHING UI IMAGES 1, 2, 3)
    // ========================================================

    /**
     * Ensure Cutting & Wastage Schema (Tables & Columns)
     */
    private function ensureCuttingSchema(): void {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        DB::query("
            CREATE TABLE IF NOT EXISTS cutting_masters (
                id INT AUTO_INCREMENT PRIMARY KEY,
                concern_id INT NOT NULL,
                name VARCHAR(150) NOT NULL,
                phone VARCHAR(30) NULL,
                employee_code VARCHAR(50) NULL,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cm_concern (concern_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $existingCols = array_column(DB::fetchAll("DESCRIBE cutting_logs"), 'Field');
        $colsToAdd = [
            'batch_no'              => "VARCHAR(50) NULL AFTER log_no",
            'lot_no'                => "VARCHAR(50) NULL AFTER batch_no",
            'cutting_name'          => "VARCHAR(255) NULL AFTER lot_no",
            'status'                => "ENUM('in_progress', 'planned', 'completed') DEFAULT 'in_progress' AFTER cutting_name",
            'cutting_master_id'     => "INT NULL AFTER status",
            'cutting_master_name'   => "VARCHAR(150) NULL AFTER cutting_master_id",
            'total_roll_weight'     => "DECIMAL(12,2) DEFAULT 0.00 AFTER consumed_qty",
            'total_roll_price'      => "DECIMAL(12,2) DEFAULT 0.00 AFTER total_roll_weight",
            'fabric_rate_per_kg'    => "DECIMAL(12,2) DEFAULT 0.00 AFTER total_roll_price",
            'wastage_cost'          => "DECIMAL(12,2) DEFAULT 0.00 AFTER wastage_qty",
            'wastage_percent'       => "DECIMAL(5,2) DEFAULT 0.00 AFTER wastage_cost",
            'used_weight'           => "DECIMAL(12,2) DEFAULT 0.00 AFTER wastage_percent",
            'fabric_cost_per_piece' => "DECIMAL(12,2) DEFAULT 0.00 AFTER used_weight",
            'sold_wastage_qty'      => "DECIMAL(12,2) DEFAULT 0.00 AFTER wastage_unit",
        ];

        foreach ($colsToAdd as $col => $def) {
            if (!in_array($col, $existingCols)) {
                DB::query("ALTER TABLE cutting_logs ADD COLUMN $col $def");
            }
        }

        DB::query("ALTER TABLE cutting_logs MODIFY COLUMN raw_variant_id INT NULL");

        DB::query("
            CREATE TABLE IF NOT EXISTS cutting_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cutting_id INT NOT NULL,
                program_roll_id INT NULL,
                variant_id INT NULL,
                size VARCHAR(50) NULL,
                product_code VARCHAR(100) NULL,
                variant_name VARCHAR(255) NULL,
                cut_pieces INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_ci_cutting (cutting_id),
                KEY idx_ci_roll (program_roll_id),
                KEY idx_ci_variant (variant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        DB::query("
            CREATE TABLE IF NOT EXISTS cutting_wastage_sales (
                id INT AUTO_INCREMENT PRIMARY KEY,
                concern_id INT NOT NULL,
                sale_no VARCHAR(50) NOT NULL,
                cutting_id INT NOT NULL,
                buyer_name VARCHAR(150) NOT NULL,
                buyer_phone VARCHAR(20) NOT NULL,
                buyer_company VARCHAR(200) NULL,
                wastage_category VARCHAR(100) NOT NULL,
                wastage_weight DECIMAL(12,2) NOT NULL,
                selling_rate DECIMAL(12,2) NOT NULL,
                total_price DECIMAL(12,2) NOT NULL,
                amount_received DECIMAL(12,2) DEFAULT 0.00,
                sale_date DATE NOT NULL,
                payment_status ENUM('paid', 'partial', 'pending') DEFAULT 'paid',
                notes TEXT NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cws_concern (concern_id),
                KEY idx_cws_cutting (cutting_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /**
     * Generate next Cutting Batch No: CUT-YYYYMMDD-XXXX
     */
    private function generateCuttingBatchNo(?int $cid = null): string {
        $prefix = 'CUT-' . date('Ymd') . '-';
        $sql = "SELECT batch_no FROM cutting_logs WHERE batch_no LIKE ? ORDER BY id DESC LIMIT 1";
        $last = DB::fetch($sql, [$prefix . '%']);
        $seq = 1;
        if ($last && !empty($last['batch_no'])) {
            $parts = explode('-', $last['batch_no']);
            $lastNum = (int)end($parts);
            $seq = $lastNum + 1;
        }
        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate next Wastage Scrap Sale No: SCRAP-YYYYMMDD-XXXX
     */
    private function generateWastageSaleNo(?int $cid = null): string {
        $prefix = 'SCRAP-' . date('Ymd') . '-';
        $sql = "SELECT sale_no FROM cutting_wastage_sales WHERE sale_no LIKE ? ORDER BY id DESC LIMIT 1";
        $last = DB::fetch($sql, [$prefix . '%']);
        $seq = 1;
        if ($last && !empty($last['sale_no'])) {
            $parts = explode('-', $last['sale_no']);
            $lastNum = (int)end($parts);
            $seq = $lastNum + 1;
        }
        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Cutting Module Index (List of Cutting Batches + Wastage Selling Register)
     */
    public function cuttingIndex(): void {
        ModuleGuard::enforce('cutting_wastage', 'view');
        $this->ensureCuttingSchema();
        $cid = Auth::concernId();
        $tab = $_GET['tab'] ?? 'batches';

        // 1. Fetch Cutting Batches
        $sql = "SELECT cl.*, 
                       pp.program_no, pp.program_name, pp.planned_qty AS program_planned_qty,
                       c.name AS catalog_name, c.style_code AS catalog_code,
                       COALESCE(cl.cutting_master_name, cm.name) AS master_name,
                       con.name AS concern_name,
                       (SELECT COUNT(*) FROM cutting_items WHERE cutting_id = cl.id) AS variant_count,
                       (SELECT COALESCE(SUM(cut_pieces), 0) FROM cutting_items WHERE cutting_id = cl.id) AS total_cut_pieces_sum,
                       (SELECT COUNT(DISTINCT program_roll_id) FROM cutting_items WHERE cutting_id = cl.id) AS rolls_used_count
                FROM cutting_logs cl
                LEFT JOIN production_programs pp ON cl.program_id = pp.id
                LEFT JOIN catalogs c ON pp.catalog_id = c.id
                    AND c.source_module = 'production_catalog'
                    AND (c.concern_id = pp.concern_id OR c.concern_id = 1 OR c.concern_id IS NULL)
                LEFT JOIN cutting_masters cm ON cl.cutting_master_id = cm.id
                LEFT JOIN concerns con ON cl.concern_id = con.id";
        $params = [];
        if ($cid) {
            $sql .= " WHERE cl.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY cl.id DESC";
        $batches = DB::fetchAll($sql, $params);

        if (!empty($batches)) {
            $batchIds = array_map(static fn($row) => (int)$row['id'], $batches);
            $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
            $batchCatalogRows = DB::fetchAll(
                "SELECT DISTINCT ci.cutting_id, cat.id AS catalog_id, cat.style_code, cat.name AS catalog_name
                 FROM cutting_items ci
                 LEFT JOIN program_rolls pr ON ci.program_roll_id = pr.id
                 LEFT JOIN variants v ON ci.variant_id = v.id
                 JOIN catalogs cat ON cat.id = COALESCE(NULLIF(pr.catalog_id, 0), v.catalog_id)
                 WHERE ci.cutting_id IN ({$placeholders})
                 ORDER BY cat.id ASC",
                $batchIds
            );
            $catalogMap = [];
            foreach ($batchCatalogRows as $cr) {
                $cidKey = (int)$cr['cutting_id'];
                $catIdKey = (int)$cr['catalog_id'];
                if (!isset($catalogMap[$cidKey][$catIdKey])) {
                    $catalogMap[$cidKey][$catIdKey] = [
                        'id' => $catIdKey,
                        'style_code' => $cr['style_code'],
                        'name' => $cr['catalog_name'],
                    ];
                }
            }
            foreach ($batches as &$bRow) {
                $bid = (int)$bRow['id'];
                if (!empty($catalogMap[$bid])) {
                    $bRow['catalogs'] = array_values($catalogMap[$bid]);
                } elseif (!empty($bRow['catalog_name'])) {
                    $bRow['catalogs'] = [[
                        'id' => 0,
                        'style_code' => $bRow['catalog_code'] ?? '',
                        'name' => $bRow['catalog_name'],
                    ]];
                } else {
                    $bRow['catalogs'] = [];
                }
            }
            unset($bRow);
        }

        // 2. Available Cutting Wastage for Scrap Sale
        $availSql = "SELECT cl.*, 
                            pp.program_no, pp.program_name,
                            (cl.wastage_qty - cl.sold_wastage_qty) AS available_wastage_qty,
                            COALESCE((
                                SELECT s.name FROM program_rolls pr 
                                JOIN purchase_bill_items pbi ON pr.purchase_bill_item_id = pbi.id 
                                JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id 
                                LEFT JOIN suppliers s ON pb.supplier_id = s.id 
                                WHERE pr.program_id = cl.program_id LIMIT 1
                            ), 'Direct Purchase') AS supplier_display
                     FROM cutting_logs cl
                     LEFT JOIN production_programs pp ON cl.program_id = pp.id
                     WHERE (cl.wastage_qty - cl.sold_wastage_qty) > 0.001" . ($cid ? " AND cl.concern_id = ?" : "") . "
                     ORDER BY cl.id DESC";
        $availableWastages = DB::fetchAll($availSql, $cid ? [$cid] : []);

        // 3. Scrap Sales History
        $salesSql = "SELECT cws.*, 
                            cl.batch_no, cl.cutting_name, cl.wastage_qty, cl.sold_wastage_qty, pp.program_no,
                            con.name AS concern_name
                     FROM cutting_wastage_sales cws
                     JOIN cutting_logs cl ON cws.cutting_id = cl.id
                     LEFT JOIN production_programs pp ON cl.program_id = pp.id
                     LEFT JOIN concerns con ON cws.concern_id = con.id";
        if ($cid) {
            $salesSql .= " WHERE cws.concern_id = ?";
        }
        $salesSql .= " ORDER BY cws.id DESC";
        $scrapSales = DB::fetchAll($salesSql, $cid ? [$cid] : []);

        // Aggregate stats
        $totalWastageGen = 0.00;
        $totalWastageSold = 0.00;
        foreach ($batches as $b) {
            $totalWastageGen += (float)$b['wastage_qty'];
            $totalWastageSold += (float)$b['sold_wastage_qty'];
        }
        $totalScrapRevenue = 0.00;
        foreach ($scrapSales as $s) {
            $totalScrapRevenue += (float)$s['total_price'];
        }

        $this->render('concern/cutting_list', [
            'pageTitle'         => 'Fabric Cutting & Wastage Tracking',
            'currentModule'     => 'cutting_wastage',
            'activeTab'         => $tab,
            'batches'           => $batches,
            'availableWastages' => $availableWastages,
            'scrapSales'        => $scrapSales,
            'totalWastageGen'   => $totalWastageGen,
            'totalWastageSold'  => $totalWastageSold,
            'availableWastage'  => max(0.00, $totalWastageGen - $totalWastageSold),
            'totalScrapRevenue' => $totalScrapRevenue,
            'selectedCid'       => $cid,
        ]);
    }

    /**
     * Backward-compatible alias for cutting()
     */
    public function cutting(): void {
        $this->cuttingIndex();
    }

    /**
     * Create Cutting Batch Form (Matching UI Images 1 & 2)
     */
    public function createCutting(): void {
        ModuleGuard::enforce('cutting_wastage', 'add');
        $this->ensureCatalogSchema();
        $this->ensureCuttingSchema();
        $cid = Auth::concernId();

        $batchNo = $this->generateCuttingBatchNo($cid);

        // Fetch active production programs that have UNCUT assigned fabric rolls
        $params = [];
        $progSql = "SELECT pp.id, pp.concern_id, pp.program_no, pp.program_name, pp.planned_qty, con.name AS concern_name,
                           COUNT(pr.id) AS rolls_count,
                           COALESCE(SUM(pr.roll_weight), 0.00) AS total_rolls_weight,
                           COALESCE(SUM(pr.roll_price), 0.00) AS total_rolls_price
                    FROM production_programs pp
                    JOIN program_rolls pr ON pp.id = pr.program_id
                    LEFT JOIN purchase_bill_items pbi ON pr.purchase_bill_item_id = pbi.id
                    LEFT JOIN concerns con ON pp.concern_id = con.id
                    WHERE pp.status != 'cancelled'
                      AND (pp.catalog_id IS NULL OR EXISTS (
                          SELECT 1 FROM catalogs pc
                          WHERE pc.id = pp.catalog_id AND pc.source_module = 'production_catalog'
                            AND (pc.concern_id = pp.concern_id OR pc.concern_id = 1 OR pc.concern_id IS NULL)
                      ))
                      AND pr.concern_id = pp.concern_id
                      AND (pbi.id IS NULL OR (pbi.section_type = 'rolls' AND pbi.concern_id = pr.concern_id))
                      AND pr.id NOT IN (
                          SELECT DISTINCT program_roll_id 
                          FROM cutting_items 
                          WHERE program_roll_id IS NOT NULL AND cut_pieces > 0
                      )";
        if ($cid) {
            $progSql .= " AND pp.concern_id = ?";
            $params[] = $cid;
        }
        $progSql .= " GROUP BY pp.id, pp.concern_id, pp.program_no, pp.program_name, pp.planned_qty, con.name HAVING rolls_count > 0 ORDER BY pp.id DESC";
        $programs = DB::fetchAll($progSql, $params);

        // Fetch active cutting masters
        $masterSql = "SELECT * FROM cutting_masters WHERE status = 'active'" . ($cid ? " AND concern_id = ?" : "") . " ORDER BY name ASC";
        $cuttingMasters = DB::fetchAll($masterSql, $cid ? [$cid] : []);

        $this->render('concern/cutting_create', [
            'pageTitle'      => 'New Cutting Batch',
            'currentModule'  => 'cutting_wastage',
            'batchNo'        => $batchNo,
            'programs'       => $programs,
            'cuttingMasters' => $cuttingMasters,
            'selectedCid'    => $cid,
        ]);
    }

    /**
     * AJAX endpoint to fetch rolls & dynamic variant breakdown for a selected program
     */
    public function apiCuttingProgramDetails(): void {
        header('Content-Type: application/json');
        try {
        $this->ensureCatalogSchema();
        $this->ensureCuttingSchema();
        $cid = Auth::concernId();
        $progId = (int)($_GET['id'] ?? ($_GET['program_id'] ?? 0));

        if (!$progId) {
            echo json_encode(['success' => false, 'message' => 'Invalid program ID.']);
            exit;
        }

        $program = DB::fetch(
            "SELECT pp.*, c.name AS catalog_name, c.style_code AS catalog_code 
             FROM production_programs pp 
             LEFT JOIN catalogs c ON pp.catalog_id = c.id AND c.source_module = 'production_catalog'
                 AND (c.concern_id = pp.concern_id OR c.concern_id = 1 OR c.concern_id IS NULL)
             WHERE pp.id = ? AND pp.status != 'cancelled' AND (pp.catalog_id IS NULL OR c.id IS NOT NULL)" . ($cid ? " AND pp.concern_id = ?" : ""),
            $cid ? [$progId, $cid] : [$progId]
        );

        if (!$program) {
            echo json_encode(['success' => false, 'message' => 'Program not found or access denied.']);
            exit;
        }

        $rolls = DB::fetchAll(
            "SELECT pr.*, 
                    pbi.item_code, pbi.item_name, pbi.pattern, pbi.color, pbi.unit, pbi.net_weight, pbi.unit_price,
                    cat.name AS category_name, sub.name AS subcategory_name, subsub.name AS subsub_name,
                    c.id AS roll_catalog_id,
                    c.name AS roll_catalog_name,
                    c.style_code AS roll_style_code,
                    c.sizes AS roll_catalog_sizes
             FROM program_rolls pr
             LEFT JOIN purchase_bill_items pbi ON pr.purchase_bill_item_id = pbi.id
             LEFT JOIN categories cat ON pr.category_id = cat.id
             LEFT JOIN subcategories sub ON pr.subcategory_id = sub.id
             LEFT JOIN sub_subcategories subsub ON pr.sub_sub_category_id = subsub.id
             LEFT JOIN catalogs c ON c.id = COALESCE(
                 (SELECT c0.id FROM catalogs c0 WHERE c0.id = pr.catalog_id AND c0.source_module = 'production_catalog' AND (c0.concern_id = pr.concern_id OR c0.concern_id = 1 OR c0.concern_id IS NULL)),
                 (SELECT pp.catalog_id FROM production_programs pp JOIN catalogs pc ON pc.id = pp.catalog_id AND pc.source_module = 'production_catalog' AND (pc.concern_id = pp.concern_id OR pc.concern_id = 1 OR pc.concern_id IS NULL) WHERE pp.id = pr.program_id),
                 (SELECT c1.id FROM catalogs c1 WHERE c1.source_module = 'production_catalog' AND (c1.concern_id = pr.concern_id OR c1.concern_id = 1 OR c1.concern_id IS NULL) AND pr.sub_sub_category_id IS NOT NULL AND c1.sub_sub_category_id = pr.sub_sub_category_id ORDER BY c1.id ASC LIMIT 1),
                 (SELECT c2.id FROM catalogs c2 WHERE c2.source_module = 'production_catalog' AND (c2.concern_id = pr.concern_id OR c2.concern_id = 1 OR c2.concern_id IS NULL) AND pr.subcategory_id IS NOT NULL AND c2.subcategory_id = pr.subcategory_id AND (pr.category_id IS NULL OR c2.category_id = pr.category_id) ORDER BY c2.id ASC LIMIT 1),
                 (SELECT c3.id FROM catalogs c3 WHERE c3.source_module = 'production_catalog' AND (c3.concern_id = pr.concern_id OR c3.concern_id = 1 OR c3.concern_id IS NULL) AND pr.category_id IS NOT NULL AND c3.category_id = pr.category_id ORDER BY c3.id ASC LIMIT 1)
             ) AND c.source_module = 'production_catalog'
             WHERE pr.program_id = ?
               AND pr.concern_id = ?
               AND (pbi.id IS NULL OR (pbi.section_type = 'rolls' AND pbi.concern_id = pr.concern_id))
               AND pr.id NOT IN (
                   SELECT DISTINCT program_roll_id 
                   FROM cutting_items 
                   WHERE program_roll_id IS NOT NULL AND cut_pieces > 0
               )
             ORDER BY pr.id ASC",
            [$progId, (int)$program['concern_id']]
        );

        if (empty($rolls)) {
            echo json_encode([
                'success' => false,
                'message' => 'All fabric rolls in this production program have already been cut into batches.'
            ]);
            exit;
        }

        $totalWeight = 0.00;
        $totalPrice = 0.00;
        $rollsData = [];

        foreach ($rolls as $r) {
            $weight = (float)$r['roll_weight'];
            if ($weight <= 0) {
                $weight = (float)$r['allocated_qty'];
            }
            $price = (float)$r['roll_price'];
            if ($price <= 0) {
                $price = (float)$r['allocated_qty'] * (float)($r['unit_price'] ?? 0);
            }

            $totalWeight += $weight;
            $totalPrice += $price;

            $hierParts = array_filter([$r['category_name'] ?? '', $r['subcategory_name'] ?? '', $r['subsub_name'] ?? '']);
            $r['hierarchy_display'] = !empty($hierParts) ? implode(' > ', $hierParts) : '';
            if (empty($r['roll_catalog_name']) && !empty($r['hierarchy_display'])) {
                $r['roll_catalog_name'] = $r['hierarchy_display'];
            }

            $catId = $r['roll_catalog_id'] ?: null;
            $variants = [];

            if ($catId) {
                $variants = DB::fetchAll(
                    "SELECT id, product_code, name, size, weight, price, mrp 
                     FROM variants 
                     WHERE catalog_id = ? AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND status = 'active'
                     ORDER BY id ASC",
                    [$catId, (int)$program['concern_id']]
                );

                if (empty($variants)) {
                    $catRecord = DB::fetch("SELECT id, style_code, name, sizes FROM catalogs WHERE id = ? AND source_module = 'production_catalog' AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)", [$catId, (int)$program['concern_id']]);
                    if ($catRecord && !empty($catRecord['sizes'])) {
                        $sizesList = json_decode($catRecord['sizes'], true);
                        if (is_array($sizesList)) {
                            foreach ($sizesList as $s) {
                                $variants[] = [
                                    'id'       => null,
                                    'product_code' => ($catRecord['style_code'] ?: 'STY') . '-' . strtoupper($s),
                                    'name'     => $catRecord['name'] . ' - ' . strtoupper($s),
                                    'size'     => strtoupper($s),
                                    'price'    => 0.00,
                                ];
                            }
                        }
                    }
                }
            }

            if (empty($variants)) {
                $defaultSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'Free Size'];
                foreach ($defaultSizes as $ds) {
                    $variants[] = [
                        'id'       => null,
                        'product_code' => ($r['roll_piece_tag'] ?: 'ROLL') . '-' . $ds,
                        'name'     => ($r['fabric_name'] ?: 'Fabric') . ' (' . ($r['color'] ?: 'Standard') . ') - ' . $ds,
                        'size'     => $ds,
                        'price'    => 0.00,
                    ];
                }
            }

            $r['calculated_weight'] = round($weight, 2);
            $r['calculated_price']  = round($price, 2);
            $r['variants']          = $variants;
            $rollsData[]            = $r;
        }

        $fabricRate = $totalWeight > 0 ? round($totalPrice / $totalWeight, 2) : 0.00;

        echo json_encode([
            'success'            => true,
            'program'            => $program,
            'rolls'              => $rollsData,
            'total_roll_weight'  => round($totalWeight, 2),
            'total_roll_price'   => round($totalPrice, 2),
            'fabric_rate_per_kg' => $fabricRate,
        ]);
        exit;
        } catch (\Throwable $e) {
            error_log('Cutting program details failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Unable to load program details. Please try again.']);
            exit;
        }
    }

    /**
     * AJAX endpoint to quickly add a cutting master / operator
     */
    public function apiQuickAddCuttingMaster(): void {
        header('Content-Type: application/json');
        $this->ensureCuttingSchema();
        $cid = Auth::concernId();
        $programId = (int)($_POST['program_id'] ?? 0);

        if ($programId > 0) {
            $programSql = "SELECT concern_id FROM production_programs WHERE id = ? AND status != 'cancelled'";
            $programParams = [$programId];
            if ($cid) {
                $programSql .= " AND concern_id = ?";
                $programParams[] = $cid;
            }
            $program = DB::fetch($programSql, $programParams);
            if (!$program) {
                echo json_encode(['success' => false, 'message' => 'Select a valid production program before adding a cutting master.']);
                exit;
            }
            if (!$cid) {
                $cid = (int)$program['concern_id'];
            }
        }

        if (!$cid || !DB::fetchValue("SELECT id FROM concerns WHERE id = ? AND status = 'active'", [$cid])) {
            echo json_encode(['success' => false, 'message' => 'Select a concern or production program before adding a cutting master.']);
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $code = trim($_POST['employee_code'] ?? '');

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Cutting Master Name is required.']);
            exit;
        }

        if (!empty($phone) && !preg_match('/^[6-9]\d{9}$/', $phone)) {
            echo json_encode(['success' => false, 'message' => 'Phone number must be a valid 10-digit mobile number starting with 6, 7, 8, or 9.']);
            exit;
        }

        try {
            DB::query(
                "INSERT INTO cutting_masters (concern_id, name, phone, employee_code, status, created_at)
                 VALUES (?, ?, ?, ?, 'active', NOW())",
                [$cid, $name, $phone, $code]
            );
            $id = (int)DB::lastInsertId();

            echo json_encode([
                'success' => true,
                'id'      => $id,
                'name'    => $name,
                'phone'   => $phone,
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Store Cutting Batch & Dynamic Variant Breakdown (Images 1 & 2)
     */
    public function storeCuttingBatch(): void {
        ModuleGuard::enforce('cutting_wastage', 'add');
        $this->ensureCuttingSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();

        $progId = (int)($_POST['program_id'] ?? 0);
        $cuttingName = trim($_POST['cutting_name'] ?? '');
        $status = $_POST['status'] ?? 'in_progress';
        if (!in_array($status, ['in_progress', 'planned', 'completed'])) {
            $status = 'in_progress';
        }
        $cuttingMasterId = !empty($_POST['cutting_master_id']) ? (int)$_POST['cutting_master_id'] : null;
        $notes = trim($_POST['notes'] ?? '');

        if (!$progId) {
            $this->flash('error', 'Please select a Production Program.');
            $this->redirect('/production/cutting/create');
        }

        if (empty($cuttingName)) {
            $this->flash('error', 'Cutting Name is required.');
            $this->redirect('/production/cutting/create');
        }

        $program = DB::fetch(
            "SELECT * FROM production_programs WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""),
            $cid ? [$progId, $cid] : [$progId]
        );

        if (!$program) {
            $this->flash('error', 'Selected Production Program not found or access denied.');
            $this->redirect('/production/cutting/create');
        }

        $batchConcernId = $cid ?: (int)$program['concern_id'];

        $masterName = null;
        if ($cuttingMasterId) {
            $cm = DB::fetch("SELECT name FROM cutting_masters WHERE id = ?", [$cuttingMasterId]);
            $masterName = $cm['name'] ?? null;
        }

        // Fetch uncut rolls belonging to this program
        $rolls = DB::fetchAll(
            "SELECT * FROM program_rolls 
             WHERE program_id = ?
               AND id NOT IN (
                   SELECT DISTINCT program_roll_id 
                   FROM cutting_items 
                   WHERE program_roll_id IS NOT NULL AND cut_pieces > 0
               )",
            [$progId]
        );
        if (empty($rolls)) {
            $this->flash('error', 'All assigned fabric rolls in this Production Program have already been cut into batches.');
            $this->redirect('/production/cutting/create');
        }

        $totalRollWeight = 0.00;
        $totalRollPrice = 0.00;
        foreach ($rolls as $r) {
            $w = (float)$r['roll_weight'] > 0 ? (float)$r['roll_weight'] : (float)$r['allocated_qty'];
            $p = (float)$r['roll_price'] > 0 ? (float)$r['roll_price'] : 0.00;
            $totalRollWeight += $w;
            $totalRollPrice += $p;
        }

        $fabricRatePerKg = $totalRollWeight > 0 ? round($totalRollPrice / $totalRollWeight, 2) : 0.00;

        $wastageWeight = round((float)($_POST['wastage_qty'] ?? 0.00), 2);
        if ($wastageWeight < 0) {
            $wastageWeight = 0.00;
        }

        if ($totalRollWeight > 0 && $wastageWeight > $totalRollWeight) {
            $this->flash('error', "Wastage Fabric Weight ({$wastageWeight} kg) cannot exceed Total Roll Weight ({$totalRollWeight} kg).");
            $this->redirect('/production/cutting/create');
        }

        $usedWeight = max(0.00, $totalRollWeight - $wastageWeight);
        $wastageCost = round($wastageWeight * $fabricRatePerKg, 2);
        $wastagePct = $totalRollWeight > 0 ? round(($wastageWeight / $totalRollWeight) * 100, 2) : 0.00;

        // Process variant cut pieces breakdown from Section 3
        $submittedRolls = $_POST['rolls'] ?? [];
        $validatedItems = [];
        $totalCutPieces = 0;

        if (is_array($submittedRolls)) {
            foreach ($submittedRolls as $rollId => $rollData) {
                $rollIdInt = (int)$rollId;

                // Validate that this roll hasn't already been cut
                $alreadyCut = DB::fetch(
                    "SELECT pr.roll_piece_tag, cl.batch_no 
                     FROM program_rolls pr
                     JOIN cutting_items ci ON pr.id = ci.program_roll_id
                     JOIN cutting_logs cl ON ci.cutting_id = cl.id
                     WHERE pr.id = ? AND ci.cut_pieces > 0
                     LIMIT 1",
                    [$rollIdInt]
                );
                if ($alreadyCut) {
                    $this->flash('error', "Fabric Roll '{$alreadyCut['roll_piece_tag']}' has already been cut in Batch [{$alreadyCut['batch_no']}] and cannot be cut again.");
                    $this->redirect('/production/cutting/create');
                }

                $varList = $rollData['variants'] ?? [];
                if (is_array($varList)) {
                    foreach ($varList as $vData) {
                        $pieces = (int)($vData['cut_pieces'] ?? 0);
                        if ($pieces > 0) {
                            $totalCutPieces += $pieces;
                            $validatedItems[] = [
                                'program_roll_id' => (int)$rollId,
                                'variant_id'      => !empty($vData['variant_id']) ? (int)$vData['variant_id'] : null,
                                'size'            => trim($vData['size'] ?? ''),
                                'product_code'        => trim($vData['product_code'] ?? ''),
                                'variant_name'    => trim($vData['variant_name'] ?? ''),
                                'cut_pieces'      => $pieces,
                            ];
                        }
                    }
                }
            }
        }

        if ($totalCutPieces <= 0) {
            $this->flash('error', 'Please enter cut pieces for at least one variant/size in Section 3.');
            $this->redirect('/production/cutting/create');
        }

        $fabricCostPerPiece = $totalCutPieces > 0 ? round(($totalRollPrice - $wastageCost) / $totalCutPieces, 2) : 0.00;

        $batchNo = $this->generateCuttingBatchNo($batchConcernId);

        DB::beginTransaction();
        try {
            DB::query(
                "INSERT INTO cutting_logs (
                    concern_id, log_no, batch_no, cutting_name, status,
                    program_id, cutting_master_id, cutting_master_name,
                    consumed_qty, total_roll_weight, total_roll_price, fabric_rate_per_kg,
                    cut_pieces, wastage_qty, wastage_cost, wastage_percent, used_weight, fabric_cost_per_piece,
                    sold_wastage_qty, wastage_unit, notes, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 'kg', ?, ?, NOW())",
                [
                    $batchConcernId,
                    $batchNo,
                    $batchNo,
                    $cuttingName,
                    $status,
                    $progId,
                    $cuttingMasterId,
                    $masterName,
                    $usedWeight,
                    $totalRollWeight,
                    $totalRollPrice,
                    $fabricRatePerKg,
                    $totalCutPieces,
                    $wastageWeight,
                    $wastageCost,
                    $wastagePct,
                    $usedWeight,
                    $fabricCostPerPiece,
                    $notes,
                    Auth::id()
                ]
            );
            $cuttingId = (int)DB::lastInsertId();

            // Insert each variant cutting item
            foreach ($validatedItems as $it) {
                DB::query(
                    "INSERT INTO cutting_items (
                        cutting_id, program_roll_id, variant_id, size, product_code, variant_name, cut_pieces, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $cuttingId,
                        $it['program_roll_id'],
                        $it['variant_id'],
                        $it['size'],
                        $it['product_code'],
                        $it['variant_name'],
                        $it['cut_pieces']
                    ]
                );
            }

            // Update production program status
            DB::query("UPDATE production_programs SET status = 'in_progress', updated_at = NOW() WHERE id = ? AND status = 'planned'", [$progId]);

            AuditLog::log('CREATE', 'cutting_logs', $cuttingId, null, [
                'batch_no'         => $batchNo,
                'cutting_name'     => $cuttingName,
                'program_id'       => $progId,
                'total_cut_pieces' => $totalCutPieces,
                'wastage_qty'      => $wastageWeight,
            ]);

            DB::commit();
            $this->flash('success', "Cutting Batch '{$batchNo}' ({$cuttingName}) created successfully with {$totalCutPieces} cut pieces!");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to save cutting batch: ' . $e->getMessage());
            $this->redirect('/production/cutting/create');
        }

        $this->redirect('/production/cutting');
    }

    /**
     * View Cutting Batch Details
     */
    public function viewCuttingBatch(): void {
        $this->ensureCuttingSchema();
        $cid = Auth::concernId();
        $id = (int)($_GET['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid cutting batch ID.');
            $this->redirect('/production/cutting');
        }

        $batch = DB::fetch(
            "SELECT cl.*, pp.program_no, pp.program_name, cm.name AS master_name, con.name AS concern_name
             FROM cutting_logs cl
             LEFT JOIN production_programs pp ON cl.program_id = pp.id
             LEFT JOIN cutting_masters cm ON cl.cutting_master_id = cm.id
             LEFT JOIN concerns con ON cl.concern_id = con.id
             WHERE cl.id = ?" . ($cid ? " AND cl.concern_id = ?" : ""),
            $cid ? [$id, $cid] : [$id]
        );

        if (!$batch) {
            $this->flash('error', 'Cutting batch not found or access denied.');
            $this->redirect('/production/cutting');
        }

        $items = DB::fetchAll(
            "SELECT ci.*, pr.roll_piece_tag, pr.fabric_name, pr.color 
             FROM cutting_items ci
             LEFT JOIN program_rolls pr ON ci.program_roll_id = pr.id
             WHERE ci.cutting_id = ?
             ORDER BY ci.program_roll_id ASC, " . self::sizeOrderSql('ci.size') . ", ci.id ASC",
            [$id]
        );

        $sales = DB::fetchAll(
            "SELECT * FROM cutting_wastage_sales WHERE cutting_id = ? ORDER BY id DESC",
            [$id]
        );

        $this->render('concern/cutting_view', [
            'pageTitle'     => 'Cutting Batch: ' . ($batch['batch_no'] ?: $batch['log_no']),
            'currentModule' => 'cutting_wastage',
            'batch'         => $batch,
            'items'         => $items,
            'sales'         => $sales,
            'selectedCid'   => $cid,
        ]);
    }

    /**
     * Edit Cutting Batch Form
     */
    public function editCuttingBatch(): void {
        ModuleGuard::enforce('cutting_wastage', 'edit');
        $this->ensureCuttingSchema();
        $cid = Auth::concernId();
        $id = (int)($_GET['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid cutting batch ID.');
            $this->redirect('/production/cutting');
        }

        $batch = DB::fetch(
            "SELECT cl.*, pp.program_no, pp.program_name, pp.planned_qty, pp.catalog_id AS program_catalog_id, cm.name AS master_name, con.name AS concern_name
             FROM cutting_logs cl
             LEFT JOIN production_programs pp ON cl.program_id = pp.id
             LEFT JOIN cutting_masters cm ON cl.cutting_master_id = cm.id
             LEFT JOIN concerns con ON cl.concern_id = con.id
             WHERE cl.id = ?" . ($cid ? " AND cl.concern_id = ?" : ""),
            $cid ? [$id, $cid] : [$id]
        );

        if (!$batch) {
            $this->flash('error', 'Cutting batch not found or access denied.');
            $this->redirect('/production/cutting');
        }

        // Fetch cutting items grouped by program_roll_id
        $items = DB::fetchAll(
            "SELECT ci.*, pr.roll_piece_tag, pr.fabric_name, pr.color 
             FROM cutting_items ci
             LEFT JOIN program_rolls pr ON ci.program_roll_id = pr.id
             WHERE ci.cutting_id = ?
             ORDER BY ci.program_roll_id ASC, " . self::sizeOrderSql('ci.size') . ", ci.id ASC",
            [$id]
        );

        // Fetch rolls belonging to this batch
        $rolls = DB::fetchAll(
            "SELECT pr.*, 
                    pbi.item_code, pbi.item_name, pbi.pattern, pbi.color, pbi.unit, pbi.net_weight, pbi.unit_price,
                    cat.name AS category_name, sub.name AS subcategory_name, subsub.name AS subsub_name,
                    c.id AS roll_catalog_id,
                    c.name AS roll_catalog_name,
                    c.style_code AS roll_style_code,
                    c.sizes AS roll_catalog_sizes
             FROM program_rolls pr
             LEFT JOIN purchase_bill_items pbi ON pr.purchase_bill_item_id = pbi.id
             LEFT JOIN categories cat ON pr.category_id = cat.id
             LEFT JOIN subcategories sub ON pr.subcategory_id = sub.id
             LEFT JOIN sub_subcategories subsub ON pr.sub_sub_category_id = subsub.id
             LEFT JOIN catalogs c ON c.id = COALESCE(
                 pr.catalog_id,
                 (SELECT pp.catalog_id FROM production_programs pp WHERE pp.id = pr.program_id),
                 (SELECT c1.id FROM catalogs c1 WHERE pr.sub_sub_category_id IS NOT NULL AND c1.sub_sub_category_id = pr.sub_sub_category_id ORDER BY c1.id ASC LIMIT 1),
                 (SELECT c2.id FROM catalogs c2 WHERE pr.subcategory_id IS NOT NULL AND c2.subcategory_id = pr.subcategory_id AND (pr.category_id IS NULL OR c2.category_id = pr.category_id) ORDER BY c2.id ASC LIMIT 1),
                 (SELECT c3.id FROM catalogs c3 WHERE pr.category_id IS NOT NULL AND c3.category_id = pr.category_id ORDER BY c3.id ASC LIMIT 1)
             )
             WHERE pr.id IN (SELECT DISTINCT program_roll_id FROM cutting_items WHERE cutting_id = ? AND program_roll_id IS NOT NULL)
             GROUP BY pr.id
             ORDER BY pr.id ASC",
            [$id]
        );

        // Fallback: if no rolls found through cutting_items, fetch from program
        if (empty($rolls)) {
            $rolls = DB::fetchAll(
                "SELECT pr.*, 
                        pbi.item_code, pbi.item_name, pbi.pattern, pbi.color, pbi.unit, pbi.net_weight, pbi.unit_price,
                        cat.name AS category_name, sub.name AS subcategory_name, subsub.name AS subsub_name,
                        c.id AS roll_catalog_id,
                        c.name AS roll_catalog_name,
                        c.style_code AS roll_style_code,
                        c.sizes AS roll_catalog_sizes
                 FROM program_rolls pr
                 LEFT JOIN purchase_bill_items pbi ON pr.purchase_bill_item_id = pbi.id
                 LEFT JOIN categories cat ON pr.category_id = cat.id
                 LEFT JOIN subcategories sub ON pr.subcategory_id = sub.id
                 LEFT JOIN sub_subcategories subsub ON pr.sub_sub_category_id = subsub.id
                 LEFT JOIN catalogs c ON c.id = COALESCE(
                     pr.catalog_id,
                     (SELECT pp.catalog_id FROM production_programs pp WHERE pp.id = pr.program_id),
                     (SELECT c1.id FROM catalogs c1 WHERE pr.sub_sub_category_id IS NOT NULL AND c1.sub_sub_category_id = pr.sub_sub_category_id ORDER BY c1.id ASC LIMIT 1),
                     (SELECT c2.id FROM catalogs c2 WHERE pr.subcategory_id IS NOT NULL AND c2.subcategory_id = pr.subcategory_id AND (pr.category_id IS NULL OR c2.category_id = pr.category_id) ORDER BY c2.id ASC LIMIT 1),
                     (SELECT c3.id FROM catalogs c3 WHERE pr.category_id IS NOT NULL AND c3.category_id = pr.category_id ORDER BY c3.id ASC LIMIT 1)
                 )
                 WHERE pr.program_id = ?
                 GROUP BY pr.id
                 ORDER BY pr.id ASC",
                [$batch['program_id']]
            );
        }

        // Index cutting items by roll ID
        $itemsByRoll = [];
        foreach ($items as $it) {
            $rId = (int)($it['program_roll_id'] ?? 0);
            if (!isset($itemsByRoll[$rId])) {
                $itemsByRoll[$rId] = [];
            }
            $itemsByRoll[$rId][] = $it;
        }

        $sumRollWeight = 0.00;
        $sumRollPrice = 0.00;

        foreach ($rolls as &$r) {
            $rId = (int)$r['id'];

            // Calculate weight
            $weight = (float)$r['roll_weight'];
            if ($weight <= 0) {
                $weight = (float)$r['allocated_qty'];
            }
            if ($weight <= 0) {
                $weight = (float)($r['net_weight'] ?? 0);
            }
            if ($weight <= 0 && count($rolls) === 1 && (float)($batch['total_roll_weight'] ?? 0) > 0) {
                $weight = (float)$batch['total_roll_weight'];
            }

            // Calculate price
            $price = (float)$r['roll_price'];
            if ($price <= 0) {
                $price = (float)$r['allocated_qty'] * (float)($r['unit_price'] ?? 0);
            }
            if ($price <= 0 && $weight > 0 && (float)($r['unit_price'] ?? 0) > 0) {
                $price = $weight * (float)$r['unit_price'];
            }
            if ($price <= 0 && count($rolls) === 1 && (float)($batch['total_roll_price'] ?? 0) > 0) {
                $price = (float)$batch['total_roll_price'];
            }

            $r['calculated_weight'] = $weight;
            $r['calculated_price'] = $price;
            $sumRollWeight += $weight;
            $sumRollPrice += $price;

            $hierParts = array_filter([$r['category_name'] ?? '', $r['subcategory_name'] ?? '', $r['subsub_name'] ?? '']);
            $r['hierarchy_display'] = !empty($hierParts) ? implode(' > ', $hierParts) : '';
            if (empty($r['roll_catalog_name']) && !empty($r['hierarchy_display'])) {
                $r['roll_catalog_name'] = $r['hierarchy_display'];
            }

            // Fetch variants for this roll's catalog
            $catId = $r['roll_catalog_id'] ?: ($r['catalog_id'] ?: ($batch['program_catalog_id'] ?? null));
            $variants = [];

            if ($catId) {
                $variants = DB::fetchAll(
                    "SELECT id, product_code, name, size, weight, price, mrp 
                     FROM variants 
                     WHERE catalog_id = ? AND status = 'active' 
                     ORDER BY id ASC",
                    [$catId]
                );

                if (empty($variants)) {
                    $catRecord = DB::fetch("SELECT id, style_code, name, sizes FROM catalogs WHERE id = ?", [$catId]);
                    if ($catRecord && !empty($catRecord['sizes'])) {
                        $sizesList = json_decode($catRecord['sizes'], true);
                        if (is_array($sizesList)) {
                            foreach ($sizesList as $s) {
                                $variants[] = [
                                    'id'       => null,
                                    'product_code' => ($catRecord['style_code'] ?: 'STY') . '-' . strtoupper($s),
                                    'name'     => $catRecord['name'] . ' - ' . strtoupper($s),
                                    'size'     => strtoupper($s),
                                    'price'    => 0.00,
                                ];
                            }
                        }
                    }
                }
            }

            // Map existing cutting items to variants
            $existingRollItems = $itemsByRoll[$rId] ?? ($itemsByRoll[0] ?? []);
            $matchedItemIds = [];

            foreach ($variants as &$v) {
                $v['cut_pieces'] = 0;
                foreach ($existingRollItems as $ei) {
                    if (in_array($ei['id'], $matchedItemIds)) continue;

                    // Match by variant_id
                    if (!empty($v['id']) && !empty($ei['variant_id']) && (int)$v['id'] === (int)$ei['variant_id']) {
                        $v['cut_pieces'] = (int)$ei['cut_pieces'];
                        $matchedItemIds[] = $ei['id'];
                        break;
                    }
                    // Match by product_code
                    if (!empty($v['product_code']) && !empty($ei['product_code']) && strcasecmp($v['product_code'], $ei['product_code']) === 0) {
                        $v['cut_pieces'] = (int)$ei['cut_pieces'];
                        $matchedItemIds[] = $ei['id'];
                        break;
                    }
                    // Match by size
                    if (!empty($v['size']) && !empty($ei['size']) && strcasecmp($v['size'], $ei['size']) === 0) {
                        $v['cut_pieces'] = (int)$ei['cut_pieces'];
                        $matchedItemIds[] = $ei['id'];
                        break;
                    }
                }
            }
            unset($v);

            // Any remaining cutting items that didn't match catalog variants (e.g. custom sizes)
            foreach ($existingRollItems as $ei) {
                if (!in_array($ei['id'], $matchedItemIds)) {
                    $variants[] = [
                        'id'         => !empty($ei['variant_id']) ? (int)$ei['variant_id'] : null,
                        'product_code'   => $ei['product_code'] ?? '',
                        'name'       => $ei['variant_name'] ?: (($r['fabric_name'] ?? 'Fabric') . ' - ' . ($ei['size'] ?? 'Standard')),
                        'size'       => $ei['size'] ?? 'Standard',
                        'cut_pieces' => (int)$ei['cut_pieces'],
                    ];
                    $matchedItemIds[] = $ei['id'];
                }
            }

            // Fallback default sizes if still empty
            if (empty($variants)) {
                $defaultSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'Free Size'];
                foreach ($defaultSizes as $ds) {
                    $variants[] = [
                        'id'         => null,
                        'product_code'   => ($r['roll_piece_tag'] ?: 'ROLL') . '-' . $ds,
                        'name'       => ($r['fabric_name'] ?: 'Fabric') . ' (' . ($r['color'] ?: 'Standard') . ') - ' . $ds,
                        'size'       => $ds,
                        'cut_pieces' => 0,
                    ];
                }
            }

            $r['variants'] = $variants;
        }
        unset($r);

        // If batch total weight / price was 0, sync from sum of rolls
        if ((float)($batch['total_roll_weight'] ?? 0) <= 0 && $sumRollWeight > 0) {
            $batch['total_roll_weight'] = $sumRollWeight;
        }
        if ((float)($batch['total_roll_price'] ?? 0) <= 0 && $sumRollPrice > 0) {
            $batch['total_roll_price'] = $sumRollPrice;
        }

        // Fetch active cutting masters
        $cuttingMasters = DB::fetchAll(
            "SELECT * FROM cutting_masters WHERE status = 'active'" . ($cid ? " AND concern_id = ?" : "") . " ORDER BY name ASC",
            $cid ? [$cid] : []
        );

        $this->render('concern/cutting_edit', [
            'pageTitle'      => 'Edit Cutting Batch: ' . ($batch['batch_no'] ?: $batch['log_no']),
            'currentModule'  => 'cutting_wastage',
            'batch'          => $batch,
            'items'          => $items,
            'rolls'          => $rolls,
            'cuttingMasters' => $cuttingMasters,
            'selectedCid'    => $cid,
        ]);
    }

    /**
     * Update Cutting Batch
     */
    public function updateCuttingBatch(): void {
        ModuleGuard::enforce('cutting_wastage', 'edit');
        $this->ensureCuttingSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid cutting batch ID.');
            $this->redirect('/production/cutting');
        }

        $batch = DB::fetch(
            "SELECT * FROM cutting_logs WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""),
            $cid ? [$id, $cid] : [$id]
        );
        if (!$batch) {
            $this->flash('error', 'Cutting batch not found or access denied.');
            $this->redirect('/production/cutting');
        }

        $cuttingName = trim($_POST['cutting_name'] ?? '');
        $status = $_POST['status'] ?? 'in_progress';
        if (!in_array($status, ['in_progress', 'planned', 'completed'])) {
            $status = 'in_progress';
        }
        $cuttingMasterId = !empty($_POST['cutting_master_id']) ? (int)$_POST['cutting_master_id'] : null;
        $notes = trim($_POST['notes'] ?? '');

        if (empty($cuttingName)) {
            $this->flash('error', 'Cutting Name is required.');
            $this->redirect('/production/cutting/edit?id=' . $id);
        }

        $masterName = null;
        if ($cuttingMasterId) {
            $cm = DB::fetch("SELECT name FROM cutting_masters WHERE id = ?", [$cuttingMasterId]);
            $masterName = $cm['name'] ?? null;
        }

        $totalRollWeight = (float)$batch['total_roll_weight'];
        $totalRollPrice = (float)$batch['total_roll_price'];

        if ($totalRollWeight <= 0 || $totalRollPrice <= 0) {
            $rollSums = DB::fetch(
                "SELECT COALESCE(SUM(pr.roll_weight), 0) AS sum_weight, COALESCE(SUM(pr.roll_price), 0) AS sum_price 
                 FROM program_rolls pr
                 JOIN cutting_items ci ON pr.id = ci.program_roll_id
                 WHERE ci.cutting_id = ?",
                [$id]
            );
            if ($totalRollWeight <= 0 && (float)($rollSums['sum_weight'] ?? 0) > 0) {
                $totalRollWeight = (float)$rollSums['sum_weight'];
            }
            if ($totalRollPrice <= 0 && (float)($rollSums['sum_price'] ?? 0) > 0) {
                $totalRollPrice = (float)$rollSums['sum_price'];
            }
        }

        $fabricRatePerKg = $totalRollWeight > 0 ? round($totalRollPrice / $totalRollWeight, 2) : (float)$batch['fabric_rate_per_kg'];

        $wastageWeight = round((float)($_POST['wastage_qty'] ?? 0.00), 2);
        if ($wastageWeight < 0) {
            $wastageWeight = 0.00;
        }

        if ($totalRollWeight > 0 && $wastageWeight > $totalRollWeight) {
            $this->flash('error', "Wastage Fabric Weight ({$wastageWeight} kg) cannot exceed Total Roll Weight ({$totalRollWeight} kg).");
            $this->redirect('/production/cutting/edit?id=' . $id);
        }

        $soldWastage = (float)($batch['sold_wastage_qty'] ?? 0.00);
        if ($wastageWeight < $soldWastage) {
            $this->flash('error', "Wastage weight cannot be less than already sold scrap ({$soldWastage} kg).");
            $this->redirect('/production/cutting/edit?id=' . $id);
        }

        $usedWeight = max(0.00, $totalRollWeight - $wastageWeight);
        $wastageCost = round($wastageWeight * $fabricRatePerKg, 2);
        $wastagePct = $totalRollWeight > 0 ? round(($wastageWeight / $totalRollWeight) * 100, 2) : 0.00;

        // Process updated variant cut pieces
        $submittedRolls = $_POST['rolls'] ?? [];
        $validatedItems = [];
        $totalCutPieces = 0;

        if (is_array($submittedRolls)) {
            foreach ($submittedRolls as $rollId => $rollData) {
                $varList = $rollData['variants'] ?? [];
                if (is_array($varList)) {
                    foreach ($varList as $vData) {
                        $pieces = (int)($vData['cut_pieces'] ?? 0);
                        if ($pieces > 0) {
                            $totalCutPieces += $pieces;
                            $validatedItems[] = [
                                'program_roll_id' => (int)$rollId,
                                'variant_id'      => !empty($vData['variant_id']) ? (int)$vData['variant_id'] : null,
                                'size'            => trim($vData['size'] ?? ''),
                                'product_code'        => trim($vData['product_code'] ?? ''),
                                'variant_name'    => trim($vData['variant_name'] ?? ''),
                                'cut_pieces'      => $pieces,
                            ];
                        }
                    }
                }
            }
        }

        if ($totalCutPieces <= 0) {
            $this->flash('error', 'Please enter cut pieces for at least one variant/size.');
            $this->redirect('/production/cutting/edit?id=' . $id);
        }

        $fabricCostPerPiece = $totalCutPieces > 0 ? round(($totalRollPrice - $wastageCost) / $totalCutPieces, 2) : 0.00;

        DB::beginTransaction();
        try {
            DB::query(
                "UPDATE cutting_logs SET
                    cutting_name = ?,
                    status = ?,
                    cutting_master_id = ?,
                    cutting_master_name = ?,
                    consumed_qty = ?,
                    cut_pieces = ?,
                    wastage_qty = ?,
                    wastage_cost = ?,
                    wastage_percent = ?,
                    used_weight = ?,
                    fabric_cost_per_piece = ?,
                    notes = ?,
                    updated_at = NOW()
                 WHERE id = ?",
                [
                    $cuttingName,
                    $status,
                    $cuttingMasterId,
                    $masterName,
                    $usedWeight,
                    $totalCutPieces,
                    $wastageWeight,
                    $wastageCost,
                    $wastagePct,
                    $usedWeight,
                    $fabricCostPerPiece,
                    $notes,
                    $id
                ]
            );

            // Replace cutting items
            DB::query("DELETE FROM cutting_items WHERE cutting_id = ?", [$id]);
            foreach ($validatedItems as $it) {
                DB::query(
                    "INSERT INTO cutting_items (
                        cutting_id, program_roll_id, variant_id, size, product_code, variant_name, cut_pieces, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $id,
                        $it['program_roll_id'],
                        $it['variant_id'],
                        $it['size'],
                        $it['product_code'],
                        $it['variant_name'],
                        $it['cut_pieces']
                    ]
                );
            }

            AuditLog::log('UPDATE', 'cutting_logs', $id, $batch, [
                'cutting_name' => $cuttingName,
                'status'       => $status,
                'cut_pieces'   => $totalCutPieces,
                'wastage_qty'  => $wastageWeight
            ]);

            DB::commit();
            $this->flash('success', "Cutting Batch '{$batch['batch_no']}' updated successfully.");
            $this->redirect('/production/cutting');
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to update cutting batch: ' . $e->getMessage());
            $this->redirect('/production/cutting/edit?id=' . $id);
        }
    }

    /**
     * Delete Cutting Batch
     */
    public function deleteCuttingBatch(): void {
        $this->ensureCuttingSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid cutting batch ID.');
            $this->redirect('/production/cutting');
        }

        $batch = DB::fetch("SELECT * FROM cutting_logs WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""), $cid ? [$id, $cid] : [$id]);
        if (!$batch) {
            $this->flash('error', 'Cutting batch not found or access denied.');
            $this->redirect('/production/cutting');
        }

        // Count associated wastage scrap sales
        $salesCount = (int)DB::fetchValue("SELECT COUNT(*) FROM cutting_wastage_sales WHERE cutting_id = ?", [$id]);

        DB::beginTransaction();
        try {
            // Delete associated wastage sales if any exist
            if ($salesCount > 0) {
                DB::query("DELETE FROM cutting_wastage_sales WHERE cutting_id = ?", [$id]);
            }

            // Unlink downstream stitching logs if any
            DB::query("UPDATE stitching_items SET cutting_item_id = NULL WHERE cutting_item_id IN (SELECT id FROM cutting_items WHERE cutting_id = ?)", [$id]);
            DB::query("UPDATE stitching_logs SET cutting_id = NULL WHERE cutting_id = ?", [$id]);

            // Delete cutting items and the cutting batch
            DB::query("DELETE FROM cutting_items WHERE cutting_id = ?", [$id]);
            DB::query("DELETE FROM cutting_logs WHERE id = ?", [$id]);
            AuditLog::log('DELETE', 'cutting_logs', $id, $batch, null);
            DB::commit();

            $msg = "Cutting Batch '{$batch['batch_no']}'" . ($salesCount > 0 ? " and its {$salesCount} associated wastage scrap sale(s)" : "") . " deleted successfully.";
            $this->flash('success', $msg);
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to delete cutting batch: ' . $e->getMessage());
        }

        $this->redirect('/production/cutting');
    }

    /**
     * Delete Individual Wastage Scrap Sale
     */
    public function deleteWastageSale(): void {
        $this->ensureCuttingSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid scrap sale ID.');
            $this->redirect('/production/cutting?tab=wastage');
        }

        $sale = DB::fetch("SELECT * FROM cutting_wastage_sales WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""), $cid ? [$id, $cid] : [$id]);
        if (!$sale) {
            $this->flash('error', 'Scrap sale not found or access denied.');
            $this->redirect('/production/cutting?tab=wastage');
        }

        DB::beginTransaction();
        try {
            DB::query("DELETE FROM cutting_wastage_sales WHERE id = ?", [$id]);
            // Restore sold wastage weight back to available scrap in cutting_logs
            if (!empty($sale['cutting_id']) && !empty($sale['wastage_weight'])) {
                DB::query("UPDATE cutting_logs SET sold_wastage_qty = GREATEST(0, COALESCE(sold_wastage_qty, 0) - ?) WHERE id = ?", [
                    (float)$sale['wastage_weight'],
                    (int)$sale['cutting_id']
                ]);
            }
            AuditLog::log('DELETE', 'cutting_wastage_sales', $id, $sale, null);
            DB::commit();
            $this->flash('success', "Scrap Sale '{$sale['sale_no']}' deleted successfully and scrap weight restored to batch.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to delete scrap sale: ' . $e->getMessage());
        }

        $this->redirect('/production/cutting?tab=wastage');
    }

    /**
     * Update Individual Wastage Scrap Sale
     */
    public function updateWastageSale(): void {
        $this->ensureCuttingSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid scrap sale ID.');
            $this->redirect('/production/cutting?tab=wastage');
        }

        $sale = DB::fetch("SELECT * FROM cutting_wastage_sales WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""), $cid ? [$id, $cid] : [$id]);
        if (!$sale) {
            $this->flash('error', 'Scrap sale record not found or access denied.');
            $this->redirect('/production/cutting?tab=wastage');
        }

        $cutting = DB::fetch("SELECT * FROM cutting_logs WHERE id = ?", [$sale['cutting_id']]);
        if (!$cutting) {
            $this->flash('error', 'Associated cutting batch not found.');
            $this->redirect('/production/cutting?tab=wastage');
        }

        $buyerName       = trim($_POST['buyer_name'] ?? '');
        $buyerPhone      = trim($_POST['buyer_phone'] ?? '');
        $buyerCompany    = trim($_POST['buyer_company'] ?? '');
        $wastageCategory = trim($_POST['wastage_category'] ?? $sale['wastage_category']);
        $newWeight       = (float)($_POST['wastage_weight'] ?? 0);
        $sellingRate     = (float)($_POST['selling_rate'] ?? 0);
        $totalPrice      = (float)($_POST['total_price'] ?? 0);
        $amountReceived  = (float)($_POST['amount_received'] ?? 0);
        $saleDate        = !empty($_POST['sale_date']) ? $_POST['sale_date'] : $sale['sale_date'];
        $notes           = trim($_POST['notes'] ?? '');

        if (empty($buyerName)) {
            $this->flash('error', 'Buyer / Vendor name is required.');
            $this->redirect('/production/cutting?tab=wastage');
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $buyerPhone);
        if (strlen($cleanPhone) < 10) {
            $this->flash('error', 'Buyer phone must be at least 10 digits.');
            $this->redirect('/production/cutting?tab=wastage');
        }

        if ($newWeight <= 0) {
            $this->flash('error', 'Wastage weight must be greater than zero.');
            $this->redirect('/production/cutting?tab=wastage');
        }

        // Available scrap on the batch including this sale's current weight
        $maxAllowed = (float)$cutting['wastage_qty'] - ((float)$cutting['sold_wastage_qty'] - (float)$sale['wastage_weight']);
        if ($newWeight > ($maxAllowed + 0.001)) {
            $this->flash('error', "Weight ({$newWeight} kg) exceeds maximum available scrap balance ({$maxAllowed} kg).");
            $this->redirect('/production/cutting?tab=wastage');
        }

        if ($totalPrice <= 0 && $sellingRate > 0) {
            $totalPrice = round($newWeight * $sellingRate, 2);
        }

        $paymentStatus = ($amountReceived >= $totalPrice && $totalPrice > 0) ? 'paid' : ($amountReceived > 0 ? 'partial' : 'pending');
        $weightDiff = $newWeight - (float)$sale['wastage_weight'];

        DB::beginTransaction();
        try {
            DB::query(
                "UPDATE cutting_wastage_sales SET 
                    buyer_name = ?, buyer_phone = ?, buyer_company = ?, wastage_category = ?,
                    wastage_weight = ?, selling_rate = ?, total_price = ?, amount_received = ?,
                    sale_date = ?, payment_status = ?, notes = ?
                 WHERE id = ?",
                [
                    $buyerName, $cleanPhone, $buyerCompany, $wastageCategory,
                    $newWeight, $sellingRate, $totalPrice, $amountReceived,
                    $saleDate, $paymentStatus, $notes,
                    $id
                ]
            );

            // Update cutting_logs.sold_wastage_qty with the difference
            if (abs($weightDiff) > 0.0001) {
                DB::query("UPDATE cutting_logs SET sold_wastage_qty = GREATEST(0, COALESCE(sold_wastage_qty, 0) + ?) WHERE id = ?", [
                    $weightDiff,
                    (int)$sale['cutting_id']
                ]);
            }

            AuditLog::log('UPDATE', 'cutting_wastage_sales', $id, $sale, $_POST);
            DB::commit();
            $this->flash('success', "Scrap Sale '{$sale['sale_no']}' updated successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to update scrap sale: ' . $e->getMessage());
        }

        $this->redirect('/production/cutting?tab=wastage');
    }

    /**
     * API: Get Scrap Sale Details for Quick View Modal
     */
    public function apiWastageSaleDetails(): void {
        header('Content-Type: application/json');
        $this->ensureCuttingSchema();
        $cid = Auth::concernId();
        $id = (int)($_GET['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid scrap sale ID.']);
            exit;
        }

        $sale = DB::fetch(
            "SELECT cws.*, 
                    cl.batch_no, cl.cutting_name, cl.wastage_qty, cl.sold_wastage_qty,
                    pp.program_no, pp.program_name,
                    con.name AS concern_name
             FROM cutting_wastage_sales cws
             JOIN cutting_logs cl ON cws.cutting_id = cl.id
             LEFT JOIN production_programs pp ON cl.program_id = pp.id
             LEFT JOIN concerns con ON cws.concern_id = con.id
             WHERE cws.id = ?" . ($cid ? " AND cws.concern_id = ?" : ""),
            $cid ? [$id, $cid] : [$id]
        );

        if (!$sale) {
            echo json_encode(['success' => false, 'message' => 'Scrap sale record not found.']);
            exit;
        }

        $availScrap = max(0.00, (float)$sale['wastage_qty'] - ((float)$sale['sold_wastage_qty'] - (float)$sale['wastage_weight']));

        echo json_encode([
            'success' => true,
            'sale' => $sale,
            'available_scrap' => $availScrap
        ]);
        exit;
    }

    /**
     * Wastage Selling View Alias
     */
    public function cuttingWastageIndex(): void {
        $_GET['tab'] = 'wastage';
        $this->cuttingIndex();
    }

    /**
     * Store Wastage Scrap Sale (Matching UI Image 3)
     */
    public function storeWastageSale(): void {
        $this->ensureCuttingSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();

        $cuttingId = (int)($_POST['cutting_id'] ?? 0);
        $buyerName = trim($_POST['buyer_name'] ?? '');
        $buyerPhone = trim($_POST['buyer_phone'] ?? '');
        $buyerCompany = trim($_POST['buyer_company'] ?? '');
        $wastageCategory = trim($_POST['wastage_category'] ?? 'Cutting End-Bits (Short length ends & trims)');
        $wastageWeight = (float)($_POST['wastage_weight'] ?? 0);
        $sellingRate = (float)($_POST['selling_rate'] ?? 0);
        $totalPrice = (float)($_POST['total_price'] ?? 0);
        $amountReceived = (float)($_POST['amount_received'] ?? 0);
        $saleDate = !empty($_POST['sale_date']) ? $_POST['sale_date'] : date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');

        if (!$cuttingId) {
            $this->flash('error', 'Please select a Cutting Wastage Batch.');
            $this->redirect('/production/cutting?tab=wastage');
        }

        $cutting = DB::fetch(
            "SELECT * FROM cutting_logs WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""),
            $cid ? [$cuttingId, $cid] : [$cuttingId]
        );

        if (!$cutting) {
            $this->flash('error', 'Cutting batch not found or access denied.');
            $this->redirect('/production/cutting?tab=wastage');
        }

        $batchConcernId = $cid ?: (int)$cutting['concern_id'];
        $availWastage = max(0.00, (float)$cutting['wastage_qty'] - (float)$cutting['sold_wastage_qty']);

        if (empty($buyerName)) {
            $this->flash('error', 'Scrap Buyer / Vendor Name is required.');
            $this->redirect('/production/cutting?tab=wastage');
        }

        // Phone validation (10 digits) matching Image 3: "Buyer Phone / Mobile * (10 digits)"
        $cleanPhone = preg_replace('/[^0-9]/', '', $buyerPhone);
        if (strlen($cleanPhone) < 10) {
            $this->flash('error', 'Buyer Phone / Mobile must be at least 10 digits.');
            $this->redirect('/production/cutting?tab=wastage');
        }

        if ($wastageWeight <= 0) {
            $this->flash('error', 'Wastage Weight must be greater than zero.');
            $this->redirect('/production/cutting?tab=wastage');
        }

        if ($wastageWeight > ($availWastage + 0.001)) {
            $this->flash('error', "Wastage Weight ({$wastageWeight} kg) exceeds available balance ({$availWastage} kg).");
            $this->redirect('/production/cutting?tab=wastage');
        }

        if ($totalPrice <= 0 && $sellingRate > 0) {
            $totalPrice = round($wastageWeight * $sellingRate, 2);
        }

        $saleNo = $this->generateWastageSaleNo($batchConcernId);
        $paymentStatus = ($amountReceived >= $totalPrice && $totalPrice > 0) ? 'paid' : ($amountReceived > 0 ? 'partial' : 'pending');

        DB::beginTransaction();
        try {
            DB::query(
                "INSERT INTO cutting_wastage_sales (
                    concern_id, sale_no, cutting_id, buyer_name, buyer_phone, buyer_company,
                    wastage_category, wastage_weight, selling_rate, total_price, amount_received,
                    sale_date, payment_status, notes, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $batchConcernId,
                    $saleNo,
                    $cuttingId,
                    $buyerName,
                    $cleanPhone,
                    $buyerCompany,
                    $wastageCategory,
                    $wastageWeight,
                    $sellingRate,
                    $totalPrice,
                    $amountReceived,
                    $saleDate,
                    $paymentStatus,
                    $notes,
                    Auth::id()
                ]
            );
            $saleId = (int)DB::lastInsertId();

            // Update sold_wastage_qty in cutting_logs
            DB::query(
                "UPDATE cutting_logs SET sold_wastage_qty = sold_wastage_qty + ? WHERE id = ?",
                [$wastageWeight, $cuttingId]
            );

            // Log to Finance Cash Transactions if amount received > 0
            if ($amountReceived > 0) {
                $cashAccount = DB::fetch("SELECT id FROM cash_accounts WHERE concern_id = ? LIMIT 1", [$batchConcernId]);
                if ($cashAccount) {
                    DB::query(
                        "INSERT INTO cash_transactions (
                            concern_id, account_id, txn_date, type, amount, ref_module, ref_id, description, created_by, created_at
                        ) VALUES (?, ?, ?, 'in', ?, 'scrap_sales', ?, ?, ?, NOW())",
                        [
                            $batchConcernId,
                            $cashAccount['id'],
                            $saleDate,
                            $amountReceived,
                            $saleId,
                            "Cutting Scrap Sale [{$saleNo}] from Buyer {$buyerName}",
                            Auth::id()
                        ]
                    );
                }
            }

            AuditLog::log('CREATE', 'cutting_wastage_sales', $saleId, null, [
                'sale_no'        => $saleNo,
                'cutting_id'     => $cuttingId,
                'buyer_name'     => $buyerName,
                'wastage_weight' => $wastageWeight,
                'total_price'    => $totalPrice,
            ]);

            DB::commit();
            $this->flash('success', "Scrap Sale '{$saleNo}' recorded successfully! Sold {$wastageWeight} kg for ₹" . number_format($totalPrice, 2) . ".");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to record scrap sale: ' . $e->getMessage());
        }

        $this->redirect('/production/cutting?tab=wastage');
    }

    public function updateCutting(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $cut = DB::fetch("SELECT * FROM cutting_logs WHERE id = ?", [$id]);
        if (!$cut) {
            $this->flash('error', 'Cutting log not found.');
            $this->redirect('/m/cutting_wastage');
        }

        $cutPieces  = (int)($_POST['cut_pieces'] ?? $cut['cut_pieces']);
        $wastageQty = (float)($_POST['wastage_qty'] ?? $cut['wastage_qty']);
        $notes      = trim($_POST['notes'] ?? $cut['notes']);

        try {
            DB::query(
                "UPDATE cutting_logs SET cut_pieces = ?, wastage_qty = ?, notes = ? WHERE id = ?",
                [$cutPieces, $wastageQty, $notes, $id]
            );
            $this->flash('success', "Cutting log '{$cut['log_no']}' updated successfully.");
        } catch (Exception $e) {
            $this->flash('error', 'Failed to update cutting log: ' . $e->getMessage());
        }

        $this->redirect('/m/cutting_wastage');
    }

    public function deleteCutting(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $cut = DB::fetch("SELECT * FROM cutting_logs WHERE id = ?", [$id]);
        if (!$cut) {
            $this->flash('error', 'Cutting log not found.');
            $this->redirect('/m/cutting_wastage');
        }

        DB::beginTransaction();
        try {
            // Restore consumed stock
            StockService::move(
                $cut['concern_id'],
                $cut['raw_variant_id'],
                'Main Store',
                (float)$cut['consumed_qty'],
                0.00,
                'Cutting & Wastage',
                $id,
                "Reversal of deleted cutting job [{$cut['log_no']}]",
                Auth::id()
            );

            DB::query("DELETE FROM cutting_logs WHERE id = ?", [$id]);
            DB::commit();
            $this->flash('success', "Cutting log '{$cut['log_no']}' deleted and consumed stock restored.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to delete cutting log: ' . $e->getMessage());
        }

        $this->redirect('/m/cutting_wastage');
    }

    // ========================================================
    // 4. STITCHING & WASTAGE FLOW (Matching UI Images 1, 2, 3)
    // ========================================================

    /**
     * Ensure all stitching tables and columns are up to date
     */
    public function ensureStitchingSchema(): void {
        try {
            DB::query("
            CREATE TABLE IF NOT EXISTS stitching_masters (
                id INT AUTO_INCREMENT PRIMARY KEY,
                concern_id INT NOT NULL,
                name VARCHAR(150) NOT NULL,
                phone VARCHAR(30) NULL,
                employee_code VARCHAR(50) NULL,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sm_concern (concern_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $existingCols = array_column(DB::fetchAll("DESCRIBE stitching_logs"), 'Field');
            $colsToAdd = [
                'batch_no'         => "VARCHAR(50) NULL AFTER log_no",
                'stitching_name'   => "VARCHAR(255) NULL AFTER batch_no",
                'status'           => "ENUM('in_progress', 'planned', 'completed') DEFAULT 'in_progress' AFTER stitching_name",
                'cutting_id'       => "INT NULL AFTER status",
                'tailor_id'        => "INT NULL AFTER cutting_id",
                'tailor_name'      => "VARCHAR(150) NULL AFTER tailor_id",
                'cut_pieces'       => "INT NOT NULL DEFAULT 0 AFTER input_pieces",
                'cut_weight'       => "DECIMAL(12,2) DEFAULT 0.00 AFTER stitched_pieces",
                'stitch_weight'    => "DECIMAL(12,2) DEFAULT 0.00 AFTER cut_weight",
                'wastage_weight'   => "DECIMAL(12,2) DEFAULT 0.00 AFTER wastage_pieces",
                'sold_wastage_qty' => "DECIMAL(12,2) DEFAULT 0.00 AFTER wastage_weight",
                'updated_at'       => "TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
            ];
            foreach ($colsToAdd as $col => $def) {
                if (!in_array($col, $existingCols)) {
                    DB::query("ALTER TABLE stitching_logs ADD COLUMN $col $def");
                }
            }
            DB::query("ALTER TABLE stitching_logs MODIFY COLUMN produced_variant_id INT NULL");

            DB::query("
            CREATE TABLE IF NOT EXISTS stitching_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                stitching_id INT NOT NULL,
                cutting_item_id INT NULL,
                catalog_id INT NULL,
                variant_id INT NULL,
                size VARCHAR(50) NULL,
                color VARCHAR(100) NULL,
                product_code VARCHAR(100) NULL,
                variant_name VARCHAR(255) NULL,
                cut_qty INT NOT NULL DEFAULT 0,
                stitching_qty INT NOT NULL DEFAULT 0,
                per_pc_weight DECIMAL(8,3) DEFAULT 0.000,
                total_weight DECIMAL(12,2) DEFAULT 0.00,
                base_price DECIMAL(12,2) DEFAULT 0.00,
                discount_price DECIMAL(12,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_si_stitching (stitching_id),
                KEY idx_si_variant (variant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            DB::query("
            CREATE TABLE IF NOT EXISTS stitching_wastage_sales (
                id INT AUTO_INCREMENT PRIMARY KEY,
                concern_id INT NOT NULL,
                sale_no VARCHAR(50) NOT NULL,
                stitching_id INT NOT NULL,
                buyer_name VARCHAR(150) NOT NULL,
                buyer_phone VARCHAR(20) NOT NULL,
                buyer_company VARCHAR(200) NULL,
                wastage_category VARCHAR(100) NOT NULL,
                unit_type VARCHAR(20) DEFAULT 'KG',
                quantity DECIMAL(12,2) NOT NULL,
                selling_rate DECIMAL(12,2) NOT NULL,
                total_price DECIMAL(12,2) NOT NULL,
                amount_received DECIMAL(12,2) DEFAULT 0.00,
                sale_date DATE NOT NULL,
                payment_status ENUM('paid', 'partial', 'pending') DEFAULT 'paid',
                notes TEXT NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sws_concern (concern_id),
                KEY idx_sws_stitching (stitching_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (Exception $e) {
            // Silently handled
        }
    }

    /**
     * Generate Stitching Batch Number: STCH-YYYYMMDD-XXXX
     */
    public function generateStitchingBatchNo(?int $cid): string {
        $prefix = 'STCH-' . date('Ymd') . '-';
        $row = DB::fetch(
            "SELECT batch_no FROM stitching_logs 
             WHERE batch_no LIKE ? ORDER BY id DESC LIMIT 1",
            [$prefix . '%']
        );
        $nextNum = 1;
        if ($row && !empty($row['batch_no'])) {
            $parts = explode('-', $row['batch_no']);
            $lastSeq = (int)end($parts);
            $nextNum = $lastSeq + 1;
        }
        return $prefix . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate Stitching Scrap Sale Number: SWST-YYYYMMDD-XXXX
     */
    public function generateStitchingSaleNo(?int $cid): string {
        $prefix = 'SWST-' . date('Ymd') . '-';
        $row = DB::fetch(
            "SELECT sale_no FROM stitching_wastage_sales 
             WHERE sale_no LIKE ? ORDER BY id DESC LIMIT 1",
            [$prefix . '%']
        );
        $nextNum = 1;
        if ($row && !empty($row['sale_no'])) {
            $parts = explode('-', $row['sale_no']);
            $lastSeq = (int)end($parts);
            $nextNum = $lastSeq + 1;
        }
        return $prefix . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Stitching Module Index (List of Stitching Batches + Wastage Selling Register)
     */
    public function stitchingIndex(): void {
        ModuleGuard::enforce('stitching_wastage', 'view');
        $this->ensureStitchingSchema();
        $cid = Auth::concernId();
        $tab = $_GET['tab'] ?? 'batches';

        // 1. Fetch Stitching Batches
        $sql = "SELECT sl.*, 
                       COALESCE(sl.batch_no, sl.log_no) AS display_batch_no,
                       cl.batch_no AS cutting_batch_no, cl.cutting_name,
                       pp.program_no, pp.program_name,
                       COALESCE(sl.tailor_name, sm.name) AS tailor_display_name,
                       con.name AS concern_name,
                       (SELECT COUNT(*) FROM stitching_items WHERE stitching_id = sl.id) AS variant_count,
                       (SELECT COALESCE(SUM(stitching_qty), 0) FROM stitching_items WHERE stitching_id = sl.id) AS items_stitched_qty_sum,
                       (SELECT COALESCE(SUM(quantity), 0) FROM stitching_wastage_sales WHERE stitching_id = sl.id) AS wastage_sold_sum
                FROM stitching_logs sl
                LEFT JOIN cutting_logs cl ON sl.cutting_id = cl.id
                LEFT JOIN production_programs pp ON sl.program_id = pp.id
                LEFT JOIN stitching_masters sm ON sl.tailor_id = sm.id
                LEFT JOIN concerns con ON sl.concern_id = con.id";
        $params = [];
        if ($cid) {
            $sql .= " WHERE sl.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY sl.id DESC";
        $batches = DB::fetchAll($sql, $params);

        // Attach variant items per batch for the Choose Label Counts modal
        if (!empty($batches)) {
            $batchIds = array_map(static fn($b) => (int)$b['id'], $batches);
            $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
            $allBatchItems = DB::fetchAll(
                "SELECT id, stitching_id, variant_name, product_code, size, color, stitching_qty
                 FROM stitching_items
                 WHERE stitching_id IN ({$placeholders})
                 ORDER BY catalog_id ASC, " . self::sizeOrderSql('size') . ", id ASC",
                $batchIds
            );
            $itemsByBatch = [];
            foreach ($allBatchItems as $bi) {
                $itemsByBatch[(int)$bi['stitching_id']][] = [
                    'id'           => (int)$bi['id'],
                    'name'         => $bi['variant_name'] ?: trim(($bi['size'] ?? '') . ' - ' . ($bi['color'] ?? '')),
                    'product_code' => $bi['product_code'] ?: '-',
                    'qty'          => max(1, (int)$bi['stitching_qty']),
                ];
            }
            foreach ($batches as &$b) {
                $b['label_items'] = $itemsByBatch[(int)$b['id']] ?? [];
            }
            unset($b);
        }

        // 2. Fetch Wastage Scrap Sales
        $saleSql = "SELECT sws.*, 
                           COALESCE(sl.batch_no, sl.log_no) AS stitching_batch_no,
                           sl.stitching_name,
                           cl.batch_no AS cutting_batch_no,
                           pp.program_no,
                           con.name AS concern_name
                    FROM stitching_wastage_sales sws
                    JOIN stitching_logs sl ON sws.stitching_id = sl.id
                    LEFT JOIN cutting_logs cl ON sl.cutting_id = cl.id
                    LEFT JOIN production_programs pp ON sl.program_id = pp.id
                    LEFT JOIN concerns con ON sws.concern_id = con.id";
        $saleParams = [];
        if ($cid) {
            $saleSql .= " WHERE sws.concern_id = ?";
            $saleParams[] = $cid;
        }
        $saleSql .= " ORDER BY sws.id DESC";
        $wastageSales = DB::fetchAll($saleSql, $saleParams);

        // Batches available for Scrap Sale modal: only batches that have NOT yet recorded a scrap sale and are not cancelled
        $soldStitchingIds = array_column(
            DB::fetchAll("SELECT DISTINCT stitching_id FROM stitching_wastage_sales WHERE stitching_id IS NOT NULL"),
            'stitching_id'
        );
        $soldStitchingMap = array_flip(array_map('intval', $soldStitchingIds));
        $saleEligibleBatches = array_values(array_filter($batches, function($b) use ($soldStitchingMap) {
            if (isset($b['status']) && $b['status'] === 'cancelled') {
                return false;
            }
            return !isset($soldStitchingMap[(int)$b['id']]);
        }));

        // Statistics
        $totalBatches = count($batches);
        $totalCutPcs = array_sum(array_column($batches, 'cut_pieces'));
        $totalStitchedPcs = array_sum(array_column($batches, 'stitched_pieces'));
        $totalStitchWeight = array_sum(array_column($batches, 'stitch_weight'));
        $totalScrapSold = array_sum(array_column($wastageSales, 'quantity'));
        $totalScrapRevenue = array_sum(array_column($wastageSales, 'total_price'));

        $this->render('concern/stitching_list', [
            'pageTitle'           => 'Garment Stitching & Wastage Tracking',
            'currentModule'       => 'stitching_wastage',
            'tab'                 => $tab,
            'batches'             => $batches,
            'wastageSales'        => $wastageSales,
            'saleEligibleBatches' => $saleEligibleBatches,
            'totalBatches'        => $totalBatches,
            'totalCutPcs'         => $totalCutPcs,
            'totalStitchedPcs'    => $totalStitchedPcs,
            'totalStitchWeight'   => $totalStitchWeight,
            'totalScrapSold'      => $totalScrapSold,
            'totalScrapRevenue'   => $totalScrapRevenue,
            'selectedCid'         => $cid,
            'csrfToken'           => Auth::csrfToken(),
        ]);
    }

    /**
     * Legacy / Shortcut to Stitching Index
     */
    public function stitching(): void {
        $this->stitchingIndex();
    }

    public function stitchingWastageIndex(): void {
        $_GET['tab'] = 'wastage';
        $this->stitchingIndex();
    }

    /**
     * Create Stitching Batch Form (Matching UI Image 1)
     */
    public function createStitching(): void {
        ModuleGuard::enforce('stitching_wastage', 'add');
        $this->ensureStitchingSchema();
        $cid = Auth::concernId();

        $batchNo = $this->generateStitchingBatchNo($cid);

        // Fetch cutting batches ready for stitching (excluding batches already assigned to active stitching)
        $cutSql = "SELECT cl.id, cl.concern_id, cl.batch_no, cl.cutting_name, cl.log_no,
                          cl.cut_pieces, cl.used_weight, cl.total_roll_weight, cl.wastage_qty,
                          cl.status,
                          pp.id AS program_id, pp.program_no, pp.program_name
                   FROM cutting_logs cl
                   LEFT JOIN production_programs pp ON cl.program_id = pp.id
                   WHERE (cl.status IS NULL OR cl.status != 'cancelled')
                     AND cl.id NOT IN (
                         SELECT DISTINCT cutting_id 
                         FROM stitching_logs 
                         WHERE cutting_id IS NOT NULL AND (status IS NULL OR status != 'cancelled')
                     )" .
                   ($cid ? " AND cl.concern_id = ?" : "") . "
                   ORDER BY cl.id DESC";
        $cuttingBatches = DB::fetchAll($cutSql, $cid ? [$cid] : []);

        // Fetch tailors
        $tailors = DB::fetchAll(
            "SELECT id, name, phone, employee_code FROM stitching_masters WHERE status = 'active'" . 
            ($cid ? " AND concern_id = ?" : "") . " ORDER BY name ASC",
            $cid ? [$cid] : []
        );

        $selectedCuttingId = (int)($_GET['cutting_id'] ?? 0);
        if ($selectedCuttingId) {
            $isAvailable = false;
            foreach ($cuttingBatches as $cb) {
                if ((int)$cb['id'] === $selectedCuttingId) {
                    $isAvailable = true;
                    break;
                }
            }
            if (!$isAvailable) {
                $selectedCuttingId = 0;
            }
        }

        $this->render('concern/stitching_create', [
            'pageTitle'         => 'New Stitching Batch',
            'currentModule'     => 'stitching_wastage',
            'batchNo'           => $batchNo,
            'cuttingBatches'    => $cuttingBatches,
            'tailors'           => $tailors,
            'selectedCuttingId' => $selectedCuttingId,
            'csrfToken'         => Auth::csrfToken(),
        ]);
    }

    /**
     * AJAX endpoint to fetch cutting batch details, multi-catalog identities, and cut items
     */
    public function apiCuttingDetailsForStitching(): void {
        header('Content-Type: application/json');
        try {
        $this->ensureCatalogSchema();
        $this->ensureCuttingSchema();
        $this->ensureStitchingSchema();
        $cid = Auth::concernId();
        $cuttingId = (int)($_GET['id'] ?? ($_GET['cutting_id'] ?? 0));

        if (!$cuttingId) {
            echo json_encode(['success' => false, 'message' => 'Invalid Cutting ID.']);
            exit;
        }

        $cutting = DB::fetch(
            "SELECT cl.*, pp.id AS program_id, pp.program_no, pp.program_name, pp.catalog_id AS prog_catalog_id
             FROM cutting_logs cl
             LEFT JOIN production_programs pp ON cl.program_id = pp.id
             WHERE cl.id = ? AND (cl.status IS NULL OR cl.status != 'cancelled')" . ($cid ? " AND cl.concern_id = ?" : ""),
            $cid ? [$cuttingId, $cid] : [$cuttingId]
        );

        if (!$cutting) {
            echo json_encode(['success' => false, 'message' => 'Cutting batch not found or access denied.']);
            exit;
        }

        // Fetch cut items for this cutting batch
        $rawItems = DB::fetchAll(
            "SELECT ci.*, 
                    pr.catalog_id AS roll_catalog_id,
                    pr.category_id, pr.subcategory_id, pr.sub_sub_category_id,
                    cat.name AS category_name, sub.name AS subcategory_name, subsub.name AS subsub_name,
                    COALESCE(c_roll.id, c_var.id, c_fallback.id) AS cat_id,
                    COALESCE(c_roll.name, c_var.name, c_fallback.name) AS cat_name,
                    COALESCE(c_roll.style_code, c_var.style_code, c_fallback.style_code) AS cat_code,
                    v.id AS var_id, v.name AS var_name, v.product_code AS var_product_code, v.size AS var_size,
                    v.catalog_id AS var_cat_id, v.mrp AS var_mrp, v.price AS var_sp,
                    COALESCE(pr.color, pbi.color, 'Standard') AS roll_color,
                    COALESCE(pr.fabric_name, pbi.item_name, 'Fabric') AS roll_fabric_name
             FROM cutting_items ci
             LEFT JOIN program_rolls pr ON ci.program_roll_id = pr.id
             LEFT JOIN purchase_bill_items pbi ON pr.purchase_bill_item_id = pbi.id
             LEFT JOIN categories cat ON pr.category_id = cat.id
             LEFT JOIN subcategories sub ON pr.subcategory_id = sub.id
             LEFT JOIN sub_subcategories subsub ON pr.sub_sub_category_id = subsub.id
             LEFT JOIN catalogs c_roll ON pr.catalog_id = c_roll.id
                 AND c_roll.source_module = 'production_catalog'
                 AND (c_roll.concern_id = pr.concern_id OR c_roll.concern_id = 1 OR c_roll.concern_id IS NULL)
             LEFT JOIN variants v ON ci.variant_id = v.id
             LEFT JOIN catalogs c_var ON v.catalog_id = c_var.id
                 AND c_var.source_module = 'production_catalog'
                 AND (c_var.concern_id = v.concern_id OR c_var.concern_id = 1 OR c_var.concern_id IS NULL)
             LEFT JOIN catalogs c_fallback ON c_fallback.id = COALESCE(
                 (SELECT c1.id FROM catalogs c1 WHERE c1.source_module = 'production_catalog' AND (c1.concern_id = pr.concern_id OR c1.concern_id = 1 OR c1.concern_id IS NULL) AND pr.sub_sub_category_id IS NOT NULL AND c1.sub_sub_category_id = pr.sub_sub_category_id ORDER BY c1.id ASC LIMIT 1),
                 (SELECT c2.id FROM catalogs c2 WHERE c2.source_module = 'production_catalog' AND (c2.concern_id = pr.concern_id OR c2.concern_id = 1 OR c2.concern_id IS NULL) AND pr.subcategory_id IS NOT NULL AND c2.subcategory_id = pr.subcategory_id AND (pr.category_id IS NULL OR c2.category_id = pr.category_id) ORDER BY c2.id ASC LIMIT 1),
                 (SELECT c3.id FROM catalogs c3 WHERE c3.source_module = 'production_catalog' AND (c3.concern_id = pr.concern_id OR c3.concern_id = 1 OR c3.concern_id IS NULL) AND pr.category_id IS NOT NULL AND c3.category_id = pr.category_id ORDER BY c3.id ASC LIMIT 1)
             )
             WHERE ci.cutting_id = ?
             ORDER BY ci.program_roll_id ASC, " . self::sizeOrderSql('ci.size') . ", ci.id ASC",
            [$cuttingId]
        );

        $usableWeight = (float)$cutting['used_weight'] > 0 
            ? (float)$cutting['used_weight'] 
            : max(0.00, (float)($cutting['total_roll_weight'] ?? 0) - (float)($cutting['wastage_qty'] ?? 0));

        $totalCutPieces = 0;
        foreach ($rawItems as $it) {
            $totalCutPieces += (int)$it['cut_pieces'];
        }

        $defaultPerPcWeight = $totalCutPieces > 0 ? round($usableWeight / $totalCutPieces, 3) : 0.000;

        $items = [];
        $catalogsMap = [];

        foreach ($rawItems as $it) {
            $catId = $it['cat_id'] ?: null;
            $catName = $it['cat_name'];
            $catCode = $it['cat_code'];

            if (!$catId || !$catName) {
                // Check if SKU or variant belongs to any known catalog style
                if (!empty($it['product_code'])) {
                    $skuPrefix = explode('-', $it['product_code'])[0] ?? '';
                    if ($skuPrefix) {
                        $matchedBySku = DB::fetch(
                            "SELECT id, name, style_code FROM catalogs
                             WHERE style_code LIKE ? AND source_module = 'production_catalog'
                               AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)
                             LIMIT 1",
                            [$skuPrefix . '%', (int)$cutting['concern_id']]
                        );
                        if ($matchedBySku) {
                            $catId = (int)$matchedBySku['id'];
                            $catName = $matchedBySku['name'];
                            $catCode = $matchedBySku['style_code'];
                        }
                    }
                }
            }

            if (!$catName) {
                $hierParts = array_filter([$it['category_name'] ?? '', $it['subcategory_name'] ?? '', $it['subsub_name'] ?? '']);
                if (!empty($hierParts)) {
                    $catName = implode(' > ', $hierParts);
                    $catCode = 'HIER';
                    if (!$catId) {
                        $catId = 'cat_' . ($it['category_id'] ?? 0) . '_' . ($it['subcategory_id'] ?? 0) . '_' . ($it['sub_sub_category_id'] ?? 0);
                    }
                } else {
                    $catName = ($it['roll_fabric_name'] ?: 'Fabric') . ' (' . ($it['roll_color'] ?: 'Standard') . ')';
                    $catCode = 'ROLL-' . ($it['program_roll_id'] ?: $it['id']);
                    if (!$catId) {
                        $catId = 'roll_' . ($it['program_roll_id'] ?: $it['id']);
                    }
                }
            }

            $basePrice = (float)($it['var_mrp'] ?: 0.00);
            $discPrice = (float)($it['var_sp'] ?: 0.00);

            if ($catId && !isset($catalogsMap[$catId])) {
                $catalogsMap[$catId] = [
                    'id'             => $catId,
                    'name'           => $catName,
                    'style_code'     => $catCode,
                    'base_price'     => $basePrice,
                    'discount_price' => $discPrice,
                ];
            }

            $cutQty = (int)$it['cut_pieces'];
            $stitchingQty = $cutQty;
            $perPcWeight = $defaultPerPcWeight;
            $rowTotalWeight = round($cutQty * $perPcWeight, 2);

            $items[] = [
                'cutting_item_id' => (int)$it['id'],
                'variant_id'      => $it['variant_id'] ? (int)$it['variant_id'] : null,
                'catalog_id'      => $catId,
                'catalog_name'    => $catName,
                'catalog_code'    => $catCode,
                'size'            => $it['size'] ?: ($it['var_size'] ?: 'Free Size'),
                'color'           => $it['roll_color'] ?: 'Standard',
                'product_code'        => $it['product_code'] ?: ($it['var_product_code'] ?: 'Product Code'),
                'variant_name'    => $it['variant_name'] ?: ($it['var_name'] ?: ($it['size'] . ' - ' . $it['roll_color'])),
                'cut_qty'         => $cutQty,
                'stitching_qty'   => $stitchingQty,
                'per_pc_weight'   => $perPcWeight,
                'total_weight'    => $rowTotalWeight,
                'base_price'      => $basePrice,
                'discount_price'  => $discPrice,
            ];
        }

        // Also discover any distinct catalogs attached to the program rolls
        if (!empty($cutting['program_id'])) {
            $progRollCats = DB::fetchAll(
                "SELECT DISTINCT c.id, c.name, c.style_code 
                 FROM program_rolls pr 
                 JOIN catalogs c ON pr.catalog_id = c.id
                   AND c.source_module = 'production_catalog'
                   AND (c.concern_id = pr.concern_id OR c.concern_id = 1 OR c.concern_id IS NULL)
                 WHERE pr.program_id = ? AND pr.concern_id = ?",
                [$cutting['program_id'], (int)$cutting['concern_id']]
            );
            foreach ($progRollCats as $prc) {
                if (!isset($catalogsMap[$prc['id']])) {
                    $catalogsMap[$prc['id']] = [
                        'id'             => $prc['id'],
                        'name'           => $prc['name'],
                        'style_code'     => $prc['style_code'],
                        'base_price'     => 0.00,
                        'discount_price' => 0.00,
                    ];
                }
            }
        }

        // Also if program had a primary catalog
        if (!empty($cutting['prog_catalog_id']) && !isset($catalogsMap[$cutting['prog_catalog_id']])) {
            $progCat = DB::fetch(
                "SELECT id, name, style_code FROM catalogs
                 WHERE id = ? AND source_module = 'production_catalog'
                   AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
                [$cutting['prog_catalog_id'], (int)$cutting['concern_id']]
            );
            if ($progCat) {
                $catalogsMap[$progCat['id']] = [
                    'id'             => $progCat['id'],
                    'name'           => $progCat['name'],
                    'style_code'     => $progCat['style_code'],
                    'base_price'     => 0.00,
                    'discount_price' => 0.00,
                ];
            }
        }

        echo json_encode([
            'success'              => true,
            'cutting'              => [
                'id'                  => (int)$cutting['id'],
                'batch_no'            => $cutting['batch_no'] ?: $cutting['log_no'],
                'cutting_name'        => $cutting['cutting_name'],
                'program_no'          => $cutting['program_no'],
                'program_name'        => $cutting['program_name'],
                'usable_weight'       => round($usableWeight, 2),
                'total_cut_pieces'    => $totalCutPieces,
                'total_fabric_weight' => (float)($cutting['total_roll_weight'] ?? 0),
                'wastage_qty'         => (float)($cutting['wastage_qty'] ?? 0),
            ],
            'catalogs'             => array_values($catalogsMap),
            'items'                => $items,
            'default_per_pc_weight'=> $defaultPerPcWeight,
        ]);
        exit;
        } catch (\Throwable $e) {
            error_log('Stitching cutting details failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Unable to load cutting details. Please try again.']);
            exit;
        }
    }

    /**
     * AJAX endpoint to quickly add a tailor / stitching master
     */
    public function apiQuickAddTailor(): void {
        header('Content-Type: application/json');
        $this->ensureStitchingSchema();
        $cid = Auth::concernId();
        $cuttingId = (int)($_POST['cutting_id'] ?? 0);

        if ($cuttingId > 0) {
            $cuttingSql = "SELECT concern_id FROM cutting_logs WHERE id = ?";
            $cuttingParams = [$cuttingId];
            if ($cid) {
                $cuttingSql .= " AND concern_id = ?";
                $cuttingParams[] = $cid;
            }
            $cutting = DB::fetch($cuttingSql, $cuttingParams);
            if (!$cutting) {
                echo json_encode(['success' => false, 'message' => 'Select a valid cutting batch before adding a tailor.']);
                exit;
            }
            if (!$cid) {
                $cid = (int)$cutting['concern_id'];
            }
        }

        if (!$cid || !DB::fetchValue("SELECT id FROM concerns WHERE id = ? AND status = 'active'", [$cid])) {
            echo json_encode(['success' => false, 'message' => 'Select a concern or cutting batch before adding a tailor.']);
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $code = trim($_POST['employee_code'] ?? '');

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Tailor / Master Name is required.']);
            exit;
        }

        if (!empty($phone) && !preg_match('/^[6-9]\d{9}$/', $phone)) {
            echo json_encode(['success' => false, 'message' => 'Mobile number must be 10 digits starting with 6, 7, 8, or 9.']);
            exit;
        }

        try {
            DB::query(
                "INSERT INTO stitching_masters (concern_id, name, phone, employee_code, status, created_at)
                 VALUES (?, ?, ?, ?, 'active', NOW())",
                [$cid, $name, $phone, $code]
            );
            $id = (int)DB::lastInsertId();

            echo json_encode([
                'success' => true,
                'id'      => $id,
                'name'    => $name,
                'phone'   => $phone,
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Store Stitching Batch (Matching UI Image 1)
     */
    public function storeStitchingBatch(): void {
        ModuleGuard::enforce('stitching_wastage', 'add');
        $this->ensureStitchingSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();

        $stitchingName = strtoupper(trim($_POST['stitching_name'] ?? ''));
        $batchNo       = trim($_POST['batch_no'] ?? '');
        $cuttingId     = (int)($_POST['cutting_id'] ?? 0);
        $tailorId      = !empty($_POST['tailor_id']) ? (int)$_POST['tailor_id'] : null;
        $status        = in_array($_POST['status'] ?? '', ['in_progress', 'planned', 'completed']) ? $_POST['status'] : 'in_progress';
        $notes         = trim($_POST['notes'] ?? '');

        if (empty($stitchingName)) {
            $this->flash('error', 'Stitching Name is required. Please fill the empty input field.');
            $this->redirect('/production/stitching/create');
        }

        if (!$cuttingId) {
            $this->flash('error', 'Please choose a valid Source Cutting Batch.');
            $this->redirect('/production/stitching/create');
        }

        if (empty($tailorId)) {
            $this->flash('error', 'Tailor / Stitching Master is required. Please select a Tailor.');
            $this->redirect('/production/stitching/create');
        }

        $cutting = DB::fetch(
            "SELECT cl.*, pp.id AS program_id FROM cutting_logs cl 
             LEFT JOIN production_programs pp ON cl.program_id = pp.id
             WHERE cl.id = ?" . ($cid ? " AND cl.concern_id = ?" : ""),
            $cid ? [$cuttingId, $cid] : [$cuttingId]
        );

        if (!$cutting) {
            $this->flash('error', 'Selected Cutting Batch not found or access denied.');
            $this->redirect('/production/stitching/create');
        }

        // Validate that this cutting batch hasn't already been assigned to an active stitching batch
        $alreadyStitched = DB::fetch(
            "SELECT sl.id, COALESCE(sl.batch_no, sl.log_no) AS stitching_batch_no, sl.stitching_name 
             FROM stitching_logs sl 
             WHERE sl.cutting_id = ? AND (sl.status IS NULL OR sl.status != 'cancelled') 
             LIMIT 1",
            [$cuttingId]
        );
        if ($alreadyStitched) {
            $cBatchNo = $cutting['batch_no'] ?: $cutting['log_no'];
            $this->flash('error', "Cutting Batch [{$cBatchNo}] has already been assigned to Stitching Batch '{$alreadyStitched['stitching_batch_no']}' and cannot be cut/stitched again.");
            $this->redirect('/production/stitching/create');
        }

        $batchConcernId = $cid ?: (int)$cutting['concern_id'];
        if (empty($batchNo)) {
            $batchNo = $this->generateStitchingBatchNo($batchConcernId);
        }

        $tailorName = null;
        if ($tailorId) {
            $tm = DB::fetch("SELECT name FROM stitching_masters WHERE id = ?", [$tailorId]);
            $tailorName = $tm['name'] ?? null;
        }

        $usableCutWeight = (float)$cutting['used_weight'] > 0 
            ? (float)$cutting['used_weight'] 
            : max(0.00, (float)($cutting['total_roll_weight'] ?? 0) - (float)($cutting['wastage_qty'] ?? 0));

        // Process submitted items breakdown
        $submittedItems = $_POST['items'] ?? [];
        if (empty($submittedItems) || !is_array($submittedItems)) {
            $this->flash('error', 'Please select a cutting batch with valid items.');
            $this->redirect('/production/stitching/create');
        }

        // Validate that no item input fields are empty
        foreach ($submittedItems as $it) {
            $stitchQStr = trim((string)($it['stitching_qty'] ?? ''));
            $perWtStr   = trim((string)($it['per_pc_weight'] ?? ''));
            $totWtStr   = trim((string)($it['total_weight'] ?? ''));
            $basePrStr  = trim((string)($it['base_price'] ?? ''));
            $discPrStr  = trim((string)($it['discount_price'] ?? ''));

            if ($stitchQStr === '' || !is_numeric($stitchQStr) || (int)$stitchQStr < 0 ||
                $perWtStr === '' || !is_numeric($perWtStr) || (float)$perWtStr <= 0 ||
                $totWtStr === '' || !is_numeric($totWtStr) || (float)$totWtStr <= 0 ||
                $basePrStr === '' || !is_numeric($basePrStr) || (float)$basePrStr < 0 ||
                $discPrStr === '' || !is_numeric($discPrStr) || (float)$discPrStr < 0) {
                $this->flash('error', 'Please fill all empty input fields (Quantity, Weights, and Prices) for all variant items.');
                $this->redirect('/production/stitching/create');
            }
        }

        $validatedItems = [];
        $totalCutQty = 0;
        $totalStitchedQty = 0;
        $totalStitchWeight = 0.00;

        if (is_array($submittedItems)) {
            foreach ($submittedItems as $it) {
                $cutQ = (int)($it['cut_qty'] ?? 0);
                $stitchQ = (int)($it['stitching_qty'] ?? 0);
                $perPcWt = (float)($it['per_pc_weight'] ?? 0.000);
                $totalRowWt = (float)($it['total_weight'] ?? round($stitchQ * $perPcWt, 2));
                $basePrice = (float)($it['base_price'] ?? 0.00);
                $discPrice = (float)($it['discount_price'] ?? 0.00);

                if ($stitchQ > 0 || $cutQ > 0) {
                    $totalCutQty += $cutQ;
                    $totalStitchedQty += $stitchQ;
                    $totalStitchWeight += $totalRowWt;

                    $validatedItems[] = [
                        'cutting_item_id' => !empty($it['cutting_item_id']) ? (int)$it['cutting_item_id'] : null,
                        'catalog_id'      => !empty($it['catalog_id']) ? (int)$it['catalog_id'] : null,
                        'variant_id'      => !empty($it['variant_id']) ? (int)$it['variant_id'] : null,
                        'size'            => trim($it['size'] ?? ''),
                        'color'           => trim($it['color'] ?? ''),
                        'product_code'        => trim($it['product_code'] ?? ''),
                        'variant_name'    => trim($it['variant_name'] ?? ''),
                        'cut_qty'         => $cutQ,
                        'stitching_qty'   => $stitchQ,
                        'per_pc_weight'   => $perPcWt,
                        'total_weight'    => $totalRowWt,
                        'base_price'      => $basePrice,
                        'discount_price'  => $discPrice,
                    ];
                }
            }
        }

        if ($totalStitchedQty <= 0) {
            $this->flash('error', 'Please enter stitching quantity for at least one variant.');
            $this->redirect('/production/stitching/create');
        }

        $wastageWeight = max(0.00, $usableCutWeight - $totalStitchWeight);
        $wastagePieces = max(0, $totalCutQty - $totalStitchedQty);

        DB::beginTransaction();
        try {
            DB::query(
                "INSERT INTO stitching_logs (
                    concern_id, log_no, batch_no, stitching_name, status,
                    cutting_id, program_id, tailor_id, tailor_name,
                    input_pieces, cut_pieces, stitched_pieces, wastage_pieces,
                    cut_weight, stitch_weight, wastage_weight, notes,
                    created_by, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, NOW()
                )",
                [
                    $batchConcernId,
                    $batchNo,
                    $batchNo,
                    $stitchingName,
                    $status,
                    $cuttingId,
                    $cutting['program_id'],
                    $tailorId,
                    $tailorName,
                    $totalCutQty,
                    $totalCutQty,
                    $totalStitchedQty,
                    $wastagePieces,
                    $usableCutWeight,
                    $totalStitchWeight,
                    $wastageWeight,
                    $notes,
                    Auth::id()
                ]
            );
            $stitchingId = (int)DB::lastInsertId();

            // Insert stitching items & update finished stock / prices
            foreach ($validatedItems as $item) {
                DB::query(
                    "INSERT INTO stitching_items (
                        stitching_id, cutting_item_id, catalog_id, variant_id,
                        size, color, product_code, variant_name,
                        cut_qty, stitching_qty, per_pc_weight, total_weight,
                        base_price, discount_price, created_at
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, NOW()
                    )",
                    [
                        $stitchingId,
                        $item['cutting_item_id'],
                        $item['catalog_id'],
                        $item['variant_id'],
                        $item['size'],
                        $item['color'],
                        $item['product_code'],
                        $item['variant_name'],
                        $item['cut_qty'],
                        $item['stitching_qty'],
                        $item['per_pc_weight'],
                        $item['total_weight'],
                        $item['base_price'],
                        $item['discount_price']
                    ]
                );

                // Update prices in variants table if variant exists and prices provided
                if ($item['variant_id']) {
                    if ($item['base_price'] > 0 || $item['discount_price'] > 0) {
                        DB::query(
                            "UPDATE variants 
                             SET mrp = CASE WHEN ? > 0 THEN ? ELSE mrp END,
                                 price = CASE WHEN ? > 0 THEN ? ELSE price END
                             WHERE id = ?",
                            [
                                $item['base_price'], $item['base_price'],
                                $item['discount_price'], $item['discount_price'],
                                $item['variant_id']
                            ]
                        );
                    }

                    // Live stock is credited only after QC passes.
                    // Stitching records remain pending until QC completion.
                }
            }

            // Mark cutting batch as completed
            DB::query("UPDATE cutting_logs SET status = 'completed' WHERE id = ?", [$cuttingId]);

            AuditLog::log('CREATE', 'stitching_logs', $stitchingId, null, [
                'batch_no'       => $batchNo,
                'stitching_name' => $stitchingName,
                'cutting_id'     => $cuttingId,
                'stitched_pieces'=> $totalStitchedQty,
                'stitch_weight'  => $totalStitchWeight,
            ]);

            DB::commit();
            $this->flash('success', "Stitching Batch '{$batchNo}' ({$stitchingName}) created successfully! {$totalStitchedQty} finished units recorded.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to save Stitching Batch: ' . $e->getMessage());
        }

        $this->redirect('/production/stitching');
    }

    /**
     * Legacy storeStitching wrapper
     */
    public function storeStitching(): void {
        $this->storeStitchingBatch();
    }

    /**
     * Edit Stitching Batch Form (Matching UI Image 3)
     */
    public function editStitchingBatch(): void {
        $this->ensureStitchingSchema();
        $cid = Auth::concernId();
        $id = (int)($_GET['id'] ?? 0);

        $batch = DB::fetch(
            "SELECT sl.*, 
                    COALESCE(sl.batch_no, sl.log_no) AS display_batch_no,
                    cl.batch_no AS cutting_batch_no, cl.cutting_name, cl.used_weight AS cutting_usable_weight,
                    cl.total_roll_weight, cl.wastage_qty AS cutting_wastage,
                    pp.id AS program_id, pp.program_no, pp.program_name
             FROM stitching_logs sl
             LEFT JOIN cutting_logs cl ON sl.cutting_id = cl.id
             LEFT JOIN production_programs pp ON sl.program_id = pp.id
             WHERE sl.id = ?" . ($cid ? " AND sl.concern_id = ?" : ""),
            $cid ? [$id, $cid] : [$id]
        );

        if (!$batch) {
            $this->flash('error', 'Stitching Batch not found or access denied.');
            $this->redirect('/production/stitching');
        }

        $items = DB::fetchAll(
            "SELECT si.*, 
                    c.name AS catalog_name, c.style_code AS catalog_code
             FROM stitching_items si
             LEFT JOIN catalogs c ON si.catalog_id = c.id
             WHERE si.stitching_id = ?
             ORDER BY si.catalog_id ASC, " . self::sizeOrderSql('si.size') . ", si.id ASC",
            [$id]
        );

        // Fetch tailors
        $tailors = DB::fetchAll(
            "SELECT id, name, phone, employee_code FROM stitching_masters WHERE status = 'active'" . 
            ($cid ? " AND concern_id = ?" : "") . " ORDER BY name ASC",
            $cid ? [$cid] : []
        );

        // Distinct catalogs for bulk pricing toolbar
        $catalogsMap = [];
        foreach ($items as $it) {
            if ($it['catalog_id'] && !isset($catalogsMap[$it['catalog_id']])) {
                $catalogsMap[$it['catalog_id']] = [
                    'id'   => $it['catalog_id'],
                    'name' => $it['catalog_name'] ?: 'Catalog Item',
                    'code' => $it['catalog_code'] ?: 'CAT',
                ];
            }
        }

        $this->render('concern/stitching_edit', [
            'pageTitle'     => 'Edit Stitching Batch: ' . ($batch['display_batch_no']),
            'currentModule' => 'stitching_wastage',
            'batch'         => $batch,
            'items'         => $items,
            'catalogs'      => array_values($catalogsMap),
            'tailors'       => $tailors,
            'csrfToken'     => Auth::csrfToken(),
        ]);
    }

    /**
     * Update Stitching Batch (Matching UI Image 3)
     */
    public function updateStitchingBatch(): void {
        $this->ensureStitchingSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);

        $batch = DB::fetch(
            "SELECT * FROM stitching_logs WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""),
            $cid ? [$id, $cid] : [$id]
        );

        if (!$batch) {
            $this->flash('error', 'Stitching Batch not found or access denied.');
            $this->redirect('/production/stitching');
        }

        $stitchingName = strtoupper(trim($_POST['stitching_name'] ?? ''));
        $tailorId      = !empty($_POST['tailor_id']) ? (int)$_POST['tailor_id'] : null;
        $status        = in_array($_POST['status'] ?? '', ['in_progress', 'planned', 'completed']) ? $_POST['status'] : $batch['status'];
        $notes         = trim($_POST['notes'] ?? '');

        if (empty($stitchingName)) {
            $this->flash('error', 'Stitching Name is required. Please fill the empty input field.');
            $this->redirect('/production/stitching/edit?id=' . $id);
        }

        if (empty($tailorId)) {
            $this->flash('error', 'Tailor / Stitching Master is required. Please select a Tailor.');
            $this->redirect('/production/stitching/edit?id=' . $id);
        }

        $submittedItems = $_POST['items'] ?? [];
        if (empty($submittedItems) || !is_array($submittedItems)) {
            $this->flash('error', 'No variant items found in batch.');
            $this->redirect('/production/stitching/edit?id=' . $id);
        }

        // Validate that no item input fields are empty
        foreach ($submittedItems as $itemId => $it) {
            $stitchQStr = trim((string)($it['stitching_qty'] ?? ''));
            $perWtStr   = trim((string)($it['per_pc_weight'] ?? ''));
            $totWtStr   = trim((string)($it['total_weight'] ?? ''));
            $basePrStr  = trim((string)($it['base_price'] ?? ''));
            $discPrStr  = trim((string)($it['discount_price'] ?? ''));

            if ($stitchQStr === '' || !is_numeric($stitchQStr) || (int)$stitchQStr < 0 ||
                $perWtStr === '' || !is_numeric($perWtStr) || (float)$perWtStr <= 0 ||
                $totWtStr === '' || !is_numeric($totWtStr) || (float)$totWtStr <= 0 ||
                $basePrStr === '' || !is_numeric($basePrStr) || (float)$basePrStr < 0 ||
                $discPrStr === '' || !is_numeric($discPrStr) || (float)$discPrStr < 0) {
                $this->flash('error', 'Please fill all empty input fields (Quantity, Weights, and Prices) for all variant items.');
                $this->redirect('/production/stitching/edit?id=' . $id);
            }
        }

        $tailorName = null;
        if ($tailorId) {
            $tm = DB::fetch("SELECT name FROM stitching_masters WHERE id = ?", [$tailorId]);
            $tailorName = $tm['name'] ?? null;
        }

        $submittedItems = $_POST['items'] ?? [];
        $totalStitchedQty = 0;
        $totalStitchWeight = 0.00;
        $totalCutQty = 0;

        DB::beginTransaction();
        try {
            if (is_array($submittedItems)) {
                foreach ($submittedItems as $itemId => $it) {
                    $stitchQ    = (int)($it['stitching_qty'] ?? 0);
                    $cutQ       = (int)($it['cut_qty'] ?? 0);
                    $perPcWt    = (float)($it['per_pc_weight'] ?? 0.000);
                    $totalRowWt = (float)($it['total_weight'] ?? round($stitchQ * $perPcWt, 2));
                    $basePrice  = (float)($it['base_price'] ?? 0.00);
                    $discPrice  = (float)($it['discount_price'] ?? 0.00);

                    $totalCutQty += $cutQ;
                    $totalStitchedQty += $stitchQ;
                    $totalStitchWeight += $totalRowWt;

                    DB::query(
                        "UPDATE stitching_items 
                         SET stitching_qty = ?, per_pc_weight = ?, total_weight = ?,
                             base_price = ?, discount_price = ?
                         WHERE id = ? AND stitching_id = ?",
                        [$stitchQ, $perPcWt, $totalRowWt, $basePrice, $discPrice, $itemId, $id]
                    );

                    // Update variant prices if variant_id present
                    $existingItem = DB::fetch("SELECT variant_id FROM stitching_items WHERE id = ?", [$itemId]);
                    if ($existingItem && $existingItem['variant_id']) {
                        if ($basePrice > 0 || $discPrice > 0) {
                            DB::query(
                                "UPDATE variants 
                                 SET mrp = CASE WHEN ? > 0 THEN ? ELSE mrp END,
                                     price = CASE WHEN ? > 0 THEN ? ELSE price END
                                 WHERE id = ?",
                                [$basePrice, $basePrice, $discPrice, $discPrice, $existingItem['variant_id']]
                            );
                        }
                    }
                }
            }

            $cutWeight = (float)$batch['cut_weight'];
            $wastageWeight = max(0.00, $cutWeight - $totalStitchWeight);
            $wastagePieces = max(0, $totalCutQty - $totalStitchedQty);

            DB::query(
                "UPDATE stitching_logs 
                 SET stitching_name = ?, tailor_id = ?, tailor_name = ?, status = ?,
                     stitched_pieces = ?, stitch_weight = ?, wastage_pieces = ?,
                     wastage_weight = ?, notes = ?, updated_at = NOW()
                 WHERE id = ?",
                [
                    $stitchingName, $tailorId, $tailorName, $status,
                    $totalStitchedQty, $totalStitchWeight, $wastagePieces,
                    $wastageWeight, $notes, $id
                ]
            );

            DB::commit();
            $this->flash('success', "Stitching Batch '{$batch['batch_no']}' updated successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to update Stitching Batch: ' . $e->getMessage());
        }

        $this->redirect('/production/stitching');
    }

    /**
     * Legacy updateStitching wrapper
     */
    public function updateStitching(): void {
        $this->updateStitchingBatch();
    }

    /**
     * View Stitching Batch Details
     */
    public function viewStitchingBatch(): void {
        $this->ensureStitchingSchema();
        $cid = Auth::concernId();
        $id = (int)($_GET['id'] ?? 0);

        $batch = DB::fetch(
            "SELECT sl.*, 
                    COALESCE(sl.batch_no, sl.log_no) AS display_batch_no,
                    cl.batch_no AS cutting_batch_no, cl.cutting_name, cl.used_weight AS cutting_usable_weight,
                    cl.total_roll_weight, cl.wastage_qty AS cutting_wastage,
                    pp.id AS program_id, pp.program_no, pp.program_name,
                    COALESCE(sl.tailor_name, sm.name) AS tailor_display_name,
                    con.name AS concern_name,
                    u.name AS created_by_name
             FROM stitching_logs sl
             LEFT JOIN cutting_logs cl ON sl.cutting_id = cl.id
             LEFT JOIN production_programs pp ON sl.program_id = pp.id
             LEFT JOIN stitching_masters sm ON sl.tailor_id = sm.id
             LEFT JOIN concerns con ON sl.concern_id = con.id
             LEFT JOIN users u ON sl.created_by = u.id
             WHERE sl.id = ?" . ($cid ? " AND sl.concern_id = ?" : ""),
            $cid ? [$id, $cid] : [$id]
        );

        if (!$batch) {
            $this->flash('error', 'Stitching Batch not found or access denied.');
            $this->redirect('/production/stitching');
        }

        $items = DB::fetchAll(
            "SELECT si.*, 
                    c.name AS catalog_name, c.style_code AS catalog_code
             FROM stitching_items si
             LEFT JOIN catalogs c ON si.catalog_id = c.id
             WHERE si.stitching_id = ?
             ORDER BY si.catalog_id ASC, " . self::sizeOrderSql('si.size') . ", si.id ASC",
            [$id]
        );

        $wastageSales = DB::fetchAll(
            "SELECT sws.*, u.name AS recorded_by_name
             FROM stitching_wastage_sales sws
             LEFT JOIN users u ON sws.created_by = u.id
             WHERE sws.stitching_id = ?
             ORDER BY sws.id DESC",
            [$id]
        );

        $this->render('concern/stitching_view', [
            'pageTitle'     => 'Stitching Batch: ' . ($batch['display_batch_no']),
            'currentModule' => 'stitching_wastage',
            'batch'         => $batch,
            'items'         => $items,
            'wastageSales'  => $wastageSales,
            'csrfToken'     => Auth::csrfToken(),
        ]);
    }

    /**
     * Delete Stitching Batch
     */
    public function deleteStitchingBatch(): void {
        $this->ensureStitchingSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);

        $batch = DB::fetch(
            "SELECT * FROM stitching_logs WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""),
            $cid ? [$id, $cid] : [$id]
        );

        if (!$batch) {
            $this->flash('error', 'Stitching Batch not found.');
            $this->redirect('/production/stitching');
        }

        DB::beginTransaction();
        try {
            // Revert stock inward transactions for this batch
            $items = DB::fetchAll("SELECT * FROM stitching_items WHERE stitching_id = ?", [$id]);
            foreach ($items as $it) {
                if ($it['variant_id'] && $it['stitching_qty'] > 0) {
                    StockService::move(
                        $batch['concern_id'],
                        $it['variant_id'],
                        'Main Store',
                        -$it['stitching_qty'],
                        0.00,
                        'Stitching Reversal',
                        $id,
                        "Reversal of deleted Stitching Batch [{$batch['batch_no']}]",
                        Auth::id()
                    );
                }
            }

            // Delete associated items, wastage sales, and logs
            DB::query("DELETE FROM stitching_items WHERE stitching_id = ?", [$id]);
            DB::query("DELETE FROM stitching_wastage_sales WHERE stitching_id = ?", [$id]);
            DB::query("DELETE FROM stitching_logs WHERE id = ?", [$id]);

            DB::commit();
            $this->flash('success', "Stitching Batch '{$batch['batch_no']}' deleted and warehouse stocks adjusted.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to delete Stitching Batch: ' . $e->getMessage());
        }

        $this->redirect('/production/stitching');
    }

    /**
     * Legacy deleteStitching wrapper
     */
    public function deleteStitching(): void {
        $this->deleteStitchingBatch();
    }

    /**
     * Store Stitching Wastage Scrap Sale (Matching UI Image 2)
     */
    public function storeStitchingWastageSale(): void {
        $this->ensureStitchingSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();

        $stitchingId     = (int)($_POST['stitching_id'] ?? 0);
        $buyerName       = trim($_POST['buyer_name'] ?? '');
        $buyerPhone      = trim($_POST['buyer_phone'] ?? '');
        $buyerCompany    = trim($_POST['buyer_company'] ?? '');
        $wastageCategory = trim($_POST['wastage_category'] ?? 'Thread & Yarn Waste');
        $unitType        = in_array($_POST['unit_type'] ?? '', ['KG', 'Pcs']) ? $_POST['unit_type'] : 'KG';
        $quantity        = (float)($_POST['quantity'] ?? 0);
        $sellingRate     = (float)($_POST['selling_rate'] ?? 0);
        $totalPrice      = (float)($_POST['total_price'] ?? 0);
        $amountReceived  = (float)($_POST['amount_received'] ?? 0);
        $saleDate        = !empty($_POST['sale_date']) ? $_POST['sale_date'] : date('Y-m-d');
        $notes           = trim($_POST['notes'] ?? '');

        if (!$stitchingId) {
            $this->flash('error', 'Please select a valid Stitching Batch.');
            $this->redirect('/production/stitching?tab=wastage');
        }

        $batch = DB::fetch(
            "SELECT * FROM stitching_logs WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""),
            $cid ? [$stitchingId, $cid] : [$stitchingId]
        );

        if (!$batch) {
            $this->flash('error', 'Stitching Batch not found or access denied.');
            $this->redirect('/production/stitching?tab=wastage');
        }

        if (isset($batch['status']) && $batch['status'] === 'cancelled') {
            $this->flash('error', 'Cannot record scrap sale for a cancelled stitching batch.');
            $this->redirect('/production/stitching?tab=wastage');
        }

        // Validate that this stitching batch has not already completed wastage scrap selling
        $alreadySold = DB::fetch(
            "SELECT sws.id, sws.sale_no, COALESCE(sl.batch_no, sl.log_no) AS batch_no 
             FROM stitching_wastage_sales sws
             LEFT JOIN stitching_logs sl ON sws.stitching_id = sl.id
             WHERE sws.stitching_id = ? LIMIT 1",
            [$stitchingId]
        );
        if ($alreadySold) {
            $bNo = $alreadySold['batch_no'] ?: 'Selected Batch';
            $this->flash('error', "Stitching Batch [{$bNo}] has already completed wastage scrap selling (Sale #{$alreadySold['sale_no']}). Completed batches cannot be selected again.");
            $this->redirect('/production/stitching?tab=wastage');
        }

        if (empty($buyerName)) {
            $this->flash('error', 'Buyer Name is required.');
            $this->redirect('/production/stitching?tab=wastage');
        }

        // Validate 10-digit phone strictly
        $cleanPhone = preg_replace('/[^0-9]/', '', $buyerPhone);
        if (strlen($cleanPhone) !== 10) {
            $this->flash('error', 'Buyer Phone must be exactly 10 digits (e.g. 9876543210).');
            $this->redirect('/production/stitching?tab=wastage');
        }

        if ($quantity <= 0 || $sellingRate <= 0) {
            $this->flash('error', 'Scrap Quantity and Selling Rate must be greater than zero.');
            $this->redirect('/production/stitching?tab=wastage');
        }

        if ($totalPrice <= 0) {
            $totalPrice = round($quantity * $sellingRate, 2);
        }

        $batchConcernId = $cid ?: (int)$batch['concern_id'];
        $saleNo = $this->generateStitchingSaleNo($batchConcernId);
        $paymentStatus = $amountReceived >= $totalPrice ? 'paid' : ($amountReceived > 0 ? 'partial' : 'pending');

        DB::beginTransaction();
        try {
            DB::query(
                "INSERT INTO stitching_wastage_sales (
                    concern_id, sale_no, stitching_id, buyer_name, buyer_phone,
                    buyer_company, wastage_category, unit_type, quantity,
                    selling_rate, total_price, amount_received, sale_date,
                    payment_status, notes, created_by, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, NOW()
                )",
                [
                    $batchConcernId,
                    $saleNo,
                    $stitchingId,
                    $buyerName,
                    $cleanPhone,
                    $buyerCompany,
                    $wastageCategory,
                    $unitType,
                    $quantity,
                    $sellingRate,
                    $totalPrice,
                    $amountReceived,
                    $saleDate,
                    $paymentStatus,
                    $notes,
                    Auth::id()
                ]
            );
            $saleId = (int)DB::lastInsertId();

            // Update sold wastage quantity on stitching_logs
            DB::query(
                "UPDATE stitching_logs SET sold_wastage_qty = sold_wastage_qty + ? WHERE id = ?",
                [$quantity, $stitchingId]
            );

            // Inward cash transaction if cash account exists
            if ($amountReceived > 0) {
                $cashAccount = DB::fetch(
                    "SELECT id FROM cash_accounts WHERE concern_id = ? LIMIT 1",
                    [$batchConcernId]
                );
                if ($cashAccount) {
                    DB::query(
                        "INSERT INTO cash_transactions (
                            concern_id, account_id, txn_date, type,
                            amount, ref_module, ref_id, description,
                            created_by, created_at
                        ) VALUES (
                            ?, ?, ?, 'credit',
                            ?, 'scrap_sales', ?, ?,
                            ?, NOW()
                        )",
                        [
                            $batchConcernId,
                            $cashAccount['id'],
                            $saleDate,
                            $amountReceived,
                            $saleId,
                            "Stitching Scrap Sale [{$saleNo}] from Buyer {$buyerName}",
                            Auth::id()
                        ]
                    );
                }
            }

            AuditLog::log('CREATE', 'stitching_wastage_sales', $saleId, null, [
                'sale_no'      => $saleNo,
                'stitching_id' => $stitchingId,
                'buyer_name'   => $buyerName,
                'quantity'     => $quantity,
                'unit_type'    => $unitType,
                'total_price'  => $totalPrice,
            ]);

            DB::commit();
            $this->flash('success', "Scrap Sale '{$saleNo}' recorded successfully! Sold {$quantity} {$unitType} for ₹" . number_format($totalPrice, 2) . ".");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to record scrap sale: ' . $e->getMessage());
        }

        $this->redirect('/production/stitching?tab=wastage');
    }

    /**
     * API: Get Stitching Scrap Sale Details for Quick View Modal
     */
    public function apiStitchingWastageDetails(): void {
        header('Content-Type: application/json');
        $this->ensureStitchingSchema();
        $cid = Auth::concernId();
        $id = (int)($_GET['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid scrap sale ID.']);
            exit;
        }

        $sale = DB::fetch(
            "SELECT sws.*, 
                    COALESCE(sl.batch_no, sl.log_no) AS stitching_batch_no,
                    sl.stitching_name, sl.cut_pieces, sl.stitched_pieces, sl.stitch_weight, sl.cut_weight, sl.wastage_weight, sl.status AS batch_status,
                    cl.batch_no AS cutting_batch_no, cl.cutting_name,
                    pp.program_no, pp.program_name,
                    con.name AS concern_name,
                    u.name AS recorded_by_name
             FROM stitching_wastage_sales sws
             JOIN stitching_logs sl ON sws.stitching_id = sl.id
             LEFT JOIN cutting_logs cl ON sl.cutting_id = cl.id
             LEFT JOIN production_programs pp ON sl.program_id = pp.id
             LEFT JOIN concerns con ON sws.concern_id = con.id
             LEFT JOIN users u ON sws.created_by = u.id
             WHERE sws.id = ?" . ($cid ? " AND sws.concern_id = ?" : ""),
            $cid ? [$id, $cid] : [$id]
        );

        if (!$sale) {
            echo json_encode(['success' => false, 'message' => 'Scrap sale record not found or access denied.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'sale'    => $sale,
        ]);
        exit;
    }

    /**
     * Update Individual Stitching Wastage Scrap Sale
     */
    public function updateStitchingWastageSale(): void {
        $this->ensureStitchingSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid scrap sale ID.');
            $this->redirect('/production/stitching?tab=wastage');
        }

        $sale = DB::fetch(
            "SELECT * FROM stitching_wastage_sales WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""),
            $cid ? [$id, $cid] : [$id]
        );

        if (!$sale) {
            $this->flash('error', 'Scrap sale record not found or access denied.');
            $this->redirect('/production/stitching?tab=wastage');
        }

        $buyerName       = trim($_POST['buyer_name'] ?? '');
        $buyerPhone      = trim($_POST['buyer_phone'] ?? '');
        $buyerCompany    = trim($_POST['buyer_company'] ?? '');
        $wastageCategory = trim($_POST['wastage_category'] ?? $sale['wastage_category']);
        $unitType        = in_array($_POST['unit_type'] ?? '', ['KG', 'Pcs']) ? $_POST['unit_type'] : $sale['unit_type'];
        $quantity        = (float)($_POST['quantity'] ?? 0);
        $sellingRate     = (float)($_POST['selling_rate'] ?? 0);
        $totalPrice      = (float)($_POST['total_price'] ?? 0);
        $amountReceived  = (float)($_POST['amount_received'] ?? 0);
        $saleDate        = !empty($_POST['sale_date']) ? $_POST['sale_date'] : $sale['sale_date'];
        $notes           = trim($_POST['notes'] ?? '');

        if (empty($buyerName)) {
            $this->flash('error', 'Buyer Name is required.');
            $this->redirect('/production/stitching?tab=wastage');
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $buyerPhone);
        if (strlen($cleanPhone) !== 10) {
            $this->flash('error', 'Buyer Phone must be exactly 10 digits (e.g. 9876543210).');
            $this->redirect('/production/stitching?tab=wastage');
        }

        if ($quantity <= 0 || $sellingRate <= 0) {
            $this->flash('error', 'Scrap Quantity and Selling Rate must be greater than zero.');
            $this->redirect('/production/stitching?tab=wastage');
        }

        if ($totalPrice <= 0) {
            $totalPrice = round($quantity * $sellingRate, 2);
        }

        $paymentStatus = $amountReceived >= $totalPrice ? 'paid' : ($amountReceived > 0 ? 'partial' : 'pending');
        $qtyDiff = $quantity - (float)$sale['quantity'];

        DB::beginTransaction();
        try {
            DB::query(
                "UPDATE stitching_wastage_sales SET 
                    buyer_name = ?, buyer_phone = ?, buyer_company = ?, wastage_category = ?,
                    unit_type = ?, quantity = ?, selling_rate = ?, total_price = ?,
                    amount_received = ?, sale_date = ?, payment_status = ?, notes = ?
                 WHERE id = ?",
                [
                    $buyerName, $cleanPhone, $buyerCompany, $wastageCategory,
                    $unitType, $quantity, $sellingRate, $totalPrice,
                    $amountReceived, $saleDate, $paymentStatus, $notes,
                    $id
                ]
            );

            // Adjust sold_wastage_qty on stitching_logs by the delta difference
            if (abs($qtyDiff) > 0.0001) {
                DB::query(
                    "UPDATE stitching_logs SET sold_wastage_qty = GREATEST(0, COALESCE(sold_wastage_qty, 0) + ?) WHERE id = ?",
                    [$qtyDiff, $sale['stitching_id']]
                );
            }

            // Update associated cash transaction if present
            $cashTxn = DB::fetch(
                "SELECT id FROM cash_transactions WHERE ref_module = 'scrap_sales' AND ref_id = ? LIMIT 1",
                [$id]
            );
            if ($cashTxn) {
                DB::query(
                    "UPDATE cash_transactions SET amount = ?, txn_date = ? WHERE id = ?",
                    [$amountReceived, $saleDate, $cashTxn['id']]
                );
            } elseif ($amountReceived > 0) {
                $cashAccount = DB::fetch("SELECT id FROM cash_accounts WHERE concern_id = ? LIMIT 1", [$sale['concern_id']]);
                if ($cashAccount) {
                    DB::query(
                        "INSERT INTO cash_transactions (
                            concern_id, account_id, txn_date, type, amount, ref_module, ref_id, description, created_by, created_at
                        ) VALUES (?, ?, ?, 'credit', ?, 'scrap_sales', ?, ?, ?, NOW())",
                        [
                            $sale['concern_id'], $cashAccount['id'], $saleDate, $amountReceived, $id,
                            "Stitching Scrap Sale [{$sale['sale_no']}] from Buyer {$buyerName}", Auth::id()
                        ]
                    );
                }
            }

            AuditLog::log('UPDATE', 'stitching_wastage_sales', $id, $sale, $_POST);
            DB::commit();
            $this->flash('success', "Stitching Scrap Sale '{$sale['sale_no']}' updated successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to update scrap sale: ' . $e->getMessage());
        }

        $this->redirect('/production/stitching?tab=wastage');
    }

    /**
     * Delete Individual Stitching Wastage Scrap Sale
     */
    public function deleteStitchingWastageSale(): void {
        $this->ensureStitchingSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            $this->flash('error', 'Invalid scrap sale ID.');
            $this->redirect('/production/stitching?tab=wastage');
        }

        $sale = DB::fetch(
            "SELECT * FROM stitching_wastage_sales WHERE id = ?" . ($cid ? " AND concern_id = ?" : ""),
            $cid ? [$id, $cid] : [$id]
        );

        if (!$sale) {
            $this->flash('error', 'Scrap sale not found or access denied.');
            $this->redirect('/production/stitching?tab=wastage');
        }

        DB::beginTransaction();
        try {
            DB::query("DELETE FROM stitching_wastage_sales WHERE id = ?", [$id]);

            // Revert sold wastage quantity in stitching_logs so batch becomes eligible again
            if (!empty($sale['stitching_id']) && !empty($sale['quantity'])) {
                DB::query(
                    "UPDATE stitching_logs SET sold_wastage_qty = GREATEST(0, COALESCE(sold_wastage_qty, 0) - ?) WHERE id = ?",
                    [(float)$sale['quantity'], (int)$sale['stitching_id']]
                );
            }

            // Remove associated cash transaction
            DB::query("DELETE FROM cash_transactions WHERE ref_module = 'scrap_sales' AND ref_id = ?", [$id]);

            AuditLog::log('DELETE', 'stitching_wastage_sales', $id, $sale, null);
            DB::commit();
            $this->flash('success', "Scrap Sale '{$sale['sale_no']}' deleted successfully. Batch is now restored and available for wastage recording.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to delete scrap sale: ' . $e->getMessage());
        }

        $this->redirect('/production/stitching?tab=wastage');
    }

    // ========================================================
    // 5. QC STOCKS INSPECTION
    // ========================================================
    // ========================================================
    // ========================================================
    // 5. QC STOCKS INSPECTION (Full Production QC Flow)
    // ========================================================

    protected function ensureQcSchema(): void {
        try {
            DB::query("
                CREATE TABLE IF NOT EXISTS `qc_inspections` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `concern_id` INT NULL,
                    `qc_no` VARCHAR(50) NOT NULL,
                    `batch_no` VARCHAR(50) NULL,
                    `variant_id` INT NULL,
                    `program_id` INT NULL,
                    `stitching_batch_id` INT NULL,
                    `cutting_batch_id` INT NULL,
                    `inspected_qty` DECIMAL(12,2) NOT NULL DEFAULT 0,
                    `passed_qty` DECIMAL(12,2) NOT NULL DEFAULT 0,
                    `rework_qty` DECIMAL(12,2) NOT NULL DEFAULT 0,
                    `rejected_qty` DECIMAL(12,2) NOT NULL DEFAULT 0,
                    `location` VARCHAR(100) NULL DEFAULT 'Main Store',
                    `defect_notes` TEXT NULL,
                    `rework_notes` TEXT NULL,
                    `status` VARCHAR(50) NOT NULL DEFAULT 'PENDING',
                    `inspector_id` INT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (`concern_id`),
                    INDEX (`stitching_batch_id`),
                    INDEX (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            DB::query("
                CREATE TABLE IF NOT EXISTS `qc_inspection_items` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `qc_id` INT NOT NULL,
                    `catalog_id` INT NULL,
                    `variant_id` INT NULL,
                    `stitching_item_id` INT NULL,
                    `size` VARCHAR(50) NULL,
                    `color` VARCHAR(50) NULL,
                    `product_code` VARCHAR(100) NULL,
                    `variant_name` VARCHAR(255) NULL,
                    `inspected_qty` INT NOT NULL DEFAULT 0,
                    `passed_qty` INT NOT NULL DEFAULT 0,
                    `rejected_qty` INT NOT NULL DEFAULT 0,
                    `rework_qty` INT NOT NULL DEFAULT 0,
                    `defect_notes` TEXT NULL,
                    `rework_notes` TEXT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (`qc_id`),
                    INDEX (`variant_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            DB::query("
                CREATE TABLE IF NOT EXISTS `qc_piece_items` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `qc_id` INT NOT NULL,
                    `qc_item_id` INT NULL,
                    `variant_id` INT NULL,
                    `stitching_item_id` INT NULL,
                    `tag_code` VARCHAR(100) NOT NULL,
                    `piece_number` INT NOT NULL DEFAULT 1,
                    `total_variant_pieces` INT NOT NULL DEFAULT 1,
                    `size` VARCHAR(50) NULL,
                    `color` VARCHAR(50) NULL,
                    `catalog_name` VARCHAR(255) NULL,
                    `verdict` ENUM('PENDING','PASSED','REJECTED','REWORK') NOT NULL DEFAULT 'PENDING',
                    `defect_reason` TEXT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (`qc_id`),
                    INDEX (`tag_code`),
                    INDEX (`verdict`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $cols = array_column(DB::fetchAll("DESCRIBE `qc_inspections`"), 'Field');
            if (!in_array('batch_no', $cols)) {
                DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `batch_no` VARCHAR(50) NULL");
            }
            if (!in_array('program_id', $cols)) {
                DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `program_id` INT NULL");
            }
            if (!in_array('cutting_batch_id', $cols)) {
                DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `cutting_batch_id` INT NULL");
            }
            if (!in_array('stitching_batch_id', $cols)) {
                DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `stitching_batch_id` INT NULL");
            }
            if (!in_array('rework_qty', $cols)) {
                DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `rework_qty` DECIMAL(12,2) DEFAULT 0.00");
            }
            if (!in_array('rework_notes', $cols)) {
                DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `rework_notes` TEXT NULL");
            }
            if (!in_array('status', $cols)) {
                DB::query("ALTER TABLE `qc_inspections` ADD COLUMN `status` VARCHAR(50) DEFAULT 'COMPLETED'");
            }
            DB::query("ALTER TABLE `qc_inspections` MODIFY COLUMN `variant_id` INT NULL");
            DB::query("ALTER TABLE `qc_inspections` MODIFY COLUMN `inspected_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
            DB::query("ALTER TABLE `qc_inspections` MODIFY COLUMN `passed_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
            DB::query("ALTER TABLE `qc_inspections` MODIFY COLUMN `rejected_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
            DB::query("ALTER TABLE `qc_inspections` MODIFY COLUMN `rework_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00");

            // Consolidate legacy 'Main Store (WH-01)' to unified 'Main Store'
            \App\Services\StockService::consolidateLegacyLocations();
        } catch (\Throwable $e) {}
    }

    /**
     * Inward passed QC garment quantity directly into live stocks & stock ledger
     */
    protected function inwardVariantToStock(int $variantId, float $qty, int $cid, int $qcId, string $qcNo, string $batchNo, string $location = 'Main Store', ?int $programId = null, ?int $stitchingBatchId = null, string $inspectorName = 'System Administrator'): void {
        if ($qty <= 0) return;

        $location = ($location === 'Main Store (WH-01)' || empty($location)) ? 'Main Store' : trim($location);

        // Idempotency: A QC record is allowed to credit each variant exactly once.
        // Repeating direct inward or completing the same QC again adds no stock and no ledger row.
        $alreadyPosted = DB::fetch(
            "SELECT id, qty_in
             FROM stock_ledger
             WHERE concern_id = ?
               AND variant_id = ?
               AND location = ?
               AND ref_module = 'Production QC'
               AND ref_id = ?
             LIMIT 1",
            [$cid, $variantId, $location, $qcId]
        );
        if ($alreadyPosted) {
            return;
        }

        // 1. Credit stocks table (stocks table primary key is (concern_id, variant_id, location))
        $existing = DB::fetch(
            "SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = ?",
            [$cid, $variantId, $location]
        );

        if ($existing) {
            DB::query(
                "UPDATE stocks SET qty = qty + ?, updated_at = NOW() WHERE concern_id = ? AND variant_id = ? AND location = ?",
                [$qty, $cid, $variantId, $location]
            );
        } else {
            DB::query(
                "INSERT INTO stocks (concern_id, variant_id, location, qty, updated_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$cid, $variantId, $location, $qty]
            );
        }

        // 2. Immutable Stock Ledger Entry
        DB::query(
            "INSERT INTO stock_ledger (concern_id, variant_id, qty_in, qty_out, location, ref_module, ref_id, note, created_by, created_at)
             VALUES (?, ?, ?, 0, ?, 'Production QC', ?, ?, ?, NOW())",
            [
                $cid,
                $variantId,
                $qty,
                $location,
                $qcId,
                "QC Passed ({$qcNo}) for Stitching Batch {$batchNo}",
                Auth::id() ?: 1
            ]
        );

        // 3. Batches register entry for Finished Goods Inventory
        try {
            $existingBatch = DB::fetch(
                "SELECT id FROM batches WHERE qc_batch_id = ? AND variant_id = ?",
                [$qcId, $variantId]
            );
            if ($existingBatch) {
                DB::query("UPDATE batches SET batch_qty = batch_qty + ? WHERE id = ?", [$qty, $existingBatch['id']]);
            } else {
                $today = date('Ymd');
                $countToday = (int)DB::fetchValue("SELECT COUNT(*) FROM batches WHERE batch_no LIKE ?", ["BAT-{$today}-%"]);
                $bSeq = str_pad((string)($countToday + 1), 4, '0', STR_PAD_LEFT);
                $batNo = "BAT-{$today}-{$bSeq}";

                DB::query("
                    INSERT INTO batches (
                        concern_id, production_unit_id, program_id, qc_batch_id, stitching_batch_id, batch_no,
                        variant_id, batch_qty, total_boxes, warehouse_id, warehouse_name, packed_by_name,
                        rework_included, status, mfg_date, created_at
                    ) VALUES (
                        ?, 1, ?, ?, ?, ?,
                        ?, ?, 1, 1, ?, ?,
                        0, 'APPROVED', CURDATE(), NOW()
                    )
                ", [
                    $cid, $programId, $qcId, $stitchingBatchId, $batNo,
                    $variantId, $qty, $location, $inspectorName
                ]);
            }
        } catch (\Throwable $e) {}
    }

    /**
     * QC Inspections List (Image 1: /production/qc)
     */
    public function qc(): void {
        ModuleGuard::enforce('qc_stocks', 'view');
        $this->ensureQcSchema();
        $cid = Auth::concernId();
        if (!$cid && !Auth::isSuperAdmin()) {
            $this->flash('error', 'Select a concern to view QC inspections.');
            $this->redirect('/dashboard');
            return;
        }

        $statusFilter = trim($_GET['status'] ?? '');
        $search       = trim($_GET['search'] ?? '');
        $page         = max(1, (int)($_GET['page'] ?? 1));
        $perPage      = max(5, (int)($_GET['per_page'] ?? 10));
        $offset       = ($page - 1) * $perPage;

        $sql = "SELECT qi.*, 
                       v.name AS variant_name, v.product_code, v.size, v.price, v.mrp,
                       sl.batch_no AS stitching_batch_no, sl.stitching_name,
                       COALESCE(p.program_no, sl.batch_no, qi.batch_no, 'PRG') AS program_no,
                       COALESCE(cat.name, p.program_name, sl.stitching_name, v.name, 'Garment Product') AS product_name,
                       COALESCE(u.name, 'System Administrator') AS inspector_name,
                       con.name AS concern_name, con.code AS concern_code,
                       (SELECT COUNT(*) FROM qc_inspection_items WHERE qc_id = qi.id) AS variant_count,
                       (SELECT SUM(CASE WHEN qp.verdict = 'PASSED' THEN 1 ELSE 0 END) FROM qc_piece_items qp WHERE qp.qc_id = qi.id) AS piece_passed_qty,
                       (SELECT SUM(CASE WHEN qp.verdict = 'REJECTED' THEN 1 ELSE 0 END) FROM qc_piece_items qp WHERE qp.qc_id = qi.id) AS piece_rejected_qty,
                       (SELECT SUM(CASE WHEN qp.verdict = 'REWORK' THEN 1 ELSE 0 END) FROM qc_piece_items qp WHERE qp.qc_id = qi.id) AS piece_rework_qty,
                       (SELECT COUNT(*) FROM qc_piece_items qp WHERE qp.qc_id = qi.id) AS piece_total_qty,
                       COALESCE((SELECT SUM(sl.qty_in - sl.qty_out) FROM stock_ledger sl WHERE sl.ref_module = 'QC Inspection' AND sl.ref_id = qi.id), 0) AS qc_stocked_qty,
                       COALESCE((SELECT SUM(s.qty) FROM stocks s WHERE s.concern_id = qi.concern_id AND s.location NOT LIKE '%:DAMAGED%' AND s.location NOT LIKE '%:IN_TRANSIT%' AND (s.variant_id = qi.variant_id OR s.variant_id IN (SELECT DISTINCT qii.variant_id FROM qc_inspection_items qii WHERE qii.qc_id = qi.id AND qii.variant_id IS NOT NULL))), 0) AS available_stock_qty
                FROM qc_inspections qi
                LEFT JOIN variants v ON qi.variant_id = v.id
                LEFT JOIN stitching_logs sl ON qi.stitching_batch_id = sl.id
                LEFT JOIN production_programs p ON (qi.program_id = p.id OR sl.program_id = p.id OR p.target_variant_id = v.id)
                LEFT JOIN catalogs cat ON p.catalog_id = cat.id
                LEFT JOIN users u ON qi.inspector_id = u.id
                LEFT JOIN concerns con ON qi.concern_id = con.id
                WHERE 1=1";
        $params = [];

        if ($cid) {
            $sql .= " AND (qi.concern_id = ? OR qi.concern_id IS NULL)";
            $params[] = $cid;
        }

        if ($statusFilter !== '' && $statusFilter !== 'All Statuses') {
            $sql .= " AND UPPER(qi.status) = ?";
            $params[] = strtoupper($statusFilter);
        }

        if ($search !== '') {
            $sql .= " AND (qi.qc_no LIKE ? OR p.program_no LIKE ? OR sl.batch_no LIKE ? OR v.name LIKE ? OR v.product_code LIKE ? OR u.name LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $countSql = "SELECT COUNT(*) FROM (" . $sql . ") AS cnt_tbl";
        $totalCount = (int)DB::fetchValue($countSql, $params);
        $totalPages = max(1, (int)ceil($totalCount / $perPage));

        $sql .= " ORDER BY qi.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $inspections = DB::fetchAll($sql, $params);
        foreach ($inspections as &$item) {
            if ($item['piece_total_qty'] !== null && (int)$item['piece_total_qty'] > 0) {
                $item['live_passed_qty'] = (float)$item['piece_passed_qty'];
                $item['live_rejected_qty'] = (float)$item['piece_rejected_qty'];
                $item['live_rework_qty'] = (float)$item['piece_rework_qty'];
                $item['live_inspected_qty'] = (float)$item['piece_total_qty'];
            } else {
                $item['live_passed_qty'] = (float)$item['passed_qty'];
                $item['live_rejected_qty'] = (float)$item['rejected_qty'];
                $item['live_rework_qty'] = (float)$item['rework_qty'];
                $item['live_inspected_qty'] = (float)$item['inspected_qty'];
            }
        }
        unset($item);

        $this->render('concern/qc_stocks', [
            'pageTitle'     => 'Quality Control (QC) Inspections',
            'currentModule' => 'qc_stocks',
            'inspections'   => $inspections,
            'totalCount'    => $totalCount,
            'page'          => $page,
            'perPage'       => $perPage,
            'totalPages'    => $totalPages,
            'statusFilter'  => $statusFilter,
            'search'        => $search,
            'selectedCid'   => $cid,
        ]);
    }

    /**
     * API to fetch Stitching Batch variants & details for QC create
     */
    public function apiStitchingDetailsForQc(): void {
        $cid = Auth::concernId();
        $stitchingId = (int)($_GET['stitching_id'] ?? 0);
        if (!$stitchingId) {
            echo json_encode(['success' => false, 'message' => 'Invalid stitching ID']);
            return;
        }

        $batch = DB::fetch("
            SELECT sl.*,
                   COALESCE(sl.batch_no, sl.log_no) AS display_batch_no,
                   pp.program_no, pp.program_name,
                   (SELECT COUNT(*) FROM stitching_items WHERE stitching_id = sl.id) AS variant_count,
                   COALESCE((SELECT SUM(stitching_qty) FROM stitching_items WHERE stitching_id = sl.id), sl.stitched_pieces, 0) AS total_pieces
            FROM stitching_logs sl
            LEFT JOIN production_programs pp ON sl.program_id = pp.id
            WHERE sl.id = ?" . ($cid ? " AND (sl.concern_id = ? OR sl.concern_id IS NULL)" : ""),
            $cid ? [$stitchingId, $cid] : [$stitchingId]
        );

        if (!$batch) {
            echo json_encode(['success' => false, 'message' => 'Stitching batch not found']);
            return;
        }

        $items = DB::fetchAll("
            SELECT si.*,
                   COALESCE(cat.id, pp.catalog_id) AS catalog_id,
                   COALESCE(cat.name, 'Catalog Item') AS catalog_name,
                   cat.hierarchy_path,
                   cat.style_code,
                   COALESCE(v.product_code, si.product_code) AS variant_product_code,
                   COALESCE(v.name, si.variant_name, CONCAT(si.size, ' - ', si.color)) AS variant_full_name
            FROM stitching_items si
            JOIN stitching_logs sl ON si.stitching_id = sl.id
            LEFT JOIN production_programs pp ON sl.program_id = pp.id
            LEFT JOIN catalogs cat ON si.catalog_id = cat.id
            LEFT JOIN variants v ON si.variant_id = v.id
            WHERE si.stitching_id = ?
            ORDER BY si.catalog_id ASC, " . self::sizeOrderSql('si.size') . ", si.id ASC
        ", [$stitchingId]);

        echo json_encode([
            'success' => true,
            'batch'   => $batch,
            'items'   => $items
        ]);
    }

    /**
     * Initialize QC Inspection Batch (Image 3: /production/qc/create)
     */
    public function createQc(): void {
        ModuleGuard::enforce('qc_stocks', 'add');
        $this->ensureQcSchema();
        $cid = Auth::concernId();
        if (!$cid && !Auth::isSuperAdmin()) {
            $this->flash('error', 'Select a concern before initializing QC.');
            $this->redirect('/production/qc');
            return;
        }

        $savedQcId = (int)($_GET['qc_id'] ?? 0);

        // Fetch completed or in-progress stitching batches that have not already been submitted to QC
        $batchSql = "
            SELECT sl.*,
                   COALESCE(sl.batch_no, sl.log_no) AS display_batch_no,
                   pp.program_no, pp.program_name,
                   (SELECT COUNT(*) FROM stitching_items WHERE stitching_id = sl.id) AS variant_count,
                   COALESCE((SELECT SUM(stitching_qty) FROM stitching_items WHERE stitching_id = sl.id), sl.stitched_pieces, 0) AS total_pieces
            FROM stitching_logs sl
            LEFT JOIN production_programs pp ON sl.program_id = pp.id
            WHERE (sl.status != 'cancelled' OR sl.status IS NULL)
              AND NOT EXISTS (
                  SELECT 1 FROM qc_inspections qi
                  WHERE qi.stitching_batch_id = sl.id
                    AND (qi.status IS NULL OR qi.status != 'CANCELLED')
                    AND qi.id != ?
              )";
        $batchParams = [$savedQcId];
        if ($cid) {
            $batchSql .= " AND (sl.concern_id = ? OR sl.concern_id IS NULL)";
            $batchParams[] = $cid;
        }
        $batchSql .= " ORDER BY sl.id DESC";
        $stitchingBatches = DB::fetchAll($batchSql, $batchParams);

        // Preselected stitching batch
        $requestedStitchingId = (int)($_GET['stitching_batch_id'] ?? 0);
        $selectedBatch = null;
        foreach ($stitchingBatches as $b) {
            if ($requestedStitchingId > 0 && (int)$b['id'] === $requestedStitchingId) {
                $selectedBatch = $b;
                break;
            }
        }
        if (!$selectedBatch && !empty($stitchingBatches)) {
            $selectedBatch = $stitchingBatches[0];
        }
        $selectedStitchingId = (int)($selectedBatch['id'] ?? 0);

        // Fetch variants for selected stitching batch
        $batchItems = [];
        if ($selectedStitchingId > 0) {
            $itemsSql = "
                SELECT si.*,
                       COALESCE(cat.id, pp.catalog_id) AS catalog_id,
                       COALESCE(cat.name, 'Catalog Item') AS catalog_name,
                       cat.hierarchy_path,
                       cat.style_code,
                       COALESCE(v.product_code, si.product_code) AS variant_product_code,
                       COALESCE(v.name, si.variant_name, CONCAT(si.size, ' - ', si.color)) AS variant_full_name
                FROM stitching_items si
                JOIN stitching_logs sl ON si.stitching_id = sl.id
                LEFT JOIN production_programs pp ON sl.program_id = pp.id
                LEFT JOIN catalogs cat ON si.catalog_id = cat.id
                LEFT JOIN variants v ON si.variant_id = v.id
                WHERE si.stitching_id = ?
            ";
            $itemsParams = [$selectedStitchingId];
            if ($cid) {
                $itemsSql .= " AND (sl.concern_id = ? OR sl.concern_id IS NULL)";
                $itemsParams[] = $cid;
            }
            $itemsSql .= " ORDER BY si.catalog_id ASC, " . self::sizeOrderSql('si.size') . ", si.id ASC";
            $batchItems = DB::fetchAll($itemsSql, $itemsParams);
        }

        $savedQc = null;
        if ($savedQcId > 0 && $selectedStitchingId > 0) {
            $savedQcSql = "
                SELECT id, qc_no, status, concern_id, stitching_batch_id, defect_notes
                FROM qc_inspections
                WHERE id = ? AND stitching_batch_id = ? AND status = 'PENDING'";
            $savedQcParams = [$savedQcId, $selectedStitchingId];
            if ($cid) {
                $savedQcSql .= " AND (concern_id = ? OR concern_id IS NULL)";
                $savedQcParams[] = $cid;
            }
            $savedQc = DB::fetch($savedQcSql, $savedQcParams);

            if ($savedQc) {
                $pieceStats = DB::fetch(
                    "SELECT COUNT(*) AS total,
                            SUM(CASE WHEN verdict = 'PENDING' THEN 1 ELSE 0 END) AS pending
                     FROM qc_piece_items
                     WHERE qc_id = ?",
                    [$savedQcId]
                );
                if ((int)($pieceStats['total'] ?? 0) <= 0 || (int)($pieceStats['pending'] ?? 0) > 0) {
                    $this->flash('info', 'Continue the QC scan and mark every piece before adding passed pieces to stock.');
                    $this->redirect('/production/qc/inspect?id=' . $savedQcId);
                    return;
                }

                $savedQcItems = DB::fetchAll(
                    "SELECT qii.*,
                            (SELECT SUM(CASE WHEN qp.verdict = 'PASSED' THEN 1 ELSE 0 END)
                             FROM qc_piece_items qp WHERE qp.qc_item_id = qii.id) AS scan_passed_qty,
                            (SELECT SUM(CASE WHEN qp.verdict = 'REJECTED' THEN 1 ELSE 0 END)
                             FROM qc_piece_items qp WHERE qp.qc_item_id = qii.id) AS scan_rejected_qty,
                            (SELECT SUM(CASE WHEN qp.verdict = 'REWORK' THEN 1 ELSE 0 END)
                             FROM qc_piece_items qp WHERE qp.qc_item_id = qii.id) AS scan_rework_qty,
                            (SELECT GROUP_CONCAT(DISTINCT NULLIF(qp.defect_reason, '') SEPARATOR '; ')
                             FROM qc_piece_items qp WHERE qp.qc_item_id = qii.id) AS scan_defect_notes
                     FROM qc_inspection_items qii
                     WHERE qii.qc_id = ?
                     ORDER BY qii.id ASC",
                    [$savedQcId]
                );
                $savedQcItemsByStitchingItem = [];
                foreach ($savedQcItems as $savedQcItem) {
                    $savedQcItemsByStitchingItem[(int)$savedQcItem['stitching_item_id']] = $savedQcItem;
                }
                foreach ($batchItems as &$batchItem) {
                    $savedQcItem = $savedQcItemsByStitchingItem[(int)$batchItem['id']] ?? [];
                    $batchItem['qc_passed_qty'] = (int)($savedQcItem['scan_passed_qty'] ?? 0);
                    $batchItem['qc_rejected_qty'] = (int)($savedQcItem['scan_rejected_qty'] ?? 0);
                    $batchItem['qc_rework_qty'] = (int)($savedQcItem['scan_rework_qty'] ?? 0);
                    $batchItem['qc_defect_notes'] = (string)($savedQcItem['scan_defect_notes'] ?? $savedQcItem['defect_notes'] ?? '');
                }
                unset($batchItem);
            }
        }

        // Inspectors list
        $inspectors = DB::fetchAll("
            SELECT u.id, u.name, r.name AS role, u.email 
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.status = 'active'" . ($cid ? " AND (u.concern_id = ? OR u.concern_id IS NULL)" : "") . "
            ORDER BY u.name ASC
        ", $cid ? [$cid] : []);

        $this->render('concern/qc_create', [
            'pageTitle'           => 'Initialize QC Inspection Batch',
            'currentModule'       => 'qc_stocks',
            'stitchingBatches'    => $stitchingBatches,
            'selectedBatch'       => $selectedBatch,
            'selectedStitchingId' => $selectedStitchingId,
            'batchItems'          => $batchItems,
            'savedQc'             => $savedQc,
            'inspectors'          => $inspectors,
            'csrfToken'           => Auth::csrfToken(),
            'selectedCid'         => $cid,
        ]);
    }

    /**
     * Quick-create a QC Inspector / Staff member from QC Create page.
     */
    public function storeQcStaffAjax(): void {
        ModuleGuard::enforce('qc_stocks', 'add');
        $this->validateCsrf();

        $cid = Auth::concernId();
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            $this->renderJson(['success' => false, 'message' => 'Enter a valid staff name (max 120 characters).'], 422);
            return;
        }
        if ($phone !== '' && !preg_match('/^\d{10}$/', $phone)) {
            $this->renderJson(['success' => false, 'message' => 'Phone number must be 10 digits.'], 422);
            return;
        }

        $roleRow = DB::fetch(
            "SELECT id, name FROM roles WHERE concern_id = ? AND status = 'active' ORDER BY CASE WHEN LOWER(name) LIKE '%qc%' OR LOWER(name) LIKE '%inspector%' THEN 0 ELSE 1 END, id ASC LIMIT 1",
            [$cid ?: 1]
        );
        if (!$roleRow) {
            $roleRow = DB::fetch("SELECT id, name FROM roles WHERE status = 'active' ORDER BY id ASC LIMIT 1");
        }
        $roleId = (int)($roleRow['id'] ?? 1);
        $roleName = (string)($roleRow['name'] ?? 'Staff');

        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $name));
        $slug = trim($slug, '.') ?: 'staff';
        $email = $slug . '.' . substr((string)time(), -4) . random_int(10, 99) . '@qc.local';
        $passwordHash = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);

        DB::query(
            "INSERT INTO users (concern_id, role_id, name, email, phone, password, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())",
            [$cid ?: null, $roleId, $name, $email, $phone !== '' ? $phone : null, $passwordHash]
        );
        $newId = (int)DB::lastInsertId();

        $this->renderJson([
            'success' => true,
            'staff' => [
                'id'   => $newId,
                'name' => $name,
                'role' => $roleName,
            ],
        ]);
    }

    /**
     * Initialize a QC inspection and create its scanner piece records.
     */
    public function storeQc(): void {
        ModuleGuard::enforce('qc_stocks', 'add');
        $this->ensureQcSchema();
        $this->validateCsrf();
        $authCid = Auth::concernId();

        $stitchingBatchId = (int)($_POST['stitching_batch_id'] ?? 0);
        $inspectorId      = (int)($_POST['inspector_id'] ?? Auth::id() ?: 1);
        $location         = 'Main Store';
        $generalNotes     = trim($_POST['notes'] ?? '');
        $submitAction     = trim($_POST['submit_action'] ?? '');

        if ($submitAction !== 'save_and_inspect') {
            $this->flash('error', 'QC must be inspected before passed pieces can be added to stock.');
            $this->redirect('/production/qc/create?stitching_batch_id=' . $stitchingBatchId);
            return;
        }

        if (!$stitchingBatchId) {
            $this->flash('error', 'Please select a valid Stitching Production Batch.');
            $this->redirect('/production/qc/create');
            return;
        }

        $batchSql = "SELECT sl.*, COALESCE(sl.batch_no, sl.log_no) AS display_batch_no, pp.program_no, pp.program_name
                     FROM stitching_logs sl
                     LEFT JOIN production_programs pp ON sl.program_id = pp.id
                     WHERE sl.id = ?";
        $batchParams = [$stitchingBatchId];
        if ($authCid) {
            $batchSql .= " AND (sl.concern_id = ? OR sl.concern_id IS NULL)";
            $batchParams[] = $authCid;
        } elseif (!Auth::isSuperAdmin()) {
            $this->flash('error', 'Select a concern before creating a QC inspection.');
            $this->redirect('/production/qc/create');
            return;
        }
        $batch = DB::fetch($batchSql, $batchParams);

        if (!$batch) {
            $this->flash('error', 'Selected stitching batch not found.');
            $this->redirect('/production/qc/create');
            return;
        }

        $cid = $authCid ?: (int)$batch['concern_id'];
        if ($cid <= 0 || !DB::fetchValue("SELECT id FROM concerns WHERE id = ? AND status = 'active'", [$cid])) {
            $this->flash('error', 'The selected stitching batch is not linked to an active concern.');
            $this->redirect('/production/qc/create');
            return;
        }

        $existingQc = DB::fetch(
            "SELECT id, qc_no, status
             FROM qc_inspections
             WHERE stitching_batch_id = ? AND concern_id = ? AND status != 'CANCELLED'
             ORDER BY id DESC LIMIT 1",
            [$stitchingBatchId, $cid]
        );
        if ($existingQc) {
            $existingStatus = strtoupper((string)($existingQc['status'] ?? 'PENDING'));
            if ($existingStatus !== 'COMPLETED') {
                $this->redirect("/production/qc/inspect?id=" . (int)$existingQc['id']);
                return;
            }
            $this->flash('warning', "Stitching Batch {$batch['display_batch_no']} already has completed QC {$existingQc['qc_no']}. It cannot create another stock entry.");
            $this->redirect("/production/qc/details?id=" . (int)$existingQc['id']);
            return;
        }

        $submittedItems = $_POST['items'] ?? [];
        if (empty($submittedItems) || !is_array($submittedItems)) {
            $this->flash('error', 'No variant items available in this batch for inspection.');
            $this->redirect('/production/qc/create?stitching_batch_id=' . $stitchingBatchId);
            return;
        }

        // Sequential QC number: QC #X
        $qcCount = (int)DB::fetchValue("SELECT COUNT(*) FROM qc_inspections WHERE (concern_id = ? OR concern_id IS NULL)", [$cid]);
        $qcNo = 'QC #' . ($qcCount + 1);

        $totInspected = 0;
        $totPassed = 0;
        $totRejected = 0;
        $totRework = 0;

        foreach ($submittedItems as $it) {
            $totInspected += max(0, (int)($it['total_qty'] ?? 0));
        }

        if ($totInspected <= 0) {
            $totInspected = (int)$batch['stitched_pieces'];
        }

        $status = 'PENDING';

        $firstVariantId = !empty($submittedItems[0]['variant_id']) ? (int)$submittedItems[0]['variant_id'] : null;

        DB::beginTransaction();
        try {
            DB::query(
                "INSERT INTO qc_inspections (
                    concern_id, qc_no, batch_no, variant_id, program_id, stitching_batch_id, cutting_batch_id,
                    inspected_qty, passed_qty, rework_qty, rejected_qty, location, defect_notes, rework_notes,
                    status, inspector_id, created_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $cid, $qcNo, $batch['display_batch_no'], $firstVariantId, (int)$batch['program_id'], $stitchingBatchId, (int)$batch['cutting_id'],
                    $totInspected, $totPassed, $totRework, $totRejected, $location, $generalNotes, '',
                    $status, $inspectorId
                ]
            );

            $qcId = (int)DB::lastInsertId();

            // Insert variant-wise inspection items & generate piece items
            $batchCode = preg_replace('/[^A-Z0-9]/i', '', $batch['display_batch_no']);
            $tagSeq = 1;

            foreach ($submittedItems as $it) {
                $stitchingItemId = (int)($it['stitching_item_id'] ?? 0);
                $catalogId       = !empty($it['catalog_id']) ? (int)$it['catalog_id'] : null;
                $variantId       = !empty($it['variant_id']) ? (int)$it['variant_id'] : null;
                $size            = trim($it['size'] ?? '');
                $color           = trim($it['color'] ?? '');
                $sku             = trim($it['product_code'] ?? '');
                $variantName     = trim($it['variant_name'] ?? '');
                $inspectedQ      = max(0, (int)($it['total_qty'] ?? 0));
                $passedQ         = 0;
                $rejectedQ       = 0;
                $reworkQ         = 0;
                $defectNote      = trim($it['defect_notes'] ?? '');
                $reworkNote      = trim($it['rework_notes'] ?? '');

                DB::query("
                    INSERT INTO qc_inspection_items (
                        qc_id, catalog_id, variant_id, stitching_item_id, size, color, product_code, variant_name,
                        inspected_qty, passed_qty, rejected_qty, rework_qty, defect_notes, rework_notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ", [
                    $qcId, $catalogId, $variantId, $stitchingItemId, $size, $color, $sku, $variantName,
                    $inspectedQ, $passedQ, $rejectedQ, $reworkQ, $defectNote, $reworkNote
                ]);

                $qcItemId = (int)DB::lastInsertId();

                // If variant_id is missing, resolve by catalog and size
                if (!$variantId && $catalogId && $size) {
                    $foundVar = DB::fetch("SELECT id FROM variants WHERE catalog_id = ? AND size = ? LIMIT 1", [$catalogId, $size]);
                    if ($foundVar) {
                        $variantId = (int)$foundVar['id'];
                    }
                }

                // Generate piece records for the QR scanner
                // Format code matching Finished Goods Product label base (variant-wise)
                $fgCode = null;
                if ($variantId > 0) {
                    $fgCode = \App\Services\QrService::formatBrandVariantCode($variantId);
                }
                $skuPrefix = preg_replace('/[^A-Z0-9]/i', '', strtoupper(substr($sku ?: ($size . $color), 0, 3)));
                $baseProductCode = $fgCode ?: (($skuPrefix ?: 'GAR') . date('md'));

                for ($p = 1; $p <= $inspectedQ; $p++) {
                    // Single code for one variant's products (matching production QR label code)
                    $pieceCode = $baseProductCode;
                    
                    $pVerdict = 'PENDING';

                    DB::query("
                        INSERT INTO qc_piece_items (
                            qc_id, qc_item_id, variant_id, stitching_item_id, tag_code, piece_number,
                            total_variant_pieces, size, color, catalog_name, verdict, defect_reason, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ", [
                        $qcId, $qcItemId, $variantId, $stitchingItemId, $pieceCode, $p,
                        $inspectedQ, $size, $color, $variantName, $pVerdict, ($pVerdict === 'REJECTED' ? $defectNote : ($pVerdict === 'REWORK' ? $reworkNote : null))
                    ]);

                    $tagSeq++;
                }

            }

            DB::commit();

            $this->flash('success', "QC Inspection Batch {$qcNo} initialized. Scan each garment and record its verdict.");
            $this->redirect("/production/qc/inspect?id={$qcId}");
            return;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to save QC inspection: ' . $e->getMessage());
            $this->redirect('/production/qc/create?stitching_batch_id=' . $stitchingBatchId);
        }
    }

    /**
     * Interactive 2D QR Garment Scanner Inspection View (Image 4: /production/qc/inspect?id=X)
     */
    public function inspectQc(): void {
        $this->ensureQcSchema();
        $cid = Auth::concernId();
        if (!$cid && !Auth::isSuperAdmin()) {
            $this->flash('error', 'Select a concern to inspect this QC batch.');
            $this->redirect('/production/qc');
            return;
        }
        $id = (int)($_GET['id'] ?? 0);

        $qc = DB::fetch("
            SELECT qi.*, 
                   sl.batch_no AS stitching_batch_no, sl.stitching_name,
                   pp.program_no, pp.program_name,
                   u.name AS inspector_name
            FROM qc_inspections qi
            LEFT JOIN stitching_logs sl ON qi.stitching_batch_id = sl.id
            LEFT JOIN production_programs pp ON (qi.program_id = pp.id OR sl.program_id = pp.id)
            LEFT JOIN users u ON qi.inspector_id = u.id
            WHERE qi.id = ?" . ($cid ? " AND (qi.concern_id = ? OR qi.concern_id IS NULL)" : ""),
            $cid ? [$id, $cid] : [$id]
        );

        if (!$qc) {
            $this->flash('error', 'QC Inspection batch not found or access denied.');
            $this->redirect('/production/qc');
            return;
        }

        $pieces = DB::fetchAll("
            SELECT qp.*, qii.product_code, qii.variant_name
            FROM qc_piece_items qp
            LEFT JOIN qc_inspection_items qii ON qp.qc_item_id = qii.id
            WHERE qp.qc_id = ?
            ORDER BY qp.id ASC
        ", [$id]);

        // Dynamically heal tag_code so every variant has a single code (matching single production QR label)
        foreach ($pieces as &$p) {
            $vId = (int)($p['variant_id'] ?? 0);
            if ($vId <= 0 && !empty($p['qc_item_id'])) {
                $qii = DB::fetch("SELECT variant_id, catalog_id, size FROM qc_inspection_items WHERE id = ?", [$p['qc_item_id']]);
                if (!empty($qii['variant_id'])) {
                    $vId = (int)$qii['variant_id'];
                } elseif (!empty($qii['catalog_id']) && !empty($qii['size'])) {
                    $found = DB::fetch("SELECT id FROM variants WHERE catalog_id = ? AND size = ? LIMIT 1", [$qii['catalog_id'], $qii['size']]);
                    if ($found) $vId = (int)$found['id'];
                }
                if ($vId > 0) {
                    DB::query("UPDATE qc_piece_items SET variant_id = ? WHERE id = ?", [$vId, $p['id']]);
                    $p['variant_id'] = $vId;
                }
            }

            if ($vId > 0) {
                $fgCode = \App\Services\QrService::formatBrandVariantCode($vId);
                if ($fgCode && ($p['tag_code'] !== $fgCode || str_contains($p['tag_code'], '-'))) {
                    DB::query("UPDATE qc_piece_items SET tag_code = ? WHERE id = ?", [$fgCode, $p['id']]);
                    $p['tag_code'] = $fgCode;
                }
            } elseif (preg_match('/^(.+)-\d{1,4}$/', $p['tag_code'], $m)) {
                $cleanCode = $m[1];
                DB::query("UPDATE qc_piece_items SET tag_code = ? WHERE id = ?", [$cleanCode, $p['id']]);
                $p['tag_code'] = $cleanCode;
            }
        }
        unset($p);

        $this->render('concern/qc_inspect', [
            'pageTitle'     => "2D QR Garment Scanner — {$qc['qc_no']}",
            'currentModule' => 'qc_stocks',
            'qc'            => $qc,
            'pieces'        => $pieces,
            'csrfToken'     => Auth::csrfToken(),
        ]);
    }

    /**
     * AJAX piece verdict update (Pass, Reject, Rework from Image 4)
     */
    public function updateQcPiece(): void {
        header('Content-Type: application/json');
        ModuleGuard::enforce('qc_stocks', 'edit|add');
        $this->ensureQcSchema();
        $pieceId = (int)($_POST['piece_id'] ?? 0);
        $verdict = strtoupper(trim($_POST['verdict'] ?? ''));
        $reason  = trim($_POST['defect_reason'] ?? '');

        if (!in_array($verdict, ['PASSED', 'REJECTED', 'REWORK', 'PENDING'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid verdict']);
            return;
        }

        $piece = DB::fetch("SELECT * FROM qc_piece_items WHERE id = ?", [$pieceId]);
        if (!$piece) {
            echo json_encode(['success' => false, 'message' => 'Piece not found']);
            return;
        }

        try {
            DB::query("UPDATE qc_piece_items SET verdict = ?, defect_reason = ? WHERE id = ?", [$verdict, $reason ?: null, $pieceId]);
            
            // Recalculate tally for this QC inspection
            $counts = DB::fetch("
                SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN verdict = 'PASSED' THEN 1 ELSE 0 END) AS passed,
                    SUM(CASE WHEN verdict = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
                    SUM(CASE WHEN verdict = 'REWORK' THEN 1 ELSE 0 END) AS rework,
                    SUM(CASE WHEN verdict = 'PENDING' THEN 1 ELSE 0 END) AS pending
                FROM qc_piece_items WHERE qc_id = ?
            ", [$piece['qc_id']]);

            // Sync live counts to qc_inspections
            DB::query(
                "UPDATE qc_inspections SET passed_qty = ?, rejected_qty = ?, rework_qty = ? WHERE id = ?",
                [(int)($counts['passed'] ?? 0), (int)($counts['rejected'] ?? 0), (int)($counts['rework'] ?? 0), $piece['qc_id']]
            );

            // Sync live counts to qc_inspection_items if item id is present
            if (!empty($piece['qc_item_id'])) {
                $itemCounts = DB::fetch("
                    SELECT 
                        SUM(CASE WHEN verdict = 'PASSED' THEN 1 ELSE 0 END) AS passed,
                        SUM(CASE WHEN verdict = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
                        SUM(CASE WHEN verdict = 'REWORK' THEN 1 ELSE 0 END) AS rework
                    FROM qc_piece_items WHERE qc_item_id = ?
                ", [$piece['qc_item_id']]);
                DB::query(
                    "UPDATE qc_inspection_items SET passed_qty = ?, rejected_qty = ?, rework_qty = ? WHERE id = ?",
                    [(int)($itemCounts['passed'] ?? 0), (int)($itemCounts['rejected'] ?? 0), (int)($itemCounts['rework'] ?? 0), $piece['qc_item_id']]
                );
            }

            echo json_encode([
                'success' => true,
                'piece_id' => $pieceId,
                'verdict' => $verdict,
                'counts' => $counts
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX Pass All pending pieces
     */
    public function passAllQcPieces(): void {
        header('Content-Type: application/json');
        ModuleGuard::enforce('qc_stocks', 'edit|add');
        $this->ensureQcSchema();
        $qcId = (int)($_POST['qc_id'] ?? 0);

        try {
            DB::query("UPDATE qc_piece_items SET verdict = 'PASSED' WHERE qc_id = ? AND verdict = 'PENDING'", [$qcId]);
            $counts = DB::fetch("
                SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN verdict = 'PASSED' THEN 1 ELSE 0 END) AS passed,
                    SUM(CASE WHEN verdict = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
                    SUM(CASE WHEN verdict = 'REWORK' THEN 1 ELSE 0 END) AS rework,
                    SUM(CASE WHEN verdict = 'PENDING' THEN 1 ELSE 0 END) AS pending
                FROM qc_piece_items WHERE qc_id = ?
            ", [$qcId]);

            // Sync live counts to qc_inspections
            DB::query(
                "UPDATE qc_inspections SET passed_qty = ?, rejected_qty = ?, rework_qty = ? WHERE id = ?",
                [(int)($counts['passed'] ?? 0), (int)($counts['rejected'] ?? 0), (int)($counts['rework'] ?? 0), $qcId]
            );

            // Sync live counts to each qc_inspection_item under this qc_id
            $qcItems = DB::fetchAll("SELECT id FROM qc_inspection_items WHERE qc_id = ?", [$qcId]);
            foreach ($qcItems as $qItem) {
                $itemCounts = DB::fetch("
                    SELECT 
                        SUM(CASE WHEN verdict = 'PASSED' THEN 1 ELSE 0 END) AS passed,
                        SUM(CASE WHEN verdict = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
                        SUM(CASE WHEN verdict = 'REWORK' THEN 1 ELSE 0 END) AS rework
                    FROM qc_piece_items WHERE qc_item_id = ?
                ", [$qItem['id']]);
                DB::query(
                    "UPDATE qc_inspection_items SET passed_qty = ?, rejected_qty = ?, rework_qty = ? WHERE id = ?",
                    [(int)($itemCounts['passed'] ?? 0), (int)($itemCounts['rejected'] ?? 0), (int)($itemCounts['rework'] ?? 0), $qItem['id']]
                );
            }

            echo json_encode(['success' => true, 'counts' => $counts]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Save scanner verdicts and return to the QC batch page without moving stock.
     */
    public function saveQcInspection(): void {
        ModuleGuard::enforce('qc_stocks', 'edit|add');
        $this->ensureQcSchema();
        $this->validateCsrf();

        $authCid = Auth::concernId();
        if (!$authCid && !Auth::isSuperAdmin()) {
            $this->flash('error', 'Select a concern before saving QC.');
            $this->redirect('/production/qc');
            return;
        }

        $id = (int)($_POST['qc_id'] ?? 0);
        $qcSql = "SELECT id, qc_no, stitching_batch_id, status, concern_id FROM qc_inspections WHERE id = ?";
        $qcParams = [$id];
        if ($authCid) {
            $qcSql .= " AND (concern_id = ? OR concern_id IS NULL)";
            $qcParams[] = $authCid;
        }
        $qc = DB::fetch($qcSql, $qcParams);
        if (!$qc || strtoupper((string)$qc['status']) !== 'PENDING') {
            $this->flash('error', 'The pending QC inspection could not be found.');
            $this->redirect('/production/qc');
            return;
        }

        $counts = DB::fetch(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN verdict = 'PASSED' THEN 1 ELSE 0 END) AS passed,
                    SUM(CASE WHEN verdict = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
                    SUM(CASE WHEN verdict = 'REWORK' THEN 1 ELSE 0 END) AS rework,
                    SUM(CASE WHEN verdict = 'PENDING' THEN 1 ELSE 0 END) AS pending
             FROM qc_piece_items
             WHERE qc_id = ?",
            [$id]
        );
        if ((int)($counts['total'] ?? 0) <= 0 || (int)($counts['pending'] ?? 0) > 0) {
            $this->flash('error', 'Record a verdict for every garment before saving the inspection.');
            $this->redirect('/production/qc/inspect?id=' . $id);
            return;
        }

        DB::beginTransaction();
        try {
            $qcItems = DB::fetchAll("SELECT id FROM qc_inspection_items WHERE qc_id = ?", [$id]);
            foreach ($qcItems as $qcItem) {
                $itemCounts = DB::fetch(
                    "SELECT SUM(CASE WHEN verdict = 'PASSED' THEN 1 ELSE 0 END) AS passed,
                            SUM(CASE WHEN verdict = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
                            SUM(CASE WHEN verdict = 'REWORK' THEN 1 ELSE 0 END) AS rework
                     FROM qc_piece_items
                     WHERE qc_item_id = ?",
                    [(int)$qcItem['id']]
                );
                DB::query(
                    "UPDATE qc_inspection_items
                     SET passed_qty = ?, rejected_qty = ?, rework_qty = ?
                     WHERE id = ?",
                    [
                        (int)($itemCounts['passed'] ?? 0),
                        (int)($itemCounts['rejected'] ?? 0),
                        (int)($itemCounts['rework'] ?? 0),
                        (int)$qcItem['id']
                    ]
                );
            }

            DB::query(
                "UPDATE qc_inspections
                 SET passed_qty = ?, rejected_qty = ?, rework_qty = ?
                 WHERE id = ? AND status = 'PENDING'",
                [
                    (int)($counts['passed'] ?? 0),
                    (int)($counts['rejected'] ?? 0),
                    (int)($counts['rework'] ?? 0),
                    $id
                ]
            );
            DB::commit();

            $this->flash('success', "QC {$qc['qc_no']} saved. Review the verdicts and add passed pieces to stock from this page.");
            $this->redirect('/production/qc/create?stitching_batch_id=' . (int)$qc['stitching_batch_id'] . '&qc_id=' . $id);
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to save QC inspection: ' . $e->getMessage());
            $this->redirect('/production/qc/inspect?id=' . $id);
        }
    }

    /**
     * Add passed pieces from a saved QC inspection to stock.
     */
    public function completeQcInspection(): void {
        $this->ensureQcSchema();
        $this->validateCsrf();
        $authCid = Auth::concernId();
        if (!$authCid && !Auth::isSuperAdmin()) {
            $this->flash('error', 'Select a concern before completing QC.');
            $this->redirect('/production/qc');
            return;
        }
        $id = (int)($_POST['qc_id'] ?? 0);

        $qc = DB::fetch("
            SELECT qi.*, sl.batch_no AS stitching_batch_no, sl.stitching_name, sl.program_id AS sl_prog_id,
                   u.name AS inspector_name
            FROM qc_inspections qi
            LEFT JOIN stitching_logs sl ON qi.stitching_batch_id = sl.id
            LEFT JOIN users u ON qi.inspector_id = u.id
            WHERE qi.id = ?" . ($authCid ? " AND (qi.concern_id = ? OR qi.concern_id IS NULL)" : ""),
            $authCid ? [$id, $authCid] : [$id]
        );

        if (!$qc) {
            $this->flash('error', 'QC inspection record not found.');
            $this->redirect('/production/qc');
            return;
        }

        $cid = $authCid ?: (int)($qc['concern_id'] ?? 0);
        if ($cid <= 0) {
            $this->flash('error', 'The QC inspection is not linked to an active concern.');
            $this->redirect('/production/qc');
            return;
        }
        $terminalStatus = strtoupper((string)($qc['status'] ?? ''));
        $pendingPieces = (int)DB::fetchValue(
            "SELECT COUNT(*) FROM qc_piece_items WHERE qc_id = ? AND verdict = 'PENDING'",
            [$id]
        );
        if ($pendingPieces === 0 && in_array($terminalStatus, ['PASSED', 'PARTIAL', 'REJECTED', 'REWORK', 'COMPLETED', 'CANCELLED'], true)) {
            $this->flash('warning', "QC {$qc['qc_no']} has already been completed. Stock is not credited again.");
            $this->redirect("/production/qc/details?id={$id}");
            return;
        }
        if ($pendingPieces > 0) {
            $this->flash('error', 'Every garment must have a saved verdict before passed pieces can be added to stock.');
            $this->redirect("/production/qc/inspect?id={$id}");
            return;
        }

        $location = trim($_POST['location'] ?? ($qc['location'] ?: 'Main Store'));
        if ($location === 'Main Store (WH-01)' || empty($location)) {
            $location = 'Main Store';
        }

        DB::beginTransaction();
        try {
            $lockedQc = DB::fetch("SELECT status FROM qc_inspections WHERE id = ? FOR UPDATE", [$id]);
            if (!$lockedQc || in_array(strtoupper((string)$lockedQc['status']), ['PASSED', 'PARTIAL', 'REJECTED', 'REWORK', 'COMPLETED', 'CANCELLED'], true)) {
                DB::rollBack();
                $this->flash('warning', "QC {$qc['qc_no']} has already been completed. Stock is not credited again.");
                $this->redirect("/production/qc/details?id={$id}");
                return;
            }
            $lockedPendingPieces = (int)DB::fetchValue(
                "SELECT COUNT(*) FROM qc_piece_items WHERE qc_id = ? AND verdict = 'PENDING'",
                [$id]
            );
            if ($lockedPendingPieces > 0) {
                DB::rollBack();
                $this->flash('error', 'Every garment must have a saved verdict before passed pieces can be added to stock.');
                $this->redirect("/production/qc/inspect?id={$id}");
                return;
            }

            // Group pieces by variant and verdict
            $items = DB::fetchAll("SELECT * FROM qc_inspection_items WHERE qc_id = ?", [$id]);
            $totalPassed = 0;
            $totalRejected = 0;
            $totalRework = 0;

            foreach ($items as $it) {
                $pCounts = DB::fetch("
                    SELECT 
                        SUM(CASE WHEN verdict = 'PASSED' THEN 1 ELSE 0 END) AS passed,
                        SUM(CASE WHEN verdict = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
                        SUM(CASE WHEN verdict = 'REWORK' THEN 1 ELSE 0 END) AS rework
                    FROM qc_piece_items WHERE qc_item_id = ?
                ", [$it['id']]);

                $pPassed = (int)($pCounts['passed'] ?? 0);
                $pRejected = (int)($pCounts['rejected'] ?? 0);
                $pRework = (int)($pCounts['rework'] ?? 0);

                DB::query("
                    UPDATE qc_inspection_items 
                    SET passed_qty = ?, rejected_qty = ?, rework_qty = ?
                    WHERE id = ?
                ", [$pPassed, $pRejected, $pRework, $it['id']]);

                $totalPassed += $pPassed;
                $totalRejected += $pRejected;
                $totalRework += $pRework;

                // Credit passed pieces to Live Stocks!
                if ($pPassed > 0 && !empty($it['variant_id'])) {
                    $this->inwardVariantToStock(
                        (int)$it['variant_id'],
                        (float)$pPassed,
                        $cid,
                        $id,
                        $qc['qc_no'],
                        $qc['batch_no'] ?: 'STCH',
                        $location,
                        (int)($qc['program_id'] ?: $qc['sl_prog_id']),
                        (int)$qc['stitching_batch_id'],
                        $qc['inspector_name'] ?? 'System Administrator'
                    );
                }
            }

            // Determine status
            if ($totalRework > 0) {
                $newStatus = 'REWORK';
            } elseif ($totalRejected > 0 && $totalPassed > 0) {
                $newStatus = 'PARTIAL';
            } elseif ($totalRejected > 0 && $totalPassed == 0) {
                $newStatus = 'REJECTED';
            } else {
                $newStatus = 'PASSED';
            }

            DB::query("
                UPDATE qc_inspections 
                SET passed_qty = ?, rejected_qty = ?, rework_qty = ?, status = ?, location = ?
                WHERE id = ?
            ", [$totalPassed, $totalRejected, $totalRework, $newStatus, $location, $id]);

            if ($qc['stitching_batch_id']) {
                DB::query("UPDATE stitching_logs SET inward_status = 'completed' WHERE id = ?", [$qc['stitching_batch_id']]);
            }

            DB::commit();
            $this->flash('success', "QC Inspection {$qc['qc_no']} completed! {$totalPassed} pcs passed and added to Live Stock ({$totalRejected} rejected, {$totalRework} rework).");
            $this->redirect("/production/qc/details?id={$id}");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to complete QC inspection: ' . $e->getMessage());
            $this->redirect("/production/qc/inspect?id={$id}");
        }
    }

    /**
     * Read-Only QC Details View (Image 2: /production/qc/details?id=X)
     */
    public function viewQc(): void {
        $this->ensureQcSchema();
        $cid = Auth::concernId();
        if (!$cid && !Auth::isSuperAdmin()) {
            $this->flash('error', 'Select a concern to view this QC inspection.');
            $this->redirect('/production/qc');
            return;
        }
        $id = (int)($_GET['id'] ?? 0);

        $qc = DB::fetch("
            SELECT qi.*, 
                   v.name AS variant_name, v.product_code, v.size,
                   sl.batch_no AS stitching_batch_no, sl.stitching_name,
                   pp.program_no, pp.program_name,
                   cat.name AS catalog_name, cat.hierarchy_path,
                   u.name AS inspector_name,
                   con.name AS concern_name, con.code AS concern_code
            FROM qc_inspections qi
            LEFT JOIN variants v ON qi.variant_id = v.id
            LEFT JOIN stitching_logs sl ON qi.stitching_batch_id = sl.id
            LEFT JOIN production_programs pp ON (qi.program_id = pp.id OR sl.program_id = pp.id OR pp.target_variant_id = v.id)
            LEFT JOIN catalogs cat ON pp.catalog_id = cat.id
            LEFT JOIN users u ON qi.inspector_id = u.id
            LEFT JOIN concerns con ON qi.concern_id = con.id
            WHERE qi.id = ?" . ($cid ? " AND (qi.concern_id = ? OR qi.concern_id IS NULL)" : ""),
            $cid ? [$id, $cid] : [$id]
        );

        if (!$qc) {
            $this->flash('error', 'QC inspection record not found.');
            $this->redirect('/production/qc');
            return;
        }

        $pieceCounts = DB::fetch("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN verdict = 'PASSED' THEN 1 ELSE 0 END) AS passed,
                SUM(CASE WHEN verdict = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN verdict = 'REWORK' THEN 1 ELSE 0 END) AS rework
            FROM qc_piece_items WHERE qc_id = ?
        ", [$id]);
        if (!empty($pieceCounts['total'])) {
            $qc['passed_qty'] = (float)$pieceCounts['passed'];
            $qc['rejected_qty'] = (float)$pieceCounts['rejected'];
            $qc['rework_qty'] = (float)$pieceCounts['rework'];
        }

        $items = DB::fetchAll("
            SELECT qii.*, cat.name AS catalog_name, cat.hierarchy_path
            FROM qc_inspection_items qii
            LEFT JOIN catalogs cat ON qii.catalog_id = cat.id
            WHERE qii.qc_id = ?
            ORDER BY qii.id ASC
        ", [$id]);

        $this->render('concern/qc_details', [
            'pageTitle'     => "QC Inspection Details — {$qc['qc_no']}",
            'currentModule' => 'qc_stocks',
            'qc'            => $qc,
            'items'         => $items,
            'csrfToken'     => Auth::csrfToken(),
        ]);
    }

    /**
     * Re-inspect Rework Garments and credit newly passed pieces to stock
     */
    public function reinspectQc(): void {
        $this->ensureQcSchema();
        $this->validateCsrf();
        $cid = Auth::concernId() ?: 1;
        $id = (int)($_POST['id'] ?? 0);

        $qc = DB::fetch("
            SELECT qi.*, sl.batch_no AS stitching_batch_no, u.name AS inspector_name
            FROM qc_inspections qi
            LEFT JOIN stitching_logs sl ON qi.stitching_batch_id = sl.id
            LEFT JOIN users u ON qi.inspector_id = u.id
            WHERE qi.id = ?", [$id]
        );

        if (!$qc) {
            $this->flash('error', 'QC inspection record not found.');
            $this->redirect('/production/qc');
            return;
        }

        $pieceCounts = DB::fetch("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN verdict = 'PASSED' THEN 1 ELSE 0 END) AS passed,
                SUM(CASE WHEN verdict = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN verdict = 'REWORK' THEN 1 ELSE 0 END) AS rework
            FROM qc_piece_items WHERE qc_id = ?
        ", [$id]);
        if (!empty($pieceCounts['total'])) {
            $qc['passed_qty'] = (float)$pieceCounts['passed'];
            $qc['rejected_qty'] = (float)$pieceCounts['rejected'];
            $qc['rework_qty'] = (float)$pieceCounts['rework'];
        }

        $passedAdd   = max(0, (float)($_POST['passed_add'] ?? 0));
        $rejectedAdd = max(0, (float)($_POST['rejected_add'] ?? 0));
        $notes       = trim($_POST['rework_notes'] ?? '');
        $reworkQty   = (float)$qc['rework_qty'];
        $variantId   = (int)($_POST['variant_id'] ?? $qc['variant_id']);

        if ($passedAdd + $rejectedAdd <= 0) {
            $this->flash('error', 'Please specify how many rework pieces were repaired and passed or rejected.');
            $this->redirect('/production/qc/details?id=' . $id);
            return;
        }

        if (($passedAdd + $rejectedAdd) > $reworkQty) {
            $this->flash('error', "Cannot cycle {$passedAdd} + {$rejectedAdd} pieces; only {$reworkQty} pieces are currently in rework.");
            $this->redirect('/production/qc/details?id=' . $id);
            return;
        }

        DB::beginTransaction();
        try {
            $newPassed   = (float)$qc['passed_qty'] + $passedAdd;
            $newRejected = (float)$qc['rejected_qty'] + $rejectedAdd;
            $newRework   = max(0.00, $reworkQty - ($passedAdd + $rejectedAdd));
            $newStatus   = ($newRework > 0) ? 'REWORK' : ($newRejected > 0 && $newPassed > 0 ? 'PARTIAL' : 'PASSED');

            $reworkLog = date('Y-m-d H:i') . " Re-inspected: +{$passedAdd} passed to stock, +{$rejectedAdd} rejected. " . ($notes ? "Notes: {$notes}" : "");
            $existingLog = trim($qc['rework_notes'] ?? '');
            $updatedLog = $existingLog ? ($existingLog . "\n" . $reworkLog) : $reworkLog;

            DB::query(
                "UPDATE qc_inspections SET passed_qty = ?, rework_qty = ?, rejected_qty = ?, rework_notes = ?, status = ? WHERE id = ?",
                [$newPassed, $newRework, $newRejected, $updatedLog, $newStatus, $id]
            );

            // Credit newly passed garments to stock immediately!
            if ($passedAdd > 0 && $variantId > 0) {
                $this->inwardVariantToStock(
                    $variantId,
                    $passedAdd,
                    $cid,
                    $id,
                    $qc['qc_no'],
                    $qc['stitching_batch_no'] ?: 'REWORK',
                    $qc['location'] ?: 'Main Store',
                    (int)$qc['program_id'],
                    (int)$qc['stitching_batch_id'],
                    $qc['inspector_name'] ?? 'System Administrator'
                );
            }

            DB::commit();
            $this->flash('success', "Re-inspection complete! +{$passedAdd} repaired pieces passed & credited directly to Live Stock. Remaining rework: {$newRework}.");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', 'Re-inspection failed: ' . $e->getMessage());
        }

        $this->redirect('/production/qc/details?id=' . $id);
    }

    public function updateQc(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $qc = DB::fetch("SELECT * FROM qc_inspections WHERE id = ?", [$id]);
        if (!$qc) {
            $this->flash('error', 'QC inspection not found.');
            $this->redirect('/production/qc');
            return;
        }

        $inspectedQty = (float)($_POST['inspected_qty'] ?? $qc['inspected_qty']);
        $newPassed    = (float)($_POST['passed_qty'] ?? $qc['passed_qty']);
        $reworkQty    = (float)($_POST['rework_qty'] ?? $qc['rework_qty']);
        $rejectedQty  = (float)($_POST['rejected_qty'] ?? $qc['rejected_qty']);
        $location     = trim($_POST['location'] ?? $qc['location']);
        $defectNotes  = trim($_POST['defect_notes'] ?? $qc['defect_notes']);

        $status = ($reworkQty > 0) ? 'REWORK' : (($rejectedQty > 0 && $newPassed == 0) ? 'REJECTED' : ($rejectedQty > 0 ? 'PARTIAL' : 'PASSED'));

        DB::beginTransaction();
        try {
            DB::query(
                "UPDATE qc_inspections SET inspected_qty = ?, passed_qty = ?, rework_qty = ?, rejected_qty = ?, location = ?, defect_notes = ?, status = ? WHERE id = ?",
                [$inspectedQty, $newPassed, $reworkQty, $rejectedQty, $location, $defectNotes, $status, $id]
            );

            DB::commit();
            $this->flash('success', "QC Inspection '{$qc['qc_no']}' updated successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to update QC inspection: ' . $e->getMessage());
        }

        $this->redirect('/production/qc');
    }

    public function deleteQc(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $qc = DB::fetch("SELECT * FROM qc_inspections WHERE id = ?", [$id]);
        if (!$qc) {
            $this->flash('error', 'QC inspection not found.');
            $this->redirect('/production/qc');
            return;
        }

        DB::beginTransaction();
        try {
            DB::query("DELETE FROM qc_piece_items WHERE qc_id = ?", [$id]);
            DB::query("DELETE FROM qc_inspection_items WHERE qc_id = ?", [$id]);
            DB::query("DELETE FROM qc_inspections WHERE id = ?", [$id]);
            DB::commit();
            $this->flash('success', "QC Inspection '{$qc['qc_no']}' deleted successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to delete QC inspection: ' . $e->getMessage());
        }

        $this->redirect('/production/qc');
    }

    public function exportCatalogProgram(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT pp.*, c.name AS catalog_name, v.name AS target_variant_name, v.product_code,
                       con.name AS concern_name, con.code AS concern_code
                FROM production_programs pp
                LEFT JOIN catalogs c ON pp.catalog_id = c.id
                JOIN variants v ON pp.target_variant_id = v.id
                LEFT JOIN concerns con ON pp.concern_id = con.id";
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "pp.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(pp.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY pp.id DESC";
        $programs = DB::fetchAll($sql, $params);

        $headers = [
            'Program No', 'Catalog / Style', 'Product Code', 'Target Product', 
            'Planned Qty', 'Produced Qty', 'Wastage Qty', 'Status', 'Start Date', 'End Date', 'Concern'
        ];

        $rows = [];
        $totPlanned = 0; $totProd = 0; $totWaste = 0;
        foreach ($programs as $p) {
            $planned = (int)($p['planned_qty'] ?? 0);
            $produced = (int)($p['produced_qty'] ?? 0);
            $waste = (int)($p['wastage_qty'] ?? 0);
            $totPlanned += $planned;
            $totProd += $produced;
            $totWaste += $waste;

            $endDate = $p['end_date'] ?? $p['target_date'] ?? null;
            $rows[] = [
                $p['program_no'],
                $p['catalog_name'] ?: 'Custom Production',
                $p['product_code'],
                $p['target_variant_name'],
                $planned,
                $produced,
                $waste,
                strtoupper($p['status']),
                $p['start_date'] ? date('d M Y', strtotime($p['start_date'])) : '-',
                $endDate ? date('d M Y', strtotime($endDate)) : '-',
                $p['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total Programs: ' . count($programs), '', '', '', $totPlanned, $totProd, $totWaste, '', '', '', ''];
        $metadata = [
            'Report Title' => 'Production Catalogs & Scheduling Programs',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Programs' => count($programs),
            'Total Planned Output' => number_format($totPlanned)
        ];

        $filename = 'production_programs_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Production Scheduling Programs Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Production Scheduling Programs Report', $metadata, $headers, $rows, $totals);
        }
    }

    public function exportCutting(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT cl.*, pp.program_no, v.name AS raw_variant_name, v.product_code,
                       con.name AS concern_name, con.code AS concern_code
                FROM cutting_logs cl
                LEFT JOIN production_programs pp ON cl.program_id = pp.id
                JOIN variants v ON cl.raw_variant_id = v.id
                LEFT JOIN concerns con ON cl.concern_id = con.id";
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "cl.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(cl.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY cl.id DESC";
        $logs = DB::fetchAll($sql, $params);

        $headers = [
            'Log No', 'Date', 'Program Ref', 'Raw Fabric / Variant', 'Product Code', 
            'Input Fabric (Mtr/Kg)', 'Cut Pieces', 'Wastage (Kg)', 'Wastage %', 'Notes', 'Concern'
        ];

        $rows = [];
        $totInput = 0; $totCut = 0; $totWaste = 0;
        foreach ($logs as $l) {
            $inp = (float)$l['input_fabric_qty'];
            $cut = (int)$l['cut_pieces'];
            $wst = (float)$l['wastage_fabric_qty'];
            $pct = $inp > 0 ? round(($wst / $inp) * 100, 1) . '%' : '0%';

            $totInput += $inp; $totCut += $cut; $totWaste += $wst;

            $rows[] = [
                $l['log_no'],
                date('d M Y, h:i A', strtotime($l['created_at'])),
                $l['program_no'] ?: 'Direct',
                $l['raw_variant_name'],
                $l['product_code'],
                $inp,
                $cut,
                $wst,
                $pct,
                $l['notes'] ?: '-',
                $l['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total Logs: ' . count($logs), '', '', '', '', $totInput, $totCut, $totWaste, '', '', ''];
        $metadata = [
            'Report Title' => 'Fabric Cutting Operations & Material Wastage Audit',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Cutting Logs' => count($logs),
            'Total Cut Pieces' => number_format($totCut),
            'Total Fabric Input' => number_format($totInput, 2)
        ];

        $filename = 'cutting_wastage_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Cutting & Wastage Log Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Cutting & Wastage Log Report', $metadata, $headers, $rows, $totals);
        }
    }

    public function exportStitching(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT sl.*, pp.program_no, v.name AS produced_variant_name, v.product_code,
                       con.name AS concern_name, con.code AS concern_code
                FROM stitching_logs sl
                LEFT JOIN production_programs pp ON sl.program_id = pp.id
                JOIN variants v ON sl.produced_variant_id = v.id
                LEFT JOIN concerns con ON sl.concern_id = con.id";
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
        $logs = DB::fetchAll($sql, $params);

        $headers = [
            'Log No', 'Date', 'Program Ref', 'Finished Variant', 'Product Code', 
            'Input Panels', 'Stitched Pcs', 'Defective Pcs', 'Defect %', 'Notes', 'Concern'
        ];

        $rows = [];
        $totIn = 0; $totStitch = 0; $totWaste = 0;
        foreach ($logs as $l) {
            $in = (int)$l['input_pieces'];
            $st = (int)$l['stitched_pieces'];
            $ws = (int)$l['wastage_pieces'];
            $pct = $in > 0 ? round(($ws / $in) * 100, 1) . '%' : '0%';

            $totIn += $in; $totStitch += $st; $totWaste += $ws;

            $rows[] = [
                $l['log_no'],
                date('d M Y, h:i A', strtotime($l['created_at'])),
                $l['program_no'] ?: 'Direct',
                $l['produced_variant_name'],
                $l['product_code'],
                $in,
                $st,
                $ws,
                $pct,
                $l['notes'] ?: '-',
                $l['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total Records: ' . count($logs), '', '', '', '', $totIn, $totStitch, $totWaste, '', '', ''];
        $metadata = [
            'Report Title' => 'Garment Stitching Production & Defect Tracking',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Stitching Logs' => count($logs),
            'Total Finished Garments' => number_format($totStitch)
        ];

        $filename = 'stitching_wastage_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Stitching Production & Defect Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Stitching Production & Defect Report', $metadata, $headers, $rows, $totals);
        }
    }

    public function exportQc(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT qc.*, v.name AS variant_name, v.product_code, v.size, u.name AS inspector_name,
                       con.name AS concern_name, con.code AS concern_code
                FROM qc_inspections qc
                JOIN variants v ON qc.variant_id = v.id
                LEFT JOIN concerns con ON qc.concern_id = con.id
                LEFT JOIN users u ON qc.inspector_id = u.id";
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "qc.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(qc.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY qc.id DESC";
        $inspections = DB::fetchAll($sql, $params);

        $headers = [
            'QC No', 'Date', 'Product Code', 'Variant Name', 'Size', 
            'Inspected Qty', 'Passed Qty', 'Rejected Qty', 'Pass Rate %', 'Location', 'Inspector', 'Concern'
        ];

        $rows = [];
        $totInsp = 0; $totPass = 0; $totRej = 0;
        foreach ($inspections as $q) {
            $ins = (float)$q['inspected_qty'];
            $pas = (float)$q['passed_qty'];
            $rej = (float)$q['rejected_qty'];
            $rate = $ins > 0 ? round(($pas / $ins) * 100, 1) . '%' : '100%';

            $totInsp += $ins; $totPass += $pas; $totRej += $rej;

            $rows[] = [
                $q['qc_no'],
                date('d M Y, h:i A', strtotime($q['created_at'])),
                $q['product_code'],
                $q['variant_name'],
                $q['size'] ?? '-',
                $ins,
                $pas,
                $rej,
                $rate,
                $q['location'] ?? 'QC Department',
                $q['inspector_name'] ?? 'QC Officer',
                $q['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total Inspections: ' . count($inspections), '', '', '', '', $totInsp, $totPass, $totRej, '', '', '', ''];
        $metadata = [
            'Report Title' => 'Quality Control (QC) & Stock Approval Audit',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Inspections' => count($inspections),
            'Total Passed Units' => number_format($totPass, 2),
            'Total Rejected Units' => number_format($totRej, 2)
        ];

        $filename = 'qc_inspections_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('QC & Stock Approval Audit Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'QC & Stock Approval Audit Report', $metadata, $headers, $rows, $totals);
        }
    }
}
