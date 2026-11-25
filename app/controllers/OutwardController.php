<?php
// app/controllers/OutwardController.php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Models\Outward;
use App\Services\StockService;
use Exception;

class OutwardController extends Controller {
    private static bool $schemaEnsured = false;

    private function isOutwardInSourceScope(array $outward): bool {
        $scopeConcernId = Auth::concernId();
        if (!$scopeConcernId) {
            $requestedConcernId = (int)($_GET['concern_id'] ?? $_POST['concern_id'] ?? 0);
            $scopeConcernId = $requestedConcernId > 0 ? $requestedConcernId : null;
        }

        return !$scopeConcernId || (int)$outward['concern_id'] === $scopeConcernId;
    }

    private function deductMainStoreStock(int $concernId, int $variantId, float $qty, int $outwardId, string $outwardNo): void {
        $remaining = $qty;
        foreach (['Main Store', 'Main Store (WH-01)'] as $location) {
            $available = (float)DB::fetchValue(
                "SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = ? FOR UPDATE",
                [$concernId, $variantId, $location]
            );
            $deductQty = min($available, $remaining);
            if ($deductQty > 0) {
                StockService::move(
                    $concernId,
                    $variantId,
                    $location,
                    0.00,
                    $deductQty,
                    'Outward',
                    $outwardId,
                    "Batch outward dispatch {$outwardNo}"
                );
                $remaining -= $deductQty;
            }
        }

        if ($remaining > 0.0001) {
            throw new Exception("Insufficient Main Store stock for variant #{$variantId}.");
        }
    }

    private function mainStoreAvailableQty(int $concernId, int $variantId): float {
        return (float)(DB::fetchValue(
            "SELECT COALESCE(SUM(qty), 0)
             FROM stocks
             WHERE concern_id = ? AND variant_id = ?
               AND location IN ('Main Store', 'Main Store (WH-01)')",
            [$concernId, $variantId]
        ) ?: 0);
    }

    private function lockMainStoreAvailableQty(int $concernId, int $variantId): float {
        $rows = DB::fetchAll(
            "SELECT qty
             FROM stocks
             WHERE concern_id = ? AND variant_id = ?
               AND location IN ('Main Store', 'Main Store (WH-01)')
             ORDER BY location
             FOR UPDATE",
            [$concernId, $variantId]
        );

        return array_sum(array_map(static function (array $row): float {
            return max(0.0, (float)$row['qty']);
        }, $rows));
    }

    private function addOutwardTransitStock(int $concernId, int $variantId, float $qty, int $outwardId, string $outwardNo): void {
        StockService::move(
            $concernId,
            $variantId,
            "IN_TRANSIT:OUTWARD:{$outwardId}",
            $qty,
            0.00,
            'Outward Transit',
            $outwardId,
            "In-transit stock for outward {$outwardNo}"
        );
    }

    public function __construct() {
        parent::__construct();
        $this->ensureOutwardSchema();
    }

