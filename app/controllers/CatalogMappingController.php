<?php
// app/controllers/CatalogMappingController.php - Catalog Mapping Module for Finished Goods, 3-Tier Categories & Attributes

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Services\AuditLog;
use Exception;

class CatalogMappingController extends Controller
{
    private static bool $schemaEnsured = false;

    /**
     * Self-healing schema guard for catalog mapping tables and catalog columns
     */
    protected function ensureSchema(): void
    {
        if (self::$schemaEnsured)
            return;

        try {
            // 1. Ensure catalog_id on purchase_bill_items
            $pbiCols = array_column(DB::fetchAll('DESCRIBE `purchase_bill_items`'), 'Field');
            if (!in_array('catalog_id', $pbiCols)) {
                DB::query('ALTER TABLE `purchase_bill_items` ADD COLUMN `catalog_id` INT NULL AFTER `variant_id`');
            }

            // 2. Ensure the third-level category table used by catalog mapping queries
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

            // 3. Ensure the catalog fields used by mapping views and forms
            $catalogColumns = array_column(DB::fetchAll('DESCRIBE `catalogs`'), 'Field');
            $catalogColumnChanges = [
                'style_code' => 'ADD COLUMN style_code VARCHAR(50) NULL AFTER code',
                'short_code' => 'ADD COLUMN short_code VARCHAR(50) NULL AFTER style_code',
                'category_id' => 'ADD COLUMN category_id INT NULL AFTER short_code',
                'subcategory_id' => 'ADD COLUMN subcategory_id INT NULL AFTER category_id',
                'sub_sub_category_id' => 'ADD COLUMN sub_sub_category_id INT NULL AFTER subcategory_id',
                'main_category_code' => 'ADD COLUMN main_category_code VARCHAR(20) NULL AFTER sub_sub_category_id',
                'sub_category_code' => 'ADD COLUMN sub_category_code VARCHAR(20) NULL AFTER main_category_code',
                'sub_sub_category_code' => 'ADD COLUMN sub_sub_category_code VARCHAR(20) NULL AFTER sub_category_code',
                'hierarchy_path' => 'ADD COLUMN hierarchy_path VARCHAR(255) NULL AFTER sub_sub_category_code',
                'hsn_code' => 'ADD COLUMN hsn_code VARCHAR(50) NULL AFTER hierarchy_path',
                'gst_rate' => 'ADD COLUMN gst_rate DECIMAL(5,2) NULL DEFAULT NULL AFTER hsn_code',
                'sizes' => 'ADD COLUMN sizes TEXT NULL AFTER gst_rate',
                'status' => "ADD COLUMN status ENUM('active', 'inactive', 'in_house') NOT NULL DEFAULT 'in_house' AFTER sizes",
                'updated_at' => 'ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
            ];
            foreach ($catalogColumnChanges as $column => $definition) {
                if (!in_array($column, $catalogColumns, true)) {
                    DB::query("ALTER TABLE `catalogs` {$definition}");
                    $catalogColumns[] = $column;
                }
            }
            DB::query('ALTER TABLE `catalogs` MODIFY COLUMN gst_rate DECIMAL(5,2) NULL DEFAULT NULL');

            // 4. Ensure category fields used by hierarchy management
            $categoryColumns = array_column(DB::fetchAll('DESCRIBE `categories`'), 'Field');
            if (!in_array('code', $categoryColumns, true)) {
                DB::query('ALTER TABLE `categories` ADD COLUMN code VARCHAR(20) NULL AFTER name');
            }
            if (!in_array('default_hsn', $categoryColumns, true)) {
                DB::query('ALTER TABLE `categories` ADD COLUMN default_hsn VARCHAR(50) NULL AFTER code');
            }
            if (!in_array('default_gst_rate', $categoryColumns, true)) {
                DB::query('ALTER TABLE `categories` ADD COLUMN default_gst_rate DECIMAL(5,2) NULL DEFAULT NULL AFTER default_hsn');
            }
            DB::query('ALTER TABLE `categories` MODIFY COLUMN default_gst_rate DECIMAL(5,2) NULL DEFAULT NULL');

            $subcategoryColumns = array_column(DB::fetchAll('DESCRIBE `subcategories`'), 'Field');
            if (!in_array('code', $subcategoryColumns, true)) {
                DB::query('ALTER TABLE `subcategories` ADD COLUMN code VARCHAR(20) NULL AFTER name');
            }

            // 5. Ensure variant fields used when creating catalog size variants
            $variantColumns = array_column(DB::fetchAll('DESCRIBE `variants`'), 'Field');
            if (!in_array('catalog_id', $variantColumns, true)) {
                DB::query('ALTER TABLE `variants` ADD COLUMN catalog_id INT NULL AFTER concern_id');
                DB::query('ALTER TABLE `variants` ADD INDEX idx_var_catalog (catalog_id)');
            }
            DB::query('ALTER TABLE `variants` MODIFY COLUMN subcategory_id INT NULL');

            // 6. Ensure catalog_mappings table
            DB::query("CREATE TABLE IF NOT EXISTS `catalog_mappings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `concern_id` INT NOT NULL,
                `purchase_bill_item_id` INT NOT NULL,
                `catalog_id` INT NOT NULL,
                `variant_id` INT NULL,
                `category_id` INT NULL,
                `subcategory_id` INT NULL,
                `sub_sub_category_id` INT NULL,
                `selected_sizes` TEXT NULL,
                `selected_color` VARCHAR(100) NULL,
                `status` ENUM('mapped', 'unmapped') NOT NULL DEFAULT 'mapped',
                `notes` TEXT NULL,
                `created_by` INT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_cmap_concern` (`concern_id`),
                INDEX `idx_cmap_pbi` (`purchase_bill_item_id`),
                INDEX `idx_cmap_catalog` (`catalog_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 7. Ensure product_attributes table
            DB::query("CREATE TABLE IF NOT EXISTS `product_attributes` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `concern_id` INT NOT NULL,
                `attribute_type` VARCHAR(50) NOT NULL DEFAULT 'size',
                `attribute_name` VARCHAR(100) NOT NULL,
                `attribute_value` VARCHAR(100) NOT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_pattr_concern` (`concern_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 8. Ensure module registered in `modules`
            $existMod = DB::fetch("SELECT id FROM `modules` WHERE `code` = 'catalog_mapping'");
            if (!$existMod) {
                DB::query(
                    "INSERT INTO `modules` (`code`, `name`, `icon`, `sort_order`, `group_name`, `is_super_admin_only`)
                     VALUES ('catalog_mapping', 'Catalog Hierarchy', 'bi-diagram-3-fill', 24, 'Purchase', 0)"
                );
            } else {
                DB::query(
                    "UPDATE `modules` SET `sort_order` = 24, `group_name` = 'Purchase', `icon` = 'bi-diagram-3-fill' WHERE id = ?",
                    [(int) $existMod['id']]
                );
            }

            self::$schemaEnsured = true;
        } catch (\Throwable $e) {
            error_log('CatalogMapping schema check error: ' . $e->getMessage());
        }
    }

    /**
     * Main View: 3-Tier Category Hierarchy, Product Attributes & HSN Master
     */
    public function index(): void
    {
        $this->ensureSchema();
        $cid = Auth::concernId();

        // Load 3-Tier Hierarchy, Attributes & HSN Master
        if ($cid) {
            $categories = DB::fetchAll('SELECT * FROM categories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) ORDER BY name ASC', [$cid]);
            $subcategories = DB::fetchAll('SELECT s.*, c.name AS category_name FROM subcategories s JOIN categories c ON s.category_id = c.id WHERE (s.concern_id = ? OR s.concern_id = 1 OR s.concern_id IS NULL) ORDER BY s.name ASC', [$cid]);
            $subSubcategories = DB::fetchAll('SELECT ss.*, s.name AS subcategory_name, c.name AS category_name FROM sub_subcategories ss LEFT JOIN subcategories s ON ss.subcategory_id = s.id LEFT JOIN categories c ON s.category_id = c.id WHERE (ss.concern_id = ? OR ss.concern_id = 1 OR ss.concern_id IS NULL) ORDER BY ss.name ASC', [$cid]);
            $attributes = DB::fetchAll('SELECT * FROM product_attributes WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) ORDER BY attribute_type ASC, sort_order ASC', [$cid]);
        } else {
            $categories = DB::fetchAll('SELECT * FROM categories ORDER BY name ASC');
            $subcategories = DB::fetchAll('SELECT s.*, c.name AS category_name FROM subcategories s JOIN categories c ON s.category_id = c.id ORDER BY s.name ASC');
            $subSubcategories = DB::fetchAll('SELECT ss.*, s.name AS subcategory_name, c.name AS category_name FROM sub_subcategories ss LEFT JOIN subcategories s ON ss.subcategory_id = s.id LEFT JOIN categories c ON s.category_id = c.id ORDER BY ss.name ASC');
            $attributes = DB::fetchAll('SELECT * FROM product_attributes ORDER BY attribute_type ASC, sort_order ASC');
        }

        $hsnSql = "SELECT hm.id, hm.concern_id, hm.code_type, hm.hsn_code, hm.description, hm.category, hm.gst_rate, hm.rate_note, hm.is_active,
                          COALESCE(con.name, 'Global') AS concern_name
                   FROM hsn_masters hm
                   LEFT JOIN concerns con ON con.id = hm.concern_id
                   WHERE hm.is_active = 1 AND hm.code_type = 'HSN'";
        $hsnParams = [];
        if ($cid) {
            $hsnSql .= ' AND (hm.concern_id = ? OR hm.concern_id IS NULL)';
            $hsnParams[] = $cid;
        }
        $hsnSql .= ' ORDER BY hm.hsn_code ASC, hm.concern_id IS NULL DESC, hm.concern_id ASC';
        $hsnCodes = DB::fetchAll($hsnSql, $hsnParams);

        $hsnCategories = array_values(array_filter(array_unique(array_column($hsnCodes, 'category'))));
        sort($hsnCategories);

        $attributeTypes = array_values(array_unique(array_filter(array_column($attributes, 'attribute_type'))));
        sort($attributeTypes);

        $variantDetails = \App\Services\VariantService::getVariantDetailsList($cid);

        $this->render('concern/catalog_mapping', [
            'pageTitle' => 'Catalog Hierarchy',
            'currentModule' => 'catalog_mapping',
            'categories' => $categories,
            'subcategories' => $subcategories,
            'subSubcategories' => $subSubcategories,
            'attributes' => $attributes,
            'hsnCodes' => $hsnCodes,
            'hsnCategories' => $hsnCategories,
            'attributeTypes' => $attributeTypes,
            'variantDetails' => $variantDetails,
            'selectedCid' => $cid,
            'allConcerns' => Auth::isSuperAdmin() ? Auth::allConcerns() : [],
        ]);
    }

    /**
     * Map Finished Good Item Form
     */
    public function mapItemForm(): void
    {
        $this->ensureSchema();
        $cid = Auth::concernId();
        $itemId = (int) ($_GET['id'] ?? 0);

        if (!$itemId) {
            $this->flash('error', 'Invalid Finished Good Item ID.');
            $this->redirect('/production/catalog-mapping');
        }

        $sql = 'SELECT pbi.*, 
                       pb.bill_no, pb.bill_date, pb.supplier_id, pb.supplier_invoice_no, pb.payment_status, pb.inward_status AS bill_inward_status,
                       pb.payment_method, pb.paid_amount, pb.total_amount AS bill_total_amount, pb.tax_amount AS bill_tax_amount,
                       s.name AS supplier_name, s.phone AS supplier_phone, s.email AS supplier_email, s.gstin AS supplier_gstin, s.city AS supplier_city, s.state AS supplier_state, s.address AS supplier_address,
                       cm.id AS mapping_id, COALESCE(cm.catalog_id, pbi.catalog_id) AS mapped_catalog_id, cm.selected_sizes, cm.selected_color,
                       c.name AS catalog_name, c.style_code AS catalog_style_code, c.short_code AS catalog_short_code,
                       c.hierarchy_path, c.sizes AS catalog_sizes, c.hsn_code AS catalog_hsn_code, c.gst_rate AS catalog_gst_rate,
                       c.main_category_code AS mapped_main_category_code, c.sub_category_code AS mapped_sub_category_code,
                       c.sub_sub_category_code AS mapped_sub_sub_category_code,
                       c.category_id AS mapped_cat_id, c.subcategory_id AS mapped_subcat_id, c.sub_sub_category_id AS mapped_subsub_id
                FROM purchase_bill_items pbi
                JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                LEFT JOIN suppliers s ON pb.supplier_id = s.id
                LEFT JOIN catalog_mappings cm ON cm.purchase_bill_item_id = pbi.id
                LEFT JOIN catalogs c ON COALESCE(cm.catalog_id, pbi.catalog_id) = c.id
                WHERE pbi.id = ?' . ($cid ? ' AND pbi.concern_id = ?' : '');
        $item = DB::fetch($sql, $cid ? [$itemId, $cid] : [$itemId]);

        if (!$item) {
            $this->flash('error', 'Finished good item not found or access denied.');
            $this->redirect('/production/catalog-mapping');
        }

        $isMapped = (!empty($item['mapping_id']) && $item['mapping_status'] === 'mapped') || (!empty($item['catalog_id']) && !empty($item['variant_id'])) || !empty($item['mapped_catalog_id']);
        if ($isMapped && !Auth::can('catalog_mapping', 'edit')) {
            $this->flash('error', 'You do not have permission to edit catalog mappings.');
            $this->redirect('/production/catalog-mapping');
        }
        if (!$isMapped && !Auth::can('catalog_mapping', 'add')) {
            $this->flash('error', 'You do not have permission to create catalog mappings.');
            $this->redirect('/production/catalog-mapping');
        }

        $cid = $cid ?: (int) ($item['concern_id'] ?? 1);

        $existingVariantRows = [];
        $catalogId = (int) ($item['mapped_catalog_id'] ?? 0);
        if ($catalogId > 0) {
            $savedVariants = DB::fetchAll(
                "SELECT product_code, name, size, weight, price, mrp, attributes
                 FROM variants
                 WHERE concern_id = ? AND catalog_id = ? AND status = 'active'
                 ORDER BY id ASC",
                [$cid, $catalogId]
            );
            foreach ($savedVariants as $variant) {
                $variantAttributes = json_decode((string) ($variant['attributes'] ?? ''), true);
                $variantAttributes = is_array($variantAttributes) ? $variantAttributes : [];
                $normalizedCustomAttributes = [];
                foreach ($variantAttributes as $type => $value) {
                    $type = strtolower(trim((string) $type));
                    if (
                        $type === '' ||
                        in_array($type, ['color', 'size', 'hsn', 'weight', 'allocated_qty', 'custom_attributes'], true) ||
                        !is_scalar($value)
                    ) {
                        continue;
                    }
                    $normalizedCustomAttributes[$type] = trim((string) $value);
                }
                $customAttributes = $variantAttributes['custom_attributes'] ?? [];
                $customAttributes = is_array($customAttributes) ? $customAttributes : [];
                foreach ($customAttributes as $type => $value) {
                    $type = strtolower(trim((string) $type));
                    if (!is_scalar($value)) {
                        continue;
                    }
                    $value = trim((string) $value);
                    if ($type !== '' && $value !== '') {
                        $normalizedCustomAttributes[$type] = $value;
                    }
                }
                $existingVariantRows[] = [
                    'sku' => (string) ($variant['product_code'] ?? ''),
                    'name' => (string) ($variant['name'] ?? ''),
                    'size' => (string) ($variant['size'] ?? ''),
                    'color' => (string) ($variantAttributes['color'] ?? ''),
                    'weight' => (string) ($variant['weight'] ?? ''),
                    'hsn' => (string) ($variantAttributes['hsn'] ?? ''),
                    'mrp' => (string) ($variant['mrp'] ?? ''),
                    'selling_price' => (string) ($variant['price'] ?? ''),
                    'qty' => isset($variantAttributes['allocated_qty']) ? (float) $variantAttributes['allocated_qty'] : null,
                    'custom_attributes' => $normalizedCustomAttributes,
                ];
            }
        }

        // Use values already entered on the purchase item; do not infer preset colors.
        $resolvedColor = !empty($item['selected_color'])
            ? $item['selected_color']
            : (!empty($item['color'])
                ? $item['color']
                : ($item['specification'] ?? ''));

        // Generate suggested style code: GRM-STY-XXXX
        $styleCount = (int) DB::fetchValue('SELECT COUNT(*) FROM catalogs WHERE concern_id = ?', [$cid]) + 1;
        $conCode = Auth::concern()['code'] ?? 'GRM';
        $suggestedStyleCode = sprintf('%s-STY-%04d', $conCode, $styleCount);

        // Fetch Categories & Attributes
        $categories = $cid ? DB::fetchAll("SELECT * FROM categories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND status = 'active' ORDER BY name ASC", [$cid]) : DB::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC");
        $subcategories = $cid ? DB::fetchAll("SELECT * FROM subcategories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND status = 'active' ORDER BY name ASC", [$cid]) : DB::fetchAll("SELECT * FROM subcategories WHERE status = 'active' ORDER BY name ASC");
        $subSubcategories = $cid ? DB::fetchAll('SELECT * FROM sub_subcategories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) ORDER BY name ASC', [$cid]) : DB::fetchAll('SELECT * FROM sub_subcategories ORDER BY name ASC');
        $attributes = $cid ? DB::fetchAll('SELECT * FROM product_attributes WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) ORDER BY attribute_type ASC, sort_order ASC', [$cid]) : DB::fetchAll('SELECT * FROM product_attributes ORDER BY attribute_type ASC, sort_order ASC');
        $allSizes = [];
        foreach ($attributes as $attribute) {
            if (strtolower(trim($attribute['attribute_type'] ?? '')) !== 'size') {
                continue;
            }
            foreach (preg_split('/[,;|]+/', (string) ($attribute['attribute_value'] ?? '')) as $size) {
                $size = trim($size);
                if ($size !== '' && !in_array($size, $allSizes, true)) {
                    $allSizes[] = $size;
                }
            }
        }
        $storedSizes = json_decode((string) ($item['selected_sizes'] ?? ''), true);
        if (!is_array($storedSizes)) {
            $storedSizes = preg_split('/[,;|]+/', (string) ($item['selected_sizes'] ?? ''));
        }
        $catalogSizes = json_decode((string) ($item['catalog_sizes'] ?? ''), true);
        if (!is_array($catalogSizes)) {
            $catalogSizes = preg_split('/[,;|]+/', (string) ($item['catalog_sizes'] ?? ''));
        }
        $selectedSizes = [];
        foreach (array_merge($storedSizes, $catalogSizes) as $size) {
            $size = trim((string) $size);
            if ($size !== '' && !in_array($size, $selectedSizes, true)) {
                $selectedSizes[] = $size;
            }
        }
        foreach ($existingVariantRows as $variant) {
            $size = trim($variant['size']);
            if ($size !== '' && !in_array($size, $selectedSizes, true)) {
                $selectedSizes[] = $size;
            }
        }
        foreach ($selectedSizes as $size) {
            if (!in_array($size, $allSizes, true)) {
                $allSizes[] = $size;
            }
        }

        // Extract distinct saved color attributes
        $colorAttributes = [];
        $colorAttributeKeys = [];
        foreach ($attributes as $a) {
            if (strtolower($a['attribute_type'] ?? '') === 'color') {
                $rawVals = preg_split('/[,;|]+/', (string) ($a['attribute_value'] ?? ''));
                foreach ($rawVals as $rv) {
                    $rv = trim($rv);
                    $key = strtolower($rv);
                    if ($rv !== '' && !isset($colorAttributeKeys[$key])) {
                        $colorAttributes[] = $rv;
                        $colorAttributeKeys[$key] = true;
                    }
                }
            }
        }
        $resolvedColors = [];
        foreach (preg_split('/[,;|]+/', (string) $resolvedColor) as $rawColor) {
            $rawColor = trim($rawColor);
            $key = strtolower($rawColor);
            if ($rawColor === '') {
                continue;
            }
            $resolvedColors[$key] = true;
            if (!isset($colorAttributeKeys[$key])) {
                $colorAttributes[] = $rawColor;
                $colorAttributeKeys[$key] = true;
            }
        }
        foreach ($existingVariantRows as $variant) {
            $color = trim($variant['color']);
            $key = strtolower($color);
            if ($color === '') {
                continue;
            }
            $resolvedColors[$key] = true;
            if (!isset($colorAttributeKeys[$key])) {
                $colorAttributes[] = $color;
                $colorAttributeKeys[$key] = true;
            }
        }

        // Group all other dynamic attribute types
        $otherAttributes = [];
        foreach ($attributes as $a) {
            $t = strtolower(trim($a['attribute_type'] ?? ''));
            if ($t === 'size' || $t === 'color' || $t === '')
                continue;
            $rawVals = preg_split('/[,;|]+/', (string) ($a['attribute_value'] ?? ''));
            foreach ($rawVals as $rv) {
                $rv = trim($rv);
                if ($rv !== '' && !in_array($rv, $otherAttributes[$t] ?? [], true)) {
                    $otherAttributes[$t][] = $rv;
                }
            }
        }
        $selectedOtherAttributes = [];
        foreach ($existingVariantRows as $variant) {
            foreach ($variant['custom_attributes'] as $type => $value) {
                if (!isset($otherAttributes[$type])) {
                    $otherAttributes[$type] = [];
                }
                if (!in_array($value, $otherAttributes[$type], true)) {
                    $otherAttributes[$type][] = $value;
                }
                $selectedOtherAttributes[$type][$value] = true;
            }
        }

        $this->render('concern/catalog_mapping_form', [
            'pageTitle' => 'Map Finished Good to Catalog: ' . $item['item_name'],
            'currentModule' => 'catalog_mapping',
            'item' => $item,
            'resolvedColor' => $resolvedColor,
            'suggestedStyleCode' => $suggestedStyleCode,
            'categories' => $categories,
            'subcategories' => $subcategories,
            'subSubcategories' => $subSubcategories,
            'sizes' => $allSizes,
            'colorAttributes' => $colorAttributes,
            'resolvedColors' => $resolvedColors,
            'otherAttributes' => $otherAttributes,
            'selectedSizes' => $selectedSizes,
            'selectedOtherAttributes' => $selectedOtherAttributes,
            'existingVariantRows' => $existingVariantRows,
            'selectedCid' => $cid,
        ]);
    }

    /**
     * Save Finished Good Catalog Mapping & Auto-create Catalog / Size Variants
     */
    public function saveMapping(): void
    {
        $this->ensureSchema();
        $this->validateCsrf();

        $itemId = (int) ($_POST['purchase_bill_item_id'] ?? 0);
        if (!$itemId) {
            $this->flash('error', 'Invalid Purchased Item ID.');
            $this->redirect('/production/catalog-mapping');
        }

        $userCid = Auth::concernId();
        $item = DB::fetch('SELECT * FROM purchase_bill_items WHERE id = ?', [$itemId]);
        if (!$item) {
            $this->flash('error', 'Finished good item not found.');
            $this->redirect('/production/catalog-mapping');
        }
        if ($userCid && (int) $item['concern_id'] !== (int) $userCid) {
            $this->flash('error', 'Finished good item not found or unauthorized.');
            $this->redirect('/production/catalog-mapping');
        }
        $cid = (int) ($item['concern_id'] ?? $userCid ?? 0);
        if ($cid <= 0 || !DB::fetch('SELECT id FROM concerns WHERE id = ?', [$cid])) {
            $this->flash('error', 'Finished good item has no valid concern for catalog mapping.');
            $this->redirect('/production/catalog-mapping');
        }

        $isMapped = (!empty($item['catalog_id']) && !empty($item['variant_id'])) || (bool)DB::fetchValue("SELECT id FROM catalog_mappings WHERE purchase_bill_item_id = ?", [$itemId]);
        if ($isMapped && !Auth::can('catalog_mapping', 'edit')) {
            $this->flash('error', 'You do not have permission to edit catalog mappings.');
            $this->redirect('/production/catalog-mapping');
        }
        if (!$isMapped && !Auth::can('catalog_mapping', 'add')) {
            $this->flash('error', 'You do not have permission to create catalog mappings.');
            $this->redirect('/production/catalog-mapping');
        }

        DB::beginTransaction();
        try {
            $catalogId = null;
            $primaryVariantId = null;
            $cleanSizes = [];
            $color = trim($_POST['color'] ?? ($item['color'] ?? ''));
            $mrp = (float) ($_POST['mrp'] ?? ($item['mrp'] ?? 0.0));
            $sellingPrice = (float) ($_POST['selling_price'] ?? ($item['selling_price'] ?? 0.0));

            // Create one catalog style for this purchase item.
            $styleCode = strtoupper(trim($_POST['style_code'] ?? ''));
            $styleName = trim($_POST['style_name'] ?? '');
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $subcategoryId = !empty($_POST['subcategory_id']) ? (int) $_POST['subcategory_id'] : null;
            $subSubcategoryId = !empty($_POST['sub_sub_category_id']) ? (int) $_POST['sub_sub_category_id'] : null;

            if (empty($styleCode)) {
                throw new Exception('Product Style Code is required.');
            }
            if (empty($styleName)) {
                throw new Exception('Product Style Name is required.');
            }
            if ($categoryId <= 0) {
                throw new Exception('Main Category is required.');
            }

            $mainCatCode = strtoupper(trim($_POST['main_category_code'] ?? ''));
            $subCatCode = strtoupper(trim($_POST['sub_category_code'] ?? ''));
            $subSubCatCode = strtoupper(trim($_POST['sub_sub_category_code'] ?? ''));
            $wholeCode = strtoupper(trim($_POST['whole_code'] ?? ''));
            if (empty($wholeCode)) {
                $wholeCode = $mainCatCode . $subCatCode . $subSubCatCode;
            }

            $hsnCode = trim($_POST['hsn_code'] ?? ($item['hsn_code'] ?? ''));
            $gstRateInput = trim((string)($_POST['gst_rate'] ?? ($item['gst_rate'] ?? '')));
            if (!preg_match('/^\d{4}(?:\d{2}){0,2}$/', $hsnCode)) {
                throw new Exception('Select a valid 4-, 6-, or 8-digit HSN code before saving this catalog mapping.');
            }
            if ($gstRateInput === '' || !is_numeric($gstRateInput) || (float)$gstRateInput < 0 || (float)$gstRateInput > 100) {
                throw new Exception('Enter the verified GST rate for the selected HSN code.');
            }
            $gstRate = (float)$gstRateInput;
            $mrp = (float) ($_POST['mrp'] ?? 0.0);
            $sellingPrice = (float) ($_POST['selling_price'] ?? 0.0);

            // Sizes
            $rawSizes = $_POST['sizes'] ?? [];
            if (is_array($rawSizes)) {
                foreach ($rawSizes as $s) {
                    $val = trim($s);
                    if ($val !== '' && !in_array($val, $cleanSizes)) {
                        $cleanSizes[] = $val;
                    }
                }
            }
            if (empty($cleanSizes)) {
                throw new Exception('Select at least one size attribute before saving the catalog mapping.');
            }

            // Construct hierarchy path
            $cat = DB::fetch('SELECT name FROM categories WHERE id = ?', [$categoryId]);
            $pathParts = [$cat['name'] ?? ''];
            if ($subcategoryId) {
                $sub = DB::fetch('SELECT name FROM subcategories WHERE id = ?', [$subcategoryId]);
                if ($sub)
                    $pathParts[] = $sub['name'];
            }
            if ($subSubcategoryId) {
                $subsub = DB::fetch('SELECT name FROM sub_subcategories WHERE id = ?', [$subSubcategoryId]);
                if ($subsub)
                    $pathParts[] = $subsub['name'];
            }
            $hierarchyPath = implode(' > ', array_filter($pathParts));

            // Validate variant inputs before inserting or updating catalog
            $variantSkus = $_POST['variant_product_codes'] ?? [];
            $variantNames = $_POST['variant_names'] ?? [];
            $variantSizes = $_POST['variant_sizes'] ?? [];
            $variantColors = $_POST['variant_colors'] ?? [];
            $variantQtys = $_POST['variant_qtys'] ?? [];
            $variantWeights = $_POST['variant_weights'] ?? [];
            $variantHsns = $_POST['variant_hsns'] ?? [];
            $variantMrps = $_POST['variant_mrps'] ?? [];
            $variantSellingPrices = $_POST['variant_selling_prices'] ?? [];
            $variantAttributeTypes = $_POST['variant_attribute_types'] ?? [];
            $variantAttributeValues = $_POST['variant_attribute_values'] ?? [];
            if (!is_array($variantSkus) || empty($variantSkus) || !is_array($variantSizes) || !is_array($variantColors)) {
                throw new Exception('Select at least one size attribute to generate catalog variants.');
            }
            if (!is_array($variantAttributeTypes) || !is_array($variantAttributeValues)) {
                throw new Exception('Submitted variant attributes are invalid.');
            }

            $variantCustomAttributes = [];
            foreach ($variantSkus as $variantKey => $_skuInput) {
                $types = $variantAttributeTypes[$variantKey] ?? [];
                $values = $variantAttributeValues[$variantKey] ?? [];
                if (!is_array($types) || !is_array($values) || count($types) !== count($values)) {
                    throw new Exception('Submitted variant attributes are incomplete.');
                }
                $variantCustomAttributes[$variantKey] = [];
                foreach ($types as $index => $type) {
                    $type = strtolower(trim((string) $type));
                    $value = trim((string) ($values[$index] ?? ''));
                    if ($type === '' || $value === '') {
                        throw new Exception('Every selected variant attribute must have a value.');
                    }
                    $variantCustomAttributes[$variantKey][$type] = $value;
                }
            }

            // Strict Validation 1: Qty Validation - sum of variant quantities must equal purchased total qty
            $purchasedTotalQty = (float) ($item['qty'] ?? 0);
            $sumAllocatedQty = 0;
            if (!empty($variantQtys) && is_array($variantQtys)) {
                foreach ($variantQtys as $vQtyVal) {
                    $sumAllocatedQty += (float) $vQtyVal;
                }
            }

            if ($purchasedTotalQty > 0 && abs($sumAllocatedQty - $purchasedTotalQty) > 0.001) {
                $diff = $sumAllocatedQty - $purchasedTotalQty;
                $diffText = $diff > 0 ? "+{$diff} pieces exceeded" : "{$diff} pieces short";
                throw new Exception("Quantity Validation Error: Sum of all variant quantities ({$sumAllocatedQty}) must exactly equal the total purchased quantity ({$purchasedTotalQty}). It cannot be less than or greater than {$purchasedTotalQty} ({$diffText}).");
            }

            // Strict Validation 2: Pricing Hierarchy Validation - Selling Price > 0 and MRP > Selling Price
            foreach ($variantMrps as $vKey => $vMrpVal) {
                $varMrp = $vMrpVal !== '' ? (float) $vMrpVal : $mrp;
                $varPrice = isset($variantSellingPrices[$vKey]) && $variantSellingPrices[$vKey] !== '' ? (float) $variantSellingPrices[$vKey] : $sellingPrice;

                if ($varPrice <= 0) {
                    throw new Exception('Pricing Validation Error: Selling Price must be greater than zero.');
                }

                if ($varMrp > 0 && $varMrp <= $varPrice) {
                    throw new Exception('Pricing Validation Error: MRP (₹' . number_format($varMrp, 2) . ') must be strictly greater than Selling Price (₹' . number_format($varPrice, 2) . ').');
                }
            }

            $postedColors = $_POST['colors'] ?? [];
            if (!is_array($postedColors)) {
                throw new Exception('Submitted color attributes are invalid.');
            }
            $attributeValues = [
                'color' => array_merge(
                    $postedColors,
                    array_values($variantColors)
                ),
                'size' => $cleanSizes,
            ];
            $postedCustomAttributes = $_POST['custom_attributes'] ?? [];
            if (!is_array($postedCustomAttributes)) {
                throw new Exception('Submitted product attributes are invalid.');
            }
            foreach ($postedCustomAttributes as $attributeType => $values) {
                if (!is_array($values)) {
                    throw new Exception('Submitted product attribute values are invalid.');
                }
                $attributeValues[(string) $attributeType] = array_merge(
                    $attributeValues[(string) $attributeType] ?? [],
                    $values
                );
            }
            foreach ($variantCustomAttributes as $customValues) {
                foreach ($customValues as $attributeType => $value) {
                    $attributeValues[$attributeType][] = $value;
                }
            }
            $this->syncMappingAttributes($cid, $attributeValues);

            // Insert or update catalogs
            $existStyle = DB::fetch('SELECT id FROM catalogs WHERE concern_id = ? AND style_code = ?', [$cid, $styleCode]);
            if ($existStyle) {
                $catalogId = (int) $existStyle['id'];
                DB::query(
                    "UPDATE catalogs SET
                        name = ?, category_id = ?, subcategory_id = ?, sub_sub_category_id = ?,
                        short_code = ?, main_category_code = ?, sub_category_code = ?, sub_sub_category_code = ?,
                        hierarchy_path = ?, hsn_code = ?, gst_rate = ?, sizes = ?, status = 'in_house', updated_at = NOW()
                     WHERE id = ?",
                    [
                        $styleName, $categoryId, $subcategoryId, $subSubcategoryId,
                        $wholeCode, $mainCatCode, $subCatCode, $subSubCatCode,
                        $hierarchyPath, $hsnCode, $gstRate, json_encode($cleanSizes),
                        $catalogId
                    ]
                );
            } else {
                DB::query(
                    "INSERT INTO catalogs (
                        concern_id, code, style_code, short_code, name,
                        category_id, subcategory_id, sub_sub_category_id,
                        main_category_code, sub_category_code, sub_sub_category_code,
                        hierarchy_path, hsn_code, gst_rate, sizes, status, source_module, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_house', 'catalog_mapping', NOW())",
                    [
                        $cid, $styleCode, $styleCode, $wholeCode, $styleName,
                        $categoryId, $subcategoryId, $subSubcategoryId,
                        $mainCatCode, $subCatCode, $subSubCatCode,
                        $hierarchyPath, $hsnCode, $gstRate, json_encode($cleanSizes)
                    ]
                );
                $catalogId = (int) DB::lastInsertId();
            }

            // Generate / synchronize selected multi-attribute variants.

            $firstMrp = null;
            $firstPrice = null;
            $createdVariantQtys = [];

            foreach ($variantSkus as $variantKey => $skuInput) {
                $size = trim((string) ($variantSizes[$variantKey] ?? ''));
                if ($size === '' || !in_array($size, $cleanSizes, true)) {
                    throw new Exception('A generated variant has an invalid or unselected size.');
                }
                $sku = trim((string) $skuInput);
                $varName = trim((string) ($variantNames[$variantKey] ?? '')) ?: "{$styleName} - {$size}";
                $varColor = trim((string) ($variantColors[$variantKey] ?? ''));
                if ($sku === '' || $varColor === '') {
                    throw new Exception('Enter a SKU and color for every generated variant.');
                }
                $varQty = isset($variantQtys[$variantKey]) && (float) $variantQtys[$variantKey] > 0 ? (float) $variantQtys[$variantKey] : 1;
                $varWeight = isset($variantWeights[$variantKey]) && $variantWeights[$variantKey] !== '' ? (float) $variantWeights[$variantKey] : 0.0;
                $varHsn = !empty($variantHsns[$variantKey]) ? trim($variantHsns[$variantKey]) : $hsnCode;
                $varMrp = isset($variantMrps[$variantKey]) && $variantMrps[$variantKey] !== '' ? (float) $variantMrps[$variantKey] : $mrp;
                $varPrice = isset($variantSellingPrices[$variantKey]) && $variantSellingPrices[$variantKey] !== '' ? (float) $variantSellingPrices[$variantKey] : $sellingPrice;

                if ($firstMrp === null && $varMrp > 0)
                    $firstMrp = $varMrp;
                if ($firstPrice === null && $varPrice > 0)
                    $firstPrice = $varPrice;

                $varAttrJson = json_encode(array_filter([
                    'color' => $varColor,
                    'hsn' => $varHsn,
                    'weight' => $varWeight,
                    'allocated_qty' => $varQty,
                    'custom_attributes' => $variantCustomAttributes[$variantKey] ?? [],
                ]));

                $existingVariants = DB::fetchAll(
                    'SELECT id, attributes FROM variants WHERE concern_id = ? AND catalog_id = ? AND size = ?',
                    [$cid, $catalogId, $size]
                );
                $existVar = null;
                foreach ($existingVariants as $candidate) {
                    $candidateAttributes = json_decode((string) ($candidate['attributes'] ?? ''), true);
                    $candidateCustomAttributes = $candidateAttributes['custom_attributes'] ?? [];
                    $currentCustomAttributes = $variantCustomAttributes[$variantKey] ?? [];
                    if (!is_array($candidateCustomAttributes)) {
                        $candidateCustomAttributes = [];
                    }
                    $normalizedCandidateAttributes = [];
                    foreach ($candidateCustomAttributes as $type => $value) {
                        $normalizedCandidateAttributes[strtolower(trim((string) $type))] = strtolower(trim((string) $value));
                    }
                    $normalizedCurrentAttributes = [];
                    foreach ($currentCustomAttributes as $type => $value) {
                        $normalizedCurrentAttributes[strtolower(trim((string) $type))] = strtolower(trim((string) $value));
                    }
                    ksort($normalizedCandidateAttributes);
                    ksort($normalizedCurrentAttributes);
                    if (
                        strcasecmp(trim((string) ($candidateAttributes['color'] ?? '')), $varColor) === 0
                        && $normalizedCandidateAttributes === $normalizedCurrentAttributes
                    ) {
                        $existVar = $candidate;
                        break;
                    }
                }
                if (!$existVar) {
                    DB::query(
                        "INSERT INTO variants (
                            concern_id, catalog_id, product_code, name,
                            category_id, subcategory_id, size, weight, weight_unit, attributes,
                            price, mrp, creation_date, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'kg', ?, ?, ?, CURDATE(), 'active', NOW())",
                        [
                            $cid, $catalogId, $sku, $varName,
                            $categoryId, $subcategoryId, $size, $varWeight, $varAttrJson,
                            $varPrice, $varMrp
                        ]
                    );
                    $newVarId = (int) DB::lastInsertId();
                    if (!$primaryVariantId)
                        $primaryVariantId = $newVarId;
                    $createdVariantQtys[$newVarId] = $varQty;
                } else {
                    DB::query(
                        "UPDATE variants SET product_code = ?, name = ?, weight = ?, weight_unit = 'kg', price = ?, mrp = ?, attributes = ?, status = 'active' WHERE id = ?",
                        [$sku, $varName, $varWeight, $varPrice, $varMrp, $varAttrJson, $existVar['id']]
                    );
                    if (!$primaryVariantId)
                        $primaryVariantId = (int) $existVar['id'];
                    $createdVariantQtys[(int) $existVar['id']] = $varQty;
                }
            }

            if ($mrp <= 0 && $firstMrp !== null)
                $mrp = $firstMrp;
            if ($sellingPrice <= 0 && $firstPrice !== null)
                $sellingPrice = $firstPrice;
            // Update purchase_bill_items
            DB::query(
                "UPDATE purchase_bill_items 
                 SET catalog_id = ?, variant_id = ?, mrp = ?, selling_price = ?, color = COALESCE(NULLIF(?, ''), color) 
                 WHERE id = ?",
                [$catalogId, $primaryVariantId, $mrp, $sellingPrice, $color, $itemId]
            );

            // Save or update catalog_mappings table
            $existMap = DB::fetch('SELECT id FROM catalog_mappings WHERE purchase_bill_item_id = ?', [$itemId]);
            if ($existMap) {
                DB::query(
                    "UPDATE catalog_mappings SET
                        catalog_id = ?, variant_id = ?, category_id = ?, subcategory_id = ?, sub_sub_category_id = ?,
                        selected_sizes = ?, selected_color = ?, status = 'mapped', updated_at = NOW()
                     WHERE id = ?",
                    [
                        $catalogId, $primaryVariantId,
                        $_POST['category_id'] ?? null, $_POST['subcategory_id'] ?? null, $_POST['sub_sub_category_id'] ?? null,
                        json_encode($cleanSizes), $color,
                        $existMap['id']
                    ]
                );
            } else {
                DB::query(
                    "INSERT INTO catalog_mappings (
                        concern_id, purchase_bill_item_id, catalog_id, variant_id,
                        category_id, subcategory_id, sub_sub_category_id,
                        selected_sizes, selected_color, status, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'mapped', ?, NOW())",
                    [
                        $cid, $itemId, $catalogId, $primaryVariantId,
                        $_POST['category_id'] ?? null, $_POST['subcategory_id'] ?? null, $_POST['sub_sub_category_id'] ?? null,
                        json_encode($cleanSizes), $color,
                        Auth::id()
                    ]
                );
            }

            // Audit Log
            AuditLog::log('CREATE', 'catalog_mappings', $itemId, null, [
                'purchase_bill_item_id' => $itemId,
                'catalog_id' => $catalogId,
                'variant_id' => $primaryVariantId,
                'sizes' => $cleanSizes,
            ]);

            DB::commit();
            $this->flash('success', "Finished good item '{$item['item_name']}' successfully mapped to catalog with " . count($cleanSizes) . ' size variants.');
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Mapping failed: ' . $e->getMessage());
            $this->redirect('/production/catalog-mapping/map?id=' . $itemId);
        }

        $this->redirect('/production/catalog-mapping');
    }

