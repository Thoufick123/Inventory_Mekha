<?php
// app/controllers/StockController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Core\ModuleGuard;
use App\Services\StockService;

class StockController extends Controller {
    public function index(): void {
        $cid = Auth::concernId();

        $sql = "SELECT s.*, v.name AS variant_name, v.sku_code, v.size, v.weight, v.weight_unit, v.cost_price, v.price, v.mrp, v.catalog_id,
                       v.category_id, v.subcategory_id, v.is_complementary,
                       v.attributes AS variant_attributes,
                       c.name AS category_name, sub.name AS subcategory_name,
                       con.name AS concern_name, con.code AS concern_code,
                       (SELECT pbi.id
                        FROM purchase_bill_items pbi
                        WHERE pbi.concern_id = s.concern_id
                          AND pbi.section_type = 'finished_goods'
                          AND COALESCE(pbi.inward_status, 'pending') = 'completed'
                          AND (pbi.variant_id = v.id OR (v.catalog_id IS NOT NULL AND pbi.catalog_id = v.catalog_id))
                          AND (latest_purchase.ref_module IS NULL OR latest_purchase.ref_module <> 'Purchase Inward Item' OR pbi.id = latest_purchase.ref_id)
                          AND (latest_purchase.ref_module IS NULL OR latest_purchase.ref_module <> 'Purchase Inward' OR pbi.purchase_bill_id = latest_purchase.ref_id)
                        ORDER BY CASE
                                   WHEN latest_purchase.ref_module = 'Purchase Inward Item' AND pbi.id = latest_purchase.ref_id THEN 0
                                   WHEN pbi.variant_id = v.id THEN 1
                                   ELSE 2
                                 END,
                                 pbi.id DESC
                        LIMIT 1) AS purchase_item_id,
                       CASE COALESCE(latest_inbound.ref_module, latest_variant_source.ref_module)
                           WHEN 'Production Inward' THEN 'own'
                           WHEN 'Production Stock Adjustment' THEN 'own'
                           WHEN 'Purchase Inward' THEN 'purchase'
                           WHEN 'Purchase Inward Item' THEN 'purchase'
                           WHEN 'Purchase Stock Adjustment' THEN 'purchase'
                           ELSE 'other'
                       END AS stock_source
                FROM stocks s
                JOIN variants v ON s.variant_id = v.id
                LEFT JOIN concerns con ON s.concern_id = con.id
                LEFT JOIN categories c ON v.category_id = c.id
                LEFT JOIN subcategories sub ON v.subcategory_id = sub.id
                LEFT JOIN (
                    SELECT inbound.concern_id, inbound.variant_id, inbound.location, inbound.ref_module, inbound.ref_id
                    FROM stock_ledger inbound
                    JOIN (
                        SELECT concern_id, variant_id, location, MAX(id) AS latest_id
                        FROM stock_ledger
                        WHERE qty_in > 0
                          AND ref_module IN (
                              'Production Inward', 'Production Stock Adjustment',
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
                              'Production Inward', 'Production Stock Adjustment',
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
        
        $sql .= " WHERE s.qty > 0";
        $params = [];
        if ($cid) {
            $sql .= " AND s.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY FIELD(stock_source, 'own', 'purchase', 'other'), s.qty DESC";
        $stocks = DB::fetchAll($sql, $params);
        $totalUnits = array_sum(array_column($stocks, 'qty'));
        $totalValuation = array_reduce($stocks, fn($sum, $item) => $sum + ($item['qty'] * $item['cost_price']), 0);

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
            'selectedCid'    => $cid,
            'canEditStock'   => Auth::isSuperAdmin() || Auth::can('stocks', 'edit'),
            'canDeleteStock' => Auth::isSuperAdmin() || Auth::can('stocks', 'delete'),
            'categories'     => $categories,
            'subcategories'  => $subcategories,
        ]);
    }

    public function details(): void
    {
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
                "SELECT v.*, c.name AS category_name, sub.name AS subcategory_name, con.name AS concern_name, con.code AS concern_code
                 FROM variants v
                 LEFT JOIN categories c ON v.category_id = c.id
                 LEFT JOIN subcategories sub ON v.subcategory_id = sub.id
                 LEFT JOIN concerns con ON v.concern_id = con.id
                 WHERE v.id = ?" . ($concernId ? " AND v.concern_id = ?" : ""),
                $concernId ? [$variantId, $concernId] : [$variantId]
            );

            if (!$variant) {
                $this->renderJson(['success' => false, 'message' => 'Product variant not found.'], 404);
                return;
            }

            $concernId = (int)$variant['concern_id'];

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

            $catId = (int)($variant['catalog_id'] ?? 0);
            $purchaseBill = DB::fetch(
                "SELECT pbi.id AS purchase_item_id, pbi.item_name, pbi.item_code, pbi.hsn_code AS item_hsn,
                        pbi.color AS item_color, pbi.specification AS item_spec, pbi.qty AS item_qty,
                        pbi.unit_price AS item_unit_price, pbi.gst_rate AS item_gst_rate, pbi.gst_type AS item_gst_type,
                        pbi.tax_mode AS item_tax_mode, pbi.tax_amount AS item_tax_amount, pbi.total_price AS item_total_price,
                        pbi.mrp AS item_mrp, pbi.selling_price AS item_selling_price, pbi.inward_status AS item_inward_status,
                        pb.id AS purchase_bill_id, pb.bill_no, pb.supplier_invoice_no, pb.bill_date,
                        pb.total_amount AS bill_total, pb.tax_amount AS bill_tax, pb.net_amount AS bill_net,
                        pb.payment_status, pb.payment_method, pb.payment_reference, pb.attachment_path, pb.attachment_paths,
                        pb.notes AS bill_notes,
                        sup.id AS supplier_id, sup.name AS supplier_name, sup.company_name AS supplier_company,
                        sup.phone AS supplier_phone, sup.email AS supplier_email, sup.gstin AS supplier_gstin,
                        sup.address AS supplier_address, sup.city AS supplier_city, sup.district AS supplier_district, sup.state AS supplier_state
                 FROM purchase_bill_items pbi
                 JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
                 LEFT JOIN suppliers sup ON pb.supplier_id = sup.id
                 WHERE pbi.concern_id = ?
                   AND (pbi.variant_id = ? OR (pbi.catalog_id IS NOT NULL AND pbi.catalog_id = ?))
                 ORDER BY CASE WHEN pbi.variant_id = ? THEN 0 ELSE 1 END, pbi.id DESC
                 LIMIT 1",
                [$concernId, $variantId, $catId, $variantId]
            );

            $attachments = [];
            if ($purchaseBill) {
                $paths = json_decode((string)($purchaseBill['attachment_paths'] ?? ''), true);
                if (is_array($paths)) {
                    foreach ($paths as $path) {
                        $pathStr = trim((string)$path);
                        if ($pathStr !== '') {
                            $attachments[] = $pathStr;
                        }
                    }
                }
                if (empty($attachments) && !empty($purchaseBill['attachment_path'])) {
                    $attachments[] = trim((string)$purchaseBill['attachment_path']);
                }
            }

            echo json_encode([
                'success' => true,
                'variant' => [
                    'id' => (int)$variant['id'],
                    'concern_id' => (int)$variant['concern_id'],
                    'concern_name' => $variant['concern_name'] ?? 'Concern #' . $variant['concern_id'],
                    'concern_code' => $variant['concern_code'] ?? '',
                    'name' => $variant['name'],
                    'sku_code' => $variant['sku_code'],
                    'category_id' => $variant['category_id'] ? (int)$variant['category_id'] : null,
                    'category_name' => $variant['category_name'] ?? 'General',
                    'subcategory_id' => $variant['subcategory_id'] ? (int)$variant['subcategory_id'] : null,
                    'subcategory_name' => $variant['subcategory_name'] ?? '-',
                    'size' => $variant['size'] ?: 'Free',
                    'weight' => (float)$variant['weight'],
                    'weight_unit' => $variant['weight_unit'] ?: 'kg',
                    'cost_price' => (float)$variant['cost_price'],
                    'price' => (float)$variant['price'],
                    'mrp' => (float)$variant['mrp'],
                    'color' => (string)($variantAttributes['color'] ?? ''),
                    'hsn' => (string)($variantAttributes['hsn'] ?? ''),
                    'attributes' => $variantAttributes,
                ],
                'stock' => $stock ? [
                    'location' => $stock['location'],
                    'qty' => (float)$stock['qty'],
                    'net_value' => (float)$stock['qty'] * (float)$variant['cost_price'],
                    'latest_inbound_module' => $stock['latest_inbound_module'],
                ] : null,
                'total_stock_qty' => (float)($totalStock['total_qty'] ?? 0),
                'purchase_bill' => $purchaseBill ? [
                    'bill_id' => (int)$purchaseBill['purchase_bill_id'],
                    'bill_no' => $purchaseBill['bill_no'],
                    'supplier_invoice_no' => $purchaseBill['supplier_invoice_no'] ?: '-',
                    'bill_date' => $purchaseBill['bill_date'],
                    'total_amount' => (float)$purchaseBill['bill_total'],
                    'tax_amount' => (float)$purchaseBill['bill_tax'],
                    'net_amount' => (float)$purchaseBill['bill_net'],
                    'payment_status' => $purchaseBill['payment_status'] ?: 'Pending',
                    'payment_method' => $purchaseBill['payment_method'] ?: 'Cash',
                    'payment_reference' => $purchaseBill['payment_reference'] ?: '-',
                    'notes' => $purchaseBill['bill_notes'] ?: '',
                    'item_name' => $purchaseBill['item_name'],
                    'item_code' => $purchaseBill['item_code'] ?: '-',
                    'item_hsn' => $purchaseBill['item_hsn'] ?: '-',
                    'item_color' => $purchaseBill['item_color'] ?: '-',
                    'item_spec' => $purchaseBill['item_spec'] ?: '-',
                    'item_qty' => (float)$purchaseBill['item_qty'],
                    'item_unit_price' => (float)$purchaseBill['item_unit_price'],
                    'item_gst_rate' => (float)$purchaseBill['item_gst_rate'],
                    'item_gst_type' => $purchaseBill['item_gst_type'] ?: 'Standard',
                    'item_tax_mode' => $purchaseBill['item_tax_mode'] ?: 'Exclude GST',
                    'item_tax_amount' => (float)$purchaseBill['item_tax_amount'],
                    'item_total_price' => (float)$purchaseBill['item_total_price'],
                    'attachments' => $attachments,
                    'supplier' => [
                        'id' => (int)$purchaseBill['supplier_id'],
                        'name' => $purchaseBill['supplier_name'] ?: 'Unknown Vendor',
                        'company_name' => $purchaseBill['supplier_company'] ?: '-',
                        'phone' => $purchaseBill['supplier_phone'] ?: '-',
                        'email' => $purchaseBill['supplier_email'] ?: '-',
                        'gstin' => $purchaseBill['supplier_gstin'] ?: '-',
                        'address' => $purchaseBill['supplier_address'] ?: '-',
                        'city' => $purchaseBill['supplier_city'] ?: '-',
                        'district' => $purchaseBill['supplier_district'] ?: '-',
                        'state' => $purchaseBill['supplier_state'] ?: '-',
                    ],
                ] : null,
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

            $skuCode = trim((string)($_POST['sku_code'] ?? ''));
            if ($skuCode === '' || strlen($skuCode) > 100) {
                throw new \InvalidArgumentException('SKU code is required and cannot exceed 100 characters.');
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
                "SELECT id FROM variants WHERE concern_id = ? AND sku_code = ? AND id != ?",
                [$concernId, $skuCode, $variantId]
            );
            if ($existingSku) {
                throw new \InvalidArgumentException("SKU code '{$skuCode}' is already used by another variant.");
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
                 SET name = ?, sku_code = ?, size = ?, weight = ?, weight_unit = ?,
                     cost_price = ?, price = ?, mrp = ?, category_id = ?, subcategory_id = ?, attributes = ?
                 WHERE id = ? AND concern_id = ?",
                [
                    $variantName, $skuCode, $size, $weight, $weightUnit,
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
                    $variantName, $skuCode, $hsnCode, $color, $size,
                    $costPrice, $mrp, $price,
                    $concernId, $variantId, $catalogId ?: 0
                ]
            );

            $currentQuantity = (float)$stock['qty'];
            $targetQuantity = (float)$newQuantity;
            $difference = $targetQuantity - $currentQuantity;
            if (abs($difference) > 0.00001) {
                $refModule = $this->stockAdjustmentModule((string)$stock['latest_inbound_module']);
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
                $refModule = $this->stockAdjustmentModule((string)$stock['latest_inbound_module']);
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

    private function stockAdjustmentModule(string $latestInboundModule): string
    {
        if (in_array($latestInboundModule, ['Production Inward', 'Production Stock Adjustment'], true)) {
            return 'Production Stock Adjustment';
        }
        if (in_array($latestInboundModule, ['Purchase Inward', 'Purchase Inward Item', 'Purchase Stock Adjustment'], true)) {
            return 'Purchase Stock Adjustment';
        }
        throw new \RuntimeException('Only own-product and purchase-product stock can be adjusted here.');
    }

    public function ledger(): void {
        $cid = Auth::concernId();

        $sql = "SELECT sl.*, v.name AS variant_name, v.sku_code, v.weight_unit, u.name AS user_name,
                       con.name AS concern_name, con.code AS concern_code
                FROM stock_ledger sl
                JOIN variants v ON sl.variant_id = v.id
                LEFT JOIN concerns con ON sl.concern_id = con.id
                LEFT JOIN users u ON sl.created_by = u.id";
        
        $params = [];
        if ($cid) {
            $sql .= " WHERE sl.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY sl.id DESC";
        $ledger = DB::fetchAll($sql, $params);

        $this->render('concern/stock_ledger', [
            'pageTitle'     => 'Stock Movement Ledger (Audit Trail)',
            'currentModule' => 'stocks',
            'ledger'        => $ledger,
            'selectedCid'   => $cid,
        ]);
    }

    public function exportStocks(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT s.*, v.name AS variant_name, v.sku_code, v.size, v.weight, v.weight_unit, v.cost_price, v.price, v.mrp,
                       c.name AS category_name, sub.name AS subcategory_name,
                       con.name AS concern_name, con.code AS concern_code
                FROM stocks s
                JOIN variants v ON s.variant_id = v.id
                LEFT JOIN concerns con ON s.concern_id = con.id
                LEFT JOIN categories c ON v.category_id = c.id
                LEFT JOIN subcategories sub ON v.subcategory_id = sub.id";
        
        $params = [];
        $where = [];
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
            'SKU Code', 'Variant Name', 'Category', 'Subcategory', 'Quantity', 'Unit', 
            'Cost Price (₹)', 'Selling Price (₹)', 'Selling MRP (₹)', 'Cost Valuation (₹)', 'Concern'
        ];

        $rows = [];
        $totalQty = 0;
        $totalVal = 0;
        foreach ($stocks as $s) {
            $val = (float)$s['qty'] * (float)$s['cost_price'];
            $totalQty += (float)$s['qty'];
            $totalVal += $val;
            $rows[] = [
                $s['sku_code'],
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
            'Total SKUs' => count($stocks),
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

        $sql = "SELECT sl.*, v.name AS variant_name, v.sku_code, v.weight_unit, u.name AS user_name,
                       con.name AS concern_name, con.code AS concern_code
                FROM stock_ledger sl
                JOIN variants v ON sl.variant_id = v.id
                LEFT JOIN concerns con ON sl.concern_id = con.id
                LEFT JOIN users u ON sl.created_by = u.id";

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

        $headers = [
            'ID', 'Date & Time', 'SKU Code', 'Variant Name', 'Tx Type', 
            'Qty In', 'Qty Out', 'Balance', 'Ref Type', 'Ref ID', 'User', 'Concern'
        ];

        $rows = [];
        $totIn = 0;
        $totOut = 0;
        foreach ($ledger as $l) {
            $in = (float)($l['qty_in'] ?? 0);
            $out = (float)($l['qty_out'] ?? 0);
            $totIn += $in;
            $totOut += $out;
            $rows[] = [
                '#' . $l['id'],
                date('d M Y, h:i A', strtotime($l['created_at'])),
                $l['sku_code'],
                $l['variant_name'],
                $l['ref_module'] ?: 'Movement',
                $in,
                $out,
                $l['location'] ?? 'Main Store',
                $l['ref_id'] ? '#' . $l['ref_id'] : '-',
                $l['note'] ?: '-',
                $l['user_name'] ?? 'System',
                $l['concern_name'] ?? 'Consolidated'
            ];
        }

        $headers = [
            'ID', 'Date & Time', 'SKU Code', 'Variant Name', 'Module', 
            'Qty In (+)', 'Qty Out (-)', 'Location', 'Ref ID', 'Notes', 'User', 'Concern'
        ];

        $totals = ['Total: ' . count($ledger) . ' Records', '', '', '', '', $totIn, $totOut, '', '', '', '', ''];
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
