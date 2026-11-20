<?php
// app/controllers/WarehouseDamageController.php - Warehouse Floor Damage Management & Resolution

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Services\DamageService;
use Exception;

class WarehouseDamageController extends Controller {
    public function index(): void {
        $cid = Auth::concernId();

        $search = trim($_GET['search'] ?? '');
        $status = strtoupper(trim($_GET['status'] ?? ''));
        $severity = strtoupper(trim($_GET['severity'] ?? ''));
        $fromDate = trim($_GET['from_date'] ?? '');
        $toDate = trim($_GET['to_date'] ?? '');

        $sql = "SELECT wd.*, v.sku_code, v.name AS variant_name, v.size, v.price AS unit_price,
                       con.name AS concern_name, con.code AS concern_code,
                       u.name AS creator_name, ru.name AS resolver_name
                FROM warehouse_damages wd
                JOIN variants v ON wd.variant_id = v.id
                LEFT JOIN concerns con ON wd.concern_id = con.id
                LEFT JOIN users u ON wd.created_by = u.id
                LEFT JOIN users ru ON wd.resolved_by = ru.id
                WHERE 1=1";
        $params = [];

        if ($cid) {
            $sql .= " AND wd.concern_id = ?";
            $params[] = $cid;
        }
        if ($search !== '') {
            $sql .= " AND (wd.damage_no LIKE ? OR v.sku_code LIKE ? OR v.name LIKE ? OR wd.resolution_reason LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($status !== '') {
            $sql .= " AND wd.status = ?";
            $params[] = $status;
        }
        if ($severity !== '') {
            $sql .= " AND wd.severity = ?";
            $params[] = $severity;
        }
        if ($fromDate !== '') {
            $sql .= " AND wd.created_at >= ?";
            $params[] = "{$fromDate} 00:00:00";
        }
        if ($toDate !== '') {
            $sql .= " AND wd.created_at <= ?";
            $params[] = "{$toDate} 23:59:59";
        }

        $sql .= " ORDER BY wd.id DESC";
        $damages = DB::fetchAll($sql, $params);

        $variants = DB::fetchAll(
            "SELECT id, sku_code, name, size, price FROM variants 
             WHERE (concern_id = ? OR ? IS NULL) AND status = 'active' ORDER BY name ASC",
            [$cid, $cid]
        );

        $this->render('warehouse/damages/index', [
            'pageTitle'     => 'Warehouse Damage Management',
            'currentModule' => 'warehouse_damage',
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
            $this->redirect('/warehouse/damages');
            return;
        }

        $variantId = (int)($_POST['variant_id'] ?? 0);
        $qty = (float)($_POST['qty'] ?? 0);
        $warehouseId = (int)($_POST['warehouse_id'] ?? 1);
        $location = trim($_POST['location'] ?? 'Warehouse Floor');
        $severity = strtoupper(trim($_POST['severity'] ?? 'MODERATE'));
        $reason = trim($_POST['reason'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $photoUrl = trim($_POST['photo_url'] ?? '');

        if (!$variantId || $qty <= 0) {
            $this->flash('error', 'Please choose a valid variant and quantity.');
            $this->redirect('/warehouse/damages');
            return;
        }

        try {
            $res = DamageService::log(
                $cid,
                $variantId,
                'WAREHOUSE',
                $warehouseId,
                $qty,
                $reason,
                $notes,
                Auth::id(),
                $photoUrl,
                $severity,
                'WAREHOUSE'
            );

            $this->flash('success', "Warehouse damage '{$res['damage_no']}' logged and {$qty} unit(s) moved to DAMAGED stock.");
        } catch (\Throwable $e) {
            $this->flash('error', 'Failed to log warehouse damage: ' . $e->getMessage());
        }

        $this->redirect('/warehouse/damages');
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
            $this->redirect('/warehouse/damages');
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
                'WAREHOUSE'
            );

            $this->flash('success', "Warehouse damage resolved via action '{$action}'.");
        } catch (\Throwable $e) {
            $this->flash('error', 'Resolution failed: ' . $e->getMessage());
        }

        $this->redirect('/warehouse/damages');
    }

    public function inwardReplacement(): void {
        $id = (int)($_POST['id'] ?? 0);
        $scannedQr = trim($_POST['scanned_qr'] ?? '');
        $qty = (float)($_POST['inward_qty'] ?? 1);

        if (!$id || $scannedQr === '' || $qty <= 0) {
            $this->flash('error', 'Please provide a valid replacement QR code and inward quantity.');
            $this->redirect('/warehouse/damages');
            return;
        }

        try {
            DamageService::inwardReplacement($id, $scannedQr, $qty, Auth::id(), 'WAREHOUSE');
            $this->flash('success', "Replacement unit(s) inwarded successfully into live AVAILABLE stock.");
        } catch (\Throwable $e) {
            $this->flash('error', 'Inward replacement failed: ' . $e->getMessage());
        }

        $this->redirect('/warehouse/damages');
    }

    public function cancel(): void {
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['cancel_reason'] ?? 'Cancelled by user');

        try {
            DamageService::cancel($id, $reason, Auth::id(), 'WAREHOUSE');
            $this->flash('success', "Damage record was cancelled and stock restored to AVAILABLE.");
        } catch (\Throwable $e) {
            $this->flash('error', 'Cancellation failed: ' . $e->getMessage());
        }

        $this->redirect('/warehouse/damages');
    }

    public function print(int $id): void {
        $cid = Auth::concernId();

        $damage = DB::fetch(
            "SELECT wd.*, v.sku_code, v.name AS variant_name, v.size, v.price AS unit_price,
                    con.name AS concern_name, con.code AS concern_code, con.gstin, con.phone AS concern_phone,
                    u.name AS creator_name, ru.name AS resolver_name
             FROM warehouse_damages wd
             JOIN variants v ON wd.variant_id = v.id
             LEFT JOIN concerns con ON wd.concern_id = con.id
             LEFT JOIN users u ON wd.created_by = u.id
             LEFT JOIN users ru ON wd.resolved_by = ru.id
             WHERE wd.id = ? AND (wd.concern_id = ? OR ? IS NULL)",
            [$id, $cid, $cid]
        );

        if (!$damage) {
            $this->flash('error', 'Damage record not found.');
            $this->redirect('/warehouse/damages');
            return;
        }

        $this->render('warehouse/damages/print', [
            'pageTitle' => "Damage Memo {$damage['damage_no']}",
            'damage'    => $damage,
        ]);
    }
}
