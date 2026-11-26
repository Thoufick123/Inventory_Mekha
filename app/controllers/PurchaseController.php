<?php
// app/controllers/PurchaseController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Core\ModuleGuard;
use App\Services\AuditLog;
use App\Services\StockService;
use App\Services\NumberSeries;
use App\Services\CloudinaryService;
use App\Services\RollRegistryService;
use App\Services\PurchaseDamageService;
use App\Services\PurchaseBillSourceService;
use App\Services\PurchaseStockItemOverrideService;
use App\Services\ExportService;
use Exception;

class PurchaseController extends Controller {
    private static bool $hsnMasterSchemaEnsured = false;

    private function ensureHsnMasterSchema(): void
    {
        if (self::$hsnMasterSchemaEnsured) {
            return;
        }

        $columns = array_column(DB::fetchAll('DESCRIBE `hsn_masters`'), 'Field');
        $requiredColumns = ['code_type', 'category', 'rate_note', 'is_active'];
        if (array_diff($requiredColumns, $columns)) {
            require_once dirname(__DIR__, 2) . '/database/hsn_gst_master.php';
            \ensureHsnGstMaster();
        }

        self::$hsnMasterSchemaEnsured = true;
    }

    private function getBillAttachmentPaths(array $bill): array
    {
        $paths = json_decode((string)($bill['attachment_paths'] ?? ''), true);
        if (!is_array($paths)) {
            $paths = [];
        }

        $paths = array_values(array_filter($paths, static function ($path): bool {
            return is_string($path) && trim($path) !== '';
        }));

        if (empty($paths) && !empty($bill['attachment_path'])) {
            $paths[] = (string)$bill['attachment_path'];
        }

        return array_values(array_unique($paths));
    }

