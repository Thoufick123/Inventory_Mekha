<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ModuleGuard;
use App\Services\ExportService;
use App\Services\PurchaseBillSourceService;
use App\Services\PurchaseStockItemOverrideService;
use App\Services\RollRegistryService;
use App\Services\StockService;
use Exception;

class ProductAddingController extends Controller
{
    public function index(): void
    {
        $billId = (int) ($_GET['bill_id'] ?? 0);
        if ($billId > 0 || isset($_GET['variant_ids'])) {
            $this->renderPurchaseForm($billId);
            return;
        }

        $this->renderBillList();
    }

    public function create(): void
    {
        $this->renderPurchaseForm();
    }

    public function export(): void
    {
        $this->ensureSchema();
        PurchaseBillSourceService::ensureSchema();
        $cid = Auth::concernId();

        $format = strtolower(trim((string) ($_GET['format'] ?? 'excel')));
        $period = is_scalar($_GET['period'] ?? null) ? (string) $_GET['period'] : 'all';
        $singleDate = is_scalar($_GET['single_date'] ?? null) ? (string) $_GET['single_date'] : null;
        $fromDate = is_scalar($_GET['from_date'] ?? null) ? (string) $_GET['from_date'] : null;
        $toDate = is_scalar($_GET['to_date'] ?? null) ? (string) $_GET['to_date'] : null;
        $dateScope = ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $itemStatsSql = "SELECT pbi.purchase_bill_id,
                                COUNT(*) AS item_count,
                                COALESCE(SUM(pbi.qty), 0) AS total_qty,
                                SUM(CASE
                                    WHEN COALESCE(NULLIF(pbi.inward_status, ''), status_bill.inward_status, 'pending') = 'completed'
                                    THEN 1 ELSE 0
                                END) AS stocked_count
                         FROM purchase_bill_items pbi
                         JOIN purchase_bills status_bill ON status_bill.id = pbi.purchase_bill_id
                         GROUP BY pbi.purchase_bill_id";
        $eligibleBillsSql = "pb.source_module = 'product_adding'";

        PurchaseBillSourceService::ensureSchema();
        $billSql = "SELECT pb.id, pb.concern_id, pb.supplier_id, pb.bill_no, pb.supplier_invoice_no, pb.bill_date,
                           pb.total_amount, pb.tax_amount, pb.net_amount, pb.payment_status, pb.inward_status,
                           s.name AS supplier_name, s.company_name AS supplier_company,
                           c.name AS concern_name,
                           pb.source_module, pb.purchase_billing_completed, pb.product_adding_completed,
                           COALESCE(item_stats.item_count, 0) AS item_count,
                           COALESCE(item_stats.total_qty, 0) AS total_qty,
                           COALESCE(item_stats.stocked_count, 0) AS stocked_count
                    FROM purchase_bills pb
                    LEFT JOIN suppliers s ON s.id = pb.supplier_id
                    LEFT JOIN concerns c ON c.id = pb.concern_id
                    LEFT JOIN ({$itemStatsSql}) item_stats ON item_stats.purchase_bill_id = pb.id
                    WHERE {$eligibleBillsSql}";
        $billParams = [];
        if ($cid) {
            $billSql .= ' AND pb.concern_id = ?';
            $billParams[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $billSql .= ' AND DATE(pb.bill_date) BETWEEN ? AND ?';
            $billParams[] = $dateScope['start_date'];
            $billParams[] = $dateScope['end_date'];
        }
        $billSql .= ' ORDER BY pb.id DESC';
        $bills = PurchaseBillSourceService::attachInvoiceSyncStatus(DB::fetchAll($billSql, $billParams), $cid);

        $headers = [
            'Bill No.', 'Invoice No.', 'Supplier', 'Concern', 'Bill Date',
            'Lines', 'Total Qty', 'Taxable (INR)', 'GST (INR)', 'Net Amount (INR)',
            'Payment Status', 'Stock Status'
        ];

        $rows = [];
        $sumQty = 0;
        $sumTaxable = 0;
        $sumTax = 0;
        $sumNet = 0;

        foreach ($bills as $b) {
            $qty = (float) ($b['total_qty'] ?? 0);
            $taxable = (float) ($b['total_amount'] ?? 0);
            $tax = (float) ($b['tax_amount'] ?? 0);
            $net = (float) ($b['net_amount'] ?? 0);

            $sumQty += $qty;
            $sumTaxable += $taxable;
            $sumTax += $tax;
            $sumNet += $net;

            $stockStatus = 'Pending';
            if (($b['inward_status'] ?? '') === 'completed' || ($b['stocked_count'] > 0 && $b['stocked_count'] >= $b['item_count'])) {
                $stockStatus = 'Stocked';
            } elseif (($b['stocked_count'] ?? 0) > 0) {
                $stockStatus = 'Partially Stocked';
            }

            $rows[] = [
                $b['bill_no'],
                $b['supplier_invoice_no'] ?: '-',
                $b['supplier_name'] ?: ($b['supplier_company'] ?: '-'),
                $b['concern_name'] ?? 'Consolidated',
                $b['bill_date'] ? date('d M Y', strtotime($b['bill_date'])) : '-',
                (int) ($b['item_count'] ?? 0),
                $qty,
                number_format($taxable, 2),
                number_format($tax, 2),
                number_format($net, 2),
                strtoupper($b['payment_status'] ?? 'PENDING'),
                $stockStatus
            ];
        }

        $totals = [
            'Total Bills: ' . count($bills), '', '', '', '',
            '', $sumQty, number_format($sumTaxable, 2), number_format($sumTax, 2), number_format($sumNet, 2),
            '', ''
        ];

        $metadata = [
            'Report Title' => 'Product Adding & Purchase Bills Register',
            'Scope / Period' => $dateScope['label'] ?? 'All Time',
            'Generated At' => date('d M Y, h:i A'),
            'Total Bills' => count($bills),
            'Total Net Purchases' => 'INR ' . number_format($sumNet, 2),
            'Concern' => $cid ? ($bills[0]['concern_name'] ?? 'Concern #' . $cid) : 'All Concerns'
        ];

        $filename = 'product_adding_bills_' . date('Ymd_His');

        if ($format === 'pdf') {
            ExportService::renderPdfView('Product Adding & Purchase Bills Register', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            ExportService::downloadExcel($filename, 'Product Adding & Purchase Bills Register', $metadata, $headers, $rows, $totals);
        }
    }

    private function renderPurchaseForm(int $billId = 0): void
    {
        $this->ensureSchema();
        PurchaseBillSourceService::ensureSchema();
        $cid = Auth::concernId();
        $savedBill = null;
        $savedBillItems = [];
        if ($billId > 0) {
            $billSql = "SELECT pb.*, s.name AS supplier_name, s.company_name AS supplier_company,
                               c.name AS concern_name, c.code AS concern_code
                        FROM purchase_bills pb
                        LEFT JOIN suppliers s ON s.id = pb.supplier_id
                        LEFT JOIN concerns c ON c.id = pb.concern_id
                        WHERE pb.id = ? AND pb.source_module IN ('product_adding', 'purchase_billing')";
            $billParams = [$billId];
            if ($cid) {
                $billSql .= ' AND pb.concern_id = ?';
                $billParams[] = $cid;
            }
            $savedBill = DB::fetch($billSql, $billParams);
            if ($savedBill) {
                PurchaseStockItemOverrideService::initTable();
                $savedBillItems = DB::fetchAll(
                    "SELECT pbi.*, pbi.qty AS purchase_qty_original,
                            v.size AS variant_size,
                            v.attributes AS variant_attributes,
                            COALESCE((
                                SELECT lj.qty
                                FROM label_jobs lj
                                WHERE lj.ref_id = pbi.id
                                  AND lj.concern_id = pbi.concern_id
                                  AND lj.source_module = 'product_adding'
                                  AND lj.label_type = CASE
                                      WHEN pbi.section_type = 'finished_goods' THEN 'purchase_fg'
                                      ELSE 'purchase_raw'
                                  END
                                  AND lj.id > COALESCE(pso.label_job_baseline_id, 0)
                                ORDER BY lj.id DESC
                                LIMIT 1
                            ), 0) AS generated_label_qty,
                            COALESCE((
                                SELECT SUM(sl.qty_in - sl.qty_out)
                                FROM stock_ledger sl
                                WHERE sl.concern_id = pbi.concern_id
                                  AND sl.variant_id = pbi.variant_id
                                  AND sl.ref_id = pbi.id
                                  AND sl.ref_module IN ('Purchase Inward Item', 'Product Adding Stock Correction')
                            ), 0) AS finished_goods_inward_qty
                     FROM purchase_bill_items pbi
                     LEFT JOIN variants v ON v.id = pbi.variant_id
                     LEFT JOIN purchase_stock_item_overrides pso
                       ON pso.purchase_bill_item_id = pbi.id AND pso.concern_id = pbi.concern_id
                     WHERE pbi.purchase_bill_id = ?
                     ORDER BY pbi.id ASC",
                    [$billId]
                );
                $savedBillItems = PurchaseStockItemOverrideService::applyToItems($savedBillItems, false);
                foreach ($savedBillItems as &$savedItem) {
                    $savedItem['required_label_qty'] = (int) ceil((float) ($savedItem['purchase_qty_original'] ?? $savedItem['qty']));
                    $savedItem['generated_label_qty'] = max(0, (int) $savedItem['generated_label_qty']);
                    $savedItem['labels_complete'] = $savedItem['generated_label_qty'] > 0;
                    $savedItem['stocked_qty'] = $savedItem['section_type'] === 'finished_goods'
                        ? min((float) $savedItem['qty'], max(0, (float) $savedItem['finished_goods_inward_qty']))
                        : (float) $savedItem['qty'];
                    $savedItem['default_label_qty'] = $savedItem['generated_label_qty'] > 0
                        ? $savedItem['generated_label_qty']
                        : $savedItem['required_label_qty'];
                }
                unset($savedItem);
            }
        }
        if ($cid) {
            $suppliers = DB::fetchAll("SELECT id, concern_id, code, name, company_name, phone, email, gstin, address, district, state FROM suppliers WHERE concern_id = ? AND status = 'active' ORDER BY name ASC", [$cid]);
            $variants = DB::fetchAll("SELECT id, product_code, name, price, size FROM variants WHERE concern_id = ? AND status = 'active' ORDER BY name ASC", [$cid]);
            $hsnList = DB::fetchAll("SELECT code_type, hsn_code, description, category, gst_rate, rate_note FROM hsn_masters WHERE is_active = 1 AND code_type = 'HSN' AND (concern_id = ? OR concern_id IS NULL) ORDER BY hsn_code ASC", [$cid]);
        } else {
            $suppliers = DB::fetchAll("SELECT id, concern_id, code, name, company_name, phone, email, gstin, address, district, state FROM suppliers WHERE status = 'active' ORDER BY name ASC");
            $variants = DB::fetchAll("SELECT id, concern_id, product_code, name, price, size FROM variants WHERE status = 'active' ORDER BY name ASC");
            $hsnList = DB::fetchAll("SELECT code_type, hsn_code, description, category, gst_rate, rate_note FROM hsn_masters WHERE is_active = 1 AND code_type = 'HSN' ORDER BY hsn_code ASC");
        }

        $allConcerns = Auth::isSuperAdmin()
            ? DB::fetchAll('SELECT id, name, code FROM concerns ORDER BY id ASC')
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
                ? 'SELECT concern_id, category_id, subcategory_id, sub_sub_category_id, attribute_type, attribute_value FROM product_attributes WHERE concern_id = ? OR concern_id = 1 OR concern_id IS NULL ORDER BY attribute_type, sort_order'
                : 'SELECT concern_id, category_id, subcategory_id, sub_sub_category_id, attribute_type, attribute_value FROM product_attributes ORDER BY attribute_type, sort_order',
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
            'quickSupplierUpdateUrl' => \App\Core\Controller::url('/m/product_adding/suppliers/update'),
            'quickSupplierDeleteUrl' => \App\Core\Controller::url('/m/product_adding/suppliers/delete'),
            'quickCategorySaveUrl' => \App\Core\Controller::url('/m/product_adding/categories/store'),
            'quickAttributeSaveUrl' => \App\Core\Controller::url('/m/product_adding/attributes/store'),
            'allowQuickSupplier' => Auth::isSuperAdmin() || Auth::can('product_adding', 'add'),
            'backUrl' => \App\Core\Controller::url('/m/product_adding'),
            'backLabel' => 'Back to Product Adding',
            'submitLabel' => 'Save Product Purchase',
            'pageHeading' => 'Product Adding',
            'pageDescription' => 'Record fabric rolls, garment supplies/accessories and finished goods received from vendors.',
            'savedBill' => $savedBill,
            'savedBillItems' => $savedBillItems,
            'productAddingLabelUrl' => \App\Core\Controller::url('/m/product_adding/generate_labels'),
            'productAddingStockUrl' => \App\Core\Controller::url('/m/product_adding/add_to_stock'),
            'productAddingCorrectionUrl' => \App\Core\Controller::url('/m/product_adding/correct_stock_quantity'),
            'invoiceCheckUrl' => \App\Core\Controller::url('/m/product_adding/check_invoice'),
        ]);
    }

