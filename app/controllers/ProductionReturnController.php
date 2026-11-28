<?php
// app/controllers/ProductionReturnController.php - Reversible RMA with Real Exchanges & Multi-line Stock Ledger

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\ModuleGuard;
use App\Services\StockService;
use App\Services\DamageService;
use App\Services\DocumentNumberService;
use App\Services\AuditService;
use App\Services\ReturnExchangeSchema;
use App\Services\ExportService;
use Exception;

class ProductionReturnController extends Controller {
    public function index(): void {
        ModuleGuard::enforce('return_exchange', 'view');
        $cid = Auth::concernId();

        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $type = trim($_GET['type'] ?? '');
        $fromDate = trim($_GET['from_date'] ?? '');
        $toDate = trim($_GET['to_date'] ?? '');

        $sql = "SELECT pr.*, c.name AS customer_name, c.phone AS customer_phone,
                       con.name AS concern_name, con.code AS concern_code,
                       (SELECT COUNT(*) FROM production_return_exchange_items WHERE return_id = pr.id) AS item_count,
                       (SELECT GROUP_CONCAT(DISTINCT CONCAT(v.name, ' [', v.product_code, ']') ORDER BY v.name SEPARATOR ' | ')
                        FROM production_return_exchange_items prei
                        JOIN variants v ON v.id = prei.variant_id
                        WHERE prei.return_id = pr.id) AS product_codes,
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

    public function export(): void {
        ModuleGuard::enforce('return_exchange', 'view');
        $cid = Auth::concernId();
        $format = strtolower(trim((string)($_GET['format'] ?? 'excel')));
        $period = is_scalar($_GET['period'] ?? null) ? (string)$_GET['period'] : 'all';
        $singleDate = is_scalar($_GET['single_date'] ?? null) ? (string)$_GET['single_date'] : null;
        $fromDate = is_scalar($_GET['from_date'] ?? null) ? (string)$_GET['from_date'] : null;
        $toDate = is_scalar($_GET['to_date'] ?? null) ? (string)$_GET['to_date'] : null;
        $dateScope = ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT pr.*, c.name AS customer_name, c.phone AS customer_phone,
                       con.name AS concern_name, con.code AS concern_code,
                       (SELECT COUNT(*) FROM production_return_exchange_items WHERE return_id = pr.id) AS item_count,
                       (SELECT GROUP_CONCAT(DISTINCT CONCAT(v.name, ' [', v.product_code, ']') ORDER BY v.name SEPARATOR ' | ')
                        FROM production_return_exchange_items prei
                        JOIN variants v ON v.id = prei.variant_id
                        WHERE prei.return_id = pr.id) AS product_codes,
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
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $sql .= " AND DATE(COALESCE(pr.return_date, pr.created_at)) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        $sql .= " ORDER BY pr.id DESC";
        $returns = DB::fetchAll($sql, $params);

        $headers = [
            'Return No', 'Date', 'Type', 'Status', 'Customer', 'Phone',
            'Items Count', 'Products / Codes', 'Return Total (INR)', 'Exchange Total (INR)',
            'Net Balance (INR)', 'Reason', 'Concern'
        ];

        $rows = [];
        $totRetAmt = 0; $totExchAmt = 0; $totNet = 0;
        foreach ($returns as $r) {
            $retAmt = (float)($r['return_total'] ?? 0);
            $exchAmt = (float)($r['exchange_total'] ?? 0);
            $net = (float)($r['net_amount'] ?? ($retAmt - $exchAmt));
            $totRetAmt += $retAmt;
            $totExchAmt += $exchAmt;
            $totNet += $net;

            $rows[] = [
                $r['return_no'],
                $r['return_date'] ? date('d M Y', strtotime($r['return_date'])) : date('d M Y', strtotime($r['created_at'])),
                strtoupper($r['type'] ?? 'RETURN'),
                strtoupper($r['status'] ?? 'COMPLETED'),
                $r['customer_name'] ?: 'Counter Walk-in',
                $r['customer_phone'] ?: '-',
                (int)($r['item_count'] ?? 0),
                $r['product_codes'] ?: '-',
                number_format($retAmt, 2),
                number_format($exchAmt, 2),
                number_format($net, 2),
                $r['reason'] ?: '-',
                $r['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total RMAs: ' . count($returns), '', '', '', '', '', '', 'Totals:', number_format($totRetAmt, 2), number_format($totExchAmt, 2), number_format($totNet, 2), '', ''];
        $metadata = [
            'Report Title' => 'Returns & Exchange (RMA) Register Report',
            'Scope / Period' => $dateScope['label'] ?? 'All Time',
            'Generated At' => date('d M Y, h:i A'),
            'Total RMAs' => count($returns),
            'Concern' => $cid ? ($returns[0]['concern_name'] ?? 'Concern #' . $cid) : 'All Concerns'
        ];

        $filename = 'returns_rma_' . date('Ymd_His');

        if ($format === 'pdf') {
            ExportService::renderPdfView('Returns & Exchange (RMA) Register Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            ExportService::downloadExcel($filename, 'Returns & Exchange (RMA) Register Report', $metadata, $headers, $rows, $totals);
        }
    }

    public function update(): void {
        ModuleGuard::enforce('return_exchange', 'edit');
        $this->validateCsrf();

        if (($_POST['source_workflow'] ?? '') === 'shop' && array_key_exists('in_items', $_POST)) {
            $this->updateShopReturnTransaction();
            return;
        }

        $this->updateReturnMetadata();
    }

    private function updateReturnMetadata(): void {
        $id = (int)($_POST['id'] ?? 0);
        $cid = Auth::concernId() ?: Auth::superAdminSelectedConcernId();
        $returnDate = trim((string)($_POST['return_date'] ?? ''));
        $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $returnDate);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $returnDate) {
            $this->flash('error', 'Enter a valid return date.');
            $this->redirect('/production/returns');
            return;
        }

        $reason = trim((string)($_POST['reason'] ?? ''));
        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            $return = DB::fetch(
                "SELECT id, concern_id, return_no, status, return_date, reason
                 FROM production_returns
                 WHERE id = ? AND (concern_id = ? OR ? IS NULL)
                 LIMIT 1 FOR UPDATE",
                [$id, $cid, $cid]
            );
            if (!$return) {
                throw new \RuntimeException('Return record not found.');
            }
            if ($return['status'] === 'CANCELLED') {
                throw new \RuntimeException('A cancelled return cannot be edited.');
            }

            DB::query(
                "UPDATE production_returns SET return_date = ?, reason = ? WHERE id = ?",
                [$returnDate, $reason, $id]
            );
            AuditService::log('RETURN_UPDATE', 'production_returns', $id, [
                'return_date' => $return['return_date'],
                'reason' => $return['reason'],
            ], [
                'return_date' => $returnDate,
                'reason' => $reason,
            ]);

            $legacyReturns = DB::fetchAll(
                "SELECT id, return_date, reason FROM returns_exchanges
                 WHERE return_no = ? AND concern_id = ?
                 FOR UPDATE",
                [$return['return_no'], $return['concern_id']]
            );
            foreach ($legacyReturns as $legacyReturn) {
                DB::query(
                    "UPDATE returns_exchanges SET return_date = ?, reason = ? WHERE id = ?",
                    [$returnDate, $reason, $legacyReturn['id']]
                );
                AuditService::log('RETURN_UPDATE', 'returns_exchanges', (int)$legacyReturn['id'], [
                    'return_date' => $legacyReturn['return_date'],
                    'reason' => $legacyReturn['reason'],
                ], [
                    'return_date' => $returnDate,
                    'reason' => $reason,
                ]);
            }

            if (!$hasActiveTx) {
                DB::commit();
            }
            $this->flash('success', "Return/Exchange '{$return['return_no']}' updated successfully.");
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            $this->flash('error', 'Failed to update return: ' . $e->getMessage());
        }

        $this->redirect('/production/returns');
    }

    private function updateShopReturnTransaction(): void {
        ReturnExchangeSchema::ensure();
        $id = (int)($_POST['id'] ?? 0);
        $cid = Auth::concernId() ?: Auth::superAdminSelectedConcernId();
        $salesInvoiceId = (int)($_POST['sales_invoice_id'] ?? 0);
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $type = strtoupper(trim((string)($_POST['type'] ?? '')));
        $returnDate = trim((string)($_POST['return_date'] ?? ''));
        $settlementMode = trim((string)($_POST['settlement_mode'] ?? ''));
        $settlementReference = trim((string)($_POST['settlement_reference'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));
        $photoUrl = trim((string)($_POST['photo_url'] ?? ''));
        $inItems = json_decode((string)($_POST['in_items'] ?? ''), true);
        $outItems = json_decode((string)($_POST['out_items'] ?? ''), true);
        $inItems = is_array($inItems) ? $inItems : [];
        $outItems = is_array($outItems) ? $outItems : [];
        $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $returnDate);

        if ($id <= 0 || $salesInvoiceId <= 0 || $customerId <= 0) {
            $this->flash('error', 'A valid RMA, invoice, and customer are required to edit this transaction.');
            $this->redirect('/m/return_exchange');
            return;
        }
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $returnDate) {
            $this->flash('error', 'Enter a valid return date.');
            $this->redirect('/m/return_exchange');
            return;
        }
        if (!in_array($type, ['RETURN', 'EXCHANGE'], true)) {
            $this->flash('error', 'Choose a valid return or exchange type.');
            $this->redirect('/m/return_exchange');
            return;
        }
        if (!in_array($settlementMode, ['Cash', 'UPI / QR', 'Card / POS', 'Cash + UPI', 'Exchange Adjustment'], true)) {
            $this->flash('error', 'Choose a valid refund or exchange settlement method.');
            $this->redirect('/m/return_exchange');
            return;
        }
        if ($settlementReference !== '' && strlen($settlementReference) > 100) {
            $this->flash('error', 'Settlement reference cannot exceed 100 characters.');
            $this->redirect('/m/return_exchange');
            return;
        }
        if ($photoUrl !== '' && (!filter_var($photoUrl, FILTER_VALIDATE_URL) || strlen($photoUrl) > 255)) {
            $this->flash('error', 'Enter a valid photo URL no longer than 255 characters.');
            $this->redirect('/m/return_exchange');
            return;
        }
        if ($reason === '' || empty($inItems)) {
            $this->flash('error', 'Enter a return reason and select at least one invoice item.');
            $this->redirect('/m/return_exchange');
            return;
        }
        if ($type === 'RETURN' && !empty($outItems)) {
            $this->flash('error', 'Replacement items can only be included in an exchange.');
            $this->redirect('/m/return_exchange');
            return;
        }
        if ($type === 'EXCHANGE' && empty($outItems)) {
            $this->flash('error', 'Add at least one replacement product for this exchange.');
            $this->redirect('/m/return_exchange');
            return;
        }

        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            $return = DB::fetch(
                "SELECT * FROM production_returns
                 WHERE id = ? AND (concern_id = ? OR ? IS NULL)
                 LIMIT 1 FOR UPDATE",
                [$id, $cid, $cid]
            );
            if (!$return) {
                throw new \RuntimeException('Return record not found for this concern.');
            }
            if ($return['status'] !== 'COMPLETED') {
                throw new \RuntimeException('Only completed returns can be fully edited.');
            }
            if (empty($return['sales_invoice_id'])) {
                throw new \RuntimeException('This full edit form is only available for invoice-linked returns.');
            }

            $concernId = (int)$return['concern_id'];
            $invoice = DB::fetch(
                "SELECT id, concern_id, customer_id, total_amount, tax_amount
                 FROM sales_invoices
                 WHERE id = ? AND concern_id = ?
                 LIMIT 1 FOR UPDATE",
                [$salesInvoiceId, $concernId]
            );
            if (!$invoice) {
                throw new \RuntimeException('The selected invoice does not belong to this concern.');
            }
            if ((int)$invoice['customer_id'] !== $customerId) {
                throw new \RuntimeException('The selected customer does not match the invoice.');
            }
            if ((int)$return['sales_invoice_id'] !== $salesInvoiceId) {
                $usedProductionReturn = DB::fetch(
                    "SELECT id FROM production_returns
                     WHERE sales_invoice_id = ? AND concern_id = ? AND id <> ? AND status <> 'CANCELLED'
                     LIMIT 1 FOR UPDATE",
                    [$salesInvoiceId, $concernId, $id]
                );
                $usedLegacyReturn = DB::fetch(
                    "SELECT re.id
                     FROM returns_exchanges re
                     LEFT JOIN production_returns linked_pr
                       ON linked_pr.return_no = re.return_no AND linked_pr.concern_id = re.concern_id
                     WHERE re.sales_invoice_id = ? AND re.concern_id = ? AND re.return_no <> ?
                       AND (linked_pr.id IS NULL OR linked_pr.status <> 'CANCELLED')
                     LIMIT 1 FOR UPDATE",
                    [$salesInvoiceId, $concernId, $return['return_no']]
                );
                if ($usedProductionReturn || $usedLegacyReturn) {
                    throw new \RuntimeException('The selected invoice is already linked to another active return or exchange.');
                }
            }

            $invoiceItems = DB::fetchAll(
                "SELECT id, variant_id, qty, total_price
                 FROM sales_invoice_items
                 WHERE sales_invoice_id = ?
                 ORDER BY id ASC
                 FOR UPDATE",
                [$salesInvoiceId]
            );
            $invoiceItemsById = [];
            foreach ($invoiceItems as $invoiceItem) {
                $invoiceItemsById[(int)$invoiceItem['id']] = $invoiceItem;
            }

            $seenInvoiceItems = [];
            $validatedInItems = [];
            foreach ($inItems as $item) {
                $invoiceItemId = (int)($item['invoice_item_id'] ?? 0);
                $qty = $item['qty'] ?? null;
                if ($invoiceItemId <= 0 || !isset($invoiceItemsById[$invoiceItemId]) || !is_numeric($qty) || (float)$qty <= 0) {
                    throw new \RuntimeException('A returned item is not valid for the selected invoice.');
                }
                if (isset($seenInvoiceItems[$invoiceItemId])) {
                    throw new \RuntimeException('Each invoice item can only be listed once in a return.');
                }
                $seenInvoiceItems[$invoiceItemId] = true;
                $invoiceItem = $invoiceItemsById[$invoiceItemId];
                $soldQty = (float)$invoiceItem['qty'];
                if ($soldQty <= 0 || (float)$qty > $soldQty) {
                    throw new \RuntimeException('Returned quantity cannot exceed the quantity sold.');
                }
                $condition = trim((string)($item['condition_state'] ?? ''));
                if (!in_array($condition, ['Good (Restock)', 'Damaged (Do Not Restock)'], true)) {
                    throw new \RuntimeException('Choose a valid returned-product condition.');
                }
                $taxableValue = (float)$invoiceItem['total_price'];
                $taxShare = (float)$invoice['total_amount'] > 0
                    ? (float)$invoice['tax_amount'] * ($taxableValue / (float)$invoice['total_amount'])
                    : 0.0;
                $validatedInItems[] = [
                    'invoice_item_id' => $invoiceItemId,
                    'variant_id' => (int)$invoiceItem['variant_id'],
                    'qty' => (float)$qty,
                    'unit_price' => round(($taxableValue + $taxShare) / $soldQty, 2),
                    'condition_state' => $condition,
                    'action' => $condition === 'Good (Restock)' ? 'RESTOCK' : 'SCRAP',
                    'scanned_codes' => $item['scanned_codes'] ?? [],
                ];
            }
            $inItems = $validatedInItems;

            $outItems = $type === 'EXCHANGE' ? $outItems : [];
            $oldItems = DB::fetchAll(
                "SELECT * FROM production_return_exchange_items
                 WHERE return_id = ? ORDER BY id ASC FOR UPDATE",
                [$id]
            );
            if (!$oldItems) {
                throw new \RuntimeException('Return item history is missing; the RMA cannot be safely edited.');
            }
            $oldScrapCount = count(array_filter(
                $oldItems,
                static fn($item) => $item['direction'] === 'IN' && ($item['restock_action'] ?? '') === 'SCRAP'
            ));
            if ($oldScrapCount > 0) {
                $damageRows = DB::fetchAll(
                    "SELECT id, status
                     FROM concern_damages
                     WHERE concern_id = ? AND severity = 'SCRAP' AND status <> 'CANCELLED'
                       AND LEFT(reason, LENGTH(?)) = ?
                     ORDER BY id ASC FOR UPDATE",
                    [
                        $concernId,
                        'RMA Scrapped Return [' . $return['return_no'] . ']:',
                        'RMA Scrapped Return [' . $return['return_no'] . ']:',
                    ]
                );
                if (count($damageRows) !== $oldScrapCount) {
                    throw new \RuntimeException('The damaged-item history is incomplete; the RMA cannot be safely edited.');
                }
                foreach ($damageRows as $damageRow) {
                    DamageService::cancel(
                        (int)$damageRow['id'],
                        "RMA {$return['return_no']} edit reversal",
                        Auth::id(),
                        'CONCERN'
                    );
                }
            }

            $movements = DB::fetchAll(
                "SELECT variant_id, location, qty_in, qty_out
                 FROM stock_ledger
                 WHERE concern_id = ? AND ref_module = 'Return & Exchange' AND ref_id = ?
                   AND (note LIKE ? OR note = ?)
                 ORDER BY id DESC FOR UPDATE",
                [
                    $concernId,
                    $id,
                    '%[' . $return['return_no'] . ']%',
                    'Exchange delivery for RMA ' . $return['return_no'],
                ]
            );
            if (!$movements) {
                throw new \RuntimeException('Original stock ledger movements were not found; no RMA values were changed.');
            }
            foreach ($movements as $movement) {
                $qtyIn = (float)$movement['qty_in'];
                $qtyOut = (float)$movement['qty_out'];
                if ($qtyIn <= 0 && $qtyOut <= 0) {
                    continue;
                }
                StockService::move(
                    $concernId,
                    (int)$movement['variant_id'],
                    (string)$movement['location'],
                    $qtyOut,
                    $qtyIn,
                    'Return & Exchange',
                    $id,
                    "RMA Edit Reversal [{$return['return_no']}]: {$reason}",
                    Auth::id()
                );
            }

            $legacyReturns = DB::fetchAll(
                "SELECT id FROM returns_exchanges WHERE return_no = ? AND concern_id = ? FOR UPDATE",
                [$return['return_no'], $concernId]
            );
            if (count($legacyReturns) > 1) {
                throw new \RuntimeException('Duplicate legacy return records were found; no changes were made.');
            }
            foreach ($legacyReturns as $legacyReturn) {
                DB::query("DELETE FROM return_exchange_items WHERE return_id = ?", [$legacyReturn['id']]);
            }
            DB::query("DELETE FROM production_return_exchange_items WHERE return_id = ?", [$id]);

            $validatedOutItems = [];
            $requestedOutQtyByVariant = [];
            foreach ($outItems as $item) {
                $variantId = (int)($item['variant_id'] ?? 0);
                $qty = $item['qty'] ?? null;
                if ($variantId <= 0 || !is_numeric($qty) || (float)$qty <= 0) {
                    throw new \RuntimeException('A replacement product or quantity is invalid.');
                }
                $variant = DB::fetch(
                    "SELECT id, name, price FROM variants
                     WHERE id = ? AND concern_id = ? AND status = 'active'
                     LIMIT 1 FOR UPDATE",
                    [$variantId, $concernId]
                );
                if (!$variant) {
                    throw new \RuntimeException('A replacement product is not active in this shop.');
                }
                $requestedOutQtyByVariant[$variantId] = ($requestedOutQtyByVariant[$variantId] ?? 0) + (float)$qty;
                $stockRows = DB::fetchAll(
                    "SELECT qty FROM stocks
                     WHERE concern_id = ? AND variant_id = ?
                       AND location IN ('Main Store', 'Main Store (WH-01)')
                     FOR UPDATE",
                    [$concernId, $variantId]
                );
                $availableQty = 0.0;
                foreach ($stockRows as $stockRow) {
                    $availableQty += max(0.0, (float)$stockRow['qty']);
                }
                if ($requestedOutQtyByVariant[$variantId] > $availableQty) {
                    throw new \RuntimeException(
                        "Replacement product '{$variant['name']}' has {$availableQty} good units available; requested {$requestedOutQtyByVariant[$variantId]}."
                    );
                }
                $validatedOutItems[] = [
                    'variant_id' => $variantId,
                    'qty' => (float)$qty,
                    'unit_price' => (float)$variant['price'],
                    'scanned_codes' => $item['scanned_codes'] ?? [],
                ];
            }
            $outItems = $validatedOutItems;

            $totalInAmount = 0.0;
            $totalOutAmount = 0.0;
            foreach ($inItems as $item) {
                $totalInAmount += (float)$item['qty'] * (float)$item['unit_price'];
            }
            foreach ($outItems as $item) {
                $totalOutAmount += (float)$item['qty'] * (float)$item['unit_price'];
            }
            $priceDifference = $totalOutAmount - $totalInAmount;
            $refundAmount = $priceDifference < 0 ? abs($priceDifference) : 0.0;
            $extraCharge = $priceDifference > 0 ? $priceDifference : 0.0;

            DB::query(
                "UPDATE production_returns
                 SET type = ?, customer_id = ?, sales_invoice_id = ?, return_date = ?,
                     settlement_mode = ?, settlement_reference = ?, price_difference = ?,
                     refund_amount = ?, extra_charge = ?, reason = ?, photo_url = ?
                 WHERE id = ? AND concern_id = ?",
                [
                    $type, $customerId, $salesInvoiceId, $returnDate, $settlementMode,
                    $settlementReference !== '' ? $settlementReference : null,
                    $priceDifference, $refundAmount, $extraCharge, $reason,
                    $photoUrl !== '' ? $photoUrl : null, $id, $concernId,
                ]
            );

            $firstIn = $inItems[0];
            $firstOut = $outItems[0] ?? null;
            if ($legacyReturns) {
                $legacyReturnId = (int)$legacyReturns[0]['id'];
                DB::query(
                    "UPDATE returns_exchanges
                     SET type = ?, customer_id = ?, sales_invoice_id = ?, return_date = ?,
                         returned_variant_id = ?, returned_qty = ?, exchange_variant_id = ?, exchange_qty = ?, reason = ?
                     WHERE id = ? AND concern_id = ?",
                    [
                        strtolower($type), $customerId, $salesInvoiceId, $returnDate,
                        (int)$firstIn['variant_id'], (float)$firstIn['qty'],
                        $firstOut ? (int)$firstOut['variant_id'] : null,
                        $firstOut ? (float)$firstOut['qty'] : null,
                        $reason, $legacyReturnId, $concernId,
                    ]
                );
            } else {
                DB::query(
                    "INSERT INTO returns_exchanges (
                        concern_id, return_no, type, customer_id, sales_invoice_id, return_date,
                        returned_variant_id, returned_qty, exchange_variant_id, exchange_qty,
                        reason, created_by, created_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $concernId, $return['return_no'], strtolower($type), $customerId, $salesInvoiceId,
                        $returnDate, (int)$firstIn['variant_id'], (float)$firstIn['qty'],
                        $firstOut ? (int)$firstOut['variant_id'] : null,
                        $firstOut ? (float)$firstOut['qty'] : null,
                        $reason, Auth::id(),
                    ]
                );
                $legacyReturnId = (int)DB::lastInsertId();
            }

