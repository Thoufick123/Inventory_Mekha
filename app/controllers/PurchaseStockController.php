<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\ModuleGuard;
use App\Services\AuditLog;
use App\Services\PurchaseDamageService;
use App\Services\PurchaseStockItemOverrideService;
use App\Services\RollRegistryService;

class PurchaseStockController extends Controller {

    public function index(): void {
        ModuleGuard::enforce('purchase_stocks', 'view');

        $isSuperAdmin = Auth::isSuperAdmin();
        $userCid = Auth::concernId();
        $selectedCid = $userCid;

        if ($isSuperAdmin) {
            if (isset($_GET['concern_id']) && $_GET['concern_id'] !== '') {
                $selectedCid = (int)$_GET['concern_id'];
            } else {
                $selectedCid = null; // Consolidated
            }
        }

        $activeTab = trim($_GET['tab'] ?? 'rolls');
        if (!in_array($activeTab, ['rolls', 'garment_other', 'finished_goods'], true)) {
            $activeTab = 'rolls';
        }

        $search = trim($_GET['q'] ?? '');
        $supplierFilter = (int)($_GET['supplier_id'] ?? 0);
        $statusFilter = trim($_GET['status'] ?? '');
        PurchaseDamageService::initTable();
        PurchaseStockItemOverrideService::initTable();

        // Base where condition builder
        $buildWhere = function(string $secType) use ($selectedCid, $search, $supplierFilter, $statusFilter) {
            $where = ["pbi.section_type = ?"];
            $params = [$secType];

            // Purchase Stocks strictly shows items added to stock via the "Add to Stock" action (inward_status = 'completed')
            if ($statusFilter === 'pending') {
                $where[] = "COALESCE(NULLIF(pbi.inward_status, ''), pb.inward_status, 'pending') = 'pending'";
            } elseif ($statusFilter === 'all') {
                // Shows all items regardless of inward status
            } else {
                // Default & 'in_stock': strictly only items where Add to Stock was clicked (inward_status = 'completed')
                $where[] = "COALESCE(NULLIF(pbi.inward_status, ''), pb.inward_status, 'pending') = 'completed'";
            }

            if ($selectedCid) {
                $where[] = "pbi.concern_id = ?";
                $params[] = $selectedCid;
            }

            if (!empty($search)) {
                $like = "%{$search}%";
                $where[] = "(pbi.item_code LIKE ? OR pbi.item_name LIKE ? OR pbi.pattern LIKE ? OR pbi.color LIKE ? OR pbi.specification LIKE ? OR pb.bill_no LIKE ? OR s.name LIKE ? OR s.company_name LIKE ?)";
                $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, $like]);
            }

            if ($supplierFilter > 0) {
                $where[] = "pb.supplier_id = ?";
                $params[] = $supplierFilter;
            }

