<?php 
use App\Services\QrService; 
use App\Core\Auth;
use App\Core\DB;

$concern = Auth::concern();
if (!$concern && !empty($selectedCid)) {
    $concern = DB::fetch("SELECT * FROM concerns WHERE id = ?", [$selectedCid]);
}
$concernName = $concern['name'] ?? 'GRM Inventory';
?>
<div class="container-fluid px-0">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Barcode & QR Label Generation</h4>
            <p class="text-secondary small mb-0">
                Generate production & inventory barcode labels across 4 core business stages: Purchase Raw Materials, Purchase Finished Goods, Production After-Stitching, and Customer Returns.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= \App\Core\Controller::url('/scan') ?>" class="btn btn-outline-primary shadow-sm rounded-2 d-flex align-items-center gap-2">
                <i class="bi bi-qr-code-scan fs-6"></i> Open Live QR Scanner
            </a>
            <a href="<?= \App\Core\Controller::url('/m/purchase_stocks') ?>" class="btn btn-outline-success shadow-sm rounded-2 d-flex align-items-center gap-2">
                <i class="bi bi-boxes fs-6"></i> Purchase Stocks
            </a>
        </div>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Label Print Jobs (Excel & PDF)';
    $exportSubtitle = 'Download barcode printing batches, SKU assignments, label runs & production logs';
    $exportUrl = \App\Core\Controller::url('/m/label_generation/export');
    $prefix = 'lbl_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <?php 
    $curTab = $activeTab ?? 'purchase_raw'; 
    $curSectionTab = ($curTab === 'purchase_fg') ? 'finished_goods' : (($activeSubTab === 'garment_other') ? 'garment_other' : 'rolls');
    $activeBillSectionPending = 0;
    if (!empty($activeBill['id'])) {
        $activeBillSectionPending = (int)\App\Core\DB::fetchValue(
            "SELECT COUNT(*) FROM purchase_bill_items WHERE purchase_bill_id = ? AND section_type = ? AND inward_status = 'pending'",
            [(int)$activeBill['id'], $curSectionTab]
        );
    }
    $sectionDisplayTitle = ($curSectionTab === 'rolls') ? 'Fabric Rolls' : (($curSectionTab === 'garment_other') ? 'Trims & Supplies' : 'Finished Goods');
    ?>

    <!-- Workflow Banner if redirected directly from completing a purchase bill -->
    <?php if (!empty($activeBill)): ?>
        <div class="alert alert-success border-success-subtle shadow-sm rounded-3 d-flex flex-wrap align-items-center justify-content-between p-3 mb-4 gap-3 bg-white border-start border-4 border-success">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle p-2 d-flex align-items-center justify-content-center text-white" style="background-color: #10b981; width: 44px; height: 44px;">
                    <i class="bi bi-box-arrow-in-down fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">
                        Purchase Bill #<?= htmlspecialchars($activeBill['bill_no']) ?> Recorded!
                        <?php if (!empty($activeBill['supplier_company']) || !empty($activeBill['supplier_name'])): ?>
                            <span class="fw-normal text-muted small">&bull; Supplier: <?= htmlspecialchars($activeBill['supplier_company'] ?: $activeBill['supplier_name']) ?></span>
                        <?php endif; ?>
                    </h6>
                    <p class="text-secondary small mb-0">
                        Items are loaded for label generation. Review, print barcode/QR stickers, then click <strong>Add to Stock</strong> to inward into <?= $curSectionTab === 'finished_goods' ? 'Live Stocks' : 'Purchase Stocks' ?>.
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if ($activeBillSectionPending > 0): ?>
                    <form action="<?= \App\Core\Controller::url('/m/label/add_to_stock') ?>" method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="bill_id" value="<?= (int)$activeBill['id'] ?>">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($curSectionTab) ?>">
                        <button type="submit" class="btn btn-success fw-bold px-3 py-2 shadow-sm d-flex align-items-center gap-1.5 rounded-2">
                            <i class="bi bi-box-arrow-in-down fs-6"></i> Add <?= $sectionDisplayTitle ?> to Stock &rarr; <?= $curSectionTab === 'finished_goods' ? 'Live Stocks' : 'Purchase Stocks' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <span class="badge bg-success-subtle text-success border border-success p-2">
                        <i class="bi bi-check-circle-fill me-1"></i> <?= $sectionDisplayTitle ?> In Stock
                    </span>
                    <a href="<?= \App\Core\Controller::url($curSectionTab === 'finished_goods' ? '/m/stocks' : '/m/purchase_stocks?tab=' . $curSectionTab) ?>" class="btn btn-outline-success fw-bold px-3 py-2 rounded-2">
                        View in <?= $curSectionTab === 'finished_goods' ? 'Live Stocks' : 'Purchase Stocks' ?> &rarr;
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3-Section Navigation Tabs -->
    <ul class="nav nav-pills nav-fill bg-white p-2 rounded-3 shadow-sm mb-4 border" id="labelSectionTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($curTab === 'purchase_raw') ? 'active' : '' ?> fw-semibold d-flex align-items-center justify-content-center gap-2 py-2" id="tab-purchase-raw-btn" data-bs-toggle="pill" data-bs-target="#tab-purchase-raw" type="button" role="tab">
                <i class="bi bi-truck fs-5 text-primary"></i>
                <span>1. Purchase Label</span>
                <span class="badge bg-primary-subtle text-primary border border-primary ms-1"><?= count($purchaseRawItems) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($curTab === 'purchase_fg') ? 'active' : '' ?> fw-semibold d-flex align-items-center justify-content-center gap-2 py-2" id="tab-purchase-fg-btn" data-bs-toggle="pill" data-bs-target="#tab-purchase-fg" type="button" role="tab">
                <i class="bi bi-box-seam fs-5 text-success"></i>
                <span>2. Purchase Finished Goods</span>
                <span class="badge bg-success-subtle text-success border border-success ms-1"><?= count($purchaseFgItems) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($curTab === 'production') ? 'active' : '' ?> fw-semibold d-flex align-items-center justify-content-center gap-2 py-2" id="tab-production-btn" data-bs-toggle="pill" data-bs-target="#tab-production" type="button" role="tab">
                <i class="bi bi-scissors fs-5 text-warning"></i>
                <span>3. Production Label</span>
                <span class="badge bg-warning-subtle text-warning border border-warning ms-1"><?= count($productionItems) ?></span>
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content mb-4" id="labelSectionTabContent">
        
        <!-- ======================================================== -->
        <!-- TAB 1: PURCHASE LABEL (Rolls & Other Garments Products) -->
        <!-- ======================================================== -->
        <div class="tab-pane fade <?= ($curTab === 'purchase_raw') ? 'show active' : '' ?>" id="tab-purchase-raw" role="tabpanel">
            
            <!-- Sub-Navigation for Purchase Label: Fabric Rolls vs Other Garments Products -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 p-2 bg-white rounded-3 shadow-sm border gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-muted small fw-bold text-uppercase px-2" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                        <i class="bi bi-funnel me-1"></i> Purchase Options:
                    </span>
                    <ul class="nav nav-pills gap-1" id="purchaseSubTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($activeSubTab === 'rolls') ? 'active' : '' ?> btn-sm py-1.5 px-3 fw-bold d-flex align-items-center gap-2" id="subtab-rolls-btn" data-bs-toggle="pill" data-bs-target="#subtab-rolls-pane" type="button" role="tab">
                                <i class="bi bi-stack fs-6 text-primary"></i>
                                <span>Fabric Rolls</span>
                                <span class="badge bg-primary-subtle text-primary border border-primary"><?= count($purchaseRollItems) ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($activeSubTab === 'garment_other') ? 'active' : '' ?> btn-sm py-1.5 px-3 fw-bold d-flex align-items-center gap-2" id="subtab-other-btn" data-bs-toggle="pill" data-bs-target="#subtab-other-pane" type="button" role="tab">
                                <i class="bi bi-scissors fs-6 text-info"></i>
                                <span>Other Garments Products / Trims</span>
                                <span class="badge bg-info-subtle text-info border border-info"><?= count($purchaseOtherItems) ?></span>
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="text-muted small pe-2 d-none d-lg-block" style="font-size: 0.78rem;">
                    <i class="bi bi-info-circle me-1 text-primary"></i> Separate configuration and product lists for Fabric Rolls and Garment Trims.
                </div>
            </div>

            <!-- Purchase Label Sub-Tab Contents -->
            <div class="tab-content" id="purchaseSubTabContent">

                <!-- ---------------------------------------------------- -->
                <!-- SUB-PANE 1A: FABRIC ROLLS                            -->
                <!-- ---------------------------------------------------- -->
                <div class="tab-pane fade <?= ($activeSubTab === 'rolls') ? 'show active' : '' ?>" id="subtab-rolls-pane" role="tabpanel">
                    <div class="row g-4">
                        <!-- Fabric Roll Generator Config Card -->
                        <div class="col-lg-5">
                            <div class="card border-0 shadow-sm rounded-3 h-100">
                                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                        <i class="bi bi-stack text-primary fs-5"></i>
                                        <span>Fabric Roll Label Configuration</span>
                                    </h6>
                                    <span class="badge bg-primary-subtle text-primary border border-primary">Rolls Only</span>
                                </div>
                                <form action="<?= \App\Core\Controller::url('/m/label_generation/generate') ?>" method="POST" target="_blank" class="card-body p-4 d-flex flex-column">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="label_type" value="purchase_raw">

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Select Fabric Roll <span class="text-danger">*</span></label>
                                        <select name="item_id" id="roll_item_select" class="form-select" required onchange="updateRollPreview(); matchRollQty()">
                                            <option value="">-- Choose Fabric Roll --</option>
                                            <?php foreach ($purchaseRollItems as $raw): 
                                                $codeVal = trim($raw['item_code'] ?? '');
                                                $nameVal = trim($raw['item_name'] ?? '');
                                                $patternVal = trim($raw['pattern'] ?? '');
                                                $codeIsGenericRoll = preg_match('/^ROLL\s*#?\s*\d*$/i', $codeVal);
                                                $nameIsPatternFabric = !empty($patternVal) && (strcasecmp($nameVal, $patternVal . ' Fabric') === 0 || strcasecmp($nameVal, $patternVal) === 0);

                                                if (!empty($codeVal) && !$codeIsGenericRoll) {
                                                    $fabricName = $codeVal;
                                                } elseif (!empty($nameVal) && !$nameIsPatternFabric) {
                                                    $fabricName = $nameVal;
                                                } else {
                                                    $fabricName = !empty($codeVal) ? $codeVal : (!empty($nameVal) ? $nameVal : 'Fabric Material');
                                                }

                                                $code = $raw['item_code'] ?: ('ROLL-' . $raw['id']);
                                                $tagCode = QrService::generateTagCode(
                                                    $fabricName,
                                                    $raw['color'] ?? '',
                                                    $raw['pattern'] ?: ($raw['specification'] ?? ''),
                                                    (string)$raw['id']
                                                );
                                                $isRollInStock = (($raw['inward_status'] ?? '') === 'completed');
                                            ?>
                                                <option value="<?= $raw['id'] ?>"
                                                        <?= (count($purchaseRollItems) === 1 || ($activeRollItemId ?? 0) == $raw['id']) ? 'selected' : '' ?>
                                                        data-name="<?= htmlspecialchars($raw['item_name']) ?>"
                                                        data-fabric="<?= htmlspecialchars($fabricName) ?>"
                                                        data-code="<?= htmlspecialchars($code) ?>"
                                                        data-tag="<?= htmlspecialchars($tagCode) ?>"
                                                        data-type="Fabric Roll"
                                                        data-isroll="1"
                                                        data-inward="<?= $isRollInStock ? 'completed' : 'pending' ?>"
                                                        data-vendor="<?= htmlspecialchars($raw['supplier_company'] ?: ($raw['supplier_name'] ?: 'Vendor Mill')) ?>"
                                                        data-color="<?= htmlspecialchars($raw['color'] ?: 'STANDARD') ?>"
                                                        data-pattern="<?= htmlspecialchars($raw['pattern'] ?: '') ?>"
                                                        data-spec="<?= htmlspecialchars($raw['specification'] ?? '') ?>"
                                                        data-qty="<?= htmlspecialchars($raw['qty']) ?>"
                                                        data-unit="<?= htmlspecialchars($raw['unit'] ?: 'Rolls') ?>"
                                                        data-rq="<?= htmlspecialchars(!empty($raw['rq_qty']) ? $raw['rq_qty'] : $raw['qty']) ?>"
                                                        data-net="<?= htmlspecialchars($raw['net_weight'] ?? '') ?>"
                                                        data-price="<?= (float)($raw['unit_price'] ?: ($raw['mrp'] ?: 0)) ?>"
                                                        data-bill="<?= htmlspecialchars($raw['bill_no']) ?>"
                                                        data-qr="<?= htmlspecialchars($tagCode) ?>">
                                                    [ROLL] <?= htmlspecialchars($fabricName) ?> (<?= htmlspecialchars($tagCode) ?>) &bull; <?= number_format((float)$raw['qty'], 2) ?> <?= htmlspecialchars($raw['unit'] ?: 'Rolls') ?> &bull; Bill #<?= htmlspecialchars($raw['bill_no']) ?><?= $isRollInStock ? ' &bull; [IN STOCK]' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Directly sourced from Purchase Billing Fabric Rolls.</small>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label small fw-semibold mb-0">Number of Label Copies (N Prints) <span class="text-danger">*</span></label>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-primary py-0 px-2" onclick="matchRollQty()">Match Qty</button>
                                            </div>
                                        </div>
                                        <div class="input-group">
                                            <button type="button" class="btn btn-outline-secondary" onclick="adjustCopies('roll_qty', -1)" aria-label="Decrease label quantity"><i class="bi bi-dash-lg"></i></button>
                                            <input type="number" name="qty" id="roll_qty" class="form-control text-center" min="1" max="500" value="1" required>
                                            <button type="button" class="btn btn-outline-secondary" onclick="adjustCopies('roll_qty', 1)" aria-label="Increase label quantity"><i class="bi bi-plus-lg"></i></button>
                                            <span class="input-group-text bg-light">Stickers</span>
                                        </div>
                                    </div>

                                    <!-- Live Roll Sticker Preview -->
                                    <div class="p-3 border rounded-3 bg-light mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-bold text-dark small"><i class="bi bi-eye text-primary me-1"></i> Live Roll Sticker Preview</span>
                                            <span class="badge bg-primary-subtle text-primary border border-primary font-monospace" id="roll_preview_badge">RAW MATERIAL</span>
                                        </div>
                                        <div class="border rounded-3 p-3 bg-white shadow-sm" style="border: 1.5px solid #0f172a !important;">
                                            <div class="d-flex align-items-center justify-content-between pb-2 border-bottom border-secondary-subtle" style="border-style: dashed !important;">
                                                <div class="d-flex align-items-center">
                                                    <span style="background-color: #0d6efd; color: #ffffff; width: 17px; height: 17px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; margin-right: 6px;">
                                                        <i class="bi bi-check-lg"></i>
                                                    </span>
                                                    <span class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.04em;"><?= htmlspecialchars($concernName) ?></span>
                                                </div>
                                                <span class="badge font-monospace px-2 py-1" style="background-color: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; font-size: 0.70rem;" id="roll_preview_ref">PO-REF</span>
                                            </div>

                                            <div class="d-flex align-items-center gap-3 pt-2">
                                                <div class="d-flex align-items-center justify-content-center bg-white flex-shrink-0" style="width: 76px; height: 76px;" id="roll_qr_box"></div>
                                                <div class="flex-grow-1 overflow-hidden" style="line-height: 1.35; font-size: 0.76rem;">
                                                    <div class="fw-bold text-dark text-uppercase mb-1 text-truncate" style="font-size: 0.86rem; display: none;" id="roll_preview_title"></div>
                                                    <div class="text-truncate"><span class="text-secondary">Fabric:</span> <strong class="text-dark" id="roll_preview_fabric">-</strong></div>
                                                    <div class="text-truncate"><span class="text-secondary">Color:</span> <strong class="text-dark text-uppercase" id="roll_preview_color">-</strong></div>
                                                    <div class="text-truncate"><span class="text-secondary">Design:</span> <strong class="text-dark text-uppercase" id="roll_preview_design">-</strong></div>
                                                    <div class="text-truncate"><span class="text-secondary">Price:</span> <strong class="text-success fw-bold" id="roll_preview_price">₹0.00</strong></div>
                                                    <div class="text-truncate"><span class="text-secondary">Code:</span> <strong class="font-monospace text-primary fw-bold" id="roll_preview_code_tag">-</strong></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2 mt-auto align-items-center">
                                        <button type="submit" class="btn btn-primary flex-grow-1 py-2 fw-semibold shadow-sm">
                                            <i class="bi bi-printer-fill me-1"></i> Generate Printable Sheet
                                        </button>
                                        <button type="button" class="btn btn-success py-2 fw-semibold shadow-sm px-3 text-nowrap" id="roll_add_stock_btn" onclick="submitRollToStock()" title="Inward selected roll directly into Purchase Stocks">
                                            <i class="bi bi-box-arrow-in-down me-1"></i> Add to Stock
                                        </button>
                                        <a href="<?= \App\Core\Controller::url('/m/purchase_stocks?tab=rolls') ?>" class="btn btn-outline-success py-2 fw-semibold shadow-sm px-3 d-none text-nowrap" id="roll_in_stock_badge" title="Already in Stock - View in Purchase Stocks">
                                            <i class="bi bi-check-circle-fill text-success me-1"></i> In Stock
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Separate Fabric Rolls Table -->
                        <div class="col-lg-7">
                            <div class="card border-0 shadow-sm rounded-3 h-100">
                                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                        <i class="bi bi-stack text-primary"></i>
                                        <span>Purchase Fabric Rolls Directory</span>
                                    </h6>
                                    <input type="text" id="search_rolls_table" class="form-control form-control-sm" style="width: 220px;" placeholder="Search roll, fabric, bill...">
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 table-custom" id="tableRollItems">
                                        <thead class="table-light text-muted small">
                                            <tr>
                                                <th>#</th>
                                                <th>Roll & Fabric</th>
                                                <th>Color & Design</th>
                                                <th>Bill & Supplier</th>
                                                <th>Weight / RQ</th>
                                                <th class="text-end pe-3">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($purchaseRollItems)): ?>
                                                <tr><td colspan="6" class="text-center py-4 text-muted">No fabric rolls found. Record purchase bills with fabric rolls to see items here.</td></tr>
                                            <?php else: ?>
                                                <?php $idx = 1; foreach ($purchaseRollItems as $item): 
                                                    $codeVal = trim($item['item_code'] ?? '');
                                                    $nameVal = trim($item['item_name'] ?? '');
                                                    $patternVal = trim($item['pattern'] ?? '');
                                                    $codeIsGenericRoll = preg_match('/^ROLL\s*#?\s*\d*$/i', $codeVal);
                                                    $nameIsPatternFabric = !empty($patternVal) && (strcasecmp($nameVal, $patternVal . ' Fabric') === 0 || strcasecmp($nameVal, $patternVal) === 0);

                                                    if (!empty($codeVal) && !$codeIsGenericRoll) {
                                                        $fabricName = $codeVal;
                                                    } elseif (!empty($nameVal) && !$nameIsPatternFabric) {
                                                        $fabricName = $nameVal;
                                                    } else {
                                                        $fabricName = !empty($codeVal) ? $codeVal : (!empty($nameVal) ? $nameVal : 'Fabric Material');
                                                    }
                                                    $code = $item['item_code'] ?: ('ROLL-' . $item['id']);
                                                ?>
                                                    <tr class="roll-row" data-search="<?= strtolower(htmlspecialchars($fabricName . ' ' . $item['item_name'] . ' ' . $code . ' ' . ($item['color'] ?? '') . ' ' . ($item['pattern'] ?? '') . ' ' . $item['bill_no'] . ' ' . ($item['supplier_name'] ?? ''))) ?>">
                                                        <td class="text-muted small"><?= $idx++ ?></td>
                                                        <td>
                                                            <div class="fw-bold text-dark"><?= htmlspecialchars($fabricName) ?></div>
                                                            <div class="small text-muted d-flex align-items-center gap-1 mt-1">
                                                                <span class="badge bg-primary-subtle text-primary border" style="font-size: 0.7rem;">FABRIC ROLL</span>
                                                                <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($code) ?></span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($item['color'] ?: 'STANDARD') ?></div>
                                                            <small class="text-muted"><?= htmlspecialchars($item['pattern'] ?: ($item['specification'] ?: '-')) ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="small fw-semibold text-dark">Bill #<?= htmlspecialchars($item['bill_no']) ?></div>
                                                            <small class="text-muted"><?= htmlspecialchars($item['supplier_company'] ?: ($item['supplier_name'] ?: 'Vendor')) ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-dark font-monospace"><?= number_format((float)$item['qty'], 2) ?> <?= htmlspecialchars($item['unit'] ?: 'Rolls') ?></span>
                                                            <?php if (!empty($item['net_weight'])): ?>
                                                                <small class="d-block text-muted"><?= number_format((float)$item['net_weight'], 2) ?> kg</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-end pe-3 text-nowrap">
                                                            <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 fw-semibold me-1" onclick="selectRollItem(<?= (int)$item['id'] ?>)">
                                                                <i class="bi bi-printer me-1"></i> Print
                                                            </button>
                                                            <?php $isInStock = (($item['inward_status'] ?? '') === 'completed'); ?>
                                                            <?php if ($isInStock): ?>
                                                                <a href="<?= \App\Core\Controller::url('/m/purchase_stocks?tab=rolls') ?>" class="btn btn-sm btn-outline-success py-1 px-2 fw-semibold" title="In Stock - View in Purchase Stocks">
                                                                    <i class="bi bi-check-circle-fill text-success me-1"></i> In Stock
                                                                </a>
                                                            <?php else: ?>
                                                                <form action="<?= \App\Core\Controller::url('/m/label/add_to_stock') ?>" method="POST" class="d-inline">
                                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                                                    <input type="hidden" name="tab" value="rolls">
                                                                    <button type="submit" class="btn btn-sm btn-success py-1 px-2 fw-semibold" title="Inward roll directly into Purchase Stocks">
                                                                        <i class="bi bi-box-arrow-in-down me-1"></i> Add to Stock
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ---------------------------------------------------- -->
                <!-- SUB-PANE 1B: OTHER GARMENTS PRODUCTS & TRIMS         -->
                <!-- ---------------------------------------------------- -->
                <div class="tab-pane fade <?= ($activeSubTab === 'garment_other') ? 'show active' : '' ?>" id="subtab-other-pane" role="tabpanel">
                    <div class="row g-4">
                        <!-- Other Garments Generator Config Card -->
                        <div class="col-lg-5">
                            <div class="card border-0 shadow-sm rounded-3 h-100">
                                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                        <i class="bi bi-scissors text-info fs-5"></i>
                                        <span>Other Garments Label Configuration</span>
                                    </h6>
                                    <span class="badge bg-info-subtle text-info border border-info">Trims & Supplies</span>
                                </div>
                                <form action="<?= \App\Core\Controller::url('/m/label_generation/generate') ?>" method="POST" target="_blank" class="card-body p-4 d-flex flex-column">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="label_type" value="purchase_raw">

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Select Garment Supply / Trim Item <span class="text-danger">*</span></label>
                                        <select name="item_id" id="other_item_select" class="form-select" required onchange="updateOtherPreview()">
                                            <option value="">-- Choose Other Garment Product --</option>
                                            <?php foreach ($purchaseOtherItems as $other): 
                                                $code = $other['item_code'] ?: ('TRIM-' . $other['id']);
                                                $tagCode = QrService::generateTagCode(
                                                    $other['item_name'] ?: 'Trims',
                                                    $other['color'] ?? '',
                                                    $other['pattern'] ?: ($other['specification'] ?? ''),
                                                    (string)$other['id']
                                                );
                                                $isOtherInStock = (($other['inward_status'] ?? '') === 'completed');
                                            ?>
                                                <option value="<?= $other['id'] ?>"
                                                        data-name="<?= htmlspecialchars($other['item_name']) ?>"
                                                        data-code="<?= htmlspecialchars($code) ?>"
                                                        data-tag="<?= htmlspecialchars($tagCode) ?>"
                                                        data-type="<?= htmlspecialchars($other['product_type'] ?: 'Garment Supply') ?>"
                                                        data-isroll="0"
                                                        data-inward="<?= $isOtherInStock ? 'completed' : 'pending' ?>"
                                                        data-vendor="<?= htmlspecialchars($other['supplier_company'] ?: ($other['supplier_name'] ?: 'Vendor Mill')) ?>"
                                                        data-color="<?= htmlspecialchars($other['color'] ?: 'STANDARD') ?>"
                                                        data-pattern="<?= htmlspecialchars($other['pattern'] ?: '') ?>"
                                                        data-spec="<?= htmlspecialchars($other['specification'] ?? '') ?>"
                                                        data-qty="<?= htmlspecialchars($other['qty']) ?>"
                                                        data-unit="<?= htmlspecialchars($other['unit'] ?: 'pcs') ?>"
                                                        data-price="<?= (float)($other['unit_price'] ?: ($other['mrp'] ?: 0)) ?>"
                                                        data-bill="<?= htmlspecialchars($other['bill_no']) ?>"
                                                        data-qr="<?= htmlspecialchars($tagCode) ?>">
                                                    [TRIM] <?= htmlspecialchars($other['item_name']) ?> (<?= htmlspecialchars($tagCode) ?>) &bull; <?= number_format((float)$other['qty'], 2) ?> <?= htmlspecialchars($other['unit']) ?> &bull; Bill #<?= htmlspecialchars($other['bill_no']) ?><?= $isOtherInStock ? ' &bull; [IN STOCK]' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Directly sourced from Purchase Billing Garment Other Supplies & Trims.</small>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label small fw-semibold mb-0">Number of Label Copies (N Prints) <span class="text-danger">*</span></label>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-primary py-0 px-2" onclick="matchOtherQty()">Match Qty</button>
                                            </div>
                                        </div>
                                        <div class="input-group">
                                            <button type="button" class="btn btn-outline-secondary" onclick="adjustCopies('other_qty', -1)" aria-label="Decrease label quantity"><i class="bi bi-dash-lg"></i></button>
                                            <input type="number" name="qty" id="other_qty" class="form-control text-center" min="1" max="500" value="12" required>
                                            <button type="button" class="btn btn-outline-secondary" onclick="adjustCopies('other_qty', 1)" aria-label="Increase label quantity"><i class="bi bi-plus-lg"></i></button>
                                            <span class="input-group-text bg-light">Stickers</span>
                                        </div>
                                    </div>

                                    <!-- Live Other Garment Sticker Preview -->
                                    <div class="p-3 border rounded-3 bg-light mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-bold text-dark small"><i class="bi bi-eye text-info me-1"></i> Live Trim Sticker Preview</span>
                                            <span class="badge bg-info-subtle text-info border border-info font-monospace" id="other_preview_badge">PURCHASE TRIM</span>
                                        </div>
                                        <div class="border rounded-3 p-3 bg-white shadow-sm" style="border: 1.5px solid #0f172a !important;">
                                            <div class="d-flex align-items-center justify-content-between pb-2 border-bottom border-secondary-subtle" style="border-style: dashed !important;">
                                                <div class="d-flex align-items-center">
                                                    <span style="background-color: #0284c7; color: #ffffff; width: 17px; height: 17px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; margin-right: 6px;">
                                                        <i class="bi bi-check-lg"></i>
                                                    </span>
                                                    <span class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.04em;"><?= htmlspecialchars($concernName) ?></span>
                                                </div>
                                                <span class="badge font-monospace px-2 py-1" style="background-color: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; font-size: 0.70rem;" id="other_preview_ref">TRIM-REF</span>
                                            </div>

                                            <div class="d-flex align-items-center gap-3 pt-2">
                                                <div class="d-flex align-items-center justify-content-center bg-white flex-shrink-0" style="width: 76px; height: 76px;" id="other_qr_box"></div>
                                                <div class="flex-grow-1 overflow-hidden" style="line-height: 1.35; font-size: 0.76rem;">
                                                    <div class="fw-bold text-dark text-uppercase mb-1 text-truncate" style="font-size: 0.86rem;" id="other_preview_title">SEWING THREAD</div>
                                                    <div class="text-truncate"><span class="text-secondary">Item:</span> <strong class="text-dark" id="other_preview_item">-</strong></div>
                                                    <div class="text-truncate"><span class="text-secondary">Color:</span> <strong class="text-dark text-uppercase" id="other_preview_color">-</strong></div>
                                                    <div class="text-truncate"><span class="text-secondary">Design:</span> <strong class="text-dark text-uppercase" id="other_preview_design">STANDARD</strong></div>
                                                    <div class="text-truncate"><span class="text-secondary">Price:</span> <strong class="text-success fw-bold" id="other_preview_price">₹45.00</strong></div>
                                                    <div class="text-truncate"><span class="text-secondary">Code:</span> <strong class="font-monospace text-primary fw-bold" id="other_preview_code_tag">TR-TH-WHT</strong></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2 mt-auto align-items-center">
                                        <button type="submit" class="btn btn-info text-white flex-grow-1 py-2 fw-semibold shadow-sm">
                                            <i class="bi bi-printer-fill me-1"></i> Generate Printable Sheet
                                        </button>
                                        <button type="button" class="btn btn-success py-2 fw-semibold shadow-sm px-3 text-nowrap" id="other_add_stock_btn" onclick="submitOtherToStock()" title="Inward selected trim directly into Purchase Stocks">
                                            <i class="bi bi-box-arrow-in-down me-1"></i> Add to Stock
                                        </button>
                                        <a href="<?= \App\Core\Controller::url('/m/purchase_stocks?tab=garment_other') ?>" class="btn btn-outline-success py-2 fw-semibold shadow-sm px-3 d-none text-nowrap" id="other_in_stock_badge" title="Already in Stock - View in Purchase Stocks">
                                            <i class="bi bi-check-circle-fill text-success me-1"></i> In Stock
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Separate Other Garments Products Table -->
                        <div class="col-lg-7">
                            <div class="card border-0 shadow-sm rounded-3 h-100">
                                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                        <i class="bi bi-scissors text-info"></i>
                                        <span>Purchase Other Garments & Trims Directory</span>
                                    </h6>
                                    <input type="text" id="search_other_table" class="form-control form-control-sm" style="width: 220px;" placeholder="Search trims, supplies, bill...">
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 table-custom" id="tableOtherItems">
                                        <thead class="table-light text-muted small">
                                            <tr>
                                                <th>#</th>
                                                <th>Garment Supply Item</th>
                                                <th>Specification & Color</th>
                                                <th>Bill & Supplier</th>
                                                <th>Inwarded Qty</th>
                                                <th class="text-end pe-3">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($purchaseOtherItems)): ?>
                                                <tr><td colspan="6" class="text-center py-4 text-muted">No other garment supplies or trims found. Record purchase bills with trims to see items here.</td></tr>
                                            <?php else: ?>
                                                <?php $idx = 1; foreach ($purchaseOtherItems as $item): 
                                                    $code = $item['item_code'] ?: ('TRIM-' . $item['id']);
                                                ?>
                                                    <tr class="other-row" data-search="<?= strtolower(htmlspecialchars($item['item_name'] . ' ' . $code . ' ' . ($item['color'] ?? '') . ' ' . ($item['specification'] ?? '') . ' ' . $item['bill_no'] . ' ' . ($item['supplier_name'] ?? ''))) ?>">
                                                        <td class="text-muted small"><?= $idx++ ?></td>
                                                        <td>
                                                            <div class="fw-bold text-dark"><?= htmlspecialchars($item['item_name']) ?></div>
                                                            <div class="small text-muted d-flex align-items-center gap-1 mt-1">
                                                                <span class="badge bg-info-subtle text-info border" style="font-size: 0.7rem;"><?= strtoupper($item['product_type'] ?: 'TRIM / SUPPLY') ?></span>
                                                                <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($code) ?></span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($item['specification'] ?: '-') ?></div>
                                                            <?php if (!empty($item['color'])): ?>
                                                                <small class="text-muted">Color: <?= htmlspecialchars($item['color']) ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="small fw-semibold text-dark">Bill #<?= htmlspecialchars($item['bill_no']) ?></div>
                                                            <small class="text-muted"><?= htmlspecialchars($item['supplier_company'] ?: ($item['supplier_name'] ?: 'Vendor')) ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-dark font-monospace"><?= number_format((float)$item['qty'], 2) ?> <?= htmlspecialchars($item['unit'] ?: 'pcs') ?></span>
                                                        </td>
                                                        <td class="text-end pe-3 text-nowrap">
                                                            <button type="button" class="btn btn-sm btn-outline-info py-1 px-2 fw-semibold me-1" onclick="selectOtherItem(<?= (int)$item['id'] ?>)">
                                                                <i class="bi bi-printer me-1"></i> Print
                                                            </button>
                                                            <?php $isInStock = (($item['inward_status'] ?? '') === 'completed'); ?>
                                                            <?php if ($isInStock): ?>
                                                                <a href="<?= \App\Core\Controller::url('/m/purchase_stocks?tab=garment_other') ?>" class="btn btn-sm btn-outline-success py-1 px-2 fw-semibold" title="In Stock - View in Purchase Stocks">
                                                                    <i class="bi bi-check-circle-fill text-success me-1"></i> In Stock
                                                                </a>
                                                            <?php else: ?>
                                                                <form action="<?= \App\Core\Controller::url('/m/label/add_to_stock') ?>" method="POST" class="d-inline">
                                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                                                    <input type="hidden" name="tab" value="garment_other">
                                                                    <button type="submit" class="btn btn-sm btn-success py-1 px-2 fw-semibold" title="Inward item directly into Purchase Stocks">
                                                                        <i class="bi bi-box-arrow-in-down me-1"></i> Add to Stock
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ======================================================== -->
        <!-- TAB 2: PURCHASE FINISHED GOODS (Mapped with Catalog)     -->
        <!-- ======================================================== -->
        <div class="tab-pane fade <?= ($curTab === 'purchase_fg') ? 'show active' : '' ?>" id="tab-purchase-fg" role="tabpanel">
            <div class="row g-4">
                <!-- Generator Config Card -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-box-seam text-success fs-5"></i>
                                <span>Purchase Finished Goods Label</span>
                            </h6>
                            <span class="badge bg-success-subtle text-success border border-success">Catalog Mapped</span>
                        </div>
                        <form action="<?= \App\Core\Controller::url('/m/label_generation/generate') ?>" method="POST" target="_blank" class="card-body p-4 d-flex flex-column" id="form_fg_label">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="label_type" value="purchase_fg">

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Select Purchased Finished Good <span class="text-danger">*</span></label>
                                <select name="item_id" id="fg_item_select" class="form-select" required onchange="updateFgPreview()">
                                    <option value="">-- Choose Finished Goods Item --</option>
                                    <?php 
                                    $mappedFgCount = 0;
                                    foreach ($purchaseFgItems as $fg): 
                                        $isMapped = !empty($fg['variant_id']);
                                        // Requirement: dropdown list ONLY wants mapped goods list
                                        if (!$isMapped) {
                                            continue;
                                        }
                                        $mappedFgCount++;
                                        $fgVariantAttributeData = json_decode((string) ($fg['variant_attributes'] ?? ''), true);
                                        $fgVariantAttributeData = is_array($fgVariantAttributeData) ? $fgVariantAttributeData : [];
                                        $fgVariantDetails = [];
                                        $fgVariantSize = trim((string) ($fg['variant_size'] ?? ''));
                                        if ($fgVariantSize !== '') {
                                            $fgVariantDetails['Size'] = $fgVariantSize;
                                        }
                                        $fgCustomAttributes = [];
                                        foreach ($fgVariantAttributeData as $attributeType => $attributeValue) {
                                            $attributeType = strtolower(trim((string) $attributeType));
                                            if (
                                                $attributeType === '' ||
                                                in_array($attributeType, ['color', 'size', 'hsn', 'weight', 'allocated_qty', 'custom_attributes'], true) ||
                                                !is_scalar($attributeValue)
                                            ) {
                                                continue;
                                            }
                                            $fgCustomAttributes[$attributeType] = trim((string) $attributeValue);
                                        }
                                        if (is_array($fgVariantAttributeData['custom_attributes'] ?? null)) {
                                            $fgCustomAttributes = array_merge($fgCustomAttributes, $fgVariantAttributeData['custom_attributes']);
                                        }
                                        foreach ($fgCustomAttributes as $attributeType => $attributeValue) {
                                            $attributeType = strtolower(trim((string) $attributeType));
                                            if (!is_scalar($attributeValue)) {
                                                continue;
                                            }
                                            $attributeValue = trim((string) $attributeValue);
                                            if ($attributeType === '' || in_array($attributeType, ['color', 'size'], true) || $attributeValue === '') {
                                                continue;
                                            }
                                            $fgVariantDetails[ucwords(str_replace(['_', '-'], ' ', $attributeType))] = $attributeValue;
                                        }
                                        $fgVariantDetailsJson = json_encode($fgVariantDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                                        $varName = $fg['variant_name'] ?: $fg['item_name'];
                                        $sku = $fg['sku_code'] ?: ($fg['item_code'] ?: 'FG-' . $fg['id']);
                                        $fgColor = trim((string) ($fgVariantAttributeData['color'] ?? '')) ?: ($fg['color'] ?: 'STANDARD');
                                        $tagCode = QrService::generateTagCode(
                                            $varName,
                                            $fgColor,
                                            $fg['variant_size'] ?: ($fg['specification'] ?? ''),
                                            (string) ($fg['variant_id'] ?: $fg['id'])
                                        );
                                        $mrp = $fg['variant_mrp'] ?: ($fg['mrp'] ?: $fg['unit_price']);
                                        $size = $fg['variant_size'] ?: ($fg['specification'] ?: 'Free');
                                        $cat = $fg['category_name'] ?: 'Apparel';
                                        $isFgInStock = (($fg['inward_status'] ?? '') === 'completed');
                                    ?>
                                        <option value="<?= $fg['id'] ?>"
                                                data-name="<?= htmlspecialchars($varName) ?>"
                                                data-sku="<?= htmlspecialchars($sku) ?>"
                                                data-tag="<?= htmlspecialchars($tagCode) ?>"
                                                data-mrp="<?= htmlspecialchars($mrp) ?>"
                                                data-price="<?= htmlspecialchars($mrp) ?>"
                                                data-size="<?= htmlspecialchars($size) ?>"
                                                data-pattern="<?= htmlspecialchars($fg['pattern'] ?? '') ?>"
                                                data-spec="<?= htmlspecialchars($fg['specification'] ?? '') ?>"
                                                data-attributes="<?= htmlspecialchars($fgVariantDetailsJson ?: '{}', ENT_QUOTES, 'UTF-8') ?>"
                                                data-variant-summary="<?= htmlspecialchars($fg['mapped_variant_summary'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                data-cat="<?= htmlspecialchars($cat) ?>"
                                                data-concern="<?= htmlspecialchars($fg['item_concern_name'] ?: $concernName, ENT_QUOTES, 'UTF-8') ?>"
                                                data-brand="<?= htmlspecialchars($fg['brand_name'] ?: ($fg['supplier_company'] ?: 'GRM')) ?>"
                                                data-color="<?= htmlspecialchars($fgColor) ?>"
                                                data-mapped="1"
                                                data-varid="<?= htmlspecialchars($fg['variant_id'] ?? '') ?>"
                                                data-inward="<?= $isFgInStock ? 'completed' : 'pending' ?>"
                                                data-qty="<?= htmlspecialchars($fg['qty']) ?>"
                                                data-unit="<?= htmlspecialchars($fg['unit'] ?: 'pcs') ?>"
                                                data-bill="<?= htmlspecialchars($fg['bill_no']) ?>"
                                                data-qr="<?= htmlspecialchars($tagCode) ?>">
                                            <?= htmlspecialchars($varName) ?> (<?= htmlspecialchars($tagCode) ?>) &bull; <?= number_format((float)$fg['qty'], 0) ?> pcs &bull; Bill #<?= htmlspecialchars($fg['bill_no']) ?> [Mapped: <?= htmlspecialchars($sku) ?>]<?= $isFgInStock ? ' &bull; [IN STOCK]' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if (!empty($productionItems)): ?>
                                        <optgroup label="--- Stitched Production Finished Goods ---">
                                            <?php foreach ($productionItems as $pfg): 
                                                $pfgTag = QrService::generateTagCode(
                                                    $pfg['variant_name'],
                                                    $pfg['variant_color'] ?? ($pfg['color'] ?? ''),
                                                    $pfg['program_no'] ?? '',
                                                    (string)($pfg['produced_variant_id'] ?: $pfg['variant_id'] ?: $pfg['id'])
                                                );
                                            ?>
                                                <option value="prod_<?= $pfg['id'] ?>"
                                                        data-name="<?= htmlspecialchars($pfg['variant_name']) ?>"
                                                        data-sku="<?= htmlspecialchars($pfg['sku_code']) ?>"
                                                        data-tag="<?= htmlspecialchars($pfgTag) ?>"
                                                        data-mrp="<?= htmlspecialchars($pfg['variant_mrp']) ?>"
                                                        data-price="<?= htmlspecialchars($pfg['variant_price'] ?? $pfg['variant_mrp']) ?>"
                                                        data-size="<?= htmlspecialchars($pfg['variant_size'] ?: 'Standard') ?>"
                                                        data-pattern="<?= htmlspecialchars($pfg['program_no'] ?: 'In-House') ?>"
                                                        data-cat="<?= htmlspecialchars($pfg['category_name'] ?: 'Apparel') ?>"
                                                        data-brand="GRM Production"
                                                        data-color="<?= htmlspecialchars($pfg['variant_color'] ?? ($pfg['color'] ?? 'Standard')) ?>"
                                                        data-mapped="1"
                                                        data-varid="<?= htmlspecialchars($pfg['variant_id'] ?? '') ?>"
                                                        data-qty="<?= htmlspecialchars($pfg['stitched_pieces']) ?>"
                                                        data-unit="pcs"
                                                        data-bill="<?= htmlspecialchars($pfg['log_no']) ?>"
                                                        data-qr="<?= htmlspecialchars($pfgTag) ?>"
                                                        data-in-stock="<?= !empty($pfg['is_in_stock']) ? '1' : '0' ?>"
                                                        data-is-production="1">
                                                    [Stitched] <?= htmlspecialchars($pfg['variant_name']) ?> [<?= htmlspecialchars($pfg['variant_size'] ?: 'Standard') ?>] (<?= htmlspecialchars($pfg['sku_code']) ?>) &bull; <?= (int)$pfg['stitched_pieces'] ?> pcs &bull; Batch #<?= htmlspecialchars($pfg['log_no']) ?> <?= !empty($pfg['is_in_stock']) ? '✓ In Stock' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    <?php if ($mappedFgCount === 0 && empty($productionItems)): ?>
                                        <option value="" disabled>-- No Mapped Goods Found (Please map items in Catalog Mapping) --</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- Catalog Mapping Status Banner -->
                            <div class="mb-3" id="fg_mapping_card" style="display: none;">
                                <div class="p-3 border rounded-3 bg-light" id="fg_mapped_box">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                            <div>
                                                <div class="fw-bold text-dark small" id="fg_mapped_title">Mapped to Catalog</div>
                                                <small class="text-muted font-monospace" id="fg_mapped_sku">SKU</small>
                                            </div>
                                        </div>
                                        <span class="badge bg-success-subtle text-success border border-success">Verified</span>
                                    </div>
                                </div>
                                <div class="p-3 border border-warning-subtle rounded-3 bg-warning-subtle" id="fg_unmapped_box" style="display: none;">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                                        <div class="fw-bold text-dark small">Catalog Mapping Recommended</div>
                                    </div>
                                    <p class="small text-secondary mb-2">This item has not yet been linked to a master product variant. Link it now to print scannable retail POS barcode labels.</p>
                                    <div class="input-group input-group-sm">
                                        <select id="fg_map_variant_select" class="form-select">
                                            <option value="">-- Choose Catalog Variant to Map --</option>
                                            <?php foreach ($allVariants as $av): ?>
                                                <option value="<?= $av['id'] ?>">
                                                    <?= htmlspecialchars($av['sku_code']) ?> &bull; <?= htmlspecialchars($av['name']) ?> (<?= htmlspecialchars($av['size']) ?>) &bull; MRP ₹<?= number_format($av['mrp'], 2) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-success fw-semibold" onclick="doMapVariant()">
                                            <i class="bi bi-link-45deg"></i> Link Variant
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-semibold mb-0">Number of Label Copies (N Prints) <span class="text-danger">*</span></label>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary py-0 px-2" onclick="matchFgQty()">Match Purchase Qty</button>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-secondary" onclick="adjustCopies('fg_qty', -1)" aria-label="Decrease label quantity"><i class="bi bi-dash-lg"></i></button>
                                    <input type="number" name="qty" id="fg_qty" class="form-control text-center" min="1" max="500" value="" placeholder="Enter copies count" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="adjustCopies('fg_qty', 1)" aria-label="Increase label quantity"><i class="bi bi-plus-lg"></i></button>
                                    <span class="input-group-text bg-light">Price Tags</span>
                                </div>
                            </div>

                            <!-- Live Sticker Preview (Image 2 Reference Layout) -->
                            <div class="p-3 border rounded-3 bg-light mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-dark small"><i class="bi bi-eye text-success me-1"></i> Retail Tag Preview</span>
                                    <span class="badge bg-success-subtle text-success border border-success font-monospace" id="fg_preview_badge">READY-MADE</span>
                                </div>
                                <div class="border rounded-3 p-3 bg-white shadow-sm" style="border: 1.5px solid #0f172a !important;">
                                    <!-- Top Header: Concern Name Dynamically & Reference Badge -->
                                    <div class="d-flex align-items-center justify-content-between pb-2 border-bottom border-secondary-subtle" style="border-style: dashed !important;">
                                        <div class="d-flex align-items-center">
                                            <span style="background-color: #0d6efd; color: #ffffff; width: 17px; height: 17px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; margin-right: 6px;">
                                                <i class="bi bi-check-lg"></i>
                                            </span>
                                            <span class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.04em;" id="fg_preview_concern" data-default-concern="<?= htmlspecialchars($concernName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($concernName) ?></span>
                                        </div>
                                        <span class="badge font-monospace px-2 py-1" style="background-color: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; font-size: 0.70rem;" id="fg_preview_ref">PO-REF</span>
                                    </div>

                                    <!-- Body: Left QR, Right Details Stack -->
                                    <div class="d-flex align-items-center gap-3 pt-2">
                                        <div class="d-flex align-items-center justify-content-center bg-white flex-shrink-0" style="width: 76px; height: 76px;" id="fg_qr_box"></div>
                                        <div class="flex-grow-1 overflow-hidden" style="line-height: 1.35; font-size: 0.76rem;">
                                            <div class="fw-bold text-dark text-uppercase mb-1 text-truncate" style="font-size: 0.86rem;" id="fg_preview_title">Finished Good</div>
                                            <div class="text-truncate"><span class="text-secondary">Product:</span> <strong class="text-dark" id="fg_preview_cat">Apparel</strong></div>
                                            <div class="text-truncate"><span class="text-secondary">Color:</span> <strong class="text-dark text-uppercase" id="fg_preview_color">STANDARD</strong></div>
                                            <div class="text-truncate" id="fg_preview_design_row"><span class="text-secondary">Design:</span> <strong class="text-dark text-uppercase" id="fg_preview_design">STANDARD</strong></div>
                                            <div id="fg_preview_variant_attributes" class="d-none"></div>
                                            <div id="fg_preview_variant_summary_row" class="d-none"><span class="text-secondary">Mapped variants:</span> <strong class="text-dark" id="fg_preview_variant_summary"></strong></div>
                                            <div class="text-truncate"><span class="text-secondary">Price:</span> <strong class="text-success fw-bold" id="fg_preview_price">₹0.00</strong></div>
                                            <div class="text-truncate"><span class="text-secondary">Code:</span> <strong class="font-monospace text-primary fw-bold" id="fg_preview_code_tag">FG-0-01</strong></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-auto align-items-center">
                                <button type="submit" class="btn btn-success flex-grow-1 py-2 fw-semibold shadow-sm">
                                    <i class="bi bi-printer-fill me-1"></i> Generate Printable Sheet
                                </button>
                                <button type="button" class="btn btn-outline-success py-2 fw-semibold shadow-sm px-3 text-nowrap" id="fg_add_stock_btn" onclick="submitFgToStock()" title="Inward selected finished goods into Live Stocks">
                                    <i class="bi bi-box-arrow-in-down me-1"></i> Add to Stock
                                </button>
                                <a href="<?= \App\Core\Controller::url('/m/stocks') ?>" class="btn btn-outline-success py-2 fw-semibold shadow-sm px-3 d-none text-nowrap" id="fg_in_stock_badge" title="Already in Stock - View Live Stocks">
                                    <i class="bi bi-check-circle-fill text-success me-1"></i> In Stock
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Purchase Finished Goods Directory Table -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-list-check text-success"></i>
                                <span>Purchased Finished Goods Directory</span>
                            </h6>
                            <input type="text" id="search_fg_table" class="form-control form-control-sm" style="width: 220px;" placeholder="Search finished goods...">
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 table-custom" id="tableFgItems">
                                <thead class="table-light text-muted small">
                                    <tr>
                                        <th>#</th>
                                        <th>Finished Garment</th>
                                        <th>Bill & Supplier</th>
                                        <th>Catalog Status</th>
                                        <th>Qty & MRP</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($purchaseFgItems)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No finished goods recorded in purchase billing.</td></tr>
                                    <?php else: ?>
                                        <?php $idx = 1; foreach ($purchaseFgItems as $fg): 
                                            $isMapped = !empty($fg['variant_id']);
                                            $sku = $fg['sku_code'] ?: ($fg['item_code'] ?: 'FG-' . $fg['id']);
                                        ?>
                                            <tr class="fg-row" data-search="<?= strtolower(htmlspecialchars($fg['item_name'] . ' ' . $sku . ' ' . $fg['bill_no'])) ?>">
                                                <td class="text-muted small"><?= $idx++ ?></td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($fg['variant_name'] ?: $fg['item_name']) ?></div>
                                                    <div class="small text-muted d-flex align-items-center gap-1 mt-1">
                                                        <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($sku) ?></span>
                                                        <span class="badge bg-dark" style="font-size: 0.7rem;"><?= htmlspecialchars($fg['variant_size'] ?: ($fg['specification'] ?: 'Free')) ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small fw-semibold text-dark">Bill #<?= htmlspecialchars($fg['bill_no']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($fg['supplier_name'] ?: 'Vendor') ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($isMapped): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success">
                                                            <i class="bi bi-check-circle me-1"></i> Mapped
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning border border-warning">
                                                            <i class="bi bi-link-45deg me-1"></i> Unmapped
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?= number_format((float)$fg['qty'], 0) ?> pcs</div>
                                                    <div class="small text-danger fw-bold">MRP ₹<?= number_format((float)($fg['variant_mrp'] ?: ($fg['mrp'] ?: $fg['unit_price'])), 2) ?></div>
                                                </td>
                                                <td class="text-end pe-3 text-nowrap">
                                                    <?php if ($isMapped): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-success py-1 px-2 fw-semibold me-1" onclick="selectFgItem(<?= (int)$fg['id'] ?>)">
                                                            <i class="bi bi-printer me-1"></i> Print
                                                        </button>
                                                    <?php else: ?>
                                                        <a href="<?= \App\Core\Controller::url('/production/catalog-mapping/map?id=' . (int)$fg['id']) ?>" class="btn btn-sm btn-outline-warning py-1 px-2 fw-semibold me-1" title="Map item in Catalog Mapping before printing retail label">
                                                            <i class="bi bi-link-45deg me-1"></i> Map to Catalog
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php $isFgInStock = (($fg['inward_status'] ?? '') === 'completed'); ?>
                                                    <?php if ($isFgInStock): ?>
                                                        <a href="<?= \App\Core\Controller::url('/m/stocks') ?>" class="btn btn-sm btn-outline-success py-1 px-2 fw-semibold" title="Already in Stock - Click to view Live Stocks">
                                                            <i class="bi bi-check-circle-fill text-success me-1"></i> In Stock
                                                        </a>
                                                    <?php elseif ($isMapped): ?>
                                                        <form action="<?= \App\Core\Controller::url('/m/label/add_to_stock') ?>" method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                            <input type="hidden" name="item_id" value="<?= (int)$fg['id'] ?>">
                                                            <input type="hidden" name="tab" value="finished_goods">
                                                            <button type="submit" class="btn btn-sm btn-success py-1 px-2 fw-semibold" title="Inward finished goods into Live Stocks">
                                                                <i class="bi bi-box-arrow-in-down me-1"></i> Add to Stock
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="small text-muted">Map to catalog first</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- ======================================================== -->
        <!-- TAB 3: PRODUCTION LABEL (After Stitching Products)       -->
        <!-- ======================================================== -->
        <div class="tab-pane fade <?= ($curTab === 'production') ? 'show active' : '' ?>" id="tab-production" role="tabpanel">
            <div class="row g-4">

                <!-- ===== LEFT: Configuration Card ===== -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-scissors text-warning fs-5"></i>
                                <span>Production Label Configuration</span>
                            </h6>
                            <span class="badge bg-warning-subtle text-warning border border-warning">After Stitching</span>
                        </div>
                        <div class="card-body p-3 d-flex flex-column">

                            <!-- MODE TOGGLE BUTTONS -->
                            <div class="mb-3">
                                <div class="d-flex gap-2">
                                    <button type="button" id="prod_mode_batch_btn" onclick="switchProdMode('batch')"
                                        class="btn btn-warning text-dark fw-semibold flex-fill py-2">
                                        <i class="bi bi-collection-fill me-1"></i> Way 1: Full Batch
                                    </button>
                                    <button type="button" id="prod_mode_variant_btn" onclick="switchProdMode('variant')"
                                        class="btn btn-outline-secondary fw-semibold flex-fill py-2">
                                        <i class="bi bi-tag me-1"></i> Way 2: Single Variant
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <span id="prod_mode_hint_text">Generate all variants of a batch at once (e.g. 70 labels across 7 sizes)</span>
                                </small>
                            </div>

                            <!-- ===== WAY 1: BATCH MODE UI ===== -->
                            <div id="prod_batch_ui" class="d-flex flex-column flex-grow-1">

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Select Stitched Production Batch <span class="text-danger">*</span></label>
                                    <select id="prod_batch_select" class="form-select" onchange="onBatchSelect()">
                                        <option value="">-- Choose Stitched Production Batch --</option>
                                        <?php foreach ($productionBatches as $batch):
                                            $batchItemsJson = htmlspecialchars(json_encode(array_values($batch['items'])));
                                            $bPendingCount = count(array_filter($batch['items'], function($it){ return empty($it['is_in_stock']); }));
                                        ?>
                                        <option value="<?= (int)$batch['stitching_id'] ?>"
                                                data-batch-no="<?= htmlspecialchars($batch['batch_no']) ?>"
                                                data-log-no="<?= htmlspecialchars($batch['log_no']) ?>"
                                                data-program="<?= htmlspecialchars($batch['program_no']) ?>"
                                                data-catalog="<?= htmlspecialchars($batch['catalog_name']) ?>"
                                                data-total-pcs="<?= (int)$batch['total_pieces'] ?>"
                                                data-variant-count="<?= count($batch['items']) ?>"
                                                data-pending-count="<?= (int)$bPendingCount ?>"
                                                data-is-all-in-stock="<?= $batch['is_all_in_stock'] ? '1' : '0' ?>"
                                                data-items="<?= $batchItemsJson ?>">
                                            Batch #<?= htmlspecialchars($batch['batch_no']) ?>
                                            &bull; <?= htmlspecialchars($batch['program_no']) ?>
                                            &bull; <?= count($batch['items']) ?> Variants
                                            &bull; <?= (int)$batch['total_pieces'] ?> Total Pcs
                                            <?= $batch['is_all_in_stock'] ? ' ✓ All In Stock' : '' ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Summary Banner -->
                                <div id="prod_batch_summary" class="alert alert-warning py-2 px-3 mb-3 d-none small">
                                    <i class="bi bi-layers-fill me-1"></i>
                                    <span id="prod_batch_summary_text">Select a batch to see summary</span>
                                </div>

                                <!-- Variant Preview Table -->
                                <div id="prod_batch_table_wrap" class="d-none mb-3">
                                    <div class="table-responsive rounded-3 border" style="max-height:220px;overflow-y:auto;">
                                        <table class="table table-sm align-middle mb-0" id="prod_batch_variant_table">
                                            <thead class="table-warning text-dark small sticky-top">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Size</th>
                                                    <th>Color</th>
                                                    <th>SKU</th>
                                                    <th class="text-center">Pcs</th>
                                                    <th class="text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="prod_batch_variant_tbody"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Batch Action Buttons -->
                                <div class="d-flex gap-2 mt-auto">
                                    <form action="<?= \App\Core\Controller::url('/m/label_generation/generate') ?>" method="POST" target="_blank" class="flex-fill" id="prod_batch_print_form">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="label_type" value="production">
                                        <input type="hidden" name="prod_print_mode" value="batch">
                                        <input type="hidden" name="batch_id" id="hidden_batch_id" value="">
                                        <button type="submit" class="btn btn-warning text-dark w-100 py-2 fw-semibold shadow-sm"
                                                id="prod_batch_print_btn" disabled onclick="return validateBatchSelect()">
                                            <i class="bi bi-printer-fill me-1"></i> Generate All Labels
                                        </button>
                                    </form>
                                    <form action="<?= \App\Core\Controller::url('/m/label/add_to_stock') ?>" method="POST" id="prod_batch_stock_form">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="item_id" id="hidden_batch_stock_id" value="">
                                        <input type="hidden" name="tab" value="production">
                                        <button type="submit" class="btn btn-success py-2 px-3 fw-semibold shadow-sm text-nowrap"
                                                id="prod_batch_stock_btn" disabled onclick="return validateBatchStockSelect()">
                                            <i class="bi bi-box-arrow-in-down me-1"></i> Add Batch to Stock
                                        </button>
                                    </form>
                                    <a href="<?= \App\Core\Controller::url('/m/stocks?tab=live_stock') ?>"
                                       class="btn btn-outline-success py-2 px-3 fw-semibold shadow-sm text-nowrap d-none"
                                       id="prod_batch_in_stock_btn" title="All variants already in stock - Click to view in Live Stocks">
                                        <i class="bi bi-check-circle-fill me-1"></i> In Stock &bull; View Stocks
                                    </a>
                                </div>
                                <small class="text-muted mt-2 d-block" id="prod_batch_stock_note"></small>

                            </div><!-- /prod_batch_ui -->

                            <!-- ===== WAY 2: SINGLE VARIANT UI ===== -->
                            <div id="prod_variant_ui" class="d-none d-flex flex-column flex-grow-1">
                                <form action="<?= \App\Core\Controller::url('/m/label_generation/generate') ?>" method="POST" target="_blank" class="d-flex flex-column flex-grow-1" id="prod_variant_form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="label_type" value="production">

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Select Stitched Variant <span class="text-danger">*</span></label>
                                        <select name="item_id" id="prod_item_select" class="form-select" required onchange="updateProdPreview()">
                                            <option value="">-- Choose Stitched Production Batch --</option>
                                            <?php foreach ($productionItems as $prod):
                                                $tagCode = QrService::generateTagCode(
                                                    $prod['variant_name'],
                                                    $prod['variant_color'] ?? ($prod['color'] ?? ''),
                                                    $prod['program_no'] ?? '',
                                                    (string)($prod['produced_variant_id'] ?: $prod['variant_id'] ?: $prod['id'])
                                                );
                                            ?>
                                                <option value="<?= $prod['id'] ?>"
                                                        data-name="<?= htmlspecialchars($prod['variant_name']) ?>"
                                                        data-sku="<?= htmlspecialchars($prod['sku_code']) ?>"
                                                        data-tag="<?= htmlspecialchars($tagCode) ?>"
                                                        data-mrp="<?= htmlspecialchars($prod['variant_mrp']) ?>"
                                                        data-price="<?= htmlspecialchars($prod['variant_price'] ?? $prod['variant_mrp']) ?>"
                                                        data-size="<?= htmlspecialchars($prod['variant_size'] ?: 'Standard') ?>"
                                                        data-color="<?= htmlspecialchars($prod['variant_color'] ?? ($prod['color'] ?? 'Standard')) ?>"
                                                        data-cat="<?= htmlspecialchars($prod['category_name'] ?: 'Apparel') ?>"
                                                        data-brand="<?= htmlspecialchars($prod['brand_name'] ?: 'GRM Production') ?>"
                                                        data-log="<?= htmlspecialchars($prod['log_no']) ?>"
                                                        data-pcs="<?= htmlspecialchars($prod['stitched_pieces']) ?>"
                                                        data-program="<?= htmlspecialchars($prod['program_no'] ?: 'Standard Program') ?>"
                                                        data-in-stock="<?= !empty($prod['is_in_stock']) ? '1' : '0' ?>"
                                                        data-qr="<?= htmlspecialchars($tagCode) ?>">
                                                    Batch #<?= htmlspecialchars($prod['log_no']) ?> &bull; <?= htmlspecialchars($prod['variant_name']) ?> [<?= htmlspecialchars($prod['variant_size'] ?: 'Standard') ?>] (<?= htmlspecialchars($prod['sku_code']) ?>) &bull; <?= (int)$prod['stitched_pieces'] ?> pcs <?= !empty($prod['is_in_stock']) ? '&#10003; In Stock' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">From stitching assembly logs.</small>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label small fw-semibold mb-0">Label Copies (N Prints) <span class="text-danger">*</span></label>
                                            <button type="button" class="btn btn-outline-warning btn-sm py-0 px-2" onclick="matchProdQty()">Match Stitched Pcs</button>
                                        </div>
                                        <div class="input-group">
                                            <button type="button" class="btn btn-outline-secondary" onclick="adjustCopies('prod_qty', -1)" aria-label="Decrease"><i class="bi bi-dash-lg"></i></button>
                                            <input type="number" name="qty" id="prod_qty" class="form-control text-center" min="1" max="500" value="24" required>
                                            <button type="button" class="btn btn-outline-secondary" onclick="adjustCopies('prod_qty', 1)" aria-label="Increase"><i class="bi bi-plus-lg"></i></button>
                                            <span class="input-group-text bg-light">Stickers</span>
                                        </div>
                                    </div>

                                    <!-- Live Sticker Preview -->
                                    <div class="p-3 border rounded-3 bg-light mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-bold text-dark small"><i class="bi bi-eye text-warning me-1"></i> Production Tag Preview</span>
                                            <span class="badge bg-warning-subtle text-warning border border-warning font-monospace" id="prod_preview_badge">IN-HOUSE STITCHED</span>
                                        </div>
                                        <div class="border rounded-3 p-3 bg-white shadow-sm" style="border: 1.5px solid #0f172a !important;">
                                            <div class="d-flex align-items-center justify-content-between pb-2 border-bottom border-secondary-subtle" style="border-style: dashed !important;">
                                                <div class="d-flex align-items-center">
                                                    <span style="background-color:#0d6efd;color:#fff;width:17px;height:17px;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;margin-right:6px;">
                                                        <i class="bi bi-check-lg"></i>
                                                    </span>
                                                    <span class="fw-bold text-dark text-uppercase small" style="letter-spacing:0.04em;" id="prod_preview_concern"><?= htmlspecialchars($concernName) ?></span>
                                                </div>
                                                <div class="d-flex align-items-center gap-1">
                                                    <span class="badge font-monospace px-2 py-1" style="background-color:#fbbf24;color:#0f172a;font-size:0.70rem;font-weight:700;" id="prod_preview_size_badge">FREE</span>
                                                    <span class="badge font-monospace px-2 py-1" style="background-color:#e0f2fe;color:#0284c7;border:1px solid #bae6fd;font-size:0.70rem;" id="prod_preview_ref">PP-REF</span>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-3 pt-2">
                                                <div class="d-flex align-items-center justify-content-center bg-white flex-shrink-0" style="width:76px;height:76px;" id="prod_qr_box"></div>
                                                <div class="flex-grow-1 overflow-hidden" style="line-height:1.35;font-size:0.76rem;">
                                                    <div class="fw-bold text-dark text-uppercase mb-1 text-truncate" style="font-size:0.72rem;letter-spacing:0.02em;" id="prod_preview_title">Production Garment</div>
                                                    <div class="text-truncate"><span class="text-secondary">Design:</span> <strong class="text-dark text-uppercase" id="prod_preview_design">-</strong></div>
                                                    <div class="text-truncate"><span class="text-secondary">Size:</span> <strong class="text-dark text-uppercase fw-bold" id="prod_preview_size">FREE</strong></div>
                                                    <div class="text-truncate"><span class="text-secondary">Price:</span> <strong class="text-success fw-bold" id="prod_preview_price">&#8377;0.00</strong></div>
                                                    <div class="text-truncate"><span class="text-secondary">Code:</span> <strong class="font-monospace text-primary fw-bold" id="prod_preview_code_tag">PRO-S-01</strong></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2 mt-auto align-items-center">
                                        <button type="submit" class="btn btn-warning text-dark flex-grow-1 py-2 fw-semibold shadow-sm">
                                            <i class="bi bi-printer-fill me-1"></i> Generate Printable Sheet (N Labels)
                                        </button>
                                        <button type="button" class="btn btn-success py-2 px-3 fw-semibold shadow-sm text-nowrap" id="prod_add_stock_btn" onclick="submitProdToStock()">
                                            <i class="bi bi-box-arrow-in-down me-1"></i> Add to Stock
                                        </button>
                                        <a href="<?= \App\Core\Controller::url('/m/stocks?tab=live_stock') ?>" class="btn btn-outline-success py-2 fw-semibold shadow-sm px-3 d-none text-nowrap" id="prod_in_stock_badge">
                                            <i class="bi bi-check-circle-fill text-success me-1"></i> In Stock
                                        </a>
                                    </div>
                                </form>
                            </div><!-- /prod_variant_ui -->

                        </div><!-- /card-body -->
                    </div>
                </div>

                <!-- ===== RIGHT: Batch Directory Table ===== -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-list-check text-warning"></i>
                                <span>Recent Stitching Production Batches</span>
                            </h6>
                            <input type="text" id="search_prod_table" class="form-control form-control-sm" style="width:220px;" placeholder="Search batch, program...">
                        </div>
                        <div class="table-responsive">
                            <?php if (empty($productionBatches)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-box-seam fs-2 d-block mb-2 opacity-50"></i>
                                    No stitched production batches found.
                                </div>
                            <?php else: ?>
                            <table class="table table-hover align-middle mb-0 table-custom" id="tableProdItems">
                                <thead class="table-light text-muted small">
                                    <tr>
                                        <th style="width:30px">#</th>
                                        <th>Batch &amp; Program</th>
                                        <th>Catalog / Style</th>
                                        <th class="text-center">Total Pcs</th>
                                        <th class="text-center">Variants</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-end pe-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php $bIdx = 1; foreach ($productionBatches as $batch):
                                    $bAllStock    = $batch['is_all_in_stock'];
                                    $bPending     = array_filter($batch['items'], function($it){ return empty($it['is_in_stock']); });
                                    $batchRowId   = 'brows_' . (int)$batch['stitching_id'];
                                ?>
                                    <!-- BATCH HEADER ROW -->
                                    <tr class="prod-row table-warning fw-semibold"
                                        style="cursor:pointer"
                                        data-search="<?= strtolower(htmlspecialchars($batch['batch_no'] . ' ' . $batch['program_no'] . ' ' . $batch['catalog_name'])) ?>"
                                        onclick="toggleBatchRows('<?= $batchRowId ?>')">
                                        <td class="text-muted small"><?= $bIdx++ ?></td>
                                        <td>
                                            <span class="badge bg-dark font-monospace"><?= htmlspecialchars($batch['batch_no']) ?></span>
                                            <div class="small text-dark mt-1 fw-semibold"><?= htmlspecialchars($batch['program_no']) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark text-truncate" style="max-width:150px"><?= htmlspecialchars($batch['catalog_name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($batch['category_name']) ?></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary font-monospace fs-6"><?= (int)$batch['total_pieces'] ?> pcs</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?= count($batch['items']) ?> var</span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($bAllStock): ?>
                                                <span class="badge bg-success-subtle text-success border border-success px-2 py-1 small">
                                                    <i class="bi bi-check-circle-fill me-1"></i>All In Stock
                                                </span>
                                            <?php elseif ($batch['has_in_stock']): ?>
                                                <span class="badge bg-info-subtle text-info border border-info px-2 py-1 small">
                                                    <i class="bi bi-dash-circle me-1"></i>Partial
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1 small">
                                                    <i class="bi bi-clock-history me-1"></i>Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-inline-flex gap-1 align-items-center">
                                                <form action="<?= \App\Core\Controller::url('/m/label_generation/generate') ?>" method="POST" target="_blank" class="d-inline" onsubmit="event.stopPropagation()">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="label_type" value="production">
                                                    <input type="hidden" name="prod_print_mode" value="batch">
                                                    <input type="hidden" name="batch_id" value="<?= (int)$batch['stitching_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning text-dark py-1 px-2 fw-semibold" title="Print all <?= (int)$batch['total_pieces'] ?> labels" onclick="event.stopPropagation()">
                                                        <i class="bi bi-printer-fill me-1"></i>Print All
                                                    </button>
                                                </form>
                                                <?php if (!$bAllStock): ?>
                                                <form action="<?= \App\Core\Controller::url('/m/label/add_to_stock') ?>" method="POST" class="d-inline" onsubmit="event.stopPropagation()">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="item_id" value="batch_<?= (int)$batch['stitching_id'] ?>">
                                                    <input type="hidden" name="tab" value="production">
                                                    <button type="submit" class="btn btn-sm btn-success py-1 px-2 fw-semibold" title="Inward all pending variants into stock" onclick="event.stopPropagation()">
                                                        <i class="bi bi-box-arrow-in-down me-1"></i>Add All
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                <a href="<?= \App\Core\Controller::url('/m/stocks') ?>" class="btn btn-sm btn-outline-success py-1 px-2 fw-semibold" onclick="event.stopPropagation()">
                                                    <i class="bi bi-eye me-1"></i>View Stock
                                                </a>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2"
                                                        onclick="event.stopPropagation(); toggleBatchRows('<?= $batchRowId ?>')"
                                                        title="Expand / Collapse variants">
                                                    <i class="bi bi-chevron-down" id="icon_<?= $batchRowId ?>"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- VARIANT DETAIL ROWS (collapsed by default) -->
                                    <?php $vIdx = 1; foreach ($batch['items'] as $bItem):
                                        $bTagCode = QrService::generateTagCode(
                                            $bItem['variant_name'],
                                            $bItem['variant_color'] ?? ($bItem['color'] ?? ''),
                                            $bItem['program_no'] ?? '',
                                            (string)($bItem['produced_variant_id'] ?: $bItem['variant_id'] ?: $bItem['id'])
                                        );
                                        $bInStock = !empty($bItem['is_in_stock']);
                                    ?>
                                    <tr class="variant-detail-row <?= $batchRowId ?> d-none" style="background:#fefefe; border-left:3px solid #ffc107;">
                                        <td class="text-muted small ps-4"><?= $vIdx++ ?></td>
                                        <td colspan="2">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-secondary"><?= htmlspecialchars($bItem['variant_size'] ?: 'Free') ?></span>
                                                <span class="text-dark small fw-semibold"><?= htmlspecialchars($bItem['variant_name']) ?></span>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($bItem['sku_code']) ?></span>
                                                <span class="ms-1">Color: <?= htmlspecialchars($bItem['variant_color'] ?? ($bItem['color'] ?? '—')) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success font-monospace"><?= (int)($bItem['stitched_pieces'] ?? $bItem['stitching_qty'] ?? 0) ?> pcs</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="font-monospace small text-muted" style="font-size:0.65rem;"><?= htmlspecialchars($bTagCode) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($bInStock): ?>
                                                <span class="badge bg-success-subtle text-success border border-success" style="font-size:0.68rem;">
                                                    <i class="bi bi-check-circle-fill"></i> In Stock
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning" style="font-size:0.68rem;">
                                                    <i class="bi bi-clock-history"></i> Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-inline-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-warning text-dark py-1 px-2 fw-semibold"
                                                        onclick="selectProdItemAndSwitchMode(<?= (int)$bItem['id'] ?>)"
                                                        title="Print labels for this variant (Way 2)">
                                                    <i class="bi bi-printer me-1"></i>Print
                                                </button>
                                                <?php if ($bInStock): ?>
                                                    <a href="<?= \App\Core\Controller::url('/m/stocks') ?>" class="btn btn-sm btn-outline-success py-1 px-2 fw-semibold">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <form action="<?= \App\Core\Controller::url('/m/label/add_to_stock') ?>" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                        <input type="hidden" name="item_id" value="<?= (int)$bItem['id'] ?>">
                                                        <input type="hidden" name="tab" value="production">
                                                        <button type="submit" class="btn btn-sm btn-success py-1 px-2 fw-semibold">
                                                            <i class="bi bi-box-arrow-in-down"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        
    </div>

    <!-- ======================================================== -->
    <!-- AUDIT TRAIL: RECENT LABEL GENERATION JOBS                -->
    <!-- ======================================================== -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-primary fs-5"></i>
                <span>Recent Label Generation Jobs (Audit Trail)</span>
            </h6>
            <span class="badge bg-light text-dark border"><?= count($recentJobs) ?> Recent Print Jobs</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead class="table-light text-muted small">
                    <tr>
                        <th style="width: 50px;">Job ID</th>
                        <th>Stage / Section</th>
                        <th>Printed Item & Code</th>
                        <th>QR Code Reference</th>
                        <th>Copies</th>
                        <th>Printed By</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentJobs)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No label print jobs recorded yet. Choose an item above to generate labels.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentJobs as $job): 
                            $type = $job['label_type'] ?? 'variant';
                            $title = $job['item_title'] ?: ($job['variant_name'] ?? 'Garment Item');
                            $code = $job['item_code'] ?: ($job['sku_code'] ?? 'SKU');
                            $qr = $job['qr_payload'] ?: (!empty($job['category_id']) ? QrService::generatePayload((int)$job['category_id'], (int)$job['subcategory_id'], (int)$job['variant_id']) : 'C?-S?-V?');
                            
                            $badgeClass = 'bg-primary-subtle text-primary border-primary';
                            $badgeLabel = 'Purchase Roll';
                            if ($type === 'purchase_fg') {
                                $badgeClass = 'bg-success-subtle text-success border-success';
                                $badgeLabel = 'Finished Goods';
                            } elseif ($type === 'production') {
                                $badgeClass = 'bg-warning-subtle text-warning border-warning';
                                $badgeLabel = 'Production Stitched';
                            } elseif ($type === 'return') {
                                $badgeClass = 'bg-danger-subtle text-danger border-danger';
                                $badgeLabel = 'Return Restock';
                            } elseif ($type === 'variant') {
                                $badgeClass = 'bg-secondary-subtle text-secondary border-secondary';
                                $badgeLabel = 'Catalog Variant';
                            }
                        ?>
                            <tr>
                                <td class="fw-bold font-monospace text-dark">#<?= (int)$job['id'] ?></td>
                                <td>
                                    <span class="badge border <?= $badgeClass ?>" style="font-size: 0.72rem;"><?= $badgeLabel ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($title) ?></div>
                                    <small class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($code) ?></small>
                                </td>
                                <td><code class="text-primary font-monospace"><?= htmlspecialchars($qr) ?></code></td>
                                <td><span class="badge bg-info-subtle text-info border border-info font-monospace"><?= (int)$job['qty'] ?> Prints</span></td>
                                <td class="small text-muted"><?= htmlspecialchars($job['user_name'] ?? 'System') ?></td>
                                <td class="small text-muted font-monospace"><?= date('d M Y, H:i', strtotime($job['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="<?= \App\Core\Controller::url('/assets/js/qrcode.min.js') ?>"></script>
<script>
// Helper: Build simplified tag code (first 3 letters product - first letter color - 2 letters design)
function makeTagCode(prod, color, design, fallbackId) {
    let p = (prod || 'PRD').toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (p.length < 3) p = (p + 'XXX').substring(0, 3);
    const prod3 = p.substring(0, 3);

    let c = (color || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    const col1 = c.length > 0 ? c.substring(0, 1) : 'X';

    let d = (design || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (!d) {
        let cleanId = String(fallbackId || '').replace(/[^A-Z0-9]/g, '');
        d = cleanId.length >= 2 ? cleanId.substring(0, 2) : (cleanId.length === 1 ? '0' + cleanId : '01');
    } else if (d.length === 1) {
        d = '0' + d;
    } else {
        d = d.substring(0, 2);
    }
    const des2 = d;

    return `${prod3}-${col1}-${des2}`;
}

// Helper: Render crisp client-side QR Code into target box
function renderQr(containerId, text) {
    const el = document.getElementById(containerId);
    if (!el || typeof QRCode === 'undefined') return;
    el.innerHTML = '';
    new QRCode(el, {
        text: text,
        width: 76,
        height: 76,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.M
    });
}

// 1A. Fabric Roll Preview Handler
function updateRollPreview() {
    const sel = document.getElementById('roll_item_select');
    if (!sel) return;
    const titleEl = document.getElementById('roll_preview_title');
    if (titleEl) {
        titleEl.style.display = 'none';
        titleEl.textContent = '';
    }

    const addStockBtn = document.getElementById('roll_add_stock_btn');
    const inStockBadge = document.getElementById('roll_in_stock_badge');

    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        const isInStock = (opt.dataset && (opt.dataset.inward === 'completed' || opt.dataset.inStock === '1'));
        if (addStockBtn) {
            if (isInStock) {
                addStockBtn.classList.add('d-none');
            } else {
                addStockBtn.classList.remove('d-none');
            }
        }
        if (inStockBadge) {
            if (isInStock) {
                inStockBadge.classList.remove('d-none');
            } else {
                inStockBadge.classList.add('d-none');
            }
        }

        const fabric = opt.dataset.fabric || opt.dataset.name || opt.dataset.code || 'Fabric Material';
        const tag = opt.dataset.tag || opt.dataset.qr || makeTagCode(fabric, opt.dataset.color, opt.dataset.pattern || opt.dataset.spec, opt.value);
        const color = (opt.dataset.color || 'STANDARD').toUpperCase();
        const design = (opt.dataset.pattern || opt.dataset.spec || 'PLAIN').toUpperCase();
        const price = parseFloat(opt.dataset.price || 0);
        const ref = opt.dataset.bill ? ('PO-' + opt.dataset.bill) : 'PO-REF';

        const refEl = document.getElementById('roll_preview_ref');
        if (refEl) refEl.textContent = ref;
        const fabEl = document.getElementById('roll_preview_fabric');
        if (fabEl) fabEl.textContent = fabric;
        const colEl = document.getElementById('roll_preview_color');
        if (colEl) colEl.textContent = color;
        const desEl = document.getElementById('roll_preview_design');
        if (desEl) desEl.textContent = design;
        const priEl = document.getElementById('roll_preview_price');
        if (priEl) priEl.textContent = '₹' + price.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const codEl = document.getElementById('roll_preview_code_tag');
        if (codEl) codEl.textContent = tag;
        const badEl = document.getElementById('roll_preview_badge');
        if (badEl) {
            badEl.textContent = isInStock ? 'IN STOCK' : 'RAW MATERIAL';
            badEl.className = isInStock ? 'badge bg-success-subtle text-success border border-success font-monospace' : 'badge bg-primary-subtle text-primary border border-primary font-monospace';
        }

        try {
            renderQr('roll_qr_box', tag);
        } catch (e) {
            console.warn('QR render error:', e);
        }
    } else {
        if (addStockBtn) addStockBtn.classList.remove('d-none');
        if (inStockBadge) inStockBadge.classList.add('d-none');

        const refEl = document.getElementById('roll_preview_ref');
        if (refEl) refEl.textContent = 'PO-REF';
        const fabEl = document.getElementById('roll_preview_fabric');
        if (fabEl) fabEl.textContent = 'Select a fabric roll';
        const colEl = document.getElementById('roll_preview_color');
        if (colEl) colEl.textContent = '-';
        const desEl = document.getElementById('roll_preview_design');
        if (desEl) desEl.textContent = '-';
        const priEl = document.getElementById('roll_preview_price');
        if (priEl) priEl.textContent = '₹0.00';
        const codEl = document.getElementById('roll_preview_code_tag');
        if (codEl) codEl.textContent = '-';
        const badEl = document.getElementById('roll_preview_badge');
        if (badEl) {
            badEl.textContent = 'RAW MATERIAL';
            badEl.className = 'badge bg-primary-subtle text-primary border border-primary font-monospace';
        }
        try {
            renderQr('roll_qr_box', 'ROLL-TAG');
        } catch (e) {
            console.warn('QR render error:', e);
        }
    }
}

function selectRollItem(id) {
    const sel = document.getElementById('roll_item_select');
    if (!sel) return;
    sel.value = String(id);
    updateRollPreview();
    matchRollQty();
    sel.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function matchRollQty() {
    const sel = document.getElementById('roll_item_select');
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        const rawQty = parseFloat(opt.dataset.qty || opt.dataset.rq || '1');
        const qty = Math.max(1, Math.min(500, Math.round(rawQty)));
        const qtyInput = document.getElementById('roll_qty');
        if (qtyInput) {
            qtyInput.value = qty;
        }
    }
}

function submitRollToStock() {
    const select = document.getElementById('roll_item_select');
    if (!select || !select.value) {
        alert('Please select a fabric roll first.');
        return;
    }
    const opt = select.options[select.selectedIndex];
    if (opt && opt.dataset && opt.dataset.inward === 'completed') {
        alert('This fabric roll has already been added to stock.');
        return;
    }
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= \App\Core\Controller::url('/m/label/add_to_stock') ?>';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="item_id" value="${select.value}">
        <input type="hidden" name="tab" value="rolls">
    `;
    document.body.appendChild(form);
    form.submit();
}

// 1B. Other Garments Products & Trims Preview Handler
function updateOtherPreview() {
    const sel = document.getElementById('other_item_select');
    if (!sel) return;
    const addStockBtn = document.getElementById('other_add_stock_btn');
    const inStockBadge = document.getElementById('other_in_stock_badge');

    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        const isInStock = (opt.dataset && (opt.dataset.inward === 'completed' || opt.dataset.inStock === '1'));
        if (addStockBtn) {
            if (isInStock) {
                addStockBtn.classList.add('d-none');
            } else {
                addStockBtn.classList.remove('d-none');
            }
        }
        if (inStockBadge) {
            if (isInStock) {
                inStockBadge.classList.remove('d-none');
            } else {
                inStockBadge.classList.add('d-none');
            }
        }

        const tag = opt.dataset.tag || makeTagCode(opt.dataset.name, opt.dataset.color, opt.dataset.pattern || opt.dataset.spec, opt.value);
        const ref = opt.dataset.bill ? ('BILL #' + opt.dataset.bill) : 'TRIM-REF';
        const color = (opt.dataset.color || 'STANDARD').toUpperCase();
        const design = (opt.dataset.pattern || opt.dataset.spec || 'STANDARD').toUpperCase();
        const price = parseFloat(opt.dataset.price || 0);

        document.getElementById('other_preview_ref').textContent = ref;
        document.getElementById('other_preview_title').textContent = opt.dataset.name || 'Garment Supply';
        document.getElementById('other_preview_item').textContent = opt.dataset.name || 'Trim Item';
        document.getElementById('other_preview_color').textContent = color;
        document.getElementById('other_preview_design').textContent = design;
        document.getElementById('other_preview_price').textContent = '₹' + price.toFixed(2);
        document.getElementById('other_preview_code_tag').textContent = tag;
        
        const badgeEl = document.getElementById('other_preview_badge');
        if (badgeEl) {
            badgeEl.textContent = isInStock ? 'IN STOCK' : 'PURCHASE TRIM';
            badgeEl.className = isInStock ? 'badge bg-success-subtle text-success border border-success font-monospace' : 'badge bg-info-subtle text-info border border-info font-monospace';
        }

        renderQr('other_qr_box', tag);
    } else {
        if (addStockBtn) addStockBtn.classList.remove('d-none');
        if (inStockBadge) inStockBadge.classList.add('d-none');

        document.getElementById('other_preview_ref').textContent = 'TRIM-REF';
        document.getElementById('other_preview_title').textContent = 'SEWING THREAD';
        document.getElementById('other_preview_item').textContent = 'Select a trim item';
        document.getElementById('other_preview_color').textContent = 'BRIGHT WHITE';
        document.getElementById('other_preview_design').textContent = 'STANDARD';
        document.getElementById('other_preview_price').textContent = '₹45.00';
        document.getElementById('other_preview_code_tag').textContent = 'TR-TH-WHT';
        const badgeEl = document.getElementById('other_preview_badge');
        if (badgeEl) {
            badgeEl.textContent = 'PURCHASE TRIM';
            badgeEl.className = 'badge bg-info-subtle text-info border border-info font-monospace';
        }
        renderQr('other_qr_box', 'TR-TH-WHT');
    }
}

function selectOtherItem(id) {
    const sel = document.getElementById('other_item_select');
    if (!sel) return;
    sel.value = id;
    updateOtherPreview();
    matchOtherQty();
    sel.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function matchOtherQty() {
    const sel = document.getElementById('other_item_select');
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        const qty = parseInt(opt.dataset.qty || 1, 10);
        document.getElementById('other_qty').value = qty > 0 ? qty : 1;
    }
}

function submitOtherToStock() {
    const select = document.getElementById('other_item_select');
    if (!select || !select.value) {
        alert('Please select an other garment product item first.');
        return;
    }
    const opt = select.options[select.selectedIndex];
    if (opt && opt.dataset && opt.dataset.inward === 'completed') {
        alert('This item has already been added to stock.');
        return;
    }
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= \App\Core\Controller::url('/m/label/add_to_stock') ?>';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="item_id" value="${select.value}">
        <input type="hidden" name="tab" value="garment_other">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Backward compatibility alias for tests/legacy handlers
function updateRawPreview() {
    updateRollPreview();
    updateOtherPreview();
}
function selectRawItem(id) {
    selectRollItem(id);
    selectOtherItem(id);
}
function matchRawQty() {
    matchRollQty();
}
function submitRawToStock() {
    submitRollToStock();
}

// Tab 2: Purchase FG Preview Handler
function updateFgPreview() {
    const sel = document.getElementById('fg_item_select');
    if (!sel) return;
    const addStockBtn = document.getElementById('fg_add_stock_btn');
    const inStockBadge = document.getElementById('fg_in_stock_badge');

    const opt = sel.options[sel.selectedIndex];
    const mappingCard = document.getElementById('fg_mapping_card');
    const mappedBox = document.getElementById('fg_mapped_box');
    const unmappedBox = document.getElementById('fg_unmapped_box');

    if (opt && opt.value) {
        const isInStock = (opt.dataset && (opt.dataset.inward === 'completed' || opt.dataset.inStock === '1'));
        if (addStockBtn) {
            if (isInStock) {
                addStockBtn.classList.add('d-none');
            } else {
                addStockBtn.classList.remove('d-none');
            }
        }
        if (inStockBadge) {
            if (isInStock) {
                inStockBadge.classList.remove('d-none');
            } else {
                inStockBadge.classList.add('d-none');
            }
        }

        const tag = opt.dataset.tag || makeTagCode(opt.dataset.name, opt.dataset.color, opt.dataset.pattern, opt.value);
        const ref = opt.dataset.bill ? ('BILL #' + opt.dataset.bill) : (opt.dataset.sku || 'PO-FG');
        const color = (opt.dataset.color || 'STANDARD').toUpperCase();
        const design = (opt.dataset.pattern || opt.dataset.spec || 'STANDARD').toUpperCase();
        const price = parseFloat(opt.dataset.price || opt.dataset.mrp || 0);

        document.getElementById('fg_preview_ref').textContent = ref;
        const concernPreview = document.getElementById('fg_preview_concern');
        if (concernPreview) concernPreview.textContent = opt.dataset.concern || concernPreview.dataset.defaultConcern || '';
        document.getElementById('fg_preview_title').textContent = opt.dataset.name || 'Finished Good';
        document.getElementById('fg_preview_cat').textContent = opt.dataset.cat || 'Apparel';
        document.getElementById('fg_preview_color').textContent = color;
        document.getElementById('fg_preview_design').textContent = design;
        const designRow = document.getElementById('fg_preview_design_row');
        if (designRow) designRow.classList.toggle('d-none', !opt.dataset.pattern && !opt.dataset.spec);
        const attributeContainer = document.getElementById('fg_preview_variant_attributes');
        if (attributeContainer) {
            const attributes = JSON.parse(opt.dataset.attributes || '{}');
            attributeContainer.replaceChildren();
            Object.entries(attributes).forEach(([name, value]) => {
                const row = document.createElement('div');
                row.className = 'text-truncate';
                const label = document.createElement('span');
                label.className = 'text-secondary';
                label.textContent = name + ': ';
                const detail = document.createElement('strong');
                detail.className = 'text-dark text-uppercase';
                detail.textContent = value;
                row.append(label, detail);
                attributeContainer.appendChild(row);
            });
            attributeContainer.classList.toggle('d-none', Object.keys(attributes).length === 0);
        }
        const summaryRow = document.getElementById('fg_preview_variant_summary_row');
        const summaryText = document.getElementById('fg_preview_variant_summary');
        if (summaryText) summaryText.textContent = opt.dataset.variantSummary || '';
        if (summaryRow) summaryRow.classList.toggle('d-none', !opt.dataset.variantSummary);
        document.getElementById('fg_preview_price').textContent = '₹' + price.toFixed(2);
        document.getElementById('fg_preview_code_tag').textContent = tag;

        renderQr('fg_qr_box', tag);

        if (mappingCard) mappingCard.style.display = 'block';
        if (opt.dataset.mapped === '1') {
            if (mappedBox) mappedBox.style.display = 'block';
            if (unmappedBox) unmappedBox.style.display = 'none';
            const mTitle = document.getElementById('fg_mapped_title');
            if (mTitle) mTitle.textContent = 'Mapped to ' + opt.dataset.name;
            const mSku = document.getElementById('fg_mapped_sku');
            if (mSku) mSku.textContent = 'Catalog SKU: ' + opt.dataset.sku;
        } else {
            if (mappedBox) mappedBox.style.display = 'none';
            if (unmappedBox) unmappedBox.style.display = 'block';
        }
    } else {
        if (addStockBtn) addStockBtn.classList.remove('d-none');
        if (inStockBadge) inStockBadge.classList.add('d-none');

        document.getElementById('fg_preview_ref').textContent = 'PO-FG';
        const concernPreview = document.getElementById('fg_preview_concern');
        if (concernPreview) concernPreview.textContent = concernPreview.dataset.defaultConcern || '';
        document.getElementById('fg_preview_title').textContent = 'Select Finished Good';
        document.getElementById('fg_preview_cat').textContent = 'Apparel';
        document.getElementById('fg_preview_color').textContent = 'STANDARD';
        document.getElementById('fg_preview_design').textContent = 'STANDARD';
        const designRow = document.getElementById('fg_preview_design_row');
        if (designRow) designRow.classList.remove('d-none');
        const attributeContainer = document.getElementById('fg_preview_variant_attributes');
        if (attributeContainer) {
            attributeContainer.replaceChildren();
            attributeContainer.classList.add('d-none');
        }
        const summaryRow = document.getElementById('fg_preview_variant_summary_row');
        const summaryText = document.getElementById('fg_preview_variant_summary');
        if (summaryText) summaryText.textContent = '';
        if (summaryRow) summaryRow.classList.add('d-none');
        document.getElementById('fg_preview_price').textContent = '₹0.00';
        document.getElementById('fg_preview_code_tag').textContent = 'GAR-S-01';
        renderQr('fg_qr_box', 'GAR-S-01');
        if (mappingCard) mappingCard.style.display = 'none';
    }
}

function selectFgItem(id) {
    const sel = document.getElementById('fg_item_select');
    sel.value = id;
    if (sel.value) {
        updateFgPreview();
        matchFgQty();
        sel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
        alert('This finished good item is not yet mapped to the catalog. Please map it first in Catalog Mapping.');
    }
}

function matchFgQty() {
    const sel = document.getElementById('fg_item_select');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        const qty = parseInt(opt.dataset.qty || 24, 10);
        document.getElementById('fg_qty').value = qty > 0 ? qty : 24;
    }
}

function doMapVariant() {
    const itemSel = document.getElementById('fg_item_select');
    const itemId = itemSel.value;
    const varSel = document.getElementById('fg_map_variant_select');
    const variantId = varSel.value;

    if (!itemId) {
        alert('Please select a purchase finished good item first.');
        return;
    }
    if (!variantId) {
        alert('Please choose a catalog variant to map.');
        return;
    }

    const formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars($csrfToken) ?>');
    formData.append('item_id', itemId);
    formData.append('variant_id', variantId);

    fetch('<?= \App\Core\Controller::url('/m/label_generation/map_variant') ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert(res.message);
            const opt = itemSel.options[itemSel.selectedIndex];
            opt.dataset.mapped = '1';
            opt.dataset.name = res.variant_name;
            opt.dataset.sku = res.sku_code;
            opt.dataset.mrp = res.mrp;
            opt.dataset.size = res.size;
            opt.dataset.tag = makeTagCode(res.variant_name, '', '', res.sku_code);
            opt.dataset.qr = opt.dataset.tag;
            updateFgPreview();
        } else {
            alert('Error: ' + res.message);
        }
    })
    .catch(err => {
        alert('Failed to link variant: ' + err.message);
    });
}


// Tab 3: Production Preview Handler - Way 2 (single variant)
function updateProdPreview() {
    const sel = document.getElementById('prod_item_select');
    if (!sel) return;
    const addStockBtn = document.getElementById('prod_add_stock_btn');
    const inStockBadge = document.getElementById('prod_in_stock_badge');

    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        const isInStock = (opt.dataset && (opt.dataset.inStock === '1' || opt.dataset.inward === 'completed'));
        if (addStockBtn) {
            isInStock ? addStockBtn.classList.add('d-none') : addStockBtn.classList.remove('d-none');
        }
        if (inStockBadge) {
            isInStock ? inStockBadge.classList.remove('d-none') : inStockBadge.classList.add('d-none');
        }

        const tag    = opt.dataset.tag || makeTagCode(opt.dataset.name, opt.dataset.color, opt.dataset.program, opt.value);
        const ref    = opt.dataset.program || opt.dataset.log || 'PP-REF';
        const size   = (opt.dataset.size || 'Free').toUpperCase();
        const price  = parseFloat(opt.dataset.price || opt.dataset.mrp || 0);

        if (document.getElementById('prod_preview_ref')) document.getElementById('prod_preview_ref').textContent = ref;
        if (document.getElementById('prod_preview_size_badge')) document.getElementById('prod_preview_size_badge').textContent = size;
        if (document.getElementById('prod_preview_title')) document.getElementById('prod_preview_title').textContent = opt.dataset.name || 'Production Garment';
        if (document.getElementById('prod_preview_size')) document.getElementById('prod_preview_size').textContent = size;
        if (document.getElementById('prod_preview_price')) document.getElementById('prod_preview_price').textContent = '₹' + price.toFixed(2);
        if (document.getElementById('prod_preview_code_tag')) document.getElementById('prod_preview_code_tag').textContent = tag;

        const badgeEl = document.getElementById('prod_preview_badge');
        if (badgeEl) {
            badgeEl.textContent = isInStock ? 'IN STOCK' : 'IN-HOUSE STITCHED';
            badgeEl.className   = isInStock
                ? 'badge bg-success-subtle text-success border border-success font-monospace'
                : 'badge bg-warning-subtle text-warning border border-warning font-monospace';
        }
        renderQr('prod_qr_box', tag);
    } else {
        if (addStockBtn)   addStockBtn.classList.remove('d-none');
        if (inStockBadge)  inStockBadge.classList.add('d-none');
        if (document.getElementById('prod_preview_ref')) document.getElementById('prod_preview_ref').textContent = 'PP-REF';
        if (document.getElementById('prod_preview_size_badge')) document.getElementById('prod_preview_size_badge').textContent = 'FREE';
        if (document.getElementById('prod_preview_title')) document.getElementById('prod_preview_title').textContent = 'Select Stitched Batch';
        if (document.getElementById('prod_preview_size')) document.getElementById('prod_preview_size').textContent = 'FREE';
        if (document.getElementById('prod_preview_price')) document.getElementById('prod_preview_price').textContent = '₹0.00';
        if (document.getElementById('prod_preview_code_tag')) document.getElementById('prod_preview_code_tag').textContent = 'PRO-S-01';
        const badgeEl = document.getElementById('prod_preview_badge');
        if (badgeEl) {
            badgeEl.textContent = 'IN-HOUSE STITCHED';
            badgeEl.className   = 'badge bg-warning-subtle text-warning border border-warning font-monospace';
        }
        renderQr('prod_qr_box', 'PRO-S-01');
    }
}

// Switch between Way 1 (batch) and Way 2 (variant)
function switchProdMode(mode) {
    const batchUi    = document.getElementById('prod_batch_ui');
    const variantUi  = document.getElementById('prod_variant_ui');
    const batchBtn   = document.getElementById('prod_mode_batch_btn');
    const variantBtn = document.getElementById('prod_mode_variant_btn');
    const hintText   = document.getElementById('prod_mode_hint_text');

    if (mode === 'batch') {
        batchUi.classList.remove('d-none');
        variantUi.classList.add('d-none');
        batchBtn.classList.replace('btn-outline-secondary', 'btn-warning');
        batchBtn.classList.add('text-dark');
        variantBtn.classList.replace('btn-warning', 'btn-outline-secondary');
        variantBtn.classList.remove('text-dark');
        if (hintText) hintText.textContent = 'Generate all variants of a batch at once (e.g. 70 labels across 7 sizes)';
    } else {
        variantUi.classList.remove('d-none');
        batchUi.classList.add('d-none');
        variantBtn.classList.replace('btn-outline-secondary', 'btn-warning');
        variantBtn.classList.add('text-dark');
        batchBtn.classList.replace('btn-warning', 'btn-outline-secondary');
        batchBtn.classList.remove('text-dark');
        if (hintText) hintText.textContent = 'Generate N labels for one specific size/color variant';
        updateProdPreview();
    }
}

// Called when user selects a batch in Way 1 dropdown
function onBatchSelect() {
    const sel = document.getElementById('prod_batch_select');
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];

    const printBtn    = document.getElementById('prod_batch_print_btn');
    const stockBtn    = document.getElementById('prod_batch_stock_btn');
    const inStockBtn  = document.getElementById('prod_batch_in_stock_btn');
    const hiddenBatch = document.getElementById('hidden_batch_id');
    const hiddenStock = document.getElementById('hidden_batch_stock_id');
    const summary     = document.getElementById('prod_batch_summary');
    const summaryText = document.getElementById('prod_batch_summary_text');
    const tableWrap   = document.getElementById('prod_batch_table_wrap');
    const tbody       = document.getElementById('prod_batch_variant_tbody');
    const noteEl      = document.getElementById('prod_batch_stock_note');

    if (!opt || !opt.value) {
        if (printBtn)   printBtn.disabled = true;
        if (stockBtn)   { stockBtn.disabled = true; stockBtn.classList.remove('d-none'); }
        if (inStockBtn) inStockBtn.classList.add('d-none');
        if (summary)    summary.classList.add('d-none');
        if (tableWrap)  tableWrap.classList.add('d-none');
        return;
    }

    const batchId       = opt.value;
    const totalPcs      = opt.dataset.totalPcs || 0;
    const variantCount  = opt.dataset.variantCount || 0;
    const pendingCount  = parseInt(opt.dataset.pendingCount || 0, 10);
    const isAllInStock  = opt.dataset.isAllInStock === '1';
    const batchNo       = opt.dataset.batchNo || opt.dataset.logNo || batchId;
    const program       = opt.dataset.program || '';
    const catalog       = opt.dataset.catalog || '';

    // Update hidden inputs
    if (hiddenBatch) hiddenBatch.value = batchId;
    if (hiddenStock) hiddenStock.value = 'batch_' + batchId;

    // Enable print always
    if (printBtn) printBtn.disabled = false;

    // Toggle Add to Stock vs In Stock View button
    if (isAllInStock) {
        if (stockBtn)   stockBtn.classList.add('d-none');
        if (inStockBtn) inStockBtn.classList.remove('d-none');
    } else {
        if (stockBtn) {
            stockBtn.classList.remove('d-none');
            stockBtn.disabled = false;
            stockBtn.title = 'Inward ' + pendingCount + ' pending variants into stock';
        }
        if (inStockBtn) inStockBtn.classList.add('d-none');
    }
    if (noteEl) {
        noteEl.textContent = isAllInStock
            ? '✓ All variants of this batch are already in stock.'
            : pendingCount + ' variant(s) pending inward out of ' + variantCount + ' total.';
    }

    // Summary banner
    if (summary && summaryText) {
        summaryText.textContent =
            'Batch #' + batchNo + ' • ' + program +
            ' • Will generate ' + totalPcs + ' labels across ' + variantCount + ' variants' +
            (catalog ? ' (' + catalog + ')' : '');
        summary.classList.remove('d-none');
    }

    // Build variant preview table
    if (tableWrap && tbody) {
        tableWrap.classList.remove('d-none');
        tbody.innerHTML = '';
        let items = [];
        try { items = JSON.parse(opt.dataset.items || '[]'); } catch(e) {}
        items.forEach(function(item, idx) {
            const inStock = item.is_in_stock == 1 || item.is_in_stock === true;
            const size    = item.variant_size || item.size || 'Free';
            const color   = item.variant_color || item.color || '—';
            const sku     = item.sku_code || '—';
            const pcs     = item.stitched_pieces || item.stitching_qty || 0;
            const statusHtml = inStock
                ? '<span class="badge bg-success-subtle text-success border border-success" style="font-size:0.68rem;"><i class="bi bi-check-circle-fill"></i> In Stock</span>'
                : '<span class="badge bg-warning-subtle text-warning border border-warning" style="font-size:0.68rem;"><i class="bi bi-clock-history"></i> Pending</span>';
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td class="text-muted small">' + (idx+1) + '</td>' +
                '<td><span class="badge bg-secondary">' + size + '</span></td>' +
                '<td class="small">' + color + '</td>' +
                '<td class="small font-monospace">' + sku + '</td>' +
                '<td class="text-center"><span class="badge bg-success font-monospace">' + pcs + ' pcs</span></td>' +
                '<td class="text-center">' + statusHtml + '</td>';
            tbody.appendChild(tr);
        });
    }
}

// Way 1 form validation
function validateBatchSelect() {
    const sel = document.getElementById('prod_batch_select');
    if (!sel || !sel.value) {
        alert('Please choose a stitched production batch first.');
        return false;
    }
    return true;
}

// Way 1 stock form validation
function validateBatchStockSelect() {
    const sel = document.getElementById('prod_batch_select');
    if (!sel || !sel.value) {
        alert('Please choose a stitched production batch first.');
        return false;
    }
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.isAllInStock === '1') {
        alert('All variants of this batch are already in stock.');
        return false;
    }
    return true;
}

// Way 2: select a specific item and switch to variant mode
function selectProdItemAndSwitchMode(id) {
    switchProdMode('variant');
    const sel = document.getElementById('prod_item_select');
    if (!sel) return;
    sel.value = id;
    updateProdPreview();
    matchProdQty();
    sel.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Way 2: existing helpers
function selectProdItem(id) {
    switchProdMode('variant');
    const sel = document.getElementById('prod_item_select');
    if (!sel) return;
    sel.value = id;
    updateProdPreview();
    matchProdQty();
    sel.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function matchProdQty() {
    const sel = document.getElementById('prod_item_select');
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        const pcs = parseInt(opt.dataset.pcs || 24, 10);
        const qtyEl = document.getElementById('prod_qty');
        if (qtyEl) qtyEl.value = pcs > 0 ? pcs : 24;
    }
}

function submitProdToStock() {
    const select = document.getElementById('prod_item_select');
    if (!select || !select.value) {
        alert('Please choose a stitched production batch / item first.');
        return;
    }
    const opt = select.options[select.selectedIndex];
    if (opt && opt.dataset && (opt.dataset.inStock === '1' || opt.dataset.inward === 'completed')) {
        alert('This stitched production item has already been added to stock.');
        return;
    }
    const val     = select.value;
    const isProd  = val.startsWith('prod_');
    const cleanId = isProd ? val.replace('prod_', '') : val;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= \App\Core\Controller::url('/m/label/add_to_stock') ?>';
    form.innerHTML =
        '<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">' +
        '<input type="hidden" name="item_id" value="' + cleanId + '">' +
        '<input type="hidden" name="tab" value="production">';
    document.body.appendChild(form);
    form.submit();
}

// Toggle expand/collapse of variant detail rows in the right table
function toggleBatchRows(rowClass) {
    const rows = document.querySelectorAll('.' + rowClass);
    const icon = document.getElementById('icon_' + rowClass);
    let isHidden = false;
    rows.forEach(function(r) {
        if (r.classList.contains('d-none')) isHidden = true;
    });
    rows.forEach(function(r) {
        isHidden ? r.classList.remove('d-none') : r.classList.add('d-none');
    });
    if (icon) {
        icon.className = isHidden ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
    }
}


function adjustCopies(inputId, delta) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const min = parseInt(input.min || '1', 10);
    const max = parseInt(input.max || '500', 10);
    const val = (input.value || '').trim();
    if (val === '') {
        input.value = String(delta > 0 ? min : min);
        return;
    }
    const current = parseInt(val, 10);
    input.value = String(Math.min(max, Math.max(min, current + delta)));
}

function submitFgToStock() {
    const select = document.getElementById('fg_item_select');
    if (!select || !select.value) {
        alert('Please select a finished goods item first.');
        return;
    }
    const opt = select.options[select.selectedIndex];
    if (opt && opt.dataset && (opt.dataset.inward === 'completed' || opt.dataset.inStock === '1')) {
        alert('This finished goods item has already been added to stock.');
        return;
    }
    const val = select.value;
    const isProd = val.startsWith('prod_');
    const cleanId = isProd ? val.replace('prod_', '') : val;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= \App\Core\Controller::url('/m/label/add_to_stock') ?>';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="item_id" value="${cleanId}">
        <input type="hidden" name="tab" value="${isProd ? 'production' : 'finished_goods'}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Client-side search filters for tables
function setupTableSearch(inputId, rowClass) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('input', function() {
        const q = (this.value || '').trim().toLowerCase();
        document.querySelectorAll('.' + rowClass).forEach(function(row) {
            const text = row.dataset.search || '';
            row.style.display = (!q || text.includes(q)) ? '' : 'none';
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    setupTableSearch('search_rolls_table', 'roll-row');
    setupTableSearch('search_other_table', 'other-row');
    setupTableSearch('search_raw_table', 'raw-row');
    setupTableSearch('search_fg_table', 'fg-row');
    setupTableSearch('search_prod_table', 'prod-row');


    // Auto-select and attach listeners for fabric roll select
    const rollSel = document.getElementById('roll_item_select');
    if (rollSel) {
        rollSel.addEventListener('change', function() {
            updateRollPreview();
            matchRollQty();
        });
        const urlParams = new URLSearchParams(window.location.search);
        const urlItemId = urlParams.get('item_id');
        if (urlItemId) {
            rollSel.value = urlItemId;
        } else if (!rollSel.value && rollSel.options.length === 2 && rollSel.options[1].value) {
            rollSel.selectedIndex = 1;
        }
    }

    // Initialize previews
    updateRollPreview();
    if (rollSel && rollSel.value) {
        matchRollQty();
    }
    updateOtherPreview();
    updateFgPreview();
    updateProdPreview();

});
</script>
