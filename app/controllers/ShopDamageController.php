<?php
// app/controllers/ShopDamageController.php - Retail Shop Floor Damage Management & Resolution

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Services\DamageService;
use Exception;

class ShopDamageController extends Controller {
    public function index(): void {
        $cid = Auth::concernId();

        $search = trim($_GET['search'] ?? '');
        $status = strtoupper(trim($_GET['status'] ?? ''));
        $severity = strtoupper(trim($_GET['severity'] ?? ''));
        $fromDate = trim($_GET['from_date'] ?? '');
        $toDate = trim($_GET['to_date'] ?? '');

        $sql = "SELECT sd.*, v.sku_code, v.name AS variant_name, v.size, v.price AS unit_price,
                       con.name AS concern_name, con.code AS concern_code,
                       u.name AS creator_name, ru.name AS resolver_name
                FROM shop_damages sd
                JOIN variants v ON sd.variant_id = v.id
                LEFT JOIN concerns con ON sd.concern_id = con.id
                LEFT JOIN users u ON sd.created_by = u.id
                LEFT JOIN users ru ON sd.resolved_by = ru.id
                WHERE 1=1";
        $params = [];

        if ($cid) {
            $sql .= " AND sd.concern_id = ?";
            $params[] = $cid;
        }
        if ($search !== '') {
            $sql .= " AND (sd.damage_no LIKE ? OR v.sku_code LIKE ? OR v.name LIKE ? OR sd.resolution_reason LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($status !== '') {
            $sql .= " AND sd.status = ?";
            $params[] = $status;
        }
        if ($severity !== '') {
            $sql .= " AND sd.severity = ?";
            $params[] = $severity;
        }
        if ($fromDate !== '') {
            $sql .= " AND sd.created_at >= ?";
            $params[] = "{$fromDate} 00:00:00";
        }
        if ($toDate !== '') {
            $sql .= " AND sd.created_at <= ?";
            $params[] = "{$toDate} 23:59:59";
        }

        $sql .= " ORDER BY sd.id DESC";
        $damages = DB::fetchAll($sql, $params);

        $variants = DB::fetchAll(
            "SELECT id, sku_code, name, size, price FROM variants 
             WHERE (concern_id = ? OR ? IS NULL) AND status = 'active' ORDER BY name ASC",
            [$cid, $cid]
        );

        $this->render('shop/damages/index', [
            'pageTitle'     => 'Retail Shop Damage Management',
            'currentModule' => 'shop_damage',
            'damages'       => $damages,
            'variants'      => $variants,
            'search'        => $search,
            'status'        => $status,
            'severity'      => $severity,
            'fromDate'      => $fromDate,
            'toDate'        => $toDate,
            'selectedCid'   => $cid,
        ]);
    }

    public function store(): void {
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);
        if (!$cid) {
            $this->flash('error', 'Please select a concern.');
            $this->redirect('/shop/damages');
            return;
        }

        $variantId = (int)($_POST['variant_id'] ?? 0);
        $qty = (float)($_POST['qty'] ?? 0);
        $shopId = (int)($_POST['shop_id'] ?? 1);
        $severity = strtoupper(trim($_POST['severity'] ?? 'MODERATE'));
        $reason = trim($_POST['reason'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $photoUrl = trim($_POST['photo_url'] ?? '');

        if (!$variantId || $qty <= 0) {
            $this->flash('error', 'Please choose a valid variant and quantity.');
            $this->redirect('/shop/damages');
            return;
        }

        try {
            $res = DamageService::log(
                $cid,
                $variantId,
                'SHOP',
                $shopId,
                $qty,
                $reason,
                $notes,
                Auth::id(),
                $photoUrl,
                $severity,
                'SHOP'
            );

            $this->flash('success', "Shop damage '{$res['damage_no']}' logged and {$qty} unit(s) moved to DAMAGED stock.");
        } catch (\Throwable $e) {
            $this->flash('error', 'Failed to log shop damage: ' . $e->getMessage());
        }

        $this->redirect('/shop/damages');
    }

    public function resolve(): void {
        $id = (int)($_POST['id'] ?? 0);
        $action = strtoupper(trim($_POST['resolution_action'] ?? ''));
        $reason = trim($_POST['resolution_reason'] ?? '');
        $notes = trim($_POST['resolution_notes'] ?? '');
        $lossAmount = (float)($_POST['loss_amount'] ?? 0);
        $replacementQr = trim($_POST['replacement_qr'] ?? '');

        if (!$id || $action === '') {
            $this->flash('error', 'Invalid damage resolution parameters.');
            $this->redirect('/shop/damages');
            return;
        }

        try {
            DamageService::resolve(
                $id,
                $action,
                [
                    'resolution_reason' => $reason,
                    'resolution_notes'  => $notes,
                    'loss_amount'       => $lossAmount,
                    'replacement_qr'    => $replacementQr
                ],
                Auth::id(),
                'SHOP'
            );

            $this->flash('success', "Shop damage resolved via action '{$action}'.");
        } catch (\Throwable $e) {
            $this->flash('error', 'Resolution failed: ' . $e->getMessage());
        }

        $this->redirect('/shop/damages');
    }

    public function inwardReplacement(): void {
        $id = (int)($_POST['id'] ?? 0);
        $scannedQr = trim($_POST['scanned_qr'] ?? '');
        $qty = (float)($_POST['inward_qty'] ?? 1);

        if (!$id || $scannedQr === '' || $qty <= 0) {
            $this->flash('error', 'Please provide a valid replacement QR code and inward quantity.');
            $this->redirect('/shop/damages');
            return;
        }

        try {
            DamageService::inwardReplacement($id, $scannedQr, $qty, Auth::id(), 'SHOP');
            $this->flash('success', "Replacement unit(s) inwarded successfully into live AVAILABLE stock.");
        } catch (\Throwable $e) {
            $this->flash('error', 'Inward replacement failed: ' . $e->getMessage());
        }

        $this->redirect('/shop/damages');
    }

    public function cancel(): void {
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['cancel_reason'] ?? 'Cancelled by user');

        try {
            DamageService::cancel($id, $reason, Auth::id(), 'SHOP');
            $this->flash('success', "Damage record was cancelled and stock restored to AVAILABLE.");
        } catch (\Throwable $e) {
            $this->flash('error', 'Cancellation failed: ' . $e->getMessage());
        }

        $this->redirect('/shop/damages');
    }

    public function print(int $id): void {
        $cid = Auth::concernId();

        $damage = DB::fetch(
            "SELECT sd.*, v.sku_code, v.name AS variant_name, v.size, v.price AS unit_price,
                    con.name AS concern_name, con.code AS concern_code, con.gstin, con.phone AS concern_phone,
                    u.name AS creator_name, ru.name AS resolver_name
             FROM shop_damages sd
             JOIN variants v ON sd.variant_id = v.id
             LEFT JOIN concerns con ON sd.concern_id = con.id
             LEFT JOIN users u ON sd.created_by = u.id
             LEFT JOIN users ru ON sd.resolved_by = ru.id
             WHERE sd.id = ? AND (sd.concern_id = ? OR ? IS NULL)",
            [$id, $cid, $cid]
        );

        if (!$damage) {
            $this->flash('error', 'Damage record not found.');
            $this->redirect('/shop/damages');
            return;
        }

        $this->render('shop/damages/print', [
            'pageTitle' => "Shop Damage Memo {$damage['damage_no']}",
            'damage'    => $damage,
        ]);
    }
}
