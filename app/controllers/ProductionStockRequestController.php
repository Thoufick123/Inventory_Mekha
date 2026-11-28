<?php
// app/controllers/ProductionStockRequestController.php - Requisition State Machine & Multi-location Stock Movement

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\ModuleGuard;
use App\Services\StockService;
use App\Services\DocumentNumberService;
use App\Services\AuditService;
use App\Services\ExportService;
use Exception;

class ProductionStockRequestController extends Controller {
    /**
     * Allowed status transitions
     */
    private const ALLOWED_TRANSITIONS = [
        'PENDING'    => ['APPROVED', 'REJECTED', 'CANCELLED'],
        'APPROVED'   => ['DISPATCHED', 'REJECTED', 'CANCELLED'],
        'DISPATCHED' => ['IN_TRANSIT', 'RECEIVED', 'SHORTAGE', 'CANCELLED'],
        'IN_TRANSIT' => ['RECEIVED', 'SHORTAGE', 'CANCELLED'],
        'RECEIVED'   => [],
        'SHORTAGE'   => [],
        'REJECTED'   => [],
        'CANCELLED'  => [],
    ];

    public static function canTransition(string $current, string $next): bool {
        $current = strtoupper(trim($current));
        $next = strtoupper(trim($next));
        return in_array($next, self::ALLOWED_TRANSITIONS[$current] ?? [], true);
    }

    public function index(): void {
        ModuleGuard::enforce('request', 'view');
        $cid = Auth::concernId();
        $tab = strtolower(trim($_GET['tab'] ?? 'receive'));
        if (!in_array($tab, ['receive', 'send'], true)) {
            $tab = 'receive';
        }

        $search = trim($_GET['search'] ?? '');
        $status = strtoupper(trim($_GET['status'] ?? ''));
        $fromDate = trim($_GET['from_date'] ?? '');
        $toDate = trim($_GET['to_date'] ?? '');

        // 1. Fetch active destination concerns, excluding the current source concern.
        $concerns = DB::fetchAll(
            "SELECT id, name, code FROM concerns
             WHERE status = 'active' AND (? IS NULL OR id <> ?)
             ORDER BY name ASC",
            [$cid, $cid]
        );

        // 2. Fetch active variants that have stock in the current concern (source)
        $variants = DB::fetchAll(
            "SELECT v.id, v.product_code, v.name, v.size, v.price, v.concern_id,
                    COALESCE(SUM(s.qty), 0) AS current_stock
             FROM variants v
             INNER JOIN stocks s ON s.variant_id = v.id AND s.concern_id = ?
                AND s.location NOT LIKE '%:DAMAGED%' AND s.location NOT LIKE '%:IN_TRANSIT%'
             WHERE v.status = 'active'
             GROUP BY v.id, v.product_code, v.name, v.size, v.price, v.concern_id
             HAVING COALESCE(SUM(s.qty), 0) > 0
             ORDER BY v.name ASC",
            [$cid ?? 0]
        );

        // 3. Precompute stock availability per concern & variant
        $stocksRaw = DB::fetchAll(
            "SELECT concern_id, variant_id, SUM(qty) AS total_qty 
             FROM stocks 
             WHERE location NOT LIKE '%:DAMAGED%' AND location NOT LIKE '%:IN_TRANSIT%' 
             GROUP BY concern_id, variant_id"
        );
        $concernStocksMap = [];
        foreach ($stocksRaw as $stk) {
            $cIdKey = (int)$stk['concern_id'];
            $vIdKey = (int)$stk['variant_id'];
            $concernStocksMap[$cIdKey][$vIdKey] = (float)$stk['total_qty'];
        }
        // Source (current) concern stock map for the indent modal
        $sourceStocksMap = $concernStocksMap[(int)$cid] ?? [];

        // Base SELECT for requisitions
        $baseSelect = "SELECT psr.*, 
                              COALESCE(dest_con.name, 'Main Retail Store') AS destination_concern_name, 
                              dest_con.code AS destination_concern_code,
                              COALESCE(src_con.name, 'Central Warehouse & Hub') AS source_concern_name, 
                              src_con.code AS source_concern_code,
                              (SELECT COUNT(*) FROM production_stock_request_items WHERE request_id = psr.id) AS item_count,
                              u.name AS creator_name, du.name AS dispatcher_name, ru.name AS receiver_name
                       FROM production_stock_requests psr
                       LEFT JOIN concerns dest_con ON psr.destination_concern_id = dest_con.id
                       LEFT JOIN concerns src_con ON (psr.source_concern_id = src_con.id OR (psr.source_concern_id IS NULL AND psr.concern_id = src_con.id))
                       LEFT JOIN users u ON psr.created_by = u.id
                       LEFT JOIN users du ON psr.dispatched_by = du.id
                       LEFT JOIN users ru ON psr.received_by = ru.id
                       WHERE 1=1";

        // Query Receive list (Image 1): Incoming requisitions from other concerns or destined for this store
        $receiveSql = $baseSelect;
        $receiveParams = [];
        if ($cid) {
            $receiveSql .= " AND (
                psr.destination_concern_id = ? 
                OR (psr.destination_concern_id IS NULL AND psr.to_location_type = 'SHOP' AND psr.concern_id = ?)
                OR (psr.source_concern_id IS NOT NULL AND psr.source_concern_id != ? AND (psr.destination_concern_id = ? OR psr.destination_concern_id IS NULL))
            )";
            $receiveParams[] = $cid;
            $receiveParams[] = $cid;
            $receiveParams[] = $cid;
            $receiveParams[] = $cid;
        }
        if ($search !== '') {
            $receiveSql .= " AND (psr.request_no LIKE ? OR psr.notes LIKE ? OR psr.dispatch_challan_no LIKE ?)";
            $receiveParams[] = "%{$search}%";
            $receiveParams[] = "%{$search}%";
            $receiveParams[] = "%{$search}%";
        }
        if ($status !== '') {
            $receiveSql .= " AND psr.status = ?";
            $receiveParams[] = $status;
        }
        if ($fromDate !== '') {
            $receiveSql .= " AND psr.created_at >= ?";
            $receiveParams[] = "{$fromDate} 00:00:00";
        }
        if ($toDate !== '') {
            $receiveSql .= " AND psr.created_at <= ?";
            $receiveParams[] = "{$toDate} 23:59:59";
        }
        $receiveSql .= " ORDER BY psr.id DESC";
        $receivedRequests = DB::fetchAll($receiveSql, $receiveParams);
        $this->attachItemsAndReplies($receivedRequests, $concernStocksMap);