            foreach ($inItems as $item) {
                $qty = (float)$item['qty'];
                $price = (float)$item['unit_price'];
                $scannedCodesJson = $this->encodeScannedCodes($item['scanned_codes'] ?? []);
                $action = $item['action'];
                $total = $qty * $price;
                DB::query(
                    "INSERT INTO production_return_exchange_items (
                        return_id, direction, variant_id, sales_invoice_item_id, tag_code, scanned_codes,
                        qty, unit_price, total_amount, condition_state, restock_action, created_at
                     ) VALUES (?, 'IN', ?, ?, '', ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $id, (int)$item['variant_id'], (int)$item['invoice_item_id'], $scannedCodesJson,
                        $qty, $price, $total, $item['condition_state'], $action,
                    ]
                );
                DB::query(
                    "INSERT INTO return_exchange_items (
                        return_id, direction, variant_id, qty, unit_price, total_amount, condition_state, restock_action, created_at
                     ) VALUES (?, 'IN', ?, ?, ?, ?, ?, ?, NOW())",
                    [$legacyReturnId, (int)$item['variant_id'], $qty, $price, $total, $item['condition_state'], $action]
                );

                if ($action === 'RESTOCK') {
                    StockService::move($concernId, (int)$item['variant_id'], 'Main Store', $qty, 0.0, 'Return & Exchange', $id, "RMA Restock [{$return['return_no']}]: {$reason}", Auth::id());
                } else {
                    StockService::move($concernId, (int)$item['variant_id'], 'Main Store', $qty, 0.0, 'Return & Exchange', $id, "RMA Damaged Return Received [{$return['return_no']}]: {$reason}", Auth::id());
                    DamageService::log(
                        $concernId,
                        (int)$item['variant_id'],
                        'Main Store',
                        'Main Store',
                        $qty,
                        "RMA Scrapped Return [{$return['return_no']}]: {$reason}",
                        "Logged from RMA {$return['return_no']}",
                        Auth::id(),
                        $photoUrl !== '' ? $photoUrl : null,
                        'SCRAP',
                        'CONCERN',
                        true
                    );
                }
            }

            foreach ($outItems as $item) {
                $variantId = (int)$item['variant_id'];
                $qty = (float)$item['qty'];
                $price = (float)$item['unit_price'];
                $scannedCodesJson = $this->encodeScannedCodes($item['scanned_codes'] ?? []);
                $total = $qty * $price;
                DB::query(
                    "INSERT INTO production_return_exchange_items (
                        return_id, direction, variant_id, tag_code, scanned_codes, qty, unit_price, total_amount, created_at
                     ) VALUES (?, 'OUT', ?, '', ?, ?, ?, ?, NOW())",
                    [$id, $variantId, $scannedCodesJson, $qty, $price, $total]
                );
                DB::query(
                    "INSERT INTO return_exchange_items (
                        return_id, direction, variant_id, qty, unit_price, total_amount, created_at
                     ) VALUES (?, 'OUT', ?, ?, ?, ?, NOW())",
                    [$legacyReturnId, $variantId, $qty, $price, $total]
                );

                $qtyRemaining = $qty;
                foreach (['Main Store', 'Main Store (WH-01)'] as $stockLocation) {
                    if ($qtyRemaining <= 0.00001) {
                        break;
                    }
                    $locationQty = (float)DB::fetchValue(
                        "SELECT qty FROM stocks
                         WHERE concern_id = ? AND variant_id = ? AND location = ?
                         FOR UPDATE",
                        [$concernId, $variantId, $stockLocation]
                    );
                    $qtyToMove = min($qtyRemaining, max(0.0, $locationQty));
                    if ($qtyToMove <= 0) {
                        continue;
                    }
                    StockService::move(
                        $concernId, $variantId, $stockLocation, 0.0, $qtyToMove,
                        'Return & Exchange', $id, "Exchange delivery for RMA {$return['return_no']}", Auth::id()
                    );
                    $qtyRemaining -= $qtyToMove;
                }
                if ($qtyRemaining > 0.00001) {
                    throw new \RuntimeException("Insufficient good shop stock for replacement product #{$variantId}.");
                }
            }

            AuditService::log('RETURN_UPDATE', 'production_returns', $id, [
                'return_date' => $return['return_date'],
                'reason' => $return['reason'],
                'type' => $return['type'],
                'sales_invoice_id' => $return['sales_invoice_id'],
                'settlement_mode' => $return['settlement_mode'],
                'settlement_reference' => $return['settlement_reference'],
                'price_difference' => $return['price_difference'],
                'refund_amount' => $return['refund_amount'],
                'extra_charge' => $return['extra_charge'],
                'items' => $oldItems,
            ], [
                'return_date' => $returnDate,
                'reason' => $reason,
                'type' => $type,
                'sales_invoice_id' => $salesInvoiceId,
                'settlement_mode' => $settlementMode,
                'settlement_reference' => $settlementReference,
                'price_difference' => $priceDifference,
                'refund_amount' => $refundAmount,
                'extra_charge' => $extraCharge,
                'items' => array_merge(
                    array_map(static fn($item) => array_merge($item, ['direction' => 'IN']), $inItems),
                    array_map(static fn($item) => array_merge($item, ['direction' => 'OUT']), $outItems)
                ),
            ]);

            if (!$hasActiveTx) {
                DB::commit();
            }
            $this->flash('success', "Return/Exchange '{$return['return_no']}' was updated and its stock ledger rebalanced.");
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            $this->flash('error', 'Failed to update return: ' . $e->getMessage());
        }

        $this->redirect('/m/return_exchange');
    }

    public function create(): void {
        ModuleGuard::enforce('return_exchange', 'add');
        $cid = Auth::concernId();

        $customers = DB::fetchAll("SELECT id, name, phone FROM customers WHERE (concern_id = ? OR ? IS NULL) AND status = 'active' ORDER BY name ASC", [$cid, $cid]);
        $variants = DB::fetchAll("SELECT id, product_code, name, size, price FROM variants WHERE (concern_id = ? OR ? IS NULL) AND status = 'active' ORDER BY name ASC", [$cid, $cid]);

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
            "SELECT sii.*, v.product_code, v.name AS variant_name, v.size 
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
        ModuleGuard::enforce('return_exchange', 'add');
        $this->validateCsrf();
        ReturnExchangeSchema::ensure();
        $shopWorkflow = ($_POST['source_workflow'] ?? '') === 'shop';
        $errorRedirect = $shopWorkflow ? '/m/return_exchange' : '/production/returns/create';
        $cid = Auth::concernId() ?: Auth::superAdminSelectedConcernId() ?: (int)($_POST['concern_id'] ?? 0);
        if (!$cid) {
            $this->flash('error', 'Please select a valid business concern.');
            $this->redirect($shopWorkflow ? '/m/return_exchange' : '/production/returns');
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
        $inItems = is_array($inItems) ? $inItems : [];
        $outItems = is_array($outItems) ? $outItems : [];

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
            $this->redirect($errorRedirect);
            return;
        }
        if ($shopWorkflow && (!$salesInvoiceId || !$customerId || !in_array($type, ['RETURN', 'EXCHANGE'], true))) {
            $this->flash('error', 'Choose an unused customer invoice and a valid return or exchange type.');
            $this->redirect($errorRedirect);
            return;
        }
        if ($shopWorkflow && $reason === '') {
            $this->flash('error', 'Enter a reason for the customer return or exchange.');
            $this->redirect($errorRedirect);
            return;
        }

        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            if ($salesInvoiceId) {
                $invoice = DB::fetch(
                    "SELECT id, concern_id, customer_id, total_amount, tax_amount
                     FROM sales_invoices
                     WHERE id = ? AND concern_id = ?
                     LIMIT 1 FOR UPDATE",
                    [$salesInvoiceId, $cid]
                );
                if (!$invoice) {
                    throw new \RuntimeException('The selected invoice does not belong to this concern.');
                }
                if ($customerId !== null && (int)$invoice['customer_id'] !== $customerId) {
                    throw new \RuntimeException('The selected customer does not match the invoice.');
                }
                $usedReturn = DB::fetch(
                    "SELECT re.id
                     FROM returns_exchanges re
                     LEFT JOIN production_returns linked_pr
                       ON linked_pr.return_no = re.return_no AND linked_pr.concern_id = re.concern_id
                     WHERE re.sales_invoice_id = ?
                       AND (linked_pr.id IS NULL OR linked_pr.status <> 'CANCELLED')
                     LIMIT 1 FOR UPDATE",
                    [$salesInvoiceId]
                );
                $usedProductionReturn = DB::fetch(
                    "SELECT id FROM production_returns
                     WHERE sales_invoice_id = ? AND status <> 'CANCELLED'
                     LIMIT 1 FOR UPDATE",
                    [$salesInvoiceId]
                );
                if ($usedReturn || $usedProductionReturn) {
                    throw new \RuntimeException('This invoice has already been used for a return or exchange.');
                }

                if ($shopWorkflow) {
                    if (!in_array($settlementMode, ['Cash', 'UPI / QR', 'Card / POS', 'Cash + UPI', 'Exchange Adjustment'], true)) {
                        throw new \RuntimeException('Choose a valid refund or exchange settlement method.');
                    }
                    $invoiceItems = DB::fetchAll(
                        "SELECT id, variant_id, qty, total_price
                         FROM sales_invoice_items
                         WHERE sales_invoice_id = ?
                         ORDER BY id ASC
                         FOR UPDATE",
                        [$salesInvoiceId]
                    );
                    $itemsById = [];
                    foreach ($invoiceItems as $invoiceItem) {
                        $itemsById[(int)$invoiceItem['id']] = $invoiceItem;
                    }
                    $seenInvoiceItems = [];
                    $validatedInItems = [];
                    foreach ($inItems as $item) {
                        $invoiceItemId = (int)($item['invoice_item_id'] ?? 0);
                        $qty = $item['qty'] ?? null;
                        if ($invoiceItemId <= 0 || !isset($itemsById[$invoiceItemId]) || !is_numeric($qty) || (float)$qty <= 0) {
                            throw new \RuntimeException('A returned item is not valid for the selected invoice.');
                        }
                        if (isset($seenInvoiceItems[$invoiceItemId])) {
                            throw new \RuntimeException('Each invoice item can only be listed once in a return.');
                        }
                        $seenInvoiceItems[$invoiceItemId] = true;
                        $invoiceItem = $itemsById[$invoiceItemId];
                        $soldQty = (float)$invoiceItem['qty'];
                        if ($soldQty <= 0 || (float)$qty > $soldQty) {
                            throw new \RuntimeException('Returned quantity cannot exceed the quantity sold.');
                        }
                        $taxableValue = (float)$invoiceItem['total_price'];
                        $taxShare = (float)$invoice['total_amount'] > 0
                            ? (float)$invoice['tax_amount'] * ($taxableValue / (float)$invoice['total_amount'])
                            : 0.0;
                        $item['variant_id'] = (int)$invoiceItem['variant_id'];
                        $item['unit_price'] = round(($taxableValue + $taxShare) / $soldQty, 2);
                        $condition = trim((string)($item['condition_state'] ?? 'Good (Restock)'));
                        if (!in_array($condition, ['Good (Restock)', 'Damaged (Do Not Restock)'], true)) {
                            throw new \RuntimeException('Choose a valid returned-product condition.');
                        }
                        $item['condition_state'] = $condition;
                        $item['action'] = $condition === 'Good (Restock)' ? 'RESTOCK' : 'SCRAP';
                        $item['invoice_item_id'] = $invoiceItemId;
                        $item['qty'] = (float)$qty;
                        $validatedInItems[] = $item;
                    }
                    $inItems = $validatedInItems;

                    if ($type === 'RETURN' && !empty($outItems)) {
                        throw new \RuntimeException('Replacement items can only be added to an exchange.');
                    }
                    if ($type === 'EXCHANGE' && empty($outItems)) {
                        throw new \RuntimeException('Add at least one replacement product for an exchange.');
                    }
                }
            }

            if ($shopWorkflow) {
                $validatedOutItems = [];
                $requestedOutQtyByVariant = [];
                foreach ($outItems as $item) {
                    $variantId = (int)($item['variant_id'] ?? 0);
                    $qty = $item['qty'] ?? null;
                    if ($variantId <= 0 || !is_numeric($qty) || (float)$qty <= 0) {
                        throw new \RuntimeException('A replacement product or quantity is invalid.');
                    }
                    $variant = DB::fetch(
                        "SELECT id, name, price FROM variants WHERE id = ? AND concern_id = ? AND status = 'active' LIMIT 1",
                        [$variantId, $cid]
                    );
                    if (!$variant) {
                        throw new \RuntimeException('A replacement product is not active in this shop.');
                    }
                    $requestedOutQtyByVariant[$variantId] = ($requestedOutQtyByVariant[$variantId] ?? 0) + (float)$qty;
                    $stockRows = DB::fetchAll(
                        "SELECT qty FROM stocks
                         WHERE concern_id = ? AND variant_id = ?
                           AND location IN ('Main Store', 'Main Store (WH-01)')
                         FOR UPDATE",
                        [$cid, $variantId]
                    );
                    $availableQty = 0.0;
                    foreach ($stockRows as $stockRow) {
                        $availableQty += max(0.0, (float)$stockRow['qty']);
                    }
                    if ($availableQty <= 0 || $requestedOutQtyByVariant[$variantId] > $availableQty) {
                        throw new \RuntimeException(
                            "Replacement product '{$variant['name']}' has {$availableQty} good units available in shop stock; requested {$requestedOutQtyByVariant[$variantId]}."
                        );
                    }
                    $item['unit_price'] = (float)$variant['price'];
                    $item['qty'] = (float)$qty;
                    $validatedOutItems[] = $item;
                }
                $outItems = $validatedOutItems;
            }

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
                $scannedCodesJson = $this->encodeScannedCodes($item['scanned_codes'] ?? []);
                $condition = trim((string)($item['condition_state'] ?? 'Good'));
                $action = strtoupper(trim((string)($item['action'] ?? 'RESTOCK')));
                $tot = $qty * $price;

                if ($variantId <= 0 || $qty <= 0) continue;

                DB::query(
                    "INSERT INTO production_return_exchange_items (
                        return_id, direction, variant_id, sales_invoice_item_id, tag_code, scanned_codes, qty, unit_price, total_amount, condition_state, restock_action, created_at
                     ) VALUES (?, 'IN', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$returnId, $variantId, !empty($item['invoice_item_id']) ? (int)$item['invoice_item_id'] : null, $tagCode, $scannedCodesJson, $qty, $price, $tot, $condition, $action]
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
                    StockService::move(
                        $cid,
                        $variantId,
                        'Main Store',
                        $qty,
                        0.00,
                        'Return & Exchange',
                        $returnId,
                        "RMA Damaged Return Received [{$returnNo}]: {$reason}",
                        Auth::id()
                    );
                    DamageService::log(
                        $cid,
                        $variantId,
                        'Main Store',
                        'Main Store',
                        $qty,
                        "RMA Scrapped Return [{$returnNo}]: {$reason}",
                        "Logged from RMA {$returnNo}",
                        Auth::id(),
                        $photoUrl,
                        'SCRAP',
                        'CONCERN',
                        true
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
                    $scannedCodesJson = $this->encodeScannedCodes($item['scanned_codes'] ?? []);
                    $tot = $qty * $price;

                    if ($variantId <= 0 || $qty <= 0) continue;

                    DB::query(
                        "INSERT INTO production_return_exchange_items (
                            return_id, direction, variant_id, tag_code, scanned_codes, qty, unit_price, total_amount, created_at
                         ) VALUES (?, 'OUT', ?, ?, ?, ?, ?, ?, NOW())",
                        [$returnId, $variantId, $tagCode, $scannedCodesJson, $qty, $price, $tot]
                    );

                    // Deduct from live stock
                    $qtyRemaining = $qty;
                    foreach (['Main Store', 'Main Store (WH-01)'] as $stockLocation) {
                        if ($qtyRemaining <= 0.00001) {
                            break;
                        }
                        $locationQty = (float)DB::fetchValue(
                            "SELECT qty FROM stocks
                             WHERE concern_id = ? AND variant_id = ? AND location = ?
                             FOR UPDATE",
                            [$cid, $variantId, $stockLocation]
                        );
                        $qtyToMove = min($qtyRemaining, max(0.0, $locationQty));
                        if ($qtyToMove <= 0) {
                            continue;
                        }
                        StockService::move(
                            $cid,
                            $variantId,
                            $stockLocation,
                            0.00,
                            $qtyToMove,
                            'Return & Exchange',
                            $returnId,
                            "Exchange delivery for RMA {$returnNo}",
                            Auth::id()
                        );
                        $qtyRemaining -= $qtyToMove;
                    }
                    if ($qtyRemaining > 0.00001) {
                        throw new \RuntimeException("Insufficient good shop stock for replacement product #{$variantId}.");
                    }
                }
            }

            // Sync the invoice summary with the shop return register.
            $firstIn = $inItems[0] ?? null;
            $firstOut = $outItems[0] ?? null;
            $legacyReturnId = null;
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
                $legacyReturnId = (int)DB::lastInsertId();

                foreach ($inItems as $item) {
                    $qty = (float)$item['qty'];
                    $price = (float)$item['unit_price'];
                    DB::query(
                        "INSERT INTO return_exchange_items (
                            return_id, direction, variant_id, qty, unit_price, total_amount, condition_state, restock_action, created_at
                         ) VALUES (?, 'IN', ?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $legacyReturnId,
                            (int)$item['variant_id'],
                            $qty,
                            $price,
                            $qty * $price,
                            trim((string)($item['condition_state'] ?? 'Good')),
                            strtoupper(trim((string)($item['action'] ?? 'RESTOCK'))),
                        ]
                    );
                }
                foreach ($outItems as $item) {
                    $qty = (float)$item['qty'];
                    $price = (float)$item['unit_price'];
                    DB::query(
                        "INSERT INTO return_exchange_items (
                            return_id, direction, variant_id, qty, unit_price, total_amount, created_at
                         ) VALUES (?, 'OUT', ?, ?, ?, ?, NOW())",
                        [$legacyReturnId, (int)$item['variant_id'], $qty, $price, $qty * $price]
                    );
                }
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
            $this->redirect($shopWorkflow ? '/m/return_exchange' : '/production/returns/view/' . $returnId);
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            $this->flash('error', 'Failed to process return: ' . $e->getMessage());
            $this->redirect($errorRedirect);
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
            "SELECT prei.*, v.product_code, v.name AS variant_name, v.size 
             FROM production_return_exchange_items prei
             JOIN variants v ON prei.variant_id = v.id
             WHERE prei.return_id = ?
             ORDER BY prei.direction ASC, prei.id ASC",
            [$id]
        );
        $items = $this->withScannedCodeDisplay($items);

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
                    si.invoice_no, con.name AS concern_name, con.code AS concern_code, con.gstin, con.phone AS concern_phone, con.logo AS concern_logo,
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
            "SELECT prei.*, v.product_code, v.name AS variant_name, v.size 
             FROM production_return_exchange_items prei
             JOIN variants v ON prei.variant_id = v.id
             WHERE prei.return_id = ?
             ORDER BY prei.direction ASC, prei.id ASC",
            [$id]
        );
        $items = $this->withScannedCodeDisplay($items);

        $this->render('production/returns/print', [
            'pageTitle' => "Print Voucher {$return['return_no']}",
            'return'    => $return,
            'items'     => $items,
        ]);
    }

    public function cancel(): void {
        ModuleGuard::enforce('return_exchange', 'delete');
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim((string)($_POST['cancel_reason'] ?? '')) ?: 'Cancelled by administrator';
        $cid = Auth::concernId() ?: Auth::superAdminSelectedConcernId();
        $returnTo = (string)($_POST['return_to'] ?? '');
        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            $return = DB::fetch(
                "SELECT * FROM production_returns
                 WHERE id = ? AND (concern_id = ? OR ? IS NULL)
                 LIMIT 1 FOR UPDATE",
                [$id, $cid, $cid]
            );
            if (!$return) {
                throw new \RuntimeException('Return record not found.');
            }
            if ($return['status'] === 'CANCELLED') {
                throw new \RuntimeException('This return is already cancelled.');
            }

            $concernId = (int)$return['concern_id'];
            $items = DB::fetchAll(
                "SELECT * FROM production_return_exchange_items WHERE return_id = ? ORDER BY id ASC FOR UPDATE",
                [$id]
            );
            if (!$items) {
                throw new \RuntimeException('Return item history is missing; the RMA cannot be safely reversed.');
            }

            $scrapItemCount = 0;
            foreach ($items as $item) {
                if ($item['direction'] === 'IN' && ($item['restock_action'] ?? '') === 'SCRAP') {
                    $scrapItemCount++;
                }
            }
            if ($scrapItemCount > 0) {
                $damageRows = DB::fetchAll(
                    "SELECT id, status
                     FROM concern_damages
                     WHERE concern_id = ? AND severity = 'SCRAP' AND status <> 'CANCELLED'
                       AND LEFT(reason, LENGTH(?)) = ?
                     ORDER BY id ASC
                     FOR UPDATE",
                    [
                        $concernId,
                        'RMA Scrapped Return [' . $return['return_no'] . ']:',
                        'RMA Scrapped Return [' . $return['return_no'] . ']:',
                    ]
                );
                if (count($damageRows) !== $scrapItemCount) {
                    throw new \RuntimeException('The damaged-item quarantine history is incomplete; no stock was changed.');
                }
                foreach ($damageRows as $damageRow) {
                    if ($damageRow['status'] !== 'CANCELLED') {
                        DamageService::cancel(
                            (int)$damageRow['id'],
                            "RMA {$return['return_no']} cancellation: {$reason}",
                            Auth::id(),
                            'CONCERN'
                        );
                    }
                }
            }

            $movements = DB::fetchAll(
                "SELECT variant_id, location, qty_in, qty_out
                 FROM stock_ledger
                 WHERE concern_id = ? AND ref_module = 'Return & Exchange' AND ref_id = ?
                   AND (note LIKE ? OR note = ?)
                 ORDER BY id DESC
                 FOR UPDATE",
                [
                    $concernId,
                    $id,
                    '%[' . $return['return_no'] . ']%',
                    'Exchange delivery for RMA ' . $return['return_no'],
                ]
            );
            if (!$movements) {
                throw new \RuntimeException('Original stock ledger movements were not found; the RMA cannot be safely reversed.');
            }
            foreach ($movements as $movement) {
                $qtyIn = (float)$movement['qty_in'];
                $qtyOut = (float)$movement['qty_out'];
                if ($qtyIn <= 0 && $qtyOut <= 0) {
                    continue;
                }
                StockService::move(
                    $concernId,
                    (int)$movement['variant_id'],
                    (string)$movement['location'],
                    $qtyOut,
                    $qtyIn,
                    'Return & Exchange',
                    $id,
                    "RMA Cancellation Reversal [{$return['return_no']}]: {$reason}",
                    Auth::id()
                );
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

        $returnPath = match ($returnTo) {
            'return_exchange' => '/m/return_exchange',
            'production_returns' => '/production/returns',
            default => '/production/returns/view/' . $id,
        };
        $this->redirect($returnPath);
    }

    private function encodeScannedCodes($rawScannedCodes): ?string {
        if (!is_array($rawScannedCodes)) {
            throw new \RuntimeException('Scanned-code details are invalid.');
        }

        $scannedCodes = [];
        foreach ($rawScannedCodes as $rawScannedCode) {
            if (!is_string($rawScannedCode) || trim($rawScannedCode) === '' || strlen($rawScannedCode) > 255) {
                throw new \RuntimeException('Each scanned code must contain between 1 and 255 characters.');
            }
            $scannedCodes[] = $rawScannedCode;
        }
        if (!$scannedCodes) {
            return null;
        }

        $encoded = json_encode($scannedCodes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            throw new \RuntimeException('Could not encode the scanned codes.');
        }
        if (strlen($encoded) > 65000) {
            throw new \RuntimeException('Scanned-code details are too large to store.');
        }
        return $encoded;
    }

    private function withScannedCodeDisplay(array $items): array {
        foreach ($items as &$item) {
            $scannedCodes = json_decode((string)($item['scanned_codes'] ?? ''), true);
            $displayCodes = [];
            if (is_array($scannedCodes)) {
                foreach ($scannedCodes as $scannedCode) {
                    if (is_string($scannedCode) && trim($scannedCode) !== '') {
                        $displayCodes[] = $scannedCode;
                    }
                }
            }
            if (!$displayCodes && !empty($item['tag_code'])) {
                $displayCodes[] = (string)$item['tag_code'];
            }
            $item['scanned_code_display'] = implode(', ', $displayCodes);
        }
        unset($item);
        return $items;
    }
}