    private function syncMappingAttributes(int $concernId, array $valuesByType): void
    {
        $existingRows = DB::fetchAll(
            'SELECT attribute_type, attribute_value FROM product_attributes
             WHERE concern_id = ? OR concern_id = 1 OR concern_id IS NULL',
            [$concernId]
        );
        $knownValues = [];
        foreach ($existingRows as $row) {
            $type = strtolower(trim((string) ($row['attribute_type'] ?? '')));
            foreach (preg_split('/[,;|]+/', (string) ($row['attribute_value'] ?? '')) as $value) {
                $value = strtolower(trim($value));
                if ($type !== '' && $value !== '') {
                    $knownValues[$type][$value] = true;
                }
            }
        }

        $sortOrder = (int) DB::fetchValue(
            'SELECT COALESCE(MAX(sort_order), 0) FROM product_attributes WHERE concern_id = ?',
            [$concernId]
        );
        foreach ($valuesByType as $attributeType => $values) {
            $type = strtolower(trim((string) $attributeType));
            if ($type === '' || strlen($type) > 50 || !is_array($values)) {
                throw new Exception('A submitted product attribute type is invalid.');
            }

            foreach ($values as $value) {
                if (!is_scalar($value)) {
                    throw new Exception('A submitted product attribute value is invalid.');
                }
                $value = trim((string) $value);
                $normalizedValue = strtolower($value);
                if ($value === '' || isset($knownValues[$type][$normalizedValue])) {
                    continue;
                }

                DB::query(
                    'INSERT INTO product_attributes (concern_id, attribute_type, attribute_name, attribute_value, sort_order)
                     VALUES (?, ?, ?, ?, ?)',
                    [$concernId, $type, ucfirst($type), $value, ++$sortOrder]
                );
                $knownValues[$type][$normalizedValue] = true;
            }
        }
    }

