<?php
// app/controllers/CouponController.php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ModuleGuard;
use App\Services\AuditLog;
use App\Services\CouponService;

class CouponController extends Controller
{
    public function index(): void
    {
        ModuleGuard::enforce('coupons', 'view');
        CouponService::ensureSchema();
        $cid = Auth::concernId();

        $where = [];
        $params = [];
        if ($cid) {
            $where[] = 'pc.concern_id = ?';
            $params[] = $cid;
        }
        $statusFilter = strtoupper(trim((string) ($_GET['status'] ?? '')));
        if ($statusFilter !== '' && in_array($statusFilter, ['ACTIVE', 'INACTIVE', 'REDEEMED', 'EXPIRED', 'CANCELLED'], true)) {
            $where[] = 'pc.status = ?';
            $params[] = $statusFilter;
        }
        $search = trim((string) ($_GET['q'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(pc.code LIKE ? OR pc.title LIKE ?)';
            array_push($params, $like, $like);
        }

        $sql = "SELECT pc.*, con.name AS concern_name, con.code AS concern_code,
                       ib.invoice_no AS issued_invoice_no, rb.invoice_no AS redeemed_invoice_no
                FROM pos_coupons pc
                LEFT JOIN concerns con ON con.id = pc.concern_id
                LEFT JOIN sales_invoices ib ON ib.id = pc.issued_bill_id
                LEFT JOIN sales_invoices rb ON rb.id = pc.redeemed_bill_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY pc.id DESC';
        $coupons = DB::fetchAll($sql, $params);

        $summarySql = "SELECT
                            COUNT(*) AS total_count,
                            SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) AS active_count,
                            SUM(CASE WHEN status = 'REDEEMED' THEN 1 ELSE 0 END) AS redeemed_count,
                            SUM(CASE WHEN status IN ('INACTIVE', 'EXPIRED', 'CANCELLED') THEN 1 ELSE 0 END) AS closed_count
                       FROM pos_coupons" . ($cid ? ' WHERE concern_id = ?' : '');
        $summary = DB::fetch($summarySql, $cid ? [$cid] : []) ?: [
            'total_count' => 0,
            'active_count' => 0,
            'redeemed_count' => 0,
            'closed_count' => 0,
        ];

        $allConcerns = Auth::isSuperAdmin()
            ? DB::fetchAll("SELECT id, name, code FROM concerns WHERE status = 'active' ORDER BY name ASC")
            : [];

        $this->render('concern/coupons', [
            'pageTitle' => 'POS Coupons',
            'currentModule' => 'coupons',
            'coupons' => $coupons,
            'summary' => $summary,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'selectedCid' => $cid,
            'allConcerns' => $allConcerns,
        ]);
    }

    public function store(): void
    {
        ModuleGuard::enforce('coupons', 'add');
        $this->validateCsrf();
        CouponService::ensureSchema();

        $cid = Auth::concernId();
        if (!$cid && Auth::isSuperAdmin()) {
            $cid = (int) ($_POST['concern_id'] ?? 0) ?: null;
        }
        if (!$cid || !DB::fetchValue("SELECT id FROM concerns WHERE id = ? AND status = 'active'", [$cid])) {
            $this->flash('error', 'Select an active concern before creating a coupon.');
            $this->redirect('/m/coupons');
            return;
        }

        $code = strtoupper(preg_replace('/\s+/', '', trim((string) ($_POST['code'] ?? ''))));
        $title = trim((string) ($_POST['title'] ?? ''));
        $discountType = strtoupper(trim((string) ($_POST['discount_type'] ?? 'FIXED')));
        $discountValue = (float) ($_POST['discount_value'] ?? 0);
        $minRedemption = max(0.0, (float) ($_POST['min_redemption_amount'] ?? 0));
        $maxUses = max(0, (int) ($_POST['max_uses'] ?? 1));
        $expiresAt = trim((string) ($_POST['expires_at'] ?? ''));
        $status = strtoupper(trim((string) ($_POST['status'] ?? 'ACTIVE')));
        if ($code === '' || strlen($code) > 50 || !preg_match('/^[A-Z0-9_-]+$/', $code)) {
            $this->flash('error', 'Coupon code is required and may contain only letters, numbers, hyphens, and underscores.');
            $this->redirect('/m/coupons');
            return;
        }
        if ($title === '' || mb_strlen($title) > 150) {
            $this->flash('error', 'Coupon title is required (1 to 150 characters).');
            $this->redirect('/m/coupons');
            return;
        }
        if (!in_array($discountType, ['FIXED', 'PERCENT'], true)) {
            $this->flash('error', 'Discount type must be FIXED or PERCENT.');
            $this->redirect('/m/coupons');
            return;
        }
        if ($discountValue <= 0 || ($discountType === 'PERCENT' && $discountValue > 100)) {
            $this->flash('error', $discountType === 'PERCENT'
                ? 'Percentage discount must be greater than 0 and at most 100.'
                : 'Fixed discount value must be greater than 0.');
            $this->redirect('/m/coupons');
            return;
        }
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $status = 'ACTIVE';
        }
        if ($expiresAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) {
            $this->flash('error', 'Enter a valid expiry date (YYYY-MM-DD).');
            $this->redirect('/m/coupons');
            return;
        }
        if (DB::fetchValue("SELECT id FROM pos_coupons WHERE concern_id = ? AND code = ?", [$cid, $code])) {
            $this->flash('error', "Duplicate Error: Coupon code '{$code}' already exists for this concern.");
            $this->redirect('/m/coupons');
            return;
        }

        DB::query(
            "INSERT INTO pos_coupons
             (concern_id, code, title, discount_type, discount_value, min_redemption_amount, max_uses, used_count, expires_at, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, NOW())",
            [
                $cid,
                $code,
                $title,
                $discountType,
                round($discountValue, 2),
                round($minRedemption, 2),
                $maxUses,
                $expiresAt !== '' ? $expiresAt : null,
                $status,
                Auth::id(),
            ]
        );
        $newId = (int) DB::lastInsertId();
        AuditLog::log('CREATE', 'pos_coupons', $newId, null, ['code' => $code, 'title' => $title]);

        $this->flash('success', "Coupon '{$code}' created successfully.");
        $this->redirect('/m/coupons');
    }

