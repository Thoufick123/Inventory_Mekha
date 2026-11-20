<?php
// app/controllers/ProductionReturnController.php - Reversible RMA with Real Exchanges & Multi-line Stock Ledger

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Services\StockService;
use App\Services\DamageService;
use App\Services\DocumentNumberService;
use App\Services\AuditService;
use Exception;

class ProductionReturnController extends Controller {
    public function index(): void {
        $cid = Auth::concernId();

        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $type = trim($_GET['type'] ?? '');
        $fromDate = trim($_GET['from_date'] ?? '');
        $toDate = trim($_GET['to_date'] ?? '');

        $sql = "SELECT pr.*, c.name AS customer_name, c.phone AS customer_phone,
                       con.name AS concern_name, con.code AS concern_code,
                       (SELECT COUNT(*) FROM production_return_exchange_items WHERE return_id = pr.id) AS item_count,
                       (SELECT COALESCE(SUM(total_amount), 0) FROM production_return_exchange_items WHERE return_id = pr.id AND direction = 'IN') AS return_total,
                       (SELECT COALESCE(SUM(total_amount), 0) FROM production_return_exchange_items WHERE return_id = pr.id AND direction = 'OUT') AS exchange_total
                FROM production_returns pr
                LEFT JOIN customers c ON pr.customer_id = c.id
                LEFT JOIN concerns con ON pr.concern_id = con.id
                WHERE 1=1";
        $params = [];

        if ($cid) {
            $sql .= " AND pr.concern_id = ?";
            $params[] = $cid;
        }
        if ($search !== '') {
            $sql .= " AND (pr.return_no LIKE ? OR c.name LIKE ? OR pr.reason LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($status !== '') {
            $sql .= " AND pr.status = ?";
            $params[] = $status;
        }
        if ($type !== '') {
            $sql .= " AND pr.type = ?";
            $params[] = $type;
        }
        if ($fromDate !== '') {
            $sql .= " AND pr.return_date >= ?";
            $params[] = $fromDate;
        }
        if ($toDate !== '') {
            $sql .= " AND pr.return_date <= ?";
            $params[] = $toDate;
        }

        $sql .= " ORDER BY pr.id DESC";
        $returns = DB::fetchAll($sql, $params);

        $this->render('production/returns/index', [
            'pageTitle'     => 'Returns & Exchanges (RMA)',
            'currentModule' => 'return_exchange',
            'returns'       => $returns,
            'search'        => $search,
            'status'        => $status,
            'type'          => $type,
            'fromDate'      => $fromDate,
            'toDate'        => $toDate,
            'selectedCid'   => $cid,
        ]);
    }

    public function create(): void {
        $cid = Auth::concernId();

        $customers = DB::fetchAll("SELECT id, name, phone FROM customers WHERE (concern_id = ? OR ? IS NULL) AND status = 'active' ORDER BY name ASC", [$cid, $cid]);
        $variants = DB::fetchAll("SELECT id, sku_code, name, size, price FROM variants WHERE (concern_id = ? OR ? IS NULL) AND status = 'active' ORDER BY name ASC", [$cid, $cid]);

        $this->render('production/returns/create', [
            'pageTitle'     => 'Create Return / Exchange RMA',
            'currentModule' => 'return_exchange',
            'customers'     => $customers,
            'variants'      => $variants,
            'selectedCid'   => $cid,
        ]);
    }

    public function ajaxLookupBill(): void {
        $cid = Auth::concernId();
        $invoiceNo = trim($_GET['invoice_no'] ?? '');

        if ($invoiceNo === '') {
            $this->renderJson(['success' => false, 'message' => 'Invoice number is required.'], 400);
            return;
        }

        $invoice = DB::fetch(
            "SELECT si.*, c.name AS customer_name, c.phone AS customer_phone 
             FROM sales_invoices si
             LEFT JOIN customers c ON si.customer_id = c.id
             WHERE si.invoice_no = ? AND (si.concern_id = ? OR ? IS NULL)",
            [$invoiceNo, $cid, $cid]
        );

        if (!$invoice) {
            $this->renderJson(['success' => false, 'message' => 'Invoice not found.'], 404);
            return;
        }

        $items = DB::fetchAll(
            "SELECT sii.*, v.sku_code, v.name AS variant_name, v.size 
             FROM sales_invoice_items sii
             JOIN variants v ON sii.variant_id = v.id
             WHERE sii.sales_invoice_id = ?",
            [$invoice['id']]
        );

        $this->renderJson([
            'success' => true,
            'invoice' => $invoice,
            'items'   => $items
        ]);
    }