    /**
     * Unmap Finished Good Item
     */
    public function unmapItem(): void
    {
        $this->ensureSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $itemId = (int) ($_POST['purchase_bill_item_id'] ?? 0);

        if (!$itemId) {
            $this->flash('error', 'Invalid item ID.');
            $this->redirect('/production/catalog-mapping');
        }

        $item = DB::fetch('SELECT * FROM purchase_bill_items WHERE id = ?' . ($cid ? ' AND concern_id = ?' : ''), $cid ? [$itemId, $cid] : [$itemId]);
        if (!$item) {
            $this->flash('error', 'Item not found or access denied.');
            $this->redirect('/production/catalog-mapping');
        }

        DB::beginTransaction();
        try {
            DB::query('DELETE FROM catalog_mappings WHERE purchase_bill_item_id = ?', [$itemId]);
            DB::query('UPDATE purchase_bill_items SET catalog_id = NULL, variant_id = NULL WHERE id = ?', [$itemId]);
            AuditLog::log('DELETE', 'catalog_mappings', $itemId, null, ['action' => 'unmap']);
            DB::commit();
            $this->flash('success', "Item '{$item['item_name']}' unmapped successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Unmap failed: ' . $e->getMessage());
        }

        $this->redirect('/production/catalog-mapping');
    }

    /**
     * Quick-add Category / Subcategory / Sub-Subcategory from Catalog Mapping
     */
    public function quickCreateCategory(): void
    {
        $this->ensureSchema();
        $this->validateCsrf();
        $userCid = Auth::concernId();
        $itemId = (int) ($_POST['purchase_bill_item_id'] ?? 0);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            !empty($_POST['ajax']) ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        $tier = $_POST['tier'] ?? 'category';  // 'category', 'subcategory', 'subsubcategory'
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));