    private function renderBillList(): void
    {
        $this->ensureSchema();
        PurchaseBillSourceService::ensureSchema();
        $cid = Auth::concernId();

        $itemStatsSql = "SELECT pbi.purchase_bill_id,
                                COUNT(*) AS item_count,
                                COALESCE(SUM(pbi.qty), 0) AS total_qty,
                                SUM(CASE
                                    WHEN COALESCE(NULLIF(pbi.inward_status, ''), status_bill.inward_status, 'pending') = 'completed'
                                    THEN 1 ELSE 0
                                END) AS stocked_count
                         FROM purchase_bill_items pbi
                         JOIN purchase_bills status_bill ON status_bill.id = pbi.purchase_bill_id
                         GROUP BY pbi.purchase_bill_id";
        $eligibleBillsSql = "pb.source_module = 'product_adding'";

        $summarySql = "SELECT COUNT(*) AS total_bills,
                              COALESCE(SUM(pb.net_amount), 0) AS total_net,
                              COALESCE(SUM(pb.tax_amount), 0) AS total_tax,
                              COALESCE(SUM(CASE WHEN COALESCE(pb.inward_status, 'pending') != 'completed' THEN 1 ELSE 0 END), 0) AS pending_bills,
                              COALESCE(SUM(item_stats.item_count), 0) AS total_items
                        FROM purchase_bills pb
                        LEFT JOIN ({$itemStatsSql}) item_stats ON item_stats.purchase_bill_id = pb.id
                        WHERE {$eligibleBillsSql}";
        $summaryParams = [];
        if ($cid) {
            $summarySql .= ' AND pb.concern_id = ?';
            $summaryParams[] = $cid;
        }
        $summary = DB::fetch($summarySql, $summaryParams) ?: [];

        PurchaseBillSourceService::ensureSchema();
        $billSql = "SELECT pb.id, pb.concern_id, pb.supplier_id, pb.bill_no, pb.supplier_invoice_no, pb.bill_date,
                           pb.total_amount, pb.tax_amount, pb.net_amount, pb.payment_status, pb.inward_status,
                           s.name AS supplier_name, s.company_name AS supplier_company,
                           c.name AS concern_name,
                           pb.source_module, pb.purchase_billing_completed, pb.product_adding_completed,
                           COALESCE(item_stats.item_count, 0) AS item_count,
                           COALESCE(item_stats.total_qty, 0) AS total_qty,
                           COALESCE(item_stats.stocked_count, 0) AS stocked_count
                    FROM purchase_bills pb
                    LEFT JOIN suppliers s ON s.id = pb.supplier_id
                    LEFT JOIN concerns c ON c.id = pb.concern_id
                    LEFT JOIN ({$itemStatsSql}) item_stats ON item_stats.purchase_bill_id = pb.id
                    WHERE {$eligibleBillsSql}";
        $billParams = [];
        if ($cid) {
            $billSql .= ' AND pb.concern_id = ?';
            $billParams[] = $cid;
        }
        $billSql .= ' ORDER BY pb.id DESC';
        $bills = PurchaseBillSourceService::attachInvoiceSyncStatus(DB::fetchAll($billSql, $billParams), $cid);

        // Fetch 3-Tier Hierarchy, Attributes & HSN Master for Product Adding
        if ($cid) {
            $categories = DB::fetchAll('SELECT * FROM categories WHERE concern_id = ? OR concern_id = 1 OR concern_id IS NULL ORDER BY name ASC', [$cid]);
            $subcategories = DB::fetchAll('SELECT s.*, c.name AS category_name FROM subcategories s JOIN categories c ON s.category_id = c.id WHERE s.concern_id = ? OR s.concern_id = 1 OR s.concern_id IS NULL ORDER BY s.name ASC', [$cid]);
            $subSubcategories = DB::fetchAll('SELECT ss.*, s.name AS subcategory_name, c.name AS category_name FROM sub_subcategories ss LEFT JOIN subcategories s ON ss.subcategory_id = s.id LEFT JOIN categories c ON s.category_id = c.id WHERE ss.concern_id = ? OR ss.concern_id = 1 OR ss.concern_id IS NULL ORDER BY ss.name ASC', [$cid]);
            $attributes = DB::fetchAll('SELECT * FROM product_attributes WHERE concern_id = ? OR concern_id = 1 OR concern_id IS NULL ORDER BY attribute_type ASC, sort_order ASC', [$cid]);
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
                   WHERE hm.is_active = 1";
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

        $activeTab = $_GET['tab'] ?? 'purchases';
        $variantDetails = \App\Services\VariantService::getVariantDetailsList($cid);

        $this->render('concern/product_adding_list', [
            'pageTitle' => 'Product Adding',
            'currentModule' => 'product_adding',
            'selectedCid' => $cid,
            'summary' => $summary,
            'bills' => $bills,
            'createUrl' => \App\Core\Controller::url('/m/product_adding/create'),
            'activeTab' => $activeTab,
            'categories' => $categories,
            'subcategories' => $subcategories,
            'subSubcategories' => $subSubcategories,
            'attributes' => $attributes,
            'attributeTypes' => $attributeTypes,
            'hsnCodes' => $hsnCodes,
            'hsnCategories' => $hsnCategories,
            'variantDetails' => $variantDetails,
            'allConcerns' => Auth::isSuperAdmin() ? Auth::allConcerns() : [],
        ]);
    }

    public function subcategories(): void
    {
        $categoryId = (int) ($_GET['category_id'] ?? 0);
        $cid = Auth::concernId();
        $sql = "SELECT id, name, code FROM subcategories WHERE category_id = ? AND status = 'active'";
        $params = [$categoryId];
        if ($cid) {
            $sql .= ' AND concern_id = ?';
            $params[] = $cid;
        }
        $sql .= ' ORDER BY name ASC';
        $this->renderJson(['success' => true, 'items' => DB::fetchAll($sql, $params)]);
    }

    public function subSubcategories(): void
    {
        $subcategoryId = (int) ($_GET['subcategory_id'] ?? 0);
        $cid = Auth::concernId();
        $sql = "SELECT id, name, code FROM sub_subcategories WHERE subcategory_id = ? AND status = 'active'";
        $params = [$subcategoryId];
        if ($cid) {
            $sql .= ' AND concern_id = ?';
            $params[] = $cid;
        }
        $sql .= ' ORDER BY name ASC';
        $this->renderJson(['success' => true, 'items' => DB::fetchAll($sql, $params)]);
    }

    public function storeHierarchyAjax(): void
    {
        $this->validateCsrf();
        header('Content-Type: application/json');

        $userConcernId = Auth::concernId();
        $requestedConcernId = (int) ($_POST['concern_id'] ?? 0);
        $concernId = Auth::isSuperAdmin()
            ? ($requestedConcernId ?: (int) ($userConcernId ?? 0))
            : (int) ($userConcernId ?? 0);
        $tier = trim((string) ($_POST['tier'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));

        $respond = static function (bool $success, array $data = [], int $status = 200): void {
            http_response_code($status);
            echo json_encode(array_merge(['success' => $success], $data));
            exit;
        };

        if ($concernId <= 0 || (!Auth::isSuperAdmin() && $requestedConcernId && $requestedConcernId !== $concernId)) {
            $respond(false, ['message' => 'Choose a valid concern before adding a category.'], 422);
        }
        if (!DB::fetchValue('SELECT id FROM concerns WHERE id = ?', [$concernId])) {
            $respond(false, ['message' => 'The selected concern does not exist.'], 422);
        }
        if (!in_array($tier, ['category', 'subcategory', 'subsubcategory'], true)) {
            $respond(false, ['message' => 'Choose a valid category level.'], 422);
        }
        if ($name === '' || mb_strlen($name) > 100) {
            $respond(false, ['message' => 'Enter a category name of 1 to 100 characters.'], 422);
        }
        if ($code === '') {
            $code = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $name) ?: 'NEW', 0, 10));
        }

