<?php
// app/controllers/VariantController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Core\ModuleGuard;
use App\Models\Variant;
use App\Services\QrService;
use Exception;

class VariantController extends Controller {
    public function index(): void {
        ModuleGuard::enforce('variants', 'view');
        $variantModel = new Variant();
        $variants = $variantModel->allWithDetails();

        $cid = Auth::concernId();
        if ($cid) {
            $categories = DB::fetchAll("SELECT * FROM categories WHERE concern_id = ? AND status = 'active'", [$cid]);
            $subcategories = DB::fetchAll("SELECT * FROM subcategories WHERE concern_id = ? AND status = 'active'", [$cid]);
            $brands = DB::fetchAll("SELECT * FROM brands WHERE concern_id = ? AND status = 'active'", [$cid]);
        } else {
            $categories = DB::fetchAll("SELECT * FROM categories WHERE status = 'active'");
            $subcategories = DB::fetchAll("SELECT * FROM subcategories WHERE status = 'active'");
            $brands = DB::fetchAll("SELECT * FROM brands WHERE status = 'active'");
        }

        $this->render('concern/variants', [
            'pageTitle'     => 'Product Variants & Master Catalog',
            'currentModule' => 'stocks',
            'variants'      => $variants,
            'categories'    => $categories,
            'subcategories' => $subcategories,
            'brands'        => $brands,
            'selectedCid'   => $cid,
        ]);
    }

    public function store(): void {
        ModuleGuard::enforce('variants', 'add');
        $this->validateCsrf();
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);

        $sku          = trim($_POST['product_code'] ?? '');
        $name         = trim($_POST['name'] ?? '');
        $categoryId   = (int)($_POST['category_id'] ?? 0);
        $subcatId     = (int)($_POST['subcategory_id'] ?? 0);
        $brandId      = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $size         = trim($_POST['size'] ?? '');
        $weight       = (float)($_POST['weight'] ?? 0);
        $weightUnit   = trim($_POST['weight_unit'] ?? 'pcs');
        $price        = (float)($_POST['price'] ?? 0);
        $mrp          = (float)($_POST['mrp'] ?? 0);
        $creationDate = $_POST['creation_date'] ?: date('Y-m-d');
        $expiryDate   = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

        // Custom JSON attributes
        $color  = trim($_POST['attr_color'] ?? '');
        $fabric = trim($_POST['attr_fabric'] ?? '');
        $gsm    = trim($_POST['attr_gsm'] ?? '');
        $attributes = [];
        if ($color)  $attributes['color'] = $color;
        if ($fabric) $attributes['fabric'] = $fabric;
        if ($gsm)    $attributes['gsm'] = $gsm;

        if (empty($sku) || empty($name) || !$categoryId || !$subcatId) {
            $this->flash('error', 'Product Code, Product Name, Category and Subcategory are required.');
            $this->redirect('/m/variants');
        }

        try {
            $varModel = new Variant();
            $varId = $varModel->insert([
                'product_code'       => $sku,
                'name'           => $name,
                'category_id'    => $categoryId,
                'subcategory_id' => $subcatId,
                'brand_id'       => $brandId,
                'size'           => $size,
                'weight'         => $weight,
                'weight_unit'    => $weightUnit,
                'attributes'     => !empty($attributes) ? json_encode($attributes) : null,
                'price'          => $price,
                'mrp'            => $mrp,
                'creation_date'  => $creationDate,
                'expiry_date'    => $expiryDate,
                'status'         => 'active',
            ]);

            $this->flash('success', "Variant '{$name}' ({$sku}) created successfully! ID: {$varId}");
        } catch (Exception $e) {
            $this->flash('error', 'Failed to save variant: ' . $e->getMessage());
        }