        // Query Send list: Requisitions sent/indented by this concern
        $sendSql = $baseSelect;
        $sendParams = [];
        if ($cid) {
            $sendSql .= " AND (psr.concern_id = ? OR psr.source_concern_id = ?)";
            $sendParams[] = $cid;
            $sendParams[] = $cid;
        }
        if ($search !== '') {
            $sendSql .= " AND (psr.request_no LIKE ? OR psr.notes LIKE ? OR psr.dispatch_challan_no LIKE ?)";
            $sendParams[] = "%{$search}%";
            $sendParams[] = "%{$search}%";
            $sendParams[] = "%{$search}%";
        }
        if ($status !== '') {
            $sendSql .= " AND psr.status = ?";
            $sendParams[] = $status;
        }
        if ($fromDate !== '') {
            $sendSql .= " AND psr.created_at >= ?";
            $sendParams[] = "{$fromDate} 00:00:00";
        }
        if ($toDate !== '') {
            $sendSql .= " AND psr.created_at <= ?";
            $sendParams[] = "{$toDate} 23:59:59";
        }
        $sendSql .= " ORDER BY psr.id DESC";
        $sentRequests = DB::fetchAll($sendSql, $sendParams);
        $this->attachItemsAndReplies($sentRequests, $concernStocksMap);