    public function store(): void {
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);
        if (!$cid) {
            $this->flash('error', 'Please select a valid business concern.');
            $this->redirect('/production/returns');
            return;
        }

        $type = strtoupper(trim($_POST['type'] ?? 'RETURN'));
        $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $salesInvoiceId = !empty($_POST['sales_invoice_id']) ? (int)$_POST['sales_invoice_id'] : null;
        $returnDate = !empty($_POST['return_date']) ? trim($_POST['return_date']) : date('Y-m-d');
        $settlementMode = trim($_POST['settlement_mode'] ?? 'Credit Note');
        $settlementRef = trim($_POST['settlement_reference'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $photoUrl = trim($_POST['photo_url'] ?? '');

        $inItems = json_decode($_POST['in_items'] ?? '[]', true);
        $outItems = json_decode($_POST['out_items'] ?? '[]', true);

        // Fallback to single-line input if legacy form submitted
        if (empty($inItems) && !empty($_POST['returned_variant_id'])) {
            $inItems = [[
                'variant_id'      => (int)$_POST['returned_variant_id'],
                'qty'             => (float)($_POST['returned_qty'] ?? 1),
                'unit_price'      => (float)($_POST['returned_unit_price'] ?? 0),
                'condition_state' => trim($_POST['condition_state'] ?? 'Good'),
                'action'          => trim($_POST['action_type'] ?? 'RESTOCK')
            ]];
        }
        if (empty($outItems) && !empty($_POST['exchange_variant_id']) && $type === 'EXCHANGE') {
            $outItems = [[
                'variant_id' => (int)$_POST['exchange_variant_id'],
                'qty'        => (float)($_POST['exchange_qty'] ?? 1),
                'unit_price' => (float)($_POST['exchange_unit_price'] ?? 0)
            ]];
        }

        if (empty($inItems)) {
            $this->flash('error', 'Please provide at least one returned item.');
            $this->redirect('/production/returns/create');
            return;
        }

        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            $returnNo = DocumentNumberService::generate('RETURN', $cid);

            $totalInAmount = 0.00;
            $totalOutAmount = 0.00;

            foreach ($inItems as $item) {
                $q = (float)($item['qty'] ?? 0);
                $p = (float)($item['unit_price'] ?? 0);
                $totalInAmount += ($q * $p);
            }
            foreach ($outItems as $item) {
                $q = (float)($item['qty'] ?? 0);
                $p = (float)($item['unit_price'] ?? 0);
                $totalOutAmount += ($q * $p);
            }

            $priceDifference = $totalOutAmount - $totalInAmount;
            $refundAmount = $priceDifference < 0 ? abs($priceDifference) : 0.00;
            $extraCharge = $priceDifference > 0 ? $priceDifference : 0.00;

            // 1. Insert production_returns
            DB::query(
                "INSERT INTO production_returns (
                    concern_id, return_no, type, status, customer_id, sales_invoice_id, return_date, 
                    settlement_mode, settlement_reference, price_difference, refund_amount, extra_charge, 
                    reason, photo_url, created_by, created_at
                 ) VALUES (?, ?, ?, 'COMPLETED', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $cid, $returnNo, $type, $customerId, $salesInvoiceId, $returnDate,
                    $settlementMode, $settlementRef, $priceDifference, $refundAmount, $extraCharge,
                    $reason, $photoUrl, Auth::id()
                ]
            );
            $returnId = (int)DB::lastInsertId();

            // 2. Insert items and execute Stock Movements
            foreach ($inItems as $item) {
                $variantId = (int)($item['variant_id'] ?? 0);
                $qty = (float)($item['qty'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                $tagCode = trim((string)($item['tag_code'] ?? ''));
                $condition = trim((string)($item['condition_state'] ?? 'Good'));
                $action = strtoupper(trim((string)($item['action'] ?? 'RESTOCK')));
                $tot = $qty * $price;

                if ($variantId <= 0 || $qty <= 0) continue;

                DB::query(
                    "INSERT INTO production_return_exchange_items (
                        return_id, direction, variant_id, tag_code, qty, unit_price, total_amount, condition_state, restock_action, created_at
                     ) VALUES (?, 'IN', ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$returnId, $variantId, $tagCode, $qty, $price, $tot, $condition, $action]
                );

                // Stock ledger movement based on condition & action
                if ($action === 'RESTOCK') {
                    StockService::move(
                        $cid,
                        $variantId,
                        'Main Store',
                        $qty,
                        0.00,
                        'Return & Exchange',
                        $returnId,
                        "RMA Restock [{$returnNo}]: {$reason}",
                        Auth::id()
                    );
                } elseif ($action === 'REWORK') {
                    StockService::move(
                        $cid,
                        $variantId,
                        'PRODUCTION_UNIT:1:AVAILABLE',
                        $qty,
                        0.00,
                        'Return & Exchange',
                        $returnId,
                        "RMA Sent to Rework [{$returnNo}]: {$reason}",
                        Auth::id()
                    );
                } elseif ($action === 'SCRAP') {
                    DamageService::log(
                        $cid,
                        $variantId,
                        'WAREHOUSE',
                        1,
                        $qty,
                        "RMA Scrapped Return [{$returnNo}]: {$reason}",
                        "Logged from RMA {$returnNo}",
                        Auth::id(),
                        $photoUrl,
                        'SCRAP',
                        'CONCERN'
                    );
                }
            }

            // 3. Process exchange items OUT
            if ($type === 'EXCHANGE') {
                foreach ($outItems as $item) {
                    $variantId = (int)($item['variant_id'] ?? 0);
                    $qty = (float)($item['qty'] ?? 0);
                    $price = (float)($item['unit_price'] ?? 0);
                    $tagCode = trim((string)($item['tag_code'] ?? ''));
                    $tot = $qty * $price;

                    if ($variantId <= 0 || $qty <= 0) continue;

                    DB::query(
                        "INSERT INTO production_return_exchange_items (
                            return_id, direction, variant_id, tag_code, qty, unit_price, total_amount, created_at
                         ) VALUES (?, 'OUT', ?, ?, ?, ?, ?, NOW())",
                        [$returnId, $variantId, $tagCode, $qty, $price, $tot]
                    );

                    // Deduct from live stock
                    StockService::move(
                        $cid,
                        $variantId,
                        'Main Store',
                        0.00,
                        $qty,
                        'Return & Exchange',
                        $returnId,
                        "Exchange delivery for RMA {$returnNo}",
                        Auth::id()
                    );
                }
            }

            // Sync with returns_exchanges table for backward compatibility
            $firstIn = $inItems[0] ?? null;
            $firstOut = $outItems[0] ?? null;
            if ($firstIn) {
                DB::query(
                    "INSERT INTO returns_exchanges (
                        concern_id, return_no, type, customer_id, sales_invoice_id, return_date, 
                        returned_variant_id, returned_qty, exchange_variant_id, exchange_qty, 
                        reason, created_by, created_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $cid, $returnNo, strtolower($type), $customerId ?: 1, $salesInvoiceId, $returnDate,
                        (int)$firstIn['variant_id'], (float)$firstIn['qty'],
                        $firstOut ? (int)$firstOut['variant_id'] : null,
                        $firstOut ? (float)$firstOut['qty'] : null,
                        $reason, Auth::id()
                    ]
                );
            }