        $this->redirect('/m/variants');
    }

    public function update(): void {
        ModuleGuard::enforce('variants', 'edit');
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $variant = DB::fetch("SELECT * FROM variants WHERE id = ?", [$id]);
        if (!$variant) {
            $this->flash('error', 'Variant not found.');
            $this->redirect('/m/variants');
        }

        $sku          = trim($_POST['product_code'] ?? $variant['product_code']);
        $name         = trim($_POST['name'] ?? $variant['name']);
        $categoryId   = (int)($_POST['category_id'] ?? $variant['category_id']);
        $subcatId     = (int)($_POST['subcategory_id'] ?? $variant['subcategory_id']);
        $brandId      = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : $variant['brand_id'];
        $size         = trim($_POST['size'] ?? $variant['size']);
        $weight       = (float)($_POST['weight'] ?? $variant['weight']);
        $weightUnit   = trim($_POST['weight_unit'] ?? $variant['weight_unit']);
        $price        = (float)($_POST['price'] ?? $variant['price']);
        $mrp          = (float)($_POST['mrp'] ?? $variant['mrp']);
        $status       = trim($_POST['status'] ?? $variant['status']);

        $color  = trim($_POST['attr_color'] ?? '');
        $fabric = trim($_POST['attr_fabric'] ?? '');
        $gsm    = trim($_POST['attr_gsm'] ?? '');
        $attributes = json_decode($variant['attributes'] ?? '{}', true) ?: [];
        if ($color)  $attributes['color'] = $color;
        if ($fabric) $attributes['fabric'] = $fabric;
        if ($gsm)    $attributes['gsm'] = $gsm;

        try {
            DB::query(
                "UPDATE variants SET product_code = ?, name = ?, category_id = ?, subcategory_id = ?, brand_id = ?, size = ?, weight = ?, weight_unit = ?, price = ?, mrp = ?, attributes = ?, status = ? WHERE id = ?",
                [$sku, $name, $categoryId, $subcatId, $brandId, $size, $weight, $weightUnit, $price, $mrp, !empty($attributes) ? json_encode($attributes) : null, $status, $id]
            );
            $this->flash('success', "Variant '{$name}' ({$sku}) updated successfully.");
        } catch (Exception $e) {
            $this->flash('error', 'Failed to update variant: ' . $e->getMessage());
        }

        $this->redirect('/m/variants');
    }

    public function delete(): void {
        ModuleGuard::enforce('variants', 'delete');
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $variant = DB::fetch("SELECT * FROM variants WHERE id = ?", [$id]);
        if (!$variant) {
            $this->flash('error', 'Variant not found.');
            $this->redirect('/m/variants');
        }

        DB::beginTransaction();
        try {
            DB::query("DELETE FROM stock_ledger WHERE variant_id = ?", [$id]);
            DB::query("DELETE FROM stocks WHERE variant_id = ?", [$id]);
            DB::query("DELETE FROM variants WHERE id = ?", [$id]);
            DB::commit();
            $this->flash('success', "Variant '{$variant['name']}' ({$variant['product_code']}) deleted successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            DB::query("UPDATE variants SET status = 'inactive' WHERE id = ?", [$id]);
            $this->flash('warning', "Variant '{$variant['name']}' is referenced in commercial invoices/bills, so it has been set to Inactive to protect accounting records.");
        }

        $this->redirect('/m/variants');
    }

    public function exportVariants(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT v.*, c.name AS category_name, sc.name AS subcategory_name, b.name AS brand_name,
                       con.name AS concern_name, con.code AS concern_code
                FROM variants v
                LEFT JOIN categories c ON v.category_id = c.id
                LEFT JOIN subcategories sc ON v.subcategory_id = sc.id
                LEFT JOIN brands b ON v.brand_id = b.id
                LEFT JOIN concerns con ON v.concern_id = con.id";
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "v.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(v.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY v.id DESC";
        $variants = DB::fetchAll($sql, $params);

        $headers = [
            'Product Code', 'Variant Name', 'Brand', 'Category', 'Subcategory', 
            'Size', 'Weight / Unit', 'Cost Price (₹)', 'Selling MRP (₹)', 'Status', 'Concern'
        ];

        $rows = [];
        foreach ($variants as $v) {
            $rows[] = [
                $v['product_code'],
                $v['name'],
                $v['brand_name'] ?: '-',
                $v['category_name'] ?: 'General',
                $v['subcategory_name'] ?: '-',
                $v['size'] ?: '-',
                $v['weight'] . ' ' . ($v['weight_unit'] ?: 'Pcs'),
                '₹' . number_format((float)$v['price'], 2),
                '₹' . number_format((float)$v['mrp'], 2),
                strtoupper($v['status'] ?? 'ACTIVE'),
                $v['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total Variants: ' . count($variants), '', '', '', '', '', '', '', '', '', ''];
        $metadata = [
            'Report Title' => 'Product Variant & Master Product Catalog Directory',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Variants' => count($variants)
        ];

        $filename = 'variants_catalog_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Product Variants Catalog Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Product Variants Catalog Report', $metadata, $headers, $rows, $totals);
        }
    }
}
