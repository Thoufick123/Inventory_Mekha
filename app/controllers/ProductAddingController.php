<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Services\StockService;
use App\Services\RollRegistryService;
use Exception;

class ProductAddingController extends Controller
{
    public function index(): void
    {
        $this->ensureSchema();
        $cid = Auth::concernId();
        if ($cid) {
            $suppliers = DB::fetchAll("SELECT id, code, name, company_name, district, state FROM suppliers WHERE concern_id = ? AND status = 'active' ORDER BY name ASC", [$cid]);
            $variants = DB::fetchAll("SELECT id, sku_code, name, price, size FROM variants WHERE concern_id = ? AND status = 'active' ORDER BY name ASC", [$cid]);
            $hsnList = DB::fetchAll("SELECT code_type, hsn_code, description, category, gst_rate, rate_note FROM hsn_masters WHERE is_active = 1 AND code_type = 'HSN' AND (concern_id = ? OR concern_id IS NULL) ORDER BY hsn_code ASC", [$cid]);
        } else {
            $suppliers = DB::fetchAll("SELECT id, concern_id, code, name, company_name, district, state FROM suppliers WHERE status = 'active' ORDER BY name ASC");
            $variants = DB::fetchAll("SELECT id, concern_id, sku_code, name, price, size FROM variants WHERE status = 'active' ORDER BY name ASC");
            $hsnList = DB::fetchAll("SELECT code_type, hsn_code, description, category, gst_rate, rate_note FROM hsn_masters WHERE is_active = 1 AND code_type = 'HSN' ORDER BY hsn_code ASC");
        }

        $allConcerns = Auth::isSuperAdmin()
            ? DB::fetchAll("SELECT id, name, code FROM concerns ORDER BY id ASC")
            : [];
        $targetCid = $cid ?: 1;
        $categoryScope = $cid
            ? " WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND status = 'active'"
            : " WHERE status = 'active'";
        $categoryParams = $cid ? [$cid] : [];
        $categories = DB::fetchAll("SELECT id, concern_id, name, default_hsn, default_gst_rate FROM categories{$categoryScope} ORDER BY name ASC", $categoryParams);
        $subcategories = DB::fetchAll(
            "SELECT id, concern_id, category_id, name FROM subcategories{$categoryScope} ORDER BY name ASC",
            $categoryParams
        );
        $subSubcategories = DB::fetchAll(
            "SELECT id, concern_id, subcategory_id, name FROM sub_subcategories{$categoryScope} ORDER BY name ASC",
            $categoryParams
        );
        $productAttributes = DB::fetchAll(
            $cid
                ? "SELECT concern_id, attribute_type, attribute_value FROM product_attributes WHERE concern_id = ? OR concern_id = 1 OR concern_id IS NULL ORDER BY attribute_type, sort_order"
                : "SELECT concern_id, attribute_type, attribute_value FROM product_attributes ORDER BY attribute_type, sort_order",
            $cid ? [$cid] : []
        );
        $this->render('concern/purchase_billing_create', [
            'pageTitle' => 'Product Adding',
            'currentModule' => 'product_adding',
            'suppliers' => $suppliers,
            'variants' => $variants,
            'selectedCid' => $cid,
            'allConcerns' => $allConcerns,
            'categories' => $categories,
            'subcategories' => $subcategories,
            'subSubcategories' => $subSubcategories,
            'productAttributes' => $productAttributes,
            'hsnList' => $hsnList,
            'savedRollSpecs' => RollRegistryService::getSavedSpecs($targetCid),
            'savedColors' => RollRegistryService::getSavedColors($targetCid),
            'savedDesigns' => RollRegistryService::getSavedDesigns($targetCid),
            'formAction' => \App\Core\Controller::url('/m/product_adding/store'),
            'hsnSaveUrl' => \App\Core\Controller::url('/m/product_adding/save_hsn'),
            'rollSpecSaveUrl' => \App\Core\Controller::url('/m/product_adding/save_roll_spec'),
            'quickSupplierSaveUrl' => \App\Core\Controller::url('/m/product_adding/suppliers/store'),
            'allowQuickSupplier' => Auth::isSuperAdmin() || Auth::can('product_adding', 'add'),
            'backUrl' => \App\Core\Controller::url('/m/stocks'),
            'backLabel' => 'Back to Stock',
            'submitLabel' => 'Save Product Purchase',
            'pageHeading' => 'Product Adding',
            'pageDescription' => 'Record fabric rolls, garment supplies/accessories and finished goods received from vendors.',
        ]);
    }