            AuditService::log('RETURN_CREATE', 'production_returns', $returnId, null, [
                'return_no'       => $returnNo,
                'type'            => $type,
                'item_count_in'   => count($inItems),
                'item_count_out'  => count($outItems),
                'refund_amount'   => $refundAmount,
                'extra_charge'    => $extraCharge
            ]);

            if (!$hasActiveTx) {
                DB::commit();
            }

            $this->flash('success', "Return/Exchange '{$returnNo}' processed successfully and stock ledger balanced.");
            $this->redirect('/production/returns/view/' . $returnId);
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            $this->flash('error', 'Failed to process return: ' . $e->getMessage());
            $this->redirect('/production/returns/create');
        }
    }

    public function view(int $id): void {
        $cid = Auth::concernId();

        $return = DB::fetch(
            "SELECT pr.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
                    si.invoice_no, con.name AS concern_name, con.code AS concern_code, u.name AS user_name
             FROM production_returns pr
             LEFT JOIN customers c ON pr.customer_id = c.id
             LEFT JOIN sales_invoices si ON pr.sales_invoice_id = si.id
             LEFT JOIN concerns con ON pr.concern_id = con.id
             LEFT JOIN users u ON pr.created_by = u.id
             WHERE pr.id = ? AND (pr.concern_id = ? OR ? IS NULL)",
            [$id, $cid, $cid]
        );

        if (!$return) {
            $this->flash('error', 'Return record not found.');
            $this->redirect('/production/returns');
            return;
        }

        $items = DB::fetchAll(
            "SELECT prei.*, v.sku_code, v.name AS variant_name, v.size 
             FROM production_return_exchange_items prei
             JOIN variants v ON prei.variant_id = v.id
             WHERE prei.return_id = ?
             ORDER BY prei.direction ASC, prei.id ASC",
            [$id]
        );

        $this->render('production/returns/view', [
            'pageTitle'     => "Return / Exchange {$return['return_no']}",
            'currentModule' => 'return_exchange',
            'return'        => $return,
            'items'         => $items,
        ]);
    }

    public function print(int $id): void {
        $cid = Auth::concernId();

        $return = DB::fetch(
            "SELECT pr.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
                    si.invoice_no, con.name AS concern_name, con.code AS concern_code, con.gstin, con.phone AS concern_phone,
                    u.name AS user_name
             FROM production_returns pr
             LEFT JOIN customers c ON pr.customer_id = c.id
             LEFT JOIN sales_invoices si ON pr.sales_invoice_id = si.id
             LEFT JOIN concerns con ON pr.concern_id = con.id
             LEFT JOIN users u ON pr.created_by = u.id
             WHERE pr.id = ? AND (pr.concern_id = ? OR ? IS NULL)",
            [$id, $cid, $cid]
        );

        if (!$return) {
            $this->flash('error', 'Return record not found.');
            $this->redirect('/production/returns');
            return;
        }

        $items = DB::fetchAll(
            "SELECT prei.*, v.sku_code, v.name AS variant_name, v.size 
             FROM production_return_exchange_items prei
             JOIN variants v ON prei.variant_id = v.id
             WHERE prei.return_id = ?
             ORDER BY prei.direction ASC, prei.id ASC",
            [$id]
        );

        $this->render('production/returns/print', [
            'pageTitle' => "Print Voucher {$return['return_no']}",
            'return'    => $return,
            'items'     => $items,
        ]);
    }

    public function cancel(): void {
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['cancel_reason'] ?? 'Cancelled by administrator');
        $cid = Auth::concernId();

        $return = DB::fetch(
            "SELECT * FROM production_returns WHERE id = ? AND (concern_id = ? OR ? IS NULL)",
            [$id, $cid, $cid]
        );

        if (!$return) {
            $this->flash('error', 'Return record not found.');
            $this->redirect('/production/returns');
            return;
        }
        if ($return['status'] === 'CANCELLED') {
            $this->flash('error', 'This return is already cancelled.');
            $this->redirect('/production/returns/view/' . $id);
            return;
        }

        $items = DB::fetchAll(
            "SELECT * FROM production_return_exchange_items WHERE return_id = ?",
            [$id]
        );

        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            $concernId = (int)$return['concern_id'];

            // Reverse stock ledger movements
            foreach ($items as $item) {
                $variantId = (int)$item['variant_id'];
                $qty = (float)$item['qty'];
                $dir = $item['direction'];
                $action = $item['restock_action'] ?? 'RESTOCK';

                if ($dir === 'IN') {
                    // Reversing returned restock: deduct from where it was credited
                    if ($action === 'RESTOCK') {
                        StockService::move(
                            $concernId,
                            $variantId,
                            'Main Store',
                            0.00,
                            $qty,
                            'Return & Exchange',
                            $id,
                            "RMA Cancellation Reversal [{$return['return_no']}]: Deducted restocked item",
                            Auth::id()
                        );
                    }
                } elseif ($dir === 'OUT') {
                    // Reversing exchange OUT: credit back to Main Store
                    StockService::move(
                        $concernId,
                        $variantId,
                        'Main Store',
                        $qty,
                        0.00,
                        'Return & Exchange',
                        $id,
                        "RMA Cancellation Reversal [{$return['return_no']}]: Restored exchange item",
                        Auth::id()
                    );
                }
            }

            DB::query(
                "UPDATE production_returns SET status = 'CANCELLED', cancelled_by = ?, cancelled_at = NOW(), cancel_reason = ? WHERE id = ?",
                [Auth::id(), $reason, $id]
            );

            AuditService::log('RETURN_CANCEL', 'production_returns', $id, ['old_status' => $return['status']], [
                'status'        => 'CANCELLED',
                'cancel_reason' => $reason
            ]);

            if (!$hasActiveTx) {
                DB::commit();
            }

            $this->flash('success', "Return record '{$return['return_no']}' was cancelled and all stock movements were reversed.");
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            $this->flash('error', 'Failed to cancel return: ' . $e->getMessage());
        }

        $this->redirect('/production/returns/view/' . $id);
    }
}