            return ['sql' => implode(' AND ', $where), 'params' => $params];
        };

        // 1. Fetch Fabric Rolls (Individual batches)
        $rollsWhere = $buildWhere('rolls');
        $rolls = DB::fetchAll(
            "SELECT pbi.*, pb.bill_no, pb.bill_date, pb.supplier_invoice_no, pb.inward_status, pb.payment_status,
                    s.name AS supplier_name, s.company_name AS supplier_company,
                    c.name AS concern_name, c.code AS concern_code
             FROM purchase_bill_items pbi
             JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
             LEFT JOIN suppliers s ON pb.supplier_id = s.id
             LEFT JOIN concerns c ON pbi.concern_id = c.id
             WHERE {$rollsWhere['sql']}
             ORDER BY pbi.id DESC",
            $rollsWhere['params']
        );

        // Fetch Consolidated Color & Design Roll Stock Registry (Removes duplicate register entries & adds counts)
        RollRegistryService::syncSpecs($selectedCid);
        $consolidatedRolls = RollRegistryService::getConsolidatedStock($selectedCid, $search);
        $rollViewMode = trim($_GET['view'] ?? 'consolidated');

        // 2. Fetch Other Garment Supplies & Trims
        $otherWhere = $buildWhere('garment_other');
        $garmentOther = DB::fetchAll(
            "SELECT pbi.*, pb.bill_no, pb.bill_date, pb.supplier_invoice_no, pb.inward_status, pb.payment_status,
                    COALESCE(damage.damaged_qty, 0) AS damaged_qty,
                    COALESCE(damage.damaged_weight, 0) AS damaged_weight,
                    COALESCE(damage.estimated_loss, 0) AS estimated_loss,
                    s.name AS supplier_name, s.company_name AS supplier_company,
                    c.name AS concern_name, c.code AS concern_code
             FROM purchase_bill_items pbi
             JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
             LEFT JOIN suppliers s ON pb.supplier_id = s.id
             LEFT JOIN concerns c ON pbi.concern_id = c.id
             LEFT JOIN (
                 SELECT purchase_bill_item_id, SUM(qty) AS damaged_qty,
                        SUM(damaged_weight) AS damaged_weight, SUM(estimated_loss) AS estimated_loss
                 FROM purchase_damage_quarantine
                 WHERE item_type = 'garment_other'
                 GROUP BY purchase_bill_item_id
             ) damage ON damage.purchase_bill_item_id = pbi.id
             WHERE {$otherWhere['sql']}
             ORDER BY pbi.id DESC",
            $otherWhere['params']
        );
        $garmentOther = PurchaseStockItemOverrideService::applyToItems($garmentOther);
        foreach ($garmentOther as &$item) {
            $item['available_qty'] = max(0.0, (float)$item['qty'] - (float)$item['damaged_qty']);
            $item['available_weight'] = max(0.0, (float)($item['net_weight'] ?? 0) - (float)$item['damaged_weight']);
            $item['available_value'] = max(0.0, (float)$item['total_price'] - (float)$item['estimated_loss']);
        }
        unset($item);

        // 3. Fetch Finished Goods
        $finishedWhere = $buildWhere('finished_goods');
        $finishedGoods = DB::fetchAll(
            "SELECT pbi.*, pb.bill_no, pb.bill_date, pb.supplier_invoice_no, pb.inward_status, pb.payment_status,
                    s.name AS supplier_name, s.company_name AS supplier_company,
                    c.name AS concern_name, c.code AS concern_code,
                    v.sku_code AS variant_sku, v.name AS variant_name, v.size AS variant_size, v.mrp AS variant_mrp,
                    cat.name AS category_name,
                    subcat.name AS subcategory_name,
                    COALESCE(NULLIF(variant_catalog.main_category_code, ''), cat.code) AS main_category_code,
                    COALESCE(NULLIF(variant_catalog.sub_category_code, ''), subcat.code) AS sub_category_code,
                    COALESCE(NULLIF(variant_catalog.sub_sub_category_code, ''), subsub.code) AS sub_sub_category_code,
                    subsub.name AS sub_subcategory_name,
                    (SELECT st.qty FROM stocks st WHERE st.variant_id = pbi.variant_id AND st.concern_id = pbi.concern_id AND st.location = 'Main Store' LIMIT 1) AS live_variant_stock,
                    COALESCE(label_jobs.generated_qty, 0) AS label_generated_qty,
                    label_jobs.label_latest_job_id AS label_latest_job_id,
                    (SELECT lj.qr_payload FROM label_jobs lj
                     WHERE lj.concern_id = pbi.concern_id AND lj.ref_id = pbi.id AND lj.label_type = 'purchase_fg'
                       AND lj.id > COALESCE((SELECT stock_override.label_job_baseline_id
                                             FROM purchase_stock_item_overrides stock_override
                                             WHERE stock_override.concern_id = pbi.concern_id
                                               AND stock_override.purchase_bill_item_id = pbi.id), 0)
                     ORDER BY lj.id DESC LIMIT 1) AS fg_label_qr_payload,
                    (SELECT lj.item_title FROM label_jobs lj
                     WHERE lj.concern_id = pbi.concern_id AND lj.ref_id = pbi.id AND lj.label_type = 'purchase_fg'
                       AND lj.id > COALESCE((SELECT stock_override.label_job_baseline_id
                                             FROM purchase_stock_item_overrides stock_override
                                             WHERE stock_override.concern_id = pbi.concern_id
                                               AND stock_override.purchase_bill_item_id = pbi.id), 0)
                     ORDER BY lj.id DESC LIMIT 1) AS fg_label_title,
                    (SELECT lj.item_code FROM label_jobs lj
                     WHERE lj.concern_id = pbi.concern_id AND lj.ref_id = pbi.id AND lj.label_type = 'purchase_fg'
                       AND lj.id > COALESCE((SELECT stock_override.label_job_baseline_id
                                             FROM purchase_stock_item_overrides stock_override
                                             WHERE stock_override.concern_id = pbi.concern_id
                                               AND stock_override.purchase_bill_item_id = pbi.id), 0)
                     ORDER BY lj.id DESC LIMIT 1) AS fg_label_code
             FROM purchase_bill_items pbi
             JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
             LEFT JOIN suppliers s ON pb.supplier_id = s.id
             LEFT JOIN concerns c ON pbi.concern_id = c.id
             LEFT JOIN variants v ON pbi.variant_id = v.id
             LEFT JOIN categories cat ON v.category_id = cat.id
             LEFT JOIN subcategories subcat ON v.subcategory_id = subcat.id
             LEFT JOIN catalogs variant_catalog ON variant_catalog.id = v.catalog_id
             LEFT JOIN sub_subcategories subsub ON subsub.id = variant_catalog.sub_sub_category_id
             LEFT JOIN (
                 SELECT lj.concern_id, lj.ref_id, SUM(lj.qty) AS generated_qty,
                        MAX(lj.id) AS label_latest_job_id
                 FROM label_jobs lj
                 LEFT JOIN purchase_stock_item_overrides stock_override
                   ON stock_override.concern_id = lj.concern_id
                  AND stock_override.purchase_bill_item_id = lj.ref_id
                  AND stock_override.section_type = 'finished_goods'
                 WHERE lj.label_type = 'purchase_fg'
                   AND lj.id > COALESCE(stock_override.label_job_baseline_id, 0)
                 GROUP BY lj.concern_id, lj.ref_id
             ) label_jobs ON label_jobs.concern_id = pbi.concern_id AND label_jobs.ref_id = pbi.id
             WHERE {$finishedWhere['sql']}
             ORDER BY pbi.id DESC",
            $finishedWhere['params']
        );
        $finishedGoods = PurchaseStockItemOverrideService::applyToItems($finishedGoods);
        foreach ($finishedGoods as &$item) {
            $item['label_generation_complete'] =
                (float)$item['qty'] > 0 &&
                (float)$item['label_generated_qty'] >= (float)$item['qty'] &&
                !empty($item['fg_label_qr_payload']) &&
                (int)($item['label_latest_job_id'] ?? 0) > (int)($item['label_job_baseline_id'] ?? 0);
        }
        unset($item);

        // Calculate KPI Metrics across current concern scope (strictly inwarded stocks: inward_status = 'completed')
        $metricWhere = ["COALESCE(NULLIF(pbi.inward_status, ''), pb.inward_status, 'pending') = 'completed'"];
        $metricParams = [];
        if ($selectedCid) {
            $metricWhere[] = "pbi.concern_id = ?";
            $metricParams[] = $selectedCid;
        }

        $allMetrics = DB::fetchAll(
            "SELECT pbi.section_type,
                    SUM(COALESCE(stock_override.qty, pbi.qty)) AS total_qty,
                    SUM(COALESCE(pbi.net_weight, 0)) AS total_weight,
                    SUM(COALESCE(stock_override.total_value, pbi.total_price)) AS total_val,
                    SUM(COALESCE(stock_override.mrp, pbi.mrp, 0) * COALESCE(stock_override.qty, pbi.qty)) AS total_mrp_val,
                    COUNT(pbi.id) AS item_count
             FROM purchase_bill_items pbi
             JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
             LEFT JOIN purchase_stock_item_overrides stock_override
               ON stock_override.concern_id = pbi.concern_id
              AND stock_override.purchase_bill_item_id = pbi.id
              AND stock_override.section_type = pbi.section_type
             WHERE " . implode(' AND ', $metricWhere) . "
             GROUP BY pbi.section_type",
            $metricParams
        );

        $metrics = [
            'rolls_qty'        => 0.0,
            'rolls_weight'     => 0.0,
            'rolls_value'      => 0.0,
            'rolls_count'      => 0,
            'other_qty'        => 0.0,
            'other_count'      => 0,
            'other_value'      => 0.0,
            'finished_qty'     => 0.0,
            'finished_count'   => 0,
            'finished_value'   => 0.0,
            'finished_mrp_val' => 0.0,
            'grand_value'      => 0.0,
        ];

        $consolidatedRollMetrics = RollRegistryService::getConsolidatedStock($selectedCid);
        foreach ($allMetrics as $m) {
            $t = $m['section_type'];
            if ($t === 'rolls') {
                $metrics['rolls_qty']    = (float)$m['total_qty'];
                $metrics['rolls_weight'] = (float)$m['total_weight'];
                $metrics['rolls_value']  = (float)$m['total_val'];
                $metrics['rolls_count']  = (int)$m['item_count'];
            } elseif ($t === 'garment_other') {
                $metrics['other_qty']   = (float)$m['total_qty'];
                $metrics['other_value'] = (float)$m['total_val'];
                $metrics['other_count'] = (int)$m['item_count'];
            } elseif ($t === 'finished_goods') {
                $metrics['finished_qty']     = (float)$m['total_qty'];
                $metrics['finished_value']   = (float)$m['total_val'];
                $metrics['finished_mrp_val'] = (float)$m['total_mrp_val'];
                $metrics['finished_count']   = (int)$m['item_count'];
            }
            $metrics['grand_value'] += (float)$m['total_val'];
        }
        $damageMetrics = DB::fetchAll(
            "SELECT item_type, SUM(qty) AS damaged_qty, SUM(estimated_loss) AS estimated_loss
             FROM purchase_damage_quarantine
             WHERE (? = 0 OR concern_id = ?)
             GROUP BY item_type",
            [$selectedCid ?: 0, $selectedCid ?: 0]
        );
        foreach ($damageMetrics as $damageMetric) {
            $damagedQty = (float)$damageMetric['damaged_qty'];
            $estimatedLoss = (float)$damageMetric['estimated_loss'];
            if ($damageMetric['item_type'] === 'garment_other') {
                $metrics['other_qty'] = max(0.0, $metrics['other_qty'] - $damagedQty);
                $metrics['other_value'] = max(0.0, $metrics['other_value'] - $estimatedLoss);
                $metrics['grand_value'] -= $estimatedLoss;
            } elseif ($damageMetric['item_type'] === 'finished_goods') {
                $metrics['finished_qty'] = max(0.0, $metrics['finished_qty'] - $damagedQty);
                $metrics['finished_value'] = max(0.0, $metrics['finished_value'] - $estimatedLoss);
                $metrics['grand_value'] -= $estimatedLoss;
            }
        }
        $damagedFinishedMrp = (float)DB::fetchValue(
            "SELECT COALESCE(SUM(q.qty * COALESCE(v.mrp, 0)), 0)
             FROM purchase_damage_quarantine q
             LEFT JOIN variants v ON v.id = q.variant_id
             WHERE q.item_type = 'finished_goods'
               AND (? = 0 OR q.concern_id = ?)",
            [$selectedCid ?: 0, $selectedCid ?: 0]
        );
        $metrics['finished_mrp_val'] = max(0.0, $metrics['finished_mrp_val'] - $damagedFinishedMrp);
        $sourceRollValue = $metrics['rolls_value'];
        $metrics['rolls_qty'] = array_sum(array_map(
            static fn(array $roll): float => (float)$roll['qty'],
            $consolidatedRollMetrics
        ));
        $metrics['rolls_weight'] = array_sum(array_map(
            static fn(array $roll): float => (float)$roll['net_weight'],
            $consolidatedRollMetrics
        ));
        $metrics['rolls_value'] = array_sum(array_map(
            static fn(array $roll): float => (float)$roll['total_value'],
            $consolidatedRollMetrics
        ));
        $metrics['rolls_count'] = count($consolidatedRollMetrics);
        $metrics['grand_value'] += $metrics['rolls_value'] - $sourceRollValue;

        // Supplier filter list
        $supSql = "SELECT id, name, company_name FROM suppliers";
        $supParams = [];
        if ($selectedCid) {
            $supSql .= " WHERE concern_id = ?";
            $supParams[] = $selectedCid;
        }
        $supSql .= " ORDER BY name ASC";
        $suppliers = DB::fetchAll($supSql, $supParams);

        $allConcerns = $isSuperAdmin ? DB::fetchAll("SELECT id, name, code FROM concerns ORDER BY name ASC") : [];
        $canEditRollStock = $isSuperAdmin || Auth::can('purchase_stocks', 'edit');
        $canEditPurchaseStock = $canEditRollStock;

        $this->render('concern/purchase_stocks', [
            'pageTitle'         => 'Purchase Stocks & Inventory',
            'currentModule'     => 'purchase_stocks',
            'activeTab'         => $activeTab,
            'rolls'             => $rolls,
            'consolidatedRolls' => $consolidatedRolls,
            'rollViewMode'      => $rollViewMode,
            'garmentOther'      => $garmentOther,
            'finishedGoods'     => $finishedGoods,
            'metrics'           => $metrics,
            'suppliers'         => $suppliers,
            'allConcerns'       => $allConcerns,
            'selectedCid'       => $selectedCid,
            'canEditRollStock'  => $canEditRollStock,
            'canEditPurchaseStock' => $canEditPurchaseStock,
            'search'            => $search,
            'supplierFilter'    => $supplierFilter,
            'statusFilter'      => $statusFilter,
        ]);
    }

    public function updateConsolidatedRoll(): void {
        ModuleGuard::enforce('purchase_stocks', 'edit');
        $this->validateCsrf();

        if (Auth::isSuperAdmin()) {
            $concernId = (int)($_POST['concern_id'] ?? 0);
        } else {
            $concernId = Auth::concernId();
        }
        if (!$concernId) {
            $this->flash('error', 'Select a concern before editing consolidated fabric stock.');
            $this->redirect('/m/purchase_stocks?tab=rolls&view=consolidated');
            return;
        }

        $returnUrl = '/m/purchase_stocks?tab=rolls&view=consolidated';
        if (Auth::isSuperAdmin()) {
            $returnUrl .= '&concern_id=' . $concernId;
        }

        $sourceColor = strtoupper(trim((string)($_POST['source_color'] ?? '')));
        $sourceDesignName = strtoupper(trim((string)($_POST['source_design_name'] ?? '')));
        $color = strtoupper(trim((string)($_POST['color'] ?? '')));
        $designName = strtoupper(trim((string)($_POST['design_name'] ?? '')));
        $hsnCode = trim((string)($_POST['hsn_code'] ?? ''));
        $unit = trim((string)($_POST['unit'] ?? 'Rolls'));

        if (
            $sourceColor === '' || strlen($sourceColor) > 100 ||
            $sourceDesignName === '' || strlen($sourceDesignName) > 150 ||
            $color === '' || strlen($color) > 100 ||
            $designName === '' || strlen($designName) > 150 ||
            ($hsnCode !== '' && !preg_match('/^\d{4}(?:\d{2}){0,2}$/', $hsnCode)) ||
            $unit === '' || strlen($unit) > 50
        ) {
            $this->flash('error', 'Enter valid fabric name, design, color, HSN, and unit values.');
            $this->redirect($returnUrl);
            return;
        }

        $numericFields = [
            'qty' => 'Quantity',
            'net_weight' => 'Weight',
            'last_unit_price' => 'Unit rate',
            'total_value' => 'Total valuation',
        ];
        $numbers = [];
        foreach ($numericFields as $field => $label) {
            $rawValue = $_POST[$field] ?? null;
            if (!is_scalar($rawValue) || !is_numeric($rawValue)) {
                $this->flash('error', "{$label} must be a valid number.");
                $this->redirect($returnUrl);
                return;
            }
            $number = (float)$rawValue;
            if (!is_finite($number) || $number < 0 || $number > 9999999999.99) {
                $this->flash('error', "{$label} must be between 0 and 9,999,999,999.99.");
                $this->redirect($returnUrl);
                return;
            }
            $numbers[$field] = $number;
        }

        RollRegistryService::syncSpecs($concernId);
        $result = RollRegistryService::saveConsolidatedOverride(
            $concernId,
            $sourceColor,
            $sourceDesignName,
            [
                'color' => $color,
                'design_name' => $designName,
                'hsn_code' => $hsnCode,
                'qty' => $numbers['qty'],
                'net_weight' => $numbers['net_weight'],
                'unit' => $unit,
                'last_unit_price' => $numbers['last_unit_price'],
                'total_value' => $numbers['total_value'],
            ]
        );

        if (!$result) {
            $this->flash('error', 'This stock row could not be updated. It may have changed, or another row already uses that design and color.');
            $this->redirect($returnUrl);
            return;
        }

        AuditLog::log(
            'UPDATE',
            'fabric_roll_spec_overrides',
            $result['id'],
            $result['before'],
            $result['after']
        );
        $this->flash('success', 'Consolidated fabric stock updated. Purchase bill history was not changed.');
        $this->redirect($returnUrl);
    }

    public function updateStockItem(): void {
        ModuleGuard::enforce('purchase_stocks', 'edit');
        $this->validateCsrf();

        $itemId = (int)($_POST['item_id'] ?? 0);
        $sectionType = trim((string)($_POST['section_type'] ?? ''));
        $concernId = Auth::isSuperAdmin()
            ? (int)($_POST['concern_id'] ?? 0)
            : Auth::concernId();
        $returnUrl = '/m/purchase_stocks?tab=' . rawurlencode($sectionType);
        if (Auth::isSuperAdmin() && $concernId) {
            $returnUrl .= '&concern_id=' . $concernId;
        }

        if (!$itemId || !$concernId || !in_array($sectionType, ['garment_other', 'finished_goods'], true)) {
            $this->flash('error', 'Select a valid purchase stock item before editing.');
            $this->redirect('/m/purchase_stocks?tab=garment_other');
            return;
        }

        $sourceItem = DB::fetch(
            "SELECT id, concern_id, section_type, qty
             FROM purchase_bill_items
             WHERE id = ? AND concern_id = ? AND section_type = ?",
            [$itemId, $concernId, $sectionType]
        );
        if (!$sourceItem) {
            $this->flash('error', 'This purchase stock item was not found or is outside the selected concern.');
            $this->redirect($returnUrl);
            return;
        }

        $textFields = [
            'item_name' => ['Item name', 255],
            'item_code' => ['Item code', 100],
            'specification' => ['Specification', 255],
            'color' => ['Color', 100],
            'unit' => ['Unit', 50],
        ];
        $values = [];
        foreach ($textFields as $field => [$label, $maxLength]) {
            $rawValue = $_POST[$field] ?? '';
            if (!is_scalar($rawValue)) {
                $this->flash('error', "{$label} is invalid.");
                $this->redirect($returnUrl);
                return;
            }
            $value = trim((string)$rawValue);
            if (($field === 'item_name' || $field === 'unit') && $value === '') {
                $this->flash('error', "{$label} is required.");
                $this->redirect($returnUrl);
                return;
            }
            if (strlen($value) > $maxLength) {
                $this->flash('error', "{$label} is too long.");
                $this->redirect($returnUrl);
                return;
            }
            $values[$field] = $value;
        }

        foreach ([
            'qty' => 'Quantity',
            'unit_price' => 'Unit price',
            'mrp' => 'MRP',
            'total_value' => 'Stock value',
        ] as $field => $label) {
            $rawValue = $_POST[$field] ?? null;
            if (!is_scalar($rawValue) || !is_numeric($rawValue)) {
                $this->flash('error', "{$label} must be a valid number.");
                $this->redirect($returnUrl);
                return;
            }
            $number = (float)$rawValue;
            if (!is_finite($number) || $number < 0 || $number > 9999999999.99) {
                $this->flash('error', "{$label} must be between 0 and 9,999,999,999.99.");
                $this->redirect($returnUrl);
                return;
            }
            $values[$field] = $number;
        }

        if ($sectionType === 'garment_other') {
            PurchaseDamageService::initTable();
            $damagedQty = (float)DB::fetchValue(
                "SELECT COALESCE(SUM(qty), 0) FROM purchase_damage_quarantine
                 WHERE concern_id = ? AND purchase_bill_item_id = ? AND item_type = 'garment_other'",
                [$concernId, $itemId]
            );
            if ($values['qty'] < $damagedQty) {
                $this->flash('error', 'Quantity cannot be lower than the amount already in damage quarantine.');
                $this->redirect($returnUrl);
                return;
            }
        }

        $result = PurchaseStockItemOverrideService::saveItemOverride(
            $concernId,
            $itemId,
            $sectionType,
            $values,
            Auth::id()
        );
        if (!$result) {
            $this->flash('error', 'This stock item could not be updated. Please refresh and try again.');
            $this->redirect($returnUrl);
            return;
        }

        AuditLog::log(
            'UPDATE',
            'purchase_stock_item_overrides',
            $result['id'],
            $result['before'],
            $result['after']
        );
        $this->flash('success', 'Purchase stock item updated. Purchase bill history was not changed.');
        $this->redirect($returnUrl);
    }

    public function export(): void {
        ModuleGuard::enforce('purchase_stocks', 'view');
        PurchaseDamageService::initTable();

        $isSuperAdmin = Auth::isSuperAdmin();
        $userCid = Auth::concernId();
        $selectedCid = $userCid;
        if ($isSuperAdmin && isset($_GET['concern_id']) && $_GET['concern_id'] !== '') {
            $selectedCid = (int)$_GET['concern_id'];
        }

        $tab = trim($_GET['tab'] ?? 'rolls');
        if (!in_array($tab, ['rolls', 'garment_other', 'finished_goods'], true)) {
            $tab = 'rolls';
        }

        $where = [
            "pbi.section_type = ?",
            "COALESCE(NULLIF(pbi.inward_status, ''), pb.inward_status, 'pending') = 'completed'"
        ];
        $params = [$tab];
        if ($selectedCid) {
            $where[] = "pbi.concern_id = ?";
            $params[] = $selectedCid;
        }

        $items = DB::fetchAll(
            "SELECT pbi.*, pb.bill_no, pb.bill_date, pb.supplier_invoice_no, pb.inward_status,
                    s.name AS supplier_name, s.company_name AS supplier_company,
                    c.name AS concern_name, c.code AS concern_code,
                    COALESCE(damage.damaged_qty, 0) AS damaged_qty,
                    GREATEST(0, pbi.qty - COALESCE(damage.damaged_qty, 0)) AS available_qty,
                    GREATEST(0, pbi.total_price - COALESCE(damage.estimated_loss, 0)) AS available_value
             FROM purchase_bill_items pbi
             JOIN purchase_bills pb ON pbi.purchase_bill_id = pb.id
             LEFT JOIN suppliers s ON pb.supplier_id = s.id
             LEFT JOIN concerns c ON pbi.concern_id = c.id
             LEFT JOIN (
                 SELECT purchase_bill_item_id, SUM(qty) AS damaged_qty,
                        SUM(estimated_loss) AS estimated_loss
                 FROM purchase_damage_quarantine
                 WHERE item_type = 'garment_other'
                 GROUP BY purchase_bill_item_id
             ) damage ON damage.purchase_bill_item_id = pbi.id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY pbi.id DESC",
            $params
        );

        $filename = "purchase_stocks_{$tab}_" . date('Ymd_His') . ".csv";

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // Add UTF-8 BOM for Excel compatibility
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        if ($tab === 'rolls') {
            $isDetailed = (trim($_GET['mode'] ?? '') === 'detailed');
            if ($isDetailed) {
                fputcsv($out, ['Roll Code', 'Fabric Name', 'Color / Shade', 'Pattern', 'HSN', 'Gross Wt (kg)', 'Net Wt (kg)', 'Qty (Rolls)', 'Unit Price (INR)', 'Total Value (INR)', 'Bill No', 'Bill Date', 'Supplier', 'Inward Status']);
                foreach ($items as $r) {
                    fputcsv($out, [
                        $r['item_code'] ?: '-',
                        $r['item_name'] ?: '-',
                        $r['color'] ?: '-',
                        $r['pattern'] ?: '-',
                        $r['hsn_code'] ?: '-',
                        $r['gross_weight'] ?? 0,
                        $r['net_weight'] ?? 0,
                        $r['qty'],
                        $r['unit_price'],
                        $r['total_price'],
                        $r['bill_no'],
                        $r['bill_date'],
                        $r['supplier_name'] ?: '-',
                        strtoupper($r['inward_status'] ?? 'PENDING')
                    ]);
                }
            } else {
                // Consolidated Color & Design Stock Report (removes duplicates, accumulates counts)
                RollRegistryService::syncSpecs($selectedCid);
                $consRolls = RollRegistryService::getConsolidatedStock($selectedCid);
                fputcsv($out, ['Design Name / Pattern', 'Color / Shade', 'HSN Code', 'Total Available Qty (Rolls)', 'Total Fabric Weight (kg)', 'Unit Rate (INR)', 'Total Stock Valuation (INR)', 'Inward Batches Count', 'Latest Bill No', 'Latest Supplier', 'Stock Status']);
                foreach ($consRolls as $cr) {
                    fputcsv($out, [
                        $cr['design_name'] ?: '-',
                        $cr['color'] ?: '-',
                        $cr['hsn_code'] ?: '-',
                        $cr['qty'],
                        $cr['net_weight'],
                        $cr['last_unit_price'],
                        $cr['total_value'],
                        $cr['bills_count'],
                        $cr['last_bill_no'] ?: '-',
                        $cr['last_supplier_name'] ?: '-',
                        (float)$cr['qty'] > 0 ? 'IN STOCK' : 'OUT OF STOCK'
                    ]);
                }
            }
        } elseif ($tab === 'garment_other') {
            fputcsv($out, ['Item Code', 'Item Name', 'Specification', 'Unit', 'Qty Inwarded', 'Qty Available', 'Unit Price (INR)', 'Available Value (INR)', 'Bill No', 'Bill Date', 'Supplier', 'Inward Status']);
            foreach ($items as $g) {
                fputcsv($out, [
                    $g['item_code'] ?: '-',
                    $g['item_name'] ?: '-',
                    $g['specification'] ?: '-',
                    $g['unit'] ?: 'Pcs',
                    $g['qty'],
                    $g['available_qty'],
                    $g['unit_price'],
                    $g['available_value'],
                    $g['bill_no'],
                    $g['bill_date'],
                    $g['supplier_name'] ?: '-',
                    strtoupper($g['inward_status'] ?? 'PENDING')
                ]);
            }
        } else {
            fputcsv($out, ['SKU / Code', 'Garment Product', 'Category', 'Specification / Size', 'Color', 'MRP (INR)', 'Purchase Price (INR)', 'Available Qty', 'Total Value (INR)', 'Bill No', 'Bill Date', 'Supplier', 'Inward Status']);
            foreach ($items as $f) {
                fputcsv($out, [
                    $f['item_code'] ?: '-',
                    $f['item_name'] ?: '-',
                    $f['category_name'] ?: '-',
                    $f['specification'] ?: '-',
                    $f['color'] ?: '-',
                    $f['mrp'] ?? 0,
                    $f['unit_price'],
                    $f['qty'],
                    $f['total_price'],
                    $f['bill_no'],
                    $f['bill_date'],
                    $f['supplier_name'] ?: '-',
                    strtoupper($f['inward_status'] ?? 'PENDING')
                ]);
            }
        }

        fclose($out);
        exit;
    }
}
