<?php
// app/controllers/DamageController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Auth;
use App\Core\ModuleGuard;
use App\Services\QrService;
use App\Services\StockService;
use App\Services\DamageService;
use Exception;

class DamageController extends Controller {
    public function index(): void {
        $cid = $this->requestConcernId();
        $rawSearch = $_GET['search'] ?? '';
        $rawSourceType = $_GET['source'] ?? '';
        $rawLocation = $_GET['location'] ?? '';
        $rawStatus = $_GET['status'] ?? '';
        $search = is_scalar($rawSearch) ? trim((string)$rawSearch) : '';
        $sourceType = is_scalar($rawSourceType) ? strtolower(trim((string)$rawSourceType)) : '';
        $location = is_scalar($rawLocation) ? trim((string)$rawLocation) : '';
        $status = is_scalar($rawStatus) ? strtoupper(trim((string)$rawStatus)) : '';

        $sql = "SELECT cd.*, v.name AS variant_name, v.sku_code, v.size, v.price AS unit_price, v.mrp,
                       c.name AS category_name, u.name AS user_name,
                       COALESCE(cd.status, 'LOGGED') AS current_status,
                       EXISTS(
                           SELECT 1 FROM stock_ledger damage_credit
                           WHERE damage_credit.ref_module = 'Concern Damage'
                             AND damage_credit.ref_id = cd.id
                             AND damage_credit.qty_in > 0
                       ) AS has_quarantine,
                       origin.ref_module AS origin_module, origin.ref_id AS origin_ref_id,
                       con.name AS concern_name, con.code AS concern_code
                FROM concern_damages cd
                JOIN variants v ON cd.variant_id = v.id
                LEFT JOIN categories c ON v.category_id = c.id
                LEFT JOIN concerns con ON cd.concern_id = con.id
                LEFT JOIN users u ON cd.created_by = u.id
                LEFT JOIN (
                    SELECT inbound.concern_id, inbound.variant_id, inbound.location, inbound.ref_module, inbound.ref_id
                    FROM stock_ledger inbound
                    JOIN (
                        SELECT concern_id, variant_id, location, MAX(id) AS latest_id
                        FROM stock_ledger
                        WHERE qty_in > 0
                          AND ref_module NOT IN ('Concern Damage', 'Warehouse Damage', 'Shop Damage')
                        GROUP BY concern_id, variant_id, location
                    ) latest ON latest.latest_id = inbound.id
                ) origin
                  ON origin.concern_id = cd.concern_id
                 AND origin.variant_id = cd.variant_id
                 AND origin.location = cd.location
                WHERE 1 = 1";
        $params = [];
        if ($cid) {
            $sql .= " AND cd.concern_id = ?";
            $params[] = $cid;
        }
        if ($search !== '') {
            $sql .= " AND (cd.damage_no LIKE ? OR v.sku_code LIKE ? OR v.name LIKE ? OR cd.reason LIKE ?)";
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($location !== '') {
            $sql .= " AND cd.location = ?";
            $params[] = $location;
        }
        if (in_array($status, ['LOGGED', 'INSPECTED', 'RESOLVED', 'CANCELLED'], true)) {
            $sql .= " AND COALESCE(cd.status, 'LOGGED') = ?";
            $params[] = $status;
        }
        if ($sourceType === 'purchase') {
            $sql .= " AND origin.ref_module IN ('Purchase Inward', 'Purchase Inward Item')";
        } elseif ($sourceType === 'own') {
            $sql .= " AND origin.ref_module IN ('Production Inward', 'Production Stock Adjustment')";
        }
        $sql .= " ORDER BY cd.id DESC LIMIT 500";
        $damages = DB::fetchAll($sql, $params);

        $metricsSql = "SELECT
                           COALESCE(SUM(CASE WHEN COALESCE(cd.status, 'LOGGED') IN ('LOGGED', 'INSPECTED') THEN cd.qty ELSE 0 END), 0) AS damaged_qty,
                           COALESCE(SUM(CASE WHEN COALESCE(cd.status, 'LOGGED') = 'RESOLVED' AND cd.resolution_action = 'REWORK' THEN cd.qty ELSE 0 END), 0) AS rework_qty,
                           COALESCE(SUM(CASE WHEN COALESCE(cd.status, 'LOGGED') IN ('LOGGED', 'INSPECTED') THEN cd.qty * COALESCE(NULLIF(v.price, 0), v.mrp, 0) ELSE 0 END), 0) AS estimated_loss,
                           COALESCE(SUM(CASE WHEN COALESCE(cd.status, 'LOGGED') = 'RESOLVED' AND cd.resolution_action IN ('SALVAGE', 'REPAIR') THEN cd.qty ELSE 0 END), 0) AS recovered_qty,
                           COALESCE(SUM(CASE WHEN COALESCE(cd.status, 'LOGGED') = 'RESOLVED' AND cd.resolution_action IN ('SALVAGE', 'REPAIR') THEN cd.qty * COALESCE(NULLIF(v.price, 0), v.mrp, 0) ELSE 0 END), 0) AS recovered_value
                       FROM concern_damages cd
                       JOIN variants v ON v.id = cd.variant_id";
        $metricsParams = [];
        if ($cid) {
            $metricsSql .= " WHERE cd.concern_id = ?";
            $metricsParams[] = $cid;
        }
        $metrics = DB::fetch($metricsSql, $metricsParams) ?: [];

        $locations = $this->getAvailableLocations($cid);
        $damageLocationsSql = "SELECT DISTINCT cd.location FROM concern_damages cd";
        $damageLocationParams = [];
        if ($cid) {
            $damageLocationsSql .= " WHERE cd.concern_id = ?";
            $damageLocationParams[] = $cid;
        }
        $damageLocations = DB::fetchAll($damageLocationsSql . " ORDER BY cd.location ASC", $damageLocationParams);
        foreach ($damageLocations as $damageLocation) {
            $value = trim((string)$damageLocation['location']);
            if ($value !== '' && !in_array($value, $locations, true)) {
                $locations[] = $value;
            }
        }
        sort($locations, SORT_NATURAL | SORT_FLAG_CASE);

        $this->render('concern/concern_damage', [
            'pageTitle'     => 'Concern Damage & Rework',
            'currentModule' => 'concern_damage',
            'damages'       => $damages,
            'metrics'       => $metrics,
            'locations'     => $locations,
            'selectedCid'   => $cid,
            'search'        => $search,
            'sourceType'    => $sourceType,
            'selectedLocation' => $location,
            'status'        => $status,
            'canAddDamage'  => Auth::isSuperAdmin() || Auth::can('concern_damage', 'add'),
            'canEditDamage' => Auth::isSuperAdmin() || Auth::can('concern_damage', 'edit'),
            'canDeleteDamage' => Auth::isSuperAdmin() || Auth::can('concern_damage', 'delete'),
        ]);
    }

    public function entry(): void {
        ModuleGuard::enforce('concern_damage', 'add');
        $cid = $this->requestConcernId();
        $locations = $cid ? $this->getAvailableLocations($cid) : [];
        $concernName = $cid
            ? (string)DB::fetchValue("SELECT name FROM concerns WHERE id = ?", [$cid])
            : '';
        $this->render('concern/concern_damage_entry', [
            'pageTitle' => 'Log Concern Damage',
            'currentModule' => 'concern_damage',
            'selectedCid' => $cid,
            'concernName' => $concernName,
            'locations' => $locations,
        ]);
    }

    public function scan(): void {
        ModuleGuard::enforce('concern_damage', 'add');
        $this->validateCsrf();
        $concernId = $this->requestConcernId();
        $rawTagCode = $_POST['tag_code'] ?? '';
        $rawLocation = $_POST['location'] ?? '';
        $tagCode = is_scalar($rawTagCode) ? strtoupper(trim((string)$rawTagCode)) : '';
        $location = is_scalar($rawLocation) ? trim((string)$rawLocation) : '';
        if (!$concernId || $tagCode === '' || strlen($tagCode) > 100 || !$this->isAvailableLocation($concernId, $location)) {
            $this->renderJson(['success' => false, 'message' => 'Select a valid stock location and scan a product label.'], 422);
            return;
        }

        try {
            $variant = $this->resolveConcernDamageTag($concernId, $tagCode);
            $availableQty = StockService::getQty($concernId, (int)$variant['id'], $location);
            if ($availableQty <= 0) {
                $otherLocations = DB::fetchAll(
                    "SELECT location, qty FROM stocks
                     WHERE concern_id = ? AND variant_id = ? AND qty > 0
                       AND location NOT LIKE '%:DAMAGED'
                       AND location NOT LIKE '%:RESERVED'
                       AND location NOT LIKE '%:IN_TRANSIT'
                     ORDER BY location ASC",
                    [$concernId, (int)$variant['id']]
                );
                if ($otherLocations) {
                    $availableAt = array_map(
                        static function (array $stock): string {
                            $qty = rtrim(rtrim(number_format((float)$stock['qty'], 2, '.', ''), '0'), '.');
                            return $stock['location'] . ' (' . $qty . ' pcs)';
                        },
                        $otherLocations
                    );
                    throw new \RuntimeException(
                        "No stock at '{$location}'. Available at: " . implode(', ', $availableAt) . '.'
                    );
                }
                throw new \RuntimeException('No available stock for this label at the selected location.');
            }
            $this->renderJson([
                'success' => true,
                'item' => [
                    'variant_id' => (int)$variant['id'],
                    'tag_code' => $tagCode,
                    'sku_code' => (string)$variant['sku_code'],
                    'variant_name' => (string)$variant['name'],
                    'category_name' => (string)($variant['category_name'] ?? 'General'),
                    'size' => (string)($variant['size'] ?? ''),
                    'available_qty' => $availableQty,
                    'unit_price' => (float)($variant['price'] ?: $variant['mrp']),
                ],
            ]);
        } catch (\RuntimeException $e) {
            $this->renderJson(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function store(): void {
        ModuleGuard::enforce('concern_damage', 'add');
        $this->validateCsrf();
        $cid = $this->requestConcernId();
        $rawLocation = $_POST['location'] ?? '';
        $location = is_scalar($rawLocation) ? trim((string)$rawLocation) : '';
        $rawItems = $_POST['scanned_items'] ?? '';
        $items = is_string($rawItems) ? json_decode($rawItems, true) : null;
        if (!$cid || !$this->isAvailableLocation($cid, $location) || !is_array($items) || !$items || count($items) > 500) {
            $this->flash('error', 'Select a valid stock location and scan at least one product label.');
            $this->redirect('/m/concern_damage/new' . (Auth::isSuperAdmin() && $cid ? '?concern_id=' . $cid : ''));
            return;
        }

        try {
            $validatedItems = $this->validateScannedDamageItems($cid, $items);
            DB::beginTransaction();
            foreach ($validatedItems as $item) {
                $stock = DB::fetch(
                    "SELECT qty FROM stocks WHERE concern_id = ? AND variant_id = ? AND location = ? FOR UPDATE",
                    [$cid, $item['variant_id'], $location]
                );
                $availableQty = $stock ? (float)$stock['qty'] : 0.0;
                if ($item['qty'] - $availableQty > 0.00001) {
                    throw new \RuntimeException("Only {$availableQty} piece(s) remain for {$item['variant_name']} at this location.");
                }
                DamageService::log(
                    $cid,
                    $item['variant_id'],
                    'EXACT',
                    $location,
                    $item['qty'],
                    $item['reason'],
                    null,
                    Auth::id(),
                    null,
                    'MODERATE',
                    'CONCERN',
                    true
                );
            }
            DB::commit();
            $this->flash('success', count($validatedItems) . ' damage case(s) logged; stock moved to quarantine.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to record concern damage: ' . $e->getMessage());
        }

        $this->redirect('/m/concern_damage' . (Auth::isSuperAdmin() && $cid ? '?concern_id=' . $cid : ''));
    }

    private function requestConcernId(): ?int {
        if (Auth::isSuperAdmin()) {
            $rawConcernId = $_GET['concern_id'] ?? $_POST['concern_id'] ?? null;
            if (!is_scalar($rawConcernId)) {
                return null;
            }
            $concernId = filter_var($rawConcernId, FILTER_VALIDATE_INT);
            return $concernId && $concernId > 0 ? (int)$concernId : null;
        }

        $concernId = Auth::concernId();
        return $concernId ? (int)$concernId : null;
    }

    private function getAvailableLocations(?int $concernId): array {
        if (!$concernId) {
            return [];
        }

        $rows = DB::fetchAll(
            "SELECT DISTINCT location
             FROM stocks
             WHERE concern_id = ? AND qty > 0
               AND CHAR_LENGTH(location) <= 50
               AND location NOT LIKE '%:DAMAGED'
               AND location NOT LIKE '%:RESERVED'
               AND location NOT LIKE '%:IN_TRANSIT'
             ORDER BY location ASC",
            [$concernId]
        );
        return array_values(array_filter(array_map(
            static fn(array $row): string => trim((string)$row['location']),
            $rows
        )));
    }

    private function isAvailableLocation(int $concernId, string $location): bool {
        if ($location === '' || strlen($location) > 50 || preg_match('/:(DAMAGED|RESERVED|IN_TRANSIT)$/i', $location)) {
            return false;
        }

        return (int)DB::fetchValue(
            "SELECT COUNT(*) FROM stocks
             WHERE concern_id = ? AND location = ? AND qty > 0",
            [$concernId, $location]
        ) > 0;
    }

    private function resolveConcernDamageTag(int $concernId, string $tagCode): array {
        if (QrService::parseFinishedGoodsCode($tagCode)) {
            $variant = QrService::lookupVariant($tagCode, $concernId);
            if ($variant && ($variant['status'] ?? '') === 'active') {
                return $variant;
            }
        }

        $variantId = QrService::parseVariantId($tagCode);
        if ($variantId) {
            $variant = DB::fetch(
                "SELECT v.id, v.name, v.sku_code, v.size, v.price, v.mrp, c.name AS category_name
                 FROM variants v
                 LEFT JOIN categories c ON c.id = v.category_id
                 WHERE v.id = ? AND v.concern_id = ? AND v.status = 'active'",
                [$variantId, $concernId]
            );
            if ($variant) {
                return $variant;
            }
        }

        $parsed = QrService::parsePayload($tagCode);
        if ($parsed) {
            $variant = DB::fetch(
                "SELECT v.id, v.name, v.sku_code, v.size, v.price, v.mrp, c.name AS category_name
                 FROM variants v
                 LEFT JOIN categories c ON c.id = v.category_id
                 WHERE v.id = ? AND v.concern_id = ? AND v.category_id = ? AND v.subcategory_id = ? AND v.status = 'active'",
                [$parsed['variant_id'], $concernId, $parsed['category_id'], $parsed['subcategory_id']]
            );
            if ($variant) {
                return $variant;
            }
        }

        $variants = DB::fetchAll(
            "SELECT v.id, v.name, v.sku_code, v.size, v.price, v.mrp, v.attributes,
                    (SELECT pbi.color
                     FROM purchase_bill_items pbi
                     WHERE pbi.concern_id = v.concern_id
                       AND pbi.section_type = 'finished_goods'
                       AND (pbi.variant_id = v.id OR (v.catalog_id IS NOT NULL AND pbi.catalog_id = v.catalog_id))
                       AND COALESCE(pbi.color, '') <> ''
                     ORDER BY pbi.id DESC
                     LIMIT 1) AS purchase_color,
                    c.name AS category_name, sc.name AS subcategory_name
             FROM variants v
             LEFT JOIN categories c ON c.id = v.category_id
             LEFT JOIN subcategories sc ON sc.id = v.subcategory_id
             WHERE v.concern_id = ? AND v.status = 'active'",
            [$concernId]
        );
        $matches = [];
        foreach ($variants as $variant) {
            if (strtoupper(trim((string)$variant['sku_code'])) === $tagCode) {
                $matches[(int)$variant['id']] = $variant;
                continue;
            }

            $attributes = json_decode((string)($variant['attributes'] ?? ''), true);
            $attributes = is_array($attributes) ? $attributes : [];
            $color = trim((string)($attributes['color'] ?? ''));
            $purchaseColor = trim((string)($variant['purchase_color'] ?? ''));
            $colors = array_unique([$color, $purchaseColor, '', 'STANDARD']);
            $designs = array_unique([
                trim((string)($variant['size'] ?? '')),
                trim((string)($variant['subcategory_name'] ?? '')),
                '',
            ]);
            foreach ($colors as $candidateColor) {
                foreach ($designs as $candidateDesign) {
                    if (QrService::generateTagCode(
                        (string)$variant['name'],
                        $candidateColor,
                        $candidateDesign,
                        (string)$variant['id']
                    ) === $tagCode) {
                        $matches[(int)$variant['id']] = $variant;
                    }
                }
            }
        }

        if (!$matches) {
            throw new \RuntimeException('This QR tag does not match an active product in this concern.');
        }
        if (count($matches) !== 1) {
            throw new \RuntimeException('This QR tag matches multiple variants. Scan a unique product label.');
        }

        return reset($matches);
    }

    private function validateScannedDamageItems(int $concernId, array $items): array {
        $validated = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \RuntimeException('The scanned queue contains an invalid item.');
            }
            $rawVariantId = $item['variant_id'] ?? null;
            $rawQty = $item['qty'] ?? null;
            $rawReason = $item['reason'] ?? null;
            if (!is_scalar($rawVariantId) || !is_scalar($rawReason) || !is_scalar($rawQty) || !is_numeric($rawQty)) {
                throw new \RuntimeException('Each queued item must include a valid variant, quantity, and reason.');
            }
            $variantId = filter_var($rawVariantId, FILTER_VALIDATE_INT);
            $reason = trim((string)$rawReason);
            if (!$variantId || $variantId < 1) {
                throw new \RuntimeException('Each queued item must include a valid variant and quantity.');
            }
            $qty = (float)$rawQty;
            if (!is_finite($qty) || $qty < 1 || abs($qty - round($qty)) > 0.000001) {
                throw new \RuntimeException('Damage quantities must be whole pieces greater than zero.');
            }
            if ($reason === '' || strlen($reason) > 255) {
                throw new \RuntimeException('Enter a damage reason of at most 255 characters for every queued item.');
            }

            $variant = DB::fetch(
                "SELECT id, name FROM variants WHERE id = ? AND concern_id = ? AND status = 'active'",
                [$variantId, $concernId]
            );
            if (!$variant) {
                throw new \RuntimeException('A queued variant is inactive or does not belong to this concern.');
            }

            $validated[] = [
                'variant_id' => (int)$variant['id'],
                'variant_name' => (string)$variant['name'],
                'qty' => $qty,
                'reason' => $reason,
            ];
        }

        return $validated;
    }

    public function update(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $dmg = DB::fetch("SELECT * FROM concern_damages WHERE id = ?", [$id]);
        if (!$dmg) {
            $this->flash('error', 'Damage record not found.');
            $this->redirect('/m/concern_damage');
        }

        $location = trim($_POST['location'] ?? $dmg['location']);
        $reason   = trim($_POST['reason'] ?? $dmg['reason']);
        $newQty   = (float)($_POST['qty'] ?? $dmg['qty']);

        DB::beginTransaction();
        try {
            $diff = $newQty - (float)$dmg['qty'];
            if ($diff != 0) {
                // If diff > 0, deduct more stock. If diff < 0, return stock.
                if ($diff > 0) {
                    StockService::move($dmg['concern_id'], $dmg['variant_id'], $location, 0.00, $diff, 'Concern Damage', $id, "Updated Damage [{$dmg['damage_no']}]: Additional deduction", Auth::id());
                } else {
                    StockService::move($dmg['concern_id'], $dmg['variant_id'], $location, abs($diff), 0.00, 'Concern Damage', $id, "Updated Damage [{$dmg['damage_no']}]: Restored partial stock", Auth::id());
                }
            }

            DB::query("UPDATE concern_damages SET qty = ?, location = ?, reason = ? WHERE id = ?", [$newQty, $location, $reason, $id]);
            DB::commit();
            $this->flash('success', "Internal damage '{$dmg['damage_no']}' updated successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to update damage: ' . $e->getMessage());
        }

        $this->redirect('/m/concern_damage');
    }

    public function delete(): void {
        ModuleGuard::enforce('concern_damage', 'delete');
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $dmg = DB::fetch("SELECT * FROM concern_damages WHERE id = ?", [$id]);
        if (!$dmg) {
            $this->flash('error', 'Damage record not found.');
            $this->redirect('/m/concern_damage');
            return;
        }
        if (in_array(strtoupper((string)($dmg['status'] ?? 'LOGGED')), ['RESOLVED', 'CANCELLED'], true)) {
            $this->flash('error', 'Resolved or cancelled damage cases cannot be deleted.');
            $this->redirect('/m/concern_damage');
            return;
        }

        DB::beginTransaction();
        try {
            $hasQuarantine = (int)DB::fetchValue(
                "SELECT COUNT(*) FROM stock_ledger
                 WHERE ref_module = 'Concern Damage' AND ref_id = ? AND qty_in > 0",
                [$id]
            ) > 0;
            if ($hasQuarantine) {
                DamageService::cancel($id, 'Cancelled while removing the damage record.', Auth::id(), 'CONCERN');
            } else {
                StockService::move(
                    (int)$dmg['concern_id'],
                    (int)$dmg['variant_id'],
                    (string)$dmg['location'],
                    (float)$dmg['qty'],
                    0.00,
                    'Concern Damage',
                    $id,
                    "Reversal of deleted Damage [{$dmg['damage_no']}]",
                    Auth::id()
                );
            }
            DB::query("DELETE FROM concern_damages WHERE id = ?", [$id]);
            DB::commit();
            $this->flash('success', "Damage record '{$dmg['damage_no']}' deleted and {$dmg['qty']} units restored to live stock.");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->flash('error', 'Failed to delete damage: ' . $e->getMessage());
        }

        $this->redirect('/m/concern_damage');
    }

    public function resolve(): void {
        ModuleGuard::enforce('concern_damage', 'edit');
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $action = strtoupper(trim($_POST['resolution_action'] ?? ''));
        $reason = trim($_POST['resolution_reason'] ?? '');
        $notes = trim($_POST['resolution_notes'] ?? '');
        $lossAmount = (float)($_POST['loss_amount'] ?? 0);
        $replacementQr = trim($_POST['replacement_qr'] ?? '');

        if (!$id || $action === '') {
            $this->flash('error', 'Invalid resolution parameters.');
            $this->redirect('/m/concern_damage');
            return;
        }

        try {
            if (!(int)DB::fetchValue(
                "SELECT COUNT(*) FROM stock_ledger
                 WHERE ref_module = 'Concern Damage' AND ref_id = ? AND qty_in > 0",
                [$id]
            )) {
                throw new \RuntimeException('This legacy write-off has no quarantined stock to resolve.');
            }
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
                'CONCERN'
            );
            $this->flash('success', "Concern damage resolved via action '{$action}'.");
        } catch (\Throwable $e) {
            $this->flash('error', 'Resolution failed: ' . $e->getMessage());
        }

        $this->redirect('/m/concern_damage');
    }

    public function inwardReplacement(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $scannedQr = trim($_POST['scanned_qr'] ?? '');
        $qty = (float)($_POST['inward_qty'] ?? 1);

        if (!$id || $scannedQr === '' || $qty <= 0) {
            $this->flash('error', 'Please provide a valid replacement QR and inward quantity.');
            $this->redirect('/m/concern_damage');
            return;
        }

        try {
            DamageService::inwardReplacement($id, $scannedQr, $qty, Auth::id(), 'CONCERN');
            $this->flash('success', "Replacement inwarded successfully into live AVAILABLE stock.");
        } catch (\Throwable $e) {
            $this->flash('error', 'Inward replacement failed: ' . $e->getMessage());
        }

        $this->redirect('/m/concern_damage');
    }

    public function cancel(): void {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['cancel_reason'] ?? 'Cancelled by user');

        try {
            DamageService::cancel($id, $reason, Auth::id(), 'CONCERN');
            $this->flash('success', "Damage record was cancelled and stock restored to AVAILABLE.");
        } catch (\Throwable $e) {
            $this->flash('error', 'Cancellation failed: ' . $e->getMessage());
        }

        $this->redirect('/m/concern_damage');
    }

    public function exportConcernDamage(): void {
        $cid = Auth::concernId();
        $format = strtolower($_GET['format'] ?? 'excel');
        $period = $_GET['period'] ?? 'all';
        $singleDate = $_GET['single_date'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $dateScope = \App\Services\ExportService::resolveDateRange($period, $singleDate, $fromDate, $toDate);

        $sql = "SELECT cd.*, v.name AS variant_name, v.sku_code, v.size, v.price, u.name AS user_name,
                       con.name AS concern_name, con.code AS concern_code
                FROM concern_damages cd
                JOIN variants v ON cd.variant_id = v.id
                LEFT JOIN concerns con ON cd.concern_id = con.id
                LEFT JOIN users u ON cd.created_by = u.id";
        $params = [];
        $where = [];
        if ($cid) {
            $where[] = "cd.concern_id = ?";
            $params[] = $cid;
        }
        if ($dateScope['start_date'] && $dateScope['end_date']) {
            $where[] = "DATE(cd.created_at) BETWEEN ? AND ?";
            $params[] = $dateScope['start_date'];
            $params[] = $dateScope['end_date'];
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY cd.id DESC";
        $damages = DB::fetchAll($sql, $params);

        $headers = [
            'Damage No', 'Date', 'SKU Code', 'Variant Name', 'Size', 
            'Damaged Qty', 'Unit Cost (₹)', 'Est. Loss (₹)', 'Location', 'Reason', 'Logged By', 'Concern'
        ];

        $rows = [];
        $totQty = 0;
        $totLoss = 0;
        foreach ($damages as $d) {
            $loss = (float)$d['qty'] * (float)($d['price'] ?? 0);
            $totQty += (float)$d['qty'];
            $totLoss += $loss;
            $rows[] = [
                $d['damage_no'],
                date('d M Y, h:i A', strtotime($d['created_at'])),
                $d['sku_code'],
                $d['variant_name'],
                $d['size'] ?? '-',
                (float)$d['qty'],
                '₹' . number_format((float)($d['price'] ?? 0), 2),
                '₹' . number_format($loss, 2),
                $d['location'] ?? 'Main Store',
                $d['reason'] ?? '-',
                $d['user_name'] ?? 'System',
                $d['concern_name'] ?? 'Consolidated'
            ];
        }

        $totals = ['Total: ' . count($damages) . ' Records', '', '', '', '', $totQty, '', '₹' . number_format($totLoss, 2), '', '', '', ''];
        $metadata = [
            'Report Title' => 'Internal Concern Damages & Scrap Write-offs',
            'Scope / Period' => $dateScope['label'],
            'Generated At' => date('d M Y, h:i A'),
            'Total Incident Records' => count($damages),
            'Total Damaged Units' => number_format($totQty, 2),
            'Total Valuation Loss' => '₹' . number_format($totLoss, 2)
        ];

        $filename = 'concern_damages_' . date('Ymd_His');

        if ($format === 'pdf') {
            \App\Services\ExportService::renderPdfView('Internal Damage & Write-offs Report', $metadata, $headers, $rows, $totals);
        } elseif ($format === 'csv') {
            \App\Services\ExportService::downloadCsv($filename, $headers, $rows, $totals);
        } else {
            \App\Services\ExportService::downloadExcel($filename, 'Internal Damage & Write-offs Report', $metadata, $headers, $rows, $totals);
        }
    }
}
