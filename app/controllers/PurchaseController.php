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
        if (!CloudinaryService::isConfigured()) {
            throw new \RuntimeException('Cloudinary is not configured. Configure Cloudinary before uploading bill attachments.');
        }

        foreach ($filesToUpload as $file) {
            $uploaded = CloudinaryService::uploadFile($file['tmp_name'], $file['name'], 'invento/bills');
            if (empty($uploaded['secure_url'])) {
                throw new \RuntimeException('An attachment could not be stored in Cloudinary. The purchase bill was not saved.');
            }
            $existingPaths[] = $uploaded['secure_url'];
        }

        return array_values(array_unique($existingPaths));
    }

    // 1. SUPPLIERS
    public function suppliers(): void {
        ModuleGuard::enforce('suppliers', 'view');
        $cid = Auth::concernId();

        $sql = "SELECT s.*, con.name AS concern_name, con.code AS concern_code
                FROM suppliers s
                LEFT JOIN concerns con ON s.concern_id = con.id";
        $params = [];
        if ($cid) {
            $sql .= " WHERE s.concern_id = ?";
            $params[] = $cid;
        }
        $sql .= " ORDER BY s.id DESC";
        $suppliers = DB::fetchAll($sql, $params);

        $allConcerns = [];
        if (Auth::isSuperAdmin()) {
            $allConcerns = DB::fetchAll("SELECT id, name, code FROM concerns ORDER BY id ASC");
        }

        $this->render('concern/suppliers', [
            'pageTitle'     => 'Supplier Master',
            'currentModule' => 'suppliers',
            'suppliers'     => $suppliers,
            'selectedCid'   => $cid,
            'allConcerns'   => $allConcerns,
        ]);
    }

    public function exportSuppliers(): void {
        ModuleGuard::enforce('suppliers', 'view');

        $isSuperAdmin = Auth::isSuperAdmin();
        $cid = $isSuperAdmin
            ? (int)($_GET['concern_id'] ?? 0)
            : Auth::concernId();
        if (!$cid && !$isSuperAdmin) {
            $cid = Auth::concernId() ?: 1;
        }

        $format = is_scalar($_GET['format'] ?? null)
            ? strtolower(trim((string)$_GET['format']))
            : 'excel';
        $period = is_scalar($_GET['period'] ?? null) ? (string)$_GET['period'] : 'all';
        $singleDate = is_scalar($_GET['single_date'] ?? null) ? (string)$_GET['single_date'] : null;
        $fromDate = is_scalar($_GET['from_date'] ?? null) ? (string)$_GET['from_date'] : null;
        $toDate = is_scalar($_GET['to_date'] ?? null) ? (string)$_GET['to_date'] : null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT s.*, con.name AS concern_name, con.code AS concern_code
                FROM suppliers s
                LEFT JOIN concerns con ON s.concern_id = con.id";
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "s.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(s.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY s.id DESC";
        $suppliers = DB::fetchAll($sql, $params);

        $headers = [
            '#', 'Supplier Code', 'Supplier Name', 'Company / Mill Name',
            'Phone', 'Email', 'GSTIN', 'Address', 'City / District',
            'State', 'Status', 'Registered Date'
        ];

        $rows = [];
        $idx = 1;
        foreach ($suppliers as $s) {
            $code = $s['code'] ?: 'SUP-' . str_pad((string)$s['id'], 3, '0', STR_PAD_LEFT);
            $rows[] = [
                $idx++,
                $code,
                $s['name'],
                $s['company_name'] ?: '—',
                $s['phone'] ?: '—',
                $s['email'] ?: '—',
                $s['gstin'] ?: 'Unregistered',
                $s['address'] ?: '—',
                trim(($s['city'] ?? '') . ' ' . ($s['district'] ?? '')) ?: '—',
                $s['state'] ?: 'Tamil Nadu',
                strtoupper($s['status'] ?? 'active'),
                !empty($s['created_at']) ? date('d M Y, h:i A', strtotime($s['created_at'])) : '—',
            ];
        }

        $concernName = 'GRM Enterprise';
        if ($cid) {
            $con = DB::fetch("SELECT name FROM concerns WHERE id = ?", [$cid]);
            if ($con && !empty($con['name'])) {
                $concernName = $con['name'];
            }
        }

        $metadata = [
            'Generated On' => date('d M Y, h:i A'),
            'Concern'      => $concernName,
            'Period'       => $dateScope['label'] ?? 'All Time',
            'Total Vendors'=> count($rows),
        ];

        $filename = 'Suppliers_Directory_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Supplier Directory Master Report', $metadata, $headers, $rows, []);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, []);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Supplier Directory Master Report', $metadata, $headers, $rows, []);
        }
    }

    public function storeSupplier(): void {
        ModuleGuard::enforce('suppliers', 'create');
        $this->validateCsrf();
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);

        $name     = trim($_POST['name'] ?? '');
        $company  = trim($_POST['company_name'] ?? '');
        $phone    = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
        $email    = trim($_POST['email'] ?? '');
        $gstin    = strtoupper(trim($_POST['gstin'] ?? ''));
        $address  = trim($_POST['address'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $state    = trim($_POST['state'] ?? 'Tamil Nadu');

        // Full Validation Rules
        $errors = [];

        if (!$cid || !DB::fetchValue("SELECT id FROM concerns WHERE id = ?", [$cid])) {
            $errors[] = 'Target Concern: Please select a valid business concern.';
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            $errors[] = 'Contact Person / Name: Must be between 2 and 150 characters.';
        }

        if (mb_strlen($company) < 2 || mb_strlen($company) > 150) {
            $errors[] = 'Company / Mill Name: Must be between 2 and 150 characters.';
        }

        if (empty($phone) || !preg_match('/^[6-9]\d{9}$/', $phone)) {
            $errors[] = 'Phone: Enter a valid 10-digit Indian mobile number (starting with 6, 7, 8, or 9).';
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email: Enter a valid email address (e.g. vendor@mill.com).';
        }

        if (!empty($gstin) && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gstin)) {
            $errors[] = 'GSTIN: Must follow standard 15-character GST format (e.g. 33AAAAA0000A1Z5).';
        }

        if (mb_strlen($address) < 3) {
            $errors[] = 'Address: Street / Area details are required (at least 3 characters).';
        }

        if (mb_strlen($district) < 2) {
            $errors[] = 'District: District is required (e.g. Karur, Tirupur, Coimbatore).';
        }

        if (mb_strlen($state) < 2) {
            $errors[] = 'State: State is required (e.g. Tamil Nadu).';
        }

        // Check for duplicates within this concern
        if ($cid) {
            if (!empty($phone) && DB::fetchValue("SELECT id FROM suppliers WHERE concern_id = ? AND phone = ? LIMIT 1", [$cid, $phone])) {
                $errors[] = "A supplier with this phone number ({$phone}) is already registered in this concern.";
            }
            if (!empty($gstin) && DB::fetchValue("SELECT id FROM suppliers WHERE concern_id = ? AND gstin = ? LIMIT 1", [$cid, $gstin])) {
                $errors[] = "A supplier with GSTIN ({$gstin}) is already registered in this concern.";
            }
        }

        if (!empty($errors)) {
            $this->flash('error', implode("\n", $errors));
            $this->redirect('/m/suppliers');
            return;
        }

        $code = 'SUP-' . str_pad((string)(DB::fetchValue("SELECT COUNT(*) FROM suppliers WHERE concern_id = ?", [$cid]) + 1), 3, '0', STR_PAD_LEFT);
        $emailValue = !empty($email) ? $email : null;

        DB::query(
            "INSERT INTO suppliers (concern_id, code, name, company_name, phone, email, gstin, address, city, district, state, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')",
            [$cid, $code, $name, $company, $phone, $emailValue, $gstin, $address, $district, $district, $state]
        );

        $this->flash('success', "Supplier '{$name}' ({$company}) registered successfully with code {$code}!");
        $this->redirect('/m/suppliers');
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

    public function updateSupplier(): void {
        ModuleGuard::enforce('suppliers', 'edit');
        $this->validateCsrf();
        $id = (int)($_POST['supplier_id'] ?? 0);
        $cid = Auth::concernId();

        $supplier = null;
        if ($cid) {
            $supplier = DB::fetch("SELECT * FROM suppliers WHERE id = ? AND concern_id = ?", [$id, $cid]);
        } else {
            $supplier = DB::fetch("SELECT * FROM suppliers WHERE id = ?", [$id]);
            $cid = (int)($supplier['concern_id'] ?? ($_POST['concern_id'] ?? 0));
        }

        if (!$supplier) {
            $this->flash('error', 'Supplier record not found or access denied.');
            $this->redirect('/m/suppliers');
            return;
        }

        $name     = trim($_POST['name'] ?? '');
        $company  = trim($_POST['company_name'] ?? '');
        $phone    = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
        $email    = trim($_POST['email'] ?? '');
        $gstin    = strtoupper(trim($_POST['gstin'] ?? ''));
        $address  = trim($_POST['address'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $state    = trim($_POST['state'] ?? 'Tamil Nadu');
        $status   = trim($_POST['status'] ?? 'active');
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            $errors[] = 'Contact Person / Name: Must be between 2 and 150 characters.';
        }
        if (mb_strlen($company) < 2 || mb_strlen($company) > 150) {
            $errors[] = 'Company / Mill Name: Must be between 2 and 150 characters.';
        }
        if (empty($phone) || !preg_match('/^[6-9]\d{9}$/', $phone)) {
            $errors[] = 'Phone: Enter a valid 10-digit Indian mobile number (starting with 6, 7, 8, or 9).';
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email: Enter a valid email address.';
        }
        if (!empty($gstin) && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gstin)) {
            $errors[] = 'GSTIN: Must follow standard 15-character GST format.';
        }
        if (mb_strlen($address) < 3) {
            $errors[] = 'Address: Street / Area details are required.';
        }
        if (mb_strlen($district) < 2) {
            $errors[] = 'District: District is required.';
        }
        if (mb_strlen($state) < 2) {
            $errors[] = 'State: State is required.';
        }

        if ($cid) {
            if (!empty($phone) && DB::fetchValue("SELECT id FROM suppliers WHERE concern_id = ? AND phone = ? AND id != ? LIMIT 1", [$cid, $phone, $id])) {
                $errors[] = "A supplier with phone number ({$phone}) is already registered in this concern.";
            }
            if (!empty($gstin) && DB::fetchValue("SELECT id FROM suppliers WHERE concern_id = ? AND gstin = ? AND id != ? LIMIT 1", [$cid, $gstin, $id])) {
                $errors[] = "A supplier with GSTIN ({$gstin}) is already registered in this concern.";
            }
        }

        if (!empty($errors)) {
            $this->flash('error', implode("\n", $errors));
            $this->redirect('/m/suppliers');
            return;
        }

        $emailValue = !empty($email) ? $email : null;
        DB::query(
            "UPDATE suppliers SET name = ?, company_name = ?, phone = ?, email = ?, gstin = ?, address = ?, city = ?, district = ?, state = ?, status = ? WHERE id = ?",
            [$name, $company, $phone, $emailValue, $gstin, $address, $district, $district, $state, $status, $id]
        );

        $this->flash('success', "Supplier '{$name}' ({$company}) updated successfully!");
        $this->redirect('/m/suppliers');
    }

    public function deleteSupplier(): void {
        ModuleGuard::enforce('suppliers', 'delete');
        $this->validateCsrf();
        $id = (int)($_POST['supplier_id'] ?? 0);
        $cid = Auth::concernId();

        $supplier = null;
        if ($cid) {
            $supplier = DB::fetch("SELECT * FROM suppliers WHERE id = ? AND concern_id = ?", [$id, $cid]);
        } else {
            $supplier = DB::fetch("SELECT * FROM suppliers WHERE id = ?", [$id]);
        }

        if (!$supplier) {
            $this->flash('error', 'Supplier record not found or access denied.');
            $this->redirect('/m/suppliers');
            return;
        }

        $hasBills = (int)DB::fetchValue("SELECT COUNT(*) FROM purchase_bills WHERE supplier_id = ?", [$id]);
        if ($hasBills > 0) {
            DB::query("UPDATE suppliers SET status = 'inactive' WHERE id = ?", [$id]);
            $this->flash('success', "Supplier '{$supplier['name']}' has {$hasBills} recorded purchase bills. Status updated to 'Inactive' to preserve financial audit history.");
        } else {
            DB::query("DELETE FROM suppliers WHERE id = ?", [$id]);
            $this->flash('success', "Supplier '{$supplier['name']}' deleted successfully.");
        }

        $this->redirect('/m/suppliers');
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
        $cid = Auth::concernId();

        $sql = "SELECT pb.*, s.name AS supplier_name, con.name AS concern_name, con.code AS concern_code,
                       (SELECT COUNT(*) FROM purchase_bill_items pbi WHERE pbi.purchase_bill_id = pb.id) AS items_count,
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
        $bills = DB::fetchAll($sql, $params);

        if ($cid) {
            $suppliers = DB::fetchAll("SELECT id, name, company_name FROM suppliers WHERE concern_id = ? AND status = 'active'", [$cid]);
            $variants  = DB::fetchAll("SELECT id, sku_code, name, price, size FROM variants WHERE concern_id = ? AND status = 'active'", [$cid]);
        } else {
            $suppliers = DB::fetchAll("SELECT id, name, company_name FROM suppliers WHERE status = 'active'");
            $variants  = DB::fetchAll("SELECT id, sku_code, name, price, size FROM variants WHERE status = 'active'");
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
            $suppliers = DB::fetchAll("SELECT id, code, name, company_name, district, state FROM suppliers WHERE concern_id = ? AND status = 'active' ORDER BY name ASC", [$cid]);
            $variants  = DB::fetchAll("SELECT id, sku_code, name, price, size FROM variants WHERE concern_id = ? AND status = 'active' ORDER BY name ASC", [$cid]);
            $hsnList   = DB::fetchAll("SELECT code_type, hsn_code, description, category, gst_rate, rate_note FROM hsn_masters WHERE is_active = 1 AND code_type = 'HSN' AND (concern_id = ? OR concern_id IS NULL) ORDER BY hsn_code ASC", [$cid]);
        } else {
            $suppliers = DB::fetchAll("SELECT id, concern_id, code, name, company_name, district, state FROM suppliers WHERE status = 'active' ORDER BY name ASC");
            $variants  = DB::fetchAll("SELECT id, concern_id, sku_code, name, price, size FROM variants WHERE status = 'active' ORDER BY name ASC");
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
        ]);
    }

    public function editBill(): void {
        $this->ensureHsnMasterSchema();
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
        if (!$bill) {
            $this->flash('error', 'Purchase Bill not found or access denied.');
            $this->redirect('/m/purchase_billing');
            return;
        }

        $items = DB::fetchAll("SELECT * FROM purchase_bill_items WHERE purchase_bill_id = ? ORDER BY id ASC", [$id]);

        if ($cid) {
            $suppliers = DB::fetchAll("SELECT id, code, name, company_name, district, state FROM suppliers WHERE concern_id = ? AND status = 'active' ORDER BY name ASC", [$cid]);
            $variants  = DB::fetchAll("SELECT id, sku_code, name, price, size FROM variants WHERE concern_id = ? AND status = 'active' ORDER BY name ASC", [$cid]);
            $hsnList   = DB::fetchAll("SELECT code_type, hsn_code, description, category, gst_rate, rate_note FROM hsn_masters WHERE is_active = 1 AND code_type = 'HSN' AND (concern_id = ? OR concern_id IS NULL) ORDER BY hsn_code ASC", [$cid]);
        } else {
            $suppliers = DB::fetchAll("SELECT id, concern_id, code, name, company_name, district, state FROM suppliers WHERE status = 'active' ORDER BY name ASC");
            $variants  = DB::fetchAll("SELECT id, concern_id, sku_code, name, price, size FROM variants WHERE status = 'active' ORDER BY name ASC");
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

        $this->render('concern/purchase_billing_create', [
            'pageTitle'      => 'Edit Purchase Bill: #' . $bill['bill_no'],
            'currentModule'  => 'purchase_billing',
            'suppliers'      => $suppliers,
            'variants'       => $variants,
            'selectedCid'    => $cid,
            'allConcerns'    => $allConcerns,
            'hsnList'        => $hsnList,
            'bill'           => $bill,
            'existingItems'  => $items,
            'savedRollSpecs' => $savedRollSpecs,
            'savedColors'    => $savedColors,
            'savedDesigns'   => $savedDesigns,
        ]);
    }

    public function saveHsnAjax(): void {
        $this->ensureHsnMasterSchema();
        header('Content-Type: application/json; charset=utf-8');
        $cid = Auth::concernId() ?: ((int)($_POST['concern_id'] ?? 0) ?: null);
        $code = strtoupper(trim($_POST['hsn_code'] ?? ''));
        $codeType = strtoupper(trim($_POST['code_type'] ?? 'HSN'));
        $desc = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $rateNote = trim($_POST['rate_note'] ?? '');
        $rateInput = trim((string)($_POST['gst_rate'] ?? ''));
        $rate = $rateInput === '' ? null : (is_numeric($rateInput) ? (float)$rateInput : -1.0);

        if (!in_array($codeType, ['HSN', 'SAC'], true) || !preg_match('/^\d{4}(?:\d{2}){0,2}$/', $code) || strlen($desc) > 255 || strlen($category) > 100 || strlen($rateNote) > 500 || ($rate !== null && ($rate < 0 || $rate > 100))) {
            echo json_encode(['success' => false, 'message' => 'Use a valid 4-, 6-, or 8-digit HSN/SAC code and GST rate details.']);
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

    public function storeBill(): void {
        $this->validateCsrf();
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

        // Process the 3 Inline Sections
        $rollsRaw        = $_POST['rolls'] ?? [];
        $garmentOtherRaw = $_POST['garment_other'] ?? [];
        $finishedRaw     = $_POST['finished_goods'] ?? [];

        $itemsToInsert = [];
        $totalAmount   = 0.0;
        $totalTax      = 0.0;

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
                    if ((float)($variantRow['quantity'] ?? 0) <= 0 || (float)($variantRow['supplier_cost'] ?? 0) <= 0) {
                        $this->flash('error', 'Every new finished-goods variant needs a quantity and supplier cost greater than zero.');
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
                    $catalogDraft = null;
                    $categoryName = trim($f['category'] ?? 'Apparel Finished Goods');
                    $productName = trim($f['product'] ?? ($f['item_name'] ?? 'Finished Garment'));
                    $itemCode = trim($f['sku'] ?? ($f['item_code'] ?? ''));
                    $hsnCode = trim($f['hsn'] ?? '');
                    $size = trim($f['size'] ?? ($f['variant'] ?? ''));
                    $color = '';
                    $mrp = (float)($f['mrp'] ?? 0);
                    $sellingPrice = (float)($f['selling_price'] ?? 0);
                    $catalogGstRate = (float)($f['catalog_gst_rate'] ?? 0);

                    if ($isProductAddingInvoice) {
                        $styleCode = strtoupper(trim((string)($f['style_code'] ?? '')));
                        $categoryId = (int)($f['category_id'] ?? 0);
                        $subcategoryId = (int)($f['subcategory_id'] ?? 0) ?: null;
                        $subSubcategoryId = (int)($f['sub_sub_category_id'] ?? 0) ?: null;
                        $size = trim((string)($f['size'] ?? ''));
                        $color = trim((string)($f['color'] ?? ''));
                        $hsnCode = strtoupper(trim((string)($f['hsn'] ?? '')));
                        $catalogGstRate = (float)($f['catalog_gst_rate'] ?? 0);
                        $styleName = trim((string)($f['catalog_style_name'] ?? $productName));
                        $variantName = trim((string)($f['variant_name'] ?? $productName));
                        if ($variantName === '') {
                            $variantName = trim($styleName . ' - ' . $size . ' - ' . $color);
                        }
                        $productName = $variantName;
                        if ($styleCode === '' || $categoryId <= 0 || $size === '' || $color === '' || $styleName === '') {
                            $this->flash('error', 'New catalog variants require a style, category, size, and color.');
                            $this->redirect($createRedirect);
                            return;
                        }
                        $customAttributes = [];
                        $reservedFields = [
                            'product', 'item_name', 'sku', 'item_code', 'variant_id', 'catalog_id',
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
                            'sku_code' => $itemCode,
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
                    if ($catalogDraft && !in_array($taxType, ['Amount', 'Amount (₹)'], true)
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
            $this->flash('error', 'Please enter at least one item (Roll, Garment Supply, or Finished Good) with quantity and price.');
            $this->redirect($createRedirect);
            return;
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
                    $item['item_code'] = $mappedProduct['sku_code'];
                    $item['category_name'] = $mappedProduct['category_name'];
                    unset($item['catalog_draft']);
                }
            }
            unset($item);

            DB::query(
                "INSERT INTO purchase_bills (
                    concern_id, bill_no, supplier_invoice_no, supplier_id, bill_date,
                    total_amount, tax_amount, net_amount, payment_status, payment_method, payment_reference, paid_amount,
                    inward_status, notes, attachment_path, attachment_paths, fabric_description, created_by, created_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $cid, $billNo, $supplierInvoiceNo, $supplierId, $billDate,
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
                        DB::query("INSERT INTO hsn_masters (concern_id, hsn_code, description, gst_rate, created_at) VALUES (?, ?, ?, ?, NOW())", [
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
            $this->redirect($successRedirect);
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
        $requestedSku = strtoupper(trim((string)($draft['sku_code'] ?? '')));
        $categoryId = (int)($draft['category_id'] ?? 0);
        $subcategoryId = (int)($draft['subcategory_id'] ?? 0) ?: null;
        $subSubcategoryId = (int)($draft['sub_sub_category_id'] ?? 0) ?: null;
        $size = trim((string)($draft['size'] ?? ''));
        $color = trim((string)($draft['color'] ?? ''));
        $weight = (float)($draft['weight'] ?? 0);
        $hsnCode = strtoupper(trim((string)($draft['hsn_code'] ?? '')));
        $sellingPrice = (float)($draft['selling_price'] ?? 0);
        $mrp = (float)($draft['mrp'] ?? 0);

        if ($styleCode === '' || strlen($styleCode) > 50 || mb_strlen($styleName) < 2 || mb_strlen($styleName) > 150) {
            throw new Exception('Enter a unique style code and a product name between 2 and 150 characters.');
        }
        if ($supplierCost <= 0 || $sellingPrice <= $supplierCost || $mrp <= $sellingPrice) {
            throw new Exception('New catalog pricing must follow Supplier Cost < Selling Price < MRP.');
        }
        if ($size === '' || mb_strlen($size) > 50 || $color === '' || mb_strlen($color) > 100
            || $weight < 0 || $weight > 999999) {
            throw new Exception('Choose a valid size and color for the new catalog variant.');
        }
        if ($variantName === '') {
            $variantName = trim($styleName . ' - ' . $size . ' - ' . $color);
        }
        if (mb_strlen($variantName) < 2 || mb_strlen($variantName) > 150) {
            throw new Exception('Variant name must be between 2 and 150 characters.');
        }
        if ($requestedSku !== '' && strlen($requestedSku) > 50) {
            throw new Exception('Variant SKU must be 50 characters or fewer.');
        }
        if (!preg_match('/^\d{4}(?:\d{2}){0,2}$/', $hsnCode)) {
            throw new Exception('Select or create a valid 4-, 6-, or 8-digit HSN code.');
        }

        $category = DB::fetch(
            "SELECT id, name FROM categories
             WHERE id = ? AND status = 'active'
               AND (concern_id = ? OR concern_id = 1 OR concern_id IS NULL)",
            [$categoryId, $concernId]
        );
        if (!$category) {
            throw new Exception('The selected main category is not valid for this concern.');
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
                'SELECT id FROM catalogs WHERE concern_id = ? AND style_code = ? LIMIT 1',
                [$concernId, $styleCode]
            )) {
                throw new Exception('This style code already exists. Enter a unique style code for the new style.');
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
                    hsn_code, gst_rate, sizes, status, name, created_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())",
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

        $skuBase = $requestedSku !== ''
            ? $requestedSku
            : strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', implode('-', array_merge(
                [$styleCode, $size, $color],
                array_values($normalizedAttributes)
            ))) ?: $styleCode);
        $skuBase = substr(trim($skuBase, '-'), 0, 45);
        $sku = $skuBase;
        $sequence = 2;
        while (DB::fetchValue('SELECT id FROM variants WHERE concern_id = ? AND sku_code = ? LIMIT 1', [$concernId, $sku])) {
            $suffix = '-' . $sequence++;
            $sku = substr($skuBase, 0, 50 - strlen($suffix)) . $suffix;
        }

        $variantAttributes = array_merge(
            ['size' => $size, 'color' => $color, 'hsn' => $hsnCode, 'weight' => $weight, 'custom_attributes' => $normalizedAttributes],
            $normalizedAttributes
        );
        DB::query(
            "INSERT INTO variants (
                concern_id, catalog_id, sku_code, name, category_id, subcategory_id, size,
                weight, weight_unit, attributes, cost_price, price, mrp, is_complementary,
                creation_date, status, created_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'kg', ?, ?, ?, ?, 0, CURDATE(), 'active', NOW())",
            [
                $concernId, $catalog['catalog_id'], $sku, $variantName, $categoryId, $subcategoryId,
                $size, $weight, json_encode($variantAttributes), $supplierCost, $sellingPrice, $mrp,
            ]
        );
        $variantId = (int)DB::lastInsertId();

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
            'catalog_id' => (int)$catalog['catalog_id'],
            'variant_id' => $variantId,
            'sku_code' => $sku,
            'category_name' => $catalog['category_name'],
        ];
    }

    public function updateBill(): void {
        $this->validateCsrf();
        $billId = (int)($_POST['bill_id'] ?? 0);
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);

        if (!$billId) {
            $this->flash('error', 'Invalid purchase bill ID.');
            $this->redirect('/m/purchase_billing');
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
            $this->redirect('/m/purchase_billing');
            return;
        }

        $inwardedItemCount = (int)DB::fetchValue(
            "SELECT COUNT(*) FROM purchase_bill_items WHERE purchase_bill_id = ? AND inward_status = 'completed'",
            [$billId]
        );
        if (($bill['inward_status'] ?? 'pending') === 'completed' || $inwardedItemCount > 0) {
            $this->flash('error', 'This bill has items already added to stock and can no longer be edited.');
            $this->redirect('/m/purchase_billing');
            return;
        }

        $supplierId = (int)($_POST['supplier_id'] ?? $bill['supplier_id']);
        if (!$supplierId) {
            $this->flash('error', 'Please select a registered supplier.');
            $this->redirect('/m/purchase_billing/edit?id=' . $billId);
            return;
        }

        $billDate          = !empty($_POST['bill_date']) ? $_POST['bill_date'] : $bill['bill_date'];
        $supplierInvoiceNo = trim($_POST['supplier_invoice_no'] ?? '');
        if (empty($supplierInvoiceNo)) {
            $this->flash('error', 'Invoice / Bill Number is required.');
            $this->redirect('/m/purchase_billing/edit?id=' . $billId);
            return;
        }
        $fabricDescription = trim($_POST['fabric_description'] ?? '');
        $notes             = trim($_POST['notes'] ?? '');
        $paymentStatus     = trim($_POST['payment_status'] ?? ($bill['payment_status'] ?? 'unpaid'));
        $paymentMethod     = trim($_POST['payment_method'] ?? ($bill['payment_method'] ?? 'credit'));
        $paymentReference  = trim($_POST['payment_reference'] ?? ($bill['payment_reference'] ?? ''));
        $paidAmount        = isset($_POST['paid_amount']) ? (float)$_POST['paid_amount'] : (float)($bill['paid_amount'] ?? 0.0);
        $inwardStatus      = 'pending';

        if (!in_array($paymentStatus, ['unpaid', 'partial', 'paid'], true)) {
            $paymentStatus = 'unpaid';
        }
        // Process sections
        $rollsRaw        = $_POST['rolls'] ?? [];
        $garmentOtherRaw = $_POST['garment_other'] ?? [];
        $finishedRaw     = $_POST['finished_goods'] ?? [];

        $itemsToInsert = [];
        $totalAmount   = 0.0;
        $totalTax      = 0.0;

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
            $this->redirect('/m/purchase_billing/edit?id=' . $billId);
            return;
        }

        try {
            $attachmentPaths = $this->uploadBillAttachments($this->getBillAttachmentPaths($bill));
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/m/purchase_billing/edit?id=' . $billId);
            return;
        }
        if (empty($attachmentPaths)) {
            $this->flash('error', 'Tax Invoice / Bill Attachment is required. Please upload at least one invoice file.');
            $this->redirect('/m/purchase_billing/edit?id=' . $billId);
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

        DB::beginTransaction();
        try {
            DB::query(
                "UPDATE purchase_bills
                 SET supplier_id = ?, bill_date = ?, supplier_invoice_no = ?, notes = ?,
                     payment_status = ?, payment_method = ?, payment_reference = ?, paid_amount = ?,
                     inward_status = ?, attachment_path = ?, attachment_paths = ?, fabric_description = ?,
                     total_amount = ?, tax_amount = ?, net_amount = ?
                 WHERE id = ?",
                [
                    $supplierId, $billDate, $supplierInvoiceNo, $notes,
                    $paymentStatus, $paymentMethod, $paymentReference, $paidAmount,
                    $inwardStatus, $attachmentPath, $attachmentPathsJson, $fabricDescription,
                    $totalAmount, $totalTax, $netAmount,
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
                        DB::query("INSERT INTO hsn_masters (concern_id, hsn_code, description, gst_rate, created_at) VALUES (?, ?, ?, ?, NOW())", [
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
            $this->redirect('/m/purchase_billing');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Error updating purchase bill: ' . $e->getMessage());
            $this->redirect('/m/purchase_billing/edit?id=' . $billId);
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
    public function damage(): void {
        $isSuperAdmin = Auth::isSuperAdmin();
        $cid = Auth::concernId();
        if ($isSuperAdmin) {
            $cid = (int)($_GET['concern_id'] ?? 0) ?: null;
        }

        PurchaseDamageService::initTable();
        $sql = "SELECT history_source, record_id, damage_no, concern_id, concern_name, supplier_name,
                       item_name, item_code, item_type, tag_code, qty, location, reason, notes,
                       damaged_weight, estimated_loss, action_taken, created_at
                FROM (
                    SELECT 'legacy' AS history_source, pd.id AS record_id, pd.damage_no, pd.concern_id,
                           con.name AS concern_name, s.name AS supplier_name, v.name AS item_name,
                           v.sku_code AS item_code, 'legacy' AS item_type, NULL AS tag_code, pd.qty,
                           pd.location, pd.reason, NULL AS notes, 0 AS damaged_weight, 0 AS estimated_loss,
                           pd.action_taken, pd.created_at
                    FROM purchase_damages pd
                    JOIN suppliers s ON pd.supplier_id = s.id
                    JOIN variants v ON pd.variant_id = v.id
                    LEFT JOIN concerns con ON con.id = pd.concern_id
                    UNION ALL
                    SELECT 'quarantine' AS history_source, q.id AS record_id, q.damage_no, q.concern_id,
                           con.name AS concern_name, q.supplier_name, q.item_name, q.item_code,
                           q.item_type, q.tag_code, q.qty, q.location, q.reason, q.notes,
                           q.damaged_weight, q.estimated_loss, q.status AS action_taken, q.created_at
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
        $allConcerns = $isSuperAdmin
            ? DB::fetchAll("SELECT id, name, code FROM concerns ORDER BY name ASC")
            : [];
        $canAddDamage = $isSuperAdmin || Auth::can('purchase_damage', 'add');
        $canEditDamage = $isSuperAdmin || Auth::can('purchase_damage', 'edit');
        $canDeleteDamage = $isSuperAdmin || Auth::can('purchase_damage', 'delete');

        $this->render('concern/purchase_damage', [
            'pageTitle'     => 'Purchase Damage & Rejections',
            'currentModule' => 'purchase_damage',
            'damages'       => $damages,
            'selectedCid'   => $cid,
            'isSuperAdmin'  => $isSuperAdmin,
            'allConcerns'   => $allConcerns,
            'damageReasons' => $damageReasons,
            'canAddDamage'  => $canAddDamage,
            'canEditDamage' => $canEditDamage,
            'canDeleteDamage' => $canDeleteDamage,
        ]);
    }

    public function exportDamage(): void {
        ModuleGuard::enforce('purchase_damage', 'view');
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
                    SELECT 'legacy' AS history_source, pd.id AS record_id, pd.damage_no, pd.concern_id,
                           con.name AS concern_name, s.name AS supplier_name, v.name AS item_name,
                           v.sku_code AS item_code, 'legacy' AS item_type, NULL AS tag_code, pd.qty,
                           pd.location, pd.reason, NULL AS notes, 0 AS damaged_weight, 0 AS estimated_loss,
                           pd.action_taken, pd.created_at
                    FROM purchase_damages pd
                    JOIN suppliers s ON pd.supplier_id = s.id
                    JOIN variants v ON pd.variant_id = v.id
                    LEFT JOIN concerns con ON con.id = pd.concern_id
                    UNION ALL
                    SELECT 'quarantine' AS history_source, q.id AS record_id, q.damage_no, q.concern_id,
                           con.name AS concern_name, q.supplier_name, q.item_name, q.item_code,
                           q.item_type, q.tag_code, q.qty, q.location, q.reason, q.notes,
                           q.damaged_weight, q.estimated_loss, q.status AS action_taken, q.created_at
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
        ModuleGuard::enforce('purchase_damage', 'add');
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
            $this->redirect('/m/purchase_damage');
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

        $this->redirect('/m/purchase_damage');
    }

    public function updateDamageRecord(): void {
        ModuleGuard::enforce('purchase_damage', 'edit');
        $this->validateCsrf();

        $damageId = (int)($_POST['damage_id'] ?? 0);
        $concernId = Auth::isSuperAdmin()
            ? (int)($_POST['concern_id'] ?? 0)
            : Auth::concernId();
        $returnUrl = '/m/purchase_damage' . (Auth::isSuperAdmin() && $concernId ? '?concern_id=' . $concernId : '');

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
        ModuleGuard::enforce('purchase_damage', 'delete');
        $this->validateCsrf();

        $damageId = (int)($_POST['damage_id'] ?? 0);
        $concernId = Auth::isSuperAdmin()
            ? (int)($_POST['concern_id'] ?? 0)
            : Auth::concernId();
        $returnUrl = '/m/purchase_damage' . (Auth::isSuperAdmin() && $concernId ? '?concern_id=' . $concernId : '');

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