        if (empty($name)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Name is required.']);
                exit;
            }
            $this->flash('error', 'Name is required.');
            $this->redirect('/production/catalog-mapping?tab=hierarchy');
        }

        try {
            $newId = 0;
            $hsn = null;
            $gst = null;
            $catId = null;
            $subcatId = null;
            $itemCid = null;

            if ($itemId > 0) {
                $mappingItem = DB::fetch('SELECT concern_id FROM purchase_bill_items WHERE id = ?', [$itemId]);
                if (!$mappingItem) {
                    throw new Exception('Finished good item not found.');
                }
                $itemCid = (int) $mappingItem['concern_id'];
                if ($userCid !== null && $itemCid !== $userCid) {
                    throw new Exception('Finished good item not found or unauthorized.');
                }
            }

            $cid = $userCid ?? $itemCid;

            if ($tier === 'category') {
                if (!$cid || !DB::fetch('SELECT id FROM concerns WHERE id = ?', [$cid])) {
                    throw new Exception('Select a valid concern before creating a main category.');
                }
                $hsn = trim($_POST['default_hsn'] ?? '');
                $gstInput = trim((string)($_POST['default_gst_rate'] ?? ''));
                $gst = $gstInput === '' ? null : (is_numeric($gstInput) ? (float)$gstInput : -1.0);
                if (($hsn !== '' && !preg_match('/^\d{4}(?:\d{2}){0,2}$/', $hsn)) || ($gst !== null && ($gst < 0 || $gst > 100))) {
                    throw new Exception('Enter a valid HSN code and GST rate, or leave these optional defaults blank.');
                }
                if (empty($code))
                    $code = strtoupper(substr($name, 0, 2));

                // Strict duplicate validation: name and code
                $dupCat = DB::fetch(
                    "SELECT id, name FROM categories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND status != 'deleted'",
                    [$cid, $name]
                );
                if ($dupCat) {
                    throw new Exception("Duplicate Error: Main Category '{$name}' already exists. Duplicates are not allowed.");
                }
                if (!empty($code)) {
                    $dupCode = DB::fetch(
                        "SELECT id, code FROM categories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND code = ? AND status != 'deleted'",
                        [$cid, $code]
                    );
                    if ($dupCode) {
                        throw new Exception("Duplicate Error: Category code '{$code}' already exists. Duplicates are not allowed.");
                    }
                }

                DB::query(
                    "INSERT INTO categories (concern_id, name, code, default_hsn, default_gst_rate, status, created_at)
                     VALUES (?, ?, ?, ?, ?, 'active', NOW())",
                    [$cid, $name, $code, $hsn, $gst]
                );
                $newId = (int) DB::lastInsertId();
                $msg = "Main Category '{$name}' created successfully.";
            } elseif ($tier === 'subcategory') {
                $catId = (int) ($_POST['category_id'] ?? 0);
                if (!$catId)
                    throw new Exception('Parent Main Category is required.');
                $parentCategory = DB::fetch("SELECT concern_id FROM categories WHERE id = ? AND status = 'active'", [$catId]);
                if (!$parentCategory) {
                    throw new Exception('Selected parent category was not found or is inactive.');
                }
                $parentCid = (int) $parentCategory['concern_id'];
                if (($userCid !== null && $parentCid !== $userCid && $parentCid !== 1) ||
                    ($itemCid !== null && $parentCid !== $itemCid && $parentCid !== 1)) {
                    throw new Exception('Selected parent category belongs to a different concern.');
                }
                if (!DB::fetch('SELECT id FROM concerns WHERE id = ?', [$parentCid])) {
                    throw new Exception('Selected parent category has an invalid concern.');
                }
                $cid = $parentCid;
                if (empty($code))
                    $code = strtoupper(substr($name, 0, 2));

                // Strict duplicate validation: name under parent category and short code
                $dupSub = DB::fetch(
                    "SELECT id FROM subcategories WHERE category_id = ? AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND status != 'deleted'",
                    [$catId, $cid, $name]
                );
                if ($dupSub) {
                    throw new Exception("Duplicate Error: Subcategory '{$name}' already exists under the selected parent category. Duplicates are not allowed.");
                }
                if (!empty($code)) {
                    $dupSubCode = DB::fetch(
                        "SELECT id, code FROM subcategories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND LOWER(TRIM(code)) = LOWER(TRIM(?)) AND status != 'deleted'",
                        [$cid, $code]
                    );
                    if ($dupSubCode) {
                        throw new Exception("Duplicate Error: Subcategory code '{$code}' already exists. Duplicates are not allowed.");
                    }
                }

                DB::query(
                    "INSERT INTO subcategories (concern_id, category_id, name, code, status, created_at)
                     VALUES (?, ?, ?, ?, 'active', NOW())",
                    [$cid, $catId, $name, $code]
                );
                $newId = (int) DB::lastInsertId();
                $msg = "Subcategory '{$name}' created successfully.";
            } elseif ($tier === 'subsubcategory') {
                $subcatId = (int) ($_POST['subcategory_id'] ?? 0);
                if (!$subcatId)
                    throw new Exception('Parent Subcategory is required for Tier-3 category.');
                $parentSubcategory = DB::fetch(
                    "SELECT s.concern_id, s.category_id FROM subcategories s WHERE s.id = ? AND s.status = 'active'",
                    [$subcatId]
                );
                if (!$parentSubcategory) {
                    throw new Exception('Selected parent subcategory was not found or is inactive.');
                }
                $catId = (int) ($_POST['category_id'] ?? 0);
                $parentCid = (int) $parentSubcategory['concern_id'];
                if (!$catId || $catId !== (int) $parentSubcategory['category_id']) {
                    throw new Exception('Selected subcategory does not belong to the selected main category.');
                }
                if (($userCid !== null && $parentCid !== $userCid && $parentCid !== 1) ||
                    ($itemCid !== null && $parentCid !== $itemCid && $parentCid !== 1)) {
                    throw new Exception('Selected parent subcategory belongs to a different concern.');
                }
                if (!DB::fetch('SELECT id FROM concerns WHERE id = ?', [$parentCid])) {
                    throw new Exception('Selected parent subcategory has an invalid concern.');
                }
                $cid = $parentCid;
                if (empty($code))
                    $code = strtoupper(substr($name, 0, 2));

                // Strict duplicate validation: name under parent subcategory and short code
                $dupSubSub = DB::fetch(
                    "SELECT id FROM sub_subcategories WHERE subcategory_id = ? AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND status != 'deleted'",
                    [$subcatId, $cid, $name]
                );
                if ($dupSubSub) {
                    throw new Exception("Duplicate Error: Sub-Subcategory '{$name}' already exists under the selected parent subcategory. Duplicates are not allowed.");
                }
                if (!empty($code)) {
                    $dupSubSubCode = DB::fetch(
                        "SELECT id, code FROM sub_subcategories WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND LOWER(TRIM(code)) = LOWER(TRIM(?)) AND status != 'deleted'",
                        [$cid, $code]
                    );
                    if ($dupSubSubCode) {
                        throw new Exception("Duplicate Error: Sub-Subcategory code '{$code}' already exists. Duplicates are not allowed.");
                    }
                }

                DB::query(
                    "INSERT INTO sub_subcategories (concern_id, subcategory_id, name, code, status, created_at)
                     VALUES (?, ?, ?, ?, 'active', NOW())",
                    [$cid, $subcatId, $name, $code]
                );
                $newId = (int) DB::lastInsertId();
                $msg = "Tier-3 Sub-Subcategory '{$name}' created successfully.";
            } else {
                throw new Exception('Invalid category tier.');
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $msg,
                    'item' => [
                        'id' => $newId,
                        'name' => $name,
                        'code' => $code,
                        'tier' => $tier,
                        'category_id' => $catId,
                        'subcategory_id' => $subcatId,
                        'default_hsn' => $hsn,
                        'default_gst_rate' => $gst
                    ]
                ]);
                exit;
            }

            $this->flash('success', $msg);
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            $this->flash('error', $e->getMessage());
        }

        $returnUrl = $_POST['return_url'] ?? '/production/catalog-mapping?tab=hierarchy';
        $this->redirect($returnUrl);
    }

    /**
     * Quick-add Custom Product Attribute (e.g. Size, Color)
     */
    public function quickCreateAttribute(): void
    {
        $this->ensureSchema();
        $this->validateCsrf();
        $cid = Auth::concernId();
        $itemId = (int) ($_POST['purchase_bill_item_id'] ?? 0);
        if ($cid === null && Auth::isSuperAdmin()) {
            $requestedCid = (int) ($_POST['concern_id'] ?? 0);
            if ($requestedCid > 0) {
                $cid = $requestedCid;
            }
        }
        if ($cid === null && $itemId > 0) {
            $mappingItem = DB::fetch('SELECT concern_id FROM purchase_bill_items WHERE id = ?', [$itemId]);
            if ($mappingItem) {
                $cid = (int) $mappingItem['concern_id'];
            }
        }
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
            (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');
        if (!$cid || !DB::fetch('SELECT id FROM concerns WHERE id = ?', [$cid])) {
            $msg = 'Choose a concern in the attribute form, or select a specific concern from the top bar.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $this->flash('error', $msg);
            $this->redirect($_POST['return_url'] ?? '/production/catalog-mapping?tab=hierarchy');
            return;
        }

        $type = trim($_POST['attribute_type'] ?? '');
        if ($type === '__create_new__' || !empty($_POST['new_attribute_type'])) {
            $type = strtolower(trim($_POST['new_attribute_type'] ?? ''));
        }
        if (empty($type)) {
            $msg = 'Choose an existing attribute type or enter a new one.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $this->flash('error', $msg);
            $this->redirect($_POST['return_url'] ?? '/production/catalog-mapping?tab=hierarchy');
            return;
        }

        $val = trim($_POST['attribute_value'] ?? '');
        $name = trim($_POST['attribute_name'] ?? ucfirst($type));
        $values = array_values(array_filter(
            array_map('trim', preg_split('/[,;|]+/', $val)),
            static fn(string $value): bool => $value !== ''
        ));

        if (empty($values)) {
            $msg = 'Enter at least one attribute value.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $this->flash('error', $msg);
            $this->redirect($_POST['return_url'] ?? '/production/catalog-mapping?tab=hierarchy');
            return;
        }

        try {
            $existingAttrs = DB::fetchAll(
                'SELECT attribute_value FROM product_attributes WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND LOWER(TRIM(attribute_type)) = LOWER(TRIM(?))',
                [$cid, $type]
            );
            $existingValues = [];
            foreach ($existingAttrs as $existingAttr) {
                foreach (preg_split('/[,;|]+/', (string) ($existingAttr['attribute_value'] ?? '')) as $existingValue) {
                    $existingValues[strtolower(trim($existingValue))] = true;
                }
            }

            $submittedValues = [];
            foreach ($values as $value) {
                $normalizedValue = strtolower($value);
                if (isset($existingValues[$normalizedValue]) || isset($submittedValues[$normalizedValue])) {
                    throw new Exception("Duplicate Error: Attribute value '{$value}' already exists under '" . ucfirst($type) . "'. Duplicates are not allowed.");
                }
                $submittedValues[$normalizedValue] = true;
            }

            DB::beginTransaction();
            $newIds = [];
            try {
                foreach ($values as $value) {
                    DB::query(
                        'INSERT INTO product_attributes (concern_id, attribute_type, attribute_name, attribute_value, sort_order)
                         VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_attributes pa WHERE pa.concern_id = ?))',
                        [$cid, $type, $name, $value, $cid]
                    );
                    $newIds[] = (int) DB::lastInsertId();
                }
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

            $msg = count($values) === 1
                ? "Attribute '{$values[0]}' for '" . ucfirst($type) . "' added successfully."
                : count($values) . " attributes for '" . ucfirst($type) . "' added successfully.";
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'id' => $newIds[0],
                    'ids' => $newIds,
                    'attribute_type' => $type,
                    'attribute_value' => $val,
                    'attribute_values' => $values,
                    'message' => $msg
                ]);
                exit;
            }
            $this->flash('success', $msg);
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            $this->flash('error', 'Failed to add attribute: ' . $e->getMessage());
        }

        $returnUrl = $_POST['return_url'] ?? '/production/catalog-mapping?tab=hierarchy';
        $this->redirect($returnUrl);
    }

    /**
     * Delete Category / Subcategory / Sub-Subcategory / Attribute
     */
    public function deleteHierarchy(): void
    {
        $this->ensureSchema();
        $this->validateCsrf();
        $cid = Auth::concernId() ?: 1;

        $type = $_POST['type'] ?? '';  // 'category', 'subcategory', 'subsubcategory', 'attribute'
        $id = (int) ($_POST['id'] ?? 0);
        $returnUrl = $_POST['return_url'] ?? '/production/catalog-mapping?tab=hierarchy';

        if (!$id || empty($type)) {
            $this->flash('error', 'Invalid delete request.');
            $this->redirect($returnUrl);
            return;
        }

        try {
            if ($type === 'category') {
                $used = (int) DB::fetchValue('SELECT COUNT(*) FROM catalogs WHERE category_id = ?', [$id]);
                if ($used > 0) {
                    throw new Exception("Cannot delete category: It is currently linked to {$used} catalog style(s). Unmap or change their category first.");
                }
                $subIds = DB::fetchAll('SELECT id FROM subcategories WHERE category_id = ?', [$id]);
                foreach ($subIds as $s) {
                    DB::query('DELETE FROM sub_subcategories WHERE subcategory_id = ?', [$s['id']]);
                }
                DB::query('DELETE FROM subcategories WHERE category_id = ?', [$id]);
                DB::query('DELETE FROM categories WHERE id = ?', [$id]);
                $this->flash('success', 'Main Category and its subcategories were deleted successfully.');
            } elseif ($type === 'subcategory') {
                $used = (int) DB::fetchValue('SELECT COUNT(*) FROM catalogs WHERE subcategory_id = ?', [$id]);
                if ($used > 0) {
                    throw new Exception("Cannot delete subcategory: It is currently linked to {$used} catalog style(s).");
                }
                DB::query('DELETE FROM sub_subcategories WHERE subcategory_id = ?', [$id]);
                DB::query('DELETE FROM subcategories WHERE id = ?', [$id]);
                $this->flash('success', 'Subcategory was deleted successfully.');
            } elseif ($type === 'subsubcategory') {
                $used = (int) DB::fetchValue('SELECT COUNT(*) FROM catalogs WHERE sub_sub_category_id = ?', [$id]);
                if ($used > 0) {
                    throw new Exception("Cannot delete sub-subcategory: It is currently linked to {$used} catalog style(s).");
                }
                DB::query('DELETE FROM sub_subcategories WHERE id = ?', [$id]);
                $this->flash('success', 'Tier-3 Sub-Subcategory was deleted successfully.');
            } elseif ($type === 'attribute') {
                $subVal = trim($_POST['value'] ?? '');
                $attr = DB::fetch('SELECT * FROM product_attributes WHERE id = ?', [$id]);
                if ($attr) {
                    if ($subVal !== '' && (str_contains($attr['attribute_value'], ',') || str_contains($attr['attribute_value'], ';'))) {
                        $parts = preg_split('/[,;|]+/', (string) $attr['attribute_value']);
                        $remaining = array_values(array_filter($parts, fn($p) => strtolower(trim($p)) !== strtolower($subVal)));
                        if (empty($remaining)) {
                            DB::query('DELETE FROM product_attributes WHERE id = ?', [$id]);
                        } else {
                            DB::query('UPDATE product_attributes SET attribute_value = ? WHERE id = ?', [implode(',', $remaining), $id]);
                        }
                    } else {
                        DB::query('DELETE FROM product_attributes WHERE id = ?', [$id]);
                    }
                }
                $this->flash('success', 'Attribute was deleted successfully.');
            } elseif ($type === 'hsn') {
                $hsn = DB::fetch('SELECT * FROM hsn_masters WHERE id = ?', [$id]);
                if (!$hsn) {
                    throw new Exception('HSN record not found.');
                }
                if (!Auth::isSuperAdmin()) {
                    $userCid = Auth::concernId();
                    if ($hsn['concern_id'] === null) {
                        throw new Exception('Only Super Admin can delete Global HSN codes.');
                    }
                    if ((int)$hsn['concern_id'] !== (int)$userCid) {
                        throw new Exception('Unauthorized to delete HSN code belonging to another concern.');
                    }
                }
                $code = $hsn['hsn_code'];
                $catalogCount = (int) DB::fetchValue('SELECT COUNT(*) FROM catalogs WHERE hsn_code = ?', [$code]);
                $categoryCount = (int) DB::fetchValue('SELECT COUNT(*) FROM categories WHERE default_hsn = ?', [$code]);
                if ($catalogCount > 0 || $categoryCount > 0) {
                    DB::query('UPDATE hsn_masters SET is_active = 0 WHERE id = ?', [$id]);
                    $this->flash('success', "HSN Code '{$code}' is currently linked and was deactivated rather than deleted.");
                } else {
                    DB::query('DELETE FROM hsn_masters WHERE id = ?', [$id]);
                    $this->flash('success', "HSN Code '{$code}' was deleted successfully.");
                }
            } else {
                throw new Exception('Unknown hierarchy type.');
            }
        } catch (Exception $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect($returnUrl);
    }

    /**
     * AJAX endpoint returning JSON details of a finished good item
     */
    public function apiItemDetails(): void
    {
        header('Content-Type: application/json');
        $this->ensureSchema();
        $cid = Auth::concernId();
        $id = (int) ($_GET['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
            exit;
        }

        $sql = 'SELECT pbi.*, pb.bill_no, pb.bill_date, s.name AS supplier_name,
                       c.name AS catalog_name, c.style_code, c.hierarchy_path, c.sizes
                FROM purchase_bill_items pbi
                JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                LEFT JOIN suppliers s ON pb.supplier_id = s.id
                LEFT JOIN catalogs c ON pbi.catalog_id = c.id
                WHERE pbi.id = ?' . ($cid ? ' AND pbi.concern_id = ?' : '');
        $item = DB::fetch($sql, $cid ? [$id, $cid] : [$id]);

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'item' => $item]);
        exit;
    }

    /**
     * Create HSN master code
     */
    public function createHsn(): void
    {
        $this->ensureSchema();
        $this->validateCsrf();
        $returnUrl = $_POST['return_url'] ?? '/production/catalog-mapping?tab=hierarchy';
        if (!Auth::can('catalog_mapping', 'add') && !Auth::can('product_adding', 'add') && !Auth::isSuperAdmin()) {
            $this->flash('error', 'Unauthorized to add HSN codes.');
            $this->redirect($returnUrl);
            return;
        }

        $codeType = 'HSN';

        $hsnCode = trim($_POST['hsn_code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $rawGst = trim($_POST['gst_rate'] ?? '');
        $gstRate = $rawGst !== '' ? (float) $rawGst : null;
        $returnUrl = $_POST['return_url'] ?? '/production/catalog-mapping?tab=hierarchy';

        if (!preg_match('/^\d{4}(?:\d{2}){0,2}$/', $hsnCode)) {
            $this->flash('error', 'HSN code must contain only digits and be 4, 6, or 8 digits.');
            $this->redirect($returnUrl);
            return;
        }
        if ($rawGst === '') {
            $this->flash('error', 'GST percentage is required and must be between 0.00 and 100.00.');
            $this->redirect($returnUrl);
            return;
        }
        if ($rawGst !== '' && (!is_numeric($rawGst) || !is_finite((float)$rawGst) || (float)$rawGst < 0 || (float)$rawGst > 100)) {
            $this->flash('error', 'GST percentage must be between 0.00 and 100.00.');
            $this->redirect($returnUrl);
            return;
        }

        // Scope determination
        $concernId = null;
        if (Auth::isSuperAdmin()) {
            $scope = trim($_POST['scope'] ?? 'global');
            if ($scope === 'concern') {
                $concernId = !empty($_POST['concern_id']) ? (int) $_POST['concern_id'] : (Auth::concernId() ?: null);
            } else {
                $concernId = null; // Global
            }
        } else {
            $concernId = Auth::concernId();
        }

        try {
            // Check duplicate
            $dupSql = "SELECT id, is_active FROM hsn_masters WHERE hsn_code = ? AND code_type = ? AND " . 
                      ($concernId === null ? "concern_id IS NULL" : "concern_id = ?");
            $dupParams = $concernId === null ? [$hsnCode, $codeType] : [$hsnCode, $codeType, $concernId];
            $existing = DB::fetch($dupSql, $dupParams);

            if ($existing) {
                if ((int)$existing['is_active'] === 1) {
                    throw new Exception("The {$codeType} code '{$hsnCode}' already exists for this scope.");
                } else {
                    // Reactivate and update
                    DB::query("UPDATE hsn_masters SET is_active = 1, description = ?, category = ?, gst_rate = ? WHERE id = ?", [
                        $description ?: null,
                        $category ?: null,
                        $gstRate,
                        $existing['id']
                    ]);
                    $this->flash('success', "{$codeType} code '{$hsnCode}' reactivated and updated successfully.");
                    $this->redirect($returnUrl);
                    return;
                }
            }

            DB::query("INSERT INTO hsn_masters (concern_id, code_type, hsn_code, description, category, gst_rate, rate_note, is_active)
                       VALUES (?, ?, ?, ?, ?, ?, NULL, 1)", [
                $concernId,
                $codeType,
                $hsnCode,
                $description ?: null,
                $category ?: null,
                $gstRate
            ]);

            $this->flash('success', "{$codeType} Code '{$hsnCode}' created successfully.");
        } catch (Exception $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect($returnUrl);
    }

    /**
     * Update HSN master code
     */
    public function updateHsn(): void
    {
        $this->ensureSchema();
        $this->validateCsrf();
        $returnUrl = $_POST['return_url'] ?? '/production/catalog-mapping?tab=hierarchy';
        if (!Auth::can('catalog_mapping', 'edit') && !Auth::can('product_adding', 'edit') && !Auth::isSuperAdmin()) {
            $this->flash('error', 'Unauthorized to edit HSN codes.');
            $this->redirect($returnUrl);
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $returnUrl = $_POST['return_url'] ?? '/production/catalog-mapping?tab=hierarchy';

        if (!$id) {
            $this->flash('error', 'Invalid HSN ID.');
            $this->redirect($returnUrl);
            return;
        }

        $hsn = DB::fetch('SELECT * FROM hsn_masters WHERE id = ?', [$id]);
        if (!$hsn) {
            $this->flash('error', 'HSN record not found.');
            $this->redirect($returnUrl);
            return;
        }

        // Access check for concern-scoped users
        if (!Auth::isSuperAdmin()) {
            $userCid = Auth::concernId();
            if ($hsn['concern_id'] === null) {
                $this->flash('error', 'Only Super Admin can edit Global HSN codes.');
                $this->redirect($returnUrl);
                return;
            }
            if ((int)$hsn['concern_id'] !== (int)$userCid) {
                $this->flash('error', 'Unauthorized to edit HSN codes belonging to another concern.');
                $this->redirect($returnUrl);
                return;
            }
        }

        $codeType = 'HSN';
        $hsnCode = trim($_POST['hsn_code'] ?? $hsn['hsn_code']);
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $rawGst = trim($_POST['gst_rate'] ?? '');
        $gstRate = $rawGst !== '' ? (float) $rawGst : null;
        $isActive = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

        if (!preg_match('/^\d{4}(?:\d{2}){0,2}$/', $hsnCode)) {
            $this->flash('error', 'HSN code must contain only digits and be 4, 6, or 8 digits.');
            $this->redirect($returnUrl);
            return;
        }
        if ($rawGst !== '' && (!is_numeric($rawGst) || !is_finite((float)$rawGst) || (float)$rawGst < 0 || (float)$rawGst > 100)) {
            $this->flash('error', 'GST percentage must be between 0.00 and 100.00.');
            $this->redirect($returnUrl);
            return;
        }

        // Scope check / update if super admin
        $concernId = $hsn['concern_id'];
        if (Auth::isSuperAdmin() && isset($_POST['scope'])) {
            $scope = trim($_POST['scope']);
            if ($scope === 'global') {
                $concernId = null;
            } elseif ($scope === 'concern') {
                $concernId = !empty($_POST['concern_id']) ? (int) $_POST['concern_id'] : (Auth::concernId() ?: null);
            }
        }

        try {
            // Check duplicate code if code, type or scope changed
            $dupSql = "SELECT id FROM hsn_masters WHERE hsn_code = ? AND code_type = ? AND id != ? AND " .
                      ($concernId === null ? "concern_id IS NULL" : "concern_id = ?");
            $dupParams = $concernId === null ? [$hsnCode, $codeType, $id] : [$hsnCode, $codeType, $id, $concernId];
            $existing = DB::fetch($dupSql, $dupParams);
            if ($existing) {
                throw new Exception("Another {$codeType} record with code '{$hsnCode}' already exists for this scope.");
            }

            DB::query("UPDATE hsn_masters 
                       SET concern_id = ?, code_type = ?, hsn_code = ?, description = ?, category = ?, gst_rate = ?, is_active = ?
                       WHERE id = ?", [
                $concernId,
                $codeType,
                $hsnCode,
                $description ?: null,
                $category ?: null,
                $gstRate,
                $isActive,
                $id
            ]);

            $this->flash('success', "{$codeType} Code '{$hsnCode}' updated successfully.");
        } catch (Exception $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect($returnUrl);
    }

    /**
     * Delete or deactivate an HSN master code.
     */
    public function deleteHsn(): void
    {
        $this->ensureSchema();
        $this->validateCsrf();
        $returnUrl = $_POST['return_url'] ?? '/production/catalog-mapping?tab=hierarchy';
        if (!Auth::can('catalog_mapping', 'delete') && !Auth::can('product_adding', 'delete') && !Auth::isSuperAdmin()) {
            $this->flash('error', 'Unauthorized to delete HSN codes.');
            $this->redirect($returnUrl);
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $returnUrl = $_POST['return_url'] ?? '/production/catalog-mapping?tab=hierarchy';

        if (!$id) {
            $this->flash('error', 'Invalid HSN ID.');
            $this->redirect($returnUrl);
            return;
        }

        $hsn = DB::fetch('SELECT * FROM hsn_masters WHERE id = ?', [$id]);
        if (!$hsn) {
            $this->flash('error', 'HSN record not found.');
            $this->redirect($returnUrl);
            return;
        }

        if (!Auth::isSuperAdmin()) {
            $userCid = Auth::concernId();
            if ($hsn['concern_id'] === null) {
                $this->flash('error', 'Only Super Admin can delete Global HSN codes.');
                $this->redirect($returnUrl);
                return;
            }
            if ((int)$hsn['concern_id'] !== (int)$userCid) {
                $this->flash('error', 'Unauthorized to delete HSN code belonging to another concern.');
                $this->redirect($returnUrl);
                return;
            }
        }

        try {
            $code = $hsn['hsn_code'];
            $catalogCount = (int) DB::fetchValue('SELECT COUNT(*) FROM catalogs WHERE hsn_code = ?', [$code]);
            $categoryCount = (int) DB::fetchValue('SELECT COUNT(*) FROM categories WHERE default_hsn = ?', [$code]);

            if ($catalogCount > 0 || $categoryCount > 0) {
                DB::query('UPDATE hsn_masters SET is_active = 0 WHERE id = ?', [$id]);
                $refs = [];
                if ($catalogCount > 0) $refs[] = "{$catalogCount} catalog style(s)";
                if ($categoryCount > 0) $refs[] = "{$categoryCount} category(ies)";
                $this->flash('success', "HSN Code '{$code}' is currently referenced by " . implode(' and ', $refs) . ". It has been deactivated rather than deleted.");
            } else {
                DB::query('DELETE FROM hsn_masters WHERE id = ?', [$id]);
                $this->flash('success', "HSN Code '{$code}' deleted successfully.");
            }
        } catch (Exception $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect($returnUrl);
    }
}