        $this->render('production/stock_requests/index', [
            'pageTitle'        => 'Stock Requisitions & Inter-Concern Movement',
            'currentModule'    => 'request',
            'activeTab'        => $tab,
            'receivedRequests' => $receivedRequests,
            'sentRequests'     => $sentRequests,
            'requests'         => $tab === 'send' ? $sentRequests : $receivedRequests,
            'concerns'         => $concerns,
            'variants'         => $variants,
            'concernStocksMap' => $concernStocksMap,
            'sourceStocksMap'  => $concernStocksMap[(int)$cid] ?? [],
            'sourceCid'        => (int)$cid,
            'search'           => $search,
            'status'           => $status,
            'fromDate'         => $fromDate,
            'toDate'           => $toDate,
            'selectedCid'      => $cid,
        ]);
    }

    /**
     * Attach line items (with live source available stock) and staff replies to requisition rows.
     */
    private function attachItemsAndReplies(array &$requests, array $concernStocksMap): void {
        if (empty($requests)) {
            return;
        }
        $reqIds = array_map(fn($r) => (int)$r['id'], $requests);
        $inPlaceholders = implode(',', array_fill(0, count($reqIds), '?'));

        $itemRows = DB::fetchAll(
            "SELECT psri.request_id, psri.variant_id, psri.requested_qty,
                    v.product_code, v.name AS variant_name, v.size
             FROM production_stock_request_items psri
             JOIN variants v ON psri.variant_id = v.id
             WHERE psri.request_id IN ({$inPlaceholders})
             ORDER BY psri.id ASC",
            $reqIds
        );
        $itemsByReq = [];
        foreach ($itemRows as $ir) {
            $itemsByReq[(int)$ir['request_id']][] = $ir;
        }

        $replyRows = DB::fetchAll(
            "SELECT psra.request_id, psra.notes, psra.created_at, u.name AS actor_name
             FROM production_stock_request_audits psra
             LEFT JOIN users u ON psra.actor_id = u.id
             WHERE psra.request_id IN ({$inPlaceholders}) AND psra.action = 'REPLY'
             ORDER BY psra.id ASC",
            $reqIds
        );
        $repliesByReq = [];
        foreach ($replyRows as $rr) {
            $repliesByReq[(int)$rr['request_id']][] = $rr;
        }

        foreach ($requests as &$reqRow) {
            $rId = (int)$reqRow['id'];
            $srcCid = (int)($reqRow['source_concern_id'] ?: $reqRow['concern_id']);
            $reqItems = $itemsByReq[$rId] ?? [];
            foreach ($reqItems as &$ri) {
                $ri['live_source_available'] = (float)($concernStocksMap[$srcCid][(int)$ri['variant_id']] ?? 0.0);
            }
            unset($ri);
            $reqRow['items'] = $reqItems;
            $reqRow['replies'] = $repliesByReq[$rId] ?? [];
        }
        unset($reqRow);
    }

    public function export(): void {
        ModuleGuard::enforce('request', 'view');
        $cid = Auth::concernId();
        $format = strtolower(trim((string)($_GET['format'] ?? 'excel')));
        $period = is_scalar($_GET['period'] ?? null) ? (string)$_GET['period'] : 'all';
        $singleDate = is_scalar($_GET['single_date'] ?? null) ? (string)$_GET['single_date'] : null;
        $fromDate = is_scalar($_GET['from_date'] ?? null) ? (string)$_GET['from_date'] : null;
        $toDate = is_scalar($_GET['to_date'] ?? null) ? (string)$_GET['to_date'] : null;
        $dateScope = ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT psr.*,
                       sc.name AS source_concern_name, sc.code AS source_concern_code,
                       dc.name AS destination_concern_name, dc.code AS destination_concern_code,
                       u.name AS requested_by_user,
                       (SELECT COUNT(*) FROM production_stock_request_items WHERE request_id = psr.id) AS item_count,
                       (SELECT COALESCE(SUM(requested_qty), 0) FROM production_stock_request_items WHERE request_id = psr.id) AS total_req_qty,
                       (SELECT COALESCE(SUM(dispatched_qty), 0) FROM production_stock_request_items WHERE request_id = psr.id) AS total_disp_qty,
                       (SELECT COALESCE(SUM(received_qty), 0) FROM production_stock_request_items WHERE request_id = psr.id) AS total_recv_qty
                FROM production_stock_requests psr
                LEFT JOIN concerns sc ON psr.source_concern_id = sc.id
                LEFT JOIN concerns dc ON psr.destination_concern_id = dc.id
                LEFT JOIN users u ON psr.requested_by = u.id
                WHERE 1=1";
        $params = [];
        if ($cid) {
            $sql .= " AND (psr.concern_id = ? OR psr.source_concern_id = ? OR psr.destination_concern_id = ?)";
            $params[] = $cid;
            $params[] = $cid;
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $sql .= " AND DATE(psr.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        $sql .= " ORDER BY psr.id DESC";
        $requests = DB::fetchAll($sql, $params);

        $headers = [
            'Request No', 'Date', 'Source Unit', 'Destination Warehouse', 'Status',
            'Items Count', 'Requested Qty', 'Dispatched Qty', 'Received Qty',
            'Requested By', 'Dispatch Challan', 'Notes'
        ];

        $rows = [];
        $totReq = 0; $totDisp = 0; $totRecv = 0;
        foreach ($requests as $r) {
            $reqQty = (float)($r['total_req_qty'] ?? 0);
            $dispQty = (float)($r['total_disp_qty'] ?? 0);
            $recvQty = (float)($r['total_recv_qty'] ?? 0);
            $totReq += $reqQty;
            $totDisp += $dispQty;
            $totRecv += $recvQty;

            $rows[] = [
                $r['request_no'],
                date('d M Y, h:i A', strtotime($r['created_at'])),
                $r['source_concern_name'] ? "{$r['source_concern_name']} ({$r['source_concern_code']})" : 'Central Warehouse',
                $r['destination_concern_name'] ? "{$r['destination_concern_name']} ({$r['destination_concern_code']})" : 'Main Facility',
                strtoupper($r['status'] ?? 'PENDING'),
                (int)($r['item_count'] ?? 0),
                $reqQty,
                $dispQty,
                $recvQty,
                $r['requested_by_user'] ?? 'Staff',
                $r['dispatch_challan_no'] ?: '-',
                $r['notes'] ?: '-'
            ];
        }

        $totals = ['Total Requisitions: ' . count($requests), '', '', '', '', '', $totReq, $totDisp, $totRecv, '', '', ''];
        $metadata = [
            'Report Title' => 'Stock Requisitions & Transfer Register Report',
            'Scope / Period' => $dateScope['label'] ?? 'All Time',
            'Generated At' => date('d M Y, h:i A'),
            'Total Requisitions' => count($requests),
            'Total Requested Units' => number_format($totReq),
            'Total Dispatched Units' => number_format($totDisp)
        ];

        $filename = 'stock_requisitions_' . date('Ymd_His');

        if ($format === 'pdf') {
            ExportService::renderPdfView('Stock Requisitions & Transfer Register Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            ExportService::downloadExcel($filename, 'Stock Requisitions & Transfer Register Report', $metadata, $headers, $rows, $totals);
        }
    }

    /**
     * AJAX endpoint: return stock map for a concern
     */
    public function variantStocks(): void {
        $cid = (int)($_GET['concern_id'] ?? Auth::concernId() ?? 0);
        $stocks = [];
        if ($cid > 0) {
            $rows = DB::fetchAll(
                "SELECT variant_id, SUM(qty) AS total_qty 
                 FROM stocks 
                 WHERE concern_id = ? AND location NOT LIKE '%:DAMAGED%' AND location NOT LIKE '%:IN_TRANSIT%' 
                 GROUP BY variant_id",
                [$cid]
            );
            foreach ($rows as $r) {
                $stocks[(int)$r['variant_id']] = (float)$r['total_qty'];
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'stocks' => $stocks]);
        exit;
    }

    public function create(): void {
        ModuleGuard::enforce('request', 'add');
        $cid = Auth::concernId();

        $variants = DB::fetchAll(
            "SELECT id, product_code, name, size, price FROM variants 
             WHERE (concern_id = ? OR ? IS NULL) AND status = 'active' ORDER BY name ASC",
            [$cid, $cid]
        );

        $warehouses = [
            ['id' => 1, 'name' => 'Central Warehouse'],
            ['id' => 2, 'name' => 'Material Store'],
        ];
        $shops = [
            ['id' => 1, 'name' => 'Main Retail Outlet'],
            ['id' => 2, 'name' => 'Showroom Store'],
        ];
        $units = DB::fetchAll(
            "SELECT id, name, code FROM production_units WHERE (concern_id = ? OR ? IS NULL) AND is_active = 1",
            [$cid, $cid]
        );

        $this->render('production/stock_requests/create', [
            'pageTitle'     => 'Create Stock Requisition',
            'currentModule' => 'request',
            'variants'      => $variants,
            'warehouses'    => $warehouses,
            'shops'         => $shops,
            'units'         => $units,
            'selectedCid'   => $cid,
        ]);
    }

    public function store(): void {
        ModuleGuard::enforce('request', 'add');
        $cid = Auth::concernId() ?: (int)($_POST['concern_id'] ?? 0);
        if (!$cid) {
            $firstCon = DB::fetch("SELECT id FROM concerns WHERE status = 'active' ORDER BY id ASC LIMIT 1");
            $cid = $firstCon ? (int)$firstCon['id'] : 1;
        }

        $destConcernId = !empty($_POST['destination_concern_id']) ? (int)$_POST['destination_concern_id'] : $cid;
        $sourceConcernId = $cid;
        if ($destConcernId === $sourceConcernId) {
            $this->flash('error', 'Destination concern must be different from the source concern.');
            $this->redirect('/production/stock-requests?tab=send');
            return;
        }

        $fromType = strtoupper(trim($_POST['from_location_type'] ?? 'WAREHOUSE'));
        $fromId = (int)($_POST['from_location_id'] ?? 1);
        $toType = strtoupper(trim($_POST['to_location_type'] ?? 'SHOP'));
        $toId = (int)($_POST['to_location_id'] ?? 1);
        $priority = strtoupper(trim($_POST['priority'] ?? 'NORMAL'));
        $expectedDate = !empty($_POST['expected_date']) ? trim($_POST['expected_date']) : date('Y-m-d', strtotime('+3 days'));
        $notes = trim($_POST['notes'] ?? '');

        $rawItems = $_POST['items'] ?? null;
        $items = [];
        if (is_array($rawItems)) {
            foreach ($rawItems as $k => $it) {
                $vId = (int)($it['variant_id'] ?? $k);
                $rq = (float)($it['requested_qty'] ?? 0);
                if ($vId > 0 && $rq > 0) {
                    $items[] = ['variant_id' => $vId, 'requested_qty' => $rq];
                }
            }
        } elseif (is_string($rawItems)) {
            $decoded = json_decode($rawItems, true);
            if (is_array($decoded)) {
                foreach ($decoded as $it) {
                    $vId = (int)($it['variant_id'] ?? 0);
                    $rq = (float)($it['requested_qty'] ?? 0);
                    if ($vId > 0 && $rq > 0) {
                        $items[] = ['variant_id' => $vId, 'requested_qty' => $rq];
                    }
                }
            }
        }

        // Fallback for single variant input
        if (empty($items) && !empty($_POST['variant_id'])) {
            $vId = (int)$_POST['variant_id'];
            $rq = (float)($_POST['requested_qty'] ?? 1);
            if ($vId > 0 && $rq > 0) {
                $items[] = ['variant_id' => $vId, 'requested_qty' => $rq];
            }
        }

        if (empty($items)) {
            $this->flash('error', 'Please add at least one line item to the requisition.');
            $this->redirect('/production/stock-requests?tab=send');
            return;
        }

        DocumentNumberService::initTable();

        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            $requestNo = DocumentNumberService::generate('PRODUCTION_STOCK_REQUEST', 0, 'REQ');

            $totalRequestedQty = 0.00;
            foreach ($items as $item) {
                $totalRequestedQty += (float)($item['requested_qty'] ?? 0);
            }

            // 1. Insert header
            DB::query(
                "INSERT INTO production_stock_requests (
                    concern_id, source_concern_id, destination_concern_id, request_no, request_type, from_location_type, from_location_id,
                    to_location_type, to_location_id, status, priority, requested_qty, expected_date, notes, created_by, created_at
                 ) VALUES (?, ?, ?, ?, 'INTER_MODULE', ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?, ?, NOW())",
                [
                    $cid, $sourceConcernId, $destConcernId, $requestNo, $fromType, $fromId,
                    $toType, $toId, $priority, $totalRequestedQty, $expectedDate, $notes, Auth::id()
                ]
            );
            $requestId = (int)DB::lastInsertId();

            // 2. Insert line items
            foreach ($items as $item) {
                $variantId = (int)($item['variant_id'] ?? 0);
                $rq = (float)($item['requested_qty'] ?? 0);
                if ($variantId <= 0 || $rq <= 0) continue;

                DB::query(
                    "INSERT INTO production_stock_request_items (request_id, variant_id, requested_qty, status, created_at)
                     VALUES (?, ?, ?, 'PENDING', NOW())",
                    [$requestId, $variantId, $rq]
                );
            }

            // 3. Log audit timeline
            DB::query(
                "INSERT INTO production_stock_request_audits (request_id, action, qty, actor_id, notes, created_at)
                 VALUES (?, 'CREATE', ?, ?, ?, NOW())",
                [$requestId, $totalRequestedQty, Auth::id(), "Requisition created: {$notes}"]
            );

            AuditService::log('STOCK_REQUEST_CREATE', 'production_stock_requests', $requestId, null, [
                'request_no' => $requestNo,
                'source'     => $sourceConcernId,
                'dest'       => $destConcernId,
                'from'       => "{$fromType}:{$fromId}",
                'to'         => "{$toType}:{$toId}",
                'total_qty'  => $totalRequestedQty
            ]);

            if (!$hasActiveTx) {
                DB::commit();
            }

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success'    => true,
                    'request_no' => $requestNo,
                    'id'         => $requestId,
                    'message'    => "Stock Requisition '{$requestNo}' submitted successfully."
                ]);
                exit;
            }

            $this->flash('success', "Stock Requisition '{$requestNo}' submitted successfully.");
            $this->redirect('/production/stock-requests?tab=send');
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            $this->flash('error', 'Failed to create requisition: ' . $e->getMessage());
            $this->redirect('/production/stock-requests?tab=send');
        }
    }

    public function view(int $id): void {
        $cid = Auth::concernId();

        $request = DB::fetch(
            "SELECT psr.*, con.name AS concern_name, con.code AS concern_code,
                    dest_con.name AS destination_concern_name, src_con.name AS source_concern_name,
                    u.name AS creator_name, du.name AS dispatcher_name, ru.name AS receiver_name
             FROM production_stock_requests psr
             LEFT JOIN concerns con ON psr.concern_id = con.id
             LEFT JOIN concerns dest_con ON psr.destination_concern_id = dest_con.id
             LEFT JOIN concerns src_con ON psr.source_concern_id = src_con.id
             LEFT JOIN users u ON psr.created_by = u.id
             LEFT JOIN users du ON psr.dispatched_by = du.id
             LEFT JOIN users ru ON psr.received_by = ru.id
             WHERE psr.id = ? AND (psr.concern_id = ? OR psr.destination_concern_id = ? OR psr.source_concern_id = ? OR ? IS NULL)",
            [$id, $cid, $cid, $cid, $cid]
        );

        if (!$request) {
            $this->flash('error', 'Stock requisition not found.');
            $this->redirect('/production/stock-requests');
            return;
        }

        $items = DB::fetchAll(
            "SELECT psri.*, v.product_code, v.name AS variant_name, v.size 
             FROM production_stock_request_items psri
             JOIN variants v ON psri.variant_id = v.id
             WHERE psri.request_id = ?
             ORDER BY psri.id ASC",
            [$id]
        );

        // Fetch live available stock for each line at source concern
        $sourceConcernId = (int)($request['source_concern_id'] ?: $request['concern_id']);
        foreach ($items as &$item) {
            $avail = DB::fetchValue(
                "SELECT COALESCE(SUM(qty_in - qty_out), 0)
                 FROM stock_ledger
                 WHERE concern_id = ? AND variant_id = ?
                   AND location NOT LIKE '%:DAMAGED'
                   AND location NOT LIKE '%:IN_TRANSIT'
                   AND location NOT LIKE '%:RESERVED'",
                [$sourceConcernId, (int)$item['variant_id']]
            );
            $item['live_source_available'] = max(0.0, (float)$avail);
        }
        unset($item);

        $audits = DB::fetchAll(
            "SELECT psra.*, u.name AS actor_name 
             FROM production_stock_request_audits psra
             LEFT JOIN users u ON psra.actor_id = u.id
             WHERE psra.request_id = ?
             ORDER BY psra.id ASC",
            [$id]
        );

        $this->render('production/stock_requests/view', [
            'pageTitle'     => "Requisition {$request['request_no']}",
            'currentModule' => 'request',
            'request'       => $request,
            'items'         => $items,
            'audits'        => $audits,
        ]);
    }

    /**
     * Add a staff reply / note to a stock requisition.
     */
    public function reply(): void {
        ModuleGuard::enforce('request', 'view');
        if (!\App\Core\Csrf::verify($_POST['csrf_token'] ?? '')) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/production/stock-requests');
            return;
        }

        $id = (int)($_POST['request_id'] ?? 0);
        $replyNotes = trim((string)($_POST['reply_notes'] ?? ''));
        $redirectTab = trim((string)($_POST['redirect_tab'] ?? ''));
        $cid = Auth::concernId();

        $fallbackRedirect = $redirectTab === 'receive'
            ? '/production/stock-requests?tab=receive'
            : ($id > 0 ? "/production/stock-requests/view/{$id}" : '/production/stock-requests');

        if ($id <= 0 || $replyNotes === '') {
            $this->flash('error', 'Please enter a reply note before submitting.');
            $this->redirect($fallbackRedirect);
            return;
        }

        $request = DB::fetch(
            "SELECT id FROM production_stock_requests
             WHERE id = ? AND (concern_id = ? OR destination_concern_id = ? OR source_concern_id = ? OR ? IS NULL)",
            [$id, $cid, $cid, $cid, $cid]
        );
        if (!$request) {
            $this->flash('error', 'Stock requisition not found.');
            $this->redirect('/production/stock-requests');
            return;
        }

        $alreadyReplied = (bool)DB::fetchValue(
            "SELECT 1 FROM production_stock_request_audits WHERE request_id = ? AND action = 'REPLY' LIMIT 1",
            [$id]
        );
        if ($alreadyReplied) {
            $this->flash('error', 'A reply has already been submitted for this requisition.');
            $this->redirect($fallbackRedirect);
            return;
        }

        DB::query(
            "INSERT INTO production_stock_request_audits (request_id, action, qty, actor_id, notes, created_at)
             VALUES (?, 'REPLY', 0, ?, ?, NOW())",
            [$id, Auth::id(), $replyNotes]
        );

        try {
            $col = DB::fetch("SHOW COLUMNS FROM `production_stock_requests` LIKE 'status'");
            if ($col && stripos((string)($col['Type'] ?? ''), 'enum(') !== false && stripos((string)$col['Type'], "'REPLIED'") === false) {
                DB::query("ALTER TABLE `production_stock_requests` MODIFY COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'PENDING'");
            }
            DB::query("UPDATE production_stock_requests SET status = 'REPLIED' WHERE id = ? AND status IN ('PENDING', 'APPROVED')", [$id]);
        } catch (\Throwable $e) {
            error_log('StockRequest reply status update: ' . $e->getMessage());
        }

        $this->flash('success', 'Reply note saved.');
        $this->redirect($fallbackRedirect);
    }

    /**
     * Dispatch stock: source AVAILABLE -> IN_TRANSIT
     */
    public function sendStocks(): void {
        $id = (int)($_POST['request_id'] ?? 0);
        $cid = Auth::concernId();

        $request = DB::fetch(
            "SELECT * FROM production_stock_requests WHERE id = ? AND (concern_id = ? OR source_concern_id = ? OR ? IS NULL)",
            [$id, $cid, $cid, $cid]
        );

        if (!$request) {
            $this->flash('error', 'Requisition record not found.');
            $this->redirect('/production/stock-requests');
            return;
        }

        if (!in_array($request['status'], ['PENDING', 'APPROVED'], true)) {
            $this->flash('error', "Cannot dispatch requisition in status '{$request['status']}'.");
            $this->redirect('/production/stock-requests/view/' . $id);
            return;
        }

        $items = DB::fetchAll("SELECT * FROM production_stock_request_items WHERE request_id = ?", [$id]);
        $dispatchInputs = $_POST['dispatch_qty'] ?? [];

        $concernId = (int)$request['concern_id'];
        $sourceLoc = StockService::formatLocation($request['from_location_type'], $request['from_location_id'], 'AVAILABLE');
        $inTransitLoc = StockService::formatLocation($request['from_location_type'], $request['from_location_id'], 'IN_TRANSIT');

        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            $challanNo = DocumentNumberService::generate('CHALLAN', $concernId);
            $batchLines = [];
            $totalDispatchedThisTurn = 0.00;

            foreach ($items as $item) {
                $itemId = (int)$item['id'];
                $variantId = (int)$item['variant_id'];
                $requested = (float)$item['requested_qty'];
                $alreadyDispatched = (float)$item['dispatched_qty'];
                $remainingToSend = $requested - $alreadyDispatched;

                // Determine quantity to dispatch
                $sendQty = isset($dispatchInputs[$itemId]) ? (float)$dispatchInputs[$itemId] : $remainingToSend;

                // Validate bounded send_qty
                if ($sendQty <= 0) continue;
                if ($sendQty > $remainingToSend + 0.001) {
                    throw new Exception("Dispatch quantity ({$sendQty}) exceeds remaining requested quantity ({$remainingToSend}).");
                }

                // Check live source stock
                $available = StockService::getAvailable($variantId, $request['from_location_type'], $request['from_location_id'], $concernId);
                if ($available < $sendQty) {
                    throw new Exception("Insufficient stock at source. Available: {$available}, Requested dispatch: {$sendQty}.");
                }

                $batchLines[] = [
                    'variant_id'    => $variantId,
                    'from_location' => $sourceLoc,
                    'to_location'   => $inTransitLoc,
                    'qty'           => $sendQty,
                    'note'          => "Dispatched under Challan #{$challanNo} for Request #{$request['request_no']}"
                ];

                $newDispatched = $alreadyDispatched + $sendQty;
                $newInTransit = (float)$item['in_transit_qty'] + $sendQty;

                DB::query(
                    "UPDATE production_stock_request_items SET dispatched_qty = ?, in_transit_qty = ?, status = 'DISPATCHED' WHERE id = ?",
                    [$newDispatched, $newInTransit, $itemId]
                );

                $totalDispatchedThisTurn += $sendQty;
            }

            if (empty($batchLines)) {
                throw new Exception("No valid quantities specified for dispatch.");
            }

            // Atomic double-entry transfer: Source AVAILABLE -> IN_TRANSIT
            StockService::transferBatch(
                $concernId,
                $batchLines,
                'Stock Request Dispatch',
                $id,
                "Dispatch Challan [{$challanNo}]"
            );

            $newTotDispatched = (float)$request['dispatched_qty'] + $totalDispatchedThisTurn;
            $newTotInTransit = (float)$request['in_transit_qty'] + $totalDispatchedThisTurn;

            DB::query(
                "UPDATE production_stock_requests SET 
                    status = 'DISPATCHED',
                    dispatch_challan_no = ?,
                    dispatched_qty = ?,
                    in_transit_qty = ?,
                    dispatched_by = ?,
                    dispatched_at = NOW()
                 WHERE id = ?",
                [$challanNo, $newTotDispatched, $newTotInTransit, Auth::id(), $id]
            );

            // Audit
            DB::query(
                "INSERT INTO production_stock_request_audits (request_id, action, qty, actor_id, notes, created_at)
                 VALUES (?, 'DISPATCH', ?, ?, ?, NOW())",
                [$id, $totalDispatchedThisTurn, Auth::id(), "Dispatched {$totalDispatchedThisTurn} units under Challan #{$challanNo}"]
            );

            AuditService::log('STOCK_REQUEST_DISPATCH', 'production_stock_requests', $id, ['old_status' => $request['status']], [
                'status'        => 'DISPATCHED',
                'challan_no'    => $challanNo,
                'dispatched_qty'=> $totalDispatchedThisTurn
            ]);

            if (!$hasActiveTx) {
                DB::commit();
            }

            $this->flash('success', "Dispatched {$totalDispatchedThisTurn} units under Challan '{$challanNo}'. Stock moved to IN_TRANSIT.");
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            $this->flash('error', 'Dispatch failed: ' . $e->getMessage());
        }

        $this->redirect('/production/stock-requests/view/' . $id);
    }

    /**
     * Confirm receipt at destination: IN_TRANSIT -> destination AVAILABLE
     */
    public function confirmReceived(): void {
        $id = (int)($_POST['request_id'] ?? 0);
        $cid = Auth::concernId();

        $request = DB::fetch(
            "SELECT * FROM production_stock_requests WHERE id = ? AND (concern_id = ? OR destination_concern_id = ? OR source_concern_id = ? OR ? IS NULL)",
            [$id, $cid, $cid, $cid, $cid]
        );

        if (!$request) {
            $this->flash('error', 'Requisition not found.');
            $this->redirect('/production/stock-requests?tab=receive');
            return;
        }

        if (!in_array($request['status'], ['DISPATCHED', 'IN_TRANSIT'], true)) {
            $this->flash('error', "Cannot confirm receipt for requisition in status '{$request['status']}'.");
            $this->redirect('/production/stock-requests/view/' . $id);
            return;
        }

        $items = DB::fetchAll("SELECT * FROM production_stock_request_items WHERE request_id = ?", [$id]);
        $receivedInputs = $_POST['received_qty'] ?? [];
        $receivedTotal = isset($_POST['received_qty_total']) ? (float)$_POST['received_qty_total'] : null;
        if ($receivedTotal !== null && empty($receivedInputs) && !empty($items)) {
            if (count($items) === 1) {
                $receivedInputs[$items[0]['id']] = $receivedTotal;
            } else {
                $totTransit = (float)$request['in_transit_qty'] ?: (float)$request['dispatched_qty'];
                $ratio = $totTransit > 0 ? min(1.0, $receivedTotal / $totTransit) : 1.0;
                foreach ($items as $it) {
                    $receivedInputs[$it['id']] = round((float)$it['in_transit_qty'] * $ratio, 2);
                }
            }
        }

        $concernId = (int)$request['concern_id'];
        $inTransitLoc = StockService::formatLocation($request['from_location_type'], $request['from_location_id'], 'IN_TRANSIT');
        $destLoc = StockService::formatLocation($request['to_location_type'], $request['to_location_id'], 'AVAILABLE');

        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            $transferLines = [];
            $totalReceivedThisTurn = 0.00;
            $totalShortageThisTurn = 0.00;

            foreach ($items as $item) {
                $itemId = (int)$item['id'];
                $variantId = (int)$item['variant_id'];
                $inTransit = (float)$item['in_transit_qty'];

                if ($inTransit <= 0) continue;

                $receivedQty = isset($receivedInputs[$itemId]) ? (float)$receivedInputs[$itemId] : $inTransit;
                if ($receivedQty > $inTransit + 0.001) {
                    throw new Exception("Received quantity ({$receivedQty}) cannot exceed in-transit quantity ({$inTransit}).");
                }

                $shortageQty = max(0.00, $inTransit - $receivedQty);

                // Transfer received portion: IN_TRANSIT -> destination AVAILABLE
                if ($receivedQty > 0) {
                    $transferLines[] = [
                        'variant_id'    => $variantId,
                        'from_location' => $inTransitLoc,
                        'to_location'   => $destLoc,
                        'qty'           => $receivedQty,
                        'note'          => "Receipt confirmation for Request #{$request['request_no']}"
                    ];
                }

                // If shortage occurred, deduct shortage from IN_TRANSIT and log adjustment
                if ($shortageQty > 0) {
                    StockService::move(
                        $concernId,
                        $variantId,
                        $inTransitLoc,
                        0.00,
                        $shortageQty,
                        'Stock Request Shortage',
                        $id,
                        "In-transit transit shortage for Request #{$request['request_no']}",
                        Auth::id()
                    );
                }

                $newReceived = (float)$item['received_qty'] + $receivedQty;
                $newShortage = (float)$item['shortage_qty'] + $shortageQty;
                $lineStatus = $newShortage > 0 ? 'SHORTAGE' : 'RECEIVED';

                DB::query(
                    "UPDATE production_stock_request_items SET in_transit_qty = 0, received_qty = ?, shortage_qty = ?, status = ? WHERE id = ?",
                    [$newReceived, $newShortage, $lineStatus, $itemId]
                );

                $totalReceivedThisTurn += $receivedQty;
                $totalShortageThisTurn += $shortageQty;
            }

            if (!empty($transferLines)) {
                StockService::transferBatch(
                    $concernId,
                    $transferLines,
                    'Stock Request Receipt',
                    $id,
                    "Receipt Confirmation"
                );
            }

            $overallStatus = $totalShortageThisTurn > 0 ? 'SHORTAGE' : 'RECEIVED';

            DB::query(
                "UPDATE production_stock_requests SET 
                    status = ?,
                    in_transit_qty = 0,
                    received_qty = received_qty + ?,
                    shortage_qty = shortage_qty + ?,
                    received_by = ?,
                    received_at = NOW()
                 WHERE id = ?",
                [$overallStatus, $totalReceivedThisTurn, $totalShortageThisTurn, Auth::id(), $id]
            );

            // Audit
            DB::query(
                "INSERT INTO production_stock_request_audits (request_id, action, qty, actor_id, notes, created_at)
                 VALUES (?, 'RECEIVE', ?, ?, ?, NOW())",
                [$id, $totalReceivedThisTurn, Auth::id(), "Confirmed receipt of {$totalReceivedThisTurn} units (Shortage: {$totalShortageThisTurn})"]
            );

            AuditService::log('STOCK_REQUEST_RECEIVE', 'production_stock_requests', $id, ['old_status' => $request['status']], [
                'status'       => $overallStatus,
                'received_qty' => $totalReceivedThisTurn,
                'shortage_qty' => $totalShortageThisTurn
            ]);

            if (!$hasActiveTx) {
                DB::commit();
            }

            $msg = "Received {$totalReceivedThisTurn} units at destination.";
            if ($totalShortageThisTurn > 0) {
                $msg .= " Shortage of {$totalShortageThisTurn} units was recorded.";
            }
            $this->flash('success', $msg);
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            $this->flash('error', 'Receipt confirmation failed: ' . $e->getMessage());
        }

        $this->redirect('/production/stock-requests/view/' . $id);
    }

    /**
     * Reject requisition (admin or source manager)
     */
    public function reject(): void {
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Rejected');
        $cid = Auth::concernId();

        $request = DB::fetch(
            "SELECT * FROM production_stock_requests WHERE id = ? AND (concern_id = ? OR source_concern_id = ? OR ? IS NULL)",
            [$id, $cid, $cid, $cid]
        );

        if (!$request) {
            $this->flash('error', 'Requisition not found.');
            $this->redirect('/production/stock-requests');
            return;
        }

        if (!in_array($request['status'], ['PENDING', 'APPROVED'], true)) {
            $this->flash('error', "Cannot reject requisition in status '{$request['status']}'.");
            $this->redirect('/production/stock-requests/view/' . $id);
            return;
        }

        DB::query(
            "UPDATE production_stock_requests SET status = 'REJECTED', notes = CONCAT(COALESCE(notes, ''), '\n[Rejected]: ', ?) WHERE id = ?",
            [$reason, $id]
        );

        DB::query(
            "INSERT INTO production_stock_request_audits (request_id, action, actor_id, notes, created_at)
             VALUES (?, 'REJECT', ?, ?, NOW())",
            [$id, Auth::id(), "Rejected: {$reason}"]
        );

        AuditService::log('STOCK_REQUEST_REJECT', 'production_stock_requests', $id, ['old_status' => $request['status']], [
            'status' => 'REJECTED',
            'reason' => $reason
        ]);

        $this->flash('success', "Requisition '{$request['request_no']}' was rejected.");
        $this->redirect('/production/stock-requests/view/' . $id);
    }

    /**
     * Cancel requisition (reverses IN_TRANSIT stock if already dispatched)
     */
    public function cancel(): void {
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['cancel_reason'] ?? 'Cancelled by user');
        $cid = Auth::concernId();

        $request = DB::fetch(
            "SELECT * FROM production_stock_requests WHERE id = ? AND (concern_id = ? OR source_concern_id = ? OR ? IS NULL)",
            [$id, $cid, $cid, $cid]
        );

        if (!$request) {
            $this->flash('error', 'Requisition not found.');
            $this->redirect('/production/stock-requests');
            return;
        }

        if (in_array($request['status'], ['RECEIVED', 'CANCELLED', 'REJECTED'], true)) {
            $this->flash('error', "Cannot cancel requisition in status '{$request['status']}'.");
            $this->redirect('/production/stock-requests/view/' . $id);
            return;
        }

        $items = DB::fetchAll("SELECT * FROM production_stock_request_items WHERE request_id = ?", [$id]);
        $concernId = (int)$request['concern_id'];
        $inTransitLoc = StockService::formatLocation($request['from_location_type'], $request['from_location_id'], 'IN_TRANSIT');
        $sourceLoc = StockService::formatLocation($request['from_location_type'], $request['from_location_id'], 'AVAILABLE');

        $hasActiveTx = DB::pdo()->inTransaction();
        if (!$hasActiveTx) {
            DB::beginTransaction();
        }

        try {
            $reverseLines = [];
            foreach ($items as $item) {
                $inTransit = (float)$item['in_transit_qty'];
                if ($inTransit > 0) {
                    $reverseLines[] = [
                        'variant_id'    => (int)$item['variant_id'],
                        'from_location' => $inTransitLoc,
                        'to_location'   => $sourceLoc,
                        'qty'           => $inTransit,
                        'note'          => "Cancellation reversal: Restored in-transit stock"
                    ];
                }
            }

            if (!empty($reverseLines)) {
                StockService::transferBatch(
                    $concernId,
                    $reverseLines,
                    'Stock Request Cancellation',
                    $id,
                    "Cancelled Requisition Reversal"
                );
            }

            DB::query(
                "UPDATE production_stock_requests SET status = 'CANCELLED', in_transit_qty = 0, cancelled_by = ?, cancelled_at = NOW(), cancel_reason = ? WHERE id = ?",
                [Auth::id(), $reason, $id]
            );

            DB::query(
                "INSERT INTO production_stock_request_audits (request_id, action, actor_id, notes, created_at)
                 VALUES (?, 'CANCEL', ?, ?, NOW())",
                [$id, Auth::id(), "Cancelled: {$reason}"]
            );

            AuditService::log('STOCK_REQUEST_CANCEL', 'production_stock_requests', $id, ['old_status' => $request['status']], [
                'status'        => 'CANCELLED',
                'cancel_reason' => $reason
            ]);

            if (!$hasActiveTx) {
                DB::commit();
            }

            $this->flash('success', "Requisition '{$request['request_no']}' was cancelled and in-transit stock returned to source.");
        } catch (\Throwable $e) {
            if (!$hasActiveTx) {
                DB::rollBack();
            }
            $this->flash('error', 'Cancellation failed: ' . $e->getMessage());
        }

        $this->redirect('/production/stock-requests/view/' . $id);
    }

    public function printChallan(int $id): void {
        $cid = Auth::concernId();

        $request = DB::fetch(
            "SELECT psr.*, con.name AS concern_name, con.code AS concern_code, con.gstin, con.phone AS concern_phone, con.logo AS concern_logo,
                    du.name AS dispatcher_name
             FROM production_stock_requests psr
             LEFT JOIN concerns con ON psr.concern_id = con.id
             LEFT JOIN users du ON psr.dispatched_by = du.id
             WHERE psr.id = ? AND (psr.concern_id = ? OR ? IS NULL)",
            [$id, $cid, $cid]
        );

        if (!$request) {
            $this->flash('error', 'Requisition not found.');
            $this->redirect('/production/stock-requests');
            return;
        }

        $items = DB::fetchAll(
            "SELECT psri.*, v.product_code, v.name AS variant_name, v.size 
             FROM production_stock_request_items psri
             JOIN variants v ON psri.variant_id = v.id
             WHERE psri.request_id = ?
             ORDER BY psri.id ASC",
            [$id]
        );

        $this->render('production/stock_requests/print_challan', [
            'pageTitle' => "Dispatch Challan {$request['dispatch_challan_no']}",
            'request'   => $request,
            'items'     => $items,
        ]);
    }
}