        try {
            if ($tier === 'category') {
                $duplicate = DB::fetch(
                    "SELECT id, name FROM categories
                     WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)
                       AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND status != 'deleted'
                     LIMIT 1",
                    [$concernId, $name]
                );
                if ($duplicate) {
                    $respond(false, ['message' => 'That category already exists.', 'existing_id' => (int) $duplicate['id']], 409);
                }
                $dupCode = DB::fetch(
                    "SELECT id FROM categories
                     WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)
                       AND LOWER(TRIM(code)) = LOWER(TRIM(?)) AND status != 'deleted'
                     LIMIT 1",
                    [$concernId, $code]
                );
                if ($dupCode) {
                    $respond(false, ['message' => "Category code '{$code}' already exists. Duplicates are not allowed.", 'existing_id' => (int) $dupCode['id']], 409);
                }

                DB::query(
                    "INSERT INTO categories (concern_id, name, code, default_hsn, default_gst_rate, status, created_at)
                     VALUES (?, ?, ?, NULL, NULL, 'active', NOW())",
                    [$concernId, $name, $code]
                );
                $parentId = null;
                $id = (int) DB::lastInsertId();
                $categoryId = $id;
            } elseif ($tier === 'subcategory') {
                $parentId = (int) ($_POST['category_id'] ?? 0);
                $parent = DB::fetch(
                    "SELECT id FROM categories
                     WHERE id = ? AND status = 'active'
                       AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
                    [$parentId, $concernId]
                );
                if (!$parent) {
                    $respond(false, ['message' => 'Select a valid parent category first.'], 422);
                }
                $duplicate = DB::fetch(
                    "SELECT id FROM subcategories
                     WHERE category_id = ? AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)
                       AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND status != 'deleted'
                     LIMIT 1",
                    [$parentId, $concernId, $name]
                );
                if ($duplicate) {
                    $respond(false, ['message' => 'That subcategory already exists under the selected category.', 'existing_id' => (int) $duplicate['id']], 409);
                }
                $dupCode = DB::fetch(
                    "SELECT id FROM subcategories
                     WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)
                       AND LOWER(TRIM(code)) = LOWER(TRIM(?)) AND status != 'deleted'
                     LIMIT 1",
                    [$concernId, $code]
                );
                if ($dupCode) {
                    $respond(false, ['message' => "Subcategory code '{$code}' already exists. Duplicates are not allowed.", 'existing_id' => (int) $dupCode['id']], 409);
                }

                DB::query(
                    "INSERT INTO subcategories (concern_id, category_id, name, code, status, created_at)
                     VALUES (?, ?, ?, ?, 'active', NOW())",
                    [$concernId, $parentId, $name, $code]
                );
                $categoryId = $parentId;
                $id = (int) DB::lastInsertId();
            } else {
                $parentId = (int) ($_POST['subcategory_id'] ?? 0);
                $categoryId = (int) ($_POST['category_id'] ?? 0);
                $parent = DB::fetch(
                    "SELECT id FROM subcategories
                     WHERE id = ? AND category_id = ? AND status = 'active'
                       AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
                    [$parentId, $categoryId, $concernId]
                );
                if (!$parent) {
                    $respond(false, ['message' => 'Select a valid parent subcategory first.'], 422);
                }
                $duplicate = DB::fetch(
                    "SELECT id FROM sub_subcategories
                     WHERE subcategory_id = ? AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)
                       AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND status != 'deleted'
                     LIMIT 1",
                    [$parentId, $concernId, $name]
                );
                if ($duplicate) {
                    $respond(false, ['message' => 'That sub-subcategory already exists under the selected subcategory.', 'existing_id' => (int) $duplicate['id']], 409);
                }
                $dupCode = DB::fetch(
                    "SELECT id FROM sub_subcategories
                     WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)
                       AND LOWER(TRIM(code)) = LOWER(TRIM(?)) AND status != 'deleted'
                     LIMIT 1",
                    [$concernId, $code]
                );
                if ($dupCode) {
                    $respond(false, ['message' => "Sub-subcategory code '{$code}' already exists. Duplicates are not allowed.", 'existing_id' => (int) $dupCode['id']], 409);
                }

                DB::query(
                    "INSERT INTO sub_subcategories (concern_id, subcategory_id, name, code, status, created_at)
                     VALUES (?, ?, ?, ?, 'active', NOW())",
                    [$concernId, $parentId, $name, $code]
                );
                $categoryId = (int) ($_POST['category_id'] ?? 0);
                $id = (int) DB::lastInsertId();
            }

            $respond(true, [
                'id' => $id,
                'name' => $name,
                'code' => $code,
                'tier' => $tier,
                'concern_id' => $concernId,
                'parent_id' => $parentId,
                'category_id' => $tier === 'category' ? $id : ($categoryId ?? $parentId),
            ]);
        } catch (\Throwable $e) {
            error_log('Product Adding hierarchy quick-add failed: ' . $e->getMessage());
            $respond(false, ['message' => 'Unable to save this category. Please try again.'], 500);
        }
    }

    public function storeAttributeAjax(): void
    {
        $this->validateCsrf();
        header('Content-Type: application/json');

        $userConcernId = Auth::concernId();
        $requestedConcernId = (int) ($_POST['concern_id'] ?? 0);
        $concernId = Auth::isSuperAdmin()
            ? ($requestedConcernId ?: (int) ($userConcernId ?? 0))
            : (int) ($userConcernId ?? 0);
        $type = strtolower(trim((string) ($_POST['attribute_type'] ?? '')));
        $attributeName = trim((string) ($_POST['attribute_name'] ?? ''));
        $value = trim((string) ($_POST['attribute_value'] ?? ''));
        $categoryId = max(0, (int) ($_POST['category_id'] ?? 0));
        $subcategoryId = max(0, (int) ($_POST['subcategory_id'] ?? 0));
        $subSubcategoryId = max(0, (int) ($_POST['sub_sub_category_id'] ?? 0));

        $respond = static function (bool $success, array $data = [], int $status = 200): void {
            http_response_code($status);
            echo json_encode(array_merge(['success' => $success], $data));
            exit;
        };

        if ($concernId <= 0 || (!Auth::isSuperAdmin() && $requestedConcernId && $requestedConcernId !== $concernId)) {
            $respond(false, ['message' => 'Choose a valid concern before saving an attribute.'], 422);
        }
        if (!DB::fetchValue('SELECT id FROM concerns WHERE id = ?', [$concernId])) {
            $respond(false, ['message' => 'The selected concern does not exist.'], 422);
        }
        if (!preg_match('/^[a-z][a-z0-9_]{0,49}$/', $type)) {
            $respond(false, ['message' => 'Attribute names must start with a letter and contain only letters, numbers, or underscores.'], 422);
        }
        $reservedTypes = [
            'product',
            'item_name',
            'sku',
            'item_code',
            'variant_id',
            'catalog_id',
            'quantity',
            'qty',
            'supplier_cost',
            'unit_price',
            'hsn',
            'hsn_code',
            'variant',
            'style_code',
            'category_id',
            'subcategory_id',
            'sub_sub_category_id',
            'catalog_gst_rate',
            'tax_mode',
            'gst',
            'gst_type',
            'tax_input_type',
            'gst_rate',
            'sgst',
            'cgst',
            'igst',
            'mrp',
            'selling_price',
            'weight',
            'allocated_qty',
            'custom_attributes',
            'catalog_style_name',
            'variant_name',
        ];
        if (in_array($type, $reservedTypes, true)) {
            $respond(false, ['message' => 'That attribute name is reserved for a built-in product field.'], 422);
        }
        if ($attributeName === '') {
            $attributeName = ucwords(str_replace('_', ' ', $type));
        }
        if (mb_strlen($attributeName) > 100 || $value === '' || mb_strlen($value) > 100) {
            $respond(false, ['message' => 'Enter an attribute label and value of at most 100 characters.'], 422);
        }

        try {
            $existing = DB::fetch(
                'SELECT id, attribute_name, attribute_value FROM product_attributes
                 WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)
                   AND LOWER(TRIM(attribute_type)) = LOWER(TRIM(?))
                   AND LOWER(TRIM(attribute_value)) = LOWER(TRIM(?))
                   AND (category_id IS NULL OR category_id = ?)
                   AND (subcategory_id IS NULL OR subcategory_id = ?)
                   AND (sub_sub_category_id IS NULL OR sub_sub_category_id = ?)
                 LIMIT 1',
                [$concernId, $type, $value, $categoryId ?: null, $subcategoryId ?: null, $subSubcategoryId ?: null]
            );
            if ($existing) {
                $respond(false, [
                    'message' => "Duplicate Error: Attribute value '{$value}' already exists under '{$attributeName}'. Duplicates are not allowed.",
                    'existing_id' => (int) $existing['id'],
                ], 409);
            }

            $sortOrder = (int) DB::fetchValue(
                'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_attributes
                 WHERE concern_id = ? AND LOWER(TRIM(attribute_type)) = LOWER(TRIM(?))',
                [$concernId, $type]
            );
            DB::query(
                'INSERT INTO product_attributes (
                    concern_id, category_id, subcategory_id, sub_sub_category_id,
                    attribute_type, attribute_name, attribute_value, sort_order
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$concernId, $categoryId ?: null, $subcategoryId ?: null, $subSubcategoryId ?: null,
                    $type, $attributeName, $value, $sortOrder]
            );
            $respond(true, [
                'id' => (int) DB::lastInsertId(),
                'concern_id' => $concernId,
                'category_id' => $categoryId ?: null,
                'subcategory_id' => $subcategoryId ?: null,
                'sub_sub_category_id' => $subSubcategoryId ?: null,
                'attribute_type' => $type,
                'attribute_name' => $attributeName,
                'attribute_value' => $value,
                'existing' => false,
            ]);
        } catch (\Throwable $e) {
            error_log('Product Adding attribute quick-add failed: ' . $e->getMessage());
            $respond(false, ['message' => 'Unable to save this attribute. Please try again.'], 500);
        }
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
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $subcategoryId = (int) ($_POST['subcategory_id'] ?? 0) ?: null;
        $subSubcategoryId = (int) ($_POST['sub_sub_category_id'] ?? 0) ?: null;
        $hsnCode = trim($_POST['hsn_code'] ?? '');
        $gstRate = (float) ($_POST['gst_rate'] ?? 0);
        $color = trim($_POST['color'] ?? '');
        $costPrice = (float) ($_POST['cost_price'] ?? 0);
        $mrp = (float) ($_POST['mrp'] ?? 0);
        $sellingPrice = (float) ($_POST['price'] ?? 0);
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
            $cat = DB::fetch('SELECT * FROM categories WHERE id = ? AND (concern_id = ? OR concern_id = 1)', [$categoryId, $cid]);
            if (!$cat) {
                throw new Exception('The selected category is not valid for this concern.');
            }

            $sub = $subcategoryId ? DB::fetch('SELECT * FROM subcategories WHERE id = ? AND (concern_id = ? OR concern_id = 1)', [$subcategoryId, $cid]) : null;
            $subSub = $subSubcategoryId ? DB::fetch('SELECT * FROM sub_subcategories WHERE id = ? AND (concern_id = ? OR concern_id = 1)', [$subSubcategoryId, $cid]) : null;

            $mainCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($cat['code'] ?? $cat['name'])) ?: 'XX');
            $subCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($sub['code'] ?? $sub['name'] ?? '')) ?: 'XX');
            $subSubCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($subSub['code'] ?? $subSub['name'] ?? '')) ?: 'XX');
            $wholeCode = substr($mainCode, 0, 2) . substr($subCode, 0, 2) . substr($subSubCode, 0, 2);
            $path = implode(' > ', array_filter([$cat['name'] ?? '', $sub['name'] ?? '', $subSub['name'] ?? '']));

            $catalogId = (int) DB::fetchValue(
                "SELECT id FROM catalogs WHERE concern_id = ? AND style_code = ? AND source_module = 'product_adding' LIMIT 1",
                [$cid, $styleCode]
            );
            if ($catalogId > 0) {
                DB::query(
                    "UPDATE catalogs SET name = ?, category_id = ?, subcategory_id = ?, sub_sub_category_id = ?, hierarchy_path = ?, hsn_code = ?, gst_rate = ?, short_code = ?, main_category_code = ?, sub_category_code = ?, sub_sub_category_code = ?, sizes = ?, status = 'active', updated_at = NOW() WHERE id = ? AND source_module = 'product_adding'",
                    [$styleName, $categoryId, $subcategoryId, $subSubcategoryId, $path, $hsnCode, $gstRate, $wholeCode, substr($mainCode, 0, 10), substr($subCode, 0, 10), substr($subSubCode, 0, 10), json_encode(array_values($sizes)), $catalogId]
                );
            } else {
                DB::query(
                    "INSERT INTO catalogs (concern_id, code, style_code, short_code, category_id, subcategory_id, sub_sub_category_id, main_category_code, sub_category_code, sub_sub_category_code, hierarchy_path, hsn_code, gst_rate, sizes, status, source_module, name, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'product_adding', ?, NOW())",
                    [$cid, $styleCode, $styleCode, $wholeCode, $categoryId, $subcategoryId, $subSubcategoryId, substr($mainCode, 0, 10), substr($subCode, 0, 10), substr($subSubCode, 0, 10), $path, $hsnCode, $gstRate, json_encode(array_values($sizes)), $styleName]
                );
                $catalogId = (int) DB::lastInsertId();
            }

            $createdIds = [];
            $totalQty = 0.0;
            foreach ($sizes as $index => $size) {
                $size = trim((string) $size);
                if ($size === '') {
                    continue;
                }
                $qty = (float) ($quantities[$size] ?? 0);
                $colorKey = strtoupper(preg_replace('/[^A-Z0-9]/', '', $color) ?: 'GEN');
                $sizeKey = strtoupper(preg_replace('/[^A-Z0-9]/', '', $size) ?: 'OS');
                $baseSku = strtoupper(trim($styleCode . '-' . $sizeKey . '-' . $colorKey));

                $sku = $baseSku;
                $sequence = 1;
                while (DB::fetch('SELECT id FROM variants WHERE concern_id = ? AND product_code = ?', [$cid, $sku])) {
                    $sequence++;
                    $sku = $baseSku . '-' . $sequence;
                }

                $attributes = [
                    'color' => $color,
                    'size' => $size,
                    'allocated_qty' => $qty,
                ];
                DB::query(
                    "INSERT INTO variants (concern_id, catalog_id, product_code, name, category_id, subcategory_id, brand_id, size, weight, weight_unit, attributes, cost_price, price, mrp, is_complementary, creation_date, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NULL, ?, 0, 'pcs', ?, ?, ?, ?, 0, CURDATE(), 'active', NOW())",
                    [$cid, $catalogId, $sku, $styleName . ' - ' . $size . ($color !== '' ? ' - ' . $color : ''), $categoryId, $subcategoryId, $size, json_encode($attributes), $costPrice, $sellingPrice, $mrp]
                );
                $variantId = (int) DB::lastInsertId();
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
        $this->redirect('/m/product_adding?variant_ids=' . implode(',', $createdIds) . '&qty=' . max(1, (int) ceil($totalQty)));
    }

    private function ensureSchema(): void
    {
        try {
            $catalogColumns = array_column(DB::fetchAll('DESCRIBE catalogs'), 'Field');
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

            $variantColumns = array_column(DB::fetchAll('DESCRIBE variants'), 'Field');
            if (!in_array('catalog_id', $variantColumns, true)) {
                DB::query('ALTER TABLE variants ADD COLUMN catalog_id INT NULL AFTER concern_id');
            }
            if (!in_array('cost_price', $variantColumns, true)) {
                DB::query('ALTER TABLE variants ADD COLUMN cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER attributes');
            }

            $categoryColumns = array_column(DB::fetchAll('DESCRIBE categories'), 'Field');
            if (!in_array('code', $categoryColumns, true)) {
                DB::query('ALTER TABLE categories ADD COLUMN code VARCHAR(20) NULL AFTER name');
            }
            if (!in_array('default_hsn', $categoryColumns, true)) {
                DB::query('ALTER TABLE categories ADD COLUMN default_hsn VARCHAR(50) NULL AFTER code');
            }
            if (!in_array('default_gst_rate', $categoryColumns, true)) {
                DB::query('ALTER TABLE categories ADD COLUMN default_gst_rate DECIMAL(5,2) NULL DEFAULT NULL AFTER default_hsn');
            }

            $purchaseItemColumns = array_column(DB::fetchAll('DESCRIBE purchase_bill_items'), 'Field');
            if (!in_array('catalog_id', $purchaseItemColumns, true)) {
                DB::query('ALTER TABLE purchase_bill_items ADD COLUMN catalog_id INT NULL AFTER variant_id');
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
                category_id INT NULL,
                subcategory_id INT NULL,
                sub_sub_category_id INT NULL,
                attribute_type VARCHAR(50) NOT NULL DEFAULT 'size',
                attribute_name VARCHAR(100) NOT NULL,
                attribute_value VARCHAR(100) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pattr_concern (concern_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $attributeColumns = array_column(DB::fetchAll('DESCRIBE `product_attributes`'), 'Field');
            if (!in_array('category_id', $attributeColumns, true)) {
                DB::query('ALTER TABLE `product_attributes` ADD COLUMN `category_id` INT NULL AFTER `concern_id`');
            }
            if (!in_array('subcategory_id', $attributeColumns, true)) {
                DB::query('ALTER TABLE `product_attributes` ADD COLUMN `subcategory_id` INT NULL AFTER `category_id`');
            }
            if (!in_array('sub_sub_category_id', $attributeColumns, true)) {
                DB::query('ALTER TABLE `product_attributes` ADD COLUMN `sub_sub_category_id` INT NULL AFTER `subcategory_id`');
            }
        } catch (\Throwable $e) {
            error_log('Product Adding schema check error: ' . $e->getMessage());
        }
    }
}