    private function uploadBillAttachments(array $existingPaths = []): array
    {
        $files = $_FILES['attachments'] ?? null;
        if (!is_array($files) || !isset($files['name'])) {
            $legacyFile = $_FILES['attachment'] ?? null;
            if (!is_array($legacyFile) || empty($legacyFile['name'])) {
                return $existingPaths;
            }

            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
                $files[$key] = [$legacyFile[$key] ?? null];
            }
        } elseif (!is_array($files['name'])) {
            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
                $files[$key] = [$files[$key] ?? null];
            }
        }

        $extensionsToMimeTypes = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
            'pdf'  => ['application/pdf', 'application/x-pdf'],
        ];
        $filesToUpload = [];

        foreach ($files['name'] as $index => $originalName) {
            $error = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('An attachment could not be uploaded. Please check the file size and try again.');
            }

            $tmpPath = (string)($files['tmp_name'][$index] ?? '');
            $size = (int)($files['size'][$index] ?? 0);
            if ($tmpPath === '' || !is_file($tmpPath) || !is_readable($tmpPath)) {
                throw new \RuntimeException('An attachment could not be read. Please choose the file again.');
            }
            if ($size <= 0 || $size > 5 * 1024 * 1024) {
                throw new \RuntimeException('Each attachment must be no larger than 5 MB.');
            }

            $extension = strtolower(pathinfo((string)$originalName, PATHINFO_EXTENSION));
            $mimeType = mime_content_type($tmpPath);
            if (
                !isset($extensionsToMimeTypes[$extension]) ||
                !in_array($mimeType, $extensionsToMimeTypes[$extension], true)
            ) {
                throw new \RuntimeException('Attachments must be JPG, PNG, WEBP, or PDF files.');
            }

            $filesToUpload[] = [
                'tmp_name' => $tmpPath,
                'name' => (string)$originalName,
            ];
        }

        if (empty($filesToUpload)) {
            return $existingPaths;
        }

        foreach ($filesToUpload as $file) {
            $storedPath = CloudinaryService::saveUploadedFile($file, 'bills', ['jpg', 'jpeg', 'png', 'webp', 'pdf']);
            if (empty($storedPath)) {
                throw new \RuntimeException('An attachment could not be stored. The purchase bill was not saved.');
            }
            $existingPaths[] = $storedPath;
        }

        return array_values(array_unique($existingPaths));
    }

    public function storeSupplierAjax(): void {
        $this->validateCsrf();

        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $company = trim((string)($_POST['company_name'] ?? ''));
        $phone = preg_replace('/\D/', '', trim((string)($_POST['phone'] ?? '')));
        $email = trim((string)($_POST['email'] ?? ''));
        $gstin = strtoupper(trim((string)($_POST['gstin'] ?? '')));
        $address = trim((string)($_POST['address'] ?? ''));
        $district = trim((string)($_POST['district'] ?? ''));
        $state = trim((string)($_POST['state'] ?? ''));

        $errors = [];
        if (!$cid || !DB::fetchValue("SELECT id FROM concerns WHERE id = ?", [$cid])) {
            $errors[] = 'Select a valid concern.';
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            $errors[] = 'Contact person name must be between 2 and 150 characters.';
        }
        if (mb_strlen($company) < 2 || mb_strlen($company) > 150) {
            $errors[] = 'Company or mill name must be between 2 and 150 characters.';
        }
        if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
            $errors[] = 'Enter a valid 10-digit Indian mobile number.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($gstin !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
            $errors[] = 'Enter a valid 15-character GSTIN.';
        }
        if (mb_strlen($address) < 3) {
            $errors[] = 'Address must contain at least 3 characters.';
        }
        if (mb_strlen($district) < 2 || mb_strlen($state) < 2) {
            $errors[] = 'Enter a valid district and state.';
        }
        if (!$errors && DB::fetchValue("SELECT id FROM suppliers WHERE concern_id = ? AND phone = ? LIMIT 1", [$cid, $phone])) {
            $errors[] = 'A supplier with this phone number is already registered in this concern.';
        }
        if (!$errors && $gstin !== '' && DB::fetchValue("SELECT id FROM suppliers WHERE concern_id = ? AND gstin = ? LIMIT 1", [$cid, $gstin])) {
            $errors[] = 'A supplier with this GSTIN is already registered in this concern.';
        }
        if ($errors) {
            $this->renderJson(['success' => false, 'message' => implode(' ', $errors)], 422);
            return;
        }

        $code = 'SUP-' . str_pad((string)((int)DB::fetchValue("SELECT COUNT(*) FROM suppliers WHERE concern_id = ?", [$cid]) + 1), 3, '0', STR_PAD_LEFT);
        DB::query(
            "INSERT INTO suppliers (concern_id, code, name, company_name, phone, email, gstin, address, city, district, state, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')",
            [$cid, $code, $name, $company, $phone, $email !== '' ? $email : null, $gstin !== '' ? $gstin : null, $address, $district, $district, $state]
        );

        $this->renderJson([
            'success' => true,
            'supplier' => [
                'id' => (int)DB::lastInsertId(),
                'code' => $code,
                'name' => $name,
                'company_name' => $company,
                'phone' => $phone,
                'email' => $email,
                'gstin' => $gstin,
                'address' => $address,
                'district' => $district,
                'state' => $state,
            ],
        ]);
    }

    public function updateSupplierAjax(): void {
        $this->validateCsrf();

        $id = (int)($_POST['supplier_id'] ?? 0);
        $cid = Auth::concernId();
        $existing = $cid
            ? DB::fetch("SELECT * FROM suppliers WHERE id = ? AND concern_id = ?", [$id, $cid])
            : DB::fetch("SELECT * FROM suppliers WHERE id = ?", [$id]);

        if (!$existing) {
            $this->renderJson(['success' => false, 'message' => 'Supplier not found or access denied.'], 404);
            return;
        }

        $targetCid = (int)$existing['concern_id'];
        $name = trim((string)($_POST['name'] ?? ''));
        $company = trim((string)($_POST['company_name'] ?? ''));
        $phone = preg_replace('/\D/', '', trim((string)($_POST['phone'] ?? '')));
        $email = trim((string)($_POST['email'] ?? ''));
        $gstin = strtoupper(trim((string)($_POST['gstin'] ?? '')));
        $address = trim((string)($_POST['address'] ?? ''));
        $district = trim((string)($_POST['district'] ?? ''));
        $state = trim((string)($_POST['state'] ?? ''));

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            $errors[] = 'Contact person name must be between 2 and 150 characters.';
        }
        if (mb_strlen($company) < 2 || mb_strlen($company) > 150) {
            $errors[] = 'Company or mill name must be between 2 and 150 characters.';
        }
        if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
            $errors[] = 'Enter a valid 10-digit Indian mobile number.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($gstin !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Za-z]{1}Z[0-9A-Za-z]{1}$/', $gstin)) {
            $errors[] = 'Enter a valid 15-character GSTIN.';
        }
        if (mb_strlen($address) < 3) {
            $errors[] = 'Address must contain at least 3 characters.';
        }
        if (mb_strlen($district) < 2 || mb_strlen($state) < 2) {
            $errors[] = 'Enter a valid district and state.';
        }
        if (!$errors && DB::fetchValue("SELECT id FROM suppliers WHERE concern_id = ? AND phone = ? AND id != ? LIMIT 1", [$targetCid, $phone, $id])) {
            $errors[] = 'A supplier with this phone number is already registered in this concern.';
        }
        if (!$errors && $gstin !== '' && DB::fetchValue("SELECT id FROM suppliers WHERE concern_id = ? AND gstin = ? AND id != ? LIMIT 1", [$targetCid, $gstin, $id])) {
            $errors[] = 'A supplier with this GSTIN is already registered in this concern.';
        }
        if ($errors) {
            $this->renderJson(['success' => false, 'message' => implode(' ', $errors)], 422);
            return;
        }

        DB::query(
            "UPDATE suppliers SET name = ?, company_name = ?, phone = ?, email = ?, gstin = ?, address = ?, city = ?, district = ?, state = ? WHERE id = ?",
            [$name, $company, $phone, $email !== '' ? $email : null, $gstin !== '' ? $gstin : null, $address, $district, $district, $state, $id]
        );

        $this->renderJson([
            'success' => true,
            'supplier' => [
                'id' => $id,
                'code' => $existing['code'],
                'name' => $name,
                'company_name' => $company,
                'phone' => $phone,
                'email' => $email,
                'gstin' => $gstin,
                'address' => $address,
                'district' => $district,
                'state' => $state,
            ],
        ]);
    }

    public function deleteSupplierAjax(): void {
        $this->validateCsrf();

        $id = (int)($_POST['supplier_id'] ?? 0);
        $cid = Auth::concernId();
        $supplier = $cid
            ? DB::fetch("SELECT * FROM suppliers WHERE id = ? AND concern_id = ?", [$id, $cid])
            : DB::fetch("SELECT * FROM suppliers WHERE id = ?", [$id]);

        if (!$supplier) {
            $this->renderJson(['success' => false, 'message' => 'Supplier not found or access denied.'], 404);
            return;
        }

        $hasBills = (int)DB::fetchValue("SELECT COUNT(*) FROM purchase_bills WHERE supplier_id = ?", [$id]);
        if ($hasBills > 0) {
            DB::query("UPDATE suppliers SET status = 'inactive' WHERE id = ?", [$id]);
            $this->renderJson([
                'success' => true,
                'deactivated' => true,
                'message' => "Supplier '{$supplier['name']}' has {$hasBills} purchase bills and was marked inactive.",
            ]);
            return;
        }

        DB::query("DELETE FROM suppliers WHERE id = ?", [$id]);
        $this->renderJson([
            'success' => true,
            'deactivated' => false,
            'message' => "Supplier '{$supplier['name']}' deleted successfully.",
        ]);
    }

    public function exportBills(): void {
        ModuleGuard::enforce('purchase_billing', 'view');

        $isSuperAdmin = Auth::isSuperAdmin();
        $cid = $isSuperAdmin
            ? (int)($_GET['concern_id'] ?? 0)
            : Auth::concernId();
        $format = strtolower(trim((string)($_GET['format'] ?? 'excel')));
        $period = is_scalar($_GET['period'] ?? null) ? (string)$_GET['period'] : 'all';
        $singleDate = is_scalar($_GET['single_date'] ?? null) ? (string)$_GET['single_date'] : null;
        $fromDate = is_scalar($_GET['from_date'] ?? null) ? (string)$_GET['from_date'] : null;
        $toDate = is_scalar($_GET['to_date'] ?? null) ? (string)$_GET['to_date'] : null;
        $dateScope = ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT pb.*, s.name AS supplier_name, con.name AS concern_name, con.code AS concern_code,
                       (SELECT COUNT(*) FROM purchase_bill_items pbi WHERE pbi.purchase_bill_id = pb.id) AS items_count
                FROM purchase_bills pb
                JOIN suppliers s ON pb.supplier_id = s.id
                LEFT JOIN concerns con ON pb.concern_id = con.id";
        $where = [];
        $params = [];
        if ($cid) {
            $where[] = "pb.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(pb.bill_date) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY pb.id DESC";
        $bills = DB::fetchAll($sql, $params);

        $headers = [
            'Bill No.', 'Invoice No.', 'Date', 'Supplier', 'Items Qty', 
            'Total (INR)', 'Tax (INR)', 'Net Amount (INR)', 'Paid (INR)', 
            'Payment Status', 'Inward Status', 'Concern'
        ];

        $rows = [];
        $sumNet = 0;
        $sumPaid = 0;
        foreach ($bills as $b) {
            $net = (float)($b['net_amount'] ?? 0);
            $paid = (float)($b['paid_amount'] ?? 0);
            $sumNet += $net;
            $sumPaid += $paid;
            $rows[] = [
                $b['bill_no'],
                $b['supplier_invoice_no'] ?: '-',
                $b['bill_date'] ? date('d M Y', strtotime($b['bill_date'])) : '-',
                $b['supplier_name'],
                (int)($b['items_count'] ?? 0),
                number_format((float)($b['total_amount'] ?? 0), 2),
                number_format((float)($b['tax_amount'] ?? 0), 2),
                number_format($net, 2),
                number_format($paid, 2),
                strtoupper($b['payment_status'] ?? 'PENDING'),
                strtoupper($b['inward_status'] ?? 'PENDING'),
                $b['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total Bills: ' . count($bills), '', '', '', '', '', 'Total Net / Paid:', number_format($sumNet, 2), number_format($sumPaid, 2), '', '', ''];

        $metadata = [
            'Report Title' => 'Purchase Invoices Register Report',
            'Generated At' => date('d M Y, h:i A'),
            'Total Bills'  => count($bills),
            'Date Filter'  => $dateScope['label'] ?? 'All Time',
            'Concern'      => $cid ? ($bills[0]['concern_name'] ?? 'Concern #' . $cid) : 'All Concerns'
        ];

        $filename = 'purchase_invoices_' . date('Ymd_His');

        if ($format === 'pdf') {
            ExportService::renderPdfView('Purchase Invoices Register Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            ExportService::downloadExcel($filename, 'Purchase Invoices Register Report', $metadata, $headers, $rows, $totals);
        }
    }

    // 2. PURCHASE BILLING
    public function billing(): void {
        PurchaseBillSourceService::ensureSchema();
        $cid = Auth::concernId();

        $sql = "SELECT pb.*, s.name AS supplier_name, con.name AS concern_name, con.code AS concern_code,
                       (SELECT COUNT(*) FROM purchase_bill_items pbi WHERE pbi.purchase_bill_id = pb.id) AS items_count,
                       COALESCE((SELECT SUM(pbi.qty) FROM purchase_bill_items pbi WHERE pbi.purchase_bill_id = pb.id), 0) AS total_qty,
                       (SELECT GROUP_CONCAT(DISTINCT pbi.section_type ORDER BY pbi.section_type SEPARATOR ',') 
                        FROM purchase_bill_items pbi 
                        WHERE pbi.purchase_bill_id = pb.id) AS bill_types
                FROM purchase_bills pb
                JOIN suppliers s ON pb.supplier_id = s.id
                LEFT JOIN concerns con ON pb.concern_id = con.id";
        $params = [];
        if ($cid) {
            $sql .= " WHERE pb.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY pb.id DESC";
        $bills = PurchaseBillSourceService::attachInvoiceSyncStatus(DB::fetchAll($sql, $params), $cid);

        if ($cid) {
            $suppliers = DB::fetchAll("SELECT id, name, company_name FROM suppliers WHERE concern_id = ? AND status = 'active'", [$cid]);
            $variants  = DB::fetchAll("SELECT id, product_code, name, price, size FROM variants WHERE concern_id = ? AND status = 'active'", [$cid]);
        } else {
            $suppliers = DB::fetchAll("SELECT id, name, company_name FROM suppliers WHERE status = 'active'");
            $variants  = DB::fetchAll("SELECT id, product_code, name, price, size FROM variants WHERE status = 'active'");
        }

        $this->render('concern/purchase_billing', [
            'pageTitle'     => 'Purchase Billing (Invoices)',
            'currentModule' => 'purchase_billing',
            'bills'         => $bills,
            'suppliers'     => $suppliers,
            'variants'      => $variants,
            'selectedCid'   => $cid,
        ]);
    }

    public function createBill(): void {
        $this->ensureHsnMasterSchema();
        $cid = Auth::concernId();

        if ($cid) {
            $suppliers = DB::fetchAll("SELECT id, concern_id, code, name, company_name, phone, email, gstin, address, district, state FROM suppliers WHERE concern_id = ? AND status = 'active' ORDER BY name ASC", [$cid]);
            $variants  = DB::fetchAll("SELECT id, product_code, name, price, size FROM variants WHERE concern_id = ? AND status = 'active' ORDER BY name ASC", [$cid]);
            $hsnList   = DB::fetchAll("SELECT code_type, hsn_code, description, category, gst_rate, rate_note FROM hsn_masters WHERE is_active = 1 AND code_type = 'HSN' AND (concern_id = ? OR concern_id IS NULL) ORDER BY hsn_code ASC", [$cid]);
        } else {
            $suppliers = DB::fetchAll("SELECT id, concern_id, code, name, company_name, phone, email, gstin, address, district, state FROM suppliers WHERE status = 'active' ORDER BY name ASC");
            $variants  = DB::fetchAll("SELECT id, concern_id, product_code, name, price, size FROM variants WHERE status = 'active' ORDER BY name ASC");
            $hsnList   = DB::fetchAll("SELECT code_type, hsn_code, description, category, gst_rate, rate_note FROM hsn_masters WHERE is_active = 1 AND code_type = 'HSN' ORDER BY hsn_code ASC");
        }

        $allConcerns = [];
        if (Auth::isSuperAdmin()) {
            $allConcerns = DB::fetchAll("SELECT id, name, code FROM concerns ORDER BY id ASC");
        }

        $targetCid = $cid ?: 1;
        $savedRollSpecs = RollRegistryService::getSavedSpecs($targetCid);
        $savedColors    = RollRegistryService::getSavedColors($targetCid);
        $savedDesigns   = RollRegistryService::getSavedDesigns($targetCid);

        $this->render('concern/purchase_billing_create', [
            'pageTitle'      => 'Record Production Purchase',
            'currentModule'  => 'purchase_billing',
            'suppliers'      => $suppliers,
            'variants'       => $variants,
            'selectedCid'    => $cid,
            'allConcerns'    => $allConcerns,
            'hsnList'        => $hsnList,
            'savedRollSpecs' => $savedRollSpecs,
            'savedColors'    => $savedColors,
            'savedDesigns'   => $savedDesigns,
            'quickSupplierSaveUrl' => \App\Core\Controller::url('/m/purchase_billing/suppliers/store'),
            'quickSupplierUpdateUrl' => \App\Core\Controller::url('/m/purchase_billing/suppliers/update'),
            'quickSupplierDeleteUrl' => \App\Core\Controller::url('/m/purchase_billing/suppliers/delete'),
            'allowQuickSupplier'   => Auth::isSuperAdmin() || Auth::can('purchase_billing', 'add'),
            'invoiceCheckUrl' => \App\Core\Controller::url('/m/purchase_billing/check_invoice'),
        ]);
    }

    public function editBillFromProductAdding(): void {
        $_GET['product_adding_edit'] = '1';
        $this->editBill();
    }

    public function editBill(): void {
        $this->ensureHsnMasterSchema();
        $isProductAddingEdit = ($_GET['product_adding_edit'] ?? '') === '1';
        if ($isProductAddingEdit) {
            PurchaseBillSourceService::ensureSchema();
        }
        $cid = Auth::concernId();
        $id = (int)($_GET['id'] ?? 0);

        $sql = "SELECT pb.*, s.name AS supplier_name, s.company_name AS supplier_company
                FROM purchase_bills pb
                JOIN suppliers s ON pb.supplier_id = s.id
                WHERE pb.id = ?";
        $params = [$id];
        if ($cid) {
            $sql .= " AND pb.concern_id = ?";
            $params[] = $cid;
        }

        $bill = DB::fetch($sql, $params);
        if (!$bill || ($isProductAddingEdit && ($bill['source_module'] ?? '') !== 'purchase_billing')) {
            $this->flash('error', 'Purchase Bill not found or access denied.');
            $this->redirect($isProductAddingEdit ? '/m/product_adding/create?bill_id=' . $billId : '/m/purchase_billing');
            return;
        }

        if ($isProductAddingEdit) {
            $stockedCount = (int)DB::fetchValue(
                "SELECT COUNT(*)
                 FROM purchase_bill_items pbi
                 JOIN purchase_bills pb ON pb.id = pbi.purchase_bill_id
                 WHERE pbi.purchase_bill_id = ?
                   AND COALESCE(NULLIF(pbi.inward_status, ''), pb.inward_status, 'pending') = 'completed'",
                [$id]
            );
            if (($bill['inward_status'] ?? 'pending') === 'completed' || $stockedCount > 0) {
                $this->flash('error', 'This invoice has already had stock added and can no longer be edited.');
                $this->redirect('/m/product_adding');
                return;
            }
        }

        $items = DB::fetchAll("SELECT * FROM purchase_bill_items WHERE purchase_bill_id = ? ORDER BY id ASC", [$id]);

        if ($cid) {
            $suppliers = DB::fetchAll("SELECT id, concern_id, code, name, company_name, phone, email, gstin, address, district, state FROM suppliers WHERE concern_id = ? AND status = 'active' ORDER BY name ASC", [$cid]);
            $variants  = DB::fetchAll("SELECT id, product_code, name, price, size FROM variants WHERE concern_id = ? AND status = 'active' ORDER BY name ASC", [$cid]);
            $hsnList   = DB::fetchAll("SELECT code_type, hsn_code, description, category, gst_rate, rate_note FROM hsn_masters WHERE is_active = 1 AND code_type = 'HSN' AND (concern_id = ? OR concern_id IS NULL) ORDER BY hsn_code ASC", [$cid]);
        } else {
            $suppliers = DB::fetchAll("SELECT id, concern_id, code, name, company_name, phone, email, gstin, address, district, state FROM suppliers WHERE status = 'active' ORDER BY name ASC");
            $variants  = DB::fetchAll("SELECT id, concern_id, product_code, name, price, size FROM variants WHERE status = 'active' ORDER BY name ASC");
            $hsnList   = DB::fetchAll("SELECT code_type, hsn_code, description, category, gst_rate, rate_note FROM hsn_masters WHERE is_active = 1 AND code_type = 'HSN' ORDER BY hsn_code ASC");
        }

        $allConcerns = [];
        if (Auth::isSuperAdmin()) {
            $allConcerns = DB::fetchAll("SELECT id, name, code FROM concerns ORDER BY id ASC");
        }

        $targetCid = $cid ?: (int)$bill['concern_id'];
        $savedRollSpecs = RollRegistryService::getSavedSpecs($targetCid);
        $savedColors    = RollRegistryService::getSavedColors($targetCid);
        $savedDesigns   = RollRegistryService::getSavedDesigns($targetCid);

        $categories = [];
        $subcategories = [];
        $subSubcategories = [];
        $productAttributes = [];
        if ($isProductAddingEdit) {
            $categoryScope = $cid
                ? " WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL) AND status = 'active'"
                : " WHERE status = 'active'";
            $categoryParams = $cid ? [$cid] : [];
            $categories = DB::fetchAll("SELECT id, concern_id, name, default_hsn, default_gst_rate FROM categories{$categoryScope} ORDER BY name ASC", $categoryParams);
            $subcategories = DB::fetchAll("SELECT id, concern_id, category_id, name FROM subcategories{$categoryScope} ORDER BY name ASC", $categoryParams);
            $subSubcategories = DB::fetchAll("SELECT id, concern_id, subcategory_id, name FROM sub_subcategories{$categoryScope} ORDER BY name ASC", $categoryParams);
            $productAttributes = DB::fetchAll(
                $cid
                    ? "SELECT concern_id, category_id, subcategory_id, sub_sub_category_id, attribute_type, attribute_value FROM product_attributes WHERE concern_id = ? OR concern_id = 1 OR concern_id IS NULL ORDER BY attribute_type, sort_order"
                    : "SELECT concern_id, category_id, subcategory_id, sub_sub_category_id, attribute_type, attribute_value FROM product_attributes ORDER BY attribute_type, sort_order",
                $cid ? [$cid] : []
            );
        }

        $this->render('concern/purchase_billing_create', [
            'pageTitle'      => ($isProductAddingEdit ? 'Edit Existing Purchase Invoice: #' : 'Edit Purchase Bill: #') . $bill['bill_no'],
            'pageHeading'    => ($isProductAddingEdit ? 'Edit Existing Purchase Invoice: #' : 'Edit Purchase Bill: #') . $bill['bill_no'],
            'pageDescription' => $isProductAddingEdit
                ? 'Review and update the existing Purchase Billing invoice before adding it to stock.'
                : 'Update fabric rolls, garment supplies/accessories and finished goods for this bill.',
            'currentModule'  => $isProductAddingEdit ? 'product_adding' : 'purchase_billing',
            'suppliers'      => $suppliers,
            'variants'       => $variants,
            'selectedCid'    => $cid,
            'allConcerns'    => $allConcerns,
            'categories'     => $categories,
            'subcategories'  => $subcategories,
            'subSubcategories' => $subSubcategories,
            'productAttributes' => $productAttributes,
            'hsnList'        => $hsnList,
            'bill'           => $bill,
            'existingItems'  => $items,
            'savedRollSpecs' => $savedRollSpecs,
            'savedColors'    => $savedColors,
            'savedDesigns'   => $savedDesigns,
            'formAction'     => \App\Core\Controller::url($isProductAddingEdit ? '/m/product_adding/update_purchase_bill' : '/m/purchase_billing/update'),
            'hsnSaveUrl'     => \App\Core\Controller::url($isProductAddingEdit ? '/m/product_adding/save_hsn' : '/m/purchase_billing/save_hsn'),
            'rollSpecSaveUrl' => \App\Core\Controller::url($isProductAddingEdit ? '/m/product_adding/save_roll_spec' : '/m/purchase_billing/save_roll_spec'),
            'quickCategorySaveUrl' => $isProductAddingEdit ? \App\Core\Controller::url('/m/product_adding/categories/store') : '',
            'quickAttributeSaveUrl' => $isProductAddingEdit ? \App\Core\Controller::url('/m/product_adding/attributes/store') : '',
            'backUrl'        => \App\Core\Controller::url($isProductAddingEdit ? '/m/product_adding' : '/m/purchase_billing'),
            'backLabel'      => $isProductAddingEdit ? 'Back to Product Adding' : 'Back to Purchases',
            'submitLabel'    => $isProductAddingEdit ? 'Update Existing Invoice' : 'Update Purchase Bill',
            'productAddingEdit' => $isProductAddingEdit,
            'quickSupplierSaveUrl' => \App\Core\Controller::url($isProductAddingEdit ? '/m/product_adding/suppliers/store' : '/m/purchase_billing/suppliers/store'),
            'quickSupplierUpdateUrl' => \App\Core\Controller::url($isProductAddingEdit ? '/m/product_adding/suppliers/update' : '/m/purchase_billing/suppliers/update'),
            'quickSupplierDeleteUrl' => \App\Core\Controller::url($isProductAddingEdit ? '/m/product_adding/suppliers/delete' : '/m/purchase_billing/suppliers/delete'),
            'allowQuickSupplier'   => Auth::isSuperAdmin() || Auth::can($isProductAddingEdit ? 'product_adding' : 'purchase_billing', 'add'),
            'invoiceCheckUrl' => \App\Core\Controller::url($isProductAddingEdit ? '/m/product_adding/check_invoice' : '/m/purchase_billing/check_invoice'),
        ]);
    }

    public function saveHsnAjax(): void {
        $this->ensureHsnMasterSchema();
        header('Content-Type: application/json; charset=utf-8');
        $cid = Auth::concernId() ?: ((int)($_POST['concern_id'] ?? 0) ?: null);
        $code = strtoupper(trim($_POST['hsn_code'] ?? ''));
        $codeType = 'HSN';
        $desc = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $rateNote = trim($_POST['rate_note'] ?? '');
        $rateInput = trim((string)($_POST['gst_rate'] ?? ''));
        $rate = $rateInput === '' ? null : (is_numeric($rateInput) ? (float)$rateInput : -1.0);

        if (!preg_match('/^\d{4}(?:\d{2}){0,2}$/', $code) || strlen($desc) > 255 || strlen($category) > 100 || strlen($rateNote) > 500 || ($rate === null || !is_finite($rate) || $rate < 0 || $rate > 100)) {
            echo json_encode(['success' => false, 'message' => 'Use a valid 4-, 6-, or 8-digit HSN code and GST percentage from 0 to 100.']);
            exit;
        }

        try {
            $existing = DB::fetch(
                "SELECT id, concern_id, code_type, hsn_code, description, category, gst_rate, rate_note
                 FROM hsn_masters
                 WHERE is_active = 1 AND code_type = ? AND hsn_code = ? AND (concern_id = ? OR concern_id IS NULL)
                 ORDER BY concern_id DESC LIMIT 1",
                [$codeType, $code, $cid]
            );

            if (!$existing) {
                DB::query(
                    "INSERT INTO hsn_masters (concern_id, code_type, hsn_code, description, category, gst_rate, rate_note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$cid, $codeType, $code, $desc, $category, $rate, $rateNote]
                );
                $saved = [
                    'code_type' => $codeType,
                    'hsn_code' => $code,
                    'description' => $desc,
                    'category' => $category,
                    'gst_rate' => $rate,
                    'rate_note' => $rateNote,
                ];
            } elseif ($existing['concern_id'] === null) {
                $saved = $existing;
            } else {
                DB::query(
                    "UPDATE hsn_masters SET description = ?, category = ?, gst_rate = ?, rate_note = ?, is_active = 1 WHERE id = ?",
                    [$desc, $category, $rate, $rateNote, $existing['id']]
                );
                $saved = [
                    'code_type' => $codeType,
                    'hsn_code' => $code,
                    'description' => $desc,
                    'category' => $category,
                    'gst_rate' => $rate,
                    'rate_note' => $rateNote,
                ];
            }

            echo json_encode([
                'success' => true,
                'code_type' => $saved['code_type'],
                'hsn_code' => $saved['hsn_code'],
                'description' => $saved['description'],
                'category' => $saved['category'],
                'gst_rate' => $saved['gst_rate'],
                'rate_note' => $saved['rate_note'],
                'label' => $saved['hsn_code'] . ($saved['description'] ? ' - ' . $saved['description'] : '')
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function saveRollSpecAjax(): void {
        header('Content-Type: application/json; charset=utf-8');
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 1);

        $color = strtoupper(trim($_POST['color'] ?? ''));
        $designName = strtoupper(trim($_POST['design_name'] ?? ''));
        $unitPrice = isset($_POST['unit_price']) && $_POST['unit_price'] !== '' ? (float)$_POST['unit_price'] : 0.0;
        $hsnCode = trim($_POST['hsn_code'] ?? '');
        $initialQty = isset($_POST['qty']) && $_POST['qty'] !== '' ? (float)$_POST['qty'] : 0.0;
        $netWeight = isset($_POST['net_weight']) && $_POST['net_weight'] !== '' ? (float)$_POST['net_weight'] : 0.0;
        $supplierName = trim($_POST['supplier_name'] ?? '');

        if (empty($color) || empty($designName)) {
            echo json_encode(['success' => false, 'message' => 'Color / Shade and Design Name / Pattern are required.']);
            exit;
        }

        try {
            $res = RollRegistryService::createOrUpdateSpec(
                $cid,
                $color,
                $designName,
                $unitPrice,
                $hsnCode,
                $initialQty,
                $netWeight,
                $supplierName
            );
            echo json_encode($res);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function viewBill(): void {
        $cid = Auth::concernId();
        $id = (int)($_GET['id'] ?? 0);

        $sql = "SELECT pb.*, s.name AS supplier_name, s.company_name AS supplier_company, s.phone AS supplier_phone,
                       s.gstin AS supplier_gstin, s.district AS supplier_district, s.state AS supplier_state,
                       con.name AS concern_name, con.code AS concern_code
                FROM purchase_bills pb
                JOIN suppliers s ON pb.supplier_id = s.id
                LEFT JOIN concerns con ON pb.concern_id = con.id
                WHERE pb.id = ?";
        $params = [$id];
        if ($cid) {
            $sql .= " AND pb.concern_id = ?";
            $params[] = $cid;
        }

        $bill = DB::fetch($sql, $params);
        if (!$bill) {
            $this->flash('error', 'Purchase Bill not found.');
            $this->redirect('/m/purchase_billing');
            return;
        }

        $items = DB::fetchAll("SELECT * FROM purchase_bill_items WHERE purchase_bill_id = ? ORDER BY id ASC", [$id]);

        $this->render('concern/purchase_billing_view', [
            'pageTitle'     => 'Purchase Bill #' . $bill['bill_no'],
            'currentModule' => 'purchase_billing',
            'bill'          => $bill,
            'items'         => $items,
        ]);
    }

    public function downloadItems(): void {
        $cid = Auth::concernId();
        $id = (int)($_GET['id'] ?? 0);

        $sql = "SELECT pb.*, s.name AS supplier_name, s.company_name AS supplier_company, s.phone AS supplier_phone,
                       s.gstin AS supplier_gstin, s.district AS supplier_district, s.state AS supplier_state,
                       con.name AS concern_name, con.code AS concern_code
                FROM purchase_bills pb
                JOIN suppliers s ON pb.supplier_id = s.id
                LEFT JOIN concerns con ON pb.concern_id = con.id
                WHERE pb.id = ?";
        $params = [$id];
        if ($cid) {
            $sql .= " AND pb.concern_id = ?";
            $params[] = $cid;
        }

        $bill = DB::fetch($sql, $params);
        if (!$bill) {
            $this->flash('error', 'Purchase Bill not found.');
            $this->redirect('/m/purchase_billing');
            return;
        }

        $items = DB::fetchAll("SELECT * FROM purchase_bill_items WHERE purchase_bill_id = ? ORDER BY id ASC", [$id]);

        $cleanBillNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', $bill['bill_no']);
        $filename = "Purchase_Items_{$cleanBillNo}_" . date('Ymd_His') . ".csv";

        // Send headers for CSV download
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // Add UTF-8 BOM so Excel opens it with proper encoding
        fwrite($out, "\xEF\xBB\xBF");

        // Bill Header Information
        fputcsv($out, ['GRM INVENTORY - PURCHASE BILL ITEMS']);
        fputcsv($out, ['Bill No', $bill['bill_no'], 'Purchase Date', date('d-m-Y', strtotime($bill['bill_date']))]);
        fputcsv($out, ['Supplier Name', $bill['supplier_name'], 'Company', $bill['supplier_company'] ?: '-']);
        fputcsv($out, ['Supplier Invoice No', $bill['supplier_invoice_no'] ?: '-', 'GSTIN', $bill['supplier_gstin'] ?: '-']);
        fputcsv($out, ['Payment Status', ucfirst($bill['payment_status']), 'Payment Method', ucfirst($bill['payment_method'] ?? 'credit')]);
        fputcsv($out, ['Inward Status', ucfirst($bill['inward_status']), 'Total Net Value (INR)', number_format($bill['net_amount'], 2, '.', '')]);
        fputcsv($out, []); // Blank separator line

        // Items Column Headers
        fputcsv($out, [
            'S.No',
            'Section / Category',
            'Item Code / Roll No',
            'Item / Product Name',
            'HSN Code',
            'Color / Shade',
            'Design / Pattern',
            'Quantity',
            'Unit',
            'Weight (kg)',
            'Unit Price (INR)',
            'Tax Mode',
            'GST Type',
            'GST Rate (%)',
            'Taxable Base (INR)',
            'Tax Amount (INR)',
            'Total Value (INR)'
        ]);

        $totalBase = 0.0;
        $totalTax  = 0.0;
        $totalNet  = 0.0;
        $totalQty  = 0.0;
        $totalWt   = 0.0;

        foreach ($items as $idx => $item) {
            $sectionLabel = match ($item['section_type'] ?? '') {
                'rolls'          => 'Fabric Rolls',
                'garment_other'  => 'Other Garment Supplies',
                'finished_goods' => 'Finished Goods',
                default          => ucfirst(str_replace('_', ' ', $item['section_type'] ?? 'General'))
            };

            $qty       = (float)($item['qty'] ?? 1);
            $weight    = (float)($item['net_weight'] ?? 0);
            $unitPrice = (float)($item['unit_price'] ?? 0);
            $baseVal   = (float)($item['total_price'] ?? 0);
            $taxVal    = (float)($item['tax_amount'] ?? 0);
            $totalVal  = $baseVal + $taxVal;
            $gstRate   = (float)($item['gst_rate'] ?? 0);

            $totalQty  += $qty;
            $totalWt   += $weight;
            $totalBase += $baseVal;
            $totalTax  += $taxVal;
            $totalNet  += $totalVal;

            fputcsv($out, [
                $idx + 1,
                $sectionLabel,
                $item['item_code'] ?: '-',
                $item['item_name'] ?: '-',
                $item['hsn_code'] ?: '-',
                $item['color'] ?: ($item['specification'] ?: '-'),
                $item['pattern'] ?: '-',
                $qty,
                $item['unit'] ?: 'Pcs',
                number_format($weight, 3, '.', ''),
                number_format($unitPrice, 2, '.', ''),
                $item['tax_mode'] ?: 'Exclude GST',
                $item['gst_type'] ?: 'Exempted',
                number_format($gstRate, 2, '.', '') . '%',
                number_format($baseVal, 2, '.', ''),
                number_format($taxVal, 2, '.', ''),
                number_format($totalVal, 2, '.', '')
            ]);
        }

        // Summary row
        fputcsv($out, []);
        fputcsv($out, [
            'TOTAL',
            '',
            '',
            '',
            '',
            '',
            '',
            number_format($totalQty, 2, '.', ''),
            '',
            number_format($totalWt, 3, '.', ''),
            '',
            '',
            '',
            '',
            number_format($totalBase, 2, '.', ''),
            number_format($totalTax, 2, '.', ''),
            number_format($totalNet, 2, '.', '')
        ]);

        fclose($out);
        exit;
    }

    private function calculateLineTax(
        float $qty,
        float $price,
        string $taxMode,
        string $gstType,
        string $taxType,
        float $sgstVal,
        float $cgstVal,
        float $igstVal,
        float $fallbackRate = 0.0
    ): array {
        $grossVal = round($qty * $price, 2);
        if ($gstType === 'Exempted') {
            return [
                'itemBase' => $grossVal,
                'itemTax'  => 0.0,
                'gstRate'  => 0.0,
                'total'    => $grossVal
            ];
        }

        $isAmount = ($taxType === 'Amount' || $taxType === 'Amount (₹)');
        $rate = 0.0;
        $tax = 0.0;

        if ($gstType === 'Both (CGST and SGST)') {
            if ($isAmount) {
                $tax = round($sgstVal + $cgstVal, 2);
            } else {
                $rate = round($sgstVal + $cgstVal, 2);
            }
        } else if ($gstType === 'CGST') {
            if ($isAmount) {
                $tax = round($cgstVal, 2);
            } else {
                $rate = round($cgstVal, 2);
            }
        } else if ($gstType === 'SGST') {
            if ($isAmount) {
                $tax = round($sgstVal, 2);
            } else {
                $rate = round($sgstVal, 2);
            }
        } else { // IGST
            if ($isAmount) {
                $tax = round($igstVal, 2);
            } else {
                $rate = round($igstVal, 2);
            }
        }

        if (!$isAmount && $rate === 0.0 && $fallbackRate > 0) {
            $rate = $fallbackRate;
        }

        if ($taxMode === 'Include GST') {
            $total = $grossVal;
            if ($isAmount) {
                $tax = min($tax, $grossVal);
                $base = round($grossVal - $tax, 2);
                $rate = ($base > 0) ? round(($tax / $base) * 100, 2) : 0.0;
            } else {
                $base = $rate > 0 ? round($grossVal / (1 + ($rate / 100)), 2) : $grossVal;
                $tax = round($grossVal - $base, 2);
            }
        } else {
            $base = $grossVal;
            if ($isAmount) {
                $rate = ($base > 0) ? round(($tax / $base) * 100, 2) : 0.0;
            } else {
                $tax = round($base * ($rate / 100), 2);
            }
            $total = round($base + $tax, 2);
        }

        return [
            'itemBase' => $base,
            'itemTax'  => $tax,
            'gstRate'  => $rate,
            'total'    => $total
        ];
    }

    private function supplierInvoiceNumberExists(int $concernId, int $supplierId, string $invoiceNo, ?int $excludeBillId = null): bool
    {
        $sql = "SELECT id FROM purchase_bills
                WHERE concern_id = ? AND supplier_id = ?
                  AND LOWER(TRIM(supplier_invoice_no)) = LOWER(TRIM(?))";
        $params = [$concernId, $supplierId, $invoiceNo];
        if ($excludeBillId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeBillId;
        }
        return (bool)DB::fetchValue($sql . " LIMIT 1", $params);
    }

    public function checkSupplierInvoiceNumberAjax(): void
    {
        $this->validateCsrf();
        PurchaseBillSourceService::ensureSchema();

        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $invoiceNo = trim((string)($_POST['supplier_invoice_no'] ?? ''));
        $concernId = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);
        if (!$concernId && $supplierId > 0) {
            $concernId = (int)DB::fetchValue('SELECT concern_id FROM suppliers WHERE id = ?', [$supplierId]);
        }
        if (!$concernId) {
            $this->renderJson(['success' => false, 'message' => 'Choose a concern before checking the invoice number.'], 422);
            return;
        }
        if ($supplierId <= 0 || $invoiceNo === '') {
            $this->renderJson(['success' => true, 'duplicate' => false]);
            return;
        }

        $billId = (int)($_POST['bill_id'] ?? 0);
        $isProductAddingRequest = ($_POST['product_adding_redirect'] ?? '') === '1';

        if ($isProductAddingRequest) {
            // Check if a Purchase Billing invoice exists for this supplier + invoice number (invoice no + supplier + qty contact)
            $pbBill = DB::fetch(
                "SELECT pb.id, pb.bill_no, COALESCE(SUM(pbi.qty), 0) AS invoice_qty
                 FROM purchase_bills pb
                 LEFT JOIN purchase_bill_items pbi ON pbi.purchase_bill_id = pb.id
                 WHERE pb.concern_id = ? AND pb.supplier_id = ?
                   AND LOWER(TRIM(pb.supplier_invoice_no)) = LOWER(TRIM(?))
                   AND COALESCE(pb.source_module, 'purchase_billing') = 'purchase_billing'
                 GROUP BY pb.id
                 ORDER BY pb.id DESC LIMIT 1",
                [$concernId, $supplierId, $invoiceNo]
            );

            $paSql = "SELECT COALESCE(SUM(pbi.qty), 0) AS added_qty
                      FROM purchase_bills pb
                      JOIN purchase_bill_items pbi ON pbi.purchase_bill_id = pb.id
                      WHERE pb.concern_id = ? AND pb.supplier_id = ?
                        AND LOWER(TRIM(pb.supplier_invoice_no)) = LOWER(TRIM(?))
                        AND pb.source_module = 'product_adding'";
            $paParams = [$concernId, $supplierId, $invoiceNo];
            if ($billId > 0) {
                $paSql .= ' AND pb.id != ?';
                $paParams[] = $billId;
            }
            $addedQty = (float)(DB::fetchValue($paSql, $paParams) ?: 0);

            if ($pbBill) {
                $invoiceQty = (float)($pbBill['invoice_qty'] ?? 0);
                $remainingQty = max(0.0, $invoiceQty - $addedQty);
                $this->renderJson([
                    'success' => true,
                    'duplicate' => false,
                    'matched_purchase_bill' => [
                        'id' => (int)$pbBill['id'],
                        'bill_no' => (string)$pbBill['bill_no'],
                        'invoice_qty' => $invoiceQty,
                        'product_adding_qty' => $addedQty,
                        'remaining_qty' => $remainingQty,
                    ],
                ]);
                return;
            }
        }

        $existingSql = "SELECT pb.id, pb.source_module, pb.inward_status,
                               COUNT(pbi.id) AS item_count,
                               COALESCE(SUM(CASE
                                   WHEN COALESCE(NULLIF(pbi.inward_status, ''), pb.inward_status, 'pending') = 'completed'
                                   THEN 1 ELSE 0
                               END), 0) AS stocked_count
                        FROM purchase_bills pb
                        LEFT JOIN purchase_bill_items pbi ON pbi.purchase_bill_id = pb.id
                        WHERE pb.concern_id = ? AND pb.supplier_id = ?
                          AND LOWER(TRIM(pb.supplier_invoice_no)) = LOWER(TRIM(?))";
        $existingParams = [$concernId, $supplierId, $invoiceNo];
        if ($billId > 0) {
            $existingSql .= ' AND pb.id != ?';
            $existingParams[] = $billId;
        }
        $existingSql .= ' GROUP BY pb.id ORDER BY pb.id DESC LIMIT 1';
        $existingBill = DB::fetch($existingSql, $existingParams);
        $duplicate = !empty($existingBill);
        $response = [
            'success' => true,
            'duplicate' => $duplicate,
            'message' => $duplicate
                ? 'This supplier invoice number has already been recorded for the selected supplier.'
                : '',
        ];
        $this->renderJson($response);
    }

    public function storeBill(): void {
        $this->validateCsrf();
        PurchaseBillSourceService::ensureSchema();
        $isProductAddingInvoice = ($_POST['product_adding_redirect'] ?? '') === '1';
        $successRedirect = $isProductAddingInvoice
            ? '/m/product_adding'
            : '/m/purchase_billing';
        $createRedirect = $isProductAddingInvoice
            ? '/m/product_adding'
            : '/m/purchase_billing/create';
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);
        $supplierId = (int)($_POST['supplier_id'] ?? 0);

        if (!$cid && $supplierId) {
            $cid = (int)DB::fetchValue("SELECT concern_id FROM suppliers WHERE id = ?", [$supplierId]);
        }

        if (!$cid) {
            $this->flash('error', 'Please choose a target concern or valid supplier.');
            $this->redirect($createRedirect);
            return;
        }

        if (!$supplierId) {
            $this->flash('error', 'Please select a registered supplier.');
            $this->redirect($createRedirect);
            return;
        }

        $billDate          = !empty($_POST['bill_date']) ? $_POST['bill_date'] : date('Y-m-d');
        $supplierInvoiceNo = trim($_POST['supplier_invoice_no'] ?? '');
        if (empty($supplierInvoiceNo)) {
            $this->flash('error', 'Invoice / Bill Number is required.');
            $this->redirect($createRedirect);
            return;
        }
        if (!$isProductAddingInvoice && $this->supplierInvoiceNumberExists($cid, $supplierId, $supplierInvoiceNo)) {
            $this->flash('error', 'This supplier invoice number has already been recorded for the selected supplier.');
            $this->redirect($createRedirect);
            return;
        }

        $matchedPurchaseBillQty = null;
        $alreadyAddedProductAddingQty = 0.0;
        if ($isProductAddingInvoice) {
            $matchedPb = DB::fetch(
                "SELECT pb.id, COALESCE(SUM(pbi.qty), 0) AS invoice_qty
                 FROM purchase_bills pb
                 LEFT JOIN purchase_bill_items pbi ON pbi.purchase_bill_id = pb.id
                 WHERE pb.concern_id = ? AND pb.supplier_id = ?
                   AND LOWER(TRIM(pb.supplier_invoice_no)) = LOWER(TRIM(?))
                   AND COALESCE(pb.source_module, 'purchase_billing') = 'purchase_billing'
                 GROUP BY pb.id
                 ORDER BY pb.id DESC LIMIT 1",
                [$cid, $supplierId, $supplierInvoiceNo]
            );
            if ($matchedPb) {
                $matchedPurchaseBillQty = (float)($matchedPb['invoice_qty'] ?? 0);
                $alreadyAddedProductAddingQty = (float)(DB::fetchValue(
                    "SELECT COALESCE(SUM(pbi.qty), 0)
                     FROM purchase_bills pb
                     JOIN purchase_bill_items pbi ON pbi.purchase_bill_id = pb.id
                     WHERE pb.concern_id = ? AND pb.supplier_id = ?
                       AND LOWER(TRIM(pb.supplier_invoice_no)) = LOWER(TRIM(?))
                       AND pb.source_module = 'product_adding'",
                    [$cid, $supplierId, $supplierInvoiceNo]
                ) ?: 0);
            } elseif ($this->supplierInvoiceNumberExists($cid, $supplierId, $supplierInvoiceNo)) {
                $this->flash('error', 'This supplier invoice number has already been recorded for the selected supplier.');
                $this->redirect($createRedirect);
                return;
            }
        }
        $fabricDescription = trim($_POST['fabric_description'] ?? '');
        $notes             = trim($_POST['notes'] ?? '');
        $taxRate           = isset($_POST['tax_rate']) ? (float)$_POST['tax_rate'] : 5.0;

        // Payment & Inward Settings
        $paymentStatus    = trim($_POST['payment_status'] ?? 'unpaid');
        $paymentMethod    = trim($_POST['payment_method'] ?? 'credit');
        $paymentReference = trim($_POST['payment_reference'] ?? '');
        $paidAmount       = (float)($_POST['paid_amount'] ?? 0.0);
        // Stock inwarding must happen through the Label Generation Add to Stock action.
        $inwardStatus     = 'pending';

        if (!in_array($paymentStatus, ['unpaid', 'partial', 'paid'], true)) {
            $paymentStatus = 'unpaid';
        }

        // Process Sections (Unified Purchase Billing items or 3-section Product Adding items)
        $simpleItemsRaw  = $_POST['purchase_items'] ?? [];
        $rollsRaw        = $_POST['rolls'] ?? [];
        $garmentOtherRaw = $_POST['garment_other'] ?? [];
        $finishedRaw     = $_POST['finished_goods'] ?? [];

        $itemsToInsert = [];
        $totalAmount   = 0.0;
        $totalTax      = 0.0;

        if (!empty($simpleItemsRaw) && is_array($simpleItemsRaw)) {
            $itemsToInsert = $this->parseSimplePurchaseItems($simpleItemsRaw, $totalAmount, $totalTax);
        }

        // 1. Fabric Rolls (Reference Image 1)
        if (is_array($rollsRaw)) {
            foreach ($rollsRaw as $idx => $r) {
                $netWt = (float)($r['weight'] ?? ($r['net_weight'] ?? 0));
                $price = (float)($r['price'] ?? ($r['rate'] ?? 0));
                $rq    = max(1, (int)($r['rq'] ?? 1));

                if ($rq > 0 && $price > 0) {
                    $taxMode  = trim($r['tax_mode'] ?? 'Exclude GST');
                    $gstType  = trim($r['gst'] ?? ($r['gst_type'] ?? 'Exempted'));
                    $taxType  = trim($r['tax_input_type'] ?? 'Percentage');
                    $sgstVal  = (float)($r['sgst'] ?? 0);
                    $cgstVal  = (float)($r['cgst'] ?? 0);
                    $igstVal  = (float)($r['igst'] ?? 0);
                    $fallback = (float)($r['gst_rate'] ?? 0);

                    $calc = $this->calculateLineTax($rq, $price, $taxMode, $gstType, $taxType, $sgstVal, $cgstVal, $igstVal, $fallback);
                    $itemBase = $calc['itemBase'];
                    $itemTax  = $calc['itemTax'];
                    $gstRate  = $calc['gstRate'];

                    $totalAmount += $itemBase;
                    $totalTax    += $itemTax;

                    // Handle roll sample photo upload if present (Cloudinary first, local fallback)
                    $rollPhotoPath = !empty($r['existing_photo']) ? trim($r['existing_photo']) : null;
                    $rollFile = CloudinaryService::normalizeNestedFile($_FILES['rolls'] ?? [], $idx, 'photo');
                    if ($rollFile) {
                        $savedPhoto = CloudinaryService::saveUploadedFile($rollFile, 'rolls', ['jpg', 'jpeg', 'png', 'webp']);
                        if ($savedPhoto) {
                            $rollPhotoPath = $savedPhoto;
                        }
                    }

                    $rawRollCode = trim($r['roll_code'] ?? '');
                    $rollPattern = trim($r['pattern'] ?? ($r['design_name'] ?? ''));
                    $rollColor   = trim($r['color'] ?? ($r['shade'] ?? ''));
                    $rollShade   = trim($r['shade'] ?? '');
                    $rollSpec    = (!empty($rollShade) && strcasecmp($rollShade, $rollColor) !== 0) ? $rollShade : null;
                    $rollName    = !empty($rawRollCode) ? $rawRollCode : (!empty($rollPattern) ? ($rollPattern . ' Fabric') : ('ROLL #' . $idx));

                    $itemsToInsert[] = [
                        'section_type'  => 'rolls',
                        'variant_id'    => !empty($r['variant_id']) ? (int)$r['variant_id'] : null,
                        'item_name'     => $rollName,
                        'item_code'     => trim($r['roll_code'] ?? ('ROLL #' . $idx)),
                        'hsn_code'      => trim($r['hsn'] ?? ($r['hsn_code'] ?? '')),
                        'specification' => $rollSpec,
                        'color'         => $rollColor,
                        'pattern'       => $rollPattern,
                        'designs_count' => 1,
                        'rq_qty'        => $rq,
                        'unit'          => 'RQ',
                        'gross_weight'  => $netWt,
                        'net_weight'    => $netWt,
                        'qty'           => $rq,
                        'unit_price'    => $price,
                        'gst_rate'      => $gstRate,
                        'gst_type'      => $gstType,
                        'tax_mode'      => $taxMode,
                        'total_price'   => $itemBase,
                        'tax_amount'    => $itemTax,
                        'photo_path'    => $rollPhotoPath,
                        'category_name' => 'Fabric Rolls',
                        'product_type'  => 'Roll',
                        'mrp'           => null,
                        'selling_price' => null,
                    ];
                }
            }
        }

        // 2. Other Garment Supplies (Exact same GST and HSN fields)
        if (is_array($garmentOtherRaw)) {
            foreach ($garmentOtherRaw as $idx => $g) {
                $qty   = (float)($g['quantity'] ?? ($g['qty'] ?? 0));
                $price = (float)($g['unit_price'] ?? ($g['rate'] ?? 0));

                if ($qty > 0 && $price > 0) {
                    $taxMode  = trim($g['tax_mode'] ?? 'Exclude GST');
                    $gstType  = trim($g['gst'] ?? ($g['gst_type'] ?? 'Exempted'));
                    $taxType  = trim($g['tax_input_type'] ?? 'Percentage');
                    $sgstVal  = (float)($g['sgst'] ?? 0);
                    $cgstVal  = (float)($g['cgst'] ?? 0);
                    $igstVal  = (float)($g['igst'] ?? 0);
                    $fallback = (float)($g['gst_rate'] ?? 0);

                    $calc = $this->calculateLineTax($qty, $price, $taxMode, $gstType, $taxType, $sgstVal, $cgstVal, $igstVal, $fallback);
                    $itemBase = $calc['itemBase'];
                    $itemTax  = $calc['itemTax'];
                    $gstRate  = $calc['gstRate'];

                    $totalAmount += $itemBase;
                    $totalTax    += $itemTax;

                    $itemsToInsert[] = [
                        'section_type'  => 'garment_other',
                        'variant_id'    => !empty($g['variant_id']) ? (int)$g['variant_id'] : null,
                        'item_name'     => trim($g['item_name'] ?? 'Garment Supply'),
                        'item_code'     => trim($g['product_no'] ?? ($g['item_code'] ?? '')),
                        'hsn_code'      => trim($g['hsn'] ?? ''),
                        'specification' => trim($g['item_name'] ?? ''),
                        'color'         => null,
                        'pattern'       => null,
                        'designs_count' => 1,
                        'rq_qty'        => 1,
                        'unit'          => trim($g['unit'] ?? 'Pieces'),
                        'gross_weight'  => null,
                        'net_weight'    => null,
                        'qty'           => $qty,
                        'unit_price'    => $price,
                        'gst_rate'      => $gstRate,
                        'gst_type'      => $gstType,
                        'tax_mode'      => $taxMode,
                        'total_price'   => $itemBase,
                        'tax_amount'    => $itemTax,
                        'photo_path'    => null,
                        'category_name' => 'Garment Other Supplies',
                        'product_type'  => 'Supply / Trim',
                        'mrp'           => null,
                        'selling_price' => null,
                    ];
                }
            }
        }

        // 3. Finished Goods (Exact same GST and HSN fields)
        if ($isProductAddingInvoice && is_array($finishedRaw)) {
            $expandedFinishedGoods = [];
            foreach ($finishedRaw as $finishedItem) {
                $variantRows = $finishedItem['variants'] ?? [];
                if (!is_array($variantRows) || empty($variantRows)) {
                    $this->flash('error', 'Add at least one variant row to every finished-goods style.');
                    $this->redirect($createRedirect);
                    return;
                }
                foreach ($variantRows as $variantRow) {
                    if (!is_array($variantRow)) {
                        continue;
                    }
                    $quantity = (float)($variantRow['quantity'] ?? 0);
                    $supplierCost = (float)($variantRow['supplier_cost'] ?? 0);
                    $sellingPrice = (float)($variantRow['selling_price'] ?? 0);
                    $mrp = (float)($variantRow['mrp'] ?? 0);
                    if (!is_finite($quantity) || !is_finite($supplierCost) || !is_finite($sellingPrice) || !is_finite($mrp)
                        || $quantity <= 0 || $supplierCost <= 0
                        || $sellingPrice <= $supplierCost || $mrp <= $sellingPrice) {
                        $this->flash('error', 'Every finished-goods variant must satisfy Supplier Cost < Selling Price < MRP and have a quantity greater than zero.');
                        $this->redirect($createRedirect);
                        return;
                    }
                    $expandedItem = array_merge($finishedItem, $variantRow);
                    $expandedItem['catalog_style_name'] = trim((string)($finishedItem['product'] ?? ''));
                    $expandedItem['product'] = trim((string)($variantRow['variant_name'] ?? ''));
                    $expandedItem['variant_name'] = $expandedItem['product'];
                    $expandedFinishedGoods[] = $expandedItem;
                }
            }
            $finishedRaw = $expandedFinishedGoods;
        }

        if (is_array($finishedRaw)) {
            foreach ($finishedRaw as $idx => $f) {
                $qty   = (float)($f['quantity'] ?? ($f['qty'] ?? 0));
                $price = (float)($f['supplier_cost'] ?? ($f['unit_price'] ?? 0));

                if ($qty > 0 && $price > 0) {
                    $catalogId = null;
                    $variantId = null;
                    $categoryName = trim($f['category'] ?? 'Apparel Finished Goods');
                    $productName = trim($f['product'] ?? ($f['item_name'] ?? 'Finished Garment'));
                    $itemCode = trim($f['sku'] ?? ($f['item_code'] ?? ''));
                    $hsnCode = trim($f['hsn'] ?? '');
                    $size = trim($f['size'] ?? ($f['variant'] ?? ''));
                    $color = '';
                    $mrp = (float)($f['mrp'] ?? 0);
                    $sellingPrice = (float)($f['selling_price'] ?? 0);
                    $catalogGstRate = (float)($f['catalog_gst_rate'] ?? 0);

                    $catalogDraft = null;
                    if ($isProductAddingInvoice) {
                        $categoryId = (int)($f['category_id'] ?? 0);
                        $subcategoryId = (int)($f['subcategory_id'] ?? 0) ?: null;
                        $subSubcategoryId = (int)($f['sub_sub_category_id'] ?? 0) ?: null;
                        $size = trim((string)($f['size'] ?? ''));
                        $color = trim((string)($f['color'] ?? ''));
                        $hsnCode = strtoupper(trim((string)($f['hsn'] ?? '')));
                        $hsnMaster = DB::fetch(
                            "SELECT gst_rate FROM hsn_masters
                             WHERE hsn_code = ? AND code_type = 'HSN' AND is_active = 1
                               AND (concern_id = ? OR concern_id IS NULL)
                             ORDER BY concern_id IS NULL ASC LIMIT 1",
                            [$hsnCode, $cid]
                        );
                        if (!$hsnMaster || $hsnMaster['gst_rate'] === null || $hsnMaster['gst_rate'] === ''
                            || !is_numeric($hsnMaster['gst_rate'])
                            || (float)$hsnMaster['gst_rate'] < 0 || (float)$hsnMaster['gst_rate'] > 100) {
                            $this->flash('error', 'Choose a registered HSN with a valid GST rate for every finished-goods variant.');
                            $this->redirect($createRedirect);
                            return;
                        }
                        $catalogGstRate = (float)$hsnMaster['gst_rate'];
                        $styleCode = strtoupper(trim((string)($f['style_code'] ?? '')));
                        $styleName = trim((string)($f['catalog_style_name'] ?? $productName));
                        $variantName = trim((string)($f['variant_name'] ?? $productName));
                        if ($variantName === '') {
                            $variantName = $styleName;
                        }
                        $productName = $variantName;
                        $customAttributes = [];
                        $reservedFields = [
                            'product', 'item_name', 'sku', 'item_code', 'product_code', 'variant_id', 'catalog_id',
                            'quantity', 'qty', 'supplier_cost', 'unit_price', 'hsn', 'size', 'variant',
                            'color', 'style_code', 'category_id', 'subcategory_id', 'sub_sub_category_id',
                            'catalog_gst_rate', 'tax_mode', 'gst', 'gst_type', 'tax_input_type',
                            'gst_rate', 'sgst', 'cgst', 'igst', 'mrp', 'selling_price', 'weight',
                            'catalog_style_name', 'variant_name',
                        ];
                        foreach ($f as $key => $value) {
                            if (in_array((string)$key, $reservedFields, true) || !is_scalar($value)) {
                                continue;
                            }
                            $value = trim((string)$value);
                            if ($value !== '') {
                                $customAttributes[(string)$key] = $value;
                            }
                        }
                        $catalogDraft = [
                            'style_code' => $styleCode,
                            'style_name' => $styleName,
                            'variant_name' => $variantName,
                            'product_code' => $itemCode,
                            'weight' => (float)($f['weight'] ?? 0),
                            'category_id' => $categoryId,
                            'subcategory_id' => $subcategoryId,
                            'sub_sub_category_id' => $subSubcategoryId,
                            'size' => $size,
                            'color' => $color,
                            'hsn_code' => $hsnCode,
                            'gst_rate' => $catalogGstRate,
                            'mrp' => $mrp,
                            'selling_price' => $sellingPrice,
                            'custom_attributes' => $customAttributes,
                        ];
                        $categoryName = '';
                    }

                    $taxMode  = trim($f['tax_mode'] ?? 'Exclude GST');
                    $gstType  = trim($f['gst'] ?? ($f['gst_type'] ?? 'Exempted'));
                    $taxType  = trim($f['tax_input_type'] ?? 'Percentage');
                    $sgstVal  = (float)($f['sgst'] ?? 0);
                    $cgstVal  = (float)($f['cgst'] ?? 0);
                    $igstVal  = (float)($f['igst'] ?? 0);
                    if (!in_array($taxType, ['Amount', 'Amount (₹)'], true)
                        && $catalogGstRate >= 0 && $catalogGstRate <= 100) {
                        if ($gstType === 'Both (CGST and SGST)') {
                            $sgstVal = $catalogGstRate / 2;
                            $cgstVal = $catalogGstRate / 2;
                        } elseif ($gstType === 'CGST') {
                            $cgstVal = $catalogGstRate / 2;
                        } elseif ($gstType === 'SGST') {
                            $sgstVal = $catalogGstRate / 2;
                        } elseif ($gstType === 'IGST') {
                            $igstVal = $catalogGstRate;
                        }
                    }
                    $fallback = $catalogGstRate > 0 ? $catalogGstRate : (float)($f['gst_rate'] ?? 0);

                    $calc = $this->calculateLineTax($qty, $price, $taxMode, $gstType, $taxType, $sgstVal, $cgstVal, $igstVal, $fallback);
                    $itemBase = $calc['itemBase'];
                    $itemTax  = $calc['itemTax'];
                    $gstRate  = $calc['gstRate'];

                    $totalAmount += $itemBase;
                    $totalTax    += $itemTax;

                    $itemsToInsert[] = [
                        'section_type'  => 'finished_goods',
                        'variant_id'    => $variantId,
                        'catalog_id'    => $catalogId,
                        'catalog_draft' => $catalogDraft,
                        'item_name'     => $productName,
                        'item_code'     => $itemCode,
                        'hsn_code'      => $hsnCode,
                        'specification' => $size,
                        'color'         => $color ?: null,
                        'pattern'       => null,
                        'designs_count' => 1,
                        'rq_qty'        => 1,
                        'unit'          => 'Pieces',
                        'gross_weight'  => null,
                        'net_weight'    => (float)($f['weight'] ?? 0),
                        'qty'           => $qty,
                        'unit_price'    => $price,
                        'gst_rate'      => $gstRate,
                        'gst_type'      => $gstType,
                        'tax_mode'      => $taxMode,
                        'total_price'   => $itemBase,
                        'tax_amount'    => $itemTax,
                        'photo_path'    => null,
                        'category_name' => $categoryName,
                        'product_type'  => trim($f['product_type'] ?? 'Individual Piece'),
                        'mrp'           => $mrp,
                        'selling_price' => $sellingPrice,
                    ];
                }
            }
        }

        // Backward compatibility fallback for single-item modal submissions
        if (empty($itemsToInsert) && !empty($_POST['variant_id']) && (float)($_POST['qty'] ?? 0) > 0) {
            $varId = (int)$_POST['variant_id'];
            $qty   = (float)$_POST['qty'];
            $price = (float)$_POST['unit_price'];
            $varName = DB::fetchValue("SELECT name FROM variants WHERE id = ?", [$varId]) ?: 'Product Item';
            $itemTotal = round($qty * $price, 2);
            $totalAmount += $itemTotal;
            $itemsToInsert[] = [
                'section_type'  => 'rolls',
                'variant_id'    => $varId,
                'item_name'     => $varName,
                'item_code'     => 'SKU-' . $varId,
                'hsn_code'      => null,
                'specification' => '',
                'color'         => null,
                'pattern'       => null,
                'designs_count' => 1,
                'rq_qty'        => 1,
                'unit'          => 'pcs',
                'gross_weight'  => null,
                'net_weight'    => null,
                'qty'           => $qty,
                'unit_price'    => $price,
                'gst_rate'      => 0.00,
                'gst_type'      => 'Exempted',
                'tax_mode'      => 'Exclude GST',
                'total_price'   => $itemTotal,
                'tax_amount'    => 0.00,
                'photo_path'    => null,
                'category_name' => 'General',
                'product_type'  => 'Item',
                'mrp'           => null,
                'selling_price' => null,
            ];
        }

        if (empty($itemsToInsert)) {
            $this->flash('error', 'Please enter at least one item with quantity and price.');
            $this->redirect($createRedirect);
            return;
        }

        if ($isProductAddingInvoice && $matchedPurchaseBillQty !== null) {
            $newTotalQty = array_reduce($itemsToInsert, fn($carry, $it) => $carry + (float)($it['qty'] ?? 0), 0.0);
            $remainingQty = max(0.0, $matchedPurchaseBillQty - $alreadyAddedProductAddingQty);
            if (($alreadyAddedProductAddingQty + $newTotalQty) > ($matchedPurchaseBillQty + 0.0001)) {
                $this->flash(
                    'error',
                    "Product Adding quantity ({$newTotalQty}) exceeds remaining Purchase Billing invoice quantity ({$remainingQty} of {$matchedPurchaseBillQty}) for invoice '{$supplierInvoiceNo}'."
                );
                $this->redirect($createRedirect);
                return;
            }
        }

        try {
            $attachmentPaths = $this->uploadBillAttachments();
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect($createRedirect);
            return;
        }
        if (empty($attachmentPaths)) {
            $this->flash('error', 'Tax Invoice / Bill Attachment is required. Please upload at least one invoice file.');
            $this->redirect($createRedirect);
            return;
        }
        $attachmentPath = $attachmentPaths[0] ?? null;
        $attachmentPathsJson = empty($attachmentPaths)
            ? null
            : json_encode($attachmentPaths, JSON_UNESCAPED_SLASHES);

        $netAmount = round($totalAmount + $totalTax, 2);
        if ($paymentStatus === 'paid' && $paidAmount <= 0) {
            $paidAmount = $netAmount;
        }

        PurchaseBillSourceService::ensureSchema();
        DB::beginTransaction();
        try {
            $billNo = NumberSeries::getNext($cid, 'PO');
            $catalogCache = [];
            foreach ($itemsToInsert as &$item) {
                if (!empty($item['catalog_draft']) && is_array($item['catalog_draft'])) {
                    $mappedProduct = $this->createProductAddingCatalogVariant(
                        $cid,
                        $item['catalog_draft'],
                        (float)$item['unit_price'],
                        $catalogCache
                    );
                    $item['catalog_id'] = $mappedProduct['catalog_id'];
                    $item['variant_id'] = $mappedProduct['variant_id'];
                    $item['item_code'] = $mappedProduct['product_code'];
                    $item['category_name'] = $mappedProduct['category_name'];
                    unset($item['catalog_draft']);
                }
            }
            unset($item);

            DB::query(
                "INSERT INTO purchase_bills (
                    concern_id, bill_no, supplier_invoice_no, source_module, supplier_id, bill_date,
                    total_amount, tax_amount, net_amount, payment_status, payment_method, payment_reference, paid_amount,
                    inward_status, notes, attachment_path, attachment_paths, fabric_description, created_by, created_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $cid, $billNo, $supplierInvoiceNo, $isProductAddingInvoice ? 'product_adding' : 'purchase_billing', $supplierId, $billDate,
                    $totalAmount, $totalTax, $netAmount, $paymentStatus, $paymentMethod, $paymentReference, $paidAmount,
                    $inwardStatus, $notes, $attachmentPath, $attachmentPathsJson, $fabricDescription, Auth::id()
                ]
            );
            $billId = (int)DB::lastInsertId();

            foreach ($itemsToInsert as $item) {
                DB::query(
                    "INSERT INTO purchase_bill_items (
                        concern_id, purchase_bill_id, variant_id, section_type, item_name, item_code,
                        hsn_code, specification, color, pattern, designs_count, rq_qty, unit,
                        gross_weight, net_weight, qty, inward_status, unit_price, gst_rate, gst_type, tax_mode, total_price, tax_amount,
                        photo_path, category_name, product_type, mrp, selling_price
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $cid,
                        $billId,
                        $item['variant_id'],
                        $item['section_type'],
                        $item['item_name'],
                        $item['item_code'],
                        $item['hsn_code'],
                        $item['specification'],
                        $item['color'],
                        $item['pattern'],
                        $item['designs_count'],
                        $item['rq_qty'],
                        $item['unit'],
                        $item['gross_weight'],
                        $item['net_weight'],
                        $item['qty'],
                        $inwardStatus,
                        $item['unit_price'],
                        $item['gst_rate'],
                        $item['gst_type'],
                        $item['tax_mode'],
                        $item['total_price'],
                        $item['tax_amount'],
                        $item['photo_path'],
                        $item['category_name'],
                        $item['product_type'],
                        $item['mrp'],
                        $item['selling_price'],
                    ]
                );
                $purchaseItemId = (int)DB::lastInsertId();
                if (!empty($item['catalog_id'])) {
                    DB::query(
                        "UPDATE purchase_bill_items SET catalog_id = ? WHERE id = ?",
                        [(int)$item['catalog_id'], $purchaseItemId]
                    );
                }

                // If bill was directly marked completed upon creation, inward finished goods to stock ledger
                if ($inwardStatus === 'completed' && !empty($item['variant_id']) && $item['section_type'] === 'finished_goods') {
                    try {
                        StockService::move(
                            $cid,
                            (int)$item['variant_id'],
                            'Main Store',
                            (float)$item['qty'],
                            0.00,
                            'Purchase Inward',
                            $billId,
                            "Purchase Inward from Bill #{$billNo}"
                        );
                    } catch (\Exception $stockEx) {
                        // Stock movement logged or skipped if zero
                    }
                }

                // Ensure newly recorded HSN code is preserved in HSN master for future use
                if (!empty($item['hsn_code']) && $item['hsn_code'] !== '-') {
                    $existingHsn = DB::fetchValue("SELECT id FROM hsn_masters WHERE hsn_code = ? AND (concern_id = ? OR concern_id IS NULL)", [$item['hsn_code'], $cid]);
                    if (!$existingHsn) {
                        DB::query(                        "INSERT INTO hsn_masters (concern_id, code_type, hsn_code, description, gst_rate, created_at) VALUES (?, 'HSN', ?, ?, ?, NOW())", [
                            $cid,
                            $item['hsn_code'],
                            $item['item_name'] ?? 'Recorded HSN',
                            isset($item['gst_rate']) && $item['gst_rate'] !== '' ? $item['gst_rate'] : null
                        ]);
                    }
                }
            }

            DB::commit();

            // Synchronize and consolidate fabric roll specifications & accumulated stock
            RollRegistryService::syncSpecs($cid);

            $hasRolls = false;
            $hasOther = false;
            $hasFg = false;
            foreach ($itemsToInsert as $it) {
                if ($it['section_type'] === 'rolls') {
                    $hasRolls = true;
                } elseif ($it['section_type'] === 'garment_other') {
                    $hasOther = true;
                } elseif ($it['section_type'] === 'finished_goods') {
                    $hasFg = true;
                }
            }

            if ($hasRolls) {
                $targetTab = 'purchase_raw';
                $targetSubtab = 'rolls';
            } elseif ($hasOther) {
                $targetTab = 'purchase_raw';
                $targetSubtab = 'garment_other';
            } elseif ($hasFg) {
                $targetTab = 'purchase_fg';
                $targetSubtab = '';
            } else {
                $targetTab = 'purchase_raw';
                $targetSubtab = 'rolls';
            }

            $this->flash('success', "Production Purchase '{$billNo}' recorded successfully!");
            $this->redirect($isProductAddingInvoice ? $successRedirect . '?bill_id=' . $billId : $successRedirect);
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Error recording production purchase: ' . $e->getMessage());
            $this->redirect($createRedirect);
        }
    }

    private function createProductAddingCatalogVariant(int $concernId, array $draft, float $supplierCost, array &$catalogCache): array
    {
        $styleCode = strtoupper(trim((string)($draft['style_code'] ?? '')));
        $styleName = trim((string)($draft['style_name'] ?? ''));
        $variantName = trim((string)($draft['variant_name'] ?? ''));
        $requestedCode = strtoupper(trim((string)($draft['product_code'] ?? '')));
        $categoryId = (int)($draft['category_id'] ?? 0);
        $subcategoryId = (int)($draft['subcategory_id'] ?? 0) ?: null;
        $subSubcategoryId = (int)($draft['sub_sub_category_id'] ?? 0) ?: null;
        $size = trim((string)($draft['size'] ?? ''));
        $color = trim((string)($draft['color'] ?? ''));
        $weight = (float)($draft['weight'] ?? 0);
        $hsnCode = strtoupper(trim((string)($draft['hsn_code'] ?? '')));
        $sellingPrice = (float)($draft['selling_price'] ?? 0);
        $mrp = (float)($draft['mrp'] ?? 0);

        if ($styleName === '' && $variantName !== '') {
            $styleName = $variantName;
        }
        if (mb_strlen($styleName) < 2 || mb_strlen($styleName) > 150) {
            throw new Exception('Enter a product name between 2 and 150 characters.');
        }
        if ($styleCode === '') {
            $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $styleName) ?: 'PRD');
            $styleCode = substr($prefix, 0, 8) . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        }
        if (strlen($styleCode) > 50) {
            $styleCode = substr($styleCode, 0, 50);
        }
        if ($supplierCost <= 0 || $sellingPrice <= $supplierCost || $mrp <= $sellingPrice) {
            throw new Exception('New catalog pricing must follow Supplier Cost < Selling Price < MRP.');
        }
        if ($size === '') {
            $size = 'Free Size';
        }
        if ($color === '') {
            $color = 'Standard';
        }
        if (mb_strlen($size) > 50 || mb_strlen($color) > 100 || $weight < 0 || $weight > 999999) {
            throw new Exception('Choose a valid size and color for the new catalog variant.');
        }
        if ($variantName === '') {
            $variantName = $styleName;
        }
        if (mb_strlen($variantName) < 2 || mb_strlen($variantName) > 150) {
            throw new Exception('Variant name must be between 2 and 150 characters.');
        }
        if ($requestedCode !== '' && strlen($requestedCode) > 50) {
            throw new Exception('Product Code must be 50 characters or fewer.');
        }
        if (!preg_match('/^\d{4}(?:\d{2}){0,2}$/', $hsnCode)) {
            throw new Exception('Select or create a valid 4-, 6-, or 8-digit HSN code.');
        }

        $category = $categoryId > 0 ? DB::fetch(
            "SELECT id, name FROM categories
             WHERE id = ? AND status = 'active'
               AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
            [$categoryId, $concernId]
        ) : null;
        if (!$category) {
            $category = DB::fetch(
                "SELECT id, name FROM categories
                 WHERE status = 'active'
                   AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)
                 ORDER BY id ASC LIMIT 1",
                [$concernId]
            );
            if ($category) {
                $categoryId = (int)$category['id'];
            } else {
                DB::query("INSERT INTO categories (concern_id, name, code, status) VALUES (?, 'General', 'GEN', 'active')", [$concernId]);
                $categoryId = (int)DB::lastInsertId();
                $category = ['id' => $categoryId, 'name' => 'General'];
            }
        }
        $subcategory = null;
        if ($subcategoryId) {
            $subcategory = DB::fetch(
                "SELECT id, name FROM subcategories
                 WHERE id = ? AND category_id = ? AND status = 'active'
                   AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
                [$subcategoryId, $categoryId, $concernId]
            );
            if (!$subcategory) {
                throw new Exception('The selected subcategory does not belong to the chosen main category.');
            }
        }
        $subSubcategory = null;
        if ($subSubcategoryId) {
            if (!$subcategoryId) {
                throw new Exception('Choose a subcategory before selecting a sub-subcategory.');
            }
            $subSubcategory = DB::fetch(
                "SELECT id, name FROM sub_subcategories
                 WHERE id = ? AND subcategory_id = ?
                   AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
                [$subSubcategoryId, $subcategoryId, $concernId]
            );
            if (!$subSubcategory) {
                throw new Exception('The selected sub-subcategory does not belong to the chosen subcategory.');
            }
        }

        $hsn = DB::fetch(
            "SELECT gst_rate FROM hsn_masters
             WHERE hsn_code = ? AND code_type = 'HSN' AND is_active = 1
               AND (concern_id = ? OR concern_id IS NULL)
             ORDER BY concern_id IS NULL ASC LIMIT 1",
            [$hsnCode, $concernId]
        );
        if (!$hsn) {
            throw new Exception('The selected HSN is not registered. Use Add HSN to create it first.');
        }
        $gstRate = $hsn['gst_rate'] !== null && $hsn['gst_rate'] !== ''
            ? (float)$hsn['gst_rate']
            : (float)($draft['gst_rate'] ?? -1);
        if ($gstRate < 0 || $gstRate > 100) {
            throw new Exception('Enter a valid GST rate for the selected HSN code.');
        }

        $customAttributes = $draft['custom_attributes'] ?? [];
        if (!is_array($customAttributes)) {
            throw new Exception('Submitted product attributes are invalid.');
        }
        $allowedAttributeTypes = ['size' => true, 'color' => true];
        $attributeTypes = DB::fetchAll(
            'SELECT DISTINCT attribute_type FROM product_attributes
             WHERE concern_id = ? OR concern_id = 1 OR concern_id IS NULL',
            [$concernId]
        );
        foreach ($attributeTypes as $attributeType) {
            $type = strtolower(trim((string)($attributeType['attribute_type'] ?? '')));
            if ($type !== '') {
                $allowedAttributeTypes[$type] = true;
            }
        }
        $normalizedAttributes = [];
        foreach ($customAttributes as $type => $value) {
            $type = strtolower(trim((string)$type));
            $value = is_scalar($value) ? trim((string)$value) : '';
            if ($value === '') {
                continue;
            }
            if ($type === '' || !isset($allowedAttributeTypes[$type]) || strlen($type) > 50 || mb_strlen($value) > 100
                || in_array($type, ['hsn', 'weight', 'allocated_qty', 'custom_attributes'], true)) {
                throw new Exception('A submitted product attribute is invalid.');
            }
            $normalizedAttributes[$type] = $value;
        }

        $styleKey = strtolower($styleCode);
        if (isset($catalogCache[$styleKey])) {
            $cached = $catalogCache[$styleKey];
            if (
                $cached['style_name'] !== $styleName
                || $cached['category_id'] !== $categoryId
                || $cached['subcategory_id'] !== $subcategoryId
                || $cached['sub_sub_category_id'] !== $subSubcategoryId
            ) {
                throw new Exception('A style code can only be reused for variants with the same name and category hierarchy.');
            }
        } else {
            if (DB::fetchValue(
                "SELECT id FROM catalogs WHERE concern_id = ? AND style_code = ? AND source_module = 'product_adding' LIMIT 1",
                [$concernId, $styleCode]
            )) {
                $styleCode = substr($styleCode, 0, 44) . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            }

            $categoryCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$category['name']) ?: 'CAT');
            $subcategoryCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($subcategory['name'] ?? '')) ?: 'SUB');
            $subSubcategoryCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($subSubcategory['name'] ?? '')) ?: 'SS');
            $shortCode = substr($categoryCode, 0, 2) . substr($subcategoryCode, 0, 2) . substr($subSubcategoryCode, 0, 2);
            $hierarchyPath = implode(' > ', array_filter([
                $category['name'] ?? '',
                $subcategory['name'] ?? '',
                $subSubcategory['name'] ?? '',
            ]));

            DB::query(
                "INSERT INTO catalogs (
                    concern_id, code, style_code, short_code, category_id, subcategory_id, sub_sub_category_id,
                    main_category_code, sub_category_code, sub_sub_category_code, hierarchy_path,
                    hsn_code, gst_rate, sizes, status, source_module, name, created_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'product_adding', ?, NOW())",
                [
                    $concernId, $styleCode, $styleCode, $shortCode, $categoryId, $subcategoryId, $subSubcategoryId,
                    substr($categoryCode, 0, 10), substr($subcategoryCode, 0, 10), substr($subSubcategoryCode, 0, 10),
                    $hierarchyPath, $hsnCode, $gstRate, json_encode([$size]), $styleName,
                ]
            );
            $catalogCache[$styleKey] = [
                'catalog_id' => (int)DB::lastInsertId(),
                'style_name' => $styleName,
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
                'sub_sub_category_id' => $subSubcategoryId,
                'hsn_code' => $hsnCode,
                'gst_rate' => $gstRate,
                'category_name' => (string)$category['name'],
                'sizes' => [$size],
            ];
        }

        $catalog = &$catalogCache[$styleKey];
        if (!in_array($size, $catalog['sizes'], true)) {
            $catalog['sizes'][] = $size;
            DB::query('UPDATE catalogs SET sizes = ? WHERE id = ?', [json_encode($catalog['sizes']), $catalog['catalog_id']]);
        }

        $resolvedVariant = \App\Services\VariantService::findOrCreate($concernId, [
            'catalog_id' => $catalog['catalog_id'],
            'name' => $variantName,
            'category_id' => $categoryId,
            'subcategory_id' => $subcategoryId,
            'sub_sub_category_id' => $subSubcategoryId,
            'size' => $size,
            'color' => $color,
            'hsn_code' => $hsnCode,
            'weight' => $weight,
            'weight_unit' => 'kg',
            'cost_price' => $supplierCost,
            'price' => $sellingPrice,
            'mrp' => $mrp,
            'custom_attributes' => $normalizedAttributes,
        ]);
        $variantId = (int)$resolvedVariant['variant_id'];
        $productCode = (string)$resolvedVariant['product_code'];
        $resolvedCatalogId = (int)($resolvedVariant['catalog_id'] ?: $catalog['catalog_id']);

        $attributeValues = array_merge(['size' => $size, 'color' => $color], $normalizedAttributes);
        foreach ($attributeValues as $type => $value) {
            $knownRows = DB::fetchAll(
                'SELECT attribute_value FROM product_attributes
                 WHERE (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)
                   AND LOWER(TRIM(attribute_type)) = LOWER(TRIM(?))',
                [$concernId, $type]
            );
            $exists = false;
            foreach ($knownRows as $knownRow) {
                $knownValues = preg_split('/[,;|]+/', (string)($knownRow['attribute_value'] ?? ''));
                foreach ($knownValues as $knownValue) {
                    if (strcasecmp(trim($knownValue), $value) === 0) {
                        $exists = true;
                        break 2;
                    }
                }
            }
            if (!$exists) {
                $sortOrder = (int)DB::fetchValue(
                    'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_attributes WHERE concern_id = ?',
                    [$concernId]
                );
                DB::query(
                    'INSERT INTO product_attributes (concern_id, attribute_type, attribute_name, attribute_value, sort_order)
                     VALUES (?, ?, ?, ?, ?)',
                    [$concernId, $type, ucfirst($type), $value, $sortOrder]
                );
            }
        }

        return [
            'catalog_id' => $resolvedCatalogId,
            'variant_id' => $variantId,
            'product_code' => $productCode,
            'category_name' => $catalog['category_name'],
        ];
    }

    private function parseSimplePurchaseItems(array $rawItems, float &$totalAmount, float &$totalTax): array
    {
        $items = [];
        $typeLabels = [
            'rolls' => 'Fabric',
            'garment_other' => 'Other Garments Supply',
            'finished_goods' => 'Finished Goods',
        ];

        foreach ($rawItems as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sectionType = trim((string)($row['product_type'] ?? 'rolls'));
            if (!isset($typeLabels[$sectionType])) {
                $sectionType = 'rolls';
            }
            $name = trim((string)($row['name'] ?? ($row['item_name'] ?? '')));
            $qty = (float)($row['qty'] ?? ($row['quantity'] ?? 0));
            $price = (float)($row['price'] ?? ($row['unit_price'] ?? 0));

            if ($name === '' || $qty <= 0 || $price <= 0) {
                continue;
            }

            $hsnCode = trim((string)($row['hsn'] ?? ($row['hsn_code'] ?? '')));
            $taxMode = trim((string)($row['tax_mode'] ?? 'Exclude GST'));
            $gstType = trim((string)($row['gst'] ?? ($row['gst_type'] ?? 'Exempted')));
            $taxType = trim((string)($row['tax_input_type'] ?? 'Percentage'));
            $sgstVal = (float)($row['sgst'] ?? 0);
            $cgstVal = (float)($row['cgst'] ?? 0);
            $igstVal = (float)($row['igst'] ?? 0);
            $fallback = (float)($row['gst_rate'] ?? 0);

            $calc = $this->calculateLineTax($qty, $price, $taxMode, $gstType, $taxType, $sgstVal, $cgstVal, $igstVal, $fallback);
            $totalAmount += $calc['itemBase'];
            $totalTax += $calc['itemTax'];

            $items[] = [
                'section_type'  => $sectionType,
                'variant_id'    => null,
                'catalog_id'    => null,
                'item_name'     => $name,
                'item_code'     => '',
                'hsn_code'      => $hsnCode ?: null,
                'specification' => '',
                'color'         => null,
                'pattern'       => null,
                'designs_count' => 1,
                'rq_qty'        => $sectionType === 'rolls' ? max(1, (int)round($qty)) : 1,
                'unit'          => $sectionType === 'rolls' ? 'Rolls' : 'Pieces',
                'gross_weight'  => null,
                'net_weight'    => null,
                'qty'           => $qty,
                'unit_price'    => $price,
                'gst_rate'      => $calc['gstRate'],
                'gst_type'      => $gstType,
                'tax_mode'      => $taxMode,
                'total_price'   => $calc['itemBase'],
                'tax_amount'    => $calc['itemTax'],
                'photo_path'    => null,
                'category_name' => $typeLabels[$sectionType],
                'product_type'  => $typeLabels[$sectionType],
                'mrp'           => null,
                'selling_price' => null,
            ];
        }

        return $items;
    }

    public function updateBill(): void {
        $this->validateCsrf();
        $isProductAddingEdit = ($_POST['product_adding_redirect'] ?? '') === '1';
        if ($isProductAddingEdit) {
            PurchaseBillSourceService::ensureSchema();
        }
        $billId = (int)($_POST['bill_id'] ?? 0);
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);
        $billListUrl = $isProductAddingEdit ? '/m/product_adding' : '/m/purchase_billing';
        $editBillUrl = $isProductAddingEdit
            ? '/m/product_adding/edit_purchase_bill?id=' . $billId
            : '/m/purchase_billing/edit?id=' . $billId;

        if (!$billId) {
            $this->flash('error', 'Invalid purchase bill ID.');
            $this->redirect($billListUrl);
            return;
        }

        $bill = null;
        if ($cid) {
            $bill = DB::fetch("SELECT * FROM purchase_bills WHERE id = ? AND concern_id = ?", [$billId, $cid]);
        } else {
            $bill = DB::fetch("SELECT * FROM purchase_bills WHERE id = ?", [$billId]);
            if ($bill) {
                $cid = (int)$bill['concern_id'];
            }
        }

        if (!$bill) {
            $this->flash('error', 'Purchase Bill not found or access denied.');
            $this->redirect($billListUrl);
            return;
        }
        if ($isProductAddingEdit && ($bill['source_module'] ?? '') !== 'purchase_billing') {
            $this->flash('error', 'Only Purchase Billing invoices can be edited from Product Adding.');
            $this->redirect($billListUrl);
            return;
        }

        if ($isProductAddingEdit) {
            $inwardedItemCount = (int)DB::fetchValue(
                "SELECT COUNT(*)
                 FROM purchase_bill_items pbi
                 JOIN purchase_bills pb ON pb.id = pbi.purchase_bill_id
                 WHERE pbi.purchase_bill_id = ?
                   AND COALESCE(NULLIF(pbi.inward_status, ''), pb.inward_status, 'pending') = 'completed'",
                [$billId]
            );
            if (($bill['inward_status'] ?? 'pending') === 'completed' || $inwardedItemCount > 0) {
                $this->flash('error', 'This bill has items already added to stock and can no longer be edited.');
                $this->redirect($billListUrl);
                return;
            }
        }

        $supplierId = (int)($_POST['supplier_id'] ?? $bill['supplier_id']);
        if (!$supplierId) {
            $this->flash('error', 'Please select a registered supplier.');
            $this->redirect($editBillUrl);
            return;
        }

        $billDate          = !empty($_POST['bill_date']) ? $_POST['bill_date'] : $bill['bill_date'];
        $supplierInvoiceNo = trim($_POST['supplier_invoice_no'] ?? '');
        if (empty($supplierInvoiceNo)) {
            $this->flash('error', 'Invoice / Bill Number is required.');
            $this->redirect($editBillUrl);
            return;
        }
        if ($this->supplierInvoiceNumberExists($cid, $supplierId, $supplierInvoiceNo, $billId)) {
            $this->flash('error', 'This supplier invoice number has already been recorded for the selected supplier.');
            $this->redirect($editBillUrl);
            return;
        }
        $fabricDescription = trim($_POST['fabric_description'] ?? '');
        $notes             = trim($_POST['notes'] ?? '');
        $paymentStatus     = trim($_POST['payment_status'] ?? ($bill['payment_status'] ?? 'unpaid'));
        $paymentMethod     = trim($_POST['payment_method'] ?? ($bill['payment_method'] ?? 'credit'));
        $paymentReference  = trim($_POST['payment_reference'] ?? ($bill['payment_reference'] ?? ''));
        $paidAmount        = isset($_POST['paid_amount']) ? (float)$_POST['paid_amount'] : (float)($bill['paid_amount'] ?? 0.0);
        $inwardStatus      = (($bill['inward_status'] ?? 'pending') === 'completed') ? 'completed' : 'pending';

        if (!in_array($paymentStatus, ['unpaid', 'partial', 'paid'], true)) {
            $paymentStatus = 'unpaid';
        }
        // Process sections
        $simpleItemsRaw  = $_POST['purchase_items'] ?? [];
        $rollsRaw        = $_POST['rolls'] ?? [];
        $garmentOtherRaw = $_POST['garment_other'] ?? [];
        $finishedRaw     = $_POST['finished_goods'] ?? [];

        $itemsToInsert = [];
        $totalAmount   = 0.0;
        $totalTax      = 0.0;

        if (!empty($simpleItemsRaw) && is_array($simpleItemsRaw)) {
            $itemsToInsert = $this->parseSimplePurchaseItems($simpleItemsRaw, $totalAmount, $totalTax);
        }

        // 1. Rolls
        if (is_array($rollsRaw)) {
            foreach ($rollsRaw as $idx => $r) {
                $netWt = (float)($r['weight'] ?? ($r['net_weight'] ?? 0));
                $price = (float)($r['price'] ?? ($r['rate'] ?? 0));
                $rq    = max(1, (int)($r['rq'] ?? 1));

                if ($rq > 0 && $price > 0) {
                    $taxMode  = trim($r['tax_mode'] ?? 'Exclude GST');
                    $gstType  = trim($r['gst'] ?? ($r['gst_type'] ?? 'Exempted'));
                    $taxType  = trim($r['tax_input_type'] ?? 'Percentage');
                    $sgstVal  = (float)($r['sgst'] ?? 0);
                    $cgstVal  = (float)($r['cgst'] ?? 0);
                    $igstVal  = (float)($r['igst'] ?? 0);
                    $fallback = (float)($r['gst_rate'] ?? 0);

                    $calc = $this->calculateLineTax($rq, $price, $taxMode, $gstType, $taxType, $sgstVal, $cgstVal, $igstVal, $fallback);
                    $itemBase = $calc['itemBase'];
                    $itemTax  = $calc['itemTax'];
                    $gstRate  = $calc['gstRate'];

                    $totalAmount += $itemBase;
                    $totalTax    += $itemTax;

                    // Handle roll photo upload (Cloudinary first, local disk fallback)
                    $rollPhotoPath = !empty($r['existing_photo']) ? trim($r['existing_photo']) : null;
                    $rollFile = CloudinaryService::normalizeNestedFile($_FILES['rolls'] ?? [], $idx, 'photo');
                    if ($rollFile) {
                        $savedPhoto = CloudinaryService::saveUploadedFile($rollFile, 'rolls', ['jpg', 'jpeg', 'png', 'webp']);
                        if ($savedPhoto) {
                            $rollPhotoPath = $savedPhoto;
                        }
                    }

                    $rawRollCode = trim($r['roll_code'] ?? '');
                    $rollPattern = trim($r['pattern'] ?? ($r['design_name'] ?? ''));
                    $rollColor   = trim($r['color'] ?? ($r['shade'] ?? ''));
                    $rollShade   = trim($r['shade'] ?? '');
                    $rollSpec    = (!empty($rollShade) && strcasecmp($rollShade, $rollColor) !== 0) ? $rollShade : null;
                    $rollName    = !empty($rawRollCode) ? $rawRollCode : (!empty($rollPattern) ? ($rollPattern . ' Fabric') : ('ROLL #' . $idx));

                    $itemsToInsert[] = [
                        'section_type'  => 'rolls',
                        'variant_id'    => !empty($r['variant_id']) ? (int)$r['variant_id'] : null,
                        'item_name'     => $rollName,
                        'item_code'     => trim($r['roll_code'] ?? ('ROLL #' . $idx)),
                        'hsn_code'      => trim($r['hsn'] ?? ($r['hsn_code'] ?? '')),
                        'specification' => $rollSpec,
                        'color'         => $rollColor,
                        'pattern'       => $rollPattern,
                        'designs_count' => 1,
                        'rq_qty'        => $rq,
                        'unit'          => 'RQ',
                        'gross_weight'  => $netWt,
                        'net_weight'    => $netWt,
                        'qty'           => $rq,
                        'unit_price'    => $price,
                        'gst_rate'      => $gstRate,
                        'gst_type'      => $gstType,
                        'tax_mode'      => $taxMode,
                        'total_price'   => $itemBase,
                        'tax_amount'    => $itemTax,
                        'photo_path'    => $rollPhotoPath,
                        'category_name' => 'Fabric Rolls',
                        'product_type'  => 'Roll',
                        'mrp'           => null,
                        'selling_price' => null,
                    ];
                }
            }
        }

        // 2. Garment Other (Exact same GST and HSN fields)
        if (is_array($garmentOtherRaw)) {
            foreach ($garmentOtherRaw as $idx => $g) {
                $qty   = (float)($g['quantity'] ?? ($g['qty'] ?? 0));
                $price = (float)($g['unit_price'] ?? ($g['rate'] ?? 0));

                if ($qty > 0 && $price > 0) {
                    $taxMode  = trim($g['tax_mode'] ?? 'Exclude GST');
                    $gstType  = trim($g['gst'] ?? ($g['gst_type'] ?? 'Exempted'));
                    $taxType  = trim($g['tax_input_type'] ?? 'Percentage');
                    $sgstVal  = (float)($g['sgst'] ?? 0);
                    $cgstVal  = (float)($g['cgst'] ?? 0);
                    $igstVal  = (float)($g['igst'] ?? 0);
                    $fallback = (float)($g['gst_rate'] ?? 0);

                    $calc = $this->calculateLineTax($qty, $price, $taxMode, $gstType, $taxType, $sgstVal, $cgstVal, $igstVal, $fallback);
                    $itemBase = $calc['itemBase'];
                    $itemTax  = $calc['itemTax'];
                    $gstRate  = $calc['gstRate'];

                    $totalAmount += $itemBase;
                    $totalTax    += $itemTax;

                    $itemsToInsert[] = [
                        'section_type'  => 'garment_other',
                        'variant_id'    => !empty($g['variant_id']) ? (int)$g['variant_id'] : null,
                        'item_name'     => trim($g['item_name'] ?? 'Garment Supply'),
                        'item_code'     => trim($g['product_no'] ?? ($g['item_code'] ?? '')),
                        'hsn_code'      => trim($g['hsn'] ?? ''),
                        'specification' => trim($g['item_name'] ?? ''),
                        'color'         => null,
                        'pattern'       => null,
                        'designs_count' => 1,
                        'rq_qty'        => 1,
                        'unit'          => trim($g['unit'] ?? 'Pieces'),
                        'gross_weight'  => null,
                        'net_weight'    => null,
                        'qty'           => $qty,
                        'unit_price'    => $price,
                        'gst_rate'      => $gstRate,
                        'gst_type'      => $gstType,
                        'tax_mode'      => $taxMode,
                        'total_price'   => $itemBase,
                        'tax_amount'    => $itemTax,
                        'photo_path'    => null,
                        'category_name' => 'Garment Other Supplies',
                        'product_type'  => 'Supply / Trim',
                        'mrp'           => null,
                        'selling_price' => null,
                    ];
                }
            }
        }

        // 3. Finished Goods (Exact same GST and HSN fields)
        if (is_array($finishedRaw)) {
            foreach ($finishedRaw as $idx => $f) {
                $qty   = (float)($f['quantity'] ?? ($f['qty'] ?? 0));
                $price = (float)($f['supplier_cost'] ?? ($f['unit_price'] ?? 0));

                if ($qty > 0 && $price > 0) {
                    $taxMode  = trim($f['tax_mode'] ?? 'Exclude GST');
                    $gstType  = trim($f['gst'] ?? ($f['gst_type'] ?? 'Exempted'));
                    $taxType  = trim($f['tax_input_type'] ?? 'Percentage');
                    $sgstVal  = (float)($f['sgst'] ?? 0);
                    $cgstVal  = (float)($f['cgst'] ?? 0);
                    $igstVal  = (float)($f['igst'] ?? 0);
                    $fallback = (float)($f['gst_rate'] ?? 0);

                    $calc = $this->calculateLineTax($qty, $price, $taxMode, $gstType, $taxType, $sgstVal, $cgstVal, $igstVal, $fallback);
                    $itemBase = $calc['itemBase'];
                    $itemTax  = $calc['itemTax'];
                    $gstRate  = $calc['gstRate'];

                    $totalAmount += $itemBase;
                    $totalTax    += $itemTax;

                    $itemsToInsert[] = [
                        'section_type'  => 'finished_goods',
                        'variant_id'    => !empty($f['variant_id']) ? (int)$f['variant_id'] : null,
                        'item_name'     => trim($f['product'] ?? ($f['item_name'] ?? 'Finished Garment')),
                        'item_code'     => trim($f['sku'] ?? ($f['item_code'] ?? '')),
                        'hsn_code'      => trim($f['hsn'] ?? ''),
                        'specification' => trim($f['variant'] ?? ($f['size'] ?? '')),
                        'color'         => null,
                        'pattern'       => null,
                        'designs_count' => 1,
                        'rq_qty'        => 1,
                        'unit'          => 'Pieces',
                        'gross_weight'  => null,
                        'net_weight'    => (float)($f['weight'] ?? 0),
                        'qty'           => $qty,
                        'unit_price'    => $price,
                        'gst_rate'      => $gstRate,
                        'gst_type'      => $gstType,
                        'tax_mode'      => $taxMode,
                        'total_price'   => $itemBase,
                        'tax_amount'    => $itemTax,
                        'photo_path'    => null,
                        'category_name' => trim($f['category'] ?? 'Apparel Finished Goods'),
                        'product_type'  => trim($f['product_type'] ?? 'Individual Piece'),
                        'mrp'           => (float)($f['mrp'] ?? 0),
                        'selling_price' => (float)($f['selling_price'] ?? 0),
                    ];
                }
            }
        }

        if (empty($itemsToInsert)) {
            $this->flash('error', 'Please enter at least one item with valid quantity and price.');
            $this->redirect($editBillUrl);
            return;
        }

        try {
            $attachmentPaths = $this->uploadBillAttachments($this->getBillAttachmentPaths($bill));
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect($editBillUrl);
            return;
        }
        if (empty($attachmentPaths)) {
            $this->flash('error', 'Tax Invoice / Bill Attachment is required. Please upload at least one invoice file.');
            $this->redirect($editBillUrl);
            return;
        }
        $attachmentPath = $attachmentPaths[0] ?? null;
        $attachmentPathsJson = empty($attachmentPaths)
            ? null
            : json_encode($attachmentPaths, JSON_UNESCAPED_SLASHES);

        $netAmount = round($totalAmount + $totalTax, 2);
        if ($paymentStatus === 'paid' && $paidAmount <= 0) {
            $paidAmount = $netAmount;
        }

        PurchaseBillSourceService::ensureSchema();
        DB::beginTransaction();
        try {
            $lockedBillSql = 'SELECT id, concern_id, source_module, inward_status FROM purchase_bills WHERE id = ? AND concern_id = ? FOR UPDATE';
            $lockedBill = DB::fetch($lockedBillSql, [$billId, $cid]);
            if (!$lockedBill || ($isProductAddingEdit && ($lockedBill['source_module'] ?? '') !== 'purchase_billing')) {
                throw new \RuntimeException('Purchase Bill not found or access denied.');
            }
            if ($isProductAddingEdit) {
                $lockedStockedCount = (int)DB::fetchValue(
                    "SELECT COUNT(*)
                     FROM purchase_bill_items pbi
                     JOIN purchase_bills pb ON pb.id = pbi.purchase_bill_id
                     WHERE pbi.purchase_bill_id = ?
                       AND COALESCE(NULLIF(pbi.inward_status, ''), pb.inward_status, 'pending') = 'completed'",
                    [$billId]
                );
                if (($lockedBill['inward_status'] ?? 'pending') === 'completed' || $lockedStockedCount > 0) {
                    throw new \RuntimeException('This invoice has already had stock added and can no longer be edited.');
                }
            }

            PurchaseBillSourceService::ensureSchema();
            DB::query(
                "UPDATE purchase_bills
                 SET supplier_id = ?, bill_date = ?, supplier_invoice_no = ?, notes = ?,
                     payment_status = ?, payment_method = ?, payment_reference = ?, paid_amount = ?,
                     inward_status = ?, attachment_path = ?, attachment_paths = ?, fabric_description = ?,
                     total_amount = ?, tax_amount = ?, net_amount = ?,
                     purchase_billing_completed = CASE WHEN ? = 1 THEN 1 ELSE COALESCE(purchase_billing_completed, CASE WHEN source_module = 'purchase_billing' THEN 1 ELSE 0 END) END,
                     product_adding_completed = CASE WHEN ? = 1 THEN 1 ELSE COALESCE(product_adding_completed, CASE WHEN source_module = 'product_adding' THEN 1 ELSE 0 END) END
                 WHERE id = ?",
                [
                    $supplierId, $billDate, $supplierInvoiceNo, $notes,
                    $paymentStatus, $paymentMethod, $paymentReference, $paidAmount,
                    $inwardStatus, $attachmentPath, $attachmentPathsJson, $fabricDescription,
                    $totalAmount, $totalTax, $netAmount,
                    !$isProductAddingEdit ? 1 : 0,
                    $isProductAddingEdit ? 1 : 0,
                    $billId
                ]
            );

            // Re-insert items
            DB::query("DELETE FROM purchase_bill_items WHERE purchase_bill_id = ?", [$billId]);

            foreach ($itemsToInsert as $item) {
                DB::query(
                    "INSERT INTO purchase_bill_items (
                        concern_id, purchase_bill_id, variant_id, section_type, item_name, item_code,
                        hsn_code, specification, color, pattern, designs_count, rq_qty, unit,
                        gross_weight, net_weight, qty, inward_status, unit_price, gst_rate, gst_type, tax_mode, total_price, tax_amount,
                        photo_path, category_name, product_type, mrp, selling_price
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $cid,
                        $billId,
                        $item['variant_id'],
                        $item['section_type'],
                        $item['item_name'],
                        $item['item_code'],
                        $item['hsn_code'],
                        $item['specification'],
                        $item['color'],
                        $item['pattern'],
                        $item['designs_count'],
                        $item['rq_qty'],
                        $item['unit'],
                        $item['gross_weight'],
                        $item['net_weight'],
                        $item['qty'],
                        $inwardStatus,
                        $item['unit_price'],
                        $item['gst_rate'],
                        $item['gst_type'],
                        $item['tax_mode'],
                        $item['total_price'],
                        $item['tax_amount'],
                        $item['photo_path'],
                        $item['category_name'],
                        $item['product_type'],
                        $item['mrp'],
                        $item['selling_price'],
                    ]
                );

                if (!empty($item['hsn_code']) && $item['hsn_code'] !== '-') {
                    $existingHsn = DB::fetchValue("SELECT id FROM hsn_masters WHERE hsn_code = ? AND (concern_id = ? OR concern_id IS NULL)", [$item['hsn_code'], $cid]);
                    if (!$existingHsn) {
                        DB::query(                        "INSERT INTO hsn_masters (concern_id, code_type, hsn_code, description, gst_rate, created_at) VALUES (?, 'HSN', ?, ?, ?, NOW())", [
                            $cid,
                            $item['hsn_code'],
                            $item['item_name'] ?? 'Recorded HSN',
                            isset($item['gst_rate']) && $item['gst_rate'] !== '' ? $item['gst_rate'] : null
                        ]);
                    }
                }
            }

            DB::commit();

            // Synchronize and consolidate fabric roll specifications & accumulated stock
            RollRegistryService::syncSpecs($cid);

            $this->flash('success', "Purchase Bill '#{$bill['bill_no']}' updated successfully for ₹" . number_format($netAmount, 2) . " (" . count($itemsToInsert) . " items)!");
            $this->redirect($isProductAddingEdit ? '/m/product_adding/create?bill_id=' . $billId : '/m/purchase_billing');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Error updating purchase bill: ' . $e->getMessage());
            $this->redirect($editBillUrl);
        }
    }

    public function updateBillStatus(): void {
        $this->validateCsrf();
        $id = (int)($_POST['bill_id'] ?? 0);
        $cid = Auth::concernId();

        $bill = null;
        if ($cid) {
            $bill = DB::fetch("SELECT * FROM purchase_bills WHERE id = ? AND concern_id = ?", [$id, $cid]);
        } else {
            $bill = DB::fetch("SELECT * FROM purchase_bills WHERE id = ?", [$id]);
        }

        if (!$bill) {
            $this->flash('error', 'Purchase Bill not found or access denied.');
            $this->redirect('/m/purchase_billing');
            return;
        }

        $paymentStatus = trim($_POST['payment_status'] ?? 'unpaid');
        $inwardStatus  = $bill['inward_status'] ?? 'pending';
        $notes         = trim($_POST['notes'] ?? '');

        if (!in_array($paymentStatus, ['unpaid', 'partial', 'paid'], true)) {
            $paymentStatus = 'unpaid';
        }
        DB::query(
            "UPDATE purchase_bills SET payment_status = ?, inward_status = ?, notes = ? WHERE id = ?",
            [$paymentStatus, $inwardStatus, $notes, $id]
        );

        $this->flash('success', "Purchase Bill #{$bill['bill_no']} updated: Payment [{$paymentStatus}], Inward [{$inwardStatus}].");
        $this->redirect('/m/purchase_billing');
    }

    public function deleteBillFromProductAdding(): void
    {
        ModuleGuard::enforce('product_adding', 'delete');
        $this->validateCsrf();

        $billId = (int)($_POST['bill_id'] ?? 0);
        $concernId = Auth::concernId();
        if ($billId <= 0) {
            $this->flash('error', 'Invalid purchase bill ID.');
            $this->redirect('/m/product_adding');
            return;
        }

        try {
            PurchaseBillSourceService::ensureSchema();
            PurchaseStockItemOverrideService::initTable();
            PurchaseDamageService::initTable();
            DB::beginTransaction();

            $billSql = 'SELECT * FROM purchase_bills WHERE id = ?';
            $billParams = [$billId];
            if ($concernId) {
                $billSql .= ' AND concern_id = ?';
                $billParams[] = $concernId;
            }
            $billSql .= ' FOR UPDATE';
            $bill = DB::fetch($billSql, $billParams);
            if (!$bill) {
                throw new \RuntimeException('Purchase Bill not found or access denied.');
            }

            $sourceModule = (string)($bill['source_module'] ?? 'purchase_billing');
            if (!in_array($sourceModule, ['product_adding', 'purchase_billing'], true)) {
                throw new \RuntimeException('This bill cannot be deleted from Product Adding.');
            }

            $billConcernId = (int)$bill['concern_id'];
            $items = DB::fetchAll(
                'SELECT * FROM purchase_bill_items WHERE purchase_bill_id = ? AND concern_id = ? ORDER BY id ASC FOR UPDATE',
                [$billId, $billConcernId]
            );
            if ($sourceModule === 'purchase_billing') {
                if (!$items) {
                    throw new \RuntimeException('This Purchase Billing invoice has no line items and cannot be deleted from Product Adding.');
                }
                $stockedCount = (int)DB::fetchValue(
                    "SELECT COUNT(*)
                     FROM purchase_bill_items
                     WHERE purchase_bill_id = ?
                       AND COALESCE(NULLIF(inward_status, ''), ?) = 'completed'",
                    [$billId, $bill['inward_status'] ?? 'pending']
                );
                if ($stockedCount > 0 || ($bill['inward_status'] ?? 'pending') === 'completed') {
                    throw new \RuntimeException('This Purchase Billing invoice is no longer eligible for deletion from Product Adding.');
                }
            }

            $itemIds = array_map(static fn(array $item): int => (int)$item['id'], $items);
            $this->assertProductAddingBillHasNoDependents($itemIds);
            $this->reverseUnusedPurchaseBillStock($billConcernId, $billId, $items);

            AuditLog::logOrThrow(
                'DELETE',
                'purchase_bills',
                $billId,
                [
                    'bill_no' => $bill['bill_no'],
                    'source_module' => $sourceModule,
                    'supplier_id' => (int)$bill['supplier_id'],
                    'item_count' => count($items),
                    'net_amount' => (float)$bill['net_amount'],
                ],
                null
            );

            DB::query('DELETE FROM purchase_bill_items WHERE purchase_bill_id = ?', [$billId]);
            DB::query('DELETE FROM purchase_bills WHERE id = ? AND concern_id = ?', [$billId, $billConcernId]);
            DB::commit();

            try {
                RollRegistryService::syncSpecs($billConcernId);
            } catch (\Throwable $syncError) {
                error_log('Purchase bill deleted but roll registry synchronization failed: ' . $syncError->getMessage());
                $this->flash('error', "Purchase Bill #{$bill['bill_no']} was deleted, but the fabric roll summary could not be refreshed: " . $syncError->getMessage());
                $this->redirect('/m/product_adding');
                return;
            }

            $this->flash('success', "Purchase Bill #{$bill['bill_no']} deleted. Any unused finished-goods stock was reversed.");
        } catch (\Throwable $e) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            $this->flash('error', 'Could not delete purchase bill: ' . $e->getMessage());
        }

        $this->redirect('/m/product_adding');
    }

    private function assertProductAddingBillHasNoDependents(array $itemIds): void
    {
        if (!$itemIds) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        if (DB::fetch(
            "SELECT id FROM program_rolls
             WHERE purchase_bill_item_id IN ({$placeholders})
             LIMIT 1 FOR UPDATE",
            $itemIds
        )) {
            throw new \RuntimeException('This bill has rolls linked to production. Remove or cancel those production allocations before deleting it.');
        }

        if (DB::fetch(
            "SELECT id FROM purchase_damage_quarantine
             WHERE purchase_bill_item_id IN ({$placeholders})
             LIMIT 1 FOR UPDATE",
            $itemIds
        )) {
            throw new \RuntimeException('This bill has linked damage records. Resolve those records before deleting it.');
        }
    }

    private function reverseUnusedPurchaseBillStock(int $concernId, int $billId, array $items): void
    {
        $itemIds = array_map(static fn(array $item): int => (int)$item['id'], $items);
        $sourceItemRefs = '';
        $sourceParams = [];
        if ($itemIds) {
            $itemPlaceholders = implode(',', array_fill(0, count($itemIds), '?'));
            $sourceItemRefs = " OR (ref_module IN ('Purchase Inward Item', 'Product Adding Stock Correction') AND ref_id IN ({$itemPlaceholders}))";
            $sourceParams = $itemIds;
        }

        $sourceRows = DB::fetchAll(
            "SELECT id, variant_id, location, qty_in, qty_out
             FROM stock_ledger
             WHERE concern_id = ?
               AND ( (ref_module = 'Purchase Inward' AND ref_id = ?) {$sourceItemRefs} )
             ORDER BY id ASC
             FOR UPDATE",
            array_merge([$concernId, $billId], $sourceParams)
        );

        $movements = [];
        foreach ($sourceRows as $row) {
            $variantId = (int)$row['variant_id'];
            $location = (string)$row['location'];
            $key = $variantId . "\0" . $location;
            if (!isset($movements[$key])) {
                $movements[$key] = [
                    'variant_id' => $variantId,
                    'location' => $location,
                    'qty' => 0.0,
                    'first_ledger_id' => (int)$row['id'],
                ];
            }
            $movements[$key]['qty'] += (float)$row['qty_in'] - (float)$row['qty_out'];
            $movements[$key]['first_ledger_id'] = min($movements[$key]['first_ledger_id'], (int)$row['id']);
        }

        ksort($movements);
        foreach ($movements as $movement) {
            $qty = round((float)$movement['qty'], 2);
            if ($qty < -0.0001) {
                throw new \RuntimeException('The bill stock ledger has an invalid negative balance; deletion was stopped without changing stock.');
            }
            if ($qty <= 0.0001) {
                continue;
            }

            $variantId = (int)$movement['variant_id'];
            $location = (string)$movement['location'];
            $stock = DB::fetch(
                'SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = ? FOR UPDATE',
                [$concernId, $variantId, $location]
            );
            if (!$stock || (float)$stock['qty'] + 0.0001 < $qty) {
                throw new \RuntimeException('Some bill stock is no longer available at its original location, so it cannot be safely reversed.');
            }

            $outflowSql = "SELECT id FROM stock_ledger
                           WHERE concern_id = ? AND variant_id = ? AND location = ?
                             AND id > ? AND qty_out > 0
                             AND NOT (
                                 (ref_module = 'Purchase Inward' AND ref_id = ?)
                                 {$sourceItemRefs}
                             )
                           ORDER BY id ASC LIMIT 1 FOR UPDATE";
            $outflowParams = [$concernId, $variantId, $location, (int)$movement['first_ledger_id'], $billId];
            if ($itemIds) {
                $outflowParams = array_merge($outflowParams, $itemIds);
            }
            if (DB::fetch($outflowSql, $outflowParams)) {
                throw new \RuntimeException('Finished-goods stock from this bill has been used. The entire bill must remain unchanged.');
            }

            StockService::move(
                $concernId,
                $variantId,
                $location,
                0.0,
                $qty,
                'Purchase Bill Reversal',
                $billId,
                "Unused stock reversed while deleting Purchase Bill #{$billId}",
                Auth::id()
            );
        }
    }

    public function deleteBill(): void {
        $this->validateCsrf();
        $id = (int)($_POST['bill_id'] ?? 0);
        $cid = Auth::concernId();

        $bill = null;
        if ($cid) {
            $bill = DB::fetch("SELECT * FROM purchase_bills WHERE id = ? AND concern_id = ?", [$id, $cid]);
        } else {
            $bill = DB::fetch("SELECT * FROM purchase_bills WHERE id = ?", [$id]);
        }

        if (!$bill) {
            $this->flash('error', 'Purchase Bill not found or access denied.');
            $this->redirect('/m/purchase_billing');
            return;
        }

        PurchaseBillSourceService::ensureSchema();
        DB::beginTransaction();
        try {
            DB::query("DELETE FROM purchase_bill_items WHERE purchase_bill_id = ?", [$id]);
            DB::query("DELETE FROM purchase_bills WHERE id = ?", [$id]);
            DB::commit();

            // Synchronize and consolidate fabric roll specifications & accumulated stock
            RollRegistryService::syncSpecs($cid);

            $this->flash('success', "Purchase Bill #{$bill['bill_no']} deleted successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to delete purchase bill: ' . $e->getMessage());
        }

        $this->redirect('/m/purchase_billing');
    }

    // 3. PURCHASE DAMAGE
    public static function fetchPurchaseDamageData(?int $cid): array {
        PurchaseDamageService::initTable();
        $sql = "SELECT history_source, record_id, damage_no, concern_id, concern_name, supplier_name,
                       item_name, item_code, item_type, tag_code, qty, location, reason, notes,
                       damaged_weight, estimated_loss, action_taken, created_at
                FROM (
                    SELECT 'legacy' AS history_source, pd.id AS record_id,
                           pd.damage_no COLLATE utf8mb4_unicode_ci AS damage_no,
                           pd.concern_id,
                           con.name COLLATE utf8mb4_unicode_ci AS concern_name,
                           s.name COLLATE utf8mb4_unicode_ci AS supplier_name,
                           v.name COLLATE utf8mb4_unicode_ci AS item_name,
                           v.product_code COLLATE utf8mb4_unicode_ci AS item_code,
                           'legacy' COLLATE utf8mb4_unicode_ci AS item_type,
                           CAST(NULL AS CHAR(50)) COLLATE utf8mb4_unicode_ci AS tag_code,
                           pd.qty,
                           pd.location COLLATE utf8mb4_unicode_ci AS location,
                           pd.reason COLLATE utf8mb4_unicode_ci AS reason,
                           CAST(NULL AS CHAR(255)) COLLATE utf8mb4_unicode_ci AS notes,
                           0 AS damaged_weight, 0 AS estimated_loss,
                           pd.action_taken COLLATE utf8mb4_unicode_ci AS action_taken,
                           pd.created_at
                    FROM purchase_damages pd
                    JOIN suppliers s ON pd.supplier_id = s.id
                    JOIN variants v ON pd.variant_id = v.id
                    LEFT JOIN concerns con ON con.id = pd.concern_id
                    UNION ALL
                    SELECT 'quarantine' AS history_source, q.id AS record_id,
                           q.damage_no COLLATE utf8mb4_unicode_ci AS damage_no,
                           q.concern_id,
                           con.name COLLATE utf8mb4_unicode_ci AS concern_name,
                           q.supplier_name COLLATE utf8mb4_unicode_ci AS supplier_name,
                           q.item_name COLLATE utf8mb4_unicode_ci AS item_name,
                           q.item_code COLLATE utf8mb4_unicode_ci AS item_code,
                           q.item_type COLLATE utf8mb4_unicode_ci AS item_type,
                           q.tag_code COLLATE utf8mb4_unicode_ci AS tag_code,
                           q.qty,
                           q.location COLLATE utf8mb4_unicode_ci AS location,
                           q.reason COLLATE utf8mb4_unicode_ci AS reason,
                           q.notes COLLATE utf8mb4_unicode_ci AS notes,
                           q.damaged_weight, q.estimated_loss,
                           q.status COLLATE utf8mb4_unicode_ci AS action_taken,
                           q.created_at
                    FROM purchase_damage_quarantine q
                    LEFT JOIN concerns con ON con.id = q.concern_id
                ) damage_history";
        $params = [];
        if ($cid) {
            $sql .= " WHERE damage_history.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY damage_history.created_at DESC LIMIT 200";
        $damages = DB::fetchAll($sql, $params);
        $damageReasons = $cid ? PurchaseDamageService::getDamageReasons((int)$cid) : [];
        return [
            'damages' => $damages,
            'damageReasons' => $damageReasons,
        ];
    }

    public function damage(): void {
        $isSuperAdmin = Auth::isSuperAdmin();
        $cid = Auth::concernId();
        if ($isSuperAdmin) {
            $cid = (int)($_GET['concern_id'] ?? 0) ?: null;
        }
        $this->redirect('/m/purchase_stocks?tab=damage' . ($isSuperAdmin && $cid ? '&concern_id=' . $cid : ''));
    }

    public function exportDamage(): void {
        ModuleGuard::enforce('purchase_stocks', 'view');
        PurchaseDamageService::initTable();

        $isSuperAdmin = Auth::isSuperAdmin();
        $cid = $isSuperAdmin
            ? (int)($_GET['concern_id'] ?? 0)
            : Auth::concernId();
        $format = is_scalar($_GET['format'] ?? null)
            ? strtolower(trim((string)$_GET['format']))
            : 'excel';
        $period = is_scalar($_GET['period'] ?? null) ? (string)$_GET['period'] : 'all';
        $singleDate = is_scalar($_GET['single_date'] ?? null) ? (string)$_GET['single_date'] : null;
        $fromDate = is_scalar($_GET['from_date'] ?? null) ? (string)$_GET['from_date'] : null;
        $toDate = is_scalar($_GET['to_date'] ?? null) ? (string)$_GET['to_date'] : null;
        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT history_source, record_id, damage_no, concern_id, concern_name, supplier_name,
                       item_name, item_code, item_type, tag_code, qty, location, reason, notes,
                       damaged_weight, estimated_loss, action_taken, created_at
                FROM (
                    SELECT 'legacy' AS history_source, pd.id AS record_id,
                           pd.damage_no COLLATE utf8mb4_unicode_ci AS damage_no,
                           pd.concern_id,
                           con.name COLLATE utf8mb4_unicode_ci AS concern_name,
                           s.name COLLATE utf8mb4_unicode_ci AS supplier_name,
                           v.name COLLATE utf8mb4_unicode_ci AS item_name,
                           v.product_code COLLATE utf8mb4_unicode_ci AS item_code,
                           'legacy' COLLATE utf8mb4_unicode_ci AS item_type,
                           CAST(NULL AS CHAR(50)) COLLATE utf8mb4_unicode_ci AS tag_code,
                           pd.qty,
                           pd.location COLLATE utf8mb4_unicode_ci AS location,
                           pd.reason COLLATE utf8mb4_unicode_ci AS reason,
                           CAST(NULL AS CHAR(255)) COLLATE utf8mb4_unicode_ci AS notes,
                           0 AS damaged_weight, 0 AS estimated_loss,
                           pd.action_taken COLLATE utf8mb4_unicode_ci AS action_taken,
                           pd.created_at
                    FROM purchase_damages pd
                    JOIN suppliers s ON pd.supplier_id = s.id
                    JOIN variants v ON pd.variant_id = v.id
                    LEFT JOIN concerns con ON con.id = pd.concern_id
                    UNION ALL
                    SELECT 'quarantine' AS history_source, q.id AS record_id,
                           q.damage_no COLLATE utf8mb4_unicode_ci AS damage_no,
                           q.concern_id,
                           con.name COLLATE utf8mb4_unicode_ci AS concern_name,
                           q.supplier_name COLLATE utf8mb4_unicode_ci AS supplier_name,
                           q.item_name COLLATE utf8mb4_unicode_ci AS item_name,
                           q.item_code COLLATE utf8mb4_unicode_ci AS item_code,
                           q.item_type COLLATE utf8mb4_unicode_ci AS item_type,
                           q.tag_code COLLATE utf8mb4_unicode_ci AS tag_code,
                           q.qty,
                           q.location COLLATE utf8mb4_unicode_ci AS location,
                           q.reason COLLATE utf8mb4_unicode_ci AS reason,
                           q.notes COLLATE utf8mb4_unicode_ci AS notes,
                           q.damaged_weight, q.estimated_loss,
                           q.status COLLATE utf8mb4_unicode_ci AS action_taken,
                           q.created_at
                    FROM purchase_damage_quarantine q
                    LEFT JOIN concerns con ON con.id = q.concern_id
                ) damage_history";
        $where = [];
        $params = [];
        if ($cid) {
            $where[] = 'damage_history.concern_id = ?';
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = 'DATE(damage_history.created_at) BETWEEN ? AND ?';
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY damage_history.created_at DESC';
        $damages = DB::fetchAll($sql, $params);

        $headers = [
            'Damage No.', 'Date', 'Concern', 'Supplier', 'Fabric / Product', 'Item Code',
            'Item Type', 'Quantity', 'Damaged Weight', 'Estimated Loss', 'Location',
            'Reason', 'Notes', 'Status', 'Record Source',
        ];
        $rows = [];
        $totalQty = 0.0;
        $totalWeight = 0.0;
        $totalLoss = 0.0;
        foreach ($damages as $damage) {
            $totalQty += (float)$damage['qty'];
            $totalWeight += (float)$damage['damaged_weight'];
            $totalLoss += (float)$damage['estimated_loss'];
            $rows[] = [
                $damage['damage_no'],
                date('d M Y, h:i A', strtotime($damage['created_at'])),
                $damage['concern_name'] ?: '—',
                $damage['supplier_name'] ?: '—',
                $damage['item_name'],
                $damage['tag_code'] ?: ($damage['item_code'] ?: '—'),
                $damage['item_type'],
                (float)$damage['qty'],
                (float)$damage['damaged_weight'],
                '₹' . number_format((float)$damage['estimated_loss'], 2),
                $damage['location'] ?: 'Main Store',
                $damage['reason'] ?: 'Unspecified defect',
                $damage['notes'] ?: '',
                $damage['action_taken'] ?: 'Quarantined',
                $damage['history_source'],
            ];
        }

        $concernName = $cid
            ? (string)(DB::fetchValue('SELECT name FROM concerns WHERE id = ?', [$cid]) ?: 'Selected Concern')
            : 'All Concerns';
        $metadata = [
            'Concern / Outlet' => $concernName,
            'Period Scope' => $dateScope['label'],
            'Total Records' => (string)count($damages),
            'Total Quantity' => number_format($totalQty, 2),
            'Damaged Weight' => number_format($totalWeight, 2),
            'Estimated Loss' => '₹' . number_format($totalLoss, 2),
        ];
        $totals = [
            'Totals: ' . count($damages) . ' records', '', '', '', '', '', '',
            $totalQty, $totalWeight, '₹' . number_format($totalLoss, 2), '', '', '', '', '',
        ];
        $filename = 'purchase_damage_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Purchase Damage & Quarantine Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Purchase Damage & Quarantine Report', $metadata, $headers, $rows, $totals);
        }
    }

    public function scanPurchaseDamageTag(): void {
        $this->validateCsrf();
        $cid = Auth::isSuperAdmin()
            ? (int)($_POST['concern_id'] ?? 0)
            : Auth::concernId();
        if (!$cid) {
            $this->renderJson(['success' => false, 'message' => 'Select a concern before scanning labels.'], 422);
            return;
        }

        try {
            $item = PurchaseDamageService::resolveTag($cid, (string)($_POST['tag_code'] ?? ''));
            $this->renderJson(['success' => true, 'item' => $item]);
        } catch (\RuntimeException $e) {
            $this->renderJson(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function addDamageReason(): void {
        ModuleGuard::enforce('purchase_stocks', 'add');
        $this->validateCsrf();

        $cid = Auth::isSuperAdmin()
            ? (int)($_POST['concern_id'] ?? 0)
            : Auth::concernId();
        $reason = $_POST['reason'] ?? null;
        if (!$cid || !is_scalar($reason)) {
            $this->renderJson(['success' => false, 'message' => 'Enter a valid damage reason.'], 422);
            return;
        }

        try {
            $savedReason = PurchaseDamageService::addDamageReason($cid, (string)$reason, Auth::id());
            $this->renderJson([
                'success' => true,
                'reason' => $savedReason,
                'message' => 'Damage reason is available in the dropdown.',
            ]);
        } catch (\RuntimeException $e) {
            $this->renderJson(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function storeDamage(): void {
        $this->validateCsrf();
        $cid = Auth::isSuperAdmin()
            ? (int)($_POST['concern_id'] ?? 0)
            : Auth::concernId();

        if (!$cid) {
            $this->flash('error', 'Please choose a concern for this purchase damage.');
            $this->redirect('/m/purchase_stocks?tab=damage');
        }

        $rawScannedItems = $_POST['scanned_items'] ?? '';
        $scannedItems = is_string($rawScannedItems) ? json_decode($rawScannedItems, true) : null;
        if (!is_array($scannedItems)) {
            $scannedItems = [];
        }

        try {
            $result = PurchaseDamageService::saveScannedDamage(
                $cid,
                $scannedItems,
                trim((string)($_POST['reason'] ?? '')),
                trim((string)($_POST['notes'] ?? '')),
                Auth::id()
            );

            $quarantinedQty = number_format((float)$result['count'], 2);
            $this->flash(
                'success',
                "Purchase Damage '{$result['damage_no']}' quarantined {$quarantinedQty} unit(s); stock was updated."
            );
        } catch (\Throwable $e) {
            $this->flash('error', 'Failed to record damage: ' . $e->getMessage());
        }

        $this->redirect('/m/purchase_stocks?tab=damage' . (Auth::isSuperAdmin() && $cid ? '&concern_id=' . $cid : ''));
    }

    public function updateDamageRecord(): void {
        ModuleGuard::enforce('purchase_stocks', 'edit');
        $this->validateCsrf();

        $damageId = (int)($_POST['damage_id'] ?? 0);
        $concernId = Auth::isSuperAdmin()
            ? (int)($_POST['concern_id'] ?? 0)
            : Auth::concernId();
        $returnUrl = '/m/purchase_stocks?tab=damage' . (Auth::isSuperAdmin() && $concernId ? '&concern_id=' . $concernId : '');

        $rawQty = $_POST['qty'] ?? null;
        $reason = $_POST['reason'] ?? null;
        $notes = $_POST['notes'] ?? '';
        if (
            !$damageId || !$concernId ||
            !is_scalar($rawQty) || !is_numeric($rawQty) ||
            !is_scalar($reason) || !is_scalar($notes)
        ) {
            $this->flash('error', 'Enter valid quarantine record details.');
            $this->redirect($returnUrl);
            return;
        }
        $qty = (float)$rawQty;
        $reason = trim((string)$reason);
        $notes = trim((string)$notes);
        if (!is_finite($qty) || $qty <= 0 || $qty > 500 || $reason === '' || strlen($reason) > 255 || strlen($notes) > 2000) {
            $this->flash('error', 'Enter a valid quantity, reason, and notes within the allowed limits.');
            $this->redirect($returnUrl);
            return;
        }

        try {
            $result = PurchaseDamageService::updateQuarantineRecord(
                $concernId,
                $damageId,
                $qty,
                $reason,
                $notes,
                Auth::id()
            );
            AuditLog::log(
                'UPDATE',
                'purchase_damage_quarantine',
                $damageId,
                $result['before'],
                $result['after']
            );
            $this->flash('success', 'Quarantine record updated and stock balances reconciled.');
        } catch (\Throwable $e) {
            $this->flash('error', 'Failed to update quarantine record: ' . $e->getMessage());
        }

        $this->redirect($returnUrl);
    }

    public function deleteDamageRecord(): void {
        ModuleGuard::enforce('purchase_stocks', 'delete');
        $this->validateCsrf();

        $damageId = (int)($_POST['damage_id'] ?? 0);
        $concernId = Auth::isSuperAdmin()
            ? (int)($_POST['concern_id'] ?? 0)
            : Auth::concernId();
        $returnUrl = '/m/purchase_stocks?tab=damage' . (Auth::isSuperAdmin() && $concernId ? '&concern_id=' . $concernId : '');

        if (!$damageId || !$concernId) {
            $this->flash('error', 'Select a valid quarantine record.');
            $this->redirect($returnUrl);
            return;
        }

        try {
            $result = PurchaseDamageService::deleteQuarantineRecord($concernId, $damageId, Auth::id());
            AuditLog::log(
                'DELETE',
                'purchase_damage_quarantine',
                $damageId,
                $result['before'],
                null
            );
            $this->flash('success', 'Quarantine record deleted and stock balances restored.');
        } catch (\Throwable $e) {
            $this->flash('error', 'Failed to delete quarantine record: ' . $e->getMessage());
        }

        $this->redirect($returnUrl);
    }
}