    public function update(): void
    {
        ModuleGuard::enforce('coupons', 'edit');
        $this->validateCsrf();
        CouponService::ensureSchema();

        $cid = Auth::concernId();
        $id = (int) ($_POST['id'] ?? 0);
        $coupon = DB::fetch("SELECT * FROM pos_coupons WHERE id = ?", [$id]);
        if (!$coupon || ($cid && (int) $coupon['concern_id'] !== $cid)) {
            $this->flash('error', 'Coupon not found.');
            $this->redirect('/m/coupons');
            return;
        }

        $targetCid = (int) $coupon['concern_id'];
        $code = strtoupper(preg_replace('/\s+/', '', trim((string) ($_POST['code'] ?? ''))));
        $title = trim((string) ($_POST['title'] ?? ''));
        $discountType = strtoupper(trim((string) ($_POST['discount_type'] ?? 'FIXED')));
        $discountValue = (float) ($_POST['discount_value'] ?? 0);
        $minRedemption = max(0.0, (float) ($_POST['min_redemption_amount'] ?? 0));
        $maxUses = max(0, (int) ($_POST['max_uses'] ?? 1));
        $expiresAt = trim((string) ($_POST['expires_at'] ?? ''));
        $status = strtoupper(trim((string) ($_POST['status'] ?? 'ACTIVE')));
        if ($code === '' || strlen($code) > 50 || !preg_match('/^[A-Z0-9_-]+$/', $code)) {
            $this->flash('error', 'Invalid coupon code.');
            $this->redirect('/m/coupons');
            return;
        }
        if ($title === '' || mb_strlen($title) > 150) {
            $this->flash('error', 'Coupon title is required.');
            $this->redirect('/m/coupons');
            return;
        }
        if (!in_array($discountType, ['FIXED', 'PERCENT'], true) || $discountValue <= 0 || ($discountType === 'PERCENT' && $discountValue > 100)) {
            $this->flash('error', 'Invalid discount type or value.');
            $this->redirect('/m/coupons');
            return;
        }
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'REDEEMED', 'EXPIRED', 'CANCELLED'], true)) {
            $status = (string) $coupon['status'];
        }
        if (DB::fetchValue("SELECT id FROM pos_coupons WHERE concern_id = ? AND code = ? AND id != ?", [$targetCid, $code, $id])) {
            $this->flash('error', "Duplicate Error: Coupon code '{$code}' already exists.");
            $this->redirect('/m/coupons');
            return;
        }

        DB::query(
            "UPDATE pos_coupons
             SET code = ?, title = ?, discount_type = ?, discount_value = ?, min_redemption_amount = ?,
                 max_uses = ?, expires_at = ?, status = ?, updated_at = NOW()
             WHERE id = ?",
            [
                $code,
                $title,
                $discountType,
                round($discountValue, 2),
                round($minRedemption, 2),
                $maxUses,
                $expiresAt !== '' ? $expiresAt : null,
                $status,
                $id,
            ]
        );
        AuditLog::log('UPDATE', 'pos_coupons', $id, $coupon, ['code' => $code, 'status' => $status]);

        $this->flash('success', "Coupon '{$code}' updated.");
        $this->redirect('/m/coupons');
    }

    public function toggleStatus(): void
    {
        ModuleGuard::enforce('coupons', 'edit');
        $this->validateCsrf();
        CouponService::ensureSchema();

        $cid = Auth::concernId();
        $id = (int) ($_POST['id'] ?? 0);
        $nextStatus = strtoupper(trim((string) ($_POST['status'] ?? 'INACTIVE')));
        if (!in_array($nextStatus, ['ACTIVE', 'INACTIVE', 'CANCELLED'], true)) {
            $nextStatus = 'INACTIVE';
        }

        $coupon = DB::fetch("SELECT * FROM pos_coupons WHERE id = ?", [$id]);
        if (!$coupon || ($cid && (int) $coupon['concern_id'] !== $cid)) {
            $this->flash('error', 'Coupon not found.');
            $this->redirect('/m/coupons');
            return;
        }

        DB::query("UPDATE pos_coupons SET status = ?, updated_at = NOW() WHERE id = ?", [$nextStatus, $id]);
        $this->flash('success', "Coupon '{$coupon['code']}' marked as {$nextStatus}.");
        $this->redirect('/m/coupons');
    }

    public function delete(): void
    {
        ModuleGuard::enforce('coupons', 'delete');
        $this->validateCsrf();
        CouponService::ensureSchema();

        $cid = Auth::concernId();
        $id = (int) ($_POST['id'] ?? 0);
        $coupon = DB::fetch("SELECT * FROM pos_coupons WHERE id = ?", [$id]);
        if (!$coupon || ($cid && (int) $coupon['concern_id'] !== $cid)) {
            $this->flash('error', 'Coupon not found.');
            $this->redirect('/m/coupons');
            return;
        }

        if ((int) $coupon['used_count'] > 0) {
            DB::query("UPDATE pos_coupons SET status = 'CANCELLED', updated_at = NOW() WHERE id = ?", [$id]);
            $this->flash('info', "Coupon '{$coupon['code']}' has redemption history and was marked CANCELLED instead of deleting.");
        } else {
            DB::query("DELETE FROM pos_coupons WHERE id = ?", [$id]);
            AuditLog::log('DELETE', 'pos_coupons', $id, $coupon, null);
            $this->flash('success', "Coupon '{$coupon['code']}' deleted.");
        }

        $this->redirect('/m/coupons');
    }

    public function printCoupon(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }
        CouponService::ensureSchema();
        $cid = Auth::concernId();
        $id = (int) ($_GET['id'] ?? 0);
        $billId = (int) ($_GET['bill_id'] ?? 0);

        $sql = "SELECT pc.*, con.name AS concern_name, con.code AS concern_code, con.address AS concern_address,
                       con.phone AS concern_phone, con.gstin AS concern_gstin, con.logo AS concern_logo,
                       si.invoice_no AS issued_invoice_no
                FROM pos_coupons pc
                LEFT JOIN concerns con ON con.id = pc.concern_id
                LEFT JOIN sales_invoices si ON si.id = pc.issued_bill_id
                WHERE " . ($id > 0 ? 'pc.id = ?' : 'pc.issued_bill_id = ?');
        $params = [$id > 0 ? $id : $billId];
        if ($cid) {
            $sql .= ' AND pc.concern_id = ?';
            $params[] = $cid;
        }
        $sql .= ' ORDER BY pc.id DESC LIMIT 1';
        $coupon = DB::fetch($sql, $params);

        if (!$coupon) {
            $this->flash('error', 'Coupon voucher not found.');
            $this->redirect('/m/coupons');
            return;
        }

        require __DIR__ . '/../views/concern/coupon_print.php';
    }

    public function apiActive(): void
    {
        ModuleGuard::enforce('billing', 'view');
        header('Content-Type: application/json; charset=utf-8');
        $cid = Auth::concernId() ?: (int) ($_GET['concern_id'] ?? 0);
        if ($cid <= 0 && Auth::isSuperAdmin()) {
            $cid = (int) (DB::fetchValue("SELECT id FROM concerns WHERE status = 'active' ORDER BY id ASC LIMIT 1") ?: 0);
        }
        $coupons = $cid > 0 ? CouponService::getActiveCouponsForPos($cid) : [];
        echo json_encode(['success' => true, 'coupons' => $coupons]);
        exit;
    }
}