    public function subcategories(): void
    {
        $categoryId = (int)($_GET['category_id'] ?? 0);
        $cid = Auth::concernId();
        $sql = "SELECT id, name, code FROM subcategories WHERE category_id = ? AND status = 'active'";
        $params = [$categoryId];
        if ($cid) {
            $sql .= " AND concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY name ASC";
        $this->renderJson(['success' => true, 'items' => DB::fetchAll($sql, $params)]);
    }

    public function subSubcategories(): void
    {
        $subcategoryId = (int)($_GET['subcategory_id'] ?? 0);
        $cid = Auth::concernId();
        $sql = "SELECT id, name, code FROM sub_subcategories WHERE subcategory_id = ? AND status = 'active'";
        $params = [$subcategoryId];
        if ($cid) {
            $sql .= " AND concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY name ASC";
        $this->renderJson(['success' => true, 'items' => DB::fetchAll($sql, $params)]);
    }

    public function store(): void
    {
        $this->validateCsrf();
        $cid = Auth::concernId();
        if (!$cid) {
            $this->flash('error', 'Select a concern before adding products.');
            $this->redirect('/m/product_adding');
        }

        $styleCode = strtoupper(trim($_POST['style_code'] ?? ''));
        $styleName = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $subcategoryId = (int)($_POST['subcategory_id'] ?? 0) ?: null;
        $subSubcategoryId = (int)($_POST['sub_sub_category_id'] ?? 0) ?: null;
        $hsnCode = trim($_POST['hsn_code'] ?? '');
        $gstRate = (float)($_POST['gst_rate'] ?? 0);
        $color = trim($_POST['color'] ?? '');
        $costPrice = (float)($_POST['cost_price'] ?? 0);
        $mrp = (float)($_POST['mrp'] ?? 0);
        $sellingPrice = (float)($_POST['price'] ?? 0);
        $location = trim($_POST['location'] ?? 'Main Store') ?: 'Main Store';
        $sizes = $_POST['sizes'] ?? [];
        $quantities = $_POST['quantities'] ?? [];

        if ($styleCode === '' || $styleName === '' || $categoryId <= 0) {
            $this->flash('error', 'Style code, product name, and main category are required.');
            $this->redirect('/m/product_adding');
        }
        if (!preg_match('/^\d{4}(?:\d{2}){0,2}$/', $hsnCode) || $gstRate < 0 || $gstRate > 100) {
            $this->flash('error', 'Enter a valid HSN code and GST rate.');
            $this->redirect('/m/product_adding');
        }
        if (!is_array($sizes) || count($sizes) === 0) {
            $this->flash('error', 'Select at least one size.');
            $this->redirect('/m/product_adding');
        }

        DB::beginTransaction();
        try {
            $cat = DB::fetch("SELECT * FROM categories WHERE id = ? AND (concern_id = ? OR concern_id = 1)", [$categoryId, $cid]);
            if (!$cat) {
                throw new Exception('The selected category is not valid for this concern.');
            }

            $sub = $subcategoryId ? DB::fetch("SELECT * FROM subcategories WHERE id = ? AND (concern_id = ? OR concern_id = 1)", [$subcategoryId, $cid]) : null;
            $subSub = $subSubcategoryId ? DB::fetch("SELECT * FROM sub_subcategories WHERE id = ? AND (concern_id = ? OR concern_id = 1)", [$subSubcategoryId, $cid]) : null;

            $mainCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($cat['code'] ?? $cat['name'])) ?: 'XX');
            $subCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($sub['code'] ?? $sub['name'] ?? '')) ?: 'XX');
            $subSubCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($subSub['code'] ?? $subSub['name'] ?? '')) ?: 'XX');
            $wholeCode = substr($mainCode, 0, 2) . substr($subCode, 0, 2) . substr($subSubCode, 0, 2);
            $path = implode(' > ', array_filter([$cat['name'] ?? '', $sub['name'] ?? '', $subSub['name'] ?? '']));

            $catalogId = (int)DB::fetchValue(
                "SELECT id FROM catalogs WHERE concern_id = ? AND style_code = ? LIMIT 1",
                [$cid, $styleCode]
            );
            if ($catalogId > 0) {
                DB::query(
                    "UPDATE catalogs SET name = ?, category_id = ?, subcategory_id = ?, sub_sub_category_id = ?, hierarchy_path = ?, hsn_code = ?, gst_rate = ?, short_code = ?, main_category_code = ?, sub_category_code = ?, sub_sub_category_code = ?, sizes = ?, status = 'active', updated_at = NOW() WHERE id = ?",
                    [$styleName, $categoryId, $subcategoryId, $subSubcategoryId, $path, $hsnCode, $gstRate, $wholeCode, substr($mainCode, 0, 10), substr($subCode, 0, 10), substr($subSubCode, 0, 10), json_encode(array_values($sizes)), $catalogId]
                );
            } else {
                DB::query(
                    "INSERT INTO catalogs (concern_id, code, style_code, short_code, category_id, subcategory_id, sub_sub_category_id, main_category_code, sub_category_code, sub_sub_category_code, hierarchy_path, hsn_code, gst_rate, sizes, status, name, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())",
                    [$cid, $styleCode, $styleCode, $wholeCode, $categoryId, $subcategoryId, $subSubcategoryId, substr($mainCode, 0, 10), substr($subCode, 0, 10), substr($subSubCode, 0, 10), $path, $hsnCode, $gstRate, json_encode(array_values($sizes)), $styleName]
                );
                $catalogId = (int)DB::lastInsertId();
            }

            $createdIds = [];
            $totalQty = 0.0;
            foreach ($sizes as $index => $size) {
                $size = trim((string)$size);
                if ($size === '') {
                    continue;
                }
                $qty = (float)($quantities[$size] ?? 0);
                $colorKey = strtoupper(preg_replace('/[^A-Z0-9]/', '', $color) ?: 'GEN');
                $sizeKey = strtoupper(preg_replace('/[^A-Z0-9]/', '', $size) ?: 'OS');
                $baseSku = strtoupper(trim($styleCode . '-' . $sizeKey . '-' . $colorKey));

                $sku = $baseSku;
                $sequence = 1;
                while (DB::fetch("SELECT id FROM variants WHERE concern_id = ? AND sku_code = ?", [$cid, $sku])) {
                    $sequence++;
                    $sku = $baseSku . '-' . $sequence;
                }

                $attributes = [
                    'color' => $color,
                    'size' => $size,
                    'allocated_qty' => $qty,
                ];
                DB::query(
                    "INSERT INTO variants (concern_id, catalog_id, sku_code, name, category_id, subcategory_id, brand_id, size, weight, weight_unit, attributes, cost_price, price, mrp, is_complementary, creation_date, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NULL, ?, 0, 'pcs', ?, ?, ?, ?, 0, CURDATE(), 'active', NOW())",
                    [$cid, $catalogId, $sku, $styleName . ' - ' . $size . ($color !== '' ? ' - ' . $color : ''), $categoryId, $subcategoryId, $size, json_encode($attributes), $costPrice, $sellingPrice, $mrp]
                );
                $variantId = (int)DB::lastInsertId();
                $createdIds[] = $variantId;

                if ($qty > 0) {
                    StockService::move($cid, $variantId, $location, $qty, 0, 'Product Adding', $catalogId, 'Opening stock created from Product Adding', Auth::id());
                    $totalQty += $qty;
                }
            }

            if (!$createdIds) {
                throw new Exception('No valid product variants were created.');
            }
            DB::commit();
        } catch (\Throwable $e) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            $this->flash('error', 'Failed to add product: ' . $e->getMessage());
            $this->redirect('/m/product_adding');
        }

        $this->flash('success', 'Product created successfully. ' . count($createdIds) . ' size variant(s) added; opening stock credited: ' . number_format($totalQty, 2) . '.');
        $this->redirect('/m/product_adding?variant_ids=' . implode(',', $createdIds) . '&qty=' . max(1, (int)ceil($totalQty)));
    }

    private function ensureSchema(): void
    {
        try {
            $catalogColumns = array_column(DB::fetchAll("DESCRIBE catalogs"), 'Field');
            $requiredCatalogColumns = [
                'style_code' => 'VARCHAR(50) NULL',
                'short_code' => 'VARCHAR(50) NULL',
                'category_id' => 'INT NULL',
                'subcategory_id' => 'INT NULL',
                'sub_sub_category_id' => 'INT NULL',
                'main_category_code' => 'VARCHAR(20) NULL',
                'sub_category_code' => 'VARCHAR(20) NULL',
                'sub_sub_category_code' => 'VARCHAR(20) NULL',
                'hierarchy_path' => 'VARCHAR(255) NULL',
                'hsn_code' => 'VARCHAR(50) NULL',
                'gst_rate' => 'DECIMAL(5,2) NULL',
                'sizes' => 'TEXT NULL',
            ];
            foreach ($requiredCatalogColumns as $column => $definition) {
                if (!in_array($column, $catalogColumns, true)) {
                    DB::query("ALTER TABLE catalogs ADD COLUMN `{$column}` {$definition}");
                }
            }

            $variantColumns = array_column(DB::fetchAll("DESCRIBE variants"), 'Field');
            if (!in_array('catalog_id', $variantColumns, true)) {
                DB::query("ALTER TABLE variants ADD COLUMN catalog_id INT NULL AFTER concern_id");
            }
            if (!in_array('cost_price', $variantColumns, true)) {
                DB::query("ALTER TABLE variants ADD COLUMN cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER attributes");
            }

            $categoryColumns = array_column(DB::fetchAll("DESCRIBE categories"), 'Field');
            if (!in_array('code', $categoryColumns, true)) {
                DB::query("ALTER TABLE categories ADD COLUMN code VARCHAR(20) NULL AFTER name");
            }
            if (!in_array('default_hsn', $categoryColumns, true)) {
                DB::query("ALTER TABLE categories ADD COLUMN default_hsn VARCHAR(50) NULL AFTER code");
            }
            if (!in_array('default_gst_rate', $categoryColumns, true)) {
                DB::query("ALTER TABLE categories ADD COLUMN default_gst_rate DECIMAL(5,2) NULL DEFAULT NULL AFTER default_hsn");
            }

            $purchaseItemColumns = array_column(DB::fetchAll("DESCRIBE purchase_bill_items"), 'Field');
            if (!in_array('catalog_id', $purchaseItemColumns, true)) {
                DB::query("ALTER TABLE purchase_bill_items ADD COLUMN catalog_id INT NULL AFTER variant_id");
            }

            DB::query("CREATE TABLE IF NOT EXISTS sub_subcategories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                concern_id INT NOT NULL,
                subcategory_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(20) NULL,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ssc_concern (concern_id),
                INDEX idx_ssc_subcategory (subcategory_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            DB::query("CREATE TABLE IF NOT EXISTS product_attributes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                concern_id INT NOT NULL,
                attribute_type VARCHAR(50) NOT NULL DEFAULT 'size',
                attribute_name VARCHAR(100) NOT NULL,
                attribute_value VARCHAR(100) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pattr_concern (concern_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {
            error_log('Product Adding schema check error: ' . $e->getMessage());
        }
    }
}