    /**
     * Self-heal the outward schema.
     */
    protected function ensureOutwardSchema(): void {
        if (self::$schemaEnsured) {
            return;
        }

        try {
            self::ensureBaseTables();

            DB::query("
                CREATE TABLE IF NOT EXISTS `batch_items` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `concern_id` INT NOT NULL,
                    `batch_id` INT NOT NULL,
                    `variant_id` INT NOT NULL,
                    `size` VARCHAR(50) NULL,
                    `color` VARCHAR(50) NULL,
                    `sku_code` VARCHAR(100) NULL,
                    `qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `taxable_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `gst_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `qr_codes` LONGTEXT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (`batch_id`),
                    INDEX (`variant_id`),
                    INDEX (`concern_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $outCols = array_column(DB::fetchAll("DESCRIBE `outwards`"), 'Field');
            if (!in_array('sales_invoice_id', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `sales_invoice_id` INT NULL AFTER `outward_no`");
            if (!in_array('warehouse_name', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `warehouse_name` VARCHAR(150) DEFAULT 'wareouse (WH-01)' AFTER `warehouse_id`");
            if (!in_array('dispatched_by_name', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `dispatched_by_name` VARCHAR(150) DEFAULT 'System Administrator' AFTER `driver_contact`");
            if (!in_array('total_boxes', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `total_boxes` INT DEFAULT 1 AFTER `warehouse_name`");
            if (!in_array('notes', $outCols)) DB::query("ALTER TABLE `outwards` ADD COLUMN `notes` TEXT NULL AFTER `total_boxes`");

            self::$schemaEnsured = true;
        } catch (\Throwable $e) {
            error_log("OutwardController ensureOutwardSchema: " . $e->getMessage());
        }
    }

    /**
     * Dynamic & Concern-Aware Production Outward & Dispatch List View
     */
    public function index(): void {
        $cid = Auth::concernId();
        $selectedConcernId = $cid ?: (!empty($_GET['concern_id']) ? (int)$_GET['concern_id'] : null);
        $posInvoiceId = max(0, (int)($_GET['pos_invoice_id'] ?? 0));
        $posInvoiceTransfer = null;
        $posInvoiceItems = [];

        if ($posInvoiceId > 0) {
            $invoiceScopeId = $cid ?: $selectedConcernId;
            $invoiceSql = "
                SELECT si.id, si.concern_id, si.invoice_no, si.customer_id,
                       si.notes, si.billing_company_name,
                       COALESCE(si.billing_company_name, c.name) AS customer_name
                FROM sales_invoices si
                JOIN customers c ON c.id = si.customer_id
                WHERE si.id = ?";
            $invoiceParams = [$posInvoiceId];
            if ($invoiceScopeId) {
                $invoiceSql .= " AND si.concern_id = ?";
                $invoiceParams[] = $invoiceScopeId;
            } elseif (!Auth::isSuperAdmin()) {
                $this->flash('error', 'Select a concern before transferring a POS invoice to Outward.');
                $this->redirect('/production/outward');
                return;
            }

            $posInvoiceTransfer = DB::fetch($invoiceSql, $invoiceParams);
            if (!$posInvoiceTransfer) {
                $this->flash('error', 'The POS invoice was not found for the selected concern.');
                $this->redirect('/production/outward');
                return;
            }
            if (!$selectedConcernId) {
                $selectedConcernId = (int)$posInvoiceTransfer['concern_id'];
            }
            $posInvoiceTransfer['is_company'] =
                !empty($posInvoiceTransfer['billing_company_name'])
                || stripos((string)$posInvoiceTransfer['notes'], 'Billing For: company') !== false;

            $existingInvoiceOutward = DB::fetch(
                "SELECT id, outward_no FROM outwards WHERE sales_invoice_id = ? LIMIT 1",
                [$posInvoiceId]
            );
            if ($existingInvoiceOutward) {
                $this->flash('warning', "Invoice {$posInvoiceTransfer['invoice_no']} is already linked to outward {$existingInvoiceOutward['outward_no']}.");
                $this->redirect('/production/outward');
                return;
            }

            $posInvoiceItems = DB::fetchAll(
                "SELECT sii.variant_id, SUM(sii.qty) AS qty, v.sku_code, v.name, v.size
                 FROM sales_invoice_items sii
                 JOIN variants v ON v.id = sii.variant_id
                 WHERE sii.sales_invoice_id = ?
                 GROUP BY sii.variant_id, v.sku_code, v.name, v.size
                 ORDER BY sii.variant_id",
                [$posInvoiceId]
            );
            if (!$posInvoiceItems) {
                $this->flash('error', "Invoice {$posInvoiceTransfer['invoice_no']} has no items to transfer.");
                $this->redirect('/production/outward');
                return;
            }
        }

        $search       = trim($_GET['search'] ?? '');
        $page         = max(1, (int)($_GET['page'] ?? 1));
        $perPage      = max(5, (int)($_GET['per_page'] ?? 10));
        $offset       = ($page - 1) * $perPage;

        $sql = "SELECT o.*, si.invoice_no AS sales_invoice_no,
                       con.name AS source_concern_name, con.code AS source_concern_code,
                       dest_con.name AS dest_concern_name, dest_con.code AS dest_concern_code,
                       c.name AS customer_name,
                       COALESCE(pob.cartons_count, o.total_boxes, b.total_boxes, 1) AS cartons_count,
                       COALESCE((SELECT SUM(qty) FROM outward_items oi WHERE oi.outward_id = o.id), pob.pieces_count, b.batch_qty, 490) AS pieces_count,
                       b.batch_no,
                       b.id AS linked_batch_id,
                       COALESCE(v.name, p.program_name, 'KIDS - BABY - DRESS') AS product_name,
                       v.sku_code, v.size,
                       p.program_no,
                       pu.name AS production_unit_name, pu.code AS production_unit_code,
                       COALESCE(o.vehicle_number, 'rtt31232') AS display_vehicle,
                       COALESCE(o.dispatched_by_name, 'System Administrator') AS display_dispatched_by,
                       COALESCE(o.warehouse_name, b.warehouse_name, 'wareouse (WH-01)') AS display_warehouse_name,
                       COALESCE(o.dispatched_at, o.created_at) AS display_dispatched_at
                FROM outwards o
                LEFT JOIN concerns con ON o.concern_id = con.id
                LEFT JOIN concerns dest_con ON o.destination_concern_id = dest_con.id
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN sales_invoices si ON si.id = o.sales_invoice_id
                LEFT JOIN production_outward_batches pob ON pob.outward_id = o.id
                LEFT JOIN batches b ON pob.batch_id = b.id
                LEFT JOIN variants v ON (b.variant_id = v.id OR (SELECT oi.variant_id FROM outward_items oi WHERE oi.outward_id = o.id LIMIT 1) = v.id)
                LEFT JOIN production_programs p ON b.program_id = p.id
                LEFT JOIN production_units pu ON o.production_unit_id = pu.id
                WHERE 1=1";
        $params = [];

        // Outward records belong to the source concern; destinations only receive them in Inward.
        if ($selectedConcernId) {
            $sql .= " AND o.concern_id = ?";
            $params[] = $selectedConcernId;
        }

        if ($search !== '') {
            $sql .= " AND (o.outward_no LIKE ? OR b.batch_no LIKE ? OR v.name LIKE ? OR o.vehicle_number LIKE ? OR o.warehouse_name LIKE ? OR dest_con.name LIKE ? OR c.name LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $countSql = "SELECT COUNT(*) FROM (" . $sql . ") AS cnt_tbl";
        $totalRecords = (int)DB::fetchValue($countSql, $params);
        $totalPages = max(1, (int)ceil($totalRecords / $perPage));

        $sql .= " ORDER BY o.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $outwards = DB::fetchAll($sql, $params);

        // All created concerns for multi-concern transfers, dispatching & filtering
        $allConcerns = DB::fetchAll("SELECT id, name, code, address, status FROM concerns ORDER BY id ASC");
        $currentConcernId = Auth::concernId() ?: ($selectedConcernId ?: 1);
        $sourceConcerns = $cid
            ? array_values(array_filter(
                $allConcerns,
                static fn($concern) => (int)$concern['id'] === (int)$currentConcernId
            ))
            : $allConcerns;

        // Show QC-inwarded production batches with any remaining Main Store stock.
        $availableBatches = DB::fetchAll("
            SELECT b.*,
                   COALESCE(v.name, p.program_name, 'Finished Production Batch') AS product_name,
                   v.sku_code, v.size, p.program_no, qi.qc_no,
                   COALESCE((
                       SELECT SUM(LEAST(batch_line.qty, COALESCE((
                           SELECT SUM(s.qty)
                           FROM stocks s
                           WHERE s.concern_id = batch_line.concern_id
                             AND s.variant_id = batch_line.variant_id
                             AND s.location IN ('Main Store', 'Main Store (WH-01)')
                       ), 0)))
                       FROM (
                           SELECT batch_id, concern_id, variant_id, SUM(qty) AS qty
                           FROM batch_items
                           GROUP BY batch_id, concern_id, variant_id
                       ) batch_line
                       WHERE batch_line.batch_id = b.id
                   ), LEAST(b.batch_qty, COALESCE((
                       SELECT SUM(s.qty)
                       FROM stocks s
                       WHERE s.concern_id = b.concern_id
                         AND s.variant_id = b.variant_id
                         AND s.location IN ('Main Store', 'Main Store (WH-01)')
                   ), 0))) AS dispatch_qty
            FROM batches b
            LEFT JOIN variants v ON b.variant_id = v.id
            LEFT JOIN production_programs p ON b.program_id = p.id
            JOIN qc_inspections qi ON qi.id = b.qc_batch_id AND qi.concern_id = b.concern_id
            WHERE b.concern_id = ?
              AND UPPER(b.status) = 'APPROVED'
              AND EXISTS (
                  SELECT 1
                  FROM stock_ledger qc_ledger
                  WHERE qc_ledger.concern_id = b.concern_id
                    AND qc_ledger.ref_module = 'Production QC'
                    AND qc_ledger.ref_id = b.qc_batch_id
                    AND qc_ledger.qty_in > 0
              )
              AND (
                  (
                      EXISTS (SELECT 1 FROM batch_items bi WHERE bi.batch_id = b.id)
                      AND NOT EXISTS (
                          SELECT 1
                          FROM batch_items bi
                          WHERE bi.batch_id = b.id
                            AND (
                                bi.qty <= 0
                                OR bi.concern_id <> b.concern_id
                                OR NOT EXISTS (
                                    SELECT 1
                                    FROM variants bv
                                    WHERE bv.id = bi.variant_id
                                      AND bv.concern_id = b.concern_id
                                      AND bv.status = 'active'
                                )
                                OR NOT EXISTS (
                                    SELECT 1
                                    FROM stock_ledger qc_line
                                    WHERE qc_line.concern_id = bi.concern_id
                                      AND qc_line.variant_id = bi.variant_id
                                      AND qc_line.ref_module = 'Production QC'
                                      AND qc_line.ref_id = b.qc_batch_id
                                      AND qc_line.qty_in > 0
                                )
                            )
                      )
                      AND EXISTS (
                          SELECT 1
                          FROM batch_items bi
                          WHERE bi.batch_id = b.id
                            AND COALESCE((
                                SELECT SUM(s.qty)
                                FROM stocks s
                                WHERE s.concern_id = bi.concern_id
                                  AND s.variant_id = bi.variant_id
                                  AND s.location IN ('Main Store', 'Main Store (WH-01)')
                            ), 0) > 0
                      )
                  )
                  OR (
                      NOT EXISTS (SELECT 1 FROM batch_items bi WHERE bi.batch_id = b.id)
                      AND b.variant_id IS NOT NULL
                      AND b.batch_qty > 0
                      AND EXISTS (
                          SELECT 1
                          FROM variants bv
                          WHERE bv.id = b.variant_id
                            AND bv.concern_id = b.concern_id
                            AND bv.status = 'active'
                      )
                      AND EXISTS (
                          SELECT 1
                          FROM stock_ledger qc_line
                          WHERE qc_line.concern_id = b.concern_id
                            AND qc_line.variant_id = b.variant_id
                            AND qc_line.ref_module = 'Production QC'
                            AND qc_line.ref_id = b.qc_batch_id
                            AND qc_line.qty_in > 0
                      )
                      AND COALESCE((
                          SELECT SUM(s.qty)
                          FROM stocks s
                          WHERE s.concern_id = b.concern_id
                            AND s.variant_id = b.variant_id
                            AND s.location IN ('Main Store', 'Main Store (WH-01)')
                      ), 0) > 0
                  )
              )
              AND COALESCE((
                  SELECT SUM(LEAST(batch_line.qty, COALESCE((
                      SELECT SUM(s.qty)
                      FROM stocks s
                      WHERE s.concern_id = batch_line.concern_id
                        AND s.variant_id = batch_line.variant_id
                        AND s.location IN ('Main Store', 'Main Store (WH-01)')
                  ), 0)))
                  FROM (
                      SELECT batch_id, concern_id, variant_id, SUM(qty) AS qty
                      FROM batch_items
                      GROUP BY batch_id, concern_id, variant_id
                  ) batch_line
                  WHERE batch_line.batch_id = b.id
              ), LEAST(b.batch_qty, COALESCE((
                  SELECT SUM(s.qty)
                  FROM stocks s
                  WHERE s.concern_id = b.concern_id
                    AND s.variant_id = b.variant_id
                    AND s.location IN ('Main Store', 'Main Store (WH-01)')
              ), 0))) > 0
            ORDER BY b.id DESC
        ", [$currentConcernId]);

        // Outward dispatch consumes Main Store stock for the variant's concern.
        $stockVariants = DB::fetchAll("
            SELECT v.id, v.concern_id, v.sku_code, v.name, v.size, v.price, v.mrp,
                   COALESCE((
                       SELECT SUM(s.qty)
                       FROM stocks s
                       WHERE s.concern_id = v.concern_id
                         AND s.variant_id = v.id
                         AND s.location IN ('Main Store', 'Main Store (WH-01)')
                   ), 0) AS stock_qty
            FROM variants v
            WHERE v.status = 'active'
            ORDER BY v.name ASC
            LIMIT 200
        ");

        // Customers list
        $customers = DB::fetchAll("SELECT id, name FROM customers WHERE status = 'active' ORDER BY name ASC");

        $this->render('concern/outward', [
            'pageTitle'         => 'Production Outward & Dispatch',
            'currentModule'     => 'outward',
            'outwards'          => $outwards,
            'totalRecords'      => $totalRecords,
            'page'              => $page,
            'perPage'           => $perPage,
            'totalPages'        => $totalPages,
            'search'            => $search,
            'selectedConcernId' => $selectedConcernId,
            'currentConcernId'  => $currentConcernId,
            'sourceConcerns'    => $sourceConcerns,
            'allConcerns'       => $allConcerns,
            'availableBatches'  => $availableBatches,
            'stockVariants'     => $stockVariants,
            'customers'         => $customers,
            'posInvoiceTransfer' => $posInvoiceTransfer,
            'posInvoiceItems' => $posInvoiceItems,
        ]);
    }

    private function validateOutwardDetails(
        string $vehicleNumber,
        string $driverName,
        string $driverContact,
        string $transporterName,
        string $notes,
        string $totalBoxes
    ): void {
        $compactVehicleNumber = preg_replace('/[\s-]+/', '', strtoupper(trim($vehicleNumber)));
        if (
            $compactVehicleNumber === null
            || !preg_match('/^(?:[A-Z]{2}\d{1,2}[A-Z]{1,3}\d{1,4}|\d{2}BH\d{4}[A-Z]{1,2})$/D', $compactVehicleNumber)
        ) {
            throw new Exception('Enter a valid vehicle registration number, such as TN 12 AB 1234.');
        }

        if ($driverName !== '' && !preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} .'-]{0,99}$/u", $driverName)) {
            throw new Exception('Driver name must contain only letters, spaces, apostrophes, periods, or hyphens.');
        }
        if ($driverContact !== '' && !preg_match('/^[6-9][0-9]{9}$/D', $driverContact)) {
            throw new Exception('Driver contact must be a valid 10-digit Indian mobile number.');
        }
        if (strlen($transporterName) > 150) {
            throw new Exception('Transporter name cannot exceed 150 characters.');
        }
        if (strlen($notes) > 2000) {
            throw new Exception('Outward notes cannot exceed 2000 characters.');
        }
        if ($totalBoxes !== '' && (!preg_match('/^\d+$/D', $totalBoxes) || (int)$totalBoxes < 1 || (int)$totalBoxes > 99999)) {
            throw new Exception('Total boxes/cartons must be a whole number between 1 and 99999.');
        }
    }

    private function storeUnifiedDispatch(): void {
        $authConcernId = Auth::concernId();
        $postedSourceConcernId = (int)($_POST['source_concern_id'] ?? 0);
        $cid = $authConcernId ?: ($postedSourceConcernId ?: (Auth::superAdminSelectedConcernId() ?: 1));
        $destinationConcernId = (int)($_POST['destination_concern_id'] ?? 0);
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $posInvoiceId = max(0, (int)($_POST['pos_invoice_id'] ?? 0));
        $vehicleNumber = trim($_POST['vehicle_number'] ?? '');
        $driverName = trim($_POST['driver_name'] ?? '');
        $driverContact = trim($_POST['driver_contact'] ?? '');
        $transporter = trim($_POST['transporter_name'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $transactionStarted = false;

        try {
            $rawTotalBoxes = $_POST['total_boxes'] ?? '';
            if (!is_scalar($rawTotalBoxes)) {
                throw new Exception('Total boxes/cartons must be a whole number.');
            }
            $this->validateOutwardDetails(
                $vehicleNumber,
                $driverName,
                $driverContact,
                $transporter,
                $notes,
                trim((string)$rawTotalBoxes)
            );
            if ($posInvoiceId > 0 && $batchId > 0) {
                throw new Exception('A POS invoice handoff cannot include a production batch.');
            }
            if ($posInvoiceId > 0) {
                $posInvoiceForRoute = DB::fetch(
                    "SELECT id, concern_id, notes, billing_company_name
                     FROM sales_invoices
                     WHERE id = ? LIMIT 1",
                    [$posInvoiceId]
                );
                $isCompanyInvoice = $posInvoiceForRoute && (
                    !empty($posInvoiceForRoute['billing_company_name'])
                    || stripos((string)$posInvoiceForRoute['notes'], 'Billing For: company') !== false
                );
                if ($isCompanyInvoice && $destinationConcernId <= 0) {
                    throw new Exception('Please select a destination concern for this company bill.');
                }
            }
            if ($posInvoiceId === 0 && $destinationConcernId <= 0) {
                throw new Exception('Please select a destination concern.');
            }
            if ($destinationConcernId > 0 && $destinationConcernId === $cid) {
                throw new Exception('The destination concern must be different from the source concern.');
            }

            $destination = null;
            if ($destinationConcernId > 0) {
                $destination = DB::fetch(
                    "SELECT id, name FROM concerns WHERE id = ? AND status = 'active'",
                    [$destinationConcernId]
                );
                if (!$destination) {
                    throw new Exception('The selected destination concern does not exist or is inactive.');
                }
            }

            $rawScannedItems = $_POST['scanned_items_json'] ?? '[]';
            if (!is_string($rawScannedItems)) {
                throw new Exception('The scanned outward items are invalid. Please scan the products again.');
            }
            $submittedItems = json_decode($rawScannedItems, true);
            if (!is_array($submittedItems)) {
                throw new Exception('The scanned outward items are invalid. Please scan the products again.');
            }
            $finishedGoodsByVariant = [];
            foreach ($submittedItems as $item) {
                if (!is_array($item)) {
                    throw new Exception('A scanned finished-good item is invalid.');
                }
                $variantId = (int)($item['variant_id'] ?? 0);
                $quantity = $item['qty'] ?? null;
                if ($variantId <= 0 || !is_numeric($quantity) || !is_finite((float)$quantity) || (float)$quantity <= 0) {
                    throw new Exception('A scanned finished-good item has an invalid variant or quantity.');
                }
                $finishedGoodsByVariant[$variantId] = ($finishedGoodsByVariant[$variantId] ?? 0.0) + (float)$quantity;
            }
            if ($batchId <= 0 && !$finishedGoodsByVariant) {
                throw new Exception('Scan a production batch or at least one finished-good product before dispatching.');
            }

            $today = date('Ymd');
            $countToday = (int)DB::fetchValue("SELECT COUNT(*) FROM outwards WHERE outward_no LIKE ?", ["OUT-{$today}-%"]);
            $sequence = str_pad((string)($countToday + 1), 4, '0', STR_PAD_LEFT);
            $outwardNo = "OUT-{$today}-{$sequence}";
            $gatePassNo = "GP-{$today}-{$sequence}";

            DB::beginTransaction();
            $transactionStarted = true;

            $posInvoice = null;
            if ($posInvoiceId > 0) {
                if ($authConcernId && $authConcernId !== $cid) {
                    throw new Exception('The POS invoice belongs to a different concern.');
                }
                $posInvoice = DB::fetch(
                    "SELECT id, concern_id, invoice_no, customer_id
                     FROM sales_invoices
                     WHERE id = ? AND concern_id = ?
                     LIMIT 1 FOR UPDATE",
                    [$posInvoiceId, $cid]
                );
                if (!$posInvoice) {
                    throw new Exception('The POS invoice was not found for this concern.');
                }
                if (DB::fetchValue("SELECT id FROM outwards WHERE sales_invoice_id = ? LIMIT 1", [$posInvoiceId])) {
                    throw new Exception("Invoice {$posInvoice['invoice_no']} is already linked to an outward.");
                }
                $expectedItems = [];
                foreach (DB::fetchAll(
                    "SELECT variant_id, SUM(qty) AS qty
                     FROM sales_invoice_items
                     WHERE sales_invoice_id = ?
                     GROUP BY variant_id",
                    [$posInvoiceId]
                ) as $invoiceItem) {
                    $expectedItems[(int)$invoiceItem['variant_id']] = (float)$invoiceItem['qty'];
                }
                ksort($expectedItems);
                ksort($finishedGoodsByVariant);
                if (array_keys($expectedItems) !== array_keys($finishedGoodsByVariant)) {
                    throw new Exception('The outward items must match the POS invoice items.');
                }
                foreach ($expectedItems as $variantId => $expectedQuantity) {
                    if (abs($expectedQuantity - $finishedGoodsByVariant[$variantId]) > 0.0001) {
                        throw new Exception('The outward quantities must match the POS invoice quantities.');
                    }
                }
                $notes = trim($notes . ' POS invoice ' . $posInvoice['invoice_no'] . '; stock was deducted during billing.');
            }

            $batch = null;
            $batchLines = [];
            $productionUnitId = (int)(DB::fetchValue("SELECT id FROM production_units WHERE concern_id = ? LIMIT 1", [$cid]) ?: 1);
            if ($batchId > 0) {
                $batch = DB::fetch(
                    "SELECT * FROM batches
                     WHERE id = ? AND concern_id = ? AND UPPER(status) = 'APPROVED'
                     LIMIT 1 FOR UPDATE",
                    [$batchId, $cid]
                );
                if (!$batch) {
                    throw new Exception('The selected production batch is no longer available for this concern.');
                }
                if (
                    empty($batch['qc_batch_id'])
                    || !DB::fetchValue(
                        "SELECT id FROM stock_ledger
                         WHERE concern_id = ? AND ref_module = 'Production QC'
                           AND ref_id = ? AND qty_in > 0
                         LIMIT 1",
                        [$cid, (int)$batch['qc_batch_id']]
                    )
                ) {
                    throw new Exception('The selected batch is not linked to QC-passed production stock.');
                }

                $productionUnitId = (int)($batch['production_unit_id'] ?: $productionUnitId);
                $batchRows = DB::fetchAll(
                    "SELECT * FROM batch_items WHERE batch_id = ? ORDER BY id ASC FOR UPDATE",
                    [$batchId]
                );
                if ($batchRows) {
                    foreach ($batchRows as $batchRow) {
                        $variantId = (int)$batchRow['variant_id'];
                        $quantity = (float)$batchRow['qty'];
                        if ((int)$batchRow['concern_id'] !== $cid || $variantId <= 0 || $quantity <= 0) {
                            throw new Exception('The selected production batch contains an invalid stock line.');
                        }
                        $batchLines[$variantId] = ($batchLines[$variantId] ?? 0.0) + $quantity;
                    }
                } else {
                    $variantId = (int)($batch['variant_id'] ?? 0);
                    $quantity = (float)$batch['batch_qty'];
                    if ($variantId <= 0 || $quantity <= 0) {
                        throw new Exception('The selected production batch has no dispatchable stock lines.');
                    }
                    $batchLines[$variantId] = $quantity;
                }
            }

            $allVariantIds = array_unique(array_merge(array_keys($finishedGoodsByVariant), array_keys($batchLines)));
            $availableByVariant = [];
            foreach ($allVariantIds as $variantId) {
                if (!DB::fetchValue(
                    "SELECT id FROM variants WHERE id = ? AND concern_id = ? AND status = 'active'",
                    [(int)$variantId, $cid]
                )) {
                    throw new Exception("Variant #{$variantId} is not active in the selected source concern.");
                }
                $availableByVariant[$variantId] = $posInvoiceId > 0
                    ? 0.0
                    : $this->lockMainStoreAvailableQty($cid, (int)$variantId);
            }

            $batchDispatchByVariant = [];
            foreach ($batchLines as $variantId => $requestedQuantity) {
                if (!DB::fetchValue(
                    "SELECT id FROM stock_ledger
                     WHERE concern_id = ? AND variant_id = ?
                       AND ref_module = 'Production QC' AND ref_id = ? AND qty_in > 0
                     LIMIT 1",
                    [$cid, (int)$variantId, (int)$batch['qc_batch_id']]
                )) {
                    throw new Exception('The selected batch contains stock that is not linked to its QC pass.');
                }
                $batchDispatchByVariant[$variantId] = min($requestedQuantity, $availableByVariant[$variantId]);
            }

            $combinedItems = [];
            foreach ($allVariantIds as $variantId) {
                $batchQuantity = (float)($batchDispatchByVariant[$variantId] ?? 0);
                $finishedQuantity = (float)($finishedGoodsByVariant[$variantId] ?? 0);
                if ($posInvoiceId === 0 && $batchQuantity + $finishedQuantity > $availableByVariant[$variantId] + 0.0001) {
                    throw new Exception("The combined batch and finished-good quantity exceeds available Main Store stock for variant #{$variantId}.");
                }
                $combinedQuantity = $batchQuantity + $finishedQuantity;
                if ($combinedQuantity > 0) {
                    $combinedItems[$variantId] = $combinedQuantity;
                }
            }
            if (!$combinedItems) {
                throw new Exception('No QC-passed batch or finished-good stock remains available for dispatch.');
            }

            $boxes = max(1, (int)($_POST['total_boxes'] ?? 0));
            if (empty($_POST['total_boxes']) && $batch) {
                $boxes = max(1, (int)($batch['total_boxes'] ?: 1));
            }
            $customerId = $posInvoice ? (int)$posInvoice['customer_id'] : null;
            $warehouseName = $destination
                ? (string)$destination['name']
                : (string)(DB::fetchValue("SELECT name FROM customers WHERE id = ?", [$customerId]) ?: '');
            $transferType = $destination ? 'concern' : ($customerId ? 'customer' : 'concern');

            DB::query("
                INSERT INTO outwards (
                    concern_id, production_unit_id, outward_no, sales_invoice_id, gate_pass_no, customer_id, transfer_type,
                    destination_concern_id, outward_date, location, warehouse_name, total_boxes,
                    vehicle_number, driver_name, driver_contact, transporter_name, dispatched_by_name,
                    notes, status, dispatched_at, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, CURDATE(), 'Main Store', ?, ?,
                    ?, ?, ?, ?, 'System Administrator',
                    ?, 'DISPATCHED', NOW(), NOW()
                )
            ", [
                $cid, $productionUnitId, $outwardNo, $posInvoiceId ?: null, $gatePassNo, $customerId, $transferType,
                $destinationConcernId ?: null, $warehouseName, $boxes,
                $vehicleNumber, $driverName, $driverContact, $transporter, $notes
            ]);
            $outwardId = (int)DB::lastInsertId();

            $batchPieces = array_sum($batchDispatchByVariant);
            if ($batch) {
                DB::query(
                    "INSERT INTO production_outward_batches (concern_id, outward_id, batch_id, cartons_count, pieces_count)
                     VALUES (?, ?, ?, ?, ?)",
                    [$cid, $outwardId, $batchId, $boxes, $batchPieces]
                );
            }

            foreach ($combinedItems as $variantId => $quantity) {
                DB::query(
                    "INSERT INTO outward_items (concern_id, outward_id, variant_id, qty) VALUES (?, ?, ?, ?)",
                    [$cid, $outwardId, (int)$variantId, $quantity]
                );
                if ($posInvoiceId === 0) {
                    $this->deductMainStoreStock($cid, (int)$variantId, $quantity, $outwardId, $outwardNo);
                    $this->addOutwardTransitStock($cid, (int)$variantId, $quantity, $outwardId, $outwardNo);
                }
            }
            if ($batch) {
                DB::query("UPDATE batches SET status = 'DISPATCHED', outward_id = ? WHERE id = ?", [$outwardId, $batchId]);
            }

            DB::commit();
            $transactionStarted = false;
            $totalQuantity = array_sum($combinedItems);
            $this->flash('success', "Outward Manifest {$outwardNo} successfully dispatched via {$vehicleNumber}! ({$totalQuantity} pcs, {$boxes} boxes)");
            $this->redirect('/production/outward');
            return;
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                DB::rollBack();
            }
            $this->flash('error', 'Dispatch failed: ' . $e->getMessage());
            $redirectPath = $posInvoiceId > 0
                ? "/production/outward?pos_invoice_id={$posInvoiceId}"
                : '/production/outward';
            $this->redirect($redirectPath);
        }
    }

    /**
     * Store new Outward Dispatch Manifest (POST)
     * Dynamically supports: Production Batch, Inter-Concern Transfer, Direct Stock, Customer Sale
     */
    public function store(): void {
        $this->validateCsrf();
        if (($_POST['dispatch_mode'] ?? '') === 'unified') {
            $this->storeUnifiedDispatch();
            return;
        }
        $authConcernId = Auth::concernId();
        $postedSourceConcernId = (int)($_POST['source_concern_id'] ?? ($_POST['source_concern_id_fg'] ?? 0));
        $cid = $authConcernId ?: ($postedSourceConcernId ?: (Auth::superAdminSelectedConcernId() ?: 1));
        $posInvoiceId = max(0, (int)($_POST['pos_invoice_id'] ?? 0));

        $dispatchMode    = trim($_POST['dispatch_mode'] ?? 'batch'); // batch, finished_goods
        $destConcernId   = !empty($_POST['destination_concern_id']) 
            ? (int)$_POST['destination_concern_id'] 
            : (!empty($_POST['destination_concern_id_fg']) ? (int)$_POST['destination_concern_id_fg'] : null);
        $customerId      = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;

        // STRICT CONCERN SELECTION: If dispatching to a concern, destination concern is mandatory
        if ($posInvoiceId > 0) {
            $posInvoiceRoute = DB::fetch(
                "SELECT id, notes, billing_company_name FROM sales_invoices WHERE id = ?",
                [$posInvoiceId]
            );
            $isCompanyPosInvoice = $posInvoiceRoute && (
                !empty($posInvoiceRoute['billing_company_name'])
                || stripos((string)$posInvoiceRoute['notes'], 'Billing For: company') !== false
            );
            if ($isCompanyPosInvoice && !$destConcernId) {
                throw new Exception('Please select a destination concern for this company bill.');
            }
        }
        if ($dispatchMode === 'batch' || ($posInvoiceId === 0 && empty($customerId))) {
            if (!$destConcernId) {
                throw new Exception('Please select a valid Destination Concern for this outward dispatch.');
            }
        }
        if ($destConcernId !== null && $destConcernId === $cid) {
            throw new Exception('The destination concern must be different from the source concern.');
        }

        if ($destConcernId) {
            $destCon = DB::fetch("SELECT id, name FROM concerns WHERE id = ? AND status = 'active'", [$destConcernId]);
            if (!$destCon) {
                throw new Exception("Selected destination concern #{$destConcernId} does not exist or is inactive.");
            }
            $warehouseName = $destCon['name'];
        } else {
            $warehouseName = $customerId
                ? (string)(DB::fetchValue("SELECT name FROM customers WHERE id = ?", [$customerId]) ?: '')
                : '';
        }
        $vehicleNumber   = trim($_POST['vehicle_number'] ?? '');
        $driverName      = trim($_POST['driver_name'] ?? '');
        $driverContact   = trim($_POST['driver_contact'] ?? '');
        $transporter     = trim($_POST['transporter_name'] ?? '');
        $notes           = trim($_POST['notes'] ?? '');

        $today = date('Ymd');
        $countToday = (int)DB::fetchValue("SELECT COUNT(*) FROM outwards WHERE outward_no LIKE ?", ["OUT-{$today}-%"]);
        $seq = str_pad((string)($countToday + 1), 4, '0', STR_PAD_LEFT);
        $outwardNo = "OUT-{$today}-{$seq}";
        $gatePassNo = "GP-{$today}-{$seq}";
        $handoffCompleted = false;

        DB::beginTransaction();
        try {
            $rawTotalBoxes = $_POST['total_boxes'] ?? '';
            if (!is_scalar($rawTotalBoxes)) {
                throw new Exception('Total boxes/cartons must be a whole number.');
            }
            $this->validateOutwardDetails(
                $vehicleNumber,
                $driverName,
                $driverContact,
                $transporter,
                $notes,
                trim((string)$rawTotalBoxes)
            );
            if ($dispatchMode === 'batch') {
                // Mode 1: Production Batch Outward
                $batchId = (int)($_POST['batch_id'] ?? 0);
                if (!$batchId) {
                    throw new Exception('Please select a production batch to dispatch.');
                }

                $batch = DB::fetch(
                    "SELECT * FROM batches
                     WHERE id = ? AND concern_id = ? AND UPPER(status) = 'APPROVED'
                     LIMIT 1 FOR UPDATE",
                    [$batchId, $cid]
                );
                if (!$batch) {
                    throw new Exception('The selected production batch is no longer available for this concern.');
                }
                if (
                    empty($batch['qc_batch_id'])
                    || !DB::fetchValue(
                        "SELECT id FROM stock_ledger
                         WHERE concern_id = ? AND ref_module = 'Production QC'
                           AND ref_id = ? AND qty_in > 0
                         LIMIT 1",
                        [$cid, (int)$batch['qc_batch_id']]
                    )
                ) {
                    throw new Exception('The selected batch is not linked to QC-passed production stock.');
                }

                $puId = (int)($batch['production_unit_id'] ?: 1);
                $boxes = max(1, (int)($batch['total_boxes'] ?: 1));
                $batchVariants = DB::fetchAll(
                    "SELECT * FROM batch_items WHERE batch_id = ? ORDER BY id ASC FOR UPDATE",
                    [$batchId]
                );
                $requestedByVariant = [];
                if (!empty($batchVariants)) {
                    foreach ($batchVariants as $batchVariant) {
                        if (
                            (int)$batchVariant['concern_id'] !== $cid
                            || (int)$batchVariant['variant_id'] <= 0
                            || (float)$batchVariant['qty'] <= 0
                        ) {
                            throw new Exception('The selected production batch contains an invalid stock line.');
                        }
                        $requestedByVariant[(int)$batchVariant['variant_id']] =
                            ($requestedByVariant[(int)$batchVariant['variant_id']] ?? 0.0)
                            + (float)$batchVariant['qty'];
                    }
                } else {
                    $variantId = (int)($batch['variant_id'] ?? 0);
                    $batchQty = (float)$batch['batch_qty'];
                    if ($variantId <= 0 || $batchQty <= 0) {
                        throw new Exception('The selected production batch has no dispatchable stock lines.');
                    }
                    $requestedByVariant[$variantId] = $batchQty;
                }

                $dispatchLines = [];
                $qty = 0.0;
                foreach ($requestedByVariant as $variantId => $requestedQty) {
                    if (!DB::fetchValue(
                        "SELECT id FROM stock_ledger
                         WHERE concern_id = ? AND variant_id = ?
                           AND ref_module = 'Production QC' AND ref_id = ?
                           AND qty_in > 0
                         LIMIT 1",
                        [$cid, (int)$variantId, (int)$batch['qc_batch_id']]
                    )) {
                        throw new Exception('The selected batch contains stock that is not linked to its QC pass.');
                    }
                    $variant = DB::fetch(
                        "SELECT id FROM variants WHERE id = ? AND concern_id = ? AND status = 'active'",
                        [$variantId, $cid]
                    );
                    if (!$variant) {
                        throw new Exception('The selected production batch contains an invalid stock line.');
                    }
                    $dispatchQty = min($requestedQty, $this->mainStoreAvailableQty($cid, (int)$variantId));
                    if ($dispatchQty > 0) {
                        $dispatchLines[] = ['variant_id' => (int)$variantId, 'qty' => $dispatchQty];
                        $qty += $dispatchQty;
                    }
                }
                if ($qty <= 0) {
                    throw new Exception('No QC-passed production stock remains available in Main Store for this batch.');
                }

                DB::query("
                    INSERT INTO outwards (
                        concern_id, production_unit_id, outward_no, gate_pass_no, transfer_type,
                        destination_concern_id, outward_date, location, warehouse_name, total_boxes,
                        vehicle_number, driver_name, driver_contact, transporter_name, dispatched_by_name,
                        notes, status, dispatched_at, created_at
                    ) VALUES (
                        ?, ?, ?, ?, 'concern',
                        ?, CURDATE(), 'Main Store', ?, ?,
                        ?, ?, ?, ?, 'System Administrator',
                        ?, 'DISPATCHED', NOW(), NOW()
                    )
                ", [
                    $cid, $puId, $outwardNo, $gatePassNo,
                    $destConcernId, $warehouseName, $boxes,
                    $vehicleNumber, $driverName, $driverContact, $transporter, $notes
                ]);

                $outwardId = (int)DB::lastInsertId();

                // Link batch
                DB::query("
                    INSERT INTO production_outward_batches (concern_id, outward_id, batch_id, cartons_count, pieces_count)
                    VALUES (?, ?, ?, ?, ?)
                ", [$cid, $outwardId, $batchId, $boxes, $qty]);

                // Link and deduct only the QC-passed quantity still available in Main Store.
                foreach ($dispatchLines as $line) {
                    DB::query("
                        INSERT INTO outward_items (concern_id, outward_id, variant_id, qty)
                        VALUES (?, ?, ?, ?)
                    ", [$cid, $outwardId, $line['variant_id'], $line['qty']]);

                    $this->deductMainStoreStock($cid, $line['variant_id'], $line['qty'], $outwardId, $outwardNo);
                    $this->addOutwardTransitStock($cid, $line['variant_id'], $line['qty'], $outwardId, $outwardNo);
                }

                // Update batch status to DISPATCHED
                DB::query("UPDATE batches SET status = 'DISPATCHED', outward_id = ? WHERE id = ?", [$outwardId, $batchId]);

            } else {
                // Mode 2: Finishing Good (FG) Product Scan & Outward Dispatch
                $items = [];
                if (!empty($_POST['items']) && is_array($_POST['items'])) {
                    foreach ($_POST['items'] as $it) {
                        $vid = (int)($it['variant_id'] ?? 0);
                        $qty = (float)($it['qty'] ?? 0);
                        if ($vid > 0 && $qty > 0) {
                            $items[] = ['variant_id' => $vid, 'qty' => $qty];
                        }
                    }
                }

                // Support JSON array string from barcode scanner
                if (empty($items) && !empty($_POST['scanned_items_json'])) {
                    $decoded = json_decode($_POST['scanned_items_json'], true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $it) {
                            $vid = (int)($it['variant_id'] ?? 0);
                            $qty = (float)($it['qty'] ?? 0);
                            if ($vid > 0 && $qty > 0) {
                                $items[] = ['variant_id' => $vid, 'qty' => $qty];
                            }
                        }
                    }
                }

                // Fallback to single variant selection
                if (empty($items)) {
                    $variantId = (int)($_POST['variant_id'] ?? 0);
                    $qty       = (float)($_POST['quantity'] ?? 0);
                    if ($variantId > 0 && $qty > 0) {
                        $items[] = ['variant_id' => $variantId, 'qty' => $qty];
                    }
                }

                if (empty($items)) {
                    throw new Exception('Please scan or select at least one finishing good product to dispatch.');
                }

                if ($posInvoiceId > 0) {
                    if ($dispatchMode !== 'finished_goods') {
                        throw new Exception('A POS invoice can only be transferred as finishing goods.');
                    }
                    $authConcernId = Auth::concernId();
                    if ($authConcernId && $authConcernId !== $cid) {
                        throw new Exception('The POS invoice belongs to a different concern.');
                    }
                    $posInvoice = DB::fetch(
                        "SELECT id, concern_id, invoice_no, customer_id
                         FROM sales_invoices
                         WHERE id = ? AND concern_id = ?
                         LIMIT 1 FOR UPDATE",
                        [$posInvoiceId, $cid]
                    );
                    if (!$posInvoice) {
                        throw new Exception('The POS invoice was not found for this concern.');
                    }
                    if (DB::fetchValue("SELECT id FROM outwards WHERE sales_invoice_id = ? LIMIT 1", [$posInvoiceId])) {
                        throw new Exception("Invoice {$posInvoice['invoice_no']} is already linked to an outward.");
                    }

                    $expectedItems = [];
                    foreach (DB::fetchAll(
                        "SELECT variant_id, SUM(qty) AS qty
                         FROM sales_invoice_items
                         WHERE sales_invoice_id = ?
                         GROUP BY variant_id
                         ORDER BY variant_id",
                        [$posInvoiceId]
                    ) as $invoiceItem) {
                        $expectedItems[(int)$invoiceItem['variant_id']] = (float)$invoiceItem['qty'];
                    }
                    $submittedItems = [];
                    foreach ($items as $item) {
                        $variantId = (int)$item['variant_id'];
                        $submittedItems[$variantId] = ($submittedItems[$variantId] ?? 0) + (float)$item['qty'];
                    }
                    ksort($expectedItems);
                    ksort($submittedItems);
                    if (array_keys($expectedItems) !== array_keys($submittedItems)) {
                        throw new Exception('The outward items must match the POS invoice items.');
                    }
                    foreach ($expectedItems as $variantId => $expectedQty) {
                        if (abs($expectedQty - $submittedItems[$variantId]) > 0.0001) {
                            throw new Exception('The outward quantities must match the POS invoice quantities.');
                        }
                    }

                    $customerId = (int)$posInvoice['customer_id'];
                    $notes = trim($notes . ' POS invoice ' . $posInvoice['invoice_no'] . '; stock was deducted during billing.');
                }

                $boxes = max(1, (int)($_POST['total_boxes'] ?? 1));
                $transferType = $destConcernId
                    ? 'concern'
                    : (($dispatchMode === 'customer' || !empty($customerId)) ? 'customer' : 'concern');
                $puId = (int)DB::fetchValue("SELECT id FROM production_units WHERE concern_id = ? LIMIT 1", [$cid]) ?: 1;

                DB::query("
                    INSERT INTO outwards (
                        concern_id, production_unit_id, outward_no, sales_invoice_id, gate_pass_no, customer_id, transfer_type,
                        destination_concern_id, outward_date, location, warehouse_name, total_boxes,
                        vehicle_number, driver_name, driver_contact, transporter_name, dispatched_by_name,
                        notes, status, dispatched_at, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, CURDATE(), 'Main Store', ?, ?,
                        ?, ?, ?, ?, 'System Administrator',
                        ?, 'DISPATCHED', NOW(), NOW()
                    )
                ", [
                    $cid, $puId, $outwardNo, $posInvoiceId ?: null, $gatePassNo, $customerId, $transferType,
                    $destConcernId, $warehouseName, $boxes,
                    $vehicleNumber, $driverName, $driverContact, $transporter, $notes
                ]);

                $outwardId = (int)DB::lastInsertId();
                $totalPiecesDispatched = 0;

                foreach ($items as $it) {
                    $vId = $it['variant_id'];
                    $iQty = $it['qty'];
                    $totalPiecesDispatched += $iQty;

                    DB::query("
                        INSERT INTO outward_items (concern_id, outward_id, variant_id, qty)
                        VALUES (?, ?, ?, ?)
                    ", [$cid, $outwardId, $vId, $iQty]);

                    if ($posInvoiceId === 0) {
                        StockService::move($cid, $vId, 'Main Store', 0.00, $iQty, 'Outward', $outwardId, "Outward FG dispatch {$outwardNo}");
                        $this->addOutwardTransitStock($cid, $vId, $iQty, $outwardId, $outwardNo);
                    }
                }

                $qty = $totalPiecesDispatched;
            }

            DB::commit();
            $handoffCompleted = $posInvoiceId > 0;
            $this->flash('success', "Outward Manifest {$outwardNo} successfully dispatched via {$vehicleNumber}! ({$qty} pcs, {$boxes} box)");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', "Dispatch failed: " . $e->getMessage());
        }

        $redirectPath = '/production/outward';
        if ($posInvoiceId > 0) {
            $redirectPath = $handoffCompleted
                ? '/production/outward'
                : "/production/outward?pos_invoice_id={$posInvoiceId}";
        }
        $this->redirect($redirectPath);
    }

    /**
     * Update Outward Dispatch Details (POST)
     */
    public function update(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);

        $out = DB::fetch("SELECT * FROM outwards WHERE id = ?", [$id]);
        if (!$out) {
            $this->flash('error', 'Outward record not found.');
            $this->redirect('/production/outward');
            return;
        }
        if (!$this->isOutwardInSourceScope($out)) {
            $this->flash('error', 'Access denied: this outward belongs to a different source concern.');
            $this->redirect('/production/outward');
            return;
        }

        $vehicle       = trim($_POST['vehicle_number'] ?? $out['vehicle_number']);
        $driver        = trim($_POST['driver_name'] ?? $out['driver_name']);
        $contact       = trim($_POST['driver_contact'] ?? $out['driver_contact']);
        $warehouse     = trim($_POST['warehouse_name'] ?? ($out['warehouse_name'] ?? 'wareouse (WH-01)'));
        $destConcernId = !empty($_POST['destination_concern_id']) ? (int)$_POST['destination_concern_id'] : $out['destination_concern_id'];
        $boxes         = max(1, (int)($_POST['total_boxes'] ?? ($out['total_boxes'] ?? 1)));
        $status        = strtoupper(trim($_POST['status'] ?? $out['status']));
        $currentStatus = strtolower((string)$out['status']);

        if ($destConcernId !== null && (int)$destConcernId === (int)$out['concern_id']) {
            $this->flash('error', 'The destination concern must be different from the source concern.');
            $this->redirect('/production/outward');
            return;
        }
        if (
            (int)$destConcernId !== (int)$out['destination_concern_id'] &&
            in_array($currentStatus, ['dispatched', 'partial', 'received'], true)
        ) {
            $this->flash('error', 'The destination concern cannot be changed after dispatch.');
            $this->redirect('/production/outward');
            return;
        }
        if ($status === 'RECEIVED' && $currentStatus !== 'received') {
            $this->flash('error', 'An outward can be marked received only after inward verification is completed.');
            $this->redirect('/production/outward');
            return;
        }

        try {
            DB::query("
                UPDATE outwards 
                SET vehicle_number = ?, driver_name = ?, driver_contact = ?, warehouse_name = ?, 
                    destination_concern_id = ?, total_boxes = ?, status = ?
                WHERE id = ?
            ", [$vehicle, $driver, $contact, $warehouse, $destConcernId, $boxes, $status, $id]);

            $this->flash('success', "Outward dispatch '{$out['outward_no']}' updated successfully.");
        } catch (\Throwable $e) {
            $this->flash('error', "Update failed: " . $e->getMessage());
        }

        $this->redirect('/production/outward');
    }

    /**
     * Delete Outward Consignment (POST)
     */
    public function delete(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);

        $out = DB::fetch("SELECT * FROM outwards WHERE id = ?", [$id]);
        if (!$out) {
            $this->flash('error', 'Outward record not found.');
            $this->redirect('/production/outward');
            return;
        }
        if (!$this->isOutwardInSourceScope($out)) {
            $this->flash('error', 'Access denied: this outward belongs to a different source concern.');
            $this->redirect('/production/outward');
            return;
        }

        try {
            DB::beginTransaction();
            $lockedOutward = DB::fetch("SELECT * FROM outwards WHERE id = ? FOR UPDATE", [$id]);
            if (!$lockedOutward) {
                throw new Exception('Outward record not found.');
            }
            if ((int)DB::fetchValue("SELECT COUNT(*) FROM inwards WHERE outward_id = ?", [$id]) > 0) {
                throw new Exception('An outward with recorded inward receipts cannot be deleted.');
            }
            $out = $lockedOutward;

            // Find linked batch and restore status to APPROVED
            $batchId = (int)DB::fetchValue("SELECT batch_id FROM production_outward_batches WHERE outward_id = ? LIMIT 1", [$id]);
            if ($batchId) {
                DB::query("UPDATE batches SET status = 'APPROVED' WHERE id = ?", [$batchId]);
            }

            // Restore deducted items if stock transfer
            $items = DB::fetchAll("SELECT * FROM outward_items WHERE outward_id = ?", [$id]);
            if (empty($out['sales_invoice_id'])) {
                foreach ($items as $item) {
                    $transitLocation = "IN_TRANSIT:OUTWARD:{$id}";
                    $transitQty = DB::fetchValue(
                        "SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = ? FOR UPDATE",
                        [(int)$out['concern_id'], (int)$item['variant_id'], $transitLocation]
                    );
                    $hasTransitLedger = DB::fetchValue(
                        "SELECT id FROM stock_ledger
                         WHERE concern_id = ? AND variant_id = ? AND ref_module = 'Outward Transit' AND ref_id = ?
                         LIMIT 1",
                        [(int)$out['concern_id'], (int)$item['variant_id'], $id]
                    );
                    if ($hasTransitLedger) {
                        if ($transitQty === false || $transitQty === null || (float)$transitQty + 0.0001 < (float)$item['qty']) {
                            throw new Exception("Transit stock for variant #{$item['variant_id']} cannot be fully restored.");
                        }
                        StockService::move(
                            (int)$out['concern_id'],
                            (int)$item['variant_id'],
                            $transitLocation,
                            0.00,
                            (float)$item['qty'],
                            'Outward Transit Reversal',
                            $id,
                            "Reverted outward deletion {$out['outward_no']}"
                        );
                    }
                    StockService::move((int)$out['concern_id'], (int)$item['variant_id'], 'Main Store', (float)$item['qty'], 0.00, 'inward', $id, "Reverted outward deletion {$out['outward_no']}");
                }
            }

            DB::query("DELETE FROM production_outward_batches WHERE outward_id = ?", [$id]);
            DB::query("DELETE FROM outward_items WHERE outward_id = ?", [$id]);
            DB::query("DELETE FROM outwards WHERE id = ?", [$id]);

            DB::commit();
            $this->flash('success', "Outward consignment '{$out['outward_no']}' deleted successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', "Delete failed: " . $e->getMessage());
        }

        $this->redirect('/production/outward');
    }

    /**
     * Dispatch Outward (POST)
     */
    public function dispatch(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $out = DB::fetch("SELECT id, concern_id FROM outwards WHERE id = ?", [$id]);
        if (!$out) {
            $this->flash('error', 'Outward record not found.');
            $this->redirect('/production/outward');
            return;
        }
        if (!$this->isOutwardInSourceScope($out)) {
            $this->flash('error', 'Access denied: this outward belongs to a different source concern.');
            $this->redirect('/production/outward');
            return;
        }

        try {
            DB::query("UPDATE outwards SET status = 'DISPATCHED', dispatched_at = NOW() WHERE id = ?", [$id]);
            $this->flash('success', "Outward #{$id} DISPATCHED! Stock in transit.");
        } catch (\Throwable $e) {
            $this->flash('error', "Dispatch failed: " . $e->getMessage());
        }

        $this->redirect('/production/outward');
    }

    /**
     * AJAX Traceability Details Endpoint
     */
    public function traceability(int|string $id): void {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        $out = DB::fetch("
            SELECT o.*, 
                   con.name AS source_concern_name, con.code AS source_concern_code,
                   dest_con.name AS dest_concern_name, dest_con.code AS dest_concern_code,
                   c.name AS customer_name,
                   COALESCE(pob.cartons_count, o.total_boxes, 1) AS cartons_count,
                   COALESCE((SELECT SUM(qty) FROM outward_items oi WHERE oi.outward_id = o.id), pob.pieces_count, 490) AS pieces_count,
                   b.batch_no,
                   COALESCE(v.name, 'KIDS - BABY - DRESS') AS product_name,
                   v.sku_code, v.size,
                   pu.name AS production_unit_name, pu.location AS production_unit_location
            FROM outwards o
            LEFT JOIN concerns con ON o.concern_id = con.id
            LEFT JOIN concerns dest_con ON o.destination_concern_id = dest_con.id
            LEFT JOIN customers c ON o.customer_id = c.id
            LEFT JOIN production_outward_batches pob ON pob.outward_id = o.id
            LEFT JOIN batches b ON pob.batch_id = b.id
            LEFT JOIN variants v ON (b.variant_id = v.id OR (SELECT oi.variant_id FROM outward_items oi WHERE oi.outward_id = o.id LIMIT 1) = v.id)
            LEFT JOIN production_units pu ON o.production_unit_id = pu.id
            WHERE o.id = ? OR o.outward_no = ?
            LIMIT 1
        ", [$id, $id]);

        if (!$out) {
            echo json_encode(['success' => false, 'error' => 'Outward not found']);
            return;
        }
        if (!$this->isOutwardInSourceScope($out)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied: outward belongs to a different source concern.']);
            return;
        }

        $destinationLabel = $out['dest_concern_name'] ?: ($out['customer_name'] ?: ($out['warehouse_name'] ?: 'wareouse (WH-01)'));

        $itemsList = DB::fetchAll("
            SELECT oi.qty, v.name AS variant_name, v.sku_code, v.size
            FROM outward_items oi
            JOIN variants v ON oi.variant_id = v.id
            WHERE oi.outward_id = ?
        ", [$out['id']]);

        echo json_encode([
            'success'            => true,
            'outward_no'         => $out['outward_no'],
            'batch_no'           => $out['batch_no'] ?: 'Finishing Good Outward',
            'product_name'       => $out['product_name'],
            'sku_code'           => $out['sku_code'] ?? 'GRM-STY-0001-XL',
            'size'               => $out['size'] ?? 'XL',
            'pieces_count'       => (int)$out['pieces_count'],
            'cartons_count'      => (int)$out['cartons_count'],
            'vehicle_number'     => $out['vehicle_number'] ?: 'rtt31232',
            'driver_name'        => $out['driver_name'] ?: 'Vanaja',
            'driver_contact'     => $out['driver_contact'] ?: '9943557998',
            'source_concern'     => $out['source_concern_name'] ?: 'GRM Garments Pvt Ltd',
            'destination'        => $destinationLabel,
            'dispatched_by'      => $out['dispatched_by_name'] ?: 'System Administrator',
            'dispatched_at'      => date('d M Y, h:i A', strtotime($out['dispatched_at'] ?: $out['created_at'])),
            'status'             => strtoupper($out['status']),
            'production_unit'    => $out['production_unit_name'] ?: 'Kadampadi',
            'items'              => $itemsList,
        ]);
        return;
    }
}
